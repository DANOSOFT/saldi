<?php
// tests/shopify/support/run_customers.php
//
// Child-process runner for inbound customers and create_debtor (SD-792): bootstraps the tenant and
// runs a list of steps, each in its own transaction unless it says otherwise.
//   php run_customers.php <test_db> '<steps json>'
// Steps: ["job", shop_key, topic, customer] (stored as an event, run as the worker runs the job),
//        ["hold", shop_key, customer, seconds] (ensure_customer, then the transaction stays open),
//        ["number"], ["number_outside"], ["create_debtor", kontonr, grp, betalingsbet, headless, bynavn, webservice] (no transaction, as the order page).
// Prints JSON: one result per step (or its exception).
//
// History:
// 20261101 CL/SS SD-792: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_customers.php <test_db> '<steps json>'\n");
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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/customers.php';
require_once ShopifyEnv::repoRoot() . '/includes/std_func.php';

$shopId = fn(string $key): int => (int)shopify_shop($key)['id'];
$results = [];
try {
    shopify_bootstrap($testDb);
    foreach ($steps as $step) {
        try {
            switch ($step[0]) {
                case 'job':
                    $eventId = 'ev-' . bin2hex(random_bytes(6));
                    $row = shopify_db_rows("INSERT INTO shopify_events (shop_id, topic, event_id, payload_b64, state) VALUES ($1, $2, $3, $4, 'queued') RETURNING id",
                        [$shopId($step[1]), $step[2], $eventId, base64_encode(json_encode($step[3]))]);
                    $job = ['id' => 0, 'type' => 'customer.upsert', 'shop_id' => $shopId($step[1]), 'payload' => ['event_row_id' => (int)$row[0]['id'], 'topic' => $step[2], 'event_id' => $eventId]];
                    $results[] = shopify_customer_upsert_job($job, microtime(true) + 50, fn(): bool => true);
                    break;
                case 'hold':
                    $results[] = shopify_db_transaction(function () use ($step, $shopId) {
                        $id = ensure_customer($shopId($step[1]), $step[2]);
                        usleep((int)($step[3] * 1000000));
                        return $id;
                    });
                    break;
                case 'number':
                    $results[] = shopify_db_transaction(fn() => shopify_customer_number());
                    break;
                case 'number_outside':
                    $results[] = shopify_customer_number();
                    break;
                case 'create_debtor':
                    $webservice = $step[6] ?? true; // false: as a CLI caller without the bootstrap's webservice mode
                    $id = create_debtor($step[1], 'SD792 Kunde', 'Vej 1', '', '2750', $step[5] ?? 'Ballerup', 'k@example.dk', '', '', $step[2], '', $step[3], 8, '', $step[4] ?? false);
                    $results[] = ['id' => $id, 'idle' => pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE];
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
echo json_encode(['results' => $results], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
