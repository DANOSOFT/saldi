<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/approveLines.php --- ver 5.0.0 --- 2026-10-19 ---
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
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20261019 ASR Delvis bogføring: AJAX-endpoint der sætter/fjerner "Godkendt" på kladdelinjer
//              (kassekladde.godkendt/godkendt_af/godkendt_tid). Kun åbne kladder (bogfort '-'),
//              kun linjer der hører til kladden. Skriver journal.lines_approved/_unapproved i audit_log.
//
// Request (POST): kladde_id, ids (kommasepareret liste af kassekladde.id), godkendt (1|0)
// Response (JSON): {ok, approved, total} eller {ok:false, error}

ob_start();
session_start();
$s_id = session_id();
$title = 'approveLines';
$modulnr = 0;
$bg = 'nix';
$header = 'nix';
$webservice = true;

include(__DIR__ . '/../../includes/connect.php');
include(__DIR__ . '/../../includes/online.php');
include_once(__DIR__ . '/../../includes/std_func.php');
include_once(__DIR__ . '/../../includes/auditLog.php');

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (empty($regnaar) || !$db || $db == $sqdb) {
	echo json_encode(array('ok' => false, 'error' => 'Session expired'));
	exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(array('ok' => false, 'error' => 'POST required'));
	exit;
}

$kladde_id = (int)ifset($_POST, 'kladde_id', 0);
$godkendt  = (int)ifset($_POST, 'godkendt', 0) ? 1 : 0;
$ids = array();
foreach (explode(',', (string)ifset($_POST, 'ids', '')) as $one) {
	$one = (int)trim($one);
	if ($one > 0) $ids[] = $one;
}
$ids = array_values(array_unique($ids));

if (!$kladde_id || !$ids) {
	echo json_encode(array('ok' => false, 'error' => 'Missing kladde_id or ids'));
	exit;
}

// Only an open journal can have its lines approved; a posted or simulated one is read-only.
$r = db_fetch_array(db_select("select bogfort from kladdeliste where id = '$kladde_id'", __FILE__ . " linje " . __LINE__));
if (!$r || $r['bogfort'] != '-') {
	echo json_encode(array('ok' => false, 'error' => 'Journal is not open'));
	exit;
}

// Keep only the ids that belong to this journal, so the audit entry lists what was actually changed.
$idList = implode(',', $ids);
$ids = array();
$q = db_select("select id from kassekladde where kladde_id = '$kladde_id' and id in ($idList)", __FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($q)) $ids[] = (int)$row['id'];
if (!$ids) {
	$tot = db_fetch_array(db_select("select count(*) as total, sum(case when godkendt = TRUE then 1 else 0 end) as approved from kassekladde where kladde_id = '$kladde_id'", __FILE__ . " linje " . __LINE__));
	echo json_encode(array('ok' => true, 'approved' => (int)($tot['approved'] ?? 0), 'total' => (int)($tot['total'] ?? 0)));
	exit;
}
$idList = implode(',', $ids);
$brugernavn_sql = db_escape_string((string)$brugernavn);
if ($godkendt) {
	$qtxt = "update kassekladde set godkendt = TRUE, godkendt_af = '$brugernavn_sql', godkendt_tid = now() ";
} else {
	$qtxt = "update kassekladde set godkendt = FALSE, godkendt_af = NULL, godkendt_tid = NULL ";
}
// kladde_id in the WHERE: an id from another journal (or another tab) is never touched.
$qtxt .= "where kladde_id = '$kladde_id' and id in ($idList)";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);

audit_log_write(
	$godkendt ? 'journal.lines_approved' : 'journal.lines_unapproved',
	'kladde',
	(string)$kladde_id,
	audit_log_details_json(array('after' => array('ids' => $ids, 'godkendt' => (bool)$godkendt)))
);

$tot = db_fetch_array(db_select("select count(*) as total, sum(case when godkendt = TRUE then 1 else 0 end) as approved from kassekladde where kladde_id = '$kladde_id'", __FILE__ . " linje " . __LINE__));
echo json_encode(array('ok' => true, 'approved' => (int)($tot['approved'] ?? 0), 'total' => (int)($tot['total'] ?? 0)));
exit;
