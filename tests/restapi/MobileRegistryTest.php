<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileRegistryTest.php --- ver 5.0.0 --- 2026.10.08
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
// 20261001 CDX/LH Exercise admin exchange with pending tenant migrations and preserved session identity.
// 20261001 CDX/LH Verify tenant company labels, directory fallbacks and registry exchange responses.
// 20261001 CDX/LH Verify bounded backends across 24 tenant databases and registry login/refresh restrictions.
// 20261005 CL/LH Keep the fixture user id small; saldikrypt() cost grows with the id.
// 20261007 CDX/LH Compare stable admin registry memberships during concurrent tenant fixtures.
// 20261008 CL/LH Cover additive mobile Assist rights and family migration.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/core/MobileAuth.php';

final class MobileRegistryTest extends TestCase
{
    private const AUTH = '/restapi/endpoints/v1/auth/';
    private static $master;
    private static $tenant;
    private static $auth;
    private static $user;
    private static $tenantId;
    private static $closedId;
    private static $secondId;

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$master = RestApiEnv::connect(RestApiEnv::masterDb());
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        self::$tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        self::$closedId = RestApiEnv::regnskabId(RestApiEnv::accountClosed());
        global $sqhost, $squser, $sqpass, $sqdb, $db_type, $db_encode, $connection;
        require_once __DIR__ . '/../../includes/db_query.php';
        include __DIR__ . '/../../includes/connect.php';
        require_once __DIR__ . '/../../restapi/endpoints/v1/auth/registry-login.php';
        self::$auth = new MobileAuth();
        JWT::setSecret(_jwtLoadSecret());
        JWT::setClaimEnricher(null);
        // saldikrypt() costs id x 100 rounds, so ignore ids left far up by interrupted runs and keep ours small.
        $registryMax = (int)RestApiEnv::rows(self::$master, 'SELECT COALESCE(max(id),0) AS id FROM brugere WHERE id < 1000000')[0]['id'];
        $tenantMax = (int)RestApiEnv::rows(self::$tenant, 'SELECT COALESCE(max(id),0) AS id FROM brugere WHERE id < 1000000')[0]['id'];
        $id = max($registryMax, $tenantMax) + 1000;
        $name = 'b3_' . bin2hex(random_bytes(8));
        self::$user = RestApiEnv::rows(self::$master, 'INSERT INTO brugere(id,brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,$4,true) RETURNING *',
            [$id, $name, md5(TestCredentials::password('mobileregistry')), ',,,' . self::$tenantId])[0];
        RestApiEnv::rows(self::$tenant, "INSERT INTO brugere(id,brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,'0000000000000000',true)",
            [$id, $name, md5(TestCredentials::password('mobileregistryaccount'))]);
        self::$secondId = (int)RestApiEnv::rows(self::$master, "INSERT INTO regnskab(regnskab,db,lukket) VALUES ($1,$2,'') RETURNING id",
            ['b3_second_' . bin2hex(random_bytes(8)), RestApiEnv::testDb()])[0]['id'];
        RestApiEnv::rows(self::$tenant, "DELETE FROM adresser WHERE art='S'");
        RestApiEnv::rows(self::$tenant, "INSERT INTO adresser(art,firmanavn,cvrnr,email) VALUES ('S','B3 company','12345678','b3@example.invalid')");
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$master) {
            if (self::$user) {
                foreach (['registry', 'account'] as $principal) {
                    $subject = Subject::build($principal, (int)self::$user['id']);
                    foreach (['refresh_sessions', 'auth_challenges', 'auth_versions'] as $table) {
                        RestApiEnv::rows(self::$master, "DELETE FROM $table WHERE subject=$1", [$subject]);
                    }
                    $key = MobileAuth::bucketKey($principal, $principal === 'account' ? RestApiEnv::accountOpen() : '', self::$user['brugernavn']);
                    RestApiEnv::rows(self::$master, "DELETE FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1", [$key]);
                }
                RestApiEnv::rows(self::$master, 'DELETE FROM brugere WHERE id=$1', [self::$user['id']]);
            }
            if (self::$secondId) {
                RestApiEnv::rows(self::$master, 'DELETE FROM regnskab WHERE id=$1', [self::$secondId]);
            }
            pg_close(self::$tenant);
            pg_close(self::$master);
        }
        RestApiEnv::teardownTenant();
    }

    protected function setUp(): void
    {
        RestApiEnv::rows(self::$master, 'UPDATE brugere SET kode=$1,rettigheder=$2,status=true WHERE id=$3',
            [md5(TestCredentials::password('mobileregistry')), ',,,' . self::$tenantId, self::$user['id']]);
        foreach (['twofactor' => 'f', 'email' => 'b3@example.invalid', 'tlf' => null, 'ip_address' => ''] as $column => $value) {
            if (array_key_exists($column, self::$user)) {
                RestApiEnv::rows(self::$master, "UPDATE brugere SET $column=$1 WHERE id=$2", [$value, self::$user['id']]);
            }
        }
        RestApiEnv::rows(self::$master, "UPDATE regnskab SET lukket='',lukkes=NULL WHERE id=ANY($1::int[])", ['{' . self::$tenantId . ',' . self::$secondId . '}']);
        RestApiEnv::rows(self::$master, "UPDATE regnskab SET lukket='on' WHERE id=$1", [self::$closedId]);
        foreach (['account', 'registry'] as $principal) {
            $key = MobileAuth::bucketKey($principal, $principal === 'account' ? RestApiEnv::accountOpen() : '', self::$user['brugernavn']);
            RestApiEnv::rows(self::$master, "UPDATE auth_rate_limits SET tokens=10,updated_at=now() WHERE bucket_type='account' AND bucket_key=$1", [$key]);
        }
        RestApiEnv::rows(self::$master, "UPDATE auth_rate_limits SET tokens=120,updated_at=now() WHERE bucket_type='ip' AND bucket_key=ANY($1::text[])", ['{127.0.0.1,::1}']);
    }

    /** @return array{status:int,json:?array,body:string} */
    private function login(array $overrides = []): array
    {
        return RestApiEnv::http('POST', self::AUTH . 'registry-login.php', array_replace([
            'username' => self::$user['brugernavn'], 'password' => TestCredentials::password('mobileregistry'), 'device_id' => 'b3-device',
        ], $overrides));
    }

    /** @return array Token data for a fresh registry session. */
    private function pair(): array
    {
        $r = $this->login();
        $this->assertSame(200, $r['status'], $r['body']);
        return $r['json']['data'];
    }

    /** @return array{status:int,json:?array,body:string} */
    private function exchange(string $token, $tenantId): array
    {
        return RestApiEnv::http('POST', self::AUTH . 'tenant-token.php', ['tenant_id' => $tenantId], ['Authorization: Bearer ' . $token]);
    }

    private function error(array $r, string $code, int $status = 401): void
    {
        $this->assertSame($status, $r['status'], $r['body']);
        $this->assertSame($code, ifset($r, ['json', 'data', 'error']), $r['body']);
    }

    public function test_restricted_registry_sees_exact_memberships_cvr_and_deprecated_alias(): void
    {
        // oprette/slette do not confer access; ids are deduplicated by the foundation decoder.
        RestApiEnv::rows(self::$master, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2',
            [',on,on,' . self::$tenantId . ',' . self::$tenantId . ',' . self::$closedId, self::$user['id']]);
        $login = $this->pair();
        $this->assertFalse($login['user']['is_admin']);
        $this->assertNull($login['tenant']);
        $this->assertSame([['id' => self::$tenantId, 'name' => RestApiEnv::accountOpen(), 'db' => RestApiEnv::testDb(), 'cvr' => '12345678', 'company_name' => 'B3 company']], $login['tenants']);
        $payload = JWT::decode($login['access_token']);
        $this->assertSame('registry', $payload['principal']);
        $this->assertNull($payload['tenant_id']);
        $headers = ['Authorization: Bearer ' . $login['access_token']];
        $list = RestApiEnv::http('GET', self::AUTH . 'tenants.php', null, $headers);
        $this->assertSame(200, $list['status'], $list['body']);
        $this->assertSame($login['tenants'], $list['json']['data']['tenants']);
        $alias = RestApiEnv::http('GET', '/restapi/endpoints/v1/user/tenants.php', null, $headers);
        $this->assertSame(200, $alias['status'], $alias['body']);
        $this->assertCount(1, $alias['json']['data']);
        $this->assertSame(['id' => self::$tenantId, 'name' => RestApiEnv::accountOpen(), 'cvr' => '12345678', 'db' => RestApiEnv::testDb(), 'company_name' => 'B3 company', 'email' => 'b3@example.invalid', 'closed' => false], $alias['json']['data'][0]);
    }

    public function test_company_cvr_remains_available_without_an_email_column(): void
    {
        RestApiEnv::rows(self::$tenant, 'ALTER TABLE adresser RENAME COLUMN email TO b3_email');
        try {
            $pair = $this->pair();
            $this->assertSame('12345678', $pair['tenants'][0]['cvr']);
        } finally {
            RestApiEnv::rows(self::$tenant, 'ALTER TABLE adresser RENAME COLUMN b3_email TO email');
        }
    }

    public function test_registry_company_names_fall_back_without_a_name_column_or_own_address(): void
    {
        RestApiEnv::rows(self::$tenant, 'ALTER TABLE adresser RENAME COLUMN firmanavn TO fxp_firmanavn');
        try {
            $pair = $this->pair();
            $this->assertSame(RestApiEnv::accountOpen(), $pair['tenants'][0]['company_name']);
            $this->assertSame('12345678', $pair['tenants'][0]['cvr']);
            $exchange = $this->exchange($pair['access_token'], self::$tenantId);
            $this->assertSame(200, $exchange['status'], $exchange['body']);
            $this->assertSame(RestApiEnv::accountOpen(), $exchange['json']['data']['tenant']['company_name']);
        } finally {
            RestApiEnv::rows(self::$tenant, 'ALTER TABLE adresser RENAME COLUMN fxp_firmanavn TO firmanavn');
        }
        try {
            foreach (['   ', null] as $name) {
                RestApiEnv::rows(self::$tenant, "UPDATE adresser SET firmanavn=$1,art=$2 WHERE cvrnr='12345678'", [$name, $name === null ? 'K' : 'S']);
                $pair = $this->pair();
                $this->assertSame(RestApiEnv::accountOpen(), $pair['tenants'][0]['company_name']);
                $exchange = $this->exchange($pair['access_token'], self::$tenantId);
                $this->assertSame(200, $exchange['status'], $exchange['body']);
                $this->assertSame(RestApiEnv::accountOpen(), $exchange['json']['data']['tenant']['company_name']);
            }
        } finally {
            RestApiEnv::rows(self::$tenant, "UPDATE adresser SET art='S',firmanavn='B3 company' WHERE cvrnr='12345678'");
        }
    }

    public function test_missing_company_database_does_not_prevent_membership_listing(): void
    {
        $missing = (int)RestApiEnv::rows(self::$master, "INSERT INTO regnskab(regnskab,db,lukket) VALUES ($1,$2,'') RETURNING id",
            ['b3_missing_' . bin2hex(random_bytes(4)), 'b3_missing_' . bin2hex(random_bytes(4))])[0]['id'];
        try {
            RestApiEnv::rows(self::$master, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [',,,' . $missing, self::$user['id']]);
            $pair = $this->pair();
            $this->assertCount(1, $pair['tenants']);
            $this->assertSame($missing, $pair['tenants'][0]['id']);
            $this->assertSame('', $pair['tenants'][0]['cvr']);
            $this->assertSame($pair['tenants'][0]['name'], $pair['tenants'][0]['company_name']);
        } finally {
            RestApiEnv::rows(self::$master, 'DELETE FROM regnskab WHERE id=$1', [$missing]);
        }
    }

    public function test_registry_admin_sees_all_open_tenants_and_can_exchange_unlisted_id(): void
    {
        RestApiEnv::rows(self::$master, "UPDATE brugere SET rettigheder='on,,,' WHERE id=$1", [self::$user['id']]);
        // Other suites can replace or close their own tenants while HTTP metadata is read.
        // Compare unchanged rows across both snapshots and always check our owned fixtures.
        $snapshot = static fn(): array => array_column(RestApiEnv::rows(self::$master,
            "SELECT id,xmin::text AS row_revision FROM regnskab WHERE db<>$1 AND (lukket IS NULL OR lukket<>'on') AND (lukkes IS NULL OR lukkes='2099-12-31' OR lukkes>CURRENT_DATE) ORDER BY regnskab,id",
            [RestApiEnv::masterDb()]), 'row_revision', 'id');
        $before = $snapshot();
        $pair = $this->pair();
        $this->assertTrue($pair['user']['is_admin']);
        $after = $snapshot();
        $expected = [];
        foreach ($before as $id => $revision) {
            if (ifset($after, $id) === $revision) {
                $expected[] = (int)$id;
            }
        }
        $actual = array_column($pair['tenants'], 'id');
        $this->assertSame($expected, array_values(array_intersect($actual, $expected)));
        $this->assertContains(self::$tenantId, $actual);
        $this->assertContains(self::$secondId, $actual);
        $this->assertNotContains(self::$closedId, $actual);
        // An unopened tenant must migrate during exchange, before the JWT is issued.
        RestApiEnv::rows(self::$tenant, "DELETE FROM settings WHERE var_grp='mobile' AND var_name='mobile_tenant_step'");
        $r = $this->exchange($pair['access_token'], self::$secondId);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertSame(array_key_last(mobileTenantUpdateSteps()), RestApiEnv::rows(self::$tenant,
            "SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name='mobile_tenant_step'")[0]['var_value']);
        $parent = JWT::decode($pair['access_token']);
        $exchanged = JWT::decode($r['json']['data']['access_token']);
        foreach (['sub', 'principal', 'sid', 'auth_version', 'device_id'] as $claim) {
            $this->assertSame($parent[$claim], $exchanged[$claim]);
        }
        $this->assertSame(self::$secondId, $exchanged['tenant_id']);
        MobileAuth::checkSession($exchanged);
    }

    public function test_listing_twenty_four_distinct_databases_releases_metadata_backends(): void
    {
        global $connection;
        $selected = $connection;
        $databases = [];
        $ids = [];
        $prefix = 'authmeta_' . bin2hex(random_bytes(4));
        try {
            for ($i = 0; $i < 24; $i++) {
                $database = $prefix . '_' . $i;
                RestApiEnv::rows(self::$master, 'CREATE DATABASE ' . $database . ' TEMPLATE template0');
                $databases[] = $database;
                $metadataLink = RestApiEnv::connect($database);
                try {
                    $pattern = pg_escape_literal($metadataLink, $prefix . '%');
                    // Observe backend demand during each metadata read, before the listing returns.
                    RestApiEnv::rows($metadataLink, "CREATE VIEW adresser AS SELECT 1 AS id,'S'::text AS art,
                        (SELECT count(*)::text FROM pg_stat_activity WHERE datname LIKE $pattern) AS firmanavn");
                } finally {
                    pg_close($metadataLink);
                }
                $ids[] = (int)RestApiEnv::rows(self::$master, "INSERT INTO regnskab(regnskab,db,lukket) VALUES ($1,$2,'') RETURNING id", [$database, $database])[0]['id'];
            }
            $user = self::$user;
            $user['rettigheder'] = ',,,' . implode(',', $ids);
            $names = '{' . implode(',', $databases) . '}';
            $count = static function () use ($names): int {
                // Clear the statistics snapshot so the assertion sees links opened by this very request.
                RestApiEnv::rows(self::$master, 'SELECT pg_stat_clear_snapshot()');
                return (int)RestApiEnv::rows(self::$master, 'SELECT count(*) AS n FROM pg_stat_activity WHERE datname=ANY($1::text[])', [$names])[0]['n'];
            };
            $this->assertSame(0, $count());
            $tenants = MobileRegistryDirectory::tenants(self::$auth, $user);
            $this->assertCount(24, $tenants);
            $peak = max(array_map(static fn(array $tenant): int => (int)$tenant['company_name'], $tenants));
            $this->assertGreaterThanOrEqual(1, $peak);
            $this->assertLessThanOrEqual(2, $peak, 'Metadata backend demand must stay bounded throughout the request');
            $this->assertSame(0, $count(), 'Tenant links must close before this request ends');
            $this->assertSame($selected, $connection);
            $this->assertSame('1', pg_fetch_result(pg_query($selected, 'SELECT 1'), 0, 0));
        } finally {
            foreach ($ids as $id) {
                RestApiEnv::rows(self::$master, 'DELETE FROM regnskab WHERE id=$1', [$id]);
            }
            foreach ($databases as $database) {
                RestApiEnv::rows(self::$master, 'DROP DATABASE ' . $database . ' WITH (FORCE)');
            }
        }
    }

    public function test_successful_registry_logins_preserve_the_failure_budget_and_refresh_checks_ip(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $pair = $this->pair();
        }
        $key = MobileAuth::bucketKey('registry', '', self::$user['brugernavn']);
        $this->assertSame(10.0, (float)RestApiEnv::rows(self::$master, "SELECT tokens FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1", [$key])[0]['tokens']);
        if (!array_key_exists('ip_address', self::$user)) {
            $this->markTestSkipped('Registry IP field unavailable');
        }
        RestApiEnv::rows(self::$master, "UPDATE brugere SET ip_address='192.0.2.55' WHERE id=$1", [self::$user['id']]);
        $this->error(RestApiEnv::http('POST', self::AUTH . 'refresh.php', ['refresh_token' => $pair['refresh_token'], 'device_id' => 'b3-device']), 'ip_rejected', 403);
        $this->error($this->login(['password' => bin2hex(random_bytes(24))]), 'ip_rejected', 403);
        RestApiEnv::rows(self::$master, "UPDATE brugere SET ip_address='' WHERE id=$1", [self::$user['id']]);
        $r = RestApiEnv::http('POST', self::AUTH . 'refresh.php', ['refresh_token' => $pair['refresh_token'], 'device_id' => 'b3-device']);
        $this->assertSame(200, $r['status'], $r['body']);
    }

    public function test_empty_membership_lists_nothing_and_unknown_or_unlisted_ids_are_refused(): void
    {
        RestApiEnv::rows(self::$master, "UPDATE brugere SET rettigheder=',,,' WHERE id=$1", [self::$user['id']]);
        $pair = $this->pair();
        $this->assertSame([], $pair['tenants']);
        $this->error($this->exchange($pair['access_token'], self::$tenantId), 'permission_denied', 403);
        $this->error($this->exchange($pair['access_token'], PHP_INT_MAX), 'permission_denied', 403);
    }

    public function test_exchange_rechecks_membership_after_login_and_refuses_closed_and_scheduled_accounts(): void
    {
        $pair = $this->pair();
        $this->error($this->exchange($pair['access_token'], self::$secondId), 'permission_denied', 403);
        RestApiEnv::rows(self::$master, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [',,,' . self::$closedId, self::$user['id']]);
        $this->error($this->exchange($pair['access_token'], self::$tenantId), 'permission_denied', 403);
        $this->error($this->exchange($pair['access_token'], self::$closedId), 'account_closed', 403);
        RestApiEnv::rows(self::$master, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [',,,' . self::$tenantId, self::$user['id']]);
        RestApiEnv::rows(self::$master, 'UPDATE regnskab SET lukkes=CURRENT_DATE WHERE id=$1', [self::$tenantId]);
        $this->error($this->exchange($pair['access_token'], self::$tenantId), 'account_closed', 403);
        $list = RestApiEnv::http('GET', self::AUTH . 'tenants.php', null, ['Authorization: Bearer ' . $pair['access_token']]);
        $this->assertSame([], $list['json']['data']['tenants']);
    }

    public function test_identical_username_and_equal_numeric_ids_keep_principals_and_sessions_separate(): void
    {
        $this->error($this->login(['password' => TestCredentials::password('mobileregistryaccount')]), 'invalid_credentials');
        $accountBody = ['account_name' => RestApiEnv::accountOpen(), 'username' => self::$user['brugernavn'], 'password' => TestCredentials::password('mobileregistry'), 'device_id' => 'b3-device'];
        $this->error(RestApiEnv::http('POST', self::AUTH . 'login.php', $accountBody), 'invalid_credentials');
        $accountBody['password'] = TestCredentials::password('mobileregistryaccount');
        $account = RestApiEnv::http('POST', self::AUTH . 'login.php', $accountBody);
        $this->assertSame(200, $account['status'], $account['body']);
        $registry = $this->pair();
        $a = JWT::decode($account['json']['data']['access_token']);
        $r = JWT::decode($registry['access_token']);
        $this->assertSame($a['user_id'], $r['user_id']);
        $this->assertSame('account', $a['principal']);
        $this->assertNotSame($a['sub'], $r['sub']);
        $this->assertNotSame($a['sid'], $r['sid']);
        $rows = RestApiEnv::rows(self::$master, 'SELECT DISTINCT subject,principal FROM refresh_sessions WHERE family_id=ANY($1::text[])', ['{' . $a['sid'] . ',' . $r['sid'] . '}']);
        $this->assertCount(2, $rows);
        foreach ([self::AUTH . 'tenants.php', '/restapi/endpoints/v1/user/tenants.php'] as $path) {
            $this->error(RestApiEnv::http('GET', $path, null, ['Authorization: Bearer ' . $account['json']['data']['access_token']]), 'permission_denied', 403);
        }
        $this->error($this->exchange($account['json']['data']['access_token'], self::$tenantId), 'permission_denied', 403);
    }

    public function test_exchange_preserves_registry_subject_device_family_and_migrates_the_actual_tenant(): void
    {
        // Simulate an installed tenant with the foundation marker awaiting its last step.
        mobileTenantUpdates(self::$tenant);
        RestApiEnv::rows(self::$tenant, "UPDATE settings SET var_value='m05_mobile_ip_exception' WHERE var_grp='mobile' AND var_name='mobile_tenant_step'");
        $pair = $this->pair();
        $parent = JWT::decode($pair['access_token']);
        $r = $this->exchange($pair['access_token'], self::$tenantId);
        $this->assertSame(200, $r['status'], $r['body']);
        $data = $r['json']['data'];
        $this->assertSame(['id' => self::$tenantId, 'name' => RestApiEnv::accountOpen(), 'db' => RestApiEnv::testDb(), 'company_name' => 'B3 company'], $data['tenant']);
        $this->assertTrue($data['user']['is_admin']);
        foreach (['access_token', 'refresh_token'] as $key) {
            $payload = JWT::decode($data[$key]);
            foreach (['principal', 'sub', 'device_id', 'sid', 'auth_version'] as $claim) {
                $this->assertSame($parent[$claim], $payload[$claim]);
            }
            $this->assertSame(self::$tenantId, $payload['tenant_id']);
        }
        $marker = RestApiEnv::rows(self::$tenant, "SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name='mobile_tenant_step'")[0]['var_value'];
        $this->assertNotSame('m05_mobile_ip_exception', $marker);
        $token = $data['access_token'];
        $tenantEndpoint = RestApiEnv::http('GET', '/restapi/endpoints/v1/accounts/posting-chart.php', null, ['Authorization: Bearer ' . $token]);
        $this->assertSame(200, $tenantEndpoint['status'], $tenantEndpoint['body']);
        $me = RestApiEnv::http('GET', self::AUTH . 'me.php', null, ['Authorization: Bearer ' . $token]);
        $this->assertSame(200, $me['status'], $me['body']);
        $this->assertSame($parent['sub'], $me['json']['data']['subject']);
        $this->assertSame($data['tenant'], $me['json']['data']['tenant']);
        $this->assertSame(array_fill_keys(['finans', 'debitor', 'lager', 'sager', 'bilag', 'admin', 'assistant'], true), $me['json']['data']['rights']);
        $this->assertSame(200, RestApiEnv::http('GET', self::AUTH . 'tenants.php', null, ['Authorization: Bearer ' . $pair['access_token']])['status']);
        $refresh = RestApiEnv::http('POST', self::AUTH . 'refresh.php', ['refresh_token' => $data['refresh_token'], 'device_id' => 'b3-device']);
        $this->assertSame(200, $refresh['status'], $refresh['body']);
        $this->assertSame($parent['sub'], JWT::decode($refresh['json']['data']['access_token'])['sub']);
    }

    public function test_multiple_tenant_chains_stay_live_and_logout_revokes_the_whole_device_family(): void
    {
        RestApiEnv::rows(self::$master, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [',,,' . self::$tenantId . ',' . self::$secondId, self::$user['id']]);
        $pair = $this->pair();
        $first = $this->exchange($pair['access_token'], self::$tenantId)['json']['data'];
        $second = $this->exchange($pair['access_token'], self::$secondId)['json']['data'];
        foreach ([$pair, $first, $second] as $session) {
            $this->assertSame(200, RestApiEnv::http('GET', self::AUTH . 'me.php', null, ['Authorization: Bearer ' . $session['access_token']])['status']);
        }
        $logout = RestApiEnv::http('POST', self::AUTH . 'logout.php', [], ['Authorization: Bearer ' . $pair['access_token']]);
        $this->assertSame(200, $logout['status'], $logout['body']);
        foreach ([$pair, $first, $second] as $session) {
            $this->error(RestApiEnv::http('GET', self::AUTH . 'me.php', null, ['Authorization: Bearer ' . $session['access_token']]), 'session_revoked');
        }
        $this->error($this->exchange($pair['access_token'], self::$tenantId), 'session_revoked');
    }

    public function test_registry_validation_disabled_and_ip_rules_and_hashed_password(): void
    {
        foreach ([['device_id' => null], ['device_id' => ''], ['username' => ''], ['password' => []]] as $body) {
            $this->error($this->login($body), 'validation_failed', 400);
        }
        $this->error(RestApiEnv::http('POST', self::AUTH . 'registry-login.php', []), 'validation_failed', 400);
        $this->error(RestApiEnv::http('GET', self::AUTH . 'registry-login.php'), 'validation_failed', 405);
        $this->error(RestApiEnv::http('GET', self::AUTH . 'tenants.php'), 'invalid_token');
        $pair = $this->pair();
        foreach (['1', 0, -1, 1.5, true, null] as $id) {
            $this->error($this->exchange($pair['access_token'], $id), 'validation_failed', 400);
        }
        RestApiEnv::rows(self::$master, 'UPDATE brugere SET status=false WHERE id=$1', [self::$user['id']]);
        $this->error($this->login(), 'user_disabled', 403);
        RestApiEnv::rows(self::$master, 'UPDATE brugere SET status=true,kode=$1 WHERE id=$2', [saldikrypt((int)self::$user['id'], TestCredentials::password('mobileregistry')), self::$user['id']]);
        $this->pair();
        if (array_key_exists('ip_address', self::$user)) {
            RestApiEnv::rows(self::$master, "UPDATE brugere SET ip_address='192.0.2.55' WHERE id=$1", [self::$user['id']]);
            $this->error($this->login(), 'ip_rejected', 403);
        }
    }

    public function test_registry_rate_bucket_is_normalized_and_separate_from_account_bucket(): void
    {
        $wrong = bin2hex(random_bytes(24));
        for ($i = 0; $i < 10; $i++) {
            $this->error($this->login(['username' => strtoupper(self::$user['brugernavn']), 'password' => $wrong]), 'invalid_credentials');
        }
        $this->error($this->login(), 'rate_limited', 429);
        $account = RestApiEnv::http('POST', self::AUTH . 'login.php', ['username' => self::$user['brugernavn'], 'password' => TestCredentials::password('mobileregistryaccount'), 'account_name' => RestApiEnv::accountOpen(), 'device_id' => 'b3-device']);
        $this->assertSame(200, $account['status'], $account['body']);
    }

    /** CLI-only capture of the delivery boundary; endpoint code cannot select it. */
    private function capturedChallenges(): MobileRegistryChallenges
    {
        return new class(self::$auth) extends MobileRegistryChallenges {
            public $code;
            public $channel;
            public $failDelivery = false;

            protected function deliver(string $channel, string $target, string $code, int $tenantId): void
            {
                if (PHP_SAPI !== 'cli' || $this->failDelivery) {
                    throw new RuntimeException('Test delivery unavailable');
                }
                $this->code = $code;
                $this->channel = $channel;
            }
        };
    }

    /** @return array{service:MobileRegistryChallenges,challenge:array} */
    private function challenge(string $phone = ''): array
    {
        $user = self::$user;
        $user['email'] = 'b3@example.invalid';
        $user['tlf'] = $phone;
        $service = $this->capturedChallenges();
        $version = self::$auth->version(Subject::build('registry', (int)$user['id']), null);
        return ['service' => $service, 'challenge' => $service->create($user, 0, 'b3-device', $version)];
    }

    public function test_registry_challenges_bind_principal_device_epoch_expiry_and_attempt_limit(): void
    {
        $c = $this->challenge();
        $id = $c['challenge']['challenge_id'];
        $row = RestApiEnv::rows(self::$master, 'SELECT * FROM auth_challenges WHERE id=$1', [$id])[0];
        $this->assertSame('registry', $row['principal']);
        $this->assertNull($row['tenant_id']);
        $this->assertSame('email', $c['challenge']['channel']);
        $this->assertArrayNotHasKey('code', $c['challenge']);
        $success = RestApiEnv::http('POST', self::AUTH . 'registry-login.php', ['challenge_id' => $id, 'code' => $c['service']->code, 'device_id' => 'b3-device']);
        $this->assertSame(200, $success['status'], $success['body']);
        $this->assertSame('registry', JWT::decode($success['json']['data']['access_token'])['principal']);
        $this->error(RestApiEnv::http('POST', self::AUTH . 'registry-login.php', ['challenge_id' => $id, 'code' => $c['service']->code, 'device_id' => 'b3-device']), '2fa_failed');
        foreach (['attempts', 'expiry', 'device', 'epoch'] as $failure) {
            $c = $this->challenge();
            $id = $c['challenge']['challenge_id'];
            if ($failure === 'expiry') {
                RestApiEnv::rows(self::$master, "UPDATE auth_challenges SET expires_at=now()-INTERVAL '1 second' WHERE id=$1", [$id]);
            } elseif ($failure === 'epoch') {
                self::$auth->revoke(Subject::build('registry', (int)self::$user['id']), 'password_changed');
            }
            $wrong = str_pad((string)(((int)$c['service']->code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
            $tries = $failure === 'attempts' ? 5 : 1;
            for ($i = 0; $i < $tries; $i++) {
                try {
                    $c['service']->complete($id, $failure === 'attempts' ? $wrong : $c['service']->code, $failure === 'device' ? 'other-device' : 'b3-device');
                    $this->fail('Challenge must fail');
                } catch (MobileAuthError $e) {
                    $this->assertSame('2fa_failed', $e->error);
                }
            }
            try {
                $c['service']->complete($id, $c['service']->code, 'b3-device');
                $this->fail('Consumed challenge must fail');
            } catch (MobileAuthError $e) {
                $this->assertSame('2fa_failed', $e->error);
            }
        }
        $sms = $this->challenge('4512345678');
        $this->assertSame('sms', $sms['challenge']['channel']);
        $this->assertSame('***5678', $sms['challenge']['hint']);
    }

    public function test_account_challenge_cannot_complete_on_registry_endpoint(): void
    {
        $subject = Subject::build('account', (int)self::$user['id']);
        $id = MobileAuth::uuid();
        $code = (string)random_int(100000, 999999);
        $version = self::$auth->version($subject, self::$tenantId);
        RestApiEnv::rows(self::$master, "INSERT INTO auth_challenges(id,subject,principal,tenant_id,device_id,channel,code_hash,password_verified_at,auth_version,expires_at) VALUES ($1,$2,'account',$3,'b3-device','email',$4,now(),$5,now()+INTERVAL '3 minutes')",
            [$id, $subject, self::$tenantId, hash_hmac('sha256', $id . ':' . $code, _jwtLoadSecret()), $version]);
        $this->error(RestApiEnv::http('POST', self::AUTH . 'registry-login.php', ['challenge_id' => $id, 'code' => $code, 'device_id' => 'b3-device']), '2fa_failed');
        $this->assertNull(RestApiEnv::rows(self::$master, 'SELECT consumed_at FROM auth_challenges WHERE id=$1', [$id])[0]['consumed_at']);
    }

    public function test_registry_delivery_failure_consumes_challenge_and_missing_data_fails_closed(): void
    {
        $service = $this->capturedChallenges();
        $service->failDelivery = true;
        $subject = Subject::build('registry', (int)self::$user['id']);
        $version = self::$auth->version($subject, null);
        $user = self::$user;
        $user['email'] = 'b3@example.invalid';
        $user['tlf'] = '';
        try {
            $service->create($user, 0, 'b3-device', $version);
            $this->fail('Delivery must fail');
        } catch (RuntimeException $e) {
            $this->assertSame('Test delivery unavailable', $e->getMessage());
        }
        $row = RestApiEnv::rows(self::$master, 'SELECT consumed_at FROM auth_challenges WHERE subject=$1 ORDER BY created_at DESC LIMIT 1', [$subject])[0];
        $this->assertNotNull($row['consumed_at']);
        if (array_key_exists('twofactor', self::$user)) {
            RestApiEnv::rows(self::$master, "UPDATE brugere SET twofactor=true,email=NULL,tlf=NULL WHERE id=$1", [self::$user['id']]);
            $this->error($this->login(), 'internal', 500);
        }
    }

    public function test_registry_http_email_challenge_via_local_mailpit(): void
    {
        if (getenv('SMTP_HOST') !== 'mailpit' || !array_key_exists('twofactor', self::$user)) {
            $this->markTestSkipped('Registry 2FA fields/local Mailpit unavailable');
        }
        $email = 'b3-' . bin2hex(random_bytes(8)) . '@example.invalid';
        RestApiEnv::rows(self::$master, 'UPDATE brugere SET twofactor=true,email=$1,tlf=NULL WHERE id=$2', [$email, self::$user['id']]);
        $r = $this->login();
        $this->assertSame(200, $r['status'], $r['body']);
        $challenge = $r['json']['data'];
        $this->assertTrue($challenge['2fa_required']);
        $this->assertArrayNotHasKey('access_token', $challenge);
        $ch = curl_init('http://mailpit:8025/api/v1/messages');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        $list = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $message = null;
        foreach (ifset($list, 'messages', []) as $candidate) {
            if (in_array($email, array_column(ifset($candidate, 'To', []), 'Address'), true)) {
                $message = $candidate['ID'];
                break;
            }
        }
        $this->assertNotNull($message, 'Expected registry challenge in local Mailpit');
        try {
            $ch = curl_init('http://mailpit:8025/api/v1/message/' . rawurlencode($message));
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            $mail = json_decode(curl_exec($ch), true);
            curl_close($ch);
            $this->assertSame(1, preg_match('/kode: ([0-9]{6})/', (string)ifset($mail, 'Text', ''), $matches));
            $completion = RestApiEnv::http('POST', self::AUTH . 'registry-login.php', ['challenge_id' => $challenge['challenge_id'], 'code' => $matches[1], 'device_id' => 'b3-device']);
            $this->assertSame(200, $completion['status'], $completion['body']);
            $this->assertNotEmpty($completion['json']['data']['refresh_token']);
        } finally {
            $ch = curl_init('http://mailpit:8025/api/v1/messages');
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['IDs' => [$message]])]);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
