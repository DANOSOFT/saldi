<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/fejlrapport.php --- ver 5.1.1 --- 2026.11.13 ---
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
// 20261104 CL/ASR Created: the JSON endpoint behind Send fejlrapport in the error box (includes/errorReport.php).
//                 A POST with the session's CSRF token, a reference stored in this session and not reported yet, and an optional message of at most 2000 characters sends the stored details to Saldi support with PHP mail().
//                 The recipient is $errorReportTo from includes/connect.php when set, else support@danosoft.dk; at most 5 reports per session and 20 per ledger per hour.
//                 A field sent as an array (message[]) is refused with 400 before anything is sent.
//                 Every answer is JSON, also when the session has expired or a query fails here.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
@session_start();
$s_id = session_id();
$saldi_error_json = true; // a failing query here answers JSON (includes/errorReport.php), never the box or the old alert
$fejlrapportDone = false;
ob_start();
include_once(__DIR__ . '/../includes/errorReport.php');

/** Answer and stop (not from the shutdown function, where an exit would skip the others). Anything printed before (online.php's page head) is dropped. */
function fejlrapport_answer(int $status, array $out, bool $stop = true): void
{
	global $fejlrapportDone;
	$fejlrapportDone = true;
	while (ob_get_level() > 0 && @ob_end_clean()) {
	}
	if (!headers_sent()) {
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
	}
	$out['succes'] = $out['success'] = !empty($out['ok']);
	print json_encode($out, JSON_INVALID_UTF8_SUBSTITUTE);
	if ($stop) {
		exit;
	}
}

// Something that ended the request early (an expired session in online.php, a refusal, a database error) still
// answers JSON: a JSON answer already printed is kept, anything else becomes {"ok":false,"error":"server"}.
register_shutdown_function(function () {
	global $fejlrapportDone;
	if ($fejlrapportDone || ob_get_level() === 0) {
		return;
	}
	$printed = (string) ob_get_contents();
	$json = json_decode(trim($printed), true);
	if (is_array($json) && array_key_exists('ok', $json)) {
		return;
	}
	fejlrapport_answer(500, array('ok' => false, 'error' => 'server'), false);
});

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
	fejlrapport_answer(405, array('ok' => false, 'error' => 'method'));
}
foreach (array('ref', 'message', 'csrf_token') as $field) {
	if (isset($_POST[$field]) && !is_string($_POST[$field])) {
		fejlrapport_answer(400, array('ok' => false, 'error' => 'bad_input')); // e.g. message[]=..., which would become the text "Array"
	}
}
$sent = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : (isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '');
if ($sent === '' || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $sent)) {
	fejlrapport_answer(403, array('ok' => false, 'error' => 'csrf'));
}
$ref = isset($_POST['ref']) ? (string) $_POST['ref'] : '';
$reports = (isset($_SESSION['saldi_error_reports']) && is_array($_SESSION['saldi_error_reports'])) ? $_SESSION['saldi_error_reports'] : array();
if (!preg_match('/^F-\d{8}-[A-Z0-9]{6}$/', $ref) || !isset($reports[$ref]) || (int) ($reports[$ref]['time'] ?? 0) < time() - SALDI_ERROR_TTL) {
	fejlrapport_answer(404, array('ok' => false, 'error' => 'unknown_ref'));
}
if (!empty($reports[$ref]['reported'])) {
	fejlrapport_answer(409, array('ok' => false, 'error' => 'already', 'ref' => $ref));
}
$message = isset($_POST['message']) ? (string) $_POST['message'] : '';
if (!mb_check_encoding($message, 'UTF-8')) {
	fejlrapport_answer(400, array('ok' => false, 'error' => 'encoding', 'ref' => $ref));
}
$message = trim(str_replace(array("\r\n", "\r"), "\n", $message));
if (mb_strlen($message, 'UTF-8') > 2000) {
	fejlrapport_answer(400, array('ok' => false, 'error' => 'too_long', 'ref' => $ref));
}
$recent = array();
foreach ((isset($_SESSION['saldi_error_sent']) && is_array($_SESSION['saldi_error_sent'])) ? $_SESSION['saldi_error_sent'] : array() as $t) {
	if ((int) $t > time() - 3600) {
		$recent[] = (int) $t;
	}
}
if (count($recent) >= 5) {
	fejlrapport_answer(429, array('ok' => false, 'error' => 'rate', 'ref' => $ref));
}
// At most 20 per ledger per hour as well, which a new login does not reset (temp/<db>/.ht_fejlrapport.txt, one time per line).
$ledgerDb = (string) ($reports[$ref]['db'] ?? '');
$ledgerFh = (preg_match('/^[A-Za-z0-9_]+$/', $ledgerDb) && is_dir(__DIR__ . '/../temp/' . $ledgerDb))
	? @fopen(__DIR__ . '/../temp/' . $ledgerDb . '/.ht_fejlrapport.txt', 'c+') : false;
