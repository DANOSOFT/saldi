<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/allocation.php --- ver 5.1.0 --- 2026.10.28
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
// Allocation of a web order (WP07, K15, K16, K55): which lager every order line and set
// component is drawn from. Pure: no database, no clock; quantities in integer thousandths.
//
// Rules, in this order:
// - A line is drawn from the lager Shopify assigned it to, when that lager is sellable
//   (shopify_lager rolle 'saelgbar'), then from the other sellable lagre by priority (K16).
//   An assignment to a display or ignored lager, or to a location no lager maps to, counts as
//   no assignment and is reported as an alarm; Saldi never draws from a display lager.
// - What no sellable lager has left is a shortage: it is drawn from the assigned lager, or the
//   first sellable one when there is no valid assignment, and reported. A set component short
//   everywhere goes on the lager the rest of the set is drawn from when that is one lager, so it
//   does not split the set by itself; when the rest comes from several lagre or none, it goes on
//   the assigned or first sellable lager.
// - A set is drawn whole from one lager when it can be: the assigned one, or without a valid
//   assignment the first sellable one by priority that has every component. Otherwise each
//   component is drawn by the line rule above, and a set drawn from more than one lager is a
//   split set: assembled at the assembly lager (Mårum) and tagged saldi-saet-delt (K55).
// - Lines are allocated in the order given and draw down one shared copy of the stock, so the
//   same item on two lines is never counted twice.
// - A set is one BOM level (HML has no sets inside sets nor variant-controlled components, P:492).
//
// 20261028 CL/SS SD-788: created (WP07-1).

require_once __DIR__ . '/../schema.php';

const SHOPIFY_TAG_SPLIT_SET = 'saldi-saet-delt';
/** shopify_lager roles, as SHOPIFY_LAGER_ROLES in config.php. config.php is not loaded here: it defines shopify_secret(), which changes the client's token lookup wherever this file is loaded. */
const SHOPIFY_ALLOCATION_ROLES = array('saelgbar', 'udstilling', 'ingen');

/**
 * Allocates an order's lines to lagre.
 *
 * @param list<array{key: string|int, vare_id: int, quantity_milli: int, assigned_location?: string|null,
 *   components?: list<array{bom_id: int, vare_id: int, quantity_milli: int}>}> $lines
 *   one entry per order line, keyed by a non-empty key (e.g. the line item GID); a set line lists its
 *   BOM occurrences (styklister rows) with the quantity per set, and its quantity_milli is the number
 *   of sets in thousandths; assigned_location is a Location GID or numeric id, matched against the
 *   location_gid of $locations (a lager without one is never an assignment target). The result is keyed by
 *   line key as a PHP array is (a numeric string key becomes an int key); each result's key field
 *   keeps the key as given
 * @param list<array{lager: int, rolle: string, prioritet?: int|null, location_gid?: string|null}> $locations
 *   every lager the shop maps to a location, once, with its role (shopify_lager) and the location GID
 * @param array<int, array<int, int>> $stockMilli vare_id => lager => stock in thousandths; missing or negative is 0
 * @param int $assemblyLager the lager split sets are assembled at (split_set_lager); never a display lager. Only
 *   checked when a set splits, so an order without one needs no assembly lager (0 when unset)
 * @return array{
 *   lines: array<string|int, array{key: string|int, vare_id: int, set: bool, split: bool, lager: int,
 *     segments: list<array{lager: int, quantity_milli: int, shortage_milli: int}>,
 *     lager_plan: list<array{bom_id: int, lager: int, quantity_milli: int}>, tags: list<string>}>,
 *   alarms: list<array{key: string|int, kind: string, location: string, lager: int|null}>,
 *   shortages: list<array{key: string|int, vare_id: int, bom_id: int|null, lager: int, quantity_milli: int}>,
 *   tags: list<string>
 * }
 *   lager is the line's lager: the one a plain line or a whole set is drawn from (the first
 *   segment for a plain line drawn from several), the assembly lager for a split set. segments
 *   are a plain line's draws, one per lager (shortage_milli of it is a shortage); lager_plan is a
 *   set's draws, one per BOM occurrence and lager, in BOM order: the lager plan the second S07
 *   change adds to opret_saet().
 * @throws InvalidArgumentException on a malformed line, location or quantity, or when no lager is sellable
 */
