<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/lock_heartbeat.php --- patch 5.0.0 --- 2026-08-10 ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260810 MJ AJAX-endpoint: forny ordrelaas fra JS heartbeat (sendBeacon hvert 5. minut)

@session_start();
$s_id = session_id();

include("connect.php");
include("std_func.php");

// Look up the user's database and brugernavn from the global online table.
$r_online = db_fetch_array(db_select(
    "SELECT db, brugernavn FROM online WHERE session_id='" . db_escape_string($s_id) . "' ORDER BY logtime DESC LIMIT 1",
    __FILE__ . " linje " . __LINE__,
    true  // run against master/global DB
));

if ($r_online && $r_online['db']) {
    $db         = trim($r_online['db']);
    $brugernavn = trim($r_online['brugernavn']);

    // Switch connection to the user's own database (where record_locks lives)
    if ($db && $db != $sqdb) {
        $connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " linje " . __LINE__);
    }

    include("record_lock.php");

    $tabel     = isset($_POST['tabel'])     ? $_POST['tabel']     : 'ordrer';
    $record_id = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;

    if ($record_id > 0 && in_array($tabel, ['ordrer']) && $brugernavn) {
        order_lock_refresh($tabel, $record_id, $brugernavn, $s_id);
    }
}

http_response_code(200);
