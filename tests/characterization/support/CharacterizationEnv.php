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
// Tenant strategy: each run drops and re-creates a dedicated throwaway
// tenant database (SALDI_CHAR_TEST_DB, default saldi_chartest) as a clone of
// an existing installed tenant (SALDI_CHAR_TEMPLATE_DB, default saldi_2),
// then registers it in the master `regnskab` table and seeds an `online`
// session row so the legacy page scripts accept a fabricated session id.
// Nothing in the template tenant or the master registry outside these rows
// is touched.
//
// History:
// 20260723 CL/LH SD-601: created.
// 20260725 CL/LH Re-sync sequences after the clone; add nextId() for fixtures that mirror the app's MAX(id)+1 allocation.
// 20260916 CDX/LH Isolate disposable test tenants and retain child-process failure diagnostics.
// 20261022 CL/NTR bootstrapTenantOnce() is guarded by $ownsTenant, so a teardown by one suite makes the next one re-clone.
//                 runChild() collects stderr in a temp file so a chatty child cannot deadlock on a full pipe.

final class CharacterizationEnv
{
    private static bool $ownsTenant = false;

    public const SESSION_ID = 'saldichartestsession000000000001'; // 32 chars, matches online.session_id varchar(32)

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

    public static function templateDb(): string
    {
        return getenv('SALDI_CHAR_TEMPLATE_DB') ?: 'saldi_2';
    }

    public static function testDb(): string
    {
        return getenv('SALDI_CHAR_TEST_DB') ?: 'saldi_chartest';
    }

    public static function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /** Returns null when the environment is usable, otherwise a human skip-reason. */
    public static function unavailableReason(): ?string
    {
        if (!extension_loaded('pgsql')) {
            return 'pgsql extension not loaded (run inside the docker web container)';
        }
        self::assertSafeDatabases();
        if (self::pgPass() === '') {
            return 'SALDI_CHAR_PGPASS is required for database characterization tests';
        }
        $conn = pg_connect(self::connString(self::masterDb()), PGSQL_CONNECT_FORCE_NEW);
        if ($conn === false) {
            return 'postgres not reachable at host "' . self::pgHost() . '" (is the docker-compose stack up?)';
        }
        $template = self::templateDb();
        $r = pg_query_params($conn, 'SELECT 1 FROM pg_database WHERE datname = $1', [$template]);
        $exists = $r !== false && pg_num_rows($r) === 1;
        pg_close($conn);
        if (!$exists) {
            return "template tenant db \"$template\" does not exist (install the app + create a tenant first, see tests/characterization/README.md)";
        }
        return null;
    }

    private static function quoteConnectionValue(string $value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    }

    private static function connString(string $db): string
    {
        return sprintf(
            'host=%s dbname=%s user=%s password=%s connect_timeout=3',
            self::quoteConnectionValue(self::pgHost()),
            self::quoteConnectionValue($db),
            self::quoteConnectionValue(self::pgUser()),
            self::quoteConnectionValue(self::pgPass())
        );
    }

