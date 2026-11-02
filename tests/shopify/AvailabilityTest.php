<?php
// tests/shopify/AvailabilityTest.php
//
// What Saldi publishes per item and lager (SD-755, WP06): the availability matrix (sellable,
// display, ingen, virtual lager, negative and fractional stock), K13, K60, sets with the K55 worked
// example and the surplus on the split-set lager, and the total never exceeding what the sellable
// lagre can assemble. Pure, no database.
//
// History:
// 20261102 CL/SZ SD-755: created.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/shopify/services/availability.php';

final class AvailabilityTest extends TestCase
{
    private const ITEM = 100;
    private const SET = 200;
    private const X = 201;
    private const Y = 202;

    /** Sellable 1 and 2, display 3, ingen 4, sellable-looking virtual 5. */
    private static function lagre(): array
    {
        return [
            ['lager' => 2, 'rolle' => 'saelgbar', 'prioritet' => 2],
            ['lager' => 1, 'rolle' => 'saelgbar', 'prioritet' => 1],
            ['lager' => 3, 'rolle' => 'udstilling', 'prioritet' => null],
            ['lager' => 4, 'rolle' => 'ingen', 'prioritet' => null],
            ['lager' => 5, 'rolle' => 'saelgbar', 'prioritet' => 3, 'virtual' => true],
        ];
    }

    private static function plain(array $perLager): array
    {
        return shopify_availability(['vare_id' => self::ITEM], self::lagre(), [self::ITEM => $perLager], [], null);
    }

    /** One X and two Y per set. */
    private static function bom(): array
    {
        return [
            ['bom_id' => 1, 'vare_id' => self::X, 'quantity_milli' => 1000],
            ['bom_id' => 2, 'vare_id' => self::Y, 'quantity_milli' => 2000],
        ];
    }

    private static function set(array $stock, ?int $split = null, ?array $bom = null): array
    {
        return shopify_availability(['vare_id' => self::SET], self::lagre(), $stock, $bom ?? self::bom(), $split);
    }

    private static function available(array $out): array
    {
        return array_map(fn(array $row): int => $row['available'], $out);
    }

    public function testThePublishedQuantityIsTheWholeStockOfEachSellableLager(): void
    {
        $out = self::plain([1 => 5000, 2 => 2500]);

        $this->assertSame([1, 2, 3], array_keys($out), 'sellable by priority, then display; ingen and virtual are never sent');
        $this->assertSame([1 => 5, 2 => 2, 3 => 0], self::available($out));
    }

    #[DataProvider('stockValues')]
    public function testStockIsFlooredAndNeverNegative(int|string|null $milli, int $expected): void
    {
        $stock = $milli === null ? [] : [1 => $milli];

        $this->assertSame($expected, self::plain($stock)[1]['available']);
    }

    /** @return array<string, array{int|string|null, int}> */
    public static function stockValues(): array
    {
        return [
            'negative' => [-3000, 0],
            'missing' => [null, 0],
            'just under one' => [999, 0],
            'exactly one' => [1000, 1],
            'just over one' => [1001, 1],
            'an integer string from a database row' => ['7000', 7],
        ];
    }

    public function testK13ADisplayLagerSendsZeroAndSaysTheItemStandsThere(): void
    {
        $out = self::plain([1 => 0, 3 => 1000]);

        $this->assertSame(['available' => 0, 'udstillet' => true], $out[3]);
        $this->assertSame(0, $out[1]['available']);
    }

    public function testADisplayLagerWithoutAWholeItemIsSentAndNotUdstillet(): void
    {
        $this->assertSame(['available' => 0, 'udstillet' => false], self::plain([1 => 1000])[3]);
        $this->assertSame(['available' => 0, 'udstillet' => false], self::plain([3 => 500])[3]);
    }

    public function testK60TheVirtualLagerIsNeverIncluded(): void
    {
        $this->assertArrayNotHasKey(5, self::plain([1 => 1000, 5 => 9000]));
        $this->assertSame([1 => 0, 2 => 0, 3 => 0], self::available(self::set([self::X => [5 => 9000], self::Y => [5 => 9000]])));
    }

    public function testRoleIngenIsNeverSentAndCountsForNothing(): void
    {
        $this->assertArrayNotHasKey(4, self::plain([4 => 9000]));
        $this->assertSame([1 => 0, 2 => 0, 3 => 0], self::available(self::set([self::X => [4 => 9000], self::Y => [4 => 9000]])));
    }

    public function testTheConfigHelperMarksTheVirtualLager(): void
    {
        $rows = [['lager' => '1', 'rolle' => 'saelgbar', 'prioritet' => '1'], ['lager' => '5', 'rolle' => 'saelgbar', 'prioritet' => '2']];

        $lagre = shopify_availability_lagre(['lagre' => $rows, 'split_set_lager' => null, 'virtual_lager' => 5]);
        $out = shopify_availability(['vare_id' => self::ITEM], $lagre, [self::ITEM => [1 => 1000, 5 => 9000]], [], null);

        $this->assertSame([1 => 1], self::available($out), 'database rows work as they come');
        $this->assertFalse(shopify_availability_lagre(['lagre' => $rows, 'virtual_lager' => null])[1]['virtual']);
    }

    public function testASetWithTwoComponentsShowsTheCompleteSetsOfEachLager(): void
    {
        $out = self::set([self::X => [1 => 5000, 2 => 1000], self::Y => [1 => 4000, 2 => 2000]]);

        $this->assertSame([1 => 2, 2 => 1, 3 => 0], self::available($out));
    }

