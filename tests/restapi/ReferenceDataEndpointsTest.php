<?php
// tests/restapi/ReferenceDataEndpointsTest.php
//
// Integration tests for the read-mostly reference endpoints under
// /restapi/endpoints/v1/ (currencies, vat, vat-codes, accountingYear,
// debitor/groups, products/groups, inventory/warehouses, dashboard/stats.php)
// against the local docker stack's self-provisioned tenant: every one of them
// demands a bearer token, answers with the JSON envelope and the documented
// shape, and the read-only ones refuse writes with 405.
//
// History:
// 20260904 CL/NTR created.
// 20261008 CL/LH products/groups list for the current fiscal year, codeNo lookup, non-group ids are 404.
// 20261008 CL/LH inventory/warehouses lists the real warehouses with their names, numbers and stock.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class ReferenceDataEndpointsTest extends TestCase
{
    private const V1 = '/restapi/endpoints/v1/';

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

    private function get(string $path): array
    {
        return RestApiEnv::http('GET', self::V1 . $path, null, RestApiEnv::authHeaders());
    }

    /** @return array<string, array{string}> */
    public static function protectedPaths(): array
    {
        return [
            'currencies' => ['currencies/'],
            'vat' => ['vat/'],
            'vat-codes' => ['vat-codes/'],
            'accountingYear' => ['accountingYear/'],
            'debitor/groups' => ['debitor/groups/'],
            'products/groups' => ['products/groups/'],
            'dashboard/stats' => ['dashboard/stats.php'],
        ];
    }

    #[DataProvider('protectedPaths')]
    public function test_reference_endpoints_require_a_bearer_token(string $path): void
    {
        $res = RestApiEnv::http('GET', self::V1 . $path);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertIsArray($res['json'], 'refusal is a JSON envelope');
        $this->assertFalse($res['json']['success']);
    }

    /** @return array<string, array{string}> */
    public static function readOnlyPaths(): array
    {
        return [
            'currencies' => ['currencies/'],
            'vat' => ['vat/'],
            'vat-codes' => ['vat-codes/'],
            'accountingYear' => ['accountingYear/'],
            'debitor/groups' => ['debitor/groups/'],
            'dashboard/stats' => ['dashboard/stats.php'],
        ];
    }

    #[DataProvider('readOnlyPaths')]
    public function test_read_only_endpoints_refuse_post_405(string $path): void
    {
        $res = RestApiEnv::http('POST', self::V1 . $path, ['anything' => 1], RestApiEnv::authHeaders());

        $this->assertSame(405, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_currency_list_is_an_array_and_unknown_code_is_404(): void
    {
        $list = $this->get('currencies/');
        $this->assertSame(200, $list['status'], $list['body']);
        $this->assertTrue($list['json']['success']);
        $this->assertIsArray($list['json']['data']);
        foreach ($list['json']['data'] as $currency) {
            $this->assertArrayHasKey('currencyCode', $currency);
            $this->assertArrayHasKey('exchangeRate', $currency);
        }

        $missing = $this->get('currencies/?currencyCode=ZZZ');
        $this->assertSame(404, $missing['status'], $missing['body']);
        $this->assertFalse($missing['json']['success']);
    }

    public function test_vat_list_exposes_english_keys_and_single_item_lookup(): void
    {
        $list = $this->get('vat/');
        $this->assertSame(200, $list['status'], $list['body']);
        $this->assertNotEmpty($list['json']['data'], 'template tenant has VAT codes for the current fiscal year');
        $first = $list['json']['data'][0];
        foreach (['id', 'description', 'vatCode', 'number', 'fiscalYear', 'account', 'rate'] as $key) {
            $this->assertArrayHasKey($key, $first);
        }

        $single = $this->get('vat/?id=' . (int)$first['id']);
        $this->assertSame(200, $single['status'], $single['body']);
        $this->assertSame($first['vatCode'], $single['json']['data']['vatCode']);
        $this->assertSame($first['description'], $single['json']['data']['description']);

        $byCode = $this->get('vat/?field=vatCode&value=' . urlencode($first['vatCode']));
        $this->assertSame(200, $byCode['status'], $byCode['body']);
        $this->assertNotEmpty($byCode['json']['data']);
        foreach ($byCode['json']['data'] as $vat) {
            $this->assertSame($first['vatCode'], $vat['vatCode']);
        }

        $missing = $this->get('vat/?id=99999999');
        $this->assertSame(404, $missing['status'], $missing['body']);
    }

    public function test_vat_codes_answers_with_json_list_of_code_rate_description(): void
    {
        $res = $this->get('vat-codes/');

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertIsArray($res['json'], 'response is JSON, not a PHP error page');
        $this->assertTrue($res['json']['success']);
        $this->assertIsArray($res['json']['data']);
        foreach ($res['json']['data'] as $code) {
            $this->assertIsInt($code['code']);
            $this->assertIsFloat($code['rate']);
            $this->assertArrayHasKey('description', $code);
        }
    }

    public function test_accounting_year_reports_the_fiscal_year_covering_today(): void
    {
        $res = $this->get('accountingYear/');

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertTrue($res['json']['success']);
        $data = $res['json']['data'];
        $this->assertSame(date('Y-m-d'), $data['current_date']);
        $this->assertSame(date('m'), $data['current_month']);
        $this->assertSame(date('Y'), $data['current_year']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', (string)$data['fiscal_year']);
        $this->assertMatchesRegularExpression('/^\d{1,2}$/', (string)$data['fiscal_year_start']);
        $this->assertMatchesRegularExpression('/^\d{1,2}$/', (string)$data['fiscal_year_end']);

        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $rows = RestApiEnv::rows(
            $tenant,
            "SELECT box2 FROM grupper WHERE art = 'RA' AND box1 = $1 AND box2 = $2 AND box3 = $3",
            [$data['fiscal_year_start'], $data['fiscal_year'], $data['fiscal_year_end']]
        );
        pg_close($tenant);
        $this->assertCount(1, $rows, 'reported fiscal year matches a grupper RA row');
    }

    public function test_debtor_groups_list_current_year_groups(): void
    {
        $res = $this->get('debitor/groups/');

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertTrue($res['json']['success']);
        $this->assertIsArray($res['json']['data']);
        foreach ($res['json']['data'] as $group) {
            $this->assertIsInt($group['number']);
            $this->assertArrayHasKey('description', $group);
            $this->assertIsBool($group['b2b']);
            $this->assertIsInt($group['fiscalYear']);
        }
    }

    public function test_product_group_single_lookup_matches_the_database_row(): void
    {
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $rows = RestApiEnv::rows($tenant, "SELECT id, kodenr, beskrivelse FROM grupper WHERE art = 'VG' ORDER BY id LIMIT 1");
        pg_close($tenant);
        $this->assertNotEmpty($rows, 'template tenant has at least one product group');

        $res = $this->get('products/groups/?id=' . (int)$rows[0]['id']);

        $this->assertSame(200, $res['status'], $res['body']);
        $group = $res['json']['data'];
        $this->assertSame((int)$rows[0]['id'], (int)$group['id']);
        $this->assertSame((int)$rows[0]['kodenr'], (int)$group['codeNo']);
        $this->assertSame($rows[0]['beskrivelse'], $group['description']);
        $this->assertArrayHasKey('accounts', $group);

        $missing = $this->get('products/groups/?id=99999999');
        $this->assertSame(404, $missing['status'], $missing['body']);

        $del = RestApiEnv::http('DELETE', self::V1 . 'products/groups/?id=' . (int)$rows[0]['id'], null, RestApiEnv::authHeaders());
        $this->assertSame(405, $del['status'], $del['body']);
    }

    /** kodenr of the tenant's fiscal year (grupper art RA) covering today. */
    private function currentFiscalYear($tenant): int
    {
        $year = null;
        $now = (int)date('Y') * 12 + (int)date('n');
        foreach (RestApiEnv::rows($tenant, "SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA' AND (box10 IS NULL OR box10 <> 'on')") as $ra) {
            if ((int)$ra['box2'] * 12 + (int)$ra['box1'] <= $now && $now <= (int)$ra['box4'] * 12 + (int)$ra['box3']) {
                $year = (int)$ra['kodenr'];
            }
        }
        $this->assertNotNull($year, 'template tenant has a fiscal year covering today');
        return $year;
    }

    public function test_product_group_list_holds_the_current_fiscal_years_groups(): void
    {
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $year = $this->currentFiscalYear($tenant);
        $expected = array_map('intval', array_column(RestApiEnv::rows($tenant, "SELECT id FROM grupper WHERE art = 'VG' AND fiscal_year = $1 ORDER BY id", [$year]), 'id'));
        $other = RestApiEnv::rows($tenant, "SELECT id FROM grupper WHERE art <> 'VG' ORDER BY id LIMIT 1");
        pg_close($tenant);
        $this->assertNotEmpty($expected, 'template tenant has product groups in the current fiscal year');

        $res = $this->get('products/groups/');

        $this->assertSame(200, $res['status'], $res['body']);
        $ids = array_map(fn($g) => (int)$g['id'], $res['json']['data']);
        sort($ids);
        $this->assertSame($expected, $ids);
        foreach ($res['json']['data'] as $group) {
            $this->assertSame($year, (int)$group['fiscalYear']);
        }

        $first = $res['json']['data'][0];
        foreach (['codeNo', 'kodenr'] as $field) {
            $byCode = $this->get("products/groups/?field=$field&value=" . (int)$first['codeNo']);
            $this->assertSame(200, $byCode['status'], $byCode['body']);
            $this->assertSame([(int)$first['id']], array_map(fn($g) => (int)$g['id'], $byCode['json']['data']), "field=$field");
        }

        $this->assertNotEmpty($other);
        $notAGroup = $this->get('products/groups/?id=' . (int)$other[0]['id']);
        $this->assertSame(404, $notAGroup['status'], 'a grupper row that is not a product group is not served as one');
    }

    public function test_warehouse_list_holds_the_current_fiscal_years_warehouses(): void
    {
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $year = $this->currentFiscalYear($tenant);
        $seeded = [];
        foreach ([91 => ['Holbæk test', 3], 92 => ['Hillerød test', 7]] as $kodenr => [$name, $qty]) {
            $id = (int)RestApiEnv::rows($tenant, "INSERT INTO grupper (art, kodenr, beskrivelse, fiscal_year) VALUES ('LG', $1, $2, $3) RETURNING id", [$kodenr, $name, $year])[0]['id'];
            $seeded[$id] = ['number' => $kodenr, 'description' => $name, 'qty' => $qty];
        }
        RestApiEnv::rows($tenant, "INSERT INTO grupper (art, kodenr, beskrivelse, fiscal_year) VALUES ('LG', 93, 'other year', $1)", [$year + 100]);
        $product = (int)RestApiEnv::rows($tenant, 'SELECT id FROM varer ORDER BY id LIMIT 1')[0]['id'];
        foreach ($seeded as $w) {
            RestApiEnv::rows($tenant, 'INSERT INTO lagerstatus (lager, vare_id, variant_id, beholdning) VALUES ($1, $2, 0, $3)', [$w['number'], $product, $w['qty']]);
        }
        $expected = array_map('intval', array_column(RestApiEnv::rows($tenant, "SELECT id FROM grupper WHERE art = 'LG' AND fiscal_year = $1 ORDER BY id", [$year]), 'id'));
        pg_close($tenant);

        $res = $this->get('inventory/warehouses/');
        $this->assertSame(200, $res['status'], $res['body']);
        $ids = array_map(fn($w) => (int)$w['id'], $res['json']['data']);
        sort($ids);
        $this->assertSame($expected, $ids, 'the real warehouses, not the id -1 placeholder');

        $withStock = $this->get("inventory/warehouses/?productId=$product");
        $this->assertSame(200, $withStock['status'], $withStock['body']);
        $byId = array_column($withStock['json']['data'], null, 'id');
        foreach ($seeded as $id => $w) {
            $this->assertSame($w['description'], $byId[$id]['description']);
            $this->assertSame($w['number'], (int)$byId[$id]['number']);
            $this->assertSame($year, (int)$byId[$id]['fiscal_year']);
            $this->assertSame($w['number'], (int)$byId[$id]['inventory']['inventory'], 'stock of this warehouse, not of warehouse 1');
            $this->assertEquals($w['qty'], $byId[$id]['inventory']['quantity']);
        }

        $one = $this->get('inventory/warehouses/?id=' . array_key_first($seeded));
        $this->assertSame(200, $one['status'], $one['body']);
        $this->assertSame('Holbæk test', $one['json']['data']['description']);
    }

    public function test_dashboard_stats_answers_with_numeric_totals(): void
    {
        $res = $this->get('dashboard/stats.php');

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertIsArray($res['json'], 'response is JSON, not a PHP error page');
        $this->assertTrue($res['json']['success']);
        $stats = $res['json']['data'];
        $this->assertIsNumeric($stats['revenue_ytd']);
        $this->assertIsInt($stats['overdue_count']);
        $this->assertIsNumeric($stats['overdue_amount']);
        $this->assertGreaterThanOrEqual(0, $stats['overdue_count']);
    }
}
