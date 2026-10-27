<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261023 CL/LH MB-94 Pins which fiscal years active_fiscal_years() and newest_active_fiscal_year()
//                  offer. systemdata/fiscalYearInc/deleteFiscalYear.php marks a deleted year with a
//                  Unix timestamp in grupper.box10 (older deletions and the 5-year auto-mark use 'on'),
//                  and systemdata/regnskabsaar.php treats only an empty box10 as a live year.
//                  The real SQL runs against an in-memory SQLite table, and also against PostgreSQL
//                  when ACTIVE_FISCAL_YEARS_PG is set to a pg_connect() string for a throwaway
//                  database (the test creates and drops a temporary table only).
// 20261027 CL/LH MB-94 A backend that is expected to run now fails instead of skipping: SQLite always,
//                  PostgreSQL once ACTIVE_FISCAL_YEARS_PG is set. Only an unset ACTIVE_FISCAL_YEARS_PG skips.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ActiveFiscalYearsTest extends TestCase
{
    /**
     * kodenr, beskrivelse, box1 (start month), box2 (start year), box5 (bookkeeping allowed), box10 (deleted marker).
     * Synthetic years; 1729500000 is a date('U') value as deleteFiscalYear.php writes it.
     */
    private const YEARS = [
        [1, '2018', '1', '2018', 'on', '1729500000'],
        [2, '2019', '1', '2019', 'on', 'on'],
        [3, '2024', '1', '2024', '', null],
        [4, '2025', '1', '2025', 'on', ''],
        [5, '2026', '1', '2026', 'on', null],
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function backends(): array
    {
        return ['sqlite' => ['sqlite'], 'postgresql' => ['pgsql']];
    }

    /**
     * Loads std_func.php with db_select()/db_fetch_array() routed to a fixture grupper table.
     *
     * @param array<int, array{0: int, 1: string, 2: string, 3: string, 4: string, 5: string|null}> $years
     */
    private function load(string $backend, array $years): void
    {
        if ($backend === 'sqlite') {
            if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                self::fail('pdo_sqlite is required for the SQLite fixture.');
            }
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE grupper (id INTEGER, kodenr INTEGER, beskrivelse TEXT, art TEXT, box1 TEXT, box2 TEXT, box5 TEXT, box10 TEXT)');
            $insert = $pdo->prepare("INSERT INTO grupper VALUES (?, ?, ?, 'RA', ?, ?, ?, ?)");
            foreach ($years as $year) {
                $insert->execute([$year[0], ...$year]);
            }
            $GLOBALS['activeFiscalYearsTestSelect'] = function (string $sql) use ($pdo) {
                return $pdo->query($sql);
            };
            $GLOBALS['activeFiscalYearsTestFetch'] = function ($result) {
                return $result->fetch(PDO::FETCH_BOTH);
            };
        } else {
            $dsn = (string) getenv('ACTIVE_FISCAL_YEARS_PG');
            if ($dsn === '') {
                self::markTestSkipped('Set ACTIVE_FISCAL_YEARS_PG to a pg_connect() string to run against PostgreSQL.');
            }
            if (!function_exists('pg_connect')) {
                self::fail('ACTIVE_FISCAL_YEARS_PG is set, but the pgsql extension (pg_connect) is not loaded.');
            }
            $pg = pg_connect($dsn);
            self::assertNotFalse($pg, 'could not connect to ACTIVE_FISCAL_YEARS_PG');
            pg_query($pg, 'CREATE TEMPORARY TABLE grupper (id integer, kodenr integer, beskrivelse text, art text, box1 text, box2 text, box5 text, box10 text)');
            foreach ($years as $year) {
                pg_query_params($pg, "INSERT INTO grupper VALUES ($1, $2, $3, 'RA', $4, $5, $6, $7)", [$year[0], ...$year]);
            }
            $GLOBALS['activeFiscalYearsTestSelect'] = function (string $sql) use ($pg) {
                return pg_query($pg, $sql);
            };
            $GLOBALS['activeFiscalYearsTestFetch'] = function ($result) {
                return pg_fetch_array($result);
            };
        }
        require_once __DIR__ . '/support/active_fiscal_years_database.php';
        require_once dirname(__DIR__, 4) . '/includes/std_func.php';
    }

    /**
     * @return int[] kodenr of the years active_fiscal_years() returns, in its order.
     */
    private static function kodenrs(): array
    {
        return array_map(function ($r) { return (int) $r['kodenr']; }, active_fiscal_years());
    }

    #[DataProvider('backends')]
    public function testOnlyOpenYearsWithAnEmptyDeletedMarkerAreOfferedNewestFirst(string $backend): void
    {
        $this->load($backend, self::YEARS);

        // 1 (deleted, timestamp) and 2 (deleted, 'on') stay out even though box5 is still 'on'; 3 is closed.
        self::assertSame([5, 4], self::kodenrs());
        self::assertSame(['kodenr', 'beskrivelse'], array_keys(active_fiscal_years()[0]));
        self::assertSame('2026', active_fiscal_years()[0]['beskrivelse']);
    }

    #[DataProvider('backends')]
    public function testAYearDeletedWithATimestampIsNeverTheNewestActiveYear(string $backend): void
    {
        $this->load($backend, [
            [1, '2018', '1', '2018', 'on', '1729500000'],
            [3, '2024', '1', '2024', '', null],
        ]);

        self::assertSame([], self::kodenrs());
        self::assertSame(0, newest_active_fiscal_year());
    }

    #[DataProvider('backends')]
    public function testAYearWithNullOrEmptyDeletedMarkerIsActive(string $backend): void
    {
        $this->load($backend, [
            [1, '2025', '1', '2025', 'on', ''],
            [2, '2025b', '7', '2025', 'on', null],
        ]);

        // Same start year: the later start month comes first.
        self::assertSame([2, 1], self::kodenrs());
        self::assertSame(2, newest_active_fiscal_year());
    }
}
