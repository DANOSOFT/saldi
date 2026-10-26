<?php
// tests/characterization/debitor/LegacyShopPagesCharacterizationTest.test.php
//
// Characterization of the legacy shop calls made straight from page scripts, not through
// the std_func.php functions (SD-750), pinned before the coexistence switches go in:
//
// - debitor/ordreliste.php order intake: every slot box4/box5/box6 is asked for new orders
//   by a backgrounded wget, at most every 1200 s; "Hent ordrer" (hent_nu) and a named order
//   or invoice skip the wait.
// - debitor/ordreliste.php "SHOP import": the DIV/5 box2 hent_ordrer.php link, and with
//   shop_fakt=1 a wget --spider to box4.
// - sync_shop/sync_all_products.php: stock, price and sku for every stock item, and stock
//   and cost for the sets it is part of, to every slot by a backgrounded curl, refused
//   when no slot has an endpoint.
// - lager/varekort.php: opening a product card sends the item's price to every slot
//   (sync_shop_price() at the end of the page runs on a plain view, not only on save).
//
// The tenant's endpoints point at LegacyShopStub, so nothing reaches a real shop. Data is
// synthetic. Requires the docker-compose stack (see README) - skips cleanly otherwise.
//
// History:
// 20261026 CL/SZ Created (SD-777, sub-task of SD-750).
// 20261026 CL/SZ SD-750 An empty box4 no longer disables box5/box6: every slot with an endpoint is called.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../support/LegacyShopStub.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class LegacyShopPagesCharacterizationTest extends CharacterizationTestCase
{
	private static LegacyShopStub $shop;
	private static bool $createdTemp = false;
	/** @var array<string, array<string,mixed>|null> grupper rows as they were before the suite */
	private static array $saved = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		// The pages write ../temp/<db>/; a folder left by the web server is not writable here.
		$temp = dirname(self::tempFile());
		if (!is_dir($temp)) {
			mkdir($temp, 0777, true);
			self::$createdTemp = true;
		}
		if (!is_writable($temp)) {
			self::fail("$temp is not writable by this user; set SALDI_CHAR_TEST_DB to a tenant name without one");
		}
		self::$shop = new LegacyShopStub();
		foreach (['API' => null, 'DIV' => '5'] as $art => $kodenr) {
			$where = $kodenr === null ? "art = 'API'" : "art = 'DIV' AND kodenr = '5'";
			self::$saved[$art] = self::one("SELECT id, box2, box4, box5, box6 FROM grupper WHERE $where ORDER BY id LIMIT 1");
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (isset(self::$shop)) {
			self::$shop->stop();
		}
		foreach (self::$saved as $art => $row) {
			if ($row === null) {
				self::rows("DELETE FROM grupper WHERE art = $1 AND beskrivelse = $2", [$art, Fixtures::TAG]);
			} else {
				self::rows('UPDATE grupper SET box2 = $1, box4 = $2, box5 = $3, box6 = $4 WHERE id = $5',
					[$row['box2'], $row['box4'], $row['box5'], $row['box6'], $row['id']]);
			}
		}
		@unlink(self::tempFile());
		if (self::$createdTemp) {
			// The pages' own logs, curl output and barcode images for the throwaway tenant.
			exec('rm -rf ' . escapeshellarg(dirname(self::tempFile())));
		}
		parent::tearDownAfterClass();
		// Drops the clone and its registry/session rows; a later suite clones a new one.
		CharacterizationEnv::teardownTenant();
	}

	protected function setUp(): void
	{
		self::$shop->reset();
		@unlink(self::tempFile());
		$this->setShopImport('');
	}

	public static function slotCombinations(): array
	{
		$cases = [];
		foreach ([false, true] as $four) {
			foreach ([false, true] as $five) {
				foreach ([false, true] as $six) {
					$cases['box4 ' . ($four ? 'set' : 'empty') . ', box5 ' . ($five ? 'set' : 'empty') . ', box6 ' . ($six ? 'set' : 'empty')] = [$four, $five, $six];
				}
			}
		}
		return $cases;
	}

	// ---- debitor/ordreliste.php: order intake ---------------------------------------------

	#[DataProvider('slotCombinations')]
	public function testOrderListAsksEverySlotWithAnEndpointForNewOrders(bool $four, bool $five, bool $six): void
	{
		$this->setSlots($four, $five, $six);

		$this->page('debitor/ordreliste.php', ['valg' => 'ordrer']);

		$expected = $this->expectedPaths($four, $five, $six);
		$sent = self::$shop->waitFor(count($expected));
		$this->assertSame($expected, $this->sortedPaths($sent));
		foreach ($sent as $request) {
			$this->assertSame(['put_new_orders' => '1'], $request['query']);
			$this->assertSame('Mozilla/5.0 Gecko/20100101 Firefox/23.0', $request['ua']);
		}
		$this->assertSame($expected !== [], is_file(self::tempFile()), 'the timestamp is written when any slot is asked');
	}

	public function testOrderListWaitsTwentyMinutesBetweenFetchesUnlessHentNuIsPressed(): void
	{
		$this->setSlots(true, false, false);

		file_put_contents(self::tempFile(), (string)(time() - 1190));
		$this->page('debitor/ordreliste.php', ['valg' => 'ordrer']);
		$this->assertSame([], self::$shop->waitFor(0, 0, 1.0), 'fetched 1190 s ago: nothing sent');

		file_put_contents(self::tempFile(), (string)(time() - 1210));
		$this->page('debitor/ordreliste.php', ['valg' => 'ordrer']);
		$this->assertSame(['/a/'], $this->sortedPaths(self::$shop->waitFor(1)), 'fetched 1210 s ago: sent');

		self::$shop->reset();
		file_put_contents(self::tempFile(), (string)(time() - 600));

		// hent_nu counts the last fetch 1170 s older, so anything older than 30 s is fetched now.
		$this->page('debitor/ordreliste.php', ['valg' => 'ordrer', 'hent_nu' => '1']);
		$this->assertSame(['/a/'], $this->sortedPaths(self::$shop->waitFor(1)));

		self::$shop->reset();
		file_put_contents(self::tempFile(), (string)time());
		$out = $this->page('debitor/ordreliste.php', ['valg' => 'ordrer', 'hent_nu' => '1']);
		$this->assertSame([], self::$shop->waitFor(0, 0, 1.0), 'fetched just now: nothing sent');
		$this->assertStringContainsString("alert('Vent 30 sekunder')", $out);
	}

	public function testOrderListPassesANamedOrderOrInvoiceToEverySlot(): void
	{
		$this->setSlots(true, true, true);
		file_put_contents(self::tempFile(), (string)time());

		$this->page('debitor/ordreliste.php', ['valg' => 'ordrer', 'shop_ordre_id' => '55']);
		$sent = self::$shop->waitFor(3);
		$this->assertSame(['/a/', '/b/', '/c/'], $this->sortedPaths($sent));
		$this->assertSame([['put_new_orders' => '1', 'order_id' => '55']], array_values(array_unique(array_column($sent, 'query'), SORT_REGULAR)));

		self::$shop->reset();
		$this->page('debitor/ordreliste.php', ['valg' => 'ordrer', 'shop_faktura' => 'F7']);
		$sent = self::$shop->waitFor(3);
		$this->assertSame(['/a/', '/b/', '/c/'], $this->sortedPaths($sent));
		$this->assertSame([['put_new_orders' => '1', 'invoice' => 'F7']], array_values(array_unique(array_column($sent, 'query'), SORT_REGULAR)));
	}

	// ---- debitor/ordreliste.php: SHOP import (DIV/5 box2) ---------------------------------

	public function testShopImportIsALinkToHentOrdrerAndSendsNothingItself(): void
	{
		$this->setSlots(false, false, false);
		$this->setShopImport(self::$shop->url('imp') . '?shop=1');

		$out = $this->page('debitor/ordreliste.php', ['valg' => 'ordrer']);

		$this->assertSame([], self::$shop->waitFor(0, 0, 1.0));
		$link = preg_quote(self::$shop->url('imp') . 'hent_ordrer.php?shop=1&saldi_db=' . CharacterizationEnv::testDb()
			. '&saldiurl=http://chartest.local/saldi/debitor/ordreliste.php&random=', '#');
		$this->assertMatchesRegularExpression("#window\\.open\\('$link\\d+','hent:ordrer'#", $out);
		$this->assertStringContainsString('SHOP import</span>', $out);
	}

	public function testShopImportWithShopFaktSpidersBox4ForANamedOrder(): void
	{
		$this->setSlots(true, false, false);
		$this->setShopImport(self::$shop->url('imp') . '?shop=1&shop_fakt=1');

		$this->page('debitor/ordreliste.php', ['valg' => 'ordrer', 'shop_ordre_id' => '55']);

		$sent = self::$shop->waitFor(2);
		usort($sent, fn(array $x, array $y): int => strcmp($x['method'], $y['method']));
		$this->assertSame([['GET', '/a/'], ['HEAD', '/a/']], array_map(fn(array $r): array => [$r['method'], $r['path']], $sent),
			'intake asks box4 for order 55, then shop_fakt spiders box4 itself, not the import url');
	}

	// ---- sync_shop/sync_all_products.php --------------------------------------------------

	#[DataProvider('slotCombinations')]
	public function testSyncAllProductsSendsStockPriceSkuAndSetPartsToEverySlotWithAnEndpoint(bool $four, bool $five, bool $six): void
	{
		$this->setSlots($four, $five, $six);
		// A stock item that is part of a set: 4 in warehouse 1, 2 in warehouse 2. The set is in
		// an untracked group, so the page only reaches it through the part branch.
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);
		$set = self::$fx->item(['kostpris' => 30.0, 'stocked' => false]);
		self::rows("UPDATE varer SET delvare = 'on' WHERE id = $1", [$item['id']]);
		self::rows("INSERT INTO lagerstatus (vare_id, lager, variant_id, beholdning) VALUES ($1, 1, 0, 4), ($1, 2, 0, 2)", [$item['id']]);
		self::rows('INSERT INTO styklister (vare_id, indgaar_i, antal) VALUES ($1, $2, 1)', [$item['id'], $set['id']]);
		self::rows('INSERT INTO shop_varer (saldi_id, shop_id) VALUES ($1, 509)', [$set['id']]);

		try {
			$out = $this->page('sync_shop/sync_all_products.php', []);

			$slots = $this->expectedPaths($four, $five, $six);
			$ours = fn(array $r): bool => in_array($r['query']['itemNo'] ?? $r['query']['sku'] ?? null, [$item['varenr'], $set['varenr']], true);
			if (!$slots) {
				$this->assertSame([], self::$shop->waitFor(0, 0, 1.0), 'nothing at all is sent');
				$this->assertStringContainsString('Error: No API configuration found', $out);
				return;
			}
			// Per warehouse and slot: stock, price and sku for the item, then stock and cost
			// for the set. Prices go out as the database prints them (numeric(15,3)).
			$sent = self::$shop->waitFor(10 * count($slots), 10.0, 0.5, $ours);
			$calls = [];
			foreach ($sent as $request) {
				$q = $request['query'];
				unset($q['rand']);
				if (isset($q['file'])) {
					// The set's stock url carries the page's own path and line number.
					$this->assertStringEndsWith('/sync_shop/sync_all_products.php', $q['file']);
					$this->assertMatchesRegularExpression('/^\d+$/', $q['line']);
					unset($q['file'], $q['line']);
				}
				$calls[] = $request['path'] . ' ' . http_build_query($q);
				$this->assertStringStartsWith('curl/', $request['ua']);
			}
			sort($calls);
			$expected = [];
			foreach ($slots as $slot) {
				foreach (['1' => 4, '2' => 2] as $lager => $stock) {
					$expected[] = "$slot " . http_build_query(['update_stock' => '0', 'stock' => (string)$stock, 'totalStock' => '6', 'stockno' => $lager,
						'costPrice' => '80.000', 'salesPrice' => '200.000', 'discountType' => '', 'discount' => '', 'itemNo' => $item['varenr'],
						'retailPrice' => '', 'webFragt' => '0', 'barcode' => '']);
					$expected[] = "$slot " . http_build_query(['update_price' => '0', 'salesPrice' => '200.000', 'discountType' => '', 'discount' => '',
						'itemNo' => $item['varenr'], 'costPrice' => '80.000', 'retailPrice' => '', 'webFragt' => '0', 'barcode' => '']);
					$expected[] = "$slot " . http_build_query(['sku' => $item['varenr'], 'costPrice' => '80.000']);
					$expected[] = "$slot " . http_build_query(['update_stock' => '509', 'stock' => (string)$stock, 'totalStock' => '6', 'stockno' => $lager,
						'costPrice' => '30.000', 'itemNo' => $set['varenr'], 'sku' => $set['varenr']]);
					$expected[] = "$slot " . http_build_query(['costPrice' => '30.000', 'sku' => $set['varenr']]);
				}
			}
			sort($expected);
			$this->assertSame($expected, $calls);
		} finally {
			self::rows('DELETE FROM shop_varer WHERE saldi_id = $1', [$set['id']]);
			self::rows('DELETE FROM styklister WHERE vare_id = $1', [$item['id']]);
			self::rows('DELETE FROM lagerstatus WHERE vare_id = $1', [$item['id']]);
			self::rows('DELETE FROM varer WHERE id IN ($1, $2)', [$item['id'], $set['id']]);
		}
	}

	// ---- lager/varekort.php ---------------------------------------------------------------

	#[DataProvider('slotCombinations')]
	public function testOpeningAProductCardSendsItsPriceToEverySlotWithAnEndpoint(bool $four, bool $five, bool $six): void
	{
		$this->setSlots($four, $five, $six);
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);

		// No save: $stockItem is only set while saving, so a plain view takes the
		// sync_shop_price() branch at the end of the page.
		$this->page('lager/varekort.php', ['id' => (string)$item['id']]);

		$sent = array_values(array_filter(self::$shop->requests(), fn(array $r): bool => ($r['query']['itemNo'] ?? null) === $item['varenr']));
		$this->assertSame($this->expectedPaths($four, $five, $six), array_column($sent, 'path'));
		foreach ($sent as $request) {
			$this->assertSame(['update_price' => '', 'salesPrice' => '200.000', 'discountType' => '', 'discount' => '', 'itemNo' => $item['varenr'],
				'rand' => '', 'costPrice' => '80.000', 'retailPrice' => '', 'webFragt' => '0', 'barcode' => ''], $request['query']);
			$this->assertSame('Saldi shop sync', $request['ua']);
		}
		self::rows('DELETE FROM varer WHERE id = $1', [$item['id']]);
	}

	// ---- helpers ---------------------------------------------------------------------------

	private function setSlots(bool $four, bool $five, bool $six): void
	{
		$this->setGrupper('API', null, [
			'box4' => $four ? self::$shop->url('a') : '',
			'box5' => $five ? self::$shop->url('b') : '',
			'box6' => $six ? self::$shop->url('c') : '',
		]);
	}

	private function setShopImport(string $box2): void
	{
		$this->setGrupper('DIV', '5', ['box2' => $box2]);
	}

	/** @param array<string,string> $boxes */
	private function setGrupper(string $art, ?string $kodenr, array $boxes): void
	{
		$where = $kodenr === null ? 'art = $1' : 'art = $1 AND kodenr = $2';
		$params = $kodenr === null ? [$art] : [$art, $kodenr];
		if (self::one("SELECT id FROM grupper WHERE $where", $params) === null) {
			self::rows('INSERT INTO grupper (beskrivelse, art, kodenr) VALUES ($1, $2, $3)', [Fixtures::TAG, $art, $kodenr ?? '1']);
		}
		foreach ($boxes as $box => $value) {
			self::rows("UPDATE grupper SET $box = $" . (count($params) + 1) . " WHERE $where", array_merge($params, [$value]));
		}
	}

	/**
	 * Every slot that holds an endpoint, in slot order. Before SD-750 an empty box4 sent
	 * nothing at all, even to box5/box6; with no shopify_legacy settings that is the only change.
	 */
	private function expectedPaths(bool $four, bool $five, bool $six): array
	{
		return array_keys(array_filter(['/a/' => $four, '/b/' => $five, '/c/' => $six]));
	}

	private function sortedPaths(array $requests): array
	{
		$paths = array_column($requests, 'path');
		sort($paths);
		return $paths;
	}

	private static function tempFile(): string
	{
		return CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb() . '/shoptidspkt.txt';
	}

	/** Runs the page as the seeded session user and returns its output. */
	private function page(string $page, array $get): string
	{
		$output = (string)tempnam(sys_get_temp_dir(), 'legacy-shop-page');
		try {
			self::runChild('run_legacy_shop_page.php', [$page, CharacterizationEnv::SESSION_ID, json_encode(['get' => $get]), $output]);
			return (string)file_get_contents($output);
		} finally {
			unlink($output);
		}
	}
}