    /** @return resource|\PgSql\Connection */
    public static function connect(string $db)
    {
        $conn = pg_connect(self::connString($db), PGSQL_CONNECT_FORCE_NEW);
        if ($conn === false) {
            throw new RuntimeException("could not connect to $db");
        }
        return $conn;
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

    /**
     * Drop + re-create the throwaway tenant as a clone of the template tenant
     * and register it in master regnskab/online. Idempotent per test run.
     */
    public static function bootstrapTenant(): void
    {
        self::assertSafeDatabases();
        $master = self::connect(self::masterDb());
        $test = self::testDb();
        $template = self::templateDb();

        // Only terminate sessions on the disposable test database; never disconnect template users.
        foreach ([$test] as $dbName) {
            pg_query_params(
                $master,
                'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()',
                [$dbName]
            );
        }
        // Identifiers can't be bound parameters; both names come from trusted env/defaults.
        if (!preg_match('/^[a-z0-9_]+$/', $test) || !preg_match('/^[a-z0-9_]+$/', $template)) {
            throw new RuntimeException('unsafe database name');
        }
        pg_query($master, "DROP DATABASE IF EXISTS $test");
        $r = pg_query($master, "CREATE DATABASE $test TEMPLATE $template");
        if ($r === false) {
            throw new RuntimeException('could not clone template tenant: ' . pg_last_error($master));
        }

        self::$ownsTenant = true;

        // Register the clone in the master registry (mirrors the template's row shape).
        pg_query_params($master, 'DELETE FROM regnskab WHERE db = $1', [$test]);
        pg_query_params(
            $master,
            "INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
             SELECT 'chartest', dbhost, dbuser, $1, version, sidst, brugerantal, 1000000, '', administrator
             FROM regnskab WHERE db = $2",
            [$test, $template]
        );

        // Seed the online-session row the legacy page scripts authenticate by.
        $tenant = self::connect($test);
        $ra = self::one($tenant, "SELECT max(kodenr) AS ra FROM grupper WHERE art = 'RA'");
        $regnaar = (int)($ra['ra'] ?? 1);
        pg_close($tenant);

        // `rettigheder` is a positional digit string, one character per module
        // (includes/online.php:331 checks substr($rettigheder, $modulnr, 1)),
        // so "all permissions" means filling it with 9s. Its width is not the
        // same everywhere - a fresh install declares varchar(30), older
        // tenants are wider - and overflowing it makes the INSERT fail
        // silently, leaving every page-driven test without a session. Take
        // the width from the column itself.
        $width = self::one(
            $master,
            "SELECT character_maximum_length AS n FROM information_schema.columns
             WHERE table_name = 'online' AND column_name = 'rettigheder'"
        );
        $rettigheder = str_repeat('9', max(1, (int)($width['n'] ?? 30)));

        pg_query_params($master, 'DELETE FROM online WHERE session_id = $1', [self::SESSION_ID]);
        $r = pg_query_params(
            $master,
            'INSERT INTO online (session_id, brugernavn, db, dbuser, rettigheder, regnskabsaar, logtime, revisor, language_id)
             VALUES ($1, $2, $3, $4, $5, $6, $7, false, 0)',
            [self::SESSION_ID, 'chartest', $test, self::pgUser(), $rettigheder, $regnaar, (string)time()]
        );
        if ($r === false) {
            $err = pg_last_error($master);
            pg_close($master);
            // Without this row every page-driven test runs without a session
            // and fails somewhere far away from the cause. Fail here instead.
            throw new RuntimeException('could not seed the online session row: ' . $err);
        }
        pg_close($master);
    }

    /**
     * Clone the tenant the first time any suite asks for it, and reuse it for
     * the rest of the run.
     *
     * Re-cloning per test class costs a few seconds each and buys nothing:
     * fixtures name their rows uniquely (see Fixtures), so suites do not
     * collide. Set SALDI_CHAR_FRESH_TENANT=1 to force a clone per class when
     * a suite genuinely needs virgin ledger state.
     *
     * Guarded by $ownsTenant rather than a separate flag, so a suite that
     * calls teardownTenant() (KassekladdePosting, OrderInvoice) makes the next
     * shared suite clone a new tenant instead of using the dropped one.
     */
    public static function bootstrapTenantOnce(): void
    {
        if (self::$ownsTenant && !getenv('SALDI_CHAR_FRESH_TENANT')) {
            return;
        }
        self::bootstrapTenant();
    }

    /**
     * Fast-forward every owned sequence in the tenant past the highest value
     * already present in its column.
     *
     * Saldi allocates several primary keys itself with "SELECT MAX(id)+1"
     * and inserts the id explicitly (finans/kassekladde.php:1045,
     * admin/bankfordeling.php:516),
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

    public static function assertSafeDatabases(): void
    {
        $test = self::testDb();
        foreach ([$test, self::templateDb(), self::masterDb()] as $name) {
            if (!preg_match('/^[a-z0-9_]+$/', $name)) {
                throw new RuntimeException('unsafe database name');
            }
        }
        if ($test === self::templateDb() || $test === self::masterDb()) {
            throw new RuntimeException('SALDI_CHAR_TEST_DB must differ from template and master databases');
        }
    }

    public static function teardownTenant(): void
    {
        if (!self::$ownsTenant) {
            return;
        }
        self::assertSafeDatabases();
        $master = self::connect(self::masterDb());
        try {
            $test = self::testDb();
            self::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()', [$test]);
            self::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $test));
            self::rows($master, "DELETE FROM online WHERE db = $1 AND session_id = $2", [$test, self::SESSION_ID]);
            self::rows($master, "DELETE FROM regnskab WHERE db = $1 AND regnskab = 'chartest'", [$test]);
            self::$ownsTenant = false;
        } finally {
            pg_close($master);
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
