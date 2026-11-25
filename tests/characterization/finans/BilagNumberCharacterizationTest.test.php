<?php
// tests/characterization/finans/BilagNumberCharacterizationTest.test.php
//
// Characterization tests for the cash journal's voucher number (bilag) allocation (SST-817).
//
// The next number a line is offered - and the number a line is given when it was saved without one -
// comes from the journal's highest used number, and from the fiscal year's when the journal has no
// numbered row yet. Deriving it from the row above instead (the old $bilag[$x-1] + 1 rule) is what let
// a journal whose last row is an empty row offer no number at all: the line typed there was stored as
// bilag 0 and the series restarted at 1.
//
// bilagNextNumberAfter() is the rule without database access, so it is pinned here directly; the
// database-facing bilagNextNumberForJournal() only applies it to one journal (see
// finans/kassekladde_includes/bilagNumber.php).
//
// History:
// 20260928 LOE SST-817: created.
// 20261125 LOE SST-906: pins that a line's own date chooses the fiscal year's series, and that the page passes the date.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/finans/kassekladde_includes/bilagNumber.php';

final class BilagNumberCharacterizationTest extends TestCase
{
    /**
     * @return array<string, array{0: int|string, 1: int|string, 2: int}>
     */
    public static function numberCases(): array
    {
        return array(
            'journal number wins over the fiscal year'      => array(4, 9, 5),
            'journal continues its own series'              => array(17, 3, 18),
            'empty journal continues the fiscal year'       => array(0, 4, 5),
            'empty journal in a fresh fiscal year starts at 1' => array(0, 0, 1),
            'a zero row in the journal cannot pull the series back' => array(0, 250, 251),
            'strings from the database behave like numbers' => array('12', '7', 13),
            'a missing count is treated as none'            => array('', '', 1),
            'no reused number when the journal is behind the year' => array(2, 40, 3),
        );
    }

    #[DataProvider('numberCases')]
    public function testNextNumberFollowsTheJournalThenTheFiscalYear($journalMax, $fiscalYearMax, int $expected): void
    {
        $this->assertSame($expected, bilagNextNumberAfter($journalMax, $fiscalYearMax));
    }

    public function testNextNumberIsNeverZeroOrNegative(): void
    {
        $this->assertSame(1, bilagNextNumberAfter(0, 0));
        $this->assertSame(1, bilagNextNumberAfter(-5, -5));
    }

    public function testAllocationLeavesNoGapForTheSingleUserCase(): void
    {
        $previous = 0;
        for ($i = 1; $i <= 5; $i++) {
            $previous = bilagNextNumberAfter($previous, 0);
            $this->assertSame($i, $previous);
        }
    }

    public function testFiscalYearFallbackIsOnlyUsedWhenTheJournalHasNoNumberedRow(): void
    {
        // A journal that has used numbers keeps its series even when the fiscal year has moved on.
        $this->assertSame(41, bilagNextNumberAfter(40, 250));
        // Only an unnumbered journal falls back to the year's highest number.
        $this->assertSame(251, bilagNextNumberAfter(0, 250));
    }

    /**
     * A line's own date decides which fiscal year's series it continues (SST-906).
     *
     * The date-to-year helper is database-facing (it reads grupper art='RA'), so what can be pinned without a
     * tenant is the rule the allocator applies: a line date is accepted, and a malformed one leaves the bounds
     * alone instead of picking a year. The page wiring is pinned at source level below.
     *
     * @return void
     */
    public function testTheLineDateDecidesTheSeries(): void
    {
        $ref = @file_get_contents(dirname(__DIR__, 3) . '/finans/kassekladde_includes/bilagNumber.php');
        self::assertNotFalse($ref, 'cannot read finans/kassekladde_includes/bilagNumber.php');
        self::assertStringContainsString('function bilagFiscalYearForDate($date)', (string)$ref,
            'the line date can be resolved to a fiscal year');
        self::assertStringContainsString('function bilagFiscalYearBounds($regnskabsaar = null)', (string)$ref,
            'the bounds helper takes the year, so another year than the active one can be asked for');
        self::assertStringContainsString('function bilagNextNumberForJournal($kladde_id, $regnstart = null, $regnslut = null, $lineDate = null)', (string)$ref,
            'the allocator takes the line date');
        self::assertStringContainsString('$lineYear = bilagFiscalYearForDate($lineDate);', (string)$ref,
            'and switches the bounds to that year before the seed is read');
        self::assertStringContainsString("preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', \$date)", (string)$ref,
            'a date that is not Y-m-d resolves to no year, so the caller keeps its own bounds');
    }

    /**
     * Every place the page proposes a number passes the date of the line it proposes for (SST-906).
     *
     * @return void
     */
    public function testThePagePassesTheLineDate(): void
    {
        $page = @file_get_contents(dirname(__DIR__, 3) . '/finans/kassekladde.php');
        self::assertNotFalse($page, 'cannot read finans/kassekladde.php');
        self::assertSame(2, substr_count((string)$page, "bilagNextNumberForJournal(\$kladde_id, \$regnstart, \$regnslut, date('Y-m-d'))"),
            'the placeholder line and the next-line proposal use today, which is what a new line gets');
        self::assertStringContainsString("bilagNextNumberForJournal(\$kladde_id, \$regnstart, \$regnslut, usdate(\$dato[\$x] ?? ''))", (string)$page,
            'and the rendered line uses its own date');
    }
}
