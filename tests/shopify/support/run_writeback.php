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
//        ["untag", order_gid, tag] (staff remove a tag in Shopify).
// Prints JSON: one result per step (or its exception), the operations sent and the shop's orders.
//
// History:
// 20261109 CL/MJ SD-759: created.

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
                    foreach (['order.tags', 'order.references', 'order.note', 'order.processed'] as $type) {
                        shopify_worker_register($registry, $type, fn(array $job, float $deadline, callable $heartbeat): array
                            => shopify_writeback_job($job, $deadline, $heartbeat, ['graphql' => $store->opts()]));
                    }
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
    $orders[$gid] = ['note' => $o['note'], 'tags' => $o['tags'], 'metafields' => $metafields];
}
$calls = array_map(fn(array $c): array => ['operation' => $c['operation'], 'roots' => $c['roots']], array_slice($store->calls, $sent));
echo json_encode(['results' => $results, 'calls' => $calls, 'orders' => $orders], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
