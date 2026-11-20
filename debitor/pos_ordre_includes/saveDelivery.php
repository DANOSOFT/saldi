<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/saveDelivery.php --- ver 5.1.0 ---- 2026-11-20 ----
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

ob_start();

@session_start();
$s_id = session_id();
$title = "saveDelivery";
$modulnr = 0;
$bg = "nix";
$header = "nix";
$webservice = true;

include(__DIR__ . "/../../includes/connect.php");
include(__DIR__ . "/../../includes/online.php");
include(__DIR__ . "/../../includes/std_func.php");
include_once(__DIR__ . "/saleDeliveryFields.php");

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');

/**
 * @var string $db_encode From includes/connect.php.
 */

$answer = function ($error) use ($db_encode) {
	if ($error !== null && !mb_check_encoding($error, 'UTF-8')) {
		$error = mb_convert_encoding($error, 'UTF-8', 'ISO-8859-15');
	}
	echo json_encode($error === null ? array('ok' => true) : array('ok' => false, 'error' => $error));
	exit;
};

$orderId = isset($_POST['id']) && is_scalar($_POST['id']) ? (int) $_POST['id'] : 0;
$valg = isset($_POST['leveringsvalg_id']) && is_scalar($_POST['leveringsvalg_id']) ? (string) $_POST['leveringsvalg_id'] : null;
$dato = isset($_POST['oensket_levdato']) && is_scalar($_POST['oensket_levdato']) ? (string) $_POST['oensket_levdato'] : '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $orderId < 1 || $valg === null || !pos_delivery_supported()) {
	$answer(null);
}
$answer(pos_save_sale_delivery($orderId, $valg, $dato));
