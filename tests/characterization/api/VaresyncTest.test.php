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
// - A label with an apostrophe is saved (it is escaped in the variant_typer queries).
// - When the shop gives an existing variant a new id, the relink finds the variant through the
//   label within its own category, so the existing shop_varer link moves to the new id instead of
//   a second link being added.
//
// Pinned (MB-192):
// - A product barcode that matches any existing variant barcode is cleared with a message, not only
//   one that matches the last variant barcode read.
// - When the variant file can't be fetched, the barcode messages from the product step are still shown.
//
// Pinned (MB-109):
// - A ';' inside a quoted description stays in the description, so the product is created.
// - A qty column is found by its header name in an LF file with a BOM, so new items get the stock from the file.
// - A qty column in an LF variant file sets the stock of a new variant.
// - A new item whose item number has an apostrophe gets its stock.
// - qty and kostpris are only used in their fixed column positions, as before: an LF file with
//   kostpris and a trailing qty keeps the kostpris layout, and a qty column that is not last is ignored.
// - Stock values from the file keep their decimals (1250.500, 0.005, 0).
//
// Pinned (MB-110):
// - An item number that is twice in the file creates one product, linked to the first row's shop id;
//   the second row is skipped with a message, and its qty is not used as stock. The first row counts
//   even when it is skipped itself, so a later duplicate never imports with the first row's qty.
// - A row with a cost that is not a number (12,34), a shop id that is not a number, or a price that is
//   not a number is skipped with a message, and the rows after it are still imported.
// - A shop id that already belongs to another product is not linked to a new product (Hent) or to an
//   existing product without a link (Opdater); the message names the owner.
// - In Opdater fra shop a shop id that isn't a number doesn't stop the price update: an already linked item
//   ignores it, an unlinked item gets a message and no link.
// - A shop id guessed from a numeric item number is dropped when it is not valid or belongs to another
//   product; the item itself is still created, without a link. A shop id of only spaces counts as none.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261125 CL/SZ MB-145 Created.
// 20261125 CL/SZ MB-145 Review: apostrophe and relink tests, skip without wget or php -S, temp folders removed.
// 20261125 CL/SZ MB-192 Variant barcode clash and missing variant file tests; sync() can leave the variant file out.
// 20261125 CL/SZ MB-109 Quoted ';', qty by header name in LF files, apostrophe item number stock tests.
// 20261125 CL/SZ MB-109 Review: column position tests and the stock door values.
// 20261125 CL/SZ MB-110 Bad and duplicate row tests; sync() can run Opdater fra shop.
// 20261125 CL/SZ MB-110 Review: Opdater with a bad shop id, guessed shop ids.

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
		if (trim((string)shell_exec('command -v wget')) === '') {
			self::markTestSkipped('varesync() downloads the shop files with wget, which is not installed');
		}
		parent::setUpBeforeClass();

		self::$stubDir = sys_get_temp_dir() . '/varesync_stub_' . getmypid();
		if (!is_dir(self::$stubDir) && !mkdir(self::$stubDir, 0777, true)) {
			self::fail('could not create ' . self::$stubDir);
		}
		$port = self::freePort();
		// php -S writes its start line and one line per request here; the test waits for the first.
		$log = self::$stubDir . '/server.log';
		self::$server = proc_open(
			[PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/../support/varesync_stub_router.php'],
			[0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', $log, 'w']],
			$pipes,
			self::$stubDir
		);
		if (!self::waitForServer($log)) {
			self::stopServer();
			parent::tearDownAfterClass();
			self::markTestSkipped("the stub shop (php -S on port $port) did not start");
		}

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
		self::stopServer();
		self::removeDir(self::$stubDir);
		if (isset(self::$tenant) && self::$tenant) {
			// run_varesync.php works in temp/<tenant db>, as the page does.
			self::removeDir(CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb());
		}
		parent::tearDownAfterClass();
	}

	private static function stopServer(): void
	{
		if (self::$server) {
			proc_terminate(self::$server);
			proc_close(self::$server);
			self::$server = null;
		}
	}

	private static function removeDir(string $dir): void
	{
		if ($dir === '' || !is_dir($dir)) {
			return;
		}
		foreach (scandir($dir) as $f) {
			if ($f !== '.' && $f !== '..') {
				is_dir("$dir/$f") ? self::removeDir("$dir/$f") : unlink("$dir/$f");
			}
		}
		rmdir($dir);
	}

	private static function freePort(): int
	{
		$sock = stream_socket_server('tcp://127.0.0.1:0');
		$name = stream_socket_get_name($sock, false);
		fclose($sock);
		return (int)substr($name, strrpos($name, ':') + 1);
	}

	private static function waitForServer(string $log): bool
	{
		for ($i = 0; $i < 50; $i++) {
			clearstatcache();
			if (is_file($log) && str_contains((string)file_get_contents($log), 'started')) {
				return true;
			}
			usleep(100000);
		}
		return false;
	}

	/**
	 * @param list<string> $productRows
	 * @param list<string>|null $variantRows null = the shop serves no variant file
	 */
	private function sync(string $productHeader, array $productRows, ?array $variantRows, string $eol = "\n", string $valg = '1'): string
	{
		file_put_contents(
			self::$stubDir . '/shop_products.csv',
			implode($eol, array_merge([$productHeader], $productRows)) . $eol
		);
		$variantFile = self::$stubDir . '/shop_variants.csv';
		if ($variantRows === null) {
			if (is_file($variantFile)) {
				unlink($variantFile);
			}
		} else {
			file_put_contents($variantFile, implode("\n", array_merge([self::VARIANT_HEADER], $variantRows)) . "\n");
		}
		$out = $this->runChildJson('run_varesync.php', [$valg, CharacterizationEnv::testDb()]);
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

	public function test_a_label_with_an_apostrophe_is_saved(): void
	{
		$g = self::$group;
		$this->sync(self::PRODUCT_HEADER, ["505;MB145APO;;100;Apostrophe item;$g;;"], [
			"MB145APO;505;9505;MB145APOW;Women's;Size;Womens size",
		]);

		$types = self::rows(
			"SELECT id FROM variant_typer WHERE beskrivelse = 'Women''s' AND variant_id = $1",
			[self::$sizeId]
		);
		$this->assertCount(1, $types, "Women's is created under Size");

		$variants = $this->variantsOf($this->itemId('MB145APO'));
		$this->assertCount(1, $variants, 'the variant is saved');
		$this->assertSame((string)$types[0]['id'], (string)$variants[0]['variant_type'], "it stores the Women's type");
	}

	public function test_a_new_shop_variant_id_moves_the_existing_link(): void
	{
		$g = self::$group;
		$product = ["503;MB145REL;;100;Relink item;$g;;"];
		$this->sync(self::PRODUCT_HEADER, $product, ['MB145REL;503;9601;MB145RELSMALL;Small;Length;Small length']);
		$this->sync(self::PRODUCT_HEADER, $product, ['MB145REL;503;9602;MB145RELSMALL;Small;Length;Small length']);

		$itemId = $this->itemId('MB145REL');
		$this->assertCount(1, $this->variantsOf($itemId), 'still one variant');
		$links = self::rows(
			"SELECT shop_id, shop_variant FROM shop_varer WHERE saldi_id = $1 AND saldi_variant <> '0' ORDER BY id",
			[$itemId]
		);
		$this->assertSame(
			[['shop_id' => '503', 'shop_variant' => '9602']],
			$links,
			'the one variant link now points at the new shop variant id'
		);
	}

	public function test_a_product_barcode_used_by_any_variant_is_cleared(): void
	{
		$g = self::$group;
		$owner = (int)self::one(
			"INSERT INTO varer (varenr, beskrivelse, gruppe, salgspris, kostpris, lukket) VALUES ('MB192OWNER', 'Variant owner', $1, 0, 0, '0') RETURNING id",
			[$g]
		)['id'];
		foreach (['MB192VB1', 'MB192VB2'] as $barcode) {
			self::rows('INSERT INTO variant_varer (vare_id, variant_stregkode) VALUES ($1, $2)', [$owner, $barcode]);
		}

		$printed = $this->sync(self::PRODUCT_HEADER, [
			"701;MB192P1;MB192VB1;100;Clash one;$g;;",
			"702;MB192P2;MB192VB2;100;Clash two;$g;;",
		], []);

		foreach (['MB192P1' => 'MB192VB1', 'MB192P2' => 'MB192VB2'] as $varenr => $barcode) {
			$r = self::one('SELECT stregkode FROM varer WHERE varenr = $1', [$varenr]);
			$this->assertNotNull($r, "item $varenr was created");
			$this->assertSame('', (string)$r['stregkode'], "$varenr does not keep the variant barcode $barcode");
			$this->assertStringContainsString(
				"Stregkode $barcode bruges også i varianter,  -- stregkode slettet for $varenr:",
				$printed,
				"the clash on $barcode is reported"
			);
		}
		$this->assertStringContainsString('MB192P1:Clash one<br>Stregkode MB192VB2', $printed, 'each clash message ends its line');
	}

	public function test_barcode_messages_are_shown_when_the_variant_file_is_missing(): void
	{
		$g = self::$group;
		$printed = $this->sync(self::PRODUCT_HEADER, [
			"711;MB192D1;MB192DUP;100;Duplicate one;$g;;",
			"712;MB192D2;MB192DUP;100;Duplicate two;$g;;",
		], null);

		$this->itemId('MB192D2');
		$this->assertStringContainsString(
			'Stregkode MB192DUP bruges også i MB192D1:Duplicate one -- stregkode slettet for MB192D2:Duplicate two',
			$printed,
			'the duplicate barcode message is shown although the run stops at the variant file'
		);
	}

	/** @return array{beholdning: float, kob: float} stock and bought quantity for an item (variant 0 = the item itself) */
	private function stockOf(int $itemId, int $variantId = 0): array
	{
		$ls = self::rows(
			'SELECT beholdning FROM lagerstatus WHERE vare_id = $1 AND variant_id = $2',
			[$itemId, $variantId]
		);
		$bk = self::rows(
			'SELECT antal FROM batch_kob WHERE vare_id = $1 AND variant_id = $2',
			[$itemId, $variantId]
		);
		return ['beholdning' => self::sumOf($ls, 'beholdning'), 'kob' => self::sumOf($bk, 'antal')];
	}

	public function test_a_semicolon_inside_a_quoted_description_stays_in_the_description(): void
	{
		$g = self::$group;
		$this->sync(self::PRODUCT_HEADER, ["801;MB109SEMI;;100;\"Bord; rund\";$g;;"], []);

		$r = self::one('SELECT beskrivelse, salgspris, gruppe FROM varer WHERE varenr = $1', ['MB109SEMI']);
		$this->assertNotNull($r, 'the product with a quoted ; is created');
		$this->assertSame('Bord; rund', $r['beskrivelse']);
		$this->assertSame(100.0, (float)$r['salgspris']);
		$this->assertSame((string)$g, (string)$r['gruppe']);
	}

	public function test_a_qty_column_in_an_lf_file_with_a_bom_sets_the_stock(): void
	{
		$g = self::$group;
		$this->sync("\u{FEFF}" . self::PRODUCT_HEADER . ';qty', ["811;MB109LF;;100;LF qty item;$g;;;4", ''], []);

		$this->assertSame(
			['beholdning' => 4.0, 'kob' => 4.0],
			$this->stockOf($this->itemId('MB109LF')),
			'the new item gets the 4 from the qty column'
		);
	}

	public function test_a_qty_column_in_an_lf_variant_file_sets_the_variant_stock(): void
	{
		$g = self::$group;
		$this->syncWithVariantHeader(
			self::VARIANT_HEADER . ';qty',
			["821;MB109VQ;;100;Variant qty item;$g;;"],
			['MB109VQ;821;9821;MB109VQSMALL;Small;Size;Small size;3']
		);

		$itemId = $this->itemId('MB109VQ');
		$v = self::one('SELECT id FROM variant_varer WHERE vare_id = $1', [$itemId]);
		$this->assertNotNull($v, 'the variant is saved');
		$this->assertSame(
			['beholdning' => 3.0, 'kob' => 3.0],
			$this->stockOf($itemId, (int)$v['id']),
			'the new variant gets the 3 from the qty column'
		);
	}

	public function test_an_item_number_with_an_apostrophe_gets_its_stock(): void
	{
		$g = self::$group;
		$this->sync(self::PRODUCT_HEADER . ';qty', ["831;MB109O'NEIL;;100;Apostrophe stock;$g;;;6"], [], "\r\n");

		$this->assertSame(
			['beholdning' => 6.0, 'kob' => 6.0],
			$this->stockOf($this->itemId("MB109O'NEIL")),
			'the new item gets the 6 from the qty column'
		);
	}

	/**
	 * @param list<string> $productRows
	 * @param list<string> $variantRows
	 * @return string what the run printed
	 */
	private function syncWithVariantHeader(string $variantHeader, array $productRows, array $variantRows): string
	{
		file_put_contents(
			self::$stubDir . '/shop_products.csv',
			implode("\n", array_merge([self::PRODUCT_HEADER], $productRows)) . "\n"
		);
		file_put_contents(
			self::$stubDir . '/shop_variants.csv',
			implode("\n", array_merge([$variantHeader], $variantRows)) . "\n"
		);
		$out = $this->runChildJson('run_varesync.php', ['1', CharacterizationEnv::testDb()]);
		return (string)$out['printed'];
	}

	public function test_an_lf_file_with_kostpris_and_a_trailing_qty_keeps_the_kostpris_layout(): void
	{
		$g = self::$group;
		$this->sync(
			'id;varenr;stregkode;salgspris;kostpris;beskrivelse;gruppe;tilbud;notes;qty',
			["851;MB109KQ;;100;40;Kost qty item;$g;;;7"],
			[]
		);

		$r = self::one('SELECT beskrivelse, kostpris, gruppe FROM varer WHERE varenr = $1', ['MB109KQ']);
		$this->assertNotNull($r, 'the product is created');
		$this->assertSame('Kost qty item', $r['beskrivelse']);
		$this->assertSame(40.0, (float)$r['kostpris']);
		$this->assertSame((string)$g, (string)$r['gruppe']);
	}

	public function test_a_qty_column_that_is_not_last_is_ignored(): void
	{
		$g = self::$group;
		$this->sync('id;varenr;stregkode;salgspris;beskrivelse;gruppe;tilbud;qty;notes', ["861;MB109QN;;100;Qty not last;$g;;9;12"], []);

		$this->assertSame(
			['beholdning' => 0.0, 'kob' => 0.0],
			$this->stockOf($this->itemId('MB109QN')),
			'neither the qty nor the notes value is used as stock'
		);
	}

	public function test_stock_values_keep_their_decimals(): void
	{
		$g = self::$group;
		$this->sync(self::PRODUCT_HEADER . ';qty', [
			"871;MB109BIG;;100;Big stock;$g;;;1250.500",
			"872;MB109TINY;;100;Tiny stock;$g;;;0.005",
			"873;MB109ZERO;;100;Zero stock;$g;;;0",
		], []);

		$this->assertSame(['beholdning' => 1250.5, 'kob' => 1250.5], $this->stockOf($this->itemId('MB109BIG')));
		$this->assertSame(['beholdning' => 0.005, 'kob' => 0.005], $this->stockOf($this->itemId('MB109TINY')));
		$this->assertSame(['beholdning' => 0.0, 'kob' => 0.0], $this->stockOf($this->itemId('MB109ZERO')));
	}

	/** @return list<array<string,string>> */
	private function linksOf(int $shopId): array
	{
		return self::rows(
			'SELECT v.varenr, s.shop_variant FROM shop_varer s JOIN varer v ON v.id = s.saldi_id WHERE s.shop_id = $1 ORDER BY s.id',
			[$shopId]
		);
	}

	public function test_an_item_number_twice_in_the_file_creates_one_product(): void
	{
		$g = self::$group;
		$printed = $this->sync(self::PRODUCT_HEADER, [
			"1101;MB110DUP;;100;First;$g;;",
			"1102;MB110DUP;;200;Second;$g;;",
		], []);

		$r = self::rows('SELECT beskrivelse FROM varer WHERE varenr = $1', ['MB110DUP']);
		$this->assertSame([['beskrivelse' => 'First']], $r, 'one product, from the first row');
		$this->assertSame([['varenr' => 'MB110DUP', 'shop_variant' => '0']], $this->linksOf(1101));
		$this->assertSame([], $this->linksOf(1102), 'the second shop id is not linked');
		$this->assertStringContainsString('CSV-række 3, varenr MB110DUP: varenummeret står også i CSV-række 2', $printed);
	}

	public function test_the_qty_of_a_skipped_duplicate_row_is_not_used(): void
	{
		$g = self::$group;
		$this->sync(self::PRODUCT_HEADER . ';qty', [
			"1111;MB110DUPQ;;100;First;$g;;;3",
			"1112;MB110DUPQ;;100;Second;$g;;;7",
		], [], "\r\n");

		$this->assertSame(['beholdning' => 3.0, 'kob' => 3.0], $this->stockOf($this->itemId('MB110DUPQ')));
	}

	public function test_a_duplicate_after_a_bad_first_row_is_skipped_too(): void
	{
		$g = self::$group;
		$printed = $this->sync(self::PRODUCT_HEADER . ';qty', [
			"1121;MB110DUPB;;12,50;Bad first;$g;;;3",
			"1122;MB110DUPB;;100;Good second;$g;;;7",
		], [], "\r\n");

		$this->assertNull(self::one('SELECT id FROM varer WHERE varenr = $1', ['MB110DUPB']), 'no product from either row');
		$this->assertSame([], $this->linksOf(1122));
		$this->assertStringContainsString('CSV-række 3, varenr MB110DUPB: varenummeret står også i CSV-række 2', $printed);
	}

	public function test_a_bad_cost_skips_only_that_row(): void
	{
		$g = self::$group;
		$printed = $this->sync('id;varenr;stregkode;salgspris;kostpris;beskrivelse;gruppe;tilbud;notes', [
			"1201;MB110C1;;100;40;Before;$g;;",
			"1202;MB110C2;;100;12,34;Danish cost;$g;;",
			"1203;MB110C3;;100;50;After;$g;;",
		], []);

		$this->itemId('MB110C1');
		$this->itemId('MB110C3');
		$this->assertNull(self::one('SELECT id FROM varer WHERE varenr = $1', ['MB110C2']), 'the bad row is not imported');
		$this->assertSame([], $this->linksOf(1202));
		$this->assertStringContainsString('CSV-række 3, varenr MB110C2: kostprisen er ikke et tal. Rækken er sprunget over.', $printed);
	}

	public function test_a_bad_shop_id_or_price_skips_only_that_row(): void
	{
		$g = self::$group;
		$printed = $this->sync(self::PRODUCT_HEADER, [
			"ABC;MB110S1;;100;Bad shop id;$g;;",
			"1302;MB110S2;;12,50;Danish price;$g;;",
			"1303;MB110S3;;100;After;$g;;",
		], []);

		$this->assertNull(self::one('SELECT id FROM varer WHERE varenr = $1', ['MB110S1']));
		$this->assertNull(self::one('SELECT id FROM varer WHERE varenr = $1', ['MB110S2']));
		$this->itemId('MB110S3');
		$this->assertStringContainsString('CSV-række 2, varenr MB110S1: shop-id ABC er ikke et gyldigt id.', $printed);
		$this->assertStringContainsString('CSV-række 3, varenr MB110S2: salgsprisen er ikke et tal.', $printed);
	}

	public function test_a_shop_id_of_another_product_is_not_linked_to_a_new_product(): void
	{
		$g = self::$group;
		$this->sync(self::PRODUCT_HEADER, ["1401;MB110OWN;;100;Owner;$g;;"], []);
		$printed = $this->sync(self::PRODUCT_HEADER, ["1401;MB110NEW;;100;Newcomer;$g;;"], []);

		$this->assertNull(self::one('SELECT id FROM varer WHERE varenr = $1', ['MB110NEW']));
		$this->assertSame([['varenr' => 'MB110OWN', 'shop_variant' => '0']], $this->linksOf(1401));
		$this->assertStringContainsString('CSV-række 2, varenr MB110NEW: shop-id 1401 hører allerede til varenr MB110OWN.', $printed);
	}

	public function test_opdater_does_not_link_a_shop_id_of_another_product(): void
	{
		$g = self::$group;
		$this->sync(self::PRODUCT_HEADER, ["1501;MB110UOWN;;100;Owner;$g;;"], []);
		self::one(
			"INSERT INTO varer (varenr, beskrivelse, salgspris, kostpris, gruppe, beholdning, lukket) VALUES ('MB110ULINK', 'No link', 100, 0, $1, 0, '0') RETURNING id",
			[$g]
		);
		$printed = $this->sync(self::PRODUCT_HEADER, ["1501;MB110ULINK;;150;No link;$g;;"], [], "\n", '2');

		$this->assertSame([['varenr' => 'MB110UOWN', 'shop_variant' => '0']], $this->linksOf(1501));
		$r = self::one('SELECT salgspris FROM varer WHERE varenr = $1', ['MB110ULINK']);
		$this->assertSame(150.0, (float)$r['salgspris'], 'the price is still updated');
		$this->assertStringContainsString("CSV-række 2, varenr MB110ULINK: shop-id 1501 hører allerede til varenr MB110UOWN. Shop-id'et er ikke knyttet til varen.", $printed);
	}

	public function test_opdater_updates_the_price_when_the_shop_id_is_not_a_number(): void
	{
		$g = self::$group;
		$this->sync(self::PRODUCT_HEADER, ["1801;MB110LNK;;100;Linked;$g;;"], []);
		self::one(
			"INSERT INTO varer (varenr, beskrivelse, salgspris, kostpris, gruppe, beholdning, lukket) VALUES ('MB110NOLNK', 'Not linked', 100, 0, $1, 0, '0') RETURNING id",
			[$g]
		);
		$printed = $this->sync(self::PRODUCT_HEADER, ["-5;MB110LNK;;175;Linked;$g;;", "12.5;MB110NOLNK;;180;Not linked;$g;;"], [], "\n", '2');

		$this->assertSame(175.0, (float)self::one('SELECT salgspris FROM varer WHERE varenr = $1', ['MB110LNK'])['salgspris']);
		$this->assertSame(180.0, (float)self::one('SELECT salgspris FROM varer WHERE varenr = $1', ['MB110NOLNK'])['salgspris']);
		$this->assertSame([['varenr' => 'MB110LNK', 'shop_variant' => '0']], $this->linksOf(1801));
		$this->assertStringNotContainsString('MB110LNK', $printed, 'no message for the linked item');
		$this->assertStringContainsString("CSV-række 3, varenr MB110NOLNK: shop-id 12.5 er ikke et gyldigt id. Shop-id'et er ikke knyttet til varen.", $printed);
	}

	public function test_a_guessed_shop_id_that_cannot_be_used_is_dropped(): void
	{
		$g = self::$group;
		$this->sync(self::PRODUCT_HEADER, ["1901;MB110OWN2;;100;Owner;$g;;"], []);
		$printed = $this->sync(self::PRODUCT_HEADER, [";1901;;100;Numeric item no.;$g;;", ";19.5;;100;Decimal item no.;$g;;", " ;MB110BLANK;;100;Blank shop id;$g;;"], []);

		$this->itemId('1901');
		$this->itemId('19.5');
		$this->itemId('MB110BLANK');
		$this->assertSame([['varenr' => 'MB110OWN2', 'shop_variant' => '0']], $this->linksOf(1901));
		$this->assertStringNotContainsString('Rækken er sprunget over', $printed);
	}
}
