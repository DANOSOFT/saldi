<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/events.php --- ver 5.1.0 --- 2026.10.29
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
// turns stored events into queued jobs, ORDERS_PAID first. Registration (WP03-3) keeps each
// shop's webhook subscriptions pointed at the receiver; replay feeds recorded webhooks back in.
//
// Never logged: the body, the signature and the secret.
//
// 20261027 CL/SZ SD-748: created (WP03-2).
// 20261028 CL/SZ SD-748: a failed anomaly alarm is logged and retried next run instead of stopping promotion.
// 20261028 CL/SZ SD-748 review: a failed event alarm too; compliance topics are ignored whatever the flow.
// 20261028 CL/SZ SD-748: alarms go through S02c's includes/shopify/errors.php (SD-738).
// 20261028 CL/SZ SD-748 review: deferred events go behind fresh ones; a failed route alarm keeps its kind.
// 20261028 CL/SZ SD-749: webhook registration, the hourly webhook.health job and fixture replay (WP03-3).
// 20261029 CL/SZ SD-749 review: a shop with a wrong domain or API version kills the health job on purpose (doc).
// 20261029 CL/SZ SD-749 review: a health job that can't restore raises it on the error list; the base URL's host is lower-cased.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/client.php';

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
 * 'received' with processed_at NULL replays it. Events of a shop whose flow (orders or customers) is off stay
 * 'received' until it is switched on. It also raises the alarm of every anomaly the receiver
 * could not alarm. Each event's state change and alarm are stored together. An alarm that fails,
 * of an anomaly or an event, is logged (alarm_deferred) and its row is left for the next run. A
 * deferred event stays 'received' with processed_at set, and is taken after every event not tried
 * yet, so deferred events never fill the batch ahead of new ones. Concurrent runs skip each
 * other's rows.
 *
 * @return array{queued: int, ignored: int, error: int, failed: int, deferred: int, alarmed: int, events: list<array{id: int, topic: string, state: string, job_id: int|null}>}
 *   error: bodies that can't be routed; failed: events whose enqueue failed (both in state
 *   'error'); deferred: events left 'received' because their alarm failed; alarmed: anomalies
 *   alarmed by this run
 */
