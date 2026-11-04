<?php
// tests/shopify/support/run_import.php
//
// Child-process runner for the paid-order import (SD-743): bootstraps the tenant and runs one
// order.import job with the order given in place of the Shopify read.
//   php run_import.php <test_db> '<job json>' '<order json>' [crash stage] [hold seconds]
// crash stage: the process exits (code 9) when the import reaches it, as a killed worker would,
// with its transaction open. hold seconds: the run sleeps that long at stage 'reserved', so a
// second run can meet its lock.
// Prints JSON: {"result": <the handler's return>} or {"exception": class, "message": text}.
//
// History:
// 20261103 CL/SS SD-743: created.
// 20261104 CL/SS SD-743 review: test_link_state in the order reserves its link during the read.
// 20261104 CL/SS SD-743 review: test_sql_at_reserved in the order runs between the validation and the creation.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_import.php <test_db> '<job json>' '<order json>' [crash stage] [hold seconds]\n");
    exit(2);
}
$testDb = $argv[1];
$job = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
$order = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);
$crashAt = $argv[4] ?? '';
$hold = (int)($argv[5] ?? 0);

require_once __DIR__ . '/ShopifyEnv.php';
ShopifyEnv::assertSafeTestDb($testDb);
@mkdir(ShopifyEnv::repoRoot() . "/temp/$testDb", 0777, true); // the legacy posting writes its logs there, as the worker run does

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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/orderImport.php';

try {
    shopify_bootstrap($testDb);
    ob_start(); // legacy posting prints stray HTML on some branches
    $result = shopify_import_paid_order($job, [
        'fetch' => function (array $shop, string $gid) use ($order): array {
            if (isset($order['test_link_state'])) { // another worker reserves the link while this one validates
                shopify_reserve_order_link((int)$shop['id'], $gid, 'web', ['order_name' => $order['name'], 'state' => $order['test_link_state']]);
            }
            return $order;
        },
        'crash' => function (string $stage) use ($crashAt, $hold, $order): void {
            if ($stage === 'reserved' && isset($order['test_sql_at_reserved'])) { // the tenant changes after the validation
                shopify_db_rows($order['test_sql_at_reserved']);
            }
            if ($stage === 'reserved' && $hold > 0) {
                sleep($hold);
            }
            if ($stage === $crashAt) {
                ob_end_clean();
                exit(9);
            }
        },
    ]);
    ob_end_clean();
    echo json_encode(['result' => $result], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['exception' => get_class($e), 'message' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
