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

    /**
     * DEFECT PINNED: the product-groups list is always empty, whatever the
     * tenant has configured.
     *
     * VareGruppeModel::getAllItems (restapi/models/lager/VareGruppeModel.php:233)
     * filters on a global that the REST request never sets:
     *
     *     global $regnaar;
     *     $qtxt = "SELECT id FROM grupper WHERE art = 'VG'
     *              AND fiscal_year = '$regnaar' ORDER BY ...";
     *
     * `$regnaar` is a page-script global. The neighbouring models learned to
     * stop trusting it - AccountModel:170 and VatModel:55 both call
     * `self::getFiscalYear()` instead, which is why /accounts and /vat return
     * real rows while this one returns none.
     *
     * Item groups decide an item's posting accounts and whether it is
     * stock-tracked, so an API client cannot build a working
     * create-a-product form without them.
     *
     * When getAllItems resolves the fiscal year properly, this test fails;
     * move the endpoint into listEndpoints() above at that point.
     */
    public function test_the_product_groups_list_is_always_empty(): void
    {
        $configured = self::tenantRows("SELECT kodenr FROM grupper WHERE art = 'VG'");
        $this->assertNotEmpty($configured, 'precondition: the tenant does have item groups');

        $res = self::api('GET', '/restapi/endpoints/v1/products/groups/');
        $items = $this->assertSuccessEnvelope($res, 'product groups');

        $this->assertSame([], $items, 'the endpoint reports none of them');
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
     * It authenticates the caller and then fails to resolve them, so it
     * answers 404 "User not found" for a perfectly valid token. Pinned rather
     * than reported: the behavior is deliberate, and the test exists so that
     * whoever implements the identity mapping notices this endpoint.
     */
    public function test_the_tenant_list_is_unsupported_under_jwt_auth(): void
    {
        $res = self::api('GET', '/restapi/endpoints/v1/user/tenants.php');

        $this->assertSame(404, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success'] ?? true);
        $this->assertSame('User not found', $res['json']['message'] ?? null);
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
