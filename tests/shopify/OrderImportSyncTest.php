<?php
// tests/shopify/OrderImportSyncTest.php
//
// The paid-order import against a tenant (SD-743, WP09-2): a paid order created, delivered and
// posted once; a run killed at every stage and run again gives one order and one invoice; the
// post-only step after status 3; two workers at once; the Kreditkort fallback; a refused order
// writes nothing and its replay after the fix creates it; a set with a VAT-free line. Child
// processes (support/run_import.php).
//
// History:
// 20261103 CL/SS SD-743: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class OrderImportSyncTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';
    /** @var resource|\PgSql\Connection|null */
    private $conn = null;
    private int $year = 0;
    private int $n = 0;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_m');
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
        if (self::$db !== '') {
            ShopifyEnv::removeTempDir(self::$db);
        }
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $this->conn = ShopifyEnv::connect(self::$db);
        // a fresh install has adresser.mobile (includes/opretfunc.php); an old template may not
        $this->sql('ALTER TABLE adresser ADD COLUMN IF NOT EXISTS mobile varchar(15)');
        foreach (['shopify_errors', 'shopify_jobs', 'shopify_move_barriers', 'shopify_order_links', 'shopify_customer_links', 'shopify_product_links',
            'shopify_shipping_map', 'shopify_payment_map', 'shopify_location_map', 'shopify_brands', 'shopify_lager', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM settings WHERE var_grp = 'shopify' AND var_name IN ('split_set_lager', 'virtual_lager')");
        $this->sql("DELETE FROM transaktioner WHERE ordre_id IN (SELECT id FROM ordrer WHERE kundeordnr LIKE '#SD743%')");
        $this->sql("DELETE FROM pos_betalinger WHERE ordre_id IN (SELECT id FROM ordrer WHERE kundeordnr LIKE '#SD743%')");
        $this->sql("DELETE FROM batch_salg WHERE ordre_id IN (SELECT id FROM ordrer WHERE kundeordnr LIKE '#SD743%')");
        $this->sql("DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE kundeordnr LIKE '#SD743%')");
        $this->sql("DELETE FROM ordrer WHERE kundeordnr LIKE '#SD743%'");
        $this->sql("DELETE FROM openpost WHERE beskrivelse = 'SD743 kladde'");
        $this->sql("DELETE FROM lagerstatus WHERE vare_id IN (SELECT id FROM varer WHERE varenr LIKE 'SD743%')");
        $this->sql("DELETE FROM styklister WHERE indgaar_i IN (SELECT id FROM varer WHERE varenr LIKE 'SD743%')");
        $this->sql("DELETE FROM varer WHERE varenr LIKE 'SD743%'");

        // one open fiscal year covering today, with the template's groups and chart of its last year
        $this->sql("UPDATE grupper SET box10 = 'on' WHERE art = 'RA'");
        $old = $this->sql("SELECT kodenr FROM grupper WHERE art = 'RA' AND beskrivelse = 'SD743'");
        foreach ($old as $o) {
            $this->sql('DELETE FROM grupper WHERE fiscal_year = $1', [(int)$o['kodenr']]);
            $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = $1', [(int)$o['kodenr']]);
        }
        $this->sql("DELETE FROM grupper WHERE art = 'RA' AND beskrivelse = 'SD743'");
        $from = (int)$this->sql("SELECT max(kodenr::int) AS k FROM grupper WHERE art = 'RA'")[0]['k'];
        $this->year = $from + 1;
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('SD743', $1, 'RA', '01', $2, '12', $2, 'on')",
            [(string)$this->year, date('Y')]);
        $cols = implode(', ', array_column($this->sql("SELECT column_name FROM information_schema.columns WHERE table_name = 'grupper'
            AND column_name NOT IN ('id', 'fiscal_year') ORDER BY ordinal_position"), 'column_name'));
        $this->sql("INSERT INTO grupper ($cols, fiscal_year) SELECT $cols, $1 FROM grupper WHERE fiscal_year = $2", [$this->year, $from]);
        $cols = implode(', ', array_column($this->sql("SELECT column_name FROM information_schema.columns WHERE table_name = 'kontoplan'
            AND column_name NOT IN ('id', 'regnskabsaar') ORDER BY ordinal_position"), 'column_name'));
        $this->sql("INSERT INTO kontoplan ($cols, regnskabsaar) SELECT $cols, $1 FROM kontoplan WHERE regnskabsaar = $2", [$this->year, $from]);
        // the web kasse is kasse 3: cash account, VAT code and the card type Kort with its account; other cards on 58050
        $this->sql("UPDATE grupper SET box1 = '3', box2 = $2, box5 = 'Kort', box6 = '58050', box7 = $3 WHERE art = 'POS' AND kodenr = '1' AND fiscal_year = $1",
            [$this->year, "58200\t58200\t58200", "S1\tS1\tS1"]);
        $this->sql("UPDATE grupper SET box6 = '58050', box9 = $2 WHERE art = 'POS' AND kodenr = '2' AND fiscal_year = $1", [$this->year, "7950\t7950\t7950"]);
        $this->sql("UPDATE grupper SET box5 = 'on;on', box10 = '58050' WHERE art = 'DIV' AND kodenr = '3'");
        $this->sql("DELETE FROM grupper WHERE art = 'VG' AND kodenr = '9747'");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year, box3, box4, box7, box8) VALUES ('SD743 Momsfri', '9747', 'VG', $1, '1100', '1100', 'on', 'on')", [$this->year]);

        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, orders_enabled, afd, web_kasse, default_freight_vare_id)
            VALUES ('hml', 'hml-test.myshopify.com', true, true, 5, '3', NULL)");
        $shop = $this->shopId();
        $this->sql("INSERT INTO shopify_brands (sprog, shop_id) VALUES ('havemoebelland', $1)", [$shop]);
        $this->sql("INSERT INTO shopify_lager (lager, rolle, prioritet) VALUES (1, 'saelgbar', 1), (2, 'saelgbar', 2)");
        $this->sql("INSERT INTO shopify_location_map (shop_id, lager, location_gid) VALUES ($1, 1, 'gid://shopify/Location/1'), ($1, 2, 'gid://shopify/Location/2')", [$shop]);
        $this->sql("INSERT INTO shopify_payment_map (shop_id, gateway, card_brand, betalingstype) VALUES ($1, 'shopify_payments', '', 'Kort')", [$shop]);
        $freight = $this->item('FRAGT', ['gruppe' => 4, 'salgspris' => 0]);
        $this->sql("INSERT INTO shopify_shipping_map (shop_id, rate_name, vare_id) VALUES ($1, 'GLS', $2)", [$shop, $freight]);
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->sql("UPDATE grupper SET box5 = 'on;on' WHERE art = 'DIV' AND kodenr = '3'");
            pg_close($this->conn);
        }
    }

    private function sql(string $sql, array $params = []): array
    {
        return ShopifyEnv::rows($this->conn, $sql, $params);
    }

    private function shopId(): int
    {
        return (int)$this->sql("SELECT id FROM shopify_shops WHERE shop_key = 'hml'")[0]['id'];
    }

    private function item(string $suffix, array $fields = []): int
    {
        $fields += ['gruppe' => 12, 'salgspris' => 80, 'samlevare' => ''];
        return (int)$this->sql("INSERT INTO varer (varenr, beskrivelse, gruppe, salgspris, kostpris, serienr, samlevare, stregkode, lukket)
            VALUES ($1, $1, $2, $3, 0, '', $4, '', '') RETURNING id", ["SD743$suffix", $fields['gruppe'], $fields['salgspris'], $fields['samlevare']])[0]['id'];
    }

    private function stock(int $vareId, int $lager, float $qty): void
    {
        $this->sql('INSERT INTO lagerstatus (vare_id, lager, beholdning, variant_id) VALUES ($1, $2, $3, 0)', [$vareId, $lager, $qty]);
        $this->sql('UPDATE varer SET beholdning = COALESCE(beholdning, 0) + $2 WHERE id = $1', [$vareId, $qty]);
    }

    /** A paid order of these lines and 39.00 freight; its gid and name are new for every call. */
    private function order(array $lines, array $over = []): array
    {
        $this->n++;
        $total = number_format(array_sum(array_map(fn($l) => (float)$l['original_total'] - (float)$l['discount'], $lines)) + 39, 2, '.', '');
        return $over + [
            'gid' => 'gid://shopify/Order/' . (743000 + $this->n), 'name' => '#SD743' . $this->n, 'created_at' => date('Y-m-d') . 'T08:00:00Z',
            'source_name' => 'web', 'source_identifier' => '', 'tags' => [], 'note' => 'Stil ved døren', 'email' => 'kunde@example.dk', 'phone' => '+4512345678',
            'taxes_included' => true, 'currency' => 'DKK', 'total' => $total, 'financial_status' => 'PAID', 'customer_gid' => 'gid://shopify/Customer/743',
            'shipping_address' => ['firstName' => 'Kim', 'lastName' => 'Kunde', 'company' => '', 'address1' => 'Havevej 1', 'address2' => '', 'zip' => '5000',
                'city' => 'Odense C', 'country' => 'Denmark', 'countryCodeV2' => 'DK', 'phone' => ''],
            'billing_address' => null,
            'lines' => $lines,
            'shipping' => [['gid' => 'gid://shopify/ShippingLine/1', 'title' => 'GLS', 'code' => 'gls', 'amount' => '39.00']],
            'transactions' => [['gid' => 'gid://shopify/OrderTransaction/' . (9000 + $this->n), 'kind' => 'SALE', 'status' => 'SUCCESS',
                'gateway' => 'shopify_payments', 'amount' => $total, 'card' => 'Visa']],
        ];
    }

    private static function line(string $sku, int $qty, string $total, string $location = 'gid://shopify/Location/1'): array
    {
        return ['gid' => "gid://shopify/LineItem/$sku", 'sku' => "SD743$sku", 'name' => $sku, 'quantity' => $qty, 'current_quantity' => $qty,
            'variant_gid' => null, 'product_gid' => null, 'original_total' => $total, 'discount' => '0.00',
            'assigned_location' => $location, 'assigned_locations' => [$location]];
    }

    private function job(array $order): array
    {
        return ['id' => 0, 'shop_id' => $this->shopId(), 'payload' => ['order_gid' => $order['gid']]];
    }

    /** @return array{exit: int, result: ?array, stderr: string, stdout: string} */
    private function runChild(array $order, string $crash = '', int $hold = 0, bool $wait = true)
    {
        $child = ShopifyEnv::startChild('run_import.php', [self::$db, json_encode($this->job($order)), json_encode($order), $crash, $hold]);
        return $wait ? ShopifyEnv::waitChild($child) : $child;
    }

    private function import(array $order): array
    {
        $run = $this->runChild($order);
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertArrayHasKey('result', $run['result'] ?? [], $run['stdout'] . $run['stderr']);
        return $run['result']['result'];
    }

    /** Orders, their status, the posting's balance and open posts, the link and the write-back jobs of one Shopify order. */
    private function outcome(array $order): array
    {
        $orders = $this->sql('SELECT id, status, sum, moms, betalingsbet, felt_1, felt_2, felt_5, konto_id FROM ordrer WHERE kundeordnr = $1', [$order['name']]);
        $id = isset($orders[0]) ? (int)$orders[0]['id'] : 0;
        $tx = $this->sql('SELECT count(*) AS n, COALESCE(sum(debet), 0) - COALESCE(sum(kredit), 0) AS balance FROM transaktioner WHERE ordre_id = $1', [$id])[0];
        return [
            'orders' => count($orders),
            'status' => isset($orders[0]) ? (int)$orders[0]['status'] : null,
            'order' => $orders[0] ?? null,
            'postings' => (int)$tx['n'],
            'balance' => round((float)$tx['balance'], 2),
            'open_posts' => (int)$this->sql("SELECT count(*) AS n FROM openpost WHERE refnr = $1 AND beskrivelse <> 'SD743 kladde'", [$id])[0]['n'],
            'link' => $this->sql('SELECT state, ordre_id FROM shopify_order_links WHERE order_gid = $1', [$order['gid']])[0]['state'] ?? null,
            'writeback' => array_column($this->sql("SELECT dedupe_key FROM shopify_jobs WHERE type = 'order.tags' AND order_gid = $1", [$order['gid']]), 'dedupe_key'),
        ];
    }

    public function testAPaidOrderFromTwoLocationsIsCreatedDeliveredAndPostedOnce(): void
    {
        $a = $this->item('A');
        $b = $this->item('B');
        $this->stock($a, 1, 5);
        $this->stock($b, 2, 5);
        // freight at 25 % too: one rate and no set, so momsupdat() sums the order itself in bogfor()
        $this->sql("UPDATE shopify_shipping_map SET vare_id = $1", [$this->item('FRAGT25', ['salgspris' => 0])]);
        // an open post of a kassekladde voucher whose number is the coming order id: not this order's
        $next = (int)$this->sql("SELECT CASE WHEN is_called THEN last_value + 1 ELSE last_value END AS n FROM ordrer_id_seq")[0]['n'];
        $this->sql("INSERT INTO openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, refnr) VALUES (1, '1', '77', 10, 'SD743 kladde', '0', now(), $1)", [$next]);
        $order = $this->order([self::line('A', 2, '250.00'), self::line('B', 1, '125.00', 'gid://shopify/Location/2')], ['total_tax' => '82.80']);

        $result = $this->import($order);
        $this->assertSame('invoiced', $result['result']['state'] ?? null, json_encode($result));
        $o = $this->outcome($order);
        $this->assertSame(1, $o['orders']);
        $this->assertSame(4, $o['status']);
        $this->assertSame(['Kreditkort', 'Kort', '414.00', '3'], [$o['order']['betalingsbet'], $o['order']['felt_1'], $o['order']['felt_2'], $o['order']['felt_5']]);
        $this->assertSame(414.0, round((float)$o['order']['sum'] + (float)$o['order']['moms'], 2), 'the invoice is what was paid');
        $this->assertGreaterThan(0, $o['postings']);
        $this->assertSame(0.0, $o['balance'], 'the posting balances');
        $this->assertSame(0, $o['open_posts'], 'a paid order leaves no open post');
        $this->assertSame(0, (int)$this->sql("SELECT count(*) AS n FROM transaktioner WHERE ordre_id = $1 AND beskrivelse LIKE 'Dagsafslutning%'", [$o['order']['id']])[0]['n'],
            'posted as the order, not as a day-end');
        $this->assertSame('invoiced', $o['link']);
        $this->assertSame(['hml:' . $order['gid']], $o['writeback'], 'the write-back is handed off once');
        $this->assertSame('queued', $this->sql("SELECT state FROM shopify_jobs WHERE type = 'order.tags'")[0]['state'], 'and waits for its handler (WP10)');
        $this->assertGreaterThan(0, (int)$o['order']['konto_id'], 'the customer is a debtor');

        $lines = $this->sql('SELECT vare_id, antal, pris, lager FROM ordrelinjer WHERE ordre_id = $1 AND vare_id IN ($2, $3) ORDER BY vare_id', [$o['order']['id'], $a, $b]);
        $this->assertSame([[$a, 2.0, 100.0, 1], [$b, 1.0, 100.0, 2]], array_map(fn($l) => [(int)$l['vare_id'], (float)$l['antal'], (float)$l['pris'], (int)$l['lager']], $lines),
            'net prices; each line on the lager it is drawn from (K15)');
        $stock = $this->sql('SELECT vare_id, lager, beholdning FROM lagerstatus WHERE vare_id IN ($1, $2) ORDER BY vare_id', [$a, $b]);
        $this->assertSame([[$a, 1, 3.0], [$b, 2, 4.0]], array_map(fn($s) => [(int)$s['vare_id'], (int)$s['lager'], (float)$s['beholdning']], $stock), 'the stock is drawn');
        $barriers = array_map('intval', array_column($this->sql("SELECT vare_id FROM shopify_move_barriers WHERE ordre_id = $1 AND state = 'open' ORDER BY vare_id", [$o['order']['id']]), 'vare_id'));
        $this->assertSame([$a, $b], $barriers, 'a move barrier per item');

        // NF-1: running it again changes nothing
        $again = $this->import($order);
        $this->assertSame('invoiced', $again['result']['state'] ?? null);
        $this->assertSame($o, $this->outcome($order));
    }

    public function testARunKilledAtEveryStageIsResumedToOneOrderAndOneInvoice(): void
    {
        $a = $this->item('A');
        $this->stock($a, 1, 100);
        $reference = null;
        foreach (['reserved', 'creating', 'created', 'delivering', 'delivered', 'posting', 'posted'] as $stage) {
            $order = $this->order([self::line('A', 3, '375.00')]);
            $killed = $this->runChild($order, $stage);
            $this->assertSame(9, $killed['exit'], "killed at $stage: " . $killed['stdout'] . $killed['stderr']);
            $result = $this->import($order);
            $this->assertSame('invoiced', $result['result']['state'] ?? null, "$stage: " . json_encode($result));
            $o = $this->outcome($order);
            $this->assertSame([1, 4, 0.0, 0, 'invoiced', 1], [$o['orders'], $o['status'], $o['balance'], $o['open_posts'], $o['link'], count($o['writeback'])], "resumed after $stage");
            $reference ??= $o['postings'];
            $this->assertSame($reference, $o['postings'], "after $stage the order is posted once");
        }
        $this->assertSame(79.0, (float)$this->sql('SELECT beholdning FROM lagerstatus WHERE vare_id = $1', [$a])[0]['beholdning'], 'seven orders of 3 drawn once each');
    }

    public function testAnOrderLeftAtStatus3IsPostedByThePostOnlyStep(): void
    {
        $this->sql("UPDATE grupper SET box5 = '' WHERE art = 'DIV' AND kodenr = '3'");
        $this->sql("DELETE FROM settings WHERE var_name = 'postEachSale'");
        $a = $this->item('A');
        $this->stock($a, 1, 10);
        $order = $this->order([self::line('A', 1, '125.00')]);
        $killed = $this->runChild($order, 'posting_only');
        $this->assertSame(9, $killed['exit'], $killed['stdout'] . $killed['stderr']);
        $this->assertSame(3, $this->outcome($order)['status'], 'bogfor() without immediate posting leaves status 3');

        $result = $this->import($order);
        $this->assertSame(['invoiced', 4], [$result['result']['state'] ?? null, $result['result']['status'] ?? null], json_encode($result));
        $o = $this->outcome($order);
        $this->assertSame([0.0, 0, 1], [$o['balance'], $o['open_posts'], count($o['writeback'])]);
    }

    public function testASecondWorkerOnTheSameOrderYieldsAndOneInvoiceIsMade(): void
    {
        $a = $this->item('A');
        $this->stock($a, 1, 10);
        $order = $this->order([self::line('A', 1, '125.00')]);
        $first = $this->runChild($order, '', 3, false);
        usleep(1500000);
        $second = $this->runChild($order);
        $this->assertSame(['outcome' => 'yield', 'run_after' => 30], $second['result']['result'] ?? null, $second['stdout'] . $second['stderr']);
        $first = ShopifyEnv::waitChild($first);
        $this->assertSame('invoiced', $first['result']['result']['result']['state'] ?? null, $first['stdout'] . $first['stderr']);
        $this->assertSame('invoiced', $this->import($order)['result']['state'] ?? null);
        $o = $this->outcome($order);
        $this->assertSame([1, 4, 1], [$o['orders'], $o['status'], count($o['writeback'])]);
    }

    public function testWithoutAWebKasseTheOrderPostsAsKreditkortWithAnAlarm(): void
    {
        $this->sql("UPDATE shopify_shops SET web_kasse = NULL");
        $a = $this->item('A');
        $this->stock($a, 1, 10);
        $order = $this->order([self::line('A', 1, '125.00')]);
        $this->assertSame(9, $this->runChild($order, 'created')['exit'], 'killed right after the creation: its alarms are kept');
        $result = $this->import($order);
        $this->assertSame('invoiced', $result['result']['state'] ?? null, json_encode($result));
        $o = $this->outcome($order);
        $this->assertSame(['Kreditkort', '', 4, 0.0, 0], [$o['order']['betalingsbet'], (string)$o['order']['felt_1'], $o['status'], $o['balance'], $o['open_posts']],
            'posted on the Kreditkort account with no open post');
        $this->assertSame(0, (int)$this->sql("SELECT count(*) AS n FROM transaktioner WHERE ordre_id = $1 AND beskrivelse LIKE 'Dagsafslutning%'", [$o['order']['id']])[0]['n'],
            'posted as the order, not as a day-end');
        $this->assertSame(1, (int)$this->sql("SELECT count(*) AS n FROM transaktioner WHERE ordre_id = $1 AND kontonr = 58050 AND debet = 164", [$o['order']['id']])[0]['n'],
            'the paid amount on DIV/3 box10');
        $this->assertSame(['order.payment'], array_column($this->sql("SELECT kind FROM shopify_errors WHERE severity = 'warning' AND resolved_at IS NULL"), 'kind'));
    }

    public function testAnOrderWhoseVatDiffersFromWhatShopifyChargedIsRefused(): void
    {
        $a = $this->item('A');
        $this->stock($a, 1, 10);
        $order = $this->order([self::line('A', 1, '125.00')], ['total_tax' => '27.33']);
        $this->assertSame(['refused' => ['order.create']], $this->import($order)['result'] ?? null, 'Shopify charged 27.33, the invoice has 25.00');
        $this->assertSame(0, $this->outcome($order)['orders']);
        $this->assertStringContainsString('Shopify charged 27.33', $this->sql("SELECT message FROM shopify_errors WHERE kind = 'order.create'")[0]['message']);
    }

    public function testAnOrderWithoutACustomerIsRefusedAndWritesNothing(): void
    {
        $a = $this->item('A');
        $this->stock($a, 1, 10);
        $order = $this->order([self::line('A', 1, '125.00')], ['customer_gid' => null]);
        $this->assertSame(['refused' => ['order.customer']], $this->import($order)['result'] ?? null);
        $this->assertSame([0, null], [$this->outcome($order)['orders'], $this->outcome($order)['link']], 'nothing is written');
    }

    public function testAPostingThatDoesNotReachStatus4IsRefused(): void
    {
        $this->sql("UPDATE grupper SET box5 = '' WHERE art = 'DIV' AND kodenr = '3'");
        $this->sql("DELETE FROM settings WHERE var_name = 'postEachSale'");
        $this->sql("CREATE OR REPLACE FUNCTION sd743_hold_status() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN IF NEW.status = 4 AND NEW.kundeordnr LIKE '#SD743%' THEN NEW.status := 3; END IF; RETURN NEW; END $$");
        $this->sql('CREATE TRIGGER sd743_hold_status BEFORE UPDATE ON ordrer FOR EACH ROW EXECUTE FUNCTION sd743_hold_status()');
        try {
            $a = $this->item('A');
            $this->stock($a, 1, 10);
            $order = $this->order([self::line('A', 1, '125.00')]);
            $this->assertSame(['refused' => ['order.invoice']], $this->import($order)['result'] ?? null);
            $this->assertStringContainsString('status 3 after posting', $this->sql("SELECT message FROM shopify_errors WHERE kind = 'order.invoice'")[0]['message']);
            $this->assertSame([[], 'delivery_committed'], [$this->outcome($order)['writeback'], $this->outcome($order)['link']], 'not invoiced, no write-back');
        } finally {
            $this->sql('DROP TRIGGER IF EXISTS sd743_hold_status ON ordrer');
        }
    }

    public function testARoundingDifferenceGoesOnTheKassesAccountAndWithoutOneTheOrderIsRefusedBeforeAnyWrite(): void
    {
        $a = $this->item('A');
        $b = $this->item('B');
        $this->stock($a, 1, 10);
        $this->stock($b, 1, 10);
        $order = $this->order([self::line('A', 1, '1.02'), self::line('B', 1, '1.02')]);
        $this->assertSame('invoiced', $this->import($order)['result']['state'] ?? null);
        $o = $this->outcome($order);
        $this->assertSame([4, 0.0, 0], [$o['status'], $o['balance'], $o['open_posts']]);
        $this->assertSame(41.04, round((float)$o['order']['sum'] + (float)$o['order']['moms'], 2), 'the rounding is on the VAT: the invoice is what was paid');
        $this->assertSame(0, (int)$this->sql('SELECT count(*) AS n FROM transaktioner WHERE ordre_id = $1 AND kontonr = 58200', [$o['order']['id']])[0]['n'],
            'nothing on the kasse\'s cash account');

        $this->sql("UPDATE grupper SET box9 = '7950' WHERE art = 'POS' AND kodenr = '2' AND fiscal_year = $1", [$this->year]);
        $order = $this->order([self::line('A', 1, '1.02'), self::line('B', 1, '1.02')]);
        $this->assertSame(['refused' => ['order.create']], $this->import($order)['result'] ?? null, 'kasse 3 has no account for øredifferencer');
        $this->assertSame([0, 'validated'], [$this->outcome($order)['orders'], $this->outcome($order)['link']], 'no order is written');
        $this->assertStringContainsString('øredifferencer', $this->sql("SELECT message FROM shopify_errors WHERE kind = 'order.create'")[0]['message']);

        // removed after the order was created: refused before bogfor_nu() could exit the worker on it
        $this->sql("UPDATE grupper SET box9 = $2 WHERE art = 'POS' AND kodenr = '2' AND fiscal_year = $1", [$this->year, "7950\t7950\t7950"]);
        $order = $this->order([self::line('A', 1, '1.02'), self::line('B', 1, '1.02')]);
        $this->assertSame(9, $this->runChild($order, 'delivered')['exit']);
        $this->sql("UPDATE grupper SET box9 = '7950' WHERE art = 'POS' AND kodenr = '2' AND fiscal_year = $1", [$this->year]);
        $this->assertSame(['refused' => ['order.invoice']], $this->import($order)['result'] ?? null);
        $this->assertSame([0, 'delivery_committed'], [$this->outcome($order)['postings'], $this->outcome($order)['link']], 'not posted');
    }

    public function testASettingChangedBeforeThePostingIsCheckedAgainAndNoOpenPostIsLeft(): void
    {
        $this->sql("UPDATE shopify_shops SET web_kasse = NULL");
        $a = $this->item('A');
        $this->stock($a, 1, 10);
        $order = $this->order([self::line('A', 1, '125.00')]);
        $this->assertSame(9, $this->runChild($order, 'delivered')['exit']);
        $this->sql("UPDATE grupper SET box10 = '' WHERE art = 'DIV' AND kodenr = '3'");
        try {
            $this->assertSame(['refused' => ['order.invoice']], $this->import($order)['result'] ?? null);
            $o = $this->outcome($order);
            $this->assertSame([0, 0, 'delivery_committed', []], [$o['postings'], $o['open_posts'], $o['link'], $o['writeback']], 'not posted, no open post');
        } finally {
            $this->sql("UPDATE grupper SET box10 = '58050' WHERE art = 'DIV' AND kodenr = '3'");
        }
        $this->assertSame('invoiced', $this->import($order)['result']['state'] ?? null, 'the replay after the fix posts it');
        $this->assertSame(0, (int)$this->sql("SELECT count(*) AS n FROM shopify_errors WHERE kind = 'order.invoice' AND resolved_at IS NULL")[0]['n']);
    }

    public function testAnOrderStartedBeforeTheOrdersFlowWasSwitchedOffIsListed(): void
    {
        $a = $this->item('A');
        $this->stock($a, 1, 10);
        $order = $this->order([self::line('A', 1, '125.00')]);
        $this->assertSame(9, $this->runChild($order, 'delivered')['exit']);
        $this->sql('UPDATE shopify_shops SET orders_enabled = false');
        $this->assertSame(['skipped' => 'orders flow off'], $this->import($order)['result'] ?? null);
        $this->assertStringContainsString('stopped at delivery_committed', $this->sql("SELECT message FROM shopify_errors WHERE kind = 'order.invoice'")[0]['message'] ?? '');
        $this->sql('UPDATE shopify_shops SET orders_enabled = true');
        $this->assertSame('invoiced', $this->import($order)['result']['state'] ?? null);
    }

    public function testARefusedOrderWritesNothingAndItsReplayAfterTheFixCreatesIt(): void
    {
        $order = $this->order([self::line('NY', 1, '125.00')]);
        $this->assertSame(['refused' => ['order.sku']], $this->import($order)['result'] ?? null);
        $o = $this->outcome($order);
        $this->assertSame([0, null], [$o['orders'], $o['link']], 'no order, no link');
        $this->assertSame(1, (int)$this->sql("SELECT count(*) AS n FROM shopify_errors WHERE kind = 'order.sku' AND resolved_at IS NULL")[0]['n']);

        $this->stock($this->item('NY'), 1, 1);
        $this->assertSame('invoiced', $this->import($order)['result']['state'] ?? null);
        $this->assertSame([1, 4], [$this->outcome($order)['orders'], $this->outcome($order)['status']]);
        $this->assertSame(0, (int)$this->sql("SELECT count(*) AS n FROM shopify_errors WHERE severity = 'error' AND resolved_at IS NULL")[0]['n'], 'the K25 error is resolved');
    }

    public function testASetIsInvoicedAtItsPriceWithItsComponentsDrawnBesideAVatFreeLine(): void
    {
        $set = $this->item('SAET', ['salgspris' => 0, 'samlevare' => 'on']);
        $c1 = $this->item('K1', ['salgspris' => 60]);
        $c2 = $this->item('K2', ['salgspris' => 45]);
        $free = $this->item('FRI', ['gruppe' => 9747, 'salgspris' => 50]);
        $this->sql("INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $3, 2, 1), ($2, $3, 1, 2)", [$c1, $c2, $set]);
        $this->stock($c1, 1, 10);
        $this->stock($c2, 1, 10);
        $this->stock($free, 1, 10);
        $order = $this->order([self::line('SAET', 1, '149.95'), self::line('FRI', 1, '50.00')]);

        $result = $this->import($order);
        $this->assertSame('invoiced', $result['result']['state'] ?? null, json_encode($result));
        $o = $this->outcome($order);
        $this->assertSame(238.95, round((float)$o['order']['sum'] + (float)$o['order']['moms'], 2), 'set, VAT-free line and freight at what was paid (K26)');
        $this->assertSame([0.0, 0], [$o['balance'], $o['open_posts']]);
        $stock = $this->sql('SELECT vare_id, beholdning FROM lagerstatus WHERE vare_id IN ($1, $2) ORDER BY vare_id', [$c1, $c2]);
        $this->assertSame([[$c1, 8.0], [$c2, 9.0]], array_map(fn($s) => [(int)$s['vare_id'], (float)$s['beholdning']], $stock), 'the components are drawn');
        $lines = $this->sql('SELECT vare_id, posnr FROM ordrelinjer WHERE ordre_id = $1 ORDER BY posnr', [$o['order']['id']]);
        $this->assertSame([$c1, $c2, $set, $free], array_slice(array_map(fn($l) => (int)$l['vare_id'], $lines), 0, 4), 'the set\'s lines, the set line, then the next line');
    }
}
