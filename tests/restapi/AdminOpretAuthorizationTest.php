<?php
// tests/restapi/AdminOpretAuthorizationTest.php
//
// SECURITY: does creating an accounting entity require being logged in?
//
// admin/opret.php creates a whole tenant - a new PostgreSQL database with the
// full ~116-table schema, seeded from importfiler/, plus an administrator
// account that can log into it. It is reached from the admin panel, so it
// ought to be behind the same session check as everything else there.
//
// It is not. The check is inside the branch taken when the form fields are
// MISSING (admin/opret.php:118):
//
//     if (!isset($_POST['regnskab']) || !$_POST['brugernavn']
//         || !$_POST['passwd'] || !$_POST['passwd2']) {
//         include("../includes/online.php");   // <- the only auth check
//         if ($db != $sqdb) { ...refuse... }
//     }
//
// Supply all four and includes/online.php is never loaded, so no session is
// ever consulted and the creation below it runs for anyone.
//
// This test proves it against the local docker stack and cleans up after
// itself. It is written as an assertion about the CURRENT behavior; when the
// authorization check is moved to the top of the file, it will fail, and it
// should then be rewritten to assert the refusal.
//
// See doc/TESTING_FINDINGS.md P1-4.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

require_once __DIR__ . '/support/RestApiTestCase.php';

final class AdminOpretAuthorizationTest extends RestApiTestCase
{
    private const ENTITY = 'chartest_authz_probe';

    protected function tearDown(): void
    {
        // Always clean up, whether or not the assertions passed: this test
        // creates a real database.
        $master = RestApiEnv::connect(RestApiEnv::masterDb());

        $rows = RestApiEnv::rows(
            $master,
            'SELECT db FROM regnskab WHERE regnskab = $1',
            [self::ENTITY]
        );
        pg_query_params($master, 'DELETE FROM regnskab WHERE regnskab = $1', [self::ENTITY]);

        foreach ($rows as $row) {
            $db = (string)$row['db'];
            if ($db === '' || !preg_match('/^saldi_\d+$/', $db)) {
                continue; // never touch a name that is not a generated tenant
            }
            pg_query_params(
                $master,
                'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()',
                [$db]
            );
            pg_query($master, "DROP DATABASE IF EXISTS $db");
        }
        pg_close($master);

        parent::tearDown();
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
