<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../support/LegacyShopStub.php';

// 20261026 CL/SZ SD-777 (SD-750) Pins today's legacy shop sync in includes/std_func.php before the
//                coexistence switches go in: hent_shop_ordrer() (order intake), sync_shop_price()
//                and sync_shop_vare() (outbound), for every box4/box5/box6 combination. The
//                functions run unchanged against scripted query answers (run_legacy_shop_sync.php)
//                and a local stand-in shop (LegacyShopStub). Data is synthetic.
final class LegacyShopSyncCharacterizationTest extends TestCase
{
	private const DB = 'chardb';

	private static LegacyShopStub $shop;
	private string $work;

	public static function setUpBeforeClass(): void
	{
		self::$shop = new LegacyShopStub();
	}

	public static function tearDownAfterClass(): void
	{
		self::$shop->stop();
	}

	protected function setUp(): void
	{
		self::$shop->reset();
		$this->work = sys_get_temp_dir() . '/legacy-shop-' . bin2hex(random_bytes(6));
		mkdir($this->work . '/page', 0777, true);
		mkdir($this->work . '/temp/' . self::DB, 0777, true);
	}

	protected function tearDown(): void
	{
		exec('rm -rf ' . escapeshellarg($this->work));
	}

	/** The eight box4/box5/box6 combinations: which slots hold an endpoint. */
	public static function slotCombinations(): array
	{
		$cases = [];
		foreach ([false, true] as $four) {
			foreach ([false, true] as $five) {
				foreach ([false, true] as $six) {
					$name = 'box4 ' . ($four ? 'set' : 'empty') . ', box5 ' . ($five ? 'set' : 'empty') . ', box6 ' . ($six ? 'set' : 'empty');
					$cases[$name] = [$four, $five, $six];
				}
			}
		}
		return $cases;
	}

	// ---- hent_shop_ordrer(): order intake -------------------------------------------------

	#[DataProvider('slotCombinations')]
	public function testOrderIntakeAsksEverySlotWithAnEndpointOnlyWhenBox4IsSet(bool $four, bool $five, bool $six): void
	{
		$api = $this->api($four, $five, $six);

		$this->sync('hent_shop_ordrer', ['', ''], [$api]);

		$expected = $this->expectedPaths($four, $five, $six);
		$sent = self::$shop->waitFor(count($expected));
		$this->assertSame($expected, $this->sortedPaths($sent), 'backgrounded wgets: compared without order');
		foreach ($sent as $request) {
			$this->assertSame(['put_new_orders' => '1'], $request['query']);
			$this->assertStringStartsWith('Wget/', $request['ua'], 'no User-Agent header is set: $header is undefined there');
		}
		if ($four) {
			$this->assertSame(json_encode($api[1][0]), $this->temp('ny_shop.json'));
			$this->assertEqualsWithDelta(time(), (int)$this->temp('shoptidspkt.txt'), 5);
		} else {
			$this->assertNull($this->temp('ny_shop.json'));
			$this->assertNull($this->temp('shoptidspkt.txt'));
		}
	}

	public function testOrderIntakeForOneOrderPassesTheOrderIdAndFromDateToEverySlot(): void
	{
		$this->sync('hent_shop_ordrer', ['55', '2026-10-01'], [$this->api(true, true, true)]);

		$sent = self::$shop->waitFor(3);
		$this->assertSame(['/a/', '/b/', '/c/'], $this->sortedPaths($sent));
		foreach ($sent as $request) {
			$this->assertSame(['put_new_orders' => '1', 'ordre_id' => '55', 'from_date' => '2026-10-01'], $request['query']);
		}
	}

	public function testOrderIntakeRunsAtMostEveryFiveMinutesUnlessAnOrderIsNamed(): void
	{
		$recent = (string)(time() - 290);
		file_put_contents($this->tempPath('shoptidspkt.txt'), $recent);

		$this->sync('hent_shop_ordrer', ['', ''], [$this->api(true, true, true)]);
		$this->assertSame([], self::$shop->waitFor(0, 0, 1.0), 'asked 290 s ago: nothing sent');
		$this->assertSame($recent, $this->temp('shoptidspkt.txt'), 'timestamp unchanged');

		$this->sync('hent_shop_ordrer', ['55', ''], [$this->api(true, true, true)]);
		$this->assertCount(3, self::$shop->waitFor(3), 'a named order is fetched at once');

		self::$shop->reset();
		file_put_contents($this->tempPath('shoptidspkt.txt'), (string)(time() - 301));
		$this->sync('hent_shop_ordrer', ['', ''], [$this->api(true, false, false)]);
		$this->assertSame(['/a/'], $this->sortedPaths(self::$shop->waitFor(1)), 'asked more than 300 s ago: sent again');
	}

