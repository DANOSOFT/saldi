<?php
// tests/shopify/ConfigLinksTest.php
//
// Shop registry, secrets, lager and maps (includes/shopify/config.php) and the order, product
// and customer links (includes/shopify/links.php), SD-735, on a throwaway tenant registered in
// master. Skips when ShopifyEnv is not configured.
//
// History:
// 20261022 Sawaneh SD-735: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class ConfigLinksTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_c');
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
        $conn = ShopifyEnv::connect(self::$db);
        foreach (['shopify_order_links', 'shopify_product_links', 'shopify_customer_links', 'shopify_location_map', 'shopify_shipping_map', 'shopify_payment_map', 'shopify_brands', 'shopify_lager', 'shopify_shops'] as $table) {
            ShopifyEnv::rows($conn, "DELETE FROM $table");
        }
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp = 'shopify' OR var_grp LIKE 'shopify:%'");
        ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
        ShopifyEnv::rows($conn, "INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
        pg_close($conn);
    }

    private function runMode(string $mode): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_config.php', [$mode, self::$db]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertNotNull($run['result'], $run['stdout']);
        return $run['result'];
    }

    public function testShopsFlagsAndTheirValidation(): void
    {
        $r = $this->runMode('config');
        $this->assertSame('hml-test.myshopify.com', $r['hml']['domain'], 'domain is stored lower-cased');
        $this->assertSame('Havemøbelland', $r['hml']['navn']);
        $this->assertSame('2026-07', $r['hml']['api_version']);
        $this->assertSame('t', $r['hml']['is_primary']);
        $this->assertSame('f', $r['hml']['orders_enabled']);
        $this->assertNull($r['unknown_shop']);
        $this->assertSame(['hml', 'brand2', 'brand3'], $r['all_keys']);
        $this->assertSame([], $r['orders_before'], 'every flow is off by default');
        $this->assertSame(['brand2'], $r['orders_after']);
        $this->assertSame('Brand two', $r['brand2']['navn'], 'update touches only the given columns');
        $this->assertSame('brand2.myshopify.com', $r['brand2']['domain']);
        $this->assertSame('InvalidArgumentException', $r['bad_flow']['class']);
        $this->assertSame('ShopifyConfigError', $r['bad_column']['class']);
        $this->assertStringContainsString("unknown column 'colour'", $r['bad_column']['message']);
        $this->assertSame('ShopifyConfigError', $r['bad_domain']['class']);
        $this->assertSame('ShopifyConfigError', $r['bad_key']['class']);
        $this->assertSame('23505', $r['dup_afd']['sqlstate'], 'one shop per afdeling (K01)');
        $this->assertSame('ShopifyConfigError', $r['secret_unknown_shop']['class']);
        $this->assertSame('ShopifyConfigError', $r['customer_number']['class']);
        $this->assertStringContainsString('SD-740', $r['customer_number']['message']);
    }

    public function testSecretsRoundTripAndNeverReachOutputOrLogs(): void
    {
        $r = $this->runMode('config');
        $this->assertNull($r['secret_before']);
        $this->assertTrue($r['secret_roundtrip']);
        $this->assertNull($r['secret_other_shop'], 'secrets are per shop');
        $this->assertTrue($r['secret_rotated']);
        $this->assertSame(1, $r['secret_rows'], 'rotation updates the row instead of adding one');
        $this->assertNull($r['secret_after_delete']);
        $this->assertSame('shopify:hml', $r['secret_group']);
        $this->assertFalse($r['secret_in_logs'], 'the token was found in temp/<db> logs');
        $this->assertStringNotContainsString('shpat_', json_encode($r), 'the runner output carries no token');
    }

    public function testLagerRolesMapsAndSettings(): void
    {
        $r = $this->runMode('config');
        $lagre = array_column($r['lager_config']['lagre'], 'rolle', 'lager');
        $this->assertSame(['1' => 'saelgbar', '2' => 'saelgbar', '3' => 'udstilling', '4' => 'ingen'], $lagre, 'sellable first by priority');
        $byLager = array_column($r['lager_config']['lagre'], null, 'lager');
        $this->assertSame('3', $byLager['2']['prioritet'], 'a second save updates the priority');
        $this->assertSame('1', $byLager['3']['udstilling_af']);
        $this->assertNull($byLager['4']['prioritet']);
        $this->assertSame(2, $r['lager_config']['split_set_lager']);
        $this->assertNull($r['lager_config']['virtual_lager'], 'an empty setting reads as unset');
        $this->assertSame('ShopifyConfigError', $r['bad_role']['class']);
        $this->assertSame('ShopifyConfigError', $r['saelgbar_without_prioritet']['class']);
        $this->assertSame('23505', $r['dup_prioritet']['sqlstate'], 'priority is unique among sellable lagre');
        $this->assertSame('23505', $r['dup_location']['sqlstate'], 'one lager per location in a shop');
        $this->assertSame('InvalidArgumentException', $r['bad_location_gid']['class']);
        $this->assertSame('gid://shopify/Location/1234567890124', $r['location_1'], 'a bare id is stored as a GID and a re-save replaces it');
        $this->assertSame('gid://shopify/Location/98765432109876543210', $r['location_2']);
        $this->assertNull($r['location_unmapped']);
        $this->assertSame(['vare_id' => 77, 'pickup_lager' => null], $r['shipping_fragt']);
        $this->assertSame(['vare_id' => null, 'pickup_lager' => 2], $r['shipping_pickup']);
        $this->assertNull($r['shipping_unknown']);
        $this->assertSame('MobilePay', $r['payment_exact']);
        $this->assertSame('Kort', $r['payment_fallback'], 'an unmapped card brand falls back to the gateway');
        $this->assertSame('Kort', $r['payment_no_brand']);
        $this->assertNull($r['payment_unknown']);
        $this->assertSame('hml', $r['brand_empty'], 'an empty sprog is dansk, and a map stored as Dansk still matches');
        $this->assertSame('hml', $r['brand_spaces']);
        $this->assertSame('hml', $r['brand_dansk_cased']);
        $this->assertSame('brand2', $r['brand_english']);
        $this->assertNull($r['brand_unknown']);
    }

    public function testOrderLinksReserveOnceAndAdvanceWithMergedFlags(): void
    {
        $r = $this->runMode('links');
        $this->assertTrue($r['web']['first']['created']);
        $this->assertSame('reserved', $r['web']['first']['state']);
        $this->assertSame('gid://shopify/Order/98765432109876543210', $r['web']['first']['gid'], 'a 20-digit id becomes a GID and survives unchanged');
        $this->assertSame('#1001', $r['web']['first']['name']);
        $this->assertFalse($r['web']['again']['created']);
        $this->assertSame($r['web']['first']['id'], $r['web']['again']['id']);
        $this->assertTrue($r['web']['other_shop']['created'], 'the same order GID in another shop is another link');
        $this->assertSame($r['web']['first']['gid'], $r['web']['other_shop']['gid']);
        $this->assertSame($r['web']['first']['id'], (int)$r['lookup_gid']);
        $this->assertTrue($r['store']['first']['created']);
        $this->assertNull($r['store']['first']['gid'], 'a store link has no order GID until exported');
        $this->assertSame('2', $r['store']['first']['ship_lager']);
        $this->assertFalse($r['store']['again']['created']);
        $this->assertSame($r['store']['first']['id'], (int)$r['lookup_ordre']);
        $this->assertSame('posted', $r['advanced']['state']);
        $this->assertSame('555', $r['advanced']['ordre_id']);
        $this->assertEquals(['intake' => ['step' => 'posted'], 'allocation_v1' => ['lager' => 1], 'writeback_v1' => ['tags' => true]], $r['advanced']['flags'], 'flags merge by namespace (jsonb orders keys its own way)');
        $this->assertStringStartsWith('{', $r['empty_flags']['raw'], 'empty flags leave the jsonb an object, not an array');
        $this->assertSame('posted', $r['empty_flags']['intake'], 'namespaces survive an advance with empty flags');
        $this->assertSame('ShopifyLinkError', $r['list_flags']['class'], 'flags must be namespaced');
        $this->assertSame('gid://shopify/Order/1234567890123', $r['store_exported']['gid']);
        $this->assertSame('#S1', $r['store_exported']['name']);
        foreach (['bad_direction', 'web_without_gid', 'store_without_ordre', 'bad_column', 'missing_link'] as $case) {
            $this->assertSame('ShopifyLinkError', $r[$case]['class'], $case);
        }
        $this->assertSame('InvalidArgumentException', $r['bad_gid']['class']);
    }

    public function testProductAndCustomerLinksUpsert(): void
    {
        $r = $this->runMode('links');
        $p = $r['product'];
        $this->assertSame('gid://shopify/Product/12345678901234567890', $p['first']['product_gid']);
        $this->assertSame('gid://shopify/ProductVariant/22345678901234567890', $p['first']['variant_gid']);
        $this->assertSame('gid://shopify/InventoryItem/32345678901234567890', $p['first']['inventory_item_gid']);
        $this->assertSame('t', $p['first']['listed']);
        $this->assertSame($p['first']['id'], $p['second']['id'], 'the second upsert updates the same row');
        $this->assertSame($p['first']['product_gid'], $p['second']['product_gid'], 'columns not given are kept');
        $this->assertSame('h2', $p['second']['hash']);
        $this->assertSame('f', $p['second']['listed']);
        $this->assertNotSame($p['first']['id'], $p['other_shop']['id'], 'links are per shop');
        $this->assertSame($p['first']['id'], $p['by_variant']);
        $this->assertSame($p['first']['id'], $p['lookup']);
        $this->assertNull($p['lookup_missing']);
        $c = $r['customer'];
        $this->assertSame('gid://shopify/Customer/42345678901234567890', $c['first']['gid']);
        $this->assertSame('700', $c['first']['adresser_id']);
        $this->assertSame('2026-10-22 10:00:00', $c['first']['updated']);
        $this->assertSame($c['first']['id'], $c['second']['id']);
        $this->assertSame('c2', $c['second']['hash']);
        $this->assertSame($c['first']['id'], $c['by_gid']);
        $this->assertSame($c['first']['id'], $c['by_adresser']);
        $this->assertSame('23505', $r['customer_relink']['sqlstate'], 'a debtor is linked to one customer per shop');
        $this->assertSame('shopify_customer_links_adresser_id_key', substr($r['customer_relink']['message'], strpos($r['customer_relink']['message'], 'constraint ') + 11, 38));
    }

    public function testConcurrentSecretSavesLeaveOneRow(): void
    {
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, "INSERT INTO shopify_shops (shop_key, domain) VALUES ('race', 'race.myshopify.com')");
        pg_close($conn);
        $one = ShopifyEnv::startChild('run_config.php', ['secret_race', self::$db]);
        $two = ShopifyEnv::startChild('run_config.php', ['secret_race', self::$db]);
        $results = [ShopifyEnv::waitChild($one), ShopifyEnv::waitChild($two)];
        foreach ($results as $i => $run) {
            $this->assertSame(0, $run['exit'], "run $i: " . $run['stderr'] . $run['stdout']);
        }
        $conn = ShopifyEnv::connect(self::$db);
        $rows = ShopifyEnv::rows($conn, "SELECT var_value FROM settings WHERE var_grp = 'shopify:race' AND var_name = 'access_token'");
        pg_close($conn);
        $this->assertCount(1, $rows, 'two concurrent saves of the same secret leave one row');
        $this->assertContains($rows[0]['var_value'], ['shpat_' . $results[0]['result']['pid'], 'shpat_' . $results[1]['result']['pid']]);
    }

    public function testConcurrentReservationFromTwoConnectionsCreatesOneLink(): void
    {
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, "INSERT INTO shopify_shops (shop_key, domain) VALUES ('race', 'race.myshopify.com')");
        pg_close($conn);
        $one = ShopifyEnv::startChild('run_config.php', ['race', self::$db]);
        $two = ShopifyEnv::startChild('run_config.php', ['race', self::$db]);
        $results = [ShopifyEnv::waitChild($one), ShopifyEnv::waitChild($two)];
        foreach ($results as $i => $run) {
            $this->assertSame(0, $run['exit'], "run $i: " . $run['stderr'] . $run['stdout']);
            $this->assertNotNull($run['result']);
        }
        $created = array_column(array_column($results, 'result'), 'created');
        $this->assertSame(1, count(array_filter($created)), 'exactly one of the two reservations created the link');
        $this->assertSame($results[0]['result']['id'], $results[1]['result']['id'], 'both ended on the same link');
        $conn = ShopifyEnv::connect(self::$db);
        $this->assertSame('1', ShopifyEnv::one($conn, "SELECT count(*) AS n FROM shopify_order_links WHERE order_gid = 'gid://shopify/Order/77777777777777777777'")['n']);
        pg_close($conn);
    }
}
