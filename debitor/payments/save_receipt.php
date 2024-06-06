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
//

@session_start();
$s_id = session_id();

global $db;

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");
include ("../../includes/stdFunc/dkDecimal.php");
include ("../../includes/stdFunc/usDecimal.php");

$json = json_decode(file_get_contents('php://input'), true);
$data = $json["data"];
$id = $json["id"];
$type = $json["type"];

echo "<pre>";
print_r($data);
echo "</pre>";

$directory = "../../temp/$db";

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
$print_receipt = 1;
if ($print_receipt) include_once("print_receipt.php");
