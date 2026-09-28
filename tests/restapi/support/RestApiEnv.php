<?php
// tests/restapi/support/RestApiEnv.php
//
// Environment for REST API integration tests (SD-602).
//
// The suite talks HTTP to the docker-compose stack's own REST API — never to
// a live server, never with committed production credentials. It provisions
// its own throwaway tenant (clone of an installed tenant) with a known test
// user, and registers two master `regnskab` rows for it: one open
// (accountOpen()) and one closed (accountClosed()) so the closed-tenant
// rejection path can be exercised.
//
// The tenant database and both account names carry a random per-process
// suffix (testDb(), accountOpen(), accountClosed()), and the class remembers
// exactly which database it created and which regnskab ids it inserted.
// Teardown only ever removes those, so two runs against the same postgres,
// a run that crashed half-way, or a real account that happens to be called
// "apitest" are never touched.
//
// Each test class bootstraps the tenant in setUpBeforeClass() and drops it
// again in tearDownAfterClass() (teardownTenant()), so the seeded login only
// exists while a class is running. Set SALDI_REST_KEEP_TENANT=1 to keep it
// around for inspecting a failure (the next class in the same process still
// replaces it, so the last class's tenant is the one that survives).
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
// 20260928 CL/NTR Tenant db and account names get a random per-process suffix;
//                 bootstrap records the db it created and the regnskab ids it
//                 inserted, and teardown removes only those.
//                 No longer terminates sessions on the template db or deletes
//                 regnskab rows by name, so concurrent runs, stale leftovers
//                 and unrelated accounts are never touched.
//                 The master db name is validated like the test/template names.

require_once dirname(__DIR__, 2) . '/TestCredentials.php';

final class RestApiEnv
{
    /** Username of the API user the suite seeds into its throwaway tenant (grants nothing by itself). */
    public const USER = 'apitest';

    /** Master `regnskab.db` is varchar(25); a longer clone name would be registered truncated. */
    private const MAX_DB_NAME_LENGTH = 25;

    /** @var array|null Decoded `data` of the seeded user's login, cached per bootstrap. */
    private static $loginData = null;

    /** @var string|null Random per-process suffix shared by testDb(), accountOpen() and accountClosed(). */
    private static $suffix = null;

    /** @var string|null Tenant database this process created and still owns; null until bootstrapTenant() and after teardownTenant() drops it. */
    private static $ownedDb = null;

    /** @var array<string, int> Master `regnskab.id` of each row the current bootstrap inserted, keyed by account name. */
    private static $ownedRegnskabIds = [];

    /** Username of the seeded API user. */
    public static function user(): string
    {
        return self::USER;
    }

    /** Name of the open account this process registers (`apitest_<suffix>`). */
    public static function accountOpen(): string
    {
        return 'apitest_' . self::suffix();
    }

    /** Name of the closed account this process registers (`apitestclosed_<suffix>`). */
    public static function accountClosed(): string
    {
        return 'apitestclosed_' . self::suffix();
    }

