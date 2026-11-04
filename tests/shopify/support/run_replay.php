<?php
// tests/shopify/support/run_replay.php
//
// Child-process runner for the NF-1 offline replay (SD-766, ReplayDayTest). Bootstraps the test
// tenant, gives the shop a random webhook secret, reads ReplayDay::TABLES as the baseline, then
// replays tests/shopify/fixtures/day.json in process (signed at run time), drains the worker with
// ReplayDay::registry() against the store in graphql.json, snapshots ReplayDay::SNAPSHOT and
// reads TABLES again. The second replay sends the day again, shuffled and with retries, sets every
// promoted event back to received so each job runs again, drains, snapshots and reads once more.
//   php run_replay.php <test_db> replay   prints JSON: answers, drains, first and second snapshot, the TABLES changes of each replay
//   php run_replay.php <test_db> record   the same, and writes the first snapshot and changes to expected.json
//   php run_replay.php <test_db> http     the day once over HTTP (php -S with webhook_router.php), drained: the snapshot and changes
//   php run_replay.php <test_db> relink   no replay: a product link moved to another item and a pending export to another debtor,
//                                         each in a transaction rolled back; the TABLES changes each read sees (stored, portable)
//
// History:
// 20261103 CL/LH SD-766: created.
// 20261103 CL/LH SD-766: review fixes: TABLES read before and after each replay, their changes recorded.
// 20261103 CL/LH SD-766: second review: the second replay compares TABLES as stored, ids and references too; expected.json
//                keeps the portable read; relink mode.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_replay.php <test_db> <replay|record|http|relink>\n");
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

// the receiver logs every duplicate (error_log): kept off stderr, which the parent reads only at the end
$log = (string)tempnam(sys_get_temp_dir(), 'sd766-replay-log');
ini_set('error_log', $log);
try {
    shopify_bootstrap($testDb);
    $fixtures = ReplayDay::load();
    if ($fixtures['day'] === null || $fixtures['graphql'] === null) {
        throw new RuntimeException('tests/shopify/fixtures/day.json or graphql.json is missing: run php tests/shopify/support/build_replay_day.php');
    }
    ReplayDay::prepareTenant();
    if ($mode === 'relink') {
        $cases = [
            'product_link' => ["INSERT INTO varer (varenr, beskrivelse) VALUES ('SD766-A', 'Relink A'), ('SD766-B', 'Relink B')",
                "INSERT INTO shopify_product_links (shop_id, vare_id, product_gid, variant_gid) SELECT s.id, v.id, 'gid://shopify/Product/9001', 'gid://shopify/ProductVariant/9002'
                    FROM shopify_shops s, varer v WHERE v.varenr = 'SD766-A'",
                "UPDATE shopify_product_links SET vare_id = (SELECT id FROM varer WHERE varenr = 'SD766-B')"],
            'customer_export' => ["INSERT INTO adresser (art, kontonr, firmanavn) VALUES ('D', 'SD766-A', 'Relink A'), ('D', 'SD766-B', 'Relink B')",
                "INSERT INTO shopify_customer_exports (shop_id, adresser_id, state) SELECT s.id, a.id, 'pending' FROM shopify_shops s, adresser a WHERE a.kontonr = 'SD766-A'",
                "UPDATE shopify_customer_exports SET adresser_id = (SELECT id FROM adresser WHERE kontonr = 'SD766-B')"],
        ];
        $out = [];
        foreach ($cases as $case => [$rows, $link, $move]) {
            shopify_db_rows('BEGIN');
            try {
                shopify_db_rows($rows);
                shopify_db_rows($link);
                [$stored, $portable] = [ReplayDay::tables(), ReplayDay::tables(true)];
                shopify_db_rows($move);
                $out[$case] = ['stored' => ReplayDay::changes($stored, ReplayDay::tables()), 'portable' => ReplayDay::changes($portable, ReplayDay::tables(true))];
            } finally {
                shopify_db_rows('ROLLBACK');
            }
        }
        echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit(0);
    }
    $store = FakeShopifyStore::fromState($fixtures['graphql']);
    $graphql = $store->opts();
    $deliveries = $fixtures['day']['deliveries'];
    $baseline = ReplayDay::tables(true);
    if ($mode === 'http') {
        $server = WebhookReplayer::startServer(['SALDI_SHOPIFY_DB' => $testDb]);
        try {
            $answers = WebhookReplayer::forShop(shopify_shop(ReplayDay::SHOP['shop_key']), $server['url'])->replay($deliveries);
        } finally {
            WebhookReplayer::stopServer($server);
        }
        echo json_encode(['answers1' => $answers, 'drain1' => ReplayDay::drain($testDb, $graphql), 'first' => ReplayDay::snapshot(),
            'changes1' => ReplayDay::changes($baseline, ReplayDay::tables(true))], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit(0);
    }
    $replayer = WebhookReplayer::forShop(shopify_shop(ReplayDay::SHOP['shop_key']));

    $answers1 = $replayer->replay($deliveries);
    $drain1 = ReplayDay::drain($testDb, $graphql);
    $first = ReplayDay::snapshot();
    $changes1 = ReplayDay::changes($baseline, ReplayDay::tables(true));
    $tables1 = ReplayDay::tables();

    $again = WebhookReplayer::arrange(WebhookReplayer::arrange($deliveries, 'shuffled', ReplayDay::SEED), 'duplicated', ReplayDay::SEED);
    $answers2 = $replayer->replay($again);
    $reset = count(shopify_db_rows("UPDATE shopify_events SET state = 'received', processed_at = NULL, job_id = NULL WHERE state IN ('queued', 'ignored') RETURNING id"));
    $drain2 = ReplayDay::drain($testDb, $graphql);
    $second = ReplayDay::snapshot();
    $changes2 = ReplayDay::changes($tables1, ReplayDay::tables());

    if ($mode === 'record') {
        file_put_contents(ReplayDay::FIXTURES . '/expected.json', ReplayDay::encode(['about' => 'ReplayDay::SNAPSHOT after one replay of day.json, and changes: '
            . 'the ReplayDay::TABLES rows it added and removed (SD-766), written by run_replay.php record.'] + $first + ['changes' => $changes1]));
    }
    echo json_encode(['answers1' => $answers1, 'drain1' => $drain1, 'answers2' => $answers2, 'reset' => $reset, 'drain2' => $drain2,
        'log_lines' => count(file($log)), 'first' => $first, 'second' => $second, 'changes1' => $changes1, 'changes2' => $changes2], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'class' => get_class($e), 'at' => $e->getFile() . ':' . $e->getLine(), 'log' => array_slice(file($log), -5)]);
    exit(1);
} finally {
    @unlink($log);
}
