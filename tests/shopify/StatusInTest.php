<?php
// tests/shopify/StatusInTest.php
//
// The pure parts of inbound order status (SD-801): a refund's amount from its successful refund
// transactions, the projections' ordering rule (later wins, a tie goes to the greater body, a
// replay never wins) and Shopify's time as UTC. No database. Each test runs in its own process,
// since statusIn.php brings the Shopify config and links functions, which other tests expect to be absent.
//
// History:
// 20261103 CL/SS SD-801: created.

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class StatusInTest extends TestCase
{
	protected function setUp(): void
	{
		require_once __DIR__ . '/../../includes/shopify/services/statusIn.php';
	}

	public function testARefundIsItsSuccessfulRefundTransactions(): void
	{
		$t = fn(string $amount, string $kind = 'refund', string $status = 'success', string $currency = 'DKK'): array => compact('amount', 'kind', 'status', 'currency');
		$this->assertSame(['amount' => '1249.000', 'currency' => 'DKK'], shopify_refund_amount([$t('1000.00'), $t('249.00'), $t('5.00', 'refund', 'failure'), $t('7.00', 'sale')]));
		$this->assertSame(['amount' => '0.300', 'currency' => 'EUR'], shopify_refund_amount([$t('0.10', 'refund', 'success', 'eur'), $t('0.20', 'refund', 'success', 'EUR')]),
			'0.1 + 0.2 summed exactly');
		$this->assertSame(['amount' => '0.000', 'currency' => 'DKK'], shopify_refund_amount([$t('5.00', 'refund', 'pending')]), 'none succeeded yet');
		$this->assertSame(['amount' => '0.000', 'currency' => null], shopify_refund_amount([]));
	}

	public function testALaterTimeWinsATieGoesToTheGreaterBodyAndAReplayNever(): void
	{
		$this->assertTrue(shopify_status_newer('2026-11-02 13:00:00', 'a', '2026-11-02 12:00:00', 'z'));
		$this->assertFalse(shopify_status_newer('2026-11-02 11:00:00', 'z', '2026-11-02 12:00:00', 'a'));
		$this->assertTrue(shopify_status_newer('2026-11-02 12:00:00', 'b', '2026-11-02 12:00:00', 'a'));
		$this->assertFalse(shopify_status_newer('2026-11-02 12:00:00', 'a', '2026-11-02 12:00:00', 'b'), 'either arrival order ends with b');
		$this->assertFalse(shopify_status_newer('2026-11-02 12:00:00', 'a', '2026-11-02 12:00:00', 'a'), 'a replay');
		$this->assertTrue(shopify_status_newer('2026-11-02 12:00:00', 'a', null, 'z'), 'a time beats none');
		$this->assertFalse(shopify_status_newer(null, 'z', '2026-11-02 12:00:00', 'a'));
	}

	public function testShopifyTimesCompareInUtc(): void
	{
		$this->assertSame('2026-11-02 13:05:11', shopify_status_utc('2026-11-02T14:05:11+01:00'));
		$this->assertSame('2026-10-25 00:30:00', shopify_status_utc('2026-10-25T02:30:00+02:00'), 'the hour that repeats when summer time ends');
		$this->assertTrue(shopify_status_utc('2026-10-25T02:10:00+01:00') > shopify_status_utc('2026-10-25T02:50:00+02:00'));
		$this->assertNull(shopify_status_utc('yesterday'));
		$this->assertNull(shopify_status_utc(null));
	}
}
