<?php
// tests/shopify/OrderMoveTest.php
//
// shopify_plan_moves() (SD-760, WP10-2), pure: which fulfillment-order lines move where, from the
// import's allocation_v1 and the fulfillment orders Shopify has. No database needed.
//
// History:
// 20261113 CL/MJ SD-760: created.
// 20261114 CL/MJ SD-760 (CI): orderMove.php loads logging.php, so it is required when the class starts.
//                Collecting the files no longer loads it, and ClientRetryTest keeps a client without a redactor.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/FakeShopify.php';

final class OrderMoveTest extends TestCase
{
    private const L1 = 'gid://shopify/Location/1';
    private const L2 = 'gid://shopify/Location/2';
    private const L3 = 'gid://shopify/Location/3';
    /** lager => location: 1 and 2 sellable; 3 (display) and 4 (ingen) are left out, as shopify_move_locations() leaves them out */
    private const LOCATIONS = [1 => self::L1, 2 => self::L2];

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/shopify/services/orderMove.php';
    }

    /** @param array<string, int> $lines line gid => remaining */
    private static function fo(string $id, string $location, array $lines, bool $movable = true, string $status = 'OPEN'): array
    {
        $nodes = [];
        foreach ($lines as $gid => $qty) {
            $nodes[] = ['id' => "$id-li-" . count($nodes), 'remainingQuantity' => $qty, 'lineItem' => ['id' => $gid]];
        }
        return ['id' => $id, 'status' => $status, 'assignedLocation' => ['location' => ['id' => $location]],
            'supportedActions' => $movable ? [['action' => 'CREATE_FULFILLMENT'], ['action' => 'MOVE']] : [['action' => 'CREATE_FULFILLMENT']],
            'lineItems' => ['nodes' => $nodes]];
    }

    /** @param array<int, int> $milliByLager */
    private static function line(string $gid, array $milliByLager): array
    {
        $rows = [];
        foreach ($milliByLager as $lager => $milli) {
            $rows[] = ['ordrelinje_id' => 500 + count($rows), 'vare_id' => 12, 'lager' => $lager, 'quantity_milli' => $milli];
        }
        return ['line_gid' => $gid, 'vare_id' => 12, 'quantity' => intdiv(array_sum($milliByLager), 1000), 'set' => false, 'split' => count($rows) > 1,
            'lager' => array_key_first($milliByLager), 'rows' => $rows];
    }

    public function testNoMoveWhenTheLagerIsWhereShopifyAssignedTheLine(): void
    {
        $plan = shopify_plan_moves(['lines' => [self::line('L11', [1 => 3000])]], [self::fo('F1', self::L1, ['L11' => 3])], self::LOCATIONS);
        $this->assertSame(['moves' => [], 'unresolved' => []], $plan);
    }

    public function testAPartOfALineDrawnFromAnotherLagerMovesWithItsQuantity(): void
    {
        $plan = shopify_plan_moves(['lines' => [self::line('L11', [1 => 2000, 2 => 1000])]], [self::fo('F1', self::L1, ['L11' => 3])], self::LOCATIONS);
        $this->assertSame([['fulfillment_order' => 'F1', 'to' => self::L2, 'lines' => [['id' => 'F1-li-0', 'quantity' => 1]]]], $plan['moves']);
    }

    public function testLinesOfOneFulfillmentOrderGoingToOneLocationAreOneMove(): void
    {
        $plan = shopify_plan_moves(['lines' => [self::line('L11', [2 => 1000]), self::line('L12', [2 => 2000])]],
            [self::fo('F1', self::L1, ['L11' => 1, 'L12' => 2])], self::LOCATIONS);
        $this->assertSame([['fulfillment_order' => 'F1', 'to' => self::L2, 'lines' => [['id' => 'F1-li-0', 'quantity' => 1], ['id' => 'F1-li-1', 'quantity' => 2]]]], $plan['moves']);
    }

    /** K55: a split set stays one line in Shopify, on the set's own lager, whatever lagre its components came from. */
    public function testASplitSetMovesWholeToTheSetsLager(): void
    {
        $set = ['line_gid' => 'L12', 'vare_id' => 40, 'quantity' => 2, 'set' => true, 'split' => true, 'lager' => 2, 'rows' => [
            ['ordrelinje_id' => 503, 'vare_id' => 41, 'lager' => 1, 'quantity_milli' => 2000],
            ['ordrelinje_id' => 504, 'vare_id' => 42, 'lager' => 2, 'quantity_milli' => 2000],
            ['ordrelinje_id' => 505, 'vare_id' => 40, 'lager' => 2, 'quantity_milli' => 2000, 'set_line' => true]]];
        $plan = shopify_plan_moves(['lines' => [$set]], [self::fo('F1', self::L1, ['L12' => 2])], self::LOCATIONS);
        $this->assertSame([['fulfillment_order' => 'F1', 'to' => self::L2, 'lines' => [['id' => 'F1-li-0', 'quantity' => 2]]]], $plan['moves']);
    }

    /** A display, ingen or unmapped lager has no location in the map: the line stays, and the move list carries it. */
    public function testDisplayIngenAndUnmappedDestinationsAreNeverPlanned(): void
    {
        foreach ([3, 4, 9] as $lager) {
            $plan = shopify_plan_moves(['lines' => [self::line('L11', [1 => 1000, $lager => 1000])]], [self::fo('F1', self::L1, ['L11' => 2])], self::LOCATIONS);
            $this->assertSame(['moves' => [], 'unresolved' => []], $plan, "lager $lager");
        }
    }

    /** A sellable lager the shop has no location for can't be moved to: unresolved, so it is retried and alarmed, not done. */
    public function testASellableLagerWithoutALocationIsUnresolved(): void
    {
        $plan = shopify_plan_moves(['lines' => [self::line('L11', [1 => 1000, 5 => 1000])]], [self::fo('F1', self::L1, ['L11' => 2])], self::LOCATIONS + [5 => '']);
        $this->assertSame(['moves' => [], 'unresolved' => ['L11']], $plan);
    }

    /** A refund or an edit since the import: Shopify's open quantity is not what Saldi wrote, so nothing is guessed. */
    public function testALineWhoseOpenQuantityDiffersIsLeftAlone(): void
    {
        $plan = shopify_plan_moves(['lines' => [self::line('L11', [1 => 2000, 2 => 1000])]], [self::fo('F1', self::L1, ['L11' => 2])], self::LOCATIONS);
        $this->assertSame([], $plan['moves']);
        $plan = shopify_plan_moves(['lines' => [self::line('L11', [2 => 1500])]], [self::fo('F1', self::L1, ['L11' => 1])], [2 => self::L2, 1 => self::L1]);
        $this->assertSame([], $plan['moves'], 'part of an item is not moved');
    }

    /** After a partial move Shopify has the line on two fulfillment orders; only what is still in the wrong place moves. */
    public function testALineAlreadyPartlyMovedOnlyMovesTheRest(): void
    {
        $alloc = ['lines' => [self::line('L11', [1 => 1000, 2 => 2000])]];
        $plan = shopify_plan_moves($alloc, [self::fo('F1', self::L1, ['L11' => 2]), self::fo('F9', self::L2, ['L11' => 1])], self::LOCATIONS);
        $this->assertSame([['fulfillment_order' => 'F1', 'to' => self::L2, 'lines' => [['id' => 'F1-li-0', 'quantity' => 1]]]], $plan['moves']);
        $done = shopify_plan_moves($alloc, [self::fo('F1', self::L1, ['L11' => 1]), self::fo('F9', self::L2, ['L11' => 2])], self::LOCATIONS);
        $this->assertSame([], $done['moves'], 'no move once it is all in place');
    }

    public function testAClosedFulfillmentOrderIsNotOpenAndAnUnmovableOneIsUnresolved(): void
    {
        $alloc = ['lines' => [self::line('L11', [2 => 1000])]];
        $closed = shopify_plan_moves($alloc, [self::fo('F1', self::L1, ['L11' => 1], true, 'CLOSED')], self::LOCATIONS);
        $this->assertSame(['moves' => [], 'unresolved' => []], $closed);
        $stuck = shopify_plan_moves($alloc, [self::fo('F1', self::L1, ['L11' => 1], false)], self::LOCATIONS);
        $this->assertSame(['moves' => [], 'unresolved' => ['L11']], $stuck);
    }

    /** A fulfillment order put on hold still holds the line; it can't be moved, so the move fails and is abandoned in the end. */
    public function testALineOnAFulfillmentOrderOnHoldIsUnresolvedNotDone(): void
    {
        $plan = shopify_plan_moves(['lines' => [self::line('L11', [2 => 1000])]], [self::fo('F1', self::L1, ['L11' => 1], false, 'ON_HOLD')], self::LOCATIONS);
        $this->assertSame(['moves' => [], 'unresolved' => ['L11']], $plan);
    }

    public function testASetWithoutItsLagerTakesTheLagerOfItsSetLine(): void
    {
        $set = ['line_gid' => 'L12', 'quantity' => 1, 'set' => true, 'rows' => [
            ['ordrelinje_id' => 503, 'vare_id' => 41, 'lager' => 1, 'quantity_milli' => 1000],
            ['ordrelinje_id' => 505, 'vare_id' => 40, 'lager' => 2, 'quantity_milli' => 1000, 'set_line' => true]]];
        $plan = shopify_plan_moves(['lines' => [$set]], [self::fo('F1', self::L1, ['L12' => 1])], self::LOCATIONS);
        $this->assertSame(self::L2, $plan['moves'][0]['to']);
    }

    /** Out of time is not a failed attempt: the job yields before it reads or sends anything. */
    public function testRunMovesYieldsWhenThePassHasNoTimeLeft(): void
    {
        $result = shopify_run_moves(['shop_key' => 'hml'], 'gid://shopify/Order/1', ['lines' => []], [], ['deadline' => microtime(true) + 1]);
        $this->assertSame(['status' => 'yield', 'moved' => 0, 'errors' => []], $result);
    }

    /** A hold that ends before a move sent now could be answered: nothing is sent, and the job abandons at once. */
    public function testRunMovesSendsNoMoveOnceTheHoldWouldEndFirst(): void
    {
        $fake = new FakeShopify([FakeShopify::ok(['order' => ['fulfillmentOrders' => ['pageInfo' => ['hasNextPage' => false],
            'nodes' => [self::fo('F1', self::L1, ['L11' => 1])]]]])]);
        $result = shopify_run_moves(FakeShopify::shop(), 'gid://shopify/Order/1', ['lines' => [self::line('L11', [2 => 1000])]], self::LOCATIONS,
            ['graphql' => $fake->opts(), 'deadline' => microtime(true) + 300, 'held' => fn(): bool => false]);
        $this->assertSame('expired', $result['status']);
        $this->assertCount(1, $fake->calls, 'the read only, no fulfillmentOrderMove');
    }

    /** Before a move is sent its late window is recorded; an answered move clears it, a lost answer ends the run and keeps it. */
    public function testRunMovesRecordsTheLateWindowFirstAndStopsAtALostAnswer(): void
    {
        $read = FakeShopify::ok(['order' => ['fulfillmentOrders' => ['pageInfo' => ['hasNextPage' => false],
            'nodes' => [self::fo('F1', self::L1, ['L11' => 2])]]]]);
        $fake = new FakeShopify([$read, FakeShopify::ok(['fulfillmentOrderMove' => ['userErrors' => []]]), $read, FakeShopify::timeout()]);
        $deadline = microtime(true) + 300;
        $recorded = [];
        $pending = function (float $until) use (&$recorded, $fake): void {
            $recorded[] = [count($fake->calls), $until];
        };
        $result = shopify_run_moves(FakeShopify::shop(), 'gid://shopify/Order/1', ['lines' => [self::line('L11', [2 => 2000])]], self::LOCATIONS,
            ['graphql' => $fake->opts(), 'deadline' => $deadline, 'held' => fn(): bool => true, 'pending' => $pending]);
        $this->assertSame(['uncertain', 2], [$result['status'], $result['moved']]);
        $this->assertCount(4, $fake->calls, 'no read or move after the lost answer');
        $this->assertSame([[1, $deadline + SHOPIFY_MOVE_LATE_SECONDS], [2, 0.0], [3, $deadline + SHOPIFY_MOVE_LATE_SECONDS]], $recorded,
            'recorded before each move is sent, cleared only once it was answered');
    }

    public function testALineNotInTheAllocationIsNotTouched(): void
    {
        $plan = shopify_plan_moves(['lines' => [self::line('L11', [1 => 1000])]], [self::fo('F1', self::L3, ['L11' => 1, 'L99' => 4])], self::LOCATIONS + [3 => self::L3]);
        $this->assertSame([['fulfillment_order' => 'F1', 'to' => self::L1, 'lines' => [['id' => 'F1-li-0', 'quantity' => 1]]]], $plan['moves']);
    }
}
