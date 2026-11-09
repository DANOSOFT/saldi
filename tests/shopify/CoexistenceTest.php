<?php
// tests/shopify/CoexistenceTest.php
//
// includes/shopify/coexistence.php (SD-750, WP15a, K06): the per-slot legacy switches. The
// functions run in a child process against scripted query answers (run_legacy_shop_sync.php),
// the legacy shop calls go to a local stand-in shop (LegacyShopStub), and the slot locks live in
// a throwaway temp/<db>/ folder. No database.
//
// History:
// 20261026 CL/SZ Created (SD-750).

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../characterization/support/CharacterizationEnv.php';
require_once __DIR__ . '/../characterization/support/LegacyShopStub.php';
require_once __DIR__ . '/../../includes/shopify/coexistence.php';

final class CoexistenceTest extends TestCase
{
	private static LegacyShopStub $shop;
	private string $work;
	private string $db;

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
		$this->db = 'coexist_' . bin2hex(random_bytes(4));
		$this->work = sys_get_temp_dir() . '/legacy-coexist-' . $this->db;
		mkdir($this->work . '/page', 0777, true);
		mkdir($this->work . '/temp/' . $this->db, 0777, true);
		mkdir($this->lockDir(), 0777, true);
	}

	protected function tearDown(): void
	{
		exec('rm -rf ' . escapeshellarg($this->work) . ' ' . escapeshellarg($this->lockDir()));
	}

	// ---- settings ------------------------------------------------------------------------------

	public static function settingValues(): array
	{
		return [
			'no row'      => [null, true],
			'on'          => ['on', true],
			'one'         => ['1', true],
			'empty'       => ['', true],
			'zero'        => ['0', false],
			'off'         => ['off', false],
			'OFF padded'  => [' OFF ', false],
			'false'       => ['false', false],
			'nej'         => ['nej', false],
		];
	}

	#[DataProvider('settingValues')]
	public function testOnlyAnExplicitOffClosesAFlowAndAMissingRowMeansOn(?string $value, bool $open): void
	{
		$rows = $value === null ? [] : [$this->setting(5, ['intake_enabled' => $value])];

		$out = $this->legacy([['shopify_legacy_enabled', [['db' => $this->db], 5, 'intake']], ['shopify_legacy_enabled', [['db' => $this->db], 5, 'outbound']]], $rows);

		$this->assertSame([$open, true], $out['return'], 'outbound has its own setting');
	}

	public function testEveryOnOffCombinationPerSlotAndFlow(): void
	{
		$api = $this->api(true, true, true);
		for ($mask = 0; $mask < 64; $mask++) {
			$settings = [];
			$expected = ['intake' => [], 'outbound' => []];
			foreach ([4, 5, 6] as $i => $slot) {
				$intake = (bool)($mask & (1 << $i));
				$outbound = (bool)($mask & (1 << ($i + 3)));
				$settings[] = $this->setting($slot, ['intake_enabled' => $intake ? 'on' : 'off', 'outbound_enabled' => $outbound ? 'on' : 'off']);
				if ($intake) {
					$expected['intake'][$slot] = self::$shop->url(['a', 'b', 'c'][$i]);
				}
				if ($outbound) {
					$expected['outbound'][$slot] = self::$shop->url(['a', 'b', 'c'][$i]);
				}
			}

			$out = $this->legacy([['shopify_legacy_endpoints', [['db' => $this->db], 'intake']], ['shopify_legacy_endpoints', [['db' => $this->db], 'outbound']]], array_merge([$api], $settings));

			$this->assertSame([$expected['intake'], $expected['outbound']], $out['return'], "mask $mask: keyed by the original slot, never renumbered");
		}
	}

	public function testAnEmptyBox4NoLongerHidesBox5AndBox6(): void
	{
		$out = $this->legacy([['shopify_legacy_endpoints', [['db' => $this->db], 'outbound']]], [$this->api(false, true, true)]);

		$this->assertSame([[5 => self::$shop->url('b'), 6 => self::$shop->url('c')]], $out['return']);
	}

	// ---- a closed slot dispatches nothing; the others are unchanged -----------------------------

	public function testAClosedOutboundSlotGetsNoPriceAndTheOthersTheSamePayload(): void
	{
		$out = $this->legacy('sync_shop_price', [7], [$this->api(true, true, true), $this->setting(5, ['outbound_enabled' => '0', 'intake_enabled' => '0']), $this->item()]);

		$this->assertSame('OK', $out['return']);
		$sent = self::$shop->requests();
		$this->assertSame(['/a/', '/c/'], array_column($sent, 'path'));
		foreach ($sent as $request) {
			$this->assertSame(['update_price' => '', 'salesPrice' => '20', 'discountType' => '', 'discount' => '0', 'itemNo' => 'V7', 'rand' => '',
				'costPrice' => '10', 'retailPrice' => '25', 'webFragt' => '', 'barcode' => '123'], $request['query']);
		}
	}

	public function testItemSyncSkipsAClosedSlotForPriceSkuAndStock(): void
	{
		$rows = [
			$this->api(true, true, true),
			$this->setting(4, ['outbound_enabled' => 'off']),
			["/delvare,gruppe from varer where id='7'/", [['delvare' => '', 'gruppe' => '3']]],
			["/art = 'VG'/", [['box8' => 'on']]],
			["/from lagerstatus,varer where lagerstatus.vare_id='7'/", [['varenr' => 'V7', 'varenr_alias' => 'A7', 'kostpris' => '10', 'stock' => '4']]],
			["/shop_id from shop_varer where saldi_id='7'/", [['shop_id' => '501']]],
			$this->item(),
		];

		$out = $this->legacy('sync_shop_vare', [7, 0, 1], $rows);

		$this->assertSame('OK', $out['return']);
		// price to 5 and 6, sku to 5 only (never 6), stock to 5 and 6
		$this->assertSame(['/b/', '/c/', '/b/', '/b/', '/c/'], array_column(self::$shop->requests(), 'path'));
	}

	public function testEveryOutboundSlotClosedSendsNothing(): void
	{
		$closed = array_map(fn(int $slot): array => $this->setting($slot, ['outbound_enabled' => 'off']), [4, 5, 6]);

		$out = $this->legacy([['sync_shop_price', [7]], ['sync_shop_vare', [7, 0, 1]]], array_merge([$this->api(true, true, true), $this->item()], $closed));

		$this->assertSame(['no api', 'no api'], $out['return']);
		$this->assertSame([], self::$shop->requests());
	}

	public function testAClosedIntakeSlotIsNotAskedForOrdersAndOutboundIsUntouched(): void
	{
		$rows = [$this->api(true, true, true), $this->setting(4, ['intake_enabled' => 'off']), $this->item()];

		$this->legacy([['hent_shop_ordrer', ['', '']], ['sync_shop_price', [7]]], $rows);

		$sent = self::$shop->waitFor(5);
		$intake = array_values(array_filter($sent, fn(array $r): bool => isset($r['query']['put_new_orders'])));
		$outbound = array_values(array_filter($sent, fn(array $r): bool => isset($r['query']['update_price'])));
		$paths = array_column($intake, 'path');
		sort($paths);
		$this->assertSame(['/b/', '/c/'], $paths);
		$this->assertSame(['/a/', '/b/', '/c/'], array_column($outbound, 'path'));
	}

	// ---- admission -----------------------------------------------------------------------------

	public function testAnAfdWithoutASlotIsAlwaysAdmitted(): void
	{
		$out = $this->legacy('shopify_legacy_admission', [['db' => $this->db], 0, 3, 'insert_shop_order', '9'], []);

		$this->assertSame(['admitted' => true, 'reason' => 'no_slot', 'slot' => 0, 'afd' => 3, 'action' => 'insert_shop_order'], $out['return']);
	}

	public function testAnOpenSlotAdmitsAndAClosedOneRefuses(): void
	{
		$out = $this->legacy([
			['shopify_legacy_admission', [['db' => $this->db], 4, 1, 'insert_shop_order', '9']],
			['shopify_legacy_admission', [['db' => $this->db], 5, 2, 'insert_shop_order', '9']],
		], [$this->setting(5, ['intake_enabled' => 'off'])]);

		$this->assertSame([[true, 'open'], [false, 'closed']], array_map(fn(array $r): array => [$r['admitted'], $r['reason']], $out['return']));
	}

	public function testTheDrainManifestAdmitsOnlyItsOwnOrdersUntilDrainUntil(): void
	{
		$now = 1800000000;
		$settings = $this->setting(5, ['intake_enabled' => 'off', 'drain_until' => (string)($now + 1800), 'drain_manifest' => '101, 102,0103']);
		$admit = fn(?string $id, int $at): array => ['shopify_legacy_admission', [['db' => $this->db, 'now' => $at], 5, 2, 'insert_shop_order', $id]];

		$out = $this->legacy([$admit('102', $now), $admit(' 101 ', $now), $admit('103', $now), $admit('9', $now), $admit(null, $now), $admit('102', $now + 1800)], [$settings]);

		$this->assertSame(
			[[true, 'drain'], [true, 'drain'], [false, 'closed'], [false, 'closed'], [false, 'closed'], [false, 'closed']],
			array_map(fn(array $r): array => [$r['admitted'], $r['reason']], $out['return']),
			'exact ids after trim, never a numeric match ("0103" is not 103), and nothing once drain_until has passed'
		);
	}

	public function testACutoverInProgressMeansBusyNotClosed(): void
	{
		$fh = fopen($this->lockDir() . '/.ht_shopify_legacy_5.lock', 'c');
		$this->assertTrue(flock($fh, LOCK_EX));
		try {
			$started = microtime(true);
			$out = $this->legacy('shopify_legacy_admission', [['db' => $this->db, 'lock_wait' => 0.3], 5, 2, 'insert_shop_order', '9'], []);
			$this->assertLessThan(3, microtime(true) - $started, 'the wait is bounded');
		} finally {
			flock($fh, LOCK_UN);
			fclose($fh);
		}

		$this->assertSame([false, 'busy'], [$out['return']['admitted'], $out['return']['reason']]);
	}

	public function testACutoverWaitsForAdmittedRequestsAndRefusesWhileTheyRun(): void
	{
		$fh = fopen($this->lockDir() . '/.ht_shopify_legacy_4.lock', 'c');
		$this->assertTrue(flock($fh, LOCK_SH), 'an admitted request holds the shared lock');
		try {
			$busy = $this->legacy('shopify_legacy_cutover', [['db' => $this->db, 'lock_wait' => 0.3], 4, 'time'], []);
		} finally {
			flock($fh, LOCK_UN);
			fclose($fh);
		}
		$free = $this->legacy('shopify_legacy_cutover', [['db' => $this->db, 'lock_wait' => 0.3], 4, 'time'], []);
		$badSlot = $this->legacy('shopify_legacy_cutover', [['db' => $this->db], 7, 'time'], []);

		$this->assertSame('RuntimeException', $busy['return']['exception']);
		$this->assertStringContainsString('admitted requests still running', $busy['return']['message']);
		$this->assertIsInt($free['return'], 'the operation ran under the exclusive lock');
		$this->assertSame('InvalidArgumentException', $badSlot['return']['exception']);
	}

	public function testAnAdmittedRequestAndARunningSyncKeepTheirLockUntilTheyEnd(): void
	{
		$cases = [
			'intake admission' => ['shopify_legacy_admission', [['db' => $this->db], 5, 2, 'insert_shop_order', '9']],
			'outbound sync'    => ['shopify_legacy_endpoints', [['db' => $this->db], 'outbound']],
		];
		foreach ($cases as $name => $call) {
			$spec = $this->work . "/hold-$name.json";
			file_put_contents($spec, json_encode(['work' => $this->work, 'db' => $this->db, 'regnaar' => 1, 'rows' => [$this->api(true, true, true)],
				'calls' => [$call, ['usleep', [1500000]]]]));
			$child = proc_open([PHP_BINARY, __DIR__ . '/../characterization/support/run_legacy_shop_sync.php', $spec], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
			try {
				$probe = fopen($this->lockDir() . '/.ht_shopify_legacy_5.lock', 'c');
				$until = microtime(true) + 3;
				while (flock($probe, LOCK_EX | LOCK_NB) && microtime(true) < $until) {
					flock($probe, LOCK_UN);
					usleep(20000);
				}
				fclose($probe);

				$cutover = $this->legacy('shopify_legacy_cutover', [['db' => $this->db, 'lock_wait' => 0.3], 5, 'time'], []);
			} finally {
				proc_close($child);
			}
			$after = $this->legacy('shopify_legacy_cutover', [['db' => $this->db, 'lock_wait' => 0.3], 5, 'time'], []);

			$this->assertStringContainsString('admitted requests still running', $cutover['return']['message'] ?? '', "$name: the call returned but its request still holds the slot");
			$this->assertIsInt($after['return'], "$name: released when the request ended");
		}
	}

	public function testWithoutAUsableLockFileAnOpenSlotStillAdmits(): void
	{
		$out = $this->legacy([
			['shopify_legacy_admission', [['db' => 'no_such_tenant_dir'], 4, 1, 'insert_shop_order', '9']],
			['shopify_legacy_admission', [['db' => 'no_such_tenant_dir'], 5, 2, 'insert_shop_order', '9']],
			['shopify_legacy_cutover', [['db' => 'no_such_tenant_dir'], 4, 'time']],
		], [$this->setting(5, ['intake_enabled' => 'off'])]);

		$this->assertSame([true, 'no_lock'], [$out['return'][0]['admitted'], $out['return'][0]['reason']]);
		$this->assertSame([false, 'closed'], [$out['return'][1]['admitted'], $out['return'][1]['reason']]);
		$this->assertStringContainsString('lock file not usable', $out['return'][2]['message'], 'a cutover never goes ahead without the lock');
	}

	// ---- slot of an afd, effective afd --------------------------------------------------------

	public function testTheSlotOfAnAfdIsOnlyReadWhenTheShopifySchemaIsInstalled(): void
	{
		$marker = ["/var_grp = 'shopify' and var_name = 'schema_version'/", [['var_value' => '1']]];
		$shop = ["/legacy_slot from shopify_shops where afd = '2'/", [['legacy_slot' => '5']]];

		$none = $this->legacy('shopify_legacy_slot_for_afd', [2], [$shop]);
		$installed = $this->legacy('shopify_legacy_slot_for_afd', [2], [$marker, $shop]);
		$mysql = $this->runSpec(['call' => 'shopify_legacy_slot_for_afd', 'args' => [2], 'rows' => [$marker, $shop], 'db_type' => 'mysql']);

		$this->assertSame(0, $none['return']);
		$this->assertNotContains("select legacy_slot from shopify_shops where afd = '2'", $none['sql'], 'no statement against a table that may not exist');
		$this->assertSame(5, $installed['return']);
		$this->assertSame([0, []], [$mysql['return'], $mysql['sql']], 'the Shopify module is PostgreSQL only');
	}

	public function testOrdersFromTheFixedIpLandInAfd4(): void
	{
		$this->assertSame(4, shopify_legacy_effective_afd(2, '172.105.246.144'));
		$this->assertSame(2, shopify_legacy_effective_afd(2, '10.0.0.1'));
		$this->assertSame(0, shopify_legacy_effective_afd(0, ''));
	}

	// ---- helpers -------------------------------------------------------------------------------

	private function lockDir(): string
	{
		return dirname(__DIR__, 2) . '/temp/' . $this->db;
	}

	private function api(bool $four, bool $five, bool $six): array
	{
		return ["/art='API'/", [[
			'box4' => $four ? self::$shop->url('a') : '',
			'box5' => $five ? self::$shop->url('b') : '',
			'box6' => $six ? self::$shop->url('c') : '',
		]]];
	}

	/** @param array<string,string> $values */
	private function setting(int $slot, array $values): array
	{
		$rows = [];
		foreach ($values as $name => $value) {
			$rows[] = ['var_name' => $name, 'var_value' => $value];
		}
		return ["/var_grp = 'shopify_legacy:$slot'/", $rows];
	}

	private function item(): array
	{
		return ["/FROM varer WHERE varer.id='7'/", [['varenr' => 'V7', 'varenr_alias' => 'A7', 'kostpris' => '10', 'salgspris' => '20', 'm_type' => '',
			'm_rabat' => '0', 'retail_price' => '25', 'colli_webfragt' => '', 'stregkode' => '123']]];
	}

	/**
	 * Runs one function ($calls a name, then $args) or several ($calls a list of [name, args]).
	 *
	 * @return array{return:mixed, sql:list<string>}
	 */
	private function legacy(string|array $calls, array $argsOrRows, ?array $rows = null): array
	{
		return is_string($calls)
			? $this->runSpec(['call' => $calls, 'args' => $argsOrRows, 'rows' => $rows ?? []])
			: $this->runSpec(['calls' => $calls, 'rows' => $argsOrRows]);
	}

	private function runSpec(array $spec): array
	{
		$file = $this->work . '/spec.json';
		file_put_contents($file, json_encode($spec + ['work' => $this->work, 'db' => $this->db, 'regnaar' => 1]));
		$res = CharacterizationEnv::runChild(__DIR__ . '/../characterization/support/run_legacy_shop_sync.php', [$file]);
		$lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
		$out = $lines !== [] ? json_decode(end($lines), true) : null;
		$this->assertIsArray($out, "runner failed (exit {$res['exit']}).\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
		return $out;
	}
}
