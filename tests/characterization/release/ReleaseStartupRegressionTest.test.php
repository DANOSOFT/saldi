<?php
// 20260920 CDX/LUI Include isolated startup and empty-report regressions in the standard suite.
// 20260921 CDX/LH Route the gate fixture to import rollback checks and reject silent child skips.
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
        $this->runRegressionScript('test_legacy_item_import.php', true);
    }

    public function testLegacyItemImportHttpSessionAndUpload(): void
    {
        $this->runRegressionScript('test_legacy_item_import_http.php');
    }

    public function testInventorySupplierFilterWithoutSavedSelection(): void
    {
        $this->runRegressionScript('test_inventory_supplier_filter.php');
    }

    private function runRegressionScript(string $script, bool $requiresPostgres = false): void
    {
        $environment = getenv();
        if ($requiresPostgres) {
            $dsn = getenv('SALDI_CHAR_PG_DSN') ?: getenv('SALDI_TEST_DSN');
            if (!extension_loaded('pgsql') || (!$dsn && !getenv('SALDI_ITEM_IMPORT_PGHOST'))) {
                self::markTestSkipped('Set SALDI_CHAR_PG_DSN and enable pgsql for import write/rollback checks.');
            }
            if ($dsn) $environment['SALDI_TEST_DSN'] = $dsn;
        }
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/' . $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        self::assertSame(0, $status, $output . $errors);
        self::assertSame('', $errors);
        self::assertMatchesRegularExpression('/^PASS(?::| )/m', $output);
        self::assertStringNotContainsString('SKIP:', $output);
        if ($requiresPostgres) {
            self::assertStringContainsString('PASS: Later write failure rolls back earlier item updates', $output);
        }
    }
}
