<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/queue.php --- ver 5.1.0 --- 2026.10.30
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
// Durable job queue of the Shopify module (WP02, interface sheet 2.2) on shopify_jobs. Every
// Shopify effect is a job: enqueued with a dedupe key, claimed with FOR UPDATE SKIP LOCKED
// under a lease token, and finished only by the holder of that token (complete, yield, fail
// or dead). A job that keeps failing is retried 1, 5, 15, 60 minutes, then hourly, and is
// dead after max_attempts (52, about 48 hours).
//
// 20261026 Sawaneh SD-737: created (WP02-2).
// 20261028 Sawaneh SD-738: a stored job error is redacted (shopify_redact(), loaded by the worker); recovery
//                 reports the jobs it recovered, so a crash can alarm.
// 20261029 CL/SS SD-741: shopify_queue_enqueue_coalesced() for producers of changed data: a follow-up behind a
//                running job, and shopify_queue_hasten() brings a waiting one forward.

require_once __DIR__ . '/db.php';

final class ShopifyQueueError extends RuntimeException
{
}

/** Priorities of the plan's table (P:72); lower is claimed first. */
const SHOPIFY_QUEUE_PRIORITY = array(
	'mail.alert' => 5,
	'order.import' => 10, 'order.tags' => 10,
	'order.move' => 15,
	'store.export' => 20, 'customer.export' => 20, 'order.references' => 20, 'order.document' => 20,
	'customer.upsert' => 25, 'fulfillment.upsert' => 25, 'order.status' => 25, 'order.cancelled' => 25,
	'refund.upsert' => 25, 'credit.publish' => 25, 'order.note' => 25, 'order.processed' => 25,
	'stock.push' => 30,
	'stock.display' => 35,
	'monitor.counterpart' => 40, 'webhook.health' => 40,
	'price.window' => 50,
	'catalogue.upsert' => 60, 'catalogue.link' => 60, 'catalogue.seed' => 60,
	'stock.reconcile' => 70, 'stock.baseline' => 70,
	'monitor.overview' => 80,
	'runtime.purge' => 90,
);

/** Retry delays in seconds after the 1st, 2nd and 3rd failed attempt; every later one waits an hour. */
const SHOPIFY_QUEUE_BACKOFF = array(60, 300, 900);
const SHOPIFY_QUEUE_BACKOFF_LATER = 3600;
const SHOPIFY_QUEUE_ERROR_LENGTH = 2000;

/**
 * Adds a job unless an equal one (same type and dedupe key) is still queued or running; then
 * that job is returned with created=false. Correlation keys event_row_id, ordre_id and
 * order_gid are also stored in their own columns for tracing.
 *
 * @param string $dedupeKey <shop_key>:<natural key>, or tenant:<key> for tenant-wide jobs
 * @param array<string, mixed> $payload
 * @param int $priority 0-100, lower first; see SHOPIFY_QUEUE_PRIORITY
 * @param DateTimeImmutable|null $runAt not before this time; now when null
 * @param array<string, mixed> $correlation
 * @return array{id: int, created: bool, state: string}
 */
function shopify_queue_enqueue(string $type, ?int $shopId, string $dedupeKey, array $payload, int $priority, ?DateTimeImmutable $runAt = null, array $correlation = array()): array {
	return shopify_queue_insert(array(
		'type' => $type, 'shop_id' => $shopId, 'dedupe_key' => $dedupeKey, 'payload' => $payload,
		'priority' => $priority, 'run_at' => $runAt, 'correlation' => $correlation, 'schedule_slot' => null,
	));
}

/**
 * Claims the next due job of the given types: lowest priority first, then the oldest due
 * time. Concurrent claimers skip each other's locked rows. The claim counts an attempt and
 * leases the job to a new token for $leaseSeconds; only that token can finish the job.
 *
 * @param list<string> $types
 * @return array{id: int, type: string, shop_id: int|null, dedupe_key: string, payload: array<string, mixed>, priority: int, attempts: int, max_attempts: int, checkpoint: array<string, mixed>|null, correlation: array<string, mixed>, event_row_id: int|null, ordre_id: int|null, order_gid: string|null, schedule_slot: string|null, lock_token: string, locked_until: string}|null
 */
