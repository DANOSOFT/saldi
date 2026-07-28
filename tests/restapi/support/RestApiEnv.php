<?php
// tests/restapi/support/RestApiEnv.php
//
// Environment for REST API integration tests (SD-602).
//
// The suite talks HTTP to the docker-compose stack's own REST API — never to
// a live server, never with committed production credentials. It provisions
// its own throwaway tenant (clone of an installed tenant) with a known test
// user, and registers two master `regnskab` rows for it: one open
// ("apitest") and one closed ("apitestclosed") so the closed-tenant
// rejection path can be exercised.
//
// Skips cleanly when the stack is not reachable (no pgsql/curl extension or
// no postgres/web host), so `composer test` stays green on a bare checkout.
//
// History:
// 20260723 CL/LH SD-602: created.

final class RestApiEnv
{
    public const USER = 'apitest';
    public const PASSWORD = 'apitest-local-2026';
    public const ACCOUNT_OPEN = 'apitest';
    public const ACCOUNT_CLOSED = 'apitestclosed';

    public static function baseUrl(): string
    {
        // In-container default; on the host use SALDI_REST_BASE_URL=http://localhost:5000/saldi
        return rtrim(getenv('SALDI_REST_BASE_URL') ?: 'http://localhost/saldi', '/');
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
        return getenv('SALDI_REST_TEST_DB') ?: 'saldi_apitest';
    }

    /** Returns null when usable, otherwise a human skip-reason. */
    public static function unavailableReason(): ?string
    {
        foreach (['pgsql', 'curl'] as $ext) {
            if (!extension_loaded($ext)) {
                return "$ext extension not loaded (run inside the docker web container)";
            }
        }
        $conn = @pg_connect(self::connString(self::masterDb()), PGSQL_CONNECT_FORCE_NEW);
        if ($conn === false) {
            return 'postgres not reachable at host "' . self::pgHost() . '" (is the docker-compose stack up?)';
        }
        $r = pg_query_params($conn, 'SELECT 1 FROM pg_database WHERE datname = $1', [self::templateDb()]);
        $exists = $r !== false && pg_num_rows($r) === 1;
        pg_close($conn);
        if (!$exists) {
            return 'template tenant db "' . self::templateDb() . '" does not exist (install the app + create a tenant first)';
        }
        $probe = self::http('GET', '/restapi/endpoints/v1/auth/login.php');
        if ($probe['status'] === 0) {
            return 'REST API not reachable at ' . self::baseUrl() . ' (override with SALDI_REST_BASE_URL)';
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

    /** Clone the template tenant, register open+closed regnskab rows, seed the API user. */
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

        pg_query_params($master, 'DELETE FROM regnskab WHERE db = $1 OR regnskab IN ($2, $3)', [$test, self::ACCOUNT_OPEN, self::ACCOUNT_CLOSED]);
        pg_query_params(
            $master,
            "INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
             SELECT $1, dbhost, dbuser, $2, version, sidst, brugerantal, 1000000, $3, administrator
             FROM regnskab WHERE db = $4",
            [self::ACCOUNT_OPEN, $test, '', $template]
        );
        pg_query_params(
            $master,
            "INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
             SELECT $1, dbhost, dbuser, $2, version, sidst, brugerantal, 1000000, $3, administrator
             FROM regnskab WHERE db = $4",
            [self::ACCOUNT_CLOSED, $test, 'on', $template]
        );
        pg_close($master);

        $tenant = self::connect($test);
        pg_query_params($tenant, 'DELETE FROM brugere WHERE brugernavn = $1', [self::USER]);
        pg_query_params(
            $tenant,
            "INSERT INTO brugere (brugernavn, kode, email, rettigheder, status) VALUES ($1, $2, $3, $4, true)",
            [self::USER, md5(self::PASSWORD), 'apitest@example.invalid', 'admin']
        );
        pg_close($tenant);
    }

    /**
     * Minimal curl helper.
     *
     * @return array{status:int, json:?array, body:string}
     */
    public static function http(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $ch = curl_init(self::baseUrl() . $path);
        $hdrs = array_merge(['Accept: application/json'], $headers);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
            $hdrs[] = 'Content-Type: application/json';
        }
        $opts[CURLOPT_HTTPHEADER] = $hdrs;
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $raw = $raw === false ? '' : (string)$raw;
        $json = json_decode($raw, true);
        return ['status' => $status, 'json' => is_array($json) ? $json : null, 'body' => $raw];
    }

    /** Login against the test tenant, return the decoded response json. */
    public static function login(string $username, string $password, string $account): array
    {
        return self::http('POST', '/restapi/endpoints/v1/auth/login.php', [
            'username' => $username,
            'password' => $password,
            'account_name' => $account,
        ]);
    }

    /** Access token for the seeded test user (cached per process). */
    public static function accessToken(): string
    {
        static $token = null;
        if ($token === null) {
            $res = self::login(self::USER, self::PASSWORD, self::ACCOUNT_OPEN);
            $token = $res['json']['data']['access_token'] ?? '';
            if ($token === '') {
                throw new RuntimeException('login for seeded test user failed: ' . $res['body']);
            }
        }
        return $token;
    }
}
