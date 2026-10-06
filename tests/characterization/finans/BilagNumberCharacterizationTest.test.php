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
}
