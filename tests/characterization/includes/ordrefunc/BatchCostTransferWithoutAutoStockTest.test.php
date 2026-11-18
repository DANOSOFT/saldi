<?php
// tests/characterization/includes/ordrefunc/BatchCostTransferWithoutAutoStockTest.test.php
//
// SST-1008: in a fiscal year that started before 2015 Saldi does not post the stock value automatically. When such
// a year invoices an item with batch control, batch_salg() moves the cost from the group's stock account to its
// purchase account with two order lines (posnr -1). Those two INSERTs named 7 columns and gave 8 values, so the
// invoice stopped on a database error. Now they name the saet column like the lines for stock items without batches.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261118 CL/MMR SST-1008 Created.

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class BatchCostTransferWithoutAutoStockTest extends CharacterizationTestCase
{
    private static string $startYear = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // The fiscal year's start year (grupper RA box2) decides automatic stock posting: 2015 or later = on.
        $ra = self::one("SELECT box2 FROM grupper WHERE art = 'RA' AND kodenr::text = $1", [(string)self::$regnaar]);
        self::$startYear = (string)($ra['box2'] ?? '');
        self::rows("UPDATE grupper SET box2 = '2014' WHERE art = 'RA' AND kodenr::text = $1", [(string)self::$regnaar]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$startYear !== '') {
            self::rows("UPDATE grupper SET box2 = $1 WHERE art = 'RA' AND kodenr::text = $2", [self::$startYear, (string)self::$regnaar]);
        }
        parent::tearDownAfterClass();
    }

    /** A stock item in a group with batch control and a stock account (box2) and a purchase account (box3). */
    private function batchItem(float $kostpris): array
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => $kostpris]);
        [$stock, $purchase] = self::$fx->plainAccounts(2);
        $from = self::one("SELECT * FROM grupper WHERE art = 'VG' AND kodenr::text = $1 AND fiscal_year = $2", [(string)$item['gruppe'], self::$regnaar]);
        unset($from['id']);
        $from['kodenr'] = (string)(10080 + random_int(0, 899));
        $from['beskrivelse'] = 'Batch-kontrol uden automatisk lagerbogføring (test)';
        $from['box1'] = (string)($from['box1'] ?: $stock);
        $from['box2'] = (string)$stock;
        $from['box3'] = (string)$purchase;
        $from['box8'] = 'on';
        $from['box9'] = 'on';
        $cols = array_keys($from);
        $marks = implode(', ', array_map(fn (int $i): string => '$' . ($i + 1), array_keys($cols)));
        self::rows('INSERT INTO grupper (' . implode(', ', $cols) . ") VALUES ($marks)", array_values($from));
        self::rows('UPDATE varer SET gruppe = $1 WHERE id = $2', [(int)$from['kodenr'], $item['id']]);
        $item['gruppe'] = (int)$from['kodenr'];
        $item['stock'] = (string)$stock;
        $item['purchase'] = (string)$purchase;
        return $item;
    }

    /** Ten received on a purchase with a batch number, as batch control asks for. */
    private function receive(array $item): void
    {
        $purchase = self::$fx->creditorOrder(self::$fx->creditor(), [['item' => $item, 'antal' => 10, 'pris' => 50.00]]);
        self::rows("UPDATE ordrelinjer SET leveres = 10, batch_batch_no = 'BATCH-' || id WHERE ordre_id = $1 AND vare_id > 0", [$purchase['id']]);
        self::runChild('run_modtag_page.php', [$purchase['id'], CharacterizationEnv::sessionId()]);
    }

    public function test_invoicing_a_batch_item_moves_the_cost_without_a_database_error(): void
    {
        $item = $this->batchItem(50.00);
        $this->receive($item);
        $sale = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 4]]);

        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $sale['id'], CharacterizationEnv::testDb()]);

        $this->assertSame('OK', $out['bogfor'], 'the invoice is booked');
        // bogfor_nu() posts the two cost lines (posnr -1) and then deletes them from ordrelinjer.
        $moved = static fn (string $account): array => self::one(
            'SELECT COALESCE(sum(debet), 0) AS debet, COALESCE(sum(kredit), 0) AS kredit FROM transaktioner WHERE ordre_id = $1 AND kontonr::text = $2',
            [$sale['id'], $account]
        );
        $stock = $moved($item['stock']);
        $purchase = $moved($item['purchase']);
        $this->assertEqualsWithDelta(200.0, (float)$stock['kredit'] - (float)$stock['debet'], 0.001, 'four at cost 50 leave the stock account');
        $this->assertEqualsWithDelta(200.0, (float)$purchase['debet'] - (float)$purchase['kredit'], 0.001, 'and go to the purchase account');
    }
}
