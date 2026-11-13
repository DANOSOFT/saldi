<?php
// tests/shopify/StockActivateTest.php
//
// SD-822: stock.activate stocks every linked item at its locations in bulk before go-live, so the
// baseline only sets quantities. Runs the job against FakeShopifyStore (B38 read, B39 bulk mutation,
// B40 inventoryBulkToggleActivation) through run_stock.php, and the command through the real CLI.
//
// 20261112 CL/SZ SD-822: created.
// 20261112 CL/SZ SD-822 CodeRabbit: running out of time before the upload; singular counts in the report.
// 20261113 CL/SZ SD-822 Review: running out of time before the start; an unanswered zero call goes again under its key.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class StockActivateTest extends TestCase
{
    /** lager => Shopify location name; 4 is a display. */
    private const LOCATIONS = [1 => 'Ballerup', 2 => 'Kolding', 4 => 'Ballerup udstilling'];

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
            self::$db = ShopifyEnv::testDb('_a');
            // a fresh checkout has no temp/ (gitignored); the job makes only the tenant's folder below it
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
        $this->state = tempnam(sys_get_temp_dir(), 'sd822_');
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
        $this->sql("DELETE FROM lagerstatus WHERE vare_id IN (SELECT id FROM varer WHERE varenr LIKE 'SD822-%')");
        $this->sql("DELETE FROM varer WHERE varenr LIKE 'SD822-%'");
        $this->sql("INSERT INTO shopify_lager (lager, rolle, prioritet) VALUES (1, 'saelgbar', 1), (2, 'saelgbar', 2), (4, 'udstilling', NULL)");
        // before go-live: capture on, publishing off, no baseline
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, stock_capture_enabled, stock_publish_enabled, stock_baseline_at)
            VALUES ('hml', 'hml-fake.myshopify.com', 't', true, false, NULL)");
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
        $id = (int)$this->sql("INSERT INTO varer (varenr, beskrivelse) VALUES ($1, $2) RETURNING id", ["SD822-$varenr", "Havestol $varenr"])[0]['id'];
        foreach ($stock as $lager => $qty) {
            $this->sql('INSERT INTO lagerstatus (lager, vare_id, beholdning) VALUES ($1, $2, $3)', [$lager, $id, $qty]);
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
    private function shop(array $items): void
    {
        $steps = [['shop', 'hml', self::LOCATIONS]];
        foreach ($items as $id) {
            $steps[] = ['seed', 'hml', $id];
        }
        $steps[] = ['clean'];
        $this->steps(...$steps);
    }

    /**
     * Runs the job until it is done, each pass in its own run as the worker would.
     *
     * @return array{outcome: array<string, mixed>, calls: list<array<string, mixed>>, levels: array<string, mixed>, passes: int}
     */
    private function activate(bool $dryRun = false, ?array $checkpoint = null): array
    {
        $calls = [];
        for ($pass = 1; $pass <= 10; $pass++) {
            $run = $this->steps(['activate', 'hml', $checkpoint, $dryRun]);
            $calls = array_merge($calls, $run['calls']);
            $out = $run['results'][0];
            $this->assertArrayNotHasKey('exception', $out, json_encode($out));
            if ($out['outcome'] === 'done') {
                return ['outcome' => $out, 'calls' => $calls, 'levels' => $run['levels']['hml'] ?? [], 'passes' => $pass];
            }
            $checkpoint = $out['checkpoint'];
        }
        $this->fail('stock.activate did not finish in 10 passes');
    }

    private static function operations(array $calls): array
    {
        return array_column($calls, 'operation');
    }

    private static function calls(array $calls, string $operation): array
    {
        return array_values(array_filter($calls, fn(array $c): bool => $c['operation'] === $operation));
    }

    private function report(): array
    {
        return $this->sql("SELECT severity, message FROM shopify_errors WHERE kind = 'stock' AND ref = 'activate' AND resolved_at IS NULL")[0] ?? [];
    }

    /** @return list<string> the open item warnings, as "ref: message" */
    private function itemErrors(): array
    {
        return array_map(fn(array $r): string => "{$r['ref']}: {$r['message']}", $this->sql("SELECT ref, message FROM shopify_errors WHERE ref LIKE 'item:%' AND resolved_at IS NULL ORDER BY ref"));
    }

    private function levels(): array
    {
        return $this->steps()['levels']['hml'] ?? [];
    }

    // ---- the run ---------------------------------------------------------------------------

    public function test_a_dry_run_counts_the_pairs_left_and_sends_nothing(): void
    {
        $a = $this->item('A', [1 => 3]);
        $b = $this->item('B', [1 => 2]);
        $this->shop([$a, $b]);
        $this->steps(['unstock', 'hml', $a, 2], ['unstock', 'hml', $a, 4], ['unstock', 'hml', $b, 4]);
        $this->sql("INSERT INTO shopify_stock_state (shop_id, vare_id, variant_id, lager, quantity, acknowledged_at)
            SELECT id, $1, 0, 2, 3, now() - interval '1 day' FROM shopify_shops WHERE shop_key = 'hml'", [$a]);

        $run = $this->activate(true);

        $this->assertSame(3, $run['outcome']['pairs']);
        $this->assertSame(['severity' => 'info', 'message' => 'Dry run: 3 pairs at 2 items are not stocked yet.'], $this->report());
        $this->assertSame([], array_intersect(self::operations($run['calls']), ['stagedUploadsCreate', 'stockSet', 'bulkOperationRunMutation', 'stockActivate']),
            'a dry run only reads');
        $this->assertArrayNotHasKey('Kolding', $run['levels']['SD822-A'], 'nothing was stocked');
        $this->assertSame(1, (int)$this->sql('SELECT count(*) AS n FROM shopify_stock_state')[0]['n'], 'no acknowledgement was forgotten');
    }

    public function test_a_run_stocks_every_pair_left_at_0_also_where_a_quantity_was_hidden(): void
    {
        $a = $this->item('A', [1 => 3, 2 => 5]);
        $b = $this->item('B', [1 => 2]);
        $this->shop([$a, $b]);
        $this->steps(['unstock', 'hml', $a, 2], ['unstock', 'hml', $a, 4], ['unstock', 'hml', $b, 4], ['hide', 'hml', $a, 2, 7]);

        $run = $this->activate();

        $this->assertSame(['severity' => 'info', 'message' => 'Stocked 3 of 3 pairs at 2 items.'], $this->report());
        $this->assertSame(0, $run['levels']['SD822-A']['Kolding'], 'the hidden 7 was set to 0 before the pair was stocked');
        $this->assertSame(0, $run['levels']['SD822-A']['Ballerup udstilling'], 'the display location is stocked too, at 0');
        $this->assertSame(0, $run['levels']['SD822-B']['Ballerup udstilling']);
        $ops = self::operations($run['calls']);
        $this->assertLessThan(array_search('bulkOperationRunMutation', $ops, true), array_search('stockSet', $ops, true), 'the zeroing goes first');
        $zeroed = self::calls($run['calls'], 'stockSet')[0]['variables']['input']['quantities'];
        $this->assertCount(3, $zeroed, 'only the pairs to stock are zeroed');
        $this->assertSame([0], array_values(array_unique(array_column($zeroed, 'quantity'))));
        $this->assertSame([], $this->itemErrors());
    }

    public function test_after_the_run_the_baseline_sends_quantities_only(): void
    {
        $a = $this->item('A', [1 => 3, 2 => 5]);
        $this->shop([$a]);
        $this->steps(['unstock', 'hml', $a, 2], ['unstock', 'hml', $a, 4]);
        $this->activate();

        $this->steps(['start_baseline', 'hml']);
        $prepared = $this->steps(['baseline', 'hml', null]);
        $sent = $this->steps(['baseline', 'hml', $prepared['results'][0]['checkpoint']]);

        $this->assertTrue($sent['results'][0]['stamped'], json_encode($sent['results'][0]));
        $this->assertSame([], self::calls($sent['calls'], 'stockActivate'), 'every pair was stocked: no inventoryActivate');
        $this->assertSame(['Ballerup' => 3, 'Kolding' => 5, 'Ballerup udstilling' => 0], $sent['levels']['hml']['SD822-A']);
        $again = $this->steps(['start_activate', 'hml', false]);
        $this->assertSame('ShopifyConfigError', $again['results'][0]['exception'] ?? null, 'a shop with a baseline is not activated again');
    }

    public function test_a_rerun_finds_every_pair_stocked_and_sends_nothing(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a]);
        $this->steps(['unstock', 'hml', $a, 4]);
        $this->activate();

        $run = $this->activate();

        $this->assertSame(['severity' => 'info', 'message' => 'Every linked item is stocked at all its locations.'], $this->report());
        $this->assertSame([], array_intersect(self::operations($run['calls']), ['stockSet', 'bulkOperationRunMutation']));
    }

    public function test_an_item_shopify_refuses_is_reported_and_the_others_are_stocked(): void
    {
        $a = $this->item('A', [1 => 3]);
        $b = $this->item('B', [1 => 2]);
        $this->shop([$a, $b]);
        $this->steps(['unstock', 'hml', $a, 2], ['unstock', 'hml', $b, 2], ['refuse_toggle', 'hml', $a]);

        $run = $this->activate();

        $this->assertSame(1, $run['outcome']['refused']);
        $this->assertSame(0, $run['levels']['SD822-B']['Kolding'], 'the other item is stocked');
        $this->assertArrayNotHasKey('Kolding', $run['levels']['SD822-A']);
        $this->assertSame(["item:$a: Shopify refused to stock item $a at its locations: FAILED_TO_STOCK_AT_LOCATION The item could not be stocked at the location."], $this->itemErrors());
        $this->assertSame('warning', $this->report()['severity']);
        $this->assertStringStartsWith('Stocked 1 of 2 pairs at 2 items. Shopify refused 1 item', $this->report()['message']);
    }

    public function test_an_item_refused_at_the_zeroing_is_left_out_and_the_rest_of_its_call_is_still_zeroed(): void
    {
        $a = $this->item('A', [1 => 3]);
        $b = $this->item('B', [1 => 2]);
        $c = $this->item('C', [1 => 1]);
        $this->shop([$a, $b, $c]);
        $this->steps(['unstock', 'hml', $a, 2], ['unstock', 'hml', $b, 2], ['unstock', 'hml', $c, 2], ['hide', 'hml', $b, 2, 7],
            ['refuse_set', 'hml', $a], ['limit_bulk', 'hml']);

        $run = $this->activate();

        $this->assertSame(0, $run['levels']['SD822-B']['Kolding'], 'the call refused for A set nothing, so B was zeroed again before it was stocked');
        $this->assertSame(0, $run['levels']['SD822-C']['Kolding']);
        $this->assertArrayNotHasKey('Kolding', $run['levels']['SD822-A'], 'the refused item is not stocked');
        $this->assertCount(2, self::calls($run['calls'], 'bulkOperationRunMutation'), 'LIMIT_REACHED, then the start again');
        $this->assertSame(['severity' => 'warning', 'message' => "Stocked 2 of 3 pairs at 3 items. Shopify refused 1 item (see their warnings); the baseline won't stamp until they are fixed."], $this->report());
        $this->assertCount(1, $this->itemErrors());
        $this->assertStringStartsWith("item:$a: Shopify refused setting item $a to 0", $this->itemErrors()[0]);
    }

    public function test_running_out_of_time_before_the_upload_keeps_the_zeroing_done(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a]);
        $this->steps(['unstock', 'hml', $a, 2], ['unstock', 'hml', $a, 4]);

        $checkpoint = null;
        $zeroing = [];
        for ($pass = 1; $pass <= 5; $pass++) {
            $late = $this->steps(['activate', 'hml', $checkpoint, false, 30, true]);
            $out = $late['results'][0];
            $zeroing = array_merge($zeroing, self::calls($late['calls'], 'stockSet'));
            $this->assertArrayNotHasKey('exception', $out, 'the job yields instead of failing: ' . json_encode($out));
            $checkpoint = $out['checkpoint'];
            if (isset($checkpoint['pairs'])) {
                break;
            }
        }

        $this->assertSame('yield', $out['outcome']);
        $this->assertSame(1, $out['checkpoint']['zeroed'], "this pass's zeroing is kept");
        $this->assertArrayNotHasKey('op', $out['checkpoint']);
        $this->assertCount(1, $zeroing);
        $run = $this->activate(false, $out['checkpoint']);
        $this->assertSame([], self::calls($run['calls'], 'stockSet'), 'the next pass does not zero again');
        $this->assertSame(['severity' => 'info', 'message' => 'Stocked 2 of 2 pairs at 1 item.'], $this->report());
    }

    public function test_running_out_of_time_before_the_start_keeps_the_zeroing_and_yields(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a]);
        $this->steps(['unstock', 'hml', $a, 2], ['unstock', 'hml', $a, 4]);

        $checkpoint = null;
        $out = [];
        for ($pass = 1; $pass <= 5; $pass++) {
            $late = $this->steps(['activate', 'hml', $checkpoint, false, 30, 'start']);
            $out = $late['results'][0];
            $this->assertArrayNotHasKey('exception', $out, 'the job yields instead of failing: ' . json_encode($out));
            $this->assertSame([], self::calls($late['calls'], 'bulkOperationRunMutation'), 'no start goes out once the time is spent');
            $checkpoint = $out['checkpoint'];
            if (isset($checkpoint['pairs'])) {
                break;
            }
        }

        $this->assertSame('yield', $out['outcome']);
        $this->assertSame(1, $out['checkpoint']['zeroed'], "this pass's zeroing is kept");
        $this->assertArrayNotHasKey('op', $out['checkpoint']);
        $run = $this->activate(false, $out['checkpoint']);
        $this->assertSame([], self::calls($run['calls'], 'stockSet'), 'the next pass does not zero again');
        $this->assertCount(1, self::calls($run['calls'], 'bulkOperationRunMutation'));
        $this->assertSame(['severity' => 'info', 'message' => 'Stocked 2 of 2 pairs at 1 item.'], $this->report());
    }

    public function test_a_zero_call_without_an_answer_goes_again_under_the_same_key(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a]);
        $this->steps(['unstock', 'hml', $a, 2], ['unstock', 'hml', $a, 4]);

        // the zero is applied in Shopify, but no answer comes back in this pass
        $checkpoint = null;
        $first = [];
        $out = [];
        for ($pass = 1; $pass <= 5; $pass++) {
            $lost = $this->steps(['fail', 'inventorySetQuantities', 'timeout_after_applyx9'], ['activate', 'hml', $checkpoint]);
            $out = $lost['results'][1];
            $first = array_merge($first, self::calls($lost['calls'], 'stockSet'));
            $this->assertArrayNotHasKey('exception', $out, 'the job yields instead of failing: ' . json_encode($out));
            $checkpoint = $out['checkpoint'];
            if ($first !== []) {
                break;
            }
        }

        $this->assertSame('yield', $out['outcome']);
        $this->assertSame(0, $out['checkpoint']['zeroed'], 'an unanswered zero does not count as done');
        $this->assertArrayHasKey('zero_key', $out['checkpoint']);
        $run = $this->activate(false, $out['checkpoint']);
        $again = self::calls($run['calls'], 'stockSet');
        $this->assertCount(1, $again);
        $keys = array_unique(array_map(fn(array $c): string => $c['variables']['key'], array_merge($first, $again)));
        $this->assertSame([$out['checkpoint']['zero_key']], array_values($keys), 'every attempt, in either pass, carries the one key');
        $this->assertSame($first[0]['variables']['input'], $again[0]['variables']['input'], 'and the same input');
        $this->assertSame(['severity' => 'info', 'message' => 'Stocked 2 of 2 pairs at 1 item.'], $this->report());
    }

    public function test_a_zero_call_that_never_gets_an_answer_fails_the_job_in_the_end(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a]);
        $this->steps(['unstock', 'hml', $a, 2]);

        $checkpoint = null;
        $keys = [];
        $out = [];
        for ($pass = 1; $pass <= 12; $pass++) {
            $lost = $this->steps(['fail', 'inventorySetQuantities', 'http_500x9'], ['activate', 'hml', $checkpoint]);
            $out = $lost['results'][1];
            $keys = array_merge($keys, array_map(fn(array $c): string => $c['variables']['key'], self::calls($lost['calls'], 'stockSet')));
            if (isset($out['exception'])) {
                break;
            }
            $checkpoint = $out['checkpoint'];
        }

        $this->assertSame('zeroing the pairs to stock failed: retries_exhausted', $out['message'] ?? null, 'the job fails, so the worker retries and then raises its alarm');
        $this->assertSame(4, $checkpoint['zero_tries'], 'the fifth unanswered pass (SHOPIFY_STOCK_ACTIVATE_ZERO_TRIES) fails');
        $this->assertCount(1, array_unique($keys), 'all under the one key');
        // the failed job keeps its file for its retries; this test ends it here
        array_map('unlink', glob(ShopifyEnv::repoRoot() . '/temp/' . self::$db . '/.ht_shopify_activate*') ?: []);
    }

    public function test_a_failed_bulk_operation_is_reported_and_a_rerun_stocks_only_what_is_left(): void
    {
        $a = $this->item('A', [1 => 3]);
        $b = $this->item('B', [1 => 2]);
        $c = $this->item('C', [1 => 1]);
        $this->shop([$a, $b, $c]);
        $this->steps(['unstock', 'hml', $a, 2], ['unstock', 'hml', $b, 2], ['unstock', 'hml', $c, 2], ['fail_bulk', 'hml', 1]);

        $failed = $this->activate();

        $this->assertSame('warning', $this->report()['severity']);
        $this->assertStringStartsWith('The stock activation ended FAILED', $this->report()['message']);
        $this->assertSame(0, $failed['levels']['SD822-A']['Kolding'], 'the line applied before the failure is stocked');

        $rerun = $this->activate();

        $this->assertSame(['severity' => 'info', 'message' => 'Stocked 2 of 2 pairs at 2 items.'], $this->report());
        $this->assertCount(2, self::calls($rerun['calls'], 'stockSet')[0]['variables']['input']['quantities'], 'only what was left');
        $this->assertSame(0, $rerun['levels']['SD822-C']['Kolding']);
        $this->assertSame([], glob(ShopifyEnv::repoRoot() . '/temp/' . self::$db . '/.ht_shopify_activate*'), 'the job leaves no file');
    }

    public function test_the_run_forgets_the_acknowledgements_of_the_pairs_it_stocks_from_before_its_read_only(): void
    {
        $a = $this->item('A', [1 => 3, 2 => 5]);
        $this->shop([$a]);
        $this->steps(['unstock', 'hml', $a, 2], ['unstock', 'hml', $a, 4]);
        $shop = "(SELECT id FROM shopify_shops WHERE shop_key = 'hml')";
        $read = $this->steps(['activate', 'hml', null]);
        // times relative to the read's start, which the worker takes on its own session clock
        $at = $read['results'][0]['checkpoint']['started_at'];
        $this->sql("INSERT INTO shopify_stock_state (shop_id, vare_id, variant_id, lager, quantity, acknowledged_at) VALUES
            ($shop, $1, 0, 1, 3, $2::timestamp - interval '1 hour'), ($shop, $1, 0, 2, 5, $2::timestamp - interval '1 hour'),
            ($shop, $1, 0, 4, 0, $2::timestamp + interval '1 minute')", [$a, $at]);
        $this->activate(false, $read['results'][0]['checkpoint']);

        $left = array_column($this->sql('SELECT lager FROM shopify_stock_state ORDER BY lager'), 'lager');
        $this->assertSame(['1', '4'], $left, 'lager 2 (stocked by the run, acknowledged before its read) is forgotten; 1 was stocked already, 4 was acknowledged after the read');
    }

    public function test_a_job_for_a_shop_with_a_baseline_stops_with_a_warning(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a]);
        $this->steps(['unstock', 'hml', $a, 2]);
        $read = $this->steps(['activate', 'hml', null]);
        $this->sql("UPDATE shopify_shops SET stock_baseline_at = now() WHERE shop_key = 'hml'");

        $run = $this->steps(['activate', 'hml', $read['results'][0]['checkpoint']]);

        $this->assertSame('done', $run['results'][0]['outcome']);
        $this->assertStringStartsWith('The stock activation stopped: shop hml has a stock baseline from', $this->report()['message']);
        $this->assertSame([], array_intersect(self::operations($run['calls']), ['stockSet', 'bulkOperationRunMutation']));
    }

    // ---- items linked after the run ---------------------------------------------------------

    public function test_an_item_linked_after_the_run_is_stocked_at_every_location_by_its_first_push(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a]);
        $this->activate();
        $this->steps(['start_baseline', 'hml']);
        $prepared = $this->steps(['baseline', 'hml', null]);
        $this->assertTrue($this->steps(['baseline', 'hml', $prepared['results'][0]['checkpoint']])['results'][0]['stamped']);
        $this->sql("UPDATE shopify_shops SET stock_publish_enabled = true WHERE shop_key = 'hml'");

        $late = $this->item('LATE', [1 => 4, 2 => 1]);
        $this->steps(['seed', 'hml', $late], ['unstock', 'hml', $late, 2], ['unstock', 'hml', $late, 4]);
        $this->assertGreaterThan(0, (int)$this->sql('SELECT count(*) AS n FROM shopify_stock_dirty WHERE vare_id = $1', [$late])[0]['n'], 'the new link marks the item');
        $run = $this->steps(['drain', 100], ['push', 'hml']);

        $this->assertCount(2, self::calls($run['calls'], 'stockActivate'), 'the push stocks the two pairs itself');
        $this->assertSame(['Ballerup' => 4, 'Kolding' => 1, 'Ballerup udstilling' => 0], $run['levels']['hml']['SD822-LATE']);
    }

    // ---- the command ------------------------------------------------------------------------

    public function test_the_command_takes_a_bare_dry_run_and_queues_its_own_job(): void
    {
        $dry = $this->runTool(['activate', '--db=' . self::$db, '--shop=hml', '--dry-run']);
        $real = $this->runTool(['activate', '--db=' . self::$db, '--shop=hml']);
        $bad = $this->runTool(['activate', '--db=' . self::$db, '--shop=hml', '--dry-run=yes']);

        $this->assertSame(0, $dry['exit'], $dry['stderr']);
        $this->assertSame(0, $real['exit'], $real['stderr']);
        $this->assertSame(2, $bad['exit'], 'an unclear dry-run value runs nothing');
        $this->assertTrue(json_decode($dry['stdout'], true)['dry_run']);
        $this->assertFalse(json_decode($real['stdout'], true)['dry_run']);
        $jobs = $this->sql("SELECT dedupe_key, payload->>'dry_run' AS dry FROM shopify_jobs WHERE type = 'stock.activate' ORDER BY id");
        $this->assertSame([['dedupe_key' => 'hml:activate-dry', 'dry' => 'true'], ['dedupe_key' => 'hml:activate', 'dry' => 'false']], $jobs,
            'a dry run and a real run are two jobs');

        $this->sql("UPDATE shopify_shops SET stock_baseline_at = now() WHERE shop_key = 'hml'");
        $after = $this->runTool(['activate', '--db=' . self::$db, '--shop=hml']);
        $this->assertSame(2, $after['exit']);
        $this->assertStringContainsString('has a stock baseline', $after['stderr']);
    }

    public function test_another_option_without_a_value_still_asks_for_it(): void
    {
        $remap = $this->runTool(['remap', '--db=' . self::$db, '--shop=hml', '--lager']);

        $this->assertSame(2, $remap['exit'], 'a bare --lager is not lager 1');
        $this->assertStringContainsString('usage:', $remap['stderr']);
    }

    public function test_activate_and_the_baseline_refuse_each_other_while_one_is_queued(): void
    {
        $a = $this->item('A', [1 => 3]);
        $this->shop([$a]);
        $this->steps(['unstock', 'hml', $a, 2]);

        $this->steps(['start_activate', 'hml', false]);
        $baseline = $this->steps(['start_baseline', 'hml']);
        $this->assertSame('ShopifyConfigError', $baseline['results'][0]['exception'] ?? null, 'no baseline while an activation is queued');
        $this->assertStringContainsString('activation queued or running', $baseline['results'][0]['message']);
        $this->steps(['start_activate', 'hml', true]);
        $this->sql("DELETE FROM shopify_jobs WHERE type = 'stock.activate' AND dedupe_key = 'hml:activate'");
        $this->assertSame('integer', gettype($this->steps(['start_baseline', 'hml'])['results'][0]), 'a dry run does not hold the baseline back');

        $activate = $this->steps(['start_activate', 'hml', false]);
        $this->assertSame('ShopifyConfigError', $activate['results'][0]['exception'] ?? null, 'no activation while a baseline is queued');
        $read = $this->steps(['activate', 'hml', null]);
        $this->assertSame('done', $read['results'][0]['outcome'], 'a job already queued stops at its next pass');
        $this->assertStringContainsString('has a stock baseline queued or running', $this->report()['message']);
        $this->assertSame([], array_intersect(self::operations($read['calls']), ['bulkOperationRunQuery', 'stockSet']));
    }

    public function test_an_item_shopify_s_read_lacks_makes_the_report_a_warning(): void
    {
        $a = $this->item('A', [1 => 3]);
        $b = $this->item('B', [1 => 2]);
        $this->shop([$a, $b]);
        $this->sql("UPDATE shopify_product_links SET inventory_item_gid = 'gid://shopify/InventoryItem/999999' WHERE vare_id = $1", [$b]);

        $this->activate();

        $this->assertSame(['severity' => 'warning', 'message' => "Every linked item in Shopify's read is stocked at all its locations. 1 linked item is not in Shopify's read and was left out."], $this->report());
    }

    /** @return array{exit: int, stdout: string, stderr: string} */
    private function runTool(array $args): array
    {
        $cmd = array_merge([PHP_BINARY, ShopifyEnv::repoRoot() . '/tools/shopify_worker.php'], $args);
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ShopifyEnv::repoRoot());
        fclose($pipes[0]);
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['exit' => proc_close($proc), 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
