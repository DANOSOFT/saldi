<?php
// SD-844 K61: the contact rule (a pickup or delivery sale needs the customer's email OR phone),
// as a truth table and against a real tenant, where the sale's delivery choice decides.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

if (!function_exists('findtekst')) {
    /** The text after the pipe, as findtekst() shows a key no translator has filled in yet. */
    function findtekst($token, $sprogId)
    {
        $parts = explode('|', (string)$token, 2);
        return $parts[1] ?? $parts[0];
    }
}

final class PosDeliveryContactSyncTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';
    /** @var resource|\PgSql\Connection|null */
    private $conn = null;

    public static function setUpBeforeClass(): void
    {
        require_once ShopifyEnv::repoRoot() . '/debitor/pos_ordre_includes/deliveryContact.php';
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_k61');
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

    /** @return array<string, array{string, string, string, bool}> type, email, phone, allowed */
    public static function truthTable(): array
    {
        $rows = [];
        foreach (['afhentning', 'levering', 'taget_med', ''] as $type) {
            foreach (['email' => 'kunde@example.test', 'no email' => ''] as $e => $email) {
                foreach (['phone' => '12345678', 'no phone' => ''] as $p => $phone) {
                    $needsContact = in_array($type, ['afhentning', 'levering'], true);
                    $rows[($type ?: 'no choice') . ", $e, $p"] = [$type, $email, $phone, !$needsContact || $email !== '' || $phone !== ''];
                }
            }
        }
        return $rows;
    }

    // The rule itself, without the refusal's text: findtekst() needs a database, and in the full
    // suite the real one may already be loaded by an earlier test, so this file's stub is not used.
    #[DataProvider('truthTable')]
    public function testTheContactRule(string $type, string $email, string $phone, bool $allowed): void
    {
        self::assertSame($allowed, pos_delivery_contact_ok($type, $email, $phone));
    }

    public function testBlanksAreNotContactData(): void
    {
        self::assertFalse(pos_delivery_contact_ok('levering', '  ', "\t"));
    }

    public function testTheMessageCannotBreakTheAlertItIsShownIn(): void
    {
        self::assertSame('a\\x27b\\x22c d e', pos_delivery_contact_alert_text("a'b\"c\rd\ne<>\\"));
    }

    public function testARefusalIsRememberedForTheRequest(): void
    {
        unset($GLOBALS['pos_delivery_contact_refused']);
        pos_validate_delivery_contact('levering', 'kunde@example.test', '');
        self::assertArrayNotHasKey('pos_delivery_contact_refused', $GLOBALS, 'an accepted sale leaves no mark');
        pos_validate_delivery_contact('levering', '', '');
        self::assertTrue($GLOBALS['pos_delivery_contact_refused']);
        unset($GLOBALS['pos_delivery_contact_refused']);
    }

    public function testASaleIsJudgedByItsDeliveryChoice(): void
    {
        $this->needDatabase();
        $pickup = $this->choice('Afhentning i butik', 'afhentning');
        $delivery = $this->choice('Danske Fragtmænd', 'levering');
        $takeaway = $this->choice('Taget med', 'taget_med');

        $sales = [
            'no choice, no contact' => [$this->sale(null, '', ''), true],
            'taget med, no contact' => [$this->sale($takeaway, '', ''), true],
            'pickup, no contact' => [$this->sale($pickup, '', ''), false],
            'pickup, phone' => [$this->sale($pickup, '', '12345678'), true],
            'delivery, no contact' => [$this->sale($delivery, '', ''), false],
            'delivery, email' => [$this->sale($delivery, 'kunde@example.test', ''), true],
        ];
        $answers = $this->ask(array_column($sales, 0));

        foreach ($sales as $label => [$id, $allowed]) {
            self::assertSame($allowed, $answers[$id]['ok'], $label);
        }
        self::assertSame('Afhentning og levering kræver kundens e-mail eller telefonnummer.', $answers[$sales['pickup, no contact'][0]]['message']);
    }

    public function testAnUnknownSalePasses(): void
    {
        $this->needDatabase();
        self::assertTrue($this->ask([987654321])[987654321]['ok']);
    }

    public function testATenantWithoutTheShopifySchemaIsNeverChecked(): void
    {
        $this->needDatabase();
        $id = $this->sale($this->choice('Levering', 'levering'), '', '');
        $marker = $this->sql("SELECT var_value FROM settings WHERE var_grp = 'shopify' AND var_name = 'schema_version'")[0]['var_value'];
        $this->sql("DELETE FROM settings WHERE var_grp = 'shopify' AND var_name = 'schema_version'");
        try {
            self::assertTrue($this->ask([$id])[$id]['ok']);
        } finally {
            $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify', 'schema_version', $1)", [$marker]);
        }
    }

    private function needDatabase(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $this->conn ??= ShopifyEnv::connect(self::$db);
    }

    private function choice(string $navn, string $type): int
    {
        return (int)$this->sql('INSERT INTO pos_leveringsvalg (navn, type) VALUES ($1, $2) RETURNING id', [$navn, $type])[0]['id'];
    }

    private function sale(?int $choice, string $email, string $phone): int
    {
        return (int)$this->sql("INSERT INTO ordrer (art, status, ordrenr, konto_id, email, phone, leveringsvalg_id) VALUES ('PO', 0, 0, 0, $1, $2, $3) RETURNING id",
            [$email, $phone, $choice])[0]['id'];
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{ok: bool, message: string}>
     */
    private function ask(array $ids): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_pos_contact.php', array_merge([self::$db], array_map('strval', $ids))));
        self::assertSame(0, $run['exit'], 'run_pos_contact.php failed: ' . $run['stderr'] . $run['stdout']);
        self::assertIsArray($run['result'], 'run_pos_contact.php did not report JSON: ' . $run['stdout']);
        return $run['result'];
    }

    private function sql(string $sql, array $params = []): array
    {
        return ShopifyEnv::rows($this->conn, $sql, $params);
    }
}
