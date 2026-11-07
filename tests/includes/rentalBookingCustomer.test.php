<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stdFunc/rentalBookingCustomer.php';

/**
 * The handles the public booking's createCust gives a visitor's session (rental_booking_customer_issue(),
 * includes/stdFunc/rentalBookingCustomer.php) and the customer createBooking books for with one
 * (rental_booking_customer()). No database or session: tests/characterization/rental/RemoteBookingCustomerTest.test.php
 * drives remoteBooking/api.php itself.
 */
final class rentalBookingCustomer extends TestCase
{
    public function test_an_earlier_handle_still_stands_for_its_customer_after_later_ones(): void
    {
        $session = [];
        $first = rental_booking_customer_issue($session, 'saldi_7', 12);
        $again = rental_booking_customer_issue($session, 'saldi_7', 12);
        $other = rental_booking_customer_issue($session, 'saldi_7', 34);

        $this->assertCount(3, array_unique([$first, $again, $other]), 'every createCust gets a handle of its own');
        $this->assertSame([12, 12, 34], [
            rental_booking_customer($session, $first, 'saldi_7'),
            rental_booking_customer($session, $again, 'saldi_7'),
            rental_booking_customer($session, $other, 'saldi_7'),
        ]);
    }

    public function test_a_handle_stands_for_nobody_at_another_regnskab_or_in_another_session(): void
    {
        $session = [];
        $elsewhere = [];
        $handle = rental_booking_customer_issue($session, 'saldi_7', 12);
        rental_booking_customer_issue($session, 'saldi_8', 34);
        rental_booking_customer_issue($elsewhere, 'saldi_7', 56);

        $this->assertSame(0, rental_booking_customer($session, $handle, 'saldi_8'));
        $this->assertSame(0, rental_booking_customer($elsewhere, $handle, 'saldi_7'));
        $this->assertSame(12, rental_booking_customer($session, $handle, 'saldi_7'), 'a handle at another regnskab leaves it good');
    }

    /** @return array<string, array{mixed}> */
    public static function claims(): array
    {
        return [
            'the customer id'       => [12],
            'the customer id, text' => ['12'],
            'nothing'               => [null],
            'empty'                 => [''],
            'a list'                => [['12']],
        ];
    }

    #[DataProvider('claims')]
    public function test_anything_but_a_handle_stands_for_nobody(mixed $claim): void
    {
        $session = [];
        rental_booking_customer_issue($session, 'saldi_7', 12);

        $this->assertSame(0, rental_booking_customer($session, $claim, 'saldi_7'));
        $this->assertSame(0, rental_booking_customer([], $claim, 'saldi_7'));
    }

    public function test_the_20_latest_handles_of_a_session_at_a_regnskab_are_kept(): void
    {
        $session = [];
        $handles = [];
        for ($id = 1; $id <= 21; $id++) {
            $handles[$id] = rental_booking_customer_issue($session, 'saldi_7', $id);
        }

        $this->assertSame(0, rental_booking_customer($session, $handles[1], 'saldi_7'), 'the oldest of 21 is dropped');
        foreach (range(2, 21) as $id) {
            $this->assertSame($id, rental_booking_customer($session, $handles[$id], 'saldi_7'));
        }
    }
}
