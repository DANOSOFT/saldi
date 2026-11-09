<?php
// tests/shopify/support/run_intake.php
//
// Child-process runner for the paid-order intake's database parts (SD-742): bootstraps the tenant
// and runs a list of steps.
//   php run_intake.php <test_db> '<steps json>'
// Steps: ["context", shop_key, order]                 shopify_intake_context()
//        ["validate", shop_key, order]                context, then shopify_validate_paid_order()
//        ["report", shop_key, order]                  validate, then shopify_intake_report() in a transaction
//        ["preflight", ordre_id]                      shopify_preflight_delivery()
//        ["legacy", {name: value}, name]              shopify_with_legacy_context(): the global during and after
// Prints JSON: one result per step (or its exception).
//
// History:
// 20261103 CL/SS SD-742: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_intake.php <test_db> '<steps json>'\n");
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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/orderIntake.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/legacyBridge.php';

$results = [];
try {
    shopify_bootstrap($testDb);
    foreach ($steps as $step) {
        try {
            switch ($step[0]) {
                case 'context':
                    $results[] = shopify_intake_context(shopify_shop($step[1]), $step[2]);
                    break;
                case 'validate':
                    $shop = shopify_shop($step[1]);
                    $results[] = shopify_validate_paid_order($shop, $step[2], shopify_intake_context($shop, $step[2]));
                    break;
                case 'report':
                    $shop = shopify_shop($step[1]);
                    $v = shopify_validate_paid_order($shop, $step[2], shopify_intake_context($shop, $step[2]));
                    shopify_db_transaction(fn() => shopify_intake_report((int)$shop['id'], $v, ['order_gid' => $step[2]['gid']]));
                    $results[] = ['ok' => $v['ok']];
                    break;
                case 'preflight':
                    shopify_preflight_delivery((int)$step[1]);
                    $results[] = 'ok';
                    break;
                case 'legacy':
                    $name = $step[2];
                    $during = shopify_with_legacy_context($step[1], fn() => $GLOBALS[$name] ?? null);
                    $results[] = ['during' => $during, 'after' => array_key_exists($name, $GLOBALS) ? $GLOBALS[$name] : '(unset)'];
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
echo json_encode(['results' => $results], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
