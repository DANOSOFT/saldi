<?php
// tests/shopify/PricesTest.php
//
// The price resolver and payloads (SD-802) without a database: the offer windows as POS reads
// them (find_pris() for a price special, opret_ordrelinje() for a percent special), their
// boundaries and the edge days, the wall clock across both DST changes, gross VAT rounding as
// dkdecimal() rounds, compareAt only while an offer lowers the price, the retail price as stored,
// the documents and inputs against the 2026-07 schema, and that the oversell flag changes the
// catalogue hash, and the worker registers price.window. PricesSyncTest compares the resolver with the legacy functions on a tenant.
// Each test runs in its own process, since prices.php brings catalogue.php (see CatalogueTest).
//
// History:
// 20261103 CL/SS SD-802: created.
// 20261104 CL/SS SD-802 review: a percent offer rounds as the POS total (3 then 2 decimals); the worker's real wiring.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class PricesTest extends TestCase
{
	/** Net 999.20: 1249.00 with 25 % VAT. */
	private const ITEM = ['salgspris' => '999.200', 'retail_price' => '1999.000', 'specialtype' => 'price', 'special_price' => '799.200',
		'special_from_date' => '2026-11-10', 'special_to_date' => '2026-11-12', 'special_from_time' => '10:00:00', 'special_to_time' => '18:00:00'];

	protected function setUp(): void
	{
		require_once __DIR__ . '/../../includes/shopify/services/prices.php';
		require_once __DIR__ . '/support/GraphqlSchema.php';
	}

	private static function at(string $local, array $item = self::ITEM, float $vat = 25.0): array
	{
		return shopify_price_resolve($item, $vat, new DateTimeImmutable($local, new DateTimeZone('Europe/Copenhagen')));
	}

	public static function priceWindow(): array
	{
		return [
			'the day before' => ['2026-11-09 12:00:00', false],
			'first day, before the start time' => ['2026-11-10 09:59:59', false],
			'first day, at the start time' => ['2026-11-10 10:00:00', true],
			'a middle day, before the end time' => ['2026-11-11 08:00:00', true],
			'a middle day, after the end time (find_pris keeps it)' => ['2026-11-11 18:00:01', false],
			'last day, at the end time' => ['2026-11-12 18:00:00', true],
			'last day, a second after' => ['2026-11-12 18:00:01', false],
		];
	}

	#[DataProvider('priceWindow')]
	public function testAPriceSpecialFollowsFindPrisWindow(string $local, bool $on): void
	{
		$r = self::at($local);

		$this->assertSame($on ? ['999.00', '1249.00', 'price'] : ['1249.00', null, null], [$r['price'], $r['compare_at'], $r['offer']]);
		$this->assertSame('1999.00', $r['retail_price'], 'Vejl.pris as stored, no VAT added');
	}

	public function testAnEndTimeOfMidnightMakesWholeDays(): void
	{
		$item = ['special_to_time' => '00:00:00'] + self::ITEM;

		$this->assertSame('price', self::at('2026-11-11 23:59:59', $item)['offer'], 'a day before the last runs to 23:59:59');
		$this->assertNull(self::at('2026-11-12 00:00:01', $item)['offer'], 'the last day ends at 00:00:00');
	}

	public static function percentWindow(): array
	{
		return [
			'first day, before the start time' => ['2026-11-10 09:59:59', false],
			'first day, at the start time' => ['2026-11-10 10:00:00', true],
			'a middle day, any time' => ['2026-11-11 23:00:00', true],
			'last day, at the end time' => ['2026-11-12 18:00:00', true],
			'last day, a second after' => ['2026-11-12 18:00:01', false],
		];
	}

	#[DataProvider('percentWindow')]
	public function testAPercentSpecialFollowsOpretOrdrelinjesWindowAsTheLineDiscount(string $local, bool $on): void
	{
		$r = self::at($local, ['specialtype' => 'percent', 'special_price' => '15'] + self::ITEM);

		// 1249.00 less 15 % = 1061.65
		$this->assertSame($on ? ['1061.65', '1249.00', 'percent'] : ['1249.00', null, null], [$r['price'], $r['compare_at'], $r['offer']]);
	}

	public static function oddOre(): array
	{
		return [
			'12.37 less 15 %' => ['9.896', '10.52'],
			'1.17 less 15 % (0.9945)' => ['0.936', '1.00'],
			'999.37 less 15 %' => ['799.496', '849.47'],
		];
	}

	#[DataProvider('oddOre')]
	public function testAPercentOfferRoundsAsThePosTotal(string $net, string $price): void
	{
		$this->assertSame($price, self::at('2026-11-11 12:00:00', ['salgspris' => $net, 'specialtype' => 'percent', 'special_price' => '15'] + self::ITEM)['price'],
			'ordrelinjerDataII.php:100 rounds the line to 3 decimals, showPosLinesFunc.php:163 the total to 2');
	}

	public function testTheWorkerRegistersThePriceWindowHandler(): void
	{
		require_once __DIR__ . '/../characterization/debitor/support/pos_lift.php';
		require_once __DIR__ . '/../../includes/shopify/worker.php';
		$tools = realpath(__DIR__ . '/../../tools');
		// the function as it ships; its __DIR__ is the tools folder
		eval(str_replace('__DIR__', var_export($tools, true), pos_lift("$tools/shopify_worker.php", ['shopify_worker_wiring'])));

		$this->assertSame('shopify_prices_window_job', shopify_worker_wiring()['registry']['price.window'] ?? null);
	}

	public function testOnlyAPriceOrPercentSpecialWithAPriceCounts(): void
	{
		$this->assertNull(self::at('2026-11-11 12:00:00', ['specialtype' => ''] + self::ITEM)['offer'], 'POS ignores a special without its type');
		$this->assertNull(self::at('2026-11-11 12:00:00', ['specialtype' => null] + self::ITEM)['offer']);
		$this->assertNull(self::at('2026-11-11 12:00:00', ['special_price' => '0.000'] + self::ITEM)['offer']);
		$this->assertNull(self::at('2026-11-11 12:00:00', ['special_from_date' => null, 'special_to_date' => null, 'special_from_time' => null, 'special_to_time' => null] + self::ITEM)['offer']);
		$this->assertSame('price', self::at('2026-11-11 12:00:00', ['special_from_date' => null] + self::ITEM)['offer'], 'no start date: running until the end, as in POS');
	}

	public function testAnOfferAboveTheNormalPriceHasNoCompareAt(): void
	{
		$r = self::at('2026-11-11 12:00:00', ['special_price' => '1200.000'] + self::ITEM);

		$this->assertSame(['1500.00', null, 'price'], [$r['price'], $r['compare_at'], $r['offer']]);
	}

	public function testTheWallClockDecidesAcrossTheAutumnDstChange(): void
	{
		// 25 Oct 2026: 03:00 CEST becomes 02:00 CET, so 02:00-03:00 happens twice
		$item = ['special_from_date' => '2026-10-20', 'special_to_date' => '2026-10-25', 'special_to_time' => '02:30:00'] + self::ITEM;
		$utc = fn(string $t): array => shopify_price_resolve($item, 25.0, (new DateTimeImmutable($t, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Copenhagen')));

		$this->assertSame('price', $utc('2026-10-25 00:15:00')['offer'], '02:15 CEST');
		$this->assertNull($utc('2026-10-25 00:45:00')['offer'], '02:45 CEST');
		$this->assertSame('price', $utc('2026-10-25 01:15:00')['offer'], '02:15 CET: the repeated half hour is in the offer again, as in POS');
		$this->assertNull($utc('2026-10-25 01:45:00')['offer'], '02:45 CET');
	}

	public function testTheWallClockDecidesAcrossTheSpringDstChange(): void
	{
		// 29 Mar 2026: 02:00 CET becomes 03:00 CEST, so 02:30 never happens
		$item = ['special_from_date' => '2026-03-29', 'special_to_date' => '2026-04-05', 'special_from_time' => '02:30:00'] + self::ITEM;
		$utc = fn(string $t): array => shopify_price_resolve($item, 25.0, (new DateTimeImmutable($t, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Copenhagen')));

		$this->assertNull($utc('2026-03-29 00:59:59')['offer'], '01:59:59 CET');
		$this->assertSame('price', $utc('2026-03-29 01:00:00')['offer'], '03:00 CEST: the start fell in the skipped hour');
	}

	public function testTheResolverReadsTheWallClockOfTheZoneItIsGiven(): void
	{
		$utcNoon = new DateTimeImmutable('2026-11-10 09:30:00', new DateTimeZone('UTC'));

		$this->assertNull(shopify_price_resolve(self::ITEM, 25.0, $utcNoon)['offer'], '09:30 in UTC');
		$this->assertSame('price', shopify_price_resolve(self::ITEM, 25.0, $utcNoon->setTimezone(new DateTimeZone('Europe/Copenhagen')))['offer'], '10:30 in Copenhagen');
	}

	public function testGrossPricesAreRoundedAsDkdecimalRounds(): void
	{
		$this->assertSame(100.0, shopify_price_gross(79.996, 25.0), '99.995 rounds half up');
		$this->assertSame(12.35, shopify_price_gross(9.87599, 25.0), 'afrund() adds 0.0001 first: 12.3449875 becomes 12.35');
		$this->assertSame(1249.0, shopify_price_gross(999.2, 25.0));
		$this->assertSame(['0.00', '0.00'], [self::at('2026-11-01 12:00:00', ['salgspris' => '0', 'retail_price' => null] + self::ITEM)['price'],
			self::at('2026-11-01 12:00:00', ['retail_price' => null] + self::ITEM)['retail_price']]);
		$this->assertSame('999.20', self::at('2026-11-01 12:00:00', self::ITEM, 0.0)['price'], 'an item without VAT is sent as stored');
	}

	public function testTheDocumentAndInputsMatchThe2026_07Schema(): void
	{
		$schema = GraphqlSchema::load();
		$offer = self::at('2026-11-11 12:00:00');
		$normal = self::at('2026-11-13 12:00:00');

		$this->assertSame([], $schema->validateDocument(SHOPIFY_PRICES_UPDATE));
		$this->assertSame([], $schema->validateInput('ProductVariantsBulkInput!', shopify_prices_variant_input('gid://shopify/ProductVariant/8', $offer)));
		$this->assertSame(['id' => 'gid://shopify/ProductVariant/8', 'price' => '1249.00', 'compareAtPrice' => null],
			shopify_prices_variant_input('gid://shopify/ProductVariant/8', $normal), 'after the offer, compareAt is sent as null: cleared');
		$retail = shopify_prices_retail_metafield('gid://shopify/ProductVariant/8', $offer);
		$this->assertSame([], $schema->validateInput('MetafieldsSetInput!', $retail));
		$this->assertSame(['saldi', 'retail_price', 'number_decimal', '1999.00'], [$retail['namespace'], $retail['key'], $retail['type'], $retail['value']]);
	}

	public function testTheOversellFlagChangesTheCatalogueHash(): void
	{
		$item = ['id' => '42', 'varenr' => 'HAVE-1', 'beskrivelse' => 'Havestol', 'stregkode' => '', 'kostpris' => '1', 'grossweight' => '1',
			'grossweightunit' => 'kg', 'netweight' => '0', 'netweightunit' => 'kg', 'oversalg' => ''];

		$this->assertNotSame(shopify_catalogue_hash($item, ['rate' => 25.0]), shopify_catalogue_hash(['oversalg' => 'on'] + $item, ['rate' => 25.0]),
			'K14: ticking Tillad oversalg sends the item again (inventoryPolicy CONTINUE)');
	}
}
