<?php
// 20261023 CL/NTR Cover the tekster.tekst_key column + idx_tekster_sprog_key migration in
//                  betweenUpdates.php: Postgres idempotency, the MySQL GET_LOCK/recheck idiom,
//                  lock-acquisition failure, concurrent creation and DDL-error lock release.
// 20261028 CDX/NTR Expect the index lock when an existing column still needs its index.
// 20261028 CDX/NTR Cover the same migration running against the master database.
// 20261029 CL/LH Stop the eval at the end of this migration, so blocks appended after it in betweenUpdates.php are not run.

namespace Saldi\Tests\TekstKeyMigration;

use PHPUnit\Framework\TestCase;

function db_select($sql, $location, $global = false)
{
    $state = $GLOBALS['tekstKeyMigration'];
    $state->queries[] = $sql;
    $state->queryTargets[] = ['sql' => $sql, 'global' => $global];
    if (strpos($sql, 'GET_LOCK(') !== false) {
        // A rival login may add the column while this connection waits on the lock.
        if (!$global && $state->rivalCreatesColumn) {
            $state->columnExists = true;
        }
        return ['acquired' => $state->lockResult];
    }
    if (strpos($sql, 'RELEASE_LOCK(') !== false) {
        if ($global) {
            $state->masterReleased = true;
        } else {
            $state->released = true;
        }
        return [];
    }
    if (strpos($sql, 'information_schema.statistics') !== false) {
        $exists = $global ? $state->masterIndexExists : $state->indexExists;
        return $exists ? ['index_name' => 'idx_tekster_sprog_key'] : false;
    }
    // information_schema.columns existence probe for tekst_key.
    $exists = $global ? $state->masterColumnExists : $state->columnExists;
    return $exists ? ['column_name' => 'tekst_key'] : false;
}

function db_fetch_array($result)
{
    return $result;
}

function db_modify($sql, $location, $global = false)
{
    $state = $GLOBALS['tekstKeyMigration'];
    $state->writes[] = $sql;
    $state->writeTargets[] = ['sql' => $sql, 'global' => $global];
    if ($state->writeError !== null) {
        throw $state->writeError;
    }
    if (strpos($sql, 'ALTER TABLE') !== false) {
        if ($global) {
            $state->masterColumnExists = true;
        } else {
            $state->columnExists = true;
        }
    } elseif (strpos($sql, 'CREATE INDEX') !== false) {
        if ($global) {
            $state->masterIndexExists = true;
        } else {
            $state->indexExists = true;
        }
    }
}

