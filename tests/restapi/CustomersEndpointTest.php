<?php
// tests/restapi/CustomersEndpointTest.php
//
// Integration tests for /restapi/endpoints/v1/debitor/customers/: create,
// read back, update, delete, search and the validation rules
// CustomerService enforces (required fields, duplicate email, duplicate
// phone).
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

require_once __DIR__ . '/support/RestApiTestCase.php';

final class CustomersEndpointTest extends RestApiTestCase
{
    private const CUSTOMERS = '/restapi/endpoints/v1/debitor/customers/';

    private static int $seq = 0;

    /** @return array<string,string> a body that passes every validation rule */
    private static function newCustomerBody(array $overrides = []): array
    {
        $n = ++self::$seq;
        $pid = getmypid();

        return $overrides + [
            'companyName' => "API kunde $n-$pid",
            'phone' => sprintf('55%04d%03d', $pid % 10000, $n),
            'email' => "apicust-$n-$pid@example.invalid",
            'address1' => 'Testvej 1',
            'zipCode' => '8000',
            'city' => 'Aarhus',
        ];
    }

    /**
     * Note on shape: the endpoint ACCEPTS English property names
     * (companyName, phone) and RETURNS Danish ones (firmanavn, tlf) -
     * CustomerService::mapApiToDanish only maps one way, and
     * CustomerModel::toArray emits the column names. A client cannot round-trip
     * its own payload.
     */
    public function test_a_customer_can_be_created_and_read_back(): void
    {
        $body = self::newCustomerBody();
        $created = self::api('POST', self::CUSTOMERS, $body);
        $payload = $this->assertSuccessEnvelope($created, 'create');

        $id = (int)($payload['id'] ?? 0);
        $this->assertGreaterThan(0, $id, 'create returns the new id: ' . $created['body']);

        $read = self::api('GET', self::CUSTOMERS . '?id=' . $id);
        $customer = $this->assertSuccessEnvelope($read, 'read back');

        $this->assertSame($body['companyName'], $customer['firmanavn'] ?? null, 'company name round-trips');
        $this->assertSame($body['phone'], $customer['tlf'] ?? null, 'phone round-trips');
        $this->assertSame(
            $body['address1'],
            $customer['adresse']['addr1'] ?? null,
            'address round-trips (nested under adresse on read)'
        );
    }

    /**
     * DEFECT PINNED: the email supplied when creating a customer is written
     * and then immediately erased.
     *
     * CustomerModel::save() INSERTs the row with the email
     * (CustomerModel.php:223), then calls saveKontaktEmails() unconditionally
     * on the create path (:250). That method ends with
     * (CustomerModel.php:456-459):
     *
     *     $r = db_fetch_array(db_select("SELECT email FROM kontakt_emails
     *                                    WHERE konto_id = ... ORDER BY id LIMIT 1"));
     *     $sync_email = $r ? db_escape_string($r['email']) : '';
     *     db_modify("UPDATE adresser SET email = '$sync_email' WHERE id = ...");
     *
     * When the caller did not also send `contactEmails`, that table is empty,
     * so the "sync back for backward compatibility" overwrites the address
     * with an empty string. The customer ends up with no email at all - not in
     * `adresser.email`, not in `kontakt_emails`.
     *
     * Consequences: invoices cannot be emailed to an API-created customer, and
     * the duplicate-email guard below can never fire.
     *
     * The update path is unaffected: there saveKontaktEmails() only runs
     * `if (!empty($this->kontakt_emails))` (:212).
     */
    public function test_the_email_supplied_on_create_is_discarded(): void
    {
        $body = self::newCustomerBody();
        $created = self::api('POST', self::CUSTOMERS, $body);
        $id = (int)$this->assertSuccessEnvelope($created, 'create')['id'];

        // The create RESPONSE echoes the email back, which is what makes this
        // hard to notice from the outside.
        $this->assertSame($body['email'], $created['json']['data']['email'] ?? null, 'the response echoes the email');

        $stored = self::tenantRows('SELECT email FROM adresser WHERE id = $1', [$id]);
        $this->assertSame('', trim((string)$stored[0]['email']), 'but nothing was stored');

        $contacts = self::tenantRows('SELECT email FROM kontakt_emails WHERE konto_id = $1', [$id]);
        $this->assertSame([], $contacts, 'and it is not in kontakt_emails either');
    }

    /**
     * The counterpart that proves the mechanism: send `contactEmails` and the
     * address survives, because the sync-back finds a row to copy.
     */
    public function test_an_email_sent_as_a_contact_email_is_kept(): void
    {
        $body = self::newCustomerBody();
        $body['contactEmails'] = [['email' => $body['email'], 'email_type' => 'faktura']];

        $created = self::api('POST', self::CUSTOMERS, $body);
        $id = (int)$this->assertSuccessEnvelope($created, 'create')['id'];

        $stored = self::tenantRows('SELECT email FROM adresser WHERE id = $1', [$id]);
        $this->assertSame($body['email'], trim((string)$stored[0]['email']), 'the contact email is synced onto the customer');
    }

    /** The created row really is a debtor, not a creditor. */
    public function test_a_created_customer_is_stored_as_a_debtor(): void
    {
        $created = self::api('POST', self::CUSTOMERS, self::newCustomerBody());
        $payload = $this->assertSuccessEnvelope($created, 'create');
        $id = (int)$payload['id'];

        $rows = self::tenantRows('SELECT art FROM adresser WHERE id = $1', [$id]);
        $this->assertCount(1, $rows);
        $this->assertSame('D', trim($rows[0]['art']), 'the row is filed under art=D');
    }

