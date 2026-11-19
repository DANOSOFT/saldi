<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

// 20261119 CL/MMR SST-999 Review (PR #1097): createBooking on both public booking endpoints, run as a real request.
//                 The calendar checks an item only up to the chosen end. When closed days move the end, the moved period
//                 must not overlap another booking or reservation of the item: another free item of the product is booked,
//                 or the booking is refused with 409 and nothing is stored.
final class RentalBookingOverlapTest extends CharacterizationTestCase
{
    private static int $itemA;
    private static int $itemB;
    private static int $customer;
    private static string $sku;
    private static string $tz;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // includes/settings.php sets Europe/Copenhagen in the endpoint, so the day values here use it too.
        self::$tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Copenhagen');
        $product = self::$fx->item(['stocked' => false, 'salgspris' => 100.00]);
        self::$sku = $product['varenr'];
        self::$customer = self::$fx->debtor()['id'];
        self::$itemA = (int)self::one(
            "INSERT INTO rentalitems (item_name, product_id) VALUES ('SST-999 A', $1) RETURNING id",
            [$product['id']]
        )['id'];
        self::$itemB = (int)self::one(
            "INSERT INTO rentalitems (item_name, product_id) VALUES ('SST-999 B', $1) RETURNING id",
            [$product['id']]
        )['id'];
    }

    public static function tearDownAfterClass(): void
    {
        date_default_timezone_set(self::$tz);
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        self::rows('DELETE FROM rentalperiod');
        self::rows('DELETE FROM rentalreserved');
        self::rows('DELETE FROM rentalclosed');
        self::rows("DELETE FROM ordrelinjer WHERE beskrivelse LIKE 'Udlejning: Fra 2030-12-20 %'");
        // Item B takes part only in the tests that book it free.
        self::rows('UPDATE rentalitems SET product_id = NULL WHERE id = $1', [self::$itemB]);
    }

    private static function day(string $ymd): int
    {
        return (int)strtotime($ymd);
    }

    private static function closeDay(string $ymd): void
    {
        self::rows('INSERT INTO rentalclosed (day) VALUES ($1)', [self::day($ymd)]);
    }

    private static function bookItem(int $item, string $from, string $to): void
    {
        self::rows(
            'INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id) VALUES ($1, $2, $3, $4, 0)',
            [$item, self::$customer, self::day($from), self::day($to)]
        );
    }

    private static function useItemB(): void
    {
        self::rows(
            'UPDATE rentalitems SET product_id = (SELECT product_id FROM rentalitems WHERE id = $1) WHERE id = $2',
            [self::$itemA, self::$itemB]
        );
    }

    /**
     * Book item A for one week from 20 Dec, the way the booking pages send it.
     * @return array{status: int, body: string}
     */
    private function createBooking(string $api): array
    {
        $body = json_encode([
            'item_id' => self::$itemA, 'cust_id' => self::$customer, 'weeks' => 1, 'unit' => 'Uge', 'sku' => self::$sku,
            'start_date' => '2030-12-20', 'end_date' => '2030-12-27', 'price' => 80, 'product_id' => 0,
        ]);
        return $this->runChildJson('run_booking_api.php', [$api, CharacterizationEnv::testDb(), $body]);
    }

    /** @return list<array{item_id: string, rt_from: string, rt_to: string}> Bookings made by the request (not the fixture's). */
    private static function newBookings(): array
    {
        $rows = self::rows('SELECT item_id, rt_from, rt_to FROM rentalperiod WHERE rt_from = $1 ORDER BY id', [self::day('2030-12-20')]);
        return array_map(static fn(array $row): array => [
            'item_id' => (string)$row['item_id'],
            'rt_from' => date('Y-m-d', (int)$row['rt_from']),
            'rt_to' => date('Y-m-d', (int)$row['rt_to']),
        ], $rows);
    }

    private static function orderLines(): int
    {
        return (int)self::one("SELECT count(*) AS n FROM ordrelinjer WHERE beskrivelse LIKE 'Udlejning: Fra 2030-12-20 %'")['n'];
    }

    /** @return array<string, array{string}> */
    public static function apis(): array
    {
        return ['remoteBooking' => ['remoteBooking'], 'mysale' => ['mysale']];
    }

    /** Saul's case: item A is booked from 28 Dec, and 24 Dec is closed. The moved end (28 Dec) is that booking's first day. */
    #[DataProvider('apis')]
    public function testMovedEndOnAnotherBookingIsRefusedWhenNoOtherItemIsFree(string $api): void
    {
        self::bookItem(self::$itemA, '2030-12-28', '2031-01-04');
        self::closeDay('2030-12-24');

        $res = $this->createBooking($api);

        self::assertSame(409, $res['status'], $res['body']);
        self::assertSame([], self::newBookings(), 'nothing may be stored on an item that is booked from 28 Dec');
        self::assertSame(0, self::orderLines(), 'no order may be made for a refused booking');
    }

    #[DataProvider('apis')]
    public function testMovedEndOnAnotherBookingUsesAFreeItemOfTheProduct(string $api): void
    {
        self::useItemB();
        self::bookItem(self::$itemA, '2030-12-28', '2031-01-04');
        self::closeDay('2030-12-24');

        $res = $this->createBooking($api);

        self::assertSame(200, $res['status'], $res['body']);
        self::assertSame(
            [['item_id' => (string)self::$itemB, 'rt_from' => '2030-12-20', 'rt_to' => '2030-12-28']],
            self::newBookings()
        );
    }

    #[DataProvider('apis')]
    public function testMovedEndOnAReservationIsRefused(string $api): void
    {
        self::rows(
            "INSERT INTO rentalreserved (item_id, rr_from, rr_to, comment) VALUES ($1, $2, $3, 'SST-999')",
            [self::$itemA, self::day('2030-12-28'), self::day('2030-12-30')]
        );
        self::closeDay('2030-12-24');

        $res = $this->createBooking($api);

        self::assertSame(409, $res['status'], $res['body']);
        self::assertSame([], self::newBookings());
    }

    /** The extension itself still works when the moved period is free. */
    #[DataProvider('apis')]
    public function testMovedEndIsKeptWhenThePeriodIsFree(string $api): void
    {
        self::bookItem(self::$itemA, '2030-12-29', '2031-01-05');
        self::closeDay('2030-12-24');

        $res = $this->createBooking($api);

        self::assertSame(200, $res['status'], $res['body']);
        self::assertSame(
            [['item_id' => (string)self::$itemA, 'rt_from' => '2030-12-20', 'rt_to' => '2030-12-28']],
            self::newBookings()
        );
        self::assertSame(
            '1',
            self::one("SELECT count(*) AS n FROM ordrelinjer WHERE beskrivelse = 'Udlejning: Fra 2030-12-20 til 2030-12-28'")['n'],
            'the order line shows the moved end'
        );
    }

    /** Without a closed day in the period nothing changes: the period the calendar checked is stored as before. */
    #[DataProvider('apis')]
    public function testWithoutClosedDaysTheChosenPeriodIsStoredUnchanged(string $api): void
    {
        self::bookItem(self::$itemA, '2030-12-28', '2031-01-04');

        $res = $this->createBooking($api);

        self::assertSame(200, $res['status'], $res['body']);
        self::assertSame(
            [['item_id' => (string)self::$itemA, 'rt_from' => '2030-12-20', 'rt_to' => '2030-12-27']],
            self::newBookings()
        );
    }
}
