<?php
// tests/shopify/support/run_customers_out.php
//
// Child-process runner for outbound customers (SD-803): bootstraps the tenant and runs a list of
// steps against a FakeShopifyStore whose state lives in a JSON file, so a test can edit the tenant
// between runs and the shops keep their customers.
//   php run_customers_out.php <test_db> <state file> '<steps json>'
// Steps: ["enqueue", shop_key, adresser_id], ["job", shop_key, adresser_id] (as the worker runs customer.export),
//        ["job_lose", shop_key, adresser_id, n, search_failures] (the n-th customerCreate's answer is lost, then the customer search fails),
//        ["job_edit", shop_key, adresser_id, sql] (the debtor is edited with sql, $1 = adresser_id, and queued again while Shopify answers the create),
//        ["remote", shop_key, input] (a customer made in Shopify itself), ["fail", root_field, mode],
//        ["echo", shop_key, adresser_id] (the CUSTOMERS_CREATE webhook of the debtor's customer, found by its link or else its tag, run through the inbound job),
//        ["input", debtor] (the customerCreate input of a debtor row).
// Prints JSON: one result per step (or its exception), the operations sent and the shops' customers.
//
// History:
// 20261108 CL/SS SD-803: created.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_customers_out.php <test_db> <state file> '<steps json>'\n");
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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/customersOut.php';
require_once ShopifyEnv::repoRoot() . '/includes/std_func.php';

