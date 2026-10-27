<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/payments/flatpay_proxy.php --- ver 5.0.0 --- 2026.10.07 ---
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
// 20261007 CL/SZ SST-843 Server-side proxy for the Flatpay socket API, so the Flatpay ID (GUID) stays on the server.
//                flatpay.php calls this page instead of socket-api.flatpay.dk.

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
 *
 * @return void
 */
function flatpay_proxy_fejl($http_code, $fejl) {
	http_response_code($http_code);
	print json_encode(array('error' => $fejl));
	exit;
}

/**
 * Blank the GUID wherever it appears in a decoded Flatpay answer, so the answer
 * can go to the browser (and on into the saved receipt) without it.
 *
 * @param mixed  $value Decoded JSON value.
 * @param string $guid  The Flatpay ID.
 *
 * @return mixed The value with the GUID removed from every string.
 */
function flatpay_proxy_skjul_guid($value, $guid) {
	if (is_array($value)) {
		foreach ($value as $key => $item) {
			$value[$key] = flatpay_proxy_skjul_guid($item, $guid);
		}
		return $value;
	}
	return is_string($value) ? str_ireplace($guid, '', $value) : $value;
}

if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'POST') {
	flatpay_proxy_fejl(405, 'POST required');
}

# Session-bound token printed into flatpay.php, so a forged cross-site POST cannot start a payment
$csrf_token = ifset($_SESSION, 'csrf_token', '');
if ($csrf_token === '' || !hash_equals($csrf_token, (string) ifset($_SERVER, 'HTTP_X_CSRF_TOKEN', ''))) {
	flatpay_proxy_fejl(403, 'Invalid or expired form token');
}

# Nothing below writes to the session; release the lock so the page's receipt call is not held up
session_write_close();

$post   = json_decode(file_get_contents('php://input'), true);
$action = (string) ifset($post, 'action', '');
$kasse  = (int) ($_COOKIE['saldi_pos'] ?? 0);

$r = db_fetch_array(db_select("select var_value from settings where var_name = 'flatpay_auth'", __FILE__ . " linje " . __LINE__));
$guid = $r ? trim((string) $r[0]) : '';
if ($guid === '') {
	flatpay_proxy_fejl(500, 'No Flatpay ID in Diverse valg');
}

# Same terminal lookup as flatpay.php: the register's terminal ID from Pos valg
$qtxt = "SELECT box4 FROM grupper WHERE beskrivelse = 'Pos valg' AND kodenr = '2' and fiscal_year = '" . (int) $regnaar . "'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$terminal_id = trim(explode(chr(9), $r ? $r[0] : '')[$kasse - 1] ?? '');
# Pos valg is free text, so only require printable characters without spaces; the value is only json/query-encoded
if (!preg_match('/^[\x21-\x7E]{1,100}$/', $terminal_id)) {
	flatpay_proxy_fejl(400, 'No Flatpay terminal for this register');
}

# flatpay.php makes this reference with generateUUID(); it is the only value the browser picks
$reference = (string) ifset($post, 'transactionReference', '');
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $reference)) {
	flatpay_proxy_fejl(400, 'Expected a transaction reference');
}

$base   = 'https://socket-api.flatpay.dk/socket/transaction/';
$method = 'POST';
$body   = NULL;

if ($action == 'start') {
	$amount   = (int) round((float) ifset($post, 'amount', 0));
	$type     = (string) ifset($post, 'transactionType', '');
	$ordre_id = (int) ifset($post, 'ordre_id', 0);
	if ($amount <= 0) {
		flatpay_proxy_fejl(400, 'Expected a positive amount');
	}
	if ($type !== 'SALE' && $type !== 'REFUND') {
		flatpay_proxy_fejl(400, 'Expected SALE or REFUND');
	}
	# Same rule as flatpay.php: 0, NULL or no row means the terminal must not print
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'flatpay_terminal_print'", __FILE__ . " linje " . __LINE__));
	$terminal_print = $r ? $r[0] : NULL;
	$url  = $base . 'start';
	$body = array(
		'terminalId'            => $terminal_id,
		'transactionType'       => $type,
		'amount'                => (string) $amount,
		'guid'                  => $guid,
		'disableTerminalPrints' => ($terminal_print == 0),
		'language'              => 'da_DK',
		'reference'             => (string) $ordre_id,
		'externalReference'     => (string) $ordre_id,
		'transactionReference'  => $reference
	);
} elseif ($action == 'response') {
	$method = 'GET';
	$url = $base . 'response?' . http_build_query(array(
		'transactionReference' => $reference,
		'guid'                 => $guid,
		'terminalId'           => $terminal_id
	), '', '&', PHP_QUERY_RFC3986);
} elseif ($action == 'cancel') {
	$url  = $base . 'cancel';
	$body = array(
		'transactionReference' => $reference,
		'terminalId'           => $terminal_id,
		'guid'                 => $guid
	);
} else {
	flatpay_proxy_fejl(400, 'Unknown action');
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
if ($method == 'POST') {
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
}
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	'Content-Type: application/json',
	'Accept: application/json',
	'Authorization: Basic ' . base64_encode($guid)
));
$svar = curl_exec($ch);
$http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_fejl = curl_error($ch);

if ($svar === false || $http_code == 0) {
	flatpay_proxy_fejl(502, 'Flatpay did not answer: ' . $curl_fejl);
}

# Pass Flatpay's status and body through, so flatpay.php reads them as it did before.
# Only an answer that echoes the GUID is decoded and rewritten without it.
http_response_code($http_code);
if (stripos($svar, $guid) === false) {
	print $svar;
} else {
	$data = json_decode($svar, true);
	if (is_array($data)) {
		print json_encode(flatpay_proxy_skjul_guid($data, $guid), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
	} else {
		print str_ireplace($guid, '', $svar);
	}
}
