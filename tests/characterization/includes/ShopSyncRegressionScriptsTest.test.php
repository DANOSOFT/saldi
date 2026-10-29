<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Copyright (c) 2026 Danosoft ApS
// 20261028 CDX/MJ SST-876 Run the existing isolated queue/transaction regressions in the discovered CI suite.
final class ShopSyncRegressionScriptsTest extends TestCase
{
    /** @return array<string, array{0:string,1?:array<string,string>}> */
    public static function scripts(): array
    {
        return [
            'configuration' => ['test_sst876_configuration.php'],
            'delivery transaction' => ['test_sst876_delivery_transaction.php'],
            'commit and rollback recovery' => ['test_sst876_transaction_recovery.php'],
            'document flush' => ['test_sst876_flush.php'],
            'queue and transport outcomes' => ['test_sst876_sync_queue.php'],
            'document fanout and budgets' => ['test_sst876_item_fanout.php'],
            'queued sets' => ['test_sst876_set_recovery.php'],
            'actual delayed HTTP document' => ['test_sst876_document_http.php'],
            'actual HTTP failure classification' => ['test_sst876_transport_http.php'],
            'actual HTTP queued sets' => ['test_sst876_set_recovery.php', ['SALDI_SHOP_SET_RECOVERY_HTTP' => '1']],
            'outer transaction callers' => ['characterization/includes/support/shop_sync_outer_callers.php'],
            'retry marker persistence' => ['characterization/includes/support/shop_sync_retry_persistence.php'],
            'fresh batches and exhausted budget' => ['characterization/includes/support/shop_sync_batch_refresh.php'],
        ];
    }

    #[DataProvider('scripts')]
    public function testIsolatedRegression(string $script, array $environment = []): void
    {
        $path = str_starts_with($script, 'test_sst876_')
            ? __DIR__ . '/support/shopSync/' . $script
            : dirname(__DIR__, 2) . '/' . $script;
        $process = proc_open([PHP_BINARY, $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), $environment));
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertSame(0, $exit, "$script\n$output\n$errors");
        self::assertStringContainsString('PASS:', $output);
        self::assertDoesNotMatchRegularExpression('/(?:Warning|Fatal error|Deprecated|Parse error):/', $output . $errors);
    }
}
