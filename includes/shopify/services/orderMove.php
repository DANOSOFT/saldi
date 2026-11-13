<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/orderMove.php --- ver 5.1.0 --- 2026.11.13
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
// Moving an invoiced web order's fulfillment-order lines to the location Saldi drew them from
// (implementation plan 2.7, WP10-2, K17). The import stores what it wrote per Shopify line in
// shopify_order_links.flags under allocation_v1; order.move compares that with the fulfillment
// orders Shopify has now and moves the difference, a line or part of one at a time.
//
// fulfillmentOrderMove is never resent blind: before every move the fulfillment orders are read
// again and the plan is made afresh, so a move whose answer was lost is found done, and the ids a
// partial move recreates are picked up. The order's move barriers are released when nothing is
// left to move. A move that fails three times, or is still open after SHOPIFY_BARRIER_MINUTES,
// is abandoned: the barriers are expired so the stock push proceeds, an alarm is raised, and the
// move list (moveList.php) still shows the lines.
//
// 20261113 CL/MJ SD-760: created (WP10-2).

require_once __DIR__ . '/../noteAppend.php';
require_once __DIR__ . '/../errors.php';
require_once __DIR__ . '/stock.php';

/** Fulfillment orders read at most, one per read as the intake reads them (each read under the query cost limit). */
const SHOPIFY_MOVE_MAX_FULFILLMENT_ORDERS = 30;

/** Moves one job attempt sends at most before it yields; the next attempt carries on from a fresh read. */
const SHOPIFY_MOVE_ROUNDS = 10;

/** Seconds a round needs (two reads and a move); with less left in the pass the job yields. */
const SHOPIFY_MOVE_MIN_SECONDS = 10;

const SHOPIFY_MOVE_READ = 'query orderMoveRead($id: ID!, $after: String) { order(id: $id) {
	fulfillmentOrders(first: 1, after: $after) { pageInfo { hasNextPage endCursor } nodes { id status
		assignedLocation { location { id } } supportedActions { action }
		lineItems(first: 250) { pageInfo { hasNextPage } nodes { id remainingQuantity lineItem { id } } } } }
} }';

const SHOPIFY_MOVE_MUTATION = 'mutation orderMove($id: ID!, $to: ID!, $lines: [FulfillmentOrderLineItemInput!]) {'
	. ' fulfillmentOrderMove(id: $id, newLocationId: $to, fulfillmentOrderLineItems: $lines) { movedFulfillmentOrder { id } userErrors { field message } } }';

/**
 * The moves that put each Shopify line where Saldi drew it from (pure). A line is planned only
 * when every lager it was drawn from is one of $locations, its quantities are whole items, and
 * Shopify still has exactly that quantity open: anything else is left where it is, and the move
 * list carries it. A line drawn from a sellable lager without a location is unresolved. A split
 * set stays one line in Shopify, on the set's own lager.
 *
 * @param array{lines?: list<array<string, mixed>>} $alloc the link's allocation_v1
 * @param list<array<string, mixed>> $fulfillmentOrders as SHOPIFY_MOVE_READ returns the nodes
 * @param array<int, string> $locations lager => location gid, the sellable lagre only ('' when the shop has no location for one)
 * @return array{moves: list<array{fulfillment_order: string, to: string, lines: list<array{id: string, quantity: int}>}>,
 *   unresolved: list<string>} unresolved: the lines that need a move no movable fulfillment order can make
 */
