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
//   preflight <ordre_id>- the posting difference forkontrolPosteringsdifference() predicts
//                         for the order as it is now (MB-80; run it after 'invoice')
//   direct <ordre_id>   - bogfor_nu($id,'webservice') with no caller transaction, as the REST send
//                         method does (MB-80); $valutakurs is taken from the order
//
// Prints a single JSON object on the LAST line of stdout.
//
// History:
// 20260723 CL/LH SD-601: created.
// 20260725 CL/LH Bootstrap extracted to bootstrap_ordrefunc.php, shared with run_order_lifecycle.php.
// 20260916 CDX/LH Isolate disposable test tenants and retain child-process failure diagnostics.
// 20261028 CL/LH Removed the inline bootstrap that the #815 merge brought back next to the
//                bootstrap_ordrefunc.php require; it included ordrefunc.php a second time.
// 20261111 CDX/NTR Require the caller-owned database instead of a fixed fallback.
// 20261125 CL/MMR MB-80: 'preflight' scenario, so a test can compare the pre-flight check with the posting.
// 20261125 CL/MMR MB-80 (Codex round 4): 'direct' scenario, bogfor_nu() without a caller transaction.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_order_invoice.php <invoice|reinvoice|preflight|direct> <ordre_id> <tenant_db>\n");
    exit(2);
}
$scenario = $argv[1];
$ordreId = (int)$argv[2];
$tenantDb = $argv[3];

require __DIR__ . '/bootstrap_ordrefunc.php';
error_reporting(E_ALL);

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
} elseif ($scenario === 'direct') {
    $r = db_fetch_array(db_select("select valutakurs from ordrer where id = '$ordreId'", __FILE__ . " linje " . __LINE__));
    $valutakurs = $r['valutakurs'];
    try {
        $out['bogfor'] = bogfor_nu($ordreId, 'webservice');
    } catch (Throwable $error) {
        $out['bogfor'] = get_class($error) . ': ' . $error->getMessage();
    }
} elseif ($scenario === 'preflight') {
    $r = db_fetch_array(db_select("select sum, moms, valuta, valutakurs from ordrer where id = '$ordreId'", __FILE__ . " linje " . __LINE__));
    $out['preflight'] = forkontrolPosteringsdifference($ordreId, $r['sum'] + $r['moms'], $r['valutakurs'], $r['moms']);
    $out['balance'] = forkontrolPosteringsbalance($ordreId, $r['sum'] + $r['moms'], $r['valuta'], $r['valutakurs'], $r['moms']);
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
