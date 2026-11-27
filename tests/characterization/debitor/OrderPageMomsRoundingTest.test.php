<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/std_func.php';

// 20261027 CL/MMR SST-902 Created. After Gem the order page computed the moms of the net rounded to øre, so a webshop price without moms at 3 decimals
//                 (MEDSHOP order 2634097: 2 x 139,648) showed and stored moms 69,825 (69,83 / 349,13) while the invoice posts 69,82 / 349,12.
//                 The two statements in debitor/ordre.php are lifted and run here next to the invoice's rule from momsupdat() in includes/ordrefunc.php.
final class OrderPageMomsRoundingTest extends TestCase
{
    private const ORDRE = __DIR__ . '/../../../debitor/ordre.php';

    /** @return array<string, array{float, float}> */
    public static function nets(): array
    {
        return [
            'MEDSHOP 2 x 139,648 (2634097)' => [279.296, 69.82],
            'net with 3rd decimal 5'        => [2082.333, 520.58],
            'order 167841'                  => [11721.496, 2930.37],
            'whole øre, moms ends in ,xx5'  => [179.98, 45.00],
            'whole øre'                     => [418.95, 104.74],
            'zero'                          => [0.0, 0.0],
        ];
    }

    /**
     * The order page's moms after a save equals the invoice's moms: of the unrounded net, rounded to øre.
     */
    #[DataProvider('nets')]
    public function testTheOrderPageMomsMatchesTheInvoice(float $lineSum, float $expectedMoms): void
    {
        $src = (string)file_get_contents(self::ORDRE);
        $round = self::line($src, '$sumBeforeRounding = $sum;');
        $vat = self::line($src, 'if (!$blandet_moms && !$incl_moms) $moms =');

        $sum = $lineSum;
        $momssats = 25;
        $blandet_moms = 0;
        $incl_moms = '';
        $moms = 0;
        eval($round . "\n" . '$sum = afrund($sum, 2);' . "\n" . $vat);

        $invoiceMoms = afrund($lineSum / 100 * $momssats, 2); // momsupdat(), includes/ordrefunc.php
        self::assertSame($invoiceMoms, round((float)$moms, 3), 'the order page and the invoice disagree on the moms');
        self::assertSame($expectedMoms, round((float)$moms, 2));
    }

    public function testTheProcentTillaegIsAddedToTheUnroundedNetToo(): void
    {
        $src = (string)file_get_contents(self::ORDRE);
        self::assertMatchesRegularExpression('/\$sum \+= \$tillag;\s*\$sumBeforeRounding \+= \$tillag;/', $src,
            'a procenttillæg no longer reaches the net the moms is computed from');
    }

    /** The single source line that contains $needle, comments stripped; fails when it is not exactly one line. */
    private static function line(string $src, string $needle): string
    {
        $hits = array_values(array_filter(explode("\n", $src), static fn ($l) => strpos($l, $needle) !== false && strpos(ltrim($l), '//') !== 0));
        self::assertCount(1, $hits, "expected one line with $needle in debitor/ordre.php");
        return preg_replace('/\s#.*$/', '', $hits[0]);
    }
}
