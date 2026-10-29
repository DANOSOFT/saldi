<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/crmApi.php --- lap 5.0.0 --- 2026.10.20 ---
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
// 20261020 ASR JSON actions for debitor/crm.php. POST with a JSON body {action, ...} and the page's CSRF token in
//              X-CSRF-Token. Answers {ok, result, data}; data is the whole dataset again (small for a CRM), so the
//              page never drifts from the database. 'search' only reads and answers {ok, result}.
// 20261020 ASR 'place' updates the CRM's entry in the navigation stack (Back from an order lands in the drawer).
// 20261029 ASR 'busy' (who already has a meeting then) and 'ical' (the user's own calendar feed link) need read access;
//              calendar invitations queued by an action are mailed after the commit (crm_flush_mail).

@session_start();
$s_id = session_id();
ob_start();

$title = 'crmApi';
$css = '';
$header = 'nix';
$modulnr = 6;
$permission_key = 'debitor.konti';
// Reading actions (search, place) only need read access; everything else writes.
$crmBody = (string) file_get_contents('php://input');
$crmPeek = json_decode($crmBody, true);
$crmReadActions = array('search', 'place', 'customer', 'data', 'busy', 'ical');
$permission_level = (is_array($crmPeek) && in_array($crmPeek['action'] ?? '', $crmReadActions, true)) ? 'read' : 'write';
// 20261024 ASR review R71: the central gate (includes/permissions.php) counts every POST as a write unless the page says
//              the POST only reads; without this a read-only user could not search or report the page's place.
$permission_post_read = ($permission_level === 'read');

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/crmFunctions.php");

// Server-side right for every writing action (the page only hides the controls).
if ($permission_level === 'write' && !crm_can_write()) {
	ob_end_clean();
	http_response_code(403);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array('ok' => false, 'error' => 'noaccess'));
	exit;
}

function crm_api_answer(array $body, int $code = 200): void
{
	ob_end_clean();
	http_response_code($code);
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	echo json_encode($body);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	crm_api_answer(array('ok' => false, 'error' => 'method'), 405);
}
$token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
	if (function_exists('audit_log')) {
		audit_log('csrf', 'crmApi.php');
	}
	crm_api_answer(array('ok' => false, 'error' => 'csrf'), 403);
}
$in = $crmPeek;
if (!is_array($in) || empty($in['action'])) {
	crm_api_answer(array('ok' => false, 'error' => 'input'), 400);
}

if ($in['action'] === 'place') {
	// The page's place (tab, open customer) goes into the session's navigation stack, so the shell's came-from chip
	// and Alt+L lead back to it from an order or the customer card.
	crm_api_answer(array('ok' => crm_nav_place((string) ($in['q'] ?? ''))));
}
if ($in['action'] === 'search') {
	crm_api_answer(array('ok' => true, 'result' => crm_search((string) ($in['q'] ?? ''))));
}
if ($in['action'] === 'data') {
	// the whole dataset again, read-only: the page refreshes itself when its tab is shown again (review R20)
	crm_api_answer(array('ok' => true, 'data' => crm_dataset()));
}
if ($in['action'] === 'busy') {
	// meetings of the chosen people that overlap the asked time, and the first free start that day
	$date = crm_date($in['date'] ?? '');
	$start = crm_time($in['start'] ?? '');
	if (!$date || !$start) {
		crm_api_answer(array('ok' => false, 'error' => 'fields'), 400);
	}
	$ids = array_values(array_filter(array_map('intval', (array) ($in['ids'] ?? array()))));
	crm_api_answer(array('ok' => true, 'result' => crm_busy($date, $start, max(5, min(720, (int) ($in['dur'] ?? 60))), $ids, (int) ($in['except'] ?? 0))));
}
if ($in['action'] === 'ical') {
	// the user's own feed link: 'new' makes one (an older one stops working), 'off' removes it
	$mode = in_array($in['mode'] ?? '', array('new', 'off'), true) ? $in['mode'] : '';
	crm_api_answer(array('ok' => true, 'result' => array('url' => $mode ? crm_ical_set($mode) : crm_ical_url())));
}
if ($in['action'] === 'customer') {
	// one customer with its history (a drawer on a customer outside the loaded set); read access is enough
	$one = crm_action('customer', $in);
	crm_api_answer(empty($one['error']) ? array('ok' => true, 'result' => $one) : array('ok' => false, 'error' => $one['error']), empty($one['error']) ? 200 : 404);
}

transaktion('begin');
$result = crm_action((string) $in['action'], $in);
if (!empty($result['error'])) {
	transaktion('rollback');
	crm_api_answer(array('ok' => false, 'error' => $result['error']), 400);
}
transaktion('commit');
// invitations go out only now that the change is saved
if (!empty($GLOBALS['crm_mail_queue'])) {
	$result['mail'] = crm_flush_mail();
}
crm_api_answer(array('ok' => true, 'result' => $result, 'data' => crm_dataset()));
