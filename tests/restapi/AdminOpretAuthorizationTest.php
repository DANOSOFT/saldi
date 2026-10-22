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
// 20261022 CL/NTR Dropped the "DEFECT PINNED" anonymous-create test: SD-615 fixed it, and it contradicted the refusal test.
//                 The missing-field test uses a per-run entity name and RestApiEnv::httpForm() (self::ENTITY was undefined).

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

    /** An anonymous request with one field left out is refused the same way. */
    public function test_the_same_request_missing_one_field_is_refused(): void
    {
        $regnskab = 'sd615probe' . bin2hex(random_bytes(4));

        $res = RestApiEnv::httpForm('POST', '/admin/opret.php', [
            'regnskab' => $regnskab,
            'brugernavn' => 'probeadmin',
            'passwd' => 'probe-password-2026',
            // passwd2 deliberately omitted
        ]);

        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        $created = RestApiEnv::rows($master, 'SELECT db FROM regnskab WHERE regnskab = $1', [$regnskab]);
        pg_close($master);

        $this->assertSame([], $created, 'nothing is created when the session check runs');
        $this->assertMatchesRegularExpression(
            '/logud\.php|ikke noget at g/i',
            $res['body'],
            'the session check sends the caller to the logout page'
        );
    }
}
