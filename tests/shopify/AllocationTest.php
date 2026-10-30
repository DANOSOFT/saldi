<?php
// tests/shopify/AllocationTest.php
//
// The allocation engine (SD-739, WP07-1): assigned sellable lager first, then priority (K15,
// K16); display, ignored and unmapped assignments alarmed and never drawn from; shortages;
// whole and split sets with the K55 worked example; determinism and conservation. Pure, no
// database.
//
// History:
// 20261028 CL/SS SD-788: created.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shopify/services/allocation.php';

final class AllocationTest extends TestCase
{
    private const X = 101;
    private const Y = 102;
    private const MAARUM = 9;

    /** Lager 1 and 2 sellable (priority 1, 2), 3 a display lager, 4 ignored, Mårum 9 sellable last. */
    private static function locations(): array
    {
        return [
            ['lager' => 1, 'rolle' => 'saelgbar', 'prioritet' => 1, 'location_gid' => 'gid://shopify/Location/1'],
            ['lager' => 2, 'rolle' => 'saelgbar', 'prioritet' => 2, 'location_gid' => 'gid://shopify/Location/2'],
            ['lager' => 3, 'rolle' => 'udstilling', 'prioritet' => null, 'location_gid' => 'gid://shopify/Location/3'],
            ['lager' => 4, 'rolle' => 'ingen', 'prioritet' => null, 'location_gid' => 'gid://shopify/Location/4'],
            ['lager' => self::MAARUM, 'rolle' => 'saelgbar', 'prioritet' => 9, 'location_gid' => 'gid://shopify/Location/9'],
        ];
    }

    private static function set(string $key, int $sets, ?int $location = null): array
    {
        return ['key' => $key, 'vare_id' => 100, 'quantity_milli' => $sets * 1000, 'assigned_location' => $location ? "gid://shopify/Location/$location" : null,
            'components' => [['bom_id' => 11, 'vare_id' => self::X, 'quantity_milli' => 1000], ['bom_id' => 12, 'vare_id' => self::Y, 'quantity_milli' => 2000]]];
    }

    private static function line(string $key, int $vareId, int $qtyMilli, ?int $location = null): array
    {
        return ['key' => $key, 'vare_id' => $vareId, 'quantity_milli' => $qtyMilli, 'assigned_location' => $location ? "gid://shopify/Location/$location" : null];
    }

    private static function allocate(array $lines, array $stock, ?array $locations = null): array
    {
        return shopify_allocate_order($lines, $locations ?? self::locations(), $stock, self::MAARUM);
    }

    public function testTheK55WorkedExampleSplitsTheSetAndAssemblesItAtMaarum(): void
    {
        // Set of 1 X + 2 Y; location 1 has 3 X / 2 Y, location 2 has 6 Y; an order for 2 sets.
        $stock = [self::X => [1 => 3000], self::Y => [1 => 2000, 2 => 6000]];
        foreach ([null, 1] as $assigned) {
            $r = self::allocate([self::set('s', 2, $assigned)], $stock);
            $line = $r['lines']['s'];
            $this->assertSame([
                ['bom_id' => 11, 'lager' => 1, 'quantity_milli' => 2000],
                ['bom_id' => 12, 'lager' => 1, 'quantity_milli' => 2000],
                ['bom_id' => 12, 'lager' => 2, 'quantity_milli' => 2000],
            ], $line['lager_plan'], '2 X + 2 Y at location 1 and 2 Y at location 2');
            $this->assertTrue($line['split']);
            $this->assertSame(self::MAARUM, $line['lager'], 'a split set is assembled at Mårum');
            $this->assertSame(['saldi-saet-delt'], $line['tags']);
            $this->assertSame(['saldi-saet-delt'], $r['tags']);
            $this->assertSame([], $r['shortages']);
        }
    }

    public function testASplitSetDrawsFromItsAssignedLagerFirstThenByPriority(): void
    {
        // K55 (2): assigned location 2 can't hold the whole set; its components go first, the rest by priority.
        $stock = [self::X => [1 => 5000], self::Y => [1 => 5000, 2 => 1000]];
        $line = self::allocate([self::set('s', 1, 2)], $stock)['lines']['s'];
        $this->assertSame([
            ['bom_id' => 11, 'lager' => 1, 'quantity_milli' => 1000],
            ['bom_id' => 12, 'lager' => 2, 'quantity_milli' => 1000],
            ['bom_id' => 12, 'lager' => 1, 'quantity_milli' => 1000],
        ], $line['lager_plan']);
        $this->assertTrue($line['split']);
    }

