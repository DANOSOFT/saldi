<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/signup_api.php --- ver 5.0.0 --- 2026-10-12 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261012 CL/NTR Dedicated server-to-server sign-up endpoint: creates a regnskab
//                 for a public sign-up without reopening the pre-SD-615 anonymous
//                 bypass on admin/opret.php. The trusted caller authenticates with
//                 an HMAC signature over the posted fields (shared key in master
//                 settings: signup_api_key / var_grp=signup); the end user is NOT
//                 and does not become a super-admin of this system. Creation is
//                 delegated to create_regnskab() in includes/opretfunc.php (shared
//                 with admin/opret.php).
//
//                 STATUS: not yet wired to a caller. The current sign-up pages
//                 (opret/opretMini.php, opret_basis.php, opret_proff.php) run on
//                 this same host and create accounts by calling create_regnskab()
//                 directly, so they do not use this endpoint. It is kept for a
//                 future OFF-HOST sign-up - most likely an email-driven activation
//                 flow where a separate server finalises the account over HTTPS and
//                 signs its request with signup_api_key. Until such a caller exists
//                 no signup_api_key need be configured (without one every request
//                 is refused, which is the safe default). See the plan/notes before
//                 pointing a remote caller at it.
// ----------------------------------------------------------------------

date_default_timezone_set('Europe/Copenhagen');

include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/std_func.php");
include(__DIR__ . "/../includes/opretfunc.php");

header('Content-Type: application/json');

// @var string $sqdb,$sqhost,$squser,$sqpass  master DB coordinates (includes/connect.php)
// @var resource $connection                   master connection (includes/connect.php)

$webservice  = 'on';   // so a failed db_modify() returns instead of exit(), letting create_regnskab() roll back (SD-595)
$db          = $sqdb;  // operate against the master DB; this endpoint has no user session
$db_skriv_id = NULL;

/**
 * Emit a JSON response and stop.
 * @param int   $code    HTTP status code
 * @param array $payload response body
 * @return void
 */
function signup_respond($code, $payload) {
	http_response_code($code);
	echo json_encode($payload);
	exit;
}

/**
 * Append one line to the sign-up abuse/audit log. Never logs the signature or key.
 * @param string $msg
 * @return void
 */
function signup_log($msg) {
	$dir = __DIR__ . '/../temp/signup';
	if (!file_exists($dir)) @mkdir($dir, 0750, true);
	$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?';
	@file_put_contents($dir . '/signup_api.log', date('Y-m-d H:i:s') . " $ip $msg\n", FILE_APPEND);
}

/**
 * Canonical HMAC-SHA256 over the request fields. Both this endpoint and the
 * mini sign-up server must build the signature identically: every posted field
 * except `sig`, sorted by key, joined as "key=value" lines.
 * @param array  $fields
 * @param string $key
 * @return string hex signature
 */
function signup_sign($fields, $key) {
	ksort($fields);
	$parts = array();
	foreach ($fields as $k => $v) {
		$parts[] = $k . '=' . $v;
	}
	return hash_hmac('sha256', implode("\n", $parts), $key);
}

// --- transport: require HTTPS for public callers (honor the reverse-proxy header) ---
// The credential on the wire is an HMAC signature, not the shared key itself, so
// plaintext interception can't leak the key - HTTPS here is defence in depth. We
// enforce it for public-internet clients (the remote mini sign-up server) but
// exempt private/loopback callers (internal server-to-server, the test stack).
$https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
	|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$publicClient = filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
if ($publicClient && !$https) {
	signup_log('rejected: not https');
	signup_respond(400, array('ok' => false, 'error' => 'HTTPS required'));
}

// --- method ---
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
	signup_respond(405, array('ok' => false, 'error' => 'method not allowed'));
}

// --- shared secret from the master settings table ---
$r = db_fetch_array(db_select("select var_value from settings where var_name='signup_api_key' and var_grp='signup'", __FILE__ . " linje " . __LINE__));
$secret = trim(isset($r['var_value']) ? $r['var_value'] : '');
if ($secret === '') {
	// A missing/blank key must never authenticate.
	signup_log('rejected: signup_api_key not configured');
	signup_respond(403, array('ok' => false, 'error' => 'forbidden'));
}