function shopify_promote_events(int $limit = 100): array {
	// The worker has loaded the queue already; loaded here only when it hasn't.
	if (!function_exists('shopify_queue_enqueue')) {
		require_once __DIR__ . '/queue.php';
	}
	return shopify_db_transaction(function () use ($limit) {
		$out = array('queued' => 0, 'ignored' => 0, 'error' => 0, 'failed' => 0, 'deferred' => 0, 'alarmed' => 0, 'events' => array());
		$anomalies = shopify_db_rows("SELECT id FROM shopify_events WHERE state = 'anomaly' AND processed_at IS NULL ORDER BY id LIMIT $1 FOR UPDATE SKIP LOCKED", array(max(1, $limit)));
		foreach ($anomalies as $anomaly) {
			try {
				// Own savepoint: a failed alarm rolls back to it and the anomaly stays pending.
				$out['alarmed'] += shopify_event_raise_anomaly_alarm((int)$anomaly['id']) ? 1 : 0;
			} catch (ShopifyDbMisuse $e) {
				throw $e;
			} catch (Exception $e) {
				shopify_event_log('alarm_deferred', array('event_row_id' => (int)$anomaly['id'], 'error' => get_class($e) . ': ' . $e->getMessage()));
			}
		}
		$events = shopify_db_rows(
			"SELECT e.id, e.shop_id, e.topic, e.event_id, e.payload_b64, s.shop_key FROM shopify_events e JOIN shopify_shops s ON s.id = e.shop_id"
			. " WHERE e.state = 'received' AND CASE WHEN e.topic IN ('" . implode("', '", SHOPIFY_EVENT_NOT_ROUTED) . "') THEN true"
			. " WHEN e.topic LIKE 'CUSTOMERS\\_%' THEN s.customers_enabled"
			. " WHEN e.topic LIKE 'ORDERS\\_%' OR e.topic LIKE 'FULFILLMENTS\\_%' OR e.topic LIKE 'REFUNDS\\_%' THEN s.orders_enabled ELSE true END"
			. " ORDER BY (e.processed_at IS NULL) DESC, e.processed_at, (e.topic = 'ORDERS_PAID') DESC, e.received_at, e.id LIMIT $1 FOR UPDATE OF e SKIP LOCKED",
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
				if ($e instanceof ShopifyEventRouteAlarmFailed) {
					list($kind, $message, $count) = array('webhook.route', $e->problem, 'error');
				} else {
					list($kind, $message, $count) = array('webhook.promote', 'enqueue failed: ' . get_class($e) . ': ' . $e->getMessage(), 'failed');
				}
				try {
					shopify_db_transaction(function () use ($event, $id, $ref, $kind, $message) {
						shopify_db_rows("UPDATE shopify_events SET state = 'error', processed_at = now() WHERE id = $1", array($id));
						shopify_event_alarm((int)$event['shop_id'], $kind, $ref, $message, array('event_row_id' => $id));
					});
					$done = array('state' => 'error', 'job_id' => null, 'count' => $count);
				} catch (ShopifyDbMisuse $e) {
					throw $e;
				} catch (Exception $e) {
					// Own savepoint: the event stays 'received' and is tried again next run, after the
					// events not tried yet (processed_at set).
					shopify_db_rows('UPDATE shopify_events SET processed_at = now() WHERE id = $1', array($id));
					shopify_event_log('alarm_deferred', array('event_row_id' => $id, 'cause' => $message, 'error' => get_class($e) . ': ' . $e->getMessage()));
					$done = array('state' => 'received', 'job_id' => null, 'count' => 'deferred');
				}
			}
			$out[$done['count']]++;
			$out['events'][] = array('id' => $id, 'topic' => $event['topic'], 'state' => $done['state'], 'job_id' => $done['job_id']);
		}
		return $out;
	});
}

/** The alarm of an event whose body can't be routed failed; carries the route problem. */
final class ShopifyEventRouteAlarmFailed extends RuntimeException
{
	public string $problem;

	public function __construct(string $problem, Throwable $previous) {
		parent::__construct("$problem; its alarm failed: " . get_class($previous) . ': ' . $previous->getMessage(), 0, $previous);
		$this->problem = $problem;
	}
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
		try {
			shopify_event_alarm((int)$event['shop_id'], 'webhook.route', $ref, $problem, array('event_row_id' => $id));
		} catch (ShopifyDbMisuse $e) {
			throw $e;
		} catch (Exception $e) {
			// Re-thrown with the route problem, so the caller keeps its kind and message.
			throw new ShopifyEventRouteAlarmFailed($problem, $e);
		}
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
 * Raises an alarm through WP02's shopify_error_open() (S02c, includes/shopify/errors.php), inside
 * the caller's transaction, so the alarm and the state it reports are stored together; a failing
 * alarm throws and rolls both back. errors.php is loaded here unless it already is.
 *
 * @throws Exception when the alarm can't be raised
 */
function shopify_event_alarm(?int $shopId, string $kind, string $ref, string $message, array $correlation = array()): void {
	if (!function_exists('shopify_error_open')) {
		require_once __DIR__ . '/errors.php';
	}
	shopify_error_open($shopId, $kind, $ref, $message, 'error', $correlation);
}

/** One line in the PHP error log; never the body, the signature or the secret. */
function shopify_event_log(string $what, array $entry): void {
	error_log('shopify_webhook ' . $what . ' ' . json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));
}

/**
 * Topics subscribed on every shop (plan P:306-344). INVENTORY_LEVELS_UPDATE is left out in P1:
 * Saldi's own stock pushes would flood the inbox.
 */
