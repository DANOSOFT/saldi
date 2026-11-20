<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/stdFunc/rentalClosedDays.php';

// 20261111 CL/MMR SST-999 Pins how the public booking page and mysale move the end of a booking for closed days.
//                 rentalclosed.day holds the unix time of the day at local midnight. The bookings used strtotime() on it,
//                 which gave false, so no closed day was counted. The last test checks that both createBooking use the helper.
final class RentalClosedDaysTest extends TestCase
{
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

    private static function day(string $ymd, int $hour = 0): int
    {
        return (int)strtotime($ymd) + $hour * 3600;
    }

    /** @return array<string, array{string, string, list<string>, string}> from, to, closed days, expected end */
    public static function bookings(): array
    {
        return [
            'no closed days'                 => ['2026-12-20', '2026-12-27', [], '2026-12-27'],
            'one closed day inside'          => ['2026-12-20', '2026-12-27', ['2026-12-24'], '2026-12-28'],
            'closed on the start day'        => ['2026-12-20', '2026-12-27', ['2026-12-20'], '2026-12-28'],
            'closed on the end day'          => ['2026-12-20', '2026-12-27', ['2026-12-27'], '2026-12-28'],
            'closed before and after'        => ['2026-12-20', '2026-12-27', ['2026-12-19', '2026-12-29'], '2026-12-27'],
            'two inside'                     => ['2026-12-20', '2026-12-27', ['2026-12-24', '2026-12-25'], '2026-12-29'],
            'move reaches the next day'      => ['2026-12-20', '2026-12-27', ['2026-12-28', '2026-12-24'], '2026-12-29'],
            'move reaches a chain'           => ['2026-12-20', '2026-12-27', ['2026-12-29', '2026-12-24', '2026-12-28'], '2026-12-30'],
            'across the DST change'          => ['2027-03-25', '2027-04-01', ['2027-03-28'], '2027-04-02'],
        ];
    }

    /** @param list<string> $closed */
    #[DataProvider('bookings')]
    public function testEndIsMovedForClosedDays(string $from, string $to, array $closed, string $expected): void
    {
        $days = array_map(static fn(string $d): int => self::day($d), $closed);
        $end = rental_extend_for_closed_days(self::day($from), self::day($to), $days);
        self::assertSame(self::day($expected), $end);
        self::assertSame($expected, date('Y-m-d', $end));
    }

    public function testDayValuesAsTheDatabaseReturnsThem(): void
    {
        // db_fetch_array returns the integer column as a string
        $end = rental_extend_for_closed_days(self::day('2026-12-20'), self::day('2026-12-27'), [(string)self::day('2026-12-24')]);
        self::assertSame('2026-12-28', date('Y-m-d', $end));
    }

    public function testSameDayStoredTwiceCountsOnce(): void
    {
        $closed = [self::day('2026-12-24'), self::day('2026-12-24', 12)];
        $end = rental_extend_for_closed_days(self::day('2026-12-20'), self::day('2026-12-27'), $closed);
        self::assertSame('2026-12-28', date('Y-m-d', $end));
    }

    public function testEmptyAndInvalidValuesAreSkipped(): void
    {
        $end = rental_extend_for_closed_days(self::day('2026-12-20'), self::day('2026-12-27'), [null, '', 'x']);
        self::assertSame('2026-12-27', date('Y-m-d', $end));
    }

    public function testInvalidPeriodIsReturnedUnchanged(): void
    {
        self::assertFalse(rental_extend_for_closed_days(false, false, [self::day('2026-12-24')]));
    }

    public function testBothBookingApisUseTheHelper(): void
    {
        foreach (['remoteBooking/api.php', 'mysale/api.php'] as $file) {
            $src = (string)file_get_contents(__DIR__ . '/../../../' . $file);
            self::assertStringContainsString('stdFunc/rentalClosedDays.php', $src, $file);
            self::assertStringContainsString('$to = rental_extend_for_closed_days($from, $to, rental_closed_days());', $src, $file);
            self::assertStringNotContainsString('strtotime($row["day"])', $src, $file);
        }
    }
}