function shopify_allocate_order(array $lines, array $locations, array $stockMilli, int $assemblyLager): array {
	$sellable = array();
	$byGid = array();
	$roles = array();
	foreach ($locations as $loc) {
		$lager = shopify_allocation_lager($loc['lager'] ?? null, 'location lager');
		if (isset($roles[$lager])) {
			throw new InvalidArgumentException("lager $lager is listed twice in the locations");
		}
		$rolle = (string)($loc['rolle'] ?? '');
		if (!in_array($rolle, SHOPIFY_ALLOCATION_ROLES, true)) {
			throw new InvalidArgumentException("lager $lager has an unknown role '$rolle'");
		}
		if ($rolle === 'saelgbar') {
			$sellable[$lager] = shopify_allocation_int($loc['prioritet'] ?? null, "priority of sellable lager $lager");
		}
		if (isset($loc['location_gid']) && $loc['location_gid'] !== '') {
			$gid = shopify_allocation_location((string)$loc['location_gid']);
			if ($gid === null || isset($byGid[$gid])) {
				throw new InvalidArgumentException("location {$loc['location_gid']} of lager $lager is invalid or mapped twice");
			}
			$byGid[$gid] = array('lager' => $lager, 'rolle' => $rolle);
		}
		$roles[$lager] = $rolle;
	}
	if ($sellable === array()) {
		throw new InvalidArgumentException('no sellable lager: nothing can be allocated');
	}
	uksort($sellable, fn(int $a, int $b): int => array($sellable[$a], $a) <=> array($sellable[$b], $b));
	$priority = array_keys($sellable);

	$stock = array();
	foreach ($stockMilli as $vareId => $perLager) {
		$vare = shopify_allocation_lager($vareId, 'stock item id');
		if (!is_array($perLager)) {
			throw new InvalidArgumentException("stock of item $vare is not a list per lager");
		}
		foreach ($perLager as $lager => $milli) {
			$stock[$vare][shopify_allocation_lager($lager, "stock lager of item $vare")] = max(0, shopify_allocation_int($milli, "stock of item $vare at lager $lager"));
		}
	}

	$out = array('lines' => array(), 'alarms' => array(), 'shortages' => array(), 'tags' => array());
	foreach ($lines as $line) {
		$key = $line['key'] ?? null;
		if ((!is_string($key) || trim($key) === '') && !is_int($key)) {
			throw new InvalidArgumentException('every line needs a key');
		}
		if (isset($out['lines'][$key])) {
			throw new InvalidArgumentException("line key $key is used twice");
		}
		$vareId = shopify_allocation_lager($line['vare_id'] ?? null, "vare_id of line $key");
		$qty = shopify_allocation_int($line['quantity_milli'] ?? null, "quantity of line $key");
		if ($qty <= 0) {
			throw new InvalidArgumentException("line $key has no positive quantity");
		}

		$assigned = null;
		$gid = $line['assigned_location'] ?? null;
		if ($gid !== null && !is_string($gid) && !is_int($gid)) {
			throw new InvalidArgumentException("assigned_location of line $key is not a location id");
		}
		if ($gid !== null && trim((string)$gid) !== '') {
			$normal = shopify_allocation_location((string)$gid);
			$found = $byGid[$normal ?? ''] ?? null;
			if ($found !== null && $found['rolle'] === 'saelgbar') {
				$assigned = $found['lager'];
			} else {
				$kind = $found === null ? 'assignment_unmapped' : ($found['rolle'] === 'udstilling' ? 'assignment_display' : 'assignment_ignored');
				$out['alarms'][] = array('key' => $key, 'kind' => $kind, 'location' => $normal ?? (string)$gid, 'lager' => $found['lager'] ?? null);
			}
		}
		$order = $assigned === null ? $priority : array_merge(array($assigned), array_values(array_diff($priority, array($assigned))));

		$result = array('key' => $key, 'vare_id' => $vareId, 'set' => false, 'split' => false, 'lager' => 0,
			'segments' => array(), 'lager_plan' => array(), 'tags' => array());
		$components = $line['components'] ?? null;
		if ($components !== null && !is_array($components)) {
			throw new InvalidArgumentException("components of line $key are not a list");
		}
		if ($components === null) {
			$result['segments'] = shopify_allocation_draw($stock, $vareId, $qty, $order);
			$result['lager'] = $result['segments'][0]['lager'];
			foreach ($result['segments'] as $seg) {
				if ($seg['shortage_milli'] > 0) {
					$out['shortages'][] = array('key' => $key, 'vare_id' => $vareId, 'bom_id' => null, 'lager' => $seg['lager'], 'quantity_milli' => $seg['shortage_milli']);
				}
			}
		} else {
			$result['set'] = true;
			$needs = shopify_allocation_set_needs($components, $qty, $key);
			// what a lager must hold to take the set whole: the need, capped at what the sellable lagre
			// hold in total, so a component short everywhere does not split the set by itself
			$totals = array();
			foreach ($needs as $need) {
				$totals[$need['vare_id']] = ($totals[$need['vare_id']] ?? 0) + $need['quantity_milli'];
			}
			foreach ($totals as $item => $total) {
				$totals[$item] = min($total, array_sum(array_intersect_key($stock[$item] ?? array(), $sellable)));
			}
			$whole = null;
			foreach ($assigned === null ? $priority : array($assigned) as $lager) {
				if (shopify_allocation_has_all($stock, $totals, $lager)) {
					$whole = $lager;
					break;
				}
			}
			$from = $whole === null ? $order : array($whole);
			$taken = array();
			$drawnAt = array();
			foreach ($needs as $i => $need) {
				$taken[$i] = shopify_allocation_take($stock, $need['vare_id'], $need['quantity_milli'], $from);
				$drawnAt += array_fill_keys(array_keys($taken[$i]['take']), true);
			}
			// what is short joins the one lager the rest is drawn from, else the assigned or first lager
			$shortAt = count($drawnAt) === 1 ? (int)array_key_first($drawnAt) : $order[0];
			$lagre = array();
			foreach ($needs as $i => $need) {
				$draws = shopify_allocation_segments($taken[$i], $from, $shortAt);
				foreach ($draws as $seg) {
					$result['lager_plan'][] = array('bom_id' => $need['bom_id'], 'lager' => $seg['lager'], 'quantity_milli' => $seg['quantity_milli']);
					$lagre[$seg['lager']] = true;
					if ($seg['shortage_milli'] > 0) {
						$out['shortages'][] = array('key' => $key, 'vare_id' => $need['vare_id'], 'bom_id' => $need['bom_id'], 'lager' => $seg['lager'], 'quantity_milli' => $seg['shortage_milli']);
					}
				}
			}
			$result['split'] = count($lagre) > 1;
			if ($result['split'] && ($assemblyLager < 1 || ($roles[$assemblyLager] ?? '') === 'udstilling')) {
				throw new InvalidArgumentException("set line $key splits, but assembly lager $assemblyLager is not a lager split sets can be assembled at");
			}
			$result['lager'] = $result['split'] ? $assemblyLager : (int)array_key_first($lagre);
			if ($result['split']) {
				$result['tags'][] = SHOPIFY_TAG_SPLIT_SET;
				$out['tags'][SHOPIFY_TAG_SPLIT_SET] = SHOPIFY_TAG_SPLIT_SET;
			}
		}
		$out['lines'][$key] = $result;
	}
	$out['tags'] = array_values($out['tags']);
	return $out;
}

