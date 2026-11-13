<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the account card's delete check and «Slet» action
 * (systemdata/chartOfAccountIncludes/accountDelete.php, used by systemdata/kontokort.php).
 *
 * The database layer is a double: grupper RA gives the fiscal year, transaktioner holds postings by account
 * and date, kontoplan holds id => account number for the year. The double evaluates the date bounds of the
 * transaktioner query itself, so the test pins that the first and last day of the year count (SST-1012).
 *
 * History:
 * 20261112 CL/MMR Created (SST-1012).
 * 20261112 CL/MMR A missing or invalid fiscal year refuses the delete (Codex round 2).
 * 20261112 CL/MMR An account of another fiscal year than the selected one is refused (Codex round 3).
 */
#[RunTestsInSeparateProcesses]
final class AccountDeleteTest extends TestCase
{
	/**
	 * @param array<int, int>               $accounts kontoplan id => kontonr
	 * @param array<int, list<string>>      $postings kontonr => transdates (Y-m-d)
	 * @param array{0:int,1:int,2:int,3:int}|null $year start month, start year, end month, end year; null = no RA row
	 */
	private static function boot(array $accounts, array $postings, ?array $year = [1, 2026, 12, 2026], array $accountYears = []): void
	{
		$GLOBALS['__accounts'] = $accounts;
		$GLOBALS['__accountYears'] = $accountYears;
		$GLOBALS['__postings'] = $postings;
		$GLOBALS['__year'] = $year;
		$GLOBALS['__modify'] = [];
		eval('
			function db_select($q, $where = "") {
				if (strpos($q, "art=\'RA\'") !== false) {
					if ($GLOBALS["__year"] === null) return (object) ["rows" => []];
					[$sm, $sy, $em, $ey] = $GLOBALS["__year"];
					return (object) ["rows" => [["box1" => $sm, "box2" => $sy, "box3" => $em, "box4" => $ey]]];
				}
				if (preg_match("/from transaktioner where transdate(>=?)\'([0-9-]+)\' and transdate(<=?)\'([0-9-]+)\' and kontonr=\'(-?[0-9]+)\'/", $q, $m)) {
					$from = strtotime($m[2]); $to = strtotime($m[4]);
					foreach ($GLOBALS["__postings"][(int) $m[5]] ?? [] as $d) {
						$t = strtotime($d);
						$okFrom = $m[1] === ">=" ? $t >= $from : $t > $from;
						$okTo = $m[3] === "<=" ? $t <= $to : $t < $to;
						if ($okFrom && $okTo) return (object) ["rows" => [["id" => 1]]];
					}
					return (object) ["rows" => []];
				}
				if (preg_match("/select kontonr, regnskabsaar from kontoplan where id = (-?[0-9]+)/", $q, $m)) {
					$id = (int) $m[1];
					$year = $GLOBALS["__accountYears"][$id] ?? 1;
					return (object) ["rows" => isset($GLOBALS["__accounts"][$id]) ? [["kontonr" => $GLOBALS["__accounts"][$id], "regnskabsaar" => $year]] : []];
				}
				if (preg_match("/select id from kontoplan where kontonr >= (-?[0-9]+)/", $q, $m)) {
					$rows = [];
					$left = $GLOBALS["__accounts"];
					asort($left);
					foreach ($left as $id => $nr) if ($nr >= (int) $m[1]) $rows[] = ["id" => $id];
					return (object) ["rows" => $rows];
				}
				return (object) ["rows" => []];
			}
			function db_fetch_array($r) { return array_shift($r->rows) ?? false; }
			function db_modify($q, $where = "") {
				$GLOBALS["__modify"][] = $q;
				if (preg_match("/delete from kontoplan where id = (-?[0-9]+) and regnskabsaar = \'([0-9]+)\'/", $q, $m) && ($GLOBALS["__accountYears"][(int) $m[1]] ?? 1) === (int) $m[2]) unset($GLOBALS["__accounts"][(int) $m[1]]);
				return true;
			}
		');
		require dirname(__DIR__, 3) . '/systemdata/chartOfAccountIncludes/accountDelete.php';
	}

	public function testPostingsOnTheFirstAndLastDayOfTheYearCount(): void
	{
		self::boot([10 => 9001, 11 => 9002, 12 => 9003], [9001 => ['2026-01-01'], 9002 => ['2026-12-31'], 9003 => ['2025-12-31', '2027-01-01']]);
		self::assertTrue(accountHasPostingsInYear(9001, 1), 'first day of the year');
		self::assertTrue(accountHasPostingsInYear(9002, 1), 'last day of the year');
		self::assertFalse(accountHasPostingsInYear(9003, 1), 'postings outside the year');
	}

	public function testFiscalYearAcrossNewYearIncludesTheLastDayOfItsEndMonth(): void
	{
		self::boot([10 => 9001, 11 => 9002], [9001 => ['2026-06-30'], 9002 => ['2025-07-01']], [7, 2025, 6, 2026]);
		self::assertTrue(accountHasPostingsInYear(9001, 1), 'last day: 30 June');
		self::assertTrue(accountHasPostingsInYear(9002, 1), 'first day: 1 July');
	}

	public function testFiscalYearAcrossNewYearEndsBeforeTheNextYear(): void
	{
		self::boot([10 => 9001], [9001 => ['2026-07-01', '2025-06-30']], [7, 2025, 6, 2026]);
		self::assertFalse(accountHasPostingsInYear(9001, 1));
	}

	public function testDeleteIsRefusedWhenTheAccountHasPostings(): void
	{
		self::boot([10 => 9001, 11 => 9002], [9001 => ['2026-01-01']]);
		$result = deleteAccountAction(10, 9001, 1);
		self::assertSame(['deleted' => false, 'refusedKontonr' => 9001, 'refusedReason' => 'postings', 'nextId' => 10], $result);
		self::assertSame([], $GLOBALS['__modify'], 'nothing written');
	}

	public function testDeleteRemovesAnAccountWithoutPostingsAndOpensTheNextAccount(): void
	{
		self::boot([10 => 9001, 11 => 9002], []);
		$result = deleteAccountAction(10, 9001, 1);
		self::assertSame(['deleted' => true, 'refusedKontonr' => 0, 'refusedReason' => '', 'nextId' => 11], $result);
		self::assertSame(["delete from kontoplan where id = 10 and regnskabsaar = '1'"], $GLOBALS['__modify']);
	}

	public function testDeletingTheLastAccountOpensNoAccount(): void
	{
		self::boot([12 => 9003], []);
		$result = deleteAccountAction(12, 9003, 1);
		self::assertSame(['deleted' => true, 'refusedKontonr' => 0, 'refusedReason' => '', 'nextId' => 0], $result);
	}

	public function testTheCheckUsesTheStoredAccountNumberNotThePostedOne(): void
	{
		self::boot([10 => 9001, 11 => 9002], [9001 => ['2026-05-05']]);
		$result = deleteAccountAction(10, 9002, 1);
		self::assertFalse($result['deleted'], 'a hand-made form with another number cannot delete an account with postings');
		self::assertSame(9001, $result['refusedKontonr']);
	}

	public function testAMissingFiscalYearRefusesTheDelete(): void
	{
		self::boot([10 => 9001, 11 => 9002], [9001 => ['2026-01-01']], null);
		self::assertNull(accountHasPostingsInYear(9001, 1), 'unknown, not "no postings"');
		$result = deleteAccountAction(10, 9001, 1);
		self::assertSame(['deleted' => false, 'refusedKontonr' => 9001, 'refusedReason' => 'year', 'nextId' => 10], $result);
		self::assertSame([], $GLOBALS['__modify'], 'nothing written');
	}

	public function testAnInvalidFiscalYearRefusesTheDelete(): void
	{
		self::boot([10 => 9001], [], [13, 2026, 0, 2026]);
		self::assertNull(accountHasPostingsInYear(9001, 1));
		$result = deleteAccountAction(10, 9001, 1);
		self::assertFalse($result['deleted']);
		self::assertSame('year', $result['refusedReason']);
		self::assertSame([], $GLOBALS['__modify']);
	}

	public function testAFiscalYearEndingBeforeItStartsRefusesTheDelete(): void
	{
		self::boot([10 => 9001], [], [7, 2026, 6, 2026]);
		self::assertNull(accountHasPostingsInYear(9001, 1));
		self::assertSame('year', deleteAccountAction(10, 9001, 1)['refusedReason']);
	}

	public function testAnUnknownAccountIdDeletesNothing(): void
	{
		self::boot([11 => 9002], []);
		$result = deleteAccountAction(99, 9001, 1);
		self::assertSame(['deleted' => false, 'refusedKontonr' => 0, 'refusedReason' => '', 'nextId' => 11], $result);
		self::assertSame([], $GLOBALS['__modify']);
	}

	public function testAnAccountOfAnotherFiscalYearIsRefusedEvenIfTheSelectedYearHasNoPostings(): void
	{
		// account id 10 belongs to year 1 and has a posting on its first day; the session has selected year 2
		self::boot([10 => 9001, 20 => 9001], [9001 => ['2026-01-01']], [1, 2027, 12, 2027], [10 => 1, 20 => 2]);
		$result = deleteAccountAction(10, 9001, 2);
		self::assertFalse($result['deleted']);
		self::assertSame('otheryear', $result['refusedReason']);
		self::assertSame(9001, $result['refusedKontonr']);
		self::assertSame([], $GLOBALS['__modify'], 'nothing written');
		self::assertArrayHasKey(10, $GLOBALS['__accounts'], 'the year-1 account is kept');
	}

	public function testAnAccountOfTheSelectedYearIsDeletedOnlyInThatYear(): void
	{
		self::boot([20 => 9001], [9001 => ['2026-01-01']], [1, 2027, 12, 2027], [20 => 2]);
		$result = deleteAccountAction(20, 9001, 2);
		self::assertTrue($result['deleted']);
		self::assertSame(["delete from kontoplan where id = 20 and regnskabsaar = '2'"], $GLOBALS['__modify']);
	}
}
