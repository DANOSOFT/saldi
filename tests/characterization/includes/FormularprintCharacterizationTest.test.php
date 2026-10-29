<?php
// tests/characterization/includes/FormularprintCharacterizationTest.test.php
//
// Characterization tests for includes/formfunk.php formularprint() (SD-751, core edit 6).
//
// formfunk.php is legacy core used by every install's printing, so the browser print is pinned
// before and after the headless edit: the files written to temp/<db>/<bruger_id>/, the redirect to
// includes/udskriv.php and the mails. The headless branch gets its own cases: it writes into the
// caller's work directory only, prints no redirect, sends no mail and returns the documents.
//
// Fixture invoices and credit notes are inserted already posted (status 3, fakturanr set), as
// bogfor() leaves them; the runner drives formularprint() in a child process because it prints
// and can exit().
//
// Requires the characterization DB env - skips cleanly otherwise.
//
// History:
// 20261029 CL/SZ SD-751: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class FormularprintCharacterizationTest extends TestCase
{
    /** @var resource|\PgSql\Connection */
    private static $tenant;
    private static int $debtorId;
    private static int $next = 7510;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $dg = CharacterizationEnv::one(self::$tenant, "SELECT kodenr FROM grupper WHERE art = 'DG' ORDER BY kodenr LIMIT 1");
        $debtor = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO adresser (kontonr, firmanavn, addr1, postnr, bynavn, email, art, gruppe, betalingsbet, betalingsdage)
             VALUES (91751, 'Formular Kunde', 'Printvej 2', '8000', 'Aarhus', 'formular@example.invalid', 'D', $1, 'Netto', 8)
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

    /**
     * A posted invoice (art DO) or credit note (art DK) with $lines lines of 2 x 125.00 at 25% VAT.
     *
     * @return array{id: int, fakturanr: string}
     */
    private function postedDocument(string $art = 'DO', string $udskrivTil = 'PDF', string $mailFakt = '', ?int $credits = null, int $lines = 1): array
    {
        $nr = ++self::$next;
        $sign = $art === 'DK' ? -1 : 1;
        $order = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsbet, betalingsdage, kontonr, art, valuta,
                                 ordredate, levdate, fakturadate, ordrenr, fakturanr, sum, moms, momssats, status, konto_id,
                                 udskriv_til, mail_fakt, sprog, kred_ord_id)
             VALUES ('Formular Kunde', 'Printvej 2', '8000', 'Aarhus', 'formular@example.invalid', 'Netto', 8, 91751, $1, 'DKK',
                     '2026-10-01', '2026-10-01', '2026-10-01', $2, $3, $4, $5, 25, 3, $6, $7, $8, 'Dansk', $9)
             RETURNING id",
            [$art, $nr, (string)$nr, $sign * 250 * $lines, $sign * 62.5 * $lines, self::$debtorId, $udskrivTil, $mailFakt, $credits]
        );
        for ($posnr = 1; $posnr <= $lines; $posnr++) {
            CharacterizationEnv::rows(
                self::$tenant,
                "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, enhed, posnr, antal, pris, rabat, momssats, procent)
                 VALUES ($1, 0, 'P$posnr', 'Printvare $posnr', 'stk', $posnr, $2, 125.00, 0, 25, 100)",
                [(int)$order['id'], $sign * 2]
            );
        }
        return ['id' => (int)$order['id'], 'fakturanr' => (string)$nr];
    }

    /**
     * @return array{returned: mixed, printed: string, mails: list<array{ordre_id: string, email: string, file: string}>,
     *   print_files: array<string, string>, work_files: array<string, string>|null}
     */
    private function print(int $orderId, int $formular, string $udskrivAlleTil = 'PDF', bool $headless = false): array
    {
        $res = CharacterizationEnv::runChild(
            __DIR__ . '/../support/run_formularprint.php',
            [$orderId, $formular, CharacterizationEnv::testDb(), $udskrivAlleTil === '' ? '-' : $udskrivAlleTil, $headless ? '1' : '0']
        );
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        $this->assertIsArray($json, "runner must emit JSON (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        return $json;
    }

    public function testABrowserPrintOfAnInvoiceWritesPsAndHtmAndRedirectsToUdskriv(): void
    {
        $doc = $this->postedDocument();

        $out = $this->print($doc['id'], 4);

        $this->assertSame('OK', $out['returned']);
        $nr = $doc['fakturanr'];
        $this->assertSame(["fakt$nr.htm", "fakt$nr.ps"], array_keys($out['print_files']));
        $ps = $out['print_files']["fakt$nr.ps"];
        $this->assertStringStartsWith('%!', $ps);
        foreach (['(Printvare 1)', '(250,00)', '(62,50)', '(312,50)', "($nr)"] as $text) {
            $this->assertStringContainsString($text, $ps);
        }
        $this->assertStringContainsString('<big><b>Vent - Udskrift genereres</b></big><br>', $out['printed']);
        $this->assertMatchesRegularExpression(
            '#<meta http-equiv="refresh" content="0;URL=../includes/udskriv\.php\?locat=&ps_fil=' . preg_quote(CharacterizationEnv::testDb(), '#')
            . "/1/fakt$nr&amp;id={$doc['id']}&amp;udskriv_til=PDF&amp;art=DO&amp;bgr=[^\"]*&returside=\">$#",
            $out['printed']
        );
        $this->assertSame([], $out['mails']);
    }

    public function testACreditNoteAskedForAsAnInvoicePrintsAsKn(): void
    {
        $invoice = $this->postedDocument();
        $credit = $this->postedDocument('DK', 'PDF', '', $invoice['id']);

        $out = $this->print($credit['id'], 4);

        $this->assertSame('OK', $out['returned']);
        $nr = $credit['fakturanr'];
        $this->assertSame(["kn$nr.htm", "kn$nr.ps"], array_keys($out['print_files']));
        $this->assertStringContainsString('(Kreditnota)', $out['print_files']["kn$nr.ps"]);
        $this->assertStringContainsString("ps_fil=" . CharacterizationEnv::testDb() . "/1/kn$nr&amp;", $out['printed']);
    }

    public function testAnInvoiceSetToEmailIsMailedAndNotRedirected(): void
    {
        $doc = $this->postedDocument('DO', 'email', 'on');

        $out = $this->print($doc['id'], 4, '');

        $this->assertSame('OK', $out['returned']);
        $this->assertSame([['ordre_id' => (string)$doc['id'], 'email' => 'formular@example.invalid', 'file' => "fakt{$doc['fakturanr']}.pdf"]], $out['mails']);
        $this->assertStringNotContainsString('udskriv.php', $out['printed']);
    }

    public function testAnUnpostedInvoiceIsRefused(): void
    {
        $doc = $this->postedDocument();
        CharacterizationEnv::rows(self::$tenant, "UPDATE ordrer SET fakturanr = '' WHERE id = $1", [$doc['id']]);

        $out = $this->print($doc['id'], 4);

        $this->assertStringStartsWith('Fakturering afbrudt', (string)$out['returned']);
        $this->assertSame([], $out['print_files']);
    }

    public function testACreditNoteWhosePageBreakTextNamesTheCreditedInvoicePrintsItsNumber(): void
    {
        // Before SD-751 skriv() called exit() on $ordre_creditedinvoice in a page-break ('!S') text.
        $invoice = $this->postedDocument();
        $credit = $this->postedDocument('DK', 'PDF', '', $invoice['id'], 60);
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO formularer (formular, art, side, beskrivelse, justering, xa, ya, xb, str, color, font, fed, kursiv, sprog)
             VALUES (5, 2, '!S', 'Krediterer faktura \$ordre_creditedinvoice', 'V', 26, 70, 0, 10, 0, 'Helvetica', '', '', 'Dansk')"
        );
        try {
            $out = $this->print($credit['id'], 5);
        } finally {
            CharacterizationEnv::rows(self::$tenant, "DELETE FROM formularer WHERE side = '!S' AND beskrivelse LIKE 'Krediterer faktura%'");
        }

        $this->assertSame('OK', $out['returned']);
        $this->assertStringContainsString("(Krediterer faktura {$invoice['fakturanr']})", $out['print_files']["kn{$credit['fakturanr']}.ps"]);
    }

    public function testACreditNoteWithoutACreditedInvoicePrintsTheVariableEmpty(): void
    {
        // Before SD-751 find_form_tekst() then queried "select creditedinvoice from ordrer", a column that doesn't exist.
        $credit = $this->postedDocument('DK');
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO formularer (formular, art, side, beskrivelse, justering, xa, ya, xb, str, color, font, fed, kursiv, sprog)
             VALUES (5, 2, 'A', 'Krediterer: \$ordre_creditedinvoice slut', 'V', 26, 250, 0, 10, 0, 'Helvetica', '', '', 'Dansk')"
        );
        try {
            $out = $this->print($credit['id'], 5);
        } finally {
            CharacterizationEnv::rows(self::$tenant, "DELETE FROM formularer WHERE beskrivelse LIKE 'Krediterer: %'");
        }

        $this->assertSame('OK', $out['returned']);
        $this->assertMatchesRegularExpression('/\(Krediterer: +slut\)/', $out['print_files']["kn{$credit['fakturanr']}.ps"]);
    }

    public function testAHeadlessPrintWritesOnlyIntoItsWorkDirectoryAndReturnsTheDocuments(): void
    {
        $doc = $this->postedDocument('DO', 'email', 'on');

        $out = $this->print($doc['id'], 4, '', true);

        $nr = $doc['fakturanr'];
        $this->assertSame([['name' => "fakt$nr", 'background' => '', 'pages' => 1]], $out['returned']);
        $this->assertSame(["fakt$nr.htm", "fakt$nr.ps"], array_keys($out['work_files']));
        $this->assertStringContainsString('(312,50)', $out['work_files']["fakt$nr.ps"]);
        $this->assertSame([], $out['mails'], 'an invoice set to email is not mailed');
        $this->assertStringNotContainsString('<meta', $out['printed'], 'no redirect');
        $this->assertStringNotContainsString('Vent - Udskrift genereres', $out['printed']);
        $this->assertArrayNotHasKey("fakt$nr.ps", $out['print_files'], "nothing is written to the user's print directory");
    }

    public function testAFormEditorLanguageLockAppliesToABrowserPrintButNotToAHeadlessOne(): void
    {
        $doc = $this->postedDocument();
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $dbId = (int)CharacterizationEnv::one($master, 'SELECT id FROM regnskab WHERE db = $1', [CharacterizationEnv::testDb()])['id'];
        pg_close($master);
        $dir = dirname(__DIR__, 3) . "/logolib/$dbId";
        $madeDir = !is_dir($dir) && mkdir($dir, 0775, true);
        file_put_contents("$dir/fe_printlang_4.json", json_encode(['sprog' => 'English']));
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO formularer (formular, art, side, beskrivelse, justering, xa, ya, xb, str, color, font, fed, kursiv, sprog)
             VALUES (4, 2, 'A', 'LOCKED ENGLISH', 'V', 120, 250, 0, 15, 0, 'Helvetica', '', '', 'English')"
        );
        try {
            $browser = $this->print($doc['id'], 4);
            $headless = $this->print($doc['id'], 4, '', true);
        } finally {
            unlink("$dir/fe_printlang_4.json");
            if ($madeDir) {
                rmdir($dir);
            }
            CharacterizationEnv::rows(self::$tenant, "DELETE FROM formularer WHERE beskrivelse = 'LOCKED ENGLISH'");
        }

        $nr = $doc['fakturanr'];
        $this->assertStringContainsString('(LOCKED ENGLISH)', $browser['print_files']["fakt$nr.ps"], 'the browser print follows the lock');
        $this->assertStringNotContainsString('(LOCKED ENGLISH)', $headless['work_files']["fakt$nr.ps"], "the headless print keeps the order's language");
        $this->assertStringContainsString('(Nettosum)', $headless['work_files']["fakt$nr.ps"]);
    }

    public function testAHeadlessPrintOfAnOrderSetToNoPrintStillPrints(): void
    {
        $doc = $this->postedDocument('DO', 'ingen');

        $out = $this->print($doc['id'], 4, '', true);

        $this->assertSame("fakt{$doc['fakturanr']}", $out['returned'][0]['name'] ?? null);
    }
}
