<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The CRM's tables and columns are made by one login at a time (includes/betweenUpdates.php crm_ensure_schema(),
 * pre-check on #1199, 2026-11-24).
 *
 * crm_ensure_schema() runs at every login and adds the CRM's columns to historik and its tables (crm_deals,
 * crm_meetings, crm_participants, crm_ical) where they are missing. Two first logins after a deploy both found a
 * column missing and both ran the ALTER: the second waited for the first and then stopped on "already exists", and on
 * a page that is the error box instead of the login. It now takes a lock (pg_advisory_lock, GET_LOCK on MySQL), and
 * each step looks again once the login holds it.
 *
 * Pins: on a real Postgres, a login (support/crm_schema_runner.php, a child process) waits while another session holds
 * the lock, makes nothing meanwhile, and makes the tables once the lock is free; a second login after it finds
 * everything and changes nothing. The tables go in a schema of the test's own in the shared SQL fixture database
 * (TestDatabase::sqlFixture()), dropped afterwards; nothing else there is read or changed.
 *
 * History:
 * 20261124 CL/ASR Created.
 */
final class CrmSchemaLockTest extends TestCase
{
    private const RUNNER = __DIR__ . '/support/crm_schema_runner.php';

    /** @var \PgSql\Connection|null */
    private $conn = null;
    private string $database = '';
    private string $schema = '';
    /** @var list<resource> the logins this test started, ended in tearDown() if still running */
    private array $running = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to run the CRM schema SQL.');
        }
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $this->database = TestDatabase::sqlFixture(static::class)->name();
        $this->conn = TestDatabase::connect($this->database);
        $this->schema = 'crm_lock_' . getmypid();
        $this->exec("DROP SCHEMA IF EXISTS {$this->schema} CASCADE");
        $this->exec("CREATE SCHEMA {$this->schema}");
        // historik as a ledger has it before the CRM: the columns the CRM's steps read
        $this->exec("CREATE TABLE {$this->schema}.historik (id serial PRIMARY KEY, konto_id integer, kontaktes date, notat text)");
    }

    protected function tearDown(): void
    {
        // A login an assertion left running is ended before its schema is dropped.
        foreach ($this->running as $proc) {
            if (is_resource($proc) && proc_get_status($proc)['running']) {
                proc_terminate($proc);
            }
            if (is_resource($proc)) {
                proc_close($proc);
            }
        }
        if ($this->conn) {
            @pg_query($this->conn, "SELECT pg_advisory_unlock_all()");
            @pg_query($this->conn, "DROP SCHEMA IF EXISTS {$this->schema} CASCADE");
        }
    }

    private function exec(string $sql): void
    {
        if (@pg_query($this->conn, $sql) === false) {
            throw new \RuntimeException(pg_last_error($this->conn) . " in: " . $sql);
        }
    }

    private function tableExists(string $table): bool
    {
        $r = pg_query_params($this->conn, "SELECT 1 FROM information_schema.tables WHERE table_schema = $1 AND table_name = $2", [$this->schema, $table]);
        return pg_num_rows($r) > 0;
    }

    /** @return array{0: resource, 1: array<int, resource>} a login's crm_ensure_schema() running in a child process */
    private function startLogin(): array
    {
        $pipes = [];
        $proc = proc_open([PHP_BINARY, self::RUNNER, $this->database, $this->schema], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($proc, 'the login could not be started');
        $this->running[] = $proc;
        return [$proc, $pipes];
    }

    /** @return array{0: int, 1: string, 2: string} exit code, stdout and stderr once the login has ended */
    private function finish($proc, array $pipes, int $seconds = 30): array
    {
        // The first status that says it has ended is kept: before PHP 8.3 only that one carries the exit code.
        $deadline = microtime(true) + $seconds;
        $status = proc_get_status($proc);
        while ($status['running'] && microtime(true) < $deadline) {
            usleep(50000);
            $status = proc_get_status($proc);
        }
        if ($status['running']) {
            self::fail("the login did not end within $seconds s");
        }
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        return [(int) $status['exitcode'], (string) $out, (string) $err];
    }

    public function test_a_login_waits_for_the_lock_and_then_makes_the_crm_tables_once(): void
    {
        // Another login holds the lock, as the first one does while it makes the tables.
        $this->exec("SELECT pg_advisory_lock(hashtext('saldi_crm_schema'))");
        [$proc, $pipes] = $this->startLogin();
        // Until another session waits for this lock (its key is classid and objid in pg_locks), or the login has ended
        // without waiting.
        $waiting = false;
        $deadline = microtime(true) + 20;
        while (!$waiting && proc_get_status($proc)['running'] && microtime(true) < $deadline) {
            usleep(50000);
            $waiting = pg_num_rows(pg_query($this->conn, "SELECT 1 FROM pg_locks WHERE locktype = 'advisory' AND NOT granted "
                . "AND pid <> pg_backend_pid() AND database = (SELECT oid FROM pg_database WHERE datname = current_database()) "
                . "AND ((classid::bigint << 32) | objid::bigint) = hashtext('saldi_crm_schema')::bigint")) > 0;
        }

        $this->assertTrue($waiting, 'the second login waits for the lock instead of running the same ALTER');
        $this->assertTrue(proc_get_status($proc)['running']);
        $this->assertFalse($this->tableExists('crm_deals'), 'nothing is made while another login holds the lock');

        $this->exec("SELECT pg_advisory_unlock(hashtext('saldi_crm_schema'))");
        [$code, $out, $err] = $this->finish($proc, $pipes);
        $this->assertSame(0, $code, "the login ended with an error: $err");
        $this->assertSame("start\ndone\n", $out);
        foreach (['crm_deals', 'crm_meetings', 'crm_participants', 'crm_ical'] as $table) {
            $this->assertTrue($this->tableExists($table), "$table is made once the lock is free");
        }

        // The next login finds everything and changes nothing.
        [$proc, $pipes] = $this->startLogin();
        [$code, $out, $err] = $this->finish($proc, $pipes);
        $this->assertSame(0, $code, "a later login ended with an error: $err");
        $this->assertSame("start\ndone\n", $out);
    }
}
