<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/contraSuggestionLookup.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ SD-722 Created: the contra account suggested for a kreditor (contraSuggestion()), and the user's "Udfyld modkonto automatisk".
//                Read-only, JSON. The company and user come from the session.
//
// Request: kreditor=<kreditor account number>  kladde_id=<journal>
// Answer:  {"suggestion": {account, type, name, moms, text, count, reason, source} or null, "auto": bool}

ob_start();

session_start();
$s_id = session_id();
$title = 'contraSuggestionLookup';
$modulnr = 0;
$bg = 'nix';
$header = 'nix';
$webservice = true;

include(__DIR__ . '/../../includes/connect.php');
include(__DIR__ . '/../../includes/online.php');
include_once(__DIR__ . '/../../includes/std_func.php');
include_once(__DIR__ . '/contraSuggestion.php');

/**
 * Injected by connect.php and online.php, included above:
 * @var string $db
 * @var string $sqdb
 * @var int    $regnaar
 * @var int    $sprog_id
 * @var int    $bruger_id
 */

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (empty($regnaar) || !$db || $db == $sqdb) {
	echo json_encode(array('error' => 'Session expired'));
	exit;
}

$kreditor = trim((string)ifset($_GET, 'kreditor', ''));
$kladdeId = (int)ifset($_GET, 'kladde_id', 0);
echo json_encode(array(
	'suggestion' => ctype_digit($kreditor) ? contraSuggestion($kreditor, $kladdeId, $regnaar, $sprog_id) : null,
	'auto' => contraSuggestionUserAuto($bruger_id ?? null),
), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
