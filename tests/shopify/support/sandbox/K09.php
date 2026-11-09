<?php
// tests/shopify/support/sandbox/K09.php
//
// Sandbox acceptance of K09 (SD-757): a set with two components shows the right quantity in Shopify.
// Run on the server that hosts the tenant, after the push has run:
//
//   php tests/shopify/support/sandbox/K09.php <tenant_db> <set vare_id>
//
// Prints one PASS/FAIL/SKIP line per shop.
//
// History:
// 20261105 CL/SZ SD-757: created.

if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 3) {
    echo "K09 SKIP: usage php tests/shopify/support/sandbox/K09.php <tenant_db> <set vare_id>\n";
    exit(0);
}
const STOCK_CHECK_K = 'K09';
require_once __DIR__ . '/stockCheck.php';
exit(stock_check_set((int)$argv[2]));
