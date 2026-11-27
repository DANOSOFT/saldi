<?php
// 20261113 CL/ASR Pin which grupper art='MR' row vat_report_range() reads for a fiscal year, and when it returns no range.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class VatReportRangeTest extends TestCase
{
    private object $state;

    protected function setUp(): void
    {
        // The stubbed database returns the MR rows in id order, as the helper's query asks for.
        $this->state = (object) ['rows' => [], 'pending' => [], 'queries' => []];
        $GLOBALS['vatReportRangeState'] = $this->state;
        if (!function_exists('db_select')) {
            function db_select($sql, $location = '') {
                $state = $GLOBALS['vatReportRangeState'];
                $state->queries[] = $sql;
                $state->pending = $state->rows;
                return 'vat-report-range-result';
            }
            function db_fetch_array($result) {
                $state = $GLOBALS['vatReportRangeState'];
                return $state->pending ? array_shift($state->pending) : false;
            }
        }
        require_once __DIR__ . '/../../../includes/vatReportRange.php';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['vatReportRangeState']);
    }

    /** @param array<int, array{0: int|null, 1: string|null, 2: string|null}> $rows fiscal_year, box1, box2 in id order */
    private function rows(array $rows): void
    {
        $this->state->rows = array_map(static function (array $row): array {
            return ['box1' => $row[1], 'box2' => $row[2], 'fiscal_year' => $row[0] === null ? null : (string) $row[0]];
        }, $rows);
    }

    public function testTheReportYearsOwnRowWins(): void
    {
        $this->rows([[4, '6780', '7740'], [6, '7680', '7840'], [5, '7680', '7830'], [3, '1', '2']]);
        self::assertSame(['from' => '7680', 'to' => '7830', 'fiscal_year' => 5], vat_report_range(5));
        self::assertCount(1, $this->state->queries);
        self::assertSame("select box1, box2, fiscal_year from grupper where art = 'MR' order by id", $this->state->queries[0]);
    }

    public function testWithoutItsOwnRowTheLatestEarlierYearIsUsed(): void
    {
        $this->rows([[2, '6780', '7740'], [7, '7680', '7840'], [3, '7680', '7830']]);
        self::assertSame(['from' => '7680', 'to' => '7830', 'fiscal_year' => 3], vat_report_range(5));
    }

    public function testWhenEveryRowIsLaterTheLatestYearIsUsed(): void
    {
        $this->rows([[9, '7680', '7830'], [7, '6780', '7740']]);
        self::assertSame(['from' => '7680', 'to' => '7830', 'fiscal_year' => 9], vat_report_range(5));
    }

    public function testTheFirstRowOfAYearWinsAndTheBoundsAreTrimmed(): void
    {
        $this->rows([[5, ' 7680 ', '7830 '], [5, '6780', '7740']]);
        self::assertSame(['from' => '7680', 'to' => '7830', 'fiscal_year' => 5], vat_report_range(5));
    }

    public function testARowWithoutAFiscalYearCountsAsYearZero(): void
    {
        $this->rows([[null, '7680', '7830']]);
        self::assertSame(['from' => '7680', 'to' => '7830', 'fiscal_year' => 0], vat_report_range(1));
    }

    /** @return array<string, array{0: array<int, array{0: int|null, 1: string|null, 2: string|null}>}> */
    public static function noRange(): array
    {
        return [
            'no MR row' => [[]],
            'empty from' => [[[5, '', '7830']]],
            'empty to' => [[[5, '7680', '']]],
            'blank from' => [[[5, '  ', '7830']]],
            'null to' => [[[5, '7680', null]]],
            // An emptied range for the year itself is not replaced by an earlier year's range.
            'own row empty, earlier row set' => [[[4, '7680', '7830'], [5, '', '']]],
            // The fallback row is not skipped either when its range is empty.
            'fallback row empty' => [[[2, '7680', '7830'], [3, '', '7830']]],
        ];
    }

    /** @param array<int, array{0: int|null, 1: string|null, 2: string|null}> $rows */
    #[DataProvider('noRange')]
    public function testNoRangeGivesNull(array $rows): void
    {
        $this->rows($rows);
        self::assertNull(vat_report_range(5));
    }
}
