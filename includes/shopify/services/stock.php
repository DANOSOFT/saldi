<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/stock.php --- ver 5.1.0 --- 2026.11.06
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
// job's checkpoint, so every job of the shop sees them. Saldi's stock is never changed from Shopify.
//
// Sets, display stock, reconcile, baseline and remap (WP06-3, K09, K12, K13, K19, K55): a set publishes
// complete sets per lager and the surplus on the split-set lager, and waits for a barrier on any of
// its components. A display lager is sent as 0, and stock.display writes the variant metafield
// saldi.udstillet, the JSON list of the location names the item stands at. stock.reconcile compares
// a nightly bulk read with Saldi and corrects through the push: it forgets the pair's acknowledgement
// and marks the item, so it never sends a call itself and never changes Saldi. stock.baseline sends
// every pair, also with publishing off, and stamps stock_baseline_at only when all are acknowledged.
// shopify_stock_remap() zeroes a lager's location before its mapping changes.
//
// 20261102 CL/SZ SD-755: created (WP06-1).
// 20261103 CL/SZ SD-755: shopify_split_set_lager_checked() warns on the error list while the setting is not used.
// 20261104 CL/SZ SD-756: shopify_stock_drain_dirty() and shopify_stock_push(), the stock push to every shop (WP06-2).
// 20261104 CL/SZ SD-756 review: the drain queues a push for a shop whose job went dead; a push's read stops at its deadline.
// 20261105 CL/SZ SD-756: each call reads where its items are stocked and stocks the rest first, as Shopify hides a quantity set on a pair not stocked.
//                An item Shopify refuses to stock is left out with a warning, so it can't hold back the shop.
// 20261105 CL/SZ SD-756 review: a pending call waits for an open barrier, an activation forgets the pair's acknowledgement.
//                A pair stocked after all goes on to the set, and an item over one call's size is left out with a warning.
// 20261105 CL/SZ SD-757: sets and display lagre in the push (WP06-3).
//                shopify_stock_display(), shopify_stock_reconcile(), shopify_stock_baseline() and shopify_stock_remap().
// 20261105 CL/SZ SD-757 review: a set that breaks is sent as 0, an acknowledged item's stock warning is resolved.
//                A broken set warns on set:<vare_id>, resolved as soon as the set publishes again.
//                A remap checks the new location is the shop's, and counts a started baseline as written.
// 20261106 CL/SZ SD-757 review: the baseline stamps only on Saldi's quantities, the remap's zero call waits for its items' barriers.
//                The reconcile defers a pair while Shopify has stock committed there.

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
const SHOPIFY_STOCK_LEVELS = 'query stockLevels($ids: [ID!]!) {
	nodes(ids: $ids) { ... on InventoryItem { id inventoryLevels(first: 50) { nodes { location { id } } } } }
}';
const SHOPIFY_STOCK_ACTIVATE = 'mutation stockActivate($item: ID!, $location: ID!, $available: Int!, $key: String!) {
	inventoryActivate(inventoryItemId: $item, locationId: $location, available: $available) @idempotent(key: $key) { inventoryLevel { id } userErrors { field message } }
}';
/** Items per metafieldsSet call; Shopify takes at most 25 metafields. */
const SHOPIFY_STOCK_DISPLAY_BATCH = 25;
const SHOPIFY_STOCK_DISPLAY_READ = 'query stockDisplayRead($ids: [ID!]!) {
	nodes(ids: $ids) { ... on ProductVariant { id metafield(namespace: "saldi", key: "udstillet") { value } } ... on Location { id name } }
}';
const SHOPIFY_STOCK_DISPLAY_SET = 'mutation stockDisplaySet($metafields: [MetafieldsSetInput!]!) {
	metafieldsSet(metafields: $metafields) { metafields { id } userErrors { field message code } }
}';
/** The nightly bulk read: every inventory item's levels, one JSONL line each after the item's own line. */
const SHOPIFY_STOCK_BULK_LEVELS = '{ inventoryItems { edges { node { id inventoryLevels { edges { node { location { id } quantities(names: ["available", "committed"]) { name quantity } } } } } } } }';
/** Lines of the bulk file, or items of the final check, compared per database read. */
const SHOPIFY_STOCK_RECONCILE_CHUNK = 500;
/** Seconds between looks at a running bulk operation. */
const SHOPIFY_STOCK_RECONCILE_POLL = 60;
/** Drifted pairs named in the report; the count covers them all. */
const SHOPIFY_STOCK_RECONCILE_NAMED = 20;

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
				if (shopify_queue_state_get("stock.display:{$shop['shop_key']}") !== array()) {
					shopify_queue_enqueue('stock.display', (int)$shop['id'], "{$shop['shop_key']}:display", array(), SHOPIFY_QUEUE_PRIORITY['stock.display']);
				}
			}
			return array('drained' => 0, 'queued' => $queued);
		}
		// Dirty ids are handed out in insert order, not commit order, so the mark is this drain's own
		// transaction id: every drain that sees an item changes its mark, also for a row that committed late.
		$mark = (int)shopify_db_rows('SELECT txid_current() AS x')[0]['x'];
		$ids = array_unique(array_map(fn(array $row): int => (int)$row['vare_id'], $rows));
		foreach ($shops as $shop) {
			shopify_stock_mark($shop, $ids, $mark);
		}
		return array('drained' => count($rows), 'queued' => count($shops));
	});
}

/**
 * Marks items for the shop's stock.push and stock.display, under the state keys' locks, and queues both jobs.
 *
 * @param list<int> $ids
 * @param int $mark the caller's transaction id
 */
function shopify_stock_mark(array $shop, array $ids, int $mark): void {
	foreach (array('stock.dirty', 'stock.display') as $set) {
		shopify_stock_state_update("$set:{$shop['shop_key']}", function (array $marks) use ($ids, $mark): array {
			foreach ($ids as $vareId) {
				$marks[$vareId] = $mark;
			}
			return $marks;
		});
	}
	shopify_queue_enqueue_coalesced('stock.push', (int)$shop['id'], "{$shop['shop_key']}:stock", array(), SHOPIFY_QUEUE_PRIORITY['stock.push']);
	shopify_queue_enqueue_coalesced('stock.display', (int)$shop['id'], "{$shop['shop_key']}:display", array(), SHOPIFY_QUEUE_PRIORITY['stock.display']);
}

/**
 * The stock.push job of one shop: resends the shop's pending mutation if there is one, then sends
 * what its dirty set needs in calls of at most SHOPIFY_STOCK_BATCH quantities until nothing is left.
 * The pending mutation is stored before each call and resent unchanged until Shopify answers;
 * shopify_stock_state and the dirty set move on only once it has. Items with an open move barrier
 * keep their mark, and the job yields while only those are left. The exception is a stored call with such an item: it
 * waits with its key and input, and as a shop has one pending call, the shop's other items wait with it, until
 * no barrier is open on its items (each lasts at most SHOPIFY_BARRIER_MINUTES). Gated on stock_publish_enabled and stock_baseline_at;
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
	$rt['lager_config'] = shopify_stock_checked_config();
	return shopify_stock_send($rt, $job, $shop, $deadline);
}

/**
 * The lager settings for one job, with the warning on split_set_lager brought up to date once. A
 * tenant with no sellable lager has no split-set lager to check.
 *
 * @return array{lagre: list<array<string, string|null>>, split_set_lager: int|null, virtual_lager: int|null}
 */
function shopify_stock_checked_config(): array {
	$config = shopify_lager_config();
	$lagre = shopify_availability_lagre($config);
	if (array_filter($lagre, fn(array $l): bool => $l['rolle'] === 'saelgbar' && !$l['virtual']) !== array()) {
		shopify_split_set_lager_checked($config['split_set_lager'], $lagre);
	}
	return $config;
}

/**
 * Sends the shop's pending mutation and then what its dirty set needs, whatever the publish gate
 * says; shopify_stock_push() and shopify_stock_baseline() hold the gates.
 *
 * @return array{outcome: string, sent: int, run_after?: int}
 * @throws RuntimeException when a call fails; the pending mutation stays for the retry
 */
