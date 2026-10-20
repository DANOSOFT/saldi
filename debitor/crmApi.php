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
$permission_level = (is_array($crmPeek) && in_array($crmPeek['action'] ?? '', array('search', 'place'), true)) ? 'read' : 'write';

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

transaktion('begin');
$result = crm_action((string) $in['action'], $in);
if (!empty($result['error'])) {
	transaktion('rollback');
	crm_api_answer(array('ok' => false, 'error' => $result['error']), 400);
}
transaktion('commit');
crm_api_answer(array('ok' => true, 'result' => $result, 'data' => crm_dataset()));
