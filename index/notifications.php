<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/notifications.php --- lap 5.0.0 --- 2026.09.27 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260927 Sawaneh JSON endpoint for the shell's notification bell (topbar spec §3):
//                  GET = refresh sources (throttled per session) + list + unread count;
//                  POST action=read (id or all) marks as read.

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var int    $bruger_id
 * @var string $rettigheder
 * @var int    $sprog_id
 * @var string $db_encode
 */

@session_start();
$s_id = session_id();
ob_start();

$title = 'notifications';
$css = '';
$permission_key = 'any';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/notifications.php");

$brugerId = (int) $bruger_id;
$out = array('items' => array(), 'unread' => 0);

if ($brugerId !== 0 && notif_ready()) {
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
		$id = isset($_POST['id']) && $_POST['id'] !== 'all' ? (int) $_POST['id'] : null;
		notif_mark_read($brugerId, $id);
	} else {
		// Sources are re-evaluated at most every 5 minutes per session.
		$slot = 'notif_refreshed_' . $brugerId;
		if (!isset($_SESSION[$slot]) || (time() - (int) $_SESSION[$slot]) > 300) {
			notif_refresh($brugerId, (string) $rettigheder, (int) $sprog_id);
			$_SESSION[$slot] = time();
		}
	}
	$toUtf8 = function (string $s) use ($db_encode): string {
		return ($db_encode === 'UTF8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
	};
	foreach (notif_list($brugerId) as $n) {
		$n['title'] = $toUtf8($n['title']);
		$n['body'] = $toUtf8($n['body']);
		$n['ago'] = $toUtf8(notif_relative($n['created'], (int) $sprog_id));
		$out['items'][] = $n;
		if ($n['unread']) {
			$out['unread']++;
		}
	}
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
print json_encode($out);
exit;
