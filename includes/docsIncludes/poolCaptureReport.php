<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolCaptureReport.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ SD-722 Created: JSON endpoint for javascript/poolCapture.js.
//                Actions (POST): info (captured and current values of a document, and whether this user reported it), report ("Send"),
//                deliver (e-mails the report just stored, so the user never waits for the mail), reject ("×" on a suggestion).
//                A document is a pool file (poolFile) or a file attached to a journal line (filename + sourceId).
//                The company and the user come from the session, never from the request (SST-776).

@session_start();
$s_id = session_id();

$bg = 'nix';
$header = 'nix';
$modulnr = 0;
$webservice = true; // online.php answers an expired session with a return instead of its html box
ob_start(); // online.php writes html, which must not end up in the JSON answer
include(__DIR__ . '/../connect.php');
include(__DIR__ . '/../online.php');
include_once(__DIR__ . '/../std_func.php');
include_once(__DIR__ . '/poolCapture.php');
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

/**
 * Sends the answer and ends the request.
 *
 * @param array<string, mixed> $data
 * @param int $status HTTP status.
 * @return void
 */
function poolCaptureAnswer(array $data, $status = 200) {
	http_response_code($status);
	print json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
}

if (empty($brugernavn) || empty($db) || $db == $sqdb) poolCaptureAnswer(array('error' => 'not authenticated'), 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') poolCaptureAnswer(array('error' => 'POST only'), 405);
if (!poolCaptureReady()) poolCaptureAnswer(array('error' => 'not ready'), 409);

$action = (string)if_isset($_POST, '', 'action');
$userId = isset($bruger_id) && is_numeric($bruger_id) && (int)$bruger_id !== 0 ? (int)$bruger_id : null;
$userName = (string)$brugernavn;

/**
 * The document the request is about, with its snapshot and the values it has now.
 *
 * @return array{filename: string, sourceId: int, hash: string, snapshot: array|null, current: array, docPath: string}|null
 */
function poolCaptureDocument() {
	$poolFile = basename((string)if_isset($_POST, '', 'poolFile'));
	$filename = basename((string)if_isset($_POST, '', 'filename'));
	$sourceId = (int)if_isset($_POST, 0, 'sourceId');
	if ($poolFile !== '') {
		$row = db_fetch_array(db_select("SELECT file_date, amount, invoice_number, currency, vendor_konto_id, content_sha256 FROM pool_files WHERE filename = '" . db_escape_string($poolFile) . "'", __FILE__ . " linje " . __LINE__));
		if (!$row) return null;
		$kreditor = '';
		if ((int)$row['vendor_konto_id']) {
			$k = db_fetch_array(db_select("SELECT kontonr FROM adresser WHERE id = '" . (int)$row['vendor_konto_id'] . "' AND art = 'K'", __FILE__ . " linje " . __LINE__));
			$kreditor = $k ? (string)$k['kontonr'] : '';
		}
		$docPath = poolCaptureDocPath($poolFile);
		$hash = trim((string)$row['content_sha256']);
		if ($hash === '' && $docPath !== '') $hash = (string)hash_file('sha256', $docPath);
		return array(
			'filename' => $poolFile,
			'sourceId' => 0,
			'hash' => $hash,
			'snapshot' => poolCaptureForFile($poolFile),
			'current' => array('date' => $row['file_date'], 'amount' => $row['amount'], 'invoiceNumber' => $row['invoice_number'], 'currency' => $row['currency'], 'kreditor' => $kreditor),
			'docPath' => $docPath,
		);
	}
	if ($filename === '' || !$sourceId) return null;
	// Only a file that really is attached to that line of this company
	if (!db_fetch_array(db_select("SELECT id FROM documents WHERE source = 'kassekladde' AND source_id = '$sourceId' AND filename = '" . db_escape_string($filename) . "'", __FILE__ . " linje " . __LINE__))) return null;
	$kept = poolCaptureAttachedSnapshot($filename, $sourceId);
	$current = poolCaptureFinalValues($sourceId, $filename);
	$docPath = poolCaptureDocPath($filename, $sourceId);
	$hash = $kept ? $kept['content_hash'] : '';
	if ($hash === '' && $docPath !== '') $hash = (string)hash_file('sha256', $docPath);
	return array(
		'filename' => $filename,
		'sourceId' => $sourceId,
		'hash' => $hash,
		'snapshot' => $kept ? $kept['snapshot'] : null,
		'current' => $current ?: array(),
		'docPath' => $docPath,
	);
}

/** A kreditor number with its name, for the dialog and the e-mail. */
function poolCaptureKreditorLabel($kontonr) {
	$kontonr = trim((string)$kontonr);
	if ($kontonr === '') return '';
	$r = db_fetch_array(db_select("SELECT firmanavn FROM adresser WHERE art = 'K' AND kontonr = '" . db_escape_string($kontonr) . "' ORDER BY id LIMIT 1", __FILE__ . " linje " . __LINE__));
	return $r ? $kontonr . ' ' . trim((string)$r['firmanavn']) : $kontonr;
}

/**
 * Per field: raw from the service, after Saldi's normalisation (what the pool showed) and the value now.
 * The values the user has in the form win over the stored ones for "now".
 *
 * @return array<int, array{field: string, raw: string, norm: string, final: string, changed: bool}>
 */
function poolCaptureRows(array $doc, array $typed) {
	$rows = array();
	foreach (poolCaptureFields() as $field) {
		$now = array_key_exists($field, $typed) && trim((string)$typed[$field]) !== '' ? $typed[$field] : ($doc['current'][$field] ?? '');
		$norm = (string)($doc['snapshot']['fields'][$field]['norm'] ?? '');
		$final = poolCaptureNorm($field, $now);
		$changed = !poolCaptureSame($field, $norm, $final);
		// An empty currency on the line is the company's own: shown as what was read when that is the same
		if (!$changed) $final = $norm;
		if ($field === 'kreditor') {
			$norm = poolCaptureKreditorLabel($norm);
			$final = poolCaptureKreditorLabel($final);
		} else {
			$norm = poolCaptureDisplay($field, $norm);
			$final = poolCaptureDisplay($field, $final);
		}
		$rows[] = array(
			'field' => $field,
			'raw' => (string)($doc['snapshot']['fields'][$field]['raw'] ?? ''),
			'norm' => $norm,
			'final' => $final,
			'changed' => $changed,
		);
	}
	return $rows;
}

$settings = poolCaptureReportSettings();

if ($action === 'reject') {
	$field = (string)if_isset($_POST, '', 'field');
	if (!in_array($field, array('debet', 'kreditor'), true)) poolCaptureAnswer(array('error' => 'field'), 422);
	poolCaptureReject(basename((string)if_isset($_POST, '', 'poolFile')), $field, (string)if_isset($_POST, '', 'value'), (string)if_isset($_POST, '', 'reason'), $userId, $userName);
	poolCaptureAnswer(array('ok' => true));
}

if ($action === 'deliver') {
	// Sending may take seconds (SMTP); nothing below writes the session
	session_write_close();
	$id = (int)if_isset($_POST, 0, 'id');
	poolCaptureAnswer(poolCaptureReportDeliver(1, $id ?: null));
}

if (!$settings['enabled']) poolCaptureAnswer(array('error' => 'off'), 403);
$doc = poolCaptureDocument();
if ($doc === null) poolCaptureAnswer(array('error' => 'not found'), 404);
$typed = is_array($_POST['current'] ?? null) ? $_POST['current'] : array();

if ($action === 'info') {
	poolCaptureAnswer(array(
		'captured' => $doc['snapshot'] !== null,
		'reported' => poolCaptureReported($doc['filename'], $doc['hash'], $userId, $userName),
		'rows' => poolCaptureRows($doc, $typed),
	));
}

if ($action === 'report') {
	if (poolCaptureReported($doc['filename'], $doc['hash'], $userId, $userName)) poolCaptureAnswer(array('ok' => true, 'already' => true));
	$rows = poolCaptureRows($doc, $typed);
	$kreditor = '';
	foreach ($rows as $row) {
		if ($row['field'] === 'kreditor') $kreditor = $row['final'] !== '' ? $row['final'] : $row['raw'];
	}
	poolCaptureReportCreate(array(
		'filename' => $doc['filename'],
		'hash' => $doc['hash'],
		'sourceId' => $doc['sourceId'],
		'extractionId' => (string)($doc['snapshot']['extractionId'] ?? ''),
		'kreditor' => $kreditor,
		'kreditorCvr' => (string)($doc['snapshot']['vendorCvr'] ?? ''),
		'fields' => $rows,
		'comment' => trim((string)if_isset($_POST, '', 'comment')),
		'userId' => $userId,
		'userName' => $userName,
		'docPath' => $doc['docPath'],
	));
	$id = db_fetch_array(db_select("SELECT MAX(id) AS id FROM pool_capture_log WHERE kind = 'report' AND content_hash = '" . db_escape_string($doc['hash']) . "'", __FILE__ . " linje " . __LINE__));
	poolCaptureAnswer(array('ok' => true, 'id' => $id ? (int)$id['id'] : 0));
}

poolCaptureAnswer(array('error' => 'action'), 400);
