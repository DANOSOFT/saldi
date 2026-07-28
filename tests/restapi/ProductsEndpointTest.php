<?php
// tests/restapi/ProductsEndpointTest.php
//
// Integration tests for /restapi/endpoints/v1/products/: create, read back,
// update, list, field search, and the pagination controls.
//
// The pagination tests are the interesting ones. The endpoint takes `limit`
// straight from the query string and hands it to
// VareModel::getAllItems(), which interpolates it into the SQL:
//
//     $query = "SELECT * FROM varer ORDER BY $orderBy $orderDirection LIMIT $limit";
//
// The only guard is a numeric comparison against a string
// (products/index.php:31), which a value like "1 OFFSET 5" slips straight
// through - see test_the_limit_parameter_accepts_a_sql_fragment.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

require_once __DIR__ . '/support/RestApiTestCase.php';

final class ProductsEndpointTest extends RestApiTestCase
{
    private const PRODUCTS = '/restapi/endpoints/v1/products/';

    /** Seed enough products that a row count actually distinguishes cases. */
    private static function seedProducts(int $count): void
    {
        static $seeded = false;
        if ($seeded) {
            return;
        }
        for ($i = 1; $i <= $count; $i++) {
            RestApiEnv::rows(
                self::tenant(),
                "INSERT INTO varer (varenr, beskrivelse, salgspris, kostpris, beholdning, gruppe, lukket)
                 VALUES ($1, $2, $3, $4, 0, 2, '')",
                ["APITEST$i", "apitest product $i", 10.0 * $i, 5.0 * $i]
            );
        }
        $seeded = true;
    }

    private static function uniqueSku(): string
    {
        static $n = 0;
        return 'APINEW' . (++$n) . '-' . getmypid();
    }

    public function test_a_product_can_be_created_and_read_back(): void
    {
        $sku = self::uniqueSku();
        $created = self::api('POST', self::PRODUCTS, [
            'sku' => $sku,
            'description' => 'created over the api',
            'salesPrice' => 199.50,
            'costPrice' => 90.00,
        ]);
        $payload = $this->assertSuccessEnvelope($created, 'create');
        $this->assertNotEmpty($payload['id'] ?? null, 'create returns the new id: ' . $created['body']);

        $id = (int)$payload['id'];
        $read = self::api('GET', self::PRODUCTS . '?id=' . $id);
        $product = $this->assertSuccessEnvelope($read, 'read back');

        $this->assertSame($sku, $product['sku'] ?? null, 'sku round-trips');
        $this->assertSame('created over the api', $product['description'] ?? null, 'description round-trips');
        $this->assertEqualsWithDelta(199.50, (float)($product['salesPrice'] ?? 0), 0.005, 'sales price round-trips');
    }

    public function test_creating_a_product_without_the_required_fields_is_refused(): void
    {
        $res = self::api('POST', self::PRODUCTS, ['salesPrice' => 10.0]);

        $this->assertNotSame(200, $res['status'], 'a product with no sku or description must be refused');
        $this->assertFalse($res['json']['success'] ?? true, $res['body']);
    }

