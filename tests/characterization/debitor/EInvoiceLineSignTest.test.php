<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/stdFunc/einvoiceLineSign.php';

// 20261102 CL/MMR SST-746 Pins the quantity and unit price an order line is sent to EasyUBL with.
//                 MEDSHOP's discount lines (999rabat, 1 x -600) were rejected: a negative price is not allowed (BR-27), and sent as an allowance they failed BR-S-08 / UNCL 5189.
//                 A negative quantity at a positive price is accepted, so the price is never negative and the line amount keeps its sign.
//                 The figures are MEDSHOP's faktura 236656, faktura 239960 and kreditnota 240876, which credits 236656.
//                 The last tests check that sendInvoice() uses the helper and no longer flags lines as allowances.
final class EInvoiceLineSignTest extends TestCase
{
    private const API = __DIR__ . '/../../../debitor/api.php';

    /** @return array<string, array{float, float, bool, float, float}> antal, pris, credit note, quantity sent, price sent */
    public static function lines(): array
    {
        return [
            'invoice item'                        => [1.0, 17196.0, false, 1.0, 17196.0],
            'invoice discount 999rabat'           => [1.0, -600.0, false, -1.0, 600.0],
            'invoice discount 239960'             => [1.0, -1500.0, false, -1.0, 1500.0],
            'invoice return line'                 => [-1.0, 1500.0, false, -1.0, 1500.0],
            'invoice negative both'               => [-2.0, -10.0, false, 2.0, 10.0],
            'invoice text line'                   => [0.0, 0.0, false, 0.0, 0.0],
            'credit note returned item'           => [-2.0, 100.0, true, 2.0, 100.0],
            'credit note discount (240876)'       => [-1.0, -600.0, true, -1.0, 600.0],
            'credit note line taken back'         => [1.0, 44.4, true, -1.0, 44.4],
            'credit note text line'               => [0.0, 0.0, true, 0.0, 0.0],
            'credit note free line'               => [-1.0, 0.0, true, 1.0, 0.0],
        ];
    }

    #[DataProvider('lines')]
    public function testQuantityAndPrice(float $antal, float $pris, bool $credit, float $quantity, float $price): void
    {
        $r = einvoice_line_quantity_price($antal, $pris, $credit);
        self::assertSame($quantity, $r['quantity']);
        self::assertSame($price, $r['price']);
        self::assertGreaterThanOrEqual(0.0, $r['price'], 'the unit price is never negative (BR-27)');
    }

    #[DataProvider('lines')]
    public function testLineAmountKeepsItsSign(float $antal, float $pris, bool $credit): void
    {
        $r = einvoice_line_quantity_price($antal, $pris, $credit);
        $booked = $antal * $pris;
        self::assertEqualsWithDelta($credit ? -$booked : $booked, $r['quantity'] * $r['price'], 0.000001);
    }

    public function testDatabaseStringsAreAccepted(): void
    {
        self::assertSame(array('quantity' => -1.0, 'price' => 600.0), einvoice_line_quantity_price('1.000', '-600.000', false));
    }

    public function testZeroIsNeverSentAsMinusZero(): void
    {
        self::assertSame('{"quantity":0,"price":5}', json_encode(einvoice_line_quantity_price(0.0, -5.0, false)));
        self::assertSame('{"quantity":0,"price":0}', json_encode(einvoice_line_quantity_price('0.000', '0.000', true)));
    }

    public function testInvoice236656TotalsStillReconcile(): void
    {
        // 236656: 1 x 17196, a text line, 1 x -600 (999rabat), 1 x 49. ordrer.sum 16645, moms 4161.25.
        $sum = 0.0;
        foreach (array(array(1, 17196), array(0, 0), array(1, -600), array(1, 49)) as $l) {
            $r = einvoice_line_quantity_price($l[0], $l[1], false);
            $sum += $r['quantity'] * $r['price'];
        }
        self::assertEqualsWithDelta(16645.0, $sum, 0.001);
        self::assertEqualsWithDelta(4161.25, $sum * 0.25, 0.001);
    }

    public function testCreditNote240876CreditsTheDiscountedAmount(): void
    {
        // 240876 credits 236656: -1 x 17196, -1 x -600 (999rabat), -1 x 49. ordrer.sum -16645.
        $sum = 0.0;
        foreach (array(array(-1, 17196), array(-1, -600), array(-1, 49)) as $l) {
            $r = einvoice_line_quantity_price($l[0], $l[1], true);
            $sum += $r['quantity'] * $r['price'];
        }
        self::assertEqualsWithDelta(16645.0, $sum, 0.001, 'before SST-746 abs() made it 17845');
    }

    public function testSendInvoiceUsesTheHelper(): void
    {
        $src = (string) file_get_contents(self::API);
        self::assertStringContainsString('einvoiceLineSign.php', $src);
        self::assertMatchesRegularExpression('/einvoice_line_quantity_price\(\$res\["antal"\], \$res\["pris"\], \$creditNote == "Cre"\)/', $src);
        self::assertStringNotContainsString('$res["antal"] = abs(', $src, 'abs() on a credit note turned its discount into a charge');
    }

    public function testNoLineIsFlaggedAsAnAllowance(): void
    {
        $src = (string) file_get_contents(self::API);
        self::assertSame(1, preg_match_all('/"isAllowanceCharge" => ([^,]+),/', $src, $m));
        self::assertSame('false', trim($m[1][0]));
    }
}
