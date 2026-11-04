<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * SST-841: booking_invoice_once() invoices a booking's order once, under a PostgreSQL advisory lock per order.
 * CodeRabbit (PR #969): when the invoice callback threw, the lock was never released and the caller got a 500
 * instead of invoice_failed. A thrown error is now a failed attempt, and the lock is released in every case.
 *
 * The database layer is a double that records every statement, so the lock and unlock calls can be counted.
 *
 * History:
 * 20261103 CL/MMR Created (SST-841, CodeRabbit on PR #969).
 * 20261104 CL/MMR CodeRabbit (PR #969): a failed lock query is a failed attempt, with no invoice and nothing to release.
 */
#[RunTestsInSeparateProcesses]
final class bookingInvoiceOnce extends TestCase
{
    private static function boot(?array $order, bool $lockFails = false): void
    {
        $GLOBALS['__sql'] = [];
        $GLOBALS['__order'] = $order;
        $GLOBALS['__lockFails'] = $lockFails;
        eval('
            function db_select($q, $where = "") {
                $GLOBALS["__sql"][] = $q;
                return ($GLOBALS["__lockFails"] && strpos($q, "SELECT pg_advisory_lock(") === 0) ? false : $q;
            }
            function db_fetch_array($q) { return strpos($q, "SELECT fakturanr") === 0 ? $GLOBALS["__order"] : false; }
        ');
        require_once __DIR__ . '/../../includes/vibrantPayment.php';
        ini_set('error_log', sys_get_temp_dir() . '/bookingInvoiceOnce-' . getmypid() . '.log');
    }

    /** @return array{int, int} how many times the lock was taken and released for order 5 */
    private static function locks(): array
    {
        $taken = count(array_filter($GLOBALS['__sql'], static fn ($q) => $q === 'SELECT pg_advisory_lock(841, 5)'));
        $released = count(array_filter($GLOBALS['__sql'], static fn ($q) => $q === 'SELECT pg_advisory_unlock(841, 5)'));
        return [$taken, $released];
    }

    public function testAFailedLockQueryIsAFailedAttemptWithoutInvoicing(): void
    {
        self::boot(['fakturanr' => ''], true);
        $called = false;
        $done = booking_invoice_once(5, static function () use (&$called) {
            $called = true;
            return 5;
        });
        $this->assertFalse($done);
        $this->assertFalse($called, 'no invoice without the lock');
        $this->assertSame([1, 0], self::locks(), 'nothing to release when no lock was taken');
    }

    public function testAThrownExceptionIsAFailedAttemptAndTheLockIsReleased(): void
    {
        self::boot(['fakturanr' => '']);
        $done = booking_invoice_once(5, static function (): int {
            throw new RuntimeException('mail server down');
        });
        $this->assertFalse($done);
        $this->assertSame([1, 1], self::locks());
        $this->assertSame('SELECT pg_advisory_unlock(841, 5)', end($GLOBALS['__sql']), 'the unlock is the last statement');
        $this->assertStringContainsString('invoicing order 5 failed: mail server down', (string) file_get_contents(ini_get('error_log')));
    }

    public function testAnUndefinedFunctionInTheCallbackIsAFailedAttempt(): void
    {
        // mysale/api.php's fakturer_ordre() calls levering(), which mysale never loads (pre-existing, outside SST-841)
        self::boot(['fakturanr' => '0']);
        $done = booking_invoice_once(5, static function () {
            return call_user_func('levering_not_loaded_in_this_test', 5);
        });
        $this->assertFalse($done);
        $this->assertSame([1, 1], self::locks());
    }

    public function testASuccessfulInvoiceReleasesTheLock(): void
    {
        self::boot(['fakturanr' => '']);
        $this->assertTrue(booking_invoice_once(5, static fn (int $id): int => $id));
        $this->assertSame([1, 1], self::locks());
    }

    public function testAnAlreadyInvoicedOrderIsNotInvoicedAgain(): void
    {
        self::boot(['fakturanr' => '1042']);
        $called = false;
        $this->assertTrue(booking_invoice_once(5, static function (int $id) use (&$called): int {
            $called = true;
            return $id;
        }));
        $this->assertFalse($called);
        $this->assertSame([1, 1], self::locks());
    }

    public function testAFailedAttemptCanBeRetried(): void
    {
        self::boot(['fakturanr' => '']);
        $this->assertFalse(booking_invoice_once(5, static function (): int {
            throw new RuntimeException('first try');
        }));
        $this->assertTrue(booking_invoice_once(5, static fn (int $id): int => $id));
        $this->assertSame([2, 2], self::locks(), 'every lock taken is released again');
    }

    public function testAMissingOrderIsNotInvoiced(): void
    {
        self::boot(null);
        $called = false;
        $this->assertFalse(booking_invoice_once(5, static function (int $id) use (&$called): int {
            $called = true;
            return $id;
        }));
        $this->assertFalse($called);
        $this->assertSame([1, 1], self::locks());
    }
}
