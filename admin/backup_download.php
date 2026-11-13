<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- admin/backup_download.php --- ver 5.1.1 --- 2026-11-13 ---
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
// Copyright (c) 2026-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261103 CL/ASR Created: hands this ledger's kept backup copy (temp/backup/<db>/, made by admin/backup.php) to a user with write on Sikkerhedskopi.
//                 The file is no longer fetched from a public URL under temp/.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
@session_start();
$s_id = session_id();
$header  = 'nix';
$bg      = 'nix';
$title   = "Sikkerhedskopi";
$modulnr = 11;
$permission_key   = 'settings.backup';
$permission_level = 'write';

include("../includes/connect.php");
include("../includes/online.php");

// Only a .sdat file in this ledger's own backup folder; the name is reduced to its last part, so no other path can be asked for.
$name = basename((string) (isset($_GET['f']) ? $_GET['f'] : ''));
$dir = realpath(__DIR__ . '/../temp/backup/' . basename((string) $db));
$file = ($dir !== false && preg_match('/^[A-Za-z0-9_.-]+\.sdat$/', $name)) ? realpath($dir . '/' . $name) : false;
if ($file === false || dirname($file) !== $dir || !is_file($file)) {
	http_response_code(404);
	print "404";
	exit;
}
while (ob_get_level() > 0) {
	ob_end_clean();
}
// The download keeps the old name (<db>_<date>-<time>.sdat); the random part only protects the stored copy.
$downloadName = preg_replace('/_[0-9a-f]{16}(\.sdat)$/', '$1', $name);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($file);
exit;
