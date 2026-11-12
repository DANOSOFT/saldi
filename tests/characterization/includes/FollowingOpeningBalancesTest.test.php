<?php
// 20261112 CL/MMR SST-1005 Pins how later fiscal years follow an earlier year: a zero balance needs no destination and a blocked transfer is reported.
//                 Postings in an earlier year are carried forward once a session in a later year opens a page, and only then.
//                 The SQL runs against PostgreSQL temporary tables only.
//                 Gem/opdatér on the year card leaves the later years alone when the transfer into the saved year is blocked (Codex round 1).
//                 Invalid dates stop before anything is written, and a failed rebuild is rolled back and stays visible until Gem/opdatér (Codex round 2).
//                 CodeRabbit on PR #1109: JSON-encoded alert, reversed ranges, «Sæt aktivt» on year 1 / after a deleted year, the ten-minute fingerprint, and the card keeping a blocked year's opening balances.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FollowingOpeningBalancesTest extends TestCase
{
    private $connection;

    protected function setUp(): void
    {
        $helper = __DIR__ . '/../support/database/TestDatabase.php';
        if (!is_file($helper)) {
            self::markTestSkipped('tests/characterization/support/database/TestDatabase.php is not in this tree');
        }
        require_once $helper;
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $this->connection = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        $GLOBALS['connection'] = $this->connection;
        $GLOBALS['db_type'] = 'postgres';
        $GLOBALS['db_transaktion_depth'] = 0;
        // Only temporary tables are visible, so nothing outside this session can be changed.
        $this->sql('SET search_path TO pg_temp');
        $this->sql("CREATE TEMP TABLE grupper (id SERIAL, kodenr INTEGER, art TEXT, beskrivelse TEXT, box1 TEXT, box2 TEXT, box3 TEXT, box4 TEXT, box5 TEXT, box6 TEXT, box7 TEXT, box8 TEXT, box9 TEXT, box10 TEXT)");
        $this->sql("CREATE TEMP TABLE kontoplan (id SERIAL, regnskabsaar INTEGER, kontonr INTEGER, kontotype TEXT, primo NUMERIC DEFAULT 0, saldo NUMERIC DEFAULT 0, overfor_til INTEGER DEFAULT 0, fra_kto INTEGER DEFAULT 0, lukket TEXT DEFAULT '')");
        $this->sql("CREATE TEMP TABLE transaktioner (id SERIAL, kontonr INTEGER, transdate DATE, debet NUMERIC DEFAULT 0, kredit NUMERIC DEFAULT 0)");
        $this->sql("CREATE TEMP TABLE settings (id SERIAL, var_name TEXT, var_grp TEXT, var_value TEXT, var_description TEXT, user_id INTEGER)");
        // Years 8-11 run July to June (year 9 = 2023/24). 1999 is the Sideskift account, its result goes to 3000.
        foreach ([8, 9, 10, 11] as $year) {
            $start = 2014 + $year;
            $end = $start + 1;
            $this->sql("INSERT INTO grupper (kodenr, art, beskrivelse, box1, box2, box3, box4, box5, box10) VALUES ($year, 'RA', '$start/" . substr((string) $end, 2) . "', '7', '$start', '6', '$end', 'on', '')");
            foreach ([1000 => 'D', 1999 => 'X', 2000 => 'S', 2100 => 'S', 2500 => 'S', 2900 => 'Z', 2950 => 'R', 3000 => 'S'] as $account => $type) {
                $destination = $account === 1999 ? 3000 : ($account === 2100 ? 2000 : 0);
                $from = $type === 'Z' ? 2000 : ($type === 'R' ? 2900 : 0);
                $this->sql("INSERT INTO kontoplan (regnskabsaar, kontonr, kontotype, primo, saldo, overfor_til, fra_kto) VALUES ($year, $account, '$type', 777, 888, $destination, $from)");
            }
        }
        $this->sql('UPDATE kontoplan SET primo = 0, saldo = 0 WHERE regnskabsaar = 9');
        $this->sql('UPDATE kontoplan SET primo = 1000 WHERE regnskabsaar = 9 AND kontonr = 2000');
        $this->sql('UPDATE kontoplan SET primo = -1000 WHERE regnskabsaar = 9 AND kontonr = 3000');
        $this->sql("INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES
            (2000, '2024-02-29', 100, 0), (1000, '2024-02-29', 0, 100),
            (2100, '2024-06-30', 20, 0), (1000, '2024-06-30', 0, 20),
            (2000, '2024-07-01', 50, 0), (1000, '2024-07-01', 0, 50)");

        if (!function_exists('db_select')) {
            function db_select($sql, $location = '') {
                $result = pg_query($GLOBALS['connection'], $sql);
                if ($result === false) {
                    throw new RuntimeException(pg_last_error($GLOBALS['connection']) . " in $sql");
                }
                return $result;
            }
            function db_modify($sql, $location = '') {
                return db_select($sql, $location);
            }
            function db_fetch_array($result) {
                return pg_fetch_assoc($result);
            }
            function db_escape_string($value) {
                return pg_escape_string($GLOBALS['connection'], (string) $value);
            }
            function transaktion($sql) {
                db_select($sql);
            }
            /** The texts are not seeded here, so findtekst() gives the fallback after the '|'. */
            function findtekst($textId, $languageId) {
                $parts = explode('|', (string) $textId, 2);
                return $parts[1] ?? $parts[0];
            }
        }
        if ($this->name() === 'testAnUnexpectedErrorRollsTheCarryForwardBackAndIsReported' && !function_exists('genberegn')) {
            /** Fails after the first target year's primo is written, like an error nobody expected. */
            function genberegn($year, $updateUsage = true) {
                throw new RuntimeException('test: genberegn stopped');
            }
        }
        require_once dirname(__DIR__, 3) . '/includes/std_func.php';
        require_once dirname(__DIR__, 3) . '/includes/genberegn.php';
        require_once dirname(__DIR__, 3) . '/includes/updateFollowingOpeningBalances.php';
    }

    protected function tearDown(): void
    {
        if ($this->connection) {
            pg_close($this->connection);
        }
        unset($GLOBALS['connection']);
    }

    private function sql(string $sql)
    {
        $result = pg_query($this->connection, $sql);
        if ($result === false) {
            self::fail(pg_last_error($this->connection) . " in $sql");
        }
        return $result;
    }

    private function primo(int $year, int $account): float
    {
        $row = pg_fetch_assoc($this->sql("SELECT primo FROM kontoplan WHERE regnskabsaar = $year AND kontonr = $account"));
        return (float) $row['primo'];
    }

    /** primo and saldo of every account in a year, plus the year's genberegn summary in grupper box6-8. */
    private function yearState(int $year): array
    {
        $accounts = pg_fetch_all($this->sql("SELECT kontonr, primo, saldo FROM kontoplan WHERE regnskabsaar = $year ORDER BY kontonr")) ?: [];
        $summary = pg_fetch_assoc($this->sql("SELECT box6, box7, box8 FROM grupper WHERE art = 'RA' AND kodenr = $year"));
        return ['accounts' => $accounts, 'summary' => $summary];
    }

    /** The id watermark and the waiting year from 'openingBalanceSync' ("lastId|pendingYear|fingerprintTime"). */
    private function syncState(): string
    {
        $parts = explode('|', (string) $this->setting('openingBalanceSync'));
        return $parts[0] . '|' . ($parts[1] ?? '');
    }

    private function setting(string $name): ?string
    {
        $row = pg_fetch_assoc($this->sql("SELECT var_value FROM settings WHERE var_name = '$name'"));
        return $row === false ? null : $row['var_value'];
    }

    private function postInYear9(int $account, float $amount): void
    {
        $this->sql("INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES ($account, '2024-03-01', $amount, 0), (1000, '2024-03-01', 0, $amount)");
    }

    /** The stop shape with only the given fields set. */
    private function stop(array $fields): array
    {
        return array_replace([
            'sourceYear' => 9,
            'targetYear' => 10,
            'accounts' => [],
            'resultAccount' => 0,
            'resultDestination' => 0,
            'noResultAccount' => false,
            'noStatusAccounts' => false,
            'invalidDates' => [],
            'failed' => false,
        ], $fields);
    }

    public function testAZeroBalanceWithoutAnAccountInTheNextYearDoesNotBlock(): void
    {
        // 2500 has no primo and no postings in year 9, so there is nothing to carry.
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        $stop = 'unset';
        self::assertSame([10, 11], updateFollowingOpeningBalances(9, $stop));
        self::assertNull($stop);
        self::assertSame(1120.0, $this->primo(10, 2000));
        self::assertSame(-1120.0, $this->primo(10, 3000));
        self::assertSame(1170.0, $this->primo(11, 2000));
    }

    public function testABalanceWithoutAnAccountInTheNextYearBlocksAndIsReported(): void
    {
        $this->postInYear9(2500, 7.5);
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        $stop = null;
        self::assertSame([], updateFollowingOpeningBalances(9, $stop));
        self::assertSame($this->stop(['accounts' => [2500]]), $stop);
        self::assertSame(777.0, $this->primo(10, 2000), 'The blocked year is left as it was.');
        self::assertSame(777.0, $this->primo(11, 2000), 'Later years are left as they were.');
    }

    public function testAMissingResultDestinationIsReported(): void
    {
        $this->sql('UPDATE kontoplan SET overfor_til = 3999 WHERE regnskabsaar = 9 AND kontonr = 1999');
        $stop = null;
        self::assertSame([], updateFollowingOpeningBalances(9, $stop));
        self::assertSame($this->stop(['resultAccount' => 1999, 'resultDestination' => 3999]), $stop);
    }

    public function testAResultWithoutASideskiftAccountBlocksButNoResultNeedsNone(): void
    {
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 9 AND kontonr = 1999');
        $stop = null;
        self::assertSame([], updateFollowingOpeningBalances(9, $stop));
        self::assertSame($this->stop(['noResultAccount' => true]), $stop);

        // Without postings on the result accounts there is no result to carry.
        $this->sql("DELETE FROM transaktioner WHERE kontonr = 1000 AND transdate < '2024-07-01'");
        self::assertSame([10, 11], updateFollowingOpeningBalances(9, $stop));
        self::assertNull($stop);
    }

    public function testATargetYearWithoutStatusAccountsIsReported(): void
    {
        $this->sql("DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontotype = 'S'");
        $stop = null;
        self::assertSame([], updateFollowingOpeningBalances(9, $stop));
        self::assertSame($this->stop(['noStatusAccounts' => true]), $stop);
    }

    public function testPostingsInAnEarlierYearWaitForASessionInALaterYear(): void
    {
        // The first check only starts following postings; it does not rebuild the past.
        self::assertNull(checkFollowingOpeningBalances(11));
        $maxId = pg_fetch_assoc($this->sql('SELECT max(id) AS id FROM transaktioner'))['id'];
        self::assertSame("$maxId|0|0", $this->setting('openingBalanceSync'));
        self::assertSame(777.0, $this->primo(10, 2000));

        // A posting in year 9 while the session works in year 9: remembered, not rebuilt yet.
        $this->postInYear9(2000, 5);
        self::assertNull(checkFollowingOpeningBalances(9));
        $maxId = pg_fetch_assoc($this->sql('SELECT max(id) AS id FROM transaktioner'))['id'];
        self::assertSame("$maxId|9", $this->syncState());
        self::assertSame(777.0, $this->primo(10, 2000));

        // A session in year 10 opens a page: years 10 and 11 follow year 9, as after Gem/opdatér.
        self::assertNull(checkFollowingOpeningBalances(10));
        self::assertSame("$maxId|0", $this->syncState());
        self::assertSame(1125.0, $this->primo(10, 2000));
        self::assertSame(-1125.0, $this->primo(10, 3000));
        self::assertSame(1175.0, $this->primo(11, 2000));
        self::assertSame(-1175.0, $this->primo(11, 3000));

        // A posting in the newest year has no later year to carry into.
        $this->sql("INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2025-08-01', 10, 0), (1000, '2025-08-01', 0, 10)");
        self::assertNull(checkFollowingOpeningBalances(11));
        $maxId = pg_fetch_assoc($this->sql('SELECT max(id) AS id FROM transaktioner'))['id'];
        self::assertSame("$maxId|0", $this->syncState());
        self::assertSame(1175.0, $this->primo(11, 2000));
    }

    public function testNothingRunsInsideACallersTransaction(): void
    {
        self::assertNull(checkFollowingOpeningBalances(11));
        $this->postInYear9(2000, 5);
        $state = $this->setting('openingBalanceSync');
        $this->sql('BEGIN');
        self::assertNull(checkFollowingOpeningBalances(10));
        self::assertNull(syncFollowingOpeningBalances(9));
        self::assertSame($state, $this->setting('openingBalanceSync'));
        self::assertSame(777.0, $this->primo(10, 2000));
        $this->sql('ROLLBACK');
    }

    public function testOnlyOneCheckPerRequest(): void
    {
        self::assertNull(refreshFollowingOpeningBalances(11));
        $this->postInYear9(2000, 5);
        self::assertNull(refreshFollowingOpeningBalances(10));
        self::assertSame(777.0, $this->primo(10, 2000), 'The second call in the same request does nothing.');
    }

    public function testSyncStoresABlockedTransferUntilARebuildGetsPastIt(): void
    {
        $this->postInYear9(2500, 7.5);
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        $expected = $this->stop(['accounts' => [2500]]);
        self::assertSame($expected, syncFollowingOpeningBalances(9));
        self::assertSame($expected, followingOpeningBalanceStop());

        // A rebuild that starts after the blocked year does not touch it.
        self::assertNull(syncFollowingOpeningBalances(10));
        self::assertSame($expected, followingOpeningBalanceStop());

        // Once the account exists in year 10, the next rebuild gets past it and the message goes away.
        $this->sql("INSERT INTO kontoplan (regnskabsaar, kontonr, kontotype) VALUES (10, 2500, 'S')");
        self::assertNull(syncFollowingOpeningBalances(9));
        self::assertNull(followingOpeningBalanceStop());
        self::assertSame(7.5, $this->primo(10, 2500));
        self::assertSame(1120.0, $this->primo(10, 2000));
    }

    public function testSyncStartsAtAnEarlierWaitingYearAndClearsIt(): void
    {
        self::assertNull(checkFollowingOpeningBalances(11));
        $this->sql("INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2023-03-01', 3, 0), (1000, '2023-03-01', 0, 3)");
        self::assertNull(checkFollowingOpeningBalances(8));
        self::assertStringEndsWith('|8', $this->syncState());

        // «Sæt aktivt» on year 11 asks for a rebuild from year 10, but year 8 is waiting, so it starts there.
        self::assertNull(syncFollowingOpeningBalances(10, 11));
        self::assertStringEndsWith('|0', $this->syncState());
        // Year 9 now opens with year 8's closing balances: 2000 = 777 + 3 + 777 from 2100, 3000 = 777 + result 774.
        self::assertSame(1557.0, $this->primo(9, 2000));
        self::assertSame(1551.0, $this->primo(9, 3000));
    }

    public function testGemOpdaterLeavesTheLaterYearsAloneWhenTheTransferIntoTheSavedYearIsBlocked(): void
    {
        $this->postInYear9(2500, 7.5);
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        $this->sql('UPDATE kontoplan SET primo = 4444 WHERE regnskabsaar = 11 AND kontonr = 2000');
        $year10 = $this->yearState(10);
        $year11 = $this->yearState(11);
        $expected = $this->stop(['accounts' => [2500]]);
        self::assertSame($expected, updateOpeningBalancesAfterYearSave(10));
        self::assertSame($year10, $this->yearState(10));
        self::assertSame($year11, $this->yearState(11), 'Year 11 is not carried forward from a year whose own transfer is blocked.');
        self::assertSame($expected, followingOpeningBalanceStop());
    }

    public function testGemOpdaterWithAMissingResultDestinationLeavesTheLaterYearsAlone(): void
    {
        $this->sql('UPDATE kontoplan SET overfor_til = 3999 WHERE regnskabsaar = 9 AND kontonr = 1999');
        $year10 = $this->yearState(10);
        $year11 = $this->yearState(11);
        self::assertSame($this->stop(['resultAccount' => 1999, 'resultDestination' => 3999]), updateOpeningBalancesAfterYearSave(10));
        self::assertSame($year10, $this->yearState(10));
        self::assertSame($year11, $this->yearState(11));
    }

    public function testGemOpdaterOnTheFirstYearCarriesItsOwnBalancesForward(): void
    {
        // Year 8 has no year before it: its opening balances are typed in, so years 9-11 follow year 8.
        self::assertNull(updateOpeningBalancesAfterYearSave(8));
        self::assertSame(1554.0, $this->primo(9, 2000), '777 on 2000 plus 777 from 2100.');
        self::assertSame(1554.0, $this->primo(9, 3000), '777 on 3000 plus the year 8 result 777.');
        self::assertSame(1674.0, $this->primo(10, 2000));
    }

    public function testGemOpdaterAfterADeletedYearCarriesItsOwnBalancesForward(): void
    {
        $this->sql("UPDATE grupper SET box10 = 'on' WHERE art = 'RA' AND kodenr = 9");
        $year10 = $this->yearState(10);
        self::assertNull(updateOpeningBalancesAfterYearSave(10));
        self::assertSame($year10['accounts'], $this->yearState(10)['accounts'], 'Year 10 keeps the balances saved on its card.');
        self::assertSame(1604.0, $this->primo(11, 2000), '777 + 50 on 2000 plus 777 from 2100.');
        self::assertSame(1504.0, $this->primo(11, 3000), '777 on 3000 plus the year 10 result 727.');
    }

    public function testGemOpdaterThatGetsPastABlockClearsTheMessage(): void
    {
        $this->postInYear9(2500, 7.5);
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        self::assertNotNull(syncFollowingOpeningBalances(9));
        self::assertNotNull(followingOpeningBalanceStop());
        $this->sql("INSERT INTO kontoplan (regnskabsaar, kontonr, kontotype) VALUES (10, 2500, 'S')");
        self::assertNull(updateOpeningBalancesAfterYearSave(10));
        self::assertNull(followingOpeningBalanceStop());
        self::assertSame(7.5, $this->primo(10, 2500));
        self::assertSame(1170.0, $this->primo(11, 2000));
    }

    public function testInvalidSourceDatesAreReportedInsteadOfThrown(): void
    {
        $this->sql("UPDATE grupper SET box1 = '' WHERE art = 'RA' AND kodenr = 9");
        $year10 = $this->yearState(10);
        $expected = $this->stop(['invalidDates' => [9]]);
        self::assertSame($expected, syncFollowingOpeningBalances(9));
        self::assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status($this->connection));
        self::assertSame($expected, followingOpeningBalanceStop());
        $this->sql('BEGIN');
        self::assertSame($expected, updateOpeningBalancesAfterYearSave(10));
        $this->sql('COMMIT');
        self::assertSame($year10, $this->yearState(10));
    }

    public function testAnInvalidTargetYearIsNotHalfRebuiltByTheCard(): void
    {
        // Year 10 first gets a real saldo from valid dates, then its start month is broken.
        genberegn(10, false);
        $this->sql("UPDATE grupper SET box1 = '' WHERE art = 'RA' AND kodenr = 10");
        $year10 = $this->yearState(10);
        $year11 = $this->yearState(11);
        self::assertSame(827.0, (float) $year10['accounts'][2]['saldo'], 'Account 2000: 777 + 50 posted in year 10.');
        $this->sql('BEGIN');
        $stop = updateOpeningBalancesAfterYearSave(10);
        $this->sql('COMMIT');
        self::assertSame($this->stop(['invalidDates' => [10]]), $stop);
        self::assertSame($year10, $this->yearState(10), 'No primo is written without its saldo and summary.');
        self::assertSame($year11, $this->yearState(11));
        self::assertSame($stop, followingOpeningBalanceStop());
    }

    public function testFailedAutomaticWorkIsReportedAndRecoveredByGemOpdater(): void
    {
        self::assertNull(checkFollowingOpeningBalances(11));
        $this->postInYear9(2000, 5);
        self::assertNull(checkFollowingOpeningBalances(9));
        $this->sql("UPDATE grupper SET box1 = '' WHERE art = 'RA' AND kodenr = 10");
        $expected = $this->stop(['invalidDates' => [10]]);
        self::assertSame($expected, checkFollowingOpeningBalances(10), 'The failed rebuild is reported, not dropped.');
        self::assertStringEndsWith('|0', $this->syncState(), 'No retry on every page.');
        self::assertSame($expected, followingOpeningBalanceStop(), 'The message stays until it is fixed.');
        self::assertSame(777.0, $this->primo(10, 2000));

        // The dates are repaired; nothing new was posted, so the message still asks for Gem/opdatér.
        $this->sql("UPDATE grupper SET box1 = '7' WHERE art = 'RA' AND kodenr = 10");
        self::assertNull(checkFollowingOpeningBalances(10));
        self::assertSame($expected, followingOpeningBalanceStop());

        // Gem/opdatér on the year 10 card retries from year 9 and clears the message.
        $this->sql('BEGIN');
        self::assertNull(updateOpeningBalancesAfterYearSave(10));
        $this->sql('COMMIT');
        self::assertSame(1125.0, $this->primo(10, 2000));
        self::assertSame(1175.0, $this->primo(11, 2000));
        self::assertNull(followingOpeningBalanceStop());
    }

    public function testAnUnexpectedErrorRollsTheCarryForwardBackAndIsReported(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'sst1005');
        ini_set('error_log', $log);
        $expected = $this->stop(['failed' => true]);
        // The card's own save happens in the same transaction and must survive.
        $this->sql('BEGIN');
        $this->sql("UPDATE grupper SET beskrivelse = 'saved by the card' WHERE art = 'RA' AND kodenr = 10");
        self::assertSame($expected, updateOpeningBalancesAfterYearSave(10));
        $this->sql('COMMIT');
        self::assertSame(777.0, $this->primo(10, 2000), 'The primo written before the error is rolled back.');
        self::assertSame('saved by the card', pg_fetch_assoc($this->sql("SELECT beskrivelse FROM grupper WHERE art = 'RA' AND kodenr = 10"))['beskrivelse']);
        self::assertSame($expected, followingOpeningBalanceStop());

        self::assertSame($expected, syncFollowingOpeningBalances(9));
        self::assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status($this->connection));
        self::assertSame(777.0, $this->primo(10, 2000));
        self::assertStringContainsString('SST-1005: opening balances after saving fiscal year 10 not updated: test: genberegn stopped', (string) file_get_contents($log));
        unlink($log);
    }

    public function testTheAlertScriptCannotBeEndedByAFiscalYearDescription(): void
    {
        $this->sql("UPDATE grupper SET beskrivelse = '</script><script>window.__sst1005=1</script> ''q'' \"dq\" «æøå»' WHERE art = 'RA' AND kodenr = 10");
        $stop = $this->stop(['accounts' => [2500]]);
        $html = followingOpeningBalanceAlertScript($stop, 1);
        self::assertSame(1, substr_count(strtolower($html), '<script'));
        self::assertSame(1, substr_count(strtolower($html), '</script'));
        self::assertStringStartsWith("<script type='text/javascript'>alert(", $html);
        self::assertStringEndsWith(');</script>', $html);
        $literal = substr($html, strlen("<script type='text/javascript'>alert("), -strlen(');</script>'));
        self::assertSame(followingOpeningBalanceStopText($stop, 1), json_decode($literal));
        self::assertStringContainsString('«æøå»', $html, 'Danish letters stay readable.');
    }

    public function testReversedOrBrokenFiscalYearRangesAreInvalid(): void
    {
        self::assertSame(['2025-07-01', '2025-07-31'], followingOpeningBalanceYearRange(['box1' => '7', 'box2' => '2025', 'box3' => '7', 'box4' => '2025']), 'A one-month year is valid.');
        self::assertSame(['2024-01-01', '2025-06-30'], followingOpeningBalanceYearRange(['box1' => '01', 'box2' => '2024', 'box3' => '06', 'box4' => '2025']), 'An 18-month first year is valid.');
        self::assertNull(followingOpeningBalanceYearRange(['box1' => '7', 'box2' => '2025', 'box3' => '6', 'box4' => '2025']), 'July to June of the same year ends before it starts.');
        self::assertNull(followingOpeningBalanceYearRange(['box1' => '13', 'box2' => '2025', 'box3' => '6', 'box4' => '2026']));
        self::assertNull(followingOpeningBalanceYearRange(['box1' => '7', 'box2' => '2025', 'box3' => '6', 'box4' => '']));

        // A reversed source and a reversed target are both reported before anything is written.
        $year10 = $this->yearState(10);
        $this->sql("UPDATE grupper SET box4 = '2023' WHERE art = 'RA' AND kodenr = 9");
        self::assertSame($this->stop(['invalidDates' => [9]]), syncFollowingOpeningBalances(9));
        self::assertSame($year10, $this->yearState(10));
        $this->sql("UPDATE grupper SET box4 = '2024' WHERE art = 'RA' AND kodenr = 9");
        $this->sql("UPDATE grupper SET box4 = '2024' WHERE art = 'RA' AND kodenr = 10");
        self::assertSame($this->stop(['invalidDates' => [10]]), syncFollowingOpeningBalances(9));
        self::assertSame($year10, $this->yearState(10));
    }

    public function testActivatingTheFirstYearCarriesItForward(): void
    {
        // Year 8 has no year before it, like year 1: «Sæt aktivt» on it carries year 8 itself forward.
        self::assertNull(syncFollowingOpeningBalances(7, 8));
        self::assertSame(1554.0, $this->primo(9, 2000));
        self::assertSame(1674.0, $this->primo(10, 2000));
    }

    public function testActivatingAYearAfterADeletedYearCarriesItForward(): void
    {
        $this->sql("UPDATE grupper SET box10 = 'on' WHERE art = 'RA' AND kodenr = 9");
        self::assertNull(syncFollowingOpeningBalances(9, 10));
        self::assertSame(1604.0, $this->primo(11, 2000));
        self::assertSame(1504.0, $this->primo(11, 3000));
    }

    public function testActivationNeverBypassesARealBlock(): void
    {
        $this->postInYear9(2500, 7.5);
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        $year11 = $this->yearState(11);
        self::assertSame($this->stop(['accounts' => [2500]]), syncFollowingOpeningBalances(9, 10));
        self::assertSame($year11, $this->yearState(11));
    }

    public function testTheFingerprintFindsALateCommitAndADeletedPosting(): void
    {
        self::assertNull(checkFollowingOpeningBalances(11));
        self::assertNull(checkFollowingOpeningBalances(11), 'The first fingerprint only stores the baseline.');
        self::assertNotNull($this->setting('openingBalanceFingerprint'));

        // A year 9 posting whose id the watermark has already passed, as when it commits after a later check.
        $this->postInYear9(2000, 5);
        $maxId = pg_fetch_assoc($this->sql('SELECT max(id) AS id FROM transaktioner'))['id'];
        $this->sql("UPDATE settings SET var_value = '$maxId|0|" . (time() - 60) . "' WHERE var_name = 'openingBalanceSync'");
        self::assertNull(checkFollowingOpeningBalances(10));
        self::assertSame(777.0, $this->primo(10, 2000), 'Within ten minutes of the last fingerprint it is not seen yet.');
        $this->sql("UPDATE settings SET var_value = '$maxId|0|" . (time() - 601) . "' WHERE var_name = 'openingBalanceSync'");
        self::assertNull(checkFollowingOpeningBalances(10));
        self::assertSame(1125.0, $this->primo(10, 2000), 'The next fingerprint finds it and year 10 follows.');

        // A deleted posting changes the fingerprint too.
        $this->sql("DELETE FROM transaktioner WHERE transdate = '2024-03-01'");
        $this->sql("UPDATE settings SET var_value = '$maxId|0|" . (time() - 601) . "' WHERE var_name = 'openingBalanceSync'");
        self::assertNull(checkFollowingOpeningBalances(10));
        self::assertSame(1120.0, $this->primo(10, 2000));
    }

    public function testTheCardKeepsTheSavedYearsOpeningBalancesWhenTheTransferIntoItIsBlocked(): void
    {
        $this->postInYear9(2500, 7.5);
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        $before = followingOpeningBalancePrimo(10);
        $year10 = $this->yearState(10);
        $year11 = $this->yearState(11);
        $this->sql('BEGIN');
        // What the card's own save does first: reset the year and add the transferable balances.
        $this->sql('UPDATE kontoplan SET primo = 0 WHERE regnskabsaar = 10');
        $this->sql('UPDATE kontoplan SET primo = 1120 WHERE regnskabsaar = 10 AND kontonr = 2000');
        $this->sql("UPDATE grupper SET beskrivelse = 'saved by the card' WHERE art = 'RA' AND kodenr = 10");
        self::assertSame($this->stop(['accounts' => [2500]]), updateOpeningBalancesAfterYearSave(10, $before));
        $this->sql('COMMIT');
        self::assertSame($year10['accounts'], $this->yearState(10)['accounts'], 'Year 10 keeps the opening balances it had.');
        self::assertSame($year11, $this->yearState(11));
        self::assertSame('saved by the card', pg_fetch_assoc($this->sql("SELECT beskrivelse FROM grupper WHERE art = 'RA' AND kodenr = 10"))['beskrivelse']);
    }

    public function testTheCardKeepsTypedOpeningBalancesOfTheFirstYear(): void
    {
        $before = followingOpeningBalancePrimo(8);
        $this->sql('BEGIN');
        $this->sql('UPDATE kontoplan SET primo = 1500 WHERE regnskabsaar = 8 AND kontonr = 2000');
        self::assertNull(updateOpeningBalancesAfterYearSave(8, $before));
        $this->sql('COMMIT');
        self::assertSame(1500.0, $this->primo(8, 2000), 'Typed opening balances of a year with no year before it are the save.');
        self::assertSame(2277.0, $this->primo(9, 2000), 'Year 9 follows: 1500 + 777 from 2100.');
    }

    public function testTheMessageNamesTheYearsAndAccounts(): void
    {
        $text = followingOpeningBalanceStopText($this->stop(['accounts' => [2500, 2600]]), 1);
        self::assertSame(
            'Primo i regnskabsåret 2024/25 er ikke opdateret automatisk. '
            . 'Saldoen på konto 2500, 2600 i 2023/24 har ingen konto at blive overført til i 2024/25. '
            . 'Vælg en konto i «Overfør til», eller opret kontoen i 2024/25. '
            . 'Ret det på regnskabsåret 2024/25 under Indstillinger → Regnskabsår, og tryk Gem/opdatér.',
            $text
        );
        $text = followingOpeningBalanceStopText($this->stop(['resultAccount' => 1999, 'resultDestination' => 3999]), 1);
        self::assertStringContainsString('Årets resultat i 2023/24 skal overføres til konto 3999, som ikke findes i 2024/25.', $text);
        $text = followingOpeningBalanceStopText($this->stop(['resultAccount' => 1999]), 1);
        self::assertStringContainsString('Der er ikke valgt en konto i «Overfør til» ud for Resultat', $text);
        self::assertSame(
            'Primo i regnskabsåret 2024/25 er ikke opdateret automatisk. '
            . 'Regnskabsåret 2024/25 har ikke en gyldig start- og slutmåned. '
            . 'Ret datoerne på regnskabsåret 2024/25 under Indstillinger → Regnskabsår, og tryk Gem/opdatér.',
            followingOpeningBalanceStopText($this->stop(['invalidDates' => [10]]), 1)
        );
        self::assertSame(
            'Primo i regnskabsåret 2024/25 er ikke opdateret automatisk. '
            . 'Der opstod en uventet fejl under opdateringen. Tryk Gem/opdatér på regnskabsåret 2024/25 for at prøve igen, og kontakt Saldi, hvis fejlen bliver ved.',
            followingOpeningBalanceStopText($this->stop(['failed' => true]), 1)
        );
    }
}
