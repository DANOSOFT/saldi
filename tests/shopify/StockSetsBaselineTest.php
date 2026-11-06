<?php
// tests/shopify/StockSetsBaselineTest.php
//
// SD-757 (WP06-3, K09, K12, K13, K19, K55): sets and display lagre in the push, the saldi.udstillet
// metafield, the nightly reconcile, the baseline and the remap, on a cloned tenant against the shared
// fake store, in child processes as the worker runs them.
//
// History:
// 20261105 CL/SZ SD-757: created.
// 20261106 CL/SZ SD-757 review: a moved-on acknowledgement blocks the stamp unless dirty, committed stock defers a pair.
//                A stored zero call waits for its items' barriers; the sandbox scripts print lines the runner reads.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class StockSetsBaselineTest extends TestCase
{
    /** lager => Shopify location name; 3 is the split-set lager, 4 a display. */
    private const LOCATIONS = [1 => 'Ballerup', 2 => 'Kolding', 3 => 'Mårum', 4 => 'Ballerup udstilling'];

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
            self::$db = ShopifyEnv::testDb('_s');
            // a fresh checkout has no temp/ (gitignored); the reconcile makes only the tenant's folder below it
            is_dir(ShopifyEnv::repoRoot() . '/temp') || mkdir(ShopifyEnv::repoRoot() . '/temp', 0770);
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
        $this->state = tempnam(sys_get_temp_dir(), 'sd757_');
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
        $this->sql("DELETE FROM settings WHERE (var_grp = 'shopify_state' AND var_name LIKE 'stock.%') OR (var_grp = 'shopify' AND var_name = 'split_set_lager')");
        $this->sql("DELETE FROM styklister WHERE indgaar_i IN (SELECT id FROM varer WHERE varenr LIKE 'SD757-%')");
        $this->sql("DELETE FROM lagerstatus WHERE vare_id IN (SELECT id FROM varer WHERE varenr LIKE 'SD757-%')");
        $this->sql("DELETE FROM varer WHERE varenr LIKE 'SD757-%'");
        $this->sql("INSERT INTO shopify_lager (lager, rolle, prioritet) VALUES (1, 'saelgbar', 1), (2, 'saelgbar', 2), (3, 'saelgbar', 3), (4, 'udstilling', NULL)");
        $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify', 'split_set_lager', '3')");
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, stock_capture_enabled, stock_publish_enabled, stock_baseline_at)
            VALUES ('hml', 'hml-fake.myshopify.com', 't', true, true, now())");
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

    private function item(string $varenr, array $stock = []): int
    {
        $id = (int)$this->sql("INSERT INTO varer (varenr, beskrivelse) VALUES ($1, $2) RETURNING id", ["SD757-$varenr", "Havestol $varenr"])[0]['id'];
        foreach ($stock as $lager => $qty) {
            $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES ($1, $2, $3)', [$lager, $id, $qty]);
        }
        return $id;
    }

    /** @param array<int, int|float> $components vare_id => per set */
    private function set(string $varenr, array $components): int
    {
        $id = (int)$this->sql("INSERT INTO varer (varenr, beskrivelse, samlevare) VALUES ($1, $2, 'on') RETURNING id", ["SD757-$varenr", "Sæt $varenr"])[0]['id'];
        $pos = 0;
        foreach ($components as $component => $antal) {
            $this->sql('INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $2, $3, $4)', [$component, $id, $antal, ++$pos]);
        }
        return $id;
    }

    private function steps(array ...$steps): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_stock.php', [self::$db, $this->state, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertIsArray($run['result'], $run['stdout'] . $run['stderr']);
        return $run['result'];
    }

    /** One shop 'hml' with all locations and these items linked; the links' own marks are cleared. */
    private function shop(array $items, array $locations = self::LOCATIONS): array
    {
        $steps = [['shop', 'hml', $locations]];
        foreach ($items as $id) {
            $steps[] = ['seed', 'hml', $id];
        }
        $steps[] = ['clean'];
        return $this->steps(...$steps);
    }

    private function dirty(array $ids): void
    {
        $this->sql('INSERT INTO shopify_stock_dirty (vare_id) SELECT unnest($1::int[])', ['{' . implode(',', $ids) . '}']);
    }

    private function assertNoException(array $run): void
    {
        foreach ($run['results'] as $r) {
            $this->assertArrayNotHasKey('exception', is_array($r) ? $r : [], json_encode($r));
        }
    }

    private static function calls(array $run, string $operation): array
    {
        return array_values(array_filter($run['calls'], fn(array $c): bool => $c['operation'] === $operation));
    }

    /** @return list<string> the open errors, as "ref: message" */
    private function errors(): array
    {
        return array_map(fn(array $r): string => "{$r['ref']}: {$r['message']}", $this->sql("SELECT ref, message FROM shopify_errors WHERE resolved_at IS NULL AND ref NOT LIKE 'job:%' ORDER BY ref"));
    }

    private function state(): array
    {
        return array_map(fn(array $r): string => "{$r['vare_id']}/{$r['lager']}={$r['quantity']}", $this->sql('SELECT vare_id, lager, round(quantity)::int AS quantity FROM shopify_stock_state ORDER BY 1, 2'));
    }

    private function baselineAt(): ?string
    {
        return $this->sql("SELECT stock_baseline_at FROM shopify_shops WHERE shop_key = 'hml'")[0]['stock_baseline_at'];
    }

    // ---- sets (K09, K55) -----------------------------------------------------------------------

    public function test_the_ks_fixture_shows_three_sets_with_the_cross_location_ones_on_maarum(): void
    {
        $x = $this->item('X', [1 => 3]);
        $y = $this->item('Y', [1 => 2, 2 => 6]);
        $set = $this->set('SET', [$x => 1, $y => 2]);
        $this->shop([$set]);
        $this->dirty([$set]);

        $run = $this->steps(['drain', 100], ['push', 'hml']);

        $this->assertNoException($run);
        $shown = $run['levels']['hml']['SD757-SET'];
        $this->assertSame(1, $shown['Ballerup'], 'lager 1 has one whole set');
        $this->assertSame(0, $shown['Kolding'], 'lager 2 has none whole');
        $this->assertSame(2, $shown['Mårum'], 'the cross-location sets');
        $this->assertSame(3, $shown['Ballerup'] + $shown['Kolding'] + $shown['Mårum'], 'never more than the lagre can assemble: min(3/1, 8/2)');
    }

    public function test_a_set_with_two_components_shows_the_right_quantity_and_follows_a_component(): void
    {
        $x = $this->item('X', [1 => 4]);
        $y = $this->item('Y', [1 => 10]);
        $set = $this->set('SET', [$x => 1, $y => 2]);
        $this->shop([$set]);
        $this->dirty([$set]);
        $first = $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertSame(4, $first['levels']['hml']['SD757-SET']['Ballerup']);

        $this->steps(['triggers']);
        $this->sql('UPDATE lagerstatus SET beholdning = 4 WHERE vare_id = $1 AND lager = 1', [$y]);
        $after = $this->steps(['drain', 100], ['push', 'hml']);

        $this->assertNoException($after);
        $this->assertSame(2, $after['levels']['hml']['SD757-SET']['Ballerup'], 'the component\'s stock reached the set');
    }

    public function test_a_set_waits_for_a_barrier_on_one_of_its_components(): void
    {
        $x = $this->item('X', [1 => 4]);
        $y = $this->item('Y', [1 => 4]);
        $set = $this->set('SET', [$x => 1, $y => 1]);
        $this->shop([$set]);
        $this->dirty([$set]);

        $held = $this->steps(['open', 'hml', 7001, [$y]], ['drain', 100], ['push', 'hml']);
        $this->assertNoException($held);
        $this->assertSame('yield', $held['results'][2]['outcome']);
        $this->assertSame([], self::calls($held, 'stockSet'));

        $sent = $this->steps(['release', 'hml', 7001], ['drain', 100], ['push', 'hml']);
        $this->assertSame(4, $sent['levels']['hml']['SD757-SET']['Ballerup']);
    }

    public function test_a_stored_call_with_a_set_waits_for_a_barrier_on_its_component(): void
    {
        $x = $this->item('X', [1 => 4]);
        $y = $this->item('Y', [1 => 4]);
        $set = $this->set('SET', [$x => 1, $y => 1]);
        $this->shop([$set]);
        $this->dirty([$set]);
        $this->assertNoException($this->steps(['drain', 100], ['next', 'hml']));

        $held = $this->steps(['open', 'hml', 7002, [$x]], ['push', 'hml']);

        $this->assertSame('yield', $held['results'][1]['outcome']);
        $this->assertSame([], self::calls($held, 'stockSet'));
    }

    public function test_a_set_that_cannot_be_published_is_left_out_with_a_warning(): void
    {
        $plain = $this->item('PLAIN', [1 => 1]);
        $empty = $this->set('EMPTY', []);
        $inner = $this->set('INNER', [$plain => 1]);
        $nested = $this->set('NESTED', [$inner => 1]);
        $good = $this->item('GOOD', [1 => 5]);
        $this->shop([$empty, $nested, $good]);
        $this->dirty([$empty, $nested, $good]);

        $run = $this->steps(['drain', 100], ['push', 'hml']);

        $this->assertNoException($run);
        $this->assertSame(5, $run['levels']['hml']['SD757-GOOD']['Ballerup']);
        $this->assertSame([], array_filter($run['levels']['hml']['SD757-EMPTY']), 'no quantity went out');
        $this->assertCount(2, array_filter($this->errors(), fn(string $e): bool => str_contains($e, 'no bill of materials')));
    }

    public function test_a_set_that_breaks_after_publishing_is_sent_as_0_and_its_warning_ends_when_it_is_fixed(): void
    {
        $x = $this->item('X', [1 => 4]);
        $y = $this->item('Y', [1 => 4]);
        $set = $this->set('SET', [$x => 1, $y => 1]);
        $this->shop([$set]);
        $this->dirty([$set]);
        $this->assertSame(4, $this->steps(['drain', 100], ['push', 'hml'])['levels']['hml']['SD757-SET']['Ballerup']);

        $bom = $this->sql('SELECT id, vare_id FROM styklister WHERE indgaar_i = $1', [$set]);
        $this->sql('DELETE FROM styklister WHERE indgaar_i = $1', [$set]);
        $this->dirty([$set]);
        $broken = $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertNoException($broken);
        $this->assertSame(0, $broken['levels']['hml']['SD757-SET']['Ballerup'], 'what it published before can no longer be sold');
        $this->assertCount(1, array_filter($this->errors(), fn(string $e): bool => str_contains($e, 'no bill of materials')), 'the warning stays');

        foreach ($bom as $i => $c) {
            $this->sql('INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $2, 1, $3)', [$c['vare_id'], $set, $i + 1]);
        }
        $this->dirty([$set]);
        $fixed = $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertSame(4, $fixed['levels']['hml']['SD757-SET']['Ballerup']);
        $this->assertSame([], array_filter($this->errors(), fn(string $e): bool => str_starts_with($e, "set:$set")), 'the set publishes again, so the warning is resolved');
    }

    public function test_a_set_repaired_with_no_stock_ends_its_warning_without_a_call(): void
    {
        $x = $this->item('X', []);
        $y = $this->item('Y', []);
        $set = $this->set('SET', []);
        $this->shop([$set]);
        $this->dirty([$set]);
        $broken = $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertSame(0, $broken['levels']['hml']['SD757-SET']['Ballerup']);
        $this->assertCount(1, array_filter($this->errors(), fn(string $e): bool => str_starts_with($e, "set:$set")));

        $this->sql('INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) VALUES ($1, $3, 1, 1), ($2, $3, 1, 2)', [$x, $y, $set]);
        $this->dirty([$set]);
        $fixed = $this->steps(['drain', 100], ['push', 'hml']);

        $this->assertSame([], array_values(array_filter($fixed['calls'], fn(array $c): bool => $c['operation'] === 'stockSet')), 'still 0 everywhere, so nothing is sent');
        $this->assertSame([], array_filter($this->errors(), fn(string $e): bool => str_starts_with($e, "set:$set")), 'the warning ends anyway');
    }

    public function test_the_warning_on_split_set_lager_is_raised_once_per_push_and_resolved(): void
    {
        $x = $this->item('X', [1 => 1, 2 => 0]);
        $this->shop([$x]);
        $this->sql("UPDATE settings SET var_value = '4' WHERE var_grp = 'shopify' AND var_name = 'split_set_lager'");
        $this->dirty([$x]);

        $this->steps(['drain', 100], ['push', 'hml']);

        $row = $this->sql("SELECT occurrences FROM shopify_errors WHERE ref = 'split_set_lager' AND resolved_at IS NULL");
        $this->assertSame(1, (int)$row[0]['occurrences']);

        $this->sql("UPDATE settings SET var_value = '3' WHERE var_grp = 'shopify' AND var_name = 'split_set_lager'");
        $this->dirty([$x]);
        $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertSame([], $this->sql("SELECT 1 FROM shopify_errors WHERE ref = 'split_set_lager' AND resolved_at IS NULL"));
    }

    // ---- display (K13) -------------------------------------------------------------------------

    public function test_a_display_lager_is_sent_as_0_with_the_metafield_as_a_json_list(): void
    {
        $id = $this->item('SHOW', [4 => 1]);
        $this->shop([$id]);
        $this->dirty([$id]);

        $run = $this->steps(['drain', 100], ['push', 'hml'], ['display', 'hml'], ['udstillet', 'hml', $id]);

        $this->assertNoException($run);
        $this->assertSame(0, $run['levels']['hml']['SD757-SHOW']['Ballerup udstilling'], 'not sellable though Saldi has 1');
        $this->assertSame(['Ballerup udstilling'], json_decode((string)$run['results'][3], true, 512, JSON_THROW_ON_ERROR));
        $write = self::calls($run, 'stockDisplaySet');
        $this->assertCount(1, $write);
        $this->assertSame(['ownerId', 'namespace', 'key', 'type', 'value'], array_keys($write[0]['variables']['metafields'][0]));
        $this->assertSame('json', $write[0]['variables']['metafields'][0]['type']);
        $this->assertSame('saldi', $write[0]['variables']['metafields'][0]['namespace']);
        $this->assertSame('udstillet', $write[0]['variables']['metafields'][0]['key']);
    }

    public function test_the_metafield_writes_only_what_changes_and_an_item_that_leaves_gets_an_empty_list(): void
    {
        $never = $this->item('NEVER', [1 => 5]);
        $shown = $this->item('SHOW', [4 => 1]);
        $this->shop([$never, $shown]);
        $this->dirty([$never, $shown]);

        $first = $this->steps(['drain', 100], ['display', 'hml']);
        $this->assertCount(1, self::calls($first, 'stockDisplaySet')[0]['variables']['metafields'], 'an item that never stood anywhere gets no metafield');

        $this->dirty([$never, $shown]);
        $again = $this->steps(['drain', 100], ['display', 'hml']);
        $this->assertSame([], self::calls($again, 'stockDisplaySet'), 'nothing changed, nothing written');

        $this->sql('UPDATE lagerstatus SET beholdning = 0 WHERE vare_id = $1', [$shown]);
        $this->dirty([$shown]);
        $left = $this->steps(['drain', 100], ['display', 'hml'], ['udstillet', 'hml', $shown]);
        $this->assertSame('[]', $left['results'][2]);
    }

    public function test_a_set_at_a_display_lager_stands_there_only_when_it_is_whole(): void
    {
        $x = $this->item('X', [4 => 1]);
        $y = $this->item('Y', [4 => 1]);
        $set = $this->set('SET', [$x => 1, $y => 2]);
        $this->shop([$set]);
        $this->dirty([$set]);
        $this->assertNull($this->steps(['drain', 100], ['display', 'hml'], ['udstillet', 'hml', $set])['results'][2]);

        $this->sql('UPDATE lagerstatus SET beholdning = 2 WHERE vare_id = $1', [$y]);
        $this->dirty([$set]);
        $whole = $this->steps(['drain', 100], ['display', 'hml'], ['udstillet', 'hml', $set]);
        $this->assertSame(['Ballerup udstilling'], json_decode((string)$whole['results'][2], true));
    }

    public function test_the_display_job_keeps_its_marks_behind_the_gate(): void
    {
        $id = $this->item('SHOW', [4 => 1]);
        $this->shop([$id]);
        $this->sql("UPDATE shopify_shops SET stock_baseline_at = NULL WHERE shop_key = 'hml'");
        $this->dirty([$id]);

        $closed = $this->steps(['drain', 100], ['display', 'hml']);
        $this->assertSame(['outcome' => 'yield', 'written' => 0, 'gate' => 'closed', 'run_after' => 60], $closed['results'][1]);
        $this->assertSame([], self::calls($closed, 'stockDisplaySet'));

        $this->sql("UPDATE shopify_shops SET stock_baseline_at = now() WHERE shop_key = 'hml'");
        $this->assertCount(1, self::calls($this->steps(['display', 'hml']), 'stockDisplaySet'));
    }

    public function test_a_display_lager_without_a_location_is_skipped_without_a_warning(): void
    {
        $id = $this->item('SHOW', [1 => 2, 4 => 1]);
        $this->shop([$id], [1 => 'Ballerup', 2 => 'Kolding', 3 => 'Mårum']);
        $this->dirty([$id]);

        $run = $this->steps(['drain', 100], ['push', 'hml'], ['display', 'hml'], ['udstillet', 'hml', $id]);

        $this->assertNoException($run);
        $this->assertSame(2, $run['levels']['hml']['SD757-SHOW']['Ballerup']);
        $this->assertSame([], $this->errors());
        $this->assertNull($run['results'][3]);
    }

    public function test_the_virtual_and_ingen_lagre_are_never_sent(): void
    {
        $this->sql("INSERT INTO shopify_lager (lager, rolle) VALUES (5, 'ingen'), (6, 'saelgbar')");
        $this->sql("UPDATE shopify_lager SET prioritet = 6 WHERE lager = 6");
        $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify', 'virtual_lager', '6')");
        try {
            $id = $this->item('A', [1 => 2, 5 => 9, 6 => 9]);
            $this->shop([$id], self::LOCATIONS + [5 => 'Ingen', 6 => 'Virtuel']);
            $this->dirty([$id]);
            $run = $this->steps(['drain', 100], ['push', 'hml']);
            $this->assertSame(0, $run['levels']['hml']['SD757-A']['Ingen']);
            $this->assertSame(0, $run['levels']['hml']['SD757-A']['Virtuel']);
            $sent = array_merge(...array_map(fn(array $c): array => $c['variables']['input']['quantities'], self::calls($run, 'stockSet')));
            $this->assertCount(4, $sent, 'three sellable lagre and the display');
        } finally {
            $this->sql("DELETE FROM settings WHERE var_grp = 'shopify' AND var_name = 'virtual_lager'");
        }
    }

    // ---- baseline (K19) ------------------------------------------------------------------------

    private function offToBaseline(): void
    {
        $this->sql("UPDATE shopify_shops SET stock_publish_enabled = false, stock_baseline_at = NULL WHERE shop_key = 'hml'");
    }

    public function test_a_baseline_refuses_a_shop_that_is_not_ready(): void
    {
        $this->shop([]);
        $published = $this->steps(['start_baseline', 'hml']);
        $this->assertStringContainsString('has one from', $published['results'][0]['message'], 'a baseline exists');

        $this->sql("UPDATE shopify_shops SET stock_baseline_at = NULL WHERE shop_key = 'hml'");
        $this->assertStringContainsString('already publishes', $this->steps(['start_baseline', 'hml'])['results'][0]['message']);

        $this->sql("UPDATE shopify_shops SET stock_publish_enabled = false, stock_capture_enabled = false WHERE shop_key = 'hml'");
        $this->assertStringContainsString("doesn't capture", $this->steps(['start_baseline', 'hml'])['results'][0]['message']);
    }

    public function test_a_baseline_sends_every_pair_with_publishing_off_and_stamps_only_then(): void
    {
        $a = $this->item('A', [1 => 3]);
        $b = $this->item('B', [2 => 4]);
        $this->shop([$a, $b]);
        $this->offToBaseline();
        // stale acknowledgements of an earlier try don't count
        $this->sql('INSERT INTO shopify_stock_state (shop_id, vare_id, variant_id, lager, quantity, acknowledged_at) SELECT id, $1, 0, 1, 3, now() FROM shopify_shops', [$a]);

        $start = $this->steps(['start_baseline', 'hml']);
        $this->assertIsInt($start['results'][0]);

        $prepared = $this->steps(['baseline', 'hml', null]);
        $this->assertSame(['outcome' => 'yield', 'checkpoint' => ['prepared' => true]], $prepared['results'][0]);
        $this->assertSame([], $this->state(), 'every acknowledgement is forgotten');

        $blocked = $this->steps(['push', 'hml']);
        $this->assertSame([], self::calls($blocked, 'stockSet'), 'an ordinary push stays blocked');
        $this->assertSame('closed', $blocked['results'][0]['gate']);

        $done = $this->steps(['baseline', 'hml', ['prepared' => true]]);
        $this->assertNoException($done);
        $this->assertSame(true, $done['results'][0]['stamped']);
        $this->assertSame(3, $done['levels']['hml']['SD757-A']['Ballerup']);
        $this->assertSame(4, $done['levels']['hml']['SD757-B']['Kolding']);
        $this->assertCount(8, $this->state(), 'two items at four lagre');
        $this->assertNotNull($this->baselineAt());

        $this->sql("UPDATE lagerstatus SET beholdning = 2 WHERE vare_id = $1 AND lager = 1", [$a]);
        $this->dirty([$a]);
        $still = $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertSame([], self::calls($still, 'stockSet'), 'publishing is still off after the stamp');
        $this->sql("UPDATE shopify_shops SET stock_publish_enabled = true WHERE shop_key = 'hml'");
        $this->assertSame(2, $this->steps(['push', 'hml'])['levels']['hml']['SD757-A']['Ballerup'], 'and the mark waited for it');
    }

    public function test_a_partial_failure_leaves_the_stamp_unset_and_a_new_baseline_completes(): void
    {
        $a = $this->item('A', [1 => 3]);
        $b = $this->item('B', [1 => 4]);
        $this->shop([$a, $b]);
        $this->offToBaseline();
        $this->sql("UPDATE shopify_product_links SET inventory_item_gid = 'gid://shopify/InventoryItem/999999' WHERE vare_id = $1", [$b]);
        $this->steps(['baseline', 'hml', null]);

        $run = $this->steps(['baseline', 'hml', ['prepared' => true]]);

        $this->assertNoException($run);
        $this->assertSame(false, $run['results'][0]['stamped']);
        $this->assertSame(4, $run['results'][0]['missing'], 'item B at each of its four lagre');
        $this->assertNull($this->baselineAt());
        $this->assertSame(3, $run['levels']['hml']['SD757-A']['Ballerup'], 'the rest went out');
        $this->assertNotEmpty(array_filter($this->errors(), fn(string $e): bool => str_starts_with($e, 'baseline:')));

        $this->sql("UPDATE shopify_product_links SET inventory_item_gid = NULL, listed = false WHERE vare_id = $1", [$b]);
        $this->assertSame(true, $this->steps(['baseline', 'hml', null], ['baseline', 'hml', ['prepared' => true]])['results'][1]['stamped']);
        $this->assertNotNull($this->baselineAt());
        $this->assertEmpty(array_filter($this->errors(), fn(string $e): bool => str_starts_with($e, 'baseline:')), 'the error is resolved');
    }

    public function test_a_failed_call_keeps_the_baseline_going_after_the_retry(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a]);
        $this->offToBaseline();
        $this->steps(['baseline', 'hml', null]);

        $failed = $this->steps(['fail', 'inventorySetQuantities', 'timeout_after_applyx4'], ['baseline', 'hml', ['prepared' => true]]);
        $this->assertSame('RuntimeException', $failed['results'][1]['exception'] ?? null, json_encode($failed['results'][1]));
        $this->assertNull($this->baselineAt());

        $after = $this->steps(['baseline', 'hml', ['prepared' => true]]);
        $this->assertTrue($after['results'][0]['stamped']);
    }

    public function test_a_sellable_lager_without_a_location_blocks_the_stamp_and_a_display_one_does_not(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a], [1 => 'Ballerup', 2 => 'Kolding']);
        $this->offToBaseline();
        $run = $this->steps(['baseline', 'hml', null], ['baseline', 'hml', ['prepared' => true]]);
        $this->assertSame(false, $run['results'][1]['stamped']);

        $gid = $this->steps(['location', 'hml', 'Mårum'])['results'][0];
        $this->sql("INSERT INTO shopify_location_map (shop_id, lager, location_gid) SELECT id, 3, $1 FROM shopify_shops", [$gid]);
        $ok = $this->steps(['baseline', 'hml', null], ['baseline', 'hml', ['prepared' => true]]);
        $this->assertTrue($ok['results'][1]['stamped'], 'lager 4 is a display with no location');
    }

    public function test_an_acknowledgement_saldi_moved_on_from_blocks_the_stamp_until_it_is_sent(): void
    {
        $a = $this->item('A', [1 => 3]);
        $b = $this->item('B', [1 => 4]);
        $this->shop([$a, $b]);
        $this->offToBaseline();
        // B is refused, so the first run doesn't stamp; A is acknowledged at 3
        $this->sql("UPDATE shopify_product_links SET inventory_item_gid = 'gid://shopify/InventoryItem/999999' WHERE vare_id = $1", [$b]);
        $this->assertFalse($this->steps(['baseline', 'hml', null], ['baseline', 'hml', ['prepared' => true]])['results'][1]['stamped']);
        // A changes to 1 and its mark is lost, as when Shopify refused the call that carried it
        $this->sql('UPDATE lagerstatus SET beholdning = 1 WHERE vare_id = $1 AND lager = 1', [$a]);
        $this->sql('DELETE FROM shopify_stock_dirty WHERE vare_id = $1', [$a]);
        $this->sql("UPDATE shopify_product_links SET inventory_item_gid = NULL, listed = false WHERE vare_id = $1", [$b]);

        $run = $this->steps(['baseline', 'hml', ['prepared' => true]]);

        $this->assertNoException($run);
        $this->assertFalse($run['results'][0]['stamped'], 'Shopify has 3, Saldi 1');
        $this->assertSame(3, $run['levels']['hml']['SD757-A']['Ballerup']);
        $this->assertStringContainsString("item $a at lager 1: acknowledged 3, Saldi 1", implode(' ', $this->errors()));
        $this->assertNull($this->baselineAt());

        // marked again, it goes out and the stamp follows
        $this->dirty([$a]);
        $done = $this->steps(['drain', 100], ['baseline', 'hml', ['prepared' => true]]);
        $this->assertTrue($done['results'][1]['stamped']);
        $this->assertSame(1, $done['levels']['hml']['SD757-A']['Ballerup']);
    }

    public function test_a_change_not_drained_yet_at_the_end_of_the_baseline_does_not_block_the_stamp(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a]);
        $this->offToBaseline();
        $this->steps(['baseline', 'hml', null]);
        $sent = $this->steps(['baseline', 'hml', ['prepared' => true]]);
        $this->assertTrue($sent['results'][0]['stamped']);
        // the same check again, with a sale after the last send that no drain has seen yet
        $this->sql('UPDATE shopify_shops SET stock_baseline_at = NULL');
        $this->sql('UPDATE lagerstatus SET beholdning = 2 WHERE vare_id = $1 AND lager = 1', [$a]);
        $this->dirty([$a]);

        $run = $this->steps(['baseline', 'hml', ['prepared' => true]]);

        $this->assertTrue($run['results'][0]['stamped'], 'its dirty row becomes a mark the push sends');
        $this->sql("UPDATE shopify_shops SET stock_publish_enabled = true WHERE shop_key = 'hml'");
        $this->assertSame(2, $this->steps(['drain', 100], ['push', 'hml'])['levels']['hml']['SD757-A']['Ballerup']);
    }

    public function test_a_change_during_the_baseline_goes_with_it(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a]);
        $this->offToBaseline();
        $this->steps(['baseline', 'hml', null]);
        $this->sql("UPDATE lagerstatus SET beholdning = 1 WHERE vare_id = $1 AND lager = 1", [$a]);
        $this->dirty([$a]);

        $run = $this->steps(['drain', 100], ['baseline', 'hml', ['prepared' => true]]);

        $this->assertSame(1, $run['levels']['hml']['SD757-A']['Ballerup']);
        $this->assertTrue($run['results'][1]['stamped']);
    }

    // ---- reconcile (K12) -----------------------------------------------------------------------

    /** Runs the job until it ends, as the worker would; returns its results. */
    private function reconcile(?int $lines = null, ?array &$yields = null): array
    {
        $cp = null;
        $yields = [];
        for ($i = 0; $i < 50; $i++) {
            $run = $this->steps(['reconcile', 'hml', $cp, $lines]);
            $this->assertNoException($run);
            $out = $run['results'][0];
            if ($out['outcome'] === 'done') {
                return $out;
            }
            $cp = $out['checkpoint'];
            $yields[] = $cp;
        }
        $this->fail('the reconcile never ended');
    }

    /** One item stocked everywhere, in step with Saldi. */
    private function inStep(string $varenr, array $stock): int
    {
        $id = $this->item($varenr, $stock);
        $this->shop([$id]);
        $this->dirty([$id]);
        $this->assertNoException($this->steps(['drain', 100], ['push', 'hml']));
        return $id;
    }

    public function test_drift_is_corrected_toward_saldi_through_the_push_and_reported(): void
    {
        $id = $this->inStep('A', [1 => 5]);
        $this->assertNoException($this->steps(['level', 'hml', $id, 1, 9]));

        $out = $this->reconcile();

        $this->assertSame(1, $out['drifted']);
        $this->assertSame(4, $out['compared']);
        $error = array_values(array_filter($this->errors(), fn(string $e): bool => str_starts_with($e, 'reconcile:')));
        $this->assertStringContainsString("item $id at lager 1: Shopify 9, Saldi 5", $error[0]);
        $this->assertSame('5.000', $this->sql('SELECT beholdning FROM lagerstatus WHERE vare_id = $1 AND lager = 1', [$id])[0]['beholdning'], 'Saldi is never changed');

        $push = $this->steps(['push', 'hml']);
        $this->assertSame(5, $push['levels']['hml']['SD757-A']['Ballerup']);
        $this->assertCount(1, self::calls($push, 'stockSet')[0]['variables']['input']['quantities'], 'only the pair that drifted');

        $this->reconcile();
        $this->assertEmpty(array_filter($this->errors(), fn(string $e): bool => str_starts_with($e, 'reconcile:')), 'a clean night resolves the report');
    }

    public function test_the_reconcile_resumes_from_the_byte_offset_after_a_yield(): void
    {
        $ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $ids[] = $this->item("I$i", [1 => $i]);
        }
        $this->shop($ids);
        $this->dirty($ids);
        $this->assertNoException($this->steps(['drain', 100], ['push', 'hml']));
        $this->assertNoException($this->steps(['level', 'hml', $ids[2], 1, 40], ['level', 'hml', $ids[2], 2, 41]));

        $out = $this->reconcile(1, $yields);

        $offsets = array_values(array_unique(array_column(array_filter($yields, fn(array $c): bool => isset($c['offset'])), 'offset')));
        $this->assertGreaterThanOrEqual(1, count($offsets));
        $this->assertSame(2, $out['drifted'], 'each line is compared once, also a pair behind one this run corrected');
        $this->assertSame(12, $out['compared'], 'three items at four lagre, each once');
    }

    public function test_the_reconcile_goes_on_at_the_offset_with_a_later_chunk_only(): void
    {
        $id = $this->inStep('A', [1 => 5]);
        $this->assertNoException($this->steps(['level', 'hml', $id, 1, 9]));
        $start = $this->steps(['reconcile', 'hml', null]);
        $cp = $start['results'][0]['checkpoint'];
        $poll = $this->steps(['reconcile', 'hml', $cp]);
        $this->assertSame($cp, $poll['results'][0]['checkpoint'], 'still running');

        // the whole file is behind the offset: nothing more is compared in the scan
        $file = $this->steps(['reconcile', 'hml', $cp, 0]);
        $next = $file['results'][0]['checkpoint'];
        $this->assertSame(0, $next['offset']);
        $next['offset'] = PHP_INT_MAX >> 40;
        $out = $this->steps(['reconcile', 'hml', $next]);
        $this->assertSame(0, $out['results'][0]['drifted']);
    }

    public function test_a_pair_the_shop_stopped_stocking_is_drift(): void
    {
        $id = $this->inStep('A', [1 => 5]);
        $this->assertNoException($this->steps(['unstock', 'hml', $id, 1]));

        $out = $this->reconcile();
        $this->assertSame(1, $out['drifted']);
        $this->assertStringContainsString('Shopify not stocked, Saldi 5', implode(' ', $this->errors()));

        $push = $this->steps(['push', 'hml']);
        $this->assertSame(5, $push['levels']['hml']['SD757-A']['Ballerup'], 'stocked again with the quantity');
    }

    public function test_a_marked_item_a_barred_item_and_a_newer_acknowledgement_are_not_drift(): void
    {
        $marked = $this->item('M', [1 => 5]);
        $barred = $this->item('B', [1 => 5]);
        $newer = $this->item('N', [1 => 5]);
        $this->shop([$marked, $barred, $newer]);
        $this->dirty([$marked, $barred, $newer]);
        $this->assertNoException($this->steps(['drain', 100], ['push', 'hml']));
        foreach ([$marked, $barred, $newer] as $id) {
            $this->assertNoException($this->steps(['level', 'hml', $id, 1, 9]));
        }
        $this->sql('UPDATE lagerstatus SET beholdning = 6 WHERE vare_id = $1 AND lager = 1', [$marked]);
        $this->assertNoException($this->steps(['open', 'hml', 7003, [$barred]]));
        $start = $this->steps(['reconcile', 'hml', null])['results'][0]['checkpoint'];
        $this->dirty([$marked]);
        $this->steps(['drain', 100]);
        $this->sql('UPDATE shopify_stock_state SET acknowledged_at = now() + interval \'1 day\' WHERE vare_id = $1', [$newer]);

        $poll = $this->steps(['reconcile', 'hml', $start])['results'][0]['checkpoint'];
        $out = $this->steps(['reconcile', 'hml', $poll])['results'][0];

        $this->assertSame('done', $out['outcome'], json_encode($out));
        $this->assertSame(0, $out['drifted']);
    }

    public function test_a_pair_with_stock_committed_in_shopify_is_deferred_not_corrected(): void
    {
        $id = $this->inStep('A', [1 => 5, 2 => 4]);
        // an unpaid order holds 2 at Ballerup: available 3 there, Saldi still 5
        $this->assertNoException($this->steps(['hold', 'hml', $id, 1, 2]));
        // at Kolding the order is held as well, but available is back at Saldi's 4: nothing to defer
        $this->assertNoException($this->steps(['hold', 'hml', $id, 2, 1], ['level', 'hml', $id, 2, 4]));

        $out = $this->reconcile();

        $this->assertSame(0, $out['drifted']);
        $this->assertSame(1, $out['deferred']);
        $this->assertStringContainsString("item $id at lager 1 deferred: Shopify 3 with 2 committed, Saldi 5", implode(' ', $this->errors()));
        $push = $this->steps(['push', 'hml']);
        $this->assertSame([], self::calls($push, 'stockSet'), 'the reserved unit is not put on sale again');
        $this->assertSame(3, $push['levels']['hml']['SD757-A']['Ballerup']);
    }

    public function test_a_lost_file_is_fetched_again(): void
    {
        $id = $this->inStep('A', [1 => 5]);
        $this->assertNoException($this->steps(['level', 'hml', $id, 1, 9]));
        $cp = $this->steps(['reconcile', 'hml', null])['results'][0]['checkpoint'];
        $this->steps(['reconcile', 'hml', $cp]);
        $held = $this->steps(['reconcile', 'hml', $cp, 0])['results'][0]['checkpoint'];
        $out = $this->steps(['kill_file', 'hml', $held['op']], ['reconcile', 'hml', $held]);
        $this->assertSame(1, $out['results'][1]['drifted']);
    }

    public function test_two_overlapping_reads_keep_their_own_files(): void
    {
        $id = $this->inStep('A', [1 => 5]);
        $this->assertNoException($this->steps(['level', 'hml', $id, 1, 9]));
        $held = [];
        foreach (['night', 'manual'] as $read) {
            $cp = $this->steps(['reconcile', 'hml', null])['results'][0]['checkpoint'];
            $this->steps(['reconcile', 'hml', $cp]);
            $held[$read] = $this->steps(['reconcile', 'hml', $cp, 0])['results'][0]['checkpoint'];
        }

        $files = $this->steps(['file', 'hml', $held['night']['op']], ['file', 'hml', $held['manual']['op']])['results'];
        $this->assertNotSame($files[0]['path'], $files[1]['path']);
        $this->assertTrue($files[0]['exists'] && $files[1]['exists']);

        $this->assertSame(1, $this->steps(['reconcile', 'hml', $held['manual']])['results'][0]['drifted']);
        $this->assertTrue($this->steps(['file', 'hml', $held['night']['op']])['results'][0]['exists'], 'finishing one read leaves the other\'s file');
    }

    public function test_the_reconcile_does_nothing_behind_the_gate(): void
    {
        $this->shop([]);
        $this->sql("UPDATE shopify_shops SET stock_publish_enabled = false WHERE shop_key = 'hml'");
        $run = $this->steps(['reconcile', 'hml', null]);
        $this->assertSame(['outcome' => 'done', 'gate' => 'closed'], $run['results'][0]);
        $this->assertSame([], $run['calls']);
    }

    // ---- remap ---------------------------------------------------------------------------------

    public function test_a_remap_zeroes_the_old_location_before_the_new_one_gets_the_stock(): void
    {
        $a = $this->inStep('A', [1 => 5, 2 => 2]);
        $new = $this->steps(['location', 'hml', 'Ballerup ny'])['results'][0];

        $run = $this->steps(['remap', 'hml', 1, $new]);

        $this->assertNoException($run);
        $this->assertSame(1, $run['results'][0], 'one pair zeroed');
        $this->assertSame(0, $run['levels']['hml']['SD757-A']['Ballerup']);
        $this->assertSame($new, $this->sql('SELECT location_gid FROM shopify_location_map WHERE lager = 1')[0]['location_gid']);
        $this->assertSame([], $this->sql('SELECT 1 FROM shopify_stock_state WHERE lager = 1'), 'its acknowledgements are forgotten');
        $this->assertSame(['2'], array_column($this->sql('SELECT round(quantity)::int AS q FROM shopify_stock_state WHERE vare_id = $1 AND lager = 2', [$a]), 'q'), 'other lagre keep theirs');

        $push = $this->steps(['push', 'hml']);
        $this->assertSame(5, $push['levels']['hml']['SD757-A']['Ballerup ny'], 'the item was marked, so the stock goes to the new location');
        $this->assertSame(0, $push['levels']['hml']['SD757-A']['Ballerup']);
        $this->assertCount(1, self::calls($push, 'stockSet')[0]['variables']['input']['quantities']);
    }

    public function test_a_remap_without_a_new_location_leaves_the_lager_unmapped_and_a_push_sends_nothing_there(): void
    {
        $this->inStep('A', [1 => 5]);

        $run = $this->steps(['remap', 'hml', 1, null], ['push', 'hml']);

        $this->assertNoException($run);
        $this->assertSame([], $this->sql('SELECT 1 FROM shopify_location_map WHERE lager = 1'));
        $this->assertSame(0, $run['levels']['hml']['SD757-A']['Ballerup'], 'the push did not fill it again');
    }

    public function test_a_refused_item_keeps_the_mapping(): void
    {
        $a = $this->item('A', [1 => 5]);
        $b = $this->item('B', [1 => 5]);
        $this->shop([$a, $b]);
        $this->dirty([$a, $b]);
        $this->assertNoException($this->steps(['drain', 100], ['push', 'hml']));
        $old = $this->sql('SELECT location_gid FROM shopify_location_map WHERE lager = 1')[0]['location_gid'];
        $this->sql("UPDATE shopify_product_links SET inventory_item_gid = 'gid://shopify/InventoryItem/999999' WHERE vare_id = $1", [$b]);

        $run = $this->steps(['remap', 'hml', 1, null]);

        $this->assertSame('RuntimeException', $run['results'][0]['exception'] ?? null, json_encode($run['results'][0]));
        $this->assertStringContainsString("item $b", $run['results'][0]['message']);
        $this->assertSame($old, $this->sql('SELECT location_gid FROM shopify_location_map WHERE lager = 1')[0]['location_gid']);
        $this->assertSame(0, $run['levels']['hml']['SD757-A']['Ballerup'], 'the rest was zeroed');

        $restored = $this->steps(['push', 'hml']);
        $this->assertSame(5, $restored['levels']['hml']['SD757-A']['Ballerup'], 'the zeroed items were marked, so the push restores them where the mapping still points');
    }

    public function test_a_first_zero_call_without_an_answer_is_restored_by_the_push(): void
    {
        $this->inStep('A', [1 => 5]);

        $run = $this->steps(['fail', 'inventorySetQuantities', 'timeout_after_applyx4'], ['remap', 'hml', 1, null]);
        $this->assertSame('RuntimeException', $run['results'][1]['exception'] ?? null, json_encode($run['results'][1]));
        $this->assertSame(0, $run['levels']['hml']['SD757-A']['Ballerup'], 'the zero call applied');

        $push = $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertNoException($push);
        $this->assertSame(5, $push['levels']['hml']['SD757-A']['Ballerup'], 'the mapping stayed, so the push restores the stock there');
    }

    public function test_a_remap_to_a_location_another_lager_has_zeroes_nothing(): void
    {
        $this->inStep('A', [1 => 5]);
        $taken = $this->sql('SELECT location_gid FROM shopify_location_map WHERE lager = 2')[0]['location_gid'];

        $run = $this->steps(['remap', 'hml', 1, $taken]);

        $this->assertSame('ShopifyConfigError', $run['results'][0]['exception'] ?? null);
        $this->assertSame([], self::calls($run, 'stockSet'));
        $this->assertSame(5, $run['levels']['hml']['SD757-A']['Ballerup']);
    }

    public function test_a_remap_to_a_location_of_another_shop_zeroes_nothing(): void
    {
        $this->inStep('A', [1 => 5]);

        $run = $this->steps(['remap', 'hml', 1, 'gid://shopify/Location/999999']);

        $this->assertSame('ShopifyConfigError', $run['results'][0]['exception'] ?? null, json_encode($run['results'][0]));
        $this->assertSame([], self::calls($run, 'stockSet'));
        $this->assertSame(5, $run['levels']['hml']['SD757-A']['Ballerup']);
    }

    public function test_a_baseline_retry_before_a_remap_still_counts_as_written(): void
    {
        $a = $this->item('A', [1 => 5]);
        $this->shop([$a]);
        $this->offToBaseline();
        $this->steps(['start_baseline', 'hml'], ['baseline', 'hml', null]);
        $this->assertSame(5, $this->steps(['baseline', 'hml', ['prepared' => true]])['levels']['hml']['SD757-A']['Ballerup']);
        // a rerun forgets every acknowledgement before it sends again
        $this->sql("UPDATE shopify_shops SET stock_baseline_at = NULL WHERE shop_key = 'hml'");
        $this->steps(['baseline', 'hml', null]);
        $this->assertSame([], $this->state());

        $run = $this->steps(['remap', 'hml', 1, null]);

        $this->assertSame(1, $run['results'][0], 'the old location is zeroed');
        $this->assertSame(0, $run['levels']['hml']['SD757-A']['Ballerup']);
    }

    public function test_before_saldi_has_written_stock_a_remap_only_moves_the_mapping(): void
    {
        $a = $this->item('A', [1 => 5]);
        $this->shop([$a]);
        $this->sql("UPDATE shopify_shops SET stock_publish_enabled = false, stock_baseline_at = NULL WHERE shop_key = 'hml'");
        $new = $this->steps(['location', 'hml', 'Ballerup ny'])['results'][0];

        $run = $this->steps(['remap', 'hml', 1, $new]);

        $this->assertSame(0, $run['results'][0]);
        $this->assertSame(['stockRemapLocation'], array_column($run['calls'], 'operation'), 'only the new location is read: the legacy integration\'s stock is left alone');
        $this->assertSame($new, $this->sql('SELECT location_gid FROM shopify_location_map WHERE lager = 1')[0]['location_gid']);
    }

    public function test_a_stored_call_behind_a_barrier_stops_the_remap_and_keeps_the_mapping(): void
    {
        $x = $this->item('X', [1 => 4]);
        $y = $this->item('Y', [1 => 4]);
        $set = $this->set('SET', [$x => 1, $y => 1]);
        $this->shop([$set]);
        $this->dirty([$set]);
        $this->assertNoException($this->steps(['drain', 100], ['next', 'hml']));
        $old = $this->sql('SELECT location_gid FROM shopify_location_map WHERE lager = 1')[0]['location_gid'];

        $run = $this->steps(['open', 'hml', 7004, [$y]], ['remap', 'hml', 1, null]);

        $this->assertStringContainsString('waits for a move barrier', $run['results'][1]['message'] ?? '');
        $this->assertSame([], self::calls($run, 'stockSet'), 'nothing was sent');
        $this->assertSame($old, $this->sql('SELECT location_gid FROM shopify_location_map WHERE lager = 1')[0]['location_gid']);
    }

    public function test_a_remap_after_an_unanswered_baseline_call_settles_it_and_zeroes_the_old_location(): void
    {
        $a = $this->item('A', [1 => 5]);
        $this->shop([$a]);
        $this->offToBaseline();
        $this->steps(['baseline', 'hml', null]);
        $failed = $this->steps(['fail', 'inventorySetQuantities', 'timeout_after_applyx4'], ['baseline', 'hml', ['prepared' => true]]);
        $this->assertSame('RuntimeException', $failed['results'][1]['exception'] ?? null, 'the first call applied but got no answer');
        $this->assertSame(5, $failed['levels']['hml']['SD757-A']['Ballerup']);
        $new = $this->steps(['location', 'hml', 'Ballerup ny'])['results'][0];

        $run = $this->steps(['remap', 'hml', 1, $new]);

        $this->assertNoException($run);
        $this->assertSame(1, $run['results'][0], 'the stored call was settled, so the old location counts as written and is zeroed');
        $this->assertSame(0, $run['levels']['hml']['SD757-A']['Ballerup']);
        $done = $this->steps(['baseline', 'hml', ['prepared' => true]]);
        $this->assertTrue($done['results'][0]['stamped']);
        $this->assertSame(5, $done['levels']['hml']['SD757-A']['Ballerup ny']);
        $this->assertSame(0, $done['levels']['hml']['SD757-A']['Ballerup']);
    }

    public function test_an_unanswered_zero_call_waits_for_a_barrier_on_its_items(): void
    {
        $a = $this->item('A', [1 => 5]);
        $this->shop([$a]);
        $this->dirty([$a]);
        $this->assertNoException($this->steps(['drain', 100], ['push', 'hml']));
        $failed = $this->steps(['fail', 'inventorySetQuantities', 'timeout_after_applyx4'], ['remap', 'hml', 1, null]);
        $this->assertSame('RuntimeException', $failed['results'][1]['exception'] ?? null, json_encode($failed['results'][1]));

        $run = $this->steps(['open', 'hml', 7005, [$a]], ['push', 'hml']);

        $this->assertSame([], self::calls($run, 'stockSet'), 'the stored zero call waits for the barrier');
        $this->assertNotEmpty(self::calls($this->steps(['release', 'hml', 7005], ['push', 'hml']), 'stockSet'), 'and goes once it has gone');
    }

    public function test_the_stock_sandbox_scripts_print_lines_the_runner_reads(): void
    {
        $lines = 0;
        foreach (['stockCheck', 'K09', 'K13', 'K19', 'K55'] as $script) {
            $source = (string)file_get_contents(__DIR__ . "/support/sandbox/$script.php");
            preg_match_all('/echo\s+(?:"(?:K\d+|\$k)|STOCK_CHECK_K\s*\.\s*\')\s*(?:PASS|FAIL|SKIP|BLOCKED)(.)/', $source, $m);
            foreach ($m[1] as $after) {
                $this->assertSame(':', $after, "$script: the runner needs the colon right after the status (scenarios.php)");
                $lines++;
            }
        }
        $this->assertGreaterThan(10, $lines);
    }

    public function test_a_stored_call_goes_first_and_a_lager_without_a_mapping_is_refused(): void
    {
        $a = $this->item('A', [1 => 5]);
        $this->shop([$a]);
        $this->dirty([$a]);
        $this->assertNoException($this->steps(['drain', 100], ['next', 'hml']));

        $run = $this->steps(['remap', 'hml', 1, null]);

        $this->assertNoException($run);
        $sets = self::calls($run, 'stockSet');
        $this->assertCount(2, $sets);
        $this->assertSame(5, $sets[0]['variables']['input']['quantities'][0]['quantity'], 'the stored call was resolved first');
        $this->assertSame(0, $sets[1]['variables']['input']['quantities'][0]['quantity']);

        $this->assertSame('ShopifyConfigError', $this->steps(['remap', 'hml', 1, null])['results'][0]['exception']);
    }
}
