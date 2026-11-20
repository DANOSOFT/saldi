<?php
// tests/characterization/debitor/CreditNotePaymentSignTest.test.php
//
// SST-1018: with Brug samlet pris (POS/2 box12) felt_2 and felt_4 on an order are the two payment amounts. Kredit on a
// posted order inserted the credit note with the order's positive amounts, because the sign change in debitor/ordre.php
// ran only after the insert. On a cash-paid order the credit note got cash +3.999, the page computed the card amount as
// the total minus that (-7.998), and Kreditér booked both, since they add up to the total.
// The helper in debitor/orderIncludes/orderPayment.php is tested here, the page is checked to call it before the
// credit note is inserted, and the unchanged payment check is checked to let mixed refunds that add up through.
//
// History:
// 20261117 CL/MMR SST-1018 Created.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CreditNotePaymentSignTest extends TestCase
{
    private const HELPER = __DIR__ . '/../../../debitor/orderIncludes/orderPayment.php';
    private const ORDRE = __DIR__ . '/../../../debitor/ordre.php';

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('afrund')) {
            // Rounds like afrund() in includes/std_func.php, which the order page has loaded before the helper runs.
            eval('function afrund($tal, $decimaler) { return round((float)$tal, (int)$decimaler); }');
        }
        require_once self::HELPER;
    }

    /** @return array<string, array{mixed, mixed}> amount on the credited order, amount on the credit note */
    public static function creditedPayments(): array
    {
        return [
            'cash from usdecimal()'          => [3999.0, -3999.0],
            'card as stored text'            => ['13999', -13999.0],
            'amount with øre'                => [1457.28, -1457.28],
            'negative amount (change given)' => [-1.0, 1.0],
            'zero from usdecimal()'          => ['0.00', '0.00'],
            'zero as number'                 => [0, 0],
            'empty field'                    => ['', ''],
            'text in the field'              => ['Kontant', 'Kontant'],
        ];
    }

    #[DataProvider('creditedPayments')]
    public function testACreditNotePaysBackWhatTheOrderTookIn(mixed $order, mixed $creditNote): void
    {
        self::assertSame($creditNote, credit_note_payment_amount($order));
    }

    /**
     * The payment check before Fakturér/Kreditér is unchanged: it stops only when the payments do not add up to the
     * total. Mixed refunds that add up (a smaller card payment taken while a larger cash amount is paid back, as used
     * in real credit notes) still go through.
     *
     * @return array<string, array{mixed, mixed, float, string, bool}> felt_2, felt_4, sum, moms, stopped
     */
    public static function paymentChecks(): array
    {
        return [
            'mixed refund, card +1 / cash -1.000'          => [1.0, -1000.0, -799.2, '-199.800', false],
            'mixed refund, card +5.249,20 / cash -5.599,20' => [5249.2, -5599.2, -280.0, '-70.000', false],
            'mixed refund, card +200,20 / cash -1.000'     => [200.2, -1000.0, -639.84, '-159.960', false],
            'mixed refund, card +0,09 / cash -799,99'      => [0.09, -799.99, -639.92, '-159.980', false],
            'cash refund after the fix'                    => ['0.00', -3999.0, -3199.2, '-799.800', false],
            'amounts do not add up'                        => [-1000.0, -1000.0, -3199.2, '-799.800', true],
            'sale that adds up'                            => [699.0, 1000.0, 1359.2, '339.800', false],
        ];
    }

    #[DataProvider('paymentChecks')]
    public function testThePaymentCheckStopsOnlyWhenTheSumIsWrong(mixed $felt_2, mixed $felt_4, float $sum, string $moms, bool $stop): void
    {
        $src = (string)file_get_contents(self::ORDRE);
        $condition = "afrund(\$sum + \$r['moms'] - (\$felt_2 + \$felt_4), 2)";
        self::pos($src, "if ($condition) {");
        $r = ['moms' => $moms];
        $stopped = (bool)eval("return $condition;");
        self::assertSame($stop, $stopped);
    }

    public function testKreditTurnsThePaymentsBeforeTheCreditNoteIsInserted(): void
    {
        $src = (string)file_get_contents(self::ORDRE);
        $kred = self::pos($src, "\$kred_ord_id = \$id;\n\t\t\t\$id = '';");
        $turn = self::pos($src, "\$felt_4 = credit_note_payment_amount(\$felt_4);\n\t\t\t\$creditNotePaymentsTurned = true;");
        $insert = self::pos($src, '# Opretter ny ordre fra konto id');

        self::assertLessThan($turn, $kred, 'the payments are turned in the Kred setup');
        self::assertLessThan($insert, $turn, 'the payments are turned before the credit note is inserted');
        self::assertStringContainsString("if (\$vis_saet && !(\$creditNotePaymentsTurned ?? false)) {", $src, 'the later sign change does not turn them back');
        self::assertStringNotContainsString('$felt_4 *= -1;', $src);
    }

    /** Position of the one occurrence of $needle in debitor/ordre.php. */
    private static function pos(string $src, string $needle): int
    {
        self::assertSame(1, substr_count($src, $needle), "expected one occurrence of: $needle");
        return (int)strpos($src, $needle);
    }
}
