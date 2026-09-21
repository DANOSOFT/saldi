<?php
// 20260921 CDX/LUI Cover the production no-write rejection used by reference-client retries.
// 20260920 CDX/LH Discover webshop, price and SQL-limit regressions in the ordinary characterization suite.
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReleaseWebshopRegressionTest extends TestCase
{
    public static function scripts(): array
    {
        return [
            'stock receipt quantity and identity' => ['test_stock_receipt_input.php', true],
            'stock receipt posting and warehouse atomicity' => ['test_stock_receipt_posting.php', true],
            'delivery and credit quantity accumulation' => ['test_delivery_quantity.php', true],
            'creditor posting precision' => ['test_creditor_posting_precision.php', true],
            'dunning atomic posting' => ['test_dunning_posting.php', true],
            'credit atomicity and metadata' => ['test_webshop_credit_note.php', true],
            'reference client safe server rejection' => ['test_rest_client_rejection_contract.php', true],
            'legacy API dispatch diagnostics' => ['test_legacy_api_dispatch.php', false],
            'CSV barcode collision diagnostics' => ['test_varesync_barcode_diagnostics.php', true],
            'CSV import dialects' => ['test_varesync_csv.php', true],
            'CSV atomic validation and row diagnostics' => ['test_varesync_validation.php', true],
            'configured legacy API actor and attribution' => ['test_legacy_api_actor.php', true],
            'product group fiscal year' => ['test_product_group_fiscal_year.php', true],
            'SQL collection limits' => ['test_api_collection_limits.php', true],
            'JSON sales price' => ['test_product_sales_price.php', false],
            'invoice preflight totals' => ['test_order_invoice_totals.php', false],
            'shop monetary and identity inputs' => ['test_shop_order_input.php', false],
        ];
    }

    #[DataProvider('scripts')]
    public function testRegressionScript(string $script, bool $requiresPostgres): void
    {
        $environment = getenv();
        if ($requiresPostgres) {
            $dsn = getenv('SALDI_CHAR_PG_DSN');
            if (!extension_loaded('pgsql') || !$dsn) {
                self::markTestSkipped('Set SALDI_CHAR_PG_DSN for an isolated PostgreSQL fixture.');
            }
            $environment['SALDI_TEST_DSN'] = $dsn;
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
        self::assertSame('', $errors, $output);
        self::assertStringContainsString('PASS:', $output);
        self::assertStringNotContainsString('SKIP:', $output);
    }
}
