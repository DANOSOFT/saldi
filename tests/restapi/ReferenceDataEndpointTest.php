<?php
// tests/restapi/ReferenceDataEndpointTest.php
//
// Read-path tests for the endpoints that serve a tenant's reference data:
// chart of accounts, VAT groups, currencies, debtor and product groups,
// warehouses, the accounting year, and the tenant list.
//
// These endpoints have no writes to speak of, so what they need pinning on is
// that they answer at all, answer in the standard envelope, and answer with
// the tenant's real configuration rather than an empty list - an endpoint
// that silently returns [] looks healthy to a smoke test and breaks every
// dropdown in a client.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.
// 20261022 CL/NTR Product groups joined listEndpoints() (fixed in MB-67; the "always empty" pin is gone).
//                 The tenant-list test follows the registry user the endpoint actually resolves instead of assuming none.

require_once __DIR__ . '/support/RestApiTestCase.php';

final class ReferenceDataEndpointTest extends RestApiTestCase
{
    /**
     * Endpoints that must answer with a non-empty list for a freshly
     * installed tenant, together with the table their content comes from.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function listEndpoints(): array
    {
        return [
            'accounts' => ['/restapi/endpoints/v1/accounts/', 'chart of accounts'],
            'vat' => ['/restapi/endpoints/v1/vat/', 'VAT groups'],
            'debtor groups' => ['/restapi/endpoints/v1/debitor/groups/', 'debtor groups'],
            'product groups' => ['/restapi/endpoints/v1/products/groups/', 'product groups'],
        ];
    }

    /**
     * Currencies are legitimately empty on a stock installation: a Danish
     * tenant books in DKK and only gains `grupper` art='VK' rows once someone
     * adds a foreign currency. Only the shape is pinned.
     */
    public function test_the_currencies_endpoint_answers_with_a_list(): void
    {
        $res = self::api('GET', '/restapi/endpoints/v1/currencies/');
        $items = $this->assertSuccessEnvelope($res, 'currencies');

        $this->assertIsArray($items, 'currencies is a list even when none are configured');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('listEndpoints')]
    public function test_reference_list_answers_with_the_tenant_configuration(string $path, string $what): void
    {
        $res = self::api('GET', $path);
        $items = $this->assertSuccessEnvelope($res, $what);

        $this->assertIsArray($items, "$what should be a list: " . substr($res['body'], 0, 300));
        $this->assertNotEmpty(
            $items,
            "$what came back empty - a freshly installed tenant has these seeded from importfiler/"
        );
    }

    /** The chart of accounts really is the tenant's, not a stub. */
    public function test_the_accounts_list_matches_the_tenants_chart(): void
    {
        $res = self::api('GET', '/restapi/endpoints/v1/accounts/?limit=50');
        $items = $this->assertSuccessEnvelope($res, 'accounts');

        $this->assertNotEmpty($items);
        $accountNumbers = array_filter(array_map(
            static fn($a) => $a['accountNumber'] ?? $a['kontonr'] ?? null,
            $items
        ));
        $this->assertNotEmpty($accountNumbers, 'accounts carry an account number: ' . substr($res['body'], 0, 300));

        $known = self::tenantRows(
            'SELECT kontonr FROM kontoplan WHERE kontonr = ANY($1)',
            ['{' . implode(',', array_map('intval', $accountNumbers)) . '}']
        );
        $this->assertNotEmpty($known, 'the returned account numbers exist in this tenant kontoplan');
    }

    /** VAT groups carry the rate the posting engine uses. */
    public function test_the_vat_list_carries_a_usable_rate(): void
    {
        $res = self::api('GET', '/restapi/endpoints/v1/vat/');
        $items = $this->assertSuccessEnvelope($res, 'vat');

        $this->assertNotEmpty($items);
        $rates = array_filter(array_map(
            static fn($v) => $v['rate'] ?? $v['percentage'] ?? $v['box2'] ?? null,
            $items
        ), static fn($r) => is_numeric($r) && (float)$r > 0);

        $this->assertNotEmpty(
            $rates,
            'at least one VAT group exposes a positive rate: ' . substr($res['body'], 0, 400)
        );
    }

    public function test_the_accounting_year_endpoint_describes_the_current_year(): void
    {
        $res = self::api('GET', '/restapi/endpoints/v1/accountingYear/');
        $data = $this->assertSuccessEnvelope($res, 'accounting year');

        $this->assertNotEmpty($data, 'the accounting year is described: ' . substr($res['body'], 0, 300));
    }

    /**
     * user/tenants.php is knowingly unsupported: its own header says so.
     *
     *   "UNSUPPORTED with account-scoped JWT authentication. The JWT user_id
     *    belongs to an account database and must not be used to query a
     *    registry-database user with the same numeric ID."
     *
     * It looks the token's user_id up in the MASTER `brugere` table, so the
     * answer is about whichever registry user happens to share that numeric
     * id - 404 "User not found" when there is none, otherwise that registry
     * user's tenant list. Pinned against the registry itself, so the test
     * holds on any install, and so that whoever implements the identity
     * mapping notices this endpoint.
     */
    public function test_the_tenant_list_resolves_the_token_user_id_in_the_registry(): void
    {
        $userId = (int)RestApiEnv::loginData()['user']['id'];
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        $registryUser = RestApiEnv::rows($master, 'SELECT id FROM brugere WHERE id = $1', [$userId]);
        pg_close($master);

        $res = self::api('GET', '/restapi/endpoints/v1/user/tenants.php');

        if ($registryUser === []) {
            $this->assertSame(404, $res['status'], $res['body']);
            $this->assertSame('User not found', $res['json']['message'] ?? null);
        } else {
            $this->assertSame(200, $res['status'], 'answered for registry user ' . $userId . ': ' . $res['body']);
            $this->assertIsArray($res['json']['data'] ?? null, $res['body']);
        }
    }

    public function test_the_warehouses_endpoint_answers_with_a_list(): void
    {
        $res = self::api('GET', '/restapi/endpoints/v1/inventory/warehouses/');
        $items = $this->assertSuccessEnvelope($res, 'warehouses');

        $this->assertIsArray($items, 'warehouses is a list even when none are configured');
    }

    public function test_the_inventory_status_endpoint_answers_with_a_list(): void
    {
        $res = self::api('GET', '/restapi/endpoints/v1/inventory/status/');
        $items = $this->assertSuccessEnvelope($res, 'inventory status');

        $this->assertIsArray($items);
    }

    /**
     * `limit` on accounts and currencies is passed to the model without an
     * int cast (accounts/index.php:54, currencies/index.php:30), the same
     * shape as the products defect - see ProductsEndpointTest. Pinned here so
     * the fix covers all three call sites, not just products.
     */
    public function test_the_accounts_limit_parameter_accepts_a_sql_fragment(): void
    {
        $baseline = $this->assertSuccessEnvelope(
            self::api('GET', '/restapi/endpoints/v1/accounts/?limit=5'),
            'baseline'
        );
        $this->assertGreaterThan(4, count($baseline), 'precondition: the chart has at least five accounts');

        $res = self::api('GET', '/restapi/endpoints/v1/accounts/?limit=' . rawurlencode('1 OFFSET 3'));
        $items = $this->assertSuccessEnvelope($res, 'limit with a fragment');

        $this->assertCount(1, $items, 'the fragment reached the SQL');
        $this->assertSame(
            $baseline[3]['id'] ?? null,
            $items[0]['id'] ?? null,
            'and OFFSET was executed - the fourth account came back'
        );
    }
}