function shopify_queue_claim(array $types, int $leaseSeconds = 120): ?array {
	if ($types === array()) {
		return null;
	}
	foreach ($types as $type) {
		shopify_queue_check_type((string)$type);
	}
	$token = bin2hex(random_bytes(16));
	$rows = shopify_db_rows(
		"WITH next AS (
			SELECT id FROM shopify_jobs
			WHERE state = 'queued' AND next_run_at <= now() AND type = ANY($1::text[])
			ORDER BY priority, next_run_at, id
			LIMIT 1
			FOR UPDATE SKIP LOCKED
		)
		UPDATE shopify_jobs j
		SET state = 'running', attempts = j.attempts + 1, lock_token = $2,
			locked_until = now() + make_interval(secs => $3), updated_at = now()
		FROM next WHERE j.id = next.id
		RETURNING j.*",
		array('{' . implode(',', $types) . '}', $token, max(1, $leaseSeconds))
	);
	return isset($rows[0]) ? shopify_queue_job($rows[0]) : null;
}

/** Marks the job done. False when $token no longer holds the lease (another run took it over). */
function shopify_queue_complete(int $id, string $token): bool {
	return shopify_queue_fenced($id, $token, "state = 'done', finished_at = now(), last_error = NULL");
}

/**
 * shopify_queue_enqueue() for a producer whose data changed: when the equal job is already running
 * it may have read the data before the change, so a second job (key <key>:again) is queued
 * behind it; when it is waiting (perhaps in a retry backoff) it is brought forward to $runAt.
 *
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $correlation
 * @return array{id: int, created: bool, state: string}
 */
function shopify_queue_enqueue_coalesced(string $type, ?int $shopId, string $dedupeKey, array $payload, int $priority, ?DateTimeImmutable $runAt = null, array $correlation = array()): array {
	$job = shopify_queue_enqueue($type, $shopId, $dedupeKey, $payload, $priority, $runAt, $correlation);
	if (!$job['created'] && $job['state'] === 'running') {
		$job = shopify_queue_enqueue($type, $shopId, "$dedupeKey:again", $payload, $priority, $runAt, $correlation);
	}
	if (!$job['created'] && $job['state'] === 'queued') {
		shopify_queue_hasten($job['id'], $runAt);
	}
	return $job;
}

/**
 * Brings a queued job's next run forward to $runAt (now when null), never later. False when the
 * job is not queued. For a producer whose job is waiting out a retry backoff: the new change
 * should not wait an hour.
 */
function shopify_queue_hasten(int $id, ?DateTimeImmutable $runAt = null): bool {
	return shopify_db_rows(
		"UPDATE shopify_jobs SET next_run_at = least(next_run_at, coalesce($2::timestamp, now()))
		 WHERE id = $1 AND state = 'queued' RETURNING id",
		array($id, $runAt)
	) !== array();
}

/**
 * Puts a job that ran out of time back in the queue with its checkpoint. A yield is not a
 * failure, so the attempt it used is given back.
 *
 * @param array<string, mixed> $checkpoint handed to the handler on the next claim
 * @param int $runAfter seconds before it may be claimed again
 */
function shopify_queue_yield(int $id, string $token, array $checkpoint, int $runAfter = 0): bool {
	return shopify_queue_fenced(
		$id, $token,
		"state = 'queued', attempts = greatest(attempts - 1, 0), checkpoint = $3::jsonb, next_run_at = now() + make_interval(secs => $4)",
		array(shopify_queue_json($checkpoint), max(0, $runAfter))
	);
}

/**
 * Records a failed attempt: back in the queue after the backoff (or $retryAfter seconds,
 * e.g. a Retry-After from Shopify), or dead when max_attempts is reached.
 */
function shopify_queue_fail(int $id, string $token, string $err, ?int $retryAfter = null): bool {
	$rows = shopify_db_rows("SELECT attempts, max_attempts FROM shopify_jobs WHERE id = $1 AND lock_token = $2 AND state = 'running'", array($id, $token));
	if (!isset($rows[0])) {
		return false;
	}
	$delay = shopify_queue_backoff((int)$rows[0]['attempts'], (int)$rows[0]['max_attempts']);
	if ($delay === null) {
		return shopify_queue_dead($id, $token, $err);
	}
	return shopify_queue_fenced(
		$id, $token,
		"state = 'queued', next_run_at = now() + make_interval(secs => $3), first_failed_at = coalesce(first_failed_at, now()), last_error = $4",
		array($retryAfter !== null ? max(0, $retryAfter) : $delay, shopify_queue_error_text($err))
	);
}

/**
 * Ends a job for good without further retries: for errors a retry cannot fix, such as
 * ShopifyDbMisuse (the work is already final) or a programming error in the handler.
 */
