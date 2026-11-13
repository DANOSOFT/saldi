<?php
// 20261113 CDX/NTR Exercise ledger posting through the public adapter without the order library (SD-599).

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class InvoicePostingAdapterTest extends CharacterizationTestCase
{
    /** @return iterable<string, array{string,int}> */
    public static function salesTypes(): iterable
    {
        yield 'sales' => ['DO', 1];
        yield 'credit' => ['DK', -1];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('salesTypes')]
    public function test_delivered_invoice_posts_without_loading_ordrefunc(string $art, int $quantity): void
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['stocked' => false, 'salgspris' => 100, 'kostpris' => 0]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => $quantity]], ['art' => $art, 'status' => 3]);
        [$salesAccount, $vatAccount] = self::$fx->vatSalesAccount();
        self::rows('UPDATE ordrer SET fakturanr=59901,fakturadate=CURRENT_DATE WHERE id=$1', [$order['id']]);
        self::rows('UPDATE ordrelinjer SET bogf_konto=$2,vat_account=$3,leveret=antal WHERE ordre_id=$1', [$order['id'], $salesAccount, $vatAccount]);
        $out = $this->runChildJson('run_invoice_posting_adapter.php', [$order['id'], CharacterizationEnv::testDb(), self::$regnaar]);
        self::assertSame('OK', $out['result']);
        self::assertFalse($out['loadedOrderLibrary']);
        self::assertSame(PGSQL_TRANSACTION_INTRANS, $out['transaction']);
        $rows = self::rows('SELECT kontonr,debet,kredit FROM transaktioner WHERE ordre_id=$1', [$order['id']]);
        self::assertCount(3, $rows);
        self::assertEqualsWithDelta(125.0, self::sumOf($rows, 'debet'), 0.0001);
        self::assertEqualsWithDelta(125.0, self::sumOf($rows, 'kredit'), 0.0001);
        $open = self::one('SELECT amount,faktnr FROM openpost WHERE refnr=$1', [$order['id']]);
        self::assertEqualsWithDelta($quantity * 125, (float)$open['amount'], 0.0001);
        self::assertSame('59901', $open['faktnr']);
    }
}