if ($ledgerFh) {
	flock($ledgerFh, LOCK_EX);
	$ledgerRecent = array();
	while (($line = fgets($ledgerFh)) !== false) {
		if ((int) $line > time() - 3600) {
			$ledgerRecent[] = (int) $line;
		}
	}
	$ledgerFull = count($ledgerRecent) >= 20;
	if (!$ledgerFull) {
		$ledgerRecent[] = time();
		ftruncate($ledgerFh, 0);
		rewind($ledgerFh);
		fwrite($ledgerFh, implode("\n", $ledgerRecent) . "\n");
	}
	flock($ledgerFh, LOCK_UN);
	fclose($ledgerFh);
	if ($ledgerFull) {
		fejlrapport_answer(429, array('ok' => false, 'error' => 'rate', 'ref' => $ref));
	}
}

// The installation ($errorReportTo) and, while the login is still valid, the user's ledger: Reply-To and the audit log.
include(__DIR__ . '/../includes/connect.php');
$details = $reports[$ref];
$sameLedger = false;
$online = db_fetch_array(db_select("select db from online where session_id = '" . db_escape_string($s_id) . "' limit 1", __FILE__ . " linje " . __LINE__, true));
if ($online && trim((string) $online['db']) !== '' && trim((string) $online['db']) === (string) $details['db']) {
	$title = 'fejlrapport';
	$header = 'nix';
	$bg = 'nix';
	$css = '';
	$permission_key = 'any';
	include(__DIR__ . '/../includes/online.php');
	$sameLedger = isset($db) && (string) $db === (string) $details['db'];
}
$replyTo = '';
if ($sameLedger) {
	if ((int) $details['bruger_id'] > 0) {
		$r = db_fetch_array(db_select("select email from brugere where id = '" . (int) $details['bruger_id'] . "'", __FILE__ . " linje " . __LINE__));
		$replyTo = $r ? trim((string) $r['email']) : '';
	}
	if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
		$r = db_fetch_array(db_select("select email from adresser where art = 'S'", __FILE__ . " linje " . __LINE__));
		$replyTo = $r ? trim((string) $r['email']) : '';
	}
	if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
		$replyTo = '';
	}
}
$details['reply_to'] = $replyTo;

$mail = saldi_error_report_compose($details, $message);
$recent[] = time();
$_SESSION['saldi_error_sent'] = $recent;
$ok = mail($mail['to'], $mail['subject'], $mail['body'], $mail['headers']);
if (!$ok) {
	error_log('fejlrapport: mail() failed for ' . $ref);
	fejlrapport_answer(502, array('ok' => false, 'error' => 'mail', 'ref' => $ref));
}
$_SESSION['saldi_error_reports'][$ref]['reported'] = time();
if ($sameLedger && function_exists('audit_log')) {
	audit_log('error.reported', $ref);
}
fejlrapport_answer(200, array('ok' => true, 'ref' => $ref));
