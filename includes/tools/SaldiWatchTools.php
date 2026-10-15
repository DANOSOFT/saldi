<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiWatchTools.php --- ai/mcp-assistant --- 2026.10.03
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
// Permission-gated daily overview, posted VAT totals and period readiness facts.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Implement Watch v2 read handlers and exact legacy VAT rounding.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/SaldiToolRuntime.php';
require_once __DIR__ . '/../automation/SaldiDailyOverview.php';

final class SaldiWatchTools
{
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function overview(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'company.read');
		$data = SaldiDailyOverview::build($ctx, $db);
		return SaldiToolRuntime::result($data, [], count($data['open_items']['interrupts'] ?? []) === 20);
	}
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function vatSummary(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'ledger.read');
		if (SaldiPermissionService::revenueHidden($ctx, $db)) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'VAT reveals revenue');
		}
		[$from, $to] = self::range($args);
		$yearId = null;
		foreach ($db->all("SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA' ORDER BY kodenr DESC") as $row) {
			$year = SaldiToolRuntime::year($row);
			if ($from >= $year['start'] && $from <= $year['end']) {
				if ($to > $year['end']) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'VAT range crosses fiscal years');
				}
				$yearId = $year['id'];
				break;
			}
		}
		if ($yearId === null) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'VAT range outside a fiscal year');
		}
		$groups = $db->all("SELECT art, kode, kodenr, box1, box2 FROM grupper WHERE art IN ('SM','KM','EM','YM') AND fiscal_year = ? ORDER BY art, kodenr, id", [$yearId]);
		$accountKinds = [];
		foreach ($groups as $group) {
			if (preg_match('/^[0-9]{1,12}$/D', trim((string)$group['box1']))) {
				$account = (string)(int)$group['box1'];
				if (!isset($accountKinds[$account]) || $group['art'] !== 'KM') {
					$accountKinds[$account] = $group['art'];
				}
			}
		}
		$amounts = [];
		if ($accountKinds) {
			$accounts = array_keys($accountKinds);
			$rows = $db->all('SELECT kontonr, ROUND(SUM(ROUND(COALESCE(debet, 0), 2) - ROUND(COALESCE(kredit, 0), 2)), 0) AS amount FROM transaktioner WHERE transdate >= ? AND transdate <= ? AND kontonr IN (' . implode(',', array_fill(0, count($accounts), '?')) . ') GROUP BY kontonr', array_merge([$from, $to], $accounts));
			foreach ($rows as $row) {
				$amounts[(string)(int)$row['kontonr']] = (int)$row['amount'];
			}
		}
		$sales = 0;
		$purchase = 0;
		$output = 0;
		foreach ($accountKinds as $account => $art) {
			$amount = $amounts[$account] ?? 0;
			if ($art === 'KM') {
				$purchase += $amount;
			} elseif ($art === 'SM') {
				$sales -= $amount;
			} else {
				$output -= $amount;
			}
		}
		$codes = [];
		$citations = [];
		foreach ($groups as $group) {
			$account = (string)(int)$group['box1'];
			if (!isset($accountKinds[$account])) {
				continue;
			}
			$code = ((string)$group['kode'] !== '' ? (string)$group['kode'] : substr($group['art'], 0, 1)) . $group['kodenr'];
			$amount = ($amounts[$account] ?? 0) * ($group['art'] === 'KM' ? 1 : -1);
			$codes[] = ['code' => $code, 'kind' => ['SM' => 'sales', 'KM' => 'purchase', 'EM' => 'eu_purchase', 'YM' => 'foreign_service'][$group['art']], 'rate' => (string)$group['box2'], 'vat_account' => $account, 'vat_amount' => SaldiToolRuntime::money((string)$amount), 'currency' => $ctx->baseCurrency];
			$citations[] = SaldiToolRuntime::citation('account', $account . '@' . $yearId, $code . ' · ' . $account);
		}
		$payable = 0;
		$range = $db->one("SELECT box1, box2 FROM grupper WHERE art = 'MR' ORDER BY id LIMIT 1");
		if (!empty($range['box1']) && !empty($range['box2'])) {
			$accounts = $db->all('SELECT kontonr, kontotype, fra_kto FROM kontoplan WHERE regnskabsaar = ? AND kontonr >= ? AND kontonr <= ? ORDER BY kontonr', [$yearId, (int)$range['box1'], (int)$range['box2']]);
			foreach ($accounts as $accountRow) {
				$account = (int)$accountRow['kontonr'];
				$amount = '0';
				if (in_array($accountRow['kontotype'], ['D', 'S', 'Z'], true)) {
					$condition = $accountRow['kontotype'] === 'Z' ? 'kontonr >= ? AND kontonr < ?' : 'kontonr = ?';
					$params = $accountRow['kontotype'] === 'Z' ? [(int)$accountRow['fra_kto'], $account] : [$account];
					$amount = $db->value('SELECT ROUND(COALESCE(SUM(ROUND(COALESCE(debet, 0), 2) - ROUND(COALESCE(kredit, 0), 2)), 0), 0) FROM transaktioner WHERE transdate >= ? AND transdate <= ? AND kontonr >= ? AND kontonr <= ? AND ' . $condition, array_merge([$from, $to, (int)$range['box1'], (int)$range['box2']], $params));
				}
				$payable -= (int)$amount;
				$id = 'saldi:account:' . $account . '@' . $yearId;
				if (!in_array($id, array_column($citations, 'id'), true)) {
					$citations[] = SaldiToolRuntime::citation('account', $account . '@' . $yearId, (string)$account);
				}
			}
		}
		$months = SaldiDailyOverview::vatMonths($db, $from, $to);
		$data = ['from' => $from, 'to' => $to, 'codes' => $codes, 'sales_vat' => SaldiToolRuntime::money((string)$sales), 'purchase_vat' => SaldiToolRuntime::money((string)$purchase), 'payable' => SaldiToolRuntime::money((string)$payable), 'currency' => $ctx->baseCurrency, 'period_closed' => !in_array(false, array_column($months, 'closed'), true), 'revenue_hidden' => false, 'note' => 'Computed from posted transactions only; unposted journals are not included.'];
		return SaldiToolRuntime::result($data, $citations);
	}
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function periodStatus(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'journal.read');
		[$from, $to] = self::range($args);
		$select = "SELECT journal.id, journal.kladdenote, journal.kladdedate, COUNT(*) AS lines FROM kladdeliste journal JOIN kassekladde line ON line.kladde_id = journal.id WHERE journal.bogfort = '-' AND line.transdate >= ? AND line.transdate <= ? GROUP BY journal.id, journal.kladdenote, journal.kladdedate";
		$params = [$from, $to];
		$total = (int)$db->value('SELECT COUNT(*) FROM (' . $select . ') journals', $params);
		$rows = $db->all($select . ' ORDER BY journal.kladdedate, journal.id LIMIT 20', $params);
		$journals = array_map(static fn(array $row): array => ['journal_id' => (int)$row['id'], 'note' => SaldiToolRuntime::text($row['kladdenote']), 'date' => $row['kladdedate'], 'lines' => (int)$row['lines'], 'screen' => SaldiScreens::link('journal', (int)$row['id'])], $rows);
		$missing = SaldiDailyOverview::missingDocuments($ctx, $db, $from, $to, false);
		$data = ['from' => $from, 'to' => $to, 'open_journals' => $journals, 'unposted_lines' => (int)$db->value("SELECT COUNT(*) FROM kassekladde line JOIN kladdeliste journal ON journal.id = line.kladde_id WHERE journal.bogfort = '-' AND line.transdate >= ? AND line.transdate <= ?", $params), 'lines_without_document' => $missing['count'], 'vat_months' => SaldiDailyOverview::vatMonths($db, $from, $to), 'last_posted_date' => $db->value('SELECT MAX(transdate) FROM transaktioner WHERE transdate >= ? AND transdate <= ?', $params)];
		$citations = array_map(static fn(array $row): array => SaldiToolRuntime::citation('journal', $row['journal_id'], $row['note'], 'journal'), $journals);
		return SaldiToolRuntime::result($data, $citations, $total > 20);
	}
	/** @return array{0:string,1:string} Validated inclusive dates; no SQL input is interpolated. */
	private static function range(array $args): array
	{
		if (!is_string($args['from'] ?? null) || !is_string($args['to'] ?? null)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Date range required');
		}
		$from = SaldiToolRuntime::date($args['from']);
		$to = SaldiToolRuntime::date($args['to']);
		if ($from > $to) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Reversed date range');
		}
		return [$from, $to];
	}
}