    public function testASetIsDrawnWholeFromOneLagerWhenItCanBe(): void
    {
        $stock = [self::X => [1 => 1000, 2 => 5000], self::Y => [1 => 5000, 2 => 5000]];
        $assigned = self::allocate([self::set('s', 2, 2)], $stock)['lines']['s'];
        $this->assertSame([2, false, []], [$assigned['lager'], $assigned['split'], $assigned['tags']], 'the assigned lager holds the whole set');
        $this->assertSame([['bom_id' => 11, 'lager' => 2, 'quantity_milli' => 2000], ['bom_id' => 12, 'lager' => 2, 'quantity_milli' => 4000]], $assigned['lager_plan']);

        $unassigned = self::allocate([self::set('s', 2)], $stock)['lines']['s'];
        $this->assertSame([2, false], [$unassigned['lager'], $unassigned['split']], 'priority 1 lacks an X, so the first lager that has every component');

        $this->assertSame([], self::allocate([self::set('s', 2)], $stock)['tags']);
    }

    public function testAPlainLineIsDrawnFromItsAssignedLagerThenByPriority(): void
    {
        // K15: one line, two lagre.
        $r = self::allocate([self::line('a', 500, 5000, 2)], [500 => [1 => 4000, 2 => 3000]]);
        $this->assertSame([
            ['lager' => 2, 'quantity_milli' => 3000, 'shortage_milli' => 0],
            ['lager' => 1, 'quantity_milli' => 2000, 'shortage_milli' => 0],
        ], $r['lines']['a']['segments']);
        $this->assertSame(2, $r['lines']['a']['lager']);
        $this->assertSame([], $r['alarms']);
    }

    public function testTheSellablePriorityDecidesWithoutAnAssignment(): void
    {
        // K16: changing the priority in Saldi changes the draw, no code involved.
        $stock = [500 => [1 => 9000, 2 => 9000]];
        $this->assertSame(1, self::allocate([self::line('a', 500, 1000)], $stock)['lines']['a']['lager']);
        $swapped = self::locations();
        $swapped[0]['prioritet'] = 5;
        $this->assertSame(2, self::allocate([self::line('a', 500, 1000)], $stock, $swapped)['lines']['a']['lager']);
    }

    public function testADisplayIgnoredOrUnmappedAssignmentIsAlarmedAndNeverDrawnFrom(): void
    {
        $stock = [500 => [3 => 9000, 4 => 9000, 2 => 9000]];
        $r = self::allocate([self::line('d', 500, 1000, 3), self::line('i', 500, 1000, 4), self::line('u', 500, 1000, 77)], $stock);
        $this->assertSame([
            ['key' => 'd', 'kind' => 'assignment_display', 'location' => 'gid://shopify/Location/3', 'lager' => 3],
            ['key' => 'i', 'kind' => 'assignment_ignored', 'location' => 'gid://shopify/Location/4', 'lager' => 4],
            ['key' => 'u', 'kind' => 'assignment_unmapped', 'location' => 'gid://shopify/Location/77', 'lager' => null],
        ], $r['alarms']);
        foreach (['d', 'i', 'u'] as $key) {
            $this->assertSame([['lager' => 2, 'quantity_milli' => 1000, 'shortage_milli' => 0]], $r['lines'][$key]['segments'], "$key: treated as unassigned, drawn by priority from a sellable lager");
        }
    }

