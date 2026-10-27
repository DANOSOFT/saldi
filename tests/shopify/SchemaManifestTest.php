<?php
// tests/shopify/SchemaManifestTest.php
//
// Schema manifest v1 (SD-733) against a throwaway PostgreSQL tenant: upgrade path and
// fresh-tenant path give the same catalog, replay keeps data and repairs a partial schema,
// a concurrent first install ends consistent with the marker written last, and a 13+ digit
// GID survives a text-column round trip. Skips when ShopifyEnv is not configured.
//
// History:
// 20261021 Sawaneh SD-733: created.
// 20261027 Sawaneh SD-736: marker checks follow SHOPIFY_SCHEMA_VERSION (manifest v2); the primary-key repair
//                 test uses shopify_lager, since shopify_stock_dirty is keyed on a serial id in v2.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';
require_once dirname(__DIR__, 2) . '/includes/shopify/schema.php';

final class SchemaManifestTest extends TestCase
{
    private static ?string $skip = null;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
    }

    public static function tearDownAfterClass(): void
    {
        ShopifyEnv::dropOwnedTenants();
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
    }

    private function freshTenant(string $suffix): string
    {
        $db = ShopifyEnv::testDb($suffix);
        ShopifyEnv::cloneTenant($db);
        return $db;
    }

    private function hasColumn(array $catalog, string $table, string $column): bool
    {
        foreach ($catalog['columns'] as $row) {
            if ($row['table_name'] === $table && $row['column_name'] === $column) {
                return true;
            }
        }
        return false;
    }

    private function assertRunOk(array $run, string $what): void
    {
        $this->assertSame(0, $run['exit'], "$what failed: " . $run['stderr'] . $run['stdout']);
        $this->assertNotNull($run['result'], "$what gave no JSON: " . $run['stdout']);
        $this->assertTrue($run['result']['result'], "$what returned false");
    }

    public function testUpgradeAndFreshPathsGiveTheSameCatalog(): void
    {
        $upgrade = $this->freshTenant('_upgrade');
        $fresh = $this->freshTenant('_fresh');
        $this->assertRunOk(ShopifyEnv::runSchema($upgrade, 'ensure'), 'ensure');
        $this->assertRunOk(ShopifyEnv::runSchema($fresh, 'opret'), 'opret');

        $a = ShopifyEnv::connect($upgrade);
        $b = ShopifyEnv::connect($fresh);
        $catalogA = ShopifyEnv::catalog($a);
        $catalogB = ShopifyEnv::catalog($b);
        pg_close($a);
        pg_close($b);

        $this->assertSame($catalogA, $catalogB);
        $tables = array_unique(array_column($catalogA['columns'], 'table_name'));
        foreach (array_keys(shopify_schema_catalog()) as $table) {
            $this->assertContains($table, $tables, "$table missing");
        }
        foreach (shopify_schema_legacy_columns() as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                $this->assertTrue($this->hasColumn($catalogA, $table, $column), "$table.$column missing");
            }
        }
        $indexes = array_column($catalogA['indexes'], 'indexdef', 'indexname');
        $this->assertStringContainsString("WHERE (state = ANY (ARRAY['queued'::text, 'running'::text]))", $indexes['shopify_jobs_active_uidx']);
        $this->assertStringStartsWith('CREATE UNIQUE INDEX', $indexes['shopify_jobs_slot_uidx']);
        $this->assertStringContainsString('WHERE (resolved_at IS NULL)', $indexes['shopify_errors_unresolved_uidx']);
        $this->assertStringContainsString("WHERE (rolle = 'saelgbar'::text)", $indexes['shopify_lager_prioritet_uidx']);
        $this->assertStringContainsString('WHERE is_primary', $indexes['shopify_shops_is_primary_uidx']);
        $constraints = array_column($catalogA['constraints'], 'definition', 'conname');
        $this->assertSame('UNIQUE (shop_id, topic, event_id)', $constraints['shopify_events_event_id_key']);
        $this->assertSame('UNIQUE (shop_id, order_gid)', $constraints['shopify_order_links_order_gid_key']);
        $this->assertSame('UNIQUE (ordre_id, direction)', $constraints['shopify_order_links_ordre_id_key']);
        $this->assertSame('UNIQUE (shop_id, customer_gid)', $constraints['shopify_customer_links_customer_gid_key']);
        $this->assertSame('UNIQUE (shop_id, adresser_id)', $constraints['shopify_customer_links_adresser_id_key']);
    }

    public function testReplayKeepsDataAndRepairsAPartialSchema(): void
    {
        $db = $this->freshTenant('_replay');
        $this->assertRunOk(ShopifyEnv::runSchema($db, 'ensure'), 'first ensure');

        $conn = ShopifyEnv::connect($db);
        ShopifyEnv::rows($conn, "INSERT INTO shopify_shops (shop_key, domain, afd, is_primary) VALUES ('hml', 'hml.myshopify.com', 1, true)");
        ShopifyEnv::rows($conn, "INSERT INTO shopify_lager (lager, rolle, prioritet) VALUES (1, 'saelgbar', 1)");
        ShopifyEnv::rows($conn, "INSERT INTO shopify_jobs (type, dedupe_key, payload) VALUES ('stock.push', 'hml:1', '{\"vare_id\": 1}')");
        $before = ShopifyEnv::catalog($conn);

        $replay = ShopifyEnv::runSchema($db, 'ensure');
        $this->assertRunOk($replay, 'replay');
        $this->assertSame(SHOPIFY_SCHEMA_VERSION, $replay['result']['version']);
        $this->assertSame($before, ShopifyEnv::catalog($conn));

        ShopifyEnv::rows($conn, 'ALTER TABLE shopify_jobs DROP COLUMN lock_token');
        ShopifyEnv::rows($conn, 'DROP INDEX shopify_jobs_active_uidx');
        ShopifyEnv::rows($conn, 'ALTER TABLE shopify_events DROP CONSTRAINT shopify_events_event_id_key');
        ShopifyEnv::rows($conn, 'DROP TABLE shopify_price_state');
        ShopifyEnv::rows($conn, 'ALTER TABLE ordrer DROP COLUMN oensket_levdato');
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp = 'shopify' AND var_name = 'schema_version'");
        $this->assertNotSame($before, ShopifyEnv::catalog($conn));

        $this->assertRunOk(ShopifyEnv::runSchema($db, 'ensure'), 'repair');
        $this->assertSame($before, ShopifyEnv::catalog($conn));
        $this->assertSame((string)SHOPIFY_SCHEMA_VERSION, ShopifyEnv::one($conn, "SELECT var_value FROM settings WHERE var_grp = 'shopify' AND var_name = 'schema_version'")['var_value']);
        $this->assertSame('hml', ShopifyEnv::one($conn, 'SELECT shop_key FROM shopify_shops')['shop_key']);
        $this->assertSame('saelgbar', ShopifyEnv::one($conn, 'SELECT rolle FROM shopify_lager WHERE lager = 1')['rolle']);
        $this->assertSame('hml:1', ShopifyEnv::one($conn, 'SELECT dedupe_key FROM shopify_jobs')['dedupe_key']);

        $ready = ShopifyEnv::runSchema($db, 'ready');
        $this->assertRunOk($ready, 'ready');
        pg_close($conn);
    }

    public function testInsideACallerTransactionItUsesSavepointsAndNeverCommits(): void
    {
        $rolledBack = $this->freshTenant('_txrollback');
        $run = ShopifyEnv::runSchema($rolledBack, 'tx_rollback');
        $this->assertRunOk($run, 'tx_rollback');
        $conn = ShopifyEnv::connect($rolledBack);
        $this->assertNull(ShopifyEnv::one($conn, "SELECT 1 FROM information_schema.tables WHERE table_name = 'shopify_shops'"));
        $this->assertNull(ShopifyEnv::one($conn, "SELECT 1 FROM settings WHERE var_grp = 'shopify'"));
        pg_close($conn);

        $committed = $this->freshTenant('_txcommit');
        $this->assertRunOk(ShopifyEnv::runSchema($committed, 'tx_commit'), 'tx_commit');
        $reference = $this->freshTenant('_txreference');
        $this->assertRunOk(ShopifyEnv::runSchema($reference, 'ensure'), 'reference');
        $a = ShopifyEnv::connect($committed);
        $b = ShopifyEnv::connect($reference);
        $this->assertSame(ShopifyEnv::catalog($b), ShopifyEnv::catalog($a));
        pg_close($a);
        pg_close($b);
    }

    private function markerVersion($conn): ?string
    {
        $row = ShopifyEnv::one($conn, "SELECT var_value FROM settings WHERE var_grp = 'shopify' AND var_name = 'schema_version'");
        return $row['var_value'] ?? null;
    }

    public function testAFailedStatementNeverWritesTheMarker(): void
    {
        $db = $this->freshTenant('_failed');
        $conn = ShopifyEnv::connect($db);
        ShopifyEnv::rows($conn, 'DROP TABLE ordrelinjer CASCADE');

        $run = ShopifyEnv::runSchema($db, 'ensure:webservice');
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertFalse($run['result']['result'], 'ensure reported success with ordrelinjer missing');
        $this->assertSame(0, $run['result']['version']);
        $this->assertNull($this->markerVersion($conn));
        $ready = ShopifyEnv::runSchema($db, 'ready:webservice');
        $this->assertFalse($ready['result']['result']);
        $this->assertNotNull(ShopifyEnv::one($conn, "SELECT 1 FROM information_schema.tables WHERE table_name = 'shopify_jobs'"), 'tables before the failure are kept');

        $legacy = ShopifyEnv::runSchema($db, 'ensure');
        $this->assertNotSame(0, $legacy['exit'], 'outside webservice mode db_modify alerts and exits');
        $this->assertNull($this->markerVersion($conn));
        pg_close($conn);
    }

    public function testRepairRestoresAMissingPrimaryKey(): void
    {
        $db = $this->freshTenant('_pkey');
        $this->assertRunOk(ShopifyEnv::runSchema($db, 'ensure'), 'ensure');
        $conn = ShopifyEnv::connect($db);
        $before = ShopifyEnv::catalog($conn);

        ShopifyEnv::rows($conn, 'ALTER TABLE shopify_lager DROP CONSTRAINT shopify_lager_pkey');
        ShopifyEnv::rows($conn, 'ALTER TABLE shopify_location_map DROP CONSTRAINT shopify_location_map_pkey');
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp = 'shopify' AND var_name = 'schema_version'");
        ShopifyEnv::rows($conn, 'INSERT INTO shopify_lager (lager) VALUES (7), (7)');

        $blocked = ShopifyEnv::runSchema($db, 'ensure:webservice');
        $this->assertFalse($blocked['result']['result'], 'a primary key over duplicate rows cannot be restored');
        $this->assertNull($this->markerVersion($conn));

        ShopifyEnv::rows($conn, 'DELETE FROM shopify_lager');
        $this->assertRunOk(ShopifyEnv::runSchema($db, 'ensure:webservice'), 'repair');
        $this->assertSame((string)SHOPIFY_SCHEMA_VERSION, $this->markerVersion($conn));
        $this->assertSame($before, ShopifyEnv::catalog($conn));

        ShopifyEnv::rows($conn, 'INSERT INTO shopify_lager (lager) VALUES (7)');
        $this->assertFalse(@pg_query($conn, 'INSERT INTO shopify_lager (lager) VALUES (7)'), 'duplicate lager accepted');
        pg_close($conn);
    }

    public function testSchemaIsNotReadyBeforeTheMarker(): void
    {
        $db = $this->freshTenant('_notready');
        $ready = ShopifyEnv::runSchema($db, 'ready');
        $this->assertSame(0, $ready['exit'], $ready['stderr']);
        $this->assertFalse($ready['result']['result']);
        $this->assertSame(0, $ready['result']['version']);
    }

    public function testConcurrentFirstInstallEndsConsistent(): void
    {
        $db = $this->freshTenant('_concurrent');
        $first = ShopifyEnv::startSchemaRun($db, 'ensure');
        $second = ShopifyEnv::startSchemaRun($db, 'ensure');
        $runs = [ShopifyEnv::waitChild($first), ShopifyEnv::waitChild($second)];
        foreach ($runs as $i => $run) {
            $this->assertRunOk($run, "concurrent run $i");
        }

        $reference = $this->freshTenant('_reference');
        $this->assertRunOk(ShopifyEnv::runSchema($reference, 'ensure'), 'reference');

        $a = ShopifyEnv::connect($db);
        $b = ShopifyEnv::connect($reference);
        $this->assertSame(ShopifyEnv::catalog($b), ShopifyEnv::catalog($a));
        $markers = ShopifyEnv::rows($a, "SELECT var_value FROM settings WHERE var_grp = 'shopify' AND var_name = 'schema_version'");
        $this->assertCount(1, $markers);
        $this->assertSame((string)SHOPIFY_SCHEMA_VERSION, $markers[0]['var_value']);
        pg_close($a);
        pg_close($b);
    }

    public function testThirteenPlusDigitGidSurvivesATextColumnRoundTrip(): void
    {
        $db = $this->freshTenant('_gid');
        $this->assertRunOk(ShopifyEnv::runSchema($db, 'ensure'), 'ensure');
        $conn = ShopifyEnv::connect($db);
        ShopifyEnv::rows($conn, "INSERT INTO shopify_shops (shop_key, domain) VALUES ('hml', 'hml.myshopify.com')");
        $shopId = (int)ShopifyEnv::one($conn, "SELECT id FROM shopify_shops WHERE shop_key = 'hml'")['id'];
        $gids = [
            shopify_gid('1234567890123', 'Order'),
            shopify_gid('98765432109876543210', 'Order'),
        ];
        foreach ($gids as $i => $gid) {
            ShopifyEnv::rows($conn, 'INSERT INTO shopify_order_links (shop_id, order_gid, direction) VALUES ($1, $2, $3)', [$shopId, $gid, 'web']);
            ShopifyEnv::rows($conn, 'INSERT INTO shopify_events (shop_id, topic, event_id, payload_b64) VALUES ($1, $2, $3, $4)', [$shopId, 'ORDERS_PAID', $gid, base64_encode($gid)]);
        }
        foreach ($gids as $gid) {
            $this->assertSame($gid, ShopifyEnv::one($conn, 'SELECT order_gid FROM shopify_order_links WHERE order_gid = $1', [$gid])['order_gid']);
            $this->assertSame($gid, ShopifyEnv::one($conn, 'SELECT event_id FROM shopify_events WHERE event_id = $1', [$gid])['event_id']);
        }
        $this->assertSame(count($gids), (int)ShopifyEnv::one($conn, 'SELECT count(*) AS n FROM shopify_order_links')['n']);
        pg_close($conn);
    }
}
