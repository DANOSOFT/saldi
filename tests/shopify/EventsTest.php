<?php
// tests/shopify/EventsTest.php
//
// The parts of the webhook inbox (includes/shopify/events.php, SD-748) that need no database:
// the HMAC check, the request headers, topic names and the routing of events to jobs. Runs in
// its own processes: events.php brings config.php, and its shopify_secret() would change the
// token lookup that ClientRetryTest checks in the shared process.
//
// History:
// 20261027 CL/SZ SD-748: created.

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class EventsTest extends TestCase
{
    private const BODY = '{"id":820982911946154508,"admin_graphql_api_id":"gid://shopify/Order/820982911946154508"}';

    /**
     * The base64 HMAC-SHA256 by RFC 2104, built on hash('sha256') and not on hash_hmac(), so the
     * check under test is compared with an independent computation.
     */
    private static function referenceHmac(string $data, string $key): string
    {
        if (strlen($key) > 64) {
            $key = hash('sha256', $key, true);
        }
        $key = str_pad($key, 64, "\0");
        $inner = hash('sha256', ($key ^ str_repeat("\x36", 64)) . $data, true);
        return base64_encode(hash('sha256', ($key ^ str_repeat("\x5c", 64)) . $inner, true));
    }

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/shopify/events.php';
        require_once __DIR__ . '/../../includes/shopify/queue.php';
    }

    private static function fixture(string $name): array
    {
        return json_decode((string)file_get_contents(__DIR__ . "/fixtures/webhooks/$name.json"), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function event(string $topic, string $eventId = 'e-1', int $id = 7): array
    {
        return ['id' => (string)$id, 'shop_id' => '3', 'topic' => $topic, 'event_id' => $eventId, 'shop_key' => 'hml'];
    }

    public function testTheExactBytesPassAndAnyChangedByteFails(): void
    {
        $secret = bin2hex(random_bytes(16));
        $signature = self::referenceHmac(self::BODY, $secret);
        $this->assertTrue(shopify_verify_hmac(self::BODY, $signature, $secret));
        $this->assertTrue(shopify_verify_hmac(self::BODY, ' ' . $signature . "\n", $secret), 'whitespace around the header value');
        for ($i = 0; $i < strlen(self::BODY); $i++) {
            $changed = self::BODY;
            $changed[$i] = chr(ord($changed[$i]) ^ 1);
            $this->assertFalse(shopify_verify_hmac($changed, $signature, $secret), "byte $i changed");
        }
        $this->assertFalse(shopify_verify_hmac(self::BODY . "\n", $signature, $secret), 'one byte added');
        $this->assertFalse(shopify_verify_hmac(substr(self::BODY, 0, -1), $signature, $secret), 'one byte removed');
        $long = random_bytes(100);
        $this->assertTrue(shopify_verify_hmac(self::BODY, self::referenceHmac(self::BODY, $long), $long), 'a key longer than the block size');
    }

    public function testAWrongSecretOrAMissingSignatureFails(): void
    {
        $secret = bin2hex(random_bytes(16));
        $signature = self::referenceHmac(self::BODY, $secret);
        $this->assertFalse(shopify_verify_hmac(self::BODY, $signature, $secret . 'x'));
        $this->assertFalse(shopify_verify_hmac(self::BODY, '', $secret));
        $this->assertFalse(shopify_verify_hmac(self::BODY, self::referenceHmac(self::BODY, ''), ''), 'an empty secret never verifies');
        $this->assertFalse(shopify_verify_hmac(self::BODY, bin2hex(base64_decode($signature)), $secret), 'hex is not base64');
    }

    public function testOnlyTheShopifyHeadersAreTakenWithLowerCaseNames(): void
    {
        $headers = shopify_webhook_headers([
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/paid',
            'HTTP_X_SHOPIFY_HMAC_SHA256' => 'c2lnbmF0dXJl',
            'HTTP_X_SHOPIFY_EVENT_ID' => '98880550-7158-44d4-b7cd-2c97c8a091b5',
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'hml-test.myshopify.com',
            'HTTP_HOST' => 'saldi.example',
            'REQUEST_METHOD' => 'POST',
            'HTTP_X_SHOPIFY_ODD' => ['not', 'a', 'string'],
        ]);
        $this->assertSame([
            'x-shopify-topic' => 'orders/paid',
            'x-shopify-hmac-sha256' => 'c2lnbmF0dXJl',
            'x-shopify-event-id' => '98880550-7158-44d4-b7cd-2c97c8a091b5',
            'x-shopify-shop-domain' => 'hml-test.myshopify.com',
        ], $headers);
    }

    public function testTopicHeadersBecomeTheTopicNamesOfThePlan(): void
    {
        $this->assertSame('ORDERS_PAID', shopify_event_topic('orders/paid'));
        $this->assertSame('FULFILLMENTS_CREATE', shopify_event_topic(' Fulfillments/Create '));
        $this->assertSame('CUSTOMERS_DATA_REQUEST', shopify_event_topic('customers/data_request'));
        foreach (['', 'orders', 'orders/paid/extra', 'orders/pa id', "orders\n/paid", '../etc', 'orders/paid;drop'] as $bad) {
            $this->assertNull(shopify_event_topic($bad), var_export($bad, true));
        }
    }

    public function testEverySubscribedTopicRoutesToItsJobAndPriority(): void
    {
        $cases = [
            'ORDERS_PAID' => ['order.import', 10, 'orders_paid'],
            'ORDERS_CREATE' => ['order.observed', 25, 'orders_create'],
            'ORDERS_UPDATED' => ['order.status', 25, 'orders_create'],
            'ORDERS_CANCELLED' => ['order.cancelled', 25, 'orders_create'],
            'ORDERS_FULFILLED' => ['fulfillment.upsert', 25, 'orders_create'],
            'FULFILLMENTS_CREATE' => ['fulfillment.upsert', 25, 'fulfillments_create'],
            'FULFILLMENTS_UPDATE' => ['fulfillment.upsert', 25, 'fulfillments_create'],
            'CUSTOMERS_CREATE' => ['customer.upsert', 25, 'customers_create'],
            'CUSTOMERS_UPDATE' => ['customer.upsert', 25, 'customers_create'],
            'REFUNDS_CREATE' => ['refund.upsert', 25, 'refunds_create'],
        ];
        foreach ($cases as $topic => [$type, $priority, $fixture]) {
            $job = shopify_route_event(self::event($topic, 'ev-42'), self::fixture($fixture));
            $this->assertSame($type, $job['type'], $topic);
            $this->assertSame($priority, $job['priority'], $topic);
            $this->assertSame(7, $job['payload']['event_row_id'], $topic);
            $this->assertSame(7, $job['correlation']['event_row_id'], $topic);
            if ($topic === 'ORDERS_PAID') {
                $this->assertSame('hml:gid://shopify/Order/820982911946154508', $job['dedupe_key'], 'order.import dedupes on the order GID');
            } else {
                $this->assertSame("hml:$topic:ev-42", $job['dedupe_key'], "$topic dedupes on the event");
            }
            $expectedGid = $fixture === 'customers_create' ? null : 'gid://shopify/Order/820982911946154508';
            $this->assertSame($expectedGid, $job['correlation']['order_gid'] ?? null, "$topic order GID");
        }
        $this->assertSame(SHOPIFY_QUEUE_PRIORITY['order.import'], 10, 'the priorities come from the queue table');
    }

    public function testTwoPaidEventsOfOneOrderShareTheJobAndOtherTopicsNever(): void
    {
        $paid = self::fixture('orders_paid');
        $first = shopify_route_event(self::event('ORDERS_PAID', 'ev-1', 1), $paid);
        $second = shopify_route_event(self::event('ORDERS_PAID', 'ev-2', 2), $paid);
        $this->assertSame($first['dedupe_key'], $second['dedupe_key']);

        $created = shopify_route_event(self::event('ORDERS_CREATE', 'ev-1', 3), $paid);
        $updated = shopify_route_event(self::event('ORDERS_UPDATED', 'ev-1', 4), $paid);
        $this->assertNotSame($created['dedupe_key'], $updated['dedupe_key'], 'one event id shared by two topics');
    }

    public function testThePaidOrderIdIsTakenFromTheGidOrTheNumericId(): void
    {
        $this->assertSame('hml:gid://shopify/Order/5', shopify_route_event(self::event('ORDERS_PAID'), ['id' => 5])['dedupe_key']);
        $this->assertSame('hml:gid://shopify/Order/5', shopify_route_event(self::event('ORDERS_PAID'), ['id' => '5'])['dedupe_key']);
        $this->assertSame('hml:gid://shopify/Order/9', shopify_route_event(self::event('ORDERS_PAID'), ['admin_graphql_api_id' => 'gid://shopify/Order/9', 'id' => 5])['dedupe_key']);
        foreach ([[], ['id' => 0], ['id' => -3], ['id' => '12a'], ['id' => 1.5], ['admin_graphql_api_id' => 'gid://shopify/Customer/9']] as $payload) {
            try {
                shopify_route_event(self::event('ORDERS_PAID'), $payload);
                $this->fail('no order id in ' . json_encode($payload));
            } catch (InvalidArgumentException $e) {
                $this->assertSame('ORDERS_PAID without an order id', $e->getMessage());
            }
        }
    }

    public function testTopicsWithoutAJobAreNotRouted(): void
    {
        foreach (['PRODUCTS_UPDATE', 'INVENTORY_LEVELS_UPDATE', 'CUSTOMERS_DATA_REQUEST', 'CUSTOMERS_REDACT', 'SHOP_REDACT', 'UNKNOWN', 'ORDERS_EDITED'] as $topic) {
            $this->assertNull(shopify_route_event(self::event($topic), self::fixture('orders_paid')), $topic);
        }
    }

    public function testTriggeredAtIsReadOnlyAsAnIsoTime(): void
    {
        $this->assertSame('2026-10-27T08:12:44.123+00:00', shopify_event_time('2026-10-27T08:12:44.123Z')->format('Y-m-d\TH:i:s.vP'));
        $this->assertSame('2026-10-27T09:12:44+01:00', shopify_event_time('2026-10-27T09:12:44+01:00')->format('c'));
        foreach ([null, '', 'yesterday', '2026-10-27', '2026-13-45T99:00:00Z', '@1700000000'] as $bad) {
            $this->assertNull(shopify_event_time($bad), var_export($bad, true));
        }
    }
}