    public function testAShortageIsPlacedOnTheAssignedOrFirstSellableLagerAndRecorded(): void
    {
        $r = self::allocate([self::line('a', 500, 5000, 2), self::line('b', 600, 3000), self::line('c', 700, 2000, 3)], [500 => [1 => 1000, 2 => 2000], 600 => [2 => 1000]]);
        $this->assertSame([
            ['lager' => 2, 'quantity_milli' => 4000, 'shortage_milli' => 2000],
            ['lager' => 1, 'quantity_milli' => 1000, 'shortage_milli' => 0],
        ], $r['lines']['a']['segments'], 'the shortage joins the assigned lager');
        $this->assertSame([
            ['lager' => 1, 'quantity_milli' => 2000, 'shortage_milli' => 2000],
            ['lager' => 2, 'quantity_milli' => 1000, 'shortage_milli' => 0],
        ], $r['lines']['b']['segments'], 'without an assignment, the first sellable lager');
        $this->assertSame([['lager' => 1, 'quantity_milli' => 2000, 'shortage_milli' => 2000]], $r['lines']['c']['segments'], 'a display assignment never takes the shortage');
        $this->assertSame([
            ['key' => 'a', 'vare_id' => 500, 'bom_id' => null, 'lager' => 2, 'quantity_milli' => 2000],
            ['key' => 'b', 'vare_id' => 600, 'bom_id' => null, 'lager' => 1, 'quantity_milli' => 2000],
            ['key' => 'c', 'vare_id' => 700, 'bom_id' => null, 'lager' => 1, 'quantity_milli' => 2000],
        ], $r['shortages']);
    }

    public function testASetComponentShortIsRecordedWithItsBomOccurrence(): void
    {
        $r = self::allocate([self::set('s', 1, 1)], [self::X => [1 => 1000], self::Y => [1 => 1000]]);
        $this->assertSame([['bom_id' => 11, 'lager' => 1, 'quantity_milli' => 1000], ['bom_id' => 12, 'lager' => 1, 'quantity_milli' => 2000]], $r['lines']['s']['lager_plan']);
        $this->assertFalse($r['lines']['s']['split'], 'a shortage on the assigned lager does not split the set');
        $this->assertSame([['key' => 's', 'vare_id' => self::Y, 'bom_id' => 12, 'lager' => 1, 'quantity_milli' => 1000]], $r['shortages']);
    }

    public function testAShortageAloneNeverSplitsASet(): void
    {
        // X only at lager 2, Y nowhere: the set is drawn at lager 2 and Y's shortage joins it there.
        $r = self::allocate([self::set('s', 1)], [self::X => [2 => 1000]]);
        $line = $r['lines']['s'];
        $this->assertSame([false, 2, []], [$line['split'], $line['lager'], $line['tags']]);
        $this->assertSame([['bom_id' => 11, 'lager' => 2, 'quantity_milli' => 1000], ['bom_id' => 12, 'lager' => 2, 'quantity_milli' => 2000]], $line['lager_plan']);
        $this->assertSame([['key' => 's', 'vare_id' => self::Y, 'bom_id' => 12, 'lager' => 2, 'quantity_milli' => 2000]], $r['shortages']);
    }

    public function testAnAssignedSetShortOfAComponentStaysWithTheLagerItIsDrawnFrom(): void
    {
        // Assigned to lager 2, X only at lager 1, Y nowhere: the set stays whole at lager 1.
        $r = self::allocate([self::set('s', 1, 2)], [self::X => [1 => 1000]]);
        $line = $r['lines']['s'];
        $this->assertSame([false, 1], [$line['split'], $line['lager']]);
        $this->assertSame([['bom_id' => 11, 'lager' => 1, 'quantity_milli' => 1000], ['bom_id' => 12, 'lager' => 1, 'quantity_milli' => 2000]], $line['lager_plan']);
        $nothing = self::allocate([self::set('s', 1, 2)], [])['lines']['s'];
        $this->assertSame([false, 2], [$nothing['split'], $nothing['lager']], 'nothing anywhere: everything short on the assigned lager');
    }

    public function testAComponentShortEverywhereDoesNotSplitASetOneLagerCanTake(): void
    {
        // 2 sets of 1 X + 1 Y; X at lager 1 (1) and 2 (2); Y only at lager 2 (1): everything at lager 2, 1 Y short there.
        $set = ['key' => 's', 'vare_id' => 100, 'quantity_milli' => 2000,
            'components' => [['bom_id' => 11, 'vare_id' => self::X, 'quantity_milli' => 1000], ['bom_id' => 12, 'vare_id' => self::Y, 'quantity_milli' => 1000]]];
        $r = self::allocate([$set], [self::X => [1 => 1000, 2 => 2000], self::Y => [2 => 1000]]);
        $this->assertSame([false, 2], [$r['lines']['s']['split'], $r['lines']['s']['lager']]);
        $this->assertSame([['bom_id' => 11, 'lager' => 2, 'quantity_milli' => 2000], ['bom_id' => 12, 'lager' => 2, 'quantity_milli' => 2000]], $r['lines']['s']['lager_plan']);
        $this->assertSame([['key' => 's', 'vare_id' => self::Y, 'bom_id' => 12, 'lager' => 2, 'quantity_milli' => 1000]], $r['shortages']);
    }

