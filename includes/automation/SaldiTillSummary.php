<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiTillSummary.php --- ai/mcp-assistant --- 2026.10.05
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
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/../tools/SaldiToolRuntime.php';
require_once __DIR__ . '/../std_func.php';

final class SaldiTillSummary
{
	/** @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int} */
	public static function build(SaldiTenantContext $ctx, SaldiDb $db, string $date, ?int $till): array
	{
		SaldiPermissionService::require($ctx, $db, 'sales.read');
		if (SaldiPermissionService::revenueHidden($ctx, $db)) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Till summary reveals revenue');
		}
		SaldiToolRuntime::date($date);
		$where = "o.fakturadate=? AND o.status>=3 AND o.art IN ('PO','DO','DK') AND TRIM(COALESCE(o.felt_5,''))<>''";
		$params = [$date];
		if ($till !== null) {
			if ($till < 1) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid till');
			}
			$where .= ' AND o.felt_5=?';
			$params[] = (string)$till;
		}
		$totals = $db->all('SELECT o.felt_5 AS till, o.valuta, COUNT(*) AS receipts, SUM(COALESCE(o.sum,0)) AS net, SUM(COALESCE(o.sum,0)+COALESCE(o.moms,0)) AS gross, SUM(CASE WHEN o.sum<0 OR COALESCE(o.kred_ord_id,0)>0 THEN COALESCE(o.sum,0)+COALESCE(o.moms,0) ELSE 0 END) AS refunds FROM ordrer o WHERE ' . $where . ' GROUP BY o.felt_5, o.valuta ORDER BY o.felt_5', $params);
		$orders = $db->all('SELECT o.id FROM ordrer o WHERE ' . $where . ' ORDER BY o.id LIMIT 201', $params);
		$citations = array_map(static fn(array $order): array => SaldiToolRuntime::citation('order', (int)$order['id'], 'Bon ' . $order['id'], 'sales_order'), array_slice($orders, 0, 200));
		$rows = [];
		$more = count($orders) > 200;
		foreach ($totals as $total) {
			if (!ctype_digit(trim((string)$total['till']))) {
				$more = true;
				continue;
			}
			$currency = SaldiToolRuntime::currency($ctx, $total['valuta']);
			// Never sum foreign and base-currency sales silently.
			$key = (int)$total['till'] . ':' . $currency;
			if (isset($rows[$key])) {
				$sum = $db->one('SELECT CAST(? AS DECIMAL(30,6))+CAST(? AS DECIMAL(30,6)) AS net, CAST(? AS DECIMAL(30,6))+CAST(? AS DECIMAL(30,6)) AS gross, CAST(? AS DECIMAL(30,6))+CAST(? AS DECIMAL(30,6)) AS refunds', [$rows[$key]['net'], $total['net'], $rows[$key]['gross'], $total['gross'], $rows[$key]['refund_raw'], $total['refunds']]);
				$rows[$key]['net'] = $sum['net'];
				$rows[$key]['gross'] = $sum['gross'];
				$rows[$key]['refund_raw'] = $sum['refunds'];
				$rows[$key]['receipts'] += (int)$total['receipts'];
			} else {
				$rows[$key] = self::emptyRow((int)$total['till'], $currency) + ['net' => $total['net'], 'gross' => $total['gross'], 'refund_raw' => $total['refunds']];
				$rows[$key]['receipts'] = (int)$total['receipts'];
			}
		}
		// Subtract total tender minus gross once per order from base-currency cash, even for card-only change.
		// With no payment rows the legacy fallback is felt_1 and gross; there is no inferred change.
		$payments = $db->all("SELECT o.felt_5 AS till, COALESCE(p.betalingstype,o.felt_1,'') AS method, COALESCE(p.valuta,o.valuta) AS valuta, SUM(COALESCE(p.amount,COALESCE(o.sum,0)+COALESCE(o.moms,0))) AS amount FROM ordrer o LEFT JOIN pos_betalinger p ON p.ordre_id=o.id WHERE " . $where . ' GROUP BY o.felt_5, COALESCE(p.betalingstype,o.felt_1,\'\'), COALESCE(p.valuta,o.valuta)', $params);
		$paymentMap = [];
		foreach ($payments as $payment) {
			$method = str_starts_with($payment['method'], 'Betalingskort|') ? 'Betalingskort' : $payment['method'];
			$currency = SaldiToolRuntime::currency($ctx, $payment['valuta']);
			$paymentMap[(int)$payment['till']][$currency][$method][] = $payment['amount'];
			$key = (int)$payment['till'] . ':' . $currency;
			if (ctype_digit(trim((string)$payment['till']))) {
				$rows[$key] ??= self::emptyRow((int)$payment['till'], $currency);
			}
		}
		$changes = $db->all('SELECT o.felt_5 AS till, SUM(COALESCE(o.sum,0)+COALESCE(o.moms,0)-(SELECT SUM(COALESCE(p.amount,0) * CASE WHEN COALESCE(NULLIF(p.valuta,\'\'),\'-\') IN (\'-\',?) THEN 1 ELSE COALESCE(NULLIF(p.valutakurs,0),100)/100 END) FROM pos_betalinger p WHERE p.ordre_id=o.id)) AS amount FROM ordrer o WHERE ' . $where . " AND COALESCE(NULLIF(o.valuta,''),?) IN (?,'-') AND EXISTS (SELECT 1 FROM pos_betalinger p WHERE p.ordre_id=o.id) GROUP BY o.felt_5", array_merge([$ctx->baseCurrency], $params, [$ctx->baseCurrency, $ctx->baseCurrency]));
		foreach ($changes as $change) {
			$paymentMap[(int)$change['till']][$ctx->baseCurrency]['Kontant'][] = $change['amount'];
		}
		// Exact head match prevents tills on the same date sharing one count. Latest report wins.
		$heads = $db->all("SELECT report_number, description FROM report WHERE type='Head line' AND date=? AND description LIKE 'Cash count, box %' ORDER BY report_number, id", [$date]);
		foreach ($heads as $head) {
			if (!preg_match('/^Cash count, box ([0-9]+)$/D', $head['description'], $match) || ($till !== null && (int)$match[1] !== $till)) {
				continue;
			}
			foreach ($rows as &$row) {
				if ($row['till'] === (int)$match[1]) {
					$row['expected'] = $row['counted'] = $row['difference'] = null;
				}
			}
			unset($row);
			$parsed = false;
			$countRows = $db->all("SELECT description, total FROM report WHERE type='cashCount' AND report_number=? AND date=? ORDER BY id", [$head['report_number'], $date]);
			// Positional summary blocks follow denomination rows. Unknown layouts stay unavailable.
			for ($i = 0; $i + 2 < count($countRows); $i++) {
				if (!preg_match('/^Forventet beholdning ([A-Z]{3}):$/uD', trim($countRows[$i]['description']), $currency)) {
					continue;
				}
				$code = $currency[1];
				if (trim($countRows[$i + 1]['description']) !== 'Optalt beholdning ' . $code . ':' || trim($countRows[$i + 2]['description']) !== 'Difference ' . $code . ':') {
					$more = true;
					continue;
				}
				$parsed = true;
				$key = (int)$match[1] . ':' . $code;
				$rows[$key] ??= self::emptyRow((int)$match[1], $code);
				foreach (['expected', 'counted', 'difference'] as $offset => $field) {
					$rows[$key][$field] = SaldiToolRuntime::money($countRows[$i + $offset]['total']);
				}
			}
			$more = $more || !$parsed;
		}
		foreach ($rows as &$row) {
			$row['turnover_net'] = SaldiToolRuntime::money(ifset($row, 'net', '0'));
			$row['turnover_gross'] = SaldiToolRuntime::money(ifset($row, 'gross', '0'));
			$row['refunds'] = SaldiToolRuntime::money(ifset($row, 'refund_raw', '0'));
			unset($row['net'], $row['gross'], $row['refund_raw']);
			foreach (if_array($paymentMap, [$row['till'], $row['currency']]) as $method => $amounts) {
				$sumSql = implode('+', array_fill(0, count($amounts), 'CAST(? AS DECIMAL(30,6))'));
				$amount = $db->value('SELECT ' . $sumSql, $amounts);
				$row['payments'][] = ['method' => SaldiToolRuntime::text($method), 'currency' => $row['currency'], 'amount' => SaldiToolRuntime::money($amount)];
			}
			$postings = $db->all("SELECT transdate, kontonr, bilag, COALESCE(debet,0)-COALESCE(kredit,0) AS amount FROM transaktioner WHERE transdate=? AND (kasse_nr=? OR COALESCE(kasse_nr,0)=0) AND beskrivelse=? ORDER BY id LIMIT 201", [$date, $row['till'], $row['currency'] === $ctx->baseCurrency ? 'Kassedifference, kasse ' . $row['till'] : 'Kassedifference ' . $row['currency'] . ', kasse ' . $row['till']]);
			$more = $more || count($postings) > 200;
			foreach (array_slice($postings, 0, 200) as $posting) {
				$row['cash_difference_postings'][] = ['date' => $posting['transdate'], 'account' => (string)$posting['kontonr'], 'bilag' => (int)$posting['bilag'], 'currency' => $ctx->baseCurrency, 'amount' => SaldiToolRuntime::money($posting['amount'])];
			}
		}
		unset($row);
		ksort($rows, SORT_NATURAL);
		$more = $more || count($rows) > 100;
		return SaldiToolRuntime::result(['date' => $date, 'currency' => $ctx->baseCurrency, 'revenue_hidden' => false, 'rows' => array_slice(array_values($rows), 0, 100)], $citations, $more);
	}
	/** @return array{till:int,receipts:int,currency:string,payments:array,expected:null,counted:null,difference:null,cash_difference_postings:array} */
	private static function emptyRow(int $till, string $currency): array
	{
		return ['till' => $till, 'receipts' => 0, 'currency' => $currency, 'payments' => [], 'expected' => null, 'counted' => null, 'difference' => null, 'cash_difference_postings' => []];
	}
}
