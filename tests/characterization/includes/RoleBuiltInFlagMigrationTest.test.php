<?php
// 20261113 CDX/NTR Cover the built-in role flag rename, repeat runs, tenant isolation and lock cleanup.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class RoleBuiltInFlagMigrationTest extends TestCase
{
    private ?\PgSql\Connection $connection = null;
    private object $state;

    protected function setUp(): void
    {
        $this->state = (object) ['queries' => [], 'writes' => [], 'old' => true, 'new' => false,
            'acquired' => 1, 'failure' => false, 'rivalRenames' => false];
        $GLOBALS['roleFlagMigrationState'] = $this->state;
        if (!function_exists('db_select')) {
            function db_select($sql, $location = '') {
                $state = $GLOBALS['roleFlagMigrationState'];
                $state->queries[] = $sql;
                return ($state->query)($sql);
            }
            function db_fetch_array($result) {
                return is_array($result) || $result === false ? $result : pg_fetch_assoc($result);
            }
            function db_modify($sql, $location = '') {
                $state = $GLOBALS['roleFlagMigrationState'];
                $state->writes[] = $sql;
                return ($state->write)($sql);
            }
        }
        if (!function_exists('tbl_exists')) {
            function tbl_exists($table): bool {
                $connection = $GLOBALS['roleFlagMigrationState']->connection;
                return pg_num_rows(pg_query_params($connection,
                    'SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = $1', [$table])) > 0;
            }
        }
        require_once __DIR__ . '/../../../includes/opdat_func/migrateRoleBuiltInFlag.php';
    }

    /** Create a private schema in the owned SQL fixture. */
    private function postgres(): void
    {
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $this->connection = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        $this->state->connection = $this->connection;
        $schema = 'role_flag_' . bin2hex(random_bytes(8));
        $this->sql('BEGIN');
        $this->sql("CREATE SCHEMA $schema");
        $this->sql("SET LOCAL search_path TO $schema");
        $query = function (string $sql) { return $this->sql($sql); };
        $this->state->query = $query;
        $this->state->write = $query;
    }

    /** Execute fixture SQL with errors visible. */
    private function sql(string $sql): \PgSql\Result
    {
        $result = pg_query($this->connection, $sql);
        self::assertNotFalse($result);
        return $result;
    }

    /** Model MySQL metadata and lock outcomes without using a customer database. */
    private function mysql(): void
    {
        $state = $this->state;
        $state->query = static function (string $sql) use ($state) {
            if (str_contains($sql, 'GET_LOCK(')) {
                if ($state->rivalRenames) {
                    $state->old = false;
                    $state->new = true;
                }
                return ['acquired' => $state->acquired];
            }
            if (str_contains($sql, 'RELEASE_LOCK(')) {
                return [];
            }
            self::assertStringContainsString('table_schema = DATABASE()', $sql);
            return str_contains($sql, "column_name = 'system'")
                ? ($state->old ? ['column_name' => 'system'] : false)
                : ($state->new ? ['column_name' => 'is_built_in'] : false);
        };
        $state->write = static function (string $sql) use ($state) {
            if ($state->failure) {
                throw new RuntimeException('Simulated DDL failure');
            }
            $state->old = false;
            $state->new = true;
        };
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            pg_query($this->connection, 'ROLLBACK');
            pg_close($this->connection);
        }
        unset($GLOBALS['roleFlagMigrationState']);
    }

    public function testUpgradePreservesFlagsAndPermissionRolesReadTheNewName(): void
    {
        $this->postgres();
        $this->sql('CREATE TABLE roles (id integer, role_key text, navn text, beskrivelse text, "system" boolean DEFAULT false)');
        $this->sql('CREATE TABLE role_permissions (role_id integer)');
        $this->sql("INSERT INTO roles VALUES (1, 'builtin', 'Built-in', '', true), (2, NULL, 'Custom', '', false), (3, NULL, 'Legacy null', '', NULL)");
        migrateRoleBuiltInFlag('postgresql');
        $writes = $this->state->writes;
        migrateRoleBuiltInFlag('postgresql');
        self::assertSame($writes, $this->state->writes);
        self::assertSame([['id' => '1', 'is_built_in' => 't'], ['id' => '2', 'is_built_in' => 'f'], ['id' => '3', 'is_built_in' => null]],
            pg_fetch_all($this->sql('SELECT id, is_built_in FROM roles ORDER BY id')));
        $this->sql("INSERT INTO roles (id, navn) VALUES (4, 'Default flag')");
        self::assertSame('f', pg_fetch_result($this->sql('SELECT is_built_in FROM roles WHERE id = 4'), 0, 0));
        self::assertSame(0, pg_num_rows($this->sql("SELECT 1 FROM information_schema.columns WHERE table_name = 'roles' AND column_name = 'system' AND table_schema = current_schema()")));
        require_once __DIR__ . '/../../../includes/permissions.php';
        $roles = perm_roles();
        $flags = array_column($roles, 'is_built_in', 'id');
        ksort($flags);
        self::assertSame([1 => true, 2 => false, 3 => false, 4 => false], $flags);
        self::assertArrayNotHasKey('system', $roles[0]);
    }

    public function testFreshTableDefinitionAlreadyUsesTheNewFlag(): void
    {
        $this->postgres();
        $source = file_get_contents(__DIR__ . '/../../../includes/betweenUpdates.php');
        self::assertSame(1, preg_match('/\$qtxt = ("CREATE TABLE roles \(.*?\)");/s', $source, $match));
        $sawanehId = opdat_id_column('postgresql');
        $sawanehNow = opdat_timestamp_column('postgresql');
        eval('$qtxt = ' . $match[1] . ';');
        $this->sql($qtxt);
        migrateRoleBuiltInFlag('postgresql');
        self::assertSame([], $this->state->writes);
        self::assertSame('f', pg_fetch_result($this->sql("INSERT INTO roles (navn) VALUES ('Custom') RETURNING is_built_in"), 0, 0));
        self::assertLessThan(strpos($source, 'perm_ensure_default_roles();'), strpos($source, 'migrateRoleBuiltInFlag($db_type);'));
    }

    public function testAnotherSchemasOldFlagDoesNotTriggerARename(): void
    {
        $this->postgres();
        $other = 'other_roles_' . bin2hex(random_bytes(8));
        $this->sql("CREATE SCHEMA $other");
        $this->sql("CREATE TABLE $other.roles (system boolean DEFAULT false)");
        $this->sql('CREATE TABLE roles (is_built_in boolean DEFAULT false)');
        migrateRoleBuiltInFlag('postgresql');
        self::assertSame([], $this->state->writes);
        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM information_schema.columns WHERE table_name = 'roles' AND column_name = 'system' AND table_schema = '$other'")));
    }

    /** @return array<string, array{0: string}> */
    public static function mysqlDrivers(): array
    {
        return ['mysql' => ['mysql'], 'mysqli' => ['mysqli']];
    }

    #[DataProvider('mysqlDrivers')]
    public function testMysqlRenameIsRepeatableAndReleasesItsLock(string $driver): void
    {
        $this->mysql();
        migrateRoleBuiltInFlag($driver);
        migrateRoleBuiltInFlag($driver);
        self::assertSame(['ALTER TABLE roles CHANGE COLUMN `system` is_built_in boolean DEFAULT false'], $this->state->writes);
        self::assertStringContainsString("RELEASE_LOCK(CONCAT('saldi:roles_builtin:', MD5(DATABASE())))", implode("\n", $this->state->queries));
    }

    public function testFailedRenameReleasesTheLockAndCanRetry(): void
    {
        $this->mysql();
        $this->state->failure = true;
        try {
            migrateRoleBuiltInFlag('mysqli');
            self::fail('The simulated DDL failure must propagate.');
        } catch (RuntimeException $error) {
            self::assertSame('Simulated DDL failure', $error->getMessage());
        }
        self::assertStringContainsString('RELEASE_LOCK(', end($this->state->queries));
        self::assertTrue($this->state->old);
        $this->state->failure = false;
        migrateRoleBuiltInFlag('mysqli');
        self::assertTrue($this->state->new);
    }

    public function testBusyLockStopsBeforeRoleSeedingCanUseTheWrongSchema(): void
    {
        $this->mysql();
        $this->state->acquired = 0;
        try {
            migrateRoleBuiltInFlag('mysqli');
            self::fail('A busy lock must stop the upgrade.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('migration lock', $error->getMessage());
        }
        self::assertSame([], $this->state->writes);
        self::assertStringNotContainsString('RELEASE_LOCK(', implode("\n", $this->state->queries));
    }

    public function testConcurrentRenameIsRecheckedAfterAcquiringTheLock(): void
    {
        $this->mysql();
        $this->state->rivalRenames = true;
        migrateRoleBuiltInFlag('mysqli');
        self::assertSame([], $this->state->writes);
        self::assertStringContainsString('RELEASE_LOCK(', end($this->state->queries));
    }
}
