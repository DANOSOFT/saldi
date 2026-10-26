<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/client.php --- ver 5.0.0 --- 2026-10-26 ---
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
// 20261023 CDX/LH Tighten reconcile results, retry state, mutation guards and bulk polling (SD-747).
// 20261023 CL/SZ Follow-ups from the #828 review: exact bulk-start matching, X-Request-Id and redacted
//                bodies for WP02's call log, @idempotent(key:) in the idempotent-mode example (SD-747).
// 20261026 CL/SZ Normalise splits names, numbers and "..." into separate tokens, reads block-string
//                escapes and BOMs as GraphQL does and refuses bad escapes and leading zeros; a body
//                that can't be JSON-encoded is reported as redact_error, and error-log lines keep
//                their metadata when a value isn't valid UTF-8 (SD-747, #862 review).

const SHOPIFY_API_VERSION = '2026-07';
const SHOPIFY_MAX_RETRIES = 3;
// Error-log lines: a stray invalid byte (say in a correlation id) is replaced, never an empty line.
const SHOPIFY_LOG_JSON = JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
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
// bulkOperations lists newest first by default; reverse: true would return the oldest.
const SHOPIFY_BULK_RECENT_QUERY = 'query bulkOperationsRecent {
	bulkOperations(first: 10, sortKey: CREATED_AT) {
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
 * Retry modes: "read" for queries; "idempotent" for mutations that are safe to send twice,
 * e.g. an absolute inventorySetQuantities that carries the same @idempotent(key: $key) on every
 * attempt (`inventorySetQuantities(input: $input) @idempotent(key: $key) { userErrors { message } }`),
 * or metafieldsSet; "reconcile" for mutations that must
 * not run twice (SHOPIFY_RECONCILE_OPS, a note orderUpdate; both are detected in the document
 * itself, whatever $op says). In reconcile mode an attempt
 * that may have reached Shopify is followed by opts.reconcile, never by a blind resend.
 * The callback gets array{op, status, error, attempts, deadline} and returns
 * array{state: string, data?: array}:
 * state "applied" requires array data = the effect found on Shopify, or "absent" when it
 * is safe to send again. Missing, null or scalar data on "applied", any other state, or a
 * runtime exception ends the call with outcome "uncertain", and then the caller must read
 * before it sends again. LogicException and Error propagate as programming errors.
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
 *   log?: callable,        Receives one array per attempt; default shopify_client_log().
 *   redact?: callable,     Redacts the logged bodies, called with the body and the secrets to strip;
 *                          default WP02's shopify_redact once it exists. Without one, no bodies are logged.
 * } $opts
 * @return array{
 *   ok: bool,                True when Shopify returned data without errors (or reconcile found it applied).
 *   outcome: string,         ok | reconciled | failed | uncertain.
 *   data: array|null,        The response's data, or the reconcile callback's data.
 *   errors: list<array{message: string, code: string|null, path: list<string|int>|null}>,
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
	// Checked before anything is sent: a bad logging option found afterwards would cost the result.
	foreach (['log', 'redact'] as $seam) {
		if (isset($opts[$seam]) && !is_callable($opts[$seam])) {
			throw new InvalidArgumentException("opts.$seam must be callable");
		}
	}

	$transport = $opts['transport'] ?? 'shopify_http_post';
	$clock = $opts['clock'] ?? fn() => microtime(true);
	$sleep = $opts['sleep'] ?? fn(float $s) => usleep((int) round($s * 1e6));
	$log = $opts['log'] ?? null;
	$redact = $opts['redact'] ?? (function_exists('shopify_redact') ? 'shopify_redact' : null);
	$correlation = $opts['correlation'] ?? [];
	$deadline = (float) ($opts['deadline'] ?? $clock() + SHOPIFY_DEFAULT_BUDGET);
	$shopKey = (string) ($shop['shop_key'] ?? $shop['domain'] ?? '');

	$url = shopify_graphql_url($shop);
	$token = shopify_client_access_token($shop, $opts);
	// The token is passed to the logger only as a secret to strip, never inside an entry.
	$log ??= fn(array $entry) => shopify_client_log($entry, [$token]);
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

		$result = array_merge($result, ['data' => null, 'errors' => [], 'user_errors' => [], 'cost' => null]);
		$started = $clock();
		$resp = $transport($url, $headers, $body, min(SHOPIFY_CONNECT_TIMEOUT, $remaining), min(SHOPIFY_TOTAL_TIMEOUT, $remaining));
		$result['attempts']++;
		$parsed = $resp['errno'] === 0 ? shopify_parse_graphql($resp['status'], $resp['body']) : null;
		$class = shopify_classify_attempt($resp, $parsed);
		// A payload or an error below a root means the mutation may have run: read before anything else.
		if ($class === 'fatal' && $mode === 'reconcile' && $isMutation && shopify_graphql_has_payload($parsed)) {
			$class = 'uncertain';
		}
		if ($parsed !== null) {
			$result = array_merge($result, array_intersect_key($parsed, array_flip(['data', 'errors', 'user_errors', 'cost'])));
			shopify_client_remember($shopKey, $op, $parsed['cost'], $clock());
		}
		$result['status'] = $resp['status'];
		$result['error'] = $parsed['error'] ?? 'transport';
		shopify_client_emit($log, [
			'op' => $op, 'shop' => $shopKey, 'attempt' => $result['attempts'], 'mode' => $mode,
			'status' => $resp['status'], 'class' => $class, 'error' => $class === 'ok' ? null : $result['error'],
			'curl_errno' => $resp['errno'], 'curl_error' => $resp['error'] ?: null, 'ms' => (int) round(($clock() - $started) * 1000),
			'cost' => $parsed['cost']['actualQueryCost'] ?? $parsed['cost']['requestedQueryCost'] ?? null,
			'available' => $parsed['cost']['throttleStatus']['currentlyAvailable'] ?? null,
			'correlation' => $correlation, 'direction' => 'out', 'request_id' => $resp['request_id'] ?? null,
		] + shopify_client_logged_bodies($redact, ['query' => $query, 'variables' => $vars], $resp['body'], [$token]));

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
			shopify_client_emit($log, ['op' => $op, 'shop' => $shopKey, 'event' => 'reconcile', 'attempt' => $result['attempts'],
				'state' => $found['state'] ?? null, 'correlation' => $correlation,
				'exception' => isset($found['exception']) ? str_replace($token, '[redacted]', $found['exception']) : null]);
			if (($found['state'] ?? null) === 'applied' && is_array($found['data'] ?? null)) {
				return array_merge($result, ['ok' => true, 'outcome' => 'reconciled', 'error' => null,
					'data' => $found['data'], 'errors' => [], 'user_errors' => []]);
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
 *   errors: list<array{message: string, code: string|null, path: list<string|int>|null}>,
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
		$out['errors'][] = [
			'message' => shopify_client_text(is_array($e) ? ($e['message'] ?? '') : $e), 'code' => $code,
			'path' => is_array($e) && is_array($e['path'] ?? null) ? $e['path'] : null,
		];
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
 * one new QUERY operation, matching this query token for token (shopify_graphql_normalise()),
 * is this start's, whatever its status (a failed one is reported, not started again); no new
 * QUERY operation means it did not start; anything else (a new one that differs, or several)
 * is ambiguous and ends as uncertain. Since 2026-01 an app may run up to five bulk queries per
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
		$wanted = shopify_graphql_normalise($bulkQuery);
		$new = 0;
		$mine = [];
		foreach ($read['data']['bulkOperations']['nodes'] ?? [] as $node) {
			if (($node['type'] ?? '') !== 'QUERY' || in_array($node['id'] ?? null, $known, true)) {
				continue;
			}
			$new++;
			if ($wanted !== null && shopify_graphql_normalise((string) ($node['query'] ?? '')) === $wanted) {
				$mine[] = ['id' => $node['id'], 'status' => $node['status'] ?? null];
			}
		}
		if ($new === 0) {
			return ['state' => 'absent'];
		}
		// Exactly one new operation, and it is ours: anything else (a new one that differs, or
		// several) may be this start under another shape, so it is never resent.
		return $new === 1 && count($mine) === 1
			? ['state' => 'applied', 'data' => ['bulkOperationRunQuery' => ['bulkOperation' => $mine[0], 'userErrors' => []]]]
			: ['state' => 'unknown'];
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
 *   done: bool  True once the status is terminal (COMPLETED, FAILED, CANCELED, EXPIRED),
 *               or the operation is not found (ok: false, error: not_found).
 */
function shopify_bulk_poll(array $shop, string $id, array $opts = []): array
{
	$opts['retry_mode'] = 'read';
	$result = shopify_graphql($shop, 'bulkOperation', SHOPIFY_BULK_POLL_QUERY, ['id' => $id], $opts);
	$result['bulk'] = $result['ok'] ? ($result['data']['bulkOperation'] ?? null) : null;
	if ($result['ok'] && $result['bulk'] === null) {
		return array_merge($result, ['ok' => false, 'outcome' => 'failed', 'error' => 'not_found', 'done' => true]);
	}
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
	if (isset($opts['log']) && !is_callable($opts['log'])) {
		throw new InvalidArgumentException('opts.log must be callable');
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
	shopify_client_emit($log, [
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
 * @return array{status: int, body: string, errno: int, error: string, sent: bool, retry_after: float|null, request_id: string|null}
 */
function shopify_http_post(string $url, array $headers, string $body, float $connectTimeout, float $timeout): array
{
	$retryAfter = null;
	$requestId = null;
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $body,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT_MS => max(1, (int) ($connectTimeout * 1000)),
		CURLOPT_TIMEOUT_MS => max(1, (int) ($timeout * 1000)),
		CURLOPT_HEADERFUNCTION => function ($handle, string $line) use (&$retryAfter, &$requestId): int {
			if (preg_match('/^Retry-After:\s*(\d+(?:\.\d+)?)\s*$/i', $line, $m)) {
				$retryAfter = (float) $m[1];
			} elseif (preg_match('/^X-Request-Id:\s*([\w.:-]{1,128})\s*$/i', $line, $m)) {
				$requestId = $m[1];
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
		'error' => curl_error($ch), 'sent' => $sent, 'retry_after' => $retryAfter, 'request_id' => $requestId,
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
 * uncertain (it may have executed: 5xx, timeout after connecting, garbled 200). In reconcile
 * mode, shopify_graphql() also treats a mutation's errors next to payload data or below a
 * null root as uncertain.
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

/** True when top-level errors carry a payload or point below a returned root (even null). */
function shopify_graphql_has_payload(?array $parsed): bool
{
	if ($parsed === null || !$parsed['errors'] || !is_array($parsed['data'])) {
		return false;
	}
	foreach ($parsed['data'] as $value) {
		if ($value !== null) {
			return true;
		}
	}
	foreach ($parsed['errors'] as $error) {
		$path = $error['path'] ?? null;
		if (is_array($path) && count($path) > 1 && is_string($path[0] ?? null) && array_key_exists($path[0], $parsed['data'])) {
			return true;
		}
	}
	return false;
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

/**
 * The document without string literals and comments, so neither can hide or fake a keyword.
 * Comments and plain strings end at \r or \n, as in GraphQL. Possessive quantifiers keep long
 * strings from exhausting the regex stack; if stripping still fails, nothing may be sent,
 * because the guards would otherwise inspect an empty document.
 */
function shopify_graphql_code(string $query): string
{
	$code = preg_replace_callback(
		'/"""(?:[^"\\\\]++|\\\\.|"(?!""))*+"""|"(?:[^"\\\\\r\n]++|\\\\.)*+"|#[^\r\n]*+/s',
		fn(array $m): string => $m[0][0] === '#' ? ' ' : '""',
		$query
	);
	if ($code === null) {
		throw new RuntimeException('GraphQL document could not be checked (' . preg_last_error_msg() . '); not sent');
	}
	return $code;
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
	$setsNote = preg_match('/\bnote(?:\s|,|\xEF\xBB\xBF)*+:/', $code) || str_contains(json_encode($vars), '"note":');
	return preg_match('/\borderUpdate\b/', $code) && $setsNote ? 'orderUpdate (note)' : null;
}

/**
 * The document as a list of tokens joined by single spaces: string literals stay exact, while
 * whitespace, commas and comments between tokens do not count. Two documents are the same query
 * when this is equal. Null when the document cannot be read.
 */
function shopify_graphql_normalise(string $query): ?string
{
	// Every byte must belong to a token (string, punctuator, name or number), an ignored run
	// (whitespace, commas, BOMs) or a comment; anything else (a stray quote, an unterminated
	// string, a bad escape, a leading zero, a number run into a name) lands in the last group and
	// makes the document unreadable. In a block string only \""" is an escape. Names, numbers
	// and "..." are separate tokens, so "id..." and "id ..." normalise alike.
	$found = preg_match_all(
		'/"""(?:[^"\\\\]++|\\\\"""|\\\\|"(?!""))*+"""|(?!""")"(?:[^"\\\\\r\n]++|\\\\(?:["\\\\\/bfnrt]|u[0-9A-Fa-f]{4}|u\{[0-9A-Fa-f]++\}))*+"|#[^\r\n]*+|(?:[\s,]|\xEF\xBB\xBF)++|\.\.\.|[!$&():=@\[\]{}|]|[_A-Za-z][_0-9A-Za-z]*+|-?(?:0|[1-9]\d*+)(?:\.\d++)?+(?:[eE][+-]?+\d++)?+(?![_A-Za-z\d.])|(.)/s',
		$query,
		$m
	);
	if ($found === false || array_filter($m[1], fn(string $bad): bool => $bad !== '')) {
		return null;
	}
	$tokens = array_filter($m[0], fn(string $t): bool => $t[0] !== '#' && trim($t, " \t\r\n\v\f,\xEF\xBB\xBF") !== '');
	return implode(' ', $tokens);
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
 * returned for the log; LogicException and Error (bugs in the callback) are not swallowed.
 */
function shopify_client_reconcile(callable $reconcile, string $op, array $attempt, float $deadline): array
{
	try {
		$found = $reconcile([
			'op' => $op, 'status' => $attempt['status'], 'error' => $attempt['error'],
			'attempts' => $attempt['attempts'], 'deadline' => $deadline,
		]);
	} catch (LogicException $e) {
		throw $e;
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
 * The request and response bodies for the call log, redacted and base64 JSON. Null without a
 * redactor, and null (with only the exception class noted) when the redactor fails or its
 * output can't be JSON-encoded (invalid UTF-8): the call has already been sent, so neither may
 * cost its result.
 *
 * @return array{request_b64: string|null, response_b64: string|null, redact_error?: string}
 */
function shopify_client_logged_bodies(?callable $redact, array $request, string $responseBody, array $secrets): array
{
	if ($redact === null) {
		return ['request_b64' => null, 'response_b64' => null];
	}
	try {
		$encode = fn(mixed $body): string => base64_encode((string) json_encode($redact($body, $secrets), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
		return ['request_b64' => $encode($request), 'response_b64' => $encode(json_decode($responseBody, true) ?? $responseBody)];
	} catch (Throwable $e) {
		return ['request_b64' => null, 'response_b64' => null, 'redact_error' => get_class($e)];
	}
}

/**
 * Hands one entry to the logger. A logger that fails must not cost the call's result, so the
 * entry then goes to the PHP error log without bodies, with only the exception class noted.
 */
function shopify_client_emit(callable $log, array $entry): void
{
	try {
		$log($entry);
	} catch (Throwable $e) {
		unset($entry['request_b64'], $entry['response_b64']);
		$entry['log_error'] = get_class($e);
		try {
			error_log('shopify_graphql ' . json_encode($entry, SHOPIFY_LOG_JSON));
		} catch (Throwable) {
			// Both destinations failed: the entry is lost, the call's result is not.
		}
	}
}

/**
 * Default log. Call entries go to WP02's shopify_call_log() once it exists; a failure there
 * falls back to the error log, because losing a call's result could lead to a resend.
 * Otherwise, and for reconcile events, one JSON line goes to the PHP error log, without
 * request or response bodies.
 */
function shopify_client_log(array $entry, array $secrets = []): void
{
	if (!isset($entry['event']) && function_exists('shopify_call_log')) {
		try {
			shopify_call_log($entry, $secrets);
			return;
		} catch (Throwable $e) {
			$entry['call_log_error'] = get_class($e);
		}
	}
	unset($entry['request_b64'], $entry['response_b64']);
	error_log('shopify_graphql ' . json_encode($entry, SHOPIFY_LOG_JSON));
}
