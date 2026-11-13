<?php
// tests/shopify/OrderMoveSyncTest.php
//
// order.move, barrier.sweep and the move list (SD-760, WP10-2) on a test tenant against the
// FakeShopifyStore, through the worker as tools/shopify_worker.php wires them (run_writeback.php).
// Skipped without the test database.
//
// History:
// 20261113 CL/MJ SD-760: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class OrderMoveSyncTest extends TestCase
{
    private const DAY = '2026-11-13';

    private static ?string $skip = null;
    private static string $db = '';
    /** @var resource|\PgSql\Connection|null */
    private $conn = null;
    private string $state = '';
    /** @var list<string> location GIDs: index 0 is lager 91, 1 is 92, 2 is 93 */
    private array $locations = [];
    private int $ordrenr = 7600;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_fm');
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
        $this->state = tempnam(sys_get_temp_dir(), 'sd760_');
        foreach (['shopify_order_links', 'shopify_jobs', 'shopify_errors', 'shopify_move_barriers', 'shopify_location_map', 'shopify_lager', 'dokument_links', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE firmanavn = 'SD760')");
        $this->sql("DELETE FROM ordrer WHERE firmanavn = 'SD760'");
        $this->sql("DELETE FROM grupper WHERE art = 'LG' AND kodenr::text IN ('91', '92', '93')");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art) VALUES ('Mårum', 91, 'LG'), ('Hillerød', 92, 'LG'), ('Roskilde', 93, 'LG')");
        $this->sql("DELETE FROM settings WHERE var_grp = 'shopify' AND var_name = 'webhook_base_url'");
        $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify', 'webhook_base_url', 'https://saldi.example')");
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, orders_enabled) VALUES ('hml', 'hml-fake.myshopify.com', true, true)");
        $this->locations = $this->steps(['location', 'Lager 2'], ['location', 'Lager 3'])['locations'];
        $shopId = $this->shopId();
        foreach ([91, 92, 93] as $i => $lager) {
            $this->sql("INSERT INTO shopify_lager (lager, rolle, prioritet) VALUES ($1, 'saelgbar', $2)", [$lager, $i + 1]);
            $this->sql('INSERT INTO shopify_location_map (shop_id, lager, location_gid) VALUES ($1, $2, $3)', [$shopId, $lager, $this->locations[$i]]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            pg_close($this->conn);
        }
        foreach ([$this->state, $this->state . '.heartbeat'] as $file) {
            if ($file !== '' && is_file($file)) {
                unlink($file);
            }
        }
    }

    private function sql(string $sql, array $params = []): array
    {
        return ShopifyEnv::rows($this->conn, $sql, $params);
    }

    private function steps(array ...$steps): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_writeback.php', [self::$db, $this->state, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'];
    }

    private function shopId(): int
    {
        return (int)$this->sql("SELECT id FROM shopify_shops WHERE shop_key = 'hml'")[0]['id'];
    }

    /**
     * An invoiced web order as the import leaves it: its Saldi lines, allocation_v1, a barrier per
     * item, and order.tags queued. The barriers expire in a day rather than 30 minutes: expires_at has
     * no time zone, and this connection's zone may not be the worker's.
     *
     * @param list<array{gid: string, rows: list<array{0: string, 1: int, 2: int}>, set?: array{0: string, 1: int}}> $lines
     *   rows: [varenr, lager, antal]; set: [varenr, lager] of the set's own line
     */
    private function invoiced(string $gid, array $lines, ?int $shipLager = 91, string $day = self::DAY): int
    {
        $ordreId = (int)$this->sql("INSERT INTO ordrer (ordrenr, fakturanr, art, status, firmanavn, ordredate) VALUES ($1, $2, 'DO', 4, 'SD760', $3) RETURNING id",
            [$this->ordrenr, (string)$this->ordrenr++, $day])[0]['id'];
        $insert = 'INSERT INTO ordrelinjer (ordre_id, posnr, varenr, beskrivelse, antal, lager) VALUES ($1, $2, $3, $4, $5, $6) RETURNING id';
        $alloc = [];
        $posnr = 0;
        foreach ($lines as $line) {
            $rows = [];
            foreach ($line['rows'] as [$varenr, $lager, $antal]) {
                $id = (int)$this->sql($insert, [$ordreId, ++$posnr, $varenr, "Vare $varenr", $antal, $lager])[0]['id'];
                $rows[] = ['ordrelinje_id' => $id, 'vare_id' => $posnr, 'lager' => $lager, 'quantity_milli' => 1000 * $antal];
            }
            $quantity = isset($line['set']) ? $rows[0]['quantity_milli'] / 1000 : array_sum(array_column($rows, 'quantity_milli')) / 1000;
            if (isset($line['set'])) {
                $id = (int)$this->sql($insert, [$ordreId, ++$posnr, $line['set'][0], "Sæt {$line['set'][0]}", $quantity, $line['set'][1]])[0]['id'];
                $rows[] = ['ordrelinje_id' => $id, 'vare_id' => $posnr, 'lager' => $line['set'][1], 'quantity_milli' => 1000 * $quantity, 'set_line' => true];
            }
            $alloc[] = ['line_gid' => $line['gid'], 'vare_id' => $posnr, 'quantity' => $quantity, 'set' => isset($line['set']),
                'split' => count(array_unique(array_column($rows, 'lager'))) > 1, 'lager' => $line['set'][1] ?? $rows[0]['lager'], 'rows' => $rows];
        }
        $flags = json_encode(['intake' => ['tags' => [], 'total_diff' => 0], 'allocation_v1' => ['lines' => $alloc]]);
        $linkSql = "INSERT INTO shopify_order_links (shop_id, order_gid, order_name, ordre_id, direction, state, ship_lager, flags) VALUES ($1, $2, $3, $4, 'web', 'invoiced', $5, $6::jsonb) RETURNING id";
        $linkId = (int)$this->sql($linkSql, [$this->shopId(), $gid, '#' . $this->ordrenr, $ordreId, $shipLager, $flags])[0]['id'];
        $this->sql("INSERT INTO shopify_move_barriers (shop_id, vare_id, ordre_id, expires_at) SELECT $1, v, $2, now() + interval '1 day' FROM generate_series(1, $3) v",
            [$this->shopId(), $ordreId, $posnr]);
        $payload = json_encode(['link_id' => $linkId, 'ordre_id' => $ordreId, 'order_gid' => $gid]);
        $this->sql("INSERT INTO shopify_jobs (type, shop_id, dedupe_key, payload, priority) VALUES ('order.tags', $1, $2, $3::jsonb, 10)", [$this->shopId(), "hml:$gid", $payload]);
        return $ordreId;
    }

    private function writeback(string $gid): array
    {
        return json_decode($this->sql('SELECT flags FROM shopify_order_links WHERE order_gid = $1', [$gid])[0]['flags'], true)['writeback_v1'] ?? [];
    }

    /** @return list<string> */
    private function barriers(int $ordreId): array
    {
        return array_values(array_unique(array_column($this->sql('SELECT state FROM shopify_move_barriers WHERE ordre_id = $1', [$ordreId]), 'state')));
    }

    /** @return list<string> the mutations sent, in order */
    private static function mutations(array $run): array
    {
        return array_values(array_filter(array_merge([], ...array_column($run['calls'], 'roots')), fn(string $r): bool => $r !== 'order'));
    }

    private function retryNow(): void
    {
        $this->sql("UPDATE shopify_jobs SET next_run_at = now() WHERE state = 'queued'");
    }

    /** K17: 2 of 3 from Mårum, 1 from Hillerød; Shopify put all 3 on Lager 1. */
    public function testALineDrawnFromAnotherLagerIsMovedThereBeforeSaldiBehandletAndTheBarrierIsReleased(): void
    {
        $order = $this->steps(['web_order', [['STOL-X', 3, [0 => 5, 1 => 5]]]])['results'][0];
        $line = $order['lines']['STOL-X'];
        $ordreId = $this->invoiced($order['order'], [['gid' => $line, 'rows' => [['STOL-X', 91, 2], ['STOL-X', 92, 1]]]]);

        $run = $this->steps(['pass']);

        $this->assertSame([['order.tags', 'done'], ['order.move', 'done'], ['order.references', 'done'], ['order.note', 'done'], ['order.processed', 'done']], $run['results'][0]);
        $this->assertSame(['fulfillmentOrderMove', 'metafieldsSet', 'tagsAdd'], self::mutations($run), 'the move, then the numbers and saldi-behandlet');
        $this->assertSame([[0, $line, 2], [1, $line, 1]], $run['orders'][$order['order']]['open'], 'one card per location');
        $this->assertSame(['released'], $this->barriers($ordreId), 'released at once, not held until expiry');
        $this->assertSame('done', $this->writeback($order['order'])['order.move']);
    }

    public function testAnOrderWhereShopifyAlreadyHasItMovesNothingAndReleasesTheBarrierAtOnce(): void
    {
        $order = $this->steps(['web_order', [['STOL-X', 2, [0 => 5]]]])['results'][0];
        $ordreId = $this->invoiced($order['order'], [['gid' => $order['lines']['STOL-X'], 'rows' => [['STOL-X', 91, 2]]]]);

        $run = $this->steps(['pass']);

        $this->assertSame(['metafieldsSet', 'tagsAdd'], self::mutations($run), 'no fulfillmentOrderMove');
        $this->assertSame(['released'], $this->barriers($ordreId));
        $this->assertSame('done', $this->writeback($order['order'])['order.move']);
    }

    /** fulfillmentOrderMove is never resent blind: the fresh read finds the move done. */
    public function testALostMoveAnswerIsReadNotSentAgain(): void
    {
        $order = $this->steps(['web_order', [['STOL-X', 1, [0 => 5, 1 => 5]]]])['results'][0];
        $line = $order['lines']['STOL-X'];
        $ordreId = $this->invoiced($order['order'], [['gid' => $line, 'rows' => [['STOL-X', 92, 1]]]]);

        $run = $this->steps(['fail', 'fulfillmentOrderMove', 'timeout_after_apply'], ['pass']);

        $this->assertSame(['fulfillmentOrderMove', 'metafieldsSet', 'tagsAdd'], self::mutations($run), 'one move only');
        $this->assertSame([[1, $line, 1]], $run['orders'][$order['order']]['open']);
        $this->assertSame(['released'], $this->barriers($ordreId));
    }

    /** A partial move makes a new fulfillment order; the next move is planned from a fresh read, not from the ids before it. */
    public function testAMoveThatMakesANewFulfillmentOrderIsFollowedByTheNextFromAFreshRead(): void
    {
        $order = $this->steps(['web_order', [['STOL-X', 3, [0 => 5, 1 => 5, 2 => 5]], ['BORD-Y', 1, [0 => 5, 1 => 5, 2 => 5]]]])['results'][0];
        [$stol, $bord] = [$order['lines']['STOL-X'], $order['lines']['BORD-Y']];
        $this->invoiced($order['order'], [
            ['gid' => $stol, 'rows' => [['STOL-X', 91, 1], ['STOL-X', 92, 1], ['STOL-X', 93, 1]]],
            ['gid' => $bord, 'rows' => [['BORD-Y', 92, 1]]],
        ]);

        $run = $this->steps(['pass']);

        $this->assertSame(['fulfillmentOrderMove', 'fulfillmentOrderMove', 'metafieldsSet', 'tagsAdd'], self::mutations($run));
        $expected = [[0, $stol, 1], [1, $bord, 1], [1, $stol, 1], [2, $stol, 1]];
        sort($expected);
        $this->assertSame($expected, $run['orders'][$order['order']]['open']);
    }

    /** A destination that does not stock the item refuses; the third failed attempt abandons the move. */
    public function testARefusedMoveIsAbandonedOnTheThirdAttemptWithAnAlarmAndTheStockPushGoesAhead(): void
    {
        $order = $this->steps(['web_order', [['STOL-X', 1, [0 => 5, 1 => 5]]]])['results'][0];
        $ordreId = $this->invoiced($order['order'], [['gid' => $order['lines']['STOL-X'], 'rows' => [['STOL-X', 92, 1]]]]);
        $this->steps(['unstock', 'STOL-X', 1]);

        $first = $this->steps(['pass']);
        $held = $this->barriers($ordreId);
        $this->retryNow();
        $second = $this->steps(['pass']);
        $this->retryNow();
        $third = $this->steps(['pass']);

        $this->assertContains(['order.move', 'failed'], $first['results'][0]);
        $this->assertContains(['order.processed', 'yielded'], $first['results'][0], 'saldi-behandlet waits for the move');
        $this->assertSame(['open'], $held, 'held while the move is retried');
        $this->assertContains(['order.move', 'failed'], $second['results'][0]);
        $this->assertSame([['order.move', 'done'], ['order.processed', 'done']], $third['results'][0]);
        $this->assertSame(['abandoned'], $this->barriers($ordreId));
        $this->assertSame('abandoned', $this->writeback($order['order'])['order.move']);
        $this->assertSame(['saldi-behandlet'], $third['orders'][$order['order']]['tags']);
        $alarm = $this->sql("SELECT ref, message FROM shopify_errors WHERE kind = 'order.move' AND resolved_at IS NULL");
        $this->assertSame((string)$ordreId, $alarm[0]['ref']);
        $this->assertStringContainsString("doesn't stock", $alarm[0]['message']);
    }

    /** Once the barrier has expired its stock is published again: the move is abandoned before anything is sent. */
    public function testAMoveWhoseBarrierHasExpiredIsAbandonedWithoutMoving(): void
    {
        $order = $this->steps(['web_order', [['STOL-X', 1, [0 => 5, 1 => 5]]]])['results'][0];
        $line = $order['lines']['STOL-X'];
        $ordreId = $this->invoiced($order['order'], [['gid' => $line, 'rows' => [['STOL-X', 92, 1]]]]);
        $this->sql("UPDATE shopify_move_barriers SET expires_at = now() - interval '1 day'");

        $run = $this->steps(['pass']);

        $this->assertContains(['order.move', 'done'], $run['results'][0]);
        $this->assertSame(['metafieldsSet', 'tagsAdd'], self::mutations($run), 'no fulfillmentOrderMove');
        $this->assertSame([[0, $line, 1]], $run['orders'][$order['order']]['open']);
        $this->assertSame(['abandoned'], $this->barriers($ordreId));
        $this->assertSame('abandoned', $this->writeback($order['order'])['order.move']);
    }

    /** The orders whose move never ran (the write-back stopped earlier) still get their stock pushed. */
    public function testTheSweepAbandonsAnExpiredBarrierWhoseMoveNeverRanWithAnAlarm(): void
    {
        $order = $this->steps(['web_order', [['STOL-X', 1, [0 => 5]]]])['results'][0];
        $ordreId = $this->invoiced($order['order'], [['gid' => $order['lines']['STOL-X'], 'rows' => [['STOL-X', 91, 1]]]]);
        $this->sql("UPDATE shopify_jobs SET state = 'dead'");
        $this->sql("UPDATE shopify_move_barriers SET expires_at = now() - interval '1 day'");
        $this->sql("INSERT INTO shopify_jobs (type, shop_id, dedupe_key, payload, priority) VALUES ('barrier.sweep', $1, 'hml:sweep', '{}'::jsonb, 15)", [$this->shopId()]);

        $run = $this->steps(['pass']);

        $this->assertSame([['barrier.sweep', 'done']], $run['results'][0]);
        $this->assertSame(['abandoned'], $this->barriers($ordreId));
        $this->assertSame([(string)$ordreId], array_column($this->sql("SELECT ref FROM shopify_errors WHERE kind = 'order.move'"), 'ref'));
    }

    /** A cancelled order has no move to wait for: its barriers go, but nobody is alarmed. */
    public function testTheSweepRaisesNoAlarmForACancelledOrder(): void
    {
        $ordreId = $this->invoiced('gid://shopify/Order/9', [['gid' => 'gid://shopify/LineItem/9', 'rows' => [['STOL-X', 91, 1]]]]);
        $this->sql("UPDATE shopify_jobs SET state = 'dead'");
        $this->sql("UPDATE shopify_order_links SET cancelled_at = now()");
        $this->sql("UPDATE shopify_move_barriers SET expires_at = now() - interval '1 day'");
        $this->sql("INSERT INTO shopify_jobs (type, shop_id, dedupe_key, payload, priority) VALUES ('barrier.sweep', $1, 'hml:sweep', '{}'::jsonb, 15)", [$this->shopId()]);

        $this->steps(['pass']);

        $this->assertSame(['abandoned'], $this->barriers($ordreId));
        $this->assertSame([], $this->sql("SELECT ref FROM shopify_errors WHERE kind = 'order.move'"));
    }

    /** K17 and K55: three held orders give exactly the lines drawn from another lager than the order ships from. */
    public function testTheMoveListShowsExactlyTheCrossLocationLinesOfThreeOrders(): void
    {
        $this->invoiced('gid://shopify/Order/1', [['gid' => 'gid://shopify/LineItem/1', 'rows' => [['STOL-X', 91, 2]]]]);
        $plain = $this->invoiced('gid://shopify/Order/2', [['gid' => 'gid://shopify/LineItem/2', 'rows' => [['STOL-X', 91, 2], ['STOL-X', 92, 1]]]]);
        // K55's worked example: a split set whose 2 Y come from lager 92
        $set = $this->invoiced('gid://shopify/Order/3', [['gid' => 'gid://shopify/LineItem/3', 'rows' => [['X', 91, 2], ['Y', 92, 2]], 'set' => ['SAET-XY', 91]]]);
        $this->invoiced('gid://shopify/Order/4', [['gid' => 'gid://shopify/LineItem/4', 'rows' => [['STOL-X', 93, 1]]]], 91, '2026-11-12');
        $this->invoiced('gid://shopify/Order/5', [['gid' => 'gid://shopify/LineItem/5', 'rows' => [['STOL-X', 93, 1]]]], null);
        $this->sql("UPDATE shopify_order_links SET cancelled_at = now() WHERE order_gid = 'gid://shopify/Order/1'");

        $run = $this->steps(['movelist', self::DAY, null], ['movelist', self::DAY, 92]);

        $row = fn(int $ordreId, ?string $saet, string $varenr, float $antal): array => ['ordre_id' => $ordreId, 'saet' => $saet, 'varenr' => $varenr, 'antal' => $antal,
            'fra_lager' => 92, 'fra_navn' => 'Hillerød', 'til_lager' => 91, 'til_navn' => 'Mårum'];
        $keep = fn(array $r): array => array_intersect_key($r, array_flip(['ordre_id', 'saet', 'varenr', 'antal', 'fra_lager', 'fra_navn', 'til_lager', 'til_navn']));
        $this->assertEquals([$row($plain, null, 'STOL-X', 1), $row($set, 'SAET-XY', 'Y', 2)], array_map($keep, $run['results'][0]));
        $this->assertSame([], $run['results'][1], 'no order ships from lager 92');
    }
}