const SHOPIFY_WEBHOOK_TOPICS = array(
	'ORDERS_CREATE', 'ORDERS_PAID', 'ORDERS_UPDATED', 'ORDERS_CANCELLED', 'ORDERS_FULFILLED',
	'CUSTOMERS_CREATE', 'CUSTOMERS_UPDATE', 'FULFILLMENTS_CREATE', 'FULFILLMENTS_UPDATE', 'REFUNDS_CREATE',
);

/** The receiver below the install's base URL; a subscription to it, for the shop's key, is ours. */
const SHOPIFY_WEBHOOK_PATH = '/api/shopify/webhook.php';

/** Tenant setting (settings group shopify) with the base URL register-webhooks last used. */
const SHOPIFY_WEBHOOK_BASE_SETTING = 'webhook_base_url';

const SHOPIFY_WEBHOOK_LIST_QUERY = 'query webhookSubscriptions($topics: [WebhookSubscriptionTopic!], $after: String) {
	webhookSubscriptions(first: 100, topics: $topics, after: $after) {
		nodes { id topic uri }
		pageInfo { hasNextPage endCursor }
	}
}';

const SHOPIFY_WEBHOOK_CREATE_MUTATION = 'mutation webhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
	webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
		webhookSubscription { id topic uri }
		userErrors { field message }
	}
}';

const SHOPIFY_WEBHOOK_UPDATE_MUTATION = 'mutation webhookSubscriptionUpdate($id: ID!, $webhookSubscription: WebhookSubscriptionInput!) {
	webhookSubscriptionUpdate(id: $id, webhookSubscription: $webhookSubscription) {
		webhookSubscription { id topic uri }
		userErrors { field message }
	}
}';

/**
 * Reconciles the shop's webhook subscriptions with SHOPIFY_WEBHOOK_TOPICS at $baseUrl (plan
 * P:171-177, P:306-344). Per topic, a subscription at the wanted URI is kept; one of ours at
 * another base URL (a tunnel that changed) is moved to the wanted URI; otherwise one is created.
 * Subscriptions to any other URI are left alone, so a second run sends nothing. Shopify lists
 * only this app's subscriptions; on a custom app that two developers share, the last run's base
 * URL wins. With opts restore_only (the hourly health job) only missing topics are created: one
 * of ours at another base URL is kept as it is (topic state 'elsewhere', counted as unchanged), so
 * two installs that share an app don't move each other's subscriptions every hour.
 *
 * @param array<string, mixed> $shop shopify_shops row
 * @param string $baseUrl https URL of the Saldi install, e.g. https://ssl12.saldi.dk/sz
 * @param array<string, mixed> $opts restore_only, and the rest passed on to shopify_graphql():
 *   deadline, correlation, test seams
 * @return array{created: int, updated: int, unchanged: int, failed: int, topics: array<string, string>, errors: list<string>}
 * @throws RuntimeException when the subscriptions can't be read; nothing is changed then
 */
