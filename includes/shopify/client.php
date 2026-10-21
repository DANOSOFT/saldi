<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/client.php --- ver 5.0.0 --- 2026-10-21 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// The one Shopify Admin GraphQL client every Shopify call goes through (WP03):
// response parsing, cost-based pacing, retry rules and bulk-operation helpers.
// A mutation whose outcome may already have taken effect is never resent blind;
// in retry_mode "reconcile" the caller's read-first callback decides instead.
// Nothing calls this yet, so it changes no behaviour on its own.
//
// 20261021 CL/SZ Created (SD-747).

const SHOPIFY_API_VERSION = '2026-07';
const SHOPIFY_MAX_RETRIES = 3;
const SHOPIFY_CONNECT_TIMEOUT = 10.0;
const SHOPIFY_TOTAL_TIMEOUT = 30.0;
// A request (or a reconcile read) is not started with less time than this left.
const SHOPIFY_MIN_ATTEMPT_BUDGET = 1.0;
// Used when deadline is not given (WP02's jobs always pass one).
const SHOPIFY_DEFAULT_BUDGET = 60.0;
// Wait used when the bucket is short but reports no restore rate to divide by.
const SHOPIFY_THROTTLE_FALLBACK_DELAY = 1.0;
// Cost assumed for an operation before Shopify has reported its real cost.
const SHOPIFY_DEFAULT_COST_ESTIMATE = 10.0;
// Mutations whose effect may exist after a timeout or 5xx: never resent without reading first.
const SHOPIFY_RECONCILE_OPS = ['orderCreate', 'customerCreate', 'fulfillmentOrderMove', 'draftOrderCreate'];
const SHOPIFY_BULK_TERMINAL = ['COMPLETED', 'FAILED', 'CANCELED', 'EXPIRED'];

const SHOPIFY_BULK_START_MUTATION = 'mutation bulkOperationRunQuery($query: String!) {
	bulkOperationRunQuery(query: $query) {
		bulkOperation { id status }
		userErrors { field message code }
	}
}';
const SHOPIFY_BULK_RECENT_QUERY = 'query bulkOperationsRecent {
	bulkOperations(first: 10, reverse: true, sortKey: CREATED_AT) {
		nodes { id status type query }
	}
}';
const SHOPIFY_BULK_POLL_QUERY = 'query bulkOperation($id: ID!) {
	bulkOperation(id: $id) { id status errorCode objectCount fileSize url partialDataUrl }
}';

/**
 * Sends one GraphQL operation to a shop, pacing on the shop's cost bucket and retrying
 * THROTTLED, HTTP 429, 5xx and transport failures up to SHOPIFY_MAX_RETRIES times within
 * the deadline. Failures are returned, never swallowed: the job runtime (WP02) counts them.
 *
 * Retry modes: "read" for queries; "idempotent" for mutations that are safe to send twice
 * (an absolute inventorySetQuantities, metafieldsSet); "reconcile" for mutations that must
 * not run twice (SHOPIFY_RECONCILE_OPS, a note orderUpdate; both are detected in the document
 * itself, whatever $op says). In reconcile mode an attempt
 * that may have reached Shopify is followed by opts.reconcile, never by a blind resend.
 * The callback gets array{op, status, error, attempts, deadline} and returns array{state, data?}:
 * state "applied" with data = the effect found on Shopify (data may be omitted, the result's
 * data is then null), or "absent" when it is safe to
 * send again. Anything else, or an exception, ends the call with outcome "uncertain", and
 * then the caller must read before it sends again.
 *
 * @param array $shop Shop row (shopify_shops): shop_key, domain (*.myshopify.com), api_version.
 * @param string $op Operation name for logs and pacing, e.g. "orderCreate".
 * @param string $query GraphQL document.
 * @param array $vars GraphQL variables.
 * @param array{
 *   correlation?: array,   Logged with every attempt (job id, order GID, ...).
 *   deadline?: float,      Absolute unix time the call must finish by; timeouts and waits are clipped to it.
 *   retry_mode?: string,   read (default) | idempotent | reconcile.
 *   reconcile?: callable,  Read-first check, see above. Required in reconcile mode.
 *   access_token?: string, Until S01b's shopify_secret() is merged; never logged.
 *   transport?: callable,  Test seam, same contract as shopify_http_post().
 *   clock?: callable,      Test seam, returns unix time as float.
 *   sleep?: callable,      Test seam, takes seconds as float.
 *   log?: callable,        Test seam, receives one array per attempt.
 * } $opts
 * @return array{
 *   ok: bool,                True when Shopify returned data without errors (or reconcile found it applied).
 *   outcome: string,         ok | reconciled | failed | uncertain.
 *   data: array|null,        The response's data, or the reconcile callback's data.
 *   errors: list<array>,     Top-level GraphQL errors: message, code.
 *   user_errors: list<array>, Every nested userErrors or mediaUserErrors-style entry, with its path.
 *   error: string|null,      Short failure code: http_<status>, graphql_errors, user_errors, throttled,
 *                            transport, invalid_json, deadline, retries_exhausted, uncertain.
 *   retryable: bool,         True when a later attempt (a job retry) may succeed.
 *   status: int,             HTTP status of the last attempt, 0 when nothing came back.
 *   attempts: int,           Requests sent.
 *   cost: array|null,        extensions.cost of the last response.
 * }
 */
