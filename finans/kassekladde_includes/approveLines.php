<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/approveLines.php --- ver 5.1.1 --- 2026-11-22 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261019 ASR Delvis bogføring: AJAX-endpoint der sætter/fjerner "Godkendt" på kladdelinjer (kassekladde.godkendt/godkendt_af/godkendt_tid). Kun åbne kladder (bogfort '-'), kun linjer der hører til kladden. Skriver journal.lines_approved/_unapproved i audit_log.
// 20261023 ASR Review (#817): $modulnr = 2 (finans) as in kassekladde.php, so online.php's module-rights check runs for this endpoint too; with 0 the check is skipped and any logged-in user could approve lines.
//
// Request (POST): kladde_id, ids (comma-separated), godkendt (1|0), snapshots (JSON id => fingerprint)
// Response (JSON): {ok, approved, total} eller {ok:false, error}
// 20261105 CDX/NTR Keep the finance permission check when integrating master.
// 20261122 CDX/ASR Reject approvals of changed content and serialize with journal saves and posting.

ob_start();
session_start();
$s_id = session_id();
$title = 'approveLines';
$modulnr = 2; // finans: online.php only checks the module rights when $modulnr is set
$bg = 'nix';
$header = 'nix';
$webservice = true;

include(__DIR__ . '/../../includes/connect.php');
include(__DIR__ . '/../../includes/online.php');
include_once(__DIR__ . '/../../includes/std_func.php');
include_once(__DIR__ . '/../../includes/auditLog.php');
include_once(__DIR__ . '/journalApproval.php');
include_once(__DIR__ . '/journalSaveLock.php');

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

$snapshots = json_decode((string)ifset($_POST, 'snapshots', '{}'), true);
kkJournalLock($kladde_id);
$result = kk_set_approval($kladde_id, $ids, (bool)$godkendt, is_array($snapshots) ? $snapshots : array(), (string)$brugernavn);
kkJournalUnlock();
if (!$result['ok']) {
	echo json_encode($result);
	exit;
}

$tot = db_fetch_array(db_select("select count(*) as total, sum(case when godkendt = TRUE then 1 else 0 end) as approved from kassekladde where kladde_id = '$kladde_id'", __FILE__ . " linje " . __LINE__));
echo json_encode(array('ok' => true, 'approved' => (int)($tot['approved'] ?? 0), 'total' => (int)($tot['total'] ?? 0)));
exit;
