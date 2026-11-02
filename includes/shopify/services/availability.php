<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/availability.php --- ver 5.1.0 --- 2026.11.02
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// What Saldi publishes per item and lager (WP06, K09, K12, K13, K55, K60). Pure: no database, no
// clock; stock in integer thousandths, published quantities in whole units.
//
// - A plain item publishes floor(max(0, beholdning)) per sellable lager, with nothing taken off for open orders.
// - A display lager is always sent with 0 and says whether the item stands there (K13). Role ingen
//   and the virtual lager are never sent and count for nothing (K60).
// - A set publishes the complete sets each sellable lager has by itself. The sets only the lagre
//   together can assemble go on the split-set lager, so the total is never more than the sellable
//   lagre can assemble (K55). The split-set lager is the first sellable one by priority when unset
//   (K55, Å28). The surplus is calculated for Shopify and never changes Saldi's stock.
// - A set is one BOM level (Å10). No stock is reserved across sets, single items or channels (Å26).
//
// 20261102 CL/SZ SD-755: created (WP06-1).

require_once __DIR__ . '/allocation.php';

/**
 * The quantity to publish for one item, per lager that is sent.
 *
 * @param array{vare_id: int} $item
 * @param list<array{lager: int, rolle: string, prioritet?: int|null, virtual?: bool}> $lagre every lager with
 *   its role, from shopify_availability_lagre(); a row with virtual true is left out
 * @param array<int, array<int, int>> $stock vare_id => lager => stock in thousandths; missing or negative is 0
 * @param list<array{bom_id: int, vare_id: int, quantity_milli: int}> $bom the set's BOM occurrences with the
 *   quantity per set; empty for a plain item
 * @param int|null $splitLager the split-set lager (split_set_lager). Only checked when a set has a surplus
 * @return array<int, array{available: int, udstillet: bool}> lager => what to send, sellable lagre by
 *   priority, then display lagre. udstillet is true on a display lager holding at least one whole item or set
 * @throws InvalidArgumentException on a malformed item, lager, BOM or stock value, or when a set's surplus
 *   would go on a split-set lager that isn't sellable
 */
function shopify_availability(array $item, array $lagre, array $stock, array $bom, ?int $splitLager): array {
	$vareId = shopify_allocation_lager($item['vare_id'] ?? null, 'item id');
	$sellable = array();
	$display = array();
	$seen = array();
	foreach ($lagre as $row) {
		$lager = shopify_allocation_lager($row['lager'] ?? null, 'lager');
		if (isset($seen[$lager])) {
			throw new InvalidArgumentException("lager $lager is listed twice");
		}
		$seen[$lager] = true;
		$rolle = (string)($row['rolle'] ?? '');
		if (!in_array($rolle, SHOPIFY_ALLOCATION_ROLES, true)) {
			throw new InvalidArgumentException("lager $lager has an unknown role '$rolle'");
		}
		if (!empty($row['virtual'])) {
			continue;
		}
		if ($rolle === 'saelgbar') {
			$sellable[$lager] = shopify_allocation_int($row['prioritet'] ?? null, "priority of sellable lager $lager");
		} elseif ($rolle === 'udstilling') {
			$display[] = $lager;
		}
	}
	uksort($sellable, fn(int $a, int $b): int => array($sellable[$a], $a) <=> array($sellable[$b], $b));
	$priority = array_keys($sellable);

	$needs = array();
	if ($bom !== array()) {
		foreach (shopify_allocation_set_needs($bom, 1000, $vareId) as $need) {
			$needs[$need['vare_id']] = ($needs[$need['vare_id']] ?? 0) + $need['quantity_milli'];
		}
	} else {
		$needs[$vareId] = 1000;
	}
	$whole = fn(int $lager): int => shopify_availability_total($stock, $needs, array($lager));

	$out = array();
	$complete = 0;
	foreach ($priority as $lager) {
		$out[$lager] = array('available' => $whole($lager), 'udstillet' => false);
		$complete += $out[$lager]['available'];
	}
	if ($bom !== array()) {
		$surplus = shopify_availability_total($stock, $needs, $priority) - $complete;
		if ($surplus > 0) {
			$target = $splitLager ?? ($priority[0] ?? 0);
			if (!isset($out[$target])) {
				throw new InvalidArgumentException("split-set lager $target of item $vareId is not sellable");
			}
			$out[$target]['available'] += $surplus;
		}
	}
	foreach ($display as $lager) {
		$out[$lager] = array('available' => 0, 'udstillet' => $whole($lager) > 0);
	}
	return $out;
}

/**
 * The lager rows for shopify_availability() from shopify_lager_config(), with the virtual lager marked.
 *
 * @param array{lagre: list<array<string, string|null>>, virtual_lager: int|null} $config
 * @return list<array<string, mixed>>
 */
function shopify_availability_lagre(array $config): array {
	$lagre = array();
	foreach ($config['lagre'] as $row) {
		$row['virtual'] = $config['virtual_lager'] !== null && (int)$row['lager'] === $config['virtual_lager'];
		$lagre[] = $row;
	}
	return $lagre;
}

/**
 * Whole items or sets the given lagre can make up together.
 *
 * @param array<int, int> $needs vare_id => thousandths per item or set
 * @param list<int> $lagre
 */
function shopify_availability_total(array $stock, array $needs, array $lagre): int {
	$total = null;
	foreach ($needs as $vareId => $need) {
		$sum = 0;
		foreach ($lagre as $lager) {
			$sum += shopify_availability_stock($stock, $vareId, $lager);
		}
		$count = intdiv($sum, $need);
		$total = $total === null ? $count : min($total, $count);
	}
	return (int)$total;
}

/** Stock in thousandths, also as an integer string from a database row; missing or negative is 0. */
function shopify_availability_stock(array $stock, int $vareId, int $lager): int {
	$perLager = $stock[$vareId] ?? array();
	if (!is_array($perLager)) {
		throw new InvalidArgumentException("stock of item $vareId is not a list per lager");
	}
	return max(0, shopify_allocation_int($perLager[$lager] ?? 0, "stock of item $vareId at lager $lager"));
}
