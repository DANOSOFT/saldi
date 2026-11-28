<?php
// tests/characterization/support/run_pos_order_lines.php
//
// Child-process runner for the POS screen's line list: vis_pos_linjer() in
// debitor/pos_ordre_includes/showPosLines/showPosLinesFunc.php, which
// debitor/pos_ordre.php calls each time it draws an open POS order, with
// $show on as pos_ordre_itemscan.php calls it. Drawing the lines stores the
// order's sum and moms in ordrer, which is what the tests read afterwards.
//
// pos_ordre.php itself needs a till login and draws the whole till, so the
// runner loads what vis_pos_linjer() calls the way pos_ordre.php does
// (includes/ordrefunc.php, pos_ordre_includes/helperMethods/helperFunc.php and
// frontpage/itemTxt.php) and stands in only for pos_ordre.php's own
// find_stil() (a button style) and kundedisplay() (the customer display,
// which is only driven when a sale is closed). The screen writes its customer
// display file to ../temp/<db>/ and findtekst() looks for ../importfiler, so
// it runs in a scratch directory that has both, importfiler as a link to the
// repository's.
//
// argv: 1 = tenant database
//       2 = order id
//
// Prints one JSON line on stdout:
//   result - what vis_pos_linjer() returned: sum, rest, rounded sum and cost.
//
// History:
// 20261105 CL/LH Created for RestOrderLineTotalsTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_pos_order_lines.php <tenant_db> <order id>\n");
    exit(2);
}
$tenantDb = $argv[1];
$chartestOrderId = (int) $argv[2];

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/debitor/pos_ordre.php?id=' . $chartestOrderId;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

$chartestScratch = sys_get_temp_dir() . '/chartest_pos_' . getmypid();
mkdir($chartestScratch . '/page', 0700, true);
mkdir($chartestScratch . '/temp/' . $tenantDb, 0700, true);
symlink(dirname(__DIR__, 3) . '/importfiler', $chartestScratch . '/importfiler');
chdir($chartestScratch . '/page');

include_once __DIR__ . '/../../../includes/db_query.php';
include_once __DIR__ . '/../../../includes/connect.php';
include_once __DIR__ . '/../../../includes/std_func.php';
include_once __DIR__ . '/../../../includes/ordrefunc.php';
include_once __DIR__ . '/../../../debitor/pos_ordre_includes/helperMethods/helperFunc.php';
include_once __DIR__ . '/../../../debitor/pos_ordre_includes/frontpage/itemTxt.php';
include_once __DIR__ . '/../../../debitor/pos_ordre_includes/showPosLines/showPosLinesFunc.php';

/** Stands in for pos_ordre.php's find_stil(): the style of a button. */
function find_stil($type, $nr, $fs)
{
    return '';
}

/** Stands in for pos_ordre.php's kundedisplay(): vis_pos_linjer() drives the display only when a sale is closed. */
function kundedisplay($beskrivelse, $pris, $ryd)
{
}

$connection = db_connect($sqhost, $squser, $sqpass, $tenantDb, __FILE__ . " linje " . __LINE__);
if (!$connection || !db_select_database($connection, $tenantDb)) {
    fwrite(STDERR, "could not connect to $tenantDb\n");
    exit(1);
}
$db = $tenantDb;
$r = db_fetch_array(db_select("SELECT max(kodenr) AS regnaar FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__));
$regnaar = (int) $r['regnaar'];
// The globals pos_ordre.php has set when it draws the lines
$r = db_fetch_array(db_select("SELECT box12 FROM grupper WHERE art = 'POS' AND kodenr = '2' AND fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
$vis_saet = $r ? trim((string) $r['box12']) : '';
$bgcolor = '#ffffff';
$bgcolor5 = '#eeeeee';
$baseCurrency = 'DKK';
$brugernavn = 'chartest';
$kasse = 1;
$afd = 0;
$sprog_id = 1;
$lagerantal = 1;
$lagernr = $lagernavn = [];
$r = db_fetch_array(db_select("SELECT momssats, status FROM ordrer WHERE id = $chartestOrderId", __FILE__ . " linje " . __LINE__));

ob_start();
$chartestResult = vis_pos_linjer($chartestOrderId, $r['momssats'], $r['status'], NULL, 1);
$chartestHtml = ob_get_clean();
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $chartestHtml . "\n");
}

if (file_exists("$chartestScratch/temp/$tenantDb/customerDisplay.txt")) {
    unlink("$chartestScratch/temp/$tenantDb/customerDisplay.txt");
}
rmdir("$chartestScratch/temp/$tenantDb");
rmdir("$chartestScratch/temp");
unlink("$chartestScratch/importfiler");
rmdir("$chartestScratch/page");
rmdir($chartestScratch);

echo "\n" . json_encode(['result' => $chartestResult]) . "\n";
