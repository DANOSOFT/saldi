<?php
// 20261111 CDX/NTR Verify fresh fixtures, independent ownership, retention and registry cleanup.
// 20261111 CDX/NTR Verify class-created database identities reach individual IDE/JUnit test output.
// 20261111 CDX/NTR Verify cleanup requires retained test markers and preserves active/unmarked databases.
// 20261111 CDX/NTR Cover shared SQL fixture identities in individual IDE/JUnit results.
// 20261111 CDX/NTR Put wrapped helper-call arguments on separate lines.
// 20261111 CDX/NTR Restore retention environment even when database cleanup fails.
// 20261111 CDX/NTR Verify a failed ownership record leaves no retained routing account.

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/database/TestDatabase.php';

final class TestDatabaseLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
    }

    /** @return array<string, array{string}> */
    public static function outputCases(): array
    {
        return [
            'installed tenant' => ['DunningCharacterizationTest::test_an_invoice_overdue_beyond_the_grace_period_is_dunned'],
            'shared SQL fixture' => ['DimensionSqlFragmentTest::testNoFilterMeansNoFragment'],
        ];
    }

    #[DataProvider('outputCases')]
    public function testClassSetupIdentityAppearsInIndividualTestResultOutput(string $filter): void
    {
        require_once __DIR__ . '/CharacterizationEnv.php';
        $root = dirname(__DIR__, 3);
        $report = tempnam(sys_get_temp_dir(), 'saldi-test-output-');
        try {
            $result = CharacterizationEnv::runChild(
                $root . '/vendor/bin/phpunit',
                [
                    '--configuration', $root . '/phpunit.xml', '--teamcity', '--log-junit', $report,
                    '--filter', $filter,
                ]
            );
            self::assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);
            $xml = simplexml_load_file($report);
            self::assertNotFalse($xml);
            $cases = $xml->xpath('//testcase');
            self::assertCount(1, $cases);
            $output = (string)$cases[0]->{'system-out'};
            self::assertMatchesRegularExpression(
                '/UUID=[a-f0-9-]{36} database=t[a-f0-9]{24}/',
                $output,
                'The IDE needs the database identity attached to the individual test result'
            );
            $started = strpos($result['stdout'], '##teamcity[testStarted ');
            $finished = strpos($result['stdout'], '##teamcity[testFinished ');
            self::assertNotFalse($started);
            self::assertNotFalse($finished);
            self::assertStringContainsString(trim($output), substr($result['stdout'], $started, $finished - $started));
        } finally {
            unlink($report);
        }
    }

    public function testCleanupCommandDeletesOnlyVerifiedRetainedTestDatabases(): void
    {
        require_once __DIR__ . '/CharacterizationEnv.php';
        $root = dirname(__DIR__, 3);
        $previous = getenv('SALDI_TEST_KEEP_DATABASES');
        $legacy = getenv('SALDI_REST_KEEP_TENANT');
        $databases = [];
        $master = TestDatabase::connect(TestDatabase::masterDb());
        try {
            putenv('SALDI_TEST_KEEP_DATABASES=0');
            putenv('SALDI_REST_KEEP_TENANT=0');
            $active = $databases[] = TestDatabase::create('active-cleanup-control', false);
            $unmarked = $databases[] = TestDatabase::create('unmarked-cleanup-control', false);
            TestDatabase::query($master, 'COMMENT ON DATABASE ' . pg_escape_identifier($master, $unmarked->name()) . ' IS NULL');
            $retained = $databases[] = TestDatabase::create('retained-cleanup-target');
            putenv('SALDI_TEST_KEEP_DATABASES=1');
            $retained->release();
            putenv('SALDI_TEST_KEEP_DATABASES=0');

            $command = $root . '/tools/phpunit-databases.php';
            $listed = CharacterizationEnv::runChild($command, ['--database', $retained->name()]);
            self::assertSame(0, $listed['exit'], $listed['stdout'] . $listed['stderr']);
            self::assertStringContainsString($retained->uuid(), $listed['stdout']);
            foreach ([$active, $unmarked] as $protected) {
                $refused = CharacterizationEnv::runChild($command, ['--delete', '--database', $protected->name()]);
                self::assertNotSame(0, $refused['exit'], 'Cleanup must refuse active and unmarked databases');
            }
            $identity = TestDatabase::connect($retained->name());
            try {
                TestDatabase::query($identity, 'UPDATE public.saldi_phpunit_identity SET uuid=$1', [$active->uuid()]);
                $mismatch = CharacterizationEnv::runChild($command, ['--delete', '--database', $retained->name()]);
                self::assertNotSame(0, $mismatch['exit'], 'A database comment without matching internal identity is insufficient');
                TestDatabase::query($identity, 'UPDATE public.saldi_phpunit_identity SET uuid=$1,master_db=$2', [$retained->uuid(), 'other_test_master']);
                $otherMaster = CharacterizationEnv::runChild($command, ['--delete', '--database', $retained->name()]);
                self::assertNotSame(0, $otherMaster['exit'], 'Cleanup must preserve databases belonging to another installed master');
                TestDatabase::query($identity, 'UPDATE public.saldi_phpunit_identity SET master_db=$1', [TestDatabase::masterDb()]);
            } finally {
                pg_close($identity);
            }
            foreach ($databases as $database) {
                self::assertSame('1', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM pg_database WHERE datname=$1', [$database->name()]), 0, 0));
            }

            $deleted = CharacterizationEnv::runChild($command, ['--delete', '--database', $retained->name()]);
            self::assertSame(0, $deleted['exit'], $deleted['stdout'] . $deleted['stderr']);
            self::assertSame('0', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM pg_database WHERE datname=$1', [$retained->name()]), 0, 0));
            self::assertSame('0', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM regnskab WHERE db=$1', [$retained->name()]), 0, 0));
            self::assertSame('0', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM online WHERE db=$1', [$retained->name()]), 0, 0));
            foreach ([$active, $unmarked] as $protected) {
                self::assertSame('1', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM pg_database WHERE datname=$1', [$protected->name()]), 0, 0));
            }
        } finally {
            try {
                // Test-owned names only; also clean up when the command assertion fails.
                foreach ($databases as $database) {
                    TestDatabase::query($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $database->name()));
                    TestDatabase::query($master, 'DELETE FROM regnskab WHERE db=$1', [$database->name()]);
                    TestDatabase::query($master, 'DELETE FROM online WHERE db=$1 AND session_id=$2', [$database->name(), $database->sessionId()]);
                    $database->release();
                }
                pg_close($master);
            } finally {
                putenv($previous === false ? 'SALDI_TEST_KEEP_DATABASES' : 'SALDI_TEST_KEEP_DATABASES=' . $previous);
                putenv($legacy === false ? 'SALDI_REST_KEEP_TENANT' : 'SALDI_REST_KEEP_TENANT=' . $legacy);
            }
        }
    }

    public function testAccountRecordingFailureRemovesTheInsertedRoutingRowBeforeRetention(): void
    {
        $previous = getenv('SALDI_TEST_KEEP_DATABASES');
        $database = null;
        try {
            putenv('SALDI_TEST_KEEP_DATABASES=1');
            $database = TestDatabase::create('failed-account-recording', false);
            $tenant = TestDatabase::connect($database->name());
            try {
                TestDatabase::query($tenant, 'ALTER TABLE public.saldi_phpunit_identity ADD CHECK (cardinality(registry_ids)=0)');
            } finally {
                pg_close($tenant);
            }
            // Turn the deliberately rejected ownership write into a catchable failure.
            set_error_handler(static function ($severity, $message): never {
                throw new RuntimeException($message);
            });
            $failure = null;
            try {
                $database->registerAccount('failed_' . $database->name());
            } catch (RuntimeException $error) {
                $failure = $error;
            } finally {
                restore_error_handler();
            }
            self::assertNotNull($failure);
            self::assertStringContainsString('check constraint', $failure->getMessage());
            $database->release();
            $master = TestDatabase::connect(TestDatabase::masterDb());
            try {
                $rows = TestDatabase::query($master, 'SELECT count(*) FROM regnskab WHERE db=$1', [$database->name()]);
                self::assertSame('0', pg_fetch_result($rows, 0, 0), 'A failed registration must not leave an untracked retained routing row');
            } finally {
                pg_close($master);
            }
            $retained = RetainedTestDatabases::listing($database->name());
            self::assertCount(1, $retained);
            self::assertSame('{}', $retained[0]['registry_ids']);
        } finally {
            try {
                if ($database !== null) {
                    $database->release();
                    RetainedTestDatabases::delete($database->name());
                    $master = TestDatabase::connect(TestDatabase::masterDb());
                    try {
                        TestDatabase::query($master, 'DELETE FROM regnskab WHERE db=$1 AND regnskab=$2', [$database->name(), 'failed_' . $database->name()]);
                    } finally {
                        pg_close($master);
                    }
                }
            } finally {
                putenv($previous === false ? 'SALDI_TEST_KEEP_DATABASES' : 'SALDI_TEST_KEEP_DATABASES=' . $previous);
            }
        }
    }

    public function testSameGroupCreatesIndependentDatabasesAndReleasingOnePreservesTheOther(): void
    {
        $first = TestDatabase::create('same-group', false);
        $second = TestDatabase::create('same-group', false);
        try {
            self::assertNotSame($first->name(), $second->name());
            self::assertNotSame($first->sessionId(), $second->sessionId());
            self::assertLessThanOrEqual(25, strlen($first->name()));
            self::assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $first->uuid());
            $connection = TestDatabase::connect($second->name());
            TestDatabase::query($connection, 'CREATE TABLE marker (value integer)');
            TestDatabase::query($connection, 'INSERT INTO marker VALUES (42)');
            $first->release();
            $first->release();
            self::assertSame('42', pg_fetch_result(TestDatabase::query($connection, 'SELECT value FROM marker'), 0, 0));
            pg_close($connection);
        } finally {
            $first->release();
            $second->release();
        }
    }

    public function testFreshInstallationIncludesCurrentYearGroupsAndCleanupRemovesItsRoutingRows(): void
    {
        $database = TestDatabase::create(static::class);
        try {
            $connection = TestDatabase::connect($database->name());
            $groups = pg_fetch_all(TestDatabase::query($connection, "SELECT art FROM grupper WHERE fiscal_year=1 AND art IN ('DG','KG','SM','KM','VG') GROUP BY art ORDER BY art"));
            self::assertSame(['DG','KG','KM','SM','VG'], array_column($groups, 'art'));
            self::assertSame(date('Y'), pg_fetch_result(TestDatabase::query($connection, "SELECT box2 FROM grupper WHERE art='RA' AND kodenr=1"), 0, 0));
            pg_close($connection);
            $master = TestDatabase::connect(TestDatabase::masterDb());
            self::assertSame('1', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM online WHERE session_id=$1 AND db=$2', [$database->sessionId(), $database->name()]), 0, 0));
            $database->release();
            if (!TestDatabase::keep()) {
                self::assertSame('0', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM regnskab WHERE db=$1', [$database->name()]), 0, 0));
                self::assertSame('0', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM online WHERE session_id=$1', [$database->sessionId()]), 0, 0));
            }
            pg_close($master);
        } finally {
            $database->release();
        }
    }

    public function testKeepFlagPreservesTheDatabaseOnRelease(): void
    {
        $previous = getenv('SALDI_TEST_KEEP_DATABASES');
        $database = null;
        try {
            putenv('SALDI_TEST_KEEP_DATABASES=1');
            $database = TestDatabase::create('retention');
            $database->release();
            $connection = TestDatabase::connect($database->name());
            self::assertSame($database->name(), pg_fetch_result(TestDatabase::query($connection, 'SELECT current_database()'), 0, 0));
            pg_close($connection);
            $master = TestDatabase::connect(TestDatabase::masterDb());
            self::assertSame('1', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM regnskab WHERE db=$1', [$database->name()]), 0, 0));
            self::assertSame('1', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM online WHERE db=$1 AND session_id=$2', [$database->name(), $database->sessionId()]), 0, 0));
            pg_close($master);
        } finally {
            try {
                // This test explicitly owns its cleanup, even when retention is enabled.
                if ($database !== null) {
                    $master = TestDatabase::connect(TestDatabase::masterDb());
                    TestDatabase::query($master, 'DROP DATABASE ' . pg_escape_identifier($master, $database->name()));
                    TestDatabase::query($master, 'DELETE FROM regnskab WHERE db=$1', [$database->name()]);
                    TestDatabase::query($master, 'DELETE FROM online WHERE db=$1 AND session_id=$2', [$database->name(), $database->sessionId()]);
                    pg_close($master);
                }
            } finally {
                putenv($previous === false ? 'SALDI_TEST_KEEP_DATABASES' : 'SALDI_TEST_KEEP_DATABASES=' . $previous);
            }
        }
    }
}
