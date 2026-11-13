<?php
// 20261113 CDX/NTR Execute supplier posting against the caller-owned characterization tenant (SD-599).
// 20261113 CDX/NTR Call the extracted supplier posting function directly.

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
require_once dirname(__DIR__, 4) . '/includes/purchaseInvoicePosting.php';
transaktion('begin');
post_purchase_invoice($id);
$out = ['transaction' => pg_transaction_status($connection)];
transaktion('commit');
ob_end_clean();
fwrite(STDOUT, json_encode($out) . "\n");
