<?php
// tests/shopify/CatalogueTest.php
//
// The catalogue's pure parts (SD-741): the productSet payloads against the 2026-07 introspection
// dump, the ownership allowlist (an update never carries a field HML owns), the variant-set
// check before variants are sent, oversell policy, weight units, and the change hash.
// No database: runs in CI. Each test runs in its own process, since catalogue.php brings
// shopify_redact() and shopify_secret(), which ClientRetryTest expects to be absent.
//
// History:
// 20261029 CL/SS SD-741: created.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class CatalogueTest extends TestCase
{
	/** The product fields Saldi may set on an update; everything else is HML's. */
	private const OWNED_PRODUCT = ['title', 'productOptions', 'variants'];
	private const OWNED_VARIANT = ['id', 'optionValues', 'sku', 'barcode', 'inventoryPolicy', 'taxable', 'inventoryItem'];
	private const OWNED_INVENTORY = ['cost', 'tracked', 'measurement'];

	private const ITEM = ['id' => '42', 'varenr' => 'HAVE-1', 'beskrivelse' => 'Havestol teak', 'stregkode' => '5701234567890',
		'kostpris' => '812.5', 'grossweight' => '7.25', 'grossweightunit' => 'kg', 'netweight' => '6', 'netweightunit' => 'kg',
		'oversalg' => ''];
	private const VAT = ['rate' => 25.0];
	private const LINK = ['product_gid' => 'gid://shopify/Product/7', 'variant_gid' => 'gid://shopify/ProductVariant/8'];

	protected function setUp(): void
	{
		require_once __DIR__ . '/../../includes/shopify/services/catalogue.php';
		require_once __DIR__ . '/support/GraphqlSchema.php';
	}

	private static function remote(array $variantIds = ['gid://shopify/ProductVariant/8']): array
	{
		return ['id' => 'gid://shopify/Product/7', 'handle' => 'havestol', 'variantsCount' => ['count' => count($variantIds)],
			'options' => [['id' => 'gid://shopify/ProductOption/1', 'name' => 'Title', 'optionValues' => [['id' => 'gid://shopify/ProductOptionValue/1', 'name' => 'Default Title']]]],
			'variants' => ['nodes' => array_map(fn(string $id): array => ['id' => $id, 'sku' => 'HAVE-1',
				'selectedOptions' => [['name' => 'Title', 'value' => 'Default Title']], 'inventoryItem' => ['id' => 'gid://shopify/InventoryItem/9']], $variantIds)]];
	}

	public function testTheDocumentsMatchThe2026_07Schema(): void
	{
		$schema = GraphqlSchema::load();
		foreach ([SHOPIFY_CATALOGUE_READ, SHOPIFY_CATALOGUE_BY_HANDLE, SHOPIFY_CATALOGUE_BY_SKU, SHOPIFY_CATALOGUE_VARIANT, SHOPIFY_CATALOGUE_SET, SHOPIFY_CATALOGUE_METAFIELDS] as $doc) {
			$this->assertSame([], $schema->validateDocument($doc), $doc);
		}
	}

	public function testACreateIsADraftWithTheK07FieldsAndAValidInput(): void
	{
		$p = shopify_catalogue_payload(self::ITEM, self::VAT, null, null, true);
		$schema = GraphqlSchema::load();

		$this->assertSame([], $schema->validateInput('ProductSetInput!', $p['input']));
		$this->assertSame([], $schema->validateInput('ProductSetIdentifiers', $p['identifier']));
		$this->assertSame(['handle' => 'saldi-42'], $p['identifier'], 'a create that reached Shopify is found again by its handle');
		$this->assertSame('DRAFT', $p['input']['status']);
		$this->assertSame('Havestol teak', $p['input']['title']);
		$variant = $p['input']['variants'][0];
		$this->assertSame(['HAVE-1', '5701234567890', 'DENY', true], [$variant['sku'], $variant['barcode'], $variant['inventoryPolicy'], $variant['taxable']]);
		$this->assertSame(['cost' => '812.50', 'tracked' => true, 'measurement' => ['weight' => ['value' => 7.25, 'unit' => 'KILOGRAMS']]], $variant['inventoryItem']);
		$this->assertArrayNotHasKey('descriptionHtml', $p['input']);
		$this->assertNull($p['problem']);
	}

	public function testAnUpdateCarriesOnlyOwnedFieldsAndTheLinkedVariant(): void
	{
		$p = shopify_catalogue_payload(self::ITEM, self::VAT, self::LINK, self::remote(), false);

		$this->assertSame([], GraphqlSchema::load()->validateInput('ProductSetInput!', $p['input']));
		$this->assertSame(['id' => 'gid://shopify/Product/7'], $p['identifier'], 'a rename updates the same product');
		$this->assertSame([], array_diff(array_keys($p['input']), self::OWNED_PRODUCT), 'no HML-owned product field');
		$variant = $p['input']['variants'][0];
		$this->assertSame([], array_diff(array_keys($variant), self::OWNED_VARIANT));
		$this->assertSame([], array_diff(array_keys($variant['inventoryItem']), self::OWNED_INVENTORY));
		$this->assertSame('gid://shopify/ProductVariant/8', $variant['id']);
		$this->assertSame([['optionName' => 'Title', 'name' => 'Default Title']], $variant['optionValues'], 'the remote options are kept as they are');
		$this->assertSame('gid://shopify/ProductOption/1', $p['input']['productOptions'][0]['id']);
	}

	public static function remoteVariantSets(): array
	{
		return [
			'two variants' => [self::remote(['gid://shopify/ProductVariant/8', 'gid://shopify/ProductVariant/10']), 'has 2 variants'],
			'another variant' => [self::remote(['gid://shopify/ProductVariant/11']), 'not the linked one'],
			'not read' => [null, 'could not be read'],
		];
	}

	#[DataProvider('remoteVariantSets')]
	public function testVariantsAreSentOnlyWhenTheRemoteSetIsTheLinkedVariant(?array $remote, string $why): void
	{
		$p = shopify_catalogue_payload(self::ITEM, self::VAT, self::LINK, $remote, false);

		$this->assertStringContainsString($why, (string)$p['problem']);
		$this->assertSame([], $p['input'], 'nothing: productSet would delete variants, and another product\'s title is not this item\'s name');
	}

	public function testOversellWeightUnitAndVatFreeMapToShopifyValues(): void
	{
		$item = ['oversalg' => 'on', 'grossweight' => '0', 'netweight' => '850', 'netweightunit' => 'g', 'beskrivelse' => ' '] + self::ITEM;
		$variant = shopify_catalogue_payload($item, ['rate' => 0.0], null, null, true)['input']['variants'][0];

		$this->assertSame('CONTINUE', $variant['inventoryPolicy'], 'K14 groundwork: Tillad oversalg');
		$this->assertSame(['value' => 850.0, 'unit' => 'GRAMS'], $variant['inventoryItem']['measurement']['weight'], 'net weight when there is no gross weight');
		$this->assertFalse($variant['taxable']);
		$this->assertSame('HAVE-1', shopify_catalogue_payload($item, self::VAT, null, null, true)['input']['title'], 'no name: the item number');
	}

	public function testTheHashFollowsOwnedFieldsOnly(): void
	{
		$hash = shopify_catalogue_hash(self::ITEM, self::VAT);

		$this->assertSame($hash, shopify_catalogue_hash(['notes' => 'internal'] + self::ITEM, self::VAT));
		$this->assertNotSame($hash, shopify_catalogue_hash(['beskrivelse' => 'Havestol eg'] + self::ITEM, self::VAT), 'K11: a rename is sent');
		$this->assertNotSame($hash, shopify_catalogue_hash(self::ITEM, ['rate' => 0.0]));
	}

	public function testAnUpdateWithoutALinkedProductIsRefused(): void
	{
		$this->expectException(InvalidArgumentException::class);
		shopify_catalogue_payload(self::ITEM, self::VAT, null, self::remote(), false);
	}
}
