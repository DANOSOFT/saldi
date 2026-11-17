<?php

declare(strict_types=1);

namespace Saldi\Tests\NewLineReceiveQuantity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261116 CL/MMR SST-1024 Pins what a line added on a purchase order or credit note starts with: antal and «Modtag»/«Returner» (ordrelinjer.leveres).
//                 Custom Audio added items to a purchase order that was already approved. The line got leveres NULL, «Modtag» showed empty,
//                 and the next «Gem» (Enter in any field) stored 0, so «Modtag» received nothing of it.
//                 On an approved order (status 1 or more, also when «Godkend» is ticked in the same save) the new line now starts
//                 with leveres = antal, as approval gives the lines already on the order. A draft (status 0) keeps NULL.
//                 Both inserts are covered: the item typed on the empty line (POST) and the item picked through «Opslag» (GET vare_id).
//                 An empty quantity on a credit note (KK) is -1, the sign every KK line is stored with (Codex round 1 found +1 → Returner 0).
//                 kreditor/ordre.php is a page script, so the parsing of the posted quantity, newLineQuantity(), newLineReceiveQuantity()
//                 and each insert are lifted out of the source and the SQL they build is read back.
// 20261117 CL/MMR SST-1024 The lifted parse now runs to the end of «Modtag» and through opslagLineQuantities() (Saul's review), which gets its own cases.
//                 It runs with the real ifset() and usdecimal() from includes/std_func.php, not stubs (Codex round 3).
//                 The page round trip (Opslag, then Gem) is OpslagThenSaveRoundTripTest.

/** Stand-in for the real escape, which needs a database connection; quoting is not what is tested here. */
function db_escape_string($value)
{
	return str_replace("'", "''", (string)$value);
}

final class NewLineReceiveQuantityTest extends TestCase
{
	private const ORDRE = __DIR__ . '/../../../kreditor/ordre.php';

	private static ?string $src = null;

	private static function source(): string
	{
		if (self::$src === null) {
			$src = file_get_contents(self::ORDRE);
			self::assertNotFalse($src, 'could not read kreditor/ordre.php');
			self::$src = $src;
			require_once __DIR__ . '/../../../includes/std_func.php'; // the real ifset() and usdecimal() the lifted parse calls
			foreach (['newLineQuantity', 'newLineReceiveQuantity', 'opslagLineQuantities'] as $name) {
				if (function_exists($name)) {
					continue;
				}
				$fn = strpos($src, "\nfunction $name(");
				self::assertNotFalse($fn, "$name() is gone from kreditor/ordre.php");
				$end = strpos($src, "\n}\n", $fn);
				self::assertNotFalse($end, "could not find the end of $name()");
				eval(substr($src, $fn, $end - $fn + 2)); // global, once per process
			}
		}
		return self::$src;
	}

	/** The source from $from up to (not including) $before, with db_escape_string() pointed at this namespace. */
	private static function slice(string $from, string $before): string
	{
		$src = self::source();
		$at = strpos($src, $from);
		self::assertNotFalse($at, "the block starting \"$from\" is gone from kreditor/ordre.php");
		$end = strpos($src, $before, $at);
		self::assertNotFalse($end, "the block starting \"$from\" no longer reaches \"$before\"");
		return str_replace('db_escape_string(', '\\' . __NAMESPACE__ . '\\db_escape_string(', substr($src, $at, $end - $at));
	}

	/** @return array<string, string> column => value of an «insert into ordrelinjer (…) values (…)» */
	private static function columns(string $qtxt): array
	{
		self::assertSame(1, preg_match('/^insert into ordrelinjer\s*\(([^)]*)\) values\s*\((.*)\)$/s', trim($qtxt), $m), "not an insert: $qtxt");
		$columns = array_map('trim', explode(',', $m[1]));
		$values = array_map('trim', str_getcsv($m[2], ',', "'", ''));
		self::assertCount(count($columns), $values, "column and value count differ in: $qtxt");
		return array_combine($columns, $values);
	}

	/**
	 * The typed line: the posted «Antal» as the line loop parses it, the default from the item branch, and the insert.
	 *
	 * @return array<string, string>
	 */
	private static function typedLine(string $art, int $status, string $postedAntal): array
	{
		$parse = self::slice('$y="anta".$x;', '$y="lev_varenr".$x;');
		$default = self::slice('$antal[0] = newLineQuantity($antal[0], $art);', 'if (!$rabat[0]) $rabat[0]=0;');
		$insert = self::slice('$nyLeveres = newLineReceiveQuantity($status, $antal[0]);', 'db_modify($qtxt');

		$_POST = ['anta0' => $postedAntal];
		$x = 0;
		$antal = [];
		$leveres = [];
		eval($parse);
		eval($default);

		$id = 3791;
		$posnr_ny = [0 => 400];
		$varenr = [0 => 'M118006'];
		$vare_id = [0 => 15360];
		$beskrivelse = [0 => 'Forstaerker'];
		$enhed = [0 => ''];
		$pris = [0 => 2719.6];
		$rabat = [0 => 0];
		$serienr = [0 => ''];
		$lev_varenr = [0 => ''];
		$momsfri = [0 => ''];
		$variant_id = [0 => 0];
		$samlevare = [0 => ''];
		$omvbet = [0 => ''];
		$varemomssats = [0 => 25];
		$lager = 3;
		$qtxt = '';
		eval($insert);
		return self::columns($qtxt);
	}

