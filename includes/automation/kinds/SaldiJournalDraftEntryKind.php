<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiJournalDraftEntryKind.php --- ai/mcp-assistant --- 2026.10.05
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
// Reversible unposted journal lines with live period checks and exact-row undo.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Translate singular and plural draft titles without claiming journal locks.
// 20261002 CL/LH Validation errors name the line, side and the expected ledger.
// 20261002 CL/LH One-sided lines must balance within a draft; betal_id stored as '' like the kassekladde page.
// 20261002 CDX/LH Validate invoice, VAT and payment fields; attach documents and detach before undo.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261003 CL/LH Card warns when the same party invoice is already in a kassekladde.
// 20261004 CDX/LH Preserve bank-line dates and allocate one voucher per bank movement; release staged lines on undo.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiInboxKind.php';
require_once __DIR__ . '/../SaldiDocumentAttach.php';
require_once __DIR__ . '/../SaldiBankImport.php';
final class SaldiJournalDraftEntryKind extends SaldiBaseInboxKind
{
	public function name(): string
	{
		return 'journal.draft_entry';
	}
	public function actionClass(): string
	{
		return 'reversible_draft';
	}
	public function proposePermission(): string
	{
		return 'journal.draft';
	}
	public function decidePermission(array $item): string
	{
		return 'journal.draft';
	}
	/** @return array{0:string,1:string} */
	private function bounds(SaldiDb $db, int $year): array
	{
		$r = $db->one("SELECT box1, box2, box3, box4 FROM grupper WHERE art = 'RA' AND kodenr = ? AND box5 = 'on' AND COALESCE(box10, '') <> 'on'", [$year]);
		if (!$r || !checkdate((int)$r['box1'], 1, (int)$r['box2']) || !checkdate((int)$r['box3'], 1, (int)$r['box4'])) {
			throw new SaldiRuntimeError('CLOSED_PERIOD', 'Fiscal year closed');
		}
		return [sprintf('%04d-%02d-01', $r['box2'], $r['box1']), (new DateTimeImmutable(sprintf('%04d-%02d-01', $r['box4'], $r['box3'])))->format('Y-m-t')];
	}
	private function account(SaldiDb $db, int $year, mixed $side, string $where = 'line'): array
	{
		if (!is_array($side) || !in_array($side['type'] ?? '', ['F', 'D', 'K'], true) || !is_string($side['account'] ?? null) || !preg_match('/^[0-9]{1,15}$/D', $side['account'])) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', $where . ': give a ledger (account, customer or supplier) and a numeric account', ['fields' => ['$.' . $where]]);
		}
		$type = $side['type'];
		$number = $side['account'];
		if ($type === 'F') {
			$row = $db->one("SELECT kontonr FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ? AND kontotype IN ('D','S') AND COALESCE(lukket, '') <> 'on'", [$number, $year]);
		} else {
			$row = $db->one("SELECT kontonr FROM adresser WHERE kontonr = ? AND art = ? AND COALESCE(lukket, '') <> 'on'", [$number, $type]);
		}
		if (!$row || !preg_match('/^[0-9]{1,15}$/D', (string)$row['kontonr'])) {
			$expected = ['F' => 'an open finance account (pl or balance) in fiscal year ' . $year . '; check search_accounts', 'D' => 'an open customer account; check search_parties type debtor', 'K' => 'an open supplier account; check search_parties type creditor'][$type];
			throw new SaldiRuntimeError('VALIDATION_ERROR', $where . ': account ' . $number . ' is not ' . $expected, ['fields' => ['$.' . $where]]);
		}
		$result = ['type' => $type, 'account' => $number];
		if (array_key_exists('vat_code', $side)) {
			$column = str_ends_with($where, '.debit') ? 'debetvat' : 'kreditvat';
			if (!$db->hasColumn('kassekladde', 'debetvat') || !$db->hasColumn('kassekladde', $column)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'VAT overrides unsupported', ['reason' => 'vat_override_unsupported']);
			}
			$code = $side['vat_code'];
			if (!is_string($code) || strlen($code) > 8 || ($code !== '' && (!preg_match('/^([A-Z])([0-9]+)$/D', $code, $parts)
				|| $db->value('SELECT id FROM grupper WHERE art = ? AND kodenr = ? AND fiscal_year = ? LIMIT 1', [$parts[1] . 'M', (int)$parts[2], $year]) === null))) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid VAT code', ['fields' => [$where . '.vat_code']]);
			}
			$result['vat_code'] = $code;
		}
		return $result;
	}
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		if ($ctx->baseCurrency !== 'DKK') {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Only DKK drafts are supported', ['reason' => 'currency_unsupported']);
		}
		$year = isset($input['fiscal_year']) ? (int)$input['fiscal_year'] : $ctx->fiscalYear;
		[$start, $end] = $this->bounds($db, $year);
		$bankDraft = array_key_exists('bank_batch_id', $input);
		if ($bankDraft && (!is_string($input['bank_batch_id']) || !preg_match('/^[a-f0-9]{32}$/D', $input['bank_batch_id']) || $year !== $ctx->fiscalYear)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid bank batch or fiscal year');
		}
		if ($bankDraft) {
			SaldiBankImport::bankAccount($ctx, $db, $input['bank_account'] ?? null);
		}
		$date = $this->date($input['date'] ?? null, 'date');
		if ($date < $start || $date > $end) {
			throw new SaldiRuntimeError('CLOSED_PERIOD', 'Date outside active fiscal year');
		}
		if ($db->hasTable('moms_periode_luk') && $db->value("SELECT 1 FROM moms_periode_luk WHERE status = 'closed' AND kalender_aar = ? AND kalender_maaned = ? LIMIT 1", [(int)substr($date, 0, 4), (int)substr($date, 5, 2)]) !== null) {
			throw new SaldiRuntimeError('CLOSED_PERIOD', 'VAT period closed');
		}
		$id = $input['kladde_id'] ?? null;
		if ($id !== null && (!is_int($id) || $id <= 0)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid journal id');
		}
		if ($id !== null && !$db->one("SELECT id FROM kladdeliste WHERE id = ? AND COALESCE(bogfort, '-') = '-'", [$id])) {
			throw new SaldiRuntimeError('CONFLICT', 'Journal not open', ['reason' => 'not_open']);
		}
		$lines = $input['lines'] ?? null;
		if (!is_array($lines) || !array_is_list($lines) || count($lines) < 1 || count($lines) > ($bankDraft ? 200 : 20)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected 1 to ' . ($bankDraft ? 200 : 20) . ' journal lines');
		}
		$p = ['kladde_id' => $id, 'note' => $this->string($input['note'] ?? '', 0, 60, 'note'), 'date' => $date, 'fiscal_year' => $year, 'currency' => 'DKK', 'lines' => []];
		if ($bankDraft) {
			$p['bank_batch_id'] = $input['bank_batch_id'];
			$p['bank_account'] = $input['bank_account'];
			if (isset($input['bank_request']) && is_array($input['bank_request'])) {
				$p['bank_request'] = $input['bank_request'];
			}
		}
		if (array_key_exists('document_ref', $input)) {
			SaldiPermissionService::require($ctx, $db, 'documents.read');
			$ref = $this->string($input['document_ref'], 6, 20, 'document_ref');
			(new SaldiDocumentAttach($ctx, $db))->pool($ref);
			$p['document_ref'] = $ref;
		}
		foreach (array_values($lines) as $index => $line) {
			if (!is_array($line) || !is_string($line['amount'] ?? null) || !preg_match('/^[0-9]{1,12}(\.[0-9]{1,2})?$/D', $line['amount']) || !preg_match('/[1-9]/', $line['amount'])) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Amount must be a positive decimal string');
			}
			$n = ['description' => $this->string($line['description'] ?? null, 1, 100, 'description'), 'amount' => $this->amount($line['amount'])];
			foreach (['debit', 'credit'] as $side) {
				if (isset($line[$side])) {
					$n[$side] = $this->account($db, $year, $line[$side], 'lines[' . $index . '].' . $side);
				}
			}
			if (!isset($n['debit']) && !isset($n['credit'])) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Line needs a debit or credit');
			}
			$party = in_array($n['debit']['type'] ?? '', ['D', 'K'], true) || in_array($n['credit']['type'] ?? '', ['D', 'K'], true);
			if (array_key_exists('invoice', $line)) {
				$invoice = $this->string($line['invoice'], 1, isset($input['payment_registration']) ? 200 : 30, 'invoice');
				if (!$party || !preg_match('/^[A-Za-z0-9 ._;\/-]+$/D', $invoice)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invoice needs a party and a valid number', ['fields' => ['lines[' . $index . '].invoice']]);
				}
				$n['invoice'] = $invoice;
			}
			if (array_key_exists('due_date', $line)) {
				if (!$party) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Due date needs a party');
				}
				$n['due_date'] = $this->date($line['due_date'], 'due_date');
			}
			if (array_key_exists('vat_free', $line)) {
				if (!is_bool($line['vat_free']) || ($line['vat_free'] && (array_key_exists('vat_code', $n['debit'] ?? []) || array_key_exists('vat_code', $n['credit'] ?? [])))) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'VAT-free and VAT overrides are mutually exclusive');
				}
				$n['vat_free'] = $line['vat_free'];
			}
			if ($bankDraft) {
				$n = $this->bankLine($db, $p, $line, $n, $start, $end);
				if (in_array($n['bank_line_id'], array_column($p['lines'], 'bank_line_id'), true)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Bank line repeated in draft');
				}
			}
			$p['lines'][] = $n;
		}
		if (isset($input['payment_registration'])) {
			require_once __DIR__ . '/../../tools/SaldiDocumentTools.php';
			$payment = $input['payment_registration'];
			if (!is_array($payment) || !is_array($payment['party'] ?? null) || !is_array($payment['invoice_numbers'] ?? null) || count($p['lines']) !== 1) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid payment registration');
			}
			$amount = SaldiDocumentTools::paymentOpenAmount($db, $ctx, $payment['party'], $payment['invoice_numbers']);
			$supplier = $payment['party']['ledger'] === 'supplier';
			$partySide = $p['lines'][0][$supplier ? 'debit' : 'credit'] ?? [];
			$bankSide = $p['lines'][0][$supplier ? 'credit' : 'debit'] ?? [];
			if (($partySide['type'] ?? '') !== ($supplier ? 'K' : 'D') || ($partySide['account'] ?? '') !== $payment['party']['account']
				|| ($bankSide['type'] ?? '') !== 'F' || ($p['lines'][0]['invoice'] ?? '') !== implode(';', $payment['invoice_numbers'])) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Payment registration does not match journal line');
			}
			$p['payment_registration'] = ['party' => $payment['party'], 'invoice_numbers' => $payment['invoice_numbers'], 'open_amount' => $amount];
		}
		// apply() books the whole draft on one new bilag, so one-sided lines must net to zero (finans/bogfor.php
		// refuses a kladde with differences). Integer øre from the normalised 'amount', no floats.
		$difference = 0;
		foreach ($p['lines'] as $n) {
			if (isset($n['debit']) !== isset($n['credit'])) {
				$difference += (isset($n['debit']) ? 1 : -1) * (int)str_replace('.', '', $n['amount']);
			}
		}
		if ($difference !== 0) {
			$abs = abs($difference);
			$text = ($difference < 0 ? '-' : '') . intdiv($abs, 100) . '.' . str_pad((string)($abs % 100), 2, '0', STR_PAD_LEFT);
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Journal draft does not balance: one-sided debits minus one-sided credits = ' . $text, ['reason' => 'unbalanced', 'difference' => $text, 'fields' => ['$.lines']]);
		}
		$n = $this->normalized($ctx, $p, count($lines) . ' ' . $this->text($ctx, $db, count($lines) === 1 ? 5750 : 5751) . ': ' . $p['lines'][0]['description']);
		$n['subject_type'] = $id === null ? null : 'journal';
		$n['subject_id'] = $id === null ? null : (string)$id;
		$n['proposed_rev'] = $id === null ? null : $this->revision($db, $id);
		return $n;
	}

	/** Verify bank provenance and per-voucher balance without changing ordinary draft semantics.
	 * @return array Normalised two-sided bank line with its own date.
	 */
	private function bankLine(SaldiDb $db, array $payload, array $input, array $line, string $start, string $end): array
	{
		$id = $input['bank_line_id'] ?? null;
		if (!is_int($id) || $id <= 0 || !isset($line['debit'], $line['credit'])) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Bank movements require a staged id and two sides');
		}
		$date = $this->date($input['date'] ?? null, 'lines.date');
		if ($date < $start || $date > $end) {
			throw new SaldiRuntimeError('CLOSED_PERIOD', 'Bank date outside active fiscal year');
		}
		if ($db->hasTable('moms_periode_luk') && $db->value("SELECT 1 FROM moms_periode_luk WHERE status = 'closed' AND kalender_aar = ? AND kalender_maaned = ? LIMIT 1", [(int)substr($date, 0, 4), (int)substr($date, 5, 2)]) !== null) {
			throw new SaldiRuntimeError('CLOSED_PERIOD', 'Bank VAT period closed');
		}
		$row = $db->one('SELECT * FROM bank_import_lines WHERE id = ? AND batch_id = ?', [$id, $payload['bank_batch_id']]);
		if (!$row || !in_array($row['status'], ['new', 'proposed'], true) || $row['bank_account'] !== $payload['bank_account']
			|| $row['currency'] !== 'DKK' || $row['transdate'] !== $date || SaldiToolRuntime::money(ltrim($row['amount'], '-')) !== $line['amount']) {
			throw new SaldiRuntimeError('CONFLICT', 'Bank movement no longer available', ['reason' => 'not_open']);
		}
		$incoming = SaldiBankStatementParser::milli($row['amount']) > 0;
		$bank = $line[$incoming ? 'debit' : 'credit'];
		$counter = $line[$incoming ? 'credit' : 'debit'];
		if (SaldiBankStatementParser::milli($row['amount']) === 0 || $bank['type'] !== 'F' || $bank['account'] !== $payload['bank_account']
			|| !in_array($counter['type'], ['F', $incoming ? 'D' : 'K'], true)
			|| ($counter['type'] === 'F' && $counter['account'] === $payload['bank_account'])) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Bank movement sides do not match its sign');
		}
		return $line + ['date' => $date, 'bank_line_id' => $id];
	}
	/** Positive input, no floating-point arithmetic. */
	private function amount(string $value): string
	{
		$parts = explode('.', $value);
		return (ltrim($parts[0], '0') ?: '0') . '.' . str_pad($parts[1] ?? '', 2, '0');
	}
	private function rows(SaldiDb $db, int $id, ?array $lineIds = null, bool $lock = false): array
	{
		$sql = 'SELECT * FROM kassekladde WHERE kladde_id = ?';
		$params = [$id];
		if ($lineIds !== null) {
			if (!$lineIds) {
				return [];
			}
			$sql .= ' AND id IN (' . implode(',', array_fill(0, count($lineIds), '?')) . ')';
			$params = array_merge($params, $lineIds);
		}
		$rows = $db->all($sql . ' ORDER BY id' . ($lock ? ' FOR UPDATE' : ''), $params);
		foreach ($rows as &$row) {
			unset($row['pos']);
		} unset($row);
		return $rows;
	}
	private function revision(SaldiDb $db, int $id): string
	{
		$header = $db->one('SELECT id, bogfort FROM kladdeliste WHERE id = ?' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$id]);
		return hash('sha256', saldi_runtime_json(['kladdeliste' => $header, 'kassekladde' => $this->rows($db, $id, null, $db->inTransaction())]));
	}
	public function currentRevision(SaldiTenantContext $ctx, SaldiDb $db, array $item): ?string
	{
		return empty($item['payload']['kladde_id']) ? null : $this->revision($db, $item['payload']['kladde_id']);
	}
	private function available(SaldiTenantContext $ctx, SaldiDb $db, int $id): void
	{
		$row = $db->one('SELECT * FROM kladdeliste WHERE id = ? FOR UPDATE', [$id]);
		if (!$row || ($row['bogfort'] ?? '-') !== '-') {
			throw new SaldiRuntimeError('CONFLICT', 'Journal not open', ['reason' => 'not_open']);
		}
		$time = explode(' ', (string)($row['tidspkt'] ?? ''));
		if (!empty($row['hvem']) && $row['hvem'] !== $ctx->username && (float)($time[1] ?? 0) > time() - 3600) {
			throw new SaldiRuntimeError('CONFLICT', 'Journal in use by another user', ['reason' => 'journal_in_use']);
		}
		if ($db->hasTable('tmpkassekl') && $db->value('SELECT 1 FROM tmpkassekl WHERE kladde_id = ? LIMIT 1', [$id]) !== null) {
			throw new SaldiRuntimeError('CONFLICT', 'Journal has unsaved changes', ['reason' => 'journal_unsaved']);
		}
	}
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$p = $item['payload'];
		$id = $p['kladde_id'];
		$bankDraft = isset($p['bank_batch_id']);
		if ($bankDraft) {
			$db->lock('saldi_bank_apply_' . $p['bank_account']);
			$claimed = [];
			// Earlier approvals from this batch have already consumed their own movements.
			foreach ($db->all("SELECT i.result FROM inbox_items i WHERE i.kind = 'journal.draft_entry' AND i.status = 'applied' AND EXISTS (SELECT 1 FROM bank_import_lines b WHERE b.batch_id = ? AND b.inbox_item_id = i.id AND b.status = 'proposed')", [$p['bank_batch_id']]) as $prior) {
				$receipt = json_decode($prior['result'], true, 512, JSON_THROW_ON_ERROR);
				foreach ($receipt['line_ids'] as $lineId) {
					$claimed[] = (int)$lineId;
				}
			}
			foreach ($db->all("SELECT proposal FROM bank_import_lines WHERE batch_id = ? AND status = 'duplicate' AND proposal IS NOT NULL", [$p['bank_batch_id']]) as $duplicate) {
				$claim = json_decode($duplicate['proposal'], true, 512, JSON_THROW_ON_ERROR);
				if (isset($claim['line_id'])) {
					$claimed[] = (int)$claim['line_id'];
				}
			}
			foreach ($p['lines'] as $line) {
				$staged = $db->one('SELECT status, inbox_item_id FROM bank_import_lines WHERE id = ? AND batch_id = ? FOR UPDATE', [$line['bank_line_id'], $p['bank_batch_id']]);
				if (!$staged || $staged['status'] !== 'proposed' || (int)$staged['inbox_item_id'] !== (int)$item['id']) {
					throw new SaldiRuntimeError('CONFLICT', 'Bank line reservation changed', ['reason' => 'not_open']);
				}
				$signed = $line['debit']['account'] === $p['bank_account'] && $line['debit']['type'] === 'F' ? $line['amount'] : '-' . $line['amount'];
				if (SaldiBankImport::journalDuplicate($db, $p['bank_account'], ['date' => $line['date'], 'amount' => $signed], $claimed) !== null) {
					throw new SaldiRuntimeError('CONFLICT', 'Bank movement already exists in a journal', ['reason' => 'not_open']);
				}
			}
		}
		$created = $id === null;
		if ($created) {
			$db->lock('saldi_kladdeliste_id');
			if ($db->isPostgres()) {
				$db->exec('LOCK TABLE kladdeliste IN SHARE ROW EXCLUSIVE MODE');
			}
			$id = (int)$db->value('SELECT COALESCE(MAX(id), 0) + 1 FROM kladdeliste');
			$db->insert('kladdeliste', ['id' => $id, 'kladdenote' => $p['note'] !== '' ? $p['note'] : $this->text($ctx, $db, 5700), 'kladdedate' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d'), 'bogfort' => '-', 'oprettet_af' => $ctx->username]);
		} else {
			$this->available($ctx, $db, $id);
		}
		$db->lock('saldi_bilag_' . $id);
		$bilag = (int)$db->value('SELECT COALESCE(MAX(bilag), 0) FROM kassekladde WHERE kladde_id = ? AND bilag > 0', [$id]);
		if ($bilag === 0) {
			[$start, $end] = $this->bounds($db, $p['fiscal_year']);
			$bilag = (int)$db->value('SELECT COALESCE(MAX(bilag), 0) FROM kassekladde WHERE bilag > 0 AND transdate >= ? AND transdate <= ?', [$start, $end]);
		}
		$bilag++;
		$pos = (int)$db->value('SELECT COALESCE(MAX(pos), 0) FROM kassekladde WHERE kladde_id = ?', [$id]);
		$ids = [];
		foreach ($p['lines'] as $index => $line) {
			$lineDate = $bankDraft ? $line['date'] : $p['date'];
			$lineBilag = $bilag + ($bankDraft ? $index : 0);
			$d = $line['debit'] ?? ['type' => '', 'account' => 0];
			$k = $line['credit'] ?? ['type' => '', 'account' => 0];
			$row = ['bilag' => $lineBilag, 'kladde_id' => $id, 'transdate' => $lineDate, 'beskrivelse' => $line['description'], 'd_type' => $d['type'], 'debet' => $d['account'], 'k_type' => $k['type'], 'kredit' => $k['account'], 'amount' => $line['amount'], 'faktura' => $line['invoice'] ?? '', 'momsfri' => !empty($line['vat_free']) ? 'on' : '', 'afd' => 0, 'ansat' => 0, 'projekt' => '', 'valuta' => 0, 'valutakurs' => '100', 'betal_id' => '', 'pos' => ++$pos, 'forfaldsdate' => in_array($d['type'], ['D','K'], true) || in_array($k['type'], ['D','K'], true) ? ($line['due_date'] ?? $lineDate) : null];
			foreach (['debetvat', 'kreditvat'] as $vat) {
				if ($db->hasColumn('kassekladde', $vat)) {
					$row[$vat] = ($vat === 'debetvat' ? $d : $k)['vat_code'] ?? '';
				}
			}
			$ids[] = $db->insert('kassekladde', $row);
		}
		$result = ['kladde_id' => $id, 'line_ids' => $ids, 'bilag' => $bilag, 'created_kladde' => $created, 'rows_sha256' => hash('sha256', saldi_runtime_json($this->rows($db, $id, $ids))), 'screen' => SaldiScreens::link('journal', $id)];
		if ($bankDraft) {
			$result['bilags'] = range($bilag, $bilag + count($p['lines']) - 1);
		}
		if (isset($p['document_ref'])) {
			$result['document'] = (new SaldiDocumentAttach($ctx, $db))->attach($p['document_ref'], $id, $ids[0]);
		}
		return $result;
	}
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool
	{
		if ($item['status'] !== 'applied' || empty($item['result']['line_ids'])) {
			return false;
		}
		$r = $item['result'];
		$header = $db->one('SELECT bogfort FROM kladdeliste WHERE id = ?' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$r['kladde_id']]);
		if (!$header || ($header['bogfort'] ?? '-') !== '-') {
			return false;
		}
		if (isset($r['document']) && !(new SaldiDocumentAttach($ctx, $db))->canDetach($r['document'])) {
			return false;
		}
		if ($db->hasTable('documents')) {
			$documents = $db->all("SELECT id FROM documents WHERE source = 'kassekladde' AND source_id IN (" . implode(',', array_fill(0, count($r['line_ids']), '?')) . ')' . ($db->inTransaction() ? ' FOR UPDATE' : ''), $r['line_ids']);
			$expected = isset($r['document']) ? [(int)$r['document']['document_id']] : [];
			if (array_map(static fn(array $document): int => (int)$document['id'], $documents) !== $expected) {
				return false;
			}
		}
		$rows = $this->rows($db, (int)$r['kladde_id'], $r['line_ids'], $db->inTransaction());
		return count($rows) === count($r['line_ids']) && hash_equals($r['rows_sha256'], hash('sha256', saldi_runtime_json($rows)));
	}
	public function undo(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		if (!$this->canUndo($ctx, $db, $item)) {
			throw new SaldiRuntimeError('CONFLICT', 'Inserted rows changed', ['reason' => 'undo_changed']);
		}
		$r = $item['result'];
		$this->available($ctx, $db, (int)$r['kladde_id']);
		if (isset($r['document'])) {
			(new SaldiDocumentAttach($ctx, $db))->detach($r['document']);
		}
		$n = $db->exec('DELETE FROM kassekladde WHERE kladde_id = ? AND id IN (' . implode(',', array_fill(0, count($r['line_ids']), '?')) . ')', array_merge([$r['kladde_id']], $r['line_ids']));
		$removed = false;
		if ($r['created_kladde'] && (int)$db->value('SELECT COUNT(*) FROM kassekladde WHERE kladde_id = ?', [$r['kladde_id']]) === 0) {
			$removed = $db->exec('DELETE FROM kladdeliste WHERE id = ?', [$r['kladde_id']]) === 1;
		}
		if (isset($item['payload']['bank_batch_id'])) {
			SaldiBankImport::release($db, (int)$item['id']);
		}
		return ['removed_lines' => $n, 'removed_kladde' => $removed]
			+ (isset($r['document']) ? ['document_filename' => $r['document']['pool_row']['filename']] : []);
	}
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$p = $item['payload'];
		$name = function (?array $side) use ($db, $p): string {
			if ($side === null) {
				return '';
			}
			if ($side['type'] === 'F') {
				$label = $db->value('SELECT beskrivelse FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ?', [$side['account'], $p['fiscal_year']]);
			} else {
				$label = $db->value('SELECT firmanavn FROM adresser WHERE kontonr = ? AND art = ?', [$side['account'], $side['type']]);
			}
			return $side['account'] . ' ' . (string)$label;
		};
		$rows = [];
		foreach ($p['lines'] as $index => $line) {
			$description = $line['description'] . (isset($line['invoice']) ? ' (fakt. ' . $line['invoice'] . ')' : '');
			$vat = !empty($line['vat_free']) ? $this->text($ctx, $db, 5935, 'Momsfri') : '';
			foreach (['debit', 'credit'] as $side) {
				if (isset($line[$side]['vat_code']) && $line[$side]['vat_code'] !== '') {
					$default = $db->value('SELECT moms FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ?', [$line[$side]['account'], $p['fiscal_year']]);
					if ($line[$side]['vat_code'] !== $default) {
						$vat = $this->text($ctx, $db, 5922, 'Moms') . ': ' . $line[$side]['vat_code'];
					}
				}
			}
			$description .= $vat !== '' ? ' (' . $vat . ')' : '';
			$rows[] = [isset($p['bank_batch_id']) ? $line['date'] : $p['date'], isset($item['result']['bilag']) ? (string)($item['result']['bilag'] + (isset($p['bank_batch_id']) ? $index : 0)) : '', $description, $name($line['debit'] ?? null), $name($line['credit'] ?? null), $line['amount']];
		}
		$fields = [['label' => $this->text($ctx, $db, 5716), 'value' => $p['kladde_id'] === null ? $this->text($ctx, $db, 5717) : (string)$p['kladde_id']]];
		if (isset($p['document_ref'])) {
			$filename = $item['result']['document']['pool_row']['filename'] ?? $item['result']['document_filename']
				?? $db->value('SELECT filename FROM pool_files WHERE id = ?', [(int)substr($p['document_ref'], 5)]);
			$fields[] = ['label' => $this->text($ctx, $db, 5742, 'Bilag'), 'value' => (string)$filename];
		}
		$result = ['columns' => array_map(fn(int $id): string => $this->text($ctx, $db, $id), [5741,5742,5743,5744,5745,5746]), 'rows' => $rows, 'fields' => $fields];
		$notices = [];
		$expected = $p['payment_registration']['open_amount'] ?? null;
		if ($expected !== null && $expected !== $p['lines'][0]['amount']) {
			$notices[] = ['kind' => 'info', 'text' => sprintf($this->text($ctx, $db, 6030, 'Beløbet svarer ikke til de valgte fakturaer (%s).'), $expected)];
		}
		// The same supplier/customer invoice already in a kassekladde (open or posted) is a likely
		// duplicate. Checked live at render time; the item's own applied lines are excluded.
		if (!isset($p['payment_registration'])) {
			$own = array_map('intval', $item['result']['line_ids'] ?? []);
			foreach ($p['lines'] as $line) {
				$party = (($line['credit']['type'] ?? '') === 'K' || ($line['credit']['type'] ?? '') === 'D') ? $line['credit'] : ((($line['debit']['type'] ?? '') === 'K' || ($line['debit']['type'] ?? '') === 'D') ? $line['debit'] : null);
				if ($party === null || empty($line['invoice'])) {
					continue;
				}
				$sql = 'SELECT kladde_id FROM kassekladde WHERE LOWER(faktura) = LOWER(?) AND ((k_type = ? AND kredit = ?) OR (d_type = ? AND debet = ?))';
				$params = [$line['invoice'], $party['type'], $party['account'], $party['type'], $party['account']];
				if ($own) {
					$sql .= ' AND id NOT IN (' . implode(',', array_fill(0, count($own), '?')) . ')';
					$params = array_merge($params, $own);
				}
				$kladde = $db->value($sql . ' ORDER BY id LIMIT 1', $params);
				if ($kladde !== null) {
					$notices[] = ['kind' => 'warning', 'text' => sprintf($this->text($ctx, $db, 6032, 'Mulig dublet: faktura %s fra samme part findes allerede i kassekladde %s.'), $line['invoice'], (string)$kladde)];
					break;
				}
			}
		}
		if ($notices) {
			$result['notices'] = array_merge(!empty($item['evidence']['tainted']) ? [['kind' => 'tainted', 'text' => $this->text($ctx, $db, 5723)]] : [], $notices);
		}
		return $result;
	}
}
