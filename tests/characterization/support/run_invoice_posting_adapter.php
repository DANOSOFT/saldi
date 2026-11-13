<?php
// 20261113 CDX/NTR Execute the invoice posting interface independently of the order library (SD-599).

if ($argc !== 4) {
    fwrite(STDERR, "usage: run_invoice_posting_adapter.php <order_id> <tenant_db> <fiscal_year>\n");
    exit(2);
}
$id = (int)$argv[1];
$tenantDb = $argv[2];
$regnaar = (int)$argv[3];
$sprog_id = 1;
$difkto = 0;
$reportNumber = 0;
$valuta = 'DKK';
$valutakurs = 100;
$title = '';
require __DIR__ . '/bootstrap_invoice_posting.php';
transaktion('begin');
$row = db_fetch_array(db_select("select art from ordrer where id='$id'", __FILE__ . ' linje ' . __LINE__));
$out = ['result' => invoice_posting_for_type($row['art'])->post($id, 'webservice')];
$out['loadedOrderLibrary'] = function_exists('bogfor_nu');
$out['transaction'] = pg_transaction_status($connection);
transaktion('commit');
ob_end_clean();
fwrite(STDOUT, json_encode($out) . "\n");
