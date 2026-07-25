<?php
// tests/characterization/includes/ordrefunc/OrderInvoiceCharacterizationTest.test.php
//
// Characterization tests for the order -> invoice conversion (SD-601).
//
// Pins the CURRENT behavior of includes/ordrefunc.php bogfor($ordre_id,'on')
// as production drives it (remoteBooking/api.php:296-337): deliver the lines,
// convert to invoice, assign fakturanr, set status 3. Also pins the guard
// against invoicing twice.
//
// Fixture orders are created the way remoteBooking/api.php CreateOrder does
// it (direct INSERT INTO ordrer + opret_ordrelinje) via the child runner's
// bootstrap; here we insert the rows over pg and let the runner drive the
// ordrefunc code.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20260723 CL/LH SD-601: created.
// 20260725 CL/LH Moved onto CharacterizationTestCase + Fixtures.

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class OrderInvoiceCharacterizationTest extends CharacterizationTestCase
{
    /** Debtor + item + order with one line: qty 2 x 125.00, 25% VAT. */
    private function createOrderFixture(): array
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['salgspris' => 125.00, 'kostpris' => 75.00]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 2]]);

        return [$order['id'], $debtor['id'], $order['sum'], $order['moms']];
    }

    private function runInvoice(string $scenario, int $orderId): array
    {
        return $this->runChildJson('run_order_invoice.php', [$scenario, $orderId, CharacterizationEnv::testDb()]);
    }

    public function test_invoicing_an_open_order_assigns_invoice_number_and_invoiced_status(): void
    {
        [$orderId, , $sum, $moms] = $this->createOrderFixture();

        $out = $this->runInvoice('invoice', $orderId);

        $this->assertSame('OK', $out['levering'], 'levering reports OK');
        $this->assertSame('OK', $out['bogfor'], 'bogfor reports OK');
        // Observed: the delivered+invoiced order ends at status 4 (the
        // re-invoice guard at includes/ordrefunc.php:1231 only requires >2).
        $this->assertSame('4', (string)$out['status'], 'order status becomes 4 (delivered + invoiced)');
        $this->assertGreaterThan(0, (int)$out['fakturanr'], 'an invoice number is assigned');
        $this->assertEqualsWithDelta($sum, (float)$out['sum'], 0.005, 'order sum unchanged by invoicing');
        $this->assertEqualsWithDelta($moms, (float)$out['moms'], 0.005, 'order VAT unchanged by invoicing');

        $lines = self::rows(
            'SELECT antal, pris, leveres, leveret FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0',
            [$orderId]
        );
        $this->assertNotEmpty($lines);
        foreach ($lines as $line) {
            // Observed: levering() consumes the pending quantity (leveres was
            // set to antal before the call, and is 0 after); leveret is not
            // stamped on this path.
            $this->assertEqualsWithDelta(0.0, (float)$line['leveres'], 0.001, 'pending delivery quantity consumed');
            $this->assertEqualsWithDelta(2.0, (float)$line['antal'], 0.001, 'ordered quantity unchanged');
        }
    }

    public function test_invoice_numbers_are_sequential_across_orders(): void
    {
        [$firstId] = $this->createOrderFixture();
        $first = $this->runInvoice('invoice', $firstId);
        [$secondId] = $this->createOrderFixture();
        $second = $this->runInvoice('invoice', $secondId);

        $this->assertSame('OK', $second['bogfor']);
        $this->assertSame(
            (int)$first['fakturanr'] + 1,
            (int)$second['fakturanr'],
            'invoice numbers are assigned sequentially'
        );
    }

    public function test_invoicing_twice_is_refused_and_keeps_the_original_invoice_number(): void
    {
        [$orderId] = $this->createOrderFixture();
        $first = $this->runInvoice('invoice', $orderId);
        $this->assertSame('OK', $first['bogfor']);
        $fakturanr = (int)$first['fakturanr'];
        $this->assertGreaterThan(0, $fakturanr);

        $second = $this->runInvoice('reinvoice', $orderId);

        $this->assertStringContainsString(
            'invoice allready created',
            (string)$second['bogfor'],
            'second invoicing attempt is refused (current wording pinned)'
        );
        $this->assertSame($fakturanr, (int)$second['fakturanr'], 'invoice number is unchanged');
        $this->assertSame('4', (string)$second['status'], 'status stays 4');
    }
}