function shopify_graphql(array $shop, string $op, string $query, array $vars, array $opts): array
{
	$mode = $opts['retry_mode'] ?? 'read';
	if (!in_array($mode, ['read', 'idempotent', 'reconcile'], true)) {
		throw new InvalidArgumentException("Unknown retry_mode '$mode'");
	}
	$isMutation = shopify_graphql_is_mutation($query);
	if ($isMutation && $mode === 'read') {
		throw new InvalidArgumentException("$op is a mutation: retry_mode must be idempotent or reconcile");
	}
	$guarded = in_array($op, SHOPIFY_RECONCILE_OPS, true) ? $op : shopify_graphql_needs_reconcile($query, $vars);
	if ($guarded !== null && $mode !== 'reconcile') {
		throw new InvalidArgumentException("$guarded may already have succeeded after a failure: retry_mode must be reconcile");
	}
	if ($mode === 'reconcile' && !is_callable($opts['reconcile'] ?? null)) {
		throw new InvalidArgumentException("retry_mode reconcile needs a reconcile callback");
	}

	$transport = $opts['transport'] ?? 'shopify_http_post';
	$clock = $opts['clock'] ?? fn() => microtime(true);
	$sleep = $opts['sleep'] ?? fn(float $s) => usleep((int) round($s * 1e6));
	$log = $opts['log'] ?? 'shopify_client_log';
	$correlation = $opts['correlation'] ?? [];
	$deadline = (float) ($opts['deadline'] ?? $clock() + SHOPIFY_DEFAULT_BUDGET);
	$shopKey = (string) ($shop['shop_key'] ?? $shop['domain'] ?? '');

	$url = shopify_graphql_url($shop);
	$token = shopify_client_access_token($shop, $opts);
	$headers = [
		'Content-Type: application/json',
		'Accept: application/json',
		'X-Shopify-Access-Token: ' . $token,
	];
	$body = json_encode(['query' => $query, 'variables' => $vars ?: new stdClass()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

	$result = ['ok' => false, 'outcome' => 'failed', 'data' => null, 'errors' => [], 'user_errors' => [], 'error' => null,
		'retryable' => false, 'status' => 0, 'attempts' => 0, 'cost' => null];
	$retries = 0;
	while (true) {
		$state = shopify_client_state($shopKey);
		$estimate = $state['costs'][$op] ?? SHOPIFY_DEFAULT_COST_ESTIMATE;
		$wait = shopify_throttle_delay($state['bucket'], $estimate, $clock());
		if ($wait > 0 && !shopify_client_wait($wait, $deadline, $clock, $sleep)) {
			return shopify_client_stop($result, 'deadline');
		}
		$remaining = $deadline - $clock();
		if ($remaining < SHOPIFY_MIN_ATTEMPT_BUDGET) {
			return shopify_client_stop($result, 'deadline');
		}

		$started = $clock();
		$resp = $transport($url, $headers, $body, min(SHOPIFY_CONNECT_TIMEOUT, $remaining), min(SHOPIFY_TOTAL_TIMEOUT, $remaining));
		$result['attempts']++;
		$parsed = $resp['errno'] === 0 ? shopify_parse_graphql($resp['status'], $resp['body']) : null;
		$class = shopify_classify_attempt($resp, $parsed);
		if ($parsed !== null) {
			$result = array_merge($result, array_intersect_key($parsed, array_flip(['data', 'errors', 'user_errors', 'cost'])));
			shopify_client_remember($shopKey, $op, $parsed['cost'], $clock());
		}
		$result['status'] = $resp['status'];
		$result['error'] = $parsed['error'] ?? 'transport';
		$log([
			'op' => $op, 'shop' => $shopKey, 'attempt' => $result['attempts'], 'mode' => $mode,
			'status' => $resp['status'], 'class' => $class, 'error' => $class === 'ok' ? null : $result['error'],
			'curl_errno' => $resp['errno'], 'curl_error' => $resp['error'] ?: null, 'ms' => (int) round(($clock() - $started) * 1000),
			'cost' => $parsed['cost']['actualQueryCost'] ?? $parsed['cost']['requestedQueryCost'] ?? null,
			'available' => $parsed['cost']['throttleStatus']['currentlyAvailable'] ?? null,
			'correlation' => $correlation,
		]);

		if ($class === 'ok') {
			return array_merge($result, ['ok' => true, 'outcome' => 'ok', 'error' => null]);
		}
		if ($class === 'fatal') {
			return $result;
		}
		if ($class === 'uncertain' && $mode === 'reconcile') {
			// No time left to read first: the effect stays unknown and nothing is resent.
			$found = $deadline - $clock() >= SHOPIFY_MIN_ATTEMPT_BUDGET
				? shopify_client_reconcile($opts['reconcile'], $op, $result, $deadline) : ['state' => 'no_time'];
			$log(['op' => $op, 'shop' => $shopKey, 'event' => 'reconcile', 'attempt' => $result['attempts'],
				'state' => $found['state'] ?? null, 'correlation' => $correlation,
				'exception' => isset($found['exception']) ? str_replace($token, '[redacted]', $found['exception']) : null]);
			if (($found['state'] ?? null) === 'applied') {
				return array_merge($result, ['ok' => true, 'outcome' => 'reconciled', 'error' => null, 'data' => $found['data'] ?? null]);
			}
			if (($found['state'] ?? null) !== 'absent') {
				return array_merge($result, ['outcome' => 'uncertain', 'error' => 'uncertain', 'retryable' => true]);
			}
		}
		if ($retries >= SHOPIFY_MAX_RETRIES) {
			return shopify_client_stop($result, 'retries_exhausted');
		}
		$retries++;
		if (!shopify_client_wait(shopify_retry_delay($resp, $parsed, $retries), $deadline, $clock, $sleep)) {
			return shopify_client_stop($result, 'deadline');
		}
	}
}

/**
 * Parses one Admin API response. Top-level errors and every nested userErrors list
 * (also the *UserErrors variants such as mediaUserErrors) count as failure.
 *
 * @return array{
 *   ok: bool,                  HTTP 200, data present, no errors of either kind.
 *   data: array|null,
 *   errors: list<array{message: string, code: string|null}>,
 *   user_errors: list<array{path: string, field: mixed, message: string, code: string|null}>,
 *   throttled: bool,           A top-level error has code THROTTLED.
 *   cost: array|null,          extensions.cost (requestedQueryCost, actualQueryCost, throttleStatus).
 *   error: string|null,        http_<status> | invalid_json | throttled | graphql_errors | user_errors | null.
 * }
 */
function shopify_parse_graphql(int $status, string $body): array
{
	$out = ['ok' => false, 'data' => null, 'errors' => [], 'user_errors' => [], 'throttled' => false, 'cost' => null, 'error' => null];
	$json = json_decode($body, true);
	if (!is_array($json)) {
		$out['error'] = $status === 200 ? 'invalid_json' : "http_$status";
		return $out;
	}
	$out['data'] = is_array($json['data'] ?? null) ? $json['data'] : null;
	$out['cost'] = is_array($json['extensions']['cost'] ?? null) ? $json['extensions']['cost'] : null;

	// Shopify sends errors as a list of objects, but as a plain string on e.g. a bad token.
	$errors = $json['errors'] ?? [];
	foreach (is_array($errors) ? $errors : [$errors] as $e) {
		$code = is_array($e) && is_string($e['extensions']['code'] ?? null) ? $e['extensions']['code'] : null;
		$out['errors'][] = ['message' => shopify_client_text(is_array($e) ? ($e['message'] ?? '') : $e), 'code' => $code];
		$out['throttled'] = $out['throttled'] || $code === 'THROTTLED';
	}
	if ($out['data'] !== null) {
		shopify_collect_user_errors($out['data'], '', $out['user_errors']);
	}

	if ($status !== 200) {
		$out['error'] = "http_$status";
	} elseif ($out['errors']) {
		$out['error'] = $out['throttled'] ? 'throttled' : 'graphql_errors';
	} elseif ($out['user_errors']) {
		$out['error'] = 'user_errors';
	} elseif ($out['data'] === null) {
		$out['error'] = 'invalid_json';
	} else {
		$out['ok'] = true;
	}
	return $out;
}

/**
 * Seconds to wait before a request of $cost points fits the shop's cost bucket.
 * The bucket is what Shopify last reported in extensions.cost.throttleStatus, plus
 * "at", the unix time it was observed; nothing about the bucket size is hardcoded.
 *
 * @param array{maximumAvailable?: float, currentlyAvailable?: float, restoreRate?: float, at?: float} $bucket
 */
function shopify_throttle_delay(array $bucket, float $cost, float $now): float
{
	$max = $bucket['maximumAvailable'] ?? null;
	$current = $bucket['currentlyAvailable'] ?? null;
	if (!is_numeric($max) || !is_numeric($current) || $max <= 0) {
		return 0.0;
	}
	$rate = is_numeric($bucket['restoreRate'] ?? null) ? max(0.0, (float) $bucket['restoreRate']) : 0.0;
	$elapsed = max(0.0, $now - (float) ($bucket['at'] ?? $now));
	// A query costing more than the bucket holds can only wait for a full bucket.
	$need = min($cost, (float) $max);
	$available = min((float) $max, (float) $current + $rate * $elapsed);
	if ($available >= $need) {
		return 0.0;
	}
	if ($rate <= 0) {
		return SHOPIFY_THROTTLE_FALLBACK_DELAY;
	}
	return ($need - $available) / $rate;
}

/**
 * Starts a bulk query (bulkOperationRunQuery). The shop's recent bulk operations are read
 * first; a start that may have reached Shopify is reconciled by reading them again: exactly
 * one new QUERY operation with this query is this start's, whatever its status (a failed
 * one is reported, not started again); none means it did not start; more than one is
 * ambiguous and ends as uncertain. Since 2026-01 an app may run up to five bulk queries per
 * shop at once; with one worker per tenant another start of the same query is not expected.
 * Never resent blind.
 *
 * @param string $bulkQuery The inner query Shopify runs, e.g. "{ products { edges { node { id } } } }".
 * @param array $opts As shopify_graphql(); retry_mode and reconcile are set here.
 * @return array shopify_graphql()'s result plus bulk: array{id: string, status: string}|null.
 */
function shopify_bulk_start(array $shop, string $bulkQuery, array $opts = []): array
{
	$clock = $opts['clock'] ?? fn() => microtime(true);
	$opts['deadline'] = (float) ($opts['deadline'] ?? $clock() + SHOPIFY_DEFAULT_BUDGET);
	$recent = fn(): array => shopify_graphql($shop, 'bulkOperationsRecent', SHOPIFY_BULK_RECENT_QUERY, [], ['retry_mode' => 'read'] + $opts);
	$before = $recent();
	if (!$before['ok']) {
		return $before + ['bulk' => null];
	}
	$known = array_column($before['data']['bulkOperations']['nodes'] ?? [], 'id');
	$opts['retry_mode'] = 'reconcile';
	$opts['reconcile'] = function () use ($recent, $known, $bulkQuery): array {
		$read = $recent();
		if (!$read['ok']) {
			return ['state' => 'unknown'];
		}
		$new = [];
		foreach ($read['data']['bulkOperations']['nodes'] ?? [] as $node) {
			// Whitespace is normalised in case Shopify stores the query reformatted.
			$same = preg_replace('/\s+/', ' ', trim((string) ($node['query'] ?? ''))) === preg_replace('/\s+/', ' ', trim($bulkQuery));
			if ($same && ($node['type'] ?? '') === 'QUERY' && !in_array($node['id'] ?? null, $known, true)) {
				$new[] = ['id' => $node['id'], 'status' => $node['status'] ?? null];
			}
		}
		if (count($new) > 1) {
			return ['state' => 'unknown'];
		}
		return $new ? ['state' => 'applied', 'data' => ['bulkOperationRunQuery' => ['bulkOperation' => $new[0], 'userErrors' => []]]] : ['state' => 'absent'];
	};
	$result = shopify_graphql($shop, 'bulkOperationRunQuery', SHOPIFY_BULK_START_MUTATION, ['query' => $bulkQuery], $opts);
	$result['bulk'] = $result['ok'] ? ($result['data']['bulkOperationRunQuery']['bulkOperation'] ?? null) : null;
	return $result;
}

/**
 * Reads a bulk operation's state.
 *
 * @param string $id BulkOperation GID.
 * @param array $opts As shopify_graphql(); retry_mode is forced to read.
 * @return array shopify_graphql()'s result plus
 *   bulk: array{id: string, status: string, errorCode: string|null, objectCount: string,
 *     fileSize: string|null, url: string|null, partialDataUrl: string|null}|null,
 *   done: bool  True once the status is terminal (COMPLETED, FAILED, CANCELED, EXPIRED).
 */
function shopify_bulk_poll(array $shop, string $id, array $opts = []): array
{
	$opts['retry_mode'] = 'read';
	$result = shopify_graphql($shop, 'bulkOperation', SHOPIFY_BULK_POLL_QUERY, ['id' => $id], $opts);
	$result['bulk'] = $result['ok'] ? ($result['data']['bulkOperation'] ?? null) : null;
	$result['done'] = in_array($result['bulk']['status'] ?? '', SHOPIFY_BULK_TERMINAL, true);
	return $result;
}

/**
 * Downloads a finished bulk operation's JSONL file to $path. The file appears only when
 * the download is complete; a failure leaves no partial file behind. The signed URL is
 * a credential, so only its host is logged.
 *
 * @param array{
 *   deadline?: float,      As in shopify_graphql().
 *   correlation?: array,   Logged with the download.
 *   download?: callable,   Test seam, same contract as shopify_http_download().
 *   clock?: callable,      Test seam.
 *   log?: callable,        Test seam.
 * } $opts
 * @return array{ok: bool, path: string, bytes: int, status: int, error: string|null}
 */
function shopify_bulk_download(string $url, string $path, array $opts = []): array
{
	if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
		throw new InvalidArgumentException('Bulk download URL must be https');
	}
	$clock = $opts['clock'] ?? fn() => microtime(true);
	$log = $opts['log'] ?? 'shopify_client_log';
	$remaining = (float) ($opts['deadline'] ?? $clock() + SHOPIFY_DEFAULT_BUDGET) - $clock();
	$out = ['ok' => false, 'path' => $path, 'bytes' => 0, 'status' => 0, 'error' => null];
	if ($remaining <= 0) {
		return array_merge($out, ['error' => 'deadline']);
	}

	// Checked up front so an unusable path fails cleanly instead of warning in fopen().
	$dir = dirname($path);
	if (is_dir($path) || !is_dir($dir) || !is_writable($dir)) {
		return array_merge($out, ['error' => 'file']);
	}
	// Own temporary file per download, so overlapping downloads to one target never mix.
	$tmp = $path . '.' . bin2hex(random_bytes(6)) . '.part';
	$fh = fopen($tmp, 'xb');
	if ($fh === false) {
		return array_merge($out, ['error' => 'file']);
	}
	$started = $clock();
	$resp = ($opts['download'] ?? 'shopify_http_download')($url, $fh, min(SHOPIFY_CONNECT_TIMEOUT, $remaining), $remaining);
	fclose($fh);
	$out['status'] = $resp['status'];
	if ($resp['errno'] !== 0 || $resp['status'] !== 200) {
		$out['error'] = $resp['errno'] !== 0 ? 'transport' : 'http_' . $resp['status'];
	} elseif (!rename($tmp, $path)) {
		$out['error'] = 'file';
	} else {
		$out = array_merge($out, ['ok' => true, 'bytes' => (int) filesize($path)]);
	}
	if (!$out['ok'] && is_file($tmp)) {
		unlink($tmp);
	}
	$log([
		'op' => 'bulkDownload', 'host' => parse_url($url, PHP_URL_HOST), 'status' => $resp['status'], 'error' => $out['error'],
		'curl_errno' => $resp['errno'], 'bytes' => $out['bytes'], 'ms' => (int) round(($clock() - $started) * 1000),
		'correlation' => $opts['correlation'] ?? [],
	]);
	return $out;
}

/**
 * POSTs one request with curl. "sent" is false only when the request cannot have
 * reached Shopify (no DNS or TCP connection), which makes a resend safe.
 *
 * @return array{status: int, body: string, errno: int, error: string, sent: bool, retry_after: float|null}
 */
function shopify_http_post(string $url, array $headers, string $body, float $connectTimeout, float $timeout): array
{
	$retryAfter = null;
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $body,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT_MS => max(1, (int) ($connectTimeout * 1000)),
		CURLOPT_TIMEOUT_MS => max(1, (int) ($timeout * 1000)),
		CURLOPT_HEADERFUNCTION => function ($handle, string $line) use (&$retryAfter): int {
			if (preg_match('/^Retry-After:\s*(\d+(?:\.\d+)?)\s*$/i', $line, $m)) {
				$retryAfter = (float) $m[1];
			}
			return strlen($line);
		},
	]);
	$respBody = curl_exec($ch);
	$errno = curl_errno($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	// pretransfer is 0 when the connection never got far enough to send a byte.
	$sent = $errno === 0 || (float) curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME) > 0;
	return [
		'status' => $status, 'body' => is_string($respBody) ? $respBody : '', 'errno' => $errno,
		'error' => curl_error($ch), 'sent' => $sent, 'retry_after' => $retryAfter,
	];
}

