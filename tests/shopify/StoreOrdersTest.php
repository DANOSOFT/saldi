<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class StoreOrdersTest extends TestCase
{
	private const LINKS = ['10:0' => 'gid://shopify/ProductVariant/10', '20:0' => 'gid://shopify/ProductVariant/20', '21:0' => 'gid://shopify/ProductVariant/21'];

	protected function setUp(): void
	{
		require_once __DIR__ . '/../../includes/shopify/services/storeOrders.php';
		shopify_store_orders_require();
	}

	private static function line(array $over = []): array
	{
		return $over + ['id' => 1, 'posnr' => 1, 'vare_id' => 10, 'variant_id' => '', 'varenr' => 'V10', 'beskrivelse' => 'Stol', 'enhed' => 'stk',
			'antal' => 1, 'pris' => 100, 'rabat' => 0, 'rabatart' => '', 'procent' => null, 'momsfri' => '', 'omvbet' => '', 'momssats' => 25,
			'bogf_konto' => 0, 'saet' => 0, 'samlevare' => '', 'lager' => 1, 'lager_navn' => 'Butik'];
	}

	/** One Stol at 100 + 25 VAT, paid 125 by card, by debtor 5, taken along. */
	private static function snap(array $order = [], ?array $lines = null, ?array $payments = null): array
	{
		return [
			'order' => $order + ['id' => 77, 'db' => 'tenant_1', 'art' => 'PO', 'status' => 3, 'fakturanr' => '5001', 'fakturadate' => '2026-11-12', 'tidspkt' => '10:30',
				'sum' => 100, 'moms' => 25, 'momssats' => 25, 'valuta' => 'DKK', 'felt_1' => 'Dankort', 'konto_id' => 5,
				'email' => 'k@example.dk', 'phone' => '12345678', 'land' => 'Danmark', 'kontakt' => 'Hans Jensen', 'firmanavn' => 'Hans Jensen',
				'addr1' => 'Havevej 1', 'addr2' => '', 'postnr' => '2000', 'bynavn' => 'Frederiksberg',
				'lev_navn' => '', 'lev_kontakt' => '', 'lev_addr1' => '', 'lev_addr2' => '', 'lev_postnr' => '', 'lev_bynavn' => '', 'lev_land' => '',
				'lev_type' => null, 'lev_valg' => null, 'shopify_vaerdi' => null, 'leveringsmaade' => '', 'oensket_levdato' => null],
			'lines' => $lines ?? [self::line()],
			'payments' => $payments ?? [['betalingstype' => 'Dankort', 'amount' => 125, 'valutakurs' => 100]],
			'links' => self::LINKS,
			'shop_id' => 1,
		];
	}

	private static function shop(array $over = []): array
	{
		return $over + ['id' => '1', 'shop_key' => 'hml', 'go_live_at' => '2026-11-10 08:00:00', 'customers_enabled' => 't'];
	}

	private static function payload(array $snap, array $customer = ['gid' => 'gid://shopify/Customer/9', 'contact' => true]): array
	{
		return shopify_store_orders_build_payload($snap, $customer, shopify_store_orders_project_lines($snap, $snap['links']), shopify_store_orders_project_payments($snap), null);
	}

	/** @return array{0: int, 1: int} the lines' total and their VAT, in øre */
	private static function totals(array $lines): array
	{
		$total = $tax = 0;
		foreach ($lines as $l) {
			$total += $l['quantity'] * (int)round((float)$l['priceSet']['shopMoney']['amount'] * 100);
			foreach ($l['taxLines'] ?? [] as $t) {
				$tax += (int)round((float)$t['priceSet']['shopMoney']['amount'] * 100);
			}
		}
		return [$total, $tax];
	}

	public static function deliveryModes(): array
	{
		$choice = fn(string $type, string $navn, ?string $value = null): array => ['lev_type' => $type, 'lev_valg' => $navn, 'shopify_vaerdi' => $value];
		return [
			'taget med' => [[], ['fulfillmentStatus' => 'FULFILLED'], ['customAttributes', 'shippingAddress', 'note']],
			'afhentning' => [$choice('afhentning', 'Afhentning i butik'), ['customAttributes' => [['key' => 'Leveringsmaade', 'value' => 'Afhentning i butik']]],
				['fulfillmentStatus', 'shippingAddress', 'note']],
			'levering with a date' => [$choice('levering', 'Levering', 'EasyRoutes') + ['oensket_levdato' => '2026-11-20'],
				['customAttributes' => [['key' => 'Leveringsmaade', 'value' => 'EasyRoutes'], ['key' => 'Leveringsdato', 'value' => '2026-11-20']]],
				['fulfillmentStatus', 'note']],
			'levering by Danske Fragtmænd' => [$choice('levering', 'Fragtmand', 'Danske Fragtmænd') + ['oensket_levdato' => '2026-11-21'],
				['customAttributes' => [['key' => 'Leveringsmaade', 'value' => 'Danske Fragtmænd'], ['key' => 'Leveringsdato', 'value' => '2026-11-21']]],
				['fulfillmentStatus', 'note']],
			'levering without a date' => [$choice('levering', 'Levering', 'EasyRoutes'),
				['customAttributes' => [['key' => 'Leveringsmaade', 'value' => 'EasyRoutes']], 'note' => 'Leveringsdato aftales med kunden'],
				['fulfillmentStatus']],
		];
	}

	#[DataProvider('deliveryModes')]
	public function testEachDeliveryTypeIsAPaidStoreOrder(array $order, array $expected, array $absent): void
	{
		$v = self::payload(self::snap($order));

		$this->assertSame(['inventoryBehaviour' => 'BYPASS', 'sendReceipt' => true], $v['options'], 'no Shopify stock is drawn; the confirmation goes to the email');
		$o = $v['order'];
		$this->assertSame(['saldi-pos', 'tenant_1:77', ['saldi-butik', 'saldi-po-tenant_1-77'], true, 'DKK'],
			[$o['sourceName'], $o['sourceIdentifier'], $o['tags'], $o['taxesIncluded'], $o['currency']]);
		$this->assertSame([['kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'Dankort', 'amountSet' => ['shopMoney' => ['amount' => '125.00', 'currencyCode' => 'DKK']]]], $o['transactions'], 'paid');
		$this->assertSame(['id' => 'gid://shopify/Customer/9'], $o['customer']['toAssociate']);
		$this->assertSame(['k@example.dk', '+4512345678'], [$o['email'], $o['phone']]);
		$this->assertSame([['fakturanr', '5001'], ['kassebon', '5001']], array_map(fn(array $m): array => [$m['key'], $m['value']], $o['metafields']), 'K54');
		foreach ($expected as $key => $value) {
			$this->assertSame($value, $o[$key], $key);
		}
		foreach ($absent as $key) {
			$this->assertArrayNotHasKey($key, $o, "$key does not go with this delivery type");
		}
		if (isset($o['shippingAddress'])) {
			$this->assertSame(['firstName' => 'Hans', 'lastName' => 'Jensen', 'address1' => 'Havevej 1', 'zip' => '2000', 'city' => 'Frederiksberg', 'phone' => '+4512345678', 'countryCode' => 'DK'],
				$o['shippingAddress']);
		}
	}

	public function testADeliveryGoesToItsDeliveryAddressWhenItHasOne(): void
	{
		$o = self::payload(self::snap(['lev_type' => 'levering', 'lev_navn' => 'Sommerhus', 'lev_kontakt' => 'Grete Hansen', 'lev_addr1' => 'Strandvej 9',
			'lev_postnr' => '3000', 'lev_bynavn' => 'Helsingør', 'lev_land' => 'DK', 'oensket_levdato' => '2026-11-20']))['order'];

		$this->assertSame(['firstName' => 'Grete', 'lastName' => 'Hansen', 'company' => 'Sommerhus', 'address1' => 'Strandvej 9', 'zip' => '3000', 'city' => 'Helsingør',
			'phone' => '+4512345678', 'countryCode' => 'DK'], $o['shippingAddress']);
	}

	public function testAParkedCustomerSendsNoEmailOrPhoneSoShopifyAssociatesNoOne(): void
	{
		$v = self::payload(self::snap(), ['gid' => null, 'contact' => false]);

		foreach (['customer', 'email', 'phone'] as $key) {
			$this->assertArrayNotHasKey($key, $v['order']);
		}
		$this->assertFalse($v['options']['sendReceipt']);
	}

	public function testTotalsAreExactToTheOreWithDiscountsAndSetCorrections(): void
	{
		$lines = [
			self::line(['antal' => 3, 'pris' => 33.33]),
			self::line(['id' => 2, 'vare_id' => 20, 'varenr' => 'A', 'beskrivelse' => 'Bord', 'pris' => 100, 'rabat' => 10, 'saet' => 1]),
			self::line(['id' => 3, 'vare_id' => 21, 'varenr' => 'B', 'beskrivelse' => 'Bænk', 'pris' => 50, 'rabat' => 10, 'saet' => 1]),
			self::line(['id' => 4, 'vare_id' => 22, 'varenr' => 'S', 'beskrivelse' => 'Havesæt', 'pris' => -15.01, 'saet' => 1, 'samlevare' => 'on']),
			self::line(['id' => 5, 'vare_id' => 10, 'pris' => 40, 'rabat' => 5, 'rabatart' => 'amount']),
			self::line(['id' => 6, 'vare_id' => 0, 'varenr' => '', 'beskrivelse' => 'Rabat', 'pris' => -20, 'bogf_konto' => 1000]),
		];
		// net: 99.99 + 90 + 45 - 15.01 + 35 - 20 = 234.98; VAT on all but the set correction: (99.99 + 135 + 35 - 20) * 0.25 = 62.4975
		$snap = self::snap(['sum' => 234.98, 'moms' => 62.50], $lines, [['betalingstype' => 'Dankort', 'amount' => 297.48, 'valutakurs' => 100]]);

		$out = shopify_store_orders_project_lines($snap, self::LINKS);

		$this->assertSame([29748, 6250], self::totals($out), 'the lines are the posted total and VAT to the øre');
		$this->assertSame(['gid://shopify/ProductVariant/10', 'gid://shopify/ProductVariant/10', 'gid://shopify/ProductVariant/20', 'gid://shopify/ProductVariant/21', 'gid://shopify/ProductVariant/10'],
			array_column($out, 'variantId'), 'the set correction and the negative line are folded into the others; 3 stole that do not divide make two lines');
		$this->assertSame([2, 1], [$out[0]['quantity'], $out[1]['quantity']]);
		$this->assertSame(1, (int)round((float)$out[1]['priceSet']['shopMoney']['amount'] * 100) - (int)round((float)$out[0]['priceSet']['shopMoney']['amount'] * 100));
		foreach ($out as $l) {
			$this->assertGreaterThan(0, (float)$l['priceSet']['shopMoney']['amount'], 'no line has a negative price');
			$this->assertSame([['name' => 'Lager', 'value' => 'Butik']], $l['properties']);
		}
	}

	public function testAReturnLineIsFoldedIntoLinesOfItsOwnVatRateSoTheVatStaysAsPosted(): void
	{
		$lines = [self::line(['id' => 1, 'posnr' => 1]), self::line(['id' => 2, 'posnr' => 2, 'vare_id' => 11, 'varenr' => 'V11', 'momsfri' => 'on']),
			self::line(['id' => 3, 'posnr' => 3, 'antal' => -1, 'pris' => 50])];
		$out = shopify_store_orders_project_lines(self::snap(['sum' => 150, 'moms' => 12.5], $lines), self::LINKS);
		$this->assertSame([16250, 1250], self::totals($out), 'the posted total and VAT');
		$exempt = array_values(array_filter($out, fn(array $l): bool => ($l['sku'] ?? '') === 'V11'));
		$this->assertSame([false], array_column($exempt, 'taxable'), 'the VAT-free item carries no VAT');
		$this->assertSame([10000], array_map(fn(array $l): int => $l['quantity'] * (int)round((float)$l['priceSet']['shopMoney']['amount'] * 100), $exempt),
			'and keeps its full price: the return is taken off the taxed item');
	}

	public function testAReturnLargerThanTheLinesOfItsRateTakesTheRestFromTheOthersAndNoLineGoesBelowZero(): void
	{
		$lines = [self::line(['id' => 1, 'posnr' => 1]), self::line(['id' => 2, 'posnr' => 2, 'vare_id' => 11, 'varenr' => 'V11', 'pris' => 400, 'momssats' => 10]),
			self::line(['id' => 3, 'posnr' => 3, 'antal' => -1, 'pris' => 200])];
		$out = shopify_store_orders_project_lines(self::snap(['sum' => 300, 'moms' => 15], $lines), self::LINKS);
		$this->assertSame([31500, 1500], self::totals($out), 'the posted total and VAT');
		$this->assertSame([], array_filter($out, fn(array $l): bool => (float)$l['priceSet']['shopMoney']['amount'] < 0), 'no line below zero');
	}

	public function testALineUsedUpByAReturnIsZeroNotARoundingErrorBelowIt(): void
	{
		$lines = [self::line(['id' => 1, 'posnr' => 1, 'pris' => 1.00]), self::line(['id' => 2, 'posnr' => 2, 'pris' => 1.63]),
			self::line(['id' => 3, 'posnr' => 3, 'antal' => -1, 'pris' => 12.63]), self::line(['id' => 4, 'posnr' => 4, 'vare_id' => 11, 'varenr' => 'V11', 'pris' => 100, 'momssats' => 10])];
		$out = shopify_store_orders_project_lines(self::snap(['sum' => 90, 'moms' => 7.5], $lines), self::LINKS);
		$this->assertSame([9750, 750], self::totals($out));
	}

	public function testAReturnThatLeavesFractionsOfAnOreOnManyLinesKeepsThem(): void
	{
		$lines = [];
		for ($i = 1; $i <= 8; $i++) {
			$lines[] = self::line(['id' => $i, 'posnr' => $i, 'vare_id' => 10 + $i, 'varenr' => "V$i"]);
		}
		$lines[] = self::line(['id' => 9, 'posnr' => 9, 'antal' => -1, 'pris' => 799.97]);
		$lines[] = self::line(['id' => 10, 'posnr' => 10, 'vare_id' => 30, 'varenr' => 'V30', 'momsfri' => 'on']);
		$out = shopify_store_orders_project_lines(self::snap(['sum' => 100.03, 'moms' => 0.01], $lines), self::LINKS);
		$this->assertSame([10004, 1], self::totals($out));
	}

	public function testAVatFreeReturnLeavingMoreVatThanTheLinesCostIsRefused(): void
	{
		$lines = [self::line(['id' => 1, 'posnr' => 1, 'antal' => 2, 'pris' => 364.27]),
			self::line(['id' => 2, 'posnr' => 2, 'vare_id' => 11, 'varenr' => 'V11', 'antal' => -3, 'pris' => 280.90, 'momsfri' => 'on']),
			self::line(['id' => 3, 'posnr' => 3, 'vare_id' => 12, 'varenr' => 'V12', 'pris' => 79.79, 'momsfri' => 'on'])];
		$this->expectExceptionMessage('its VAT is more than the lines it sits on cost');
		shopify_store_orders_project_lines(self::snap(['sum' => -34.37, 'moms' => 182.14], $lines), self::LINKS);
	}

	public function testASaleWhoseVatIsNegativeIsRefusedRatherThanSentWrong(): void
	{
		$lines = [self::line(['id' => 1, 'posnr' => 1]), self::line(['id' => 2, 'posnr' => 2, 'vare_id' => 11, 'varenr' => 'V11', 'pris' => 200, 'momsfri' => 'on']),
			self::line(['id' => 3, 'posnr' => 3, 'antal' => -1, 'pris' => 150])];
		$this->expectException(DomainException::class);
		shopify_store_orders_project_lines(self::snap(['sum' => 150, 'moms' => -12.5], $lines), self::LINKS);
	}

	public function testAFractionalQuantityAndAnUnlinkedItemAreCustomItems(): void
	{
		$lines = [
			self::line(['vare_id' => 10, 'beskrivelse' => 'Stof', 'enhed' => 'm', 'antal' => 2.5, 'pris' => 10]),
			self::line(['id' => 2, 'vare_id' => 99, 'varenr' => 'X99', 'beskrivelse' => 'Hynde', 'antal' => 2, 'pris' => 50, 'lager' => 0]),
		];
		$out = shopify_store_orders_project_lines(self::snap(['sum' => 125, 'moms' => 31.25], $lines), self::LINKS);

		$this->assertSame(['title' => 'Stof (2,5 m)', 'sku' => 'V10', 'quantity' => 1, 'amount' => '31.25'],
			['title' => $out[0]['title'], 'sku' => $out[0]['sku'], 'quantity' => $out[0]['quantity'], 'amount' => $out[0]['priceSet']['shopMoney']['amount']]);
		$this->assertArrayNotHasKey('variantId', $out[0]);
		$this->assertSame(['title' => 'Hynde', 'sku' => 'X99', 'quantity' => 2, 'amount' => '62.50'],
			['title' => $out[1]['title'], 'sku' => $out[1]['sku'], 'quantity' => $out[1]['quantity'], 'amount' => $out[1]['priceSet']['shopMoney']['amount']]);
		$this->assertArrayNotHasKey('properties', $out[1], 'no lager, no property');
	}

	public function testPaymentsAddUpToTheTotalAfterChangeAndForAnAccountSale(): void
	{
		$money = fn(array $t): array => [$t['gateway'], $t['amountSet']['shopMoney']['amount']];
		$cash = self::snap([], null, [['betalingstype' => 'Gavekort', 'amount' => 25, 'valutakurs' => 100], ['betalingstype' => 'Kontant', 'amount' => 200, 'valutakurs' => 100]]);
		$this->assertSame([['Gavekort', '25.00'], ['Kontant', '100.00']], array_map($money, shopify_store_orders_project_payments($cash)), 'the change comes off the last payment');

		$euro = self::snap([], null, [['betalingstype' => 'Kontant', 'amount' => 20, 'valutakurs' => 745]]);
		$this->assertSame([['Kontant', '125.00']], array_map($money, shopify_store_orders_project_payments($euro)), 'a foreign payment by its rate, capped at the total');

		$account = self::snap(['felt_1' => 'Konto'], null, []);
		$this->assertSame([['Konto', '125.00']], array_map($money, shopify_store_orders_project_payments($account)), 'what no payment covers goes as the sale\'s payment type');
	}

	public static function eligibility(): array
	{
		return [
			'a sale by a debtor' => [[], [], true],
			'an anonymous sale taken along (K37)' => [['konto_id' => 0], [], false],
			'an anonymous delivery' => [['konto_id' => 0, 'lev_type' => 'levering'], [], true],
			'a sale from before go-live (K38)' => [['fakturadate' => '2026-11-10', 'tidspkt' => '07:59'], [], false],
			'a sale at go-live' => [['fakturadate' => '2026-11-10', 'tidspkt' => '08:00'], [], true],
			'a sale of another brand' => [[], ['id' => '2'], false],
			'a shop not live' => [[], ['go_live_at' => null], false],
			'not posted' => [['status' => 2], [], false],
			'a return' => [['sum' => -100, 'moms' => -25], [], false],
		];
	}

	public function testAnAccountPaymentHasNoLinesAndDoesNotGo(): void
	{
		$r = shopify_store_orders_eligibility(self::snap(['moms' => 0], []), self::shop(), new DateTimeImmutable('2026-11-12 12:00'));
		$this->assertSame([false, 'nothing was sold (an account payment)'], [$r['ok'], $r['reason'] ?? null]);
	}

	#[DataProvider('eligibility')]
	public function testOnlyPostedSalesFromGoLiveWithADebtorOrADeliveryGo(array $order, array $shop, bool $ok): void
	{
		$this->assertSame($ok, shopify_store_orders_eligibility(self::snap($order), self::shop($shop), new DateTimeImmutable('2026-11-12 12:00'))['ok']);
	}
}
