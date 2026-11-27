<?php
// tests/restapi/InventoryStatusEndpointTest.php
//
// Integration tests for /restapi/endpoints/v1/inventory/status/ against the
// local docker stack's self-provisioned tenant: a stock quantity the API sets
// must reach what SALDI counts stock from - the bought/sold batches that
// Lagerstatus (lager/lagerstatus.php) sums and the item card resyncs
// lagerstatus from - and the item's total, varer.beholdning.
//
// History:
// 20261105 CL/LH Created: create, adjust_quantity, set_quantity and PUT go through lagerreguler().
//                Also covers batch remainders, concurrent requests, rows set before the fix, identity changes and variants.
//                Also covers batches without a warehouse or variant, a second row for one warehouse, and a fourth decimal.

require_once __DIR__ . '/support/RestApiTestCase.php';

final class InventoryStatusEndpointTest extends RestApiTestCase
{
    private const STATUS = '/restapi/endpoints/v1/inventory/status/';
    private const PRODUCTS = '/restapi/endpoints/v1/products/';

    /** A fresh product with a cost price, as the lab's stock session made one. */
    private function createProduct(): int
    {
        $res = self::api('POST', self::PRODUCTS, [
            'sku' => 'APITEST-STOCK-' . random_int(100000, 999999),
            'description' => 'REST stock test',
            'costPrice' => 12.5,
        ]);
        $this->assertSame(201, $res['status'], $res['body']);
        return (int)$res['json']['data']['id'];
    }

    /** A variant of the product. The API has no variant endpoint, so it goes in as the item card stores one. */
    private static function createVariant(int $product): int
    {
        $sql = 'INSERT INTO variant_varer (vare_id, variant_type, variant_beholdning, variant_stregkode) VALUES ($1, 0, 0, $2) RETURNING id';
        return (int)self::rows($sql, [$product, 'APITEST-VAR-' . random_int(100000, 999999)])[0]['id'];
    }

    /** POST a row and return its id. */
    private function createRow(int $product, int $warehouse, float $quantity, int $variant = 0): int
    {
        $res = self::api('POST', self::STATUS, ['inventory' => $warehouse, 'productId' => $product, 'quantity' => $quantity, 'variantId' => $variant]);
        $this->assertSame(201, $res['status'], $res['body']);
        $this->assertEquals($quantity, $res['json']['data']['quantity']);
        return (int)$res['json']['data']['id'];
    }

    /** A row as the API wrote it before 20261105: the quantity in lagerstatus and no batch behind it. */
    private static function rowWithoutBatches(int $product, int $warehouse, float $quantity): int
    {
        $sql = "INSERT INTO lagerstatus (lager, vare_id, variant_id, beholdning, lok1, lok2, lok3, lok4, lok5) VALUES ($1, $2, 0, $3, '', '', '', '', '') RETURNING id";
        return (int)self::rows($sql, [$warehouse, $product, $quantity])[0]['id'];
    }

    /**
     * Rows from the test tenant on a connection of their own. RestApiTestCase::tenant() keeps one
     * connection for every class, and the tenant teardown after this class terminates it.
     *
     * @param array<int,mixed> $params
     * @return list<array<string,?string>>
     */
    private static function rows(string $sql, array $params = []): array
    {
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        try {
            return RestApiEnv::rows($tenant, $sql, $params);
        } finally {
            pg_close($tenant);
        }
    }

    /**
     * What SALDI holds for one product: the warehouse row, the item total and
     * the batch balance per warehouse that Lagerstatus and the item card count.
     *
     * @return array{lagerstatus: array<int, float>, total: ?float, batches: array<int, float>}
     */
    private function stock(int $product): array
    {
        $lagerstatus = [];
        foreach (self::rows('SELECT lager, beholdning FROM lagerstatus WHERE vare_id = $1 ORDER BY lager', [$product]) as $r) {
            $lagerstatus[(int)$r['lager']] = (float)$r['beholdning'];
        }
        $total = self::rows('SELECT beholdning FROM varer WHERE id = $1', [$product])[0]['beholdning'];
        $batches = [];
        $sql = 'SELECT lager, sum(antal) AS antal FROM (SELECT lager, antal FROM batch_kob WHERE vare_id = $1'
            . ' UNION ALL SELECT lager, -antal FROM batch_salg WHERE vare_id = $1) b GROUP BY lager ORDER BY lager';
        foreach (self::rows($sql, [$product]) as $r) {
            $batches[(int)$r['lager']] = (float)$r['antal'];
        }
        return ['lagerstatus' => $lagerstatus, 'total' => $total === null ? null : (float)$total, 'batches' => $batches];
    }

