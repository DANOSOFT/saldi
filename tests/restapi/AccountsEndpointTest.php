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
// 20261105 CL/LH A kontonr the fiscal year already has is refused with 400 on POST and on a renumbering PUT, and nothing is stored.
//                A PUT that keeps its number is saved even when an older duplicate shares it.
//                A number that is not a whole number from 1 is refused with 400, so a fraction is never rounded onto the next account.
// 20261105 CL/LH A PUT without vat keeps the account's VAT code, and one with vat stores the new code.

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

    /** @return list<array{id: string, beskrivelse: string}> kontoplan rows with that number in the latest fiscal year */
    private function rowsWithNumber(int $kontonr): array
    {
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $rows = RestApiEnv::rows($tenant, "SELECT id, beskrivelse FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = (SELECT max(kodenr) FROM grupper WHERE art = 'RA') ORDER BY id", [$kontonr]);
        pg_close($tenant);
        return $rows;
    }

    /** The account card refuses a second account with the same number in a fiscal year. */
    public function test_the_same_account_posted_twice_is_refused_400_and_stored_once(): void
    {
        $kontonr = $this->freeAccountNumber();
        $body = ['accountNumber' => $kontonr, 'description' => 'Apitest konto æøå', 'accountType' => 'D', 'vat' => 'S1'];
        $first = $this->create($body);
        $this->assertSame(201, $first['status'], $first['body']);
        $id = (int)$first['json']['data']['id'];

        $repeat = $this->create($body);

        $this->assertSame(400, $repeat['status'], $repeat['body']);
        $this->assertFalse($repeat['json']['success']);
        $this->assertStringContainsString('already exists', $repeat['json']['message']);
        $this->assertSame([['id' => (string)$id, 'beskrivelse' => 'Apitest konto æøå']], $this->rowsWithNumber($kontonr), 'one account with that number');
    }

    public function test_renumbering_onto_another_accounts_number_is_refused_400(): void
    {
        $taken = $this->freeAccountNumber();
        $this->assertSame(201, $this->create(['accountNumber' => $taken, 'description' => 'taken', 'accountType' => 'D'])['status']);
        $own = $taken === 9999999 ? 9000000 : $taken + 1;
        $create = $this->create(['accountNumber' => $own, 'description' => 'mover', 'accountType' => 'D']);
        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];

        $put = RestApiEnv::http('PUT', self::ACCOUNTS, ['id' => $id, 'accountNumber' => $taken], RestApiEnv::authHeaders());

        $this->assertSame(400, $put['status'], $put['body']);
        $this->assertStringContainsString('already exists', $put['json']['message']);
        $this->assertSame($own, (int)$this->read($id)['json']['data']['kontonr']);
        $this->assertCount(1, $this->rowsWithNumber($taken));
    }

    /** Rows the API duplicated before the check stay editable; only a new number is checked. */
    public function test_a_put_that_keeps_its_number_is_saved_beside_an_older_duplicate(): void
    {
        $kontonr = $this->freeAccountNumber();
        $create = $this->create(['accountNumber' => $kontonr, 'description' => 'before', 'accountType' => 'D']);
        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        RestApiEnv::rows($tenant, 'INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, regnskabsaar) SELECT kontonr, $2, kontotype, regnskabsaar FROM kontoplan WHERE id = $1', [$id, 'older duplicate']);
        pg_close($tenant);

        $put = RestApiEnv::http('PUT', self::ACCOUNTS, ['id' => $id, 'accountNumber' => $kontonr, 'description' => 'after'], RestApiEnv::authHeaders());

        $this->assertSame(200, $put['status'], $put['body']);
        $this->assertSame('after', $this->read($id)['json']['data']['beskrivelse']);
    }

    /** kontonr is numeric(15,0): the database would round 1001.6 to 1002, so a fraction must not pass the check as 1001. */
    public function test_a_fractional_account_number_is_refused_400_instead_of_rounded_onto_the_next_account(): void
    {
        $low = $this->freeAccountNumber();
        $high = $low + 1;
        $this->assertSame(201, $this->create(['accountNumber' => $high, 'description' => 'next', 'accountType' => 'D'])['status']);

        $post = $this->create(['accountNumber' => $low + 0.6, 'description' => 'fraction', 'accountType' => 'D']);

        $this->assertSame(400, $post['status'], $post['body']);
        $this->assertStringContainsString('whole number', $post['json']['message']);
        $this->assertSame([], $this->rowsWithNumber($low));
        $this->assertCount(1, $this->rowsWithNumber($high));

        $create = $this->create(['accountNumber' => $low, 'description' => 'mover', 'accountType' => 'D']);
        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];

        $put = RestApiEnv::http('PUT', self::ACCOUNTS, ['id' => $id, 'accountNumber' => $low + 0.6], RestApiEnv::authHeaders());

        $this->assertSame(400, $put['status'], $put['body']);
        $this->assertStringContainsString('whole number', $put['json']['message']);
        $this->assertSame($low, (int)$this->read($id)['json']['data']['kontonr']);
        $this->assertCount(1, $this->rowsWithNumber($high));
    }

    /** swagger.yaml documents accountNumber as a string of digits; an integer works too, anything else is refused. */
    public function test_an_account_number_is_a_string_of_digits_or_an_integer(): void
    {
        $kontonr = $this->freeAccountNumber();
        $create = $this->create(['accountNumber' => (string)$kontonr, 'description' => 'digits', 'accountType' => 'D']);
        $this->assertSame(201, $create['status'], $create['body']);
        $this->assertSame([['id' => (string)$create['json']['data']['id'], 'beskrivelse' => 'digits']], $this->rowsWithNumber($kontonr));

        foreach (['12a', '', '-5', 0, '1.5', '1000000000000000'] as $bad) {
            $res = $this->create(['accountNumber' => $bad, 'description' => 'bad number', 'accountType' => 'D']);
            $this->assertSame(400, $res['status'], var_export($bad, true) . ': ' . $res['body']);
            $this->assertStringContainsString('whole number', $res['json']['message']);
        }
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

    private function storedMoms(int $id): ?string
    {
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $rows = RestApiEnv::rows($tenant, 'SELECT moms FROM kontoplan WHERE id = $1', [$id]);
        pg_close($tenant);
        return $rows[0]['moms'] ?? null;
    }

    /** A PUT changes only the fields it sends: one without vat keeps the account's VAT code, one with vat stores it. */
    public function test_a_put_without_vat_keeps_the_vat_code_and_one_with_vat_changes_it(): void
    {
        $create = $this->create(['accountNumber' => $this->freeAccountNumber(), 'description' => 'Apitest konto æøå', 'accountType' => 'D', 'vat' => 'S1']);
        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];
        $this->assertSame('S1', $this->read($id)['json']['data']['moms'], 'the VAT code reads back as stored');

        $put = RestApiEnv::http('PUT', self::ACCOUNTS, ['id' => $id, 'description' => 'Apitest konto Ærø'], RestApiEnv::authHeaders());

        $this->assertSame(200, $put['status'], $put['body']);
        $this->assertSame('S1', $put['json']['data']['moms']);
        $this->assertSame('S1', $this->storedMoms($id), 'a description-only PUT leaves kontoplan.moms alone');
        $read = $this->read($id)['json']['data'];
        $this->assertSame('Apitest konto Ærø', $read['beskrivelse']);
        $this->assertSame('S1', $read['moms']);

        $put = RestApiEnv::http('PUT', self::ACCOUNTS, ['id' => $id, 'vat' => 'K1'], RestApiEnv::authHeaders());

        $this->assertSame(200, $put['status'], $put['body']);
        $this->assertSame('K1', $this->storedMoms($id));
        $this->assertSame('Apitest konto Ærø', $this->read($id)['json']['data']['beskrivelse']);
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
