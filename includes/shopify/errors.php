<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/errors.php --- ver 5.0.0 --- 2026.10.28
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
// Error list, alarms and the login watchdog of the Shopify module (WP02, plan P:121-122, P:306-344).
// An error is one open row per sha256(shop, kind, ref) in shopify_errors that counts its
// occurrences; opening it alarms support by a queued mail.alert at most once an hour. A job
// alarms from its third failed attempt on. The watchdog on login mails support directly (the
// queue needs the worker it reports as stopped) and starts one background worker run.
//
// 20261028 Sawaneh SD-738: created (WP02-3).

require_once __DIR__ . '/logging.php';
require_once __DIR__ . '/queue.php';
require_once __DIR__ . '/config.php';

const SHOPIFY_ALARM_AFTER_ATTEMPTS = 3;
const SHOPIFY_ALARM_THROTTLE = 3600;
const SHOPIFY_WATCHDOG_STALE = 600;
/** Until HML names its recipients (settings group shopify, support_email). */
const SHOPIFY_SUPPORT_EMAIL_DEFAULT = 'support@saldi.dk';

/** The key of an error's open row: sha256 of shop, kind and ref. */
function shopify_error_key(?int $shopId, string $kind, string $ref): string {
	return hash('sha256', implode("\x1f", array((string)$shopId, $kind, $ref)));
}

/**
 * Opens an error, or counts one more occurrence on its open row (last_at, message and
 * correlation follow the latest). Unless the severity is info, support is alarmed by a queued
 * mail.alert when the row was not alarmed within the last hour. Runs in the caller's
 * transaction when there is one, so the error and what it reports are stored together.
 *
 * @param array<string, mixed> $correlation trace keys (job_id, event_row_id, ordre_id, order_gid, ...)
 * @return int the open row's id
 */
function shopify_error_open(?int $shopId, string $kind, string $ref, string $message, string $severity = 'error', array $correlation = []): int {
	$message = (string)shopify_redact($message);
	$correlation = (array)shopify_redact($correlation);
	return shopify_db_transaction(function () use ($shopId, $kind, $ref, $message, $severity, $correlation): int {
		$rows = shopify_db_rows(
			'INSERT INTO shopify_errors (shop_id, error_key, kind, ref, message, severity, correlation)
			VALUES ($1, $2, $3, $4, $5, $6, $7)
			ON CONFLICT (error_key) WHERE resolved_at IS NULL DO UPDATE SET occurrences = shopify_errors.occurrences + 1,
				last_at = now(), message = EXCLUDED.message, severity = EXCLUDED.severity, correlation = EXCLUDED.correlation
			RETURNING id',
			array($shopId, shopify_error_key($shopId, $kind, $ref), $kind, $ref, $message, $severity, shopify_queue_json($correlation))
		);
		$id = (int)$rows[0]['id'];
		if ($severity !== 'info') {
			$due = shopify_db_rows(
				"UPDATE shopify_errors SET alarmed_at = now()
				WHERE id = $1 AND (alarmed_at IS NULL OR alarmed_at <= now() - make_interval(secs => $2))
				RETURNING id, shop_id, kind, ref, message, severity, occurrences, first_at, last_at, correlation",
				array($id, SHOPIFY_ALARM_THROTTLE)
			);
			if (isset($due[0])) {
				shopify_error_alarm($due[0]);
			}
		}
		return $id;
	});
}

/** Marks an open error resolved; the next occurrence opens a fresh row. False when it was not open. */
function shopify_error_resolve(int $id): bool {
	return shopify_db_rows('UPDATE shopify_errors SET resolved_at = now() WHERE id = $1 AND resolved_at IS NULL RETURNING id', array($id)) !== array();
}

/**
 * The error of a failed job: from its third failed attempt on, and whenever it ended dead. A
 * failing mail.alert is recorded as info, so a broken mailer cannot queue mail about itself.
 *
 * @param array<string, mixed> $job as claimed (attempts includes this one)
 */
