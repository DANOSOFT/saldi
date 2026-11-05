<?php
// tests/shopify/support/sandbox/K12.php
//
// Sandbox acceptance of K12 (SD-756): a Ballerup POS sale reduces the Ballerup location in all
// three sandbox shops within 5 minutes. Run on the server that hosts the tenant, with its worker
// timer running, right after the sale was made in POS:
//
//   php tests/shopify/support/sandbox/K12.php <tenant_db> <vare_id> <lager>
//   e.g. php tests/shopify/support/sandbox/K12.php saldi_shopify_test_xx 1042 2
//
// Reads what Saldi has of the item at the lager now, then reads the item's available quantity at
// the lager's location in every shop with stock_publish_enabled every 10 seconds, until all show
// it or 5 minutes have passed. Changes nothing in Saldi or Shopify. Prints one
// PASS/FAIL/SKIP line per shop.
//
// History:
// 20261104 CL/SZ SD-756: created.
// 20261104 CL/SZ SD-756 review: links.php is loaded; a match after the 5 minutes doesn't count.

if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 4) {
    echo "K12 SKIP: usage php tests/shopify/support/sandbox/K12.php <tenant_db> <vare_id> <lager>\n";
    exit(0);
}
[, $tenant, $vareId, $lager] = $argv;
[$vareId, $lager] = [(int)$vareId, (int)$lager];
if (!preg_match('/^saldi_shopify_test_[a-z0-9_]+$/', $tenant)) {
    echo "K12 SKIP: $tenant is not a saldi_shopify_test_* tenant\n";
    exit(0);
}
$root = dirname(__DIR__, 4);
if (!is_readable("$root/includes/connect.php")) {
    echo "K12 SKIP: includes/connect.php is not installed here\n";
    exit(0);
}
// connect.php finds db_query.php relative to the working directory, like the worker in tools/.
chdir("$root/tools");
include("$root/includes/connect.php");
require_once "$root/includes/shopify/bootstrap.php";
require_once "$root/includes/shopify/links.php";
require_once "$root/includes/shopify/services/stock.php";

const K12_LEVEL = 'query k12Level($item: ID!, $location: ID!) {
	inventoryItem(id: $item) { inventoryLevel(locationId: $location) { quantities(names: ["available"]) { name quantity } } }
}';

try {
    shopify_bootstrap($tenant);
    $milli = (int)(shopify_db_rows('SELECT round(coalesce(sum(beholdning), 0) * 1000)::bigint AS milli FROM lagerstatus
        WHERE vare_id = $1 AND lager = $2 AND coalesce(variant_id, 0) = 0', [$vareId, $lager])[0]['milli']);
    $lagre = shopify_availability_lagre(shopify_lager_config());
    $sellable = array_filter($lagre, fn(array $l): bool => (int)$l['lager'] === $lager && $l['rolle'] === 'saelgbar' && !$l['virtual']) !== [];
    $want = $sellable ? shopify_availability(['vare_id' => $vareId], $lagre, [$vareId => [$lager => $milli]], [], null)[$lager]['available'] : null;
    $shops = shopify_db_rows('SELECT * FROM shopify_shops WHERE stock_publish_enabled ORDER BY shop_key');
} catch (Throwable $e) {
    echo 'K12 SKIP: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(0);
}
if ($want === null) {
    echo "K12 SKIP: lager $lager is not a sellable lager, so the push sends nothing for it\n";
    exit(0);
}
$targets = [];
foreach ($shops as $shop) {
    $link = shopify_product_link((int)$shop['id'], $vareId);
    $location = shopify_location((int)$shop['id'], $lager);
    if ($link === null || $link['inventory_item_gid'] === null || $location === null) {
        echo "K12 SKIP {$shop['shop_key']}: item $vareId is not linked, or lager $lager has no location, in this shop\n";
        continue;
    }
    $targets[$shop['shop_key']] = ['row' => $shop, 'vars' => ['item' => $link['inventory_item_gid'], 'location' => $location], 'seen' => null, 'at' => null];
}
if ($targets === []) {
    echo "K12 SKIP: no shop publishes stock for item $vareId at lager $lager\n";
    exit(0);
}
$start = microtime(true);
do {
    foreach ($targets as $key => $t) {
        if ($t['at'] !== null) {
            continue;
        }
        $r = shopify_graphql($t['row'], 'k12Level', K12_LEVEL, $t['vars'], ['retry_mode' => 'read', 'deadline' => microtime(true) + 20]);
        $seen = $r['ok'] ? ($r['data']['inventoryItem']['inventoryLevel']['quantities'][0]['quantity'] ?? null) : null;
        $targets[$key]['seen'] = $seen;
        $elapsed = microtime(true) - $start;
        if ($seen === $want && $elapsed <= 300) {
            $targets[$key]['at'] = $elapsed;
        }
    }
    $open = array_filter($targets, fn(array $t): bool => $t['at'] === null);
    if ($open !== [] && microtime(true) - $start < 300) {
        sleep(10);
    }
} while ($open !== [] && microtime(true) - $start < 300);
$failed = false;
foreach ($targets as $key => $t) {
    if ($t['at'] !== null) {
        printf("K12 PASS %s: lager %d shows %d, as Saldi has, %.0f s after the start\n", $key, $lager, $want, $t['at']);
    } else {
        $failed = true;
        echo "K12 FAIL $key: lager $lager shows " . json_encode($t['seen']) . " after 5 minutes; Saldi has $want\n";
    }
}
exit($failed ? 1 : 0);
