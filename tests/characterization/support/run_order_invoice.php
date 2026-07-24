<?php
// tests/characterization/support/run_order_invoice.php
//
// Child-process runner for the order->invoice conversion (SD-601).
//
// includes/ordrefunc.php is a function library with some top-level code; the
// production precedent for bootstrapping it WITHOUT a browser session is
// remoteBooking/api.php:29-60 (connect to master, set $db, include
// ordrefunc, reconnect to the tenant, derive $regnaar from grupper art='RA').
// This runner copies that bootstrap, then executes the same invoice sequence
// remoteBooking runs in production (api.php:296-337):
//
//   transaktion('begin')
//   update ordrer set fakturadate=ordredate
//   update ordrelinjer set leveres=antal
//   levering($id,'on',NULL,'on')
//   bogfor($id,'on')          <- ordrefunc's bogfor, NOT finans/bogfor.php
//   transaktion('commit')
//
// Scenarios (argv 1):
//   invoice <ordre_id>  - run the sequence above on an existing order
//   reinvoice <ordre_id>- call bogfor() again on an already-invoiced order
//
// Prints a single JSON object on the LAST line of stdout.
//
// History:
// 20260723 CL/LH SD-601: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_order_invoice.php <invoice|reinvoice> <ordre_id> [tenant_db]\n");
    exit(2);
}
$scenario = $argv[1];
$ordreId = (int)$argv[2];
$tenantDb = $argv[3] ?? 'saldi_chartest';

error_reporting(E_ERROR | E_PARSE);

$_SERVER['REQUEST_URI'] = '/saldi/remoteBooking/api.php';
$_SERVER['PHP_SELF'] = '/saldi/remoteBooking/api.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

chdir(dirname(__DIR__, 3) . '/remoteBooking'); // ../includes/... must resolve

// --- bootstrap copied from remoteBooking/api.php:29-60 ---
$header = "nix";
$bg = "nix";
ob_start(); // legacy includes print stray HTML on some branches
include("../includes/connect.php");      // connects to master, defines db_* + $sqhost/$squser/...
include("../includes/std_func.php");
$db = $tenantDb;

// Mirror what includes/online.php would provide for a logged-in session.
$r = db_fetch_array(db_select("select id, regnskab, posteringer from regnskab where db = '$db'", __FILE__ . " linje " . __LINE__));
$db_id = $r['id'];
$db_skriv_id = $db_id;
$regnskab = $r['regnskab'];
$max_posteringer = $r['posteringer'];

include("../includes/ordrefunc.php");

$connection = db_connect($sqhost, $squser, $sqpass, $db);
$query = db_select("SELECT box1, box2, box3, box4, kodenr FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__);
$currentYear = date('Y');
$currentMonth = date('m');
while ($row = db_fetch_array($query)) {
    if (($currentYear > $row['box2'] || ($currentYear == $row['box2'] && $currentMonth >= $row['box1'])) &&
        ($currentYear < $row['box4'] || ($currentYear == $row['box4'] && $currentMonth <= $row['box3']))) {
        $regnaar = $row['kodenr'];
    }
}
$baseCurrency = get_settings_value("baseCurrency", "globals", "");
if ($baseCurrency == "") {
    $baseCurrency = "DKK";
}
$brugernavn = 'chartest';
$bruger_id = 1;
$sprog_id = 0;
// --- end bootstrap ---

$out = ['scenario' => $scenario, 'ordre_id' => $ordreId];

if ($scenario === 'invoice') {
    transaktion('begin');
    db_modify("update ordrer set fakturadate=ordredate where id='$ordreId'", __FILE__ . " linje " . __LINE__);
    db_modify("update ordrelinjer set leveres = antal where ordre_id='$ordreId'", __FILE__ . " linje " . __LINE__);
    $out['levering'] = levering($ordreId, 'on', NULL, 'on');
    $out['bogfor'] = bogfor($ordreId, 'on');
    if ($out['bogfor'] === 'OK') {
        transaktion('commit');
    }
} elseif ($scenario === 'reinvoice') {
    $out['bogfor'] = bogfor($ordreId, 'on');
} else {
    ob_end_clean();
    fwrite(STDERR, "unknown scenario $scenario\n");
    exit(2);
}

$r = db_fetch_array(db_select("select status, fakturanr, sum, moms from ordrer where id = '$ordreId'", __FILE__ . " linje " . __LINE__));
$out['status'] = $r['status'];
$out['fakturanr'] = $r['fakturanr'];
$out['sum'] = $r['sum'];
$out['moms'] = $r['moms'];

ob_end_clean();
fwrite(STDOUT, json_encode($out) . "\n");
