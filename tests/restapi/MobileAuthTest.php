<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileAuthTest.php --- ver 5.0.0 --- 2026.10.10
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
// 20261001 CDX/LH Verify legacy refresh stability, revocation and modern compatibility isolation.
// 20261001 CDX/LH Expect enabled capabilities after the Tier 1 integration gate.
// 20261001 CDX/LH Verify company names and fallbacks on login, 2FA completion and current tenant responses.
// 20261001 CDX/LH Cover refresh IP rollback, deadline closure, successful-login refunds and bounded auth storage.
// 20261001 CDX/LH Expect the enabled Tier 2 catalogue capability.
// 20261002 CL/LH Assert the Tier 1 capabilities as a subset; installed capability fragments may add keys.
// 20261003 CL/LH Run prune() in its 500-row batches until no stale auth rows remain, so leftovers from earlier runs cannot hide this test's rows.
// 20261008 CL/LH Cover additive mobile Assist rights and family migration.
// 20261010 CDX/LH Verify device-scoped Assist grant revocation on logout.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/core/MobileAuth.php';
require_once __DIR__ . '/../../restapi/core/MobileAuthChallenges.php';
require_once __DIR__ . '/../../restapi/core/MobileAuthRevocation.php';

/** Only this CLI test class captures delivery; endpoint classes cannot construct it. */
final class MobileAuthCapturedChallenges extends MobileAuthChallenges
{
    public $code;
    public $channel;

    protected function deliver(string $channel, string $target, string $code, int $tenantId): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('Test delivery is CLI only');
        }
        $this->code = $code;
        $this->channel = $channel;
    }
}

