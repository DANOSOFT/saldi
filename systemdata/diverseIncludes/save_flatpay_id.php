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
// 20261002 LOE SST-844 Flatpay ID saves need the Indstillinger right, a CSRF token and a GUID value.

ob_start();

# $header and $bg are read by includes/online.php; "nix" keeps the answer free of the page frame
$header = "nix";
$bg     = "nix";
# Indstillinger. Setting $modulnr before the include is what makes online.php check the right: the
# script used to leave it unset, so every logged-in user could write the setting.
$modulnr = 1;

@session_start();
$s_id = session_id();

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

/**
 * Answer the popup with JSON and stop the request.
 *
 * @param int         $http_code HTTP status to send.
 * @param bool        $success   Whether the ID was saved.
 * @param string|null $fejl      Short reason when $success is false.
 *
 * @return void
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

if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'POST') {
	flatpay_id_svar(405, false, 'POST required');
}

# Session-bound token from the settings page, so a forged cross-site POST cannot change the ID
$csrf_token = ifset($_SESSION, 'csrf_token', '');
if ($csrf_token === '' || !hash_equals($csrf_token, (string) ifset($_SERVER, 'HTTP_X_CSRF_TOKEN', ''))) {
	flatpay_id_svar(403, false, 'Invalid or expired form token');
}

# Expect a posted ID
$post = json_decode(file_get_contents('php://input'), true);
$id   = ifset($post, 'id', '');
if (!is_string($id)) {
	flatpay_id_svar(400, false, 'Expected an id');
}
$id = trim($id);

# Flatpay returns the GUID, e.g. 00000000-0000-4000-8000-000000000000 (example, not a real ID).
# An empty or malformed ID is refused, so a call without a usable ID can no longer blank or
# overwrite the setting.
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
	flatpay_id_svar(400, false, 'Expected a GUID');
}
$id = db_escape_string($id);

$qtxt = "SELECT var_value FROM settings WHERE var_name='flatpay_auth'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

# If the row alreayd exsists
if ($r) {
  $qtxt = "UPDATE settings SET var_value='$id' WHERE var_name='flatpay_auth'";
  db_modify($qtxt, __FILE__ . " linje " . __LINE__);
# If the row needs to be created in the database
} else {
  $qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description) VALUES ('flatpay_auth', 'globals', '$id', 'The flatpay auth GUID')";
  db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

flatpay_id_svar(200, true);
?>

