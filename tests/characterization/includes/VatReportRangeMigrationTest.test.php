<?php
// 20261113 CL/ASR Cover the one-time VAT report range repair in includes/opdat_func/fixVatReportRange.php.
//                 Pins the seeded ranges per fiscal year, the guards that leave a row alone, the flag and both locks.
//                 A year without accounts is judged by the nearest earlier year with accounts, kept rows are logged, and a rolled-back run is retried.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * The database functions are stubbed with an in-memory grupper, kontoplan and settings, so the repair's
 * decisions can be checked per row without a tenant database.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class VatReportRangeMigrationTest extends TestCase
{
    private object $state;
    private string $log;

    protected function setUp(): void
    {
        $this->state = (object) ['grupper' => [], 'kontoplan' => [], 'settings' => [], 'queries' => [], 'writes' => [],
            'transactions' => [], 'acquired' => 1, 'rollback' => false];
        $GLOBALS['vatRangeState'] = $this->state;
        $GLOBALS['db'] = 'test_vatrange';
        $this->log = (string) tempnam(sys_get_temp_dir(), 'vatrange');
        ini_set('error_log', $this->log);
        if (!function_exists('db_select')) {
            function db_select($sql, $location = '') {
                return VatReportRangeMigrationTest::select($sql);
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
                VatReportRangeMigrationTest::modify($sql);
                return true;
            }
            function db_escape_string($value) {
                return str_replace("'", "''", (string) $value);
            }
            function transaktion($operation) {
                $GLOBALS['vatRangeState']->transactions[] = $operation;
            }
        }
        require_once __DIR__ . '/../../../includes/opdat_func/fixVatReportRange.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->log)) {
            unlink($this->log);
        }
        unset($GLOBALS['vatRangeState']);
    }

    /** Answers the repair's SELECTs from the in-memory tables; any other query fails the test. */
    public static function select(string $sql): ArrayIterator
    {
        $state = $GLOBALS['vatRangeState'];
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
        if ($sql === 'SELECT regnskabsaar FROM kontoplan GROUP BY regnskabsaar') {
            $years = array_values(array_unique(array_map(static fn (array $row): string => $row['regnskabsaar'], $state->kontoplan)));
            return new ArrayIterator(array_map(static fn (string $year): array => ['regnskabsaar' => $year], $years));
        }
        if ($sql === "SELECT id, box1, box2, fiscal_year FROM grupper WHERE art = 'MR' ORDER BY id") {
            return new ArrayIterator(array_values(array_filter($state->grupper, static fn (array $row): bool => $row['art'] === 'MR')));
        }
        if (preg_match('/^SELECT kontonr, kontotype FROM kontoplan WHERE regnskabsaar = (\d+) AND kontonr >= (\d+) AND kontonr <= (\d+)$/', $sql, $m)) {
            return new ArrayIterator(array_values(array_filter($state->kontoplan, static fn (array $row): bool =>
                $row['regnskabsaar'] === $m[1] && (int) $row['kontonr'] >= (int) $m[2] && (int) $row['kontonr'] <= (int) $m[3])));
        }
        if (preg_match("/^SELECT art, box1, box3 FROM grupper WHERE fiscal_year = (\d+) AND art IN \('SM', 'KM', 'EM', 'YM'\)$/", $sql, $m)) {
            return new ArrayIterator(array_values(array_filter($state->grupper, static fn (array $row): bool =>
                $row['fiscal_year'] === $m[1] && in_array($row['art'], ['SM', 'KM', 'EM', 'YM'], true))));
        }
        throw new RuntimeException("Unexpected query: $sql");
    }

    /** Applies the repair's UPDATE by id and its flag INSERT; any other statement fails the test. */
    public static function modify(string $sql): void
    {
        $state = $GLOBALS['vatRangeState'];
        $state->writes[] = $sql;
        $value = "'((?:[^']|'')*)'";
        if (preg_match("/^UPDATE grupper SET box1 = $value, box2 = $value WHERE id = (\d+) AND art = 'MR' AND box1 = $value AND box2 = $value$/", $sql, $m)) {
            [$box1, $box2, $old1, $old2] = array_map(static fn (string $s): string => str_replace("''", "'", $s), [$m[1], $m[2], $m[4], $m[5]]);
            foreach ($state->grupper as $i => $row) {
                if ($row['id'] === $m[3] && $row['art'] === 'MR' && $row['box1'] === $old1 && $row['box2'] === $old2) {
                    $state->grupper[$i]['box1'] = $box1;
                    $state->grupper[$i]['box2'] = $box2;
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

    /** Adds the MR row of a fiscal year; returns its id. */
    private function reportRange(int $year, string $box1, string $box2): string
    {
        $id = (string) (count($this->state->grupper) + 1);
        $this->state->grupper[] = ['id' => $id, 'art' => 'MR', 'box1' => $box1, 'box2' => $box2, 'box3' => '1630', 'fiscal_year' => (string) $year];
        return $id;
    }

    /**
     * Seeds a fiscal year with the standard chart's accounts around the range (importfiler/kontoplan.txt) and the
     * standard VAT codes (importfiler/grupper.txt), plus extra accounts by kontonr => kontotype.
     *
     * @param array<int, string> $extra
     */
    private function standardYear(int $year, array $extra = [], bool $vatCodes = true): void
    {
        $accounts = [6810 => 'S', 6930 => 'Z', 7110 => 'S', 7440 => 'S', 7450 => 'Z', 7630 => 'S', 7680 => 'S', 7700 => 'S',
            7720 => 'S', 7740 => 'S', 7760 => 'S', 7780 => 'S', 7800 => 'S', 7810 => 'S', 7820 => 'S', 7830 => 'S', 7840 => 'S',
            7860 => 'S', 7900 => 'S'];
        foreach ($extra + $accounts as $kontonr => $kontotype) {
            $this->state->kontoplan[] = ['kontonr' => (string) $kontonr, 'kontotype' => $kontotype, 'regnskabsaar' => (string) $year];
        }
        if ($vatCodes) {
            $this->vatCode($year, 'SM', '7680');
            $this->vatCode($year, 'KM', '7740');
            $this->vatCode($year, 'EM', '7700', '7740');
            $this->vatCode($year, 'YM', '7720', '7740');
        }
    }

    private function vatCode(int $year, string $art, string $box1, string $box3 = ''): void
    {
        $this->state->grupper[] = ['id' => (string) (count($this->state->grupper) + 1), 'art' => $art, 'box1' => $box1,
            'box2' => '25', 'box3' => $box3, 'fiscal_year' => (string) $year];
    }

    /** @return array{0: string, 1: string} box1 and box2 of the grupper row with this id. */
    private function range(string $id): array
    {
        foreach ($this->state->grupper as $row) {
            if ($row['id'] === $id) {
                return [$row['box1'], $row['box2']];
            }
        }
        self::fail("No grupper row $id");
    }

    private function assertFlagWritten(): void
    {
        $flags = array_filter($this->state->settings, static fn (array $row): bool =>
            $row['var_grp'] === 'system' && $row['var_name'] === 'vat_report_range_7680');
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

    public function testSeededTypoOnTheStandardChartBecomesTheVatReturnRange(): void
    {
        $this->standardYear(1);
        $id = $this->reportRange(1, '6780', '7740');
        self::assertSame('done', fixVatReportRange('postgresql'));
        self::assertSame(['7680', '7830'], $this->range($id));
        $this->assertFlagWritten();
        foreach ($this->updates() as $sql) {
            self::assertStringNotContainsStringIgnoringCase('select', $sql, 'MySQL refuses a subquery on the UPDATE target');
        }
        self::assertStringContainsString("pg_advisory_lock(hashtext('saldi_vat_report_range'))", $this->state->queries[1]);
        self::assertStringContainsString("pg_advisory_unlock(hashtext('saldi_vat_report_range'))", end($this->state->queries));
        self::assertStringContainsString('Saldi test_vatrange: VAT report range', $this->logged());
        self::assertStringContainsString('year 1 6780..7740 -> 7680..7830', $this->logged());
        self::assertStringContainsString('1 changed, 0 kept', $this->logged());
    }

    public function testAnotherAccountBetweenTheDutiesKeepsTheEndAt7740(): void
    {
        $this->standardYear(1, [7750 => 'S']);
        $id = $this->reportRange(1, '6780', '7740');
        fixVatReportRange('postgresql');
        self::assertSame(['7680', '7740'], $this->range($id));
        $this->assertFlagWritten();
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function vatCodesBelow7680(): array
    {
        return ['sales VAT on 7600' => ['SM', '7600', ''], 'purchase VAT on 6790' => ['KM', '6790', ''],
            'reverse charge offset on 7679' => ['EM', '7700', '7679'], 'service VAT on 6780' => ['YM', '6780', '7740']];
    }

    #[DataProvider('vatCodesBelow7680')]
    public function testAVatCodeBookingBelow7680KeepsTheTypoRange(string $art, string $box1, string $box3): void
    {
        $this->standardYear(1, [(int) $box1 => 'S']);
        $this->vatCode(1, $art, $box1, $box3);
        $id = $this->reportRange(1, '6780', '7740');
        fixVatReportRange('postgresql');
        self::assertSame(['6780', '7740'], $this->range($id));
        self::assertSame([], $this->updates());
        $this->assertFlagWritten();
        self::assertStringContainsString('year 1 6780..7740 kept', $this->logged());
        self::assertStringContainsString('0 changed, 1 kept by a guard', $this->logged());
    }

    public function testAVatCodeOfAnotherYearDoesNotBlockTheRepair(): void
    {
        $this->standardYear(1);
        $this->standardYear(2);
        $this->vatCode(2, 'KM', '7600');
        $id = $this->reportRange(1, '6780', '7740');
        fixVatReportRange('postgresql');
        self::assertSame(['7680', '7830'], $this->range($id));
    }

    public function testRepairedStartGetsTheDutyAccounts(): void
    {
        $this->standardYear(1);
        $id = $this->reportRange(1, '7680', '7740');
        fixVatReportRange('postgresql');
        self::assertSame(['7680', '7830'], $this->range($id));
        $this->assertFlagWritten();
        self::assertStringContainsString('year 1 7680..7740 -> 7680..7830', $this->logged());
    }

    /** @return array<string, array{0: int, 1: string}> */
    public static function foreignAccountsBetweenTheDuties(): array
    {
        return ['balance account' => [7790, 'S'], 'heading' => [7741, 'H'], 'sum account' => [7825, 'Z']];
    }

    #[DataProvider('foreignAccountsBetweenTheDuties')]
    public function testAForeignAccountBetweenTheDutiesLeavesARepairedStartAlone(int $kontonr, string $kontotype): void
    {
        $this->standardYear(1, [$kontonr => $kontotype]);
        $id = $this->reportRange(1, '7680', '7740');
        fixVatReportRange('postgresql');
        self::assertSame(['7680', '7740'], $this->range($id));
        self::assertSame([], $this->updates());
        $this->assertFlagWritten();
        self::assertStringContainsString('year 1 7680..7740 kept', $this->logged());
    }

    /** @return array<string, array{0: array<int, string>}> */
    public static function otherCharts(): array
    {
        return ['no 7680' => [[]], '7680 a heading' => [[7680 => 'H']], '7680 an operating account' => [[7680 => 'D']]];
    }

    /** @param array<int, string> $accounts */
    #[DataProvider('otherCharts')]
    public function testAnotherChartOfAccountsIsLeftAlone(array $accounts): void
    {
        foreach ($accounts + [7440 => 'S', 7740 => 'S', 7840 => 'S'] as $kontonr => $kontotype) {
            $this->state->kontoplan[] = ['kontonr' => (string) $kontonr, 'kontotype' => $kontotype, 'regnskabsaar' => '1'];
        }
        $typo = $this->reportRange(1, '6780', '7740');
        $repaired = $this->reportRange(1, '7680', '7740');
        fixVatReportRange('postgresql');
        self::assertSame(['6780', '7740'], $this->range($typo));
        self::assertSame(['7680', '7740'], $this->range($repaired));
        self::assertSame([], $this->updates());
        $this->assertFlagWritten();
    }

    public function testEveryFiscalYearIsJudgedAgainstItsOwnChart(): void
    {
        $this->standardYear(1);
        $this->standardYear(2);
        $this->standardYear(4, [7750 => 'S']);
        $first = $this->reportRange(1, '6780', '7740');
        $second = $this->reportRange(2, '6780', '7740');
        $noChart = $this->reportRange(3, '6780', '7740'); // no accounts of its own: judged by year 2, the nearest earlier year
        $fourth = $this->reportRange(4, '7680', '7740');
        fixVatReportRange('postgresql');
        self::assertSame(['7680', '7830'], $this->range($first));
        self::assertSame(['7680', '7830'], $this->range($second));
        self::assertSame(['7680', '7830'], $this->range($noChart));
        self::assertSame(['7680', '7740'], $this->range($fourth));
        self::assertCount(3, $this->updates());
        $this->assertFlagWritten();
        self::assertStringContainsString('3 changed, 1 kept by a guard', $this->logged());
        self::assertStringContainsString('year 4 7680..7740 kept', $this->logged());
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
        $id = $this->reportRange(1, '6780', '7740');
        fixVatReportRange('postgresql');
        self::assertSame(['7680', '7830'], $this->range($id));
    }

    public function testNoAccountsAtAllKeepsTheRowAndLogsIt(): void
    {
        $id = $this->reportRange(1, '6780', '7740');
        fixVatReportRange('postgresql');
        self::assertSame(['6780', '7740'], $this->range($id));
        self::assertSame([], $this->updates());
        $this->assertFlagWritten();
        self::assertStringContainsString('year 1 6780..7740 kept', $this->logged());
    }

    public function testAYearWithoutVatCodesUsesTheVatCodesOfItsChartYear(): void
    {
        $this->standardYear(1);
        $this->vatCode(1, 'KM', '7600');
        $id = $this->reportRange(2, '6780', '7740'); // year 2 has neither accounts nor VAT codes of its own
        fixVatReportRange('postgresql');
        self::assertSame(['6780', '7740'], $this->range($id));
        self::assertStringContainsString('year 2 6780..7740 kept', $this->logged());
    }

    public function testAYearWithItsOwnVatCodesIsNotBlockedByTheChartYearsCodes(): void
    {
        $this->standardYear(1);
        $this->vatCode(1, 'KM', '7600');
        $this->vatCode(2, 'SM', '7680');
        $this->vatCode(2, 'KM', '7740');
        $id = $this->reportRange(2, '6780', '7740');
        fixVatReportRange('postgresql');
        self::assertSame(['7680', '7830'], $this->range($id));
    }

    public function testARolledBackRunReportsFailedAndIsRetried(): void
    {
        $this->standardYear(1);
        $this->reportRange(1, '6780', '7740');
        $this->state->rollback = true;
        self::assertSame('failed', fixVatReportRange('postgresql'));
        self::assertStringContainsString('was rolled back; the next login tries again', $this->logged());
        self::assertStringNotContainsString('changed', $this->logged());
        self::assertStringContainsString('pg_advisory_unlock', end($this->state->queries));
        $this->state->rollback = false;
        self::assertSame('done', fixVatReportRange('postgresql'));
        self::assertCount(1, $this->state->settings);
        self::assertSame(['begin', 'commit', 'begin', 'commit'], $this->state->transactions);
    }

    public function testStoredWhitespaceIsIgnoredAndTheRowStillMatchedByItsStoredValues(): void
    {
        $this->standardYear(1);
        $id = $this->reportRange(1, ' 6780', '7740 ');
        fixVatReportRange('postgresql');
        self::assertSame(['7680', '7830'], $this->range($id));
    }

    public function testFlagPresentRunsNothing(): void
    {
        $this->standardYear(1);
        $id = $this->reportRange(1, '6780', '7740');
        $this->state->settings[] = ['id' => '1', 'var_grp' => 'system', 'var_name' => 'vat_report_range_7680', 'var_value' => '20261113'];
        self::assertSame('done', fixVatReportRange('postgresql'));
        self::assertSame(['6780', '7740'], $this->range($id));
        self::assertCount(1, $this->state->queries);
        self::assertSame([], $this->state->writes);
        self::assertSame([], $this->state->transactions);
    }

    public function testSecondLoginOnlyReadsTheFlag(): void
    {
        $this->standardYear(1);
        $this->reportRange(1, '6780', '7740');
        fixVatReportRange('postgresql');
        $writes = $this->state->writes;
        $queries = count($this->state->queries);
        fixVatReportRange('postgresql');
        self::assertSame($writes, $this->state->writes);
        self::assertSame($queries + 1, count($this->state->queries));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function handMadeRanges(): array
    {
        return ['own chart' => ['66100', '66200'], 'already the new range' => ['7680', '7830'], 'own end' => ['6780', '7760'],
            'empty' => ['', '']];
    }

    #[DataProvider('handMadeRanges')]
    public function testAHandMadeRangeIsLeftAlone(string $box1, string $box2): void
    {
        $this->standardYear(1);
        $id = $this->reportRange(1, $box1, $box2);
        fixVatReportRange('postgresql');
        self::assertSame([$box1, $box2], $this->range($id));
        self::assertSame([], $this->updates());
        self::assertSame([], array_filter($this->state->queries, static fn (string $sql): bool => str_contains($sql, 'FROM kontoplan')));
        $this->assertFlagWritten();
        self::assertSame('', $this->logged());
    }

    public function testMysqlBusyLockLeavesTheRepairToTheNextLogin(): void
    {
        $this->standardYear(1);
        $id = $this->reportRange(1, '6780', '7740');
        $this->state->acquired = 0;
        self::assertSame('busy', fixVatReportRange('mysqli'));
        self::assertSame(['6780', '7740'], $this->range($id));
        self::assertSame([], $this->state->writes);
        self::assertStringNotContainsString('RELEASE_LOCK(', implode("\n", $this->state->queries));
        $this->state->acquired = 1;
        self::assertSame('done', fixVatReportRange('mysql'));
        self::assertSame(['7680', '7830'], $this->range($id));
        $lock = 'saldi_vatrange_' . substr(md5('test_vatrange'), 0, 16);
        self::assertContains("SELECT GET_LOCK('$lock', 30) AS acquired", $this->state->queries);
        self::assertSame("SELECT RELEASE_LOCK('$lock')", end($this->state->queries));
        $this->assertFlagWritten();
    }

    public function testBetweenUpdatesRunsTheRepairAfterTheTillAccountCopy(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../../includes/betweenUpdates.php');
        $till = strpos($source, 'migrateTillAccounts($db_type);');
        $call = strpos($source, "require_once __DIR__ . '/opdat_func/fixVatReportRange.php';\nfixVatReportRange(\$db_type);");
        self::assertNotFalse($till);
        self::assertNotFalse($call);
        self::assertGreaterThan($till, $call);
        self::assertSame('?>', trim(substr($source, $call + strlen("require_once __DIR__ . '/opdat_func/fixVatReportRange.php';\nfixVatReportRange(\$db_type);"))));
    }
}
