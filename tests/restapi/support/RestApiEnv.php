<?php
// tests/restapi/support/RestApiEnv.php
//
// Environment for REST API integration tests (SD-602).
//
// Each REST class installs an independent tenant through TestDatabase and
// registers its own open/closed account names for HTTP routing. The application
// master remains installed, but no existing tenant is used as a template.
// SALDI_TEST_KEEP_DATABASES=1 retains every group for inspection.
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
// 20261022 CL/NTR Removed the bootstrapTenantOnce()/$bootstrapped pair the master merge duplicated (fatal on load);
//                 bootstrapTenantOnce() now keys on $ownedDb, so a class teardown makes the next class re-provision.
//                 bootstrapTenant() no longer opens a stray master connection it never closed.
// 20261111 CDX/NTR Fresh UUID-owned tenant per REST class; no existing template dependency.

require_once dirname(__DIR__, 2) . '/TestCredentials.php';
require_once dirname(__DIR__, 2) . '/characterization/support/database/TestDatabase.php';

final class RestApiEnv
{
    /** Username of the API user the suite seeds into its throwaway tenant (grants nothing by itself). */
    public const USER = 'apitest';

    private static ?TestDatabase $database = null;
    private static $loginData = null;
    private static array $ownedRegnskabIds = [];

    /** Username of the seeded API user. */
    public static function user(): string
    {
        return self::USER;
    }

    /** Name of the open account this process registers (`apitest_<suffix>`). */
    public static function accountOpen(): string
    {
        return 'apitest_' . self::testDb();
    }

    /** Name of the closed account this process registers (`apitestclosed_<suffix>`). */
    public static function accountClosed(): string
    {
        return 'closed_' . self::testDb();
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
        return getenv('SALDI_CHAR_PGPASS') ?: '';
    }

    public static function masterDb(): string
    {
        return getenv('SALDI_CHAR_MASTER_DB') ?: 'saldi';
    }

    public static function testDb(): string
    {
        if (self::$database === null) {
            throw new RuntimeException('Bootstrap the REST group before asking for its database');
        }
        return self::$database->name();
    }

    public static function unavailableReason(): ?string
    {
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            return $reason;
        }
        if (!extension_loaded('curl')) {
            return 'curl extension not loaded';
        }
        return self::http('GET', '/restapi/endpoints/v1/auth/login.php')['status'] === 0
            ? 'REST API not reachable at the configured local URL' : null;
    }

    public static function connect(string $db)
    {
        return TestDatabase::connect($db);
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

    public static function bootstrapTenant(string $group = 'restapi'): void
    {
        self::teardownTenant();
        self::$database = TestDatabase::create($group);
        foreach ([self::accountOpen() => false, self::accountClosed() => true] as $account => $closed) {
            self::$ownedRegnskabIds[$account] = self::$database->registerAccount($account, $closed);
        }
        $tenant = self::connect(self::testDb());
        try {
            self::rows($tenant, "INSERT INTO brugere (brugernavn,kode,email,rettigheder,status)
                VALUES ($1,$2,$3,'admin',true)", [self::user(), md5(self::password()), 'apitest@example.invalid']);
        } finally {
            pg_close($tenant);
        }
    }

    public static function teardownTenant(): void
    {
        self::$loginData = null;
        if (self::$database !== null) {
            self::$database->release();
            self::$database = null;
        }
        self::$ownedRegnskabIds = [];
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
