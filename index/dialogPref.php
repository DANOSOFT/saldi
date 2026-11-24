<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/dialogPref.php --- ver 5.1.1 --- 2026.11.14 ---
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
// 20261114 ASR Created: "Vis ikke igen" in Saldi's message box (javascript/saldi-dialog.js), a POST of {key, hide} with the session's CSRF token.
//              hide = true stores the key for the logged-in user (settings var_grp 'dialogs', user_id), false removes it again.
/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var int $bruger_id
 */
@session_start();
$s_id = session_id();
ob_start();
$title = 'dialogPref';
$css = '';
$header = 'nix';
$permission_key = 'any';
$skipLogtime = true;
include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $token === '' || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
	http_response_code(403);
	print json_encode(array('ok' => false, 'error' => 'csrf'));
	exit;
}
$brugerId = (int) $bruger_id;
$in = json_decode((string) file_get_contents('php://input'), true);
$key = (is_array($in) && isset($in['key'])) ? (string) $in['key'] : '';
if ($brugerId <= 0 || !preg_match('/^[a-z0-9_.-]{1,40}$/', $key)) {
	http_response_code(400);
	print json_encode(array('ok' => false, 'error' => 'key'));
	exit;
}
$k = db_escape_string($key);
$exists = db_fetch_array(db_select("select id from settings where var_grp = 'dialogs' and var_name = '$k' and user_id = $brugerId", __FILE__ . " linje " . __LINE__));
if (!empty($in['hide'])) {
	if (!$exists) db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('dialogs', '$k', 'hide', 'Question not shown again (Vis ikke igen)', $brugerId)", __FILE__ . " linje " . __LINE__);
} elseif ($exists) {
	db_modify("delete from settings where var_grp = 'dialogs' and var_name = '$k' and user_id = $brugerId", __FILE__ . " linje " . __LINE__);
}
print json_encode(array('ok' => true));