/**
 * Streams a GET response into an open file handle with curl.
 *
 * @param resource $fh
 * @return array{status: int, errno: int, error: string}
 */
function shopify_http_download(string $url, $fh, float $connectTimeout, float $timeout): array
{
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_FILE => $fh,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_CONNECTTIMEOUT_MS => max(1, (int) ($connectTimeout * 1000)),
		CURLOPT_TIMEOUT_MS => max(1, (int) ($timeout * 1000)),
	]);
	curl_exec($ch);
	return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'errno' => curl_errno($ch), 'error' => curl_error($ch)];
}

/**
 * Sorts one attempt: ok; fatal (do not retry: auth, bad query, userErrors);
 * not_sent (Shopify certainly did not execute it: throttled, 429, no connection);
 * uncertain (it may have executed: 5xx, timeout after connecting, garbled 200).
 */
function shopify_classify_attempt(array $resp, ?array $parsed): string
{
	if ($resp['errno'] !== 0) {
		return $resp['sent'] ? 'uncertain' : 'not_sent';
	}
	if ($parsed['ok']) {
		return 'ok';
	}
	if ($resp['status'] === 429 || $parsed['throttled']) {
		return 'not_sent';
	}
	$internal = in_array('INTERNAL_SERVER_ERROR', array_column($parsed['errors'], 'code'), true);
	if ($resp['status'] >= 500 || $internal || ($resp['status'] === 200 && $parsed['error'] === 'invalid_json')) {
		return 'uncertain';
	}
	return 'fatal';
}

