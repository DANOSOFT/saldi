<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/docFile.php --- ver 5.1.0 --- 2026-11-10 ---
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
// 20261003 CL/SZ SD-723 Created: serves a document to a logged-in user of the tenant it belongs to.
//                The tenant comes from the session, never from the request, and the path must stay inside that tenant's folder.
// 20261005 CL/SZ SD-723 A document that isn't there gets a page with "Bilaget findes ikke på serveren" (still 404) instead of the plain "Not found".
// 20261110 CDX/ASR Declare the document viewer module permissions for enforced read access.
// 20261110 CDX/ASR Enforce the owning module for both requested and resolved document paths.
// 20261110 CDX/ASR Authorize cached XML against its session-bound original before serving any bytes.
//
// Request: k=doc  f=<path below the tenant's document folder>, e.g. f=pulje/x.pdf
//          k=temp f=<XML preview rendered by the viewer>, e.g. f=xml_view_<md5>.html
// Build the URL with docFileUrl() in docFileFunc.php rather than by hand.

session_start();
$s_id = session_id();

$header = 'nix';
$bg = 'nix';
$title = 'docFile';
include_once(__DIR__ . '/docFileFunc.php');
$kind = is_string($_GET['k'] ?? 'doc') ? ($_GET['k'] ?? 'doc') : '';
$requested = is_string($_GET['f'] ?? '') ? ($_GET['f'] ?? '') : '';
// Temp ownership is session-bound and can only be resolved after online.php authenticates the tenant.
$permission_key = $kind === 'temp' ? 'any' : docFilePermissionKey($kind, $requested);

// online.php ends the request with its "session expired" box when there is no login
include(__DIR__ . '/../connect.php');
include(__DIR__ . '/../online.php');
include_once(__DIR__ . '/../std_func.php');

/**
 * Injected by ../connect.php and ../online.php, included above:
 * @var string $db
 * @var string $sqdb
 */

while (ob_get_level()) {
	ob_end_clean();
}

/**
 * Ends the request with a plain-text error status.
 *
 * @param int    $status HTTP status code.
 * @param string $text   Short reason, not shown to the user beyond the frame.
 * @return never
 */
function docFileFail($status, $text) {
	http_response_code($status);
	header('Content-Type: text/plain; charset=utf-8');
	header('X-Content-Type-Options: nosniff');
	echo $text;
	exit;
}

if (!$db || $db == $sqdb || !preg_match('/^[A-Za-z0-9_]+$/', $db)) {
	docFileFail(403, 'No company open');
}

$appRoot = dirname(__DIR__, 2);
if (file_exists("$appRoot/owncloud")) {
	$docRoot = "$appRoot/owncloud";
} elseif (file_exists("$appRoot/bilag")) {
	$docRoot = "$appRoot/bilag";
} elseif (file_exists("$appRoot/documents")) {
	$docRoot = "$appRoot/documents";
} else {
	$docRoot = "$appRoot/bilag";
}

if ($kind === 'temp') {
	$preview = docFilePreviewTarget($db, "$docRoot/$db", "$appRoot/temp/$db", $requested);
	if ($preview === null) {
		docFileFail(403, 'Document access denied');
	}
	perm_enforce_request($preview['key'], 'read', true, null);
	$file = $preview['path'];
} elseif ($kind === 'doc') {
	$file = docFileResolve("$docRoot/$db", $requested, array('pdf', 'jpg', 'jpeg', 'png', 'gif', 'xml'));
	if ($file) {
		$target = docFileReadTarget("$docRoot/$db", $file);
		if ($target === null) {
			docFileFail(403, 'Document access denied');
		}
		perm_enforce_request($target['key'], 'read', true, null);
	}
} else {
	docFileFail(403, 'Document access denied');
}
if (!$file) {
	// Shown inside the viewer's frame, so a page with the reason rather than a bare status text
	http_response_code(404);
	header('Content-Type: text/html; charset=utf-8');
	header('X-Content-Type-Options: nosniff');
	echo "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body style='margin:0;background-color:#ffffff'>";
	echo docFileMissingBox(basename($requested), $sprog_id);
	echo "</body></html>";
	exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file));
header('Content-Type: ' . docFileContentType($ext));
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-cache');
if ($ext == 'html' || $ext == 'xml') {
	// Rendered invoice HTML and raw XML come from outside; never let them run script in our origin
	header("Content-Security-Policy: sandbox");
}
readfile($file);
exit;
