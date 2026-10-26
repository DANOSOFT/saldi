<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/queue.php --- ver 5.0.0 --- 2026.10.26
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
// 20261026 Sawaneh SD-737 seam: contracts only (classes, constants, public signatures); bodies follow.

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
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
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
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}

/** Marks the job done. False when $token no longer holds the lease (another run took it over). */
function shopify_queue_complete(int $id, string $token): bool {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}

/**
 * Puts a job that ran out of time back in the queue with its checkpoint. A yield is not a
 * failure, so the attempt it used is given back.
 *
 * @param array<string, mixed> $checkpoint handed to the handler on the next claim
 * @param int $runAfter seconds before it may be claimed again
 */
function shopify_queue_yield(int $id, string $token, array $checkpoint, int $runAfter = 0): bool {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}

/**
 * Records a failed attempt: back in the queue after the backoff (or $retryAfter seconds,
 * e.g. a Retry-After from Shopify), or dead when max_attempts is reached.
 */
function shopify_queue_fail(int $id, string $token, string $err, ?int $retryAfter = null): bool {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}

/**
 * Ends a job for good without further retries: for errors a retry cannot fix, such as
 * ShopifyDbMisuse (the work is already final) or a programming error in the handler.
 */
function shopify_queue_dead(int $id, string $token, string $err): bool {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}

/** Extends the lease of a running job; the handler's heartbeat. False when the lease was lost. */
function shopify_queue_extend(int $id, string $token, int $leaseSeconds = 120): bool {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}

/**
 * Returns running jobs whose lease expired (a crashed or killed run) to the queue, or makes
 * them dead when that crash used their last attempt. Their old token can no longer finish them.
 *
 * @return int number of jobs recovered
 */
function shopify_queue_recover_expired(int $limit = 100): int {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}

/**
 * Seconds before the next attempt after $attempts failed ones: 1, 5 and 15 minutes, then an
 * hour. Null when the job has used its attempts and is dead.
 */
function shopify_queue_backoff(int $attempts, int $maxAttempts = 52): ?int {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}

/**
 * Durable state of a recurring job between runs (e.g. the counterpart scan's cursor), kept in
 * settings group shopify_state. Empty array when unset.
 *
 * @return array<string, mixed>
 */
function shopify_queue_state_get(string $key): array {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}

/** @param array<string, mixed> $value */
function shopify_queue_state_set(string $key, array $value): void {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}