    public function test_reading_a_nonexistent_product_returns_404(): void
    {
        $res = self::api('GET', self::PRODUCTS . '?id=99999999');

        $this->assertSame(404, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success'] ?? true);
    }

    public function test_a_product_can_be_found_by_its_sku(): void
    {
        $sku = self::uniqueSku();
        $created = self::api('POST', self::PRODUCTS, ['sku' => $sku, 'description' => 'findable']);
        $this->assertSuccessEnvelope($created, 'create');

        $res = self::api('GET', self::PRODUCTS . '?field=varenr&value=' . rawurlencode($sku));
        $items = $this->assertSuccessEnvelope($res, 'search by sku');

        $this->assertIsArray($items);
        $this->assertCount(1, $items, 'exactly one product carries that sku');
        $this->assertSame($sku, $items[0]['sku'] ?? null);
    }

    /**
     * Searching by a column that is not on the allow-list returns nothing
     * rather than querying it (VareModel::findBy:83).
     */
    public function test_searching_by_a_column_outside_the_allow_list_returns_nothing(): void
    {
        $res = self::api('GET', self::PRODUCTS . '?field=kostpris&value=5');
        $items = $this->assertSuccessEnvelope($res, 'search by disallowed field');

        $this->assertSame([], $items, 'a non-allow-listed field yields no rows');
    }

    public function test_the_list_honours_a_numeric_limit(): void
    {
        self::seedProducts(8);

        $res = self::api('GET', self::PRODUCTS . '?limit=3');
        $items = $this->assertSuccessEnvelope($res, 'limit=3');

        $this->assertCount(3, $items, 'three rows requested, three returned');
    }

    /** A non-numeric or out-of-range limit falls back to the default of 20. */
    public function test_an_out_of_range_limit_falls_back_to_the_default(): void
    {
        self::seedProducts(8);

        foreach (['abc', '0', '-5', '100000'] as $bad) {
            $res = self::api('GET', self::PRODUCTS . '?limit=' . rawurlencode($bad));
            $items = $this->assertSuccessEnvelope($res, "limit=$bad");
            $this->assertLessThanOrEqual(20, count($items), "limit=$bad must not exceed the default page size");
        }
    }

    /**
     * DEFECT PINNED: `limit` reaches the SQL as an uninterpolated string, so a
     * value that is not a number but still compares below "100" as a string
     * is passed straight into the LIMIT clause.
     *
     * products/index.php:30-33:
     *
     *     $limit = $_GET['limit'] ?? 20;
     *     if ($limit > 100 || $limit < 1) { $limit = 20; }
     *
     * PHP 8 compares a non-numeric string with an int as strings. "1 OFFSET 5"
     * sorts below "100" (space < '0') and above "1", so both guards pass and
     * VareModel::getAllItems:132 builds `... LIMIT 1 OFFSET 5`.
     *
     * The blast radius is bounded - Postgres will not run stacked statements
     * through this driver, verified - but an authenticated caller can page
     * past the 100-row cap and walk an entire table, and any change to the
     * query's shape widens it. The same unguarded `$limit` reaches
     * AccountModel::getAllItems:213 and CurrencyModel::getAllItems:89 from
     * accounts/index.php:54 and currencies/index.php:30.
     *
     * When `limit` is cast to an int, this test will fail; delete it then.
     */
    public function test_the_limit_parameter_accepts_a_sql_fragment(): void
    {
        self::seedProducts(8);

        $all = $this->assertSuccessEnvelope(self::api('GET', self::PRODUCTS . '?limit=20'), 'baseline');
        $this->assertGreaterThan(4, count($all), 'precondition: more than four products exist');
        $fifthId = $all[4]['id'] ?? null;
        $this->assertNotNull($fifthId);

        $res = self::api('GET', self::PRODUCTS . '?limit=' . rawurlencode('1 OFFSET 4'));
        $items = $this->assertSuccessEnvelope($res, 'limit=1 OFFSET 4');

        $this->assertCount(1, $items, 'the fragment reached the SQL: one row came back');
        $this->assertSame(
            $fifthId,
            $items[0]['id'],
            'and it was the fifth row - OFFSET was executed, not rejected'
        );
    }

    /** Stacked statements do NOT execute through this driver - bounding the above. */
    public function test_the_limit_parameter_cannot_run_a_stacked_statement(): void
    {
        RestApiEnv::rows(self::tenant(), 'DROP TABLE IF EXISTS sqli_marker');

        self::api('GET', self::PRODUCTS . '?limit=' . rawurlencode('1; CREATE TABLE sqli_marker (proof text)'));

        $marker = RestApiEnv::rows(
            self::tenant(),
            "SELECT to_regclass('public.sqli_marker') IS NOT NULL AS created"
        );
        $this->assertSame('f', $marker[0]['created'], 'a stacked statement must not execute');
    }

    /** An unknown order column falls back to id rather than reaching the SQL. */
    public function test_an_unknown_order_column_is_ignored(): void
    {
        self::seedProducts(8);

        $res = self::api('GET', self::PRODUCTS . '?limit=5&orderBy=' . rawurlencode('kostpris; DROP TABLE varer'));
        $items = $this->assertSuccessEnvelope($res, 'bad orderBy');

        $this->assertCount(5, $items, 'the request still succeeds');
        $ids = array_map(static fn($i) => (int)$i['id'], $items);
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids, 'it fell back to ordering by id');
    }
}
