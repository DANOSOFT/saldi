<?php

declare(strict_types=1);

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

/**
 * The webshop offer push (includes/stdFunc/shopOfferPush.php) against a fresh tenant and a fake shop: a loopback-only
 * `php -S` server (support/shop_price_recorder.php) configured as all three webshop endpoints, which records every call and can
 * fail, refuse or answer slowly per shop.
 *
 * Pins that a campaign import pushes the offers that apply now, that the start and the end of an offer are pushed once their
 * time has come (a test clock), that the item card's and a sale's calls send the offer during an offer and are restored after
 * it, that nothing unchanged is sent again, that every shop is reconciled to what it confirmed, that failures and refusals are
 * retried with back-off, that a look keeps its time budget with slow shops, and that the schedule only moves earlier.
 *
 * History:
 * 20261117 CL/LH Created (Havemøbelland Black Week: offers in the webshops).
 * 20261117 CL/LH Codex verification: one regression test per finding (unrecorded card/sale offers, partial delivery, the budget with
 *                slow shops, the schedule race, the retry state after a first failure, a refused restoration).
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
		$env = array_merge(getenv(), ['SALDI_SHOP_RECORDER_LOG' => self::$shopLog, 'PHP_CLI_SERVER_WORKERS' => '8']);
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
		self::rows("UPDATE grupper SET box4 = $1, box5 = $2, box6 = $3 WHERE art = 'API'",
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
		if (self::$tempDir !== '' && is_dir(self::$tempDir)) {
			foreach (array_merge((array)glob(self::$tempDir . '/shopoffer/*'), (array)glob(self::$tempDir . '/shopsync/*'), (array)glob(self::$tempDir . '/*')) as $file) {
				@unlink((string)$file);
			}
			@rmdir(self::$tempDir . '/shopoffer');
			@rmdir(self::$tempDir . '/shopsync');
			@rmdir(self::$tempDir);
		}
		parent::tearDownAfterClass();
	}

	/** Every test starts with no offer anywhere, no push state, an empty shop log and every shop answering at once. */
	protected function setUp(): void
	{
		self::rows("UPDATE varer SET specialtype = NULL, special_price = 0");
		foreach ((array)glob(self::$tempDir . '/shopoffer/*') as $file) {
			@unlink((string)$file);
		}
		foreach ((array)glob(self::$shopLog . '.*') as $file) {
			@unlink((string)$file);
		}
		file_put_contents(self::$shopLog, '');
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

	/** An offer around today: from yesterday until the end of today. */
	private static function todaysOffer(float $price = 159.2): array
	{
		return ['price' => $price, 'from' => date('Y-m-d', time() - 86400), 'to' => date('Y-m-d')];
	}

	/** @param list<int> $prefer */
	private static function tick(int $now, bool $force = false, int $maxItems = 10, float $maxSeconds = 3, array $prefer = []): array
	{
		CharacterizationEnv::resyncSequences(self::$tenant);
		$args = ['tick', (string)$now, $force ? 'force' : '-', (string)$maxItems, (string)$maxSeconds];
		if ($prefer) $args[] = implode(',', $prefer);
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
		self::assertStringNotContainsString('Fatal error', $stdout . $stderr, "runner crashed\n$stdout\n$stderr");
		self::assertStringNotContainsString('shopOffer', $stderr, 'no warning from the offer push');
		$lines = array_values(array_filter(explode("\n", trim($stdout))));
		$out = $lines ? json_decode((string)end($lines), true) : null;
		self::assertIsArray($out, "runner output\nstdout: $stdout\nstderr: $stderr");
		return $out;
	}

	/** @param list<string> $args @return resource The runner started in the background; finish it with finish(). */
	private static function start(array $args)
	{
		$cmd = array_merge([PHP_BINARY, __DIR__ . '/../support/run_shop_offer_push.php', CharacterizationEnv::testDb()], $args);
		return proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
	}

	/** @param resource $proc */
	private static function finish($proc): void
	{
		proc_close($proc);
	}

	private static function control(string $name, string $content = ''): void
	{
		file_put_contents(self::$shopLog . '.' . $name, $content);
	}

	private static function clearLog(): void
	{
		file_put_contents(self::$shopLog, '');
	}

	/** @return list<array<string, mixed>> The update_price calls the fake shop received, optionally for one item. */
	private static function priceCalls(?string $itemNo = null): array
	{
		$calls = [];
		foreach (array_filter(explode("\n", (string)file_get_contents(self::$shopLog))) as $line) {
			$entry = json_decode($line, true);
			$query = $entry['query'];
			if (array_key_exists('update_price', $query) && ($itemNo === null || $query['itemNo'] === $itemNo)) {
				$calls[] = $query + ['_shop' => (string)$entry['shop'], '_status' => (string)$entry['status']];
			}
		}
		return $calls;
	}

	/** @return array<string, list<string>> 'salesPrice / retailPrice' per shop, for one item's update_price calls. */
	private static function pricesSent(string $itemNo): array
	{
		$sent = [];
		foreach (self::priceCalls($itemNo) as $call) {
			$sent['shop ' . $call['_shop']][] = ($call['salesPrice'] ?? '') . ' / ' . ($call['retailPrice'] ?? '');
		}
		ksort($sent);
		return $sent;
	}

	private const OFFER_ALL = ['shop 1' => ['159.200 / 799.00'], 'shop 2' => ['159.200 / 799.00'], 'shop 3' => ['159.200 / 799.00']];
	private const NORMAL_ALL = ['shop 1' => ['639.200 / 899.000'], 'shop 2' => ['639.200 / 899.000'], 'shop 3' => ['639.200 / 899.000']];

	// ------------------------------------------------------------------ what passed before, kept

	public function testACampaignImportPushesTheOfferThatStartsNow(): void
	{
		$now = self::item('SPNOW');
		self::item('SPLATER');
		$today = date('d-m-Y');
		$csv = tempnam(sys_get_temp_dir(), 'offercsv');
		self::$files[] = $csv;
		file_put_contents($csv, "Varenr;Tilbudspris;Start;Slut\r\nSPNOW;199,00;$today;" . date('d-m-Y', strtotime('+3 days')) . "\r\n"
			. "SPLATER;149,00;" . date('d-m-Y', strtotime('+10 days')) . ";" . date('d-m-Y', strtotime('+12 days')) . "\r\n");
		chmod($csv, 0644);
		CharacterizationEnv::resyncSequences(self::$tenant);
		$res = self::runChild('run_importer_varer_page.php', [$csv, self::sessionId(), 'varenr,special_price_incl_moms,special_from_date,special_to_date', 'all_products', 'UTF-8']);
		self::assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout']);
		self::assertSame('159.200', self::one('SELECT special_price FROM varer WHERE id = $1', [$now])['special_price']);
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPNOW'),
			'one push per shop: the offer ex VAT, and the normal price 639.20 incl. VAT as the before-price, as the card shows it');
		self::assertSame([], self::pricesSent('SPLATER'), 'an offer that starts later is not pushed yet');
		self::assertSame(3, count(self::priceCalls()), 'nothing else');
	}

	public function testTheStartAndTheEndOfALaterOfferArePushedWhenTheirTimeComes(): void
	{
		$start = strtotime('2031-03-10 08:00:00');
		$end = strtotime('2031-03-12 18:00:00');
		$id = self::item('SPWEEK', ['price' => 159.2, 'from' => '2031-03-10', 'to' => '2031-03-12', 'fromTime' => '08:00:00', 'toTime' => '18:00:00']);
		$early = self::tick($start - 7200, true);
		self::assertSame(0, $early['pushed']);
		self::assertSame($start - 7200 + 1800, $early['next'], 'far from the start: the half-hourly look');
		$before = self::tick($start - 1200, true);
		self::assertSame($start, $before['next'], 'the next look is due at the start');
		self::assertFalse(self::tick($start - 60)['looked'], 'not due yet: only the small file is read');
		self::assertSame(1, self::tick($start + 60)['pushed'], 'the clock is past the start');
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPWEEK'));
		self::clearLog();
		self::assertSame(1, self::tick($end + 60, true)['pushed'], 'the clock is past the end');
		self::assertSame(self::NORMAL_ALL, self::pricesSent('SPWEEK'), 'the normal price, and the original Vejl.pris as retailPrice');
		self::assertNull(self::runner(['state', (string)$id])['state'], 'every shop confirmed the normal prices: nothing left to watch');
	}

	public function testNothingUnchangedIsSentAgain(): void
	{
		self::item('SPSAME', self::todaysOffer());
		$now = time();
		self::assertFalse(self::tick($now)['looked'], 'dormant until an import, a card save or a sale asks for a look: no query');
		self::assertSame(1, self::tick($now, true)['pushed']);
		self::clearLog();
		$again = self::tick($now + 5, true);
		self::assertTrue($again['looked']);
		self::assertSame(0, $again['pushed']);
		self::assertSame([], self::priceCalls(), 'no call when every shop confirmed the prices');
	}

	public function testTheItemCardsCallsSendTheOfferDuringAnOffer(): void
	{
		$normal = self::item('SPPLAIN');
		self::runner(['sync_vare', (string)$normal, '1', 'stock']);
		self::assertSame(PHP_INT_MAX, self::runner(['due'])['due'], 'a sale of an item without an offer leaves the push dormant');
		self::assertNull(self::runner(['state', (string)$normal])['state']);

		$id = self::item('SPCARD', self::todaysOffer());
		self::clearLog();
		self::runner(['sync_vare', (string)$id, '1']);
		$calls = self::priceCalls('SPCARD');
		self::assertCount(3, $calls, 'update_price to the three shops, as on a save of a stocked item');
		foreach ($calls as $call) {
			self::assertSame(['159.200', '799.00'], [$call['salesPrice'], $call['retailPrice']]);
		}
		$stock = array_filter(array_map(fn($l) => json_decode($l, true)['query'], array_filter(explode("\n", (string)file_get_contents(self::$shopLog)))),
			fn($q) => array_key_exists('update_stock', $q) && ($q['itemNo'] ?? '') === 'SPCARD');
		foreach ($stock as $q) {
			self::assertSame(['159.200', '799.00'], [$q['salesPrice'], $q['retailPrice']], 'the stock call carries the same prices');
		}
		self::clearLog();
		self::runner(['sync_price', (string)$id]);
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPCARD'), 'sync_shop_price(), the call for an item outside a stocked group');
	}

	public function testAFailedDeliveryIsRetriedLater(): void
	{
		$id = self::item('SPFAIL', self::todaysOffer());
		$now = time();
		self::control('fail');
		$failed = self::tick($now, true);
		self::assertSame(1, $failed['failed']);
		self::assertSame($now + 60, $failed['next'], 'retried after a minute');
		self::assertNotSame([], self::priceCalls('SPFAIL'), 'it was tried');
		@unlink(self::$shopLog . '.fail');
		self::clearLog();
		self::assertFalse(self::tick($now + 30)['looked'], 'not before the retry time');
		self::assertSame(1, self::tick($now + 61)['pushed']);
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPFAIL'));
		self::clearLog();
		self::assertSame(0, self::tick($now + 62, true)['pushed'], 'delivered: not sent again');
		self::rows("UPDATE varer SET specialtype = NULL WHERE id = $1", [$id]);
		self::assertSame(1, self::tick($now + 63, true)['pushed'], 'the offer was removed on the card: the normal prices go out');
		self::assertSame(self::NORMAL_ALL, self::pricesSent('SPFAIL'));
		self::assertSame(0, self::tick($now + 64, true)['next'], 'nothing left: dormant again');
		self::assertSame(PHP_INT_MAX, self::runner(['due'])['due']);
	}

	public function testOneLookStopsAtItsBudgetAndTheRestFollows(): void
	{
		$now = time();
		$ids = [];
		for ($i = 1; $i <= 120; $i++) {
			$ids[$i] = self::item(sprintf('SPB%03d', $i), ['price' => 100 + $i, 'from' => date('Y-m-d', $now - 86400), 'to' => date('Y-m-d', $now + 86400)]);
		}
		$timeBound = self::tick($now, true, 200, 0.0);
		self::assertSame([0, 120], [$timeBound['pushed'], $timeBound['pending']], 'a look with no time left starts no item');
		self::assertSame([], self::priceCalls());
		$preferred = self::tick($now, true, 3, 30, [$ids[118], $ids[119], $ids[120]]);
		self::assertSame(3, $preferred['pushed']);
		self::assertSame(['SPB118', 'SPB119', 'SPB120'], array_values(array_unique(array_map(fn($c) => $c['itemNo'], self::priceCalls()))),
			'the items an import has just written go first');
		$first = self::tick($now, true, 25, 30);
		self::assertSame(25, $first['pushed']);
		self::assertSame(92, $first['pending']);
		self::assertSame($now + 30, $first['next'], 'the rest no sooner than half a minute later');
		self::assertSame(84, count(self::priceCalls()), '3 + 25 items to three shops');
		self::assertFalse(self::tick($now + 10)['looked'], 'rate limited');
		$t = $now + 30;
		for ($round = 0; $round < 4; $round++) {
			$t += 30;
			self::tick($t, false, 25, 30);
		}
		self::assertCount(120, array_unique(array_map(fn($c) => $c['itemNo'], self::priceCalls())), 'all 120 items reached the shops');
		self::assertSame(360, count(self::priceCalls()), 'each once per shop');
	}

	// ------------------------------------------------------------------ Codex finding 1: offers sent by other paths

	public function testAnOfferSentByACardSaveIsRestoredWhenItEnds(): void
	{
		$id = self::item('SPF1CARD', self::todaysOffer());
		self::runner(['sync_vare', (string)$id, '1']);
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPF1CARD'), 'the card save sent the offer');
		self::clearLog();
		$look = self::tick(strtotime('tomorrow 00:00:30'));
		self::assertTrue($look['looked'], 'the card save left a look due for the end of the offer');
		self::assertSame(self::NORMAL_ALL, self::pricesSent('SPF1CARD'), 'the end of the offer restores the normal prices in every shop');
		self::assertNull(self::runner(['state', (string)$id])['state']);
	}

	public function testAnOfferSentByASaleIsRestoredWhenItEnds(): void
	{
		self::item('SPF1SALE', self::todaysOffer());
		$id = (int)self::one("SELECT id FROM varer WHERE varenr = 'SPF1SALE'")['id'];
		self::assertSame(PHP_INT_MAX, self::runner(['due'])['due'], 'dormant before the sale');
		self::runner(['sync_vare', (string)$id, '1', 'stock']);
		self::assertLessThanOrEqual(time(), self::runner(['due'])['due'], 'the sale sent the offer without a confirmed price: a look is due at once');
		self::assertSame(1, self::tick(time())['pushed'], 'the look sends update_price, which the shops confirm');
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPF1SALE'));
		self::clearLog();
		self::assertTrue(self::tick(strtotime('tomorrow 00:00:30'))['looked']);
		self::assertSame(self::NORMAL_ALL, self::pricesSent('SPF1SALE'), 'and the end of the offer is restored');
	}

	// ------------------------------------------------------------------ Codex finding 2: partial delivery

	public function testAPartialDeliveryIsCorrectedShopByShop(): void
	{
		$id = self::item('SPF2', self::todaysOffer(79.2));
		$now = time();
		self::assertSame(1, self::tick($now, true)['pushed'], 'offer A to all three shops');
		self::rows("UPDATE varer SET special_price = 59.2 WHERE id = $1", [$id]);
		self::control('fail3');
		self::clearLog();
		self::assertSame(1, self::tick($now + 1, true)['failed'], 'offer B: shops 1 and 2 accept, shop 3 answers 503');
		self::assertSame(['shop 1' => ['59.200 / 799.00'], 'shop 2' => ['59.200 / 799.00'], 'shop 3' => ['59.200 / 799.00']], self::pricesSent('SPF2'));
		self::rows("UPDATE varer SET special_price = 79.2 WHERE id = $1", [$id]);
		@unlink(self::$shopLog . '.fail3');
		self::clearLog();
		self::tick($now + 2, true);
		self::assertSame(['shop 1' => ['79.200 / 799.00'], 'shop 2' => ['79.200 / 799.00']], self::pricesSent('SPF2'),
			'back to A: shops 1 and 2, which confirmed B, get A; shop 3 still has A');
		self::clearLog();
		self::assertSame(0, self::tick($now + 3, true)['pushed'], 'every shop confirmed A');
	}

	// ------------------------------------------------------------------ Codex finding 3: the budget with slow shops

	public function testALookAfterASaleKeepsItsBudgetWithThreeSlowShopsOneAfterTheOther(): void
	{
		$id = self::item('SPF3SEQ', self::todaysOffer());
		self::runner(['schedule', '0']);
		self::control('slow', '3');
		$drain = self::runner(['drain'], ['-d', 'disable_functions=curl_multi_init']);
		self::assertLessThan(1.9, $drain['elapsed'], 'the sale waited at most its 1.5 s budget, three 3 s shops one after the other');
		@unlink(self::$shopLog . '.slow');
		self::clearLog();
		self::assertSame(1, self::tick(time() + 120, true)['pushed'], 'the item is still owed, and delivered once the shops answer');
		self::assertSame(self::OFFER_ALL, self::pricesSent('SPF3SEQ'));
		self::assertNotNull(self::runner(['state', (string)$id])['state']);
	}

	public function testALookAfterASaleKeepsItsBudgetWithThreeSlowShopsAtOnce(): void
	{
		self::item('SPF3PAR', self::todaysOffer());
		self::runner(['schedule', '0']);
		self::control('slow', '3');
		$drain = self::runner(['drain']);
		self::assertLessThan(1.9, $drain['elapsed'], 'at most the 1.5 s budget with curl_multi as well');
	}

	// ------------------------------------------------------------------ Codex finding 4: the schedule

	public function testAnEarlierLookAskedForDuringALookIsKept(): void
	{
		self::item('SPF4', self::todaysOffer());
		self::control('slow', '2');
		$look = self::start(['tick', 'now', 'force', '10', '10']);
		usleep(700000); // the look is waiting for the slow shops
		$asked = time();
		self::runner(['schedule', (string)$asked]);
		self::finish($look);
		self::assertLessThanOrEqual($asked, self::runner(['due'])['due'], 'the look that ended did not move the asked-for look later');
	}

	public function testASchedulingRequestOnlyMovesTheNextLookEarlier(): void
	{
		$t = time() + 3600;
		self::runner(['schedule', (string)($t + 500)]);
		self::runner(['schedule', (string)($t + 100)]);
		self::runner(['schedule', (string)($t + 900)]);
		self::assertSame($t + 100, self::runner(['due'])['due']);
	}

	public function testConcurrentSchedulingRequestsKeepTheEarliest(): void
	{
		$t = time() + 3600;
		$times = [];
		$procs = [];
		for ($i = 0; $i < 12; $i++) {
			$times[] = $when = $t + random_int(1, 5000);
			$procs[] = self::start(['schedule', (string)$when]);
		}
		foreach ($procs as $proc) {
			self::finish($proc);
		}
		self::assertSame(min($times), self::runner(['due'])['due'], 'twelve requests at once: the earliest wins');
	}

	// ------------------------------------------------------------------ Codex finding 5: the retry state after a first failure

	public function testAFirstFailureKeepsItsBackOff(): void
	{
		self::item('SPF5', self::todaysOffer());
		$now = time();
		self::control('fail1');
		self::assertSame(1, self::tick($now, true)['failed'], 'shop 1 answers 503 to the first push');
		self::clearLog();
		self::tick($now + 30, true);
		self::assertSame([], self::pricesSent('SPF5'), 'half a minute later shop 1 still waits for its retry; shops 2 and 3 confirmed');
		@unlink(self::$shopLog . '.fail1');
		self::tick($now + 61, true);
		self::assertSame(['shop 1' => ['159.200 / 799.00']], self::pricesSent('SPF5'), 'after a minute only shop 1 is sent again');
	}

	// ------------------------------------------------------------------ Codex finding 6: refusals

	public function testARefusedRestorationIsRetriedUntilTheShopConfirms(): void
	{
		$id = self::item('SPF6', self::todaysOffer());
		$now = time();
		self::assertSame(1, self::tick($now, true)['pushed']);
		self::control('reject3');
		self::clearLog();
		$end = strtotime('tomorrow 00:00:30');
		self::assertSame(1, self::tick($end, true)['failed'], 'shop 3 refuses the restoration: not counted as delivered');
		@unlink(self::$shopLog . '.reject3');
		self::clearLog();
		self::tick($end + 61);
		self::assertSame(['shop 3' => ['639.200 / 899.000']], self::pricesSent('SPF6'), 'the refused restoration is sent again to shop 3');
		self::assertNull(self::runner(['state', (string)$id])['state'], 'then every shop has the normal prices');
	}

	public function testRepeatedRefusalsStopVisiblyAndStartAgainWhenThePricesChange(): void
	{
		$id = self::item('SPF6GIVE', ['price' => 159.2, 'from' => date('Y-m-d', time() - 86400), 'to' => date('Y-m-d', time() + 3 * 86400)]);
		self::control('reject2');
		$t = time();
		$gaveUp = 0;
		for ($i = 0; $i < 9; $i++) {
			$gaveUp += self::tick($t, true)['gaveUp'];
			$t += 1801;
		}
		self::assertSame(1, $gaveUp, 'shop 2 is given up after eight refusals of the same prices, and that is reported');
		$state = self::runner(['state', (string)$id])['state'];
		$entries = array_filter($state['shops'], fn($e) => !empty($e['gaveUp']));
		self::assertCount(1, $entries, 'the state shows which shop gave up');
		self::assertStringContainsString('rejection', (string)current($entries)['error']);
		self::clearLog();
		self::tick($t, true);
		self::assertSame([], self::pricesSent('SPF6GIVE'), 'no more sends of the same prices');
		self::rows("UPDATE varer SET special_price = 149.2 WHERE id = $1", [$id]);
		self::tick($t + 1, true);
		self::assertSame(['shop 2' => ['149.200 / 799.00']], array_intersect_key(self::pricesSent('SPF6GIVE'), ['shop 2' => 1]),
			'new prices are sent to shop 2 again');
	}

	/** The seeded session id for the page runner (master harness: sessionId(), live harness: SESSION_ID). */
	private static function sessionId(): string
	{
		return method_exists(CharacterizationEnv::class, 'sessionId')
			? CharacterizationEnv::sessionId()
			: (string)constant(CharacterizationEnv::class . '::SESSION_ID');
	}
}