function shopify_stock_send(array $rt, array $job, array $shop, float $deadline): array {
	$shopId = (int)$shop['id'];
	$sent = 0;
	while (true) {
		if ($deadline - microtime(true) < SHOPIFY_STOCK_MIN_SECONDS || (isset($rt['heartbeat']) && !($rt['heartbeat'])())) {
			return array('outcome' => 'yield', 'sent' => $sent);
		}
		$pending = shopify_queue_state_get("stock.pending:{$shop['shop_key']}");
		if ($pending !== array() && shopify_barrier_deferred($shopId, $pending['watch'] ?? array_keys($pending['marks'])) !== array()) {
			// held as it is: this may be its first send, with a quantity read before the barrier opened
			return array('outcome' => 'yield', 'sent' => $sent, 'run_after' => SHOPIFY_STOCK_DEFERRED_SECONDS);
		}
		$pending = $pending ?: shopify_stock_next($rt, $shop, $deadline);
		if ($pending === array()) {
			// what is left waits for a barrier, or ran out of time stocking its items; an expired barrier marks nothing dirty until its sweep runs
			$left = shopify_queue_state_get("stock.dirty:{$shop['shop_key']}") !== array();
			return $left ? array('outcome' => 'yield', 'sent' => $sent, 'run_after' => SHOPIFY_STOCK_DEFERRED_SECONDS) : array('outcome' => 'done', 'sent' => $sent);
		}
		if (shopify_stock_call($rt, $job, $shop, $pending, $deadline) === array()) {
			$sent += count($pending['rows']);
		}
	}
}

/**
 * Sends one stored call and settles its answer.
 *
 * @return list<int> the items Shopify refused, so nothing of the call was set; empty when it was acknowledged
 * @throws RuntimeException when Shopify doesn't answer, or refuses without naming a quantity
 */
function shopify_stock_call(array $rt, array $job, array $shop, array $pending, float $deadline): array {
	$res = shopify_graphql($shop, 'inventorySetQuantities', SHOPIFY_STOCK_SET, $pending['vars'],
		($rt['graphql'] ?? array()) + array('deadline' => $deadline, 'retry_mode' => 'idempotent', 'correlation' => array('job_id' => $job['id'] ?? null, 'key' => $pending['vars']['key'])));
	if ($res['ok'] && $res['user_errors'] === array()) {
		shopify_stock_ack($shop, $pending);
		return array();
	}
	if ($res['user_errors'] === array()) {
		// kept even when the failure is final: an earlier attempt of this call may have applied, and only
		// its answer tells what Shopify shows; a final one (a 4xx) goes on the error list
		$message = "inventorySetQuantities failed ({$res['error']}): " . implode('; ', array_column($res['errors'], 'message'));
		if (!$res['retryable']) {
			shopify_error_open((int)$shop['id'], 'stock', 'inventorySetQuantities', "$message; the same call is resent once the shop answers");
		}
		throw new RuntimeException("$message; the same call is resent");
	}
	return shopify_stock_refused($shop, $pending, $res['user_errors']);
}

/**
 * The shop's next call, stored as its pending mutation, or an empty array when its dirty set needs
 * nothing sent. Marks that need nothing sent (in step, no link, no valid set, no mapped lager) are removed.
 * A set waits for a barrier on itself or on any of its components.
 *
 * @return array{vars: array{key: string, input: array<string, mixed>}, rows: list<array{vare_id: int, lager: int, quantity: int}>, marks: array<int, int>, watch: list<int>, read_at: string}|array{}
 */
function shopify_stock_next(array $rt, array $shop, float $deadline): array {
	$shopId = (int)$shop['id'];
	$set = shopify_queue_state_get("stock.dirty:{$shop['shop_key']}");
	if ($set === array()) {
		return array();
	}
	$config = $rt['lager_config'] ?? shopify_lager_config();
	$rows = $marks = $settled = $watch = array();
	$readAt = null;
	foreach (array_chunk(array_map('intval', array_keys($set)), SHOPIFY_STOCK_SLICE) as $ids) {
		if ($readAt !== null && $deadline - microtime(true) < SHOPIFY_STOCK_MIN_SECONDS) {
			break;
		}
		$read = shopify_stock_wanted($shopId, $ids, $config);
		$readAt ??= $read['read_at'];
		$deferred = shopify_stock_deferred($shopId, $ids, $read['components']);
		foreach ($ids as $vareId) {
			if (isset($deferred[$vareId])) {
				continue;
			}
			$item = $read['items'][$vareId] ?? array();
			if (count($item) > SHOPIFY_STOCK_BATCH) {
				shopify_error_open($shopId, 'stock', "item:$vareId", 'Item ' . $vareId . ' has ' . count($item) . ' quantities, more than one call takes; it is not sent.', 'warning');
				$settled[$vareId] = (int)$set[$vareId];
				continue;
			}
			if (count($rows) + count($item) > SHOPIFY_STOCK_BATCH) {
				break 2;
			}
			if ($item === array()) {
				$settled[$vareId] = (int)$set[$vareId];
				continue;
			}
			array_push($rows, ...$item);
			$marks[$vareId] = (int)$set[$vareId];
			$watch[$vareId] = array_merge(array($vareId), $read['components'][$vareId] ?? array());
		}
	}
	if ($settled !== array()) {
		shopify_stock_unmark($shop, $settled);
	}
	$refused = $rows === array() ? array() : shopify_stock_levels_ready($rt, $shop, $rows, $deadline);
	if ($refused === null) {
		return array();
	}
	if ($refused !== array()) {
		shopify_stock_unmark($shop, array_intersect_key($marks, $refused));
		$marks = array_diff_key($marks, $refused);
		$watch = array_diff_key($watch, $refused);
		$rows = array_values(array_filter($rows, fn(array $r): bool => !isset($refused[$r['vare_id']])));
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
		'watch' => array_values(array_unique(array_merge(...array_values($watch)))),
		'read_at' => $readAt,
	);
	return shopify_stock_state_update("stock.pending:{$shop['shop_key']}", fn(array $current): array => $current ?: $pending);
}

/**
 * Which of the items wait for a move barrier: a barrier on the item, or for a set on any of its components.
 *
 * @param list<int> $ids
 * @param array<int, list<int>> $components set => its components
 * @return array<int, true>
 */
function shopify_stock_deferred(int $shopId, array $ids, array $components): array {
	$barred = array_flip(shopify_barrier_deferred($shopId, array_merge($ids, ...array_values($components))));
	$deferred = array();
	foreach ($ids as $vareId) {
		if (isset($barred[$vareId]) || array_intersect_key(array_flip($components[$vareId] ?? array()), $barred) !== array()) {
			$deferred[$vareId] = true;
		}
	}
	return $deferred;
}

/**
 * What the shop should show of the items, whatever it last acknowledged: per lager the push sends
 * (sellable lagre, the split-set surplus and display lagre at 0). A sellable lager without a location
 * mapping is skipped with a warning on the error list. A display lager without one is skipped silently:
 * the shop then doesn't show that display, and nothing can be oversold there. A set
 * that can't be published (no bill of materials, a set inside it, a quantity that doesn't work) is
 * sent as 0 with a warning (invalid). An item without a listed link is left out.
 *
 * @param list<int> $ids
 * @param array{lagre: list<array<string, string|null>>, split_set_lager: int|null, virtual_lager: int|null} $config
 * @return array{
 *   items: array<int, array{inventory_item_gid: string, variant_gid: string|null, pairs: array<int, array{quantity: int, udstillet: bool, location_gid: string}>, invalid: bool}>,
 *   components: array<int, list<int>>, unmapped: list<int>, read_at: string
 * }
 */
