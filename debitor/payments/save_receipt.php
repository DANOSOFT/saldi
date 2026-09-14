<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- payments/save_receipt.php --- lap 4.1.0 --- 2024.03.01 ---
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
// Copyright (c) 204-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20240227 PHR Added include print_receipt
// 20260720 NTR Recreate temp/$db if missing (cleared daily) before saving receipt
// 20260914 CDX/LH SST-788 Acknowledge successful receipt writes for reconciliation callers.
//

/**
 * Injected by ../../includes/online.php, included below:
 * @var string $db
 */

@session_start();
$s_id = session_id();

$json = json_decode(file_get_contents('php://input'), true);
$confirmSaved = ($json['confirm_saved'] ?? false) === true;
if ($confirmSaved) {
	$header = 'nix';
	$modulnr = 5;
}

include (__DIR__ . "/../../includes/connect.php");
include (__DIR__ . "/../../includes/online.php");
include (__DIR__ . "/../../includes/std_func.php");
include (__DIR__ . "/../../includes/stdFunc/dkDecimal.php");
include (__DIR__ . "/../../includes/stdFunc/usDecimal.php");

if ($confirmSaved) {
	header('Content-Type: application/json; charset=UTF-8');
}
$data = $json["data"];
$id = $json["id"];
$type = $json["type"];
$kasse = isset($json["kasse"]) ? $json["kasse"] : null;
$terminal_id = isset($json["terminal_id"]) ? $json["terminal_id"] : null;

if (!$confirmSaved) {
	echo "<pre>";
	print_r($data);
	echo "</pre>";
}

$directory = "../../temp/$db";

// temp/$db is cleared daily, so recreate it if it is missing before writing
if (!is_dir($directory)) mkdir($directory, 0777, true);

// Set the initial filename
$filename = "$directory/receipt_$id.txt";

// Check if the file already exists
$counter = 1;
while (file_exists($filename)) {
    // If the file exists, increment the counter and try again
    $filename = "$directory/receipt_$id-$counter.txt";
    $counter++;
}

$receiptJson = json_encode($data);
$receiptBytesWritten = file_put_contents($filename, $receiptJson);
if ($confirmSaved && ($receiptBytesWritten === false || $receiptBytesWritten !== strlen($receiptJson))) {
	http_response_code(500);
	echo json_encode(['saved' => false]);
	exit;
}

// For flatpay transactions, also save a copy with terminal_id filename
if (($type == 'flatpay' || $type == 'move3500') && $terminal_id) {
    $terminal_filename = "$directory/terminal_$id.txt";
    
    // Check if the terminal file already exists
    $terminal_counter = 1;
    while (file_exists($terminal_filename)) {
        // If the file exists, increment the counter and try again
        $terminal_filename = "$directory/terminal_$terminal_id-$terminal_counter.txt";
        $terminal_counter++;
    }
    
    file_put_contents($terminal_filename, json_encode($data));
}
$print_receipt = 1;
$printserver = $printserver ?? null;
if ($print_receipt) include_once(__DIR__ . "/print_receipt.php");

if ($confirmSaved) {
	$saved = isset($printBytesWritten) && $printBytesWritten !== false
		&& $printBytesWritten > 0 && $printBytesWritten === strlen($bon);
	if (!$saved) {
		http_response_code(500);
	}
	echo json_encode(['saved' => $saved]);
}
