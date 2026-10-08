<?php
// tests/restapi/ProductsEndpointTest.php
//
// Integration tests for /restapi/endpoints/v1/products/ against the local
// docker stack's self-provisioned tenant: auth enforcement, list, create ->
// read-back, duplicate SKU and missing-field validation, field search,
// update, delete, 404s, limit/offset/page paging, English field names.
//
// History:
// 20260904 CL/NTR created.
// 20261008 CL/LH paging (limit cap 200, offset, page) and the documented English orderBy/field names.

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

    /** @return list<string> product ids of one list call */
    private function listIds(string $query): array
    {
        $res = RestApiEnv::http('GET', self::PRODUCTS . $query, null, RestApiEnv::authHeaders());
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertIsArray($res['json']['data'], $res['body']);
        return array_map(fn($p) => (string)$p['id'], $res['json']['data']);
    }

    /** Number of products in the tenant, topped up to $min with created ones. */
    private function productCount(int $min = 0): int
    {
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $n = (int)RestApiEnv::rows($tenant, 'SELECT count(*) AS n FROM varer')[0]['n'];
        pg_close($tenant);
        for (; $n < $min; $n++) {
            $this->assertSame(201, $this->create(['sku' => $this->sku('fill'), 'description' => 'filler'])['status']);
        }
        return $n;
    }

    public function test_limit_above_100_is_honoured_up_to_200(): void
    {
        $total = $this->productCount();

        $this->assertCount(min(150, $total), $this->listIds('?limit=150'));
        $this->assertCount(min(200, $total), $this->listIds('?limit=500'));
        $this->assertCount(min(20, $total), $this->listIds(''));
    }

    public function test_offset_and_page_return_the_next_slice(): void
    {
        $this->productCount(10);

        $first = $this->listIds('?limit=5');
        $second = $this->listIds('?limit=5&offset=5');

        $this->assertCount(5, $second);
        $this->assertSame([], array_intersect($first, $second), 'offset=5 starts after the first page');
        $this->assertSame(array_merge($first, $second), $this->listIds('?limit=10'));
        $this->assertSame($second, $this->listIds('?limit=5&page=2'));
        $this->assertSame($first, $this->listIds('?limit=5&page=1'));
    }

    public function test_paging_returns_every_product_exactly_once(): void
    {
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $expected = array_column(RestApiEnv::rows($tenant, 'SELECT id FROM varer ORDER BY id'), 'id');
        pg_close($tenant);
        $this->assertNotEmpty($expected, 'template tenant has products');
        $limit = max(1, min(200, intdiv(count($expected), 5)));

        // By page in id order, and by offset in description order - descriptions repeat, so
        // this also needs a stable tiebreak to neither skip nor repeat products across pages.
        foreach (['page', 'offset'] as $mode) {
            $seen = [];
            for ($i = 0; $i <= count($expected); $i++) {
                $param = $mode === 'page' ? 'page=' . ($i + 1) : 'offset=' . ($i * $limit) . '&orderBy=description';
                $ids = $this->listIds("?limit=$limit&$param");
                $seen = array_merge($seen, $ids);
                if (count($ids) < $limit) {
                    break;
                }
            }
            sort($seen);
            $sortedExpected = $expected;
            sort($sortedExpected);
            $this->assertSame($sortedExpected, $seen, "paging by $mode");
        }
    }

    public function test_documented_english_field_names_order_and_filter(): void
    {
        $this->productCount(10);
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $bySku = array_column(RestApiEnv::rows($tenant, 'SELECT id FROM varer ORDER BY varenr, id LIMIT 10'), 'id');
        $group = RestApiEnv::rows($tenant, 'SELECT gruppe, count(*) AS n FROM varer WHERE gruppe IS NOT NULL GROUP BY gruppe ORDER BY 2, 1 LIMIT 1');
        pg_close($tenant);

        $this->assertSame($bySku, $this->listIds('?orderBy=sku&limit=10'));
        $this->assertSame($bySku, $this->listIds('?orderBy=varenr&limit=10'));

        $this->assertNotEmpty($group, 'template tenant has products with a group');
        $inGroup = $this->listIds('?field=group&value=' . urlencode($group[0]['gruppe']));
        $this->assertCount((int)$group[0]['n'], $inGroup);
        $this->assertSame($inGroup, $this->listIds('?field=gruppe&value=' . urlencode($group[0]['gruppe'])));
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
