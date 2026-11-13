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
// 20261111 CL/MJ SST-960 The session id comes from CharacterizationEnv::sessionId(), as master's test database now gives one per run.

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

    public function test_a_sale_is_counted_once_in_lagerstatus(): void
    {
        $item = $this->batchItem();

        $this->boughtAndSold($item, self::$fx->debtor(), 4);

        $this->assertStockAgrees($item, 6.0);
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
}
