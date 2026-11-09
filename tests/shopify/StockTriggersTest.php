<?php
// tests/shopify/StockTriggersTest.php
//
// Stock triggers (SD-736, core edit 10) on a throwaway tenant: no shop row means no triggers;
// saving the first shop installs them in its transaction; nothing is captured until a shop has
// stock_capture_enabled; capture on lagerstatus, styklister and varer with fan-out to the sets
// above through styklister.indgaar_i, ending on a cycle and at depth 10; a modtime-only or
// location-only update marks nothing; a rolled-back write leaves nothing; two crossing writers
// never deadlock; the first save during a sale does not deadlock it; a later save takes no table
// lock; a forced manifest pass reinstalls dropped triggers; a v1 dirty table is migrated, and a
// save before that installs nothing. Skips without ShopifyEnv.
//
// History:
// 20261027 Sawaneh SD-736: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';
require_once dirname(__DIR__, 2) . '/includes/shopify/schema.php';

final class StockTriggersTest extends TestCase
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
            self::$db = ShopifyEnv::testDb('_t');
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
        foreach (['lagerstatus', 'styklister', 'varer'] as $table) {
            foreach (['shopify_dirty_insdel', 'shopify_dirty_upd'] as $name) {
                $this->sql("DROP TRIGGER IF EXISTS $name ON $table");
            }
        }
        $this->sql('DROP FUNCTION IF EXISTS shopify_mark_stock_dirty()');
        foreach (['shopify_stock_dirty', 'shopify_shops', 'lagerstatus', 'styklister'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM grupper WHERE art = 'RA'");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
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

    /** @return list<int> dirty vare_ids, sorted and distinct */
    private function dirty(): array
    {
        return array_map('intval', array_column($this->sql('SELECT DISTINCT vare_id FROM shopify_stock_dirty ORDER BY vare_id'), 'vare_id'));
    }

    private function triggerCount(): int
    {
        return (int)$this->sql("SELECT count(*) AS n FROM pg_trigger WHERE tgname LIKE 'shopify_dirty_%'")[0]['n'];
    }

    private function saveShop(bool $capture): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_triggers.php', ['save', self::$db, $capture ? 'capture' : '']));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'];
    }

    /** Sets: component 1 and 2 in set 100, set 100 in set 200. */
    private function bom(): void
    {
        $this->sql('INSERT INTO styklister (vare_id, indgaar_i, antal) VALUES (1, 100, 1), (2, 100, 2), (100, 200, 1)');
        $this->sql('DELETE FROM shopify_stock_dirty');
    }

    /** @return int|null the child's exit code, or null while it still runs after $seconds */
    private function childExit(array $child, float $seconds): ?int
    {
        $until = microtime(true) + $seconds;
        do {
            $status = proc_get_status($child['proc']);
            if (!$status['running']) {
                return $status['exitcode'];
            }
            usleep(20000);
        } while (microtime(true) < $until);
        return null;
    }

    public function testNoShopRowMeansNoTriggersAndNoCapture(): void
    {
        $run = ShopifyEnv::runSchema(self::$db, 'force');
        $this->assertSame(0, $run['exit'], $run['stderr']);
        $this->assertSame(0, $this->triggerCount(), 'installs without a shop get no triggers');
        $this->assertSame([], $this->sql("SELECT 1 FROM pg_proc WHERE proname = 'shopify_mark_stock_dirty'"));
        $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES (1, 1, 5)');
        $this->assertSame([], $this->dirty());
    }

    public function testSavingTheFirstShopInstallsTheTriggersOnce(): void
    {
        $this->assertSame(6, $this->saveShop(false)['triggers'], 'two triggers on each of the three tables');
        $this->assertSame(6, $this->saveShop(false)['triggers'], 'a second save adds none');
        $this->assertCount(1, $this->sql("SELECT 1 FROM pg_indexes WHERE indexname = 'shopify_styklister_vare_idx'"));
    }

    public function testNothingIsCapturedUntilAShopEnablesCapture(): void
    {
        $this->saveShop(false);
        $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES (1, 1, 5)');
        $this->sql('UPDATE lagerstatus SET beholdning = 4 WHERE vare_id = 1');
        $this->assertSame([], $this->dirty(), 'merged triggers change nothing before go-live step 2');
        $this->sql('UPDATE shopify_shops SET stock_capture_enabled = true');
        $this->sql('UPDATE lagerstatus SET beholdning = 3 WHERE vare_id = 1');
        $this->assertSame([1], $this->dirty());
    }

    public function testCaptureOnLagerstatusWithFanOutToTheSetsAbove(): void
    {
        $this->saveShop(true);
        $this->bom();
        $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES (1, 1, 5)');
        $this->assertSame([1, 100, 200], $this->dirty(), 'the component, its set and the set above');
        $this->sql('DELETE FROM shopify_stock_dirty');
        $this->sql("UPDATE lagerstatus SET lok1 = 'A-12' WHERE vare_id = 1");
        $this->assertSame([], $this->dirty(), 'a shelf location change is not a stock change');
        $this->sql('UPDATE lagerstatus SET beholdning = 4 WHERE vare_id = 1');
        $this->assertSame([1, 100, 200], $this->dirty());
        $this->sql('DELETE FROM shopify_stock_dirty');
        $this->sql('DELETE FROM lagerstatus WHERE vare_id = 1');
        $this->assertSame([1, 100, 200], $this->dirty());
        $this->sql('DELETE FROM shopify_stock_dirty');
        $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES (2, 7, 1)');
        $this->assertSame([7], $this->dirty(), 'an item in no set marks only itself');
        $this->sql('DELETE FROM shopify_stock_dirty');
        $this->sql('UPDATE lagerstatus SET lager = 3 WHERE vare_id = 7');
        $this->assertSame([7], $this->dirty(), 'moving stock to another lager');
        $this->sql('DELETE FROM shopify_stock_dirty');
        $this->sql('UPDATE lagerstatus SET variant_id = 1 WHERE vare_id = 7');
        $this->assertSame([7], $this->dirty(), 'moving stock to a variant');
    }

    public function testTheWalkEndsOnACycleAndAtDepthTen(): void
    {
        $this->saveShop(true);
        $this->sql('INSERT INTO styklister (vare_id, indgaar_i, antal) VALUES (100, 200, 1), (200, 100, 1)');
        $this->sql('DELETE FROM shopify_stock_dirty');
        $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES (1, 100, 1)');
        $this->assertSame([100, 200], $this->dirty(), 'a set inside itself ends the walk');
        $this->sql('DELETE FROM styklister');
        for ($i = 1; $i <= 12; $i++) {
            $this->sql('INSERT INTO styklister (vare_id, indgaar_i, antal) VALUES ($1, $2, 1)', [$i, $i + 1]);
        }
        $this->sql('DELETE FROM shopify_stock_dirty');
        $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES (1, 1, 1)');
        $this->assertSame(range(1, 11), $this->dirty(), 'the item and ten levels of sets above it');
    }

    public function testCaptureOnStyklisterMarksTheSetNotTheComponent(): void
    {
        $this->saveShop(true);
        $this->bom();
        $this->sql('INSERT INTO styklister (vare_id, indgaar_i, antal) VALUES (3, 100, 1)');
        $this->assertSame([100, 200], $this->dirty());
        $this->sql('DELETE FROM shopify_stock_dirty');
        $this->sql('UPDATE styklister SET posnr = 9 WHERE vare_id = 3');
        $this->assertSame([], $this->dirty(), 'a position number is not a stock change');
        $this->sql('UPDATE styklister SET indgaar_i = 300 WHERE vare_id = 3');
        $this->assertSame([100, 200, 300], $this->dirty(), 'moving a component marks the old and the new set');
    }

    public function testCaptureOnVarerOnlyForStockColumns(): void
    {
        $this->saveShop(true);
        $id = (int)$this->sql('SELECT id FROM varer ORDER BY id LIMIT 1')[0]['id'];
        $this->sql('UPDATE varer SET beskrivelse = beskrivelse WHERE id = $1', [$id]);
        $this->assertSame([], $this->dirty(), 'the modtime trigger fires on every update; that alone marks nothing');
        $this->sql('UPDATE varer SET beholdning = coalesce(beholdning, 0) + 1 WHERE id = $1', [$id]);
        $this->assertSame([$id], $this->dirty());
        $this->sql('DELETE FROM shopify_stock_dirty');
        $this->sql("UPDATE varer SET samlevare = CASE WHEN samlevare = 'on' THEN '' ELSE 'on' END WHERE id = $1", [$id]);
        $this->assertSame([$id], $this->dirty());
        $this->sql('DELETE FROM shopify_stock_dirty');
        $this->sql('UPDATE varer SET gruppe = coalesce(gruppe, 0) + 1 WHERE id = $1', [$id]);
        $this->assertSame([$id], $this->dirty());
    }

    public function testARolledBackWriteLeavesNoDirtyRow(): void
    {
        $this->saveShop(true);
        $this->sql('BEGIN');
        $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES (1, 1, 5)');
        $this->sql('ROLLBACK');
        $this->assertSame([], $this->dirty());
    }

    public function testCrossingWritersOnOneSetDoNotWaitOnEachOther(): void
    {
        $this->saveShop(true);
        $this->bom();
        $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES (1, 1, 10), (1, 2, 10)');
        $this->sql('DELETE FROM shopify_stock_dirty');
        $other = ShopifyEnv::connect(self::$db);
        try {
            $this->sql('BEGIN');
            $this->sql('UPDATE lagerstatus SET beholdning = beholdning - 1 WHERE vare_id = 1');
            ShopifyEnv::rows($other, 'BEGIN');
            pg_send_query($other, 'UPDATE lagerstatus SET beholdning = beholdning - 1 WHERE vare_id = 2');
            $waited = 0;
            while (pg_connection_busy($other) && $waited < 30) {
                usleep(100000);
                $waited++;
            }
            if (pg_connection_busy($other)) {
                pg_cancel_query($other);
                $this->sql('ROLLBACK');
                $this->fail('the second sale waited on the first one\'s dirty mark of set 100; crossing writers would deadlock');
            }
            $result = pg_get_result($other);
            $this->assertSame(PGSQL_COMMAND_OK, pg_result_status($result), (string)pg_result_error($result));
            while (pg_get_result($other) !== false) {
            }
            pg_send_query($this->conn, 'UPDATE lagerstatus SET beholdning = beholdning - 1 WHERE vare_id = 2');
            usleep(300000);
            ShopifyEnv::rows($other, 'COMMIT');
            $result = pg_get_result($this->conn);
            $this->assertSame(PGSQL_COMMAND_OK, pg_result_status($result), 'no deadlock: ' . pg_result_error($result));
            while (pg_get_result($this->conn) !== false) {
            }
            $this->sql('COMMIT');
        } finally {
            pg_close($other);
        }
        $this->assertSame([1, 2, 100, 200], $this->dirty());
    }

    public function testTheFirstSaveDuringASaleDoesNotDeadlockIt(): void
    {
        $id = (int)$this->sql('SELECT id FROM varer ORDER BY id LIMIT 1')[0]['id'];
        $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES (1, $1, 10)', [$id]);
        $this->sql('BEGIN');
        $this->sql('UPDATE varer SET beholdning = coalesce(beholdning, 0) - 1 WHERE id = $1', [$id]);
        $child = ShopifyEnv::startChild('run_triggers.php', ['save', self::$db, '']);
        $until = microtime(true) + 10;
        while (!$this->sql("SELECT 1 FROM pg_locks WHERE relation = 'varer'::regclass AND NOT granted") && microtime(true) < $until) {
            usleep(20000);
        }
        try {
            $this->sql('UPDATE lagerstatus SET beholdning = beholdning - 1 WHERE vare_id = $1', [$id]);
            $this->sql('COMMIT');
        } finally {
            $exit = $this->childExit($child, 10);
            $run = ShopifyEnv::waitChild($child);
        }
        $this->assertSame(0, $exit, 'the save fails or deadlocks: ' . $run['stderr'] . $run['stdout']);
        $this->assertSame(6, $this->triggerCount());
    }

    public function testALaterSaveTakesNoTableLock(): void
    {
        $this->saveShop(false);
        $this->sql('BEGIN');
        $this->sql('INSERT INTO styklister (vare_id, indgaar_i, antal) VALUES (1, 100, 1)');
        $child = ShopifyEnv::startChild('run_triggers.php', ['save', self::$db, '']);
        $exit = $this->childExit($child, 5);
        $this->sql('ROLLBACK');
        $run = ShopifyEnv::waitChild($child);
        $this->assertNotNull($exit, 'the second save waited on an open styklister write');
        $this->assertSame(0, $exit, $run['stderr'] . $run['stdout']);
    }

    public function testAForcedManifestPassReinstallsDroppedTriggers(): void
    {
        $this->saveShop(false);
        $this->sql('DROP TRIGGER shopify_dirty_upd ON varer');
        $this->assertSame(5, $this->triggerCount());
        $run = ShopifyEnv::runSchema(self::$db, 'force');
        $this->assertSame(0, $run['exit'], $run['stderr']);
        $this->assertSame(6, $this->triggerCount());
    }

    public function testAVersionOneDirtyTableIsMigrated(): void
    {
        $this->sql('DROP TABLE shopify_stock_dirty');
        $this->sql('CREATE TABLE shopify_stock_dirty (vare_id integer NOT NULL, dirtied_at timestamp NOT NULL DEFAULT now(), CONSTRAINT shopify_stock_dirty_pkey PRIMARY KEY (vare_id))');
        $this->sql("UPDATE settings SET var_value = '1' WHERE var_grp = 'shopify' AND var_name = 'schema_version'");
        $this->assertSame(0, $this->saveShop(false)['triggers'], 'no triggers on the v1 table');
        $run = ShopifyEnv::runSchema(self::$db, 'ensure');
        $this->assertSame(0, $run['exit'], $run['stderr']);
        $this->assertSame(SHOPIFY_SCHEMA_VERSION, $run['result']['version']);
        $this->assertSame('id', $this->sql("SELECT a.attname FROM pg_constraint c JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey) WHERE c.conname = 'shopify_stock_dirty_pkey'")[0]['attname']);
        $this->assertCount(1, $this->sql("SELECT 1 FROM pg_indexes WHERE indexname = 'shopify_stock_dirty_vare_idx'"));
        $this->assertSame(6, $this->triggerCount(), 'the login pass that migrates installs the triggers for the saved shop');
        $this->sql('INSERT INTO shopify_stock_dirty (vare_id) VALUES (5), (5)');
        $this->assertCount(2, $this->sql('SELECT id FROM shopify_stock_dirty WHERE vare_id = 5'), 'append-only: the same item can be marked twice');
    }
}
