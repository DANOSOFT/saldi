<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileOcrTokenEndpointTest.php --- ver 5.0.0 --- 2026.10.01
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

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class MobileOcrTokenEndpointTest extends TestCase
{
    private const V1 = '/restapi/endpoints/v1/auth/';
    private static string $username;
    private static ?array $login = null;

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$username = 'capture_ocr_' . bin2hex(random_bytes(4));
        self::$login = null;
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        RestApiEnv::rows($conn, 'UPDATE brugere SET rettigheder=$1, brugernavn=$2 WHERE brugernavn=$3', [str_repeat('0', 16), self::$username, RestApiEnv::user()]);
        pg_close($conn);
    }

    public static function tearDownAfterClass(): void
    {
        RestApiEnv::teardownTenant();
    }

    /** Each suite owns a distinct principal, so other suites cannot exhaust its login bucket.
     * @return list<string>
     */
    private function authHeaders(): array
    {
        if (self::$login === null) {
            $res = RestApiEnv::login(self::$username, RestApiEnv::password(), RestApiEnv::accountOpen());
            $this->assertSame(200, $res['status'], $res['body']);
            self::$login = $res['json']['data'];
        }
        return ['Authorization: Bearer ' . self::$login['access_token']];
    }

    public function test_receipt_only_user_mints_token_verifiable_with_anonymous_key_endpoint(): void
    {
        $jwtPath = dirname(__DIR__, 2) . '/restapi/.ht_jwt_secret.bin';
        $jwtHash = hash_file('sha256', $jwtPath);
        $before = time();
        $result = RestApiEnv::http('POST', self::V1 . 'ocr-token.php', [], $this->authHeaders());
        // JSON [] is not an object: the explicitly supported request is {} or an empty body.
        $this->assertSame(422, $result['status']);
        $result = RestApiEnv::http('POST', self::V1 . 'ocr-token.php', null, $this->authHeaders());
        $this->assertSame(200, $result['status'], $result['body']);
        $this->assertTrue($result['json']['success']);
        $data = $result['json']['data'];
        $this->assertSame(['token', 'expires_at', 'audience'], array_keys($data));
        $this->assertGreaterThanOrEqual($before + 600, $data['expires_at']);
        $this->assertLessThanOrEqual(time() + 600, $data['expires_at']);
        $this->assertSame('ai.saldi.dk', $data['audience']);
        $parts = explode('.', $data['token']);
        $header = json_decode(sodium_base642bin($parts[0], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), true);
        $claims = json_decode(sodium_base642bin($parts[1], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), true);
        $keys = RestApiEnv::http('GET', self::V1 . 'ocr-keys.php');
        $this->assertSame(200, $keys['status'], $keys['body']);
        $this->assertSame($keys['json']['data']['issuer'], $claims['iss']);
        $this->assertSame(RestApiEnv::regnskabId(RestApiEnv::accountOpen()), $claims['tenant_id']);
        $this->assertStringStartsWith($claims['iss'] . '/account/', $claims['sub']);
        $this->assertSame($data['expires_at'], $claims['exp']);
        $this->assertSame(600, $claims['exp'] - $claims['iat']);
        $this->assertCount(2, $keys['json']['data']['keys']);
        $matching = array_values(array_filter($keys['json']['data']['keys'], fn(array $key): bool => $key['kid'] === $header['kid']));
        $this->assertCount(1, $matching);
        $this->assertTrue(sodium_crypto_sign_verify_detached(
            sodium_base642bin($parts[2], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
            $parts[0] . '.' . $parts[1],
            sodium_base642bin($matching[0]['x'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)
        ));
        foreach ($keys['json']['data']['keys'] as $key) {
            $this->assertSame(['kty', 'crv', 'use', 'alg', 'kid', 'x'], array_keys($key));
        }
        $this->assertSame($jwtHash, hash_file('sha256', $jwtPath), 'OCR never changes the REST signing secret');
    }

    public function test_unauthenticated_and_unbound_tokens_fail_with_codes(): void
    {
        $res = RestApiEnv::http('POST', self::V1 . 'ocr-token.php');
        $this->assertSame(401, $res['status']);
        $this->assertSame('invalid_token', $res['json']['data']['error']);
        if (RestApiEnv::installSecretUnavailableReason() !== null) {
            $this->markTestSkipped(RestApiEnv::installSecretUnavailableReason());
        }
        $this->authHeaders();
        $claims = ['type' => 'access', 'user_id' => (int)self::$login['user']['id']];
        $res = RestApiEnv::http('POST', self::V1 . 'ocr-token.php', null, [
            'Authorization: Bearer ' . RestApiEnv::signToken($claims),
            'X-Tenant-ID: ' . RestApiEnv::regnskabId(RestApiEnv::accountOpen()),
        ]);
        $this->assertSame(401, $res['status']);
        $this->assertSame('session_revoked', $res['json']['data']['error']);
    }

    public function test_client_cannot_choose_claims_and_wrong_methods_are_405(): void
    {
        $res = RestApiEnv::http('POST', self::V1 . 'ocr-token.php', ['tenant_id' => 999], $this->authHeaders());
        $this->assertSame(422, $res['status']);
        $this->assertSame('validation_failed', $res['json']['data']['error']);
        foreach (['ocr-token.php' => 'GET', 'ocr-keys.php' => 'POST'] as $path => $method) {
            $res = RestApiEnv::http($method, self::V1 . $path, null, $this->authHeaders());
            $this->assertSame(405, $res['status']);
            $this->assertFalse($res['json']['success']);
        }
    }
}
