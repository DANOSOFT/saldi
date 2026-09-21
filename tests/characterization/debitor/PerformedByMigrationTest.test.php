<?php
// 20260921 CDX/LH Cover concurrent performed_by migration attempts and lock failures.

namespace Saldi\Tests\PerformedByMigration;

use PHPUnit\Framework\TestCase;

function db_select($sql, $location)
{
    $state = $GLOBALS['performedByMigration'];
    $state->queries[] = $sql;
    if (strpos($sql, 'GET_LOCK(') !== false) {
        if ($state->rivalCreatesColumn) {
            $state->exists = true;
        }
        return ['acquired' => $state->lockResult];
    }
    if (strpos($sql, 'RELEASE_LOCK(') !== false) {
        $state->released = true;
        return [];
    }
    return $state->exists ? ['column_name' => 'performed_by'] : false;
}

function db_fetch_array($result)
{
    return $result;
}

function db_modify($sql, $location)
{
    $state = $GLOBALS['performedByMigration'];
    $state->writes[] = $sql;
    if ($state->writeError !== null) {
        throw $state->writeError;
    }
    // Model a rival ALTER between the first existence check and our DDL.
    if ($state->rivalCreatesColumn && strpos($sql, 'IF NOT EXISTS') === false) {
        throw new \RuntimeException('Duplicate column');
    }
    $state->exists = true;
}

final class PerformedByMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['performedByMigration'] = (object) [
            'exists' => false,
            'rivalCreatesColumn' => false,
            'lockResult' => '1',
            'released' => false,
            'writeError' => null,
            'queries' => [],
            'writes' => [],
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['performedByMigration']);
    }

    public function testPostgresCreatesColumnAndRepeatedRunDoesNotWrite(): void
    {
        $this->migrate('postgresql');
        $this->migrate('postgresql');
        $state = $GLOBALS['performedByMigration'];
        self::assertTrue($state->exists);
        self::assertCount(1, $state->writes);
        self::assertStringContainsString('table_schema = current_schema()', $state->queries[0]);
    }

    public function testPostgresToleratesConcurrentCreation(): void
    {
        $GLOBALS['performedByMigration']->rivalCreatesColumn = true;
        $this->migrate('postgresql');
        self::assertTrue($GLOBALS['performedByMigration']->exists);
    }

    public function testMysqlCreatesColumnAndReleasesLockForBothDriverNames(): void
    {
        foreach (['mysql', 'mysqli'] as $driver) {
            $this->setUp();
            $this->migrate($driver);
            $state = $GLOBALS['performedByMigration'];
            self::assertTrue($state->exists);
            self::assertTrue($state->released);
            self::assertCount(1, $state->writes);
            self::assertStringNotContainsString('IF NOT EXISTS', $state->writes[0]);
            self::assertStringContainsString('table_schema = DATABASE()', $state->queries[0]);
        }
    }

    public function testMysqlRechecksAfterWaitingForConcurrentCreation(): void
    {
        $GLOBALS['performedByMigration']->rivalCreatesColumn = true;
        $this->migrate('mysqli');
        self::assertSame([], $GLOBALS['performedByMigration']->writes);
        self::assertTrue($GLOBALS['performedByMigration']->released);
    }

    public function testExistingMysqlColumnDoesNotAcquireLock(): void
    {
        $GLOBALS['performedByMigration']->exists = true;
        $this->migrate('mysqli');
        self::assertCount(1, $GLOBALS['performedByMigration']->queries);
        self::assertSame([], $GLOBALS['performedByMigration']->writes);
    }

    public function testMysqlLockTimeoutOrErrorNeverRunsUnlockedDdl(): void
    {
        foreach (['0', null] as $result) {
            $GLOBALS['performedByMigration']->lockResult = $result;
            try {
                $this->migrate('mysqli');
                self::fail('Expected lock acquisition failure.');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('migration lock', $error->getMessage());
            }
            self::assertSame([], $GLOBALS['performedByMigration']->writes);
            self::assertFalse($GLOBALS['performedByMigration']->released);
        }
    }

    public function testOtherDdlFailuresPropagateAndReleaseMysqlLock(): void
    {
        foreach (['postgresql', 'mysqli'] as $driver) {
            $this->setUp();
            $state = $GLOBALS['performedByMigration'];
            $state->writeError = new \RuntimeException('Permission denied');
            try {
                $this->migrate($driver);
                self::fail('Expected the DDL error to propagate.');
            } catch (\RuntimeException $error) {
                self::assertSame($state->writeError, $error);
            }
            self::assertSame($driver === 'mysqli', $state->released);
        }
    }

    private function migrate(string $db_type): void
    {
        // Exercise only this migration, without running unrelated pending updates.
        $source = file_get_contents(__DIR__ . '/../../../includes/betweenUpdates.php');
        $start = strpos($source, '$performedByMysql =');
        $end = strpos($source, '// Bilagsmatch scoring engine:', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        eval('namespace ' . __NAMESPACE__ . '; use RuntimeException; ' . substr($source, $start, $end - $start));
    }
}
