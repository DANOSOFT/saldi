<?php
// 20261113 CL/ASR Cover the one-time item group account repair in includes/opdat_func/fixStdItemGroupAccounts.php and the fixed seed in importfiler/grupper.txt.
//                 Pins the seeded rows per fiscal year on the standard chart, the guards that leave a cell alone, what is logged, the flag and both locks.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * The database functions are stubbed with an in-memory grupper, kontoplan and settings, so the repair's decisions
 * can be checked per cell without a tenant database. A standard year holds the accounts of importfiler/kontoplan.txt.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class StdItemGroupAccountsMigrationTest extends TestCase
{
    /** The VG rows importfiler/grupper.txt seeded before the fix: box11..box14 by description. */
    private const OLD_SEED = [
        'Ydelser' => ['1710', '1150', '1740', '1250'],
        'Handelsvarer' => ['1050', '1220', '1650', '1100'],
        'Forbrugssvarer' => ['1050', '1220', '1650', '1100'],
        'Fragt/porto' => ['1710', '1105', '1740', '1250'],
    ];

    private object $state;
    private string $log;

    protected function setUp(): void
    {
        $this->state = (object) ['grupper' => [], 'kontoplan' => [], 'settings' => [], 'queries' => [], 'writes' => [],
            'transactions' => [], 'acquired' => 1, 'rollback' => false, 'afterRead' => null];
        $GLOBALS['vgAccountsState'] = $this->state;
        $GLOBALS['db'] = 'test_vgaccounts';
        $this->log = (string) tempnam(sys_get_temp_dir(), 'vgaccounts');
        ini_set('error_log', $this->log);
        if (!function_exists('db_select')) {
            function db_select($sql, $location = '') {
                return StdItemGroupAccountsMigrationTest::select($sql);
            }
            function db_fetch_array($result) {
                if (!$result instanceof ArrayIterator || !$result->valid()) {
                    return false;
                }
                $row = $result->current();
                $result->next();
                return $row;
            }
            function db_modify($sql, $location = '') {
                StdItemGroupAccountsMigrationTest::modify($sql);
                return true;
            }
            function db_escape_string($value) {
                return str_replace("'", "''", (string) $value);
            }
            function transaktion($operation) {
                $GLOBALS['vgAccountsState']->transactions[] = $operation;
            }
        }
        require_once __DIR__ . '/../../../includes/opdat_func/fixStdItemGroupAccounts.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->log)) {
            unlink($this->log);
        }
        unset($GLOBALS['vgAccountsState']);
    }

    /** Answers the repair's SELECTs from the in-memory tables; any other query fails the test. */
    public static function select(string $sql): ArrayIterator
    {
        $state = $GLOBALS['vgAccountsState'];
        $state->queries[] = $sql;
        if (str_contains($sql, 'GET_LOCK(')) {
            return new ArrayIterator([['acquired' => (string) $state->acquired]]);
        }
        if (str_contains($sql, 'RELEASE_LOCK(') || str_contains($sql, 'pg_advisory_')) {
            return new ArrayIterator([['lock' => '1']]);
        }
        if (preg_match("/^SELECT id FROM settings WHERE var_grp = '([^']+)' AND var_name = '([^']+)'$/", $sql, $m)) {
            return new ArrayIterator(array_values(array_filter($state->settings,
                static fn (array $row): bool => $row['var_grp'] === $m[1] && $row['var_name'] === $m[2])));
        }
        if ($sql === "SELECT id, kodenr, box11, box12, box13, box14, fiscal_year FROM grupper WHERE art = 'VG' ORDER BY id") {
            $rows = array_values(array_filter($state->grupper, static fn (array $row): bool => $row['art'] === 'VG'));
            if ($state->afterRead) {
                ($state->afterRead)($state); // another login saves the row after this read
            }
            return new ArrayIterator($rows);
        }
        if ($sql === 'SELECT regnskabsaar FROM kontoplan GROUP BY regnskabsaar') {
            $years = array_values(array_unique(array_map(static fn (array $row): string => $row['regnskabsaar'], $state->kontoplan)));
            return new ArrayIterator(array_map(static fn (string $year): array => ['regnskabsaar' => $year], $years));
        }
        $chart = '/^SELECT kontonr, kontotype, moms, beskrivelse, lukket FROM kontoplan WHERE regnskabsaar = (\d+) '
            . 'AND kontonr IN \(1050, 1100, 1105, 1150, 1200, 1220, 1250, 1630\)$/';
        if (preg_match($chart, $sql, $m)) {
            return new ArrayIterator(array_values(array_filter($state->kontoplan, static fn (array $row): bool =>
                $row['regnskabsaar'] === $m[1] && in_array((int) $row['kontonr'], [1050, 1100, 1105, 1150, 1200, 1220, 1250, 1630], true))));
        }
        throw new RuntimeException("Unexpected query: $sql");
    }

    /** Applies the repair's UPDATE by id and stored values, and its flag INSERT; any other statement fails the test. */
    public static function modify(string $sql): void
    {
        $state = $GLOBALS['vgAccountsState'];
        $state->writes[] = $sql;
        $value = "'((?:[^']|'')*)'";
        if (preg_match("/^UPDATE grupper SET (.+) WHERE id = (\d+) AND art = 'VG' AND (.+)$/", $sql, $m)) {
            preg_match_all("/(box1[1-4]) = $value/", $m[1], $sets, PREG_SET_ORDER);
            $conditions = array_map('trim', explode(' AND ', $m[3]));
            self::assertCount(4, $conditions, 'every account cell of the row is in the WHERE');
            foreach ($state->grupper as $i => $row) {
                if ($row['id'] !== $m[2] || $row['art'] !== 'VG') {
                    continue;
                }
                foreach ($conditions as $condition) {
                    if (preg_match('/^(box1[1-4]) IS NULL$/', $condition, $c)) {
                        $matches = $row[$c[1]] === null;
                    } elseif (preg_match("/^(box1[1-4]) = $value$/", $condition, $c)) {
                        $matches = $row[$c[1]] === str_replace("''", "'", $c[2]);
                    } else {
                        throw new RuntimeException("Unexpected condition: $condition");
                    }
                    if (!$matches) {
                        return; // saved by hand since it was read
                    }
                }
                foreach ($sets as $set) {
                    $state->grupper[$i][$set[1]] = str_replace("''", "'", $set[2]);
                }
            }
            return;
        }
        if (preg_match("/^INSERT INTO settings \(var_name, var_grp, var_value, var_description, user_id\) VALUES \($value, $value, '(\d{8})', $value, 0\)$/", $sql, $m)) {
            if ($state->rollback) {
                return; // the transaction is rolled back at commit: the flag is not kept
            }
            $state->settings[] = ['id' => (string) (count($state->settings) + 1), 'var_name' => $m[1], 'var_grp' => $m[2],
                'var_value' => $m[3], 'var_description' => str_replace("''", "'", $m[4])];
            return;
        }
        throw new RuntimeException("Unexpected write: $sql");
    }

    /**
     * The accounts of importfiler/kontoplan.txt as includes/opretfunc.php reads them into a new ledger.
     *
     * @return array<int, array{kontonr: string, beskrivelse: string, kontotype: string, moms: string}>
     */
    private static function standardChart(): array
    {
        $accounts = [];
        foreach (file(__DIR__ . '/../../../importfiler/kontoplan.txt', FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, "\t")) {
                continue;
            }
            $f = explode("\t", $line);
            $accounts[(int) $f[0]] = ['kontonr' => $f[0], 'beskrivelse' => trim(str_replace('"', '', $f[1])),
                'kontotype' => trim($f[2]), 'moms' => trim($f[3] ?? '')];
        }
        return $accounts;
    }

    /**
     * The VG rows of importfiler/grupper.txt, parsed as includes/settings/rows.php parses them.
     *
     * @return array<string, array<string, string>> The row's columns by description.
     */
    private static function seed(): array
    {
        $keys = ['beskrivelse', 'kode', 'kodenr', 'art', 'box1', 'box2', 'box3', 'box4', 'box5', 'box6', 'box7', 'box8', 'box9',
            'box10', 'box11', 'box12', 'box13', 'box14'];
        $rows = [];
        foreach (file(__DIR__ . '/../../../importfiler/grupper.txt', FILE_IGNORE_NEW_LINES) as $line) {
            if (trim($line) === '' || $line[0] === '#') {
                continue;
            }
            $row = array_combine($keys, array_map('trim', str_getcsv(trim($line), ',', "'", '\\')));
            if ($row['art'] === 'VG') {
                $rows[$row['beskrivelse']] = $row;
            }
        }
        return $rows;
    }

    /**
     * Seeds a fiscal year with the standard chart, changed by kontonr: an array replaces fields of that account
     * (or adds it), null removes it.
     *
     * @param array<int, array<string, string>|null> $changes
     */
    private function standardYear(int $year, array $changes = []): void
    {
        $accounts = self::standardChart();
        foreach ($changes as $kontonr => $fields) {
            if ($fields === null) {
                unset($accounts[$kontonr]);
            } else {
                $accounts[$kontonr] = $fields + ($accounts[$kontonr] ?? ['kontonr' => (string) $kontonr, 'beskrivelse' => 'Egen konto',
                    'kontotype' => 'D', 'moms' => '']);
            }
        }
        foreach ($accounts as $account) {
            $this->state->kontoplan[] = $account + ['lukket' => '', 'regnskabsaar' => (string) $year];
        }
    }

    /**
     * Adds a VG row of a fiscal year; returns its id.
     *
     * @param array{0: ?string, 1: ?string, 2: ?string, 3: ?string} $boxes box11..box14 as stored.
     */
    private function itemGroup(int $year, array $boxes, int $kodenr = 1): string
    {
        $id = (string) (count($this->state->grupper) + 1);
        $this->state->grupper[] = ['id' => $id, 'art' => 'VG', 'kodenr' => (string) $kodenr, 'fiscal_year' => (string) $year,
            'box11' => $boxes[0], 'box12' => $boxes[1], 'box13' => $boxes[2], 'box14' => $boxes[3]];
        return $id;
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string} box11..box14 of the grupper row with this id. */
    private function boxes(string $id): array
    {
        foreach ($this->state->grupper as $row) {
            if ($row['id'] === $id) {
                return [$row['box11'], $row['box12'], $row['box13'], $row['box14']];
            }
        }
        self::fail("No grupper row $id");
    }

    private function assertFlagWritten(): void
    {
        $flags = array_filter($this->state->settings, static fn (array $row): bool =>
            $row['var_grp'] === 'system' && $row['var_name'] === 'std_item_group_accounts');
        self::assertCount(1, $flags);
        self::assertSame(['begin', 'commit'], $this->state->transactions);
    }

    /** @return string[] The UPDATE statements the repair sent. */
    private function updates(): array
    {
        return array_values(array_filter($this->state->writes, static fn (string $sql): bool => str_starts_with($sql, 'UPDATE')));
    }

    private function logged(): string
    {
        return (string) file_get_contents($this->log);
    }

    public function testEveryAccountTheSeedNamesIsAnOpenOperatingAccountOfTheStandardChart(): void
    {
        $chart = self::standardChart();
        $seed = self::seed();
        self::assertSame(['Ydelser', 'Handelsvarer', 'Forbrugssvarer', 'Fragt/porto'], array_keys($seed));
        foreach ($seed as $name => $row) {
            foreach (['box3', 'box4', 'box11', 'box12', 'box13', 'box14'] as $col) {
                self::assertArrayHasKey((int) $row[$col], $chart, "$name $col $row[$col] is in importfiler/kontoplan.txt");
                self::assertSame('D', $chart[(int) $row[$col]]['kontotype'], "$name $col $row[$col] is an operating account");
            }
            foreach (['box12', 'box14'] as $col) {
                self::assertSame('', $chart[(int) $row[$col]]['moms'], "$name $col: a sale outside Denmark carries no Danish VAT code");
                self::assertStringStartsWith('Salg af ', $chart[(int) $row[$col]]['beskrivelse'], "$name $col is a sales account");
            }
            foreach (['box11', 'box13'] as $col) {
                self::assertMatchesRegularExpression('/k(ø|&oslash;)b/u', $chart[(int) $row[$col]]['beskrivelse'], "$name $col is a purchase account");
            }
        }
    }

    public function testSeededRowsOnTheStandardChartBecomeTheFixedSeed(): void
    {
        $this->standardYear(1);
        $ids = [];
        foreach (self::OLD_SEED as $name => $boxes) {
            $ids[$name] = $this->itemGroup(1, $boxes, count($ids) + 1);
        }
        self::assertSame('done', fixStdItemGroupAccounts('postgresql'));
        foreach (self::seed() as $name => $row) {
            self::assertSame([$row['box11'], $row['box12'], $row['box13'], $row['box14']], $this->boxes($ids[$name]), $name);
        }
        $this->assertFlagWritten();
        self::assertCount(4, $this->updates());
        foreach ($this->updates() as $sql) {
            self::assertStringNotContainsStringIgnoringCase('select', $sql, 'MySQL refuses a subquery on the UPDATE target');
        }
        self::assertStringContainsString("pg_advisory_lock(hashtext('saldi_std_item_group_accounts'))", $this->state->queries[1]);
        self::assertStringContainsString("pg_advisory_unlock(hashtext('saldi_std_item_group_accounts'))", end($this->state->queries));
        self::assertStringContainsString('Saldi test_vgaccounts: Item group EU/non-EU accounts', $this->logged());
        self::assertStringContainsString('year 1 group 1 box14 1250 -> 1200', $this->logged());
        self::assertStringContainsString('year 1 group 2 box12 1220 -> 1050, box11 1050 -> 1630', $this->logged());
        self::assertStringContainsString('year 1 group 4 box12 1105 -> 1050, box14 1250 -> 1100', $this->logged());
        self::assertStringContainsString('7 changed, 0 kept by a guard', $this->logged());
    }

    /** @return array<string, array{0: int, 1: array{0: string, 1: string, 2: string, 3: string}}> */
    public static function accountsTheCompanyHas(): array
    {
        return ['1220 for EU goods (the older chart)' => [1220, ['2800', '1220', '2820', '1270']],
            '1105 created by hand' => [1105, ['1710', '1105', '1740', '1100']],
            '1250 as a heading' => [1250, ['1710', '1150', '1740', '1250']]];
    }

    /** @param array{0: string, 1: string, 2: string, 3: string} $boxes */
    #[DataProvider('accountsTheCompanyHas')]
    public function testAnAccountTheCompanyHasIsItsOwnChoiceAndNotReported(int $kontonr, array $boxes): void
    {
        $this->standardYear(1, [$kontonr => ['kontotype' => $kontonr === 1250 ? 'H' : 'D', 'beskrivelse' => 'Egen konto']]);
        $id = $this->itemGroup(1, $boxes);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame($boxes, $this->boxes($id));
        self::assertSame([], $this->updates());
        $this->assertFlagWritten();
        self::assertSame('', $this->logged());
    }

    /** @return array<string, array{0: int, 1: array<string, string>}> */
    public static function replacementsThatAreNotTheStandardAccount(): array
    {
        return ['another account with sales VAT' => [1050, ['beskrivelse' => 'Kørsel', 'moms' => 'S1']],
            'the standard name with a VAT code' => [1050, ['moms' => 'S1']],
            'another name without VAT' => [1050, ['beskrivelse' => 'Husleje fastboende']],
            'the older chart\'s EU services account' => [1200, ['beskrivelse' => 'Salg af ydelser indenfor EU']],
            'a heading' => [1100, ['kontotype' => 'H']],
            'a sum account' => [1200, ['kontotype' => 'Z']],
            'closed' => [1050, ['lukket' => 'on']]];
    }

    /** @param array<string, string> $fields */
    #[DataProvider('replacementsThatAreNotTheStandardAccount')]
    public function testAReplacementThatIsNotTheStandardAccountKeepsTheCellAndIsLogged(int $kontonr, array $fields): void
    {
        $this->standardYear(1, [$kontonr => $fields]);
        $boxes = [1050 => ['1630', '1220', '1650', '1100'], 1100 => ['1630', '1050', '1650', '1250'],
            1200 => ['1710', '1150', '1740', '1250']][$kontonr];
        $id = $this->itemGroup(1, $boxes);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame($boxes, $this->boxes($id));
        self::assertSame([], $this->updates());
        $this->assertFlagWritten();
        $from = ['1050' => '1220', '1100' => '1250', '1200' => '1250'][(string) $kontonr];
        self::assertStringContainsString("year 1 group 1 box1" . ($kontonr === 1050 ? '2' : '4') . " $from kept ($from is not in the chart, $kontonr is not the standard account)", $this->logged());
        self::assertStringContainsString('0 changed, 1 kept by a guard', $this->logged());
    }

    public function testTheStandardNameIsRecognisedInAnyEncoding(): void
    {
        $this->standardYear(1, [1630 => ['beskrivelse' => 'Varek&oslash;b udland, EU']]);
        $this->standardYear(2, [1630 => ['beskrivelse' => "Varek\xF8b udland, EU"], 1050 => ['beskrivelse' => ' salg af varer udland, eu ']]);
        $first = $this->itemGroup(1, self::OLD_SEED['Handelsvarer']);
        $second = $this->itemGroup(2, self::OLD_SEED['Handelsvarer']);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame(['1630', '1050', '1650', '1100'], $this->boxes($first));
        self::assertSame(['1630', '1050', '1650', '1100'], $this->boxes($second));
    }

    /** @return array<string, array{0: array{0: string, 1: string, 2: string, 3: string}}> */
    public static function rowsNotSeeded(): array
    {
        return ['EU sales fixed by hand' => [['1050', '1050', '1650', '1100']], 'own non-EU purchase account' => [['1050', '1220', '1660', '1100']],
            'own export account' => [['1050', '1220', '1650', '1200']]];
    }

    /** @param array{0: string, 1: string, 2: string, 3: string} $boxes */
    #[DataProvider('rowsNotSeeded')]
    public function testBox11OnARowThatIsNotTheSeededOneIsKeptAndLogged(array $boxes): void
    {
        $this->standardYear(1);
        $id = $this->itemGroup(1, $boxes);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame('1050', $this->boxes($id)[0]);
        self::assertStringContainsString('year 1 group 1 box11 1050 kept (the EU sales account, on a row that is not the seeded one)', $this->logged());
        $this->assertFlagWritten();
    }

    public function testBox11KeepsWhen1630IsNotTheStandardAccountWhileBox12IsStillRepaired(): void
    {
        $this->standardYear(1, [1630 => ['beskrivelse' => 'Småanskaffelser, maskiner og værktøj u/skattemæssig grænse u/moms', 'moms' => '']]);
        $id = $this->itemGroup(1, self::OLD_SEED['Forbrugssvarer']);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame(['1050', '1050', '1650', '1100'], $this->boxes($id));
        self::assertStringContainsString('year 1 group 1 box11 1050 kept (the EU sales account, 1630 is not the standard account)', $this->logged());
        self::assertStringContainsString('year 1 group 1 box12 1220 -> 1050', $this->logged());
        self::assertStringContainsString('1 changed, 1 kept by a guard', $this->logged());
    }

    public function testBox11WhenTheChartHasNo1050IsKeptAndLogged(): void
    {
        $this->standardYear(1, [1050 => null]);
        $id = $this->itemGroup(1, self::OLD_SEED['Handelsvarer']);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame(self::OLD_SEED['Handelsvarer'], $this->boxes($id));
        self::assertStringContainsString('year 1 group 1 box12 1220 kept (1220 is not in the chart, 1050 is not the standard account)', $this->logged());
        self::assertStringContainsString('year 1 group 1 box11 1050 kept (1050 is not in the chart)', $this->logged());
    }

    public function testBox11OnAnotherChartsOwn1050IsNotReported(): void
    {
        $this->standardYear(1, [1050 => ['beskrivelse' => 'Copydan', 'moms' => 'K1']]);
        $id = $this->itemGroup(1, ['1050', '1200', '', '1300']);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame(['1050', '1200', '', '1300'], $this->boxes($id));
        self::assertSame('', $this->logged());
        $this->assertFlagWritten();
    }

    public function testANumberThatOccursTwiceInAYearIsNobodysStandardAccount(): void
    {
        $this->standardYear(1);
        $this->state->kontoplan[] = ['kontonr' => '1200', 'beskrivelse' => 'Salg af ydelser udland, ikke-EU', 'kontotype' => 'D', 'moms' => '',
            'lukket' => '', 'regnskabsaar' => '1'];
        $id = $this->itemGroup(1, self::OLD_SEED['Ydelser']);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame(self::OLD_SEED['Ydelser'], $this->boxes($id));
        self::assertStringContainsString('box14 1250 kept (1250 is not in the chart, 1200 is not the standard account)', $this->logged());
    }

    public function testEveryFiscalYearIsJudgedAgainstItsOwnChart(): void
    {
        $this->standardYear(1, [1220 => ['beskrivelse' => 'Salg af varer EU']]);
        $this->standardYear(2);
        $this->standardYear(4, [1050 => ['beskrivelse' => 'Kørsel', 'moms' => 'S1']]);
        $first = $this->itemGroup(1, self::OLD_SEED['Handelsvarer']);
        $second = $this->itemGroup(2, self::OLD_SEED['Handelsvarer']);
        $noChart = $this->itemGroup(3, self::OLD_SEED['Handelsvarer']); // no accounts of its own: judged by year 2
        $fourth = $this->itemGroup(4, self::OLD_SEED['Handelsvarer']);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame(['1630', '1220', '1650', '1100'], $this->boxes($first)); // 1220 is the company's own account
        self::assertSame(['1630', '1050', '1650', '1100'], $this->boxes($second));
        self::assertSame(['1630', '1050', '1650', '1100'], $this->boxes($noChart));
        self::assertSame(self::OLD_SEED['Handelsvarer'], $this->boxes($fourth));
        self::assertCount(3, $this->updates());
        self::assertStringContainsString('year 4 group 1 box12 1220 kept', $this->logged());
        self::assertStringContainsString('5 changed, 1 kept by a guard', $this->logged());
        foreach ([1 => 1, 2 => 2, 3 => 0, 4 => 1] as $year => $count) {
            $chartQueries = array_filter($this->state->queries, static fn (string $sql): bool => str_contains($sql, "FROM kontoplan WHERE regnskabsaar = $year "));
            self::assertCount($count, $chartQueries, "chart queries for year $year");
        }
        $yearQueries = array_filter($this->state->queries, static fn (string $sql): bool => str_contains($sql, 'GROUP BY regnskabsaar'));
        self::assertCount(1, $yearQueries, 'the years with accounts are read once');
    }

    public function testAYearBeforeEveryChartIsJudgedByTheLatestChart(): void
    {
        $this->standardYear(2);
        $id = $this->itemGroup(1, self::OLD_SEED['Fragt/porto']);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame(['1710', '1050', '1740', '1100'], $this->boxes($id)); // freight sells on the goods accounts
    }

    public function testNoAccountsAtAllKeepsTheRowAndLogsIt(): void
    {
        $id = $this->itemGroup(1, self::OLD_SEED['Handelsvarer']);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame(self::OLD_SEED['Handelsvarer'], $this->boxes($id));
        self::assertSame([], $this->updates());
        self::assertSame([], array_filter($this->state->queries, static fn (string $sql): bool => str_contains($sql, 'FROM kontoplan WHERE')));
        $this->assertFlagWritten();
        self::assertStringContainsString('year 1 group 1 box12 1220 kept', $this->logged());
        self::assertStringContainsString('year 1 group 1 box11 1050 kept (1050 is not in the chart)', $this->logged());
    }

    public function testRowsWithoutABadDefaultAreNotRead(): void
    {
        $this->standardYear(1);
        $id = $this->itemGroup(1, ['1630', '1050', '1650', '1100']);
        $this->itemGroup(1, ['', '', '', ''], 2);
        $this->itemGroup(1, [null, null, null, null], 3);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame(['1630', '1050', '1650', '1100'], $this->boxes($id));
        self::assertSame([], $this->updates());
        self::assertSame([], array_filter($this->state->queries, static fn (string $sql): bool => str_contains($sql, 'FROM kontoplan')));
        $this->assertFlagWritten();
        self::assertSame('', $this->logged());
    }

    public function testStoredWhitespaceAndNullAreMatchedByTheirStoredValues(): void
    {
        $this->standardYear(1);
        $id = $this->itemGroup(1, ['', ' 1220 ', null, '1100']);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame(['', '1050', null, '1100'], $this->boxes($id));
        self::assertStringContainsString("box12 = ' 1220 ' AND box13 IS NULL", $this->updates()[0]);
    }

    public function testARowSavedByHandSinceItWasReadIsLeftAlone(): void
    {
        $this->standardYear(1);
        $id = $this->itemGroup(1, self::OLD_SEED['Ydelser']);
        // The repair reads the seeded row; a user saves 1300 in box14 before its UPDATE arrives.
        $this->state->afterRead = static function (object $state): void {
            $state->grupper[0]['box14'] = '1300';
        };
        fixStdItemGroupAccounts('postgresql');
        self::assertSame(['1710', '1150', '1740', '1300'], $this->boxes($id));
        self::assertCount(1, $this->updates());
        self::assertStringContainsString("box14 = '1250'", $this->updates()[0]);
    }

    public function testARolledBackRunReportsFailedAndIsRetried(): void
    {
        $this->standardYear(1);
        $this->itemGroup(1, self::OLD_SEED['Handelsvarer']);
        $this->state->rollback = true;
        self::assertSame('failed', fixStdItemGroupAccounts('postgresql'));
        self::assertStringContainsString('was rolled back; the next login tries again', $this->logged());
        self::assertStringNotContainsString('changed', $this->logged());
        self::assertStringContainsString('pg_advisory_unlock', end($this->state->queries));
        $this->state->rollback = false;
        self::assertSame('done', fixStdItemGroupAccounts('postgresql'));
        self::assertCount(1, $this->state->settings);
        self::assertSame(['begin', 'commit', 'begin', 'commit'], $this->state->transactions);
    }

    public function testFlagPresentRunsNothing(): void
    {
        $this->standardYear(1);
        $id = $this->itemGroup(1, self::OLD_SEED['Handelsvarer']);
        $this->state->settings[] = ['id' => '1', 'var_grp' => 'system', 'var_name' => 'std_item_group_accounts', 'var_value' => '20261113'];
        self::assertSame('done', fixStdItemGroupAccounts('postgresql'));
        self::assertSame(self::OLD_SEED['Handelsvarer'], $this->boxes($id));
        self::assertCount(1, $this->state->queries);
        self::assertSame([], $this->state->writes);
        self::assertSame([], $this->state->transactions);
    }

    public function testSecondLoginOnlyReadsTheFlag(): void
    {
        $this->standardYear(1);
        $this->itemGroup(1, self::OLD_SEED['Handelsvarer']);
        fixStdItemGroupAccounts('postgresql');
        $writes = $this->state->writes;
        $queries = count($this->state->queries);
        fixStdItemGroupAccounts('postgresql');
        self::assertSame($writes, $this->state->writes);
        self::assertSame($queries + 1, count($this->state->queries));
    }

    public function testMysqlBusyLockLeavesTheRepairToTheNextLogin(): void
    {
        $this->standardYear(1);
        $id = $this->itemGroup(1, self::OLD_SEED['Handelsvarer']);
        $this->state->acquired = 0;
        self::assertSame('busy', fixStdItemGroupAccounts('mysqli'));
        self::assertSame(self::OLD_SEED['Handelsvarer'], $this->boxes($id));
        self::assertSame([], $this->state->writes);
        self::assertStringNotContainsString('RELEASE_LOCK(', implode("\n", $this->state->queries));
        $this->state->acquired = 1;
        self::assertSame('done', fixStdItemGroupAccounts('mysql'));
        self::assertSame(['1630', '1050', '1650', '1100'], $this->boxes($id));
        $lock = 'saldi_vgaccounts_' . substr(md5('test_vgaccounts'), 0, 16);
        self::assertContains("SELECT GET_LOCK('$lock', 30) AS acquired", $this->state->queries);
        self::assertSame("SELECT RELEASE_LOCK('$lock')", end($this->state->queries));
        $this->assertFlagWritten();
    }

    public function testBetweenUpdatesRunsTheRepairAfterTheVatReportRange(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../../includes/betweenUpdates.php');
        $vat = strpos($source, 'fixVatReportRange($db_type);');
        $call = "require_once __DIR__ . '/opdat_func/fixStdItemGroupAccounts.php';\nfixStdItemGroupAccounts(\$db_type);";
        $at = strpos($source, $call);
        self::assertNotFalse($vat);
        self::assertNotFalse($at);
        self::assertGreaterThan($vat, $at);
    }
}
