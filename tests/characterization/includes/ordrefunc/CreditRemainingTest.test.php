<?php
// tests/characterization/includes/ordrefunc/CreditRemainingTest.test.php
//
// SD-833: credit_remaining() tells how much of each invoice line is still creditable, by quantity and amount,
// over every credit note on the invoice, drafts included. credit_apply_line_choice() turns a line into a dekort
// or a quantity credit, and credit_hold_to_remaining() keeps a draft within what is left, also when two
// drafts are saved at the same time.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261118 CL/SS SD-833 Created.
// 20261120 CL/SS SD-833 review: a credit line whose quantity points the wrong way is held to nothing.
// 20261123 CDX/NTR Use the test class-owned database and session.

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class CreditRemainingTest extends CharacterizationTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::rows('ALTER TABLE ordrelinjer ADD COLUMN IF NOT EXISTS retur_lager varchar(2)');
        self::rows('ALTER TABLE ordrelinjer ADD COLUMN IF NOT EXISTS kredit_art varchar(6)');
    }

    private function remaining(int $origId, int $except = 0): array
    {
        return $this->runChildJson('run_credit_service.php', ['remaining', $origId, $except, CharacterizationEnv::testDb()])['remaining'];
    }

    private static function firstLine(int $orderId): int
    {
        return (int)self::one('SELECT id FROM ordrelinjer WHERE ordre_id = $1 ORDER BY id LIMIT 1', [$orderId])['id'];
    }

    private function credit(int $origLineId, array $item, float $antal, float $pris, ?string $kind = null): array
    {
        $note = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => $antal, 'pris' => $pris]], ['art' => 'DK']);
        self::rows('UPDATE ordrelinjer SET kred_linje_id = $1, kredit_art = $2 WHERE ordre_id = $3', [$origLineId, $kind, $note['id']]);
        self::rows('UPDATE ordrer SET kred_ord_id = (SELECT ordre_id FROM ordrelinjer WHERE id = $1), status = 0 WHERE id = $2', [$origLineId, $note['id']]);
        return $note;
    }

    private function invoiceOfTwoChairs(array $item, float $rabat = 0): array
    {
        return self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 2, 'pris' => 1000.00, 'rabat' => $rabat]], ['status' => 3]);
    }

    public function test_an_invoice_without_credit_notes_is_creditable_in_full(): void
    {
        $item = self::$fx->item();
        $invoice = $this->invoiceOfTwoChairs($item);
        $line = $this->remaining($invoice['id'])[self::firstLine($invoice['id'])];

        $this->assertEqualsWithDelta(2.0, $line['remaining_antal'], 0.001);
        $this->assertEqualsWithDelta(2000.0, $line['remaining_amount'], 0.001);
    }

    public function test_a_line_discount_lowers_the_creditable_amount(): void
    {
        $item = self::$fx->item();
        $invoice = $this->invoiceOfTwoChairs($item, 10);
        $line = $this->remaining($invoice['id'])[self::firstLine($invoice['id'])];

        $this->assertEqualsWithDelta(1800.0, $line['remaining_amount'], 0.001);
    }

    public function test_a_quantity_credit_lowers_both_quantity_and_amount(): void
    {
        $item = self::$fx->item();
        $invoice = $this->invoiceOfTwoChairs($item);
        $origLine = self::firstLine($invoice['id']);
        $this->credit($origLine, $item, -1, 1000.00, 'antal');
        $line = $this->remaining($invoice['id'])[$origLine];

        $this->assertEqualsWithDelta(1.0, $line['credited_antal'], 0.001);
        $this->assertEqualsWithDelta(1.0, $line['remaining_antal'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $line['remaining_amount'], 0.001);
    }

    public function test_a_dekort_lowers_the_amount_but_not_the_quantity(): void
    {
        $item = self::$fx->item();
        $invoice = $this->invoiceOfTwoChairs($item);
        $origLine = self::firstLine($invoice['id']);
        $this->credit($origLine, $item, -1, 200.00, 'dekort');
        $line = $this->remaining($invoice['id'])[$origLine];

        $this->assertEqualsWithDelta(2.0, $line['remaining_antal'], 0.001);
        $this->assertEqualsWithDelta(1800.0, $line['remaining_amount'], 0.001);
    }

    public function test_every_credit_note_counts_and_one_can_be_left_out(): void
    {
        $item = self::$fx->item();
        $invoice = $this->invoiceOfTwoChairs($item);
        $origLine = self::firstLine($invoice['id']);
        $this->credit($origLine, $item, -1, 1000.00);
        $second = $this->credit($origLine, $item, -1, 1000.00);

        $this->assertEqualsWithDelta(0.0, $this->remaining($invoice['id'])[$origLine]['remaining_antal'], 0.001, 'two drafts credit both chairs');
        $this->assertEqualsWithDelta(1.0, $this->remaining($invoice['id'], (int)$second['id'])[$origLine]['remaining_antal'], 0.001, 'without the second');
    }

    public function test_without_the_kredit_art_column_every_credit_counts_as_quantity(): void
    {
        $item = self::$fx->item();
        $invoice = $this->invoiceOfTwoChairs($item);
        $origLine = self::firstLine($invoice['id']);
        $this->credit($origLine, $item, -1, 200.00, 'dekort');
        self::rows('ALTER TABLE ordrelinjer DROP COLUMN kredit_art');
        try {
            $line = $this->remaining($invoice['id'])[$origLine];
        } finally {
            self::rows('ALTER TABLE ordrelinjer ADD COLUMN IF NOT EXISTS kredit_art varchar(6)');
        }

        $this->assertEqualsWithDelta(1.0, $line['remaining_antal'], 0.001);
        $this->assertEqualsWithDelta(1800.0, $line['remaining_amount'], 0.001);
    }

    private function service(string $scenario, array $args): array
    {
        return $this->runChildJson('run_credit_service.php', array_merge([$scenario], $args, [CharacterizationEnv::testDb()]));
    }

    private static function lineOf(int $orderId): array
    {
        return self::one('SELECT * FROM ordrelinjer WHERE ordre_id = $1 ORDER BY id LIMIT 1', [$orderId]);
    }

    public function test_a_ten_percent_dekort_of_1250_credits_exactly_125_and_moves_no_stock(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 500.00]);
        $purchase = self::$fx->creditorOrder(self::$fx->creditor(), [['item' => $item, 'antal' => 5, 'pris' => 500.00]]);
        self::rows('UPDATE ordrelinjer SET leveres = 5 WHERE ordre_id = $1 AND vare_id > 0', [$purchase['id']]);
        self::runChild('run_modtag_page.php', [$purchase['id'], CharacterizationEnv::sessionId()]);
        $invoice = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 1, 'pris' => 1000.00]]);
        $this->runChildJson('run_order_invoice.php', ['invoice', $invoice['id'], CharacterizationEnv::testDb()]);
        $stock = (float)self::one('SELECT beholdning FROM varer WHERE id = $1', [$item['id']])['beholdning'];

        $note = $this->credit(self::firstLine($invoice['id']), $item, -1, 1000.00);
        $this->service('choice', [self::firstLine($note['id']), 10, 0]);
        $line = self::lineOf($note['id']);
        $this->assertSame('dekort', trim($line['kredit_art']));
        $this->assertSame('of', trim($line['retur_lager']));
        $this->assertEqualsWithDelta(-1.0, (float)$line['antal'], 0.001);
        $this->assertEqualsWithDelta(100.0, (float)$line['pris'], 0.001);

        $this->runChildJson('run_order_invoice.php', ['invoice', $note['id'], CharacterizationEnv::testDb()]);
        $head = self::one('SELECT sum, moms FROM ordrer WHERE id = $1', [$note['id']]);
        $this->assertEqualsWithDelta(125.0, abs((float)$head['sum']) + abs((float)$head['moms']), 0.001, '100 + 25 VAT');
        $this->assertEqualsWithDelta($stock, (float)self::one('SELECT beholdning FROM varer WHERE id = $1', [$item['id']])['beholdning'], 0.001, 'no stock movement');
    }

    public function test_turning_a_dekort_back_restores_the_invoice_price(): void
    {
        $item = self::$fx->item();
        $invoice = $this->invoiceOfTwoChairs($item, 10);
        $note = $this->credit(self::firstLine($invoice['id']), $item, -1, 1000.00);
        $noteLine = self::firstLine($note['id']);
        self::rows('UPDATE ordrelinjer SET rabat = 10 WHERE id = $1', [$noteLine]);
        $this->service('choice', [$noteLine, 10, 0]);
        $this->service('choice', [$noteLine, 0, 1]);
        $line = self::lineOf($note['id']);

        $this->assertSame('antal', trim($line['kredit_art']));
        $this->assertSame('on', trim($line['retur_lager']));
        $this->assertEqualsWithDelta(1000.0, (float)$line['pris'], 0.001);
        $this->assertEqualsWithDelta(10.0, (float)$line['rabat'], 0.001);
    }

    public function test_a_draft_is_held_to_the_quantity_left(): void
    {
        $item = self::$fx->item();
        $invoice = $this->invoiceOfTwoChairs($item);
        $origLine = self::firstLine($invoice['id']);
        $this->credit($origLine, $item, -2, 1000.00, 'antal');
        $second = $this->credit($origLine, $item, -2, 1000.00, 'antal');
        $changed = $this->service('hold', [$second['id']])['changed'];

        $this->assertCount(1, $changed);
        $this->assertEqualsWithDelta(0.0, (float)self::lineOf($second['id'])['antal'], 0.001, 'both chairs are already on the first note');
    }

    public function test_after_a_dekort_a_unit_is_credited_at_what_is_left_of_it(): void
    {
        $item = self::$fx->item();
        $invoice = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 1, 'pris' => 1000.00]], ['status' => 3]);
        $origLine = self::firstLine($invoice['id']);
        $this->credit($origLine, $item, -1, 100.00, 'dekort');
        $second = $this->credit($origLine, $item, -1, 1000.00, 'antal');
        $this->service('hold', [$second['id']]);
        $line = self::lineOf($second['id']);

        $this->assertEqualsWithDelta(-1.0, (float)$line['antal'], 0.001, 'the unit itself can still come back');
        $this->assertEqualsWithDelta(900.0, (float)$line['pris'], 0.001, 'at the 900 not yet credited');
    }

    public function test_two_drafts_saved_at_the_same_time_never_credit_more_than_the_invoice(): void
    {
        $item = self::$fx->item();
        $invoice = $this->invoiceOfTwoChairs($item);
        $origLine = self::firstLine($invoice['id']);
        $first = $this->credit($origLine, $item, 0, 1000.00, 'antal');
        $second = $this->credit($origLine, $item, 0, 1000.00, 'antal');

        $cmd = [PHP_BINARY, __DIR__ . '/../../support/run_credit_service.php', 'raise_and_hold', (string)$first['id'], '2', '1500', CharacterizationEnv::testDb()];
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($proc);
        fclose($pipes[0]);
        usleep(400000);
        $this->service('raise_and_hold', [$second['id'], 2, 0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        proc_close($proc);

        $credited = -(float)self::lineOf($first['id'])['antal'] - (float)self::lineOf($second['id'])['antal'];
        $this->assertEqualsWithDelta(2.0, $credited, 0.001, 'the second save waits for the first and takes what is left');
        $this->assertEqualsWithDelta(0.0, (float)self::lineOf($second['id'])['antal'], 0.001);
    }

    public function test_a_capped_price_never_rounds_above_what_is_left(): void
    {
        $item = self::$fx->item();
        $invoice = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 100, 'pris' => 1.00]], ['status' => 3]);
        $origLine = self::firstLine($invoice['id']);
        $this->credit($origLine, $item, -1, 0.05, 'dekort');
        $second = $this->credit($origLine, $item, -100, 1.00, 'antal');
        $this->service('hold', [$second['id']]);
        $line = self::lineOf($second['id']);

        $this->assertLessThanOrEqual(99.95, -(float)$line['antal'] * (float)$line['pris'] + 0.0000001, '99.95 is left');
        $this->assertEqualsWithDelta(0.0, $this->remaining($invoice['id'])[$origLine]['remaining_amount'] >= 0 ? 0.0 : 1.0, 0.001, 'nothing is over-credited');
    }

    public function test_a_negative_invoice_line_is_credited_the_other_way_round_and_only_once(): void
    {
        $item = self::$fx->item();
        $invoice = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => -1, 'pris' => 100.00]], ['status' => 3]);
        $origLine = self::firstLine($invoice['id']);
        $this->assertEqualsWithDelta(1.0, $this->remaining($invoice['id'])[$origLine]['remaining_antal'], 0.001);

        $this->credit($origLine, $item, 1, 100.00, 'antal');
        $line = $this->remaining($invoice['id'])[$origLine];
        $this->assertEqualsWithDelta(0.0, $line['remaining_antal'], 0.001);
        $this->assertEqualsWithDelta(0.0, $line['remaining_amount'], 0.001);

        $again = $this->credit($origLine, $item, 1, 100.00, 'antal');
        $this->service('hold', [$again['id']]);
        $this->assertEqualsWithDelta(0.0, (float)self::lineOf($again['id'])['antal'], 0.001, 'a second reversal is held to nothing');
    }

    public function test_a_dekort_on_a_negative_invoice_line_is_held_to_the_amount_left(): void
    {
        $item = self::$fx->item();
        $invoice = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => -1, 'pris' => 100.00]], ['status' => 3]);
        $origLine = self::firstLine($invoice['id']);
        $this->credit($origLine, $item, -1, -90.00, 'dekort');
        $second = $this->credit($origLine, $item, -1, -50.00, 'dekort');
        $this->service('hold', [$second['id']]);

        $this->assertEqualsWithDelta(-10.0, (float)self::lineOf($second['id'])['pris'], 0.001, '10 of the 100 is left');
        $this->assertEqualsWithDelta(0.0, $this->remaining($invoice['id'])[$origLine]['remaining_amount'], 0.001);
    }

    public function test_a_credit_line_whose_quantity_points_the_wrong_way_is_held_to_nothing(): void
    {
        $item = self::$fx->item();
        $invoice = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 1, 'pris' => 100.00]], ['status' => 3]);
        $origLine = self::firstLine($invoice['id']);
        $note = $this->credit($origLine, $item, 1, -200.00, 'antal');
        $changed = $this->service('hold', [$note['id']])['changed'];

        $this->assertCount(1, $changed, 'the change is reported');
        $this->assertEqualsWithDelta(0.0, (float)self::lineOf($note['id'])['antal'], 0.001, '+1 x -200 would credit 200 of an invoice of 100');
        $this->assertEqualsWithDelta(100.0, $this->remaining($invoice['id'])[$origLine]['remaining_amount'], 0.001);
    }
}