function shopify_stock_plan(int $shopId, array $ids, array $config): array {
	$links = shopify_db_rows(
		"SELECT l.vare_id, l.inventory_item_gid, l.variant_gid, trim(coalesce(v.samlevare, '')) = 'on' AS is_set, now()::timestamp AS read_at
		 FROM shopify_product_links l JOIN varer v ON v.id = l.vare_id
		 WHERE l.shop_id = $1 AND l.variant_id = 0 AND l.listed AND l.inventory_item_gid IS NOT NULL AND l.vare_id = ANY($2::int[])",
		array($shopId, shopify_barrier_int_array($ids))
	);
	$readAt = $links[0]['read_at'] ?? shopify_db_rows('SELECT now()::timestamp AS read_at')[0]['read_at'];
	if ($links === array()) {
		return array('items' => array(), 'components' => array(), 'unmapped' => array(), 'read_at' => $readAt);
	}
	$locations = array_column(shopify_db_rows('SELECT lager, location_gid FROM shopify_location_map WHERE shop_id = $1', array($shopId)), 'location_gid', 'lager');
	$lagre = shopify_availability_lagre($config);
	$sellable = array_flip(array_map(fn(array $l): int => (int)$l['lager'], array_filter($lagre, fn(array $l): bool => $l['rolle'] === 'saelgbar' && !$l['virtual'])));
	$unmapped = array_diff_key($sellable, $locations);
	ksort($unmapped);
	if ($unmapped !== array()) {
		shopify_error_open($shopId, 'config', 'stock_location_map', 'sellable lager ' . implode(', ', array_keys($unmapped)) . ' has no Shopify location in this shop; its stock is not sent', 'warning');
	} else {
		shopify_error_resolve_key($shopId, 'config', 'stock_location_map');
	}
	$setIds = array_map(fn(array $l): int => (int)$l['vare_id'], array_filter($links, fn(array $l): bool => $l['is_set'] === 't'));
	$boms = shopify_stock_boms($setIds);
	$components = array_map(fn(array $bom): array => array_values(array_unique(array_column($bom, 'vare_id'))), $boms);
	$stock = array();
	foreach (shopify_db_rows(
		'SELECT vare_id, lager, round(sum(beholdning) * 1000)::bigint AS milli FROM lagerstatus
		 WHERE vare_id = ANY($1::int[]) AND coalesce(variant_id, 0) = 0 GROUP BY vare_id, lager',
		array(shopify_barrier_int_array(array_values(array_unique(array_merge(array_column($links, 'vare_id'), ...array_values($components))))))
	) as $row) {
		$stock[(int)$row['vare_id']][(int)$row['lager']] = (int)$row['milli'];
	}
	$items = $valid = array();
	foreach ($links as $link) {
		$vareId = (int)$link['vare_id'];
		$bom = array();
		if ($link['is_set'] === 't') {
			$bom = $boms[$vareId] ?? array();
			if ($bom === array() || array_filter($bom, fn(array $c): bool => $c['nested']) !== array()) {
				$items[$vareId] = shopify_stock_invalid_set($shopId, $link, $lagre, $locations, "Set $vareId has no bill of materials, or a set inside it; it is sent as 0.");
				continue;
			}
		}
		try {
			$wanted = shopify_availability(array('vare_id' => $vareId), $lagre, $stock, $bom, $config['split_set_lager']);
		} catch (InvalidArgumentException $e) {
			if ($bom === array()) {
				throw $e;
			}
			$items[$vareId] = shopify_stock_invalid_set($shopId, $link, $lagre, $locations, "Set $vareId is sent as 0: " . $e->getMessage());
			continue;
		}
		$pairs = array();
		foreach ($wanted as $lager => $want) {
			if (isset($locations[$lager])) {
				$pairs[$lager] = array('quantity' => $want['available'], 'udstillet' => $want['udstillet'], 'location_gid' => $locations[$lager]);
			}
		}
		$items[$vareId] = array('inventory_item_gid' => $link['inventory_item_gid'], 'variant_gid' => $link['variant_gid'], 'pairs' => $pairs, 'invalid' => false);
		if ($bom !== array()) {
			$valid[] = shopify_error_key($shopId, 'stock', "set:$vareId");
		}
	}
	if ($valid !== array()) {
		// a set that publishes again ends its warning, also when it needs no new quantity
		shopify_db_rows('UPDATE shopify_errors SET resolved_at = now() WHERE error_key = ANY($1::text[]) AND resolved_at IS NULL', array('{' . implode(',', $valid) . '}'));
	}
	return array('items' => $items, 'components' => array_intersect_key($components, $items), 'unmapped' => array_keys($unmapped), 'read_at' => $readAt);
}

/**
 * A set that can't be published is sent as 0 at every mapped lager it would be sent to, so a quantity
 * published before it broke can't be sold, and its warning stays open.
 *
 * @return array{inventory_item_gid: string, variant_gid: string|null, pairs: array<int, array{quantity: int, udstillet: bool, location_gid: string}>, invalid: bool}
 */
function shopify_stock_invalid_set(int $shopId, array $link, array $lagre, array $locations, string $why): array {
	shopify_error_open($shopId, 'stock', "set:{$link['vare_id']}", $why, 'warning');
	$pairs = array();
	foreach ($lagre as $l) {
		$lager = (int)$l['lager'];
		if (empty($l['virtual']) && in_array($l['rolle'], array('saelgbar', 'udstilling'), true) && isset($locations[$lager])) {
			$pairs[$lager] = array('quantity' => 0, 'udstillet' => false, 'location_gid' => $locations[$lager]);
		}
	}
	return array('inventory_item_gid' => $link['inventory_item_gid'], 'variant_gid' => $link['variant_gid'], 'pairs' => $pairs, 'invalid' => true);
}

/**
 * The bills of materials of the sets, in shopify_availability()'s shape.
 *
 * @param list<int> $setIds
 * @return array<int, list<array{bom_id: int, vare_id: int, quantity_milli: int, nested: bool}>>
 */
function shopify_stock_boms(array $setIds): array {
	if ($setIds === array()) {
		return array();
	}
	$boms = array();
	foreach (shopify_db_rows(
		'SELECT s.id, s.indgaar_i, s.vare_id, round(s.antal * 1000)::bigint AS milli,
			EXISTS (SELECT 1 FROM styklister sub WHERE sub.indgaar_i = s.vare_id) AS nested
		 FROM styklister s WHERE s.indgaar_i = ANY($1::int[]) ORDER BY s.indgaar_i, s.posnr, s.id',
		array(shopify_barrier_int_array($setIds))
	) as $row) {
		$boms[(int)$row['indgaar_i']][] = array('bom_id' => (int)$row['id'], 'vare_id' => (int)$row['vare_id'], 'quantity_milli' => (int)$row['milli'], 'nested' => $row['nested'] === 't');
	}
	return $boms;
}

/**
 * What each item should show in the shop where it differs from the last acknowledged quantity.
 *
 * @param list<int> $ids
 * @param array{lagre: list<array<string, string|null>>, split_set_lager: int|null, virtual_lager: int|null} $config
 * @return array{items: array<int, list<array{vare_id: int, lager: int, quantity: int, inventory_item_gid: string, location_gid: string}>>, components: array<int, list<int>>, read_at: string}
 */
function shopify_stock_wanted(int $shopId, array $ids, array $config): array {
	$plan = shopify_stock_plan($shopId, $ids, $config);
	if ($plan['items'] === array()) {
		return array('items' => array(), 'components' => array(), 'read_at' => $plan['read_at']);
	}
	$state = shopify_stock_acknowledged($shopId, array_keys($plan['items']));
	$items = array();
	foreach ($plan['items'] as $vareId => $item) {
		foreach ($item['pairs'] as $lager => $pair) {
			if (($state[$vareId][$lager] ?? null) !== $pair['quantity']) {
				$items[$vareId][] = array('vare_id' => $vareId, 'lager' => $lager, 'quantity' => $pair['quantity'],
					'inventory_item_gid' => $item['inventory_item_gid'], 'location_gid' => $pair['location_gid']);
			}
		}
	}
	return array('items' => $items, 'components' => $plan['components'], 'read_at' => $plan['read_at']);
}

/**
 * The quantities Shopify last acknowledged. A state row counts only when it was acknowledged after the
 * item's link last changed, so a relinked item is sent again.
 *
 * @param list<int> $ids
 * @return array<int, array<int, int>> vare_id => lager => quantity
 */
function shopify_stock_acknowledged(int $shopId, array $ids): array {
	$state = array();
	foreach (shopify_db_rows(
		'SELECT s.vare_id, s.lager, s.quantity FROM shopify_stock_state s
		 JOIN shopify_product_links l ON l.shop_id = s.shop_id AND l.vare_id = s.vare_id AND l.variant_id = s.variant_id
		 WHERE s.shop_id = $1 AND s.variant_id = 0 AND s.vare_id = ANY($2::int[]) AND s.acknowledged_at >= l.updated_at',
		array($shopId, shopify_barrier_int_array($ids))
	) as $row) {
		$state[(int)$row['vare_id']][(int)$row['lager']] = (int)round((float)$row['quantity']);
	}
	return $state;
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
		// Shopify took the items, so a warning left by an earlier refusal is over (a broken set's own warning is set:<vare_id>)
		$keys = array();
		foreach (array_unique(array_column($pending['rows'], 'vare_id')) as $vareId) {
			$keys[] = shopify_error_key((int)$shop['id'], 'stock', "item:$vareId");
		}
		if ($keys !== array()) {
			shopify_db_rows('UPDATE shopify_errors SET resolved_at = now() WHERE error_key = ANY($1::text[]) AND resolved_at IS NULL', array('{' . implode(',', $keys) . '}'));
		}
	});
}

