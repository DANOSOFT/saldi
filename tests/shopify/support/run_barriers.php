<?php
// tests/shopify/support/run_barriers.php
//
// Child-process runner for the move barriers (SD-755). Boots the tenant session-less with
// shopify_bootstrap(), as the worker does, and runs the calls given as JSON, e.g.
// [["open", 1, 50, [10, 11]], ["release", 1, 50], ["expire", 1], ["expire", 1, 50], ["deferred", 1, [10]]].
// open_rollback opens inside a transaction that then rolls back, as a failed order creation does.
//
// Usage: php run_barriers.php <test_db> <calls json>
//
// Prints a JSON list with one entry per call: result, or error (class and message).
//
// History:
// 20261102 CL/SZ SD-755: created.
// 20261103 CL/SZ SD-755: split_lager runs shopify_split_set_lager_checked().

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_barriers.php <test_db> <calls json>\n");
    exit(2);
}
$testDb = $argv[1];
$calls = json_decode($argv[2], true);

require_once __DIR__ . '/ShopifyEnv.php';
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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/stock.php';

shopify_bootstrap($testDb);
$out = array();
foreach ($calls as $call) {
    try {
        switch ($call[0]) {
            case 'open':
                $out[] = array('result' => shopify_barrier_open((int)$call[1], (int)$call[2], $call[3]));
                break;
            case 'open_rollback':
                try {
                    shopify_db_transaction(function () use ($call): void {
                        shopify_barrier_open((int)$call[1], (int)$call[2], $call[3]);
                        throw new RuntimeException('order creation failed');
                    });
                } catch (RuntimeException $e) {
                    $out[] = array('result' => $e->getMessage());
                }
                break;
            case 'release':
                $out[] = array('result' => shopify_barrier_release((int)$call[1], (int)$call[2]));
                break;
            case 'expire':
                $out[] = array('result' => isset($call[2]) ? shopify_barrier_expire((int)$call[1], (int)$call[2]) : shopify_barrier_expire((int)$call[1]));
                break;
            case 'deferred':
                $out[] = array('result' => shopify_barrier_deferred((int)$call[1], $call[2]));
                break;
            case 'split_lager':
                $out[] = array('result' => shopify_split_set_lager_checked($call[1], $call[2]));
                break;
            default:
                throw new InvalidArgumentException("unknown call {$call[0]}");
        }
    } catch (Throwable $e) {
        $out[] = array('error' => array('class' => get_class($e), 'message' => $e->getMessage()));
    }
}
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
