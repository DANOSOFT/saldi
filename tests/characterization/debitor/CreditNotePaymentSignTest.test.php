<?php
// tests/characterization/debitor/CreditNotePaymentSignTest.test.php
//
// SST-1018: with Brug samlet pris (POS/2 box12) felt_2 and felt_4 on an order are the two payment amounts. Kredit on a
// posted order inserted the credit note with the order's positive amounts, because the sign change in debitor/ordre.php
// ran only after the insert. On a cash-paid order the credit note got cash +3.999, the page computed the card amount as
// the total minus that (-7.998), and Kreditér booked both, since they add up to the total.
// The helpers in debitor/orderIncludes/orderPayment.php are tested here, and the page is checked to call them
// before the credit note is inserted and before Fakturér/Kreditér.
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

    /** @return array<string, array{mixed, mixed, bool}> amount, credit note total, stopped */
    public static function creditNotePayments(): array
    {
        return [
            'positive cash on a negative credit note' => [3999.0, -3999.0, true],
            'positive cash as text'                   => ['2700', '-4898.000', true],
            'positive card on a negative credit note' => [1.0, -999.0, true],
            'negative payment'                        => [-3999.0, -3999.0, false],
            'zero payment'                            => ['0.00', -3999.0, false],
            'less than half an øre'                   => [0.004, -3999.0, false],
            'credit note with a positive total'       => [15000.0, 15000.0, false],
            'credit note with a zero total'           => [100.0, 0.0, false],
            'empty field'                             => ['', -3999.0, false],
        ];
    }

    #[DataProvider('creditNotePayments')]
    public function testAPaymentWithASalesSignIsFound(mixed $amount, mixed $total, bool $wrong): void
    {
        self::assertSame($wrong, credit_note_payment_sign_wrong($amount, $total));
    }

    /** @return array<string, array{string, string, string, mixed, mixed, float, string, bool, ?string}> art, felt_1, betalingsbet, felt_2, felt_4, sum, moms, stop, alert */
    public static function invoiceChecks(): array
    {
        return [
            'credit note, cash +3.999 and card -7.998 add up' => ['DK', 'Kortbetaling', 'Kontant', -7998.0, 3999.0, -3199.2, '-799.800', true, 'sign'],
            'credit note, cash +3.999 and card 0'             => ['DK', 'Kortbetaling', 'Kontant', '0.00', 3999.0, -3199.2, '-799.800', true, 'sign'],
            'credit note, cash -3.999 and card 0'             => ['DK', 'Kortbetaling', 'Kontant', '0.00', -3999.0, -3199.2, '-799.800', false, null],
            'credit note, amounts do not add up'              => ['DK', 'Kortbetaling', 'Kontant', -1000.0, -1000.0, -3199.2, '-799.800', true, 'sum'],
            'credit note with a positive total'               => ['DK', 'Kortbetaling', 'Kontant', 15000.0, '0.00', 12000.0, '3000.000', false, null],
            'sale, card and cash add up'                      => ['DO', 'MASTERCARD', 'Kontant', 699.0, 1000.0, 1359.2, '339.800', false, null],
            'sale, amounts do not add up'                     => ['DO', 'MASTERCARD', 'Kontant', 699.0, '0.00', 1359.2, '339.800', true, 'sum'],
            'sale on Konto with Kontant terms'                => ['DO', 'Konto', 'Kontant', 1699.0, '0.00', 1359.2, '339.800', true, null],
        ];
    }

    #[DataProvider('invoiceChecks')]
    public function testInvoicingStopsOnAWrongSignOrSum(string $art, string $felt1, string $betalingsbet, mixed $felt2, mixed $felt4, float $sum, string $moms, bool $stop, ?string $alert): void
    {
        $check = order_invoice_payment_check($art, $felt1, $betalingsbet, $felt2, $felt4, $sum, $moms);

        self::assertSame($stop, $check['stop']);
        self::assertSame($alert, $check['alert']);
    }

    public function testTheSumMessageKeepsItsNumbers(): void
    {
        $check = order_invoice_payment_check('DK', 'Kortbetaling', 'Kontant', -1000.0, -1000.0, -3199.2, '-799.800');

        self::assertSame('Betalt beløb (-2000 stemmer ikke med sum -3999)', "Betalt beløb ($check[paid] stemmer ikke med sum $check[total])");
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

    public function testFakturerAndKrediterUseThePaymentCheck(): void
    {
        $src = (string)file_get_contents(self::ORDRE);
        $branch = self::pos($src, "if (\$b_submit == 'doInvoice' && \$vis_saet) {");
        $check = self::pos($src, "\$paymentCheck = order_invoice_payment_check(\$art, \$felt_1, \$betalingsbet, \$felt_2, \$felt_4, \$sum, \$r['moms']);");
        $stop = self::pos($src, "if (\$paymentCheck['stop']) \$b_submit = 'Gem';");

        self::assertLessThan($check, $branch);
        self::assertLessThan($stop, $check);
        self::assertStringContainsString("include_once(__DIR__ . '/orderIncludes/orderPayment.php');", $src);
        self::assertStringNotContainsString("afrund(\$sum + \$r['moms'] - (\$felt_2 + \$felt_4), 2)", $src, 'the old check is no longer in the page');
    }

    /** Position of the one occurrence of $needle in debitor/ordre.php. */
    private static function pos(string $src, string $needle): int
    {
        self::assertSame(1, substr_count($src, $needle), "expected one occurrence of: $needle");
        return (int)strpos($src, $needle);
    }
}