/** Clears the shop's pending slot while it still holds this call. */
function shopify_stock_clear_pending(array $shop, array $pending): void {
	shopify_stock_state_update("stock.pending:{$shop['shop_key']}", fn(array $current): array
		=> ($current['vars']['key'] ?? null) === $pending['vars']['key'] ? array() : $current);
}

/**
 * Shopify refused the call, so nothing of it was set and its key keeps answering the refusal: the
 * pending slot is cleared and the next call is built again. A refusal of a named quantity drops
 * that item's mark with a warning, so one item cannot hold back the shop; it is sent again when
 * it is marked again. A refusal that names no quantity fails the job.
 *
 * @param list<array<string, mixed>> $userErrors
 * @return list<int> the items refused
 * @throws RuntimeException for a refusal that names no quantity
 * @throws LogicException when Shopify says the key was reused with other input
 */
function shopify_stock_refused(array $shop, array $pending, array $userErrors): array {
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
		$vareId = $pending['rows'][$index]['vare_id'];
		$drop[$vareId] = (int)($pending['marks'][$vareId] ?? 0);
		shopify_error_open($shopId, 'stock', "item:$vareId", "Shopify refused the stock of item $vareId at {$q['locationId']}: " . (string)$error['message'], 'warning');
	}
	if ($drop !== array()) {
		shopify_stock_unmark($shop, $drop);
	}
	return array_keys($drop);
}

/**
 * Stocks the rows' items where Shopify doesn't, with one read per call. An inventorySetQuantities on
 * a pair that isn't stocked answers success and keeps the quantity hidden until the pair is
 * activated, so a push there must not go out first. Read every time, as a shop can stop stocking
 * an item after a push.
 *
 * An item Shopify refuses to stock goes on the error list as a warning and is left out, so one item
 * cannot hold back the shop.
 *
 * @param list<array{vare_id: int, lager: int, quantity: int, inventory_item_gid: string, location_gid: string}> $rows
 * @return array<int, true>|null the items refused, null when the time ran out; the marks stay and the next run goes on
 * @throws RuntimeException when Shopify doesn't answer
 */
function shopify_stock_levels_ready(array $rt, array $shop, array $rows, float $deadline): ?array {
	$res = shopify_graphql($shop, 'stockLevels', SHOPIFY_STOCK_LEVELS, array('ids' => array_values(array_unique(array_column($rows, 'inventory_item_gid')))),
		($rt['graphql'] ?? array()) + array('deadline' => $deadline, 'retry_mode' => 'read'));
	if (!$res['ok']) {
		throw new RuntimeException("reading the stocked locations failed: {$res['error']}");
	}
	$known = $stocked = array();
	foreach (array_filter($res['data']['nodes']) as $node) {
		$known[$node['id']] = true;
		foreach ($node['inventoryLevels']['nodes'] as $level) {
			$stocked["{$node['id']}|{$level['location']['id']}"] = true;
		}
	}
	$refused = array();
	foreach ($rows as $row) {
		// an item Shopify doesn't know is left to the set, whose refusal drops only that item
		if (isset($refused[$row['vare_id']]) || !isset($known[$row['inventory_item_gid']]) || isset($stocked["{$row['inventory_item_gid']}|{$row['location_gid']}"])) {
			continue;
		}
		if ($deadline - microtime(true) < SHOPIFY_STOCK_MIN_SECONDS) {
			return null;
		}
		// the activation writes a quantity, so a lost answer must not leave the old acknowledgement in step
		shopify_db_rows('DELETE FROM shopify_stock_state WHERE shop_id = $1 AND vare_id = $2 AND variant_id = 0 AND lager = $3', array((int)$shop['id'], $row['vare_id'], $row['lager']));
		$why = shopify_stock_activate($rt, $shop, $row, $deadline);
		if ($why !== null) {
			$refused[$row['vare_id']] = true;
			shopify_error_open((int)$shop['id'], 'stock', "item:{$row['vare_id']}", "Shopify refused to stock item {$row['vare_id']} at {$row['location_gid']}: $why", 'warning');
		}
	}
	return $refused;
}

/**
 * Stocks the item at the location (inventoryActivate) with the quantity wanted, so a quantity hidden
 * there is never shown. For a pair read as not stocked; one that is stocked after all answers its level
 * with a refusal of the quantity, and the set that follows sends it.
 *
 * @return string|null Shopify's refusal, null when the item is stocked
 * @throws RuntimeException when Shopify doesn't answer
 */
function shopify_stock_activate(array $rt, array $shop, array $row, float $deadline): ?string {
	$res = shopify_graphql($shop, 'inventoryActivate', SHOPIFY_STOCK_ACTIVATE,
		array('item' => $row['inventory_item_gid'], 'location' => $row['location_gid'], 'available' => $row['quantity'], 'key' => shopify_stock_key()),
		($rt['graphql'] ?? array()) + array('deadline' => $deadline, 'retry_mode' => 'idempotent'));
	if ($res['user_errors'] !== array() && ($res['data']['inventoryActivate']['inventoryLevel'] ?? null) === null) {
		return implode('; ', array_column($res['user_errors'], 'message'));
	}
	if ($res['user_errors'] === array() && !$res['ok']) {
		throw new RuntimeException("inventoryActivate of {$row['inventory_item_gid']} at {$row['location_gid']} failed: {$res['error']}");
	}
	return null;
}

