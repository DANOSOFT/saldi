<?php
// tests/characterization/includes/orderFuncIncludes/GridOrderSortCharacterizationTest.test.php
//
// Pure characterization coverage for shared grid ORDER BY generation.
// 20260804 CDX/NTR Added nullable Genfakt. sorting regression coverage.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../includes/orderFuncIncludes/grid_order.php';

final class GridOrderSortCharacterizationTest extends TestCase
{
    private function gridData(): array
    {
        return [
            'query' => 'SELECT o.id, o.nextfakt FROM ordrer o WHERE {{WHERE}} ORDER BY {{SORT}}',
        ];
    }

    private function columns(): array
    {
        return [
            [
                'field' => 'nextfakt',
                'sqlOverride' => 'o.nextfakt',
                'nullsLast' => true,
            ],
            [
                'field' => 'ordrenr',
                'sqlOverride' => '',
            ],
        ];
    }

    public function test_nextfakt_desc_places_nulls_last_in_main_and_count_queries(): void
    {
        $expectedSort = 'CASE WHEN o.nextfakt IS NULL THEN 1 ELSE 0 END ASC, o.nextfakt DESC';

        $mainQuery = build_query('invoice-list', $this->gridData(), $this->columns(), [], [], 'nextfakt DESC', 25, 50);
        $countQuery = build_count_query($this->gridData(), $this->columns(), [], [], 'nextfakt DESC');

        $this->assertSame(
            "SELECT o.id, o.nextfakt FROM ordrer o WHERE 1=1 ORDER BY $expectedSort LIMIT 25 OFFSET 50",
            $mainQuery
        );
        $this->assertSame(
            "SELECT COUNT(*) as total_items FROM (SELECT o.id, o.nextfakt FROM ordrer o WHERE 1=1 ORDER BY $expectedSort) as subquery",
            $countQuery
        );
    }

    public function test_nextfakt_asc_places_nulls_last_in_main_and_count_queries(): void
    {
        $expectedSort = 'CASE WHEN o.nextfakt IS NULL THEN 1 ELSE 0 END ASC, o.nextfakt ASC';

        $mainQuery = build_query('invoice-list', $this->gridData(), $this->columns(), [], [], 'nextfakt ASC', 25, 0);
        $countQuery = build_count_query($this->gridData(), $this->columns(), [], [], 'nextfakt ASC');

        $this->assertSame(
            "SELECT o.id, o.nextfakt FROM ordrer o WHERE 1=1 ORDER BY $expectedSort LIMIT 25 OFFSET 0",
            $mainQuery
        );
        $this->assertSame(
            "SELECT COUNT(*) as total_items FROM (SELECT o.id, o.nextfakt FROM ordrer o WHERE 1=1 ORDER BY $expectedSort) as subquery",
            $countQuery
        );
    }

    public function test_ordinary_sort_keeps_the_existing_order_by_clause(): void
    {
        $mainQuery = build_query('invoice-list', $this->gridData(), $this->columns(), [], [], 'ordrenr DESC', 25, 0);
        $countQuery = build_count_query($this->gridData(), $this->columns(), [], [], 'ordrenr DESC');

        $this->assertStringContainsString('ORDER BY ordrenr DESC LIMIT 25 OFFSET 0', $mainQuery);
        $this->assertStringContainsString('ORDER BY ordrenr DESC) as subquery', $countQuery);
        $this->assertStringNotContainsString('CASE WHEN ordrenr IS NULL', $mainQuery);
        $this->assertStringNotContainsString('CASE WHEN ordrenr IS NULL', $countQuery);
    }
}