function shopify_error_job_failed(array $job, string $result, string $err): void {
	if ($result !== 'dead' && (int)$job['attempts'] < SHOPIFY_ALARM_AFTER_ATTEMPTS) {
		return;
	}
	$trace = array_filter(array('job_id' => $job['id'], 'event_row_id' => $job['event_row_id'] ?? null,
		'ordre_id' => $job['ordre_id'] ?? null, 'order_gid' => $job['order_gid'] ?? null), fn($v) => $v !== null);
	$message = "{$job['type']} attempt {$job['attempts']} " . ($result === 'dead' ? 'failed, no more retries' : 'failed') . ": $err";
	shopify_error_open(isset($job['shop_id']) ? (int)$job['shop_id'] : null, 'job.failed', "{$job['type']}:{$job['dedupe_key']}", $message,
		$job['type'] === 'mail.alert' ? 'info' : 'error', $trace + (array)($job['correlation'] ?? array()));
}

/** Resolves a job's open job.failed error once it succeeds; only a job with earlier failures can have one. */
function shopify_error_job_done(array $job): void {
	if ((int)$job['attempts'] <= SHOPIFY_ALARM_AFTER_ATTEMPTS) {
		return;
	}
	$key = shopify_error_key(isset($job['shop_id']) ? (int)$job['shop_id'] : null, 'job.failed', "{$job['type']}:{$job['dedupe_key']}");
	shopify_db_rows('UPDATE shopify_errors SET resolved_at = now() WHERE error_key = $1 AND resolved_at IS NULL', array($key));
}

/** @param array<string, string|null> $row the alarmed shopify_errors row */
function shopify_error_alarm(array $row): void {
	global $db;
	$subject = "Saldi Shopify alarm $db: {$row['kind']} {$row['ref']}";
	$body = implode("\n", array(
		"Tenant: $db", "Kind: {$row['kind']}", "Ref: {$row['ref']}", "Severity: {$row['severity']}",
		"Occurrences: {$row['occurrences']} (first {$row['first_at']}, last {$row['last_at']})",
		"Correlation: {$row['correlation']}", '', (string)$row['message'],
	));
	shopify_queue_enqueue('mail.alert', isset($row['shop_id']) ? (int)$row['shop_id'] : null, "tenant:alert:{$row['id']}:{$row['occurrences']}",
		array('to' => shopify_support_email(), 'subject' => $subject, 'body' => $body), SHOPIFY_QUEUE_PRIORITY['mail.alert']);
}

function shopify_support_email(): string {
	$rows = shopify_db_rows("SELECT var_value FROM settings WHERE var_grp = 'shopify' AND var_name = 'support_email' LIMIT 1");
	$to = trim((string)($rows[0]['var_value'] ?? ''));
	return $to !== '' ? $to : SHOPIFY_SUPPORT_EMAIL_DEFAULT;
}

/** Handler of mail.alert (prio 5); a mail that was not accepted is a failed attempt, retried. */
function shopify_mail_alert_job(array $job, float $deadline, callable $heartbeat): array {
	$p = $job['payload'];
	if (!shopify_mail_send((string)($p['to'] ?? ''), (string)($p['subject'] ?? ''), (string)($p['body'] ?? ''))) {
		throw new RuntimeException('the alarm mail was not accepted for delivery');
	}
	return array('outcome' => 'done');
}

/**
 * Sends a plain-text mail to one or more comma-separated addresses; invalid ones are dropped.
 * False when no address is valid or the transport refused it.
 */
function shopify_mail_send(string $to, string $subject, string $body): bool {
	$list = array_filter(array_map('trim', explode(',', $to)), fn(string $a): bool => filter_var($a, FILTER_VALIDATE_EMAIL) !== false);
	if ($list === array()) {
		return false;
	}
	return (bool)shopify_mail_transport()(implode(', ', $list), trim(str_replace(array("\r", "\n"), ' ', $subject)), $body);
}

