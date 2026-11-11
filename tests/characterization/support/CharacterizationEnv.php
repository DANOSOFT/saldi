<?php
// tests/characterization/support/CharacterizationEnv.php
//
// Shared environment for DB-backed characterization tests (SD-601).
//
// These tests exercise the REAL posting engine against a REAL PostgreSQL
// tenant database, inside the repo's docker-compose stack. They are designed
// to be discovered by phpunit.xml everywhere but to SKIP cleanly on machines
// where the stack is not reachable (no pgsql extension, or no postgres host),
// so `composer test` stays green on a bare checkout.
//
// Each test class now owns a fresh UUID-named tenant installed from repository
// schema/seed files. SALDI_TEST_KEEP_DATABASES=1 retains its database and routing
// rows; the helper prints the UUID and name before creation.
//
// History:
// 20260723 CL/LH SD-601: created.
// 20260725 CL/LH Re-sync sequences after the clone; add nextId() for fixtures that mirror the app's MAX(id)+1 allocation.
// 20260916 CDX/LH Isolate disposable test tenants and retain child-process failure diagnostics.
// 20261022 CL/NTR bootstrapTenantOnce() is guarded by $ownsTenant, so a teardown by one suite makes the next one re-clone.
//                 runChild() collects stderr in a temp file so a chatty child cannot deadlock on a full pipe.
// 20261111 CDX/NTR Replace the shared template clone with UUID-owned fresh installations.

require_once __DIR__ . '/database/TestDatabase.php';

final class CharacterizationEnv
{
    private static ?TestDatabase $database = null;

    public static function sessionId(): string
    {
        return self::$database->sessionId();
    }

    public static function pgHost(): string
    {
        return getenv('SALDI_CHAR_PGHOST') ?: 'postgres';
    }

    public static function pgUser(): string
    {
        return getenv('SALDI_CHAR_PGUSER') ?: 'user';
    }

    public static function pgPass(): string
    {
        return getenv('SALDI_CHAR_PGPASS') ?: '';
    }

    public static function masterDb(): string
    {
        return getenv('SALDI_CHAR_MASTER_DB') ?: 'saldi';
    }

    public static function testDb(): string
    {
        if (self::$database === null) {
            throw new RuntimeException('Bootstrap the test group before asking for its database');
        }
        return self::$database->name();
    }

    public static function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /** Only the application master is needed; no installed template tenant. */
    public static function unavailableReason(): ?string
    {
        return TestDatabase::unavailableReason();
    }

    public static function connect(string $db)
    {
        return TestDatabase::connect($db);
    }

    /** Run a query, return all rows as associative arrays. */
    public static function rows($conn, string $sql, array $params = []): array
    {
        $r = $params === [] ? pg_query($conn, $sql) : pg_query_params($conn, $sql, $params);
        if ($r === false) {
            throw new RuntimeException('query failed: ' . pg_last_error($conn) . ' -- ' . $sql);
        }
        $out = [];
        while ($row = pg_fetch_assoc($r)) {
            $out[] = $row;
        }
        return $out;
    }

    public static function one($conn, string $sql, array $params = []): ?array
    {
        $rows = self::rows($conn, $sql, $params);
        return $rows[0] ?? null;
    }

    /** Every mutating class owns a fresh installation and an independent session. */
    public static function bootstrapTenant(string $group = 'characterization'): void
    {
        self::teardownTenant();
        self::$database = TestDatabase::create($group);
    }

    /**
     * Fast-forward every owned sequence in the tenant past the highest value
     * already present in its column.
     *
     * Saldi allocates several primary keys itself with "SELECT MAX(id)+1"
     * and inserts the id explicitly (finans/kassekladde.php:1045,
     * admin/bankfordeling.php:516, bank_integration/aiia_import.php:69),
     * so the serial sequences behind those tables never advance and a clone
     * of a used tenant starts out with sequences far behind its data. Any
     * fixture that relies on the column default would then collide with an
     * existing row. Re-syncing after the clone makes the throwaway tenant
     * behave like a freshly created one regardless of what the template has
     * been through.
     */
    public static function resyncSequences($conn): void
    {
        $stmts = self::rows($conn, <<<'SQL'
            SELECT 'SELECT setval('
                     || quote_literal(quote_ident(sn.nspname) || '.' || quote_ident(s.relname))
                     || ', COALESCE((SELECT MAX(' || quote_ident(a.attname) || ') FROM '
                     || quote_ident(tn.nspname) || '.' || quote_ident(t.relname) || '), 0) + 1, false)' AS stmt
            FROM pg_class s
            JOIN pg_namespace sn ON sn.oid = s.relnamespace
            JOIN pg_depend d ON d.objid = s.oid AND d.classid = 'pg_class'::regclass AND d.deptype IN ('a', 'i')
            JOIN pg_class t ON t.oid = d.refobjid
            JOIN pg_namespace tn ON tn.oid = t.relnamespace
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid
            WHERE s.relkind = 'S' AND sn.nspname NOT IN ('pg_catalog', 'information_schema')
        SQL);

        foreach ($stmts as $row) {
            if (pg_query($conn, $row['stmt']) === false) {
                throw new RuntimeException('sequence resync failed: ' . pg_last_error($conn));
            }
        }
    }

    /**
     * Allocate the next primary key the way the legacy pages do (MAX(id)+1).
     *
     * Used by fixtures that must mirror the application's own allocation
     * rather than the column default - see resyncSequences() for why the two
     * disagree in a real tenant.
     */
    public static function nextId($conn, string $table): int
    {
        if (!preg_match('/^[a-z0-9_]+$/', $table)) {
            throw new RuntimeException('unsafe table name');
        }
        $r = self::one($conn, "SELECT COALESCE(MAX(id), 0) + 1 AS id FROM $table");
        return (int)$r['id'];
    }

    public static function teardownTenant(): void
    {
        if (self::$database !== null) {
            self::$database->release();
            self::$database = null;
        }
    }

    /**
     * Run a support script in a child PHP process (isolates the legacy page
     * scripts' exit()/die() calls from the PHPUnit process).
     *
     * @return array{exit:int, stdout:string, stderr:string}
     */
    public static function runChild(string $script, array $args = []): array
    {
        $cmd = array_merge([PHP_BINARY, $script], array_map('strval', $args));
        // stderr goes to a temp file, not a pipe: with SALDI_CHAR_DEBUG the
        // child writes whole pages to stderr before its stdout is done, and a
        // full stderr pipe would block it while we wait on stdout.
        $errFile = tmpfile();
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $errFile], $pipes);
        if (!is_resource($proc)) {
            fclose($errFile);
            throw new RuntimeException('could not start child php');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($proc);
        rewind($errFile);
        $stderr = stream_get_contents($errFile);
        fclose($errFile);
        return ['exit' => $exit, 'stdout' => (string)$stdout, 'stderr' => (string)$stderr];
    }
}
