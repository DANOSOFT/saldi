<?php
// tests/characterization/lager/LagerstatusDateTypeTest.test.php
//
// Regression test for the testlab finding "Lagerstatus CSV and GET navigation
// drop Fakturadato (back to Leveringsdato)" (stock-valuation-reports, round 1).
//
// Lagerstatus counts stock up to a date by the delivery date (Leveringsdato,
// levdate) or by the invoice date (Fakturadato, fakturadate), chosen in the
// filter form's Dato select. The form posts it, but the CSV link did not carry
// it, and a GET request with a date set it to Leveringsdato, so the CSV, the
// grid's page links and the Print view all counted by Leveringsdato while the
// screen said Fakturadato.
//
// The test seeds one item whose receipts and sales have a later invoice date
// than delivery date, posts the form with each date basis, then opens the
// links the page printed the way the browser does, and reads the item's row
// on each page and its line in the CSV (see support/run_lagerstatus_page.php).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class LagerstatusDateTypeTest extends CharacterizationTestCase
{
    /** The report's date, as the form's Dato field has it. */
    private const DATO = '30-11-2025';

    /**
     * The user id online.php gives the seeded session. Its online row has revisor = false,
     * which pgsql returns as 'f', so online.php takes it for an auditor: bruger_id -1.
     */
    private const SESSION_USER = -1;

    private const KOSTPRIS = 10.0;
    private const SALGSPRIS = 25.0;

    /**
     * The item's movements: delivery date, invoice date, quantity, price. Two are invoiced after
     * DATO although delivered before it, so they count by Leveringsdato and not by Fakturadato.
     */
    private const RECEIPTS = [
        ['2025-11-10', '2025-11-10', 5, 10.0],
        ['2025-11-28', '2025-12-05', 10, 12.0],
    ];
    private const SALES = [
        ['2025-11-20', '2025-11-20', 1, 25.0],
        ['2025-11-29', '2025-12-03', 2, 25.0],
    ];

    /**
     * What the item's row shows at DATO for each date basis. Købspris values the stock from the
     * newest receipts: by Leveringsdato 10 at 12,00 and 2 at 10,00, by Fakturadato 4 at 10,00.
     */
    private const EXPECTED = [
        'levdate' => ['Tilgang' => 15.0, 'Afgang' => 3.0, 'Antal' => 12.0, 'Købspris' => '140,00', 'Kostpris' => '120,00', 'Salgspris' => '300,00'],
        'fakturadate' => ['Tilgang' => 5.0, 'Afgang' => 1.0, 'Antal' => 4.0, 'Købspris' => '40,00', 'Kostpris' => '40,00', 'Salgspris' => '100,00'],
    ];

    /** Varenr. of this test's item, so a search narrows the report to it. */
    private string $varenr = '';
    private int $itemId = 0;
    /** box3 of the session's user setting before the test: null when the test added the row, false when it did not touch it */
    private string|null|false $layoutBefore = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        CharacterizationEnv::resyncSequences(self::$tenant);
        // The report writes its CSV to temp/<db>/, which the login creates; these requests skip the login.
        if (!is_dir(dirname(self::csvPath()))) {
            mkdir(dirname(self::csvPath()), 0770, true);
        }
    }

    /** Where the report writes its CSV (lager/lagerstatus.php: ../temp/$db/lagerstatus.csv). */
    private static function csvPath(): string
    {
        return CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb() . '/lagerstatus.csv';
    }

    protected function setUp(): void
    {
        $this->varenr = 'lsdt' . bin2hex(random_bytes(4));
        $item = self::$fx->item(['varenr' => $this->varenr, 'kostpris' => self::KOSTPRIS, 'salgspris' => self::SALGSPRIS]);
        $this->itemId = $item['id'];
        foreach (self::RECEIPTS as [$levdate, $fakturadate, $antal, $pris]) {
            self::rows(
                'INSERT INTO batch_kob (kobsdate, fakturadate, vare_id, pris, antal, rest, lager) VALUES ($1, $2, $3, $4, $5, $5, 1)',
                [$levdate, $fakturadate, $this->itemId, $pris, $antal]
            );
        }
        foreach (self::SALES as [$levdate, $fakturadate, $antal, $pris]) {
            self::rows(
                'INSERT INTO batch_salg (salgsdate, fakturadate, vare_id, pris, antal, lager) VALUES ($1, $2, $3, $4, $5, 1)',
                [$levdate, $fakturadate, $this->itemId, $pris, $antal]
            );
        }
    }

    protected function tearDown(): void
    {
        self::rows('DELETE FROM batch_kob WHERE vare_id = $1', [$this->itemId]);
        self::rows('DELETE FROM batch_salg WHERE vare_id = $1', [$this->itemId]);
        self::rows('DELETE FROM lagerstatus WHERE vare_id = $1', [$this->itemId]);
        self::rows('DELETE FROM varer WHERE id = $1', [$this->itemId]);
        if ($this->layoutBefore === null) {
            self::rows("DELETE FROM grupper WHERE art = 'USET' AND kodenr = $1", [self::SESSION_USER]);
        } elseif ($this->layoutBefore !== false) {
            self::rows("UPDATE grupper SET box3 = $2 WHERE art = 'USET' AND kodenr = $1", [self::SESSION_USER, $this->layoutBefore]);
        }
        $this->layoutBefore = false;
    }

    /**
     * Pick the page layout through the session's user setting (grupper USET, box3):
     * 'S' is the grid layout, anything else but 'T' the classic one.
     */
    private function useLayout(string $menu): void
    {
        $row = self::one("SELECT box3 FROM grupper WHERE art = 'USET' AND kodenr = $1", [self::SESSION_USER]);
        if ($row === null) {
            self::rows(
                "INSERT INTO grupper (beskrivelse, art, kodenr, box1, box2, box3, box4, box5)
                 VALUES ('Usersettings', 'USET', $1, '', '', $2, '#eeeef0', '')",
                [self::SESSION_USER, $menu]
            );
        } else {
            self::rows("UPDATE grupper SET box3 = $2 WHERE art = 'USET' AND kodenr = $1", [self::SESSION_USER, $menu]);
        }
        $this->layoutBefore = $row === null ? null : (string)$row['box3'];
    }

    /** The report after OK in the filter form, with $dateType in the Dato select and the item's Varenr. searched. */
    private function submitForm(string $dateType): string
    {
        $form = [
            'lagervalg' => '0',
            'varegruppe' => '0:Alle',
            'dato' => self::DATO,
            'dateType' => $dateType,
            'zStock' => 'on',
            'showClosed' => 'on',
            'varenr' => $this->varenr,
        ];
        return self::runChild('run_lagerstatus_page.php', [CharacterizationEnv::SESSION_ID, '', http_build_query($form)])['stdout'];
    }

    /** The page a link the report printed opens: $href is lagerstatus.php?... as the browser follows it. */
    private function follow(string $href): string
    {
        $this->assertStringStartsWith('lagerstatus.php?', $href, 'the link opens the report itself');
        if (is_file(self::csvPath())) {
            unlink(self::csvPath()); // so a CSV read after this is this request's
        }
        $query = substr($href, strlen('lagerstatus.php?'));
        return self::runChild('run_lagerstatus_page.php', [CharacterizationEnv::SESSION_ID, $query])['stdout'];
    }

    /** The option the Dato select shows, which is the one it lists first. */
    private static function selectedDateType(string $html): ?string
    {
        return preg_match('/name="dateType">\s*<option value=\'([a-z]+)\'/', $html, $m) ? $m[1] : null;
    }

    /**
     * The item's row: each column title with what stands below it.
     *
     * @return array<string, string>|null
     */
    private function itemRow(string $html): ?array
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $titles = ['Varenr.', 'Enhed', 'Beskrivelse', 'Tilgang', 'Afgang', 'Antal', 'Købspris', 'Kostpris', 'Salgspris'];
        foreach ($doc->getElementsByTagName('tr') as $row) {
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                    $cells[] = trim((string)preg_replace('/\s+/u', ' ', $cell->textContent));
                }
            }
            if (($cells[0] ?? '') === $this->varenr && count($cells) === count($titles)) {
                return array_combine($titles, $cells);
            }
        }
        return null;
    }

    /** Assert the item's row on $html has the figures $dateType gives. */
    private function assertRow(string $dateType, string $html, string $where): void
    {
        $row = $this->itemRow($html);
        $this->assertNotNull($row, "$where has no row for {$this->varenr}:\n" . substr(strip_tags($html), 0, 3000));
        $expected = self::EXPECTED[$dateType];
        $actual = [
            'Tilgang' => (float)str_replace(',', '.', $row['Tilgang']),
            'Afgang' => (float)str_replace(',', '.', $row['Afgang']),
            'Antal' => (float)str_replace(',', '.', $row['Antal']),
            'Købspris' => $row['Købspris'],
            'Kostpris' => $row['Kostpris'],
            'Salgspris' => $row['Salgspris'],
        ];
        $this->assertSame($expected, $actual, "$where counts by $dateType");
    }

    /** @return array<string, array{bool, string}> grid layout, date basis */
    public static function layoutsAndDateTypes(): array
    {
        return [
            'grid layout, Fakturadato' => [true, 'fakturadate'],
            'classic layout, Fakturadato' => [false, 'fakturadate'],
            'grid layout, Leveringsdato' => [true, 'levdate'],
            'classic layout, Leveringsdato' => [false, 'levdate'],
        ];
    }

    #[DataProvider('layoutsAndDateTypes')]
    public function test_the_csv_link_keeps_the_date_basis(bool $grid, string $dateType): void
    {
        $this->useLayout($grid ? 'S' : '');
        $html = $this->submitForm($dateType);
        $this->assertSame($grid, str_contains($html, "id='lsGridTable'"), $grid ? 'the grid layout is used' : 'the classic layout is used');
        $this->assertSame($dateType, self::selectedDateType($html), 'the Dato select shows what was posted');
        $this->assertRow($dateType, $html, 'the posted report');

        $this->assertSame(1, preg_match("/<a href='(lagerstatus\\.php\\?[^']*&csv=1[^']*)'/", $html, $m), 'the page has its CSV link');
        $page = $this->follow(html_entity_decode($m[1], ENT_QUOTES));
        $this->assertRow($dateType, $page, 'the report behind the CSV link');
        $this->assertSame($dateType, self::selectedDateType($page), 'the Dato select shows the date basis after the CSV link');

        $this->assertFileExists(self::csvPath(), 'the CSV link wrote the CSV');
        $line = null;
        foreach (preg_split('/\r?\n/', (string)file_get_contents(self::csvPath())) as $csvLine) {
            if (str_starts_with($csvLine, $this->varenr . ';')) {
                $line = explode(';', $csvLine);
            }
        }
        $this->assertNotNull($line, "the CSV has no line for {$this->varenr}");
        $expected = self::EXPECTED[$dateType];
        $this->assertSame(
            [$expected['Tilgang'], $expected['Afgang'], $expected['Antal'], $expected['Købspris'], $expected['Kostpris'], $expected['Salgspris']],
            [(float)$line[3], (float)$line[4], (float)$line[5], $line[6], $line[7], $line[8]],
            "the CSV (Købt, Solgt, Antal, Købspris, Kostpris, Salgspris) counts by $dateType"
        );
    }

    /** @return array<string, array{string}> date basis */
    public static function dateTypes(): array
    {
        return ['Fakturadato' => ['fakturadate'], 'Leveringsdato' => ['levdate']];
    }

    #[DataProvider('dateTypes')]
    public function test_the_grid_page_links_and_print_view_keep_the_date_basis(string $dateType): void
    {
        $this->useLayout('S');
        $html = $this->submitForm($dateType);
        $this->assertRow($dateType, $html, 'the posted report');

        // Linjer pr. side: the select's onchange opens this URL with the chosen page size appended.
        $this->assertSame(1, preg_match("/id='lsPageSize' onchange=\"window\\.location\\.href='([^']*)' \\+ this\\.value;\"/", $html, $m), 'the grid has its page-size select');
        $page = $this->follow(html_entity_decode($m[1], ENT_QUOTES) . '50');
        $this->assertRow($dateType, $page, 'the report behind a page link');
        $this->assertSame($dateType, self::selectedDateType($page), 'the Dato select shows the date basis after a page link');

        // Print: the shell prints the URL the page hands it in window.saldiPrintAutoprint.
        $this->assertSame(1, preg_match('/window\.saldiPrintAutoprint = ("[^"]*");/', $html, $m), 'the grid hands the shell its print URL');
        $print = $this->follow((string)json_decode($m[1]));
        $this->assertStringContainsString('window.saldiPrintAutoprintView = true', $print, 'the print view is shown');
        $this->assertRow($dateType, $print, 'the print view');
    }
}
