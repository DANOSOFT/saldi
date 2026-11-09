<?php
// tests/shopify/support/run_events.php
//
// Child-process runner for the webhook inbox and event promotion (SD-748). Bootstraps the
// tenant like run_config.php and runs one mode:
//   receive  <deliveries.json> [alarm_fails|real]  shopify_receive_webhook() for each {shop_key, raw_b64, headers}
//   promote  <limit> [fail_type|alarm_fails|alarm_fails_once|sql_error|misuse|real_queue|real]  shopify_promote_events();
//            enqueue of fail_type throws before writing; with sql_error the order.import enqueue
//            writes its job and then fails in SQL; with misuse every enqueue sends COMMIT itself;
//            with real_queue the enqueue is SD-737's own includes/shopify/queue.php
//   health   <base_url|-> [fail_create]  the webhook.health handler for shop hml against
//            FakeWebhookShop, which has every topic at the base URL but ORDERS_PAID (missing) and
//            REFUNDS_CREATE (at another install); stores the base URL first unless -; with
//            fail_create the ORDERS_PAID create answers a userError
// Prints JSON on stdout: the results, or the error that ended the run.
//
// This runner stands in for S02c's shopify_error_open() and S02b's queue, because the failure
// modes need an alarm and a queue that fail on cue. With real_queue the queue is SD-737's own;
// with real neither is a stand-in, and events.php loads S02c's includes/shopify/errors.php. Both stand-ins work on the real tables, so their writes commit and roll back
// with the caller's transaction: shopify_error_open() opens (or counts again) an unresolved row
// in shopify_errors, and shopify_queue_enqueue() inserts into shopify_jobs under the table's own
// unique index on (type, dedupe_key) for queued and running jobs, which is the contract's "an
// equal job still queued or running is returned with created=false". With alarm_fails every
// alarm throws; with alarm_fails_once only the first one does.
//
// History:
// 20261027 CL/SZ SD-748: created.
// 20261027 CL/SZ SD-748: real_queue runs promotion on SD-737's queue (#918) instead of the stand-in.
// 20261028 CL/SZ SD-748: no_alarm replaced by real, S02c's error list and SD-737's queue (#948).
// 20261028 CL/SZ SD-748 review: alarm_fails_once.
// 20261028 CL/SZ SD-749: mode health.
// 20261029 CL/SZ SD-749 review: health fail_create raises its alarm on S02c's real error list.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_events.php <receive|promote> <test_db> <deliveries.json|limit> [fail_type]\n");
    exit(2);
}
$mode = $argv[1];
$testDb = $argv[2];

require_once __DIR__ . '/ShopifyEnv.php';
ShopifyEnv::assertSafeTestDb($testDb);

function alert($msg)
{
    fwrite(STDERR, "alert: $msg\n");
    exit(3);
}

function out(array $data, int $exit = 0): void
{
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit($exit);
}

$failType = $argv[4] ?? '';

// With real and with health's fail_create neither stand-in is defined: events.php loads S02c's errors.php,
// which loads SD-737's queue.php.
$realErrorList = $failType === 'real' || $failType === 'fail_create';
if (!$realErrorList) {
function shopify_error_open(?int $shopId, string $kind, string $ref, string $message, string $severity = 'error', array $correlation = []): int
{
    global $failType;
    static $calls = 0;
    $calls++;
    if ($failType === 'alarm_fails' || ($failType === 'alarm_fails_once' && $calls === 1)) {
        throw new RuntimeException("alarm $kind failed (test)");
    }
    $rows = shopify_db_rows(
        "INSERT INTO shopify_errors (shop_id, error_key, kind, ref, message, severity, correlation) VALUES ($1, $2, $3, $4, $5, $6, $7)"
        . " ON CONFLICT (error_key) WHERE resolved_at IS NULL DO UPDATE SET occurrences = shopify_errors.occurrences + 1, last_at = now() RETURNING id",
        [$shopId, hash('sha256', "$kind|$ref"), $kind, $ref, $message, $severity, json_encode($correlation)]
    );
    return (int)$rows[0]['id'];
}
}

