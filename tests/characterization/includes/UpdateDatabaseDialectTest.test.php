<?php
// 20261113 CDX/NTR Exercise update dialect helpers with private PostgreSQL schemas and MySQL driver aliases.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/opdat_func/databaseDialect.php';

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class UpdateDatabaseDialectTest extends TestCase
{
    private ?\PgSql\Connection $connection = null;
    private string $otherSchema;

    /** Open a transaction with two private schemas in the owned SQL fixture. */
    private function postgres(): void
    {
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $this->connection = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        $GLOBALS['connection'] = $this->connection;
        $GLOBALS['db_type'] = 'postgresql';
        require_once __DIR__ . '/../../../includes/db_query.php';
        $suffix = bin2hex(random_bytes(8));
        $schema = 'dialect_' . $suffix;
        $this->otherSchema = 'other_' . $suffix;
        $this->sql('BEGIN');
        $this->sql("CREATE SCHEMA $schema");
        $this->sql("CREATE SCHEMA {$this->otherSchema}");
        $this->sql("SET LOCAL search_path TO $schema");
    }

    /** Execute fixture SQL without suppressing database errors. */
    private function sql(string $sql): \PgSql\Result
    {
        $result = pg_query($this->connection, $sql);
        self::assertNotFalse($result);
        return $result;
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            pg_query($this->connection, 'ROLLBACK');
            pg_close($this->connection);
        }
        unset($GLOBALS['connection'], $GLOBALS['db_type']);
    }

    public function testGeneratedColumnsSupplyIdsAndTimestampsAndPreserveNullability(): void
    {
        $this->postgres();
        $this->sql('CREATE TABLE generated (' . opdat_id_column('postgresql')
            . ', created ' . opdat_timestamp_column('postgresql')
            . ', required ' . opdat_timestamp_column('postgresql', true) . ')');
        $first = pg_fetch_assoc($this->sql('INSERT INTO generated DEFAULT VALUES RETURNING *'));
        $second = pg_fetch_assoc($this->sql('INSERT INTO generated (created) VALUES (NULL) RETURNING *'));
        self::assertSame('1', $first['id']);
        self::assertSame('2', $second['id']);
        self::assertNotNull($first['created']);
        self::assertNotNull($first['required']);
        self::assertNull($second['created']);
        $columns = pg_fetch_all($this->sql("SELECT column_name, is_nullable FROM information_schema.columns WHERE table_name = 'generated'"
            . opdat_schema_filter('postgresql') . ' ORDER BY ordinal_position'));
        self::assertSame([
            ['column_name' => 'id', 'is_nullable' => 'NO'],
            ['column_name' => 'created', 'is_nullable' => 'YES'],
            ['column_name' => 'required', 'is_nullable' => 'NO'],
        ], $columns);
    }

    public function testIntegerConversionUsesOnlyTheCurrentSchemaAndKeepsValues(): void
    {
        $this->postgres();
        $this->sql("CREATE TABLE {$this->otherSchema}.legacy (employee integer NOT NULL DEFAULT 99)");
        $this->sql("INSERT INTO {$this->otherSchema}.legacy DEFAULT VALUES");
        $this->sql("CREATE TABLE legacy (employee text NOT NULL DEFAULT '')");
        $this->sql("INSERT INTO legacy VALUES (''), (' 27 '), ('-3')");
        $typeQuery = "SELECT data_type FROM information_schema.columns WHERE table_name = 'legacy' AND column_name = 'employee'"
            . opdat_schema_filter('postgresql');
        $types = pg_fetch_all($this->sql($typeQuery));
        self::assertSame([['data_type' => 'text']], $types);
        foreach (opdat_nullable_integer_queries('postgresql', 'legacy', 'employee') as $query) {
            $this->sql($query);
        }
        self::assertSame([null, '-3', '27'], array_column(pg_fetch_all($this->sql('SELECT employee FROM legacy ORDER BY employee NULLS FIRST')), 'employee'));
        $column = pg_fetch_assoc($this->sql("SELECT data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = 'legacy' AND column_name = 'employee'"
            . opdat_schema_filter('postgresql')));
        self::assertSame(['data_type' => 'integer', 'is_nullable' => 'YES', 'column_default' => null], $column);
        self::assertSame('99', pg_fetch_assoc($this->sql("SELECT employee FROM {$this->otherSchema}.legacy"))['employee']);
    }

    public function testIndexLookupIgnoresOtherSchemasAndEscapesMetadataValues(): void
    {
        $this->postgres();
        $this->sql("CREATE TABLE {$this->otherSchema}.indexed (id integer)");
        $this->sql("CREATE INDEX fixture_idx ON {$this->otherSchema}.indexed (id)");
        $this->sql('CREATE TABLE indexed (id integer)');
        $query = opdat_index_query('postgresql', 'indexed', 'fixture_idx');
        self::assertSame(0, pg_num_rows($this->sql($query)));
        $this->sql('CREATE INDEX fixture_idx ON indexed (id)');
        self::assertSame(1, pg_num_rows($this->sql($query)));
        self::assertSame(0, pg_num_rows($this->sql(opdat_index_query('postgresql', 'indexed', "' OR 1=1 --"))));
        self::assertSame(0, pg_num_rows($this->sql(opdat_index_query('postgresql', "' OR 1=1 --", 'fixture_idx'))));
    }

    public function testMysqlAndMysqliUseTheSameDialect(): void
    {
        foreach (['mysql', 'mysqli'] as $driver) {
            self::assertTrue(opdat_is_mysql($driver));
            self::assertStringContainsString('AUTO_INCREMENT PRIMARY KEY', opdat_id_column($driver));
            self::assertSame('DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', opdat_timestamp_column($driver, true));
            self::assertSame(' AND table_schema = DATABASE()', opdat_schema_filter($driver));
            self::assertSame([
                "UPDATE `legacy` SET `employee` = NULL WHERE TRIM(`employee`) = ''",
                'ALTER TABLE `legacy` MODIFY `employee` integer NULL DEFAULT NULL',
            ], opdat_nullable_integer_queries($driver, 'legacy', 'employee'));
        }
        self::assertFalse(opdat_is_mysql('postgresql'));
        self::assertFalse(opdat_is_mysql('postgres'));
    }

    public function testConversionRejectsSqlInAnIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        opdat_nullable_integer_queries('mysqli', 'legacy; DROP TABLE legacy', 'employee');
    }
}
