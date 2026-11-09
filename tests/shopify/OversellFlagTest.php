<?php
// tests/shopify/OversellFlagTest.php
//
// 'Tillad oversalg' on the real item card (SD-802, K14): the page is run as a browser runs it
// (support/run_varekort.php), its own form posted back. Ticking the box stores 'on' and queues
// one catalogue.upsert after the commit; unticking stores ''; a save that ends inside its
// transaction (the stock-change confirmation exits there) stores nothing and queues nothing; a
// tenant without the Shopify schema's varer.oversalg shows no box and still saves. Skips without
// ShopifyEnv.
//
// History:
// 20261103 CL/SS SD-802: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class OversellFlagTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';

    /** @var resource|\PgSql\Connection|null */
    private $conn = null;
    private int $item = 0;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        if (!is_file(ShopifyEnv::repoRoot() . '/includes/connect.php')) {
            self::$skip = 'the item card needs includes/connect.php';
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_v');
            ShopifyEnv::cloneTenant(self::$db);
            ShopifyEnv::registerTenant(self::$db);
            self::ensureSchema();
        } catch (Throwable $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db !== '') {
            ShopifyEnv::removeTempDir(self::$db);
        }
        ShopifyEnv::unregisterTenants();
        ShopifyEnv::dropOwnedTenants();
    }

    private static function ensureSchema(): void
    {
        $run = ShopifyEnv::runSchema(self::$db, 'ensure');
        if ($run['exit'] !== 0) {
            throw new RuntimeException('schema ensure failed: ' . $run['stderr']);
        }
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $this->conn = ShopifyEnv::connect(self::$db);
        foreach (['shopify_product_links', 'shopify_jobs', 'shopify_errors', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM settings WHERE var_name = 'confirmStockChange' AND var_grp = 'items'");
        $this->sql("DELETE FROM varer WHERE varenr LIKE 'SD802-%'");
        $this->sql("DELETE FROM grupper WHERE art = 'RA' OR (art = 'VG' AND kodenr = 903) OR (art = 'SM' AND kodenr = 79)");
        $this->sql("DELETE FROM kontoplan WHERE kontonr = 9004");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box4, box7, box8, fiscal_year) VALUES ('Havemøbler', 903, 'VG', '9004', '', 'on', 1)");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box2) VALUES ('Salgsmoms', 79, 'SM', '25')");
        $this->sql("INSERT INTO kontoplan (kontonr, beskrivelse, moms, regnskabsaar) VALUES (9004, 'Salg', 'S79', 1)");
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, products_enabled) VALUES ('hml', 'hml-test.myshopify.com', true, true)");
        $this->item = (int)$this->sql("INSERT INTO varer (varenr, beskrivelse, salgspris, kostpris, gruppe, lukket, min_lager, max_lager, beholdning)
            VALUES ('SD802-O1', 'Havestol O1', 999.2, 500, 903, '0', 0, 0, 0) RETURNING id")[0]['id'];
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

    /** The page's output for a GET of the item, or for a POST. */
    private function page(array $request): string
    {
        $out = (string)tempnam(sys_get_temp_dir(), 'sd802v');
        try {
            $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_varekort.php', [self::$db, json_encode($request), $out]));
            $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
            $this->assertStringContainsString('SD802_PAGE_DONE', $run['stdout'], $run['stderr']);
            return (string)file_get_contents($out);
        } finally {
            unlink($out);
        }
    }

    private function view(): string
    {
        return $this->page(['get' => ['id' => (string)$this->item]]);
    }

    /**
     * The card's form as a browser posts it on Gem: every field with its value, checkboxes only
     * when ticked; $set overrides (null unticks or drops).
     */
    private function save(string $html, array $set): string
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $post = [];
        foreach ((new DOMXPath($dom))->query('//input[@name] | //select[@name] | //textarea[@name]') as $el) {
            $name = $el->getAttribute('name');
            $type = strtolower($el->getAttribute('type'));
            if ($el->nodeName === 'input' && in_array($type, ['submit', 'button', 'image', 'reset', 'file'], true)) {
                continue;
            }
            if (in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) {
                continue;
            }
            $value = match ($el->nodeName) {
                'textarea' => $el->textContent,
                'select' => (function () use ($el): string {
                    $first = null;
                    foreach ($el->getElementsByTagName('option') as $o) {
                        $first ??= $o;
                        if ($o->hasAttribute('selected')) {
                            return $o->getAttribute('value');
                        }
                    }
                    return $first ? $first->getAttribute('value') : '';
                })(),
                default => $el->hasAttribute('value') ? $el->getAttribute('value') : ($type === 'checkbox' ? 'on' : ''),
            };
            if (str_ends_with($name, '[]')) {
                $post[substr($name, 0, -2)][] = $value;
            } elseif (preg_match('/^(\w+)\[(\w*)\]$/', $name, $m)) {
                $post[$m[1]][$m[2]] = $value;
            } else {
                $post[$name] = $value;
            }
        }
        foreach ($set as $name => $value) {
            if ($value === null) {
                unset($post[$name]);
            } else {
                $post[$name] = $value;
            }
        }
        return $this->page(['get' => ['id' => (string)$this->item], 'post' => $post + ['saveItem' => 'Gem']]);
    }

    private function oversalg(): ?string
    {
        return $this->sql('SELECT oversalg FROM varer WHERE id = $1', [$this->item])[0]['oversalg'];
    }

    private function jobs(): array
    {
        return $this->sql("SELECT type, dedupe_key, payload->>'vare_id' AS vare_id FROM shopify_jobs ORDER BY id");
    }

    public function testTickingTheBoxStoresOnAndQueuesOneUpsertAndUntickingStoresNothing(): void
    {
        $card = $this->view();
        $this->assertStringContainsString('Tillad oversalg', $card);
        $this->assertMatchesRegularExpression("/name='oversalg' >/", $card, 'unticked');

        $this->save($card, ['oversalg' => 'on']);

        $this->assertSame('on', $this->oversalg());
        $this->assertSame([['type' => 'catalogue.upsert', 'dedupe_key' => "hml:vare:{$this->item}", 'vare_id' => (string)$this->item]], $this->jobs(),
            'one upsert, queued after the commit; its inventoryPolicy becomes CONTINUE (PricesTest: the hash changes)');

        $card = $this->view();
        $this->assertMatchesRegularExpression("/name='oversalg' checked>/", $card, 'shown ticked');
        $this->save($card, ['oversalg' => null]);
        $this->assertSame('', $this->oversalg());
        $this->assertCount(1, $this->jobs(), 'the waiting upsert is the one job for the item');
    }

    public function testASaveThatEndsInsideItsTransactionStoresAndQueuesNothing(): void
    {
        $this->sql("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('confirmStockChange', 'items', 'on')");
        $card = $this->view();

        $out = $this->save($card, ['oversalg' => 'on', 'ny_beholdning' => '5']);

        $this->assertStringContainsString("name='confirmStockChange'", $out, 'the confirmation exits before the commit');
        $this->assertNull($this->oversalg(), 'the update was rolled back with the connection');
        $this->assertSame([], $this->jobs());
    }

    public function testWithoutTheShopifyColumnTheCardShowsNoBoxAndStillSaves(): void
    {
        $this->sql('ALTER TABLE varer DROP COLUMN oversalg');
        try {
            $card = $this->view();
            $this->assertStringNotContainsString("name='oversalg'", $card);
            $this->save($card, ['oversalg' => 'on', 'beskrivelse0' => 'Havestol O1 ny']);
            $this->assertSame('Havestol O1 ny', $this->sql('SELECT beskrivelse FROM varer WHERE id = $1', [$this->item])[0]['beskrivelse']);
        } finally {
            self::ensureSchema();
        }
    }
}
