<?php
// tests/shopify/support/sandbox/K13.php
//
// Sandbox acceptance of K13 (SD-757): an item with stock at a display lager shows 0 at that location
// in every shop, and the variant's saldi.udstillet lists the location. Run on the server that hosts
// the tenant, after the push and stock.display jobs have run:
//
//   php tests/shopify/support/sandbox/K13.php <tenant_db> <vare_id> <display lager>
//
// Prints one PASS/FAIL/SKIP line per shop.
//
// History:
// 20261105 CL/SZ SD-757: created.

if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 4) {
    echo "K13 SKIP: usage php tests/shopify/support/sandbox/K13.php <tenant_db> <vare_id> <display lager>\n";
    exit(0);
}
const STOCK_CHECK_K = 'K13';
require_once __DIR__ . '/stockCheck.php';
[$vareId, $lager] = [(int)$argv[2], (int)$argv[3]];
$shops = stock_check_read($vareId);
if ($shops === []) {
    echo "K13 SKIP: no shop publishes stock\n";
    exit(0);
}
$failed = false;
foreach ($shops as $key => $row) {
    if (is_string($row)) {
        echo "K13 SKIP $key: $row\n";
        continue;
    }
    if (!array_key_exists($lager, $row['want'])) {
        echo "K13 SKIP $key: lager $lager has no location in this shop\n";
        continue;
    }
    $name = shopify_graphql(shopify_shop($key), 'k13Location', 'query k13Location($id: ID!) { location(id: $id) { name } }', ['id' => shopify_location((int)shopify_shop($key)['id'], $lager)], ['retry_mode' => 'read', 'deadline' => microtime(true) + 20])['data']['location']['name'] ?? null;
    $listed = is_array($row['udstillet']) && in_array($name, $row['udstillet'], true);
    if ($row['shown'][$lager] === 0 && $row['want'][$lager] === 0 && $row['display'][$lager] && $listed) {
        echo "K13 PASS $key: 0 sellable at $name, and saldi.udstillet lists it: " . json_encode($row['udstillet'], JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        $failed = true;
        echo "K13 FAIL $key: shows " . json_encode($row['shown'][$lager]) . " at $name; Saldi has it there: " . json_encode($row['display'][$lager]) . '; saldi.udstillet ' . json_encode($row['udstillet'], JSON_UNESCAPED_UNICODE) . "\n";
    }
}
exit($failed ? 1 : 0);