function shopify_plan_moves(array $alloc, array $fulfillmentOrders, array $locations): array {
	$open = array();
	foreach ($fulfillmentOrders as $fo) {
		// an order on hold or scheduled still holds its lines there; without MOVE among its actions it is unresolved
		if (!in_array(strtoupper((string)($fo['status'] ?? '')), array('OPEN', 'IN_PROGRESS', 'ON_HOLD', 'SCHEDULED'), true)) {
			continue;
		}
		$at = (string)($fo['assignedLocation']['location']['id'] ?? '');
		$movable = in_array('MOVE', array_column((array)($fo['supportedActions'] ?? array()), 'action'), true);
		foreach ($fo['lineItems']['nodes'] ?? array() as $li) {
			$gid = (string)($li['lineItem']['id'] ?? '');
			if ((int)($li['remainingQuantity'] ?? 0) > 0 && $gid !== '') {
				$open[$gid][$at][] = array('fo' => (string)$fo['id'], 'id' => (string)$li['id'], 'qty' => (int)$li['remainingQuantity'], 'movable' => $movable);
			}
		}
	}
	$moves = array();
	$unresolved = array();
	foreach ($alloc['lines'] ?? array() as $line) {
		$gid = (string)($line['line_gid'] ?? '');
		$want = shopify_move_wanted((array)$line, $locations);
		if ($want === null || !isset($open[$gid])) {
			continue;
		}
		if ($want === array()) {
			$unresolved[$gid] = $gid;
			continue;
		}
		$have = array_map(static fn(array $parts): int => array_sum(array_column($parts, 'qty')), $open[$gid]);
		if (array_sum($have) !== array_sum($want)) {
			continue;
		}
		foreach ($want as $to => $need) {
			$deficit = $need - ($have[$to] ?? 0);
			foreach ($open[$gid] as $from => $parts) {
				foreach (array_keys($parts) as $i) {
					$part = $open[$gid][$from][$i];
					$take = min($deficit, ($have[$from] ?? 0) - ($want[$from] ?? 0), $part['qty']);
					if ((string)$from === (string)$to || $take <= 0 || !$part['movable']) {
						continue;
					}
					$key = $part['fo'] . ' ' . $to;
					$moves[$key]['fulfillment_order'] = $part['fo'];
					$moves[$key]['to'] = (string)$to;
					$moves[$key]['lines'][] = array('id' => $part['id'], 'quantity' => $take);
					$open[$gid][$from][$i]['qty'] -= $take;
					$have[$from] -= $take;
					$have[$to] = ($have[$to] ?? 0) + $take;
					$deficit -= $take;
				}
			}
			if ($deficit > 0) {
				$unresolved[$gid] = $gid;
			}
		}
	}
	return array('moves' => array_values($moves), 'unresolved' => array_values($unresolved));
}

/**
 * Whole items per location gid for one allocation_v1 line, null when it can't be planned: a
 * lager that is not sellable (display, ingen), or part of an item on a lager. An empty array when
 * it was drawn from a sellable lager the shop has no location for.
 *
 * @param array<string, mixed> $line
 * @param array<int, string> $locations
 * @return array<string, int>|null
 */
function shopify_move_wanted(array $line, array $locations): ?array {
	$milli = array();
	if (!empty($line['set'])) {
		$lager = $line['lager'] ?? null;
		foreach ($lager === null ? (array)($line['rows'] ?? array()) : array() as $row) {
			$lager = !empty($row['set_line']) ? $row['lager'] : $lager;
		}
		$milli[(int)$lager] = 1000 * (int)($line['quantity'] ?? 0);
	} else {
		foreach ((array)($line['rows'] ?? array()) as $row) {
			$lager = (int)($row['lager'] ?? 0);
			$milli[$lager] = ($milli[$lager] ?? 0) + (int)($row['quantity_milli'] ?? 0);
		}
	}
	$want = array();
	foreach ($milli as $lager => $quantity) {
		if (($locations[$lager] ?? null) === '') {
			return array();
		}
		if (!isset($locations[$lager]) || $quantity <= 0 || $quantity % 1000 !== 0) {
			return null;
		}
		$want[$locations[$lager]] = intdiv($quantity, 1000);
	}
	return $want ?: null;
}

/**
 * Moves until the plan is empty. Every round reads the fulfillment orders first, so a move whose
 * answer was lost (outcome uncertain) is never sent again unread.
 *
 * @param array<string, string|null> $shop
 * @param array{lines?: list<array<string, mixed>>} $alloc
 * @param array<int, string> $locations
 * @param array{deadline?: float, graphql?: array<string, mixed>, held?: callable(): bool} $rt held: false when the
 *   order's hold would end before a move sent now is answered
 * @return array{status: string, moved: int, errors: list<string>} status done, failed, expired (held said no before a
 *   move), or yield when the pass's time or SHOPIFY_MOVE_ROUNDS ran out before the moves were done (not a failed attempt)
 */
