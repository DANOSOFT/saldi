<?php
// 20261111 CDX/NTR Verify fresh fixtures, independent ownership, retention and registry cleanup.

use PHPUnit\Framework\TestCase;

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
            // This test explicitly owns its cleanup, even when retention is enabled.
            if ($database !== null) {
                $master = TestDatabase::connect(TestDatabase::masterDb());
                TestDatabase::query($master, 'DROP DATABASE ' . pg_escape_identifier($master, $database->name()));
                TestDatabase::query($master, 'DELETE FROM regnskab WHERE db=$1', [$database->name()]);
                TestDatabase::query($master, 'DELETE FROM online WHERE db=$1 AND session_id=$2', [$database->name(), $database->sessionId()]);
                pg_close($master);
            }
            putenv($previous === false ? 'SALDI_TEST_KEEP_DATABASES' : 'SALDI_TEST_KEEP_DATABASES=' . $previous);
        }
    }
}
