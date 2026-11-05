<?php
// tests/shopify/PricesSyncTest.php
//
// Prices on a throwaway tenant (SD-802). Characterization first: the price POS charges (find_pris()
// and opret_ordrelinje(), run as pos_ordre.php runs them, on the live clock) next to
// shopify_price_resolve() on the same item and second: window boundaries, the edge days, a percent
// special, a special without its type, and weekday offers (varetilbud), alone, overlapping each
// other and overlapping a special. Then the sync against the shared fake store: every shop gets
// price, compareAt and saldi.retail_price; nothing is sent again while unchanged; an offer starts
// and ends with the next quarter-hour worker pass in all shops (K08); a refusal leaves the state
// as it was and goes on the error list; a call that fails keeps what the other confirmed; a
// relinked variant gets everything again; running out of time yields with the cursor. Skips
// without ShopifyEnv.
//
// History:
// 20261103 CL/SS SD-802: created.
// 20261104 CL/SS SD-802 review: POS's total path with odd-øre percent cases, find_pris()'s VAT (box7 NULL).
//                A relink during a call; the worker's wiring moved to PricesTest.
// 20261105 CL/SS SD-809: a set in a group without box8 gets its price in every shop.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class PricesSyncTest extends TestCase
{
    private const SHOPS = ['hml', 'hms', 'di'];

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
            self::$db = ShopifyEnv::testDb('_p');
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
        if (self::$db !== '') {
            ShopifyEnv::removeTempDir(self::$db);
        }
        ShopifyEnv::unregisterTenants();
        ShopifyEnv::dropOwnedTenants();
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $this->conn = ShopifyEnv::connect(self::$db);
        $this->state = tempnam(sys_get_temp_dir(), 'sd802_');
        unlink($this->state);
        foreach (['shopify_price_state', 'shopify_product_links', 'shopify_jobs', 'shopify_errors', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM varetilbud WHERE vare_id IN (SELECT id FROM varer WHERE varenr LIKE 'SD802-%')");
        $this->sql("DELETE FROM styklister WHERE indgaar_i IN (SELECT id FROM varer WHERE varenr LIKE 'SD802-%')");
        $this->sql("DELETE FROM varer WHERE varenr LIKE 'SD802-%'");
        $this->sql("DELETE FROM grupper WHERE art = 'RA' OR (art = 'VG' AND kodenr IN (902, 904, 905, 906)) OR (art = 'SM' AND kodenr = 78)");
        $this->sql("DELETE FROM kontoplan WHERE kontonr = 9002");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box4, box7, box8, fiscal_year) VALUES ('Havemøbler', 902, 'VG', '9002', '', 'on', 1),
            ('Parasoller', 904, 'VG', '9002', NULL, 'on', 1), ('Momsfri', 905, 'VG', '9002', 'on', 'on', 1), ('Sæt', 906, 'VG', '9002', '', '', 1)");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, fiscal_year) VALUES ('Salgsmoms', 78, 'SM', '9003', '25', 1)");
        $this->sql("INSERT INTO kontoplan (kontonr, beskrivelse, moms, regnskabsaar) VALUES (9002, 'Salg', 'S78', 1)");
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, products_enabled, prices_enabled) VALUES
            ('hml', 'hml-fake.myshopify.com', true, true, true), ('hms', 'hms-fake.myshopify.com', false, true, true),
            ('di', 'di-fake.myshopify.com', false, true, true), ('off', 'off-fake.myshopify.com', false, true, false)");
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

    /** Net 999.20 (1249.00 gross), Vejl.pris 1999.00. */
    private function item(string $varenr, array $fields = []): int
    {
        $fields += ['beskrivelse' => "Havestol $varenr", 'salgspris' => 999.2, 'kostpris' => 500, 'retail_price' => 1999, 'gruppe' => 902, 'lukket' => ''];
        $cols = implode(', ', array_keys($fields));
        $marks = implode(', ', array_map(fn(int $i): string => '$' . ($i + 2), array_keys(array_values($fields))));
        return (int)$this->sql("INSERT INTO varer (varenr, $cols) VALUES ($1, $marks) RETURNING id", array_merge(["SD802-$varenr"], array_values($fields)))[0]['id'];
    }

    /** Runs steps in a child against the fake store kept in $this->state; a step that throws fails the test. */
    private function steps(array ...$steps): array
    {
        $result = $this->runSteps($steps);
        foreach ($result['results'] as $r) {
            $this->assertArrayNotHasKey('exception', is_array($r) ? $r : [], json_encode($r));
        }
        return $result;
    }

    private function runSteps(array $steps): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_prices.php', [self::$db, $this->state, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertIsArray($run['result'], $run['stdout'] . $run['stderr']);
        return $run['result'];
    }

    /** The item seeded and linked in every shop of self::SHOPS. */
    private function linked(int $id): array
    {
        return array_map(fn(string $shop): array => ['seed', $shop, $id], self::SHOPS);
    }

    private function state(int $id): array
    {
        return array_column($this->sql('SELECT s.shop_key, p.price, p.compare_at, p.retail_price FROM shopify_price_state p JOIN shopify_shops s ON s.id = p.shop_id
            WHERE p.vare_id = $1 ORDER BY s.shop_key', [$id]), null, 'shop_key');
    }

    // ---- characterization: POS against the resolver ----------------------------------------

    public static function posCases(): array
    {
        $special = ['specialtype' => 'price', 'special_price' => 799.2];
        $percent = ['specialtype' => 'percent', 'special_price' => 15, 'special_from_date' => ['day', -1], 'special_from_time' => '00:00:00',
            'special_to_date' => ['day', 1], 'special_to_time' => '23:59:59'];
        return [
            'no offer' => [[], [], '1249.00'],
            'price special starting this second' => [$special + ['special_from_date' => ['day', 0], 'special_from_time' => ['time', 0],
                'special_to_date' => ['day', 1], 'special_to_time' => '23:59:59'], [], '999.00'],
            'price special starting next second' => [$special + ['special_from_date' => ['day', 0], 'special_from_time' => ['time', 1],
                'special_to_date' => ['day', 1], 'special_to_time' => '23:59:59'], [], '1249.00'],
            'price special ending this second' => [$special + ['special_from_date' => ['day', -1], 'special_from_time' => '00:00:00',
                'special_to_date' => ['day', 0], 'special_to_time' => ['time', 0]], [], '999.00'],
            'price special ended a second ago' => [$special + ['special_from_date' => ['day', -1], 'special_from_time' => '00:00:00',
                'special_to_date' => ['day', 0], 'special_to_time' => ['time', -1]], [], '1249.00'],
            'middle day, end time passed (POS keeps it)' => [$special + ['special_from_date' => ['day', -1], 'special_from_time' => '00:00:00',
                'special_to_date' => ['day', 1], 'special_to_time' => ['time', -1]], [], '1249.00'],
            'middle day, start time to come (POS starts at midnight)' => [$special + ['special_from_date' => ['day', -1], 'special_from_time' => ['time', 1],
                'special_to_date' => ['day', 1], 'special_to_time' => '00:00:00'], [], '999.00'],
            'special without its type' => [['specialtype' => ''] + $special + ['special_from_date' => ['day', -1], 'special_from_time' => '00:00:00',
                'special_to_date' => ['day', 1], 'special_to_time' => '23:59:59'], [], '1249.00'],
            'percent special' => [['specialtype' => 'percent', 'special_price' => 15, 'special_from_date' => ['day', -1], 'special_from_time' => '00:00:00',
                'special_to_date' => ['day', 1], 'special_to_time' => ['time', -1]], [], '1061.65'],
            'percent special ended a second ago' => [['specialtype' => 'percent', 'special_price' => 15, 'special_from_date' => ['day', -1], 'special_from_time' => '00:00:00',
                'special_to_date' => ['day', 0], 'special_to_time' => ['time', -1]], [], '1249.00'],
            'weekday offer' => [[], [500], '1249.00'],
            'two weekday offers' => [[], [500, 400], '1249.00'],
            'weekday offer and a price special' => [$special + ['special_from_date' => ['day', -1], 'special_from_time' => '00:00:00',
                'special_to_date' => ['day', 1], 'special_to_time' => '23:59:59'], [500], '999.00'],
            'weekday offer and a percent special' => [['specialtype' => 'percent', 'special_price' => 15, 'special_from_date' => ['day', -1], 'special_from_time' => '00:00:00',
                'special_to_date' => ['day', 1], 'special_to_time' => '23:59:59'], [500], '1061.65'],
            // the POS total rounds a discounted line to 3 decimals, then to 2
            'percent special, odd øre (12.37 less 15 %)' => [['salgspris' => 9.896] + $percent, [], '10.52', '12.37'],
            'percent special, odd øre (1.17 less 15 %)' => [['salgspris' => 0.936] + $percent, [], '1.00', '1.17'],
            'percent special, odd øre (999.37 less 15 %)' => [['salgspris' => 799.496] + $percent, [], '849.47', '999.37'],
            'a VAT group with box7 NULL is not VAT-free' => [['gruppe' => 904], [], '1249.00'],
            'a VAT-free group' => [['gruppe' => 905], [], '999.20'],
        ];
    }

    #[DataProvider('posCases')]
    public function testTheResolverChargesWhatPosCharges(array $fields, array $offers, string $expected, ?string $normal = null): void
    {
        $id = $this->item('C1');
        $r = $this->steps(['legacy', $id, $fields, $offers])['results'][0];

        $this->assertNotNull($r['pos'], 'POS made a line: ' . json_encode($r));
        $this->assertSame($expected, $r['pos'], 'POS, pinned: ' . json_encode($r));
        $this->assertSame($r['pos'], $r['resolver']['price'], 'the resolver agrees with POS: ' . json_encode($r));
        $normal ??= $expected === '1249.00' || $expected === '999.20' ? null : '1249.00';
        $this->assertSame($normal, $r['resolver']['compare_at']);
    }

    // ---- the sync ---------------------------------------------------------------------------

    public function testEveryShopGetsPriceCompareAtAndRetailOnceAndThenNothing(): void
    {
        $id = $this->item('S1');
        $r = $this->steps(...array_merge($this->linked($id), [['seed', 'off', $id], ['sync', 'hml'], ['sync', 'hms'], ['sync', 'di'], ['sync', 'off'], ['sync', 'hml']]));
        array_splice($r['results'], 3, 1);

        foreach (self::SHOPS as $shop) {
            $this->assertSame(['price' => '1249.00', 'compareAtPrice' => null], $r['variants'][$shop]['SD802-S1'], $shop);
            $this->assertSame(['value' => '1999.00', 'type' => 'number_decimal'], $r['metafields'][$shop]['SD802-S1']['saldi.retail_price'], $shop);
        }
        $this->assertSame(['sent' => 1, 'unchanged' => 0, 'rejected' => 0], array_intersect_key($r['results'][3], array_flip(['sent', 'unchanged', 'rejected'])));
        $this->assertSame(0, $r['results'][6]['sent'] + $r['results'][6]['unchanged'], 'a shop with prices off gets nothing');
        $this->assertNotContains('off', array_column($r['calls'], 'shop'));
        $this->assertSame(['sent' => 0, 'unchanged' => 1], array_intersect_key($r['results'][7], array_flip(['sent', 'unchanged'])));
        $this->assertSame(['pricesUpdate', 'catalogueMetafields', 'pricesUpdate', 'catalogueMetafields', 'pricesUpdate', 'catalogueMetafields'],
            array_column($r['calls'], 'operation'), 'one product update and one metafield per shop, nothing on the unchanged run');
        $this->assertSame(['di', 'hml', 'hms'], array_keys($this->state($id)), 'state per shop, none for the shop with prices off');
        $this->assertSame(['shop_key' => 'hml', 'price' => '1249.000', 'compare_at' => null, 'retail_price' => '1999.000'], $this->state($id)['hml']);
    }

    public function testASetInAGroupWithoutBox8GetsItsPriceInEveryShop(): void
    {
        $part = $this->item('P1');
        $set = $this->item('SET1', ['gruppe' => 906, 'samlevare' => 'on']);
        $this->sql('INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $2, 2, 1)', [$part, $set]);
        $r = $this->steps(...array_merge($this->linked($set), [['sync', 'hml'], ['sync', 'hms'], ['sync', 'di']]));

        foreach (self::SHOPS as $shop) {
            $this->assertSame(['price' => '1249.00', 'compareAtPrice' => null], $r['variants'][$shop]['SD802-SET1'], $shop);
        }
        $this->assertSame(['di', 'hml', 'hms'], array_keys($this->state($set)), 'the set has its price state in every shop');
    }

    public function testOnlyTheChangedValueIsSentAgain(): void
    {
        $id = $this->item('S2');
        $this->steps(...array_merge($this->linked($id), [['sync', 'hml']]));
        $this->sql('UPDATE varer SET retail_price = 2199 WHERE id = $1', [$id]);
        $r = $this->steps(['sync', 'hml']);
        $this->sql('UPDATE varer SET salgspris = 799.2 WHERE id = $1', [$id]);
        $r2 = $this->steps(['sync', 'hml']);

        $this->assertSame(['catalogueMetafields'], array_column($r['calls'], 'operation'));
        $this->assertSame(['pricesUpdate'], array_column($r2['calls'], 'operation'));
        $this->assertSame(['price' => '999.00', 'compareAtPrice' => null], $r2['variants']['hml']['SD802-S2']);

        // during an offer a new normal price moves only compareAt
        $this->sql("UPDATE varer SET salgspris = 999.2, specialtype = 'price', special_price = 799.2, special_from_date = '2026-11-10',
            special_to_date = '2026-11-12', special_from_time = '00:00:00', special_to_time = '23:59:59' WHERE id = $1", [$id]);
        $noon = (new DateTimeImmutable('2026-11-11 12:00:00', new DateTimeZone('Europe/Copenhagen')))->format(DATE_ATOM);
        $this->steps(['sync', 'hml', $noon]);
        $this->sql('UPDATE varer SET salgspris = 1199.2 WHERE id = $1', [$id]);
        $r3 = $this->steps(['sync', 'hml', $noon]);
        $this->assertSame(['pricesUpdate'], array_column($r3['calls'], 'operation'));
        $this->assertSame(['price' => '999.00', 'compareAtPrice' => '1499.00'], $r3['variants']['hml']['SD802-S2']);
    }

    public function testAnOfferStartsAndEndsInAllShopsWithTheNextQuarterHourPass(): void
    {
        $id = $this->item('K08', ['specialtype' => 'price', 'special_price' => 799.2, 'special_from_date' => '2026-11-10', 'special_from_time' => '10:07:00',
            'special_to_date' => '2026-11-12', 'special_to_time' => '18:00:00']);
        $this->steps(...$this->linked($id));
        $at = fn(string $local): string => (new DateTimeImmutable($local, new DateTimeZone('Europe/Copenhagen')))->format(DATE_ATOM);

        $before = $this->steps(['worker', $at('2026-11-10 10:00:30')]);
        $during = $this->steps(['worker', $at('2026-11-10 10:15:30')]);
        $still = $this->steps(['worker', $at('2026-11-12 18:00:00')]);
        $after = $this->steps(['worker', $at('2026-11-12 18:15:30')]);

        foreach (self::SHOPS as $shop) {
            $this->assertSame(['price' => '1249.00', 'compareAtPrice' => null], $before['variants'][$shop]['SD802-K08'], "$shop before 10:07");
            $this->assertSame(['price' => '999.00', 'compareAtPrice' => '1249.00'], $during['variants'][$shop]['SD802-K08'], "$shop: the 10:15 pass starts it, the before-price shown");
            $this->assertSame(['price' => '1249.00', 'compareAtPrice' => null], $after['variants'][$shop]['SD802-K08'], "$shop: the 18:15 pass ends it, compareAt cleared");
        }
        $this->assertSame(3, $before['results'][0]['done'], 'one price.window job per shop with prices on');
        $this->assertSame(['price.window'], array_values(array_unique(array_column($before['results'][0]['jobs'], 'type'))));
        $this->assertSame([], $still['calls'], 'nothing changed at 18:00:00');
        $this->assertSame(3, count($after['calls']), 'one product update per shop; the retail price had not changed');
    }

    public function testARefusalLeavesTheStateAndIsReportedWhileTheOtherItemsGoOn(): void
    {
        $bad = $this->item('R1');
        $good = $this->item('R2');
        $this->steps(['seed', 'hml', $bad], ['seed', 'hml', $good]);
        $r = $this->steps(['fail', 'productVariantsBulkUpdate', 'user_error'], ['sync', 'hml']);

        $this->assertSame(['sent' => 1, 'unchanged' => 0, 'rejected' => 1], array_intersect_key($r['results'][1], array_flip(['sent', 'unchanged', 'rejected'])));
        $this->assertSame(['price' => '100.00', 'compareAtPrice' => null], $r['variants']['hml']['SD802-R1'], 'nothing changed in Shopify (the seeded price)');
        $state = $this->sql('SELECT price, compare_at, retail_price FROM shopify_price_state WHERE vare_id = $1', [$bad]);
        $this->assertSame([], $state, 'nothing confirmed, nothing stored: the item is tried again whole');
        $error = $this->sql("SELECT kind, ref, severity, message FROM shopify_errors WHERE resolved_at IS NULL");
        $this->assertSame([['prices.rejected', "vare:$bad", 'warning']], array_map(fn(array $e): array => [$e['kind'], $e['ref'], $e['severity']], $error));
        $this->assertStringContainsString('Injected failure', $error[0]['message']);

        $again = $this->steps(['sync', 'hml']);
        $this->assertSame(['pricesUpdate', 'catalogueMetafields'], array_column($again['calls'], 'operation'), 'the next window sends the item again');
        $this->assertSame([], $this->sql('SELECT id FROM shopify_errors WHERE resolved_at IS NULL'), 'and resolves the error');
    }

    public function testANetworkFailureKeepsWhatShopifyConfirmedAndFailsTheRun(): void
    {
        $id = $this->item('N1');
        $this->steps(['seed', 'hml', $id]);
        $r = $this->runSteps([['fail', 'metafieldsSet', 'http_500x9'], ['sync', 'hml']]);

        $this->assertSame('ShopifyCatalogueError', $r['results'][1]['exception'] ?? null, json_encode($r['results'][1]));
        $this->assertTrue($r['results'][1]['retryable']);
        $this->assertSame([['price' => '1249.000', 'retail_price' => null]], $this->sql('SELECT price, retail_price FROM shopify_price_state WHERE vare_id = $1', [$id]));
        $this->assertSame([], $this->sql('SELECT id FROM shopify_errors'), 'the queue retries and alarms, not the error list');
    }

    public function testARelinkedVariantGetsEverythingAgain(): void
    {
        $id = $this->item('L1');
        $this->steps(['seed', 'hml', $id], ['sync', 'hml']);
        // what the catalogue's upsert of the link does (in the worker's zone, as acknowledged_at)
        $this->sql("UPDATE shopify_product_links l SET updated_at = s.acknowledged_at + interval '1 millisecond' FROM shopify_price_state s WHERE s.vare_id = l.vare_id AND l.vare_id = $1", [$id]);
        $r = $this->steps(['sync', 'hml']);

        $this->assertSame(['pricesUpdate', 'catalogueMetafields'], array_column($r['calls'], 'operation'));

        // relinked again, and only the price gets through: the old retail state is not trusted for the new variant
        $this->sql("UPDATE shopify_product_links l SET updated_at = s.acknowledged_at + interval '1 millisecond' FROM shopify_price_state s WHERE s.vare_id = l.vare_id AND l.vare_id = $1", [$id]);
        $this->runSteps([['fail', 'metafieldsSet', 'http_500x9'], ['sync', 'hml']]);
        $this->assertSame([['price' => '1249.000', 'retail_price' => null]], $this->sql('SELECT price, retail_price FROM shopify_price_state WHERE vare_id = $1', [$id]));
        $this->assertSame(['catalogueMetafields'], array_column($this->steps(['sync', 'hml'])['calls'], 'operation'));
    }

    public function testAnUnlistedOrClosedItemGetsNothing(): void
    {
        $unlisted = $this->item('U1');
        $closed = $this->item('U2');
        $this->steps(['seed', 'hml', $unlisted], ['seed', 'hml', $closed]);
        $this->sql('UPDATE shopify_product_links SET listed = false WHERE vare_id = $1', [$unlisted]);
        $this->sql("UPDATE varer SET lukket = 'on' WHERE id = $1", [$closed]);
        $r = $this->steps(['sync', 'hml']);

        $this->assertSame([], $r['calls']);
        $this->assertSame(0, $r['results'][0]['unchanged']);
    }

    public function testRunningOutOfTimeYieldsWithTheCursor(): void
    {
        $first = $this->item('T1');
        $second = $this->item('T2');
        $this->steps(['seed', 'hml', $first], ['seed', 'hml', $second]);
        $r = $this->steps(['sync', 'hml', null, 0, 4], ['sync', 'hml', null, $first]);

        $this->assertSame(['sent' => 0, 'after' => 0, 'done' => false], array_intersect_key($r['results'][0], array_flip(['sent', 'after', 'done'])));
        $this->assertSame(['sent' => 1, 'after' => $second, 'done' => true], array_intersect_key($r['results'][1], array_flip(['sent', 'after', 'done'])), 'from the cursor on');
    }

    public function testTheSyncAddsTheVatPosAdds(): void
    {
        $nullBox7 = $this->item('V1', ['gruppe' => 904]);
        $free = $this->item('V2', ['gruppe' => 905]);
        $r = $this->steps(['seed', 'hml', $nullBox7], ['seed', 'hml', $free], ['sync', 'hml']);

        $this->assertSame('1249.00', $r['variants']['hml']['SD802-V1']['price'], 'box7 NULL is not VAT-free, as in find_pris()');
        $this->assertSame('999.20', $r['variants']['hml']['SD802-V2']['price']);
    }

    public function testALinkChangedWhileACallIsInFlightIsSentAgain(): void
    {
        $id = $this->item('W1');
        $this->steps(['seed', 'hml', $id], ['sync', 'hml']);
        $this->sql("UPDATE varer SET salgspris = 799.2 WHERE id = $1", [$id]);
        $this->steps(['sync_relink', 'hml', $id]);
        $r = $this->steps(['sync', 'hml']);

        $this->assertSame(['pricesUpdate', 'catalogueMetafields'], array_column($r['calls'], 'operation'),
            'the state is dated when its row was read, so the relink is newer');
    }
}