function shopify_run_moves(array $shop, string $orderGid, array $alloc, array $locations, array $rt = array()): array {
	$orderGid = shopify_gid($orderGid, 'Order');
	$moved = 0;
	for ($round = 0; $round < SHOPIFY_MOVE_ROUNDS; $round++) {
		if ((float)($rt['deadline'] ?? INF) - microtime(true) < SHOPIFY_MOVE_MIN_SECONDS) {
			return array('status' => 'yield', 'moved' => $moved, 'errors' => array());
		}
		$read = shopify_move_read($shop, $orderGid, $rt);
		if ($read['errors']) {
			return array('status' => 'failed', 'moved' => $moved, 'errors' => $read['errors']);
		}
		$plan = shopify_plan_moves($alloc, $read['fulfillment_orders'], $locations);
		if (!$plan['moves']) {
			$errors = $plan['unresolved'] ? array('no movable fulfillment order holds ' . implode(', ', $plan['unresolved'])) : array();
			return array('status' => $errors ? 'failed' : 'done', 'moved' => $moved, 'errors' => $errors);
		}
		if (isset($rt['held']) && !$rt['held']()) {
			return array('status' => 'expired', 'moved' => $moved, 'errors' => array('the hold expires before the move would be done'));
		}
		$move = $plan['moves'][0];
		$opts = shopify_writeback_opts($rt, array(
			'retry_mode' => 'reconcile',
			// the next round reads the fulfillment orders before anything else is sent
			'reconcile' => static fn(array $attempt): array => array('state' => 'unknown'),
			'correlation' => array('order_gid' => $orderGid),
		));
		$res = shopify_graphql($shop, 'fulfillmentOrderMove', SHOPIFY_MOVE_MUTATION,
			array('id' => $move['fulfillment_order'], 'to' => $move['to'], 'lines' => $move['lines']), $opts);
		if (!$res['ok'] && $res['outcome'] !== 'uncertain') {
			return array('status' => 'failed', 'moved' => $moved, 'errors' => shopify_graphql_messages($res) ?: array("fulfillmentOrderMove failed ({$res['error']})"));
		}
		$moved++;
	}
	return array('status' => 'yield', 'moved' => $moved, 'errors' => array());
}

/**
 * The order's fulfillment orders as they are now, one read each.
 *
 * @param array<string, string|null> $shop
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt
 * @return array{fulfillment_orders: list<array<string, mixed>>, errors: list<string>}
 */
function shopify_move_read(array $shop, string $orderGid, array $rt): array {
	$nodes = array();
	$after = null;
	for ($page = 0; $page < SHOPIFY_MOVE_MAX_FULFILLMENT_ORDERS; $page++) {
		$res = shopify_graphql($shop, 'orderMoveRead', SHOPIFY_MOVE_READ, array('id' => $orderGid, 'after' => $after),
			shopify_writeback_opts($rt, array('retry_mode' => 'read', 'correlation' => array('order_gid' => $orderGid))));
		$fos = $res['data']['order']['fulfillmentOrders'] ?? null;
		if (!$res['ok'] || !is_array($fos)) {
			return array('fulfillment_orders' => array(), 'errors' => shopify_graphql_messages($res) ?: array("the fulfillment orders of $orderGid could not be read"));
		}
		foreach ($fos['nodes'] ?? array() as $fo) {
			if (!empty($fo['lineItems']['pageInfo']['hasNextPage'])) {
				return array('fulfillment_orders' => array(), 'errors' => array("a fulfillment order of $orderGid has more than 250 lines"));
			}
			$nodes[] = $fo;
		}
		if (empty($fos['pageInfo']['hasNextPage'])) {
			return array('fulfillment_orders' => $nodes, 'errors' => array());
		}
		$after = $fos['pageInfo']['endCursor'] ?? null;
	}
	return array('fulfillment_orders' => array(), 'errors' => array("$orderGid has more than " . SHOPIFY_MOVE_MAX_FULFILLMENT_ORDERS . ' fulfillment orders'));
}

/**
 * The sellable lagre and the shop's location for each, the only places a move goes to.
 *
 * @return array<int, string> lager => location gid, '' when the shop has none for it
 */
function shopify_move_locations(int $shopId): array {
	$rows = shopify_db_rows(
		"SELECT l.lager, coalesce(m.location_gid, '') AS location_gid FROM shopify_lager l
		LEFT JOIN shopify_location_map m ON m.lager = l.lager AND m.shop_id = $1 WHERE l.rolle = 'saelgbar'",
		array($shopId)
	);
	$locations = array();
	foreach ($rows as $row) {
		$locations[(int)$row['lager']] = (string)$row['location_gid'];
	}
	return $locations;
}

/**
 * The order.move stage, run by shopify_writeback_job() once order.tags is done. An order
 * imported before allocation_v1 existed has nothing to compare with and moves nothing.
 *
 * @param array<string, mixed> $job
 * @param array<string, string|null> $link
 * @param array<string, string|null> $shop
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt
 * @return array{outcome: string}
 * @throws ShopifyWritebackError when the move failed and is not abandoned yet (the queue retries it)
 */
