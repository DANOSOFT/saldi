<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/shopify/webhook.php --- ver 5.0.0 --- 2026.10.28
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
// Shopify webhook endpoint (WP03-2): POST api/shopify/webhook.php?shop=<shop_key>. Stores the
// delivery in the inbox through shopify_receive_webhook() and does nothing else. The tenant
// comes from SALDI_SHOPIFY_DB or includes/shopify/.ht_shopify_db, never from the request: a
// db parameter is refused.
//
// 20261027 CL/SZ SD-748: created (WP03-2).
// 20261028 CL/SZ SD-748: the body is read up to 10 MB; a larger one gets 413 unread.

// Legacy includes print HTML on their error paths (alert(), die()); the answer stays ours.
ob_start();
// Shopify's webhook bodies are far smaller. post_max_size doesn't limit a JSON body, so the read
// stops one byte past the limit and a larger body is refused before anything else.
const SHOPIFY_WEBHOOK_MAX_BYTES = 10485760;
$shopifyWebhookRaw = (string)stream_get_contents(fopen('php://input', 'rb'), SHOPIFY_WEBHOOK_MAX_BYTES + 1);
// Until an answer is set, anything that ends the request (a die() in connect.php, an exit in
// db_select()) leaves 503, so Shopify retries instead of taking it as received.
http_response_code(503);

function shopify_webhook_respond(int $status, string $outcome): void {
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array('status' => $outcome));
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	shopify_webhook_respond(405, 'method_not_allowed');
}
if (strlen($shopifyWebhookRaw) > SHOPIFY_WEBHOOK_MAX_BYTES) {
	shopify_webhook_respond(413, 'payload_too_large');
}
if (array_key_exists('db', $_GET) || array_key_exists('db', $_POST) || array_key_exists('db', $_COOKIE)) {
	shopify_webhook_respond(400, 'bad_request');
}
$shopifyWebhookKey = $_GET['shop'] ?? '';
if (!is_string($shopifyWebhookKey) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $shopifyWebhookKey)) {
	shopify_webhook_respond(400, 'bad_request');
}

include(__DIR__ . '/../../includes/connect.php');
require_once(__DIR__ . '/../../includes/shopify/bootstrap.php');
require_once(__DIR__ . '/../../includes/shopify/events.php');

try {
	shopify_bootstrap();
	$shopifyWebhookShop = shopify_shop($shopifyWebhookKey);
} catch (ShopifyBootstrapError | ShopifyDbError $e) {
	shopify_event_log('unavailable', array('shop' => $shopifyWebhookKey, 'error' => $e->getMessage()));
	shopify_webhook_respond(503, 'unavailable');
}
if ($shopifyWebhookShop === null) {
	shopify_event_log('unknown_shop', array('shop' => $shopifyWebhookKey));
	shopify_webhook_respond(404, 'unknown_shop');
}
try {
	$shopifyWebhookResult = shopify_receive_webhook($shopifyWebhookShop, $shopifyWebhookRaw, shopify_webhook_headers($_SERVER));
} catch (Throwable $e) {
	shopify_event_log('failed', array('shop' => $shopifyWebhookKey, 'error' => get_class($e) . ': ' . $e->getMessage()));
	shopify_webhook_respond(500, 'failed');
}
shopify_webhook_respond($shopifyWebhookResult['status'], $shopifyWebhookResult['outcome']);
