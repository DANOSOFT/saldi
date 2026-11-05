<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- systemdata/diverseIncludes/show_vibrant_login.php ---------- ver 5.0.0----2026.10.07-------
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
// Copyright (c) 2026-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261007 CL/SZ SST-843 Returns the saved Vibrant terminal login for "Vis login" on Diverse valg.
//                The page used to print the email and password into its source.

/**
 * Injected by ../../includes/online.php, included below:
 * @var string $rettigheder
 */

ob_start();

# $header and $bg are read by includes/online.php; "nix" keeps the answer free of the page frame
$header = "nix";
$bg     = "nix";
# $modulnr is not passed to online.php, which would answer a missing right with an HTML page; checked below

@session_start();
$s_id = session_id();

include (__DIR__ . "/../../includes/connect.php");
include (__DIR__ . "/../../includes/online.php");
include (__DIR__ . "/../../includes/std_func.php");

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * Answer with JSON and stop the request.
 *
 * @param int   $http_code HTTP status to send.
 * @param array $svar      Body; always carries 'success'.
 *
 * @return void
 */
function vibrant_login_vis_svar($http_code, $svar) {
	http_response_code($http_code);
	print json_encode($svar);
	exit;
}

# Indstillinger is module 1, the same right the Diverse valg page needs
if (!isset($rettigheder) || $rettigheder === '' || substr($rettigheder, 1, 1) < '1') {
	vibrant_login_vis_svar(403, array('success' => false, 'error' => 'Missing the Indstillinger right'));
}

if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'POST') {
	vibrant_login_vis_svar(405, array('success' => false, 'error' => 'POST required'));
}

$csrf_token = ifset($_SESSION, 'csrf_token', '');
if ($csrf_token === '' || !hash_equals($csrf_token, (string) ifset($_SERVER, 'HTTP_X_CSRF_TOKEN', ''))) {
	vibrant_login_vis_svar(403, array('success' => false, 'error' => 'Invalid or expired form token'));
}

# Same row Diverse valg checks before it shows the button: var_name is the email, var_value the password
$r = db_fetch_array(db_select("SELECT var_name, var_value FROM settings WHERE var_grp='vibrant_account'", __FILE__ . " linje " . __LINE__));
if (!$r) {
	vibrant_login_vis_svar(404, array('success' => false, 'error' => 'Der er ikke gemt et Vibrant login'));
}

vibrant_login_vis_svar(200, array('success' => true, 'email' => $r['var_name'], 'password' => $r['var_value']));
