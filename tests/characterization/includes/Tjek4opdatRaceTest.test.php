<?php
// tests/characterization/includes/Tjek4opdatRaceTest.test.php
//
// Two first logins to a regnskab that was behind both ran the update chain side by side (testlab login-race, test_36).
// The one-time 4.3.0 backfills in includes/opdat_4.2.php look before they insert, so both logins passed the look and
// every delivery address and 'hoved' email went in twice. tjek4opdat() lets one request at a time go on now, to the end
// of that request (includes/betweenUpdates.php comes after the chain), and reads the version again once it has the
// lock: a login that waited for another one's update does not run it again, and goes on from where that one stopped.
//
// The database is the SQL fixture TestDatabase owns (tests/characterization/support/database/TestDatabase.php), and the
// tests skip with its reason when no test PostgreSQL is there. Every test owns a randomized schema in it and drops only
// that schema again. The other login is a PHP process of its own (support/run_tjek4opdat.php), which gets the
// fixture's name as an argument and connects as TestDatabase does; this login runs in the test's process. The update
// chain is the stand-in opdat_4_3() and opdat_5_0() in support/tjek4opdat_database.php, and a stand-in opdat_5_1()
// there that does nothing (the 5.1.1 step is Release511UpgradeTest's).
//
// The pages write the version they read to regnskab.version before they call tjek4opdat() (index/login.php,
// includes/online.php). Written outside the lock, after another login updated the regnskab and let go, it took
// regnskab.version back, and tjek4opdat() ran the update again from there (review of the first fix).
//
// History:
// 20261105 CL/LH Created.
// 20261115 CL/ASR Integration: includes/login.php is only a redirect to index/index.php now (owner's login redesign) and is no longer named as a page that writes the version; the chain's new 5.1.1 step has a stand-in in the fixture.
// 20261124 CL/ASR The tests get their database from TestDatabase (sqlFixture), so CI runs them without SALDI_CHAR_PG_DSN; the other login gets the database's name as an argument, and tearDown() lets go of this login's lock while its connection is still open, as the fixture is dropped by a shutdown function that runs before the lock's.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Tjek4opdatRaceTest extends TestCase
{
    private $observer;
    private string $database;
    private string $schema;
    private string $regnskab;
    private $otherLogin;
    private array $otherPipes = [];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        // Every connection to the fixture is a new one (PGSQL_CONNECT_FORCE_NEW): the observer, this login and the
        // other login's process.
        $this->database = TestDatabase::sqlFixture(static::class)->name();
        $this->observer = TestDatabase::connect($this->database);
        $suffix = bin2hex(random_bytes(8));
        $this->schema = 'tjek4opdat_race_' . $suffix;
        $this->regnskab = 'race_' . $suffix;
        pg_query($this->observer, 'CREATE SCHEMA ' . $this->schema);
        pg_query($this->observer, 'SET search_path TO ' . $this->schema);
        pg_query($this->observer, 'CREATE TABLE grupper (art varchar(4), box1 varchar(20))');
        pg_query($this->observer, "INSERT INTO grupper (art, box1) VALUES ('VE', '5.0.0')");
        pg_query($this->observer, 'CREATE TABLE regnskab (db varchar(64), version varchar(20))');
        pg_query_params($this->observer, "INSERT INTO regnskab (db, version) VALUES ($1, '5.0.0')", [$this->regnskab]);
        pg_query($this->observer, 'CREATE TABLE events (id serial PRIMARY KEY, what text NOT NULL)');
    }

    protected function tearDown(): void
    {
        // TestDatabase drops the fixture in a shutdown function that comes before the lock's, so a test that stopped
        // before assertLockHeldUntilTheRequestEnds() lets go of this login's lock here, while its connection is open.
        if (function_exists('tjek4opdat_frigiv')) {
            tjek4opdat_frigiv();
        }
        if (is_resource($this->otherLogin)) {
            proc_terminate($this->otherLogin);
            $this->finishOtherLogin();
        }
        if ($this->observer) {
            if (isset($this->schema)) pg_query($this->observer, 'DROP SCHEMA ' . $this->schema . ' CASCADE');
            pg_close($this->observer);
        }
    }

    public function testWaitsForTheOtherLoginToFinishInsteadOfRunningTheUpdateAgain(): void
    {
        $this->startOtherLogin('slow');
        $this->runThisLogin();

        self::assertSame(['other-login: update to 5.1.0', 'other-login: rest of the login'], $this->events(), 'This login ran the update again, or went on before the other login was done');
        self::assertSame(0, $this->finishOtherLogin());
        self::assertSame(['5.1.0', '5.1.0'], $this->versions());
        $this->assertLockHeldUntilTheRequestEnds();
    }

    public function testRunsTheUpdateItselfWhenTheOtherLoginStoppedHalfWay(): void
    {
        $this->startOtherLogin('stops');
        $this->runThisLogin();

        self::assertSame(['other-login: update to 5.1.0', 'this-login: update to 5.1.0'], $this->events());
        self::assertSame(3, $this->finishOtherLogin());
        self::assertSame(['5.1.0', '5.1.0'], $this->versions());
        $this->assertLockHeldUntilTheRequestEnds();
    }

    public function testGoesOnFromTheVersionTheOtherLoginReachedBeforeItStopped(): void
    {
        // Both logins read 4.3.0. The other one stamps 5.0.0 and stops inside the 5.1.0 step. Going on from 4.3.0 would
        // run the 5.0.0 step a second time, and such a step is not always safe twice (opdat_3.4.php's ADD saet fails).
        $this->setVersions('4.3.0');
        $this->startOtherLogin('stops', '4.3.0');
        $this->runThisLogin('4.3.0');

        self::assertSame(['other-login: update to 5.0.0', 'other-login: update to 5.1.0', 'this-login: update to 5.1.0'], $this->events(), 'This login went on from the version it read before the lock');
        self::assertSame(3, $this->finishOtherLogin());
        self::assertSame(['5.1.0', '5.1.0'], $this->versions());
        $this->assertLockHeldUntilTheRequestEnds();
    }

    public function testDoesNotRunTheUpdateWhenAnotherLoginFinishedItBeforeThisOneGotTheLock(): void
    {
        // This login read 5.0.0; the other one then ran the whole update and its request ended, lock and all.
        $this->setVersions('5.1.0');
        $this->runThisLogin();

        self::assertSame([], $this->events(), 'This login ran the update again from the version it read before the lock');
        self::assertSame(['5.1.0', '5.1.0'], $this->versions());
        $this->assertLockHeldUntilTheRequestEnds();
    }

    public function testGoesOnFromTheVersionTheOtherLoginReachedAfterThisOneReadTheVersion(): void
    {
        // The review's interleaving: both logins read 4.3.0 (index/login.php before its write of regnskab.version). The
        // other one stamps 5.0.0, stops inside the 5.1.0 step and its request ends, lock and all. Only then this one writes
        // the version it read and calls tjek4opdat(). Writing 4.3.0 then took regnskab.version back from 5.0.0, and this
        // login ran the 5.0.0 step a second time (opdat_3.4.php's ADD saet fails on a second run).
        $this->setVersions('4.3.0');
        $this->startOtherLogin('stops', '4.3.0');
        self::assertSame(3, $this->finishOtherLogin());
        $this->runThisLoginAsThePagesDo('4.3.0');

        self::assertSame(['other-login: update to 5.0.0', 'other-login: update to 5.1.0', 'this-login: update to 5.1.0'], $this->events(), 'This login went back to the version it read before the other login updated the regnskab');
        self::assertSame(['5.1.0', '5.1.0'], $this->versions());
        $this->assertLockHeldUntilTheRequestEnds();
    }

    public function testLeavesTheVersionAtTheEndWhenTheOtherLoginUpdatedAfterThisOneReadTheVersion(): void
    {
        // As above, but the other login runs the whole update from 4.3.0 to 5.1.0 before this one goes on.
        $this->setVersions('4.3.0');
        $this->startOtherLogin('quick', '4.3.0');
        self::assertSame(0, $this->finishOtherLogin());
        $this->runThisLoginAsThePagesDo('4.3.0');

        self::assertSame(['other-login: update to 5.0.0', 'other-login: update to 5.1.0', 'other-login: rest of the login'], $this->events(), 'This login ran the update again after it wrote back the version it read');
        self::assertSame(['5.1.0', '5.1.0'], $this->versions());
        $this->assertLockHeldUntilTheRequestEnds();
    }

    public function testWaitsAsThePagesDoForTheOtherLoginToFinish(): void
    {
        // The pages' write comes before tjek4opdat(): it must not take the version back while the other login is updating.
        $this->setVersions('4.3.0');
        $this->startOtherLogin('slow', '4.3.0');
        $this->runThisLoginAsThePagesDo('4.3.0');

        self::assertSame(['other-login: update to 5.0.0', 'other-login: update to 5.1.0', 'other-login: rest of the login'], $this->events(), 'This login ran the update again, or went on before the other login was done');
        self::assertSame(0, $this->finishOtherLogin());
        self::assertSame(['5.1.0', '5.1.0'], $this->versions());
        $this->assertLockHeldUntilTheRequestEnds();
    }

    public function testBringsRegnskabVersionDownToTheTenantUnderTheLock(): void
    {
        // A tenant behind what master says (a restored backup): online.php reads 4.3.0 in grupper below regnskab.version
        // 5.1.0 and writes 4.3.0 down before the update, as it did before the lock.
        pg_query($this->observer, "UPDATE grupper SET box1 = '4.3.0' WHERE art = 'VE'");
        pg_query_params($this->observer, "UPDATE regnskab SET version = '5.1.0' WHERE db = $1", [$this->regnskab]);
        $this->runThisLoginAsThePagesDo('4.3.0');

        self::assertSame(['this-login: update to 5.0.0', 'this-login: update to 5.1.0'], $this->events());
        self::assertSame(['5.1.0', '5.1.0'], $this->versions());
        $this->assertLockHeldUntilTheRequestEnds();
    }

    public function testRunsTheUpdateOnceWhenNoOtherLoginIsUpdating(): void
    {
        $this->runThisLogin();

        self::assertSame(['this-login: update to 5.1.0'], $this->events());
        self::assertSame(['5.1.0', '5.1.0'], $this->versions());
        $this->assertLockHeldUntilTheRequestEnds();
    }

    /** Start the other login and return once it is inside the update chain, a second before it is done. */
    private function startOtherLogin(string $step, string $dbver = '5.0.0'): void
    {
        $command = [PHP_BINARY, __DIR__ . '/support/run_tjek4opdat.php', $this->database, $this->schema, $this->regnskab, 'other-login', $step, $dbver];
        $this->otherLogin = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this->otherPipes);
        self::assertIsResource($this->otherLogin);
        $deadline = microtime(true) + 10;
        while ($this->events() === []) {
            if (!proc_get_status($this->otherLogin)['running'] || microtime(true) > $deadline) {
                proc_terminate($this->otherLogin);
                $error = stream_get_contents($this->otherPipes[2]);
                self::fail('The other login never reached the update chain: ' . $error);
            }
            usleep(20000);
        }
    }

    /** This login, which read $dbver before it called tjek4opdat(). */
    private function runThisLogin(string $dbver = '5.0.0'): void
    {
        $this->connectThisLogin();
        tjek4opdat($dbver, '5.1.0');
    }

    /** This login as the pages go on after they read $dbver: write it to regnskab.version, then call tjek4opdat(). */
    private function runThisLoginAsThePagesDo(string $dbver): void
    {
        $this->connectThisLogin();
        tjek4opdat_skriv_version($dbver);
        tjek4opdat($dbver, '5.1.0');
    }

    private function connectThisLogin(): void
    {
        require_once __DIR__ . '/support/tjek4opdat_database.php';
        tjekFixtureLogin($this->database, $this->schema, $this->regnskab, 'this-login', 'quick');
        require_once dirname(__DIR__, 3) . '/includes/tjek4opdat.php';
        chdir(dirname(__DIR__, 3) . '/index');
    }

    private function setVersions(string $version): void
    {
        pg_query_params($this->observer, "UPDATE grupper SET box1 = $1 WHERE art = 'VE'", [$version]);
        pg_query_params($this->observer, 'UPDATE regnskab SET version = $1 WHERE db = $2', [$version, $this->regnskab]);
    }

    private function finishOtherLogin(): int
    {
        foreach ($this->otherPipes as $pipe) {
            stream_get_contents($pipe);
            fclose($pipe);
        }
        $this->otherPipes = [];
        $exitCode = proc_close($this->otherLogin);
        $this->otherLogin = null;
        return $exitCode;
    }

    private function events(): array
    {
        $result = pg_query($this->observer, 'SELECT what FROM events ORDER BY id');
        return array_column(pg_fetch_all($result) ?: [], 'what');
    }

    /** grupper VE box1 and regnskab.version: both are stamped at the end of the chain. */
    private function versions(): array
    {
        $result = pg_query_params($this->observer, "SELECT (SELECT box1 FROM grupper WHERE art = 'VE'), (SELECT version FROM regnskab WHERE db = $1)", [$this->regnskab]);
        return array_values(pg_fetch_row($result));
    }

    /** The lock stays until the shutdown function of this login's request releases it, so betweenUpdates.php runs under it too. */
    private function assertLockHeldUntilTheRequestEnds(): void
    {
        self::assertSame(1, $this->locksHeldByThisLogin(), 'This login let go of the lock before its request ended');
        tjek4opdat_frigiv();
        self::assertSame(0, $this->locksHeldByThisLogin(), 'This login kept its lock after its request ended');
    }

    private function locksHeldByThisLogin(): int
    {
        $result = pg_query($this->observer, "SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND granted AND pid = " . (int) pg_get_pid($GLOBALS['connection']));
        return (int) pg_fetch_result($result, 0, 0);
    }
}
