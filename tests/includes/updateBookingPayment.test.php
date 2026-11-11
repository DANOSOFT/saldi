<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * SST-841: update_booking_payment(), the updateBooking action of the login-free booking pages.
 * Review on PR #969: approving must claim the booking row (a booking the expiry cleanup deleted while Vibrant was asked
 * is not invoiced), an approved booking with a bound payment is invoiced once without asking Vibrant again, and
 * Vibrant is asked only while the booking is pending.
 *
 * The database layer is a scripted double: the booking row, the result of the approving UPDATE ... RETURNING and the
 * order's invoice number are set per test, and every statement is recorded. vibrant_payment_state() is a stub that
 * counts its calls (vibrantPayment.php only declares it when it does not exist yet).
 *
 * History:
 * 20261103 CL/MMR Created (SST-841, review by sawaneh1 on PR #969).
 * 20261104 CL/MMR CodeRabbit (PR #969): deleting the booking and its order is one transaction; transaktion() is recorded too.
 * 20261111 CL/MMR Codex round 19 (PR #969): a list or object as status or id gives no PHP warning.
 */
#[RunTestsInSeparateProcesses]
final class updateBookingPayment extends TestCase
{
    /**
     * @param array<string, mixed>|false $booking  rentalperiod row (order_id, expiry_time, payment_id) or false.
     * @param array<string, mixed>|false $claimed  What the approving UPDATE ... RETURNING returns.
     */
    private static function boot($booking, ?string $vibrant, $claimed = ['order_id' => '7'], string $fakturanr = '', bool $orderDeleteFails = false): void
    {
        $GLOBALS['__sql'] = [];
        $GLOBALS['__orderDeleteFails'] = $orderDeleteFails;
        $GLOBALS['__booking'] = $booking;
        $GLOBALS['__claimed'] = $claimed;
        $GLOBALS['__fakturanr'] = $fakturanr;
        $GLOBALS['__vibrant'] = $vibrant;
        $GLOBALS['__vibrantCalls'] = 0;
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_select($q, $where = "") {
                $GLOBALS["__sql"][] = $q;
                return ($GLOBALS["__orderDeleteFails"] && strpos($q, "DELETE FROM ordrer") === 0) ? false : $q;
            }
            function db_modify($q, $where = "") { $GLOBALS["__sql"][] = $q; return true; }
            function booking_query_quiet($q) {
                $GLOBALS["__sql"][] = $q;
                return ($GLOBALS["__orderDeleteFails"] && strpos($q, "DELETE FROM ordrer") === 0) ? false : $q;
            }
            function transaktion($q) { $GLOBALS["__sql"][] = "transaktion:" . $q; }
            function db_fetch_array($q) {
                if (strpos($q, "DELETE FROM rentalperiod") === 0) return ["order_id" => $GLOBALS["__booking"]["order_id"]];
                if (strpos($q, "information_schema.columns") !== false) return ["n" => 2];
                if (strpos($q, "SELECT order_id, expiry_time, payment_id FROM rentalperiod") === 0) return $GLOBALS["__booking"];
                if (strpos($q, "SELECT apikey FROM rentalpayment") === 0) return ["apikey" => "testkey"];
                if (strpos($q, "UPDATE rentalperiod SET expiry_time = NULL") === 0) return $GLOBALS["__claimed"];
                if (strpos($q, "SELECT fakturanr FROM ordrer") === 0) return ["fakturanr" => $GLOBALS["__fakturanr"]];
                return false;
            }
            function vibrant_payment_state($apiKey, $paymentId) { $GLOBALS["__vibrantCalls"]++; return $GLOBALS["__vibrant"]; }
        ');
        require_once __DIR__ . '/../../includes/vibrantPayment.php';
        ini_set('error_log', sys_get_temp_dir() . '/updateBookingPayment-' . getmypid() . '.log');
    }

    /** @return array{0: callable, 1: ArrayObject<int, int>} an invoice callback that records the order ids it got */
    private static function invoice(bool $ok = true): array
    {
        $calls = new ArrayObject();
        $cb = static function (int $id) use ($calls, $ok) {
            $calls[] = $id;
            return $ok ? $id : false;
        };
        return [$cb, $calls];
    }

    private const PENDING = ['order_id' => '7', 'expiry_time' => '2026-11-03 12:00:00', 'payment_id' => 'pi_1'];
    private const APPROVED = ['order_id' => '7', 'expiry_time' => null, 'payment_id' => 'pi_1'];

    public function testABookingDeletedWhileVibrantWasAskedIsNotInvoiced(): void
    {
        // the expiry cleanup deleted the row: the approving UPDATE ... RETURNING returns nothing
        self::boot(self::PENDING, 'succeeded', false);
        [$cb, $calls] = self::invoice();
        $this->assertSame(['status' => 'not_found'], update_booking_payment('saldi_1', ['id' => 3, 'status' => 'approved'], $cb));
        $this->assertCount(0, $calls, 'no invoice for a booking that no longer exists');
        $this->assertStringContainsString('booking 3 was removed while its payment pi_1 succeeded', (string) file_get_contents(ini_get('error_log')));
    }

    public function testAConfirmedPaymentClaimsTheRowAndInvoicesItsOrderOnce(): void
    {
        self::boot(self::PENDING, 'succeeded', ['order_id' => '7']);
        [$cb, $calls] = self::invoice();
        $this->assertSame(['status' => 'approved'], update_booking_payment('saldi_1', ['id' => 3, 'status' => 'approved'], $cb));
        $this->assertSame([7], $calls->getArrayCopy());
        $this->assertContains('UPDATE rentalperiod SET expiry_time = NULL WHERE id = 3 RETURNING order_id', $GLOBALS['__sql']);
    }

    public function testAnApprovedBookingWhoseInvoiceFailedIsRetriedWithoutAskingVibrant(): void
    {
        // review "Should": Vibrant unreachable used to answer approved with no invoice attempt
        self::boot(self::APPROVED, null, ['order_id' => '7'], '');
        [$cb, $calls] = self::invoice();
        $this->assertSame(['status' => 'approved'], update_booking_payment('saldi_1', ['id' => 3, 'status' => 'approved'], $cb));
        $this->assertSame([7], $calls->getArrayCopy());
        $this->assertSame(0, $GLOBALS['__vibrantCalls'], 'Vibrant is not asked for an approved booking');
    }

    public function testAnApprovedBookingWhoseInvoiceFailsAgainAnswersInvoiceFailed(): void
    {
        self::boot(self::APPROVED, null, ['order_id' => '7'], '');
        [$cb, $calls] = self::invoice(false);
        $this->assertSame(['status' => 'invoice_failed'], update_booking_payment('saldi_1', ['id' => 3, 'status' => 'timeout'], $cb));
        $this->assertSame([7], $calls->getArrayCopy());
    }

    public function testAnApprovedAndInvoicedBookingIsLeftAlone(): void
    {
        self::boot(self::APPROVED, 'succeeded', ['order_id' => '7'], '1042');
        [$cb, $calls] = self::invoice();
        $this->assertSame(['status' => 'approved'], update_booking_payment('saldi_1', ['id' => 3, 'status' => 'rejected'], $cb));
        $this->assertCount(0, $calls);
        $this->assertSame(0, $GLOBALS['__vibrantCalls']);
    }

    public function testAnApprovedBookingWithoutABoundPaymentIsNotInvoiced(): void
    {
        // approved before SST-841 or by hand: no payment the server checked, so no invoice from this endpoint
        self::boot(['order_id' => '7', 'expiry_time' => null, 'payment_id' => ''], null);
        [$cb, $calls] = self::invoice();
        $this->assertSame(['status' => 'approved'], update_booking_payment('saldi_1', ['id' => 3, 'status' => 'approved'], $cb));
        $this->assertCount(0, $calls);
    }

    public function testAPendingBookingAsksVibrantOnceAndStaysPendingWithoutAnAnswer(): void
    {
        self::boot(self::PENDING, null);
        [$cb, $calls] = self::invoice();
        $this->assertSame(['status' => 'pending'], update_booking_payment('saldi_1', ['id' => 3, 'status' => 'approved'], $cb));
        $this->assertSame(1, $GLOBALS['__vibrantCalls']);
        $this->assertCount(0, $calls);
    }

    private const UNPAID = ['order_id' => '7', 'expiry_time' => '2026-11-03 12:00:00', 'payment_id' => ''];

    /** @return list<string> the statements of the delete branch, in order */
    private static function deleteStatements(): array
    {
        return array_values(array_filter($GLOBALS['__sql'], static fn ($q) => strpos($q, 'transaktion:') === 0 || strpos($q, 'DELETE ') === 0));
    }

    public function testARejectedUnpaidBookingAndItsOrderAreDeletedInOneTransaction(): void
    {
        self::boot(self::UNPAID, null);
        [$cb, $calls] = self::invoice();
        $this->assertSame(['status' => 'deleted'], update_booking_payment('saldi_1', ['id' => 3, 'status' => 'rejected'], $cb));
        $s = self::deleteStatements();
        $this->assertSame('transaktion:begin', $s[0]);
        $this->assertStringStartsWith('DELETE FROM rentalperiod WHERE id = 3', $s[1]);
        $this->assertSame('DELETE FROM ordrer WHERE id = 7', $s[2]);
        $this->assertSame(['transaktion:commit'], array_slice($s, 3));
        $this->assertCount(0, $calls);
    }

    public function testAFailedOrderDeleteRollsTheBookingDeleteBack(): void
    {
        // CodeRabbit (PR #969): the booking delete committed on its own and left an order without a booking
        self::boot(self::UNPAID, null, ['order_id' => '7'], '', true);
        [$cb] = self::invoice();
        $this->assertSame(['status' => 'pending'], update_booking_payment('saldi_1', ['id' => 3, 'status' => 'rejected'], $cb));
        $s = self::deleteStatements();
        $this->assertSame('transaktion:rollback', end($s));
        $this->assertNotContains('transaktion:commit', $s);
    }

    /**
     * Runs update_booking_payment() with every PHP warning or notice turned into a test failure.
     * @return array<string, string> the answer
     */
    private static function callWithoutWarnings(array $data): array
    {
        [$cb] = self::invoice();
        set_error_handler(static function (int $no, string $msg): never {
            throw new ErrorException($msg, 0, $no);
        });
        try {
            return update_booking_payment('saldi_1', $data, $cb);
        } finally {
            restore_error_handler();
        }
    }

    /** boot() declares the doubles once per process; later cases only swap the scripted rows. */
    private static function rescript($booking, ?string $vibrant): void
    {
        $GLOBALS['__sql'] = [];
        $GLOBALS['__booking'] = $booking;
        $GLOBALS['__vibrant'] = $vibrant;
    }

    public function testAStatusThatIsAListOrAnObjectCountsAsMissingWithoutAWarning(): void
    {
        // Codex round 19: (string) on an array warned "Array to string conversion" in front of the JSON answer
        self::boot(self::UNPAID, null);
        foreach ([['rejected'], ['status' => 'approved'], []] as $status) {
            self::rescript(self::UNPAID, null);
            $this->assertSame(['status' => 'deleted'], self::callWithoutWarnings(['id' => 3, 'status' => $status]), 'same as no status: unpaid is deleted');
        }
        self::rescript(self::UNPAID, null);
        $this->assertSame(['status' => 'deleted'], self::callWithoutWarnings(['id' => 3]), 'no status');
        // a confirmed payment still wins over a malformed status
        self::rescript(self::PENDING, 'succeeded');
        $this->assertSame(['status' => 'approved'], self::callWithoutWarnings(['id' => 3, 'status' => ['rejected']]));
        // a waiting payment is kept
        self::rescript(self::PENDING, 'waiting');
        $this->assertSame(['status' => 'pending'], self::callWithoutWarnings(['id' => 3, 'status' => ['x' => 1]]));
    }

    public function testAnIdThatIsAListOrAnObjectFindsNoBooking(): void
    {
        // (int) on a non-empty array is 1, which would have picked booking 1
        self::boot(false, null);
        foreach ([[3], ['id' => 3]] as $id) {
            self::rescript(false, null);
            $this->assertSame(['status' => 'not_found'], self::callWithoutWarnings(['id' => $id, 'status' => 'rejected']));
            $this->assertStringContainsString('FROM rentalperiod WHERE id = 0', implode("\n", $GLOBALS['__sql']));
        }
    }

    public function testThePaymentColumnsAreCountedOncePerName(): void
    {
        self::boot(self::PENDING, null);
        $this->assertTrue(rental_payment_ready('saldi_1'));
        $this->assertStringContainsString('count(DISTINCT column_name)', $GLOBALS['__sql'][0]);
    }
}
