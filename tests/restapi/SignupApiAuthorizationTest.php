<?php
// tests/restapi/SignupApiAuthorizationTest.php
//
// Integration test for api/signup_api.php: the dedicated server-to-server
// sign-up endpoint that creates a regnskab (a master regnskab row + a full
// tenant database) for a public sign-up, WITHOUT the pre-SD-615 anonymous
// bypass. The trusted caller authenticates with an HMAC signature over the
// posted fields (shared key in master settings: signup_api_key/var_grp=signup).
//
// Asserts: an unsigned or wrongly-signed or stale request is refused and
// creates nothing; a correctly signed request creates the regnskab + tenant
// db + administrator user (then cleans everything up).
//
// The test seeds its own signup_api_key into the master settings table and
// restores the previous value on teardown, so it never depends on, nor leaks,
// a production key.
//
// NOTE: api/signup_api.php has no live caller yet - the current same-host
// sign-up pages call create_regnskab() directly. The endpoint (and this test)
// are kept ready for a future off-host, likely email-driven activation flow.
//
// History:
// 20261012 CL/NTR Created alongside includes/opretfunc.php + api/signup_api.php.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class SignupApiAuthorizationTest extends TestCase
{
    private const ENDPOINT = '/api/signup_api.php';

    /** @var string */
    private static $key;

    /** @var bool whether a signup_api_key row existed before the test seeded one */
    private static $hadKey = false;

    /** @var string|null previous var_value to restore */
    private static $prevKey = null;

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }

        self::$key = bin2hex(random_bytes(32));

        // Seed signup_api_key, remembering any prior value so teardown restores it.
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        $rows = RestApiEnv::rows(
            $master,
            "SELECT var_value FROM settings WHERE var_name = 'signup_api_key' AND var_grp = 'signup'"
        );
        if ($rows !== []) {
            self::$hadKey = true;
            self::$prevKey = $rows[0]['var_value'];
            pg_query_params(
                $master,
                "UPDATE settings SET var_value = $1 WHERE var_name = 'signup_api_key' AND var_grp = 'signup'",
                [self::$key]
            );
        } else {
            pg_query_params(
                $master,
                "INSERT INTO settings (var_name, var_grp, var_value) VALUES ('signup_api_key', 'signup', $1)",
                [self::$key]
            );
        }
        pg_close($master);
    }

    public static function tearDownAfterClass(): void
    {
        if (!isset(self::$key)) {
            return; // skipped before seeding
        }
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        if (self::$hadKey) {
            pg_query_params(
                $master,
                "UPDATE settings SET var_value = $1 WHERE var_name = 'signup_api_key' AND var_grp = 'signup'",
                [self::$prevKey]
            );
        } else {
            pg_query($master, "DELETE FROM settings WHERE var_name = 'signup_api_key' AND var_grp = 'signup'");
        }
        pg_close($master);
    }

    /** Canonical signature - must match signup_sign() in api/signup_api.php. */
    private static function sign(array $fields, string $key): string
    {
        ksort($fields);
        $parts = [];
        foreach ($fields as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        return hash_hmac('sha256', implode("\n", $parts), $key);
    }

    /** A valid field set for a fresh random account name. */
    private function freshFields(string $regnskab): array
    {
        return [
            'regnskab'     => $regnskab,
            'brugernavn'   => 'signupadmin',
            'passwd'       => 'signup-password-2026',
            'passwd2'      => 'signup-password-2026',
            'std_kto_plan' => 'on',
            'email'        => 'signup@example.invalid',
            'ts'           => (string) time(),
        ];
    }

    /** Assert no regnskab row and no tenant database exist for $regnskab; clean up either if present. */
    private function assertNothingCreated(string $regnskab): void
    {
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        $rows = RestApiEnv::rows($master, 'SELECT id, db FROM regnskab WHERE regnskab = $1', [$regnskab]);
        $this->dropRows($master, $rows);
        pg_close($master);
        $this->assertSame([], $rows, "an unauthorized request must not create a regnskab row ($regnskab)");
    }

    /** Terminate, drop and delete every tenant db + regnskab row in $rows. Cleanup before assertions. */
    private function dropRows($master, array $rows): void
    {
        foreach ($rows as $row) {
            $dbName = $row['db'];
            if ($dbName !== '' && preg_match('/^[a-z0-9_]+$/', $dbName)) {
                pg_query_params(
                    $master,
                    'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()',
                    [$dbName]
                );
                pg_query($master, "DROP DATABASE IF EXISTS $dbName");
            }
            pg_query_params($master, 'DELETE FROM regnskab WHERE id = $1', [$row['id']]);
        }
    }

    public function test_unsigned_request_is_refused_and_creates_nothing(): void
    {
        $regnskab = 'sgnprobe' . bin2hex(random_bytes(4));
        $fields = $this->freshFields($regnskab); // no 'sig'
        $res = RestApiEnv::httpForm('POST', self::ENDPOINT, $fields);

        $this->assertSame(403, $res['status'], 'unsigned request must be forbidden: ' . $res['body']);
        $this->assertNothingCreated($regnskab);
    }

    public function test_wrong_signature_is_refused_and_creates_nothing(): void
    {
        $regnskab = 'sgnprobe' . bin2hex(random_bytes(4));
        $fields = $this->freshFields($regnskab);
        $fields['sig'] = self::sign($fields, 'not-the-real-key');
        $res = RestApiEnv::httpForm('POST', self::ENDPOINT, $fields);

        $this->assertSame(403, $res['status'], 'wrong signature must be forbidden: ' . $res['body']);
        $this->assertNothingCreated($regnskab);
    }

    public function test_stale_timestamp_is_refused_and_creates_nothing(): void
    {
        $regnskab = 'sgnprobe' . bin2hex(random_bytes(4));
        $fields = $this->freshFields($regnskab);
        $fields['ts'] = (string) (time() - 3600); // outside the 5-minute window
        $fields['sig'] = self::sign($fields, self::$key); // correctly signed, but stale
        $res = RestApiEnv::httpForm('POST', self::ENDPOINT, $fields);

        $this->assertSame(403, $res['status'], 'stale ts must be refused: ' . $res['body']);
        $this->assertNothingCreated($regnskab);
    }

    public function test_valid_signature_creates_regnskab_tenant_and_admin_user(): void
    {
        $regnskab = 'sgnprobe' . bin2hex(random_bytes(4));
        $fields = $this->freshFields($regnskab);
        $fields['sig'] = self::sign($fields, self::$key);

        $createdDb = null;
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        try {
            $res = RestApiEnv::httpForm('POST', self::ENDPOINT, $fields);
            $this->assertSame(200, $res['status'], 'valid request should create the account: ' . $res['body']);

            $json = json_decode($res['body'], true);
            $this->assertIsArray($json, 'response should be JSON: ' . $res['body']);
            $this->assertTrue(!empty($json['ok']), 'response ok flag: ' . $res['body']);
            $this->assertNotEmpty($json['db_id'], 'response should carry the new db_id');

            $rows = RestApiEnv::rows($master, 'SELECT id, db FROM regnskab WHERE regnskab = $1', [$regnskab]);
            $this->assertCount(1, $rows, 'exactly one regnskab row should exist');
            $createdDb = $rows[0]['db'];
            $this->assertSame($json['db'], $createdDb, 'response db should match the stored regnskab.db');

            $exists = RestApiEnv::rows($master, 'SELECT 1 FROM pg_database WHERE datname = $1', [$createdDb]);
            $this->assertNotSame([], $exists, "tenant database $createdDb should have been created");

            // The administrator user must exist in the new tenant with a hashed password.
            $tenant = RestApiEnv::connect($createdDb);
            $users = RestApiEnv::rows($tenant, 'SELECT brugernavn, kode FROM brugere WHERE brugernavn = $1', ['signupadmin']);
            pg_close($tenant);
            $this->assertCount(1, $users, 'the administrator user should exist in the tenant');
            $this->assertNotEmpty($users[0]['kode'], 'the administrator password hash should be set');
        } finally {
            // Always remove the tenant + regnskab row, whatever happened above.
            $rows = RestApiEnv::rows($master, 'SELECT id, db FROM regnskab WHERE regnskab = $1', [$regnskab]);
            $this->dropRows($master, $rows);
            pg_close($master);
        }
    }
}