    public function testABlankAssignmentIsNoAssignment(): void
    {
        $r = self::allocate([['key' => 'a', 'vare_id' => 500, 'quantity_milli' => 1000, 'assigned_location' => '  ']], [500 => [1 => 1000]]);
        $this->assertSame([[], 1], [$r['alarms'], $r['lines']['a']['lager']]);
    }

    public function testANumericOrPaddedLocationIdIsTheSameLocation(): void
    {
        $stock = [500 => [1 => 1000, 2 => 9000]];
        $r = shopify_allocate_order([['key' => 'd', 'vare_id' => 500, 'quantity_milli' => 1000, 'assigned_location' => ' 3 ']], self::locations(), $stock, self::MAARUM);
        $this->assertSame('gid://shopify/Location/3', $r['alarms'][0]['location'], 'the alarm names the normalised location');
        foreach (['2', ' gid://shopify/Location/2 ', 'gid://shopify/Location/2?x=1'] as $location) {
            $r = shopify_allocate_order([['key' => 'a', 'vare_id' => 500, 'quantity_milli' => 1000, 'assigned_location' => $location]], self::locations(), $stock, self::MAARUM);
            $this->assertSame([[], 2], [$r['alarms'], $r['lines']['a']['lager']], "'$location' is location 2");
        }
        $r = shopify_allocate_order([['key' => 'a', 'vare_id' => 500, 'quantity_milli' => 1000, 'assigned_location' => 'not-a-gid']], self::locations(), $stock, self::MAARUM);
        $this->assertSame('assignment_unmapped', $r['alarms'][0]['kind'], 'an id that is no Location GID is unmapped');
    }

    public function testTheAssemblyLagerIsCheckedOnlyWhenASetSplits(): void
    {
        $k55 = [self::X => [1 => 3000], self::Y => [1 => 2000, 2 => 6000]];
        foreach ([0, 3] as $assembly) {
            $plain = shopify_allocate_order([self::line('a', 500, 1000), self::set('w', 1)], self::locations(), [500 => [1 => 1000]] + $k55, $assembly);
            $this->assertFalse($plain['lines']['w']['split'], "assembly lager $assembly: an order without a split set needs none");
            try {
                shopify_allocate_order([self::set('s', 2)], self::locations(), $k55, $assembly);
                $this->fail("assembly lager $assembly was accepted for a split set");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString("assembly lager $assembly", $e->getMessage());
            }
        }
    }

    public function testLinesShareTheStockSoNothingIsCountedTwice(): void
    {
        $r = self::allocate([self::line('a', self::X, 2000), self::set('s', 1), self::line('b', self::X, 1000)], [self::X => [1 => 3000, 2 => 1000], self::Y => [1 => 2000]]);
        $this->assertSame([['lager' => 1, 'quantity_milli' => 2000, 'shortage_milli' => 0]], $r['lines']['a']['segments']);
        $this->assertSame([['bom_id' => 11, 'lager' => 1, 'quantity_milli' => 1000], ['bom_id' => 12, 'lager' => 1, 'quantity_milli' => 2000]], $r['lines']['s']['lager_plan']);
        $this->assertSame([['lager' => 2, 'quantity_milli' => 1000, 'shortage_milli' => 0]], $r['lines']['b']['segments'], 'lager 1 has no X left');
    }

    public function testTheResultDependsOnlyOnTheInputNotOnItsKeyOrder(): void
    {
        $lines = [self::set('s', 2, 1), self::line('a', 500, 4500, 2), self::line('b', self::Y, 1500)];
        $stock = [self::X => [1 => 3000, 9 => 1000], self::Y => [1 => 2000, 2 => 6000], 500 => [1 => 1000, 2 => 1000, 9 => 1000]];
        $expected = self::allocate($lines, $stock);
        $shuffledStock = [];
        foreach (array_reverse($stock, true) as $vare => $perLager) {
            $shuffledStock[$vare] = array_reverse($perLager, true);
        }
        $this->assertSame($expected, self::allocate($lines, $shuffledStock, array_reverse(self::locations())));
        $this->assertSame($expected, self::allocate($lines, $stock), 'the same input gives the same plan');
    }