function shopify_register_webhooks(array $shop, string $baseUrl, array $opts = array()): array {
	$base = shopify_webhook_base_url_check($baseUrl);
	if ($base === null) {
		throw new InvalidArgumentException('the webhook base URL must be an https URL without query or fragment');
	}
	$restoreOnly = !empty($opts['restore_only']);
	unset($opts['restore_only']);
	$shopKey = (string)$shop['shop_key'];
	$wanted = shopify_webhook_uri($base, $shopKey);
	$ours = array();
	foreach (shopify_webhook_subscriptions($shop, SHOPIFY_WEBHOOK_TOPICS, $opts) as $sub) {
		if (shopify_webhook_owned((string)$sub['uri'], $shopKey)) {
			$ours[(string)$sub['topic']][] = $sub;
		}
	}
	$input = array('uri' => $wanted, 'format' => 'JSON');
	$out = array('created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0, 'topics' => array(), 'errors' => array());
	foreach (SHOPIFY_WEBHOOK_TOPICS as $topic) {
		$mine = $ours[$topic] ?? array();
		if (in_array($wanted, array_column($mine, 'uri'), true)) {
			$out['unchanged']++;
			$out['topics'][$topic] = 'unchanged';
			continue;
		}
		if ($mine && $restoreOnly) {
			$out['unchanged']++;
			$out['topics'][$topic] = 'elsewhere';
			continue;
		}
		if ($mine) {
			$state = 'updated';
			$result = shopify_graphql($shop, 'webhookSubscriptionUpdate', SHOPIFY_WEBHOOK_UPDATE_MUTATION,
				array('id' => $mine[0]['id'], 'webhookSubscription' => $input), array('retry_mode' => 'idempotent') + $opts);
		} else {
			$state = 'created';
			// A create that may have reached Shopify is looked up before anything is sent again.
			$reconcile = function (array $attempt) use ($shop, $topic, $wanted, $opts): array {
				foreach (shopify_webhook_subscriptions($shop, array($topic), array('deadline' => $attempt['deadline']) + $opts) as $sub) {
					if ($sub['uri'] === $wanted) {
						return array('state' => 'applied', 'data' => array('webhookSubscriptionCreate' => array('webhookSubscription' => $sub, 'userErrors' => array())));
					}
				}
				return array('state' => 'absent');
			};
			$result = shopify_graphql($shop, 'webhookSubscriptionCreate', SHOPIFY_WEBHOOK_CREATE_MUTATION,
				array('topic' => $topic, 'webhookSubscription' => $input), array('retry_mode' => 'reconcile', 'reconcile' => $reconcile) + $opts);
		}
		if (!$result['ok']) {
			$state = 'failed';
			$messages = array_column(array_merge($result['user_errors'], $result['errors']), 'message');
			$out['errors'][] = "$topic: " . ($messages ? implode('; ', $messages) : (string)$result['error']);
		}
		$out[$state]++;
		$out['topics'][$topic] = $state;
	}
	return $out;
}

/**
 * This app's subscriptions for the given topics, every page.
 *
 * @return list<array{id: string, topic: string, uri: string}>
 */
function shopify_webhook_subscriptions(array $shop, array $topics, array $opts): array {
	$subs = array();
	$after = null;
	for ($page = 0; $page < 20; $page++) {
		$result = shopify_graphql($shop, 'webhookSubscriptions', SHOPIFY_WEBHOOK_LIST_QUERY,
			array('topics' => $topics, 'after' => $after), array('retry_mode' => 'read') + $opts);
		$connection = $result['data']['webhookSubscriptions'] ?? null;
		if (!$result['ok'] || !is_array($connection)) {
			throw new RuntimeException('webhook subscriptions of ' . $shop['shop_key'] . ' could not be read: ' . ($result['error'] ?? 'no data'));
		}
		foreach ($connection['nodes'] as $node) {
			$subs[] = $node;
		}
		if (empty($connection['pageInfo']['hasNextPage'])) {
			return $subs;
		}
		$after = (string)$connection['pageInfo']['endCursor'];
	}
	throw new RuntimeException('more than 20 pages of webhook subscriptions on ' . $shop['shop_key'] . '; nothing reconciled');
}

/**
 * The base URL without a trailing slash, scheme and host lower-cased (so it compares with what Shopify
 * returns); null unless it is https without credentials, query or fragment.
 */
function shopify_webhook_base_url_check(string $url): ?string {
	$parts = parse_url(trim($url));
	if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === ''
		|| isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
		return null;
	}
	return 'https://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '') . rtrim($parts['path'] ?? '', '/');
}

/** The receiver URI Shopify posts a shop's webhooks to. */
function shopify_webhook_uri(string $baseUrl, string $shopKey): string {
	return $baseUrl . SHOPIFY_WEBHOOK_PATH . '?shop=' . rawurlencode($shopKey);
}

