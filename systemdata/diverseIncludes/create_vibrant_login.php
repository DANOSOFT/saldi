<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- systemdata/diverseIncludes/create_vibrant_term.php ---------- lap 3.9.9----2023.03.15-------
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
// Copyright (c) 2012-2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20261001 Sawaneh Input escaped; an account with the same e-mail is replaced (one row per setting).
// 20261002 Sawaneh The user is created at Vibrant here on the server with the stored API key (spec B-D10: the key and the
//                  password no longer pass through the browser); needs settings.integrations write; answers JSON.
@session_start();
$s_id = session_id();

include ("../../includes/connect.php");
$permission_key = 'system.indstillinger';
include ("../../includes/online.php");
include ("../../includes/std_func.php");
include_once(__DIR__ . "/../settingsRegistry.php");
include_once(__DIR__ . "/../../includes/settings/components.php");
include_once(__DIR__ . "/../../includes/settings/integrations.php");

header('Content-Type: application/json');
if (function_exists('require_permission')) {
	require_permission('settings.integrations', 'write');
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
