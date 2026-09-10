<?php
// tests/restapi/OrdersEndpointTest.php
//
// Integration tests for /restapi/endpoints/v1/debitor/orders/ (SD-602):
// auth enforcement, list, create, read-back — against the local docker
// stack's self-provisioned tenant. Also covers the invoices endpoint's
// list shape (read-only).
//
// Note: the REST API has no voucher endpoint (the old restapi/tests
// VoucherEndpointTest targeted a /vouchers path that does not exist in the
// repo). Voucher/kassekladde behavior is covered at engine level by
// tests/characterization/ (SD-601).
//
// History:
// 20260723 CL/LH SD-602: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class OrdersEndpointTest extends TestCase
{
    private const ORDERS = '/restapi/endpoints/v1/debitor/orders/';
    private const INVOICES = '/restapi/endpoints/v1/debitor/invoices/';

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
    }

    private function authHeaders(): array
    {
        return ['Authorization: Bearer ' . RestApiEnv::accessToken()];
    }

    public function test_orders_require_a_bearer_token(): void
    {
        $res = RestApiEnv::http('GET', self::ORDERS);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_orders_reject_a_garbage_token(): void
    {
        $res = RestApiEnv::http('GET', self::ORDERS, null, ['Authorization: Bearer not.a.token']);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_order_list_returns_success_and_array_data(): void
    {
        $res = RestApiEnv::http('GET', self::ORDERS . '?limit=5', null, $this->authHeaders());

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertTrue($res['json']['success']);
        $this->assertIsArray($res['json']['data']);
    }

    public function test_created_order_can_be_read_back_with_its_debtor(): void
    {
        $create = RestApiEnv::http('POST', self::ORDERS, [
            'companyName' => 'Apitest Kunde ApS',
            'phone' => '99887766',
            'email' => 'apitest-order@example.invalid',
            'vatRate' => 25,
        ], $this->authHeaders());

        $this->assertSame(201, $create['status'], $create['body']);
        $this->assertTrue($create['json']['success']);
        $orderId = (int)($create['json']['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $orderId, 'created order has an id');

        $read = RestApiEnv::http('GET', self::ORDERS . '?id=' . $orderId, null, $this->authHeaders());

        $this->assertSame(200, $read['status'], $read['body']);
        $this->assertTrue($read['json']['success']);
        $order = $read['json']['data'];
        $this->assertSame($orderId, (int)$order['id']);
        $this->assertSame('Apitest Kunde ApS', $order['companyName'] ?? $order['firmanavn']);

        // The debtor was auto-created in the tenant db.
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $debtor = RestApiEnv::rows(
            $tenant,
            "SELECT id, art FROM adresser WHERE firmanavn = 'Apitest Kunde ApS' AND art = 'D'"
        );
        pg_close($tenant);
        $this->assertNotEmpty($debtor, 'order creation provisions the debtor');
    }

    public function test_reading_a_nonexistent_order_returns_404(): void
    {
        $res = RestApiEnv::http('GET', self::ORDERS . '?id=99999999', null, $this->authHeaders());

        $this->assertSame(404, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_invoice_list_requires_token_and_returns_array_when_authorized(): void
    {
        $unauth = RestApiEnv::http('GET', self::INVOICES);
        $this->assertSame(401, $unauth['status'], $unauth['body']);

        $res = RestApiEnv::http('GET', self::INVOICES . '?limit=5', null, $this->authHeaders());
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertTrue($res['json']['success']);
        $this->assertIsArray($res['json']['data']);
    }
}
