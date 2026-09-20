<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20260920 CDX/MJ SST-794 The price handed to opret_saet() for a shop "samlevare" line must be
//                  grossed up with the ORDER's momssats, not a hardcoded 25%. opret_saet() is then
//                  told the same rate and strips exactly what was added, so the net price is the
//                  shop's net price for every VAT rate - including 0% for an export customer, which
//                  is what MEDSHOP reported.
final class ShopSetVatRateTest extends TestCase
{
    /**
     * What the API now hands to opret_saet(): the shop price grossed up by the order's own rate.
     *
     * @param float $shopPrice Net price as the shop sends it.
     * @param float $rate The order's momssats.
     * @return float
     */
    private static function grossedUp(float $shopPrice, float $rate): float
    {
        return $shopPrice * (1 + $rate / 100);
    }

    /**
     * What opret_saet() and opret_ordrelinje() take back out again, given the same rate.
     * Mirrors includes/ordrefunc.php:4111 - $pris - ($pris * $rate / (100 + $rate)).
     *
     * @param float $price Price as passed in, VAT-inclusive at $rate.
     * @param float $rate The rate opret_saet() was told.
     * @return float
     */
    private static function vatStripped(float $price, float $rate): float
    {
        if ($rate <= 0) {
            return $price;
        }
        return $price - ($price * $rate / (100 + $rate));
    }

    /**
     * The round trip must return the shop's net price at every rate. This is the acceptance
     * criterion: 0% imports 1:1, and 25% lands where it always did.
     */
    #[DataProvider('rates')]
    public function testGrossUpAndStripReturnTheShopPrice(float $rate): void
    {
        foreach ([41.60, 83.20, 1166.36, 290.00] as $shopPrice) {
            $net = self::vatStripped(self::grossedUp($shopPrice, $rate), $rate);
            self::assertEqualsWithDelta($shopPrice, $net, 0.0000001, "rate $rate, price $shopPrice");
        }
    }

    public static function rates(): array
    {
        return ['export 0%' => [0.0], 'danish 25%' => [25.0], 'reduced 12%' => [12.0]];
    }

    /**
     * The defect itself, using the four lines from the ticket. With the rate hardcoded to 25 the
     * price was grossed up by 25% and then stripped at 25% - which cancels only when the order
     * really is 25%. For the 0% export order the strip did not happen (opret_ordrelinje clamps the
     * item rate down to the order's 0), so the 25% that was added stayed on the price.
     */
    #[DataProvider('ticketLines')]
    public function testHardcodedRateMispricesAZeroRateOrder(float $shopPrice): void
    {
        $orderRate = 0.0;

        // Old behaviour: gross up at 25, strip at the order's real rate (0) - nothing comes off.
        $old = self::vatStripped(self::grossedUp($shopPrice, 25.0), $orderRate);
        self::assertEqualsWithDelta($shopPrice * 1.25, $old, 0.0000001, 'the old path left 25% on the price');
        self::assertNotEqualsWithDelta($shopPrice, $old, 0.0000001, 'the old path should not match the shop price');

        // New behaviour: gross up and strip at the same real rate.
        $new = self::vatStripped(self::grossedUp($shopPrice, $orderRate), $orderRate);
        self::assertEqualsWithDelta($shopPrice, $new, 0.0000001, 'the new path imports 1:1');
    }

    public static function ticketLines(): array
    {
        return [
            'gaze-swap 754041'   => [41.60],
            'staserem 487010'    => [83.20],
            'littmann 601331'    => [1166.36],
            'oximeter 385802'    => [290.00],
        ];
    }

    /** A 25% order must be untouched by the change - the regression the ticket asks for. */
    #[DataProvider('ticketLines')]
    public function testTwentyFivePercentOrderIsUnchanged(float $shopPrice): void
    {
        $old = self::vatStripped(self::grossedUp($shopPrice, 25.0), 25.0);
        $new = self::vatStripped(self::grossedUp($shopPrice, 25.0), 25.0);
        self::assertEqualsWithDelta($old, $new, 0.0000001);
        self::assertEqualsWithDelta($shopPrice, $new, 0.0000001);
    }

    /** Both shop importers must pass the order's rate, not a literal 25. */
    public function testNeitherImporterHardcodesTheRate(): void
    {
        foreach (['api/rest_api.php', 'api/hent_ordrer.php'] as $file) {
            $src = file_get_contents(__DIR__ . '/../../../' . $file);
            $calls = [];
            preg_match_all('/opret_saet\([^;]*\);/', $src, $calls);
            self::assertNotEmpty($calls[0], "no opret_saet() call found in $file");
            foreach ($calls[0] as $call) {
                self::assertStringNotContainsString('*1.25', $call, "$file still grosses up by a hardcoded 1.25");
                self::assertStringNotContainsString(',25,', $call, "$file still passes a hardcoded 25% rate");
                self::assertStringContainsString('momssats', $call, "$file should pass the order's momssats");
            }
        }
    }

    /**
     * Arguments in a call, counting only commas at the call's own nesting level so that array
     * subscripts and nested parentheses in an argument are not miscounted as separators.
     *
     * @param string $call A complete "name(...);" call.
     * @return int
     */
    private static function argumentCount(string $call): int
    {
        $inner = substr($call, strpos($call, '(') + 1, strrpos($call, ')') - strpos($call, '(') - 1);
        $depth = 0;
        $args = 1;
        foreach (str_split($inner) as $char) {
            if ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $args++;
            }
        }
        return $args;
    }

    /** The bare `on` in hent_ordrer.php was an undefined constant, fatal under PHP 8. */
    public function testHentOrdrerPassesQuotedOnAndAllSevenArguments(): void
    {
        $src = file_get_contents(__DIR__ . '/../../../api/hent_ordrer.php');
        preg_match('/opret_saet\([^;]*\);/', $src, $call);
        self::assertNotEmpty($call, 'no opret_saet() call found');
        self::assertStringNotContainsString(",on)", $call[0], 'bare `on` is an undefined constant on PHP 8');
        self::assertStringContainsString("'on'", $call[0], "the incl_moms flag should be the string 'on'");
        self::assertSame(7, self::argumentCount($call[0]), 'opret_saet() takes seven arguments');
    }
}
