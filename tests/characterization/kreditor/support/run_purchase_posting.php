<?php
// 20261113 CDX/NTR Execute supplier posting against the caller-owned characterization tenant (SD-599).

if ($argc !== 4) {
    fwrite(STDERR, "usage: run_purchase_posting.php <order_id> <tenant_db> <fiscal_year>\n");
    exit(2);
}
$id = (int)$argv[1];
$tenantDb = $argv[2];
$regnaar = (int)$argv[3];
$sprog_id = 1;
$difkto = 0;
require __DIR__ . '/../../support/bootstrap_ordrefunc.php';
error_reporting(E_ALL);
// Before extraction this executes the page's exact function without its request controller.
$source = file_get_contents(dirname(__DIR__, 4) . '/kreditor/bogfor.php');
$start = strpos($source, 'function bogfor($id) {');
$end = strpos($source, "\n?>", $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Supplier posting engine was not found');
}
$function = substr($source, $start, $end - $start);
eval(str_replace('function bogfor($id)', 'function characterize_purchase_posting($id)', $function));
transaktion('begin');
characterize_purchase_posting($id);
$out = ['transaction' => pg_transaction_status($connection)];
transaktion('commit');
ob_end_clean();
fwrite(STDOUT, json_encode($out) . "\n");
