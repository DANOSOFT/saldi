<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/lock_release.php --- patch 5.0.0 --- 2026-08-03 ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260803 MJ AJAX-endpoint: frigiv ordrelås ved browser-luk (sendBeacon)

@session_start();
$s_id = session_id();

include("connect.php");
include("std_func.php");
include("record_lock.php");

$tabel     = isset($_POST['tabel'])     ? $_POST['tabel']     : 'ordrer';
$record_id = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;

if ($record_id > 0 && in_array($tabel, ['ordrer'])) {
    order_lock_release($tabel, $record_id, $s_id);
}

http_response_code(200);
