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
// 20261026 Sawaneh SD-737 review: a transaction left open by a handler is rolled back before the job is
//                 finished, so the terminal state always commits; a yield waits until after the pass.

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
	shopify_queue_check_type($type);
	if (isset($registry[$type])) {
		throw new LogicException("a handler for $type is already registered");
	}
	$registry[$type] = $handler;
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
	$clock = $ctx['clock'] ?? fn(): float => microtime(true);
	$now = $ctx['now'] ?? fn(): DateTimeImmutable => new DateTimeImmutable('now');
	$started = $clock();
	$end = $started + max(1, $maxSeconds);
	$summary = array('recovered' => 0, 'scheduled' => 0, 'producers' => array(), 'claimed' => 0, 'done' => 0,
		'yielded' => 0, 'failed' => 0, 'dead' => 0, 'lost' => 0, 'jobs' => array(), 'stopped' => 'empty', 'ms' => 0);

	$summary['recovered'] = shopify_queue_recover_expired();
	$types = array_keys($registry);
	$summary['scheduled'] = shopify_schedule_insert($now(), $ctx['shops'] ?? shopify_shops(), $types);
	foreach ($producers as $name => $producer) {
		try {
			$summary['producers'][$name] = $producer($ctx, $end);
		} catch (Throwable $e) {
			$summary['producers'][$name] = array('error' => get_class($e) . ': ' . $e->getMessage());
		}
	}

	while (true) {
		if ($end - $clock() < SHOPIFY_WORKER_MIN_SLICE) {
			$summary['stopped'] = 'budget';
			break;
		}
		$job = shopify_queue_claim($types, max(SHOPIFY_WORKER_LEASE, $maxSeconds + 30));
		if ($job === null) {
			break;
		}
		$summary['claimed']++;
		$result = shopify_worker_execute($job, $registry[$job['type']], $end);
		$summary[$result]++;
		$summary['jobs'][] = array('id' => $job['id'], 'type' => $job['type'], 'result' => $result);
	}
	$summary['ms'] = (int)round(($clock() - $started) * 1000);
	shopify_worker_write_heartbeat($ctx['heartbeat_file'] ?? shopify_worker_heartbeat_path((string)$ctx['db']), $summary);
	return $summary;
}

/**
 * Runs one claimed job and finishes it through its lease token. Whatever transaction the
 * handler leaves open is rolled back first, so the finisher always commits on its own; a
 * handler that returned with a transaction open did not finish cleanly and is dead. A yield
 * is deferred until after this pass, so a job that yields for lack of time cannot crowd out
 * the rest of the queue.
 *
 * @param array<string, mixed> $job
 * @return string done, yielded, failed, dead, or lost when the lease was taken over meanwhile
 */
function shopify_worker_execute(array $job, callable $handler, float $deadline): string {
	$id = $job['id'];
	$token = $job['lock_token'];
	$heartbeat = fn(): bool => shopify_queue_extend($id, $token, SHOPIFY_WORKER_LEASE);
	try {
		$outcome = $handler($job, $deadline, $heartbeat);
	} catch (LogicException $e) {
		shopify_worker_close_transaction();
		return shopify_queue_dead($id, $token, get_class($e) . ': ' . $e->getMessage()) ? 'dead' : 'lost';
	} catch (Throwable $e) {
		shopify_worker_close_transaction();
		$dead = $job['attempts'] >= $job['max_attempts'];
		return shopify_queue_fail($id, $token, get_class($e) . ': ' . $e->getMessage()) ? ($dead ? 'dead' : 'failed') : 'lost';
	}
	if (shopify_worker_close_transaction()) {
		return shopify_queue_dead($id, $token, 'handler returned with a transaction open; its uncommitted work was rolled back') ? 'dead' : 'lost';
	}
	$kind = is_array($outcome) ? ($outcome['outcome'] ?? null) : null;
	if ($kind === 'done') {
		return shopify_queue_complete($id, $token) ? 'done' : 'lost';
	}
	if ($kind === 'yield') {
		$checkpoint = is_array($outcome['checkpoint'] ?? null) ? $outcome['checkpoint'] : array();
		$afterThisPass = max(1, (int)ceil($deadline - microtime(true)));
		return shopify_queue_yield($id, $token, $checkpoint, max((int)($outcome['run_after'] ?? 0), $afterThisPass)) ? 'yielded' : 'lost';
	}
	return shopify_queue_dead($id, $token, 'handler returned no outcome done or yield') ? 'dead' : 'lost';
}

/**
 * Rolls back a transaction a handler left open (or aborted) on the tenant connection and resets
 * the legacy transaktion() depth. True when there was one.
 */
function shopify_worker_close_transaction(): bool {
	global $connection, $db_transaktion_depth;
	if (!$connection || pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE) {
		return false;
	}
	@pg_query($connection, 'ROLLBACK');
	$db_transaktion_depth = 0;
	return true;
}

/** The heartbeat file S02c's login watchdog reads: temp/<db>/.ht_shopify_worker.heartbeat. */
function shopify_worker_heartbeat_path(string $db): string {
	return dirname(__DIR__, 2) . "/temp/$db/.ht_shopify_worker.heartbeat";
}

/**
 * Writes the time of the run and its counts, never job payloads.
 *
 * @param array<string, mixed> $summary
 */
function shopify_worker_write_heartbeat(?string $file, array $summary): void {
	if ($file === null) {
		return;
	}
	$counts = array_intersect_key($summary, array_flip(array('recovered', 'scheduled', 'claimed', 'done', 'yielded', 'failed', 'dead', 'lost', 'stopped', 'ms')));
	$line = json_encode(array('at' => date('c'), 'unix' => time()) + $counts);
	if (is_dir(dirname($file))) {
		@file_put_contents($file, $line . "\n", LOCK_EX);
	}
}
