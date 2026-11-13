<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class StoreOrdersSyncTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';
    /** @var resource|\PgSql\Connection|null */
    private $conn = null;
    private string $state = '';

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_so');
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
        $this->state = tempnam(sys_get_temp_dir(), 'sd804_');
        $this->sql('ALTER TABLE adresser ADD COLUMN IF NOT EXISTS mobile varchar(15)');
        foreach (['shopify_order_links', 'shopify_customer_links', 'shopify_customer_exports', 'shopify_product_links', 'shopify_jobs', 'shopify_errors', 'shopify_brands', 'shopify_shops', 'pos_leveringsvalg'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM pos_betalinger WHERE ordre_id IN (SELECT id FROM ordrer WHERE ref = 'SD804')");
        $this->sql("DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE ref = 'SD804')");
        $this->sql("DELETE FROM ordrer WHERE ref = 'SD804'");
        $this->sql("DELETE FROM adresser WHERE kontonr LIKE 'SD804%'");
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, customers_enabled, store_orders_enabled, go_live_at)
            VALUES ('hml', 'hml-fake.myshopify.com', true, true, true, '2026-11-10 08:00')");
        $this->sql("INSERT INTO shopify_brands (sprog, shop_id) SELECT 'dansk', id FROM shopify_shops WHERE shop_key = 'hml'");
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            pg_close($this->conn);
        }
        if ($this->state !== '' && is_file($this->state)) {
            unlink($this->state);
        }
    }

    private function sql(string $sql, array $params = []): array
    {
        return ShopifyEnv::rows($this->conn, $sql, $params);
    }

    private function steps(array ...$steps): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_store_orders.php', [self::$db, $this->state, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'];
    }

    private function debtor(string $kontonr, string $email): int
    {
        return (int)$this->sql("INSERT INTO adresser (kontonr, firmanavn, kontakt, email, tlf, land, art) VALUES ($1, 'SD804 Kunde', 'Hans Jensen', $2, '', 'Danmark', 'D') RETURNING id",
            [$kontonr, $email])[0]['id'];
    }

    /** A posted POS sale of one item 10 (100 + 25 VAT), paid 125 by card. */
    private function sale(int $kontoId, string $fakturadate = '2026-11-12', string $tidspkt = '10:30', ?int $leveringsvalg = null): int
    {
        $email = $kontoId > 0 ? (string)$this->sql('SELECT email FROM adresser WHERE id = $1', [$kontoId])[0]['email'] : '';
        $id = (int)$this->sql("INSERT INTO ordrer (art, status, fakturanr, fakturadate, tidspkt, sum, moms, momssats, valuta, felt_1, konto_id, sprog, email, phone, firmanavn, kontakt, land, ref, leveringsvalg_id)
            VALUES ('PO', 3, $1, $2, $3, 100, 25, 25, 'DKK', 'Dankort', $4, 'Dansk', $5, '', 'SD804 Kunde', 'Hans Jensen', 'Danmark', 'SD804', $6) RETURNING id",
            [(string)random_int(10000, 99999), $fakturadate, $tidspkt, $kontoId, $email, $leveringsvalg])[0]['id'];
        $this->sql("INSERT INTO ordrelinjer (ordre_id, posnr, vare_id, varenr, beskrivelse, antal, pris, rabat, momssats, lager) VALUES ($1, 1, 9804, 'SD804-1', 'Havestol', 1, 100, 0, 25, 0)", [$id]);
        $this->sql("INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES ($1, 'Dankort', 125, 'DKK', 100)", [$id]);
        return $id;
    }

    private function link(int $id): ?array
    {
        return $this->sql("SELECT state, order_gid, order_name FROM shopify_order_links WHERE ordre_id = $1 AND direction = 'store'", [$id])[0] ?? null;
    }

    private static function creates(array $run): int
    {
        return count(array_filter($run['calls'], fn(array $c): bool => $c['operation'] === 'orderCreate'));
    }

    /** The debtor's export is parked: its sales go without a customer, email or phone. */
    private function park(int $adresserId): void
    {
        $this->sql("INSERT INTO shopify_customer_exports (shop_id, adresser_id, state) SELECT id, $1, 'parked' FROM shopify_shops WHERE shop_key = 'hml'", [$adresserId]);
    }

    public function testAPostedSaleBecomesOnePaidOrderAndAReplayChangesNothing(): void
    {
        $debtor = $this->debtor('SD804A', 'a@example.dk');
        $id = $this->sale($debtor);

        $r = $this->steps(['product', 'hml', 9804, 'SD804-1'], ['remote_customer', 'hml', ['firstName' => 'Hans', 'email' => 'a@example.dk', 'tags' => ['x']]]);
        [$variant, $customer] = $r['results'];
        $this->sql("INSERT INTO shopify_customer_links (shop_id, customer_gid, adresser_id) SELECT id, $1, $2 FROM shopify_shops WHERE shop_key = 'hml'", [$customer, $debtor]);
        $r = $this->steps(['enqueue', $id, '2026-11-12 10:31'], ['job', 'hml', $id], ['job', 'hml', $id], ['echo', 'hml', $id]);

        $this->assertTrue($r['results'][0]['queued']);
        $this->assertSame(['outcome' => 'done'], $r['results'][1]);
        $this->assertCount(1, $r['orders']['hml']);
        $o = $r['orders']['hml'][0];
        $this->assertSame(['saldi-butik', 'saldi-po-' . self::$db . "-$id"], $o['tags']);
        $this->assertSame(['BYPASS', 'PAID', 125.0, true, $customer, 'a@example.dk'], [$o['behaviour'], $o['financial'], (float)$o['paid'], $o['fulfilled'], $o['customer'], $o['email']],
            'K18: no Shopify stock is drawn; paid, taken along so fulfilled, with the customer');
        $this->assertSame([[$variant, 'sd804-1', 1, 125.0]], array_map(fn(array $l): array => [$l[0], $l[1], $l[2], (float)$l[3]], $o['lines']));
        $this->assertSame(['state' => 'created', 'order_gid' => $o['gid'], 'order_name' => $o['name']], $this->link($id), 'K54: the name is on the link');
        $this->assertCount(1, $r['receipts']['hml'], 'K42: the confirmation goes to the email');
        $this->assertSame(1, self::creates($r), 'the replay and the echo send nothing');
        $this->assertSame([], $this->sql("SELECT 1 FROM ordrer WHERE id = $1 AND kundeordnr IS NOT NULL AND kundeordnr <> ''", [$id]), 'K54: kundeordnr is left as the cashier typed it');
    }

    public function testALostAnswerIsAdoptedWithNoSecondOrder(): void
    {
        $debtor = $this->debtor('SD804B', 'b@example.dk');
        $this->park($debtor);
        $id = $this->sale($debtor);
        $this->steps(['product', 'hml', 9804, 'SD804-1']);

        $r = $this->steps(['job_lose', 'hml', $id, 20]);
        $this->assertSame('yield', $r['results'][0]['outcome'], 'the answer is lost and the search fails: it may have been created');
        $this->assertSame('create_started', $this->link($id)['state']);

        $r = $this->steps(['job', 'hml', $id], ['echo', 'hml', $id], ['job', 'hml', $id]);
        $this->assertSame(['outcome' => 'done'], $r['results'][0], 'the next run finds it by its tag before the settle time');
        $this->assertCount(1, $r['orders']['hml']);
        $this->assertSame(0, self::creates($r), 'no second order');
        $this->assertSame('created', $this->link($id)['state']);
    }

    public function testASendThatNeverArrivedIsSentAgainOnlyAfterTheSettleTime(): void
    {
        $debtor = $this->debtor('SD804C', 'c@example.dk');
        $this->park($debtor);
        $id = $this->sale($debtor);

        $r = $this->steps(['job_drop', 'hml', $id], ['job', 'hml', $id]);
        $this->assertSame('yield', $r['results'][0]['outcome']);
        $this->assertSame('yield', $r['results'][1]['outcome'], 'not found, but the search may lag: it waits');
        $this->assertSame([], $r['orders']['hml'] ?? []);

        $this->sql("UPDATE shopify_order_links SET flags = jsonb_set(flags, '{store,sent_at}', to_jsonb(extract(epoch FROM now())::int - 601))");
        $r = $this->steps(['job', 'hml', $id]);
        $this->assertSame(['outcome' => 'done'], $r['results'][0]);
        $this->assertSame(1, self::creates($r));
        $this->assertCount(1, $r['orders']['hml']);
    }

    public function testADebtorWithoutACustomerIsExportedFirstAndAParkedEmailGoesWithoutIt(): void
    {
        $new = $this->debtor('SD804D', 'd@example.dk');
        $taken = $this->debtor('SD804E', 'taget@example.dk');
        $one = $this->sale($new);
        $two = $this->sale($taken);

        $r = $this->steps(['remote_customer', 'hml', ['firstName' => 'Anden', 'email' => 'taget@example.dk']],
            ['job', 'hml', $one], ['customer', 'hml', $new], ['job', 'hml', $one],
            ['job', 'hml', $two], ['customer', 'hml', $taken], ['job', 'hml', $two]);

        $this->assertSame(['outcome' => 'yield', 'checkpoint' => [], 'run_after' => 60], $r['results'][1], 'K31: the customer is exported first');
        $this->assertSame(['outcome' => 'done'], $r['results'][3]);
        $orders = $r['orders']['hml'];
        $this->assertCount(2, $orders);
        $customer = $this->sql('SELECT customer_gid FROM shopify_customer_links WHERE adresser_id = $1', [$new])[0]['customer_gid'];
        $this->assertSame([$customer, 'd@example.dk'], [$orders[0]['customer'], $orders[0]['email']]);
        $this->assertSame('parked', $this->sql('SELECT state FROM shopify_customer_exports WHERE adresser_id = $1', [$taken])[0]['state']);
        $this->assertSame([null, null], [$orders[1]['customer'], $orders[1]['email']], 'a parked email: the order is not associated with the customer who has it');
        $this->assertCount(1, $r['receipts']['hml'], 'and gets no confirmation');
    }

    public function testARefusalIsListedAndTheNextRunBuildsTheOrderAgain(): void
    {
        $debtor = $this->debtor('SD804F', 'f@example.dk');
        $this->park($debtor);
        $id = $this->sale($debtor);
        $this->sql("INSERT INTO shopify_product_links (shop_id, vare_id, variant_id, variant_gid) SELECT id, 9804, 0, 'gid://shopify/ProductVariant/999999' FROM shopify_shops");

        $r = $this->steps(['job', 'hml', $id]);
        $this->assertSame(['outcome' => 'done'], $r['results'][0]);
        $this->assertSame('refused', $this->link($id)['state']);
        $this->assertSame([['kind' => 'store.export', 'ref' => "ordre:$id"]], $this->sql('SELECT kind, ref FROM shopify_errors WHERE resolved_at IS NULL'));

        $this->sql('DELETE FROM shopify_product_links');
        $r = $this->steps(['job', 'hml', $id]);
        $this->assertSame('created', $this->link($id)['state'], 'built again: the item goes as a custom item now');
        $this->assertSame([[null, 'Havestol', 1, 125.0]], array_map(fn(array $l): array => [$l[0], $l[1], $l[2], (float)$l[3]], $r['orders']['hml'][0]['lines']));
        $this->assertSame([], $this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'));
    }

    public function testOnlySalesFromGoLiveWithADebtorOrADeliveryGoAndTheScanFindsThoseNeverQueued(): void
    {
        $debtor = $this->debtor('SD804G', 'g@example.dk');
        $choice = (int)$this->sql("INSERT INTO pos_leveringsvalg (navn, type, shopify_vaerdi) VALUES ('Levering', 'levering', 'EasyRoutes') RETURNING id")[0]['id'];
        $before = $this->sale($debtor, '2026-11-10', '07:59');
        $anonymous = $this->sale(0);
        $delivery = $this->sale(0, '2026-11-12', '11:00', $choice);
        $old = $this->sale($debtor, '2026-11-01');
        $sale = $this->sale($debtor, '2026-11-12', '12:00');
        // an account payment: a POS order with no lines
        $payment = $this->sale($debtor, '2026-11-12', '12:05');
        $this->sql('DELETE FROM ordrelinjer WHERE ordre_id = $1', [$payment]);
        $this->sql('UPDATE ordrer SET moms = 0 WHERE id = $1', [$payment]);

        $r = $this->steps(['enqueue', $before, '2026-11-10 07:59'], ['recover', 'hml']);
        $this->assertFalse($r['results'][0]['queued'], 'K38: the shop was not live yet');
        $queued = array_map('intval', array_column($this->sql("SELECT payload->>'ordre_id' AS id FROM shopify_jobs WHERE type = 'store.export' ORDER BY 1"), 'id'));
        $this->assertSame([$delivery, $sale], $queued, 'the scan queues the sales from go-live that were never queued and would go, so those that never go hold no place under its limit');
        $this->assertNotContains($old, $queued);

        $r = $this->steps(['job', 'hml', $before], ['job', 'hml', $anonymous], ['job', 'hml', $payment], ['job', 'hml', $delivery]);
        $this->assertCount(1, $r['orders']['hml'], 'K37, K38: the sale before go-live, the anonymous cash sale and the account payment stay in Saldi');
        $this->assertNull($r['orders']['hml'][0]['customer']);
        $this->assertFalse($r['orders']['hml'][0]['fulfilled'], 'a delivery is not fulfilled at once');
        $this->assertSame([], $this->sql("SELECT ordre_id FROM shopify_order_links WHERE ordre_id IN ($1, $2, $3)", [$before, $anonymous, $payment]));
    }
}
