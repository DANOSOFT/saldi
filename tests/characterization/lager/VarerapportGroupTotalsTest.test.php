<?php
// tests/characterization/lager/VarerapportGroupTotalsTest.test.php
//
// Regression test for the testlab finding "Varerapport group subtotal cells
// misaligned under the 17-column header" (stock-valuation-reports, round 1).
//
// Lager -> Rapporter with "Beregn lagerbeh./værdi" ends every item group with
// a total row. That row kept an older column layout: in the 17-column report
// Købspris stood under Kostpris, Salgspris under Købt, DB under Købspris, DG
// under Solgt and Værdi under Moms, the CSV had the same shift, and with
// "Kun salg / DB" (11 columns) Salgspris stood under the second (alias).
//
// The test opens lager/rapport.php the way the report's links do (see
// support/run_varerapport_page.php), in the grid layout (user setting menu S)
// and in the classic one, and reads every cell of the group total under the
// column title it stands below, on the page and in the CSV.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class VarerapportGroupTotalsTest extends CharacterizationTestCase
{
    /** The report runs over November 2025; every movement is on DAY. */
    private const FROM = '2025-11-01';
    private const TO = '2025-11-30';
    private const DAY = '2025-11-28';

    /**
     * The user id online.php gives the seeded session. Its online row has revisor = false,
     * which pgsql returns as 'f', so online.php takes it for an auditor: bruger_id -1.
     */
    private const SESSION_USER = -1;

    /** Two items of one stock group, each bought and sold on DAY. */
    private const ITEMS = [
        'a' => ['kostpris' => 40.0, 'salgspris' => 100.0, 'bought' => 10, 'sold' => 4],
        'b' => ['kostpris' => 10.0, 'salgspris' => 30.0, 'bought' => 5, 'sold' => 1],
    ];

    /**
     * The group total for ITEMS. Købspris 10*40 + 5*10, Salgspris 4*100 + 1*30,
     * DB 430 - (4*40 + 1*10), DG 260/430, Værdi (10-4)*40 + (5-1)*10.
     */
    private const TOTALS = ['Købspris' => '450,00', 'Salgspris' => '430,00', 'DB' => '260,00', 'DG' => '60,47%', 'Værdi' => '280,00'];

    /** The same totals in the CSV, where DG has no % and the columns have their CSV titles. */
    private const CSV_TOTALS = ['Købspris' => '450,00', 'Salgspris' => '430,00', 'DB' => '260,00', 'DG' => '60,47', 'Værdi' => '280,00'];

    /** Varenr. prefix of this test's items, so the report shows only them. */
    private string $token = '';
    private int $group = 0;
    private string $groupName = '';
    /** @var list<int> */
    private array $itemIds = [];
    /** @var list<int> */
    private array $orderIds = [];
    /** @var list<int> */
    private array $addressIds = [];
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

    /** Where the report writes its CSV (lager/rapport.php: ../temp/$db/salgsrapport.csv). */
    private static function csvPath(): string
    {
        return CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb() . '/salgsrapport.csv';
    }

    protected function setUp(): void
    {
        $this->token = 'vgt' . bin2hex(random_bytes(4));
        $items = [];
        foreach (self::ITEMS as $suffix => $item) {
            $items[$suffix] = self::$fx->item([
                'varenr' => $this->token . $suffix,
                'kostpris' => $item['kostpris'],
                'salgspris' => $item['salgspris'],
            ]);
            $this->itemIds[] = $items[$suffix]['id'];
            // For a day before today the report takes the cost price from kostpriser, as it stood before the period's end.
            self::rows(
                'INSERT INTO kostpriser (vare_id, transdate, kostpris) VALUES ($1, $2, $3)',
                [$items[$suffix]['id'], self::FROM, $item['kostpris']]
            );
        }
        $this->group = $items['a']['gruppe'];
        $this->assertSame($this->group, $items['b']['gruppe'], 'both items are in one stock group');
        $row = self::one(
            "SELECT beskrivelse FROM grupper WHERE art = 'VG' AND fiscal_year = $1 AND kodenr = $2",
            [self::$regnaar, $this->group]
        );
        $this->groupName = trim((string)$row['beskrivelse']);
        $this->assertNotSame('', $this->groupName, 'the stock group has a name');

        $creditor = self::$fx->creditor();
        $debtor = self::$fx->debtor();
        $this->addressIds = [$creditor['id'], $debtor['id']];
        $purchase = [];
        $sale = [];
        foreach (self::ITEMS as $suffix => $item) {
            $purchase[] = ['item' => $items[$suffix], 'antal' => $item['bought'], 'pris' => $item['kostpris']];
            $sale[] = ['item' => $items[$suffix], 'antal' => $item['sold'], 'pris' => $item['salgspris']];
        }
        // Both invoiced (status 3), so the purchase is not open and counts nothing under Bestilt.
        $this->batches('batch_kob', self::$fx->creditorOrder($creditor, $purchase, ['status' => 3, 'ordredate' => self::DAY])['id']);
        $this->batches('batch_salg', self::$fx->order($debtor, $sale, ['status' => 3, 'ordredate' => self::DAY])['id']);
    }

    /** The stock movement an invoiced order leaves: one batch row per line, dated DAY. */
    private function batches(string $table, int $orderId): void
    {
        $this->orderIds[] = $orderId;
        $date = $table === 'batch_kob' ? 'kobsdate' : 'salgsdate';
        foreach (self::rows('SELECT id, vare_id, antal, pris FROM ordrelinjer WHERE ordre_id = $1', [$orderId]) as $line) {
            self::rows(
                "INSERT INTO $table ($date, fakturadate, vare_id, linje_id, ordre_id, pris, antal, lager)
                 VALUES ($1, $1, $2, $3, $4, $5, $6, 1)",
                [self::DAY, (int)$line['vare_id'], (int)$line['id'], $orderId, (float)$line['pris'], (float)$line['antal']]
            );
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->itemIds as $id) {
            self::rows('DELETE FROM batch_kob WHERE vare_id = $1', [$id]);
            self::rows('DELETE FROM batch_salg WHERE vare_id = $1', [$id]);
            self::rows('DELETE FROM kostpriser WHERE vare_id = $1', [$id]);
        }
        foreach ($this->orderIds as $id) {
            self::rows('DELETE FROM ordrelinjer WHERE ordre_id = $1', [$id]);
            self::rows('DELETE FROM ordrer WHERE id = $1', [$id]);
        }
        foreach ($this->itemIds as $id) {
            self::rows('DELETE FROM varer WHERE id = $1', [$id]);
        }
        foreach ($this->addressIds as $id) {
            self::rows('DELETE FROM adresser WHERE id = $1', [$id]);
        }
        if ($this->layoutBefore === null) {
            self::rows("DELETE FROM grupper WHERE art = 'USET' AND kodenr = $1", [self::SESSION_USER]);
        } elseif ($this->layoutBefore !== false) {
            self::rows("UPDATE grupper SET box3 = $2 WHERE art = 'USET' AND kodenr = $1", [self::SESSION_USER, $this->layoutBefore]);
        }
        $this->itemIds = $this->orderIds = $this->addressIds = [];
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

    /** The report for this test's items, as the front page's OK button opens it, every row on one page. */
    private function report(bool $kunSalg): string
    {
        $query = [
            'submit' => 'ok',
            'varegruppe' => (string)$this->group,
            'date_from' => self::FROM,
            'date_to' => self::TO,
            'varenr' => $this->token,
            'lagertal' => 'on',
            'vr_per_page' => '100000',
        ];
        if ($kunSalg) {
            $query['kun_salg'] = 'on';
        }
        if (is_file(self::csvPath())) {
            unlink(self::csvPath()); // so the CSV read below is this request's
        }
        $res = self::runChild('run_varerapport_page.php', [CharacterizationEnv::SESSION_ID, http_build_query($query)]);
        return $res['stdout'];
    }

    /**
     * The text of every column a table row covers. A cell with colspan covers
     * several: its text goes in the first, the others are blank.
     *
     * @return list<string>
     */
    private static function columns(DOMElement $row): array
    {
        $columns = [];
        foreach ($row->childNodes as $cell) {
            if (!$cell instanceof DOMElement || !in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                continue;
            }
            $columns[] = trim((string)preg_replace('/\s+/u', ' ', $cell->textContent));
            for ($i = 1; $i < max(1, (int)$cell->getAttribute('colspan')); $i++) {
                $columns[] = '';
            }
        }
        return $columns;
    }

    /**
     * Each title paired with what stands below it: "Købspris: 450,00".
     *
     * @param list<string> $titles
     * @param list<string> $cells
     * @return list<string>
     */
    private static function underTitles(array $titles, array $cells): array
    {
        $pairs = [];
        foreach ($titles as $i => $title) {
            $pairs[] = $title . ': ' . ($cells[$i] ?? '(no cell)');
        }
        for ($i = count($titles); $i < count($cells); $i++) {
            $pairs[] = '(no title): ' . $cells[$i];
        }
        return $pairs;
    }

    /**
     * What the group total should show: the group's name under Varenr., the
     * totals under their titles, nothing under the rest.
     *
     * @param list<string> $titles
     * @param array<string, string> $totals
     * @return list<string>
     */
    private function expected(array $titles, array $totals, string $firstTitle): array
    {
        $pairs = [];
        foreach ($titles as $i => $title) {
            $pairs[] = $title . ': ' . ($i === 0 ? $this->groupName : ($totals[$title] ?? ''));
        }
        $this->assertSame($firstTitle, $titles[0] ?? null, 'the column titles start with ' . $firstTitle);
        return $pairs;
    }

    /** @return array<string, array{bool, bool, int}> grid layout, Kun salg / DB, number of columns */
    public static function layouts(): array
    {
        return [
            'grid layout' => [true, false, 17],
            'classic layout' => [false, false, 17],
            'grid layout, Kun salg / DB' => [true, true, 11],
            'classic layout, Kun salg / DB' => [false, true, 11],
        ];
    }

    #[DataProvider('layouts')]
    public function test_the_group_total_puts_each_amount_under_its_own_column_title(bool $grid, bool $kunSalg, int $width): void
    {
        $this->useLayout($grid ? 'S' : '');
        $html = $this->report($kunSalg);

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $titles = null;
        $total = null;
        foreach ($doc->getElementsByTagName('tr') as $row) {
            $columns = self::columns($row);
            if ($titles === null && ($columns[0] ?? '') === 'Varenr.') {
                $titles = $columns;
            } elseif (($columns[0] ?? '') === $this->groupName && count($columns) > 2) {
                $total = $columns; // the group's heading row above its items has one or two columns
            }
        }
        $this->assertNotNull($titles, "the report has no column titles:\n" . substr(strip_tags($html), 0, 3000));
        $this->assertSame($grid, str_contains($html, "id='vrGridTable'"), $grid ? 'the grid layout is used' : 'the classic layout is used');
        $this->assertCount($width, $titles, 'the report has its ' . $width . ' columns: ' . implode(' | ', $titles));
        foreach ([$this->token . 'a', $this->token . 'b'] as $varenr) {
            $this->assertStringContainsString(">$varenr<", $html, "item $varenr is in the report");
        }
        $this->assertNotNull($total, "the report has no total row for the group {$this->groupName}");

        $totals = $kunSalg ? array_diff_key(self::TOTALS, ['Købspris' => 1, 'Værdi' => 1]) : self::TOTALS;
        $this->assertSame($this->expected($titles, $totals, 'Varenr.'), self::underTitles($titles, $total));

        // The CSV the page wrote next to it, with the same columns.
        $this->assertFileExists(self::csvPath(), 'the report wrote its CSV');
        $csv = (string)file_get_contents(self::csvPath());
        $csvTitles = null;
        $csvTotal = null;
        foreach (preg_split('/\r?\n/', $csv) as $line) {
            if (str_starts_with($line, 'Varenr;')) {
                $csvTitles = explode(';', $line);
            } elseif (str_starts_with($line, $this->groupName . ';')) {
                $csvTotal = explode(';', $line);
            }
        }
        $this->assertNotNull($csvTitles, "the CSV has no column titles:\n" . substr($csv, 0, 2000));
        $this->assertNotNull($csvTotal, "the CSV has no total line for the group {$this->groupName}:\n" . substr($csv, 0, 2000));
        $this->assertCount($width, $csvTitles, 'the CSV has the report\'s columns: ' . implode(' | ', $csvTitles));
        $csvTotals = $kunSalg ? array_diff_key(self::CSV_TOTALS, ['Købspris' => 1, 'Værdi' => 1]) : self::CSV_TOTALS;
        $this->assertSame($this->expected($csvTitles, $csvTotals, 'Varenr'), self::underTitles($csvTitles, $csvTotal));
    }
}
