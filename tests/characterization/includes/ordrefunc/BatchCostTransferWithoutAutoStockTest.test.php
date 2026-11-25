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
// 20261118 CL/MMR SST-1008 A project text on the sale line is kept on the cost lines (Codex).
// 20261118 CL/MMR SST-1008 An item group without a purchase account stops the invoice with a message and changes nothing (Codex).
// 20261118 CL/MMR SST-1008 Each case gets an unused item group code (Codex).
// 20261118 CL/MMR SST-1008 In a page the missing purchase account ends the page; a webservice caller gets the message (Codex).
// 20261123 CL/MMR SST-1008 A sale against a purchase reservation posts the cost lines under the sale line's project (Codex).
// 20261123 CL/MMR SST-1008 The reservation test makes the tenant temp folder bogfor_nu() logs to; fixture return shape documented (Codex).

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
    private function batchItem(float $kostpris, bool $purchaseAccount = true): array
    {
        $item = self::$fx->item(['beholdning' => 0.0, 'kostpris' => $kostpris]);
        [$stock, $purchase] = self::$fx->plainAccounts(2);
        $from = self::one("SELECT * FROM grupper WHERE art = 'VG' AND kodenr::text = $1 AND fiscal_year = $2", [(string)$item['gruppe'], self::$regnaar]);
        unset($from['id']);
        // A group code no other row uses in this fiscal year, so every case gets its own group (Codex).
        $free = self::one("SELECT COALESCE(max(kodenr), 0) + 1 AS n FROM grupper WHERE art = 'VG' AND fiscal_year = $1", [self::$regnaar]);
        $from['kodenr'] = (string)max(10080, (int)$free['n']);
        $from['beskrivelse'] = 'Batch-kontrol uden automatisk lagerbogføring (test)';
        $from['box1'] = (string)($from['box1'] ?: $stock);
        $from['box2'] = (string)$stock;
        $from['box3'] = $purchaseAccount ? (string)$purchase : '';
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
        $this->invoiceAndAssertCostMoved('');
    }

    public function test_the_project_text_is_kept_on_the_cost_lines(): void
    {
        $this->invoiceAndAssertCostMoved('Projekt Æblehave');
    }

    private function invoiceAndAssertCostMoved(string $project): void
    {
        $item = $this->batchItem(50.00);
        $this->receive($item);
        $sale = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 4]]);
        if ($project !== '') {
            self::rows('UPDATE ordrelinjer SET projekt = $1 WHERE ordre_id = $2', [$project, $sale['id']]);
        }

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
        if ($project !== '') {
            $projects = self::rows('SELECT DISTINCT projekt FROM transaktioner WHERE ordre_id = $1 AND kontonr::text IN ($2, $3)', [$sale['id'], $item['stock'], $item['purchase']]);
            $this->assertSame([$project], array_column($projects, 'projekt'), 'the project is stored as typed');
        }
    }

    /**
     * A sale of 4 delivered against an open purchase order (reservation, nothing received yet) whose line already has
     * a used-up batch. The purchase line is on project «BUY» and the sale line on $saleProject.
     *
     * @return array{
     *   0: array{
     *     id: int,         The item (varer.id); the other keys come from Fixtures::item().
     *     gruppe: int,     Its own item group (VG kodenr) with batch control.
     *     stock: string,   The group's stock account (box2).
     *     purchase: string, The group's purchase account (box3).
     *   },
     *   1: array{
     *     id: int,         The sale order (ordrer.id); the other keys come from Fixtures::order().
     *   },
     * }
     */
    private function saleAgainstPurchaseReservation(string $saleProject): array
    {
        $item = $this->batchItem(50.00);
        $purchase = self::$fx->creditorOrder(self::$fx->creditor(), [['item' => $item, 'antal' => 10, 'pris' => 50.00]]);
        $purchaseLine = self::one('SELECT id FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$purchase['id'], $item['id']]);
        self::rows("UPDATE ordrelinjer SET projekt = 'BUY' WHERE id = $1", [$purchaseLine['id']]);
        self::rows(
            'INSERT INTO batch_kob (linje_id, vare_id, ordre_id, pris, antal, rest, lager, variant_id) VALUES ($1, $2, $3, 50, 0, 0, 0, 0)',
            [$purchaseLine['id'], $item['id'], $purchase['id']]
        );
        $sale = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 4]]);
        $saleLine = self::one('SELECT id FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$sale['id'], $item['id']]);
        self::rows('UPDATE ordrelinjer SET projekt = $1 WHERE id = $2', [$saleProject, $saleLine['id']]);
        // As «Reserver» on the sale line does for goods on order: batch_salg_id = minus the sale line until delivery.
        self::rows(
            'INSERT INTO reservation (linje_id, vare_id, batch_salg_id, antal, lager) VALUES ($1, $2, $3, 4, 0)',
            [$purchaseLine['id'], $item['id'], -(int)$saleLine['id']]
        );
        return [$item, $sale];
    }

    public function test_a_sale_against_a_purchase_reservation_posts_the_cost_under_the_sale_project(): void
    {
        // bogfor_nu() appends to ../temp/<tenant db>/hmlog.log. A receipt through modtag creates that folder; this
        // fixture never receives, so the test makes it, and removes it again if it was not there (Codex).
        $tempDir = dirname(__DIR__, 4) . '/temp/' . CharacterizationEnv::testDb();
        $madeTempDir = !is_dir($tempDir) && mkdir($tempDir, 0700, true);
        try {
            $this->assertDirectoryExists($tempDir, 'the tenant folder bogfor_nu() writes its log to');
            foreach (['SALE', 'BUY'] as $saleProject) {
                $this->invoiceAgainstPurchaseReservation($saleProject);
            }
        } finally {
            if ($madeTempDir) {
                // The logs written there include hidden ones (.ht_select.log), so not glob('*').
                foreach (array_diff(scandir($tempDir) ?: [], ['.', '..']) as $name) {
                    if (is_file($tempDir . '/' . $name)) {
                        unlink($tempDir . '/' . $name);
                    }
                }
                rmdir($tempDir);
            }
        }
    }

    private function invoiceAgainstPurchaseReservation(string $saleProject): void
    {
        [$item, $sale] = $this->saleAgainstPurchaseReservation($saleProject);

        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $sale['id'], CharacterizationEnv::testDb()]);

        $this->assertSame('OK', $out['bogfor'], "the invoice is booked (sale project $saleProject)");
        $rows = self::rows(
            'SELECT kontonr::text AS konto, debet, kredit, projekt FROM transaktioner WHERE ordre_id = $1 AND kontonr::text IN ($2, $3) ORDER BY kontonr',
            [$sale['id'], $item['stock'], $item['purchase']]
        );
        $this->assertCount(2, $rows, "both cost lines are posted (sale project $saleProject)");
        $byAccount = array_column($rows, null, 'konto');
        $this->assertEqualsWithDelta(200.0, (float)$byAccount[$item['stock']]['kredit'], 0.001, 'four at cost 50 leave the stock account');
        $this->assertEqualsWithDelta(200.0, (float)$byAccount[$item['purchase']]['debet'], 0.001, 'and go to the purchase account');
        $this->assertSame([$saleProject, $saleProject], array_column($rows, 'projekt'), "under the sale line's project");
    }

    /** A sale of 4 of an item whose group has no purchase account, and a reader of its stock. */
    private function saleWithoutPurchaseAccount(): array
    {
        $item = $this->batchItem(50.00, false);
        $this->receive($item);
        $sale = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => 4]]);
        $stock = static fn (): array => [
            (float)self::one('SELECT beholdning FROM varer WHERE id = $1', [$item['id']])['beholdning'],
            (float)self::one('SELECT COALESCE(sum(beholdning), 0) AS b FROM lagerstatus WHERE vare_id = $1', [$item['id']])['b'],
            (float)self::one('SELECT COALESCE(sum(rest), 0) AS r FROM batch_kob WHERE vare_id = $1', [$item['id']])['r'],
        ];
        return [$item, $sale, $stock];
    }

    private function assertNothingPosted(array $sale, callable $stock, array $before): void
    {
        $this->assertSame(0, (int)self::one('SELECT count(*) AS n FROM transaktioner WHERE ordre_id = $1', [$sale['id']])['n'], 'nothing is posted');
        $this->assertSame(0, (int)self::one('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1 AND posnr < 0', [$sale['id']])['n'], 'no cost lines are left');
        $this->assertSame($before, $stock(), 'the stock is unchanged');
        $this->assertNotSame('3', trim((string)self::one('SELECT status FROM ordrer WHERE id = $1', [$sale['id']])['status']), 'the order is not invoiced');
    }

    public function test_a_group_without_a_purchase_account_stops_the_invoice_and_changes_nothing(): void
    {
        [$item, $sale, $stock] = $this->saleWithoutPurchaseAccount();
        $before = $stock();

        $out = $this->runChildJson('run_order_invoice.php', ['invoice-webservice', $sale['id'], CharacterizationEnv::testDb()]);

        $this->assertSame("Varegruppe {$item['gruppe']} mangler kontonummer for varekøb", $out['bogfor'], 'a webservice caller gets the message back');
        $this->assertNothingPosted($sale, $stock, $before);
    }

    public function test_in_a_page_the_missing_purchase_account_ends_the_page_like_a_database_error(): void
    {
        [$item, $sale, $stock] = $this->saleWithoutPurchaseAccount();
        $before = $stock();

        $res = self::runChild('run_order_invoice.php', ['invoice', $sale['id'], CharacterizationEnv::testDb()]);

        $this->assertStringContainsString("Varegruppe {$item['gruppe']} mangler kontonummer for varek", $res['stdout'], 'the page shows the message');
        $this->assertStringNotContainsString('"bogfor"', $res['stdout'], 'and stops: the caller never gets an answer to go on with (massefakt.php would print the order)');
        $this->assertNothingPosted($sale, $stock, $before);
    }
}
