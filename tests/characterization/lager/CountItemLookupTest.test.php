<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the stock count's item lookup and variant check (lager/optallingIncludes/countItem.php,
 * used by lager/optalling.php for both the variant guard and the recount).
 *
 * The database layer is a double over a small varer/variant_varer table: it evaluates the exact
 * «varenr= or stregkode=» query and the case-insensitive fallback the same way PostgreSQL would, and
 * db_escape_string() is addslashes(), so an unescaped apostrophe would not match.
 *
 * History:
 * 20261112 CL/MMR Created (SST-1014, Codex round 1: one lookup for the guard and the recount).
 */
#[RunTestsInSeparateProcesses]
final class CountItemLookupTest extends TestCase
{
	private static function boot(): void
	{
		$GLOBALS['__varer'] = [
			['id' => 1, 'varenr' => 'Løsdel', 'stregkode' => '5701234000011'],
			['id' => 2, 'varenr' => 'løsdel', 'stregkode' => ''],
			['id' => 3, 'varenr' => 'TSHIRT', 'stregkode' => '5701234000028'],
			['id' => 4, 'varenr' => "O'Hara", 'stregkode' => ''],
		];
		$GLOBALS['__variants'] = [3 => [31, 32]];
		eval('
			function db_escape_string($s) { return addslashes((string) $s); }
			function db_select($q, $where = "") {
				$rows = [];
				if (preg_match("/from varer where varenr=\'((?:[^\'\\\\\\\\]|\\\\\\\\.)*)\' or stregkode=\'/", $q, $m)) {
					$v = stripslashes($m[1]);
					foreach ($GLOBALS["__varer"] as $r) if ($r["varenr"] === $v || $r["stregkode"] === $v) $rows[] = $r;
				} elseif (preg_match("/from varer where lower\\(varenr\\)=\'((?:[^\'\\\\\\\\]|\\\\\\\\.)*)\'/", $q, $m)) {
					$v = stripslashes($m[1]);
					foreach ($GLOBALS["__varer"] as $r) if (strtolower($r["varenr"]) === $v || strtolower($r["stregkode"]) === $v) $rows[] = $r;
				} elseif (preg_match("/from variant_varer where vare_id=\'([0-9]+)\'/", $q, $m)) {
					foreach ($GLOBALS["__variants"][(int) $m[1]] ?? [] as $id) $rows[] = ["id" => $id];
				}
				return (object) ["rows" => $rows];
			}
			function db_fetch_array($r) { return array_shift($r->rows) ?? false; }
		');
		require dirname(__DIR__, 3) . '/lager/optallingIncludes/countItem.php';
	}

	public function testExactNumberWinsOverACaseInsensitiveMatch(): void
	{
		self::boot();
		self::assertSame(2, countItemLookup('løsdel')['id']);
		self::assertSame(1, countItemLookup('Løsdel')['id']);
	}

	public function testBarcodeFindsTheItem(): void
	{
		self::boot();
		self::assertSame(3, countItemLookup('5701234000028')['id']);
	}

	public function testOtherCaseFallsBackToTheCaseInsensitiveLookup(): void
	{
		self::boot();
		self::assertSame(3, countItemLookup('tshirt')['id']);
	}

	public function testApostropheIsEscaped(): void
	{
		self::boot();
		self::assertSame(4, countItemLookup("O'Hara")['id']);
	}

	public function testEmptyAndUnknownFindNothing(): void
	{
		self::boot();
		self::assertFalse(countItemLookup(''));
		self::assertFalse(countItemLookup('NOPE'));
	}

	public function testVariantItemIsRecognisedByNumberAndByBarcode(): void
	{
		self::boot();
		self::assertTrue(countItemHasVariants(countItemLookup('TSHIRT')['id']));
		self::assertTrue(countItemHasVariants(countItemLookup('5701234000028')['id']));
		self::assertFalse(countItemHasVariants(countItemLookup('Løsdel')['id']));
	}
}
