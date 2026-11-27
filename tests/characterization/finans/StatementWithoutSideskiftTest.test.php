<?php
// tests/characterization/finans/StatementWithoutSideskiftTest.test.php
//
// Finans -> Rapporter, the statement reports Resultat, Resultat/budget, Resultat/sidste år and Balance
// (finans/rapport.php, finans/rapport_includes/regnskab.php) on a chart of accounts without a Sideskift
// account (kontotype X).
//
// rapport.php splits the result from the balance by account number around the chart's Sideskift account.
// The standard chart new regnskaber get (importfiler/kontoplan.txt) has none, so each of the four reports
// showed the whole chart, after an alert that the two could not be separated. Without a Sideskift account
// the reports now split by account type (regnskab_kontoside(), tests/includes/regnskabKontoside.test.php):
// Resultat, Budget and Sidste år show the Drift accounts and the headings and sums that belong to them,
// Balance the Status accounts, the Resultat account (kontotype R, the year's result) and their headings and
// sums. The amounts are computed as before, and Fra konto / Til konto still limit the lines. A chart with a
// Sideskift account is split by number, as before. Balance alerts when such a chart has no Resultat account,
// as the year's result is then not in the balance.
//
// The fixture is the installed standard chart, each account's name prefixed with its number so the lines on
// screen can be told apart (headings and sums print no number), with an opening balance of 2.500 on the bank
// 6480 against the share capital 6510, a sale of 10.000 (1010 against 6480) and rent of 4.000 (2030 against
// 6480) in the fiscal year, and a budget on 1010 and 2030. The year's result is -6.000 (a profit), so the
// bank holds 8.500 and the liabilities and equity, the result included through 6935, -8.500.
// The fiscal year after it has a small chart with a Sideskift account at 5000, an S account numbered below
// it and a D account above it. Its four reports are compared line by line with the output of the code before this
// change (tests/fixtures/statement-sideskift/: the statement's lines with their amounts, and its CSV, recorded from
// that code). The page header, menu and styling around the statement are not compared.
// One test adds a further fiscal year with importfiler/kontoplan2.txt, an older chart whose sums start at their
// heading and which, like every chart imported through systemdata/importer_kontoplan.php, has no Sideskift account.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261119 CL/ASR Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class StatementWithoutSideskiftTest extends CharacterizationTestCase
{
    /** The alert the reports showed for a chart without a Sideskift account. */
    private const ALERT = 'Sideskiftkonto ikke defineret i kontoplan';
    /** The alert Balance shows for a chart without a Sideskift account and without a Resultat account. */
    private const NO_RESULT_ALERT = "alert('Kontoplanen har ingen konto af typen Resultat";
    /** The standard chart's balance starts at H 5000 BALANCE; its result side is below it. */
    private const BALANCE_FROM = 5000;
    /** The recorded output of the reports on the chart with a Sideskift account. */
    private const FIXTURE_DIR = __DIR__ . '/../../fixtures/statement-sideskift';
    private const KINDS = ['resultat', 'balance', 'budget', 'lastYear'];

    /** @var array{startMonth:int, startYear:int, endMonth:int, endYear:int} the fiscal year of the installed tenant */
    private static array $ra = [];
    /** The day of the postings in the installed fiscal year (Y-m-d). */
    private static string $day = '';
    /** The fiscal year with the Sideskift chart, the one after the installed year. */
    private static int $sideskiftYear = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        CharacterizationEnv::resyncSequences(self::$tenant);
        // The report writes its CSV to temp/<db>/, which the login creates; these requests skip the login.
        if (!is_dir(dirname(self::csvPath()))) {
            mkdir(dirname(self::csvPath()), 0770, true);
        }
        // The first page a user opens stores their settings (includes/online.php: grupper USET, menu 'S') and is drawn
        // without them. Open the report front page first, so every report below is drawn alike whichever test runs first.
        self::runChild('run_finans_rapport_page.php', [CharacterizationEnv::sessionId(), '']);

        $ra = self::one("SELECT box1, box2, box3, box4 FROM grupper WHERE art = 'RA' AND kodenr = $1", [self::$regnaar]);
        self::$ra = ['startMonth' => (int)$ra['box1'], 'startYear' => (int)$ra['box2'], 'endMonth' => (int)$ra['box3'], 'endYear' => (int)$ra['box4']];

        // The installed standard chart. Each name starts with the account number.
        self::rows("UPDATE kontoplan SET beskrivelse = kontonr::text || ' ' || beskrivelse WHERE regnskabsaar = $1", [self::$regnaar]);
        self::primo(self::$regnaar, 6480, 2500);
        self::primo(self::$regnaar, 6510, -2500);
        self::$day = sprintf('%04d-%02d-15', self::$ra['startYear'], self::$ra['startMonth']);
        self::post(self::$day, 1, 6480, 1010, 10000.00, 'Salg');
        self::post(self::$day, 2, 2030, 6480, 4000.00, 'Husleje');
        self::budget(self::$regnaar, 1010, -12000);
        self::budget(self::$regnaar, 2030, 4500);

        // The following fiscal year, with a small chart that has a Sideskift account at 5000, an S account numbered below
        // it (2035) and a D account above it (6475).
        self::$sideskiftYear = self::$regnaar + 1;
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, box6, fiscal_year)
             SELECT $1, kode, $2, art, box1, (box2::int + 1)::text, box3, (box4::int + 1)::text, box5, box6, $2
             FROM grupper WHERE art = 'RA' AND kodenr = $3",
            [Fixtures::TAG . ' sideskift', self::$sideskiftYear, self::$regnaar]
        );
        foreach (self::sideskiftChart() as [$kontonr, $type, $name, $fra]) {
            self::account(self::$sideskiftYear, $kontonr, $type, "$kontonr $name", $fra);
        }
        self::primo(self::$sideskiftYear, 6480, 1000);
        self::primo(self::$sideskiftYear, 6510, -1000);
        $day = sprintf('%04d-%02d-15', self::$ra['startYear'] + 1, self::$ra['startMonth']);
        self::post($day, 11, 6480, 1010, 8000.00, 'Salg');
        self::post($day, 12, 2030, 6480, 3000.00, 'Husleje');
        self::post($day, 13, 2035, 6475, 300.00, 'Mellemregning');
        self::budget(self::$sideskiftYear, 1010, -9000);
    }

    /**
     * The chart of the Sideskift year: kontonr, kontotype, name, fra_kto.
     *
     * @return list<array{0:int, 1:string, 2:string, 3:int}>
     */
    private static function sideskiftChart(): array
    {
        return [
            [1000, 'H', 'RESULTATOPGØRELSE', 0],
            [1001, 'H', 'Nettoomsætning', 0],
            [1010, 'D', 'Salg af varer og ydelser', 0],
            [1050, 'D', 'Salg af varer udland, EU', 0],
            [1390, 'Z', 'Nettoomsætning', 1010],
            [2020, 'H', 'Lokaleomkostninger', 0],
            [2030, 'D', 'Husleje', 0],
            [2035, 'S', 'Mellemregning', 0],
            [2210, 'Z', 'Lokaleomkostninger', 2030],
            [4999, 'Z', 'Årets resultat', 1010],
            [5000, 'X', 'BALANCE', 0],
            [5001, 'H', 'AKTIVER', 0],
            [6462, 'H', 'Likvide beholdninger', 0],
            [6475, 'D', 'Kassedifference', 0],
            [6480, 'S', 'Bankkonto', 0],
            [6490, 'Z', 'Likvide beholdninger', 6470],
            [6499, 'Z', 'AKTIVER I ALT', 5010],
            [6500, 'H', 'PASSIVER', 0],
            [6501, 'H', 'Egenkapital', 0],
            [6510, 'S', 'Registreret kapital mv.', 0],
            [6931, 'H', 'Overført resultat', 0],
            [6935, 'R', 'Årets resultat', 4999],
            [6940, 'S', 'Overført resultat', 0],
            [6971, 'Z', 'Egenkapital i alt', 6510],
            [9999, 'Z', 'PASSIVER I ALT', 6510],
        ];
    }

    /** An account in the chart of fiscal year $regnaar, as Kontoplan saves it. */
    private static function account(int $regnaar, int $kontonr, string $type, string $name, int $fra = 0): void
    {
        self::rows(
            "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, fra_kto, moms, regnskabsaar, lukket, valuta, valutakurs, primo, saldo)
             VALUES ($1, $2, $3, $4, '', $5, '', '0', '100', 0, 0)",
            [$kontonr, $name, $type, $fra, $regnaar]
        );
    }

    private static function primo(int $regnaar, int $kontonr, float $amount): void
    {
        self::rows('UPDATE kontoplan SET primo = $1 WHERE kontonr = $2 AND regnskabsaar = $3', [$amount, $kontonr, $regnaar]);
    }

    /** A voucher of $amount on $debet against $kredit on $day, as finans/bogfor.php leaves it in the ledger. */
    private static function post(string $day, int $bilag, int $debet, int $kredit, float $amount, string $tekst): void
    {
        foreach ([[$debet, $amount, 0], [$kredit, 0, $amount]] as [$konto, $d, $k]) {
            self::rows(
                "INSERT INTO transaktioner (kontonr, bilag, transdate, beskrivelse, debet, kredit, kladde_id, afd, ordre_id, logdate, logtime)
                 VALUES ($1, $2, $3, $4, $5, $6, 0, 0, 0, CURRENT_DATE, '12:00')",
                [$konto, $bilag, $day, Fixtures::TAG . " $tekst", $d, $k]
            );
        }
    }

    /** A budget of $amount on $kontonr in the first month of fiscal year $regnaar (finans/budget.php: md 1-12). */
    private static function budget(int $regnaar, int $kontonr, int $amount): void
    {
        self::rows('INSERT INTO budget (regnaar, md, kontonr, amount) VALUES ($1, 1, $2, $3)', [$regnaar, $kontonr, $amount]);
    }

    /** Where regnskab() writes its CSV (../temp/$db/regnskab.csv). */
    private static function csvPath(): string
    {
        return CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb() . '/regnskab.csv';
    }

    /**
     * Finans -> Rapporter, report $rapportart for the whole of fiscal year $regnaar (default the installed one; a later
     * one is as many calendar years later), as the front page posts it after OK.
     *
     * @param array<string, string> $extra more form fields: konto_fra, konto_til
     * @return array{html:string, csv:string}
     */
    private function report(string $rapportart, int $regnaar = 0, array $extra = []): array
    {
        $shift = $regnaar ? $regnaar - self::$regnaar : 0;
        $form = [
            'regnaar' => (string)($regnaar ?: self::$regnaar),
            'rapportart' => $rapportart,
            'maaned_fra' => (self::$ra['startYear'] + $shift) . '|' . self::$ra['startMonth'],
            'dato_fra' => '1',
            'maaned_til' => (self::$ra['endYear'] + $shift) . '|' . self::$ra['endMonth'],
            'dato_til' => '31',
            'submit' => ' OK ',
        ] + $extra;
        if (is_file(self::csvPath())) {
            unlink(self::csvPath());
        }
        $html = self::runChild('run_finans_rapport_page.php', [CharacterizationEnv::sessionId(), '', http_build_query($form)])['stdout'];
        $this->assertStringContainsString('<!--Function regnskab slut-->', $html, "the $rapportart report did not finish:\n$html");
        return ['html' => $html, 'csv' => is_file(self::csvPath()) ? (string)file_get_contents(self::csvPath()) : ''];
    }

    /**
     * The statement's lines in their order, from what regnskab() prints (between its start and end markers): the name,
     * then the cells after it in the same line (period, then year to date, last year or budget and deviation), as
     * printed. Every name starts with its account number, so headings (no cells) and sums are found too. The markup,
     * the styling and the page header around the lines are left out.
     *
     * @return list<array{0:string, 1:list<string>}>
     */
    private static function statement(string $html): array
    {
        $start = strpos($html, '<!--Function regnskab start-->');
        $end = strpos($html, '<!--Function regnskab slut-->');
        if ($start === false || $end === false) {
            return [];
        }
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . substr($html, $start, $end - $start));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $statement = [];
        foreach ($doc->getElementsByTagName('td') as $td) {
            $name = self::text($td);
            if (!preg_match('/^\d+ \D/u', $name)) {
                continue;
            }
            // The cells that follow in the same line. A sum line is printed without its <tr>, so stop at the next row.
            $cells = [];
            for ($node = $td->nextSibling; $node; $node = $node->nextSibling) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                if (strtolower($node->nodeName) !== 'td') {
                    break;
                }
                $cells[] = self::text($node);
            }
            $statement[] = [$name, $cells];
        }
        return $statement;
    }

    /**
     * The report's lines, in their order: account number => the cells after the name.
     *
     * @return array<int, list<string>>
     */
    private static function lines(string $html): array
    {
        $lines = [];
        foreach (self::statement($html) as [$name, $cells]) {
            $lines[(int)$name] = $cells;
        }
        return $lines;
    }

    /** The statement's lines as text, one line each: the name and the cells after it, tab-separated. */
    private static function statementText(string $html): string
    {
        $text = '';
        foreach (self::statement($html) as [$name, $cells]) {
            $text .= implode("\t", array_merge([$name], $cells)) . "\n";
        }
        return $text;
    }

    private static function text(DOMNode $node): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', $node->textContent));
    }

    /** The page shows no alert that the chart has no Sideskift account (the message only, not the whole page, on failure). */
    private function assertNoSideskiftAlert(string $html, string $kind): void
    {
        $this->assertFalse(str_contains($html, self::ALERT), "the $kind report alerts '" . self::ALERT . "'");
    }

    /**
     * The report shows no line numbered from $from to $to; on failure the message lists the numbers it does show.
     *
     * @param array<int, list<string>> $lines
     */
    private function assertNoLinesBetween(array $lines, int $from, int $to, string $what): void
    {
        $this->assertSame('', implode(' ', array_keys(self::between($lines, $from, $to))), $what);
    }

    /** A Danish amount as dkdecimal() prints it, e.g. -8.500,00, as a number. */
    private static function amount(string $dk): float
    {
        return (float)str_replace(['.', ','], ['', '.'], $dk);
    }

    /**
     * @param array<int, list<string>> $lines
     * @return array<int, list<string>> the lines numbered from $from to $to
     */
    private static function between(array $lines, int $from, int $to): array
    {
        return array_filter($lines, static fn(int $nr): bool => $nr >= $from && $nr <= $to, ARRAY_FILTER_USE_KEY);
    }

    public function test_resultat_shows_the_result_side_of_a_chart_without_sideskift(): void
    {
        $this->assertSame([], self::rows("SELECT kontonr FROM kontoplan WHERE regnskabsaar = $1 AND kontotype = 'X'", [self::$regnaar]), 'the standard chart has no Sideskift account');
        $all = self::lines($this->report('regnskab')['html']);
        $report = $this->report('resultat');
        $lines = self::lines($report['html']);

        // No line from 5000, and the lines of the whole chart's report below 5000, with the same amounts.
        $this->assertNoLinesBetween($lines, self::BALANCE_FROM, PHP_INT_MAX, 'Resultat shows balance lines');
        $this->assertSame(self::between($all, 0, self::BALANCE_FROM - 1), $lines);
        $this->assertSame([], $lines[1000], 'the heading RESULTATOPGØRELSE');
        $this->assertSame(['-10.000,00', '-10.000,00'], $lines[1010]);
        $this->assertSame(['-10.000,00', '-10.000,00'], $lines[1390], 'the sum Nettoomsætning');
        $this->assertSame(['4.000,00', '4.000,00'], $lines[2030]);
        $this->assertSame(['-6.000,00', '-6.000,00'], $lines[4999], 'the sum Årets resultat');
        $this->assertStringContainsString('"";"4999 Årets resultat";"-6.000,00";"-6.000,00";', $report['csv']);
        $this->assertStringNotContainsString('6935 Årets resultat', $report['csv']);
        $this->assertNoSideskiftAlert($report['html'], 'resultat');
    }

    public function test_balance_shows_the_balance_side_and_the_years_result(): void
    {
        $all = self::lines($this->report('regnskab')['html']);
        $report = $this->report('balance');
        $lines = self::lines($report['html']);

        // No line below 5000, and the lines of the whole chart's report from 5000, with the same amounts (opening balances
        // included).
        $this->assertNoLinesBetween($lines, 0, self::BALANCE_FROM - 1, 'Balance shows result lines');
        $this->assertSame(self::between($all, self::BALANCE_FROM, PHP_INT_MAX), $lines);
        $this->assertSame([], $lines[5000], 'the heading BALANCE');
        $this->assertSame(['6.000,00', '8.500,00'], $lines[6480], 'the bank: 2.500 opening balance + 10.000 - 4.000');
        $this->assertSame(['0,00', '-2.500,00'], $lines[6510]);
        $this->assertSame(['-6.000,00', '-6.000,00'], $lines[6935], 'the Resultat account: the year\'s result from 4999');
        $this->assertSame(['6.000,00', '8.500,00'], $lines[6499], 'AKTIVER I ALT');
        $this->assertSame(['-6.000,00', '-8.500,00'], $lines[9999], 'PASSIVER I ALT, the year\'s result included');
        $this->assertSame(0.0, self::amount($lines[6499][1]) + self::amount($lines[9999][1]), 'the balance balances');
        $this->assertStringContainsString('"6935";"6935 Årets resultat";"-6.000,00";"-6.000,00";', $report['csv']);
        $this->assertStringNotContainsString('4999 Årets resultat', $report['csv']);
        $this->assertNoSideskiftAlert($report['html'], 'balance');
    }

    public function test_budget_and_last_year_show_the_result_side(): void
    {
        $resultat = self::lines($this->report('resultat')['html']);
        foreach (['budget', 'lastYear'] as $kind) {
            $report = $this->report($kind);
            $lines = self::lines($report['html']);
            $this->assertNoLinesBetween($lines, self::BALANCE_FROM, PHP_INT_MAX, "$kind shows balance lines");
            $this->assertSame(array_keys($resultat), array_keys($lines), "$kind shows the lines Resultat shows");
            $this->assertNoSideskiftAlert($report['html'], $kind);
        }
        $budget = self::lines($this->report('budget')['html']);
        $this->assertSame(['-10.000,00', '-12.000,00', '-16,67%'], $budget[1010]);
        $this->assertSame(['-6.000,00', '-7.500,00', '-20,00%'], $budget[4999]);
        $lastYear = self::lines($this->report('lastYear')['html']);
        $this->assertSame(['-10.000,00', '0,00'], $lastYear[1010]);
    }

    public function test_a_chart_with_sideskift_gives_the_same_output_as_before(): void
    {
        $differs = [];
        foreach (self::KINDS as $kind) {
            $report = $this->report($kind, self::$sideskiftYear);
            $this->assertNoSideskiftAlert($report['html'], $kind);
            $actual = ['txt' => self::statementText($report['html']), 'csv' => $report['csv']];
            foreach ($actual as $ext => $text) {
                $fixture = self::FIXTURE_DIR . "/$kind.$ext";
                if (!is_file($fixture) || file_get_contents($fixture) !== $text) {
                    // Left for a look (and for recording the fixture from the code before the change).
                    $saved = dirname(self::csvPath()) . "/statement-sideskift/$kind.$ext";
                    if (!is_dir(dirname($saved))) {
                        mkdir(dirname($saved), 0770, true);
                    }
                    file_put_contents($saved, $text);
                    $differs[] = "$kind.$ext (this run's output: $saved)";
                }
            }
            $lines = self::lines($report['html']);
            if ($kind === 'balance') {
                // By number: the D account 6475 above the Sideskift account is on the balance, the S account 2035 is not.
                $this->assertNoLinesBetween($lines, 0, 5000, 'Balance shows lines up to the Sideskift account');
                $this->assertSame(['-300,00', '-300,00'], $lines[6475]);
                $this->assertSame(['-4.700,00', '-4.700,00'], $lines[6935]);
            } else {
                $this->assertNoLinesBetween($lines, 5000, PHP_INT_MAX, "$kind shows lines from the Sideskift account");
                $this->assertArrayHasKey(2035, $lines, $kind);
            }
        }
        $this->assertSame([], $differs, 'the reports on a chart with a Sideskift account differ from the recorded output');
    }

    public function test_an_interleaved_chart_is_split_by_type(): void
    {
        // An S account numbered among the Drift accounts (in the group 1830-2009 summed by 2010 Salgsomkostninger) and a D
        // account numbered among the Status accounts (in 6470-6489 summed by 6490 Likvide beholdninger), with a voucher.
        self::account(self::$regnaar, 1995, 'S', '1995 Mellemregning');
        self::account(self::$regnaar, 6475, 'D', '6475 Kassedifference');
        self::post(self::$day, 3, 1995, 6475, 300.00, 'Mellemregning');
        try {
            $resultat = self::lines($this->report('resultat')['html']);
            $balance = self::lines($this->report('balance')['html']);
        } finally {
            self::rows('DELETE FROM transaktioner WHERE kontonr IN (1995, 6475) AND bilag = 3');
            self::rows('DELETE FROM kontoplan WHERE kontonr IN (1995, 6475) AND regnskabsaar = $1', [self::$regnaar]);
        }

        $this->assertArrayNotHasKey(1995, $resultat);
        $this->assertSame(['300,00', '300,00'], $balance[1995]);
        $this->assertArrayNotHasKey(6475, $balance);
        $this->assertSame(['-300,00', '-300,00'], $resultat[6475]);
        // The sums the two are numbered in stay on the side of the accounts they add up, with the amounts summed as before.
        $this->assertSame(['300,00', '300,00'], $resultat[2010], 'Salgsomkostninger adds up 1830-2009, 1995 included');
        $this->assertArrayNotHasKey(2010, $balance);
        $this->assertSame(['-5.700,00', '-5.700,00'], $resultat[4999]);
        $this->assertSame(['5.700,00', '8.200,00'], $balance[6490], 'Likvide beholdninger adds up 6470-6489, 6475 included');
        $this->assertArrayNotHasKey(6490, $resultat);
        // Every other line is on the side it has in the standard chart.
        $this->assertSame([6475], array_keys(self::between($resultat, self::BALANCE_FROM, PHP_INT_MAX)));
        $this->assertSame([1995], array_keys(self::between($balance, 0, self::BALANCE_FROM - 1)));
    }

    public function test_a_group_or_heading_with_one_account_of_the_other_side_stays_on_its_side(): void
    {
        // Drift accounts numbered among the Status accounts of the standard chart: 7445 among 7440 Leverandører (sum 7450
        // from 7440), 6945 Privat among 6940 Overført resultat (sum 6950 from 6940), and 6465 as the first account
        // under the heading 6462 Likvide beholdninger (6470 and 6480 after it). Each sum adds up one account of each side,
        // and the standard chart has more Status than Drift accounts.
        self::account(self::$regnaar, 7445, 'D', '7445 Gebyrer');
        self::account(self::$regnaar, 6945, 'D', '6945 Privat');
        self::account(self::$regnaar, 6465, 'D', '6465 Kassedifference');
        self::post(self::$day, 4, 7445, 6480, 100.00, 'Gebyr');
        self::post(self::$day, 5, 6480, 7440, 40.00, 'Leverandør');
        self::post(self::$day, 6, 6945, 6480, 1000.00, 'Privat');
        self::post(self::$day, 7, 6465, 6480, 50.00, 'Kassedifference');
        try {
            $resultat = self::lines($this->report('resultat')['html']);
            $balance = self::lines($this->report('balance')['html']);
        } finally {
            self::rows('DELETE FROM transaktioner WHERE bilag IN (4, 5, 6, 7) AND transdate = $1', [self::$day]);
            self::rows('DELETE FROM kontoplan WHERE kontonr IN (7445, 6945, 6465) AND regnskabsaar = $1', [self::$regnaar]);
        }

        // The sums and the heading stay on the balance, with the amounts as before; Resultat shows only the three accounts.
        $this->assertSame([6465, 6945, 7445], array_keys(self::between($resultat, self::BALANCE_FROM, PHP_INT_MAX)), 'Resultat from 5000');
        $this->assertNoLinesBetween($balance, 0, self::BALANCE_FROM - 1, 'Balance shows result lines');
        $this->assertArrayHasKey(7450, $balance, 'Leverandører af varer og tjenesteydelser on Balance');
        $this->assertSame(['60,00', '60,00'], $balance[7450], '7450: -40 on 7440 and 100 on 7445');
        $this->assertSame(['-40,00', '-40,00'], $balance[7440]);
        $this->assertSame(['100,00', '100,00'], $resultat[7445]);
        $this->assertArrayHasKey(6950, $balance, 'Overført resultat on Balance');
        $this->assertSame(['1.000,00', '1.000,00'], $balance[6950], '6950: 1.000 on 6945');
        $this->assertSame(['1.000,00', '1.000,00'], $resultat[6945]);
        $this->assertArrayHasKey(6462, $balance, 'the heading Likvide beholdninger on Balance');
        $this->assertSame([], $balance[6462]);
        $this->assertSame(['50,00', '50,00'], $resultat[6465]);
        $this->assertSame(['4.890,00', '7.390,00'], $balance[6490], 'Likvide beholdninger: the bank 8.500 - 100 + 40 - 1.000 - 50');
        foreach ([6465, 6945, 7445] as $nr) {
            $this->assertArrayNotHasKey($nr, $balance, "the Drift account $nr on Balance");
        }
    }

    public function test_a_group_with_several_accounts_of_the_other_side_stays_on_its_side(): void
    {
        // Two Drift accounts in each of two balance groups of the standard chart, 7441 and 7445 among 7440 Leverandører
        // (sum 7450 from 7440) and 6945 Privat and 6946 Privat bil among 6940 Overført resultat (sum 6950 from 6940), and
        // two Status accounts 3815 and 3816 among 3810 Andre skatter (sum 3820 from 3810). Each sum adds up more accounts
        // of the other side than of its own. 7440 and 6940 have an opening balance, which only Balance adds.
        $primo = [];
        foreach ([5570, 6940, 7440] as $nr) {
            $primo[$nr] = (float)self::one('SELECT primo FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = $2', [$nr, self::$regnaar])['primo'];
        }
        self::primo(self::$regnaar, 5570, 3000);
        self::primo(self::$regnaar, 6940, -2000);
        self::primo(self::$regnaar, 7440, -1000);
        $accounts = [7441 => 'Gebyr leverandør', 7445 => 'Kursdifference leverandør', 6945 => 'Privat', 6946 => 'Privat bil', 3815 => 'Mellemregning skat', 3816 => 'Skattekonto'];
        foreach ($accounts as $nr => $name) {
            self::account(self::$regnaar, $nr, $nr > 5000 ? 'D' : 'S', "$nr $name");
        }
        self::post(self::$day, 20, 7441, 7440, 150.00, 'Gebyr');
        self::post(self::$day, 21, 7445, 7440, 250.00, 'Kursdifference');
        self::post(self::$day, 22, 6945, 6480, 1000.00, 'Privat');
        self::post(self::$day, 23, 6946, 6480, 500.00, 'Privat bil');
        self::post(self::$day, 24, 3810, 6480, 200.00, 'Andre skatter');
        self::post(self::$day, 25, 3815, 6480, 30.00, 'Mellemregning skat');
        self::post(self::$day, 26, 3816, 6480, 40.00, 'Skattekonto');
        self::post(self::$day, 27, 1610, 7440, 800.00, 'Varekøb på kredit');
        try {
            $resultat = self::lines($this->report('resultat')['html']);
            $balance = self::lines($this->report('balance')['html']);
        } finally {
            self::rows('DELETE FROM transaktioner WHERE bilag BETWEEN 20 AND 27 AND transdate = $1', [self::$day]);
            self::rows('DELETE FROM kontoplan WHERE kontonr IN (' . implode(', ', array_keys($accounts)) . ') AND regnskabsaar = $1', [self::$regnaar]);
            foreach ($primo as $nr => $amount) {
                self::primo(self::$regnaar, $nr, $amount);
            }
        }

        // Only the six accounts change side; the sums and headings stay on their own side, with the amounts as before.
        $this->assertSame([6945, 6946, 7441, 7445], array_keys(self::between($resultat, self::BALANCE_FROM, PHP_INT_MAX)), 'Resultat from 5000');
        $this->assertSame([3815, 3816], array_keys(self::between($balance, 0, self::BALANCE_FROM - 1)), 'Balance below 5000');
        $this->assertSame([], $balance[7421], 'the heading Leverandører af varer og tjenesteydelser on Balance');
        $this->assertSame(['-1.200,00', '-2.200,00'], $balance[7440], '7440: -1.000 opening balance - 150 - 250 - 800');
        $this->assertSame(['-800,00', '-1.800,00'], $balance[7450], '7450 on Balance: 7440 with 150 on 7441 and 250 on 7445');
        $this->assertSame(['150,00', '150,00'], $resultat[7441]);
        $this->assertSame(['250,00', '250,00'], $resultat[7445]);
        $this->assertSame([], $balance[6931], 'the heading Overført resultat on Balance');
        $this->assertSame(['1.500,00', '-500,00'], $balance[6950], '6950 on Balance: -2.000 opening balance + 1.000 + 500');
        $this->assertSame(['1.000,00', '1.000,00'], $resultat[6945]);
        $this->assertSame(['500,00', '500,00'], $resultat[6946]);
        $this->assertSame([], $resultat[3791], 'the heading Andre skatter on Resultat');
        $this->assertSame(['200,00', '200,00'], $resultat[3810]);
        $this->assertSame(['270,00', '270,00'], $resultat[3820], '3820 on Resultat: 200 on 3810 and 70 on 3815 and 3816');
        $this->assertSame(['30,00', '30,00'], $balance[3815]);
        $this->assertSame(['40,00', '40,00'], $balance[3816]);
        foreach ([7421, 7450, 6931, 6950] as $nr) {
            $this->assertArrayNotHasKey($nr, $resultat, "$nr on Resultat");
        }
        foreach ([3791, 3820] as $nr) {
            $this->assertArrayNotHasKey($nr, $balance, "$nr on Balance");
        }
        // Every account still counts in the sum its number falls in, so the balance still balances.
        $this->assertSame(0.0, self::amount($balance[6499][1]) + self::amount($balance[9999][1]), 'the balance balances');
    }

    public function test_an_imported_chart_whose_sums_start_at_their_heading_keeps_its_totals_on_the_balance(): void
    {
        // importfiler/kontoplan2.txt in the fiscal year two after the installed one, with 60800 Årets resultat made a
        // Resultat account (from 49998 PERIODENS RESULTAT). Its sums start at their heading: 51995 Anlægsaktiver i alt and
        // 59999 AKTIVER IALT from 50000 A K T I V E R, 62995 Egenkapital i alt and 70000 PASSIVER IALT from 60000
        // P A S S I V E R. Two Drift accounts are numbered before the first account of a balance group: 50500
        // Afskrivninger driftsmidler before 50800 Driftsmidler & Inventar, and 60100 Privat before 60500 Egenkapital.
        $year = self::$regnaar + 2;
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, box6, fiscal_year)
             SELECT $1, kode, $2, art, box1, (box2::int + 2)::text, box3, (box4::int + 2)::text, box5, box6, $2
             FROM grupper WHERE art = 'RA' AND kodenr = $3",
            [Fixtures::TAG . ' kontoplan2', $year, self::$regnaar]
        );
        $day = sprintf('%04d-%02d-15', self::$ra['startYear'] + 2, self::$ra['startMonth']);
        try {
            $types = ['Tekst' => 'H', 'Drift' => 'D', 'Status' => 'S', 'Sum' => 'Z'];
            foreach (file(__DIR__ . '/../../../importfiler/kontoplan2.txt') as $line) {
                $f = explode("\t", rtrim(mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1'), "\r\n"));
                if (!isset($f[2]) || !ctype_digit($f[0])) {
                    continue;
                }
                $nr = (int)$f[0];
                $name = html_entity_decode(trim($f[1], '"'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                [$type, $fra] = ($nr == 60800) ? ['R', 49998] : [$types[trim($f[2], '"')], (int)trim($f[5] ?? '')];
                self::account($year, $nr, $type, "$nr $name", $fra);
            }
            self::account($year, 50500, 'D', '50500 Afskrivninger driftsmidler');
            self::account($year, 60100, 'D', '60100 Privat');
            self::primo($year, 50800, 30000);
            self::primo($year, 58000, 100000);
            self::primo($year, 60500, -130000);
            self::post($day, 30, 58000, 1000, 40000.00, 'Salg');
            self::post($day, 31, 5100, 58000, 12000.00, 'Husleje');
            self::post($day, 32, 50500, 50830, 5000.00, 'Afskrivning');
            self::post($day, 33, 60100, 58000, 15000.00, 'Privat');
            $resultat = self::lines($this->report('resultat', $year)['html']);
            $balanceHtml = $this->report('balance', $year)['html'];
            $balance = self::lines($balanceHtml);
        } finally {
            self::rows('DELETE FROM transaktioner WHERE bilag BETWEEN 30 AND 33 AND transdate = $1', [$day]);
            self::rows('DELETE FROM kontoplan WHERE regnskabsaar = $1', [$year]);
            self::rows("DELETE FROM grupper WHERE art = 'RA' AND kodenr = $1", [$year]);
        }

        // Resultat shows the two accounts after 49998 PERIODENS RESULTAT, and none of the balance's headings and sums.
        $this->assertSame([50500, 60100], array_keys(self::between($resultat, 50000, PHP_INT_MAX)), 'Resultat from 50000');
        $this->assertSame(['-28.000,00', '-28.000,00'], $resultat[49998], 'PERIODENS RESULTAT: -40.000 + 12.000');
        $this->assertSame(['5.000,00', '5.000,00'], $resultat[50500]);
        $this->assertSame(['15.000,00', '15.000,00'], $resultat[60100]);
        // Balance keeps them, with the amounts as before: the two accounts still count in the sums their numbers fall in.
        $this->assertNoLinesBetween($balance, 0, 49999, 'Balance shows result lines');
        foreach ([50000 => 'A K T I V E R', 50100 => 'ANLÆGSAKTIVER', 60000 => 'P A S S I V E R', 60001 => 'EGENKAPITAL'] as $nr => $name) {
            $this->assertSame([], $balance[$nr] ?? null, "the heading $nr $name on Balance");
        }
        $this->assertSame(['0,00', '30.000,00'], $balance[51995] ?? null, '51995 Anlægsaktiver i alt: 30.000 opening balance - 5.000 on 50830 + 5.000 on 50500');
        $this->assertSame(['13.000,00', '143.000,00'], $balance[59999] ?? null, '59999 AKTIVER IALT: 30.000 and the bank 113.000');
        $this->assertSame(['-28.000,00', '-28.000,00'], $balance[60800], 'the Resultat account 60800');
        $this->assertSame(['-13.000,00', '-143.000,00'], $balance[62995] ?? null, '62995 Egenkapital i alt: -130.000 + 15.000 on 60100 - 28.000');
        $this->assertSame(['-13.000,00', '-143.000,00'], $balance[70000] ?? null, '70000 PASSIVER IALT');
        $this->assertArrayHasKey(99999, $balance, 'Balancekontrol on Balance');
        foreach ([50000, 50100, 51995, 59999, 60000, 60001, 62995, 70000, 99999] as $nr) {
            $this->assertArrayNotHasKey($nr, $resultat, "$nr on Resultat");
        }
        $this->assertArrayNotHasKey(50500, $balance);
        $this->assertArrayNotHasKey(60100, $balance);
        $this->assertSame(0.0, self::amount($balance[59999][1]) + self::amount($balance[70000][1]), 'the balance balances');
        $this->assertFalse(str_contains($balanceHtml, self::NO_RESULT_ALERT), 'Balance alerts on a chart with a Resultat account');
        $this->assertNoSideskiftAlert($balanceHtml, 'balance');
    }

    public function test_balance_without_a_resultat_account_says_the_years_result_is_missing(): void
    {
        $standard = $this->report('balance')['html'];
        // The chart without its Resultat account, as importfiler/kontoplan2.txt has none.
        self::rows("UPDATE kontoplan SET kontotype = 'S' WHERE kontonr = 6935 AND regnskabsaar = $1", [self::$regnaar]);
        try {
            $balance = $this->report('balance')['html'];
            $resultat = $this->report('resultat')['html'];
        } finally {
            self::rows("UPDATE kontoplan SET kontotype = 'R' WHERE kontonr = 6935 AND regnskabsaar = $1", [self::$regnaar]);
        }

        // str_contains() so that a failure names the report, not the whole page.
        $this->assertTrue(str_contains($balance, self::NO_RESULT_ALERT), 'Balance does not say why it does not balance');
        $lines = self::lines($balance);
        $this->assertSame(['6.000,00', '8.500,00'], $lines[6499], 'AKTIVER I ALT');
        $this->assertSame(['0,00', '-2.500,00'], $lines[9999], 'PASSIVER I ALT, without the year\'s result');
        $this->assertFalse(str_contains($resultat, self::NO_RESULT_ALERT), 'Resultat alerts, though it has the year\'s result');
        $this->assertFalse(str_contains($standard, self::NO_RESULT_ALERT), 'Balance alerts on a chart with a Resultat account');
        $this->assertNoSideskiftAlert($balance, 'balance');
    }

    public function test_fra_and_til_konto_still_limit_the_lines(): void
    {
        $resultat = self::lines($this->report('resultat')['html']);
        $balance = self::lines($this->report('balance')['html']);

        $lines = self::lines($this->report('resultat', 0, ['konto_fra' => '2000', 'konto_til' => '3000'])['html']);
        $this->assertSame(self::between($resultat, 2000, 3000), $lines);
        $this->assertSame([], $lines[2020], 'the heading Lokaleomkostninger');
        $this->assertSame(['4.000,00', '4.000,00'], $lines[2210], 'the sum Lokaleomkostninger');
        $this->assertArrayNotHasKey(1010, $lines);
        $this->assertArrayNotHasKey(4999, $lines);

        // Limits that span both sides show the report's own side within them.
        $lines = self::lines($this->report('balance', 0, ['konto_fra' => '4000', 'konto_til' => '6600'])['html']);
        $this->assertNoLinesBetween($lines, 4000, self::BALANCE_FROM - 1, 'Balance 4000-6600 shows result lines (4999 Årets resultat)');
        $this->assertSame(self::between($balance, 4000, 6600), $lines);
        $this->assertSame([], $lines[5000], 'the heading BALANCE');
        $this->assertSame(['6.000,00', '8.500,00'], $lines[6480]);
        $this->assertArrayNotHasKey(6935, $lines);
        $this->assertArrayNotHasKey(9999, $lines);

        $lines = self::lines($this->report('resultat', 0, ['konto_fra' => '1000', 'konto_til' => '9999'])['html']);
        $this->assertNoLinesBetween($lines, self::BALANCE_FROM, PHP_INT_MAX, 'Resultat 1000-9999 shows balance lines');
        $this->assertSame($resultat, $lines);
        $lines = self::lines($this->report('balance', 0, ['konto_fra' => '1000', 'konto_til' => '9999'])['html']);
        $this->assertNoLinesBetween($lines, 0, self::BALANCE_FROM - 1, 'Balance 1000-9999 shows result lines');
        $this->assertSame($balance, $lines);
    }
}
