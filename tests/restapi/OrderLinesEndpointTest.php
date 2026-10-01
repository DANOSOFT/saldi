<?php
// tests/restapi/OrderLinesEndpointTest.php
//
// Integration tests for /restapi/endpoints/v1/debitor/orderlines/ against
// the local docker stack's self-provisioned tenant: auth enforcement,
// parameter validation, adding a free-text line to a freshly created order,
// listing/reading it back, refusing lines on a posted order, and the
// disabled PUT/DELETE.
//
// History:
// 20260904 CL/NTR created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class OrderLinesEndpointTest extends TestCase
{
    private const ORDERS = '/restapi/endpoints/v1/debitor/orders/';
    private const LINES = '/restapi/endpoints/v1/debitor/orderlines/';

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
    }

    public static function tearDownAfterClass(): void
    {
        RestApiEnv::teardownTenant();
    }

    /** Create a fresh debtor order (auto-provisioning its debtor) and return its id. */
    private function createOrder(string $tag): int
    {
        $digits = (string)random_int(10000000, 99999999);
        $res = RestApiEnv::http('POST', self::ORDERS, [
            'companyName' => "Apitest Ordre $tag ApS",
            'phone' => $digits,
            'email' => "apitest-order-$tag-$digits@example.invalid",
            'vatRate' => 25,
        ], RestApiEnv::authHeaders());
        $this->assertSame(201, $res['status'], $res['body']);
        $id = (int)($res['json']['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $id, 'order created');
        return $id;
    }

    private function addLine(array $body): array
    {
        return RestApiEnv::http('POST', self::LINES, $body, RestApiEnv::authHeaders());
    }

    public function test_order_lines_require_a_bearer_token(): void
    {
        $res = RestApiEnv::http('GET', self::LINES . '?order_id=1');

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_get_without_order_or_line_id_is_rejected_400(): void
    {
        $res = RestApiEnv::http('GET', self::LINES, null, RestApiEnv::authHeaders());

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_create_without_order_id_is_rejected_400(): void
    {
        $res = $this->addLine(['description' => 'orphan line', 'quantity' => 1, 'price' => 10]);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('orderId', $res['json']['message']);
    }

    public function test_create_on_unknown_order_is_rejected_400(): void
    {
        $res = $this->addLine(['orderId' => 99999999, 'description' => 'nowhere', 'quantity' => 1, 'price' => 10]);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('Order not found', $res['json']['message']);
    }

    public function test_create_without_product_or_description_is_rejected_400(): void
    {
        $orderId = $this->createOrder('empty');

        $res = $this->addLine(['orderId' => $orderId, 'quantity' => 1, 'price' => 10]);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_free_text_line_is_added_and_can_be_listed_and_read_back(): void
    {
        $orderId = $this->createOrder('lines');

        $create = $this->addLine([
            'orderId' => $orderId,
            'description' => 'Apitest konsulenttime',
            'quantity' => 2,
            'price' => 100,
        ]);

        $this->assertSame(201, $create['status'], $create['body']);
        $this->assertTrue($create['json']['success']);
        $lineId = (int)($create['json']['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $lineId, 'line has an id');
        $this->assertEqualsWithDelta(200.0, (float)$create['json']['data']['total'], 0.001, 'total = price * quantity');

        $list = RestApiEnv::http('GET', self::LINES . '?order_id=' . $orderId, null, RestApiEnv::authHeaders());
        $this->assertSame(200, $list['status'], $list['body']);
        $this->assertCount(1, $list['json']['data']);
        $line = $list['json']['data'][0];
        $this->assertSame($lineId, (int)$line['id']);
        $this->assertSame($orderId, (int)$line['orderId']);
        $this->assertSame('Apitest konsulenttime', $line['description']);
        $this->assertEqualsWithDelta(2.0, (float)$line['quantity'], 0.001);
        $this->assertEqualsWithDelta(100.0, (float)$line['price'], 0.001);
        $this->assertSame(1, (int)$line['posNo'], 'first line gets position 1');

        $single = RestApiEnv::http('GET', self::LINES . '?id=' . $lineId, null, RestApiEnv::authHeaders());
        $this->assertSame(200, $single['status'], $single['body']);
        $this->assertSame('Apitest konsulenttime', $single['json']['data']['description']);
    }

    public function test_second_line_gets_the_next_position_number(): void
    {
        $orderId = $this->createOrder('posnr');
        $this->assertSame(201, $this->addLine(['orderId' => $orderId, 'description' => 'one', 'price' => 1])['status']);
        $this->assertSame(201, $this->addLine(['orderId' => $orderId, 'description' => 'two', 'price' => 2])['status']);

        $list = RestApiEnv::http('GET', self::LINES . '?order_id=' . $orderId, null, RestApiEnv::authHeaders());

        $this->assertSame(200, $list['status'], $list['body']);
        $this->assertSame(['one', 'two'], array_column($list['json']['data'], 'description'));
        $this->assertSame([1, 2], array_map('intval', array_column($list['json']['data'], 'posNo')));
    }

    public function test_lines_cannot_be_added_to_a_posted_order(): void
    {
        $orderId = $this->createOrder('posted');
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        RestApiEnv::rows($tenant, 'UPDATE ordrer SET status = 3 WHERE id = $1 RETURNING id', [$orderId]);
        pg_close($tenant);

        $res = $this->addLine(['orderId' => $orderId, 'description' => 'too late', 'price' => 10]);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('posted', $res['json']['message']);
    }

    public function test_reading_a_nonexistent_line_returns_404(): void
    {
        $res = RestApiEnv::http('GET', self::LINES . '?id=99999999', null, RestApiEnv::authHeaders());

        $this->assertSame(404, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_put_and_delete_are_not_supported_405(): void
    {
        $put = RestApiEnv::http('PUT', self::LINES, ['id' => 1], RestApiEnv::authHeaders());
        $this->assertSame(405, $put['status'], $put['body']);

        $del = RestApiEnv::http('DELETE', self::LINES . '?id=1', null, RestApiEnv::authHeaders());
        $this->assertSame(405, $del['status'], $del['body']);
    }
}
