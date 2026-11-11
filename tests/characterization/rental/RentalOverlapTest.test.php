<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/stdFunc/rentalOverlap.php';

// 20261111 CL/MMR SST-1000 Pins when an existing booking or reservation blocks a new booking of the same unit.
//                 The rule is the booking page's (remoteBooking/index.js onDayCreate): overlap with both days included.
//                 An unpaid booking whose expiry_time has passed does not block.
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

    public function testBothBookingApisStoreThroughTheCheck(): void
    {
        foreach (['remoteBooking/api.php', 'mysale/api.php'] as $file) {
            $src = (string)file_get_contents(__DIR__ . '/../../../' . $file);
            self::assertStringContainsString('stdFunc/rentalOverlap.php', $src, $file);
            self::assertStringContainsString('rental_store_booking($data["item_id"], $data["cust_id"], $from, $to, $expiryTime)', $src, $file);
            self::assertStringContainsString('http_response_code(409)', $src, $file);
            self::assertStringNotContainsString('INSERT INTO rentalperiod', $src, $file);
        }
        foreach (['remoteBooking/index.js', 'mysale/remote.js'] as $file) {
            $src = (string)file_get_contents(__DIR__ . '/../../../' . $file);
            self::assertMatchesRegularExpression('/if \(!response \|\| !response\.id\) \{[^}]*location\.reload\(\)\s*return/s', $src, $file);
        }
    }
}
