<?php
// tests/characterization/shopify/OpretOrdrelinjeFixedPriceTest.test.php
//
// opret_ordrelinje() with $fixed_price (SD-789 core edit 3), in each of its three copies: the price
// and discount are stored as given, the way the Shopify intake writes a line at the paid price.
// OpretOrdrelinjeDefaultTest pins the same item setups without it.
//
// History:
// 20261102 CL/SS SD-789: created.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/support/OrderLineSteps.php';

final class OpretOrdrelinjeFixedPriceTest extends CharacterizationTestCase
{
    use OrderLineSteps;

    private const FIXED = 21;

    private static ?array $div3 = null;
    private static array $b2b;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$div3 = self::one("SELECT box11 FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        self::rows("UPDATE grupper SET box11 = '' WHERE art = 'DIV' AND kodenr = '3'");
        self::$debtor = self::$fx->debtor();
        // a B2B customer group with a 10 % discount on every item group
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year, box8)
            SELECT 'SD789 Fast', '94', 'DG', $1, 'on'
            WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art = 'DG' AND kodenr = '94' AND fiscal_year = $1)", [self::$regnaar]);
        self::rows("DELETE FROM rabat WHERE debitor = 94");
        self::rows("INSERT INTO rabat (rabat, debitorart, debitor, vareart, vare, rabatart)
            SELECT 10, 'DG', 94, 'VG', kodenr::int, '%' FROM grupper WHERE art = 'VG' AND fiscal_year = $1", [self::$regnaar]);
        self::$b2b = self::$fx->debtor();
        self::rows("UPDATE adresser SET gruppe = 94 WHERE id = $1", [self::$b2b['id']]);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            if (self::$div3 !== null) {
                self::rows("UPDATE grupper SET box11 = $1 WHERE art = 'DIV' AND kodenr = '3'", [self::$div3['box11']]);
            }
            self::rows("DELETE FROM rabat WHERE debitor = 94");
            self::rows("DELETE FROM grupper WHERE art = 'DG' AND beskrivelse = 'SD789 Fast'");
        }
        parent::tearDownAfterClass();
    }

    /** An item with an offer today, a tier price and a percent special in its period. */
    private static function itemWithEveryPrice(): array
    {
        $item = self::$fx->item(['salgspris' => 200, 'kostpris' => 80]);
        self::rows("UPDATE varer SET tier_price = 170, special_price = 15, specialtype = 'percent',
            special_from_date = CURRENT_DATE - 1, special_to_date = CURRENT_DATE + 1,
            special_from_time = '00:00:00', special_to_time = '23:59:59' WHERE id = $1", [$item['id']]);
        self::rows("INSERT INTO varetilbud (vare_id, ugedag, startdag, slutdag, starttid, sluttid, salgspris, kostpris)
            SELECT $1, d, 0, 9999999999, '00:00:00', '23:59:59', 99, 10 FROM generate_series(1, 7) d", [$item['id']]);
        return $item;
    }

    #[DataProvider('copies')]
    public function testAFixedPriceAndDiscountAreStoredAsGiven(string $copy): void
    {
        $item = self::itemWithEveryPrice();
        $plain = self::order(self::$b2b);
        $discounted = self::order(self::$b2b);

        $this->runSteps([
            self::line($copy, $plain, $item, 150, [self::FIXED => true]),
            self::line($copy, $discounted, $item, 150, [6 => 5, self::FIXED => true]),
        ]);

        $line = self::lines($plain)[0];
        self::assertEqualsWithDelta(150.0, (float)$line['pris'], 0.0001);
        self::assertEqualsWithDelta(0.0, (float)$line['rabat'], 0.0001, 'neither the percent special nor the customer discount');
        self::assertEqualsWithDelta(80.0, (float)$line['kostpris'], 0.0001);
        self::assertEqualsWithDelta(5.0, (float)self::lines($discounted)[0]['rabat'], 0.0001);
    }

    #[DataProvider('copies')]
    public function testAFixedZeroPriceStaysZero(string $copy): void
    {
        $item = self::itemWithEveryPrice();
        $zero = self::order(self::$b2b);
        $empty = self::order(self::$b2b);

        $this->runSteps([
            self::line($copy, $zero, $item, 0, [self::FIXED => true]),
            self::line($copy, $empty, $item, '', [self::FIXED => true]),
        ]);

        foreach ([$zero, $empty] as $order) {
            $line = self::lines($order)[0];
            self::assertEqualsWithDelta(0.0, (float)$line['pris'], 0.0001, 'no offer, special, tier or list price');
            self::assertEqualsWithDelta(80.0, (float)$line['kostpris'], 0.0001, "the item's cost price, not the offer's");
            self::assertEqualsWithDelta(0.0, (float)$line['rabat'], 0.0001);
        }
    }

    #[DataProvider('copies')]
    public function testAFixedPriceGetsNoQuantityDiscountAndLeavesTheOtherLinesTheirs(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        self::rows("UPDATE varer SET m_antal = '3', m_rabat = '10', m_type = 'percent' WHERE id = $1", [$item['id']]);
        $order = self::order();

        $this->runSteps([
            self::line($copy, $order, $item, '', [3 => 3]),
            self::line($copy, $order, $item, 200, [3 => 3, self::FIXED => true]),
        ]);

        [$default, $fixed] = self::lines($order);
        self::assertEqualsWithDelta(20.0, (float)$default['m_rabat'], 0.0001, 'the default line keeps its quantity discount');
        self::assertEqualsWithDelta(0.0, (float)$fixed['m_rabat'], 0.0001);
    }

    #[DataProvider('copies')]
    public function testAFixedVatInclusivePriceIsStillStoredNet(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        $order = self::order();

        $this->runSteps([self::line($copy, $order, $item, 125, [12 => 'on', self::FIXED => true])]);

        [$line] = self::lines($order);
        self::assertEqualsWithDelta(100.0, (float)$line['pris'], 0.0001);
        self::assertEqualsWithDelta(25.0, (float)$line['momssats'], 0.0001);
    }

    /** The copies' callers never merge (art D, no line id), but the branch is edited in all three. */
    #[DataProvider('copies')]
    public function testAFixedPricePosLineMergedIntoAnotherGetsNoQuantityDiscount(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        self::rows("UPDATE varer SET m_antal = '3', m_rabat = '10', m_type = 'percent' WHERE id = $1", [$item['id']]);
        $order = self::order(null, ['art' => 'PO']);
        $po = self::line($copy, $order, $item, 200, [3 => 2, 8 => 'PO', self::FIXED => true]);

        $this->runSteps([$po, $po]);

        $lines = self::lines($order);
        self::assertCount(1, $lines);
        self::assertSame('4', (string)(float)$lines[0]['antal']);
        self::assertEqualsWithDelta(0.0, (float)$lines[0]['m_rabat'], 0.0001);
    }
}