/** Seconds to wait before retry number $retry (1-based). */
function shopify_retry_delay(array $resp, ?array $parsed, int $retry): float
{
	if ($parsed !== null && $parsed['throttled']) {
		$status = $parsed['cost']['throttleStatus'] ?? null;
		$bucket = ['at' => 0.0] + (is_array($status) ? $status : []);
		$requested = $parsed['cost']['requestedQueryCost'] ?? null;
		$cost = is_numeric($requested) ? (float) $requested : SHOPIFY_DEFAULT_COST_ESTIMATE;
		return max(0.25, shopify_throttle_delay($bucket, $cost, 0.0));
	}
	if ($resp['status'] === 429 && $resp['retry_after'] !== null) {
		return (float) $resp['retry_after'];
	}
	return (float) (2 ** ($retry - 1));
}

/**
 * True when the document holds a mutation operation. Only text outside every { } block is
 * looked at (definition headers), after strings and comments are removed, so separators
 * GraphQL ignores (commas, BOM) and leading fragments cannot hide the keyword. A false
 * positive only makes the caller pick a stricter retry mode.
 */
function shopify_graphql_is_mutation(string $query): bool
{
	$code = shopify_graphql_code($query);
	$top = '';
	$depth = 0;
	for ($i = 0, $n = strlen($code); $i < $n; $i++) {
		$c = $code[$i];
		if ($c === '{') {
			$depth++;
		} elseif ($c === '}') {
			$depth = max(0, $depth - 1);
		} elseif ($depth === 0) {
			$top .= $c;
		}
	}
	return (bool) preg_match('/(?<![$\w])mutation(?!\w)/', $top);
}

