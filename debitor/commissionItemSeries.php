<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/commissionItemSeries.php --- ver 5.1.1 --- 2026-11-16 ---
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
// 20261116 CL/MMR SD-831 Created. The debitor card posts here after it is drawn; the next block of kommission items is created for the highest stored customer number (includes/commissionItemSeries.php) and the answer is JSON.
//                 Only customers already saved count, nothing from the request is used. Needs a logged-in user with debitor rights (online.php, module 6, as the card).

/**
 * Injected by ../includes/connect.php, included below:
 * @var string $sqdb
 *
 * Injected by ../includes/online.php, included below:
 * @var string $db
 * @var string $brugernavn
 */

ob_start(); // online.php may print; only the JSON below is sent

@session_start();
$s_id = session_id();
$title = "commissionItemSeries";
$modulnr = 6;
$bg = "nix";
$header = "nix";
$webservice = true;

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/commissionItemSeries.php");

while (ob_get_level() > 0) {
	ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	http_response_code(405);
	print json_encode(array('ok' => false, 'error' => 'method'));
	exit;
}
if (empty($brugernavn) || empty($db) || $db == $sqdb) {
	http_response_code(401);
	print json_encode(array('ok' => false, 'error' => 'session'));
	exit;
}
print json_encode(array('ok' => true, 'created' => commissionItemSeriesEnsureForDebitors()));
