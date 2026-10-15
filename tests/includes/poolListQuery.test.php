<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/docsIncludes/poolListQuery.php';

/**
 * The pool list is sent in pages, so the match groups, search and sort over every document are worked out here.
 * They must give what docPool.php's renderFiles() gave in the browser.
 */
final class poolListQuery extends TestCase
{
    private function row(string $file, string $amount, string $date = '2026-10-01 10:00:00', array $more = []): array
    {
        return array_merge(['filename' => $file, 'amount' => $amount, 'date' => $date, 'subject' => $file, 'invoiceNumber' => '', 'account' => '', 'description' => ''], $more);
    }

    public function testAmountsAreReadInEveryFormatTheListAccepted(): void
    {
        $this->assertSame(1234.56, poolListAmount('1.234,56'));
        $this->assertSame(1234.56, poolListAmount('1,234.56'));
        $this->assertSame(1234.56, poolListAmount('1.234.56'));
        $this->assertSame(1000.5, poolListAmount('1000,5'));
        $this->assertSame(61.13, poolListAmount('61.13'));
        $this->assertNull(poolListAmount(''));
        $this->assertNull(poolListAmount('abc'));
    }

    public function testDatesAreComparedAsYearMonthDay(): void
    {
        $this->assertSame('2026-10-03', poolListDate('2026-10-03 14:03:16'));
        $this->assertSame('2026-10-03', poolListDate('03-10-2026'));
        $this->assertNull(poolListDate(''));
        $this->assertNull(poolListDate('3/10/2026'));
    }

    public function testPerfectAmountAndDateMatches(): void
    {
        $rows = [$this->row('a.pdf', '100.00', '2026-10-03 09:00:00'), $this->row('b.pdf', '100.00', '2026-09-01 09:00:00'), $this->row('c.pdf', '50.00', '2026-10-03 09:00:00'), $this->row('d.pdf', '70.00')];
        $m = poolListMatches($rows, '100,00', '03-10-2026');
        $this->assertSame(['a.pdf'], $m['perfect']);
        $this->assertSame(['b.pdf'], $m['amount']);
        $this->assertSame(['c.pdf'], $m['date']);
        $this->assertSame([], $m['combination']['files']);
        $this->assertArrayNotHasKey('d.pdf', $m['byFile']);
    }

    public function testCombinationsOnlyWhenNothingMatchesTheAmount(): void
    {
        $rows = [$this->row('a.pdf', '60.00'), $this->row('b.pdf', '40.00'), $this->row('c.pdf', '25.00')];
        $m = poolListMatches($rows, '100,00', '');
        $this->assertSame(['a.pdf', 'b.pdf'], $m['combination']['files']);
        $this->assertSame(['files' => ['a.pdf', 'b.pdf'], 'amounts' => [60.0, 40.0]], $m['combination']['first']);

        $rows[] = $this->row('e.pdf', '100.00');
        $this->assertSame([], poolListMatches($rows, '100,00', '')['combination']['files']);
    }

    public function testTripletsWhenNoPairAndQuadsWhenNoTriplet(): void
    {
        $triplet = poolListMatches([$this->row('a.pdf', '10.00'), $this->row('b.pdf', '20.00'), $this->row('c.pdf', '30.00')], '60,00', '');
        $this->assertSame(['a.pdf', 'b.pdf', 'c.pdf'], $triplet['combination']['first']['files']);

        $quad = poolListMatches([$this->row('a.pdf', '1.00'), $this->row('b.pdf', '2.00'), $this->row('c.pdf', '4.00'), $this->row('d.pdf', '8.00')], '15,00', '');
        $this->assertSame(['a.pdf', 'b.pdf', 'c.pdf', 'd.pdf'], $quad['combination']['first']['files']);
    }

    public function testADateMatchInACombinationKeepsItsDateGroupButCountsInTheCombination(): void
    {
        $rows = [$this->row('a.pdf', '60.00', '2026-10-03 09:00:00'), $this->row('b.pdf', '40.00')];
        $m = poolListMatches($rows, '100,00', '03-10-2026');
        $this->assertSame('date', $m['byFile']['a.pdf']);
        $this->assertSame('combination', $m['byFile']['b.pdf']);
        $this->assertSame(['a.pdf', 'b.pdf'], $m['combination']['files']);
    }

    public function testNoLineAmountOrDateMeansNoMatches(): void
    {
        $m = poolListMatches([$this->row('a.pdf', '100.00')], '', '');
        $this->assertSame([], $m['byFile']);
    }

    public function testGroupsComeFirstInTheirOwnOrder(): void
    {
        $rows = [$this->row('x.pdf', '1'), $this->row('c.pdf', '1'), $this->row('a.pdf', '1'), $this->row('d.pdf', '1'), $this->row('b.pdf', '1')];
        $ordered = poolListOrder($rows, ['a.pdf' => 'amount', 'b.pdf' => 'perfect', 'c.pdf' => 'combination', 'd.pdf' => 'date']);
        $this->assertSame(['b.pdf', 'a.pdf', 'd.pdf', 'c.pdf', 'x.pdf'], array_column($ordered, 'filename'));
    }

    public function testSearchLooksInEveryShownFieldIgnoringCase(): void
    {
        $rows = [$this->row('a.pdf', '1', '2026-10-01', ['subject' => 'Havemøbelland ApS']), $this->row('b.pdf', '2', '2026-10-01', ['invoiceNumber' => 'INV-77']), $this->row('c.pdf', '3')];
        $this->assertSame(['a.pdf'], array_column(poolListSearch($rows, 'HAVEMØBEL'), 'filename'));
        $this->assertSame(['b.pdf'], array_column(poolListSearch($rows, 'inv-77'), 'filename'));
        $this->assertCount(3, poolListSearch($rows, '  '));
    }

    public function testSearchFindsTheAmountAsShownWithDecimalComma(): void
    {
        $rows = [$this->row('a.pdf', '5.03'), $this->row('b.pdf', '1234.56'), $this->row('c.pdf', '')];
        $this->assertSame(['a.pdf'], array_column(poolListSearch($rows, '5,03'), 'filename'));
        $this->assertSame(['b.pdf'], array_column(poolListSearch($rows, '1.234,56'), 'filename'));
        $this->assertSame(['b.pdf'], array_column(poolListSearch($rows, '1234,5'), 'filename'));
        $this->assertSame(['a.pdf'], array_column(poolListSearch($rows, '5.03'), 'filename'));
    }

    public function testSortByAmountReadsTheNumberAndKeepsTiesInOrder(): void
    {
        $rows = [$this->row('a.pdf', '1.000,00'), $this->row('b.pdf', '20,00'), $this->row('c.pdf', '20.00'), $this->row('d.pdf', '')];
        $this->assertSame(['d.pdf', 'b.pdf', 'c.pdf', 'a.pdf'], array_column(poolListSort($rows, 'amount', true), 'filename'));
        $this->assertSame(['a.pdf', 'b.pdf', 'c.pdf', 'd.pdf'], array_column(poolListSort($rows, 'amount', false), 'filename'));
        $this->assertSame($rows, poolListSort($rows, 'filename; drop', true));
    }
}
