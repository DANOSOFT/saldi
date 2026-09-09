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
// Each test class bootstraps the tenant in setUpBeforeClass() and drops it
// again in tearDownAfterClass() (teardownTenant()), so the seeded login only
// exists while a class is running. Set SALDI_REST_KEEP_TENANT=1 to keep it
// around for inspecting a failure.
//
// Skips cleanly when the stack is not reachable (no pgsql/curl extension or
// no postgres/web host), so `composer test` stays green on a bare checkout.
//
// History:
// 20260723 CL/LH SD-602: created.
// 20260904 CL/NTR Reset the cached login when the tenant is re-bootstrapped
//                 (each bootstrap re-inserts the regnskab rows with new ids,
//                 which invalidated a token cached by an earlier test class);
//                 added authHeaders(), loginData(), refreshToken(),
//                 regnskabId() and signToken() for the wider endpoint suite.
// 20260904 CL/NTR Seeded API user's password is no longer a literal: it comes
//                 from tests/TestCredentials.php, random per process (override
//                 with SALDI_TEST_PASSWORD_RESTAPI to log in by hand).
// 20260904 CL/NTR teardownTenant(): drop the throwaway tenant + its regnskab
//                 rows after each test class (SALDI_REST_KEEP_TENANT=1 keeps
//                 them for debugging) so no seeded login outlives the run.

require_once dirname(__DIR__, 2) . '/TestCredentials.php';

final class RestApiEnv
{
    /** Username of the API user the suite seeds into its throwaway tenant (grants nothing by itself). */
    public const USER = 'apitest';
    public const ACCOUNT_OPEN = 'apitest';
    public const ACCOUNT_CLOSED = 'apitestclosed';

    /** @var array|null Decoded `data` of the seeded user's login, cached per bootstrap. */
    private static $loginData = null;

    /** @var bool True between bootstrapTenant() and teardownTenant(), so teardown is a no-op after a skipped setup. */
    private static $bootstrapped = false;

    /** Username of the seeded API user. */
    public static function user(): string
    {
        return self::USER;
    }

    /** Password of the seeded API user - random per process, see tests/TestCredentials.php. */
    public static function password(): string
    {
        return TestCredentials::password('restapi');
    }

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
        self::$loginData = null; // the regnskab ids change below, so any cached token is stale
        self::$bootstrapped = true;
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
        pg_query_params($tenant, 'DELETE FROM brugere WHERE brugernavn = $1', [self::user()]);
        pg_query_params(
            $tenant,
            "INSERT INTO brugere (brugernavn, kode, email, rettigheder, status) VALUES ($1, $2, $3, $4, true)",
            [self::user(), md5(self::password()), 'apitest@example.invalid', 'admin']
        );
        pg_close($tenant);
    }

    /**
     * Drop the throwaway tenant and its two master regnskab rows again, so the
     * seeded API user does not outlive the test class that created it. No-op
     * when nothing was bootstrapped in this process, or when
     * SALDI_REST_KEEP_TENANT=1 asks to keep the tenant for inspection.
     */
    public static function teardownTenant(): void
    {
        if (!self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = false;
        self::$loginData = null;
        if (getenv('SALDI_REST_KEEP_TENANT')) {
            return;
        }
        $test = self::testDb();
        if (!preg_match('/^[a-z0-9_]+$/', $test)) {
            throw new RuntimeException('unsafe database name');
        }
        $master = self::connect(self::masterDb());
        pg_query_params(
            $master,
            'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()',
            [$test]
        );
        $dropped = pg_query($master, "DROP DATABASE IF EXISTS $test");
        pg_query_params($master, 'DELETE FROM regnskab WHERE db = $1 OR regnskab IN ($2, $3)', [$test, self::ACCOUNT_OPEN, self::ACCOUNT_CLOSED]);
        $error = $dropped === false ? pg_last_error($master) : '';
        pg_close($master);
        if ($dropped === false) {
            throw new RuntimeException("could not drop throwaway tenant $test: $error");
        }
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

    /**
     * Minimal curl helper for classic $_POST-form pages (not the JSON REST API).
     *
     * @return array{status:int, body:string}
     */
    public static function httpForm(string $method, string $path, array $fields = []): array
    {
        $ch = curl_init(self::baseUrl() . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_POSTFIELDS => http_build_query($fields),
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $raw === false ? '' : (string)$raw];
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

    /**
     * The `data` block of a successful login for the seeded test user
     * (tokens, user, tenant). Cached until the next bootstrapTenant().
     *
     * @return array{access_token:string, refresh_token:string, token_type:string, expires_in:int, user:array, tenant:array}
     */
    public static function loginData(): array
    {
        if (self::$loginData === null) {
            $res = self::login(self::user(), self::password(), self::ACCOUNT_OPEN);
            $data = $res['json']['data'] ?? null;
            if (!is_array($data) || empty($data['access_token'])) {
                throw new RuntimeException('login for seeded test user failed: ' . $res['body']);
            }
            self::$loginData = $data;
        }
        return self::$loginData;
    }

    /** Access token for the seeded test user (cached until the next bootstrap). */
    public static function accessToken(): string
    {
        return self::loginData()['access_token'];
    }

    /** Refresh token for the seeded test user (cached until the next bootstrap). */
    public static function refreshToken(): string
    {
        return self::loginData()['refresh_token'];
    }

    /** Authorization header for the seeded test user, ready for http(). */
    public static function authHeaders(): array
    {
        return ['Authorization: Bearer ' . self::accessToken()];
    }

    /** Master `regnskab.id` for one of the registered account names (ACCOUNT_OPEN / ACCOUNT_CLOSED). */
    public static function regnskabId(string $account): int
    {
        $master = self::connect(self::masterDb());
        $rows = self::rows($master, 'SELECT id FROM regnskab WHERE regnskab = $1', [$account]);
        pg_close($master);
        if ($rows === []) {
            throw new RuntimeException("no regnskab row named $account (bootstrapTenant() not run?)");
        }
        return (int)$rows[0]['id'];
    }

    /** Null when tokens can be signed with the install's own JWT secret, otherwise a skip-reason. */
    public static function installSecretUnavailableReason(): ?string
    {
        require_once dirname(__DIR__, 3) . '/restapi/core/JWT.php';
        $path = JWT::secretPath();
        if (!is_readable($path)) {
            return "JWT secret $path is not readable from the test process (run inside the docker web container)";
        }
        return null;
    }

    /**
     * Sign an arbitrary JWT payload with the install's own secret, so tests can
     * hand the API tokens it would never issue itself (expired, wrong type,
     * foreign account id, ...). Requires installSecretUnavailableReason() === null.
     */
    public static function signToken(array $claims, int $ttl = 3600): string
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/restapi/core/JWT.php';
        require_once $root . '/restapi/core/JwtSecretProvisioning.php';
        JWT::setSecret(_jwtLoadSecret(JWT::secretPath()));
        return JWT::encode($claims, $ttl);
    }
}
