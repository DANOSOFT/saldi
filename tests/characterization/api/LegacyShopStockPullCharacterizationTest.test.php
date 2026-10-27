<?php
// tests/characterization/api/LegacyShopStockPullCharacterizationTest.test.php
//
// Characterization of the legacy shop flows SD-778 puts behind the coexistence switches, before
// any of them changes. The shop endpoints (grupper art='API' box4/box5/box6) point at
// LegacyShopStub. Pinned:
//
// - lager/webshopStock.php (lager/shopStockIncludes/sync.php): the total-stock push goes to
//   every shop the user picks; a URL repeated in a later slot stays with the first slot.
// - api/updateShopCostPrice.php updateShopCostPrice(): the cost price goes to box4 only.
// - api/varesync.php varesync(), from Indstillinger -> API: "Hent nye varer fra shop" (1)
//   fetches the product and variant files from box4 and creates the new items; "Opdater fra
//   shop" (2) updates the existing ones.
// - api/varesync_pos8.php varesync(): the same fetch from box4; it writes and then stops on a
//   PHP 8 fatal error that predates SD-778 (it has no callers).
// - api/shopQty2saldi.php varesync(): the whole body is commented out; it does nothing.
// - lager/optalling.php: the shop pushes after a stock count are commented out; no live code
//   in the file sends anything to a shop.
//
// Synthetic data. Requires the docker-compose stack (see README) - skips cleanly otherwise.
//
// History:
// 20261027 CL/SZ Created (SD-778, sub-task of SD-750).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../support/LegacyShopStub.php';

