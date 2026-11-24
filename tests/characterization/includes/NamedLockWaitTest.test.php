<?php
// tests/characterization/includes/NamedLockWaitTest.test.php
//
// How the opening-balance carry's lock (includes/updateFollowingOpeningBalances.php) and the journal save lock
// (finans/kassekladde_includes/journalSaveLock.php) are taken on MySQL and MariaDB (p1-yearend-F3, MB-81). Both asked
// GET_LOCK(name, -1), which waits without end on MySQL, but which MariaDB answers at once with NULL and no lock: on
// MariaDB every Gem on a year card, every Opret after the first year, every POS close with an earlier-year sale and every
// journal save that takes its lock failed, on every try. They now ask GET_LOCK(name, 30) through saldiNamedLockWait()
// (includes/stdFunc/namedLockWait.php), again while another session holds the lock, for at most 4 rounds (2 minutes).
//
// CI has no MySQL or MariaDB. The decision is proven against what those servers answer: GET_LOCK() gives 1 (the lock),
// 0 (another session still held it when the timeout ran out) or NULL (an error, or a negative timeout on MariaDB). First
// saldiNamedLockWait() itself with a stand-in query; then the real callers in a child process
// (support/named_lock_runner.php) against a stand-in mysqli that answers as MySQL or MariaDB does. PostgreSQL's advisory
// locks are untouched; PosTenderDayCloseTest and JournalOpeningCarryLockTest run them.
//
// The carry's lock is released on the connection that took it, which is kept until then: finans/bogfor.php and the year card
// hold it through their recalculations after the commit, and genberegn() connects to the master database and back, which on
// MySQL and MariaDB opens a new connection to the company database (p1-pos-close-N1).
//
// History:
// 20261119 CL/ASR Created (p1-yearend-F3, MB-81).
// 20261120 CL/ASR The lock outlives a new connection to the company database and is released on its own (p1-pos-close-N1).

use PHPUnit\Framework\TestCase;

final class NamedLockWaitTest extends TestCase
{
    private const CARRY_LOCK = "select get_lock(concat('saldi:opening_carry:', md5(database())), %d) as locked";

    /** The carry's first try inside a transaction, without a wait. */
    private const CARRY_TRY = "select get_lock(concat('saldi:opening_carry:', md5(database())), 0)";

    private const JOURNAL_LOCK = "select get_lock(CONCAT('saldi:kassekladde:', MD5(DATABASE()), ':', 7), %d) as locked";

