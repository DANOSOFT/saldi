<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/signupIncludes/signupBootstrap.php --- ver 5.1.0 --- 2026-11-02 ---
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
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Shared start of every signup/*.php script: connects to the master database the way
// index/index.php does (no login session - these pages are public), loads the flow's library and
// makes sure its tables exist. The calling script must chdir(__DIR__) first, because
// includes/connect.php resolves "../includes" from the working directory (opret_build_tenant()
// anchors its own seed files).
//
// 20261014 CL/PS Created.
// 20261102 CL/ASR Includes includes/opretfunc.php instead of the removed copy.
//                 No DDL here any more: until includes/betweenUpdates.php has created the tables, every endpoint answers "not ready".
//                 base_url is required ("not ready" without it), and CLI runs get a three-level REQUEST_URI so the logs land in temp/<db>.
//                 Includes includes/cvrLookup.php for CVR numbers.

$css = '';
$title = 'signup';
$bg = "";
$webservice = "on"; // db_modify()/db_select() then flag a failed statement ($db_modify_fejl) instead of printing an alert and exiting
if (!isset($_SERVER['REQUEST_URI'])) {
	// CLI (cron: signup/rydop.php). get_relative() in db_query.php counts the slashes: three give '../', the
	// installation root, as for the web requests to /<install>/signup/x.php, so its logs go to temp/<db>.
	$_SERVER['REQUEST_URI'] = '/saldi/signup/cli.php';
	$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
include("../includes/connect.php");
include("../includes/std_func.php");
include(__DIR__ . "/../opretfunc.php"); // opret_build_tenant(): the tenant database build
include(__DIR__ . "/../cvrLookup.php"); // cvrLookupCompany(): CVR numbers at Datafordeleren
include(__DIR__ . "/signupFunc.php");
include(__DIR__ . "/signupMail.php");
include(__DIR__ . "/signupPage.php");

/** @var string $sqdb from includes/connect.php */
$db = $sqdb; // writes go to the master database (and its temp/<db> log)
date_default_timezone_set('Europe/Copenhagen');
// The tables are created by includes/betweenUpdates.php at the next login after a deploy. Until then the
// flow answers "not ready" instead of creating them here (no DDL outside the update chain).
// base_url is required: the activation links in the mails are built from it and must never come from the
// request's Host header (see signup_base_url()).
$signupNotReady = !signup_schema_ready() ? 'signup tables are missing; includes/betweenUpdates.php creates them at the next login'
	: (signup_base_url() === '' ? 'the signup setting base_url is not set (e.g. https://ssl3.saldi.dk/finans)' : '');
if ($signupNotReady !== '') {
	signup_log($signupNotReady);
	if (PHP_SAPI === 'cli') {
		fwrite(STDERR, "signup: $signupNotReady\n");
		exit(1);
	}
	$notReady = 'Oprettelsen er ikke klar lige nu. Prøv igen om lidt, eller ring på ' . signup_setting('support_phone', '46 90 22 08') . '.';
	signup_cors(); // answers the website's preflight, so its real request gets the JSON below
	if (signup_wants_json()) {
		signup_json(array('ok' => false, 'errors' => array('_' => $notReady)), 503);
	}
	signup_page('Oprettelsen er ikke klar', '<p>' . signup_h($notReady) . '</p>', '', 503);
}
