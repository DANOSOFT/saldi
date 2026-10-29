<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- ver 5.0.0 --- 2026.10.29
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
// 20261028 CL/LH MB-72: One-time backfill of openpost.udlign_date for settled groups that have none.
// 20261028 CDX/NTR Apply the tekst_key schema migration to both tenant and master databases.
// 20261027 CL/LH #877 (CodeRabbit): delete the removed Aiia integration's OAuth Client ID and Client Secret rows from the master database's settings table.

/**
 * Injected by includes/connect.php via the entry page that includes this file:
 * @var string $db_type
 */

// ===== PROTECTED: when a version is cut (see doc/ai/convention_database_changes.md), COPY this =====
// ===== segment into the opdat_<major>.<minor>.php file - do not move it. The moved statements =====
// ===== call these helpers, and this file keeps needing them for the next release. =====
include_once(__DIR__ . '/opdat_func/deleteStaleTekst.php');
// ===== END PROTECTED =====

// 20261019 CL/LH MB-72: automatic settlement (equalizeMatchingRecords() and the journal's openpost())
// closed open posts without a settlement date. Each settled group still missing one gets the latest
// transaction date in the group, which is what both now write. A group is an account plus its
// udlign_id, since autoudlign reuses small ids across accounts. Rows settled without an udlign_id
// cannot be grouped and are left alone, as are groups where no row has a transaction date.
// One-time, gated by a settings flag; the update only touches udlignet = '1' rows whose udlign_date
// is NULL, so a second run (or a concurrent login) changes nothing.
$udlignDateBackfilled = db_fetch_array(db_select(
	"SELECT var_value FROM settings WHERE var_name = 'openpost_udlign_date_backfilled' AND var_grp = 'system'",
	__FILE__ . " linje " . __LINE__
));
if (!$udlignDateBackfilled) {
	$udlignGroups = "SELECT konto_id, udlign_id, MAX(transdate) AS max_date FROM openpost";
	$udlignGroups.= " WHERE udlignet = '1' AND udlign_id > 0 GROUP BY konto_id, udlign_id";
	$udlignGroups.= " HAVING MAX(transdate) IS NOT NULL AND COUNT(*) > COUNT(udlign_date)";
	if (in_array($db_type, ['mysql', 'mysqli'], true)) {
		// MySQL has no UPDATE ... FROM; the grouped derived table is materialized, so it may read openpost.
		$qtxt = "UPDATE openpost JOIN ($udlignGroups) udlign_group";
		$qtxt.= " ON openpost.konto_id = udlign_group.konto_id AND openpost.udlign_id = udlign_group.udlign_id";
		$qtxt.= " SET openpost.udlign_date = udlign_group.max_date";
		$qtxt.= " WHERE openpost.udlignet = '1' AND openpost.udlign_date IS NULL";
	} else {
		$qtxt = "UPDATE openpost SET udlign_date = udlign_group.max_date FROM ($udlignGroups) udlign_group";
		$qtxt.= " WHERE openpost.konto_id = udlign_group.konto_id AND openpost.udlign_id = udlign_group.udlign_id";
		$qtxt.= " AND openpost.udlignet = '1' AND openpost.udlign_date IS NULL";
	}
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	db_modify(
		"INSERT INTO settings (var_name, var_grp, var_value, var_description)
		VALUES ('openpost_udlign_date_backfilled', 'system', 'yes', 'One-time backfill of openpost.udlign_date for settled groups that had none')",
		__FILE__ . " linje " . __LINE__
	);
}

