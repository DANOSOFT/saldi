<?php
// Child runner for PosCreateCustomerSyncTest: runs the till's customer creation (K31) on a test
// tenant through the same bootstrap as the Shopify worker.
//
// usage: php run_pos_create_customer.php <test_db> '<steps json>'
// steps: ["mode"], ["create", fields, token], ["hold", fields, token, seconds] (creates inside an
// outer transaction and keeps it, with the debtor-number lock, open for the given seconds)
// prints JSON: {"results": [...]}, an exception as {"error": class, "message": text}

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_pos_create_customer.php <test_db> '<steps json>'\n");
    exit(2);
}
$testDb = $argv[1];
$steps = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);

require_once __DIR__ . '/ShopifyEnv.php';
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
require_once ShopifyEnv::repoRoot() . '/includes/std_func.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/config.php';
require_once ShopifyEnv::repoRoot() . '/debitor/pos_ordre_includes/createCustomer.php';

$results = [];
shopify_bootstrap($testDb);
foreach ($steps as $step) {
    try {
        switch ($step[0]) {
            case 'mode':
                $results[] = pos_customer_create_shopify_mode();
                break;
            case 'create':
                $results[] = pos_create_customer($step[1], $step[2]);
                break;
            case 'hold':
                $results[] = shopify_db_transaction(function () use ($step) {
                    $customer = pos_create_customer($step[1], $step[2]);
                    usleep((int)($step[3] * 1000000));
                    return $customer;
                });
                break;
            default:
                throw new RuntimeException("unknown step $step[0]");
        }
    } catch (Throwable $e) {
        $results[] = ['error' => get_class($e), 'message' => $e->getMessage()];
    }
}
echo json_encode(['results' => $results]);