// With real_queue (or a real error list) the stand-in is not defined: queue.php is loaded instead.
if ($failType !== 'real_queue' && !$realErrorList) {
function shopify_queue_enqueue(string $type, ?int $shopId, string $dedupeKey, array $payload, int $priority, ?DateTimeImmutable $runAt = null, array $correlation = []): array
{
    global $failType;
    if ($type === $failType) {
        throw new RuntimeException("enqueue of $type failed (test)");
    }
    if ($failType === 'misuse') {
        // ends the transaction itself, the way a legacy transaktion('commit') would
        shopify_db_rows('COMMIT');
    }
    $rows = shopify_db_rows(
        "INSERT INTO shopify_jobs (type, shop_id, dedupe_key, payload, priority, correlation, event_row_id, order_gid) VALUES ($1, $2, $3, $4, $5, $6, $7, $8)"
        . " ON CONFLICT (type, dedupe_key) WHERE state IN ('queued', 'running') DO NOTHING RETURNING id, state",
        [$type, $shopId, $dedupeKey, json_encode($payload), $priority, json_encode($correlation), $correlation['event_row_id'] ?? null, $correlation['order_gid'] ?? null]
    );
    if ($failType === 'sql_error' && $type === 'order.import') {
        // the job row is written, then a statement fails: the transaction is aborted
        shopify_db_rows('SELECT 1 / 0');
    }
    if ($rows) {
        return ['id' => (int)$rows[0]['id'], 'created' => true, 'state' => $rows[0]['state']];
    }
    $rows = shopify_db_rows("SELECT id, state FROM shopify_jobs WHERE type = $1 AND dedupe_key = $2 AND state IN ('queued', 'running')", [$type, $dedupeKey]);
    return ['id' => (int)$rows[0]['id'], 'created' => false, 'state' => $rows[0]['state']];
}

// The real priority table: shopify_route_event() reads it, and queue.php itself can't be
// loaded beside the stand-in enqueue above.
$queueSource = (string)file_get_contents(ShopifyEnv::repoRoot() . '/includes/shopify/queue.php');
if (!preg_match('/^const SHOPIFY_QUEUE_PRIORITY = array\((.*?)^\);/ms', $queueSource, $m)) {
    fwrite(STDERR, "SHOPIFY_QUEUE_PRIORITY not found in queue.php\n");
    exit(2);
}
eval('const SHOPIFY_QUEUE_PRIORITY = array(' . $m[1] . ');');
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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/events.php';

// The receiver's log lines go to stderr, where the test can check that no secret is in them.
ini_set('log_errors', '1');
ini_set('error_log', 'php://stderr');

try {
    shopify_bootstrap($testDb);
    switch ($mode) {
        case 'receive':
            $results = [];
            foreach (json_decode((string)file_get_contents($argv[3]), true) as $delivery) {
                $shop = shopify_shop($delivery['shop_key']);
                $results[] = shopify_receive_webhook($shop, base64_decode($delivery['raw_b64']), $delivery['headers']);
            }
            out(['results' => $results]);
            // no break: out() exits
        case 'promote':
            out(['result' => shopify_promote_events((int)$argv[3])]);
            // no break: out() exits
        case 'health':
            require_once __DIR__ . '/FakeWebhookShop.php';
            if ($argv[3] !== '-') {
                shopify_save_webhook_base_url($argv[3]);
            }
            $store = new FakeWebhookShop();
            $base = shopify_webhook_base_url();
            foreach ($base === null ? [] : SHOPIFY_WEBHOOK_TOPICS as $topic) {
                if ($topic !== 'ORDERS_PAID') {
                    $store->add($topic, shopify_webhook_uri($topic === 'REFUNDS_CREATE' ? 'https://other-install.example.dk/sz' : $base, 'hml'));
                }
            }
            if ($failType === 'fail_create') {
                $store->fail['ORDERS_PAID'] = 'user_error';
            }
            $job = ['id' => 1, 'shop_id' => (int)shopify_shop('hml')['id']];
            $done = shopify_webhook_health_job($job, time() + 30, fn(): bool => true, $store->opts());
            out(['result' => $done, 'ops' => $store->ops(), 'topics' => $store->byTopic()]);
            // no break: out() exits
        default:
            fwrite(STDERR, "unknown mode $mode\n");
            exit(2);
    }
} catch (Throwable $e) {
    out(['error' => ['class' => get_class($e), 'message' => $e->getMessage()]], 1);
}
