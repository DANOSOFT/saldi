<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/events.php --- ver 5.0.0 --- 2026.10.27
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
// Webhook inbox of the Shopify module (WP03-2, plan P:161-169, P:361-371): every HMAC-valid
// delivery is stored once in shopify_events, keyed (shop_id, topic, event_id), and answered
// 200, duplicates and conflicting bodies included, so Shopify never retries a delivery that
// was received. The receiver does no business work; shopify_promote_events() (run-loop step 3)
// turns stored events into queued jobs, ORDERS_PAID first.
//
// Never logged: the body, the signature and the secret.
//
// 20261027 CL/SZ SD-748: created (WP03-2).

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

/** Name of the per-shop secret (settings group shopify:<shop_key>) that signs the shop's webhooks. */
const SHOPIFY_WEBHOOK_SECRET = 'webhook_secret';

/**
 * Job type per topic (plan P:363-371). Topics ending in '_*' match every topic with that
 * prefix. Every type dedupes on the event, except order.import, which dedupes on the order.
 */
const SHOPIFY_EVENT_ROUTES = array(
	'ORDERS_PAID' => 'order.import',
	'ORDERS_CREATE' => 'order.observed',
	'ORDERS_UPDATED' => 'order.status',
	'ORDERS_CANCELLED' => 'order.cancelled',
	'ORDERS_FULFILLED' => 'fulfillment.upsert',
	'FULFILLMENTS_*' => 'fulfillment.upsert',
	'CUSTOMERS_*' => 'customer.upsert',
	'REFUNDS_CREATE' => 'refund.upsert',
);

/** Shopify's mandatory compliance topics share the CUSTOMERS_ prefix but are not customer upserts. */
const SHOPIFY_EVENT_NOT_ROUTED = array('CUSTOMERS_DATA_REQUEST', 'CUSTOMERS_REDACT');

/**
 * Priority of a job type that SHOPIFY_QUEUE_PRIORITY does not list yet (order.observed): the
 * level of the other order status jobs (plan P:72, 25).
 */
const SHOPIFY_EVENT_DEFAULT_PRIORITY = 25;

/** Whether $sig is the base64 HMAC-SHA256 of exactly these bytes under $secret. */
function shopify_verify_hmac(string $raw, string $sig, string $secret): bool {
	if ($secret === '' || $sig === '') {
		return false;
	}
	return hash_equals(base64_encode(hash_hmac('sha256', $raw, $secret, true)), trim($sig));
}

/**
 * The X-Shopify-* request headers from $_SERVER, with lower-case names ('x-shopify-topic').
 *
 * @param array<string, mixed> $server
 * @return array<string, string>
 */
function shopify_webhook_headers(array $server): array {
	$headers = array();
	foreach ($server as $name => $value) {
		if (is_string($name) && strncmp($name, 'HTTP_X_SHOPIFY_', 15) === 0 && is_scalar($value)) {
			$headers[strtolower(str_replace('_', '-', substr($name, 5)))] = (string)$value;
		}
	}
	return $headers;
}

/** 'orders/paid' -> 'ORDERS_PAID'; null when the header is not a topic. */
function shopify_event_topic(string $header): ?string {
	$topic = strtolower(trim($header));
	return preg_match('#^[a-z_]{1,60}/[a-z_]{1,60}$#', $topic) ? strtoupper(str_replace('/', '_', $topic)) : null;
}

/**
 * Stores one webhook delivery for a shop. Answers, as the endpoint returns them:
 * - 200 stored | duplicate (same key, same body) | anomaly (same key, other body, or no usable
 *   topic or event id; stored as state 'anomaly' and alarmed: at once when the alarm can be
 *   raised, else by the next shopify_promote_events(), so the 200 never waits on the alarm);
 * - 401 shop_mismatch | bad_signature (logged, no rows);
 * - 503 not_configured (no webhook secret for the shop), 500 database_failed | failed: Shopify retries.
 *
 * @param array<string, string|null> $shop shopify_shops row
 * @param array<string, string> $headers request headers, any case ('X-Shopify-Hmac-Sha256')
 * @return array{status: int, outcome: string, event_row_id: int|null}
 */
