<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/stripe_invoice_autopay.php --- 2026.10.12
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261007 CL/LH Created: invoice-payment worker. Retries unposted card
//                payments and, when autopay_enabled is on, charges saved
//                cards for invoices due today or earlier. CLI only; run from
//                cron a few times a day, e.g.
//                  17 7,13 * * * php /var/www/saldi/tools/stripe_invoice_autopay.php
//                One run at a time (flock). Exit 0 ok, 1 declines, 2 setup error.

// 20261012 CL/LH Continue receipt, enrollment and notification recovery with new collection disabled.

if (php_sapi_name() !== 'cli') { header('HTTP/1.1 403 Forbidden'); print 'CLI only'; exit; }
chdir(__DIR__);

// Same depth-3 URI trick as tools/stripe_print_link.php (get_relative()).
if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '/install/tools/stripe_invoice_autopay.php';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = 'cli';

include(__DIR__ . '/../includes/db_query.php');
include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/stripeIncludes/stripeBootstrap.php');

if (!$stripe_boot_ok) {
	fwrite(STDERR, "ERROR: tenant unresolved or connection failed (SALDI_STRIPE_DB / .ht_stripe_db)\n");
	exit(2);
}
include_once(__DIR__ . '/../includes/stripeIncludes/invoicePaymentRuntime.php');

$lock = fopen(sys_get_temp_dir() . '/saldi_stripe_invoice_autopay_' . md5($stripe_target_db) . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
	print "En anden kørsel er i gang - springer over\n";
	exit(0);
}

try {
	$service = stripe_invoice_payment_service();
	$expired = $service->expireAmbiguousOperations();
	$recovered = $service->reconcileIntents();
	$enriched = $service->enrichCards();
	$settled = $service->settlePending();
	$res = $service->autopay(false);
	$service->flushNotifications();
} catch (Throwable $e) {
	stripe_log('autopay worker: ' . $e->getMessage());
	fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
	exit(2);
}
print date('Y-m-d H:i:s') . " afstemte betalinger: $recovered, til manuel afstemning: $expired, gemte kort: $enriched, bogføring forsøgt: $settled, trukket: {$res['charged']}, afvist: {$res['failed']}, sprunget over: {$res['skipped']}"
	. (stripe_setting('autopay_enabled', 'off') === 'on' ? '' : ' (automatisk betaling slået fra)') . "\n";
exit($res['failed'] > 0 ? 1 : 0);
