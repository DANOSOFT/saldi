<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/semanticWebhook.php --- ver 5.1.0 --- 2026-11-09 ---
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
// 20261109 CL/MMR SD-815 Created (draft). Webhook for Semantic's notifications about documents Saldi sent (no login session).
//                 The tenant database comes from the URL's db parameter, authenticated with an HMAC token (semantic_webhook_url()); Semantic's own signature is checked once its scheme is known.
//                 The notification is matched to the send log by Semantic's document id, never by invoice number; the status is then read from Semantic's API and applied.
//                 A copy of a regnskab (semantic_copy_guard()) ignores every notification.

/**
 * Injected by ../includes/connect.php, included below:
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 * @var string $sqdb
 * @var string $db_type
 */

ob_start(); // db_select()/db_modify() can print on errors; the JSON answer stays ours
$webservice = true; // db_select()/db_modify() return instead of showing an alert and exiting

include_once(__DIR__ . '/../includes/einvoice/semanticClient.php');
include_once(__DIR__ . '/../includes/einvoice/semanticSendLog.php');
include_once(__DIR__ . '/../includes/einvoice/semanticStatus.php');

/**
 * Send the JSON answer. With $continue the client gets the answer and the connection is closed, and the script goes
 * on (the status lookup), so Semantic is answered quickly.
 *
 * @param int                  $status
 * @param array<string, mixed> $body
 * @param bool                 $continue
 * @return void
 */
function semanticWebhookRespond($status, array $body, $continue = false) {
	$json = (string)json_encode($body);
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	http_response_code((int)$status);
	header('Content-Type: application/json; charset=utf-8');
	if (!$continue) {
		print $json;
		exit;
	}
	ignore_user_abort(true);
	header('Connection: close');
	header('Content-Length: ' . strlen($json));
	print $json;
	if (function_exists('fastcgi_finish_request')) {
		fastcgi_finish_request();
	} else {
		flush();
	}
	ob_start(); // anything printed from here on is discarded
}

$rawBody = (string)file_get_contents('php://input', false, null, 0, 1048577);
$preflight = semantic_webhook_preflight(
	$_SERVER['REQUEST_METHOD'] ?? '',
	$rawBody,
	$_GET,
	semantic_webhook_secret(),
	semantic_request_headers($_SERVER),
	semantic_signing_secrets(),
	semantic_webhook_signature_verifier()
);
if ($preflight['httpStatus'] !== 200) {
	if ($preflight['httpStatus'] === 503) {
		error_log('semanticWebhook: ' . $preflight['reason']);
	}
	semanticWebhookRespond($preflight['httpStatus'], array('status' => $preflight['reason']));
}
$webhookDb = (string)$preflight['db'];
$payload = $preflight['payload'];

include(__DIR__ . '/../includes/connect.php');
// The token proves the URL was made for this database name; it must also still be a regnskab on this server.
if ($webhookDb === $sqdb) {
	semanticWebhookRespond(404, array('status' => 'unknown_db'));
}
$regnskab = db_fetch_array(db_select("SELECT id FROM regnskab WHERE db = '" . db_escape_string($webhookDb) . "'", __FILE__ . " linje " . __LINE__));
if (!$regnskab) {
	semanticWebhookRespond(404, array('status' => 'unknown_db'));
}
$connection = db_connect($sqhost, $squser, $sqpass, $webhookDb, __FILE__ . " linje " . __LINE__);
if (!$connection || !db_select_database($connection, $webhookDb)) {
	semanticWebhookRespond(503, array('status' => 'database_unavailable'));
}
$db = $webhookDb;

$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_name = 'einvoice_sendlog'";
$qtxt .= einvoice_sendlog_is_mysql($db_type) ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
$table = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if (!$table) {
	semanticWebhookRespond(200, array('status' => 'ignored'));
}
// A copy of a regnskab (its Semantic ids were stored under another database name) never acts on the customer's documents.
include_once(__DIR__ . '/../includes/einvoice/semanticOnboarding.php');
$copyGuard = semantic_copy_guard(semantic_settings_read(), $webhookDb);
if (!$copyGuard['allowed']) {
	error_log('semanticWebhook: ' . $copyGuard['reason'] . " in $webhookDb");
	semanticWebhookRespond(200, array('status' => 'ignored'));
}
if ($payload['kind'] === 'incoming') {
	// SD-815 OPEN: incoming documents and ApplicationResponses (tasks 19/20) are not handled yet.
	semanticWebhookRespond(202, array('status' => 'ignored_incoming'));
}
$logRow = ($payload['documentId'] !== null)
	? einvoice_sendlog_find_by_semantic_id($payload['documentId'])
	: einvoice_sendlog_find_by_ubl_id((string)$payload['ublDocumentId']);
if ($logRow === null || $logRow['semantic_doc_id'] === '') {
	semanticWebhookRespond(200, array('status' => 'ignored'));
}
if ($payload['documentNumber'] !== null && $logRow['fakturanr'] !== '' && $payload['documentNumber'] !== $logRow['fakturanr']) {
	// The id matched but the number does not: leave the row alone and let the poll ask Semantic.
	error_log('semanticWebhook: document number mismatch for send log ' . $logRow['id'] . " in $webhookDb");
	semanticWebhookRespond(200, array('status' => 'ignored'));
}

semanticWebhookRespond(200, array('status' => 'accepted'), true);

// The answer is sent; read the status from Semantic's API (the payload's own status only when that fails).
$client = semantic_client(null, array('connectTimeout' => 5, 'timeout' => 15));
if ($client instanceof SemanticClient) {
	semantic_status_refresh($client, $logRow, $payload['statusCode'], $payload['text']);
} elseif ($payload['statusCode'] !== null) {
	semantic_status_apply($logRow, (string)$payload['statusCode'], $payload['text']);
}
exit;
