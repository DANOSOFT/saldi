<?php
// tests/characterization/includes/ordrefunc/CreditStockCharacterizationTest.test.php
//
// SD-833 (SD-746 core edit 4): what levering() does to stock for credit notes and set lines.
// A credited stock line goes back on stock, unless the line asks for no restock (retur_lager 'of') or is a dekort.
// A set's master line draws no stock of the set item; its components carry the stock.
// A stock-neutral line still counts as delivered, and the stock counted from batches stays equal to the item's.
// Batch-controlled items (VG box9) are left to SST-960 (#1102), which makes lagerstatus move once on delivery and return.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261118 CL/SS SD-833 Created.
// 20261118 CL/SS SD-833 Core edit 4: retur_lager 'of' and a set's master line leave stock alone.
// 20261121 CL/SS SD-833 review: a credit line held to what is left puts back only that many.
// 20261123 CDX/NTR Use the test class-owned database and session.

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
        self::runChild('run_modtag_page.php', [$orderId, CharacterizationEnv::sessionId()]);
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

    public function test_a_no_restock_credit_invoiced_on_another_day_dates_its_batch_with_the_invoice(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $note = $this->noRestockCredit($item);
        $this->deliver($note['id'], -1);
        $this->assertNull(self::one('SELECT bk.fakturadate FROM batch_salg bs JOIN batch_kob bk ON bk.id = bs.batch_kob_id WHERE bs.linje_id = $1', [self::lineId($note['id'])])['fakturadate'], 'no invoice date before the invoice, like the row');
        self::rows("UPDATE ordrer SET ordredate = date_trunc('month', current_date)::date - 1 WHERE id = $1", [$note['id']]);
        $this->invoice($note['id']);

        $dates = self::one('SELECT bs.fakturadate AS salg, bk.fakturadate AS kob FROM batch_salg bs JOIN batch_kob bk ON bk.id = bs.batch_kob_id WHERE bs.linje_id = $1', [self::lineId($note['id'])]);
        $this->assertNotEmpty($dates['salg']);
        $this->assertSame($dates['salg'], $dates['kob'], 'stock counted by invoice date nets to 0 too');
    }

    public function test_a_no_restock_credit_posts_no_stock_value_where_stock_value_is_kept(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $group = self::one('SELECT gruppe FROM varer WHERE id = $1', [$item['id']])['gruppe'];
        $vg = self::one("SELECT id, box1, box2 FROM grupper WHERE art = 'VG' AND kodenr = $1 ORDER BY id DESC LIMIT 1", [$group]);
        $ra = self::one("SELECT id, box2 FROM grupper WHERE art = 'RA' ORDER BY kodenr DESC LIMIT 1");
        $note = $this->noRestockCredit($item);
        self::rows("UPDATE grupper SET box1 = '1610', box2 = '1660' WHERE id = $1", [$vg['id']]);
        self::rows("UPDATE grupper SET box2 = '2014' WHERE id = $1", [$ra['id']]);
        try {
            $this->invoice($note['id']);
        } finally {
            self::rows('UPDATE grupper SET box2 = $1 WHERE id = $2', [$ra['box2'], $ra['id']]);
            self::rows('UPDATE grupper SET box1 = $1, box2 = $2 WHERE id = $3', [$vg['box1'], $vg['box2'], $vg['id']]);
        }

        $this->assertGreaterThanOrEqual(3, (int)self::one('SELECT status FROM ordrer WHERE id = $1', [$note['id']])['status']);
        $stockValue = self::rows("SELECT kontonr FROM transaktioner WHERE ordre_id = $1 AND kontonr IN (1610, 1660)", [$note['id']]);
        $this->assertSame([], $stockValue, 'no stock value posting: nothing came back on stock');
    }

    public function test_a_dekort_line_leaves_stock_alone(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $sale = $this->boughtAndSoldTwo($item);
        $note = $this->creditNote(self::lineId($sale['id']), $item, 1);
        self::rows("UPDATE ordrelinjer SET kredit_art = 'dekort' WHERE ordre_id = $1", [$note['id']]);
        $this->deliver($note['id'], -1);

        $this->assertEqualsWithDelta(8.0, self::stockOf($item), 0.001, 'the customer keeps the unit');
        $this->assertEqualsWithDelta(8.0, self::batchStock($item), 0.001);
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

    public function test_a_credit_line_held_to_what_is_left_puts_back_only_that_many(): void
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => 50.00]);
        $purchase = self::$fx->creditorOrder(self::$fx->creditor(), [['item' => $item, 'antal' => 10, 'pris' => 100.00]]);
        $this->receive($purchase['id'], 10);
        $sale = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 4]]);
        $this->deliver($sale['id'], 4);
        $first = $this->creditNote(self::lineId($sale['id']), $item, 2);
        $second = $this->creditNote(self::lineId($sale['id']), $item, 4);
        self::rows("UPDATE ordrelinjer SET retur_lager = 'on', kredit_art = 'antal', leveres = antal WHERE ordre_id IN ($1, $2)", [$first['id'], $second['id']]);
        self::rows('UPDATE ordrer SET kred_ord_id = $1, status = 1 WHERE id IN ($2, $3)', [$sale['id'], $first['id'], $second['id']]);
        $this->deliver($first['id'], -2);
        $this->assertEqualsWithDelta(8.0, self::stockOf($item), 0.001);

        $this->runChildJson('run_credit_service.php', ['hold_and_deliver', $second['id'], CharacterizationEnv::testDb()]);

        $this->assertEqualsWithDelta(-2.0, self::delivered(self::lineId($second['id'])), 0.001, 'the two still sold, not the four asked for');
        $this->assertEqualsWithDelta(10.0, self::stockOf($item), 0.001);
        $this->assertEqualsWithDelta(10.0, self::batchStock($item), 0.001);
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
