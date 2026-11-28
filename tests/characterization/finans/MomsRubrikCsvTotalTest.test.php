<?php
// tests/characterization/finans/MomsRubrikCsvTotalTest.test.php
//
// Regression test for the testlab finding "Momsrubrikker CSV omits the
// screen's I alt row" (vat-return-eu-reports, round 1).
//
// finans/rapport_includes/moms_rubrik.php shows the five rubrik rows and an
// I alt row on screen, but wrote only the five rubrik rows to
// temp/<db>/moms_rubrik.csv. The CSV now ends with the same I alt row.
//
// The test seeds a debtor group with EU-zone B2B-EU, one with B2C-UDL and a
// creditor group with B2B-EU, a customer or supplier in each, and an invoiced
// and posted order from each on one day. It posts Finans -> Rapporter -> Momsrubrikker
// for that day and for the next day, which has nothing invoiced, and compares
// the CSV with the screen (see support/run_finans_rapport_page.php).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261115 CL/ASR Integration: master's per-class test database (CharacterizationEnv::sessionId()); the item comment says why eu_sales_kind() gives B-varer on the fresh tenant.
// 20261117 CL/ASR The orders are posted (status 4 and a posting with the order id), since the report counts only posted orders (audit finding reports R-2).

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class MomsRubrikCsvTotalTest extends CharacterizationTestCase
{
    /** The rubrik rows, in the order the screen and the CSV list them. */
    private const RUBRIKKER = ['A-varer', 'A-ydelser', 'B-varer', 'B-ydelser', 'C'];

    /** The invoiced day (Y-m-d), the 23rd of the fiscal year's first month. */
    private static string $day = '';
    /** @var list<int> the grupper rows this test added */
    private static array $groupIds = [];
    /** @var list<int> the adresser rows this test added */
    private static array $accountIds = [];
    /** @var list<int> the ordrer rows this test added */
    private static array $orderIds = [];
    private static int $itemId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        CharacterizationEnv::resyncSequences(self::$tenant);
        // The report writes its CSV to temp/<db>/, which the login creates; these requests skip the login.
        if (!is_dir(dirname(self::csvPath()))) {
            mkdir(dirname(self::csvPath()), 0770, true);
        }

        $ra = self::one("SELECT box1, box2 FROM grupper WHERE art = 'RA' AND kodenr = $1", [self::$regnaar]);
        self::$day = sprintf('%04d-%02d-23', (int)$ra['box2'], (int)$ra['box1']);

        // A Handelsvarer item. Its group's EU sales account (VG box12 1050) is the MR rubrik B goods account
        // (box5 1050, services box6 1150), so eu_sales_kind() gives goods: the EU line counts as B-varer.
        $item = self::$fx->item();
        self::$itemId = $item['id'];

        $euCustomer = self::account(self::$fx->debtor(), self::group('DG', 'B2B-EU'));
        $nonEuCustomer = self::account(self::$fx->debtor(), self::group('DG', 'B2C-UDL'));
        $euSupplier = self::account(self::$fx->creditor(), self::group('KG', 'B2B-EU'));

        self::invoice(self::$fx->order($euCustomer, [['item' => $item, 'antal' => 2, 'pris' => 1234.56]], ['ordredate' => self::$day]));
        self::invoice(self::$fx->order($nonEuCustomer, [['item' => $item, 'antal' => 1, 'pris' => 310.40]], ['ordredate' => self::$day]));
        self::invoice(self::$fx->creditorOrder($euSupplier, [['item' => $item, 'antal' => 3, 'pris' => 99.99]], ['ordredate' => self::$day]));
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            foreach (self::$orderIds as $id) {
                self::rows('DELETE FROM transaktioner WHERE ordre_id = $1', [$id]);
                self::rows('DELETE FROM ordrelinjer WHERE ordre_id = $1', [$id]);
                self::rows('DELETE FROM ordrer WHERE id = $1', [$id]);
            }
            foreach (self::$accountIds as $id) {
                self::rows('DELETE FROM adresser WHERE id = $1', [$id]);
            }
            foreach (self::$groupIds as $id) {
                self::rows('DELETE FROM grupper WHERE id = $1', [$id]);
            }
            if (self::$itemId) {
                self::rows('DELETE FROM varer WHERE id = $1', [self::$itemId]);
            }
        }
        self::$orderIds = [];
        self::$accountIds = [];
        self::$groupIds = [];
        self::$itemId = 0;
        parent::tearDownAfterClass();
    }

    /** Where the report writes its CSV (moms_rubrik.php: ../temp/$db/moms_rubrik.csv). */
    private static function csvPath(): string
    {
        return CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb() . '/moms_rubrik.csv';
    }

    /** A new debtor ('DG') or creditor ('KG') group with EU-zone $zone (box10), as Indstillinger sets it. */
    private static function group(string $art, string $zone): int
    {
        $row = self::one(
            "INSERT INTO grupper (beskrivelse, art, kodenr, box10, fiscal_year)
             VALUES ($1, $2, (SELECT COALESCE(max(kodenr), 0) + 1 FROM grupper WHERE art = $2), $3, $4)
             RETURNING id, kodenr",
            [Fixtures::TAG . ' ' . $zone, $art, $zone, self::$regnaar]
        );
        self::$groupIds[] = (int)$row['id'];
        return (int)$row['kodenr'];
    }

    /**
     * Move a customer or supplier into group $kodenr.
     *
     * @param array{id:int, kontonr:string} $account
     * @return array{id:int, kontonr:string}
     */
    private static function account(array $account, int $kodenr): array
    {
        self::rows('UPDATE adresser SET gruppe = $2 WHERE id = $1', [$account['id'], $kodenr]);
        self::$accountIds[] = $account['id'];
        return $account;
    }

    /**
     * Mark an order invoiced and posted on its order date, which is what the report counts (status 4, fakturadate,
     * and postings that carry the order id). The posting is a balanced pair on two accounts without a VAT code; the
     * report reads only that it exists.
     *
     * @param array{id:int, ordrenr:int, sum:float, moms:float} $order
     */
    private static function invoice(array $order): void
    {
        self::rows('UPDATE ordrer SET status = 4, fakturadate = ordredate WHERE id = $1', [$order['id']]);
        [$debit, $credit] = self::$fx->plainAccounts(2);
        foreach ([[$debit, $order['sum'], 0], [$credit, 0, $order['sum']]] as [$konto, $debet, $kredit]) {
            self::rows(
                "INSERT INTO transaktioner (kontonr, bilag, transdate, beskrivelse, debet, kredit, kladde_id, afd, ordre_id, logdate, logtime)
                 SELECT $1, 0, ordredate, $2, $3, $4, 0, 0, id, CURRENT_DATE, '12:00' FROM ordrer WHERE id = $5",
                [$konto, Fixtures::TAG . ' faktura', $debet, $kredit, $order['id']]
            );
        }
        self::$orderIds[] = $order['id'];
    }

    /** The report for the one day $date (Y-m-d), as Finans -> Rapporter posts it after OK. */
    private function report(string $date): string
    {
        if (is_file(self::csvPath())) {
            unlink(self::csvPath()); // so the CSV read after this is this request's
        }
        [$aar, $maaned, $dato] = array_map('intval', explode('-', $date));
        $form = [
            'regnaar' => (string)self::$regnaar,
            'rapportart' => 'moms_rubrik',
            'maaned_fra' => "$aar|$maaned",
            'dato_fra' => (string)$dato,
            'maaned_til' => "$aar|$maaned",
            'dato_til' => (string)$dato,
            'submit' => ' OK ',
        ];
        return self::runChild('run_finans_rapport_page.php', [CharacterizationEnv::sessionId(), '', http_build_query($form)])['stdout'];
    }

    /**
     * The report's rows on screen: the rubrik code (or I alt) and its amount.
     *
     * @return list<array{string, string}>
     */
    private static function screenRows(string $html): array
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $rows = [];
        foreach ($doc->getElementsByTagName('tr') as $row) {
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && strtolower($cell->nodeName) === 'td') {
                    $cells[] = trim((string)preg_replace('/\s+/u', ' ', $cell->textContent));
                }
            }
            if ($cells !== [] && in_array($cells[0], [...self::RUBRIKKER, 'I alt'], true)) {
                $rows[] = [$cells[0], (string)end($cells)];
            }
        }
        return $rows;
    }

    /**
     * The CSV's rows below its header: the first field (rubrik code or I alt) and the amount, its last field.
     * The amount is read from the end because a rubrik's Beskrivelse holds HTML entities (V&aelig;rdien),
     * whose semicolons split it into more fields.
     *
     * @return list<array{string, string}>
     */
    private function csvRows(): array
    {
        $this->assertFileExists(self::csvPath(), 'the report wrote its CSV');
        $text = mb_convert_encoding((string)file_get_contents(self::csvPath()), 'UTF-8', 'ISO-8859-1');
        $lines = array_values(array_filter(preg_split('/\r?\n/', $text), static fn($l) => $l !== ''));
        $this->assertSame('Rubrik;Beskrivelse;Nettobeloeb', array_shift($lines), 'the CSV starts with its header');
        return array_map(static function (string $line): array {
            $fields = str_getcsv($line, ';', '"', '');
            return [$fields[0], (string)end($fields)];
        }, $lines);
    }

    /** The CSV's last line as the report wrote it. */
    private function lastCsvLine(): string
    {
        $lines = preg_split('/\r?\n/', rtrim((string)file_get_contents(self::csvPath()), "\r\n"));
        return (string)end($lines);
    }

    /** A Danish amount as dkdecimal() prints it, e.g. 3.079,49, as a number. */
    private static function amount(string $dk): float
    {
        return (float)str_replace(['.', ','], ['', '.'], $dk);
    }

    /** @return array<string, array{bool, array<string, string>, string}> invoiced day or the next, the rubrik amounts, I alt */
    public static function periods(): array
    {
        return [
            // The lab's case: a period with nothing invoiced.
            'a day with nothing invoiced' => [false, array_fill_keys(self::RUBRIKKER, '0,00'), '0,00'],
            // 2 x 1234,56 to an EU business customer, 310,40 to a customer outside the EU, 3 x 99,99 bought from an EU supplier.
            'a day with EU and non-EU invoices' => [
                true,
                ['A-varer' => '299,97', 'A-ydelser' => '0,00', 'B-varer' => '2.469,12', 'B-ydelser' => '0,00', 'C' => '310,40'],
                '3.079,49',
            ],
        ];
    }

    /** @param array<string, string> $rubrikker */
    #[DataProvider('periods')]
    public function test_the_csv_ends_with_the_i_alt_row_the_screen_shows(bool $invoicedDay, array $rubrikker, string $iAlt): void
    {
        $date = $invoicedDay ? self::$day : date('Y-m-d', strtotime(self::$day . ' +1 day'));
        $html = $this->report($date);
        $screen = self::screenRows($html);
        $expected = [];
        foreach ($rubrikker as $kode => $beloeb) {
            $expected[] = [$kode, $beloeb];
        }
        $expected[] = ['I alt', $iAlt];
        $this->assertSame($expected, $screen, "the screen shows the rubrik rows and I alt:\n" . substr(strip_tags($html), 0, 3000));

        $csv = $this->csvRows();
        $this->assertSame($screen, $csv, 'the CSV has the rows the screen shows, I alt last');
        $this->assertSame('I alt;;"' . $iAlt . '"', $this->lastCsvLine(), 'I alt has its amount under Nettobeloeb');
        $this->assertEqualsWithDelta(
            array_sum(array_map(static fn($r) => self::amount($r[1]), array_slice($csv, 0, -1))),
            self::amount($iAlt),
            0.001,
            'I alt in the CSV is the sum of its rubrik rows'
        );
    }
}
