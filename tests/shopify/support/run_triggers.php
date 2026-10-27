<?php
// tests/shopify/support/run_triggers.php
//
// Child-process runner for the stock triggers (SD-736): bootstraps the tenant and saves a shop
// through shopify_save_shop() inside shopify_db_transaction(), the install path of the triggers.
//   save <test_db> [capture]   saves shop 'hml'; 'capture' also sets stock_capture_enabled
// Prints JSON: the shop id and the number of Shopify stock triggers afterwards.
//
// History:
// 20261027 Sawaneh SD-736: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_triggers.php save <test_db> [capture]\n");
    exit(2);
}
$testDb = $argv[2];
$capture = ($argv[3] ?? '') === 'capture';

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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/config.php';

try {
    shopify_bootstrap($testDb);
    $id = shopify_db_transaction(fn() => shopify_save_shop(['shop_key' => 'hml', 'domain' => 'hml.myshopify.com', 'stock_capture_enabled' => $capture]));
    $triggers = (int)shopify_db_rows("SELECT count(*) AS n FROM pg_trigger WHERE tgname LIKE 'shopify_dirty_%'")[0]['n'];
    echo json_encode(['shop_id' => $id, 'triggers' => $triggers]);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'class' => get_class($e)]);
    exit(1);
}
