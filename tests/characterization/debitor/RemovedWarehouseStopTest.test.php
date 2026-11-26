<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Copyright (c) 2026 Danosoft ApS
// 20261116 CL/MMR SD-827 Run the removed-warehouse stop regression in the discovered CI suite.
final class RemovedWarehouseStopTest extends TestCase
{
    /**
     * Fakturér, Kreditér and Lever stop before posting when a line is on a removed warehouse.
     *
     * The script runs order_lines_on_removed_warehouse() on fake order data and then the real
     * debitor/levering.php with doubles, in its own process because the page ends with exit.
     */
    public function testARemovedWarehouseStopsTheDeliveryBeforeAnythingIsPosted(): void
    {
        $path = dirname(__DIR__) . '/includes/support/removed_warehouse_stop.php';
        $process = proc_open([PHP_BINARY, $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertSame(0, $exit, "$output\n$errors");
        self::assertSame(11, substr_count($output, 'PASS:'), $output);
        self::assertDoesNotMatchRegularExpression('/(?:Warning|Fatal error|Deprecated|Parse error|FAIL):/', $output . $errors);
    }
}
