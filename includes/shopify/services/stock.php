<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/stock.php --- ver 5.1.0 --- 2026.11.04
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
// Also the warning when split_set_lager isn't sellable, so the fallback in shopify_split_set_lager() is seen.
//
// The stock push (WP06-2, K12, K18): the worker's stock producer drains shopify_stock_dirty into a
// dirty set per shop that captures stock and queues one stock.push job per shop. The job sends
// absolute available quantities, the ones that differ from what Shopify last acknowledged
// (shopify_stock_state), in inventorySetQuantities calls of at most 250. Each call is stored as the
// shop's pending mutation before it is sent and is resent unchanged, with the same @idempotent key,
// until Shopify answers it, so a shop has one unresolved mutation at a time and a crash or timeout
// never draws twice. The pending mutation and the dirty set live in the shop's queue state, not in a
// job's checkpoint, so every job of the shop sees them. Plain items on sellable lagre; sets and
// display lagre come with WP06-3. Saldi's stock is never changed from Shopify.
//
// 20261102 CL/SZ SD-755: created (WP06-1).
// 20261103 CL/SZ SD-755: shopify_split_set_lager_checked() warns on the error list while the setting is not used.
// 20261104 CL/SZ SD-756: shopify_stock_drain_dirty() and shopify_stock_push(), the stock push to every shop (WP06-2).
// 20261104 CL/SZ SD-756 review: the drain queues a push for a shop whose job went dead; a push's read stops at its deadline.

require_once __DIR__ . '/allocation.php';
require_once __DIR__ . '/availability.php';
require_once __DIR__ . '/../client.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../errors.php';
require_once __DIR__ . '/../queue.php';

const SHOPIFY_BARRIER_MINUTES = 30;
/** WP10 calls shopify_barrier_expire() with the order id when a move has failed this many times. */
const SHOPIFY_BARRIER_MOVE_ATTEMPTS = 3;

/**
 * shopify_split_set_lager() for a caller with a database: a warning stays open on the error list while
 * split_set_lager is set and another lager is used, and is resolved once they match or it is unset. Call it once
 * per stock push or order, not per item: every call on a mismatch counts an occurrence.
 *
 * @param int|null $setting split_set_lager, null when unset
 * @param list<array<string, mixed>> $locations the rows from shopify_availability_lagre()
 * @throws InvalidArgumentException when no lager is sellable
 */
function shopify_split_set_lager_checked(?int $setting, array $locations): int {
	$lager = shopify_split_set_lager($setting, $locations);
	if ($setting !== null && $lager !== $setting) {
		shopify_error_open(null, 'config', 'split_set_lager', "split_set_lager is lager $setting, which is not sellable; split sets go on lager $lager", 'warning');
	} else {
		shopify_error_resolve_key(null, 'config', 'split_set_lager');
	}
	return $lager;
}

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

/** Quantities per inventorySetQuantities call; Shopify takes at most 250. */
const SHOPIFY_STOCK_BATCH = 250;
/** A push stops starting calls with less than this many seconds left. */
const SHOPIFY_STOCK_MIN_SECONDS = 5;
/** A push whose remaining items all wait for a move barrier, or for the gate, looks again after this many seconds. */
const SHOPIFY_STOCK_DEFERRED_SECONDS = 60;
/** Items of the dirty set read per query while a call is filled. */
const SHOPIFY_STOCK_SLICE = 500;
const SHOPIFY_STOCK_SET = 'mutation stockSet($input: InventorySetQuantitiesInput!, $key: String!) {
	inventorySetQuantities(input: $input) @idempotent(key: $key) { inventoryAdjustmentGroup { id } userErrors { field message code } }
}';
const SHOPIFY_STOCK_ACTIVATE = 'mutation stockActivate($item: ID!, $location: ID!, $key: String!) {
	inventoryActivate(inventoryItemId: $item, locationId: $location) @idempotent(key: $key) { inventoryLevel { id } userErrors { field message } }
}';

/**
 * Run-loop step 4: moves up to $limit rows of shopify_stock_dirty into the dirty set of every shop
 * with stock_capture_enabled and queues that shop's stock.push. One transaction: a crash keeps the
 * rows, and a second drain at the same time skips the rows this one holds, so each row is drained
 * once. A shop that captures without publishing keeps its set until publishing starts. No Shopify call.
 *
 * @param array<string, mixed> $rt unused; the producer's runtime
 * @return array{drained: int, queued: int}
 */
