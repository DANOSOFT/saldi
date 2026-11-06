<?php
// tests/characterization/includes/Tjek4opdatLockTest.test.php
//
// How tjek4opdat() takes its lock on MySQL/MariaDB, and that the update chain does not run without it (testlab
// login-race, test_36). MariaDB answers GET_LOCK(name, -1) with NULL at once instead of waiting, so a waiting login went
// on beside the other one; GET_LOCK() is now asked again every 30 seconds until it gives the lock. CI has no MySQL, so the
// database answers are scripted (support/tjek4opdat_stub_database.php); Tjek4opdatRaceTest runs two logins on PostgreSQL.
//
// The pages write regnskab.version only through tjek4opdat_skriv_version(), which writes under the same lock: written
// outside it, a version read before another login updated the regnskab took regnskab.version back (review of the first fix).
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Tjek4opdatLockTest extends TestCase
{
    public function testAsksGetLockAgainEvery30SecondsUntilItGetsTheLock(): void
    {
        // Another login holds the lock for two rounds and then is done: the regnskab is at 5.1.0.
        $this->runLogin('mysqli', ['0', '0', '1'], '5.1.0');

        $lockQueries = array_values(array_filter($GLOBALS['tjekQueries'], static fn ($sql) => strpos($sql, 'GET_LOCK(') !== false));
        self::assertSame(array_fill(0, 3, "SELECT GET_LOCK(CONCAT('saldi:tjek4opdat:', MD5('tenant_1')), 30) AS laast"), $lockQueries);
        self::assertSame([], $GLOBALS['tjekChain'], 'This login ran the update the other login had done');

        tjek4opdat_frigiv();
        self::assertSame("SELECT RELEASE_LOCK(CONCAT('saldi:tjek4opdat:', MD5('tenant_1')))", end($GLOBALS['tjekQueries']));
    }

    public function testRunsTheUpdateOnceItHasTheLock(): void
    {
        $this->runLogin('mysqli', ['1'], '5.0.0');

        self::assertSame(['update to 5.1.0'], $GLOBALS['tjekChain']);
    }

    public function testDoesNotRunTheUpdateWhenGetLockFails(): void
    {
        $this->assertNoUpdateWithoutTheLock('mysqli', [null]);
    }

    public function testDoesNotRunTheUpdateWhenPgAdvisoryLockFails(): void
    {
        $this->assertNoUpdateWithoutTheLock('postgresql', [false]);
    }

    public function testLeavesRegnskabVersionAloneWithoutTheLockWhenItAlreadySaysWhatThePageRead(): void
    {
        // The usual login of a regnskab that is up to date: no write, and no wait for another login's lock.
        $this->writeAsThePagesDo('postgresql', [], '5.1.0', '5.1.0');

        self::assertSame(["SELECT version FROM regnskab WHERE db = 'tenant_1'"], $GLOBALS['tjekQueries']);
    }

    public function testReadsTheVersionAgainUnderTheLockInsteadOfWritingWhatThePageReadBeforeIt(): void
    {
        // The page read 4.3.0; another login has since updated the regnskab to 5.1.0 and let go of the lock.
        $this->writeAsThePagesDo('postgresql', [true], '5.1.0', '4.3.0');

        self::assertSame([
            "SELECT version FROM regnskab WHERE db = 'tenant_1'",
            "SELECT pg_advisory_lock(hashtext('saldi:tjek4opdat:tenant_1'))",
            "SELECT version FROM regnskab WHERE db = 'tenant_1'",
            "SELECT box1 FROM grupper WHERE art = 'VE'",
        ], $GLOBALS['tjekQueries'], 'The version the page read was written, or written outside the lock');
    }

    public function testThePagesWriteRegnskabVersionOnlyThroughTjek4opdatSkrivVersion(): void
    {
        $root = dirname(__DIR__, 3);
        foreach (['index/login.php', 'includes/login.php', 'includes/online.php'] as $page) {
            $source = file_get_contents($root . '/' . $page);
            self::assertDoesNotMatchRegularExpression('/update\s+regnskab\s+set\s+version/i', $source, "$page writes regnskab.version outside the update lock");
            self::assertStringContainsString('tjek4opdat_skriv_version(', $source, "$page no longer writes the version it read");
        }
    }

    /** A page on $dbType whose read gave $dbver, while the database holds $version, as it goes on to write the version. */
    private function writeAsThePagesDo(string $dbType, array $lockAnswers, string $version, string $dbver): void
    {
        require_once __DIR__ . '/support/tjek4opdat_stub_database.php';
        tjekStubLogin($dbType, $lockAnswers, $version);
        require_once dirname(__DIR__, 3) . '/includes/tjek4opdat.php';
        tjek4opdat_skriv_version($dbver);
    }

    /** A login on $dbType that read 5.0.0 and gets $lockAnswers from the database. */
    private function runLogin(string $dbType, array $lockAnswers, string $version): void
    {
        require_once __DIR__ . '/support/tjek4opdat_stub_database.php';
        tjekStubLogin($dbType, $lockAnswers, $version);
        require_once dirname(__DIR__, 3) . '/includes/tjek4opdat.php';
        // The chain includes its steps as ../includes/opdat_*.php, like the pages in index/ do.
        chdir(dirname(__DIR__, 3) . '/index');
        tjek4opdat('5.0.0', '5.1.0');
    }

    private function assertNoUpdateWithoutTheLock(string $dbType, array $lockAnswers): void
    {
        $error = null;
        try {
            $this->runLogin($dbType, $lockAnswers, '5.0.0');
        } catch (RuntimeException $caught) {
            $error = $caught;
        }
        self::assertSame([], $GLOBALS['tjekChain'], 'The update ran without the lock');
        self::assertCount(1, $GLOBALS['tjekQueries'], 'tjek4opdat() went on to other queries without the lock');
        self::assertInstanceOf(RuntimeException::class, $error, 'tjek4opdat() did not stop the request without the lock');
        self::assertSame('Could not acquire the update lock, so the update did not run.', $error->getMessage());
    }
}
