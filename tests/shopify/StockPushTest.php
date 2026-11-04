<?php
// tests/shopify/StockPushTest.php
//
// The stock push on a throwaway tenant against the shared fake store (SD-756): 501 quantities go
// in calls of 250, 250 and 1; a stock change reaches the Ballerup location of all three shops in
// one worker pass (K12); a call that may have applied, or failed, leaves shopify_stock_state as it
// was and is resent with the same key and input, and a later change goes out after it; an item
// behind an open move barrier waits, its job yielding, for its release or expiry; a change that
// commits after the push read is sent even when its dirty row has the lower id; a drain skips the rows another one
// holds, so each dirty row is drained once; a shop that captures without publishing keeps its
// marks; a final failure keeps a call that may have applied; an item Shopify refuses is dropped with a warning and
// the rest is sent; a level missing at a location is activated and then set; a sellable lager without a
// location is skipped with a warning; a dead job's call goes out with the next drain, and a waiting
// job keeps its backoff; an item linked later is sent; a shop can't publish without capturing. Skips without ShopifyEnv.
//
// History:
// 20261104 CL/SZ SD-756: created.
// 20261104 CL/SZ SD-756 review: dead job, backoff, late link, publish without capture; the seeded state follows the link's time.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class StockPushTest extends TestCase
{
    private const SHOPS = ['hml', 'hms', 'di'];
    /** lager => Shopify location name; 2 is Ballerup. */
    private const LOCATIONS = [1 => 'Mårum', 2 => 'Ballerup'];

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
            self::$db = ShopifyEnv::testDb('_k');
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
        if (self::$db !== '') {
            ShopifyEnv::removeTempDir(self::$db);
        }
        ShopifyEnv::unregisterTenants();
        ShopifyEnv::dropOwnedTenants();
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $this->conn = ShopifyEnv::connect(self::$db);
        $this->state = tempnam(sys_get_temp_dir(), 'sd756_');
        unlink($this->state);
        foreach (['lagerstatus', 'styklister', 'varer'] as $table) {
            foreach (['shopify_dirty_insdel', 'shopify_dirty_upd'] as $name) {
                $this->sql("DROP TRIGGER IF EXISTS $name ON $table");
            }
        }
        foreach (['shopify_stock_state', 'shopify_stock_dirty', 'shopify_move_barriers', 'shopify_product_links', 'shopify_location_map',
            'shopify_lager', 'shopify_jobs', 'shopify_errors', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM settings WHERE var_grp = 'shopify_state' AND var_name LIKE 'stock.%'");
        $this->sql("DELETE FROM lagerstatus WHERE vare_id IN (SELECT id FROM varer WHERE varenr LIKE 'SD756-%')");
        $this->sql("DELETE FROM varer WHERE varenr LIKE 'SD756-%'");
        $this->sql("DELETE FROM grupper WHERE art = 'RA'");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
        $this->sql("INSERT INTO shopify_lager (lager, rolle, prioritet) VALUES (1, 'saelgbar', 1), (2, 'saelgbar', 2), (3, 'udstilling', NULL)");
        foreach (self::SHOPS as $i => $key) {
            $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, stock_capture_enabled, stock_publish_enabled, stock_baseline_at)
                VALUES ($1, $2, $3, true, true, now())", [$key, "$key-fake.myshopify.com", $i === 0 ? 't' : 'f']);
        }
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

    /** A plain item with stock per lager. */
    private function item(string $varenr, array $stock = []): int
    {
        $id = (int)$this->sql("INSERT INTO varer (varenr, beskrivelse) VALUES ($1, $2) RETURNING id", ["SD756-$varenr", "Havestol $varenr"])[0]['id'];
        foreach ($stock as $lager => $qty) {
            $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES ($1, $2, $3)', [$lager, $id, $qty]);
        }
        return $id;
    }

    /** Runs steps in a child against the fake store kept in $this->state. */
    private function steps(array ...$steps): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_stock.php', [self::$db, $this->state, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertIsArray($run['result'], $run['stdout'] . $run['stderr']);
        return $run['result'];
    }

    /** The steps that set up the given shops with both locations and the items linked in each; the links' own marks are cleared. */
    private function shops(array $items, array $shops = self::SHOPS): array
    {
        $steps = [];
        foreach ($shops as $shop) {
            $steps[] = ['shop', $shop, self::LOCATIONS];
            foreach ($items as $id) {
                $steps[] = ['seed', $shop, $id];
            }
        }
        $steps[] = ['clean'];
        return $steps;
    }

    private function dirty(array $ids): void
    {
        $this->sql('INSERT INTO shopify_stock_dirty (vare_id) SELECT unnest($1::int[])', ['{' . implode(',', $ids) . '}']);
    }

    /** @return list<array<string, mixed>> the inventorySetQuantities calls */
    private static function sets(array $run): array
    {
        return array_values(array_filter($run['calls'], fn(array $c): bool => $c['operation'] === 'stockSet'));
    }

    private function assertNoException(array $run): void
    {
        foreach ($run['results'] as $r) {
            $this->assertArrayNotHasKey('exception', is_array($r) ? $r : [], json_encode($r));
        }
    }

    public function test_501_quantities_go_in_calls_of_250_250_and_1(): void
    {
        $this->sql("UPDATE shopify_lager SET rolle = 'ingen', prioritet = NULL WHERE lager = 2");
        $ids = [];
        for ($i = 1; $i <= 501; $i++) {
            $ids[] = $this->item((string)$i, [1 => $i]);
        }
        $this->steps(...$this->shops($ids, ['hml']));
        $this->dirty($ids);

        $run = $this->steps(['drain', 1000], ['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame(['drained' => 501, 'queued' => 3], $run['results'][0]);
        $this->assertSame([250, 250, 1], array_map(fn(array $c): int => count($c['variables']['input']['quantities']), self::sets($run)));
        $keys = array_map(fn(array $c): string => $c['variables']['key'], self::sets($run));
        $this->assertCount(3, array_unique($keys), 'each call has a key of its own');
        foreach (self::sets($run) as $call) {
            $this->assertSame('available', $call['variables']['input']['name']);
            $this->assertNull($call['variables']['input']['quantities'][0]['changeFromQuantity']);
        }
        $this->assertSame(7, $run['levels']['hml']['SD756-7']['Mårum']);
        $this->assertSame(501, (int)$this->sql('SELECT count(*) AS n FROM shopify_stock_state')[0]['n']);

        $this->dirty($ids);
        $again = $this->steps(['drain', 1000], ['push', 'hml']);
        $this->assertSame([], self::sets($again), 'what Shopify already has is not sent again');
    }

    public function test_a_ballerup_sale_reaches_ballerup_in_all_three_shops_in_one_worker_pass(): void
    {
        $id = $this->item('stol', [1 => 7]);
        $this->steps(...$this->shops([$id]), ...[['triggers']]);
        $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES (2, $1, 5)', [$id]);
        $first = $this->steps(['worker', 30]);
        $this->assertNoException($first);
        foreach (self::SHOPS as $shop) {
            $this->assertSame(['Mårum' => 7, 'Ballerup' => 5], $first['levels'][$shop]['SD756-stol'], $shop);
        }

        // the POS sale draws one from Ballerup
        $this->sql('UPDATE lagerstatus SET beholdning = beholdning - 1 WHERE vare_id = $1 AND lager = 2', [$id]);
        $sale = $this->steps(['worker', 30]);
        $this->assertNoException($sale);
        $this->assertSame(3, $sale['results'][0]['done'], json_encode($sale['results'][0]));
        foreach (self::SHOPS as $shop) {
            $this->assertSame(['Mårum' => 7, 'Ballerup' => 4], $sale['levels'][$shop]['SD756-stol'], $shop);
        }
        $sets = self::sets($sale);
        $this->assertCount(3, $sets, 'one call per shop');
        foreach ($sets as $call) {
            $this->assertCount(1, $call['variables']['input']['quantities'], 'only Ballerup changed');
            $this->assertSame(4, $call['variables']['input']['quantities'][0]['quantity']);
        }

        $quiet = $this->steps(['worker', 30]);
        $this->assertSame([], self::sets($quiet), 'nothing changed, nothing sent');
    }

    public function test_a_call_that_may_have_applied_is_resent_with_the_same_key_and_input(): void
    {
        $id = $this->item('bord', [1 => 5]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->dirty([$id]);

        $lost = $this->steps(['drain'], ['fail', 'inventorySetQuantities', 'timeout_after_applyx4'], ['push', 'hml']);
        $this->assertSame('RuntimeException', $lost['results'][2]['exception'] ?? null, json_encode($lost['results']));
        $this->assertSame(5, $lost['levels']['hml']['SD756-bord']['Mårum'], 'Shopify applied the first attempt');
        $this->assertSame([], $this->sql('SELECT * FROM shopify_stock_state'), 'nothing is acknowledged without an answer');
        $first = self::sets($lost)[0]['variables'];
        $this->assertCount(1, array_unique(array_map(fn(array $c): string => $c['variables']['key'], self::sets($lost))), 'the retries keep the key');

        // a sale while the answer is missing
        $this->sql('UPDATE lagerstatus SET beholdning = 4 WHERE vare_id = $1', [$id]);
        $this->dirty([$id]);
        $run = $this->steps(['drain'], ['push', 'hml']);
        $this->assertNoException($run);
        $sets = self::sets($run);
        $this->assertSame($first, $sets[0]['variables'], 'the pending call goes first, unchanged');
        $this->assertSame(4, $sets[1]['variables']['input']['quantities'][0]['quantity']);
        $this->assertNotSame($first['key'], $sets[1]['variables']['key']);
        $this->assertSame(4, $run['levels']['hml']['SD756-bord']['Mårum']);
        $this->assertSame('4.000', $this->sql('SELECT quantity FROM shopify_stock_state WHERE lager = 1')[0]['quantity']);
    }

    public function test_a_failed_call_leaves_the_state_and_is_resent_with_the_same_key(): void
    {
        $id = $this->item('lampe', [1 => 3]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->sql('INSERT INTO shopify_stock_state (shop_id, vare_id, lager, quantity, acknowledged_at) SELECT l.shop_id, $1, 1, 2, l.updated_at FROM shopify_product_links l JOIN shopify_shops s ON s.id = l.shop_id WHERE s.shop_key = \'hml\' AND l.vare_id = $1', [$id]);
        $this->dirty([$id]);

        $failed = $this->steps(['drain'], ['fail', 'inventorySetQuantities', 'http_500x4'], ['push', 'hml']);
        $this->assertSame('RuntimeException', $failed['results'][2]['exception'] ?? null);
        $this->assertSame('2.000', $this->sql('SELECT quantity FROM shopify_stock_state WHERE lager = 1')[0]['quantity'], 'still the last acknowledged quantity');
        $key = self::sets($failed)[0]['variables']['key'];

        $run = $this->steps(['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame([$key], array_map(fn(array $c): string => $c['variables']['key'], self::sets($run)));
        $this->assertSame(3, $run['levels']['hml']['SD756-lampe']['Mårum']);
        $this->assertSame('3.000', $this->sql('SELECT quantity FROM shopify_stock_state WHERE lager = 1')[0]['quantity']);
    }

    public function test_an_item_behind_an_open_barrier_waits_for_its_release_or_expiry(): void
    {
        $this->sql("UPDATE shopify_lager SET rolle = 'ingen', prioritet = NULL WHERE lager = 2");
        $held = $this->item('held', [1 => 2]);
        $free = $this->item('free', [1 => 6]);
        $late = $this->item('late', [1 => 9]);
        $this->steps(...$this->shops([$held, $free, $late], ['hml']), ...[['triggers']]);
        $this->steps(['open', 'hml', 900001, [$held]], ['open', 'hml', 900002, [$late]]);
        $this->dirty([$held, $free, $late]);
        $due = fn() => $this->sql("UPDATE shopify_jobs SET next_run_at = now() WHERE type = 'stock.push' AND state = 'queued'");

        $run = $this->steps(['worker', 30]);
        $this->assertNoException($run);
        $this->assertSame([6], array_column(self::sets($run)[0]['variables']['input']['quantities'], 'quantity'), 'only the free item is sent');
        $this->assertSame(1, $run['results'][0]['yielded'], 'the job waits for the held items');

        // a minute later, with no stock change and no sweep: the expired barrier no longer holds its item
        $this->sql("UPDATE shopify_move_barriers SET expires_at = now() - interval '1 second' WHERE ordre_id = 900002");
        $due();
        $expired = $this->steps(['worker', 30]);
        $this->assertSame([9], array_column(self::sets($expired)[0]['variables']['input']['quantities'], 'quantity'), 'an expired barrier no longer holds its item');

        $this->steps(['release', 'hml', 900001]);
        $due();
        $released = $this->steps(['worker', 30]);
        $this->assertNoException($released);
        $this->assertSame([2], array_column(self::sets($released)[0]['variables']['input']['quantities'], 'quantity'));
        $this->assertSame([], $this->sql("SELECT id FROM shopify_jobs WHERE type = 'stock.push' AND state = 'queued'"), 'nothing is left to wait for');
        // Ballerup is not sellable here, so it keeps Shopify's 0
        foreach (['SD756-free' => 6, 'SD756-held' => 2, 'SD756-late' => 9] as $sku => $qty) {
            $this->assertSame(['Mårum' => $qty, 'Ballerup' => 0], $released['levels']['hml'][$sku], $sku);
        }
    }

    public function test_a_change_that_commits_after_the_push_read_is_sent_even_with_a_lower_dirty_id(): void
    {
        $this->sql("UPDATE shopify_lager SET rolle = 'ingen', prioritet = NULL WHERE lager = 2");
        $id = $this->item('sofa', [1 => 5]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->dirty([$id]);
        $this->dirty([$id]);
        $low = (int)$this->sql('SELECT min(id) AS id FROM shopify_stock_dirty')[0]['id'];
        // the drain takes the newer row; the older one belongs to a sale that has not committed yet
        $this->sql('DELETE FROM shopify_stock_dirty WHERE id = $1', [$low]);
        $read = $this->steps(['drain'], ['next', 'hml']);
        $this->assertSame(5, $read['results'][1]['rows'][0]['quantity']);

        // the sale commits with its lower id after the push read the stock
        $this->sql('UPDATE lagerstatus SET beholdning = 4 WHERE vare_id = $1', [$id]);
        $this->sql('INSERT INTO shopify_stock_dirty (id, vare_id) VALUES ($1, $2)', [$low, $id]);
        $run = $this->steps(['drain'], ['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame([5, 4], array_map(fn(array $c): int => $c['variables']['input']['quantities'][0]['quantity'], self::sets($run)));
        $this->assertSame(4, $run['levels']['hml']['SD756-sofa']['Mårum']);
    }

    public function test_a_drain_skips_the_rows_another_holds_so_each_row_is_drained_once(): void
    {
        $ids = [];
        for ($i = 1; $i <= 30; $i++) {
            $ids[] = $this->item("r$i", [1 => $i]);
        }
        $this->steps(...$this->shops($ids, ['hml']));
        $this->dirty($ids);

        // a second connection holds the first ten rows mid-drain
        $other = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($other, 'BEGIN');
        $held = ShopifyEnv::rows($other, 'DELETE FROM shopify_stock_dirty WHERE id IN (SELECT id FROM shopify_stock_dirty ORDER BY id LIMIT 10 FOR UPDATE SKIP LOCKED) RETURNING vare_id');
        $this->assertCount(10, $held);
        $run = $this->steps(['drain', 500]);
        $this->assertSame(['drained' => 20, 'queued' => 3], $run['results'][0], 'the held rows are skipped, not waited on');
        ShopifyEnv::rows($other, 'ROLLBACK');
        pg_close($other);

        $rest = $this->steps(['drain', 500], ['drain', 500], ['push', 'hml']);
        $this->assertSame(['drained' => 10, 'queued' => 3], $rest['results'][0]);
        $this->assertSame(['drained' => 0, 'queued' => 0], $rest['results'][1]);
        $this->assertSame([], $this->sql('SELECT id FROM shopify_stock_dirty'));
        $this->assertSame(1, (int)$this->sql("SELECT count(*) AS n FROM shopify_jobs j JOIN shopify_shops s ON s.id = j.shop_id WHERE j.type = 'stock.push' AND s.shop_key = 'hml'")[0]['n'],
            'one job per shop');
        $pushed = array_merge(...array_map(fn(array $c): array => $c['variables']['input']['quantities'], self::sets($rest)));
        $this->assertCount(60, $pushed, 'each item once, at both sellable lagre');
        $this->assertCount(60, array_unique(array_map(fn(array $q): string => $q['inventoryItemId'] . $q['locationId'], $pushed)));
    }

    public function test_a_shop_that_captures_without_publishing_keeps_its_marks(): void
    {
        $id = $this->item('pude', [1 => 4]);
        $this->steps(...$this->shops([$id], ['hms']));
        $this->sql("UPDATE shopify_shops SET stock_baseline_at = NULL WHERE shop_key = 'hms'");
        $this->dirty([$id]);

        $gated = $this->steps(['drain'], ['push', 'hms']);
        $this->assertSame(['outcome' => 'yield', 'sent' => 0, 'gate' => 'closed', 'run_after' => 60], $gated['results'][1], 'the job looks again while marks wait');
        $this->assertSame([], self::sets($gated));

        $this->sql("UPDATE shopify_shops SET stock_baseline_at = now() WHERE shop_key = 'hms'");
        $open = $this->steps(['push', 'hms']);
        $this->assertSame(4, $open['levels']['hms']['SD756-pude']['Mårum'], 'the mark made while gated is still there');
    }

    public function test_a_final_failure_keeps_a_call_that_may_have_applied_until_the_shop_answers(): void
    {
        $this->sql("UPDATE shopify_lager SET rolle = 'ingen', prioritet = NULL WHERE lager = 2");
        $id = $this->item('kurv', [1 => 5]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->dirty([$id]);
        $this->steps(['drain'], ['push', 'hml']);

        // a sale; the call applies but its answer is lost, and the sale is then taken back
        $this->sql('UPDATE lagerstatus SET beholdning = 4 WHERE vare_id = $1', [$id]);
        $this->dirty([$id]);
        $lost = $this->steps(['drain'], ['fail', 'inventorySetQuantities', 'timeout_after_applyx4'], ['push', 'hml']);
        $this->assertSame(4, $lost['levels']['hml']['SD756-kurv']['Mårum']);
        $this->sql('UPDATE lagerstatus SET beholdning = 5 WHERE vare_id = $1', [$id]);
        $this->dirty([$id]);
        $key = self::sets($lost)[0]['variables']['key'];

        $denied = $this->steps(['drain'], ['fail', 'inventorySetQuantities', 'http_401'], ['push', 'hml']);
        $this->assertSame('RuntimeException', $denied['results'][2]['exception'] ?? null);
        $this->assertSame('error', $this->sql("SELECT severity FROM shopify_errors WHERE ref = 'inventorySetQuantities' AND resolved_at IS NULL")[0]['severity'] ?? null);
        $this->assertSame([$key], array_map(fn(array $c): string => $c['variables']['key'], self::sets($denied)), 'the pending call is kept');

        $run = $this->steps(['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame([$key], array_slice(array_map(fn(array $c): string => $c['variables']['key'], self::sets($run)), 0, 1), 'resent first once the shop answers');
        $this->assertSame(5, $run['levels']['hml']['SD756-kurv']['Mårum'], 'Shopify ends at what Saldi has');
        $this->assertSame('5.000', $this->sql('SELECT quantity FROM shopify_stock_state')[0]['quantity']);
    }

    public function test_an_item_shopify_refuses_is_dropped_with_a_warning_and_the_rest_is_sent(): void
    {
        $this->sql("UPDATE shopify_lager SET rolle = 'ingen', prioritet = NULL WHERE lager = 2");
        $bad = $this->item('bad', [1 => 1]);
        $good = $this->item('good', [1 => 8]);
        $this->steps(...$this->shops([$bad, $good], ['hml']));
        $this->sql("UPDATE shopify_product_links SET inventory_item_gid = 'gid://shopify/InventoryItem/1' WHERE vare_id = $1", [$bad]);
        $this->dirty([$bad, $good]);

        $run = $this->steps(['drain'], ['push', 'hml']);
        $this->assertNoException($run);
        $sets = self::sets($run);
        $this->assertCount(2, $sets[0]['variables']['input']['quantities'], 'the refused call held both');
        $this->assertSame([8], array_column($sets[1]['variables']['input']['quantities'], 'quantity'), 'the rest goes out under a new key');
        $this->assertSame(8, $run['levels']['hml']['SD756-good']['Mårum']);
        $this->assertSame('warning', $this->sql('SELECT severity FROM shopify_errors WHERE ref = $1 AND resolved_at IS NULL', ["item:$bad"])[0]['severity'] ?? null);
        $this->assertSame([], $this->sql("SELECT 1 FROM settings WHERE var_grp = 'shopify_state' AND var_name = 'stock.dirty:hml' AND var_value <> '{}'"), 'no mark is left to retry for ever');
    }

    public function test_a_dead_job_leaves_its_call_to_the_next_drain(): void
    {
        $this->sql("UPDATE shopify_lager SET rolle = 'ingen', prioritet = NULL WHERE lager = 2");
        $id = $this->item('dug', [1 => 6]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->dirty([$id]);
        $lost = $this->steps(['drain'], ['fail', 'inventorySetQuantities', 'http_500x4'], ['push', 'hml']);
        $key = self::sets($lost)[0]['variables']['key'];
        $this->sql("UPDATE shopify_jobs SET state = 'dead', finished_at = now() WHERE type = 'stock.push'");

        $run = $this->steps(['worker', 30]);
        $this->assertNoException($run);
        $this->assertSame(3, $run['results'][0]['done'], 'one job per capturing shop, with no new stock change');
        $this->assertSame([$key], array_map(fn(array $c): string => $c['variables']['key'], self::sets($run)));
        $this->assertSame(6, $run['levels']['hml']['SD756-dug']['Mårum']);
    }

    public function test_a_waiting_job_keeps_its_backoff_when_nothing_new_is_drained(): void
    {
        $id = $this->item('vase', [1 => 1]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->dirty([$id]);
        $this->steps(['drain']);
        $this->sql("UPDATE shopify_jobs SET next_run_at = now() + interval '1 hour' WHERE type = 'stock.push'");
        $run = $this->steps(['drain']);
        $this->assertSame(['drained' => 0, 'queued' => 0], $run['results'][0]);
        $this->assertSame([['late' => 't']], $this->sql("SELECT bool_and(next_run_at > now() + interval '30 minutes') AS late FROM shopify_jobs WHERE type = 'stock.push'"));
    }

    public function test_an_item_linked_after_its_mark_was_dropped_is_sent(): void
    {
        $this->sql("UPDATE shopify_lager SET rolle = 'ingen', prioritet = NULL WHERE lager = 2");
        $id = $this->item('ny', [1 => 3]);
        $this->steps(...$this->shops([], ['hml']));
        $this->dirty([$id]);
        $this->assertSame([], self::sets($this->steps(['drain'], ['push', 'hml'])), 'no link yet, so nothing is sent and the mark goes');

        $run = $this->steps(['seed', 'hml', $id], ['worker', 30]);
        $this->assertNoException($run);
        $this->assertSame(3, $run['levels']['hml']['SD756-ny']['Mårum']);
    }

    public function test_a_shop_cannot_publish_stock_without_capturing_it(): void
    {
        $run = $this->steps(['save', ['shop_key' => 'hml', 'stock_capture_enabled' => false]],
            ['save', ['shop_key' => 'ny', 'domain' => 'ny.myshopify.com', 'stock_publish_enabled' => true]]);
        $this->assertSame('ShopifyConfigError', $run['results'][0]['exception'] ?? null, 'hml publishes');
        $this->assertSame('ShopifyConfigError', $run['results'][1]['exception'] ?? null);
        $this->assertIsInt($this->steps(['save', ['shop_key' => 'ny', 'domain' => 'ny.myshopify.com', 'stock_publish_enabled' => true, 'stock_capture_enabled' => true]])['results'][0]);
    }

    public function test_a_level_missing_at_the_location_is_activated_then_set(): void
    {
        $id = $this->item('hynde', [1 => 3, 2 => 8]);
        $this->steps(...$this->shops([$id], ['hml']), ...[['unstock', 'hml', $id, 2]]);
        $this->dirty([$id]);

        $run = $this->steps(['drain'], ['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame(['stockSet', 'stockActivate', 'stockSet'], array_column($run['calls'], 'operation'));
        $this->assertNotSame($run['calls'][0]['variables']['key'], $run['calls'][2]['variables']['key'], 'a refused key is not used again');
        $this->assertSame(['Mårum' => 3, 'Ballerup' => 8], $run['levels']['hml']['SD756-hynde']);
    }

    public function test_a_sellable_lager_without_a_location_is_skipped_with_a_warning(): void
    {
        $id = $this->item('skab', [1 => 1, 2 => 2]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->sql("DELETE FROM shopify_location_map WHERE lager = 2");
        $this->dirty([$id]);

        $run = $this->steps(['drain'], ['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame([1], array_column(self::sets($run)[0]['variables']['input']['quantities'], 'quantity'));
        $error = $this->sql("SELECT severity, message FROM shopify_errors WHERE ref = 'stock_location_map' AND resolved_at IS NULL");
        $this->assertSame('warning', $error[0]['severity'] ?? null);
        $this->assertStringContainsString('sellable lager 2', $error[0]['message']);
    }
}