/** Removes the marks given, each only while it is still the mark given: an item marked again since stays. */
function shopify_stock_unmark(array $shop, array $marks, string $set = 'stock.dirty'): void {
	shopify_stock_state_update("$set:{$shop['shop_key']}", function (array $set) use ($marks): array {
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

/**
 * The stock.display job of one shop (K13): the variant metafield saldi.udstillet follows the display
 * locations each marked item stands at, as a JSON list of location names. What the shop has is read
 * first, so an item that didn't change costs no write, and an item that never stood anywhere gets no
 * metafield; one that left gets []. Behind the same gate as the push. An item Shopify refuses drops
 * its mark with a warning; a refused call set nothing, so the others are sent again.
 *
 * @param array{heartbeat?: callable(): bool, graphql?: array<string, mixed>, lager_config?: array<string, mixed>} $rt
 * @param array{id: int, shop_id: int} $job
 * @return array{outcome: string, written?: int, gate?: string, run_after?: int}
 * @throws RuntimeException when Shopify doesn't answer; the marks stay for the retry
 */
function shopify_stock_display(array $rt, array $job, float $deadline): array {
	$shopId = (int)$job['shop_id'];
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1', array($shopId))[0] ?? null;
	if ($shop === null || $shop['stock_publish_enabled'] !== 't' || $shop['stock_baseline_at'] === null) {
		$waiting = $shop !== null && shopify_queue_state_get("stock.display:{$shop['shop_key']}") !== array();
		return $waiting ? array('outcome' => 'yield', 'written' => 0, 'gate' => 'closed', 'run_after' => SHOPIFY_STOCK_DEFERRED_SECONDS)
			: array('outcome' => 'done', 'written' => 0, 'gate' => 'closed');
	}
	$config = $rt['lager_config'] ?? shopify_lager_config();
	$written = 0;
	while (true) {
		if ($deadline - microtime(true) < SHOPIFY_STOCK_MIN_SECONDS || (isset($rt['heartbeat']) && !($rt['heartbeat'])())) {
			return array('outcome' => 'yield', 'written' => $written);
		}
		$set = shopify_queue_state_get("stock.display:{$shop['shop_key']}");
		if ($set === array()) {
			return array('outcome' => 'done', 'written' => $written);
		}
		$batch = array_slice($set, 0, SHOPIFY_STOCK_DISPLAY_BATCH, true);
		$items = array_filter(shopify_stock_plan($shopId, array_map('intval', array_keys($batch)), $config)['items'], fn(array $i): bool => $i['variant_gid'] !== null);
		$metafields = $items === array() ? array() : shopify_stock_display_changes($rt, $shop, $items, $deadline);
		if ($metafields !== array()) {
			$refused = shopify_stock_display_write($rt, $shop, $metafields, $deadline);
			if ($refused !== array()) {
				// dropped whatever their mark, so an item Shopify keeps refusing can't loop until the deadline; a later change marks it again
				shopify_stock_state_update("stock.display:{$shop['shop_key']}", fn(array $marks): array => array_diff_key($marks, $refused));
				continue;
			}
			$written += count($metafields);
		}
		shopify_stock_unmark($shop, $batch, 'stock.display');
	}
}

/**
 * The metafields that differ from what the shop has.
 *
 * @param array<int, array{variant_gid: string, pairs: array<int, array{udstillet: bool, location_gid: string}>}> $items
 * @return list<array{vare_id: int, ownerId: string, namespace: string, key: string, type: string, value: string}>
 * @throws RuntimeException when Shopify doesn't answer
 */
function shopify_stock_display_changes(array $rt, array $shop, array $items, float $deadline): array {
	$ids = array_column($items, 'variant_gid');
	foreach ($items as $item) {
		foreach ($item['pairs'] as $pair) {
			if ($pair['udstillet']) {
				$ids[] = $pair['location_gid'];
			}
		}
	}
	$res = shopify_graphql($shop, 'stockDisplayRead', SHOPIFY_STOCK_DISPLAY_READ, array('ids' => array_values(array_unique($ids))),
		($rt['graphql'] ?? array()) + array('deadline' => $deadline, 'retry_mode' => 'read'));
	if (!$res['ok']) {
		throw new RuntimeException("reading saldi.udstillet failed: {$res['error']}");
	}
	$nodes = array_column(array_filter($res['data']['nodes']), null, 'id');
	$metafields = array();
	foreach ($items as $vareId => $item) {
		if (!isset($nodes[$item['variant_gid']])) {
			continue; // the variant is gone from the shop: the catalogue's to repair, nothing to write on
		}
		$names = array();
		foreach ($item['pairs'] as $pair) {
			if ($pair['udstillet'] && isset($nodes[$pair['location_gid']])) {
				$names[] = (string)$nodes[$pair['location_gid']]['name'];
			}
		}
		sort($names);
		$current = $nodes[$item['variant_gid']]['metafield']['value'] ?? null;
		if ($current === null ? $names !== array() : json_decode((string)$current, true) !== $names) {
			$metafields[] = array('vare_id' => $vareId, 'ownerId' => $item['variant_gid'], 'namespace' => 'saldi', 'key' => 'udstillet', 'type' => 'json',
				'value' => json_encode($names, JSON_UNESCAPED_UNICODE));
		}
	}
	return $metafields;
}

/**
 * Writes the metafields: absolute values, so a resend is safe.
 *
 * @param list<array{vare_id: int, ownerId: string, namespace: string, key: string, type: string, value: string}> $metafields
 * @return array<int, true> the items refused, each with a warning; a call with a refusal set nothing
 * @throws RuntimeException when Shopify doesn't answer, or refuses without naming a metafield
 */
function shopify_stock_display_write(array $rt, array $shop, array $metafields, float $deadline): array {
	$input = array_map(fn(array $m): array => array_diff_key($m, array('vare_id' => true)), $metafields);
	$res = shopify_graphql($shop, 'metafieldsSet', SHOPIFY_STOCK_DISPLAY_SET, array('metafields' => $input),
		($rt['graphql'] ?? array()) + array('deadline' => $deadline, 'retry_mode' => 'idempotent'));
	if ($res['user_errors'] === array()) {
		if (!$res['ok']) {
			throw new RuntimeException("writing saldi.udstillet failed: {$res['error']}");
		}
		return array();
	}
	$refused = array();
	foreach ($res['user_errors'] as $error) {
		$index = isset($error['field'][1]) && $error['field'][0] === 'metafields' ? (int)$error['field'][1] : null;
		if ($index === null || !isset($metafields[$index])) {
			throw new RuntimeException('Shopify refused saldi.udstillet: ' . (string)$error['message']);
		}
		$vareId = $metafields[$index]['vare_id'];
		$refused[$vareId] = true;
		shopify_error_open((int)$shop['id'], 'stock', "item:$vareId", "Shopify refused saldi.udstillet of item $vareId: " . (string)$error['message'], 'warning');
	}
	return $refused;
}

/** The bulk file of one read, below the tenant's temp folder (.ht: never served); a nightly and a manual reconcile can overlap. */
function shopify_stock_reconcile_file(array $shop, string $op): string {
	require_once __DIR__ . '/../../sharedFile.php';
	$dir = dirname(__DIR__, 3) . '/temp/' . ($GLOBALS['db'] ?? '');
	if (!sharedDirectory($dir)) {
		throw new RuntimeException('the tenant temp folder for the stock bulk file cannot be made');
	}
	return "$dir/.ht_shopify_reconcile_{$shop['shop_key']}_" . md5($op) . '.jsonl';
}

/**
 * The stock.reconcile job of one shop, the nightly read that corrects drift toward Saldi (K12). It starts
 * a bulk read, waits for it, then compares the JSONL file in chunks of whole items from a byte offset kept
 * in the job's checkpoint; a pair the file has no level for is one the shop stopped stocking. A pair that differs is
 * corrected by the push: its acknowledgement is forgotten and its item marked, in one transaction.
 * So this job sends no stock call and never changes Saldi. A pair is left alone while its item is
 * marked or behind a barrier, when it was acknowledged after the read started (the file is older
 * than that), and when a call holding it was unanswered as the read started. A pair that differs while Shopify has
 * stock committed there is deferred, not corrected: an unpaid order lowered available and Saldi only counts it once
 * paid, so a correction would put the reserved unit on sale again. What was corrected or deferred goes on the error
 * list as one warning, resolved by the next clean night. The checks run outside the correcting transaction, so the
 * caller holds the worker lock, which keeps a push or remap from committing in between (tools/shopify_worker.php does).
 *
 * @param array{heartbeat?: callable(): bool, graphql?: array<string, mixed>, download?: array<string, mixed>, lines_per_run?: int} $rt
 *   lines_per_run: a test seam, yield after each chunk of this many items
 * @param array{id: int, shop_id: int, checkpoint?: array<string, mixed>|null} $job
 * @return array{outcome: string, checkpoint?: array<string, mixed>, run_after?: int, gate?: string, compared?: int, drifted?: int, deferred?: int}
 * @throws RuntimeException when the read can't be started, polled or downloaded; the job retries
 */
function shopify_stock_reconcile(array $rt, array $job, float $deadline): array {
	$shopId = (int)$job['shop_id'];
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1', array($shopId))[0] ?? null;
	$cp = $job['checkpoint'] ?? array();
	if ($shop === null || $shop['stock_publish_enabled'] !== 't' || $shop['stock_baseline_at'] === null) {
		if ($shop !== null && isset($cp['op'])) {
			@unlink(shopify_stock_reconcile_file($shop, $cp['op']));
		}
		return array('outcome' => 'done', 'gate' => 'closed');
	}
	$graphql = ($rt['graphql'] ?? array()) + array('deadline' => $deadline);
	if (!isset($cp['op'])) {
		$startedAt = shopify_db_rows('SELECT now()::timestamp AS at')[0]['at'];
		$res = shopify_bulk_start($shop, SHOPIFY_STOCK_BULK_LEVELS, $graphql);
		if (!$res['ok'] || ($res['bulk']['id'] ?? null) === null) {
			throw new RuntimeException('starting the stock bulk read failed: ' . (string)$res['error']);
		}
		// a call unanswered now may land after the read saw its pairs; its acknowledgement keeps the read time
		$busy = array_values(array_unique(array_column(shopify_queue_state_get("stock.pending:{$shop['shop_key']}")['rows'] ?? array(), 'vare_id')));
		return array('outcome' => 'yield', 'checkpoint' => array('op' => $res['bulk']['id'], 'started_at' => $startedAt, 'busy' => $busy), 'run_after' => SHOPIFY_STOCK_RECONCILE_POLL);
	}
	$file = shopify_stock_reconcile_file($shop, $cp['op']);
	if (!isset($cp['offset']) || !is_file($file)) {
		$read = shopify_stock_reconcile_download($rt, $shop, $cp['op'], $file, $graphql, $deadline);
		if ($read === 'running') {
			return array('outcome' => 'yield', 'checkpoint' => $cp, 'run_after' => SHOPIFY_STOCK_RECONCILE_POLL);
		}
		if ($read !== 'ready') {
			// tomorrow's slot starts a new read
			shopify_error_open($shopId, 'stock', 'reconcile', "The nightly stock read ended without a file: $read", 'warning');
			return array('outcome' => 'done', 'compared' => 0, 'drifted' => 0);
		}
		$cp += array('offset' => 0, 'compared' => 0, 'drifted' => 0, 'deferred' => 0, 'named' => array(), 'corrected' => array());
	}
	$out = shopify_stock_reconcile_scan($rt, $shop, $file, $cp, shopify_stock_checked_config(), $deadline);
	if ($out !== null) {
		return $out;
	}
	@unlink($file);
	$deferred = $cp['deferred'] ?? 0;
	if ($cp['drifted'] > 0 || $deferred > 0) {
		shopify_error_open($shopId, 'stock', 'reconcile', "The nightly read found {$cp['drifted']} of {$cp['compared']} pairs differing from Saldi and corrects them through the push, and deferred $deferred while Shopify has stock committed there: " . implode('; ', $cp['named']), 'warning');
	} else {
		shopify_error_resolve_key($shopId, 'stock', 'reconcile');
	}
	return array('outcome' => 'done', 'compared' => $cp['compared'], 'drifted' => $cp['drifted'], 'deferred' => $deferred);
}

/**
 * Fetches the finished bulk read to $file; a read with no rows gives an empty file.
 *
 * @return string ready, running, or the reason the read ended without a file
 * @throws RuntimeException when Shopify or the download doesn't answer; the job retries
 */
function shopify_stock_reconcile_download(array $rt, array $shop, string $op, string $file, array $graphql, float $deadline): string {
	$poll = shopify_bulk_poll($shop, $op, $graphql);
	if (!($poll['done'] ?? false)) {
		if (!$poll['ok']) {
			throw new RuntimeException("polling the stock bulk read failed: {$poll['error']}");
		}
		return 'running';
	}
	if (($poll['bulk']['status'] ?? '') !== 'COMPLETED') {
		return (string)($poll['bulk']['status'] ?? $poll['error']) . ' ' . (string)($poll['bulk']['errorCode'] ?? '');
	}
	if (($poll['bulk']['url'] ?? null) === null) {
		file_put_contents($file, '');
		return 'ready';
	}
	$got = shopify_bulk_download($poll['bulk']['url'], $file, ($rt['download'] ?? array()) + array('deadline' => $deadline));
	if (!$got['ok']) {
		throw new RuntimeException("downloading the stock bulk read failed: {$got['error']}");
	}
	return 'ready';
}

/**
 * Compares the file from the checkpoint's offset, in chunks of whole items: an item's line comes before its
 * levels, and a pair with no level is one the shop doesn't stock. Returns the job's yield when it must stop,
 * null at the end of the file.
 *
 * @param array<string, mixed> $cp updated in place
 */
function shopify_stock_reconcile_scan(array $rt, array $shop, string $file, array &$cp, array $config, float $deadline): ?array {
	$fh = fopen($file, 'rb');
	if ($fh === false) {
		throw new RuntimeException('the stock bulk file cannot be read');
	}
	try {
		fseek($fh, (int)$cp['offset']);
		$chunk = min(SHOPIFY_STOCK_RECONCILE_CHUNK, $rt['lines_per_run'] ?? PHP_INT_MAX);
		while (true) {
			if ($deadline - microtime(true) < SHOPIFY_STOCK_MIN_SECONDS || (isset($rt['heartbeat']) && !($rt['heartbeat'])()) || $chunk < 1) {
				return array('outcome' => 'yield', 'checkpoint' => $cp);
			}
			$levels = $held = array();
			while (true) {
				$at = ftell($fh);
				$line = fgets($fh);
				if ($line === false) {
					break;
				}
				$row = json_decode($line, true);
				if (!is_array($row)) {
					throw new RuntimeException('the stock bulk file has a line that is not JSON');
				}
				if (!isset($row['__parentId'])) {
					if (count($levels) >= $chunk) {
						fseek($fh, $at);
						break;
					}
					$levels[(string)$row['id']] = array();
				} elseif (isset($row['location']['id'])) {
					$quantities = array_column($row['quantities'] ?? array(), 'quantity', 'name');
					$levels[$row['__parentId']][$row['location']['id']] = (int)($quantities['available'] ?? 0);
					if ((int)($quantities['committed'] ?? 0) > 0) {
						$held[$row['__parentId']][$row['location']['id']] = (int)$quantities['committed'];
					}
				}
			}
			if ($levels === array()) {
				return null;
			}
			$cp['offset'] = ftell($fh);
			shopify_stock_reconcile_compare($shop, $levels, $cp, $config, $held);
			if (isset($rt['lines_per_run'])) {
				return array('outcome' => 'yield', 'checkpoint' => $cp);
			}
		}
	} finally {
		fclose($fh);
	}
}

/**
 * Compares whole items' levels with what Saldi wants and corrects what differs.
 *
 * @param array<string, array<string, int>> $levels inventory item gid => location gid => available, every item of the chunk
 * @param array<string, mixed> $cp compared, drifted, deferred and named are updated in place
 * @param array<string, array<string, int>> $held inventory item gid => location gid => committed, where above 0
 */
function shopify_stock_reconcile_compare(array $shop, array $levels, array &$cp, array $config, array $held = array()): void {
	$shopId = (int)$shop['id'];
	$ids = array_map(fn(array $r): int => (int)$r['vare_id'], shopify_db_rows(
		'SELECT vare_id FROM shopify_product_links WHERE shop_id = $1 AND variant_id = 0 AND listed AND inventory_item_gid = ANY($2::text[])',
		array($shopId, '{' . implode(',', array_map(fn(string $gid): string => '"' . addcslashes($gid, '"\\') . '"', array_keys($levels))) . '}')
	));
	if ($ids === array()) {
		return;
	}
	$plan = shopify_stock_plan($shopId, $ids, $config);
	$marked = shopify_queue_state_get("stock.dirty:{$shop['shop_key']}");
	$deferred = shopify_stock_deferred($shopId, $ids, $plan['components']);
	$recent = array();
	foreach (shopify_db_rows(
		'SELECT vare_id, lager FROM shopify_stock_state WHERE shop_id = $1 AND variant_id = 0 AND vare_id = ANY($2::int[]) AND acknowledged_at >= $3',
		array($shopId, shopify_barrier_int_array($ids), $cp['started_at'])
	) as $row) {
		$recent[(int)$row['vare_id']][(int)$row['lager']] = true;
	}
	$drift = array();
	foreach ($plan['items'] as $vareId => $item) {
		// a mark this run set doesn't hide the item's other pairs: the push only sends a pair whose acknowledgement is gone
		if ((isset($marked[$vareId]) && !isset($cp['corrected'][$vareId])) || isset($deferred[$vareId]) || in_array($vareId, $cp['busy'] ?? array(), true)) {
			continue;
		}
		foreach ($item['pairs'] as $lager => $pair) {
			if (!isset($levels[$item['inventory_item_gid']])) {
				continue;
			}
			$has = array_key_exists($pair['location_gid'], $levels[$item['inventory_item_gid']]);
			$cp['compared']++;
			if (isset($recent[$vareId][$lager]) || ($has && $levels[$item['inventory_item_gid']][$pair['location_gid']] === $pair['quantity'])) {
				continue;
			}
			$committed = $held[$item['inventory_item_gid']][$pair['location_gid']] ?? 0;
			if ($committed > 0) {
				$cp['deferred'] = ($cp['deferred'] ?? 0) + 1;
				if (count($cp['named']) < SHOPIFY_STOCK_RECONCILE_NAMED) {
					$cp['named'][] = "item $vareId at lager $lager deferred: Shopify {$levels[$item['inventory_item_gid']][$pair['location_gid']]} with $committed committed, Saldi {$pair['quantity']}";
				}
				continue;
			}
			$drift[$vareId][] = $lager;
			$cp['corrected'][$vareId] = true;
			$cp['drifted']++;
			if (count($cp['named']) < SHOPIFY_STOCK_RECONCILE_NAMED) {
				$cp['named'][] = "item $vareId at lager $lager: Shopify " . ($has ? $levels[$item['inventory_item_gid']][$pair['location_gid']] : 'not stocked') . ", Saldi {$pair['quantity']}";
			}
		}
	}
	if ($drift === array()) {
		return;
	}
	shopify_db_transaction(function () use ($shop, $shopId, $drift): void {
		foreach ($drift as $vareId => $lagre) {
			shopify_db_rows('DELETE FROM shopify_stock_state WHERE shop_id = $1 AND vare_id = $2 AND variant_id = 0 AND lager = ANY($3::int[])', array($shopId, $vareId, shopify_barrier_int_array($lagre)));
		}
		shopify_stock_mark($shop, array_keys($drift), (int)shopify_db_rows('SELECT txid_current() AS x')[0]['x']);
	});
}

/**
 * Queues the shop's stock baseline (K19), for go-live day: capture armed, publishing off, no baseline
 * yet. The shop's other flows don't matter. A baseline already queued or running is returned as it is.
 *
 * @param array<string, mixed> $rt unused; the caller's runtime
 * @return int the job's id
 * @throws ShopifyConfigError when the shop isn't in that state
 */
function shopify_stock_start_baseline(array $rt, int $shopId): int {
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1', array($shopId))[0] ?? null;
	$problem = $shop === null ? "shop $shopId is unknown" : shopify_stock_baseline_problem($shop);
	if ($problem !== null) {
		throw new ShopifyConfigError("no stock baseline: $problem");
	}
	return (int)shopify_queue_enqueue('stock.baseline', $shopId, "{$shop['shop_key']}:baseline", array(), SHOPIFY_QUEUE_PRIORITY['stock.baseline'])['id'];
}

/** Why the shop can't run a baseline, null when it can. */
function shopify_stock_baseline_problem(array $shop): ?string {
	if ($shop['stock_baseline_at'] !== null) {
		return "shop {$shop['shop_key']} has one from {$shop['stock_baseline_at']}";
	}
	if ($shop['stock_capture_enabled'] !== 't') {
		return "shop {$shop['shop_key']} doesn't capture stock, so a change during the baseline would be lost";
	}
	if ($shop['stock_publish_enabled'] === 't') {
		return "shop {$shop['shop_key']} already publishes stock; it starts after the baseline";
	}
	return null;
}

/**
 * The stock.baseline job of one shop: forgets what Shopify acknowledged, marks every linked item, and sends
 * them all through the shop's pending slot, with chunks as long as a worker pass. It bypasses the
 * publish gate; ordinary pushes stay blocked on stock_baseline_at. Changes during the baseline
 * are drained into the same marks and go with it. stock_baseline_at is stamped only when every pair
 * has an acknowledgement of Saldi's quantity, or its item is marked or dirty again; otherwise one error lists what's missing, the stamp stays unset and
 * the baseline can be run again.
 *
 * @param array{heartbeat?: callable(): bool, graphql?: array<string, mixed>} $rt
 * @param array{id: int, shop_id: int, checkpoint?: array<string, mixed>|null} $job
 * @return array{outcome: string, sent?: int, checkpoint?: array<string, mixed>, run_after?: int, stamped?: bool, missing?: int}
 * @throws RuntimeException when a call fails; the pending mutation stays for the retry
 */
function shopify_stock_baseline(array $rt, array $job, float $deadline): array {
	$shopId = (int)$job['shop_id'];
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1', array($shopId))[0] ?? null;
	if ($shop === null) {
		return array('outcome' => 'done', 'stamped' => false);
	}
	$problem = shopify_stock_baseline_problem($shop);
	if ($problem !== null) {
		shopify_error_open($shopId, 'stock', 'baseline', "The stock baseline stopped: $problem");
		return array('outcome' => 'done', 'stamped' => false);
	}
	if (empty($job['checkpoint']['prepared'])) {
		shopify_db_transaction(function () use ($shop, $shopId): void {
			shopify_db_rows('DELETE FROM shopify_stock_state WHERE shop_id = $1', array($shopId));
			$rows = shopify_db_rows('SELECT vare_id FROM shopify_product_links WHERE shop_id = $1 AND variant_id = 0 AND listed AND inventory_item_gid IS NOT NULL', array($shopId));
			shopify_stock_mark($shop, array_map('intval', array_column($rows, 'vare_id')), (int)shopify_db_rows('SELECT txid_current() AS x')[0]['x']);
		});
		return array('outcome' => 'yield', 'checkpoint' => array('prepared' => true));
	}
	$rt['lager_config'] = shopify_stock_checked_config();
	$sending = shopify_stock_send($rt, $job, $shop, $deadline);
	if ($sending['outcome'] !== 'done') {
		return $sending + array('checkpoint' => array('prepared' => true));
	}
	$missing = shopify_stock_baseline_missing($shopId, $rt['lager_config']);
	if ($missing !== array()) {
		shopify_error_open($shopId, 'stock', 'baseline', 'The stock baseline is not complete, so stock_baseline_at stays unset; ' . count($missing) . ' missing: ' . implode('; ', array_slice($missing, 0, SHOPIFY_STOCK_RECONCILE_NAMED)));
		return array('outcome' => 'done', 'sent' => $sending['sent'], 'stamped' => false, 'missing' => count($missing));
	}
	shopify_db_transaction(function () use ($shop, $shopId): void {
		shopify_save_shop(array('shop_key' => $shop['shop_key'], 'stock_baseline_at' => shopify_db_rows('SELECT now()::timestamp AS at')[0]['at']));
		shopify_error_resolve_key($shopId, 'stock', 'baseline');
	});
	return array('outcome' => 'done', 'sent' => $sending['sent'], 'stamped' => true, 'missing' => 0);
}

/**
 * What keeps a shop's baseline from being complete: a pair without an acknowledgement, or acknowledged at
 * another quantity than Saldi's while its item isn't marked or dirty (a refusal dropped the mark), a set that
 * can't be published or a sellable lager without a location. A display lager without one doesn't count.
 *
 * @return list<string> a line per problem, none when every pair is acknowledged
 */
function shopify_stock_baseline_missing(int $shopId, array $config): array {
	$missing = array();
	$after = 0;
	while (true) {
		$ids = array_map(fn(array $r): int => (int)$r['vare_id'], shopify_db_rows(
			'SELECT vare_id FROM shopify_product_links WHERE shop_id = $1 AND variant_id = 0 AND listed AND inventory_item_gid IS NOT NULL AND vare_id > $2 ORDER BY vare_id LIMIT $3',
			array($shopId, $after, SHOPIFY_STOCK_SLICE)
		));
		if ($ids === array()) {
			return array_values($missing);
		}
		$after = end($ids);
		$plan = shopify_stock_plan($shopId, $ids, $config);
		$state = shopify_stock_acknowledged($shopId, $ids);
		$marked = shopify_queue_state_get('stock.dirty:' . shopify_db_rows('SELECT shop_key FROM shopify_shops WHERE id = $1', array($shopId))[0]['shop_key']);
		// a change not drained yet becomes a mark after the stamp just the same
		foreach (shopify_db_rows('SELECT DISTINCT vare_id FROM shopify_stock_dirty WHERE vare_id = ANY($1::int[])', array(shopify_barrier_int_array($ids))) as $row) {
			$marked[(int)$row['vare_id']] = true;
		}
		foreach ($plan['unmapped'] as $lager) {
			$missing["lager $lager"] = "sellable lager $lager has no location";
		}
		foreach ($ids as $vareId) {
			if (!isset($plan['items'][$vareId]) || $plan['items'][$vareId]['invalid']) {
				$missing[] = "item $vareId can't be published";
				continue;
			}
			foreach ($plan['items'][$vareId]['pairs'] as $lager => $pair) {
				if (!isset($state[$vareId][$lager])) {
					$missing[] = "item $vareId at lager $lager has no acknowledgement";
				} elseif ($state[$vareId][$lager] !== $pair['quantity'] && !isset($marked[$vareId])) {
					// a refusal dropped the mark of a later change; a marked item still goes out with the push
					$missing[] = "item $vareId at lager $lager: acknowledged {$state[$vareId][$lager]}, Saldi {$pair['quantity']}";
				}
			}
		}
	}
}

/**
 * Zeroes a lager's location in the shop before the mapping changes, so a retired location keeps no
 * stock to sell, then in one transaction forgets the lager's acknowledgements, moves the mapping
 * and marks the items, so the next push sends the stock to the new location. Without a new
 * location the lager just loses its mapping. Before Saldi has written to the shop's stock (no baseline job,
 * no acknowledgement at the lager), the location is the legacy integration's and only the mapping moves. The caller holds the worker lock, as a push running
 * alongside could send to the old location again (tools/shopify_worker.php remap does). Goes through the
 * shop's pending slot: a call already stored there is sent first, and one waiting for a barrier stops the
 * remap. An item Shopify refuses goes on the error list and the mapping stays, as that item could still sell at
 * the old location.
 *
 * @param array{graphql?: array<string, mixed>, deadline?: float} $rt
 * @param string|null $newLocationGid the lager's new location
 * @return int how many pairs were zeroed
 * @throws ShopifyConfigError when the lager isn't mapped, or the new location is the old one, another lager's or not the shop's
 * @throws RuntimeException when Shopify doesn't answer or refused an item; run it again
 */
function shopify_stock_remap(array $rt, int $shopId, int $lager, ?string $newLocationGid = null): int {
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1', array($shopId))[0] ?? null;
	$old = $shop === null ? null : shopify_location($shopId, $lager);
	if ($old === null) {
		throw new ShopifyConfigError("lager $lager has no location in shop $shopId");
	}
	$new = $newLocationGid === null ? null : shopify_gid($newLocationGid, 'Location');
	if ($new === $old) {
		throw new ShopifyConfigError("lager $lager is already mapped to $old");
	}
	if ($new !== null && shopify_db_rows('SELECT lager FROM shopify_location_map WHERE shop_id = $1 AND location_gid = $2', array($shopId, $new)) !== array()) {
		throw new ShopifyConfigError("$new is already mapped to another lager in shop $shopId");
	}
	if ($new !== null) {
		$found = shopify_graphql($shop, 'stockRemapLocation', 'query stockRemapLocation($id: ID!) { location(id: $id) { id } }', array('id' => $new),
			($rt['graphql'] ?? array()) + array('deadline' => (float)($rt['deadline'] ?? microtime(true) + 60), 'retry_mode' => 'read'));
		if (!$found['ok']) {
			throw new RuntimeException("reading location $new failed: {$found['error']}");
		}
		if (($found['data']['location']['id'] ?? null) !== $new) {
			throw new ShopifyConfigError("$new is not a location of shop {$shop['shop_key']}");
		}
	}
	// a call already stored goes first, also one of a baseline that hasn't been answered yet: it may write the old location
	$stored = shopify_queue_state_get("stock.pending:{$shop['shop_key']}");
	if ($stored !== array()) {
		if (shopify_barrier_deferred($shopId, $stored['watch'] ?? array_keys($stored['marks'])) !== array()) {
			throw new RuntimeException("the shop's stored stock call waits for a move barrier; lager $lager keeps its mapping, run the remap again when it has gone");
		}
		shopify_stock_call($rt, array('id' => null), $shop, $stored, (float)($rt['deadline'] ?? microtime(true) + 3600));
	}
	// Saldi has written to the location once a baseline started (its preparation forgets every acknowledgement, so
	// a retry leaves none) or a call was acknowledged there; before that its stock is the legacy integration's, so only the mapping moves
	$written = $shop['stock_baseline_at'] !== null
		|| shopify_db_rows("SELECT 1 FROM shopify_jobs WHERE shop_id = $1 AND type = 'stock.baseline' LIMIT 1", array($shopId)) !== array()
		|| shopify_db_rows('SELECT 1 FROM shopify_stock_state WHERE shop_id = $1 AND lager = $2 LIMIT 1', array($shopId, $lager)) !== array();
	$zeroed = $written ? shopify_stock_remap_zero($rt, $shop, $lager, $old) : 0;
	shopify_stock_remap_finish($shop, $lager, $new);
	return $zeroed;
}

/**
 * Sets the old location to 0 for every listed item. On a failure after a zero call was stored, the items it
 * covers show, or will show once it is resent, 0 with an acknowledgement saying so, so the lager's acknowledgements are forgotten and the items marked: the push
 * restores them at the location the mapping still names.
 *
 * @return int pairs zeroed
 * @throws RuntimeException when Shopify doesn't answer, or refused an item or a barrier holds the stored call
 */
function shopify_stock_remap_zero(array $rt, array $shop, int $lager, string $old): int {
	$shopId = (int)$shop['id'];
	$deadline = (float)($rt['deadline'] ?? microtime(true) + 3600);
	$job = array('id' => null);
	$slot = "stock.pending:{$shop['shop_key']}";
	$zeroed = $after = 0;
	$refused = array();
	$tried = false;
	try {
		while (true) {
			$links = shopify_db_rows(
				'SELECT vare_id, inventory_item_gid FROM shopify_product_links
				 WHERE shop_id = $1 AND variant_id = 0 AND listed AND inventory_item_gid IS NOT NULL AND vare_id > $2 AND NOT vare_id = ANY($3::int[]) ORDER BY vare_id LIMIT $4',
				array($shopId, $after, shopify_barrier_int_array($refused), SHOPIFY_STOCK_BATCH)
			);
			if ($links === array()) {
				break;
			}
			$pending = array(
				'vars' => array('key' => shopify_stock_key(), 'input' => array('name' => 'available', 'reason' => 'correction', 'quantities' => array_map(fn(array $l): array
					=> array('inventoryItemId' => $l['inventory_item_gid'], 'locationId' => $old, 'quantity' => 0, 'changeFromQuantity' => null), $links))),
				'rows' => array_map(fn(array $l): array => array('vare_id' => (int)$l['vare_id'], 'lager' => $lager, 'quantity' => 0), $links),
				'marks' => array(),
				'watch' => array_map(fn(array $l): int => (int)$l['vare_id'], $links),
				'read_at' => shopify_db_rows('SELECT now()::timestamp AS at')[0]['at'],
			);
			$pending = shopify_stock_state_update($slot, fn(array $current): array => $current ?: $pending);
			$tried = true;
			$named = shopify_stock_call($rt, $job, $shop, $pending, $deadline);
			if ($named !== array()) {
				array_push($refused, ...$named);
				continue;
			}
			$zeroed += count($links);
			$after = (int)end($links)['vare_id'];
		}
		if ($refused !== array()) {
			throw new RuntimeException('Shopify refused to zero item ' . implode(', ', $refused) . " at $old; the mapping of lager $lager stays and the push restores its stock");
		}
	} catch (Throwable $e) {
		// also after a zero call with no answer: it stays stored, and once the push resends it the marks restore the items
		if ($tried) {
			shopify_stock_remap_finish($shop, $lager, false);
		}
		throw $e;
	}
	return $zeroed;
}

/**
 * Forgets the lager's acknowledgements, moves the mapping (false: keeps it) and marks the shop's listed items, in one transaction.
 *
 * @param string|false|null $new the new location, null to unmap, false to keep the mapping
 */
function shopify_stock_remap_finish(array $shop, int $lager, string|false|null $new): void {
	$shopId = (int)$shop['id'];
	shopify_db_transaction(function () use ($shop, $shopId, $lager, $new): void {
		shopify_db_rows('DELETE FROM shopify_stock_state WHERE shop_id = $1 AND lager = $2', array($shopId, $lager));
		if ($new === null) {
			shopify_db_rows('DELETE FROM shopify_location_map WHERE shop_id = $1 AND lager = $2', array($shopId, $lager));
		} elseif ($new !== false) {
			shopify_save_map($shopId, $lager, $new);
		}
		if ($shop['stock_capture_enabled'] === 't') {
			$rows = shopify_db_rows('SELECT vare_id FROM shopify_product_links WHERE shop_id = $1 AND variant_id = 0 AND listed AND inventory_item_gid IS NOT NULL', array($shopId));
			shopify_stock_mark($shop, array_map('intval', array_column($rows, 'vare_id')), (int)shopify_db_rows('SELECT txid_current() AS x')[0]['x']);
		}
	});
}
