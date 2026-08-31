<?php
// tests/characterization/support/bootstrap_ordrefunc.php
//
// Session-less bootstrap for includes/ordrefunc.php, shared by the child
// runners that drive it.
//
// ordrefunc.php is a function library with some top-level code and a lot of
// globals. The production precedent for loading it WITHOUT a browser session
// is remoteBooking/api.php:29-60: connect to master, pick the tenant, include
// ordrefunc, reconnect to the tenant, derive $regnaar from grupper art='RA'.
// This file is that bootstrap, extracted so the runners agree on it.
//
// Expects $tenantDb to be set by the including runner. Leaves $regnaar,
// $brugernavn, $baseCurrency and the db_* functions in scope.
//
// History:
// 20260725 CL/LH Extracted from run_order_invoice.php for the coverage push.
// 20260825 CL/SZ Also include order_creation.php - opret_ordre()/opret_ordre_kopi()
//                now call order_creation_create() (SD-600)

if (!isset($tenantDb) || $tenantDb === '') {
    fwrite(STDERR, "bootstrap_ordrefunc.php: \$tenantDb must be set before including\n");
    exit(2);
}

error_reporting(E_ERROR | E_PARSE);

$_SERVER['REQUEST_URI'] = '/saldi/remoteBooking/api.php';
$_SERVER['PHP_SELF'] = '/saldi/remoteBooking/api.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

chdir(dirname(__DIR__, 3) . '/remoteBooking'); // ../includes/... must resolve

$header = "nix";
$bg = "nix";
ob_start(); // legacy includes print stray HTML on some branches

include("../includes/connect.php");  // connects to master, defines db_* + $sqhost/$squser/...
include("../includes/std_func.php");
$db = $tenantDb;

// Mirror what includes/online.php would provide for a logged-in session.
$r = db_fetch_array(db_select("select id, regnskab, posteringer from regnskab where db = '$db'", __FILE__ . " linje " . __LINE__));
$db_id = $r['id'];
$db_skriv_id = $db_id;
$regnskab = $r['regnskab'];
$max_posteringer = $r['posteringer'];

include("../includes/ordrefunc.php");
include("../includes/order_creation.php");

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
