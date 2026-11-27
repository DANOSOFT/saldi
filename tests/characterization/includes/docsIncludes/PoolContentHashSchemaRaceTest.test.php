<?php
// tests/characterization/includes/docsIncludes/PoolContentHashSchemaRaceTest.test.php
//
// Two first logins to the same regnskab ran poolContentHashColumnExists(true) at the same time. Both found
// idx_pool_files_content_sha256 missing and both sent CREATE INDEX IF NOT EXISTS. PostgreSQL does not make the
// later one wait for an index that is still being created, so it failed on pg_class_relname_nsp_index and
// db_modify() ended that login (testlab login-race, test_39, 3 of 3 rounds).
//
// Set SALDI_CHAR_PG_DSN to an isolated PostgreSQL connection string. Every test owns a randomized schema and
// drops only that schema again.
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PoolContentHashSchemaRaceTest extends TestCase
{
    private $request;
    private $rival;
    private $observer;
    private string $schema;
    private bool $rivalPending = false;

    protected function setUp(): void
    {
        if (!extension_loaded('pgsql') || !getenv('SALDI_CHAR_PG_DSN')) {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN for an isolated PostgreSQL fixture.');
        }
        require_once __DIR__ . '/support/pool_hash_database.php';
        require_once dirname(__DIR__, 4) . '/includes/docsIncludes/poolContentHash.php';
        $dsn = getenv('SALDI_CHAR_PG_DSN');
        // Report an unavailable fixture without echoing a potentially credential-bearing DSN.
        set_error_handler(static function () {
            throw new RuntimeException('Configured PostgreSQL connection is unavailable.');
        });
        try {
            $this->request = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
            $this->rival = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
            $this->observer = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        } catch (Throwable $error) {
            self::markTestSkipped('Configured PostgreSQL connection is unavailable.');
        } finally {
            restore_error_handler();
        }
        if (!$this->request || !$this->rival || !$this->observer) {
            self::markTestSkipped('The configured isolated PostgreSQL fixture is unavailable.');
        }
        $this->schema = 'pool_hash_race_' . bin2hex(random_bytes(8));
        pg_query($this->observer, 'CREATE SCHEMA ' . $this->schema);
        foreach ([$this->request, $this->rival, $this->observer] as $connection) {
            pg_query($connection, 'SET search_path TO ' . $this->schema);
            pg_query($connection, "SET statement_timeout = '5s'");
        }
        // Both columns are there: in the testlab both logins got past ADD COLUMN IF NOT EXISTS, which waits for the other one.
        pg_query($this->observer, 'CREATE TABLE pool_files (id SERIAL PRIMARY KEY, filename VARCHAR(255) NOT NULL UNIQUE, content_sha256 CHAR(64), source_sha256 CHAR(64))');
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['poolHashQueries'] = [];
    }

    protected function tearDown(): void
    {
        if ($this->rival) {
            if ($this->rivalPending) {
                pg_cancel_query($this->rival);
                while (pg_get_result($this->rival) !== false) {}
            }
            pg_query($this->rival, 'ROLLBACK');
        }
        if (isset($this->schema) && $this->observer) pg_query($this->observer, 'DROP SCHEMA ' . $this->schema . ' CASCADE');
        foreach ([$this->request, $this->rival, $this->observer] as $connection) {
            if ($connection) pg_close($connection);
        }
    }

    public static function hashColumns(): array
    {
        return [
            'stored-file hash' => ['content_sha256'],
            'original-upload hash' => ['source_sha256'],
        ];
    }

    #[DataProvider('hashColumns')]
    public function testWaitsForTheIndexAnotherLoginIsCreating(string $column): void
    {
        // The other login has started its CREATE INDEX and not finished it.
        $this->runAs('other-login', $this->rival, true);
        self::assertTrue(poolContentHashColumnExists(true, $column));
        self::assertTrue(isset($GLOBALS['poolHashHeld']), 'The other login wrote nothing');
        self::assertSame(0, $this->indexCount($column), 'The other login\'s index is already visible');
        // It finishes a second from now, while this login is looking for the same index.
        self::assertTrue(pg_send_query($this->rival, 'SELECT pg_sleep(1); ' . implode('; ', $GLOBALS['poolHashHeld'])));
        $this->rivalPending = true;

        $this->runAs('this-login', $this->request, false);
        self::assertTrue(poolContentHashColumnExists(true, $column));
        $this->finishRival();

        $creates = array_filter($GLOBALS['poolHashQueries'], static fn ($sql) => stripos($sql, 'CREATE INDEX') !== false);
        self::assertSame([], array_values($creates), 'This login created the index again instead of using the other login\'s');
        self::assertSame(1, $this->indexCount($column));
        $locks = pg_query($this->observer, "SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND pid = " . (int) pg_get_pid($this->request));
        self::assertSame('0', pg_fetch_result($locks, 0, 0), 'This login kept its schema lock');
    }

    /** Point the fixture's db_select()/db_modify() at one login's connection; the static cache is keyed by $db. */
    private function runAs(string $db, $connection, bool $hold): void
    {
        $GLOBALS['db'] = $db;
        $GLOBALS['poolHashConnection'] = $connection;
        $GLOBALS['poolHashHold'] = $hold;
        $GLOBALS['poolHashQueries'] = [];
    }

    private function indexCount(string $column): int
    {
        $result = pg_query_params($this->observer, 'SELECT count(*) FROM pg_indexes WHERE schemaname = $1 AND tablename = $2 AND indexname = $3', [$this->schema, 'pool_files', 'idx_pool_files_' . $column]);
        return (int) pg_fetch_result($result, 0, 0);
    }

    private function finishRival(): void
    {
        while (($result = pg_get_result($this->rival)) !== false) {
            self::assertContains(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], (string) pg_result_error($result));
        }
        $this->rivalPending = false;
    }
}
