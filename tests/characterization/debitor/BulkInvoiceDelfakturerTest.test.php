<?php
// tests/characterization/debitor/BulkInvoiceDelfakturerTest.test.php
//
// Bulk invoicing (debitor/massefakt.php?valg=ordrer) with Delfakturer on (grupper MFAKT box2) splits an order with
// more ordered than in stock: delfakturer() keeps the part in stock on the order and moves the rest to a new order with
// the same order number, then writes both orders' totals. Until audit finding p1-sales-F2 that write stopped PHP 8 with
// an ArgumentCountError and the split was rolled back. Now the split reaches the database, and with it two defects of
// the split itself:
//  - each line's quantity was read with (int), so a line of 2,5 units (hours, kg) was cut to 2 on the kept order, and
//    the half unit was on neither order;
//  - the totals were written at 3 decimals. On an order with a VAT-free line momsupdat() keeps the header as written,
//    so the VAT (49,998) and the open item (299,988) differed from the posting (50,00 and 299,99);
//  - with negative stock the order kept ordered - stock, a negative quantity, and the remainder got more than was
//    ordered. The split itself leaves stock negative, as the same run invoices the remainder.
//
// The page is driven as a browser drives it, through support/run_debitor_order_page.php. find_next() does not hold
// the remainder back for want of stock (its stock check never runs), so the same run invoices it too.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261119 CL/ASR Created.
//                 A second run on an item the first run left at negative stock is checked.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class BulkInvoiceDelfakturerTest extends CharacterizationTestCase
{
    /** grupper.kodenr of an item group whose items are VAT-free (VG box7), made once per class */
    private static ?int $vatFreeGroup = null;
    /** @var list<array{0:string, 1:array}> the statements that put back the settings a test changed */
    private array $restore = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$vatFreeGroup = null;
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->restore) as [$sql, $params]) {
            self::rows($sql, $params);
        }
        $this->restore = [];
    }

    /** Delfakturer on in bulk invoicing (grupper MFAKT box2), with no delivery deadline (box3), until tearDown(). */
    private function partialBulkInvoicingOn(): void
    {
        $row = self::one("SELECT id, box2, box3 FROM grupper WHERE art = 'MFAKT' AND kodenr = 1");
        if ($row === null) {
            $row = self::one("INSERT INTO grupper (beskrivelse, kode, kodenr, art, box2, box3) VALUES ('Massefakturering', '', 1, 'MFAKT', '', '') RETURNING id, box2, box3");
            $this->restore[] = ['DELETE FROM grupper WHERE id = $1', [$row['id']]];
        } else {
            $this->restore[] = ['UPDATE grupper SET box2 = $1, box3 = $2 WHERE id = $3', [$row['box2'], $row['box3'], $row['id']]];
        }
        self::rows("UPDATE grupper SET box2 = 'on', box3 = '0' WHERE id = $1", [$row['id']]);
    }

    /** @return array{id:int, varenr:string, salgspris:float, kostpris:float, gruppe:int} an item of a VAT-free item group */
    private static function vatFreeItem(float $price): array
    {
        $item = self::$fx->item(['salgspris' => $price, 'kostpris' => 10.00, 'beholdning' => 100]);
        if (self::$vatFreeGroup === null) {
            $source = self::one("SELECT id FROM grupper WHERE art = 'VG' AND kodenr = $1 AND fiscal_year = $2", [$item['gruppe'], self::$regnaar]);
            $boxes = implode(', ', array_map(static fn(int $n): string => "box$n", range(1, 14)));
            $row = self::one(
                "INSERT INTO grupper (beskrivelse, kode, kodenr, art, fiscal_year, $boxes)
                 SELECT 'chartest momsfri', kode, (SELECT max(kodenr) + 1 FROM grupper WHERE art = 'VG'), art, fiscal_year, "
                . rtrim(str_replace('box7,', "'on',", "$boxes,"), ',') . " FROM grupper WHERE id = $1 RETURNING kodenr",
                [(int)$source['id']]
            );
            self::$vatFreeGroup = (int)$row['kodenr'];
        }
        self::rows('UPDATE varer SET gruppe = $1 WHERE id = $2', [self::$vatFreeGroup, $item['id']]);
        $item['gruppe'] = self::$vatFreeGroup;
        return $item;
    }

    private function bulkInvoice(int $after): void
    {
        $res = $this->runChildJson(
            'run_debitor_order_page.php',
            ["valg=ordrer&ordre_id=$after", CharacterizationEnv::sessionId(), '', 'debitor/massefakt.php']
        );
        $this->assertStringNotContainsString('Fatal error', $res['html'], 'bulk invoicing crashed');
    }

    /**
     * The id bulk invoicing starts after, so it leaves the orders of earlier tests alone. The page starts a run without
     * an ordre_id after order 1, so a closed order is made first: the test's order is never order 1.
     */
    private static function startAfter(): int
    {
        self::$fx->order(self::$fx->debtor(), [], ['status' => 9]);
        return (int)self::one('SELECT coalesce(max(id), 0) AS id FROM ordrer')['id'];
    }

    /** The quantity of an item on an order. */
    private static function quantity(int $orderId, int $itemId): float
    {
        return (float)(self::one('SELECT sum(antal) AS antal FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$orderId, $itemId])['antal'] ?? 0);
    }

    /** @return list<array{id:string, status:string, sum:string, moms:string}> the orders with the order's number, oldest first */
    private static function splitOrders(int $ordrenr): array
    {
        return self::rows("SELECT id, status, sum, moms FROM ordrer WHERE ordrenr = $1 AND art = 'DO' ORDER BY id", [$ordrenr]);
    }

    /**
     * What an invoice posted, in kr: revenue on its lines' sales account, output VAT on the sales VAT account, and the
     * receivable (the voucher's debit, which must equal its credit).
     *
     * @return array{0:float,1:float,2:float}
     */
    private function posted(int $orderId): array
    {
        $line = self::one('SELECT bogf_konto FROM ordrelinjer WHERE ordre_id = $1 AND bogf_konto > 0 ORDER BY id LIMIT 1', [$orderId]);
        $vatAccount = (int)self::one("SELECT box1 FROM grupper WHERE art = 'SM' ORDER BY kodenr LIMIT 1")['box1'];
        $credit = fn (int $kontonr): float => round((float)self::one(
            'SELECT coalesce(sum(kredit), 0) - coalesce(sum(debet), 0) AS amount FROM transaktioner WHERE ordre_id = $1 AND kontonr = $2',
            [$orderId, $kontonr]
        )['amount'], 2);
        $total = self::one('SELECT coalesce(sum(debet), 0) AS debet, coalesce(sum(kredit), 0) AS kredit FROM transaktioner WHERE ordre_id = $1', [$orderId]);
        $this->assertEqualsWithDelta((float)$total['debet'], (float)$total['kredit'], 0.005, 'the voucher balances');
        return [$credit((int)$line['bogf_konto']), $credit($vatAccount), round((float)$total['debet'], 2)];
    }

    /** The invoice's open item, unrounded, as the customer's statement and payment matching read it. */
    private static function openItem(int $orderId): float
    {
        return (float)self::one('SELECT amount FROM openpost WHERE refnr = $1', [$orderId])['amount'];
    }

    /**
     * Item A, 2 ordered at 100,00 with 1 in stock, and item B, 2,5 units at 100,00 with 100 in stock. The order keeps
     * A 1 and all of B: 350,00 + 87,50 VAT. The remainder is A 1: 100,00 + 25,00. It kept 2 of B (300,00 + 75,00), and
     * the 0,5 unit was on neither order.
     */
    public function test_a_split_keeps_a_fractional_quantity_on_the_order(): void
    {
        $this->partialBulkInvoicingOn();
        $after = self::startAfter();
        $a = self::$fx->item(['salgspris' => 100.00, 'kostpris' => 40.00, 'beholdning' => 1]);
        $b = self::$fx->item(['salgspris' => 100.00, 'kostpris' => 40.00, 'beholdning' => 100]);
        $order = self::$fx->order(self::$fx->debtor(), [['item' => $a, 'antal' => 2], ['item' => $b, 'antal' => 2.5]]);

        $this->bulkInvoice($after);

        $orders = self::splitOrders($order['ordrenr']);
        $this->assertCount(2, $orders, 'the order is split in two');
        [$kept, $rest] = $orders;
        $this->assertSame((string)$order['id'], $kept['id']);
        $quantity = fn (int $orderId, int $itemId): float => (float)(self::one(
            'SELECT sum(antal) AS antal FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$orderId, $itemId]
        )['antal'] ?? 0);
        $this->assertEqualsWithDelta(1.0, $quantity($order['id'], $a['id']), 0.0001, 'the order keeps the unit of A in stock');
        $this->assertEqualsWithDelta(2.5, $quantity($order['id'], $b['id']), 0.0001, 'and all 2,5 of B (it kept 2)');
        $this->assertEqualsWithDelta(1.0, $quantity((int)$rest['id'], $a['id']), 0.0001, 'the remainder has the other unit of A');
        $this->assertEqualsWithDelta(0.0, $quantity((int)$rest['id'], $b['id']), 0.0001, 'and none of B');

        $this->assertGreaterThanOrEqual(3, (int)$kept['status'], 'the order is invoiced');
        $this->assertEqualsWithDelta(350.00, (float)$kept['sum'], 0.0001);
        $this->assertEqualsWithDelta(87.50, (float)$kept['moms'], 0.0001);
        $this->assertSame([350.0, 87.5, 437.5], $this->posted($order['id']), 'revenue 350,00, VAT 87,50, receivable 437,50 (it was 300,00 / 75,00 / 375,00)');
        $this->assertEqualsWithDelta(437.50, self::openItem($order['id']), 0.0001);
        $this->assertGreaterThanOrEqual(3, (int)$rest['status'], 'the same run invoices the remainder');
        $this->assertSame([100.0, 25.0, 125.0], $this->posted((int)$rest['id']), 'revenue 100,00, VAT 25,00, receivable 125,00');
        $stock = (float)self::one('SELECT beholdning FROM varer WHERE id = $1', [$b['id']])['beholdning'];
        $this->assertEqualsWithDelta(97.5, $stock, 0.0001, 'the stock of B goes down by 2,5 (it went down by 2)');
    }

    /**
     * Item A, 2 ordered at 100,00 with 1 in stock, item B, 3 x 33,33, and a VAT-free line of 50,00. The order keeps
     * A 1, B 3 and the VAT-free line: 249,99 with VAT 25,00 + 24,9975 = 49,9975, so 50,00 and an open item of 299,99,
     * as the posting. It wrote VAT 49,998, and the open item was 299,988 against a posting of 299,99.
     */
    public function test_a_split_order_with_a_vat_free_line_has_its_totals_in_whole_ore(): void
    {
        $this->partialBulkInvoicingOn();
        $after = self::startAfter();
        $a = self::$fx->item(['salgspris' => 100.00, 'kostpris' => 40.00, 'beholdning' => 1]);
        $b = self::$fx->item(['salgspris' => 33.33, 'kostpris' => 10.00, 'beholdning' => 100]);
        $free = self::vatFreeItem(50.00);
        $order = self::$fx->order(self::$fx->debtor(), [
            ['item' => $a, 'antal' => 2], ['item' => $b, 'antal' => 3], ['item' => $free, 'antal' => 1, 'momssats' => 0],
        ]);
        self::rows("UPDATE ordrelinjer SET momsfri = 'on' WHERE ordre_id = $1 AND vare_id = $2", [$order['id'], $free['id']]);
        self::rows('UPDATE ordrer SET sum = 349.99, moms = 75.00 WHERE id = $1', [$order['id']]);

        $this->bulkInvoice($after);

        $orders = self::splitOrders($order['ordrenr']);
        $this->assertCount(2, $orders, 'the order is split in two');
        [$kept, $rest] = $orders;
        $this->assertGreaterThanOrEqual(3, (int)$kept['status'], 'the order is invoiced');
        $this->assertEqualsWithDelta(249.99, (float)$kept['sum'], 0.0001);
        $this->assertEqualsWithDelta(50.00, (float)$kept['moms'], 0.0001, 'VAT in whole øre (it was 49,998)');
        $this->assertSame([249.99, 50.0, 299.99], $this->posted($order['id']), 'revenue 249,99, VAT 50,00, receivable 299,99');
        $this->assertEqualsWithDelta(299.99, self::openItem($order['id']), 0.0001, 'the open item is the receivable posted (it was 299,988)');
        $this->assertGreaterThanOrEqual(3, (int)$rest['status'], 'the same run invoices the remainder');
        $this->assertEqualsWithDelta(100.00, (float)$rest['sum'], 0.0001);
        $this->assertEqualsWithDelta(25.00, (float)$rest['moms'], 0.0001);
        $this->assertSame([100.0, 25.0, 125.0], $this->posted((int)$rest['id']));
        $this->assertEqualsWithDelta(125.00, self::openItem((int)$rest['id']), 0.0001);
    }

    /**
     * Two bulk runs, each on an order of item A 2 x 100,00 and item B 1 x 100,00. A has 1 in stock and B 100.
     *
     * Run 1 keeps A 1 and B 1 on the order (200,00 + 50,00) and moves A 1 to the remainder (100,00 + 25,00). The same
     * run invoices the remainder, so A's stock ends at -1.
     *
     * Run 2 then has nothing of A in stock: its order keeps B 1 and no A (100,00 + 25,00), and the remainder is A 2
     * (200,00 + 50,00). It kept A -1 (ordered 2 less stock -1 = 3 moved), so its invoice was A -1 + B 1 at 0,00, and the
     * remainder billed A 3 at 375,00 though the customer ordered 2.
     */
    public function test_a_split_with_negative_stock_keeps_none_of_that_item_and_never_a_negative_quantity(): void
    {
        $this->partialBulkInvoicingOn();
        $a = self::$fx->item(['salgspris' => 100.00, 'kostpris' => 40.00, 'beholdning' => 1]);
        $b = self::$fx->item(['salgspris' => 100.00, 'kostpris' => 40.00, 'beholdning' => 100]);

        $after = self::startAfter();
        $first = self::$fx->order(self::$fx->debtor(), [['item' => $a, 'antal' => 2], ['item' => $b, 'antal' => 1]]);
        $this->bulkInvoice($after);
        $orders = self::splitOrders($first['ordrenr']);
        $this->assertCount(2, $orders, 'run 1 splits the order in two');
        [$kept, $rest] = $orders;
        $this->assertSame([1.0, 1.0], [self::quantity((int)$kept['id'], $a['id']), self::quantity((int)$kept['id'], $b['id'])], 'run 1 keeps A 1 and B 1');
        $this->assertSame([200.0, 50.0, 250.0], $this->posted((int)$kept['id']));
        $this->assertSame([100.0, 25.0, 125.0], $this->posted((int)$rest['id']), 'and invoices the remainder, A 1');
        $this->assertEqualsWithDelta(-1.0, (float)self::one('SELECT beholdning FROM varer WHERE id = $1', [$a['id']])['beholdning'], 0.0001,
            'precondition: run 1 leaves A at -1 in stock');

        $after = self::startAfter();
        $second = self::$fx->order(self::$fx->debtor(), [['item' => $a, 'antal' => 2], ['item' => $b, 'antal' => 1]]);
        $this->bulkInvoice($after);

        $orders = self::splitOrders($second['ordrenr']);
        $this->assertCount(2, $orders, 'run 2 splits the order in two');
        [$kept, $rest] = $orders;
        $negative = self::one("SELECT count(*) AS n FROM ordrelinjer l JOIN ordrer o ON o.id = l.ordre_id WHERE o.ordrenr = $1 AND o.art = 'DO' AND l.antal < 0", [$second['ordrenr']]);
        $this->assertSame(0, (int)$negative['n'], 'no invoice line with a negative quantity (the order kept A -1)');
        $this->assertSame([0.0, 1.0], [self::quantity((int)$kept['id'], $a['id']), self::quantity((int)$kept['id'], $b['id'])], 'the order keeps no A and B 1');
        $this->assertSame([2.0, 0.0], [self::quantity((int)$rest['id'], $a['id']), self::quantity((int)$rest['id'], $b['id'])], 'the remainder is A 2, as ordered (it was A 3)');
        $this->assertGreaterThanOrEqual(3, (int)$kept['status'], 'the order is invoiced');
        $this->assertEqualsWithDelta(100.00, (float)$kept['sum'], 0.0001);
        $this->assertEqualsWithDelta(25.00, (float)$kept['moms'], 0.0001);
        $this->assertSame([100.0, 25.0, 125.0], $this->posted((int)$kept['id']), 'revenue 100,00, VAT 25,00, receivable 125,00 (it was 0,00)');
        $this->assertEqualsWithDelta(125.00, self::openItem((int)$kept['id']), 0.0001);
        $this->assertGreaterThanOrEqual(3, (int)$rest['status'], 'the same run invoices the remainder');
        $this->assertSame([200.0, 50.0, 250.0], $this->posted((int)$rest['id']), 'revenue 200,00, VAT 50,00, receivable 250,00 (it was 375,00)');
        $this->assertEqualsWithDelta(250.00, self::openItem((int)$rest['id']), 0.0001);
    }
}
