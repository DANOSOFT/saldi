<?php
// tests/shopify/CustomersOutSyncTest.php
//
// Outbound customers against a cloned tenant and the shared fake store (SD-803): a debtor becomes a
// customer with its correlation tag, and the webhook of our own create neither makes a second
// debtor nor changes this one (K31). An email another customer has parks the export; a phone
// another customer has is left out with an info row. A lost create response is looked up, never
// sent twice, and a create that can only be found by its tag waits until the search caught up.
// A job that died after its create finds the customer by its tag. A later update of an exported
// debtor's customer leaves the name Saldi gave it. A parked export is looked up before any create.
//
// History:
// 20261108 CL/SS SD-803: created.
// 20261109 CL/SS SD-803 review: an email field with several addresses survives an update; the phone info goes with a later create that has the phone.
// 20261109 CL/SS SD-814: an exported debtor's update without a company leaves its customer type and names.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class CustomersOutSyncTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';
    /** @var resource|\PgSql\Connection|null */
    private $conn = null;
    private string $state = '';

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_y');
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
        $this->state = tempnam(sys_get_temp_dir(), 'sd803_');
        $this->sql('ALTER TABLE adresser ADD COLUMN IF NOT EXISTS mobile varchar(15)');
        foreach (['shopify_customer_links', 'shopify_customer_exports', 'shopify_events', 'shopify_jobs', 'shopify_errors', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM adresser WHERE kontonr LIKE 'SD803%' OR btrim(kontonr) ~ '^2900[0-9]{4}$'");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art) SELECT 'SD803 Shopify', 93, 'DG' WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art = 'DG' AND kodenr = 93)");
        $this->sql("DELETE FROM settings WHERE var_grp = 'shopify' AND var_name LIKE 'customer_%'");
        $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify', 'customer_number_start', '29000000'), ('shopify', 'customer_group', '93')");
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, customers_enabled) VALUES
            ('hml', 'hml-fake.myshopify.com', true, true), ('off', 'off-fake.myshopify.com', false, false)");
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            pg_close($this->conn);
        }
        if ($this->state !== '' && is_file($this->state)) {
            unlink($this->state);
        }
    }

    private function sql(string $sql, array $params = []): array
    {
        return ShopifyEnv::rows($this->conn, $sql, $params);
    }

    private function steps(array ...$steps): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_customers_out.php', [self::$db, $this->state, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'];
    }

    private function debtor(string $kontonr, array $fields = []): int
    {
        $fields += ['firmanavn' => "SD803 Kunde $kontonr", 'kontakt' => '', 'email' => strtolower($kontonr) . '@example.dk', 'tlf' => '', 'mobile' => '', 'land' => 'Danmark', 'art' => 'D'];
        $cols = implode(', ', array_keys($fields));
        $marks = implode(', ', array_map(fn(int $i): string => '$' . ($i + 2), array_keys(array_values($fields))));
        return (int)$this->sql("INSERT INTO adresser (kontonr, $cols) VALUES ($1, $marks) RETURNING id", array_merge([$kontonr], array_values($fields)))[0]['id'];
    }

    private function export(int $id): ?array
    {
        return $this->sql('SELECT state, correlation_tag FROM shopify_customer_exports WHERE adresser_id = $1', [$id])[0] ?? null;
    }

    private function tag(int $id): string
    {
        return 'saldi-deb-' . self::$db . "-$id";
    }

    private static function creates(array $run): int
    {
        return count(array_filter($run['calls'], fn(array $c): bool => $c['operation'] === 'customerCreate'));
    }

    public function testADebtorBecomesACustomerAndTheWebhookOfOurCreateChangesNothing(): void
    {
        $id = $this->debtor('SD803A', ['firmanavn' => 'SD803 Firma ApS', 'kontakt' => 'Hans Peter Jensen', 'tlf' => '12 34 56 78']);

        $r = $this->steps(['enqueue', 'hml', $id], ['job', 'hml', $id], ['echo', 'hml', $id], ['enqueue', 'hml', $id]);

        $this->assertTrue($r['results'][0]['created']);
        $this->assertSame(['outcome' => 'done'], $r['results'][1]);
        $this->assertSame([['first_name' => 'Hans Peter', 'last_name' => 'Jensen', 'email' => 'sd803a@example.dk', 'phone' => '+4512345678', 'tags' => [$this->tag($id)]]],
            array_map(fn(array $c): array => array_diff_key($c, ['gid' => 1]), $r['customers']['hml']), 'K31: the contact person, the email, the phone in E.164 and the tag');
        $gid = $r['customers']['hml'][0]['gid'];
        $this->assertSame([['customer_gid' => $gid, 'adresser_id' => (string)$id]], $this->sql('SELECT customer_gid, adresser_id FROM shopify_customer_links'));
        $this->assertSame('linked', $this->export($id)['state']);
        $this->assertSame(['SD803 Firma ApS', 'Hans Peter Jensen', '12 34 56 78'], array_values($this->sql('SELECT firmanavn, kontakt, tlf FROM adresser WHERE id = $1', [$id])[0]),
            'the webhook of our own create changes nothing on the debtor');
        $this->assertSame([], $this->sql("SELECT id FROM adresser WHERE btrim(kontonr) ~ '^2900[0-9]{4}$'"), 'and makes no second debtor');
        $this->assertNull($r['results'][3], 'a debtor with a customer is not queued again');
        $this->assertSame(1, self::creates($r));
    }

    public function testTheInboundWebhookAdoptsTheExportWhenItArrivesFirst(): void
    {
        $id = $this->debtor('SD803B');
        $this->steps(['enqueue', 'hml', $id], ['job', 'hml', $id]);
        $this->sql('DELETE FROM shopify_customer_links');
        $this->sql("UPDATE shopify_customer_exports SET state = 'create_started'");

        $r = $this->steps(['echo', 'hml', $id], ['job', 'hml', $id]);

        $this->assertSame([['adresser_id' => (string)$id]], $this->sql('SELECT adresser_id FROM shopify_customer_links'), 'the inbound side links the customer to our debtor by its tag');
        $this->assertSame([], $this->sql("SELECT id FROM adresser WHERE btrim(kontonr) ~ '^2900[0-9]{4}$'"), 'no second debtor');
        $this->assertSame(['outcome' => 'done'], $r['results'][1]);
        $this->assertSame('linked', $this->export($id)['state']);
        $this->assertSame(0, self::creates($r), 'the job finds the link and sends nothing');
    }

    public function testAnEmailTakenParksAndAPhoneTakenCreatesWithoutThePhone(): void
    {
        $email = $this->debtor('SD803C', ['email' => 'taget@example.dk']);
        $phone = $this->debtor('SD803D', ['mobile' => '+4511223344']);

        $r = $this->steps(['remote', 'hml', ['firstName' => 'Anden', 'email' => 'taget@example.dk', 'phone' => '+4511223344']],
            ['enqueue', 'hml', $email], ['job', 'hml', $email], ['enqueue', 'hml', $phone], ['job', 'hml', $phone]);

        $this->assertSame(['outcome' => 'done'], $r['results'][2], 'parking ends the job');
        $this->assertSame('parked', $this->export($email)['state']);
        $this->assertCount(2, $r['customers']['hml'], 'no customer for the debtor whose email is taken; one without the phone for the other');
        $this->assertSame([null, 'sd803d@example.dk', [$this->tag($phone)]],
            [$r['customers']['hml'][1]['phone'], $r['customers']['hml'][1]['email'], $r['customers']['hml'][1]['tags']]);
        $errors = $this->sql("SELECT kind, ref, severity FROM shopify_errors WHERE resolved_at IS NULL ORDER BY kind");
        $this->assertSame([['kind' => 'customer.export', 'ref' => "adresser:$email", 'severity' => 'error'], ['kind' => 'customer.phone', 'ref' => "adresser:$phone", 'severity' => 'info']], $errors);
        $this->assertSame([], $this->sql('SELECT 1 FROM shopify_customer_links WHERE adresser_id = $1', [$email]), 'no automatic association with the customer who has the email');

        $this->sql("UPDATE adresser SET email = 'ny@example.dk' WHERE id = $1", [$email]);
        $r = $this->steps(['enqueue', 'hml', $email], ['job', 'hml', $email]);
        $this->assertSame('linked', $this->export($email)['state'], 'a parked export goes again with the next order once the email is corrected');
        $this->assertSame([], $this->sql("SELECT 1 FROM shopify_errors WHERE resolved_at IS NULL AND kind = 'customer.export'"));
    }

    public function testAnInboundLinkAfterAParkedEmailResolvesItsError(): void
    {
        $id = $this->debtor('SD803K', ['email' => 'optaget@example.dk']);
        $this->steps(['remote', 'hml', ['firstName' => 'Anden', 'email' => 'optaget@example.dk']], ['enqueue', 'hml', $id], ['job', 'hml', $id]);
        $this->sql("UPDATE adresser SET email = 'rettet@example.dk' WHERE id = $1", [$id]);
        $this->steps(['enqueue', 'hml', $id]);
        // the corrected create reached Shopify and its answer was lost; its webhook arrives before the job runs again
        $this->sql("UPDATE shopify_customer_exports SET state = 'create_started', attempt_at = now()");

        $r = $this->steps(['remote', 'hml', ['firstName' => 'SD803', 'email' => 'rettet@example.dk', 'tags' => [$this->tag($id)]]], ['echo', 'hml', $id], ['job', 'hml', $id]);

        $this->assertSame(['outcome' => 'done'], $r['results'][2]);
        $this->assertSame('linked', $this->export($id)['state'], 'the webhook linked it first');
        $this->assertSame([], $this->sql("SELECT kind FROM shopify_errors WHERE resolved_at IS NULL"), 'and the job resolves the parked email\'s error');
    }

    public function testThePhoneInfoSurvivesALostAnswerOfTheCreateWithoutIt(): void
    {
        $id = $this->debtor('SD803L', ['email' => '', 'mobile' => '+4599887766']);
        $this->steps(['remote', 'hml', ['firstName' => 'Anden', 'phone' => '+4599887766']], ['enqueue', 'hml', $id]);

        $r = $this->steps(['job_lose', 'hml', $id, 2, 9]);
        $this->assertSame('yield', $r['results'][0]['outcome'], 'the create without the phone was applied, its answer lost and the search down');
        $this->sql("UPDATE shopify_customer_exports SET attempt_at = now()");
        $r = $this->steps(['job', 'hml', $id]);

        $this->assertSame('linked', $this->export($id)['state']);
        $this->assertSame([['kind' => 'customer.phone', 'severity' => 'info']], $this->sql('SELECT kind, severity FROM shopify_errors WHERE resolved_at IS NULL'),
            'found later, still with its info row');
    }

    public function testALinkedExportWhoseLinkIsGoneFindsItsCustomerAgain(): void
    {
        $tagOnly = $this->debtor('SD803M', ['email' => '']);
        $withEmail = $this->debtor('SD803N');
        $this->steps(['enqueue', 'hml', $tagOnly], ['job', 'hml', $tagOnly], ['enqueue', 'hml', $withEmail], ['job', 'hml', $withEmail]);
        $before = $this->sql('SELECT adresser_id, customer_gid FROM shopify_customer_links ORDER BY adresser_id');
        $this->sql('DELETE FROM shopify_customer_links');

        $r = $this->steps(['enqueue', 'hml', $tagOnly], ['job', 'hml', $tagOnly], ['enqueue', 'hml', $withEmail], ['job', 'hml', $withEmail]);

        $this->assertSame(0, self::creates($r), 'both customers exist and carry their tags: found, not created again');
        $this->assertCount(2, $r['customers']['hml']);
        $this->assertSame($before, $this->sql('SELECT adresser_id, customer_gid FROM shopify_customer_links ORDER BY adresser_id'), 'linked to the same customers');
        $this->assertSame(['linked', 'linked'], [$this->export($tagOnly)['state'], $this->export($withEmail)['state']], 'the one with an email is not parked on its own customer');
    }

    public function testAQueueWhileTheJobRunsQueuesAnotherBehindIt(): void
    {
        $id = $this->debtor('SD803O', ['email' => 'travl@example.dk']);
        $this->steps(['remote', 'hml', ['firstName' => 'Anden', 'email' => 'travl@example.dk']], ['enqueue', 'hml', $id]);
        // the worker has claimed the job; it parks the export on the taken email while the debtor is corrected and queued again
        $this->sql("UPDATE shopify_jobs SET state = 'running' WHERE type = 'customer.export'");
        $this->steps(['job', 'hml', $id]);
        $this->sql("UPDATE adresser SET email = 'ledig@example.dk' WHERE id = $1", [$id]);

        $r = $this->steps(['enqueue', 'hml', $id]);

        $this->assertTrue($r['results'][0]['created'], 'the running job read the export before it was reopened');
        $this->assertSame([['state' => 'queued']], $this->sql("SELECT state FROM shopify_jobs WHERE type = 'customer.export' AND id = $1", [$r['results'][0]['id']]));
        $this->assertSame('create_started', $this->export($id)['state']);
    }

    public function testAnUpdateFromShopifyLeavesAnExportedDebtorsNameUnlessTheAddressHasACompany(): void
    {
        $company = $this->debtor('SD803Q', ['firmanavn' => 'SD803 Firma ApS', 'kontakt' => 'Hans Peter Jensen', 'tlf' => '12 34 56 78',
            'email' => 'bogholderi@acme.dk;jens@acme.dk', 'kontotype' => 'erhverv']);
        $person = $this->debtor('SD803R', ['firmanavn' => 'Bo Hansen', 'kontotype' => 'privat', 'fornavn' => 'Bo', 'efternavn' => 'Hansen']);
        $this->steps(['enqueue', 'hml', $company], ['job', 'hml', $company], ['echo', 'hml', $company], ['enqueue', 'hml', $person], ['job', 'hml', $person], ['echo', 'hml', $person]);

        // the customers add a phone and correct the name in Shopify
        $r = $this->steps(['echo', 'hml', $company, ['phone' => '+4587654321', 'updated_at' => '2030-01-01T10:00:00Z']],
            ['echo', 'hml', $person, ['first_name' => 'Bo', 'last_name' => 'Hansen-Larsen', 'updated_at' => '2030-01-01T10:00:00Z']]);

        $this->assertSame(['outcome' => 'done'], $r['results'][0]);
        $this->assertSame(['SD803 Firma ApS', 'Hans Peter Jensen', '+4587654321', 'bogholderi@acme.dk;jens@acme.dk'],
            array_values($this->sql('SELECT firmanavn, kontakt, mobile, email FROM adresser WHERE id = $1', [$company])[0]),
            'the company keeps its name, contact and both email addresses (the customer has the first); the phone comes in');
        $this->assertSame(['erhverv', null, null], array_values($this->sql('SELECT kontotype, fornavn, efternavn FROM adresser WHERE id = $1', [$company])[0]),
            'SD-814: the company stays a business debtor and gets no person names, so its card keeps the company name when saved');
        $this->assertSame(['Bo Hansen', '', 'privat', 'Bo', 'Hansen'], array_values($this->sql('SELECT firmanavn, kontakt, kontotype, fornavn, efternavn FROM adresser WHERE id = $1', [$person])[0]),
            'the name is Saldi\'s, it was sent out from here, and so are the names the card joins');

        $r = $this->steps(['echo', 'hml', $company, ['updated_at' => '2030-01-02T10:00:00Z',
            'default_address' => ['company' => 'SD803 Nyt Firma A/S', 'address1' => 'Vejen 1', 'zip' => '8000', 'city' => 'Aarhus', 'country' => 'Danmark']]]);
        $this->assertSame(['outcome' => 'done'], $r['results'][0]);
        $this->assertSame(['SD803 Nyt Firma A/S', 'Hans Peter Jensen', 'Vejen 1'], array_values($this->sql('SELECT firmanavn, kontakt, addr1 FROM adresser WHERE id = $1', [$company])[0]),
            'a company on the address is the customer\'s own name, as for any customer');

        $this->steps(['echo', 'hml', $company, ['email' => 'ny@acme.dk', 'updated_at' => '2030-01-03T10:00:00Z']]);
        $this->assertSame('ny@acme.dk', $this->sql('SELECT email FROM adresser WHERE id = $1', [$company])[0]['email'], 'an email really changed in Shopify comes in');
    }

    public function testAnExportThatNeverLinkedLeavesTheInboundNameUpdate(): void
    {
        // a customer made in Shopify comes in as a new debtor; an export row of that debtor never got linked
        $r = $this->steps(['remote', 'hml', ['firstName' => 'Inge', 'lastName' => 'Holm', 'tags' => [$this->tag(999999)]]], ['echo', 'hml', 999999]);
        $this->assertSame(['outcome' => 'done'], $r['results'][1]);
        $id = (int)$this->sql("SELECT id FROM adresser WHERE btrim(kontonr) ~ '^2900[0-9]{4}$'")[0]['id'];
        $this->sql("INSERT INTO shopify_customer_exports (shop_id, adresser_id, state, correlation_tag) SELECT id, $1, 'parked', 'x' FROM shopify_shops WHERE shop_key = 'hml'", [$id]);

        $r = $this->steps(['echo', 'hml', $id, ['last_name' => 'Holm-Berg', 'updated_at' => '2030-01-01T10:00:00Z']]);

        $this->assertSame(['outcome' => 'done'], $r['results'][0]);
        $this->assertSame('Inge Holm-Berg', $this->sql('SELECT firmanavn FROM adresser WHERE id = $1', [$id])[0]['firmanavn'], 'only a linked export keeps the name');
    }

    public function testALinkedExportQueuedAgainRightAwayWaitsForTheSearch(): void
    {
        $id = $this->debtor('SD803V', ['email' => '']);
        $this->steps(['enqueue', 'hml', $id], ['job', 'hml', $id]);
        $this->sql('DELETE FROM shopify_customer_links');

        $r = $this->steps(['enqueue', 'hml', $id], ['job_lag', 'hml', $id]);

        $this->assertSame('yield', $r['results'][1]['outcome'], 'its create was just now: the search may not show it yet');
        $this->assertSame(0, self::creates($r), 'no second customer with the tag');
        $this->assertSame('create_started', $this->export($id)['state']);
    }

    public function testAParkedExportIsLookedUpBeforeAnythingIsCreated(): void
    {
        $id = $this->debtor('SD803S', ['email' => '']);
        $other = $this->debtor('SD803T', ['email' => '']);
        $this->steps(['enqueue', 'hml', $id], ['job', 'hml', $id]);
        // the customer with our tag is linked to another debtor, say by hand
        $this->sql('UPDATE shopify_customer_links SET adresser_id = $1', [$other]);

        $r = $this->steps(['enqueue', 'hml', $id], ['job', 'hml', $id]);
        $this->assertSame('parked', $this->export($id)['state'], 'found by its tag, linked otherwise: parked');
        $this->assertSame(0, self::creates($r));

        $r = $this->steps(['enqueue', 'hml', $id], ['job', 'hml', $id]);

        $this->assertSame('parked', $this->export($id)['state'], 'parked again, not created again');
        $this->assertSame(0, self::creates($r));
        $this->assertCount(1, $r['customers']['hml'], 'one customer carries the tag');
    }

    public function testAPhoneTakenThenAnEmailTakenLeavesNoPhoneInfoRow(): void
    {
        $id = $this->debtor('SD803U', ['email' => 'kapret@example.dk', 'mobile' => '+4533445566']);
        $this->steps(['remote', 'hml', ['firstName' => 'Anden', 'phone' => '+4533445566']], ['enqueue', 'hml', $id]);

        // the phone is refused; while the create without it is prepared, another customer takes the email
        $r = $this->steps(['job_race', 'hml', $id, ['firstName' => 'Tredje', 'email' => 'kapret@example.dk']]);

        $this->assertSame(['outcome' => 'done'], $r['results'][0]);
        $this->assertSame('parked', $this->export($id)['state']);
        $this->assertSame(3, self::creates($r), 'with the phone, the other customer, and without the phone');
        $this->assertSame([['kind' => 'customer.export']], $this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'no customer went without its phone, so no info row stays');
    }

    public function testADebtorCorrectedWhileShopifyAnswersIsNotParkedOnTheOldEmail(): void
    {
        $id = $this->debtor('SD803P', ['email' => 'gammel@example.dk']);
        $this->steps(['remote', 'hml', ['firstName' => 'Anden', 'email' => 'gammel@example.dk']], ['enqueue', 'hml', $id]);

        $r = $this->steps(['job_edit', 'hml', $id, "UPDATE adresser SET email = 'ny-mens@example.dk' WHERE id = \$1"]);
        $this->assertSame(['outcome' => 'yield', 'checkpoint' => [], 'run_after' => 1], $r['results'][0], 'refused on the old email: runs again instead of parking');
        $this->assertSame('pending', $this->export($id)['state']);
        $this->assertSame([], $this->sql("SELECT 1 FROM shopify_errors WHERE resolved_at IS NULL AND kind = 'customer.export'"));

        $r = $this->steps(['job', 'hml', $id]);
        $this->assertSame('linked', $this->export($id)['state'], 'sent with the corrected email');
        $this->assertSame('ny-mens@example.dk', $r['customers']['hml'][1]['email']);
    }

    public function testALostCreateResponseIsFoundAndNotSentAgain(): void
    {
        $id = $this->debtor('SD803E');

        $r = $this->steps(['enqueue', 'hml', $id], ['fail', 'customerCreate', 'timeout_after_apply'], ['job', 'hml', $id]);

        $this->assertSame(['outcome' => 'done'], $r['results'][2]);
        $this->assertCount(1, $r['customers']['hml'], 'one customer');
        $this->assertSame(1, self::creates($r), 'the create is looked up after the timeout, not resent');
        $this->assertSame('linked', $this->export($id)['state']);
    }

    public function testALookupAfterAFailedCreateNeverAdoptsAnotherCustomerWithTheEmail(): void
    {
        $id = $this->debtor('SD803I', ['email' => 'anden@example.dk']);

        $r = $this->steps(['remote', 'hml', ['firstName' => 'Anden', 'email' => 'anden@example.dk']], ['enqueue', 'hml', $id], ['fail', 'customerCreate', 'http_500'], ['job', 'hml', $id]);

        $this->assertSame('parked', $this->export($id)['state'], 'the customer with the email lacks our tag: not ours, so the create is sent again and refused');
        $this->assertSame([], $this->sql('SELECT 1 FROM shopify_customer_links'));
        $this->assertSame(3, self::creates($r), 'the other customer, the failed create and the one sent again');
    }

    public function testAJobThatDiedAfterItsCreateFindsTheCustomerByItsTag(): void
    {
        $id = $this->debtor('SD803J', ['email' => '']);
        $this->steps(['enqueue', 'hml', $id]);
        // the worker sent the create and died before it stored the answer
        $this->sql("UPDATE shopify_customer_exports SET state = 'create_started', attempt_at = now()");

        $r = $this->steps(['remote', 'hml', ['firstName' => 'SD803', 'tags' => [$this->tag($id)]]], ['job', 'hml', $id]);

        $this->assertSame(['outcome' => 'done'], $r['results'][1], 'found by its tag at once, without waiting');
        $this->assertSame(1, self::creates($r), 'only the create that died');
        $this->assertSame('linked', $this->export($id)['state']);
    }

    public function testACreateThatOnlyItsTagCanFindWaitsBeforeItIsSentAgain(): void
    {
        $id = $this->debtor('SD803F', ['email' => '']);

        $r = $this->steps(['enqueue', 'hml', $id], ['fail', 'customerCreate', 'http_500x4'], ['job', 'hml', $id]);
        $this->assertSame('yield', $r['results'][2]['outcome'], 'no email or phone: a lagging search could miss it, so it is not resent');
        $this->assertSame(600, $r['results'][2]['run_after']);
        $this->assertSame('create_started', $this->export($id)['state']);
        $this->assertSame(1, self::creates($r));

        $r = $this->steps(['job', 'hml', $id]);
        $this->assertSame('yield', $r['results'][0]['outcome'], 'still within the settle time: read, nothing found, nothing sent');
        $this->assertSame(0, self::creates($r));

        $this->sql("UPDATE shopify_customer_exports SET attempt_at = now() - interval '11 minutes'");
        $r = $this->steps(['job', 'hml', $id]);
        $this->assertSame(['outcome' => 'done'], $r['results'][0]);
        $this->assertSame(1, self::creates($r), 'after the settle time it is sent again');
        $this->assertCount(1, $r['customers']['hml']);
        $this->assertSame('linked', $this->export($id)['state']);
    }

    public function testThePhoneInfoGoesWhenALaterCreateCarriesThePhone(): void
    {
        $id = $this->debtor('SD803W', ['mobile' => '+4544556677']);
        $this->steps(['remote', 'hml', ['firstName' => 'Anden', 'phone' => '+4544556677']], ['enqueue', 'hml', $id]);

        // the phone is refused, and the retry without it is refused before it reaches Shopify
        $r = $this->steps(['job_refuse', 'hml', $id, 2]);
        $this->assertSame('ShopifyCustomerExportError', $r['results'][0]['exception']);
        $this->assertSame([['kind' => 'customer.phone']], $this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'));

        // the other customer gives up the phone in Shopify; the next run creates the debtor with it
        $state = json_decode((string)file_get_contents($this->state), true);
        foreach ($state['shops'] as &$shop) {
            foreach ($shop['customers'] as &$c) {
                $c['phone'] = $c['phone'] === '+4544556677' ? null : $c['phone'];
            }
        }
        unset($shop, $c);
        file_put_contents($this->state, json_encode($state));
        $r = $this->steps(['job', 'hml', $id]);

        $this->assertSame('linked', $this->export($id)['state']);
        $this->assertSame('+4544556677', $r['customers']['hml'][1]['phone']);
        $this->assertSame([], $this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'it went with its phone after all: the info row goes');
    }

    public function testThePhoneInfoGoesWhenTheWebhookLinksACreateWithThePhoneFirst(): void
    {
        $id = $this->debtor('SD803X', ['email' => '', 'mobile' => '+4544556688']);
        $this->steps(['remote', 'hml', ['firstName' => 'Anden', 'phone' => '+4544556688']], ['enqueue', 'hml', $id], ['job_refuse', 'hml', $id, 2]);
        $this->sql("UPDATE adresser SET mobile = '+4544556699' WHERE id = $1", [$id]);
        $this->steps(['enqueue', 'hml', $id]);
        // the corrected create reached Shopify and its answer was lost; its webhook links the debtor before the job runs again
        $this->sql("UPDATE shopify_customer_exports SET state = 'create_started', attempt_at = now()");

        $r = $this->steps(['remote', 'hml', ['firstName' => 'SD803', 'phone' => '+4544556699', 'tags' => [$this->tag($id)]]], ['echo', 'hml', $id], ['job', 'hml', $id]);

        $this->assertSame(['outcome' => 'done'], $r['results'][2]);
        $this->assertSame('linked', $this->export($id)['state'], 'the webhook linked it first');
        $this->assertSame([], $this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'its customer has the phone: the info row goes');
    }

    public function testACreateThatFailedBeforeShopifyGoesBackToPendingAndTheJobFails(): void
    {
        $id = $this->debtor('SD803G');

        $r = $this->steps(['enqueue', 'hml', $id], ['fail', 'customerCreate', 'http_401'], ['job', 'hml', $id]);

        $this->assertSame('ShopifyCustomerExportError', $r['results'][2]['exception'], 'the queue retries and alarms');
        $this->assertSame('pending', $this->export($id)['state'], 'nothing was created, so the next attempt sends at once');
        $this->assertSame([], $r['customers']['hml'] ?? []);
    }

    public function testAShopWithoutTheCustomersFlowOrADeletedDebtorSendsNothing(): void
    {
        $id = $this->debtor('SD803H');

        $r = $this->steps(['enqueue', 'off', $id], ['enqueue', 'hml', $id]);
        $this->assertNull($r['results'][0]);
        $this->sql('DELETE FROM adresser WHERE id = $1', [$id]);
        $r = $this->steps(['job', 'hml', $id]);

        $this->assertSame(['outcome' => 'done'], $r['results'][0]);
        $this->assertNull($this->export($id), 'the reservation of a deleted debtor goes');
        $this->assertSame(0, self::creates($r));
    }

    public function testTheInputUsesTheDebtorsNameAndAnE164PhoneOnly(): void
    {
        $r = $this->steps(
            ['input', ['firmanavn' => 'Jensen', 'kontakt' => '', 'email' => '', 'tlf' => '0045 12 34 56 78', 'mobile' => '', 'land' => 'Danmark']],
            ['input', ['firmanavn' => 'A/S Firma', 'kontakt' => '  Mette  Hansen ', 'email' => ' m@example.dk ', 'tlf' => '', 'mobile' => '87654321', 'land' => 'Sverige']],
            ['input', ['firmanavn' => 'Kunde', 'kontakt' => '', 'email' => '', 'tlf' => '87654321', 'mobile' => '+46 70 123 45 67', 'land' => '']],
            ['input', ['firmanavn' => 'Flere', 'kontakt' => '', 'email' => ' ; bogholderi@acme.dk;jens@acme.dk, ', 'tlf' => '', 'mobile' => '', 'land' => '']]
        );

        $this->assertSame(['firstName' => 'Jensen', 'phone' => '+4512345678', 'tags' => ['saldi-deb-test-1']], $r['results'][0]);
        $this->assertSame(['firstName' => 'Mette', 'lastName' => 'Hansen', 'email' => 'm@example.dk', 'tags' => ['saldi-deb-test-1']], $r['results'][1],
            'an 8-digit number outside Denmark is not guessed');
        $this->assertSame(['firstName' => 'Kunde', 'phone' => '+46701234567', 'tags' => ['saldi-deb-test-1']], $r['results'][2], 'mobile before tlf');
        $this->assertSame(['firstName' => 'Flere', 'email' => 'bogholderi@acme.dk', 'tags' => ['saldi-deb-test-1']], $r['results'][3], 'the first of several emails');
    }
}
