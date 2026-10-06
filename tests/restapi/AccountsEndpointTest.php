<?php
// tests/restapi/AccountsEndpointTest.php
//
// Integration tests for /restapi/endpoints/v1/accounts/ (chart of accounts,
// kontoplan) against the local docker stack's self-provisioned tenant:
// auth enforcement, list, create -> read-back (stamped with the current
// fiscal year), validation, update, 404, and the disabled DELETE.
//
// History:
// 20260904 CL/NTR created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class AccountsEndpointTest extends TestCase
{
    private const ACCOUNTS = '/restapi/endpoints/v1/accounts/';

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

    /** An account number no template chart of accounts uses (kontoplan tops out well below 9 000 000). */
    private function freeAccountNumber(): int
    {
        return random_int(9000000, 9999999);
    }

    private function create(array $body): array
    {
        return RestApiEnv::http('POST', self::ACCOUNTS, $body, RestApiEnv::authHeaders());
    }

    private function read(int $id): array
    {
        return RestApiEnv::http('GET', self::ACCOUNTS . '?id=' . $id, null, RestApiEnv::authHeaders());
    }

    public function test_accounts_require_a_bearer_token(): void
    {
        $res = RestApiEnv::http('GET', self::ACCOUNTS);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_account_list_returns_chart_rows_and_honours_limit(): void
    {
        $res = RestApiEnv::http('GET', self::ACCOUNTS . '?limit=3', null, RestApiEnv::authHeaders());

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertTrue($res['json']['success']);
        $this->assertIsArray($res['json']['data']);
        $this->assertNotEmpty($res['json']['data'], 'template tenant has a chart of accounts');
        $this->assertLessThanOrEqual(3, count($res['json']['data']));
        foreach ($res['json']['data'] as $account) {
            $this->assertArrayHasKey('kontonr', $account);
            $this->assertArrayHasKey('beskrivelse', $account);
            $this->assertArrayHasKey('kontotype', $account);
        }
    }

    public function test_created_account_can_be_read_back_in_the_current_fiscal_year(): void
    {
        $kontonr = $this->freeAccountNumber();
        $create = $this->create([
            'accountNumber' => $kontonr,
            'description' => 'Apitest driftskonto',
            'accountType' => 'D',
        ]);

        $this->assertSame(201, $create['status'], $create['body']);
        $this->assertTrue($create['json']['success']);
        $id = (int)($create['json']['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $id, 'created account has an id');

        $read = $this->read($id);

        $this->assertSame(200, $read['status'], $read['body']);
        $a = $read['json']['data'];
        $this->assertSame($kontonr, (int)$a['kontonr']);
        $this->assertSame('Apitest driftskonto', $a['beskrivelse']);
        $this->assertSame('D', $a['kontotype']);

        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $rows = RestApiEnv::rows($tenant, 'SELECT regnskabsaar FROM kontoplan WHERE id = $1', [$id]);
        $latest = RestApiEnv::rows($tenant, "SELECT max(kodenr) AS kodenr FROM grupper WHERE art = 'RA'");
        pg_close($tenant);
        $this->assertCount(1, $rows);
        $this->assertSame((int)$latest[0]['kodenr'], (int)$rows[0]['regnskabsaar'], 'new account belongs to the latest fiscal year');
    }

    public function test_create_without_description_is_rejected_400(): void
    {
        $res = $this->create(['accountNumber' => $this->freeAccountNumber()]);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_updated_account_reflects_the_change_on_read_back(): void
    {
        $create = $this->create([
            'accountNumber' => $this->freeAccountNumber(),
            'description' => 'before',
            'accountType' => 'D',
        ]);
        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];

        $put = RestApiEnv::http('PUT', self::ACCOUNTS, ['id' => $id, 'description' => 'after'], RestApiEnv::authHeaders());

        $this->assertSame(200, $put['status'], $put['body']);
        $this->assertTrue($put['json']['success']);
        $this->assertSame('after', $this->read($id)['json']['data']['beskrivelse']);
    }

    public function test_reading_or_updating_a_nonexistent_account_returns_404(): void
    {
        $read = $this->read(99999999);
        $this->assertSame(404, $read['status'], $read['body']);

        $put = RestApiEnv::http('PUT', self::ACCOUNTS, ['id' => 99999999, 'description' => 'x'], RestApiEnv::authHeaders());
        $this->assertSame(404, $put['status'], $put['body']);
    }

    public function test_delete_is_not_allowed_405(): void
    {
        $res = RestApiEnv::http('DELETE', self::ACCOUNTS . '?id=1', null, RestApiEnv::authHeaders());

        $this->assertSame(405, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }
}
