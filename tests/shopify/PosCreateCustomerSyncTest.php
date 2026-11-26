<?php
// SD-844 K31: a customer created at the till gets a number from the shared Shopify allocator at
// once, with no Shopify call, and the same create form sent twice (a refresh, a double click, two
// at the same moment) creates one customer.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class PosCreateCustomerSyncTest extends TestCase
{
    private const START = 29100000;
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
            self::$db = ShopifyEnv::testDb('_k31');
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
        $this->sql('ALTER TABLE adresser ADD COLUMN IF NOT EXISTS mobile varchar(15)');
        foreach (['shopify_customer_links', 'shopify_customer_exports', 'shopify_jobs', 'shopify_errors', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM adresser WHERE btrim(kontonr) ~ '^291[0-9]{5}$' OR firmanavn LIKE 'SD844%'");
        $this->sql("DELETE FROM settings WHERE var_grp = 'pos_customer_create'");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art) SELECT 'SD844 POS', 94, 'DG' WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art = 'DG' AND kodenr = 94)");
        $this->sql("DELETE FROM settings WHERE var_grp = 'shopify' AND var_name = 'customer_number_start'");
        $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify', 'customer_number_start', $1)", [(string)self::START]);
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, customers_enabled) VALUES ('hml', 'hml-test.myshopify.com', true, true)");
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            pg_close($this->conn);
        }
    }

    public function testTheTillCreatesThroughShopifyOnlyWhenAShopTakesCustomers(): void
    {
        self::assertTrue($this->steps(['mode'])[0]);
        $this->sql('UPDATE shopify_shops SET customers_enabled = false');
        self::assertFalse($this->steps(['mode'])[0]);
        $this->sql('DELETE FROM shopify_shops');
        self::assertFalse($this->steps(['mode'])[0]);
    }

    public function testACustomerGetsTheNextShopifyNumberAndTheFormsDetails(): void
    {
        $result = $this->steps(['create', $this->form(['firmanavn' => 'SD844 Hansen', 'phone' => '+45 12 34 56 78',
            'email' => 'hansen@example.test', 'addr1' => 'Havevej 1', 'postnr' => '5000', 'bynavn' => 'Odense C']), $this->token()])[0];

        self::assertArrayNotHasKey('error', $result, json_encode($result));
        self::assertTrue($result['created']);
        self::assertSame((string)self::START, $result['kontonr']);
        $debtor = $this->sql('SELECT kontonr, art, firmanavn, tlf, email, addr1, postnr, bynavn, gruppe, oprettet FROM adresser WHERE id = $1', [$result['konto_id']])[0];
        self::assertSame(['kontonr' => (string)self::START, 'art' => 'D', 'firmanavn' => 'SD844 Hansen', 'tlf' => '+45 12 34 56 78',
            'email' => 'hansen@example.test', 'addr1' => 'Havevej 1', 'postnr' => '5000', 'bynavn' => 'Odense C', 'gruppe' => '94',
            'oprettet' => date('Y-m-d')], $debtor);
        self::assertSame([], $this->sql('SELECT id FROM shopify_jobs'), 'creating at the till calls nothing in Shopify');
    }

    public function testTheSameFormSentAgainCreatesOneCustomer(): void
    {
        $token = $this->token();
        [$first, $again] = $this->steps(['create', $this->form(), $token], ['create', $this->form(), $token]);

        self::assertTrue($first['created']);
        self::assertFalse($again['created']);
        self::assertSame($first['konto_id'], $again['konto_id']);
        self::assertSame('1', $this->sql("SELECT count(*) AS n FROM adresser WHERE firmanavn = 'SD844 Kunde'")[0]['n']);
    }

    public function testAFormSentAgainAfterItsCustomerWasDeletedCreatesANewOne(): void
    {
        $token = $this->token();
        [$first] = $this->steps(['create', $this->form(), $token]);
        $this->sql('DELETE FROM adresser WHERE id = $1', [$first['konto_id']]);

        [$again] = $this->steps(['create', $this->form(), $token]);

        self::assertTrue($again['created'], 'not a failure on the old token row');
        self::assertNotSame($first['konto_id'], $again['konto_id']);
        self::assertSame((string)$again['konto_id'], $this->sql('SELECT var_value FROM settings WHERE var_grp = $1 AND var_name = $2',
            ['pos_customer_create', $token])[0]['var_value'], 'the token now points at the new customer');
    }

    public function testTwoCopiesOfTheFormAtTheSameMomentCreateOneCustomer(): void
    {
        $token = $this->token();
        $first = $this->start(['hold', $this->form(), $token, 1.5]);
        usleep(400000);
        $second = $this->start(['create', $this->form(), $token]);
        $a = $this->finish($first)[0];
        $b = $this->finish($second)[0];

        self::assertArrayNotHasKey('error', $a, json_encode($a));
        self::assertArrayNotHasKey('error', $b, json_encode($b));
        self::assertSame($a['konto_id'], $b['konto_id']);
        self::assertSame([true, false], [$a['created'], $b['created']]);
        self::assertSame('1', $this->sql("SELECT count(*) AS n FROM adresser WHERE firmanavn = 'SD844 Kunde'")[0]['n']);
    }

    public function testTwoFormsGiveTwoCustomersWithTheirOwnNumbers(): void
    {
        [$a, $b] = $this->steps(['create', $this->form(), $this->token()], ['create', $this->form(['firmanavn' => 'SD844 Anden']), $this->token()]);

        self::assertNotSame($a['konto_id'], $b['konto_id']);
        self::assertSame([(string)self::START, (string)(self::START + 1)], [$a['kontonr'], $b['kontonr']]);
    }

    public function testAFormWithoutNameOrGroupCreatesNothing(): void
    {
        [$noName, $noGroup, $noToken] = $this->steps(
            ['create', $this->form(['firmanavn' => '  ']), $this->token()],
            ['create', $this->form(['grp' => '']), $this->token()],
            ['create', $this->form(), 'not-a-token']
        );

        self::assertSame('InvalidArgumentException', $noName['error']);
        self::assertSame('InvalidArgumentException', $noGroup['error']);
        self::assertSame('UnexpectedValueException', $noToken['error']);
        self::assertSame([], $this->sql("SELECT id FROM adresser WHERE firmanavn LIKE 'SD844%'"));
        self::assertSame([], $this->sql("SELECT id FROM settings WHERE var_grp = 'pos_customer_create'"));
    }

    public function testAPhoneTheShopifyExportWouldDropCreatesNothing(): void
    {
        // The export sends a phone only as E.164 (shopify_customer_export_phone()): 8 digits are taken as
        // Danish, anything else needs its country code. A sale with a phone it drops reaches Shopify with
        // no contact although it passed the pickup and delivery contact check (K61).
        [$short, $local, $padded] = $this->steps(
            ['create', $this->form(['phone' => '12 34 56']), $this->token()],
            ['create', $this->form(['phone' => '0701234567']), $this->token()],
            // valid as typed, but the sale keeps only its first 50 characters, which lose the last digit
            ['create', $this->form(['phone' => implode(str_repeat(' ', 7), str_split('12345678'))]), $this->token()]
        );

        foreach ([$short, $local, $padded] as $refused) {
            self::assertSame('InvalidArgumentException', $refused['error']);
            self::assertSame(2, $refused['code'], 'POS_CUSTOMER_CREATE_BAD_PHONE');
        }
        self::assertSame([], $this->sql("SELECT id FROM adresser WHERE firmanavn LIKE 'SD844%'"));
    }

    public function testAPhoneTheExportCanSendIsKeptAsTyped(): void
    {
        $results = $this->steps(
            ['create', $this->form(['firmanavn' => 'SD844 Dansk', 'phone' => '12 34 56 78']), $this->token()],
            ['create', $this->form(['firmanavn' => 'SD844 Svensk', 'phone' => '+46 70 123 45 67']), $this->token()],
            ['create', $this->form(['firmanavn' => 'SD844 Uden']), $this->token()]
        );

        self::assertSame(['12 34 56 78', '+46 70 123 45 67', ''], array_map(
            fn(array $r): string => (string)$this->sql('SELECT tlf FROM adresser WHERE id = $1', [$r['konto_id']])[0]['tlf'], $results));
    }

    public function testLongValuesAreCutToTheColumns(): void
    {
        $result = $this->steps(['create', $this->form(['firmanavn' => 'SD844 ' . str_repeat('ø', 100), 'cvrnr' => str_repeat('1', 30)]), $this->token()])[0];

        $debtor = $this->sql('SELECT firmanavn, cvrnr FROM adresser WHERE id = $1', [$result['konto_id']])[0];
        self::assertSame(90, mb_strlen($debtor['firmanavn']));
        self::assertSame(str_repeat('1', 20), $debtor['cvrnr']);
    }

    public function testOldTokensAreCleared(): void
    {
        $this->sql("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('old', 'pos_customer_create', '1', '2000-01-01 00:00:00')");
        $this->steps(['create', $this->form(), $this->token()]);

        self::assertSame([], $this->sql("SELECT id FROM settings WHERE var_grp = 'pos_customer_create' AND var_name = 'old'"));
        self::assertSame('1', $this->sql("SELECT count(*) AS n FROM settings WHERE var_grp = 'pos_customer_create'")[0]['n']);
    }

    /** @return array<string, string> */
    private function form(array $over = []): array
    {
        return $over + ['firmanavn' => 'SD844 Kunde', 'addr1' => '', 'addr2' => '', 'postnr' => '', 'bynavn' => '', 'email' => '',
            'phone' => '', 'kontakt' => '', 'cvrnr' => '', 'ean' => '', 'grp' => '94', 'betalingsbet' => 'Kontant', 'betalingsdage' => '0'];
    }

    private function token(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function start(array ...$steps)
    {
        return ShopifyEnv::startChild('run_pos_create_customer.php', [self::$db, json_encode($steps)]);
    }

    private function finish($child): array
    {
        $run = ShopifyEnv::waitChild($child);
        self::assertSame(0, $run['exit'], 'run_pos_create_customer.php failed: ' . $run['stderr'] . $run['stdout']);
        self::assertIsArray($run['result'], 'run_pos_create_customer.php did not report JSON: ' . $run['stdout']);
        return $run['result']['results'];
    }

    private function steps(array ...$steps): array
    {
        return $this->finish($this->start(...$steps));
    }

    private function sql(string $sql, array $params = []): array
    {
        return ShopifyEnv::rows($this->conn, $sql, $params);
    }
}
