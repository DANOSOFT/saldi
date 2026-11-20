<?php
// tests/shopify/CustomersTest.php
//
// The customer mapping's pure parts (SD-792): a Shopify customer's debtor fields (company or
// person, address, column widths), the E.164 phone kept in mobile, Shopify's time as UTC, and
// the change hash. No database. Each test runs in its own process, since customers.php brings
// the Shopify config and links functions, which other tests expect to be absent.
//
// History:
// 20261101 CL/SS SD-792: created.
// 20261109 CL/SS SD-814: the fields carry the customer type and the first and last name.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class CustomersTest extends TestCase
{
	private const CUSTOMER = ['id' => 7, 'email' => 'kunde@example.dk', 'first_name' => 'Test', 'last_name' => 'Kunde', 'phone' => '+4512345678',
		'default_address' => ['address1' => 'Havevej 1', 'address2' => '2. th', 'zip' => '2750', 'city' => 'Ballerup', 'country' => 'Denmark', 'company' => '']];

	protected function setUp(): void
	{
		require_once __DIR__ . '/../../includes/shopify/services/customers.php';
	}

	public function testAPersonIsTheNameAndACompanyHasThePersonAsContact(): void
	{
		$person = shopify_customer_fields(self::CUSTOMER);
		$this->assertSame(['firmanavn' => 'Test Kunde', 'kontakt' => '', 'kontotype' => 'privat', 'fornavn' => 'Test', 'efternavn' => 'Kunde', 'addr1' => 'Havevej 1',
			'addr2' => '2. th', 'postnr' => '2750', 'bynavn' => 'Ballerup', 'land' => 'Denmark', 'email' => 'kunde@example.dk', 'mobile' => '+4512345678'], $person);

		$company = shopify_customer_fields(['default_address' => ['company' => 'Havemøbler ApS'] + self::CUSTOMER['default_address']] + self::CUSTOMER);
		$this->assertSame(['Havemøbler ApS', 'Test Kunde', 'erhverv', '', ''], [$company['firmanavn'], $company['kontakt'], $company['kontotype'], $company['fornavn'], $company['efternavn']]);
	}

	public function testANamelessCustomerFallsBackToTheAddressNameElseNoName(): void
	{
		$noName = ['first_name' => null, 'last_name' => ''] + self::CUSTOMER;
		$this->assertSame('Anna Have', shopify_customer_fields(['default_address' => ['name' => 'Anna Have']] + $noName)['firmanavn']);
		$this->assertSame('', shopify_customer_fields(['default_address' => []] + $noName)['firmanavn'], 'a stand-in name is only for a new debtor');
		$this->assertSame('Havevej 1', shopify_customer_fields(['default_address' => null, 'addresses' => [self::CUSTOMER['default_address']]] + self::CUSTOMER)['addr1'],
			'the first address when there is no default');
	}

	public function testAPrivateNameIsJoinedFromTheCutNamesAndFitsTheNameColumn(): void
	{
		$long = shopify_customer_fields(['first_name' => str_repeat('A', 61), 'last_name' => str_repeat('B', 100)] + self::CUSTOMER);
		$this->assertSame([str_repeat('A', 60), str_repeat('B', 29)], [$long['fornavn'], $long['efternavn']], 'the last name is cut so the joined name fits 90');
		$this->assertSame(trim($long['fornavn'] . ' ' . $long['efternavn']), $long['firmanavn'], 'what the card joins when it saves');
		$this->assertSame(90, mb_strlen($long['firmanavn']));

		$spaced = shopify_customer_fields(['first_name' => str_repeat('A', 59) . ' C', 'last_name' => 'Hansen'] + self::CUSTOMER);
		$this->assertSame(trim($spaced['fornavn'] . ' ' . $spaced['efternavn']), $spaced['firmanavn']);
	}

	public function testNamesAndAddressesAreCutToTheColumnWidthsButNotTheEmail(): void
	{
		$fields = shopify_customer_fields(['email' => str_repeat('a', 70) . '@example.dk', 'default_address' => ['zip' => 'SW1A 1AA EXTRA']] + self::CUSTOMER);
		$this->assertSame(str_repeat('a', 70) . '@example.dk', $fields['email'], 'an email is never cut: a cut one is another address');
		$this->assertSame('SW1A 1AA E', $fields['postnr']);
	}

	public static function phones(): array
	{
		return [
			'E.164' => ['+4512345678', '+4512345678'],
			'spaces and dashes' => ['+45 12 34-56 78', '+4512345678'],
			'15 digits keep their digits' => ['+491234567890123', '491234567890123'],
			'national form is not E.164' => ['12345678', ''],
			'empty' => ['', ''],
		];
	}

	#[DataProvider('phones')]
	public function testThePhoneInMobileIsE164(string $in, string $out): void
	{
		$this->assertSame($out, shopify_customer_phone($in));
	}

	public function testShopifyTimeIsUtcAndTheHashFollowsTheFields(): void
	{
		$this->assertSame('2026-11-01 09:00:00', shopify_customer_time('2026-11-01T10:00:00+01:00'));
		$this->assertLessThan(shopify_customer_time('2026-10-25T02:10:00+01:00'), shopify_customer_time('2026-10-25T02:30:00+02:00'),
			'UTC keeps the order in the hour that repeats when summer time ends');
		$this->assertNull(shopify_customer_time(null));
		$this->assertNull(shopify_customer_time('not a time'));
		$this->assertNull(shopify_customer_time('tomorrow'), 'only an ISO 8601 time: a relative one would push the stored time ahead');

		$fields = shopify_customer_fields(self::CUSTOMER);
		$this->assertSame(shopify_customer_hash($fields), shopify_customer_hash(shopify_customer_fields(['tags' => 'vip', 'updated_at' => 'x'] + self::CUSTOMER)), 'tags and times are not debtor fields');
		$this->assertNotSame(shopify_customer_hash($fields), shopify_customer_hash(shopify_customer_fields(['phone' => '+4587654321'] + self::CUSTOMER)));
	}
}
