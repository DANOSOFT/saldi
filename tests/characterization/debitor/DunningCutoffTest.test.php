<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * debitor/ny_rykker.php selects the open posts to dun with
 * `forfaldsdate <= $rykkerfrist1`, where $rykkerfrist1 is today minus ffdage1
 * grace days (the same for ffdage2/ffdage3 against ordredate). Both sides of
 * that boundary and the two Europe/Copenhagen DST switches are pinned here.
 *
 * No database: the cutoff is a pure function of the clock, and the query's
 * comparison is mirrored with string comparison on Y-m-d dates.
 *
 * History:
 * 20260828 Sawaneh Created (review of PR #444).
 */
final class DunningCutoffTest extends TestCase
{
    private const FFDAGE1 = 8;

    private string $tz;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/stdFunc/dunningCutoff.php';
    }

    protected function setUp(): void
    {
        $this->tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Copenhagen');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->tz);
    }

    private static function copenhagen(string $when): DateTimeImmutable
    {
        return new DateTimeImmutable($when, new DateTimeZone('Europe/Copenhagen'));
    }

    /** Mirrors `openpost.forfaldsdate <= '$rykkerfrist1'` in ny_rykker.php. */
    private static function isDunned(string $dueDate, string $cutoff): bool
    {
        return $dueDate <= $cutoff;
    }

    public function test_cutoff_is_today_minus_the_grace_days(): void
    {
        $now = self::copenhagen('2026-06-15 14:05:00');
        self::assertSame('2026-06-07', dunning_cutoff_date(self::FFDAGE1, $now));
    }

    public function test_an_invoice_overdue_by_ffdage1_plus_one_day_is_dunned(): void
    {
        $now = self::copenhagen('2026-06-15 14:05:00');
        $cutoff = dunning_cutoff_date(self::FFDAGE1, $now);
        $due = $now->modify('-' . (self::FFDAGE1 + 1) . ' days')->format('Y-m-d');
        self::assertSame('2026-06-06', $due);
        self::assertTrue(self::isDunned($due, $cutoff));
    }

    public function test_an_invoice_overdue_by_exactly_ffdage1_days_is_dunned(): void
    {
        $now = self::copenhagen('2026-06-15 14:05:00');
        $cutoff = dunning_cutoff_date(self::FFDAGE1, $now);
        $due = $now->modify('-' . self::FFDAGE1 . ' days')->format('Y-m-d');
        self::assertSame($cutoff, $due);
        self::assertTrue(self::isDunned($due, $cutoff));
    }

    public function test_an_invoice_inside_the_grace_period_is_not_dunned(): void
    {
        $now = self::copenhagen('2026-06-15 14:05:00');
        $cutoff = dunning_cutoff_date(self::FFDAGE1, $now);
        $due = $now->modify('-' . (self::FFDAGE1 - 1) . ' days')->format('Y-m-d');
        self::assertFalse(self::isDunned($due, $cutoff));
    }

    /**
     * 2026-03-29 02:00 -> 03:00 (spring forward), 2026-10-25 03:00 -> 02:00 (fall back).
     * A run shortly after midnight on the following day covers a 23/25-hour day
     * inside the grace window, which is where seconds arithmetic lands on the
     * wrong date.
     */
    public static function dstBoundaries(): array
    {
        return [
            'day after spring forward, just past midnight' => ['2026-03-30 00:30:00', '2026-03-22'],
            'day after fall back, just before midnight'    => ['2026-10-26 23:30:00', '2026-10-18'],
        ];
    }

    #[DataProvider('dstBoundaries')]
    public function test_cutoff_counts_calendar_days_across_a_dst_switch(string $when, string $expected): void
    {
        $now = self::copenhagen($when);
        self::assertSame($expected, dunning_cutoff_date(self::FFDAGE1, $now));
    }

    #[DataProvider('dstBoundaries')]
    public function test_seconds_arithmetic_is_off_by_one_day_across_a_dst_switch(string $when, string $expected): void
    {
        // The formula ny_rykker.php used until 20260828; kept as the regression guard.
        $legacy = date('Y-m-d', self::copenhagen($when)->getTimestamp() - self::FFDAGE1 * 3600 * 24);
        self::assertNotSame($expected, $legacy);
    }
}
