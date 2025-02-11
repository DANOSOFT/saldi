<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/main.php --- lap 4.1.0 --- 2024.02.09 ---
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
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 17042024 MMK - Added suport for reloading page, and keeping current URI, DELETED old system that didnt work
// 17-10-2024 PBLM - Added link to booking

@session_start();
$s_id = session_id();

include "std_func.php";
include "connect.php";
include "online.php";
include "stdFunc/dkDecimal.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch the raw JSON input
    $input = file_get_contents('php://input');
    
    // Decode the JSON input to an associative array
    $post_data = json_decode($input, true);

    // Check if decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        die('Invalid JSON input');
    }

    // Access the data
    $function = if_isset($post_data['function'], '');

    if ($function === 'closed-card') {
        // Escape the data to prevent SQL injection
        $id = if_isset($post_data['id'], '');
        $selector = if_isset($post_data['selector'], '');

        $id = db_escape_string($id);
        $selector = db_escape_string($selector);

        // Execute the database query
        db_modify("INSERT INTO tutorials (user_id, tutorial_id, selector) VALUES ($bruger_id, '$id', '$selector')", __FILE__ . " line " . __LINE__);
    }
    if ($function === 'closed-card-all') {
        $id = if_isset($post_data['id'], '');
        $id = db_escape_string($id);

        $steps = if_isset($post_data["steps"], array());
        foreach ($steps as $step) {
            // Escape the data to prevent SQL injection
            $selector = db_escape_string($step["selector"]);

            // Execute the database query
            db_modify("INSERT INTO tutorials (user_id, tutorial_id, selector) VALUES ($bruger_id, '$id', '$selector')", __FILE__ . " line " . __LINE__);
        }
    }
    if ($function === 'restart') {
        $id = if_isset($post_data['id'], '');
        $id = db_escape_string($id);

        db_modify("DELETE FROM tutorials WHERE user_id=$bruger_id AND tutorial_id='$id'", __FILE__ . " line " . __LINE__);
    }
}
