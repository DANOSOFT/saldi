<?php
// tests/shopify/support/run_status.php
//
// Child-process runner for inbound order status (SD-801): bootstraps the tenant and runs a list
// of steps against the events the test stored in shopify_events.
//   php run_status.php <test_db> '<steps json>'
// Steps: ["promote"] (received events become jobs, as the worker's events producer does),
//        ["work"] (one worker pass with the status handlers as tools/shopify_worker.php wires them, no producers),
//        ["read_model", ordre_id], ["debtor_model", adresser_id].
// Prints JSON: one result per step (or its exception).
//
// History:
// 20261103 CL/SS SD-801: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_status.php <test_db> '<steps json>'\n");
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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/worker.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/statusIn.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/readModels.php';

$registry = [];
shopify_worker_register($registry, 'fulfillment.upsert', 'shopify_fulfillment_upsert_job');
shopify_worker_register($registry, 'order.status', 'shopify_order_status_job');
shopify_worker_register($registry, 'order.cancelled', 'shopify_order_cancelled_job');
shopify_worker_register($registry, 'refund.upsert', 'shopify_refund_upsert_job');
$heartbeat = sys_get_temp_dir() . '/sd801-' . getmypid() . '.heartbeat';

$results = [];
try {
    shopify_bootstrap($testDb);
    foreach ($steps as $step) {
        try {
            switch ($step[0]) {
                case 'promote':
                    $results[] = array_intersect_key(shopify_promote_events(), array_flip(['queued', 'ignored', 'error']));
                    break;
                case 'work':
                    $run = shopify_worker_run(['db' => $testDb, 'heartbeat_file' => $heartbeat], $registry, [], 30);
                    $results[] = array_intersect_key($run, array_flip(['claimed', 'done', 'yielded', 'failed', 'dead', 'lost', 'jobs']));
                    break;
                case 'read_model':
                    $results[] = shopify_order_read_model((int)$step[1]);
                    break;
                case 'debtor_model':
                    $results[] = shopify_debtor_read_model((int)$step[1]);
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
} finally {
    @unlink($heartbeat);
}
echo json_encode(['results' => $results], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
