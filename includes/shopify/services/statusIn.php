<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/statusIn.php --- ver 5.1.0 --- 2026.11.03
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
// Inbound order status (WP08-2, K40, K41, K46 projection): fulfillments with their tracking,
// the order's financial and fulfillment status, cancellations and refunds, projected onto the
// order's link. Projection only (K40): nothing here posts, mails, moves stock or writes an
// order, line, batch, open post or ledger row. An event whose order has no Saldi order yet is
// deferred, not failed, for up to 48 hours; a projection keeps the newest data whatever order
// the webhooks arrive in, ordered by the body's own updated_at (K41).
//
// 20261103 CL/SS SD-801: created (WP08-2).
// 20261103 CL/SS SD-801 review: an event is listed once; an applied event resolves status.unlinked.
// 20261103 CL/SS SD-801 review: a link cancelled before the import is not waited for.
// 20261103 CL/SS SD-801 review: a cancellation in a legacy shop or with the orders flow off is a warning.
// 20261103 CL/SS SD-801 review 2: a cancelled link resolves status.unlinked; the legacy warning needs an open intake.
// 20261103 CL/SS SD-801 review 2: the flow-off warning has its own ref and is resolved once the cancellation is handled.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../links.php';
require_once __DIR__ . '/../errors.php';
require_once __DIR__ . '/../events.php';
require_once __DIR__ . '/../coexistence.php';

/** How long an event waits for its Saldi order, from when it was received, and how often it looks. */
const SHOPIFY_STATUS_DEFER_HOURS = 48;
const SHOPIFY_STATUS_DEFER_SECONDS = 300;

/** The event's order has no Saldi order yet: the job yields and tries again (not a failed attempt). */
final class ShopifyOrderLinkPending extends RuntimeException
{
}

/**
 * The link of the event's order with its Saldi order, when it has one (and $ready accepts it);
 * an open status.unlinked of the order and job type is resolved then. A link cancelled before
 * the import never gets an order: null at once, nothing listed, and an open status.unlinked is
 * resolved too, so the list does not depend on which came first. Else, within 48 hours of the
 * event's arrival, throws ShopifyOrderLinkPending so the job is deferred; after that it lists the
 * event once (status.unlinked, one row per job type and order) and returns null, and the event
 * is not applied. An order.status event is listed as info, since an unpaid order is never
 * imported; the others as warning.
 *
 * @param array{shop_id: int, event_row_id: int, topic: string} $event
 * @param callable(array<string, string|null>): bool|null $ready
 * @return array<string, string|null>|null the shopify_order_links row
 * @throws ShopifyOrderLinkPending
 */
function shopify_require_order_link(array $event, string $orderGid, ?callable $ready = null): ?array {
	$shopId = (int)$event['shop_id'];
	$type = (string)shopify_event_job_type((string)$event['topic']);
	$link = shopify_order_link($shopId, $orderGid);
	if ($link !== null && $link['ordre_id'] !== null && ($ready === null || $ready($link))) {
		shopify_error_resolve_key($shopId, 'status.unlinked', "$type:$orderGid");
		return $link;
	}
	if ($link !== null && $link['state'] === 'cancelled' && $link['ordre_id'] === null) {
		shopify_error_resolve_key($shopId, 'status.unlinked', "$type:$orderGid");
		error_log("shopify_status skipped: $type event {$event['event_row_id']} of $orderGid, cancelled before it was imported");
		return null;
	}
	$rows = shopify_db_rows('SELECT received_at <= now() - make_interval(hours => $2) AS expired FROM shopify_events WHERE id = $1',
		array((int)$event['event_row_id'], SHOPIFY_STATUS_DEFER_HOURS));
	if (($rows[0]['expired'] ?? 't') !== 't') {
		throw new ShopifyOrderLinkPending("order $orderGid has no Saldi order yet");
	}
	$waited = $link === null ? 'no Saldi order' : "its link in state {$link['state']}" . ($link['ordre_id'] === null ? ' without an order' : '');
	shopify_status_list_once($event, 'status.unlinked', "$type:$orderGid",
		"$type event {$event['topic']} of order $orderGid waited " . SHOPIFY_STATUS_DEFER_HOURS . " hours and found $waited; it was not applied",
		$type === 'order.status' ? 'info' : 'warning', array('order_gid' => $orderGid));
	return null;
}