	/**
	 * The item picked through «Opslag»: the order row it reads (status, art), the default quantity and the insert.
	 *
	 * @return array<string, string>
	 */
	private static function lookupLine(string $art, int $status): array
	{
		$row = ['status' => $status, 'art' => $art, 'omvbet' => '', 'konto_id' => 120838];
		$state = self::slice('$omlev=$row[\'omvbet\'];', '$query = db_select("select posnr from ordrelinjer');
		$default = self::slice('$antal[0] = newLineQuantity($antal[0], $opslagArt ?? \'\');', 'if (!$rabat) $rabat=0;');
		$insert = self::slice('$nyLeveres = newLineReceiveQuantity($opslagStatus ?? 0, $antal[0]);', 'db_modify($qtxt');

		eval($state);
		$antal = [0 => null];
		eval($default);

		$id = 3791;
		$posnr = [0 => 301];
		$varenr = [0 => 'M118006'];
		$vare_id = [0 => 15360];
		$beskrivelse = [0 => 'Forstaerker'];
		$enhed = [0 => ''];
		$pris = [0 => 2719.6];
		$lev_varenr = [0 => ''];
		$serienr = [0 => ''];
		$momsfri = [0 => ''];
		$samlevare = [0 => ''];
		$omvbet = [0 => ''];
		$varemomssats = [0 => 25];
		$lager = 3;
		$qtxt = '';
		eval($insert);
		return self::columns($qtxt);
	}

	/** @return array<string, array{string, int, string, string, string}> art, status, posted Antal, stored antal, stored leveres */
	public static function typedCases(): array
	{
		return [
			'approved order, the reported case (Antal 1)'     => ['KO', 1, '1', '1', '1'],
			'approved order, Antal left empty'                 => ['KO', 1, '', '1', '1'],
			'approved order, Antal 3'                          => ['KO', 1, '3', '3', '3'],
			'approved order, Antal 2,5'                        => ['KO', 1, '2,5', '2.5', '2.5'],
			'status 2 (Godkend ticked again in the same save)' => ['KO', 2, '1', '1', '1'],
			'draft order keeps no receive quantity'            => ['KO', 0, '1', '1', 'NULL'],
			'approved credit note, Antal 1'                    => ['KK', 1, '1', '-1', '-1'],
			'approved credit note, Antal left empty'           => ['KK', 1, '', '-1', '-1'],
			'draft credit note, Antal left empty'              => ['KK', 0, '', '-1', 'NULL'],
		];
	}

	#[DataProvider('typedCases')]
	public function testATypedLineStartsWithTheRightQuantityAndReceiveQuantity(string $art, int $status, string $posted, string $antal, string $leveres): void
	{
		$row = self::typedLine($art, $status, $posted);
		self::assertSame((float)$antal, (float)$row['antal'], 'antal');
		self::assertArrayHasKey('leveres', $row, 'the insert sets no leveres, so «Modtag» shows empty and Gem stores 0');
		self::assertSame($leveres, $row['leveres'] === 'NULL' ? 'NULL' : (string)(float)$row['leveres'], 'leveres');
	}

	/** @return array<string, array{string, int, string, string}> art, order status, stored antal, stored leveres */
	public static function lookupCases(): array
	{
		return [
			'approved order'       => ['KO', 1, '1', '1'],
			'draft order'          => ['KO', 0, '1', 'NULL'],
			'approved credit note' => ['KK', 1, '-1', '-1'],
		];
	}

	#[DataProvider('lookupCases')]
	public function testALinePickedThroughOpslagStartsWithTheRightQuantityAndReceiveQuantity(string $art, int $status, string $antal, string $leveres): void
	{
		$row = self::lookupLine($art, $status);
		self::assertSame($antal, $row['antal'], 'antal');
		self::assertArrayHasKey('leveres', $row, 'the «Opslag» insert sets no leveres');
		self::assertSame($leveres, $row['leveres']);
	}

	public function testTheOtherColumnsOfTheTypedLineAreUnchanged(): void
	{
		$row = self::typedLine('KO', 1, '1');
		self::assertSame('M118006', $row['varenr']);
		self::assertSame('3', $row['lager']);
		self::assertSame('25', $row['momssats']);
	}

	/** @return array<string, array{?string, ?string, ?string, ?string, ?string}> posted Antal, Modtag, antaDefault; expected Antal, Modtag */
	public static function opslagCases(): array
	{
		return [
			'Opslag line, Gem without typing'        => ['', '1', '1', '1', '1'],
			'Opslag line, Antal 5, Modtag untouched' => ['5', '1', '1', '5', '5'],
			'Opslag line, Antal 5, Modtag 2'         => ['5', '2', '1', '5', '2'],
			'Opslag line, Antal 2,5, Modtag 1,0'     => ['2,5', '1,0', '1', '2,5', '2,5'],
			'Opslag line on a draft (no Modtag)'     => ['', null, '1', '1', null],
			'Opslag line, Antal 0 typed'             => ['0', '1', '1', '0', '0'],
			'any other line, Antal empty'            => ['', '1', null, '', '1'],
			'any other line, Antal 5'                => ['5', '1', null, '5', '1'],
		];
	}

	#[DataProvider('opslagCases')]
	public function testTheLineOpslagAddedKeepsItsQuantityAndModtagFollowsIt(?string $anta, ?string $leve, ?string $default, ?string $antal, ?string $leveres): void
	{
		self::source();
		self::assertSame([$antal, $leveres], \opslagLineQuantities($anta, $leve, $default));
	}

	public function testTheParseUsesTheDefaultOnACreditNote(): void
	{
		$parse = self::slice('$y="anta".$x;', '$y="lev_varenr".$x;');
		$_POST = ['anta2' => '', 'leve2' => '1', 'antaDefault2' => '1'];
		$x = 2;
		$art = 'KK';
		$antal = [];
		$leveres = [];
		eval($parse);
		self::assertSame([-1.0, -1.0], [(float)$antal[2], (float)$leveres[2]]);
	}
}
