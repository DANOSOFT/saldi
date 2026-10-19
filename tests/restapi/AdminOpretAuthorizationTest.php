<?php
// tests/restapi/AdminOpretAuthorizationTest.php
//
// Integration test for admin/opret.php (SD-615): creating an accounting
// entity (a regnskab row plus a full tenant database) must require an
// authenticated super-admin session ($db == $sqdb), regardless of which
// form fields are present in the request. Asserts the refusal: no
// regnskab row and no database for an anonymous request.
//
// History:
// 20260803 CL/SZ SD-615: created.
// 20260804 CL/SZ Assert the logout refusal body (not just status != 0) so the
//                test fails on a 500 instead of passing on any failure.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class AdminOpretAuthorizationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
    }

    public function test_anonymous_post_with_all_fields_is_refused_and_creates_nothing(): void
    {
        $regnskab = 'sd615probe' . bin2hex(random_bytes(4));

        // No cookie jar, no session, no credentials — mirrors the ticket's
        // verification request exactly.
        $res = RestApiEnv::httpForm('POST', '/admin/opret.php', [
            'regnskab' => $regnskab,
            'brugernavn' => 'probeadmin',
            'passwd' => 'probe-password-2026',
            'passwd2' => 'probe-password-2026',
        ]);

        $this->assertNotSame(0, $res['status'], 'admin/opret.php was not reachable at all');
        $this->assertStringContainsString(
            'logud.php',
            $res['body'],
            'an anonymous request must reach the logout refusal path'
        );

        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        $rows = RestApiEnv::rows($master, 'SELECT id, db FROM regnskab WHERE regnskab = $1', [$regnskab]);

        // Clean up FIRST, before any assertion — a failing assertion throws,
        // and we must not leave a stray tenant database behind either way.
        $createdDb = null;
        foreach ($rows as $row) {
            $dbName = $row['db'];
            if ($dbName !== '' && preg_match('/^[a-z0-9_]+$/', $dbName)) {
                $exists = RestApiEnv::rows($master, 'SELECT 1 FROM pg_database WHERE datname = $1', [$dbName]);
                if ($exists !== []) {
                    $createdDb = $dbName;
                    pg_query_params(
                        $master,
                        'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()',
                        [$dbName]
                    );
                    pg_query($master, "DROP DATABASE IF EXISTS $dbName");
                }
            }
            pg_query_params($master, 'DELETE FROM regnskab WHERE id = $1', [$row['id']]);
        }
        pg_close($master);

        $this->assertSame([], $rows, 'an anonymous request must not create a regnskab row');
        $this->assertNull($createdDb, 'an anonymous request must not create a tenant database');
    }
    /**
     * DEFECT PINNED: an anonymous POST creates a working accounting entity.
     *
     * No cookie, no token, no credentials - just the four form fields.
     */
    public function test_creating_an_accounting_entity_does_not_require_a_session(): void
    {
        // No cookie jar, no Authorization header - a bare form post.
        $res = self::postForm('/admin/opret.php', [
            'regnskab' => self::ENTITY,
            'brugernavn' => 'probeadmin',
            'passwd' => 'Probe-2026-not-a-real-password',
            'passwd2' => 'Probe-2026-not-a-real-password',
        ]);

        $this->assertSame(200, $res['status'], 'the request was served: ' . substr($res['body'], 0, 200));

        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        $created = RestApiEnv::rows(
            $master,
            'SELECT db FROM regnskab WHERE regnskab = $1',
            [self::ENTITY]
        );
        pg_close($master);

        $this->assertCount(
            1,
            $created,
            'an anonymous request registered a new accounting entity - admin/opret.php:118 '
            . 'only checks the session when the form fields are missing'
        );

        // Not just a registry row: a real database with the full schema and a
        // usable administrator account.
        $tenantDb = (string)$created[0]['db'];
        $this->assertMatchesRegularExpression('/^saldi_\d+$/', $tenantDb, 'a generated tenant database');

        $tenant = RestApiEnv::connect($tenantDb);
        $tables = RestApiEnv::rows(
            $tenant,
            "SELECT count(*) AS n FROM information_schema.tables WHERE table_schema = 'public'"
        );
        $users = RestApiEnv::rows($tenant, 'SELECT brugernavn FROM brugere');
        pg_close($tenant);

        $this->assertGreaterThan(50, (int)$tables[0]['n'], 'the full schema was installed');
        $this->assertSame('probeadmin', trim((string)$users[0]['brugernavn']), 'with an administrator the caller chose');
    }

    /**
     * The same request with one field left out DOES hit the session check and
     * is refused - which is what shows the check exists and is simply on the
     * wrong side of the condition.
     */
    public function test_the_same_request_missing_one_field_is_refused(): void
    {
        $res = self::postForm('/admin/opret.php', [
            'regnskab' => self::ENTITY,
            'brugernavn' => 'probeadmin',
            'passwd' => 'Probe-2026-not-a-real-password',
            // passwd2 deliberately omitted
        ]);

        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        $created = RestApiEnv::rows($master, 'SELECT db FROM regnskab WHERE regnskab = $1', [self::ENTITY]);
        pg_close($master);

        $this->assertSame([], $created, 'nothing is created when the session check runs');
        $this->assertMatchesRegularExpression(
            '/logud\.php|ikke noget at g/i',
            $res['body'],
            'the session check sends the caller to the logout page'
        );
    }

    /**
     * Post an HTML form body (the legacy pages do not speak JSON).
     *
     * @param array<string,string> $fields
     * @return array{status:int, json:?array, body:string}
     */
    private static function postForm(string $path, array $fields): array
    {
        $ch = curl_init(str_replace('/saldi', '', RestApiEnv::baseUrl()) . '/saldi' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'json' => null, 'body' => $raw === false ? '' : (string)$raw];
    }
}
