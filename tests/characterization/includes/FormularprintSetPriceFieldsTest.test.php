<?php
// tests/characterization/includes/FormularprintSetPriceFieldsTest.test.php
//
// The order fields $ordre_felt_3 and $ordre_felt_4 on an invoice layout when set prices are shown
// (POS 2 box12, SD-797): felt_4 is the amount, and felt_3 prints only next to an amount that isn't 0.
// A felt_4 that isn't a number stopped the print with a TypeError on PHP 8; it now counts as 0, so
// both fields stay blank and the rest of the invoice prints. The print runs in a child process
// (run_formularprint.php), as the browser print does, and the test reads the PostScript it wrote.
//
// Requires the characterization DB env - skips cleanly otherwise.
//
// History:
// 20261103 CL/SZ SD-797: created.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class FormularprintSetPriceFieldsTest extends TestCase
{
    /** @var resource|\PgSql\Connection */
    private static $tenant;
    private static int $debtorId;
    private static int $next = 7970;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        CharacterizationEnv::rows(self::$tenant, "UPDATE grupper SET box12 = 'on' WHERE art = 'POS' AND kodenr = '2'");
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO formularer (formular, art, side, beskrivelse, justering, xa, ya, xb, str, color, font, fed, kursiv, sprog)
             VALUES (4, 2, 'A', 'Saetfelt3 \$ordre_felt_3', 'V', 20, 150, 0, 10, 0, 'Helvetica', '', '', 'Dansk'),
                    (4, 2, 'A', 'Saetfelt4 \$ordre_felt_4', 'V', 20, 140, 0, 10, 0, 'Helvetica', '', '', 'Dansk')"
        );
        $dg = CharacterizationEnv::one(self::$tenant, "SELECT kodenr FROM grupper WHERE art = 'DG' ORDER BY kodenr LIMIT 1");
        $debtor = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO adresser (kontonr, firmanavn, addr1, postnr, bynavn, email, art, gruppe, betalingsbet, betalingsdage)
             VALUES (91797, 'Saet Kunde', 'Saetvej 7', '8000', 'Aarhus', 'saet@example.invalid', 'D', $1, 'Netto', 8)
             RETURNING id",
            [$dg ? (int)$dg['kodenr'] : 1]
        );
        self::$debtorId = (int)$debtor['id'];
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            pg_close(self::$tenant);
            self::$tenant = null;
            CharacterizationEnv::teardownTenant();
        }
    }

    /** A posted invoice, 2 x 125.00 at 25% VAT, with felt_3 'Saetnavn' and the given felt_4. */
    private function invoice(string $felt4): int
    {
        $nr = ++self::$next;
        $order = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsbet, betalingsdage, kontonr, art, valuta,
                                 ordredate, levdate, fakturadate, ordrenr, fakturanr, sum, moms, momssats, status, konto_id,
                                 udskriv_til, sprog, felt_3, felt_4)
             VALUES ('Saet Kunde', 'Saetvej 7', '8000', 'Aarhus', 'saet@example.invalid', 'Netto', 8, 91797, 'DO', 'DKK',
                     '2026-10-01', '2026-10-01', '2026-10-01', $1, $2, 250, 62.5, 25, 3, $3, 'PDF', 'Dansk', 'Saetnavn', $4)
             RETURNING id",
            [$nr, (string)$nr, self::$debtorId, $felt4]
        );
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, enhed, posnr, antal, pris, rabat, momssats, procent)
             VALUES ($1, 0, 'S1', 'Saetvare', 'stk', 1, 2, 125.00, 0, 25, 100)",
            [(int)$order['id']]
        );
        return (int)$order['id'];
    }

    /** The PostScript the browser print wrote for the invoice. */
    private function printed(int $orderId): string
    {
        $res = CharacterizationEnv::runChild(__DIR__ . '/../support/run_formularprint.php', [$orderId, 4, CharacterizationEnv::testDb(), 'PDF']);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        $this->assertIsArray($json, "the print must finish (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        $this->assertSame('OK', $json['returned']);
        $ps = implode('', array_filter($json['print_files'], fn(string $name): bool => str_ends_with($name, '.ps'), ARRAY_FILTER_USE_KEY));
        $this->assertStringStartsWith('%!', $ps);
        return $ps;
    }

    /** @return array<string, array{string}> */
    public static function notANumber(): array
    {
        return ['text' => ['ikke et tal'], 'empty' => [''], 'zero' => ['0']];
    }

    #[DataProvider('notANumber')]
    public function testAFelt4ThatIsNoAmountLeavesBothFieldsBlankAndPrintsTheInvoice(string $felt4): void
    {
        $ps = $this->printed($this->invoice($felt4));

        foreach (['(Saetvare)', '(312,50)', '(Saetfelt3 )', '(Saetfelt4 )'] as $text) {
            $this->assertStringContainsString($text, $ps);
        }
        $this->assertStringNotContainsString('Saetnavn', $ps);
    }

    public function testANumericFelt4PrintsTheAmountAndFelt3(): void
    {
        $ps = $this->printed($this->invoice('125.5'));

        $this->assertStringContainsString('(Saetfelt3 Saetnavn)', $ps);
        $this->assertStringContainsString('(Saetfelt4 125,50)', $ps);
        $this->assertStringContainsString('(312,50)', $ps);
    }
}
