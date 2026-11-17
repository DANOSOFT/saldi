<?php

declare(strict_types=1);

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

/**
 * The webshop offer reconciler (includes/stdFunc/shopOfferPush.php) against a fresh tenant and a fake shop: a loopback-only
 * `php -S` server (support/shop_price_recorder.php) configured as all three webshop endpoints. It records every call, keeps each
 * shop's own price per item as a real shop would (applied when the call is applied, which can be later than it arrived), and
 * can fail, refuse, apply late or answer late per shop.
 *
 * Pins that only a tick sends offer prices (no card save, sale, import or unauthorised API call does), that a tick sends the
 * offer that applies now and restores the normal prices after it, that the card's and the sale's calls send each shop only the
 * pair it confirmed, that a send is certified only when no other send overlapped it, that refusal counters belong to one price
 * pair, that unwritable state sends nothing, that one tick runs at a time within its budget and the next goes on from where it
 * stopped, that every tracked item is sent again after an hour, and that an item without an offer costs nothing.
 *
 * History:
 * 20261117 CL/LH Created (Havemøbelland Black Week: offers in the webshops).
 * 20261117 CL/LH Codex verification: one regression test per finding (unrecorded card/sale offers, partial delivery, the budget with
 *                slow shops, the schedule race, the retry state after a first failure, a refused restoration).
 * 20261117 CL/LH An install without a webshop keeps no push state after a campaign import, and a look there makes the push dormant.
 * 20261117 CL/LH Single-writer rebuild after the second Codex verification: the findings of both verifications that still apply,
 *                the concurrency of a card save, a stock sync and two ticks against a shop that applies late, the tick lock, and
 *                no offer price without an authorised tick.
 */