function shopify_receive_webhook(array $shop, string $raw, array $headers): array {
	$h = array_change_key_case($headers, CASE_LOWER);
	$shopKey = (string)$shop['shop_key'];
	$log = array('shop' => $shopKey, 'topic' => (string)($h['x-shopify-topic'] ?? ''), 'event_id' => (string)($h['x-shopify-event-id'] ?? ''), 'webhook_id' => (string)($h['x-shopify-webhook-id'] ?? ''));
	$answer = function (int $status, string $outcome, ?int $id = null) use (&$log): array {
		if ($status !== 200 || $outcome !== 'stored') {
			shopify_event_log($outcome, $log + array('status' => $status, 'event_row_id' => $id));
		}
		return array('status' => $status, 'outcome' => $outcome, 'event_row_id' => $id);
	};

	if (strtolower(trim((string)($h['x-shopify-shop-domain'] ?? ''))) !== strtolower((string)$shop['domain'])) {
		return $answer(401, 'shop_mismatch');
	}
	try {
		$secret = shopify_secret($shopKey, SHOPIFY_WEBHOOK_SECRET);
	} catch (ShopifyDbError $e) {
		return $answer(500, 'database_failed');
	}
	if ($secret === null || $secret === '') {
		return $answer(503, 'not_configured');
	}
	if (!shopify_verify_hmac($raw, (string)($h['x-shopify-hmac-sha256'] ?? ''), $secret)) {
		return $answer(401, 'bad_signature');
	}

	$topic = shopify_event_topic((string)($h['x-shopify-topic'] ?? ''));
	$eventId = trim((string)($h['x-shopify-event-id'] ?? ''));
	$state = 'received';
	if ($topic === null || $eventId === '' || strlen($eventId) > 200) {
		// Signed, so it is answered 200 and kept, but it can't be keyed: the body's hash keys it,
		// so a retry of the same delivery still lands on the same row.
		$topic = $topic ?? 'UNKNOWN';
		$eventId = 'missing:' . hash('sha256', $raw);
		$state = 'anomaly';
	}
	$row = array(
		'shop_id' => (int)$shop['id'],
		'topic' => $topic,
		'event_id' => $eventId,
		'webhook_id' => shopify_event_header($h, 'x-shopify-webhook-id'),
		'triggered_at' => shopify_event_time(shopify_event_header($h, 'x-shopify-triggered-at')),
		'api_version' => shopify_event_header($h, 'x-shopify-api-version'),
		'payload_b64' => base64_encode($raw),
	);
	$ref = "$shopKey:$topic:$eventId";

	try {
		if ($state === 'anomaly') {
			$id = shopify_event_insert($row, 'anomaly');
			$outcome = $id === null ? 'duplicate' : 'anomaly';
			$id = $id ?? shopify_event_id($row);
			shopify_event_alarm_now($id, $log);
			return $answer(200, $outcome, $id);
		}
		$id = shopify_event_insert($row, $state);
		if ($id !== null) {
			return $answer(200, 'stored', $id);
		}
		$existing = shopify_db_rows('SELECT id, payload_b64 = $4 AS same FROM shopify_events WHERE shop_id = $1 AND topic = $2 AND event_id = $3', array($row['shop_id'], $topic, $eventId, $row['payload_b64']));
		if ($existing && $existing[0]['same'] === 't') {
			return $answer(200, 'duplicate', (int)$existing[0]['id']);
		}
		// Same key, other body: the first row stays as it is, and the conflicting body is kept
		// beside it under a key of its own, so a retry of that body adds nothing.
		$conflict = $row;
		$conflict['event_id'] = $eventId . '#conflict:' . hash('sha256', $raw);
		$id = shopify_event_insert($conflict, 'anomaly') ?? shopify_event_id($conflict);
		shopify_event_alarm_now($id, $log);
		return $answer(200, 'anomaly', $id);
	} catch (ShopifyDbError $e) {
		$log['error'] = $e->getMessage();
		return $answer(500, 'database_failed');
	} catch (Exception $e) {
		$log['error'] = get_class($e) . ': ' . $e->getMessage();
		return $answer(500, 'failed');
	}
}

/**
 * Raises the alarm of a stored anomaly that has none yet, right after the delivery was stored.
 * A failure is logged and left to shopify_promote_events(), which raises every anomaly alarm
 * still missing: the delivery was stored and is answered 200 either way.
 */
function shopify_event_alarm_now(?int $id, array $log): void {
	if ($id === null) {
		return;
	}
	try {
		shopify_event_raise_anomaly_alarm($id);
	} catch (Exception $e) {
		shopify_event_log('alarm_deferred', $log + array('event_row_id' => $id, 'error' => get_class($e) . ': ' . $e->getMessage()));
	}
}

