<?php
// tests/shopify/support/run_authorized.php
//
// Child-process runner for an order whose card is authorized at checkout and captured at
// fulfilment (HML, option A), end to end on the shared fake store: the store creates the order
// AUTHORIZED (B41), its webhooks reach the tenant through WebhookReplayer twice over (Shopify's
// retries), and the worker promotes them and runs the import (reading the order from the store),
// the status jobs and nothing else. Then the shop captures the order (B42) and the day is drained
// again; a second order is authorized, imported and voided (B43).
//   php run_authorized.php <test_db> <sku> <lager>
// The tenant is OrderImportSyncTest's: shop hml with its payment, location and shipping maps, and
// an item <sku> stocked on <lager>, which the runner maps to the store's location.
// Prints JSON: {phases: {<name>: snapshot}, orders: [gid, gid]}; a snapshot holds the Saldi orders of
// the shop with their postings, the order links, the import jobs and the open errors.
//
// History:
// 20261124 CL/LH HML: created.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_authorized.php <test_db> <sku> <lager>\n");
    exit(2);
}
[, $testDb, $sku, $lager] = $argv;

require_once __DIR__ . '/ShopifyEnv.php';
require_once __DIR__ . '/FakeShopifyStore.php';
require_once __DIR__ . '/WebhookReplayer.php';
ShopifyEnv::assertSafeTestDb($testDb);
@mkdir(ShopifyEnv::repoRoot() . "/temp/$testDb", 0777, true);

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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/statusIn.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/orderImport.php';

const RUN_AUTHORIZED_ORDER = 'mutation orderCreate($order: OrderCreateOrderInput!, $options: OrderCreateOptionsInput) {
	orderCreate(order: $order, options: $options) { order { id } userErrors { field message } } }';

