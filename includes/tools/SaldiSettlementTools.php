<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiSettlementTools.php --- ai/mcp-assistant --- 2026.10.04
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
// Read-only exact settlement suggestions, bounded to twelve candidates per party.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Implement exact pairs, invoice references and subset sums.

declare(strict_types=1);

require_once __DIR__ . '/../automation/kinds/SaldiSettlementKind.php';

final class SaldiSettlementTools
{
	/**
	 * @return array{
	 *   data:array{groups:list<array{party:array{party_type:string,account:string,name:string,party_id:int},item_ids:list<int>,amounts:list<string>,total:string,currency:string,reason:string}>},
	 *   citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int
	 * }
	 */
	public static function suggest(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		$type = ifset($args, 'party_type');
		$account = ifset($args, 'party_account');
		$limit = ifset($args, 'limit', 25);
		if (!in_array($type, ['debtor', 'creditor'], true) || !is_int($limit) || $limit < 1 || $limit > 100
			|| ($account !== null && (!is_string($account) || !preg_match('/^[0-9]{1,15}$/D', $account)))) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid settlement query');
		}
		SaldiPermissionService::require($ctx, $db, $type === 'creditor' ? 'creditors.read' : 'debtors.read');
		$year = $db->one("SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA' AND kodenr = ?", [$ctx->fiscalYear]);
		if (!$year) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Fiscal year unavailable');
		}
		$params = [$type === 'creditor' ? 'K' : 'D', SaldiToolRuntime::year($year)['end']];
		$where = " WHERE a.art = ? AND COALESCE(a.lukket, '') <> 'on' AND COALESCE(o.udlignet, '') <> '1' AND o.transdate <= ? AND o.amount <> 0";
		if ($account !== null) {
			$where .= ' AND a.kontonr = ?';
			$params[] = $account;
		}
		$currency = "CASE WHEN COALESCE(o.valuta, '') IN ('', '-') THEN ? ELSE o.valuta END";
		$rows = $db->all('SELECT * FROM (SELECT o.id, o.konto_id, o.faktnr, o.amount, o.valuta, a.kontonr, a.firmanavn, ROW_NUMBER() OVER (PARTITION BY o.konto_id, ' . $currency . ' ORDER BY o.transdate, o.id) AS candidate FROM openpost o JOIN adresser a ON a.id = o.konto_id' . $where . ') candidates WHERE candidate <= 13 ORDER BY konto_id, id LIMIT 12001', array_merge([$ctx->baseCurrency], $params));
		$more = count($rows) > 12000;
		$buckets = [];
		foreach (array_slice($rows, 0, 12000) as $row) {
			if ((int)$row['candidate'] > 12) {
				$more = true;
				continue;
			}
			$buckets[$row['konto_id'] . ':' . SaldiToolRuntime::currency($ctx, $row['valuta'])][] = $row;
		}
		$groups = $citations = [];
		foreach ($buckets as $rows) {
			$sets = [];
			$count = count($rows);
			for ($a = 0; $a < $count; $a++) {
				for ($b = $a + 1; $b < $count; $b++) {
					if (self::balanced([$rows[$a], $rows[$b]])) {
						$invoice = trim((string)$rows[$a]['faktnr']) !== '' && $rows[$a]['faktnr'] === $rows[$b]['faktnr'];
						$sets[] = ['indices' => [$a, $b], 'reason' => $invoice ? 'invoice_number' : 'exact_pair'];
					}
				}
			}
			for ($payment = 0; $payment < $count; $payment++) {
				$candidates = [];
				$sign = SaldiSettlementKind::milli((string)$rows[$payment]['amount']) < 0;
				foreach ($rows as $index => $row) {
					if ((SaldiSettlementKind::milli((string)$row['amount']) < 0) !== $sign) {
						$candidates[] = $index;
					}
				}
				for ($a = 0; $a < count($candidates); $a++) {
					for ($b = $a + 1; $b < count($candidates); $b++) {
						$indices = [$payment, $candidates[$a], $candidates[$b]];
						if (self::balanced(array_map(static fn(int $i): array => $rows[$i], $indices))) {
							$sets[] = ['indices' => $indices, 'reason' => 'exact_subset'];
						}
						for ($c = $b + 1; $c < count($candidates); $c++) {
							$indices3 = array_merge($indices, [$candidates[$c]]);
							if (self::balanced(array_map(static fn(int $i): array => $rows[$i], $indices3))) {
								$sets[] = ['indices' => $indices3, 'reason' => 'exact_subset'];
							}
						}
					}
				}
			}
			usort($sets, static fn(array $a, array $b): int => ($a['reason'] !== 'invoice_number') <=> ($b['reason'] !== 'invoice_number'));
			foreach ($sets as $set) {
				if (count($groups) === $limit) {
					$more = true;
					break 2;
				}
				$selected = array_map(static fn(int $i): array => $rows[$i], $set['indices']);
				$first = $selected[0];
				$party = ['party_type' => $type, 'account' => (string)$first['kontonr'], 'name' => SaldiToolRuntime::text($first['firmanavn']), 'party_id' => (int)$first['konto_id']];
				$groups[] = ['party' => $party, 'item_ids' => array_map(static fn(array $row): int => (int)$row['id'], $selected),
					'amounts' => array_map(static fn(array $row): string => SaldiToolRuntime::money($row['amount']), $selected), 'total' => '0.00',
					'currency' => SaldiToolRuntime::currency($ctx, $first['valuta']), 'reason' => $set['reason']];
				$citations[$party['party_id']] = SaldiToolRuntime::citation('party', $party['party_id'], $party['name'], $type);
			}
		}
		return SaldiToolRuntime::result(['groups' => $groups], array_values($citations), $more, count($groups));
	}
	/** A set must balance both stored thousandths and the displayed øre. */
	private static function balanced(array $rows): bool
	{
		$milli = $cents = 0;
		foreach ($rows as $row) {
			$milli += SaldiSettlementKind::milli((string)$row['amount']);
			$cents += SaldiSettlementKind::cents((string)$row['amount']);
		}
		return $milli === 0 && $cents === 0;
	}
}
