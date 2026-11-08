<?php
// tests/shopify/support/sandbox/K55.php
//
// Sandbox acceptance of K55 (SD-757): a set shows its complete sets per lager and the cross-location ones on the split-set lager, never more than the sellable lagre can assemble.
// Run on the server that hosts the tenant, after the push has run:
//
//   php tests/shopify/support/sandbox/K55.php <tenant_db> <set vare_id>
//
// Prints one PASS/FAIL/SKIP line per shop.
//
// History:
// 20261105 CL/SZ SD-757: created.

if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 3) {
    echo "K55 SKIP: usage php tests/shopify/support/sandbox/K55.php <tenant_db> <set vare_id>\n";
    exit(0);
}
const STOCK_CHECK_K = 'K55';
require_once __DIR__ . '/stockCheck.php';
exit(stock_check_set((int)$argv[2]));