/**
 * Raises the alarm of one anomaly row unless it has one, and marks it alarmed (processed_at) in
 * the same transaction, so each anomaly is alarmed once and a failed alarm leaves it unmarked.
 *
 * @return bool whether an alarm was raised now
 */
function shopify_event_raise_anomaly_alarm(int $id): bool {
	return shopify_db_transaction(function () use ($id) {
		$rows = shopify_db_rows(
			"SELECT e.id, e.shop_id, e.topic, e.event_id, s.shop_key FROM shopify_events e JOIN shopify_shops s ON s.id = e.shop_id"
			. " WHERE e.id = $1 AND e.state = 'anomaly' AND e.processed_at IS NULL FOR UPDATE OF e SKIP LOCKED",
			array($id)
		);
		if (!$rows) {
			return false;
		}
		$event = $rows[0];
		$correlation = array('event_row_id' => $id);
		$conflictAt = strpos((string)$event['event_id'], '#conflict:');
		if ($conflictAt !== false) {
			$eventId = substr((string)$event['event_id'], 0, $conflictAt);
			$message = 'webhook redelivered with a different body; stored as anomaly';
			$correlation['first_event_row_id'] = shopify_event_id(array('shop_id' => $event['shop_id'], 'topic' => $event['topic'], 'event_id' => $eventId));
		} else {
			$eventId = (string)$event['event_id'];
			$message = 'webhook without a usable topic or event id stored as anomaly';
		}
		shopify_db_rows('UPDATE shopify_events SET processed_at = now() WHERE id = $1', array($id));
		shopify_event_alarm((int)$event['shop_id'], 'webhook.anomaly', "{$event['shop_key']}:{$event['topic']}:$eventId", $message, $correlation);
		return true;
	});
}

/** The id of a stored event row by its key. */
function shopify_event_id(array $row): ?int {
	$rows = shopify_db_rows('SELECT id FROM shopify_events WHERE shop_id = $1 AND topic = $2 AND event_id = $3', array($row['shop_id'], $row['topic'], $row['event_id']));
	return $rows ? (int)$rows[0]['id'] : null;
}

/**
 * Routes one stored event to its job (plan P:361-371), or null when the topic has no job.
 *
 * @param array<string, mixed> $event shopify_events row with the shop's shop_key
 * @param array<string, mixed> $payload the decoded webhook body
 * @return array{type: string, dedupe_key: string, payload: array<string, mixed>, priority: int, correlation: array<string, mixed>}|null
 * @throws InvalidArgumentException when an ORDERS_PAID body carries no order id
 */
function shopify_route_event(array $event, array $payload): ?array {
	$topic = (string)$event['topic'];
	$type = shopify_event_job_type($topic);
	if ($type === null) {
		return null;
	}
	$shopKey = (string)$event['shop_key'];
	$orderGid = shopify_event_order_gid($topic, $payload);
	if ($type === 'order.import') {
		if ($orderGid === null) {
			throw new InvalidArgumentException('ORDERS_PAID without an order id');
		}
		$dedupe = "$shopKey:$orderGid";
	} else {
		$dedupe = "$shopKey:$topic:{$event['event_id']}";
	}
	$correlation = array('event_row_id' => (int)$event['id']);
	$jobPayload = array('event_row_id' => (int)$event['id'], 'topic' => $topic, 'event_id' => (string)$event['event_id']);
	if ($orderGid !== null) {
		$correlation['order_gid'] = $orderGid;
		$jobPayload['order_gid'] = $orderGid;
	}
	if (!defined('SHOPIFY_QUEUE_PRIORITY')) {
		require_once __DIR__ . '/queue.php';
	}
	$priority = SHOPIFY_QUEUE_PRIORITY[$type] ?? SHOPIFY_EVENT_DEFAULT_PRIORITY;
	return array('type' => $type, 'dedupe_key' => $dedupe, 'payload' => $jobPayload, 'priority' => $priority, 'correlation' => $correlation);
}

/**
 * Run-loop step 3: turns up to $limit received events into queued jobs, ORDERS_PAID first,
 * then oldest first. Each promoted event gets state 'queued' and its job_id; a topic without a
 * job becomes 'ignored'. A body that can't be routed, or an enqueue that fails, makes the event
 * 'error' with an alarm, so it never holds up the events behind it; setting it back to
 * 'received' replays it. Events of a shop whose flow (orders or customers) is off stay
 * 'received' until it is switched on. It also raises the alarm of every anomaly the receiver
 * could not alarm. Each event's state change and alarm are stored together; an alarm that fails
 * rolls back the whole run. Concurrent runs skip each other's rows.
 *
 * @return array{queued: int, ignored: int, error: int, failed: int, alarmed: int, events: list<array{id: int, topic: string, state: string, job_id: int|null}>}
 *   error: bodies that can't be routed; failed: events whose enqueue failed (both in state
 *   'error'); alarmed: anomalies alarmed by this run
 */