	// ---- sync_shop_price(): price outbound ------------------------------------------------

	#[DataProvider('slotCombinations')]
	public function testPriceSyncSendsTheSamePriceToEverySlotOnlyWhenBox4IsSet(bool $four, bool $five, bool $six): void
	{
		$out = $this->sync('sync_shop_price', [7], [$this->api($four, $five, $six), $this->item()]);

		$expected = $this->expectedPaths($four, $five, $six);
		$sent = self::$shop->requests();
		$this->assertSame($four ? 'OK' : 'no api', $out['return']);
		$this->assertSame($expected, $this->paths($sent));
		foreach ($sent as $request) {
			$this->assertSame('?update_price=&salesPrice=20&discountType=&discount=0&itemNo=V7&rand=&costPrice=10&retailPrice=25&webFragt=&barcode=123', substr($request['uri'], strlen($request['path'])));
			$this->assertSame('Saldi shop sync', $request['ua']);
		}
		if (!$four) {
			$this->assertSame(["select box4, box5, box6 from grupper where art='API'"], $out['sql'], 'no item is even read');
		}
	}

	public function testPriceSyncCountsAFailingSlotAndStillSendsToTheOthers(): void
	{
		$api = ["/art='API'/", [['box4' => self::$shop->url('a'), 'box5' => self::$shop->url('fail'), 'box6' => self::$shop->url('c')]]];

		$out = $this->sync('sync_shop_price', [7], [$api, $this->item()]);

		$this->assertSame('sync errors: 1', $out['return']);
		$this->assertSame(['/a/', '/fail/', '/fail/', '/c/'], $this->paths(self::$shop->requests()), 'a 500 is retried once');
	}

	public function testPriceSyncForAnUnknownItemSendsNothing(): void
	{
		$out = $this->sync('sync_shop_price', [7], [$this->api(true, true, true)]);

		$this->assertSame('OK', $out['return']);
		$this->assertSame([], self::$shop->requests());
	}

	// ---- sync_shop_vare(): stock, price and sku outbound -----------------------------------

	#[DataProvider('slotCombinations')]
	public function testItemSyncSendsPriceSkuAndStockPerSlotOnlyWhenBox4IsSet(bool $four, bool $five, bool $six): void
	{
		$out = $this->sync('sync_shop_vare', [7, 0, 1], array_merge([$this->api($four, $five, $six)], $this->stockItem()));

		$slots = $this->expectedPaths($four, $five, $six);
		// sku never goes to box6: the original loop only covers box4 and box5.
		$skuSlots = array_values(array_diff($slots, ['/c/']));
		$sent = self::$shop->requests();
		$this->assertSame($four ? 'OK' : 'no api', $out['return']);
		$this->assertSame(array_merge($slots, $skuSlots, $slots), $this->paths($sent));

		$price = ['update_price' => '501', 'salesPrice' => '20', 'discountType' => '', 'discount' => '0', 'itemNo' => 'V7', 'itemNoAlias' => 'A7',
			'rand' => '', 'costPrice' => '10', 'retailPrice' => '25', 'webFragt' => '', 'barcode' => '123'];
		$stock = ['update_stock' => '501', 'stock' => '4', 'totalStock' => '6', 'stockno' => '1', 'costPrice' => '10', 'salesPrice' => '20',
			'discountType' => '', 'discount' => '0', 'itemNo' => 'V7', 'itemNoAlias' => 'A7', 'retailPrice' => '25', 'webFragt' => '', 'barcode' => '123'];
		foreach ($sent as $i => $request) {
			$this->assertSame('Mozilla/5.0 Gecko/20100101 Firefox/23.0', $request['ua']);
			$query = $request['query'];
			if ($i < count($slots)) {
				$this->assertSame($price, $query);
			} elseif ($i < count($slots) + count($skuSlots)) {
				$this->assertSame(['sku', 'skuAlias', 'costPrice', 'rand'], array_keys($query));
				$this->assertSame(['V7', 'A7', '10'], [$query['sku'], $query['skuAlias'], $query['costPrice']]);
				$skuRand = $query['rand'];
			} else {
				$this->assertSame(['update_stock', 'stock', 'totalStock', 'stockno', 'costPrice', 'salesPrice', 'discountType', 'discount',
					'itemNo', 'itemNoAlias', 'rand', 'retailPrice', 'webFragt', 'barcode'], array_keys($query));
				$this->assertSame($skuRand, $query['rand'], 'stock and sku carry the same random number');
				unset($query['rand']);
				$this->assertSame($stock, $query);
			}
		}
	}

