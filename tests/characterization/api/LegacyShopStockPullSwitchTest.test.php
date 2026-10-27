<?php
// tests/characterization/api/LegacyShopStockPullSwitchTest.test.php
//
// The SD-778 coexistence switches on the legacy stock, cost-price and product-pull flows, on a
// throwaway clone whose shop endpoints (box4/box5/box6 = legacy slots 4/5/6) point at
// LegacyShopStub. With no shopify_legacy settings the flows behave as
// LegacyShopStockPullCharacterizationTest pins them. Pinned here:
//
// - lager/webshopStock.php: a slot closed for outbound is not offered and gets nothing, the
//   others get the same payload; a URL shared with a closed slot stays with it (nobody gets it);
//   a job whose slot closes stops before sending.
// - api/updateShopCostPrice.php: nothing is sent while slot 4 is closed for outbound.
// - api/varesync.php and api/varesync_pos8.php: with slot 4 closed for intake nothing is fetched
//   or written, and the page says why; while a cutover holds slot 4 they say "try again";
//   slot 4 closed for outbound only still fetches, and the new items' push skips slot 4.
//
// Synthetic data. Requires the docker-compose stack (see README) - skips cleanly otherwise.
//
// History:
// 20261027 CL/SZ Created (SD-778, sub-task of SD-750).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../support/LegacyShopStub.php';