$log = (string)tempnam(sys_get_temp_dir(), 'hml-authorized-log');
ini_set('error_log', $log); // the receiver logs every duplicate; kept off the stderr pipe
try {
    shopify_bootstrap($testDb);
    $shopRow = shopify_shop('hml');
    $store = new FakeShopifyStore();
    $store->epoch = time() - 1000; // Saldi compares the authorization's expiry with the wall clock
    $domain = $store->addShop('hml', ['domain' => (string)$shopRow['domain'], 'locations' => ['Lager 1']])['domain'];
    $location = $store->locations($domain)[0];
    foreach (SHOPIFY_WEBHOOK_TOPICS as $topic) {
        $store->subscribe($domain, $topic, 'https://saldi.example/api/shopify/webhook.php?shop=hml');
    }
    shopify_db_rows('UPDATE shopify_location_map SET location_gid = $2 WHERE lager = $1', [(int)$lager, $location]);
    shopify_db_rows("DELETE FROM settings WHERE var_grp = 'shopify:hml' AND var_name = $1", [SHOPIFY_WEBHOOK_SECRET]);
    shopify_db_rows("INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('shopify:hml', $1, $2, '')", [SHOPIFY_WEBHOOK_SECRET, bin2hex(random_bytes(16))]);
    $variant = $store->seedProduct($domain, 'parasol', $sku, [$location => 10], ['price' => '125.00'])['variant'];
    $replayer = WebhookReplayer::forShop(shopify_shop('hml'));

    $graphql = $store->opts();
    $registry = [];
    shopify_worker_register($registry, 'order.import', fn(array $job, float $deadline, callable $heartbeat): array
        => shopify_import_paid_order($job, ['fetch' => fn(array $shop, string $gid): array => shopify_fetch_paid_order($shop, $gid, ['graphql' => $graphql])]));
    shopify_worker_register($registry, 'order.status', 'shopify_order_status_job');
    shopify_worker_register($registry, 'fulfillment.upsert', 'shopify_fulfillment_upsert_job');

    // the store's webhooks since the last call, each sent twice (a retry), then worker passes until nothing is left
    $day = function () use ($store, $replayer, $registry, $testDb): array {
        $deliveries = WebhookReplayer::fromOutbox($store->takeDeliveries());
        $answers = $replayer->replay(array_merge($deliveries, $deliveries));
        $jobs = [];
        for ($i = 0; $i < 20; $i++) {
            $run = shopify_worker_run(['db' => $testDb, 'shops' => [], 'heartbeat_file' => sys_get_temp_dir() . "/hml-authorized-$testDb.heartbeat"],
                $registry, ['events' => fn(): array => shopify_promote_events(100)], 30);
            foreach ($run['jobs'] as $j) {
                $jobs[] = [$j['type'], $j['result']];
            }
            if ((int)$run['producers']['events']['queued'] === 0 && $run['claimed'] === 0) {
                return ['topics' => array_column($deliveries, 'topic'), 'answers' => $answers, 'jobs' => $jobs];
            }
        }
        throw new RuntimeException('the worker did not drain in 20 passes');
    };
    $snapshot = fn(): array => [
        'orders' => shopify_db_rows("SELECT o.kundeordnr, o.status, o.felt_1, o.felt_2, o.betalings_id, (o.sum + o.moms)::numeric(10,2)::text AS total,
            (SELECT count(*) FROM transaktioner t WHERE t.ordre_id = o.id) AS postings
            FROM ordrer o WHERE o.id IN (SELECT ordre_id FROM shopify_order_links) ORDER BY o.id"),
        'links' => shopify_db_rows('SELECT order_gid, state FROM shopify_order_links ORDER BY id'),
        'imports' => shopify_db_rows("SELECT dedupe_key, state FROM shopify_jobs WHERE type = 'order.import' ORDER BY id"),
        'errors' => shopify_db_rows("SELECT kind, ref, severity, occurrences FROM shopify_errors WHERE resolved_at IS NULL AND severity <> 'info' ORDER BY kind, ref"),
        'job_errors' => array_column(shopify_db_rows("SELECT type, last_error FROM shopify_jobs WHERE last_error IS NOT NULL ORDER BY id"), 'last_error', 'type'),
        'stock' => (float)shopify_db_rows('SELECT sum(beholdning) AS n FROM lagerstatus WHERE vare_id = (SELECT id FROM varer WHERE varenr = $1)', [$sku])[0]['n'],
    ];
    $authorize = function (string $email) use ($store, $domain, $variant): string {
        $answer = json_decode($store->request($domain, RUN_AUTHORIZED_ORDER, ['order' => [
            'lineItems' => [['variantId' => $variant, 'quantity' => 2, 'taxLines' => [['title' => 'Moms', 'rate' => 0.25, 'priceSet' => ['shopMoney' => ['amount' => '50.00', 'currencyCode' => 'DKK']]]]]],
            'taxesIncluded' => true, 'currency' => 'DKK',
            'customer' => ['toUpsert' => ['email' => $email, 'firstName' => 'Kim', 'lastName' => 'Kunde']],
            'transactions' => [['kind' => 'AUTHORIZATION', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amountSet' => ['shopMoney' => ['amount' => '250.00', 'currencyCode' => 'DKK']]]],
        ], 'options' => ['inventoryBehaviour' => 'DECREMENT_OBEYING_POLICY']])['body'], true);
        // no shipping address: the fake models no MailingAddress.company, which the intake reads; an order without one is not refused as abroad
        $gid = $answer['data']['orderCreate']['order']['id'] ?? null;
        if ($gid === null) {
            throw new RuntimeException('orderCreate refused: ' . json_encode($answer));
        }
        return $gid;
    };

    $phases = [];
    $first = $authorize('kim@example.dk');
    $phases['authorized'] = ['day' => $day()] + $snapshot();
    $store->captureOrder($domain, $first);
    $phases['captured'] = ['day' => $day()] + $snapshot();
    $second = $authorize('kim2@example.dk');
    $phases['second_authorized'] = ['day' => $day()] + $snapshot();
    $store->voidOrder($domain, $second);
    $phases['voided'] = ['day' => $day()] + $snapshot();
    echo json_encode(['phases' => $phases, 'orders' => [$first, $second]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'class' => get_class($e), 'at' => $e->getFile() . ':' . $e->getLine(), 'log' => array_slice((array)file($log), -10)]);
    exit(1);
} finally {
    @unlink($log);
}
