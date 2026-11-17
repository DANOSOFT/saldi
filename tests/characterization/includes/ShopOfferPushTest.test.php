<?php

declare(strict_types=1);

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

/**
 * The webshop offer push (includes/stdFunc/shopOfferPush.php) against a fresh tenant and a fake shop: a loopback-only
 * `php -S` server (support/shop_price_recorder.php) configured as all three webshop endpoints, which records every call.
 *
 * Pins that a campaign import pushes the offers that apply now, that the start and the end of an offer are pushed once
 * their time has come (a test clock), that the item card's calls send the offer during an offer, that nothing unchanged is
 * sent again, that a failed delivery is retried later, and that one look stops at its item budget with 100+ items.
 *
 * History:
 * 20261117 CL/LH Created (Havemøbelland Black Week: offers in the webshops).
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
		$port = (int)substr((string)stream_socket_get_name($probe, false), strrpos((string)stream_socket_get_name($probe, false), ':') + 1);
		fclose($probe);
		$env = array_merge(getenv(), ['SALDI_SHOP_RECORDER_LOG' => self::$shopLog, 'PHP_CLI_SERVER_WORKERS' => '4']);
		self::$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/../support/shop_price_recorder.php'],
			[0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env);
		for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1); $i++) {
			usleep(100000);
		}
		$db = CharacterizationEnv::testDb();
		self::$tempDir = CharacterizationEnv::repoRoot() . '/temp/' . $db;
		@mkdir(self::$tempDir, 0777, true);
		$shop = "http://127.0.0.1:$port/shop.php";
		if (self::one("SELECT id FROM grupper WHERE art = 'API'") === null) {
			self::rows("INSERT INTO grupper (id, beskrivelse, kodenr, art, fiscal_year) VALUES ($1, 'API', '1', 'API', 0)", [CharacterizationEnv::nextId(self::$tenant, 'grupper')]);
		}
		self::rows("UPDATE grupper SET box4 = $1, box5 = $2, box6 = $3 WHERE art = 'API'", ["$shop?shop=1", "$shop?shop=2", "$shop?shop=3"]);
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
		foreach (array_merge(self::$files, [self::$shopLog, self::$shopLog . '.fail']) as $file) {
			@unlink($file);
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

	/** Every test starts with no offer anywhere, no push state and an empty shop log. */
	protected function setUp(): void
	{
		self::rows("UPDATE varer SET specialtype = NULL, special_price = 0");
		foreach ((array)glob(self::$tempDir . '/shopoffer/*') as $file) {
			@unlink((string)$file);
		}
		file_put_contents(self::$shopLog, '');
		@unlink(self::$shopLog . '.fail');
	}

	/**
	 * An item with its offer; dates relative to $now unless given as Y-m-d.
	 */
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

	/** @return array<string, mixed> */
	private static function tick(int $now, bool $force = false, int $maxItems = 10, float $maxSeconds = 3): array
	{
		CharacterizationEnv::resyncSequences(self::$tenant);
		return self::runner(['tick', (string)$now, $force ? 'force' : '-', (string)$maxItems, (string)$maxSeconds]);
	}

	/** @param list<string> $args @return array<string, mixed> */
	private static function runner(array $args): array
	{
		$res = self::runChild('run_shop_offer_push.php', array_merge([CharacterizationEnv::testDb()], $args));
		self::assertStringNotContainsString('shopOffer', $res['stderr'], 'no warning from the offer push');
		$lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
		$out = $lines ? json_decode((string)end($lines), true) : null;
		self::assertIsArray($out, "runner output\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
		return $out;
	}

	/** @return list<array<string, string>> The update_price calls the fake shop received, optionally for one item. */
	private static function priceCalls(?string $itemNo = null): array
	{
		$calls = [];
		foreach (array_filter(explode("\n", (string)file_get_contents(self::$shopLog))) as $line) {
			$entry = json_decode($line, true);
			$query = $entry['query'];
			if (array_key_exists('update_price', $query) && ($itemNo === null || $query['itemNo'] === $itemNo)) {
				$calls[] = $query + ['_status' => (string)$entry['status']];
			}
		}
		return $calls;
	}

	/** @return array<string, list<string>> salesPrice and retailPrice per shop, for one item. */
	private static function pricesSent(string $itemNo): array
	{
		$sent = [];
		foreach (self::priceCalls($itemNo) as $call) {
			$sent['shop ' . $call['shop']][] = $call['salesPrice'] . ' / ' . $call['retailPrice'];
		}
		ksort($sent);
		return $sent;
	}

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
		self::assertSame(['shop 1' => ['159.200 / 799.00'], 'shop 2' => ['159.200 / 799.00'], 'shop 3' => ['159.200 / 799.00']], self::pricesSent('SPNOW'),
			'one push per shop: the offer ex VAT, and the normal price 639.20 incl. VAT as the before-price, as the card shows it');
		self::assertSame([], self::pricesSent('SPLATER'), 'an offer that starts later is not pushed yet');
		self::assertSame(3, count(self::priceCalls()), 'nothing else');
	}

	public function testTheStartAndTheEndOfALaterOfferArePushedWhenTheirTimeComes(): void
	{
		$start = strtotime('2031-03-10 08:00:00');
		$end = strtotime('2031-03-12 18:00:00');
		self::item('SPWEEK', ['price' => 159.2, 'from' => '2031-03-10', 'to' => '2031-03-12', 'fromTime' => '08:00:00', 'toTime' => '18:00:00']);
		$early = self::tick($start - 7200, true);
		self::assertSame(0, $early['pushed']);
		self::assertSame($start - 7200 + 1800, $early['next'], 'far from the start: the half-hourly look');
		$before = self::tick($start - 1200, true);
		self::assertSame($start, $before['next'], 'the next look is due at the start');
		self::assertSame(0, self::tick($start - 60)['pushed'], 'not due yet: only the small file is read');
		self::assertFalse(self::tick($start - 60)['looked']);
		self::assertSame(1, self::tick($start + 60)['pushed'], 'the clock is past the start');
		self::assertSame(['shop 1' => ['159.200 / 799.00'], 'shop 2' => ['159.200 / 799.00'], 'shop 3' => ['159.200 / 799.00']], self::pricesSent('SPWEEK'));

		file_put_contents(self::$shopLog, '');
		self::assertSame(1, self::tick($end + 60, true)['pushed'], 'the clock is past the end');
		self::assertSame(['shop 1' => ['639.200 / 899.000'], 'shop 2' => ['639.200 / 899.000'], 'shop 3' => ['639.200 / 899.000']], self::pricesSent('SPWEEK'),
			'the normal price, and the original Vejl.pris as retailPrice');
		self::assertFileDoesNotExist(self::$tempDir . '/shopoffer/p' . self::one("SELECT id FROM varer WHERE varenr = 'SPWEEK'")['id'], 'nothing left to watch');
	}

	public function testNothingUnchangedIsSentAgain(): void
	{
		$now = time();
		self::item('SPSAME', ['price' => 159.2, 'from' => date('Y-m-d', $now - 86400), 'to' => date('Y-m-d', $now + 86400)]);
		self::assertFalse(self::tick($now)['looked'], 'dormant until an import or a card save asks for a look: no query');
		self::assertSame(1, self::tick($now, true)['pushed']);
		file_put_contents(self::$shopLog, '');
		$again = self::tick($now + 5, true);
		self::assertTrue($again['looked']);
		self::assertSame(0, $again['pushed']);
		self::assertSame([], self::priceCalls(), 'no call when nothing changed');
	}

	public function testTheItemCardsCallsSendTheOfferDuringAnOffer(): void
	{
		$now = time();
		$id = self::item('SPCARD', ['price' => 159.2, 'from' => date('Y-m-d', $now - 86400), 'to' => date('Y-m-d', $now + 86400)]);
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
		file_put_contents(self::$shopLog, '');
		self::runner(['sync_price', (string)$id]);
		self::assertSame(['shop 1' => ['159.200 / 799.00'], 'shop 2' => ['159.200 / 799.00'], 'shop 3' => ['159.200 / 799.00']], self::pricesSent('SPCARD'),
			'sync_shop_price(), the call for an item outside a stocked group');
		self::rows("UPDATE varer SET specialtype = NULL WHERE id = $1", [$id]);
		file_put_contents(self::$shopLog, '');
		self::runner(['sync_price', (string)$id]);
		self::assertSame(['shop 1' => ['639.200 / 899.000'], 'shop 2' => ['639.200 / 899.000'], 'shop 3' => ['639.200 / 899.000']], self::pricesSent('SPCARD'),
			'without an offer the stored prices, as before');
	}

	public function testAFailedDeliveryIsRetriedLater(): void
	{
		$now = time();
		self::item('SPFAIL', ['price' => 159.2, 'from' => date('Y-m-d', $now - 86400), 'to' => date('Y-m-d', $now + 86400)]);
		touch(self::$shopLog . '.fail');
		$failed = self::tick($now, true);
		self::assertSame(1, $failed['failed']);
		self::assertSame($now + 60, $failed['next'], 'retried after a minute');
		self::assertNotSame([], self::priceCalls('SPFAIL'), 'it was tried');
		unlink(self::$shopLog . '.fail');
		file_put_contents(self::$shopLog, '');
		self::assertFalse(self::tick($now + 30)['looked'], 'not before the retry time');
		$retried = self::tick($now + 61);
		self::assertSame(1, $retried['pushed']);
		self::assertSame(['shop 1' => ['159.200 / 799.00'], 'shop 2' => ['159.200 / 799.00'], 'shop 3' => ['159.200 / 799.00']], self::pricesSent('SPFAIL'));
		file_put_contents(self::$shopLog, '');
		self::assertSame(0, self::tick($now + 62, true)['pushed'], 'delivered: not sent again');
		self::rows("UPDATE varer SET specialtype = NULL WHERE varenr = 'SPFAIL'");
		file_put_contents(self::$shopLog, '');
		self::assertSame(1, self::tick($now + 63, true)['pushed'], 'the offer was removed on the card: the normal prices go out');
		self::assertSame(['shop 1' => ['639.200 / 899.000'], 'shop 2' => ['639.200 / 899.000'], 'shop 3' => ['639.200 / 899.000']], self::pricesSent('SPFAIL'));
		self::assertSame(0, self::tick($now + 64, true)['next'], 'nothing left: dormant again');
		self::assertFileDoesNotExist(self::$tempDir . '/shopoffer/next');
	}

	public function testOneLookStopsAtItsBudgetAndTheRestFollows(): void
	{
		$now = time();
		$from = date('Y-m-d', $now - 86400);
		$to = date('Y-m-d', $now + 86400);
		for ($i = 1; $i <= 120; $i++) {
			self::item(sprintf('SPB%03d', $i), ['price' => 100 + $i, 'from' => $from, 'to' => $to]);
		}
		$timeBound = self::tick($now, true, 200, 0.0);
		self::assertSame([0, 120], [$timeBound['pushed'], $timeBound['pending']], 'a look with no time left starts no item');
		self::assertSame([], self::priceCalls());
		$first = self::tick($now, true, 25, 30);
		self::assertSame(25, $first['pushed']);
		self::assertSame(95, $first['pending']);
		self::assertSame($now + 30, $first['next'], 'the rest no sooner than half a minute later');
		self::assertSame(75, count(self::priceCalls()), '25 items to three shops');
		self::assertFalse(self::tick($now + 10)['looked'], 'rate limited');
		$t = $now + 30;
		for ($round = 0; $round < 4; $round++) {
			$t += 30;
			self::tick($t, false, 25, 30);
		}
		$items = array_unique(array_map(fn($c) => $c['itemNo'], self::priceCalls()));
		self::assertCount(120, $items, 'all 120 items reached the shops');
		self::assertSame(360, count(self::priceCalls()), 'each once per shop');
	}

	/** The seeded session id for the page runner (master harness: sessionId(), live harness: SESSION_ID). */
	private static function sessionId(): string
	{
		return method_exists(CharacterizationEnv::class, 'sessionId')
			? CharacterizationEnv::sessionId()
			: (string)constant(CharacterizationEnv::class . '::SESSION_ID');
	}
}
