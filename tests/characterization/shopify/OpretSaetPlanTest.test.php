<?php
// tests/characterization/shopify/OpretSaetPlanTest.test.php
//
// Pins opret_saet() without a lager plan, as every caller uses it today, before SD-789 adds
// $lager_plan: the lines a set becomes, the set discount, the totals, and the discount reset
// gendan_saet() does first. A null plan must behave exactly like this after the edit.
//
// History:
// 20261102 CL/SS SD-789: created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/support/OrderLineSteps.php';

final class OpretSaetPlanTest extends CharacterizationTestCase
{
    use OrderLineSteps;

    private const LAGER = 3;

    private static ?array $div3 = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$div3 = self::one("SELECT box2, box11 FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        self::rows("UPDATE grupper SET box11 = '' WHERE art = 'DIV' AND kodenr = '3'");
        self::$debtor = self::$fx->debtor();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant && self::$div3 !== null) {
            self::rows("UPDATE grupper SET box2 = $1, box11 = $2 WHERE art = 'DIV' AND kodenr = '3'", [self::$div3['box2'], self::$div3['box11']]);
        }
        parent::tearDownAfterClass();
    }

    /**
     * A set of 1 X (100) + 2 Y (50), sold alone for 180, all excl. VAT and in a 25 % group.
     *
     * @return array{set: array, x: array, y: array}
     */
    private static function set(): array
    {
        $x = self::$fx->item(['salgspris' => 100, 'kostpris' => 40]);
        $y = self::$fx->item(['salgspris' => 50, 'kostpris' => 20]);
        $set = self::$fx->item(['salgspris' => 180, 'kostpris' => 0]);
        self::rows("UPDATE varer SET samlevare = 'on' WHERE id = $1", [$set['id']]);
        self::rows("INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $3, 1, 1), ($2, $3, 2, 2)",
            [$x['id'], $y['id'], $set['id']]);
        return ['set' => $set, 'x' => $x, 'y' => $y];
    }

    public function testASetIsOneLinePerComponentThenTheSetLineAllInTheGivenLager(): void
    {
        $s = self::set();
        $order = self::order();

        $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 2, '', self::LAGER]]);

        $lines = self::lines($order);
        self::assertSame([$s['x']['id'], $s['y']['id'], $s['set']['id']], array_map(static fn($l) => (int)$l['vare_id'], $lines));
        self::assertSame(['2', '4', '1'], array_map(static fn($l) => (string)(float)$l['antal'], $lines), 'the components times the sets; the set line once');
        self::assertSame([1, 1, 1], array_map(static fn($l) => (int)$l['saet'], $lines));
        self::assertSame([self::LAGER, self::LAGER, self::LAGER], array_map(static fn($l) => (int)$l['lager'], $lines));
        [$x, $y, $setLine] = $lines;
        self::assertEqualsWithDelta(100.0, (float)$x['pris'], 0.0001, 'each component at its own price');
        self::assertEqualsWithDelta(50.0, (float)$y['pris'], 0.0001);
        self::assertEqualsWithDelta(20.0, (float)$x['rabat'], 0.0001, '400 down to 320 is 20 % off every component');
        self::assertEqualsWithDelta(20.0, (float)$y['rabat'], 0.0001);
        self::assertEqualsWithDelta(0.0, (float)$setLine['pris'], 0.0001, 'the set line carries what the discount left over');
        self::assertEqualsWithDelta(0.0, (float)$setLine['rabat'], 0.0001);
        self::assertSame('on', $setLine['samlevare']);
        self::assertSame('320|20', $setLine['lev_varenr'], 'the set price and the discount, for gendan_saet()');
        self::assertEqualsWithDelta(0.0, (float)$setLine['kostpris'], 0.0001);
        self::assertSame('', (string)$x['samlevare']);
    }

    public function testAVatExclusiveSetTotalsTheSetPriceToTheOere(): void
    {
        $s = self::set();
        $order = self::order();

        $this->runSteps([['saet', $order, $s['set']['id'], 133.33, 25, 1, '', self::LAGER]]);

        $lines = self::lines($order);
        self::assertSame(133.33, round(self::net($lines), 2));
        self::assertEqualsWithDelta(33.335, (float)$lines[0]['rabat'], 0.0001, 'the discount is kept to three decimals');
        self::assertEqualsWithDelta(133.33 - self::net([$lines[0], $lines[1]]), (float)$lines[2]['pris'], 0.001, 'what the discount leaves over sits on the set line');
    }

    public function testAVatInclusiveSetTotalsTheSetPriceToTheOere(): void
    {
        $s = self::set();
        $order = self::order();

        $this->runSteps([['saet', $order, $s['set']['id'], 199.99, 25, 1, 'on', self::LAGER]]);

        $lines = self::lines($order);
        self::assertEqualsWithDelta(100.0, (float)$lines[0]['pris'], 0.0001, 'the components are stored net');
        self::assertEqualsWithDelta(50.0, (float)$lines[1]['pris'], 0.0001);
        self::assertEqualsWithDelta(20.004, (float)$lines[0]['rabat'], 0.0001, '250 incl. VAT down to 199.99');
        self::assertSame(199.99, round(self::net($lines) * 1.25, 2));
        self::assertSame('199.99|20.004', $lines[2]['lev_varenr']);
    }

    /**
     * One component at 10000, three in the set, sold for 10000: the discount (66.667 %) leaves
     * 0.10 over, which the set line carries.
     */
    public function testTheRoundingTheDiscountLeavesIsOnTheSetLineSoTheTotalIsTheSetPrice(): void
    {
        $z = self::$fx->item(['salgspris' => 10000]);
        $set = self::$fx->item(['salgspris' => 10000, 'kostpris' => 0]);
        self::rows("UPDATE varer SET samlevare = 'on' WHERE id = $1", [$set['id']]);
        self::rows("INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $2, 3, 1)", [$z['id'], $set['id']]);
        $excl = self::order();
        $incl = self::order();

        $this->runSteps([['saet', $excl, $set['id'], 10000, 25, 1, '', self::LAGER], ['saet', $incl, $set['id'], 12500, 25, 1, 'on', self::LAGER]]);

        $lines = self::lines($excl);
        self::assertEqualsWithDelta(66.667, (float)$lines[0]['rabat'], 0.0001);
        self::assertEqualsWithDelta(0.1, (float)$lines[1]['pris'], 0.0001);
        self::assertSame(10000.0, round(self::net($lines), 2));
        $lines = self::lines($incl);
        self::assertEqualsWithDelta(66.667, (float)$lines[0]['rabat'], 0.0001);
        self::assertSame(12500.0, round(self::net($lines) * 1.25, 2));
    }

    public function testASetPriceOfZeroTakesTheSetItemsPriceOnceForAllTheSets(): void
    {
        $s = self::set();
        $order = self::order();

        $this->runSteps([['saet', $order, $s['set']['id'], 0, 25, 2, '', self::LAGER]]);

        $lines = self::lines($order);
        self::assertSame('180.000|55', $lines[2]['lev_varenr'], 'two sets for the price of one: 400 down to 180, as varer stores it');
        self::assertSame(180.0, round(self::net($lines), 2));
    }

    public function testASetPriceOfZeroWithVatIncludedAddsTheOrdersVatToTheSetItemsPrice(): void
    {
        $s = self::set();
        $order = self::order();

        $this->runSteps([['saet', $order, $s['set']['id'], 0, 25, 1, 'on', self::LAGER]]);

        self::assertSame('225|10', self::lines($order)[2]['lev_varenr'], '180 + 25 %, against 250 incl. VAT');
    }

    public function testASetWhoseItemCostsNothingIsFreeAllItsComponentsAtFullDiscount(): void
    {
        $s = self::set();
        self::rows("UPDATE varer SET salgspris = 0 WHERE id = $1", [$s['set']['id']]);
        $order = self::order();

        $this->runSteps([['saet', $order, $s['set']['id'], 0, 25, 1, '', self::LAGER]]);

        $lines = self::lines($order);
        self::assertSame(['100', '100'], [(string)(float)$lines[0]['rabat'], (string)(float)$lines[1]['rabat']]);
        self::assertEqualsWithDelta(0.0, (float)$lines[2]['pris'], 0.0001);
        self::assertSame(0.0, round(self::net($lines), 2));
    }

    public function testAComponentThatIsASetAddsItsOwnComponentsAfterIt(): void
    {
        $s = self::set();
        $w = self::$fx->item(['salgspris' => 10]);
        self::rows("INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $2, 3, 1)", [$w['id'], $s['y']['id']]);
        $order = self::order();

        $this->runSteps([['saet', $order, $s['set']['id'], 300, 25, 1, '', self::LAGER]]);

        $lines = self::lines($order);
        self::assertSame([$s['x']['id'], $s['y']['id'], $w['id'], $s['set']['id']], array_map(static fn($l) => (int)$l['vare_id'], $lines),
            'Y stays a line of its own, and its components are added too');
        self::assertSame('6', (string)(float)$lines[2]['antal'], '3 W in each of the 2 Y');
    }

    public function testASecondSetIsNumberedOnAndNotMergedIntoTheFirst(): void
    {
        $s = self::set();
        $order = self::order();
        $set = ['saet', $order, $s['set']['id'], 160, 25, 1, '', self::LAGER];

        $this->runSteps([$set, $set]);

        $lines = self::lines($order);
        self::assertCount(6, $lines, 'two lines per component and two set lines, though they are identical');
        self::assertSame([1, 1, 1, 2, 2, 2], array_map(static fn($l) => (int)$l['saet'], $lines));
    }

    public function testANewSetFirstUndoesTheOrderDiscountAndRestoresEachSetsDiscount(): void
    {
        $s = self::set();
        $plain = self::$fx->item(['salgspris' => 70]);
        $discountItem = self::$fx->item(['salgspris' => 0]);
        self::rows("UPDATE grupper SET box2 = $1 WHERE art = 'DIV' AND kodenr = '3'", [(string)$discountItem['id']]);
        $order = self::order();
        $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 1, '', self::LAGER]]);
        // an order discount as the order page gives it: a discount item line and a discount on every line
        self::rows("INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, antal, pris, rabat, posnr, saet)
            VALUES ($1, $2, $3, 1, 70, 0, 10, 0), ($1, $4, $5, 1, -10, 0, 11, 0)",
            [$order, $plain['id'], $plain['varenr'], $discountItem['id'], $discountItem['varenr']]);
        self::rows("UPDATE ordrelinjer SET rabat = 7 WHERE ordre_id = $1", [$order]);

        $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 1, '', self::LAGER]]);

        $lines = self::lines($order);
        $byItem = static fn(int $id) => array_values(array_filter($lines, static fn($l) => (int)$l['vare_id'] === $id));
        self::assertSame([], $byItem($discountItem['id']), 'the discount item line is deleted');
        self::assertEqualsWithDelta(0.0, (float)$byItem($plain['id'])[0]['rabat'], 0.0001, 'a plain line loses the order discount');
        $first = array_values(array_filter($lines, static fn($l) => (int)$l['saet'] === 1));
        self::assertSame(['20', '20', '20'], array_map(static fn($l) => (string)(float)$l['rabat'], $first),
            "the first set's discount is back, on its set line too");
        self::assertCount(3, array_filter($lines, static fn($l) => (int)$l['saet'] === 2));
    }

    public function testWithoutADiscountItemTheOrderDiscountStays(): void
    {
        $s = self::set();
        self::rows("UPDATE grupper SET box2 = '' WHERE art = 'DIV' AND kodenr = '3'");
        $plain = self::$fx->item(['salgspris' => 70]);
        $order = self::order();
        self::rows("INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, antal, pris, rabat, posnr, saet) VALUES ($1, $2, $3, 1, 70, 7, 10, 0)",
            [$order, $plain['id'], $plain['varenr']]);

        $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 1, '', self::LAGER]]);

        $lines = self::lines($order);
        self::assertEqualsWithDelta(7.0, (float)$lines[0]['rabat'], 0.0001);
    }
}
