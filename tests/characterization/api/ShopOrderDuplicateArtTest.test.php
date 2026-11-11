<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261110 CL/MMR SST-989 Created. shop_order_taken() in api/rest_api.php decides whether a shop order id is already used.
//                 The duplicate check was per art only, so a shop credit note sent once as DK and once as DO was created and invoiced twice.
//                 The function is lifted from the source (including the file connects to a database) and its query is run against a small in-memory table of shop orders.
#[RunTestsInSeparateProcesses]
final class ShopOrderDuplicateArtTest extends TestCase
{
	private const API = __DIR__ . '/../../../api/rest_api.php';

	/**
	 * Loads shop_order_taken() with db_select()/db_fetch_array() stubs that run the WHERE clause the
	 * function builds against $rows, so the test checks what the query matches, not how it is spelled.
	 *
	 * @param list<array{shop_id: int, saldi_id: int, art: string, sum: float}> $rows orders already linked in shop_ordrer
	 */
	private static function boot(array $rows): void
	{
		$GLOBALS['__rows'] = $rows;
		eval('
			function db_escape_string($s) { return str_replace("\'", "\'\'", (string)$s); }
			function db_select($q, $where = "") { $GLOBALS["__sql"] = $q; return $q; }
			function db_fetch_array($q) {
				if (!preg_match("/where so\\\\.shop_id = \'(\\\\d+)\'(.*) order by so\\\\.saldi_id limit 1$/", $q, $m)) {
					throw new RuntimeException("unexpected query: " . $q);
				}
				$extra = trim($m[2]);
				foreach ($GLOBALS["__rows"] as $r) {
					if ($r["shop_id"] !== (int)$m[1]) continue;
					if ($extra === "") return array("saldi_id" => (string)$r["saldi_id"]);
					if ($extra === "and (o.art = \'DK\' or (o.art = \'DO\' and o.sum < 0))") {
						if ($r["art"] === "DK" || ($r["art"] === "DO" && $r["sum"] < 0)) return array("saldi_id" => (string)$r["saldi_id"]);
						continue;
					}
					if (preg_match("/^and o\\\\.art = \'([^\']*)\'$/", $extra, $a)) {
						if ($r["art"] === $a[1]) return array("saldi_id" => (string)$r["saldi_id"]);
						continue;
					}
					throw new RuntimeException("unexpected condition: " . $extra);
				}
				return false;
			}
		');
		$src = (string)file_get_contents(self::API);
		$a = strpos($src, 'function shop_order_taken(');
		self::assertNotFalse($a, 'shop_order_taken() is gone from api/rest_api.php');
		$b = strpos($src, "\n}\n", $a);
		eval(substr($src, $a, $b - $a + 2));
	}

	/** @return array<string, array{list<array{shop_id: int, saldi_id: int, art: string, sum: float}>, int, string, int}> */
	public static function cases(): array
	{
		$dk = ['shop_id' => 19225, 'saldi_id' => 3099, 'art' => 'DK', 'sum' => -5839.2];
		$negativeDo = ['shop_id' => 19240, 'saldi_id' => 3154, 'art' => 'DO', 'sum' => -158.4];
		$order = ['shop_id' => 19211, 'saldi_id' => 3063, 'art' => 'DO', 'sum' => 7038.6];
		$credit = ['shop_id' => 19211, 'saldi_id' => 3070, 'art' => 'DK', 'sum' => -5839.2];
		return [
			'DO after the same credit note as DK (Custom Audio 2192/2199)' => [[$dk], 19225, 'DO', 3099],
			'DK after the same credit note as a negative DO (2229/2222)'   => [[$negativeDo], 19240, 'DK', 3154],
			'DO sent twice'                                               => [[$order], 19211, 'DO', 3063],
			'DK sent twice'                                               => [[$order, $credit], 19211, 'DK', 3070],
			'DK under the id of an ordinary order is allowed'             => [[$order], 19211, 'DK', 0],
			'new shop order id'                                           => [[$order, $dk], 19300, 'DO', 0],
			'new credit note id'                                          => [[$order, $dk], 19301, 'DK', 0],
		];
	}

	/**
	 * @param list<array{shop_id: int, saldi_id: int, art: string, sum: float}> $rows
	 */
	#[DataProvider('cases')]
	public function testDuplicateShopOrders(array $rows, int $shopId, string $art, int $expected): void
	{
		self::boot($rows);
		self::assertSame($expected, shop_order_taken($shopId, $art));
	}

	public function testTheShopIdIsCastToAnInteger(): void
	{
		self::boot([]);
		shop_order_taken("19225' or '1'='1", 'DO');
		self::assertStringContainsString("so.shop_id = '19225'", $GLOBALS['__sql']);
	}
}
