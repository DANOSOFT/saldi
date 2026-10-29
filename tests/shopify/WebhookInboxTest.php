<?php
// tests/shopify/WebhookInboxTest.php
//
// The webhook inbox and event promotion (includes/shopify/events.php, SD-748) on a throwaway
// tenant registered in master: what shopify_receive_webhook() stores and answers, and how
// shopify_promote_events() turns stored events into jobs. Skips when ShopifyEnv is not
// configured. The alarm and the queue are support/run_events.php's stand-ins, which can fail on
// cue, except where a test runs SD-737's real queue or S02c's real error list (SD-738). Both
// stand-ins write to the real tables, so alarms are read from shopify_errors. The webhook secrets are random per run.
//
// History:
// 20261027 CL/SZ SD-748: created.
// 20261027 CL/SZ SD-748: the promotion test also runs on SD-737's real queue (#918).
// 20261028 CL/SZ SD-748: an anomaly whose alarm fails no longer stops promotion.
// 20261028 CL/SZ SD-748 review: nor does an event whose alarm fails; compliance topics are ignored with the flow off.
// 20261028 CL/SZ SD-748: S02c is on hml/shopify; no_alarm tests use alarm_fails, and one runs on the real error list.
// 20261028 CL/SZ SD-748 review: deferred events go behind new ones (limit 1); a failed route alarm keeps its kind.
// 20261028 CL/SZ SD-749: the webhook.health job restores a deleted subscription.
// 20261029 CL/SZ SD-749 review: a health job that can't restore raises one error and one alarm mail.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class WebhookInboxTest extends TestCase
{
    private static string $secret = '';
    private static string $brandSecret = '';

    private static ?string $skip = null;
    private static string $db = '';
    private static int $hml = 0;
    private static int $brand = 0;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        self::$secret = bin2hex(random_bytes(16));
        self::$brandSecret = bin2hex(random_bytes(16));
        try {
            self::$db = ShopifyEnv::testDb('_w');
            ShopifyEnv::cloneTenant(self::$db);
            ShopifyEnv::registerTenant(self::$db);
            $run = ShopifyEnv::runSchema(self::$db, 'ensure');
            if ($run['exit'] !== 0) {
                throw new RuntimeException('schema ensure failed: ' . $run['stderr']);
            }
        } catch (Throwable $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        ShopifyEnv::unregisterTenants();
        ShopifyEnv::dropOwnedTenants();
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $conn = ShopifyEnv::connect(self::$db);
        foreach (['shopify_events', 'shopify_jobs', 'shopify_errors', 'shopify_shops'] as $table) {
            ShopifyEnv::rows($conn, "DELETE FROM $table");
        }
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp LIKE 'shopify:%' OR (var_grp = 'shopify' AND var_name = 'webhook_base_url')");
        ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
        ShopifyEnv::rows($conn, "INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
        self::$hml = (int)ShopifyEnv::one($conn, "INSERT INTO shopify_shops (shop_key, domain, is_primary, orders_enabled, customers_enabled) VALUES ('hml', 'hml-test.myshopify.com', true, true, true) RETURNING id")['id'];
        self::$brand = (int)ShopifyEnv::one($conn, "INSERT INTO shopify_shops (shop_key, domain) VALUES ('brand2', 'brand2-test.myshopify.com') RETURNING id")['id'];
        ShopifyEnv::rows($conn, "INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('shopify:hml', 'webhook_secret', $1, ''), ('shopify:brand2', 'webhook_secret', $2, '')", [self::$secret, self::$brandSecret]);
        pg_close($conn);
    }

    // ---- receive ---------------------------------------------------------------------------

    public function testTheSameWebhookTwiceGivesOneInboxRow(): void
    {
        $raw = self::body('orders_paid');
        $first = self::delivery('hml', 'orders/paid', 'ev-paid-1', $raw, ['x-shopify-webhook-id' => 'wh-attempt-1']);
        $retry = self::delivery('hml', 'orders/paid', 'ev-paid-1', $raw, ['x-shopify-webhook-id' => 'wh-attempt-2']);

        $run = $this->receive([$first, $first, $retry]);

        $this->assertSame([[200, 'stored'], [200, 'duplicate'], [200, 'duplicate']], self::answers($run), 'a retry carries a new webhook id but the same event id');
        $rows = $this->events();
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame([(string)self::$hml, 'ORDERS_PAID', 'ev-paid-1', 'wh-attempt-1', '2026-07', 'received'], [$row['shop_id'], $row['topic'], $row['event_id'], $row['webhook_id'], $row['api_version'], $row['state']]);
        $this->assertSame($raw, base64_decode($row['payload_b64']), 'the body is stored byte for byte');
        $this->assertSame('2026-10-27 09:12:44.123', $row['triggered_at'], 'X-Shopify-Triggered-At in the business time zone');
        $this->assertNull($row['job_id']);
        $this->assertSame([], $this->alarms());
        $this->assertSame([(int)$row['id'], (int)$row['id'], (int)$row['id']], array_column($run['results'], 'event_row_id'));
    }

    public function testTwoTopicsSharingOneEventIdAreBothStoredAndSoAreTwoShops(): void
    {
        $run = $this->receive([
            self::delivery('hml', 'orders/create', 'ev-shared', self::body('orders_create')),
            self::delivery('hml', 'orders/updated', 'ev-shared', self::body('orders_create')),
            self::delivery('brand2', 'orders/create', 'ev-shared', self::body('orders_create'), [], self::$brandSecret),
        ]);

        $this->assertSame([[200, 'stored'], [200, 'stored'], [200, 'stored']], self::answers($run));
        $this->assertSame([[(string)self::$hml, 'ORDERS_CREATE'], [(string)self::$hml, 'ORDERS_UPDATED'], [(string)self::$brand, 'ORDERS_CREATE']],
            array_map(fn(array $r): array => [$r['shop_id'], $r['topic']], $this->events()));
    }

    public function testAConflictingBodyIsStoredAsAnomalyGets200AndAnAlarm(): void
    {
        $raw = self::body('orders_paid');
        $other = str_replace('1249.00', '1.00', $raw);
        $run = $this->receive([
            self::delivery('hml', 'orders/paid', 'ev-c', $raw),
            self::delivery('hml', 'orders/paid', 'ev-c', $other),
            self::delivery('hml', 'orders/paid', 'ev-c', $other),
        ]);

        $this->assertSame([[200, 'stored'], [200, 'anomaly'], [200, 'anomaly']], self::answers($run), 'Shopify must never retry it');
        $rows = $this->events();
        $this->assertCount(2, $rows, 'the conflicting body is kept once, beside the first');
        $this->assertSame(['ev-c', 'received', $raw], [$rows[0]['event_id'], $rows[0]['state'], base64_decode($rows[0]['payload_b64'])], 'the first row is untouched');
        $this->assertSame(['ev-c#conflict:' . hash('sha256', $other), 'anomaly', $other], [$rows[1]['event_id'], $rows[1]['state'], base64_decode($rows[1]['payload_b64'])]);
        $this->assertNotNull($rows[1]['processed_at'], 'alarmed at once');
        $this->assertSame([(int)$rows[1]['id'], (int)$rows[1]['id']], array_column(array_slice($run['results'], 1), 'event_row_id'));
        $alarms = $this->alarms();
        $this->assertCount(1, $alarms, 'one alarm per conflicting body');
        $this->assertSame([(string)self::$hml, 'webhook.anomaly', 'hml:ORDERS_PAID:ev-c', 'error', '1'], [$alarms[0]['shop_id'], $alarms[0]['kind'], $alarms[0]['ref'], $alarms[0]['severity'], $alarms[0]['occurrences']]);
        $this->assertSame(['event_row_id' => (int)$rows[1]['id'], 'first_event_row_id' => (int)$rows[0]['id']], json_decode($alarms[0]['correlation'], true));
    }

    public function testAWrongSignatureGets401IsLoggedAndCreatesNoRows(): void
    {
        $raw = self::body('orders_paid');
        $tampered = self::delivery('hml', 'orders/paid', 'ev-bad', $raw);
        $tampered['raw_b64'] = base64_encode(str_replace('1249.00', '1249.01', $raw));
        $unsigned = self::delivery('hml', 'orders/paid', 'ev-bad', $raw);
        unset($unsigned['headers']['X-Shopify-Hmac-Sha256']);

        $run = $this->receive([
            $tampered,
            self::delivery('hml', 'orders/paid', 'ev-bad', $raw, [], self::$brandSecret),
            $unsigned,
        ]);

        $this->assertSame([[401, 'bad_signature'], [401, 'bad_signature'], [401, 'bad_signature']], self::answers($run));
        $this->assertSame([], $this->events());
        $this->assertSame([], $this->alarms());
        $this->assertSame(3, substr_count($run['stderr'], 'shopify_webhook bad_signature {"shop":"hml","topic":"orders/paid","event_id":"ev-bad"'), $run['stderr']);
        foreach ([self::$secret, self::$brandSecret, '1249.0', $tampered['headers']['X-Shopify-Hmac-Sha256']] as $secret) {
            $this->assertStringNotContainsString($secret, $run['stderr'], 'the log never carries the secret, the body or the signature');
        }
    }

    public function testAnotherShopsDomainGets401AndAShopWithoutASecretGets503(): void
    {
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp = 'shopify:brand2'");
        pg_close($conn);
        $raw = self::body('orders_paid');
        $wrongDomain = self::delivery('hml', 'orders/paid', 'ev-d', $raw);
        $wrongDomain['headers']['X-Shopify-Shop-Domain'] = 'brand2-test.myshopify.com';

        $run = $this->receive([$wrongDomain, self::delivery('brand2', 'orders/paid', 'ev-d', $raw, [], self::$brandSecret)]);

        $this->assertSame([[401, 'shop_mismatch'], [503, 'not_configured']], self::answers($run), '503 makes Shopify retry once the secret is set');
        $this->assertSame([], $this->events());
    }

    public function testASignedDeliveryWithoutATopicOrEventIdIsKeptAsAnomaly(): void
    {
        $raw = self::body('customers_create');
        $noEvent = self::delivery('hml', 'customers/create', 'x', $raw);
        unset($noEvent['headers']['X-Shopify-Event-Id']);
        $badTopic = self::delivery('hml', 'not a topic', 'ev-t', $raw);

        $run = $this->receive([$noEvent, $noEvent, $badTopic]);

        $this->assertSame([[200, 'anomaly'], [200, 'duplicate'], [200, 'anomaly']], self::answers($run), 'a retry lands on the same row');
        $this->assertSame([['CUSTOMERS_CREATE', 'missing:' . hash('sha256', $raw), 'anomaly'], ['UNKNOWN', 'missing:' . hash('sha256', $raw), 'anomaly']],
            array_map(fn(array $r): array => [$r['topic'], $r['event_id'], $r['state']], $this->events()));
        $this->assertSame(['webhook.anomaly', 'webhook.anomaly'], array_column($this->alarms(), 'kind'));
    }

    public function testAnAnomalyGets200EvenWhenItsAlarmFailsAndIsAlarmedByTheNextPromotion(): void
    {
        $raw = self::body('orders_paid');
        $other = str_replace('1249.00', '2.00', $raw);
        $this->receive([self::delivery('hml', 'orders/paid', 'ev-a', $raw)]);

        $failing = $this->receive([self::delivery('hml', 'orders/paid', 'ev-a', $other)], 'alarm_fails');

        $this->assertSame([[200, 'anomaly']], self::answers($failing), 'every HMAC-valid delivery gets 200');
        $this->assertStringContainsString('shopify_webhook alarm_deferred', $failing['stderr']);
        $rows = $this->events();
        $this->assertSame(['received', 'anomaly'], array_column($rows, 'state'));
        $this->assertNull($rows[1]['processed_at'], 'not alarmed yet');
        $this->assertSame([], $this->alarms());

        $promoted = $this->promote(10);
        $again = $this->promote(10);
        $retry = $this->receive([self::delivery('hml', 'orders/paid', 'ev-a', $other)]);

        $this->assertSame(1, $promoted['result']['alarmed']);
        $this->assertSame(0, $again['result']['alarmed']);
        $this->assertSame([[200, 'anomaly']], self::answers($retry));
        $alarms = $this->alarms();
        $this->assertSame([['webhook.anomaly', 'hml:ORDERS_PAID:ev-a', '1']], array_map(fn(array $a): array => [$a['kind'], $a['ref'], $a['occurrences']], $alarms), 'alarmed once');
        $this->assertSame(['event_row_id' => (int)$rows[1]['id'], 'first_event_row_id' => (int)$rows[0]['id']], json_decode($alarms[0]['correlation'], true));
        $this->assertNotNull($this->events()[1]['processed_at']);
    }

    public function testAnAnomalyWhoseAlarmFailsStaysPendingAndTheEventsBehindItArePromoted(): void
    {
        $raw = self::body('orders_paid');
        $this->receive([self::delivery('hml', 'orders/paid', 'ev-n', $raw)]);

        $run = $this->receive([self::delivery('hml', 'orders/paid', 'ev-n', str_replace('1249.00', '3.00', $raw))], 'alarm_fails');
        $promoteWithout = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_events.php', ['promote', self::$db, '10', 'alarm_fails']));

        $this->assertSame([[200, 'anomaly']], self::answers($run));
        $this->assertStringContainsString('alarm webhook.anomaly failed (test)', $run['stderr']);
        $this->assertSame(0, $promoteWithout['exit'], $promoteWithout['stderr'] . $promoteWithout['stdout']);
        $this->assertSame(0, $promoteWithout['result']['result']['alarmed']);
        $this->assertStringContainsString('shopify_webhook alarm_deferred', $promoteWithout['stderr']);
        $this->assertSame(['queued', 'anomaly'], array_column($this->events(), 'state'), 'the paid order behind the anomaly is promoted');
        $this->assertNull($this->events()[1]['processed_at'], 'the anomaly stays pending, not marked alarmed');
        $this->assertSame([], $this->alarms());

        $this->assertSame(1, $this->promote(10)['result']['alarmed'], 'alarmed once the alarm is there');
        $this->assertSame(['webhook.anomaly'], array_column($this->alarms(), 'kind'));
    }

    // ---- promote ---------------------------------------------------------------------------

    public static function queues(): array
    {
        return ['stand-in queue' => [''], 'SD-737 queue' => ['real_queue']];
    }

    #[DataProvider('queues')]
    public function testPaidOrdersArePromotedFirstAndAnEventIsNeverEnqueuedTwice(string $queue): void
    {
        $this->receive([
            self::delivery('hml', 'customers/create', 'ev-1', self::body('customers_create')),
            self::delivery('hml', 'orders/create', 'ev-2', self::body('orders_create')),
            self::delivery('hml', 'orders/paid', 'ev-3', self::body('orders_paid')),
            self::delivery('hml', 'orders/paid', 'ev-4', self::body('orders_paid')),
        ]);

        $first = $this->promote(2, $queue);
        $second = $this->promote(10, $queue);
        $third = $this->promote(10, $queue);

        $this->assertSame([['ORDERS_PAID', 'queued'], ['ORDERS_PAID', 'queued']], self::promoted($first), 'ORDERS_PAID before older events');
        $this->assertSame([['CUSTOMERS_CREATE', 'queued'], ['ORDERS_CREATE', 'queued']], self::promoted($second), 'then oldest first');
        $this->assertSame([], $third['result']['events'], 'a promoted event is not taken again');

        $jobs = $this->jobs();
        $this->assertSame([
            ['order.import', 10, 'hml:gid://shopify/Order/820982911946154508', 'gid://shopify/Order/820982911946154508'],
            ['customer.upsert', 25, 'hml:CUSTOMERS_CREATE:ev-1', null],
            ['order.observed', 25, 'hml:ORDERS_CREATE:ev-2', 'gid://shopify/Order/820982911946154508'],
        ], array_map(fn(array $j): array => [$j['type'], (int)$j['priority'], $j['dedupe_key'], $j['order_gid']], $jobs), 'two paid events of one order make one order.import');
        $byTopic = [];
        foreach ($this->events() as $event) {
            $this->assertSame('queued', $event['state']);
            $this->assertNotNull($event['processed_at']);
            $byTopic[$event['event_id']] = (int)$event['job_id'];
        }
        $this->assertSame(['ev-1' => (int)$jobs[1]['id'], 'ev-2' => (int)$jobs[2]['id'], 'ev-3' => (int)$jobs[0]['id'], 'ev-4' => (int)$jobs[0]['id']], $byTopic);
        $this->assertSame((int)$this->events()[2]['id'], (int)$jobs[0]['event_row_id'], 'the job points at the event that made it');
        $payload = json_decode($jobs[0]['payload'], true);
        ksort($payload); // jsonb keeps its own key order
        $this->assertSame(['event_id' => 'ev-3', 'event_row_id' => (int)$this->events()[2]['id'], 'order_gid' => 'gid://shopify/Order/820982911946154508', 'topic' => 'ORDERS_PAID'], $payload);
    }

    public function testAnUnroutedTopicIsIgnoredAndAnUnreadableBodyIsAnErrorWithAnAlarm(): void
    {
        $this->receive([
            self::delivery('hml', 'products/update', 'ev-p', '{"id":1}'),
            self::delivery('hml', 'orders/paid', 'ev-x', 'not json'),
            self::delivery('hml', 'orders/paid', 'ev-y', '{"name":"#1002"}'),
        ]);

        $run = $this->promote(10);

        $this->assertSame([['ORDERS_PAID', 'error'], ['ORDERS_PAID', 'error'], ['PRODUCTS_UPDATE', 'ignored']], self::promoted($run));
        $this->assertSame([], $this->jobs());
        $this->assertSame(['queued' => 0, 'ignored' => 1, 'error' => 2, 'failed' => 0, 'deferred' => 0, 'alarmed' => 0], array_diff_key($run['result'], ['events' => 0]));
        $this->assertSame([['webhook.route', 'hml:ORDERS_PAID:ev-x', 'the webhook body is not a JSON object'], ['webhook.route', 'hml:ORDERS_PAID:ev-y', 'ORDERS_PAID without an order id']],
            array_map(fn(array $a): array => [$a['kind'], $a['ref'], $a['message']], $this->alarms()));
    }

    public function testEventsOfAShopWithItsFlowOffWaitUntilItIsSwitchedOn(): void
    {
        $this->receive([
            self::delivery('brand2', 'orders/paid', 'ev-b1', self::body('orders_paid'), [], self::$brandSecret),
            self::delivery('brand2', 'customers/create', 'ev-b2', self::body('customers_create'), [], self::$brandSecret),
            self::delivery('brand2', 'products/update', 'ev-b3', '{"id":1}', [], self::$brandSecret),
            self::delivery('brand2', 'customers/redact', 'ev-b4', '{"customer":{"id":1}}', [], self::$brandSecret),
        ]);

        $off = $this->promote(10);
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, 'UPDATE shopify_shops SET orders_enabled = true WHERE id = $1', [self::$brand]);
        pg_close($conn);
        $ordersOn = $this->promote(10);

        $this->assertSame([['PRODUCTS_UPDATE', 'ignored'], ['CUSTOMERS_REDACT', 'ignored']], self::promoted($off), 'a topic without a job is not held back by its flow');
        $this->assertSame([['ORDERS_PAID', 'queued']], self::promoted($ordersOn), 'customers stay off');
        $this->assertSame(['queued', 'received', 'ignored', 'ignored'], array_column($this->events(), 'state'));
    }

    public function testAFailedEnqueueMakesTheEventAnErrorWithAnAlarmAndTheBatchGoesOn(): void
    {
        $this->receive([
            self::delivery('hml', 'orders/paid', 'ev-f1', self::body('orders_paid')),
            self::delivery('hml', 'customers/create', 'ev-f2', self::body('customers_create')),
        ]);

        $failing = $this->promote(10, 'order.import');
        $after = $this->promote(10);

        $this->assertSame(['queued' => 1, 'ignored' => 0, 'error' => 0, 'failed' => 1, 'deferred' => 0, 'alarmed' => 0], array_diff_key($failing['result'], ['events' => 0]));
        $this->assertSame([['ORDERS_PAID', 'error'], ['CUSTOMERS_CREATE', 'queued']], self::promoted($failing), 'the failing event holds nothing up');
        $this->assertSame([['webhook.promote', 'hml:ORDERS_PAID:ev-f1', 'enqueue failed: RuntimeException: enqueue of order.import failed (test)']],
            array_map(fn(array $a): array => [$a['kind'], $a['ref'], $a['message']], $this->alarms()));
        $this->assertSame([], $after['result']['events'], 'an error is replayed by hand, not on every run');
        $this->assertSame(['customer.upsert'], array_column($this->jobs(), 'type'));
    }

    public function testAnEnqueueThatFailsInSqlAfterWritingLeavesNoJobAndTheBatchGoesOn(): void
    {
        $this->receive([
            self::delivery('hml', 'orders/paid', 'ev-s1', self::body('orders_paid')),
            self::delivery('hml', 'customers/create', 'ev-s2', self::body('customers_create')),
        ]);

        $run = $this->promote(10, 'sql_error');

        $this->assertSame([['ORDERS_PAID', 'error'], ['CUSTOMERS_CREATE', 'queued']], self::promoted($run), 'the aborted savepoint is recovered');
        $this->assertSame(['customer.upsert'], array_column($this->jobs(), 'type'), 'the job written before the failure is rolled back');
        $this->assertSame(['webhook.promote'], array_column($this->alarms(), 'kind'));
        $this->assertStringContainsString('ShopifyDbError', $this->alarms()[0]['message']);
    }

    public function testAnEventWhoseAlarmFailsWaitsForTheNextRunAndTheRestArePromoted(): void
    {
        $this->receive([
            self::delivery('hml', 'orders/paid', 'ev-r1', self::body('orders_paid')),
            self::delivery('hml', 'orders/create', 'ev-r2', 'not json'),
        ]);

        $failing = $this->promote(10, 'alarm_fails');

        $this->assertSame(['queued' => 1, 'ignored' => 0, 'error' => 0, 'failed' => 0, 'deferred' => 1, 'alarmed' => 0], array_diff_key($failing['result'], ['events' => 0]));
        $this->assertSame([['ev-r1', 'queued'], ['ev-r2', 'received']], array_map(fn(array $e): array => [$e['event_id'], $e['state']], $this->events()),
            'the paid order is kept; the event without its alarm is not stored as an error');
        $this->assertStringContainsString('shopify_webhook alarm_deferred', $failing['stderr']);
        $this->assertStringContainsString('the webhook body is not a JSON object', $failing['stderr'], 'the route problem stays in the log');
        $this->assertSame([], $this->alarms());

        $after = $this->promote(10);

        $this->assertSame([['ORDERS_CREATE', 'error']], self::promoted($after), 'routed again once the alarm works');
        $this->assertSame([['webhook.route', 'hml:ORDERS_CREATE:ev-r2', 'the webhook body is not a JSON object']],
            array_map(fn(array $a): array => [$a['kind'], $a['ref'], $a['message']], $this->alarms()));
    }

    public function testAPaidEventWhoseAlarmFailsDoesNotHoldUpThePaidEventsBehindIt(): void
    {
        $this->receive([
            self::delivery('hml', 'orders/paid', 'ev-bad', '{"note":"no order id"}'),
            self::delivery('hml', 'orders/paid', 'ev-good', self::body('orders_paid')),
        ]);

        $runs = [];
        for ($i = 0; $i < 2; $i++) {
            $runs[] = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_events.php', ['promote', self::$db, '10', 'alarm_fails']));
        }

        foreach ($runs as $run) {
            $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        }
        $this->assertSame([['ORDERS_PAID', 'received'], ['ORDERS_PAID', 'queued']], self::promoted($runs[0]['result']));
        $this->assertSame([['ORDERS_PAID', 'received']], self::promoted($runs[1]['result']), 'the bad event is tried again, the good one is not');
        $this->assertSame([['ev-bad', 'received'], ['ev-good', 'queued']], array_map(fn(array $e): array => [$e['event_id'], $e['state']], $this->events()));
        $this->assertSame(['order.import'], array_column($this->jobs(), 'type'));

        $this->promote(10);

        $this->assertSame('error', $this->events()[0]['state']);
        $this->assertSame([['webhook.route', 'ORDERS_PAID without an order id']], array_map(fn(array $a): array => [$a['kind'], $a['message']], $this->alarms()));
    }

    public function testDeferredEventsGoBehindTheEventsNotTriedYet(): void
    {
        $this->receive([
            self::delivery('hml', 'orders/paid', 'ev-bad', '{"note":"no order id"}'),
            self::delivery('hml', 'orders/paid', 'ev-good', self::body('orders_paid')),
            self::delivery('hml', 'customers/create', 'ev-c', self::body('customers_create')),
        ]);

        $runs = [];
        for ($i = 0; $i < 4; $i++) {
            $runs[] = self::promoted($this->promote(1, 'alarm_fails'));
        }

        $this->assertSame([[['ORDERS_PAID', 'received']], [['ORDERS_PAID', 'queued']], [['CUSTOMERS_CREATE', 'queued']], [['ORDERS_PAID', 'received']]], $runs,
            'with limit 1 the deferred paid event never holds up the new ones, and is tried again when they are done');
        $this->assertSame([['ev-bad', 'received'], ['ev-good', 'queued'], ['ev-c', 'queued']], array_map(fn(array $e): array => [$e['event_id'], $e['state']], $this->events()));
    }

    public function testWhenOnlyTheRouteAlarmFailsTheEventKeepsItsRouteAlarm(): void
    {
        $this->receive([self::delivery('hml', 'orders/paid', 'ev-o', '{"note":"no order id"}')]);

        $run = $this->promote(10, 'alarm_fails_once');

        $this->assertSame(['queued' => 0, 'ignored' => 0, 'error' => 1, 'failed' => 0, 'deferred' => 0, 'alarmed' => 0], array_diff_key($run['result'], ['events' => 0]));
        $this->assertSame([['webhook.route', 'hml:ORDERS_PAID:ev-o', 'ORDERS_PAID without an order id']],
            array_map(fn(array $a): array => [$a['kind'], $a['ref'], $a['message']], $this->alarms()));
        $this->assertSame('error', $this->events()[0]['state']);
    }

    public function testAlarmsGoThroughS02csErrorListAndQueueAMailAlert(): void
    {
        $raw = self::body('orders_paid');
        $run = $this->receive([
            self::delivery('hml', 'orders/paid', 'ev-e1', $raw),
            self::delivery('hml', 'orders/paid', 'ev-e1', str_replace('1249.00', '4.00', $raw)),
            self::delivery('hml', 'orders/paid', 'ev-e2', '{"note":"no order id"}'),
        ], 'real');

        $promoted = $this->promote(10, 'real');

        $this->assertSame([[200, 'stored'], [200, 'anomaly'], [200, 'stored']], self::answers($run));
        $this->assertSame([['ORDERS_PAID', 'queued'], ['ORDERS_PAID', 'error']], self::promoted($promoted));
        $this->assertSame([['webhook.anomaly', 'hml:ORDERS_PAID:ev-e1'], ['webhook.route', 'hml:ORDERS_PAID:ev-e2']],
            array_map(fn(array $a): array => [$a['kind'], $a['ref']], $this->alarms()));
        foreach ($this->alarms() as $alarm) {
            $this->assertNotNull($alarm['alarmed_at'], 'S02c alarms support');
        }
        $types = array_column($this->jobs(), 'type');
        sort($types);
        $this->assertSame(['mail.alert', 'mail.alert', 'order.import'], $types, 'one alarm mail per error, beside the paid order');
    }

    public function testAnEnqueueThatEndsTheTransactionItselfStopsTheRun(): void
    {
        $this->receive([
            self::delivery('hml', 'orders/paid', 'ev-m1', self::body('orders_paid')),
            self::delivery('hml', 'customers/create', 'ev-m2', self::body('customers_create')),
        ]);

        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_events.php', ['promote', self::$db, '10', 'misuse']));

        $this->assertSame(1, $run['exit'], $run['stdout']);
        $this->assertSame('ShopifyDbMisuse', $run['result']['error']['class'], 'a programming error is never turned into an event error');
        $this->assertSame('received', $this->events()[1]['state'], 'nothing after it is promoted');
        $this->assertSame([], $this->alarms());
    }

    // ---- helpers ---------------------------------------------------------------------------

    // ---- webhook.health (SD-749) ------------------------------------------------------------

    public function testTheHealthJobRestoresADeletedSubscriptionAndWaitsForABaseUrl(): void
    {
        $without = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_events.php', ['health', self::$db, '-']));
        $this->assertSame(0, $without['exit'], $without['stderr'] . $without['stdout']);
        $this->assertSame(['outcome' => 'done'], $without['result']['result']);
        $this->assertSame([], $without['result']['ops'], 'nothing is sent before register-webhooks stored a base URL');
        $this->assertStringContainsString('shopify_webhook health_skipped', $without['stderr']);

        $with = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_events.php', ['health', self::$db, 'https://dev-sz.example.dk/sz/']));
        $this->assertSame(0, $with['exit'], $with['stderr'] . $with['stdout']);
        $this->assertSame(['outcome' => 'done'], $with['result']['result']);
        $this->assertSame(['list', 'create'], $with['result']['ops'], 'only the deleted topic is created');
        $this->assertSame(['https://dev-sz.example.dk/sz/api/shopify/webhook.php?shop=hml'], $with['result']['topics']['ORDERS_PAID']);
        $this->assertSame(['https://other-install.example.dk/sz/api/shopify/webhook.php?shop=hml'], $with['result']['topics']['REFUNDS_CREATE'],
            'ours at another install is not moved by the hourly job');
        $this->assertStringContainsString('shopify_webhook subscriptions_restored {"shop":"hml","topics":["ORDERS_PAID"]}', $with['stderr']);

        $failing = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_events.php', ['health', self::$db, '-', 'fail_create']));
        $this->assertSame(0, $failing['exit'], $failing['stderr'] . $failing['stdout']);
        $this->assertSame(['outcome' => 'done'], $failing['result']['result'], 'a failure ends the job; the next hour retries');
        $this->assertStringContainsString('shopify_webhook health_failed', $failing['stderr']);
        $this->assertSame([['webhook.health', 'hml']], array_map(fn(array $a): array => [$a['kind'], $a['ref']], $this->alarms()),
            'a subscription that cannot be restored reaches the error list');
        $this->assertSame(['mail.alert'], array_column($this->jobs(), 'type'), 'and is mailed');
        $again = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_events.php', ['health', self::$db, '-', 'fail_create']));
        $this->assertSame(0, $again['exit'], $again['stderr'] . $again['stdout']);
        $this->assertSame(['2'], array_column($this->alarms(), 'occurrences'), 'the next hour counts on the open row');
        $this->assertSame(['mail.alert'], array_column($this->jobs(), 'type'), 'and mails no more than once an hour');
        $conn = ShopifyEnv::connect(self::$db);
        $stored = ShopifyEnv::rows($conn, "SELECT var_value FROM settings WHERE var_grp = 'shopify' AND var_name = 'webhook_base_url'");
        pg_close($conn);
        $this->assertSame([['var_value' => 'https://dev-sz.example.dk/sz']], $stored);
    }

    private static function body(string $fixture): string
    {
        return rtrim((string)file_get_contents(__DIR__ . "/fixtures/webhooks/$fixture.json"), "\n");
    }

    /** One delivery as Shopify sends it, signed with the shop's secret unless another is given. */
    private static function delivery(string $shop, string $topic, string $eventId, string $raw, array $extra = [], ?string $secret = null): array
    {
        $secret ??= self::$secret;
        $domain = $shop === 'hml' ? 'hml-test.myshopify.com' : "$shop-test.myshopify.com";
        $headers = [
            'X-Shopify-Topic' => $topic,
            'X-Shopify-Hmac-Sha256' => base64_encode(hash_hmac('sha256', $raw, $secret, true)),
            'X-Shopify-Shop-Domain' => $domain,
            'X-Shopify-Event-Id' => $eventId,
            'X-Shopify-Webhook-Id' => 'wh-' . $eventId,
            'X-Shopify-Triggered-At' => '2026-10-27T08:12:44.123Z',
            'X-Shopify-API-Version' => '2026-07',
        ];
        foreach ($extra as $name => $value) {
            foreach (array_keys($headers) as $default) {
                if (strcasecmp($default, $name) === 0) {
                    unset($headers[$default]);
                }
            }
            $headers[$name] = $value;
        }
        return ['shop_key' => $shop, 'raw_b64' => base64_encode($raw), 'headers' => $headers];
    }

    private function receive(array $deliveries, string $option = ''): array
    {
        $file = (string)tempnam(sys_get_temp_dir(), 'sd748-deliveries');
        try {
            file_put_contents($file, json_encode($deliveries));
            $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_events.php', array_merge(['receive', self::$db, $file], $option === '' ? [] : [$option])));
        } finally {
            unlink($file);
        }
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'] + ['stderr' => $run['stderr']];
    }

    private function promote(int $limit, string $failType = ''): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_events.php', array_merge(['promote', self::$db, (string)$limit], $failType === '' ? [] : [$failType])));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'] + ['stderr' => $run['stderr']];
    }

    private static function answers(array $run): array
    {
        return array_map(fn(array $r): array => [$r['status'], $r['outcome']], $run['results']);
    }

    private static function promoted(array $run): array
    {
        return array_map(fn(array $e): array => [$e['topic'], $e['state']], $run['result']['events']);
    }

    private function events(): array
    {
        $conn = ShopifyEnv::connect(self::$db);
        try {
            return ShopifyEnv::rows($conn, 'SELECT * FROM shopify_events ORDER BY id');
        } finally {
            pg_close($conn);
        }
    }

    private function alarms(): array
    {
        $conn = ShopifyEnv::connect(self::$db);
        try {
            return ShopifyEnv::rows($conn, 'SELECT * FROM shopify_errors ORDER BY id');
        } finally {
            pg_close($conn);
        }
    }

    private function jobs(): array
    {
        $conn = ShopifyEnv::connect(self::$db);
        try {
            return ShopifyEnv::rows($conn, 'SELECT * FROM shopify_jobs ORDER BY id');
        } finally {
            pg_close($conn);
        }
    }
}