function shopify_promote_events(int $limit = 100): array {
	// The worker has loaded the queue already; loaded here only when it hasn't.
	if (!function_exists('shopify_queue_enqueue')) {
		require_once __DIR__ . '/queue.php';
	}
	return shopify_db_transaction(function () use ($limit) {
		$out = array('queued' => 0, 'ignored' => 0, 'error' => 0, 'failed' => 0, 'alarmed' => 0, 'events' => array());
		$anomalies = shopify_db_rows("SELECT id FROM shopify_events WHERE state = 'anomaly' AND processed_at IS NULL ORDER BY id LIMIT $1 FOR UPDATE SKIP LOCKED", array(max(1, $limit)));
		foreach ($anomalies as $anomaly) {
			$out['alarmed'] += shopify_event_raise_anomaly_alarm((int)$anomaly['id']) ? 1 : 0;
		}
		$events = shopify_db_rows(
			"SELECT e.id, e.shop_id, e.topic, e.event_id, e.payload_b64, s.shop_key FROM shopify_events e JOIN shopify_shops s ON s.id = e.shop_id"
			. " WHERE e.state = 'received' AND CASE WHEN e.topic LIKE 'CUSTOMERS\\_%' THEN s.customers_enabled"
			. " WHEN e.topic LIKE 'ORDERS\\_%' OR e.topic LIKE 'FULFILLMENTS\\_%' OR e.topic LIKE 'REFUNDS\\_%' THEN s.orders_enabled ELSE true END"
			. " ORDER BY (e.topic = 'ORDERS_PAID') DESC, e.received_at, e.id LIMIT $1 FOR UPDATE OF e SKIP LOCKED",
			array(max(1, $limit))
		);
		foreach ($events as $event) {
			$id = (int)$event['id'];
			$ref = "{$event['shop_key']}:{$event['topic']}:{$event['event_id']}";
			try {
				// Own savepoint: a failed enqueue or alarm rolls back this event only.
				$done = shopify_db_transaction(function () use ($event, $id, $ref) {
					return shopify_promote_event($event, $id, $ref);
				});
			} catch (ShopifyDbMisuse $e) {
				throw $e;
			} catch (Exception $e) {
				$message = 'enqueue failed: ' . get_class($e) . ': ' . $e->getMessage();
				shopify_db_transaction(function () use ($event, $id, $ref, $message) {
					shopify_db_rows("UPDATE shopify_events SET state = 'error', processed_at = now() WHERE id = $1", array($id));
					shopify_event_alarm((int)$event['shop_id'], 'webhook.promote', $ref, $message, array('event_row_id' => $id));
				});
				$done = array('state' => 'error', 'job_id' => null, 'count' => 'failed');
			}
			$out[$done['count']]++;
			$out['events'][] = array('id' => $id, 'topic' => $event['topic'], 'state' => $done['state'], 'job_id' => $done['job_id']);
		}
		return $out;
	});
}

/**
 * Promotes one locked event inside the caller's savepoint.
 *
 * @return array{state: string, job_id: int|null, count: string}
 */
function shopify_promote_event(array $event, int $id, string $ref): array {
	$payload = json_decode((string)base64_decode((string)$event['payload_b64'], true), true);
	try {
		$route = is_array($payload) ? shopify_route_event($event, $payload) : null;
		$problem = is_array($payload) ? null : 'the webhook body is not a JSON object';
	} catch (InvalidArgumentException $e) {
		$route = null;
		$problem = $e->getMessage();
	}
	if ($problem !== null) {
		shopify_db_rows("UPDATE shopify_events SET state = 'error', processed_at = now() WHERE id = $1", array($id));
		shopify_event_alarm((int)$event['shop_id'], 'webhook.route', $ref, $problem, array('event_row_id' => $id));
		return array('state' => 'error', 'job_id' => null, 'count' => 'error');
	}
	if ($route === null) {
		shopify_db_rows("UPDATE shopify_events SET state = 'ignored', processed_at = now() WHERE id = $1", array($id));
		return array('state' => 'ignored', 'job_id' => null, 'count' => 'ignored');
	}
	$job = shopify_queue_enqueue($route['type'], (int)$event['shop_id'], $route['dedupe_key'], $route['payload'], $route['priority'], null, $route['correlation']);
	shopify_db_rows("UPDATE shopify_events SET state = 'queued', job_id = $2, processed_at = now() WHERE id = $1", array($id, (int)$job['id']));
	return array('state' => 'queued', 'job_id' => (int)$job['id'], 'count' => 'queued');
}

