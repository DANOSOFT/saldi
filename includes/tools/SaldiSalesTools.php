<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiSalesTools.php --- ai/mcp-assistant --- 2026.10.05
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
// Customer statements from openpost, including historical settlements and exact base-currency balances.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Add permission-checked customer statements with historical open items.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/../std_func.php';
require_once __DIR__ . '/../automation/SaldiPermissionService.php';
require_once __DIR__ . '/SaldiToolRuntime.php';

final class SaldiSalesTools
{
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function statement(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'debtors.read');
		$id = ifset($args, 'party_id');
		$account = ifset($args, 'account');
		if (($id === null && $account === null) || $id !== null && (!is_int($id) || $id < 1) || $account !== null && (!is_string($account) || !preg_match('/^[0-9]{1,15}$/D', $account))) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected customer id or account');
		}
		$party = $db->one("SELECT id, kontonr, firmanavn FROM adresser WHERE art = 'D' AND " . ($id !== null ? 'id = ?' : 'kontonr = ?'), [$id ?? $account]);
		if (!$party || $account !== null && (string)$party['kontonr'] !== $account) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Customer not found');
		}
		$year = $db->one("SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA' AND kodenr = ?", [$ctx->fiscalYear]);
		$from = SaldiToolRuntime::date($args['from'] ?? ($year ? SaldiToolRuntime::year($year)['start'] : substr(SaldiToolRuntime::today(), 0, 4) . '-01-01'));
		$to = SaldiToolRuntime::date($args['to'] ?? SaldiToolRuntime::today());
		if ($from > $to) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid statement range');
		}
		$conversion = SaldiToolRuntime::openpostBaseSql($ctx->baseCurrency);
		$base = $conversion['amount'];
		$historicalOpen = "(COALESCE(o.udlignet, '') <> '1' OR o.udlign_date IS NULL OR o.udlign_date > ?)";
		$totals = $db->one('SELECT COALESCE(SUM(CASE WHEN o.transdate < ? THEN ' . $base . ' ELSE 0 END), 0) AS opening, COALESCE(SUM(' . $base . '), 0) AS closing, COALESCE(SUM(CASE WHEN ' . $historicalOpen . ' AND COALESCE(o.forfaldsdate, o.transdate) < ? THEN ' . $base . ' ELSE 0 END), 0) AS overdue, COALESCE(SUM(' . $conversion['missing'] . '), 0) AS missing FROM openpost o WHERE o.konto_id = ? AND o.transdate <= ?', array_merge([$from], $conversion['params'], $conversion['params'], [$to, $to], $conversion['params'], $conversion['params'], [$party['id'], $to]));
		// The fixed contract has no missing-rate metadata: fail closed instead of giving partial balances.
		if ((int)$totals['missing'] > 0) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Customer statement has unavailable currency rates');
		}
		// A document link must agree with its sign: an invoice's own POS payment
		// shares refnr and faktnr, but has the opposite sign (ordrefunc.php:2203).
		$documentSource = "SELECT 1 FROM ordrer r WHERE r.id = o.refnr AND r.konto_id = o.konto_id AND r.status >= 3 AND r.art IN ('DO','DK') AND COALESCE(o.bilag_id,0) = 0 AND (COALESCE(r.fakturanr,'') = '' OR r.fakturanr = o.faktnr)";
		$document = "EXISTS (" . $documentSource . " AND ((r.art = 'DO' AND o.amount > 0) OR (r.art = 'DK' AND o.amount < 0)))";
		$documentPayment = "EXISTS (" . $documentSource . " AND ((r.art = 'DO' AND o.amount < 0) OR (r.art = 'DK' AND o.amount > 0)))";
		$journalOrigin = " FROM kassekladde k JOIN kontoplan c ON c.regnskabsaar = ? AND ((k.d_type = 'D' AND k.debet = " . (int)$party['kontonr'] . " AND k.k_type = 'F' AND c.kontonr = k.kredit) OR (k.k_type = 'D' AND k.kredit = " . (int)$party['kontonr'] . " AND k.d_type = 'F' AND c.kontonr = k.debet)) WHERE k.id = o.bilag_id AND k.kladde_id = o.kladde_id";
		$journalInvoice = "EXISTS (SELECT 1" . $journalOrigin . " AND c.kontotype = 'D')";
		$journalPayment = "EXISTS (SELECT 1" . $journalOrigin . " AND c.kontotype = 'S')";
		$invoice = "(" . $document . " OR " . $journalInvoice . " OR (NOT " . $documentPayment . " AND NOT " . $journalPayment . " AND COALESCE(o.amount,0) > 0 AND TRIM(COALESCE(o.faktnr,'')) <> ''))";
		// Old imported rows may lack a source. Keep the conventional sign-based
		// grouping visible, while explicitly disclosing that its origin is uncertain.
		$known = "(" . $document . " OR " . $documentPayment . " OR " . $journalInvoice . " OR " . $journalPayment . " OR (o.amount > 0 AND TRIM(COALESCE(o.faktnr,'')) <> '' AND o.forfaldsdate IS NOT NULL))";
		$selection = 'SELECT o.id, o.faktnr, o.transdate, o.forfaldsdate, o.amount, o.valuta, CASE WHEN ' . $known . ' THEN 0 ELSE 1 END AS classification_uncertain FROM openpost o WHERE o.konto_id = ? AND o.transdate <= ?';
		$sets = ['open_items' => [$historicalOpen, [$to]], 'payments' => ['o.transdate >= ? AND NOT ' . $invoice, [$from]], 'invoices' => ['o.transdate >= ? AND ' . $invoice, [$from]]];
		$data = ['party' => ['party_id' => (int)$party['id'], 'account' => (string)$party['kontonr'], 'name' => SaldiToolRuntime::text($party['firmanavn'])], 'from' => $from, 'to' => $to, 'currency' => $ctx->baseCurrency];
		$more = false;
		$count = 0;
		foreach ($sets as $key => [$condition, $params]) {
			$query = $selection . ' AND ' . $condition . ' ORDER BY o.transdate, o.id LIMIT 501';
			$bindings = [$ctx->fiscalYear, $ctx->fiscalYear, $party['id'], $to];
			if ($key !== 'open_items') {
				$bindings[] = $params[0];
				$bindings[] = $ctx->fiscalYear;
				$bindings[] = $ctx->fiscalYear;
			} else {
				$bindings = array_merge($bindings, $params);
			}
			$rows = $db->all($query, $bindings);
			$more = $more || count($rows) > 500;
			$rows = array_slice($rows, 0, 500);
			$data[$key] = array_map(fn(array $r): array => ['item_id' => (int)$r['id'], 'invoice' => (string)($r['faktnr'] ?? ''), 'date' => (string)$r['transdate'], 'due_date' => $r['forfaldsdate'], 'amount' => SaldiToolRuntime::money($r['amount']), 'currency' => SaldiToolRuntime::currency($ctx, $r['valuta']), 'classification_uncertain' => (int)$r['classification_uncertain'] === 1], $rows);
			$count += count($rows);
		}
		$data += ['opening_balance' => SaldiToolRuntime::money($totals['opening']), 'closing_balance' => SaldiToolRuntime::money($totals['closing']), 'overdue_sum' => SaldiToolRuntime::money($totals['overdue'])];
		// Preserve complete totals while keeping large historical statements within the tool envelope limit.
		while (strlen(SaldiToolRuntime::canonical($data)) > 190 * 1024) {
			$largest = 'open_items';
			foreach (['payments','invoices'] as $key) {
				if (count($data[$key]) > count($data[$largest])) {
					$largest = $key;
				}
			}
			array_pop($data[$largest]);
			$count--;
			$more = true;
		}
		return SaldiToolRuntime::result($data, [SaldiToolRuntime::citation('party', (int)$party['id'], (string)$party['firmanavn'], 'debtor')], $more, $count);
	}
}