	public function testItemSyncWithoutAShopLinkSendsShopIdZero(): void
	{
		$rows = array_values(array_filter($this->stockItem(), fn(array $r): bool => !str_contains($r[0], 'saldi_id')));

		$this->sync('sync_shop_vare', [7, 0, 1], array_merge([$this->api(true, false, false)], $rows));

		$sent = self::$shop->requests();
		$this->assertSame(['0', '0'], [$sent[0]['query']['update_price'], $sent[2]['query']['update_stock']]);
	}

	public function testItemSyncOfAnItemGroupWithoutStockSendsNothing(): void
	{
		$rows = [$this->api(true, true, true), ["/delvare,gruppe from varer where id='7'/", [['delvare' => '', 'gruppe' => '3']]], ["/art = 'VG'/", [['box8' => '']]]];

		$out = $this->sync('sync_shop_vare', [7, 0, 1], $rows);

		$this->assertSame('no stock', $out['return']);
		$this->assertSame([], self::$shop->requests());
		$this->assertContains("select box8 from grupper where kodenr='3' and art = 'VG' AND fiscal_year = 1", $out['sql']);
	}

	public function testVariantSyncSendsOnlyTheVariantStockToEverySlot(): void
	{
		$rows = [
			$this->api(true, true, true),
			["/delvare,gruppe from varer where id='7'/", [['delvare' => '', 'gruppe' => '3']]],
			["/art = 'VG'/", [['box8' => 'on']]],
			["/shop_variant from shop_varer where saldi_variant='11'/", [['shop_variant' => 'SV11']]],
			["/beholdning from lagerstatus where variant_id='11'/", [['beholdning' => '4']]],
			["/kostpris from varer where id='7'/", [['kostpris' => '10']]],
		];

		$out = $this->sync('sync_shop_vare', [7, 11, 2], $rows);

		$this->assertSame('OK', $out['return']);
		$sent = self::$shop->requests();
		$this->assertSame(['/a/', '/b/', '/c/'], $this->paths($sent));
		foreach ($sent as $request) {
			$this->assertSame(['update_stock' => 'SV11', 'stock' => '4', 'stockno' => '2', 'stockvalue' => ''], $request['query']);
		}
	}

	public function testVariantWithoutAShopLinkFallsBackToItsBarcodeWithoutEan(): void
	{
		$rows = [
			$this->api(true, true, true),
			["/delvare,gruppe from varer where id='7'/", [['delvare' => '', 'gruppe' => '3']]],
			["/art = 'VG'/", [['box8' => 'on']]],
			["/beholdning from lagerstatus where variant_id='11'/", [['beholdning' => '4']]],
			["/variant_stregkode from variant_varer where id='11'/", [['variant_stregkode' => 'EAN5701234']]],
		];

		$this->sync('sync_shop_vare', [7, 11, 2], $rows);

		$sent = self::$shop->requests();
		$this->assertSame(['/a/', '/b/', '/c/'], $this->paths($sent));
		foreach ($sent as $request) {
			$this->assertSame(['update_stock' => '5701234', 'stock' => '4', 'stockno' => '2', 'stockvalue' => ''], $request['query']);
		}
	}

