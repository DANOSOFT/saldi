<?php
// tests/restapi/JwtTest.php
//
// Unit tests for restapi/core/JWT.php (SD-602). Pure — no DB, no HTTP.
//
// NOTE on the default secret: JWT.php currently falls back to a derived
// default when setSecret() was never called. That fallback is the subject of
// SD-587 (deterministic-secret fix, Nicolai) and is deliberately NOT pinned
// here — these tests always set an explicit secret, so they hold before and
// after that fix.
//
// History:
// 20260723 CL/LH SD-602: created.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/restapi/core/JWT.php';

final class JwtTest extends TestCase
{
    protected function setUp(): void
    {
        JWT::setSecret('unit-test-secret-a');
    }

    public function test_encode_decode_round_trip_preserves_payload(): void
    {
        $token = JWT::encode(['user_id' => 7, 'username' => 'x', 'type' => 'access', 'tenant_id' => 2], 3600);

        $this->assertIsString($token);
        $this->assertCount(3, explode('.', $token), 'JWT has header.payload.signature');

        $payload = JWT::decode($token);
        $this->assertIsArray($payload);
        $this->assertSame(7, $payload['user_id']);
        $this->assertSame('access', $payload['type']);
        $this->assertSame(2, $payload['tenant_id']);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertGreaterThan(time(), $payload['exp']);
    }

    public function test_token_with_tampered_payload_is_rejected(): void
    {
        $token = JWT::encode(['user_id' => 7, 'type' => 'access', 'tenant_id' => 2], 3600);
        [$h, $p, $s] = explode('.', $token);

        $forged = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        $forged['tenant_id'] = 999; // try to hop tenants
        $forgedP = rtrim(strtr(base64_encode(json_encode($forged)), '+/', '-_'), '=');

        $this->assertNull(JWT::decode("$h.$forgedP.$s"), 'signature must not validate a tampered payload');
    }

    public function test_token_signed_with_wrong_secret_is_rejected(): void
    {
        JWT::setSecret('unit-test-secret-b');
        $foreign = JWT::encode(['user_id' => 1, 'type' => 'access'], 3600);

        JWT::setSecret('unit-test-secret-a');
        $this->assertNull(JWT::decode($foreign), 'token from another secret must not decode');
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = JWT::encode(['user_id' => 1, 'type' => 'access'], -10);
        $this->assertNull(JWT::decode($token), 'expired token must not decode');
    }

    public function test_malformed_tokens_are_rejected(): void
    {
        $this->assertNull(JWT::decode(''));
        $this->assertNull(JWT::decode('not-a-jwt'));
        $this->assertNull(JWT::decode('a.b'));
        $this->assertNull(JWT::decode('a.b.c.d'));
    }
}
