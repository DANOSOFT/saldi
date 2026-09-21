<?php
// 20260920 CDX/LUI Include isolated startup and empty-report regressions in the standard suite.
use PHPUnit\Framework\TestCase;

final class ReleaseStartupRegressionTest extends TestCase
{
    public function testPhp8StartupAndStockReportRegressions(): void
    {
        $this->runRegressionScript('test_release_startup.php');
    }

    public function testLegacyStockPagesAndExplicitCostWrite(): void
    {
        $this->runRegressionScript('test_legacy_stock_pages.php');
    }

    public function testLegacyItemImportParsingAndValidation(): void
    {
        $this->runRegressionScript('test_legacy_item_import.php');
    }

    public function testLegacyItemImportHttpSessionAndUpload(): void
    {
        $this->runRegressionScript('test_legacy_item_import_http.php');
    }

    public function testInventorySupplierFilterWithoutSavedSelection(): void
    {
        $this->runRegressionScript('test_inventory_supplier_filter.php');
    }

    private function runRegressionScript(string $script): void
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/' . $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        self::assertSame(0, $status, $output . $errors);
        self::assertSame('', $errors);
    }
}
