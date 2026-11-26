<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/saveDelivery.php --- ver 5.1.1 ---- 2026-11-26 ----
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261120 CL/LH K33/K34 Saves the delivery choice and wished date of an open POS sale the moment the cashier changes them (posLeveringGem() in saleDeliveryFields.php).
//                The POS product buttons leave the page by a link, which would otherwise lose a change that was not yet posted with the sale form.
//                Answers JSON: {"ok": true} or {"ok": false, "error": "<text for the cashier>"}.
// 20261124 CL/LH K33 Codex review: $webservice skips online.php's permission gate, so the endpoint checks pos.kasse write and the CSRF token itself; modulnr 5 as the POS.
// 20261126 CDX/NTR Return false for invalid or inapplicable saves and keep unexpected PHP warnings visible.

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
$s_id = session_id();
$title = "saveDelivery";
$modulnr = 5;
$bg = "nix";
$header = "nix";
$webservice = true;

include(__DIR__ . "/../../includes/connect.php");
include(__DIR__ . "/../../includes/online.php");
include(__DIR__ . "/../../includes/std_func.php");
include_once(__DIR__ . "/saleDeliveryFields.php");

ob_end_clean();

// $webservice keeps online.php from printing a page, and with it skips the permission gate, so the till right and the
// session's token are checked here; both answer JSON.
require_permission('pos.kasse', 'write');
perm_require_post_csrf(true);

header('Content-Type: application/json; charset=utf-8');
$answer = pos_sale_delivery_response($_POST);
if (isset($answer['error']) && !mb_check_encoding($answer['error'], 'UTF-8')) {
	$answer['error'] = mb_convert_encoding($answer['error'], 'UTF-8', 'ISO-8859-15');
}
echo json_encode($answer);