final class LegacyShopStockPullCharacterizationTest extends CharacterizationTestCase
{
	private static LegacyShopStub $shop;
	private static bool $createdTemp = false;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		$temp = self::tempDir();
		if (!is_dir($temp)) {
			mkdir($temp, 0777, true);
			self::$createdTemp = true;
		}
		if (!is_writable($temp)) {
			self::fail("$temp is not writable by this user; set SALDI_CHAR_TEST_DB to a tenant name without one");
		}
		self::$shop = new LegacyShopStub();
		// lager/webshopStock.php wants exactly '1' in the varer rights position (9), not '9'.
		$master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
		CharacterizationEnv::rows($master, "UPDATE online SET rettigheder = overlay(rettigheder placing '1' from 10 for 1) WHERE session_id = $1", [CharacterizationEnv::SESSION_ID]);
		pg_close($master);
		self::rows("DELETE FROM grupper WHERE art = 'API'");
		self::rows("INSERT INTO grupper (beskrivelse, art, kodenr) VALUES ($1, 'API', '1')", [Fixtures::TAG]);
	}

	public static function tearDownAfterClass(): void
	{
		if (isset(self::$shop)) {
			self::$shop->stop();
		}
		self::removeStockJob();
		if (self::$createdTemp) {
			exec('rm -rf ' . escapeshellarg(self::tempDir()));
		}
		parent::tearDownAfterClass();
		// Drops the clone and its registry and session rows.
		CharacterizationEnv::teardownTenant();
	}

	protected function setUp(): void
	{
		self::$shop->reset();
		self::removeStockJob();
		$this->setSlots('a', 'b', 'c');
	}

	// ---- lager/webshopStock.php --------------------------------------------------------------

	public function testTheStockPageSendsTotalStockToEveryShopThePickerOffers(): void
	{
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);
		self::rows('INSERT INTO lagerstatus (vare_id, lager, variant_id, beholdning) VALUES ($1, 1, 0, 7)', [$item['id']]);

		[$offered, $job] = $this->runStockJob();

		$this->assertSame(['1', '2', '3'], $offered, 'one checkbox per slot, numbered 1-3 for box4-box6');
		$this->assertSame('done', $job['status']);
		$this->assertSame(['/a/', '/b/', '/c/'], $this->stockPaths($item['varenr']));
		foreach ($this->stockRequests($item['varenr']) as $request) {
			$this->assertSame(['update_stock' => '0', 'stock' => '7', 'totalStock' => '7', 'itemNo' => $item['varenr'], 'itemNoAlias' => ''], $request['query']);
			$this->assertSame('Saldi total-stock synchronization', $request['ua']);
		}
		self::rows('DELETE FROM lagerstatus WHERE vare_id = $1', [$item['id']]);
		self::rows('DELETE FROM varer WHERE id = $1', [$item['id']]);
	}

	public function testAUrlRepeatedInALaterSlotStaysWithTheFirstSlot(): void
	{
		$this->setSlots('a', 'a', 'c');
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);

		[$offered] = $this->runStockJob();

		$this->assertSame(['1', '3'], $offered, 'box5 repeats box4, so slot 2 is not offered');
		$this->assertSame(['/a/', '/c/'], $this->stockPaths($item['varenr']));
		self::rows('DELETE FROM varer WHERE id = $1', [$item['id']]);
	}

	// ---- api/updateShopCostPrice.php ----------------------------------------------------------

	public function testTheCostPriceGoesToBox4Only(): void
	{
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);

		$result = $this->call(['call' => 'cost', 'id' => (string)$item['id']]);

		$this->assertSame('OK', $result['result']);
		$sent = self::$shop->waitFor(1);
		$this->assertCount(1, $sent);
		$this->assertSame('/a/', $sent[0]['path']);
		$query = $sent[0]['query'];
		$this->assertMatchesRegularExpression('/^\d+$/', $query['rand']);
		unset($query['rand']);
		$this->assertSame(['sku' => $item['varenr'], 'costPrice' => '80.000'], $query);

		self::$shop->reset();
		$this->setSlots('', 'b', 'c');
		$this->assertSame('no api', $this->call(['call' => 'cost', 'id' => (string)$item['id']])['result'], 'box5 and box6 are never used');
		$this->assertSame([], self::$shop->waitFor(0, 0, 1.0));
		self::rows('DELETE FROM varer WHERE id = $1', [$item['id']]);
	}

	// ---- api/varesync.php ---------------------------------------------------------------------

	public function testHentNyeVarerFetchesFromBox4AndCreatesTheNewItems(): void
	{
		$no = 'CHARNEW' . bin2hex(random_bytes(3));
		$this->serveProducts([[901, $no, '5700000000017', '149.00', '60.00', 'Ny shopvare']]);

		$output = $this->page('systemdata/diverse.php', ['sektion' => 'api_valg', 'varesync' => '1']);

		$this->assertStringContainsString("Opretter $no, Ny shopvare", $output);
		$item = self::one('SELECT id, stregkode, beskrivelse, salgspris, kostpris, beholdning FROM varer WHERE varenr = $1', [$no]);
		$this->assertSame(['5700000000017', 'Ny shopvare', '149.000', '60.000', '0.000'], [$item['stregkode'], $item['beskrivelse'], $item['salgspris'], $item['kostpris'], $item['beholdning']]);
		$this->assertSame([['901', '0', '0']], array_map(fn(array $r): array => [$r['shop_id'], $r['saldi_variant'], $r['shop_variant']], self::rows('SELECT * FROM shop_varer WHERE saldi_id = $1', [$item['id']])));
		$this->assertCount(1, self::rows('SELECT id FROM lagerstatus WHERE vare_id = $1', [$item['id']]));
		$this->assertSame(self::fetches('curl'), $this->fetchRequests());
		// Creating the item goes through lagerreguler(), which sends its price and stock to
		// every slot (includes/std_func.php sync_shop_vare(), already behind the SD-750 switch).
		$this->assertSame(['/a/', '/b/', '/c/'], $this->outboundPaths('update_price', $no));
		$this->removeItem($no);
	}

	public function testOpdaterFraShopUpdatesExistingItemsAndSendsNothing(): void
	{
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);
		$this->serveProducts([[902, $item['varenr'], '5700000000024', '249.00', '90.00', 'Opdateret navn']]);
		// With no shop_varer row at all it stops on a PHP 8 fatal error (api/varesync.php:274,
		// predates SD-778); an install that syncs has links.
		$link = $this->otherLink();

		$this->page('systemdata/diverse.php', ['sektion' => 'api_valg', 'varesync' => '2']);

		$row = self::one('SELECT stregkode, beskrivelse, salgspris, kostpris FROM varer WHERE id = $1', [$item['id']]);
		$this->assertSame(['5700000000024', 'Opdateret navn', '249.000', '90.000'], array_values($row));
		$this->assertSame(['902'], array_column(self::rows('SELECT shop_id FROM shop_varer WHERE saldi_id = $1', [$item['id']]), 'shop_id'));
		$this->assertSame(self::fetches('curl'), $this->fetchRequests());
		$this->assertSame([], $this->outboundPaths('update_price', $item['varenr']));
		$this->removeItem($item['varenr']);
		self::rows('DELETE FROM shop_varer WHERE id = $1', [$link]);
	}

	// ---- api/varesync_pos8.php ----------------------------------------------------------------

	public function testThePos8CopyFetchesFromBox4AndWritesBeforeItsPhp8Fatal(): void
	{
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);
		$this->serveProducts([[903, $item['varenr'], '5700000000031', '259.00', '95.00', 'Pos8 navn']]);
		// Without a variant barcode it stops even earlier, at its first item (line 177).
		self::rows("INSERT INTO variant_varer (vare_id, variant_stregkode) VALUES (0, 'CHARTEST-VB')");

		$run = $this->runPage('tests/characterization/support/legacy_shop_call.php', ['call' => 'pos8', 'valg' => '2']);

		$this->assertSame(255, $run['exit']);
		$this->assertStringContainsString('Unsupported operand types: string * int in ' . CharacterizationEnv::repoRoot() . '/api/varesync_pos8.php', $run['stderr']);
		$row = self::one('SELECT stregkode, beskrivelse, salgspris, kostpris FROM varer WHERE id = $1', [$item['id']]);
		$this->assertSame(['5700000000031', 'Pos8 navn', '259.000', '95.000'], array_values($row), 'written before the fatal');
		$this->assertSame(self::fetches('spider'), $this->fetchRequests());
		self::rows("DELETE FROM variant_varer WHERE variant_stregkode = 'CHARTEST-VB'");
		$this->removeItem($item['varenr']);
	}

	// ---- api/shopQty2saldi.php, lager/optalling.php -----------------------------------------

	public function testShopQty2saldiDoesNothing(): void
	{
		$before = $this->counts();

		$result = $this->call(['call' => 'qty']);

		$this->assertSame('NULL', $result['result']);
		$this->assertSame([], self::$shop->waitFor(0, 0, 1.0));
		$this->assertSame($before, $this->counts());
	}

	public function testNoLiveCodeInTheStockCountSendsAnythingToAShop(): void
	{
		$live = self::liveCode(CharacterizationEnv::repoRoot() . '/lager/optalling.php');

		foreach (['curl', 'wget', 'shopApiRequest', 'update_stock', 'sync_shop_', 'file_get_contents(\'http', 'exec('] as $call) {
			$this->assertStringNotContainsString($call, $live, "$call in live code");
		}
		// The box4 URL is still read into $api_fil, but used nowhere else.
		$this->assertSame(2, substr_count($live, '$api_fil'), '$api_fil = trim($r[\'box4\']) and the global in opdat_behold()');
	}

	// ---- helpers -------------------------------------------------------------------------------

	private function setSlots(string $four, string $five, string $six): void
	{
		$url = fn(string $name): string => $name === '' ? '' : self::$shop->url($name);
		self::rows("UPDATE grupper SET box4 = $1, box5 = $2, box6 = $3 WHERE art = 'API'", [$url($four), $url($five), $url($six)]);
	}

	/**
	 * Opens the stock page, starts a job for every shop it offers and runs one batch, as the
	 * page's own buttons do.
	 *
	 * @return array{0: list<string>, 1: array<string, mixed>} the offered slots and the job's progress
	 */
	private function runStockJob(): array
	{
		$page = $this->page('lager/webshopStock.php', []);
		$this->assertMatchesRegularExpression('/const csrf = "([0-9a-f]{64})"/', $page);
		preg_match('/const csrf = "([0-9a-f]{64})"/', $page, $csrf);
		preg_match_all('/name="shop" value="(\d)"/', $page, $offered);
		$start = json_decode($this->page('lager/webshopStock.php', [], ['action' => 'start', 'csrf' => $csrf[1], 'shops' => $offered[1]]), true);
		$this->assertIsArray($start['job'] ?? null, 'the job starts');
		$job = $start['job'];
		for ($i = 0; $i < 20 && $job['status'] === 'paused'; $i++) {
			$batch = json_decode($this->page('lager/webshopStock.php', [], ['action' => 'batch', 'csrf' => $csrf[1], 'job' => $job['id']]), true);
			$this->assertIsArray($batch['job'] ?? null, 'the batch runs');
			$job = $batch['job'];
		}
		return [$offered[1], $job];
	}

	private function stockRequests(string $varenr): array
	{
		return array_values(array_filter(self::$shop->requests(), fn(array $r): bool => ($r['query']['itemNo'] ?? null) === $varenr && isset($r['query']['update_stock'])));
	}

	private function stockPaths(string $varenr): array
	{
		$paths = array_column($this->stockRequests($varenr), 'path');
		sort($paths);
		return $paths;
	}

	/**
	 * The CSV the shop module answers the product fetch with, in the cost-price layout, and a
	 * variants file with one row for an unknown shop product: without variant rows varesync()
	 * stops on a PHP 8 fatal error at its end (api/varesync.php:573, predates SD-778).
	 *
	 * @param list<array{0:int, 1:string, 2:string, 3:string, 4:string, 5:string}> $items shop id, varenr, barcode, price, cost, name
	 */
	private function serveProducts(array $items): void
	{
		$group = self::one("SELECT kodenr FROM grupper WHERE art = 'VG' AND box8 = 'on' ORDER BY kodenr LIMIT 1")['kodenr'];
		$csv = '"id";"varenr";"stregkode";"salgspris";"kostpris";"beskrivelse";"gruppe";"tilbud";"notes"' . "\n";
		foreach ($items as [$id, $no, $barcode, $price, $cost, $name]) {
			$csv .= "\"$id\";\"$no\";\"$barcode\";\"$price\";\"$cost\";\"$name\";\"$group\";\"\";\"\"\n";
		}
		self::$shop->serve('shop_products.csv', $csv);
		self::$shop->serve('shop_variants.csv', '"varenr";"parent_id";"variant_id";"stregkode";"variant";"variant_type";"variant_text"' . "\n" . '"CHARTEST-V";"999";"1";"";"";"";""' . "\n");
	}

	/** The requests a pull makes to fetch the shop's files, in order: all of them to box4. */
	private static function fetches(string $productList): array
	{
		$list = $productList === 'curl'
			? 'GET /a/?products_id=*&filename=shop_products.csv'
			: 'HEAD /a/?products_id=*&filename=shop_products.csv';
		return [$list, 'GET /a/files/shop_products.csv', 'HEAD /a/?variant=*&filename=shop_variants.csv', 'GET /a/files/shop_variants.csv'];
	}

	private function fetchRequests(): array
	{
		$fetch = fn(array $r): bool => isset($r['query']['products_id']) || isset($r['query']['variant']) || str_contains($r['path'], '/files/');
		return array_map(fn(array $r): string => $r['method'] . ' ' . urldecode($r['uri']), array_values(array_filter(self::$shop->waitFor(4, 10.0, 1.0, $fetch), $fetch)));
	}

	private function outboundPaths(string $kind, string $varenr): array
	{
		$paths = array_column(array_filter(self::$shop->requests(), fn(array $r): bool => isset($r['query'][$kind]) && ($r['query']['itemNo'] ?? null) === $varenr), 'path');
		sort($paths);
		return $paths;
	}

	private function removeItem(string $varenr): void
	{
		$id = self::one('SELECT id FROM varer WHERE varenr = $1', [$varenr])['id'] ?? null;
		if ($id !== null) {
			self::rows('DELETE FROM shop_varer WHERE saldi_id = $1', [$id]);
			self::rows('DELETE FROM lagerstatus WHERE vare_id = $1', [$id]);
			self::rows('DELETE FROM batch_kob WHERE vare_id = $1', [$id]);
			self::rows('DELETE FROM varer WHERE id = $1', [$id]);
		}
		self::$shop->unserve('shop_products.csv');
		self::$shop->unserve('shop_variants.csv');
	}

	/** A shop_varer link for an item the test doesn't otherwise touch. */
	private function otherLink(): int
	{
		$other = self::$fx->item(['salgspris' => 10.0, 'kostpris' => 5.0]);
		return (int)self::one("INSERT INTO shop_varer (saldi_id, shop_id, saldi_variant, shop_variant) VALUES ($1, 990, 0, 0) RETURNING id", [$other['id']])['id'];
	}

	private function counts(): array
	{
		return self::one('SELECT (SELECT count(*) FROM varer) AS varer, (SELECT count(*) FROM lagerstatus) AS lagerstatus, (SELECT count(*) FROM shop_varer) AS shop_varer');
	}

	/** The PHP source without its comments and strings' surroundings: only code that runs. */
	private static function liveCode(string $file): string
	{
		$code = '';
		foreach (token_get_all((string)file_get_contents($file)) as $token) {
			if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML], true)) {
				continue;
			}
			$code .= is_array($token) ? $token[1] : $token;
		}
		return $code;
	}

	/** Calls tests/characterization/support/legacy_shop_call.php and returns what it printed. */
	private function call(array $get): array
	{
		$output = $this->page('tests/characterization/support/legacy_shop_call.php', $get);
		$this->assertMatchesRegularExpression('/RESULT (.*)$/s', $output);
		preg_match('/RESULT (.*)$/s', $output, $m);
		return ['result' => trim($m[1]), 'output' => $output];
	}

	private function page(string $page, array $get, array $post = []): string
	{
		$output = (string)tempnam(sys_get_temp_dir(), 'legacy-shop-page');
		try {
			self::runChild('run_legacy_shop_page.php', [$page, CharacterizationEnv::SESSION_ID, json_encode(['get' => $get, 'post' => $post]), $output]);
			return (string)file_get_contents($output);
		} finally {
			unlink($output);
		}
	}

	/** Like page(), for a page that is expected to end on a fatal error. */
	private function runPage(string $page, array $get): array
	{
		$output = (string)tempnam(sys_get_temp_dir(), 'legacy-shop-page');
		try {
			$res = CharacterizationEnv::runChild(__DIR__ . '/../support/run_legacy_shop_page.php', [$page, CharacterizationEnv::SESSION_ID, json_encode(['get' => $get]), $output]);
			return $res + ['output' => (string)file_get_contents($output)];
		} finally {
			unlink($output);
		}
	}

	/** lager/webshopStock.php keeps its job outside the repository, per tenant. */
	private static function removeStockJob(): void
	{
		$dir = sys_get_temp_dir() . '/saldi-shop-stock-' . substr(hash('sha256', CharacterizationEnv::repoRoot() . '/lager:' . CharacterizationEnv::testDb()), 0, 24);
		if (is_dir($dir)) {
			array_map('unlink', glob("$dir/{,.}*", GLOB_BRACE) ? array_filter(glob("$dir/{,.}*", GLOB_BRACE), 'is_file') : []);
			rmdir($dir);
		}
	}

	private static function tempDir(): string
	{
		return CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb();
	}
}
