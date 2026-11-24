<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/vibrantPayment.php';

/**
 * SST-841: what updateBooking does with a booking on the login-free booking pages. The stored payment and
 * Vibrant's answer decide; the browser's word alone can neither approve (invoice) nor delete.
 */
final class bookingPaymentDecision extends TestCase
{
    private const PENDING_PAID = ['expiry_time' => '2026-10-29 16:00:00', 'payment_id' => 'pi_1'];
    private const PENDING_NO_PAYMENT = ['expiry_time' => '2026-10-29 16:00:00', 'payment_id' => ''];
    private const APPROVED = ['expiry_time' => null, 'payment_id' => 'pi_1'];

    public function testMissingBookingIsNotFound(): void
    {
        $this->assertSame('not_found', booking_payment_decision(false, 'approved', 'succeeded'));
    }

    public function testApprovedBookingIsNeverTouchedAgain(): void
    {
        foreach (['approved', 'rejected', 'timeout'] as $status) {
            $this->assertSame('already_approved', booking_payment_decision(self::APPROVED, $status, 'succeeded'));
            $this->assertSame('already_approved', booking_payment_decision(self::APPROVED, $status, 'failed'));
        }
    }

    public function testOnlyAConfirmedPaymentApproves(): void
    {
        $this->assertSame('approve', booking_payment_decision(self::PENDING_PAID, 'approved', 'succeeded'));
        $this->assertSame('pending', booking_payment_decision(self::PENDING_PAID, 'approved', 'requires_payment_method'));
        $this->assertSame('pending', booking_payment_decision(self::PENDING_PAID, 'approved', null));
        $this->assertSame('pending', booking_payment_decision(self::PENDING_NO_PAYMENT, 'approved', null));
    }

    public function testALatePaymentIsHonouredEvenWhenTheBrowserGaveUp(): void
    {
        $this->assertSame('approve', booking_payment_decision(self::PENDING_PAID, 'timeout', 'succeeded'));
        $this->assertSame('approve', booking_payment_decision(self::PENDING_PAID, 'rejected', 'succeeded'));
    }

    public function testDeleteOnlyWithoutAStartedPaymentOrAfterAFailedOne(): void
    {
        $this->assertSame('delete', booking_payment_decision(self::PENDING_NO_PAYMENT, 'rejected', null));
        foreach (['failed', 'canceled', 'cancelled'] as $state) {
            $this->assertSame('delete', booking_payment_decision(self::PENDING_PAID, 'rejected', $state));
        }
        $this->assertSame('pending', booking_payment_decision(self::PENDING_PAID, 'timeout', 'requires_payment_method'));
        $this->assertSame('pending', booking_payment_decision(self::PENDING_PAID, 'rejected', null));
    }
}
