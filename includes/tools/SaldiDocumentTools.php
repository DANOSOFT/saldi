<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiDocumentTools.php --- ai/mcp-assistant --- 2026.10.03
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
// Permission-filtered document metadata and approval-gated invoice/payment drafts.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Implement C3 document reads and journal proposals.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261003 CL/LH get_document retries the suggestion with the vendor name when the invoice text has no history.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/SaldiToolRuntime.php';
require_once __DIR__ . '/SaldiDraftTools.php';

final class SaldiDocumentTools
{
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function listDocuments(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'documents.read');
		$status = $args['status'] ?? 'pool';
		if (!in_array($status, ['pool', 'attached', 'all'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid document status');
		}
		$selects = [];
		if ($status !== 'attached') {
			$selects[] = self::poolSelect($db);
		}
		if ($status !== 'pool') {
			$selects[] = self::attachedSelect($ctx, $db);
		}
		$sql = 'SELECT * FROM (' . implode(' UNION ALL ', $selects) . ') visible_documents WHERE 1 = 1';
		$params = [];
		if (isset($args['query']) && $args['query'] !== '') {
			$sql .= " AND (LOWER(filename) LIKE ? ESCAPE '!' OR LOWER(vendor_name) LIKE ? ESCAPE '!' OR LOWER(invoice_number) LIKE ? ESCAPE '!')";
			$params = array_fill(0, 3, SaldiToolRuntime::like($args['query']));
		}
		foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
			if (isset($args[$key])) {
				if (self::date($args[$key]) !== $args[$key]) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid document date');
				}
				$sql .= ' AND file_date ' . $operator . ' ?';
				$params[] = $args[$key];
			}
		}
		$page = SaldiToolRuntime::page($db, $sql, 'created_at DESC, source, id DESC', $params, $args);
		$rows = array_map(static fn(array $row): array => self::row($ctx, $row), $page['rows']);
		return SaldiToolRuntime::result(['rows' => $rows, 'total' => $page['total']], array_map(self::citation(...), $rows), $page['more']);
	}
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function getDocument(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'documents.read');
		if (!preg_match('/^(pool|doc):([1-9][0-9]{0,14})$/D', (string)($args['ref'] ?? ''), $match)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid document reference');
		}
		$sql = $match[1] === 'pool' ? self::poolSelect($db) : self::attachedSelect($ctx, $db);
		$raw = $db->one('SELECT * FROM (' . $sql . ') visible_documents WHERE id = ?', [(int)$match[2]]);
		if (!$raw) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Document not found');
		}
		$row = self::row($ctx, $raw);
		$row['suggestion'] = null;
		if ($row['source'] === 'pool' && $row['vendor'] !== null && $row['vendor']['supplier_account'] !== null
			&& trim($row['description'] ?: $row['vendor']['name']) !== ''
			&& $db->value("SELECT id FROM adresser WHERE art = 'K' AND kontonr = ? AND COALESCE(lukket, '') <> 'on'", [$row['vendor']['supplier_account']]) !== null
			&& SaldiPermissionService::allows($ctx, $db, 'journal.read') && SaldiPermissionService::allows($ctx, $db, 'accounts.read')) {
			$path = __DIR__ . '/../automation/SaldiPostingSuggester.php';
			if (is_file($path)) {
				require_once $path;
			}
			if (class_exists('SaldiPostingSuggester')) {
				$line = ['description' => mb_substr($row['description'] ?: $row['vendor']['name'], 0, 100),
					'party' => ['ledger' => 'supplier', 'account' => $row['vendor']['supplier_account']]];
				foreach (['amount', 'date'] as $key) {
					if ($row[$key] !== null) {
						$line[$key] = $row[$key];
					}
				}
				$suggester = new SaldiPostingSuggester($ctx, $db);
				$row['suggestion'] = $suggester->suggest($line)['suggestions'][0] ?? null;
				// Invoice text rarely matches history ("Garn til efterårskollektionen"); journals often
				// name the vendor instead ("Hjertegarn"), so retry with the vendor name as the text.
				$vendor = (string)($row['vendor']['name'] ?? '');
				if ($row['suggestion'] === null && $vendor !== '' && $vendor !== $line['description']) {
					$row['suggestion'] = $suggester->suggest(['description' => mb_substr($vendor, 0, 100)] + $line)['suggestions'][0] ?? null;
				}
			}
		}
		return SaldiToolRuntime::result($row, [self::citation($row)]);
	}
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function proposeSupplierInvoice(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'journal.draft');
		$lines = $args['lines'] ?? null;
		if (!is_array($lines) || !array_is_list($lines) || count($lines) < 1 || count($lines) > 10) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected 1 to 10 invoice lines');
		}
		$draft = self::common($args);
		foreach ($lines as $line) {
			$debit = ['ledger' => 'account', 'account' => $line['account'] ?? ''];
			if (array_key_exists('vat_code', $line)) {
				$debit['vat_code'] = $line['vat_code'];
			}
			$entry = ['description' => $line['description'] ?? '', 'amount' => $line['amount'] ?? '',
				'debit' => $debit, 'credit' => ['ledger' => 'supplier', 'account' => $args['supplier_account'] ?? ''],
				'invoice' => $args['invoice_number'] ?? ''];
			if (isset($args['due_date'])) {
				$entry['due_date'] = $args['due_date'];
			}
			$draft['lines'][] = $entry;
		}
		return SaldiDraftTools::propose('journal.draft_entry', $ctx, $db, $draft);
	}
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function proposePaymentRegistration(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'journal.draft');
		$party = $args['party'] ?? [];
		$numbers = $args['invoice_numbers'] ?? [];
		self::paymentOpenAmount($db, $ctx, $party, $numbers);
		$draft = self::common($args);
		$bank = ['ledger' => 'account', 'account' => $args['bank_account'] ?? ''];
		$supplier = $party['ledger'] === 'supplier';
		$entry = ['description' => SaldiText::get($db, 6031, $ctx->languageId, 'Registrering af betaling'),
			'amount' => $args['amount'] ?? '', 'debit' => $supplier ? $party : $bank, 'credit' => $supplier ? $bank : $party];
		if ($numbers) {
			$entry['invoice'] = implode(';', $numbers);
		}
		$draft['lines'] = [$entry];
		$draft['payment_registration'] = ['party' => $party, 'invoice_numbers' => $numbers];
		return SaldiDraftTools::propose('journal.draft_entry', $ctx, $db, $draft);
	}

	/** Rechecked by the kind at approval; money is summed in SQL, never with floats. */
	public static function paymentOpenAmount(SaldiDb $db, SaldiTenantContext $ctx, array $party, array $numbers): ?string
	{
		$ledger = $party['ledger'] ?? '';
		$account = $party['account'] ?? '';
		if (!in_array($ledger, ['customer', 'supplier'], true) || !is_string($account) || !preg_match('/^[0-9]{1,15}$/D', $account)
			|| !array_is_list($numbers) || count($numbers) > 10 || count(array_unique($numbers)) !== count($numbers)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid payment party or invoice numbers');
		}
		foreach ($numbers as $number) {
			if (!is_string($number)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid invoice number');
			}
		}
		if (mb_strlen(implode(';', $numbers)) > 200) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invoice references exceed 200 characters joined', ['fields' => ['invoice_numbers']]);
		}
		$partyId = $db->value("SELECT id FROM adresser WHERE art = ? AND kontonr = ? AND COALESCE(lukket, '') <> 'on'", [SaldiToolRuntime::ledgerCode($ledger), $account]);
		if ($partyId === null) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Payment party is not open');
		}
		foreach ($numbers as $number) {
			if (!is_string($number) || !preg_match('/^[A-Za-z0-9 ._;\/-]{1,30}$/D', $number) || trim($number) === '') {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid invoice number');
			}
			$open = $db->all("SELECT id, valuta FROM openpost WHERE konto_id = ? AND faktnr = ? AND COALESCE(udlignet, '') <> '1'" . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$partyId, $number]);
			if (!$open) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invoice is not open: ' . $number, ['fields' => ['invoice_numbers']]);
			}
			foreach ($open as $row) {
				if (SaldiToolRuntime::currency($ctx, $row['valuta']) !== $ctx->baseCurrency) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Payment currency unsupported', ['reason' => 'currency_unsupported']);
				}
			}
		}
		if (!$numbers) {
			return null;
		}
		return SaldiToolRuntime::money($db->value("SELECT ABS(SUM(amount)) FROM openpost WHERE konto_id = ? AND COALESCE(udlignet, '') <> '1' AND faktnr IN (" . implode(',', array_fill(0, count($numbers), '?')) . ')', array_merge([$partyId], $numbers)));
	}

	/** @return array<string,mixed> */
	private static function common(array $args): array
	{
		return array_intersect_key($args, array_flip(['date', 'kladde_id', 'document_ref', 'request_id', 'explanation', 'citations', 'tainted']));
	}

	private static function poolSelect(SaldiDb $db): string
	{
		$optional = static fn(string $column, string $fallback = "''"): string => $db->hasColumn('pool_files', $column) ? 'p.' . $column : $fallback;
		$cast = $db->isPostgres() ? 'VARCHAR(50)' : 'CHAR(50)';
		$date = "CASE WHEN CHAR_LENGTH(TRIM(p.file_date)) = 10 AND SUBSTRING(p.file_date, 3, 1) IN ('-', '/', '.') "
			. "AND SUBSTRING(p.file_date, 6, 1) IN ('-', '/', '.') THEN CONCAT(SUBSTRING(p.file_date, 7, 4), '-', SUBSTRING(p.file_date, 4, 2), '-', SUBSTRING(p.file_date, 1, 2)) ELSE p.file_date END";
		return "SELECT p.id, p.filename, 'pool' AS source, NULL AS target_id, NULL AS bilag, $date AS file_date, p.amount, "
			. $optional('norm_amount', 'NULL') . ' AS norm_amount, p.currency, p.invoice_number, p.description, CAST(p.updated AS ' . $cast . ') AS created_at, '
			. $optional('manually_edited', $db->isPostgres() ? 'FALSE' : '0') . ' AS manually_edited, '
			. $optional('vendor_name') . ' AS vendor_name, ' . $optional('vendor_cvr') . ' AS vendor_cvr, '
			. $optional('vendor_match', 'NULL') . ' AS vendor_match, a.kontonr AS supplier_account, a.firmanavn AS supplier_name '
			. "FROM pool_files p LEFT JOIN adresser a ON a.id = " . $optional('vendor_konto_id', 'NULL') . " AND a.art = 'K'";
	}

	private static function attachedSelect(SaldiTenantContext $ctx, SaldiDb $db): string
	{
		$cast = $db->isPostgres() ? 'VARCHAR(50)' : 'CHAR(50)';
		$sources = [];
		foreach (['journal.read' => "(d.source = 'kassekladde' AND k.id IS NOT NULL)",
			'purchases.read' => "(d.source = 'creditorOrder' AND o.art IN ('KO','KK'))",
			'sales.read' => "(d.source = 'debitorOrdrer' AND o.art IN ('DO','DK'))"] as $permission => $condition) {
			if (SaldiPermissionService::allows($ctx, $db, $permission) && ($permission !== 'sales.read' || !SaldiPermissionService::revenueHidden($ctx, $db))) {
				$sources[] = $condition;
			}
		}
		return "SELECT d.id, d.filename, CASE d.source WHEN 'kassekladde' THEN 'journal' WHEN 'creditorOrder' THEN 'purchase_order' ELSE 'sales_order' END AS source, "
			. "CASE WHEN d.source = 'kassekladde' THEN k.kladde_id ELSE d.source_id END AS target_id, k.bilag, "
			. "CAST(COALESCE(k.transdate, o.fakturadate, o.ordredate) AS $cast) AS file_date, CAST(k.amount AS $cast) AS amount, NULL AS norm_amount, CASE WHEN d.source = 'kassekladde' THEN CASE WHEN COALESCE(k.valuta, 0) = 0 THEN '' ELSE (SELECT g.box1 FROM grupper g WHERE g.art = 'VK' AND g.kodenr = k.valuta ORDER BY g.id LIMIT 1) END ELSE o.valuta END AS currency, "
			. "COALESCE(k.faktura, CAST(o.fakturanr AS $cast), '') AS invoice_number, COALESCE(k.beskrivelse, '') AS description, CAST(d.timestamp AS $cast) AS created_at, "
			. ($db->isPostgres() ? 'FALSE' : '0') . " AS manually_edited, '' AS vendor_name, '' AS vendor_cvr, NULL AS vendor_match, NULL AS supplier_account, NULL AS supplier_name "
			. "FROM documents d LEFT JOIN kassekladde k ON d.source = 'kassekladde' AND k.id = d.source_id "
			. "LEFT JOIN kladdeliste j ON j.id = k.kladde_id LEFT JOIN ordrer o ON d.source IN ('creditorOrder','debitorOrdrer') AND o.id = d.source_id "
			. 'WHERE (' . ($sources ? implode(' OR ', $sources) : '1 = 0') . ") AND (d.source <> 'kassekladde' OR j.id IS NOT NULL)";
	}

	/** @return array{ref:string,filename:string,source:string,attached_to:?array,date:?string,amount:?string,currency:string,invoice_number:string,description:string,vendor:?array,extracted:bool,manually_edited:bool,created_at:string} */
	private static function row(SaldiTenantContext $ctx, array $raw): array
	{
		$pool = $raw['source'] === 'pool';
		if ($raw['source'] === 'journal' && $raw['currency'] === null) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Journal currency unavailable');
		}
		$amount = $raw['norm_amount'] ?? $raw['amount'];
		$amount = is_string($amount) && preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', trim($amount)) ? SaldiToolRuntime::money(trim($amount)) : null;
		$attached = null;
		if (!$pool) {
			$id = (int)$raw['target_id'];
			$label = $raw['source'] === 'journal' ? 'Kassekladde ' . $id . ', bilag ' . $raw['bilag'] : ($raw['source'] === 'purchase_order' ? 'Indkøbsordre ' : 'Salgsordre ') . $id;
			$attached = ['type' => $raw['source'], 'id' => $id, 'label' => $label, 'screen' => SaldiScreens::link($raw['source'], $id)];
		}
		$created = (string)$raw['created_at'];
		if (ctype_digit($created)) {
			$created = gmdate('Y-m-d\TH:i:s\Z', (int)$created);
		} elseif ($created !== '') {
			$created = (new DateTimeImmutable($created, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
		}
		$vendor = $pool && (trim((string)$raw['vendor_name']) !== '' || trim((string)$raw['vendor_cvr']) !== '' || $raw['supplier_account'] !== null)
			? ['name' => SaldiToolRuntime::text($raw['vendor_name']), 'cvr' => SaldiToolRuntime::text($raw['vendor_cvr']),
				'match' => $raw['vendor_match'], 'supplier_account' => $raw['supplier_account'] === null ? null : (string)$raw['supplier_account'],
				'supplier_name' => $raw['supplier_name'] === null ? null : SaldiToolRuntime::text($raw['supplier_name'])] : null;
		return ['ref' => ($pool ? 'pool:' : 'doc:') . $raw['id'], 'filename' => SaldiToolRuntime::text($raw['filename']),
			'source' => $raw['source'], 'attached_to' => $attached, 'date' => self::date($raw['file_date']), 'amount' => $amount,
			'currency' => SaldiToolRuntime::currency($ctx, $raw['currency']), 'invoice_number' => SaldiToolRuntime::text($raw['invoice_number']),
			'description' => SaldiToolRuntime::text($raw['description']), 'vendor' => $vendor,
			'extracted' => $pool && ($raw['norm_amount'] !== null || trim((string)$raw['amount']) !== '' || trim((string)$raw['file_date']) !== '' || trim((string)$raw['invoice_number']) !== '' || trim((string)$raw['vendor_name']) !== ''),
			'manually_edited' => in_array($raw['manually_edited'], [true, 1, '1', 't'], true), 'created_at' => $created];
	}

	private static function date(mixed $value): ?string
	{
		foreach (['!Y-m-d', '!d-m-Y', '!d/m/Y', '!d.m.Y'] as $format) {
			$date = DateTimeImmutable::createFromFormat($format, trim((string)$value), new DateTimeZone('UTC'));
			$errors = DateTimeImmutable::getLastErrors();
			if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
				return $date->format('Y-m-d');
			}
		}
		return null;
	}

	/** @return array{id:string,label:string,screen:string} */
	private static function citation(array $row): array
	{
		return ['id' => 'saldi:' . ($row['source'] === 'pool' ? 'pool_file:' : 'document:') . explode(':', $row['ref'])[1],
			'label' => $row['filename'], 'screen' => $row['attached_to']['screen'] ?? SaldiScreens::link('document_pool')];
	}
}
