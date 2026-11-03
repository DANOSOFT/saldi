<?php
// tests/shopify/support/run_chain.php
//
// Child-process runner for scenario X01 of the sandbox runner (SD-766): one web order from item
// to invoice across the lanes, on the shared fake store and a cloned tenant. Each link of the
// chain that is merged runs for real (register-webhooks, catalogue, webhook inbox and promotion,
// inbound customers); a link whose lane is not merged yet is reported BLOCKED with its ticket,
// and the fake plays Shopify's part (and the warehouse's) so the next link still runs.
//   php run_chain.php <test_db>   prints JSON: {results: list<{step, status, detail, owner?}>}
//
// History:
// 20261103 CL/LH SD-766: created.

if ($argc < 2) {
    fwrite(STDERR, "usage: php run_chain.php <test_db>\n");
    exit(2);
}
$testDb = $argv[1];

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
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/catalogue.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/services/customers.php';
require_once __DIR__ . '/ReplayDay.php';
require_once __DIR__ . '/sandbox/run.php';

$log = (string)tempnam(sys_get_temp_dir(), 'sd766-chain-log');
ini_set('error_log', $log);
$results = [];
$step = function (string $name, string $status, string $detail, ?string $owner = null) use (&$results): void {
    $results[] = ['step' => $name, 'status' => $status, 'detail' => $detail] + ($owner === null ? [] : ['owner' => $owner]);
};
/** Runs one merged link; its exception is a FAIL of that link and ends the chain. */
$link = function (string $name, callable $run) use ($step): bool {
    try {
        [$ok, $detail] = $run();
        $step($name, $ok ? 'PASS' : 'FAIL', $detail);
        return $ok;
    } catch (Throwable $e) {
        $step($name, 'FAIL', get_class($e) . ': ' . $e->getMessage());
        return false;
    }
};