    public function testTheK55WorkedExampleShowsThreeSetsWithTheSurplusOnTheSplitLager(): void
    {
        $stock = [self::X => [1 => 3000], self::Y => [1 => 2000, 2 => 6000]];

        $this->assertSame([1 => 1, 2 => 2, 3 => 0], self::available(self::set($stock, 2)), 'Mårum as lager 2');
        $this->assertSame([1 => 3, 2 => 0, 3 => 0], self::available(self::set($stock, 1)), 'Mårum as lager 1');
    }

    public function testWithoutASplitLagerTheSurplusGoesOnTheFirstSellableByPriority(): void
    {
        $stock = [self::X => [1 => 3000], self::Y => [1 => 2000, 2 => 6000]];

        $this->assertSame([1 => 3, 2 => 0, 3 => 0], self::available(self::set($stock)));
    }

    public function testDisplayStockIsNeverPartOfASetButSaysItStandsThere(): void
    {
        $out = self::set([self::X => [1 => 1000, 3 => 1000], self::Y => [2 => 2000, 3 => 2000]], 2);

        $this->assertSame([1 => 0, 2 => 1, 3 => 0], self::available($out));
        $this->assertTrue($out[3]['udstillet']);
        $onlyWithDisplay = self::set([self::X => [3 => 1000], self::Y => [1 => 2000]], 2);
        $this->assertSame([1 => 0, 2 => 0, 3 => 0], self::available($onlyWithDisplay));
    }

    public function testAComponentOnTwoBomOccurrencesIsCountedTogether(): void
    {
        $bom = [
            ['bom_id' => 1, 'vare_id' => self::X, 'quantity_milli' => 1000],
            ['bom_id' => 2, 'vare_id' => self::X, 'quantity_milli' => 1000],
        ];

        $this->assertSame(1, self::set([self::X => [1 => 3000]], null, $bom)[1]['available']);
    }

    public function testFractionalComponentQuantitiesGiveExactSetCounts(): void
    {
        $bom = [['bom_id' => 1, 'vare_id' => self::X, 'quantity_milli' => 100]];

        // 0.3 / 0.1 is 2.9999... in floats
        $this->assertSame(3, self::set([self::X => [1 => 300]], null, $bom)[1]['available']);
    }

    public function testNegativeComponentStockDoesNotEatFromAnotherLager(): void
    {
        $stock = [self::X => [1 => 2000, 2 => -5000], self::Y => [1 => 4000]];

        $this->assertSame([1 => 2, 2 => 0, 3 => 0], self::available(self::set($stock)));
    }

    public function testASplitLagerThatIsNotSellableIsRefusedOnlyWhenItIsNeeded(): void
    {
        $split = [self::X => [1 => 1000], self::Y => [2 => 2000]];
        $whole = [self::X => [1 => 1000], self::Y => [1 => 2000]];

        $this->assertSame(1, self::set($whole, 3)[1]['available'], 'no surplus, nothing to place');
        foreach ([3, 4, 5, 99] as $lager) {
            try {
                self::set($split, $lager);
                $this->fail("surplus on lager $lager");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString("split-set lager $lager", $e->getMessage());
            }
        }
    }

    public function testTheTotalIsNeverMoreThanTheSellableLagreCanAssemble(): void
    {
        mt_srand(755);
        for ($i = 0; $i < 500; $i++) {
            $stock = [];
            foreach ([self::X, self::Y] as $vare) {
                foreach ([1, 2, 3, 4, 5] as $lager) {
                    $stock[$vare][$lager] = mt_rand(-2000, 9000);
                }
            }
            $x = max(0, $stock[self::X][1]) + max(0, $stock[self::X][2]);
            $y = max(0, $stock[self::Y][1]) + max(0, $stock[self::Y][2]);

            $total = array_sum(self::available(self::set($stock, mt_rand(1, 2))));

            $this->assertSame(min(intdiv($x, 1000), intdiv($y, 2000)), $total, json_encode($stock));
        }
    }

    public function testNoSellableLagerPublishesNothingForASet(): void
    {
        $lagre = [['lager' => 3, 'rolle' => 'udstilling']];

        $out = shopify_availability(['vare_id' => self::SET], $lagre, [self::X => [3 => 9000], self::Y => [3 => 9000]], self::bom(), null);

        $this->assertSame([3 => ['available' => 0, 'udstillet' => true]], $out);
    }

    #[DataProvider('malformed')]
    public function testMalformedInputIsRefused(array $item, array $lagre, array $stock, array $bom, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        shopify_availability($item, $lagre, $stock, $bom, null);
    }

    /** @return array<string, array{array, array, array, array, string}> */
    public static function malformed(): array
    {
        $one = [['lager' => 1, 'rolle' => 'saelgbar', 'prioritet' => 1]];
        return [
            'no item id' => [[], $one, [], [], 'item id'],
            'a lager twice' => [['vare_id' => 1], [$one[0], $one[0]], [], [], 'listed twice'],
            'an unknown role' => [['vare_id' => 1], [['lager' => 1, 'rolle' => 'x']], [], [], 'unknown role'],
            'sellable without a priority' => [['vare_id' => 1], [['lager' => 1, 'rolle' => 'saelgbar']], [], [], 'priority'],
            'stock that is no list per lager' => [['vare_id' => 1], $one, [1 => 5000], [], 'not a list per lager'],
            'float stock' => [['vare_id' => 1], $one, [1 => [1 => 1.5]], [], 'must be an integer'],
            'a BOM quantity of zero' => [['vare_id' => 1], $one, [], [['bom_id' => 1, 'vare_id' => 2, 'quantity_milli' => 0]], 'no positive quantity'],
        ];
    }
}