$store = is_file($stateFile) && filesize($stateFile) > 0 ? FakeShopifyStore::fromState(json_decode((string)file_get_contents($stateFile), true)) : new FakeShopifyStore();
$sent = count($store->calls);
$shopId = fn(string $key): int => (int)shopify_shop($key)['id'];
$domain = function (string $key) use ($store): string {
    $d = "$key-fake.myshopify.com";
    try {
        $store->shopRow($d);
    } catch (LogicException $e) {
        $store->addShop($key);
    }
    return $d;
};
$results = [];
try {
    shopify_bootstrap($testDb);
    foreach ($steps as $step) {
        try {
            switch ($step[0]) {
                case 'enqueue':
                    $domain($step[1]);
                    $results[] = shopify_customer_export_enqueue($shopId($step[1]), (int)$step[2]);
                    break;
                case 'job':
                    $domain($step[1]);
                    $job = ['id' => 0, 'type' => 'customer.export', 'shop_id' => $shopId($step[1]), 'payload' => ['adresser_id' => (int)$step[2]]];
                    $results[] = shopify_customer_export_job($job, microtime(true) + 50, fn(): bool => true, ['graphql' => $store->opts()]);
                    break;
                case 'job_lose':
                    // the n-th customerCreate is applied but its answer is lost; the customer search fails $step[4] times after it
                    $domain($step[1]);
                    $creates = 0;
                    $searchFails = 0;
                    $opts = $store->opts(['transport' => function (string $url, array $headers, string $body, float $connect, float $timeout) use ($store, $step, &$creates, &$searchFails): array {
                        if (str_contains($body, 'customers(') && $searchFails > 0) {
                            $searchFails--;
                            return FakeShopify::json(['errors' => 'Internal error'], 500);
                        }
                        $resp = $store->send($url, $headers, $body, $connect, $timeout);
                        if (str_contains($body, 'customerCreate(') && ++$creates === (int)$step[3]) {
                            $searchFails = (int)($step[4] ?? 0);
                            return FakeShopify::timeout();
                        }
                        return $resp;
                    }]);
                    $job = ['id' => 0, 'type' => 'customer.export', 'shop_id' => $shopId($step[1]), 'payload' => ['adresser_id' => (int)$step[2]]];
                    $results[] = shopify_customer_export_job($job, microtime(true) + 50, fn(): bool => true, ['graphql' => $opts]);
                    break;
                case 'job_edit':
                    // while Shopify answers the first customerCreate, $step[3] (SQL) runs on another connection, then the debtor is queued again
                    $domain($step[1]);
                    $edited = false;
                    $opts = $store->opts(['transport' => function (string $url, array $headers, string $body, float $connect, float $timeout) use ($store, $step, $testDb, $shopId, &$edited): array {
                        $resp = $store->send($url, $headers, $body, $connect, $timeout);
                        if (!$edited && str_contains($body, 'customerCreate(')) {
                            $edited = true;
                            $other = pg_connect(sprintf('host=%s user=%s password=%s dbname=%s', ShopifyEnv::pgHost(), ShopifyEnv::pgUser(), ShopifyEnv::pgPass(), $testDb), PGSQL_CONNECT_FORCE_NEW);
                            pg_query_params($other, $step[3], [(int)$step[2]]);
                            pg_close($other);
                            shopify_customer_export_enqueue($shopId($step[1]), (int)$step[2]);
                        }
                        return $resp;
                    }]);
                    $job = ['id' => 0, 'type' => 'customer.export', 'shop_id' => $shopId($step[1]), 'payload' => ['adresser_id' => (int)$step[2]]];
                    $results[] = shopify_customer_export_job($job, microtime(true) + 50, fn(): bool => true, ['graphql' => $opts]);
                    break;
                case 'remote':
                    $shop = shopify_shop($step[1]) + $store->shopRow($domain($step[1]));
                    $res = shopify_graphql($shop, 'customerCreate', SHOPIFY_CUSTOMER_EXPORT_CREATE, ['input' => $step[2]], $store->opts(['retry_mode' => 'reconcile',
                        'reconcile' => fn(): array => ['state' => 'unknown']]));
                    $results[] = $res['data']['customerCreate']['customer']['id'] ?? $res['error'];
                    break;
                case 'fail':
                    $store->fail[$step[1]] = $step[2];
                    $results[] = 'armed';
                    break;
                case 'echo':
                    $shop = shopify_shop($step[1]) + $store->shopRow($domain($step[1]));
                    $gid = shopify_customer_link_for_adresser($shopId($step[1]), (int)$step[2])['customer_gid']
                        ?? shopify_customer_export_find(['graphql' => $store->opts()], $shop, shopify_customer_export_tag((int)$step[2]), [])['id'];
                    $c = shopify_graphql($shop, 'customer', 'query customer($id: ID!) { customer(id: $id) { ' . SHOPIFY_CUSTOMER_EXPORT_FIELDS . ' } }', ['id' => $gid], $store->opts())['data']['customer'];
                    $body = ['id' => (int)preg_replace('#\D#', '', $gid), 'admin_graphql_api_id' => $gid, 'first_name' => $c['firstName'], 'last_name' => $c['lastName'],
                        'email' => $c['defaultEmailAddress']['emailAddress'] ?? null, 'phone' => $c['defaultPhoneNumber']['phoneNumber'] ?? null, 'tags' => implode(', ', $c['tags']), 'updated_at' => $c['updatedAt'], 'addresses' => [], 'default_address' => null];
                    $eventId = 'ev-' . bin2hex(random_bytes(6));
                    $row = shopify_db_rows("INSERT INTO shopify_events (shop_id, topic, event_id, payload_b64, state) VALUES ($1, 'CUSTOMERS_CREATE', $2, $3, 'queued') RETURNING id",
                        [$shopId($step[1]), $eventId, base64_encode(json_encode($body))]);
                    $results[] = shopify_customer_upsert_job(['id' => 0, 'type' => 'customer.upsert', 'shop_id' => $shopId($step[1]),
                        'payload' => ['event_row_id' => (int)$row[0]['id'], 'topic' => 'CUSTOMERS_CREATE', 'event_id' => $eventId]], microtime(true) + 50, fn(): bool => true);
                    break;
                case 'input':
                    $results[] = shopify_customer_export_input($step[1], 'saldi-deb-test-1');
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
$customers = [];
foreach ($state['shops'] as $s) {
    foreach ($s['customers'] as $gid => $c) {
        $customers[$s['key']][] = ['gid' => $gid, 'first_name' => $c['first_name'], 'last_name' => $c['last_name'], 'email' => $c['email'], 'phone' => $c['phone'], 'tags' => $c['tags']];
    }
}
$calls = array_map(fn(array $c): array => ['shop' => substr($c['shop'], 0, strpos($c['shop'], '-')), 'operation' => $c['operation'], 'variables' => $c['variables'], 'status' => $c['status']],
    array_slice($store->calls, $sent));
echo json_encode(['results' => $results, 'calls' => $calls, 'customers' => $customers], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