/** The document without string literals and comments, so neither can hide or fake a keyword. */
function shopify_graphql_code(string $query): string
{
	return (string) preg_replace_callback(
		'/"""(?:[^"\\\\]|\\\\.|"(?!""))*"""|"(?:[^"\\\\\n]|\\\\.)*"|#[^\n]*/s',
		fn(array $m): string => $m[0][0] === '#' ? ' ' : '""',
		$query
	);
}

/**
 * Name of the must-not-run-twice mutation the document performs, or null: one of
 * SHOPIFY_RECONCILE_OPS (aliases included), or an orderUpdate that sets a note.
 */
function shopify_graphql_needs_reconcile(string $query, array $vars): ?string
{
	// Checked whether or not the document parses as a mutation: stricter is the safe side.
	$code = shopify_graphql_code($query);
	foreach (SHOPIFY_RECONCILE_OPS as $field) {
		if (preg_match('/\b' . $field . '\b/', $code)) {
			return $field;
		}
	}
	$setsNote = preg_match('/\bnote\s*:/', $code) || str_contains(json_encode($vars), '"note":');
	return preg_match('/\borderUpdate\b/', $code) && $setsNote ? 'orderUpdate (note)' : null;
}

/** Admin GraphQL endpoint for the shop. Only *.myshopify.com hosts get the access token. */
function shopify_graphql_url(array $shop): string
{
	$domain = strtolower((string) ($shop['domain'] ?? ''));
	if (!preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', $domain)) {
		throw new InvalidArgumentException("Shop domain '$domain' is not a *.myshopify.com domain");
	}
	// Pinned: the client and its schema tests are written against this one version.
	$version = (string) ($shop['api_version'] ?? SHOPIFY_API_VERSION);
	if ($version !== SHOPIFY_API_VERSION) {
		throw new InvalidArgumentException("Shop API version '$version' is not the pinned " . SHOPIFY_API_VERSION);
	}
	return "https://$domain/admin/api/$version/graphql.json";
}

