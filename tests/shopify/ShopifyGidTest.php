<?php
// tests/shopify/ShopifyGidTest.php
//
// Pure tests for shopify_gid() and the ShopifyEnv safety rules; no database needed.
//
// History:
// 20261021 Sawaneh SD-733: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';
require_once dirname(__DIR__, 2) . '/includes/shopify/schema.php';

final class ShopifyGidTest extends TestCase
{
    public function testNumericIdBecomesFullGid(): void
    {
        $this->assertSame('gid://shopify/Order/1234567890123', shopify_gid('1234567890123', 'Order'));
        $this->assertSame('gid://shopify/Product/98765432109876543210', shopify_gid(' 98765432109876543210 ', 'Product'));
    }

    public function testGidOfTheResourcePassesUnchanged(): void
    {
        $gid = 'gid://shopify/InventoryLevel/1234567890123?inventory_item_id=4567890123456';
        $this->assertSame($gid, shopify_gid($gid, 'InventoryLevel'));
        $this->assertSame('gid://shopify/Customer/7000000000001', shopify_gid('gid://shopify/Customer/7000000000001', 'Customer'));
    }

    public function testGidOfAnotherResourceIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        shopify_gid('gid://shopify/Product/1234567890123', 'Order');
    }

    public function testGarbageIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        shopify_gid('1234567890123abc', 'Order');
    }

    public function testMysqlTenantsAreLeftAlone(): void
    {
        $saved = $GLOBALS['db_type'] ?? null;
        $GLOBALS['db_type'] = 'mysqli';
        try {
            $this->assertFalse(ensure_shopify_schema());
            $this->assertFalse(opret_shopify_schema());
            $this->assertFalse(shopify_schema_ready());
        } finally {
            $GLOBALS['db_type'] = $saved;
        }
    }

    public function testEnvRefusesLiveTenants(): void
    {
        foreach (['pos_50', 'test_12'] as $name) {
            try {
                ShopifyEnv::assertSafeTestDb($name);
                $this->fail("$name was accepted as a test tenant");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($name, $e->getMessage());
            }
            try {
                ShopifyEnv::assertSafeTemplate($name);
                $this->fail("$name was accepted as a template");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($name, $e->getMessage());
            }
        }
    }

    public function testEnvRefusesTenantsOutsideTheNamingRule(): void
    {
        foreach (['saldi_2', 'saldi_shopify_test', 'shopify_test_x', 'saldi_shopify_test_A'] as $name) {
            try {
                ShopifyEnv::assertSafeTestDb($name);
                $this->fail("$name was accepted as a test tenant");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('saldi_shopify_test_', $e->getMessage());
            }
        }
        $this->assertSame('saldi_shopify_test_sawaneh', ShopifyEnv::assertSafeTestDb('saldi_shopify_test_sawaneh'));
    }

    public function testEnvHasNoCredentialFallback(): void
    {
        $saved = [];
        foreach (['SALDI_SHOPIFY_PGUSER', 'SALDI_SHOPIFY_PGPASS'] as $var) {
            $saved[$var] = getenv($var);
            putenv($var);
        }
        try {
            foreach (['pgUser', 'pgPass'] as $method) {
                try {
                    ShopifyEnv::$method();
                    $this->fail("$method returned a value without the environment variable");
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('no credential fallback', $e->getMessage());
                }
            }
        } finally {
            foreach ($saved as $var => $value) {
                if (is_string($value)) {
                    putenv("$var=$value");
                }
            }
        }
    }
}
