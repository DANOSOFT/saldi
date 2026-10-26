<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/einvoiceCvrSave.php --- ver 5.0.0 --- 2026-10-26 ---
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
// 20261026 CL/MMR SD-774 Created. Saves the CVR typed in the order page's e-invoice popup (javascript/einvoiceCvrPopup.js) and answers with JSON.
//                 The popup posts here instead of posting the order form, so a refresh of the order page can never send the CVR again, and a rejected CVR is shown in the popup without a page load.
//                 Needs a logged-in user with debitor rights (online.php, module 5); the rules are in orderIncludes/einvoiceCvr.php.

ob_start(); // online.php may print; only the JSON below is sent

@session_start();
$s_id = session_id();
$title = "einvoiceCvrSave";
$modulnr = 5;
$bg = "nix";
$header = "nix";
$webservice = true;

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/orderIncludes/einvoiceCvr.php");

while (ob_get_level() > 0) {
	ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$problem = einvoiceCvrSaveRequestProblem($_SERVER['REQUEST_METHOD'] ?? '', !empty($brugernavn) && !empty($db) && $db != $sqdb);
if ($problem !== '') {
	http_response_code($problem === 'method' ? 405 : 401);
	print json_encode(array('ok' => false, 'problem' => '', 'error' => $problem));
	exit;
}
$result = einvoiceCvrSaveFromOrder(
	ifset($_POST, 'id', 0),
	ifset($_POST, 'cvr', ''),
	ifset($_POST, 'toCard') === '1',
	ifset($_POST, 'cardWas', '')
);
print json_encode(einvoiceCvrSaveJson($result), JSON_INVALID_UTF8_SUBSTITUTE);
