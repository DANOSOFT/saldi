<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/poolFolderSync.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261003 CL/SZ SD-718 Created: the pool folder sync, called by the pool page in the background once the page is shown, so the page never waits on the folder.
//                Runs docPool.php's poolFolderSync() (full sync at most every 10 minutes, otherwise only when the folder's mtime changed).
//                Answers {"changed": true} when documents were added or removed, and the page then fetches the list again.
// 20261003 CL/SZ SD-722 After the answer is sent, e-mails the "Rapportér fejl i aflæsning" reports that are due (a failed send is retried here).

ob_start(); // nothing from the includes may end up in the JSON answer

@session_start();
$s_id = session_id();
$header = 'nix';
$bg = 'nix';
$title = 'poolFolderSync';

include_once(__DIR__ . "/connect.php");
include_once(__DIR__ . "/std_func.php");

// The company comes from the session's online row, as in _docPoolData.php
$r = db_fetch_array(db_select("select db from online where session_id = '" . db_escape_string($s_id) . "' order by logtime desc limit 1", __FILE__ . " line " . __LINE__));
$db = $r['db'] ?? '';
// The folder can be slow (owncloud); the session isn't written below, so the user's other requests don't wait for it
session_write_close();

if (!$db) {
	while (ob_get_level()) ob_end_clean();
	http_response_code(403);
	header('Content-Type: application/json');
	print json_encode(array('error' => 'not authenticated'));
	exit;
}
$connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " line " . __LINE__);

// The same documents root as includes/documents.php
if (file_exists('../owncloud')) $docFolder = '../owncloud';
elseif (file_exists('../bilag')) $docFolder = '../bilag';
elseif (file_exists('../documents')) $docFolder = '../documents';
else $docFolder = '../bilag';

include_once(__DIR__ . "/docsIncludes/docPool.php");
$changed = poolFolderSync($docFolder, $db);

while (ob_get_level()) ob_end_clean();
$answer = json_encode(array('changed' => $changed));
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
// The page has its answer before any mail is sent: the length tells the browser the answer is complete.
// Not with PHP's own compression on, where the length sent would not be the length of the body.
if (!ini_get('zlib.output_compression')) {
	header('Content-Length: ' . strlen($answer));
	header('Connection: close');
}
print $answer;
flush();
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

// SD-722: reports to support that are due, e.g. after the mail server was down
ignore_user_abort(true);
try {
	include_once(__DIR__ . "/docsIncludes/poolCapture.php");
	ob_start();
	poolCaptureReportDeliver(3);
	ob_end_clean();
} catch (Throwable $e) {
	error_log("poolFolderSync report delivery failed: " . $e->getMessage());
}
?>
