<?php
// 20260921 CDX/LUI Run the actual reference-client HTTP failure/retry regressions in CI.
use PHPUnit\Framework\TestCase;
final class ReleaseRestClientRegressionTest extends TestCase
{
    public function testOrderExportTransportAndReplaySafety(): void
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/test_rest_api_client.php'],
            [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $output . $errors);
        self::assertSame('', $errors);
    }
}
