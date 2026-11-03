<?php
// tests/shopify/support/run_replay.php
//
// Child-process runner for the NF-1 offline replay (SD-766, ReplayDayTest). Bootstraps the test
// tenant, gives the shop a random webhook secret, then replays tests/shopify/fixtures/day.json
// in process (signed at run time), drains the worker with ReplayDay::registry() against the
// store in graphql.json and snapshots ReplayDay::SNAPSHOT. The second replay sends the day again,
// shuffled and with retries, sets every promoted event back to received so each job runs again,
// drains and snapshots once more.
//   php run_replay.php <test_db> replay   prints JSON: answers, drains, first and second snapshot
//   php run_replay.php <test_db> record   the same, and writes the first snapshot to expected.json
//
// History:
// 20261103 CL/LH SD-766: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_replay.php <test_db> <replay|record>\n");
    exit(2);
}
$testDb = $argv[1];
$mode = $argv[2];

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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/events.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/customers.php';
require_once __DIR__ . '/ReplayDay.php';

/** A clean shop on the tenant: its Shopify rows gone, the customers flow on, a fresh webhook secret. */
function replay_prepare(): void
{
    // a fresh install has adresser.mobile (includes/opretfunc.php); an old template may not
    shopify_db_rows('ALTER TABLE adresser ADD COLUMN IF NOT EXISTS mobile varchar(15)');
    foreach (['shopify_customer_links', 'shopify_customer_exports', 'shopify_refund_allocations', 'shopify_refunds', 'shopify_fulfillments', 'shopify_order_links',
        'shopify_stock_state', 'shopify_product_links', 'shopify_events', 'shopify_jobs', 'shopify_errors', 'shopify_shops'] as $table) {
        shopify_db_rows("DELETE FROM $table");
    }
    shopify_db_rows("DELETE FROM adresser WHERE btrim(kontonr) ~ '^29766[0-9]{3}$'");
    shopify_db_rows("INSERT INTO grupper (beskrivelse, kodenr, art) SELECT 'SD766 Shopify', 76, 'DG' WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art = 'DG' AND kodenr = 76)");
    shopify_db_rows("DELETE FROM settings WHERE (var_grp = 'shopify' AND var_name LIKE 'customer_%') OR var_grp = 'shopify:hml'");
    shopify_db_rows("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify', 'customer_number_start', '29766000'),
        ('shopify', 'customer_group', '76'), ('shopify', 'customer_payment_terms', 'Kontant'), ('shopify', 'customer_payment_days', '0')");
    shopify_db_rows('INSERT INTO shopify_shops (shop_key, domain, is_primary, orders_enabled, customers_enabled) VALUES ($1, $2, true, true, true)',
        [ReplayDay::SHOP['shop_key'], ReplayDay::SHOP['domain']]);
    shopify_db_rows("INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('shopify:hml', $1, $2, '')",
        [SHOPIFY_WEBHOOK_SECRET, bin2hex(random_bytes(16))]);
}

// the receiver logs every duplicate (error_log): kept off stderr, which the parent reads only at the end
$log = (string)tempnam(sys_get_temp_dir(), 'sd766-replay-log');
ini_set('error_log', $log);
try {
    shopify_bootstrap($testDb);
    $fixtures = ReplayDay::load();
    if ($fixtures['day'] === null || $fixtures['graphql'] === null) {
        throw new RuntimeException('tests/shopify/fixtures/day.json or graphql.json is missing: run php tests/shopify/support/build_replay_day.php');
    }
    replay_prepare();
    $store = FakeShopifyStore::fromState($fixtures['graphql']);
    $graphql = $store->opts();
    $replayer = WebhookReplayer::forShop(shopify_shop(ReplayDay::SHOP['shop_key']));
    $deliveries = $fixtures['day']['deliveries'];

    $answers1 = $replayer->replay($deliveries);
    $drain1 = ReplayDay::drain($testDb, $graphql);
    $first = ReplayDay::snapshot();

    $again = WebhookReplayer::arrange(WebhookReplayer::arrange($deliveries, 'shuffled', ReplayDay::SEED), 'duplicated', ReplayDay::SEED);
    $answers2 = $replayer->replay($again);
    $reset = count(shopify_db_rows("UPDATE shopify_events SET state = 'received', processed_at = NULL, job_id = NULL WHERE state IN ('queued', 'ignored') RETURNING id"));
    $drain2 = ReplayDay::drain($testDb, $graphql);
    $second = ReplayDay::snapshot();

    if ($mode === 'record') {
        file_put_contents(ReplayDay::FIXTURES . '/expected.json', ReplayDay::encode(['about' => 'ReplayDay::SNAPSHOT after one replay of day.json (SD-766), written by run_replay.php record.'] + $first));
    }
    echo json_encode(['answers1' => $answers1, 'drain1' => $drain1, 'answers2' => $answers2, 'reset' => $reset, 'drain2' => $drain2,
        'log_lines' => count(file($log)), 'first' => $first, 'second' => $second], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'class' => get_class($e), 'at' => $e->getFile() . ':' . $e->getLine(), 'log' => array_slice(file($log), -5)]);
    exit(1);
} finally {
    @unlink($log);
}