/**
 * Draws $qty of an item from the lagre in $order and puts what is left on the first lager in
 * $order as a shortage. One segment per lager, in $order. Draws $stock down.
 *
 * @param array<int, array<int, int>> $stock
 * @param list<int> $order
 * @return list<array{lager: int, quantity_milli: int, shortage_milli: int}>
 */
function shopify_allocation_draw(array &$stock, int $vareId, int $qty, array $order): array {
	return shopify_allocation_segments(shopify_allocation_take($stock, $vareId, $qty, $order), $order, $order[0]);
}

/**
 * Takes what each lager in $order has of an item, up to $qty, and draws $stock down.
 *
 * @return array{take: array<int, int>, left: int}
 */
function shopify_allocation_take(array &$stock, int $vareId, int $qty, array $order): array {
	$take = array();
	$left = $qty;
	foreach ($order as $lager) {
		$n = min($left, $stock[$vareId][$lager] ?? 0);
		if ($n > 0) {
			$take[$lager] = $n;
			$stock[$vareId][$lager] -= $n;
			$left -= $n;
		}
	}
	return array('take' => $take, 'left' => $left);
}

/**
 * One segment per lager in $order, with what is left as a shortage on $shortAt (a lager in
 * $order whenever anything is left).
 *
 * @param array{take: array<int, int>, left: int} $taken
 * @return list<array{lager: int, quantity_milli: int, shortage_milli: int}>
 */
