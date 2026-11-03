<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/oauth.php --- ver 5.1.0 --- 2026.11.03
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
// App install of a shop (SD-790): Shopify's authorization code grant for an app in Danosoft's
// Partner organization with Custom distribution, one app per shop. The app's client ID is the
// shop's secret client_id; its client secret is the shop's webhook_secret, because Shopify signs an
// app's webhooks with that same secret. The token is offline and never expires: no grant_options,
// no expiring=1 (custom apps are outside Shopify's expiring-token requirement). APP_UNINSTALLED
// deletes it again (shop.uninstalled).
//
// 20261103 CL/MJ SD-790: created.
// 20261103 CL/MJ SD-790 review: the signature also verifies over form-encoded values, as Shopify's libraries sign them.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/events.php';

/** Per-shop secret with the app's client ID (the client secret is SHOPIFY_WEBHOOK_SECRET). */
const SHOPIFY_OAUTH_CLIENT_ID = 'client_id';
/** Per-shop setting with the pending install's state, "<nonce>:<unix expiry>", used once. */
const SHOPIFY_OAUTH_STATE = 'oauth_state';
/** Per-shop setting with the UTC time the token was stored; an older uninstall leaves it alone. */
const SHOPIFY_OAUTH_INSTALLED_AT = 'installed_at';
const SHOPIFY_OAUTH_PATH = '/api/shopify/oauth.php';
const SHOPIFY_OAUTH_STATE_TTL = 600;
/** A signed request older (or newer) than this is refused, so a captured link can't be replayed later. */
const SHOPIFY_OAUTH_MAX_AGE = 3600;
/** What the module reads and writes; write_* includes read_*. */
const SHOPIFY_OAUTH_SCOPES = 'write_products,write_inventory,read_locations,write_orders,write_draft_orders,write_customers,write_merchant_managed_fulfillment_orders';

/**
 * Shopify's signature of an OAuth request: the hex HMAC-SHA256 of the other query parameters,
 * sorted by name and joined as name=value with &, the values as sent (form-encoded, as Shopify's
 * own libraries sign them) or not encoded (as its docs show). Null when a parameter is not a
 * single string.
 *
 * @param array<string, mixed> $query
 */
function shopify_oauth_query_hmac(array $query, string $secret, bool $encoded = false): ?string {
	unset($query['hmac'], $query['signature']);
	ksort($query, SORT_STRING);
	$pairs = array();
	foreach ($query as $name => $value) {
		if (!is_string($value)) {
			return null;
		}
		$pairs[] = $encoded ? shopify_oauth_form_encode((string)$name) . '=' . shopify_oauth_form_encode($value)
			: strtr((string)$name, array('%' => '%25', '&' => '%26', '=' => '%3D')) . '=' . strtr($value, array('%' => '%25', '&' => '%26'));
	}
	return hash_hmac('sha256', implode('&', $pairs), $secret);
}

/** A value encoded as shopify-app-js signs it (URLSearchParams, a space as %20): unreserved and * stay. */
function shopify_oauth_form_encode(string $value): string {
	return strtr(rawurlencode($value), array('%2A' => '*', '~' => '%7E'));
}

/** Whether the request carries Shopify's signature under $secret and a timestamp near $now. */
function shopify_oauth_verify(array $query, string $secret, int $now): bool {
	$hmac = $query['hmac'] ?? '';
	$timestamp = $query['timestamp'] ?? '';
	if ($secret === '' || !is_string($hmac) || $hmac === '' || !is_string($timestamp) || !preg_match('/^\d{1,12}$/', $timestamp)) {
		return false;
	}
	$hmac = strtolower($hmac);
	$plain = shopify_oauth_query_hmac($query, $secret);
	$encoded = shopify_oauth_query_hmac($query, $secret, true);
	return $plain !== null && (hash_equals($plain, $hmac) || hash_equals((string)$encoded, $hmac)) && abs($now - (int)$timestamp) <= SHOPIFY_OAUTH_MAX_AGE;
}

