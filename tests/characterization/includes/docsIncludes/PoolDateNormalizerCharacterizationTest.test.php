<?php
// tests/characterization/includes/docsIncludes/PoolDateNormalizerCharacterizationTest.test.php
//
// SST-775: pins normalizeDateFormat() in includes/docsIncludes/poolDateNormalizer.php.
//
// pool_files.file_date is a varchar, so whatever this function returns is stored verbatim
// and read back by Bilagsmatch and the cash journal. Three defects each produced a wrong
// or impossible bilagsdato that was then posted silently:
//   - a two-digit year swapped day with year   ("17-10-25" -> 2017-10-25)
//   - a month-first document was read day-first ("10/17/2025" -> "2025-17-10", month 17)
//   - nothing validated the result             ("31.02.2025" -> "2025-02-31")
//
// The formats that already worked are pinned too, so the fix cannot regress them.
//
// History:
// 20260909 CDX/MJ SST-775: created.
// 20261124 CL/LH A receipt printing "21 11 26" (spaces, dd mm yy) became 2021-11-26 in the pool.
//                Space-separated triples are read day-first, and poolExtractedDate() takes the day-first reading of an AI-read date that is more than a year old or in the future.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/includes/docsIncludes/poolDateNormalizer.php';

final class PoolDateNormalizerCharacterizationTest extends TestCase
{
	/** Formats that already worked before SST-775 - guard against regression. */
	public static function alreadyWorkingProvider(): array
	{
		return [
			'danish month name, dotted' => ['17.oktober.2025', '2025-10-17'],
			'danish month name, spaced' => ['1. maj 2025', '2025-05-01'],
			'danish month abbreviation' => ['17. okt 2025', '2025-10-17'],
			'day first, dashes'         => ['17-10-2025', '2025-10-17'],
			'day first, dots'           => ['17.10.2025', '2025-10-17'],
			'day first, slashes'        => ['17/10/2025', '2025-10-17'],
			'already iso'               => ['2025-10-17', '2025-10-17'],
			'iso with slashes'          => ['2025/10/17', '2025-10-17'],
		];
	}

	#[DataProvider('alreadyWorkingProvider')]
	public function testFormatsThatAlreadyWorkedStillWork(string $input, string $expected): void
	{
		$this->assertSame($expected, normalizeDateFormat($input));
	}

	public function testTwoDigitYearIsReadDayFirstInsteadOfSwappingDayAndYear(): void
	{
		// Was 2017-10-25: the day-first branch demanded a four-digit year, so this fell
		// through to strtotime(), which reads a dash-separated triple as yy-mm-dd.
		$this->assertSame('2025-10-17', normalizeDateFormat('17-10-25'));
		$this->assertSame('2025-10-17', normalizeDateFormat('17.10.25'));
		$this->assertSame('2025-10-17', normalizeDateFormat('17/10/25'));
	}

	public function testMonthFirstDocumentIsRecognisedRatherThanProducingMonthSeventeen(): void
	{
		// Was "2025-17-10". A month of 17 is impossible, so month-first is the only
		// reading that yields a real date.
		$this->assertSame('2025-10-17', normalizeDateFormat('10/17/2025'));
		$this->assertSame('2025-12-31', normalizeDateFormat('12/31/2025'));
	}

	public function testAmbiguousDayFirstDateIsStillReadDayFirst(): void
	{
		// Both groups are valid months, so nothing is swapped: day-first wins, as before.
		$this->assertSame('2025-10-11', normalizeDateFormat('11/10/2025'));
	}

	public function testImpossibleDateYieldsNoDateRatherThanTheWrongOne(): void
	{
		// Was "2025-02-31" / "2025-02-30", stored silently into a varchar column and then
		// rolled over to 3 March by docPool.php's strtotime(). Returning the raw string
		// instead was no better - strtotime() rolls "31.02.2025" over just the same - so an
		// impossible date has to yield nothing at all.
		$this->assertSame('', normalizeDateFormat('31.02.2025'));
		$this->assertSame('', normalizeDateFormat('30-02-2025'));
		// Neither group can be a month, so there is nothing to swap and nothing to invent.
		$this->assertSame('', normalizeDateFormat('31/17/2025'));
	}

	public function testImpossibleIsoDateIsNotAccepted(): void
	{
		$this->assertSame('', normalizeDateFormat('2025-02-31'));
	}

	public function testImpossibleDanishMonthDayIsNotAccepted(): void
	{
		$this->assertSame('', normalizeDateFormat('31. februar 2025'));
	}

	public function testInputThatWasNeverDateShapedIsLeftAlone(): void
	{
		// Nothing parsed it, so there is no impossible date to suppress - and strtotime()
		// rejects it downstream as well, so it cannot become a wrong journal date.
		$this->assertSame('ikke en dato', normalizeDateFormat('ikke en dato'));
	}

