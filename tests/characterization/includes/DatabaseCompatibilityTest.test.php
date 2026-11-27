<?php
// 20261124 CDX/NTR Verify shared database helpers and the relocated operator-panel structure builder.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DatabaseCompatibilityTest extends TestCase
{
    private ?\PgSql\Connection $connection = null;
    private ?\PgSql\Connection $competitor = null;
    private string $runtimeDir;
    private string $originalDirectory;
    private string|false $originalGroup;

    /** Give query logging private runtime storage, independent of the checkout's permissions. */
    protected function setUp(): void
    {
        $this->originalDirectory = getcwd();
        $this->originalGroup = getenv('SALDI_SHARED_FILE_GROUP');
        putenv('SALDI_SHARED_FILE_GROUP=' . posix_getgrgid(posix_getegid())['name']);
        $this->runtimeDir = sys_get_temp_dir() . '/saldi_compat_' . bin2hex(random_bytes(8));
        mkdir($this->runtimeDir, 0700);
        mkdir($this->runtimeDir . '/temp', 0700);
        chdir($this->runtimeDir);
    }

    /** Open a private schema in the owned SQL fixture, using the production query helpers. */
    private function postgres(): void
    {
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $database = TestDatabase::sqlFixture(static::class)->name();
        $this->connection = TestDatabase::connect($database);
        $GLOBALS['connection'] = $this->connection;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db_encode'] = 'UTF8';
        $GLOBALS['db'] = $database;
        $GLOBALS['sqdb'] = $database;
        $GLOBALS['db_skriv_id'] = 1;
        $GLOBALS['brugernavn'] = '';
        $GLOBALS['s_id'] = '';
        $GLOBALS['db_transaktion_depth'] = 0;
        $_SERVER['REQUEST_URI'] = '/tests';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        require_once __DIR__ . '/../../../includes/db_query.php';
        require_once __DIR__ . '/../../../includes/database/compatibility.php';
        $this->sql('BEGIN');
        $schema = 'compat_' . bin2hex(random_bytes(8));
        $this->sql("CREATE SCHEMA $schema");
        $this->sql("SET LOCAL search_path TO $schema");
    }

    /** @return \PgSql\Result Result of fixture SQL, with errors left visible. */
    private function sql(string $query): \PgSql\Result
    {
        $result = pg_query($this->connection, $query);
        self::assertNotFalse($result);
        return $result;
    }

    protected function tearDown(): void
    {
        if ($this->competitor !== null) {
            pg_close($this->competitor);
        }
        if ($this->connection !== null) {
            pg_query($this->connection, 'ROLLBACK');
            pg_close($this->connection);
        }
        chdir($this->originalDirectory);
        putenv($this->originalGroup === false ? 'SALDI_SHARED_FILE_GROUP' : 'SALDI_SHARED_FILE_GROUP=' . $this->originalGroup);
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->runtimeDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->runtimeDir);
    }

    public function testRuntimeIncludesLoadTheSharedHelpersAndPreserveDriverConversions(): void
    {
        require_once __DIR__ . '/../../../includes/entitlements.php';
        require_once __DIR__ . '/../../../includes/partnerScope.php';
        require_once __DIR__ . '/../../../includes/structure/operatorPanelSchema.php';
        require __DIR__ . '/../../../includes/database/compatibility.php';
        foreach (['mysql', 'mysqli', 'MySQLi', 'postgresql'] as $driver) {
            $GLOBALS['db_type'] = $driver;
            $mysql = strtolower($driver) !== 'postgresql';
            self::assertSame(strtolower($driver), db_type());
            self::assertSame($mysql, db_is_mysql());
            self::assertSame($mysql ? '`key`' : 'key', db_key_column());
            self::assertSame($mysql ? 'DATETIME' : 'timestamp', db_timestamp_type());
        }
        foreach ([true, 1, '1', 't', 'true'] as $value) {
            self::assertTrue(db_bool($value));
        }
        foreach ([false, 0, '0', 'f', 'false', null, ''] as $value) {
            self::assertFalse(db_bool($value));
        }
        $GLOBALS['sqdb'] = 'fixture';
        self::assertSame('saldi:' . md5('fixture:catalog_seed'), db_lock_name('catalog_seed'));
    }

    public function testInsertsAndMetadataChecksUseTheCurrentSchema(): void
    {
        $this->postgres();
        $this->sql('CREATE TABLE example (id serial PRIMARY KEY, label text)');
        self::assertTrue(db_table_exists('example'));
        self::assertTrue(db_column_exists('example', 'label'));
        self::assertFalse(db_column_exists('example', 'missing'));
        self::assertFalse(db_table_exists("example' OR 1=1 --"));
        $id = db_insert_id("INSERT INTO example (label) VALUES ('shared')", __FILE__);
        self::assertGreaterThan(0, $id);
        self::assertSame('shared', pg_fetch_assoc($this->sql("SELECT label FROM example WHERE id = $id"))['label']);
        $this->sql('SET LOCAL search_path TO pg_catalog');
        self::assertFalse(db_table_exists('example'));
        self::assertFalse(db_column_exists('example', 'label'));
    }

    public function testSessionLocksExcludeAnotherConnectionUntilUnlocked(): void
    {
        $this->postgres();
        $this->competitor = TestDatabase::connect($GLOBALS['db']);
        $name = 'compat_lock_' . bin2hex(random_bytes(8));
        self::assertTrue(db_lock($name, 0));
        $query = "SELECT pg_try_advisory_lock(hashtext('$name')) AS held";
        self::assertSame('f', pg_fetch_assoc(pg_query($this->competitor, $query))['held']);
        db_unlock($name);
        self::assertSame('t', pg_fetch_assoc(pg_query($this->competitor, $query))['held']);
    }

    public function testLockedOperationsReuseTheOuterTransaction(): void
    {
        $this->postgres();
        $this->sql('CREATE TABLE example (id serial PRIMARY KEY)');
        $GLOBALS['db_transaktion_depth'] = 1;
        self::assertSame('', db_locked('compat_transaction', function () {
            db_insert_id('INSERT INTO example DEFAULT VALUES', __FILE__);
            return '';
        }));
        self::assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->connection));
        self::assertSame('1', pg_fetch_assoc($this->sql('SELECT count(*) FROM example'))['count']);
    }

    public function testRelocatedStructureBuilderAppliesOnceAndMarksThePanelReady(): void
    {
        $this->postgres();
        $this->sql('CREATE TABLE settings (var_grp text, var_name text, var_value text, var_description text)');
        $this->sql('CREATE TABLE regnskab (id serial PRIMARY KEY)');
        require_once __DIR__ . '/../../../includes/structure/operatorPanelSchema.php';
        self::assertFalse(operator_panel_ready());
        self::assertTrue(operator_panel_schema());
        self::assertTrue(operator_panel_ready());
        self::assertFalse(operator_panel_schema());
        self::assertTrue(db_table_exists('partners'));
        self::assertTrue(db_column_exists('regnskab', 'lukkes_kommentar'));
        self::assertSame(OPERATOR_PANEL_SCHEMA, pg_fetch_assoc($this->sql("SELECT var_value FROM settings WHERE var_name = 'operator_panel_schema'"))['var_value']);
    }
}