/**
 * One request to api/shopify/oauth.php. Without a code it is the install: a fresh state is stored
 * and the answer redirects to the shop's approval page. With a code it is the callback: the state
 * is used up, the code exchanged for the offline token, the token stored and a webhook.health job
 * queued, which subscribes the shop's webhooks. Answers:
 * - 302 authorize | installed, with location;
 * - 400 bad_request, 404 unknown_shop (shop is not a registered domain), 401 bad_signature | bad_state;
 * - 503 not_configured (no client ID, client secret or base URL), 502 exchange_failed.
 *
 * @param array<string, mixed> $query the request's query parameters
 * @param array{now?: int, transport?: callable} $opts transport: same contract as shopify_http_post()
 * @return array{status: int, outcome: string, location?: string}
 */
function shopify_oauth_request(array $query, array $opts = array()): array {
	$now = (int)($opts['now'] ?? time());
	$domain = $query['shop'] ?? '';
	if (!is_string($domain) || !preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', $domain)) {
		return array('status' => 400, 'outcome' => 'bad_request');
	}
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE domain = $1', array($domain))[0] ?? null;
	if ($shop === null) {
		shopify_event_log('oauth_unknown_shop', array('domain' => $domain));
		return array('status' => 404, 'outcome' => 'unknown_shop');
	}
	$key = (string)$shop['shop_key'];
	$clientId = shopify_secret($key, SHOPIFY_OAUTH_CLIENT_ID);
	$secret = shopify_secret($key, SHOPIFY_WEBHOOK_SECRET);
	$base = shopify_webhook_base_url();
	if ($clientId === null || $secret === null || $base === null) {
		shopify_event_log('oauth_not_configured', array('shop' => $key, 'client_id' => $clientId !== null, 'client_secret' => $secret !== null, 'base_url' => $base !== null));
		return array('status' => 503, 'outcome' => 'not_configured');
	}
	if (!shopify_oauth_verify($query, $secret, $now)) {
		shopify_event_log('oauth_bad_signature', array('shop' => $key));
		return array('status' => 401, 'outcome' => 'bad_signature');
	}
	if (!isset($query['code'])) {
		$state = bin2hex(random_bytes(16));
		shopify_db_transaction(function () use ($key, $state, $now) {
			shopify_save_secret($key, SHOPIFY_OAUTH_STATE, $state . ':' . ($now + SHOPIFY_OAUTH_STATE_TTL));
		});
		$params = array('client_id' => $clientId, 'scope' => SHOPIFY_OAUTH_SCOPES, 'redirect_uri' => $base . SHOPIFY_OAUTH_PATH, 'state' => $state);
		return array('status' => 302, 'outcome' => 'authorize', 'location' => "https://$domain/admin/oauth/authorize?" . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
	}
	$code = $query['code'];
	$given = $query['state'] ?? '';
	if (!is_string($code) || $code === '' || !is_string($given) || !shopify_oauth_use_state($key, $given, $now)) {
		shopify_event_log('oauth_bad_state', array('shop' => $key));
		return array('status' => 401, 'outcome' => 'bad_state');
	}
	$token = shopify_oauth_exchange($domain, $clientId, $secret, $code, $opts['transport'] ?? 'shopify_http_post');
	if ($token === null) {
		shopify_event_log('oauth_exchange_failed', array('shop' => $key));
		return array('status' => 502, 'outcome' => 'exchange_failed');
	}
	if (!function_exists('shopify_queue_enqueue')) {
		require_once __DIR__ . '/queue.php';
	}
	shopify_db_transaction(function () use ($shop, $key, $token, $now) {
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_oauth:$key"));
		shopify_save_secret($key, 'access_token', $token['access_token']);
		shopify_save_secret($key, SHOPIFY_OAUTH_INSTALLED_AT, gmdate('Y-m-d\TH:i:s\Z', $now));
		shopify_queue_enqueue('webhook.health', (int)$shop['id'], "$key:install:$now", array('slot' => 'install'), SHOPIFY_QUEUE_PRIORITY['webhook.health']);
	});
	shopify_event_log('oauth_installed', array('shop' => $key, 'scope' => $token['scope']));
	return array('status' => 302, 'outcome' => 'installed', 'location' => "https://$domain/admin/apps");
}

/**
 * Uses up the shop's pending state: true when $given is it and it hasn't expired. The stored
 * state is deleted either way, so a state answers one callback only.
 */
function shopify_oauth_use_state(string $key, string $given, int $now): bool {
	$rows = shopify_db_transaction(function () use ($key) {
		// the lock shopify_save_secret() takes for this setting
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_secret:shopify:$key:" . SHOPIFY_OAUTH_STATE));
		return shopify_db_rows('DELETE FROM settings WHERE var_grp = $1 AND var_name = $2 RETURNING var_value', array("shopify:$key", SHOPIFY_OAUTH_STATE));
	});
	$parts = explode(':', (string)($rows[0]['var_value'] ?? ''), 2);
	return count($rows) === 1 && count($parts) === 2 && $given !== '' && hash_equals($parts[0], $given) && (int)$parts[1] >= $now;
}

/**
 * Exchanges the authorization code for the shop's offline token. Null when Shopify refuses it or
 * can't be reached; the code, secret and token never reach a log line.
 *
 * @return array{access_token: string, scope: string}|null
 */
function shopify_oauth_exchange(string $domain, string $clientId, string $secret, string $code, callable $transport): ?array {
	$body = json_encode(array('client_id' => $clientId, 'client_secret' => $secret, 'code' => $code));
	$resp = $transport("https://$domain/admin/oauth/access_token", array('Content-Type: application/json', 'Accept: application/json'), (string)$body, SHOPIFY_CONNECT_TIMEOUT, SHOPIFY_TOTAL_TIMEOUT);
	$data = ($resp['status'] ?? 0) === 200 ? json_decode((string)($resp['body'] ?? ''), true) : null;
	if (!is_array($data) || !is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
		shopify_event_log('oauth_exchange_refused', array('domain' => $domain, 'status' => $resp['status'] ?? 0, 'errno' => $resp['errno'] ?? 0));
		return null;
	}
	return array('access_token' => $data['access_token'], 'scope' => is_string($data['scope'] ?? null) ? $data['scope'] : '');
}

/**
 * Handler of shop.uninstalled (APP_UNINSTALLED): deletes the shop's token and switches every flow
 * off in one transaction, and raises it on the error list. Without a token the client stops before
 * any request, so nothing more reaches the shop. An uninstall triggered before the stored token's
 * install (an uninstall and a reinstall promoted out of order) changes nothing.
 *
 * @param array<string, mixed> $job claimed shopify_jobs row
 * @return array{outcome: string}
 */
function shopify_shop_uninstalled_job(array $job, float $deadline, callable $heartbeat): array {
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1', array((int)$job['shop_id']))[0] ?? null;
	if ($shop === null) {
		return array('outcome' => 'done');
	}
	$key = (string)$shop['shop_key'];
	$eventId = (int)($job['payload']['event_row_id'] ?? 0);
	$triggered = shopify_db_rows('SELECT triggered_at FROM shopify_events WHERE id = $1', array($eventId))[0]['triggered_at'] ?? null;
	shopify_db_transaction(function () use ($shop, $key, $triggered, $job) {
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_oauth:$key"));
		$installed = shopify_secret($key, SHOPIFY_OAUTH_INSTALLED_AT);
		if ($installed !== null && $triggered !== null && new DateTimeImmutable($installed) > new DateTimeImmutable((string)$triggered)) {
			shopify_event_log('uninstall_superseded', array('shop' => $key, 'installed_at' => $installed, 'triggered_at' => $triggered));
			return;
		}
		foreach (array('access_token', SHOPIFY_OAUTH_INSTALLED_AT, SHOPIFY_OAUTH_STATE) as $name) {
			shopify_save_secret($key, $name, '');
		}
		$flows = array('shop_key' => $key);
		foreach (SHOPIFY_FLOWS as $flow) {
			$flows[$flow . '_enabled'] = false;
		}
		shopify_save_shop($flows);
		shopify_event_alarm((int)$shop['id'], 'shop.uninstalled', $key, 'the Saldi app was uninstalled from the shop: its token is deleted and every flow is off', array('job_id' => $job['id'] ?? null));
	});
	return array('outcome' => 'done');
}
