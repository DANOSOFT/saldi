<?php
// tests/shopify/OrderIntakeTest.php
//
// The pure parts of the paid-order intake (SD-742, WP09-1): the order as the intake reads it from
// Shopify, the echo guard, and the validation of a paid order against a tenant context. No
// database. Each test runs in its own process, since orderIntake.php brings the Shopify config,
// links and client functions, which other tests expect to be absent.
//
// History:
// 20261103 CL/SS SD-742: created.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class OrderIntakeTest extends TestCase
{
	private const GID = 'gid://shopify/Order/1001';

	protected function setUp(): void
	{
		require_once __DIR__ . '/../../includes/shopify/services/orderIntake.php';
	}

	/** A paid order: two of SKU X at 100 with 20 off, one Y at 50, freight 39, paid by one card. */
	private static function order(array $over = []): array
	{
		return $over + [
			'gid' => self::GID, 'name' => '#HML1001', 'created_at' => '2026-11-03T10:00:00Z', 'source_name' => 'web',
			'source_identifier' => '', 'tags' => [], 'note' => 'Ring før levering', 'email' => 'k@example.dk', 'phone' => '+4512345678',
			'taxes_included' => true, 'currency' => 'DKK', 'total' => '269.00', 'financial_status' => 'PAID',
			'customer_gid' => 'gid://shopify/Customer/7', 'shipping_address' => null, 'billing_address' => null,
			'lines' => [
				['gid' => 'gid://shopify/LineItem/1', 'sku' => 'X', 'name' => 'Bord', 'quantity' => 2, 'current_quantity' => 2, 'variant_gid' => 'gid://shopify/ProductVariant/11',
					'product_gid' => null, 'original_total' => '200.00', 'discount' => '20.00', 'assigned_location' => 'gid://shopify/Location/1'],
				['gid' => 'gid://shopify/LineItem/2', 'sku' => 'Y', 'name' => 'Stol', 'quantity' => 1, 'current_quantity' => 1, 'variant_gid' => null,
					'product_gid' => null, 'original_total' => '50.00', 'discount' => '0.00', 'assigned_location' => 'gid://shopify/Location/1'],
			],
			'shipping' => [['gid' => 'gid://shopify/ShippingLine/1', 'title' => 'GLS Pakkeshop', 'code' => 'gls', 'amount' => '39.00']],
			'transactions' => [['gid' => 'gid://shopify/OrderTransaction/1', 'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments',
				'amount' => '269.00', 'card' => 'Visa']],
		];
	}

	private static function item(int $id, string $varenr, array $over = []): array
	{
		return $over + ['vare_id' => $id, 'varenr' => $varenr, 'variant_id' => 0, 'variant_barcode' => '', 'stock_key' => $over['stock_key'] ?? $id, 'gruppe' => 1,
			'problem' => null, 'vat_rate' => 25.0, 'salgspris' => 100.0, 'serial' => false, 'variant_item' => false, 'set' => false, 'bom' => []];
	}

	/** A tenant where everything the order names is set up. */
	private static function ctx(array $over = []): array
	{
		return $over + [
			'by_variant' => ['gid://shopify/ProductVariant/11' => self::item(100, 'X')],
			'by_sku' => ['X' => self::item(100, 'X'), 'Y' => self::item(101, 'Y')],
			'items' => [100 => self::item(100, 'X'), 101 => self::item(101, 'Y'), 900 => self::item(900, 'FRAGT'), 901 => self::item(901, 'FRAGT-STD'),
				902 => self::item(902, 'AFHENT')],
			'shipping' => ['GLS Pakkeshop' => ['vare_id' => 900, 'pickup_lager' => null]],
			'freight_default_vare_id' => 901,
			'payments' => ["shopify_payments\x1fVisa" => 'Visa/Dankort'],
			'web_kasse' => '3',
			'pos' => ['kasse_account' => '1000', 'kasse_account_ok' => true, 'kasse_vat' => 'S1', 'card_accounts' => ['visa/dankort' => true], 'fallback_card_account_ok' => true],
			'kreditkort_account' => '58200',
			'kreditkort_ok' => true,
			'locations' => [['lager' => 1, 'rolle' => 'saelgbar', 'prioritet' => 1, 'location_gid' => 'gid://shopify/Location/1'],
				['lager' => 2, 'rolle' => 'saelgbar', 'prioritet' => 2, 'location_gid' => 'gid://shopify/Location/2']],
			'assembly_lager' => 2,
			'stock_milli' => [100 => [1 => 5000], 101 => [1 => 5000]],
			'today' => '2026-11-03',
			'fiscal' => ['from' => '2026-01-01', 'to' => '2026-12-31'],
			'period_closed' => null,
			'currency' => 'DKK',
			'brand_sprog' => 'havemoebelland',
		];
	}

	private static function shop(): array
	{
		return ['id' => 4, 'shop_key' => 'hml', 'afd' => 5, 'legacy_slot' => null, 'web_kasse' => '3', 'default_freight_vare_id' => 901];
	}

	private static function validate(array $order, array $ctx): array
	{
		return shopify_validate_paid_order(self::shop(), $order, $ctx);
	}

	private static function kinds(array $rows): array
	{
		return array_column($rows, 'kind');
	}

	// ----------------------------------------------------------------- the order as read from Shopify

	public function testTheGraphqlOrderBecomesTheIntakesShapeWithTheAssignedLocationPerLine(): void
	{
		$node = [
			'id' => self::GID, 'name' => '#HML1001', 'createdAt' => '2026-11-03T10:00:00Z', 'sourceName' => 'web', 'sourceIdentifier' => null,
			'tags' => ['vip'], 'note' => null, 'email' => ' k@example.dk ', 'phone' => null, 'taxesIncluded' => true, 'currencyCode' => 'DKK',
			'displayFinancialStatus' => 'paid', 'totalPriceSet' => ['shopMoney' => ['amount' => '269.0']], 'customer' => ['id' => 'gid://shopify/Customer/7'],
			'lineItems' => ['nodes' => [
				['id' => 'gid://shopify/LineItem/1', 'sku' => ' X ', 'name' => 'Bord', 'quantity' => 2, 'variant' => ['id' => 'gid://shopify/ProductVariant/11'],
					'product' => null, 'originalTotalSet' => ['shopMoney' => ['amount' => '200']],
					'discountAllocations' => [['allocatedAmountSet' => ['shopMoney' => ['amount' => '15.5']]], ['allocatedAmountSet' => ['shopMoney' => ['amount' => '4.5']]]]],
			]],
			'shippingLines' => ['nodes' => [['id' => 'gid://shopify/ShippingLine/1', 'title' => 'GLS', 'code' => null, 'discountedPriceSet' => ['shopMoney' => ['amount' => '39']]]]],
			'transactions' => [['id' => 'gid://shopify/OrderTransaction/1', 'kind' => 'sale', 'status' => 'success', 'gateway' => 'shopify_payments',
				'amountSet' => ['shopMoney' => ['amount' => '269']], 'paymentDetails' => ['company' => 'Visa']]],
			'fulfillmentOrders' => ['nodes' => [['id' => 'gid://shopify/FulfillmentOrder/1', 'status' => 'OPEN',
				'assignedLocation' => ['location' => ['id' => 'gid://shopify/Location/2']],
				'lineItems' => ['nodes' => [['id' => 'x', 'totalQuantity' => 1, 'lineItem' => ['id' => 'gid://shopify/LineItem/1']]]]],
				['id' => 'gid://shopify/FulfillmentOrder/2', 'status' => 'OPEN', 'assignedLocation' => ['location' => ['id' => 'gid://shopify/Location/1']],
				'lineItems' => ['nodes' => [['id' => 'y', 'totalQuantity' => 1, 'lineItem' => ['id' => 'gid://shopify/LineItem/1']]]]],
				['id' => 'gid://shopify/FulfillmentOrder/3', 'status' => 'CLOSED', 'assignedLocation' => ['location' => ['id' => 'gid://shopify/Location/3']],
				'lineItems' => ['nodes' => [['id' => 'z', 'totalQuantity' => 1, 'lineItem' => ['id' => 'gid://shopify/LineItem/1']]]]],
				['id' => 'gid://shopify/FulfillmentOrder/4', 'status' => 'OPEN', 'assignedLocation' => ['location' => ['id' => 'gid://shopify/Location/4']],
				'lineItems' => ['nodes' => [['id' => 'w', 'totalQuantity' => 0, 'lineItem' => ['id' => 'gid://shopify/LineItem/1']]]]]]],
		];

		$o = shopify_order_from_graphql($node);

		$this->assertSame('269.00', $o['total']);
		$this->assertSame('k@example.dk', $o['email']);
		$this->assertSame('PAID', $o['financial_status']);
		$this->assertSame(['sku' => 'X', 'original_total' => '200.00', 'discount' => '20.00', 'assigned_location' => 'gid://shopify/Location/2'],
			array_intersect_key($o['lines'][0], array_flip(['sku', 'discount', 'original_total', 'assigned_location'])));
		$this->assertSame(['gid://shopify/Location/2', 'gid://shopify/Location/1'], $o['lines'][0]['assigned_locations'],
			'a line Shopify split over two fulfillment orders; a closed one and one holding none of the line are no assignment');
		$this->assertSame('SALE', $o['transactions'][0]['kind']);
		$this->assertSame('Visa', $o['transactions'][0]['card']);
		$this->assertSame('39.00', $o['shipping'][0]['amount']);
	}

	// -------------------------------------------------------------------------------- the echo guard

	/** @return array<string, array{array<string, mixed>, bool}> */
	public static function echoes(): array
	{
		return [
			'source saldi-pos' => [['source_name' => 'saldi-pos'], true],
			'source identifier of this tenant' => [['source_identifier' => 'saldi_7:4711'], true],
			'tag saldi-butik' => [['tags' => ['vip', ' Saldi-Butik ']], true],
			'source identifier of another tenant' => [['source_identifier' => 'saldi_70:4711'], false],
			'a web order' => [[], false],
		];
	}

	#[DataProvider('echoes')]
	public function testSaldisOwnStoreOrdersAreRecognisedByAnyOfTheThreeMarkers(array $over, bool $echo): void
	{
		$this->assertSame($echo, shopify_is_echo(self::order($over), 'saldi_7'));
	}

	// ------------------------------------------------------------------------------- the validation

	public function testAPaidOrderIsValidWithItsPricesPaymentsAndLagre(): void
	{
		$v = self::validate(self::order(), self::ctx());

		$this->assertTrue($v['ok'], json_encode($v['errors']));
		$this->assertSame([], $v['alarms']);
		$this->assertSame([[100, 2, 180.0, 144.0, 72.0, 72.0, 1], [101, 1, 50.0, 40.0, 40.0, 40.0, 1]],
			array_map(fn($l) => [$l['vare_id'], $l['quantity'], $l['line_paid'], $l['line_net'], $l['unit_net'], $l['last_unit_net'], $l['lager']], $v['lines']),
			'the paid price is the original total less its discounts; written net of 25 % VAT');
		$this->assertSame(['vare_id' => 900, 'pickup_lager' => null, 'rate' => 'GLS Pakkeshop', 'amount' => 39.0, 'vat_rate' => 25.0, 'net' => 31.2], $v['freight']);
		$this->assertSame(['Visa/Dankort', '269.00', '', '0.00', '3'],
			[$v['header']['felt_1'], $v['header']['felt_2'], $v['header']['felt_3'], $v['header']['felt_4'], $v['header']['felt_5']],
			'one payment type: a second amount of 0.00, so bogfor() reads the payment (is_numeric on felt_2, felt_4 and felt_5)');
		$this->assertSame('2026-11-03', $v['header']['posting_date']);
		$this->assertSame(['#HML1001', 5, 'havemoebelland', 'Kreditkort'],
			[$v['header']['order_name'], $v['header']['afd'], $v['header']['sprog'], $v['header']['betalingsbet']], 'K22: the order name is kept as is');
	}

	public function testTheVariantLinkComesBeforeTheSku(): void
	{
		$ctx = self::ctx(['by_variant' => ['gid://shopify/ProductVariant/11' => self::item(150, 'X-NY')]]);

		$this->assertSame(150, self::validate(self::order(), $ctx)['lines'][0]['vare_id']);
	}

	public function testAnUnknownSkuIsAK25ErrorAndTheOrderIsNotOk(): void
	{
		$ctx = self::ctx(['by_sku' => ['X' => self::item(100, 'X')]]);

		$v = self::validate(self::order(), $ctx);

		$this->assertFalse($v['ok']);
		$this->assertSame(['order.sku'], self::kinds($v['errors']));
		$this->assertSame(self::GID, $v['errors'][0]['ref']);
		$this->assertStringContainsString('SKU Y is no item in Saldi', $v['errors'][0]['message']);
		$this->assertNull($v['allocation'], 'nothing is allocated for an order that is refused');
	}

	public function testItemsSaldiCannotDeliverHeadlessAreErrors(): void
	{
		$ctx = self::ctx(['by_sku' => ['X' => self::item(100, 'X', ['problem' => 'has item group 1 without a purchase or sales account']),
			'Y' => self::item(101, 'Y', ['serial' => true])], 'by_variant' => []]);

		$v = self::validate(self::order(), $ctx);

		$this->assertSame(['order.account', 'order.serial'], self::kinds($v['errors']));
		$this->assertStringContainsString('without a purchase or sales account', $v['errors'][0]['message']);
	}

	public function testBatchControlledAmbiguousAndCollidingItemsAreRefused(): void
	{
		$batch = self::validate(self::order(), self::ctx(['by_variant' => [], 'by_sku' => ['X' => self::item(100, 'X', ['batch' => true]), 'Y' => self::item(101, 'Y')]]));
		$ambiguous = self::validate(self::order(), self::ctx(['by_variant' => [], 'by_sku' => ['X' => self::item(100, 'X', ['sku_ambiguous' => true]), 'Y' => self::item(101, 'Y')]]));
		$colliding = self::validate(self::order(), self::ctx(['by_variant' => [], 'by_sku' => ['X' => self::item(100, 'X', ['written_as' => 'X', 'written_as_other' => true]),
			'Y' => self::item(101, 'Y')]]));

		$this->assertSame(['order.account'], self::kinds($batch['errors']));
		$this->assertStringContainsString('batch-controlled', $batch['errors'][0]['message']);
		$this->assertSame(['order.sku'], self::kinds($ambiguous['errors']));
		$this->assertStringContainsString('more than one item or variant', $ambiguous['errors'][0]['message']);
		$this->assertSame(['order.account'], self::kinds($colliding['errors']));
		$this->assertStringContainsString("another variant's barcode", $colliding['errors'][0]['message']);
	}

	public function testTheLastUnitIsNeverBelowTheOthers(): void
	{
		$line = ['gid' => 'gid://shopify/LineItem/3', 'sku' => 'Y', 'name' => 'Søm', 'quantity' => 1500, 'current_quantity' => 1500, 'variant_gid' => null,
			'product_gid' => null, 'original_total' => '1.00', 'discount' => '0.00', 'assigned_location' => 'gid://shopify/Location/1'];
		$order = self::order(['lines' => [$line], 'shipping' => [], 'total' => '1.00',
			'transactions' => [['gid' => 't', 'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amount' => '1.00', 'card' => 'Visa']]]);

		$l = self::validate($order, self::ctx(['stock_milli' => [101 => [1 => 2000000]]]))['lines'][0];

		$this->assertSame([0.0, 0.8], [$l['unit_net'], $l['last_unit_net']], 'net 0.800 over 1500 units: 0.000 each, the last takes 0.800');
	}

	public function testAVariantLineNeedsItsVariantsBarcode(): void
	{
		$variant = ['variant_item' => true, 'variant_id' => 7];
		$without = self::validate(self::order(), self::ctx(['by_variant' => ['gid://shopify/ProductVariant/11' => self::item(100, 'X', $variant)]]));
		$with = self::validate(self::order(), self::ctx(['by_variant' => ['gid://shopify/ProductVariant/11' => self::item(100, 'X', $variant + ['variant_barcode' => '5701'])]]));

		$this->assertSame(['order.variant'], self::kinds($without['errors']), 'opret_ordrelinje() finds a variant only by its barcode');
		$this->assertTrue($with['ok']);
		$this->assertSame('5701', $with['lines'][0]['variant_barcode']);
	}

	public function testAnIntakeDateOutsideTheFiscalYearOrInAnotherCurrencyIsRefused(): void
	{
		$v = self::validate(self::order(['currency' => 'EUR']), self::ctx(['today' => '2027-01-02']));
		$old = self::validate(self::order(['created_at' => '2025-12-31T23:30:00Z']), self::ctx());

		$this->assertSame(['order.period', 'order.currency'], self::kinds($v['errors']));
		$this->assertTrue($old['ok'], 'posted on the intake date: an order from last year taken in this one is fine');
	}

	public function testAnIntakeDateInAClosedVatPeriodIsRefused(): void
	{
		$v = self::validate(self::order(), self::ctx(['period_closed' => 'Perioden 11-2026 er lukket for bogfoering']));

		$this->assertSame(['order.period'], self::kinds($v['errors']));
		$this->assertStringContainsString('11-2026 er lukket', $v['errors'][0]['message']);
	}

	public function testPricesWithoutVatAreRefused(): void
	{
		$this->assertSame(['order.taxes'], self::kinds(self::validate(self::order(['taxes_included' => false]), self::ctx())['errors']));
	}

	public function testAnOrderPaidInPartOrRefundedOrEditedInShopifyIsRefused(): void
	{
		$part = self::order(['transactions' => [['gid' => 't', 'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amount' => '200.00', 'card' => 'Visa']]]);
		$refunded = self::order(['transactions' => array_merge(self::order()['transactions'],
			[['gid' => 'r', 'kind' => 'REFUND', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amount' => '50.00', 'card' => 'Visa']])]);
		$lines = self::order()['lines'];
		$lines[1]['current_quantity'] = 0;
		$edited = self::order(['lines' => $lines]);

		$this->assertSame(['order.payment'], self::kinds(self::validate($part, self::ctx())['errors']));
		$this->assertSame(['order.edited', 'order.payment'], self::kinds(self::validate($refunded, self::ctx())['errors']));
		$this->assertContains('order.edited', self::kinds(self::validate($edited, self::ctx())['errors']));
	}

	public function testLinesAndFreightMustAddUpToShopifysTotal(): void
	{
		$v = self::validate(self::order(['total' => '279.00']), self::ctx());

		$this->assertContains('order.total', self::kinds($v['errors']));
	}

	/** @return array<string, array{int, string}> */
	public static function oddLines(): array
	{
		return ['15 for 100.00' => [15, '100.00'], '7 for 99.99' => [7, '99.99'], '24 for 100.00' => [24, '100.00'], '3 for 0.05' => [3, '0.05'],
			'11 for 1234.56' => [11, '1234.56']];
	}

	/** The last unit takes the rounding, and the invoice summed as momsupdat() sums it is what was paid. */
	#[DataProvider('oddLines')]
	public function testTheNetSplitAddsUpAndTheInvoiceIsWhatWasPaid(int $quantity, string $paid): void
	{
		$line = ['gid' => 'gid://shopify/LineItem/3', 'sku' => 'Y', 'name' => 'Stol', 'quantity' => $quantity, 'current_quantity' => $quantity, 'variant_gid' => null,
			'product_gid' => null, 'original_total' => $paid, 'discount' => '0.00', 'assigned_location' => 'gid://shopify/Location/1'];
		$order = self::order(['lines' => [$line], 'shipping' => [], 'total' => $paid,
			'transactions' => [['gid' => 't', 'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amount' => $paid, 'card' => 'Visa']]]);

		$v = self::validate($order, self::ctx(['stock_milli' => [101 => [1 => 50000]]]));
		$l = $v['lines'][0];

		$this->assertSame(number_format($l['line_net'], 3, '.', ''), number_format($l['unit_net'] * ($quantity - 1) + $l['last_unit_net'], 3, '.', ''));
		// as momsupdat(): the net line to three decimals, the VAT of the order's one rate on the sum
		$net = round($l['unit_net'] * ($quantity - 1), 3) + round($l['last_unit_net'], 3);
		$this->assertSame($paid, number_format(round($net, 2) + round($net * 0.25, 2), 2, '.', ''));
		$this->assertSame([], $v['alarms']);
	}

	public function testAnInvoiceThatMissesShopifysTotalIsAnAlarm(): void
	{
		// 25 % never misses; at 12 %, 0.14 paid is 0.125 net, which invoices as 0.13 + 0.02 VAT = 0.15
		$line = ['gid' => 'gid://shopify/LineItem/3', 'sku' => 'Y', 'name' => 'Stol', 'quantity' => 1, 'current_quantity' => 1, 'variant_gid' => null,
			'product_gid' => null, 'original_total' => '0.14', 'discount' => '0.00', 'assigned_location' => 'gid://shopify/Location/1'];
		$order = self::order(['lines' => [$line], 'shipping' => [], 'total' => '0.14',
			'transactions' => [['gid' => 't', 'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amount' => '0.14', 'card' => 'Visa']]]);

		$v = self::validate($order, self::ctx(['by_sku' => ['Y' => self::item(101, 'Y', ['vat_rate' => 12.0])], 'stock_milli' => [101 => [1 => 5000]]]));

		$this->assertTrue($v['ok'], 'S09b writes the sum and VAT itself; the difference is reported, not refused');
		$this->assertSame(['order.rounding'], self::kinds($v['alarms']));
		$this->assertStringContainsString('comes to 0.15', $v['alarms'][0]['message']);
	}

	public function testSetComponentsAndTheFreightItemAreCheckedLikeLineItems(): void
	{
		$set = self::item(200, 'SAET', ['set' => true, 'bom' => [['bom_id' => 11, 'vare_id' => 100, 'quantity_milli' => 1000]]]);
		$order = self::order(['lines' => [['gid' => 'gid://shopify/LineItem/9', 'sku' => 'SAET', 'name' => 'Sæt', 'quantity' => 1, 'current_quantity' => 1,
			'variant_gid' => null, 'product_gid' => null, 'original_total' => '230.00', 'discount' => '0.00', 'assigned_location' => null]]]);
		$items = self::ctx()['items'];
		$items[100] = self::item(100, 'X', ['serial' => true]);
		$items[900] = self::item(900, 'FRAGT', ['problem' => 'has no item group 5 in the fiscal year']);

		$v = self::validate($order, self::ctx(['by_sku' => ['SAET' => $set], 'by_variant' => [], 'items' => $items]));

		$this->assertSame(['order.component', 'order.freight'], self::kinds($v['errors']));
		$this->assertStringContainsString('carries serial numbers', $v['errors'][0]['message']);
		$this->assertStringContainsString('has no item group 5', $v['errors'][1]['message']);
	}

	public function testATillSaleNeedsTheKassesAccountsAndACardAccountPerType(): void
	{
		$pos = self::ctx()['pos'];
		$noCash = self::validate(self::order(), self::ctx(['pos' => ['kasse_account_ok' => false] + $pos]));
		$noVat = self::validate(self::order(), self::ctx(['pos' => ['kasse_vat' => ''] + $pos]));
		$noCard = self::validate(self::order(), self::ctx(['pos' => ['card_accounts' => [], 'fallback_card_account_ok' => false] + $pos]));
		$fallback = self::validate(self::order(), self::ctx(['pos' => ['card_accounts' => []] + $pos]));
		$namedWithoutAccount = self::validate(self::order(), self::ctx(['pos' => ['card_accounts' => ['visa/dankort' => false]] + $pos]));
		$notANumber = self::validate(self::order(), self::ctx(['web_kasse' => 'web']));

		$this->assertSame(['config.pos'], self::kinds($noCash['errors']));
		$this->assertSame(['config.pos'], self::kinds($noVat['errors']));
		$this->assertSame(['config.pos'], self::kinds($noCard['errors']));
		$this->assertTrue($fallback['ok'], 'the account for other cards takes an unlisted type');
		$this->assertSame(['config.pos'], self::kinds($namedWithoutAccount['errors']),
			'a type the POS settings name posts to its own account, even an empty one; the other-cards account does not help it');
		$this->assertSame(['config.pos'], self::kinds($notANumber['errors']));
	}

	public function testAnOrderWithoutACustomerIsRefused(): void
	{
		$this->assertSame(['order.customer'], self::kinds(self::validate(self::order(['customer_gid' => null]), self::ctx())['errors']));
	}

	public function testAnUnmappedFreightRateTakesTheShopsDefaultWithAnAlarm(): void
	{
		$v = self::validate(self::order(), self::ctx(['shipping' => []]));

		$this->assertTrue($v['ok']);
		$this->assertSame(901, $v['freight']['vare_id']);
		$this->assertSame(['order.freight'], self::kinds($v['alarms']), 'K24');
	}

	public function testAnUnmappedFreightRateWithoutADefaultIsAnError(): void
	{
		$v = self::validate(self::order(), self::ctx(['shipping' => [], 'freight_default_vare_id' => 0]));

		$this->assertSame(['order.freight'], self::kinds($v['errors']));
	}

	public function testAClickAndCollectRateRecordsTheChosenShop(): void
	{
		$v = self::validate(self::order(), self::ctx(['shipping' => ['GLS Pakkeshop' => ['vare_id' => 902, 'pickup_lager' => 2]]]));

		$this->assertSame([902, 2], [$v['freight']['vare_id'], $v['freight']['pickup_lager']], 'K24');
	}

	public function testAnUnknownCardIsUkendtKortWithAnAlarm(): void
	{
		$v = self::validate(self::order(), self::ctx(['payments' => []]));

		$this->assertTrue($v['ok']);
		$this->assertSame('Ukendt kort', $v['header']['felt_1']);
		$this->assertSame(['order.payment'], self::kinds($v['alarms']));
	}

	public function testTwoPaymentTypesFillBothPairsAndAThirdFallsBackToKreditkort(): void
	{
		$tx = fn(string $gw, string $amount) => ['gid' => "t-$gw", 'kind' => 'CAPTURE', 'status' => 'SUCCESS', 'gateway' => $gw, 'amount' => $amount, 'card' => ''];
		$payments = ["gavekort\x1f" => 'Gavekort', "mobilepay\x1f" => 'MobilePay', "shopify_payments\x1fVisa" => 'Visa/Dankort'];
		$two = self::order(['transactions' => [$tx('gavekort', '100.00'), $tx('mobilepay', '169.00'),
			['gid' => 'failed', 'kind' => 'SALE', 'status' => 'FAILURE', 'gateway' => 'shopify_payments', 'amount' => '269.00', 'card' => 'Visa']]]);
		$three = self::order(['transactions' => [$tx('gavekort', '100.00'), $tx('mobilepay', '100.00'),
			['gid' => 't3', 'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amount' => '69.00', 'card' => 'Visa']]]);

		$pos = ['card_accounts' => ['gavekort' => true, 'mobilepay' => true, 'visa/dankort' => true]] + self::ctx()['pos'];
		$v2 = self::validate($two, self::ctx(['payments' => $payments, 'pos' => $pos]));
		$v3 = self::validate($three, self::ctx(['payments' => $payments, 'pos' => $pos]));

		$this->assertSame(['Gavekort', '100.00', 'MobilePay', '169.00', '3'],
			[$v2['header']['felt_1'], $v2['header']['felt_2'], $v2['header']['felt_3'], $v2['header']['felt_4'], $v2['header']['felt_5']],
			'a failed transaction is no payment');
		$this->assertSame(['', '', '', '', ''],
			[$v3['header']['felt_1'], $v3['header']['felt_2'], $v3['header']['felt_3'], $v3['header']['felt_4'], $v3['header']['felt_5']]);
		$this->assertSame(['order.payment'], self::kinds($v3['alarms']), 'three types post as Kreditkort with an alarm');
	}

	public function testWithoutAWebKasseTheOrderPostsAsKreditkortWithAnAlarm(): void
	{
		$v = self::validate(self::order(), self::ctx(['web_kasse' => '']));

		$this->assertTrue($v['ok']);
		$this->assertSame('', $v['header']['felt_1']);
		$this->assertSame(['order.payment'], self::kinds($v['alarms']));
	}

	public function testWithoutAValidKreditkortAccountNoOrderCanBePosted(): void
	{
		$v = self::validate(self::order(), self::ctx(['kreditkort_ok' => false]));

		$this->assertSame(['config.kreditkort'], self::kinds($v['errors']));
	}

	public function testAnOrderWithoutASuccessfulPaymentIsRefused(): void
	{
		$v = self::validate(self::order(['transactions' => []]), self::ctx());

		$this->assertSame(['order.payment'], self::kinds($v['errors']));
	}

	public function testAShortageIsInvoicedTaggedAndAlarmed(): void
	{
		$v = self::validate(self::order(), self::ctx(['stock_milli' => [100 => [1 => 1000], 101 => [1 => 5000]]]));

		$this->assertTrue($v['ok']);
		$this->assertContains('saldi-mangler-lager', $v['tags']);
		$this->assertContains('order.shortage', self::kinds($v['alarms']));
	}

	public function testAnAssignmentToADisplayLagerIsDrawnByPriorityWithAnAlarm(): void
	{
		$ctx = self::ctx(['locations' => [['lager' => 1, 'rolle' => 'udstilling', 'prioritet' => null, 'location_gid' => 'gid://shopify/Location/1'],
			['lager' => 2, 'rolle' => 'saelgbar', 'prioritet' => 1, 'location_gid' => 'gid://shopify/Location/2']],
			'stock_milli' => [100 => [2 => 5000], 101 => [2 => 5000]]]);

		$v = self::validate(self::order(), $ctx);

		$this->assertSame([2, 2], array_column($v['lines'], 'lager'), 'K16: never drawn from a display lager');
		$this->assertSame(['order.assignment', 'order.assignment'], self::kinds($v['alarms']));
	}

	public function testALineShopifySplitOverSeveralLocationsIsAnAlarm(): void
	{
		$lines = self::order()['lines'];
		$lines[0]['assigned_locations'] = ['gid://shopify/Location/1', 'gid://shopify/Location/2'];

		$v = self::validate(self::order(['lines' => $lines]), self::ctx());

		$this->assertTrue($v['ok']);
		$this->assertSame(['order.assignment'], self::kinds($v['alarms']));
		$this->assertStringContainsString('split over 2 fulfillment locations', $v['alarms'][0]['message']);
	}

	public function testASplitSetGetsItsLagerPlanAndTheSplitTag(): void
	{
		$set = self::item(200, 'SAET', ['set' => true, 'bom' => [['bom_id' => 11, 'vare_id' => 100, 'quantity_milli' => 1000],
			['bom_id' => 12, 'vare_id' => 101, 'quantity_milli' => 2000]]]);
		$order = self::order(['total' => '269.00', 'lines' => [['gid' => 'gid://shopify/LineItem/9', 'sku' => 'SAET', 'name' => 'Havesæt', 'quantity' => 2,
			'current_quantity' => 2, 'variant_gid' => null, 'product_gid' => null, 'original_total' => '230.00', 'discount' => '0.00',
			'assigned_location' => 'gid://shopify/Location/1']]]);
		// K55: lager 1 has 3 X / 2 Y, lager 2 has 6 Y
		$ctx = self::ctx(['by_sku' => ['SAET' => $set], 'by_variant' => [], 'stock_milli' => [100 => [1 => 3000], 101 => [1 => 2000, 2 => 6000]]]);

		$v = self::validate($order, $ctx);

		$line = $v['lines'][0];
		$this->assertTrue($line['split']);
		$this->assertSame(2, $line['lager'], 'the assembly lager');
		$this->assertSame([[11, 1, 2000], [12, 1, 2000], [12, 2, 2000]], array_map(fn($d) => [$d['bom_id'], $d['lager'], $d['quantity_milli']], $line['lager_plan']));
		$this->assertContains('saldi-saet-delt', $v['tags']);
		$this->assertSame(230.0, $line['line_paid']);
		$this->assertSame(184.0, $line['line_net'], 'the net set price for both sets; S09b passes line_net / quantity to opret_saet()');
	}

	public function testTwoVariantsOfOneItemAreAllocatedFromTheirOwnStock(): void
	{
		$a = self::item(100, 'X', ['variant_item' => true, 'variant_id' => 1, 'variant_barcode' => 'A', 'stock_key' => SHOPIFY_INTAKE_VARIANT_STOCK + 1]);
		$b = self::item(100, 'X', ['variant_item' => true, 'variant_id' => 2, 'variant_barcode' => 'B', 'stock_key' => SHOPIFY_INTAKE_VARIANT_STOCK + 2]);
		$line = fn(string $sku) => ['gid' => "gid://shopify/LineItem/$sku", 'sku' => $sku, 'name' => $sku, 'quantity' => 1, 'current_quantity' => 1,
			'variant_gid' => null, 'product_gid' => null, 'original_total' => '50.00', 'discount' => '0.00', 'assigned_location' => 'gid://shopify/Location/1'];
		$order = self::order(['lines' => [$line('A'), $line('B')], 'shipping' => [], 'total' => '100.00',
			'transactions' => [['gid' => 't', 'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amount' => '100.00', 'card' => 'Visa']]]);
		// A is only on lager 2, B only on lager 1
		$stock = [100 => [1 => 1000, 2 => 1000], SHOPIFY_INTAKE_VARIANT_STOCK + 1 => [2 => 1000], SHOPIFY_INTAKE_VARIANT_STOCK + 2 => [1 => 1000]];

		$v = self::validate($order, self::ctx(['by_sku' => ['A' => $a, 'B' => $b], 'by_variant' => [], 'stock_milli' => $stock]));

		$this->assertSame([2, 1], array_column($v['lines'], 'lager'));
		$this->assertSame([], $v['alarms'], 'no shortage: each variant has the unit it needs, on another lager');
		$this->assertSame([100, 100], array_column($v['lines'], 'vare_id'), 'the lines keep the item; only the stock is the variant\'s');
	}

	public function testASetMustHaveOneVatRateForItselfAndItsComponents(): void
	{
		$set = self::item(200, 'SAET', ['vat_rate' => 0.0, 'set' => true, 'bom' => [['bom_id' => 11, 'vare_id' => 100, 'quantity_milli' => 1000]]]);
		$order = self::order(['lines' => [['gid' => 'gid://shopify/LineItem/9', 'sku' => 'SAET', 'name' => 'Sæt', 'quantity' => 1, 'current_quantity' => 1,
			'variant_gid' => null, 'product_gid' => null, 'original_total' => '230.00', 'discount' => '0.00', 'assigned_location' => null]]]);

		$v = self::validate($order, self::ctx(['by_sku' => ['SAET' => $set], 'by_variant' => []]));

		$this->assertSame(['order.component'], self::kinds($v['errors']));
		$this->assertStringContainsString('a set must have one rate', $v['errors'][0]['message']);
	}

	/** opret_saet() with a plan refuses a nested set and components without a price; the intake refuses them before writing. */
	public function testASetWithANestedComponentOrUnpricedComponentsIsRefused(): void
	{
		$set = self::item(200, 'SAET', ['set' => true, 'bom' => [['bom_id' => 11, 'vare_id' => 100, 'quantity_milli' => 1000]]]);
		$order = self::order(['lines' => [['gid' => 'gid://shopify/LineItem/9', 'sku' => 'SAET', 'name' => 'Sæt', 'quantity' => 1, 'current_quantity' => 1,
			'variant_gid' => null, 'product_gid' => null, 'original_total' => '230.00', 'discount' => '0.00', 'assigned_location' => null]]]);
		$items = self::ctx()['items'];
		$priced = $items;
		$priced[100]['salgspris'] = 100.0;
		$nested = $priced;
		$nested[100]['bom'] = [['bom_id' => 21, 'vare_id' => 101, 'quantity_milli' => 1000]];
		$unpriced = $items;
		$unpriced[100]['salgspris'] = 0.0;
		$ctx = fn(array $items) => self::ctx(['by_sku' => ['SAET' => $set], 'by_variant' => [], 'items' => $items, 'stock_milli' => [100 => [1 => 5000]]]);

		$ok = self::validate($order, $ctx($priced));
		$nestedErrors = self::validate($order, $ctx($nested))['errors'];
		$unpricedErrors = self::validate($order, $ctx($unpriced))['errors'];
		$tiny = $items;
		$tiny[100]['salgspris'] = 0.01;
		$tinySet = ['bom' => [['bom_id' => 11, 'vare_id' => 100, 'quantity_milli' => 10]]] + $set;
		$tinyErrors = self::validate($order, self::ctx(['by_sku' => ['SAET' => $tinySet], 'by_variant' => [], 'items' => $tiny, 'stock_milli' => [100 => [1 => 5000]]]))['errors'];

		$this->assertTrue($ok['ok'], json_encode($ok['errors']));
		$this->assertSame(['order.component'], self::kinds($nestedErrors));
		$this->assertStringContainsString('is a set itself', $nestedErrors[0]['message']);
		$this->assertSame(['order.component'], self::kinds($unpricedErrors));
		$this->assertStringContainsString('have no price', $unpricedErrors[0]['message']);
		$this->assertSame(['order.component'], self::kinds($tinyErrors), '0.010 x 0.01 rounds to 0.000 in opret_saet(), so that set has no price either');
	}

	public function testALineIsWrittenAsOneRowPerLagerWithTheLastUnitApart(): void
	{
		$line = ['set' => false, 'quantity' => 5, 'lager' => 1, 'line_net' => 33.334, 'unit_net' => 6.667, 'last_unit_net' => 6.666,
			'segments' => [['lager' => 1, 'quantity_milli' => 3000, 'shortage_milli' => 0], ['lager' => 2, 'quantity_milli' => 2000, 'shortage_milli' => 0]]];

		$rows = shopify_intake_line_rows($line);

		$this->assertSame([[1, 3.0, 20.001], [2, 1.0, 6.667], [2, 1.0, 6.666]], array_map(fn($r) => [$r['lager'], $r['quantity'], $r['net']], $rows));
		$this->assertSame('33.334', number_format(array_sum(array_column($rows, 'net')), 3, '.', ''));
	}

	/** The allocation counts in thousandths: 1.5 + 0.5 of a 2-unit line is still two units, never one. */
	public function testFractionalStockNeverDropsAUnit(): void
	{
		$line = fn(array $milli) => ['set' => false, 'quantity' => 3, 'lager' => 1, 'line_net' => 240.0, 'unit_net' => 80.0, 'last_unit_net' => 80.0,
			'segments' => array_map(fn($m, $i) => ['lager' => $i + 1, 'quantity_milli' => $m, 'shortage_milli' => 0], $milli, array_keys($milli))];

		$this->assertSame([[1, 1.0], [2, 2.0]], array_map(fn($r) => [$r['lager'], $r['quantity']], shopify_intake_line_rows($line([1500, 1500]))));
		$this->assertSame([[1, 1.0], [2, 1.0], [3, 1.0]], array_map(fn($r) => [$r['lager'], $r['quantity']], shopify_intake_line_rows($line([1500, 500, 1000]))),
			'units from the running total: 1.5, 2.0 and 3.0 make one unit each');
		$this->assertSame([[3, 3.0]], array_map(fn($r) => [$r['lager'], $r['quantity']], shopify_intake_line_rows($line([400, 400, 2200]))),
			'a lager the running total gives no whole unit gets no row');

		$order = self::order(['lines' => [['gid' => 'gid://shopify/LineItem/1', 'sku' => 'X', 'name' => 'Bord', 'quantity' => 2, 'current_quantity' => 2,
			'variant_gid' => null, 'product_gid' => null, 'original_total' => '200.00', 'discount' => '0.00', 'assigned_location' => 'gid://shopify/Location/1']],
			'shipping' => [], 'total' => '200.00',
			'transactions' => [['gid' => 't', 'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amount' => '200.00', 'card' => 'Visa']]]);
		$v = self::validate($order, self::ctx(['by_variant' => [], 'stock_milli' => [100 => [1 => 1500, 2 => 5000]]]));

		$this->assertTrue($v['ok']);
		$this->assertSame(2.0, array_sum(array_column(shopify_intake_line_rows($v['lines'][0]), 'quantity')));
		$this->assertSame([], $v['alarms'], 'the invoice is what was paid');
	}

	/** opret_saet() writes a row per component and lager at one discount, with VAT per row; a VAT-free line makes the order's VAT per row. */
	public function testTheRoundingAlarmSeesASetsComponentRows(): void
	{
		$set = self::item(200, 'SAET', ['set' => true, 'bom' => [['bom_id' => 11, 'vare_id' => 100, 'quantity_milli' => 1000],
			['bom_id' => 12, 'vare_id' => 101, 'quantity_milli' => 1000]]]);
		$items = self::ctx()['items'];
		$items[100]['salgspris'] = 50.0;
		$items[101]['salgspris'] = 50.0;
		$items[102] = self::item(102, 'Z', ['vat_rate' => 0.0]);
		$lines = [
			['gid' => 'gid://shopify/LineItem/9', 'sku' => 'SAET', 'name' => 'Sæt', 'quantity' => 1, 'current_quantity' => 1, 'variant_gid' => null,
				'product_gid' => null, 'original_total' => '100.03', 'discount' => '0.00', 'assigned_location' => 'gid://shopify/Location/1'],
			['gid' => 'gid://shopify/LineItem/2', 'sku' => 'Z', 'name' => 'Gavekort', 'quantity' => 1, 'current_quantity' => 1, 'variant_gid' => null,
				'product_gid' => null, 'original_total' => '1.00', 'discount' => '0.00', 'assigned_location' => 'gid://shopify/Location/1'],
		];
		$order = self::order(['lines' => $lines, 'shipping' => [], 'total' => '101.03',
			'transactions' => [['gid' => 't', 'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amount' => '101.03', 'card' => 'Visa']]]);

		$v = self::validate($order, self::ctx(['by_variant' => [], 'by_sku' => ['SAET' => $set, 'Z' => $items[102]], 'items' => $items,
			'stock_milli' => [100 => [1 => 5000], 101 => [1 => 5000], 102 => [1 => 5000]]]));

		$this->assertTrue($v['ok'], json_encode($v['errors']));
		$this->assertSame(['order.rounding'], self::kinds($v['alarms']));
		$this->assertStringContainsString('comes to 101.02', $v['alarms'][0]['message'], 'two rows of 40.012 with 10.00 VAT each, the set line 0, plus 1.00');
	}

	/** Mixed rates take the VAT per row, so a line split over two lagre rounds twice. */
	public function testTheRoundingAlarmSeesTheRowsALineIsSplitInto(): void
	{
		$free = self::item(102, 'Z', ['vat_rate' => 0.0]);
		$lines = [
			['gid' => 'gid://shopify/LineItem/1', 'sku' => 'X', 'name' => 'Bord', 'quantity' => 2, 'current_quantity' => 2, 'variant_gid' => null,
				'product_gid' => null, 'original_total' => '100.03', 'discount' => '0.00', 'assigned_location' => 'gid://shopify/Location/1'],
			['gid' => 'gid://shopify/LineItem/2', 'sku' => 'Z', 'name' => 'Gavekort', 'quantity' => 1, 'current_quantity' => 1, 'variant_gid' => null,
				'product_gid' => null, 'original_total' => '1.00', 'discount' => '0.00', 'assigned_location' => 'gid://shopify/Location/1'],
		];
		$order = self::order(['lines' => $lines, 'shipping' => [], 'total' => '101.03',
			'transactions' => [['gid' => 't', 'kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments', 'amount' => '101.03', 'card' => 'Visa']]]);
		$ctx = self::ctx(['by_variant' => [], 'by_sku' => ['X' => self::item(100, 'X'), 'Z' => $free], 'stock_milli' => [100 => [1 => 1000, 2 => 1000], 102 => [1 => 5000]]]);

		$v = self::validate($order, $ctx);

		$this->assertSame([1, 2], array_column($v['lines'][0]['segments'], 'lager'), 'one unit from each lager');
		$this->assertSame(['order.rounding'], self::kinds($v['alarms']));
		$this->assertStringContainsString('comes to 101.02', $v['alarms'][0]['message'], '2 rows of 40.012 with 10.00 VAT each, plus 1.00 without');
		$this->assertSame(25.0, $v['header']['momssats'], 'the highest rate of the lines, for S09b to write and lend');
	}

	// ---------------------------------------------------------------------------------- the fetch

	public function testTheFetchReadsTheOrderAndRefusesAMissingOne(): void
	{
		require_once __DIR__ . '/support/FakeShopify.php';
		$node = ['id' => self::GID, 'name' => '#HML1001', 'totalPriceSet' => ['shopMoney' => ['amount' => '10']], 'lineItems' => ['nodes' => []],
			'shippingLines' => ['nodes' => []], 'transactions' => [], 'fulfillmentOrders' => ['nodes' => []]];
		$large = ['lineItems' => ['pageInfo' => ['hasNextPage' => true], 'nodes' => []]] + $node;
		$fake = new FakeShopify([FakeShopify::ok(['order' => $node]), FakeShopify::ok(['order' => null]), FakeShopify::json(['errors' => [['message' => 'Access denied']]], 403),
			FakeShopify::ok(['order' => $large])]);
		$rt = ['graphql' => $fake->opts()];

		$order = shopify_fetch_paid_order(FakeShopify::shop(), '1001', $rt);
		$this->assertSame(['gid' => self::GID, 'total' => '10.00'], array_intersect_key($order, ['gid' => 1, 'total' => 1]));
		$this->assertSame(self::GID, $fake->calls[0]['body']['variables']['id'], 'a numeric id is sent as the order GID');

		foreach (['not_found', 'http_403', 'too_large'] as $expected) {
			try {
				shopify_fetch_paid_order(FakeShopify::shop(), self::GID, $rt);
				$this->fail("expected $expected");
			} catch (ShopifyOrderReadError $e) {
				$this->assertSame($expected, $e->error);
				$this->assertFalse($e->retryable);
			}
		}
	}
}
