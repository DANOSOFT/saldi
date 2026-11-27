<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Runs tests/test_following_opening_balances.php, the carry's checks on a chart with a Sideskift (X) account, under
 * PHPUnit: the script is a CLI script that no suite picked up. It pins that a Sideskift chart is carried as it always was
 * (a July-June year with a leap day, three years, the transfer accounts, missing status and result destinations as
 * boundaries, rollback), also with the carry's lock (openingCarryLock(), p1-pos-close-R1) taken before the carry reads,
 * and that a repeated carry writes only an opening that changes.
 * The script works on TEMP tables in one transaction that it rolls back, so nothing in the database is changed.
 *
 * History:
 * 20261119 CL/ASR Created.
 *                 23 checks: the script also checks that a repeated carry writes only an opening that changes.
 */
final class FiscalYearSideskiftCarryScriptTest extends TestCase
{
    public function test_the_sideskift_carry_script_passes(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to run the script.');
        }
        $dsn = (string) getenv('SALDI_CHAR_PG_DSN');
        if ($dsn === '') {
            self::markTestSkipped('set SALDI_CHAR_PG_DSN (a libpq connection string) to run the script.');
        }
        $script = dirname(__DIR__, 3) . '/tests/test_following_opening_balances.php';
        $process = proc_open([PHP_BINARY, $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname($script),
            array_merge(getenv(), ['SALDI_TEST_DSN' => $dsn]));
        self::assertIsResource($process);
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $out . $err);
        self::assertSame(23, substr_count($out, 'PASS: '), $out . $err);
        self::assertStringNotContainsString('FAIL', $out . $err);
    }
}
