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
// 20261117 CL/ASR Del ordre on a half-delivered order (debitor/ordre.php): the remainder line keeps a discount in kr per unit and reverse charge (audit finding sales-F3).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../support/PageForm.php';

final class OrderLifecycleCharacterizationTest extends CharacterizationTestCase
{
    /** grupper DIV 3 box4 (hurtigfakturering) before a test turned it off, or null */
    private ?array $quickInvoicing = null;

    protected function tearDown(): void
    {
        if ($this->quickInvoicing !== null) {
            self::rows("UPDATE grupper SET box4 = $1 WHERE art = 'DIV' AND kodenr = 3", [$this->quickInvoicing['box4']]);
            $this->quickInvoicing = null;
        }
    }

    /** Hurtigfakturering off (grupper DIV 3 box4) until tearDown(): debitor/ordre.php offers Del ordre only without it. */
    private function quickInvoicingOff(): void
    {
        $this->quickInvoicing = self::one("SELECT box4 FROM grupper WHERE art = 'DIV' AND kodenr = 3");
        $this->assertNotNull($this->quickInvoicing, 'the tenant has the order settings row');
        self::rows("UPDATE grupper SET box4 = '' WHERE art = 'DIV' AND kodenr = 3");
    }

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

    /**
     * An open order of 2 x 125,01 with a discount of 20 kr per unit (rabatart 'amount', as the discount matrix
     * writes it), of which 1 is delivered: the state in which debitor/ordre.php offers Del ordre.
     *
     * @param array<string, string> $line other ordrelinjer columns, e.g. omvbet
     * @return array{order:array, item:array, debtor:array}
     */
    private function halfDeliveredOrderWithAmountDiscount(array $line = []): array
    {
        $this->quickInvoicingOff();
        $opened = $this->openOrder(qty: 2.0, price: 125.01, rabat: 20.0);
        $orderId = $opened['order']['id'];
        self::rows("UPDATE ordrelinjer SET rabatart = 'amount' WHERE ordre_id = $1 AND vare_id > 0", [$orderId]);
        foreach ($line as $column => $value) {
            self::rows("UPDATE ordrelinjer SET $column = $1 WHERE ordre_id = $2 AND vare_id > 0", [$value, $orderId]);
        }
        self::rows('UPDATE ordrer SET sum = 210.02, moms = 52.51 WHERE id = $1', [$orderId]);
        $out = $this->drive('deliver', [$orderId, 1]);
        $this->assertSame('OK', $out['levering'], 'precondition: one unit delivered');
        return $opened;
    }

    /**
     * Presses Del ordre on debitor/ordre.php, through the order form as the page draws it.
     *
     * @return int the id of the remainder order the page creates
     */
    private function pressDelOrdre(int $orderId): int
    {
        $html = $this->orderPage($orderId);
        $this->assertStringContainsString('value="Del ordre"', $html, 'precondition: the page offers Del ordre');
        $form = PageForm::fields($html, 'ordre');
        $form['b_submit'] = 'Del ordre';
        $this->orderPage($orderId, $form);
        $rest = self::one(
            'SELECT r.id FROM ordrer r, ordrer o WHERE o.id = $1 AND r.ordrenr = o.ordrenr AND r.art = o.art AND r.id > o.id',
            [$orderId]
        );
        $this->assertNotNull($rest, 'Del ordre creates the remainder order');
        return (int)$rest['id'];
    }

    /**
     * Opens debitor/ordre.php?id= (a GET), or posts its form, as support/run_debitor_order_page.php does it.
     *
     * @param array<string, string> $post the form's fields, or [] for a GET
     */
    private function orderPage(int $orderId, array $post = []): string
    {
        $res = $this->runChildJson(
            'run_debitor_order_page.php',
            ["id=$orderId", CharacterizationEnv::sessionId(), $post === [] ? '' : http_build_query($post)]
        );
        $this->assertStringNotContainsString('Fatal error', $res['html'], "debitor/ordre.php?id=$orderId crashed");
        return $res['html'];
    }

