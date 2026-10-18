<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- systemdata/diverseIncludes/save_flatpay_id.php ---------- ver 5.0.0----2026.10.02-------
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
// 20260928 Sawaneh Security 4.0 (A10): the Flatpay login is exchanged for the GUID here on the server,
//                  so username/password never leave the browser for a third-party host and are never logged.
// 20261002 LOE SST-844 Flatpay ID saves need the Indstillinger right, a CSRF token and a GUID value.
// 20261008 Sawaneh Merge of master: SST-844's JSON answers, CSRF token and GUID check around the server-side login
//                  exchange; the right is settings.integrations (write), the legacy Indstillinger bit without roles.

ob_start();

# $header and $bg are read by includes/online.php; "nix" keeps the answer free of the page frame
$header = "nix";
$bg     = "nix";
# $modulnr is deliberately not passed to online.php: when it is set and the right is missing, online.php
# prints an HTML denial page and exits from inside the include, so a refused call would answer with HTML
# and HTTP 200 instead of this endpoint's JSON. The right is checked below with the same expression.

@session_start();
$s_id = session_id();

include ("../../includes/connect.php");
$permission_key = 'any';
include ("../../includes/online.php");
include ("../../includes/std_func.php");

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

/**
 * Answer the settings page with JSON and stop the request.
 */
function flatpay_id_svar($http_code, $success, $fejl = NULL) {
	http_response_code($http_code);
	$svar = array('success' => $success);
	if ($fejl !== NULL) {
		$svar['error'] = $fejl;
	}
	print json_encode($svar);
	exit;
}

# This writes a payment credential: settings.integrations (write), or the Indstillinger right without roles.
$allowed = function_exists('perm_can') ? perm_can('settings.integrations', 'write') : (isset($rettigheder) && substr((string) $rettigheder, 1, 1) === '1');
if (!$allowed) {
	flatpay_id_svar(403, false, 'Missing the Indstillinger right');
}
if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'POST') {
	flatpay_id_svar(405, false, 'POST required');
}
$csrf_token = ifset($_SESSION, 'csrf_token', '');
if ($csrf_token === '' || !hash_equals($csrf_token, (string) ifset($_SERVER, 'HTTP_X_CSRF_TOKEN', ''))) {
	flatpay_id_svar(403, false, 'Invalid or expired form token');
}

$post = json_decode(file_get_contents('php://input'), true);
$username = is_array($post) && isset($post['username']) ? (string) $post['username'] : '';
$password = is_array($post) && isset($post['password']) ? (string) $post['password'] : '';
if ($username === '' || $password === '') {
	flatpay_id_svar(400, false, 'Missing login');
}
$ch = curl_init('https://socket.flatpay.dk/socket/guid');
curl_setopt_array($ch, array(
	CURLOPT_POST           => true,
	CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
	CURLOPT_POSTFIELDS     => json_encode(array('username' => $username, 'password' => $password)),
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT        => 15,
));
$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$id = trim((string) $body, " \t\n\r\"");

# Flatpay returns the GUID, e.g. 00000000-0000-4000-8000-000000000000 (example, not a real ID).
if ($status !== 200 || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
	flatpay_id_svar(401, false, 'Login failed');
}
$id = db_escape_string($id);

$r = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name='flatpay_auth'", __FILE__ . " linje " . __LINE__));
if ($r) {
	db_modify("UPDATE settings SET var_value='$id' WHERE var_name='flatpay_auth'", __FILE__ . " linje " . __LINE__);
} else {
	db_modify("INSERT INTO settings(var_name, var_grp, var_value, var_description) VALUES ('flatpay_auth', 'globals', '$id', 'The flatpay auth GUID')", __FILE__ . " linje " . __LINE__);
}
flatpay_id_svar(200, true);
?>
