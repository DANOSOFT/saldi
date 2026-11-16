<?php

declare(strict_types=1);

namespace Saldi\Tests\NewLineReceiveQuantity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261116 CL/MMR SST-1024 Pins what «Modtag» (ordrelinjer.leveres) a line added on a purchase order starts with.
//                 Custom Audio added items to a purchase order that was already approved. The line got leveres NULL, «Modtag» showed empty,
//                 and the next «Gem» (Enter in any field) stored 0, so «Modtag» received nothing of it.
//                 On an approved order (status 1 or more, also when «Godkend» is ticked in the same save) the new line now starts
//                 with leveres = antal, as approval gives the lines already on the order. A draft (status 0) keeps NULL.
//                 kreditor/ordre.php is a page script, so the insert is lifted out of the source and the SQL it builds is read back.
final class NewLineReceiveQuantityTest extends TestCase
{
	private const ORDRE = __DIR__ . '/../../../kreditor/ordre.php';

	/**
	 * The new-line insert, from the leveres choice up to the db_modify() that runs it.
	 * newLineReceiveQuantity() is lifted from the same file first (once per process; eval'd code is global).
	 */
	private static function insertBlock(): string
	{
		$src = file_get_contents(self::ORDRE);
		self::assertNotFalse($src, 'could not read kreditor/ordre.php');
		if (!function_exists('newLineReceiveQuantity')) {
			$fn = strpos($src, "\nfunction newLineReceiveQuantity(");
			self::assertNotFalse($fn, 'newLineReceiveQuantity() is gone from kreditor/ordre.php');
			$end = strpos($src, "\n}\n", $fn);
			self::assertNotFalse($end, 'could not find the end of newLineReceiveQuantity()');
			eval(substr($src, $fn, $end - $fn + 2));
		}
		$from = strpos($src, '$nyLeveres = ');
		self::assertNotFalse($from, 'the leveres choice for a new line is gone from kreditor/ordre.php');
		$to = strpos($src, 'db_modify($qtxt', $from);
		self::assertNotFalse($to, 'the new-line insert no longer follows the leveres choice');
		return substr($src, $from, $to - $from);
	}

	/**
	 * Builds the insert for one new line and returns column => value as the SQL has them.
	 *
	 * @return array<string, string>
	 */
	private static function insertFor(int $status, $antal): array
	{
		$id = 3791;
		$posnr_ny = [0 => 400];
		$varenr = [0 => 'M118006'];
		$vare_id = [0 => 15360];
		$beskrivelse = [0 => 'Forstaerker'];
		$enhed = [0 => ''];
		$antal = [0 => $antal];
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
		eval(self::insertBlock());

		self::assertMatchesRegularExpression('/^insert into ordrelinjer \(([^)]*)\) values \((.*)\)$/s', trim($qtxt));
		preg_match('/^insert into ordrelinjer \(([^)]*)\) values \((.*)\)$/s', trim($qtxt), $m);
		$columns = array_map('trim', explode(',', $m[1]));
		$values = array_map('trim', str_getcsv($m[2], ',', "'", ''));
		self::assertCount(count($columns), $values, "column and value count differ in: $qtxt");
		return array_combine($columns, $values);
	}

	/** @return array<string, array{int, mixed, string}> */
	public static function cases(): array
	{
		return [
			'approved order, the reported case'          => [1, 1, '1'],
			'approved order, another quantity'           => [1, 3, '3'],
			'approved order, a decimal quantity'         => [1, 2.5, '2.5'],
			'status 2 (approved again in the same save)' => [2, 1, '1'],
			'credit note (KK) line, quantity negated'    => [1, -1, '-1'],
		];
	}

	#[DataProvider('cases')]
	public function testALineAddedToAnApprovedOrderIsReadyToReceiveInFull(int $status, $antal, string $expected): void
	{
		$row = self::insertFor($status, $antal);
		self::assertArrayHasKey('leveres', $row, 'the insert sets no leveres, so «Modtag» shows empty and Gem stores 0');
		self::assertSame($expected, $row['leveres']);
	}

	public function testADraftOrderLeavesTheReceiveQuantityEmpty(): void
	{
		$row = self::insertFor(0, 1);
		self::assertSame('NULL', $row['leveres'], 'a draft order line must not get a receive quantity before approval');
	}

	public function testTheOtherColumnsAreUnchanged(): void
	{
		$row = self::insertFor(1, 1);
		self::assertSame('1', $row['antal']);
		self::assertSame('M118006', $row['varenr']);
		self::assertSame('3', $row['lager']);
		self::assertSame('25', $row['momssats']);
	}
}