/**
 * The shop's Admin API access token: opts.access_token, else WP01's shopify_secret() once
 * S01b is merged. The token itself never appears in a log line or exception message.
 */
function shopify_client_access_token(array $shop, array $opts): string
{
	$token = $opts['access_token'] ?? null;
	if ($token === null && function_exists('shopify_secret')) {
		$token = shopify_secret((string) ($shop['shop_key'] ?? ''), 'access_token');
	}
	if (!is_string($token) || $token === '') {
		throw new RuntimeException('No Shopify access token for shop ' . ($shop['shop_key'] ?? $shop['domain'] ?? '?'));
	}
	return $token;
}

/**
 * Per-process pacing state for one shop: the last reported bucket and each operation's
 * last requested cost.
 *
 * @return array{bucket: array, costs: array<string, float>}
 */
function shopify_client_state(string $shopKey, ?array $set = null): array
{
	static $state = [];
	if ($set !== null) {
		$state[$shopKey] = $set;
	}
	return $state[$shopKey] ?? ['bucket' => [], 'costs' => []];
}

/** Stores the bucket and cost Shopify reported for an attempt. */
function shopify_client_remember(string $shopKey, string $op, ?array $cost, float $now): void
{
	if ($cost === null) {
		return;
	}
	$state = shopify_client_state($shopKey);
	if (is_array($cost['throttleStatus'] ?? null)) {
		$state['bucket'] = ['at' => $now] + $cost['throttleStatus'];
	}
	if (is_numeric($cost['requestedQueryCost'] ?? null)) {
		$state['costs'][$op] = (float) $cost['requestedQueryCost'];
	}
	shopify_client_state($shopKey, $state);
}

