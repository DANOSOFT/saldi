<?php
// tests/shopify/support/run_credits.php
//
// Child-process runner for the credit notes on web orders (SD-834): bootstraps the tenant and runs a list
// of steps against a FakeShopifyStore whose state lives in a JSON file, so a test can edit the tenant
// between runs and the shop keeps its orders.
//   php run_credits.php <test_db> <state file> '<steps json>'
// Steps: ["order"] (an order in the shop, its GID is the result),
//        ["scan"] (job credit.scan for the shop hml),
//        ["pass"] (one worker pass with the credit handlers, as tools/shopify_worker.php wires them),
//        ["reconcile", order_gid] (shopify_reconcile_refunds() for the shop hml),
//        ["untag", order_gid, tag] (staff remove a tag in Shopify).
// Prints JSON: one result per step (or its exception), the operations sent and the shop's orders.
//
// History:
// 20261125 CL/SS SD-834: created.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_credits.php <test_db> <state file> '<steps json>'\n");
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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/credits.php';
require_once ShopifyEnv::repoRoot() . '/includes/std_func.php';

const RUN_CREDITS_ORDER = 'mutation orderCreate($order: OrderCreateOrderInput!) { orderCreate(order: $order) { order { id } userErrors { message } } }';

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
    $shopId = (int)shopify_shop('hml')['id'];
    foreach ($steps as $step) {
        try {
            switch ($step[0]) {
                case 'order':
                    $item = $store->seedProduct($domain, 'stol-' . count($store->calls), 'SD834-' . count($store->calls));
                    $vars = ['order' => ['lineItems' => [['variantId' => $item['variant'], 'quantity' => 2]], 'note' => 'Levering til døren']];
                    $results[] = json_decode($store->request($domain, RUN_CREDITS_ORDER, $vars)['body'], true)['data']['orderCreate']['order']['id'];
                    break;
                case 'scan':
                    $results[] = shopify_credit_scan(['id' => 0, 'type' => 'credit.scan', 'shop_id' => $shopId, 'payload' => []], microtime(true) + 50, fn(): bool => true);
                    break;
                case 'pass':
                    $registry = [];
                    shopify_worker_register($registry, 'credit.publish', fn(array $job, float $deadline, callable $heartbeat): array
                        => shopify_publish_credit($job, $deadline, $heartbeat, ['graphql' => $store->opts()]));
                    shopify_worker_register($registry, 'credit.scan', 'shopify_credit_scan');
                    $summary = shopify_worker_run(['db' => $testDb, 'shops' => [], 'heartbeat_file' => $stateFile . '.heartbeat'], $registry, [], 50);
                    $results[] = array_map(fn(array $j): array => [$j['type'], $j['result']], $summary['jobs']);
                    break;
                case 'reconcile':
                    $results[] = shopify_reconcile_refunds($shopId, (string)$step[1]);
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
