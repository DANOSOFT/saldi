<?php
// tests/characterization/includes/orderFuncIncludes/DateRangeSearchCharacterizationTest.test.php
//
// SST-806: pins ordrelisteParseDateTerm() / generateDateRangeSearch() in
// includes/orderFuncIncludes/dateRangeSearch.php.
//
// The order list's date field used to accept what people actually type: a bare six-digit date
// (210926) and a colon-separated interval (010926:300926). The builder written for the datepicker
// only understood dd-mm-yyyy with the separator surrounded by spaces, so both of those fell through
// to "1=1" - the list came back unfiltered - and an ISO date had its parts reversed, which made
// 2026-02-03 search for the third of March.
//
// History:
// 20260925 LOE SST-806: created.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/includes/orderFuncIncludes/dateRangeSearch.php';

final class DateRangeSearchCharacterizationTest extends TestCase
{
	private $column = array('field' => 'ordredate', 'sqlOverride' => 'o.ordredate');

	private function search($term)
	{
		return generateDateRangeSearch($this->column, $term);
	}

	public function testShortDateIsAccepted(): void
	{
		// The example from the ticket: 210926 is the 21st of September 2026.
		$this->assertSame('2026-09-21', ordrelisteParseDateTerm('210926'));
		$this->assertSame('2026-09-01', ordrelisteParseDateTerm('010926'));
		$this->assertSame('2026-09-30', ordrelisteParseDateTerm('300926'));
	}

	public function testEverySeparatorAndBothPartOrdersAreAccepted(): void
	{
		$this->assertSame('2026-09-21', ordrelisteParseDateTerm('21-09-2026'));
		$this->assertSame('2026-09-21', ordrelisteParseDateTerm('21.09.2026'));
		$this->assertSame('2026-09-21', ordrelisteParseDateTerm('21/09/2026'));
		$this->assertSame('2026-09-21', ordrelisteParseDateTerm('21-9-2026'));
		$this->assertSame('2026-09-21', ordrelisteParseDateTerm('2026-09-21'));
		$this->assertSame('2026-09-21', ordrelisteParseDateTerm('21092026'));
		// Two-digit years follow usdate(): 26 is 2026, 99 is 1999.
		$this->assertSame('2026-09-21', ordrelisteParseDateTerm('210926'));
		$this->assertSame('1999-09-21', ordrelisteParseDateTerm('210999'));
	}

	public function testImpossibleDatesAreRejectedRatherThanClamped(): void
	{
		// usdate() would walk 31 February back to the 28th; a filter must not invent a day.
		$this->assertSame('', ordrelisteParseDateTerm('310226'));
		$this->assertSame('', ordrelisteParseDateTerm('31-02-2026'));
		$this->assertSame('', ordrelisteParseDateTerm('2026-02-31'));
		$this->assertSame('', ordrelisteParseDateTerm('29022025'));
		$this->assertSame('2024-02-29', ordrelisteParseDateTerm('290224'));
	}

	public function testNonDatesAreRejected(): void
	{
		$this->assertSame('', ordrelisteParseDateTerm(''));
		$this->assertSame('', ordrelisteParseDateTerm('abc'));
		$this->assertSame('', ordrelisteParseDateTerm('21-09'));
		$this->assertSame('', ordrelisteParseDateTerm('21-09-2026-01'));
		$this->assertSame('', ordrelisteParseDateTerm('21092'));
	}

	public function testShortIntervalIsTurnedIntoABetweenOnTheWholePeriod(): void
	{
		// The other example from the ticket: everything in September 2026.
		$this->assertSame(
			"(o.ordredate >= '2026-09-01 00:00:00' AND o.ordredate <= '2026-09-30 23:59:59')",
			$this->search('010926:300926')
		);
	}

	public function testTheIntervalShapesTheFieldHasBeenGivenAllWork(): void
	{
		$expected = "(o.ordredate >= '2026-09-01 00:00:00' AND o.ordredate <= '2026-09-30 23:59:59')";
		$this->assertSame($expected, $this->search('010926:300926'));          // shorthand, no spaces
		$this->assertSame($expected, $this->search('01-09-2026:30-09-2026'));  // dashes, no spaces
		$this->assertSame($expected, $this->search('01-09-2026 : 30-09-2026')); // the datepicker's own shape
		$this->assertSame($expected, $this->search('01-09-2026 - 30-09-2026')); // spaced hyphen
		$this->assertSame($expected, $this->search('01.09.2026 : 30.09.2026'));
		$this->assertSame($expected, $this->search('2026-09-01:2026-09-30'));
	}

	public function testSingleDateSearchesThatWholeDay(): void
	{
		$this->assertSame(
			"(o.ordredate >= '2026-09-21 00:00:00' AND o.ordredate <= '2026-09-21 23:59:59')",
			$this->search('210926')
		);
		$this->assertSame(
			"(o.ordredate >= '2026-09-21 00:00:00' AND o.ordredate <= '2026-09-21 23:59:59')",
			$this->search('21-09-2026')
		);
	}

	public function testATermThatIsNotADateLeavesTheListUnfiltered(): void
	{
		// Same as before the fix for garbage input: no filter rather than no rows.
		$this->assertSame('1=1', $this->search(''));
		$this->assertSame('1=1', $this->search('   '));
		$this->assertSame('1=1', $this->search('abc'));
		$this->assertSame('1=1', $this->search('310226'));
		$this->assertSame('1=1', $this->search('210926:abc'));
	}

	public function testQuotesAroundTheTermAreIgnored(): void
	{
		// The grid can hand the term over quoted, which the builder has always trimmed.
		$this->assertSame(
			"(o.ordredate >= '2026-09-21 00:00:00' AND o.ordredate <= '2026-09-21 23:59:59')",
			$this->search("'210926'")
		);
	}

	public function testTheColumnFallsBackToItsFieldWhenThereIsNoOverride(): void
	{
		$this->assertSame(
			"(transdate >= '2026-09-21 00:00:00' AND transdate <= '2026-09-21 23:59:59')",
			generateDateRangeSearch(array('field' => 'transdate'), '210926')
		);
	}
}
