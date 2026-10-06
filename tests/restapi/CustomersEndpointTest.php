<?php
// tests/restapi/CustomersEndpointTest.php
//
// Integration tests for /restapi/endpoints/v1/debitor/customers/ and
// /restapi/endpoints/v1/creditor/creditors/ against the local docker
// stack's self-provisioned tenant: auth enforcement, create (English field
// names) -> read-back, required-field and duplicate validation, search,
// update, delete, and the debtor/creditor (art D/K) separation.
//
// History:
// 20260904 CL/NTR created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class CustomersEndpointTest extends TestCase
{
    private const CUSTOMERS = '/restapi/endpoints/v1/debitor/customers/';
    private const CREDITORS = '/restapi/endpoints/v1/creditor/creditors/';

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

    /** Unique per call so phone/email duplicate checks never trip on a previous test's rows. */
    private function customerBody(string $tag): array
    {
        $digits = (string)random_int(10000000, 99999999);
        return [
            'companyName' => "Apitest $tag ApS",
            'phone' => $digits,
            'email' => "apitest-$tag-$digits@example.invalid",
            'address1' => 'Testvej 1',
            'postalCode' => '8000',
            'city' => 'Aarhus C',
            'vatNumber' => '12345678',
            'paymentDays' => 14,
        ];
    }

    private function create(string $path, array $body): array
    {
        return RestApiEnv::http('POST', $path, $body, RestApiEnv::authHeaders());
    }

    private function read(string $path, int $id): array
    {
        return RestApiEnv::http('GET', $path . '?id=' . $id, null, RestApiEnv::authHeaders());
    }

    public function test_customers_require_a_bearer_token(): void
    {
        $res = RestApiEnv::http('GET', self::CUSTOMERS);

        $this->assertSame(401, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_customer_list_returns_array_and_honours_limit(): void
    {
        $res = RestApiEnv::http('GET', self::CUSTOMERS . '?limit=2', null, RestApiEnv::authHeaders());

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertTrue($res['json']['success']);
        $this->assertIsArray($res['json']['data']);
        $this->assertLessThanOrEqual(2, count($res['json']['data']));
    }

    public function test_created_customer_can_be_read_back_with_mapped_fields(): void
    {
        $body = $this->customerBody('create');
        $create = $this->create(self::CUSTOMERS, $body);

        $this->assertSame(201, $create['status'], $create['body']);
        $this->assertTrue($create['json']['success']);
        $id = (int)($create['json']['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $id, 'created customer has an id');

        $read = $this->read(self::CUSTOMERS, $id);

        $this->assertSame(200, $read['status'], $read['body']);
        $c = $read['json']['data'];
        $this->assertSame($id, (int)$c['id']);
        $this->assertSame($body['companyName'], $c['firmanavn']);
        $this->assertSame($body['phone'], $c['tlf']);
        $this->assertSame($body['email'], $c['email']);
        $this->assertSame($body['address1'], $c['adresse']['addr1']);
        $this->assertSame($body['postalCode'], $c['adresse']['postnr']);
        $this->assertSame($body['city'], $c['adresse']['bynavn']);
        $this->assertSame($body['vatNumber'], $c['cvrnr']);
        $this->assertSame(14, (int)$c['betaling']['betalingsdage']);
        $this->assertNotEmpty($c['kontonr'], 'a debtor account number is assigned');
        $this->assertSame($c['kontonr'], $c['kundenr']);
        $this->assertIsArray($c['kontakt_emails']);

        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $rows = RestApiEnv::rows($tenant, 'SELECT art FROM adresser WHERE id = $1', [$id]);
        pg_close($tenant);
        $this->assertSame([['art' => 'D']], $rows, 'stored as a debtor (art D)');
    }

    public function test_contact_emails_round_trip_and_first_one_becomes_the_primary_email(): void
    {
        $body = $this->customerBody('emails');
        $body['contactEmails'] = [
            ['email' => 'faktura@example.invalid', 'email_type' => 'faktura'],
            ['email' => 'ordre@example.invalid', 'email_type' => 'ordre'],
        ];
        $create = $this->create(self::CUSTOMERS, $body);
        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];

        $read = $this->read(self::CUSTOMERS, $id);

        $this->assertSame(200, $read['status'], $read['body']);
        $emails = $read['json']['data']['kontakt_emails'];
        $this->assertSame(['faktura@example.invalid', 'ordre@example.invalid'], array_column($emails, 'email'));
        $this->assertSame(['faktura', 'ordre'], array_column($emails, 'email_type'));
        $this->assertSame('faktura@example.invalid', $read['json']['data']['email'], 'first contact email is synced to adresser.email');
    }

    public function test_create_without_phone_is_rejected_400(): void
    {
        $body = $this->customerBody('nophone');
        unset($body['phone']);

        $res = $this->create(self::CUSTOMERS, $body);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('phone', $res['json']['message']);
    }

    public function test_create_with_duplicate_email_is_rejected_400(): void
    {
        $first = $this->customerBody('dup');
        $create = $this->create(self::CUSTOMERS, $first);
        $this->assertSame(201, $create['status'], $create['body']);

        $second = $this->customerBody('dup2');
        $second['email'] = $first['email'];
        $res = $this->create(self::CUSTOMERS, $second);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
        $this->assertStringContainsString('already in use', $res['json']['message']);
    }

    public function test_search_finds_the_created_customer_only(): void
    {
        $body = $this->customerBody('search' . random_int(1000, 9999));
        $create = $this->create(self::CUSTOMERS, $body);
        $this->assertSame(201, $create['status'], $create['body']);

        $res = RestApiEnv::http('GET', self::CUSTOMERS . '?search=' . urlencode($body['companyName']), null, RestApiEnv::authHeaders());

        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertCount(1, $res['json']['data']);
        $this->assertSame($body['companyName'], $res['json']['data'][0]['firmanavn']);
    }

    public function test_updated_customer_reflects_the_change_on_read_back(): void
    {
        $create = $this->create(self::CUSTOMERS, $this->customerBody('update'));
        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];

        $put = RestApiEnv::http('PUT', self::CUSTOMERS . '?id=' . $id, [
            'companyName' => 'Apitest Renamed ApS',
            'city' => 'Odense C',
        ], RestApiEnv::authHeaders());

        $this->assertSame(200, $put['status'], $put['body']);
        $this->assertTrue($put['json']['success']);
        $this->assertSame('Apitest Renamed ApS', $put['json']['data']['firmanavn']);

        $read = $this->read(self::CUSTOMERS, $id);
        $this->assertSame('Apitest Renamed ApS', $read['json']['data']['firmanavn']);
        $this->assertSame('Odense C', $read['json']['data']['adresse']['bynavn']);
    }

    public function test_update_without_id_is_rejected_400(): void
    {
        $res = RestApiEnv::http('PUT', self::CUSTOMERS, ['companyName' => 'Nobody'], RestApiEnv::authHeaders());

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
    }

    public function test_deleted_customer_is_gone_from_api_and_database(): void
    {
        $create = $this->create(self::CUSTOMERS, $this->customerBody('delete'));
        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];

        $del = RestApiEnv::http('DELETE', self::CUSTOMERS . '?id=' . $id, null, RestApiEnv::authHeaders());

        $this->assertSame(200, $del['status'], $del['body']);
        $this->assertTrue($del['json']['success']);

        $read = $this->read(self::CUSTOMERS, $id);
        $this->assertSame(404, $read['status'], $read['body']);

        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $rows = RestApiEnv::rows($tenant, 'SELECT id FROM adresser WHERE id = $1', [$id]);
        pg_close($tenant);
        $this->assertSame([], $rows, 'adresser row removed');
    }

    public function test_reading_or_deleting_a_nonexistent_customer_returns_404(): void
    {
        $read = $this->read(self::CUSTOMERS, 99999999);
        $this->assertSame(404, $read['status'], $read['body']);
        $this->assertFalse($read['json']['success']);

        $del = RestApiEnv::http('DELETE', self::CUSTOMERS . '?id=99999999', null, RestApiEnv::authHeaders());
        $this->assertSame(404, $del['status'], $del['body']);
        $this->assertFalse($del['json']['success']);
    }

    public function test_creditor_is_stored_as_art_k_and_invisible_to_the_debtor_endpoint(): void
    {
        $body = $this->customerBody('creditor');
        $create = $this->create(self::CREDITORS, $body);

        $this->assertSame(201, $create['status'], $create['body']);
        $id = (int)$create['json']['data']['id'];
        $this->assertGreaterThan(0, $id);

        $asCreditor = $this->read(self::CREDITORS, $id);
        $this->assertSame(200, $asCreditor['status'], $asCreditor['body']);
        $this->assertSame($body['companyName'], $asCreditor['json']['data']['firmanavn']);

        $asDebtor = $this->read(self::CUSTOMERS, $id);
        $this->assertSame(404, $asDebtor['status'], 'a creditor id must not resolve as a debtor');

        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $rows = RestApiEnv::rows($tenant, 'SELECT art FROM adresser WHERE id = $1', [$id]);
        pg_close($tenant);
        $this->assertSame([['art' => 'K']], $rows);

        $del = RestApiEnv::http('DELETE', self::CREDITORS . '?id=' . $id, null, RestApiEnv::authHeaders());
        $this->assertSame(200, $del['status'], $del['body']);
        $this->assertSame(404, $this->read(self::CREDITORS, $id)['status']);
    }
}