    /**
     * Audit finding sales-F3: Del ordre wrote the remainder line without rabatart, so its 20 kr per unit became 20 %:
     * the remainder invoice was 125,01 less 20 % = 100,01 + 25,00 VAT instead of 105,01 + 26,25. The two orders'
     * totals also left the discount out (125,01 + 31,25 each).
     */
    public function test_del_ordre_keeps_a_discount_in_kr_per_unit_on_the_remainder(): void
    {
        ['order' => $order] = $this->halfDeliveredOrderWithAmountDiscount();

        $restId = $this->pressDelOrdre($order['id']);

        $line = self::one('SELECT antal, pris, rabat, rabatart, procent FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$restId]);
        $this->assertEqualsWithDelta(1.0, (float)$line['antal'], 0.001, 'the undelivered unit moves to the remainder');
        $this->assertEqualsWithDelta(20.0, (float)$line['rabat'], 0.001);
        $this->assertSame('amount', $line['rabatart'], 'the discount stays 20 kr per unit');
        $this->assertEqualsWithDelta(100.0, (float)$line['procent'], 0.001, 'the line is invoiced at 100 %');
        $rest = self::one('SELECT sum, moms FROM ordrer WHERE id = $1', [$restId]);
        $this->assertEqualsWithDelta(105.01, (float)$rest['sum'], 0.005, 'the remainder total is 125,01 less 20 kr (it was 125,01)');
        $this->assertEqualsWithDelta(26.25, (float)$rest['moms'], 0.005, 'and its VAT 25 % of that (it was 31,25)');
        $kept = self::one('SELECT o.sum, o.moms, l.antal FROM ordrer o, ordrelinjer l WHERE o.id = $1 AND l.ordre_id = o.id AND l.vare_id > 0', [$order['id']]);
        $this->assertEqualsWithDelta(1.0, (float)$kept['antal'], 0.001, 'the delivered unit stays on the order');
        $this->assertEqualsWithDelta(105.01, (float)$kept['sum'], 0.005, 'the order total is the delivered unit less 20 kr');
        $this->assertEqualsWithDelta(26.25, (float)$kept['moms'], 0.005);

        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $restId, CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'], 'the remainder is invoiced');
        $this->assertEqualsWithDelta(105.01, (float)$out['sum'], 0.005, 'the remainder invoice is 105,01 (it was 100,01)');
        $this->assertEqualsWithDelta(26.25, (float)$out['moms'], 0.005, 'with 26,25 VAT (it was 25,00)');
        $posted = self::rows('SELECT l.bogf_konto, l.vat_account FROM ordrelinjer l WHERE l.ordre_id = $1 AND l.vare_id > 0', [$restId])[0];
        $revenue = self::one('SELECT coalesce(sum(kredit), 0) - coalesce(sum(debet), 0) AS amount FROM transaktioner WHERE ordre_id = $1 AND kontonr = $2', [$restId, (int)$posted['bogf_konto']]);
        $vat = self::one('SELECT coalesce(sum(kredit), 0) - coalesce(sum(debet), 0) AS amount FROM transaktioner WHERE ordre_id = $1 AND kontonr = $2', [$restId, (int)$posted['vat_account']]);
        $total = self::one('SELECT sum(debet) AS debet, sum(kredit) AS kredit FROM transaktioner WHERE ordre_id = $1', [$restId]);
        $this->assertEqualsWithDelta(105.01, (float)$revenue['amount'], 0.005, 'revenue credited 105,01 (it was 100,01)');
        $this->assertEqualsWithDelta(26.25, (float)$vat['amount'], 0.005, 'output VAT credited 26,25 (it was 25,00)');
        $this->assertEqualsWithDelta(131.26, (float)$total['debet'], 0.005, 'the receivable is 131,26 (it was 125,01)');
        $this->assertEqualsWithDelta(131.26, (float)$total['kredit'], 0.005, 'the voucher balances');
        $openpost = self::one('SELECT amount FROM openpost WHERE refnr = $1', [$restId]);
        $this->assertEqualsWithDelta(131.26, (float)$openpost['amount'], 0.005, 'the open item is 131,26');
    }

    /**
     * Del ordre wrote the remainder line without omvbet, so a reverse-charge line was charged VAT on the remainder.
     */
    public function test_del_ordre_keeps_reverse_charge_on_the_remainder(): void
    {
        ['order' => $order] = $this->halfDeliveredOrderWithAmountDiscount(['omvbet' => 'on']);
        self::rows("UPDATE ordrer SET omvbet = 'on', moms = 0 WHERE id = $1", [$order['id']]);

        $restId = $this->pressDelOrdre($order['id']);

        $line = self::one('SELECT antal, omvbet, rabatart FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$restId]);
        $this->assertEqualsWithDelta(1.0, (float)$line['antal'], 0.001);
        $this->assertSame('on', $line['omvbet'], 'the remainder line is still reverse charge');
        $rest = self::one('SELECT sum, moms, omvbet FROM ordrer WHERE id = $1', [$restId]);
        $this->assertSame('on', $rest['omvbet']);
        $this->assertEqualsWithDelta(105.01, (float)$rest['sum'], 0.005);
        $this->assertEqualsWithDelta(0.0, (float)$rest['moms'], 0.005, 'no VAT on a reverse-charge line (it was 31,25)');
    }
}
