<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiJournalCheck.php --- ai/mcp-assistant --- 2026.10.05
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
// Read-only pre-posting checks, ported from kontroller() and bogfor.php.
// No posting include, recalculation, journal save or document repair is executed.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.

declare(strict_types=1);

require_once __DIR__ . '/SaldiPostingSuggester.php';
require_once __DIR__ . '/kinds/SaldiSettlementKind.php';

final class SaldiJournalCheck
{
	private array $errors = [];
	private array $warnings = [];
	private array $accounts = [];
	private array $parties = [];
	private array $rules = [];
	private array $medians = [];
	private bool $hidden;
	private array $duplicatePairs = [];

	private function __construct(private SaldiTenantContext $ctx, private SaldiDb $db)
	{
		$this->hidden = SaldiPermissionService::revenueHidden($ctx, $db);
	}
	/**
	 * @return array{
	 *   journal_id:int,status:string,ready:bool,
	 *   errors:list<array{code:string,severity:string,line_ids:list<int>,bilag:?int,message_da:string,detail:array|stdClass}>,
	 *   warnings:list<array{code:string,severity:string,line_ids:list<int>,bilag:?int,message_da:string,detail:array|stdClass}>
	 * }
	 */
	public static function run(SaldiTenantContext $ctx, SaldiDb $db, int $kladdeId): array
	{
		SaldiPermissionService::require($ctx, $db, 'journal.read');
		if ($kladdeId < 1) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid journal id');
		}
		return (new self($ctx, $db))->check($kladdeId);
	}
	/** Add one structured finding; messages are always Danish, independently of UI language. */
	private function finding(string $code, string $severity, array $rows, int $textId, string $fallback, array $detail = [], array $format = []): void
	{
		$message = SaldiText::get($this->db, $textId, 1, $fallback);
		$finding = ['code' => $code, 'severity' => $severity,
			'line_ids' => array_map(static fn(array $row): int => (int)$row['id'], $rows),
			'bilag' => $rows && $rows[0]['bilag'] !== null ? (int)$rows[0]['bilag'] : null,
			'message_da' => $format ? vsprintf($message, $format) : $message, 'detail' => $detail ?: new stdClass()];
		if ($severity === 'error') {
			$this->errors[] = $finding;
		} else {
			$this->warnings[] = $finding;
		}
	}
	/** @return array{journal_id:int,status:string,ready:bool,errors:list<array>,warnings:list<array>} */
	private function check(int $id): array
	{
		$journal = $this->db->one('SELECT bogfort FROM kladdeliste WHERE id = ?', [$id]);
		if (!$journal) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Journal not found');
		}
		$rows = $this->db->all('SELECT * FROM kassekladde WHERE kladde_id = ? ORDER BY bilag, id', [$id]);
		if ($journal['bogfort'] !== '-') {
			$this->finding('not_open', 'error', $rows, 6200, 'Kladden er ikke åben.');
		}
		$year = $this->db->one("SELECT kodenr, box1, box2, box3, box4, box5 FROM grupper WHERE art = 'RA' AND kodenr = ?", [$this->ctx->fiscalYear]);
		if (!$year) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Fiscal year unavailable');
		}
		$bounds = SaldiToolRuntime::year($year);
		foreach ($this->db->all('SELECT kontonr, kontotype, lukket, moms FROM kontoplan WHERE regnskabsaar = ?', [$this->ctx->fiscalYear]) as $account) {
			$this->accounts[(string)(int)$account['kontonr']] = $account;
		}
		foreach ($this->db->all("SELECT kontonr, art, lukket FROM adresser WHERE art IN ('D','K')") as $party) {
			$this->parties[$party['art'] . ':' . (int)$party['kontonr']] = $party;
		}
		$this->rules = SaldiPostingRules::active($this->db);
		$groups = [];
		$total = 0;
		$foreignRows = 0;
		foreach ($rows as $row) {
			$bilag = (string)$row['bilag'];
			$groups[$bilag] ??= ['rows' => [], 'total' => 0, 'document' => false, 'hidden' => false];
			$groups[$bilag]['rows'][] = $row;
			$groups[$bilag]['document'] = $groups[$bilag]['document'] || trim((string)$row['dokument']) !== '';
			if (!$groups[$bilag]['document'] && $this->db->hasTable('documents')) {
				$groups[$bilag]['document'] = $this->db->value("SELECT 1 FROM documents WHERE source = 'kassekladde' AND source_id = ? LIMIT 1", [(int)$row['id']]) !== null;
			}
			$date = (string)$row['transdate'];
			$validDate = preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $date, $parts) && checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]);
			if (!$validDate || $year['box5'] !== 'on' || $date < $bounds['start'] || $date > $bounds['end']) {
				$this->finding('date_outside_year', 'error', [$row], 6145, 'Dato uden for regnskabsåret', $validDate ? ['date' => $date] : []);
			}
			if ($validDate && $this->db->hasTable('moms_periode_luk') && $this->db->value("SELECT 1 FROM moms_periode_luk WHERE kalender_aar = ? AND kalender_maaned = ? AND status = 'closed' LIMIT 1", [(int)$parts[1], (int)$parts[2]]) !== null) {
				$this->finding('vat_period_closed', 'error', [$row], 6146, 'Momsperioden er lukket', ['date' => $date]);
			}
			$debit = (int)$row['debet'] !== 0;
			$credit = (int)$row['kredit'] !== 0;
			$amount = SaldiSettlementKind::milli((string)($row['amount'] ?? '0'));
			if ($amount !== 0 && !$debit && !$credit) {
				$this->finding('no_account', 'error', [$row], 6202, 'Linjen har et beløb, men ingen konto.');
			}
			if ($debit !== $credit) {
				$this->finding('one_sided', 'warning', [$row], 6154, 'Linjen mangler konto på den ene side');
			}
			foreach ([['d_type', 'debet', 'debetvat'], ['k_type', 'kredit', 'kreditvat']] as [$type, $number, $vat]) {
				if ((int)$row[$number] !== 0) {
					$this->account($row, $type, $number, $vat);
					$groups[$bilag]['hidden'] = $groups[$bilag]['hidden'] || ($this->hidden && $row[$type] === 'F' && ifset($this->accounts, [(string)(int)$row[$number], 'kontotype']) === 'D');
				}
			}
			$baseAmount = $this->baseAmount($row, $validDate);
			$movement = ($debit ? $baseAmount : 0) - ($credit ? $baseAmount : 0);
			$total += $movement;
			$foreignRows += !empty($row['valuta']) && $amount !== 0 && ($debit || $credit) ? 1 : 0;
			$rounded = SaldiSettlementKind::milli(SaldiToolRuntime::money(SaldiSettlementKind::decimal($baseAmount)));
			$groups[$bilag]['total'] += ($debit ? $rounded : 0) - ($credit ? $rounded : 0);
			if ($validDate) {
				$this->duplicates($row);
			}
		}
		foreach ($groups as $group) {
			if (abs($group['total']) >= 10) {
				$difference = SaldiToolRuntime::money(SaldiSettlementKind::decimal($group['total']));
				$detail = $group['hidden'] ? [] : ['difference' => $difference, 'currency' => $this->ctx->baseCurrency];
				$this->finding('unbalanced', 'warning', $group['rows'], 6144, 'Bilaget stemmer ikke: forskel %s', $detail, [$group['hidden'] ? '—' : str_replace('.', ',', $difference)]);
			}
			if (!$group['document']) {
				$this->finding('missing_document', 'warning', $group['rows'], 6153, 'Bilag mangler');
			}
		}
		// bogfor.php:510-529 absorbs currency rounding strictly below one cent per foreign row.
		if (abs($total) >= 10 && abs($total) >= $foreignRows * 10) {
			$hiddenTotal = (bool)array_filter($groups, static fn(array $group): bool => $group['hidden']);
			$difference = SaldiToolRuntime::money(SaldiSettlementKind::decimal($total));
			$this->finding('journal_unbalanced', 'error', $rows, 6305, 'Kladden stemmer ikke: forskel %s', $hiddenTotal ? [] : ['difference' => $difference, 'currency' => $this->ctx->baseCurrency], [$hiddenTotal ? '—' : str_replace('.', ',', $difference)]);
		}
		return ['journal_id' => $id, 'status' => $journal['bogfort'] === '-' ? 'open' : 'closed', 'ready' => !$this->errors, 'errors' => $this->errors, 'warnings' => $this->warnings];
	}
	/** Read-only valutaopslag() parity: historic rate wins; no rate leaves the amount unchanged. */
	private function baseAmount(array $row, bool $validDate): int
	{
		$amount = (string)($row['amount'] ?? '0');
		if (empty($row['valuta'])) {
			return SaldiSettlementKind::milli($amount);
		}
		$currency = $this->db->value("SELECT box1 FROM grupper WHERE art = 'VK' AND kodenr = ? ORDER BY id LIMIT 1", [(int)$row['valuta']]);
		$historic = $validDate ? $this->db->value('SELECT kurs FROM valuta WHERE gruppe = ? AND valdate <= ? ORDER BY valdate DESC, id DESC LIMIT 1', [(int)$row['valuta'], $row['transdate']]) : null;
		if ($historic === null || !preg_match('/[1-9]/', (string)$historic)) {
			$this->finding('rate_unavailable', 'warning', [$row], 6201, 'Der mangler en valutakurs på datoen.', ['currency' => (string)($currency ?? '')] + ($validDate ? ['date' => $row['transdate']] : []));
		}
		$rate = $historic;
		if ($rate === null || !preg_match('/[1-9]/', (string)$rate)) {
			return SaldiSettlementKind::milli($amount);
		}
		$base = $this->db->value('SELECT ROUND(CAST(? AS DECIMAL(30,6)) * CAST(? AS DECIMAL(30,6)) / 100, 2)', [$amount, (string)$rate]);
		return SaldiSettlementKind::milli((string)$base);
	}
	/** Finance/party checks and VAT warnings for one populated side. */
	private function account(array $row, string $typeField, string $numberField, string $vatField): void
	{
		$type = trim((string)$row[$typeField]);
		$number = (string)(int)$row[$numberField];
		$account = $type === 'F' ? ifset($this->accounts, $number) : ifset($this->parties, $type . ':' . $number);
		if (!$account || ($type === 'F' ? (!empty($account['lukket']) || in_array($account['kontotype'], ['H', 'Z'], true)) : $account['lukket'] === 'on')) {
			$this->finding('account_unavailable', 'error', [$row], 6147, 'Kontoen findes ikke eller er lukket', ['account' => $number] + (in_array($type, ['F', 'D', 'K'], true) ? ['ledger' => SaldiToolRuntime::ledgerWord($type)] : []));
			return;
		}
		if ($type !== 'F') {
			return;
		}
		$hidden = $this->hidden && $account['kontotype'] === 'D';
		$expected = trim((string)$account['moms']);
		$vat = trim((string)($row[$vatField] ?? ''));
		$opposite = $vatField === 'debetvat' ? 'kreditvat' : 'debetvat';
		if ($vat === '' && trim((string)($row[$opposite] ?? '')) === '') {
			$vat = $expected;
		}
		$vatFree = $row['momsfri'] === 'on';
		if (!$vatFree && $vat !== '' && (!preg_match('/^([A-Z])([0-9]+)$/D', $vat, $code) || $this->db->value('SELECT 1 FROM grupper WHERE kode = ? AND art = ? AND kodenr = ? AND fiscal_year = ? LIMIT 1', [$code[1], $code[1] . 'M', (int)$code[2], $this->ctx->fiscalYear]) === null)) {
			$this->finding('vat_unknown', 'warning', [$row], 6148, 'Momskoden findes ikke; der beregnes ingen moms', ['vat_code' => $vat]);
		}
		$actual = $vatFree ? '' : $vat;
		if ($actual !== $expected) {
			$this->finding('vat_account_mismatch', 'warning', [$row], 6149, 'Momskoden afviger fra kontoens (%s)', ['vat_code' => $actual, 'expected_vat_code' => $expected], [$expected]);
		}
		foreach ($this->rules as $rule) {
			$match = $rule['match_type'] === 'text'
				? str_contains(' ' . SaldiPostingSuggester::normalise((string)$row['beskrivelse']) . ' ', ' ' . $rule['match_value'] . ' ')
				: (($row['d_type'] === ($rule['match_type'] === 'supplier' ? 'K' : 'D') && (string)(int)$row['debet'] === $rule['match_value'])
					|| ($row['k_type'] === ($rule['match_type'] === 'supplier' ? 'K' : 'D') && (string)(int)$row['kredit'] === $rule['match_value']));
			if ($match) {
				$ruleVat = $rule['vat_code'] === '' ? trim((string)ifset($this->accounts, [$rule['account'], 'moms'], '')) : ($rule['vat_code'] === 'none' ? '' : $rule['vat_code']);
				if ($actual !== $ruleVat) {
					$this->finding('vat_rule_mismatch', 'warning', [$row], 6150, 'Momskoden afviger fra bogføringsreglen (%s)', ['vat_code' => $actual, 'expected_vat_code' => $ruleVat], [$ruleVat]);
				}
				break;
			}
		}
		if (!$hidden) {
			$this->outlier($row, $number);
		}
	}
	/** Compare exact thousandths with twice the median to avoid rounding even-sized samples. */
	private function outlier(array $row, string $account): void
	{
		if (!array_key_exists($account, $this->medians)) {
			$history = $this->db->all("SELECT k.amount FROM kassekladde k JOIN kladdeliste l ON l.id = k.kladde_id WHERE l.bogfort <> '-' AND k.id <> ? AND k.transdate <= ? AND ((k.d_type = 'F' AND k.debet = ?) OR (k.k_type = 'F' AND k.kredit = ?)) ORDER BY k.transdate DESC, k.id DESC LIMIT 50", [(int)$row['id'], $row['transdate'], $account, $account]);
			$values = array_map(static fn(array $item): int => abs(SaldiSettlementKind::milli((string)($item['amount'] ?? '0'))), $history);
			sort($values, SORT_NUMERIC);
			$count = count($values);
			$this->medians[$account] = $count ? $values[intdiv($count - 1, 2)] + $values[intdiv($count, 2)] : null;
		}
		$median = $this->medians[$account];
		$amount = abs(SaldiSettlementKind::milli((string)($row['amount'] ?? '0')));
		if ($median !== null && $amount > 1000000 && $amount * 2 > $median * 5) {
			$display = SaldiToolRuntime::money(SaldiSettlementKind::decimal(intdiv($median + 1, 2)));
			$this->finding('amount_outlier', 'warning', [$row], 6151, 'Usædvanligt beløb for kontoen (normalt %s)', ['account' => $account, 'median' => $display, 'currency' => $this->ctx->baseCurrency], [str_replace('.', ',', $display)]);
		}
	}
	/** Port the suggester's party, 60-day window, amount/invoice and visibility predicates. */
	private function duplicates(array $row): void
	{
		foreach ([['d_type', 'debet'], ['k_type', 'kredit']] as [$type, $number]) {
			if (!in_array($row[$type], ['D', 'K'], true) || !(int)$row[$number]) {
				continue;
			}
			$date = new DateTimeImmutable($row['transdate']);
			$params = [$date->modify('-60 days')->format('Y-m-d'), $date->modify('+60 days')->format('Y-m-d'), (int)$row['id'], $row[$type], $row[$number], SaldiToolRuntime::money($row['amount'])];
			$invoice = trim((string)$row['faktura']);
			$check = 'ROUND(k.amount, 2) = CAST(? AS DECIMAL(15,2))';
			if ($invoice !== '') {
				$check .= ' OR LOWER(k.faktura) = ?';
				$params[] = mb_strtolower($invoice);
			}
			$hidden = '';
			if ($this->hidden) {
				$hidden = " AND NOT EXISTS (SELECT 1 FROM kontoplan a WHERE a.regnskabsaar = ? AND a.kontotype = 'D' AND ((k.d_type = 'F' AND a.kontonr = k.debet) OR (k.k_type = 'F' AND a.kontonr = k.kredit)))";
				$params[] = $this->ctx->fiscalYear;
			}
			$duplicates = $this->db->all('SELECT k.id, k.bilag, k.kladde_id FROM kassekladde k JOIN kladdeliste l ON l.id = k.kladde_id WHERE k.transdate BETWEEN ? AND ? AND k.id <> ? AND k.' . $type . ' = ? AND k.' . $number . ' = ? AND (' . $check . ')' . $hidden . ' ORDER BY k.transdate DESC, k.id DESC LIMIT 5', $params);
			foreach ($duplicates as $duplicate) {
				$pair = [min((int)$row['id'], (int)$duplicate['id']), max((int)$row['id'], (int)$duplicate['id'])];
				$key = implode(':', $pair);
				if (isset($this->duplicatePairs[$key])) {
					continue;
				}
				$this->duplicatePairs[$key] = true;
				$this->finding('possible_duplicate', 'warning', [$row], 6152, 'Mulig dublet af bilag %s i kladde %s', ['journal_id' => (int)$duplicate['kladde_id'], 'line_id' => (int)$duplicate['id'], 'invoice' => $invoice], [(string)$duplicate['bilag'], (string)$duplicate['kladde_id']]);
			}
		}
	}
}
