<?php
// tests/restapi/AuthLoginEndpointTest.php
//
// Integration tests for POST /restapi/endpoints/v1/auth/login.php (SD-602),
// against the local docker stack with a self-provisioned tenant + user.
// Skips cleanly when the stack is absent.
//
// History:
// 20260723 CL/LH SD-602: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class AuthLoginEndpointTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
    }

    public function test_login_with_valid_credentials_returns_tokens_and_tenant(): void
    {
        $res = RestApiEnv::login(RestApiEnv::USER, RestApiEnv::PASSWORD, RestApiEnv::ACCOUNT_OPEN);

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertTrue($res['json']['success']);
        $data = $res['json']['data'];
        $this->assertNotEmpty($data['access_token']);
        $this->assertNotEmpty($data['refresh_token']);
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertSame(3600, $data['expires_in']);
        $this->assertSame(RestApiEnv::USER, $data['user']['username']);
        $this->assertSame(RestApiEnv::testDb(), $data['tenant']['db']);
    }

    public function test_login_with_wrong_password_is_rejected_401(): void
    {
        $res = RestApiEnv::login(RestApiEnv::USER, 'wrong-password', RestApiEnv::ACCOUNT_OPEN);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_login_with_unknown_user_is_rejected_401(): void
    {
        $res = RestApiEnv::login('no-such-user', 'whatever', RestApiEnv::ACCOUNT_OPEN);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_login_against_unknown_account_is_rejected_404(): void
    {
        $res = RestApiEnv::login(RestApiEnv::USER, RestApiEnv::PASSWORD, 'no-such-account');

        $this->assertSame(404, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_login_against_closed_account_is_rejected_403(): void
    {
        $res = RestApiEnv::login(RestApiEnv::USER, RestApiEnv::PASSWORD, RestApiEnv::ACCOUNT_CLOSED);

        $this->assertSame(403, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_login_without_required_fields_is_rejected_400(): void
    {
        $res = RestApiEnv::http('POST', '/restapi/endpoints/v1/auth/login.php', ['username' => 'x']);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }
}
