<?php
// 20261110 CDX/ASR Cover CRM-only leads, validation and in-place conversion at the first quotation or order.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class LeadLifecycle extends TestCase
{
    private ?CrmLeadFixture $fixture = null;

    protected function setUp(): void
    {
        $dsn = (string) getenv('SALDI_CHAR_PG_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN to the disposable PostgreSQL fixture database.');
        }
        self::assertTrue(extension_loaded('pgsql'), 'The configured fixture requires the pgsql extension.');
        require_once __DIR__ . '/../support/CrmLeadFixture.php';
        $this->fixture = new CrmLeadFixture($dsn);
    }

    protected function tearDown(): void
    {
        if ($this->fixture !== null) {
            $this->fixture->close();
        }
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function input(array $changes = []): array
    {
        return array_replace([
            'id' => 0, 'n' => "  Øster's prospective customer  ", 'tlf' => '12345678',
            'mail' => 'prospect@example.invalid', 'addr1' => 'Testvej 17', 'addr2' => '2. sal',
            'zip' => '2100', 'by' => 'København Ø', 'land' => 'Danmark', 'cvr' => '',
            'own' => 11, 'st' => '',
        ], $changes);
    }

    /** @return array<string, mixed> */
    private function action(string $action, array $input): array
    {
        return $this->fixture->action(static fn(): array => crm_action($action, $input));
    }

    private function lead(array $changes = []): int
    {
        $result = $this->action('save_lead', $this->input($changes));
        self::assertArrayNotHasKey('error', $result, json_encode($result));
        self::assertGreaterThan(0, $result['id']);
        return $result['id'];
    }

    /** @return array<string, mixed> */
    private function prepare(int $id, array $changes = []): array
    {
        return $this->action('prepare_document', array_replace(['cid' => $id, 'group' => 21, 'kind' => 'quote'], $changes));
    }

    /** @return array<string, string|null> */
    private function customer(int $id): array
    {
        return $this->fixture->rows('SELECT * FROM adresser WHERE id = $1', [$id])[0];
    }

    public function testNewLeadIsVisibleInCrmAndSearchWithoutReceivingADebtorNumber(): void
    {
        $id = $this->lead();
        $row = $this->customer($id);
        self::assertSame('L', $row['art']);
        self::assertNull($row['kontonr']);
        self::assertSame("Øster's prospective customer", $row['firmanavn']);
        self::assertSame('København Ø', $row['bynavn']);
        self::assertSame('prospect@example.invalid', $row['email']);
        self::assertSame([['id' => '2'], ['id' => '3']], $this->fixture->rows("SELECT id FROM adresser WHERE art = 'D' ORDER BY id"));

        $dataset = crm_dataset();
        $customers = array_column($dataset['customers'], null, 'id');
        self::assertArrayHasKey($id, $customers);
        self::assertTrue($customers[$id]['lead']);
        self::assertSame('', $customers[$id]['nr']);
        self::assertSame('2100', $customers[$id]['zip']);
        self::assertSame([$id], array_column(crm_search("Øster's"), 'id'));
        self::assertSame([21], array_column($dataset['debtorGroups'], 'id'));
        self::assertTrue($dataset['canOrder']);

        // A new lead still belongs in CRM when it has no status, case or history.
        $this->fixture->query('DELETE FROM historik WHERE konto_id = $1', [$id]);
        self::assertContains($id, array_column(crm_dataset()['customers'], 'id'));
    }

    public function testEditingALeadPreservesItsIdentityAndCanClearOptionalFields(): void
    {
        $id = $this->lead(['cvr' => '12-34 56 78', 'st' => '1']);
        $beforeHistory = $this->fixture->rows('SELECT * FROM historik ORDER BY id');
        $result = $this->action('save_lead', $this->input([
            'id' => $id, 'n' => 'Updated lead', 'mail' => '', 'cvr' => '12345678',
            'addr2' => '', 'own' => 0, 'st' => '',
        ]));
        self::assertSame(['id' => $id], $result);
        $row = $this->customer($id);
        self::assertSame('L', $row['art']);
        self::assertNull($row['kontonr']);
        self::assertSame('Updated lead', $row['firmanavn']);
        self::assertSame('', $row['email']);
        self::assertSame('', $row['addr2']);
        self::assertNull($row['kontoansvarlig']);
        self::assertSame($beforeHistory, $this->fixture->rows('SELECT * FROM historik ORDER BY id'));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidInputs(): array
    {
        return [
            'blank name' => [['n' => '  '], 'lead_name'],
            'invalid email' => [['mail' => 'not-an-email'], 'lead_email'],
            'name too long' => [['n' => str_repeat('æ', 91)], 'lead_length'],
            'zip too long' => [['zip' => '12345678901'], 'lead_length'],
            'null byte' => [['addr1' => "Street\0suffix"], 'lead_length'],
            'array value' => [['mail' => ['unexpected']], 'fields'],
            'unknown owner' => [['own' => 9999], 'fields'],
            'customer contact is not a colleague' => [['own' => 13], 'fields'],
            'unknown status' => [['st' => 'missing'], 'fields'],
            'existing debtor CVR' => [['cvr' => '87-65 43 21'], 'lead_duplicate'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidCreateAndEditDoNotChangeCustomersOrHistory(array $changes, string $error): void
    {
        $id = $this->lead();
        $this->fixture->query("INSERT INTO ansatte (id, konto_id, navn) VALUES (13, $1, 'Customer contact')", [$id]);
        $customers = $this->fixture->rows('SELECT * FROM adresser ORDER BY id');
        $history = $this->fixture->rows('SELECT * FROM historik ORDER BY id');
        foreach ([0, $id] as $editId) {
            $result = $this->action('save_lead', $this->input(array_replace($changes, ['id' => $editId])));
            self::assertSame($error, $result['error'] ?? null);
            self::assertSame($customers, $this->fixture->rows('SELECT * FROM adresser ORDER BY id'));
            self::assertSame($history, $this->fixture->rows('SELECT * FROM historik ORDER BY id'));
        }
    }

    public function testDuplicateLeadCvrIsRejectedButBlankCvrAllowsMultipleProspects(): void
    {
        $this->lead(['cvr' => 'DK 1234-5678']);
        $duplicate = $this->action('save_lead', $this->input(['n' => 'Another prospect', 'cvr' => 'dk12345678']));
        self::assertSame('lead_duplicate', $duplicate['error'] ?? null);
        $first = $this->lead(['n' => 'No CVR yet']);
        $second = $this->lead(['n' => 'Another without CVR']);
        self::assertNotSame($first, $second);
    }

    /** @return array<string, array{0: string}> */
    public static function documentKinds(): array
    {
        return ['quotation' => ['quote'], 'order' => ['order']];
    }

    #[DataProvider('documentKinds')]
    public function testFirstDocumentPromotesSameCustomerAndPreservesContactsNotesAndCases(string $kind): void
    {
        $id = $this->lead(['st' => '1']);
        $this->fixture->query("INSERT INTO ansatte (id, konto_id, navn, email) VALUES (13, $1, 'Contact person', 'contact@example.invalid')", [$id]);
        $note = $this->action('add_entry', ['cid' => $id, 'note' => 'Discuss the requested quotation', 'to' => 13, 'due' => '2027-02-01', 'owner' => 11]);
        self::assertArrayHasKey('id', $note);
        $case = $this->action('save_deal', ['cid' => $id, 't' => 'Initial order', 'v' => '1500.50', 'st' => '1', 'own' => 11]);
        self::assertArrayHasKey('id', $case);
        $contacts = $this->fixture->rows('SELECT * FROM ansatte ORDER BY id');
        $notes = $this->fixture->rows('SELECT * FROM historik ORDER BY id');
        $cases = $this->fixture->rows('SELECT * FROM crm_deals ORDER BY id');
        $original = $this->customer($id);

        $result = $this->prepare($id, ['kind' => $kind, 'deal' => $case['id']]);
        self::assertSame(['id' => $id, 'nr' => '1003'], $result);
        $row = $this->customer($id);
        self::assertSame('D', $row['art']);
        self::assertSame('1003', $row['kontonr']);
        self::assertSame('4', $row['gruppe'], 'Use the DG account code, not its row id.');
        self::assertSame('Netto', $row['betalingsbet']);
        self::assertSame('8', $row['betalingsdage']);
        self::assertSame('erhverv', $row['kontotype']);
        foreach (['firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'land', 'tlf', 'email', 'cvrnr', 'status', 'kontoansvarlig', 'oprettet', 'kontaktes'] as $field) {
            self::assertSame($original[$field], $row[$field], $field . ' must survive promotion.');
        }
        self::assertSame($contacts, $this->fixture->rows('SELECT * FROM ansatte ORDER BY id'));
        self::assertSame($cases, $this->fixture->rows('SELECT * FROM crm_deals ORDER BY id'));
        $afterNotes = $this->fixture->rows('SELECT * FROM historik ORDER BY id');
        self::assertSame($notes, array_slice($afterNotes, 0, count($notes)));
        self::assertCount(count($notes) + 1, $afterNotes, 'One conversion history entry is appended.');
        self::assertSame([], $this->fixture->rows('SELECT * FROM ordrer'), 'Preparing the customer does not create an incomplete document.');
        $customers = array_column(crm_dataset()['customers'], null, 'id');
        self::assertFalse($customers[$id]['lead']);
        self::assertSame(13, $customers[$id]['ppl'][0]['id']);
        self::assertSame($id, crm_deals($id)[0]['cid']);

        // A retry, another browser tab and a later document must not allocate again.
        self::assertSame($result, $this->prepare($id, ['group' => 0, 'kind' => $kind]));
        self::assertSame($row, $this->customer($id));
        self::assertSame($afterNotes, $this->fixture->rows('SELECT * FROM historik ORDER BY id'));
    }

    public function testPromotionUsesNormalDebtorPaymentDefaultsAndAllocatesAcrossSuppliers(): void
    {
        $this->fixture->query("UPDATE adresser SET betalingsbet = 'Lb. md.', betalingsdage = 30 WHERE art = 'D'");
        $this->fixture->query("UPDATE adresser SET kontonr = '1010' WHERE art = 'K'");
        $id = $this->lead();
        self::assertSame(['id' => $id, 'nr' => '1011'], $this->prepare($id));
        self::assertSame('Lb. md.', $this->customer($id)['betalingsbet']);
        self::assertSame('30', $this->customer($id)['betalingsdage']);
        $next = $this->lead(['n' => 'Next prospect']);
        self::assertSame(['id' => $next, 'nr' => '1012'], $this->prepare($next, ['kind' => 'order']));
    }

    /** @return array<string, array{0: int}> */
    public static function invalidGroups(): array
    {
        return ['missing' => [0], 'unknown' => [999], 'previous year' => [22], 'creditor group' => [23], 'inactive' => [26], 'zero code' => [27]];
    }

    #[DataProvider('invalidGroups')]
    public function testInvalidDebtorGroupDoesNotPromoteLead(int $group): void
    {
        $id = $this->lead();
        $before = $this->customer($id);
        $history = $this->fixture->rows('SELECT * FROM historik ORDER BY id');
        self::assertSame('lead_group', $this->prepare($id, ['group' => $group])['error'] ?? null);
        self::assertSame($before, $this->customer($id));
        self::assertSame($history, $this->fixture->rows('SELECT * FROM historik ORDER BY id'));
    }

    public function testDuplicateCreatedAfterLeadWasSavedPreventsItsPromotion(): void
    {
        $id = $this->lead(['cvr' => 'DK12345678']);
        $this->fixture->query("UPDATE adresser SET cvrnr = 'dk 1234-5678' WHERE id = 2");
        self::assertSame('lead_duplicate', $this->prepare($id)['error'] ?? null);
        self::assertSame('L', $this->customer($id)['art']);
        self::assertNull($this->customer($id)['kontonr']);
    }

    public function testOnlyOpenUnlinkedCasesOfTheSameLeadCanPrepareDocuments(): void
    {
        $id = $this->lead();
        $other = $this->lead(['n' => 'Other prospect']);
        $this->fixture->query("INSERT INTO crm_deals (id, konto_id, title, state, ordre_id) VALUES
            (31, $1, 'Already linked', 'open', 41), (32, $2, 'Someone else', 'open', NULL),
            (33, $1, 'Lost', 'lost', NULL)", [$id, $other]);
        $before = $this->customer($id);
        foreach ([31 => 'linked', 32 => 'customer', 33 => 'customer', 999 => 'customer'] as $deal => $error) {
            self::assertSame($error, $this->prepare($id, ['deal' => $deal])['error'] ?? null);
            self::assertSame($before, $this->customer($id));
        }
    }

    public function testQuickInvoiceConfigurationRejectsQuoteBeforeConvertingButAllowsOrder(): void
    {
        $id = $this->lead();
        $this->fixture->query("INSERT INTO grupper (id, art, kodenr, box4) VALUES (29, 'DIV', 3, 'on')");
        self::assertSame('quick', $this->prepare($id)['error'] ?? null);
        self::assertSame('L', $this->customer($id)['art']);
        self::assertSame(['id' => $id, 'nr' => '1003'], $this->prepare($id, ['kind' => 'order']));
    }

    public function testLostCaseLeavesTheProspectOutsideTheDebtorRegister(): void
    {
        $id = $this->lead(['st' => '1']);
        $case = $this->action('save_deal', ['cid' => $id, 't' => 'Potential sale', 'v' => '500.00', 'st' => '1', 'own' => 11]);
        self::assertArrayNotHasKey('error', $case);
        $result = $this->action('move_deal', ['id' => $case['id'], 'state' => 'lost', 'reason' => 'No budget']);
        self::assertArrayNotHasKey('error', $result);
        self::assertSame('lost', crm_deals($id)[0]['state']);
        self::assertSame('L', $this->customer($id)['art']);
        self::assertNull($this->customer($id)['kontonr']);
        self::assertSame([], $this->fixture->rows("SELECT id FROM adresser WHERE art = 'D' AND id = $1", [$id]));
    }

    public function testInvalidDocumentRequestsAndClosedCustomersDoNotConvert(): void
    {
        $id = $this->lead();
        $before = $this->fixture->rows('SELECT * FROM adresser ORDER BY id');
        foreach ([0, -1] as $invalidId) {
            self::assertSame('fields', $this->prepare($invalidId)['error'] ?? null);
        }
        self::assertSame('fields', $this->prepare($id, ['kind' => 'invoice'])['error'] ?? null);
        foreach ([4, 999] as $invalidId) {
            self::assertSame('customer', $this->prepare($invalidId)['error'] ?? null);
        }
        self::assertSame($before, $this->fixture->rows('SELECT * FROM adresser ORDER BY id'));
        $this->fixture->query("UPDATE adresser SET lukket = 'on' WHERE id = $1", [$id]);
        self::assertSame('customer', $this->prepare($id)['error'] ?? null);
        self::assertSame('lead_changed', $this->action('save_lead', $this->input(['id' => $id]))['error'] ?? null);
        self::assertSame('L', $this->customer($id)['art']);
        self::assertNull($this->customer($id)['kontonr']);
    }

    public function testOlderGroupSchemaWithoutInactiveFlagStillSupportsPromotion(): void
    {
        $this->fixture->query('ALTER TABLE grupper DROP COLUMN inaktiv');
        $id = $this->lead();
        self::assertSame(['id' => $id, 'nr' => '1003'], $this->prepare($id));
    }

    /** @return array<string, array{0: int}> */
    public static function readOnlyPermissions(): array
    {
        return ['debtor accounts' => [6], 'sales orders' => [5]];
    }

    #[DataProvider('readOnlyPermissions')]
    public function testReadOnlyPermissionsPreventPromotionAndAccountWritePermissionPreventsLeadSaves(int $position): void
    {
        // Configure rights before the first perm_can(), whose result is cached per request.
        $GLOBALS['rettigheder'][$position] = '2';
        $this->fixture->query("INSERT INTO adresser (id, art, firmanavn) VALUES (50, 'L', 'Protected prospect')");
        $before = $this->fixture->rows('SELECT * FROM adresser ORDER BY id');
        self::assertSame('noaccess', $this->prepare(50)['error'] ?? null);
        if ($position === 6) {
            foreach ([0, 50] as $id) {
                self::assertSame('noaccess', $this->action('save_lead', $this->input(['id' => $id]))['error'] ?? null);
            }
        }
        self::assertSame($before, $this->fixture->rows('SELECT * FROM adresser ORDER BY id'));
        self::assertSame([], $this->fixture->rows('SELECT * FROM historik'));
    }

    public function testStaleLeadEditorCannotOverwriteAnAlreadyPromotedCustomer(): void
    {
        $id = $this->lead();
        self::assertArrayNotHasKey('error', $this->prepare($id));
        $before = $this->customer($id);
        $result = $this->action('save_lead', $this->input(['id' => $id, 'n' => 'Stale edit']));
        self::assertSame('lead_changed', $result['error'] ?? null);
        self::assertSame($before, $this->customer($id));
    }

    public function testCallerCanRollBackConversionAndItsHistoryTogether(): void
    {
        $id = $this->lead();
        $before = $this->customer($id);
        $history = $this->fixture->rows('SELECT * FROM historik ORDER BY id');
        $this->fixture->query('BEGIN');
        $result = crm_prepare_document(['cid' => $id, 'group' => 21, 'kind' => 'order'], 11);
        self::assertArrayNotHasKey('error', $result);
        self::assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->fixture->connection));
        $this->fixture->query('ROLLBACK');
        self::assertSame($before, $this->customer($id));
        self::assertSame($history, $this->fixture->rows('SELECT * FROM historik ORDER BY id'));
    }
    public function testEditingPreservesAnInactiveOwnerAndRetiredStatus(): void
    {
        $id = $this->lead(['own' => 12, 'st' => '1']);
        $this->fixture->query("UPDATE ansatte SET lukket = 'on' WHERE id = 12");
        $this->fixture->query("UPDATE grupper SET box3 = '', box4 = '' WHERE art = 'DebInfo'");
        $result = $this->action('save_lead', $this->input(['id' => $id, 'own' => 12, 'st' => '1', 'tlf' => '87654321']));
        self::assertSame(['id' => $id], $result);
        self::assertSame('87654321', $this->customer($id)['tlf']);
        self::assertSame('12', $this->customer($id)['kontoansvarlig']);
        self::assertSame('1', $this->customer($id)['status']);
        self::assertSame('fields', $this->action('save_lead', $this->input(['own' => 12]))['error']);
    }

    public function testConversionAndDebtorCardShareTheNumberAllocationLock(): void
    {
        $other = pg_connect((string) getenv('SALDI_CHAR_PG_DSN'), PGSQL_CONNECT_FORCE_NEW);
        self::assertNotFalse($other);
        $this->fixture->query('BEGIN');
        try {
            self::assertTrue(crm_lock_customer_number(true));
            $attempt = pg_fetch_assoc(pg_query($other, 'SELECT pg_try_advisory_lock(5130513) AS acquired'));
            self::assertSame('f', $attempt['acquired']);
            $this->fixture->query('ROLLBACK');
            $attempt = pg_fetch_assoc(pg_query($other, 'SELECT pg_try_advisory_lock(5130513) AS acquired'));
            self::assertSame('t', $attempt['acquired']);
            pg_query($other, 'SELECT pg_advisory_unlock(5130513)');
        } finally {
            pg_close($other);
        }
    }

}
