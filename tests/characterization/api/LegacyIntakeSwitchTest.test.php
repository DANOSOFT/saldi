<?php
// tests/characterization/api/LegacyIntakeSwitchTest.test.php
//
// The SD-750 legacy coexistence switches on a real tenant: the WP01 schema is installed on the
// throwaway clone, two Shopify shops are mapped to legacy slots (afd 2 -> slot 5, afd 4 -> slot 4)
// and the slots point at LegacyShopStub. Pinned:
//
// - api/rest_api.php insert_shop_order: an open slot imports as before; a slot closed for intake
//   answers 410 and writes no order; the go-live drain manifest still admits its own orders; a
//   cutover in progress answers 503; an afd without a shop is never affected.
// - api/hent_ordrer.php: with afd 4's slot closed nothing is fetched and the import lock is
//   never taken.
// - debitor/ordreliste.php: a closed intake slot is not asked for orders; the SHOP import follows
//   afd 4's slot.
// - lager/varekort.php: a slot closed for outbound gets no price.
//
// Synthetic data. Requires the docker-compose stack (see README) - skips cleanly otherwise.
//
// History:
// 20261026 CL/SZ Created (SD-750).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../support/LegacyShopStub.php';

final class LegacyIntakeSwitchTest extends CharacterizationTestCase
{
	private static LegacyShopStub $shop;
	/** The tenant's REST key for this run only, never a literal in the repository. */
	private static string $key;
	private static bool $createdTemp = false;
	private static int $orderNo = 7000;
	private int $lastOrderId = 0;
	private int $writes = 0;

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
		$schema = self::runChild('run_legacy_shop_schema.php', []);
		if (!str_contains($schema['stdout'], '"version":1')) {
			self::fail("Shopify schema not installed on the clone.\nstdout: {$schema['stdout']}\nstderr: {$schema['stderr']}");
		}
		self::$shop = new LegacyShopStub();
		self::$key = bin2hex(random_bytes(16));
		self::rows("INSERT INTO shopify_shops (shop_key, domain, afd, legacy_slot) VALUES ('chartest_b', 'chartest-b.myshopify.com', 2, 5), ('chartest_a', 'chartest-a.myshopify.com', 4, 4)");
		self::rows("DELETE FROM grupper WHERE art = 'API'");
		self::rows("INSERT INTO grupper (beskrivelse, art, kodenr, box1, box2, box4, box5, box6) VALUES ($1, 'API', '1', $2, '*', $3, $4, $5)",
			[Fixtures::TAG, self::$key, self::$shop->url('a'), self::$shop->url('b'), self::$shop->url('c')]);
	}

	public static function tearDownAfterClass(): void
	{
		if (isset(self::$shop)) {
			self::$shop->stop();
		}
		if (self::$createdTemp) {
			exec('rm -rf ' . escapeshellarg(self::tempDir()));
		}
		// hent_ordrer.php's own import lock, in case an import got as far as taking it.
		$importLock = CharacterizationEnv::repoRoot() . '/api/' . CharacterizationEnv::testDb() . '.lock';
		if (is_file($importLock)) {
			unlink($importLock);
		}
		parent::tearDownAfterClass();
		// The clone carries the Shopify schema now: drop it so no other suite inherits it.
		CharacterizationEnv::teardownTenant();
	}

	protected function setUp(): void
	{
		self::$shop->reset();
		self::rows("DELETE FROM settings WHERE var_grp LIKE 'shopify_legacy:%'");
		if (is_file(self::tempDir() . '/shoptidspkt.txt')) {
			unlink(self::tempDir() . '/shoptidspkt.txt');
		}
	}

	// ---- api/rest_api.php ------------------------------------------------------------------------

	public function testAnOpenSlotImportsTheOrderAsBefore(): void
	{
		[$status, $body, $orders] = $this->restOrder(2);

		$this->assertSame(0, $status, 'no status set: the default 200');
		$this->assertMatchesRegularExpression('/^"?\d+"?$/', $body, 'the new order id');
		$this->assertSame(1, $orders);
	}

	public function testAClosedSlotAnswers410AndWritesNoOrder(): void
	{
		$this->setting(5, 'intake_enabled', '0');

		[$status, $body, $orders] = $this->restOrder(2);

		$this->assertSame(410, $status);
		$this->assertSame('"Legacy intake is closed for this shop"', $body);
		$this->assertSame([0, 0], [$orders, $this->writes], 'no order, customer or shop-order mapping written');
	}

	public function testAClosedSlotDoesNotTouchOtherShopsOrUnmappedDepartments(): void
	{
		$this->setting(5, 'intake_enabled', '0');

		$this->assertSame(1, $this->restOrder(4)[2], 'afd 4 is slot 4, still open');
		$this->assertSame(1, $this->restOrder(3)[2], 'afd 3 has no Shopify shop');
	}

	public function testTheDrainManifestStillAdmitsItsOwnOrders(): void
	{
		$next = self::$orderNo + 1;
		$this->setting(5, 'intake_enabled', 'off');
		$this->setting(5, 'drain_until', (string)(time() + 1800));
		$this->setting(5, 'drain_manifest', "$next");

		$this->assertSame(1, $this->restOrder(2)[2], "order $next is in the manifest");
		$refused = $this->restOrder(2);
		$this->assertSame([410, 0], [$refused[0], $refused[2]], 'the next one is not');
	}

	public function testACutoverInProgressAnswers503(): void
	{
		$fh = fopen(self::tempDir() . '/.ht_shopify_legacy_5.lock', 'c');
		$this->assertTrue(flock($fh, LOCK_EX));
		try {
			[$status, , $orders] = $this->restOrder(2);
		} finally {
			flock($fh, LOCK_UN);
			fclose($fh);
		}

		$this->assertSame([503, 0], [$status, $orders]);
	}

	// ---- cutover --------------------------------------------------------------------------------

	public function testACutoverThatDoesNotCommitIsRolledBackBeforeTheLockIsReleased(): void
	{
		foreach (['uncommitted' => 'rolled back', 'throws' => 'settings write failed'] as $mode => $message) {
			$res = self::runChild('run_legacy_shop_cutover.php', [$mode]);
			$out = json_decode(trim($res['stdout']), true);

			$this->assertSame('RuntimeException', $out['exception'] ?? null, "$mode: " . $res['stdout'] . $res['stderr']);
			$this->assertStringContainsString($message, $out['message']);
			$this->assertTrue($out['idle'], "$mode: no transaction left open");
			$this->assertNull(self::one("SELECT var_value FROM settings WHERE var_grp = 'shopify_legacy:5'"), "$mode: the closure never became visible");
		}

		$res = self::runChild('run_legacy_shop_cutover.php', ['commits']);
		$this->assertSame(['result' => 'closed', 'idle' => true], json_decode(trim($res['stdout']), true));
		$this->assertSame('410', (string)$this->restOrder(2)[0], 'a committed closure is what the next request sees');
	}

	// ---- api/hent_ordrer.php ---------------------------------------------------------------------

	public function testWithAfd4sSlotClosedTheImportFetchesNothing(): void
	{
		$this->setting(4, 'intake_enabled', '0');
		$lock = CharacterizationEnv::repoRoot() . '/api/' . CharacterizationEnv::testDb() . '.lock';
		$before = (int)self::one('SELECT count(*) AS n FROM ordrer')['n'];

		$out = $this->page('api/hent_ordrer.php', ['prefix' => 'ct', 'nye_ordrer' => '11,12']);

		$this->assertFileDoesNotExist($lock, 'the import lock is never taken');
		$this->assertStringContainsString('window.close()', $out);
		$this->assertSame($before, (int)self::one('SELECT count(*) AS n FROM ordrer')['n']);
		$this->assertStringContainsString('ct/12 skipped: legacy intake closed for slot 4', (string)file_get_contents(self::tempDir() . '/shop.log'));
	}

	// ---- debitor/ordreliste.php ------------------------------------------------------------------

	public function testTheOrderListDoesNotAskAClosedSlotAndOutboundDoesNotMatter(): void
	{
		$this->setting(5, 'intake_enabled', '0');
		$this->setting(4, 'outbound_enabled', '0');

		$this->page('debitor/ordreliste.php', ['valg' => 'ordrer']);

		$paths = array_column(self::$shop->waitFor(2), 'path');
		sort($paths);
		$this->assertSame(['/a/', '/c/'], $paths);
	}

	public function testTheShopImportFollowsAfd4sSlot(): void
	{
		self::rows("UPDATE grupper SET box2 = $1 WHERE art = 'DIV' AND kodenr = '5'", [self::$shop->url('imp') . '?shop=1']);
		if (self::one("SELECT id FROM grupper WHERE art = 'DIV' AND kodenr = '5'") === null) {
			self::rows("INSERT INTO grupper (beskrivelse, art, kodenr, box2) VALUES ($1, 'DIV', '5', $2)", [Fixtures::TAG, self::$shop->url('imp') . '?shop=1']);
		}

		$open = $this->page('debitor/ordreliste.php', ['valg' => 'ordrer']);
		$this->setting(4, 'intake_enabled', '0');
		$closed = $this->page('debitor/ordreliste.php', ['valg' => 'ordrer']);
		self::rows("UPDATE grupper SET box2 = '' WHERE art = 'DIV' AND kodenr = '5'");

		$this->assertStringContainsString('SHOP import</span>', $open);
		$this->assertStringNotContainsString('SHOP import</span>', $closed);
	}

	// ---- lager/varekort.php ----------------------------------------------------------------------

	public function testAProductCardSendsNoPriceToASlotClosedForOutbound(): void
	{
		$this->setting(6, 'outbound_enabled', 'nej');
		$item = self::$fx->item(['salgspris' => 200.0, 'kostpris' => 80.0]);

		$this->page('lager/varekort.php', ['id' => (string)$item['id']]);

		$sent = array_filter(self::$shop->requests(), fn(array $r): bool => ($r['query']['itemNo'] ?? null) === $item['varenr']);
		$this->assertSame(['/a/', '/b/'], array_values(array_column($sent, 'path')));
		self::rows('DELETE FROM varer WHERE id = $1', [$item['id']]);
	}

	// ---- helpers ---------------------------------------------------------------------------------

	private function setting(int $slot, string $name, string $value): void
	{
		self::rows("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ($1, $2, $3, 'chartest')", [$name, "shopify_legacy:$slot", $value]);
	}

	/**
	 * Posts one legacy order header for an afd, the way a legacy webshop does.
	 *
	 * @return array{0:int, 1:string, 2:int} HTTP status (0 = default 200), JSON body, orders written;
	 *               every row written to ordrer, adresser and shop_ordrer is counted in $this->writes
	 */
	private function restOrder(int $afd): array
	{
		$this->lastOrderId = (int)self::one('SELECT coalesce(max(id), 0) AS n FROM ordrer')['n'];
		$shopOrderId = (string)++self::$orderNo;
		$count = fn(): int => (int)self::one('SELECT (SELECT count(*) FROM ordrer) + (SELECT count(*) FROM adresser) + (SELECT count(*) FROM shop_ordrer) AS n')['n'];
		$before = $count();
		$res = $this->runPage('api/rest_api.php', [
			'action' => 'insert_shop_order', 'db' => CharacterizationEnv::testDb(), 'saldiuser' => 'chartest', 'key' => self::$key,
			'afd' => (string)$afd, 'shop_ordre_id' => $shopOrderId, 'firmanavn' => 'Chartest kunde', 'email' => 'kunde@example.dk',
			// A phone number makes it the account number. Without one, an empty tenant hits a
			// PHP 8 fatal in insert_shop_order ($ktonr never set) that predates SD-750.
			'tlf' => '4' . $shopOrderId . '00',
			'nettosum' => '100', 'momssum' => '25', 'momssats' => '25', 'valuta' => 'DKK', 'ordredate' => date('Y-m-d'),
			'gruppe' => '1', // the template's debtor group with a VAT code
		]);
		$orders = (int)self::one('SELECT count(*) AS n FROM ordrer WHERE id > $1', [$this->lastOrderId])['n'];
		$this->lastOrderId = (int)self::one('SELECT coalesce(max(id), 0) AS n FROM ordrer')['n'];
		$this->writes = $count() - $before;
		return [$res['status'], trim($res['output']), $orders];
	}

	private function page(string $page, array $get): string
	{
		return $this->runPage($page, $get)['output'];
	}

	/** @return array{status:int, output:string} */
	private function runPage(string $page, array $get): array
	{
		$output = (string)tempnam(sys_get_temp_dir(), 'legacy-shop-page');
		try {
			$res = self::runChild('run_legacy_shop_page.php', [$page, CharacterizationEnv::SESSION_ID, json_encode(['get' => $get]), $output]);
			preg_match('/CHARTEST_HTTP (\d+)/', $res['stdout'], $m);
			return ['status' => (int)($m[1] ?? 0), 'output' => (string)file_get_contents($output)];
		} finally {
			unlink($output);
		}
	}

	private static function tempDir(): string
	{
		return CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb();
	}
}
