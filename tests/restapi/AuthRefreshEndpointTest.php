<?php
// tests/restapi/AuthRefreshEndpointTest.php
//
// Integration tests for POST /restapi/endpoints/v1/auth/refresh.php against
// the local docker stack's self-provisioned tenant: a refresh token from
// login yields a usable access token, and every other kind of token
// (access-typed, garbage, closed/unknown account, unknown user, no account
// claim) is refused with the documented status. The forged-claim cases sign
// tokens with the install's own JWT secret and skip when it is unreadable.
//
// History:
// 20260904 CL/NTR created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class AuthRefreshEndpointTest extends TestCase
{
    private const REFRESH = '/restapi/endpoints/v1/auth/refresh.php';
    private const CUSTOMERS = '/restapi/endpoints/v1/debitor/customers/';

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
        RestApiEnv::teardownTenant();
    }

    private function requireInstallSecret(): void
    {
        $reason = RestApiEnv::installSecretUnavailableReason();
        if ($reason !== null) {
            $this->markTestSkipped($reason);
        }
    }

    private function refresh(array $body, array $headers = []): array
    {
        return RestApiEnv::http('POST', self::REFRESH, $body, $headers);
    }

    /** A refresh-typed token for the seeded user with the given account id, signed with the install secret. */
    private function forgedRefreshToken(int $tenantId, ?int $userId = null): string
    {
        $login = RestApiEnv::loginData();
        return RestApiEnv::signToken([
            'user_id' => $userId ?? (int)$login['user']['id'],
            'username' => $login['user']['username'],
            'type' => 'refresh',
            'tenant_id' => $tenantId,
        ], 3600);
    }

    public function test_refresh_token_from_login_yields_a_working_access_token(): void
    {
        $res = $this->refresh(['refresh_token' => RestApiEnv::refreshToken()]);

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertTrue($res['json']['success']);
        $data = $res['json']['data'];
        $this->assertNotEmpty($data['access_token']);
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertSame(3600, $data['expires_in']);
        $this->assertArrayNotHasKey('refresh_token', $data, 'refresh does not rotate the refresh token');

        $probe = RestApiEnv::http('GET', self::CUSTOMERS . '?limit=1', null, ['Authorization: Bearer ' . $data['access_token']]);
        $this->assertSame(200, $probe['status'], $probe['body']);
        $this->assertTrue($probe['json']['success']);
    }

    public function test_access_token_is_not_accepted_as_a_refresh_token(): void
    {
        $res = $this->refresh(['refresh_token' => RestApiEnv::accessToken()]);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_garbage_refresh_token_is_rejected_401(): void
    {
        $res = $this->refresh(['refresh_token' => 'not.a.token']);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_missing_refresh_token_field_is_rejected_400(): void
    {
        $res = $this->refresh(['token' => RestApiEnv::refreshToken()]);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_get_is_not_supported_405(): void
    {
        $res = RestApiEnv::http('GET', self::REFRESH);

        $this->assertSame(405, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_refresh_for_a_closed_account_is_rejected_403(): void
    {
        $this->requireInstallSecret();
        $token = $this->forgedRefreshToken(RestApiEnv::regnskabId(RestApiEnv::ACCOUNT_CLOSED));

        $res = $this->refresh(['refresh_token' => $token]);

        $this->assertSame(403, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_refresh_for_an_unknown_account_is_rejected_401(): void
    {
        $this->requireInstallSecret();
        $token = $this->forgedRefreshToken(999999999);

        $res = $this->refresh(['refresh_token' => $token]);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_refresh_for_an_unknown_user_is_rejected_401(): void
    {
        $this->requireInstallSecret();
        $token = $this->forgedRefreshToken(RestApiEnv::regnskabId(RestApiEnv::ACCOUNT_OPEN), 999999999);

        $res = $this->refresh(['refresh_token' => $token]);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_refresh_token_without_account_claim_is_rejected_400(): void
    {
        $this->requireInstallSecret();
        $login = RestApiEnv::loginData();
        $token = RestApiEnv::signToken([
            'user_id' => (int)$login['user']['id'],
            'username' => $login['user']['username'],
            'type' => 'refresh',
        ], 3600);

        $res = $this->refresh(['refresh_token' => $token]);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_legacy_token_without_account_claim_falls_back_to_x_tenant_id_header(): void
    {
        $this->requireInstallSecret();
        $login = RestApiEnv::loginData();
        $token = RestApiEnv::signToken([
            'user_id' => (int)$login['user']['id'],
            'username' => $login['user']['username'],
            'type' => 'refresh',
        ], 3600);

        $res = $this->refresh(['refresh_token' => $token], ['X-Tenant-ID: ' . RestApiEnv::regnskabId(RestApiEnv::ACCOUNT_OPEN)]);

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertNotEmpty($res['json']['data']['access_token']);
    }
}