    public function testEveryQuantityIsAllocatedAndNoStockIsDrawnTwice(): void
    {
        mt_srand(739);
        try {
            $this->conservationRuns();
        } finally {
            mt_srand();
        }
    }

    private function conservationRuns(): void
    {
        for ($run = 0; $run < 300; $run++) {
            $stock = [];
            foreach ([self::X, self::Y, 500, 600] as $vare) {
                foreach ([1, 2, 3, 4, 9] as $lager) {
                    $stock[$vare][$lager] = mt_rand(-2, 6) * 500;
                }
            }
            $lines = [];
            for ($i = 0, $n = mt_rand(1, 5); $i < $n; $i++) {
                $loc = [null, 1, 2, 3, 4, 9, 77][mt_rand(0, 6)];
                $lines[] = mt_rand(0, 2) === 0 ? self::set("s$i", mt_rand(1, 3), $loc) : self::line("l$i", [self::X, self::Y, 500, 600][mt_rand(0, 3)], mt_rand(1, 8) * 250, $loc);
            }
            $r = self::allocate($lines, $stock);
            $drawn = [];
            $short = [];
            foreach ($lines as $line) {
                $out = $r['lines'][$line['key']];
                if (isset($line['components'])) {
                    foreach ($line['components'] as $c) {
                        $sum = 0;
                        foreach ($out['lager_plan'] as $p) {
                            if ($p['bom_id'] === $c['bom_id']) {
                                $sum += $p['quantity_milli'];
                                $drawn[$c['vare_id']][$p['lager']] = ($drawn[$c['vare_id']][$p['lager']] ?? 0) + $p['quantity_milli'];
                                $this->assertNotContains($p['lager'], [3, 4], 'never a display or ignored lager');
                            }
                        }
                        $this->assertSame(intdiv($c['quantity_milli'] * $line['quantity_milli'], 1000), $sum, "run $run: BOM occurrence {$c['bom_id']} fully allocated");
                    }
                } else {
                    $sum = 0;
                    foreach ($out['segments'] as $seg) {
                        $sum += $seg['quantity_milli'];
                        $drawn[$line['vare_id']][$seg['lager']] = ($drawn[$line['vare_id']][$seg['lager']] ?? 0) + $seg['quantity_milli'];
                        $this->assertNotContains($seg['lager'], [3, 4], 'never a display or ignored lager');
                    }
                    $this->assertSame($line['quantity_milli'], $sum, "run $run: line {$line['key']} fully allocated");
                }
            }
            foreach ($r['shortages'] as $s) {
                $short[$s['vare_id']][$s['lager']] = ($short[$s['vare_id']][$s['lager']] ?? 0) + $s['quantity_milli'];
            }
            foreach ($drawn as $vare => $perLager) {
                foreach ($perLager as $lager => $qty) {
                    $this->assertLessThanOrEqual(max(0, $stock[$vare][$lager]) + ($short[$vare][$lager] ?? 0), $qty, "run $run: item $vare at lager $lager drawn beyond its stock plus the recorded shortage");
                }
            }
            foreach ($short as $vare => $perLager) {
                $left = 0;
                foreach ([1, 2, 9] as $lager) {
                    $left += max(0, $stock[$vare][$lager]) - (($drawn[$vare][$lager] ?? 0) - ($short[$vare][$lager] ?? 0));
                }
                $this->assertSame(0, $left, "run $run: item $vare is reported short while a sellable lager still has it");
            }
        }
    }

    public function testRowsFromTheDatabaseMayCarryIntegerStrings(): void
    {
        $locations = [['lager' => '1', 'rolle' => 'saelgbar', 'prioritet' => '1', 'location_gid' => 'gid://shopify/Location/1']];
        $r = shopify_allocate_order([['key' => 1, 'vare_id' => '500', 'quantity_milli' => '1000']], $locations, ['500' => ['1' => '2000']], self::MAARUM);
        $this->assertSame([['lager' => 1, 'quantity_milli' => 1000, 'shortage_milli' => 0]], $r['lines'][1]['segments']);
    }

