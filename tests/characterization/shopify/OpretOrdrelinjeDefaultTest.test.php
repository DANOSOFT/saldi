<?php
// tests/characterization/shopify/OpretOrdrelinjeDefaultTest.test.php
//
// Pins the price an order line gets from opret_ordrelinje() today, in each of its three copies
// (includes/ordrefunc.php, mysale/api.php, rental/rental.php), before SD-789 adds $fixed_price.
// Every caller keeps this default, so these tests must stay green after the edit.
//
// History:
// 20261102 CL/SS SD-789: created.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/support/OrderLineSteps.php';

final class OpretOrdrelinjeDefaultTest extends CharacterizationTestCase
{
    use OrderLineSteps;

    private static ?array $div3 = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$div3 = self::one("SELECT box11 FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        // the negative-stock warning alerts on a DO order; not what these tests are about
        self::rows("UPDATE grupper SET box11 = '' WHERE art = 'DIV' AND kodenr = '3'");
        self::$debtor = self::$fx->debtor();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            if (self::$div3 !== null) {
                self::rows("UPDATE grupper SET box11 = $1 WHERE art = 'DIV' AND kodenr = '3'", [self::$div3['box11']]);
            }
            self::rows("DELETE FROM rabat WHERE debitor = 93 AND rabat = 10 AND rabatart = '%'");
            self::rows("DELETE FROM grupper WHERE art = 'DG' AND beskrivelse IN ('SD789 B2B', 'SD789 Rabat')");
        }
        parent::tearDownAfterClass();
    }

    #[DataProvider('copies')]
    public function testAGivenPriceIsStoredAsGiven(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200, 'kostpris' => 80]);
        $order = self::order();

        $this->runSteps([self::line($copy, $order, $item, 150)]);

        [$line] = self::lines($order);
        self::assertEqualsWithDelta(150.0, (float)$line['pris'], 0.0001);
        self::assertEqualsWithDelta(0.0, (float)$line['rabat'], 0.0001);
        self::assertEqualsWithDelta(80.0, (float)$line['kostpris'], 0.0001, 'the cost price is the item\'s');
        self::assertEqualsWithDelta(25.0, (float)$line['momssats'], 0.0001);
    }

    #[DataProvider('copies')]
    public function testNoPriceBecomesTheItemsSalesPriceAndAZeroPriceStaysZero(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200, 'kostpris' => 80]);
        $none = self::order();
        $zero = self::order();

        $this->runSteps([self::line($copy, $none, $item, ''), self::line($copy, $zero, $item, 0)]);

        self::assertEqualsWithDelta(200.0, (float)self::lines($none)[0]['pris'], 0.0001);
        self::assertEqualsWithDelta(80.0, (float)self::lines($none)[0]['kostpris'], 0.0001);
        self::assertEqualsWithDelta(0.0, (float)self::lines($zero)[0]['pris'], 0.0001, 'a 0 is a free line, not a missing price');
    }

    #[DataProvider('copies')]
    public function testAZeroPriceTakesTodaysOfferAndAGivenPriceDoesNot(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        // every weekday, so a run across midnight still finds it
        self::rows("INSERT INTO varetilbud (vare_id, ugedag, startdag, slutdag, starttid, sluttid, salgspris, kostpris)
            SELECT $1, d, 0, 9999999999, '00:00:00', '23:59:59', 99, 10 FROM generate_series(1, 7) d", [$item['id']]);
        $zero = self::order();
        $given = self::order();

        $this->runSteps([self::line($copy, $zero, $item, 0), self::line($copy, $given, $item, 150)]);

        self::assertEqualsWithDelta(99.0, (float)self::lines($zero)[0]['pris'], 0.0001);
        self::assertEqualsWithDelta(10.0, (float)self::lines($zero)[0]['kostpris'], 0.0001);
        self::assertEqualsWithDelta(150.0, (float)self::lines($given)[0]['pris'], 0.0001);
    }

    #[DataProvider('copies')]
    public function testAZeroPriceTakesTheSpecialPriceInItsPeriod(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        self::rows("UPDATE varer SET special_price = 120, campaign_cost = 30, specialtype = '',
            special_from_date = CURRENT_DATE - 1, special_to_date = CURRENT_DATE + 1,
            special_from_time = '00:00:00', special_to_time = '23:59:59' WHERE id = $1", [$item['id']]);
        $zero = self::order();
        $given = self::order();

        $this->runSteps([self::line($copy, $zero, $item, 0), self::line($copy, $given, $item, 150)]);

        self::assertEqualsWithDelta(120.0, (float)self::lines($zero)[0]['pris'], 0.0001);
        self::assertEqualsWithDelta(30.0, (float)self::lines($zero)[0]['kostpris'], 0.0001);
        self::assertEqualsWithDelta(150.0, (float)self::lines($given)[0]['pris'], 0.0001);
    }

    #[DataProvider('copies')]
    public function testAPercentSpecialInItsPeriodBecomesTheDiscountOfAGivenPrice(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        self::rows("UPDATE varer SET special_price = 15, specialtype = 'percent',
            special_from_date = CURRENT_DATE - 1, special_to_date = CURRENT_DATE + 1,
            special_from_time = '00:00:00', special_to_time = '23:59:59' WHERE id = $1", [$item['id']]);
        $order = self::order();
        $typed = self::order();

        $this->runSteps([self::line($copy, $order, $item, 150), self::line($copy, $typed, $item, 150, [6 => 5])]);

        self::assertEqualsWithDelta(150.0, (float)self::lines($order)[0]['pris'], 0.0001);
        self::assertEqualsWithDelta(15.0, (float)self::lines($order)[0]['rabat'], 0.0001);
        self::assertEqualsWithDelta(5.0, (float)self::lines($typed)[0]['rabat'], 0.0001, 'a discount passed in wins');
    }

    #[DataProvider('copies')]
    public function testAB2bCustomerGetsTheTierPriceForAZeroPrice(string $copy): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year, box8)
            SELECT 'SD789 B2B', '91', 'DG', $1, 'on'
            WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art = 'DG' AND kodenr = '91' AND fiscal_year = $1)", [self::$regnaar]);
        $b2b = self::$fx->debtor();
        self::rows("UPDATE adresser SET gruppe = 91 WHERE id = $1", [$b2b['id']]);
        $item = self::$fx->item(['salgspris' => 200]);
        self::rows("UPDATE varer SET tier_price = 170 WHERE id = $1", [$item['id']]);
        $zero = self::order($b2b);
        $given = self::order($b2b);

        $this->runSteps([self::line($copy, $zero, $item, 0), self::line($copy, $given, $item, 150)]);

        self::assertEqualsWithDelta(170.0, (float)self::lines($zero)[0]['pris'], 0.0001);
        self::assertEqualsWithDelta(150.0, (float)self::lines($given)[0]['pris'], 0.0001);
    }

    #[DataProvider('copies')]
    public function testTheCustomerDiscountComesFromTheDiscountTableUnlessOneIsPassedIn(string $copy): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year)
            SELECT 'SD789 Rabat', '93', 'DG', $1
            WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art = 'DG' AND kodenr = '93' AND fiscal_year = $1)", [self::$regnaar]);
        $customer = self::$fx->debtor();
        self::rows("UPDATE adresser SET gruppe = 93 WHERE id = $1", [$customer['id']]);
        $item = self::$fx->item(['salgspris' => 200]);
        // no DRG/DVRG groups: the debtor group and the item group are the discount groups
        self::rows("DELETE FROM rabat WHERE debitor = 93 AND vare = $1", [$item['gruppe']]);
        self::rows("INSERT INTO rabat (rabat, debitorart, debitor, vareart, vare, rabatart) VALUES (10, 'DG', 93, 'VG', $1, '%')", [$item['gruppe']]);
        $order = self::order($customer);
        $typed = self::order($customer);

        $this->runSteps([self::line($copy, $order, $item, 150), self::line($copy, $typed, $item, 150, [6 => 5])]);

        self::assertEqualsWithDelta(10.0, (float)self::lines($order)[0]['rabat'], 0.0001);
        self::assertSame('%', self::lines($order)[0]['rabatart']);
        self::assertEqualsWithDelta(150.0, (float)self::lines($order)[0]['pris'], 0.0001);
        self::assertEqualsWithDelta(5.0, (float)self::lines($typed)[0]['rabat'], 0.0001);
    }

    /** remoteBooking/api.php passes a 22nd argument (25) that the function never read; it must stay a default line. */
    #[DataProvider('copies')]
    public function testATruthy22ndArgumentIsStillADefaultLine(string $copy): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year)
            SELECT 'SD789 Rabat', '93', 'DG', $1
            WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art = 'DG' AND kodenr = '93' AND fiscal_year = $1)", [self::$regnaar]);
        $customer = self::$fx->debtor();
        self::rows("UPDATE adresser SET gruppe = 93 WHERE id = $1", [$customer['id']]);
        $item = self::$fx->item(['salgspris' => 200]);
        self::rows("DELETE FROM rabat WHERE debitor = 93 AND vare = $1", [$item['gruppe']]);
        self::rows("INSERT INTO rabat (rabat, debitorart, debitor, vareart, vare, rabatart) VALUES (10, 'DG', 93, 'VG', $1, '%')", [$item['gruppe']]);
        $order = self::order($customer);

        $this->runSteps([self::line($copy, $order, $item, 150, [21 => 25])]);

        self::assertEqualsWithDelta(10.0, (float)self::lines($order)[0]['rabat'], 0.0001, 'the customer discount, as with 21 arguments');
    }

    /**
     * For mysale/api.php and rental/rental.php this pins the m_rabat() call only: neither page
     * loads includes/ordrefunc.php, so in production that call has no function to reach.
     */
    #[DataProvider('copies')]
    public function testAQuantityDiscountIsWrittenOnlyOnALineAtTheListPrice(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        self::rows("UPDATE varer SET m_antal = '3', m_rabat = '10', m_type = 'percent' WHERE id = $1", [$item['id']]);
        $list = self::order();
        $given = self::order();

        $this->runSteps([self::line($copy, $list, $item, '', [3 => 3]), self::line($copy, $given, $item, 150, [3 => 3])]);

        self::assertEqualsWithDelta(20.0, (float)self::lines($list)[0]['m_rabat'], 0.0001, '10 % of 200 per unit');
        self::assertSame('percent', self::lines($list)[0]['rabatart']);
        self::assertEqualsWithDelta(0.0, (float)self::lines($given)[0]['m_rabat'], 0.0001,
            'm_rabat() looks for the line at the list price, so a line at another price gets none');
    }

    #[DataProvider('copies')]
    public function testAVatInclusivePriceIsStoredNet(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        $order = self::order();

        $this->runSteps([self::line($copy, $order, $item, 125, [12 => 'on'])]);

        [$line] = self::lines($order);
        self::assertEqualsWithDelta(100.0, (float)$line['pris'], 0.0001);
        self::assertEqualsWithDelta(25.0, (float)$line['momssats'], 0.0001);
    }

    #[DataProvider('copies')]
    public function testAVatInclusivePriceOfAVatFreeItemIsStoredAsGiven(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200, 'stocked' => false]);
        if (self::one("SELECT box7 FROM grupper WHERE art = 'VG' AND kodenr = $1 AND fiscal_year = $2",
            [(string)$item['gruppe'], self::$regnaar])['box7'] !== 'on') {
            self::markTestSkipped('the template has no VAT-free untracked item group');
        }
        $order = self::order();

        $this->runSteps([self::line($copy, $order, $item, 125, [12 => 'on'])]);

        [$line] = self::lines($order);
        self::assertEqualsWithDelta(125.0, (float)$line['pris'], 0.0001);
        self::assertEqualsWithDelta(0.0, (float)$line['momssats'], 0.0001);
    }

    #[DataProvider('copies')]
    public function testTheOrdersLowerVatRateCapsTheItemsAndIsTakenOffAVatInclusivePrice(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        $order = self::order(null, ['momssats' => 10]);

        $this->runSteps([self::line($copy, $order, $item, 110, [12 => 'on'])], ['momssats' => 10]);

        [$line] = self::lines($order);
        self::assertEqualsWithDelta(100.0, (float)$line['pris'], 0.0001);
        self::assertEqualsWithDelta(10.0, (float)$line['momssats'], 0.0001);
    }

    #[DataProvider('copies')]
    public function testASubstitutedPriceIsNotTakenAsVatInclusive(string $copy): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        $order = self::order();

        $this->runSteps([self::line($copy, $order, $item, '', [12 => 'on'])]);

        self::assertEqualsWithDelta(200.0, (float)self::lines($order)[0]['pris'], 0.0001, 'the list price is net already');
    }

    // ------------------------------------- an existing line (ordrefunc.php only: the copies' callers pass art D and no line id)

    public function testChangingALinesQuantityAcrossTheThresholdGivesAndTakesTheQuantityDiscount(): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        self::rows("UPDATE varer SET m_antal = '3', m_rabat = '10', m_type = 'percent' WHERE id = $1", [$item['id']]);
        $order = self::order();
        $this->runSteps([self::line('ordrefunc', $order, $item, '')]);
        $id = (int)self::lines($order)[0]['id'];

        $this->runSteps([self::line('ordrefunc', $order, $item, '', [3 => 2, 11 => (string)$id])]);
        $up = self::lines($order);
        $this->runSteps([self::line('ordrefunc', $order, $item, '', [3 => -2, 11 => (string)$id])]);
        $down = self::lines($order);

        self::assertCount(1, $up, 'the line itself changes, no new line');
        self::assertSame('3', (string)(float)$up[0]['antal']);
        self::assertEqualsWithDelta(20.0, (float)$up[0]['m_rabat'], 0.0001);
        self::assertCount(1, $down);
        self::assertSame('1', (string)(float)$down[0]['antal']);
        self::assertEqualsWithDelta(0.0, (float)$down[0]['m_rabat'], 0.0001);
    }

    public function testTwoIdenticalPosLinesMergeAndGetTheQuantityDiscountTogether(): void
    {
        $item = self::$fx->item(['salgspris' => 200]);
        self::rows("UPDATE varer SET m_antal = '3', m_rabat = '10', m_type = 'percent' WHERE id = $1", [$item['id']]);
        $order = self::order(null, ['art' => 'PO']);
        $po = self::line('ordrefunc', $order, $item, '', [3 => 2, 8 => 'PO']);

        $this->runSteps([$po, $po]);

        $lines = self::lines($order);
        self::assertCount(1, $lines);
        self::assertSame('4', (string)(float)$lines[0]['antal']);
        self::assertEqualsWithDelta(20.0, (float)$lines[0]['m_rabat'], 0.0001);
        self::assertSame('percent', $lines[0]['rabatart']);
    }
}