    /**
     * Each bought batch of the product as [bought, left], in the order they were booked.
     * Sales are allocated from what is left (batch_kob.rest).
     *
     * @return list<array{0: float, 1: float}>
     */
    private static function boughtBatches(int $product): array
    {
        $out = [];
        foreach (self::rows('SELECT antal, rest FROM batch_kob WHERE vare_id = $1 ORDER BY id', [$product]) as $r) {
            $out[] = [(float)$r['antal'], (float)$r['rest']];
        }
        return $out;
    }

    /**
     * Each sale of the product as [the bought batch it came out of, quantity], in the order they were booked.
     * Batch 0 is a sale of no batch.
     *
     * @return list<array{0: int, 1: float}>
     */
    private static function sales(int $product): array
    {
        $out = [];
        foreach (self::rows('SELECT batch_kob_id, antal FROM batch_salg WHERE vare_id = $1 ORDER BY id', [$product]) as $r) {
            $out[] = [(int)$r['batch_kob_id'], (float)$r['antal']];
        }
        return $out;
    }

    /**
     * A bought batch as kreditor/modtag.php and lager/lagerregulering.php write one: no variant, and the
     * warehouse NULL or 0.
     */
    private static function batchWithoutVariant(int $product, ?int $warehouse, string $date, float $quantity): int
    {
        $sql = 'INSERT INTO batch_kob (vare_id, variant_id, lager, linje_id, ordre_id, kobsdate, fakturadate, antal, rest, pris)'
            . ' VALUES ($1, NULL, $2, 0, 0, $3, $3, $4, $4, 12.5) RETURNING id';
        return (int)self::rows($sql, [$product, $warehouse, $date, $quantity])[0]['id'];
    }

    private static function variantStock(int $variant): float
    {
        return (float)self::rows('SELECT variant_beholdning FROM variant_varer WHERE id = $1', [$variant])[0]['variant_beholdning'];
    }

