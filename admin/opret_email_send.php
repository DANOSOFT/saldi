<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- admin/opret_email_send.php --- patch 5.0.0 --- 2026.08.04 ---
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
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// Server-to-server endpoint that sends the welcome email from the template in
// admin/opret_email.php. Called by the opret.php files on ssl3 (mini/opret and
// finans/opret) instead of each of them composing its own hardcoded mail.
//
// Expected request (POST, application/x-www-form-urlencoded):
//   key    The key shown on admin/opret_email.php (required)
//   pakke  Package code, e.g. 'finans' - identifies which opret.php ran
//   email  The customer's email address - the recipient
//   navn   Customer/company name (optional)
//   cvrnr  Company registration number (optional)
//   tlf    Phone number (optional)
//   sprog  Language id for the template: 1 Danish (default), 2 English,
//          3 Norwegian. Falls back to Danish when that language is not written yet.
//
// Always answers with JSON: {"success":true,"pakke":"...","emne":"..."} or
// {"success":false,"error":"..."} together with a 4xx/5xx status code.
//
// 20260804 Sawaneh New file.
// 20260812 Sawaneh Review: the log masks the recipient address and strips control
//                  characters from request values, so it holds no full customer
//                  address and cannot be used to forge extra log lines.

// No session: the call comes from another server, not from a logged-in user.
// Authentication rests entirely on the shared key checked below.
while (ob_get_level()) {
	ob_end_clean();
}
header('Content-Type: application/json; charset=UTF-8');

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/std_func.php");
include(__DIR__ . "/../includes/opretEmailFunc.php");

/**
 * Emits the JSON response and stops.
 *
 * @param int    $status  HTTP status code.
 * @param array  $payload Response body.
 * @return void
 */
function opret_email_svar($status, $payload)
{
	http_response_code($status);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE);
	exit;
}

/**
 * Writes one audit line so a leaked key or a misconfigured opret.php is visible.
 *
 * Callers must pass request-supplied values through opret_email_log_value(), and
 * email addresses through opret_email_maskeret_email(): the log is a flat file
 * with no retention rule, so it holds neither raw customer addresses nor
 * anything that could break the one-entry-per-line format.
 *
 * @param string $message  What happened. Already sanitised by the caller.
 * @return void
 */
function opret_email_log($message)
{
	error_log('opret_email_send: ' . opret_email_log_value($message)
		. ' (ip ' . opret_email_log_value(if_isset($_SERVER, '?', 'REMOTE_ADDR')) . ')');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	opret_email_svar(405, array('success' => false, 'error' => 'Kun POST er tilladt.'));
}

$key   = trim((string) if_isset($_POST, '', 'key'));
$pakke = trim((string) if_isset($_POST, '', 'pakke'));
$email = trim((string) if_isset($_POST, '', 'email'));
$sprog = (int) if_isset($_POST, 1, 'sprog');

$forventet_key = opret_email_api_key(false);
if ($forventet_key === '') {
	opret_email_log('no key has been created yet');
	opret_email_svar(503, array('success' => false, 'error' => 'Velkomstmailen er ikke sat op endnu.'));
}
if ($key === '' || !hash_equals($forventet_key, $key)) {
	opret_email_log('rejected key for package "' . opret_email_log_value($pakke) . '"');
	opret_email_svar(403, array('success' => false, 'error' => 'Adgang nægtet.'));
}

if ($pakke === '' || $email === '') {
	opret_email_svar(400, array('success' => false, 'error' => 'Både pakke og email skal angives.'));
}

$resultat = opret_email_send($email, $pakke, array(
	'navn'  => if_isset($_POST, '', 'navn'),
	'cvrnr' => if_isset($_POST, '', 'cvrnr'),
	'tlf'   => if_isset($_POST, '', 'tlf'),
	'email' => $email,
), $sprog);

if (!$resultat['success']) {
	opret_email_log('could not send to "' . opret_email_maskeret_email($email) . '" for package "'
		. opret_email_log_value($pakke) . '": ' . $resultat['error']);

	$status = ($resultat['error_code'] === 'mail_failed') ? 502 : 400;

	opret_email_svar($status, array(
		'success'    => false,
		'error_code' => $resultat['error_code'],
		'error'      => $resultat['error'],
	));
}

opret_email_log('sent to "' . opret_email_maskeret_email($email) . '" for package "'
	. opret_email_log_value($pakke) . '"');
opret_email_svar(200, array(
	'success' => true,
	'pakke'   => $resultat['pakke'],
	'emne'    => $resultat['emne'],
));
?>
