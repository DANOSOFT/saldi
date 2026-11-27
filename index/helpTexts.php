<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/helpTexts.php --- ver 5.1.0 --- 2026-11-07 ---
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
// 20261107 CL/ASR Created: the JSON endpoint that serves the help texts to the help panel.
//                 GET ?group=kl|kk answers {ok, group, lang, texts} with every help.<group>.* and help.common.* key of the keyed TSV files, resolved for the user's language (doc/hjaelp_saldi_ai.md §3.4).
//                 It reads nothing but group and never accepts key names, so it cannot self-heal arbitrary keys into the tenant's tekster table.
//                 Without a session it answers 401 {"ok":false,"error":"session"}.
// 20261107 CL/ASR Review: the session is closed before the answer is written, so the next page request does not wait for this one.
// 20261107 CL/ASR Review round 2: $header, $bg and $css as index/helpSupport.php, so includes/online.php builds no page head for a JSON answer.

ob_start();
@session_start();
$s_id = session_id();
$title = 'helpTexts';
$header = 'nix'; // no page head, breadcrumb or <body> from includes/online.php: the answer is JSON (as index/helpSupport.php)
$bg = 'nix';
$css = '';
$webservice = true;
$modulnr = 0;
$permission_key = 'any';
$permission_post_read = true;
$skipLogtime = true; // fetching help texts is no page activity (includes/online.php)

include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/online.php');
include(__DIR__ . '/../includes/std_func.php');
include_once(__DIR__ . '/../includes/stdFunc/findTxtUtf8.php');
include_once(__DIR__ . '/../includes/saldi_help_texts.php');

/** Print one JSON answer and stop. Anything printed before it (a notice, a page head) is dropped. */
function help_texts_answer(int $status, array $body): void
{
	// The texts are in the session cache now; release the session lock before the answer goes out.
	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}
	while (ob_get_level() > 0 && @ob_end_clean()) {
	}
	$json = json_encode($body, JSON_UNESCAPED_UNICODE);
	if ($json === false) {
		$status = 500;
		$json = '{"ok":false,"error":"encode"}';
	}
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	print $json;
	exit;
}

// online.php returns early for a webservice without a session; the shell then shows the generic panel (§2.4).
if (empty($brugernavn) || empty($db) || (isset($sqdb) && $db === $sqdb)) {
	help_texts_answer(401, array('ok' => false, 'error' => 'session'));
}
if ((isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET') !== 'GET') {
	header('Allow: GET');
	help_texts_answer(405, array('ok' => false, 'error' => 'method'));
}
$group = isset($_GET['group']) ? $_GET['group'] : '';
if (!is_string($group)) {
	help_texts_answer(400, array('ok' => false, 'error' => 'group'));
}
$sprogId = isset($sprog_id) ? (int) $sprog_id : 1;
$texts = saldi_help_texts($group, $sprogId);
if (!$texts) {
	help_texts_answer(400, array('ok' => false, 'error' => 'group'));
}
$languages = array(1 => 'da', 2 => 'en', 3 => 'no');
help_texts_answer(200, array(
	'ok' => true,
	'group' => $group,
	'lang' => isset($languages[$sprogId]) ? $languages[$sprogId] : 'da',
	'texts' => $texts,
));
