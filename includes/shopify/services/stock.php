<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/stock.php --- ver 5.1.0 --- 2026.11.02
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
// Move barriers (WP06, amendment 3; signatures in doc/shopify/interface-sheet.md): a stock push
// skips an item while its web order's fulfilment moves are pending. WP09 opens the barriers in the
// order's creation transaction, WP10 releases them after the moves, and a barrier is abandoned after
// 30 minutes or 3 failed move attempts, so the item is no longer skipped. Failed moves are counted on
// WP10's job. Released and abandoned items are marked dirty, so the next push publishes them.
//
// 20261102 CL/SZ SD-755: created (WP06-1).

require_once __DIR__ . '/allocation.php';
require_once __DIR__ . '/../db.php';

const SHOPIFY_BARRIER_MINUTES = 30;
/** WP10 calls shopify_barrier_expire() with the order id when a move has failed this many times. */
const SHOPIFY_BARRIER_MOVE_ATTEMPTS = 3;

/**
 * Opens a barrier per item of the order, expiring in SHOPIFY_BARRIER_MINUTES. Runs in the caller's
 * transaction: it never begins or commits one and never calls Shopify, so the rows commit or roll
 * back with the order. Idempotent per (shop, order, item): a resume after a crash can call it again,
 * and an item that already has a barrier for this order, in any state, gets no new one.
 *
 * @param int $ordreId ordrer.id
 * @param list<int> $vareIds the line items, the set components and the set itself
 * @return int how many barriers were opened
 * @throws InvalidArgumentException on an id that is no positive integer
 */
function shopify_barrier_open(int $shopId, int $ordreId, array $vareIds): int {
	shopify_allocation_lager($shopId, 'barrier shop id');
	shopify_allocation_lager($ordreId, 'barrier order id');
	$ids = shopify_barrier_ids($vareIds);
	if ($ids === array()) {
		return 0;
	}
	// held to the caller's commit, so a second attempt running at once sees this one's rows
	shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_barrier:$shopId:$ordreId"));
	return count(shopify_db_rows(
		"INSERT INTO shopify_move_barriers (shop_id, vare_id, ordre_id, expires_at)
		 SELECT $1::int, v, $2::int, now() + make_interval(mins => $4::int)
		 FROM unnest($3::int[]) AS v
		 WHERE NOT EXISTS (SELECT 1 FROM shopify_move_barriers b WHERE b.shop_id = $1 AND b.ordre_id = $2 AND b.vare_id = v)
		 RETURNING id",
		array($shopId, $ordreId, shopify_barrier_int_array($ids), SHOPIFY_BARRIER_MINUTES)
	));
}

/**
 * The order's moves are done: its open barriers become released and their items dirty. WP10 calls
 * this; WP09 never does.
 *
 * @param int $ordreId ordrer.id
 * @return int how many barriers were released
 */
function shopify_barrier_release(int $shopId, int $ordreId): int {
	return count(shopify_barrier_close('released', 'shop_id = $1 AND ordre_id = $2', array($shopId, $ordreId)));
}

/**
 * Abandons open barriers and marks their items dirty, so the stock push proceeds. With an order id:
 * all of that order's, for WP10 after the last failed move attempt. Without one: the sweep of the
 * shop's barriers past expires_at, which L10b schedules. L10b raises one alarm per order from what
 * this returns; these barriers are only ever returned once.
 *
 * @param int|null $ordreId ordrer.id
 * @return list<array{shop_id: int, ordre_id: int, vare_ids: list<int>}> what was just abandoned, one entry per order, sorted
 */
function shopify_barrier_expire(int $shopId, ?int $ordreId = null): array {
	$closed = $ordreId === null
		? shopify_barrier_close('abandoned', 'shop_id = $1 AND expires_at <= now()', array($shopId))
		: shopify_barrier_close('abandoned', 'shop_id = $1 AND ordre_id = $2', array($shopId, $ordreId));
	$orders = array();
	foreach ($closed as $row) {
		$orders[$row['ordre_id']]['shop_id'] = $row['shop_id'];
		$orders[$row['ordre_id']]['ordre_id'] = $row['ordre_id'];
		$orders[$row['ordre_id']]['vare_ids'][] = $row['vare_id'];
	}
	return array_values($orders);
}

/**
 * Which of these items the shop's stock push skips. A barrier past expires_at no longer defers, also
 * before the sweep has run.
 *
 * @param list<int> $vareIds
 * @return list<int> the deferred items, sorted
 * @throws InvalidArgumentException on an id that is no positive integer
 */
function shopify_barrier_deferred(int $shopId, array $vareIds): array {
	$ids = shopify_barrier_ids($vareIds);
	if ($ids === array()) {
		return array();
	}
	$rows = shopify_db_rows(
		"SELECT DISTINCT vare_id FROM shopify_move_barriers
		 WHERE shop_id = $1 AND state = 'open' AND expires_at > now() AND vare_id = ANY($2::int[])
		 ORDER BY vare_id",
		array($shopId, shopify_barrier_int_array($ids))
	);
	return array_map('intval', array_column($rows, 'vare_id'));
}

/**
 * Moves the matching open barriers to $state and marks their items dirty in one statement. Dirty rows
 * are written as the stock triggers do: the item and every set above it, not until a shop has
 * stock_capture_enabled.
 *
 * @return list<array{shop_id: int, ordre_id: int, vare_id: int}>
 */
function shopify_barrier_close(string $state, string $where, array $params): array {
	$params[] = $state;
	$stateParam = '$' . count($params);
	$rows = shopify_db_rows(
		"WITH RECURSIVE closed AS (
			UPDATE shopify_move_barriers SET state = $stateParam WHERE state = 'open' AND $where
			RETURNING shop_id, ordre_id, vare_id
		), up(id, depth) AS (
			SELECT DISTINCT vare_id, 0 FROM closed
			UNION
			SELECT s.indgaar_i, up.depth + 1 FROM styklister s JOIN up ON s.vare_id = up.id
			WHERE up.depth < 10 AND s.indgaar_i IS NOT NULL
		), dirty AS (
			INSERT INTO shopify_stock_dirty (vare_id)
			SELECT DISTINCT id FROM up WHERE EXISTS (SELECT 1 FROM shopify_shops WHERE stock_capture_enabled)
		)
		SELECT shop_id, ordre_id, vare_id FROM closed ORDER BY ordre_id, vare_id",
		$params
	);
	return array_map(fn(array $row): array => array('shop_id' => (int)$row['shop_id'], 'ordre_id' => (int)$row['ordre_id'], 'vare_id' => (int)$row['vare_id']), $rows);
}

/**
 * @param list<int> $vareIds
 * @return list<int> the ids once each
 */
function shopify_barrier_ids(array $vareIds): array {
	return array_values(array_unique(array_map(fn($id): int => shopify_allocation_lager($id, 'barrier item id'), $vareIds)));
}

/** @param list<int> $ids */
function shopify_barrier_int_array(array $ids): string {
	return '{' . implode(',', $ids) . '}';
}