function shopify_stock_drain_dirty(array $rt, int $limit = 500): array {
	return shopify_db_transaction(function () use ($limit): array {
		$shops = shopify_db_rows('SELECT id, shop_key FROM shopify_shops WHERE stock_capture_enabled ORDER BY id');
		if ($shops === array()) {
			return array('drained' => 0, 'queued' => 0);
		}
		$rows = shopify_db_rows(
			'DELETE FROM shopify_stock_dirty WHERE id IN (SELECT id FROM shopify_stock_dirty ORDER BY id LIMIT $1 FOR UPDATE SKIP LOCKED)
			 RETURNING id, vare_id',
			array(max(1, $limit))
		);
		if ($rows === array()) {
			// a job that went dead leaves its shop's call and marks behind; a queued job keeps its backoff
			$queued = 0;
			foreach ($shops as $shop) {
				if (shopify_queue_state_get("stock.pending:{$shop['shop_key']}") !== array() || shopify_queue_state_get("stock.dirty:{$shop['shop_key']}") !== array()) {
					$queued += (int)shopify_queue_enqueue('stock.push', (int)$shop['id'], "{$shop['shop_key']}:stock", array(), SHOPIFY_QUEUE_PRIORITY['stock.push'])['created'];
				}
			}
			return array('drained' => 0, 'queued' => $queued);
		}
		// Dirty ids are handed out in insert order, not commit order, so the mark is this drain's own
		// transaction id: every drain that sees an item changes its mark, also for a row that committed late.
		$mark = (int)shopify_db_rows('SELECT txid_current() AS x')[0]['x'];
		$ids = array_unique(array_map(fn(array $row): int => (int)$row['vare_id'], $rows));
		foreach ($shops as $shop) {
			shopify_stock_state_update("stock.dirty:{$shop['shop_key']}", function (array $set) use ($ids, $mark): array {
				foreach ($ids as $vareId) {
					$set[$vareId] = $mark;
				}
				return $set;
			});
			shopify_queue_enqueue_coalesced('stock.push', (int)$shop['id'], "{$shop['shop_key']}:stock", array(), SHOPIFY_QUEUE_PRIORITY['stock.push']);
		}
		return array('drained' => count($rows), 'queued' => count($shops));
	});
}

/**
 * The stock.push job of one shop: resends the shop's pending mutation if there is one, then sends
 * what its dirty set needs in calls of at most SHOPIFY_STOCK_BATCH quantities until nothing is left.
 * The pending mutation is stored before each call and resent unchanged until Shopify answers;
 * shopify_stock_state and the dirty set move on only once it has. Items with an open move barrier
 * keep their mark, and the job yields while only those are left. Gated on stock_publish_enabled and stock_baseline_at;
 * a closed gate yields while marks wait, so they go out once publishing starts.
 *
 * @param array{heartbeat?: callable(): bool, graphql?: array<string, mixed>} $rt
 * @param array{id: int, shop_id: int} $job
 * @return array{outcome: string, sent?: int, gate?: string, run_after?: int}
 * @throws RuntimeException when a call fails; the pending mutation stays for the retry
 */
function shopify_stock_push(array $rt, array $job, float $deadline): array {
	$shopId = (int)$job['shop_id'];
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1', array($shopId))[0] ?? null;
	if ($shop === null || $shop['stock_publish_enabled'] !== 't' || $shop['stock_baseline_at'] === null) {
		$waiting = $shop !== null && shopify_queue_state_get("stock.dirty:{$shop['shop_key']}") !== array();
		return $waiting ? array('outcome' => 'yield', 'sent' => 0, 'gate' => 'closed', 'run_after' => SHOPIFY_STOCK_DEFERRED_SECONDS)
			: array('outcome' => 'done', 'sent' => 0, 'gate' => 'closed');
	}
	$sent = 0;
	$activated = array();
	while (true) {
		if ($deadline - microtime(true) < SHOPIFY_STOCK_MIN_SECONDS || (isset($rt['heartbeat']) && !($rt['heartbeat'])())) {
			return array('outcome' => 'yield', 'sent' => $sent);
		}
		$pending = shopify_queue_state_get("stock.pending:{$shop['shop_key']}") ?: shopify_stock_next($shop, $deadline);
		if ($pending === array()) {
			// what is left waits for a barrier; an expired barrier marks nothing dirty until its sweep runs
			$left = shopify_queue_state_get("stock.dirty:{$shop['shop_key']}") !== array();
			return $left ? array('outcome' => 'yield', 'sent' => $sent, 'run_after' => SHOPIFY_STOCK_DEFERRED_SECONDS) : array('outcome' => 'done', 'sent' => $sent);
		}
		$res = shopify_graphql($shop, 'inventorySetQuantities', SHOPIFY_STOCK_SET, $pending['vars'],
			($rt['graphql'] ?? array()) + array('deadline' => $deadline, 'retry_mode' => 'idempotent', 'correlation' => array('job_id' => $job['id'] ?? null, 'key' => $pending['vars']['key'])));
		if ($res['ok'] && $res['user_errors'] === array()) {
			shopify_stock_ack($shop, $pending);
			$sent += count($pending['rows']);
			continue;
		}
		if ($res['user_errors'] === array()) {
			// kept even when the failure is final: an earlier attempt of this call may have applied, and only
			// its answer tells what Shopify shows; a final one (a 4xx) goes on the error list
			$message = "inventorySetQuantities failed ({$res['error']}): " . implode('; ', array_column($res['errors'], 'message'));
			if (!$res['retryable']) {
				shopify_error_open($shopId, 'stock', 'inventorySetQuantities', "$message; the same call is resent once the shop answers");
			}
			throw new RuntimeException("$message; the same call is resent");
		}
		shopify_stock_refused($rt, $shop, $pending, $res['user_errors'], $deadline, $activated);
	}
}

