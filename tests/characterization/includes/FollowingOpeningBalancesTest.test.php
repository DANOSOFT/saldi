<?php
// 20261112 CL/MMR SST-1005 Pins how later fiscal years follow an earlier year: a zero balance needs no destination and a blocked transfer is reported.
//                 Postings in an earlier year are carried forward once a session in a later year opens a page, and only then.
//                 The SQL runs against PostgreSQL temporary tables only.
//                 Gem/opdatér on the year card leaves the later years alone when the transfer into the saved year is blocked (Codex round 1).
//                 Invalid dates stop before anything is written, and a failed rebuild is rolled back and stays visible until Gem/opdatér (Codex round 2).
//                 CodeRabbit on PR #1109: JSON-encoded alert, reversed ranges, «Sæt aktivt» on year 1 / after a deleted year, the ten-minute fingerprint, and the card keeping a blocked year's opening balances.
//                 A late commit during the very first check is found by the next fingerprint, proven with two real connections (Codex round 5).
//                 Overlapping first checks keep the first baseline, and a first check that cannot get the lock waits for the next page (Codex round 6).
//                 An older rebuild that resumes after a newer one calculates again instead of writing stale balances (Codex round 7).
//                 A balanced change upstream is noticed, and a retry that becomes blocked leaves the target as it was (Codex round 8).
//                 The year card's snapshot locks its year until the card commits.
// 20261113 CL/MMR SST-1005 A change that is undone while a transfer is calculated is noticed, and a year card and a journal posting in the year before both finish (Codex round 9).
//                 A rebuild into a year a journal is posting in never waits for it: it gets the year once the journal commits, or leaves it for the next check (Codex round 10).
//                 A check that read the state earlier cannot erase a year a card left waiting, and taking the waiting year keeps one registered meanwhile (Codex round 11).
//                 A card that leaves a year waiting while the first check starts, before any check, or with an old state creates the row itself (Codex round 12).
//                 A year waiting in an old state is kept when a newer request created the row with a later year first (Codex round 13).
//                 A row another request creates right after this one found it missing does not skip this request's year (Codex round 14).

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
                // Lets a test send one statement without waiting for it, like another request that runs it.
                $async = $GLOBALS['sst1005Async'] ?? null;
                if ($async !== null && preg_match($async, $sql) === 1) {
                    unset($GLOBALS['sst1005Async']);
                    pg_send_query($GLOBALS['connection'], $sql);
                    return true;
                }
                $result = pg_query($GLOBALS['connection'], $sql);
                if ($result === false) {
                    throw new RuntimeException(pg_last_error($GLOBALS['connection']) . " in $sql");
                }
                // Lets a test run something on another connection right after a statement, before the code goes on.
                $after = $GLOBALS['sst1005Hook']['after'] ?? null;
                if ($after !== null && ($after[0] === '/' ? preg_match($after, $sql) === 1 : strpos($sql, $after) !== false)) {
                    $hook = $GLOBALS['sst1005Hook'];
                    if (--$GLOBALS['sst1005Hook']['times'] <= 0) {
                        unset($GLOBALS['sst1005Hook']);
                    }
                    ($hook['run'])();
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

    /** The id watermark from the JSON in 'openingBalanceSync' and the waiting year in 'openingBalancePending', as "lastId|pendingYear". */
    private function syncState(): string
    {
        $state = followingOpeningBalanceStateParts((string) $this->setting('openingBalanceSync'));
        return $state['lastId'] . '|' . (int) $this->setting('openingBalancePending');
    }

    /** Overwrite the watermark and the fingerprint time, keeping the stored fingerprint. */
    private function setSyncState(int $lastId, int $pendingYear, int $fingerprintAt): void
    {
        $state = followingOpeningBalanceStateParts((string) $this->setting('openingBalanceSync'));
        saveFollowingOpeningBalanceState(['lastId' => $lastId, 'fingerprintAt' => $fingerprintAt, 'fingerprint' => $state['fingerprint']]);
        $this->sql("UPDATE settings SET var_value = '$pendingYear' WHERE var_name = 'openingBalancePending'");
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
        self::assertSame("$maxId|0", $this->syncState());
        self::assertNotNull(followingOpeningBalanceStateParts((string) $this->setting('openingBalanceSync'))['fingerprint'], 'The first check stores the fingerprint with the watermark.');
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

        // A year 9 posting whose id the watermark has already passed, as when it commits after a later check.
        $this->postInYear9(2000, 5);
        $maxId = (int) pg_fetch_assoc($this->sql('SELECT max(id) AS id FROM transaktioner'))['id'];
        $this->setSyncState($maxId, 0, time() - 60);
        self::assertNull(checkFollowingOpeningBalances(10));
        self::assertSame(777.0, $this->primo(10, 2000), 'Within ten minutes of the last fingerprint it is not seen yet.');
        $this->setSyncState($maxId, 0, time() - 601);
        self::assertNull(checkFollowingOpeningBalances(10));
        self::assertSame(1125.0, $this->primo(10, 2000), 'The next fingerprint finds it and year 10 follows.');

        // A deleted posting changes the fingerprint too.
        $this->sql("DELETE FROM transaktioner WHERE transdate = '2024-03-01'");
        $this->setSyncState($maxId, 0, time() - 601);
        self::assertNull(checkFollowingOpeningBalances(10));
        self::assertSame(1120.0, $this->primo(10, 2000));
    }

    /**
     * Two real connections need real tables: copy the fixture into a private schema of the same throwaway
     * database, point this connection at it and return a second connection that sees the same tables.
     */
    private function privateSchema(string $schema)
    {
        $this->sql("CREATE SCHEMA $schema");
        foreach (['grupper', 'kontoplan', 'transaktioner', 'settings'] as $table) {
            $this->sql("CREATE TABLE $schema.$table (LIKE pg_temp.$table INCLUDING DEFAULTS)");
            $this->sql("INSERT INTO $schema.$table SELECT * FROM pg_temp.$table");
            // The copied id default points at this session's temporary sequence; both connections need their own.
            $this->sql("CREATE SEQUENCE $schema.{$table}_id_seq");
            $this->sql("SELECT setval('$schema.{$table}_id_seq', (SELECT coalesce(max(id), 0) + 1 FROM $schema.$table), false)");
            $this->sql("ALTER TABLE $schema.$table ALTER COLUMN id SET DEFAULT nextval('$schema.{$table}_id_seq')");
        }
        $this->sql("SET search_path TO $schema, pg_temp");
        $other = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        pg_query($other, "SET search_path TO $schema");
        return $other;
    }

    private function dropPrivateSchema(string $schema): void
    {
        $this->sql('SET search_path TO pg_temp');
        $this->sql("DROP SCHEMA IF EXISTS $schema CASCADE");
    }

    public function testALateCommitDuringTheFirstCheckIsFoundByTheNextFingerprint(): void
    {
        $schema = 'sst1005_first_check_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            updateFollowingOpeningBalances(9);
            self::assertSame(1120.0, $this->primo(10, 2000));
            $maxId = (int) pg_fetch_assoc($this->sql('SELECT max(id) AS id FROM transaktioner'))['id'];

            // Connection B posts in year 9 with the lower ids and holds its transaction open.
            pg_query($other, 'BEGIN');
            pg_query($other, 'INSERT INTO transaktioner (id, kontonr, transdate, debet, kredit) VALUES (' . ($maxId + 1) . ", 2000, '2024-03-01', 5, 0), (" . ($maxId + 2) . ", 1000, '2024-03-01', 0, 5)");
            // This connection commits higher ids in the newest year, then the very first check runs.
            $this->sql('INSERT INTO transaktioner (id, kontonr, transdate, debet, kredit) VALUES (' . ($maxId + 3) . ", 2000, '2025-08-01', 10, 0), (" . ($maxId + 4) . ", 1000, '2025-08-01', 0, 10)");
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertSame(($maxId + 4) . '|0', $this->syncState(), 'The watermark passes the uncommitted ids.');
            pg_query($other, 'COMMIT');

            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertSame(1120.0, $this->primo(10, 2000), 'Nothing new above the watermark, and the fingerprint is not due yet.');
            $this->setSyncState($maxId + 4, 0, time() - 601);
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertSame(1125.0, $this->primo(10, 2000), 'The first fingerprint was taken before the commit, so the next one finds it.');
            $this->setSyncState($maxId + 4, 0, time() - 601);
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertSame(1125.0, $this->primo(10, 2000));
            self::assertSame(($maxId + 4) . '|0', $this->syncState(), 'Found once: nothing is waiting afterwards.');
        } finally {
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testOverlappingFirstChecksKeepTheFirstBaseline(): void
    {
        $schema = 'sst1005_overlap_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            updateFollowingOpeningBalances(9);
            self::assertSame(1120.0, $this->primo(10, 2000));
            $mine = $GLOBALS['connection'];
            // Check B (this connection) has read "no state yet"; before it goes on, check A starts following on the
            // other connection, and an ordinary year 9 posting commits after A has returned.
            $GLOBALS['sst1005Hook'] = ['after' => 'as state', 'times' => 1, 'run' => function () use ($mine, $other): void {
                $GLOBALS['connection'] = $other;
                self::assertNull(checkFollowingOpeningBalances(11));
                self::assertSame(1120.0, $this->primo(10, 2000));
                pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2024-03-01', 5, 0), (1000, '2024-03-01', 0, 5)");
                $GLOBALS['connection'] = $mine;
            }];
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'The overlap really happened.');
            self::assertSame(1125.0, $this->primo(10, 2000), 'B continued from A\'s state, found the posting and rebuilt.');
            for ($scan = 1; $scan <= 2; $scan++) {
                $this->setSyncState((int) explode('|', $this->syncState())[0], 0, time() - 601);
                self::assertNull(checkFollowingOpeningBalances(11));
                self::assertSame(1125.0, $this->primo(10, 2000), "Due scan $scan.");
            }
            self::assertSame(1, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM settings WHERE var_name = 'openingBalanceSync'"))['n']);
        } finally {
            unset($GLOBALS['sst1005Hook']);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testAnOlderRebuildThatResumesLateCalculatesAgain(): void
    {
        $schema = 'sst1005_rebuild_race_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            updateFollowingOpeningBalances(9);
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertSame(1120.0, $this->primo(10, 2000));
            $mine = $GLOBALS['connection'];
            // «Sæt aktivt» on year 10 (A) holds year 10 and has read year 9's postings. Before it writes, a year 9 posting
            // commits and the automatic check on the other connection (B) tries to carry it into year 10. A holds year 10,
            // so B does not wait for it: B leaves year 9 for the next check (Codex round 10).
            $GLOBALS['sst1005Hook'] = ['after' => 'select kontonr, debet, kredit from transaktioner', 'times' => 1, 'run' => function () use ($mine, $other): void {
                $GLOBALS['connection'] = $other;
                pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2024-03-01', 5, 0), (1000, '2024-03-01', 0, 5)");
                self::assertNull(checkFollowingOpeningBalances(11));
                self::assertStringEndsWith('|9', $this->syncState(), 'B left year 9 waiting.');
                $GLOBALS['connection'] = $mine;
            }];
            self::assertNull(syncFollowingOpeningBalances(9, 10));
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'The overlap really happened.');
            self::assertSame(1125.0, $this->primo(10, 2000), 'A found its source year changed and calculated again.');
            for ($scan = 1; $scan <= 2; $scan++) {
                $this->setSyncState((int) explode('|', $this->syncState())[0], 0, time() - 601);
                self::assertNull(checkFollowingOpeningBalances(11));
                self::assertSame(1125.0, $this->primo(10, 2000), "Due scan $scan.");
            }
        } finally {
            unset($GLOBALS['sst1005Hook']);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testABalancedChangeUpstreamMakesAnOlderRebuildCalculateAgain(): void
    {
        // Year 8 carries 1000 and -1000 into year 9; year 10 then has 1120 on account 2000.
        $this->sql('UPDATE kontoplan SET primo = 0 WHERE regnskabsaar = 8');
        $this->sql('UPDATE kontoplan SET primo = 1000 WHERE regnskabsaar = 8 AND kontonr = 2000');
        $this->sql('UPDATE kontoplan SET primo = -1000 WHERE regnskabsaar = 8 AND kontonr = 3000');
        $schema = 'sst1005_upstream_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            updateFollowingOpeningBalances(8);
            self::assertSame([1000.0, -1000.0, 1120.0], [$this->primo(9, 2000), $this->primo(9, 3000), $this->primo(10, 2000)]);
            self::assertNull(checkFollowingOpeningBalances(11));
            $mine = $GLOBALS['connection'];
            // «Sæt aktivt» on year 10 (A) holds year 10 and has read year 9's accounts. Then a year 8 posting commits and the
            // automatic check (B) carries it into year 9 (1005/-1005: the totals stay the same); year 10 is A's, so B leaves
            // year 9 waiting there (Codex round 10).
            $GLOBALS['sst1005Hook'] = ['after' => "/regnskabsaar = '9' and kontotype in \\('S', 'D', 'X'\\) order by kontonr, id$/", 'times' => 1, 'run' => function () use ($mine, $other): void {
                $GLOBALS['connection'] = $other;
                pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2023-03-01', 5, 0), (1000, '2023-03-01', 0, 5)");
                self::assertNull(checkFollowingOpeningBalances(11));
                self::assertSame([1005.0, -1005.0, 1120.0], [$this->primo(9, 2000), $this->primo(9, 3000), $this->primo(10, 2000)], 'B carried it into year 9.');
                self::assertStringEndsWith('|9', $this->syncState(), 'B left year 9 waiting.');
                $GLOBALS['connection'] = $mine;
            }];
            self::assertNull(syncFollowingOpeningBalances(9, 10));
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'The overlap really happened.');
            self::assertSame(1125.0, $this->primo(10, 2000), 'A saw year 9 change account by account and calculated again.');
            for ($scan = 1; $scan <= 2; $scan++) {
                $this->setSyncState((int) explode('|', $this->syncState())[0], 0, time() - 601);
                self::assertNull(checkFollowingOpeningBalances(11));
                self::assertSame(1125.0, $this->primo(10, 2000), "Due scan $scan.");
            }
        } finally {
            unset($GLOBALS['sst1005Hook']);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testABlockThatAppearsOnARetryLeavesTheTargetAsItWas(): void
    {
        // Account 2500 has no balance in year 9 and is missing in year 10, so the first calculation may go ahead.
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        $schema = 'sst1005_late_block_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $year10 = $this->yearState(10);
            $year11 = $this->yearState(11);
            self::assertSame(888.0, (float) $year10['accounts'][1]['saldo']);
            // After the first target row is written, a posting gives 2500 a balance in year 9.
            $GLOBALS['sst1005Hook'] = ['after' => "update kontoplan set primo = '", 'times' => 1, 'run' => function () use ($other): void {
                pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2500, '2024-03-01', 5, 0), (1000, '2024-03-01', 0, 5)");
            }];
            self::assertSame($this->stop(['accounts' => [2500]]), syncFollowingOpeningBalances(9, 10));
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'The posting came between the attempts.');
            self::assertSame($year10, $this->yearState(10), 'The first attempt\'s writes are undone: primo, saldo and summary as before.');
            self::assertSame($year11, $this->yearState(11));

            // The same through the year card, inside the card's own transaction.
            $this->sql("DELETE FROM transaktioner WHERE kontonr = 2500 OR transdate = '2024-03-01'");
            $before = followingOpeningBalancePrimo(10);
            $GLOBALS['sst1005Hook'] = ['after' => "update kontoplan set primo = '", 'times' => 1, 'run' => function () use ($other): void {
                pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2500, '2024-03-01', 5, 0), (1000, '2024-03-01', 0, 5)");
            }];
            $this->sql('BEGIN');
            $this->sql("UPDATE grupper SET beskrivelse = 'saved by the card' WHERE art = 'RA' AND kodenr = 10");
            self::assertSame($this->stop(['accounts' => [2500]]), updateOpeningBalancesAfterYearSave(10, $before));
            $this->sql('COMMIT');
            self::assertSame($year10['accounts'], $this->yearState(10)['accounts']);
            self::assertSame($year11, $this->yearState(11));
            self::assertSame('saved by the card', pg_fetch_assoc($this->sql("SELECT beskrivelse FROM grupper WHERE art = 'RA' AND kodenr = 10"))['beskrivelse']);
        } finally {
            unset($GLOBALS['sst1005Hook']);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testAChangeThatIsUndoneWhileTheTransferIsCalculatedIsNoticed(): void
    {
        // Year 8 carries 1000 and -1000 into year 9; year 10 then has 1120 on account 2000.
        $this->sql('UPDATE kontoplan SET primo = 0 WHERE regnskabsaar = 8');
        $this->sql('UPDATE kontoplan SET primo = 1000 WHERE regnskabsaar = 8 AND kontonr = 2000');
        $this->sql('UPDATE kontoplan SET primo = -1000 WHERE regnskabsaar = 8 AND kontonr = 3000');
        $schema = 'sst1005_undone_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            updateFollowingOpeningBalances(8);
            self::assertSame([1000.0, -1000.0, 1120.0], [$this->primo(9, 2000), $this->primo(9, 3000), $this->primo(10, 2000)]);
            self::assertNull(checkFollowingOpeningBalances(11));
            $mine = $GLOBALS['connection'];
            // B on the other connection: a year 8 posting that the automatic check carries into year 9 and commits. A holds
            // year 10, so B leaves year 9 waiting there instead of waiting for A (Codex round 10).
            $post = function (string $rows, array $expected) use ($mine, $other): void {
                $GLOBALS['connection'] = $other;
                pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES $rows");
                self::assertNull(checkFollowingOpeningBalances(11));
                self::assertSame($expected, [$this->primo(9, 2000), $this->primo(10, 2000)]);
                $GLOBALS['connection'] = $mine;
            };
            // «Sæt aktivt» on year 10 (A) has read year 9's postings. B adds 5 in year 8 before A reads year 9's
            // accounts, and reverses it right after, before A calculates from what it read (Codex round 9).
            $GLOBALS['sst1005Hook'] = ['after' => 'select kontonr, debet, kredit from transaktioner', 'times' => 1, 'run' => function () use ($post): void {
                $post("(2000, '2023-03-01', 5, 0), (1000, '2023-03-01', 0, 5)", [1005.0, 1120.0]);
                $GLOBALS['sst1005Hook'] = ['after' => "/regnskabsaar = '9' and kontotype in \\('S', 'D', 'X'\\) order by kontonr, id$/", 'times' => 1, 'run' => function () use ($post): void {
                    $post("(2000, '2023-03-02', 0, 5), (1000, '2023-03-02', 5, 0)", [1000.0, 1120.0]);
                }];
            }];
            self::assertNull(syncFollowingOpeningBalances(9, 10));
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'Both changes came while A was calculating.');
            self::assertSame(1120.0, $this->primo(10, 2000), 'A read year 9 as it was in between, noticed it after writing and calculated again.');
            for ($scan = 1; $scan <= 2; $scan++) {
                $this->setSyncState((int) explode('|', $this->syncState())[0], 0, time() - 601);
                self::assertNull(checkFollowingOpeningBalances(11));
                self::assertSame(1120.0, $this->primo(10, 2000), "Due scan $scan.");
            }
        } finally {
            unset($GLOBALS['sst1005Hook']);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testACardSavedWithItsChoicesUnchangedDoesNotWaitForAJournalInTheYearBefore(): void
    {
        // Codex round 9: 2500 has no balance in year 9 and is missing in year 10, so the journal carries past it.
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        $schema = 'sst1005_card_journal_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $mine = $GLOBALS['connection'];
            self::assertNull(checkFollowingOpeningBalances(11));
            // The journal (other connection) holds 2100 of year 9, like finans/bogfor.php before it carries year 9 forward.
            pg_query($other, 'BEGIN');
            pg_query($other, 'UPDATE kontoplan SET saldo = saldo + 1 WHERE regnskabsaar = 9 AND kontonr = 2100');
            pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2100, '2024-03-01', 1, 0), (1000, '2024-03-01', 0, 1)");
            // The card on year 10 (this connection) is saved with 2100 still carried to 2000. A wait would fail here.
            $this->sql("SET lock_timeout = '3s'");
            $this->sql('BEGIN');
            followingOpeningBalanceSaveLastYearChoices(10, [1 => 2100], [1 => 2000], 1);
            $before = followingOpeningBalancePrimo(10, true);
            $this->sql("UPDATE kontoplan SET primo = '0' WHERE regnskabsaar = '10'");
            // The card's own «Overfør til» statement (systemdata/regnskabskort.php).
            $this->sql("UPDATE kontoplan SET overfor_til = '2000' WHERE kontonr = '2100' AND (regnskabsaar = '10' OR (regnskabsaar = 10-1 AND coalesce(overfor_til, 0) <> '2000'))");
            // Now the journal carries year 9 forward and reaches year 10, which the card holds. It does not wait: it
            // leaves year 9 for the next check and holds nothing of year 10.
            $GLOBALS['connection'] = $other;
            $stop = 'unset';
            self::assertSame([], updateFollowingOpeningBalances(9, $stop, $deferred));
            $GLOBALS['connection'] = $mine;
            self::assertNull($stop);
            self::assertSame(9, $deferred);
            self::assertNull(updateOpeningBalancesAfterYearSave(10, $before));
            $this->sql('COMMIT');
            pg_query($other, 'COMMIT');
            self::assertSame([1120.0, -1120.0], [$this->primo(10, 2000), $this->primo(10, 3000)], 'The card carried year 9 as it was committed.');
            self::assertStringEndsWith('|9', $this->syncState(), 'The journal left year 9 waiting.');
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertSame([1121.0, -1121.0], [$this->primo(10, 2000), $this->primo(10, 3000)], 'Both finished: the next check carried the journal\'s posting to year 10.');
        } finally {
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            while (pg_get_result($other)) {
            }
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /**
     * Codex round 10: a journal posting in year 10 holds 2000 of year 10 (its first line) while a rebuild from year 9
     * wants to write year 10; the journal then needs 1000 of year 10 (its second line) and carries year 10 forward.
     * A rebuild that held 1000 while it waited for 2000 deadlocked here. Every account of year 10 exists.
     */
    public function testARebuildIntoAYearAJournalIsPostingInLeavesItForTheNextCheck(): void
    {
        $schema = 'sst1005_journal_target_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $mine = $GLOBALS['connection'];
            self::assertNull(checkFollowingOpeningBalances(11));
            pg_query($other, "SET lock_timeout = '2s'");
            pg_query($other, 'BEGIN');
            pg_query($other, 'UPDATE kontoplan SET saldo = saldo + 1 WHERE regnskabsaar = 10 AND kontonr = 2000');
            pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2024-08-01', 1, 0), (1000, '2024-08-01', 0, 1)");
            // The rebuild from year 9 (this connection) cannot get all of year 10 while the journal holds 2000.
            $this->sql("SET lock_timeout = '5s'");
            $this->sql('BEGIN');
            $stop = 'unset';
            self::assertSame([], updateFollowingOpeningBalances(9, $stop, $deferred));
            self::assertNull($stop);
            self::assertSame(9, $deferred);
            // It holds nothing of year 10, so the journal's next account is free (a wait would end in the 2 s lock_timeout).
            self::assertNotFalse(pg_query($other, 'UPDATE kontoplan SET saldo = saldo - 1 WHERE regnskabsaar = 10 AND kontonr = 1000'), 'The journal gets 1000 of year 10.');
            $GLOBALS['connection'] = $other;
            $journalStop = 'unset';
            self::assertSame([11], updateFollowingOpeningBalances(10, $journalStop), 'The journal carries year 10 forward.');
            $GLOBALS['connection'] = $mine;
            self::assertNull($journalStop);
            self::assertSame(PGSQL_COMMAND_OK, pg_result_status(pg_query($other, 'COMMIT')));
            $this->sql('COMMIT');
            self::assertSame(2, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM transaktioner WHERE transdate = '2024-08-01'"))['n'], 'Both posting rows are kept.');
            self::assertStringEndsWith('|9', $this->syncState(), 'Year 9 waits for the next check.');
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertSame([1120.0, -1120.0], [$this->primo(10, 2000), $this->primo(10, 3000)], 'The next check carried year 9.');
            // Year 10 already had 50 on 2000 (2024-07-01); the journal added 1.
            self::assertSame([1171.0, -1171.0], [$this->primo(11, 2000), $this->primo(11, 3000)], 'And year 10 with the journal\'s posting.');
        } finally {
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            while (pg_get_result($other)) {
            }
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testARebuildIntoAYearAJournalIsPostingInGoesOnOnceTheJournalCommits(): void
    {
        $schema = 'sst1005_journal_commits_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $mine = $GLOBALS['connection'];
            self::assertNull(checkFollowingOpeningBalances(11));
            pg_query($other, "SET lock_timeout = '2s'");
            pg_query($other, 'BEGIN');
            pg_query($other, 'UPDATE kontoplan SET saldo = saldo + 1 WHERE regnskabsaar = 10 AND kontonr = 2000');
            pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2024-08-01', 1, 0), (1000, '2024-08-01', 0, 1)");
            // After the rebuild's first try has let go of year 10 the journal takes its next account and commits.
            $GLOBALS['sst1005Hook'] = ['after' => 'rollback to savepoint following_opening_balance_lock', 'times' => 1, 'run' => function () use ($other): void {
                self::assertNotFalse(pg_query($other, 'UPDATE kontoplan SET saldo = saldo - 1 WHERE regnskabsaar = 10 AND kontonr = 1000'));
                pg_query($other, 'COMMIT');
            }];
            $this->sql("SET lock_timeout = '5s'");
            $this->sql('BEGIN');
            $stop = 'unset';
            self::assertSame([10, 11], updateFollowingOpeningBalances(9, $stop, $deferred), 'The next try gets the year.');
            $this->sql('COMMIT');
            self::assertNull($stop);
            self::assertSame(0, $deferred);
            self::assertSame([1120.0, 1171.0], [$this->primo(10, 2000), $this->primo(11, 2000)], 'Year 11 includes the journal\'s posting in year 10.');
            self::assertStringEndsWith('|0', $this->syncState());
        } finally {
            unset($GLOBALS['sst1005Hook']);
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            while (pg_get_result($other)) {
            }
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testACardThatChangesAChoiceWaitsForAJournalInTheYearBeforeWithoutHoldingItsOwnYear(): void
    {
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        $schema = 'sst1005_card_waits_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $mine = $GLOBALS['connection'];
            // The journal (this connection) holds 2100 of year 9 and has posted 1 on it.
            $this->sql("SET lock_timeout = '3s'");
            $this->sql('BEGIN');
            $this->sql('UPDATE kontoplan SET saldo = saldo + 1 WHERE regnskabsaar = 9 AND kontonr = 2100');
            $this->sql("INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2100, '2024-03-01', 1, 0), (1000, '2024-03-01', 0, 1)");
            // The card on year 10 (other connection) now carries 2100 to 3000, so it writes 2100 of year 9 first: it waits.
            pg_query($other, 'BEGIN');
            $GLOBALS['connection'] = $other;
            $GLOBALS['sst1005Async'] = '/^update kontoplan set overfor_til/';
            followingOpeningBalanceSaveLastYearChoices(10, [1 => 2100], [1 => 3000], 1);
            $GLOBALS['connection'] = $mine;
            usleep(300000);
            self::assertTrue(pg_connection_busy($other), 'The card waits for the journal.');
            // The card holds nothing of year 10 yet, so the journal carries year 9 into years 10 and 11 and commits.
            $stop = 'unset';
            self::assertSame([10, 11], updateFollowingOpeningBalances(9, $stop));
            self::assertNull($stop);
            $this->sql('COMMIT');
            self::assertSame(1121.0, $this->primo(10, 2000));
            // The card goes on and saves.
            $result = pg_get_result($other);
            self::assertSame(PGSQL_COMMAND_OK, pg_result_status($result));
            self::assertSame(1, pg_affected_rows($result));
            while (pg_get_result($other)) {
            }
            $GLOBALS['connection'] = $other;
            $before = followingOpeningBalancePrimo(10, true);
            db_modify("UPDATE kontoplan SET primo = '0' WHERE regnskabsaar = '10'");
            db_modify("UPDATE kontoplan SET overfor_til = '3000' WHERE kontonr = '2100' AND (regnskabsaar = '10' OR (regnskabsaar = 10-1 AND coalesce(overfor_til, 0) <> '3000'))");
            self::assertNull(updateOpeningBalancesAfterYearSave(10, $before));
            pg_query($other, 'COMMIT');
            $GLOBALS['connection'] = $mine;
            $choices = pg_fetch_all($this->sql('SELECT regnskabsaar, overfor_til FROM kontoplan WHERE kontonr = 2100 AND regnskabsaar IN (9, 10) ORDER BY regnskabsaar'));
            self::assertSame([['regnskabsaar' => '9', 'overfor_til' => '3000'], ['regnskabsaar' => '10', 'overfor_til' => '3000']], $choices);
            self::assertSame([1100.0, -1100.0], [$this->primo(10, 2000), $this->primo(10, 3000)], 'The card carried year 9 again with the new choice: 21 on 2100 now lands on 3000.');
        } finally {
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            while (pg_get_result($other)) {
            }
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testTheCardWritesOnlyChangedChoicesOnTheYearBeforeAndOnlyForAnExistingYear(): void
    {
        $version = function (int $year, int $account): string {
            return pg_fetch_assoc($this->sql("SELECT xmin::text AS v FROM kontoplan WHERE regnskabsaar = $year AND kontonr = $account"))['v'];
        };
        $unchanged = $version(9, 2100);
        // 2100 keeps 2000, the Sideskift account 1999 moves from 3000 to 2500, and 2000 has no choice.
        followingOpeningBalanceSaveLastYearChoices(10, [0 => 2100, 1 => 1999, 2 => 2000], [0 => 2000, 1 => 2500, 2 => 0], 2);
        self::assertSame($unchanged, $version(9, 2100), 'An unchanged choice is not written on the year before.');
        $rows = pg_fetch_all($this->sql('SELECT kontonr, overfor_til FROM kontoplan WHERE regnskabsaar = 9 AND kontonr IN (1999, 2000, 2100) ORDER BY kontonr'));
        self::assertSame([['kontonr' => '1999', 'overfor_til' => '2500'], ['kontonr' => '2000', 'overfor_til' => '0'], ['kontonr' => '2100', 'overfor_til' => '2000']], $rows);
        self::assertSame('3000', pg_fetch_assoc($this->sql('SELECT overfor_til FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 1999'))['overfor_til'], 'The card writes its own year itself.');

        $this->sql("UPDATE grupper SET box10 = 'on' WHERE art = 'RA' AND kodenr = 9");
        followingOpeningBalanceSaveLastYearChoices(10, [0 => 1999], [0 => 2000], 0);
        self::assertSame('2500', pg_fetch_assoc($this->sql('SELECT overfor_til FROM kontoplan WHERE regnskabsaar = 9 AND kontonr = 1999'))['overfor_til'], 'Nothing after a deleted year.');
        $this->sql("INSERT INTO grupper (kodenr, art, box1, box2, box3, box4, box5, box10) VALUES (12, 'RA', '7', '2026', '6', '2027', 'on', '')");
        followingOpeningBalanceSaveLastYearChoices(12, [0 => 1999], [0 => 2000], 0);
        self::assertSame('3000', pg_fetch_assoc($this->sql('SELECT overfor_til FROM kontoplan WHERE regnskabsaar = 11 AND kontonr = 1999'))['overfor_til'], 'A new year is copied first; the card maps the year before after that, as before.');
    }

    public function testTheCardsSnapshotLocksTheYearUntilTheCardCommits(): void
    {
        $schema = 'sst1005_card_lock_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $this->sql('BEGIN');
            $before = followingOpeningBalancePrimo(10, true);
            self::assertCount(8, $before);
            // A rebuild on another connection that wants to write year 10 has to wait for the card.
            pg_send_query($other, 'UPDATE kontoplan SET primo = 1 WHERE regnskabsaar = 10 AND kontonr = 2000');
            usleep(300000);
            self::assertTrue(pg_connection_busy($other), 'The other write waits for the card.');
            $this->sql('COMMIT');
            pg_get_result($other);
            self::assertFalse(pg_connection_busy($other));
            self::assertSame(1.0, $this->primo(10, 2000), 'It goes on once the card has committed.');
        } finally {
            while (pg_get_result($other)) {
            }
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testTheCardsSnapshotLetsAJournalInItsYearFinishFirst(): void
    {
        $schema = 'sst1005_card_try_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            // A journal posting in year 10 holds 2000 and will need 1000 next.
            pg_query($other, "SET lock_timeout = '2s'");
            pg_query($other, 'BEGIN');
            pg_query($other, 'UPDATE kontoplan SET saldo = saldo + 1 WHERE regnskabsaar = 10 AND kontonr = 2000');
            $GLOBALS['sst1005Hook'] = ['after' => 'rollback to savepoint following_opening_balance_lock', 'times' => 1, 'run' => function () use ($other): void {
                self::assertNotFalse(pg_query($other, 'UPDATE kontoplan SET saldo = saldo - 1 WHERE regnskabsaar = 10 AND kontonr = 1000'), 'The card holds nothing of year 10 between its tries.');
                pg_query($other, 'COMMIT');
            }];
            $this->sql('BEGIN');
            $before = followingOpeningBalancePrimo(10, true);
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'The card tried while the journal held 2000.');
            self::assertCount(8, $before);
            self::assertSame(1, (int) pg_fetch_assoc($this->sql('SELECT count(*) AS n FROM kontoplan WHERE regnskabsaar = 10 AND saldo = 889'))['n'], 'The card read the journal\'s committed rows.');
            // Now the card holds year 10.
            pg_send_query($other, 'UPDATE kontoplan SET saldo = saldo + 1 WHERE regnskabsaar = 10 AND kontonr = 2000');
            usleep(300000);
            self::assertTrue(pg_connection_busy($other), 'The card holds year 10 until it commits.');
            $this->sql('COMMIT');
            pg_get_result($other);
        } finally {
            unset($GLOBALS['sst1005Hook']);
            while (pg_get_result($other)) {
            }
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /**
     * Codex round 11: a check that read the state before a card left a year waiting must not erase that year when it
     * saves its watermark and fingerprint. The card's change is a primo edit, which no fingerprint of postings finds again.
     */
    public function testACheckThatReadTheStateEarlierDoesNotEraseAYearACardLeftWaiting(): void
    {
        $this->sql('UPDATE kontoplan SET primo = 0 WHERE regnskabsaar = 8');
        $this->sql('UPDATE kontoplan SET primo = 1000 WHERE regnskabsaar = 8 AND kontonr = 2000');
        $this->sql('UPDATE kontoplan SET primo = -1000 WHERE regnskabsaar = 8 AND kontonr = 3000');
        $schema = 'sst1005_lost_pending_' . getmypid();
        $other = $this->privateSchema($schema);
        $holder = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        pg_query($holder, "SET search_path TO $schema");
        try {
            updateFollowingOpeningBalances(8);
            self::assertSame([1000.0, 1120.0], [$this->primo(9, 2000), $this->primo(10, 2000)]);
            self::assertNull(checkFollowingOpeningBalances(11));
            $this->setSyncState((int) explode('|', $this->syncState())[0], 0, time() - 601);
            $mine = $GLOBALS['connection'];
            // H holds an account of year 9 without changing anything.
            pg_query($holder, 'BEGIN');
            pg_query($holder, 'UPDATE kontoplan SET saldo = saldo WHERE regnskabsaar = 9 AND kontonr = 2000');
            // Check B (this connection) has read the state, nothing waiting. Then card A (other connection) saves year 8
            // with a balanced primo edit; year 9 is busy, so A leaves year 8 waiting and commits.
            $GLOBALS['sst1005Hook'] = ['after' => 'as pending', 'times' => 1, 'run' => function () use ($mine, $other): void {
                $GLOBALS['connection'] = $other;
                pg_query($other, 'BEGIN');
                $before = followingOpeningBalancePrimo(8, true);
                pg_query($other, 'UPDATE kontoplan SET primo = primo + 5 WHERE regnskabsaar = 8 AND kontonr = 2000');
                pg_query($other, 'UPDATE kontoplan SET primo = primo - 5 WHERE regnskabsaar = 8 AND kontonr = 3000');
                self::assertNull(updateOpeningBalancesAfterYearSave(8, $before));
                pg_query($other, 'COMMIT');
                $GLOBALS['connection'] = $mine;
                self::assertStringEndsWith('|8', $this->syncState(), 'A left year 8 waiting.');
            }];
            // B goes on: its fingerprint is due, finds no posting change and saves the watermark and fingerprint.
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'A saved while B was checking.');
            self::assertSame(1000.0, $this->primo(9, 2000));
            self::assertStringEndsWith('|8', $this->syncState(), 'B did not erase the year A left waiting.');
            pg_query($holder, 'COMMIT');
            // The next page carries the card's edit on, with no posting and no manual sync.
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertSame([1005.0, 1125.0], [$this->primo(9, 2000), $this->primo(10, 2000)]);
            self::assertStringEndsWith('|0', $this->syncState());
            $this->setSyncState((int) explode('|', $this->syncState())[0], 0, time() - 601);
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertSame([1005.0, 1125.0], [$this->primo(9, 2000), $this->primo(10, 2000)], 'A due scan keeps it.');
        } finally {
            unset($GLOBALS['sst1005Hook']);
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            pg_close($holder);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /**
     * Codex round 12: the first check is still starting (it holds the start lock and has read its watermark) when a card
     * leaves a year waiting. There is no 'openingBalancePending' row yet, so the card creates it; the first check keeps it.
     */
    public function testACardThatLeavesAYearWaitingWhileTheFirstCheckStartsKeepsIt(): void
    {
        $this->sql('UPDATE kontoplan SET primo = 0 WHERE regnskabsaar = 8');
        $this->sql('UPDATE kontoplan SET primo = 1000 WHERE regnskabsaar = 8 AND kontonr = 2000');
        $this->sql('UPDATE kontoplan SET primo = -1000 WHERE regnskabsaar = 8 AND kontonr = 3000');
        $schema = 'sst1005_init_pending_' . getmypid();
        $other = $this->privateSchema($schema);
        $holder = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        pg_query($holder, "SET search_path TO $schema");
        try {
            updateFollowingOpeningBalances(8);
            self::assertSame([1000.0, 1120.0], [$this->primo(9, 2000), $this->primo(10, 2000)]);
            self::assertNull($this->setting('openingBalanceSync'));
            self::assertNull($this->setting('openingBalancePending'));
            $mine = $GLOBALS['connection'];
            pg_query($holder, 'BEGIN');
            pg_query($holder, 'UPDATE kontoplan SET saldo = saldo WHERE regnskabsaar = 9 AND kontonr = 2000');
            // B (this connection) starts following: it has the start lock and has read the watermark when card A saves.
            $GLOBALS['sst1005Hook'] = ['after' => 'select max(id) as max_id from transaktioner', 'times' => 1, 'run' => function () use ($mine, $other): void {
                $GLOBALS['connection'] = $other;
                self::assertNull(checkFollowingOpeningBalances(8), 'The card\'s own page check finds the start busy.');
                pg_query($other, 'BEGIN');
                $before = followingOpeningBalancePrimo(8, true);
                pg_query($other, 'UPDATE kontoplan SET primo = primo + 5 WHERE regnskabsaar = 8 AND kontonr = 2000');
                pg_query($other, 'UPDATE kontoplan SET primo = primo - 5 WHERE regnskabsaar = 8 AND kontonr = 3000');
                self::assertNull(updateOpeningBalancesAfterYearSave(8, $before));
                pg_query($other, 'COMMIT');
                $GLOBALS['connection'] = $mine;
                self::assertSame('8', $this->setting('openingBalancePending'), 'The card created the row with its year.');
            }];
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS);
            self::assertSame(1, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM settings WHERE var_name = 'openingBalancePending'"))['n'], 'One row.');
            self::assertStringEndsWith('|8', $this->syncState(), 'The first check kept the card\'s year.');
            pg_query($holder, 'COMMIT');
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertSame([1005.0, 1125.0], [$this->primo(9, 2000), $this->primo(10, 2000)], 'The next page carries the card\'s edit on.');
            self::assertSame([1005.0, -1005.0], [$this->primo(8, 2000), $this->primo(8, 3000)], 'The card\'s saved primos are kept.');
        } finally {
            unset($GLOBALS['sst1005Hook']);
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            pg_close($holder);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testACardThatLeavesAYearWaitingBeforeAnyCheckOrWithAnOldStateCreatesTheRow(): void
    {
        $maxId = (int) pg_fetch_assoc($this->sql('SELECT max(id) AS m FROM transaktioner'))['m'];
        foreach (['none' => null, 'old' => "$maxId|0"] as $case => $state) {
            $this->sql("DELETE FROM settings");
            if ($state !== null) {
                $this->sql("INSERT INTO settings (var_name, var_grp, var_value, user_id) VALUES ('openingBalanceSync', 'system', '$state', 0)");
            }
            $this->sql('BEGIN');
            followingOpeningBalanceMarkPending(9);
            $this->sql('COMMIT');
            self::assertSame('9', $this->setting('openingBalancePending'), "Created ($case).");
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertStringEndsWith('|0', $this->syncState(), "The next check rebuilt from year 9 ($case).");
            self::assertSame(1120.0, $this->primo(10, 2000));
            self::assertSame(1, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM settings WHERE var_name = 'openingBalancePending'"))['n']);
        }
    }

    /** Years 8-10 consistent (year 9 2000 = 1000, year 10 2000 = 1120), then 5 posted in year 8 that is not carried yet. */
    private function yearEightWaitsWithFivePosted(): void
    {
        $this->sql('UPDATE kontoplan SET primo = 0 WHERE regnskabsaar = 8');
        $this->sql('UPDATE kontoplan SET primo = 1000 WHERE regnskabsaar = 8 AND kontonr = 2000');
        $this->sql('UPDATE kontoplan SET primo = -1000 WHERE regnskabsaar = 8 AND kontonr = 3000');
        updateFollowingOpeningBalances(8);
        self::assertSame([1000.0, 1120.0], [$this->primo(9, 2000), $this->primo(10, 2000)]);
        $this->sql("INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2023-03-01', 5, 0), (3000, '2023-03-01', 0, 5)");
    }

    /** The later years once year 8 (+5 on 2000 and -5 on 3000) and the journal's +1 in year 9 are both carried. */
    private function assertYearEightAndTheJournalCarried(string $message): void
    {
        self::assertSame(
            [1005.0, -1005.0, 1126.0, -1126.0],
            [$this->primo(9, 2000), $this->primo(9, 3000), $this->primo(10, 2000), $this->primo(10, 3000)],
            $message
        );
    }

    /** Codex round 13: an old JSON state with a waiting year and a fingerprint, and a newer request that created the row first. */
    public function testAYearWaitingInAnOldJsonStateIsKeptWhenTheRowAlreadyHasALaterYear(): void
    {
        $this->yearEightWaitsWithFivePosted();
        self::assertNull(checkFollowingOpeningBalances(11));
        $old = json_decode((string) $this->setting('openingBalanceSync'), true);
        $old['pendingYear'] = 8;
        $this->sql("UPDATE settings SET var_value = '" . pg_escape_string($this->connection, json_encode($old)) . "' WHERE var_name = 'openingBalanceSync'");
        // A journal in year 9 posts and leaves year 9 waiting.
        $this->sql('BEGIN');
        $this->sql("INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2024-03-03', 1, 0), (1000, '2024-03-03', 0, 1)");
        followingOpeningBalanceMarkPending(9);
        $this->sql('COMMIT');
        self::assertSame('9', $this->setting('openingBalancePending'));
        self::assertNull(checkFollowingOpeningBalances(11));
        $this->assertYearEightAndTheJournalCarried('The old waiting year 8 was not replaced by 9.');
        self::assertArrayNotHasKey('pendingYear', json_decode((string) $this->setting('openingBalanceSync'), true), 'The state is saved without it.');
        self::assertStringEndsWith('|0', $this->syncState());
    }

    /** Codex round 13: the old "lastId|8" state while a journal defers year 9 and creates the row during the first check. */
    public function testAYearWaitingInAnOldStateIsKeptWhenAJournalCreatesTheRowDuringTheFirstCheck(): void
    {
        $this->yearEightWaitsWithFivePosted();
        $maxId = (int) pg_fetch_assoc($this->sql('SELECT max(id) AS m FROM transaktioner'))['m'];
        $this->sql("INSERT INTO settings (var_name, var_grp, var_value, user_id) VALUES ('openingBalanceSync', 'system', '$maxId|8', 0)");
        $schema = 'sst1005_old_state_' . getmypid();
        $other = $this->privateSchema($schema);
        $holder = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        pg_query($holder, "SET search_path TO $schema");
        try {
            $mine = $GLOBALS['connection'];
            pg_query($holder, 'BEGIN');
            pg_query($holder, 'UPDATE kontoplan SET saldo = saldo WHERE regnskabsaar = 10 AND kontonr = 2000');
            // B (this connection) migrates the old state; it has read the watermark when journal A in year 9 posts.
            $GLOBALS['sst1005Hook'] = ['after' => 'select max(id) as max_id from transaktioner', 'times' => 1, 'run' => function () use ($mine, $other): void {
                $GLOBALS['connection'] = $other;
                self::assertNull(checkFollowingOpeningBalances(9), 'The journal\'s page check finds the start busy.');
                pg_query($other, 'BEGIN');
                pg_query($other, 'UPDATE kontoplan SET saldo = saldo + 1 WHERE regnskabsaar = 9 AND kontonr = 2000');
                pg_query($other, 'UPDATE kontoplan SET saldo = saldo - 1 WHERE regnskabsaar = 9 AND kontonr = 1000');
                pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2024-03-03', 1, 0), (1000, '2024-03-03', 0, 1)");
                $stop = 'unset';
                self::assertSame([], updateFollowingOpeningBalances(9, $stop, $deferred));
                self::assertSame(9, $deferred);
                pg_query($other, 'COMMIT');
                $GLOBALS['connection'] = $mine;
                self::assertSame('9', $this->setting('openingBalancePending'), 'The journal created the row with 9.');
            }];
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS);
            self::assertSame(1005.0, $this->primo(9, 2000), 'B took year 8 from the old state and carried it into year 9.');
            pg_query($holder, 'COMMIT');
            self::assertNull(checkFollowingOpeningBalances(11));
            $this->assertYearEightAndTheJournalCarried('Year 8 and the journal\'s year 9 both reached year 10.');
            $this->setSyncState((int) explode('|', $this->syncState())[0], 0, time() - 601);
            self::assertNull(checkFollowingOpeningBalances(11));
            $this->assertYearEightAndTheJournalCarried('A due scan keeps it.');
        } finally {
            unset($GLOBALS['sst1005Hook']);
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            pg_close($holder);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /** Codex round 14: the row is created by another request right after this one found it missing. */
    public function testARowCreatedByAnotherRequestRightAfterThisOneLookedDoesNotSkipItsYear(): void
    {
        $schema = 'sst1005_create_between_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $mine = $GLOBALS['connection'];
            self::assertNull($this->setting('openingBalancePending'));
            // A card (this connection) leaves year 8 waiting. Right after its first statement on the row found none (round 13
            // wrote first and looked after; now it looks first), a journal on the other connection leaves year 9 waiting,
            // creates the row and commits.
            $GLOBALS['sst1005Hook'] = ['after' => '/openingBalancePending/', 'times' => 1, 'run' => function () use ($mine, $other): void {
                $GLOBALS['connection'] = $other;
                pg_query($other, 'BEGIN');
                followingOpeningBalanceMarkPending(9);
                pg_query($other, 'COMMIT');
                $GLOBALS['connection'] = $mine;
            }];
            $this->sql('BEGIN');
            followingOpeningBalanceMarkPending(8);
            $this->sql('COMMIT');
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'The journal came in between.');
            self::assertSame('8', $this->setting('openingBalancePending'), 'The card\'s earlier year is registered.');
            self::assertSame(1, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM settings WHERE var_name = 'openingBalancePending'"))['n']);
        } finally {
            unset($GLOBALS['sst1005Hook']);
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testCreatingTheWaitingYearRowWaitsForATransactionThatCreatesIt(): void
    {
        $schema = 'sst1005_create_lock_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $mine = $GLOBALS['connection'];
            pg_query($other, 'BEGIN');
            $GLOBALS['connection'] = $other;
            followingOpeningBalanceMarkPending(9);
            $GLOBALS['connection'] = $mine;
            // The first check would create the row under the same lock: it waits until it can see the transaction's row.
            pg_send_query($mine, "SELECT pg_advisory_lock(hashtext('openingBalancePending'), 1005)");
            usleep(300000);
            self::assertTrue(pg_connection_busy($mine));
            pg_query($other, 'COMMIT');
            pg_get_result($mine);
            while (pg_get_result($mine)) {
            }
            $this->sql("SELECT pg_advisory_unlock(hashtext('openingBalancePending'), 1005)");
            followingOpeningBalanceCreatePending(0);
            self::assertSame('9', $this->setting('openingBalancePending'));
            self::assertSame(1, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM settings WHERE var_name = 'openingBalancePending'"))['n']);
        } finally {
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            while (pg_get_result($mine)) {
            }
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testTakingTheWaitingYearKeepsALowerYearRegisteredMeanwhile(): void
    {
        self::assertNull(checkFollowingOpeningBalances(11));
        followingOpeningBalanceMarkPending(10);
        followingOpeningBalanceMarkPending(11);
        self::assertStringEndsWith('|10', $this->syncState(), 'A later year does not replace an earlier one.');
        followingOpeningBalanceMarkPending(9);
        self::assertStringEndsWith('|9', $this->syncState(), 'An earlier year does.');
        // A clear that only matches the value it read leaves a year registered after that read.
        $this->sql("UPDATE settings SET var_value = '0' WHERE var_name = 'openingBalancePending' AND var_value = '10'");
        self::assertStringEndsWith('|9', $this->syncState());
        self::assertSame(9, followingOpeningBalanceTakePending());
        self::assertStringEndsWith('|0', $this->syncState());
        self::assertSame(0, followingOpeningBalanceTakePending());
    }

    public function testTakingTheWaitingYearWaitsForTheTransactionThatRegisteredIt(): void
    {
        $schema = 'sst1005_take_waits_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            self::assertNull(checkFollowingOpeningBalances(11));
            followingOpeningBalanceMarkPending(9);
            $mine = $GLOBALS['connection'];
            // Another transaction registers year 10 (a later year: the value stays 9, but the row is now its).
            pg_query($other, 'BEGIN');
            $GLOBALS['connection'] = $other;
            followingOpeningBalanceMarkPending(10);
            $GLOBALS['connection'] = $mine;
            pg_send_query($mine, "UPDATE settings SET var_value = '0' WHERE var_name = 'openingBalancePending' AND var_value = '9'");
            usleep(300000);
            self::assertTrue(pg_connection_busy($mine), 'Taking the year waits until the registering transaction commits.');
            pg_query($other, 'COMMIT');
            pg_get_result($mine);
            while (pg_get_result($mine)) {
            }
            self::assertStringEndsWith('|0', $this->syncState());
        } finally {
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testASourceYearThatKeepsChangingIsLeftWaiting(): void
    {
        $schema = 'sst1005_churn_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            self::assertNull(checkFollowingOpeningBalances(11));
            $mine = $GLOBALS['connection'];
            // A new year 9 posting commits after every read of year 9's postings.
            $GLOBALS['sst1005Hook'] = ['after' => 'select kontonr, debet, kredit from transaktioner', 'times' => 5, 'run' => function () use ($other): void {
                pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2024-03-01', 1, 0), (1000, '2024-03-01', 0, 1)");
            }];
            $this->sql('BEGIN');
            $stop = 'unset';
            self::assertSame([10, 11], updateFollowingOpeningBalances(9, $stop), 'No exception inside a caller\'s transaction.');
            $this->sql('COMMIT');
            self::assertNull($stop);
            self::assertStringEndsWith('|9', $this->syncState(), 'Year 9 waits, so the next check rebuilds it.');
            self::assertNull(checkFollowingOpeningBalances(10));
            self::assertSame(1125.0, $this->primo(10, 2000), 'The next check carries all five postings.');
        } finally {
            unset($GLOBALS['sst1005Hook']);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testAFirstCheckThatCannotGetTheLockLeavesItToTheNextPage(): void
    {
        $other = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        try {
            pg_query($other, "SELECT pg_advisory_lock(hashtext('openingBalanceSync'), 1005)");
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertNull($this->setting('openingBalanceSync'), 'No state while another check is starting.');
            pg_query($other, "SELECT pg_advisory_unlock(hashtext('openingBalanceSync'), 1005)");
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertNotNull($this->setting('openingBalanceSync'));
        } finally {
            pg_close($other);
        }
    }

    public function testAStateFromBeforeTheFingerprintKeepsItsWaitingYear(): void
    {
        $this->postInYear9(2000, 5);
        $maxId = (int) pg_fetch_assoc($this->sql('SELECT max(id) AS id FROM transaktioner'))['id'];
        $this->sql("INSERT INTO settings (var_name, var_grp, var_value, user_id) VALUES ('openingBalanceSync', 'system', '$maxId|9', 0)");
        self::assertNull(checkFollowingOpeningBalances(9));
        self::assertSame("$maxId|9", $this->syncState());
        self::assertNotNull(followingOpeningBalanceStateParts((string) $this->setting('openingBalanceSync'))['fingerprint']);
        self::assertNull(checkFollowingOpeningBalances(10));
        self::assertSame(1125.0, $this->primo(10, 2000));
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