/**
 * Opens an error for one event unless that event was listed under the same kind and ref before
 * (its id is kept in the row's correlation event_row_ids, open or resolved), so a replay of the
 * event adds no occurrence and no alarm.
 */
function shopify_status_list_once(array $event, string $kind, string $ref, string $message, string $severity, array $correlation = array()): void {
	$shopId = (int)$event['shop_id'];
	$id = (int)$event['event_row_id'];
	shopify_db_transaction(function () use ($shopId, $id, $kind, $ref, $message, $severity, $correlation) {
		$key = shopify_error_key($shopId, $kind, $ref);
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_error:$key"));
		$listed = array();
		foreach (shopify_db_rows("SELECT correlation->'event_row_ids' AS ids, resolved_at IS NULL AS open FROM shopify_errors WHERE error_key = $1", array($key)) as $row) {
			$ids = json_decode((string)$row['ids'], true);
			$ids = is_array($ids) ? $ids : array();
			if (in_array($id, $ids, true)) {
				return;
			}
			if ($row['open'] === 't') {
				$listed = $ids;
			}
		}
		shopify_error_open($shopId, $kind, $ref, $message, $severity, $correlation + array('event_row_id' => $id, 'event_row_ids' => array_merge($listed, array($id))));
	});
}

/**
 * The stored body of a status event, the order it concerns, and whether the shop's orders flow is
 * on (it may have been switched off after the event was queued; the event is then not applied).
 *
 * @param array{shop_id: int, event_row_id: int, topic: string} $event
 * @return array{body: array<string, mixed>, order_gid: string, on: bool}
 */
function shopify_status_load(array $event): array {
	$on = shopify_db_rows('SELECT 1 FROM shopify_shops WHERE id = $1 AND orders_enabled', array($event['shop_id'])) !== array();
	$rows = shopify_db_rows('SELECT payload_b64 FROM shopify_events WHERE id = $1 AND shop_id = $2', array($event['event_row_id'], $event['shop_id']));
	$body = isset($rows[0]) ? json_decode((string)base64_decode((string)$rows[0]['payload_b64'], true), true) : null;
	$gid = is_array($body) ? shopify_event_order_gid((string)$event['topic'], $body) : null;
	if ($gid === null) {
		throw new LogicException("event {$event['event_row_id']}: no order in the {$event['topic']} body");
	}
	return array('body' => $body, 'order_gid' => $gid, 'on' => $on);
}