    /**
     * @param string $missing
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('requiredFields')]
    public function test_creating_a_customer_without_a_required_field_is_refused(string $missing): void
    {
        $body = self::newCustomerBody();
        unset($body[$missing]);

        $res = self::api('POST', self::CUSTOMERS, $body);

        $this->assertSame(400, $res['status'], "missing $missing should be refused: " . $res['body']);
        $this->assertFalse($res['json']['success'] ?? true);
        $this->assertStringContainsStringIgnoringCase(
            'missing required field',
            (string)($res['json']['message'] ?? ''),
            'the message names the problem'
        );
    }

    /** @return array<string, array{0:string}> */
    public static function requiredFields(): array
    {
        return [
            'company name' => ['companyName'],
            'phone' => ['phone'],
            'email' => ['email'],
        ];
    }

    /**
     * DEFECT PINNED: the duplicate-email guard never fires, because no
     * customer created through this endpoint ever has an email stored - see
     * test_the_email_supplied_on_create_is_discarded.
     *
     * CustomerService::createCustomer:113 does call
     * CustomerModel::emailExists(), and that query is correct; it just never
     * matches anything. The identically-shaped phone guard works, which is
     * what makes the asymmetry diagnostic rather than a design choice.
     */
    public function test_a_duplicate_email_is_not_refused(): void
    {
        $first = self::newCustomerBody();
        $this->assertSuccessEnvelope(self::api('POST', self::CUSTOMERS, $first), 'first create');

        $second = self::newCustomerBody(['email' => $first['email']]);
        $res = self::api('POST', self::CUSTOMERS, $second);

        $this->assertSuccessEnvelope($res, 'duplicate email');
    }

    /** The duplicate-phone guard, by contrast, does fire. */
    public function test_a_duplicate_phone_number_is_refused(): void
    {
        $first = self::newCustomerBody();
        $this->assertSuccessEnvelope(self::api('POST', self::CUSTOMERS, $first), 'first create');

        $res = self::api('POST', self::CUSTOMERS, self::newCustomerBody(['phone' => $first['phone']]));

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertStringContainsStringIgnoringCase(
            'phone',
            (string)($res['json']['message'] ?? ''),
            'the message names the duplicated field'
        );
    }

    public function test_a_customer_can_be_updated(): void
    {
        $created = self::api('POST', self::CUSTOMERS, self::newCustomerBody());
        $id = (int)$this->assertSuccessEnvelope($created, 'create')['id'];

        $res = self::api('PUT', self::CUSTOMERS . '?id=' . $id, ['companyName' => 'Omdøbt kunde']);
        $this->assertSuccessEnvelope($res, 'update');

        $rows = self::tenantRows('SELECT firmanavn FROM adresser WHERE id = $1', [$id]);
        $this->assertSame('Omdøbt kunde', trim($rows[0]['firmanavn']), 'the new name is persisted');
    }

    public function test_updating_without_an_id_is_refused(): void
    {
        $res = self::api('PUT', self::CUSTOMERS, ['companyName' => 'Ingen id']);

        $this->assertSame(400, $res['status'], $res['body']);
        $this->assertStringContainsStringIgnoringCase('id', (string)($res['json']['message'] ?? ''));
    }

    public function test_reading_a_nonexistent_customer_returns_404(): void
    {
        $res = self::api('GET', self::CUSTOMERS . '?id=99999999');

        $this->assertSame(404, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success'] ?? true);
    }

    public function test_the_list_is_an_array_and_honours_its_limit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            self::api('POST', self::CUSTOMERS, self::newCustomerBody());
        }

        $res = self::api('GET', self::CUSTOMERS . '?limit=2');
        $items = $this->assertSuccessEnvelope($res, 'list');

        $this->assertIsArray($items);
        $this->assertCount(2, $items, 'the limit is honoured');
    }

    /** `limit` is cast to an int here, so a SQL fragment cannot reach the query. */
    public function test_the_limit_parameter_is_cast_to_an_integer(): void
    {
        for ($i = 0; $i < 3; $i++) {
            self::api('POST', self::CUSTOMERS, self::newCustomerBody());
        }

        $res = self::api('GET', self::CUSTOMERS . '?limit=' . rawurlencode('1 OFFSET 2'));
        $items = $this->assertSuccessEnvelope($res, 'limit with a fragment');

        $this->assertCount(1, $items, '"1 OFFSET 2" is cast to 1, the fragment is discarded');

        $baseline = $this->assertSuccessEnvelope(self::api('GET', self::CUSTOMERS . '?limit=1'), 'baseline');
        $this->assertSame(
            $baseline[0]['id'] ?? null,
            $items[0]['id'] ?? null,
            'no OFFSET was applied - the same first row comes back'
        );
    }

    public function test_a_customer_can_be_deleted(): void
    {
        $created = self::api('POST', self::CUSTOMERS, self::newCustomerBody());
        $id = (int)$this->assertSuccessEnvelope($created, 'create')['id'];

        $res = self::api('DELETE', self::CUSTOMERS . '?id=' . $id);
        $this->assertSuccessEnvelope($res, 'delete');

        $read = self::api('GET', self::CUSTOMERS . '?id=' . $id);
        $this->assertSame(404, $read['status'], 'the customer is gone: ' . $read['body']);
    }
}
