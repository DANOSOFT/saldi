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
//                 billing-ledger REST API. Runs the real form engine
//                 (includes/formfunk.php formularprint(), PostScript or HTML ->
//                 PDF, background via pdftk) in its "inkasso" mode, which builds
//                 the PDF and returns its path instead of mailing it.
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

// The form engine uses paths relative to debitor/ (../includes, ../temp, ../logolib).
chdir(__DIR__ . '/../debitor');
if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '/tools/stripe_invoice_pdf.php';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = 'cli';
$_GET = array(); $_POST = array(); $_SESSION = array();

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
$bruger_id = 99999;                          // engine writes to ../temp/<db>/<abs(bruger_id)>; keep it off real users' folders
$brugernavn = 'stripe_pdf';
$s_id = 'cli';
$exec_path = '';
$returside = '';
$locat = '';
$popup = 0;
foreach (array('ps2pdf' => '/usr/bin/ps2pdf', 'pdftk' => '', 'weasyprint' => '') as $tool => $fallback) {
	$q = db_fetch_array(db_select("select var_value from settings where var_name = '$tool'" . ($tool === 'weasyprint' ? " or var_name = 'html2pdf'" : ''), __FILE__ . " linje " . __LINE__));
	$$tool = ($q && trim((string)$q['var_value']) !== '') ? trim((string)$q['var_value']) : $fallback;
}
$saldi_pdf_only = true;                      // includes/formfunk.php: treat the order as "mail to email" so the PDF is built
$regnaar = function_exists('newest_active_fiscal_year') ? (string)newest_active_fiscal_year() : '';
if ($regnaar === '') { $ra = db_fetch_array(db_select("select max(fiscal_year) as y from grupper where art = 'RA'", __FILE__ . " linje " . __LINE__)); $regnaar = $ra ? (string)$ra['y'] : date('Y'); }

$o = db_fetch_array(db_select("select id, art, fakturanr, ordrenr, status, sprog, afd from ordrer where id = $ordreId", __FILE__ . " linje " . __LINE__));
if (!$o) { fwrite(STDERR, "order $ordreId not found in $db\n"); exit(2); }
if (!(int)$o['fakturanr']) { fwrite(STDERR, "order $ordreId is not invoiced\n"); exit(2); }
$formular = ($o['art'] === 'DK') ? 5 : 4;    // 4 = faktura, 5 = kreditnota

include('../includes/formfunk.php');
include('../includes/var2str.php');

// Same lookup as debitor/formularprint.php find_background_file() (not includable: it starts a session).
function stripe_pdf_background($background, $file_type, $department) {
	global $db_id;
	$base_dir = "../logolib/$db_id/";
	$dept_dir = ($department > 0) ? "../logolib/$db_id/$department/" : $base_dir;
	$is_default = in_array(strtolower((string)$background), array('dansk', 'danish', 'all', ''));
	$lang_suffix = $is_default ? '' : '_' . strtolower((string)$background);
	$candidates = array();
	if ($department > 0 && !$is_default) $candidates[] = $dept_dir . $file_type . $lang_suffix . ".pdf";
	if ($department > 0) $candidates[] = $dept_dir . $file_type . ".pdf";
	if (!$is_default) $candidates[] = $base_dir . $file_type . $lang_suffix . ".pdf";
	$candidates[] = $base_dir . $file_type . ".pdf";
	if ($file_type !== 'bg') $candidates[] = $base_dir . "bg.pdf";
	foreach ($candidates as $c) if (file_exists($c)) return $c;
	return null;
}
$background_file = stripe_pdf_background(isset($o['sprog']) ? $o['sprog'] : 'Dansk', 'faktura_bg', (int)$o['afd']);

if (!is_dir("../temp/$db")) @mkdir("../temp/$db", 0775, true);
$cacheDir = "../temp/$db/stripe_pdf";
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);

ob_start();                                  // the engine prints HTML fragments; keep stdout clean
$svar = formularprint($ordreId, $formular, 0, $charset, 'inkasso', $background_file);
$noise = ob_get_clean();
// In inkasso mode the engine returns the PDF path ("<mappe>/<file>.pdf") instead of mailing it.
if (!is_string($svar) || substr($svar, -4) !== '.pdf' || !is_file($svar)) {
	fwrite(STDERR, "formularprint did not produce a PDF: " . var_export($svar, true) . "\n" . substr(strip_tags($noise), 0, 400) . "\n");
	exit(2);
}
if (strncmp((string)file_get_contents($svar, false, null, 0, 4), '%PDF', 4) !== 0) { fwrite(STDERR, "not a PDF: $svar\n"); exit(2); }

$pdf = $outFile !== '' ? $outFile : "$cacheDir/$ordreId.pdf";
if (!copy($svar, $pdf)) { fwrite(STDERR, "could not write $pdf\n"); exit(2); }
@chmod($pdf, 0664);
print realpath($pdf) . "\n";
exit(0);
