<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/record_lock.php --- patch 5.0.0 --- 2026-08-12 ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260803 MJ Ordrelås — forhindrer at to brugere redigerer samme bilag samtidigt
// 20260810 MJ Atomisk erhvervelse via ON CONFLICT; session-bundet frigivelse; heartbeat-funktion
// 20260812 MJ Ret table_schema-tjek til at virke med baade MySQL og PostgreSQL
// 20260830 CDX/MJ Synchronize order locks with the legacy ordrer.hvem field

// Locks expire after 10 minutes of inactivity (heartbeat fires every 5 min, so max 10 min after crash)
define('RECORD_LOCK_TTL', 600);

// Create the record_locks table if it doesn't exist yet.
// betweenUpdates.php also handles this, but only when the app version is ahead
// of the DB version. This guard ensures the table is always ready on first use.
function _ensure_record_locks_table() {
    static $ensured = false;
    if ($ensured) return;
    global $db_type;
    $_rl_schema_cond = (strtolower($db_type ?? '') === 'mysql' || strtolower($db_type ?? '') === 'mysqli')
        ? "table_schema = DATABASE()"
        : "table_schema = 'public'";
    $r = db_fetch_array(db_select(
        "SELECT 1 FROM information_schema.tables WHERE $_rl_schema_cond AND table_name='record_locks' LIMIT 1",
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
// Uses INSERT ... ON CONFLICT DO NOTHING so two concurrent requests cannot
// both succeed — the loser's INSERT is silently dropped and the re-read
// reveals the winner. Returns null when the lock is ours; returns the
// conflicting row when a different user holds it.
function order_lock_check_acquire($tabel, $record_id, $brugernavn, $session_id) {
    _ensure_record_locks_table();

    $expire    = time() - RECORD_LOCK_TTL;
    $now       = time();
    $tabel_esc = db_escape_string($tabel);
    $brug_esc  = db_escape_string($brugernavn);
    $sess_esc  = db_escape_string($session_id ?? '');
    $rid       = (int)$record_id;

    // Remove stale locks for this record
    db_modify(
        "DELETE FROM record_locks WHERE tabel='$tabel_esc' AND record_id=$rid AND locked_at < $expire",
        __FILE__ . " linje " . __LINE__
    );

    // Atomically attempt to acquire — if another row exists the INSERT is silently skipped.
    // MySQL uses INSERT IGNORE; PostgreSQL uses ON CONFLICT DO NOTHING.
    global $db_type;
    if (strtolower($db_type ?? '') === 'mysql' || strtolower($db_type ?? '') === 'mysqli') {
        db_modify(
            "INSERT IGNORE INTO record_locks (tabel, record_id, brugernavn, session_id, locked_at)"
            . " VALUES ('$tabel_esc', $rid, '$brug_esc', '$sess_esc', $now)",
            __FILE__ . " linje " . __LINE__
        );
    } else {
        db_modify(
            "INSERT INTO record_locks (tabel, record_id, brugernavn, session_id, locked_at)"
            . " VALUES ('$tabel_esc', $rid, '$brug_esc', '$sess_esc', $now)"
            . " ON CONFLICT (tabel, record_id) DO NOTHING",
            __FILE__ . " linje " . __LINE__
        );
    }

    // Re-read to determine who owns the lock after the atomic insert
    $r = db_fetch_array(db_select(
        "SELECT * FROM record_locks WHERE tabel='$tabel_esc' AND record_id=$rid",
        __FILE__ . " linje " . __LINE__
    ));

    if (!$r || ($r['brugernavn'] === $brugernavn && $r['session_id'] === $session_id)) {
        // We own the lock — refresh timestamp to keep it alive
        db_modify(
            "UPDATE record_locks SET locked_at=$now"
            . " WHERE tabel='$tabel_esc' AND record_id=$rid AND brugernavn='$brug_esc' AND session_id='$sess_esc'",
            __FILE__ . " linje " . __LINE__
        );
        if ($tabel === 'ordrer') {
            db_modify(
                "UPDATE ordrer SET hvem='$brug_esc', tidspkt=$now WHERE id=$rid",
                __FILE__ . " linje " . __LINE__
            );
        }
        return null;
    }

    // Different user OR same user in a different session holds the lock
    return $r;
}

// Refresh the lock timestamp while the user is still on the page (heartbeat).
// Only updates if this session still owns the lock — a new tab that re-acquired
// will have its own session_id and won't be overwritten.
function order_lock_refresh($tabel, $record_id, $brugernavn, $session_id) {
    _ensure_record_locks_table();

    $now       = time();
    $tabel_esc = db_escape_string($tabel);
    $brug_esc  = db_escape_string($brugernavn);
    $sess_esc  = db_escape_string($session_id ?? '');
    $rid       = (int)$record_id;

    db_modify(
        "UPDATE record_locks SET locked_at=$now"
        . " WHERE tabel='$tabel_esc' AND record_id=$rid"
        . " AND brugernavn='$brug_esc' AND session_id='$sess_esc'",
        __FILE__ . " linje " . __LINE__
    );

    $r = db_fetch_array(db_select(
        "SELECT * FROM record_locks WHERE tabel='$tabel_esc' AND record_id=$rid",
        __FILE__ . " linje " . __LINE__
    ));
    if ($tabel === 'ordrer' && $r && $r['brugernavn'] === $brugernavn && $r['session_id'] === $session_id) {
        db_modify(
            "UPDATE ordrer SET hvem='$brug_esc', tidspkt=$now WHERE id=$rid",
            __FILE__ . " linje " . __LINE__
        );
    }
}

// Release the lock held by this user+session for a specific record.
// Including session_id in the predicate ensures a different tab of the same
// user cannot accidentally release a lock it no longer owns.
function order_lock_release($tabel, $record_id, $brugernavn, $session_id = null) {
    _ensure_record_locks_table();

    $tabel_esc = db_escape_string($tabel);
    $brug_esc  = db_escape_string($brugernavn);
    $rid       = (int)$record_id;

    $where = "tabel='$tabel_esc' AND record_id=$rid AND brugernavn='$brug_esc'";
    if ($session_id !== null) {
        $sess_esc = db_escape_string($session_id);
        $where   .= " AND session_id='$sess_esc'";
    }

    $r = db_fetch_array(db_select(
        "SELECT * FROM record_locks WHERE tabel='$tabel_esc' AND record_id=$rid",
        __FILE__ . " linje " . __LINE__
    ));
    $owns_lock = $r && $r['brugernavn'] === $brugernavn
        && ($session_id === null || $r['session_id'] === $session_id);

    db_modify(
        "DELETE FROM record_locks WHERE $where",
        __FILE__ . " linje " . __LINE__
    );
    if ($tabel === 'ordrer' && $owns_lock) {
        db_modify(
            "UPDATE ordrer SET hvem='' WHERE id=$rid AND hvem='$brug_esc'",
            __FILE__ . " linje " . __LINE__
        );
    }
}