function shopify_move_job(array $job, array $link, array $shop, array $rt): array {
	$shopId = (int)$link['shop_id'];
	$ordreId = (int)$link['ordre_id'];
	$alloc = json_decode((string)$link['flags'], true)['allocation_v1'] ?? null;
	try {
		// once the barrier has expired its stock is published again, so nothing is moved after that
		if (shopify_move_expired($shopId, $ordreId)) {
			$result = array('status' => 'expired', 'moved' => 0, 'errors' => array('the hold expired before the move was done'));
		} elseif (is_array($alloc)) {
			// a move is sent only while the hold outlasts the pass: no request is answered after the pass's deadline
			$rt['held'] = static fn(): bool => !shopify_move_expired($shopId, $ordreId, max(0.0, (float)($rt['deadline'] ?? 0) - microtime(true)));
			$result = shopify_run_moves($shop, (string)$link['order_gid'], $alloc, shopify_move_locations($shopId), $rt);
		} else {
			$result = array('status' => 'done', 'moved' => 0, 'errors' => array());
		}
	} catch (RuntimeException $e) {
		// a missing token or a database error is a failed attempt too, so the move is still abandoned in the end
		$result = array('status' => 'failed', 'moved' => 0, 'errors' => array(get_class($e) . ': ' . $e->getMessage()));
	}
	if ($result['status'] === 'yield' && !shopify_move_expired($shopId, $ordreId)) {
		return array('outcome' => 'yield', 'run_after' => 1);
	}
	if ($result['status'] === 'done') {
		shopify_db_transaction(function () use ($link, $shopId, $ordreId): void {
			shopify_barrier_release($shopId, $ordreId);
			shopify_error_resolve_key($shopId, 'order.move', (string)$ordreId);
			shopify_writeback_mark((int)$link['id'], 'order.move', 'done');
		});
		return array('outcome' => 'done');
	}
	$why = $result['errors'] ? implode('; ', $result['errors']) : 'it did not finish in time';
	if ($result['status'] !== 'expired' && (int)($job['attempts'] ?? 1) < SHOPIFY_BARRIER_MOVE_ATTEMPTS && !shopify_move_expired($shopId, $ordreId)) {
		throw new ShopifyWritebackError("order.move on order {$link['order_gid']} failed: $why");
	}
	shopify_db_transaction(function () use ($link, $shopId, $ordreId, $why): void {
		shopify_barrier_expire($shopId, $ordreId);
		shopify_move_alarm($shopId, $ordreId, "the move of web order {$link['order_name']} was abandoned ($why); the move list shows its lines");
		shopify_writeback_mark((int)$link['id'], 'order.move', 'abandoned');
	});
	return array('outcome' => 'done');
}

/** True when the order's barriers are past SHOPIFY_BARRIER_MINUTES (or will be within $seconds) or the sweep has abandoned them. */
function shopify_move_expired(int $shopId, int $ordreId, float $seconds = 0.0): bool {
	$row = shopify_db_rows(
		"SELECT count(*) AS n, count(*) FILTER (WHERE state = 'open' AND expires_at > now() + make_interval(secs => $3)) AS live
		FROM shopify_move_barriers WHERE shop_id = $1 AND ordre_id = $2",
		array($shopId, $ordreId, $seconds)
	)[0];
	return (int)$row['n'] > 0 && (int)$row['live'] === 0;
}

/** One alarm per order, resolved when its move is done after all. */
function shopify_move_alarm(int $shopId, int $ordreId, string $message): void {
	shopify_error_open($shopId, 'order.move', (string)$ordreId, $message, 'error', array('ordre_id' => $ordreId));
}

/**
 * The barrier.sweep job: abandons the shop's barriers past SHOPIFY_BARRIER_MINUTES, which marks
 * their items dirty so the stock push sends them, and raises an alarm per invoiced web order. A
 * barrier is normally released or expired by its order's move; this catches the orders whose move
 * never ran. An order cancelled or not invoiced has no move to wait for, so it gets no alarm.
 *
 * @param array<string, mixed> $job
 * @return array{outcome: string}
 */
function shopify_barrier_sweep_job(array $job, float $deadline, callable $heartbeat): array {
	$shopId = (int)$job['shop_id'];
	shopify_db_transaction(function () use ($shopId): void {
		foreach (shopify_barrier_expire($shopId) as $order) {
			$waiting = shopify_db_rows("SELECT 1 FROM shopify_order_links WHERE shop_id = $1 AND ordre_id = $2 AND direction = 'web'
				AND state = 'invoiced' AND cancelled_at IS NULL", array($shopId, $order['ordre_id']));
			if (!$waiting) {
				continue;
			}
			shopify_move_alarm($shopId, $order['ordre_id'], "order {$order['ordre_id']} held its items' stock for " . SHOPIFY_BARRIER_MINUTES
				. ' minutes without its move finishing; the stock push goes ahead and the move list shows its lines');
		}
	});
	return array('outcome' => 'done');
}
