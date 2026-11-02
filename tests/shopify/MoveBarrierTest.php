<?php
// tests/shopify/MoveBarrierTest.php
//
// Move barriers (SD-755, WP06, doc/shopify/interface-sheet.md) on a throwaway tenant: open defers
// an item in that shop only, is idempotent per (shop, order, item) and commits or rolls back with the
// caller's transaction; release and expire stop the deferring and mark the items dirty; expiry after
// 30 minutes and on the order id WP10 passes after the 3rd failed move. Skips when ShopifyEnv is
// not configured.
//
// History:
// 20261102 CL/SZ SD-755: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class MoveBarrierTest extends TestCase
{
    private const SHOP = 1;
    private const OTHER_SHOP = 2;
    private const ORDER = 50;
    private const ITEM = 10;
    private const COMPONENT = 11;

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
            self::$db = ShopifyEnv::testDb('_mb');
            ShopifyEnv::cloneTenant(self::$db);
            ShopifyEnv::registerTenant(self::$db);
            $run = ShopifyEnv::runSchema(self::$db, 'ensure');
            if ($run['exit'] !== 0) {
                throw new RuntimeException('schema ensure failed: ' . $run['stderr']);
            }
            $conn = ShopifyEnv::connect(self::$db);
            ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
            ShopifyEnv::rows($conn, "INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
            pg_close($conn);
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
        foreach (['shopify_move_barriers', 'shopify_stock_dirty', 'shopify_shops', 'styklister'] as $table) {
            ShopifyEnv::rows($this->conn, "DELETE FROM $table");
        }
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            pg_close($this->conn);
        }
    }

    /**
     * @param list<list<mixed>> $calls
     * @return list<mixed> the result of each call
     */
    private function calls(array $calls): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_barriers.php', [self::$db, json_encode($calls)]));
        $this->assertIsArray($run['result'], "runner gave no JSON (exit {$run['exit']}): {$run['stdout']} {$run['stderr']}");
        return array_map(function (array $out) {
            $this->assertArrayNotHasKey('error', $out, json_encode($out['error'] ?? null));
            return $out['result'];
        }, $run['result']);
    }

    /** @return list<array{vare_id: string, state: string}> */
    private function barriers(): array
    {
        return ShopifyEnv::rows($this->conn, 'SELECT vare_id, state FROM shopify_move_barriers ORDER BY vare_id, id');
    }

    /** @return list<int> dirty items, sorted */
    private function dirty(): array
    {
        return array_map('intval', array_column(ShopifyEnv::rows($this->conn, 'SELECT DISTINCT vare_id FROM shopify_stock_dirty ORDER BY vare_id'), 'vare_id'));
    }

    private function captureOn(): void
    {
        ShopifyEnv::rows($this->conn, "INSERT INTO shopify_shops (shop_key, domain, stock_capture_enabled) VALUES ('hml', 'hml.myshopify.com', true)");
    }

    /** Moves the order's barriers past their expiry. */
    private function pastExpiry(int $ordreId = self::ORDER): void
    {
        ShopifyEnv::rows($this->conn, "UPDATE shopify_move_barriers SET expires_at = now() - interval '1 second' WHERE ordre_id = $1", [$ordreId]);
    }

    public function testAnOpenBarrierDefersTheItemInThatShopOnly(): void
    {
        $out = $this->calls([
            ['open', self::SHOP, self::ORDER, [self::ITEM, self::COMPONENT]],
            ['deferred', self::SHOP, [self::COMPONENT, self::ITEM, 99]],
            ['deferred', self::OTHER_SHOP, [self::ITEM]],
        ]);

        $this->assertSame(2, $out[0]);
        $this->assertSame([self::ITEM, self::COMPONENT], $out[1]);
        $this->assertSame([], $out[2]);
    }

    public function testABarrierExpiresThirtyMinutesAfterItOpens(): void
    {
        $this->calls([['open', self::SHOP, self::ORDER, [self::ITEM]]]);

        $row = ShopifyEnv::one($this->conn, "SELECT extract(epoch FROM expires_at - created_at)::int AS seconds, state, shop_id, ordre_id FROM shopify_move_barriers");

        $this->assertSame(['seconds' => '1800', 'state' => 'open', 'shop_id' => (string)self::SHOP, 'ordre_id' => (string)self::ORDER], $row);
    }

    public function testOpeningAgainAfterACrashAddsNothingAndDoesNotThrow(): void
    {
        $out = $this->calls([
            ['open', self::SHOP, self::ORDER, [self::ITEM, self::ITEM]],
            ['open', self::SHOP, self::ORDER, [self::ITEM, self::COMPONENT]],
            ['open', self::SHOP, self::ORDER, [self::ITEM, self::COMPONENT]],
        ]);

        $this->assertSame([1, 1, 0], $out);
        $this->assertCount(2, $this->barriers());
    }

    public function testOpeningAgainDoesNotReviveAClosedBarrier(): void
    {
        $out = $this->calls([
            ['open', self::SHOP, self::ORDER, [self::ITEM]],
            ['release', self::SHOP, self::ORDER],
            ['open', self::SHOP, self::ORDER, [self::ITEM]],
            ['deferred', self::SHOP, [self::ITEM]],
        ]);

        $this->assertSame(0, $out[2]);
        $this->assertSame([], $out[3]);
    }

    public function testAnotherOrderOrShopGetsItsOwnBarrierForTheSameItem(): void
    {
        $out = $this->calls([
            ['open', self::SHOP, self::ORDER, [self::ITEM]],
            ['open', self::SHOP, 51, [self::ITEM]],
            ['open', self::OTHER_SHOP, self::ORDER, [self::ITEM]],
            ['release', self::SHOP, self::ORDER],
            ['deferred', self::SHOP, [self::ITEM]],
        ]);

        $this->assertSame([1, 1, 1, 1], array_slice($out, 0, 4));
        $this->assertSame([self::ITEM], $out[4], 'order 51 still holds the item back');
    }

    public function testOpenRollsBackWithTheCallersTransaction(): void
    {
        $out = $this->calls([['open_rollback', self::SHOP, self::ORDER, [self::ITEM]], ['deferred', self::SHOP, [self::ITEM]]]);

        $this->assertSame('order creation failed', $out[0]);
        $this->assertSame([], $out[1]);
        $this->assertSame([], $this->barriers(), 'open neither committed nor began a transaction of its own');
    }

    public function testNoItemsOpenNothing(): void
    {
        $this->assertSame([0, []], $this->calls([['open', self::SHOP, self::ORDER, []], ['deferred', self::SHOP, []]]));
    }

    public function testAReleasedItemIsNoLongerDeferredAndTheNextPushPublishesIt(): void
    {
        $this->captureOn();
        $out = $this->calls([
            ['open', self::SHOP, self::ORDER, [self::ITEM, self::COMPONENT]],
            ['release', self::SHOP, self::ORDER],
            ['deferred', self::SHOP, [self::ITEM, self::COMPONENT]],
            ['release', self::SHOP, self::ORDER],
        ]);

        $this->assertSame(2, $out[1]);
        $this->assertSame([], $out[2]);
        $this->assertSame(0, $out[3], 'nothing left to release');
        $this->assertSame([self::ITEM, self::COMPONENT], $this->dirty());
        $this->assertSame(['released', 'released'], array_column($this->barriers(), 'state'));
    }

    public function testNothingIsMarkedDirtyUntilAShopCapturesStock(): void
    {
        $this->calls([['open', self::SHOP, self::ORDER, [self::ITEM]], ['release', self::SHOP, self::ORDER]]);

        $this->assertSame([], $this->dirty());
    }

    public function testABarrierPastItsExpiryNoLongerDefersBeforeTheSweepRuns(): void
    {
        $this->calls([['open', self::SHOP, self::ORDER, [self::ITEM]]]);
        $this->pastExpiry();

        $this->assertSame([[]], $this->calls([['deferred', self::SHOP, [self::ITEM]]]));
        $this->assertSame('open', $this->barriers()[0]['state']);
    }

    public function testTheSweepAbandonsOnlyBarriersPastExpiryAndReturnsThemForTheAlarm(): void
    {
        $this->captureOn();
        $this->calls([
            ['open', self::SHOP, self::ORDER, [self::COMPONENT, self::ITEM]],
            ['open', self::SHOP, 51, [99]],
            ['open', self::OTHER_SHOP, self::ORDER, [98]],
        ]);
        ShopifyEnv::rows($this->conn, "UPDATE shopify_move_barriers SET expires_at = now() - interval '1 second' WHERE ordre_id = $1 AND shop_id = $2", [self::ORDER, self::SHOP]);
        ShopifyEnv::rows($this->conn, "UPDATE shopify_move_barriers SET expires_at = now() - interval '1 second' WHERE shop_id = $1", [self::OTHER_SHOP]);

        $out = $this->calls([['expire', self::SHOP], ['expire', self::SHOP], ['deferred', self::SHOP, [self::ITEM, 99]]]);

        $this->assertSame([['shop_id' => self::SHOP, 'ordre_id' => self::ORDER, 'vare_ids' => [self::ITEM, self::COMPONENT]]], $out[0]);
        $this->assertSame([], $out[1], 'a second sweep finds nothing');
        $this->assertSame([99], $out[2], 'the unexpired barrier still defers');
        $this->assertSame([self::ITEM, self::COMPONENT], $this->dirty(), 'the other shop\'s barrier is left for its own sweep');
    }

    public function testTheSweepReturnsOneEntryPerOrderAndEachBarrierOnlyOnce(): void
    {
        $this->calls([
            ['open', self::SHOP, 51, [99]],
            ['open', self::SHOP, self::ORDER, [self::ITEM, self::COMPONENT]],
        ]);
        ShopifyEnv::rows($this->conn, "UPDATE shopify_move_barriers SET expires_at = now() - interval '1 second'");

        $out = $this->calls([['expire', self::SHOP]]);
        $next = $this->calls([['expire', self::SHOP]]);

        $this->assertSame([
            ['shop_id' => self::SHOP, 'ordre_id' => self::ORDER, 'vare_ids' => [self::ITEM, self::COMPONENT]],
            ['shop_id' => self::SHOP, 'ordre_id' => 51, 'vare_ids' => [99]],
        ], $out[0]);
        $this->assertSame([[]], $next, 'a resolved alarm is never raised again by a later sweep');
    }

    public function testWp10AbandonsAnOrdersBarriersAfterTheThirdFailedMove(): void
    {
        $this->captureOn();
        $out = $this->calls([
            ['open', self::SHOP, self::ORDER, [self::ITEM]],
            ['open', self::SHOP, 51, [99]],
            ['expire', self::SHOP, self::ORDER],
            ['deferred', self::SHOP, [self::ITEM, 99]],
            ['release', self::SHOP, self::ORDER],
        ]);

        $this->assertSame([['shop_id' => self::SHOP, 'ordre_id' => self::ORDER, 'vare_ids' => [self::ITEM]]], $out[2], 'before its 30 minutes are up');
        $this->assertSame([99], $out[3]);
        $this->assertSame(0, $out[4], 'an abandoned barrier stays abandoned');
        $this->assertSame([self::ITEM], $this->dirty());
        $this->assertSame(['abandoned', 'open'], array_column($this->barriers(), 'state'));
    }

    public function testClosingABarrierAlsoMarksTheSetsAboveTheItem(): void
    {
        $this->captureOn();
        ShopifyEnv::rows($this->conn, 'INSERT INTO styklister (vare_id, indgaar_i, antal) VALUES ($1, 70, 1), (70, 71, 1)', [self::COMPONENT]);
        ShopifyEnv::rows($this->conn, 'DELETE FROM shopify_stock_dirty');

        $this->calls([['open', self::SHOP, self::ORDER, [self::COMPONENT]], ['release', self::SHOP, self::ORDER]]);

        $this->assertSame([self::COMPONENT, 70, 71], $this->dirty());
    }

    public function testASecondAttemptAtOpeningWaitsForTheFirstToCommit(): void
    {
        ShopifyEnv::rows($this->conn, 'BEGIN');
        ShopifyEnv::rows($this->conn, 'SELECT pg_advisory_xact_lock(hashtext($1))', ['shopify_barrier:' . self::SHOP . ':' . self::ORDER]);
        ShopifyEnv::rows($this->conn, 'INSERT INTO shopify_move_barriers (shop_id, vare_id, ordre_id, expires_at) VALUES ($1, $2, $3, now())', [self::SHOP, self::ITEM, self::ORDER]);

        $second = ShopifyEnv::startChild('run_barriers.php', [self::$db, json_encode([['open', self::SHOP, self::ORDER, [self::ITEM]]])]);
        usleep(500000);
        ShopifyEnv::rows($this->conn, 'COMMIT');
        $run = ShopifyEnv::waitChild($second);

        $this->assertSame([['result' => 0]], $run['result'], 'the second attempt saw the committed row: ' . $run['stderr']);
        $this->assertCount(1, $this->barriers());
    }

    public function testAMalformedShopOrOrderIdIsRefused(): void
    {
        foreach ([[0, self::ORDER], [self::SHOP, 0]] as [$shop, $order]) {
            $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_barriers.php', [self::$db, json_encode([['open', $shop, $order, [self::ITEM]]])]));

            $this->assertSame('InvalidArgumentException', $run['result'][0]['error']['class']);
        }
        $this->assertSame([], $this->barriers());
    }

    public function testAMalformedItemIdIsRefused(): void
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_barriers.php', [self::$db, json_encode([['open', self::SHOP, self::ORDER, [0]]])]));

        $this->assertSame('InvalidArgumentException', $run['result'][0]['error']['class']);
        $this->assertSame([], $this->barriers());
    }
}