try {
    shopify_bootstrap($testDb);
    ReplayDay::prepareTenant(['products_enabled']);
    foreach (["DELETE FROM varer WHERE varenr LIKE 'SD766-%'", "DELETE FROM grupper WHERE art = 'RA' OR (art = 'VG' AND kodenr = 976) OR (art = 'SM' AND kodenr = 76)",
        'DELETE FROM kontoplan WHERE kontonr = 9766'] as $sql) {
        shopify_db_rows($sql);
    }
    shopify_db_rows("INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
    shopify_db_rows("INSERT INTO grupper (beskrivelse, kodenr, art, box4, box7, box8, fiscal_year) VALUES ('Havemøbler', 976, 'VG', '9766', '', 'on', 1)");
    shopify_db_rows("INSERT INTO grupper (beskrivelse, kodenr, art, box2) VALUES ('Salgsmoms', 76, 'SM', '25')");
    shopify_db_rows("INSERT INTO kontoplan (kontonr, beskrivelse, moms, regnskabsaar) VALUES (9766, 'Salg', 'S76', 1)");
    $vareId = (int)shopify_db_rows("INSERT INTO varer (varenr, beskrivelse, stregkode, kostpris, salgspris, gruppe, lukket, grossweight, grossweightunit)
        VALUES ('SD766-1', 'Havestol SD766', '5701234567890', 800, 1249, 976, '', 7.5, 'kg') RETURNING id")[0]['id'];

    $store = new FakeShopifyStore();
    $domain = $store->addShop(ReplayDay::SHOP['shop_key'], ['domain' => ReplayDay::SHOP['domain'], 'locations' => ['Lager 1', 'Lager 2']])['domain'];
    [$l1, $l2] = $store->locations($domain);
    $graphql = $store->opts();
    $shop = shopify_shop(ReplayDay::SHOP['shop_key']);
    $shopId = (int)$shop['id'];
    $replayer = WebhookReplayer::forShop($shop);
    $sent = [];
    $deliver = function () use ($store, $replayer, &$sent): array {
        $batch = WebhookReplayer::fromOutbox($store->takeDeliveries());
        $sent = array_merge($sent, $batch);
        $answers = $replayer->replay($batch);
        return [array_keys($answers) === ['stored'], 'deliveries ' . json_encode($answers)];
    };
    $gql = fn(string $query, array $vars): array => json_decode($store->request($domain, $query, $vars)['body'], true)['data'];

    $ok = $link('events: register-webhooks (SD-749)', function () use ($shop, $graphql, $store, $domain): array {
        $r = shopify_register_webhooks($shop, 'https://saldi.example', $graphql);
        $subs = count($store->shop($domain)['subscriptions']);
        return [$r['created'] === count(SHOPIFY_WEBHOOK_TOPICS) && $subs === $r['created'], "created {$r['created']}, failed {$r['failed']}; the shop has $subs subscriptions"];
    });
    $product = null;
    $ok = $ok && $link('catalogue: the item becomes a draft in the primary shop (SD-741)', function () use ($graphql, $shopId, $vareId, $store, $domain, &$product): array {
        $r = shopify_catalogue_sync(['graphql' => $graphql, 'deadline' => $store->clock->now + 50], $shopId, $vareId, 'sd766-chain');
        $linked = shopify_product_link($shopId, $vareId);
        $product = $store->shop($domain)['products'][$linked['product_gid'] ?? ''] ?? null;
        return [$r['result'] === 'created' && ($product['status'] ?? '') === 'DRAFT', "result {$r['result']}, product " . ($linked['product_gid'] ?? 'none') . ' ' . ($product['status'] ?? '')];
    });
    if (!$ok) {
        throw new RuntimeException('the chain stopped at a merged link');
    }
    // HML publishes the draft; Saldi's stock push (SD-756) would set the level, the fake does it here
    $variant = $product['variants'][0];
    $item = $store->shop($domain)['variants'][$variant]['item'];
    $store->shop($domain)['products'][shopify_product_link($shopId, $vareId)['product_gid']]['status'] = 'ACTIVE';
    $store->shop($domain)['levels'][$item][$l1]['available'] = 3;
    $store->shop($domain)['levels'][$item][$l2]['available'] = 2;
    $step('stock: Saldi pushes the item\'s stock to the shop', 'BLOCKED', 'stock drain and absolute push are not merged; the fake holds 3 at Lager 1, 2 at Lager 2', 'SD-756');

    // the customer checks out on the web shop: Shopify creates the order and the customer
    $order = $gql('mutation o($order: OrderCreateOrderInput!) { orderCreate(order: $order) { order { id name fulfillmentOrders(first: 5) { nodes { id lineItems(first: 5) { nodes { id } } } } } userErrors { message } } }',
        ['order' => ['lineItems' => [['variantId' => $variant, 'quantity' => 2]], 'currency' => 'DKK', 'sourceName' => 'web',
            'customer' => ['toUpsert' => ['email' => 'chain@example.dk', 'phone' => '+4520007660', 'firstName' => 'Test', 'lastName' => 'Kæde',
                'addresses' => [['firstName' => 'Test', 'lastName' => 'Kæde', 'address1' => 'Havevej 66', 'zip' => '2750', 'city' => 'Ballerup', 'country' => 'Denmark']]]],
            'transactions' => [['kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amountSet' => ['shopMoney' => ['amount' => '2498.00', 'currencyCode' => 'DKK']]]]]])['orderCreate']['order'];
    $ok = $link('webhooks: the order\'s deliveries reach the inbox (SD-748)', function () use ($deliver): array {
        $seen = shopify_await(fn(): array => $deliver(), fn(array $r): bool => $r[0], 30);
        return [$seen['ok'], $seen['value'][1] . sprintf(' in %.2f s', $seen['seconds'])];
    });
    $drained = ReplayDay::drain($testDb, $graphql);
    $ok = $ok && $link('customers: the web order\'s customer becomes a debtor (SD-792)', function () use ($shopId, $store, $domain, $order, $drained): array {
        $gid = $store->shop($domain)['orders'][$order['id']]['customer'];
        $debtor = shopify_db_rows("SELECT a.kontonr, a.firmanavn, a.addr1, a.mobile FROM shopify_customer_links l JOIN adresser a ON a.id = l.adresser_id WHERE l.shop_id = $1 AND l.customer_gid = $2", [$shopId, $gid])[0] ?? null;
        return [$debtor !== null && $debtor['addr1'] === 'Havevej 66' && $drained['failed'] + $drained['dead'] === 0,
            $debtor === null ? "no debtor for $gid" : "debtor {$debtor['kontonr']} {$debtor['firmanavn']}, {$debtor['addr1']}, {$debtor['mobile']}"];
    });
    $import = shopify_db_rows("SELECT state FROM shopify_jobs WHERE type = 'order.import' AND dedupe_key = $1", ['hml:' . $order['id']])[0]['state'] ?? 'none';
    $step('orders: the paid web order becomes a Saldi order', 'BLOCKED', "order.import for {$order['name']} is $import; paid-order intake is not merged", 'SD-743');
    $step('allocation: the order\'s lines are drawn from the Saldi warehouses', 'BLOCKED', 'shopify_allocate_order() needs the Saldi order from SD-743', 'SD-739');

    // the warehouse moves the order to Lager 2 and ships it (the fake plays Shopify and Shipmondo)
    $fo = $order['fulfillmentOrders']['nodes'][0]['id'];
    $moved = $gql('mutation m($id: ID!, $to: ID!) { fulfillmentOrderMove(id: $id, newLocationId: $to) { movedFulfillmentOrder { id } userErrors { message } } }', ['id' => $fo, 'to' => $l2])['fulfillmentOrderMove'];
    $step('moves: Saldi moves the fulfillment order to the drawn location', 'BLOCKED', 'fulfillmentOrderMove from Saldi is not merged; the fake moved it to Lager 2: '
        . ($moved['userErrors'] === [] ? 'ok' : json_encode($moved['userErrors'])), 'SD-760');
    $gql('mutation f($f: FulfillmentInput!) { fulfillmentCreate(fulfillment: $f) { fulfillment { id } userErrors { message } } }',
        ['f' => ['lineItemsByFulfillmentOrder' => [['fulfillmentOrderId' => $moved['movedFulfillmentOrder']['id'] ?? $fo]], 'trackingInfo' => ['company' => 'GLS', 'number' => 'SD766CHAIN']]]);
    $ok = $ok && $link('webhooks: the shipment\'s deliveries reach the inbox (SD-748)', $deliver);
    ReplayDay::drain($testDb, $graphql);
    $fulfil = shopify_db_rows("SELECT count(*) AS n FROM shopify_jobs WHERE type = 'fulfillment.upsert' AND state = 'queued'")[0]['n'];
    $step('fulfilments: the shipment is recorded on the Saldi order', 'BLOCKED', "$fulfil fulfillment.upsert jobs queued; fulfilment upsert is not merged", 'SD-740');
    $step('invoice: the delivered order is invoiced and posted', 'BLOCKED', 'delivery transaction and posting are not merged', 'SD-743');
    $step('stock: the sale lowers the stock pushed to every shop', 'BLOCKED', 'stock drain and push are not merged', 'SD-756');
    $link('replay: the chain\'s webhooks sent again change nothing (NF-1)', function () use ($replayer, $sent, $testDb, $graphql): array {
        $before = ReplayDay::snapshot();
        $answers = $replayer->replay(WebhookReplayer::arrange($sent, 'reversed'));
        ReplayDay::drain($testDb, $graphql);
        return [array_keys($answers) === ['duplicate'] && ReplayDay::snapshot() === $before, count($sent) . ' deliveries again: ' . json_encode($answers) . '; ReplayDay::SNAPSHOT unchanged'];
    });
} catch (Throwable $e) {
    $step('chain', 'FAIL', get_class($e) . ': ' . $e->getMessage());
} finally {
    @unlink($log);
}
echo json_encode(['results' => $results], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