    /** Eight hex chars, drawn once per process, so parallel runs never share a tenant or an account name. */
    private static function suffix(): string
    {
        if (self::$suffix === null) {
            self::$suffix = bin2hex(random_bytes(4));
        }
        return self::$suffix;
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

    /**
     * Name of this process's throwaway tenant database: the SALDI_REST_TEST_DB
     * prefix (default `saldi_apitest`) plus the per-process suffix, fixed for
     * the lifetime of the process.
     */
    public static function testDb(): string
    {
        return (getenv('SALDI_REST_TEST_DB') ?: 'saldi_apitest') . '_' . self::suffix();
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

    /**
     * Clone the template tenant, register open+closed regnskab rows, seed the
     * API user. Remembers the database and the regnskab ids it created so
     * teardownTenant() removes exactly those and nothing else. A tenant an
     * earlier class in this process left behind (SALDI_REST_KEEP_TENANT) is
     * dropped first; a database of the same name this process did not create
     * makes CREATE DATABASE fail rather than being taken over.
     */
    public static function bootstrapTenant(): void
    {
        self::$loginData = null; // the regnskab ids change below, so any cached token is stale
        if (self::$ownedDb !== null) {
            self::dropOwned();
        }
        $test = self::testDb();
        $template = self::templateDb();
        $master = self::masterDb();
        foreach ([$test, $template, $master] as $name) {
            if (!preg_match('/^[a-z0-9_]+$/', $name)) {
                throw new RuntimeException("unsafe database name \"$name\"");
            }
        }
        if (strlen($test) > self::MAX_DB_NAME_LENGTH) {
            throw new RuntimeException("test db name $test is longer than " . self::MAX_DB_NAME_LENGTH . ' chars (shorten SALDI_REST_TEST_DB)');
        }
        if ($test === $template || $test === $master) {
            throw new RuntimeException('SALDI_REST_TEST_DB must differ from template and master databases');
        }

        $master = self::connect($master);
        // Only ever touch our own clone; never disconnect whoever is using the template.
        if (pg_query($master, "CREATE DATABASE $test TEMPLATE $template") === false) {
            $error = pg_last_error($master);
            pg_close($master);
            throw new RuntimeException("could not clone template tenant: $error");
        }
        self::$ownedDb = $test;
        self::$ownedRegnskabIds = [];
        foreach ([self::accountOpen() => '', self::accountClosed() => 'on'] as $account => $lukket) {
            $rows = self::rows(
                $master,
                "INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version, sidst, brugerantal, posteringer, lukket, administrator)
                 SELECT $1, dbhost, dbuser, $2, version, sidst, brugerantal, 1000000, $3, administrator
                 FROM regnskab WHERE db = $4
                 RETURNING id",
                [$account, $test, $lukket, $template]
            );
            if (count($rows) !== 1) {
                pg_close($master);
                throw new RuntimeException("could not register $account: no regnskab row for template db $template to copy");
            }
            self::$ownedRegnskabIds[$account] = (int)$rows[0]['id'];
        }
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
        self::$loginData = null;
        if (self::$ownedDb === null || getenv('SALDI_REST_KEEP_TENANT')) {
            return;
        }
        self::dropOwned();
    }

    /**
     * Remove exactly what the last bootstrapTenant() created: the database it
     * cloned (after disconnecting its own sessions) and the regnskab rows whose
     * ids it recorded. Nothing is matched by name, so a stale tenant from an
     * older run, a parallel run's tenant or a real account called "apitest"
     * is never removed.
     */
    private static function dropOwned(): void
    {
        $test = self::$ownedDb;
        if ($test === null || !preg_match('/^[a-z0-9_]+$/', $test)) {
            throw new RuntimeException('unsafe database name');
        }
        $ids = array_values(self::$ownedRegnskabIds);
        $master = self::connect(self::masterDb());
        pg_query_params(
            $master,
            'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()',
            [$test]
        );
        $dropped = pg_query($master, "DROP DATABASE IF EXISTS $test");
        if ($ids !== []) {
            pg_query_params($master, 'DELETE FROM regnskab WHERE id = ANY($1::int[])', ['{' . implode(',', $ids) . '}']);
        }
        $error = $dropped === false ? pg_last_error($master) : '';
        pg_close($master);
        self::$ownedRegnskabIds = [];
        if ($dropped === false) {
            throw new RuntimeException("could not drop throwaway tenant $test: $error");
        }
        self::$ownedDb = null;
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
            $res = self::login(self::user(), self::password(), self::accountOpen());
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

    /** Master `regnskab.id` the current bootstrap inserted for one of its account names (accountOpen() / accountClosed()). */
    public static function regnskabId(string $account): int
    {
        if (!isset(self::$ownedRegnskabIds[$account])) {
            throw new RuntimeException("no regnskab row named $account was registered by this process (bootstrapTenant() not run?)");
        }
        return self::$ownedRegnskabIds[$account];
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