final class TekstKeyMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['db'] = 'tenant';
        $GLOBALS['sqdb'] = 'tenant';
        $GLOBALS['tekstKeyMigration'] = (object) [
            'columnExists' => false,
            'indexExists' => false,
            'masterColumnExists' => false,
            'masterIndexExists' => false,
            'rivalCreatesColumn' => false,
            'lockResult' => '1',
            'released' => false,
            'masterReleased' => false,
            'writeError' => null,
            'queries' => [],
            'queryTargets' => [],
            'writes' => [],
            'writeTargets' => [],
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['db'], $GLOBALS['sqdb'], $GLOBALS['tekstKeyMigration']);
    }

    public function testPostgresAddsColumnAndIndexIdempotently(): void
    {
        $this->migrate('postgresql');
        $this->migrate('postgresql');
        $state = $GLOBALS['tekstKeyMigration'];
        self::assertTrue($state->columnExists);
        self::assertTrue($state->indexExists);
        // The column is only altered on the first run; both runs issue the idempotent index DDL.
        $alters = array_filter($state->writes, static fn ($w) => strpos($w, 'ALTER TABLE') !== false);
        self::assertCount(1, $alters);
        self::assertStringContainsString('ADD COLUMN IF NOT EXISTS', reset($alters));
        self::assertStringContainsString('CREATE INDEX IF NOT EXISTS idx_tekster_sprog_key', $state->writes[1]);
        self::assertStringContainsString('current_schema()', $state->queries[0]);
        foreach ($state->queries as $q) {
            self::assertStringNotContainsString('GET_LOCK(', $q);
        }
    }

    public function testMysqlAddsColumnUnderLockThenCreatesIndex(): void
    {
        foreach (['mysql', 'mysqli'] as $driver) {
            $this->setUp();
            $this->migrate($driver);
            $state = $GLOBALS['tekstKeyMigration'];
            self::assertTrue($state->columnExists);
            self::assertTrue($state->indexExists);
            self::assertTrue($state->released);
            $alters = array_values(array_filter($state->writes, static fn ($w) => strpos($w, 'ALTER TABLE') !== false));
            self::assertCount(1, $alters);
            self::assertStringNotContainsString('IF NOT EXISTS', $alters[0]);
            $indexes = array_values(array_filter($state->writes, static fn ($w) => strpos($w, 'CREATE INDEX') !== false));
            self::assertCount(1, $indexes);
            self::assertStringNotContainsString('IF NOT EXISTS', $indexes[0]);
            self::assertStringContainsString('DATABASE()', $state->queries[0]);
        }
    }

    public function testMysqlExistingColumnSkipsColumnLockButLocksMissingIndex(): void
    {
        $GLOBALS['tekstKeyMigration']->columnExists = true;
        $this->migrate('mysqli');
        $state = $GLOBALS['tekstKeyMigration'];
        $getLocks = array_values(array_filter($state->queries, static fn ($q) => strpos($q, 'GET_LOCK(') !== false));
        $releaseLocks = array_values(array_filter($state->queries, static fn ($q) => strpos($q, 'RELEASE_LOCK(') !== false));
        self::assertCount(1, $getLocks);
        self::assertStringContainsString('saldi:tekst_key_idx:', $getLocks[0]);
        self::assertCount(1, $releaseLocks);
        self::assertStringContainsString('saldi:tekst_key_idx:', $releaseLocks[0]);
        self::assertSame([], array_values(array_filter($state->writes, static fn ($w) => strpos($w, 'ALTER TABLE') !== false)));
        self::assertTrue($state->indexExists);
        self::assertCount(1, array_filter($state->writes, static fn ($w) => strpos($w, 'CREATE INDEX') !== false));
    }

    public function testMysqlExistingColumnAndIndexWriteNothing(): void
    {
        $GLOBALS['tekstKeyMigration']->columnExists = true;
        $GLOBALS['tekstKeyMigration']->indexExists = true;
        $this->migrate('mysqli');
        $state = $GLOBALS['tekstKeyMigration'];
        self::assertSame([], $state->writes);
        foreach ($state->queries as $q) {
            self::assertStringNotContainsString('GET_LOCK(', $q);
        }
    }

    public function testMigrationAlsoUpdatesMasterDatabase(): void
    {
        foreach (['postgresql', 'mysqli'] as $driver) {
            $this->setUp();
            $GLOBALS['sqdb'] = 'master';
            $this->migrate($driver);
            $state = $GLOBALS['tekstKeyMigration'];

            self::assertTrue($state->columnExists);
            self::assertTrue($state->indexExists);
            self::assertTrue($state->masterColumnExists);
            self::assertTrue($state->masterIndexExists);

            $tenantWrites = array_values(array_filter($state->writeTargets, static fn ($write) => !$write['global']));
            $masterWrites = array_values(array_filter($state->writeTargets, static fn ($write) => $write['global']));
            self::assertCount(2, $tenantWrites);
            self::assertCount(2, $masterWrites);

            if ($driver === 'mysqli') {
                $masterLocks = array_filter($state->queryTargets, static fn ($query) => $query['global'] && strpos($query['sql'], 'GET_LOCK(') !== false);
                $masterReleases = array_filter($state->queryTargets, static fn ($query) => $query['global'] && strpos($query['sql'], 'RELEASE_LOCK(') !== false);
                self::assertCount(2, $masterLocks);
                self::assertCount(2, $masterReleases);
            }
        }
    }

    public function testMysqlLockFailureThrowsAndNeverRunsDdl(): void
    {
        foreach (['0', null] as $result) {
            $this->setUp();
            $GLOBALS['tekstKeyMigration']->lockResult = $result;
            try {
                $this->migrate('mysqli');
                self::fail('Expected lock acquisition failure.');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('migration lock', $error->getMessage());
            }
            self::assertSame([], $GLOBALS['tekstKeyMigration']->writes);
            self::assertFalse($GLOBALS['tekstKeyMigration']->released);
        }
    }

    public function testMysqlRechecksColumnAfterConcurrentCreation(): void
    {
        $GLOBALS['tekstKeyMigration']->rivalCreatesColumn = true;
        $this->migrate('mysqli');
        $state = $GLOBALS['tekstKeyMigration'];
        self::assertSame([], array_values(array_filter($state->writes, static fn ($w) => strpos($w, 'ALTER TABLE') !== false)));
        self::assertTrue($state->released);
    }

    public function testColumnDdlFailurePropagatesAndReleasesMysqlLock(): void
    {
        foreach (['postgresql', 'mysqli'] as $driver) {
            $this->setUp();
            $state = $GLOBALS['tekstKeyMigration'];
            $state->writeError = new \RuntimeException('Permission denied');
            try {
                $this->migrate($driver);
                self::fail('Expected the DDL error to propagate.');
            } catch (\RuntimeException $error) {
                self::assertSame($state->writeError, $error);
            }
            // MySQL acquired the lock before the failing ALTER, so the finally must release it;
            // Postgres never took a lock.
            self::assertSame($driver === 'mysqli', $state->released);
        }
    }

    private function migrate(string $db_type): void
    {
        // Exercise only this migration, without running unrelated pending updates.
        $source = file_get_contents(__DIR__ . '/../../../includes/betweenUpdates.php');
        $start = strpos($source, '$tekstKeyMysql =');
        // End at the close of the migration's foreach: later blocks in betweenUpdates.php are other migrations.
        $end = strpos($source, "\n}\n", strpos($source, 'foreach ($tekstKeyTargets', $start)) + 3;
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        eval('namespace ' . __NAMESPACE__ . '; use RuntimeException; ' . substr($source, $start, $end - $start));
    }
}
