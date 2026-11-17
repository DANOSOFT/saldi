<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stdFunc/shopOfferPrice.php';
require_once __DIR__ . '/../../includes/stdFunc/shopOfferPush.php';
require_once __DIR__ . '/../../includes/stdFunc/shopSyncQueue.php';

/**
 * The prices the webshops are sent during and outside an offer (includes/stdFunc/shopOfferPrice.php) and the reconciliation
 * of what each shop confirmed (includes/stdFunc/shopOfferPush.php).
 */
final class shopOfferPrice extends TestCase
{
	protected function setUp(): void
	{
		date_default_timezone_set('Europe/Copenhagen');
	}

	/** Black Week as the campaign import stores a sheet with dates only. */
	private const BLACK_WEEK = [
		'specialtype' => 'price', 'special_price' => '159.200', 'salgspris' => '639.200', 'retail_price' => '899.000', 'gruppe' => '2',
		'special_from_date' => '2026-11-27', 'special_from_time' => '00:00:00', 'special_to_date' => '2026-11-30', 'special_to_time' => '23:59:59',
	];

	private static function at(string $moment): int
	{
		return (int)strtotime($moment);
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function blackWeekMoments(): array
	{
		return [
			'the evening before'        => ['2026-11-26 23:59:59', false],
			'the first second'          => ['2026-11-27 00:00:00', true],
			'a day in between'          => ['2026-11-28 14:00:00', true],
			'the last day, late'        => ['2026-11-30 23:59:59', true],
			'the day after'             => ['2026-12-01 00:00:00', false],
		];
	}

	#[DataProvider('blackWeekMoments')]
	public function testTheWindowIsFindPrisWindow(string $moment, bool $active): void
	{
		$this->assertSame($active, shopOfferActive(self::BLACK_WEEK, self::at($moment)));
	}

	public function testAStoredEndTimeOfMidnightEndsTheOfferAsTheLastDayBegins(): void
	{
		$item = ['special_to_time' => '00:00:00'] + self::BLACK_WEEK;
		$this->assertTrue(shopOfferActive($item, self::at('2026-11-29 22:00:00')), 'widened to 23:59:59 before the last day');
		$this->assertFalse(shopOfferActive($item, self::at('2026-11-30 10:00:00')), 'find_pris() gives the normal price on the last day');
	}

	public function testAnExplicitEndTimeIsADailyClosingTimeAsInFindPris(): void
	{
		$item = ['special_from_time' => '08:00:00', 'special_to_time' => '20:00:00'] + self::BLACK_WEEK;
		$this->assertFalse(shopOfferActive($item, self::at('2026-11-27 07:59:59')));
		$this->assertTrue(shopOfferActive($item, self::at('2026-11-27 08:00:00')));
		$this->assertFalse(shopOfferActive($item, self::at('2026-11-28 21:00:00')), 'after 20:00 on a day in between');
		$this->assertTrue(shopOfferActive($item, self::at('2026-11-29 00:00:30')), 'from midnight again');
		$this->assertFalse(shopOfferActive($item, self::at('2026-11-30 20:00:01')));
	}

	public function testOnlyAPriceOfferCounts(): void
	{
		$moment = self::at('2026-11-28 12:00:00');
		$this->assertFalse(shopOfferActive(['specialtype' => 'percent'] + self::BLACK_WEEK, $moment), 'percent offers are out of scope');
		$this->assertFalse(shopOfferActive(['specialtype' => null] + self::BLACK_WEEK, $moment));
		$this->assertFalse(shopOfferActive(['special_price' => '0.000'] + self::BLACK_WEEK, $moment));
		$this->assertFalse(shopOfferActive(['special_from_date' => null, 'special_to_date' => null] + self::BLACK_WEEK, $moment));
	}

	public function testTheNextCheckIsTheNextBoundary(): void
	{
		$this->assertSame(self::at('2026-11-27 00:00:00'), shopOfferNextCheck(self::BLACK_WEEK, self::at('2026-11-26 15:00:00')));
		$this->assertSame(self::at('2026-11-29 00:00:00'), shopOfferNextCheck(self::BLACK_WEEK, self::at('2026-11-28 15:00:00')), 'midnight, where the rules change');
		$this->assertSame(self::at('2026-12-01 00:00:00'), shopOfferNextCheck(self::BLACK_WEEK, self::at('2026-11-30 15:00:00')), 'the end');
		$this->assertNull(shopOfferNextCheck(self::BLACK_WEEK, self::at('2026-12-01 00:00:01')), 'ended');
		$item = ['special_from_time' => '08:00:00', 'special_to_time' => '20:00:00'] + self::BLACK_WEEK;
		$this->assertSame(self::at('2026-11-27 08:00:00'), shopOfferNextCheck($item, self::at('2026-11-27 01:00:00')));
		$this->assertSame(self::at('2026-11-28 20:00:01'), shopOfferNextCheck($item, self::at('2026-11-28 09:00:00')));
		$this->assertNull(shopOfferNextCheck(['specialtype' => 'percent'] + self::BLACK_WEEK, self::at('2026-11-26 15:00:00')));
	}

	public function testDuringTheOfferTheShopsGetTheOfferAndTheNormalPriceAsTheBeforePrice(): void
	{
		$during = self::at('2026-11-28 12:00:00');
		$this->assertSame(['salesPrice' => '159.200', 'retailPrice' => '799.00', 'offer' => true],
			shopEffectivePrices(self::BLACK_WEEK, $during, 25.0, true), 'card shows prices incl. VAT: before-price 639.20 x 1.25');
		$this->assertSame(['salesPrice' => '159.200', 'retailPrice' => '639.20', 'offer' => true],
			shopEffectivePrices(self::BLACK_WEEK, $during, 25.0, false), 'card shows prices excl. VAT');
		$this->assertSame('639.20', shopEffectivePrices(self::BLACK_WEEK, $during, 0.0, true)['retailPrice'], 'VAT-free group');
	}

	public function testOutsideTheOfferTheShopsGetTheStoredPrices(): void
	{
		$this->assertSame(['salesPrice' => '639.200', 'retailPrice' => '899.000', 'offer' => false],
			shopEffectivePrices(self::BLACK_WEEK, self::at('2026-12-01 00:00:00'), 25.0, true), 'the original Vejl.pris returns');
		$this->assertSame(['salesPrice' => '639.200', 'retailPrice' => '899.000', 'offer' => false],
			shopPricesForItem(self::BLACK_WEEK, self::at('2026-11-26 23:59:59')), 'no VAT lookup outside an offer');
	}

	public function testAMissingVejlPrisIsPassedOnAsBefore(): void
	{
		$this->assertSame(['salesPrice' => '639.200', 'retailPrice' => null, 'offer' => false],
			shopEffectivePrices(['retail_price' => null] + self::BLACK_WEEK, self::at('2026-12-01 00:00:00'), 25.0, true));
	}

	public function testThePriceCallIsTheItemCardsCall(): void
	{
		$item = ['varenr' => 'BW001', 'varenr_alias' => 'A1', 'kostpris' => '319.600', 'm_type' => 'amount', 'm_rabat' => '10',
			'colli_webfragt' => '0', 'stregkode' => '5701234567890'];
		$prices = ['salesPrice' => '159.200', 'retailPrice' => '799.00', 'offer' => true];
		$this->assertSame([
			'update_price' => 77, 'salesPrice' => '159.200', 'discountType' => 'amount', 'discount' => '10', 'itemNo' => 'BW001',
			'itemNoAlias' => 'A1', 'rand' => '', 'costPrice' => '319.600', 'retailPrice' => '799.00', 'webFragt' => '0',
			'barcode' => '5701234567890',
		], shopPriceParams($item, $prices, 77));
	}

	private const SHOPS = ['k1' => 'http://shop1', 'k2' => 'http://shop2', 'k3' => 'http://shop3'];
	private const OFFER = ['sales' => '159.200', 'retail' => '799.00'];
	private const NORMAL = ['sales' => '639.200', 'retail' => '899.000'];

	private static function confirmed(array $pair): array
	{
		return ['confirmed' => $pair, 'attempts' => 0, 'refusals' => 0, 'retryAt' => 0, 'error' => '', 'gaveUp' => null];
	}

	public function testAnUntrackedItemIsSentToEveryShop(): void
	{
		$plan = shopOfferPlan(null, self::SHOPS, self::OFFER, 1000);
		$this->assertSame(self::SHOPS, $plan['send']);
		$this->assertFalse($plan['settled']);
	}

	public function testOnlyTheShopsThatDisagreeAreSent(): void
	{
		$state = ['shops' => ['k1' => self::confirmed(self::OFFER), 'k2' => self::confirmed(['sales' => '59.200', 'retail' => '799.00']), 'k3' => self::confirmed(self::OFFER)]];
		$this->assertSame(['k2' => 'http://shop2'], shopOfferPlan($state, self::SHOPS, self::OFFER, 1000)['send']);
		$all = ['shops' => ['k1' => self::confirmed(self::OFFER), 'k2' => self::confirmed(self::OFFER), 'k3' => self::confirmed(self::OFFER)]];
		$this->assertSame(['send' => [], 'waitUntil' => null, 'settled' => true, 'gaveUp' => []], shopOfferPlan($all, self::SHOPS, self::OFFER, 1000));
		$this->assertCount(3, shopOfferPlan($all, self::SHOPS, self::NORMAL, 1000)['send'], 'the end of the offer: every shop is owed the normal prices');
	}

	public function testAShopWaitsForItsRetryTime(): void
	{
		$state = ['shops' => ['k1' => ['retryAt' => 1060, 'attempts' => 1] + self::confirmed(self::NORMAL)]];
		$plan = shopOfferPlan($state, ['k1' => 'http://shop1'], self::OFFER, 1001);
		$this->assertSame([], $plan['send']);
		$this->assertSame(1060, $plan['waitUntil']);
		$this->assertSame(['k1' => 'http://shop1'], shopOfferPlan($state, ['k1' => 'http://shop1'], self::OFFER, 1060)['send']);
	}

	public function testEachShopsAnswerIsRecordedOnItsOwn(): void
	{
		$results = [
			'k1' => ['ok' => true],
			'k2' => ['ok' => false, 'httpCode' => 503, 'transient' => true, 'error' => 'http status 503'],
			'k3' => ['ok' => false, 'httpCode' => 200, 'transient' => false, 'error' => 'shop answered http 2xx with a rejection payload'],
		];
		$out = shopOfferRecord(null, $results, self::OFFER, 1000);
		$this->assertSame(['k1'], $out['delivered']);
		$this->assertSame(['k2', 'k3'], $out['failed']);
		$this->assertSame(self::OFFER, $out['state']['shops']['k1']['confirmed']);
		$this->assertNull($out['state']['shops']['k2']['confirmed'], 'a failure confirms nothing');
		$this->assertSame([1, 0, 1060], [$out['state']['shops']['k2']['attempts'], $out['state']['shops']['k2']['refusals'], $out['state']['shops']['k2']['retryAt']]);
		$this->assertSame([0, 1, 1060], [$out['state']['shops']['k3']['attempts'], $out['state']['shops']['k3']['refusals'], $out['state']['shops']['k3']['retryAt']],
			'a refusal is not delivered: it is retried like a failure');
	}

	public function testAShopLeftUntriedByTheDeadlineIsNotAFailure(): void
	{
		$out = shopOfferRecord(null, ['k1' => ['ok' => false, 'skipped' => true, 'untried' => true]], self::OFFER, 1000);
		$this->assertSame([[], []], [$out['delivered'], $out['failed']]);
		$this->assertSame([], $out['state']['shops']);
	}

	public function testRefusalsOfTheSamePricesStopAfterTheLimitAndStartAgainForNewPrices(): void
	{
		$state = null;
		$gaveUp = [];
		for ($i = 1; $i <= SHOP_OFFER_MAX_REFUSALS + 1; $i++) {
			$out = shopOfferRecord($state, ['k1' => ['ok' => false, 'httpCode' => 200, 'transient' => false, 'error' => 'refused']], self::OFFER, 1000 * $i);
			$state = $out['state'];
			$gaveUp = array_merge($gaveUp, $out['gaveUpNow']);
		}
		$this->assertSame(['k1'], $gaveUp, 'given up once, after ' . SHOP_OFFER_MAX_REFUSALS . ' refusals');
		$plan = shopOfferPlan($state, ['k1' => 'http://shop1'], self::OFFER, PHP_INT_MAX - 1);
		$this->assertSame([[], ['k1']], [$plan['send'], $plan['gaveUp']], 'the same prices are not sent again');
		$this->assertSame(['k1' => 'http://shop1'], shopOfferPlan($state, ['k1' => 'http://shop1'], self::NORMAL, PHP_INT_MAX - 1)['send'],
			'other prices are');
	}

	public function testTheBackOffDoublesUpToHalfAnHour(): void
	{
		$this->assertSame([60, 120, 240, 480, 960, 1800, 1800, 1800], array_map('shopOfferBackoff', [1, 2, 3, 4, 5, 6, 7, 20]));
	}

	public function testPricePairsCompareToTheOere(): void
	{
		$this->assertTrue(shopOfferPairSame(['sales' => '159.2', 'retail' => '799'], ['sales' => '159.200', 'retail' => '799.00']));
		$this->assertTrue(shopOfferPairSame(['sales' => '639.200', 'retail' => null], ['sales' => '639.2', 'retail' => '']), 'an empty Vejl.pris');
		$this->assertFalse(shopOfferPairSame(['sales' => '639.200', 'retail' => null], ['sales' => '639.2', 'retail' => '0']));
		$this->assertFalse(shopOfferPairSame(null, self::OFFER));
		$this->assertFalse(shopOfferPairSame(self::OFFER, self::NORMAL));
	}

	public function testTheStateKeepsEveryFieldOnDisk(): void
	{
		$GLOBALS['db'] = 'unit_shopoffer_' . bin2hex(random_bytes(4));
		$dir = dirname(__DIR__, 2) . '/temp/' . $GLOBALS['db'];
		try {
			$state = shopOfferRecord(null, ['k1' => ['ok' => false, 'httpCode' => 503, 'transient' => true, 'error' => '']], ['sales' => '', 'retail' => null], 1000)['state'];
			shopOfferItemSave(42, $state);
			$this->assertStringStartsWith('{', (string)file_get_contents($dir . '/shopoffer/item-42.json'), 'self-describing JSON, no positional fields');
			$loaded = shopOfferItemLoad(42);
			$this->assertSame(['confirmed' => null, 'attempts' => 1, 'refusals' => 0, 'retryAt' => 1060, 'error' => '', 'gaveUp' => null], $loaded['shops']['k1'],
				'a first failure keeps its back-off when the prices are empty');
			$this->assertSame('wait', shopOfferPlan($loaded, ['k1' => 'x'], self::OFFER, 1001)['waitUntil'] === 1060 ? 'wait' : 'push');
			shopOfferItemSave(42, null);
			$this->assertNull(shopOfferItemLoad(42));
		} finally {
			array_map('unlink', (array)glob($dir . '/shopoffer/*'));
			@rmdir($dir . '/shopoffer');
			@rmdir($dir);
		}
	}

	public function testASchedulingRequestOnlyMovesTheNextLookEarlier(): void
	{
		$GLOBALS['db'] = 'unit_shopoffer_' . bin2hex(random_bytes(4));
		$dir = dirname(__DIR__, 2) . '/temp/' . $GLOBALS['db'];
		try {
			$this->assertSame(PHP_INT_MAX, shopOfferPushDueAt(), 'dormant');
			shopOfferPushSchedule(5000);
			shopOfferPushSchedule(3000);
			shopOfferPushSchedule(9000);
			$this->assertSame(3000, shopOfferPushDueAt());
			$this->assertSame(['due' => 3000, 'gen' => 2], shopOfferScheduleRead(), 'two requests moved it, one did not');
		} finally {
			array_map('unlink', (array)glob($dir . '/shopoffer/*'));
			@rmdir($dir . '/shopoffer');
			@rmdir($dir);
		}
	}
}
