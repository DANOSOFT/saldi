<?php
// tests/characterization/includes/FormularprintLagerFieldsTest.test.php
//
// Characterization tests for the order-line lager fields and the K54 references in
// includes/formfunk.php formularprint() (SD-754, WP11-4).
//
// - The line fields lager and lagernavn print the lager a line is drawn from (K17).
// - A legacy order prints one row per posnr and varenr, as before; a Shopify web order (a web row
//   in shopify_order_links) and its credit note print every row, so a line drawn from two lagre
//   prints both, whatever the order's "Vor ref." says.
// - With an LG row per accounting year, the lager name is the current year's.
// - The K54 layout recipe of doc/shopify/layouts-pos50.md: a web order's invoice and credit note
//   show the Shopify order name and the invoice / credit-note number, a store order's invoice shows
//   the invoice number only, and neither shows Saldi's order number.
//
// Requires the characterization DB env - skips cleanly otherwise.
//
// History:
// 20261103 CL/SZ SD-754: created.
// 20261103 CL/SZ SD-754: The web order is marked by shopify_order_links, not by ref. Older LG rows per year.
// 20261104 CL/SZ SD-754: The LG rows use the accounting year the print runner takes as current, added when none covers today.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class FormularprintLagerFieldsTest extends TestCase
{
    /** The brand the K54 recipe is applied to: a copy of the Dansk invoice and credit-note layouts. */
    private const BRAND = 'k54test';

    /** @var resource|\PgSql\Connection */
    private static $tenant;
    private static int $debtorId;
    private static int $next = 7540;

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
             VALUES (91754, 'Lager Kunde', 'Lagervej 4', '8000', 'Aarhus', 'lager@example.invalid', 'D', $1, 'Netto', 8)
             RETURNING id",
            [$dg ? (int)$dg['kodenr'] : 1]
        );
        self::$debtorId = (int)$debtor['id'];
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM grupper WHERE art = 'LG'");
        // lager 3 also has a newer accounting year's row with another name, read last, as on a tenant from
        // before SST-829; the current year's name prints
        $year = self::currentAccountingYear();
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO grupper (beskrivelse, art, kodenr, fiscal_year) VALUES ('Hovedlager', 'LG', 3, $1), ('Butik Aarhus', 'LG', 7, $1),
                                                                                 ('Gammelt lager', 'LG', 3, $2)",
            [$year, $year + 1]
        );
        // the intake's order links, as includes/shopify/schema.php makes them (only the columns used here)
        CharacterizationEnv::rows(
            self::$tenant,
            "CREATE TABLE IF NOT EXISTS shopify_order_links (id serial NOT NULL, shop_id integer NOT NULL, order_gid text,
                                                             ordre_id integer, direction text NOT NULL, PRIMARY KEY (id))"
        );
        // The invoice layout gets the two line fields; the K54 brand is the Dansk invoice and credit note with the recipe applied.
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO formularer (formular, art, beskrivelse, justering, xa, ya, xb, str, color, font, fed, kursiv, sprog)
             VALUES (4, 3, 'lager', 'H', 110, 0, 0, 10, 0, 'Helvetica', '', '', 'Dansk'),
                    (4, 3, 'lagernavn', 'V', 115, 0, 0, 10, 0, 'Helvetica', '', '', 'Dansk')"
        );
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO formularer (formular, art, beskrivelse, justering, xa, ya, xb, yb, str, color, font, fed, kursiv, side, sprog)
             SELECT formular, art, beskrivelse, justering, xa, ya, xb, yb, str, color, font, fed, kursiv, side, $1
             FROM formularer WHERE formular IN (4, 5) AND lower(sprog) = 'dansk'",
            [self::BRAND]
        );
        self::applyK54Recipe(self::BRAND);
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
     * The accounting year the print runner takes as current: the RA year whose period covers today
     * (tests/characterization/support/bootstrap_ordrefunc.php). One is added when the template has none.
     */
    private static function currentAccountingYear(): int
    {
        $year = (int)date('Y');
        $month = (int)date('m');
        $current = 0;
        foreach (CharacterizationEnv::rows(self::$tenant, "SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA'") as $ra) {
            if (($year > (int)$ra['box2'] || ($year === (int)$ra['box2'] && $month >= (int)$ra['box1']))
                && ($year < (int)$ra['box4'] || ($year === (int)$ra['box4'] && $month <= (int)$ra['box3']))) {
                $current = (int)$ra['kodenr'];
            }
        }
        if ($current === 0) {
            $next = CharacterizationEnv::one(self::$tenant, "SELECT coalesce(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'RA'");
            $current = (int)$next['kodenr'];
            CharacterizationEnv::rows(
                self::$tenant,
                "INSERT INTO grupper (beskrivelse, art, kodenr, box1, box2, box3, box4, fiscal_year) VALUES ($1, 'RA', $2, '01', $3, '12', $3, $2)",
                [(string)$year, $current, (string)$year]
            );
        }
        return $current;
    }

    /** The K54 recipe of doc/shopify/layouts-pos50.md, on one brand's invoice and credit note. */
    private static function applyK54Recipe(string $brand): void
    {
        $rows = [
            // the order number's label and value print the Shopify order name, and nothing when there is none
            "UPDATE formularer SET beskrivelse = 'if(\$ordre_kundeordnr)Ordrenr.' WHERE beskrivelse IN ('Vores ordre nr', 'Deres ordre nr')",
            "UPDATE formularer SET beskrivelse = 'if(\$ordre_kundeordnr)\$ordre_kundeordnr' WHERE beskrivelse = '\$ordre_ordrenr'",
            // the Shopify order name is already printed above, so the customer-reference pair goes
            "DELETE FROM formularer WHERE beskrivelse IN ('Deres ref:', '\$ordre_kundeordnr;')",
        ];
        foreach ($rows as $sql) {
            CharacterizationEnv::rows(self::$tenant, "$sql AND formular IN (4, 5) AND art = 2 AND lower(sprog) = $1", [$brand]);
        }
    }

    /**
     * A posted invoice (DO) or credit note (DK). Every line is [posnr, varenr, lager]; each is 1 x 100.00 at 25% VAT.
     * A web order is linked in shopify_order_links; its credit note is linked only through kred_ord_id.
     *
     * @param list<array{0: int, 1: string, 2: int}> $lines
     * @return array{id: int, fakturanr: string, ordrenr: string}
     */
    private function postedDocument(array $lines, string $ref = '', string $kundeordnr = '', string $sprog = 'Dansk', string $art = 'DO', ?int $credits = null, bool $web = false): array
    {
        $nr = ++self::$next;
        $ordrenr = 50000 + $nr; // never the same as the invoice number, so the print shows which one it printed
        $sign = $art === 'DK' ? -1 : 1;
        $order = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsbet, betalingsdage, kontonr, art, valuta,
                                 ordredate, levdate, fakturadate, ordrenr, fakturanr, sum, moms, momssats, status, konto_id,
                                 udskriv_til, mail_fakt, sprog, ref, kundeordnr, kred_ord_id)
             VALUES ('Lager Kunde', 'Lagervej 4', '8000', 'Aarhus', 'lager@example.invalid', 'Netto', 8, 91754, $1, 'DKK',
                     '2026-11-03', '2026-11-03', '2026-11-03', $2, $3, $4, $5, 25, 3, $6, 'PDF', '', $7, $8, $9, $10)
             RETURNING id",
            [$art, $ordrenr, (string)$nr, $sign * 100 * count($lines), $sign * 25 * count($lines), self::$debtorId, $sprog, $ref, $kundeordnr, $credits]
        );
        if ($web) {
            CharacterizationEnv::rows(
                self::$tenant,
                "INSERT INTO shopify_order_links (shop_id, order_gid, ordre_id, direction) VALUES (1, $1, $2, 'web')",
                ["gid://shopify/Order/$nr", (int)$order['id']]
            );
        }
        foreach ($lines as [$posnr, $varenr, $lager]) {
            CharacterizationEnv::rows(
                self::$tenant,
                "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, enhed, posnr, antal, pris, rabat, momssats, procent, lager)
                 VALUES ($1, 0, $2, $3, 'stk', $4, $5, 100.00, 0, 25, 100, $6)",
                [(int)$order['id'], $varenr, "Havestol $varenr", $posnr, $sign, $lager]
            );
        }
        return ['id' => (int)$order['id'], 'fakturanr' => (string)$nr, 'ordrenr' => (string)$ordrenr];
    }

    /** The PostScript of a browser print of the document. */
    private function printed(array $doc, int $formular = 4): string
    {
        $res = CharacterizationEnv::runChild(
            __DIR__ . '/../support/run_formularprint.php',
            [$doc['id'], $formular, CharacterizationEnv::testDb(), 'PDF', '0']
        );
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        $this->assertIsArray($json, "runner must emit JSON (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        $this->assertSame('OK', $json['returned']);
        $prefix = $formular === 5 ? 'kn' : 'fakt';
        $file = $prefix . $doc['fakturanr'] . '.ps';
        $this->assertArrayHasKey($file, $json['print_files']);
        return $json['print_files'][$file];
    }

    public function testTheLagerFieldsPrintTheLagerEachLineIsDrawnFrom(): void
    {
        $doc = $this->postedDocument([[1, 'A1', 3], [2, 'B2', 7]]);

        $ps = $this->printed($doc);

        foreach (['(Havestol A1)', '(Hovedlager)', '(Havestol B2)', '(Butik Aarhus)', '(3)', '(7)'] as $text) {
            $this->assertStringContainsString($text, $ps);
        }
        $this->assertStringNotContainsString('(Gammelt lager)', $ps);
    }

    public function testALegacyOrderPrintsOneRowPerPosnrAndVarenrAsBefore(): void
    {
        $doc = $this->postedDocument([[1, 'A1', 3], [1, 'A1', 7]]);

        $ps = $this->printed($doc);

        $this->assertSame(1, substr_count($ps, '(Havestol A1)'));
        $this->assertStringContainsString('(Hovedlager)', $ps);
        $this->assertStringNotContainsString('(Butik Aarhus)', $ps);
    }

    public function testAShopifyWebOrderPrintsBothRowsOfALineDrawnFromTwoLagre(): void
    {
        // reassigned to a salesperson in "Vor ref.": still a web order
        $doc = $this->postedDocument([[1, 'A1', 3], [1, 'A1', 7]], 'Anna Sælger', 'HML1234', 'Dansk', 'DO', null, true);

        $ps = $this->printed($doc);

        $this->assertSame(2, substr_count($ps, '(Havestol A1)'));
        $this->assertStringContainsString('(Hovedlager)', $ps);
        $this->assertStringContainsString('(Butik Aarhus)', $ps);
    }

    public function testAShopifyWebOrderCreditNotePrintsBothRowsOfALineDrawnFromTwoLagre(): void
    {
        $invoice = $this->postedDocument([[1, 'A1', 3], [1, 'A1', 7]], '', 'HML1236', 'Dansk', 'DO', null, true);
        $credit = $this->postedDocument([[1, 'A1', 3], [1, 'A1', 7]], '', 'HML1236', 'Dansk', 'DK', $invoice['id']);

        $ps = $this->printed($credit, 5);

        $this->assertSame(2, substr_count($ps, '(Havestol A1)'));
    }

    public function testRefShopifyAloneIsNoWebOrder(): void
    {
        $doc = $this->postedDocument([[1, 'A1', 3], [1, 'A1', 7]], 'shopify', 'HML1237');

        $ps = $this->printed($doc);

        $this->assertSame(1, substr_count($ps, '(Havestol A1)'));
    }

    public function testK54AWebOrderInvoiceShowsTheShopifyOrderNameAndTheInvoiceNumberNotTheOrderNumber(): void
    {
        $doc = $this->postedDocument([[1, 'A1', 3]], '', 'HML1234', self::BRAND, 'DO', null, true);

        $ps = $this->printed($doc);

        $this->assertStringContainsString('(Ordrenr.)', $ps);
        $this->assertStringContainsString('(HML1234)', $ps);
        $this->assertStringContainsString("({$doc['fakturanr']})", $ps);
        $this->assertStringNotContainsString($doc['ordrenr'], $ps);
    }

    public function testK54AWebOrderCreditNoteShowsTheShopifyOrderNameAndTheCreditNoteNumberNotTheOrderNumber(): void
    {
        $invoice = $this->postedDocument([[1, 'A1', 3]], '', 'HML1235', self::BRAND, 'DO', null, true);
        $credit = $this->postedDocument([[1, 'A1', 3]], '', 'HML1235', self::BRAND, 'DK', $invoice['id']);

        $ps = $this->printed($credit, 5);

        $this->assertStringContainsString('(Kreditnota)', $ps);
        $this->assertStringContainsString('(HML1235)', $ps);
        $this->assertStringContainsString("({$credit['fakturanr']})", $ps);
        $this->assertStringNotContainsString($credit['ordrenr'], $ps);
    }

    public function testK54AStoreOrderInvoiceShowsTheInvoiceNumberAndNoOrderNumber(): void
    {
        $doc = $this->postedDocument([[1, 'A1', 3]], 'Kasse 1', '', self::BRAND);

        $ps = $this->printed($doc);

        $this->assertStringContainsString("({$doc['fakturanr']})", $ps);
        $this->assertStringNotContainsString('(Ordrenr.)', $ps);
        $this->assertStringNotContainsString($doc['ordrenr'], $ps);
    }
}