/** Sleeps $seconds unless that leaves too little time for an attempt; returns false then. */
function shopify_client_wait(float $seconds, float $deadline, callable $clock, callable $sleep): bool
{
	if ($clock() + $seconds + SHOPIFY_MIN_ATTEMPT_BUDGET > $deadline) {
		return false;
	}
	$sleep($seconds);
	return true;
}

/** Ends a call that ran out of retries or time: a retryable failure for the job runtime. */
function shopify_client_stop(array $result, string $error): array
{
	return array_merge($result, ['ok' => false, 'outcome' => 'failed', 'error' => $error, 'retryable' => true]);
}

/**
 * Calls the caller's read-first reconcile callback. An Exception counts as "unknown" and is
 * returned for the log; an Error (a bug in the callback) is not caught.
 */
function shopify_client_reconcile(callable $reconcile, string $op, array $attempt, float $deadline): array
{
	try {
		$found = $reconcile([
			'op' => $op, 'status' => $attempt['status'], 'error' => $attempt['error'],
			'attempts' => $attempt['attempts'], 'deadline' => $deadline,
		]);
	} catch (Exception $e) {
		return ['state' => 'unknown', 'exception' => get_class($e) . ': ' . $e->getMessage()];
	}
	return is_array($found) ? $found : ['state' => 'unknown'];
}

