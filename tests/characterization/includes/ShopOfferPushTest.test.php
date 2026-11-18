<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

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
 * 20261118 CL/LH Codex verification 4: a bulk sync held back past untracking, a refusing shop that recovers days later, and the
 *                send time recorded when a request left.
 * 20261118 CL/LH Codex verification 5: the stall matrix (every path that sends an item's prices, held at every point between its
 *                first read and its call, while the offer is cancelled and the item is repaired and untracked), and the in-flight
 *                marker of a path that died.
 * 20261118 CL/LH A send by another path gets one catch-up instead of a new schedule: the matrix also releases the path early (the
 *                item still tracked, so only the catch-up repairs), and sends by several paths share one catch-up.
 * 20261118 CL/LH Codex verification 6: the matrix also changes the normal prices while the path is held (its own read of today's
 *                prices goes old), and the held path runs on the test's clock; an old item read that cannot be repeated sends no
 *                prices.
 * 20261118 CL/LH CodeRabbit review of #1157: the refused REST tick asserted exactly; sends counted per shop; the bulk
 *                page's exit and its six price calls asserted; open markers read before each tick; children reaped after a failed
 *                test; fake shops stopped with their workers; the price snapshot read under a lock; the bulk group left empty;
 *                the recovery tick's own calls asserted. New: a VAT rate row only in another fiscal year, and an answer cut off
 *                after its status line.
 * 20261118 CL/LH Codex verification 8: a long complete JSON refusal is a refusal and an answer longer than the tick keeps is a
 *                transport failure, on the extension and the shell transport; shops refusing that way keep the item tracked
 *                through the tail.
 * 20261118 CL/LH Codex verification 9: a gzip refusal is never an acceptance and a completed empty answer still is, on all three
 *                transports (curl_multi, curl alone, the shell); a lost, unreadable or shortened shell answer file is a transport
 *                failure.
 * 20261118 CL/LH CodeRabbit review of 63cf3d3f2: each transport leg asserts the transport the tick used; a barcode with an
 *                apostrophe reaches the shops from the bulk page.
 * 20261118 CL/LH Codex verification 10: a negotiated gzip stream cut short, and an answer in any declared content encoding, are
 *                never acceptances; a shop that failed and refused is one stuck shop. CodeRabbit review of #1158: the cut-off
 *                test's offer lasts through its retry; the curl wrapper is private to each run.
 * 20261118 CL/LH CodeRabbit review of 4cd96c7d2: every Content-Encoding field counts and any value is stored printable; the
 *                undeclared gzip case runs on every transport; a bulk run skips an item deleted during it on an account without
 *                offer state.
 * 20261118 CL/LH Codex verification 11: folded and repeated Content-Encoding fields, and redirects, read alike on every transport
 *                (a raw fake shop, support/raw_http_answer.php).
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
	/** @var list<resource> Children started by spawn(); tearDown() kills and reaps any a failed test left running. */
	private static array $children = [];
	/** The curl wrapper's directory, private to this run (curlShim()); removed after the class. */
	private static string $shimDir = '';

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
			// in a session of its own (setsid), so teardown stops the server's workers with it (PHP_CLI_SERVER_WORKERS forks them)
			self::$servers[] = proc_open(['setsid', PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/../support/shop_price_recorder.php'],
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
		if (self::$shimDir !== '') { // the children that ran it are reaped by now (tearDown())
			@unlink(self::$shimDir . '/curl');
			@rmdir(self::$shimDir);
		}
		foreach (self::$servers as $server) {
			$pid = (int)(proc_get_status($server)['pid'] ?? 0);
			if ($pid > 0 && function_exists('posix_kill')) {
				posix_kill(-$pid, 15); // the server's process group: the server and its workers
			} else {
				proc_terminate($server);
			}
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

	/** A child a failed test did not join (a held path, a table lock) must not keep a lock into the next test. */
	protected function tearDown(): void
	{
		foreach (self::$children as $child) {
			if (is_resource($child)) {
				proc_terminate($child, 9);
				proc_close($child);
			}
		}
		self::$children = [];
		parent::tearDown();
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

	/**
	 * @param list<string> $args @param list<string> $phpOptions
	 * @param array<string, string> $env Added to the runner's environment.
	 * @return array<string, mixed>
	 */
	private static function runner(array $args, array $phpOptions = [], array $env = []): array
	{
		$cmd = array_merge([PHP_BINARY], $phpOptions, [__DIR__ . '/../support/run_shop_offer_push.php', CharacterizationEnv::testDb()], $args);
		$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env ? array_merge(getenv(), $env) : null);
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

	/**
	 * @param list<string> $args
	 * @param string|null $stall For the stall tests: SALDI_SHOP_OFFER_STALL for the child (shopOfferTestPause()).
	 * @return array{0: resource, 1: string, 2: string} The runner started in the background; finish it with finish().
	 */
	private static function start(array $args, array $phpOptions = [], ?string $stall = null, ?int $clock = null): array
	{
		$cmd = array_merge([PHP_BINARY], $phpOptions, [__DIR__ . '/../support/run_shop_offer_push.php', CharacterizationEnv::testDb()], $args);
		return self::spawn($cmd, $stall, $clock);
	}

	/** @param list<string> $args @return array{0: resource, 1: string, 2: string} Another support script started in the background. */
	private static function startScript(string $script, array $args, ?string $stall = null, ?int $clock = null): array
	{
		return self::spawn(array_merge([PHP_BINARY, __DIR__ . '/../support/' . $script], $args), $stall, $clock);
	}

	/**
	 * @param list<string> $cmd
	 * @param int|null $clock For the stall tests: the child's clock at its start (SALDI_SHOP_OFFER_CLOCK, shopOfferForeignNow()).
	 * @return array{0: resource, 1: string, 2: string}
	 */
	private static function spawn(array $cmd, ?string $stall, ?int $clock = null): array
	{
		$out = (string)tempnam(sys_get_temp_dir(), 'offerrun');
		$err = (string)tempnam(sys_get_temp_dir(), 'offerrun');
		self::$files[] = $out;
		self::$files[] = $err;
		$env = $stall === null ? null : array_merge(getenv(), ['SALDI_SHOP_OFFER_STALL' => $stall], $clock === null ? [] : ['SALDI_SHOP_OFFER_CLOCK' => (string)$clock]);
		$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $out, 'w'], 2 => ['file', $err, 'w']], $pipes, null, $env);
		fclose($pipes[0]);
		self::$children[] = $proc;
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
		$snapshot = '';
		if (($fh = @fopen(self::$shopLog . '.remote', 'r')) !== false) { // the recorder rewrites it under an exclusive lock
			flock($fh, LOCK_SH);
			$snapshot = (string)stream_get_contents($fh);
			flock($fh, LOCK_UN);
			fclose($fh);
		}
		foreach ((array)json_decode($snapshot, true) as $key => $price) {
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

	/** When the item's last own request left, as recorded: untracking counts T_late plus the margin from here. */
	private static function lastSent(int $id): int
	{
		return (int)max(array_map(fn($e) => $e['leftAt'] ?? $e['sentAt'], self::stateByShop($id)));
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

	/** Calls per shop that carried prices for the item since the log was cleared; every shop must have had the same number. */
	private static function sendsPerShop(string $itemNo): int
	{
		$perShop = ['1' => 0, '2' => 0, '3' => 0];
		foreach (self::priceCalls($itemNo) as $call) {
			$perShop[$call['_shop']] = ($perShop[$call['_shop']] ?? 0) + 1;
		}
		self::assertCount(1, array_unique($perShop), 'every shop got the same number of sends: ' . json_encode($perShop));
		return $perShop['1'];
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
		// The test tenant's name has no "_<n>", so access_check() takes it for a master account, which it serves only from
		// Danosoft's own address: the route is refused before any key is read. The key rules are ShopOfferTickAuthTest's, which also
		// pins that the dispatcher runs the tick only in the branch access_check() granted.
		$route = http_build_query(['action' => 'shop_offer_tick', 'db' => CharacterizationEnv::testDb(), 'saldiuser' => 'tick']);
		$denied = self::runner(['rest', $route, http_build_query(['key' => 'wrong'])]);
		self::assertSame('Wrong IP', $denied['response'], 'a tick without access is refused by access_check()');
		self::assertSame([], array_filter(self::priceCalls(), fn($c) => $c['salesPrice'] === '199.200'), 'and sends nothing');
		self::tick();
		self::assertShopsHold('SPONLY', '199.200 / 799.00', 'the tick sends the new offer');
	}

	/** The VAT rate row kept only in another fiscal year: find_pris() reads it without a year, so the before-price still has VAT. */
	public function testTheBeforePriceFindsAVatRateKeptOnlyInAnotherFiscalYear(): void
	{
		$account = self::one(
			"SELECT k.moms FROM grupper g JOIN kontoplan k ON k.kontonr::text = g.box4 AND k.regnskabsaar = g.fiscal_year
			 WHERE g.art = 'VG' AND g.kodenr = $1 AND g.fiscal_year = $2",
			[(string)self::$stockGroup, self::$regnaar]
		);
		$art = substr((string)$account['moms'], 0, 1) . 'M';
		$kodenr = substr((string)$account['moms'], 1);
		$moved = self::rows("UPDATE grupper SET fiscal_year = fiscal_year + 1000 WHERE art = $1 AND kodenr = $2 AND fiscal_year = $3 RETURNING id",
			[$art, $kodenr, self::$regnaar]);
		self::assertNotSame([], $moved, 'the VAT rate row of this fiscal year now belongs to another year');
		try {
			self::item('SPVATYEAR', self::offer());
			self::tick();
			self::assertShopsHold('SPVATYEAR', self::OFFER, 'the normal price 639.20 plus 25 % VAT is the before-price');
		} finally {
			foreach ($moved as $row) {
				self::rows("UPDATE grupper SET fiscal_year = fiscal_year - 1000 WHERE id = $1", [$row['id']]);
			}
		}
	}

	/** A shop that answers 200 and closes before its answer is complete has not accepted: a transport failure, sent again. */
	public function testAnAnswerCutOffAfterItsStatusLineIsNotAnAcceptance(): void
	{
		$id = self::item('SPCUT', self::offer(159.2, 3)); // active through the retry at +120 s, also when run just before midnight
		$t = time();
		self::control('rules', json_encode([['item' => 'SPCUT', 'shop' => '2', 'cut' => true]]));
		$out = self::tick($t);
		self::uncontrol('rules');
		self::assertSame([2, 1], [$out['accepted'], $out['failed']], 'shop 2\'s transfer did not complete: ' . json_encode($out));
		$shops = self::stateByShop($id);
		self::assertNull($shops['shop 2']['confirmed'], 'shop 2 is not booked as holding the offer');
		self::assertSame(1, $shops['shop 2']['attempts']);
		self::assertNotNull($shops['shop 1']['confirmed']);
		self::clearLog();
		self::tick($t + 120); // the failure is an event: every shop is sent the pair again at the next slot
		self::assertSame([self::OFFER], self::pricesSent('SPCUT')['shop 2'] ?? [], 'shop 2 is sent the offer again');
		self::assertNotNull(self::stateByShop($id)['shop 2']['confirmed'], 'and has accepted it now');
	}

	/** @return list<string> PHP options for a runner whose tick has no curl extension: the tick's shell transport (shopOfferShellOne()). */
	private static function withoutCurlExtension(): array
	{
		return ['-d', 'disable_functions=curl_init,curl_multi_init'];
	}

	/**
	 * The tick's three transports, as PHP options for the runner: curl_multi (any number of shops while it exists), one call at a
	 * time with curl when curl_multi is missing (shopOfferCurlOne()), and the curl command line without the extension.
	 *
	 * @return array<string, list<string>>
	 */
	private static function transports(): array
	{
		return ['multi' => [], 'single' => ['-d', 'disable_functions=curl_multi_init'], 'shell' => self::withoutCurlExtension()];
	}

	/**
	 * A directory with a `curl` that runs the real one, then damages its output file as SHOP_OFFER_TEST_CURL_FAULT says, before
	 * the tick reads it: missing (removed), unreadable (replaced by a directory, which root cannot read as a file either) or
	 * shortened (to 16 KB). Put first in PATH, it reaches only the shell transport.
	 */
	private static function curlShim(): string
	{
		if (self::$shimDir === '') { // private to this run, so parallel runs never rewrite each other's wrapper
			self::$shimDir = sys_get_temp_dir() . '/shopoffer-curlshim-' . getmypid() . '-' . bin2hex(random_bytes(4));
			mkdir(self::$shimDir, 0755, true);
		}
		$dir = self::$shimDir;
		file_put_contents($dir . '/curl', <<<'SH'
#!/bin/sh
out=""; prev=""
for arg do
	if [ "$prev" = "-o" ]; then out="$arg"; fi
	prev="$arg"
done
/usr/bin/curl "$@"
code=$?
case "$SHOP_OFFER_TEST_CURL_FAULT" in
	missing) rm -f "$out";;
	unreadable) rm -f "$out"; mkdir "$out"; echo "$out" >> "$SHOP_OFFER_TEST_CURL_DIRS";;
	shortened) truncate -s 16384 "$out";;
esac
exit "$code"
SH);
		chmod($dir . '/curl', 0755);
		return $dir;
	}

	/** @return array{0: int, 1: int, 2: int} accepted, refused and failed calls of a tick's result. */
	private static function outcomes(array $tick): array
	{
		return [(int)$tick['accepted'], (int)$tick['refused'], (int)$tick['failed']];
	}

	/**
	 * Codex verification 8: a complete JSON refusal longer than the 16 KB the tick once kept is a refusal, on the extension and on
	 * the shell transport; cut to its start it was invalid JSON, which the judge took for an acceptance.
	 */
	public function testALongCompleteJsonRefusalIsARefusalOnEveryTransport(): void
	{
		$t = time();
		foreach (self::transports() as $transport => $php) {
			$itemNo = 'SPLONGREF' . strtoupper(substr($transport, 0, 2));
			$id = self::item($itemNo, self::offer());
			self::control('rules', json_encode([['item' => $itemNo, 'shop' => '2', 'longRefusal' => true]]));
			$out = self::runner(['tick', (string)$t, '10'], $php);
			self::uncontrol('rules');
			self::assertSame($transport, $out['transport'] ?? null, 'the leg ran on its own transport');
			self::assertSame([2, 1, 0], self::outcomes($out), "$transport: shop 2 refused, the others accepted: " . json_encode($out));
			$shop2 = self::stateByShop($id)['shop 2'];
			self::assertSame([null, 1], [$shop2['confirmed'], $shop2['refusals']], "$transport: shop 2 is not booked as holding the offer");
		}
	}

	/** Codex verification 8: an answer longer than the tick keeps (1 MB) is a transport failure on both transports, never an acceptance. */
	public function testAnAnswerLongerThanTheTickKeepsIsATransportFailureOnEveryTransport(): void
	{
		$t = time();
		foreach (self::transports() as $transport => $php) {
			$itemNo = 'SPPAD' . strtoupper(substr($transport, 0, 2));
			$id = self::item($itemNo, self::offer());
			self::control('rules', json_encode([['item' => $itemNo, 'shop' => '2', 'pad' => 1048576]]));
			$out = self::runner(['tick', (string)$t, '10'], $php);
			self::uncontrol('rules');
			self::assertSame($transport, $out['transport'] ?? null, 'the leg ran on its own transport');
			self::assertSame([2, 0, 1], self::outcomes($out), "$transport: shop 2's answer was cut, so not judged: " . json_encode($out));
			$shop2 = self::stateByShop($id)['shop 2'];
			self::assertSame([null, 1], [$shop2['confirmed'], $shop2['attempts']], "$transport: retried as a failed send");
		}
	}

	/**
	 * Codex verification 9 (R9-F2): a gzip-compressed refusal is never an acceptance. Every transport asks for the answer
	 * unencoded and decodes nothing (Codex verification 10), so a declared gzip answer is not judged (a transport failure). A
	 * gzip body without Content-Encoding is not judged either.
	 */
	public function testAGzipRefusalIsNeverAnAcceptanceOnAnyTransport(): void
	{
		$t = time();
		foreach (self::transports() as $transport => $php) {
			$itemNo = 'SPGZ' . strtoupper(substr($transport, 0, 2));
			$id = self::item($itemNo, self::offer());
			self::control('rules', json_encode([['item' => $itemNo, 'shop' => '2', 'longRefusal' => true, 'gzip' => true]]));
			$out = self::runner(['tick', (string)$t, '10'], $php);
			self::uncontrol('rules');
			self::assertSame($transport, $out['transport'] ?? null, 'the leg ran on its own transport');
			self::assertSame([2, 0, 1], self::outcomes($out), "$transport: shop 2's gzip refusal: " . json_encode($out));
			self::assertNull(self::stateByShop($id)['shop 2']['confirmed'], "$transport: shop 2 is not booked as holding the offer");
		}
		foreach (self::transports() as $transport => $php) {
			$itemNo = 'SPGZU' . strtoupper(substr($transport, 0, 2));
			$id = self::item($itemNo, self::offer(159.2, 3));
			self::control('rules', json_encode([['item' => $itemNo, 'shop' => '2', 'longRefusal' => true, 'gzip' => 'undeclared']]));
			$out = self::runner(['tick', (string)$t, '10'], $php);
			self::uncontrol('rules');
			self::assertSame($transport, $out['transport'] ?? null, 'the leg ran on its own transport');
			self::assertSame([2, 0, 1], self::outcomes($out), "$transport: a gzip body without Content-Encoding is not judged: " . json_encode($out));
			self::assertNull(self::stateByShop($id)['shop 2']['confirmed'], "$transport: shop 2 is not booked as holding the offer");
		}
	}

	/**
	 * Codex verification 10 (R10-F1): a shop that compresses only when asked, and then sends a gzip stream cut short with a
	 * matching Content-Length, is never taken for an acceptance. No transport advertises compression any more, so the shop
	 * answers with its plain, complete refusal.
	 */
	public function testANegotiatedGzipStreamCutShortIsNeverAnAcceptance(): void
	{
		$t = time();
		foreach (self::transports() as $transport => $php) {
			$itemNo = 'SPNEG' . strtoupper(substr($transport, 0, 2));
			$id = self::item($itemNo, self::offer(159.2, 3));
			self::control('rules', json_encode([['item' => $itemNo, 'shop' => '2', 'negotiated' => true]]));
			$out = self::runner(['tick', (string)$t, '10'], $php);
			self::uncontrol('rules');
			self::assertSame($transport, $out['transport'] ?? null, 'the leg ran on its own transport');
			self::assertSame([2, 1, 0], self::outcomes($out), "$transport: shop 2 refused: " . json_encode($out));
			self::assertNull(self::stateByShop($id)['shop 2']['confirmed'], "$transport: shop 2 is not booked as holding the offer");
		}
	}

	/**
	 * Codex verification 10: an answer in a declared content encoding is a transport failure on every transport, never decoded
	 * and judged: gzip, deflate, br and zstd, here each a complete encoding of "OK", and a name no client knows. "identity" is
	 * no encoding and is judged as usual.
	 */
	public function testADeclaredContentEncodingIsATransportFailureNotDecoded(): void
	{
		$t = time();
		foreach (['gzip', 'deflate', 'br', 'zstd', 'x-unknown', 'identity'] as $n => $encoding) {
			foreach (self::transports() as $transport => $php) {
				$itemNo = 'SPENC' . $n . strtoupper(substr($transport, 0, 2));
				$id = self::item($itemNo, self::offer(159.2, 3));
				self::control('rules', json_encode([['item' => $itemNo, 'shop' => '2', 'encoding' => $encoding]]));
				$out = self::runner(['tick', (string)$t, '10'], $php);
				self::uncontrol('rules');
				self::assertSame($transport, $out['transport'] ?? null, 'the leg ran on its own transport');
				if ($encoding === 'identity') {
					self::assertSame([3, 0, 0], self::outcomes($out), "$transport, $encoding: " . json_encode($out));
					continue;
				}
				self::assertSame([2, 0, 1], self::outcomes($out), "$transport, $encoding: shop 2's answer is not judged: " . json_encode($out));
				$shop2 = self::stateByShop($id)['shop 2'];
				self::assertSame([null, 1], [$shop2['confirmed'], $shop2['attempts']], "$transport, $encoding: retried as a failed send");
			}
		}
	}

	/**
	 * CodeRabbit on 4cd96c7d2: every Content-Encoding field of the final response counts, also a second one after "identity"
	 * (the shell's %header{} gave only the first). And a value that is not UTF-8 is stored printable: the state is still
	 * written, and the send is a transport failure like any other.
	 */
	public function testEveryContentEncodingFieldCountsAndAnyValueIsStoredPrintable(): void
	{
		$t = time();
		foreach (['repeated' => ['encodings' => ['identity', 'br']], 'not UTF-8' => ['encoding' => 'bad-bytes']] as $case => $rule) {
			foreach (self::transports() as $transport => $php) {
				$itemNo = 'SPFLD' . ($case === 'repeated' ? 'R' : 'B') . strtoupper(substr($transport, 0, 2));
				$id = self::item($itemNo, self::offer(159.2, 3));
				self::control('rules', json_encode([['item' => $itemNo, 'shop' => '2'] + $rule]));
				$out = self::runner(['tick', (string)$t, '10'], $php);
				self::uncontrol('rules');
				self::assertSame($transport, $out['transport'] ?? null, 'the leg ran on its own transport');
				self::assertSame([2, 0, 1, 0], [...self::outcomes($out), $out['stateErrors']], "$transport, $case: " . json_encode($out));
				$shop2 = self::stateByShop($id)['shop 2'];
				self::assertSame([null, 1], [$shop2['confirmed'], $shop2['attempts']], "$transport, $case: recorded as a failed send");
				self::assertMatchesRegularExpression('/^[\x20-\x7e]*$/', $shop2['error'], "$transport, $case: the stored error is printable");
			}
		}
	}

	/**
	 * Codex verification 11: every transport reads the final response's Content-Encoding fields with one parser
	 * (shopOfferContentCodings()), so a folded field (a line that starts with a space or tab continues it) and a repeated one
	 * count on curl and on the shell alike, and only identity is accepted. The shop is a raw server (support/raw_http_answer.php),
	 * since PHP's built-in one cannot send a folded field; its encoded answers are a zlib-compressed refusal.
	 */
	public function testFoldedAndRepeatedContentEncodingFieldsAreReadAlikeOnEveryTransport(): void
	{
		$portFile = (string)tempnam(sys_get_temp_dir(), 'rawport');
		self::$files[] = $portFile;
		$server = proc_open([PHP_BINARY, __DIR__ . '/../support/raw_http_answer.php', $portFile],
			[0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
		self::$children[] = $server;
		$box5 = (string)self::one("SELECT box5 FROM grupper WHERE art = 'API'")['box5'];
		try {
			for ($i = 0; $i < 50 && (int)@file_get_contents($portFile) === 0; $i++) {
				usleep(100000);
			}
			$port = (int)file_get_contents($portFile);
			self::assertGreaterThan(0, $port, 'the raw shop listens');
			$t = time();
			$cases = ['folded' => false, 'folded-empty' => false, 'folded-identity' => false, 'folded-tab' => false,
				'double-identity-first' => false, 'double-deflate-first' => false, 'redirect-to-encoded' => false,
				'identity' => true, 'identity-mixed-case' => true, 'identity-folded' => true, 'identity-repeated' => true,
				'redirect-from-encoded' => true];
			$n = 0;
			foreach ($cases as $case => $judged) {
				// a new shop 2 url is a new shop: the earlier cases' items would be due to it too, so each case starts as setUp() does
				self::rows("UPDATE varer SET specialtype = NULL, special_price = 0");
				self::clearState();
				self::rows("UPDATE grupper SET box5 = $1 WHERE art = 'API'", ["http://127.0.0.1:$port/$case?shop=2"]);
				foreach (self::transports() as $transport => $php) {
					$itemNo = 'SPHDR' . $n . strtoupper(substr($transport, 0, 2));
					$id = self::item($itemNo, self::offer(159.2, 3));
					$out = self::runner(['tick', (string)$t, '10'], $php);
					self::assertSame($transport, $out['transport'] ?? null, 'the leg ran on its own transport');
					if ($judged) {
						self::assertSame([3, 0, 0], self::outcomes($out), "$transport, $case: only identity, judged: " . json_encode($out));
						continue;
					}
					self::assertSame([2, 0, 1], self::outcomes($out), "$transport, $case: shop 2's answer is not judged: " . json_encode($out));
					$shop2 = self::stateByShop($id)['shop 2'];
					self::assertSame([null, 1], [$shop2['confirmed'], $shop2['attempts']], "$transport, $case: retried as a failed send");
					self::assertStringContainsString('deflate', $shop2['error'], "$transport, $case: the coding is named");
				}
				$n++;
			}
		} finally {
			self::rows("UPDATE grupper SET box5 = $1 WHERE art = 'API'", [$box5]);
			proc_terminate($server, 9);
			proc_close($server);
		}
	}

	/**
	 * Codex verification 10 (R10-F4): a shop whose sends of a pair both failed and were refused eight times is one stuck shop,
	 * also in the tick in which its eighth failure comes on top of eight refusals.
	 */
	public function testAShopThatBothFailedAndRefusedCountsAsStuckOnce(): void
	{
		$id = self::item('SPSTUCK', self::offer(159.2, 3));
		$t = time();
		self::tick($t);
		$file = self::$tempDir . "/shopoffer/item-$id.json";
		$key = substr(sha1(trim((string)self::one("SELECT box5 FROM grupper WHERE art = 'API'")['box5'])), 0, 16);
		$state = json_decode((string)file_get_contents($file), true);
		$state['shops'][$key] = ['confirmed' => null, 'attempts' => 8, 'refusals' => 8, 'retryAt' => $t + 10800, 'failedLast' => true]
			+ $state['shops'][$key];
		file_put_contents($file, json_encode($state));
		self::assertSame(1, self::tick($t + 1)['stuck'], 'eight failures and eight refusals: one stuck shop');
		$state['shops'][$key] = ['attempts' => 7, 'retryAt' => 0, 'failedLast' => false] + $state['shops'][$key];
		file_put_contents($file, json_encode($state));
		self::control('fail2');
		$out = self::tick($t + 120);
		self::uncontrol('fail2');
		self::assertSame([1, 1], [$out['failed'], $out['stuck']], 'its eighth failure on top of eight refusals is still one stuck shop: ' . json_encode($out));
	}

	/** Codex verification 9: a completed empty answer is still an acceptance, on every transport. */
	public function testACompletedEmptyAnswerIsStillAnAcceptanceOnEveryTransport(): void
	{
		$t = time();
		foreach (self::transports() as $transport => $php) {
			$itemNo = 'SPEMPTY' . strtoupper(substr($transport, 0, 2));
			$id = self::item($itemNo, self::offer());
			self::control('rules', json_encode([['item' => $itemNo, 'empty' => true]]));
			$out = self::runner(['tick', (string)$t, '10'], $php);
			self::uncontrol('rules');
			self::assertSame($transport, $out['transport'] ?? null, 'the leg ran on its own transport');
			self::assertSame([3, 0, 0], self::outcomes($out), "$transport: " . json_encode($out));
		}
	}

	/**
	 * Codex verification 9 (R9-F1): on the shell transport, an answer file that is missing, unreadable or shorter than what curl
	 * received is a transport failure, never an empty or partial answer that could pass for an acceptance.
	 */
	public function testAShellAnswerFileThatIsLostOrShortenedIsATransportFailure(): void
	{
		$t = time();
		$dirs = (string)tempnam(sys_get_temp_dir(), 'curldirs');
		self::$files[] = $dirs;
		try {
			foreach (['missing', 'unreadable', 'shortened'] as $fault) {
				$itemNo = 'SPSHF' . strtoupper(substr($fault, 0, 3));
				$id = self::item($itemNo, self::offer());
				self::control('rules', json_encode([['item' => $itemNo, 'longRefusal' => true]]));
				$out = self::runner(['tick', (string)$t, '10'], self::withoutCurlExtension(),
					['PATH' => self::curlShim() . ':' . getenv('PATH'), 'SHOP_OFFER_TEST_CURL_FAULT' => $fault, 'SHOP_OFFER_TEST_CURL_DIRS' => $dirs]);
				self::uncontrol('rules');
				self::assertSame('shell', $out['transport'] ?? null, 'the shell transport ran');
				self::assertSame([0, 0, 3], self::outcomes($out), "$fault: every shop's answer is a transport failure: " . json_encode($out));
				foreach (self::stateByShop($id) as $shop => $entry) {
					self::assertSame([null, 1], [$entry['confirmed'], $entry['attempts']], "$fault: $shop is not confirmed");
					self::assertStringContainsString('answer could not be read', $entry['error'], "$fault: $shop");
				}
			}
		} finally {
			foreach (array_filter(explode("\n", (string)@file_get_contents($dirs))) as $dir) {
				@rmdir($dir);
			}
		}
	}

	/**
	 * Codex verification 8 (tick-long-refusal): shops that refuse the normal prices after an offer with a long complete JSON
	 * refusal, at every tick through the tail, keep the item tracked: no refusal is taken for an acceptance, so it is never retired.
	 */
	public function testShopsThatRefuseWithALongAnswerKeepTheItemTrackedThroughTheTail(): void
	{
		$id = self::item('SPLONGTAIL', self::offer());
		$t = time();
		self::tick($t);
		self::assertShopsHold('SPLONGTAIL', self::OFFER);
		self::rows("UPDATE varer SET specialtype = NULL, special_price = 0 WHERE id = $1", [$id]); // the offer ends
		self::control('rules', json_encode([['item' => 'SPLONGTAIL', 'longRefusal' => true]]));
		$accepted = 0;
		foreach ([0, 120, 300, 900, 3600, 21600, 43200, 64800, 86400, 87121] as $offset) {
			$accepted += (int)self::tick($t + 10 + $offset)['accepted'];
		}
		self::uncontrol('rules');
		self::assertSame(0, $accepted, 'no refusal was taken for an acceptance');
		self::assertTrue(self::tracked($id), 'at +87,121 s the item is still tracked: no shop accepted the normal prices');
		foreach (self::stateByShop($id) as $shop => $entry) {
			self::assertGreaterThanOrEqual(8, $entry['refusals'], "$shop refused every try and counts as stuck");
		}
		self::assertSame(self::REMOTE_OFFER, self::remote('SPLONGTAIL'), 'the shops still show the offer, which the reconciler keeps trying to end');
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
		$sent = self::lastSent($id);
		self::assertGreaterThanOrEqual($end + 86400, $sent);
		self::tick($sent + 659);
		self::assertNotNull(self::runner(['state', (string)$id])['state'], 'not untracked while the last re-send could still be on its way');
		$out = self::tick($sent + 660);
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
		self::assertGreaterThanOrEqual($t + 720, self::runner(['state', (string)$id])['state']['catchup'] ?? 0,
			'and left one catch-up for after their late window');
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
		self::assertFileDoesNotExist(self::$tempDir . "/shopoffer/inflight-$id.json", 'and no in-flight marker');
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
		self::assertSame(1, self::tick(self::lastSent($id) + 660)['untracked'], 'untracked 24 hours after the last event, once no request can still arrive');
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
		// The card save calls the shops one after another and looks the pair up right before each call: a call that leaves
		// after the tick recorded B carries B. Shop 1's call leaves while the tick still waits for its answers, so A.
		for ($i = 0; $i < 100 && (self::remote('SPCONC')['shop 1'] ?? null) !== self::OFFER; $i++) {
			usleep(50000);
		}
		self::assertSame(self::OFFER, self::remote('SPCONC')['shop 1'], 'the late A landed after B in shop 1');
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
		$stuck = [];
		for ($i = 0, $at = $t + 1; $i < 8; $i++, $at += 10801) {
			$stuck[] = self::tick($at)['stuck'];
		}
		self::assertSame([0, 0, 0, 0, 0, 0, 0, 1], $stuck, 'shop 2 shows as stuck after eight refusals of A');
		self::assertSame(8, self::stateByShop($id)['shop 2']['refusals']);
		self::assertStringContainsString('SHOP OFFER REFUSED', (string)@file_get_contents(self::$tempDir . '/rest_api.log'));
		self::assertStringContainsString('STUCK: check this shop', (string)@file_get_contents(self::$tempDir . '/rest_api.log'));
		self::clearLog();
		self::tick($at);
		self::assertArrayHasKey('shop 2', self::pricesSent('SPPAIR'), 'A is still sent to shop 2: refusals never give up');
		self::rows("UPDATE varer SET special_price = 109.2 WHERE id = $1", [$id]); // B
		self::tick($at + 1);
		$entry = self::stateByShop($id)['shop 2'];
		self::assertSame([1, $at + 1 + 60], [$entry['refusals'], $entry['retryAt']], 'B has its own counters and back-off, not A\'s');
		self::uncontrol('reject2');
		self::tick($at + 122);
		self::assertSame('109.200 / 799.00', self::remote('SPPAIR')['shop 2'], 'shop 2 takes B once it accepts again');
	}

	public function testARefusingShopGetsTheNormalPricesWhenItRecoversDaysLater(): void
	{
		$id = self::item('SPRECOVER', self::offer(79.2, 3));
		$t = time();
		self::tick($t);
		self::rows("UPDATE varer SET specialtype = NULL, special_price = 0 WHERE id = $1", [$id]); // the offer is cancelled
		self::control('rules', json_encode([['item' => 'SPRECOVER', 'shop' => '2', 'refuse' => true]]));
		$stuck = 0;
		$refusedAt = [];
		for ($i = 0, $at = $t + 1; $i < 20 && count($refusedAt) < 10; $i++, $at += 10801) { // the scheduler, every 3 hours on a test clock
			$before = self::stateByShop($id)['shop 2']['refusals'];
			$stuck = max($stuck, self::tick($at)['stuck']);
			if (self::stateByShop($id)['shop 2']['refusals'] > $before) $refusedAt[] = $at;
		}
		self::assertCount(10, $refusedAt, 'shop 2 refused the normal prices ten times, and was never given up');
		$gaps = array_map(fn($a, $b) => $b - $a, array_slice($refusedAt, 0, -1), array_slice($refusedAt, 1));
		self::assertSame(10801, max($gaps), 'tried at every tick 3 hours apart: never more than 3 hours after a refusal, the 6-hour tail too');
		self::assertSame(1, $stuck);
		self::assertSame('79.200 / 799.00', self::remote('SPRECOVER')['shop 2'], 'it still shows the cancelled offer');
		self::uncontrol('rules'); // the shop recovers
		self::clearLog();
		$later = $at + 3 * 86400;
		self::tick($later);
		self::assertShopsHold('SPRECOVER', self::NORMAL, 'days later, the next try puts the normal prices in shop 2 too');
		self::assertSame(1, self::tick(self::lastSent($id) + 660)['untracked'], 'then the item is no longer tracked');
	}

	// ------------------------------------------------------------------ no send carries a pair read earlier (Codex v4)

	/**
	 * Runs $body with the bulk sync page (sync_shop/sync_all_products.php) set up to handle one item alone: item $varenr, with
	 * $offer, in a group of its own that is the only stocked one, and the shops configured without a query string (the page
	 * builds its urls by appending "?" to the endpoint). Everything is put back afterwards.
	 *
	 * @param callable(int): void $body Gets the item id.
	 */
	private static function withBulkSyncOf(string $varenr, array $offer, callable $body): void
	{
		$endpoints = self::one("SELECT box4, box5, box6 FROM grupper WHERE art = 'API'");
		$bare = array_map(fn($e) => preg_replace('/\\?.*$/', '', (string)$e), $endpoints);
		$stocked = self::rows("SELECT id, box8 FROM grupper WHERE art = 'VG' AND COALESCE(box8, '') <> ''");
		$own = self::one("SELECT box4, box7 FROM grupper WHERE art = 'VG' AND kodenr = $1 AND fiscal_year = $2", [(string)self::$stockGroup, self::$regnaar]);
		$groupId = CharacterizationEnv::nextId(self::$tenant, 'grupper');
		try {
			self::rows("UPDATE grupper SET box4 = $1, box5 = $2, box6 = $3 WHERE art = 'API'", [$bare['box4'], $bare['box5'], $bare['box6']]);
			self::rows("UPDATE grupper SET box8 = '' WHERE art = 'VG' AND COALESCE(box8, '') <> ''");
			self::rows("INSERT INTO grupper (id, beskrivelse, kodenr, art, box4, box7, box8, fiscal_year) VALUES ($1, 'Bulk test', '9871', 'VG', $2, $3, 'on', $4)",
				[$groupId, $own['box4'], $own['box7'], self::$regnaar]);
			$id = self::item($varenr, $offer);
			self::rows("UPDATE varer SET gruppe = 9871 WHERE id = $1", [$id]);
			CharacterizationEnv::resyncSequences(self::$tenant); // the page's session setup inserts rows of its own
			$body($id);
		} finally {
			self::rows("UPDATE varer SET gruppe = $1 WHERE gruppe = 9871", [self::$stockGroup]); // the group is made again by the next run
			self::rows("DELETE FROM grupper WHERE id = $1", [$groupId]);
			foreach ($stocked as $row) {
				self::rows("UPDATE grupper SET box8 = $1 WHERE id = $2", [$row['box8'], $row['id']]);
			}
			self::rows("UPDATE grupper SET box4 = $1, box5 = $2, box6 = $3 WHERE art = 'API'", [$endpoints['box4'], $endpoints['box5'], $endpoints['box6']]);
		}
	}

	/** @param array{0: resource, 1: string, 2: string} $page Waits for the page to end and for its six calls (stock and price, three shops). */
	private static function bulkSyncCalls(array $page, string $varenr): array
	{
		proc_close($page[0]);
		for ($i = 0; $i < 300 && count(self::priceCalls($varenr, null)) < 6; $i++) {
			usleep(50000);
		}
		usleep(500000);
		$calls = self::priceCalls($varenr, null);
		self::assertCount(6, $calls, 'the page sent its stock and price calls to the three shops');
		return $calls;
	}

	/** CodeRabbit on 63cf3d3f2: the bulk page passes every url to the shell through escapeshellarg(), so a barcode with an apostrophe arrives. */
	public function testABulkSyncSendsABarcodeWithAnApostrophe(): void
	{
		self::withBulkSyncOf('SPBULKQ', self::offer(159.2, 3), function (int $id): void {
			self::rows("UPDATE varer SET stregkode = $1 WHERE id = $2", ["57'01", $id]);
			self::clearLog();
			$page = self::startScript('run_sync_all_products_page.php', [self::sessionId()]);
			foreach (self::bulkSyncCalls($page, 'SPBULKQ') as $sent) {
				self::assertSame("57'01", $sent['barcode'] ?? null, 'the barcode arrives as it is stored');
			}
		});
	}

	/**
	 * CodeRabbit on 4cd96c7d2: on an account without offer state, an item deleted between the page's list and its read is
	 * skipped as "not read again", never sent with the prices of the list.
	 */
	public function testABulkSyncSkipsAnItemDeletedDuringTheRunWithoutOfferState(): void
	{
		self::withBulkSyncOf('SPBULKDEL', self::offer(159.2, 3), function (int $id): void {
			self::assertDirectoryDoesNotExist(self::$tempDir . '/shopoffer', 'an account whose scheduler never ran');
			$prefix = sys_get_temp_dir() . '/shopoffer-stall-' . bin2hex(random_bytes(4));
			self::$files[] = "$prefix.held";
			self::$files[] = "$prefix.go";
			self::clearLog();
			$page = self::startScript('run_sync_all_products_page.php', [self::sessionId()], "bulk:listed:$id:$prefix");
			for ($i = 0; $i < 600 && !is_file("$prefix.held"); $i++) {
				usleep(50000);
			}
			self::assertFileExists("$prefix.held", 'the page is held after its list, before it reads the item');
			self::rows("DELETE FROM varer WHERE id = $1", [$id]);
			file_put_contents("$prefix.go", '');
			self::assertSame(0, proc_close($page[0]), 'the page ran to its end');
			self::assertStringContainsString('could not be read again', (string)file_get_contents($page[1]));
			usleep(1500000); // the page's calls are fire-and-forget
			self::assertSame([], self::priceCalls('SPBULKDEL', null), 'no stock or price call with the prices of the list');
		});
	}

	public function testABulkSyncHeldBackPastUntrackingSendsTodaysPrices(): void
	{
		self::withBulkSyncOf('SPBULK', self::offer(79.2, 3), function (int $id): void {
			$t = time();
			self::tick($t);
			self::assertShopsHold('SPBULK', '79.200 / 799.00');
			$marker = sys_get_temp_dir() . '/shopoffer-bulklock-' . bin2hex(random_bytes(4));
			self::$files[] = $marker;
			$lock = self::start(['locktable', '60', 'lagerstatus', $marker]);
			try {
				for ($i = 0; $i < 200 && !is_file($marker); $i++) {
					usleep(50000);
				}
				self::assertFileExists($marker, 'lagerstatus is locked');
				self::clearLog();
				// the bulk page starts while the offer is on, and waits on its stock query
				$page = self::startScript('run_sync_all_products_page.php', [self::sessionId()]);
				usleep(3000000);
				self::assertSame([], self::priceCalls('SPBULK', null), 'the page waits on lagerstatus');
				self::rows("UPDATE varer SET specialtype = NULL, special_price = 0 WHERE id = $1", [$id]); // the offer is cancelled
				$end = $t + 1;
				foreach ([0, 120, 300, 900, 3600, 21600, 43200, 64800, 86400] as $offset) {
					self::tick($end + $offset); // restoration and the whole tail, on a test clock
				}
				self::assertSame(1, self::tick(self::lastSent($id) + 660)['untracked']);
				self::assertNull(self::runner(['state', (string)$id])['state'], 'no longer tracked');
				self::assertShopsHold('SPBULK', self::NORMAL);
				self::clearLog();
			} finally {
				proc_terminate($lock[0]); // the lock goes: the page goes on and sends
				proc_close($lock[0]);
			}
			foreach (self::bulkSyncCalls($page, 'SPBULK') as $sent) {
				self::assertSame(self::NORMAL, $sent['salesPrice'] . ' / ' . $sent['retailPrice'], 'looked up when it sent: today\'s prices, not the offer it saw when it began');
			}
			self::assertShopsHold('SPBULK', self::NORMAL, 'the cancelled offer is not put back');
		});
	}

	public function testABulkSyncSendOfATrackedItemGetsOneCatchUp(): void
	{
		self::withBulkSyncOf('SPBULKON', self::offer(159.2, 3), function (int $id): void {
			$t = time() - 3600;
			self::tick($t);
			self::tick($t + 3600); // the early re-sends are done; the next scheduled one is 3 hours on
			self::clearLog();
			$page = self::startScript('run_sync_all_products_page.php', [self::sessionId()]);
			foreach (self::bulkSyncCalls($page, 'SPBULKON') as $sent) {
				self::assertSame(self::OFFER, $sent['salesPrice'] . ' / ' . $sent['retailPrice'], 'a tracked item: the pair each shop accepted');
			}
			self::assertCatchUpOnly($id, 'SPBULKON', $t);
		});
	}

	public function testSendsByOtherPathsShareOneCatchUp(): void
	{
		$id = self::item('SPSHARE', self::offer(159.2, 3));
		$t = time() - 3600;
		self::tick($t);
		self::tick($t + 3600);
		foreach ([['sync_price', (string)$id], ['sync_vare', (string)$id, '1', 'stock'], ['sale', (string)$id, '1'], ['sync_price', (string)$id]] as $call) {
			self::runner($call);
		}
		self::assertCatchUpOnly($id, 'SPSHARE', $t);
	}

	/**
	 * After sends by other paths of a tracked item whose early re-sends are done: one catch-up, due T_late plus 2 minutes after
	 * the latest send, sends every shop the current pair once; no event, so nothing more until the schedule's next send.
	 */
	private static function assertCatchUpOnly(int $id, string $varenr, int $anchor): void
	{
		$lastSend = time();
		$state = self::runner(['state', (string)$id])['state'];
		self::assertSame($anchor, $state['anchor'], 'the schedule is not restarted');
		self::assertEqualsWithDelta($lastSend + 720, $state['catchup'], 3, 'one catch-up, after the latest send\'s late window');
		self::clearLog();
		$out = self::tick($state['catchup'] - 1);
		self::assertSame(0, $out['calls'], 'nothing before it');
		$out = self::tick($state['catchup']);
		self::assertSame([3, 0], [$out['calls'], $out['events']], 'then every shop once, and no event');
		self::assertSame(self::OFFER_ALL, self::pricesSent($varenr));
		$after = self::runner(['state', (string)$id])['state'];
		self::assertArrayNotHasKey('catchup', $after, 'served');
		self::assertSame($anchor, $after['anchor']);
		self::clearLog();
		foreach ([120, 300, 900, 3600] as $offset) {
			self::tick($state['catchup'] + $offset);
		}
		self::assertSame([], self::priceCalls($varenr, null), 'and no re-send schedule after it');
	}

	public function testTheSendTimeIsWhenTheRequestLeft(): void
	{
		$first = self::item('SPLEFT1', self::offer(159.2, 3));
		$second = self::item('SPLEFT2', self::offer(159.2, 3));
		self::control('rules', json_encode([['item' => 'SPLEFT1', 'answer' => 3]]));
		$t = time();
		self::tick($t, 10);
		$left1 = array_map(fn($e) => $e['leftAt'] ?? $e['sentAt'], self::stateByShop($first));
		$left2 = array_map(fn($e) => $e['leftAt'] ?? $e['sentAt'], self::stateByShop($second));
		self::assertLessThanOrEqual($t + 1, max($left1));
		self::assertGreaterThanOrEqual($t + 3, min($left2), 'the second item left after the first one\'s 3-second answers, and its leftAt says so');
		self::assertSame([$t, $t, $t], array_column(self::stateByShop($second), 'sentAt'), 'the schedule still counts from the tick that read the prices');
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

	// ------------------------------------------------------------------ the stall matrix (Codex v5)

	/**
	 * @return array<string, array{0: string, 1: string, 2: string, 3: string}> Every path that sends an item's prices × every point
	 *         between its first read and its call × when the path goes on: late (after the item was untracked) or early (2 hours
	 *         after the change, still tracked, the next scheduled send hours away, so only the catch-up can repair a stale send) ×
	 *         what changes while it is held: the offer is cancelled, or (the offer ended before the path read the item) the normal
	 *         prices change, so the path's own read of today's prices is old.
	 */
	public static function stallCells(): array
	{
		$cells = [];
		foreach (['cancel', 'normal'] as $change) {
			foreach (['late', 'early'] as $release) {
				foreach (['card', 'stock', 'document', 'bulk', 'tick'] as $path) {
					foreach (['queried', 'locked', 'marked', 'send'] as $point) {
						$cells["$change / $path / $point / $release"] = [$path, $point, $release, $change];
					}
				}
			}
		}
		return $cells;
	}

	/**
	 * A path held at a point between its first read and its call (shopOfferTestPause()), while the offer is cancelled and the
	 * reconciler, on its test clock, restores the normal prices, runs the tail and untracks the item where nothing stops it; then
	 * the path goes on and sends, and the reconciler goes on. Every shop must hold the desired prices within T_late plus the next
	 * re-send after the release (the ticks at +0, 2, 5 and 15 minutes), and at the end; no tick may untrack the item while an
	 * in-flight marker of it is open. The path runs on the test's clock too (SALDI_SHOP_OFFER_CLOCK from just after the first
	 * tick; on release the clock the reconciler has reached), so its markers, its catch-ups and the age of its item read are
	 * measured as the reconciler measures time.
	 * With SHOP_OFFER_MATRIX_LOG set, each cell appends what it saw there (one JSON line), for the evidence.
	 */
	#[DataProvider('stallCells')]
	public function testStallMatrix(string $path, string $point, string $release = 'late', string $change = 'cancel'): void
	{
		$name = 'SM' . strtoupper(substr($path, 0, 3) . substr($point, 0, 3) . $release[0] . $change[0]);
		$t0 = $release === 'late' ? time() - 2 * 86400 : time() - 2 * 3600 - 10;
		$offer = ['price' => 79.2, 'from' => date('Y-m-d', $t0 - 86400), 'to' => date('Y-m-d', time() + 3 * 86400)];
		$cell = function (int $id) use ($path, $point, $name, $t0, $release, $change): void {
			self::stallCell($path, $point, $id, $name, $t0, $release, $change);
		};
		if ($path === 'bulk') {
			self::withBulkSyncOf($name, $offer, $cell);
		} else {
			$cell(self::item($name, $offer));
		}
	}

	private static function stallCell(string $path, string $point, int $id, string $name, int $t0, string $release, string $change): void
	{
		self::tick($t0);
		self::assertShopsHold($name, '79.200 / 799.00', 'the offer is in every shop');
		$desired = $change === 'cancel' ? self::NORMAL : '259.200 / 999.000';
		$want = ['shop 1' => $desired, 'shop 2' => $desired, 'shop 3' => $desired];
		if ($change === 'normal') {
			self::rows("UPDATE varer SET specialtype = NULL, special_price = 0 WHERE id = $1", [$id]); // the offer ended before the path reads the item
		}
		$prefix = sys_get_temp_dir() . '/shopoffer-stall-' . bin2hex(random_bytes(4));
		self::$files[] = "$prefix.held";
		self::$files[] = "$prefix.go";
		$stall = "$path:$point:" . ($path === 'tick' && $point === 'queried' ? 0 : $id) . ":$prefix";
		if ($path === 'tick') {
			if ($change === 'cancel') self::rows("UPDATE varer SET special_price = 99.2 WHERE id = $1", [$id]); // what the held tick will send
			$run = self::start(['tick', (string)($t0 + 5), '10'], [], $stall);
		} elseif ($path === 'bulk') {
			$run = self::startScript('run_sync_all_products_page.php', [self::sessionId()], $stall, $t0 + 5);
		} else {
			$args = ['card' => ['sync_price', (string)$id], 'stock' => ['sync_vare', (string)$id, '1', 'stock'], 'document' => ['sale', (string)$id, '1']][$path];
			$run = self::start($args, [], $stall, $t0 + 5); // the path runs on the test's clock, from just after the first tick
		}
		for ($i = 0; $i < 600 && !is_file("$prefix.held"); $i++) {
			usleep(50000);
		}
		self::assertFileExists("$prefix.held", "the $path is held at '$point'");
		if ($change === 'cancel') {
			self::rows("UPDATE varer SET specialtype = NULL, special_price = 0 WHERE id = $1", [$id]); // cancelled: the normal prices are desired
		} else {
			self::rows("UPDATE varer SET salgspris = 259.2, retail_price = 999 WHERE id = $1", [$id]); // new normal prices: the path read the old ones
		}
		$row = ['path' => $path, 'point' => $point, 'release' => $release, 'change' => $change, 'openWhenUntracked' => []];
		$watch = function (int $clock) use ($id, &$row): void {
			$open = self::openMarker($id, $clock); // read before the tick: a tick that removed the marker too would hide it after
			self::tick($clock);
			if (!self::tracked($id) && ($open || self::openMarker($id, $clock))) $row['openWhenUntracked'][] = $clock;
		};

		// held: the restoration; late: the tail and on until the item is untracked (where nothing stops that) or two days past now
		$clock = $t0 + 10;
		$offsets = $release === 'late' ? [0, 120, 300, 900, 3600, 21600, 43200, 64800, 86400, 86400 + 700] : [0, 120, 300, 900, 3600];
		foreach ($offsets as $offset) {
			$watch($clock + $offset);
		}
		$c = $clock + end($offsets);
		while ($release === 'late' && self::tracked($id) && $c < time() + 2 * 86400) {
			$c += 21600;
			$watch($c);
		}
		$row['untrackedWhileHeld'] = !self::tracked($id);
		$row['shopsWhileHeld'] = self::remote($name);

		// released: the path goes on at the test's clock (the hold lasted until then) and sends what it chose before or after it
		$released = max($c + 60, time()); // never before now: a path without the test's clock (older code) records on the wall clock
		self::clearLog();
		file_put_contents("$prefix.go", (string)$released);
		if ($path === 'bulk') {
			$exit = proc_close($run[0]);
			self::assertSame(0, $exit, 'the bulk page ran to its end: ' . file_get_contents($run[2]));
			self::assertStringContainsString('CHARTEST_PAGE_DONE', (string)file_get_contents($run[1]));
			for ($i = 0; $i < 200 && count(self::priceCalls($name, null)) < 6; $i++) {
				usleep(50000);
			}
			self::assertCount(6, self::priceCalls($name, null), 'and sent its stock and price calls to the three shops');
		} else {
			self::finish($run);
		}
		$row['sentAfterRelease'] = array_values(array_unique(array_map(fn($call) => $call['salesPrice'] . ' / ' . $call['retailPrice'], self::priceCalls($name, null))));
		$row['shopsAfterRelease'] = self::remote($name);

		// the bound: T_late plus the next re-send after the release (for a send by another path: its catch-up, T_late plus 2 minutes)
		$after = $released + 60;
		foreach ([0, 120, 300, 900] as $offset) {
			$watch($after + $offset);
		}
		$row['shopsWithinBound'] = self::remote($name);
		$end = $after + 900;
		for ($i = 0; $i < 12 && self::tracked($id); $i++) {
			$end += 21600;
			$watch($end);
		}
		$row['untrackedAtEnd'] = !self::tracked($id);
		$row['markerLeft'] = is_file(self::$tempDir . "/shopoffer/inflight-$id.json");
		$row['shopsAtEnd'] = self::remote($name);
		$row['pass'] = $row['shopsWithinBound'] === $want && $row['shopsAtEnd'] === $want
			&& $row['untrackedAtEnd'] && !$row['markerLeft'] && !$row['openWhenUntracked'];
		if (($log = getenv('SHOP_OFFER_MATRIX_LOG')) !== false && $log !== '') {
			file_put_contents($log, json_encode($row, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
		}

		self::assertSame([], $row['openWhenUntracked'], 'no tick untracked the item while an in-flight marker of it was open');
		self::assertSame($want, $row['shopsWithinBound'], 'every shop on the desired prices within T_late plus the next re-send after the release');
		self::assertSame($want, $row['shopsAtEnd'], 'and at the end');
		self::assertTrue($row['untrackedAtEnd'], 'untracked at the end');
		self::assertFalse($row['markerLeft'], 'with no marker left');
	}

	private static function tracked(int $id): bool
	{
		return is_file(self::$tempDir . "/shopoffer/item-$id.json");
	}

	/** Whether an in-flight marker of the item is open at the tick's clock (T_late plus 2 minutes; one expired by then is not). */
	private static function openMarker(int $id, int $clock): bool
	{
		foreach ((array)json_decode((string)@file_get_contents(self::$tempDir . "/shopoffer/inflight-$id.json"), true) as $marker) {
			if ((int)($marker['startedAt'] ?? 0) + 720 > $clock) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A path whose read of the item is older than 2 minutes when it would send reads it again; when that read cannot finish
	 * (varer locked), it sends no prices: the card makes no price call, the sale queues its stock sync, and both leave an event,
	 * so the reconciler sends the current pair.
	 */
	public function testAnOldItemReadThatCannotBeRepeatedSendsNoPrices(): void
	{
		$t0 = time() - 3600;
		$offer = ['price' => 79.2, 'from' => date('Y-m-d', $t0 - 86400), 'to' => date('Y-m-d', time() + 3 * 86400)];
		$card = self::item('SPOLDCARD', $offer);
		$sale = self::item('SPOLDSALE', $offer);
		self::tick($t0);
		$runs = [];
		foreach ([[$card, 'card', ['sync_price', (string)$card]], [$sale, 'stock', ['sync_vare', (string)$sale, '1', 'stock']]] as [$id, $path, $args]) {
			$prefix = sys_get_temp_dir() . '/shopoffer-stall-' . bin2hex(random_bytes(4));
			self::$files[] = "$prefix.held";
			self::$files[] = "$prefix.go";
			$runs[] = [$id, $prefix, self::start($args, [], "$path:queried:$id:$prefix")];
			for ($i = 0; $i < 600 && !is_file("$prefix.held"); $i++) {
				usleep(50000);
			}
			self::assertFileExists("$prefix.held");
		}
		$marker = sys_get_temp_dir() . '/shopoffer-oldread-' . bin2hex(random_bytes(4));
		self::$files[] = $marker;
		$lock = self::start(['locktable', '45', 'varer', $marker]); // the card's three claims wait 5 s each, then the sale's
		for ($i = 0; $i < 200 && !is_file($marker); $i++) {
			usleep(50000);
		}
		self::assertFileExists($marker, 'varer is locked');
		self::clearLog();
		$results = [];
		foreach ($runs as [$id, $prefix, $run]) {
			file_put_contents("$prefix.go", (string)(time() + 600)); // the path goes on 10 minutes after its read
			$results[] = self::finish($run)['result'];
		}
		proc_terminate($lock[0]);
		proc_close($lock[0]);
		self::assertSame([], self::priceCalls('SPOLDCARD', null), 'the card sent no prices');
		self::assertSame([], self::priceCalls('SPOLDSALE', null), 'nor the sale');
		self::assertSame(['sync errors: 3', 'sync errors: 1'], $results, 'each reported it');
		self::assertNotSame([], (array)glob(self::$tempDir . "/shopsync/*"), 'the sale\'s stock sync is queued');
		self::assertStringContainsString('ITEM NOT READ AGAIN', (string)@file_get_contents(self::$tempDir . '/rest_api.log'));
		foreach ([$card, $sale] as $id) {
			self::assertGreaterThan(0, (int)(self::runner(['state', (string)$id])['state']['foreign']['staleAt'] ?? 0), 'an event for the reconciler');
		}
		self::clearLog();
		$out = self::tick(time() + 700);
		self::assertGreaterThanOrEqual(2, $out['events']);
		$offerPair = ['shop 1' => '79.200 / 799.00', 'shop 2' => '79.200 / 799.00', 'shop 3' => '79.200 / 799.00'];
		$sent = ['shop 1' => ['79.200 / 799.00'], 'shop 2' => ['79.200 / 799.00'], 'shop 3' => ['79.200 / 799.00']];
		self::assertSame([$sent, $sent], [self::pricesSent('SPOLDCARD'), self::pricesSent('SPOLDSALE')], 'the next tick sends both items the current pair');
		self::assertSame([$offerPair, $offerPair], [self::remote('SPOLDCARD'), self::remote('SPOLDSALE')], 'and the shops hold it');
	}

	public function testAnOpenMarkerKeepsTheItemTrackedAndAnExpiredOneIsAnEvent(): void
	{
		$t0 = time() - 2 * 86400;
		$id = self::item('SPDIED', ['price' => 79.2, 'from' => date('Y-m-d', $t0 - 86400), 'to' => date('Y-m-d', time() + 3 * 86400)]);
		self::tick($t0);
		self::rows("UPDATE varer SET specialtype = NULL, special_price = 0 WHERE id = $1", [$id]);
		foreach ([10, 130, 310, 910, 3610, 21610, 43210, 64810, 86410] as $offset) {
			self::tick($t0 + $offset);
		}
		self::assertShopsHold('SPDIED', self::NORMAL);
		// a card save claims the item, writes its marker and dies before it sends
		$prefix = sys_get_temp_dir() . '/shopoffer-stall-' . bin2hex(random_bytes(4));
		self::$files[] = "$prefix.held";
		$run = self::start(['sync_price', (string)$id], [], "card:marked:$id:$prefix");
		for ($i = 0; $i < 600 && !is_file("$prefix.held"); $i++) {
			usleep(50000);
		}
		$claimed = time();
		proc_terminate($run[0], 9);
		proc_close($run[0]);
		self::assertFileExists(self::$tempDir . "/shopoffer/inflight-$id.json", 'its marker stays');
		$markers = json_decode((string)file_get_contents(self::$tempDir . "/shopoffer/inflight-$id.json"), true);
		self::assertCount(1, $markers, 'one marker');
		$marker = array_values($markers)[0];
		self::assertSame('card', $marker['path']);
		self::assertEqualsWithDelta($claimed, $marker['startedAt'], 2);
		self::clearLog();
		self::assertSame(0, self::tick($t0 + 86400 + 800)['untracked'], 'the tail is done, but the marker is open: not untracked');
		self::assertSame(0, self::tick($marker['startedAt'] + 719)['untracked'], 'still open a second before T_late plus 2 minutes');
		self::assertTrue(self::tracked($id));
		self::assertSame([], self::priceCalls(), 'nothing owed meanwhile');
		$out = self::tick($marker['startedAt'] + 720);
		self::assertGreaterThanOrEqual(1, $out['events'], 'expired: an event');
		self::assertSame(self::NORMAL_ALL, self::pricesSent('SPDIED'), 'the item is sent again before it can be untracked');
		self::assertFileDoesNotExist(self::$tempDir . "/shopoffer/inflight-$id.json", 'the expired marker goes');
		self::assertStringContainsString('a send by another path was not recorded in time (card)', (string)self::runner(['state', (string)$id])['state']['event']);
		self::tick($marker['startedAt'] + 720 + 86400 + 700); // the last of the tail, caught up
		self::tick($marker['startedAt'] + 720 + 86400 + 1400);
		self::assertFalse(self::tracked($id), 'then, 24 hours on, untracked');
		self::assertShopsHold('SPDIED', self::NORMAL);
	}
}
