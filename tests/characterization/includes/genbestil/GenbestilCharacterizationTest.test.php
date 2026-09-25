<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CHARACTERIZATION suite for genbestil_nettobeholdning()/beregn_genbestil()
 * (includes/std_func.php), pinning the MB-35 fix.
 *
 * lager/varer.php's reorder-suggestion code (function udskriv()) had two
 * branches computing the same thing differently: one subtracted existing
 * purchase proposals/orders ($i_forslag/$bestilt) from both the "does this
 * item need reordering" check and the suggested quantity, the other used
 * neither anywhere. An item already covered by a pending purchase order
 * still got flagged and suggested for the full gap up to max - doubling the
 * order once the pending one also arrives. Both branches now call these two
 * shared functions instead of repeating the formula inline.
 */
final class GenbestilCharacterizationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ob_start();
        require_once dirname(__DIR__, 4) . '/includes/std_func.php';
        ob_end_clean();
        self::assertTrue(function_exists('genbestil_nettobeholdning'), 'genbestil_nettobeholdning() not defined after include');
        self::assertTrue(function_exists('beregn_genbestil'), 'beregn_genbestil() not defined after include');
    }

    /**
     * @return array<string, array{float, float, float, float, float, float}>
     *   [beholdning, i_ordre, i_forslag, bestilt, expectedNetto, expectedGenbestil]
     */
    public static function scenarios(): array
    {
        return [
            // MB-35 ticket's own worked example: stock 9, min 5, max 10, one open sales
            // order of 8, no existing purchase proposals/orders. Netto = 9-8 = 1, which is
            // below min (5), so a reorder of max(10) - netto(1) = 9 is suggested.
            'ticket worked example (9/5/10/8 -> 9)' => [9, 8, 0, 0, 1, 9],

            // Same stock/order as above, but 8 units are already on an open purchase
            // order (bestilt=8): netto = 9-8+0+8 = 9, so only 1 more is actually needed
            // (max 10 - netto 9). The old naive branch ignored $bestilt entirely and would
            // still have suggested 9 here, silently doubling the pending purchase.
            'existing purchase order already covers the gap' => [9, 8, 0, 8, 9, 1],

            // Same again, but the 8 units are an unconfirmed purchase proposal
            // (i_forslag=8) rather than a placed order - same effect, must net out the
            // same way since the formula treats i_forslag and bestilt identically.
            'existing purchase proposal already covers the gap' => [9, 8, 8, 0, 9, 1],

            // No open sales order and stock already at max: netto = max, so max - netto = 0.
            'no deficit suggests nothing' => [10, 0, 0, 0, 10, 0],

            // Netto exceeds max (e.g. a large incoming purchase order on top of healthy
            // stock): the raw formula would go negative, must clamp to 0, never a negative
            // reorder quantity.
            'netto above max clamps to zero, not negative' => [10, 0, 0, 5, 15, 0],
        ];
    }

    #[DataProvider('scenarios')]
    public function testNettobeholdning(float $beholdning, float $iOrdre, float $iForslag, float $bestilt, float $expectedNetto, float $expectedGenbestil): void
    {
        self::assertEquals($expectedNetto, genbestil_nettobeholdning($beholdning, $iOrdre, $iForslag, $bestilt));
    }

    #[DataProvider('scenarios')]
    public function testBeregnGenbestil(float $beholdning, float $iOrdre, float $iForslag, float $bestilt, float $expectedNetto, float $expectedGenbestil): void
    {
        $maxLager = 10;
        self::assertEquals($expectedGenbestil, beregn_genbestil($maxLager, $beholdning, $iOrdre, $iForslag, $bestilt));
    }
}
