<?php
// 20260911 CL/Sawaneh Cover tools/shop_order_reconcile.php: the replay must mirror fakturer_ordre's
//                     amount check, reproduce the SST-187 rejection (743/186 vs 185.75) and keep a
//                     foreign-currency price intact through the DKK round trip (SST-406) (SST-768/JOB-117).

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/tools/shop_order_reconcile.php';

final class ShopOrderReconcileTest extends TestCase
{
    public function testSaldiTotalsMirrorFakturerOrdre(): void
    {
        $t = saldi_totals([
            ['antal' => 2, 'pris' => 35.00, 'rabat' => 0, 'momssats' => 25],
            ['antal' => 1, 'pris' => 350.75, 'rabat' => 10, 'momssats' => 25],
        ]);
        self::assertEqualsWithDelta(70 + 315.675, $t['varesum'], 0.0001);
        self::assertEqualsWithDelta(17.5 + 78.919, $t['varemoms'], 0.0001);
    }

    public function testSst187TotalsAreRejectedAndExplainedByWholeUnitGrossRounding(): void
    {
        $requests = [
            parse_request('action=insert_shop_order&shop_ordre_id=1&valuta=DKK&momssats=25&nettosum=743&momssum=186&ekstra2=929.000000&key=secret'),
            parse_request('action=insert_shop_orderline&varenr=A&antal=1&pris=743&rabat=0&momsfri='),
        ];
        $report = reconcile($requests);
        self::assertStringContainsString('RESULT: REJECTED', $report);
        self::assertStringContainsString('Error in amount (743+186) vs. item amount (743+185.75)', $report);
        self::assertStringContainsString('= 0.0000 -> ok', $report);
        self::assertMatchesRegularExpression('/=> E gross total rounded to whole unit/', $report);
        self::assertStringNotContainsString('secret', $report);
    }

    public function testMatchingTotalsAreAccepted(): void
    {
        $requests = [
            parse_request('action=insert_shop_order&shop_ordre_id=2&valuta=DKK&nettosum=385.675&momssum=96.419&momssats=25'),
            parse_request('action=insert_shop_orderline&varenr=A&antal=2&pris=35&rabat=0'),
            parse_request('action=insert_shop_orderline&varenr=B&antal=1&pris=350.75&rabat=10'),
        ];
        self::assertStringContainsString('RESULT: accepted', reconcile($requests));
    }

    public function testForeignCurrencyPriceSurvivesTheDkkRoundTrip(): void
    {
        self::assertSame(129.5, stored_line_price(129.5, 68.53));
        self::assertSame(99.99, stored_line_price(99.99, 745.12));
        self::assertSame(12.345, stored_line_price(12.345, 100));
    }

    public function testVatFreeLineCarriesNoVat(): void
    {
        $requests = [
            parse_request('action=insert_shop_order&shop_ordre_id=3&valuta=NOK&valutakurs=68.53&nettosum=200&momssum=0&momssats=25'),
            parse_request('action=insert_shop_orderline&varenr=N&antal=1&pris=200&rabat=0&momsfri=on'),
        ];
        $report = reconcile($requests);
        self::assertStringContainsString('RESULT: accepted', $report);
        self::assertStringContainsString('varemoms 0.0000', $report);
    }
}
