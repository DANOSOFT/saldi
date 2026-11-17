<?php
// tests/shopify/support/run_writeback.php
//
// Child-process runner for the order write-back (SD-759): bootstraps the tenant and runs a list of
// steps against a FakeShopifyStore whose state lives in a JSON file, so a test can edit the tenant
// between runs and the shop keeps its orders.
//   php run_writeback.php <test_db> <state file> '<steps json>'
// Steps: ["order", note] (an order in the shop, its GID is the result),
//        ["pass"] (one worker pass with the write-back handlers, as tools/shopify_worker.php wires them),
//        ["fail", root_field, mode] (the store's next answer to root_field fails, see FakeShopifyStore::takeFailure()),
//        ["untag", order_gid, tag] (staff remove a tag in Shopify),
//        ["location", name] (one more location in the shop, its GID is the result),
//        ["web_order", [[sku, quantity, {location index: available}], ...]] (an order of those items; the result is
//            its GID and its line GIDs by sku; Shopify assigns each line to the first location that has it all),
//        ["unstock", sku, location index] (the location no longer stocks the item),
//        ["movelist", day, ship_lager|null] (shopify_load_move_list()).
// Prints JSON: one result per step (or its exception), the operations sent, the shop's orders, and per order
// its open fulfillment-order lines as [location index, line GID, remaining], and the shop's location GIDs.
//
// History:
// 20261109 CL/MJ SD-759: created.
// 20261113 CL/MJ SD-760: order.move and barrier.sweep in the pass; the steps location, web_order, unstock and movelist.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_writeback.php <test_db> <state file> '<steps json>'\n");
    exit(2);
}
$testDb = $argv[1];
$stateFile = $argv[2];
$steps = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);

require_once __DIR__ . '/ShopifyEnv.php';
require_once __DIR__ . '/FakeShopifyStore.php';
ShopifyEnv::assertSafeTestDb($testDb);

function alert($msg)
{
    fwrite(STDERR, "alert: $msg\n");
    exit(3);
}

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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/bootstrap.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/worker.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/orderWriteback.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/moveList.php';
require_once ShopifyEnv::repoRoot() . '/includes/std_func.php';

const RUN_WRITEBACK_ORDER = 'mutation orderCreate($order: OrderCreateOrderInput!) { orderCreate(order: $order) { order { id } userErrors { message } } }';

$store = is_file($stateFile) && filesize($stateFile) > 0 ? FakeShopifyStore::fromState(json_decode((string)file_get_contents($stateFile), true)) : new FakeShopifyStore();
$domain = 'hml-fake.myshopify.com';
try {
    $store->shopRow($domain);
} catch (LogicException $e) {
    $store->addShop('hml');
}
$sent = count($store->calls);
$results = [];
try {
    shopify_bootstrap($testDb);
    foreach ($steps as $step) {
        try {
            switch ($step[0]) {
                case 'order':
                    $item = $store->seedProduct($domain, 'havesaet-' . count($store->calls), 'SD759-' . count($store->calls));
                    $vars = ['order' => ['lineItems' => [['variantId' => $item['variant'], 'quantity' => 1]], 'note' => $step[1]]];
                    $results[] = json_decode($store->request($domain, RUN_WRITEBACK_ORDER, $vars)['body'], true)['data']['orderCreate']['order']['id'];
                    break;
                case 'pass':
                    $registry = [];
                    foreach (['order.tags', 'order.move', 'order.references', 'order.note', 'order.processed'] as $type) {
                        shopify_worker_register($registry, $type, fn(array $job, float $deadline, callable $heartbeat): array
                            => shopify_writeback_job($job, $deadline, $heartbeat, ['graphql' => $store->opts()]));
                    }
                    shopify_worker_register($registry, 'barrier.sweep', 'shopify_barrier_sweep_job');
                    $summary = shopify_worker_run(['db' => $testDb, 'shops' => [], 'heartbeat_file' => $stateFile . '.heartbeat'], $registry, [], 50);
                    $results[] = array_map(fn(array $j): array => [$j['type'], $j['result']], $summary['jobs']);
                    break;
                case 'fail':
                    $store->fail[$step[1]] = $step[2];
                    $results[] = 'armed';
                    break;
                case 'untag':
                    $remove = 'mutation r($id: ID!, $tags: [String!]!) { tagsRemove(id: $id, tags: $tags) { node { id } userErrors { message } } }';
                    $store->request($domain, $remove, ['id' => $step[1], 'tags' => [$step[2]]]);
                    $results[] = 'untagged';
                    break;
                case 'location':
                    $results[] = $store->addLocation($domain, $step[1]);
                    break;
                case 'web_order':
                    $locations = $store->locations($domain);
                    $lines = [];
                    foreach ($step[1] as [$sku, $quantity, $available]) {
                        $stock = [];
                        foreach ($available as $index => $qty) {
                            $stock[$locations[$index]] = $qty;
                        }
                        $item = $store->seedProduct($domain, strtolower($sku) . '-' . count($store->calls), $sku, $stock);
                        $lines[] = ['variantId' => $item['variant'], 'quantity' => $quantity];
                    }
                    $create = 'mutation o($order: OrderCreateOrderInput!) { orderCreate(order: $order) { order { id lineItems(first: 20) { nodes { id sku } } } userErrors { message } } }';
                    $order = json_decode($store->request($domain, $create, ['order' => ['lineItems' => $lines]])['body'], true)['data']['orderCreate']['order'];
                    $results[] = ['order' => $order['id'], 'lines' => array_column($order['lineItems']['nodes'], 'id', 'sku')];
                    break;
                case 'unstock':
                    foreach ($store->shop($domain)['variants'] as $variant) {
                        if (($variant['sku'] ?? null) === $step[1]) {
                            $store->unstock($domain, $variant['item'], $store->locations($domain)[$step[2]]);
                        }
                    }
                    $results[] = 'unstocked';
                    break;
                case 'movelist':
                    $results[] = shopify_load_move_list($step[1], null, $step[2]);
                    break;
                default:
                    throw new LogicException("unknown step {$step[0]}");
            }
        } catch (Throwable $e) {
            $results[] = ['exception' => get_class($e), 'message' => $e->getMessage()];
        }
    }
} finally {
    file_put_contents($stateFile, json_encode($store->exportState(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
$orders = [];
foreach ($store->shop($domain)['orders'] as $gid => $o) {
    $metafields = array_map(fn(array $m): string => (string)$m['value'], $store->shop($domain)['metafields'][$gid] ?? []);
    $open = [];
    foreach ($store->shop($domain)['fulfillment_orders'] as $fo) {
        foreach ($fo['order'] === $gid && in_array($fo['status'], ['OPEN', 'IN_PROGRESS'], true) ? $fo['lines'] : [] as $li) {
            if ($li['remaining'] > 0) {
                $open[] = [array_search($fo['location'], $store->locations($domain), true), $li['line'], $li['remaining']];
            }
        }
    }
    sort($open);
    $orders[$gid] = ['note' => $o['note'], 'tags' => $o['tags'], 'metafields' => $metafields, 'open' => $open];
}
$calls = array_map(fn(array $c): array => ['operation' => $c['operation'], 'roots' => $c['roots']], array_slice($store->calls, $sent));
echo json_encode(['results' => $results, 'calls' => $calls, 'orders' => $orders, 'locations' => $store->locations($domain)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