/**
 * The shop's next call, stored as its pending mutation, or an empty array when its dirty set needs
 * nothing sent. Marks that need nothing sent (in step, no link, a set, no mapped lager) are removed.
 *
 * @return array{vars: array{key: string, input: array<string, mixed>}, rows: list<array{vare_id: int, lager: int, quantity: int}>, marks: array<int, int>, read_at: string}|array{}
 */
function shopify_stock_next(array $shop, float $deadline): array {
	$shopId = (int)$shop['id'];
	$set = shopify_queue_state_get("stock.dirty:{$shop['shop_key']}");
	if ($set === array()) {
		return array();
	}
	$rows = $marks = $settled = array();
	$readAt = null;
	foreach (array_chunk(array_map('intval', array_keys($set)), SHOPIFY_STOCK_SLICE) as $ids) {
		if ($readAt !== null && $deadline - microtime(true) < SHOPIFY_STOCK_MIN_SECONDS) {
			break;
		}
		$read = shopify_stock_wanted($shopId, $ids);
		$readAt ??= $read['read_at'];
		$deferred = array_flip(shopify_barrier_deferred($shopId, $ids));
		foreach ($ids as $vareId) {
			if (isset($deferred[$vareId])) {
				continue;
			}
			$item = $read['items'][$vareId] ?? array();
			if (count($rows) + count($item) > SHOPIFY_STOCK_BATCH) {
				break 2;
			}
			if ($item === array()) {
				$settled[$vareId] = (int)$set[$vareId];
				continue;
			}
			array_push($rows, ...$item);
			$marks[$vareId] = (int)$set[$vareId];
		}
	}
	if ($settled !== array()) {
		shopify_stock_unmark($shop, $settled);
	}
	if ($rows === array()) {
		return array();
	}
	$quantities = array();
	foreach ($rows as $row) {
		$quantities[] = array('inventoryItemId' => $row['inventory_item_gid'], 'locationId' => $row['location_gid'],
			'quantity' => $row['quantity'], 'changeFromQuantity' => null);
	}
	$pending = array(
		'vars' => array('key' => shopify_stock_key(), 'input' => array('name' => 'available', 'reason' => 'correction', 'quantities' => $quantities)),
		'rows' => array_map(fn(array $r): array => array('vare_id' => $r['vare_id'], 'lager' => $r['lager'], 'quantity' => $r['quantity']), $rows),
		'marks' => $marks,
		'read_at' => $readAt,
	);
	return shopify_stock_state_update("stock.pending:{$shop['shop_key']}", fn(array $current): array => $current ?: $pending);
}

/**
 * What each item should show in the shop, per mapped sellable lager, where it differs from the
 * last acknowledged quantity. A state row counts only when it was acknowledged after the item's
 * link last changed, so a relinked item is sent again. Lagre without a location mapping are
 * skipped, with a warning on the error list.
 *
 * @param list<int> $ids
 * @return array{items: array<int, list<array{vare_id: int, lager: int, quantity: int, inventory_item_gid: string, location_gid: string}>>, read_at: string}
 */
