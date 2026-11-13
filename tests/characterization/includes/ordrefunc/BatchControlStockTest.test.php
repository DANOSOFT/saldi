<?php
// tests/characterization/includes/ordrefunc/BatchControlStockTest.test.php
//
// SST-960: an item with batch control (the item group's box9) is counted once in lagerstatus when it is
// delivered or returned. linjeopdat() updates lagerstatus itself; batch_salg_lev() and krediter() used to
// do it again. After a sale and after a credit note, the item card, the stock status and the batches agree.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261110 CL/MJ SST-960 Created.
// 20261110 CL/MJ SST-924 Returns typed by hand and "Opret som indkøb" go back on a batch of the same item.
// 20261111 CL/MJ SST-924 A typed return passes over the batch of a purchase that is not received yet.
// 20261111 CL/MJ SST-960 The session id comes from CharacterizationEnv::sessionId(), as master's test database now gives one per run.
// 20261112 CL/MJ SST-924 A credit note against a sale from a batch not received yet; "Opret som indkøb" names the new batch (CodeRabbit).

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class BatchControlStockTest extends CharacterizationTestCase
{
    /** An item group with stock and batch control, copied from the fixture's stock group. */
    private static int $batchGroup = 0;

    private function deliver(int $orderId, float $qty): array
    {
        return $this->runChildJson('run_order_lifecycle.php', ['deliver', $orderId, $qty, CharacterizationEnv::testDb()]);
    }

    /** Receives the purchase with a batch number on each line, as batch control asks for. */
    private function receive(int $orderId, float $qty): void
    {
        self::rows("UPDATE ordrelinjer SET leveres = $1, batch_batch_no = 'BATCH-' || id WHERE ordre_id = $2 AND vare_id > 0", [$qty, $orderId]);
        self::runChild('run_modtag_page.php', [$orderId, CharacterizationEnv::sessionId()]);
    }

    private static function lineId(int $orderId): int
    {
        return (int)self::one('SELECT id FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$orderId])['id'];
    }

    /** A stock item in an item group with batch control. */
    private function batchItem(): array
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        if (self::$batchGroup === 0) {
            $from = self::one("SELECT * FROM grupper WHERE art = 'VG' AND kodenr::text = $1 AND fiscal_year = $2", [(string)$item['gruppe'], self::$regnaar]);
            unset($from['id']);
            $from['kodenr'] = (string)(9240 + random_int(0, 99));
            $from['beskrivelse'] = 'Batch-kontrol (test)';
            $from['box9'] = 'on';
            $cols = array_keys($from);
            $marks = implode(', ', array_map(fn (int $i): string => '$' . ($i + 1), array_keys($cols)));
            self::rows('INSERT INTO grupper (' . implode(', ', $cols) . ") VALUES ($marks)", array_values($from));
            self::$batchGroup = (int)$from['kodenr'];
        }
        self::rows('UPDATE varer SET gruppe = $1 WHERE id = $2', [self::$batchGroup, $item['id']]);
        $item['gruppe'] = self::$batchGroup;
        return $item;
    }

    /** Ten bought at 100 and $sold of them delivered to $debtor. */
    private function boughtAndSold(array $item, array $debtor, float $sold): array
    {
        $purchase = self::$fx->creditorOrder(self::$fx->creditor(), [['item' => $item, 'antal' => 10, 'pris' => 100.00]]);
        $this->receive($purchase['id'], 10);
        $sale = self::$fx->order($debtor, [['item' => $item, 'antal' => $sold]]);
        $this->deliver($sale['id'], $sold);
        return ['sale' => $sale, 'batch' => self::rows('SELECT id, rest FROM batch_kob WHERE vare_id = $1 ORDER BY id', [$item['id']])[0]];
    }

    /** @return list<array<string,string>> */
    private static function batches(array $item): array
    {
        return self::rows('SELECT id, antal, rest, lager FROM batch_kob WHERE vare_id = $1 ORDER BY id', [$item['id']]);
    }

    private static function rest(int $batchId): float
    {
        return (float)self::one('SELECT rest FROM batch_kob WHERE id = $1', [$batchId])['rest'];
    }

    /** The item card, the stock status and the batches must give the same stock. */
    private function assertStockAgrees(array $item, float $expected): void
    {
        $card = (float)self::one('SELECT beholdning FROM varer WHERE id = $1', [$item['id']])['beholdning'];
        $status = (float)self::one('SELECT COALESCE(sum(beholdning), 0) AS b FROM lagerstatus WHERE vare_id = $1', [$item['id']])['b'];
        $rest = (float)self::one('SELECT COALESCE(sum(rest), 0) AS r FROM batch_kob WHERE vare_id = $1', [$item['id']])['r'];
        $this->assertEqualsWithDelta($expected, $card, 0.001, 'varer.beholdning');
        $this->assertEqualsWithDelta($expected, $status, 0.001, 'lagerstatus, counted once');
        $this->assertEqualsWithDelta($expected, $rest, 0.001, 'the sum of the batches\' rest');
    }

    private function manualReturn(array $debtor, array $item, float $qty): array
    {
        $return = self::$fx->order($debtor, [['item' => $item, 'antal' => -$qty]]);
        self::rows('UPDATE ordrelinjer SET kred_linje_id = 0 WHERE ordre_id = $1', [$return['id']]); # as debitor/ordre.php saves a typed line
        $this->deliver($return['id'], -$qty);
        return $return;
    }

    public function test_a_sale_is_counted_once_in_lagerstatus(): void
    {
        $item = $this->batchItem();

        $this->boughtAndSold($item, self::$fx->debtor(), 4);

        $this->assertStockAgrees($item, 6.0);
    }

    public function test_a_manual_return_goes_back_on_the_batch_the_item_was_sold_from(): void
    {
        $debtor = self::$fx->debtor();
        $item = $this->batchItem();
        ['batch' => $batch] = $this->boughtAndSold($item, $debtor, 4);
        $other = $this->batchItem();
        ['batch' => $otherBatch] = $this->boughtAndSold($other, self::$fx->debtor(), 3);

        $return = $this->manualReturn($debtor, $item, 2);

        $this->assertEqualsWithDelta(8.0, self::rest((int)$batch['id']), 0.001, 'six left plus the two returned');
        $this->assertEqualsWithDelta(7.0, self::rest((int)$otherBatch['id']), 0.001, 'another item\'s batch is not touched');
        $row = self::one('SELECT batch_kob_id, antal FROM batch_salg WHERE linje_id = $1', [self::lineId($return['id'])]);
        $this->assertSame((int)$batch['id'], (int)$row['batch_kob_id'], 'the return row names the batch');
        $this->assertEqualsWithDelta(-2.0, (float)$row['antal'], 0.001);
        $this->assertStockAgrees($item, 8.0);
    }

    /** What MEDSHOP hit: a delivery row elsewhere with linje_id 0 and no batch stopped the delivery. */
    public function test_a_manual_return_is_delivered_when_rows_without_a_line_or_batch_exist(): void
    {
        $debtor = self::$fx->debtor();
        $item = $this->batchItem();
        ['batch' => $batch] = $this->boughtAndSold($item, $debtor, 4);
        $stray = self::$fx->item();
        self::rows("INSERT INTO batch_salg (batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal) VALUES (0, $1, 0, now(), 0, 1)", [$stray['id']]);

        $return = $this->manualReturn($debtor, $item, 2);

        $this->assertNotNull(self::one('SELECT id FROM batch_salg WHERE linje_id = $1', [self::lineId($return['id'])]), 'the return is delivered');
        $this->assertEqualsWithDelta(8.0, self::rest((int)$batch['id']), 0.001);
        $this->assertStockAgrees($item, 8.0);
    }

    public function test_a_manual_return_of_an_item_never_sold_to_the_customer_uses_the_items_latest_batch(): void
    {
        $item = $this->batchItem();
        ['batch' => $batch] = $this->boughtAndSold($item, self::$fx->debtor(), 4);

        $this->manualReturn(self::$fx->debtor(), $item, 2);

        $this->assertEqualsWithDelta(8.0, self::rest((int)$batch['id']), 0.001);
        $this->assertCount(1, self::batches($item), 'no new batch');
        $this->assertStockAgrees($item, 8.0);
    }

    /** A sale before the purchase is received leaves a batch with rest NULL; a return put there would be lost. */
    public function test_a_manual_return_passes_over_the_batch_of_a_purchase_not_received_yet(): void
    {
        $item = $this->batchItem();
        ['batch' => $batch] = $this->boughtAndSold($item, self::$fx->debtor(), 4);
        self::rows(
            'INSERT INTO batch_kob (linje_id, vare_id, ordre_id, pris, lager, variant_id) SELECT 0, vare_id, 0, 100, lager, 0 FROM batch_kob WHERE id = $1',
            [$batch['id']]
        );

        $this->manualReturn(self::$fx->debtor(), $item, 2);

        $this->assertEqualsWithDelta(8.0, self::rest((int)$batch['id']), 0.001, 'back on the received batch');
        $this->assertCount(2, self::batches($item), 'no new batch');
    }

    /** A batch saved without a warehouse (older data, stock regulation) counts as the main warehouse's. */
    public function test_a_manual_return_finds_a_batch_saved_without_a_warehouse(): void
    {
        $debtor = self::$fx->debtor();
        $item = $this->batchItem();
        ['batch' => $batch] = $this->boughtAndSold($item, $debtor, 4);
        self::rows('UPDATE batch_kob SET lager = NULL WHERE id = $1', [$batch['id']]);

        $this->manualReturn($debtor, $item, 2);

        $this->assertEqualsWithDelta(8.0, self::rest((int)$batch['id']), 0.001);
        $this->assertCount(1, self::batches($item), 'no new batch');
    }

    public function test_a_manual_return_of_an_item_without_batches_gets_a_new_batch(): void
    {
        $item = $this->batchItem();

        $return = $this->manualReturn(self::$fx->debtor(), $item, 2);

        $batches = self::batches($item);
        $this->assertCount(1, $batches, 'a batch for the returned units');
        $this->assertEqualsWithDelta(2.0, (float)$batches[0]['rest'], 0.001);
        $row = self::one('SELECT batch_kob_id FROM batch_salg WHERE linje_id = $1', [self::lineId($return['id'])]);
        $this->assertSame((int)$batches[0]['id'], (int)$row['batch_kob_id']);
        $this->assertStockAgrees($item, 2.0);
    }

    /** "Opret som indkøb" in the batch window (kred_linje_id -1): the return becomes a batch of its own. */
    public function test_a_return_booked_as_a_purchase_in_the_batch_window_gets_a_new_batch(): void
    {
        $debtor = self::$fx->debtor();
        $item = $this->batchItem();
        ['batch' => $batch] = $this->boughtAndSold($item, $debtor, 4);
        $return = self::$fx->order($debtor, [['item' => $item, 'antal' => -2]]);
        self::rows('UPDATE ordrelinjer SET kred_linje_id = -1 WHERE ordre_id = $1', [$return['id']]);

        $this->deliver($return['id'], -2);

        $this->assertEqualsWithDelta(6.0, self::rest((int)$batch['id']), 0.001, 'the sold batch is left alone');
        $batches = self::batches($item);
        $this->assertCount(2, $batches);
        $row = self::one('SELECT batch_kob_id FROM batch_salg WHERE linje_id = $1', [self::lineId($return['id'])]);
        $this->assertSame((int)$batches[1]['id'], (int)$row['batch_kob_id'], 'the return row names the new batch');
        $this->assertStockAgrees($item, 8.0);
    }

    /** A credit note against the sale line (Kreditér, or the sale chosen in the batch window). */
    public function test_a_credit_note_puts_the_units_back_and_counts_them_once(): void
    {
        $debtor = self::$fx->debtor();
        $item = $this->batchItem();
        ['sale' => $sale, 'batch' => $batch] = $this->boughtAndSold($item, $debtor, 4);
        $note = self::$fx->order($debtor, [['item' => $item, 'antal' => -2]], ['art' => 'DK']);
        self::rows('UPDATE ordrelinjer SET kred_linje_id = $1 WHERE ordre_id = $2', [self::lineId($sale['id']), $note['id']]);

        $this->deliver($note['id'], -2);

        $this->assertEqualsWithDelta(8.0, self::rest((int)$batch['id']), 0.001);
        $this->assertStockAgrees($item, 8.0);
    }

    /** The sale took units from a purchase not received yet (rest NULL); the credit note goes on a received batch. */
    public function test_a_credit_note_against_a_sale_from_a_batch_not_received_yet_takes_a_received_batch(): void
    {
        $debtor = self::$fx->debtor();
        $item = $this->batchItem();
        ['sale' => $sale, 'batch' => $batch] = $this->boughtAndSold($item, $debtor, 4);
        self::rows(
            'INSERT INTO batch_kob (linje_id, vare_id, ordre_id, pris, lager, variant_id) SELECT 0, vare_id, 0, 100, lager, 0 FROM batch_kob WHERE id = $1',
            [$batch['id']]
        );
        $pending = (int)self::one('SELECT max(id) AS id FROM batch_kob WHERE vare_id = $1', [$item['id']])['id'];
        self::rows('UPDATE batch_salg SET batch_kob_id = $1 WHERE linje_id = $2', [$pending, self::lineId($sale['id'])]);
        $note = self::$fx->order($debtor, [['item' => $item, 'antal' => -2]], ['art' => 'DK']);
        self::rows('UPDATE ordrelinjer SET kred_linje_id = $1 WHERE ordre_id = $2', [self::lineId($sale['id']), $note['id']]);

        $this->deliver($note['id'], -2);

        $this->assertEqualsWithDelta(8.0, self::rest((int)$batch['id']), 0.001, 'six left plus the two returned');
        $this->assertNull(self::one('SELECT rest FROM batch_kob WHERE id = $1', [$pending])['rest'], 'the batch not received yet is left alone');
        $row = self::one('SELECT batch_kob_id FROM batch_salg WHERE linje_id = $1', [self::lineId($note['id'])]);
        $this->assertSame((int)$batch['id'], (int)$row['batch_kob_id'], 'the return row names the received batch');
    }
}
