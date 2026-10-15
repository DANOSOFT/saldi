<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileFoundationTest.php --- ver 5.0.0 --- 2026.10.08
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Address B1 identity, migration and security review findings.
// 20261001 CDX/LH Integrate current migration markers, live auth fixtures and independent timestamp rows.
// 20261001 CDX/LH Require declarations in diagnostic probes and expect undeclared endpoints to deny.
// 20261001 CDX/LH Verify mobile request headers and exposed ETag in exact-origin preflights.
// 20261001 CDX/LH Verify settings-provisioned MobilePay secrets, registry membership and replay rejection.
// 20261001 CDX/LH Verify the appended pool amount migration preserves existing legacy rows.
// 20261005 CDX/LH Keep fixture cleanup compatible with retained T3 provenance and current migration markers.
// 20261008 CL/LH Cover additive mobile Assist rights and family migration.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/core/Subject.php';
require_once __DIR__ . '/../../restapi/core/Rights.php';
require_once __DIR__ . '/../../restapi/core/RequestId.php';
require_once __DIR__ . '/../../restapi/core/JWT.php';
require_once __DIR__ . '/../../restapi/core/JwtSecretProvisioning.php';
require_once __DIR__ . '/../../restapi/core/logging.php';

final class MobileFoundationTest extends TestCase
{
    private static $registryDb;
    private static $registryUser;

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$registryUser !== null) {
            $master = RestApiEnv::connect(RestApiEnv::masterDb());
            $subject = Subject::build('registry', (int)self::$registryUser['id']);
            foreach (['refresh_sessions', 'auth_challenges', 'auth_versions'] as $table) {
                RestApiEnv::rows($master, "DELETE FROM $table WHERE subject=$1", [$subject]);
            }
            RestApiEnv::rows($master, 'DELETE FROM brugere WHERE id=$1', [self::$registryUser['id']]);
            pg_close($master);
        }
        RestApiEnv::teardownTenant();
        if (self::$registryDb !== null) {
            $master = RestApiEnv::connect(RestApiEnv::masterDb());
            RestApiEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()', [self::$registryDb]);
            RestApiEnv::rows($master, 'DROP DATABASE ' . self::$registryDb);
            pg_close($master);
        }
    }

    public function test_subject_round_trip_and_principal_namespaces(): void
    {
        $account = Subject::build('account', 12, 'ssl3.saldi.dk');
        $registry = Subject::build('registry', 12, 'ssl3.saldi.dk');
        $this->assertNotSame($account, $registry);
        $this->assertSame(['installation' => 'ssl3.saldi.dk', 'principal' => 'account', 'user_id' => 12], Subject::parse($account));
        $this->assertSame('registry', Subject::parse($registry)['principal']);
    }

    public function test_subject_rejects_noncanonical_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Subject::parse('ssl3.saldi.dk/account/012');
    }

    public function test_installation_identity_is_registry_backed_and_independent_of_host_and_later_env(): void
    {
        $original = getenv('SALDI_INSTALLATION_ID');
        $originalHost = ifset($_SERVER, 'HTTP_HOST');
        try {
            $installation = Subject::installation();
            putenv('SALDI_INSTALLATION_ID=foundation.example.invalid');
            $_SERVER['HTTP_HOST'] = 'attacker.example.invalid';
            $this->assertSame($installation . '/account/12', Subject::build('account', 12));
            $conn = RestApiEnv::connect(RestApiEnv::masterDb());
            $rows = RestApiEnv::rows($conn, "SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name='installation_id' AND user_id=0");
            $this->assertCount(1, $rows);
            $this->assertSame($installation, $rows[0]['var_value']);
            pg_close($conn);
        } finally {
            putenv($original === false ? 'SALDI_INSTALLATION_ID' : 'SALDI_INSTALLATION_ID=' . $original);
            if ($originalHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $originalHost;
            }
        }
    }

    public function test_rights_preserve_positions_and_read_only(): void
    {
        $this->assertSame(['finans' => true, 'debitor' => false, 'lager' => false, 'sager' => false, 'bilag' => true, 'admin' => false, 'assistant' => true], Rights::tenant('0010000000000000'));
        $this->assertTrue(Rights::allows('0020000000000000', 'kassekladde', 'read'));
        $this->assertFalse(Rights::allows('0020000000000000', 'kassekladde', 'write'));
        $this->assertTrue(Rights::tenant('0000010000000000')['debitor']);
        $this->assertTrue(Rights::tenant('0000000001000000')['lager']);
        $this->assertTrue(Rights::tenant('0100000000000000')['admin']);
        $this->assertTrue(Rights::tenant('0000000000000000', '0010000')['sager']);
        $this->assertFalse(Rights::tenant('1111111111111111', '0000000')['sager']);
        $this->assertTrue(Rights::allows('', 'varer', 'write'));
        $this->assertTrue(Rights::allows('0000000000000000', 'bilag', 'own'));
        $this->assertFalse(Rights::allows('0000000000000000', 'unknown', 'write', null, true));
        $this->assertFalse(Rights::allows('0000000000000000', 'varer', 'unknown', null, true));
        foreach (Rights::tenant('0000000000000000', null, true) as $flag) {
            $this->assertTrue($flag);
        }
    }

    public function test_registry_rights_decode_checkbox_flags_and_membership(): void
    {
        $this->assertSame(['admin' => true, 'oprette' => false, 'slette' => true, 'tenant_ids' => [12, 27]], Rights::registry('on,0,on,12,27,12'));
        $this->assertSame(['admin' => false, 'oprette' => true, 'slette' => false, 'tenant_ids' => [8]], Rights::registry(',on,0,8'));
        $this->assertSame([], Rights::registry(',,,')['tenant_ids']);
    }

    public function test_request_id_generation_and_redaction_before_truncation(): void
    {
        $id = RequestId::resolve(null);
        $this->assertSame($id, RequestId::resolve($id));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', RequestId::resolve('bad'));
        $secret = bin2hex(random_bytes(24));
        $redacted = redactRestLogData(json_encode(['image_base64' => str_repeat('a', 3000), 'nested' => ['refresh_token' => $secret, 'access_token' => $secret, 'code' => $secret, 'password' => $secret, 'authorization' => $secret]]));
        $this->assertStringNotContainsString($secret, json_encode($redacted));
        $this->assertSame('[REDACTED]', $redacted['image_base64']);
        $this->assertSame('[REDACTED]', redactRestLogData('{"password":"' . $secret));
    }

    public function test_fresh_tenant_steps_are_idempotent_and_keep_legacy_rows(): void
    {
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        RestApiEnv::rows($conn, "DROP TABLE IF EXISTS push_dispatch, notification_recipients, notifications, reminders, push_tokens, pool_files CASCADE");
        RestApiEnv::rows($conn, 'ALTER TABLE documents DROP COLUMN IF EXISTS pool_file_id');
        RestApiEnv::rows($conn, 'ALTER TABLE brugere DROP COLUMN IF EXISTS mobil_undtaget');
        RestApiEnv::rows($conn, "DELETE FROM settings WHERE var_grp='mobile' AND var_name='mobile_tenant_step'");
        $GLOBALS['connection'] = $conn;
        require_once __DIR__ . '/../../includes/mobileUpdates.php';
        mobileTenantUpdates($conn);
        $this->assertSame(array_key_last(mobileTenantUpdateSteps()), RestApiEnv::rows($conn, "SELECT var_value FROM settings WHERE var_name='mobile_tenant_step' AND var_grp='mobile'")[0]['var_value']);
        $this->assertCount(1, RestApiEnv::rows($conn, "SELECT id FROM settings WHERE var_name='mobile_tenant_step' AND var_grp='mobile'"));
        $columns = array_column(RestApiEnv::rows($conn, "SELECT column_name FROM information_schema.columns WHERE table_name='pool_files'"), 'column_name');
        foreach (['uploaded_by', 'client_id', 'sha256', 'status', 'storage_path', 'attached_at', 'attached_by', 'bilag_nr', 'document_ids', 'thumbnail_path', 'deleted_at', 'uploaded_at', 'content_sha256', 'norm_amount', 'vendor_name'] as $column) {
            $this->assertContains($column, $columns);
        }
        RestApiEnv::rows($conn, "INSERT INTO pool_files(filename, client_id) VALUES ('foundation.pdf',$1)", [RequestId::resolve(null)]);
        RestApiEnv::rows($conn, "INSERT INTO push_tokens(user_id, token, db_name) VALUES (12,$1,$2)", [bin2hex(random_bytes(16)), RestApiEnv::testDb()]);
        // Reapply after a deliberately reset marker to exercise all idempotent DDL and legacy rows.
        RestApiEnv::rows($conn, "DELETE FROM settings WHERE var_name='mobile_tenant_step' AND var_grp='mobile'");
        mobileTenantUpdates($conn);
        mobileTenantUpdates($conn);
        $push = RestApiEnv::rows($conn, 'SELECT subject, principal, last_seen_at, device_id FROM push_tokens')[0];
        $this->assertNull($push['subject']);
        $this->assertSame('account', $push['principal']);
        $this->assertNotNull($push['last_seen_at']);
        $this->assertNull($push['device_id']);
        $this->assertSame('ubehandlet', RestApiEnv::rows($conn, 'SELECT status FROM pool_files')[0]['status']);
        foreach (['push_tokens', 'notifications', 'notification_recipients', 'push_dispatch', 'reminders'] as $table) {
            $this->assertNotEmpty(RestApiEnv::rows($conn, 'SELECT to_regclass($1) AS name', [$table])[0]['name']);
        }
        RestApiEnv::rows($conn, 'BEGIN READ ONLY');
        mobileTenantUpdates($conn);
        RestApiEnv::rows($conn, 'ROLLBACK');
        $this->assertNotEmpty(RestApiEnv::rows($conn, "SELECT conname FROM pg_constraint WHERE conrelid='documents'::regclass AND confrelid='pool_files'::regclass"));
        pg_close($conn);
    }

    public function test_existing_pool_table_receives_normalized_amount_without_losing_legacy_rows(): void
    {
        require_once __DIR__ . '/../../includes/mobileUpdates.php';
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        mobileTenantUpdates($conn);
        $row = RestApiEnv::rows($conn, "INSERT INTO pool_files (filename,amount)
            VALUES ('foundation-legacy-amount.pdf','125.00') RETURNING id")[0];
        try {
            RestApiEnv::rows($conn, 'ALTER TABLE pool_files DROP COLUMN norm_amount');
            RestApiEnv::rows($conn, "UPDATE settings SET var_value='m07_push_delivery_identity'
                WHERE var_grp='mobile' AND var_name='mobile_tenant_step'");
            mobileTenantUpdates($conn);
            mobileTenantUpdates($conn);
            $this->assertSame([['id'=>$row['id'], 'filename'=>'foundation-legacy-amount.pdf',
                'amount'=>'125.00', 'norm_amount'=>null]], RestApiEnv::rows($conn,
                'SELECT id,filename,amount,norm_amount FROM pool_files WHERE id=$1', [$row['id']]));
            $marker = RestApiEnv::rows($conn, "SELECT var_value FROM settings
                WHERE var_grp='mobile' AND var_name='mobile_tenant_step'");
            $this->assertSame([['var_value'=>array_key_last(mobileTenantUpdateSteps())]], $marker);
        } finally {
            RestApiEnv::rows($conn, "UPDATE pool_files SET status='deleted',deleted_at=now() WHERE id=$1", [$row['id']]);
            pg_close($conn);
        }
    }

    public function test_fresh_registry_steps_are_idempotent_and_tenant_scoped_versions(): void
    {
        self::$registryDb = 'mobile_reg_' . bin2hex(random_bytes(4));
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows($master, 'CREATE DATABASE ' . self::$registryDb . ' TEMPLATE template0');
        pg_close($master);
        $conn = RestApiEnv::connect(self::$registryDb);
        RestApiEnv::rows($conn, 'CREATE TABLE settings (id serial PRIMARY KEY, var_name text, var_grp text, var_value text, user_id integer)');
        require_once __DIR__ . '/../../includes/mobileRegistryUpdates.php';
        $original = getenv('SALDI_INSTALLATION_ID');
        try {
            putenv('SALDI_INSTALLATION_ID=foundation.example.invalid');
            mobileRegistryUpdates($conn);
            putenv('SALDI_INSTALLATION_ID=later.example.invalid');
            mobileRegistryUpdates($conn);
            $this->assertSame('foundation.example.invalid', RestApiEnv::rows($conn, "SELECT var_value FROM settings WHERE var_name='installation_id'")[0]['var_value']);
            RestApiEnv::rows($conn, "DELETE FROM settings WHERE var_name='installation_id'");
            RestApiEnv::rows($conn, "UPDATE settings SET var_value='r04_subject_auth_versions' WHERE var_name='mobile_registry_step'");
            putenv('SALDI_INSTALLATION_ID');
            mobileRegistryUpdates($conn);
            $this->assertMatchesRegularExpression('/^install-[0-9a-f]{32}$/D', RestApiEnv::rows($conn, "SELECT var_value FROM settings WHERE var_name='installation_id'")[0]['var_value']);
        } finally {
            putenv($original === false ? 'SALDI_INSTALLATION_ID' : 'SALDI_INSTALLATION_ID=' . $original);
        }
        foreach (['refresh_sessions', 'auth_challenges', 'auth_rate_limits', 'auth_versions'] as $table) {
            $this->assertSame($table, RestApiEnv::rows($conn, 'SELECT to_regclass($1) AS name', [$table])[0]['name']);
        }
        $this->assertCount(1, RestApiEnv::rows($conn, "SELECT id FROM settings WHERE var_name='mobile_registry_step' AND var_grp='mobile'"));
        $this->assertSame(array_key_last(mobileRegistryUpdateSteps()), RestApiEnv::rows($conn, "SELECT var_value FROM settings WHERE var_name='mobile_registry_step'")[0]['var_value']);
        RestApiEnv::rows($conn, 'INSERT INTO auth_versions(subject,tenant_id) VALUES ($1,1),($1,2),($2,1)', [Subject::build('account', 12), Subject::build('registry', 12)]);
        $this->assertCount(3, RestApiEnv::rows($conn, 'SELECT * FROM auth_versions'));
        RestApiEnv::rows($conn, "DELETE FROM settings WHERE var_name='mobile_registry_step' AND var_grp='mobile'");
        mobileRegistryUpdates($conn);
        $this->assertCount(3, RestApiEnv::rows($conn, 'SELECT * FROM auth_versions'));
        RestApiEnv::rows($conn, 'BEGIN READ ONLY');
        mobileRegistryUpdates($conn);
        RestApiEnv::rows($conn, 'ROLLBACK');
        $schema = new MobileSchema($conn);
        try {
            $schema->run('rollback_probe', ['probe01' => function (MobileSchema $schema): void {
                $schema->execute('CREATE TABLE migration_rollback_probe (id integer)');
                throw new RuntimeException('Intentional rollback');
            }]);
            $this->fail('Failed migration must throw');
        } catch (RuntimeException $exception) {
            $this->assertSame('Intentional rollback', $exception->getMessage());
        }
        $this->assertNull(RestApiEnv::rows($conn, "SELECT to_regclass('migration_rollback_probe') AS name")[0]['name']);
        $this->assertSame([], RestApiEnv::rows($conn, "SELECT id FROM settings WHERE var_name='mobile_rollback_probe_step'"));
        pg_close($conn);
    }

    /** @return array{status:int,headers:array<string,string>,json:?array} */
    private function request(array $headers, ?array $body = null, ?string $url = null, ?string $method = null): array
    {
        $responseHeaders = [];
        $curl = curl_init($url ?? RestApiEnv::baseUrl() . '/restapi/endpoints/v1/auth/login.php');
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$responseHeaders): int {
                if (strpos($line, ':') !== false) {
                    [$key, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower($key)] = trim($value);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }
        if ($method !== null) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        }
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return ['status' => $status, 'headers' => $responseHeaders, 'json' => json_decode($raw, true)];
    }

    public function test_client_request_id_is_echoed_and_invalid_id_is_replaced(): void
    {
        $id = RequestId::resolve(null);
        $response = $this->request(['X-Request-Id: ' . $id]);
        $this->assertSame(405, $response['status']);
        $this->assertSame($id, $response['headers']['x-request-id']);
        $response = $this->request(['X-Request-Id: invalid']);
        $this->assertSame($response['headers']['x-request-id'], RequestId::resolve($response['headers']['x-request-id']));
        $this->assertNotSame('invalid', $response['headers']['x-request-id']);
        $this->assertArrayHasKey('x-request-id', $this->request([])['headers']);
    }

    public function test_cors_does_not_accept_localhost_substrings(): void
    {
        foreach (['https://localhost.attacker.example.invalid', 'https://attacker.example.invalid/127.0.0.1', 'https://saldi.dk.attacker.example.invalid'] as $origin) {
            $response = $this->request(['Origin: ' . $origin]);
            $this->assertArrayNotHasKey('access-control-allow-origin', $response['headers']);
            $this->assertArrayNotHasKey('access-control-allow-credentials', $response['headers']);
            $this->assertStringContainsString('Origin', $response['headers']['vary']);
        }
    }

    public function test_login_and_refresh_bodies_never_reach_tenant_log(): void
    {
        $id = RequestId::resolve(null);
        $response = $this->request(['X-Request-Id: ' . $id], ['username' => RestApiEnv::user(), 'password' => RestApiEnv::password(), 'account_name' => RestApiEnv::accountOpen(), 'image_base64' => bin2hex(random_bytes(24))]);
        $this->assertSame(200, $response['status']);
        $data = $response['json']['data'];
        $apiPath = __DIR__ . '/../../temp/api/.ht_rest_api.log';
        clearstatcache(true, $apiPath);
        $apiOffset = is_file($apiPath) ? filesize($apiPath) : 0;
        $refreshId = RequestId::resolve(null);
        $refresh = RestApiEnv::http('POST', '/restapi/endpoints/v1/auth/refresh.php', ['refresh_token' => $data['refresh_token']], ['X-Request-Id: ' . $refreshId]);
        $this->assertSame(200, $refresh['status'], $refresh['body']);
        $log = file_get_contents(__DIR__ . '/../../temp/' . RestApiEnv::testDb() . '/.ht_rest_api.log');
        $apiLog = file_get_contents($apiPath, false, null, $apiOffset);
        $this->assertStringContainsString($refreshId, $apiLog);
        $log .= $apiLog;
        $this->assertStringContainsString($id, $log);
        foreach ([RestApiEnv::password(), $data['access_token'], $data['refresh_token'], $refresh['json']['data']['access_token'], 'access_token', 'refresh_token', 'Login successful', 'image_base64'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $log);
        }
        foreach (array_filter(explode("\n", $log)) as $line) {
            $metadata = json_decode($line, true);
            $this->assertSame(['method', 'path', 'request_id', 'subject', 'status', 'duration'], array_keys($metadata));
        }
        $issued = explode('.', $data['access_token']);
        $issuedClaims = json_decode(base64_decode(strtr($issued[1], '-_', '+/')), true);
        $this->assertSame(Subject::build('account', $data['user']['id']), $issuedClaims['sub']);
        $this->assertSame('account', $issuedClaims['principal']);
    }

    public function test_tampered_jwt_still_fails(): void
    {
        $token = RestApiEnv::accessToken();
        [$header, $payload, $signature] = explode('.', $token);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $claims['user_id'] = (int)$claims['user_id'] + 1;
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        $response = RestApiEnv::http('GET', '/restapi/endpoints/v1/accounts/', null, ['Authorization: Bearer ' . "$header.$payload.$signature"]);
        $this->assertSame(401, $response['status']);
    }

    /** Send an actual Vipps signature, including the empty-key attack when requested. */
    private function webhook(string $db, string $reference, string $secret, bool $valid = true, ?string $date = null): int
    {
        $path = '/debitor/payments/mobilepay/webhook_recive.php?db=' . rawurlencode($db);
        $url = RestApiEnv::baseUrl() . $path;
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        $host .= $port ? ':' . $port : '';
        $uri = parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
        $body = json_encode(['reference' => $reference, 'name' => 'AUTHORIZED']);
        $date = $date ?? gmdate('c');
        $hash = base64_encode(hash('sha256', $body, true));
        $signature = base64_encode(hash_hmac('sha256', "POST\n$uri\n$date;$host;$hash", $secret, true));
        $auth = 'HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=' . $signature;
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => ['Content-Type: application/json',
                'X-Ms-Date: ' . $date, 'X-Ms-Content-Sha256: ' . $hash,
                'X-Vipps-Authorization: ' . ($valid ? $auth : bin2hex(random_bytes(32))),
            ],
        ]);
        curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return $status;
    }

    public function test_mobilepay_secret_failures_traversal_and_valid_signature(): void
    {
        // Let the HTTP process create its own tenant temp directory with the service user's permissions.
        $login = RestApiEnv::login(RestApiEnv::user(), RestApiEnv::password(), RestApiEnv::accountOpen());
        $this->assertSame(200, $login['status'], $login['body']);
        $directory = __DIR__ . '/../../temp/' . RestApiEnv::testDb();
        $this->assertDirectoryExists($directory);
        $secretPath = $directory . '/.ht_mobilepay_secret.txt';
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        $reference = 'foundation_' . bin2hex(random_bytes(8));
        $eventPath = $directory . '/data_' . $reference . '.json';
        $this->assertFileDoesNotExist($secretPath);
        $secret = bin2hex(random_bytes(32));
        try {
            RestApiEnv::rows($conn, "DELETE FROM settings WHERE var_grp='mobilepay' AND var_name='webhook_secret'");
            $this->assertSame(401, $this->webhook(RestApiEnv::testDb(), $reference, ''));
            $this->assertFileDoesNotExist($eventPath);
            foreach (['', bin2hex(random_bytes(7))] as $short) {
                RestApiEnv::rows($conn, "INSERT INTO settings (var_grp,var_name,var_value,user_id)
                    VALUES ('mobilepay','webhook_secret',$1,0)", [$short]);
                $this->assertSame(401, $this->webhook(RestApiEnv::testDb(), $reference, $short));
                $this->assertFileDoesNotExist($eventPath);
            }
            $olderSecret = bin2hex(random_bytes(32));
            foreach ([$olderSecret, $secret] as $provisioned) {
                RestApiEnv::rows($conn, "INSERT INTO settings (var_grp,var_name,var_value,user_id)
                    VALUES ('mobilepay','webhook_secret',$1,0)", [$provisioned]);
            }
            $this->assertSame(401, $this->webhook(RestApiEnv::testDb(), $reference, $olderSecret));
            $this->assertSame(401, $this->webhook(RestApiEnv::testDb(), $reference, $secret, false));
            $this->assertFileDoesNotExist($eventPath);
            $this->assertSame(400, $this->webhook('../' . RestApiEnv::testDb(), $reference, $secret));
            $this->assertSame(400, $this->webhook(RestApiEnv::testDb(), '../' . $reference, $secret));
            $this->assertFileDoesNotExist($eventPath);
            foreach ([gmdate('c', time() - 360), gmdate('c', time() + 360), 'not-a-date', 'now'] as $date) {
                $this->assertSame(401, $this->webhook(RestApiEnv::testDb(), $reference, $secret, true, $date));
                $this->assertFileDoesNotExist($eventPath);
            }
            // An existing DB with a provisioned secret still requires registry membership.
            $registry = RestApiEnv::connect(RestApiEnv::masterDb());
            $registrations = RestApiEnv::rows($registry, 'SELECT id FROM regnskab WHERE db=$1', [RestApiEnv::testDb()]);
            try {
                RestApiEnv::rows($registry, 'UPDATE regnskab SET db=$1 WHERE db=$2', ['unregistered_' . bin2hex(random_bytes(4)), RestApiEnv::testDb()]);
                $this->assertSame(401, $this->webhook(RestApiEnv::testDb(), $reference, $secret));
                $this->assertFileDoesNotExist($eventPath);
            } finally {
                foreach ($registrations as $registration) {
                    RestApiEnv::rows($registry, 'UPDATE regnskab SET db=$1 WHERE id=$2', [RestApiEnv::testDb(), $registration['id']]);
                }
                pg_close($registry);
            }
            $this->assertSame(200, $this->webhook(RestApiEnv::testDb(), $reference, $secret));
            $this->assertSame(['reference' => $reference, 'name' => 'AUTHORIZED'], json_decode(file_get_contents($eventPath), true));
            $this->assertFileDoesNotExist($secretPath);
            $this->assertSame(200, $this->webhook(RestApiEnv::testDb(), $reference, $secret, true, gmdate(DateTimeInterface::RFC7231)));
            // A newest short secret must fail closed even if an older valid secret remains.
            RestApiEnv::rows($conn, "INSERT INTO settings (var_grp,var_name,var_value,user_id)
                VALUES ('mobilepay','webhook_secret','',0)");
            unlink($eventPath);
            $this->assertSame(401, $this->webhook(RestApiEnv::testDb(), $reference, $secret));
            $this->assertFileDoesNotExist($eventPath);
        } finally {
            RestApiEnv::rows($conn, "DELETE FROM settings WHERE var_grp='mobilepay' AND var_name='webhook_secret'");
            pg_close($conn);
            if (file_exists($eventPath)) {
                unlink($eventPath);
            }
        }
    }

    /** Run a test-only endpoint through the real gate and sendResponse, in an isolated CLI child. */
    private function declaredEndpoint(string $token, array $extraHeaders = [], string $action = 'tenant'): array
    {
        $source = <<<'PROBE'
        $input = json_decode(stream_get_contents(STDIN), true);
        $headers = $input['headers'];
        $action = $input['action'];
        function getallheaders() { global $headers; return $headers; }
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foundation-test';
        $_SERVER['SCRIPT_NAME'] = '/foundation-test';
        require getcwd() . '/restapi/core/BaseEndpoint.php';
        class MobileRightsProbe extends BaseEndpoint {
            protected function requiredRight(): ?array { global $action; return $action === 'self' ? ['module' => 'auth', 'action' => 'self'] : ['module' => 'varer', 'action' => 'write']; }
            protected function handleGet($id = null) { $this->sendResponse(true, ['subject' => $this->subject]); }
        }
        register_shutdown_function(function () { file_put_contents('php://fd/3', (string)http_response_code()); });
        (new MobileRightsProbe())->handleRequestMethod();
        PROBE;
        $process = proc_open([PHP_BINARY, '-r', $source], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        fwrite($pipes[0], json_encode(['headers' => array_merge(['Authorization' => 'Bearer ' . $token], $extraHeaders), 'action' => $action]));
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        $status = (int)stream_get_contents($pipes[3]);
        fclose($pipes[3]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $errors);
        $this->assertSame('', $errors);
        $json = json_decode($body, true);
        $this->assertIsArray($json, $body);
        $json['_status'] = $status;
        return $json;
    }

    /**
     * Issue a registry fixture through B2 in an isolated process, retaining a live session.
     *
     * @return array{access_token:string,refresh_token:string,token_type:string,expires_in:int}
     */
    private function registryTokens(?int $tenantId): array
    {
        JWT::setSecret(_jwtLoadSecret(JWT::secretPath()));
        if (self::$registryUser === null) {
            $master = RestApiEnv::connect(RestApiEnv::masterDb());
            self::$registryUser = RestApiEnv::rows($master,
                "INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES ($1,$2,'on,,,',true) RETURNING *",
                ['foundation_' . bin2hex(random_bytes(8)), md5(TestCredentials::password('mobilefoundation'))])[0];
            pg_close($master);
        }
        $source = <<<'ISSUE'
        $_SERVER['REQUEST_URI'] = '/foundation-issuance';
        require getcwd() . '/restapi/core/BaseEndpoint.php';
        $input = json_decode(stream_get_contents(STDIN), true);
        $auth = new MobileAuth();
        $context = $auth->context('registry', $input['user_id'], $input['tenant_id']);
        echo json_encode($auth->create($context['user'], 'registry', $input['tenant_id'], 'foundation-device'));
        ISSUE;
        $process = proc_open([PHP_BINARY, '-r', $source], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        fwrite($pipes[0], json_encode(['user_id' => (int)self::$registryUser['id'], 'tenant_id' => $tenantId]));
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $errors);
        $this->assertSame('', $errors);
        $tokens = json_decode($body, true);
        $this->assertIsArray($tokens, $body);
        return $tokens;
    }

    public function test_declared_right_rejects_user_without_it_and_accepts_authorized_and_registry(): void
    {
        $login = RestApiEnv::loginData();
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        try {
            RestApiEnv::rows($conn, "UPDATE brugere SET rettigheder='0000000000000000' WHERE brugernavn=$1", [RestApiEnv::user()]);
            $denied = $this->declaredEndpoint($login['access_token']);
            $this->assertFalse($denied['success']);
            $this->assertSame('permission_denied', $denied['data']['error']);
            RestApiEnv::rows($conn, "UPDATE brugere SET rettigheder='0000000001000000' WHERE brugernavn=$1", [RestApiEnv::user()]);
            $this->assertTrue($this->declaredEndpoint($login['access_token'])['success']);
            $registry = $this->registryTokens((int)$login['tenant']['id'])['access_token'];
            $response = $this->declaredEndpoint($registry);
            $this->assertTrue($response['success']);
            $this->assertSame(Subject::build('registry', (int)self::$registryUser['id']), $response['data']['subject']);
        } finally {
            RestApiEnv::rows($conn, 'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2', ['admin', RestApiEnv::user()]);
            pg_close($conn);
        }
    }
    public function test_principal_tokens_cannot_select_tenant_by_header_and_registry_self_is_tenantless(): void
    {
        $login = RestApiEnv::loginData();
        $registry = $this->registryTokens(null);
        foreach (['registry', 'account'] as $principal) {
            $issued = $principal === 'registry' ? $registry['access_token'] : $login['access_token'];
            foreach ([null, 0, -1] as $tenant) {
                $claims = JWT::decode($issued);
                unset($claims['tenant_id']);
                if ($tenant !== null) {
                    $claims['tenant_id'] = $tenant;
                }
                $token = RestApiEnv::signToken($claims);
                $header = ['X-Tenant-ID' => (string)$login['tenant']['id']];
                $declared = $this->declaredEndpoint($token, $header);
                $this->assertSame(401, $declared['_status']);
                $this->assertFalse($declared['success']);
                $this->assertSame('invalid_token', $declared['data']['error']);
                $legacy = RestApiEnv::http('GET', '/restapi/endpoints/v1/accounts/', null,
                    ['Authorization: Bearer ' . $token, 'X-Tenant-ID: ' . $login['tenant']['id']]);
                $this->assertSame(401, $legacy['status'], $legacy['body']);
                $this->assertSame('invalid_token', $legacy['json']['data']['error']);
                if ($principal === 'registry' && $tenant === null) {
                    $this->assertTrue($this->declaredEndpoint($token, ['X-Tenant-ID' => '999999999'], 'self')['success']);
                }
            }
        }
    }

    /** Two pending runners on independent links must both wait, then observe one committed marker. */
    private function concurrentRunners(string $db, string $scope): void
    {
        $conn = RestApiEnv::connect($db);
        $marker = 'mobile_' . $scope . '_step';
        RestApiEnv::rows($conn, "DELETE FROM settings WHERE var_grp='mobile' AND var_name=$1", [$marker]);
        RestApiEnv::rows($conn, 'SELECT pg_advisory_lock(hashtext($1))', ['saldi:mobile:' . $scope]);
        $children = [];
        $source = <<<'RUNNER'
        require getcwd() . '/tests/restapi/support/RestApiEnv.php';
        [$db, $scope] = json_decode(stream_get_contents(STDIN), true);
        $conn = RestApiEnv::connect($db);
        RestApiEnv::rows($conn, "SET statement_timeout='10s'");
        echo pg_get_pid($conn) . "\n";
        flush();
        require getcwd() . '/includes/mobile' . ($scope === 'tenant' ? '' : 'Registry') . 'Updates.php';
        $runner = $scope === 'tenant' ? 'mobileTenantUpdates' : 'mobileRegistryUpdates';
        $runner($conn);
        echo "done\n";
        RUNNER;
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open([PHP_BINARY, '-r', $source], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
                fwrite($pipes[0], json_encode([$db, $scope]));
                fclose($pipes[0]);
                $pid = (int)trim(fgets($pipes[1]));
                $children[] = [$process, $pipes];
                $deadline = microtime(true) + 5;
                do {
                    $waiting = RestApiEnv::rows($conn, 'SELECT cardinality(pg_blocking_pids($1)) AS blockers', [$pid])[0]['blockers'];
                    if ((int)$waiting > 0) {
                        break;
                    }
                    usleep(20000);
                } while (microtime(true) < $deadline);
                $this->assertGreaterThan(0, (int)$waiting, 'Pending runner must block on the advisory lock');
            }
        } finally {
            RestApiEnv::rows($conn, 'SELECT pg_advisory_unlock(hashtext($1))', ['saldi:mobile:' . $scope]);
            foreach ($children as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $errors);
                $this->assertSame('', $errors);
                $this->assertSame("done\n", $output);
            }
        }
        $rows = RestApiEnv::rows($conn, "SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name=$1", [$marker]);
        $this->assertCount(1, $rows);
        $this->assertSame($scope === 'tenant' ? array_key_last(mobileTenantUpdateSteps()) : array_key_last(mobileRegistryUpdateSteps()), $rows[0]['var_value']);
        pg_close($conn);
    }

    public function test_tenant_and_registry_concurrent_pending_runners(): void
    {
        $this->concurrentRunners(RestApiEnv::testDb(), 'tenant');
        $this->concurrentRunners(self::$registryDb, 'registry');
    }

    public function test_timestamp_corrections_preserve_copenhagen_instants_and_are_idempotent(): void
    {
        foreach ([RestApiEnv::testDb() => ['tenant', 'pool_files', 'uploaded_at', 'm05_mobile_ip_exception'],
            self::$registryDb => ['registry', 'auth_versions', 'updated_at', 'r05_installation_identity']] as $db => [$scope, $table, $column, $marker]) {
            $conn = RestApiEnv::connect($db);
            $key = $scope === 'tenant' ? 'id' : 'subject';
            $fixture = $scope === 'tenant'
                ? RestApiEnv::rows($conn, 'INSERT INTO pool_files(filename) VALUES ($1) RETURNING id', ['timestamp_' . bin2hex(random_bytes(8)) . '.pdf'])[0]['id']
                : Subject::build('account', 12, 'timestamp-' . bin2hex(random_bytes(8)));
            if ($scope === 'registry') {
                RestApiEnv::rows($conn, 'INSERT INTO auth_versions(subject,tenant_id) VALUES ($1,0)', [$fixture]);
            }
            try {
                RestApiEnv::rows($conn, "SET TIME ZONE 'Europe/Copenhagen'");
                RestApiEnv::rows($conn, "ALTER TABLE $table ALTER COLUMN $column TYPE timestamp USING $column AT TIME ZONE 'Europe/Copenhagen'");
                RestApiEnv::rows($conn, "UPDATE $table SET $column='2026-09-16 12:00:00' WHERE $key=$1", [$fixture]);
                RestApiEnv::rows($conn, "UPDATE settings SET var_value=$1 WHERE var_grp='mobile' AND var_name=$2", [$marker, 'mobile_' . $scope . '_step']);
                $runner = $scope === 'tenant' ? 'mobileTenantUpdates' : 'mobileRegistryUpdates';
                $runner($conn);
                RestApiEnv::rows($conn, "SET TIME ZONE 'UTC'");
                $rows = RestApiEnv::rows($conn, "SELECT $column FROM $table WHERE $key=$1", [$fixture]);
                $this->assertCount(1, $rows, 'Timestamp fixture must exist before checking the correction');
                $this->assertSame('2026-09-16 10:00:00+00', $rows[0][$column]);
                // Reapply the correction to an already-timestamptz column, then exercise the current-marker fast path.
                RestApiEnv::rows($conn, "UPDATE settings SET var_value=$1 WHERE var_grp='mobile' AND var_name=$2", [$marker, 'mobile_' . $scope . '_step']);
                $runner($conn);
                $runner($conn);
                $this->assertSame($rows, RestApiEnv::rows($conn, "SELECT $column FROM $table WHERE $key=$1", [$fixture]));
                $this->assertSame('timestamp with time zone', RestApiEnv::rows($conn, 'SELECT data_type FROM information_schema.columns WHERE table_name=$1 AND column_name=$2', [$table, $column])[0]['data_type']);
            } finally {
                if ($scope === 'tenant') {
                    RestApiEnv::rows($conn, "UPDATE pool_files SET status='deleted',deleted_at=now() WHERE id=$1", [$fixture]);
                } else {
                    RestApiEnv::rows($conn, "DELETE FROM $table WHERE $key=$1", [$fixture]);
                }
                pg_close($conn);
            }
        }
    }

    public function test_between_updates_login_line_retries_pending_steps_and_catches_failure(): void
    {
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        $GLOBALS['connection'] = $conn;
        $source = file_get_contents(__DIR__ . '/../../includes/betweenUpdates.php');
        preg_match("/^include_once\(__DIR__ \. '\/mobileUpdates\.php'\); mobileTenantUpdatesAtLogin\(\);$/m", $source, $match);
        $this->assertNotEmpty($match);
        $line = str_replace('__DIR__', var_export(dirname(__DIR__, 2) . '/includes', true), $match[0]);
        RestApiEnv::rows($conn, "UPDATE settings SET var_value='m05_mobile_ip_exception' WHERE var_name='mobile_tenant_step'");
        eval($line);
        $this->assertSame(array_key_last(mobileTenantUpdateSteps()), RestApiEnv::rows($conn, "SELECT var_value FROM settings WHERE var_name='mobile_tenant_step'")[0]['var_value']);
        $logPath = tempnam(sys_get_temp_dir(), 'mobile-login-');
        $oldLog = ini_get('error_log');
        ini_set('error_log', $logPath);
        try {
            RestApiEnv::rows($conn, "UPDATE settings SET var_value='unknown' WHERE var_name='mobile_tenant_step'");
            eval($line);
            $this->assertStringContainsString('mobile_tenant_step', file_get_contents($logPath));
            $this->assertStringNotContainsString('Unknown mobile schema step', file_get_contents($logPath));
        } finally {
            ini_set('error_log', $oldLog);
            unlink($logPath);
            RestApiEnv::rows($conn, "UPDATE settings SET var_value=$1 WHERE var_name='mobile_tenant_step'", [array_key_last(mobileTenantUpdateSteps())]);
            pg_close($conn);
        }
    }

    public function test_configured_and_default_cors_origins_and_preflight_correlation(): void
    {
        $router = tempnam(sys_get_temp_dir(), 'mobile-cors-');
        $root = dirname(__DIR__, 2);
        file_put_contents($router, '<?php require ' . var_export($root . '/includes/std_func.php', true)
            . '; require ' . var_export($root . '/restapi/core/cors.php', true) . ';');
        $original = getenv('SALDI_CORS_ORIGINS');
        try {
            foreach (['https://foundation.example.invalid', null] as $configured) {
                $socket = stream_socket_server('tcp://127.0.0.1:0');
                $address = stream_socket_get_name($socket, false);
                fclose($socket);
                putenv($configured === null ? 'SALDI_CORS_ORIGINS' : 'SALDI_CORS_ORIGINS=' . $configured);
                $log = tempnam(sys_get_temp_dir(), 'mobile-cors-log-');
                $process = proc_open([PHP_BINARY, '-S', $address, $router], [['pipe', 'r'], ['file', $log, 'a'], ['file', $log, 'a']], $pipes, $root);
                fclose($pipes[0]);
                try {
                    // Read the startup diagnostic before sending requests; keep curl failures visible.
                    $deadline = microtime(true) + 5;
                    while (strpos(file_get_contents($log), 'started') === false && microtime(true) < $deadline) {
                        usleep(20000);
                    }
                    $this->assertStringContainsString('started', file_get_contents($log));
                    $origins = $configured === null ? ['https://saldi.dk', 'https://ssl12.saldi.dk'] : [$configured];
                    foreach ($origins as $origin) {
                        $id = RequestId::resolve(null);
                        foreach (['GET', 'OPTIONS'] as $method) {
                            $response = $this->request(['Origin: ' . $origin, 'X-Request-Id: ' . $id,
                                'Access-Control-Request-Method: POST',
                                'Access-Control-Request-Headers: Authorization, Content-Type, X-Request-Id, Idempotency-Key, If-Match'], null, 'http://' . $address, $method);
                            $this->assertSame(200, $response['status']);
                            $this->assertSame($origin, $response['headers']['access-control-allow-origin']);
                            $this->assertSame('Origin', $response['headers']['vary']);
                            $this->assertSame($id, $response['headers']['x-request-id']);
                            $this->assertStringContainsString('X-Request-Id', $response['headers']['access-control-expose-headers']);
                            $this->assertStringContainsString('X-Request-Id', $response['headers']['access-control-allow-headers']);
                            $allowed = array_map('strtolower', array_map('trim', explode(',', $response['headers']['access-control-allow-headers'])));
                            foreach (['authorization', 'content-type', 'x-request-id', 'idempotency-key', 'if-match'] as $header) {
                                $this->assertContains($header, $allowed);
                            }
                            $this->assertStringContainsString('ETag', $response['headers']['access-control-expose-headers']);
                        }
                    }
                    $response = $this->request(['Origin: http://localhost:3000'], null, 'http://' . $address, 'OPTIONS');
                    $this->assertArrayNotHasKey('access-control-allow-origin', $response['headers']);
                } finally {
                    proc_terminate($process);
                    proc_close($process);
                    unlink($log);
                }
            }
        } finally {
            putenv($original === false ? 'SALDI_CORS_ORIGINS' : 'SALDI_CORS_ORIGINS=' . $original);
            unlink($router);
        }
    }

    public function test_server_error_diagnostics_contain_only_correlation_class_and_location(): void
    {
        $source = <<<'ERROR_PROBE'
        $input = json_decode(stream_get_contents(STDIN), true);
        function getallheaders() { return []; }
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foundation-error';
        $_SERVER['SCRIPT_NAME'] = '/foundation-error';
        $_SERVER['HTTP_X_REQUEST_ID'] = $input['request_id'];
        require getcwd() . '/restapi/core/BaseEndpoint.php';
        class MobileErrorProbe extends BaseEndpoint {
            protected function requiredRight(): ?array { return ['module' => 'auth', 'action' => 'public']; }
            protected function checkAuthorization() { return true; }
            protected function handleGet($id = null) {
                global $input;
                throw new ApiException($input['secret'], $input['status']);
            }
        }
        (new MobileErrorProbe())->handleRequestMethod();
        ERROR_PROBE;
        $secret = bin2hex(random_bytes(24));
        $id = RequestId::resolve(null);
        foreach ([500, 503, 400] as $status) {
            $process = proc_open([PHP_BINARY, '-r', $source], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
            fwrite($pipes[0], json_encode(['secret' => $secret, 'status' => $status, 'request_id' => $id]));
            fclose($pipes[0]);
            $body = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $errors);
            $json = json_decode($body, true);
            if ($status >= 500) {
                $this->assertSame('Internal Server Error', $json['message']);
                $this->assertSame('internal', $json['data']['error']);
                $this->assertStringNotContainsString($secret, $body . $errors);
                $this->assertStringContainsString($id, $errors);
                $this->assertStringContainsString('ApiException', $errors);
                preg_match('/\{[^\n]+\}/', $errors, $matches);
                $diagnostic = json_decode($matches[0], true);
                $this->assertSame(['request_id', 'exception', 'location'], array_keys($diagnostic));
            } else {
                $this->assertSame($secret, $json['message']);
                $this->assertSame('', $errors);
            }
        }
    }

    public function test_unauthenticated_tenant_header_cannot_choose_log_directory(): void
    {
        $login = RestApiEnv::loginData();
        $id = RequestId::resolve(null);
        $response = $this->request(['X-Tenant-ID: ' . $login['tenant']['id'], 'X-Request-Id: ' . $id]);
        $this->assertSame(405, $response['status']);
        $this->assertStringContainsString($id, file_get_contents(__DIR__ . '/../../temp/api/.ht_rest_api.log'));
        $this->assertStringNotContainsString($id, file_get_contents(__DIR__ . '/../../temp/' . RestApiEnv::testDb() . '/.ht_rest_api.log'));
    }

    public function test_tenant_migration_include_is_definition_only(): void
    {
        $source = "require getcwd() . '/includes/mobileUpdates.php'; echo 'definitions loaded';";
        $process = proc_open([PHP_BINARY, '-r', $source], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $errors);
        $this->assertSame('', $errors);
        $this->assertSame('definitions loaded', $body);
    }

    public function test_undeclared_endpoint_denies_even_a_valid_legacy_token(): void
    {
        $login = RestApiEnv::loginData();
        $token = RestApiEnv::signToken(['user_id' => (int)$login['user']['id'], 'username' => RestApiEnv::user(),
            'type' => 'access', 'tenant_id' => $login['tenant']['id']]);
        $source = <<<'LEGACY_PROBE'
        $headers = json_decode(stream_get_contents(STDIN), true);
        function getallheaders() { global $headers; return $headers; }
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foundation-legacy';
        $_SERVER['SCRIPT_NAME'] = '/foundation-legacy';
        require getcwd() . '/restapi/core/BaseEndpoint.php';
        class MobileLegacyProbe extends BaseEndpoint {
            protected function checkAuthorization() { return true; }
            protected function handleGet($id = null) { $this->sendResponse(true, ['subject' => $this->subject]); }
        }
        (new MobileLegacyProbe())->handleRequestMethod();
        LEGACY_PROBE;
        $process = proc_open([PHP_BINARY, '-r', $source], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        fwrite($pipes[0], json_encode(['Authorization' => 'Bearer ' . $token]));
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $errors);
        $this->assertSame('', $errors);
        $json = json_decode($body, true);
        $this->assertFalse($json['success'], $body);
        $this->assertSame('permission_denied', $json['data']['error']);
    }

}
