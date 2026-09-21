<?php
// 20260920 CDX/LH Keep finance precision, journal navigation and preservation regressions in normal PHPUnit discovery.
// 20260921 CDX/LH Exercise atomic invoice/payment split and concurrent replay in the native gate.
use PHPUnit\Framework\TestCase;

final class FinanceRegressionTest extends TestCase
{
    private function runFinanceScript(string $name): void
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/' . $name],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        self::assertSame(0, $status, $output . $errors);
        self::assertSame('', $errors, $errors);
        self::assertStringContainsString('PASS:', $output);
    }

    public function testOpenpostSplitAtomicity(): void
    {
        $this->runFinanceScript('test_openpost_split.php');
    }

    public function testSalesPostingPrecision(): void
    {
        $this->runFinanceScript('test_sales_posting_precision.php');
    }

    public function testJournalRedirectContext(): void
    {
        $this->runFinanceScript('test_journal_save_redirect.php');
    }

    public function testJournalSaveHttpLifecycle(): void
    {
        $this->runFinanceScript('test_journal_save_lifecycle.php');
    }

    public function testDraftPreservationAndSettlement(): void
    {
        $this->runFinanceScript('test_journal_preservation_and_settlement.php');
    }
    public function testApiDebtorAndOrderIntegrity(): void
    {
        $this->runFinanceScript('test_api_record_integrity.php');
    }
    public function testShopOrderAtomicityAndConcurrentReplay(): void
    {
        $this->runFinanceScript('test_shop_order_transaction.php');
    }
}
