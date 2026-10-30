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
// 20261019 CL/LH MB-73: default 0 for ordrelinjer.leveret.
//                One-time backfill of NULL leveret on sales, credit note and POS lines from their batch_salg delivery records.
// 20261027 CL/LH MB-73 (review): the leveret backfill runs in id windows under a try-lock with a time budget per
//                login (includes/opdat_func/backfillOrderLineDelivered.php) instead of one UPDATE at the first login.
//                The SET DEFAULT check also matches MariaDB, which reports a missing default as the text 'NULL'.

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

// 20261019 CL/LH MB-73: ordrelinjer.leveret had no default and opret_ordrelinje() never set it, so
// linjeopdat()'s leveret+n stayed NULL. debitor/ordre.php re-syncs leveret from the delivery records
// whenever the order is shown (the sum of batch_salg.antal for the line, order and item), but fast
// invoicing never shows it, so invoiced and credited lines kept leveret NULL.
// The default is set only while it is still missing, checked in the current tenant's database/schema
// like the column checks now in includes/opdat_5.0.php. SET DEFAULT is a catalog change only; existing
// rows are not rewritten.
$leveretMysql = in_array($db_type, ['mysql', 'mysqli'], true);
// 20261027 CL/LH MB-73 (review): MariaDB reports a missing default as the text 'NULL', not as NULL.
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'ordrelinjer' AND column_name = 'leveret'";
$qtxt .= " AND (column_default IS NULL OR column_default = 'NULL')";
$qtxt .= $leveretMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE ordrelinjer ALTER COLUMN leveret SET DEFAULT 0", __FILE__ . " linje " . __LINE__);
}
// One-time backfill, flagged in settings like the pool_files backfills in opdat_5.0.php: leveret becomes the same
// batch_salg sum ordre.php shows, for sales, credit note and POS lines that have delivery records. Lines
// without any (not delivered, text lines) are left as they are, as ordre.php leaves them. Purchase lines are
// excluded: their receipts are in batch_kob and kreditor/ordre.php keeps their leveret.
// 20261027 CL/LH MB-73 (review): as one UPDATE this took 4.5 minutes at the first login on a large POS tenant.
// backfillOrderLineDelivered() runs it in windows of ordrelinjer ids under a try-lock and stops after about a
// second; later logins carry on from the stored window, and a login that finds the lock taken skips it.
require_once __DIR__ . '/opdat_func/backfillOrderLineDelivered.php';
backfillOrderLineDelivered($db_type);

?>