    /**
     * Send the requests at the same time and wait for all of them.
     *
     * @param list<array{0: string, 1: string, 2: array<string, mixed>}> $requests method, path, body
     * @return list<int> The HTTP status of each, in request order
     */
    private static function concurrently(array $requests): array
    {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($requests as [$method, $path, $body]) {
            $ch = curl_init(RestApiEnv::baseUrl() . $path);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => array_merge(['Accept: application/json', 'Content-Type: application/json'], self::authHeaders()),
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[] = $ch;
        }
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi);
            }
        } while ($running && $status === CURLM_OK);
        $codes = [];
        foreach ($handles as $ch) {
            $codes[] = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
        return $codes;
    }

    public function test_stock_set_through_the_api_reaches_the_batches_and_the_item_total(): void
    {
        $product = $this->createProduct();

        // The lab's sequence (api-masterdata-stock): create at 0 in warehouse 2, adjust by 2.5, set to 7.
        $create = self::api('POST', self::STATUS, ['inventory' => 2, 'productId' => $product, 'quantity' => 0, 'location' => 'Reol Æ-3']);
        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];
        $this->assertSame(['lagerstatus' => [2 => 0.0], 'total' => 0.0, 'batches' => []], $this->stock($product), 'a row created at 0 moves no stock');

        $adjust = self::api('PATCH', self::STATUS . "?id=$id", ['action' => 'adjust_quantity', 'amount' => 2.5]);
        $this->assertEquals(2.5, $this->assertSuccessEnvelope($adjust, 'adjust_quantity')['quantity']);
        $this->assertSame(['lagerstatus' => [2 => 2.5], 'total' => 2.5, 'batches' => [2 => 2.5]], $this->stock($product));

        $set = self::api('PATCH', self::STATUS . "?id=$id", ['action' => 'set_quantity', 'quantity' => 7]);
        $this->assertEquals(7, $this->assertSuccessEnvelope($set, 'set_quantity')['quantity']);
        $this->assertSame(['lagerstatus' => [2 => 7.0], 'total' => 7.0, 'batches' => [2 => 7.0]], $this->stock($product), 'Lagerstatus counts 7 bought, the item total is 7');
        $this->assertSame(['12.500'], array_column(self::rows('SELECT DISTINCT pris FROM batch_kob WHERE vare_id = $1', [$product]), 'pris'), 'the stock is bought in at the item\'s cost price');
        $this->assertSame([[2.5, 2.5], [4.5, 4.5]], self::boughtBatches($product));

        // Down again through a PUT: the 3 taken out are written as sold, not just overwritten.
        $put = self::api('PUT', self::STATUS . "?id=$id", ['quantity' => 4, 'location' => 'Reol Ø-4']);
        $this->assertEquals(4, $this->assertSuccessEnvelope($put, 'PUT')['quantity']);
        $this->assertSame(['lagerstatus' => [2 => 4.0], 'total' => 4.0, 'batches' => [2 => 4.0]], $this->stock($product));
        $this->assertSame('Reol Ø-4', self::rows('SELECT lok1 FROM lagerstatus WHERE id = $1', [$id])[0]['lok1']);
        $this->assertSame([[2.5, 0.0], [4.5, 4.0]], self::boughtBatches($product), 'the first batch is used up and the second keeps 4, not 5: sales are allocated from what is left');

        $get = self::api('GET', self::STATUS . "?id=$id");
        $this->assertEquals(4, $this->assertSuccessEnvelope($get, 'GET')['quantity']);
    }

    public function test_a_row_created_with_stock_counts_towards_the_item_total_of_every_warehouse(): void
    {
        $product = $this->createProduct();

        foreach ([1 => 3, 2 => 5] as $warehouse => $quantity) {
            $this->createRow($product, $warehouse, $quantity);
        }

        $this->assertSame(['lagerstatus' => [1 => 3.0, 2 => 5.0], 'total' => 8.0, 'batches' => [1 => 3.0, 2 => 5.0]], $this->stock($product));

        $unknown = self::api('POST', self::STATUS, ['inventory' => 1, 'productId' => 2000000000, 'quantity' => 1]);
        $this->assertSame(400, $unknown['status'], 'no stock is booked for a product that does not exist: ' . $unknown['body']);
    }

    public function test_concurrent_requests_for_one_row_leave_its_batches_equal_to_its_quantity(): void
    {
        $product = $this->createProduct();
        $id = $this->createRow($product, 1, 0);
        $row = self::STATUS . "?id=$id";

        // set_quantity 5 and 7 at the same time: each read 0 and bought its whole quantity in, 12 for a row that said 5 or 7.
        $pair = [['PATCH', $row, ['action' => 'set_quantity', 'quantity' => 5]], ['PATCH', $row, ['action' => 'set_quantity', 'quantity' => 7]]];
        $this->assertSame(array_fill(0, 8, 200), self::concurrently(array_merge($pair, $pair, $pair, $pair)));
        $stock = $this->stock($product);
        $quantity = $stock['lagerstatus'][1];
        $this->assertContains($quantity, [5.0, 7.0]);
        $this->assertSame(['lagerstatus' => [1 => $quantity], 'total' => $quantity, 'batches' => [1 => $quantity]], $stock, 'the batches hold what the last request set');

        // Adjustments at the same time all count: each one adds to what the one before it left.
        $this->assertSame(array_fill(0, 8, 200), self::concurrently(array_fill(0, 8, ['PATCH', $row, ['action' => 'adjust_quantity', 'amount' => 1]])));
        $this->assertSame(['lagerstatus' => [1 => $quantity + 8], 'total' => $quantity + 8, 'batches' => [1 => $quantity + 8]], $this->stock($product));
    }

    public function test_stock_the_api_set_before_without_batches_is_booked_when_the_row_is_written_again(): void
    {
        $product = $this->createProduct();

        // The lab's row (cand DEVY-5): lagerstatus 7 and no batches. Setting 7 again booked nothing, setting 8 only 1.
        $id = self::rowWithoutBatches($product, 2, 7);
        $set = self::api('PATCH', self::STATUS . "?id=$id", ['action' => 'set_quantity', 'quantity' => 7]);
        $this->assertEquals(7, $this->assertSuccessEnvelope($set, 'set_quantity 7')['quantity']);
        $this->assertSame(['lagerstatus' => [2 => 7.0], 'total' => 7.0, 'batches' => [2 => 7.0]], $this->stock($product));
        $set = self::api('PATCH', self::STATUS . "?id=$id", ['action' => 'set_quantity', 'quantity' => 8]);
        $this->assertEquals(8, $this->assertSuccessEnvelope($set, 'set_quantity 8')['quantity']);
        $this->assertSame(['lagerstatus' => [2 => 8.0], 'total' => 8.0, 'batches' => [2 => 8.0]], $this->stock($product));

        // An adjustment adds to the quantity the API reported, and a PUT of the location alone books the row as it stands.
        $adjusted = self::rowWithoutBatches($product, 1, 4);
        $adjust = self::api('PATCH', self::STATUS . "?id=$adjusted", ['action' => 'adjust_quantity', 'amount' => 1]);
        $this->assertEquals(5, $this->assertSuccessEnvelope($adjust, 'adjust_quantity')['quantity']);
        $moved = self::rowWithoutBatches($product, 3, 2);
        $put = self::api('PUT', self::STATUS . "?id=$moved", ['location' => 'Reol B-1']);
        $this->assertEquals(2, $this->assertSuccessEnvelope($put, 'PUT')['quantity']);
        $this->assertSame(['lagerstatus' => [1 => 5.0, 2 => 8.0, 3 => 2.0], 'total' => 15.0, 'batches' => [1 => 5.0, 2 => 8.0, 3 => 2.0]], $this->stock($product));
    }

    public function test_an_inventory_status_keeps_its_warehouse_product_and_variant(): void
    {
        $product = $this->createProduct();
        $other = $this->createProduct();
        $variant = self::createVariant($product);
        $id = $this->createRow($product, 1, 7);

        // A row moved to warehouse 2 at the same quantity booked nothing there and left its 7 bought in warehouse 1.
        foreach (['inventory' => 2, 'productId' => $other, 'variantId' => $variant] as $field => $value) {
            $res = self::api('PUT', self::STATUS . "?id=$id", [$field => $value, 'quantity' => 7]);
            $this->assertSame(400, $res['status'], "$field: " . $res['body']);
            $this->assertStringContainsString('cannot be changed', (string)($res['json']['message'] ?? ''), $field);
        }
        $this->assertSame(['lagerstatus' => [1 => 7.0], 'total' => 7.0, 'batches' => [1 => 7.0]], $this->stock($product));
        $this->assertSame([], $this->stock($other)['batches']);

        $same = self::api('PUT', self::STATUS . "?id=$id", ['inventory' => 1, 'productId' => $product, 'variantId' => 0, 'quantity' => 6]);
        $this->assertEquals(6, $this->assertSuccessEnvelope($same, 'PUT with the same warehouse, product and variant')['quantity']);
    }

    public function test_a_variant_counts_its_stock_in_every_warehouse(): void
    {
        $product = $this->createProduct();
        $variant = self::createVariant($product);

        $second = 0;
        foreach ([1 => 3, 2 => 5] as $warehouse => $quantity) {
            $second = $this->createRow($product, $warehouse, $quantity, $variant);
        }
        $this->assertSame(8.0, self::variantStock($variant), 'the variant holds 3 + 5, not the 5 set last');
        $this->assertSame(['lagerstatus' => [1 => 3.0, 2 => 5.0], 'total' => 8.0, 'batches' => [1 => 3.0, 2 => 5.0]], $this->stock($product));
        $this->assertSame([(string)$variant], array_values(array_unique(array_column(self::rows('SELECT variant_id FROM batch_kob WHERE vare_id = $1', [$product]), 'variant_id'))));

        $set = self::api('PATCH', self::STATUS . "?id=$second", ['action' => 'set_quantity', 'quantity' => 1]);
        $this->assertEquals(1, $this->assertSuccessEnvelope($set, 'set_quantity')['quantity']);
        $this->assertSame(4.0, self::variantStock($variant));
        $this->assertSame(['lagerstatus' => [1 => 3.0, 2 => 1.0], 'total' => 4.0, 'batches' => [1 => 3.0, 2 => 1.0]], $this->stock($product));

        $unknown = self::api('POST', self::STATUS, ['inventory' => 3, 'productId' => $product, 'quantity' => 1, 'variantId' => $variant + 1000000]);
        $this->assertSame(400, $unknown['status'], 'a variant of no product gets no stock: ' . $unknown['body']);
    }

    public function test_stock_taken_out_of_warehouse_1_comes_out_of_batches_saved_without_a_warehouse_or_variant(): void
    {
        $product = $this->createProduct();
        $first = self::batchWithoutVariant($product, null, '2026-01-01', 4);
        $second = self::batchWithoutVariant($product, 0, '2026-01-02', 3);
        $sql = "INSERT INTO lagerstatus (lager, vare_id, variant_id, beholdning, lok1) VALUES (0, $1, NULL, 7, '') RETURNING id";
        $id = (int)self::rows($sql, [$product])[0]['id'];

        // The API counted the 7 as warehouse 1's, and lagerreguler() looked for lager 1 and variant 0 only: the 5 taken
        // out were booked as a sale of no batch, and both batches kept their rest for later sales to take again.
        $set = self::api('PATCH', self::STATUS . "?id=$id", ['action' => 'set_quantity', 'quantity' => 2]);
        $this->assertEquals(2, $this->assertSuccessEnvelope($set, 'set_quantity')['quantity']);
        $this->assertSame([[4.0, 0.0], [3.0, 2.0]], self::boughtBatches($product), 'the 5 come out of the oldest batch first');
        $this->assertSame([[$first, 4.0], [$second, 1.0]], self::sales($product));
        $stock = $this->stock($product);
        $this->assertSame([1 => 2.0], $stock['lagerstatus'], 'the row is in warehouse 1, where the item card and Lagerstatus count it');
        $this->assertSame(2.0, $stock['total']);
    }

    public function test_a_second_row_for_the_same_warehouse_and_variant_is_refused_without_booking(): void
    {
        $product = $this->createProduct();
        // lagerstatus has no unique key, so a product can have two rows for one warehouse. 7 are bought, the second row says so.
        $first = self::rowWithoutBatches($product, 1, 0);
        $second = self::rowWithoutBatches($product, 1, 7);
        self::rows("INSERT INTO batch_kob (vare_id, variant_id, lager, linje_id, ordre_id, kobsdate, antal, rest, pris) VALUES ($1, 0, 1, 0, 0, '2026-01-01', 7, 7, 12.5)", [$product]);
        $rows = static fn (): array => array_map(
            static fn (array $r): array => [(int)$r['id'], (float)$r['beholdning']],
            self::rows('SELECT id, beholdning FROM lagerstatus WHERE vare_id = $1 ORDER BY id', [$product])
        );
        $total = self::rows('SELECT beholdning FROM varer WHERE id = $1', [$product])[0]['beholdning'];

        // lagerreguler() books the lowest id: setting the second row to 4 set the first one to 4 and bought 4 more,
        // so the API answered 7 and the item total became 11.
        $requests = [
            ['PATCH', $second, ['action' => 'set_quantity', 'quantity' => 4]],
            ['PATCH', $first, ['action' => 'adjust_quantity', 'amount' => 1]],
            ['PUT', $second, ['quantity' => 4]],
        ];
        foreach ($requests as [$method, $id, $body]) {
            $res = self::api($method, self::STATUS . "?id=$id", $body);
            $this->assertSame(400, $res['status'], "$method $id: " . $res['body']);
            $this->assertStringContainsString('more than one inventory status', (string)($res['json']['message'] ?? ''), "$method $id");
        }
        $this->assertSame([[$first, 0.0], [$second, 7.0]], $rows());
        $this->assertSame([[7.0, 7.0]], self::boughtBatches($product));
        $this->assertSame([], self::sales($product));
        $this->assertSame($total, self::rows('SELECT beholdning FROM varer WHERE id = $1', [$product])[0]['beholdning']);
    }

    public function test_a_quantity_with_more_than_three_decimals_is_booked_as_the_row_stores_it(): void
    {
        $product = $this->createProduct();
        $id = $this->createRow($product, 1, 7);

        // lagerstatus keeps three decimals and stored 4.0005 as 4.001, while the 2.9995 taken out was rounded to 3 on
        // its own: the batches held 4, and the item card reset the row to 4 when it opened.
        $set = self::api('PATCH', self::STATUS . "?id=$id", ['action' => 'set_quantity', 'quantity' => 4.0005]);
        $this->assertEquals(4.001, $this->assertSuccessEnvelope($set, 'set_quantity')['quantity']);
        $this->assertSame(['lagerstatus' => [1 => 4.001], 'total' => 4.001, 'batches' => [1 => 4.001]], $this->stock($product));
    }
}
