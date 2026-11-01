<?php
// tests/characterization/includes/ordrefunc/ReturnPurchaseBatchTest.test.php
//
// SST-910: a return on a stock item without batch control (credit note or negative line) goes back into the
// purchase batches, so a later sale takes its cost from a batch and not from the item card. Stock counted
// from the batches (sum(batch_kob.antal) - sum(batch_salg.antal), as lager/optalling.php and
// lager/lagerstatus.php do) must still equal the item's stock: the return is counted once, not twice.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261101 CL/MJ SST-910 Created.

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class ReturnPurchaseBatchTest extends CharacterizationTestCase
{
    private function deliver(int $orderId, float $qty): void
    {
        $this->runChildJson('run_order_lifecycle.php', ['deliver', $orderId, $qty, CharacterizationEnv::testDb()]);
    }

    private function receive(int $orderId, float $qty): void
    {
        self::rows('UPDATE ordrelinjer SET leveres = $1 WHERE ordre_id = $2 AND vare_id > 0', [$qty, $orderId]);
        self::runChild('run_modtag_page.php', [$orderId, CharacterizationEnv::SESSION_ID]);
    }

    private static function lineId(int $orderId): int
    {
        return (int)self::one('SELECT id FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$orderId])['id'];
    }

    /** @return list<array<string,string>> */
    private static function batches(array $item): array
    {
        return self::rows('SELECT id, linje_id, antal, rest, pris, kobsdate FROM batch_kob WHERE vare_id = $1 ORDER BY id', [$item['id']]);
    }

    /** Stock as the stock count and the stock list compute it from the batches. */
    private static function stockFromBatches(array $item): float
    {
        $kob = self::one('SELECT COALESCE(sum(antal), 0) AS antal FROM batch_kob WHERE vare_id = $1', [$item['id']]);
        $salg = self::one('SELECT COALESCE(sum(antal), 0) AS antal FROM batch_salg WHERE vare_id = $1', [$item['id']]);
        return (float)$kob['antal'] - (float)$salg['antal'];
    }

    private static function stockOf(array $item): float
    {
        return (float)self::one('SELECT beholdning FROM varer WHERE id = $1', [$item['id']])['beholdning'];
    }

    /** Ten bought at 100 and all ten sold: the purchase batch is empty. */
    private function boughtAndSold(array $item): array
    {
        $purchase = self::$fx->creditorOrder(self::$fx->creditor(), [['item' => $item, 'antal' => 10, 'pris' => 100.00]]);
        $this->receive($purchase['id'], 10);
        $sale = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 10]]);
        $this->deliver($sale['id'], 10);
        $batch = self::batches($item)[0];
        $this->assertEqualsWithDelta(0.0, (float)$batch['rest'], 0.001, 'all ten sold');
        return ['sale' => $sale, 'batch' => $batch];
    }

    private function creditNote(int $saleLineId, array $item, float $qty, float $kostpris): array
    {
        $note = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => -$qty, 'kostpris' => $kostpris]], ['art' => 'DK']);
        self::rows('UPDATE ordrelinjer SET kred_linje_id = $1 WHERE ordre_id = $2', [$saleLineId, $note['id']]);
        $this->deliver($note['id'], -$qty);
        return $note;
    }

    private function assertNextSaleUsesBatch(array $item, int $batchId, float $qty): void
    {
        $sale = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => $qty]]);
        $this->deliver($sale['id'], $qty);
        $rows = self::rows('SELECT batch_kob_id, antal FROM batch_salg WHERE linje_id = $1', [self::lineId($sale['id'])]);
        $this->assertCount(1, $rows, 'one batch covers the sale');
        $this->assertSame($batchId, (int)$rows[0]['batch_kob_id'], 'the sale takes the returned units from that batch');
        $deficit = self::one('SELECT count(*) AS n FROM batch_kob WHERE vare_id = $1 AND rest < 0', [$item['id']]);
        $this->assertSame(0, (int)$deficit['n'], 'no deficit batch');
    }

    public function test_a_credit_note_with_a_reference_puts_the_units_back_on_the_original_batch(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        ['sale' => $sale, 'batch' => $batch] = $this->boughtAndSold($item);

        $note = $this->creditNote(self::lineId($sale['id']), $item, 2, 100.00);

        $batches = self::batches($item);
        $this->assertCount(1, $batches, 'no new batch: the original one takes the units back');
        $this->assertEqualsWithDelta(2.0, (float)$batches[0]['rest'], 0.001, 'rest raised by the two returned');
        $neg = self::rows('SELECT batch_kob_id, antal FROM batch_salg WHERE linje_id = $1', [self::lineId($note['id'])]);
        $this->assertCount(1, $neg);
        $this->assertSame((int)$batch['id'], (int)$neg[0]['batch_kob_id'], 'the negative row names the batch');
        $this->assertEqualsWithDelta(-2.0, (float)$neg[0]['antal'], 0.001);
        $this->assertEqualsWithDelta(2.0, self::stockOf($item), 0.001);
        $this->assertEqualsWithDelta(self::stockOf($item), self::stockFromBatches($item), 0.001, 'the return is counted once');

        $this->assertNextSaleUsesBatch($item, (int)$batch['id'], 2);
    }

    public function test_a_negative_line_without_a_reference_gets_a_batch_at_the_line_cost(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $this->boughtAndSold($item);

        $return = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => -2]]);
        $this->deliver($return['id'], -2);

        $batches = self::batches($item);
        $this->assertCount(2, $batches, 'a batch for the returned units');
        $new = $batches[1];
        $this->assertEqualsWithDelta(0.0, (float)$new['antal'], 0.001, 'antal 0 like a deficit batch');
        $this->assertEqualsWithDelta(2.0, (float)$new['rest'], 0.001);
        $this->assertNotNull($new['kobsdate']);
        $this->assertEqualsWithDelta(50.0, (float)$new['pris'], 0.001, 'no open purchase line, so the item card\'s cost');
        $neg = self::one('SELECT batch_kob_id, antal FROM batch_salg WHERE linje_id = $1', [self::lineId($return['id'])]);
        $this->assertSame((int)$new['id'], (int)$neg['batch_kob_id'], 'the negative row names the new batch');
        $this->assertEqualsWithDelta(2.0, self::stockOf($item), 0.001);
        $this->assertEqualsWithDelta(self::stockOf($item), self::stockFromBatches($item), 0.001, 'the return is counted once');

        $this->assertNextSaleUsesBatch($item, (int)$new['id'], 2);
    }

    /** An original sale without batch rows (older data) has nothing to give back: the units get a batch at its cost. */
    public function test_what_the_original_batches_cannot_take_gets_a_batch_at_the_original_cost(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        ['sale' => $sale] = $this->boughtAndSold($item);
        $saleLine = self::lineId($sale['id']);
        self::rows('UPDATE ordrelinjer SET kostpris = 80 WHERE id = $1', [$saleLine]);
        self::rows('UPDATE batch_salg SET batch_kob_id = 0 WHERE linje_id = $1', [$saleLine]);

        $this->creditNote($saleLine, $item, 2, 60.00);

        $batches = self::batches($item);
        $this->assertCount(2, $batches);
        $this->assertEqualsWithDelta(0.0, (float)$batches[0]['rest'], 0.001, 'the original batch is untouched');
        $this->assertEqualsWithDelta(2.0, (float)$batches[1]['rest'], 0.001);
        $this->assertEqualsWithDelta(80.0, (float)$batches[1]['pris'], 0.001, 'the original line\'s cost, not the credit line\'s 60 or the item card\'s 50');
        $this->assertEqualsWithDelta(self::stockOf($item), self::stockFromBatches($item), 0.001, 'the return is counted once');
    }

    /**
     * A sale of 10 that took 6 from a batch and 4 without one (older data): crediting all 10 puts 6 back on
     * the batch and gives the other 4 a batch of their own at the original line's cost (CodeRabbit).
     */
    public function test_a_return_split_between_the_original_batch_and_a_new_one(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $purchase = self::$fx->creditorOrder(self::$fx->creditor(), [['item' => $item, 'antal' => 6, 'pris' => 100.00]]);
        $this->receive($purchase['id'], 6);
        $sale = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 10]]);
        $this->deliver($sale['id'], 10); # 6 from the purchase batch, 4 from a deficit batch
        $saleLine = self::lineId($sale['id']);
        self::rows('UPDATE ordrelinjer SET kostpris = 80 WHERE id = $1', [$saleLine]);
        [$batch, $deficit] = self::batches($item);
        self::rows('UPDATE batch_salg SET batch_kob_id = 0 WHERE linje_id = $1 AND batch_kob_id = $2', [$saleLine, $deficit['id']]);

        $this->creditNote($saleLine, $item, 10, 60.00);

        $batches = self::batches($item);
        $this->assertCount(3, $batches, 'one new batch for the remainder');
        $this->assertEqualsWithDelta(6.0, (float)$batches[0]['rest'], 0.001, 'the purchase batch takes back its 6');
        $this->assertEqualsWithDelta(4.0, (float)$batches[2]['rest'], 0.001, 'the other 4 in a new batch');
        $this->assertEqualsWithDelta(80.0, (float)$batches[2]['pris'], 0.001, 'at the original line\'s cost');
        $this->assertEqualsWithDelta(self::stockOf($item), self::stockFromBatches($item), 0.001);
    }

    /** A sale only draws from its own warehouse, so a return to another warehouse gets a batch there. */
    public function test_a_return_to_another_warehouse_gets_a_batch_in_that_warehouse(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        ['sale' => $sale] = $this->boughtAndSold($item);
        self::rows('UPDATE ordrelinjer SET kostpris = 90 WHERE id = $1', [self::lineId($sale['id'])]);

        $note = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => -2, 'kostpris' => 100.00]], ['art' => 'DK']);
        self::rows('UPDATE ordrelinjer SET kred_linje_id = $1, lager = 2 WHERE ordre_id = $2', [self::lineId($sale['id']), $note['id']]);
        $this->deliver($note['id'], -2);

        $batches = self::batches($item);
        $this->assertCount(2, $batches);
        $this->assertEqualsWithDelta(0.0, (float)$batches[0]['rest'], 0.001, 'the warehouse 1 batch is untouched');
        $lager = self::one('SELECT lager FROM batch_kob WHERE id = $1', [$batches[1]['id']]);
        $this->assertSame(2, (int)$lager['lager']);
        $this->assertEqualsWithDelta(2.0, (float)$batches[1]['rest'], 0.001);
        $this->assertEqualsWithDelta(90.0, (float)$batches[1]['pris'], 0.001, 'the original line\'s cost, not the credit line\'s 100');
        $this->assertEqualsWithDelta(self::stockOf($item), self::stockFromBatches($item), 0.001);
    }

    /**
     * Two credit notes against the same sale line give each batch back no more than the line took from
     * it: the second counts what the first already returned.
     */
    public function test_two_credit_notes_return_no_more_to_a_batch_than_was_taken_from_it(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $creditor = self::$fx->creditor();
        $a = self::$fx->creditorOrder($creditor, [['item' => $item, 'antal' => 5, 'pris' => 100.00]]);
        $this->receive($a['id'], 5);
        $b = self::$fx->creditorOrder($creditor, [['item' => $item, 'antal' => 100, 'pris' => 120.00]]);
        $this->receive($b['id'], 100);
        $sale = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 10]]);
        $this->deliver($sale['id'], 10); # 5 from A, 5 from B
        $other = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 50]]);
        $this->deliver($other['id'], 50); # B down to 45
        [$batchA, $batchB] = self::batches($item);
        $this->assertEqualsWithDelta(45.0, (float)$batchB['rest'], 0.001);

        $this->creditNote(self::lineId($sale['id']), $item, 4, 110.00); # B, newest first, takes 4
        $this->creditNote(self::lineId($sale['id']), $item, 6, 110.00); # B has 1 left to take, A 5

        $rest = fn (array $batch) => (float)self::one('SELECT rest FROM batch_kob WHERE id = $1', [$batch['id']])['rest'];
        $this->assertEqualsWithDelta(50.0, $rest($batchB), 0.001, 'B gets back the 5 it gave');
        $this->assertEqualsWithDelta(5.0, $rest($batchA), 0.001, 'A gets back the 5 it gave');
        $this->assertCount(2, self::batches($item), 'no extra batch');
        $this->assertEqualsWithDelta(self::stockOf($item), self::stockFromBatches($item), 0.001);
    }

    /**
     * A sale line delivered in two goes from the same batch has two rows on it. Crediting the whole line
     * gives the batch back all of it (Sol, round 2). The split is made in SQL, as two partial deliveries
     * leave it: this tenant's quick invoicing always delivers the whole line.
     */
    public function test_a_line_delivered_twice_from_one_batch_gets_all_of_it_back(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        ['sale' => $sale, 'batch' => $batch] = $this->boughtAndSold($item);
        $saleLine = self::lineId($sale['id']);
        self::rows('UPDATE batch_salg SET antal = 5 WHERE linje_id = $1', [$saleLine]);
        self::rows(
            'INSERT INTO batch_salg (batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lager, variant_id)
             SELECT batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lager, variant_id FROM batch_salg WHERE linje_id = $1',
            [$saleLine]
        );

        $this->creditNote($saleLine, $item, 10, 100.00);

        $batches = self::batches($item);
        $this->assertCount(1, $batches, 'no new batch');
        $this->assertEqualsWithDelta(10.0, (float)$batches[0]['rest'], 0.001, 'both deliveries back on the batch');
        $this->assertEqualsWithDelta(self::stockOf($item), self::stockFromBatches($item), 0.001);
    }

    /** A sale from negative stock wrote a deficit batch; crediting it brings that batch back to zero. */
    public function test_crediting_a_sale_from_negative_stock_closes_its_deficit_batch(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $sale = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 2]]);
        $this->deliver($sale['id'], 2);
        $this->assertEqualsWithDelta(-2.0, (float)self::batches($item)[0]['rest'], 0.001, 'deficit batch');

        $this->creditNote(self::lineId($sale['id']), $item, 2, 50.00);

        $batches = self::batches($item);
        $this->assertCount(1, $batches);
        $this->assertEqualsWithDelta(0.0, (float)$batches[0]['rest'], 0.001);
        $this->assertEqualsWithDelta(0.0, self::stockOf($item), 0.001);
        $this->assertEqualsWithDelta(self::stockOf($item), self::stockFromBatches($item), 0.001);
    }
}
