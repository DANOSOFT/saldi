<?php
// tests/characterization/api/VaresyncTest.test.php
//
// The shop item sync (api/varesync.php), run end to end against a local stub shop.
//
// varesync() fetches shop_products.csv and shop_variants.csv with wget from the folder of the
// API URL in grupper art='API' box4. The test starts `php -S` on a free port with
// support/varesync_stub_router.php, writes the two CSVs into the stub folder, points box4 at it
// and runs varesync() in a child process (support/run_varesync.php).
//
// Pinned (MB-145):
// - A variant label that exists under one category is created again under another category,
//   and the variant stores the type of its own category instead of 0.
// - A label that already exists under the same category is reused, not created twice.
// - The last stock message names the item, even after the variant step has read its own file.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261125 CL/SZ MB-145 Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class VaresyncTest extends CharacterizationTestCase
{
	private const PRODUCT_HEADER = 'id;varenr;stregkode;salgspris;beskrivelse;gruppe;tilbud;notes';
	private const VARIANT_HEADER = 'varenr;parent_id;variant_id;stregkode;variant;variant_type;variant_text';

	/** @var resource|null */
	private static $server;
	private static string $stubDir = '';
	private static int $group = 0;
	private static int $sizeId = 0;
	private static int $lengthId = 0;
	private static int $smallUnderSize = 0;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$stubDir = sys_get_temp_dir() . '/varesync_stub_' . getmypid();
		@mkdir(self::$stubDir, 0777, true);
		$port = self::freePort();
		self::$server = proc_open(
			[PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/../support/varesync_stub_router.php'],
			[0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
			$pipes,
			self::$stubDir
		);
		self::waitForServer($port);

		self::rows("DELETE FROM grupper WHERE art = 'API'");
		self::rows(
			"INSERT INTO grupper (beskrivelse, kodenr, art, box4) VALUES ('API', '1', 'API', $1)",
			["http://127.0.0.1:$port/api.php"]
		);

		$g = self::one(
			"SELECT kodenr FROM grupper WHERE art = 'VG' AND fiscal_year = $1 ORDER BY kodenr LIMIT 1",
			[self::$regnaar]
		);
		self::$group = (int)$g['kodenr'];

		self::$sizeId = (int)self::one("INSERT INTO varianter (beskrivelse) VALUES ('Size') RETURNING id")['id'];
		self::$lengthId = (int)self::one("INSERT INTO varianter (beskrivelse) VALUES ('Length') RETURNING id")['id'];
		self::$smallUnderSize = (int)self::one(
			"INSERT INTO variant_typer (beskrivelse, variant_id) VALUES ('Small', $1) RETURNING id",
			[self::$sizeId]
		)['id'];
	}

	public static function tearDownAfterClass(): void
	{
		if (self::$server) {
			proc_terminate(self::$server);
			proc_close(self::$server);
			self::$server = null;
		}
		foreach (glob(self::$stubDir . '/*') ?: [] as $f) {
			unlink($f);
		}
		@rmdir(self::$stubDir);
		parent::tearDownAfterClass();
	}

	private static function freePort(): int
	{
		$sock = stream_socket_server('tcp://127.0.0.1:0');
		$name = stream_socket_get_name($sock, false);
		fclose($sock);
		return (int)substr($name, strrpos($name, ':') + 1);
	}

	private static function waitForServer(int $port): void
	{
		for ($i = 0; $i < 50; $i++) {
			$fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
			if ($fp) {
				fclose($fp);
				return;
			}
			usleep(100000);
		}
		self::fail("stub shop on port $port did not start");
	}

	/** @param list<string> $productRows @param list<string> $variantRows */
	private function sync(string $productHeader, array $productRows, array $variantRows, string $eol = "\n"): string
	{
		file_put_contents(
			self::$stubDir . '/shop_products.csv',
			implode($eol, array_merge([$productHeader], $productRows)) . $eol
		);
		file_put_contents(
			self::$stubDir . '/shop_variants.csv',
			implode("\n", array_merge([self::VARIANT_HEADER], $variantRows)) . "\n"
		);
		$out = $this->runChildJson('run_varesync.php', ['1', CharacterizationEnv::testDb()]);
		return (string)$out['printed'];
	}

	private function itemId(string $varenr): int
	{
		$r = self::one('SELECT id FROM varer WHERE varenr = $1', [$varenr]);
		$this->assertNotNull($r, "item $varenr was created");
		return (int)$r['id'];
	}

	/** @return list<array<string,string>> */
	private function variantsOf(int $itemId): array
	{
		return self::rows(
			'SELECT variant_type, variant_stregkode FROM variant_varer WHERE vare_id = $1 ORDER BY id',
			[$itemId]
		);
	}

	public function test_a_label_under_another_category_gets_its_own_type(): void
	{
		$g = self::$group;
		$this->sync(self::PRODUCT_HEADER, ["501;MB145LEN;;100;Length item;$g;;"], [
			'MB145LEN;501;9501;MB145LENSMALL;Small;Length;Small length',
		]);

		$types = self::rows(
			"SELECT id FROM variant_typer WHERE beskrivelse = 'Small' AND variant_id = $1",
			[self::$lengthId]
		);
		$this->assertCount(1, $types, 'Small is created under Length, although it already exists under Size');

		$variants = $this->variantsOf($this->itemId('MB145LEN'));
		$this->assertCount(1, $variants, 'the variant is saved');
		$this->assertSame(
			(string)$types[0]['id'],
			(string)$variants[0]['variant_type'],
			'the variant stores the Length/Small type, not 0'
		);
	}

	public function test_a_label_under_the_same_category_is_reused(): void
	{
		$g = self::$group;
		$this->sync(self::PRODUCT_HEADER, ["502;MB145SIZE;;100;Size item;$g;;"], [
			'MB145SIZE;502;9502;MB145SIZESMALL;Small;Size;Small size',
		]);

		$types = self::rows(
			"SELECT id FROM variant_typer WHERE beskrivelse = 'Small' AND variant_id = $1",
			[self::$sizeId]
		);
		$this->assertCount(1, $types, 'no second Size/Small row');

		$variants = $this->variantsOf($this->itemId('MB145SIZE'));
		$this->assertCount(1, $variants, 'the variant is saved');
		$this->assertSame((string)self::$smallUnderSize, (string)$variants[0]['variant_type'], 'it uses the existing type');
	}

	public function test_the_last_stock_message_names_the_item(): void
	{
		$g = self::$group;
		$printed = $this->sync(self::PRODUCT_HEADER . ';qty', [
			"601;MB145QA;;100;Qty item A;$g;;;3",
			"602;MB145QB;;100;Qty item B;$g;;;5",
		], [], "\r\n");

		$this->itemId('MB145QA');
		$this->itemId('MB145QB');
		$this->assertStringNotContainsString('Sætter  til', $printed, 'no stock message without an item number');
		$this->assertMatchesRegularExpression(
			'/Sætter MB145QA til 3 stk<br>/',
			$printed,
			'the last stock message names the item and its quantity'
		);
	}
}
