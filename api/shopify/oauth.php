<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/shopify/oauth.php --- ver 5.1.0 --- 2026.11.03
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
// Shopify app install endpoint (SD-790): the app's App URL and its redirect URL. Shopify opens
// GET api/shopify/oauth.php?shop=<domain>&hmac=...; shopify_oauth_request() does the rest. The
// tenant comes from SALDI_SHOPIFY_DB or includes/shopify/.ht_shopify_db, as for webhook.php, never
// from the request: a db parameter is refused.
//
// 20261103 CL/MJ SD-790: created.

// Legacy includes print HTML on their error paths (alert(), die()); the answer stays ours.
ob_start();
http_response_code(503);

function shopify_oauth_respond(int $status, string $outcome, ?string $location = null): void {
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	http_response_code($status);
	header('Cache-Control: no-store');
	if ($location !== null) {
		header('Location: ' . $location);
	}
	header('Content-Type: text/plain; charset=utf-8');
	echo "Saldi: $outcome\n";
	exit;
}

register_shutdown_function(function (): void {
	if (ob_get_level() > 0 && !headers_sent()) {
		shopify_oauth_respond(503, 'unavailable');
	}
});

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
	shopify_oauth_respond(405, 'method_not_allowed');
}
if (array_key_exists('db', $_GET) || array_key_exists('db', $_POST) || array_key_exists('db', $_COOKIE)) {
	shopify_oauth_respond(400, 'bad_request');
}

include(__DIR__ . '/../../includes/connect.php');
require_once(__DIR__ . '/../../includes/shopify/bootstrap.php');
require_once(__DIR__ . '/../../includes/shopify/oauth.php');

try {
	shopify_bootstrap(null, false);
	$shopifyOauthResult = shopify_oauth_request($_GET);
} catch (ShopifyBootstrapError | ShopifyDbError $e) {
	shopify_event_log('oauth_unavailable', array('error' => $e->getMessage()));
	shopify_oauth_respond(503, 'unavailable');
} catch (Throwable $e) {
	shopify_event_log('oauth_failed', array('error' => get_class($e) . ': ' . $e->getMessage()));
	shopify_oauth_respond(500, 'failed');
}
shopify_oauth_respond($shopifyOauthResult['status'], $shopifyOauthResult['outcome'], $shopifyOauthResult['location'] ?? null);
