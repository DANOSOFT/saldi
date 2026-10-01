<?php
// 20261001 CDX/LAH Characterize open-post aging boundaries, settlement, currency conversion and rounded totals without a database.
// 20261001 CL/LAH Per-post rounded buckets match openKontrol, screen/CSV row total helper, and SQL/PHP account parity for ambiguous rates on an in-memory SQLite table.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the report's shared aging calculation with real date/rounding helpers.
 * Only foreign-currency database lookups are stubbed; no tenant data is accessed. The SQL parity
 * test runs the generated account predicate (mysqli dialect) against an in-memory SQLite table.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class OpenpostAgingTest extends TestCase
{
    private const TODAY = '2026-10-01';
    private const BUCKET_KEYS = [
        'forfalden_plus90', 'forfalden_plus60', 'forfalden_plus30',
        'forfalden_plus8', 'forfalden', 'ikke_forfalden',
    ];

    private string $tz;

    protected function setUp(): void
    {
        $this->tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Copenhagen');
        $GLOBALS['baseCurrency'] = 'DKK';
        $GLOBALS['openpost_test_queries'] = [];
        $GLOBALS['openpost_test_kurs'] = 750;

        function db_escape_string($value)
        {
            return str_replace("'", "''", (string)$value);
        }

        function db_select($query, $errorFileLine)
        {
            $GLOBALS['openpost_test_queries'][] = $query;
            // The currency code doubles as its rate group, so a test can give each currency its rate.
            if (str_starts_with($query, 'select kodenr from grupper')) {
                preg_match("/box1 = '([^']*)'/", $query, $match);
                return ['kodenr' => $match[1]];
            }
            if (str_starts_with($query, 'select kurs from valuta')) {
                preg_match("/gruppe ='([^']*)'/", $query, $match);
                $kurs = $GLOBALS['openpost_test_kurs'];
                return ['kurs' => is_array($kurs) ? ($kurs[$match[1]] ?? 750) : $kurs];
            }
            throw new LogicException('Unexpected database query in aging test: ' . $query);
        }

        function db_fetch_array($result)
        {
            return $result;
        }

        require_once __DIR__ . '/../../../includes/std_func.php';
        require_once __DIR__ . '/../../../includes/forfaldsdag.php';
        require_once __DIR__ . '/../../../includes/reportFunc/showOpenPosts.php';
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->tz);
    }

    /**
     * @return array{
     *   amount: float, valuta: string, valutakurs: float, transdate: string,
     *   forfaldsdate: string, udlignet: string, udlign_date: string|null,
     * }
     */
    private static function post(string $due, float $amount = 100.0, array $override = []): array
    {
        return array_replace([
            'amount' => $amount,
            'valuta' => 'DKK',
            'valutakurs' => 100.0,
            'transdate' => $due,
            'forfaldsdate' => $due,
            'udlignet' => '0',
            'udlign_date' => null,
        ], $override);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function agingBoundaries(): array
    {
        return [
            'future due date' => ['2026-10-02', 'ikke_forfalden'],
            'due today' => ['2026-10-01', 'ikke_forfalden'],
            'one day overdue' => ['2026-09-30', 'forfalden'],
            'seven days overdue' => ['2026-09-24', 'forfalden'],
            'eight days overdue' => ['2026-09-23', 'forfalden_plus8'],
            '29 days overdue' => ['2026-09-02', 'forfalden_plus8'],
            '30 days overdue' => ['2026-09-01', 'forfalden_plus30'],
            '59 days overdue' => ['2026-08-03', 'forfalden_plus30'],
            '60 days overdue' => ['2026-08-02', 'forfalden_plus60'],
            '89 days overdue' => ['2026-07-04', 'forfalden_plus60'],
            '90 days overdue (existing >=90 rule)' => ['2026-07-03', 'forfalden_plus90'],
            '91 days overdue' => ['2026-07-02', 'forfalden_plus90'],
        ];
    }

    #[DataProvider('agingBoundaries')]
    public function test_post_lands_in_exactly_one_bucket(string $due, string $expected): void
    {
        $cache = [];
        $aging = openpost_account_aging([self::post($due)], self::TODAY, self::TODAY, 'D', $cache);

        foreach (self::BUCKET_KEYS as $key) {
            self::assertEquals($key === $expected ? 100.0 : 0.0, $aging[$key], $key);
        }
        self::assertEquals(100.0, $aging['bucketTotal']);
        self::assertEquals($expected === 'ikke_forfalden' ? 0.0 : 100.0, $aging['rykkerbelob']);
        self::assertSame([], $GLOBALS['openpost_test_queries']);
    }

    #[DataProvider('agingBoundaries')]
    public function test_aligned_posts_land_nowhere(string $due, string $unused): void
    {
        $cache = [];
        $post = self::post($due, 100.0, ['udlignet' => '1', 'udlign_date' => '2026-09-01']);
        $aging = openpost_account_aging([$post], self::TODAY, self::TODAY, 'D', $cache);

        foreach (self::BUCKET_KEYS as $key) {
            self::assertEquals(0.0, $aging[$key], $key);
        }
        self::assertEquals(0.0, $aging['bucketTotal']);
        self::assertEquals(0.0, $aging['openY']);
        self::assertEquals(0.0, $aging['rykkerbelob']);
        self::assertEquals(100.0, $aging['y']);
        self::assertSame(1, $aging['accountAligned']);
    }

    #[DataProvider('agingBoundaries')]
    public function test_credit_posts_keep_their_negative_balance(string $due, string $expected): void
    {
        $cache = [];
        $aging = openpost_account_aging([self::post($due, -12.34)], self::TODAY, self::TODAY, 'D', $cache);

        foreach (self::BUCKET_KEYS as $key) {
            self::assertEquals($key === $expected ? -12.34 : 0.0, $aging[$key], $key);
        }
        self::assertEquals(-12.34, $aging['bucketTotal']);
    }

    public function test_later_settlement_is_still_open_at_a_historical_report_date(): void
    {
        $cache = [];
        $post = self::post('2026-09-20', 100.0, ['udlignet' => '1', 'udlign_date' => '2026-09-25']);
        $aging = openpost_account_aging([$post], '2026-09-20', self::TODAY, 'D', $cache);

        self::assertEquals(100.0, $aging['ikke_forfalden']);
        self::assertSame(0, $aging['accountAligned']);
        self::assertTrue(openpost_account_visible($aging, '2026-09-20', self::TODAY, '', '', false));
    }

    public function test_settlement_on_report_date_is_aligned(): void
    {
        $cache = [];
        $post = self::post('2026-09-19', 100.0, ['udlignet' => '1', 'udlign_date' => '2026-09-20']);
        $aging = openpost_account_aging([$post], '2026-09-20', self::TODAY, 'D', $cache);

        self::assertEquals(0.0, $aging['bucketTotal']);
        self::assertFalse(openpost_account_visible($aging, '2026-09-20', self::TODAY, '', '', false));
    }

    public function test_placeholder_foreign_rate_is_resolved_without_a_database(): void
    {
        $cache = [];
        $post = self::post('2026-10-02', 2.0, ['valuta' => 'EUR']);
        $aging = openpost_account_aging([$post, $post], self::TODAY, self::TODAY, 'D', $cache);

        self::assertEquals(30.0, $aging['ikke_forfalden']);
        self::assertEquals(30.0, $aging['openKontrol']);
        self::assertCount(2, $GLOBALS['openpost_test_queries']);
    }

    public function test_fully_settled_raw_residual_is_hidden_after_per_post_rounding(): void
    {
        $cache = [];
        $posts = [];
        for ($i = 0; $i < 3; $i++) {
            $posts[] = self::post('2026-09-01', 0.0148, ['udlignet' => '1']);
            $posts[] = self::post('2026-09-01', -0.01, ['udlignet' => '1']);
        }
        // The old SQL raw SUM passes its cent threshold; PHP's real sums do not.
        self::assertGreaterThanOrEqual(0.01, array_sum(array_column($posts, 'amount')));
        $aging = openpost_account_aging($posts, self::TODAY, self::TODAY, 'D', $cache);

        self::assertFalse(openpost_account_visible($aging, self::TODAY, self::TODAY, '', '', true));
    }

    public function test_historical_open_view_ignores_a_settled_balance(): void
    {
        $cache = [];
        $post = self::post('2026-09-01', 100.0, ['udlignet' => '1', 'udlign_date' => '2026-09-10']);
        $aging = openpost_account_aging([$post], '2026-09-20', self::TODAY, 'D', $cache);

        self::assertEquals(100.0, $aging['y']);
        self::assertEquals(0.0, $aging['openKontrol']);
        self::assertFalse(openpost_account_visible($aging, '2026-09-20', self::TODAY, '', '', false));
    }

    /** @return array<string, array{0: float}> */
    public static function roundingSigns(): array
    {
        return ['debt' => [1.0], 'credit' => [-1.0]];
    }

    #[DataProvider('roundingSigns')]
    public function test_display_total_sums_all_six_rounded_buckets(float $sign): void
    {
        $cache = [];
        $posts = [];
        foreach (['2026-07-02', '2026-08-02', '2026-09-01', '2026-09-23', '2026-09-30', '2026-10-02'] as $due) {
            $posts[] = self::post($due, $sign, ['valuta' => 'EUR', 'valutakurs' => 333.3]);
        }
        $aging = openpost_account_aging($posts, self::TODAY, self::TODAY, 'D', $cache);
        self::assertTrue(openpost_account_visible($aging, self::TODAY, self::TODAY, '', '', false));

        $displayedCents = 0;
        foreach (self::BUCKET_KEYS as $key) {
            self::assertEquals(3.33 * $sign, $aging[$key]);
            $displayedCents += (int)round($aging[$key] * 100);
        }
        self::assertSame((int)round($aging['bucketTotal'] * 100), $displayedCents);
        self::assertEquals(19.98 * $sign, $aging['bucketTotal']);
        self::assertNotEquals(afrund($aging['visY'], 2), $aging['bucketTotal']);

        // The all-posts view continues to judge/show the full balance independently.
        self::assertTrue(openpost_account_visible($aging, self::TODAY, self::TODAY, '', '', true));
        self::assertEquals(20.0 * $sign, afrund($aging['visY'], 2));
    }

    /** @return array<string, array{0: string, 1: float, 2: bool, 3: bool, 4: bool}> */
    public static function cellSigns(): array
    {
        return [
            'debtor debt' => ['D', 10.0, true, true, false],
            'debtor credit' => ['D', -10.0, true, false, true],
            'creditor debt' => ['K', -10.0, true, true, false],
            'creditor credit' => ['K', 10.0, true, false, true],
            'zero' => ['D', 0.0, true, false, false],
            'not yet due' => ['D', 10.0, false, false, false],
        ];
    }

    #[DataProvider('cellSigns')]
    public function test_aging_cell_uses_its_own_amount_and_debt_sign(
        string $art, float $amount, bool $overdue, bool $red, bool $credit
    ): void {
        foreach ([false, true] as $showZero) {
            $html = openpost_aging_cell($amount, $art, $overdue, $showZero);
            self::assertSame($red, str_contains($html, 'rgb(255, 0, 0)'));
            self::assertSame($credit, str_contains($html, 'op-credit'));
        }
    }

    public function test_buckets_add_up_to_the_per_post_rounded_control_sum(): void
    {
        $cache = [];
        // 3 x 1.000 EUR at 333.3 = 3.333 DKK each, all in the same bucket: rounding the bucket
        // sum would give 10.00, the kontokort (one rounded row per post) shows 9.99.
        $posts = [];
        for ($i = 0; $i < 3; $i++) {
            $posts[] = self::post('2026-09-30', 1.0, ['valuta' => 'EUR', 'valutakurs' => 333.3]);
        }
        $aging = openpost_account_aging($posts, self::TODAY, self::TODAY, 'D', $cache);
        self::assertTrue(openpost_account_visible($aging, self::TODAY, self::TODAY, '', '', false));

        self::assertEquals(9.99, $aging['forfalden']);
        self::assertEquals(9.99, $aging['bucketTotal']);
        self::assertEquals(9.99, $aging['visKontrol']);
        self::assertEquals(9.99, openpost_row_total($aging, false));
    }

    public function test_buckets_equal_the_open_control_sum_for_every_rate_shape(): void
    {
        // Zero rates ('0.000' from the database, numeric 0) and '-' currency posts take the native
        // amount in the control sum; the buckets must follow it, not drop those posts to 0.
        $GLOBALS['openpost_test_kurs'] = ['EUR' => '0.000', 'SEK' => 750];
        foreach (self::parityAccounts() as $id => $posts) {
            $cache = [];
            $aging = openpost_account_aging($posts, self::TODAY, self::TODAY, 'D', $cache);
            $buckets = 0.0;
            foreach (['forfalden', 'forfalden_plus8', 'forfalden_plus30', 'forfalden_plus60', 'forfalden_plus90', 'ikke_forfalden'] as $key) {
                $buckets += $aging[$key];
            }
            self::assertEqualsWithDelta($aging['openKontrol'], $buckets, 0.001, "account $id");
        }
    }

    public function test_row_total_is_the_bucket_sum_or_the_historic_full_sum(): void
    {
        $cache = [];
        $posts = [
            self::post('2026-09-30', 1.0, ['valuta' => 'EUR', 'valutakurs' => 333.3]),
            self::post('2026-09-30', 1.0, ['valuta' => 'EUR', 'valutakurs' => 333.3]),
            self::post('2026-09-01', 50.0, ['udlignet' => '1']),
        ];
        $aging = openpost_account_aging($posts, self::TODAY, self::TODAY, 'D', $cache);

        $open = $aging;
        openpost_account_visible($open, self::TODAY, self::TODAY, '', '', false);
        self::assertEquals(6.66, openpost_row_total($open, false));

        // Vis alle poster: raw 56.666 vs per-post rounded 56.66 - the control sum wins when they differ.
        $all = $aging;
        openpost_account_visible($all, self::TODAY, self::TODAY, '', '', true);
        self::assertEquals(56.66, openpost_row_total($all, true));

        $plain = openpost_account_aging([self::post('2026-09-30', 12.5)], self::TODAY, self::TODAY, 'D', $cache);
        openpost_account_visible($plain, self::TODAY, self::TODAY, '', '', true);
        self::assertEquals(12.5, openpost_row_total($plain, true));
    }

    /**
     * Accounts whose rate PHP and SQL can read differently. Rates are given the way PHP sees them:
     * PostgreSQL/mysqli return numeric columns as strings ('0.000'), other sources as numbers.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private static function parityAccounts(): array
    {
        return [
            // Placeholder rate that the valuta table resolves to '0.000': PHP converts the EUR post
            // to 0 and the account is in debit; a SQL that read it at 100 would see -50.
            1 => [
                self::post('2026-09-01', 50.0),
                self::post('2026-09-01', -100.0, ['valuta' => 'EUR', 'valutakurs' => '100.000']),
            ],
            // Stored numeric 0: PHP falls back to 100 and resolves 750, so 80 SEK = 600 DKK and the
            // account is in debit; a SQL that kept the 0 would see -40.
            2 => [
                self::post('2026-09-01', -40.0),
                self::post('2026-09-01', 80.0, ['valuta' => 'SEK', 'valutakurs' => 0]),
            ],
            // NULL rate on a base-currency post: 100 on both sides.
            3 => [self::post('2026-09-01', 10.0, ['valutakurs' => null])],
            // Plain credit account, no ambiguous rate: the predicates must still filter it.
            4 => [self::post('2026-09-01', -20.0)],
            // Fully settled before every report date: never listed.
            5 => [
                self::post('2026-08-01', 30.0, ['udlignet' => '1', 'udlign_date' => '2026-08-15']),
                self::post('2026-08-01', -30.0, ['udlignet' => '1', 'udlign_date' => '2026-08-15']),
            ],
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function parityModes(): array
    {
        return [
            'open, today' => ['', '', '', self::TODAY],
            'vis alle, today' => ['alle', '', '', self::TODAY],
            'kun debet, today' => ['', 'on', '', self::TODAY],
            'kun kredit, today' => ['', '', 'on', self::TODAY],
            'open, historical' => ['', '', '', '2026-09-20'],
            'vis alle, historical' => ['alle', '', '', '2026-09-20'],
            'kun debet, historical' => ['', 'on', '', '2026-09-20'],
        ];
    }

    #[DataProvider('parityModes')]
    public function test_sql_never_drops_an_account_php_lists(string $alle, string $debet, string $kredit, string $todate): void
    {
        $GLOBALS['openpost_test_kurs'] = ['EUR' => '0.000', 'SEK' => 750];
        $vis_alle = ($alle === 'alle');
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('create table openpost (konto_id integer, amount numeric, valuta text, valutakurs numeric, transdate text, forfaldsdate text, udlignet text, udlign_date text)');
        $insert = $pdo->prepare('insert into openpost values (?, ?, ?, ?, ?, ?, ?, ?)');
        $php = [];
        $cache = [];
        foreach (self::parityAccounts() as $id => $posts) {
            foreach ($posts as $r) {
                $insert->execute([$id, $r['amount'], $r['valuta'], $r['valutakurs'], $r['transdate'], $r['forfaldsdate'], $r['udlignet'], $r['udlign_date']]);
            }
            $aging = openpost_account_aging($posts, $todate, self::TODAY, 'D', $cache);
            if (openpost_account_visible($aging, $todate, self::TODAY, $debet, $kredit, $vis_alle)) {
                $php[] = $id;
            }
        }
        $parts = openpost_account_query_parts('', '', 'D', 1, $todate, self::TODAY, $vis_alle, $debet, $kredit, 'mysqli');
        $sql = array_map('intval', $pdo->query("select konto_id from ({$parts['accountGroup']}) g order by konto_id")->fetchAll(PDO::FETCH_COLUMN));

        self::assertSame([], array_values(array_diff($php, $sql)), 'accounts PHP lists but SQL drops');
        self::assertNotContains(5, $sql);
        if ($debet) {
            self::assertContains(1, $php);
            self::assertContains(2, $php);
            self::assertNotContains(4, $sql);
        }
        if ($kredit) {
            self::assertContains(4, $sql);
        }
    }
}