/** The job type of a topic, null when it has none. */
function shopify_event_job_type(string $topic): ?string {
	if (in_array($topic, SHOPIFY_EVENT_NOT_ROUTED, true)) {
		return null;
	}
	if (isset(SHOPIFY_EVENT_ROUTES[$topic])) {
		return SHOPIFY_EVENT_ROUTES[$topic];
	}
	foreach (SHOPIFY_EVENT_ROUTES as $pattern => $type) {
		if (substr($pattern, -2) === '_*' && strncmp($topic, substr($pattern, 0, -1), strlen($pattern) - 1) === 0) {
			return $type;
		}
	}
	return null;
}

/**
 * The order GID an event concerns: the order itself for ORDERS_*, its order_id for
 * FULFILLMENTS_* and REFUNDS_*; null when the body carries none.
 */
function shopify_event_order_gid(string $topic, array $payload): ?string {
	if (strncmp($topic, 'ORDERS_', 7) === 0) {
		$gid = $payload['admin_graphql_api_id'] ?? null;
		if (is_string($gid) && preg_match('#^gid://shopify/Order/[1-9]\d*$#', $gid)) {
			return $gid;
		}
		$id = $payload['id'] ?? null;
	} elseif (strncmp($topic, 'FULFILLMENTS_', 13) === 0 || strncmp($topic, 'REFUNDS_', 8) === 0) {
		$id = $payload['order_id'] ?? null;
	} else {
		return null;
	}
	if (is_int($id) && $id > 0) {
		return "gid://shopify/Order/$id";
	}
	return is_string($id) && preg_match('/^[1-9]\d*$/', $id) ? "gid://shopify/Order/$id" : null;
}

/** Inserts an event row unless its key exists; the new row's id, or null on conflict. */
function shopify_event_insert(array $row, string $state): ?int {
	$rows = shopify_db_rows(
		'INSERT INTO shopify_events (shop_id, topic, event_id, webhook_id, triggered_at, api_version, payload_b64, state)'
		. ' VALUES ($1, $2, $3, $4, $5, $6, $7, $8) ON CONFLICT (shop_id, topic, event_id) DO NOTHING RETURNING id',
		array($row['shop_id'], $row['topic'], $row['event_id'], $row['webhook_id'], $row['triggered_at'], $row['api_version'], $row['payload_b64'], $state)
	);
	return $rows ? (int)$rows[0]['id'] : null;
}

/** A header value, trimmed; null when absent or empty. */
function shopify_event_header(array $h, string $name): ?string {
	$value = trim((string)($h[$name] ?? ''));
	return $value === '' ? null : $value;
}

/** X-Shopify-Triggered-At as a time, null when absent or not a valid ISO 8601 time. */
function shopify_event_time(?string $value): ?DateTimeImmutable {
	if ($value === null || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/', $value)) {
		return null;
	}
	try {
		return new DateTimeImmutable($value);
	} catch (Exception $e) {
		return null;
	}
}

/**
 * Raises an alarm through WP02's shopify_error_open() (S02c), inside the caller's transaction,
 * so the alarm and the state it reports are stored together; a failing alarm throws and rolls
 * both back. Without shopify_error_open() loaded it throws too, so nothing is ever marked as
 * alarmed without an alarm: anomalies stay pending, and a promotion run rolls back.
 *
 * @throws RuntimeException when the alarm can't be raised
 */
function shopify_event_alarm(?int $shopId, string $kind, string $ref, string $message, array $correlation = array()): void {
	if (!function_exists('shopify_error_open')) {
		throw new RuntimeException('shopify_error_open() is not loaded (S02c); the alarm is not raised');
	}
	shopify_error_open($shopId, $kind, $ref, $message, 'error', $correlation);
}

/** One line in the PHP error log; never the body, the signature or the secret. */
function shopify_event_log(string $what, array $entry): void {
	error_log('shopify_webhook ' . $what . ' ' . json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));
}
