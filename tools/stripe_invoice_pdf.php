<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/stripe_invoice_pdf.php --- 2026.10.19
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
// 20261019 CL/ASR Created: renders ONE invoice (or credit note) to PDF without a
//                 browser session, for the public invoice & payment page
//                 (Requirements_invoice_email_payment_page_EN.md par. 5.2) and the
//                 billing-ledger REST API. Runs the normal form engine
//                 (debitor/formprintfunc.php -> PostScript -> ps2pdf) with
//                 $saldi_pdf_only set, so the mail branch is never taken.
//
//   php tools/stripe_invoice_pdf.php --db=<ledger db> --id=<ordrer.id> [--out=<file>]
//
// Writes <docroot>/temp/<db>/stripe_pdf/<ordre_id>.pdf (or --out) and prints the
// path on success. Exit code 0 = ok, 1 = usage, 2 = render failed.

if (php_sapi_name() !== 'cli') { header('HTTP/1.1 403 Forbidden'); print 'CLI only'; exit; }

$options = getopt('', array('db:', 'id:', 'out::'));
$targetDb = isset($options['db']) ? trim((string)$options['db']) : '';
$ordreId  = isset($options['id']) ? (int)$options['id'] : 0;
$outFile  = isset($options['out']) ? (string)$options['out'] : '';
if ($targetDb === '' || !preg_match('/^[a-z0-9_]{1,40}$/', $targetDb) || $ordreId < 1) {
	fwrite(STDERR, "usage: php tools/stripe_invoice_pdf.php --db=<db> --id=<ordre_id> [--out=<file>]\n");
	exit(1);
}

// The form engine uses paths relative to debitor/ (../includes, ../temp, ../logo).
chdir(__DIR__ . '/../debitor');
if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '/tools/stripe_invoice_pdf.php';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = 'cli';
$_GET = array(); $_POST = array();

include('../includes/db_query.php');
include('../includes/connect.php');          // master connection + $sqhost/$squser/$sqpass
include('../includes/std_func.php');

// Resolve the ledger through the master locator, exactly like stripeConfigDb().
$r = db_fetch_array(db_select("select id, db from regnskab where db = '" . db_escape_string($targetDb) . "'", __FILE__ . " linje " . __LINE__, true));
if (!$r) { fwrite(STDERR, "unknown ledger $targetDb\n"); exit(1); }
$connection = db_connect($sqhost, $squser, $sqpass, $r['db']);
if (!$connection) { fwrite(STDERR, "no connection to $targetDb\n"); exit(2); }
$db = $r['db'];
$db_id = (int)$r['id'];

// Globals the engine normally inherits from online.php.
if (!isset($charset) || !$charset) $charset = 'UTF-8';
$sprog_id = 1;
$bruger_id = 0;
$brugernavn = 'stripe_pdf';
$s_id = 'cli';
$exec_path = '';
$returside = '';
$saldi_pdf_only = true;                      // debitor/formprintfunc.php: never take the mail branch

$o = db_fetch_array(db_select("select id, art, fakturanr, ordrenr, status from ordrer where id = $ordreId", __FILE__ . " linje " . __LINE__));
if (!$o) { fwrite(STDERR, "order $ordreId not found in $db\n"); exit(2); }
if ((int)$o['status'] < 3 && !(int)$o['fakturanr']) { fwrite(STDERR, "order $ordreId is not invoiced\n"); exit(2); }
$formular = ($o['art'] === 'DK') ? 5 : 4;    // 4 = faktura, 5 = kreditnota

include('../includes/formfunk.php');
include('../includes/var2str.php');
include('formprintfunc.php');                // defines formularprint()

if (!is_dir("../temp/$db")) @mkdir("../temp/$db", 0775, true);
$cacheDir = "../temp/$db/stripe_pdf";
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);

ob_start();                                  // the engine prints HTML fragments; keep stdout clean
$svar = formularprint($ordreId, $formular, 0, $charset);
$noise = ob_get_clean();
if ($svar && $svar !== 'OK') { fwrite(STDERR, "formularprint: $svar\n"); exit(2); }

// Single, non-mail run -> ../temp/<db>/fakt<nr> or kn<nr> (formprintfunc.php, $filnavne).
$nr = (string)$o['fakturanr'];
$ps = "../temp/$db/" . ($formular == 5 ? "kn$nr" : "fakt$nr");
if (!is_file($ps) || filesize($ps) < 100) { fwrite(STDERR, "PostScript not produced ($ps)\n" . substr(strip_tags($noise), 0, 300) . "\n"); exit(2); }

$pdf = $outFile !== '' ? $outFile : "$cacheDir/$ordreId.pdf";
$cmd = '/usr/bin/ps2pdf ' . escapeshellarg($ps) . ' ' . escapeshellarg($pdf) . ' 2>&1';
exec($cmd, $out, $rc);
if ($rc !== 0 || !is_file($pdf) || strncmp((string)file_get_contents($pdf, false, null, 0, 4), '%PDF', 4) !== 0) {
	fwrite(STDERR, "ps2pdf failed (rc $rc): " . implode(' ', $out) . "\n"); exit(2);
}
@unlink($ps);
print realpath($pdf) . "\n";
exit(0);