final class MobileAuthTest extends TestCase
{
    private static $master;
    private static $tenant;
    private static $id;
    private static $user;
    private static $auth;
    private static $registryUser;
    private const AUTH = '/restapi/endpoints/v1/auth/';

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$master = RestApiEnv::connect(RestApiEnv::masterDb());
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        self::$id = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        // Populate Saldi globals from the installation's ignored config, never literal credentials.
        global $sqhost, $squser, $sqpass, $sqdb, $db_type, $db_encode, $connection;
        require_once __DIR__ . '/../../includes/db_query.php';
        include __DIR__ . '/../../includes/connect.php';
        self::$auth = new MobileAuth();
        JWT::setSecret(_jwtLoadSecret());
        JWT::setClaimEnricher(null);
        RestApiEnv::rows(self::$tenant, "UPDATE brugere SET rettigheder='0000000000000000' WHERE brugernavn=$1", [RestApiEnv::user()]);
        mobileTenantUpdates(self::$tenant);
        self::$user = RestApiEnv::rows(self::$tenant, 'SELECT * FROM brugere WHERE brugernavn=$1', [RestApiEnv::user()])[0];
        self::$registryUser = RestApiEnv::rows(self::$master, 'INSERT INTO brugere(brugernavn,kode,rettigheder) VALUES ($1,$2,$3) RETURNING *', ['b2_registry_' . bin2hex(random_bytes(8)), md5(TestCredentials::password('mobileauthregistry')), ',,,' . self::$id])[0];
        if (array_key_exists('status', self::$registryUser)) {
            RestApiEnv::rows(self::$master, 'UPDATE brugere SET status=true WHERE id=$1', [self::$registryUser['id']]);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$master) {
            if (self::$registryUser) {
                $subject = Subject::build('registry', (int)self::$registryUser['id']);
                RestApiEnv::rows(self::$master, 'DELETE FROM refresh_sessions WHERE subject=$1', [$subject]);
                RestApiEnv::rows(self::$master, 'DELETE FROM auth_versions WHERE subject=$1', [$subject]);
                RestApiEnv::rows(self::$master, 'DELETE FROM brugere WHERE id=$1', [self::$registryUser['id']]);
            }
            RestApiEnv::rows(self::$master, 'DELETE FROM refresh_sessions WHERE tenant_id=$1', [self::$id]);
            RestApiEnv::rows(self::$master, 'DELETE FROM auth_challenges WHERE tenant_id=$1', [self::$id]);
            RestApiEnv::rows(self::$master, 'DELETE FROM auth_versions WHERE tenant_id=$1', [self::$id]);
            $key = MobileAuth::bucketKey('account', RestApiEnv::accountOpen(), RestApiEnv::user());
            RestApiEnv::rows(self::$master, "DELETE FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1", [$key]);
            pg_close(self::$tenant);
            pg_close(self::$master);
        }
        RestApiEnv::teardownTenant();
    }

    protected function setUp(): void
    {
        RestApiEnv::rows(self::$tenant, "UPDATE brugere SET twofactor=false,tlf=NULL,ip_address='',mobil_undtaget=false,status=true,kode=$1 WHERE id=$2", [md5(RestApiEnv::password()), self::$user['id']]);
        RestApiEnv::rows(self::$master, "UPDATE regnskab SET lukket='',lukkes=NULL WHERE id=$1", [self::$id]);
        $key = MobileAuth::bucketKey('account', RestApiEnv::accountOpen(), RestApiEnv::user());
        RestApiEnv::rows(self::$master, "UPDATE auth_rate_limits SET tokens=10,updated_at=now() WHERE bucket_type='account' AND bucket_key=$1", [$key]);
        // Reset only the local HTTP loopback bucket used by this disposable container.
        RestApiEnv::rows(self::$master, "UPDATE auth_rate_limits SET tokens=120,updated_at=now() WHERE bucket_type='ip' AND bucket_key=ANY($1::text[])", ['{127.0.0.1,::1}']);
    }

    /** @return array Successful fresh login per test; no shared cached family. */
    private function login(string $device = 'device-a'): array
    {
        $r = RestApiEnv::http('POST', self::AUTH . 'login.php', ['account_name' => RestApiEnv::accountOpen(), 'username' => RestApiEnv::user(), 'password' => RestApiEnv::password(), 'device_id' => $device]);
        $this->assertSame(200, $r['status'], $r['body']);
        return $r['json']['data'];
    }

    private function refresh(string $token, string $device = 'device-a'): array
    {
        return RestApiEnv::http('POST', self::AUTH . 'refresh.php', ['refresh_token' => $token, 'device_id' => $device]);
    }

    private function me(string $token): array
    {
        return RestApiEnv::http('GET', self::AUTH . 'me.php', null, ['Authorization: Bearer ' . $token]);
    }

    private function error(array $response, string $code, int $status = 401): void
    {
        $this->assertSame($status, $response['status'], $response['body']);
        $this->assertSame($code, $response['json']['data']['error'], $response['body']);
    }

    public function test_rotation_and_lost_response_grace_return_current_jti(): void
    {
        $login = $this->login();
        $old = JWT::decode($login['refresh_token']);
        $r = $this->refresh($login['refresh_token']);
        $this->assertSame(200, $r['status'], $r['body']);
        $new = JWT::decode($r['json']['data']['refresh_token']);
        $this->assertNotSame($old['jti'], $new['jti']);
        $this->assertSame($old['sid'], $new['sid']);
        $retry = $this->refresh($login['refresh_token']);
        $this->assertSame(200, $retry['status'], $retry['body']);
        $this->assertSame($new['jti'], JWT::decode($retry['json']['data']['refresh_token'])['jti']);
        $this->assertSame(200, $this->me($login['access_token'])['status']);
        $rows = RestApiEnv::rows(self::$master, 'SELECT * FROM refresh_sessions WHERE family_id=$1 ORDER BY id DESC', [$old['sid']]);
        $this->assertCount(2, $rows);
        $this->assertSame(hash('sha256', $new['jti']), $rows[0]['jti_hash']);
        $this->assertStringNotContainsString($new['jti'], json_encode($rows[0]));
        $this->assertNotEmpty($rows[0]['jti_ciphertext']);
        $this->assertGreaterThan(time() + 29 * 86400, strtotime($rows[0]['expires_at']));
    }

    public function test_concurrent_refreshes_return_one_current_jti(): void
    {
        $login = $this->login();
        $multi = curl_multi_init();
        $handles = [];
        for ($i = 0; $i < 2; $i++) {
            $ch = curl_init(RestApiEnv::baseUrl() . self::AUTH . 'refresh.php');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['refresh_token' => $login['refresh_token'], 'device_id' => 'device-a'])]);
            curl_multi_add_handle($multi, $ch);
            $handles[] = $ch;
        }
        do {
            curl_multi_exec($multi, $active);
            if ($active) {
                curl_multi_select($multi, 0.2);
            }
        } while ($active);
        $jtis = [];
        foreach ($handles as $ch) {
            $body = curl_multi_getcontent($ch);
            $this->assertSame(200, (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $body);
            $response = json_decode($body, true);
            $jtis[] = JWT::decode($response['data']['refresh_token'])['jti'];
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
        $this->assertSame($jtis[0], $jtis[1]);
    }

    public function test_omitted_device_refresh_keeps_original_token_and_expiry_and_obeys_revocation(): void
    {
        $body = ['account_name' => RestApiEnv::accountOpen(), 'username' => RestApiEnv::user(), 'password' => RestApiEnv::password()];
        $login = RestApiEnv::http('POST', self::AUTH . 'login.php', $body);
        $this->assertSame(200, $login['status'], $login['body']);
        $token = $login['json']['data']['refresh_token'];
        $payload = JWT::decode($token);
        $before = RestApiEnv::rows(self::$master, 'SELECT jti_hash,expires_at,last_used_at FROM refresh_sessions WHERE family_id=$1', [$payload['sid']]);
        for ($i = 0; $i < 3; $i++) {
            $r = RestApiEnv::http('POST', self::AUTH . 'refresh.php', ['refresh_token' => $token]);
            $this->assertSame(200, $r['status'], $r['body']);
            $this->assertArrayNotHasKey('refresh_token', $r['json']['data']);
            $this->assertSame(200, $this->me($r['json']['data']['access_token'])['status']);
        }
        $this->assertSame($before, RestApiEnv::rows(self::$master, 'SELECT jti_hash,expires_at,last_used_at FROM refresh_sessions WHERE family_id=$1', [$payload['sid']]));
        revokeSessions($payload['sub'], 'password_changed', self::$id);
        $this->error(RestApiEnv::http('POST', self::AUTH . 'refresh.php', ['refresh_token' => $token]), 'session_revoked');
        $this->error($this->me($r['json']['data']['access_token']), 'session_revoked');
    }

    public function test_modern_refresh_cannot_use_header_to_repair_missing_session_claims(): void
    {
        $login = $this->login();
        $payload = JWT::decode($login['refresh_token']);
        unset($payload['sid'], $payload['jti']);
        $r = RestApiEnv::http('POST', self::AUTH . 'refresh.php', ['refresh_token' => RestApiEnv::signToken($payload)], ['X-Tenant-ID: ' . self::$id]);
        $this->error($r, 'session_revoked');
        $this->assertSame(200, $this->refresh($login['refresh_token'])['status']);
    }

    public function test_legacy_tenant_header_never_overrides_a_signed_tenant(): void
    {
        $claims = ['type' => 'access', 'user_id' => (int)self::$user['id'], 'tenant_id' => 999999999];
        $r = RestApiEnv::http('GET', '/restapi/endpoints/v1/debitor/customers/?limit=1', null, ['Authorization: Bearer ' . RestApiEnv::signToken($claims), 'X-Tenant-ID: ' . self::$id]);
        $this->assertSame(400, $r['status'], $r['body']);
        $claims['type'] = 'refresh';
        $r = RestApiEnv::http('POST', self::AUTH . 'refresh.php', ['refresh_token' => RestApiEnv::signToken($claims)], ['X-Tenant-ID: ' . self::$id]);
        $this->error($r, 'invalid_token');
    }

    public function test_header_selected_legacy_refresh_cannot_recreate_sessions_after_revocation(): void
    {
        $user = RestApiEnv::rows(self::$tenant, 'INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,true) RETURNING *',
            ['b2_legacy_' . bin2hex(random_bytes(8)), md5(TestCredentials::password('mobileauthlegacy')), '0000000000000000'])[0];
        $subject = Subject::build('account', (int)$user['id']);
        try {
            $token = RestApiEnv::signToken(['type' => 'refresh', 'user_id' => (int)$user['id'], 'username' => $user['brugernavn']]);
            $headers = ['X-Tenant-ID: ' . self::$id];
            $r = RestApiEnv::http('POST', self::AUTH . 'refresh.php', ['refresh_token' => $token], $headers);
            $this->assertSame(200, $r['status'], $r['body']);
            $this->assertArrayNotHasKey('refresh_token', $r['json']['data']);
            $this->assertSame(200, $this->me($r['json']['data']['access_token'])['status']);
            revokeSessions($subject, 'password_changed', self::$id);
            $this->error($this->me($r['json']['data']['access_token']), 'session_revoked');
            $this->error(RestApiEnv::http('POST', self::AUTH . 'refresh.php', ['refresh_token' => $token], $headers), 'session_revoked');
        } finally {
            RestApiEnv::rows(self::$master, 'DELETE FROM refresh_sessions WHERE subject=$1 AND tenant_id=$2', [$subject, self::$id]);
            RestApiEnv::rows(self::$master, 'DELETE FROM auth_versions WHERE subject=$1 AND tenant_id=$2', [$subject, self::$id]);
            RestApiEnv::rows(self::$tenant, 'DELETE FROM brugere WHERE id=$1', [$user['id']]);
        }
    }

    public function test_previous_jti_outside_grace_revokes_access_and_current_refresh(): void
    {
        $login = $this->login();
        $next = $this->refresh($login['refresh_token'])['json']['data'];
        $old = JWT::decode($login['refresh_token']);
        RestApiEnv::rows(self::$master, "UPDATE refresh_sessions SET last_used_at=now()-INTERVAL '61 seconds' WHERE jti_hash=$1", [hash('sha256', $old['jti'])]);
        $this->error($this->refresh($login['refresh_token']), 'session_revoked');
        $this->error($this->me($next['access_token']), 'session_revoked');
        $this->error($this->refresh($next['refresh_token']), 'session_revoked');
    }

    public function test_older_than_previous_jti_is_reuse_even_inside_grace(): void
    {
        $login = $this->login();
        $next = $this->refresh($login['refresh_token'])['json']['data'];
        $this->assertSame(200, $this->refresh($next['refresh_token'])['status']);
        $this->error($this->refresh($login['refresh_token']), 'session_revoked');
        $this->error($this->me($login['access_token']), 'session_revoked');
    }

    public function test_different_device_revokes_family(): void
    {
        $login = $this->login();
        $this->error($this->refresh($login['refresh_token'], 'device-b'), 'session_revoked');
        $this->error($this->me($login['access_token']), 'session_revoked');
    }

	public function test_single_device_logout_revokes_only_its_assist_grants(): void
	{
		require_once __DIR__ . '/../../includes/runtime/bootstrap.php';
		$a = $this->login();
		$b = $this->login('device-b');
		$db = SaldiDb::connect(saldi_runtime_config(), RestApiEnv::testDb());
		$rights = $db->value('SELECT rettigheder FROM brugere WHERE id = ?', [self::$user['id']]);
		try {
			$db->exec('UPDATE brugere SET rettigheder = ? WHERE id = ?', [str_repeat('1', 16), self::$user['id']]);
			saldi_runtime_ensure_schema($db);
			$ctx = saldi_runtime_context_for_user($db, ['id' => self::$id], (int)self::$user['id'], 'user:' . self::$user['id']);
			SaldiSettings::set($db, $ctx, 'assistant', 'tools_enabled', 'on');
			$grants = new SaldiGrantService(saldi_runtime_master(), $db, $ctx);
			$aGrant = $grants->issueMobile(JWT::decode($a['access_token'])['sid'], bin2hex(random_bytes(16)));
			$bGrant = $grants->issueMobile(JWT::decode($b['access_token'])['sid'], bin2hex(random_bytes(16)));
			$r = RestApiEnv::http('POST', self::AUTH . 'logout.php', [], ['Authorization: Bearer ' . $a['access_token']]);
			self::assertSame(200, $r['status'], $r['body']);
			self::assertNotNull($db->value('SELECT revoked_at FROM agent_grants WHERE id = ?', [$aGrant['grant']['id']]));
			self::assertNull($db->value('SELECT revoked_at FROM agent_grants WHERE id = ?', [$bGrant['grant']['id']]));
			$open = saldi_runtime_context_from_token($bGrant['token']);
			$open['db']->close();
			try {
				saldi_runtime_context_from_token($aGrant['token']);
				self::fail('Logged out device grant accepted');
			} catch (SaldiRuntimeError $e) {
				self::assertSame('GRANT_REVOKED', $e->errorCode);
				self::assertSame(401, $e->httpStatus());
			}
		} finally {
			$db->exec('UPDATE brugere SET rettigheder = ? WHERE id = ?', [$rights, self::$user['id']]);
			$db->close();
			saldi_runtime_reset();
		}
	}

    public function test_logout_device_and_push_cleanup_then_logout_all(): void
    {
        $a = $this->login();
        $b = $this->login('device-b');
        $payload = JWT::decode($a['access_token']);
        foreach (['device-a', 'device-b'] as $device) {
            RestApiEnv::rows(self::$tenant, 'INSERT INTO push_tokens(user_id,token,db_name,subject,principal,device_id) VALUES ($1,$2,$3,$4,$5,$6) ON CONFLICT(subject,device_id) DO UPDATE SET token=excluded.token', [self::$user['id'], bin2hex(random_bytes(32)), RestApiEnv::testDb(), $payload['sub'], 'account', $device]);
        }
        $r = RestApiEnv::http('POST', self::AUTH . 'logout.php', [], ['Authorization: Bearer ' . $a['access_token']]);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->error($this->me($a['access_token']), 'session_revoked');
        $this->assertSame(200, $this->me($b['access_token'])['status']);
        $this->assertSame(['device-b'], array_column(RestApiEnv::rows(self::$tenant, 'SELECT device_id FROM push_tokens WHERE subject=$1', [$payload['sub']]), 'device_id'));
        $c = $this->login('device-c');
        $r = RestApiEnv::http('POST', self::AUTH . 'logout.php', ['all' => true], ['Authorization: Bearer ' . $b['access_token']]);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->error($this->me($b['access_token']), 'session_revoked');
        $this->error($this->me($c['access_token']), 'session_revoked');
        $this->assertCount(0, RestApiEnv::rows(self::$tenant, 'SELECT id FROM push_tokens WHERE subject=$1', [$payload['sub']]));
    }

    public function test_password_revocation_is_immediate_and_bumps_epoch(): void
    {
        $login = $this->login();
        $payload = JWT::decode($login['access_token']);
        RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET kode=$1 WHERE id=$2', [md5(bin2hex(random_bytes(24))), self::$user['id']]);
        mobileRevokeUser((int)self::$user['id'], 'password_changed', RestApiEnv::testDb());
        $this->error($this->me($login['access_token']), 'session_revoked');
        $this->error($this->refresh($login['refresh_token']), 'session_revoked');
        $this->assertGreaterThan($payload['auth_version'], self::$auth->version($payload['sub'], self::$id));
        // A fresh login after the password update's epoch succeeds.
        RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET kode=$1 WHERE id=$2', [md5(RestApiEnv::password()), self::$user['id']]);
        $this->assertSame(200, $this->me($this->login()['access_token'])['status']);
    }

    public function test_me_receipt_only_rights_currency_and_capabilities(): void
    {
        RestApiEnv::rows(self::$tenant, "DELETE FROM settings WHERE var_name='baseCurrency'");
        RestApiEnv::rows(self::$tenant, "INSERT INTO settings(var_name,var_grp,var_value,user_id) VALUES ('baseCurrency','finans','EUR',0)");
        $r = $this->me($this->login()['access_token']);
        $this->assertSame(200, $r['status'], $r['body']);
        $data = $r['json']['data'];
        $this->assertSame(Subject::build('account', (int)self::$user['id']), $data['subject']);
        $this->assertSame('account', $data['principal']);
        $this->assertSame(['id' => (int)self::$user['id'], 'username' => RestApiEnv::user(), 'name' => RestApiEnv::user()], $data['user']);
        $this->assertNull($data['ansat_id']);
        $this->assertSame('EUR', $data['base_currency']);
        $this->assertSame(['finans' => false, 'debitor' => false, 'lager' => false, 'sager' => false, 'bilag' => false, 'admin' => false, 'assistant' => false], $data['rights']);
        $tier1 = ['api_version' => 1, 'attachments' => 'v2', 'overview' => 'v2', 'multipart' => true, 'ocr_token' => true, 'push' => true, 'reminders' => true];
        $this->assertSame($tier1, array_intersect_key($data['capabilities'], $tier1));
    }

    public function test_company_name_in_login_challenge_completion_and_me_has_registry_fallbacks(): void
    {
        RestApiEnv::rows(self::$tenant, "DELETE FROM adresser WHERE art IN ('S','K')");
        RestApiEnv::rows(self::$tenant, "INSERT INTO adresser(art,firmanavn) VALUES ('K','Supplier name is not our company')");
        foreach (['Nordbro ApS', '   ', null] as $name) {
            RestApiEnv::rows(self::$tenant, "DELETE FROM adresser WHERE art='S'");
            if ($name !== null) {
                RestApiEnv::rows(self::$tenant, "INSERT INTO adresser(art,firmanavn) VALUES ('S',$1)", [$name]);
            }
            $pair = $this->login();
            $expected = $name === 'Nordbro ApS' ? $name : RestApiEnv::accountOpen();
            $this->assertSame($expected, $pair['tenant']['company_name']);
            $this->assertSame(RestApiEnv::accountOpen(), $pair['tenant']['name']);
            $me = $this->me($pair['access_token']);
            $this->assertSame(200, $me['status'], $me['body']);
            $this->assertSame($pair['tenant'], $me['json']['data']['tenant']);
        }
        RestApiEnv::rows(self::$tenant, "INSERT INTO adresser(art,firmanavn) VALUES ('S','Nordbro ApS')");
        $challenge = $this->challenge();
        $completion = RestApiEnv::http('POST', self::AUTH . 'login.php', [
            'challenge_id' => $challenge['challenge']['challenge_id'], 'code' => $challenge['service']->code, 'device_id' => 'device-a',
        ]);
        $this->assertSame(200, $completion['status'], $completion['body']);
        $this->assertSame('Nordbro ApS', $completion['json']['data']['tenant']['company_name']);
        RestApiEnv::rows(self::$tenant, 'ALTER TABLE adresser RENAME COLUMN firmanavn TO fxp_firmanavn');
        try {
            $pair = $this->login();
            $this->assertSame(RestApiEnv::accountOpen(), $pair['tenant']['company_name']);
            $this->assertSame($pair['tenant'], $this->me($pair['access_token'])['json']['data']['tenant']);
        } finally {
            RestApiEnv::rows(self::$tenant, 'ALTER TABLE adresser RENAME COLUMN fxp_firmanavn TO firmanavn');
        }
    }

    public function test_legacy_tokens_and_header_fallback_are_rejected(): void
    {
        $token = RestApiEnv::signToken(['type' => 'refresh', 'user_id' => self::$user['id'], 'tenant_id' => self::$id]);
        $this->error($this->refresh($token), 'session_revoked');
        $token = RestApiEnv::signToken(['type' => 'access', 'user_id' => self::$user['id']]);
        $r = RestApiEnv::http('GET', self::AUTH . 'me.php', null, ['Authorization: Bearer ' . $token, 'X-Tenant-ID: ' . self::$id]);
        $this->error($r, 'session_revoked');
    }

    public function test_equal_ids_across_principals_and_tenants_never_share_revocation(): void
    {
        $otherId = (int)RestApiEnv::rows(self::$master, "INSERT INTO regnskab(regnskab,db,lukket) VALUES ($1,$2,'') RETURNING id", ['auth-isolation-' . bin2hex(random_bytes(4)), RestApiEnv::testDb()])[0]['id'];
        $registryFamily = null;
        try {
            $account = self::$auth->create(self::$user, 'account', self::$id, 'same-device');
            $registry = self::$auth->create(self::$user, 'registry', null, 'same-device');
            $registryFamily = JWT::decode($registry['access_token'])['sid'];
            $other = self::$auth->create(self::$user, 'account', $otherId, 'same-device');
            revokeSessions(Subject::build('account', (int)self::$user['id']), 'rights_changed', self::$id);
            MobileAuth::checkSession(JWT::decode($registry['access_token']));
            MobileAuth::checkSession(JWT::decode($other['access_token']));
            $this->assertNotSame(JWT::decode($account['access_token'])['sub'], JWT::decode($registry['access_token'])['sub']);
        } finally {
            RestApiEnv::rows(self::$master, 'DELETE FROM refresh_sessions WHERE family_id=$1 OR tenant_id=$2', [$registryFamily, $otherId]);
            RestApiEnv::rows(self::$master, 'DELETE FROM auth_versions WHERE subject=$1 AND tenant_id=0 OR tenant_id=$2', [Subject::build('registry', (int)self::$user['id']), $otherId]);
            RestApiEnv::rows(self::$master, 'DELETE FROM regnskab WHERE id=$1', [$otherId]);
        }
    }

    public function test_registry_refresh_me_and_membership_revocation_use_registry_identity(): void
    {
        $pair = self::$auth->create(self::$registryUser, 'registry', null, 'device-a');
        $r = $this->me($pair['access_token']);
        $this->assertSame(200, $r['status'], $r['body']);
        $data = $r['json']['data'];
        $this->assertSame('registry', $data['principal']);
        $this->assertSame(self::$registryUser['brugernavn'], $data['user']['username']);
        $this->assertNull($data['base_currency']);
        $this->assertNull($data['tenant']);
        $this->assertSame(['admin' => false, 'oprette' => false, 'slette' => false, 'tenant_ids' => [self::$id], 'assistant' => false], $data['rights']);
        $next = $this->refresh($pair['refresh_token']);
        $this->assertSame(200, $next['status'], $next['body']);
        $parent = JWT::decode($next['json']['data']['access_token']);
        $exchanged = self::$auth->create(self::$registryUser, 'registry', self::$id, 'device-a', $parent['auth_version'], $parent['sid']);
        $this->assertSame($parent['sid'], JWT::decode($exchanged['access_token'])['sid']);
        $this->assertSame(200, $this->me($next['json']['data']['access_token'])['status']);
        $this->assertSame(200, $this->refresh($next['json']['data']['refresh_token'])['status']);
        $again = self::$auth->create(self::$registryUser, 'registry', self::$id, 'device-a', $parent['auth_version'], $parent['sid']);
        $retry = $this->refresh($exchanged['refresh_token']);
        $this->assertSame(200, $retry['status'], $retry['body']);
        $this->assertSame(JWT::decode($again['refresh_token'])['jti'], JWT::decode($retry['json']['data']['refresh_token'])['jti']);
        $this->assertSame(200, $this->me($exchanged['access_token'])['status']);
        RestApiEnv::rows(self::$master, "UPDATE brugere SET rettigheder=',,,' WHERE id=$1", [self::$registryUser['id']]);
        mobileRevokeUser((int)self::$registryUser['id'], 'registry_membership_changed', RestApiEnv::masterDb());
        $this->error($this->me($pair['access_token']), 'session_revoked');
        $this->error($this->refresh($next['json']['data']['refresh_token']), 'session_revoked');
        $this->error($this->me($exchanged['access_token']), 'session_revoked');
    }

    public function test_appended_registry_step_revokes_rows_without_replay_storage_and_is_idempotent(): void
    {
        $conn = RestApiEnv::connect(RestApiEnv::masterDb());
        $schema = 'b2_auth_' . bin2hex(random_bytes(8));
        $quoted = pg_escape_identifier($conn, $schema);
        RestApiEnv::rows($conn, "CREATE SCHEMA $quoted");
        try {
            RestApiEnv::rows($conn, "SET search_path TO $quoted");
            RestApiEnv::rows($conn, 'CREATE TABLE settings(id serial PRIMARY KEY,var_name text,var_grp text,var_value text,user_id integer)');
            mobileRegistryUpdates($conn);
            RestApiEnv::rows($conn, "INSERT INTO refresh_sessions(jti_hash,family_id,subject,principal,tenant_id,device_id,expires_at) VALUES ($1,$2,$3,'account',$4,'legacy',now()+INTERVAL '30 days')", [hash('sha256', random_bytes(32)), MobileAuth::uuid(), Subject::build('account', (int)self::$user['id']), self::$id]);
            // Reproduce the final B1 marker on an installed registry with an existing session.
            RestApiEnv::rows($conn, "UPDATE settings SET var_value='r06_timezone_correction' WHERE var_name='mobile_registry_step'");
            mobileRegistryUpdates($conn);
            $row = RestApiEnv::rows($conn, 'SELECT revoked_at FROM refresh_sessions')[0];
            $this->assertNotNull($row['revoked_at']);
            $this->assertSame(array_key_last(mobileRegistryUpdateSteps()), RestApiEnv::rows($conn, "SELECT var_value FROM settings WHERE var_name='mobile_registry_step'")[0]['var_value']);
            $this->assertCount(5, RestApiEnv::rows($conn, "SELECT indexname FROM pg_indexes WHERE schemaname=$1 AND indexname IN ('mobile_session_family_scope_latest','mobile_session_expiry','mobile_session_revoked','mobile_challenge_consumed','mobile_bucket_updated')", [$schema]));
            mobileRegistryUpdates($conn);
            $this->assertSame($row['revoked_at'], RestApiEnv::rows($conn, 'SELECT revoked_at FROM refresh_sessions')[0]['revoked_at']);
        } finally {
            RestApiEnv::rows($conn, 'RESET search_path');
            RestApiEnv::rows($conn, "DROP SCHEMA $quoted CASCADE");
            pg_close($conn);
        }
    }

    public function test_auth_input_validation_and_missing_bearer_codes(): void
    {
        $this->error($this->me(bin2hex(random_bytes(20))), 'invalid_token');
        $this->error(RestApiEnv::http('POST', self::AUTH . 'login.php', ['device_id' => '']), 'validation_failed', 400);
        $login = $this->login();
        $this->error(RestApiEnv::http('POST', self::AUTH . 'logout.php', ['all' => 'true'], ['Authorization: Bearer ' . $login['access_token']]), 'validation_failed', 400);
        $this->error(RestApiEnv::http('PUT', self::AUTH . 'login.php', []), 'validation_failed', 405);
    }

    public function test_account_close_revokes_families_immediately(): void
    {
        $login = $this->login();
        RestApiEnv::rows(self::$master, "UPDATE regnskab SET lukket='on' WHERE id=$1", [self::$id]);
        mobileRevokeTenant(self::$id);
        $this->error($this->me($login['access_token']), 'session_revoked');
    }

    public function test_ip_allowlist_exact_match_and_mobile_exception(): void
    {
        RestApiEnv::rows(self::$tenant, "UPDATE brugere SET ip_address='192.0.2.55' WHERE id=$1", [self::$user['id']]);
        $body = ['account_name' => RestApiEnv::accountOpen(), 'username' => RestApiEnv::user(), 'password' => RestApiEnv::password()];
        $this->error(RestApiEnv::http('POST', self::AUTH . 'login.php', $body), 'ip_rejected', 403);
        $wrong = $body;
        $wrong['password'] = bin2hex(random_bytes(24));
        $this->error(RestApiEnv::http('POST', self::AUTH . 'login.php', $wrong), 'ip_rejected', 403);
        RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET mobil_undtaget=true WHERE id=$1', [self::$user['id']]);
        $r = RestApiEnv::http('POST', self::AUTH . 'login.php', $body);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertSame('legacy', JWT::decode($r['json']['data']['access_token'])['device_id']);
        MobileAuth::enforceIp(['ip_address' => '127.0.0.1,192.0.2.55'], '127.0.0.1');
    }

    public function test_refresh_ip_rejection_preserves_rotation_and_grace_and_legacy_token(): void
    {
        foreach (['device-a', 'legacy'] as $device) {
            RestApiEnv::rows(self::$tenant, "UPDATE brugere SET ip_address='',mobil_undtaget=false WHERE id=$1", [self::$user['id']]);
            $pair = $this->login($device);
            $payload = JWT::decode($pair['refresh_token']);
            $before = RestApiEnv::rows(self::$master, 'SELECT * FROM refresh_sessions WHERE family_id=$1 ORDER BY id', [$payload['sid']]);
            RestApiEnv::rows(self::$tenant, "UPDATE brugere SET ip_address='192.0.2.55' WHERE id=$1", [self::$user['id']]);
            $body = ['refresh_token' => $pair['refresh_token']];
            if ($device !== 'legacy') {
                $body['device_id'] = $device;
            }
            $this->error(RestApiEnv::http('POST', self::AUTH . 'refresh.php', $body), 'ip_rejected', 403);
            $this->assertSame($before, RestApiEnv::rows(self::$master, 'SELECT * FROM refresh_sessions WHERE family_id=$1 ORDER BY id', [$payload['sid']]));
            RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET mobil_undtaget=true WHERE id=$1', [self::$user['id']]);
            $allowed = RestApiEnv::http('POST', self::AUTH . 'refresh.php', $body);
            $this->assertSame(200, $allowed['status'], $allowed['body']);
            if ($device !== 'legacy') {
                RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET mobil_undtaget=false WHERE id=$1', [self::$user['id']]);
                $this->error($this->refresh($pair['refresh_token']), 'ip_rejected', 403);
                RestApiEnv::rows(self::$tenant, "UPDATE brugere SET ip_address='127.0.0.1,::1' WHERE id=$1", [self::$user['id']]);
                $retry = $this->refresh($pair['refresh_token']);
                $this->assertSame(200, $retry['status'], $retry['body']);
                $this->assertSame(JWT::decode($allowed['json']['data']['refresh_token'])['jti'], JWT::decode($retry['json']['data']['refresh_token'])['jti']);
            }
        }
    }

    public function test_resource_tokens_recheck_scheduled_and_manual_closure_without_web_hook(): void
    {
        RestApiEnv::rows(self::$tenant, "UPDATE brugere SET rettigheder='1111111111111111' WHERE id=$1", [self::$user['id']]);
        $pair = $this->login();
        $path = '/restapi/endpoints/v1/products/index.php?id=2147483647';
        $headers = ['Authorization: Bearer ' . $pair['access_token']];
        try {
            foreach (["lukkes=CURRENT_DATE+1", "lukkes='2099-12-31'"] as $state) {
                RestApiEnv::rows(self::$master, "UPDATE regnskab SET $state WHERE id=$1", [self::$id]);
                $this->assertNotSame(403, RestApiEnv::http('GET', $path, null, $headers)['status']);
            }
            foreach (["lukkes=CURRENT_DATE", "lukkes=CURRENT_DATE-1", "lukkes=NULL,lukket='on'"] as $state) {
                RestApiEnv::rows(self::$master, "UPDATE regnskab SET $state WHERE id=$1", [self::$id]);
                foreach (['GET', 'PUT'] as $method) {
                    $this->error(RestApiEnv::http($method, $path, $method === 'GET' ? null : [], $headers), 'account_closed', 403);
                }
            }
            $this->assertNull(RestApiEnv::rows(self::$master, 'SELECT revoked_at FROM refresh_sessions WHERE family_id=$1', [JWT::decode($pair['access_token'])['sid']])[0]['revoked_at']);
        } finally {
            RestApiEnv::rows(self::$master, "UPDATE regnskab SET lukket='',lukkes=NULL WHERE id=$1", [self::$id]);
            RestApiEnv::rows(self::$tenant, "UPDATE brugere SET rettigheder='0000000000000000' WHERE id=$1", [self::$user['id']]);
        }
    }

    public function test_successful_logins_refund_only_account_debits(): void
    {
        $key = MobileAuth::bucketKey('account', RestApiEnv::accountOpen(), RestApiEnv::user());
        $body = ['account_name' => RestApiEnv::accountOpen(), 'username' => RestApiEnv::user(), 'password' => bin2hex(random_bytes(24))];
        $this->error(RestApiEnv::http('POST', self::AUTH . 'login.php', $body), 'invalid_credentials');
        for ($i = 0; $i < 15; $i++) {
            $this->login();
        }
        $tokens = (float)RestApiEnv::rows(self::$master, "SELECT tokens FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1", [$key])[0]['tokens'];
        $this->assertGreaterThanOrEqual(9, $tokens);
        $this->assertLessThan(9.5, $tokens, 'Verified attempts must not erase the failed attempt');
        $ip = RestApiEnv::rows(self::$master, "SELECT tokens FROM auth_rate_limits WHERE bucket_type='ip' AND bucket_key=ANY($1::text[])", ['{127.0.0.1,::1}']);
        $this->assertTrue(count(array_filter($ip, static fn(array $row): bool => (float)$row['tokens'] < 119)) > 0);
    }

    public function test_disabled_user_and_closed_account_codes(): void
    {
        RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET status=false WHERE id=$1', [self::$user['id']]);
        $body = ['account_name' => RestApiEnv::accountOpen(), 'username' => RestApiEnv::user(), 'password' => RestApiEnv::password()];
        $this->error(RestApiEnv::http('POST', self::AUTH . 'login.php', $body), 'user_disabled', 403);
        RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET status=true WHERE id=$1', [self::$user['id']]);
        RestApiEnv::rows(self::$master, "UPDATE regnskab SET lukket='on' WHERE id=$1", [self::$id]);
        $this->error(RestApiEnv::http('POST', self::AUTH . 'login.php', $body), 'account_closed', 403);
    }

    public function test_account_bucket_normalizes_case_and_returns_429(): void
    {
        $body = ['account_name' => RestApiEnv::accountOpen(), 'username' => strtoupper(RestApiEnv::user()), 'password' => bin2hex(random_bytes(24))];
        for ($i = 0; $i < 10; $i++) {
            $this->error(RestApiEnv::http('POST', self::AUTH . 'login.php', $body), 'invalid_credentials');
        }
        $body['username'] = RestApiEnv::user();
        $this->error(RestApiEnv::http('POST', self::AUTH . 'login.php', $body), 'rate_limited', 429);
    }

    public function test_ip_bucket_is_shared_across_accounts_and_refills(): void
    {
        $ip = '2001:db8:' . bin2hex(random_bytes(2)) . '::1';
        $key = inet_ntop(inet_pton($ip));
        $account = 'test-account-' . bin2hex(random_bytes(8));
        $accountKey = MobileAuth::bucketKey('account', $account, 'user');
        try {
            RestApiEnv::rows(self::$master, "INSERT INTO auth_rate_limits(bucket_type,bucket_key,tokens,updated_at) VALUES ('ip',$1,0,now())", [$key]);
            try {
                self::$auth->rateLimit($ip, $account, 'user');
                $this->fail('Empty IP bucket accepted');
            } catch (MobileAuthError $e) {
                $this->assertSame('rate_limited', $e->error);
            }
            RestApiEnv::rows(self::$master, "UPDATE auth_rate_limits SET updated_at=now()-INTERVAL '2 seconds' WHERE bucket_key=$1", [$key]);
            self::$auth->rateLimit($ip, $account, 'user');
            $this->assertTrue(true);
        } finally {
            RestApiEnv::rows(self::$master, 'DELETE FROM auth_rate_limits WHERE bucket_key=ANY($1::text[])', ['{' . $key . ',' . $accountKey . '}']);
        }
    }

    /** @return array{challenge:array,service:MobileAuthCapturedChallenges} */
    private function challenge(string $phone = ''): array
    {
        $user = self::$user;
        $user['tlf'] = $phone;
        $service = new MobileAuthCapturedChallenges(self::$auth);
        $version = self::$auth->version(Subject::build('account', (int)$user['id']), self::$id);
        return ['challenge' => $service->create($user, self::$id, 'device-a', $version), 'service' => $service];
    }

    private function challengeError(MobileAuthCapturedChallenges $service, string $id, string $code, string $device = 'device-a'): void
    {
        try {
            $service->complete($id, $code, $device);
            $this->fail('Challenge should have failed');
        } catch (MobileAuthError $e) {
            $this->assertSame('2fa_failed', $e->error);
        }
    }

    public function test_refresh_does_not_lock_old_rotations_in_a_large_family(): void
    {
        $pair = $this->login();
        $payload = JWT::decode($pair['refresh_token']);
        $row = RestApiEnv::rows(self::$master, 'SELECT * FROM refresh_sessions WHERE family_id=$1', [$payload['sid']])[0];
        $oldIds = [];
        $seed = bin2hex(random_bytes(16));
        $locker = RestApiEnv::connect(RestApiEnv::masterDb());
        try {
            for ($i = 1; $i <= 100; $i++) {
                $oldIds[] = (int)RestApiEnv::rows(self::$master,
                    'INSERT INTO refresh_sessions(id,jti_hash,family_id,subject,principal,tenant_id,device_id,expires_at,auth_version,last_used_at) VALUES ($1,$2,$3,$4,$5,$6,$7,now()+INTERVAL \'30 days\',$8,now()-INTERVAL \'2 minutes\') RETURNING id',
                    [-((int)$row['id'] * 1000 + $i), hash('sha256', $seed . $i), $row['family_id'], $row['subject'], $row['principal'], $row['tenant_id'], $row['device_id'], $row['auth_version']])[0]['id'];
            }
            // One old row is the immediate predecessor; leave that unlocked for grace handling.
            RestApiEnv::rows($locker, 'BEGIN');
            RestApiEnv::rows($locker, 'SELECT id FROM refresh_sessions WHERE family_id=$1 AND id<$2 FOR UPDATE', [$payload['sid'], $oldIds[0]]);
            self::$auth->store()->execute("SET lock_timeout='500ms'");
            $savedIp = ifset($_SERVER, 'REMOTE_ADDR');
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            try {
                $rotated = self::$auth->refresh($payload, 'device-a');
                $this->assertNotSame($payload['jti'], JWT::decode($rotated['refresh_token'])['jti']);
            } finally {
                if ($savedIp === null) {
                    unset($_SERVER['REMOTE_ADDR']);
                } else {
                    $_SERVER['REMOTE_ADDR'] = $savedIp;
                }
            }
        } finally {
            self::$auth->store()->execute('RESET lock_timeout');
            RestApiEnv::rows($locker, 'ROLLBACK');
            pg_close($locker);
            RestApiEnv::rows(self::$master, 'DELETE FROM refresh_sessions WHERE id=ANY($1::int[])', ['{' . implode(',', $oldIds) . '}']);
        }
    }

    public function test_housekeeping_prunes_stale_records_and_keeps_grace_and_reuse_detection(): void
    {
        $pair = $this->login();
        $expiredPayload = JWT::decode($pair['refresh_token']);
        $first = $this->refresh($pair['refresh_token']);
        $this->assertSame(200, $first['status'], $first['body']);
        $second = $this->refresh($first['json']['data']['refresh_token']);
        $this->assertSame(200, $second['status'], $second['body']);
        RestApiEnv::rows(self::$master, "UPDATE refresh_sessions SET expires_at=now()-INTERVAL '2 days' WHERE jti_hash=$1", [hash('sha256', $expiredPayload['jti'])]);
        $revoked = $this->login('revoked-device');
        $revokedFamily = JWT::decode($revoked['refresh_token'])['sid'];
        RestApiEnv::rows(self::$master, 'UPDATE refresh_sessions SET revoked_at=now() WHERE family_id=$1', [$revokedFamily]);
        $oldRevoked = $this->login('old-revoked-device');
        $oldFamily = JWT::decode($oldRevoked['refresh_token'])['sid'];
        RestApiEnv::rows(self::$master, "UPDATE refresh_sessions SET revoked_at=now()-INTERVAL '31 days' WHERE family_id=$1", [$oldFamily]);
        $expiredChallenge = $this->challenge()['challenge']['challenge_id'];
        $activeChallenge = $this->challenge()['challenge']['challenge_id'];
        $consumedChallenge = $this->challenge()['challenge']['challenge_id'];
        RestApiEnv::rows(self::$master, "UPDATE auth_challenges SET expires_at=now()-INTERVAL '2 days' WHERE id=$1", [$expiredChallenge]);
        RestApiEnv::rows(self::$master, "UPDATE auth_challenges SET consumed_at=now()-INTERVAL '2 days' WHERE id=$1", [$consumedChallenge]);
        $key = 'housekeeping-' . bin2hex(random_bytes(8));
        try {
            RestApiEnv::rows(self::$master, "INSERT INTO auth_rate_limits(bucket_type,bucket_key,tokens,updated_at) VALUES ('account',$1,0,now()-INTERVAL '2 days'),('ip',$1,0,now())", [$key]);
            // prune() removes at most 500 stale rows per table and call, so a shared registry with older
            // leftovers needs several batches before this test's rows are reached.
            for ($batch = 0; $batch < 50; $batch++) {
                self::$auth->prune();
                $stale = RestApiEnv::rows(self::$master, "SELECT (SELECT count(*) FROM auth_rate_limits WHERE updated_at<now()-INTERVAL '1 day' AND (blocked_until IS NULL OR blocked_until<now()))
                    + (SELECT count(*) FROM refresh_sessions WHERE expires_at<now()-INTERVAL '1 day' OR revoked_at<now()-INTERVAL '30 days')
                    + (SELECT count(*) FROM auth_challenges WHERE expires_at<now()-INTERVAL '1 day' OR consumed_at<now()-INTERVAL '1 day') AS n");
                if ((int)$stale[0]['n'] === 0) {
                    break;
                }
            }
            $remaining = RestApiEnv::rows(self::$master, 'SELECT jti_hash FROM refresh_sessions WHERE family_id=$1', [$expiredPayload['sid']]);
            $this->assertCount(2, $remaining);
            $this->assertCount(1, RestApiEnv::rows(self::$master, 'SELECT id FROM refresh_sessions WHERE family_id=$1', [$revokedFamily]));
            $this->assertSame([], RestApiEnv::rows(self::$master, 'SELECT id FROM refresh_sessions WHERE family_id=$1', [$oldFamily]));
            $this->assertSame([], RestApiEnv::rows(self::$master, 'SELECT id FROM auth_challenges WHERE id IN ($1,$2)', [$expiredChallenge, $consumedChallenge]));
            $this->assertCount(1, RestApiEnv::rows(self::$master, 'SELECT id FROM auth_challenges WHERE id=$1', [$activeChallenge]));
            $this->assertSame([['bucket_type' => 'ip']], RestApiEnv::rows(self::$master, 'SELECT bucket_type FROM auth_rate_limits WHERE bucket_key=$1', [$key]));
            $retry = $this->refresh($first['json']['data']['refresh_token']);
            $this->assertSame(200, $retry['status'], $retry['body']);
            $this->assertSame(JWT::decode($second['json']['data']['refresh_token'])['jti'], JWT::decode($retry['json']['data']['refresh_token'])['jti']);
            $this->error($this->refresh($pair['refresh_token']), 'session_revoked');
            $this->error($this->refresh($second['json']['data']['refresh_token']), 'session_revoked');
        } finally {
            RestApiEnv::rows(self::$master, 'DELETE FROM auth_rate_limits WHERE bucket_key=$1', [$key]);
        }
    }

    public function test_twofactor_delivery_channels_success_and_single_consumption(): void
    {
        $email = $this->challenge();
        $this->assertSame('email', $email['challenge']['channel']);
        $this->assertArrayNotHasKey('code', $email['challenge']);
        $verified = $email['service']->complete($email['challenge']['challenge_id'], $email['service']->code, 'device-a');
        $this->assertSame(self::$id, $verified['tenant_id']);
        $this->challengeError($email['service'], $email['challenge']['challenge_id'], $email['service']->code);
        $sms = $this->challenge('4512345678');
        $this->assertSame('sms', $sms['challenge']['channel']);
        $this->assertSame('***5678', $sms['challenge']['hint']);
        $this->assertSame('sms', $sms['service']->channel);
    }

    public function test_twofactor_wrong_code_attempt_limit_expiry_and_device_binding(): void
    {
        $c = $this->challenge();
        $id = $c['challenge']['challenge_id'];
        $wrong = str_pad((string)(((int)$c['service']->code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
        for ($i = 0; $i < 5; $i++) {
            $this->challengeError($c['service'], $id, $wrong);
        }
        $this->challengeError($c['service'], $id, $c['service']->code);
        $row = RestApiEnv::rows(self::$master, 'SELECT * FROM auth_challenges WHERE id=$1', [$id])[0];
        $this->assertSame(5, (int)$row['attempts']);
        $expired = $this->challenge();
        RestApiEnv::rows(self::$master, "UPDATE auth_challenges SET expires_at=now()-INTERVAL '1 second' WHERE id=$1", [$expired['challenge']['challenge_id']]);
        $this->challengeError($expired['service'], $expired['challenge']['challenge_id'], $expired['service']->code);
        $device = $this->challenge();
        $this->challengeError($device['service'], $device['challenge']['challenge_id'], $device['service']->code, 'device-b');
    }

    public function test_password_change_invalidates_pending_twofactor(): void
    {
        $c = $this->challenge();
        revokeSessions(Subject::build('account', (int)self::$user['id']), 'password_changed', self::$id);
        $this->challengeError($c['service'], $c['challenge']['challenge_id'], $c['service']->code);
    }

    public function test_challenge_completion_http_issues_tokens_without_touching_tmp_kode(): void
    {
        $before = RestApiEnv::rows(self::$tenant, 'SELECT tmp_kode FROM brugere WHERE id=$1', [self::$user['id']])[0]['tmp_kode'];
        $c = $this->challenge();
        $r = RestApiEnv::http('POST', self::AUTH . 'login.php', ['challenge_id' => $c['challenge']['challenge_id'], 'code' => $c['service']->code, 'device_id' => 'device-a']);
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertNotEmpty($r['json']['data']['refresh_token']);
        $this->assertSame(200, $this->me($r['json']['data']['access_token'])['status']);
        $after = RestApiEnv::rows(self::$tenant, 'SELECT tmp_kode FROM brugere WHERE id=$1', [self::$user['id']])[0]['tmp_kode'];
        $this->assertSame($before, $after);
    }
    public function test_http_twofactor_start_and_email_delivery_via_local_mailpit(): void
    {
        if (getenv('SMTP_HOST') !== 'mailpit') {
            $this->markTestSkipped('Local Mailpit transport not configured');
        }
        $address = 'b2-' . bin2hex(random_bytes(8)) . '@example.invalid';
        RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET twofactor=true,email=$1 WHERE id=$2', [$address, self::$user['id']]);
        $body = ['account_name' => RestApiEnv::accountOpen(), 'username' => RestApiEnv::user(), 'password' => RestApiEnv::password(), 'device_id' => 'device-a'];
        $r = RestApiEnv::http('POST', self::AUTH . 'login.php', $body);
        $this->assertSame(200, $r['status'], $r['body']);
        $challenge = $r['json']['data'];
        $this->assertTrue($challenge['2fa_required']);
        $this->assertSame('email', $challenge['channel']);
        $this->assertArrayNotHasKey('access_token', $challenge);
        $message = null;
        $ch = curl_init('http://mailpit:8025/api/v1/messages');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        $list = json_decode(curl_exec($ch), true);
        curl_close($ch);
        foreach (ifset($list, 'messages', []) as $candidate) {
            if (in_array($address, array_column(ifset($candidate, 'To', []), 'Address'), true)) {
                $message = $candidate['ID'];
                break;
            }
        }
        $this->assertNotNull($message, 'Expected a challenge message in local Mailpit');
        try {
            $ch = curl_init('http://mailpit:8025/api/v1/message/' . rawurlencode($message));
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            $mail = json_decode(curl_exec($ch), true);
            curl_close($ch);
            $matched = preg_match('/kode: ([0-9]{6})/', (string)ifset($mail, 'Text', ''), $matches);
            $this->assertSame(1, $matched);
            $completion = RestApiEnv::http('POST', self::AUTH . 'login.php', ['challenge_id' => $challenge['challenge_id'], 'code' => $matches[1], 'device_id' => 'device-a']);
            $this->assertSame(200, $completion['status'], $completion['body']);
            $this->assertSame(200, $this->me($completion['json']['data']['access_token'])['status']);
        } finally {
            $ch = curl_init('http://mailpit:8025/api/v1/messages');
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['IDs' => [$message]])]);
            curl_exec($ch);
            curl_close($ch);
        }
    }

}
