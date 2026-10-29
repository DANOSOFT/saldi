<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- ver 5.0.0 --- 2026.10.28
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
// 20261021 Sawaneh SD-733: Shopify schema manifest v1 (includes/shopify/schema.php) ensured at the end of the file.
// 20261026 CL/SZ SD-758: delete the cached 5439-5441 rows that still hold the POS brand texts, which moved to 7200-7202.
// 20261028 CL/SZ Sync of master into hml/shopify: the two hml/shopify steps above stay here after the 5.1.0 release emptied the file.
// 20261028 CL/LH MB-72: One-time backfill of openpost.udlign_date for settled groups that have none.
// 20261028 CDX/NTR Apply the tekst_key schema migration to both tenant and master databases.

/**
 * Injected by includes/connect.php via the entry page that includes this file:
 * @var string $db_type
 */

// ===== PROTECTED: when a version is cut (see doc/ai/convention_database_changes.md), COPY this =====
// ===== segment into the opdat_<major>.<minor>.php file - do not move it. The moved statements =====
// ===== call these helpers, and this file keeps needing them for the next release. =====
include_once(__DIR__ . '/opdat_func/deleteStaleTekst.php');
// ===== END PROTECTED =====

// 20261021 Sawaneh SD-733: Shopify schema manifest v1. The catalog lives in includes/shopify/schema.php and is
// shared with admin/opret.php; ensure_shopify_schema() returns at once when the marker already shows the
// current version, and does nothing on MySQL tenants (the Shopify module is PostgreSQL only).
include_once(__DIR__ . '/shopify/schema.php');
ensure_shopify_schema();

// 20261026 CL/SZ SD-758: the POS brand texts were added as 5439-5441, which master uses for the kassekladde
// voucher alert (5439) and the bogfor.php posting refusal (5440/5441), and have moved to 7200-7202. A tenant
// that opened POS-valg on hml/shopify still has the brand texts cached under the old ids, so the finance
// pages would show them. Only rows still holding a brand text are deleted; findtekst() re-seeds them.
deleteStaleTekst(5439, 'Brand');
deleteStaleTekst(5440, array(
	'Brand salget oprettes med på denne kasse. Gemmes som ordrens sprog/formularvariant',
	"Brand a sale is created with on this terminal. Stored as the order's language/form variant",
	'Brand salget opprettes med på denne kassen. Lagres som ordrens språk/formularvariant'
));
deleteStaleTekst(5441, array('Ikke gemt, mangler formularvariant', 'Not saved, no matching form variant', 'Ikke lagret, mangler formularvariant'));

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

?>
