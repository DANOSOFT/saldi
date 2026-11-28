<?php
// tests/characterization/lager/PricelistLinksTest.test.php
//
// Regression test for the testlab finding "Prisliste CSV and Printvenlig drop
// Vis antal (and reset page size)" (stock-valuation-reports, round 1).
//
// lager/pricelist.php builds its CSV, Printvenlig and page links from the
// filter form's choices. After a page link (Linjer pr. side, a page number)
// each ticked box arrived as the form's markup checked='checked', and the CSV
// and Printvenlig hrefs held that quote raw, which ended the href. The CSV and
// the print view lost Vis antal and every choice after the first ticked box,
// and the page behind the CSV link went back to 50 lines. The page links left
// an unticked box out, so a column that is on by default came back.
//
// The test seeds one item that is not on the price list (so it shows only with
// Vis skjulte produkter) and has 8 in stock. It posts the form, follows the
// links the page printed the way the browser does, and reads the column titles
// and the item's row on each page and in the CSV (see
// support/run_pricelist_page.php).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261115 CL/ASR Integration: the session id comes from CharacterizationEnv::sessionId(), master's per-class test database (2bd490c6) replaced SESSION_ID.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class PricelistLinksTest extends CharacterizationTestCase
{
    /**
     * The user id online.php gives the seeded session. Its online row has revisor = false,
     * which pgsql returns as 'f', so online.php takes it for an auditor: bruger_id -1.
     */
    private const SESSION_USER = -1;

    /** Linjer pr. side Alle, as the select's option value has it. */
    private const ALLE = '100000';

    /** The item's stock: one receipt of this many. */
    private const ANTAL = 8;

    /** Varenr. of this test's item. */
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

    /** Where the report writes its CSV (lager/pricelist.php: ../temp/$db/pricelist.csv). */
    private static function csvPath(): string
    {
        return CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb() . '/pricelist.csv';
    }

    protected function setUp(): void
    {
        $this->varenr = 'pl' . bin2hex(random_bytes(4));
        $item = self::$fx->item(['varenr' => $this->varenr, 'beholdning' => self::ANTAL, 'salgspris' => 125.0]);
        $this->itemId = $item['id'];
        self::rows('UPDATE varer SET on_price_list = 0, tier_price = 100, retail_price = 150 WHERE id = $1', [$this->itemId]);
        self::rows(
            'INSERT INTO batch_kob (kobsdate, fakturadate, vare_id, pris, antal, rest, lager) VALUES ($1, $1, $2, $3, $4, $4, 1)',
            ['2025-11-10', $this->itemId, 75.0, self::ANTAL]
        );
    }

    protected function tearDown(): void
    {
        self::rows('DELETE FROM batch_kob WHERE vare_id = $1', [$this->itemId]);
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

    /**
     * The price list after Vis in the filter form, for every item group, with Vis skjulte produkter
     * and the boxes in $ticked ticked. A box the browser does not tick it does not post.
     *
     * @param list<string> $ticked
     */
    private function submitForm(array $ticked): string
    {
        $form = ['lagervalg' => '0', 'varegruppe' => '0:Alle', 'custom_text' => '', 'show_all_products' => 'on'];
        foreach ($ticked as $box) {
            $form[$box] = 'on';
        }
        $form['submit'] = 'Vis';
        return self::runChild('run_pricelist_page.php', [CharacterizationEnv::sessionId(), '', http_build_query($form)])['stdout'];
    }

    /** The page a link the report printed opens: $href is pricelist.php?... as the browser follows it. */
    private function follow(string $href): string
    {
        $this->assertStringStartsWith('pricelist.php?', $href, 'the link opens the report itself');
        if (is_file(self::csvPath())) {
            unlink(self::csvPath()); // so a CSV read after this is this request's
        }
        $query = substr($href, strlen('pricelist.php?'));
        return self::runChild('run_pricelist_page.php', [CharacterizationEnv::sessionId(), $query])['stdout'];
    }

    /** The href the browser follows for the page's CSV ('csv') or Printvenlig ('autoprint') link. */
    private function link(string $html, string $kind): string
    {
        $this->assertSame(1, preg_match("/<a href='(pricelist\\.php\\?$kind=1&[^']*)'/", $html, $m), "the page has its $kind link");
        return html_entity_decode($m[1], ENT_QUOTES);
    }

    /** The option the grid's Linjer pr. side select shows. */
    private static function pageSize(string $html): ?string
    {
        return preg_match("/id='plPageSize'.*?<option value='(\\d+)' selected>/s", $html, $m) ? $m[1] : null;
    }

    /**
     * Each table row's cells, without the page's trailing dots (Varenr.).
     *
     * @return list<list<string>>
     */
    private static function tableRows(string $html): array
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
                    $cells[] = rtrim(trim((string)preg_replace('/\s+/u', ' ', $cell->textContent)), '.');
                }
            }
            $rows[] = $cells;
        }
        return $rows;
    }

    /**
     * The column titles: the row with Beskrivelse in it.
     *
     * @return list<string>|null
     */
    private static function titles(string $html): ?array
    {
        foreach (self::tableRows($html) as $cells) {
            if (in_array('Beskrivelse', $cells, true)) {
                return $cells;
            }
        }
        return null;
    }

    /** Assert $html has the columns $titles, and the item under them with its Antal. */
    private function assertPage(array $titles, string $html, string $where): void
    {
        $this->assertSame($titles, self::titles($html), "$where has the chosen columns");
        $item = null;
        foreach (self::tableRows($html) as $cells) {
            if (($cells[0] ?? '') === $this->varenr && count($cells) === count($titles)) {
                $item = array_combine($titles, $cells);
            }
        }
        $this->assertNotNull($item, "$where has no row for {$this->varenr}:\n" . substr(strip_tags($html), 0, 3000));
        $this->assertSame((string)self::ANTAL, $item['Antal'], "$where shows the item's Antal");
    }

    /** Assert the CSV the last request wrote has the columns $titles, and the item's Antal under Antal. */
    private function assertCsv(array $titles): void
    {
        $this->assertFileExists(self::csvPath(), 'the CSV link wrote the CSV');
        $lines = preg_split('/\r?\n/', (string)file_get_contents(self::csvPath()));
        $header = explode(';', rtrim($lines[0], ';'));
        $this->assertSame($titles, $header, 'the CSV has the chosen columns');
        $item = null;
        foreach ($lines as $line) {
            if (str_starts_with($line, $this->varenr . ';')) {
                $item = array_combine($titles, explode(';', rtrim($line, ';')));
            }
        }
        $this->assertNotNull($item, "the CSV has no line for {$this->varenr}");
        $this->assertSame(self::ANTAL . ',00', $item['Antal'], "the CSV has the item's Antal");
    }

    /** @return array<string, array{list<string>, list<string>, list<string>}> ticked boxes, screen columns, CSV columns */
    public static function columnChoices(): array
    {
        return [
            // The lab's choice: Vis antal with the three prices that are on by default.
            'Vis antal' => [
                ['show_antal', 'show_tier_price', 'show_salgspris', 'show_retail_price'],
                ['Varenr', 'Beskrivelse', 'Antal', 'B2B salgspris', 'Salgspris', 'Vejl.pris'],
                ['Varenr', 'Beskrivelse', 'Antal', 'B2B pris', 'Salgspris', 'Vejl.pris'],
            ],
            // Salgspris is on by default; unticked it stays off.
            'Vis antal and Vis enhed without Salgspris' => [
                ['show_antal', 'show_enhed', 'show_tier_price', 'show_retail_price'],
                ['Varenr', 'Enhed', 'Beskrivelse', 'Antal', 'B2B salgspris', 'Vejl.pris'],
                ['Varenr', 'Enhed', 'Beskrivelse', 'Antal', 'B2B pris', 'Vejl.pris'],
            ],
        ];
    }

    /**
     * @param list<string> $ticked
     * @param list<string> $titles
     * @param list<string> $csvTitles
     */
    #[DataProvider('columnChoices')]
    public function test_the_grid_links_keep_the_choices_after_a_page_link(array $ticked, array $titles, array $csvTitles): void
    {
        $this->useLayout('S');
        $html = $this->submitForm($ticked);
        $this->assertStringContainsString("id='plGridTable'", $html, 'the grid layout is used');
        $this->assertSame($titles, self::titles($html), 'the posted price list has the chosen columns');

        // Linjer pr. side: the select's onchange opens this URL with the chosen page size appended.
        $this->assertSame(1, preg_match("/id='plPageSize' onchange=\"window\\.location\\.href='([^']*)' \\+ this\\.value;\"/", $html, $m), 'the grid has its page-size select');
        $all = $this->follow(html_entity_decode($m[1], ENT_QUOTES) . self::ALLE);
        $this->assertPage($titles, $all, 'the price list after Linjer pr. side Alle');
        $this->assertSame(self::ALLE, self::pageSize($all), 'Linjer pr. side shows Alle');

        $csvPage = $this->follow($this->link($all, 'csv'));
        $this->assertCsv($csvTitles);
        $this->assertPage($titles, $csvPage, 'the price list behind the CSV link');
        $this->assertSame(self::ALLE, self::pageSize($csvPage), 'Linjer pr. side still shows Alle behind the CSV link');

        $print = $this->follow($this->link($all, 'autoprint'));
        $this->assertPage($titles, $print, 'the Printvenlig view');
    }

    public function test_the_classic_layout_links_keep_the_choices(): void
    {
        [$ticked, $titles, $csvTitles] = self::columnChoices()['Vis antal and Vis enhed without Salgspris'];
        $this->useLayout('');
        $html = $this->submitForm($ticked);
        $this->assertStringNotContainsString("id='plGridTable'", $html, 'the classic layout is used');
        $this->assertPage($titles, $html, 'the posted price list');

        $csvPage = $this->follow($this->link($html, 'csv'));
        $this->assertCsv($csvTitles);
        $this->assertPage($titles, $csvPage, 'the price list behind the CSV link');

        // The page behind the CSV link was a GET too; its Printvenlig link keeps the choices.
        $print = $this->follow($this->link($csvPage, 'autoprint'));
        $this->assertPage($titles, $print, 'the Printvenlig view');
    }
}
