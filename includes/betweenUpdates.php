<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- ver 5.0.0 --- 2026.10.14
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261027 NTR New Release - Moved everything into opdat_5.0.php

/**
 * Injected by includes/connect.php via the entry page that includes this file:
 * @var string $db_type
 */

// ===== PROTECTED: when a version is cut (see doc/ai/convention_database_changes.md), COPY this =====
// ===== segment into the opdat_<major>.<minor>.php file - do not move it. The moved statements =====
// ===== call these helpers, and this file keeps needing them for the next release. =====
include_once(__DIR__ . '/opdat_func/deleteStaleTekst.php');
// ===== END PROTECTED =====

// 20261022 CL/NTR tekster gets a descriptive string key (tekst_key) alongside the legacy numeric
// tekst_id, so findtekst() can be called with keys like 'common.accept'. Nullable, added as a
// separate column rather than retyping tekst_id. See doc/ai/convention_findtekst_keys.md.
$tekstKeyMysql = in_array($db_type, ['mysql', 'mysqli'], true);
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='tekster' AND column_name='tekst_key'";
$qtxt .= $tekstKeyMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	if ($tekstKeyMysql) {
		// MySQL has no ADD COLUMN IF NOT EXISTS. Serialize this migration per tenant.
		$tekstKeyLock = "CONCAT('saldi:tekst_key:', MD5(DATABASE()))";
		$lockResult = db_fetch_array(db_select("SELECT GET_LOCK($tekstKeyLock, 30) AS acquired", __FILE__ . " linje " . __LINE__));
		if ((int) ($lockResult['acquired'] ?? 0) !== 1) {
			throw new RuntimeException('Could not acquire the tekst_key migration lock.');
		}
		try {
			// Another login may have added the column while this connection waited.
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE tekster ADD COLUMN tekst_key VARCHAR(190)", __FILE__ . " linje " . __LINE__);
			}
		} finally {
			db_select("SELECT RELEASE_LOCK($tekstKeyLock)", __FILE__ . " linje " . __LINE__);
		}
	} else {
		db_modify("ALTER TABLE tekster ADD COLUMN IF NOT EXISTS tekst_key VARCHAR(190)", __FILE__ . " linje " . __LINE__);
	}
}
// Composite index for findtekst() key lookups (WHERE tekst_key = ... AND sprog_id = ...).
if ($tekstKeyMysql) {
    $qtxt = "SELECT index_name FROM information_schema.statistics WHERE table_name='tekster' AND index_name='idx_tekster_sprog_key' AND table_schema = DATABASE()";
    if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
        $tekstKeyIdxLock = "CONCAT('saldi:tekst_key_idx:', MD5(DATABASE()))";
        $idxLockResult = db_fetch_array(db_select("SELECT GET_LOCK($tekstKeyIdxLock, 30) AS acquired", __FILE__ . " linje " . __LINE__));
        if ((int) ($idxLockResult['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire the tekst_key index migration lock.');
        }
        try {
            if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                db_modify("CREATE INDEX idx_tekster_sprog_key ON tekster(sprog_id, tekst_key)", __FILE__ . " linje " . __LINE__);
            }
        } finally {
            db_select("SELECT RELEASE_LOCK($tekstKeyIdxLock)", __FILE__ . " linje " . __LINE__);
        }
    }
} else {
    db_modify("CREATE INDEX IF NOT EXISTS idx_tekster_sprog_key ON tekster(sprog_id, tekst_key)", __FILE__ . " linje " . __LINE__);
}

?>
