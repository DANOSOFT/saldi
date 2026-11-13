<?php
// Child process for StoreOrdersSyncTest: runs the steps given as JSON against a test tenant and a fake
// store whose state is kept in a file between runs, and prints the results, the calls and the orders.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_store_orders.php <test_db> <state file> '<steps json>'\n");
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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/storeOrders.php';
require_once ShopifyEnv::repoRoot() . '/includes/std_func.php';

$store = is_file($stateFile) && filesize($stateFile) > 0 ? FakeShopifyStore::fromState(json_decode((string)file_get_contents($stateFile), true)) : new FakeShopifyStore();
$sent = count($store->calls);
$domain = function (string $key) use ($store): string {
    $d = "$key-fake.myshopify.com";
    try {
        $store->shopRow($d);
    } catch (LogicException $e) {
        $store->addShop($key);
        $store->addLocation($d, 'Butik');
    }
    return $d;
};
$shopId = function (string $key) use ($domain): int {
    $domain($key);
    return (int)shopify_shop($key)['id'];
};
$job = fn(string $type, string $key, array $payload): array => ['id' => 0, 'type' => $type, 'shop_id' => $shopId($key), 'payload' => $payload];
$results = [];
try {
    shopify_bootstrap($testDb);
    shopify_store_orders_require();
    foreach ($steps as $step) {
        try {
            switch ($step[0]) {
                case 'product':
                    // [product, shop, vare_id, sku]: the item has a variant in the shop
                    $p = $store->seedProduct($domain($step[1]), strtolower($step[3]), $step[3]);
                    shopify_upsert_product_link($shopId($step[1]), (int)$step[2], 0, ['product_gid' => $p['product'], 'variant_gid' => $p['variant']]);
                    $results[] = $p['variant'];
                    break;
                case 'enqueue':
                    $results[] = shopify_store_orders_enqueue((int)$step[1], new DateTimeImmutable($step[2] ?? 'now'));
                    break;
                case 'job':
                    $results[] = shopify_store_orders_run($job('store.export', $step[1], ['ordre_id' => (int)$step[2]]), microtime(true) + 50, fn(): bool => true, ['graphql' => $store->opts()]);
                    break;
                case 'job_lose':
                    // [job_lose, shop, ordre_id, searches that fail after it]: orderCreate is applied but its answer is lost
                    $searchFails = 0;
                    $lost = false;
                    $opts = $store->opts(['transport' => function (string $url, array $headers, string $body, float $connect, float $timeout) use ($store, $step, &$searchFails, &$lost): array {
                        if (str_contains($body, 'orders(') && $searchFails > 0) {
                            $searchFails--;
                            return FakeShopify::json(['errors' => 'Internal error'], 500);
                        }
                        $resp = $store->send($url, $headers, $body, $connect, $timeout);
                        if (!$lost && str_contains($body, 'orderCreate(')) {
                            $lost = true;
                            $searchFails = (int)($step[3] ?? 0);
                            return FakeShopify::timeout();
                        }
                        return $resp;
                    }]);
                    $results[] = shopify_store_orders_run($job('store.export', $step[1], ['ordre_id' => (int)$step[2]]), microtime(true) + 50, fn(): bool => true, ['graphql' => $opts]);
                    break;
                case 'job_drop':
                    // orderCreate times out before it reaches Shopify
                    $opts = $store->opts(['transport' => function (string $url, array $headers, string $body, float $connect, float $timeout) use ($store): array {
                        return str_contains($body, 'orderCreate(') ? FakeShopify::timeout() : $store->send($url, $headers, $body, $connect, $timeout);
                    }]);
                    $results[] = shopify_store_orders_run($job('store.export', $step[1], ['ordre_id' => (int)$step[2]]), microtime(true) + 50, fn(): bool => true, ['graphql' => $opts]);
                    break;
                case 'customer':
                    $results[] = shopify_customer_export_job($job('customer.export', $step[1], ['adresser_id' => (int)$step[2]]), microtime(true) + 50, fn(): bool => true, ['graphql' => $store->opts()]);
                    break;
                case 'remote_customer':
                    $shop = shopify_shop($step[1]) + $store->shopRow($domain($step[1]));
                    $res = shopify_graphql($shop, 'customerCreate', SHOPIFY_CUSTOMER_EXPORT_CREATE, ['input' => $step[2]], $store->opts(['retry_mode' => 'reconcile',
                        'reconcile' => fn(): array => ['state' => 'unknown']]));
                    $results[] = $res['data']['customerCreate']['customer']['id'] ?? $res['error'];
                    break;
                case 'recover':
                    $results[] = shopify_store_orders_recover($job('store.recover', $step[1], ['slot' => 'test']), microtime(true) + 50, fn(): bool => true);
                    break;
                case 'echo':
                    shopify_store_orders_echo($shopId($step[1]), ['source_identifier' => "$testDb:{$step[2]}"], $testDb);
                    $results[] = 'echoed';
                    break;
                default:
                    throw new LogicException("unknown step {$step[0]}");
            }
        } catch (Throwable $e) {
            $results[] = ['exception' => get_class($e), 'message' => $e->getMessage(), 'idle' => pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE];
        }
    }
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'class' => get_class($e)]);
    exit(1);
}
$state = $store->exportState();
file_put_contents($stateFile, json_encode($state));
$orders = [];
$receipts = [];
foreach ($state['shops'] as $s) {
    foreach ($s['orders'] as $gid => $o) {
        $orders[$s['key']][] = ['gid' => $gid, 'name' => $o['name'], 'tags' => $o['tags'], 'customer' => $o['customer'], 'email' => $o['email'],
            'lines' => array_map(fn(array $l): array => [$l['variant'], $l['title'], $l['quantity'], $l['price']], $o['lines']),
            'paid' => array_sum(array_column($o['transactions'], 'amount')), 'financial' => $o['financial'], 'behaviour' => $o['inventory_behaviour'],
            'fulfilled' => $o['fulfillments'] !== []];
    }
    $receipts[$s['key']] = $s['receipts'];
}
$calls = array_map(fn(array $c): array => ['shop' => substr($c['shop'], 0, strpos($c['shop'], '-')), 'operation' => $c['operation'], 'status' => $c['status']],
    array_slice($store->calls, $sent));
echo json_encode(['results' => $results, 'calls' => $calls, 'orders' => $orders, 'receipts' => $receipts], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
