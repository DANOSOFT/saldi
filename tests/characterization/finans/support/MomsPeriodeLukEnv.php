<?php
// tests/characterization/finans/support/MomsPeriodeLukEnv.php
//
// Throwaway-tenant environment for the SD-646 absent-schema regression test.
// Same connection-detail env vars as tests/restapi/support/RestApiEnv.php
// (SALDI_CHAR_PGHOST/PGUSER/PGPASS/MASTER_DB/TEMPLATE_DB) for consistency, but
// its own dedicated test tenant name - not shared with the REST API suite's
// saldi_apitest - so dropping/recreating the period-lock schema here can
// never race or interfere with any other suite's use of a shared tenant.
//
// Skips cleanly when the stack is not reachable, so `composer test` stays
// green on a bare checkout.
//
// History:
// 20260815 CL/SZ SD-646: created.

final class MomsPeriodeLukEnv
{
    public const SESSION_ID = 'sd646momsperiodetestsession00001'; // 32 chars, matches online.session_id varchar(32)

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
        return getenv('SALDI_MOMSPERIODE_TEST_DB') ?: 'saldi_momsperiode_test';
    }

    /** Returns null when usable, otherwise a human skip-reason. */
    public static function unavailableReason(): ?string
    {
        if (!extension_loaded('pgsql')) {
            return 'pgsql extension not loaded (run inside the docker web container)';
        }
        $conn = @pg_connect(self::connString(self::masterDb()), PGSQL_CONNECT_FORCE_NEW);
        if ($conn === false) {
            return 'postgres not reachable at host "' . self::pgHost() . '" (is the docker-compose stack up?)';
        }
        $r = pg_query_params($conn, 'SELECT 1 FROM pg_database WHERE datname = $1', [self::templateDb()]);
        $exists = $r !== false && pg_num_rows($r) === 1;
        pg_close($conn);
        if (!$exists) {
            return 'template tenant db "' . self::templateDb() . '" does not exist';
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
     * Clone the template tenant, register it in master regnskab, and seed the
     * online-session row the classic page scripts authenticate by (mirrors
     * includes/online.php:130's session_id lookup).
     */
    public static function bootstrapTenant(): void
    {
        $master = self::connect(self::masterDb());
        $test = self::testDb();
        $template = self::templateDb();
        if (!preg_match('/^[a-z0-9_]+$/', $test) || !preg_match('/^[a-z0-9_]+$/', $template)) {
            throw new RuntimeException('unsafe database name');
        }

        foreach ([$test, $template] as $dbName) {
            pg_query_params(
                $master,
                'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()',
                [$dbName]
            );
        }
        pg_query($master, "DROP DATABASE IF EXISTS $test");
        if (pg_query($master, "CREATE DATABASE $test TEMPLATE $template") === false) {
            throw new RuntimeException('could not clone template tenant: ' . pg_last_error($master));
        }

        pg_query_params($master, "DELETE FROM regnskab WHERE db = $1", [$test]);
        pg_query_params(
            $master,
            "INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
             SELECT 'sd646test', dbhost, dbuser, $1, version, sidst, brugerantal, 1000000, '', administrator
             FROM regnskab WHERE db = $2",
            [$test, $template]
        );

        $tenant = self::connect($test);
        $ra = self::one($tenant, "SELECT max(kodenr) AS ra FROM grupper WHERE art = 'RA'");
        $regnaar = (int)($ra['ra'] ?? (int)date('Y'));
        pg_close($tenant);

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
            [self::SESSION_ID, 'sd646test', $test, self::pgUser(), $rettigheder, $regnaar, (string)time()]
        );
        if ($r === false) {
            $err = pg_last_error($master);
            pg_close($master);
            throw new RuntimeException('could not seed the online session row: ' . $err);
        }
        pg_close($master);
    }
}
