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
//                 A request that finds the row created with the same year holds it until it commits (Codex round 15).
//                 A late commit and a deletion that cancel out in the year's totals are found by the fingerprint per account (Codex round 16).
//                 The first year after a year deleted with a timestamp or 'on' keeps its opening balances and carries them on (Codex round 17).
// 20261120 CL/MMR SST-1005 A waiting year that is deleted before its rebuild does not swallow later work (CodeRabbit on PR #1109).
//                 A rebuild that the production db_modify() ends with exit is rolled back and leaves the 'failed' message (CodeRabbit on PR #1109).
//                 The year card's save keeps that message whether its own primo reset or the carry-forward fails, and a nested caller never disarms its guard (Codex round 22).
//                 A COMMIT the database refuses leaves it too, for the card and for the page check (Codex round 23).
// 20261124 CL/MMR SST-1005 A page that declares its own genberegn() first (debitor/ordreliste.php) gets the year recalculated, and its order is left alone (Saul on PR #1109).
// 20261125 CL/MMR SST-1005 CodeRabbit and Lui's pre-check on PR #1109: a rebuild reads the years' dates under its lock, and MySQL's GET_LOCK result is respected.
//                 A year in an old state reaches its row before the state is saved, and the guard is armed before the waiting year is taken.
//                 A transfer left for the next check keeps the block it did not reach, and a block committed meanwhile is never overwritten.
//                 Two requests creating the block row both finish, and the alert reads the same on a LATIN9 regnskab.
//                 A finally block ends the blocking transaction before it drains the connection that waits for it (CodeRabbit).
// 20261125 CL/MMR SST-1005 Clearing the block never creates its row, and two rebuilds storing the first block make one row, also without a unique index (Codex round 28).
// 20261125 CL/MMR SST-1005 On MySQL the lock for creating a setting is kept until the transaction ends and released once (Codex round 29).
// 20261125 CL/MMR SST-1005 The settings rows exist before «Sæt aktivt», the page check or the year card begins its transaction, and a transaction only tries the MySQL creation lock (Codex round 31).
// 20261125 CL/MMR SST-1005 Inside a transaction the waiting-year and block rows are locked by their id before they are read (Codex round 32).

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
        $GLOBALS['db_encode'] = 'UTF8';
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
                // Lets a test answer a statement PostgreSQL does not know (MySQL's GET_LOCK) with one it does.
                $rewrite = $GLOBALS['sst1005Rewrite'] ?? null;
                if ($rewrite !== null) {
                    $sql = $rewrite($sql);
                }
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
            /**
             * The texts are not seeded here, so findtekst() gives the fallback after the '|', in the database encoding
             * like includes/stdFunc/findTxt.php: ISO-8859-1 on a LATIN9 regnskab.
             */
            function findtekst($textId, $languageId) {
                $parts = explode('|', (string) $textId, 2);
                $text = $parts[1] ?? $parts[0];
                return (($GLOBALS['db_encode'] ?? 'UTF8') !== 'UTF8') ? mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8') : $text;
            }
        }
        if ($this->name() === 'testAnUnexpectedErrorRollsTheCarryForwardBackAndIsReported' && !function_exists('recalculateLedgerYear')) {
            /** Fails after the first target year's primo is written, like an error nobody expected. */
            function recalculateLedgerYear($year, $updateUsage = true) {
                throw new RuntimeException('test: recalculation stopped');
            }
        }
        // debitor/ordreliste.php declares its own genberegn($id) at the top level, so PHP has it before the page
        // includes online.php and the page check runs; includes/genberegn.php is not loaded on that page.
        $pageWithItsOwnGenberegn = $this->name() === 'testThePageCheckOnAPageWithItsOwnGenberegnRecalculatesTheYearAndLeavesTheOrderAlone';
        if ($pageWithItsOwnGenberegn && !function_exists('genberegn')) {
            /** Like the order list's genberegn($id): recalculates the cost price of order $id. */
            function genberegn($id) {
                db_modify("UPDATE ordrer SET kostpris = 0 WHERE id = '" . (int) $id . "'");
            }
        }
        require_once dirname(__DIR__, 3) . '/includes/std_func.php';
        if (!$pageWithItsOwnGenberegn) {
            require_once dirname(__DIR__, 3) . '/includes/genberegn.php';
        }
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

    public function testThePageCheckOnAPageWithItsOwnGenberegnRecalculatesTheYearAndLeavesTheOrderAlone(): void
    {
        // Orders whose ids are the years the rebuild recalculates, as Saul's order id 2 for year 2.
        $this->sql('CREATE TEMP TABLE ordrer (id INTEGER, kostpris NUMERIC)');
        $this->sql('INSERT INTO ordrer (id, kostpris) VALUES (10, 999), (11, 999)');
        // A page opened with ?regnskabsaar= must not start includes/genberegn.php's own recalculation either.
        $_GET['regnskabsaar'] = '10';
        self::assertNull(checkFollowingOpeningBalances(11));
        $this->postInYear9(2000, 5);
        self::assertNull(checkFollowingOpeningBalances(9));

        // A session in year 10 opens the page: years 10 and 11 follow year 9 and are recalculated.
        self::assertNull(checkFollowingOpeningBalances(10));
        unset($_GET['regnskabsaar']);
        self::assertSame(1125.0, $this->primo(10, 2000));
        self::assertSame(1175.0, $this->primo(11, 2000));
        $saldo = static function (array $state, int $account): float {
            foreach ($state['accounts'] as $row) {
                if ((int) $row['kontonr'] === $account) return (float) $row['saldo'];
            }
            return NAN;
        };
        $year10 = $this->yearState(10);
        $year11 = $this->yearState(11);
        // Year 10 (2024/25) has the 50 posted on 2024-07-01 on top of its new primo; year 11 has no postings.
        self::assertSame(1175.0, $saldo($year10, 2000), 'Year 10 is recalculated by the ledger, not left at its old saldo.');
        self::assertSame(-1125.0, $saldo($year10, 3000));
        self::assertSame(1175.0, $saldo($year11, 2000));
        self::assertSame('2', $year10['summary']['box6'], 'The ledger recalculation counts the year\'s postings.');
        self::assertSame(date('Y-m-d'), $year10['summary']['box7']);
        self::assertSame(
            [['id' => '10', 'kostpris' => '999'], ['id' => '11', 'kostpris' => '999']],
            pg_fetch_all($this->sql('SELECT id, kostpris FROM ordrer ORDER BY id')),
            "The page's own genberegn() is never called with a year as an order id."
        );
        self::assertFalse(function_exists('equalizeMatchingRecords'), 'includes/genberegn.php (with its ?regnskabsaar= block) was not loaded.');
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
        self::assertStringContainsString('SST-1005: opening balances after saving fiscal year 10 not updated: test: recalculation stopped', (string) file_get_contents($log));
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
        self::assertStringContainsString('«æøå»', json_decode($literal), 'Danish letters stay readable.');
        self::assertSame(1, preg_match('/^[\x20-\x7e]*$/', $html), 'Written as ASCII, so the page charset does not matter.');
    }

    /**
     * Lui's pre-check: on a LATIN9 regnskab findtekst() and grupper.beskrivelse give ISO-8859 bytes, which json_encode()
     * replaced with U+FFFD, so every æ, ø and å in the year card's alert came out as a replacement character.
     */
    public function testTheAlertScriptReadsTheSameOnALatin9Regnskab(): void
    {
        $this->sql("UPDATE grupper SET beskrivelse = 'År «æøå»' WHERE art = 'RA' AND kodenr = 10");
        $this->sql("SET client_encoding TO 'LATIN9'");
        $GLOBALS['db_encode'] = 'LATIN9';
        $stop = $this->stop(['accounts' => range(2001, 2011)]);
        $text = followingOpeningBalanceStopText($stop, 1);
        self::assertStringContainsString("\xc5r \xab\xe6\xf8\xe5\xbb", $text, 'The text is in the database encoding, as the year list shows it.');
        self::assertStringContainsString('2010 ...', $text, 'The account list ends in plain dots: LATIN9 has no ellipsis.');
        self::assertStringNotContainsString("\xe2\x80\xa6", $text);
        $html = followingOpeningBalanceAlertScript($stop, 1);
        self::assertSame(1, preg_match('/^[\x20-\x7e]*$/', $html), 'Written as ASCII, so it reads the same on the ISO-8859-1 page.');
        $alert = json_decode(substr($html, strlen("<script type='text/javascript'>alert("), -strlen(');</script>')));
        self::assertSame(mb_convert_encoding($text, 'UTF-8', 'ISO-8859-15'), $alert);
        self::assertStringContainsString('Primo i regnskabsåret År «æøå» er ikke opdateret automatisk.', $alert);
        self::assertStringNotContainsString("\u{FFFD}", $alert);
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

    /**
     * In a finally block: end every transaction that may block $waiter first, then drain $waiter's pending result, so
     * a failed assertion cannot leave the drain waiting for a lock the test itself still holds (CodeRabbit on PR #1109).
     * A busy connection is not touched until it is drained. On the normal path everything has committed and been
     * drained already, and this does nothing.
     */
    private function releaseThenDrain(array $connections, $waiter): void
    {
        foreach ($connections as $connection) {
            if ($connection !== $waiter) {
                $this->rollbackOpenTransaction($connection);
            }
        }
        while (pg_get_result($waiter)) {
        }
        $this->rollbackOpenTransaction($waiter);
    }

    private function rollbackOpenTransaction($connection): void
    {
        $status = pg_transaction_status($connection);
        if ($status === PGSQL_TRANSACTION_INTRANS || $status === PGSQL_TRANSACTION_INERROR) {
            pg_query($connection, 'ROLLBACK');
        }
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
            $this->releaseThenDrain([$this->connection, $other], $other);
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
            $this->releaseThenDrain([$this->connection, $other], $other);
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
            $this->releaseThenDrain([$this->connection, $other], $other);
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
            $this->releaseThenDrain([$this->connection, $other], $other);
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
            $this->releaseThenDrain([$this->connection, $other], $other);
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
            $this->releaseThenDrain([$this->connection, $other], $other);
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

    /** Codex round 15: the row appeared with the very same year while this request was creating it. */
    public function testARequestThatFindsTheRowWithTheSameYearHoldsItUntilItCommits(): void
    {
        $schema = 'sst1005_create_same_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $mine = $GLOBALS['connection'];
            // A card on the other connection did not see the row; meanwhile the first check created it with 8.
            pg_query($other, 'BEGIN');
            $GLOBALS['sst1005Hook'] = ['after' => '/openingBalancePending/', 'times' => 1, 'run' => function () use ($mine, $other): void {
                $GLOBALS['connection'] = $mine;
                followingOpeningBalanceCreatePending(8);
                $GLOBALS['connection'] = $other;
            }];
            $GLOBALS['connection'] = $other;
            followingOpeningBalanceMarkPending(8);
            $GLOBALS['connection'] = $mine;
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'The row appeared in between.');
            self::assertSame('8', $this->setting('openingBalancePending'));
            // A rebuild that takes year 8 now has to wait for the card's commit.
            pg_send_query($mine, "UPDATE settings SET var_value = '0' WHERE var_name = 'openingBalancePending' AND var_value = '8'");
            usleep(300000);
            self::assertTrue(pg_connection_busy($mine), 'Taking the year waits for the card.');
            pg_query($other, 'COMMIT');
            pg_get_result($mine);
            while (pg_get_result($mine)) {
            }
            self::assertSame(1, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM settings WHERE var_name = 'openingBalancePending'"))['n']);
        } finally {
            unset($GLOBALS['sst1005Hook']);
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            $this->releaseThenDrain([$this->connection, $other], $this->connection);
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
            $this->releaseThenDrain([$this->connection, $other], $this->connection);
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
            $this->releaseThenDrain([$this->connection, $other], $this->connection);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /**
     * Codex round 16: a late commit below the watermark and a deleted journal cancel out in the year's count and totals,
     * but 20 has moved from 2100 (carried into 2000) to 2500. The fingerprint per account finds it.
     */
    public function testALateCommitAndADeletionThatCancelOutInTheYearsTotalsAreFound(): void
    {
        $schema = 'sst1005_fingerprint_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            self::assertNull(checkFollowingOpeningBalances(11));
            // A posting on 2500 that commits late: its ids are below the watermark the next checks see.
            pg_query($other, 'BEGIN');
            pg_query($other, "INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2500, '2024-03-01', 20, 0), (1000, '2024-03-01', 0, 20)");
            $this->postInYear9(2000, 1);
            self::assertNull(checkFollowingOpeningBalances(11));
            $this->setSyncState((int) explode('|', $this->syncState())[0], 0, time() - 601);
            self::assertNull(checkFollowingOpeningBalances(11));
            $vector = function (): array {
                return [$this->primo(10, 2000), $this->primo(10, 2500), $this->primo(10, 3000)];
            };
            self::assertSame([1121.0, 0.0, -1121.0], $vector());
            $totals = function (): array {
                return pg_fetch_assoc($this->sql("SELECT count(*) AS n, sum(debet) AS d, sum(kredit) AS k, max(id) AS m FROM transaktioner WHERE transdate BETWEEN '2023-07-01' AND '2024-06-30'"));
            };
            $before = $totals();
            // The journal with 20 on 2100 is taken back (its postings deleted), then the late posting commits.
            $this->sql("DELETE FROM transaktioner WHERE transdate = '2024-06-30' AND ((kontonr = 2100 AND debet = 20) OR (kontonr = 1000 AND kredit = 20))");
            pg_query($other, 'COMMIT');
            self::assertSame($before, $totals(), 'Same count, totals and highest id: the totals cannot tell.');
            $this->setSyncState((int) explode('|', $this->syncState())[0], 0, time() - 601);
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertSame([1101.0, 20.0, -1121.0], $vector(), 'The due check found the move from 2100 to 2500.');
            self::assertNull(syncFollowingOpeningBalances(9));
            self::assertSame([1101.0, 20.0, -1121.0], $vector(), 'The same as a manual rebuild.');
        } finally {
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /**
     * Codex round 17: deleteFiscalYear.php removes the year's accounts and writes a timestamp in box10; older deletions
     * wrote 'on'. Activating or saving the first year after it keeps that year's own opening balances and carries them on.
     */
    public function testTheFirstYearAfterADeletedYearKeepsItsOpeningBalancesWhateverTheMarker(): void
    {
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 8');
        foreach (['timestamp' => '1780000000', 'legacy' => 'on'] as $case => $marker) {
            $this->sql("UPDATE grupper SET box10 = '$marker' WHERE art = 'RA' AND kodenr = 8");
            foreach (['activate', 'card'] as $how) {
                $this->sql('UPDATE kontoplan SET primo = 777 WHERE regnskabsaar = 10');
                if ($how === 'activate') {
                    self::assertNull(syncFollowingOpeningBalances(8, 9), "$case/$how");
                } else {
                    $this->sql('BEGIN');
                    $before = followingOpeningBalancePrimo(9, true);
                    self::assertNull(updateOpeningBalancesAfterYearSave(9, $before), "$case/$how");
                    $this->sql('COMMIT');
                }
                self::assertSame([1000.0, -1000.0], [$this->primo(9, 2000), $this->primo(9, 3000)], "Year 9 keeps its own opening balances ($case/$how).");
                self::assertSame([1120.0, -1120.0], [$this->primo(10, 2000), $this->primo(10, 3000)], "Year 10 is carried from year 9 ($case/$how).");
                self::assertSame(0, (int) pg_fetch_assoc($this->sql('SELECT count(*) AS n FROM kontoplan WHERE regnskabsaar = 8'))['n'], 'Nothing is written into the deleted year.');
            }
        }
        // A deleted target year is a boundary too.
        $this->sql("UPDATE grupper SET box10 = '1780000000' WHERE art = 'RA' AND kodenr = 11");
        self::assertSame([10], updateFollowingOpeningBalances(9, $stop));
        self::assertNull($stop);
        self::assertTrue(followingOpeningBalanceYearDeleted('1780000000'));
        self::assertTrue(followingOpeningBalanceYearDeleted('on'));
        self::assertFalse(followingOpeningBalanceYearDeleted(''));
        self::assertFalse(followingOpeningBalanceYearDeleted(null));
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

    public function testAWaitingYearThatIsDeletedDoesNotSwallowLaterWork(): void
    {
        self::assertNull(checkFollowingOpeningBalances(11));
        // A year 8 posting leaves year 8 waiting; the session is in year 8, so nothing is rebuilt yet.
        $this->sql("INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2023-03-01', 3, 0), (1000, '2023-03-01', 0, 3)");
        self::assertNull(checkFollowingOpeningBalances(8));
        self::assertStringEndsWith('|8', $this->syncState());

        // Year 8 is deleted as systemdata/fiscalYearInc/deleteFiscalYear.php does it, before anyone rebuilds.
        $this->sql("DELETE FROM transaktioner WHERE transdate <= '2023-06-30'");
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 8');
        $this->sql("UPDATE grupper SET box10 = '1763600000' WHERE art = 'RA' AND kodenr = 8");

        // A year 9 posting is found next; the row only lowers, so it still says 8.
        $this->postInYear9(2000, 5);
        self::assertNull(checkFollowingOpeningBalances(9));
        self::assertStringEndsWith('|8', $this->syncState());
        self::assertSame(777.0, $this->primo(10, 2000), 'A session in year 9 does not rebuild year 9 into year 10.');

        // A session in year 10 rebuilds from year 9, the first year after the deleted one, instead of stopping at year 8.
        self::assertNull(checkFollowingOpeningBalances(10));
        self::assertSame(1125.0, $this->primo(10, 2000));
        self::assertSame(1175.0, $this->primo(11, 2000));
        self::assertStringEndsWith('|0', $this->syncState());
        self::assertNull(followingOpeningBalanceStop());
    }

    public function testActivatingAYearAfterADeletedWaitingYearRebuildsFromTheFirstLiveYear(): void
    {
        self::assertNull(checkFollowingOpeningBalances(11));
        $this->sql("INSERT INTO transaktioner (kontonr, transdate, debet, kredit) VALUES (2000, '2023-03-01', 3, 0), (1000, '2023-03-01', 0, 3)");
        self::assertNull(checkFollowingOpeningBalances(8));
        $this->sql("DELETE FROM transaktioner WHERE transdate <= '2023-06-30'");
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 8');
        $this->sql("UPDATE grupper SET box10 = 'on' WHERE art = 'RA' AND kodenr = 8");
        $this->postInYear9(2000, 5);
        self::assertNull(checkFollowingOpeningBalances(9));

        // «Sæt aktivt» on year 11 asks for year 10, takes waiting year 8 and starts at year 9, so years 10 and 11 follow.
        self::assertNull(syncFollowingOpeningBalances(10, 11));
        self::assertSame(1125.0, $this->primo(10, 2000));
        self::assertSame(1175.0, $this->primo(11, 2000));
        self::assertStringEndsWith('|0', $this->syncState());
    }

    /**
     * Make the database refuse UPDATE OF primo in one year: at once, or only at COMMIT (a deferred constraint trigger).
     */
    private function refuseUpdates(string $schema, int $year, bool $atCommit): void
    {
        $this->sql("CREATE FUNCTION $schema.sst1005_refuse() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'test: the database refused the update'; END \$\$");
        if ($atCommit) {
            $this->sql("CREATE CONSTRAINT TRIGGER sst1005_refuse AFTER UPDATE OF primo ON $schema.kontoplan DEFERRABLE INITIALLY DEFERRED FOR EACH ROW WHEN (NEW.regnskabsaar = $year) EXECUTE FUNCTION $schema.sst1005_refuse()");
        } else {
            $this->sql("CREATE TRIGGER sst1005_refuse BEFORE UPDATE OF primo ON $schema.kontoplan FOR EACH ROW WHEN (NEW.regnskabsaar = $year) EXECUTE FUNCTION $schema.sst1005_refuse()");
        }
    }

    /**
     * @return array<string, array{0: bool}> Whether the database refuses the rebuild's write only at COMMIT.
     */
    public static function pageFailures(): array
    {
        return ['a failed statement' => [false], 'a refused commit (a deferred trigger)' => [true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pageFailures')]
    public function testARebuildThatTheProductionErrorPathEndsLeavesTheFailedMessage(bool $atCommit): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open is not available');
        }
        $schema = 'sst1005_exit_' . ($atCommit ? 'commit_' : '') . getmypid();
        $other = $this->privateSchema($schema);
        pg_close($other);
        $scratch = sys_get_temp_dir() . '/sst1005exit' . getmypid();
        try {
            self::assertNull(checkFollowingOpeningBalances(11));
            $this->postInYear9(2000, 5);
            self::assertNull(checkFollowingOpeningBalances(9));
            self::assertStringEndsWith('|9', $this->syncState());
            // The database refuses the opening balances the rebuild writes into year 10, at once or at COMMIT.
            $this->refuseUpdates($schema, 10, $atCommit);

            // A session in year 10 opens a page in a process of its own with the production db_modify(), which exits.
            mkdir($scratch . '/temp/sst1005', 0777, true);
            $child = proc_open(
                [PHP_BINARY, __DIR__ . '/support/followingOpeningBalances/productionExit.php', TestDatabase::sqlFixture(static::class)->name(), $schema, $scratch],
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            self::assertIsResource($child);
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($child);
            self::assertStringContainsString('test: the database refused the update', $output, 'The statement failed in db_modify().');
            self::assertStringNotContainsString('not-ended', $output, 'db_modify() ended the request, as in production.');
            self::assertStringContainsString('shutdown-done', $output, $output);

            self::assertSame($this->stop(['failed' => true]), followingOpeningBalanceStop(), 'The failed message is stored, so the user is told to press Gem/opdatér.');
            self::assertStringEndsWith('|0', $this->syncState(), 'No retry on every page.');
            self::assertSame(777.0, $this->primo(10, 2000), 'Nothing of the rebuild is kept.');

            // Once the database accepts it again, Gem/opdatér on year 10 retries from year 9 and clears the message.
            $this->sql("DROP TRIGGER sst1005_refuse ON $schema.kontoplan");
            $this->sql('BEGIN');
            self::assertNull(updateOpeningBalancesAfterYearSave(10));
            $this->sql('COMMIT');
            self::assertSame(1125.0, $this->primo(10, 2000));
            self::assertNull(followingOpeningBalanceStop());
        } finally {
            $this->dropPrivateSchema($schema);
            if (is_dir($scratch)) {
                exec('rm -rf ' . escapeshellarg($scratch));
            }
        }
    }

    public function testANestedCallerKeepsTheGuardItDidNotArm(): void
    {
        self::assertTrue(followingOpeningBalanceFailureGuard(9, 9), 'The card arms it.');
        self::assertFalse(followingOpeningBalanceFailureGuard(5, 5), 'A nested caller does not.');
        $this->sql('BEGIN');
        self::assertNull(updateOpeningBalancesAfterYearSave(10));
        $this->sql('COMMIT');
        self::assertSame(['sourceYear' => 9, 'checkedFrom' => 9], $GLOBALS['followingOpeningBalanceFailure'], 'Still the card\'s guard after the carry-forward.');
        followingOpeningBalanceFailureGuard(null);
        self::assertNull($GLOBALS['followingOpeningBalanceFailure']);
        self::assertTrue(followingOpeningBalanceFailureGuard(9, 9), 'Armed again by the next save.');
        followingOpeningBalanceFailureGuard(null);
    }

    /**
     * @return array<string, array{0: int, 1: string}> The year whose primo the database refuses, and where that happens.
     */
    public static function cardFailures(): array
    {
        return [
            'the card\'s own primo reset' => [10, 'reset'],
            'the carry-forward into the next year' => [11, 'carry'],
            'the commit (a deferred trigger)' => [10, 'commit'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cardFailures')]
    public function testACardSaveThatTheProductionErrorPathEndsLeavesTheFailedMessage(int $refusedYear, string $where): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open is not available');
        }
        $schema = 'sst1005_card_' . $where . '_' . getmypid();
        $other = $this->privateSchema($schema);
        pg_close($other);
        $scratch = sys_get_temp_dir() . '/sst1005card' . $where . getmypid();
        try {
            $before = [$this->yearState(10), $this->yearState(11)];
            $this->refuseUpdates($schema, $refusedYear, $where === 'commit');

            mkdir($scratch . '/temp/sst1005', 0777, true);
            $child = proc_open(
                [PHP_BINARY, __DIR__ . '/support/followingOpeningBalances/productionExit.php', TestDatabase::sqlFixture(static::class)->name(), $schema, $scratch, 'card'],
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            self::assertIsResource($child);
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($child);
            self::assertStringContainsString('test: the database refused the update', $output);
            self::assertStringNotContainsString('not-ended', $output, 'db_modify() ended the request, as in production.');
            self::assertStringContainsString('shutdown-done', $output, $output);

            self::assertSame($this->stop(['failed' => true]), followingOpeningBalanceStop(), "The failed message is stored when $where fails.");
            self::assertSame($before, [$this->yearState(10), $this->yearState(11)], 'Nothing of the card\'s save is kept.');
            self::assertSame('2024/25', pg_fetch_assoc($this->sql("SELECT beskrivelse FROM grupper WHERE art = 'RA' AND kodenr = 10"))['beskrivelse']);

            // Once the database accepts it again, Gem/opdatér on year 10 saves and clears the message.
            $this->sql("DROP TRIGGER sst1005_refuse ON $schema.kontoplan");
            $this->sql('BEGIN');
            self::assertNull(updateOpeningBalancesAfterYearSave(10));
            $this->sql('COMMIT');
            self::assertSame(1120.0, $this->primo(10, 2000));
            self::assertNull(followingOpeningBalanceStop());
        } finally {
            $this->dropPrivateSchema($schema);
            if (is_dir($scratch)) {
                exec('rm -rf ' . escapeshellarg($scratch));
            }
        }
    }

    /**
     * CodeRabbit on PR #1109: a rebuild read year 9's dates before it locked year 10. A card that shortened year 9 to
     * May and carried it on in between was then overwritten with the June balance, because both reads of the inputs
     * used the dates read at the start.
     */
    public function testARebuildCalculatesFromTheSourceYearsDatesAsTheyAreWhenItHasTheTargetYear(): void
    {
        // Year 9 ends in May 2024 once the rebuild has read the years: 20 on 2100 of 30 June is no longer year 9's.
        $GLOBALS['sst1005Hook'] = ['after' => 'select kodenr, box1, box2, box3, box4, box10 from grupper', 'times' => 1, 'run' => function (): void {
            $this->sql("UPDATE grupper SET box3 = '5' WHERE art = 'RA' AND kodenr = 9");
        }];
        $stop = 'unset';
        self::assertSame([10, 11], updateFollowingOpeningBalances(9, $stop));
        self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'The dates changed after the first read.');
        self::assertNull($stop);
        self::assertSame([1100.0, -1100.0], [$this->primo(10, 2000), $this->primo(10, 3000)], 'Calculated with year 9 ending in May.');

        // Changed back between the read the transfer is calculated from and the read that checks it: calculated again.
        $GLOBALS['sst1005Hook'] = ['after' => "kodenr in ('9', '10')", 'times' => 1, 'run' => function (): void {
            $this->sql("UPDATE grupper SET box3 = '6' WHERE art = 'RA' AND kodenr = 9");
        }];
        self::assertSame([10, 11], updateFollowingOpeningBalances(9, $stop));
        self::assertArrayNotHasKey('sst1005Hook', $GLOBALS);
        self::assertSame([1120.0, -1120.0], [$this->primo(10, 2000), $this->primo(10, 3000)], 'The June balance once June is year 9\'s again.');

        // Dates that became invalid meanwhile stop the transfer before anything is written.
        $GLOBALS['sst1005Hook'] = ['after' => 'select kodenr, box1, box2, box3, box4, box10 from grupper', 'times' => 1, 'run' => function (): void {
            $this->sql("UPDATE grupper SET box3 = '13' WHERE art = 'RA' AND kodenr = 9");
        }];
        self::assertSame([], updateFollowingOpeningBalances(9, $stop));
        self::assertSame($this->stop(['invalidDates' => [9]]), $stop);
        self::assertSame(1120.0, $this->primo(10, 2000));
    }

    /**
     * CodeRabbit and Lui's pre-check on PR #1109: on MySQL the result of GET_LOCK() was ignored, so a request that did
     * not get the lock (0 = time-out, NULL = error) still created the row. PostgreSQL does not know GET_LOCK, so the
     * test answers it the way MySQL would; the real MySQL behaviour is in the round-28 MariaDB smoke test.
     */
    public function testOnMysqlTheWaitingYearRowIsOnlyCreatedUnderTheLock(): void
    {
        $seen = [];
        $answer = '0';
        $GLOBALS['sst1005Rewrite'] = function (string $sql) use (&$seen, &$answer): string {
            if (preg_match("/^SELECT (GET_LOCK|RELEASE_LOCK)\\(CONCAT\\('saldi:(\\w+):'/", $sql, $m) === 1) {
                $seen[] = $m[1] . ' ' . $m[2];
                return ($m[1] === 'GET_LOCK' && $m[2] === 'openingBalancePending') ? "SELECT $answer AS locked" : 'SELECT 1 AS locked';
            }
            if ($sql === 'select version() as version') {
                return "select '10.11.14-MariaDB-0ubuntu0.24.04.1' as version";
            }
            // MySQL's INSERT ... SELECT ... FROM DUAL and cast(... as signed) in PostgreSQL's words.
            return str_replace(["'0' from dual where not exists", 'as signed)'], ["'0' where not exists", 'as integer)'], $sql);
        };
        $GLOBALS['db_type'] = 'mysqli';
        foreach (['0', 'NULL'] as $answer) {
            $seen = [];
            self::assertFalse(followingOpeningBalanceCreatePending(9), "GET_LOCK gave $answer.");
            self::assertFalse(followingOpeningBalanceMarkPending(9));
            self::assertNull($this->setting('openingBalancePending'), 'Nothing is written without the lock.');
            self::assertNotContains('RELEASE_LOCK openingBalancePending', $seen, 'A lock that was not given is not released.');
            // The first check saves no state either, so the next page starts again.
            self::assertNull(checkFollowingOpeningBalances(11));
            self::assertNull($this->setting('openingBalanceSync'));
        }

        $answer = '1';
        $seen = [];
        self::assertTrue(followingOpeningBalanceCreatePending(9));
        self::assertSame('9', $this->setting('openingBalancePending'));
        self::assertSame(['GET_LOCK openingBalancePending', 'RELEASE_LOCK openingBalancePending'], $seen, 'Outside a transaction it is released at once.');
        // Inside a transaction the lock is kept until that transaction ends, so the next creator reads only once the row
        // is committed (Codex round 29: under READ COMMITTED it did not wait for the row); a second take reuses it.
        $this->sql("DELETE FROM settings WHERE var_name = 'openingBalancePending'");
        $seen = [];
        $this->sql('BEGIN');
        $GLOBALS['db_transaktion_depth'] = 1;
        self::assertTrue(followingOpeningBalanceCreatePending(8));
        $this->sql("DELETE FROM settings WHERE var_name = 'openingBalancePending'");
        self::assertTrue(followingOpeningBalanceCreatePending(7));
        self::assertSame(['GET_LOCK openingBalancePending'], $seen, 'Kept, and not taken twice.');
        $this->sql('COMMIT');
        $GLOBALS['db_transaktion_depth'] = 0;
        followingOpeningBalanceReleaseLocks();
        self::assertSame(['GET_LOCK openingBalancePending', 'RELEASE_LOCK openingBalancePending'], $seen, 'Released once the transaction has ended.');
        followingOpeningBalanceReleaseLocks();
        self::assertCount(2, $seen, 'Only once.');
        self::assertSame('7', $this->setting('openingBalancePending'));

        // A rebuild that has to leave a year waiting and cannot create the row throws, so its caller rolls it back.
        $this->sql("DELETE FROM settings WHERE var_name = 'openingBalancePending'");
        $answer = '0';
        $schema = 'sst1005_mysql_lock_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            // A journal posting holds 2000 of year 10, so the rebuild (MariaDB 10.11 knows SKIP LOCKED) leaves year 9 waiting.
            pg_query($other, 'BEGIN');
            pg_query($other, 'UPDATE kontoplan SET saldo = saldo + 1 WHERE regnskabsaar = 10 AND kontonr = 2000');
            $this->sql('BEGIN');
            $GLOBALS['db_transaktion_depth'] = 1;
            try {
                updateFollowingOpeningBalances(9, $stop, $deferred);
                self::fail('The year that could not be left waiting was dropped without a word.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('fiscal year 9 could not be left waiting', $e->getMessage());
            }
        } finally {
            $GLOBALS['db_transaktion_depth'] = 0;
            $this->releaseThenDrain([$this->connection, $other], $other);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    public function testSkipLockedIsUsedOnServersThatHaveIt(): void
    {
        self::assertTrue(followingOpeningBalanceSkipLocked(), 'PostgreSQL always.');
        self::assertTrue(followingOpeningBalanceVersionSkipsLocked('8.0.36'));
        self::assertTrue(followingOpeningBalanceVersionSkipsLocked('10.11.14-MariaDB-0ubuntu0.24.04.1'));
        self::assertTrue(followingOpeningBalanceVersionSkipsLocked('10.6.0-MariaDB'));
        self::assertFalse(followingOpeningBalanceVersionSkipsLocked('10.5.27-MariaDB'));
        self::assertFalse(followingOpeningBalanceVersionSkipsLocked('8.0.0'));
        self::assertFalse(followingOpeningBalanceVersionSkipsLocked('5.7.44-log'));
        self::assertFalse(followingOpeningBalanceVersionSkipsLocked(''));
    }

    /**
     * CodeRabbit on PR #1109: with an old state "lastId|8" and a row that already held 9, the first check saved the
     * state without year 8 before it lowered the row, so a request that ended in between lost year 8.
     */
    public function testAYearWaitingInAnOldStateIsInItsRowBeforeTheStateIsSavedWithoutIt(): void
    {
        $maxId = (int) pg_fetch_assoc($this->sql('SELECT max(id) AS id FROM transaktioner'))['id'];
        $this->sql("INSERT INTO settings (var_name, var_grp, var_value, user_id) VALUES ('openingBalanceSync', 'system', '$maxId|8', 0), ('openingBalancePending', 'system', '9', 0)");
        $GLOBALS['sst1005Hook'] = ['after' => "/^update settings set var_value = '\\{.*' where var_name = 'openingBalanceSync'$/s", 'times' => 1, 'run' => function (): void {
            throw new RuntimeException('test: the request ends right after the state is saved');
        }];
        try {
            checkFollowingOpeningBalances(11);
            self::fail('The request was meant to end.');
        } catch (RuntimeException $e) {
            self::assertSame('test: the request ends right after the state is saved', $e->getMessage());
        }
        self::assertSame(0, followingOpeningBalanceStateParts((string) $this->setting('openingBalanceSync'))['pendingYear'], 'The state without year 8 was saved.');
        self::assertSame('8', $this->setting('openingBalancePending'), 'Year 8 was in its row before that.');
    }

    /**
     * CodeRabbit on PR #1109: «Sæt aktivt» and the page check took the waiting year before the failure guard was armed,
     * so a request that ended right after (a time-out in the lookups) lost the work without a message.
     */
    public function testARequestThatEndsRightAfterTakingTheWaitingYearLeavesTheFailedMessage(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'sst1005');
        ini_set('error_log', $log);
        self::assertNull(checkFollowingOpeningBalances(11));
        followingOpeningBalanceMarkPending(9);
        $GLOBALS['sst1005Hook'] = ['after' => "update settings set var_value = '0' where var_name = 'openingBalancePending'", 'times' => 1, 'run' => function (): void {
            // The request ends here: PHP runs the shutdown functions.
            followingOpeningBalanceFailureOnShutdown();
            throw new RuntimeException('test: the request ended');
        }];
        try {
            syncFollowingOpeningBalances(9);
            self::fail('The request was meant to end.');
        } catch (RuntimeException $e) {
            self::assertSame('test: the request ended', $e->getMessage());
        }
        self::assertSame('0', $this->setting('openingBalancePending'), 'The year was taken.');
        self::assertSame($this->stop(['failed' => true]), followingOpeningBalanceStop(), 'The message asks for Gem/opdatér, so the work is not lost unseen.');
        self::assertStringContainsString('the request ended during the rebuild', (string) file_get_contents($log));
        unlink($log);
    }

    /**
     * CodeRabbit on PR #1109: a rebuild that left a transfer for the next check (another transaction held the target
     * year) cleared a stored block it never reached, on «Sæt aktivt», the page check and the year card alike.
     */
    public function testARebuildThatLeavesATransferForTheNextCheckKeepsTheBlockItDidNotReach(): void
    {
        $this->postInYear9(2500, 7.5);
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        $expected = $this->stop(['accounts' => [2500]]);
        self::assertSame($expected, syncFollowingOpeningBalances(9));
        $schema = 'sst1005_deferred_stop_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            // A journal posting in year 10 holds 2000 of year 10.
            pg_query($other, 'BEGIN');
            pg_query($other, 'UPDATE kontoplan SET saldo = saldo + 1 WHERE regnskabsaar = 10 AND kontonr = 2000');
            self::assertNull(syncFollowingOpeningBalances(9), 'Year 9 is left for the next check.');
            self::assertSame($expected, followingOpeningBalanceStop(), 'The block it did not reach stays.');
            self::assertSame('9', $this->setting('openingBalancePending'));
            // The card on year 9 carries year 8 into it and then reaches year 10 the same way.
            $this->sql('BEGIN');
            $before = followingOpeningBalancePrimo(9, true);
            self::assertNull(updateOpeningBalancesAfterYearSave(9, $before));
            $this->sql('COMMIT');
            self::assertSame($expected, followingOpeningBalanceStop(), 'The card keeps it too.');
            pg_query($other, 'ROLLBACK');
            self::assertSame($expected, syncFollowingOpeningBalances(9), 'Once year 10 is free, the rebuild reaches the block again.');
        } finally {
            $this->releaseThenDrain([$this->connection, $other], $other);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /**
     * CodeRabbit on PR #1109: a rebuild from year 10 read the stored block 10→11 without a lock and then cleared it by
     * name, so a block 9→10 that another rebuild committed in between was overwritten without a message.
     */
    public function testABlockAnotherRebuildCommitsWhileTheStopIsCheckedIsKept(): void
    {
        $schema = 'sst1005_stop_lock_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            saveFollowingOpeningBalanceStop($this->stop(['sourceYear' => 10, 'targetYear' => 11, 'accounts' => [2500]]), 10);
            $blocked = $this->stop(['accounts' => [2500]]);
            // Rebuild B from year 9 (other connection) commits its block right after rebuild A has read the stored one.
            $GLOBALS['sst1005Hook'] = ['after' => "/^select id, var_value from settings where id = '\\d+' and var_name = 'openingBalanceStop' for update$/", 'times' => 1, 'run' => function () use ($other, $blocked): void {
                pg_send_query($other, "UPDATE settings SET var_value = '" . pg_escape_string($other, json_encode($blocked)) . "' WHERE var_name = 'openingBalanceStop'");
                for ($i = 0; $i < 20 && pg_connection_busy($other); $i++) {
                    usleep(50000);
                }
            }];
            // Rebuild A from year 10 got through, so it clears the block 10→11.
            $this->sql('BEGIN');
            saveFollowingOpeningBalanceStop(null, 10);
            $this->sql('COMMIT');
            pg_get_result($other);
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'B committed between A\'s check and its clear.');
            self::assertSame($blocked, followingOpeningBalanceStop(), 'B\'s block is kept: it waited for A instead of being overwritten.');
        } finally {
            unset($GLOBALS['sst1005Hook']);
            $this->releaseThenDrain([$this->connection, $other], $other);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /**
     * Lui's pre-check on PR #1109: with settings_key_unique (includes/settings/uniqueIndex.php), two requests that both
     * found the block row missing made the second INSERT fail, which ends that request and rolls back a card save.
     */
    public function testTwoRequestsCreatingTheBlockRowAtOnceBothFinish(): void
    {
        $schema = 'sst1005_unique_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $this->sql("CREATE UNIQUE INDEX settings_key_unique ON settings (coalesce(var_grp, ''), var_name, coalesce(user_id, 0))");
            // Another rebuild creates the row and commits right after this one found it missing.
            $GLOBALS['sst1005Hook'] = ['after' => "select id from settings where var_name = 'openingBalanceStop'", 'times' => 1, 'run' => function () use ($other): void {
                pg_query($other, "INSERT INTO settings (var_name, var_grp, var_value, var_description, user_id) VALUES ('openingBalanceStop', 'system', '', 'other request', 0)");
            }];
            $stop = $this->stop(['accounts' => [2500]]);
            saveFollowingOpeningBalanceStop($stop, 9);
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'The other row appeared in between.');
            self::assertSame($stop, followingOpeningBalanceStop(), 'This INSERT gave way and the block was written into the other row.');
            self::assertSame(1, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM settings WHERE var_name = 'openingBalanceStop'"))['n']);
        } finally {
            unset($GLOBALS['sst1005Hook']);
            $this->releaseThenDrain([$this->connection, $other], $other);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /**
     * Codex round 28: without a unique index on settings (MySQL, or a tenant whose index migration has not run),
     * clearing the block created an empty row before its locking read, so a block another rebuild stored meanwhile
     * ended up as a second row.
     */
    public function testClearingTheBlockNeverCreatesASecondRow(): void
    {
        $schema = 'sst1005_clear_row_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $mine = $GLOBALS['connection'];
            $blocked = $this->stop(['accounts' => [2500]]);
            // Rebuild A from year 10 got through and finds no block row; rebuild B from year 9 then stores 9→10.
            $GLOBALS['sst1005Hook'] = ['after' => "select id from settings where var_name = 'openingBalanceStop' order by id", 'times' => 1, 'run' => function () use ($mine, $other, $blocked): void {
                $GLOBALS['connection'] = $other;
                saveFollowingOpeningBalanceStop($blocked, 9);
                $GLOBALS['connection'] = $mine;
            }];
            saveFollowingOpeningBalanceStop(null, 10);
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, 'B stored its block after A looked.');
            self::assertSame(1, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM settings WHERE var_name = 'openingBalanceStop'"))['n'], 'One block row.');
            self::assertSame($blocked, followingOpeningBalanceStop());
        } finally {
            unset($GLOBALS['sst1005Hook']);
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            $this->releaseThenDrain([$this->connection, $other], $other);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /**
     * Codex round 28: two rebuilds that both find the block row missing create it one at a time under the setting's
     * lock, and the second one reads again and finds the first one's row, also without a unique index.
     */
    public function testTwoRebuildsStoringTheFirstBlockMakeOneRow(): void
    {
        $schema = 'sst1005_two_writers_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $mine = $GLOBALS['connection'];
            $first = $this->stop(['accounts' => [2500]]);
            // Rebuild B (other connection) stores 9→10 right after rebuild A found no row.
            $GLOBALS['sst1005Hook'] = ['after' => "select id from settings where var_name = 'openingBalanceStop' order by id", 'times' => 1, 'run' => function () use ($mine, $other, $first): void {
                $GLOBALS['connection'] = $other;
                saveFollowingOpeningBalanceStop($first, 9);
                $GLOBALS['connection'] = $mine;
            }];
            saveFollowingOpeningBalanceStop($this->stop(['sourceYear' => 10, 'targetYear' => 11, 'accounts' => [2500]]), 10);
            self::assertArrayNotHasKey('sst1005Hook', $GLOBALS);
            self::assertSame(1, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM settings WHERE var_name = 'openingBalanceStop'"))['n'], 'One block row.');
            self::assertSame($first, followingOpeningBalanceStop(), 'A found B\'s earlier block and kept it.');
        } finally {
            unset($GLOBALS['sst1005Hook']);
            $GLOBALS['connection'] = $mine ?? $GLOBALS['connection'];
            $this->releaseThenDrain([$this->connection, $other], $other);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /**
     * Codex round 31: the waiting-year and block rows are created and committed before «Sæt aktivt», the page check or
     * the year card begins its transaction, so no transaction of this feature has to create them (on MySQL that could
     * wait in a circle with another creator).
     */
    public function testTheSettingsRowsExistBeforeARebuildOrTheCardBeginsItsTransaction(): void
    {
        $schema = 'sst1005_prepare_' . getmypid();
        $other = $this->privateSchema($schema);
        try {
            $seen = [];
            foreach (['sync' => fn () => syncFollowingOpeningBalances(9), 'card' => fn () => updateOpeningBalancesAfterYearSave(10)] as $caller => $run) {
                $this->sql("DELETE FROM settings WHERE var_name IN ('openingBalancePending', 'openingBalanceStop')");
                $GLOBALS['sst1005Hook'] = ['after' => '/^begin$/i', 'times' => 1, 'run' => function () use ($other, $caller, &$seen): void {
                    $seen[$caller] = (int) pg_fetch_assoc(pg_query($other, "SELECT count(*) AS n FROM settings WHERE var_name IN ('openingBalancePending', 'openingBalanceStop')"))['n'];
                }];
                $run();
                self::assertArrayNotHasKey('sst1005Hook', $GLOBALS, "$caller began a transaction.");
            }
            self::assertSame(['sync' => 2, 'card' => 2], $seen, 'Both rows were committed before the transaction began.');
            self::assertSame(1, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM settings WHERE var_name = 'openingBalanceStop'"))['n']);
            self::assertSame(1, (int) pg_fetch_assoc($this->sql("SELECT count(*) AS n FROM settings WHERE var_name = 'openingBalancePending'"))['n']);
        } finally {
            unset($GLOBALS['sst1005Hook']);
            $this->releaseThenDrain([$this->connection, $other], $other);
            pg_close($other);
            $this->dropPrivateSchema($schema);
        }
    }

    /**
     * Codex round 32: inside a transaction the waiting-year and block rows are locked by their id before anything of
     * them is read. Under MySQL SERIALIZABLE a plain read first takes a shared lock, and two rebuilds that both upgrade it
     * to write deadlocked. The ids come from the page check's first query or from before the transaction.
     */
    public function testInsideATransactionTheSettingsRowsAreLockedBeforeTheyAreRead(): void
    {
        self::assertNull(checkFollowingOpeningBalances(11));
        $seen = [];
        $GLOBALS['sst1005Rewrite'] = function (string $sql) use (&$seen): string {
            $seen[] = $sql;
            return $sql;
        };
        $firstTouch = function (string $name) use (&$seen): string {
            foreach ($seen as $sql) {
                if (strpos($sql, "'$name'") !== false) {
                    return $sql;
                }
            }
            return '';
        };
        // A kassekladde posting (a caller's transaction) leaves year 9 waiting.
        $this->sql('BEGIN');
        followingOpeningBalanceMarkPending(9);
        $this->sql('COMMIT');
        self::assertMatchesRegularExpression("/^select id, var_value from settings where id = '\\d+' and var_name = 'openingBalancePending' for update$/", $firstTouch('openingBalancePending'));
        self::assertSame('9', $this->setting('openingBalancePending'));
        // «Sæt aktivt» stores a block in its transaction.
        $this->postInYear9(2500, 7.5);
        $this->sql('DELETE FROM kontoplan WHERE regnskabsaar = 10 AND kontonr = 2500');
        $seen = [];
        self::assertSame($this->stop(['accounts' => [2500]]), syncFollowingOpeningBalances(9));
        $inTransaction = array_slice($seen, (int) array_search('begin', array_map('strtolower', $seen), true));
        $seen = $inTransaction;
        self::assertMatchesRegularExpression("/^select id, var_value from settings where id = '\\d+' and var_name = 'openingBalanceStop' for update$/", $firstTouch('openingBalanceStop'));
        self::assertSame($this->stop(['accounts' => [2500]]), followingOpeningBalanceStop());
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
