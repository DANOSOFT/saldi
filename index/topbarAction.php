<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/topbarAction.php --- ver 5.1.0 --- 2026.10.31 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260916 Sawaneh Controller for topbar actions (fiscal-year switch from the user chip).
//                  POST only, redirects back to the shell (Post/Redirect/Get).
// 20260922 Sawaneh Language switch action; auditor sessions keep their year in the master revisor table.
// 20260927 Sawaneh Placement action (cluster_placement setting, spec 2.3).
// 20261005 Sawaneh Placement action removed: the cluster is always in the top bar (topbar addendum 2026-10-05 §2).
// 20261031 CL/ASR MB-94: a fiscal year can only be switched to when active_fiscal_years() offers it (a year with box10 set is deleted).

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var string $brugernavn
 * @var int    $bruger_id
 * @var string $db
 * @var mixed  $revisor
 */

@session_start();
$s_id = session_id();
ob_start();

$title = 'topbarAction';
$css = '';
$permission_key = 'any';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");

$request = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : array();
// 20261014 Sawaneh Review: every action needs the shell's CSRF token.
if ($request && (!isset($request['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $request['csrf_token']))) {
	if (function_exists('audit_log')) {
		audit_log('csrf', 'topbarAction.php');
	}
	$request = array();
}
$action = isset($request['action']) ? (string) $request['action'] : '';
$returnHash = isset($request['return_hash']) ? (string) $request['return_hash'] : '';

// Activating a fiscal year = the same two writes systemdata/brugerdata.php has always
// done: the user's default in `brugere` (next login) and the session row in the
// master `online` table (what every page reads as $regnaar). connect.php must be
// re-included at top level so the master connection is the one the db_* helpers see.
// Auditor / master-admin sessions (bruger_id -1) have no brugere row but still
// switch year for their session, as they always could via brugerdata.php.
if ($action === 'fiscal_year') {
	include_once(__DIR__ . "/../includes/std_func.php");
	$year = topbar_open_fiscal_year(isset($request['year']) ? (string) $request['year'] : '');
	if ($year !== '') {
		if (!$revisor && (int) $bruger_id > 0) {
			db_modify("update brugere set regnskabsaar = '$year' where id = " . (int) $bruger_id, __FILE__ . " linje " . __LINE__);
		}
		include(__DIR__ . "/../includes/connect.php");
		db_modify("update online set regnskabsaar = '$year' where session_id = '" . db_escape_string($s_id) . "'", __FILE__ . " linje " . __LINE__);
		if ($revisor && isset($db_id)) {
			// Auditor sessions remember their year per company in the master `revisor` table.
			db_modify("update revisor set regnskabsaar = '$year' where brugernavn = '" . db_escape_string((string) $brugernavn) . "' and db_id = '" . (int) $db_id . "'", __FILE__ . " linje " . __LINE__);
		}
	}
}

// Language (spec 2.1): persisted on the user, on the session row and in the cookie the
// login page reads, so the choice follows the user across devices.
if ($action === 'language') {
	$languageId = (int) (isset($request['language_id']) ? $request['language_id'] : 0);
	if ($languageId >= 1 && $languageId <= 3) {
		if (!$revisor && (int) $bruger_id > 0) {
			db_modify("update brugere set language_id = $languageId where id = " . (int) $bruger_id, __FILE__ . " linje " . __LINE__);
		}
		saldi_setcookie('languageId', (string) $languageId, time() + (10 * 365 * 24 * 60 * 60), '/');
		include(__DIR__ . "/../includes/connect.php");
		db_modify("update online set language_id = '$languageId' where session_id = '" . db_escape_string($s_id) . "'", __FILE__ . " linje " . __LINE__);
	}
}

ob_end_clean();
header('Location: main.php' . topbar_return_hash($returnHash));
exit;

/**
 * The requested year, if it is one of the company's open, non-deleted fiscal years
 * (active_fiscal_years(), the list the switch offers); else ''.
 */
function topbar_open_fiscal_year(string $year): string
{
	$year = trim($year);
	if ($year === '' || !preg_match('/^[0-9]+$/', $year)) {
		return '';
	}
	foreach (active_fiscal_years() as $r) {
		if ((string) $r['kodenr'] === $year) {
			return $year;
		}
	}
	return '';
}

/**
 * Only an in-app path (as the shell writes it to location.hash) may be echoed back.
 */
function topbar_return_hash(string $hash): string
{
	$hash = trim($hash);
	if ($hash === '' || $hash[0] !== '/' || strpos($hash, '//') === 0 || preg_match('/[\s<>"\'\\\\]/', $hash)) {
		return '';
	}
	return '#' . $hash;
}
