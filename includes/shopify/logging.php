<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/logging.php --- ver 5.0.0 --- 2026.10.28
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
// Call log of the Shopify module (WP02, plan P:142-144): every Shopify call is one row in
// shopify_call_log with its trace keys and redacted bodies, kept 90 days (runtime.purge, 07:15).
// shopify_redact() is the module's one redactor: client.php uses it for logged bodies, the queue
// for stored job errors, errors.php for error rows and alarm mails.
//
// 20261028 Sawaneh SD-738: created (WP02-3).

require_once __DIR__ . '/db.php';

const SHOPIFY_REDACTED = '[redacted]';
const SHOPIFY_CALL_LOG_DAYS = 90;
/** Keys whose value is replaced whole, at any depth. */
const SHOPIFY_REDACT_KEYS = '/token|secret|passw|pwd|authorization|api[_-]?key|hmac|signature|cookie/i';
/** Values replaced wherever they appear in a string: Shopify access tokens and bearer tokens. */
const SHOPIFY_REDACT_PATTERNS = array('/\bshp(?:at|ca|pa|ss|ua)_[A-Za-z0-9]+/', '/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i');
/** A secret written as key=value or key: value in a string (a URL query, a header line); the key stays. */
const SHOPIFY_REDACT_ASSIGNED = '/\b((?:access[_-]?token|client[_-]?secret|api[_-]?key|password|passwd|secret|token)\s*[=:]\s*)[^\s&"\',;]+/i';

/**
 * A copy of $v without secrets: values under a sensitive key (token, secret, password, hmac, ...)
 * become [redacted], and in every string the given $secrets and anything shaped like a Shopify or
 * bearer token, and a secret written as token=... or Access-Token: ..., are replaced. Arrays and
 * objects keep their shape; other scalars pass unchanged.
 *
 * @param list<string|null> $secrets values to strip; shorter than 4 characters is ignored
 */
function shopify_redact(mixed $v, array $secrets = []): mixed {
	if (is_string($v)) {
		$secrets = array_filter(array_map('strval', $secrets), fn(string $s): bool => strlen($s) >= 4);
		usort($secrets, fn(string $a, string $b): int => strlen($b) - strlen($a));
		$v = (string)preg_replace(SHOPIFY_REDACT_PATTERNS, SHOPIFY_REDACTED, str_replace($secrets, SHOPIFY_REDACTED, $v));
		return (string)preg_replace(SHOPIFY_REDACT_ASSIGNED, '$1' . SHOPIFY_REDACTED, $v);
	}
	if (!is_array($v) && !$v instanceof stdClass) {
		return $v;
	}
	$out = is_array($v) ? array() : new stdClass();
	foreach ($v as $key => $value) {
		$hidden = is_string($key) && preg_match(SHOPIFY_REDACT_KEYS, $key) && $value !== null && $value !== '';
		$clean = $hidden ? SHOPIFY_REDACTED : shopify_redact($value, $secrets);
		is_array($out) ? $out[$key] = $clean : $out->$key = $clean;
	}
	return $out;
}

/**
 * Stores one call in shopify_call_log, redacted again here whatever the caller did. Takes the
 * client's attempt entry (op, shop, status, cost, available, ms, correlation, direction,
 * request_id, request_b64, response_b64); the trace keys job_id, event_row_id, ordre_id and
 * order_gid come from the entry or its correlation. The row is written in its own savepoint, so a
 * failed insert never aborts the caller's transaction.
 *
 * @param array<string, mixed> $entry
 * @param list<string|null> $secrets
 * @return int the row id
 */
function shopify_call_log(array $entry, array $secrets = []): int {
	$correlation = shopify_redact(is_array($entry['correlation'] ?? null) ? $entry['correlation'] : array(), $secrets);
	$pick = fn(string $key) => $entry[$key] ?? $correlation[$key] ?? null;
	$int = fn($value): ?int => is_numeric($value) ? (int)round((float)$value) : null;
	$body = function ($b64) use ($secrets): ?string {
		$raw = is_string($b64) ? base64_decode($b64, true) : false;
		if ($raw === false) {
			return null;
		}
		$json = json_decode($raw, true);
		$clean = $json === null ? shopify_redact($raw, $secrets) : json_encode(shopify_redact($json, $secrets), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		return base64_encode((string)$clean);
	};
	$rows = shopify_db_transaction(fn(): array => shopify_db_rows(
		'INSERT INTO shopify_call_log (shop_id, direction, operation, correlation, job_id, event_row_id, ordre_id, order_gid,
			request_id, http_status, cost_requested, cost_actual, cost_available, cost_restore_rate, ms, request_b64, response_b64)
		VALUES (coalesce($1::integer, (SELECT id FROM shopify_shops WHERE shop_key = $2)), $3, $4, $5, $6, $7, $8, $9,
			$10, $11, $12, $13, $14, $15, $16, $17, $18)
		RETURNING id',
		array(
			$int($entry['shop_id'] ?? null), (string)($entry['shop'] ?? ''),
			(string)($entry['direction'] ?? 'out'), shopify_redact(isset($entry['op']) ? (string)$entry['op'] : null, $secrets),
			$correlation === array() ? null : json_encode($correlation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
			$int($pick('job_id')), $int($pick('event_row_id')), $int($pick('ordre_id')),
			$pick('order_gid') === null ? null : (string)$pick('order_gid'),
			shopify_redact(isset($entry['request_id']) ? (string)$entry['request_id'] : null, $secrets),
			$int($entry['http_status'] ?? $entry['status'] ?? null),
			$int($entry['cost_requested'] ?? null), $int(array_key_exists('cost_actual', $entry) ? $entry['cost_actual'] : ($entry['cost'] ?? null)),
			$int($entry['cost_available'] ?? $entry['available'] ?? null), $int($entry['cost_restore_rate'] ?? null),
			$int($entry['ms'] ?? null), $body($entry['request_b64'] ?? null), $body($entry['response_b64'] ?? null),
		)
	));
	return (int)$rows[0]['id'];
}

/** Deletes call-log rows older than 90 days before $now; a row exactly 90 days old is kept. Returns the count. */
function shopify_call_log_purge(DateTimeImmutable $now): int {
	$cutoff = $now->setTimezone(new DateTimeZone(date_default_timezone_get()))->modify('-' . SHOPIFY_CALL_LOG_DAYS . ' days');
	$rows = shopify_db_rows('WITH gone AS (DELETE FROM shopify_call_log WHERE created_at < $1 RETURNING 1) SELECT count(*) AS n FROM gone', array($cutoff));
	return (int)$rows[0]['n'];
}

/** Handler of the runtime.purge job (prio 90, daily 07:15). */
function shopify_runtime_purge_job(array $job, float $deadline, callable $heartbeat): array {
	shopify_call_log_purge(new DateTimeImmutable('now'));
	return array('outcome' => 'done');
}