function shopify_allocation_segments(array $taken, array $order, int $shortAt): array {
	$segments = array();
	foreach ($order as $lager) {
		$short = $lager === $shortAt ? $taken['left'] : 0;
		if (($taken['take'][$lager] ?? 0) + $short > 0) {
			$segments[] = array('lager' => $lager, 'quantity_milli' => ($taken['take'][$lager] ?? 0) + $short, 'shortage_milli' => $short);
		}
	}
	return $segments;
}

/**
 * What a set line needs per BOM occurrence: the quantity per set times the number of sets.
 *
 * @return list<array{bom_id: int, vare_id: int, quantity_milli: int}>
 */
function shopify_allocation_set_needs(array $components, int $setsMilli, $key): array {
	if ($components === array()) {
		throw new InvalidArgumentException("set line $key has no components");
	}
	$needs = array();
	$seen = array();
	foreach ($components as $c) {
		$bomId = shopify_allocation_int($c['bom_id'] ?? null, "bom_id in set line $key");
		if (isset($seen[$bomId])) {
			throw new InvalidArgumentException("set line $key lists BOM occurrence $bomId twice");
		}
		$seen[$bomId] = true;
		$per = shopify_allocation_int($c['quantity_milli'] ?? null, "quantity of BOM occurrence $bomId");
		if ($per <= 0) {
			throw new InvalidArgumentException("BOM occurrence $bomId of set line $key has no positive quantity");
		}
		if ($per > intdiv(PHP_INT_MAX, $setsMilli) || ($per * $setsMilli) % 1000 !== 0) {
			throw new InvalidArgumentException("BOM occurrence $bomId of set line $key does not need a whole number of thousandths");
		}
		$needs[] = array('bom_id' => $bomId, 'vare_id' => shopify_allocation_lager($c['vare_id'] ?? null, "vare_id of BOM occurrence $bomId"), 'quantity_milli' => intdiv($per * $setsMilli, 1000));
	}
	return $needs;
}

/**
 * Whether one lager holds every component a set line needs.
 *
 * @param array<int, int> $totals vare_id => quantity in thousandths, the same item on two occurrences counted together
 */
function shopify_allocation_has_all(array $stock, array $totals, int $lager): bool {
	foreach ($totals as $vareId => $qty) {
		if (($stock[$vareId][$lager] ?? 0) < $qty) {
			return false;
		}
	}
	return true;
}

/** A lager or item id: a positive integer. */
function shopify_allocation_lager($value, string $what): int {
	$id = shopify_allocation_int($value, $what);
	if ($id < 1) {
		throw new InvalidArgumentException("$what must be positive");
	}
	return $id;
}

/** A Location GID without any query suffix, from a GID or numeric id; null when it is neither. */
function shopify_allocation_location(string $id): ?string {
	try {
		return explode('?', shopify_gid($id, 'Location'), 2)[0];
	} catch (InvalidArgumentException $e) {
		return null;
	}
}

/** An integer input, also when it comes as an integer string from a database row (at most 18 digits, nothing around them). */
function shopify_allocation_int($value, string $what): int {
	if (is_int($value)) {
		return $value;
	}
	if (is_string($value) && preg_match('/^-?\d{1,18}\z/', $value)) {
		return (int)$value;
	}
	throw new InvalidArgumentException("$what must be an integer");
}
