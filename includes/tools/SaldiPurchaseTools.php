<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiPurchaseTools.php --- ai/mcp-assistant --- 2026.10.05
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
// Read-only purchase invoice matching and exact sales-velocity reorder suggestions.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Implement purchase matching and sales-based replenishment.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/../std_func.php';
require_once __DIR__ . '/SaldiToolRuntime.php';
require_once __DIR__ . '/../automation/SaldiSettings.php';

final class SaldiPurchaseTools
{
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function match(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'purchases.read');
		$document = null;
		$citations = [];
		if (isset($args['document_ref'])) {
			require_once __DIR__ . '/SaldiDocumentTools.php';
			$read = SaldiDocumentTools::getDocument($ctx, $db, ['ref' => $args['document_ref']]);
			$document = $read['data'];
			$citations = $read['citations'];
			if ($document['source'] !== 'pool') {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected a pool document');
			}
		}
		$supplier = ifset($args, 'supplier_account', $document['vendor']['supplier_account'] ?? null);
		if ($supplier !== null && (!is_string($supplier) || !preg_match('/^[0-9]{1,15}$/D', $supplier))) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid supplier account');
		}
		if ($document !== null && $supplier !== null && isset($document['vendor']['supplier_account']) && $supplier !== $document['vendor']['supplier_account']) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Supplier differs from the document');
		}
		$invoice = ifset($args, 'invoice_number', $document['invoice_number'] ?? '');
		$reference = ifset($args, 'order_number', '');
		if (!is_string($invoice) || mb_strlen($invoice) > 30 || !is_string($reference) || mb_strlen($reference) > 30) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid invoice or order reference');
		}
		$inputLines = ifset($args, 'lines', []);
		if (!is_array($inputLines) || !array_is_list($inputLines) || count($inputLines) > 100) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected at most 100 comparison lines');
		}
		foreach ($inputLines as $line) {
			if (!is_array($line)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid comparison line');
			}
			foreach (['quantity', 'unit_price'] as $field) {
				if (isset($line[$field]) && (!is_string($line[$field]) || !preg_match('/^[0-9]{1,12}(\.[0-9]{1,3})?$/D', $line[$field]))) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid comparison decimal');
				}
			}
			foreach (['item_no', 'description'] as $field) {
				if (isset($line[$field]) && (!is_string($line[$field]) || mb_strlen($line[$field]) > 255)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid comparison text');
				}
			}
		}
		$sql = "SELECT * FROM ordrer WHERE art = 'KO' AND status IN (1, 2)";
		$params = [];
		if ($supplier !== null) {
			$sql .= ' AND kontonr = ?';
			$params[] = $supplier;
		}
		// Put document references first as well, even when their order is older than the fallback.
		$references = [$reference];
		if ($reference === '' && $document !== null) {
			preg_match_all('/[\pL\pN][\pL\pN._\/-]{0,99}/u', $document['description'], $tokens);
			$references = array_slice(array_values(array_unique($tokens[0])), 0, 100) ?: [''];
		}
		$holders = implode(',', array_fill(0, count($references), '?'));
		$sql .= ' ORDER BY CASE WHEN ' . SaldiToolRuntime::cast($db, 'ordrenr') . ' IN (' . $holders . ') OR kundeordnr IN (' . $holders . ') THEN 0 ELSE 1 END, id DESC LIMIT 101';
		$candidates = $db->all($sql, array_merge($params, $references, $references));
		$more = count($candidates) > 100;
		$candidates = array_slice($candidates, 0, 100);
		$orders = [];
		foreach ($candidates as $order) {
			$id = (int)$order['id'];
			$currency = SaldiToolRuntime::currency($ctx, $order['valuta']);
			$pricesComparable = $document === null || $document['currency'] === $currency;
			$more = $more || !$pricesComparable;
			$source = $db->all('SELECT l.*, COALESCE(b.received, 0) AS received, COALESCE(l.antal, 0) - COALESCE(b.received, 0) AS remaining FROM ordrelinjer l LEFT JOIN (SELECT linje_id, SUM(antal) AS received FROM batch_kob GROUP BY linje_id) b ON b.linje_id = l.id WHERE l.ordre_id = ? ORDER BY l.posnr, l.id LIMIT 201', [$id]);
			$more = $more || count($source) > 200;
			$source = array_slice($source, 0, 200);
			$used = [];
			$rows = [];
			$differences = [];
			$matched = 0;
			foreach ($source as $line) {
				$found = null;
				foreach ($inputLines as $index => $input) {
					if (isset($used[$index])) {
						continue;
					}
					$itemNo = trim((string)ifset($input, 'item_no', ''));
					$description = trim((string)ifset($input, 'description', ''));
					$hit = $itemNo !== '' ? (self::normal($itemNo) === self::normal((string)$line['varenr']) || self::normal($itemNo) === self::normal((string)$line['lev_varenr']))
						: ($description !== '' && self::normal($description) === self::normal((string)$line['beskrivelse']));
					if ($hit) {
						$found = $input;
						$used[$index] = true;
						$matched++;
						break;
					}
				}
				$quantityDifference = null;
				$price = SaldiToolRuntime::money($line['pris']);
				$invoicePrice = $pricesComparable && isset($found['unit_price']) ? SaldiToolRuntime::money($found['unit_price']) : null;
				foreach (['quantity' => [(string)$line['remaining'], $found['quantity'] ?? null], 'unit_price' => [(string)($line['pris'] ?? '0'), $pricesComparable ? ($found['unit_price'] ?? null) : null]] as $field => [$ordered, $invoiced]) {
					if ($invoiced === null) {
						continue;
					}
					$difference = self::difference($db, $invoiced, $ordered);
					if ($field === 'quantity') {
						$quantityDifference = $difference;
					}
					if (preg_match('/[1-9]/', $difference)) {
						$differences[] = ['line_id' => (int)$line['id'], 'field' => $field, 'order_value' => $field === 'unit_price' ? $price : self::quantity($ordered), 'invoice_value' => $field === 'unit_price' ? $invoicePrice : self::quantity($invoiced), 'difference' => $field === 'unit_price' ? SaldiToolRuntime::money($difference) : $difference, 'currency' => $currency];
					}
				}
				$rows[] = ['line_id' => (int)$line['id'], 'item_no' => SaldiToolRuntime::text($line['varenr']), 'description' => SaldiToolRuntime::text($line['beskrivelse']), 'currency' => $currency, 'ordered' => self::quantity((string)($line['antal'] ?? '0')), 'received' => self::quantity((string)$line['received']), 'remaining' => self::quantity((string)$line['remaining']), 'order_price' => $price, 'invoice_price' => $invoicePrice, 'quantity_difference' => $quantityDifference];
			}
			foreach ($inputLines as $index => $input) {
				if (!isset($used[$index])) {
					foreach (['quantity', 'unit_price'] as $field) {
						if (isset($input[$field]) && ($field !== 'unit_price' || $pricesComparable)) {
							$value = $field === 'unit_price' ? SaldiToolRuntime::money($input[$field]) : self::quantity($input[$field]);
							$differences[] = ['line_id' => null, 'field' => $field, 'order_value' => '0.000', 'invoice_value' => $value, 'difference' => $value, 'currency' => $currency];
						}
					}
				}
			}
			$totalMatch = false;
			if (!$inputLines && $document !== null && $document['amount'] !== null && $document['currency'] === $currency) {
				$total = (string)$db->value('SELECT COALESCE(sum, 0) + COALESCE(moms, 0) FROM ordrer WHERE id = ?', [$id]);
				$diff = self::difference($db, $document['amount'], $total);
				$totalMatch = SaldiToolRuntime::money($diff) === '0.00';
				if (!$totalMatch) {
					$differences[] = ['line_id' => null, 'field' => 'total', 'order_value' => SaldiToolRuntime::money($total), 'invoice_value' => $document['amount'], 'difference' => SaldiToolRuntime::money($diff), 'currency' => $currency];
				}
			}
			$exactReference = $reference !== '' && ($reference === (string)$order['ordrenr'] || $reference === (string)$order['kundeordnr']);
			$documentReference = false;
			if ($reference === '' && $document !== null) {
				foreach ([(string)$order['ordrenr'], (string)$order['kundeordnr']] as $number) {
					if ($number !== '' && preg_match('/(?<![\pL\pN])' . preg_quote($number, '/') . '(?![\pL\pN])/u', $document['description'])) {
						$documentReference = true;
					}
				}
			}
			$score = ($exactReference || $documentReference ? 0.5 : 0) + ($inputLines ? 0.4 * $matched / count($inputLines) : ($totalMatch ? 0.4 : 0)) + ($invoice !== '' && $invoice === (string)$order['fakturanr'] ? 0.1 : 0);
			$orders[] = ['order_id' => $id, 'order_number' => (string)$order['ordrenr'], 'score' => $score, 'lines' => $rows, 'differences' => $differences];
		}
		usort($orders, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: ($b['order_id'] <=> $a['order_id']));
		$more = $more || count($orders) > 25;
		$orders = array_slice($orders, 0, 25);
		// MCP carries both structured data and its escaped JSON text; keep room for both.
		while (strlen(SaldiToolRuntime::canonical(['orders' => $orders, 'citations' => $citations])) > 64 * 1024 && $orders) {
			if (count($orders) > 1) {
				array_pop($orders);
			} elseif ($orders[0]['lines']) {
				$removed = array_pop($orders[0]['lines']);
				$orders[0]['differences'] = array_values(array_filter($orders[0]['differences'], static fn(array $difference): bool => $difference['line_id'] !== $removed['line_id']));
			} elseif ($orders[0]['differences']) {
				array_pop($orders[0]['differences']);
			} else {
				break;
			}
			$more = true;
		}
		foreach ($orders as $order) {
			$citations[] = SaldiToolRuntime::citation('order', $order['order_id'], 'Indkøbsordre ' . $order['order_number'], 'purchase_order');
		}
		return SaldiToolRuntime::result(['orders' => $orders], $citations, $more, count($orders));
	}

	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function reorders(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'products.read');
		$data = self::reorderRows($ctx, $db, $args, SaldiToolRuntime::today(), 100);
		$citations = array_map(static fn(array $row): array => SaldiToolRuntime::citation('product', $row['product_id'], $row['item_no'], 'product'), $data['rows']);
		return SaldiToolRuntime::result($data, $citations, $data['total'] > count($data['rows']));
	}

	/** Shared SQL calculation, anchored to the scheduled date for repeatable routine runs.
	 * @return array{rows:list<array{product_id:int,item_no:string,text:string,per_day:string,on_hand:string,on_order:string,cover_days:?string,suggest_qty:string,supplier:?array{account:string,name:string}}>,total:int}
	 */
	public static function reorderRows(SaldiTenantContext $ctx, SaldiDb $db, array $args, string $asOf, int $limit = 20): array
	{
		SaldiPermissionService::require($ctx, $db, 'products.read');
		SaldiToolRuntime::date($asOf);
		$days = ifset($args, 'days', 90);
		$setting = SaldiSettings::get($db, 'assistant', 'lead_days', '14');
		$lead = ifset($args, 'lead_days', preg_match('/^[0-9]{1,9}$/D', $setting) ? (int)$setting : 14);
		if (!is_int($days) || $days < 30 || $days > 365 || !is_int($lead) || $lead < 0 || $lead > 1000000000 || $limit < 1 || $limit > 100) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid reorder window');
		}
		$from = (new DateTimeImmutable($asOf))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
		$base = "SELECT v.id, v.varenr, v.beskrivelse, COALESCE(v.beholdning, 0) AS on_hand, COALESCE(v.min_lager, 0) AS minimum, COALESCE(v.colli, 0) AS colli, "
			. "COALESCE(s.sold, 0) / CAST(? AS DECIMAL(30,9)) AS velocity, COALESCE(p.on_order, 0) AS on_order FROM varer v "
			. "LEFT JOIN (SELECT b.vare_id, SUM(b.antal) AS sold FROM batch_salg b JOIN ordrer o ON o.id = b.ordre_id WHERE b.ordre_id <> 0 AND o.status >= 3 AND b.salgsdate >= ? AND b.salgsdate <= ? GROUP BY b.vare_id) s ON s.vare_id = v.id "
			. "LEFT JOIN (SELECT l.vare_id, SUM(GREATEST(COALESCE(l.antal, 0) - COALESCE(b.received, 0), 0)) AS on_order FROM ordrelinjer l JOIN ordrer o ON o.id = l.ordre_id LEFT JOIN (SELECT linje_id, SUM(antal) AS received FROM batch_kob GROUP BY linje_id) b ON b.linje_id = l.id WHERE o.art = 'KO' AND o.status IN (1, 2) GROUP BY l.vare_id) p ON p.vare_id = v.id "
			. "WHERE COALESCE(v.lukket, '') <> 'on' AND EXISTS (SELECT 1 FROM grupper g WHERE g.art = 'VG' AND g.kodenr = v.gruppe AND g.fiscal_year = ? AND g.box8 = 'on')";
		$cover = 'SELECT base.*, CASE WHEN velocity > 0 THEN (on_hand + on_order) / velocity ELSE NULL END AS cover_days, GREATEST(minimum, velocity * CAST(? AS DECIMAL(30,9))) - on_hand - on_order AS needed FROM (' . $base . ') base';
		$eligible = 'SELECT cover.*, CASE WHEN needed > 0 AND colli > 0 THEN CEIL(needed / colli) * colli ELSE GREATEST(needed, 0) END AS suggest_qty FROM (' . $cover . ') cover WHERE needed > 0 AND (cover_days < ? OR on_hand <= minimum)';
		$params = [$lead + 30, $days, $from, $asOf, $ctx->fiscalYear, $lead + 7];
		$total = (int)$db->value('SELECT COUNT(*) FROM (' . $eligible . ') eligible', $params);
		$rows = $db->all('SELECT id, varenr, beskrivelse, ROUND(velocity, 3) AS per_day, ROUND(on_hand, 3) AS on_hand, ROUND(on_order, 3) AS on_order, ROUND(cover_days, 3) AS cover_days, ROUND(suggest_qty, 3) AS suggest_qty FROM (' . $eligible . ') eligible ORDER BY CASE WHEN cover_days IS NULL THEN 1 ELSE 0 END, cover_days, suggest_qty DESC, id LIMIT ?', array_merge($params, [$limit]));
		$result = [];
		foreach ($rows as $row) {
			$supplier = $db->one("SELECT a.kontonr AS account, a.firmanavn AS name FROM vare_lev vl JOIN adresser a ON a.id = vl.lev_id AND a.art = 'K' AND COALESCE(a.lukket, '') <> 'on' WHERE vl.vare_id = ? AND vl.posnr = 1 ORDER BY vl.id LIMIT 1", [$row['id']]);
			$result[] = ['product_id' => (int)$row['id'], 'item_no' => SaldiToolRuntime::text($row['varenr']), 'text' => SaldiToolRuntime::text($row['beskrivelse']), 'per_day' => self::quantity((string)$row['per_day']), 'on_hand' => self::quantity((string)$row['on_hand']), 'on_order' => self::quantity((string)$row['on_order']), 'cover_days' => $row['cover_days'] === null ? null : self::quantity((string)$row['cover_days']), 'suggest_qty' => self::quantity((string)$row['suggest_qty']), 'supplier' => $supplier === null ? null : ['account' => (string)$supplier['account'], 'name' => SaldiToolRuntime::text($supplier['name'])]];
		}
		return ['rows' => $result, 'total' => $total];
	}

	/** @return string Normalized literal comparison, never a SQL wildcard. */
	private static function normal(string $text): string
	{
		return mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
	}
	/** @return string Decimal quantity at the stored three-place precision. */
	private static function quantity(string $value): string
	{
		[$whole, $fraction] = array_pad(explode('.', $value), 2, '');
		return $whole . '.' . str_pad(substr($fraction, 0, 3), 3, '0');
	}
	/** @return string Exact signed difference at stored precision. */
	private static function difference(SaldiDb $db, string $invoice, string $order): string
	{
		return self::quantity((string)$db->value('SELECT CAST(? AS DECIMAL(30,3)) - CAST(? AS DECIMAL(30,3))', [$invoice, $order]));
	}
}
