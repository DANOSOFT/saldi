<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Copyright (c) 2026 Danosoft ApS
// 20261030 CL/MJ SST-883 Runs support/posting_warehouse_pg.php: the shipped linjeopdat() and lagerstatus()
//                against PostgreSQL temporary tables, inside a transaction that is rolled back.
//                CI hands the characterization suite its fixture database as SALDI_CHAR_PG_DSN.
final class PostingWarehousePgTest extends TestCase
{
    public function testThePostingWritersNeverWriteToARemovedWarehouse(): void
    {
        $dsn = getenv('SALDI_TEST_PG_DSN') ?: getenv('SALDI_CHAR_PG_DSN');
        if (!extension_loaded('pgsql') || !$dsn) {
            self::markTestSkipped('set SALDI_CHAR_PG_DSN (a libpq connection string) to run the PostgreSQL posting-warehouse scenarios.');
        }
        $process = proc_open([PHP_BINARY, __DIR__ . '/support/posting_warehouse_pg.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
            array_merge(getenv(), ['SALDI_TEST_PG_DSN' => (string)$dsn]));
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertSame(0, $exit, $output . $errors);
        self::assertStringContainsString('PASS:', (string)$output, $output . $errors);
    }
}
