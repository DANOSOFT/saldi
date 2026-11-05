<?php
// tests/characterization/lager/StockMovementCharacterizationTest.test.php
//
// Characterization tests for stock: what moves `varer.beholdning` and the
// per-warehouse `lagerstatus` rows, and what does not.
//
// Stock in Saldi is kept in two places that are updated by different code:
// the single running total on the item (`varer.beholdning`, written by
// linjeopdat) and a row per warehouse (`lagerstatus`, written by
// lagerstatus()). Whether either moves at all depends on the item GROUP:
// includes/ordrefunc.php:429 only touches stock when the group's box8 is
// 'on'. A service item in an untracked group can be sold forever without its
// balance changing - which is correct, and worth pinning so nobody "fixes" it.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class StockMovementCharacterizationTest extends CharacterizationTestCase
{
    /** @param list<int|string> $args */
    private function drive(string $scenario, array $args): array
    {
        return $this->runChildJson(
            'run_order_lifecycle.php',
            array_merge([$scenario], $args, [CharacterizationEnv::testDb()])
        );
    }

    private function stockOf(array $item): float
    {
        $r = self::one('SELECT beholdning FROM varer WHERE id = $1', [$item['id']]);
        return (float)$r['beholdning'];
    }

    /** @return list<array<string,string>> the per-warehouse rows for an item */
    private function warehouseRows(array $item): array
    {
        return self::rows(
            'SELECT lager, variant_id, beholdning FROM lagerstatus WHERE vare_id = $1 ORDER BY lager',
            [$item['id']]
        );
    }

    /**
     * The item balance and the per-warehouse rows are written by different
     * code; delivering must move both, consistently.
     */
    public function test_delivery_moves_both_the_item_balance_and_the_warehouse_row(): void
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['beholdning' => 50.0]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 12]]);

        $this->assertSame([], $this->warehouseRows($item), 'precondition: no warehouse rows yet');

        $out = $this->drive('deliver', [$order['id'], 12]);
        $this->assertSame('OK', $out['levering']);

        $this->assertEqualsWithDelta(38.0, $this->stockOf($item), 0.001, 'the item balance drops by twelve');

        $rows = $this->warehouseRows($item);
        $this->assertCount(1, $rows, 'one warehouse row is created for the delivering warehouse');
        $this->assertEqualsWithDelta(
            -12.0,
            (float)$rows[0]['beholdning'],
            0.001,
            'the warehouse row records the movement, starting from zero'
        );
    }

    /** Successive deliveries accumulate on the same warehouse row. */
    public function test_successive_deliveries_accumulate_on_one_warehouse_row(): void
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['beholdning' => 50.0]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 10]]);

        $this->drive('deliver', [$order['id'], 4]);
        $this->drive('deliver', [$order['id'], 3]);

        $rows = $this->warehouseRows($item);
        $this->assertCount(1, $rows, 'still one warehouse row');
        $this->assertEqualsWithDelta(-7.0, (float)$rows[0]['beholdning'], 0.001, 'both deliveries land on it');
        $this->assertEqualsWithDelta(43.0, $this->stockOf($item), 0.001, 'the item balance agrees');
    }

    /**
     * A service item - one in a group that is not stock-tracked - is sold
     * without any stock movement at all. Neither total changes.
     */
    public function test_an_untracked_item_does_not_move_stock(): void
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['stocked' => false, 'beholdning' => 50.0]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 10]]);

        $out = $this->drive('deliver', [$order['id'], 10]);

        $this->assertSame('OK', $out['levering'], 'the delivery still succeeds');
        $this->assertEqualsWithDelta(50.0, $this->stockOf($item), 0.001, 'the item balance is untouched');
        $this->assertSame([], $this->warehouseRows($item), 'and no warehouse row is created');
    }

    /**
     * Receive goods against a purchase order (kreditor/modtag.php).
     *
     * The arriving quantities are staged on the lines first, which is the
     * state kreditor/ordre.php leaves behind before handing over.
     */
    private function receive(int $orderId, float $qty): void
    {
        self::rows(
            'UPDATE ordrelinjer SET leveres = $1 WHERE ordre_id = $2 AND vare_id > 0',
            [$qty, $orderId]
        );
        self::runChild('run_modtag_page.php', [$orderId, CharacterizationEnv::SESSION_ID]);
    }

    /**
     * Receiving a purchase order puts goods INTO stock.
     *
     * Receiving is a different code path from delivering: kreditor/modtag.php
     * adds to `beholdning` and writes batch_kob, where levering() subtracts
     * and writes batch_salg. Driving a purchase order through levering()
     * would subtract - the sign is a property of the page, not of the order's
     * art.
     */
    public function test_receiving_a_purchase_order_increases_stock(): void
    {
        $creditor = self::$fx->creditor();
        $item = self::$fx->item(['beholdning' => 5.0]);
        $order = self::$fx->creditorOrder($creditor, [['item' => $item, 'antal' => 20, 'pris' => 60.00]]);

        $this->receive($order['id'], 20);

        $this->assertEqualsWithDelta(
            25.0,
            $this->stockOf($item),
            0.001,
            'twenty units were received on top of the five already there'
        );
    }

    /** A partial receipt only books in what arrived. */
    public function test_a_partial_receipt_only_books_in_what_arrived(): void
    {
        $creditor = self::$fx->creditor();
        $item = self::$fx->item(['beholdning' => 0.0]);
        $order = self::$fx->creditorOrder($creditor, [['item' => $item, 'antal' => 20, 'pris' => 60.00]]);

        $this->receive($order['id'], 7);

        $this->assertEqualsWithDelta(7.0, $this->stockOf($item), 0.001, 'seven of twenty arrived');
    }

    /** A receipt records a batch_kob row, the purchase-side counterpart of batch_salg. */
    public function test_a_receipt_records_a_purchase_batch(): void
    {
        $creditor = self::$fx->creditor();
        $item = self::$fx->item(['beholdning' => 0.0]);
        $order = self::$fx->creditorOrder($creditor, [['item' => $item, 'antal' => 12, 'pris' => 60.00]]);

        $this->receive($order['id'], 12);

        $batches = self::rows(
            'SELECT antal FROM batch_kob WHERE ordre_id = $1 AND vare_id = $2',
            [$order['id'], $item['id']]
        );
        $this->assertCount(1, $batches, 'one purchase batch for the receipt');
        $this->assertEqualsWithDelta(12.0, (float)$batches[0]['antal'], 0.001, 'it carries the received quantity');
    }

    /**
     * Buying then selling the same item nets out, with the two opposite
     * movements landing on the same running balance.
     */
    public function test_a_purchase_and_a_sale_net_out(): void
    {
        $creditor = self::$fx->creditor();
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['beholdning' => 0.0]);

        $purchase = self::$fx->creditorOrder($creditor, [['item' => $item, 'antal' => 30, 'pris' => 60.00]]);
        $this->receive($purchase['id'], 30);
        $this->assertEqualsWithDelta(30.0, $this->stockOf($item), 0.001, 'thirty in');

        $sale = self::$fx->order($debtor, [['item' => $item, 'antal' => 30]]);
        $this->drive('deliver', [$sale['id'], 30]);

        $this->assertEqualsWithDelta(0.0, $this->stockOf($item), 0.001, 'thirty out - back to nothing');
    }

    /**
     * The two sides keep separate warehouse arithmetic: a receipt adds to the
     * `lagerstatus` row that a sale subtracts from.
     */
    public function test_receipts_and_sales_share_the_warehouse_row(): void
    {
        $creditor = self::$fx->creditor();
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['beholdning' => 0.0]);

        $purchase = self::$fx->creditorOrder($creditor, [['item' => $item, 'antal' => 20, 'pris' => 60.00]]);
        $this->receive($purchase['id'], 20);

        $sale = self::$fx->order($debtor, [['item' => $item, 'antal' => 8]]);
        $this->drive('deliver', [$sale['id'], 8]);

        $rows = $this->warehouseRows($item);
        $this->assertCount(1, $rows, 'both movements land on one warehouse row');
        $this->assertEqualsWithDelta(
            12.0,
            (float)$rows[0]['beholdning'],
            0.001,
            'twenty received less eight sold'
        );
        $this->assertEqualsWithDelta(12.0, $this->stockOf($item), 0.001, 'and the item balance agrees');
    }

    /**
     * The minimum-stock level is stored on the item and is NOT enforced
     * anywhere on the delivery path: a sale that takes the balance below the
     * configured minimum goes through without complaint.
     *
     * lager/minmaxstock.php is a report, not a guard.
     */
    public function test_the_minimum_stock_level_does_not_block_a_sale(): void
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['beholdning' => 10.0, 'min_lager' => 8.0, 'max_lager' => 100.0]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 9]]);

        $out = $this->drive('deliver', [$order['id'], 9]);

        $this->assertSame('OK', $out['levering'], 'the sale is not refused');
        $this->assertEqualsWithDelta(
            1.0,
            $this->stockOf($item),
            0.001,
            'the balance is left below the configured minimum of 8'
        );

        $stored = self::one('SELECT min_lager, max_lager FROM varer WHERE id = $1', [$item['id']]);
        $this->assertEqualsWithDelta(8.0, (float)$stored['min_lager'], 0.001, 'the minimum is still recorded');
    }

    /**
     * Fractional quantities survive the round trip: stock is numeric(15,3),
     * so a three-decimal quantity is not silently truncated.
     */
    public function test_fractional_quantities_are_kept_to_three_decimals(): void
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['beholdning' => 10.0]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 2.5]]);

        $this->drive('deliver', [$order['id'], 2.125]);

        $this->assertEqualsWithDelta(
            7.875,
            $this->stockOf($item),
            0.0005,
            'a three-decimal quantity is subtracted exactly'
        );
    }

    /** Delivering a zero quantity is a no-op rather than an error. */
    public function test_delivering_zero_is_a_no_op(): void
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['beholdning' => 10.0]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 5]]);

        $out = $this->drive('deliver', [$order['id'], 0]);

        $this->assertSame('OK', $out['levering']);
        $this->assertEqualsWithDelta(10.0, $this->stockOf($item), 0.001, 'nothing moved');
        $this->assertSame([], $this->warehouseRows($item), 'and no warehouse row was created');
    }
}