function shopify_queue_dead(int $id, string $token, string $err): bool {
	return shopify_queue_fenced(
		$id, $token,
		"state = 'dead', finished_at = now(), first_failed_at = coalesce(first_failed_at, now()), last_error = $3",
		array(shopify_queue_error_text($err))
	);
}

/** Extends the lease of a running job; the handler's heartbeat. False when the lease was lost. */
function shopify_queue_extend(int $id, string $token, int $leaseSeconds = 120): bool {
	$rows = shopify_db_rows(
		"UPDATE shopify_jobs SET locked_until = now() + make_interval(secs => $3), updated_at = now()
		 WHERE id = $1 AND lock_token = $2 AND state = 'running' RETURNING id",
		array($id, $token, max(1, $leaseSeconds))
	);
	return $rows !== array();
}

/**
 * Returns running jobs whose lease expired (a crashed or killed run) to the queue, or makes
 * them dead when that crash used their last attempt. Their old token can no longer finish them.
 *
 * @param list<array<string, mixed>>|null $jobs receives the recovered jobs, with their new state
 * @return int number of jobs recovered
 */
function shopify_queue_recover_expired(int $limit = 100, ?array &$jobs = null): int {
	$rows = shopify_db_rows(
		"UPDATE shopify_jobs SET
			state = CASE WHEN attempts >= max_attempts THEN 'dead' ELSE 'queued' END,
			finished_at = CASE WHEN attempts >= max_attempts THEN now() ELSE NULL END,
			first_failed_at = coalesce(first_failed_at, now()),
			last_error = 'lease expired', lock_token = NULL, locked_until = NULL, next_run_at = now(), updated_at = now()
		 WHERE id IN (
			SELECT id FROM shopify_jobs WHERE state = 'running' AND locked_until < now()
			ORDER BY locked_until LIMIT $1 FOR UPDATE SKIP LOCKED
		 )
		 RETURNING *",
		array(max(1, $limit))
	);
	$jobs = array_map(fn(array $row): array => shopify_queue_job($row) + array('state' => $row['state']), $rows);
	return count($rows);
}

/**
 * Seconds before the next attempt after $attempts failed ones: 1, 5 and 15 minutes, then an
 * hour. Null when the job has used its attempts and is dead.
 */
function shopify_queue_backoff(int $attempts, int $maxAttempts = 52): ?int {
	if ($attempts >= $maxAttempts) {
		return null;
	}
	return SHOPIFY_QUEUE_BACKOFF[$attempts - 1] ?? SHOPIFY_QUEUE_BACKOFF_LATER;
}

/**
 * Durable state of a recurring job between runs (e.g. the counterpart scan's cursor), kept in
 * settings group shopify_state. Empty array when unset.
 *
 * @return array<string, mixed>
 */
function shopify_queue_state_get(string $key): array {
	$rows = shopify_db_rows("SELECT var_value FROM settings WHERE var_grp = 'shopify_state' AND var_name = $1 LIMIT 1", array($key));
	$value = isset($rows[0]) ? json_decode((string)$rows[0]['var_value'], true) : null;
	return is_array($value) ? $value : array();
}

/** @param array<string, mixed> $value */
function shopify_queue_state_set(string $key, array $value): void {
	$json = shopify_queue_json($value);
	shopify_db_transaction(function () use ($key, $json) {
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_state:$key"));
		$updated = shopify_db_rows("UPDATE settings SET var_value = $2 WHERE var_grp = 'shopify_state' AND var_name = $1 RETURNING id", array($key, $json));
		if ($updated === array()) {
			shopify_db_rows("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ($1, 'shopify_state', $2, 'Shopify runtime state')", array($key, $json));
		}
	});
}

/**
 * Inserts a job spec; the unique keys of shopify_jobs (an active job per type and dedupe key,
 * one job per scheduled slot) turn a duplicate into the existing job.
 *
 * @param array{type: string, shop_id: int|null, dedupe_key: string, payload: array<string, mixed>, priority: int, run_at: DateTimeImmutable|null, correlation: array<string, mixed>, schedule_slot: string|null} $spec
 * @return array{id: int, created: bool, state: string}
 */
