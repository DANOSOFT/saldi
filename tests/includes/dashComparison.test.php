<?php
// 20261113 CL/ASR Pin when Omsætning pr. dag (index/dashboard.php) compares a week with the same week last year: dash_week_comparable().

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../index/dashboardIncludes/dashData.php';

/**
 * A week is compared with the same ISO week last year when the previous fiscal year exists, and without one (the first
 * fiscal year) only when that week last year ends on or after the start of this fiscal year: before it no fiscal year
 * holds figures, so the chart would compare with zeros under a "Samme uge 2025" legend.
 */
final class dashComparison extends TestCase
{
    /**
     * @return array<string, array{bool, string, string, bool}>
     */
    public static function weeks(): array
    {
        return array(
            // Fjordlys (ssl12 test_441): the first fiscal year 2026, week 46 of 2026 against week 46 of 2025.
            'first year, week last year before it' => array(false, '2025-11-10', '2026-01-01', false),
            'previous fiscal year exists' => array(true, '2025-11-10', '2026-01-01', true),
            // A first fiscal year that began earlier (18 months from July 2025): last year's week is inside it.
            'first year, week last year inside it' => array(false, '2025-11-10', '2025-07-01', true),
            // The week last year runs Monday 29 December to Sunday 4 January: its last days are in this fiscal year.
            'first year, week last year across its start' => array(false, '2025-12-29', '2026-01-01', true),
            'first year, week last year ends the day before' => array(false, '2025-12-22', '2025-12-29', false),
            'first year, week last year ends on its first day' => array(false, '2025-12-22', '2025-12-28', true),
        );
    }

    #[DataProvider('weeks')]
    public function testWeekComparable(bool $hasLastFy, string $lyMonday, string $fyStart, bool $expected): void
    {
        self::assertSame($expected, dash_week_comparable($hasLastFy, $lyMonday, $fyStart));
    }
}
