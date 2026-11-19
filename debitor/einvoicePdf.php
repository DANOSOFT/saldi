<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/einvoicePdf.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261112 CL/MMR SD-815 Created. Shows the PDF of an e-invoice or credit note sent through Semantic: einvoicePdf.php?id=<ordrer.id>&log=<einvoice_sendlog.id>.
//                 Needs a logged-in user with debitor rights (online.php, module 5); the guards are in includes/einvoice/semanticPdf.php.
//                 The PDF goes straight to the browser and is never stored on the server.

/**
 * Injected by ../includes/connect.php, included below:
 * @var string $sqdb
 *
 * Injected by ../includes/online.php, included below:
 * @var string $db
 * @var string $brugernavn
 * @var int|string $sprog_id
 */

ob_start(); // online.php may print; only the PDF or the message below is sent

@session_start();
$s_id = session_id();
$title = "einvoicePdf";
$modulnr = 5;
$bg = "nix";
$header = "nix";

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include_once(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/einvoice/semanticSendLog.php");
include_once(__DIR__ . "/../includes/einvoice/semanticPdf.php");

$enabled = !empty($brugernavn) && !empty($db) && $db != $sqdb && einvoice_ui_semantic_enabled();
$response = semantic_pdf_response(semantic_pdf_request($_GET ?? array()), array(
	'enabled' => $enabled,
	'db' => (string)($db ?? ''),
	'sprogId' => (int)($sprog_id ?? 1),
	'loadLog' => function ($logId) {
		return einvoice_sendlog_get((int)$logId);
	},
	'orderExists' => function ($orderId) {
		$qtxt = "SELECT id FROM ordrer WHERE id = " . (int)$orderId . " AND (art = 'DO' OR art = 'DK')";
		return (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	},
	'storeUblId' => function ($logId, $ublId) {
		einvoice_sendlog_update((int)$logId, array('ubl_doc_id' => (string)$ublId));
	},
	'client' => $enabled ? semantic_client() : null,
));

while (ob_get_level() > 0) {
	ob_end_clean();
}
http_response_code($response['status']);
foreach ($response['headers'] as $line) {
	header($line);
}
print $response['body'];
exit;
