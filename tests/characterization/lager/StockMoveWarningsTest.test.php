<?php
// tests/characterization/lager/StockMoveWarningsTest.test.php
//
// Regression test for the testlab finding "lagerflyt.php reads unguarded
// request keys" (warehouses-transfer-receipt, round 1, both legs).
//
// Opening Flyt vare (lager/lagerflyt.php) from Vareliste's warehouse cell and
// posting a move logged PHP warnings from the page itself: it read
// $_GET['input'], which only the old varer.php popup sends, and
// $_POST['variant_id'], $_POST['batch_kob_id'] and $_POST['batch_kob_antal'],
// which the move form never posts. A move of more than the stock then read
// $reg, which only a move within the stock set. An item opened as
// ?input=<lager>:<vare_id> read an unset $variant_id.
//
// Drives the page as a browser does (support/run_lagerflyt_page.php): open it
// by both URLs, post a move within the stock and one of more than the stock to
// the URL it was opened with (the form has no action). Each request may raise
// no warning from lagerflyt.php (doc/ai/convention_testing_error_visibility.md),
// and the move still moves the stock: out of the batch and the warehouse row
// it came from, into a new batch and row on the other warehouse.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261115 CL/ASR Integration: the session id comes from CharacterizationEnv::sessionId(), master's per-class test database (2bd490c6) replaced SESSION_ID.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class StockMoveWarningsTest extends CharacterizationTestCase
{
    private const PAGE = 'lager/lagerflyt.php';
    private const STOCK = 10;
    private const PRIS = 12.5;

    private int $itemId = 0;
    private string $varenr = '';
    /** @var list<int> the warehouse groups (grupper art 'LG') this test added */
    private array $warehouses = [];

    protected function setUp(): void
    {
        foreach ([1 => 'Hovedlager', 2 => 'Filial'] as $kodenr => $name) {
            if (self::one("SELECT id FROM grupper WHERE art = 'LG' AND kodenr = $1", [$kodenr]) === null) {
                $row = self::one(
                    "INSERT INTO grupper (beskrivelse, art, kodenr, fiscal_year) VALUES ($1, 'LG', $2, $3) RETURNING id",
                    [$name, $kodenr, self::$regnaar]
                );
                $this->warehouses[] = (int)$row['id'];
            }
        }
        $this->varenr = 'lflt' . bin2hex(random_bytes(4));
        $item = self::$fx->item(['varenr' => $this->varenr, 'beholdning' => self::STOCK, 'kostpris' => self::PRIS]);
        $this->itemId = $item['id'];
        self::rows(
            "INSERT INTO batch_kob (kobsdate, fakturadate, vare_id, variant_id, linje_id, ordre_id, pris, antal, rest, lager)
             VALUES ('2026-01-05', '2026-01-05', $1, 0, 0, 0, $2, $3, $3, 1)",
            [$this->itemId, self::PRIS, self::STOCK]
        );
        self::rows(
            'INSERT INTO lagerstatus (vare_id, lager, variant_id, beholdning) VALUES ($1, 1, 0, $2)',
            [$this->itemId, self::STOCK]
        );
    }

    protected function tearDown(): void
    {
        self::rows('DELETE FROM batch_salg WHERE vare_id = $1', [$this->itemId]);
        self::rows('DELETE FROM batch_kob WHERE vare_id = $1', [$this->itemId]);
        self::rows('DELETE FROM lagerstatus WHERE vare_id = $1', [$this->itemId]);
        self::rows('DELETE FROM varer WHERE id = $1', [$this->itemId]);
        foreach ($this->warehouses as $id) {
            self::rows('DELETE FROM grupper WHERE id = $1', [$id]);
        }
        $this->warehouses = [];
    }

    /**
     * One request to lagerflyt.php, which may raise no warning from the page itself.
     *
     * @param array<string, string> $post the move form's fields, or [] for a GET
     * @return string the page's HTML
     */
    private function page(string $query, array $post = []): string
    {
        $res = $this->runChildJson('run_lagerflyt_page.php', [$query, http_build_query($post), CharacterizationEnv::sessionId()]);
        $own = array_values(array_filter($res['warnings'], static fn(string $warning): bool => str_starts_with($warning, self::PAGE . ':')));
        $this->assertSame([], $own, ($post === [] ? 'GET' : 'POST') . ' ' . self::PAGE . "?$query raised warnings");
        return $res['html'];
    }

    /**
     * The form's fields as the page drew them for warehouse 1, with Antal and Til lager filled in.
     *
     * @return array<string, string>
     */
    private function moveForm(string $html, int $antal): array
    {
        $fields = [];
        foreach (['vare_id', 'lager', 'max_antal'] as $name) {
            $this->assertSame(1, preg_match("/<input type=hidden name='$name' value='([^']*)'>/", $html, $m), "the form carries $name");
            $fields[$name] = $m[1];
        }
        return $fields + ['antal' => (string)$antal, 'nyt_lager' => '2', 'opdater' => 'Opdater'];
    }

    /** @return array<int, float> beholdning by warehouse */
    private function stockByWarehouse(): array
    {
        $stock = [];
        foreach (self::rows('SELECT lager, beholdning FROM lagerstatus WHERE vare_id = $1 AND variant_id = 0 ORDER BY lager', [$this->itemId]) as $row) {
            $stock[(int)$row['lager']] = (float)$row['beholdning'];
        }
        return $stock;
    }

    /**
     * The URLs that open the page: Vareliste's and Ordrestatus's warehouse cell, the item card's, and the old varer.php popup.
     *
     * @return array<string, array{0: string}>
     */
    public static function openingUrls(): array
    {
        return [
            'Vareliste' => ['lager=1&vare_id=%d&returside=../lager/lister/vareliste.php'],
            'item card' => ['lager=1&vare_id=%d'],
            'varer.php popup' => ['input=1:%d'],
        ];
    }

    #[DataProvider('openingUrls')]
    public function test_opening_the_page_raises_no_warning(string $url): void
    {
        $html = $this->page(sprintf($url, $this->itemId));

        $this->assertStringContainsString("Flyt vare {$this->varenr} fra lager 1", $html);
        $this->assertStringContainsString("<input type=hidden name='max_antal' value='10.000'>", $html);
        $this->assertStringContainsString("<option value='2'>2 : Filial</option>", $html);
        $this->assertStringNotContainsString("<option value='1'>", $html, 'the item is not moved to the warehouse it is on');
    }

    public function test_a_move_within_the_stock_moves_it_without_warnings(): void
    {
        $query = sprintf('lager=1&vare_id=%d&returside=../lager/lister/vareliste.php', $this->itemId);
        $html = $this->page($query, $this->moveForm($this->page($query), 3));

        $this->assertStringContainsString("alert('3 flyttet fra lager 1 til lager 2')", $html);
        $this->assertSame([1 => 7.0, 2 => 3.0], $this->stockByWarehouse());
        $batches = self::rows('SELECT lager, variant_id, pris, antal, rest FROM batch_kob WHERE vare_id = $1 ORDER BY lager', [$this->itemId]);
        $this->assertSame(
            [['1', '0', '12.500', '10.000', '7.000'], ['2', '0', '12.500', '3.000', '3.000']],
            array_map('array_values', $batches),
            'the moved 3 leave the batch on warehouse 1 and arrive as a batch on warehouse 2 at its price, without a variant'
        );
        $out = self::rows('SELECT lager, variant_id, antal FROM batch_salg WHERE vare_id = $1', [$this->itemId]);
        $this->assertSame([['1', '0', '3.000']], array_map('array_values', $out));
    }

    public function test_a_move_of_more_than_the_stock_moves_nothing_without_warnings(): void
    {
        $query = sprintf('lager=1&vare_id=%d', $this->itemId);
        $html = $this->page($query, $this->moveForm($this->page($query), 22));

        $this->assertStringContainsString("alert('Du kan maks flytte 10,00')", $html);
        $this->assertStringNotContainsString('flyttet fra lager', $html);
        $this->assertSame([1 => 10.0], $this->stockByWarehouse());
        $this->assertSame(1, count(self::rows('SELECT id FROM batch_kob WHERE vare_id = $1', [$this->itemId])));
        $this->assertSame([], self::rows('SELECT id FROM batch_salg WHERE vare_id = $1', [$this->itemId]));
    }
}
