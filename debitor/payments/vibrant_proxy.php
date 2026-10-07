<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/payments/vibrant_proxy.php --- ver 5.0.0 --- 2026.10.07 ---
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
// 20261007 CL/SZ SST-843 Server-side proxy for the Vibrant POS API, so the API key stays on the server.
//                vibrant.php calls this page instead of pos.api.vibrant.app.

/**
 * Injected by ../../includes/online.php, included below:
 * @var string $db
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
function vibrant_proxy_fejl($http_code, $fejl) {
	http_response_code($http_code);
	print json_encode(array('error' => $fejl));
	exit;
}

if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'POST') {
	vibrant_proxy_fejl(405, 'POST required');
}

# Session-bound token printed into vibrant.php, so a forged cross-site POST cannot start a payment
$csrf_token = ifset($_SESSION, 'csrf_token', '');
if ($csrf_token === '' || !hash_equals($csrf_token, (string) ifset($_SERVER, 'HTTP_X_CSRF_TOKEN', ''))) {
	vibrant_proxy_fejl(403, 'Invalid or expired form token');
}

# Nothing below writes to the session; release the lock so the page's log and receipt calls are not held up
session_write_close();

$post   = json_decode(file_get_contents('php://input'), true);
$action = (string) ifset($post, 'action', '');
$kasse  = (int) ($_COOKIE['saldi_pos'] ?? 0);

$r = db_fetch_array(db_select("select var_value from settings where var_name = 'vibrant_auth'", __FILE__ . " linje " . __LINE__));
$apikey = $r ? $r[0] : '';
if ($apikey === '') {
	vibrant_proxy_fejl(500, 'No Vibrant API key in Diverse valg');
}

# Same terminal lookup as vibrant.php: the Vibrant terminal for this register, else the register's terminal ID
$r = db_fetch_array(db_select("SELECT var_value FROM settings WHERE pos_id=$kasse AND var_grp='vibrant_terms'", __FILE__ . " linje " . __LINE__));
$terminal_id = $r ? $r['var_value'] : '';
if (!$terminal_id) {
	$qtxt = "SELECT box4 FROM grupper WHERE beskrivelse = 'Pos valg' AND kodenr = '2' and fiscal_year = '" . (int) $regnaar . "'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$terminal_id = trim(explode(chr(9), $r ? $r[0] : '')[$kasse - 1] ?? '');
}

# Vibrant object IDs look like pi_xxxx / ch_xxxx; anything else is refused so the browser cannot steer the URL
$id_pattern = '/^[A-Za-z0-9_-]{1,100}$/';
$method = 'GET';
$body   = NULL;

if ($action == 'process_payment_intent' || $action == 'process_refund') {
	$amount   = (int) round((float) ifset($post, 'amount', 0));
	$ordre_id = (int) ifset($post, 'ordre_id', 0);
	if ($amount <= 0) {
		vibrant_proxy_fejl(400, 'Expected a positive amount');
	}
	if (!preg_match($id_pattern, $terminal_id)) {
		vibrant_proxy_fejl(400, 'No Vibrant terminal for this register');
	}
	$method = 'POST';
	$path   = 'terminals/' . rawurlencode($terminal_id) . '/' . $action;
	if ($action == 'process_payment_intent') {
		$body = array(
			'paymentIntent' => array(
				'amount'      => $amount,
				'description' => "Bon $ordre_id",
				'metadata'    => array('correlationId' => (string) $ordre_id)
			)
		);
	} else {
		# The refund points at the payment intent saved on the order when it was paid
		$r = db_fetch_array(db_select("SELECT betalings_id FROM ordrer WHERE id = $ordre_id", __FILE__ . " linje " . __LINE__));
		$body = array(
			'refund' => array(
				'amount'          => $amount,
				'paymentIntentId' => $r ? (string) $r['betalings_id'] : '',
				'description'     => "Refund Bon $ordre_id",
				'reason'          => 'requested_by_customer',
				'metadata'        => array('orderId' => (string) $ordre_id)
			)
		);
	}
} elseif ($action == 'payment_intent' || $action == 'charge' || $action == 'refund') {
	$id = (string) ifset($post, 'id', '');
	if (!preg_match($id_pattern, $id)) {
		vibrant_proxy_fejl(400, 'Expected a Vibrant ID');
	}
	$paths = array('payment_intent' => 'payment_intents', 'charge' => 'charges', 'refund' => 'refunds');
	$path  = $paths[$action] . '/' . $id;
} else {
	vibrant_proxy_fejl(400, 'Unknown action');
}

$headers = array('Accept: application/json', 'apikey: ' . $apikey);
$ch = curl_init('https://pos.api.vibrant.app/pos/v1/' . $path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
if ($method == 'POST') {
	$headers[] = 'Content-Type: application/json';
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$svar = curl_exec($ch);
$http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_fejl = curl_error($ch);

if ($svar === false || $http_code == 0) {
	vibrant_proxy_fejl(502, 'Vibrant did not answer: ' . $curl_fejl);
}

# Pass Vibrant's status and body through unchanged, so vibrant.php reads them as it did before
http_response_code($http_code);
print $svar;
