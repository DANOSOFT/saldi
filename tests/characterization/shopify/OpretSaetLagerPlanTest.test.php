<?php
// tests/characterization/shopify/OpretSaetLagerPlanTest.test.php
//
// opret_saet() with a lager plan (SD-789 core edit 2), validate_saet_lager_plan() and
// shopify_reread_new_lines(). OpretSaetPlanTest pins the same sets without a plan.
//
// History:
// 20261102 CL/SS SD-789: created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/support/OrderLineSteps.php';

final class OpretSaetLagerPlanTest extends CharacterizationTestCase
{
    use OrderLineSteps;

    private const ASSEMBLY = 9;

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
            self::rows("DELETE FROM grupper WHERE beskrivelse IN ('SD789 Uden konto', 'SD789 Kun DG', 'SD789 Sidste aar')");
        }
        parent::tearDownAfterClass();
    }

    /**
     * The K55 set: 1 X (100) + 2 Y (50), set item 180.
     *
     * @return array{set: array, x: array, y: array, bx: int, by: int}
     */
    private static function set(): array
    {
        $x = self::$fx->item(['salgspris' => 100, 'kostpris' => 40]);
        $y = self::$fx->item(['salgspris' => 50, 'kostpris' => 20]);
        $set = self::$fx->item(['salgspris' => 180, 'kostpris' => 0]);
        self::rows("UPDATE varer SET samlevare = 'on' WHERE id = $1", [$set['id']]);
        $bom = self::rows("INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $3, 1, 1), ($2, $3, 2, 2) RETURNING id",
            [$x['id'], $y['id'], $set['id']]);
        return ['set' => $set, 'x' => $x, 'y' => $y, 'bx' => (int)$bom[0]['id'], 'by' => (int)$bom[1]['id']];
    }

    /** K55: 2 sets, lager 1 has 3 X / 2 Y, lager 2 has 6 Y. */
    private static function k55Plan(array $s): array
    {
        return [
            ['bom_id' => $s['bx'], 'lager' => 1, 'quantity_milli' => 2000],
            ['bom_id' => $s['by'], 'lager' => 1, 'quantity_milli' => 2000],
            ['bom_id' => $s['by'], 'lager' => 2, 'quantity_milli' => 2000],
        ];
    }

    public function testTheK55PlanDrawsEachComponentFromItsLagreAtTheSetPrice(): void
    {
        $s = self::set();
        $order = self::order();

        $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)]]);

        $lines = self::lines($order);
        $shape = array_map(static fn($l) => [(int)$l['vare_id'], (string)(float)$l['antal'], (int)$l['lager']], $lines);
        self::assertSame([
            [$s['x']['id'], '2', 1],
            [$s['y']['id'], '2', 1],
            [$s['y']['id'], '2', 2],
            [$s['set']['id'], '1', self::ASSEMBLY],
        ], $shape, '2 X + 2 Y at lager 1, 2 Y at lager 2, the set line at the assembly lager');
        self::assertSame(['20', '20', '20', '0'], array_map(static fn($l) => (string)(float)$l['rabat'], $lines));
        self::assertSame([1, 1, 1, 1], array_map(static fn($l) => (int)$l['saet'], $lines));
        self::assertSame(320.0, round(self::net($lines), 2));
        self::assertSame('320|20', $lines[3]['lev_varenr']);
        self::assertSame('on', $lines[3]['samlevare']);
    }

    /**
     * Only where nothing but the set discount applies to the price: a plan's lines are written with
     * $fixed_price and summed as the order sums them, so a customer discount or other rounding differs by design.
     */
    public function testAPlanOnOneLagerWritesTheSameLinesAsNoPlanWhenOnlyTheSetDiscountApplies(): void
    {
        $s = self::set();
        $planned = self::order();
        $legacy = self::order();
        $plan = [['bom_id' => $s['bx'], 'lager' => 3, 'quantity_milli' => 1000], ['bom_id' => $s['by'], 'lager' => 3, 'quantity_milli' => 2000]];

        $this->runSteps([
            ['saet', $planned, $s['set']['id'], 199.99, 25, 1, 'on', 3, $plan],
            ['saet', $legacy, $s['set']['id'], 199.99, 25, 1, 'on', 3],
        ]);

        $strip = static fn(array $lines) => array_map(static function ($l) {
            unset($l['id']);
            return $l;
        }, $lines);
        self::assertSame($strip(self::lines($legacy)), $strip(self::lines($planned)));
    }

    /** Each line's net rounded to three decimals, summed: how momsupdat() and the order page add up an order's lines. */
    private static function lineSum(array $lines): float
    {
        return array_sum(array_map(static fn($l) => round(((float)$l['pris'] - (float)$l['pris'] / 100 * (float)$l['rabat']) * (float)$l['antal'], 3), $lines));
    }

    /**
     * Ten components at 10.01, each drawn half from two lagre, sold for 50.05: every split line rounds
     * on its own when the order is summed, so the set line must take exactly what that leaves.
     */
    public function testASplitPlanSumsToTheSetPriceLineByLine(): void
    {
        $set = self::$fx->item(['salgspris' => 50.05, 'kostpris' => 0]);
        self::rows("UPDATE varer SET samlevare = 'on' WHERE id = $1", [$set['id']]);
        $plan = [];
        for ($i = 1; $i <= 10; $i++) {
            $c = self::$fx->item(['salgspris' => 10.01]);
            $bom = self::one("INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $2, 1, $3) RETURNING id", [$c['id'], $set['id'], $i]);
            $plan[] = ['bom_id' => (int)$bom['id'], 'lager' => 1, 'quantity_milli' => 500];
            $plan[] = ['bom_id' => (int)$bom['id'], 'lager' => 2, 'quantity_milli' => 500];
        }
        $order = self::order();

        $this->runSteps([['saet', $order, $set['id'], 50.05, 25, 1, '', self::ASSEMBLY, $plan]]);

        self::assertCount(21, self::lines($order));
        self::assertSame(50.05, round(self::lineSum(self::lines($order)), 3));
    }

    /** Set price 200 is the parts' sum, so the set discount is 0: a customer discount must not come in. */
    public function testAPlannedSetGetsNoCustomerDiscountNorQuantityDiscount(): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year)
            SELECT 'SD789 Plan', '96', 'DG', $1
            WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art = 'DG' AND kodenr = '96' AND fiscal_year = $1)", [self::$regnaar]);
        $customer = self::$fx->debtor();
        self::rows("UPDATE adresser SET gruppe = 96 WHERE id = $1", [$customer['id']]);
        $s = self::set();
        self::rows("DELETE FROM rabat WHERE debitor = 96");
        self::rows("INSERT INTO rabat (rabat, debitorart, debitor, vareart, vare, rabatart) VALUES (10, 'DG', 96, 'VG', $1, '%')", [$s['x']['gruppe']]);
        self::rows("UPDATE varer SET m_antal = '2', m_rabat = '10', m_type = 'percent' WHERE id = $1", [$s['y']['id']]);
        $order = self::order($customer);
        $plan = [['bom_id' => $s['bx'], 'lager' => 1, 'quantity_milli' => 1000], ['bom_id' => $s['by'], 'lager' => 1, 'quantity_milli' => 2000]];

        try {
            $this->runSteps([['saet', $order, $s['set']['id'], 200, 25, 1, '', self::ASSEMBLY, $plan]]);
        } finally {
            self::rows("DELETE FROM rabat WHERE debitor = 96");
            self::rows("DELETE FROM grupper WHERE art = 'DG' AND beskrivelse = 'SD789 Plan'");
        }

        $lines = self::lines($order);
        self::assertSame(['0', '0', '0'], array_map(static fn($l) => (string)(float)$l['rabat'], $lines));
        self::assertSame(['0', '0', '0'], array_map(static fn($l) => (string)(float)$l['m_rabat'], $lines));
        self::assertSame(200.0, round(self::lineSum($lines), 2));
    }

    public function testAPlannedSetLineKeepsItsRemainderOverASpecialPrice(): void
    {
        $s = self::set();
        self::rows("UPDATE varer SET special_price = 150, specialtype = '', special_from_date = CURRENT_DATE - 1, special_to_date = CURRENT_DATE + 1,
            special_from_time = '00:00:00', special_to_time = '23:59:59' WHERE id = $1", [$s['set']['id']]);
        $order = self::order();

        $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)]]);

        $lines = self::lines($order);
        self::assertEqualsWithDelta(0.0, (float)$lines[3]['pris'], 0.0001, 'the set line is 0, not the special price');
        self::assertSame(320.0, round(self::lineSum($lines), 2));
    }

    public function testAPlannedSetAtPriceZeroIsFreeNotTheSetItemsPrice(): void
    {
        $s = self::set();
        $order = self::order();

        $this->runSteps([['saet', $order, $s['set']['id'], 0, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)]]);

        $lines = self::lines($order);
        self::assertSame(['100', '100', '100', '0'], array_map(static fn($l) => (string)(float)$l['rabat'], $lines));
        self::assertSame('0|100', $lines[3]['lev_varenr']);
        self::assertSame(0.0, round(self::lineSum($lines), 2));
    }

    public function testAPlannedSetPriceMustBeANumberOfZeroOrMore(): void
    {
        $s = self::set();
        $order = self::order();

        $results = $this->runSteps([
            ['saet', $order, $s['set']['id'], null, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)],
            ['saet', $order, $s['set']['id'], -10, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)],
            ['saet', $order, $s['set']['id'], 'gratis', 25, 2, '', self::ASSEMBLY, self::k55Plan($s)],
        ]);

        foreach ($results as $result) {
            self::assertSame('InvalidArgumentException', $result['exception'] ?? null);
            self::assertStringContainsString('is not a number of 0 or more', $result['message']);
        }
        self::assertSame([], self::lines($order));
    }

    public function testAPlannedComponentWithoutAnItemGroupThrowsBeforeAnythingIsWritten(): void
    {
        $s = self::set();
        self::rows("UPDATE varer SET gruppe = 9789 WHERE id = $1", [$s['y']['id']]);
        $order = self::order();

        [$result] = $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)]]);

        self::assertSame('DomainException', $result['exception'] ?? null);
        self::assertStringContainsString("component {$s['y']['id']} of set {$s['set']['id']} has no item or no item group", $result['message']);
        self::assertSame([], self::lines($order));
    }

    /** opret_ordrelinje() needs an item group (art VG) of the fiscal year: a debtor group or last year's group is none. */
    public function testAPlannedComponentWhoseGroupIsNoItemGroupOfTheYearThrowsBeforeAnythingIsWritten(): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year)
            SELECT 'SD789 Kun DG', '9777', 'DG', $1
            WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE kodenr = '9777')", [self::$regnaar]);
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year, box4)
            SELECT 'SD789 Sidste aar', '9776', 'VG', $1, '1000'
            WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE kodenr = '9776')", [self::$regnaar - 1]);
        $results = [];
        foreach ([9777, 9776] as $gruppe) {
            $s = self::set();
            self::rows("UPDATE varer SET gruppe = $1 WHERE id = $2", [$gruppe, $s['y']['id']]);
            $order = self::order();
            [$result] = $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)]]);
            $results[] = [$result['exception'] ?? null, str_contains($result['message'] ?? '', 'has no item or no item group'), count(self::lines($order))];
        }

        self::assertSame([['DomainException', true, 0], ['DomainException', true, 0]], $results, 'refused before X, the first component, is written');
    }

    public function testAPlannedSetItemWithoutAnItemGroupThrowsBeforeAnythingIsWritten(): void
    {
        $s = self::set();
        self::rows("UPDATE varer SET gruppe = 9789 WHERE id = $1", [$s['set']['id']]);
        $order = self::order();

        [$result] = $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)]]);

        self::assertSame('DomainException', $result['exception'] ?? null);
        self::assertStringContainsString("set item {$s['set']['id']} has no item or no item group", $result['message']);
        self::assertSame([], self::lines($order));
    }

    public function testPlannedComponentsWithoutAPriceThrowBeforeAnythingIsWritten(): void
    {
        $s = self::set();
        self::rows("UPDATE varer SET salgspris = 0 WHERE id IN ($1, $2)", [$s['x']['id'], $s['y']['id']]);
        $order = self::order();

        [$result] = $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)]]);

        self::assertSame('DomainException', $result['exception'] ?? null);
        self::assertStringContainsString('have no price', $result['message']);
        self::assertSame([], self::lines($order));
    }

    /** opret_ordrelinje() refuses an item whose group has no sales account; the caller's transaction rolls the rest back. */
    public function testARefusedPlannedLineThrows(): void
    {
        self::rows("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year, box4)
            SELECT 'SD789 Uden konto', '9788', 'VG', $1, ''
            WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art = 'VG' AND kodenr = '9788' AND fiscal_year = $1)", [self::$regnaar]);
        $s = self::set();
        self::rows("UPDATE varer SET gruppe = 9788 WHERE id = $1", [$s['y']['id']]);
        $t = self::set();
        self::rows("UPDATE varer SET gruppe = 9788 WHERE id = $1", [$t['set']['id']]);
        $order = self::order();

        [$component, $setLine] = $this->runSteps([
            ['saet', $order, $s['set']['id'], 160, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)],
            ['saet', $order, $t['set']['id'], 160, 25, 2, '', self::ASSEMBLY, self::k55Plan($t)],
        ]);

        self::assertSame('DomainException', $component['exception'] ?? null);
        self::assertStringContainsString("the line for component {$s['y']['id']} of set {$s['set']['id']} was refused", $component['message']);
        self::assertSame('DomainException', $setLine['exception'] ?? null);
        self::assertStringContainsString("the set line of set {$t['set']['id']} was refused", $setLine['message']);
    }

    public function testWithAPlanTheOrderDiscountIsNotUndone(): void
    {
        $s = self::set();
        $plain = self::$fx->item(['salgspris' => 70]);
        $discountItem = self::$fx->item(['salgspris' => 0]);
        self::rows("UPDATE grupper SET box2 = $1 WHERE art = 'DIV' AND kodenr = '3'", [(string)$discountItem['id']]);
        $order = self::order();
        self::rows("INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, antal, pris, rabat, posnr, saet)
            VALUES ($1, $2, $3, 1, 70, 7, 10, 0), ($1, $4, $5, 1, -10, 0, 11, 0)",
            [$order, $plain['id'], $plain['varenr'], $discountItem['id'], $discountItem['varenr']]);

        $this->runSteps([['saet', $order, $s['set']['id'], 320, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)]]);

        $lines = self::lines($order);
        self::assertSame([$plain['id'], $discountItem['id']], [(int)$lines[0]['vare_id'], (int)$lines[1]['vare_id']], 'gendan_saet() did not run');
        self::assertEqualsWithDelta(7.0, (float)$lines[0]['rabat'], 0.0001);
    }

    public function testASecondPlannedSetIsNumberedOnAndNotMerged(): void
    {
        $s = self::set();
        $order = self::order();
        $set = ['saet', $order, $s['set']['id'], 160, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)];

        $this->runSteps([$set, $set]);

        self::assertSame([1, 1, 1, 1, 2, 2, 2, 2], array_map(static fn($l) => (int)$l['saet'], self::lines($order)));
    }

    public function testAPlanThatDoesNotFitThrowsAndWritesNothing(): void
    {
        $s = self::set();
        $order = self::order();
        $short = [['bom_id' => $s['bx'], 'lager' => 1, 'quantity_milli' => 2000], ['bom_id' => $s['by'], 'lager' => 1, 'quantity_milli' => 3000]];

        [$result] = $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 2, '', self::ASSEMBLY, $short]]);

        self::assertSame('InvalidArgumentException', $result['exception'] ?? null);
        self::assertStringContainsString("BOM occurrence {$s['by']} needs 4000 thousandths, the plan draws 3000", $result['message']);
        self::assertSame([], self::lines($order));
    }

    public function testAPlanNeedsAWholeNumberOfSetsAndOneBomLevel(): void
    {
        $s = self::set();
        $w = self::$fx->item(['salgspris' => 10]);
        $order = self::order();
        $half = [['bom_id' => $s['bx'], 'lager' => 1, 'quantity_milli' => 1500], ['bom_id' => $s['by'], 'lager' => 1, 'quantity_milli' => 3000]];

        [$fraction, $zero] = $this->runSteps([
            ['saet', $order, $s['set']['id'], 160, 25, 1.5, '', self::ASSEMBLY, $half],
            ['saet', $order, $s['set']['id'], 160, 25, 0, '', self::ASSEMBLY, $half],
        ]);
        self::rows("INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $2, 3, 1)", [$w['id'], $s['y']['id']]);
        [$nested] = $this->runSteps([['saet', $order, $s['set']['id'], 160, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)]]);

        self::assertStringContainsString('the number of sets (1.5) is not whole', $fraction['message'] ?? '');
        self::assertStringContainsString('the number of sets (0) is not positive', $zero['message'] ?? '', 'a plan is checked before no sets return');
        self::assertStringContainsString("component {$s['y']['id']} (BOM occurrence {$s['by']}) is a set itself", $nested['message'] ?? '');
        self::assertSame([], self::lines($order));
    }

    public function testValidateReportsEveryProblemOfAPlan(): void
    {
        $bom = [['id' => 11, 'vare_id' => 1, 'antal' => 1], ['id' => 12, 'vare_id' => 2, 'antal' => 2]];
        $valid = [['bom_id' => 11, 'lager' => 1, 'quantity_milli' => 2000], ['bom_id' => 12, 'lager' => 1, 'quantity_milli' => 2000],
            ['bom_id' => 12, 'lager' => 2, 'quantity_milli' => 2000]];
        $bad = [
            ['bom_id' => 11, 'lager' => 1, 'quantity_milli' => 1000],
            ['bom_id' => 11, 'lager' => 1, 'quantity_milli' => 1000],
            ['bom_id' => 12, 'lager' => 0, 'quantity_milli' => 4000],
            ['bom_id' => 13, 'lager' => 1, 'quantity_milli' => 1000],
            ['bom_id' => 12, 'lager' => 2, 'quantity_milli' => 0],
            ['bom_id' => '12', 'lager' => 2, 'quantity_milli' => 1000],
        ];

        [$ok, $problems, $none, $zero, $fraction, $empty] = $this->runSteps([
            ['validate', $bom, $valid, 2],
            ['validate', $bom, $bad, 2],
            ['validate', [], [], 1],
            ['validate', $bom, [], 0],
            ['validate', [['id' => 11, 'vare_id' => 1, 'antal' => '0.0005']], [], 1],
            ['validate', [['id' => 11, 'vare_id' => 1, 'antal' => null]], [], 1],
        ]);

        self::assertSame([], $ok);
        self::assertSame([
            'BOM occurrence 11 is drawn from lager 1 twice',
            'draw 2 of BOM occurrence 12 has no lager',
            'draw 3 is for no BOM occurrence of the set',
            'draw 4 of BOM occurrence 12 has no positive quantity',
            'draw 5 has no integer bom_id',
        ], $problems, 'BOM occurrence 11 adds up (1000 twice), 12 does: the 4000 from no lager');
        self::assertSame(['the set has no components'], $none);
        self::assertSame(['the number of sets (0) is not positive', 'BOM occurrence 11 needs no quantity', 'BOM occurrence 12 needs no quantity'], $zero);
        self::assertSame(['BOM occurrence 11 needs no quantity'], $empty, 'without a plan such a line is written with no quantity');
        self::assertSame(['BOM occurrence 11 does not need a whole number of thousandths', 'BOM occurrence 11 needs 1 thousandths, the plan draws 0'], $fraction);
    }

    public function testTheNewLinesAreReadBackInTheOrderTheyWereWritten(): void
    {
        $s = self::set();
        $plain = self::$fx->item(['salgspris' => 70]);
        $order = self::order();
        self::rows("INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, antal, pris, rabat, posnr, saet) VALUES ($1, $2, $3, 1, 70, 0, 1, 0)",
            [$order, $plain['id'], $plain['varenr']]);
        $before = array_map(static fn($l) => (int)$l['id'], self::lines($order));

        [, $new] = $this->runSteps([
            ['saet', $order, $s['set']['id'], 160, 25, 2, '', self::ASSEMBLY, self::k55Plan($s)],
            ['reread', $order, $before],
        ]);

        $written = array_slice(self::lines($order), 1);
        self::assertSame(array_map(static fn($l) => (int)$l['id'], $written), array_column($new, 'id'));
        self::assertSame([1, 1, 2, self::ASSEMBLY], array_column($new, 'lager'));
        self::assertSame(['', '', '', 'on'], array_column($new, 'samlevare'));
    }
}
