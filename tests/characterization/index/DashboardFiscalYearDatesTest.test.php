<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Oversigt's key figures for an earlier fiscal year chosen in the top bar (testlab round 2, dashboard-figures, 2026-11-05).
 *
 * The old widgets (index/dashboardIncludes/revenue_week.php, revenue_month.php, revenue_year.php) placed today's day and
 * month in the active fiscal year. The rebuilt dashboard (index/dashboardIncludes/dashData.php, a1a830862) took the week,
 * the month and the VAT period from the server's date and ended the year at min(year end, today). With 2025 active on
 * 2026-11-08 the lab saw this week 0 kr against 1.492 kr "samme uge sidste år" (week 45 of 2026 against 2025), this month
 * 0 kr, VAT 1.10.-31.12.2026 = 0 kr, and the whole of 2025 compared with the whole of 2024 instead of each to 8 November.
 *
 * Pins: for an earlier year the week, month, year to date and VAT period run to today placed in that year, against the
 * same days a year before; the week chart shows that week with all its days, and the month chart keeps the year's whole
 * months. For the year holding today nothing changes: the figures stop at today, and so do the charts. A later year runs
 * to today placed in it. dash_fiscal_today() places a day in a July-June year, a short year and on 29 February.
 *
 * dash_data() runs whole on an in-memory sqlite database shaped like the tables it reads, with the real
 * vat_period_range() from includes/std_func.php.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class DashboardFiscalYearDatesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** Revenue on account 1100 (credit), as in the lab's book: 2024 kodenr 3, 2025 kodenr 4, both January-December. */
    private const POSTINGS = [
        ['2024-03-10', 20000.00],
        ['2024-11-02', 1000.00],
        ['2024-11-05', 500.00],    // Tuesday of ISO week 45 of 2024
        ['2024-12-01', 30000.00],  // after 8 November: not in 2024 to date
        ['2025-02-10', 40000.00],
        ['2025-11-01', 5734.40],
        ['2025-11-03', 1143.20],   // Monday of ISO week 45 of 2025
        ['2025-11-04', 348.80],
        ['2025-11-20', 200.00],    // after 8 November: in the month chart of 2025, not in its figures to date
        ['2025-12-15', 300.00],
        ['2026-11-03', 999.00],    // ISO week 45 of 2026, the server's week in the lab
    ];

    private static function boot(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
        $db->exec("CREATE TABLE transaktioner (id INTEGER PRIMARY KEY, transdate TEXT, kontonr INTEGER, debet NUMERIC, kredit NUMERIC)");
        $db->exec("CREATE TABLE grupper (id INTEGER PRIMARY KEY, art TEXT, kodenr TEXT, fiscal_year TEXT, beskrivelse TEXT, box1 TEXT, box2 TEXT, box4 TEXT)");
        $db->exec("CREATE TABLE ordrer (id INTEGER PRIMARY KEY, art TEXT, status INTEGER, ordredate TEXT, fakturadate TEXT, levdate TEXT, tidspkt TEXT, firmanavn TEXT, sum NUMERIC)");
        $db->exec("CREATE TABLE ordrelinjer (id INTEGER PRIMARY KEY, ordre_id INTEGER, varenr TEXT, pris NUMERIC, antal NUMERIC, rabat NUMERIC)");
        $db->exec("CREATE TABLE varer (id INTEGER PRIMARY KEY, varenr TEXT, gruppe TEXT)");
        $db->exec("INSERT INTO grupper (art, kodenr, fiscal_year, beskrivelse, box1, box2) VALUES ('RA', '3', '0', '2024', '1', '2024'),
            ('RA', '4', '0', '2025', '1', '2025'), ('MR', '1', '3', 'Moms', '1000', '2000'), ('MR', '1', '4', 'Moms', '1000', '2000')");
        $insert = $db->prepare("INSERT INTO transaktioner (transdate, kontonr, debet, kredit) VALUES (?, 1100, 0, ?)");
        foreach (self::POSTINGS as [$date, $amount]) {
            $insert->execute([$date, $amount]);
        }
        $GLOBALS['dashDb'] = $db;
        $GLOBALS['regnaar'] = 4;
        eval('
            function db_select($q, $where = "") { return $GLOBALS["dashDb"]->query($q); }
            function db_fetch_array($r) { return $r === false ? false : $r->fetch(PDO::FETCH_ASSOC); }
            function db_escape_string($s) { return str_replace("\'", "\'\'", (string) $s); }
            function findtekst($key, $sprog_id = 1) { $p = explode("|", (string) $key, 2); return $p[1] ?? $p[0]; }
        ');
        require_once self::ROOT . '/includes/std_func.php'; // vat_period_range()
        require_once self::ROOT . '/index/dashboardIncludes/dashData.php';
    }

    /**
     * dash_data() for fiscal year 2025 (kodenr 4) on $today, as index/dashboard.php calls it.
     *
     * @return array<string, mixed>
     */
    private static function dashboard(string $today): array
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the isolated dashboard database.');
        }
        self::boot();
        return dash_data(['regnstart' => '2025-01-01', 'regnslut' => '2025-12-31', 'regnaar' => 4, 'kontomin' => 0, 'kontomaks' => 2000,
            'sprog_id' => 1, 'bruger_id' => 1, 'vat' => ['registered' => true, 'period' => 'quarter'], 'today' => $today]);
    }

    public function testAnEarlierYearShowsItsOwnWeekMonthYearToDateAndVatPeriod(): void
    {
        // The lab's case: 2025 active on Sunday 2026-11-08, so today in 2025 is Saturday 2025-11-08.
        $d = self::dashboard('2026-11-08');

        $this->assertEqualsWithDelta(1492.00, $d['weekkpi']['value'], 0.001, 'this week is ISO week 45 of 2025 (3-9 November)');
        $this->assertEqualsWithDelta(500.00, $d['weekkpi']['last'], 0.001, 'against ISO week 45 of 2024');
        $this->assertEqualsWithDelta(7226.40, $d['revmonth']['value'], 0.001, 'this month is 1-8 November 2025');
        $this->assertEqualsWithDelta(1500.00, $d['revmonth']['last'], 0.001, 'against 1-8 November 2024');
        $this->assertEqualsWithDelta(47226.40, $d['revyear']['value'], 0.001, '2025 to 8 November');
        $this->assertEqualsWithDelta(21500.00, $d['revyear']['last'], 0.001, '2024 to 8 November, not the whole of 2024');

        $vat = $d['vatcount'];
        $this->assertSame(['2025-10-01', '2025-12-31', '2026-03-01'], [$vat['from'], $vat['to'], $vat['deadline']], 'the VAT period holding 8 November 2025');
        $this->assertEqualsWithDelta(7226.40, $vat['value'], 0.001, 'the VAT period to 8 November 2025');
        $this->assertEqualsWithDelta(1500.00, $vat['last'], 0.001);

        $week = $d['weeks']['list'][0];
        $this->assertSame([45, 2025, 2024], [$week['no'], $week['year'], $week['lastYear']], 'the week chart opens on that week of 2025');
        $this->assertEqualsWithDelta([1143.20, 348.80, 0, 0, 0, 0, 0], $week['now'], 0.001);
        $this->assertEqualsWithDelta([0, 500.00, 0, 0, 0, 0, 0], $week['last'], 0.001);
        $this->assertSame([44, 43], array_column(array_slice($d['weeks']['list'], 1), 'no'));
        $this->assertSame(7, $d['weeks']['dayToDate'], 'every day of a past week has its figure');

        $this->assertSame(12, $d['month']['toDate'], 'every month of a past year has its figure');
        $this->assertEqualsWithDelta(7426.40, $d['month']['now'][10], 0.001, 'November 2025 whole');
        $this->assertEqualsWithDelta(300.00, $d['month']['now'][11], 0.001, 'December 2025');
        $this->assertEqualsWithDelta(31500.00, $d['month']['last'][10] + $d['month']['last'][11], 0.001, 'November and December 2024');
    }

    /**
     * The year holding today, and a later year (today before it starts): both run to the same day, 8 November 2025, and
     * nothing after it counts yet.
     *
     * @return array<string, array{string}>
     */
    public static function yearsRunningToToday(): array
    {
        return ['the year holding today' => ['2025-11-08'], 'a later year' => ['2024-11-08']];
    }

    #[DataProvider('yearsRunningToToday')]
    public function testTheYearHoldingTodayAndALaterYearRunToTodayInThatYear(string $today): void
    {
        $d = self::dashboard($today);

        $this->assertEqualsWithDelta(1492.00, $d['weekkpi']['value'], 0.001);
        $this->assertEqualsWithDelta(500.00, $d['weekkpi']['last'], 0.001);
        $this->assertEqualsWithDelta(7226.40, $d['revmonth']['value'], 0.001);
        $this->assertEqualsWithDelta(1500.00, $d['revmonth']['last'], 0.001);
        $this->assertEqualsWithDelta(47226.40, $d['revyear']['value'], 0.001);
        $this->assertEqualsWithDelta(21500.00, $d['revyear']['last'], 0.001);
        $this->assertSame(['2025-10-01', '2025-12-31'], [$d['vatcount']['from'], $d['vatcount']['to']]);
        $this->assertEqualsWithDelta(7226.40, $d['vatcount']['value'], 0.001);

        $this->assertSame([45, 2025], [$d['weeks']['list'][0]['no'], $d['weeks']['list'][0]['year']]);
        $this->assertSame(6, $d['weeks']['dayToDate'], 'Monday to Saturday 8 November');
        $this->assertSame(11, $d['month']['toDate'], 'January to November');
        $this->assertEqualsWithDelta(7226.40, $d['month']['now'][10], 0.001, 'November to the 8th: nothing after it counts yet');
        $this->assertEqualsWithDelta(0.0, $d['month']['now'][11], 0.001);
    }

    /**
     * @return array<string, array{string, string, string, string}> today, fiscal start, fiscal end, today placed in the year
     */
    public static function placements(): array
    {
        return [
            'the year holds today' => ['2026-11-08', '2026-01-01', '2026-12-31', '2026-11-08'],
            'an 18-month year holds today' => ['2026-11-08', '2025-07-01', '2026-12-31', '2026-11-08'],
            'an earlier calendar year' => ['2026-11-08', '2025-01-01', '2025-12-31', '2025-11-08'],
            'a later calendar year' => ['2024-11-08', '2025-01-01', '2025-12-31', '2025-11-08'],
            'July-June, before New Year' => ['2026-09-15', '2024-07-01', '2025-06-30', '2024-09-15'],
            'July-June, after New Year' => ['2026-03-15', '2024-07-01', '2025-06-30', '2025-03-15'],
            '29 February in a common year' => ['2028-02-29', '2025-01-01', '2025-12-31', '2025-02-28'],
            'a short year ends before the day' => ['2026-03-15', '2025-07-01', '2025-12-31', '2025-12-31'],
        ];
    }

    #[DataProvider('placements')]
    public function testTodayIsPlacedInTheFiscalYear(string $today, string $fyStart, string $fyEnd, string $expected): void
    {
        require_once self::ROOT . '/index/dashboardIncludes/dashData.php';
        $this->assertSame($expected, dash_fiscal_today($today, $fyStart, $fyEnd));
    }
}
