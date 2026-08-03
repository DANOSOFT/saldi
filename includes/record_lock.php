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

// Create the record_locks table if it doesn't exist yet.
// betweenUpdates.php also handles this, but only when the app version is ahead
// of the DB version. This guard ensures the table is always ready on first use.
function _ensure_record_locks_table() {
    static $ensured = false;
    if ($ensured) return;
    $r = db_fetch_array(db_select(
        "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='record_locks' LIMIT 1",
        __FILE__ . " linje " . __LINE__
    ));
    if (!$r) {
        db_modify("CREATE TABLE IF NOT EXISTS record_locks (
            id SERIAL PRIMARY KEY,
            tabel VARCHAR(50) NOT NULL DEFAULT 'ordrer',
            record_id INTEGER NOT NULL,
            brugernavn VARCHAR(100) NOT NULL DEFAULT '',
            session_id VARCHAR(100) NOT NULL DEFAULT '',
            locked_at BIGINT NOT NULL DEFAULT 0,
            CONSTRAINT record_locks_unique UNIQUE (tabel, record_id)
        )", __FILE__ . " linje " . __LINE__);
    }
    $ensured = true;
}

// Check for a conflicting lock and acquire the lock for the current user.
// Lock identity is brugernavn (not session_id) so the same user is never
// blocked by their own old lock after a session change or iframe reload.
// Returns null if the lock was acquired (or already held by this user).
// Returns the existing lock row array if a different user holds it.
function order_lock_check_acquire($tabel, $record_id, $brugernavn, $session_id) {
    _ensure_record_locks_table();

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
        if ($r['brugernavn'] === $brugernavn) {
            // Same user (session may differ after reload/iframe change) — refresh lock
            db_modify(
                "UPDATE record_locks SET locked_at=" . time() . ", session_id='$sess_esc'"
                . " WHERE tabel='$tabel_esc' AND record_id=$rid AND brugernavn='$brug_esc'",
                __FILE__ . " linje " . __LINE__
            );
            return null;
        }
        // Different user holds the lock
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

// Release the lock held by the given user for a specific record.
function order_lock_release($tabel, $record_id, $brugernavn) {
    _ensure_record_locks_table();

    $tabel_esc = db_escape_string($tabel);
    $brug_esc  = db_escape_string($brugernavn);
    $rid       = (int)$record_id;
    db_modify(
        "DELETE FROM record_locks WHERE tabel='$tabel_esc' AND record_id=$rid AND brugernavn='$brug_esc'",
        __FILE__ . " linje " . __LINE__
    );
}