/**
 * Collects every userErrors / *UserErrors entry under $node, recording the path of the
 * payload it sits in (e.g. "orderCreate").
 */
function shopify_collect_user_errors(array $node, string $path, array &$out): void
{
	foreach ($node as $key => $value) {
		if (!is_array($value)) {
			continue;
		}
		if (is_string($key) && preg_match('/^(userErrors|[a-zA-Z]+UserErrors)$/', $key)) {
			foreach ($value as $e) {
				$e = is_array($e) ? $e : ['message' => $e];
				$out[] = [
					'path' => $path, 'field' => $e['field'] ?? null,
					'message' => shopify_client_text($e['message'] ?? ''), 'code' => is_string($e['code'] ?? null) ? $e['code'] : null,
				];
			}
			continue;
		}
		shopify_collect_user_errors($value, $path === '' ? (string) $key : "$path.$key", $out);
	}
}

/** A response value as text: scalars as they are, anything else as JSON. */
function shopify_client_text(mixed $value): string
{
	return is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value);
}

/**
 * Default per-attempt log: one JSON line to the PHP error log, without request or
 * response bodies and without the token. Replaced by WP02's shopify_call_log once merged.
 */
function shopify_client_log(array $entry): void
{
	error_log('shopify_graphql ' . json_encode($entry, JSON_UNESCAPED_SLASHES));
}
