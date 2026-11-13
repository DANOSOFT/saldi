<?php
// 20261113 CDX/NTR Characterize sales, credit-note and POS posting before SD-599 extractions.

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class OrderTypeInvoiceCharacterizationTest extends CharacterizationTestCase
{
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
        $open = self::rows('SELECT konto_id, faktnr, amount, udlignet FROM openpost WHERE refnr = $1', [$order['id']]);
        self::assertCount(1, $open);
        self::assertSame((int)$debtor['id'], (int)$open[0]['konto_id']);
        self::assertSame((string)$out['fakturanr'], $open[0]['faktnr']);
        self::assertEqualsWithDelta($gross, (float)$open[0]['amount'], 0.0001);
        self::assertSame('0', (string)$open[0]['udlignet']);
    }

    public function test_a_cash_pos_sale_posts_to_the_register_without_a_receivable(): void
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
        $item = self::$fx->item(['stocked' => false, 'salgspris' => 125, 'kostpris' => 0]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 2]], ['art' => 'PO']);
        self::rows(
            "UPDATE ordrer SET betalingsbet = 'Kontant', fakturanr = '90001', felt_1 = 'Kontant',
             felt_2 = '312.50', felt_4 = '0', felt_5 = '1' WHERE id = $1",
            [$order['id']]
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
        self::assertCount(3, $postings);
        self::assertEqualsWithDelta(312.50, self::sumOf($postings, 'debet'), 0.0001);
        self::assertEqualsWithDelta(312.50, self::sumOf($postings, 'kredit'), 0.0001);
        $cash = array_values(array_filter($postings, static fn(array $row): bool => (int)$row['kontonr'] === $cashAccount));
        self::assertCount(1, $cash);
        self::assertEqualsWithDelta(312.50, (float)$cash[0]['debet'], 0.0001);
        self::assertCount(0, self::rows('SELECT id FROM openpost WHERE refnr = $1', [$order['id']]));
        $payments = self::rows('SELECT betalingstype, amount FROM pos_betalinger WHERE ordre_id = $1', [$order['id']]);
        self::assertCount(1, $payments);
        self::assertSame('Kontant', $payments[0]['betalingstype']);
        self::assertEqualsWithDelta(312.50, (float)$payments[0]['amount'], 0.0001);
    }
}
