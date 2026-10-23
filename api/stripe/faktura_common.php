<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/faktura_common.php --- 2026.10.19
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20261019 CL/ASR Created: shared bootstrap for the public invoice & payment page
//                 (faktura.php, faktura_pay.php, faktura_state.php, faktura_pdf.php).
//                 Same hardening as subscribe.php: no db parameter, pinned ledger via
//                 stripeConfigDb(), signed link (o + s), rate limits per IP and per invoice.
//                 Sets $inv (stripe_invoice_load) and $faktura_o / $faktura_s, or ends the
//                 request with a status page / JSON error.

ob_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if (isset($_GET['db']) || isset($_POST['db']) || isset($_REQUEST['db'])) { http_response_code(400); ob_end_clean(); print 'Bad request'; exit; }

include(__DIR__ . "/../../includes/connect.php");
include(__DIR__ . "/../../includes/stripeIncludes/stripeBootstrap.php");
include(__DIR__ . "/../../includes/stripeIncludes/stripePage.php");
include(__DIR__ . "/../../includes/stripeIncludes/stripeRateLimit.php");
include(__DIR__ . "/../../includes/stripeIncludes/stripeAlertMail.php");
include(__DIR__ . "/../../includes/stripeIncludes/stripeInvoice.php");
include_once(__DIR__ . "/../../includes/stripeIncludes/stripeEnrol.php");
if (file_exists(__DIR__ . "/../../includes/auditLog.php")) include_once(__DIR__ . "/../../includes/auditLog.php");

$faktura_json = !empty($FAKTURA_JSON);

function faktura_fail($status, $headline, $text, $code = 'error') {
	global $faktura_json;
	if ($faktura_json) {
		while (ob_get_level()) ob_end_clean();
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		print json_encode(array('ok' => false, 'error' => $code, 'message' => $text));
		exit;
	}
	stripe_status_page($status, 'warn', $headline, array($text), '', '');
}

if (!$stripe_boot_ok) faktura_fail(503, 'Midlertidigt utilgængelig', 'Prøv igen om lidt.', 'unavailable');
if (stripe_setting('enabled', 'off') !== 'on') faktura_fail(503, stripe_txt('not_active'), stripe_txt('not_active_txt'), 'disabled');

$faktura_o = (int)(isset($_GET['o']) ? $_GET['o'] : (isset($_POST['o']) ? $_POST['o'] : 0));
$faktura_s = (string)(isset($_GET['s']) ? $_GET['s'] : (isset($_POST['s']) ? $_POST['s'] : ''));
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0';
if (!stripe_rate_limit('fak_ip_' . $ip, 300, 600)) faktura_fail(429, 'For mange forsøg', 'Vent et par minutter og prøv igen.', 'rate');
if ($faktura_o < 1 || !stripe_link_verify($faktura_o, $faktura_s)) faktura_fail(403, stripe_txt('link_invalid'), stripe_txt('link_invalid_txt'), 'bad_link');
if (!stripe_rate_limit('fak_ord_' . $faktura_o, 120, 600)) faktura_fail(429, 'For mange forsøg', 'Vent et par minutter og prøv igen.', 'rate');

$inv = stripe_invoice_load($faktura_o);
if (!$inv) faktura_fail(404, stripe_txt('link_invalid'), stripe_txt('link_invalid_txt'), 'not_found');
