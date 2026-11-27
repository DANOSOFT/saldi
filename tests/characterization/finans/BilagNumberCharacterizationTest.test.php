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
// 20261125 LOE SST-906: the year choice and the staged-date conversion are pinned as behaviour, and no proposal site may fall back to the session year or to a hardcoded today.
// 20261127 LOE SST-906 (review): the separators the save reads and the ones it does not are pinned, so the helper and opdater() cannot disagree about a staged date.
//              The clamped days are pinned too: a day the month does not have lands on its last one, and an impossible month does not.


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
     * bilagFiscalYearContaining() is the choice itself, so it can be pinned without a tenant: the year whose
     * window holds the date, the end of a year being inclusive (the day a payment on the closing date is
     * posted), and no year at all when the date falls in none of them.
     *
     * @return void
     */
    public function testTheYearThatContainsTheDateIsChosen(): void
    {
        $years = array(
            9  => array('2025-10-01', '2026-09-30'),
            10 => array('2026-10-01', '2027-09-30'),
        );
        $this->assertSame(10, bilagFiscalYearContaining('2026-11-25', $years), 'today in the open year');
        $this->assertSame(9, bilagFiscalYearContaining('2026-06-13', $years), 'a date in the closing year');
        $this->assertSame(9, bilagFiscalYearContaining('2026-09-30', $years), 'the last day of a year is inside it');
        $this->assertSame(10, bilagFiscalYearContaining('2026-10-01', $years), 'and the first day of the next one');
        $this->assertSame(0, bilagFiscalYearContaining('2031-01-01', $years), 'a date in no year is no year');
        $this->assertSame(0, bilagFiscalYearContaining('2026-11-25', array()), 'no years configured, no year');
        $this->assertSame(0, bilagFiscalYearContaining('2026-11-25', array(9 => array('', '2026-09-30'))),
            'a year without a window cannot claim a date');
        $this->assertSame(5, bilagFiscalYearContaining('2026-05-13', array(5 => array('2026-1-01', '2026-12-31'))),
            'a window whose month is written with one digit still holds its dates');
        $this->assertSame(6, bilagFiscalYearContaining('2026-5-13', array(6 => array('2026-01-01', '2026-12-31'))),
            'and so does a date written the same way');
        $this->assertSame(array('2026-01-01', '2026-12-31'), bilagFiscalYearWindow(1, 2026, 12, 2026),
            'a year opens on the first of its month and closes on the last day of its closing month');
        $this->assertSame(array('2025-07-01', '2026-06-30'), bilagFiscalYearWindow(7, 2025, 6, 2026),
            'including a closing June, which has thirty days');
        $this->assertSame(array('', ''), bilagFiscalYearWindow(0, 2026, 12, 2026), 'month zero is no window');
        $this->assertSame('2026-06-13', bilagIsoDate('2026-6-13'), 'a date is read whatever its padding');
        $this->assertSame('', bilagIsoDate('2026-13-13'), 'and rejected when the calendar has no such day');
    }

    /**
     * A date the page's converter would not produce resolves to no year at all (SST-906).
     *
     * Only the malformed cases are pinned here, because they answer before any tenant is consulted - the whole
     * suite runs in one process, so a neighbouring test may well have loaded the application's database layer
     * (and with it `db_select`). Which year a valid date lands in when a tenant *is* available is the subject
     * of testTheYearThatContainsTheDateIsChosen() above.
     *
     * @return void
     */
    public function testAMalformedDateResolvesToNoYear(): void
    {
        $this->assertSame(0, bilagFiscalYearForDate('13-06-2026'), 'the page hands over Y-m-d, never dd-mm-yyyy');
        $this->assertSame(0, bilagFiscalYearForDate(''), 'an empty date names no year');
        $this->assertSame(0, bilagFiscalYearForDate('2026-6-13'), 'not zero padded, not a date this reads');
        $this->assertSame(0, bilagFiscalYearForDate('2026-11-31'), 'a field the calendar does not have is a date string, not a day');
    }

    /**
     * A staged date is read the way the save reads it (SST-906).
     *
     * A value this cannot read must come back empty, never as today: the save would otherwise renumber a line
     * from today's fiscal year's series.
     *
     * @return void
     */
    public function testAStagedDateIsReadAsTheSaveReadsIt(): void
    {
        $this->assertSame('2026-06-13', bilagStagedDate('13-06-2026'), 'tmpkassekl keeps the operator dd-mm-yyyy');
        $this->assertSame('2026-06-13', bilagStagedDate('13.06.2026'), 'the staging table also holds dots');
        $this->assertSame('2026-06-13', bilagStagedDate('130626'), 'and the compact ddmm the journal accepts');
        $this->assertSame('2026-06-13', bilagStagedDate('13062026'), 'or a compact ddmmyyyy');
        $this->assertSame('2026-06-13', bilagStagedDate('1306', array('2025-10-01', '2026-09-30')),
            'a value without a year takes the year the journal gives it: the opening year from the opening month onwards');
        $this->assertSame('2025-11-13', bilagStagedDate('1311', array('2025-10-01', '2026-09-30')),
            'and the opening year again for a month after the opening one');
        $this->assertSame('2026-06-13', bilagStagedDate('13-06', array('2025-10-01', '2026-09-30')),
            'the same for a day and month written with a separator');
        $this->assertSame('', bilagStagedDate('1306'), 'with no tenant and no window given, there is no year to use');
        $this->assertSame('', bilagStagedDate('1306261'), 'seven digits are not a date this reads');
        $this->assertSame('', bilagStagedDate('13x06x2025'), 'a separator the journal cannot read is not a date');
        $this->assertSame('2025-06-13', bilagStagedDate('13/06/2025'), 'a slash is one of the separators it reads');
        $this->assertSame('2025-06-13', bilagStagedDate('13-6-2025'), 'a one-digit month is read too');
        $this->assertSame('2025-06-13', bilagStagedDate('13.6.2025'), 'and a one-digit month after a dot');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', bilagStagedDate('13', array('2025-10-01', '2026-09-30')),
            'a bare day is that day of the month the journal is in, as usdate() reads it');
        $this->assertSame('2026-11-25', bilagStagedDate('2026-11-25'), 'an already converted date passes through');
        $this->assertSame('', bilagStagedDate('2026-02-31'),
            'a Y-m-d value is written by the application, so an impossible one is not clamped but rejected');
        $this->assertSame('', bilagStagedDate(''), 'an empty staged date names no day');
        $this->assertSame('', bilagStagedDate(null), 'and neither does a missing one');
        $this->assertSame('', bilagStagedDate('not a date'), 'a value this cannot read names no day');
        $this->assertSame('2026-02-28', bilagStagedDate('31-02-2026'),
            'a day its month does not have is clamped to the last one it has, as usdate() clamps it');
        $this->assertSame('2025-02-28', bilagStagedDate('30-02-2025'), 'so a thirtieth of February does not become today');
        $this->assertSame('2025-02-28', bilagStagedDate('29-02-2025'), 'and nor does a leap day in a common year');
        $this->assertSame('2024-02-29', bilagStagedDate('29-02-2024'), 'while a real leap day stays where it is');
        $this->assertSame('2025-04-30', bilagStagedDate('31-04-2025'), 'a thirty-first of April becomes the thirtieth');
        $this->assertSame('', bilagStagedDate('13-13-2026'), 'an impossible month is not clamped into another month');
    }

    /**
     * Every place the page proposes a number hands the allocator a date, and tells the save which date it was
     * (SST-906).
     *
     * This stays a source pin on purpose: what the page computes needs a rendered journal and a tenant, which
     * this suite does not open. It pins the wiring the save depends on - three proposal sites, each deriving
     * the number from a date through the page's own converter, and the hidden bilfdN that carries that date.
     *
     * @return void
     */
    public function testEveryProposalCarriesTheDateItWasDrawnFor(): void
    {
        $page = @file_get_contents(dirname(__DIR__, 3) . '/finans/kassekladde.php');
        self::assertNotFalse($page, 'cannot read finans/kassekladde.php');
        self::assertSame(4, substr_count((string)$page, 'bilagNextNumberForJournal('),
            'four allocation sites: the placeholder line, the next line, its hidden proposal, and the save');
        self::assertStringNotContainsString('bilagNextNumberForJournal($kladde_id, $regnstart, $regnslut)', (string)$page,
            'no proposal site may fall back to the session year: every one of them is handed a date');
        self::assertStringNotContainsString("bilagNextNumberForJournal(\$kladde_id, \$regnstart, \$regnslut, date('Y-m-d'))", (string)$page,
            'and none of them hardcodes today instead of the date the line carries');
        self::assertSame(2, substr_count((string)$page, "name='bilfd"),
            'both render sites post the date their proposal was drawn for');
    }
}
