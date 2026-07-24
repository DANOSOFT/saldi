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

final class CharacterizationEnv
{
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
        return getenv('SALDI_CHAR_PGPASS') ?: 'password';
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
        $conn = @pg_connect(self::connString(self::masterDb()), PGSQL_CONNECT_FORCE_NEW);
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

    private static function connString(string $db): string
    {
        return sprintf(
            'host=%s dbname=%s user=%s password=%s connect_timeout=3',
            self::pgHost(),
            $db,
            self::pgUser(),
            self::pgPass()
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
        $master = self::connect(self::masterDb());
        $test = self::testDb();
        $template = self::templateDb();

        // CREATE DATABASE ... TEMPLATE requires zero connections on both dbs.
        foreach ([$test, $template] as $dbName) {
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

        pg_query_params($master, 'DELETE FROM online WHERE session_id = $1', [self::SESSION_ID]);
        pg_query_params(
            $master,
            'INSERT INTO online (session_id, brugernavn, db, dbuser, rettigheder, regnskabsaar, logtime, revisor, language_id)
             VALUES ($1, $2, $3, $4, $5, $6, $7, false, 0)',
            // rettigheder is a positional digit string, one char per module
            // (includes/online.php:331 checks substr($rettigheder,$modulnr,1)).
            [self::SESSION_ID, 'chartest', $test, self::pgUser(), str_repeat('9', 50), $regnaar, (string)time()]
        );
        pg_close($master);
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
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('could not start child php');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        return ['exit' => $exit, 'stdout' => (string)$stdout, 'stderr' => (string)$stderr];
    }
}
