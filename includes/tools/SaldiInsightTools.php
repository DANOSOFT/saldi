<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiInsightTools.php --- ai/mcp-assistant --- 2026.10.05
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
// Read-only period, year-end, till, inventory and master-data insights.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Implement five permission-gated read-only insight handlers.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261005 CL/LH get_year_end_pack: calendar years map to the fiscal-year number; result trimmed under the MCP limit.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/SaldiToolRuntime.php';
require_once __DIR__ . '/../std_func.php';
require_once __DIR__ . '/../automation/SaldiYearEndPack.php';
require_once __DIR__ . '/../automation/SaldiTillSummary.php';
require_once __DIR__ . '/../automation/SaldiMasterDataHygiene.php';

final class SaldiInsightTools
{
	/** Posted debit minus credit; sum accounts include only D/S members and never primo.
	 * @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int}
	 */
	public static function comparePeriods(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'ledger.read');
		$years = [];
		foreach ($db->all("SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA' ORDER BY kodenr DESC") as $row) {
			$years[] = SaldiToolRuntime::year($row);
		}
		$ids = [];
		foreach (['a', 'b'] as $period) {
			$from = SaldiToolRuntime::date($args[$period . '_from']);
			$to = SaldiToolRuntime::date($args[$period . '_to']);
			$match = array_values(array_filter($years, static fn(array $year): bool => $from >= $year['start'] && $to <= $year['end']));
			if ($from > $to || !$match) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Comparison range must lie in a fiscal year');
			}
			$ids[$period] = $match[0]['id'];
		}
		$level = ifset($args, 'level', 'group');
		if (!in_array($level, ['account', 'group'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Unknown comparison level');
		}
		$hidden = SaldiPermissionService::revenueHidden($ctx, $db);
		// Prefer the second chart; retain accounts/groups present only in the first chart.
		$chart = 'SELECT k.kontonr, k.beskrivelse, k.kontotype, k.fra_kto, k.regnskabsaar FROM kontoplan k WHERE k.regnskabsaar = ? OR (k.regnskabsaar = ? AND NOT EXISTS (SELECT 1 FROM kontoplan b WHERE b.regnskabsaar = ? AND b.kontonr = k.kontonr))';
		$memberA = $level === 'group' ? 'm.kontonr >= ka.fra_kto AND m.kontonr <= ka.kontonr' : 'm.kontonr = ka.kontonr';
		$memberB = $level === 'group' ? 'm.kontonr >= kb.fra_kto AND m.kontonr <= kb.kontonr' : 'm.kontonr = kb.kontonr';
		$kind = $level === 'group' ? "='Z'" : " IN ('D','S')";
		// Aggregate movements once before joining to sum ranges. Repeated correlated sums trigger
		// costly plans on legacy charts, and must not spend the runtime's five-second query budget.
		$period = "SELECT p.kontonr, p.regnskabsaar, SUM(CAST(COALESCE(t.debet,0) AS DECIMAL(30,6))-CAST(COALESCE(t.kredit,0) AS DECIMAL(30,6))) AS %s, 0 AS %s FROM kontoplan p JOIN transaktioner t ON t.kontonr=p.kontonr WHERE p.regnskabsaar=? AND p.kontotype IN ('D','S') AND t.transdate>=? AND t.transdate<=? GROUP BY p.kontonr, p.regnskabsaar";
		// UNION column names come from its first SELECT; put b's amount in the second column.
		$movements = sprintf($period, 'a', 'b') . ' UNION ALL ' . "SELECT p.kontonr, p.regnskabsaar, 0 AS a, SUM(CAST(COALESCE(t.debet,0) AS DECIMAL(30,6))-CAST(COALESCE(t.kredit,0) AS DECIMAL(30,6))) AS b FROM kontoplan p JOIN transaktioner t ON t.kontonr=p.kontonr WHERE p.regnskabsaar=? AND p.kontotype IN ('D','S') AND t.transdate>=? AND t.transdate<=? GROUP BY p.kontonr, p.regnskabsaar";
		$where = 'k.kontotype' . $kind;
		$select = 'SELECT k.kontonr, k.beskrivelse, k.regnskabsaar, COALESCE(SUM(m.a),0) AS a, COALESCE(SUM(m.b),0) AS b FROM (' . $chart . ') k LEFT JOIN kontoplan ka ON ka.regnskabsaar=? AND ka.kontonr=k.kontonr AND ka.kontotype' . $kind . ' LEFT JOIN kontoplan kb ON kb.regnskabsaar=? AND kb.kontonr=k.kontonr AND kb.kontotype' . $kind . ' LEFT JOIN (' . $movements . ') m ON (m.regnskabsaar=ka.regnskabsaar AND ' . $memberA . ') OR (m.regnskabsaar=kb.regnskabsaar AND ' . $memberB . ') WHERE ' . $where;
		$params = [$ids['b'], $ids['a'], $ids['b'], $ids['a'], $ids['b'], $ids['a'], $args['a_from'], $args['a_to'], $ids['b'], $args['b_from'], $args['b_to']];
		if ($hidden) {
			$select .= " AND NOT EXISTS (SELECT 1 FROM kontoplan m WHERE m.kontotype='D' AND ((m.regnskabsaar=ka.regnskabsaar AND " . $memberA . ') OR (m.regnskabsaar=kb.regnskabsaar AND ' . $memberB . ')))';
		}
		$select .= ' GROUP BY k.kontonr, k.beskrivelse, k.regnskabsaar';
		$select = 'SELECT q.*, b-a AS difference, CASE WHEN a=0 THEN NULL ELSE ROUND((b-a)*100/ABS(a),3) END AS change_pct FROM (' . $select . ') q';
		if (ifset($args, 'only_changed', false)) {
			$select .= ' WHERE a <> b';
		}
		$limit = max(1, min(100, (int)(ifset($args, 'limit', 25))));
		$page = SaldiToolRuntime::page($db, $select, 'ABS(b-a) DESC, kontonr', $params, ['limit' => $limit]);
		$rows = $cites = [];
		foreach ($page['rows'] as $row) {
			$rows[] = ['key' => (string)$row['kontonr'], 'name' => SaldiToolRuntime::text($row['beskrivelse']), 'a' => SaldiToolRuntime::money($row['a']), 'b' => SaldiToolRuntime::money($row['b']), 'change' => SaldiToolRuntime::money($row['difference']), 'change_pct' => $row['change_pct'] === null ? null : (string)$row['change_pct'], 'currency' => $ctx->baseCurrency];
			$cites[] = SaldiToolRuntime::citation('account', $row['kontonr'] . '@' . $row['regnskabsaar'], $row['kontonr'] . ' ' . $row['beskrivelse']);
		}
		return SaldiToolRuntime::result(['rows' => $rows, 'total' => $page['total'], 'currency' => $ctx->baseCurrency, 'revenue_hidden' => $hidden], $cites, $page['more']);
	}
	/** @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int} */
	public static function yearEndPack(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'ledger.read');
		$year = (int)ifset($args, 'fiscal_year', $ctx->fiscalYear);
		if ($year >= 1900) {
			// "2026" is a calendar year; Saldi numbers fiscal years 1, 2, 3 ... (grupper RA kodenr).
			$mapped = $db->value("SELECT kodenr FROM grupper WHERE art = 'RA' AND box2 = ? ORDER BY kodenr DESC LIMIT 1", [(string)$year]);
			if ($mapped === null) {
				throw new SaldiRuntimeError('NOT_FOUND', 'No fiscal year starts in ' . $year);
			}
			$year = (int)$mapped;
		}
		$result = SaldiYearEndPack::build($ctx, $db, $year);
		// The full pack (every account and open item) is the Revisorpakke page; the tool returns
		// the parts a conversation needs and stays under the MCP response limit.
		$data = $result['data'];
		$trimmed = false;
		if (isset($data['trial_balance']['rows']) && is_array($data['trial_balance']['rows'])) {
			$rows = array_values(array_filter($data['trial_balance']['rows'], static fn(array $r): bool => ($r['opening'] ?? '0.00') !== '0.00' || ($r['debit'] ?? '0.00') !== '0.00' || ($r['credit'] ?? '0.00') !== '0.00' || ($r['closing'] ?? '0.00') !== '0.00'));
			usort($rows, static fn(array $a, array $b): int => abs((float)$b['closing']) <=> abs((float)$a['closing']));
			$trimmed = $trimmed || count($rows) > 60 || count($rows) !== count($data['trial_balance']['rows']);
			$data['trial_balance']['rows'] = array_slice($rows, 0, 60);
		}
		if (isset($data['open_items']) && is_array($data['open_items']) && count($data['open_items']) > 30) {
			$items = $data['open_items'];
			usort($items, static fn(array $a, array $b): int => abs((float)$b['amount']) <=> abs((float)$a['amount']));
			$data['open_items'] = array_slice($items, 0, 30);
			$trimmed = true;
		}
		$result['data'] = $data;
		if (count($result['citations']) > 40) {
			$result['citations'] = array_slice($result['citations'], 0, 40);
			$trimmed = true;
		}
		$result['possibly_incomplete'] = ($result['possibly_incomplete'] ?? false) || $trimmed;
		return $result;
	}
	/** @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int} */
	public static function tillSummary(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'sales.read');
		if (SaldiPermissionService::revenueHidden($ctx, $db)) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Till summary reveals revenue');
		}
		return SaldiTillSummary::build($ctx, $db, SaldiToolRuntime::date($args['date']), isset($args['till']) ? (int)$args['till'] : null);
	}
	/** Latest count date, preserving warehouse/variant rows rather than merging distinct counts.
	 * @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int}
	 */
	public static function stockCount(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'products.read');
		// Legacy count sessions store YYYYMMDDHHMMSS text in local calendar time.
		$reference = SaldiToolRuntime::date(ifset($args, 'date', SaldiToolRuntime::today()));
		$end = str_replace('-', '', $reference) . '235959';
		$latest = $db->value('SELECT MAX(tidspkt) FROM regulering WHERE tidspkt <= ?', [$end]);
		$date = $latest === null ? null : SaldiToolRuntime::date(substr($latest, 0, 4) . '-' . substr($latest, 4, 2) . '-' . substr($latest, 6, 2));
		$rows = $cites = [];
		$more = false;
		if ($date !== null) {
			$start = str_replace('-', '', $date) . '000000';
			$stop = (new DateTimeImmutable($date))->modify('+1 day')->format('Ymd') . '000000';
			$counts = $db->all('SELECT r.vare_id, r.lager, r.variant_id, r.beholdning, r.optalt, r.bogfort, v.varenr, v.beskrivelse, (COALESCE(r.optalt,0)-COALESCE(r.beholdning,0)) AS difference, (COALESCE(r.optalt,0)-COALESCE(r.beholdning,0))*COALESCE(v.kostpris,0) AS value_difference FROM regulering r JOIN varer v ON v.id=r.vare_id WHERE r.tidspkt >= ? AND r.tidspkt < ? ORDER BY r.tidspkt, r.id LIMIT 201', [$start, $stop]);
			$more = count($counts) > 200;
			foreach (array_slice($counts, 0, 200) as $row) {
				$rows[] = ['product_id' => (int)$row['vare_id'], 'warehouse' => (int)$row['lager'], 'variant_id' => (int)$row['variant_id'], 'item_no' => SaldiToolRuntime::text($row['varenr']), 'text' => SaldiToolRuntime::text($row['beskrivelse']), 'expected' => self::quantity($row['beholdning']), 'counted' => self::quantity($row['optalt']), 'difference' => self::quantity($row['difference']), 'value_difference' => SaldiToolRuntime::money($row['value_difference']), 'currency' => $ctx->baseCurrency, 'posted' => (string)$row['bogfort'] === '1'];
				$cites[] = SaldiToolRuntime::citation('product', (int)$row['vare_id'], $row['varenr'] . ' ' . $row['beskrivelse'], 'product');
			}
		}
		$fefo = [];
		$expiryEnabled = SaldiSettings::get($db, 'items', 'batchExpiryEnabled', 'off') === 'on' || $db->value("SELECT 1 FROM grupper WHERE art='VG' AND box9='on' AND fiscal_year=? LIMIT 1", [$ctx->fiscalYear]);
		if ($expiryEnabled) {
			$userDays = $ctx->userId > 0 ? $db->value("SELECT var_value FROM settings WHERE var_grp='lager' AND var_name='due_date_warning_days' AND user_id=? AND COALESCE(pos_id,0)=0 ORDER BY id DESC LIMIT 1", [$ctx->userId]) : null;
			$days = max(0, (int)(if_isset($userDays, SaldiSettings::get($db, 'lager', 'due_date_warning_days', '30'))));
			$warning = (new DateTimeImmutable($reference))->modify('+' . $days . ' days')->format('Y-m-d');
			$batches = $db->all('SELECT b.id, b.vare_id, b.due_date, b.rest, v.varenr, v.beskrivelse FROM batch_kob b JOIN varer v ON v.id=b.vare_id WHERE b.rest > 0 AND b.due_date <= ? ORDER BY b.due_date, b.kobsdate, b.id LIMIT 201', [$warning]);
			$more = $more || count($batches) > 200;
			foreach (array_slice($batches, 0, 200) as $batch) {
				$fefo[] = ['product_id' => (int)$batch['vare_id'], 'batch_id' => (int)$batch['id'], 'item_no' => SaldiToolRuntime::text($batch['varenr']), 'text' => SaldiToolRuntime::text($batch['beskrivelse']), 'expiry_date' => $batch['due_date'], 'days_left' => (int)(new DateTimeImmutable($reference))->diff(new DateTimeImmutable($batch['due_date']))->format('%r%a'), 'quantity' => self::quantity($batch['rest']), 'screen' => SaldiScreens::link('product', (int)$batch['vare_id'])];
				$cites[] = SaldiToolRuntime::citation('product', (int)$batch['vare_id'], $batch['varenr'] . ' ' . $batch['beskrivelse'], 'product');
			}
		}
		return SaldiToolRuntime::result(['date' => $date, 'rows' => $rows, 'fefo' => $fefo], array_values(array_unique($cites, SORT_REGULAR)), $more);
	}
	/** Preserve exact legacy quantity precision (three decimals). */
	public static function quantity(mixed $value): string
	{
		$value = (string)(if_isset($value, '0'));
		if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]*))?$/D', $value, $matches)) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Invalid stored quantity');
		}
		return $matches[1] . $matches[2] . '.' . str_pad(substr(ifset($matches, 3, ''), 0, 3), 3, '0');
	}
	/** @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int} */
	public static function masterData(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'company.read');
		$findings = SaldiMasterDataHygiene::scan($ctx, $db, 100);
		return SaldiToolRuntime::result(['rows' => $findings['rows'], 'total' => $findings['total']], [], $findings['total'] > count($findings['rows']));
	}
}