    /** @return iterable<string, array{0: array, 1: array, 2: string}> */
    public static function invalidInput(): iterable
    {
        $set = self::set('s', 1);
        yield 'no sellable lager' => [[self::line('a', 500, 1000)], [['lager' => 3, 'rolle' => 'udstilling']], 'no sellable lager'];
        yield 'unknown role' => [[self::line('a', 500, 1000)], [['lager' => 1, 'rolle' => 'lager']], "unknown role 'lager'"];
        yield 'sellable without priority' => [[self::line('a', 500, 1000)], [['lager' => 1, 'rolle' => 'saelgbar']], 'priority of sellable lager 1'];
        yield 'zero quantity' => [[self::line('a', 500, 0)], self::locations(), 'no positive quantity'];
        yield 'float quantity' => [[['key' => 'a', 'vare_id' => 500, 'quantity_milli' => 1.5]], self::locations(), 'must be an integer'];
        yield 'empty key' => [[self::line('', 500, 1000)], self::locations(), 'every line needs a key'];
        yield 'trailing newline' => [[['key' => 'a', 'vare_id' => 500, 'quantity_milli' => "5\n"]], self::locations(), 'must be an integer'];
        yield 'too many digits' => [[['key' => 'a', 'vare_id' => 500, 'quantity_milli' => '99999999999999999999']], self::locations(), 'must be an integer'];
        yield 'lager listed twice' => [[self::line('a', 500, 1000)], [['lager' => 1, 'rolle' => 'saelgbar', 'prioritet' => 1], ['lager' => 1, 'rolle' => 'udstilling']], 'lager 1 is listed twice'];
        yield 'location mapped twice' => [[self::line('a', 500, 1000)], [['lager' => 1, 'rolle' => 'saelgbar', 'prioritet' => 1, 'location_gid' => '5'], ['lager' => 2, 'rolle' => 'saelgbar', 'prioritet' => 2, 'location_gid' => 'gid://shopify/Location/5']], 'mapped twice'];
        yield 'overflowing set quantity' => [[['quantity_milli' => PHP_INT_MAX] + $set], self::locations(), 'whole number of thousandths'];
        yield 'components not a list' => [[['components' => 'X'] + self::line('s', 100, 1000)], self::locations(), 'components of line s are not a list'];
        yield 'lager 0' => [[self::line('a', 500, 1000)], [['lager' => 0, 'rolle' => 'saelgbar', 'prioritet' => 1]], 'location lager must be positive'];
        yield 'vare_id 0' => [[self::line('a', 0, 1000)], self::locations(), 'vare_id of line a must be positive'];
        yield 'BOM quantity 0' => [[['components' => [['bom_id' => 11, 'vare_id' => self::X, 'quantity_milli' => 0]]] + $set], self::locations(), 'no positive quantity'];
        yield 'location as a list' => [[['assigned_location' => ['gid://shopify/Location/1']] + self::line('a', 500, 1000)], self::locations(), 'not a location id'];
        yield 'duplicate key' => [[self::line('a', 500, 1000), self::line('a', 600, 1000)], self::locations(), 'used twice'];
        yield 'empty set' => [[['components' => []] + self::line('s', 100, 1000)], self::locations(), 'no components'];
        yield 'duplicate BOM occurrence' => [[['components' => [$set['components'][0], $set['components'][0]]] + $set], self::locations(), 'BOM occurrence 11 twice'];
        yield 'part of a thousandth' => [[['quantity_milli' => 1500, 'components' => [['bom_id' => 11, 'vare_id' => 1, 'quantity_milli' => 333]]] + $set], self::locations(), 'whole number of thousandths'];
    }

    #[DataProvider('invalidInput')]
    public function testMalformedInputIsRefused(array $lines, array $locations, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        shopify_allocate_order($lines, $locations, [], self::MAARUM);
    }

    public function testMalformedStockKeysAreRefused(): void
    {
        foreach ([['abc' => [1 => 1000]], [500 => ['x' => 1000]], [500 => [0 => 1000]], [500 => 1000]] as $stock) {
            try {
                shopify_allocate_order([self::line('a', 500, 1000)], self::locations(), $stock, self::MAARUM);
                $this->fail('accepted stock ' . json_encode($stock));
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('stock', $e->getMessage());
            }
        }
    }
}