    private const RETRY = 'Intet er gemt: primo i de følgende regnskabsår kunne ikke låses mod samtidige opdateringer fra andre brugere. Prøv igen.';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/stdFunc/namedLockWait.php';
    }

    /**
     * saldiNamedLockWait() with a stand-in for the database that gives $answers, one per GET_LOCK().
     *
     * @param array<int, mixed> $answers
     * @return array{0: string, 1: list<string>} The outcome and the statements sent.
     */
    private static function wait(array $answers): array
    {
        $queries = [];
        $outcome = saldiNamedLockWait(static function (string $sql) use (&$queries, &$answers) {
            $queries[] = $sql;
            return array_shift($answers);
        }, "concat('x:', md5(database()))");
        return [$outcome, $queries];
    }

    public function testAFreeLockIsTakenWithOneGetLockOf30Seconds(): void
    {
        self::assertSame(['locked', ["select get_lock(concat('x:', md5(database())), 30) as locked"]], self::wait(['1']));
    }

    public function testWhileAnotherSessionHoldsTheLockItIsAskedForAgainUntilItIsGiven(): void
    {
        // MySQL and MariaDB answer 0 when the 30 seconds ran out while another session held the lock.
        [$outcome, $queries] = self::wait(['0', '0', '1']);

        self::assertSame('locked', $outcome);
        self::assertSame(array_fill(0, 3, "select get_lock(concat('x:', md5(database())), 30) as locked"), $queries);
    }

    public function testTheWaitEndsAfterFourRoundsOf30Seconds(): void
    {
        // Another session holds the lock for longer than 2 minutes: no fifth GET_LOCK().
        [$outcome, $queries] = self::wait(['0', '0', '0', '0', '1']);

        self::assertSame('timeout', $outcome);
        self::assertCount(4, $queries);
        self::assertSame('2 minutter', saldiNamedLockWaitText());
    }

    public function testNullIsAnErrorThatIsNotAskedAgain(): void
    {
        // NULL is what MariaDB answered GET_LOCK(name, -1) with, and what both answer on an error.
        self::assertSame(['error', ["select get_lock(concat('x:', md5(database())), 30) as locked"]], self::wait([null]));
    }

    public function testNumbersCountAsTheirStringsAndAnythingElseIsAnError(): void
    {
        self::assertSame('locked', self::wait([0, 1])[0], 'an integer answer (MYSQLI_OPT_INT_AND_FLOAT_NATIVE)');
        self::assertSame('error', self::wait(['2'])[0]);
        self::assertSame('error', self::wait([''])[0]);
    }

    public function testTheCarrysLockBeforeTheTransactionIsTakenOnMariaDb(): void
    {
        // Gem on a year card, Opret, a POS close with an earlier-year sale and a journal into a year with a following
        // year, while nobody else holds the lock. Before: GET_LOCK(name, -1) gave NULL and the lock was refused.
        $run = self::lockRun('mariadb', 'carry-before', []);

        self::assertNull($run['exception'], (string) $run['message']);
        self::assertTrue($run['held']);
        self::assertSame([sprintf(self::CARRY_LOCK, 30)], $run['queries']);
    }

    public function testTheCarrysLockBeforeTheTransactionWaitsInRoundsOf30SecondsOnMySql(): void
    {
        $run = self::lockRun('mysql', 'carry-before', ['0', '0', '1']);

        self::assertNull($run['exception'], (string) $run['message']);
        self::assertTrue($run['held']);
        self::assertSame(array_fill(0, 3, sprintf(self::CARRY_LOCK, 30)), $run['queries']);
    }

    public function testTheCarrysLockHeldByAnotherSessionForMoreThan2MinutesIsATimeout(): void
    {
        // The POS day close catches OpeningCarryConflict (its retry text is unchanged); OpeningCarryLockTimeout is one.
        $run = self::lockRun('mariadb', 'carry-before', ['0', '0', '0', '0']);

        self::assertSame('OpeningCarryLockTimeout', $run['exception']);
        self::assertFalse($run['held']);
        self::assertSame(array_fill(0, 4, sprintf(self::CARRY_LOCK, 30)), $run['queries']);
    }

    public function testTheCarrysLockAnsweredWithNullIsRefusedAtOnce(): void
    {
        $run = self::lockRun('mysql', 'carry-before', [null]);

        self::assertSame('OpeningCarryConflict', $run['exception']);
        self::assertFalse($run['held']);
        self::assertSame([sprintf(self::CARRY_LOCK, 30)], $run['queries']);
    }

    public function testTheYearCardSaysItWaited2MinutesWhenTheLockIsNotGiven(): void
    {
        // The year card and Opret do not catch the conflict: openingCarryConflictExit() rolls back, alerts and ends.
        $run = self::lockRun('mysql', 'carry-before-exit', ['0', '0', '0', '0']);

        self::assertTrue($run['exited']);
        self::assertFalse($run['held']);
        self::assertSame(['rollback'], $run['transaktion']);
        self::assertSame([self::RETRY . ' Der blev ventet 2 minutter på, at en anden bruger blev færdig med at bogføre i et tidligere regnskabsår, '
            . 'godkende en kasseoptælling, gemme en åbningsbalance eller oprette et regnskabsår.'], $run['alerts']);

        $refused = self::lockRun('mysql', 'carry-before-exit', [null]);
        self::assertSame([self::RETRY], $refused['alerts'], 'a refused lock does not say it waited');
    }

    public function testTheCarrysLockInsideATransactionThatHadToWaitIsRefusedOnMariaDbToo(): void
    {
        // The carry's own lock (a caller without the lock before its transaction): free, it is taken with GET_LOCK(name, 0).
        $free = self::lockRun('mariadb', 'carry-in-transaction', []);
        self::assertNull($free['exception'], (string) $free['message']);
        self::assertTrue($free['held']);
        self::assertSame([self::CARRY_TRY], $free['queries']);

        // Held by another session: it waits, then refuses, because the transaction's snapshot is older than the other
        // session's carry, and lets go of the lock. Before: GET_LOCK(name, -1) gave NULL on MariaDB, refused as a lock error.
        $waited = self::lockRun('mariadb', 'carry-in-transaction', ['0', '0', '1']);
        self::assertSame('OpeningCarryConflict', $waited['exception']);
        self::assertStringContainsString("committed after this transaction's snapshot", (string) $waited['message']);
        self::assertFalse($waited['held']);
        self::assertSame([self::CARRY_TRY, sprintf(self::CARRY_LOCK, 30), sprintf(self::CARRY_LOCK, 30),
            "select release_lock(concat('saldi:opening_carry:', md5(database())))"], $waited['queries']);
    }

    public function testTheCarrysLockIsKeptAndReleasedOnItsOwnConnectionWhenThePageConnectsAgain(): void
    {
        // A journal or a year card holds the lock until it has recalculated the year after its commit, and genberegn() then
        // connects to the master database and back. On MySQL and MariaDB that is a new connection to the company database:
        // the one that held the named lock was closed with it, so the lock was gone before the recalculation that followed,
        // and the release went to the new connection, which holds nothing.
        $run = self::lockRun('mariadb', 'carry-before-reconnect', []);

        self::assertNull($run['exception'], (string) $run['message']);
        self::assertFalse($run['held']);
        self::assertSame([
            'first: ' . sprintf(self::CARRY_LOCK, 30),
            "first: select release_lock(concat('saldi:opening_carry:', md5(database())))",
            'closed first',
        ], $run['events'], 'the lock\'s connection stays open until the lock is released on it');
    }

    public function testTheJournalSaveLockIsTakenOnMariaDb(): void
    {
        // MB-81: every save of a journal takes it. Before: GET_LOCK(name, -1) gave NULL on MariaDB, and the save was refused.
        $run = self::lockRun('mariadb', 'journal-save', []);

        self::assertFalse($run['exited'], implode(' ', $run['alerts']));
        self::assertSame([], $run['alerts']);
        self::assertTrue($run['held']);
        self::assertSame([sprintf(self::JOURNAL_LOCK, 30)], $run['queries']);

        $contended = self::lockRun('mysql', 'journal-save', ['0', '1']);
        self::assertTrue($contended['held']);
        self::assertSame(array_fill(0, 2, sprintf(self::JOURNAL_LOCK, 30)), $contended['queries']);
    }

    public function testTheJournalSaveLockStopsTheSaveWhenItIsNotGiven(): void
    {
        foreach (['held for more than 2 minutes' => ['0', '0', '0', '0'], 'NULL' => [null]] as $case => $answers) {
            $run = self::lockRun('mysql', 'journal-save', $answers);

            self::assertTrue($run['exited'], $case);
            self::assertFalse($run['held'], $case);
            self::assertSame(['Kladden kunne ikke låses, så intet er gemt. Prøv igen.'], $run['alerts'], $case);
            self::assertCount(count($answers), $run['queries'], $case);
        }
    }

    /**
     * Run support/named_lock_runner.php.
     *
     * @param array<int, mixed> $answers What GET_LOCK() with a timeout of 0 or more answers, one per call.
     * @return array{queries: list<string>, alerts: list<string>, transaktion: list<string>, exception: ?string, message: ?string, exited: bool, held: bool}
     */
    private static function lockRun(string $engine, string $what, array $answers): array
    {
        $errors = tmpfile();
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/support/named_lock_runner.php', $engine, $what, json_encode($answers)],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => $errors],
            $pipes
        );
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($process);
        rewind($errors);
        $stderr = (string) stream_get_contents($errors);
        fclose($errors);
        self::assertSame(0, $exit, $stderr . $stdout);
        $lines = preg_split('/\n/', trim($stdout));
        $run = json_decode((string) end($lines), true);
        self::assertIsArray($run, $stderr . $stdout);
        if (isset($run['skip'])) {
            self::markTestSkipped($run['skip']);
        }
        return $run;
    }
}
