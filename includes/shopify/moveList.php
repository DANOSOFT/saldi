<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/moveList.php --- ver 5.1.0 --- 2026.11.13
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
// The move list (flytteliste, WP10-2, K17 and K55): per day, the web-order lines drawn from
// another lager than the one the order ships from, so the stores know what to send where. It is
// read from what the import wrote (shopify_order_links.flags allocation_v1), so a split set's
// components are on it one row per draw, and a move that was abandoned in Shopify still is.
//
// 20261113 CL/MJ SD-760: created (WP10-2).

require_once __DIR__ . '/db.php';

/**
 * The day's web-order lines drawn from a lager other than the order's ship_lager, ordered by
 * order and lager. A cancelled order, one without a ship lager, and one imported before
 * allocation_v1 existed are not on it.
 *
 * @param string $day Y-m-d, the order date (ordrer.ordredate)
 * @param int|null $shopId only this shop's orders
 * @param int|null $shipLager only the orders shipping from this lager
 * @return list<array{ordre_id: int, ordrenr: string, order_name: string, saet: string|null, varenr: string, beskrivelse: string,
 *   antal: float, fra_lager: int, fra_navn: string, til_lager: int, til_navn: string}>
 * @throws InvalidArgumentException when $day is not a date
 */
function shopify_load_move_list(string $day, ?int $shopId, ?int $shipLager): array {
	$date = DateTimeImmutable::createFromFormat('!Y-m-d', $day);
	if ($date === false || $date->format('Y-m-d') !== $day) {
		throw new InvalidArgumentException("not a date: $day");
	}
	$links = shopify_db_rows(
		"SELECT l.ship_lager, l.order_name, l.flags -> 'allocation_v1' AS alloc, o.id AS ordre_id, o.ordrenr
		FROM shopify_order_links l JOIN ordrer o ON o.id = l.ordre_id
		WHERE l.direction = 'web' AND l.cancelled_at IS NULL AND l.ship_lager IS NOT NULL AND l.flags -> 'allocation_v1' IS NOT NULL
			AND o.ordredate = $1::date AND ($2::int IS NULL OR l.shop_id = $2) AND ($3::int IS NULL OR l.ship_lager = $3)
		ORDER BY o.ordrenr, o.id",
		array($day, $shopId, $shipLager)
	);
	$draws = array();
	$lineIds = array();
	foreach ($links as $link) {
		foreach ((array)(json_decode((string)$link['alloc'], true)['lines'] ?? array()) as $line) {
			$setLine = null;
			foreach ((array)($line['rows'] ?? array()) as $row) {
				if (!empty($row['set_line'])) {
					$setLine = (int)$row['ordrelinje_id'];
				}
			}
			foreach ((array)($line['rows'] ?? array()) as $row) {
				if (empty($row['set_line']) && (int)$row['lager'] !== (int)$link['ship_lager']) {
					$draws[] = array('link' => $link, 'row' => $row, 'set_line' => !empty($line['set']) ? $setLine : null);
					$lineIds[] = (int)$row['ordrelinje_id'];
					if ($setLine !== null) {
						$lineIds[] = $setLine;
					}
				}
			}
		}
	}
	if (!$draws) {
		return array();
	}
	$lines = array();
	foreach (shopify_db_rows('SELECT id, varenr, beskrivelse FROM ordrelinjer WHERE id = ANY($1::int[])', array('{' . implode(',', array_unique($lineIds)) . '}')) as $row) {
		$lines[(int)$row['id']] = $row;
	}
	$names = shopify_move_list_lager_names();
	$list = array();
	foreach ($draws as $draw) {
		$line = $lines[(int)$draw['row']['ordrelinje_id']] ?? array();
		$from = (int)$draw['row']['lager'];
		$to = (int)$draw['link']['ship_lager'];
		$list[] = array(
			'ordre_id' => (int)$draw['link']['ordre_id'],
			'ordrenr' => (string)$draw['link']['ordrenr'],
			'order_name' => (string)$draw['link']['order_name'],
			'saet' => $draw['set_line'] !== null ? (string)($lines[$draw['set_line']]['varenr'] ?? '') : null,
			'varenr' => (string)($line['varenr'] ?? ''),
			'beskrivelse' => (string)($line['beskrivelse'] ?? ''),
			'antal' => (int)$draw['row']['quantity_milli'] / 1000,
			'fra_lager' => $from,
			'fra_navn' => $names[$from] ?? "Lager $from",
			'til_lager' => $to,
			'til_navn' => $names[$to] ?? "Lager $to",
		);
	}
	usort($list, static fn(array $a, array $b): int => array($a['ordrenr'], $a['ordre_id'], $a['fra_lager']) <=> array($b['ordrenr'], $b['ordre_id'], $b['fra_lager']));
	return $list;
}

/**
 * Each lager's name from its newest LG row (rows kept from earlier years are not listed twice).
 *
 * @return array<int, string>
 */
function shopify_move_list_lager_names(): array {
	$names = array();
	foreach (shopify_db_rows("SELECT DISTINCT ON (kodenr) kodenr, beskrivelse FROM grupper WHERE art = 'LG' ORDER BY kodenr, id DESC") as $row) {
		if (trim((string)$row['beskrivelse']) !== '') {
			$names[(int)$row['kodenr']] = trim((string)$row['beskrivelse']);
		}
	}
	return $names;
}
