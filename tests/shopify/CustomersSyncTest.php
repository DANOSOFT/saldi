<?php
// tests/shopify/CustomersSyncTest.php
//
// Inbound customers and core edit 8 against a cloned tenant (SD-792): one debtor per Shopify
// customer, matched on the GID only (K29, K32), the shared number allocator under one lock (two
// processes), replay and older events (K41), address changes (K28) that never touch the local
// tlf, adoption of an open outbound create, and create_debtor's two paths (taken, free).
//
// History:
// 20261101 CL/SS SD-792: created.
// 20261102 CL/SS SD-792: the test creates its own debtor group; CI's template has no group 2.
// 20261102 CL/SS SD-792: a number committed by a LOCK TABLE creator while the worker waits is not taken over.
// 20261109 CL/SS SD-814: the customer type, privat with first and last name or erhverv with a company.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class CustomersSyncTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';
    /** @var resource|\PgSql\Connection|null */
    private $conn = null;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_m');
            ShopifyEnv::cloneTenant(self::$db);
            ShopifyEnv::registerTenant(self::$db);
            $run = ShopifyEnv::runSchema(self::$db, 'ensure');
            if ($run['exit'] !== 0) {
                throw new RuntimeException('schema ensure failed: ' . $run['stderr']);
            }
        } catch (Throwable $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        ShopifyEnv::unregisterTenants();
        ShopifyEnv::dropOwnedTenants();
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $this->conn = ShopifyEnv::connect(self::$db);
        // a fresh install has adresser.mobile (includes/opretfunc.php); an old template may not
        $this->sql('ALTER TABLE adresser ADD COLUMN IF NOT EXISTS mobile varchar(15)');
        foreach (['shopify_customer_links', 'shopify_customer_exports', 'shopify_events', 'shopify_jobs', 'shopify_errors', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM adresser WHERE btrim(kontonr) ~ '^2900[0-9]{4}$' OR kontonr = '99999999' OR firmanavn LIKE 'SD792%'");
        // the test's own debtor group: a template need not have any given group
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art) SELECT 'SD792 Shopify', 92, 'DG' WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art = 'DG' AND kodenr = 92)");
        $this->sql("DELETE FROM settings WHERE var_grp = 'shopify' AND var_name LIKE 'customer_%'");
        $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify', 'customer_number_start', '29000000'),
            ('shopify', 'customer_group', '92'), ('shopify', 'customer_payment_terms', 'Kontant'), ('shopify', 'customer_payment_days', '0')");
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, customers_enabled) VALUES
            ('hml', 'hml-test.myshopify.com', true, true), ('hms', 'hms-test.myshopify.com', false, true)");
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            pg_close($this->conn);
        }
    }

    private function sql(string $sql, array $params = []): array
    {
        return ShopifyEnv::rows($this->conn, $sql, $params);
    }

    private function steps(array ...$steps): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_customers.php', [self::$db, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result']['results'];
    }

    /** Waits until another session of the test database holds $n advisory locks (the held child's), so the next run really meets them. */
    private function waitForAdvisoryLocks(int $n): void
    {
        for ($i = 0; $i < 100; $i++) {
            $held = (int)$this->sql("SELECT count(*) AS n FROM pg_locks l JOIN pg_database d ON d.oid = l.database
                WHERE l.locktype = 'advisory' AND l.granted AND d.datname = current_database() AND l.pid <> pg_backend_pid()")[0]['n'];
            if ($held >= $n) {
                return;
            }
            usleep(50000);
        }
        $this->fail("the held child never took its $n advisory locks");
    }

    private static function customer(int $id, array $fields = []): array
    {
        return $fields + ['id' => $id, 'admin_graphql_api_id' => "gid://shopify/Customer/$id", 'email' => "kunde$id@example.dk",
            'first_name' => 'Test', 'last_name' => "Kunde $id", 'phone' => '+4512345678', 'tags' => '', 'updated_at' => '2026-11-01T10:00:00+01:00',
            'default_address' => ['address1' => 'Havevej 1', 'address2' => '', 'zip' => '2750', 'city' => 'Ballerup', 'country' => 'Denmark', 'country_code' => 'DK', 'company' => '']];
    }

    private function debtors(): array
    {
        return $this->sql("SELECT a.id, a.kontonr, a.firmanavn, a.addr1, a.postnr, a.bynavn, a.land, a.email, a.tlf, a.mobile, a.gruppe, a.betalingsbet, a.betalingsdage
            FROM adresser a WHERE a.kontonr ~ '^2900[0-9]{4}$' ORDER BY a.kontonr");
    }

    private function links(): array
    {
        return $this->sql('SELECT s.shop_key, l.customer_gid, l.adresser_id, l.source_updated_at FROM shopify_customer_links l JOIN shopify_shops s ON s.id = l.shop_id ORDER BY l.id');
    }

    public function testANewShopifyCustomerBecomesADebtorWithTheNextNumber(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)]);

        $d = $this->debtors();
        $this->assertCount(1, $d);
        $this->assertSame(['29000000', 'Test Kunde 7', 'Havevej 1', '2750', 'Ballerup', 'Denmark', 'kunde7@example.dk'],
            [$d[0]['kontonr'], $d[0]['firmanavn'], $d[0]['addr1'], $d[0]['postnr'], $d[0]['bynavn'], $d[0]['land'], $d[0]['email']], 'K30: a løbenummer from the configured start');
        $this->assertSame(['+4512345678', '+4512345678'], [$d[0]['tlf'], $d[0]['mobile']], 'K30: the phone is searchable (tlf) and kept in mobile');
        $this->assertSame(['92', 'Kontant', '0'], [$d[0]['gruppe'], $d[0]['betalingsbet'], $d[0]['betalingsdage']], 'group and terms from the settings');
        $this->assertSame([['shop_key' => 'hml', 'customer_gid' => 'gid://shopify/Customer/7', 'adresser_id' => $d[0]['id'], 'source_updated_at' => '2026-11-01 09:00:00']], $this->links());
    }

    /** kontotype, fornavn, efternavn, firmanavn, kontakt of the first Shopify debtor */
    private function type(): array
    {
        return array_values($this->sql("SELECT kontotype, fornavn, efternavn, firmanavn, kontakt FROM adresser WHERE kontonr = '29000000'")[0]);
    }

    /** What the debtor card saves as the name of a private card (debitor/debitorkort.php: firmanavn = fornavn + efternavn). */
    private function cardName(): string
    {
        $r = $this->sql("SELECT fornavn, efternavn FROM adresser WHERE kontonr = '29000000'")[0];
        return trim($r['fornavn'] . ' ' . $r['efternavn']);
    }

    public function testAPersonIsAPrivateDebtorWhoseCardKeepsTheName(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)]);

        $this->assertSame(['privat', 'Test', 'Kunde 7', 'Test Kunde 7', ''], $this->type());
        $this->assertSame('Test Kunde 7', $this->cardName(), 'saving the private card in Saldi keeps the name');
    }

    public function testACompanyOnTheAddressIsABusinessDebtor(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7, ['first_name' => 'Anna', 'last_name' => 'Hansen',
            'default_address' => ['address1' => 'Havevej 1', 'zip' => '2750', 'city' => 'Ballerup', 'country_code' => 'DK', 'company' => 'Acme ApS']])]);

        $this->assertSame(['erhverv', '', '', 'Acme ApS', 'Anna Hansen'], $this->type());
    }

    public function testACompanyAddedOrRemovedInShopifyChangesTheType(): void
    {
        $acme = ['address1' => 'Havevej 1', 'zip' => '2750', 'city' => 'Ballerup', 'country_code' => 'DK', 'company' => 'Acme ApS'];
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)],
            ['job', 'hml', 'CUSTOMERS_UPDATE', self::customer(7, ['updated_at' => '2026-11-01T11:00:00+01:00', 'default_address' => $acme])]);
        $this->assertSame(['erhverv', 'Acme ApS', 'Test Kunde 7'], [$this->type()[0], $this->type()[3], $this->type()[4]], 'a company appeared: business, the person as contact');

        $this->steps(['job', 'hml', 'CUSTOMERS_UPDATE', ['id' => 7, 'admin_graphql_api_id' => 'gid://shopify/Customer/7', 'updated_at' => '2026-11-01T12:00:00+01:00', 'tags' => 'vip']]);
        $this->assertSame('erhverv', $this->type()[0], 'a body without a name or company leaves the type');

        $this->steps(['job', 'hml', 'CUSTOMERS_UPDATE', self::customer(7, ['updated_at' => '2026-11-01T13:00:00+01:00'])]);
        $this->assertSame(['privat', 'Test', 'Kunde 7', 'Test Kunde 7', ''], $this->type(), 'the company went: private again, with the names');
        $this->assertSame('Test Kunde 7', $this->cardName());
    }

    public function testALastNameRemovedInShopifyIsClearedSoTheCardKeepsTheName(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)],
            ['job', 'hml', 'CUSTOMERS_UPDATE', self::customer(7, ['updated_at' => '2026-11-01T11:00:00+01:00', 'last_name' => ''])]);

        $this->assertSame(['privat', 'Test', '', 'Test', ''], $this->type());
        $this->assertSame('Test', $this->cardName());
    }

    public function testANameOnlyOnTheAddressIsSplitAtTheLastSpace(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7, ['first_name' => '', 'last_name' => '',
            'default_address' => ['name' => 'Anna Marie  Hansen', 'address1' => 'Havevej 1', 'zip' => '2750', 'city' => 'Ballerup', 'country_code' => 'DK', 'company' => '']])]);

        $this->assertSame(['privat', 'Anna Marie', 'Hansen', 'Anna Marie Hansen', ''], $this->type());
        $this->assertSame('Anna Marie Hansen', $this->cardName());
    }

    public function testANamelessCustomerGetsNoType(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', ['id' => 7, 'admin_graphql_api_id' => 'gid://shopify/Customer/7', 'email' => 'anon@example.dk']]);

        $this->assertSame(['', '', '', 'anon@example.dk', ''], $this->type(), 'not privat: the card would rebuild the name from empty names');
    }

    public function testTwoCustomersWithTheSamePhoneAndAnExistingDebtorStaySeparate(): void
    {
        $this->sql("INSERT INTO adresser (kontonr, firmanavn, tlf, email, art) VALUES ('SD792X', 'SD792 Eksisterende', '+4512345678', 'kunde8@example.dk', 'D')");
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)], ['job', 'hml', 'CUSTOMERS_CREATE', self::customer(8)]);

        $this->assertSame(['29000000', '29000001'], array_column($this->debtors(), 'kontonr'), 'K29: two debtors, never matched on phone');
        $existing = $this->sql("SELECT firmanavn, tlf, email FROM adresser WHERE kontonr = 'SD792X'")[0];
        $this->assertSame(['SD792 Eksisterende', '+4512345678', 'kunde8@example.dk'], array_values($existing), 'K32: an existing debtor with the same email is neither matched nor changed');
    }

    public function testAReplayCreatesNothingNew(): void
    {
        $c = self::customer(7);
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', $c], ['job', 'hml', 'CUSTOMERS_CREATE', $c], ['job', 'hml', 'CUSTOMERS_UPDATE', $c]);

        $this->assertCount(1, $this->debtors());
        $this->assertCount(1, $this->links());
    }

    public function testAnAddressChangeUpdatesTheDebtorAndLeavesTheLocalPhone(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)]);
        $this->sql("UPDATE adresser SET tlf = '44 55 66 77' WHERE kontonr = '29000000'");
        $moved = self::customer(7, ['phone' => '+4587654321', 'updated_at' => '2026-11-01T11:00:00+01:00',
            'default_address' => ['address1' => 'Strandvejen 9', 'zip' => '2900', 'city' => 'Hellerup', 'country' => 'Denmark']]);
        $this->steps(['job', 'hml', 'CUSTOMERS_UPDATE', $moved]);

        $d = $this->debtors()[0];
        $this->assertSame(['Strandvejen 9', '2900', 'Hellerup', '+4587654321'], [$d['addr1'], $d['postnr'], $d['bynavn'], $d['mobile']], 'K28');
        $this->assertSame('44 55 66 77', $d['tlf'], 'the locally typed tlf is never overwritten');
    }

    public function testAnOlderEventChangesNothingAlsoAfterAnUnchangedNewerOne(): void
    {
        $at = fn(string $time, string $street): array => self::customer(7, ['updated_at' => "2026-11-01T$time+01:00",
            'default_address' => ['address1' => $street, 'zip' => '2750', 'city' => 'Ballerup', 'country' => 'Denmark']]);
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', $at('10:00:00', 'A-vej 1')], ['job', 'hml', 'CUSTOMERS_UPDATE', $at('12:00:00', 'A-vej 1')],
            ['job', 'hml', 'CUSTOMERS_UPDATE', $at('11:00:00', 'B-vej 2')]);

        $this->assertSame('A-vej 1', $this->debtors()[0]['addr1'], 'K41: the 11:00 event arrived last but is older than the 12:00 one');
    }

    public function testConcurrentCreatesGetDistinctNumbers(): void
    {
        $first = ShopifyEnv::startChild('run_customers.php', [self::$db, json_encode([['hold', 'hml', self::customer(7), 2]])]);
        $this->waitForAdvisoryLocks(2);
        $second = $this->steps(['hold', 'hml', self::customer(8), 0]);
        $held = ShopifyEnv::waitChild($first);

        $this->assertSame(0, $held['exit'], $held['stderr']);
        $this->assertSame(['29000000', '29000001'], array_column($this->debtors(), 'kontonr'), 'the second waits on the number lock until the first commits');
        $this->assertIsInt($second[0]);
    }

    public function testTwoEventsOfOneNewCustomerCreateOneDebtor(): void
    {
        $first = ShopifyEnv::startChild('run_customers.php', [self::$db, json_encode([['hold', 'hml', self::customer(7), 2]])]);
        $this->waitForAdvisoryLocks(2);
        $this->steps(['hold', 'hml', self::customer(7, ['updated_at' => '2026-11-01T10:00:05+01:00']), 0]);
        ShopifyEnv::waitChild($first);

        $this->assertCount(1, $this->debtors());
        $this->assertCount(1, $this->links());
    }

    public function testANumberAnotherCreatorCommitsWhileTheWorkerWaitsIsNotTakenOver(): void
    {
        // as ExternalPaidInvoiceImportService: the table lock, then a number no other session sees until the commit
        $import = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($import, 'BEGIN');
        ShopifyEnv::rows($import, 'LOCK TABLE adresser IN EXCLUSIVE MODE');
        ShopifyEnv::rows($import, "INSERT INTO adresser (kontonr, firmanavn, email, art) VALUES ('29000000', 'SD792 Import', 'import@example.dk', 'D')");
        $worker = ShopifyEnv::startChild('run_customers.php', [self::$db, json_encode([['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)]])]);
        for ($i = 0; $i < 100 && !$this->sql("SELECT 1 FROM pg_locks l JOIN pg_class c ON c.oid = l.relation JOIN pg_database d ON d.oid = l.database
            WHERE d.datname = current_database() AND c.relname = 'adresser' AND NOT l.granted"); $i++) {
            usleep(50000);
        }
        $this->assertLessThan(100, $i, 'the worker never waited on the table lock');
        ShopifyEnv::rows($import, 'COMMIT');
        pg_close($import);
        $run = ShopifyEnv::waitChild($worker);

        $this->assertSame('DebtorNumberTakenException', $run['result']['results'][0]['exception'] ?? null, $run['stderr'] . $run['stdout']);
        $this->assertSame([['firmanavn' => 'SD792 Import', 'email' => 'import@example.dk']],
            $this->sql("SELECT firmanavn, email FROM adresser WHERE kontonr = '29000000'"), 'the other debtor keeps its data and its number');
        $this->assertSame([], $this->links());

        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)]);
        $this->assertSame(['29000000', '29000001'], array_column($this->debtors(), 'kontonr'), 'the retry takes the next number');
    }

    public function testEachShopLinksItsOwnCustomer(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)], ['job', 'hms', 'CUSTOMERS_CREATE', self::customer(9)]);

        $this->assertSame(['hml', 'hms'], array_column($this->links(), 'shop_key'));
        $this->assertCount(2, $this->debtors(), 'a customer in another shop is another customer: no match across shops either');
    }

    public function testAnOpenOutboundCreateIsAdoptedInsteadOfCreatingADebtor(): void
    {
        $saldi = (int)$this->sql("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('SD792B', 'SD792 Butikskunde', 'D') RETURNING id")[0]['id'];
        $this->sql("INSERT INTO shopify_customer_exports (shop_id, adresser_id, state, correlation_tag) SELECT id, $1, 'create_started', 'saldi-kunde-abc' FROM shopify_shops WHERE shop_key = 'hml'", [$saldi]);
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7, ['tags' => 'vip, saldi-kunde-abc'])]);

        $this->assertSame([], $this->debtors(), 'no new debtor');
        $this->assertSame((string)$saldi, $this->links()[0]['adresser_id']);
        $this->assertSame('linked', $this->sql('SELECT state FROM shopify_customer_exports')[0]['state']);
        $this->assertSame('SD792 Butikskunde', $this->sql('SELECT firmanavn FROM adresser WHERE id = $1', [$saldi])[0]['firmanavn'], 'the store debtor keeps its own data');
    }

    public function testADeletedDebtorIsCreatedAgainAndOtherTopicsAreSkipped(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)]);
        $this->sql("DELETE FROM adresser WHERE kontonr = '29000000'");
        $this->steps(['job', 'hml', 'CUSTOMERS_DELETE', ['id' => 7]], ['job', 'hml', 'CUSTOMERS_UPDATE', self::customer(7, ['updated_at' => '2026-11-01T11:00:00+01:00'])]);

        $this->assertSame(['29000000'], array_column($this->debtors(), 'kontonr'), 'the delete topic carries no customer and is skipped; the update relinks a new debtor');
        $this->assertSame($this->debtors()[0]['id'], $this->links()[0]['adresser_id']);
    }

    public function testAValueEndingInABackslashIsStoredAndDoesNotStopTheWorker(): void
    {
        $c = self::customer(7, ['last_name' => 'Kunde\\', 'default_address' => ['address1' => 'Havevej 1', 'address2' => 'a;b', 'zip' => '2750', 'city' => 'Ballerup', 'country' => 'Denmark']]);
        $r = $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', $c]);

        $this->assertSame(['outcome' => 'done'], $r[0]);
        $d = $this->sql("SELECT firmanavn, addr2 FROM adresser WHERE kontonr = '29000000'")[0];
        $this->assertSame(['Test Kunde\\', 'a;b'], [$d['firmanavn'], $d['addr2']], 'customer text is bound, never through db_modify(), whose injection check misreads a trailing backslash');
    }

    public function testAnExportOfADebtorLinkedToAnotherCustomerIsNotAdopted(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)]);
        $linked = (int)$this->debtors()[0]['id'];
        $this->sql("INSERT INTO shopify_customer_exports (shop_id, adresser_id, state, correlation_tag) SELECT id, $1, 'pending', 'saldi-kunde-x' FROM shopify_shops WHERE shop_key = 'hml'", [$linked]);
        $r = $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(8, ['tags' => 'saldi-kunde-x'])]);

        $this->assertSame(['outcome' => 'done'], $r[0], 'linking it would break the one-customer-per-debtor key on every retry');
        $this->assertCount(2, $this->debtors());
    }

    public function testAnEventWithoutATimeKeepsTheStoredTime(): void
    {
        $at = fn(?string $time, string $street): array => self::customer(7, ['updated_at' => $time,
            'default_address' => ['address1' => $street, 'zip' => '2750', 'city' => 'Ballerup', 'country' => 'Denmark']]);
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', $at('2026-11-01T12:00:00+01:00', 'A-vej 1')], ['job', 'hml', 'CUSTOMERS_UPDATE', $at(null, 'A-vej 1')],
            ['job', 'hml', 'CUSTOMERS_UPDATE', $at('2026-11-01T11:00:00+01:00', 'B-vej 2')]);

        $this->assertSame('2026-11-01 11:00:00', $this->links()[0]['source_updated_at']);
        $this->assertSame('A-vej 1', $this->debtors()[0]['addr1'], 'the 11:00 event is older than the stored 12:00, also after an event without a time');
    }

    public function testPaymentTermsLongerThanTheColumnAreAConfigurationError(): void
    {
        $this->sql("UPDATE settings SET var_value = 'Netto 30 dage kontant' WHERE var_grp = 'shopify' AND var_name = 'customer_payment_terms'");
        $r = $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)]);

        $this->assertSame('ShopifyConfigError', $r[0]['exception']);
        $this->assertStringContainsString('customer_payment_terms', $r[0]['message']);
    }

    public function testAConfiguredGroupMustBeADebtorGroup(): void
    {
        $this->sql("UPDATE settings SET var_value = '98' WHERE var_grp = 'shopify' AND var_name = 'customer_group'");
        $this->sql("DELETE FROM grupper WHERE art = 'DG' AND kodenr = 98");
        $r = $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)]);

        $this->assertSame('ShopifyConfigError', $r[0]['exception']);
        $this->assertSame([], $this->debtors());
    }

    public function testANumberAtTheTopOfTheRangeLeavesTheFirstFreeNumber(): void
    {
        $this->sql("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('99999999', 'SD792 Outlier', 'K'), ('29000000', 'SD792 Taget', 'D')");
        $r = $this->steps(['number'], ['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)], ['job', 'hml', 'CUSTOMERS_CREATE', self::customer(8)]);

        $this->assertSame('29000001', $r[0], 'max + 1 is past 8 digits: the first free number from the start');
        $this->assertSame(['29000000', '29000001', '29000002'], array_column($this->debtors(), 'kontonr'));
    }

    public function testATooLongEmailIsLeftOutAndListed(): void
    {
        $email = str_repeat('a', 70) . '@example.dk';
        $r = $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7, ['email' => $email])]);

        $this->assertSame(['outcome' => 'done'], $r[0], 'the column is 60 wide on a fresh install: storing it would fail every retry');
        $this->assertSame('', $this->debtors()[0]['email'], 'not cut: a cut email is another address');
        $this->assertSame(['customer.email', 'warning'], array_values($this->sql("SELECT kind, severity FROM shopify_errors WHERE resolved_at IS NULL")[0]));

        $this->steps(['job', 'hml', 'CUSTOMERS_UPDATE', self::customer(7, ['updated_at' => '2026-11-01T11:00:00+01:00'])],
            ['job', 'hml', 'CUSTOMERS_UPDATE', self::customer(7, ['email' => $email, 'updated_at' => '2026-11-01T12:00:00+01:00', 'phone' => '+4587654321'])]);
        $this->assertSame(['kunde7@example.dk', '+4587654321'], [$this->debtors()[0]['email'], $this->debtors()[0]['mobile']], 'a too long email later leaves the stored one');
    }

    public function testAnExportOfADeletedDebtorIsNotAdopted(): void
    {
        $gone = (int)$this->sql("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('SD792D', 'SD792 Slettet', 'D') RETURNING id")[0]['id'];
        $this->sql("INSERT INTO shopify_customer_exports (shop_id, adresser_id, state, correlation_tag) SELECT id, $1, 'pending', 'saldi-kunde-d' FROM shopify_shops WHERE shop_key = 'hml'", [$gone]);
        $this->sql('DELETE FROM adresser WHERE id = $1', [$gone]);
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7, ['tags' => 'saldi-kunde-d'])]);

        $this->assertSame($this->debtors()[0]['id'], $this->links()[0]['adresser_id'], 'a new debtor, not the deleted one');
    }

    public function testACityLookedUpFromThePostnrIsKeptByLaterUpdates(): void
    {
        $noCity = fn(string $phone, string $time): array => self::customer(7, ['phone' => $phone, 'updated_at' => "2026-11-01T$time+01:00",
            'default_address' => ['address1' => 'Havevej 1', 'zip' => '2750', 'city' => '', 'country' => 'Denmark', 'country_code' => 'DK']]);
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', $noCity('+4512345678', '10:00:00')], ['job', 'hml', 'CUSTOMERS_UPDATE', $noCity('+4587654321', '11:00:00')],
            ['job', 'hml', 'CUSTOMERS_CREATE', self::customer(8, ['default_address' => ['address1' => 'Storgata 1', 'zip' => '2100', 'city' => '', 'country' => 'Norway', 'country_code' => 'NO']])]);

        $this->assertSame(['Ballerup', '+4587654321'], [$this->debtors()[0]['bynavn'], $this->debtors()[0]['mobile']]);
        $this->assertSame('', $this->debtors()[1]['bynavn'], 'the postnr file is Danish: a Norwegian 2100 is not København Ø');
    }

    public function testAnUpdateWithoutAValueLeavesTheStoredOne(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)]);
        $this->sql("UPDATE adresser SET kontakt = 'Jens' WHERE kontonr = '29000000'");
        $bare = ['id' => 7, 'admin_graphql_api_id' => 'gid://shopify/Customer/7', 'first_name' => 'Test', 'last_name' => 'Kunde 7', 'phone' => null, 'email' => null,
            'updated_at' => '2026-11-01T11:00:00+01:00', 'default_address' => ['address1' => 'Strandvejen 9', 'zip' => '2900', 'city' => 'Hellerup', 'country_code' => 'DK']];
        $this->steps(['job', 'hml', 'CUSTOMERS_UPDATE', $bare]);

        $d = $this->sql("SELECT addr1, kontakt, mobile, email FROM adresser WHERE kontonr = '29000000'")[0];
        $this->assertSame(['Strandvejen 9', 'Jens', '+4512345678', 'kunde7@example.dk'], array_values($d),
            'a body without protected customer data (no phone, no email) does not wipe what the debtor has');
    }

    public function testACustomerWithTheTagsOfTwoOpenExportsIsListed(): void
    {
        foreach (['SD792E1', 'SD792E2'] as $nr) {
            $id = (int)$this->sql("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ($1, 'SD792 Eksport', 'D') RETURNING id", [$nr])[0]['id'];
            $this->sql("INSERT INTO shopify_customer_exports (shop_id, adresser_id, state, correlation_tag) SELECT id, $1, 'pending', $2 FROM shopify_shops WHERE shop_key = 'hml'", [$id, "tag-$nr"]);
        }
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7, ['tags' => 'tag-SD792E1, tag-SD792E2'])]);

        $this->assertCount(1, $this->debtors(), 'a new debtor: which export it is cannot be told');
        $this->assertSame(['customer.export'], array_column($this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'kind'));
    }

    public function testAnAccountNumberWithSpacesCountsAsInUse(): void
    {
        $this->sql("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('29000005 ', 'SD792 Mellemrum', 'D')");

        $this->assertSame('29000006', $this->steps(['number'])[0], 'a number with surrounding spaces counts as in use');
    }

    public function testALateOlderEventForADeletedDebtorDoesNotTurnTheClockBack(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7, ['updated_at' => '2026-11-01T12:00:00+01:00'])]);
        $this->sql("DELETE FROM adresser WHERE kontonr = '29000000'");
        $this->steps(['job', 'hml', 'CUSTOMERS_UPDATE', self::customer(7, ['updated_at' => '2026-11-01T11:00:00+01:00'])]);

        $this->assertCount(1, $this->debtors(), 'the customer gets a debtor again');
        $this->assertSame('2026-11-01 11:00:00', $this->links()[0]['source_updated_at'], 'the stored 12:00 (UTC 11:00) stays, so an 11:30 event later is still older');
    }

    public function testANamelessUpdateKeepsTheNameAndACompanyRemovedClearsTheContact(): void
    {
        $company = ['address1' => 'Havevej 1', 'zip' => '2750', 'city' => 'Ballerup', 'country_code' => 'DK', 'company' => 'Havemøbler ApS'];
        $r = $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7, ['default_address' => $company])],
            ['job', 'hml', 'CUSTOMERS_UPDATE', ['id' => 7, 'admin_graphql_api_id' => 'gid://shopify/Customer/7', 'updated_at' => '2026-11-01T11:00:00+01:00', 'tags' => 'vip']]);
        $this->assertSame(['outcome' => 'done'], $r[1], 'nothing to write is no statement at all');
        $this->assertSame('2026-11-01 10:00:00', $this->links()[0]['source_updated_at'], 'and its time still counts');
        $this->assertSame(['Havemøbler ApS', 'Test Kunde 7'], array_values($this->sql("SELECT firmanavn, kontakt FROM adresser WHERE kontonr = '29000000'")[0]),
            'no stand-in name over a real one');

        $this->steps(['job', 'hml', 'CUSTOMERS_UPDATE', self::customer(7, ['updated_at' => '2026-11-01T12:00:00+01:00'])]);
        $this->assertSame(['Test Kunde 7', ''], array_values($this->sql("SELECT firmanavn, kontakt FROM adresser WHERE kontonr = '29000000'")[0]),
            'the company became a person: the contact, now the name itself, goes');

        $this->sql("UPDATE adresser SET kontakt = 'Jens', firmanavn = 'Test Kunde 7 (VIP)' WHERE kontonr = '29000000'");
        $this->steps(['job', 'hml', 'CUSTOMERS_UPDATE', self::customer(7, ['updated_at' => '2026-11-01T13:00:00+01:00', 'phone' => '+4587654321'])]);
        $this->assertSame('Jens', $this->sql("SELECT kontakt FROM adresser WHERE kontonr = '29000000'")[0]['kontakt'], 'a contact typed in Saldi stays');
    }

    public function testANamelessNewCustomerIsNamedByEmailElseItsId(): void
    {
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', ['id' => 7, 'admin_graphql_api_id' => 'gid://shopify/Customer/7', 'email' => 'anon@example.dk']],
            ['job', 'hml', 'CUSTOMERS_CREATE', ['id' => 8, 'admin_graphql_api_id' => 'gid://shopify/Customer/8']]);

        $this->assertSame(['anon@example.dk', 'Shopify 8'], array_column($this->debtors(), 'firmanavn'));
    }

    public function testAShopSwitchedOffAfterQueueingGetsNoDebtor(): void
    {
        $this->sql("UPDATE shopify_shops SET customers_enabled = false WHERE shop_key = 'hml'");
        $r = $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', self::customer(7)]);

        $this->assertSame(['outcome' => 'done'], $r[0]);
        $this->assertSame([], $this->debtors());
    }

    public function testACompanysContactTypedInSaldiStays(): void
    {
        $acme = fn(string $phone, string $time): array => self::customer(7, ['phone' => $phone, 'updated_at' => "2026-11-01T$time+01:00",
            'first_name' => 'Anna', 'last_name' => 'Hansen', 'default_address' => ['address1' => 'Havevej 1', 'zip' => '2750', 'city' => 'Ballerup', 'country_code' => 'DK', 'company' => 'Acme ApS']]);
        $this->steps(['job', 'hml', 'CUSTOMERS_CREATE', $acme('+4512345678', '10:00:00')]);
        $this->assertSame('Anna Hansen', $this->sql("SELECT kontakt FROM adresser WHERE kontonr = '29000000'")[0]['kontakt']);

        $this->sql("UPDATE adresser SET kontakt = 'Jens (lokal)' WHERE kontonr = '29000000'");
        $this->steps(['job', 'hml', 'CUSTOMERS_UPDATE', $acme('+4587654321', '11:00:00')]);
        $this->assertSame(['Jens (lokal)', '+4587654321'], array_values($this->sql("SELECT kontakt, mobile FROM adresser WHERE kontonr = '29000000'")[0]),
            'a contact is Saldi\'s once set: an update of the phone keeps it');

        $this->sql("UPDATE adresser SET kontakt = '' WHERE kontonr = '29000000'");
        $this->steps(['job', 'hml', 'CUSTOMERS_UPDATE', $acme('+4511112222', '12:00:00')]);
        $this->assertSame('Anna Hansen', $this->sql("SELECT kontakt FROM adresser WHERE kontonr = '29000000'")[0]['kontakt'], 'an empty contact is filled from Shopify');
    }

    public function testTheAllocatorNeedsATransactionAndStartsAtTheConfiguredNumber(): void
    {
        $r = $this->steps(['number_outside'], ['number']);

        $this->assertSame('ShopifyDbMisuse', $r[0]['exception']);
        $this->assertSame('29000000', $r[1]);
    }

    public function testCreateDebtorThrowsForATakenNumberAndEscapesThePaymentTerms(): void
    {
        $r = $this->steps(['create_debtor', '29001000', '2', "Netto 8'"], ['create_debtor', '29001000', '2', 'Netto']);

        $this->assertGreaterThan(0, (int)$r[0]['id']);
        $this->assertTrue($r[0]['idle'], 'from the order page (no transaction) it commits its own');
        $this->assertSame("Netto 8'", $this->sql("SELECT betalingsbet FROM adresser WHERE kontonr = '29001000'")[0]['betalingsbet'], 'betalingsbet is escaped');
        $this->assertSame(['DebtorNumberTakenException', 'Kontonr 29001000 er ikke ledigt!', true], [$r[1]['exception'], $r[1]['message'], $r[1]['idle']],
            'the page shows this message in its catch; no transaction is left open');
    }

    public function testCreateDebtorHeadlessLooksUpTheCityAndThrowsOnAFailedInsert(): void
    {
        $r = $this->steps(['create_debtor', '29001001', '2', 'Netto', true, ''], ['create_debtor', '29001002', 'ikke-et-tal', 'Netto', true, 'Ballerup', false],
            ['create_debtor', '29001003', 'ikke-et-tal', 'Netto', false]);

        $this->assertSame('Ballerup', $this->sql("SELECT bynavn FROM adresser WHERE kontonr = '29001001'")[0]['bynavn'], 'the city of a postnr is looked up as on the page');
        $this->assertSame('RuntimeException', $r[1]['exception'], 'headless: a failed insert throws, also without webservice mode, where db_modify() would alert and exit');
        $this->assertSame('RuntimeException', $r[2]['exception'], 'in webservice mode a failed insert throws instead of returning no id');
        $this->assertTrue($r[1]['idle']);
        $this->assertSame([], $this->sql("SELECT id FROM adresser WHERE kontonr = '29001002'"));
    }
}
