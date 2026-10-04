<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/kreditorFromCvr.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ SD-721 Created: JSON endpoint for the kreditor from the CVR register (javascript/kreditorFromCvr.js).
//                Actions (POST): resolve (pool document), form (groups, terms, defaults), create, undo ("Fortryd"), confirmBank ("Bekræft").
//                The company and the user come from the session; creating, undoing and confirming need the kreditor module's rights.
// 20261004 CL/SZ SD-721 Action reopen ("Genåbn") for a closed kreditor with the document's CVR number; create takes allowClosed for "Opret ny".

@session_start();
$s_id = session_id();

$bg = "nix";
$header = 'nix';
$modulnr = 0;
ob_start(); // online.php writes html, which must not end up in the JSON answer
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/kreditorFromCvr.php");
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

/**
 * Sends the answer and ends the request.
 *
 * @param array<string, mixed> $data
 * @param int $status HTTP status.
 * @return void
 */
function kreditorCvrAnswer(array $data, $status = 200) {
	http_response_code($status);
	print json_encode($data, JSON_UNESCAPED_UNICODE);
	exit;
}

if (empty($brugernavn) || empty($db)) kreditorCvrAnswer(array('error' => 'not authenticated'), 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') kreditorCvrAnswer(array('error' => 'POST only'), 405);
// The kreditor module (8), as on the kreditor card
if (!empty($rettigheder) && substr($rettigheder, 8, 1) < '1') kreditorCvrAnswer(array('error' => 'no access'), 403);

$action = if_isset($_POST, '', 'action');
$userId = isset($bruger_id) && is_numeric($bruger_id) && (int)$bruger_id !== 0 ? (int)$bruger_id : null;

if ($action === 'resolve') {
	// The CVR lookup may take a few seconds; the session isn't written below
	session_write_close();
	kreditorCvrAnswer(kreditorCvrResolve((string)if_isset($_POST, '', 'poolFile'), $userId));
}

if ($action === 'form') {
	kreditorCvrAnswer(array(
		'groups' => kreditorCvrGroups($regnaar),
		'terms' => kreditorCvrTerms(),
		'defaults' => kreditorCvrDefaults(),
	));
}

if ($action === 'create') {
	$gruppe = (int)if_isset($_POST, 0, 'gruppe');
	$groupOk = false;
	foreach (kreditorCvrGroups($regnaar) as $group) {
		if ($group['kodenr'] === $gruppe) $groupOk = true;
	}
	if (!$groupOk) kreditorCvrAnswer(array('error' => 'group'), 400);
	$betalingsbet = (string)if_isset($_POST, 'Netto', 'betalingsbet');
	$betalingsdage = (int)if_isset($_POST, 8, 'betalingsdage');
	$defaults = kreditorCvrDefaults();
	// The first creation asks for the group once and stores the answer; later the dialog may change it when "Gem som standard" is ticked
	if ($defaults['gruppe'] === null || if_isset($_POST, '', 'saveDefault') === '1') {
		kreditorCvrSaveDefaults($gruppe, in_array($betalingsbet, kreditorCvrTerms(), true) ? $betalingsbet : 'Netto', $betalingsdage);
	}
	$company = array();
	foreach (array('cvrnr', 'firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'tlf', 'email') as $key) {
		$company[$key] = (string)if_isset($_POST, '', $key);
	}
	// Bank details only ever come from the document itself, never from the request
	$file = kreditorCvrPoolFile((string)if_isset($_POST, '', 'poolFile'));
	$mode = if_isset($_POST, '', 'mode') === 'dialog' ? 'dialog' : 'click';
	$result = kreditorCvrCreate($company, array(
		'gruppe' => $gruppe,
		'betalingsbet' => $betalingsbet,
		'betalingsdage' => $betalingsdage,
		'bank' => $file ? $file['bank'] : null,
		'auto' => false,
		'userId' => $userId,
		'poolFileId' => $file ? $file['id'] : null,
		'mode' => $mode,
		'allowClosed' => if_isset($_POST, '', 'allowClosed') === '1',
	));
	kreditorCvrAnswer($result, $result['status'] === 'error' ? 400 : 200);
}

if ($action === 'undo') {
	$result = kreditorCvrUndo((int)if_isset($_POST, 0, 'id'));
	kreditorCvrAnswer($result, $result['ok'] ? 200 : 409);
}

if ($action === 'reopen') {
	$file = kreditorCvrPoolFile((string)if_isset($_POST, '', 'poolFile'));
	$result = kreditorCvrReopen((int)if_isset($_POST, 0, 'id'), $file ? $file['id'] : null);
	kreditorCvrAnswer($result, $result['ok'] ? 200 : 409);
}

if ($action === 'confirmBank') {
	kreditorCvrAnswer(array('ok' => kreditorCvrConfirmBank((int)if_isset($_POST, 0, 'id'))));
}

kreditorCvrAnswer(array('error' => 'unknown action'), 400);
?>
