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
// the rest is sent; a pair not stocked is activated before the push, and an item refused there is left out; a stored call waits for an open barrier; a lost activation can't leave its quantity in Shopify; a pair the read misses goes on to the set; an item over one call's size is left out; a sellable lager without a
// location is skipped with a warning; a dead job's call goes out with the next drain, and a waiting
// job keeps its backoff; an item linked later is sent; a shop can't publish without capturing. Skips without ShopifyEnv.
//
// History:
// 20261104 CL/SZ SD-756: created.
// 20261104 CL/SZ SD-756 review: dead job, backoff, late link, publish without capture; the seeded state follows the link's time.
// 20261105 CL/SZ SD-756: a pair not stocked is activated before the push, not after a refusal Shopify doesn't give.
//                An item Shopify refuses to stock is left out with a warning.
// 20261105 CL/SZ SD-756 review: a stored call and an open barrier, a lost activation, a pair the read misses, an item over one call's size.
// 20261120 CDX/NTR Materialize the held batch and cover the drain limit after empty-table statistics.
// 20261124 CL/LH SD-868: a unit Shopify holds for an order Saldi has not drawn stays reserved; one Saldi drew is not held twice.
//                A shipment Saldi has not projected yet, or a draw from another lager, holds back no less; an order committed after the read makes the call stale.

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
        $this->sql("DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE ordrenr BETWEEN 86800 AND 86899 AND art = 'DO')");
        $this->sql("DELETE FROM ordrer WHERE ordrenr BETWEEN 86800 AND 86899 AND art = 'DO'");
        foreach (['shopify_fulfillments', 'shopify_order_links', 'shopify_stock_state', 'shopify_stock_dirty', 'shopify_move_barriers', 'shopify_product_links', 'shopify_location_map',
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

    /** The steps of a shop 'hml' with $n sellable lagre, each mapped to its location L<lager>. */
    private function lagre(int $n): array
    {
        $this->sql('DELETE FROM shopify_lager');
        $names = [];
        for ($i = 1; $i <= $n; $i++) {
            $this->sql("INSERT INTO shopify_lager (lager, rolle, prioritet) VALUES ($1, 'saelgbar', $1)", [$i]);
            $names[$i] = "L$i";
        }
        return [['shop', 'hml', $names]];
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
            $this->assertSame(0, $call['variables']['input']['quantities'][0]['changeFromQuantity'], 'SD-868: set only while Shopify still has what the read saw');
        }
        $this->assertSame(7, $run['levels']['hml']['SD756-7']['Mårum']);
        $this->assertSame(501, (int)$this->sql('SELECT count(*) AS n FROM shopify_stock_state')[0]['n']);

        $this->dirty($ids);
        $again = $this->steps(['drain', 1000], ['push', 'hml']);
        $this->assertSame([], self::sets($again), 'what Shopify already has is not sent again');
    }

    /**
     * A web order Saldi imported and drew (SD-743): the Saldi order with its plain line at $lager and
     * the link; $fulfilled is the link's last known Shopify fulfillment status.
     */
    private function webOrder(int $ordrenr, int $vareId, int $lager, float $qty, ?string $fulfilled = null, ?string $cancelledAt = null): string
    {
        $ordreId = (int)$this->sql("INSERT INTO ordrer (ordrenr, art, status, firmanavn) VALUES ($1, 'DO', 4, 'SD868 Kunde') RETURNING id", [$ordrenr])[0]['id'];
        $this->sql("INSERT INTO ordrelinjer (ordre_id, posnr, vare_id, varenr, antal, leveret, lager, saet) VALUES ($1, 1, $2, 'x', $3, $3, $4, 0)", [$ordreId, $vareId, $qty, $lager]);
        $gid = "gid://shopify/Order/868$ordrenr";
        $this->sql("INSERT INTO shopify_order_links (shop_id, order_gid, order_name, ordre_id, direction, state, flags, cancelled_at)
            SELECT id, $1, $2, $3, 'web', 'invoiced', $4::jsonb, $5 FROM shopify_shops WHERE shop_key = 'hml'",
            [$gid, "#$ordrenr", $ordreId, json_encode(['status' => ['fulfillment_status' => $fulfilled]]), $cancelledAt]);
        return $gid;
    }

    /** Shopify's fulfillment of $qty of the item, as SD-801 projects it. */
    private function fulfilment(string $orderGid, int $vareId, int $qty, string $status = 'success'): void
    {
        $variant = $this->sql("SELECT variant_gid FROM shopify_product_links l JOIN shopify_shops s ON s.id = l.shop_id WHERE s.shop_key = 'hml' AND l.vare_id = $1", [$vareId])[0]['variant_gid'];
        $id = random_int(1000, 999999);
        $body = ['id' => $id, 'order_id' => (int)substr($orderGid, strrpos($orderGid, '/') + 1), 'status' => $status,
            'line_items' => [['id' => 1, 'variant_id' => (int)substr($variant, strrpos($variant, '/') + 1), 'quantity' => $qty]]];
        $this->sql("INSERT INTO shopify_fulfillments (shop_id, order_gid, fulfillment_gid, status, payload_b64) SELECT id, $1, $2, $3, $4 FROM shopify_shops WHERE shop_key = 'hml'",
            [$orderGid, "gid://shopify/Fulfillment/$id", $status, base64_encode(json_encode($body))]);
    }

    public function test_a_unit_shopify_holds_for_an_order_saldi_has_not_drawn_stays_reserved_after_a_stock_change(): void
    {
        $id = $this->item('parasol', [1 => 5]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->dirty([$id]);
        $this->assertNoException($this->steps(['drain', 100], ['push', 'hml']));
        // a web order Saldi has not imported (waiting for its payment, or refused) holds 2 at Mårum
        $this->assertNoException($this->steps(['hold', 'hml', $id, 1, 2]));

        // a goods receipt of 3 in Saldi
        $this->sql('UPDATE lagerstatus SET beholdning = 8 WHERE vare_id = $1 AND lager = 1', [$id]);
        $this->dirty([$id]);
        $run = $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertNoException($run);

        $this->assertSame(['on_hand' => 8, 'committed' => 2], $run['held']['hml']['SD756-parasol']['Mårum'], 'Shopify counts the 8 Saldi has and keeps the order\'s 2');
        $this->assertSame(6, $run['levels']['hml']['SD756-parasol']['Mårum'], 'SD-868: the 2 reserved units are not put on sale again');
    }

    public function test_units_saldi_drew_for_a_web_order_not_shipped_yet_are_not_held_twice(): void
    {
        $id = $this->item('bord', [1 => 5]);
        $other = $this->item('stol', [1 => 4]);
        $this->steps(...$this->shops([$id, $other], ['hml']));
        $this->dirty([$id, $other]);
        $this->assertNoException($this->steps(['drain', 100], ['push', 'hml']));

        // the order of 2 is committed in Shopify; Saldi imports it on its authorization and draws 2 (option A), long before it ships
        $this->assertNoException($this->steps(['hold', 'hml', $id, 1, 2]));
        $order = $this->webOrder(86801, $id, 1, 2);
        $this->sql('UPDATE lagerstatus SET beholdning = 3 WHERE vare_id = $1 AND lager = 1', [$id]);
        // orders of the other item that no longer hold anything: shipped, cancelled, a store order
        $this->webOrder(86802, $other, 1, 1, 'fulfilled');
        $this->webOrder(86803, $other, 1, 1, null, '2026-11-20 10:00:00');
        $this->sql("UPDATE shopify_order_links SET direction = 'store' WHERE order_gid = $1", [$this->webOrder(86804, $other, 1, 1)]);
        $this->dirty([$id, $other]);
        $run = $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame(3, $run['levels']['hml']['SD756-bord']['Mårum'], 'the 2 Saldi drew are committed in Shopify once, not subtracted again');
        $this->assertSame(['on_hand' => 5, 'committed' => 2], $run['held']['hml']['SD756-bord']['Mårum'], 'the parcel is still in the warehouse');
        $this->assertSame([], array_values(array_filter(array_merge(...array_map(fn(array $c): array => $c['variables']['input']['quantities'], self::sets($run))),
            fn(array $q): bool => $q['quantity'] !== 3 && $q['quantity'] !== 5)), 'nothing else was sent');
        $this->assertSame(4, $run['levels']['hml']['SD756-stol']['Mårum'], 'a shipped, cancelled or store order holds nothing');

        // the warehouse ships one of the two; SD-801 projects the fulfillment
        $this->assertNoException($this->steps(['ship', 'hml', $id, 1, 1]));
        $this->fulfilment($order, $id, 1);
        $this->sql('UPDATE lagerstatus SET beholdning = 4 WHERE vare_id = $1 AND lager = 1', [$id]); // and a return of 1 in Saldi
        $this->dirty([$id]);
        $run = $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame(['on_hand' => 5, 'committed' => 1], $run['held']['hml']['SD756-bord']['Mårum'], 'the shipped unit is gone from on_hand, the other still held');
        $this->assertSame(4, $run['levels']['hml']['SD756-bord']['Mårum']);

        // a cancelled fulfillment ships nothing
        $this->fulfilment($order, $id, 1, 'cancelled');
        $this->dirty([$id]);
        $this->assertSame(4, $this->steps(['drain', 100], ['push', 'hml'])['levels']['hml']['SD756-bord']['Mårum']);
    }

    public function test_a_shipment_saldi_has_not_projected_or_a_draw_from_another_lager_holds_back_no_less(): void
    {
        $id = $this->item('hynde', [1 => 5, 2 => 4]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->dirty([$id]);
        $this->assertNoException($this->steps(['drain', 100], ['push', 'hml']));

        // an order of 2 committed at Mårum, imported and drawn; the warehouse ships 1 and the fulfillment webhook is not in yet
        $this->assertNoException($this->steps(['hold', 'hml', $id, 1, 2], ['ship', 'hml', $id, 1, 1]));
        $this->webOrder(86805, $id, 1, 2);
        // another order of 2 committed at Ballerup, which Saldi drew from Mårum instead
        $this->assertNoException($this->steps(['hold', 'hml', $id, 2, 2]));
        $this->webOrder(86806, $id, 1, 2);
        $this->sql('UPDATE lagerstatus SET beholdning = 4 WHERE vare_id = $1 AND lager = 1', [$id]); // 5 - 2 - 2 + a receipt of 3
        $this->dirty([$id]);
        $run = $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertNoException($run);

        $this->assertSame(4, $run['levels']['hml']['SD756-hynde']['Mårum'], 'Saldi\'s 4: its 4 open draws count only up to the 1 Shopify still holds there');
        $this->assertSame(2, $run['levels']['hml']['SD756-hynde']['Ballerup'], 'Saldi\'s 4 less the 2 Shopify holds there: no draw of Saldi\'s is matched to them');
    }

    public function test_an_order_committed_after_the_read_makes_the_call_stale_and_it_is_planned_again(): void
    {
        $id = $this->item('pude', [1 => 5]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->dirty([$id]);
        $this->assertNoException($this->steps(['drain', 100], ['push', 'hml']));

        $this->sql('UPDATE lagerstatus SET beholdning = 8 WHERE vare_id = $1 AND lager = 1', [$id]);
        $this->dirty([$id]);
        // the call is planned (available 8 from 5), then an order Saldi has not drawn commits 2 before it is sent
        $planned = $this->steps(['drain', 100], ['next', 'hml'], ['hold', 'hml', $id, 1, 2], ['push', 'hml']);
        $this->assertNoException($planned);
        $sets = self::sets($planned);
        $this->assertSame([5, 3], array_map(fn(array $c): ?int => $c['variables']['input']['quantities'][0]['changeFromQuantity'], $sets),
            'the stale call, then one planned again from the new read');
        $this->assertSame(6, $planned['levels']['hml']['SD756-pude']['Mårum'], 'Saldi\'s 8 less the 2 the order holds');
        $this->assertSame([], $this->sql("SELECT message FROM shopify_errors WHERE resolved_at IS NULL"), 'a stale call is no error');
        $stale = fn(): ?string => $this->sql("SELECT var_value FROM settings WHERE var_grp = 'shopify_state' AND var_name = 'stock.stale:hml'")[0]['var_value'] ?? null;
        $this->assertContains($stale(), [null, '[]', '{}'], 'the call that went through ends the run of stale answers');

        // a pair so busy that the last 3 calls were stale: the next one goes without the check, as before SD-868
        $this->sql("DELETE FROM settings WHERE var_grp = 'shopify_state' AND var_name = 'stock.stale:hml'");
        $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify_state', 'stock.stale:hml', '{\"count\": 3}')");
        $this->sql('UPDATE lagerstatus SET beholdning = 9 WHERE vare_id = $1 AND lager = 1', [$id]);
        $this->dirty([$id]);
        $busy = $this->steps(['drain', 100], ['push', 'hml']);
        $this->assertNoException($busy);
        $this->assertSame([null], array_map(fn(array $c): ?int => $c['variables']['input']['quantities'][0]['changeFromQuantity'], self::sets($busy)));
        $this->assertSame(7, $busy['levels']['hml']['SD756-pude']['Mårum']);
        $this->assertContains($stale(), [null, '[]', '{}']);
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
        try {
            $held = ShopifyEnv::rows($other, 'WITH batch AS MATERIALIZED (SELECT id FROM shopify_stock_dirty ORDER BY id LIMIT 10 FOR UPDATE SKIP LOCKED)
                DELETE FROM shopify_stock_dirty USING batch WHERE shopify_stock_dirty.id = batch.id RETURNING vare_id');
            $this->assertCount(10, $held);
            $run = $this->steps(['drain', 500]);
            $this->assertSame(['drained' => 20, 'queued' => 3], $run['results'][0], 'the held rows are skipped, not waited on');
        } finally {
            ShopifyEnv::rows($other, 'ROLLBACK');
            pg_close($other);
        }

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

    public function test_a_drain_respects_its_limit_after_empty_table_statistics(): void
    {
        $ids = [];
        for ($i = 1; $i <= 30; $i++) {
            $ids[] = $this->item("limit$i", [1 => $i]);
        }
        $this->steps(...$this->shops($ids, ['hml']));

        // Statistics from an emptied table can select a nested-loop plan that rescans an IN subquery.
        $this->sql('ALTER TABLE shopify_stock_dirty SET (autovacuum_enabled = false)');
        try {
            $this->dirty(array_fill(0, 501, $ids[0]));
            $this->sql('DELETE FROM shopify_stock_dirty');
            $this->sql('ANALYZE shopify_stock_dirty');
            $this->dirty($ids);

            $first = $this->steps(['drain', 10]);
            $this->assertNoException($first);
            $this->assertSame(['drained' => 10, 'queued' => 3], $first['results'][0]);
            $this->assertCount(20, $this->sql('SELECT id FROM shopify_stock_dirty'));

            $rest = $this->steps(['drain', 10], ['drain', 10], ['drain', 10]);
            $this->assertNoException($rest);
            $this->assertSame([10, 10, 0], array_column($rest['results'], 'drained'));
            $this->assertSame([], $this->sql('SELECT id FROM shopify_stock_dirty'));
        } finally {
            $this->sql('ALTER TABLE shopify_stock_dirty RESET (autovacuum_enabled)');
        }
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

    public function test_a_pair_not_stocked_is_activated_before_the_push(): void
    {
        $id = $this->item('hynde', [1 => 3, 2 => 8]);
        $this->steps(...$this->shops([$id], ['hml']), ...[['unstock', 'hml', $id, 2]]);
        $this->dirty([$id]);

        $run = $this->steps(['drain'], ['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame(['stockLevels', 'stockActivate', 'stockSet'], array_column($run['calls'], 'operation'));
        $this->assertSame(8, $run['calls'][1]['variables']['available'], 'a quantity hidden there is never shown');
        $this->assertSame(['Mårum' => 3, 'Ballerup' => 8], $run['levels']['hml']['SD756-hynde']);
    }

    public function test_an_item_shopify_refuses_to_stock_is_left_out_with_a_warning(): void
    {
        $new = $this->item('ny', [1 => 1, 2 => 2]);
        $old = $this->item('gammel', [1 => 3, 2 => 4]);
        $this->steps(...$this->shops([$new, $old], ['hml']));
        $this->dirty([$old]);
        $this->steps(['drain'], ['push', 'hml']);

        $this->sql("UPDATE shopify_location_map SET location_gid = 'gid://shopify/Location/1' WHERE lager = 2");
        $this->sql('UPDATE lagerstatus SET beholdning = 9 WHERE vare_id = $1 AND lager = 1', [$old]);
        $this->dirty([$new, $old]);
        $run = $this->steps(['drain'], ['push', 'hml']);

        $this->assertNoException($run);
        $this->assertSame(['stockLevels', 'stockActivate', 'stockSet'], array_column($run['calls'], 'operation'));
        $this->assertSame([9], array_column(self::sets($run)[0]['variables']['input']['quantities'], 'quantity'), 'the rest goes out');
        $error = $this->sql("SELECT severity, message FROM shopify_errors WHERE ref = $1 AND resolved_at IS NULL", ["item:$new"]);
        $this->assertSame('warning', $error[0]['severity'] ?? null);
        $this->assertStringContainsString("refused to stock item $new", $error[0]['message']);
        $this->assertSame([], $this->steps(['next', 'hml'])['results'][0], 'its mark is gone');
    }

    public function test_a_pair_a_shop_stopped_stocking_after_a_push_is_activated_again(): void
    {
        $id = $this->item('bord', [1 => 3, 2 => 8]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->dirty([$id]);
        $this->assertSame(['stockLevels', 'stockSet'], array_column($this->steps(['drain'], ['push', 'hml'])['calls'], 'operation'), 'both pairs are stocked, so nothing is activated');

        $this->steps(['unstock', 'hml', $id, 2]);
        $this->sql('UPDATE lagerstatus SET beholdning = 6 WHERE vare_id = $1 AND lager = 2', [$id]);
        $this->dirty([$id]);
        $run = $this->steps(['drain'], ['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame(['stockLevels', 'stockActivate', 'stockSet'], array_column($run['calls'], 'operation'));
        $this->assertSame(['Mårum' => 3, 'Ballerup' => 6], $run['levels']['hml']['SD756-bord']);
    }

    public function test_a_stored_call_waits_for_an_open_barrier_with_its_key_and_input(): void
    {
        $id = $this->item('sofa', [1 => 5]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->dirty([$id]);
        $vars = $this->steps(['drain'], ['next', 'hml'])['results'][1]['vars'];

        $held = $this->steps(['open', 'hml', 900100, [$id]], ['push', 'hml']);
        $this->assertNoException($held);
        $this->assertSame([], self::sets($held), 'a call read before the barrier opened is not sent past it');
        $this->assertSame('yield', $held['results'][1]['outcome']);
        $this->assertSame(60, $held['results'][1]['run_after']);

        $sent = $this->steps(['release', 'hml', 900100], ['push', 'hml']);
        $this->assertNoException($sent);
        $this->assertEquals($vars, self::sets($sent)[0]['variables'], 'the same key and input go out after the release');
    }

    public function test_a_lost_activation_cannot_leave_shopify_on_its_quantity(): void
    {
        $id = $this->item('reol', [1 => 3, 2 => 8]);
        $this->steps(...$this->shops([$id], ['hml']));
        $this->dirty([$id]);
        $this->assertNoException($this->steps(['drain'], ['push', 'hml']));

        $this->steps(['unstock', 'hml', $id, 2]);
        $this->sql('UPDATE lagerstatus SET beholdning = 6 WHERE vare_id = $1 AND lager = 2', [$id]);
        $this->dirty([$id]);
        $lost = $this->steps(['drain'], ['fail', 'inventoryActivate', 'timeout_after_applyx4'], ['push', 'hml']);
        $this->assertSame(6, $lost['levels']['hml']['SD756-reol']['Ballerup'], 'the activation applied, its answer was lost');

        // Saldi goes back to the acknowledged number before the retry
        $this->sql('UPDATE lagerstatus SET beholdning = 8 WHERE vare_id = $1 AND lager = 2', [$id]);
        $run = $this->steps(['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame(8, $run['levels']['hml']['SD756-reol']['Ballerup']);
    }

    public function test_a_pair_the_read_misses_goes_on_to_the_set(): void
    {
        $id = $this->item('lampe', [51 => 4]);
        $this->steps(...$this->lagre(51), ...[['seed', 'hml', $id], ['clean']]);
        $this->dirty([$id]);

        $run = $this->steps(['drain'], ['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame(['stockLevels', 'stockActivate', 'stockSet'], array_column($run['calls'], 'operation'), 'the 51st location is past the read');
        $this->assertSame(4, $run['levels']['hml']['SD756-lampe']['L51']);
        $this->assertSame([], $this->sql("SELECT 1 FROM shopify_errors WHERE ref = $1 AND resolved_at IS NULL", ["item:$id"]));
    }

    public function test_an_item_over_one_calls_size_is_left_out_with_a_warning(): void
    {
        $big = $this->item('stor', [1 => 1]);
        $small = $this->item('lille', [1 => 4]);
        $this->steps(...$this->lagre(251), ...[['seed', 'hml', $big], ['seed', 'hml', $small], ['clean']]);
        $this->sql("INSERT INTO shopify_stock_state (shop_id, vare_id, variant_id, lager, quantity, acknowledged_at)
            SELECT l.shop_id, l.vare_id, 0, g.lager, 0, l.updated_at FROM shopify_product_links l, shopify_lager g WHERE l.vare_id = $1 AND g.lager > 1", [$small]);
        $this->dirty([$big, $small]);

        $run = $this->steps(['drain'], ['push', 'hml']);
        $this->assertNoException($run);
        $this->assertSame([4], array_column(self::sets($run)[0]['variables']['input']['quantities'], 'quantity'), 'the item behind it goes out');
        $this->assertSame('warning', $this->sql("SELECT severity FROM shopify_errors WHERE ref = $1 AND resolved_at IS NULL", ["item:$big"])[0]['severity'] ?? null);
        $this->assertSame([], $this->steps(['next', 'hml'])['results'][0], 'its mark is gone');
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
