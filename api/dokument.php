<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/dokument.php --- ver 5.1.0 --- 2026.10.30
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
// Public document link (WP11-2, K45): api/dokument.php?t=<token> gives the invoice or credit
// note PDF behind a link made by dokument_link(). No session; the tenant comes from
// SALDI_SHOPIFY_DB or includes/shopify/.ht_shopify_db, as for the webhook receiver.
//
// 20261030 CL/SZ SD-752: created (WP11-2).

ob_start();
require_once(__DIR__ . '/../includes/documents/documentHttp.php');

/** Ends the request with 503 and nothing that was printed before it. */
function dokument_unavailable(): void
{
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	if (!headers_sent()) {
		header_remove();
	}
	dokument_respond(503);
	exit;
}

// a legacy die() or exit, from connect.php or inside the print, never reaches the client as a 200 page
$dokumentDone = false;
register_shutdown_function(function () use (&$dokumentDone): void {
	if (!$dokumentDone && !headers_sent()) {
		dokument_unavailable();
	}
});

// the tenant is the install's, never one a request names
if (array_key_exists('db', $_GET) || array_key_exists('db', $_POST) || array_key_exists('db', $_COOKIE)) {
	dokument_unavailable();
}

include(__DIR__ . '/../includes/connect.php');
require_once(__DIR__ . '/../includes/shopify/bootstrap.php');

try {
	shopify_bootstrap();
} catch (ShopifyBootstrapError | ShopifyDbError $e) {
	error_log('dokument unavailable ' . json_encode(array('error' => $e->getMessage())));
	dokument_unavailable();
}
while (ob_get_level() > 0) {
	ob_end_clean();
}
ob_start();
handle_dokument_request($_GET, $_SERVER);
$dokumentDone = true;
ob_end_flush();
