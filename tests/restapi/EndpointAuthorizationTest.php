<?php
// tests/restapi/EndpointAuthorizationTest.php
//
// One test per endpoint, asserting that it cannot be reached without a valid
// bearer token.
//
// This is deliberately a sweep rather than a sample. The API grew endpoint by
// endpoint, each one a self-contained index.php that extends BaseEndpoint and
// relies on the base class to enforce auth; an endpoint that forgets to call
// up the chain, or that answers before the check runs, would expose a whole
// tenant's data and nothing else in the suite would notice. The sweep is the
// thing that notices - and a new endpoint added without a row here is an
// omission a reviewer can see.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

require_once __DIR__ . '/support/RestApiTestCase.php';

final class EndpointAuthorizationTest extends RestApiTestCase
{
    /**
     * Every endpoint under restapi/endpoints/v1 that serves tenant data.
     *
     * Excluded on purpose:
     *  - auth/login.php and auth/refresh.php issue the tokens and must be
     *    reachable without one;
     *  - inventory/ is a discovery document, not tenant data (covered by
     *    test_the_inventory_index_is_a_discovery_document_only);
     *  - dashboard/stats.php and vat-codes/ cannot execute at all (covered by
     *    test_endpoint_is_dead_for_every_caller).
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function endpoints(): array
    {
        $get = [
            'accounting year' => '/restapi/endpoints/v1/accountingYear/',
            'accounts' => '/restapi/endpoints/v1/accounts/',
            'attachment' => '/restapi/endpoints/v1/attachment/',
            'creditors' => '/restapi/endpoints/v1/creditor/creditors/',
            'creditor order lines' => '/restapi/endpoints/v1/creditor/orderlines/',
            'creditor orders' => '/restapi/endpoints/v1/creditor/orders/',
            'currencies' => '/restapi/endpoints/v1/currencies/',
            'debtor customers' => '/restapi/endpoints/v1/debitor/customers/',
            'debtor groups' => '/restapi/endpoints/v1/debitor/groups/',
            'debtor invoices' => '/restapi/endpoints/v1/debitor/invoices/',
            'debtor order lines' => '/restapi/endpoints/v1/debitor/orderlines/',
            'debtor orders' => '/restapi/endpoints/v1/debitor/orders/',
            'inventory status' => '/restapi/endpoints/v1/inventory/status/',
            'inventory warehouses' => '/restapi/endpoints/v1/inventory/warehouses/',
            'labels' => '/restapi/endpoints/v1/labels/',
            'products' => '/restapi/endpoints/v1/products/',
            'product groups' => '/restapi/endpoints/v1/products/groups/',
            'user tenants' => '/restapi/endpoints/v1/user/tenants.php',
            'vat' => '/restapi/endpoints/v1/vat/',
        ];

        $cases = [];
        foreach ($get as $name => $path) {
            $cases[$name] = [$name, $path];
        }
        return $cases;
    }

    /**
     * DEFECT PINNED: two endpoints cannot execute at all.
     *
     * `dashboard/stats.php:16` and `vat-codes/index.php:15` each declare
     *
     *     private $db;
     *
     * while `restapi/core/BaseEndpoint.php` declares the same property
     * `protected`. PHP refuses to compile a subclass that narrows an
     * inherited property's visibility:
     *
     *     PHP Fatal error: Access level to DashboardStatsEndpoint::$db must
     *     be protected (as in class BaseEndpoint) or weaker
     *
     * The fatal happens at class-declaration time, before any routing or auth
     * runs, so both endpoints answer 500 with an empty body to every caller -
     * authenticated or not. They have never worked.
     *
     * These are not authorization failures, which is why they are pinned here
     * separately rather than being listed as sweep rows. When the property
     * declarations are fixed, this test fails and the two paths should move
     * into endpoints() above.
     *
     * @return array<string, array{0:string}>
     */
    public static function deadEndpoints(): array
    {
        return [
            'dashboard stats' => ['/restapi/endpoints/v1/dashboard/stats.php'],
            'vat codes' => ['/restapi/endpoints/v1/vat-codes/'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deadEndpoints')]
    public function test_endpoint_is_dead_for_every_caller(string $path): void
    {
        $anon = self::anon('GET', $path);
        $authed = self::api('GET', $path);

        $this->assertSame(500, $anon['status'], 'anonymous call: ' . substr($anon['body'], 0, 200));
        $this->assertSame(500, $authed['status'], 'authenticated call: ' . substr($authed['body'], 0, 200));
        $this->assertSame('', trim($authed['body']), 'the fatal leaves an empty body, not an error envelope');
    }

    /**
     * inventory/ answers anonymously by design - it is a static description
     * of the inventory sub-API. What matters is that it is ONLY that: no
     * tenant data may appear in it.
     */
    public function test_the_inventory_index_is_a_discovery_document_only(): void
    {
        $res = self::anon('GET', '/restapi/endpoints/v1/inventory/');

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertIsArray($res['json']);
        $this->assertSame(
            ['success', 'message', 'version', 'endpoints', 'documentation'],
            array_keys($res['json']),
            'the anonymous index must carry nothing but the endpoint catalogue'
        );
        $this->assertIsArray($res['json']['endpoints']);
        foreach ($res['json']['endpoints'] as $entry) {
            $this->assertSame(
                ['path', 'description', 'methods'],
                array_keys($entry),
                'catalogue entries describe routes, they do not carry data'
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('endpoints')]
    public function test_endpoint_rejects_an_anonymous_request(string $name, string $path): void
    {
        $res = self::anon('GET', $path);

        $this->assertSame(
            401,
            $res['status'],
            "$name answered an anonymous GET with {$res['status']}: " . substr($res['body'], 0, 300)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('endpoints')]
    public function test_endpoint_rejects_a_forged_token(string $name, string $path): void
    {
        // A structurally valid JWT signed with the wrong key.
        $header = rtrim(strtr(base64_encode('{"typ":"JWT","alg":"HS256"}'), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode([
            'sub' => RestApiEnv::USER,
            'exp' => time() + 3600,
            'db' => RestApiEnv::testDb(),
        ])), '+/', '-_'), '=');
        $forged = "$header.$payload." . rtrim(strtr(base64_encode('not-the-real-signature'), '+/', '-_'), '=');

        $res = RestApiEnv::http('GET', $path, null, ['Authorization: Bearer ' . $forged]);

        $this->assertSame(
            401,
            $res['status'],
            "$name accepted a forged token: " . substr($res['body'], 0, 300)
        );
    }

    /**
     * The token-issuing endpoints must NOT require a token - if they did,
     * nothing could ever authenticate.
     */
    public function test_the_login_endpoint_is_reachable_without_a_token(): void
    {
        $res = self::anon('POST', '/restapi/endpoints/v1/auth/login.php', [
            'username' => 'no-such-user',
            'password' => 'no-such-password',
            'account_name' => RestApiEnv::ACCOUNT_OPEN,
        ]);

        $this->assertNotSame(0, $res['status'], 'login endpoint is not reachable at all');
        $this->assertContains(
            $res['status'],
            [400, 401, 403, 404, 422],
            'bad credentials should be refused, not accepted: ' . substr($res['body'], 0, 300)
        );
    }

    /** Bad credentials must not mint a token. */
    public function test_login_with_a_wrong_password_issues_no_token(): void
    {
        $res = RestApiEnv::login(RestApiEnv::USER, 'definitely-not-the-password', RestApiEnv::ACCOUNT_OPEN);

        $this->assertArrayNotHasKey(
            'access_token',
            (array)($res['json']['data'] ?? []),
            'a wrong password must not produce an access token'
        );
    }

    /** A closed accounting entity must not hand out a working session. */
    public function test_login_against_a_closed_tenant_is_refused(): void
    {
        $res = RestApiEnv::login(RestApiEnv::USER, RestApiEnv::PASSWORD, RestApiEnv::ACCOUNT_CLOSED);

        $this->assertArrayNotHasKey(
            'access_token',
            (array)($res['json']['data'] ?? []),
            'a closed tenant must not issue an access token: ' . substr($res['body'], 0, 300)
        );
    }
}