/** Whether $uri is this receiver for this shop, under any base URL. */
function shopify_webhook_owned(string $uri, string $shopKey): bool {
	$parts = parse_url($uri);
	if ($parts === false || !str_ends_with((string)($parts['path'] ?? ''), SHOPIFY_WEBHOOK_PATH)) {
		return false;
	}
	parse_str((string)($parts['query'] ?? ''), $query);
	return ($query['shop'] ?? null) === $shopKey;
}

/** The base URL register-webhooks stored for the tenant, null when it never ran. */
function shopify_webhook_base_url(): ?string {
	$rows = shopify_db_rows("SELECT var_value FROM settings WHERE var_grp = 'shopify' AND var_name = $1 LIMIT 1", array(SHOPIFY_WEBHOOK_BASE_SETTING));
	return isset($rows[0]['var_value']) ? shopify_webhook_base_url_check((string)$rows[0]['var_value']) : null;
}

/** Stores the tenant's webhook base URL for the webhook.health job. */
function shopify_save_webhook_base_url(string $url): void {
	$base = shopify_webhook_base_url_check($url);
	if ($base === null) {
		throw new InvalidArgumentException('the webhook base URL must be an https URL without query or fragment');
	}
	shopify_db_transaction(function () use ($base) {
		// settings has no unique key: serialized like shopify_save_secret(), one row in the end
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array('shopify_setting:' . SHOPIFY_WEBHOOK_BASE_SETTING));
		$updated = shopify_db_rows("UPDATE settings SET var_value = $2 WHERE var_grp = 'shopify' AND var_name = $1 RETURNING id", array(SHOPIFY_WEBHOOK_BASE_SETTING, $base));
		if ($updated === array()) {
			shopify_db_rows("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ($1, 'shopify', $2, 'Shopify webhook base URL (register-webhooks)')", array(SHOPIFY_WEBHOOK_BASE_SETTING, $base));
		}
	});
}

/**
 * Handler of the hourly webhook.health job (scheduler slot webhook.health, priority 40): re-creates
 * the job's shop's subscriptions that Shopify deleted after repeated failed deliveries (NF-5),
 * with the base URL register-webhooks stored; moving subscriptions stays with that command.
 * Without a stored base URL it does nothing. A failure is logged and raised on the error list
 * (kind webhook.health, one open row per shop, mailed at most once an hour), and the job still ends
 * done: the next hour's job is the retry, so failing jobs never pile up. A shop whose domain or API
 * version is wrong is left to throw (InvalidArgumentException): the job dies with an alarm, each
 * hour until the shop is fixed, as no retry can fix that.
 *
 * @param array<string, mixed> $job claimed shopify_jobs row
 * @param array<string, mixed> $opts passed on to shopify_graphql() (test seams)
 * @return array{outcome: string}
 */
function shopify_webhook_health_job(array $job, float $deadline, callable $heartbeat, array $opts = array()): array {
	$base = shopify_webhook_base_url();
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1', array((int)$job['shop_id']))[0] ?? null;
	if ($base === null || $shop === null) {
		shopify_event_log('health_skipped', array('job_id' => $job['id'] ?? null, 'reason' => $base === null ? 'no webhook base URL; run register-webhooks' : 'shop not found'));
		return array('outcome' => 'done');
	}
	try {
		$result = shopify_register_webhooks($shop, $base, array('restore_only' => true, 'deadline' => $deadline, 'correlation' => array('job_id' => $job['id'] ?? null)) + $opts);
	} catch (RuntimeException $e) {
		$result = array('created' => 0, 'failed' => 1, 'errors' => array($e->getMessage()));
	}
	if ($result['created'] > 0) {
		shopify_event_log('subscriptions_restored', array('shop' => $shop['shop_key'], 'topics' => array_keys($result['topics'], 'created', true)));
	}
	if ($result['failed'] > 0) {
		shopify_event_log('health_failed', array('shop' => $shop['shop_key'], 'errors' => $result['errors']));
		// a lost token or access stops deliveries for good; the log line alone would hide it
		shopify_event_alarm((int)$shop['id'], 'webhook.health', (string)$shop['shop_key'],
			'webhook subscriptions could not be restored: ' . implode('; ', $result['errors']), array('job_id' => $job['id'] ?? null));
	}
	return array('outcome' => 'done');
}

