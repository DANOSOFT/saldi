<?php
// tests/shopify/OrderIntakeSyncTest.php
//
// The paid-order intake against a tenant (SD-742, WP09-1): what shopify_intake_context() reads
// (items by variant link and SKU, groups and accounts, BOMs, stock, lagre, freight and payment
// maps, Kreditkort, fiscal year), the K25 errors and alarms it reports, the delivery preflight
// and the scoped legacy globals. Child processes (support/run_intake.php).
//
// History:
// 20261103 CL/SS SD-742: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class OrderIntakeSyncTest extends TestCase
{
    private const GID = 'gid://shopify/Order/5001';

    private static ?string $skip = null;
    private static string $db = '';
    /** @var resource|\PgSql\Connection|null */
    private $conn = null;
    private static array $year = [];

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_i');
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
        foreach (['shopify_errors', 'shopify_jobs', 'shopify_product_links', 'shopify_shipping_map', 'shopify_payment_map', 'shopify_location_map',
            'shopify_brands', 'shopify_lager', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM settings WHERE var_grp = 'shopify' AND var_name IN ('split_set_lager', 'virtual_lager')");
        $this->sql("DELETE FROM varer WHERE varenr LIKE 'SD743%'");
        $this->sql("DELETE FROM variant_varer WHERE variant_stregkode LIKE 'SD743%'");
        // one fiscal year covering today, the only one the bootstrap can pick
        $this->sql("UPDATE grupper SET box10 = 'on' WHERE art = 'RA'");
        $this->sql("DELETE FROM grupper WHERE art = 'RA' AND beskrivelse = 'SD743'");
        $kodenr = (int)$this->sql("SELECT COALESCE(max(kodenr::int), 0) + 1 AS k FROM grupper WHERE art = 'RA'")[0]['k'];
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('SD743', $1, 'RA', '01', $2, '12', $2, 'on')",
            [(string)$kodenr, date('Y')]);
        self::$year = ['kodenr' => $kodenr, 'from' => date('Y') . '-01-01', 'to' => date('Y') . '-12-31'];
        // an item group with a sales account in the year, and the Kreditkort account
        $this->sql("DELETE FROM grupper WHERE art = 'VG' AND beskrivelse LIKE 'SD743%'");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year, box3, box4, box8) VALUES ('SD743 Varer', '9743', 'VG', $1, '1000', '1000', 'on'),
            ('SD743 Uden konto', '9744', 'VG', $1, '', '', ''), ('SD743 Uden momskode', '9745', 'VG', $1, '1000', '1001', 'on'),
            ('SD743 Ukendt konto', '9746', 'VG', $1, '1000', '4711', 'on')", [(string)$kodenr]);
        // a posting setup for the year: sales account 1000 with VAT code S1, its VAT account, Kreditkort, and kasse 3 of the POS
        $this->sql("DELETE FROM kontoplan WHERE regnskabsaar = $1", [$kodenr]);
        $this->sql("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar) VALUES (1000, 'SD743 Salg', 'D', 'S1', $1),
            (1001, 'SD743 Salg uden moms', 'D', '', $1), (58050, 'SD743 Kreditkort', 'S', '', $1), (58200, 'SD743 Kasse', 'S', '', $1),
            (66100, 'SD743 Salgsmoms', 'S', '', $1)", [$kodenr]);
        $this->sql("DELETE FROM grupper WHERE art IN ('SM', 'POS') AND fiscal_year = $1", [$kodenr]);
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, kode, art, fiscal_year, box1, box2) VALUES ('SD743 Salgsmoms', '1', 'S', 'SM', $1, '66100', '25')", [$kodenr]);
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year, box2, box5, box6, box7) VALUES ('SD743 POS', '1', 'POS', $1, $2, 'Kort', '58050', $3)",
            [$kodenr, "\t\t58200", "\t\tS1"]);
        $this->sql("UPDATE grupper SET box10 = '58050' WHERE art = 'DIV' AND kodenr = '3'");
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, orders_enabled, afd, web_kasse, default_freight_vare_id)
            VALUES ('hml', 'hml-test.myshopify.com', true, true, 5, '3', NULL)");
        $shop = $this->shopId();
        $this->sql("INSERT INTO shopify_brands (sprog, shop_id) VALUES ('havemoebelland', $1)", [$shop]);
        $this->sql("INSERT INTO shopify_lager (lager, rolle, prioritet) VALUES (1, 'saelgbar', 1), (2, 'saelgbar', 2), (3, 'udstilling', NULL), (4, 'saelgbar', 3)");
        $this->sql("INSERT INTO shopify_location_map (shop_id, lager, location_gid) VALUES ($1, 1, 'gid://shopify/Location/1'), ($1, 2, 'gid://shopify/Location/2'),
            ($1, 3, 'gid://shopify/Location/3'), ($1, 4, 'gid://shopify/Location/4')", [$shop]);
        $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify', 'virtual_lager', '4'), ('shopify', 'split_set_lager', '2')");
        $this->sql("INSERT INTO shopify_payment_map (shop_id, gateway, card_brand, betalingstype) VALUES ($1, 'shopify_payments', '', 'Kort')", [$shop]);
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->sql("DELETE FROM grupper WHERE art = 'RA' AND beskrivelse = 'SD743'");
            $this->sql("UPDATE grupper SET box10 = '' WHERE art = 'RA'");
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

    private function steps(array ...$steps): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_intake.php', [self::$db, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result']['results'];
    }

    private function item(string $suffix, array $fields = []): int
    {
        $fields += ['gruppe' => 9743, 'salgspris' => 100, 'serienr' => '', 'samlevare' => '', 'stregkode' => ''];
        return (int)$this->sql("INSERT INTO varer (varenr, beskrivelse, gruppe, salgspris, kostpris, serienr, samlevare, stregkode, lukket)
            VALUES ($1, $1, $2, $3, 0, $4, $5, $6, '') RETURNING id",
            ["SD743$suffix", $fields['gruppe'], $fields['salgspris'], $fields['serienr'], $fields['samlevare'], $fields['stregkode']])[0]['id'];
    }

    private function stock(int $vareId, int $lager, float $qty, int $variantId = 0): void
    {
        $this->sql('INSERT INTO lagerstatus (vare_id, lager, beholdning, variant_id) VALUES ($1, $2, $3, $4)', [$vareId, $lager, $qty, $variantId]);
    }

    /** A paid order whose total and payment are its lines plus 39.00 freight. */
    private static function order(array $lines, array $over = []): array
    {
        $total = number_format(array_sum(array_column($lines, 'original_total')) + 39, 2, '.', '');
        return $over + [
            'gid' => self::GID, 'name' => '#HML5001', 'created_at' => date('Y-m-d') . 'T10:00:00Z', 'source_name' => 'web', 'source_identifier' => '',
            'tags' => [], 'note' => '', 'email' => 'k@example.dk', 'phone' => '', 'taxes_included' => true, 'currency' => 'DKK', 'total' => $total,
            'financial_status' => 'PAID', 'customer_gid' => 'gid://shopify/Customer/7', 'shipping_address' => null, 'billing_address' => null,
            'lines' => $lines,
            'shipping' => [['gid' => 'gid://shopify/ShippingLine/1', 'title' => 'GLS', 'code' => 'gls', 'amount' => '39.00']],
            'transactions' => [['gid' => 'gid://shopify/OrderTransaction/1', 'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments',
                'amount' => $total, 'card' => 'Visa']],
        ];
    }

    private static function line(string $sku, int $qty = 1, ?string $variantGid = null, string $location = 'gid://shopify/Location/1'): array
    {
        return ['gid' => "gid://shopify/LineItem/$sku", 'sku' => $sku, 'name' => $sku, 'quantity' => $qty, 'current_quantity' => $qty, 'variant_gid' => $variantGid, 'product_gid' => null,
            'original_total' => number_format(100 * $qty, 2, '.', ''), 'discount' => '0.00', 'assigned_location' => $location];
    }

    public function testTheContextReadsItemsLagreMapsAndTheFiscalYear(): void
    {
        $x = $this->item('X', ['stregkode' => '5701234567890']);
        $set = $this->item('SET', ['samlevare' => 'on']);
        $bom = (int)$this->sql('INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $2, 2, 1) RETURNING id', [$x, $set])[0]['id'];
        $this->stock($x, 1, 3);
        $this->stock($x, 2, 1.5);
        $shop = $this->shopId();
        $this->sql("INSERT INTO shopify_product_links (shop_id, vare_id, variant_id, product_gid, variant_gid) VALUES ($1, $2, 0, 'gid://shopify/Product/1', 'gid://shopify/ProductVariant/9')", [$shop, $x]);
        $this->sql("INSERT INTO shopify_shipping_map (shop_id, rate_name, vare_id, pickup_lager) VALUES ($1, 'GLS', $2, NULL)", [$shop, $x]);

        [$ctx] = $this->steps(['context', 'hml', self::order([self::line('5701234567890', 1, 'gid://shopify/ProductVariant/9'), self::line('SD743SET')])]);

        $this->assertSame($x, $ctx['by_variant']['gid://shopify/ProductVariant/9']['vare_id'], 'by the variant link');
        $this->assertSame($x, $ctx['by_sku']['5701234567890']['vare_id'], 'a SKU matches the barcode too');
        $this->assertNull($ctx['by_sku']['5701234567890']['problem'], 'sales account 1000 with VAT code S1 and its VAT account');
        $this->assertTrue($ctx['by_sku']['SD743SET']['set']);
        $this->assertSame([['bom_id' => $bom, 'vare_id' => $x, 'quantity_milli' => 2000]], $ctx['by_sku']['SD743SET']['bom']);
        $this->assertSame([1 => 3000, 2 => 1500], $ctx['stock_milli'][$x]);
        $this->assertSame([1, 2, 3], array_column($ctx['locations'], 'lager'), 'the virtual lager is left out');
        $this->assertSame('gid://shopify/Location/3', $ctx['locations'][2]['location_gid']);
        $this->assertSame(2, $ctx['assembly_lager']);
        $this->assertSame(['vare_id' => $x, 'pickup_lager' => null], $ctx['shipping']['GLS']);
        $this->assertArrayHasKey($x, $ctx['items'], 'the freight item, the components and the line items by vare_id');
        $this->assertSame(['kasse_account' => '58200', 'kasse_account_ok' => true, 'kasse_vat' => 'S1', 'card_accounts' => ['kort' => true], 'fallback_card_account_ok' => false],
            $ctx['pos'], 'kasse 3: the third entry of POS box2 and box7; Kort posts to 58050');
        $this->assertSame(25.0, (float)$ctx['by_sku']['5701234567890']['vat_rate'], 'the rate of VAT group 1');
        $this->assertSame(['shopify_payments' . "\x1f" . 'Visa' => 'Kort'], $ctx['payments'], 'the gateway entry without a card brand');
        $this->assertTrue($ctx['kreditkort_ok']);
        $this->assertSame(['from' => self::$year['from'], 'to' => self::$year['to']], $ctx['fiscal']);
        $this->assertSame(date('Y-m-d'), $ctx['today']);
        $this->assertNull($ctx['period_closed']);
        $this->assertSame('havemoebelland', $ctx['brand_sprog']);
    }

    public function testAnOrderWithAnUnknownSkuReportsOneK25ErrorAndWritesNothingElse(): void
    {
        $x = $this->item('X');
        $this->stock($x, 1, 5);
        $this->sql("UPDATE shopify_shops SET default_freight_vare_id = $1 WHERE shop_key = 'hml'", [$x]);
        $counts = fn() => array_map(fn($t) => (int)$this->sql("SELECT count(*) AS n FROM $t")[0]['n'], ['ordrer', 'ordrelinjer', 'batch_salg']);
        $before = $counts();

        [$r] = $this->steps(['report', 'hml', self::order([self::line('SD743X'), self::line('NOPE')])]);

        $this->assertFalse($r['ok']);
        $errors = $this->sql("SELECT kind, ref, severity, message FROM shopify_errors WHERE resolved_at IS NULL ORDER BY kind");
        $this->assertSame([['order.sku', self::GID, 'error']], array_map(fn($e) => [$e['kind'], $e['ref'], $e['severity']], array_values(array_filter($errors, fn($e) => $e['severity'] === 'error'))));
        $this->assertSame(['order.freight'], array_values(array_column(array_filter($errors, fn($e) => $e['severity'] === 'warning'), 'kind')),
            'the default freight item is an alarm, not an error');
        $this->assertSame($before, $counts(), 'K25: no order, order line or delivery row');
        $this->assertSame(2, (int)$this->sql("SELECT count(*) AS n FROM shopify_jobs WHERE type = 'mail.alert'")[0]['n'], 'support is alarmed once per open row');
    }

    public function testAGroupWithoutASalesAccountAndAKreditkortAccountOutsideTheYearAreErrors(): void
    {
        $this->item('X', ['gruppe' => 9744]);
        $this->sql("UPDATE grupper SET box10 = '99999' WHERE art = 'DIV' AND kodenr = '3'");

        [$v] = $this->steps(['validate', 'hml', self::order([self::line('SD743X')])]);

        $this->assertSame(['order.account', 'order.freight', 'config.kreditkort'], array_column($v['errors'], 'kind'),
            'the freight rate is unmapped and the shop has no default freight item either');
    }

    public function testTheSalesAccountMustBeInTheChartWithAVatCodeUnlessTheGroupIsVatFree(): void
    {
        $missing = $this->item('MISSING', ['gruppe' => 9746]);
        $vatFree = $this->item('FREE', ['gruppe' => 9745]);
        $this->sql("UPDATE grupper SET box7 = '' WHERE art = 'VG' AND kodenr = 9745");
        $taxed = $this->item('TAXED', ['gruppe' => 9745]);

        [$ctx] = $this->steps(['context', 'hml', self::order([self::line('SD743MISSING'), self::line('SD743TAXED')])]);
        $this->sql("UPDATE grupper SET box7 = 'on' WHERE art = 'VG' AND kodenr = 9745");
        [$free] = $this->steps(['context', 'hml', self::order([self::line('SD743FREE')])]);

        $this->assertSame('has sales account 4711, which is not in the chart of accounts for the fiscal year', $ctx['by_sku']['SD743MISSING']['problem']);
        $this->assertSame('has sales account 1001 without a VAT code with a VAT account', $ctx['by_sku']['SD743TAXED']['problem'],
            'opret_ordrelinje() would write the line without VAT and alert');
        $this->assertNull($free['by_sku']['SD743FREE']['problem'], 'a VAT-free group needs no VAT code');
        $this->assertGreaterThan(0, $missing + $vatFree);
    }

    public function testAVatGroupWithoutARateBatchControlAndAmbiguousSkusAreFound(): void
    {
        $this->sql("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar) VALUES (1002, 'SD743 Salg S2', 'D', 'S2', $1)", [self::$year['kodenr']]);
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, kode, art, fiscal_year, box1, box2) VALUES ('SD743 Uden sats', '2', 'S', 'SM', $1, '66100', '')", [self::$year['kodenr']]);
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year, box3, box4, box7, box9) VALUES ('SD743 Uden sats', '9747', 'VG', $1, '1000', '1002', '', ''),
            ('SD743 Batch', '9748', 'VG', $1, '1000', '1000', '', 'on')", [(string)self::$year['kodenr']]);
        $this->item('NORATE', ['gruppe' => 9747]);
        $this->item('BATCH', ['gruppe' => 9748]);
        $dup = $this->item('DUP');
        $this->item('DUP2', ['stregkode' => 'SD743DUP']);
        $owner = $this->item('OWNER');
        $this->sql("INSERT INTO variant_varer (vare_id, variant_stregkode) VALUES ($1, 'sd743clash')", [$owner]);
        $clash = $this->item('CLASH');

        [$ctx] = $this->steps(['context', 'hml', self::order([self::line('SD743NORATE'), self::line('SD743BATCH'), self::line('SD743DUP'), self::line('SD743CLASH')])]);

        $this->assertSame('has VAT group 2 without a rate in a group that is not VAT-free', $ctx['by_sku']['SD743NORATE']['problem'],
            'opret_ordrelinje() would fall back to the order rate');
        $this->assertTrue($ctx['by_sku']['SD743BATCH']['batch']);
        $this->assertTrue($ctx['by_sku']['SD743DUP']['sku_ambiguous'], "one item's varenr is another's barcode");
        $this->assertSame($dup, $ctx['by_sku']['SD743DUP']['vare_id']);
        $this->assertTrue($ctx['by_sku']['SD743CLASH']['written_as_other'], 'SD743CLASH is another item\'s variant barcode, matched case-insensitively');
        $this->assertGreaterThan(0, $clash);
    }

    public function testAVariantLineDrawsOnItsOwnVariantsStock(): void
    {
        $x = $this->item('V');
        $a = (int)$this->sql("INSERT INTO variant_varer (vare_id, variant_stregkode) VALUES ($1, 'SD743-A') RETURNING id", [$x])[0]['id'];
        $b = (int)$this->sql("INSERT INTO variant_varer (vare_id, variant_stregkode) VALUES ($1, 'SD743-B') RETURNING id", [$x])[0]['id'];
        $this->stock($x, 1, 5, $a);
        $this->stock($x, 1, 0, $b);
        $this->stock($x, 2, 1, $a);
        $this->stock($x, 2, 1, $a);

        [$ctx] = $this->steps(['context', 'hml', self::order([self::line('SD743-A', 2)])]);
        $this->sql('DELETE FROM variant_varer WHERE vare_id = $1', [$x]);

        $item = $ctx['by_sku']['SD743-A'];
        $this->assertSame([$a, 'SD743-A', 1000000000000 + $a], [$item['variant_id'], $item['variant_barcode'], $item['stock_key']]);
        $this->assertSame([1 => 5000, 2 => 2000], $ctx['stock_milli'][$item['stock_key']], "variant a's rows only, two rows on one lager added up");
        $this->assertSame([1 => 5000, 2 => 2000], $ctx['stock_milli'][$x], 'the item itself: every row');
    }

    public function testANamedCardTypeNeedsItsOwnAccountAndAnUnnamedOneTheAccountForOtherCards(): void
    {
        $x = $this->item('X');
        $this->sql("UPDATE shopify_shops SET default_freight_vare_id = $1 WHERE shop_key = 'hml'", [$x]);
        $this->sql("UPDATE grupper SET box5 = $1, box6 = $2 WHERE art = 'POS' AND kodenr = '1' AND fiscal_year = $3", ["Kort\tGavekort", "\t58050", self::$year['kodenr']]);
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year, box6) VALUES ('SD743 POS 2', '2', 'POS', $1, '58050')", [self::$year['kodenr']]);

        [$named] = $this->steps(['validate', 'hml', self::order([self::line('SD743X')])]);
        $this->sql("UPDATE shopify_payment_map SET betalingstype = 'MobilePay'");
        [$other] = $this->steps(['validate', 'hml', self::order([self::line('SD743X')])]);

        $this->assertSame(['config.pos'], array_column($named['errors'], 'kind'), "Kort is named with an empty account: bogfor_nu() would post to it");
        $this->assertTrue($other['ok'], 'MobilePay is not named, so the account for other cards (58050) takes it');
    }

    public function testThePreflightRefusesWhatLeveringWouldExitOn(): void
    {
        $x = $this->item('X');
        $serial = $this->item('SN', ['serienr' => 'on']);
        $order = function (?string $levdate, array $lines, int $status = 1): int {
            $id = (int)$this->sql("INSERT INTO ordrer (ordrenr, art, status, levdate, firmanavn) VALUES (999743, 'DO', $1, $2, 'SD743') RETURNING id", [$status, $levdate])[0]['id'];
            foreach ($lines as $i => [$vareId, $varenr, $antal, $serienr]) {
                $this->sql('INSERT INTO ordrelinjer (ordre_id, posnr, vare_id, varenr, antal, serienr) VALUES ($1, $2, $3, $4, $5, $6)', [$id, $i + 1, $vareId, $varenr, $antal, $serienr]);
            }
            return $id;
        };
        $noAccount = $this->item('NA', ['gruppe' => 9744]);
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year, box3, box4, box9) VALUES ('SD743 Batch', '9749', 'VG', $1, '1000', '1000', 'on')",
            [(string)self::$year['kodenr']]);
        $batch = $this->item('BT', ['gruppe' => 9749]);
        $other = $this->item('OTHER');
        $otherVariant = (int)$this->sql("INSERT INTO variant_varer (vare_id, variant_stregkode) VALUES ($1, 'SD743-OTHER') RETURNING id", [$other])[0]['id'];
        $ok = $order(date('Y-m-d'), [[$x, 'SD743X', 2, '']]);
        $early = $order(date('Y-m-d', strtotime('-1 day')), [[$x, 'SD743X', 2, '']]);
        $this->sql('UPDATE ordrer SET ordredate = CURRENT_DATE WHERE id = $1', [$early]);
        $tooMuch = $order(date('Y-m-d'), [[$x, 'SD743X', 2, '']]);
        $this->sql('UPDATE ordrelinjer SET leveres = 2 WHERE ordre_id = $1', [$tooMuch]);
        $this->sql("INSERT INTO batch_salg (ordre_id, linje_id, vare_id, antal) SELECT ordre_id, id, vare_id, 1 FROM ordrelinjer WHERE ordre_id = $1", [$tooMuch]);
        $cases = [
            $order(null, [[$x, 'SD743X', 2, '']]),
            $order(date('Y-m-d'), [[$x, 'SD743X', 2, '']], 3),
            $order(date('Y-m-d'), [[$serial, 'SD743SN', 1, 'on']]),
            $order(date('Y-m-d'), [[$x, 'SD743X', -1, '']]),
            $order(date('Y-m-d'), [[0, 'GHOST', 1, '']]),
            $order(date('Y-m-d'), [[$noAccount, 'SD743NA', 1, '']]),
            $early,
            $tooMuch,
            $order(date('Y-m-d'), [[$x, 'SD743X', 1, '']]),
            $order(date('Y-m-d'), [[$batch, 'SD743BT', 1, '']]),
        ];
        $this->sql('UPDATE ordrelinjer SET variant_id = $1 WHERE ordre_id = $2', [(string)$otherVariant, $cases[count($cases) - 2]]);
        $needles = ['no delivery date', 'already posted', 'serial numbers', 'a return', 'no item with an item group',
            'without a purchase and sales account', 'before its order date', 'delivered and 2.000 to deliver of 2.000', 'not a variant of its item', 'batch-controlled'];

        $results = $this->steps(['preflight', $ok], ...array_map(fn($id) => ['preflight', $id], $cases));

        $this->assertSame('ok', $results[0]);
        $this->assertSame(array_fill(0, count($cases), 'ShopifyPreflightError'), array_column(array_slice($results, 1), 'exception'));
        $this->assertSame($needles, array_map(fn($r, $needle) => str_contains($r['message'], $needle) ? $needle : $r['message'], array_slice($results, 1), $needles));
        $this->sql('DELETE FROM batch_salg WHERE ordre_id = $1', [$tooMuch]);
        $this->sql("DELETE FROM ordrer WHERE firmanavn = 'SD743'");
    }

    public function testTheLegacyGlobalsAreLentForTheCallAndGivenBack(): void
    {
        $results = $this->steps(['legacy', ['momssats' => 25], 'momssats'], ['legacy', ['webservice' => false], 'webservice']);

        $this->assertSame(['during' => 25, 'after' => '(unset)'], $results[0], 'a global that was not set is unset again');
        $this->assertSame(['during' => false, 'after' => true], $results[1], 'the bootstrap sets $webservice, and it gets its value back');
        $results = $this->steps(['legacy', [], 'lev_nr']);
        $this->assertSame(['during' => null, 'after' => '(unset)'], $results[0]);
    }
}
