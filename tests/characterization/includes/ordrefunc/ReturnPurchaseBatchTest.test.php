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
        $lineCost = (float)self::one('SELECT kostpris FROM ordrelinjer WHERE id = $1', [self::lineId($return['id'])])['kostpris'];
        $this->assertEqualsWithDelta($lineCost, (float)$new['pris'], 0.001, 'priced at the cost the line got');
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

        $this->creditNote($saleLine, $item, 2, 80.00);

        $batches = self::batches($item);
        $this->assertCount(2, $batches);
        $this->assertEqualsWithDelta(0.0, (float)$batches[0]['rest'], 0.001, 'the original batch is untouched');
        $this->assertEqualsWithDelta(2.0, (float)$batches[1]['rest'], 0.001);
        $this->assertEqualsWithDelta(80.0, (float)$batches[1]['pris'], 0.001, 'the original line\'s cost, not the item card\'s 50');
        $this->assertEqualsWithDelta(self::stockOf($item), self::stockFromBatches($item), 0.001, 'the return is counted once');
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
