<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/helpSupport.php --- ver 5.1.0 --- 2026-11-07 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261107 CL/ASR Created: the JSON endpoint behind "Skriv til os" in the help panel.
//                 A POST with the session's CSRF token and a message of 1-2000 characters is mailed with PHP mail() to the recipient of includes/saldi_help_panel.php (SALDI_HELP_SUPPORT_TO or $helpSupportTo); without one it answers 503 not_configured and sends nothing.
//                 At most 5 messages per session per hour; a field sent as an array is refused with 400 before anything else; every answer is JSON, also when the session has expired or a query fails.

@session_start();
$s_id = session_id();
$saldi_error_json = true; // a failing query here answers JSON (includes/errorReport.php), never the box or the old alert
$helpSupportDone = false;
ob_start();
include_once(__DIR__ . '/../includes/errorReport.php');
require_once __DIR__ . '/../includes/saldi_help_panel.php';

/** Answer and stop (not from the shutdown function, where an exit would skip the others). Anything printed before is dropped. */
function help_support_answer(int $status, array $out, bool $stop = true): void
{
	global $helpSupportDone;
	$helpSupportDone = true;
	while (ob_get_level() > 0 && @ob_end_clean()) {
	}
	if (!headers_sent()) {
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
	}
	print json_encode($out, JSON_INVALID_UTF8_SUBSTITUTE);
	if ($stop) {
		exit;
	}
}

// Something that ended the request early (an expired session, a database error) still answers JSON: a JSON answer
// already printed is kept, anything else becomes {"ok":false,"error":"server"}.
register_shutdown_function(function () {
	global $helpSupportDone;
	if ($helpSupportDone || ob_get_level() === 0) {
		return;
	}
	$json = json_decode(trim((string) ob_get_contents()), true);
	if (is_array($json) && array_key_exists('ok', $json)) {
		return;
	}
	help_support_answer(500, array('ok' => false, 'error' => 'server'), false);
});

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
	help_support_answer(405, array('ok' => false, 'error' => 'method'));
}
foreach ($_POST as $value) {
	if (!is_string($value)) {
		help_support_answer(400, array('ok' => false, 'error' => 'bad_input')); // e.g. message[]=..., which would become the text "Array"
	}
}
$sent = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : (isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '');
if ($sent === '' || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $sent)) {
	help_support_answer(403, array('ok' => false, 'error' => 'csrf'));
}

// The installation ($helpSupportTo in connect.php) and the user's ledger (regnskab, user, Reply-To).
$title = 'helpSupport';
$header = 'nix';
$bg = 'nix';
$css = '';
$webservice = true;
$modulnr = 0;
$permission_key = 'any';
$permission_post_read = true;
$skipLogtime = true;
include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/online.php');
if (empty($db) || !isset($brugernavn)) {
	help_support_answer(401, array('ok' => false, 'error' => 'session'));
}

$to = saldi_help_support_to();
if ($to === '') {
	help_support_answer(503, array('ok' => false, 'error' => 'not_configured'));
}

$message = isset($_POST['message']) ? (string) $_POST['message'] : '';
if (!mb_check_encoding($message, 'UTF-8')) {
	help_support_answer(400, array('ok' => false, 'error' => 'encoding'));
}
$message = trim(str_replace(array("\r\n", "\r"), "\n", $message));
if ($message === '') {
	help_support_answer(400, array('ok' => false, 'error' => 'empty'));
}
if (mb_strlen($message, 'UTF-8') > 2000) {
	help_support_answer(400, array('ok' => false, 'error' => 'too_long'));
}
// The page address: a path from the site root, at most 500 characters; anything else is dropped.
$page = isset($_POST['page']) ? saldi_error_header_value((string) $_POST['page']) : '';
if ($page === '' || $page[0] !== '/' || mb_strlen($page, 'UTF-8') > 500) {
	$page = '';
}
$pageLabel = isset($_POST['page_label']) ? mb_substr(saldi_error_header_value((string) $_POST['page_label']), 0, 120, 'UTF-8') : '';

$recent = array();
foreach ((isset($_SESSION['help_support_sent']) && is_array($_SESSION['help_support_sent'])) ? $_SESSION['help_support_sent'] : array() as $time) {
	if ((int) $time > time() - 3600) {
		$recent[] = (int) $time;
	}
}
if (count($recent) >= 5) {
	help_support_answer(429, array('ok' => false, 'error' => 'rate'));
}

$userId = isset($bruger_id) ? (int) $bruger_id : 0;
$replyTo = '';
if ($userId > 0) {
	$r = db_fetch_array(db_select("select email from brugere where id = '" . $userId . "'", __FILE__ . ' linje ' . __LINE__));
	$replyTo = $r ? saldi_error_header_value($r['email']) : '';
	if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
		$replyTo = '';
	}
}
if (!isset($version)) {
	@include(__DIR__ . '/../includes/version.php');
}
$ledger = saldi_error_header_value(isset($regnskab) ? $regnskab : '');
$user = saldi_error_header_value(isset($brugernavn_raw) ? $brugernavn_raw : $brugernavn);
try {
	$now = new DateTime('now', new DateTimeZone('Europe/Copenhagen'));
} catch (Exception $e) {
	$now = new DateTime('now');
}

$subject = mb_substr(saldi_error_header_value('Saldi Hjælp: ' . $ledger . ' / ' . $user), 0, 200, 'UTF-8');
$subjectHeader = preg_match('/[^\x20-\x7E]/', $subject) ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n") : $subject;

$lines = array();
$lines[] = 'Besked fra hjælpen i Saldi ("Skriv til os")';
$lines[] = '';
$lines[] = 'Regnskab:       ' . $ledger;
$lines[] = 'Database:       ' . saldi_error_header_value($db);
$lines[] = 'Brugernavn:     ' . $user;
$lines[] = 'Bruger_id:      ' . $userId;
$lines[] = 'Svar til:       ' . ($replyTo !== '' ? $replyTo : '(ingen e-mail)');
$lines[] = 'Side:           ' . $pageLabel;
if ($page !== '') {
	$lines[] = 'Sidens adresse: ' . $page;
}
$lines[] = 'Saldi-version:  ' . saldi_error_header_value(isset($version) ? $version : '');
$lines[] = 'Tidspunkt:      ' . $now->format('Y-m-d H:i:s');
$lines[] = '';
$lines[] = 'Besked:';
$lines[] = saldi_error_utf8($message);
// No line over 998 bytes (SMTP): long lines are wrapped at 200 characters, as includes/errorReport.php does.
$out = array();
foreach (explode("\n", implode("\n", $lines)) as $line) {
	$line = rtrim($line);
	while (strlen($line) > 900) {
		$out[] = mb_substr($line, 0, 200, 'UTF-8');
		$line = mb_substr($line, 200, null, 'UTF-8');
	}
	$out[] = $line;
}
$body = implode("\n", $out) . "\n";

$headers = array(
	'From'                      => 'kan_ikke_besvares@saldi.dk',
	'MIME-Version'              => '1.0',
	'Content-Type'              => 'text/plain; charset=UTF-8',
	'Content-Transfer-Encoding' => '8bit',
	'X-Mailer'                  => 'Saldi',
);
if ($replyTo !== '') {
	$headers['Reply-To'] = $replyTo;
}

$recent[] = time();
$_SESSION['help_support_sent'] = $recent;
if (!mail($to, $subjectHeader, $body, $headers)) {
	error_log('helpSupport: mail() failed');
	help_support_answer(502, array('ok' => false, 'error' => 'mail'));
}
help_support_answer(200, array('ok' => true));