function shopify_stock_wanted(int $shopId, array $ids): array {
	$param = shopify_barrier_int_array($ids);
	$links = shopify_db_rows(
		"SELECT l.vare_id, l.inventory_item_gid, l.updated_at, now()::timestamp AS read_at FROM shopify_product_links l JOIN varer v ON v.id = l.vare_id
		 WHERE l.shop_id = $1 AND l.variant_id = 0 AND l.listed AND l.inventory_item_gid IS NOT NULL AND l.vare_id = ANY($2::int[])
		   AND coalesce(v.samlevare, '') <> 'on'",
		array($shopId, $param)
	);
	$readAt = $links[0]['read_at'] ?? shopify_db_rows('SELECT now()::timestamp AS read_at')[0]['read_at'];
	if ($links === array()) {
		return array('items' => array(), 'read_at' => $readAt);
	}
	$stock = array();
	foreach (shopify_db_rows(
		'SELECT vare_id, lager, round(sum(beholdning) * 1000)::bigint AS milli FROM lagerstatus
		 WHERE vare_id = ANY($1::int[]) AND coalesce(variant_id, 0) = 0 GROUP BY vare_id, lager',
		array($param)
	) as $row) {
		$stock[(int)$row['vare_id']][(int)$row['lager']] = (int)$row['milli'];
	}
	$state = array();
	foreach (shopify_db_rows(
		'SELECT s.vare_id, s.lager, s.quantity FROM shopify_stock_state s
		 JOIN shopify_product_links l ON l.shop_id = s.shop_id AND l.vare_id = s.vare_id AND l.variant_id = s.variant_id
		 WHERE s.shop_id = $1 AND s.variant_id = 0 AND s.vare_id = ANY($2::int[]) AND s.acknowledged_at >= l.updated_at',
		array($shopId, $param)
	) as $row) {
		$state[(int)$row['vare_id']][(int)$row['lager']] = (int)round((float)$row['quantity']);
	}
	$locations = array_column(shopify_db_rows('SELECT lager, location_gid FROM shopify_location_map WHERE shop_id = $1', array($shopId)), 'location_gid', 'lager');
	$lagre = shopify_availability_lagre(shopify_lager_config());
	$sellable = array_flip(array_map(fn(array $l): int => (int)$l['lager'], array_filter($lagre, fn(array $l): bool => $l['rolle'] === 'saelgbar' && !$l['virtual'])));
	$unmapped = array();
	$items = array();
	foreach ($links as $link) {
		$vareId = (int)$link['vare_id'];
		foreach (shopify_availability(array('vare_id' => $vareId), $lagre, $stock, array(), null) as $lager => $want) {
			if (!isset($sellable[$lager])) {
				continue;
			}
			if (!isset($locations[$lager])) {
				$unmapped[$lager] = true;
				continue;
			}
			if (($state[$vareId][$lager] ?? null) === $want['available']) {
				continue;
			}
			$items[$vareId][] = array('vare_id' => $vareId, 'lager' => $lager, 'quantity' => $want['available'],
				'inventory_item_gid' => $link['inventory_item_gid'], 'location_gid' => $locations[$lager]);
		}
	}
	if ($unmapped !== array()) {
		ksort($unmapped);
		shopify_error_open($shopId, 'config', 'stock_location_map', 'sellable lager ' . implode(', ', array_keys($unmapped)) . ' has no Shopify location in this shop; its stock is not sent', 'warning');
	} else {
		shopify_error_resolve_key($shopId, 'config', 'stock_location_map');
	}
	return array('items' => $items, 'read_at' => $readAt);
}

/** Shopify acknowledged the pending mutation: the state, the pending slot and the marks move on together. */
function shopify_stock_ack(array $shop, array $pending): void {
	shopify_db_transaction(function () use ($shop, $pending): void {
		foreach ($pending['rows'] as $row) {
			shopify_db_rows(
				'INSERT INTO shopify_stock_state (shop_id, vare_id, variant_id, lager, quantity, acknowledged_at) VALUES ($1, $2, 0, $3, $4, $5)
				 ON CONFLICT (shop_id, vare_id, variant_id, lager) DO UPDATE SET quantity = EXCLUDED.quantity, acknowledged_at = EXCLUDED.acknowledged_at
				 WHERE shopify_stock_state.acknowledged_at IS NULL OR shopify_stock_state.acknowledged_at <= EXCLUDED.acknowledged_at',
				array((int)$shop['id'], $row['vare_id'], $row['lager'], $row['quantity'], $pending['read_at'])
			);
		}
		shopify_stock_clear_pending($shop, $pending);
		shopify_stock_unmark($shop, $pending['marks']);
	});
}

