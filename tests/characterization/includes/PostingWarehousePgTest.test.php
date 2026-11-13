<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Copyright (c) 2026 Danosoft ApS
// 20261030 CL/MJ SST-883 Runs support/posting_warehouse_pg.php: the shipped linjeopdat() and lagerstatus()
//                against PostgreSQL temporary tables, inside a transaction that is rolled back.
//                CI hands the characterization suite its fixture database as SALDI_CHAR_PG_DSN.
// 20261113 CL/MJ SST-883 The scenarios run on TestDatabase's shared SQL fixture, as every table is temporary.
//                CI no longer sets SALDI_CHAR_PG_DSN, and the test skipped there.
final class PostingWarehousePgTest extends TestCase
{
    public function testThePostingWritersNeverWriteToARemovedWarehouse(): void
    {
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null || !extension_loaded('pgsql')) {
            self::markTestSkipped($reason ?? 'the pgsql extension is not loaded');
        }
        $process = proc_open([PHP_BINARY, __DIR__ . '/support/posting_warehouse_pg.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
            array_merge(getenv(), ['SALDI_TEST_PG_DSN' => self::dsn(TestDatabase::sqlFixture(static::class)->name())]));
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertSame(0, $exit, $output . $errors);
        self::assertStringContainsString('PASS:', (string)$output, $output . $errors);
    }

    /** The libpq connection string TestDatabase::connect() opens, for the child process. */
    private static function dsn(string $database): string
    {
        $values = [
            'host' => getenv('SALDI_CHAR_PGHOST') ?: 'postgres',
            'user' => getenv('SALDI_CHAR_PGUSER') ?: 'user',
            'password' => getenv('SALDI_CHAR_PGPASS') ?: '',
            'dbname' => $database,
        ];
        $parts = [];
        foreach ($values as $key => $value) {
            $parts[] = $key . "='" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        }
        return implode(' ', $parts);
    }
}
