<?php

declare(strict_types=1);

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

/**
 * The webshop offer reconciler (includes/stdFunc/shopOfferPush.php) against a fresh tenant and a fake shop: a loopback-only
 * `php -S` server (support/shop_price_recorder.php) configured as all three webshop endpoints. It records every call, keeps each
 * shop's own price per item as a real shop would (applied when the call is applied, which can be later than it arrived), and
 * can fail, refuse, apply late or answer late per shop.
 *
 * Pins the guarantee: when every request is applied within T_late (10 minutes) of being sent, each shop holds the item's
 * current pair within T_late plus the next re-send after the item's last event, and no item stops being tracked while a request
 * of the reconciler could still arrive. So: only a tick sends offer prices; it sends again after every event (a change, a
 * failure, a refusal, a cut-off or killed send, an earlier pair sent by another path) on schedule; requests that arrive late
 * (Codex v3: V3LATE, V3LOST, V3CRONLOST, V3PLAIN, a killed tick) are overwritten by the next re-send; an item stays tracked 24
 * hours after its last event; refusal counters belong to one pair; unwritable state sends nothing; one tick at a time within its
 * budget, a held table lock included; an item without an offer costs nothing.
 *
 * History:
 * 20261117 CL/LH Created (Havemøbelland Black Week: offers in the webshops).
 * 20261117 CL/LH Codex verification: one regression test per finding (unrecorded card/sale offers, partial delivery, the budget with
 *                slow shops, the schedule race, the retry state after a first failure, a refused restoration).
 * 20261117 CL/LH An install without a webshop keeps no push state after a campaign import, and a look there makes the push dormant.
 * 20261117 CL/LH Single-writer rebuild after the second Codex verification: the concurrency of a card save, a stock sync and two
 *                ticks against a shop that applies late, the tick lock, and no offer price without an authorised tick.
 * 20261117 CL/LH Re-send rebuild after the third: requests that arrive late, a killed tick, a held table lock.
 */