/** A Shopify time as UTC 'Y-m-d H:i:s', the form the projections compare; null when absent or not ISO 8601. */
function shopify_status_utc($value): ?string {
	$time = shopify_event_time(is_string($value) ? trim($value) : null);
	return $time === null ? null : $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/**
 * Whether data of time $at replaces the stored data: a later time wins, and at the same time the
 * greater body, so webhooks in any order end with the same row and a replay changes nothing.
 */
function shopify_status_newer(?string $at, string $body, ?string $storedAt, string $storedBody): bool {
	if ($at !== $storedAt) {
		return $at !== null && ($storedAt === null || $at > $storedAt);
	}
	return strcmp($body, $storedBody) > 0;
}

/** A body as the projections store and compare it. */
function shopify_status_json(array $value): string {
	return (string)json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** A non-empty trimmed text, else null. */
function shopify_status_text($value): ?string {
	return is_scalar($value) && trim((string)$value) !== '' ? trim((string)$value) : null;
}

/** A fulfillment's tracking value: the single field, else the first of its list. */
function shopify_status_first(array $f, string $one, string $list): ?string {
	$values = is_array($f[$list] ?? null) ? array_values($f[$list]) : array();
	return shopify_status_text($f[$one] ?? null) ?? shopify_status_text($values[0] ?? null);
}

/**
 * Projects one fulfillment (REST shape) onto shopify_fulfillments, unless the stored one is newer
 * (its updated_at, else created_at). shipped_at is the fulfillment's created_at; the tracking
 * number and URL are the first when Shopify lists several.
 *
 * @param array<string, mixed> $f
 * @return bool whether the row was written
 */
function shopify_status_upsert_fulfillment(int $shopId, string $orderGid, int $ordreId, array $f): bool {
	$gid = shopify_gid((string)($f['admin_graphql_api_id'] ?? $f['id'] ?? ''), 'Fulfillment');
	$json = shopify_status_json($f);
	$at = shopify_status_utc($f['updated_at'] ?? $f['created_at'] ?? null);
	return shopify_db_transaction(function () use ($shopId, $orderGid, $ordreId, $f, $gid, $json, $at): bool {
		// the row may not exist yet, so FOR UPDATE can't serialize two first writers
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_fulfillment:$shopId:$gid"));
		$stored = shopify_db_rows('SELECT payload_b64 FROM shopify_fulfillments WHERE shop_id = $1 AND fulfillment_gid = $2', array($shopId, $gid));
		if ($stored) {
			$old = (string)base64_decode((string)$stored[0]['payload_b64'], true);
			$o = json_decode($old, true);
			$o = is_array($o) ? $o : array();
			if (!shopify_status_newer($at, $json, shopify_status_utc($o['updated_at'] ?? $o['created_at'] ?? null), $old)) {
				return false;
			}
		}
		shopify_db_rows(
			'INSERT INTO shopify_fulfillments (shop_id, order_gid, fulfillment_gid, ordre_id, status, tracking_company, tracking_number, tracking_url, shipped_at, payload_b64)
			 VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
			 ON CONFLICT (shop_id, fulfillment_gid) DO UPDATE SET order_gid = EXCLUDED.order_gid, ordre_id = EXCLUDED.ordre_id, status = EXCLUDED.status,
				tracking_company = EXCLUDED.tracking_company, tracking_number = EXCLUDED.tracking_number, tracking_url = EXCLUDED.tracking_url,
				shipped_at = EXCLUDED.shipped_at, payload_b64 = EXCLUDED.payload_b64, updated_at = now()',
			array($shopId, $orderGid, $gid, $ordreId, shopify_status_text($f['status'] ?? null), shopify_status_text($f['tracking_company'] ?? null),
				shopify_status_first($f, 'tracking_number', 'tracking_numbers'), shopify_status_first($f, 'tracking_url', 'tracking_urls'),
				shopify_event_time(shopify_status_text($f['created_at'] ?? null)), base64_encode($json))
		);
		return true;
	});
}

/**
 * The amount of a refund: the sum of its successful refund transactions, in the currency of the
 * first of them (else of its first transaction; null without any).
 *
 * @param list<array<string, mixed>> $transactions
 * @return array{amount: string, currency: string|null}
 */
function shopify_refund_amount(array $transactions): array {
	$thousandths = 0;
	$currency = null;
	foreach ($transactions as $t) {
		if (!is_array($t)) {
			continue;
		}
		if (strtolower((string)($t['kind'] ?? '')) === 'refund' && strtolower((string)($t['status'] ?? '')) === 'success') {
			$thousandths += (int)round((float)($t['amount'] ?? 0) * 1000);
			$currency = $currency ?? shopify_status_text($t['currency'] ?? null);
		}
	}
	$currency = $currency ?? shopify_status_text($transactions[0]['currency'] ?? null);
	return array('amount' => sprintf('%.3f', $thousandths / 1000), 'currency' => $currency === null ? null : strtoupper(substr($currency, 0, 3)));
}

/**
 * Projects one refund (REST shape) onto shopify_refunds for WP14 to match: amount, currency, its
 * transactions, state received. A refund WP14 has moved on from received is never rewritten.
 *
 * @param array<string, mixed> $r
 * @return bool whether the row was written
 */
function shopify_status_upsert_refund(int $shopId, string $orderGid, int $ordreId, array $r): bool {
	$gid = shopify_gid((string)($r['admin_graphql_api_id'] ?? $r['id'] ?? ''), 'Refund');
	$json = shopify_status_json($r);
	$at = shopify_status_utc($r['processed_at'] ?? $r['created_at'] ?? null);
	$transactions = array_values(array_filter((array)($r['transactions'] ?? array()), 'is_array'));
	$amount = shopify_refund_amount($transactions);
	return shopify_db_transaction(function () use ($shopId, $orderGid, $ordreId, $r, $gid, $json, $at, $transactions, $amount): bool {
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_refund:$shopId:$gid"));
		$stored = shopify_db_rows('SELECT payload_b64, state FROM shopify_refunds WHERE shop_id = $1 AND refund_gid = $2', array($shopId, $gid));
		if ($stored) {
			$old = (string)base64_decode((string)$stored[0]['payload_b64'], true);
			$o = json_decode($old, true);
			$o = is_array($o) ? $o : array();
			if ($stored[0]['state'] !== 'received' || !shopify_status_newer($at, $json, shopify_status_utc($o['processed_at'] ?? $o['created_at'] ?? null), $old)) {
				return false;
			}
		}
		shopify_db_rows(
			'INSERT INTO shopify_refunds (shop_id, order_gid, refund_gid, ordre_id, amount, currency, transactions_b64, refunded_at, payload_b64)
			 VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
			 ON CONFLICT (shop_id, refund_gid) DO UPDATE SET order_gid = EXCLUDED.order_gid, ordre_id = EXCLUDED.ordre_id, amount = EXCLUDED.amount,
				currency = EXCLUDED.currency, transactions_b64 = EXCLUDED.transactions_b64, refunded_at = EXCLUDED.refunded_at,
				payload_b64 = EXCLUDED.payload_b64, updated_at = now()',
			array($shopId, $orderGid, $gid, $ordreId, $amount['amount'], $amount['currency'], base64_encode(shopify_status_json($transactions)),
				shopify_event_time(shopify_status_text($r['processed_at'] ?? $r['created_at'] ?? null)), base64_encode($json))
		);
		return true;
	});
}

/**
 * Applies a fulfillment event: FULFILLMENTS_CREATE/UPDATE carry one fulfillment, ORDERS_FULFILLED
 * the order with all of them. Status and tracking only (K40).
 *
 * @param array{shop_id: int, event_row_id: int, topic: string} $event
 * @throws ShopifyOrderLinkPending while the order has no Saldi order
 */
function shopify_handle_fulfillment(array $event): void {
	$loaded = shopify_status_load($event);
	$link = $loaded['on'] ? shopify_require_order_link($event, $loaded['order_gid']) : null;
	if ($link === null) {
		return;
	}
	$body = $loaded['body'];
	$list = strncmp((string)$event['topic'], 'ORDERS_', 7) === 0 ? (array)($body['fulfillments'] ?? array()) : array($body);
	shopify_db_transaction(function () use ($event, $link, $list) {
		foreach ($list as $f) {
			if (is_array($f)) {
				shopify_status_upsert_fulfillment((int)$event['shop_id'], (string)$link['order_gid'], (int)$link['ordre_id'], $f);
			}
		}
	});
}

/**
 * Applies an ORDERS_UPDATED event: the order's financial and fulfillment status, with its
 * updated_at, in the link's flags (namespace status), unless the stored status is newer. The
 * fulfillments themselves come with their own events; the EasyRoutes date is SD-744.
 *
 * @param array{shop_id: int, event_row_id: int, topic: string} $event
 * @throws ShopifyOrderLinkPending while the order has no Saldi order
 */
function shopify_handle_order_updated(array $event): void {
	$loaded = shopify_status_load($event);
	$link = $loaded['on'] ? shopify_require_order_link($event, $loaded['order_gid']) : null;
	if ($link === null) {
		return;
	}
	$body = $loaded['body'];
	$status = array('financial_status' => shopify_status_text($body['financial_status'] ?? null),
		'fulfillment_status' => shopify_status_text($body['fulfillment_status'] ?? null), 'updated_at' => shopify_status_utc($body['updated_at'] ?? null));
	shopify_db_transaction(function () use ($link, $status) {
		$row = shopify_db_rows("SELECT flags->'status' AS status FROM shopify_order_links WHERE id = $1 FOR UPDATE", array((int)$link['id']));
		$stored = json_decode((string)($row[0]['status'] ?? ''), true);
		if (is_array($stored)) {
			ksort($stored); // jsonb reorders the keys; $status is built in key order
			if (!shopify_status_newer($status['updated_at'], shopify_status_json($status), $stored['updated_at'] ?? null, shopify_status_json($stored))) {
				return;
			}
		}
		shopify_db_rows('UPDATE shopify_order_links SET flags = flags || $2::jsonb, updated_at = now() WHERE id = $1',
			array((int)$link['id'], shopify_link_json(array('status' => $status))));
	});
}

/**
 * The legacy slot of the shop when its legacy intake may have imported an order created at
 * $createdAt: the slot's intake is still open (coexistence.php, as the import's admission reads
 * it), or the order is older than the shop's go_live_at. Null otherwise: a shop cut over keeps
 * its legacy_slot, and its orders since go-live came only through the Shopify import.
 */
function shopify_status_legacy_may_have(int $shopId, ?DateTimeImmutable $createdAt): ?int {
	$shop = shopify_db_rows('SELECT legacy_slot, $2::timestamp < go_live_at AS before_go_live FROM shopify_shops WHERE id = $1', array($shopId, $createdAt))[0] ?? null;
	if ($shop === null || $shop['legacy_slot'] === null) {
		return null;
	}
	$slot = (int)$shop['legacy_slot'];
	return shopify_legacy_enabled(array('now' => time()), $slot, 'intake') || $shop['before_go_live'] === 't' ? $slot : null;
}

/** Whether the link's Saldi order is invoiced (status 3 or more), whatever state the import's link has reached. */
function shopify_status_invoiced(array $link): bool {
	$rows = shopify_db_rows('SELECT status FROM ordrer WHERE id = $1', array((int)$link['ordre_id']));
	return isset($rows[0]) && (int)$rows[0]['status'] >= 3;
}

/**
 * Applies an ORDERS_CANCELLED event, once per order (the link's cancelled_at). An order never
 * imported gets a web link in state cancelled, so a later import finds it and does not create
 * the order; listed as info, or as a warning to look for the order when the legacy intake may
 * have imported it (shopify_status_legacy_may_have()). An imported order is waited for until it
 * is invoiced (as shopify_require_order_link() waits) and then opens 'lav kreditnota'
 * (status.credit_note) on the error list. With the orders flow off it is not applied and is
 * listed as a warning (ref <order gid>:flow_off), resolved once a later run handles the
 * cancellation (anything but a wait for the invoice). The credit note itself is WP14's; nothing is posted here.
 *
 * @param array{shop_id: int, event_row_id: int, topic: string} $event
 * @throws ShopifyOrderLinkPending while the imported order is not invoiced yet
 */
function shopify_handle_order_cancelled(array $event): void {
	$loaded = shopify_status_load($event);
	$shopId = (int)$event['shop_id'];
	$gid = $loaded['order_gid'];
	$name = shopify_status_text($loaded['body']['name'] ?? null) ?? $gid;
	if (!$loaded['on']) {
		shopify_status_list_once($event, 'status.cancelled', "$gid:flow_off", "Shopify order $name was cancelled while the orders flow of the shop is off and was not applied:"
			. ' check its Saldi order and make a credit note if it is invoiced (lav kreditnota)', 'warning', array('order_gid' => $gid));
		return;
	}
	$at = shopify_event_time(shopify_status_text($loaded['body']['cancelled_at'] ?? null)) ?? new DateTimeImmutable('now');
	$correlation = array('event_row_id' => (int)$event['event_row_id'], 'order_gid' => $gid);
	if (shopify_order_link($shopId, $gid) === null) {
		$slot = shopify_status_legacy_may_have($shopId, shopify_event_time(shopify_status_text($loaded['body']['created_at'] ?? null)));
		shopify_db_transaction(function () use ($shopId, $gid, $name, $at, $correlation, $slot) {
			$reserved = shopify_reserve_order_link($shopId, $gid, 'web', array('order_name' => $name, 'state' => 'cancelled'));
			if ($reserved['created']) { // else the import reserved it first, and it is waited for below
				shopify_advance_order_link($reserved['id'], 'cancelled', array('cancelled_at' => $at));
				$message = "Shopify order $name was cancelled before the Shopify import created it; it is recorded as cancelled and is not imported";
				if ($slot !== null) {
					$message .= ". The shop has a legacy integration (slot $slot), whose intake may have imported it: look for the order in Saldi and make a credit note if it is invoiced (lav kreditnota)";
				}
				shopify_error_open($shopId, 'status.cancelled', $gid, $message, $slot === null ? 'info' : 'warning', $correlation);
			}
		});
	}
	// a replay: a link cancelled before the import is not waited for, and cancelled_at keeps the row below to once
	$link = shopify_require_order_link($event, $gid, 'shopify_status_invoiced');
	shopify_error_resolve_key($shopId, 'status.cancelled', "$gid:flow_off");
	if ($link === null) {
		return;
	}
	shopify_db_transaction(function () use ($shopId, $gid, $name, $at, $link, $correlation) {
		if (shopify_db_rows('UPDATE shopify_order_links SET cancelled_at = $2, updated_at = now() WHERE id = $1 AND cancelled_at IS NULL RETURNING id', array((int)$link['id'], $at)) === array()) {
			return;
		}
		$ordrenr = shopify_db_rows('SELECT ordrenr FROM ordrer WHERE id = $1', array((int)$link['ordre_id']))[0]['ordrenr'] ?? $link['ordre_id'];
		shopify_error_open($shopId, 'status.credit_note', $gid, "Shopify order $name was cancelled on " . $at->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i')
			. "; Saldi order $ordrenr is invoiced: lav kreditnota (make a credit note for it in Saldi)", 'error', $correlation + array('ordre_id' => (int)$link['ordre_id']));
	});
}

/**
 * Applies a REFUNDS_CREATE event: the refund projection for WP14. Matching it to a credit note is WP14's.
 *
 * @param array{shop_id: int, event_row_id: int, topic: string} $event
 * @throws ShopifyOrderLinkPending while the order has no Saldi order
 */
function shopify_handle_refund(array $event): void {
	$loaded = shopify_status_load($event);
	$link = $loaded['on'] ? shopify_require_order_link($event, $loaded['order_gid']) : null;
	if ($link !== null) {
		shopify_status_upsert_refund((int)$event['shop_id'], (string)$link['order_gid'], (int)$link['ordre_id'], $loaded['body']);
	}
}

/** Runs a status handler for a claimed job: done, or a yield of five minutes while its order has no Saldi order. */
function shopify_status_job(callable $handler, array $job): array {
	try {
		$handler(array('shop_id' => (int)$job['shop_id'], 'event_row_id' => (int)($job['payload']['event_row_id'] ?? 0), 'topic' => (string)($job['payload']['topic'] ?? '')));
	} catch (ShopifyOrderLinkPending $e) {
		return array('outcome' => 'yield', 'run_after' => SHOPIFY_STATUS_DEFER_SECONDS);
	}
	return array('outcome' => 'done');
}

/** Job fulfillment.upsert (prio 25). */
function shopify_fulfillment_upsert_job(array $job, float $deadline, callable $heartbeat): array {
	return shopify_status_job('shopify_handle_fulfillment', $job);
}

/** Job order.status (prio 25). */
function shopify_order_status_job(array $job, float $deadline, callable $heartbeat): array {
	return shopify_status_job('shopify_handle_order_updated', $job);
}

/** Job order.cancelled (prio 25). */
function shopify_order_cancelled_job(array $job, float $deadline, callable $heartbeat): array {
	return shopify_status_job('shopify_handle_order_cancelled', $job);
}

/** Job refund.upsert (prio 25). */
function shopify_refund_upsert_job(array $job, float $deadline, callable $heartbeat): array {
	return shopify_status_job('shopify_handle_refund', $job);
}