// 20261022 CL/NTR tekster gets a descriptive string key (tekst_key) alongside the legacy numeric
// tekst_id, so findtekst() can be called with keys like 'common.accept'. Nullable, added as a
// separate column rather than retyping tekst_id. See doc/ai/convention_findtekst_keys.md.
$tekstKeyMysql = in_array($db_type, ['mysql', 'mysqli'], true);
$tekstKeyTargets = [false];
if (isset($GLOBALS['db'], $GLOBALS['sqdb']) && $GLOBALS['db'] !== $GLOBALS['sqdb']) {
	// db_select()/db_modify() route global=true to the shared master database.
	$tekstKeyTargets[] = true;
}
foreach ($tekstKeyTargets as $tekstKeyGlobal) {
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='tekster' AND column_name='tekst_key'";
	$qtxt .= $tekstKeyMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, $tekstKeyGlobal))) {
		if ($tekstKeyMysql) {
			// MySQL has no ADD COLUMN IF NOT EXISTS. Serialize this migration per database.
			$tekstKeyLock = "CONCAT('saldi:tekst_key:', MD5(DATABASE()))";
			$lockResult = db_fetch_array(db_select("SELECT GET_LOCK($tekstKeyLock, 30) AS acquired", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal));
			if ((int) ($lockResult['acquired'] ?? 0) !== 1) {
				throw new RuntimeException('Could not acquire the tekst_key migration lock.');
			}
			try {
				// Another login may have added the column while this connection waited.
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, $tekstKeyGlobal))) {
					db_modify("ALTER TABLE tekster ADD COLUMN tekst_key VARCHAR(190)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
				}
			} finally {
				db_select("SELECT RELEASE_LOCK($tekstKeyLock)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
			}
		} else {
			db_modify("ALTER TABLE tekster ADD COLUMN IF NOT EXISTS tekst_key VARCHAR(190)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
		}
	}

	// Composite index for findtekst() key lookups (WHERE tekst_key = ... AND sprog_id = ...).
	if ($tekstKeyMysql) {
		$qtxt = "SELECT index_name FROM information_schema.statistics WHERE table_name='tekster' AND index_name='idx_tekster_sprog_key' AND table_schema = DATABASE()";
		if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, $tekstKeyGlobal))) {
			$tekstKeyIdxLock = "CONCAT('saldi:tekst_key_idx:', MD5(DATABASE()))";
			$idxLockResult = db_fetch_array(db_select("SELECT GET_LOCK($tekstKeyIdxLock, 30) AS acquired", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal));
			if ((int) ($idxLockResult['acquired'] ?? 0) !== 1) {
				throw new RuntimeException('Could not acquire the tekst_key index migration lock.');
			}
			try {
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, $tekstKeyGlobal))) {
					db_modify("CREATE INDEX idx_tekster_sprog_key ON tekster(sprog_id, tekst_key)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
				}
			} finally {
				db_select("SELECT RELEASE_LOCK($tekstKeyIdxLock)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
			}
		}
	} else {
		db_modify("CREATE INDEX IF NOT EXISTS idx_tekster_sprog_key ON tekster(sprog_id, tekst_key)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
	}
}

// 20261027 CL/LH #877: the Aiia bank integration is removed, so nothing reads its OAuth client
// credentials any more. They are the 'Client ID' and 'Client Secret' rows with var_grp 'OAuth' in
// the master database's settings table (the Aiia code read them with db_select(..., true)), so they
// are deleted there. Only those two rows: the per-user 'oauth_data' rows and the 'bank_integration'
// preference rows are left alone. The column check keeps a master database whose settings table has
// no var_grp from failing the login, and looking for the rows first means later logins only read.
$aiiaCredentialsMysql = in_array($db_type, ['mysql', 'mysqli'], true);
$qtxt = "SELECT 1 FROM information_schema.columns WHERE table_name = 'settings' AND column_name = 'var_grp'";
$qtxt.= $aiiaCredentialsMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true))) {
	$aiiaCredentialsWhere = "var_grp = 'OAuth' AND var_name IN ('Client ID', 'Client Secret')";
	if (db_fetch_array(db_select("SELECT id FROM settings WHERE $aiiaCredentialsWhere", __FILE__ . " linje " . __LINE__, true))) {
		db_modify("DELETE FROM settings WHERE $aiiaCredentialsWhere", __FILE__ . " linje " . __LINE__, true);
	}
}

?>
