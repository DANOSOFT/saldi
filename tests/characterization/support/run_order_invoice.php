<?php
// tests/characterization/support/run_order_invoice.php
//
// Child-process runner for the order->invoice conversion (SD-601).
//
// The session-less bootstrap for includes/ordrefunc.php lives in
// bootstrap_ordrefunc.php (it mirrors remoteBooking/api.php:29-60). This
// runner executes the same invoice sequence remoteBooking runs in production
// (api.php:296-337):
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
// 20260725 CL/LH Bootstrap extracted to bootstrap_ordrefunc.php, shared with run_order_lifecycle.php.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_order_invoice.php <invoice|reinvoice> <ordre_id> [tenant_db]\n");
    exit(2);
}
$scenario = $argv[1];
$ordreId = (int)$argv[2];
$tenantDb = $argv[3] ?? 'saldi_chartest';

require __DIR__ . '/bootstrap_ordrefunc.php';

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
