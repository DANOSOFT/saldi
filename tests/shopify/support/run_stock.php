<?php
// tests/shopify/support/run_stock.php
//
// Child-process runner for the stock push (SD-756): boots the tenant session-less, as the worker
// does, and runs steps against a FakeShopifyStore whose state lives in a JSON file, so a test can
// change stock between runs and the shops keep their levels.
//   php run_stock.php <test_db> <state file> '<steps json>'
// Steps: ["shop", shop_key, {lager: location name}] (a fake shop with those locations, mapped),
//        ["seed", shop_key, vare_id] (a product for the item, stocked with 0 at every location, linked),
//        ["unstock", shop_key, vare_id, lager], ["triggers"], ["drain", limit], ["next", shop_key] (stores the next call, sends nothing), ["push", shop_key, seconds],
//        ["worker", seconds] (one worker pass with the stock producer and handler), ["clean"] (empties shopify_stock_dirty), ["save", shop fields] (shopify_save_shop()), ["fail", root_field, mode],
//        ["open", shop_key, ordre_id, [vare_ids]], ["release", shop_key, ordre_id].
// Prints JSON: one result per step (or its exception), the calls sent and every shop's available
// quantity per SKU and location name.
//
// History:
// 20261104 CL/SZ SD-756: created.
// 20261104 CL/SZ SD-756 review: steps save and clean.
// 20261105 CL/SZ SD-756: shopify_stock_next() takes the runtime.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_stock.php <test_db> <state file> '<steps json>'\n");
    exit(2);
}
$testDb = $argv[1];
$stateFile = $argv[2];
$steps = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);

require_once __DIR__ . '/ShopifyEnv.php';
require_once __DIR__ . '/FakeShopifyStore.php';
ShopifyEnv::assertSafeTestDb($testDb);

$db_type = 'postgresql';
$db_encode = 'UTF8';
$sqhost = ShopifyEnv::pgHost();
$squser = ShopifyEnv::pgUser();
$sqpass = ShopifyEnv::pgPass();
$sqdb = ShopifyEnv::masterDb();
$db = $sqdb;
$db_skriv_id = null;
$brugernavn = '';
$webservice = false;

require_once ShopifyEnv::repoRoot() . '/includes/db_query.php';
$connection = db_connect($sqhost, $squser, $sqpass, $sqdb);
require_once ShopifyEnv::repoRoot() . '/includes/std_func.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/bootstrap.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/worker.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/links.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/stock.php';

$store = is_file($stateFile) ? FakeShopifyStore::fromState(json_decode((string)file_get_contents($stateFile), true)) : new FakeShopifyStore();
$sent = count($store->calls);
$domain = fn(string $key): string => "$key-fake.myshopify.com";
$shopId = fn(string $key): int => (int)shopify_shop($key)['id'];
/** The fake location GID a lager is mapped to in a shop. */
$location = fn(string $key, int $lager): string => (string)shopify_location($shopId($key), $lager);

$results = [];
try {
    shopify_bootstrap($testDb);
    foreach ($steps as $step) {
        try {
            switch ($step[0]) {
                case 'shop':
                    $store->addShop($step[1], ['locations' => []]);
                    $gids = [];
                    foreach ($step[2] as $lager => $name) {
                        $gids[$lager] = $store->addLocation($domain($step[1]), $name);
                        shopify_db_transaction(fn() => shopify_save_map($shopId($step[1]), (int)$lager, $gids[$lager]));
                    }
                    $results[] = $gids;
                    break;
                case 'seed':
                    $varenr = shopify_db_rows('SELECT varenr FROM varer WHERE id = $1', [(int)$step[2]])[0]['varenr'];
                    $p = $store->seedProduct($domain($step[1]), "saldi-{$step[2]}", $varenr);
                    $results[] = shopify_upsert_product_link($shopId($step[1]), (int)$step[2], 0, ['product_gid' => $p['product'], 'variant_gid' => $p['variant'],
                        'inventory_item_gid' => $p['item'], 'listed' => true])['id'] ?? null;
                    break;
                case 'unstock':
                    $item = shopify_product_link($shopId($step[1]), (int)$step[2])['inventory_item_gid'];
                    $store->unstock($domain($step[1]), $item, $location($step[1], (int)$step[3]));
                    $results[] = 'unstocked';
                    break;
                case 'clean':
                    shopify_db_rows('DELETE FROM shopify_stock_dirty');
                    $results[] = 'clean';
                    break;
                case 'save':
                    $results[] = shopify_db_transaction(fn(): int => shopify_save_shop($step[1]));
                    break;
                case 'triggers':
                    $results[] = shopify_db_transaction(fn(): bool => ensure_shopify_stock_triggers());
                    break;
                case 'drain':
                    $results[] = shopify_stock_drain_dirty([], (int)($step[1] ?? 500));
                    break;
                case 'next':
                    $results[] = shopify_stock_next(['graphql' => $store->opts()], shopify_shop($step[1]), microtime(true) + 30);
                    break;
                case 'push':
                    $job = ['id' => 0, 'shop_id' => $shopId($step[1])];
                    $results[] = shopify_stock_push(['graphql' => $store->opts()], $job, microtime(true) + (float)($step[2] ?? 30));
                    break;
                case 'worker':
                    $registry = ['stock.push' => fn(array $job, float $deadline, callable $hb): array
                        => shopify_stock_push(['heartbeat' => $hb, 'graphql' => $store->opts()], $job, $deadline)];
                    $producers = ['stock' => fn(array $ctx, float $deadline): array => shopify_stock_drain_dirty($ctx)];
                    $results[] = shopify_worker_run(['db' => $testDb, 'heartbeat_file' => null], $registry, $producers, (int)($step[1] ?? 30));
                    break;
                case 'fail':
                    $store->fail[$step[1]] = $step[2];
                    $results[] = 'armed';
                    break;
                case 'open':
                    $results[] = shopify_db_transaction(fn(): int => shopify_barrier_open($shopId($step[1]), (int)$step[2], $step[3]));
                    break;
                case 'release':
                    $results[] = shopify_barrier_release($shopId($step[1]), (int)$step[2]);
                    break;
                default:
                    throw new LogicException("unknown step {$step[0]}");
            }
        } catch (Throwable $e) {
            $results[] = ['exception' => get_class($e), 'message' => $e->getMessage()];
        }
    }
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'class' => get_class($e)]);
    exit(1);
}
$state = $store->exportState();
file_put_contents($stateFile, json_encode($state));
$levels = [];
foreach ($state['shops'] as $s) {
    foreach ($s['variants'] as $v) {
        foreach ($s['levels'][$v['item']] ?? [] as $gid => $level) {
            $levels[$s['key']][$v['sku']][$s['locations'][$gid]['name']] = $level['available'];
        }
    }
}
$calls = array_map(fn(array $c): array => ['shop' => substr($c['shop'], 0, strpos($c['shop'], '-')), 'operation' => $c['operation'], 'variables' => $c['variables'], 'status' => $c['status']],
    array_slice($store->calls, $sent));
echo json_encode(['results' => $results, 'calls' => $calls, 'levels' => $levels], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
