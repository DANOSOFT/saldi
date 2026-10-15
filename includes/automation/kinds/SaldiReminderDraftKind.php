<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiReminderDraftKind.php --- ai/mcp-assistant --- 2026.10.03
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// Unsent, unposted first reminders with legacy grace days, fee and compounded interest.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Port the R1 document branch with eligibility and exact-row undo.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261003 CL/LH Without open_item_ids, take every eligible overdue item of the customer.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiMasterDraftKind.php';

final class SaldiReminderDraftKind extends SaldiMasterDraftKind
{
	public function name(): string
	{
		return 'reminder.draft';
	}
	public function actionClass(): string
	{
		return 'reversible_draft';
	}
	public function proposePermission(): string
	{
		return 'debtors.draft';
	}
	public function decidePermission(array $item): string
	{
		return 'debtors.draft';
	}
	/** Count the begun months used by the legacy R1 interest calculation. */
	private function interestMonths(string $due, string $today): int
	{
		$d = new DateTimeImmutable($due);
		$t = new DateTimeImmutable($today);
		$months = ((int)$t->format('Y') - (int)$d->format('Y')) * 12 + (int)$t->format('m') - (int)$d->format('m');
		return max(0, $months + ((int)$t->format('d') >= (int)$d->format('d') ? 1 : 0));
	}
	/** R1 compounds once per begun month and rounds only the final interest to cents. */
	private function interest(SaldiDb $db, string $rate, string $due, string $amount, string $today): string
	{
		$months = $this->interestMonths($due, $today);
		if ($months <= 0 || !preg_match('/[1-9]/', $rate)) {
			return '0.000';
		}
		$cast = $db->isPostgres() ? 'INTEGER' : 'SIGNED';
		return $this->calculate($db, 'ROUND(CAST(? AS DECIMAL(30,9)) * (POWER(1 + CAST(? AS DECIMAL(30,9)) / 100, CAST(? AS ' . $cast . ')) - 1), 2)', [$amount, $rate, $months]);
	}
	/** @return array{payload:array,header:array,lines:list<array>,posts:list<array>,fee:string,currency:string,subject:array} */
	/** @return list<int> Eligible overdue open-item ids for one customer, oldest due date first. */
	private function eligibleItemIds(SaldiDb $db, array $customer): array
	{
		$div = $db->one("SELECT box5 FROM grupper WHERE art = 'DIV' AND kodenr = 4 ORDER BY id LIMIT 1") ?? [];
		$today = $this->today();
		$cutoff = (new DateTimeImmutable($today))->modify('-' . max(0, (int)ifset($div, 'box5', 0)) . ' days')->format('Y-m-d');
		$cast = $db->isPostgres() ? 'CAST(o.id AS VARCHAR)' : 'CAST(o.id AS CHAR)';
		$rows = $db->all("SELECT o.id FROM openpost o WHERE o.konto_id = ? AND COALESCE(o.udlignet, '') <> '1' AND o.amount > 0
			AND o.forfaldsdate IS NOT NULL AND o.forfaldsdate < ? AND o.forfaldsdate <= ?
			AND NOT EXISTS (SELECT 1 FROM ordrelinjer l JOIN ordrer r ON r.id = l.ordre_id WHERE l.enhed = " . $cast . " AND r.art LIKE 'R%' AND COALESCE(r.betalt, '') <> 'on')
			ORDER BY o.forfaldsdate, o.id LIMIT 30", [(int)$customer['id'], $today, $cutoff]);
		return array_map(static fn(array $r): int => (int)$r['id'], $rows);
	}
	private function prepared(SaldiTenantContext $ctx, SaldiDb $db, array $input, bool $approval = false): array
	{
		$this->keys($input, ['customer_account','open_item_ids','level','note','reminder_date']);
		if (ifset($input, 'level', 1) !== 1) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Only reminder level 1 is supported', ['fields' => ['$.level']]);
		}
		if ($db->inTransaction()) {
			$this->orderLock($db);
		}
		$customer = $this->party($db, ifset($input, 'customer_account'), 'D');
		$ids = ifset($input, 'open_item_ids');
		if ($ids === null || $ids === []) {
			// "Lav en rykker til Rikke Henriksen": without chosen items, take every eligible overdue
			// item (past the grace days, positive, not on an open reminder), oldest first, max 30.
			// The resolved ids are stored in the payload, so approval re-checks exactly these.
			$ids = $this->eligibleItemIds($db, $customer);
			if ($ids === []) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'The customer has no overdue items that can be reminded', ['reason' => 'no_overdue_items', 'fields' => ['$.open_item_ids']]);
			}
		}
		if (!is_array($ids) || !array_is_list($ids) || count($ids) < 1 || count($ids) > 30 || count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected 1 to 30 distinct open item ids');
		}
		foreach ($ids as $id) {
			if (!is_int($id) || $id <= 0) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid open item id');
			}
		}
		sort($ids, SORT_NUMERIC);
		$p = ['customer_account' => (string)$customer['kontonr'], 'open_item_ids' => $ids, 'level' => 1];
		if (array_key_exists('note', $input)) {
			$p['note'] = $this->string($input['note'], 0, 200, 'note');
		}
		$lock = $db->inTransaction() ? ' FOR UPDATE' : '';
		$div = $db->one("SELECT box5, box6 FROM grupper WHERE art = 'DIV' AND kodenr = 4 ORDER BY id LIMIT 1" . $lock) ?? [];
		$today = $this->today();
		$p['reminder_date'] = $this->date(ifset($input, 'reminder_date', $today), 'reminder_date');
		$date = $p['reminder_date'];
		$grace = max(0, (int)ifset($div, 'box5', 0));
		$cutoff = (new DateTimeImmutable($today))->modify('-' . $grace . ' days')->format('Y-m-d');
		$posts = [];
		foreach ($ids as $index => $id) {
			$post = $db->one('SELECT * FROM openpost WHERE id = ?' . $lock, [$id]);
			$due = (string)ifset($post, 'forfaldsdate', '');
			$already = $db->value("SELECT 1 FROM ordrelinjer l JOIN ordrer o ON o.id = l.ordre_id WHERE l.enhed = ? AND o.art LIKE 'R%' AND COALESCE(o.betalt, '') <> 'on' LIMIT 1", [(string)$id]) !== null;
			if ($post && !$already && !empty($post['refnr'])) {
				$already = $db->value("SELECT 1 FROM ordrer WHERE id = ? AND art LIKE 'R%' AND COALESCE(betalt, '') <> 'on' LIMIT 1", [$post['refnr']]) !== null;
			}
			if (!$post || (int)$post['konto_id'] !== (int)$customer['id'] || $post['udlignet'] === '1' || $due === '' || $due >= $today || $due > $cutoff || $already) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Open item ' . $id . ' is not eligible for a reminder', ['open_item_id' => $id, 'fields' => ['$.open_item_ids[' . $index . ']']]);
			}
			if (!$db->value('SELECT CAST(? AS DECIMAL(30,9)) > 0', [(string)$post['amount']])) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Open item ' . $id . ' must have positive outstanding debt', ['reason' => 'nonpositive_debt', 'open_item_id' => $id]);
			}
			$posts[] = $post;
		}
		$credits = $db->all("SELECT * FROM openpost WHERE konto_id = ? AND COALESCE(udlignet, '') <> '1' AND amount < 0 ORDER BY id" . $lock, [$customer['id']]);
		$net = '0.000';
		foreach (array_merge($posts, $credits) as $post) {
			$net = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) + CAST(? AS DECIMAL(30,9))', [$net, (string)$post['amount']]);
		}
		if (!$db->value('SELECT CAST(? AS DECIMAL(30,9)) > 0', [$net])) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Selected debt minus unsettled credits must have a positive net open balance', ['reason' => 'nonpositive_net_balance']);
		}
		$group = $db->one("SELECT * FROM grupper WHERE art = 'DG' AND kodenr = ? ORDER BY id LIMIT 1" . $lock, [(int)$customer['gruppe']]);
		if (!$group) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Customer group missing');
		}
		$currency = trim((string)$group['box3']);
		$currency = in_array($currency, ['', '-', 'DKK'], true) ? 'DKK' : $currency;
		$rate = '100.000';
		if ($approval) {
			[$currency, $rate] = $this->currency($db, $currency, $today);
		}
		$header = ['konto_id' => (int)$customer['id']];
		foreach (['kontonr','firmanavn','addr1','addr2','postnr','bynavn','land','betalingsbet','cvrnr','ean','institution','notes','email','kontakt'] as $field) {
			$header[$field] = (string)ifset($customer, $field, '');
		}
		$days = (int)ifset($div, 'box6', 0);
		$header['betalingsdage'] = $days ?: (int)ifset($customer, 'betalingsdage', 0);
		if ($days) {
			$header['betalingsbet'] = 'Netto';
		}
		$ref = !empty($customer['kontoansvarlig']) ? (string)$db->value('SELECT navn FROM ansatte WHERE id = ?', [$customer['kontoansvarlig']]) : '';
		$header += ['art' => 'R1', 'ordredate' => $date, 'levdate' => $date, 'fakturadate' => $date, 'momssats' => '0.000', 'ref' => $ref, 'status' => 2, 'valuta' => $currency, 'valutakurs' => $rate, 'betalt' => '-', 'felt_5' => ''];
		if (isset($p['note'])) {
			$header['notes'] = $p['note'];
		}
		$forms = $db->all("SELECT * FROM formularer WHERE beskrivelse = 'GEBYR' AND formular = 6 ORDER BY id" . $lock);
		$feeProduct = null;
		$interestProduct = null;
		$interestRate = '0.000';
		foreach ($forms as $form) {
			$interestRate = (string)ifset($form, 'str', '0.000');
			if (!$feeProduct && !empty($form['xb'])) {
				$feeProduct = $db->one('SELECT * FROM varer WHERE id = ?' . $lock, [(int)$form['xb']]);
			}
			if (!$interestProduct && !empty($form['yb'])) {
				$interestProduct = $db->one('SELECT * FROM varer WHERE id = ?' . $lock, [(int)$form['yb']]);
			}
		}
		$lines = [];
		$sum = '0.000';
		foreach ($posts as $post) {
			$description = (string)$post['beskrivelse'];
			$postCurrency = ifset($post, 'valuta') ?: 'DKK';
			if ($postCurrency !== $currency) {
				$description .= ' (' . $postCurrency . ' ' . str_replace('.', ',', $this->money((string)$post['amount'])) . ')';
			}
			$lines[] = ['posnr' => count($lines) + 1, 'enhed' => (string)$post['id'], 'serienr' => $post['forfaldsdate'], 'beskrivelse' => $description];
			$interest = $approval ? $this->interest($db, $interestRate, $post['forfaldsdate'], (string)$post['amount'], $today) : '0.000';
			if ($interestProduct && preg_match('/[1-9]/', $interest)) {
				$lines[] = ['posnr' => count($lines) + 1, 'vare_id' => (int)$interestProduct['id'], 'varenr' => (string)$interestProduct['varenr'], 'serienr' => $today, 'beskrivelse' => (string)$interestProduct['beskrivelse'], 'antal' => '1.000', 'pris' => $interest];
				$sum = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) + CAST(? AS DECIMAL(30,9))', [$sum, $interest]);
			}
		}
		$fee = '0.000';
		if ($feeProduct) {
			$fee = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) * 100 / CAST(? AS DECIMAL(30,9))', [(string)ifset($feeProduct, 'salgspris', '0.000'), $rate]);
			$lines[] = ['posnr' => count($lines) + 1, 'varenr' => (string)$feeProduct['varenr'], 'vare_id' => (int)$feeProduct['id'], 'beskrivelse' => (string)$feeProduct['beskrivelse'], 'antal' => '1.000', 'pris' => $fee, 'serienr' => $today];
			$sum = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) + CAST(? AS DECIMAL(30,9))', [$sum, $fee]);
		}
		$header['sum'] = $sum;
		return ['payload' => $p, 'header' => $header, 'lines' => $lines, 'posts' => $posts, 'fee' => $fee, 'currency' => $currency,
			'subject' => ['payload' => $p, 'customer' => $customer, 'posts' => $posts, 'credits' => $credits, 'div' => $div, 'group' => $group, 'forms' => $forms, 'fee_product' => $feeProduct, 'interest_product' => $interestProduct]];
	}
	/** @return array{payload:array,title:string,subject_type:?string,subject_id:?string,proposed_rev:?string,dedupe_key:?string,visibility:string,assignee_permission:?string,owner_user_id:?int} */
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$state = $this->prepared($ctx, $db, $input);
		$n = $this->normalized($ctx, $state['payload'], $this->text($ctx, $db, 5905) . ': ' . $state['header']['firmanavn'], null, 'permission', 'debtors.draft');
		$n['subject_type'] = 'debtor';
		$n['subject_id'] = (string)$state['header']['konto_id'];
		$n['proposed_rev'] = $this->hash($state['subject']);
		return $n;
	}
	public function currentRevision(SaldiTenantContext $ctx, SaldiDb $db, array $item): ?string
	{
		return $this->hash($this->prepared($ctx, $db, $item['payload'])['subject']);
	}
	/** @return array{order_id:int,reminder_number:int,rows_sha256:string,screen:string,fee:string,interest:string,currency:string,currency_rate:string,approval_date:string,interest_months:list<array{open_item_id:int,months:int}>} */
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$this->orderLock($db);
		$state = $this->prepared($ctx, $db, $item['payload'], true);
		if (isset($item['proposed_rev']) && $item['proposed_rev'] !== $this->hash($state['subject'])) {
			throw new SaldiRuntimeError('STALE_CONTEXT', 'Reminder inputs changed');
		}
		$number = (int)$db->value("SELECT COALESCE(MAX(ordrenr), 0) + 1 FROM ordrer WHERE art IN ('R1','R2','R3')");
		$id = $db->insert('ordrer', ['ordrenr' => $number, 'fakturanr' => $number . '-1'] + $state['header']);
		foreach ($state['lines'] as $line) {
			$db->insert('ordrelinjer', ['ordre_id' => $id] + $line);
		}
		return ['order_id' => $id, 'reminder_number' => $number, 'rows_sha256' => $this->hash($this->orderRows($db, $id)), 'screen' => SaldiScreens::link('reminder', $id), 'fee' => $state['fee'], 'interest' => $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) - CAST(? AS DECIMAL(30,9))', [$state['header']['sum'], $state['fee']]), 'currency' => $state['currency'], 'currency_rate' => $state['header']['valutakurs'], 'approval_date' => $this->today(), 'interest_months' => array_map(fn(array $post): array => ['open_item_id' => (int)$post['id'], 'months' => $this->interestMonths($post['forfaldsdate'], $this->today())], $state['posts'])];
	}
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool
	{
		return $this->intactOrder($db, $item, 2, 'R1');
	}
	/** Render even a decided item whose open posts have since been paid. @return array{columns:list<string>,rows:list<array>,fields:list<array>} */
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$p = $item['payload'];
		$rows = [];
		foreach ($p['open_item_ids'] as $id) {
			$post = $db->one('SELECT faktnr, forfaldsdate, amount, valuta FROM openpost WHERE id = ?', [$id]);
			$rows[] = [(string)ifset($post, 'faktnr', (string)$id), (string)ifset($post, 'forfaldsdate', ''), $this->money((string)ifset($post, 'amount', '0.000')) . ' ' . (ifset($post, 'valuta') ?: 'DKK')];
		}
		$customer = $db->one("SELECT a.firmanavn, g.box3 FROM adresser a LEFT JOIN grupper g ON g.kodenr = a.gruppe AND g.art = 'DG' WHERE a.art = 'D' AND a.kontonr = ? ORDER BY g.id LIMIT 1", [$p['customer_account']]);
		$fee = '0.000';
		$interest = '0.000';
		$currency = ifset($customer, 'box3') ?: 'DKK';
		if (isset($item['result']['order_id'])) {
			$fee = (string)($db->value("SELECT SUM(pris) FROM ordrelinjer WHERE ordre_id = ? AND vare_id IN (SELECT xb FROM formularer WHERE beskrivelse = 'GEBYR' AND formular = 6)", [$item['result']['order_id']]) ?? '0.000');
		} else {
			$price = $db->value("SELECT v.salgspris FROM varer v WHERE v.id IN (SELECT xb FROM formularer WHERE beskrivelse = 'GEBYR' AND formular = 6) ORDER BY v.id LIMIT 1");
			if ($price !== null) {
				try {
					[, $rate] = $this->currency($db, $currency, $this->today());
					$fee = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) * 100 / CAST(? AS DECIMAL(30,9))', [(string)$price, $rate]);
				} catch (SaldiRuntimeError $e) {
					// A missing live rate must not prevent reading or rejecting an old proposal.
					$fee = null;
				}
			}
		}
		if (isset($item['result']['order_id'])) {
			$interest = (string)($db->value("SELECT SUM(pris) FROM ordrelinjer WHERE ordre_id = ? AND vare_id IN (SELECT yb FROM formularer WHERE beskrivelse = 'GEBYR' AND formular = 6)", [$item['result']['order_id']]) ?? '0.000');
		} else {
			$rate = (string)($db->value("SELECT str FROM formularer WHERE beskrivelse = 'GEBYR' AND formular = 6 ORDER BY id DESC LIMIT 1") ?? '0.000');
			$hasProduct = $db->value("SELECT 1 FROM varer WHERE id IN (SELECT yb FROM formularer WHERE beskrivelse = 'GEBYR' AND formular = 6) LIMIT 1") !== null;
			if ($hasProduct) {
				foreach ($p['open_item_ids'] as $id) {
					$post = $db->one('SELECT amount, forfaldsdate FROM openpost WHERE id = ?', [$id]);
					if ($post && $post['forfaldsdate']) {
						$lineInterest = $this->interest($db, $rate, $post['forfaldsdate'], (string)$post['amount'], $this->today());
						$interest = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) + CAST(? AS DECIMAL(30,9))', [$interest, $lineInterest]);
					}
				}
			}
		}
		if (isset($item['result']['fee'], $item['result']['interest'], $item['result']['currency'])) {
			$fee = $item['result']['fee'];
			$interest = $item['result']['interest'];
			$currency = $item['result']['currency'];
		}
		return ['columns' => array_map(fn(int $id): string => $this->text($ctx, $db, $id), [5929,5930,5746]), 'rows' => $rows, 'fields' => [['label' => $this->text($ctx, $db, 35, 'Kunde'), 'value' => $p['customer_account'] . ' ' . ifset($customer, 'firmanavn', '')], ['label' => $this->text($ctx, $db, 6044, 'Gebyr'), 'value' => $fee === null ? $this->text($ctx, $db, 5740) : $this->money($fee) . ' ' . $currency], ['label' => $this->text($ctx, $db, 6046, 'Rente'), 'value' => $this->money($interest) . ' ' . $currency], ['label' => $this->text($ctx, $db, 2881, 'Notat'), 'value' => (string)ifset($p, 'note', '')]]];
	}
}
