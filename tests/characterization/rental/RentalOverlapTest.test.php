<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/stdFunc/rentalOverlap.php';

// 20261111 CL/MMR SST-1000 Pins when an existing booking or reservation blocks a new booking of the same unit.
//                 The rule is the booking page's (remoteBooking/index.js onDayCreate): overlap with both days included.
//                 An unpaid booking whose expiry_time has passed does not block.
//                 Request days must be 'Y-m-d' calendar dates, and an existing period is compared by the days it falls on.
//                 The last test checks that both createBooking store through rental_store_booking() and answer 409.
final class RentalOverlapTest extends TestCase
{
    private const NOW = 1794481200; // 2026-11-12 12:00 Europe/Copenhagen

    private static string $tz;

    public static function setUpBeforeClass(): void
    {
        self::$tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Copenhagen');
    }

    public static function tearDownAfterClass(): void
    {
        date_default_timezone_set(self::$tz);
    }

    private static function day(string $ymd): int
    {
        return (int)strtotime($ymd);
    }

    /** @return array<string, array{string, string, bool}> existing from, existing to, blocks a new 2026-11-13 .. 2026-11-20 */
    public static function periods(): array
    {
        return [
            'ends the day before'     => ['2026-11-06', '2026-11-12', false],
            'ends on the start day'   => ['2026-11-06', '2026-11-13', true],
            'inside'                  => ['2026-11-15', '2026-11-16', true],
            'covers the whole period' => ['2026-11-01', '2026-11-30', true],
            'starts on the end day'   => ['2026-11-20', '2026-11-27', true],
            'starts the day after'    => ['2026-11-21', '2026-11-27', false],
            'same period'             => ['2026-11-13', '2026-11-20', true],
        ];
    }

    #[DataProvider('periods')]
    public function testOverlapBothDaysIncluded(string $rowFrom, string $rowTo, bool $blocks): void
    {
        $from = self::day('2026-11-13');
        $to = self::day('2026-11-20');
        // as the database returns numeric(15,0): a string
        self::assertSame($blocks, rental_period_blocks((string)self::day($rowFrom), (string)self::day($rowTo), $from, $to, null, self::NOW));
    }

    public function testUnpaidBookingBlocksUntilItExpires(): void
    {
        $from = self::day('2026-11-13');
        $to = self::day('2026-11-20');
        $rowFrom = self::day('2026-11-15');
        $rowTo = self::day('2026-11-22');
        self::assertTrue(rental_period_blocks($rowFrom, $rowTo, $from, $to, '2026-11-12 12:05:00', self::NOW), 'still waiting for payment');
        self::assertFalse(rental_period_blocks($rowFrom, $rowTo, $from, $to, '2026-11-12 11:55:00', self::NOW), 'payment window ran out');
        self::assertTrue(rental_period_blocks($rowFrom, $rowTo, $from, $to, '', self::NOW), 'empty expiry = paid');
    }

    public function testPeriodWithoutDaysDoesNotBlock(): void
    {
        self::assertFalse(rental_period_blocks(null, null, self::day('2026-11-13'), self::day('2026-11-20'), null, self::NOW));
        self::assertFalse(rental_period_blocks('', '', self::day('2026-11-13'), self::day('2026-11-20'), null, self::NOW));
    }

    public function testExistingPeriodAtAnotherHourCountsOnItsDays(): void
    {
        // an existing booking stored from noon on 13/11 to 23:59:59 on 20/11 blocks a new booking from 20/11 and one to 13/11
        $rowFrom = self::day('2026-11-13') + 12 * 3600;
        $rowTo = self::day('2026-11-20') + 86399;
        self::assertTrue(rental_period_blocks($rowFrom, $rowTo, self::day('2026-11-20'), self::day('2026-11-27'), null, self::NOW));
        self::assertTrue(rental_period_blocks($rowFrom, $rowTo, self::day('2026-11-06'), self::day('2026-11-13'), null, self::NOW));
        self::assertFalse(rental_period_blocks($rowFrom, $rowTo, self::day('2026-11-21'), self::day('2026-11-28'), null, self::NOW));
    }

    /** @return array<string, array{string, string}> first and last day of a new booking around the summer time changes */
    public static function dstBookings(): array
    {
        return [
            'last day is the 25-hour day (2027)'  => ['2027-10-24', '2027-10-31'],
            'first day is the 25-hour day (2026)' => ['2026-10-25', '2026-11-01'],
            'last day is the 23-hour day (2027)'  => ['2027-03-21', '2027-03-28'],
            'first day is the 23-hour day (2027)' => ['2027-03-28', '2027-04-04'],
        ];
    }

    #[DataProvider('dstBookings')]
    public function testSearchCoversEveryHourOfTheFirstAndLastDay(string $first, string $last): void
    {
        [$wideFrom, $wideTo] = rental_search_bounds(self::day($first), self::day($last));
        // a period that ends at 00:00 on the first day and one that starts at 23:30 on the last day must both be read
        self::assertLessThanOrEqual(self::day($first), $wideFrom);
        $lastLate = (new DateTimeImmutable($last . ' 23:30', new DateTimeZone('Europe/Copenhagen')))->getTimestamp();
        self::assertGreaterThanOrEqual($lastLate, $wideTo);
    }

    /** @return array<string, array{mixed, string|false}> request value, expected day or false */
    public static function requestDays(): array
    {
        return [
            'calendar date'        => ['2026-11-20', '2026-11-20'],
            'leap day'             => ['2028-02-29', '2028-02-29'],
            'time of day'          => ['2026-11-20 12:00:00', false],
            'ISO with zone'        => ['2026-11-20T00:00:00+01:00', false],
            'relative'             => ['+1 week', false],
            'not a calendar date'  => ['2026-02-30', false],
            'trailing newline'     => ["2026-11-20\n", false],
            'number'               => [1794438000, false],
            'empty'                => ['', false],
            'missing'              => [null, false],
        ];
    }

    #[DataProvider('requestDays')]
    public function testRequestDaysAreWholeCalendarDays(mixed $value, string|false $expected): void
    {
        $day = rental_booking_day($value);
        if ($expected === false) {
            self::assertFalse($day);
        } else {
            self::assertSame(self::day($expected), $day);
        }
    }

    public function testBothBookingApisStoreThroughTheCheck(): void
    {
        foreach (['remoteBooking/api.php', 'mysale/api.php'] as $file) {
            $src = (string)file_get_contents(__DIR__ . '/../../../' . $file);
            self::assertStringContainsString('stdFunc/rentalOverlap.php', $src, $file);
            self::assertStringContainsString('rental_store_booking($data["item_id"], $data["cust_id"], $from, $to, $expiryTime)', $src, $file);
            self::assertStringContainsString('http_response_code(409)', $src, $file);
            self::assertStringNotContainsString('INSERT INTO rentalperiod', $src, $file);
            self::assertStringContainsString('function handleCreateBooking()', $src, $file);
            self::assertStringContainsString('$from = rental_booking_day(ifset($data, "start_date", ""));', $src, $file);
            self::assertStringContainsString('$to = rental_booking_day(ifset($data, "end_date", ""));', $src, $file);
        }
        foreach (['remoteBooking/index.js', 'mysale/remote.js'] as $file) {
            $src = (string)file_get_contents(__DIR__ . '/../../../' . $file);
            self::assertMatchesRegularExpression('/if \(!response \|\| !response\.id\) \{[^}]*location\.reload\(\)\s*return/s', $src, $file);
        }
    }
}
