<?php
// tests/restapi/support/RestApiTestCase.php
//
// Base class for the REST API integration suites.
//
// Carries the skip-when-the-stack-is-down check, the tenant bootstrap and the
// authenticated request helpers, so each suite contains only the endpoint
// behavior it covers.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/RestApiEnv.php';

abstract class RestApiTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenantOnce();
    }

    /** @return list<string> */
    protected static function authHeaders(): array
    {
        return ['Authorization: Bearer ' . RestApiEnv::accessToken()];
    }

    /** An authenticated request. @return array{status:int, json:?array, body:string} */
    protected static function api(string $method, string $path, ?array $body = null): array
    {
        return RestApiEnv::http($method, $path, $body, self::authHeaders());
    }

    /** An unauthenticated request. @return array{status:int, json:?array, body:string} */
    protected static function anon(string $method, string $path, ?array $body = null): array
    {
        return RestApiEnv::http($method, $path, $body);
    }

    /**
     * Assert the standard success envelope and return the payload.
     *
     * Every endpoint answers through BaseEndpoint::sendResponse, which wraps
     * the payload as {success, message, data}.
     */
    protected function assertSuccessEnvelope(array $res, string $context = ''): mixed
    {
        // 200 for reads, 201 for creates.
        $this->assertGreaterThanOrEqual(200, $res['status'], $context . ' expected 2xx: ' . $res['body']);
        $this->assertLessThan(300, $res['status'], $context . ' expected 2xx: ' . $res['body']);
        $this->assertIsArray($res['json'], $context . ' expected a JSON body: ' . $res['body']);
        $this->assertArrayHasKey('success', $res['json'], $context . ' envelope has no success flag');
        $this->assertTrue($res['json']['success'], $context . ' request failed: ' . $res['body']);

        return $res['json']['data'] ?? null;
    }

    /** A direct connection to the API test tenant, for verifying writes. */
    protected static function tenant()
    {
        static $conn = null;
        if ($conn === null) {
            $conn = RestApiEnv::connect(RestApiEnv::testDb());
        }
        return $conn;
    }

    /** @param array<int,mixed> $params */
    protected static function tenantRows(string $sql, array $params = []): array
    {
        return RestApiEnv::rows(self::tenant(), $sql, $params);
    }
}