	public function testAPartItemAlsoSendsStockAndCostForTheItemItIsPartOf(): void
	{
		$rows = $this->stockItem();
		$rows[0] = ["/delvare,gruppe from varer where id='7'/", [['delvare' => 'on', 'gruppe' => '3']]];
		$rows = array_merge([$this->api(true, true, true)], $rows, [
			["/from styklister where vare_id = '7'/", [['indgaar_i' => '9']]],
			["/varenr,varenr_alias,kostpris from varer where id = '9'/", [['varenr' => 'SET9', 'varenr_alias' => 'S9', 'kostpris' => '30']]],
			["/shop_id from shop_varer where saldi_id = 9/", [['shop_id' => '509']]],
		]);

		$out = $this->sync('sync_shop_vare', [7, 0, 1], $rows);

		$this->assertSame('OK', $out['return']);
		$parts = array_slice(self::$shop->requests(), 8);
		$this->assertSame(['/a/', '/a/', '/b/', '/b/', '/c/', '/c/'], $this->paths($parts));
		foreach ($parts as $i => $request) {
			$this->assertSame('Mozilla/5.0 Gecko/20100101 Firefox/23.0', $request['ua']);
			$this->assertSame($i % 2 === 0
				? ['update_stock' => '509', 'stock' => '100000', 'totalStock' => '100000', 'stockno' => '1', 'costPrice' => '30',
					'itemNo' => 'SET9', 'itemNoAlias' => 'S9', 'sku' => 'SET9', 'skuAlias' => 'S9']
				: ['costPrice' => '30', 'sku' => 'SET9', 'skuAlias' => 'S9'], $request['query'], 'a set without parts of its own counts as 100000 available');
		}
	}

	// ---- helpers ---------------------------------------------------------------------------

	/** @return array{0:string, 1:list<array<string,string>>} */
	private function api(bool $four, bool $five, bool $six): array
	{
		return ["/art='API'/", [[
			'box4' => $four ? self::$shop->url('a') : '',
			'box5' => $five ? self::$shop->url('b') : '',
			'box6' => $six ? self::$shop->url('c') : '',
		]]];
	}

	/** Today's rule: nothing at all without box4, otherwise every slot that holds an endpoint, in slot order. */
	private function expectedPaths(bool $four, bool $five, bool $six): array
	{
		if (!$four) {
			return [];
		}
		return array_keys(array_filter(['/a/' => true, '/b/' => $five, '/c/' => $six]));
	}

	private function item(): array
	{
		return ["/FROM varer WHERE varer.id='7'/", [['varenr' => 'V7', 'varenr_alias' => 'A7', 'kostpris' => '10', 'salgspris' => '20', 'm_type' => '',
			'm_rabat' => '0', 'retail_price' => '25', 'colli_webfragt' => '', 'stregkode' => '123']]];
	}

	/** A stock item in group 3, 4 in warehouse 1 and 6 in total, linked to shop id 501. */
	private function stockItem(): array
	{
		return [
			["/delvare,gruppe from varer where id='7'/", [['delvare' => '', 'gruppe' => '3']]],
			["/art = 'VG'/", [['box8' => 'on']]],
			["/from lagerstatus,varer where lagerstatus.vare_id='7' and lagerstatus.lager='1'/", [['varenr' => 'V7', 'varenr_alias' => 'A7', 'kostpris' => '10', 'stock' => '4']]],
			["/sum\\(beholdning\\) as total_stock from lagerstatus where vare_id='7'/", [['total_stock' => '6']]],
			["/shop_id from shop_varer where saldi_id='7'/", [['shop_id' => '501']]],
			$this->item(),
		];
	}

	/** @return array{return:mixed, sql:list<string>} */
	private function sync(string $call, array $args, array $rows): array
	{
		$spec = $this->work . '/spec.json';
		file_put_contents($spec, json_encode(['work' => $this->work, 'db' => self::DB, 'regnaar' => 1, 'rows' => $rows, 'call' => $call, 'args' => $args]));
		$res = CharacterizationEnv::runChild(dirname(__DIR__, 2) . '/support/run_legacy_shop_sync.php', [$spec]);
		$lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
		$out = $lines !== [] ? json_decode(end($lines), true) : null;
		$this->assertIsArray($out, "runner failed (exit {$res['exit']}).\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
		$this->assertStringNotContainsString('Fatal error', $res['stderr']);
		return $out;
	}

	private function paths(array $requests): array
	{
		return array_column($requests, 'path');
	}

	private function sortedPaths(array $requests): array
	{
		$paths = $this->paths($requests);
		sort($paths);
		return $paths;
	}

	private function tempPath(string $file): string
	{
		return $this->work . '/temp/' . self::DB . '/' . $file;
	}

	private function temp(string $file): ?string
	{
		$path = $this->tempPath($file);
		return is_file($path) ? (string)file_get_contents($path) : null;
	}
}