/** Clears the shop's pending slot while it still holds this call. */
function shopify_stock_clear_pending(array $shop, array $pending): void {
	shopify_stock_state_update("stock.pending:{$shop['shop_key']}", fn(array $current): array
		=> ($current['vars']['key'] ?? null) === $pending['vars']['key'] ? array() : $current);
}

/**
 * Shopify refused the call, so nothing of it was set and its key keeps answering the refusal: the
 * pending slot is cleared and the next call is built again. A level missing at a location is
 * activated, once per push. Any other refusal of a named quantity, or a level still missing after
 * activating, drops that item's mark with a warning, so one item cannot hold back the shop; it is
 * sent again when it is marked again. A refusal that names no quantity fails the job.
 *
 * @param list<array<string, mixed>> $userErrors
 * @param array<string, true> $activated the item and location pairs this push activated
 * @throws RuntimeException for a refusal that names no quantity, or a failed activation
 * @throws LogicException when Shopify says the key was reused with other input
 */
function shopify_stock_refused(array $rt, array $shop, array $pending, array $userErrors, float $deadline, array &$activated): void {
	shopify_stock_clear_pending($shop, $pending);
	$shopId = (int)$shop['id'];
	$drop = array();
	foreach ($userErrors as $error) {
		$code = (string)($error['code'] ?? '');
		if ($code === 'IDEMPOTENCY_KEY_PARAMETER_MISMATCH') {
			throw new LogicException("inventorySetQuantities key {$pending['vars']['key']} was sent with other input");
		}
		$index = isset($error['field'][2]) && ($error['field'][1] ?? null) === 'quantities' ? (int)$error['field'][2] : null;
		$q = $index === null ? null : ($pending['vars']['input']['quantities'][$index] ?? null);
		if ($q === null) {
			$message = 'Shopify refused the stock push: ' . (string)$error['message'];
			shopify_error_open($shopId, 'stock', 'inventorySetQuantities', $message);
			throw new RuntimeException($message);
		}
		$pair = "{$q['inventoryItemId']}|{$q['locationId']}";
		if ($code === 'ITEM_NOT_STOCKED_AT_LOCATION' && !isset($activated[$pair])) {
			$activated[$pair] = true;
			shopify_stock_activate($rt, $shop, $q, $deadline);
			continue;
		}
		$vareId = $pending['rows'][$index]['vare_id'];
		$drop[$vareId] = (int)($pending['marks'][$vareId] ?? 0);
		shopify_error_open($shopId, 'stock', "item:$vareId", "Shopify refused the stock of item $vareId at {$q['locationId']}: " . (string)$error['message'], 'warning');
	}
	if ($drop !== array()) {
		shopify_stock_unmark($shop, $drop);
	}
}

/** Stocks the item at the location (inventoryActivate), which does nothing to a level that is already there. */
function shopify_stock_activate(array $rt, array $shop, array $q, float $deadline): void {
	$res = shopify_graphql($shop, 'inventoryActivate', SHOPIFY_STOCK_ACTIVATE,
		array('item' => $q['inventoryItemId'], 'location' => $q['locationId'], 'key' => shopify_stock_key()),
		($rt['graphql'] ?? array()) + array('deadline' => $deadline, 'retry_mode' => 'idempotent'));
	if (!$res['ok'] || $res['user_errors'] !== array()) {
		$why = implode('; ', array_column($res['user_errors'], 'message')) ?: (string)$res['error'];
		throw new RuntimeException("inventoryActivate of {$q['inventoryItemId']} at {$q['locationId']} failed: $why");
	}
}

/** Removes the marks given, each only while it is still the mark given: an item marked again since stays. */
function shopify_stock_unmark(array $shop, array $marks): void {
	shopify_stock_state_update("stock.dirty:{$shop['shop_key']}", function (array $set) use ($marks): array {
		foreach ($marks as $vareId => $mark) {
			if ((int)($set[$vareId] ?? 0) === $mark) {
				unset($set[$vareId]);
			}
		}
		return $set;
	});
}

/**
 * Reads, changes and writes one queue state key under its lock, so two runs never lose each
 * other's change; an empty result removes nothing but stores an empty value.
 *
 * @param callable(array): array $change
 * @return array the value written
 */
function shopify_stock_state_update(string $key, callable $change): array {
	return shopify_db_transaction(function () use ($key, $change): array {
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_state:$key"));
		$value = $change(shopify_queue_state_get($key));
		shopify_queue_state_set($key, $value);
		return $value;
	});
}

/** A new @idempotent key for one call. */
function shopify_stock_key(): string {
	return bin2hex(random_bytes(16));
}
