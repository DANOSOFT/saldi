<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/worker.php --- ver 5.0.0 --- 2026.10.26
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
// The worker run of the Shopify module (WP02, plan P:135-136): one bounded pass per minute
// that recovers expired leases, inserts due slots, runs the producers (event promotion,
// stock-dirty drain) and then claims jobs by priority until the time budget is spent.
// Handlers run outside any transaction; each finishes its job through the lease token.
//
// 20261026 Sawaneh SD-737: created (WP02-2).
// 20261026 Sawaneh SD-737 seam: contracts only (classes, constants, public signatures); bodies follow.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/queue.php';
require_once __DIR__ . '/scheduler.php';
require_once __DIR__ . '/config.php';

/** A job is not claimed with less than this many seconds of the budget left. */
const SHOPIFY_WORKER_MIN_SLICE = 5;
const SHOPIFY_WORKER_LEASE = 120;

/**
 * Adds a handler for a job type. The handler is called as
 * fn(array $job, float $deadline, callable $heartbeat): array{outcome: 'done'|'yield', checkpoint?: array, run_after?: int}
 * where $deadline is a unix time it must return by and $heartbeat() extends the lease and
 * returns false once the job was taken over. A thrown exception counts as a failed attempt;
 * a LogicException (ShopifyDbMisuse included) makes the job dead at once.
 */
function shopify_worker_register(array &$registry, string $type, callable $handler): void {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}

/**
 * One worker pass for the bootstrapped tenant.
 *
 * @param array{db: string, now?: callable(): DateTimeImmutable, clock?: callable(): float, shops?: list<array<string, mixed>>, heartbeat_file?: string|null} $ctx
 *   now/clock are test seams; shops defaults to shopify_shops(); heartbeat_file defaults to temp/<db>/.ht_shopify_worker.heartbeat
 * @param array<string, callable> $registry type => handler
 * @param array<string, callable> $producers name => fn(array $ctx, float $deadline): array, run in this order before claiming
 * @return array{recovered: int, scheduled: int, producers: array<string, mixed>, claimed: int, done: int, yielded: int, failed: int, dead: int, lost: int, jobs: list<array{id: int, type: string, result: string}>, stopped: string, ms: int}
 */
function shopify_worker_run(array $ctx, array $registry, array $producers, int $maxSeconds = 50): array {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}

/** The heartbeat file S02c's login watchdog reads: temp/<db>/.ht_shopify_worker.heartbeat. */
function shopify_worker_heartbeat_path(string $db): string {
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}
