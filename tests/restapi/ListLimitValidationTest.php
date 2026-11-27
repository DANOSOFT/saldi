<?php
// tests/restapi/ListLimitValidationTest.php
//
// MB-98: the accounts and currencies list endpoints must validate limit,
// orderBy and orderDirection before they reach SQL. A limit that smuggles in
// SQL ("1 OFFSET 1") and an unknown orderBy/orderDirection must not change
// the result: the list is the same as the default list.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class ListLimitValidationTest extends TestCase
{
    private const V1 = '/restapi/endpoints/v1/';
    private const INJECTED = '?limit=1+OFFSET+1&orderBy=noSuchColumn&orderDirection=sideways';

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant(static::class);
    }

    public static function tearDownAfterClass(): void
    {
        RestApiEnv::teardownTenant();
    }

    private function get(string $path): array
    {
        $res = RestApiEnv::http('GET', self::V1 . $path, null, RestApiEnv::authHeaders());
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertTrue($res['json']['success'], $res['body']);
        $this->assertIsArray($res['json']['data']);
        return $res['json']['data'];
    }

    public function test_account_list_ignores_sql_in_limit_and_unknown_order_fields(): void
    {
        $default = $this->get('accounts/');
        $this->assertGreaterThan(1, count($default), 'template tenant has more than one account');

        $injected = $this->get('accounts/' . self::INJECTED);

        $this->assertSame(
            array_column($default, 'id'),
            array_column($injected, 'id'),
            'limit "1 OFFSET 1" and an unknown orderBy fall back to the default list'
        );

        $ten = $this->get('accounts/?limit=10');
        $this->assertCount(min(10, count($default)), $ten);
    }

    public function test_currency_list_ignores_sql_in_limit_and_unknown_order_fields(): void
    {
        // getAllItems() looks each row up again by box1 = id, so seed rows whose box1 is their own id.
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        try {
            foreach (['991' => 'Apitest valuta A', '992' => 'Apitest valuta B', '993' => 'Apitest valuta C'] as $kodenr => $name) {
                $id = (int)RestApiEnv::rows($tenant, "INSERT INTO grupper (art, kodenr, beskrivelse, box1, box2) VALUES ('VK', $1, $2, 'TMP', '100') RETURNING id", [$kodenr, $name])[0]['id'];
                RestApiEnv::rows($tenant, 'UPDATE grupper SET box1 = $1 WHERE id = $2', [(string)$id, $id]);
            }
        } finally {
            pg_close($tenant);
        }

        $default = $this->get('currencies/');
        $this->assertGreaterThanOrEqual(3, count($default), 'seeded currencies are listed');

        $injected = $this->get('currencies/' . self::INJECTED);

        $this->assertSame($default, $injected, 'limit "1 OFFSET 1" and an unknown orderBy fall back to the default list');
    }
}
