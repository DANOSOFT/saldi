<?php
// 20261113 CDX/NTR Preserve the disabled commission entry point during extraction.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class CommissionSettlementCharacterizationTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_commission_settlement_returns_without_output_or_database_access(): void
    {
        eval('function db_select(...$args) { throw new RuntimeException("Commission must remain disabled"); }
              function db_modify(...$args) { throw new RuntimeException("Commission must remain disabled"); }');
        require dirname(__DIR__, 4) . '/includes/ordrefunc.php';
        ob_start();
        $result = settleCommision(123);
        $output = ob_get_clean();
        self::assertNull($result);
        self::assertSame('', $output);
    }
}
