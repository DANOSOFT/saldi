<?php
// tests/characterization/debitor/OpenPostReportLayoutTest.test.php
//
// Regression tests for PR #450, driven through debitor/rapport.php the way the
// browser does (see support/run_openpost_report.php).
//
//   1. The account grid of Åbne poster (includes/reportFunc/showOpenPosts.php)
//      has nine columns, or ten when the company uses BS (Betalingsservice).
//      The separator, action and pagination rows were hardcoded to span ten,
//      so without BS they reached into a column the table does not have.
//   2. The "skjul BS"/"vis BS" toggle kept the filters but dropped the report
//      mode and the page size, and the BS choice was read from GET only, so
//      the action buttons (a POST) showed the BS customers again.
//   3. debitor/rapport.php now reads the open-post GET filters raw and every
//      query escapes them itself. openpost() in includes/rapportfunc.php did
//      not: the dunning overview at the bottom of the report put the firm-name
//      search into its SQL unescaped, and the box7 update the view flags.
//   4. The Salgsstat redirect put the free-text account field into the URL raw.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261027 CL/LH PR #450: created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class OpenPostReportLayoutTest extends CharacterizationTestCase
{
    /** The smallest page the report allows, so a handful of accounts already paginates. */
    private const PAGE_SIZE = 25;

    /** One page and a bit, so both pagination rows and the action row are printed. */
    private const ACCOUNTS = 30;

    private static int $faktnrSeq = 8500;

    /** @var list<array{id: string, pbs_nr: ?string}> pbs_nr of the company's own address rows before the test */
    private array $ownPbs = [];

    /** @var list<array{id: int, kontonr: string}> the debtors with an open post */
    private array $debtors = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        CharacterizationEnv::resyncSequences(self::$tenant);
    }

    protected function setUp(): void
    {
        // The report lists every debtor with an open post and the dunning overview every R order,
        // so only this test's rows may exist.
        self::rows("DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE art LIKE 'R%')");
        self::rows("DELETE FROM ordrer WHERE art LIKE 'R%'");
        self::rows('DELETE FROM openpost');

        // The BS column is shown when the company's own address (art 'S') carries a pbs_nr.
        $this->ownPbs = self::rows("SELECT id, pbs_nr FROM adresser WHERE art = 'S'");
        self::rows("UPDATE adresser SET pbs_nr = '' WHERE art = 'S'");

        $this->debtors = [];
        for ($n = 0; $n < self::ACCOUNTS; $n++) {
            $debtor = self::$fx->debtor();
            self::$fx->openPost($debtor, 100.00 + $n, 20, (string)(++self::$faktnrSeq));
            $this->debtors[] = $debtor;
        }
    }

    protected function tearDown(): void
    {
        self::rows("DELETE FROM adresser WHERE art = 'S' AND firmanavn = $1", [Fixtures::TAG . ' egen adresse']);
        foreach ($this->ownPbs as $row) {
            self::rows('UPDATE adresser SET pbs_nr = $1 WHERE id = $2', [$row['pbs_nr'], (int)$row['id']]);
        }
    }

    private function useBs(): void
    {
        if (self::rows("UPDATE adresser SET pbs_nr = '12345678' WHERE art = 'S' RETURNING id") === []) {
            self::rows(
                "INSERT INTO adresser (kontonr, firmanavn, art, pbs_nr) VALUES ('0', $1, 'S', '12345678')",
                [Fixtures::TAG . ' egen adresse']
            );
        }
    }

    /**
     * @param array<string, string> $get
     * @param array<string, string|array<int, string>> $post
     * @return string the page HTML
     */
    private function page(array $get, array $post = []): string
    {
        $res = self::runChild(
            'run_openpost_report.php',
            [CharacterizationEnv::SESSION_ID, json_encode(['get' => (object)$get, 'post' => (object)$post])]
        );
        return $res['stdout'];
    }

    /**
     * The open posts report the way the "Åbne poster" button and its own links request it.
     *
     * @param array<string, string|null> $extra  Request parameters to add; null removes a default.
     */
    private function report(array $extra = []): string
    {
        $get = array_merge([
            'rapportart' => 'openpost',
            'submit' => 'ok',
            'openpost_content' => '1',
            'vis_aabenpost' => 'on',
            'openpost_page_size' => (string)self::PAGE_SIZE,
        ], $extra);
        return $this->page(array_filter($get, static fn($value) => $value !== null));
    }

    /**
     * Every row of the account grid with the number of columns it spans.
     *
     * @return list<array{span: int, html: string}>
     */
    private function gridRows(string $html): array
    {
        $start = strpos($html, "id='visAabnePosterTable'");
        $this->assertNotFalse($start, "the account grid is missing from the page:\n" . substr($html, 0, 3000));
        $end = strpos($html, '</table>', $start);
        $this->assertNotFalse($end, 'the account grid is never closed');
        $rows = [];
        foreach (array_slice(preg_split('/<tr\b/i', substr($html, $start, $end - $start)), 1) as $row) {
            preg_match_all('/<t[dh](?=[\s>])([^>]*)>/i', $row, $cells);
            $span = 0;
            foreach ($cells[1] as $attributes) {
                $span += preg_match('/colspan=[\'"]?(\d+)/i', $attributes, $m) ? (int)$m[1] : 1;
            }
            $rows[] = ['span' => $span, 'html' => $row];
        }
        return $rows;
    }

    private function assertEveryRowSpans(int $columns, string $html): void
    {
        $rows = $this->gridRows($html);
        $all = implode("\n", array_column($rows, 'html'));
        // The rows the bug was in must actually be on the page, or the test proves nothing.
        $this->assertStringContainsString('Viser 1-' . self::PAGE_SIZE . ' af ' . self::ACCOUNTS, $all, 'the top pagination row is missing');
        $this->assertStringContainsString('Side 1 af 2', $all, 'the bottom pagination row is missing');
        $this->assertStringContainsString('value="Mail kontoudtog"', $all, 'the action row is missing');
        $this->assertStringContainsString('<hr>', $all, 'the separator rows are missing');

        $this->assertSame($columns, $rows[0]['span'], 'the column header has the wrong number of columns');
        foreach ($rows as $row) {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags('<tr' . $row['html'])));
            $this->assertSame(
                $columns,
                $row['span'],
                "a grid row spans {$row['span']} of the table's $columns columns: " . substr($text !== '' ? $text : $row['html'], 0, 120)
            );
        }
    }

    public function test_without_bs_every_grid_row_spans_the_nine_columns(): void
    {
        $this->assertEveryRowSpans(9, $this->report());
    }

    public function test_with_bs_every_grid_row_spans_the_ten_columns(): void
    {
        $this->useBs();

        $this->assertEveryRowSpans(10, $this->report());
    }

    public function test_the_bs_toggle_keeps_the_report_mode_page_size_and_sort(): void
    {
        $this->useBs();

        $html = $this->report([
            'vis_aabenpost' => null,
            'kun_debet' => 'on',
            'order_by' => 'amount',
            'order_dir' => 'asc',
        ]);

        $this->assertSame(1, preg_match("/<a href='([^']*)'>skjul BS<\\/a>/", $html, $m), 'the "skjul BS" link is missing');
        $this->assertSame(1, substr_count($m[1], 'showPBS='), 'the toggle carries showPBS more than once: ' . $m[1]);
        parse_str((string)parse_url($m[1], PHP_URL_QUERY), $query);
        $this->assertSame('0', $query['showPBS'] ?? null, 'the toggle does not hide the BS customers');
        $this->assertSame('on', $query['kun_debet'] ?? null, 'the toggle drops the "Kun konti i debet" mode');
        $this->assertSame((string)self::PAGE_SIZE, $query['openpost_page_size'] ?? null, 'the toggle drops the page size');
        $this->assertSame('amount', $query['order_by'] ?? null, 'the toggle drops the sort');
        $this->assertSame('asc', $query['order_dir'] ?? null, 'the toggle drops the sort direction');
    }

    public function test_an_action_button_keeps_the_bs_customers_hidden(): void
    {
        $this->useBs();
        // The user opens the report with BS customers hidden ...
        $html = $this->report(['showPBS' => '0']);
        $this->assertStringContainsString('<input type=hidden name=showPBS value="0">', $html, 'the form does not post the BS choice back');

        // ... and presses "Mail kontoudtog" with no account ticked. The form posts back to
        // rapport.php with its own fields only, no query string.
        $html = $this->page([], [
            'rapportart' => 'openpost',
            'submit' => 'Mail kontoudtog',
            'dato_fra' => '',
            'dato_til' => '',
            'konto_fra' => '',
            'konto_til' => '',
            'konto_id' => ['1' => (string)$this->debtors[0]['id']],
            'rykkerbelob' => ['1' => '0'],
            'kontoantal' => '1',
            'showPBS' => '0',
            'openpost_page' => '1',
            'openpost_page_size' => (string)self::PAGE_SIZE,
        ]);

        $this->assertStringContainsString('>vis BS</a>', $html, 'the report came back with the BS customers shown again');
        $this->assertStringNotContainsString('>skjul BS</a>', $html);
    }

    /** One dunning (art R1) for a firm name, as debitor/ny_rykker.php leaves it. */
    private function dunning(string $firmanavn): void
    {
        $debtor = self::$fx->debtor(['firmanavn' => $firmanavn]);
        self::rows(
            "INSERT INTO ordrer (konto_id, kontonr, firmanavn, art, ordrenr, ordredate, status, betalt)
             VALUES ($1, $2, $3, 'R1', $4, CURRENT_DATE, 0, '')",
            [$debtor['id'], $debtor['kontonr'], $firmanavn, ++self::$faktnrSeq]
        );
    }

    public function test_the_dunning_overview_finds_a_firm_name_with_an_apostrophe(): void
    {
        $this->dunning(Fixtures::TAG . " O'Brien ApS");
        $this->dunning(Fixtures::TAG . ' Andersen ApS');

        $html = $this->report(['konto_fra' => Fixtures::TAG . " O'Brien*", 'vis_aaben_rykker' => 'on']);

        $this->assertStringContainsString('rykkerOverviewTable1', $html, 'the dunning overview is missing');
        $this->assertStringContainsString('<td>' . Fixtures::TAG . " O'Brien ApS</td>", $html, "the dunning for O'Brien is not listed");
        $this->assertStringNotContainsString(Fixtures::TAG . ' Andersen ApS', $html);
    }

    public function test_the_dunning_overview_search_cannot_widen_the_query(): void
    {
        $this->dunning(Fixtures::TAG . ' Andersen ApS');

        $html = $this->report([
            'konto_fra' => "nobody' or firmanavn like '" . Fixtures::TAG . ' Andersen*',
            'vis_aaben_rykker' => 'on',
        ]);

        $this->assertStringNotContainsString(
            Fixtures::TAG . ' Andersen ApS',
            $html,
            "a quote in the account search was spliced into the dunning overview's SQL"
        );
    }

    public function test_the_view_flags_cannot_rewrite_the_stored_report_settings(): void
    {
        $this->report();
        $before = self::one("SELECT box1 FROM grupper WHERE art = 'DRV' AND kodenr = '1'");
        $this->assertNotNull($before, 'the report did not store its view flags');

        try {
            $this->report(['vis_aaben_rykker' => "on', box1='" . Fixtures::TAG . ' injected']);

            $after = self::one("SELECT box1, box7 FROM grupper WHERE art = 'DRV' AND kodenr = '1'");
            $this->assertSame($before['box1'], $after['box1'], 'a quote in a view flag was spliced into the box7 update');
            $this->assertStringContainsString("box1='", (string)$after['box7'], 'the flag is not stored as the literal text it was');
        } finally {
            self::rows("UPDATE grupper SET box7 = 'on;;;;;;' WHERE art = 'DRV' AND kodenr = '1'");
        }
    }

    public function test_the_salgsstat_redirect_keeps_a_free_text_account_intact(): void
    {
        $account = Fixtures::TAG . ' "A&B" ApS';

        $html = $this->page([], ['salgsstat' => 'Salgsstat', 'konto' => $account, 'dato' => '']);

        $this->assertSame(
            1,
            preg_match('/<meta http-equiv="refresh" content="([^"]*)"/', $html, $m),
            "the Salgsstat redirect is missing:\n" . substr($html, -2000)
        );
        $url = substr(html_entity_decode($m[1], ENT_QUOTES), strlen('1;URL='));
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($account, $query['konto_fra'] ?? null, 'the account reached salgsstat.php mangled: ' . $m[1]);
        $this->assertSame('D', $query['art'] ?? null);
    }
}
