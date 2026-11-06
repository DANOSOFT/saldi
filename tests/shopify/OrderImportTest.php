<?php
// tests/shopify/OrderImportTest.php
//
// The paid-order import's pure parts (SD-743, WP09-2): the payment id written on the order and
// the items a move barrier is opened for. The import itself is OrderImportSyncTest.
//
// History:
// 20261103 CL/SS SD-743: created.

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class OrderImportTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/shopify/services/orderImport.php';
    }

    public function testThePaymentIdIsTheFirstSuccessfulSaleOrCapture(): void
    {
        $t = static fn(string $id, string $kind, string $status): array => ['gid' => "gid://shopify/OrderTransaction/$id", 'kind' => $kind, 'status' => $status];
        $this->assertSame('3', shopify_import_payment_id([$t('1', 'AUTHORIZATION', 'SUCCESS'), $t('2', 'SALE', 'FAILURE'), $t('3', 'CAPTURE', 'SUCCESS'), $t('4', 'SALE', 'SUCCESS')]));
        $this->assertSame('', shopify_import_payment_id([$t('1', 'REFUND', 'SUCCESS')]));
    }

    public function testABarrierIsOpenedForEveryLineItemSetComponentAndTheSetOnce(): void
    {
        $validated = ['lines' => [
            ['vare_id' => 7, 'bom' => []],
            ['vare_id' => 9, 'bom' => [['vare_id' => 11], ['vare_id' => 7], ['vare_id' => 12]]],
        ]];
        $this->assertSame([7, 9, 11, 12], shopify_import_barrier_items($validated));
    }
}
