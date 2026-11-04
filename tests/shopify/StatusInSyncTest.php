<?php
// tests/shopify/StatusInSyncTest.php
//
// Inbound order status against a cloned tenant (SD-801): fulfillments with tracking, the order's
// status, cancellations and refunds projected onto the order's link through the real promotion
// and worker run. K40: a fulfillment posts nothing, mails nothing and moves no stock. K41: an
// event before its order is deferred without a failed attempt and converges, webhooks in reverse
// order and a replay end with the same rows, and after 48 hours a deferred event is listed. The
// read models return what was projected.
//
// History:
// 20261103 CL/SS SD-801: created.
// 20261103 CL/SS SD-801 review: listed once and resolved; cancelled links, legacy shops and flow off.
// 20261103 CL/SS SD-801 review 2: a cancelled link resolves; a cut-over legacy shop; the flow-off ref, resolved once applied.
// 20261104 CL/SS SD-801 review 3: another event leaves a listed one listed; legacy orders are info; a cancellation not invoiced is listed at once.
// 20261104 CL/SS SD-801 review 3: a cancellation before the import resolves the waiting rows; a refund with the flow off is listed.
// 20261104 CL/SS SD-801 review 4: an older status leaves a newer listed one listed; a legacy order's refund still asks for the credit note.
// 20261104 CL/SS SD-801 review 5: a listed event whose write fails stays listed.
// 20261104 CL/SS SD-744: the EasyRoutes delivery date follows the newest ORDERS_UPDATED; the order screen's panel shows the order's Shopify side.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class StatusInSyncTest extends TestCase
{
    private const ORDER_GID = 'gid://shopify/Order/6011234567890';
    private const FIXTURES = __DIR__ . '/fixtures/webhooks/status/';
    /** Legacy tables a status event must never write (K40). */
    private const LEDGER = ['ordrer', 'ordrelinjer', 'batch_kob', 'batch_salg', 'lagerstatus', 'openpost', 'transaktioner', 'kassekladde'];

    private static ?string $skip = null;
    private static string $db = '';
    /** @var resource|\PgSql\Connection|null */
    private $conn = null;
    private int $events = 0;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_s');
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
        $this->conn = ShopifyEnv::connect(self::$db);
        // the manifest's times are wall time of the business zone, as the bootstrap sets it for the runner
        $this->sql("SELECT set_config('TimeZone', 'Europe/Copenhagen', false)");
        $this->reset();
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            pg_close($this->conn);
        }
    }

    private function reset(): void
    {
        foreach (['shopify_fulfillments', 'shopify_refunds', 'shopify_order_links', 'shopify_customer_links', 'shopify_events', 'shopify_jobs', 'shopify_errors', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM ordrer WHERE ordrenr BETWEEN 80100 AND 80199 AND art = 'DO'");
        $this->sql("DELETE FROM settings WHERE var_grp = 'shopify_legacy:4'");
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, orders_enabled) VALUES ('hml', 'hml-test.myshopify.com', true, true)");
    }

    private function sql(string $sql, array $params = []): array
    {
        return ShopifyEnv::rows($this->conn, $sql, $params);
    }

    private function steps(array ...$steps): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_status.php', [self::$db, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        foreach ($run['result']['results'] as $result) {
            $this->assertArrayNotHasKey('exception', (array)$result, json_encode($result));
        }
        return $run['result']['results'];
    }

    /** Promotes the stored events and runs one worker pass; the pass's counts. */
    private function pass(): array
    {
        return $this->steps(['promote'], ['work'])[1];
    }

    private static function body(string $file, array $change = []): array
    {
        return array_replace(json_decode((string)file_get_contents(self::FIXTURES . $file), true, 512, JSON_THROW_ON_ERROR), $change);
    }

    /** Stores a received webhook as the receiver does; $hoursAgo backdates its arrival. */
    private function deliver(string $topic, array $body, int $hoursAgo = 0): int
    {
        $this->events++;
        return (int)$this->sql(
            "INSERT INTO shopify_events (shop_id, topic, event_id, payload_b64, state, received_at)
             SELECT id, $1, $2, $3, 'received', now() - make_interval(hours => $4) FROM shopify_shops WHERE shop_key = 'hml' RETURNING id",
            [$topic, 'sd801-' . $this->events . '-' . bin2hex(random_bytes(4)), base64_encode(json_encode($body, JSON_UNESCAPED_SLASHES)), $hoursAgo]
        )[0]['id'];
    }

    /** The Saldi order and web link an import (SD-743) leaves; returns ordrer.id. */
    private function importOrder(int $status = 4, string $state = 'invoiced'): int
    {
        $id = (int)$this->sql("INSERT INTO ordrer (ordrenr, art, status, firmanavn, sum, moms) VALUES (80101, 'DO', $1, 'SD801 Kunde', 999.20, 249.80) RETURNING id", [$status])[0]['id'];
        $this->sql("INSERT INTO shopify_order_links (shop_id, order_gid, order_name, ordre_id, direction, state)
            SELECT id, $1, '#1042', $2, 'web', $3 FROM shopify_shops WHERE shop_key = 'hml'
            ON CONFLICT (shop_id, order_gid) DO UPDATE SET ordre_id = EXCLUDED.ordre_id, state = EXCLUDED.state", [self::ORDER_GID, $id, $state]);
        return $id;
    }

    private function fulfillments(bool $withTimes = false): array
    {
        return $this->sql('SELECT order_gid, fulfillment_gid, ordre_id IS NOT NULL AS has_ordre, status, tracking_company, tracking_number, tracking_url, shipped_at, payload_b64'
            . ($withTimes ? ', id, created_at, updated_at' : '') . ' FROM shopify_fulfillments ORDER BY fulfillment_gid');
    }

    private function counts(): array
    {
        $out = [];
        foreach (self::LEDGER as $table) {
            $out[$table] = $this->sql("SELECT count(*) AS n FROM $table")[0]['n'];
        }
        $out['ordrer_rows'] = $this->sql("SELECT ordrenr, status, sum, moms, fakturanr FROM ordrer WHERE ordrenr BETWEEN 80100 AND 80199 ORDER BY id");
        $out['mail_jobs'] = $this->sql("SELECT count(*) AS n FROM shopify_jobs WHERE type LIKE 'mail.%'")[0]['n'];
        return $out;
    }

    private function errors(): array
    {
        return $this->sql('SELECT kind, ref, severity, occurrences, message FROM shopify_errors WHERE resolved_at IS NULL ORDER BY kind, ref');
    }

    public function testAFulfillmentStoresStatusAndTrackingAndPostsMailsAndMovesNothing(): void
    {
        $ordreId = $this->importOrder();
        $before = $this->counts();
        $this->deliver('FULFILLMENTS_CREATE', self::body('fulfillments_create.json'));
        $run = $this->pass();

        $this->assertSame([1, 1], [$run['claimed'], $run['done']]);
        $row = $this->sql('SELECT ordre_id, status, tracking_company, tracking_number, tracking_url, shipped_at FROM shopify_fulfillments')[0];
        $this->assertSame([(string)$ordreId, 'success', 'PostNord', '00370730253779425581', 'https://tracking.postnord.com/dk/?id=00370730253779425581'],
            [$row['ordre_id'], $row['status'], $row['tracking_company'], $row['tracking_number'], $row['tracking_url']]);
        $this->assertSame('2026-11-02 14:05:11', $row['shipped_at'], 'afsendt: the fulfillment\'s created_at, in the business time zone (Europe/Copenhagen)');
        $this->assertSame($before, $this->counts(), 'K40: no ledger row, no stock movement, no order change, no mail job');
        $this->assertSame([], $this->errors());
    }

    public function testAFulfillmentBeforeItsOrderIsDeferredWithoutAFailureAndConverges(): void
    {
        $this->deliver('FULFILLMENTS_CREATE', self::body('fulfillments_create.json'));
        $this->deliver('FULFILLMENTS_UPDATE', self::body('fulfillments_update.json'));
        $run = $this->pass();

        $this->assertSame([2, 2, 0, 0], [$run['claimed'], $run['yielded'], $run['failed'], $run['dead']]);
        $this->assertSame([['state' => 'queued', 'attempts' => '0', 'last_error' => null, 'later' => 't'], ['state' => 'queued', 'attempts' => '0', 'last_error' => null, 'later' => 't']],
            $this->sql("SELECT state, attempts, last_error, next_run_at > now() + interval '4 minutes' AS later FROM shopify_jobs ORDER BY id"), 'deferred, not a failed attempt');
        $this->assertSame([], $this->fulfillments());
        $this->assertSame([], $this->errors(), 'no failure alarm while it waits');
        $this->assertSame('0', $this->sql("SELECT count(*) AS n FROM shopify_jobs WHERE type LIKE 'mail.%'")[0]['n']);

        // the import has reserved the link but not created the order yet
        $this->sql("INSERT INTO shopify_order_links (shop_id, order_gid, order_name, direction, state) SELECT id, $1, '#1042', 'web', 'validated' FROM shopify_shops", [self::ORDER_GID]);
        $this->sql("UPDATE shopify_jobs SET next_run_at = now() WHERE state = 'queued'");
        $this->assertSame(2, $this->pass()['yielded'], 'a link without its order still waits');

        $this->importOrder();
        $this->sql("UPDATE shopify_jobs SET next_run_at = now() WHERE state = 'queued'");
        $run = $this->pass();
        $this->assertSame([2, 2], [$run['claimed'], $run['done']]);
        $late = $this->fulfillments();

        $this->reset();
        $this->importOrder();
        $this->deliver('FULFILLMENTS_CREATE', self::body('fulfillments_create.json'));
        $this->deliver('FULFILLMENTS_UPDATE', self::body('fulfillments_update.json'));
        $this->pass();
        $this->assertSame($this->fulfillments(), $late, 'K41: the same rows as when the order came first');
        $this->assertSame('00370730253779425598', $late[0]['tracking_number']);
    }

    public function testWebhooksInReverseOrderAndAReplayEndWithTheSameRows(): void
    {
        $created = self::body('fulfillments_create.json');
        $fulfilled = self::body('orders_updated.json', ['fulfillments' => [$created]]);
        $sequence = [['FULFILLMENTS_CREATE', $created], ['ORDERS_UPDATED', self::body('orders_updated.json')],
            ['FULFILLMENTS_UPDATE', self::body('fulfillments_update.json')], ['ORDERS_FULFILLED', $fulfilled]];
        $state = function (): array {
            return [$this->fulfillments(), $this->sql("SELECT flags->'status' AS status FROM shopify_order_links")];
        };

        $this->importOrder();
        foreach ($sequence as [$topic, $body]) {
            $this->deliver($topic, $body);
            $this->pass();
        }
        $forward = $state();

        $this->reset();
        $this->importOrder();
        foreach (array_reverse($sequence) as [$topic, $body]) {
            $this->deliver($topic, $body);
            $this->pass();
        }
        $this->assertSame($forward, $state(), 'K41: each event on its own pass, in reverse order');
        $this->assertSame('00370730253779425598', $forward[0][0]['tracking_number'], 'the newest fulfillment wins over the older one inside ORDERS_FULFILLED');
        $this->assertEquals(['easyroutes_levdato' => null, 'financial_status' => 'paid', 'fulfillment_status' => 'fulfilled', 'updated_at' => '2026-11-02 13:05:12'], json_decode($forward[1][0]['status'], true));

        $written = [$this->fulfillments(true), $this->sql('SELECT flags, updated_at FROM shopify_order_links')];
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $run = $this->pass();
        $this->assertSame(4, $run['done']);
        $this->assertSame($written, [$this->fulfillments(true), $this->sql('SELECT flags, updated_at FROM shopify_order_links')], 'a replay writes nothing');
    }

    public function testAnOlderOrderUpdateDoesNotOverwriteTheStoredStatus(): void
    {
        $this->importOrder();
        $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json'));
        $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json', ['fulfillment_status' => null, 'updated_at' => '2026-11-02T09:13:00+01:00']));
        $this->pass();

        $this->assertSame('fulfilled', json_decode($this->sql("SELECT flags->'status' AS s FROM shopify_order_links")[0]['s'], true)['fulfillment_status']);
    }

    public function testTwoOrderUpdatesOfTheSameTimeEndTheSameInEitherOrder(): void
    {
        $paid = self::body('orders_updated.json');
        $refunded = self::body('orders_updated.json', ['financial_status' => 'partially_refunded']);
        $end = [];
        foreach ([[$paid, $refunded], [$refunded, $paid]] as $pair) {
            $this->reset();
            $this->importOrder();
            foreach ($pair as $body) {
                $this->deliver('ORDERS_UPDATED', $body);
                $this->pass();
            }
            $end[] = json_decode($this->sql("SELECT flags->'status' AS s FROM shopify_order_links")[0]['s'], true)['financial_status'];
        }
        $this->assertSame($end[0], $end[1], 'K41: a tie on updated_at is broken the same way whichever came first');
        $this->assertSame('partially_refunded', $end[0], 'the greater body wins the tie');
    }

    public function testAnOrdersFulfilledBodyProjectsEachOfItsFulfillments(): void
    {
        $this->importOrder();
        $second = self::body('fulfillments_create.json', ['id' => 5374851200002, 'admin_graphql_api_id' => 'gid://shopify/Fulfillment/5374851200002', 'tracking_number' => 'SECOND']);
        $this->deliver('ORDERS_FULFILLED', self::body('orders_updated.json', ['fulfillments' => [self::body('fulfillments_create.json'), $second]]));
        $this->pass();

        $this->assertSame(['SECOND', '00370730253779425581'], array_column($this->fulfillments(), 'tracking_number'));
    }

    public function testACancellationOfAnInvoicedOrderOpensLavKreditnotaOnce(): void
    {
        $ordreId = $this->importOrder();
        $before = $this->counts();
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'));
        $this->pass();

        $errors = $this->errors();
        $this->assertCount(1, $errors);
        $this->assertSame(['status.credit_note', self::ORDER_GID, 'error', '1'], [$errors[0]['kind'], $errors[0]['ref'], $errors[0]['severity'], $errors[0]['occurrences']]);
        $this->assertStringContainsString('Saldi order 80101 is invoiced: lav kreditnota', $errors[0]['message']);
        $this->assertNotNull($this->sql('SELECT cancelled_at FROM shopify_order_links')[0]['cancelled_at']);
        $this->assertSame(array_diff_key($before, ['mail_jobs' => 0]), array_diff_key($this->counts(), ['mail_jobs' => 0]), 'no credit note is made, nothing is posted');
        $this->assertSame([['type' => 'mail.alert']], $this->sql("SELECT type FROM shopify_jobs WHERE type LIKE 'mail.%'"), 'only the support alarm of the error');

        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $this->pass();
        $this->assertSame('1', $this->errors()[0]['occurrences'], 'a replay does not count it again');
        $this->assertSame($ordreId, (int)$this->sql('SELECT ordre_id FROM shopify_order_links')[0]['ordre_id']);
    }

    public function testACancellationNotInvoicedYetIsListedAtOnceAndAsksForTheCreditNoteOnceInvoiced(): void
    {
        $ordreId = $this->importOrder(2, 'created');
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'));
        $run = $this->pass();
        $this->assertSame(1, $run['yielded'], 'it still waits for an invoice');
        $errors = $this->errors();
        $this->assertSame([['status.cancelled', self::ORDER_GID, 'warning']], array_map(fn(array $e): array => [$e['kind'], $e['ref'], $e['severity']], $errors));
        $this->assertStringContainsString('Saldi order 80101 is not invoiced: do not ship or invoice it', $errors[0]['message']);
        $this->assertNotNull($this->sql('SELECT cancelled_at FROM shopify_order_links')[0]['cancelled_at'], 'the order shows as cancelled at once');

        $this->sql("UPDATE shopify_jobs SET next_run_at = now() WHERE state = 'queued'");
        $this->assertSame(1, $this->pass()['yielded']);
        $this->assertSame(['1'], array_column($this->errors(), 'occurrences'), 'a later run does not list it again');

        $this->sql('UPDATE ordrer SET status = 3 WHERE id = $1', [$ordreId]);
        $this->sql("UPDATE shopify_jobs SET next_run_at = now() WHERE state = 'queued'");
        $this->pass();
        $this->assertSame(['status.credit_note'], array_column($this->errors(), 'kind'), 'invoiced meanwhile (status 3, link still created): lav kreditnota replaces the warning');
    }

    public function testACancellationBeforeTheImportIsRecordedOnALinkWithoutAnOrder(): void
    {
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'));
        $this->pass();

        $link = $this->sql('SELECT order_gid, order_name, direction, state, ordre_id, cancelled_at IS NOT NULL AS cancelled FROM shopify_order_links');
        $this->assertSame([['order_gid' => self::ORDER_GID, 'order_name' => '#1042', 'direction' => 'web', 'state' => 'cancelled', 'ordre_id' => null, 'cancelled' => 't']], $link);
        $this->assertSame([['status.cancelled', 'info']], array_map(fn(array $e): array => [$e['kind'], $e['severity']], $this->errors()));
        $this->assertSame('0', $this->sql("SELECT count(*) AS n FROM ordrer WHERE ordrenr BETWEEN 80100 AND 80199")[0]['n']);

        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL, received_at = now() - interval '3 days'");
        $run = $this->pass();
        $this->assertSame([1, 0], [$run['done'], $run['yielded']], 'a replay of the recorded cancellation is done at once');
        $this->assertSame(['1'], array_column($this->errors(), 'occurrences'));
    }

    public function testARefundIsStoredForWp14AndAMatchedOneIsNeverRewritten(): void
    {
        $ordreId = $this->importOrder();
        $before = $this->counts();
        $this->deliver('REFUNDS_CREATE', self::body('refunds_create.json'));
        $this->pass();

        $row = $this->sql('SELECT refund_gid, ordre_id, amount, currency, state, transactions_b64, payload_b64 FROM shopify_refunds')[0];
        $this->assertSame(['gid://shopify/Refund/1009876543210', (string)$ordreId, '1249.000', 'DKK', 'received'],
            [$row['refund_gid'], $row['ordre_id'], $row['amount'], $row['currency'], $row['state']], 'the two successful transactions, not the declined one');
        $this->assertCount(3, json_decode(base64_decode($row['transactions_b64']), true));
        $this->assertSame($before, $this->counts(), 'K40: a refund posts nothing');
        $written = $this->sql('SELECT * FROM shopify_refunds');
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $this->pass();
        $this->assertSame($written, $this->sql('SELECT * FROM shopify_refunds'), 'a replay writes nothing');

        $this->sql("UPDATE shopify_refunds SET state = 'matched'");
        $this->deliver('REFUNDS_CREATE', self::body('refunds_create.json', ['processed_at' => '2026-11-03T09:00:00+01:00', 'transactions' => []]));
        $this->pass();
        $this->assertSame(['1249.000', 'matched'], array_values($this->sql('SELECT amount, state FROM shopify_refunds')[0]), 'WP14 owns it once matched');
    }

    public function testAnEventStillWithoutItsOrderAfter48HoursIsListedAndDone(): void
    {
        $this->deliver('FULFILLMENTS_CREATE', self::body('fulfillments_create.json'), 49);
        $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json'), 49);
        $this->deliver('REFUNDS_CREATE', self::body('refunds_create.json'), 47);
        $run = $this->pass();

        $this->assertSame([3, 2, 1], [$run['claimed'], $run['done'], $run['yielded']], 'the 47-hour refund still waits');
        $this->assertSame([['status.unlinked', 'fulfillment.upsert:' . self::ORDER_GID, 'warning'], ['status.unlinked', 'order.status:' . self::ORDER_GID, 'info']],
            array_map(fn(array $e): array => [$e['kind'], $e['ref'], $e['severity']], $this->errors()));
        $this->assertSame([], $this->fulfillments());
    }

    public function testAListedEventIsListedOnceAndResolvedWhenItsOrderComes(): void
    {
        $this->deliver('FULFILLMENTS_CREATE', self::body('fulfillments_create.json'), 49);
        $this->deliver('FULFILLMENTS_UPDATE', self::body('fulfillments_update.json'), 49);
        $this->pass();
        $this->sql("UPDATE shopify_errors SET alarmed_at = alarmed_at - interval '2 hours'");
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $this->pass();

        $this->assertSame(['2'], array_column($this->errors(), 'occurrences'), 'one occurrence per event; a replay of either adds nothing');
        $this->assertSame('1', $this->sql("SELECT count(*) AS n FROM shopify_jobs WHERE type = 'mail.alert'")[0]['n'], 'and mails nothing, also past the hourly throttle');

        $this->importOrder();
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL WHERE topic = 'FULFILLMENTS_CREATE'");
        $this->pass();
        $this->assertSame([$this->sql("SELECT id FROM shopify_events WHERE topic = 'FULFILLMENTS_UPDATE'")[0]['id']],
            array_map('strval', json_decode($this->sql("SELECT correlation->'event_row_ids' AS ids FROM shopify_errors WHERE resolved_at IS NULL")[0]['ids'], true)),
            'the replayed event is taken off the row; the other is still listed');
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL WHERE topic = 'FULFILLMENTS_UPDATE'");
        $this->pass();
        $this->assertSame(['00370730253779425598'], array_column($this->fulfillments(), 'tracking_number'), 'the replay applies once the order is there');
        $this->assertSame([], $this->errors(), 'and the last one resolves the listed row');
    }

    public function testEventsOfAnOrderCancelledBeforeTheImportAreNotWaitedFor(): void
    {
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'));
        $this->pass();
        $this->deliver('REFUNDS_CREATE', self::body('refunds_create.json'));
        $this->deliver('FULFILLMENTS_CREATE', self::body('fulfillments_create.json'), 49);
        $run = $this->pass();

        $this->assertSame([2, 0], [$run['done'], $run['yielded']], 'the link never gets an order');
        $this->assertSame([['status.cancelled', 'info']], array_map(fn(array $e): array => [$e['kind'], $e['severity']], $this->errors()), 'nothing listed for them');
        $this->assertSame([[], []], [$this->fulfillments(), $this->sql('SELECT * FROM shopify_refunds')]);
    }

    public function testACancellationWithoutALinkInALegacyShopAsksToLookForTheOrder(): void
    {
        $this->sql('UPDATE shopify_shops SET legacy_slot = 4');
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'));
        $this->pass();

        $errors = $this->errors();
        $this->assertSame(['status.cancelled', 'warning'], [$errors[0]['kind'], $errors[0]['severity']]);
        $this->assertStringContainsString('legacy integration (slot 4), whose intake may have imported it: look for the order in Saldi and make a credit note if it is invoiced (lav kreditnota)', $errors[0]['message']);
    }

    public function testAListedEventIsResolvedWhenItsOrderTurnsOutCancelledBeforeTheImport(): void
    {
        $this->deliver('FULFILLMENTS_CREATE', self::body('fulfillments_create.json'), 49);
        $this->pass();
        $this->assertSame(['status.unlinked'], array_column($this->errors(), 'kind'));
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'));
        $this->pass();
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL WHERE topic = 'FULFILLMENTS_CREATE'");
        $this->pass();

        $this->assertSame(['status.cancelled'], array_column($this->errors(), 'kind'), 'the same list as when the cancellation came first');
    }

    public function testACutOverLegacyShopListsACancellationBeforeImportAsInfoUnlessOlderThanGoLive(): void
    {
        $this->sql("UPDATE shopify_shops SET legacy_slot = 4");
        $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify_legacy:4', 'intake_enabled', '0')");
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'));
        $this->pass();
        $this->assertSame([['status.cancelled', 'info']], array_map(fn(array $e): array => [$e['kind'], $e['severity']], $this->errors()), 'intake closed: only the Shopify import can have it');

        $this->reset();
        $this->sql("UPDATE shopify_shops SET legacy_slot = 4, go_live_at = '2026-11-02 12:00:00'");
        $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify_legacy:4', 'intake_enabled', '0')");
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'));
        $this->pass();
        $this->assertSame([['status.cancelled', 'warning']], array_map(fn(array $e): array => [$e['kind'], $e['severity']], $this->errors()), 'intake closed, but created 09:12, before go-live: the legacy intake may have it');
    }

    public function testTheFlowOffWarningIsNotOverwrittenWhenTheCancellationIsAppliedLater(): void
    {
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'));
        $this->steps(['promote']);
        $this->sql('UPDATE shopify_shops SET orders_enabled = false');
        $this->steps(['work']);
        $this->sql('UPDATE shopify_shops SET orders_enabled = true');
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $this->pass();

        $this->assertSame([['status.cancelled', self::ORDER_GID . ':flow_off', 'warning']],
            array_map(fn(array $e): array => [$e['kind'], $e['ref'], $e['severity']], $this->sql('SELECT kind, ref, severity FROM shopify_errors WHERE resolved_at IS NOT NULL')),
            'the stale "not applied" warning is resolved, never overwritten');
        $this->assertSame([['status.cancelled', self::ORDER_GID, 'info']], array_map(fn(array $e): array => [$e['kind'], $e['ref'], $e['severity']], $this->errors()));
    }

    public function testACancellationWhileTheOrdersFlowIsOffIsListedOnce(): void
    {
        $this->importOrder();
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'));
        $this->steps(['promote']);
        $this->sql('UPDATE shopify_shops SET orders_enabled = false');
        $this->steps(['work']);
        $this->sql("UPDATE shopify_jobs SET state = 'queued', next_run_at = now(), finished_at = NULL WHERE type = 'order.cancelled'");
        $this->steps(['work']);

        $errors = $this->errors();
        $this->assertSame([['status.cancelled', 'warning', '1']], array_map(fn(array $e): array => [$e['kind'], $e['severity'], $e['occurrences']], $errors));
        $this->assertStringContainsString('orders flow of the shop is off', $errors[0]['message']);
        $this->assertNull($this->sql('SELECT cancelled_at FROM shopify_order_links')[0]['cancelled_at']);
    }

    public function testACancellationNotInvoicedWithin48HoursKeepsItsWarningAndIsAppliedOnceInvoiced(): void
    {
        $ordreId = $this->importOrder(2, 'created');
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'), 49);
        $run = $this->pass();

        $this->assertSame([1, 0], [$run['done'], $run['yielded']]);
        $this->assertSame([['status.cancelled', self::ORDER_GID, 'warning']], array_map(fn(array $e): array => [$e['kind'], $e['ref'], $e['severity']], $this->errors()),
            'the "do not ship or invoice" warning, not a status.unlinked');
        $this->assertNotNull($this->sql('SELECT cancelled_at FROM shopify_order_links')[0]['cancelled_at']);

        $this->sql('UPDATE ordrer SET status = 3 WHERE id = $1', [$ordreId]);
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $this->pass();
        $this->assertSame(['status.credit_note'], array_column($this->errors(), 'kind'), 'the replay after the invoice asks for the credit note and resolves the warning');
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $this->pass();
        $this->assertSame(['1'], array_column($this->errors(), 'occurrences'), 'once');
    }

    public function testAnotherEventOfTheOrderLeavesAListedEventListed(): void
    {
        $r1 = $this->deliver('REFUNDS_CREATE', self::body('refunds_create.json'), 49);
        $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json'), 49);
        $this->pass();
        $this->assertSame(['order.status:' . self::ORDER_GID, 'refund.upsert:' . self::ORDER_GID], array_column($this->errors(), 'ref'));

        $this->importOrder();
        $this->deliver('REFUNDS_CREATE', self::body('refunds_create.json', ['id' => 1009876543299, 'admin_graphql_api_id' => 'gid://shopify/Refund/1009876543299']));
        $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json', ['updated_at' => '2026-11-02T15:00:00+01:00']));
        $this->pass();
        $this->assertSame(['gid://shopify/Refund/1009876543299'], array_column($this->sql('SELECT refund_gid FROM shopify_refunds'), 'refund_gid'));
        $this->assertSame([['refund.upsert:' . self::ORDER_GID, [$r1]]],
            array_map(fn(array $e): array => [$e['ref'], json_decode($e['ids'], true)], $this->sql("SELECT ref, correlation->'event_row_ids' AS ids FROM shopify_errors WHERE resolved_at IS NULL")),
            'the listed refund stays listed; a newer order status replaces the listed one');

        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL WHERE id = $1", [$r1]);
        $this->pass();
        $this->assertCount(2, $this->sql('SELECT refund_gid FROM shopify_refunds'));
        $this->assertSame([], $this->errors(), 'its replay applies it and resolves the row');
    }

    public function testAListedEventWhoseWriteFailsStaysListed(): void
    {
        $body = self::body('fulfillments_create.json');
        unset($body['id'], $body['admin_graphql_api_id']);
        $listed = $this->deliver('FULFILLMENTS_CREATE', $body, 49);
        $this->pass();
        $this->importOrder();
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $run = $this->pass();

        $this->assertSame(1, $run['dead'] + $run['failed'], 'the fulfillment has no id, so its write throws');
        $this->assertSame([[(string)$listed]], array_map(fn(array $r): array => array_map('strval', json_decode($r['ids'], true)),
            $this->sql("SELECT correlation->'event_row_ids' AS ids FROM shopify_errors WHERE kind = 'status.unlinked' AND resolved_at IS NULL")),
            'still listed: it was never applied');
    }

    public function testACancellationListedWhileTheImportRanIsAppliedAndResolvedOnceTheOrderIsThere(): void
    {
        $this->sql("INSERT INTO shopify_order_links (shop_id, order_gid, order_name, direction, state) SELECT id, $1, '#1042', 'web', 'validated' FROM shopify_shops", [self::ORDER_GID]);
        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'), 49);
        $this->pass();
        $this->assertSame([['status.unlinked', 'order.cancelled:' . self::ORDER_GID]], array_map(fn(array $e): array => [$e['kind'], $e['ref']], $this->errors()));

        $this->importOrder();
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $this->pass();
        $this->assertSame(['status.credit_note'], array_column($this->errors(), 'kind'), 'applied, and the waiting row is resolved');
    }

    public function testAnOlderOrderStatusLeavesANewerListedOneListed(): void
    {
        $newer = $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json', ['financial_status' => 'refunded', 'updated_at' => '2026-11-02T16:00:00+01:00']), 49);
        $this->pass();
        $this->importOrder();
        $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json'));
        $this->pass();
        $this->assertSame(['order.status:' . self::ORDER_GID], array_column($this->errors(), 'ref'), 'the older paid status does not replace the listed refunded one');

        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL WHERE id = $1", [$newer]);
        $this->pass();
        $this->assertSame('refunded', json_decode($this->sql("SELECT flags->'status' AS s FROM shopify_order_links")[0]['s'], true)['financial_status']);
        $this->assertSame([], $this->errors());
    }

    public function testAnUnlinkedEventOfAnOrderTheLegacyIntakeMayHaveIsInfo(): void
    {
        $listed = function (?string $goLive, bool $intakeOpen, bool $orderEvent, string $topic = 'FULFILLMENTS_CREATE'): array {
            $this->reset();
            $this->sql('UPDATE shopify_shops SET legacy_slot = 4, go_live_at = $1', [$goLive]);
            if (!$intakeOpen) {
                $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify_legacy:4', 'intake_enabled', '0')");
            }
            if ($orderEvent) {
                $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json'));
            }
            $this->deliver($topic, self::body(strtolower($topic) . '.json'), 49);
            $this->pass();
            return $this->sql("SELECT severity, message FROM shopify_errors WHERE kind = 'status.unlinked' AND ref NOT LIKE 'order.status:%'")[0];
        };

        $legacy = $listed('2026-11-02 12:00:00', false, true);
        $this->assertSame('info', $legacy['severity'], 'created 09:12, before go-live (from its ORDERS_UPDATED)');
        $this->assertStringContainsString("legacy integration (slot 4) may have imported the order: nothing to do if Saldi has it", $legacy['message']);
        $this->assertSame('info', $listed(null, true, false)['severity'], 'the legacy intake is still open');
        $this->assertSame('warning', $listed('2026-11-01 12:00:00', false, true)['severity'], 'created after go-live: only the Shopify import could have it');
        $this->assertSame('warning', $listed('2026-11-02 12:00:00', false, false)['severity'], 'no order event to date it');
        $refund = $listed('2026-11-02 12:00:00', false, true, 'REFUNDS_CREATE');
        $this->assertSame('warning', $refund['severity'], 'money went back: a refund of a legacy order still mails');
        $this->assertStringContainsString('may have imported the order: if Saldi has it invoiced, make a credit note (lav kreditnota)', $refund['message']);
    }

    public function testACancellationBeforeTheImportResolvesTheWaitingRowsOfItsOrder(): void
    {
        $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json'), 49);
        $this->deliver('FULFILLMENTS_CREATE', self::body('fulfillments_create.json'), 49);
        $this->pass();
        $this->assertSame(['fulfillment.upsert:' . self::ORDER_GID, 'order.status:' . self::ORDER_GID], array_column($this->errors(), 'ref'));

        $this->deliver('ORDERS_CANCELLED', self::body('orders_cancelled.json'));
        $this->pass();
        $this->assertSame([['status.cancelled', self::ORDER_GID]], array_map(fn(array $e): array => [$e['kind'], $e['ref']], $this->errors()), 'without a replay of the listed events');
    }

    public function testARefundWhileTheOrdersFlowIsOffIsListedOnceAndResolvedWhenStored(): void
    {
        $this->importOrder();
        $this->deliver('REFUNDS_CREATE', self::body('refunds_create.json'));
        $this->steps(['promote']);
        $this->sql('UPDATE shopify_shops SET orders_enabled = false');
        $this->steps(['work']);
        $this->sql("UPDATE shopify_jobs SET state = 'queued', next_run_at = now(), finished_at = NULL WHERE type = 'refund.upsert'");
        $this->steps(['work']);

        $errors = $this->errors();
        $this->assertSame([['status.refund', 'gid://shopify/Refund/1009876543210:flow_off', 'warning', '1']],
            array_map(fn(array $e): array => [$e['kind'], $e['ref'], $e['severity'], $e['occurrences']], $errors));
        $this->assertStringContainsString('orders flow of the shop is off and was not stored', $errors[0]['message']);
        $this->assertSame([], $this->sql('SELECT * FROM shopify_refunds'));

        $this->sql('UPDATE shopify_shops SET orders_enabled = true');
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $this->pass();
        $this->assertCount(1, $this->sql('SELECT * FROM shopify_refunds'));
        $this->assertSame([], $this->errors(), 'the replay stores it and resolves the warning');
    }

    public function testAnEventOfAShopWhoseOrdersFlowWentOffIsSkipped(): void
    {
        $this->importOrder();
        $this->deliver('FULFILLMENTS_CREATE', self::body('fulfillments_create.json'));
        $this->steps(['promote']);
        $this->sql('UPDATE shopify_shops SET orders_enabled = false');
        $run = $this->steps(['work'])[0];

        $this->assertSame(1, $run['done']);
        $this->assertSame([], $this->fulfillments());
    }

    public function testABodyWithoutItsOrderEndsTheJobAtOnce(): void
    {
        $this->importOrder();
        $body = self::body('fulfillments_create.json');
        unset($body['order_id']);
        $this->deliver('FULFILLMENTS_CREATE', $body);
        $run = $this->pass();

        $this->assertSame([1, 0], [$run['dead'], $run['failed']], 'a body that names no order is not retried');
        $this->assertSame([], $this->fulfillments());
    }

    private static function dated(string $date, string $updatedAt = '2026-11-02T14:05:12+01:00'): array
    {
        return self::body('orders_updated.json', ['updated_at' => $updatedAt, 'note_attributes' => [['name' => 'Gavebesked', 'value' => 'Tillykke'], ['name' => 'leveringsdato', 'value' => $date]]]);
    }

    private function easyroutes(): ?string
    {
        return $this->sql('SELECT easyroutes_levdato FROM shopify_order_links')[0]['easyroutes_levdato'] ?? null;
    }

    public function testTheEasyRoutesDateFollowsTheNewestOrderUpdate(): void
    {
        $this->importOrder();
        $this->deliver('ORDERS_UPDATED', self::dated('2026-11-06'));
        $this->pass();
        $this->assertSame('2026-11-06', $this->easyroutes(), 'its note attribute, the name in any case');
        $written = $this->sql('SELECT flags, easyroutes_levdato, updated_at FROM shopify_order_links');
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $this->pass();
        $this->assertSame($written, $this->sql('SELECT flags, easyroutes_levdato, updated_at FROM shopify_order_links'), 'a replay writes nothing');

        $this->deliver('ORDERS_UPDATED', self::dated('2026-11-01', '2026-11-02T10:00:00+01:00'));
        $this->pass();
        $this->assertSame('2026-11-06', $this->easyroutes(), 'an older update does not overwrite it (K41)');
        $this->deliver('ORDERS_UPDATED', self::dated('2026-11-09', '2026-11-03T08:00:00+01:00'));
        $this->pass();
        $this->assertSame('2026-11-09', $this->easyroutes(), 'a newer one moves it');
        $this->deliver('ORDERS_UPDATED', self::dated('9. november', '2026-11-03T09:00:00+01:00'));
        $this->pass();
        $this->assertNull($this->easyroutes(), 'not a date: none');
        $this->deliver('ORDERS_UPDATED', self::dated('2026-11-10garbage', '2026-11-03T09:10:00+01:00'));
        $this->pass();
        $this->assertNull($this->easyroutes(), 'the whole value must be the date');
        $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json', ['updated_at' => '2026-11-03T10:00:00+01:00', 'note_attributes' => []]));
        $this->deliver('ORDERS_UPDATED', self::dated('2026-11-12', '2026-11-03T09:30:00+01:00'));
        $this->pass();
        $this->assertNull($this->easyroutes(), 'the newest body no longer carries it, whichever came last');
    }

    public function testTwoUpdatesOfTheSameTimeWithOtherDatesEndTheSameInEitherOrder(): void
    {
        $end = [];
        foreach ([['2026-11-06', '2026-11-09'], ['2026-11-09', '2026-11-06']] as $pair) {
            $this->reset();
            $this->importOrder();
            foreach ($pair as $date) {
                $this->deliver('ORDERS_UPDATED', self::dated($date));
                $this->pass();
            }
            $end[] = $this->easyroutes();
            // the first body replayed after the second must not take it back
            $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL WHERE id = (SELECT min(id) FROM shopify_events)");
            $this->pass();
            $this->assertSame(end($end), $this->easyroutes(), 'a replay of the other body changes nothing');
        }
        $this->assertSame($end[0], $end[1], 'K41: a tie on updated_at is broken over the date as well, whichever came first');
    }

    public function testAStatusStoredBeforeTheDateWasReadDoesNotDropANewerListedOne(): void
    {
        $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json', ['financial_status' => 'refunded']), 49);
        $this->pass();
        $this->importOrder();
        // projected before this change: no date in the status
        $this->sql("UPDATE shopify_order_links SET flags = flags || jsonb_build_object('status', jsonb_build_object('financial_status', 'paid', 'fulfillment_status', 'fulfilled', 'updated_at', '2026-11-02 13:05:12'))");
        $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json', ['financial_status' => 'authorized']));
        $this->pass();

        $this->assertSame('paid', json_decode($this->sql("SELECT flags->'status' AS s FROM shopify_order_links")[0]['s'], true)['financial_status'], 'the stored status still wins its tie');
        $this->assertSame(['order.status:' . self::ORDER_GID], array_column($this->errors(), 'ref'), 'the listed refunded, which would win it too, stays listed');
    }

    public function testAnEasyRoutesDateBeforeItsOrderWaitsAndAStatusStoredBeforeTheDateWasReadGetsItOnReplay(): void
    {
        $this->deliver('ORDERS_UPDATED', self::dated('2026-11-06'));
        $this->assertSame(1, $this->pass()['yielded'], 'deferred, not failed (K41)');
        $this->importOrder();
        $this->sql("UPDATE shopify_jobs SET next_run_at = now() WHERE state = 'queued'");
        $this->pass();
        $this->assertSame('2026-11-06', $this->easyroutes());

        // a status projected before this change: the same body, without the date in it or in the column
        $this->sql("UPDATE shopify_order_links SET easyroutes_levdato = NULL, flags = jsonb_set(flags, '{status}', (flags->'status') - 'easyroutes_levdato')");
        $this->sql("UPDATE shopify_events SET state = 'received', processed_at = NULL");
        $this->pass();
        $this->assertSame('2026-11-06', $this->easyroutes(), 'the replay of the stored status sets its date');
    }

    public function testTheOrderScreenPanelShowsTheShopifySideOfTheOrderOnly(): void
    {
        $ordreId = $this->importOrder();
        $shipped = self::body('fulfillments_create.json', ['id' => 5374851200009, 'admin_graphql_api_id' => 'gid://shopify/Fulfillment/5374851200009',
            'tracking_number' => "<b>X1</b>", 'tracking_url' => 'https://track.example/?id=<1>&a=b']);
        $this->deliver('FULFILLMENTS_CREATE', $shipped);
        $this->deliver('FULFILLMENTS_UPDATE', self::body('fulfillments_update.json', ['tracking_url' => 'javascript:alert(1)']));
        $this->deliver('ORDERS_UPDATED', self::dated('2026-11-06'));
        $this->deliver('REFUNDS_CREATE', self::body('refunds_create.json'));
        $this->pass();
        $other = (int)$this->sql("INSERT INTO ordrer (ordrenr, art, status, firmanavn) VALUES (80102, 'DO', 1, 'Ikke Shopify') RETURNING id")[0]['id'];
        [$panel, $none] = $this->steps(['panel', $ordreId], ['panel', $other]);

        $this->assertSame('', $none, 'an order without a Shopify link shows nothing new');
        foreach (['Shopify-ordre</b></td><td>#1042 (hml)', 'Betaling</b></td><td>paid', 'Levering</b></td><td>fulfilled', 'Leveringsdato (EasyRoutes)</b></td><td>06-11-2026',
            'Afsendt</b></td><td>02-11-2026', 'Refunderet</b></td><td>', '1.249,00 DKK'] as $needle) {
            $this->assertStringContainsString($needle, $panel);
        }
        $this->assertStringContainsString("<a href='https://track.example/?id=&lt;1&gt;&amp;a=b' target='_blank' rel='noopener noreferrer'>PostNord &lt;b&gt;X1&lt;/b&gt;</a>", $panel, 'escaped, and linked');
        $this->assertStringNotContainsString('javascript:', $panel, 'only an http(s) tracking URL is a link');
        $this->assertStringNotContainsString('<b>X1</b>', $panel);
        $this->assertStringNotContainsString('Annulleret', $panel);

        $this->sql("UPDATE shopify_order_links SET cancelled_at = '2026-11-03 08:30:00'");
        $this->assertStringContainsString('Annulleret i Shopify</b></td><td>03-11-2026', $this->steps(['panel', $ordreId])[0]);
        $version = $this->sql("SELECT var_value FROM settings WHERE var_grp = 'shopify' AND var_name = 'schema_version'")[0]['var_value'];
        $this->sql("UPDATE settings SET var_value = '0' WHERE var_grp = 'shopify' AND var_name = 'schema_version'");
        try {
            $this->assertSame('', $this->steps(['panel', $ordreId])[0], 'a tenant without the Shopify schema shows nothing');
        } finally {
            $this->sql("UPDATE settings SET var_value = $1 WHERE var_grp = 'shopify' AND var_name = 'schema_version'", [$version]);
        }
    }

    public function testTheReadModelsReturnWhatWasProjected(): void
    {
        $ordreId = $this->importOrder();
        $first = self::body('fulfillments_create.json', ['id' => 5374851200001, 'admin_graphql_api_id' => 'gid://shopify/Fulfillment/5374851200001',
            'created_at' => '2026-11-02T10:00:00+01:00', 'updated_at' => '2026-11-02T10:00:00+01:00', 'tracking_company' => 'GLS', 'tracking_number' => null,
            'tracking_numbers' => ['GLS1', 'GLS2'], 'tracking_url' => null, 'tracking_urls' => []]);
        $this->deliver('FULFILLMENTS_CREATE', $first);
        $this->deliver('FULFILLMENTS_UPDATE', self::body('fulfillments_update.json'));
        $this->deliver('ORDERS_UPDATED', self::body('orders_updated.json'));
        $this->deliver('REFUNDS_CREATE', self::body('refunds_create.json'));
        $this->pass();
        $adresser = (int)$this->sql("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('SD801X', 'SD801 Kunde', 'D') RETURNING id")[0]['id'];
        $this->sql("INSERT INTO shopify_customer_links (shop_id, customer_gid, adresser_id) SELECT id, 'gid://shopify/Customer/7612345678906', $1 FROM shopify_shops", [$adresser]);
        [$model, $none, $debtor] = $this->steps(['read_model', $ordreId], ['read_model', $ordreId + 1000000], ['debtor_model', $adresser]);
        $this->sql("DELETE FROM adresser WHERE id = $1", [$adresser]);

        $this->assertSame(['hml', self::ORDER_GID, '#1042', 'web', 'invoiced', null, null], [$model['shop_key'], $model['order_gid'], $model['order_name'], $model['direction'], $model['state'], $model['cancelled_at'], $model['easyroutes_levdato']]);
        $this->assertSame(['paid', 'fulfilled'], [$model['status']['financial_status'], $model['status']['fulfillment_status']]);
        $this->assertArrayNotHasKey('flags', $model, 'only the status namespace, not the intake or writeback internals');
        $this->assertSame([['gid://shopify/Fulfillment/5374851219514', 'PostNord', '00370730253779425598', 'in_transit'], ['gid://shopify/Fulfillment/5374851200001', 'GLS', 'GLS1', null]],
            array_map(fn(array $f): array => [$f['fulfillment_gid'], $f['tracking_company'], $f['tracking_number'], $f['shipment_status']], $model['fulfillments']), 'newest first; the first of a tracking list');
        $this->assertNull($model['fulfillments'][1]['tracking_url']);
        $this->assertNotNull($model['fulfillments'][0]['shipped_at']);
        $this->assertSame([['gid://shopify/Refund/1009876543210', '1249.000', 'DKK', 'received']],
            array_map(fn(array $r): array => [$r['refund_gid'], $r['amount'], $r['currency'], $r['state']], $model['refunds']));
        $this->assertNull($none);
        $this->assertSame([['shop_key' => 'hml', 'customer_gid' => 'gid://shopify/Customer/7612345678906', 'source_updated_at' => null]], $debtor);
    }
}