/** How shopify_mail_send() delivers: PHP mail() unless a test passed another transport. */
function shopify_mail_transport(?callable $replace = null): callable {
	static $transport = null;
	if ($replace !== null) {
		$transport = $replace;
	}
	return $transport ?? function (string $to, string $subject, string $body): bool {
		$headers = "From: kan_ikke_besvares@saldi.dk\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
		return mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, $headers);
	};
}

/**
 * The login watchdog. When the worker's heartbeat is older than 10 minutes on a tenant with a
 * Shopify shop, it mails support (at most once an hour) and starts one background worker run (at
 * most once per 10 minutes; the worker's flock keeps it to one runner). Never throws.
 *
 * @param array{db: string, now?: int, heartbeat_file?: string, spawn?: callable(string): void} $ctx
 * @return array{stale: bool, mailed: bool, spawned: bool, error?: string}
 */
function shopify_watchdog(array $ctx): array {
	$db = (string)$ctx['db'];
	$now = (int)($ctx['now'] ?? time());
	$file = $ctx['heartbeat_file'] ?? dirname(__DIR__, 2) . "/temp/$db/.ht_shopify_worker.heartbeat";
	$result = array('stale' => false, 'mailed' => false, 'spawned' => false);
	try {
		$beat = is_file($file) ? (int)(json_decode((string)file_get_contents($file), true)['unix'] ?? filemtime($file)) : null;
		if ($beat === null || $now - $beat <= SHOPIFY_WATCHDOG_STALE || !shopify_schema_ready() || shopify_shops() === array()) {
			return $result;
		}
		$result['stale'] = true;
		[$mail, $spawn, $state] = shopify_db_transaction(function () use ($now): array {
			shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array('shopify_state:watchdog'));
			$state = shopify_queue_state_get('watchdog');
			$mail = $now - (int)($state['mailed_at'] ?? 0) >= SHOPIFY_ALARM_THROTTLE;
			$spawn = $now - (int)($state['spawned_at'] ?? 0) >= SHOPIFY_WATCHDOG_STALE;
			if ($mail || $spawn) {
				shopify_queue_state_set('watchdog', array('mailed_at' => $mail ? $now : ($state['mailed_at'] ?? 0), 'spawned_at' => $spawn ? $now : ($state['spawned_at'] ?? 0)));
			}
			return array($mail, $spawn, $state);
		});
		if ($spawn) {
			($ctx['spawn'] ?? 'shopify_watchdog_spawn')($db);
			$result['spawned'] = true;
		}
		if ($mail) {
			$age = (int)floor(($now - $beat) / 60);
			$result['mailed'] = shopify_mail_send(shopify_support_email(), "Saldi Shopify alarm $db: the worker has stopped",
				"Tenant: $db\nThe Shopify worker's last run was $age minutes ago (" . date('c', $beat) . ").\nA login started a background run" .
				($spawn ? '' : ' earlier') . "; check the worker timer (doc/shopify/drift-worker.md).");
			if (!$result['mailed']) {
				// not sent: the next stale login tries again instead of waiting an hour
				shopify_queue_state_set('watchdog', array('mailed_at' => $state['mailed_at'] ?? 0, 'spawned_at' => $spawn ? $now : ($state['spawned_at'] ?? 0)));
			}
		}
	} catch (Throwable $e) {
		$result['error'] = get_class($e);
		error_log("shopify watchdog $db: " . shopify_redact(get_class($e) . ': ' . $e->getMessage()));
	}
	return $result;
}

/** Starts one worker run for the tenant in the background, like hent_shop_ordrer()'s fallback. */
function shopify_watchdog_spawn(string $db): void {
	if (!preg_match('/^[a-z0-9_]{1,40}$/', $db)) {
		return;
	}
	$php = is_executable(PHP_BINDIR . '/php') ? PHP_BINDIR . '/php' : 'php';
	exec('nohup ' . escapeshellarg($php) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/tools/shopify_worker.php') . ' run --db=' . escapeshellarg($db) . ' > /dev/null 2>&1 &');
}
