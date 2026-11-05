<?php
// tests/characterization/debitor/OrderLifecycleCharacterizationTest.test.php
//
// Characterization tests for the order lifecycle around invoicing: delivery
// and its effect on stock, adding lines, discounts, and crediting.
//
// OrderInvoiceCharacterizationTest covers the one sequence remoteBooking runs
// in production (deliver everything, then invoice). This suite stops at the
// intermediate states a human working in debitor/ordre.php actually reaches -
// a half-delivered order, a line added after the fact, a credit note - and
// pins what each does to `varer.beholdning` and to the order header.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class OrderLifecycleCharacterizationTest extends CharacterizationTestCase
{
    /** @return array{order:array, item:array, debtor:array} */
    private function openOrder(float $qty = 10.0, float $price = 125.00, float $stock = 100.0, float $rabat = 0.0): array
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['salgspris' => $price, 'kostpris' => 75.00, 'beholdning' => $stock]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => $qty, 'rabat' => $rabat]]);

        return ['order' => $order, 'item' => $item, 'debtor' => $debtor];
    }

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

    public function test_delivering_the_whole_order_draws_the_quantity_out_of_stock(): void
    {
        ['order' => $order, 'item' => $item] = $this->openOrder(qty: 10.0, stock: 100.0);
        $this->assertEqualsWithDelta(100.0, $this->stockOf($item), 0.001, 'precondition: 100 in stock');

        $out = $this->drive('deliver', [$order['id'], 10]);

        $this->assertSame('OK', $out['levering'], 'levering reports OK');
        $this->assertEqualsWithDelta(90.0, $this->stockOf($item), 0.001, 'ten units left stock');
    }

    /**
     * A delivery is recorded on the line as `leveret`, and the pending
     * quantity `leveres` is consumed.
     */
    public function test_delivery_is_recorded_on_the_order_line(): void
    {
        ['order' => $order] = $this->openOrder(qty: 10.0);

        $out = $this->drive('deliver', [$order['id'], 10]);

        $this->assertCount(1, $out['lines']);
        $line = $out['lines'][0];
        $this->assertEqualsWithDelta(10.0, $line['antal'], 0.001, 'ordered quantity unchanged');
        $this->assertEqualsWithDelta(0.0, $line['leveres'], 0.001, 'the pending quantity is consumed');
    }

    /** A partial delivery only draws the delivered quantity out of stock. */
    public function test_a_partial_delivery_only_moves_the_delivered_quantity(): void
    {
        ['order' => $order, 'item' => $item] = $this->openOrder(qty: 10.0, stock: 100.0);

        $out = $this->drive('deliver', [$order['id'], 4]);

        $this->assertSame('OK', $out['levering']);
        $this->assertEqualsWithDelta(96.0, $this->stockOf($item), 0.001, 'only four units left stock');
    }

    /** A partially delivered order is not finished: it is not marked invoiced. */
    public function test_a_partially_delivered_order_stays_open(): void
    {
        ['order' => $order] = $this->openOrder(qty: 10.0);

        $out = $this->drive('deliver', [$order['id'], 4]);

        $this->assertLessThan(3, (int)$out['status'], 'a part-delivered order is not booked');
        $this->assertEmpty(trim((string)$out['fakturanr']), 'no invoice number is assigned by delivery alone');
    }

    /** Two deliveries against the same order accumulate in stock. */
    public function test_successive_deliveries_accumulate(): void
    {
        ['order' => $order, 'item' => $item] = $this->openOrder(qty: 10.0, stock: 100.0);

        $this->drive('deliver', [$order['id'], 4]);
        $this->drive('deliver', [$order['id'], 3]);

        $this->assertEqualsWithDelta(93.0, $this->stockOf($item), 0.001, 'seven units left stock across two deliveries');
    }

    /**
     * Delivering more than is in stock is allowed and drives the balance
     * negative - there is no stock guard on this path.
     */
    public function test_delivering_more_than_stock_drives_the_balance_negative(): void
    {
        ['order' => $order, 'item' => $item] = $this->openOrder(qty: 10.0, stock: 3.0);

        $out = $this->drive('deliver', [$order['id'], 10]);

        $this->assertSame('OK', $out['levering'], 'the delivery is not refused');
        $this->assertEqualsWithDelta(-7.0, $this->stockOf($item), 0.001, 'stock goes negative');
    }

    /** A line added to an open order lands on the order with its price. */
    public function test_a_line_can_be_added_to_an_open_order(): void
    {
        ['order' => $order] = $this->openOrder(qty: 2.0);
        $extra = self::$fx->item(['salgspris' => 50.00, 'beholdning' => 20]);

        $out = $this->drive('addline', [$order['id'], $extra['id'], 3, 50.00, 0]);

        $this->assertCount(2, $out['lines'], 'the order now has two stock lines');
        $added = array_values(array_filter($out['lines'], static fn($l) => $l['vare_id'] === $extra['id']));
        $this->assertCount(1, $added);
        $this->assertEqualsWithDelta(3.0, $added[0]['antal'], 0.001);
        $this->assertEqualsWithDelta(50.00, $added[0]['pris'], 0.005);
    }

    /**
     * A booked order is closed to new lines: opret_ordrelinje() refuses with
     * a message rather than silently appending (ordrefunc.php:3714).
     */
    public function test_a_booked_order_refuses_new_lines(): void
    {
        ['order' => $order] = $this->openOrder(qty: 2.0);
        $extra = self::$fx->item(['salgspris' => 50.00, 'beholdning' => 20]);

        // Book the order the way remoteBooking does.
        $invoiced = $this->runChildJson(
            'run_order_invoice.php',
            ['invoice', $order['id'], CharacterizationEnv::testDb()]
        );
        $this->assertSame('OK', $invoiced['bogfor'], 'precondition: the order is booked');

        $out = $this->drive('addline', [$order['id'], $extra['id'], 3, 50.00, 0]);

        $this->assertStringContainsString(
            'ikke tilføjes linjer',
            (string)$out['opret_ordrelinje'],
            'adding a line to a booked order is refused (current wording pinned)'
        );
        $this->assertCount(1, $out['lines'], 'no line was appended');
    }

    /** A line discount is stored on the line as a percentage. */
    public function test_a_line_discount_is_stored_as_a_percentage(): void
    {
        ['order' => $order] = $this->openOrder(qty: 4.0, price: 100.00, rabat: 25.0);

        $lines = self::rows(
            'SELECT antal, pris, rabat FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0',
            [$order['id']]
        );
        $this->assertCount(1, $lines);
        $this->assertEqualsWithDelta(25.0, (float)$lines[0]['rabat'], 0.001, 'the discount is a percentage');
        $this->assertEqualsWithDelta(100.00, (float)$lines[0]['pris'], 0.005, 'the unit price is the undiscounted one');
        $this->assertEqualsWithDelta(
            300.00,
            $order['sum'],
            0.005,
            '4 x 100 less 25% is the order total'
        );
    }

    /** Returning goods on a delivered order puts the quantity back into stock. */
    public function test_returning_goods_puts_the_quantity_back_into_stock(): void
    {
        ['order' => $order, 'item' => $item] = $this->openOrder(qty: 10.0, stock: 100.0);

        $this->drive('deliver', [$order['id'], 10]);
        $this->assertEqualsWithDelta(90.0, $this->stockOf($item), 0.001, 'precondition: ten units delivered');

        $out = $this->drive('deliver', [$order['id'], -4]);

        $this->assertSame('OK', $out['levering'], 'the return is accepted');
        $this->assertEqualsWithDelta(94.0, $this->stockOf($item), 0.001, 'four units came back into stock');
    }

    /**
     * A return is capped at what was actually delivered: asking to return
     * more than went out only books back what went out
     * (includes/ordrefunc.php:340).
     */
    public function test_a_return_is_capped_at_the_delivered_quantity(): void
    {
        ['order' => $order, 'item' => $item] = $this->openOrder(qty: 10.0, stock: 100.0);

        $this->drive('deliver', [$order['id'], 6]);
        $this->assertEqualsWithDelta(94.0, $this->stockOf($item), 0.001, 'precondition: six units delivered');

        $this->drive('deliver', [$order['id'], -10]);

        $this->assertEqualsWithDelta(
            100.0,
            $this->stockOf($item),
            0.001,
            'only the six delivered units come back, not ten'
        );
    }
}