/**
 * Replays a recorded day of webhooks for $shop through the normal receiver,
 * shopify_receive_webhook(), each delivery signed at run time with the shop's webhook secret
 * (plan P:983, P:1006). The day file is JSON under tests/shopify/fixtures/:
 * {"deliveries": [{"topic": "orders/paid", "event_id": "...", "triggered_at": "...", "body": "<file beside it>"}]}.
 * A delivery received before answers duplicate, so replaying a day twice changes nothing.
 *
 * @param array<string, mixed> $shop shopify_shops row
 * @param array{promote?: bool} $opts promote: run shopify_promote_events() afterwards
 * @return array{stored: int, duplicate: int, anomaly: int, rejected: int, results: list<array{event_id: string, status: int, outcome: string}>, promoted: array|null}
 */
function shopify_replay_fixture(array $shop, string $path, array $opts = array()): array {
	$root = realpath(__DIR__ . '/../../tests/shopify/fixtures');
	$inFixtures = fn($file): bool => $root !== false && $file !== false && is_file($file) && strncmp($file, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) === 0;
	$file = realpath($path);
	if (!$inFixtures($file)) {
		throw new InvalidArgumentException('replay reads day files under tests/shopify/fixtures/ only');
	}
	$day = json_decode((string)file_get_contents($file), true);
	if (!is_array($day) || !is_array($day['deliveries'] ?? null)) {
		throw new InvalidArgumentException('the day file has no deliveries list');
	}
	$secret = shopify_secret((string)$shop['shop_key'], SHOPIFY_WEBHOOK_SECRET);
	if ($secret === null || $secret === '') {
		throw new RuntimeException('shop ' . $shop['shop_key'] . ' has no webhook secret');
	}
	$out = array('stored' => 0, 'duplicate' => 0, 'anomaly' => 0, 'rejected' => 0, 'results' => array(), 'promoted' => null);
	foreach ($day['deliveries'] as $i => $delivery) {
		$body = realpath(dirname($file) . '/' . (string)($delivery['body'] ?? ''));
		if (!$inFixtures($body)) {
			throw new InvalidArgumentException("delivery $i: its body file is not under tests/shopify/fixtures/");
		}
		$raw = rtrim((string)file_get_contents($body), "\n");
		$answer = shopify_receive_webhook($shop, $raw, array(
			'X-Shopify-Topic' => (string)($delivery['topic'] ?? ''),
			'X-Shopify-Hmac-Sha256' => base64_encode(hash_hmac('sha256', $raw, $secret, true)),
			'X-Shopify-Shop-Domain' => (string)$shop['domain'],
			'X-Shopify-Event-Id' => (string)($delivery['event_id'] ?? ''),
			'X-Shopify-Webhook-Id' => 'replay-' . bin2hex(random_bytes(8)),
			'X-Shopify-Triggered-At' => (string)($delivery['triggered_at'] ?? ''),
			'X-Shopify-API-Version' => (string)$shop['api_version'],
		));
		$out[$answer['status'] === 200 ? $answer['outcome'] : 'rejected']++;
		$out['results'][] = array('event_id' => (string)($delivery['event_id'] ?? ''), 'status' => $answer['status'], 'outcome' => $answer['outcome']);
	}
	if (!empty($opts['promote'])) {
		$out['promoted'] = shopify_promote_events();
	}
	return $out;
}