final class ShopOfferPushTest extends CharacterizationTestCase
{
	/** @var list<resource> One fake shop server per shop, so calls to different shops never queue behind each other. */
	private static array $servers = [];
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
		$env = array_merge(getenv(), ['SALDI_SHOP_RECORDER_LOG' => self::$shopLog, 'PHP_CLI_SERVER_WORKERS' => '6']);
		$ports = [];
		foreach ([1, 2, 3] as $n) {
			$probe = stream_socket_server('tcp://127.0.0.1:0');
			$name = (string)stream_socket_get_name($probe, false);
			$ports[$n] = $port = (int)substr($name, strrpos($name, ':') + 1);
			fclose($probe);
			self::$servers[] = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/../support/shop_price_recorder.php'],
				[0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env);
			for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1); $i++) {
				usleep(100000);
			}
		}
		$db = CharacterizationEnv::testDb();
		self::$tempDir = CharacterizationEnv::repoRoot() . '/temp/' . $db;
		@mkdir(self::$tempDir, 0777, true);
		if (self::one("SELECT id FROM grupper WHERE art = 'API'") === null) {
			self::rows("INSERT INTO grupper (id, beskrivelse, kodenr, art, fiscal_year) VALUES ($1, 'API', '1', 'API', 0)", [CharacterizationEnv::nextId(self::$tenant, 'grupper')]);
		}
		self::rows("UPDATE grupper SET box1 = 'test-key', box2 = '*', box4 = $1, box5 = $2, box6 = $3 WHERE art = 'API'",
			["http://127.0.0.1:{$ports[1]}/shop1.php?shop=1", "http://127.0.0.1:{$ports[2]}/shop2.php?shop=2", "http://127.0.0.1:{$ports[3]}/shop3.php?shop=3"]);
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
		foreach (self::$servers as $server) {
			proc_terminate($server);
			proc_close($server);
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

	/** Waits until the fake shop has received $count calls for the item carrying $salesPrice (logged on arrival). */
	private static function waitForCall(string $itemNo, string $salesPrice, int $count = 1): void
	{
		for ($i = 0; $i < 600; $i++) {
			if (count(array_filter(self::priceCalls($itemNo, null), fn($c) => $c['salesPrice'] === $salesPrice)) >= $count) return;
			usleep(25000);
		}
		self::fail("no $count calls for $itemNo with $salesPrice arrived: " . json_encode(array_map(fn($c) => [$c['_shop'], $c['salesPrice'], $c['_status']], self::priceCalls($itemNo, null))));
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

	/** Every fake shop holds $pair for the item. */
	private static function assertShopsHold(string $itemNo, string $pair, string $message = ''): void
	{
		self::assertSame(['shop 1' => $pair, 'shop 2' => $pair, 'shop 3' => $pair], self::remote($itemNo), $message);
	}

	/** Waits until every shop holds $pair for the item (a request that is applied late), at most 20 seconds. */
	private static function waitUntilShopsHold(string $itemNo, string $pair): void
	{
		for ($i = 0; $i < 400 && count(array_filter(self::remote($itemNo), fn($p) => $p === $pair)) < 3; $i++) {
			usleep(50000);
		}
		self::assertShopsHold($itemNo, $pair, 'the late request was applied');
	}

	/** Calls per shop that carried prices for the item since the log was cleared. */
	private static function sendsPerShop(string $itemNo): int
	{
		return intdiv(count(self::priceCalls($itemNo)), 3);
	}

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
		self::assertShopsHold('SPONLY', '199.200 / 799.00', 'the tick sends the new offer');
	}

	public function testATickSendsTheOfferAndSendsItAgainOnSchedule(): void
	{
		self::item('SPNOW', self::offer(159.2, 3));
		$t = time();
		$sends = [];
		foreach ([0, 60, 120, 200, 300, 900, 3600, 3600 + 10799, 3600 + 10800] as $offset) {
			self::clearLog();
			self::tick($t + $offset);
			$sends[$offset] = self::sendsPerShop('SPNOW');
		}
		self::assertSame([0 => 1, 60 => 0, 120 => 1, 200 => 0, 300 => 1, 900 => 1, 3600 => 1, 14399 => 0, 14400 => 1], $sends,
			'at once, after 2, 5, 15 and 60 minutes, then every 3 hours while the offer is active');
		self::assertShopsHold('SPNOW', self::OFFER);
	}

	public function testTheEndIsSentAgainAndTheItemKeptFor24Hours(): void
	{
		$id = self::item('SPEND', self::offer());
		self::tick();
		$end = strtotime('tomorrow') + 1;
		$sends = [];
		foreach ([0, 120, 300, 900, 3600, 21600, 43200, 64800, 86400] as $offset) {
			self::clearLog();
			self::tick($end + $offset);
			$sends[] = self::sendsPerShop('SPEND');
		}
		self::assertSame([1, 1, 1, 1, 1, 1, 1, 1, 1], $sends, 'the normal prices at the end, after 2, 5, 15, 60 minutes and 6, 12, 18, 24 hours');
		self::assertShopsHold('SPEND', self::NORMAL);
		self::tick($end + 86400 + 599);
		self::assertNotNull(self::runner(['state', (string)$id])['state'], 'not untracked while the last re-send could still be on its way');
		$out = self::tick($end + 86400 + 600);
		self::assertSame(1, $out['untracked']);
		self::assertNull(self::runner(['state', (string)$id])['state']);
		self::clearLog();
		self::tick($end + 2 * 86400);
		self::assertSame([], self::priceCalls(), 'and then nothing');
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

	// ------------------------------------------------------------------ the other paths start no price of their own

	public function testTheCardAndTheSaleSendEachShopOnlyThePairItLastAccepted(): void
	{
		$id = self::item('SPCARD', self::offer(159.2, 3));
		self::tick(); // so the state directory exists
		self::clearState(); // and the item is not tracked
		@mkdir(self::$tempDir . '/shopoffer', 0700, true);
		self::clearLog();
		self::runner(['sync_price', (string)$id]);
		self::assertSame(self::NORMAL_ALL, self::pricesSent('SPCARD'), 'an item the reconciler does not track: today\'s prices');
		$t = time();
		self::tick($t);
		self::assertSame(self::REMOTE_OFFER, self::remote('SPCARD'));
		self::rows("UPDATE varer SET special_price = 99.2 WHERE id = $1", [$id]); // the card or an import changes the offer
		foreach ([['sync_price', (string)$id], ['sync_vare', (string)$id, '1', 'all'], ['sync_vare', (string)$id, '1', 'stock'], ['sale', (string)$id, '1']] as $call) {
			self::clearLog();
			self::runner($call);
			self::assertShopsHold('SPCARD', self::OFFER, implode(' ', $call) . ' started no price of its own');
			self::assertNotSame([], self::priceCalls('SPCARD', null), implode(' ', $call) . ' did send');
			foreach (self::priceCalls('SPCARD', null) as $sent) {
				self::assertSame(self::OFFER, $sent['salesPrice'] . ' / ' . $sent['retailPrice'], implode(' ', $call) . ' sent the pair last accepted');
			}
		}
		self::assertGreaterThanOrEqual($t, self::runner(['state', (string)$id])['state']['foreign']['staleAt'] ?? 0,
			'and marked that it was not the current pair');
		self::tick($t + 1);
		self::assertShopsHold('SPCARD', '99.200 / 799.00', 'the next tick sends the new offer');
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
		self::assertSame([0, 0], [$out['tracked'], $out['due']]);
	}

	// ------------------------------------------------------------------ requests that arrive late (Codex v3)

	public function testARequestThatArrivesLateIsOverwrittenByTheNextResend(): void
	{
		$id = self::item('SPLATE', self::offer(79.2, 3));
		self::control('rules', json_encode([['item' => 'SPLATE', 'sales' => '79.200', 'apply' => 6]]));
		$t = time();
		self::assertSame(3, self::tick($t, 1.5)['failed'], 'A gets no answer within the budget');
		self::rows("UPDATE varer SET special_price = 59.2 WHERE id = $1", [$id]); // B
		self::tick($t + 1);
		self::waitUntilShopsHold('SPLATE', '79.200 / 799.00'); // A arrives after B (V3LATE)
		self::tick($t + 61);
		self::assertShopsHold('SPLATE', '79.200 / 799.00', 'until the next re-send the shops may hold the late pair');
		self::tick($t + 121);
		self::assertShopsHold('SPLATE', '59.200 / 799.00', 'the re-send 2 minutes after the last event overwrites it');
	}

	public function testACancelledOfferThatArrivesLateIsRestored(): void
	{
		$id = self::item('SPLOST', self::offer(79.2, 3));
		self::control('rules', json_encode([['item' => 'SPLOST', 'sales' => '79.200', 'apply' => 6]]));
		$t = time();
		self::tick($t, 1.5);
		self::rows("UPDATE varer SET specialtype = NULL, special_price = 0 WHERE id = $1", [$id]); // the offer is cancelled
		self::tick($t + 1);
		self::assertNotNull(self::runner(['state', (string)$id])['state'], 'still tracked after the normal prices went out');
		self::waitUntilShopsHold('SPLOST', '79.200 / 799.00'); // the offer arrives late (V3LOST)
		self::tick($t + 121);
		self::assertShopsHold('SPLOST', self::NORMAL, 'the re-send restores the normal prices');
	}

	public function testTicksTwoMinutesApartRestoreAnOfferThatArrivesLate(): void
	{
		$id = self::item('SPCRON', self::offer(79.2, 3));
		self::control('rules', json_encode([['item' => 'SPCRON', 'sales' => '79.200', 'apply' => 6]]));
		$t = time();
		self::tick($t, 1.5); // the scheduler's first tick: the offer's calls are cut off
		self::rows("UPDATE varer SET specialtype = NULL, special_price = 0 WHERE id = $1", [$id]);
		self::tick($t + 120); // the next tick, 2 minutes later (V3CRONLOST, on a test clock): the normal prices
		self::waitUntilShopsHold('SPCRON', '79.200 / 799.00');
		self::tick($t + 240); // and the one after it
		self::assertShopsHold('SPCRON', self::NORMAL);
		$late = $t + 120 + 86400 + 600;
		$out = self::tick($late);
		self::assertSame([0, 1], [$out['untracked'], $out['accepted'] > 0 ? 1 : 0], 'a day later, with the re-sends overdue: one more send first');
		self::assertSame(1, self::tick($late + 600)['untracked'], 'untracked 24 hours after the last event, once no request can still arrive');
	}

	public function testAnOrdinarySendThatArrivesAfterTheItemGotAnOfferIsOverwritten(): void
	{
		$id = self::item('SPGAIN');
		self::tick(); // the state directory exists; the item has no offer and is not tracked
		self::control('rules', json_encode([['item' => 'SPGAIN', 'sales' => '639.200', 'apply' => 6]]));
		$stock = self::start(['sync_vare', (string)$id, '1', 'stock']);
		self::waitForCall('SPGAIN', '639.200'); // an ordinary stock call with today's prices is on its way
		$offer = self::offer(79.2, 3);
		self::rows("UPDATE varer SET specialtype = 'price', special_price = 79.2, special_from_date = $2, special_to_date = $3 WHERE id = $1",
			[$id, $offer['from'], $offer['to']]);
		$t = time();
		self::tick($t); // the item gets the offer: an event
		self::finish($stock);
		self::assertShopsHold('SPGAIN', self::NORMAL, 'the ordinary call arrived after the offer (V3PLAIN)');
		self::tick($t + 121);
		self::assertShopsHold('SPGAIN', '79.200 / 799.00', 'the re-send overwrites it');
	}

	public function testATickKilledAfterItsCallsWentOutIsFollowedByResends(): void
	{
		$id = self::item('SPKILL', self::offer(79.2, 3));
		self::control('rules', json_encode([['item' => 'SPKILL', 'sales' => '79.200', 'apply' => 6, 'answer' => 20]]));
		$t = time();
		$tick = self::start(['tick', (string)$t, '25']);
		try {
			self::waitForCall('SPKILL', '79.200', 3);
		} catch (\PHPUnit\Framework\AssertionFailedError $e) {
			proc_terminate($tick[0], 9);
			proc_close($tick[0]);
			self::fail($e->getMessage() . ' | tick stdout: ' . file_get_contents($tick[1]) . ' | stderr: ' . substr((string)file_get_contents($tick[2]), 0, 600));
		}
		proc_terminate($tick[0], 9); // SIGKILL, with its calls on their way
		proc_close($tick[0]);
		self::assertNotNull(self::runner(['state', (string)$id])['state'], 'its intent is on disk');
		self::rows("UPDATE varer SET special_price = 59.2 WHERE id = $1", [$id]);
		self::tick($t + 1);
		self::waitUntilShopsHold('SPKILL', '79.200 / 799.00'); // the killed tick's calls arrive late
		self::tick($t + 121);
		self::assertShopsHold('SPKILL', '59.200 / 799.00');
	}

	public function testACardSaveAStockSyncAndTwoTicksAtOnceEndWithTheDesiredPrice(): void
	{
		$id = self::item('SPCONC', self::offer(159.2, 3));
		$t = time();
		self::tick($t);
		self::rows("UPDATE varer SET special_price = 99.2 WHERE id = $1", [$id]); // desired is now B
		// The shops apply the old pair A late and answer the reconciler's B late, so A, sent by the card save and the stock sync
		// while the tick waits for its answers, is applied after B.
		self::control('rules', json_encode([
			['item' => 'SPCONC', 'sales' => '159.200', 'apply' => 1.5],
			['item' => 'SPCONC', 'sales' => '99.200', 'answer' => 2.5],
		]));
		self::clearLog();
		$tick1 = self::start(['tick', (string)($t + 1), '10']);
		self::waitForCall('SPCONC', '99.200');
		$card = self::start(['sync_price', (string)$id]);
		$stock = self::start(['sync_vare', (string)$id, '1', 'stock']);
		$tick2 = self::start(['tick', (string)($t + 1), '10']);
		$results = array_map([self::class, 'finish'], [$tick1, $card, $stock, $tick2]);
		self::assertSame('busy', $results[3]['result'], 'the second tick found the first running');
		self::assertShopsHold('SPCONC', self::OFFER, 'the late A landed after B in every shop');
		self::uncontrol('rules');
		self::tick($t + 121);
		self::assertShopsHold('SPCONC', '99.200 / 799.00', 'the next re-send leaves every shop on the desired price');
	}

	// ------------------------------------------------------------------ failures and refusals are events

	public function testAPartialDeliveryIsCorrectedShopByShop(): void
	{
		$id = self::item('SPPART', self::offer(79.2, 3));
		$t = time();
		self::tick($t);
		self::rows("UPDATE varer SET special_price = 59.2 WHERE id = $1", [$id]);
		self::control('fail3');
		$out = self::tick($t + 1);
		self::assertSame([2, 1], [$out['accepted'], $out['failed']], 'B: shops 1 and 2 took it, shop 3 answered 503');
		self::uncontrol('fail3');
		self::rows("UPDATE varer SET special_price = 79.2 WHERE id = $1", [$id]); // back to A
		self::clearLog();
		self::tick($t + 2);
		self::assertSame(['shop 1', 'shop 2', 'shop 3'], array_keys(self::pricesSent('SPPART')), 'a changed pair goes to every shop at once');
		self::assertShopsHold('SPPART', '79.200 / 799.00');
	}

	public function testAFailedSendIsAnEventAndKeepsItsBackOff(): void
	{
		$id = self::item('SPFIRST', self::offer(159.2, 3));
		$t = time();
		self::control('fail1');
		self::tick($t);
		self::uncontrol('fail1');
		$entry = self::stateByShop($id)['shop 1'];
		self::assertSame([null, 1, $t + 60], [$entry['confirmed'], $entry['attempts'], $entry['retryAt']], 'every field kept, the empty one too');
		self::clearLog();
		self::tick($t + 61);
		self::assertSame([], self::priceCalls(), 'the failure starts the schedule again: nothing before 2 minutes');
		self::tick($t + 121);
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPFIRST'), 'then every shop again');
	}

	public function testARefusedRestorationIsSentAgainUntilTheShopAccepts(): void
	{
		$id = self::item('SPREFUSE', self::offer());
		self::tick();
		$end = strtotime('tomorrow') + 1;
		self::control('reject3');
		$out = self::tick($end);
		self::assertSame([2, 1], [$out['accepted'], $out['refused']], 'a refusal is not delivered');
		self::uncontrol('reject3');
		self::clearLog();
		self::tick($end + 121);
		self::assertSame(self::NORMAL_ALL, self::pricesSent('SPREFUSE'), 'every shop is sent the normal prices again');
		self::assertShopsHold('SPREFUSE', self::NORMAL);
		self::assertNotNull(self::runner(['state', (string)$id])['state'], 'and the item stays tracked for 24 hours');
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
		self::tick($at + 122);
		self::assertSame('109.200 / 799.00', self::remote('SPPAIR')['shop 2'], 'shop 2 takes B once it accepts again');
	}

	// ------------------------------------------------------------------ state that cannot be written

	public function testStateThatCannotBeWrittenSendsNoOffer(): void
	{
		$id = self::item('SPDISK');
		self::tick(); // creates the state directory
		$offer = self::offer(159.2, 3);
		self::rows("UPDATE varer SET specialtype = 'price', special_price = 159.2, special_from_date = $2, special_to_date = $3 WHERE id = $1",
			[$id, $offer['from'], $offer['to']]);
		mkdir(self::$tempDir . "/shopoffer/item-$id.json"); // the item's state file cannot be written (a directory is in the way)
		try {
			self::clearLog();
			$out = self::tick();
			self::assertSame('state not writable', $out['result']);
			self::assertSame([], self::priceCalls(), 'the reconciler sends nothing it cannot record');
			self::assertStringContainsString('STATE NOT WRITABLE', (string)@file_get_contents(self::$tempDir . '/rest_api.log'));
			self::runner(['sync_price', (string)$id]);
			self::assertSame(self::NORMAL_ALL, self::pricesSent('SPDISK'), 'a card save sends today\'s prices: no offer without a record');
		} finally {
			rmdir(self::$tempDir . "/shopoffer/item-$id.json");
		}
		self::clearLog();
		self::tick();
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPDISK'), 'once it can be written, the offer');

		// no state directory at all: the tick sends nothing; the other paths keep today's prices
		self::clearState();
		file_put_contents(self::$tempDir . '/shopoffer', 'not a directory');
		self::clearLog();
		self::assertSame('state not writable', self::tick()['result']);
		self::runner(['sync_price', (string)$id]);
		self::assertSame(self::NORMAL_ALL, self::pricesSent('SPDISK'));
	}

	// ------------------------------------------------------------------ locks and budgets

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
		$t = time();
		self::tick($t);
		self::rows("UPDATE varer SET special_price = 99.2 WHERE id = $1", [$id]); // the sale's pair is not the current one: it must mark that
		$hold = self::hold('lock-' . ($id % 64), 3);
		$out = self::runner(['sale', (string)$id, '1']);
		self::finish($hold);
		self::assertLessThan(1.5, $out['elapsed'], 'the sale waited at most 0.2 seconds for the lock');
		self::assertSame(['shop 1' => [self::NORMAL], 'shop 2' => [self::NORMAL], 'shop 3' => [self::NORMAL]], self::pricesSent('SPHELD', 'update_stock'),
			'it could not mark its send, so it sent today\'s prices');
		self::assertFileExists(self::$tempDir . "/shopoffer/uncertain-$id", 'and left a note, an event for the reconciler');
		self::tick($t + 1);
		self::assertShopsHold('SPHELD', '99.200 / 799.00');
	}

	public function testATickKeepsItsBudgetWithThreeSlowShopsOneAfterTheOther(): void
	{
		self::item('SPSLOW', self::offer());
		self::control('slow', '3');
		$t = time();
		$out = self::runner(['tick', (string)$t, '1.5'], ['-d', 'disable_functions=curl_multi_init']);
		self::uncontrol('slow');
		self::assertLessThan(1.9, $out['elapsed'], 'three shops answering after 3 s, one after the other: the tick stops at its budget');
		$out = self::tick($t + 121);
		self::assertSame(3, $out['accepted'], 'a later tick delivers');
	}

	public function testAHeldTableLockEndsTheTickWithinItsBudget(): void
	{
		self::item('SPDBLOCK', self::offer());
		$marker = sys_get_temp_dir() . '/shopoffer-tablelock-' . bin2hex(random_bytes(4));
		self::$files[] = $marker;
		$lock = self::start(['locktable', '8', 'varer', $marker]);
		for ($i = 0; $i < 200 && !is_file($marker); $i++) {
			usleep(50000);
		}
		self::assertFileExists($marker, 'the varer table is locked');
		$out = self::tick(null, 3);
		self::finish($lock);
		self::assertSame('database busy', $out['result']);
		self::assertLessThan(3.6, $out['elapsed'], 'within the budget, not after the lock');
		self::assertStringContainsString('SHOP OFFER DATABASE BUSY', (string)@file_get_contents(self::$tempDir . '/rest_api.log'));
		self::assertSame(3, self::tick()['accepted'], 'the next tick works');
	}

	public function testTheMostUrgentGoesFirstAndTheNextTickGoesOn(): void
	{
		for ($i = 1; $i <= 12; $i++) {
			self::item(sprintf('SPMANY%02d', $i), self::offer());
		}
		$t = time();
		$first = self::tick($t, 10, 5);
		$items1 = array_values(array_unique(array_map(fn($c) => $c['itemNo'], self::priceCalls())));
		self::clearLog();
		self::tick($t + 1, 10, 5);
		$items2 = array_values(array_unique(array_map(fn($c) => $c['itemNo'], self::priceCalls())));
		self::assertSame(5, count($items1));
		self::assertSame(7, $first['left']);
		self::assertSame([], array_intersect($items1, $items2), 'the second tick does not start over');
		self::assertSame(5, count($items2));
		self::clearLog();
		self::tick($t + 2, 10, 5);
		self::assertSame(2, count(array_unique(array_map(fn($c) => $c['itemNo'], self::priceCalls()))), 'the third tick does the last two');
	}

	public function testNewPricesGoBeforeRepeats(): void
	{
		for ($i = 1; $i <= 4; $i++) {
			$ids[$i] = self::item(sprintf('SPPRIO%02d', $i), self::offer(159.2, 3));
		}
		$t = time();
		self::tick($t);
		self::rows("UPDATE varer SET special_price = 99.2 WHERE id = $1", [$ids[4]]); // the last item gets a new price
		self::clearLog();
		$out = self::tick($t + 300, 10, 1); // every item is owed its 5-minute re-send; room for one item
		self::assertSame(4, $out['due']);
		self::assertSame(['SPPRIO04'], array_values(array_unique(array_map(fn($c) => $c['itemNo'], self::priceCalls()))),
			'the new price goes first, before the older re-sends');
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
