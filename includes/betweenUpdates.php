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
// 20261028 CL/LH MB-72: One-time backfill of openpost.udlign_date for settled groups that have none.

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

?>
