<?php
// 20261113 CDX/NTR Characterize sales, credit-note and POS posting before SD-599 extractions.
// 20261113 CDX/NTR Pin linked-credit settlement before moving it behind the credit-note adapter.

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class OrderTypeInvoiceCharacterizationTest extends CharacterizationTestCase
{
    public function test_linked_credit_settles_the_matching_invoice_and_keeps_its_credit_description(): void
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['stocked' => false, 'salgspris' => 100, 'kostpris' => 0]);
        $invoice = self::$fx->order($debtor, [['item' => $item, 'antal' => 1]]);
        $posted = $this->runChildJson('run_order_invoice.php', ['invoice', $invoice['id'], CharacterizationEnv::testDb()]);
        self::assertSame('OK', $posted['bogfor']);
        $credit = self::$fx->order($debtor, [['item' => $item, 'antal' => -1]], ['art' => 'DK']);
        self::rows('UPDATE ordrer SET kred_ord_id=$2 WHERE id=$1', [$credit['id'], $invoice['id']]);
        $postedCredit = $this->runChildJson('run_order_invoice.php', ['invoice', $credit['id'], CharacterizationEnv::testDb()]);
        self::assertSame('OK', $postedCredit['bogfor']);
        $invoiceOpen = self::one('SELECT amount,udlignet,udlign_id,udlign_date FROM openpost WHERE refnr=$1', [$invoice['id']]);
        $creditOpen = self::one('SELECT amount,udlignet,udlign_id,udlign_date,beskrivelse FROM openpost WHERE refnr=$1', [$credit['id']]);
        self::assertEqualsWithDelta(125.0, (float)$invoiceOpen['amount'], 0.0001);
        self::assertEqualsWithDelta(-125.0, (float)$creditOpen['amount'], 0.0001);
        self::assertSame('1', $invoiceOpen['udlignet']);
        self::assertSame('1', $creditOpen['udlignet']);
        self::assertGreaterThan(0, (int)$invoiceOpen['udlign_id']);
        self::assertSame($invoiceOpen['udlign_id'], $creditOpen['udlign_id']);
        self::assertSame($invoiceOpen['udlign_date'], $creditOpen['udlign_date']);
        self::assertSame('Kreditnota - ' . $postedCredit['fakturanr'], $creditOpen['beskrivelse']);
    }

    /** @return iterable<string, array{string, float, string, float}> */
    public static function invoiceTypes(): iterable
    {
        yield 'sales invoice' => ['DO', 1.0, 'DO', 1563.13];
        yield 'credit note' => ['DK', -1.0, 'DK', -1563.13];
        yield 'negative sales order becomes credit note' => ['DO', -1.0, 'DK', -1563.13];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invoiceTypes')]
    public function test_invoice_posts_balanced_rows_and_the_matching_open_item(
        string $art,
        float $quantity,
        string $postedArt,
        float $gross
    ): void {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['stocked' => false, 'salgspris' => 1250.500, 'kostpris' => 0]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => $quantity]], ['art' => $art]);
        $itemGroup = self::one(
            "SELECT box4 FROM grupper WHERE art = 'VG' AND kodenr = $1 AND fiscal_year = $2",
            [$item['gruppe'], self::$regnaar]
        );
        $salesAccount = (int)$itemGroup['box4'];
        [, $vatAccount] = self::$fx->vatSalesAccount();
        $receivableAccount = self::$fx->debtorCollectiveAccount();

        $out = $this->runChildJson(
            'run_order_invoice.php',
            ['invoice', $order['id'], CharacterizationEnv::testDb()]
        );

        self::assertSame('OK', $out['levering']);
        self::assertSame('OK', $out['bogfor']);
        self::assertSame('4', (string)$out['status']);
        self::assertGreaterThan(0, (int)$out['fakturanr']);
        $header = self::one('SELECT art, sum, moms FROM ordrer WHERE id = $1', [$order['id']]);
        self::assertSame($postedArt, $header['art']);
        self::assertEqualsWithDelta($quantity * 1250.50, (float)$header['sum'], 0.0001);
        self::assertEqualsWithDelta($quantity * 312.63, (float)$header['moms'], 0.0001);

        $postings = self::rows('SELECT kontonr, debet, kredit FROM transaktioner WHERE ordre_id = $1', [$order['id']]);
        self::assertCount(3, $postings, 'one receivable, one sale, and one VAT posting');
        self::assertEqualsWithDelta(1563.13, self::sumOf($postings, 'debet'), 0.0001);
        self::assertEqualsWithDelta(1563.13, self::sumOf($postings, 'kredit'), 0.0001);
        $expected = [
            $receivableAccount => $quantity > 0 ? [1563.13, 0.0] : [0.0, 1563.13],
            $salesAccount => $quantity > 0 ? [0.0, 1250.50] : [1250.50, 0.0],
            $vatAccount => $quantity > 0 ? [0.0, 312.63] : [312.63, 0.0],
        ];
        foreach ($postings as $posting) {
            $account = (int)$posting['kontonr'];
            self::assertArrayHasKey($account, $expected);
            self::assertEqualsWithDelta($expected[$account][0], (float)$posting['debet'], 0.0001);
            self::assertEqualsWithDelta($expected[$account][1], (float)$posting['kredit'], 0.0001);
        }
        $open = self::rows('SELECT konto_id, faktnr, amount, udlignet FROM openpost WHERE refnr = $1', [$order['id']]);
        self::assertCount(1, $open);
        self::assertSame((int)$debtor['id'], (int)$open[0]['konto_id']);
        self::assertSame((string)$out['fakturanr'], $open[0]['faktnr']);
        self::assertEqualsWithDelta($gross, (float)$open[0]['amount'], 0.0001);
        self::assertSame('0', (string)$open[0]['udlignet']);
    }

    /** @return iterable<string, array{float, float, float, int}> */
    public static function cashAmounts(): iterable
    {
        yield 'ordinary sale' => [2.0, 125.0, 312.50, 3];
        yield 'half ore' => [1.0, 0.005, 0.01, 3];
        yield 'zero' => [1.0, 0.0, 0.0, 0];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cashAmounts')]
    public function test_a_cash_pos_sale_posts_to_the_register_without_a_receivable(
        float $quantity,
        float $price,
        float $total,
        int $rowCount
    ): void
    {
        [$cashAccount] = self::$fx->plainAccounts(1);
        self::rows(
            "INSERT INTO grupper (art, kodenr, fiscal_year, box2, box9)
             SELECT 'POS', '1', $1, $2, 'on' WHERE NOT EXISTS
             (SELECT 1 FROM grupper WHERE art = 'POS' AND kodenr = '1' AND fiscal_year = $1)",
            [self::$regnaar, (string)$cashAccount]
        );
        self::rows(
            "UPDATE grupper SET box2 = $1, box7 = '1', box9 = 'on' WHERE art = 'POS' AND kodenr = '1' AND fiscal_year = $2",
            [(string)$cashAccount, self::$regnaar]
        );
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['stocked' => false, 'salgspris' => $price, 'kostpris' => 0]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => $quantity]], ['art' => 'PO']);
        self::rows(
            "UPDATE ordrer SET betalingsbet = 'Kontant', fakturanr = '90001', felt_1 = 'Kontant',
             felt_2 = $2, felt_4 = '0', felt_5 = '1' WHERE id = $1",
            [$order['id'], (string)$total]
        );

        $out = $this->runChildJson(
            'run_order_invoice.php',
            ['invoice', $order['id'], CharacterizationEnv::testDb()]
        );

        self::assertSame('OK', $out['levering']);
        self::assertSame('OK', $out['bogfor']);
        self::assertSame('4', (string)$out['status']);
        self::assertSame('90001', (string)$out['fakturanr'], 'POS keeps its assigned receipt number');
        $postings = self::rows('SELECT kontonr, debet, kredit FROM transaktioner WHERE ordre_id = $1', [$order['id']]);
        self::assertCount($rowCount, $postings);
        self::assertEqualsWithDelta($total, self::sumOf($postings, 'debet'), 0.0001);
        self::assertEqualsWithDelta($total, self::sumOf($postings, 'kredit'), 0.0001);
        $cash = array_values(array_filter($postings, static fn(array $row): bool => (int)$row['kontonr'] === $cashAccount));
        self::assertCount($rowCount > 0 ? 1 : 0, $cash);
        if ($cash !== []) {
            self::assertEqualsWithDelta($total, (float)$cash[0]['debet'], 0.0001);
        }
        self::assertCount(0, self::rows('SELECT id FROM openpost WHERE refnr = $1', [$order['id']]));
        $payments = self::rows('SELECT betalingstype, amount FROM pos_betalinger WHERE ordre_id = $1', [$order['id']]);
        self::assertCount(1, $payments);
        self::assertSame('Kontant', $payments[0]['betalingstype']);
        self::assertEqualsWithDelta($total, (float)$payments[0]['amount'], 0.0001);
    }

    /** @return iterable<string, array{string, float, float, int}> */
    public static function smallAmounts(): iterable
    {
        yield 'sales half ore' => ['DO', 0.005, 0.01, 3];
        yield 'sales zero' => ['DO', 0.0, 0.0, 0];
        yield 'credit half ore' => ['DK', -0.005, -0.01, 3];
        yield 'credit zero' => ['DK', 0.0, 0.0, 0];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('smallAmounts')]
    public function test_small_sales_keep_their_posted_rounding(string $art, float $price, float $postedTotal, int $rowCount): void
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['stocked' => false, 'salgspris' => abs($price), 'kostpris' => 0]);
        $quantity = $art === 'DK' ? -1 : 1;
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => $quantity]], ['art' => $art]);

        $out = $this->runChildJson(
            'run_order_invoice.php',
            ['invoice', $order['id'], CharacterizationEnv::testDb()]
        );

        self::assertSame('OK', $out['levering']);
        self::assertSame('OK', $out['bogfor']);
        self::assertSame('4', (string)$out['status']);
        $postings = self::rows('SELECT debet, kredit FROM transaktioner WHERE ordre_id = $1', [$order['id']]);
        self::assertCount($rowCount, $postings);
        self::assertEqualsWithDelta(abs($postedTotal), self::sumOf($postings, 'debet'), 0.0001);
        self::assertEqualsWithDelta(abs($postedTotal), self::sumOf($postings, 'kredit'), 0.0001);
        $open = self::one('SELECT amount FROM openpost WHERE refnr = $1', [$order['id']]);
        self::assertNotNull($open);
        self::assertEqualsWithDelta($postedTotal, (float)$open['amount'], 0.0001);
    }

    /** @return iterable<string, array{string, float}> */
    public static function foreignTypes(): iterable
    {
        yield 'sales invoice' => ['DO', 1.0];
        yield 'credit note' => ['DK', -1.0];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('foreignTypes')]
    public function test_foreign_invoice_keeps_foreign_receivable_and_posts_domestic_ledger_amounts(string $art, float $quantity): void
    {
        [$differenceAccount] = self::$fx->plainAccounts(1);
        $group = self::one("SELECT COALESCE(MAX(kodenr), 0) + 1 AS code FROM grupper WHERE art = 'VK'");
        $code = (int)$group['code'];
        self::rows(
            "INSERT INTO grupper (art, kodenr, box1, box3, fiscal_year) VALUES ('VK', $1, 'XTS', $2, $3)",
            [$code, (string)$differenceAccount, self::$regnaar]
        );
        self::rows('INSERT INTO valuta (gruppe, kurs, valdate) VALUES ($1, 750, CURRENT_DATE)', [$code]);
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['stocked' => false, 'salgspris' => 100, 'kostpris' => 0]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => $quantity]], ['valuta' => 'XTS', 'art' => $art]);

        $out = $this->runChildJson(
            'run_order_invoice.php',
            ['invoice', $order['id'], CharacterizationEnv::testDb()]
        );

        self::assertSame('OK', $out['levering']);
        self::assertSame('OK', $out['bogfor']);
        self::assertSame('4', (string)$out['status']);
        $postings = self::rows('SELECT debet, kredit FROM transaktioner WHERE ordre_id = $1', [$order['id']]);
        self::assertCount(3, $postings);
        self::assertEqualsWithDelta(937.50, self::sumOf($postings, 'debet'), 0.0001);
        self::assertEqualsWithDelta(937.50, self::sumOf($postings, 'kredit'), 0.0001);
        $open = self::one('SELECT amount, valuta, valutakurs FROM openpost WHERE refnr = $1', [$order['id']]);
        self::assertNotNull($open);
        self::assertEqualsWithDelta($quantity * 125.0, (float)$open['amount'], 0.0001);
        self::assertSame('XTS', $open['valuta']);
        self::assertEqualsWithDelta(750.0, (float)$open['valutakurs'], 0.0001);
    }
}
