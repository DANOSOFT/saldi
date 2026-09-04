<?php
// tests/restapi/BearerAuthEnforcementTest.php
//
// Integration tests for the shared BaseEndpoint request handling (using the
// debitor/customers endpoint as the representative protected resource):
// which bearer tokens are refused, the legacy X-Tenant-ID fallback, and the
// malformed-JSON-body guard. Cases that need a token the API would never
// issue itself are signed with the install's own JWT secret and skip when
// that secret is unreadable.
//
// History:
// 20260904 CL/NTR created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once dirname(__DIR__, 2) . '/restapi/core/JWT.php';

final class BearerAuthEnforcementTest extends TestCase
{
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

    /** Access-typed claims for the seeded user; the account id can be overridden or dropped (null). */
    private function accessClaims(?int $tenantId): array
    {
        $login = RestApiEnv::loginData();
        $claims = [
            'user_id' => (int)$login['user']['id'],
            'username' => $login['user']['username'],
            'type' => 'access',
        ];
        if ($tenantId !== null) {
            $claims['tenant_id'] = $tenantId;
        }
        return $claims;
    }

    private function get(array $headers): array
    {
        return RestApiEnv::http('GET', self::CUSTOMERS . '?limit=1', null, $headers);
    }

    public function test_request_without_authorization_header_is_rejected_401(): void
    {
        $res = $this->get([]);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
        $this->assertNull($res['json']['data']);
    }

    public function test_non_bearer_authorization_scheme_is_rejected_401(): void
    {
        $res = $this->get(['Authorization: Basic ' . base64_encode(RestApiEnv::user() . ':' . RestApiEnv::password())]);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_refresh_token_is_not_accepted_as_bearer(): void
    {
        $res = $this->get(['Authorization: Bearer ' . RestApiEnv::refreshToken()]);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_token_signed_with_a_foreign_secret_is_rejected_401(): void
    {
        JWT::setSecret('not-the-install-secret-' . bin2hex(random_bytes(8)));
        $foreign = JWT::encode($this->accessClaims(RestApiEnv::regnskabId(RestApiEnv::ACCOUNT_OPEN)), 3600);

        $res = $this->get(['Authorization: Bearer ' . $foreign]);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_expired_access_token_is_rejected_401(): void
    {
        $this->requireInstallSecret();
        $expired = RestApiEnv::signToken($this->accessClaims(RestApiEnv::regnskabId(RestApiEnv::ACCOUNT_OPEN)), -60);

        $res = $this->get(['Authorization: Bearer ' . $expired]);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_access_token_for_an_unknown_account_is_rejected_400(): void
    {
        $this->requireInstallSecret();
        $token = RestApiEnv::signToken($this->accessClaims(999999999), 3600);

        $res = $this->get(['Authorization: Bearer ' . $token]);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_access_token_without_account_claim_needs_x_tenant_id_header(): void
    {
        $this->requireInstallSecret();
        $token = RestApiEnv::signToken($this->accessClaims(null), 3600);

        $without = $this->get(['Authorization: Bearer ' . $token]);
        $this->assertSame(400, $without['status'], $without['body']);
        $this->assertFalse($without['json']['success']);

        $with = $this->get([
            'Authorization: Bearer ' . $token,
            'X-Tenant-ID: ' . RestApiEnv::regnskabId(RestApiEnv::ACCOUNT_OPEN),
        ]);
        $this->assertSame(200, $with['status'], $with['body']);
        $this->assertTrue($with['json']['success']);
    }

    public function test_malformed_json_body_is_rejected_400(): void
    {
        $ch = curl_init(RestApiEnv::baseUrl() . self::CUSTOMERS);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => '{"companyName": "Truncated',
            CURLOPT_HTTPHEADER => array_merge(RestApiEnv::authHeaders(), ['Content-Type: application/json', 'Accept: application/json']),
        ]);
        $raw = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $json = json_decode($raw, true);

        $this->assertSame(400, $status, $raw);
        $this->assertIsArray($json, 'error is still a JSON envelope');
        $this->assertFalse($json['success']);
        $this->assertStringContainsString('Invalid JSON body', $json['message']);
    }
}
