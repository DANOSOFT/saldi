<?php
// tests/characterization/includes/ordrefunc/CreditStockCharacterizationTest.test.php
//
// SD-833 (SD-746 core edit 4): what levering() does to stock for credit notes and set lines.
// A credited stock line goes back on stock, unless the line asks for no restock (retur_lager 'of').
// A set's master line draws no stock of the set item; its components carry the stock.
// A stock-neutral line still counts as delivered, and the stock counted from batches stays equal to the item's.
// Batch-controlled items (VG box9) are left to SST-960 (#1102), which makes lagerstatus move once on delivery and return.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261118 CL/SS SD-833 Created.
// 20261118 CL/SS SD-833 Core edit 4: retur_lager 'of' and a set's master line leave stock alone.

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class CreditStockCharacterizationTest extends CharacterizationTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::rows('ALTER TABLE ordrelinjer ADD COLUMN IF NOT EXISTS retur_lager varchar(2)');
        self::rows('ALTER TABLE ordrelinjer ADD COLUMN IF NOT EXISTS kredit_art varchar(6)');
    }

    private function deliver(int $orderId, float $qty): void
    {
        $this->runChildJson('run_order_lifecycle.php', ['deliver', $orderId, $qty, CharacterizationEnv::testDb()]);
    }

    private function invoice(int $orderId): void
    {
        $this->runChildJson('run_order_invoice.php', ['invoice', $orderId, CharacterizationEnv::testDb()]);
    }

    private function receive(int $orderId, float $qty): void
    {
        self::rows('UPDATE ordrelinjer SET leveres = $1 WHERE ordre_id = $2 AND vare_id > 0', [$qty, $orderId]);
        self::runChild('run_modtag_page.php', [$orderId, CharacterizationEnv::SESSION_ID]);
    }

    private static function lineId(int $orderId): int
    {
        return (int)self::one('SELECT id FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0 ORDER BY id LIMIT 1', [$orderId])['id'];
    }

    private static function stockOf(array $item): float
    {
        return (float)self::one('SELECT beholdning FROM varer WHERE id = $1', [$item['id']])['beholdning'];
    }

    private static function lagerStock(array $item, int $lager): float
    {
        $r = self::one('SELECT COALESCE(sum(beholdning), 0) AS b FROM lagerstatus WHERE vare_id = $1 AND lager = $2', [$item['id'], $lager]);
        return (float)$r['b'];
    }

    /** Stock as the batches count it: bought minus sold, the figure "Ret skæve lagertal" compares with the item. */
    private static function batchStock(array $item): float
    {
        $kob = self::one('SELECT COALESCE(sum(antal), 0) AS a FROM batch_kob WHERE vare_id = $1', [$item['id']]);
        $salg = self::one('SELECT COALESCE(sum(antal), 0) AS a FROM batch_salg WHERE vare_id = $1', [$item['id']]);
        return (float)$kob['a'] - (float)$salg['a'];
    }

    private static function delivered(int $lineId): float
    {
        return (float)self::one('SELECT COALESCE(sum(antal), 0) AS a FROM batch_salg WHERE linje_id = $1', [$lineId])['a'];
    }

    private function noRestockCredit(array $item): array
    {
        $sale = $this->boughtAndSoldTwo($item);
        $note = $this->creditNote(self::lineId($sale['id']), $item, 1);
        self::rows("UPDATE ordrelinjer SET retur_lager = 'of', kredit_art = 'antal' WHERE ordre_id = $1", [$note['id']]);
        return $note;
    }

    /** Ten bought at 100 on lager 1, and a sale of two delivered from it. */
    private function boughtAndSoldTwo(array $item): array
    {
        $purchase = self::$fx->creditorOrder(self::$fx->creditor(), [['item' => $item, 'antal' => 10, 'pris' => 100.00]]);
        $this->receive($purchase['id'], 10);
        $sale = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 2]]);
        $this->deliver($sale['id'], 2);
        return $sale;
    }

    private function creditNote(int $saleLineId, array $item, float $qty, array $line = []): array
    {
        $note = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => -$qty] + $line], ['art' => 'DK']);
        self::rows('UPDATE ordrelinjer SET kred_linje_id = $1 WHERE ordre_id = $2', [$saleLineId, $note['id']]);
        return $note;
    }

    public function test_a_quantity_credit_puts_the_unit_back_on_the_lager_it_was_drawn_from(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $sale = $this->boughtAndSoldTwo($item);
        $this->assertEqualsWithDelta(8.0, self::stockOf($item), 0.001);
        $this->assertEqualsWithDelta(8.0, self::lagerStock($item, 1), 0.001);

        $note = $this->creditNote(self::lineId($sale['id']), $item, 1);
        $this->deliver($note['id'], -1);

        $this->assertEqualsWithDelta(9.0, self::stockOf($item), 0.001, 'one unit back on the item');
        $this->assertEqualsWithDelta(9.0, self::lagerStock($item, 1), 0.001, 'one unit back on lager 1');
    }

    public function test_a_credit_line_marked_no_restock_leaves_stock_alone_and_counts_as_delivered(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $note = $this->noRestockCredit($item);
        $this->deliver($note['id'], -1);

        $this->assertEqualsWithDelta(8.0, self::stockOf($item), 0.001, 'the unit does not go back on the item');
        $this->assertEqualsWithDelta(8.0, self::lagerStock($item, 1), 0.001, 'nor on lager 1');
        $this->assertEqualsWithDelta(8.0, self::batchStock($item), 0.001, 'the batches count the same stock');
        $this->assertEqualsWithDelta(-1.0, self::delivered(self::lineId($note['id'])), 0.001, 'the line is returned');
        $line = self::one('SELECT leveret FROM ordrelinjer WHERE id = $1', [self::lineId($note['id'])]);
        $this->assertEqualsWithDelta(-1.0, (float)$line['leveret'], 0.001);
    }

    public function test_a_no_restock_credit_keeps_its_rows_when_it_is_invoiced(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $note = $this->noRestockCredit($item);
        $this->invoice($note['id']);

        $this->assertGreaterThanOrEqual(3, (int)self::one('SELECT status FROM ordrer WHERE id = $1', [$note['id']])['status']);
        $this->assertEqualsWithDelta(8.0, self::stockOf($item), 0.001);
        $this->assertEqualsWithDelta(8.0, self::batchStock($item), 0.001);
        $this->assertEqualsWithDelta(-1.0, self::delivered(self::lineId($note['id'])), 0.001);
    }

    public function test_a_credit_line_marked_restock_goes_back_on_stock(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $sale = $this->boughtAndSoldTwo($item);
        $note = $this->creditNote(self::lineId($sale['id']), $item, 1);
        self::rows("UPDATE ordrelinjer SET retur_lager = 'on', kredit_art = 'antal' WHERE ordre_id = $1", [$note['id']]);
        $this->deliver($note['id'], -1);

        $this->assertEqualsWithDelta(9.0, self::stockOf($item), 0.001);
        $this->assertEqualsWithDelta(9.0, self::batchStock($item), 0.001);
    }

    public function test_without_the_retur_lager_column_a_credit_goes_back_on_stock(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $sale = $this->boughtAndSoldTwo($item);
        $note = $this->creditNote(self::lineId($sale['id']), $item, 1);
        self::rows('ALTER TABLE ordrelinjer DROP COLUMN retur_lager');
        try {
            $this->deliver($note['id'], -1);
        } finally {
            self::rows('ALTER TABLE ordrelinjer ADD COLUMN IF NOT EXISTS retur_lager varchar(2)');
        }

        $this->assertEqualsWithDelta(9.0, self::stockOf($item), 0.001, 'a tenant without the Shopify schema restocks as before');
    }

    public function test_a_set_master_line_draws_no_stock_of_the_set_item(): void
    {
        $component = self::$fx->item(['beholdning' => 10.0]);
        $set = self::$fx->item(['beholdning' => 5.0]);
        self::rows("UPDATE varer SET samlevare = 'on' WHERE id = $1", [$set['id']]);
        $order = self::$fx->order(self::$fx->debtor(), [['item' => $component, 'antal' => 1], ['item' => $set, 'antal' => 1, 'pris' => -10.00]]);
        self::rows("UPDATE ordrelinjer SET saet = 1 WHERE ordre_id = $1", [$order['id']]);
        self::rows("UPDATE ordrelinjer SET samlevare = 'on' WHERE ordre_id = $1 AND vare_id = $2", [$order['id'], $set['id']]);
        $setLine = (int)self::one('SELECT id FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$order['id'], $set['id']])['id'];
        $setBatchStock = self::batchStock($set);

        $this->deliver($order['id'], 1);

        $this->assertEqualsWithDelta(9.0, self::stockOf($component), 0.001, 'the component is drawn');
        $this->assertEqualsWithDelta(5.0, self::stockOf($set), 0.001, 'the set item is not');
        $this->assertEqualsWithDelta($setBatchStock, self::batchStock($set), 0.001, 'nor do its batches move');
        $this->assertEqualsWithDelta(1.0, self::delivered($setLine), 0.001, 'the set line is delivered');
    }
}
