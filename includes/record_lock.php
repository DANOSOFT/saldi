<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/record_lock.php --- patch 5.0.0 --- 2026-08-03 ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260803 MJ Ordrelås — forhindrer at to brugere redigerer samme bilag samtidigt

// Locks expire after 30 minutes of inactivity
define('RECORD_LOCK_TTL', 1800);

// Check for a conflicting lock and acquire the lock for the current session.
// Returns null if the lock was acquired (or already held by this session).
// Returns the existing lock row array if another session holds it.
function order_lock_check_acquire($tabel, $record_id, $brugernavn, $session_id) {
    $expire    = time() - RECORD_LOCK_TTL;
    $tabel_esc = db_escape_string($tabel);
    $brug_esc  = db_escape_string($brugernavn);
    $sess_esc  = db_escape_string($session_id);
    $rid       = (int)$record_id;

    // Remove stale locks for this record
    db_modify(
        "DELETE FROM record_locks WHERE tabel='$tabel_esc' AND record_id=$rid AND locked_at < $expire",
        __FILE__ . " linje " . __LINE__
    );

    $r = db_fetch_array(db_select(
        "SELECT * FROM record_locks WHERE tabel='$tabel_esc' AND record_id=$rid",
        __FILE__ . " linje " . __LINE__
    ));

    if ($r) {
        if ($r['session_id'] === $session_id) {
            // Same session — refresh timestamp
            db_modify(
                "UPDATE record_locks SET locked_at=" . time() . ", brugernavn='$brug_esc'"
                . " WHERE tabel='$tabel_esc' AND record_id=$rid AND session_id='$sess_esc'",
                __FILE__ . " linje " . __LINE__
            );
            return null;
        }
        // Different session holds the lock
        return $r;
    }

    // Unclaimed — acquire it
    db_modify(
        "INSERT INTO record_locks (tabel, record_id, brugernavn, session_id, locked_at)"
        . " VALUES ('$tabel_esc', $rid, '$brug_esc', '$sess_esc', " . time() . ")",
        __FILE__ . " linje " . __LINE__
    );
    return null;
}

// Release the lock held by the current session for a specific record.
function order_lock_release($tabel, $record_id, $session_id) {
    $tabel_esc = db_escape_string($tabel);
    $sess_esc  = db_escape_string($session_id);
    $rid       = (int)$record_id;
    db_modify(
        "DELETE FROM record_locks WHERE tabel='$tabel_esc' AND record_id=$rid AND session_id='$sess_esc'",
        __FILE__ . " linje " . __LINE__
    );
}
