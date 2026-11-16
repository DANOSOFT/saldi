<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/stdFunc/einvoiceLineSign.php';

// 20261102 CL/MMR SST-746 Pins the quantity and unit price an order line is sent to EasyUBL with.
//                 A customer's discount lines (1 x a negative price) were rejected: a negative price is not allowed (BR-27), and sent as an allowance they failed BR-S-08 / UNCL 5189.
//                 A negative quantity at a positive price is accepted, so the price is never negative and the line amount keeps its sign.
//                 The figures are synthetic: an invoice with a discount line and the credit note that credits it.
//                 The last tests check that sendInvoice() uses the helper and no longer flags lines as allowances.
// 20261104 CL/MMR SST-746 Review (PR #998): test and data-set names no longer carry a customer or real invoice numbers.
//                 The amounts are synthetic as well.
final class EInvoiceLineSignTest extends TestCase
{
    private const API = __DIR__ . '/../../../debitor/api.php';

    /** @return array<string, array{float, float, bool, float, float}> antal, pris, credit note, quantity sent, price sent */
    public static function lines(): array
    {
        return [
            'invoice item'                        => [1.0, 1000.0, false, 1.0, 1000.0],
            'invoice discount line'               => [1.0, -100.0, false, -1.0, 100.0],
            'invoice larger discount'             => [1.0, -250.0, false, -1.0, 250.0],
            'invoice return line'                 => [-1.0, 250.0, false, -1.0, 250.0],
            'invoice negative both'               => [-2.0, -10.0, false, 2.0, 10.0],
            'invoice text line'                   => [0.0, 0.0, false, 0.0, 0.0],
            'credit note returned item'           => [-2.0, 100.0, true, 2.0, 100.0],
            'credit note discount'                => [-1.0, -100.0, true, -1.0, 100.0],
            'credit note line taken back'         => [1.0, 20.5, true, -1.0, 20.5],
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
        self::assertSame(array('quantity' => -1.0, 'price' => 100.0), einvoice_line_quantity_price('1.000', '-100.000', false));
    }

    public function testZeroIsNeverSentAsMinusZero(): void
    {
        self::assertSame('{"quantity":0,"price":5}', json_encode(einvoice_line_quantity_price(0.0, -5.0, false)));
        self::assertSame('{"quantity":0,"price":0}', json_encode(einvoice_line_quantity_price('0.000', '0.000', true)));
    }

    public function testDiscountedInvoiceTotalsStillReconcile(): void
    {
        // An invoice: 1 x 1000, a text line, 1 x -100 (discount), 1 x 50. ordrer.sum 950, moms 237.50.
        $sum = 0.0;
        foreach (array(array(1, 1000), array(0, 0), array(1, -100), array(1, 50)) as $l) {
            $r = einvoice_line_quantity_price($l[0], $l[1], false);
            $sum += $r['quantity'] * $r['price'];
        }
        self::assertEqualsWithDelta(950.0, $sum, 0.001);
        self::assertEqualsWithDelta(237.5, $sum * 0.25, 0.001);
    }

    public function testCreditNoteCreditsTheDiscountedAmount(): void
    {
        // The credit note for that invoice: -1 x 1000, -1 x -100 (discount), -1 x 50. ordrer.sum -950.
        $sum = 0.0;
        foreach (array(array(-1, 1000), array(-1, -100), array(-1, 50)) as $l) {
            $r = einvoice_line_quantity_price($l[0], $l[1], true);
            $sum += $r['quantity'] * $r['price'];
        }
        self::assertEqualsWithDelta(950.0, $sum, 0.001, 'before SST-746 abs() made it 1150');
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
