<?php
// tests/shopify/support/sandbox/K19.php
//
// Sandbox acceptance of K19 (SD-757): a stock baseline is set per shop. Run on the server that hosts
// the tenant, after `php tools/shopify_worker.php baseline` and the worker have run for each shop:
//
//   php tests/shopify/support/sandbox/K19.php <tenant_db>
//
// A shop passes when stock_baseline_at is set and every pair still has an acknowledgement. Prints one
// PASS/FAIL/SKIP line per shop that captures stock.
//
// History:
// 20261105 CL/SZ SD-757: created.

if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 2) {
    echo "K19 SKIP: usage php tests/shopify/support/sandbox/K19.php <tenant_db>\n";
    exit(0);
}
const STOCK_CHECK_K = 'K19';
require_once __DIR__ . '/stockCheck.php';
$shops = shopify_db_rows('SELECT * FROM shopify_shops WHERE stock_capture_enabled ORDER BY shop_key');
if ($shops === []) {
    echo "K19 SKIP: no shop captures stock\n";
    exit(0);
}
$failed = false;
foreach ($shops as $shop) {
    $missing = shopify_stock_baseline_missing((int)$shop['id'], shopify_lager_config());
    if ($shop['stock_baseline_at'] !== null && $missing === []) {
        echo "K19 PASS {$shop['shop_key']}: baseline from {$shop['stock_baseline_at']}, every pair acknowledged\n";
    } else {
        $failed = true;
        echo "K19 FAIL {$shop['shop_key']}: stock_baseline_at " . json_encode($shop['stock_baseline_at']) . ', ' . count($missing) . ' missing: ' . implode('; ', array_slice($missing, 0, 5)) . "\n";
    }
}
exit($failed ? 1 : 0);
