<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/crm/ical.php --- lap 5.0.0 --- 2026.10.29 ---
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
// 20261029 ASR A user's CRM meetings as a calendar feed (Google, Outlook, Apple subscribe to the link). No login:
//              the link carries the company (r = regnskab.id in the master database) and the user's secret token
//              (k = <bruger_id>-<token>, crm_ical). The feed is read-only and stops working when the user makes a new
//              link, turns it off, loses the Debitor right or is no longer linked to an employee.

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function crm_ical_fail(int $code): void
{
	while (ob_get_level()) ob_end_clean();
	http_response_code($code);
	header('Content-Type: text/plain; charset=utf-8');
	print $code === 404 ? 'Not found' : 'Unavailable';
	exit;
}

$rid = (int) ($_GET['r'] ?? 0);
$key = (string) ($_GET['k'] ?? '');
if ($rid <= 0 || !preg_match('/^(\d+)-([0-9a-f]{48})$/', $key, $km)) {
	crm_ical_fail(404);
}
$webservice = true; // db_modify() returns instead of printing an error page
ob_start();
include(__DIR__ . "/../../includes/connect.php");
$r = db_fetch_array(db_select("select db, regnskab from regnskab where id = $rid and (lukket is null or lukket != 'on')", __FILE__ . " linje " . __LINE__));
if (!$r || !preg_match('/^[A-Za-z0-9_]+$/', (string) $r['db'])) {
	crm_ical_fail(404);
}
$db = (string) $r['db'];
$connection = db_connect($sqhost, $squser, $sqpass, $db);
if (!$connection) {
	crm_ical_fail(503);
}
include_once(__DIR__ . "/../../includes/std_func.php");
include_once(__DIR__ . "/../../includes/crmFunctions.php");
if (!tbl_exists('crm_ical') || !tbl_exists('crm_meetings')) {
	crm_ical_fail(404);
}
$brugerId = (int) $km[1];
$t = db_fetch_array(db_select("select token from crm_ical where bruger_id = $brugerId", __FILE__ . " linje " . __LINE__));
$u = db_fetch_array(db_select("select ansat_id, rettigheder, sprog_id from brugere where id = $brugerId", __FILE__ . " linje " . __LINE__));
if (!$t || !hash_equals((string) $t['token'], $km[2]) || !$u || (int) $u['ansat_id'] <= 0 || substr((string) $u['rettigheder'], 6, 1) < '1') {
	crm_ical_fail(404);
}
$sprog_id = (int) $u['sprog_id'] ?: 1;   // the texts in the user's language (crm_lang)
$firm = db_fetch_array(db_select("select firmanavn from adresser where art = 'S'", __FILE__ . " linje " . __LINE__));
$feed = crm_ical_feed((int) $u['ansat_id'], $firm ? crm_out((string) $firm['firmanavn']) : (string) $r['regnskab']);
while (ob_get_level()) ob_end_clean();
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="saldi-crm.ics"');
print $feed;
