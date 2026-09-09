<?php
// tests/restapi/ProductsEndpointTest.php
//
// Integration tests for /restapi/endpoints/v1/products/ against the local
// docker stack's self-provisioned tenant: auth enforcement, list, create ->
// read-back, duplicate SKU and missing-field validation, field search,
// update, delete, 404s.
//
// History:
// 20260904 CL/NTR created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class ProductsEndpointTest extends TestCase
{
    private const PRODUCTS = '/restapi/endpoints/v1/products/';

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

    private function sku(string $tag): string
    {
        return 'APITEST-' . strtoupper($tag) . '-' . random_int(100000, 999999);
    }

    private function create(array $body): array
    {
        return RestApiEnv::http('POST', self::PRODUCTS, $body, RestApiEnv::authHeaders());
    }

    private function read(int $id): array
    {
        return RestApiEnv::http('GET', self::PRODUCTS . '?id=' . $id, null, RestApiEnv::authHeaders());
    }

    public function test_products_require_a_bearer_token(): void
    {
        $res = RestApiEnv::http('GET', self::PRODUCTS);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_product_list_returns_array_and_honours_limit(): void
    {
        $res = RestApiEnv::http('GET', self::PRODUCTS . '?limit=3', null, RestApiEnv::authHeaders());

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertTrue($res['json']['success']);
        $this->assertIsArray($res['json']['data']);
        $this->assertLessThanOrEqual(3, count($res['json']['data']));
        foreach ($res['json']['data'] as $product) {
            $this->assertArrayHasKey('sku', $product);
            $this->assertArrayHasKey('salesPrice', $product);
        }
    }

    public function test_created_product_can_be_read_back(): void
    {
        $sku = $this->sku('create');
        $create = $this->create([
            'sku' => $sku,
            'description' => 'Apitest widget',
            'salesPrice' => 123.45,
            'costPrice' => 100,
            'barcode' => '5701234567890',
            'location' => 'A1',
        ]);

        $this->assertSame(201, $create['status'], $create['body']);
        $this->assertTrue($create['json']['success']);
        $id = (int)($create['json']['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $id, 'created product has an id');

        $read = $this->read($id);

        $this->assertSame(200, $read['status'], $read['body']);
        $p = $read['json']['data'];
        $this->assertSame($id, (int)$p['id']);
        $this->assertSame($sku, $p['sku']);
        $this->assertSame('Apitest widget', $p['description']);
        $this->assertEqualsWithDelta(123.45, (float)$p['salesPrice'], 0.001);
        $this->assertEqualsWithDelta(100.0, (float)$p['costPrice'], 0.001);
        $this->assertSame('5701234567890', $p['barcode']);
        $this->assertSame('A1', $p['location']);
    }

    public function test_create_with_duplicate_sku_is_rejected_400(): void
    {
        $sku = $this->sku('dup');
        $first = $this->create(['sku' => $sku, 'description' => 'first']);
        $this->assertSame(201, $first['status'], $first['body']);

        $second = $this->create(['sku' => $sku, 'description' => 'second']);

        $this->assertSame(400, $second['status'], $second['body']);
        $this->assertFalse($second['json']['success']);
        $this->assertStringContainsString('already exists', $second['json']['message']);
    }

    public function test_create_without_description_is_rejected_400(): void
    {
        $res = $this->create(['sku' => $this->sku('nodesc')]);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('description', $res['json']['message']);
    }

    public function test_search_by_sku_finds_exactly_the_created_product(): void
    {
        $sku = $this->sku('find');
        $create = $this->create(['sku' => $sku, 'description' => 'findable']);
        $this->assertSame(201, $create['status'], $create['body']);

        $res = RestApiEnv::http('GET', self::PRODUCTS . '?field=varenr&value=' . urlencode($sku), null, RestApiEnv::authHeaders());

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertCount(1, $res['json']['data']);
        $this->assertSame($sku, $res['json']['data'][0]['sku']);
    }

    public function test_search_on_a_field_outside_the_whitelist_returns_empty_list(): void
    {
        $res = RestApiEnv::http('GET', self::PRODUCTS . '?field=id;drop&value=1', null, RestApiEnv::authHeaders());

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame([], $res['json']['data']);
    }

    public function test_updated_product_reflects_the_change_on_read_back(): void
    {
        $create = $this->create(['sku' => $this->sku('upd'), 'description' => 'before', 'salesPrice' => 10]);
        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];

        $put = RestApiEnv::http('PUT', self::PRODUCTS, [
            'id' => $id,
            'description' => 'after',
            'salesPrice' => 20.5,
        ], RestApiEnv::authHeaders());

        $this->assertSame(200, $put['status'], $put['body']);
        $this->assertTrue($put['json']['success']);

        $read = $this->read($id);
        $this->assertSame('after', $read['json']['data']['description']);
        $this->assertEqualsWithDelta(20.5, (float)$read['json']['data']['salesPrice'], 0.001);
    }

    public function test_update_of_nonexistent_product_returns_404(): void
    {
        $res = RestApiEnv::http('PUT', self::PRODUCTS, ['id' => 99999999, 'description' => 'x'], RestApiEnv::authHeaders());

        $this->assertSame(404, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_deleted_product_is_gone(): void
    {
        $create = $this->create(['sku' => $this->sku('del'), 'description' => 'doomed']);
        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];

        $del = RestApiEnv::http('DELETE', self::PRODUCTS . '?id=' . $id, null, RestApiEnv::authHeaders());

        $this->assertSame(200, $del['status'], $del['body']);
        $this->assertTrue($del['json']['success']);
        $this->assertSame(404, $this->read($id)['status']);

        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $rows = RestApiEnv::rows($tenant, 'SELECT id FROM varer WHERE id = $1', [$id]);
        pg_close($tenant);
        $this->assertSame([], $rows, 'varer row removed');
    }

    public function test_delete_without_id_is_rejected_400(): void
    {
        $res = RestApiEnv::http('DELETE', self::PRODUCTS, null, RestApiEnv::authHeaders());

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_reading_a_nonexistent_product_returns_404(): void
    {
        $res = $this->read(99999999);

        $this->assertSame(404, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }
}
