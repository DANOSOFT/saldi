<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stdFunc/rentalBookingCustomer.php';

/**
 * The handles the public booking's createCust gives a visitor's session (rental_booking_customer_issue(),
 * includes/stdFunc/rentalBookingCustomer.php) and the customer createBooking books for with one
 * (rental_booking_customer()). Also the bookings createBooking records in the session (rental_booking_note()), the only
 * ones updateBooking acts on (rental_booking_noted()), with Mit salg's kept apart (rental_booking_mysale_notes()), and
 * that createBooking learns when its session could not keep the booking (rental_booking_note_in_session(), on a
 * session whose save handler stands in for the session files). No database:
 * tests/characterization/rental/RemoteBookingCustomerTest.test.php and RemoteBookingRejectTest.test.php drive the
 * endpoints themselves.
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

    public function test_a_booking_is_noted_for_the_session_and_regnskab_that_made_it(): void
    {
        $session = [];
        $elsewhere = [];
        rental_booking_note($session, 'saldi_7', 41);
        rental_booking_note($session, 'saldi_7', '42');
        rental_booking_note($session, 'saldi_8', 43);
        rental_booking_note($elsewhere, 'saldi_7', 44);

        $this->assertTrue(rental_booking_noted($session, 41, 'saldi_7'));
        $this->assertTrue(rental_booking_noted($session, 42, 'saldi_7'), 'an id the request sent as text');
        $this->assertTrue(rental_booking_noted($session, '41', 'saldi_7'));
        $this->assertFalse(rental_booking_noted($session, 43, 'saldi_7'), 'made at another regnskab');
        $this->assertFalse(rental_booking_noted($session, 44, 'saldi_7'), 'made by another session');
        $this->assertFalse(rental_booking_noted($elsewhere, 41, 'saldi_7'), 'made by another session');
        $this->assertFalse(rental_booking_noted([], 41, 'saldi_7'), 'a session that made nothing');
        $this->assertTrue(rental_booking_noted($session, 43, 'saldi_8'), 'the other regnskab keeps its own');
    }

    /** @return array<string, array{mixed}> */
    public static function notBookingIds(): array
    {
        return [
            'zero'          => [0],
            'negative'      => [-41],
            'text, not id'  => ['41 or 1=1'],
            'a float'       => [41.0],
            'nothing'       => [null],
            'empty'         => [''],
            'a list'        => [[41]],
        ];
    }

    #[DataProvider('notBookingIds')]
    public function test_anything_but_a_noted_booking_id_is_not_noted(mixed $claim): void
    {
        $session = [];
        rental_booking_note($session, 'saldi_7', 41);

        $this->assertFalse(rental_booking_noted($session, $claim, 'saldi_7'));
    }

    public function test_an_unnoted_booking_is_no_longer_noted_and_the_others_stay(): void
    {
        $session = [];
        rental_booking_note($session, 'saldi_7', 41);
        rental_booking_note($session, 'saldi_7', 42);
        rental_booking_note($session, 'saldi_8', 41);

        rental_booking_unnote($session, 'saldi_7', 41);

        $this->assertFalse(rental_booking_noted($session, 41, 'saldi_7'));
        $this->assertTrue(rental_booking_noted($session, 42, 'saldi_7'));
        $this->assertTrue(rental_booking_noted($session, 41, 'saldi_8'), 'the same id at another regnskab stays');
    }

    public function test_mit_salgs_bookings_are_kept_apart_from_the_booking_pages(): void
    {
        $session = [];
        $mysale = rental_booking_mysale_notes('saldi_7');
        rental_booking_note($session, 'saldi_7', 41); // remoteBooking/api.php
        rental_booking_note($session, $mysale, 42);   // mysale/api.php, same session and regnskab

        $this->assertNotSame('saldi_7', $mysale);
        $this->assertNotSame(rental_booking_mysale_notes('saldi_8'), $mysale, 'per regnskab');
        $this->assertTrue(rental_booking_noted($session, 41, 'saldi_7'));
        $this->assertFalse(rental_booking_noted($session, 41, $mysale), "the booking page's booking is not Mit salg's");
        $this->assertTrue(rental_booking_noted($session, 42, $mysale));
        $this->assertFalse(rental_booking_noted($session, 42, 'saldi_7'), "Mit salg's booking is not the booking page's");

        rental_booking_unnote($session, $mysale, 41);
        $this->assertTrue(rental_booking_noted($session, 41, 'saldi_7'), "unnoting in Mit salg leaves the booking page's");
    }

    public function test_the_20_latest_bookings_of_a_session_at_a_regnskab_are_kept(): void
    {
        $session = [];
        for ($id = 1; $id <= 21; $id++) {
            rental_booking_note($session, 'saldi_7', $id);
        }

        $this->assertFalse(rental_booking_noted($session, 1, 'saldi_7'), 'the oldest of 21 is dropped');
        foreach (range(2, 21) as $id) {
            $this->assertTrue(rental_booking_noted($session, $id, 'saldi_7'));
        }
    }

    /**
     * rental_booking_note_in_session() for booking 41 at saldi_7, on a session whose save handler answers $saves to
     * every write, as the session files answer false when they cannot be written. Answers what it returned and what the
     * handler was given to write. The tests that call it run in a process of their own: a session can be started only
     * before any output, and other tests of a run print theirs.
     *
     * @return array{noted:bool, written:list<string>}
     */
    private function noteInSessionSavedAs(bool $saves): array
    {
        if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
            $this->markTestSkipped('a session can be started here only while none is active and before any output');
        }
        $handler = new class ($saves) implements SessionHandlerInterface {
            /** @var list<string> */
            public array $written = [];

            public function __construct(private bool $saves)
            {
            }

            public function open(string $path, string $name): bool
            {
                return true;
            }

            public function close(): bool
            {
                return true;
            }

            public function read(string $id): string|false
            {
                return '';
            }

            public function write(string $id, string $data): bool
            {
                $this->written[] = $data;
                return $this->saves;
            }

            public function destroy(string $id): bool
            {
                return true;
            }

            public function gc(int $max_lifetime): int|false
            {
                return 0;
            }
        };
        $ini = [];
        foreach (['session.save_handler', 'session.use_cookies', 'session.cache_limiter'] as $name) {
            $ini[$name] = (string) ini_get($name);
        }
        ini_set('session.use_cookies', '0'); // no cookie or cache headers from the command line
        ini_set('session.cache_limiter', '');
        session_set_save_handler($handler, false);
        session_id('chartest' . bin2hex(random_bytes(12)));
        try {
            $noted = @rental_booking_note_in_session('saldi_7', 41); // @: a refused write is also reported as a warning
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_abort();
            }
            $_SESSION = [];
            foreach ($ini as $name => $value) {
                ini_set($name, $value);
            }
            session_id('');
        }
        return ['noted' => $noted, 'written' => $handler->written];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_booking_noted_in_a_session_that_is_saved_is_answered_true(): void
    {
        $res = $this->noteInSessionSavedAs(true);

        $this->assertTrue($res['noted']);
        $this->assertCount(1, $res['written'], 'the session is written once');
        $this->assertStringContainsString('rentalBookings', $res['written'][0], 'with the booking in it');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_booking_noted_in_a_session_that_cannot_be_saved_is_answered_false(): void
    {
        $res = $this->noteInSessionSavedAs(false);

        $this->assertCount(1, $res['written'], 'the session was to be written with the booking');
        $this->assertFalse($res['noted'], 'createBooking then makes no hold, which updateBooking could not act on');
    }
}
