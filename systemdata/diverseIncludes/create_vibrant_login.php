<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- systemdata/diverseIncludes/create_vibrant_login.php ---------- ver 5.0.0----2026.10.07-------
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
// Copyright (c) 2012-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 Sawaneh Input escaped; an account with the same e-mail is replaced (one row per setting).
// 20261002 Sawaneh The user is created at Vibrant here on the server with the stored API key (spec B-D10: the key and the
//                  password no longer pass through the browser); needs settings.integrations write; answers JSON.
// 20261023 ASR Merge of master (SST-843, CL/SZ): the answer is kept free of online.php's page frame ($header/$bg = nix and an
//                output buffer, as save_flatpay_id.php), only POST is accepted (405), and a second Vibrant login is refused
//                (409) before Vibrant is called - the settings page hides the form once a login is saved, so that only
//                meets a stale tab or a direct call.
ob_start();

# $header and $bg are read by includes/online.php; "nix" keeps the answer free of the page frame
$header = "nix";
$bg     = "nix";

@session_start();
$s_id = session_id();

include ("../../includes/connect.php");
$permission_key = 'system.indstillinger';
include ("../../includes/online.php");
include ("../../includes/std_func.php");
include_once(__DIR__ . "/../settingsRegistry.php");
include_once(__DIR__ . "/../../includes/settings/components.php");
include_once(__DIR__ . "/../../includes/settings/integrations.php");

ob_end_clean();
header('Content-Type: application/json');
if (function_exists('require_permission')) {
	require_permission('settings.integrations', 'write');
}
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	print json_encode(array('ok' => false, 'message' => 'post'));
	exit;
}
// 20261014 Sawaneh Review: the token the mini form sends (X-CSRF-Token) is checked, not only sent.
$csrfHeader = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (!isset($_SESSION['csrf_token']) || $csrfHeader === '' || !hash_equals((string) $_SESSION['csrf_token'], $csrfHeader)) {
	http_response_code(403);
	print json_encode(array('ok' => false, 'message' => 'csrf'));
	exit;
}
$post = json_decode(file_get_contents('php://input'));
$name = isset($post->name) ? trim((string) $post->name) : '';
$email = isset($post->email) ? trim((string) $post->email) : '';
$passwd = isset($post->passwd) ? (string) $post->passwd : '';
if ($name === '' || $passwd === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
	http_response_code(400);
	print json_encode(array('ok' => false, 'message' => 'input'));
	exit;
}
# A login is already saved: refused before Vibrant is called, so a stale form cannot create a second user and a second row
if (db_fetch_array(db_select("SELECT id FROM settings WHERE var_grp = 'vibrant_account'", __FILE__ . " linje " . __LINE__))) {
	http_response_code(409);
	print json_encode(array('ok' => false, 'message' => 'exists'));
	exit;
}
$res = settings_vibrant_create_user($name, $email, $passwd);
if (!$res['ok']) {
	http_response_code(502);
	print json_encode(array('ok' => false, 'message' => $res['message']));
	exit;
}
$emailEsc = db_escape_string($email);
db_modify("DELETE FROM settings WHERE var_name = '$emailEsc' AND var_grp = 'vibrant_account'", __FILE__ . " linje " . __LINE__);
$qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description) VALUES ('$emailEsc', 'vibrant_account', '" . db_escape_string($passwd) . "', 'The used vibrant account login')";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);
if (function_exists('audit_log')) {
	audit_log('setting.action', 'integrations.vibrant.terminal_login');
}
print json_encode(array('ok' => true, 'message' => ''));
