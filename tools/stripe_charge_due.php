<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/stripe_charge_due.php --- 2026.10.26
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
// 20261019 CL/ASR Created (S3, Requirements_invoice_email_payment_page_EN.md par. 8.2/8.3):
//                 charges every unpaid invoice with Udskriv til = Stripe on its due date
//                 (G9.6 charge_on = due | invoice_date) on the debtor's card on file,
//                 off-session, one PaymentIntent per attempt. Retries: day 0, +3, +7 (max 3).
//                 After a declined charge a backup card (Stripe PM metadata saldi_role=backup)
//                 is tried once before the customer is mailed. Paid is set by the webhook /
//                 stripe_payment_sync, never here. Never reads or writes ordrer.nextfakt.
//
//   php tools/stripe_charge_due.php [--db=<ledger db> | --all] [--dry-run] [--today=YYYY-MM-DD] [--repair-only]
//   cron: 0 6 * * *  cd <docroot> && php tools/stripe_charge_due.php --all >> temp/stripe_charge.log 2>&1
// 20261022 CDX/LH Exit without collecting when invoice payments or enrolment are disabled.
// 20261022 CDX/LH Repair captured payments before collecting; --repair-only never creates a PaymentIntent.
// 20261022 CDX/LH Skip invoices outside the shared tenant/debtor/currency eligibility guard.
// 20261022 CDX/LH Lock the entire job; share durable decline/authentication handling; report errors with non-zero exit.
// 20261022 CDX/LH Use the tested tenant job for repair-only, summary counts and exit status.
// 20261026 CL/NTR --all runs the job for every open ledger with Stripe switched on and set up in that ledger (not a
//                 restored copy), one child process per ledger so each gets a clean connection, settings cache and job
//                 lock. Exit 1 if any ledger fails.

if (php_sapi_name() !== 'cli') { header('HTTP/1.1 403 Forbidden'); print 'CLI only'; exit; }
date_default_timezone_set('Europe/Copenhagen');
chdir(__DIR__);
if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '/cli/tools/stripe_charge_due.php';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = 'cli';
$options = getopt('', array('db::', 'all', 'dry-run', 'today::', 'repair-only'));
$dryRun = isset($options['dry-run']);
$today = (isset($options['today']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$options['today'])) ? (string)$options['today'] : date('Y-m-d');

include(__DIR__ . '/../includes/db_query.php');
include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/std_func.php');
include_once(__DIR__ . '/../includes/stripeIncludes/stripeSettings.php');

if (isset($options['all'])) {
	if (isset($options['db'])) { fwrite(STDERR, "use either --db or --all\n"); exit(1); }
	$webservice = true;
	$ledgers = array();
	$q = db_select("select db from regnskab where coalesce(lukket, '') <> 'on' and db is not null and db <> '' order by id", __FILE__ . " linje " . __LINE__, true);
	while ($r = db_fetch_array($q)) {
		if (preg_match('/^[a-z0-9_]{1,40}$/', (string)$r['db'])) $ledgers[] = (string)$r['db'];
	}
	$failed = 0;
	$pinned = stripeConfigDb();
	foreach ($ledgers as $ledger) {
		// Only ledgers that switched Stripe on and set it up themselves (stripe_ledger_armed(): a ledger restored
		// from a copy keeps the original's keys). Most ledgers have no Stripe tables at all.
		$connection = db_connect($sqhost, $squser, $sqpass, $ledger);
		if (!$connection) { fwrite(STDERR, "$ledger: no connection\n"); $failed++; continue; }
		$db = $ledger;
		$set = array();
		$q = db_select("select var_name, var_value from settings where var_grp = 'stripe' and var_name in ('enabled', 'target_db')", __FILE__ . " linje " . __LINE__);
		while ($q && $r = db_fetch_array($q)) $set[$r['var_name']] = (string)$r['var_value'];
		db_close($connection);
		if (($set['enabled'] ?? '') !== 'on' || (($set['target_db'] ?? '') !== $ledger && $ledger !== $pinned)) continue;
		$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --db=' . escapeshellarg($ledger)
			. ($dryRun ? ' --dry-run' : '') . ' --today=' . escapeshellarg($today) . (isset($options['repair-only']) ? ' --repair-only' : '');
		passthru($cmd, $code);
		if ($code !== 0) $failed++;
	}
	exit($failed ? 1 : 0);
}

// Without --db: the pinned ledger. Every ledger with its own Stripe setup can run it (--db, or all of them with --all).
$targetDb = isset($options['db']) && $options['db'] !== false ? (string)$options['db'] : stripeConfigDb();
if (!$targetDb || !preg_match('/^[a-z0-9_]{1,40}$/', $targetDb)) { fwrite(STDERR, "no ledger (pin .ht_stripe_db or pass --db)\n"); exit(1); }
include_once(__DIR__ . '/../includes/stripeIncludes/stripeCharge.php');
try {
	$jobLock = stripe_charge_job_lock($targetDb);
	if ($jobLock === false) { print "$targetDb: stripe_charge summary skipped=overlap errors=0\n"; exit(0); }
} catch (Throwable $e) {
	fwrite(STDERR, "Stripe lock failed: " . $e->getMessage() . "\n");
	exit(1);
}
$GLOBALS['stripe_charge_today'] = $today;
$r = db_fetch_array(db_select("select id, db from regnskab where db = '" . db_escape_string($targetDb) . "'", __FILE__ . " linje " . __LINE__, true));
if (!$r) { fwrite(STDERR, "unknown ledger $targetDb\n"); exit(1); }
$connection = db_connect($sqhost, $squser, $sqpass, $r['db']);
if (!$connection) { fwrite(STDERR, "no connection to $targetDb\n"); exit(1); }
$db = $r['db']; $db_id = (int)$r['id'];
$webservice = true;
$bruger_id = 0; $brugernavn = 'stripe_charge_due';
stripe_settings_flush();

include_once(__DIR__ . '/../includes/stripeIncludes/stripeInvoice.php');
include_once(__DIR__ . '/../includes/stripeIncludes/stripeEnrol.php');
if (file_exists(__DIR__ . '/../includes/auditLog.php')) include_once(__DIR__ . '/../includes/auditLog.php');

// Repair and pending Afmeld delivery run under the same whole-job lock, even with switches off.
try {
	$result = stripe_charge_job($today, $dryRun, isset($options['repair-only']));
	print date('c') . " $db: stripe_charge summary " . json_encode($result['stats']) . ($dryRun ? ' dry_run=true' : '') . "\n";
	exit($result['exit_code']);
} catch (Throwable $e) {
	fwrite(STDERR, "$targetDb: stripe_charge summary errors=1 error=" . str_replace(array("\r", "\n"), ' ', $e->getMessage()) . "\n");
	exit(1);
}
