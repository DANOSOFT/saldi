<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- index/releaseNoticeSeen.php --- ver 5.1.1 --- 2026-11-24 ---
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
// 20261124 CL/PS SST-1040 Created. Called by the release notice popup (includes/releaseNoticeFunc.php) on OK or
//                when the page is left; stores that the logged-in user has seen the current release marker and
//                marks the release's bell notification as read. Responds with JSON only.
/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var int $bruger_id
 * @var int $sprog_id
 */

// online.php prints SALDI's HTML head; buffer and discard it so the response is only JSON.
ob_start();
@session_start();
$s_id = session_id();
// online.php reads these; set them so it does not raise undefined-variable warnings.
$title = 'releaseNoticeSeen';
$css = '';
$modulnr = 0;
$permission_key = 'any';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/notifications.php");
include_once(__DIR__ . "/../includes/releaseNoticeFunc.php");
ob_end_clean();

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(array('error' => 'method_not_allowed'));
	exit;
}

// release_notice_config() whitelists the scope ('main' or 'sager').
$scope = ifset($_GET, 'scope', 'main');
if (!release_notice_mark_seen(if_isset($bruger_id, 0), $scope)) {
	http_response_code(400);
	echo json_encode(array('error' => 'not_stored'));
	exit;
}

echo json_encode(array('ok' => true));
