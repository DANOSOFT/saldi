<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/payments/lane3000_proxy.php --- ver 5.0.0 --- 2026.10.07 ---
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
// 20261007 CL/SZ SST-843 Server-side proxy for Nets Connect@Cloud (Lane3000/Move3500), so the register's
//                login and the bearer token stay on the server.
//                lane3000.php and lane3000_afstemning.php call this page instead of connectcloud.aws.nets.eu.

/**
 * Injected by ../../includes/online.php, included below:
 * @var string $regnaar
 */

ob_start();

# $header and $bg are read by includes/online.php; "nix" keeps the answer free of the page frame
$header = "nix";
$bg     = "nix";

@session_start();
$s_id = session_id();

include (__DIR__ . "/../../includes/connect.php");
include (__DIR__ . "/../../includes/online.php");
include (__DIR__ . "/../../includes/std_func.php");

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * Answer with a JSON error and stop the request.
 *
 * @param int    $http_code HTTP status to send.
 * @param string $fejl      Short reason.
 * @param bool   $login     True when the Connect@Cloud login is what failed; sent as X-Nets-Stage: login.
 *
 * @return void
 */
function lane3000_proxy_fejl($http_code, $fejl, $login = false) {
	if ($login) {
		header('X-Nets-Stage: login');
	}
	http_response_code($http_code);
	print json_encode(array('error' => $fejl));
	exit;
}

/**
 * POST a JSON body to Connect@Cloud.
 *
 * @param string      $url     Full URL.
 * @param array       $body    Request body.
 * @param string|null $token   Bearer token, or null for the login call.
 * @param int         $timeout Seconds to wait for the answer.
 *
 * @return array{
 *   0: int,          HTTP status, 0 when Nets did not answer.
 *   1: string|false, Response body, or false on a curl error.
 *   2: string,       Curl error text.
 * }
 */
function lane3000_proxy_post($url, $body, $token, $timeout) {
	$headers = array('Content-Type: application/json', 'Accept: application/json');
	if ($token !== null) {
		$headers[] = 'Authorization: bearer ' . $token;
	}
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$svar = curl_exec($ch);
	return array((int) curl_getinfo($ch, CURLINFO_HTTP_CODE), $svar, curl_error($ch));
}

if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'POST') {
	lane3000_proxy_fejl(405, 'POST required');
}

# Session-bound token printed into the payment pages, so a forged cross-site POST cannot start a payment
$csrf_token = ifset($_SESSION, 'csrf_token', '');
if ($csrf_token === '' || !hash_equals($csrf_token, (string) ifset($_SERVER, 'HTTP_X_CSRF_TOKEN', ''))) {
	lane3000_proxy_fejl(403, 'Invalid or expired form token');
}

# The terminal call waits until the card is done; release the session lock so the page's log calls
# are not held up, and do not let PHP's time limit end the wait before curl's own timeout
session_write_close();
set_time_limit(200);

$post   = json_decode(file_get_contents('php://input'), true);
$action = (string) ifset($post, 'action', '');
# lane3000.php takes the register from ?kasse= when opened from ordre.php, else from the POS cookie
$kasse  = (int) ifset($post, 'kasse', $_COOKIE['saldi_pos'] ?? 0);

# Same terminal lookup as the pages: the register's terminal ID from Pos valg
$qtxt = "SELECT box4 FROM grupper WHERE beskrivelse = 'Pos valg' AND kodenr = '2' and fiscal_year = '" . (int) $regnaar . "'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$terminal_id = trim(explode(chr(9), $r ? $r[0] : '')[$kasse - 1] ?? '');
# Pos valg is free text, so only require printable characters without spaces and at least one letter or digit
# (so "." or ".." cannot collapse the path); the value is URL-encoded below
if (!preg_match('/^(?=.*[A-Za-z0-9])[\x21-\x7E]{1,100}$/', $terminal_id)) {
	lane3000_proxy_fejl(400, 'Terminal ID ikke fundet');
}

$baseurl = 'https://connectcloud.aws.nets.eu/v1/';

if ($action == 'transaction') {
	$amount = (int) round((float) ifset($post, 'amount', 0));
	$type   = (string) ifset($post, 'transactionType', '');
	if ($amount <= 0) {
		lane3000_proxy_fejl(400, 'Expected a positive amount');
	}
	if ($type !== 'purchase' && $type !== 'returnOfGoods') {
		lane3000_proxy_fejl(400, 'Expected purchase or returnOfGoods');
	}
	$url     = $baseurl . 'terminal/' . rawurlencode($terminal_id) . '/transaction';
	$body    = array('transactionType' => $type, 'amount' => $amount);
	$timeout = 180;
} elseif ($action == 'reconciliation') {
	$url     = $baseurl . 'terminal/' . rawurlencode($terminal_id) . '/administration';
	$body    = array('action' => 'reconciliation');
	$timeout = 120;
} else {
	lane3000_proxy_fejl(400, 'Unknown action');
}

# The register's own Connect@Cloud login (settings var_grp move3500, per pos_id)
$username = get_settings_value('username', 'move3500', '', null, $kasse);
$password = get_settings_value('password', 'move3500', '', null, $kasse);
if ($username === '' || $password === '') {
	lane3000_proxy_fejl(400, 'Manglende brugernavn eller adgangskode i indstillinger', true);
}

list($http_code, $svar, $curl_fejl) = lane3000_proxy_post($baseurl . 'login', array('username' => $username, 'password' => $password), null, 30);
if ($svar === false || $http_code == 0) {
	lane3000_proxy_fejl(502, 'Nets did not answer: ' . $curl_fejl, true);
}
$login = json_decode($svar, true);
$token = is_array($login) ? (string) ifset($login, 'token', '') : '';
if ($http_code < 200 || $http_code > 299 || $token === '') {
	# Nets' own error text, never the body itself, in case it carries a token
	$fejl = is_array($login) && is_scalar(ifset($login, 'error')) ? (string) $login['error'] : 'Ingen token modtaget fra server';
	lane3000_proxy_fejl(($http_code >= 400) ? $http_code : 502, "HTTP $http_code: $fejl", true);
}

list($http_code, $svar, $curl_fejl) = lane3000_proxy_post($url, $body, $token, $timeout);
if ($svar === false || $http_code == 0) {
	lane3000_proxy_fejl(502, 'Nets did not answer: ' . $curl_fejl);
}

# Pass the terminal's status and body through, so the pages read them as they did before
http_response_code($http_code);
print $svar;
