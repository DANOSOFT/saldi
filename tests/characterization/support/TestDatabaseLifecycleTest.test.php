<?php
// 20261111 CDX/NTR Verify fresh fixtures, independent ownership, retention and registry cleanup.
// 20261111 CDX/NTR Verify class-created database identities reach individual IDE/JUnit test output.
// 20261111 CDX/NTR Verify cleanup requires retained test markers and preserves active/unmarked databases.
// 20261111 CDX/NTR Cover shared SQL fixture identities in individual IDE/JUnit results.
// 20261111 CDX/NTR Put wrapped helper-call arguments on separate lines.
// 20261111 CDX/NTR Restore retention environment even when database cleanup fails.
// 20261111 CDX/NTR Verify a failed ownership record leaves no retained routing account.
// 20261113 CDX/NTR Require registry last-activity values to use the Unix seconds expected by admin pages.
// 20261120 CDX/NTR Check database identity output only appears when retention is enabled.
// 20261120 CDX/NTR Require routing-account creation and cleanup to leave normal IDs untouched.
// 20261120 CDX/NTR Keep the simple child-process helper call on one line.
// 20261111 CL/SZ MB-154 Test databases are named t + 22 hex + _1, like a <master>_<regnskab id> tenant, so api/rest_api.php does not take them for the master.
// 20261122 CL/SZ MB-154 The cleanup of retained databases matches the t + 22 hex + _1 name.

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

    /** @return array<string, array{string, string, string, bool}> */
    public static function outputCases(): array
    {
        $cases = [];
        foreach ([
            'installed tenant' => 'TestDatabaseOutputFixture::testConnectionUsesOwnedDatabase',
            'shared SQL fixture' => 'DimensionSqlFragmentTest::testNoFilterMeansNoFragment',
        ] as $fixture => $filter) {
            $cases[$fixture . ' discarded'] = [$filter, '0', '0', false];
            $cases[$fixture . ' retained'] = [$filter, 'yes', '0', true];
            $cases[$fixture . ' retained by legacy flag'] = [$filter, '0', 'yes', true];
        }
        return $cases;
    }

    #[DataProvider('outputCases')]
    public function testClassSetupIdentityOutputRequiresRetention(string $filter, string $keep, string $legacyKeep, bool $retained): void
    {
        require_once __DIR__ . '/CharacterizationEnv.php';
        $root = dirname(__DIR__, 3);
        $report = tempnam(sys_get_temp_dir(), 'saldi-test-output-');
        $previous = getenv('SALDI_TEST_KEEP_DATABASES');
        $legacy = getenv('SALDI_REST_KEEP_TENANT');
        $result = ['stdout' => ''];
        try {
            putenv('SALDI_TEST_KEEP_DATABASES=' . $keep);
            putenv('SALDI_REST_KEEP_TENANT=' . $legacyKeep);
            $arguments = [
                '--configuration', $root . '/phpunit.xml', '--teamcity', '--log-junit', $report,
                '--filter', $filter,
            ];
            if (str_starts_with($filter, 'TestDatabaseOutputFixture::')) {
                $arguments[] = __DIR__ . '/database/TestDatabaseOutputFixture.php';
            }
            $result = CharacterizationEnv::runChild($root . '/vendor/bin/phpunit', $arguments);
            self::assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);
            $xml = simplexml_load_file($report);
            self::assertNotFalse($xml);
            $cases = $xml->xpath('//testcase');
            self::assertCount(1, $cases);
            $output = (string)$cases[0]->{'system-out'};
            if (!$retained) {
                self::assertSame('', $output, 'Discarded database identities must not appear in JUnit output');
                self::assertStringNotContainsString('[test database]', $result['stdout'] . $result['stderr']);
                return;
            }
            self::assertMatchesRegularExpression(
                '/UUID=[a-f0-9-]{36} database=t[a-f0-9]{22}_1/',
                $output,
                'The IDE needs the database identity attached to the individual test result'
            );
            $started = strpos($result['stdout'], '##teamcity[testStarted ');
            $finished = strpos($result['stdout'], '##teamcity[testFinished ');
            self::assertNotFalse($started);
            self::assertNotFalse($finished);
            self::assertStringContainsString(trim($output), substr($result['stdout'], $started, $finished - $started));
            self::assertStringContainsString('keep=yes', $output);
        } finally {
            try {
                // Only databases named by this child process are eligible for cleanup.
                preg_match_all('/database=(t[a-f0-9]{22}_1)/', $result['stdout'], $matches);
                foreach (array_unique($matches[1]) as $name) {
                    foreach (RetainedTestDatabases::listing($name) as $identity) {
                        RetainedTestDatabases::delete($identity['database']);
                    }
                }
            } finally {
                unlink($report);
                putenv($previous === false ? 'SALDI_TEST_KEEP_DATABASES' : 'SALDI_TEST_KEEP_DATABASES=' . $previous);
                putenv($legacy === false ? 'SALDI_REST_KEEP_TENANT' : 'SALDI_REST_KEEP_TENANT=' . $legacy);
            }
        }
    }

    /** @return array<string, array{bool}> */
    public static function retentionCases(): array
    {
        return ['discarded' => [false], 'retained' => [true]];
    }

    #[DataProvider('retentionCases')]
    public function testRoutingAccountsLeaveTheNormalRegistryIdsUntouched(bool $keep): void
    {
        $previous = getenv('SALDI_TEST_KEEP_DATABASES');
        $legacy = getenv('SALDI_REST_KEEP_TENANT');
        $databases = [];
        $master = TestDatabase::connect(TestDatabase::masterDb());
        $sequence = self::registrySequenceState($master);
        $maximum = pg_fetch_result(TestDatabase::query($master, 'SELECT MAX(id) FROM regnskab'), 0, 0);
        try {
            putenv('SALDI_TEST_KEEP_DATABASES=' . ($keep ? 'yes' : 'no'));
            putenv('SALDI_REST_KEEP_TENANT=0');
            $first = $databases[] = TestDatabase::create('registry-first');
            $second = $databases[] = TestDatabase::create('registry-second', false);
            $openId = $second->registerAccount('open_' . $second->name());
            $closedId = $second->registerAccount('closed_' . $second->name(), true);

            self::assertSame($sequence, self::registrySequenceState($master), 'Creating test accounts must not consume normal registry IDs');
            self::assertSame($maximum, pg_fetch_result(TestDatabase::query($master, 'SELECT MAX(id) FROM regnskab'), 0, 0));
            self::assertLessThan(0, $openId);
            self::assertLessThan(0, $closedId);
            self::assertNotSame($openId, $closedId);
            self::assertSame(
                [['id' => (string)$openId, 'lukket' => ''], ['id' => (string)$closedId, 'lukket' => 'on']],
                pg_fetch_all(TestDatabase::query($master, 'SELECT id,lukket FROM regnskab WHERE db=$1 ORDER BY id DESC', [$second->name()]))
            );
            $first->release();
            if ($keep) {
                RetainedTestDatabases::delete($first->name());
            }
            self::assertSame('2', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM regnskab WHERE db=$1', [$second->name()]), 0, 0));
            $second->release();
            if ($keep) {
                RetainedTestDatabases::delete($second->name());
            }
            self::assertSame('0', pg_fetch_result(TestDatabase::query($master, 'SELECT count(*) FROM regnskab WHERE id=ANY($1::integer[])', ['{' . $openId . ',' . $closedId . '}']), 0, 0));
            self::assertSame($sequence, self::registrySequenceState($master), 'Cleanup must leave the registry sequence unchanged');
        } finally {
            try {
                foreach ($databases as $database) {
                    $database->release();
                    foreach (RetainedTestDatabases::listing($database->name()) as $identity) {
                        RetainedTestDatabases::delete($identity['database']);
                    }
                }
            } finally {
                pg_close($master);
                putenv($previous === false ? 'SALDI_TEST_KEEP_DATABASES' : 'SALDI_TEST_KEEP_DATABASES=' . $previous);
                putenv($legacy === false ? 'SALDI_REST_KEEP_TENANT' : 'SALDI_REST_KEEP_TENANT=' . $legacy);
            }
        }
    }

    /** @return array{last_value:string, is_called:string} Normal registry ID allocation state. */
    private static function registrySequenceState(PgSql\Connection $master): array
    {
        $sequence = pg_fetch_result(TestDatabase::query($master, "SELECT pg_get_serial_sequence('regnskab','id')"), 0, 0);
        return pg_fetch_assoc(TestDatabase::query($master, 'SELECT last_value,is_called FROM ' . $sequence));
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
        $sequenceMaster = TestDatabase::connect(TestDatabase::masterDb());
        $sequence = self::registrySequenceState($sequenceMaster);
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
            self::assertSame($sequence, self::registrySequenceState($sequenceMaster), 'Failed registrations must not consume normal registry IDs');
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
                pg_close($sequenceMaster);
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
            $lastActivity = pg_fetch_result(TestDatabase::query($master, 'SELECT sidst FROM regnskab WHERE db=$1', [$database->name()]), 0, 0);
            self::assertMatchesRegularExpression('/^[0-9]+$/', $lastActivity);
            self::assertSame(date('d-m-Y'), date('d-m-Y', $lastActivity));
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