// --- verify the HMAC signature over every posted field except sig ---
$sig = (string) (isset($_POST['sig']) ? $_POST['sig'] : '');
$toSign = $_POST;
unset($toSign['sig']);
$expected = signup_sign($toSign, $secret);
if ($sig === '' || !hash_equals($expected, $sig)) {
	signup_log('rejected: bad signature regnskab=' . (isset($_POST['regnskab']) ? $_POST['regnskab'] : ''));
	signup_respond(403, array('ok' => false, 'error' => 'forbidden'));
}

// --- replay window: ts must be present and within 5 minutes ---
$ts = (string) (isset($_POST['ts']) ? $_POST['ts'] : '');
if (!ctype_digit($ts) || abs(time() - (int) $ts) > 300) {
	signup_log('rejected: stale or missing ts');
	signup_respond(403, array('ok' => false, 'error' => 'request expired'));
}

// --- validation (mirrors admin/opret.php) ---
$regnskab     = db_escape_string(trim((string) (isset($_POST['regnskab']) ? $_POST['regnskab'] : '')));
$brugernavn   = db_escape_string(trim((string) (isset($_POST['brugernavn']) ? $_POST['brugernavn'] : '')));
$passwd       = db_escape_string(trim((string) (isset($_POST['passwd']) ? $_POST['passwd'] : '')));
$passwd2      = db_escape_string(trim((string) (isset($_POST['passwd2']) ? $_POST['passwd2'] : '')));
$email        = db_escape_string(trim((string) (isset($_POST['email']) ? $_POST['email'] : '')));
$posteringer  = (int) (isset($_POST['posteringer']) ? $_POST['posteringer'] : 0);
$brugerantal  = (int) (isset($_POST['brugerantal']) ? $_POST['brugerantal'] : 0);
$std_kto_plan = isset($_POST['std_kto_plan']) ? $_POST['std_kto_plan'] : NULL;

if (is_input_too_long(trim((string) (isset($_POST['regnskab']) ? $_POST['regnskab'] : '')), 60)) {
	signup_respond(422, array('ok' => false, 'error' => 'Regnskabsnavnet må højst være 60 tegn'));
}
if (is_input_too_long(trim((string) (isset($_POST['brugernavn']) ? $_POST['brugernavn'] : '')))) {
	signup_respond(422, array('ok' => false, 'error' => 'Brugernavnet må højst være 80 tegn'));
}
if (!$regnskab || !$brugernavn || !$passwd) {
	signup_respond(422, array('ok' => false, 'error' => 'Alle felter SKAL udfyldes'));
}
if ($passwd !== $passwd2) {
	signup_respond(422, array('ok' => false, 'error' => 'Adgangskoder er ikke ens'));
}

// --- uniqueness ---
if (db_fetch_array(db_select("select id from regnskab where regnskab='$regnskab'", __FILE__ . " linje " . __LINE__))) {
	signup_respond(409, array('ok' => false, 'error' => 'regnskab findes allerede'));
}

// --- optional own-company fields ---
$companyFields = array();
if (!empty($_POST['fra_formular'])) {
	foreach (array('kontakt', 'firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'tlf', 'cvrnr') as $k) {
		$companyFields[$k] = db_escape_string(trim((string) (isset($_POST[$k]) ? $_POST[$k] : '')));
	}
	$companyFields['email'] = $email;
}

// --- create (buffer any stray output so the JSON body stays clean) ---
ob_start();
$res = create_regnskab($regnskab, $brugernavn, $passwd, $email, $posteringer, $brugerantal, $std_kto_plan, $companyFields);
ob_end_clean();

if (!empty($res['ok'])) {
	signup_log('created db_id=' . $res['db_id'] . ' regnskab=' . $regnskab);
	signup_respond(200, array('ok' => true, 'db_id' => $res['db_id'], 'db' => $res['db'], 'regnskab' => $regnskab));
}

signup_log('create failed regnskab=' . $regnskab . ' error=' . (isset($res['error']) ? $res['error'] : '?'));
if (isset($res['error']) && $res['error'] === 'exists') {
	signup_respond(409, array('ok' => false, 'error' => 'regnskab findes allerede'));
}
signup_respond(500, array('ok' => false, 'error' => 'account creation failed'));
