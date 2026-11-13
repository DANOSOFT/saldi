<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- payments/save_receipt.php --- ver 5.1.1 --- 2026.11.13 ---
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
// Copyright (c) 2024-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20240227 PHR Added include print_receipt
// 20260720 NTR Recreate temp/$db if missing (cleared daily) before saving receipt
// 20260916 SS Declared $permission_key (roles & permissions, phase 3)
// 20261103 CL/ASR QA: $permission_key is 'pos.kasse|debitor.ordre', so a till user (pos.kasse) can pay by terminal once enforcement is 'deny'.
//
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
@session_start();
$s_id = session_id();

global $db;

include ("../../includes/connect.php");
$permission_key = 'pos.kasse|debitor.ordre'; // the till (pos.kasse) and the order page both pay by terminal
include ("../../includes/online.php");
include ("../../includes/std_func.php");
include ("../../includes/stdFunc/dkDecimal.php");
include ("../../includes/stdFunc/usDecimal.php");

$json = json_decode(file_get_contents('php://input'), true);
$data = $json["data"];
$id = $json["id"];
$type = $json["type"];
$kasse = isset($json["kasse"]) ? $json["kasse"] : null;
$terminal_id = isset($json["terminal_id"]) ? $json["terminal_id"] : null;

echo "<pre>";
print_r($data);
echo "</pre>";

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

file_put_contents($filename, json_encode($data));

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
if ($print_receipt) include_once("print_receipt.php");
