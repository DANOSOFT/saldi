<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/stripe_charge_due.php --- 2026.10.19
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
// 20261019 CL/ASR Created (S3, Requirements_invoice_email_payment_page_EN.md par. 8.2/8.3):
//                 charges every unpaid invoice with Udskriv til = Stripe on its due date
//                 (G9.6 charge_on = due | invoice_date) on the debtor's card on file,
//                 off-session, one PaymentIntent per attempt. Retries: day 0, +3, +7 (max 3).
//                 After a declined charge a backup card (Stripe PM metadata saldi_role=backup)
//                 is tried once before the customer is mailed. Paid is set by the webhook /
//                 stripe_payment_sync, never here. Never reads or writes ordrer.nextfakt.
//
//   php tools/stripe_charge_due.php [--db=<ledger db>] [--dry-run] [--today=YYYY-MM-DD]
//   cron: 0 6 * * *  cd <docroot> && php tools/stripe_charge_due.php >> temp/stripe_charge.log 2>&1

if (php_sapi_name() !== 'cli') { header('HTTP/1.1 403 Forbidden'); print 'CLI only'; exit; }
chdir(__DIR__);
if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '/tools/stripe_charge_due.php';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = 'cli';
$options = getopt('', array('db::', 'dry-run', 'today::'));
$dryRun = isset($options['dry-run']);
$today = (isset($options['today']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$options['today'])) ? (string)$options['today'] : date('Y-m-d');

include(__DIR__ . '/../includes/db_query.php');
include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/std_func.php');
include_once(__DIR__ . '/../includes/stripeIncludes/stripeSettings.php');

// v1 runs for the pinned billing ledger; --db lets another ledger with its own Stripe setup run it.
$targetDb = isset($options['db']) && $options['db'] !== false ? (string)$options['db'] : stripeConfigDb();
if (!$targetDb || !preg_match('/^[a-z0-9_]{1,40}$/', $targetDb)) { fwrite(STDERR, "no ledger (pin .ht_stripe_db or pass --db)\n"); exit(1); }
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

if (stripe_setting('enabled', 'off') !== 'on') { print date('c') . " $db: stripe disabled, nothing to do\n"; exit(0); }
stripe_payments_ensure();
$chargeOn = stripe_setting('charge_on', 'due') === 'invoice_date' ? 'invoice_date' : 'due';
$retryAfter = array(1 => 3, 2 => 4);          // attempt 1 -> +3 days -> attempt 2 -> +4 days (= day 7) -> attempt 3

$stats = array('due' => 0, 'charged' => 0, 'succeeded' => 0, 'failed' => 0, 'backup' => 0, 'skipped' => 0);
$q = db_select("select o.id from ordrer o join adresser a on a.id = o.konto_id
	where o.art = 'DO' and o.udskriv_til = 'Stripe' and coalesce(o.betalt,'') <> 'on'
	  and coalesce(o.fakturanr,'') <> '' and o.fakturanr <> '0' and a.stripe = 'on' and coalesce(a.stripe_pm_id,'') <> ''
	order by o.fakturadate, o.id", __FILE__ . " linje " . __LINE__);
$ids = array();
while ($row = db_fetch_array($q)) $ids[] = (int)$row['id'];

foreach ($ids as $ordreId) {
	$inv = stripe_invoice_load($ordreId);
	if (!$inv) continue;
	$when = ($chargeOn === 'invoice_date') ? substr((string)$inv['o']['fakturadate'], 0, 10) : $inv['due'];
	if ($when === '' || $when > $today) continue;
	$stats['due']++;
	if (!stripe_invoice_payable($inv['state']) || $inv['pending']) { $stats['skipped']++; continue; }
	// retry schedule
	$failed = array_values(array_filter($inv['payments'], function ($p) { return $p['status'] === 'failed' && in_array($p['source'], array('auto', 'backup')); }));
	$attempts = count(array_filter($failed, function ($p) { return $p['source'] === 'auto'; }));
	if ($attempts >= 3) { $stats['skipped']++; continue; }
	if ($attempts > 0) {
		$last = substr((string)$failed[0]['created_at'], 0, 10);
		$wait = isset($retryAfter[$attempts]) ? $retryAfter[$attempts] : 99;
		if (date('Y-m-d', strtotime($last) + $wait * 86400) > $today) { $stats['skipped']++; continue; }
	}
	$pm = (string)$inv['debtor']['stripe_pm_id'];
	print date('c') . " $db: charge invoice {$inv['nr']} (ordre $ordreId) " . stripe_fmt_kr($inv['total_ore']) . " kr, attempt " . ($attempts + 1) . ($dryRun ? ' [dry-run]' : '') . "\n";
	if ($dryRun) continue;
	$stats['charged']++;
	$res = stripe_payment_create($inv, 'auto', false, $pm);
	if ($res['ok']) {
		$sync = stripe_payment_sync($res['pi']);
		if ($sync === 'applied' || $sync === 'duplicate') { $stats['succeeded']++; continue; }
		print "   -> status " . $res['status'] . " / $sync (webhook finishes)\n";
		continue;
	}
	$stats['failed']++;
	print "   -> declined: " . ($res['error_code'] ?? $res['error']) . "\n";
	// backup card, once per invoice
	$usedBackup = count(array_filter($inv['payments'], function ($p) { return $p['source'] === 'backup'; })) > 0;
	$cus = (string)$inv['debtor']['stripe_customer_id'];
	if (!$usedBackup && $cus !== '') {
		$list = stripeHttpRequest('GET', '/v1/customers/' . rawurlencode($cus) . '/payment_methods', array('type' => 'card', 'limit' => 10));
		$backup = '';
		if ($list['ok']) foreach ($list['body']['data'] as $b) if (($b['metadata']['saldi_role'] ?? '') === 'backup' && $b['id'] !== $pm) { $backup = $b['id']; break; }
		if ($backup !== '') {
			$inv2 = stripe_invoice_load($ordreId);
			$res2 = stripe_payment_create($inv2, 'backup', false, $backup);
			if ($res2['ok']) { stripe_payment_sync($res2['pi']); $stats['backup']++; print "   -> paid with backup card\n"; }
			else print "   -> backup card declined too\n";
		}
	}
}
print date('c') . " $db: " . json_encode($stats) . "\n";
exit(0);
