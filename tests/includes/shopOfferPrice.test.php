<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stdFunc/shopOfferPrice.php';
require_once __DIR__ . '/../../includes/stdFunc/shopOfferPush.php';
require_once __DIR__ . '/../../includes/stdFunc/shopSyncQueue.php';

/**
 * The prices the webshops are sent during and outside an offer (includes/stdFunc/shopOfferPrice.php), and the offer reconciler's
 * per-shop state (includes/stdFunc/shopOfferPush.php): what is owed, how an answer is recorded, what other paths may send.
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
	private const NOW = 1794920000;

	/** A shop entry certified at $pair at $at. */
	private static function certified(array $pair, int $at = self::NOW): array
	{
		return shopOfferShopEntry(['shops' => ['k' => ['confirmed' => $pair, 'certain' => true, 'verifiedAt' => $at]]], 'k');
	}

	public function testAnUntrackedItemIsSentToEveryShop(): void
	{
		$plan = shopOfferPlanItem(['shops' => []], self::SHOPS, self::OFFER, self::NOW);
		$this->assertSame(['k1' => 'send', 'k2' => 'send', 'k3' => 'send'], $plan['send']);
		$this->assertFalse($plan['settled']);
		$this->assertSame(self::OFFER, $plan['state']['shops']['k1']['pending'], 'the intent: the pair owed');
	}

	public function testOnlyShopsThatMayNotHaveTheDesiredPairAreSent(): void
	{
		$state = ['shops' => ['k1' => self::certified(self::OFFER), 'k2' => self::certified(['sales' => '59.200', 'retail' => '799.00']),
			'k3' => ['certain' => false] + self::certified(self::OFFER)]];
		$plan = shopOfferPlanItem($state, self::SHOPS, self::OFFER, self::NOW + 10);
		$this->assertSame(['k2' => 'send', 'k3' => 'send'], $plan['send'], 'k2 has another pair; k3 is uncertain, whatever it was certified at');
		$all = ['shops' => ['k1' => self::certified(self::OFFER), 'k2' => self::certified(self::OFFER), 'k3' => self::certified(self::OFFER)]];
		$plan = shopOfferPlanItem($all, self::SHOPS, self::OFFER, self::NOW + 10);
		$this->assertSame([[], true], [$plan['send'], $plan['settled']]);
	}

	public function testACertifiedPairIsSentAgainAfterAnHour(): void
	{
		$all = ['shops' => ['k1' => self::certified(self::OFFER), 'k2' => self::certified(self::OFFER, self::NOW + 600)]];
		$plan = shopOfferPlanItem($all, ['k1' => 'a', 'k2' => 'b'], self::OFFER, self::NOW + 3600);
		$this->assertSame(['k1' => 'heal'], $plan['send']);
		$this->assertTrue($plan['settled'], 'a re-send due is still settled');
	}

	public function testRemovedShopsAreDropped(): void
	{
		$state = ['shops' => ['gone' => self::certified(self::OFFER), 'k1' => self::certified(self::OFFER)]];
		$this->assertSame(['k1'], array_keys(shopOfferPlanItem($state, ['k1' => 'a'], self::OFFER, self::NOW)['state']['shops']));
	}

	public function testTheCountersBelongToThePendingPair(): void
	{
		$entry = ['pending' => self::OFFER, 'attempts' => 0, 'refusals' => 8, 'retryAt' => self::NOW + 1800, 'error' => 'x', 'gaveUp' => true]
			+ self::certified(self::NORMAL);
		$this->assertSame('gaveUp', shopOfferPlanShop($entry, self::OFFER, self::NOW + 9999)[0], 'given up for this pair');
		[$action, $fresh] = shopOfferPlanShop($entry, ['sales' => '129.200', 'retail' => '799.00'], self::NOW + 1);
		$this->assertSame('send', $action, 'a new pair is sent at once: no inherited refusals, no inherited back-off');
		$this->assertSame([0, 0, 0, false], [$fresh['refusals'], $fresh['attempts'], $fresh['retryAt'], $fresh['gaveUp']]);
		$waiting = ['pending' => self::OFFER, 'refusals' => 2, 'retryAt' => self::NOW + 120] + self::certified(self::NORMAL);
		$this->assertSame('wait', shopOfferPlanShop($waiting, self::OFFER, self::NOW + 60)[0]);
		$this->assertSame('send', shopOfferPlanShop($waiting, self::OFFER, self::NOW + 120)[0]);
	}

	public function testEachAnswerIsRecordedForItsShop(): void
	{
		$sent = ['pending' => self::OFFER, 'certain' => false] + self::certified(self::NORMAL);
		[$o, $e] = shopOfferRecordShop($sent, true, self::OFFER, ['ok' => true, 'httpCode' => 200], false, self::NOW);
		$this->assertSame(['certified', self::OFFER, true, self::NOW, null], [$o, $e['confirmed'], $e['certain'], $e['verifiedAt'], $e['pending']]);
		[$o, $e] = shopOfferRecordShop($sent, true, self::OFFER, ['ok' => true, 'httpCode' => 200], true, self::NOW);
		$this->assertSame(['overlapped', self::NORMAL, false], [$o, $e['confirmed'], $e['certain']],
			'accepted, but another send overlapped: the shop may have either pair, so nothing is certified');
		[$o, $e] = shopOfferRecordShop($sent, true, self::OFFER, ['ok' => false, 'httpCode' => 200, 'transient' => false, 'error' => 'refused'], false, self::NOW);
		$this->assertSame(['refused', true, 1, self::NOW + 60], [$o, $e['certain'], $e['refusals'], $e['retryAt']],
			'a refusal applied nothing: the shop still certainly has what it had');
		[$o, $e] = shopOfferRecordShop($sent, true, self::OFFER, ['ok' => false, 'httpCode' => 404, 'transient' => false, 'error' => 'http status 404'], false, self::NOW);
		$this->assertSame(['refused', 1], [$o, $e['refusals']], 'a 4xx is an answer too');
		[$o, $e] = shopOfferRecordShop($sent, true, self::OFFER, ['ok' => false, 'httpCode' => 503, 'transient' => true, 'error' => 'http status 503'], false, self::NOW);
		$this->assertSame(['failed', false, 1, 0], [$o, $e['certain'], $e['attempts'], $e['refusals']], 'a 5xx may or may not have been applied');
		[$o, $e] = shopOfferRecordShop($sent, true, self::OFFER, ['ok' => false, 'httpCode' => 0, 'transient' => true, 'error' => 'timeout'], false, self::NOW);
		$this->assertSame(['failed', false], [$o, $e['certain']]);
		[$o, $e] = shopOfferRecordShop($sent, true, self::OFFER, ['untried' => true], false, self::NOW);
		$this->assertSame(['untried', true, 0, 0], [$o, $e['certain'], $e['attempts'], $e['refusals']], 'not sent: nothing changes');
	}

	public function testTheEighthRefusalOfAPairGivesUpOnceAndVisibly(): void
	{
		$entry = ['pending' => self::OFFER, 'certain' => false] + self::certified(self::NORMAL);
		$gaveUp = [];
		for ($i = 1; $i <= SHOP_OFFER_MAX_REFUSALS + 1; $i++) {
			[, $entry, $now] = shopOfferRecordShop($entry, true, self::OFFER, ['ok' => false, 'httpCode' => 200, 'transient' => false, 'error' => 'no'], false, self::NOW);
			$gaveUp[] = $now;
		}
		$this->assertSame([false, false, false, false, false, false, false, true, false], $gaveUp);
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

	public function testOtherPathsGetTheConfirmedPairPerShop(): void
	{
		$params = ['update_stock' => 1, 'stock' => 4, 'salesPrice' => '639.200', 'retailPrice' => '899.000'];
		$handle = ['token' => 't', 'pairs' => [0 => self::OFFER, 1 => null, 2 => self::OFFER]];
		$groups = shopOfferForeignGroups([0 => 'a', 1 => 'b', 2 => 'c'], $handle, $params);
		$this->assertSame([
			['targets' => [0 => 'a', 2 => 'c'], 'params' => ['update_stock' => 1, 'stock' => 4, 'salesPrice' => '159.200', 'retailPrice' => '799.00']],
			['targets' => [1 => 'b'], 'params' => $params],
		], $groups, 'a shop that never confirmed a pair gets today\'s prices');
		$this->assertSame([['targets' => [0 => 'a'], 'params' => $params]], shopOfferForeignGroups([0 => 'a'], null, $params), 'not managed: unchanged');
	}

	public function testAnItemIsManagedByItsOfferFields(): void
	{
		$now = strtotime('2026-11-27 12:00:00');
		$this->assertTrue(shopOfferHasOfferFields(['specialtype' => 'price', 'special_price' => '79.2', 'special_to_date' => '2026-11-26'], $now), 'ended yesterday');
		$this->assertFalse(shopOfferHasOfferFields(['specialtype' => 'price', 'special_price' => '79.2', 'special_to_date' => '2026-11-25'], $now));
		$this->assertFalse(shopOfferHasOfferFields(['specialtype' => 'percent', 'special_price' => '10', 'special_to_date' => '2026-12-01'], $now), 'percent offers are not');
		$this->assertFalse(shopOfferHasOfferFields(['specialtype' => 'price', 'special_price' => '0', 'special_to_date' => '2026-12-01'], $now));
	}

	public function testTheStateIsSelfDescribingJsonAndKeepsEmptyFields(): void
	{
		$GLOBALS['db'] = 'unit_shopoffer_' . bin2hex(random_bytes(4));
		$dir = dirname(__DIR__, 2) . '/temp/' . $GLOBALS['db'];
		try {
			$this->assertSame('', shopOfferPushDir(), 'no directory until the reconciler makes one');
			$this->assertNotSame('', shopOfferPushDir(true));
			[, $entry] = shopOfferRecordShop(['pending' => ['sales' => '', 'retail' => null], 'certain' => false] + self::certified(self::NORMAL), false,
				['sales' => '', 'retail' => null], ['ok' => false, 'httpCode' => 503, 'transient' => true, 'error' => ''], false, self::NOW);
			$this->assertTrue(shopOfferStateWrite(42, ['shops' => ['k1' => $entry]]));
			$this->assertStringStartsWith('{', (string)file_get_contents($dir . '/shopoffer/item-42.json'), 'JSON, no positional fields');
			$this->assertSame(shopOfferShopEntry(['shops' => ['k1' => $entry]], 'k1'), shopOfferShopEntry(shopOfferStateRead(42), 'k1'), 'every field, the empty ones too');
			$this->assertSame(self::NOW + 60, shopOfferShopEntry(shopOfferStateRead(42), 'k1')['retryAt'], 'a first failure keeps its back-off');
			file_put_contents($dir . '/shopoffer/item-43.json', '{"shops":');
			$this->assertFalse(shopOfferStateRead(43), 'unreadable is not the same as untracked');
			$this->assertTrue(shopOfferStateWrite(42, null));
			$this->assertNull(shopOfferStateRead(42));
		} finally {
			array_map('unlink', (array)glob($dir . '/shopoffer/*'));
			@rmdir($dir . '/shopoffer');
			@rmdir($dir);
		}
	}
}
