<?php
// 20261012 CL/LH Request cache uses one lazy authorized connection and forgets facts at authentication changes.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/InvoicePaymentDatabase.php';
require_once __DIR__ . '/../../restapi/core/InvoicePaymentReadContext.php';

final class InvoicePaymentReadContextTest extends TestCase
{
    use InvoicePaymentDatabase {
        tearDown as private closeDatabase;
    }

    protected function setUp(): void { $this->openDatabase(); }
    protected function tearDown(): void
    {
        InvoicePaymentReadContext::configure(null);
        $this->closeDatabase();
    }

    public function testUnconfiguredContextReturnsNullWithoutOpeningAConnection(): void
    {
        InvoicePaymentReadContext::configure(null);
        $this->assertNull(InvoicePaymentReadContext::debtor(1));
        $this->assertNull(InvoicePaymentReadContext::invoice(10));
    }

    public function testBatchNullsAreCachedOnOneLazyConnection(): void
    {
        $connects = 0;
        InvoicePaymentReadContext::configure(function () use (&$connects): PDO {
            $connects++;
            return $this->db->pdo;
        });
        $this->assertSame(0, $connects);
        InvoicePaymentReadContext::prepareDebtors(range(1, 100));
        InvoicePaymentReadContext::prepareInvoices(range(1, 100));
        foreach (range(1, 100) as $id) {
            $this->assertNull(InvoicePaymentReadContext::debtor($id));
            $this->assertNull(InvoicePaymentReadContext::invoice($id));
        }
        $this->assertSame(1, $connects);
    }

    public function testConfigureDiscardsConnectionAndCachedNullsEvenForRepeatedIds(): void
    {
        $connects = 0;
        $connect = function () use (&$connects): PDO { $connects++; return $this->db->pdo; };
        InvoicePaymentReadContext::configure($connect);
        InvoicePaymentReadContext::debtor(1);
        InvoicePaymentReadContext::configure($connect);
        InvoicePaymentReadContext::debtor(1);
        $this->assertSame(2, $connects);
    }
}