final class ShopOfferPushTest extends CharacterizationTestCase
{
	/** @var resource|null */
	private static $server = null;
	private static string $shopLog = '';
	private static string $tempDir = '';
	private static int $stockGroup = 0;
	/** @var list<string> */
	private static array $files = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		date_default_timezone_set('Europe/Copenhagen');
		self::$shopLog = tempnam(sys_get_temp_dir(), 'shoprec');
		$probe = stream_socket_server('tcp://127.0.0.1:0');
		$name = (string)stream_socket_get_name($probe, false);
		$port = (int)substr($name, strrpos($name, ':') + 1);
		fclose($probe);
		$env = array_merge(getenv(), ['SALDI_SHOP_RECORDER_LOG' => self::$shopLog, 'PHP_CLI_SERVER_WORKERS' => '12']);
		self::$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/../support/shop_price_recorder.php'],
			[0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env);
		for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1); $i++) {
			usleep(100000);
		}
		$db = CharacterizationEnv::testDb();
		self::$tempDir = CharacterizationEnv::repoRoot() . '/temp/' . $db;
		@mkdir(self::$tempDir, 0777, true);
		if (self::one("SELECT id FROM grupper WHERE art = 'API'") === null) {
			self::rows("INSERT INTO grupper (id, beskrivelse, kodenr, art, fiscal_year) VALUES ($1, 'API', '1', 'API', 0)", [CharacterizationEnv::nextId(self::$tenant, 'grupper')]);
		}
		self::rows("UPDATE grupper SET box1 = 'test-key', box2 = '*', box4 = $1, box5 = $2, box6 = $3 WHERE art = 'API'",
			["http://127.0.0.1:$port/shop1.php?shop=1", "http://127.0.0.1:$port/shop2.php?shop=2", "http://127.0.0.1:$port/shop3.php?shop=3"]);
		self::rows("DELETE FROM settings WHERE var_name = 'vatOnItemCard' AND var_grp = 'items'");
		self::rows("INSERT INTO settings (id, var_name, var_grp, var_value, var_description, user_id) VALUES ($1, 'vatOnItemCard', 'items', 'on', 'test', 0)", [CharacterizationEnv::nextId(self::$tenant, 'settings')]);
		$group = self::one(
			"SELECT g.kodenr FROM grupper g JOIN kontoplan k ON k.kontonr::text = g.box4 AND k.regnskabsaar = g.fiscal_year
			 WHERE g.art = 'VG' AND g.fiscal_year = $1 AND COALESCE(g.box7, '') = '' AND COALESCE(k.moms, '') <> '' AND COALESCE(g.box8, '') = 'on'
			 ORDER BY g.kodenr::int LIMIT 1",
			[self::$regnaar]
		);
		self::assertNotNull($group, 'the installed tenant has a stocked item group with a VAT-coded sales account');
		self::$stockGroup = (int)$group['kodenr'];
	}

	public static function tearDownAfterClass(): void
	{
		if (self::$server) {
			proc_terminate(self::$server);
			proc_close(self::$server);
		}
		foreach (array_merge(self::$files, (array)glob(self::$shopLog . '*')) as $file) {
			@unlink((string)$file);
		}
		self::clearState();
		if (self::$tempDir !== '' && is_dir(self::$tempDir)) {
			foreach (array_merge((array)glob(self::$tempDir . '/shopsync/*'), (array)glob(self::$tempDir . '/*'), (array)glob(self::$tempDir . '/.ht*')) as $file) {
				@unlink((string)$file);
			}
			@rmdir(self::$tempDir . '/shopoffer');
			@rmdir(self::$tempDir . '/shopsync');
			@rmdir(self::$tempDir);
		}
		parent::tearDownAfterClass();
	}

	/** Every test starts with no offer anywhere, no offer state, an empty shop log and every shop answering at once. */
	protected function setUp(): void
	{
		self::rows("UPDATE varer SET specialtype = NULL, special_price = 0");
		self::clearState();
		foreach ((array)glob(self::$shopLog . '.*') as $file) {
			@unlink((string)$file);
		}
		file_put_contents(self::$shopLog, '');
	}

	private static function clearState(): void
	{
		$dir = self::$tempDir . '/shopoffer';
		if (is_file($dir)) {
			@unlink($dir);
		}
		foreach ((array)glob($dir . '/*') as $file) {
			is_dir((string)$file) ? @rmdir((string)$file) : @unlink((string)$file);
		}
		@rmdir($dir);
	}

	/** An item with its offer (price ex VAT, dates Y-m-d, times). */
	private static function item(string $varenr, ?array $offer = null, float $salgspris = 639.2, float $retail = 899.0): int
	{
		$row = self::one(
			"INSERT INTO varer (id, varenr, beskrivelse, gruppe, salgspris, kostpris, retail_price, lukket, specialtype, special_price,
			                    special_from_date, special_to_date, special_from_time, special_to_time)
			 VALUES ($1, $2, 'Offer push test', $3, $4, $5, $6, '', $7, $8, $9, $10, $11, $12) RETURNING id",
			[CharacterizationEnv::nextId(self::$tenant, 'varer'), $varenr, self::$stockGroup, $salgspris, round($salgspris / 2, 3), $retail,
			 $offer ? 'price' : null, $offer['price'] ?? 0, $offer['from'] ?? null, $offer['to'] ?? null,
			 $offer['fromTime'] ?? '00:00:00', $offer['toTime'] ?? '23:59:59']
		);
		return (int)$row['id'];
	}

	/** An offer from yesterday until the end of today, or for $days more days. */
	private static function offer(float $price = 159.2, int $days = 0): array
	{
		return ['price' => $price, 'from' => date('Y-m-d', time() - 86400), 'to' => date('Y-m-d', time() + $days * 86400)];
	}

	/** @return array<string, mixed> */
	private static function tick(?int $now = null, float $budget = 10, ?int $maxItems = null): array
	{
		$args = ['tick', $now === null ? 'now' : (string)$now, (string)$budget];
		if ($maxItems !== null) $args[] = (string)$maxItems;
		return self::runner($args);
	}

	/** @param list<string> $args @param list<string> $phpOptions @return array<string, mixed> */
	private static function runner(array $args, array $phpOptions = []): array
	{
		CharacterizationEnv::resyncSequences(self::$tenant);
		$cmd = array_merge([PHP_BINARY], $phpOptions, [__DIR__ . '/../support/run_shop_offer_push.php', CharacterizationEnv::testDb()], $args);
		$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		fclose($pipes[0]);
		$stdout = (string)stream_get_contents($pipes[1]);
		$stderr = (string)stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($proc);
		return self::parse($stdout, $stderr);
	}

	/** @return array<string, mixed> */
	private static function parse(string $stdout, string $stderr): array
	{
		self::assertStringNotContainsString('Fatal error', $stdout . $stderr, "runner crashed\n$stdout\n$stderr");
		self::assertStringNotContainsString('shopOffer', $stderr, 'no warning from the offer reconciler');
		$lines = array_values(array_filter(explode("\n", trim($stdout))));
		$out = $lines ? json_decode((string)end($lines), true) : null;
		self::assertIsArray($out, "runner output\nstdout: $stdout\nstderr: $stderr");
		return $out;
	}

	/** @param list<string> $args @return array{0: resource, 1: string, 2: string} The runner started in the background; finish it with finish(). */
	private static function start(array $args, array $phpOptions = []): array
	{
		$out = (string)tempnam(sys_get_temp_dir(), 'offerrun');
		$err = (string)tempnam(sys_get_temp_dir(), 'offerrun');
		self::$files[] = $out;
		self::$files[] = $err;
		$cmd = array_merge([PHP_BINARY], $phpOptions, [__DIR__ . '/../support/run_shop_offer_push.php', CharacterizationEnv::testDb()], $args);
		$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $out, 'w'], 2 => ['file', $err, 'w']], $pipes);
		fclose($pipes[0]);
		return [$proc, $out, $err];
	}

	/** @param array{0: resource, 1: string, 2: string} $run @return array<string, mixed> */
	private static function finish(array $run): array
	{
		proc_close($run[0]);
		return self::parse((string)file_get_contents($run[1]), (string)file_get_contents($run[2]));
	}

	/** @return array{0: resource, 1: string, 2: string} A runner holding a lock file of the state directory, once it holds it. */
	private static function hold(string $lockFile, float $seconds): array
	{
		$run = self::start(['hold', (string)$seconds, $lockFile]);
		for ($i = 0; $i < 200 && !is_file(self::$tempDir . '/shopoffer/.held-' . $lockFile); $i++) {
			usleep(50000);
		}
		self::assertFileExists(self::$tempDir . '/shopoffer/.held-' . $lockFile, 'the lock is held');
		return $run;
	}

	/** Waits until the fake shop has received a call for the item carrying $salesPrice (logged on arrival). */
	private static function waitForCall(string $itemNo, string $salesPrice): void
	{
		for ($i = 0; $i < 200; $i++) {
			foreach (self::priceCalls($itemNo, null) as $call) {
				if ($call['salesPrice'] === $salesPrice) return;
			}
			usleep(25000);
		}
		self::fail("no call for $itemNo with $salesPrice arrived");
	}

	private static function control(string $name, string $content = ''): void
	{
		file_put_contents(self::$shopLog . '.' . $name, $content);
	}

	private static function uncontrol(string $name): void
	{
		@unlink(self::$shopLog . '.' . $name);
	}

	private static function clearLog(): void
	{
		file_put_contents(self::$shopLog, '');
	}

	/** @return list<array<string, mixed>> The calls that carried prices (update_price, update_stock), optionally for one item. */
	private static function priceCalls(?string $itemNo = null, ?string $kind = 'update_price'): array
	{
		$calls = [];
		foreach (array_filter(explode("\n", (string)file_get_contents(self::$shopLog))) as $line) {
			$entry = json_decode($line, true);
			$query = $entry['query'];
			if (!array_key_exists('salesPrice', $query) || ($kind !== null && !array_key_exists($kind, $query))) continue;
			if ($itemNo === null || ($query['itemNo'] ?? '') === $itemNo) {
				$calls[] = $query + ['_shop' => (string)$entry['shop'], '_status' => (string)$entry['status']];
			}
		}
		return $calls;
	}

	/** @return array<string, list<string>> 'salesPrice / retailPrice' per shop, for one item's calls of a kind (null: both). */
	private static function pricesSent(string $itemNo, ?string $kind = 'update_price'): array
	{
		$sent = [];
		foreach (self::priceCalls($itemNo, $kind) as $call) {
			$sent['shop ' . $call['_shop']][] = ($call['salesPrice'] ?? '') . ' / ' . ($call['retailPrice'] ?? '');
		}
		ksort($sent);
		return $sent;
	}

	/** @return array<string, string> The price each shop holds for the item now: 'salesPrice / retailPrice'. */
	private static function remote(string $itemNo): array
	{
		$out = [];
		foreach ((array)json_decode((string)@file_get_contents(self::$shopLog . '.remote'), true) as $key => $price) {
			[$shop, $item] = explode('|', (string)$key, 2);
			if ($item === $itemNo) {
				$out['shop ' . $shop] = $price['sales'] . ' / ' . $price['retail'];
			}
		}
		ksort($out);
		return $out;
	}

	/** @return array<string, array<string, mixed>> The item's state per shop, keyed 'shop N' like remote(). */
	private static function stateByShop(int $id): array
	{
		$state = self::runner(['state', (string)$id])['state'];
		$out = [];
		$endpoints = self::one("SELECT box4, box5, box6 FROM grupper WHERE art = 'API'");
		foreach (['box4' => 'shop 1', 'box5' => 'shop 2', 'box6' => 'shop 3'] as $column => $label) {
			$key = substr(sha1(trim((string)$endpoints[$column])), 0, 16);
			if (is_array($state) && isset($state['shops'][$key])) $out[$label] = $state['shops'][$key];
		}
		return $out;
	}

	/** No shop is certified at a price it does not hold: the invariant a false certification would break. */
	private static function assertNoFalseCertainty(int $id, string $itemNo): void
	{
		$remote = self::remote($itemNo);
		foreach (self::stateByShop($id) as $shop => $entry) {
			if (!empty($entry['certain'])) {
				self::assertSame($entry['confirmed']['sales'] . ' / ' . $entry['confirmed']['retail'], $remote[$shop] ?? '(nothing)',
					"$shop is certified at a price it does not hold");
			}
		}
	}

	/** The seeded session id for the page runner (master harness: sessionId(), live harness: SESSION_ID). */
	private static function sessionId(): string
	{
		return method_exists(CharacterizationEnv::class, 'sessionId')
			? CharacterizationEnv::sessionId()
			: (string)constant(CharacterizationEnv::class . '::SESSION_ID');
	}

	private const OFFER = '159.200 / 799.00';
	private const NORMAL = '639.200 / 899.000';
	private const OFFER_ALL = ['shop 1' => ['159.200 / 799.00'], 'shop 2' => ['159.200 / 799.00'], 'shop 3' => ['159.200 / 799.00']];
	private const NORMAL_ALL = ['shop 1' => ['639.200 / 899.000'], 'shop 2' => ['639.200 / 899.000'], 'shop 3' => ['639.200 / 899.000']];
	private const REMOTE_OFFER = ['shop 1' => self::OFFER, 'shop 2' => self::OFFER, 'shop 3' => self::OFFER];
	private const REMOTE_NORMAL = ['shop 1' => self::NORMAL, 'shop 2' => self::NORMAL, 'shop 3' => self::NORMAL];

	// ------------------------------------------------------------------ only a tick sends offer prices

	public function testOnlyAnAuthorisedTickSendsOfferPrices(): void
	{
		$id = self::item('SPONLY', self::offer(159.2, 3));
		self::tick(); // the state directory exists from here on, as on an account whose scheduler runs
		self::clearLog();
		$csv = (string)tempnam(sys_get_temp_dir(), 'offercsv');
		self::$files[] = $csv;
		file_put_contents($csv, "SPONLY;249,00;" . date('d-m-Y') . ";" . date('d-m-Y', strtotime('+3 days')) . "\r\n");
		chmod($csv, 0644);
		CharacterizationEnv::resyncSequences(self::$tenant);
		$res = self::runChild('run_importer_varer_page.php', [$csv, self::sessionId(), 'varenr,special_price_incl_moms,special_from_date,special_to_date', 'all_products', 'UTF-8']);
		self::assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout']);
		self::assertSame('199.200', self::one('SELECT special_price FROM varer WHERE id = $1', [$id])['special_price'], 'the import wrote the new offer');
		self::assertSame([], self::priceCalls(), 'the import itself sends nothing');
		self::runner(['sync_price', (string)$id]);
		self::runner(['sync_vare', (string)$id, '1', 'all']);
		self::runner(['sale', (string)$id, '1']);
		self::assertNotSame([], self::priceCalls(null, null));
		foreach (self::priceCalls(null, null) as $call) {
			self::assertNotSame('199.200', $call['salesPrice'], 'no card save or sale sends the new offer');
		}
		$denied = self::runner(['rest', http_build_query(['action' => 'shop_offer_tick', 'db' => CharacterizationEnv::testDb(), 'saldiuser' => 'tick', 'key' => 'wrong'])]);
		self::assertIsString($denied['response'], 'a tick without access is refused by access_check(): ' . json_encode($denied));
		self::assertSame([], array_filter(self::priceCalls(), fn($c) => $c['salesPrice'] === '199.200'), 'and sends nothing');
		self::tick();
		self::assertSame(['shop 1' => '199.200 / 799.00', 'shop 2' => '199.200 / 799.00', 'shop 3' => '199.200 / 799.00'], self::remote('SPONLY'),
			'the tick sends the new offer');
	}

	public function testATickSendsTheOfferThatAppliesNowAndRestoresItAfterwards(): void
	{
		$id = self::item('SPNOW', self::offer());
		self::item('SPLATER', ['price' => 149.2, 'from' => date('Y-m-d', strtotime('+10 days')), 'to' => date('Y-m-d', strtotime('+12 days'))]);
		$out = self::tick();
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPNOW'), 'one call per shop: the offer ex VAT, the normal price 639.20 incl. VAT as the before-price');
		self::assertSame([], self::pricesSent('SPLATER'), 'an offer that starts later is not sent yet');
		self::assertSame(3, $out['certified']);
		self::clearLog();
		self::tick();
		self::assertSame([], self::priceCalls(), 'nothing certified is sent again within the hour');
		$out = self::tick(strtotime('tomorrow') + 1);
		self::assertSame(self::NORMAL_ALL, self::pricesSent('SPNOW'), 'the tick after the end restores the normal prices');
		self::assertSame(self::REMOTE_NORMAL, self::remote('SPNOW'));
		self::assertSame(1, $out['untracked']);
		self::assertNull(self::runner(['state', (string)$id])['state'], 'no longer tracked once every shop confirmed the normal prices');
	}

	public function testTheStartOfALaterOfferIsSentByTheFirstTickAfterIt(): void
	{
		$start = strtotime('tomorrow');
		self::item('SPSTART', ['price' => 159.2, 'from' => date('Y-m-d', $start), 'to' => date('Y-m-d', $start + 2 * 86400)]);
		self::tick($start - 30);
		self::assertSame([], self::priceCalls(), 'not before its start');
		self::tick($start + 30);
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPSTART'), 'the tick 30 seconds after midnight sends it');
	}

	public function testEveryTrackedItemIsSentAgainAfterAnHour(): void
	{
		self::item('SPHEAL', self::offer(159.2, 3));
		$t = time();
		self::tick($t);
		file_put_contents(self::$shopLog . '.remote', '{}'); // the shops lost the price behind our back
		self::clearLog();
		self::tick($t + 1800);
		self::assertSame([], self::priceCalls(), 'within the hour a certified price is trusted');
		$out = self::tick($t + 3601);
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPHEAL'), 'after an hour it is sent again');
		self::assertSame(1, $out['healed']);
		self::assertSame(self::REMOTE_OFFER, self::remote('SPHEAL'), 'drift healed');
	}

	// ------------------------------------------------------------------ the other paths send what each shop confirmed (v1 1, v2 4)

	public function testTheCardAndTheSaleSendEachShopOnlyThePairItConfirmed(): void
	{
		$id = self::item('SPCARD', self::offer(159.2, 3));
		self::tick(); // so the state directory exists
		self::clearState(); // and the item is not tracked yet
		@mkdir(self::$tempDir . '/shopoffer', 0700, true);
		self::clearLog();
		self::runner(['sync_price', (string)$id]);
		self::assertSame(self::NORMAL_ALL, self::pricesSent('SPCARD'), 'before the reconciler sent the offer, a card save sends today\'s prices');
		self::tick();
		self::assertSame(self::REMOTE_OFFER, self::remote('SPCARD'));
		self::rows("UPDATE varer SET special_price = 99.2 WHERE id = $1", [$id]); // the card or an import changes the offer
		foreach ([['sync_price', (string)$id], ['sync_vare', (string)$id, '1', 'all'], ['sync_vare', (string)$id, '1', 'stock'], ['sale', (string)$id, '1']] as $call) {
			self::clearLog();
			self::runner($call);
			self::assertSame(self::REMOTE_OFFER, self::remote('SPCARD'), implode(' ', $call) . ' changed no shop\'s price');
			self::assertNotSame([], self::priceCalls('SPCARD', null), implode(' ', $call) . ' did send');
			foreach (self::priceCalls('SPCARD', null) as $sent) {
				self::assertSame(self::OFFER, $sent['salesPrice'] . ' / ' . $sent['retailPrice'], implode(' ', $call) . ' sent the confirmed pair');
			}
		}
		self::tick();
		self::assertSame(['shop 1' => '99.200 / 799.00', 'shop 2' => '99.200 / 799.00', 'shop 3' => '99.200 / 799.00'], self::remote('SPCARD'),
			'the next tick sends the new offer');
		self::tick(strtotime('+4 days'));
		self::assertSame(self::REMOTE_NORMAL, self::remote('SPCARD'), 'and its end restores the normal prices');
	}

	public function testAStockSyncCarriesTheConfirmedPairNotTheNewOne(): void
	{
		$id = self::item('SPSTOCK', self::offer(159.2, 3));
		self::tick();
		self::rows("UPDATE varer SET special_price = 59.2 WHERE id = $1", [$id]);
		self::runner(['sync_vare', (string)$id, '1', 'stock']);
		self::assertSame(self::REMOTE_OFFER, self::remote('SPSTOCK'), 'the stock call carried A, not B');
		self::rows("UPDATE varer SET special_price = 159.2 WHERE id = $1", [$id]); // back to A before any tick
		self::clearLog();
		self::tick();
		self::assertSame([], self::priceCalls(), 'every shop still certainly has A');
		self::assertSame(self::REMOTE_OFFER, self::remote('SPSTOCK'));
		self::assertNoFalseCertainty($id, 'SPSTOCK');
	}

	public function testAnItemWithoutAnOfferCostsNothing(): void
	{
		$id = self::item('SPPLAIN');
		self::tick();
		self::runner(['sync_price', (string)$id]);
		self::runner(['sync_vare', (string)$id, '1', 'all']);
		self::runner(['sale', (string)$id, '1']);
		self::assertNotSame([], self::priceCalls('SPPLAIN', null));
		foreach (self::priceCalls('SPPLAIN', null) as $sent) {
			self::assertSame(self::NORMAL, $sent['salesPrice'] . ' / ' . $sent['retailPrice'], 'today\'s prices');
		}
		self::assertFileDoesNotExist(self::$tempDir . "/shopoffer/item-$id.json", 'no state for an item without an offer');
		self::assertSame([], glob(self::$tempDir . '/shopoffer/uncertain-*'));
		self::clearLog();
		$out = self::tick();
		self::assertSame([], self::priceCalls(), 'and the tick sends it nothing');
		self::assertSame(0, $out['checked']);
	}

	// ------------------------------------------------------------------ certainty under concurrent sends (v2 1)

	public function testACardSaveAStockSyncAndTwoTicksAtOnceEndWithTheDesiredPrice(): void
	{
		$id = self::item('SPCONC', self::offer(159.2, 3));
		self::tick();
		self::assertSame(self::REMOTE_OFFER, self::remote('SPCONC'));
		self::rows("UPDATE varer SET special_price = 99.2 WHERE id = $1", [$id]); // desired is now B
		// The shops apply the old pair A late and answer the reconciler's B late, so A, sent by the card save and the stock sync
		// while the tick waits for its answers, is applied after B: the order Codex's P1 race needs.
		self::control('rules', json_encode([
			['item' => 'SPCONC', 'sales' => '159.200', 'apply' => 1.5],
			['item' => 'SPCONC', 'sales' => '99.200', 'answer' => 2.5],
		]));
		self::clearLog();
		$tick1 = self::start(['tick', 'now', '10']);
		self::waitForCall('SPCONC', '99.200'); // the tick has sent B and waits 2.5 s for the answers
		$card = self::start(['sync_price', (string)$id]);
		$stock = self::start(['sync_vare', (string)$id, '1', 'stock']);
		$tick2 = self::start(['tick', 'now', '10']);
		$results = array_map([self::class, 'finish'], [$tick1, $card, $stock, $tick2]);
		self::assertSame('busy', $results[3]['result'], 'the second tick found the first running');
		self::assertGreaterThan(0, $results[0]['overlapped'] + $results[0]['deferred'], 'the first tick saw the other sends: ' . json_encode($results[0]));
		self::assertSame(['shop 1' => self::OFFER, 'shop 2' => self::OFFER, 'shop 3' => self::OFFER], self::remote('SPCONC'),
			'the late A landed after B in every shop');
		self::assertNoFalseCertainty($id, 'SPCONC');
		self::uncontrol('rules');
		self::tick();
		$b = '99.200 / 799.00';
		self::assertSame(['shop 1' => $b, 'shop 2' => $b, 'shop 3' => $b], self::remote('SPCONC'), 'once a tick has run, every shop has the desired price');
		foreach (self::stateByShop($id) as $shop => $entry) {
			self::assertTrue($entry['certain'], "$shop certain");
			self::assertSame('99.200', $entry['confirmed']['sales']);
		}
	}

	public function testATickWaitsForASendAnotherPathHasUnderWay(): void
	{
		$id = self::item('SPWAIT', self::offer(159.2, 3));
		self::tick();
		self::rows("UPDATE varer SET special_price = 59.2 WHERE id = $1", [$id]);
		// Codex's P1 order: the card save's call is applied at once but answered late; a tick starting meanwhile must not send B
		// and certify it while the card save's call is still open.
		self::control('rules', json_encode([['item' => 'SPWAIT', 'sales' => '159.200', 'answer' => 2.0]]));
		self::clearLog();
		$card = self::start(['sync_price', (string)$id]);
		self::waitForCall('SPWAIT', '159.200'); // the card save's call is open: applied, its answer 2 s away
		$out = self::tick();
		self::assertSame(1, $out['deferred'], 'the item is left for the next tick while another send of it is under way');
		self::finish($card);
		self::uncontrol('rules');
		self::assertNoFalseCertainty($id, 'SPWAIT');
		self::tick();
		self::assertSame(['shop 1' => '59.200 / 799.00', 'shop 2' => '59.200 / 799.00', 'shop 3' => '59.200 / 799.00'], self::remote('SPWAIT'));
		self::assertNoFalseCertainty($id, 'SPWAIT');
	}

	// ------------------------------------------------------------------ per-shop state (v1 2, v1 5)

	public function testAPartialDeliveryIsCorrectedShopByShop(): void
	{
		$id = self::item('SPPART', self::offer(79.2, 3));
		$t = time();
		self::tick($t);
		self::rows("UPDATE varer SET special_price = 59.2 WHERE id = $1", [$id]);
		self::control('fail3');
		$out = self::tick($t + 1);
		self::assertSame([2, 1], [$out['certified'], $out['failed']], 'B: shops 1 and 2 took it, shop 3 answered 503');
		self::uncontrol('fail3');
		self::rows("UPDATE varer SET special_price = 79.2 WHERE id = $1", [$id]); // back to A
		self::clearLog();
		self::tick($t + 120);
		self::assertSame(['shop 1', 'shop 2', 'shop 3'], array_keys(self::pricesSent('SPPART')),
			'shops 1 and 2 confirmed B; shop 3 is uncertain after its 503, so it gets A too');
		$a = '79.200 / 799.00';
		self::assertSame(['shop 1' => $a, 'shop 2' => $a, 'shop 3' => $a], self::remote('SPPART'));
		self::assertNoFalseCertainty($id, 'SPPART');
	}

	public function testAFirstFailureKeepsItsBackOff(): void
	{
		$id = self::item('SPFIRST', self::offer(159.2, 3));
		$t = time();
		self::control('fail1');
		self::tick($t);
		self::uncontrol('fail1');
		$entry = self::stateByShop($id)['shop 1'];
		self::assertSame([null, false, 1, $t + 60], [$entry['confirmed'], $entry['certain'], $entry['attempts'], $entry['retryAt']]);
		self::clearLog();
		self::tick($t + 30);
		self::assertSame([], self::priceCalls(), 'not before the back-off');
		self::tick($t + 61);
		self::assertSame(['shop 1' => [self::OFFER]], self::pricesSent('SPFIRST'), 'then only shop 1');
	}

	// ------------------------------------------------------------------ refusals (v1 6, v2 3)

	public function testARefusedRestorationIsRetriedUntilTheShopConfirms(): void
	{
		$id = self::item('SPREFUSE', self::offer());
		self::tick();
		$end = strtotime('tomorrow') + 1;
		self::control('reject3');
		$out = self::tick($end);
		self::assertSame([2, 1], [$out['certified'], $out['refused']], 'a refusal is not delivered');
		self::uncontrol('reject3');
		self::clearLog();
		self::tick($end + 61);
		self::assertSame(['shop 3' => [self::NORMAL]], self::pricesSent('SPREFUSE'), 'shop 3 is sent again');
		self::assertSame(self::REMOTE_NORMAL, self::remote('SPREFUSE'));
		self::assertNull(self::runner(['state', (string)$id])['state']);
	}

	public function testRefusalCountersBelongToThePricePair(): void
	{
		$id = self::item('SPPAIR', self::offer(159.2, 3));
		$t = time();
		self::tick($t);
		self::rows("UPDATE varer SET special_price = 129.2 WHERE id = $1", [$id]); // A
		self::control('reject2');
		$gaveUp = 0;
		for ($i = 0, $at = $t + 1; $i < 8; $i++, $at += 1801) {
			$gaveUp += self::tick($at)['gaveUp'];
		}
		self::assertSame(1, $gaveUp, 'shop 2 is given up for A after eight refusals of A');
		self::assertSame(8, self::stateByShop($id)['shop 2']['refusals']);
		self::assertStringContainsString('SHOP OFFER GAVE UP', (string)@file_get_contents(self::$tempDir . '/rest_api.log'));
		self::clearLog();
		self::tick($at);
		self::assertArrayNotHasKey('shop 2', self::pricesSent('SPPAIR'), 'A is not sent to shop 2 again');
		self::rows("UPDATE varer SET special_price = 109.2 WHERE id = $1", [$id]); // B
		$out = self::tick($at + 1);
		self::assertSame(0, $out['gaveUp'], 'one refusal of B does not give up B: its counters start again');
		$entry = self::stateByShop($id)['shop 2'];
		self::assertSame([1, false, $at + 1 + 60], [$entry['refusals'], $entry['gaveUp'], $entry['retryAt']], 'B has its own back-off, not A\'s');
		self::uncontrol('reject2');
		self::tick($at + 62);
		self::assertSame('109.200 / 799.00', self::remote('SPPAIR')['shop 2'], 'shop 2 takes B once it accepts again');
		self::assertTrue(self::stateByShop($id)['shop 2']['certain']);
	}

	// ------------------------------------------------------------------ state that cannot be written (v2 5)

	public function testStateThatCannotBeWrittenSendsNoOffer(): void
	{
		$id = self::item('SPDISK', self::offer(159.2, 3));
		self::tick(); // creates the state directory; the item is certified
		self::rows("UPDATE varer SET specialtype = NULL, special_price = 0 WHERE id = $1", [$id]);
		self::tick(); // restored and no longer tracked
		self::rows("UPDATE varer SET specialtype = 'price', special_price = 159.2 WHERE id = $1", [$id]);
		mkdir(self::$tempDir . "/shopoffer/item-$id.json"); // the item's state file cannot be written (a directory is in the way)
		try {
			self::clearLog();
			$out = self::tick();
			self::assertSame('state not writable', $out['result']);
			self::assertSame([], self::priceCalls(), 'the reconciler sends nothing it cannot record');
			self::runner(['sync_price', (string)$id]);
			self::assertSame(self::NORMAL_ALL, self::pricesSent('SPDISK'), 'a card save falls back to today\'s prices: no offer without a record');
			self::assertFileExists(self::$tempDir . "/shopoffer/uncertain-$id", 'and leaves a note for the reconciler');
			self::assertStringContainsString('STATE NOT WRITABLE', (string)@file_get_contents(self::$tempDir . '/rest_api.log'));
		} finally {
			rmdir(self::$tempDir . "/shopoffer/item-$id.json");
		}
		self::clearLog();
		self::assertSame(1, self::tick()['deferred'], 'the note holds the item while that send may still arrive');
		self::tick(time() + 121);
		self::assertSame(self::REMOTE_OFFER, self::remote('SPDISK'), 'then every shop is sent the offer');

		// no state directory at all: the tick sends nothing; the other paths keep today's prices
		self::clearState();
		file_put_contents(self::$tempDir . '/shopoffer', 'not a directory');
		self::clearLog();
		self::assertSame('state not writable', self::tick()['result']);
		self::runner(['sync_price', (string)$id]);
		self::assertSame(self::NORMAL_ALL, self::pricesSent('SPDISK'));
	}

	// ------------------------------------------------------------------ locks and the budget (v1 3, v1 4, v2 2, v2 6)

	public function testOneTickAtATimeAndABusyOneAnswersAtOnce(): void
	{
		self::item('SPLOCK', self::offer());
		self::tick();
		$hold = self::hold('tick.lock', 3);
		$out = self::tick();
		self::finish($hold);
		self::assertSame('busy', $out['result']);
		self::assertLessThan(0.5, $out['elapsed'], 'at once, not after the other tick');
	}

	public function testASaleDoesNotWaitForAHeldItemLock(): void
	{
		$id = self::item('SPHELD', self::offer(159.2, 3));
		self::tick();
		$hold = self::hold('lock-' . ($id % 64), 3);
		$out = self::runner(['sale', (string)$id, '1']);
		self::finish($hold);
		self::assertLessThan(1.5, $out['elapsed'], 'the sale waited at most 0.2 seconds for the lock');
		self::assertSame(['shop 1' => [self::NORMAL], 'shop 2' => [self::NORMAL], 'shop 3' => [self::NORMAL]], self::pricesSent('SPHELD', 'update_stock'),
			'it could not note its send, so it sent today\'s prices');
		self::assertFileExists(self::$tempDir . "/shopoffer/uncertain-$id");
		self::clearLog();
		self::tick(time() + 121);
		self::assertSame(self::REMOTE_OFFER, self::remote('SPHELD'), 'the next tick after the note sends every shop the offer again');
		self::assertNoFalseCertainty($id, 'SPHELD');
	}

	public function testATickKeepsItsBudgetWithThreeSlowShopsOneAfterTheOther(): void
	{
		self::item('SPSLOW', self::offer());
		self::control('slow', '3');
		$out = self::runner(['tick', 'now', '1.5'], ['-d', 'disable_functions=curl_multi_init']);
		self::uncontrol('slow');
		self::assertLessThan(1.9, $out['elapsed'], 'three shops answering after 3 s, one after the other: the tick stops at its budget');
		$out = self::tick(time() + 3600);
		self::assertSame(3, $out['certified'], 'a later tick delivers');
	}

	public function testTheNextTickGoesOnWhereTheLastStopped(): void
	{
		for ($i = 1; $i <= 12; $i++) {
			self::item(sprintf('SPMANY%02d', $i), self::offer());
		}
		$first = self::tick(null, 10, 5);
		$items1 = array_values(array_unique(array_map(fn($c) => $c['itemNo'], self::priceCalls())));
		self::clearLog();
		self::tick(null, 10, 5);
		$items2 = array_values(array_unique(array_map(fn($c) => $c['itemNo'], self::priceCalls())));
		self::assertSame(5, count($items1));
		self::assertSame(7, $first['left']);
		self::assertSame([], array_intersect($items1, $items2), 'the second tick does not start over');
		self::assertSame(5, count($items2));
		self::clearLog();
		self::tick(null, 10, 5);
		self::assertSame(2, count(array_unique(array_map(fn($c) => $c['itemNo'], self::priceCalls()))), 'the third tick does the last two');
	}

	public function testAnAccountWithoutAWebshopHasNoState(): void
	{
		$endpoints = self::one("SELECT box4, box5, box6 FROM grupper WHERE art = 'API'");
		self::rows("UPDATE grupper SET box4 = '', box5 = '', box6 = '' WHERE art = 'API'");
		try {
			$id = self::item('SPNOAPI', self::offer());
			self::assertSame('no api', self::tick()['result']);
			self::runner(['sync_price', (string)$id]);
			self::runner(['sale', (string)$id, '1']);
			self::assertDirectoryDoesNotExist(self::$tempDir . '/shopoffer');
			self::assertSame([], self::priceCalls(null, null));
		} finally {
			self::rows("UPDATE grupper SET box4 = $1, box5 = $2, box6 = $3 WHERE art = 'API'", [$endpoints['box4'], $endpoints['box5'], $endpoints['box6']]);
		}
	}
}