function shopify_queue_insert(array $spec): array {
	shopify_queue_check_type($spec['type']);
	if ($spec['dedupe_key'] === '' || strpos($spec['dedupe_key'], ':') === false) {
		throw new ShopifyQueueError('dedupe key must be <shop_key>:<natural key> or tenant:<key>');
	}
	if ($spec['priority'] < 0 || $spec['priority'] > 100) {
		throw new ShopifyQueueError('priority must be 0-100');
	}
	$correlation = $spec['correlation'];
	$params = array(
		$spec['type'], $spec['shop_id'], $spec['dedupe_key'], shopify_queue_json($spec['payload']), $spec['priority'],
		$spec['run_at'], shopify_queue_json($correlation), $spec['schedule_slot'],
		isset($correlation['event_row_id']) ? (int)$correlation['event_row_id'] : null,
		isset($correlation['ordre_id']) ? (int)$correlation['ordre_id'] : null,
		isset($correlation['order_gid']) ? (string)$correlation['order_gid'] : null,
	);
	for ($try = 0; $try < 3; $try++) {
		$rows = shopify_db_rows(
			"INSERT INTO shopify_jobs (type, shop_id, dedupe_key, payload, priority, next_run_at, correlation, schedule_slot, event_row_id, ordre_id, order_gid)
			 VALUES ($1, $2, $3, $4::jsonb, $5, coalesce($6::timestamp, now()), $7::jsonb, $8, $9, $10, $11)
			 ON CONFLICT DO NOTHING RETURNING id, state",
			$params
		);
		if (isset($rows[0])) {
			return array('id' => (int)$rows[0]['id'], 'created' => true, 'state' => (string)$rows[0]['state']);
		}
		$rows = shopify_db_rows(
			"SELECT id, state FROM shopify_jobs WHERE type = $1 AND dedupe_key = $2
			 AND (state IN ('queued', 'running') OR ($3::text IS NOT NULL AND schedule_slot IS NOT NULL))
			 ORDER BY id DESC LIMIT 1",
			array($spec['type'], $spec['dedupe_key'], $spec['schedule_slot'])
		);
		if (isset($rows[0])) {
			return array('id' => (int)$rows[0]['id'], 'created' => false, 'state' => (string)$rows[0]['state']);
		}
		// the conflicting job finished between the insert and the lookup: insert again
	}
	throw new ShopifyQueueError("job {$spec['type']} {$spec['dedupe_key']} could neither be inserted nor found");
}

/** Runs a lease-fenced update: only the current token of a running job applies it. */
function shopify_queue_fenced(int $id, string $token, string $set, array $extra = array()): bool {
	$rows = shopify_db_rows(
		"UPDATE shopify_jobs SET $set, lock_token = NULL, locked_until = NULL, updated_at = now()
		 WHERE id = $1 AND lock_token = $2 AND state = 'running' RETURNING id",
		array_merge(array($id, $token), $extra)
	);
	return $rows !== array();
}

/**
 * A claimed row with its types restored.
 *
 * @param array<string, string|null> $row
 * @return array<string, mixed>
 */
function shopify_queue_job(array $row): array {
	$decode = function (?string $json, bool $nullable) {
		$value = $json === null ? null : json_decode($json, true);
		return is_array($value) ? $value : ($nullable ? null : array());
	};
	$int = fn(?string $v): ?int => $v === null ? null : (int)$v;
	return array(
		'id' => (int)$row['id'], 'type' => (string)$row['type'], 'shop_id' => $int($row['shop_id']),
		'dedupe_key' => (string)$row['dedupe_key'], 'payload' => $decode($row['payload'], false),
		'priority' => (int)$row['priority'], 'attempts' => (int)$row['attempts'], 'max_attempts' => (int)$row['max_attempts'],
		'checkpoint' => $decode($row['checkpoint'], true), 'correlation' => $decode($row['correlation'], false),
		'event_row_id' => $int($row['event_row_id']), 'ordre_id' => $int($row['ordre_id']), 'order_gid' => $row['order_gid'],
		'schedule_slot' => $row['schedule_slot'], 'lock_token' => (string)$row['lock_token'], 'locked_until' => (string)$row['locked_until'],
	);
}

function shopify_queue_check_type(string $type): void {
	if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/', $type)) {
		throw new ShopifyQueueError("invalid job type '$type'; use <area>.<action>, e.g. order.import");
	}
}

/** A map as a JSON object; an empty one is {} and never [], which jsonb would keep as a list. */
function shopify_queue_json(array $value): string {
	return $value === array() ? '{}' : (string)json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** The stored error text: redacted whenever logging.php is loaded, as it is in every worker run. */
function shopify_queue_error_text(string $err): string {
	return mb_substr(function_exists('shopify_redact') ? (string)shopify_redact($err) : $err, 0, SHOPIFY_QUEUE_ERROR_LENGTH);
}
