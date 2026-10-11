<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/topbarAction.php --- lap 5.0.0 --- 2026.09.16 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260916 Sawaneh Controller for topbar actions (fiscal-year switch from the user chip).
//                  POST only, redirects back to the shell (Post/Redirect/Get).
// 20260922 Sawaneh Language switch action; auditor sessions keep their year in the master revisor table.
// 20260927 Sawaneh Placement action (cluster_placement setting, spec 2.3).
// 20261005 Sawaneh Placement action removed: the cluster is always in the top bar (topbar addendum 2026-10-05 §2).
// 20261008 Sawaneh action=theme stores the user's light/dark/system choice (settings ui/theme).
// 20261009 Sawaneh action=theme also sets the saldi_theme cookie so auditor/master-admin sessions keep the choice.
// 20261009 Sawaneh action=auto_extract / action=sidebar store the personal settings that replace two cookies (4e).

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
$action = isset($request['action']) ? (string) $request['action'] : '';
$returnHash = isset($request['return_hash']) ? (string) $request['return_hash'] : '';

// Activating a fiscal year = the same two writes systemdata/brugerdata.php has always
// done: the user's default in `brugere` (next login) and the session row in the
// master `online` table (what every page reads as $regnaar). connect.php must be
// re-included at top level so the master connection is the one the db_* helpers see.
// Auditor / master-admin sessions (bruger_id -1) have no brugere row but still
// switch year for their session, as they always could via brugerdata.php.
if ($action === 'fiscal_year') {
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
if ($action === 'theme') {
	// Lys / Mørk / System in the user chip (prototype_dashboard_tema v5): a personal setting, answered as JSON when asked by fetch.
	include_once(__DIR__ . "/../includes/std_func.php");
	$theme = isset($request['theme']) ? (string) $request['theme'] : '';
	if (in_array($theme, array('light', 'dark', 'system'), true)) {
		if ((int) $bruger_id > 0) {
			update_settings_value('theme', 'ui', $theme, 'UI theme: light, dark or system', (int) $bruger_id);
		}
		// Auditor/master-admin sessions have no user row: the cookie carries the choice (online.php reads it as the fallback).
		saldi_setcookie('saldi_theme', $theme, time() + 31536000, '/', '', null, false);
	}
	if (!empty($request['ajax'])) {
		ob_end_clean();
		header('Content-Type: application/json; charset=utf-8');
		print json_encode(array('ok' => true, 'theme' => $theme));
		exit;
	}
}
// 4e (settings redesign §7.4 item 5): the autoExtract and isSidebarOpen cookies became user settings; the page toggles post here.
if ($action === 'auto_extract' || $action === 'sidebar') {
	include_once(__DIR__ . "/../includes/settings/SettingsService.php");
	$on = !empty($request['value']) && $request['value'] !== '0' && $request['value'] !== 'false';
	if ((int) $bruger_id > 0) {
		SettingsService::save($action === 'sidebar' ? 'personal.ui.sidebar_closed' : 'personal.documents.auto_extract', $on, (int) $bruger_id);
	}
	if (!empty($request['ajax'])) {
		ob_end_clean();
		header('Content-Type: application/json; charset=utf-8');
		print json_encode(array('ok' => true, 'value' => $on));
		exit;
	}
}
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
 * The requested year, if it is one of the company's open fiscal years; else ''.
 */
function topbar_open_fiscal_year(string $year): string
{
	$year = trim($year);
	if ($year === '' || !preg_match('/^[0-9]+$/', $year)) {
		return '';
	}
	$qtxt = "select kodenr from grupper where art = 'RA' and kodenr = '$year' and box5 = 'on'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		return '';
	}
	return $year;
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