	public function testEmptyInputReturnsEmptyString(): void
	{
		$this->assertSame('', normalizeDateFormat(''));
		$this->assertSame('', normalizeDateFormat(null));
	}

	public function testLeapDayIsAccepted(): void
	{
		$this->assertSame('2024-02-29', normalizeDateFormat('29-02-2024'));
	}

	public function testNonLeapDayIsRejected(): void
	{
		$this->assertSame('', normalizeDateFormat('29-02-2025'));
	}

	public static function spaceSeparatedProvider(): array
	{
		return [
			'receipt "21 11 26"'          => ['21 11 26', '2026-11-21'],
			'receipt "05 11 24"'          => ['05 11 24', '2024-11-05'],
			'four-digit year, spaces'     => ['21 11 2026', '2026-11-21'],
			'dots with a two-digit year'  => ['21.11.26', '2026-11-21'],
			'iso with spaces'             => ['2026 11 21', '2026-11-21'],
			'time after the date'         => ['21 11 26 13:02', '2026-11-21'],
			'time after a dashed date'    => ['21-11-26 13:02', '2026-11-21'],
		];
	}

	#[DataProvider('spaceSeparatedProvider')]
	public function testSpaceSeparatedDateIsReadDayFirst(string $input, string $expected): void
	{
		// Was returned unchanged ("21 11 26"): only . - / separated a day-first triple, and
		// strtotime() cannot read the spaced form at all.
		$this->assertSame($expected, normalizeDateFormat($input));
	}

	public static function extractedDateProvider(): array
	{
		return [
			// The AI read "21 11 26" year-first. Three years before the upload, while the
			// day-first reading is three days before it.
			'year-first read of "21 11 26"'   => ['2021-11-26', '2026-11-24', '2026-11-21'],
			'the raw receipt text'            => ['21 11 26', '2026-11-24', '2026-11-21'],
			// "30 10 26" read year-first as 2030-10-26: in the future, and 30 Oct 2026 fits.
			'future year-first read'          => ['2030-10-26', '2026-11-24', '2026-10-30'],
			// 2030-11-25 turned around is 2025-11-30, a year back: not a recent receipt, nothing to correct.
			'future read without a recent swap' => ['2030-11-25', '2026-11-24', '2030-11-25'],
			'plausible date is kept'          => ['2026-11-21', '2026-11-24', '2026-11-21'],
			// 2025-11-26 is within a year of the upload, so its swap (2026-11-25) is not taken.
			'recent ambiguous date is kept'   => ['2025-11-26', '2026-11-24', '2025-11-26'],
			// Two years old, but 2005-11-24 is no better: nothing to correct.
			'old date without a better read'  => ['2024-11-05', '2026-11-24', '2024-11-05'],
			'invoice a few days ahead'        => ['2026-11-30', '2026-11-24', '2026-11-30'],
			'year-first read with a time'     => ['2021-11-26 00:00:00', '2026-11-24', '2026-11-21'],
			// Read day-first with the year in full: that is what the document says, however old.
			'old day-first date is kept'      => ['26.11.2021', '2026-11-24', '2021-11-26'],
			'old spaced day-first date kept'  => ['26 11 2021', '2026-11-24', '2021-11-26'],
			// An advance invoice read correctly: 2026-01-27 is ten months back, not a recent receipt.
			'advance invoice is kept'         => ['2027-01-26', '2026-11-24', '2027-01-26'],
			'impossible date stays empty'     => ['31.02.2026', '2026-11-24', ''],
			'empty stays empty'               => ['', '2026-11-24', ''],
		];
	}

	#[DataProvider('extractedDateProvider')]
	public function testExtractedDatePrefersTheDayFirstReadingWhenOnlyItIsPlausible(string $input, string $uploaded, string $expected): void
	{
		$this->assertSame($expected, poolExtractedDate($input, $uploaded));
	}

	public function testSlashedDateWithATimeIsStillReadByStrtotime(): void
	{
		// US receipts print "11/12/26 13:02" month-first; only the spaced and dotted forms lose their time.
		$this->assertSame('2026-11-12', normalizeDateFormat('11/12/26 13:02'));
	}

	public function testImpossibleIsoTimestampIsNotRolledOver(): void
	{
		// strtotime() made 2026-03-02 of this.
		$this->assertSame('', normalizeDateFormat('2026-02-30T13:02:00Z'));
		$this->assertSame('2026-11-21', normalizeDateFormat('2026-11-21T13:02:00+01:00'));
	}

	public function testTypedDateIsNotSecondGuessed(): void
	{
		// A date the user typed goes through normalizeDateFormat() only: an old date is kept.
		$this->assertSame('2021-11-26', normalizeDateFormat('2021-11-26'));
	}
}
