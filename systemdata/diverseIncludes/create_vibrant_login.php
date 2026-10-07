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
// 20261007 CL/SZ SST-843 Creates the Vibrant user here with the API key from settings, instead of the
//                Diverse valg page calling Vibrant with the key in its JavaScript.
//                Needs the Indstillinger right and a CSRF token, and escapes the saved login.
//                Refuses (409) when a Vibrant login is already saved, before calling Vibrant.

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

/**
 * Answer the popup with JSON and stop the request.
 *
 * @param int         $http_code HTTP status to send.
 * @param bool        $success   Whether the login was created.
 * @param string|null $fejl      Short reason when $success is false.
 *
 * @return void
 */
function vibrant_login_svar($http_code, $success, $fejl = NULL) {
	http_response_code($http_code);
	$svar = array('success' => $success);
	if ($fejl !== NULL) {
		$svar['error'] = $fejl;
	}
	print json_encode($svar);
	exit;
}

# Indstillinger is module 1; this creates a user at Vibrant with the shop's API key
if (!isset($rettigheder) || $rettigheder === '' || substr($rettigheder, 1, 1) < '1') {
	vibrant_login_svar(403, false, 'Missing the Indstillinger right');
}

if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'POST') {
	vibrant_login_svar(405, false, 'POST required');
}

$csrf_token = ifset($_SESSION, 'csrf_token', '');
if ($csrf_token === '' || !hash_equals($csrf_token, (string) ifset($_SERVER, 'HTTP_X_CSRF_TOKEN', ''))) {
	vibrant_login_svar(403, false, 'Invalid or expired form token');
}

$post   = json_decode(file_get_contents('php://input'), true);
$name   = trim((string) ifset($post, 'name', ''));
$email  = trim((string) ifset($post, 'email', ''));
$passwd = (string) ifset($post, 'passwd', '');
if ($name === '' || $email === '' || $passwd === '') {
	vibrant_login_svar(400, false, 'Navn, email og adgangskode skal udfyldes');
}

# Diverse valg only offers "Opret login" when none is saved; a repeated or direct call must not create
# a second user at Vibrant or a second row that "Vis login" might pick instead
if (db_fetch_array(db_select("SELECT id FROM settings WHERE var_grp='vibrant_account'", __FILE__ . " linje " . __LINE__))) {
	vibrant_login_svar(409, false, 'Der er allerede gemt et Vibrant login');
}

$r = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name='vibrant_auth'", __FILE__ . " linje " . __LINE__));
$apikey = $r ? $r[0] : '';
if ($apikey === '') {
	vibrant_login_svar(400, false, 'Gem din Vibrant API nøgle først');
}

$data = array(
	'name'     => $name,
	'email'    => $email,
	'roleIds'  => array(
		'ro_1xBHy6kquVWMne9caAaXps',
		'ro_bzDKsUpAeFsFm8kUUXkXTy'
	),
	'password' => $passwd
);

$ch = curl_init('https://pos.api.vibrant.app/pos/v1/users');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json', 'apikey: ' . $apikey));
$svar = curl_exec($ch);
$http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($svar === false || $http_code < 200 || $http_code > 299) {
	# Vibrant's own error and message, as the page showed them before
	$res = json_decode((string) $svar, true);
	if (is_array($res)) {
		# A validation error can carry a list of messages instead of one string
		$besked = ifset($res, 'message', '');
		$fejl = ifset($res, 'error', '') . ' : ' . (is_scalar($besked) ? $besked : json_encode($besked));
	} else {
		$fejl = "Vibrant svarede ikke ($http_code)";
	}
	vibrant_login_svar(502, false, $fejl);
}

$email  = db_escape_string($email);
$passwd = db_escape_string($passwd);
$qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description) VALUES ('$email', 'vibrant_account', '$passwd', 'The used vibrant account for logging into the clients terminal, var_name is the email and var_value is the password')";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);

vibrant_login_svar(200, true);