final class LegacyShopStockPullSwitchTest extends CharacterizationTestCase
{
	private const CLOSED = 'Varer hentes ikke fra den gamle webshop: den er lukket, fordi butikken er flyttet til Shopify.';
	private const BUSY = 'Webshoppen er ved at blive skiftet. Prøv igen om lidt.';

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
		// lager/webshopStock.php wants exactly '1' in the varer rights position (9).
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
		CharacterizationEnv::teardownTenant();
	}

	protected function setUp(): void
	{
		self::$shop->reset();
		self::removeStockJob();
		self::rows("DELETE FROM settings WHERE var_grp LIKE 'shopify_legacy:%'");
		$this->setSlots('a', 'b', 'c');
	}

	// ---- lager/webshopStock.php --------------------------------------------------------------

	public function testAStockSlotClosedForOutboundIsNotOfferedAndTheOthersGetTheSamePayload(): void
	{
		$this->setting(5, 'outbound_enabled', 'off');
		$this->setting(4, 'intake_enabled', '0');
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);
		self::rows('INSERT INTO lagerstatus (vare_id, lager, variant_id, beholdning) VALUES ($1, 1, 0, 7)', [$item['id']]);

		[$offered, $job] = $this->runStockJob();

		$this->assertSame(['1', '3'], $offered, 'slot 2 (box5) is closed; intake does not matter');
		$this->assertSame('done', $job['status']);
		$this->assertSame(['/a/', '/c/'], $this->stockPaths($item['varenr']));
		foreach ($this->stockRequests($item['varenr']) as $request) {
			$this->assertSame(['update_stock' => '0', 'stock' => '7', 'totalStock' => '7', 'itemNo' => $item['varenr'], 'itemNoAlias' => ''], $request['query']);
		}
		self::rows('DELETE FROM lagerstatus WHERE vare_id = $1', [$item['id']]);
		self::rows('DELETE FROM varer WHERE id = $1', [$item['id']]);
	}

	public function testAUrlSharedWithAClosedSlotDoesNotMoveToTheLaterSlot(): void
	{
		$this->setSlots('a', 'a', 'c');
		$this->setting(4, 'outbound_enabled', 'nej');
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);

		[$offered] = $this->runStockJob();

		$this->assertSame(['3'], $offered, 'box5 repeats the closed box4, so it is not offered either');
		$this->assertSame(['/c/'], $this->stockPaths($item['varenr']));
		self::rows('DELETE FROM varer WHERE id = $1', [$item['id']]);
	}

	public function testAJobWhoseSlotClosesStopsBeforeSending(): void
	{
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);
		$page = $this->page('lager/webshopStock.php', []);
		preg_match('/const csrf = "([0-9a-f]{64})"/', $page, $csrf);
		$job = json_decode($this->page('lager/webshopStock.php', [], ['action' => 'start', 'csrf' => $csrf[1], 'shops' => ['1', '2', '3']]), true)['job'];

		$this->setting(6, 'outbound_enabled', 'false');
		$batch = json_decode($this->page('lager/webshopStock.php', [], ['action' => 'batch', 'csrf' => $csrf[1], 'job' => $job['id']]), true);

		$this->assertSame('Webshopopsætningen er ændret. Afslut kørslen og start en ny.', $batch['error'] ?? null);
		$this->assertSame([], $this->stockPaths($item['varenr']));
		self::rows('DELETE FROM varer WHERE id = $1', [$item['id']]);
	}

	// ---- api/updateShopCostPrice.php ----------------------------------------------------------

	public function testTheCostPriceIsNotSentWhileSlot4IsClosedForOutbound(): void
	{
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);

		$this->setting(4, 'intake_enabled', 'off');
		$open = $this->call(['call' => 'cost', 'id' => (string)$item['id']]);
		$this->assertSame('OK', $open, 'intake does not matter');
		$this->assertCount(1, self::$shop->waitFor(1));

		self::$shop->reset();
		$this->setting(4, 'outbound_enabled', 'off');
		$this->assertSame('legacy outbound closed', $this->call(['call' => 'cost', 'id' => (string)$item['id']]));
		$this->assertSame([], self::$shop->waitFor(0, 0, 1.0));
		self::rows('DELETE FROM varer WHERE id = $1', [$item['id']]);
	}

	// ---- api/varesync.php, api/varesync_pos8.php ----------------------------------------------

	public function testWithSlot4ClosedForIntakeHentNyeVarerFetchesAndWritesNothing(): void
	{
		$no = 'CHARNEW' . bin2hex(random_bytes(3));
		$this->serveProducts([[901, $no, '5700000000017', '149.00', '60.00', 'Ny shopvare']]);
		$this->setting(4, 'intake_enabled', 'off');
		$before = $this->counts();

		$output = $this->page('systemdata/diverse.php', ['sektion' => 'api_valg', 'varesync' => '1']);

		$this->assertStringContainsString(self::CLOSED, $output);
		$this->assertStringNotContainsString('Opretter', $output);
		$this->assertSame([], self::$shop->waitFor(0, 0, 1.0));
		$this->assertSame($before, $this->counts(), 'no varer, lagerstatus, shop_varer or variant_varer row is written, changed or removed');
		$this->unserve();
	}

	public function testWithSlot4ClosedForIntakeOpdaterFraShopChangesNothing(): void
	{
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);
		$this->serveProducts([[902, $item['varenr'], '5700000000024', '249.00', '90.00', 'Opdateret navn']]);
		$this->setting(4, 'intake_enabled', '0');
		$row = fn(): array => self::one('SELECT stregkode, beskrivelse, salgspris, kostpris FROM varer WHERE id = $1', [$item['id']]);
		$before = $row();
		$tables = $this->counts();

		$output = $this->page('systemdata/diverse.php', ['sektion' => 'api_valg', 'varesync' => '2']);

		$this->assertStringContainsString(self::CLOSED, $output);
		$this->assertSame($before, $row());
		$this->assertSame('200.000', $before['salgspris']);
		$this->assertSame($tables, $this->counts());
		$this->assertSame([], self::$shop->waitFor(0, 0, 1.0));
		self::rows('DELETE FROM varer WHERE id = $1', [$item['id']]);
		$this->unserve();
	}

	public function testThePos8CopyFetchesAndWritesNothingWhileSlot4IsClosed(): void
	{
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);
		$this->serveProducts([[903, $item['varenr'], '5700000000031', '259.00', '95.00', 'Pos8 navn']]);
		$this->setting(4, 'intake_enabled', 'off');
		$before = $this->counts();

		$output = $this->page('tests/characterization/support/legacy_shop_call.php', ['call' => 'pos8', 'valg' => '2']);

		$this->assertStringContainsString(self::CLOSED, $output, 'it stops before its PHP 8 fatal error');
		$this->assertStringContainsString('RESULT NULL', $output);
		$this->assertSame([], self::$shop->waitFor(0, 0, 1.0));
		$this->assertSame($before, $this->counts());
		self::rows('DELETE FROM varer WHERE id = $1', [$item['id']]);
		$this->unserve();
	}

	public function testWhileACutoverHoldsSlot4ThePullSaysTryAgainAndDoesNothing(): void
	{
		$this->serveProducts([[904, 'CHARBUSY', '5700000000048', '1.00', '1.00', 'Busy']]);
		$before = $this->counts();
		$lock = fopen(self::tempDir() . '/.ht_shopify_legacy_4.lock', 'c');
		$this->assertTrue(flock($lock, LOCK_EX | LOCK_NB), 'the test holds the cutover lock');
		try {
			$output = $this->page('systemdata/diverse.php', ['sektion' => 'api_valg', 'varesync' => '1']);
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}

		$this->assertStringContainsString(self::BUSY, $output);
		$this->assertSame([], self::$shop->waitFor(0, 0, 1.0));
		$this->assertSame($before, $this->counts());
		$this->unserve();
	}

	public function testSlot4ClosedForOutboundOnlyStillFetchesAndTheNewItemsPushSkipsIt(): void
	{
		$no = 'CHARNEW' . bin2hex(random_bytes(3));
		$this->serveProducts([[905, $no, '5700000000055', '149.00', '60.00', 'Ny shopvare']]);
		$this->setting(4, 'outbound_enabled', 'off');

		$output = $this->page('systemdata/diverse.php', ['sektion' => 'api_valg', 'varesync' => '1']);

		$this->assertStringContainsString("Opretter $no, Ny shopvare", $output);
		$fetch = fn(array $r): bool => isset($r['query']['products_id']) || isset($r['query']['variant']) || str_contains($r['path'], '/files/');
		$this->assertCount(4, array_filter(self::$shop->waitFor(4, 10.0, 1.0, $fetch), $fetch), 'the fetch from box4 is intake');
		$push = array_column(array_filter(self::$shop->requests(), fn(array $r): bool => isset($r['query']['update_price']) && ($r['query']['itemNo'] ?? null) === $no), 'path');
		sort($push);
		$this->assertSame(['/b/', '/c/'], $push);
		$id = self::one('SELECT id FROM varer WHERE varenr = $1', [$no])['id'];
		foreach (['shop_varer' => 'saldi_id', 'lagerstatus' => 'vare_id', 'batch_kob' => 'vare_id', 'varer' => 'id'] as $table => $column) {
			self::rows("DELETE FROM $table WHERE $column = $1", [$id]);
		}
		$this->unserve();
	}

	// ---- helpers -------------------------------------------------------------------------------

	private function setting(int $slot, string $name, string $value): void
	{
		self::rows("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ($1, $2, $3, 'chartest')", [$name, "shopify_legacy:$slot", $value]);
	}

	private function setSlots(string $four, string $five, string $six): void
	{
		$url = fn(string $name): string => $name === '' ? '' : self::$shop->url($name);
		self::rows("UPDATE grupper SET box4 = $1, box5 = $2, box6 = $3 WHERE art = 'API'", [$url($four), $url($five), $url($six)]);
	}

	/** @return array{0: list<string>, 1: array<string, mixed>} the offered slots and the job's progress */
	private function runStockJob(): array
	{
		$page = $this->page('lager/webshopStock.php', []);
		$this->assertMatchesRegularExpression('/const csrf = "([0-9a-f]{64})"/', $page);
		preg_match('/const csrf = "([0-9a-f]{64})"/', $page, $csrf);
		preg_match_all('/name="shop" value="(\d)"/', $page, $offered);
		$job = json_decode($this->page('lager/webshopStock.php', [], ['action' => 'start', 'csrf' => $csrf[1], 'shops' => $offered[1]]), true)['job'];
		for ($i = 0; $i < 20 && $job['status'] === 'paused'; $i++) {
			$job = json_decode($this->page('lager/webshopStock.php', [], ['action' => 'batch', 'csrf' => $csrf[1], 'job' => $job['id']]), true)['job'];
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

	/** Same files as in LegacyShopStockPullCharacterizationTest::serveProducts(). */
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

	private function unserve(): void
	{
		self::$shop->unserve('shop_products.csv');
		self::$shop->unserve('shop_variants.csv');
	}

	/**
	 * Every row of the tables a pull writes, so an UPDATE shows as well as an INSERT or DELETE.
	 * A link with NULL variant fields is seeded first: both varesync() copies start by setting
	 * those to 0, which is the first write a misplaced switch would let through.
	 */
	private function counts(): array
	{
		if (self::one('SELECT id FROM shop_varer WHERE saldi_variant IS NULL') === null) {
			self::rows('INSERT INTO shop_varer (saldi_id, shop_id, saldi_variant, shop_variant) VALUES (1, 991, NULL, NULL)');
		}
		$snapshot = [];
		foreach (['varer', 'lagerstatus', 'shop_varer', 'variant_varer'] as $table) {
			$snapshot[$table] = self::rows("SELECT * FROM $table ORDER BY id");
		}
		return $snapshot;
	}

	private function call(array $get): string
	{
		$output = $this->page('tests/characterization/support/legacy_shop_call.php', $get);
		$this->assertMatchesRegularExpression('/RESULT (.*)$/s', $output);
		preg_match('/RESULT (.*)$/s', $output, $m);
		return trim($m[1]);
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

	private static function removeStockJob(): void
	{
		$dir = sys_get_temp_dir() . '/saldi-shop-stock-' . substr(hash('sha256', CharacterizationEnv::repoRoot() . '/lager:' . CharacterizationEnv::testDb()), 0, 24);
		if (is_dir($dir)) {
			array_map('unlink', array_filter(glob("$dir/{,.}*", GLOB_BRACE) ?: [], 'is_file'));
			rmdir($dir);
		}
	}

	private static function tempDir(): string
	{
		return CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb();
	}
}
