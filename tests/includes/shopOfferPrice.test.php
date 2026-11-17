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
	private const T = 1794920000;

	/** An item's state: planned for $desired, last event at $anchor, every shop last sent and accepting at $sent. */
	private static function state(array $desired, int $anchor, int $sent, ?array $confirmed = null): array
	{
		$shops = [];
		foreach (array_keys(self::SHOPS) as $key) {
			$shops[$key] = ['confirmed' => $confirmed ?? $desired, 'okAt' => $sent, 'sentAt' => $sent, 'pending' => $desired];
		}
		return ['desired' => $desired, 'anchor' => $anchor, 'shops' => $shops];
	}

	/** @return list<int> Minutes after the event at which a shop is sent the pair during the first $hours. */
	private static function sendsWithin(bool $active, int $hours): array
	{
		$at = [];
		$last = -1;
		while (($next = shopOfferNextSend(0, $last, $active)) !== null && $next <= $hours * 3600) {
			$at[] = intdiv($next, 60);
			$last = $next;
		}
		return $at;
	}

	public function testTheScheduleAfterAnEvent(): void
	{
		$this->assertSame([0, 2, 5, 15, 60, 240, 420, 600, 780, 960, 1140, 1320], self::sendsWithin(true, 24),
			'while an offer is active: at once, 2, 5, 15 and 60 minutes after, then every 3 hours');
		$this->assertSame([0, 2, 5, 15, 60, 360, 720, 1080, 1440], self::sendsWithin(false, 48),
			'without an offer: the same, then 6, 12, 18 and 24 hours after, and nothing more');
		$this->assertSame(8, count(array_filter(self::sendsWithin(true, 48), fn($m) => $m >= 1440 && $m < 2880)), 'eight a day after the first day');
		$this->assertSame(0, shopOfferNextSend(0, -1, true), 'never sent: owed at once');
		$this->assertSame(900, shopOfferNextSend(0, 420, true), 'a late tick: the next time after the last send');
	}

	public function testAChangedPairIsAnEventAndEveryShopIsOwedItAtOnce(): void
	{
		$plan = shopOfferPlanItem(self::state(self::NORMAL, self::T - 7200, self::T - 7200), self::SHOPS, self::OFFER, true, self::T);
		$this->assertSame(self::T, $plan['state']['anchor']);
		$this->assertSame('the prices changed', $plan['state']['event']);
		$this->assertSame(['k1' => true, 'k2' => true, 'k3' => true], $plan['send']);
		$this->assertTrue($plan['urgent']);
		$this->assertTrue($plan['fresh'], 'new prices: they go before repeats');
		$this->assertSame(self::OFFER, $plan['state']['shops']['k1']['pending']);
		$resend = shopOfferPlanItem(self::state(self::OFFER, self::T, self::T), self::SHOPS, self::OFFER, true, self::T + 120);
		$this->assertSame([true, false], [$resend['urgent'], $resend['fresh']], 'a re-send of what every shop accepted is a repeat');
	}

	public function testNothingIsOwedBetweenScheduledTimesEvenWhenAShopIsNotKnownToHaveThePair(): void
	{
		$state = self::state(self::OFFER, self::T, self::T, ['sales' => '1.000', 'retail' => '2.00']);
		$plan = shopOfferPlanItem($state, self::SHOPS, self::OFFER, true, self::T + 119);
		$this->assertSame([], $plan['send'], 'the schedule decides; what a shop last accepted is bookkeeping');
		$this->assertSame(self::T + 120, $plan['next']);
		$this->assertCount(3, shopOfferPlanItem($state, self::SHOPS, self::OFFER, true, self::T + 120)['send']);
		$certain = self::state(self::OFFER, self::T, self::T + 900);
		$this->assertCount(3, shopOfferPlanItem($certain, self::SHOPS, self::OFFER, true, self::T + 3600)['send'],
			'an accepted pair does not stop the re-sends');
	}

	public function testAnItemIsUntrackedOnly24HoursAfterItsLastEventWithEverythingDone(): void
	{
		$day = 86400;
		$done = self::state(self::NORMAL, self::T, self::T + $day);
		$this->assertFalse(shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, self::T + $day + 599)['untrack'],
			'the last re-send could still be on its way');
		$this->assertTrue(shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, self::T + $day + 600)['untrack']);
		$this->assertFalse(shopOfferPlanItem($done, self::SHOPS, self::OFFER, true, self::T + 2 * $day)['untrack'], 'never while an offer is active');
		$failed = $done;
		$failed['shops']['k2']['attempts'] = 1;
		$this->assertFalse(shopOfferPlanItem($failed, self::SHOPS, self::NORMAL, false, self::T + 2 * $day)['untrack'], 'not with a failure open');
		$other = $done;
		$other['shops']['k3']['confirmed'] = self::OFFER;
		$this->assertFalse(shopOfferPlanItem($other, self::SHOPS, self::NORMAL, false, self::T + 2 * $day)['untrack'], 'not before every shop accepted it');
		$early = self::state(self::NORMAL, self::T, self::T + 64800);
		$this->assertFalse(shopOfferPlanItem($early, self::SHOPS, self::NORMAL, false, self::T + $day - 1)['untrack'], 'not within 24 hours');
	}

	public function testTheCountersBelongToThePendingPair(): void
	{
		$state = self::state(self::OFFER, self::T, self::T);
		$state['shops']['k2'] = ['pending' => self::OFFER, 'attempts' => 0, 'refusals' => 8, 'retryAt' => self::T + 1800, 'error' => 'x', 'gaveUp' => true]
			+ $state['shops']['k2'];
		$plan = shopOfferPlanItem($state, self::SHOPS, self::OFFER, true, self::T + 99999);
		$this->assertSame(['k2'], $plan['gaveUp']);
		$this->assertArrayNotHasKey('k2', $plan['send'], 'given up for this pair');
		$new = ['sales' => '129.200', 'retail' => '799.00'];
		$plan = shopOfferPlanItem($state, self::SHOPS, $new, true, self::T + 1);
		$this->assertArrayHasKey('k2', $plan['send'], 'a new pair is sent at once: no inherited refusals, no inherited back-off');
		$e = $plan['state']['shops']['k2'];
		$this->assertSame([0, 0, 0, false], [$e['refusals'], $e['attempts'], $e['retryAt'], $e['gaveUp']]);
	}

	public function testEachAnswerIsRecordedForItsShop(): void
	{
		$entry = shopOfferShopEntry(self::state(self::OFFER, self::T, self::T, self::NORMAL), 'k1');
		[$o, $e] = shopOfferRecordShop($entry, self::OFFER, ['ok' => true, 'httpCode' => 200], self::T);
		$this->assertSame(['ok', self::OFFER, self::T], [$o, $e['confirmed'], $e['okAt']]);
		[$o, $e] = shopOfferRecordShop($entry, self::OFFER, ['ok' => false, 'httpCode' => 200, 'transient' => false, 'error' => 'refused'], self::T);
		$this->assertSame(['refused', self::NORMAL, 1, self::T + 60], [$o, $e['confirmed'], $e['refusals'], $e['retryAt']]);
		[$o, $e] = shopOfferRecordShop($entry, self::OFFER, ['ok' => false, 'httpCode' => 404, 'transient' => false, 'error' => 'http status 404'], self::T);
		$this->assertSame('refused', $o, 'a 4xx is an answer too');
		[$o, $e] = shopOfferRecordShop($entry, self::OFFER, ['ok' => false, 'httpCode' => 503, 'transient' => true, 'error' => 'http status 503'], self::T);
		$this->assertSame(['failed', 1, 0], [$o, $e['attempts'], $e['refusals']]);
		[$o, $e] = shopOfferRecordShop($entry, self::OFFER, ['ok' => false, 'httpCode' => 0, 'transient' => true, 'error' => 'timeout'], self::T);
		$this->assertSame('failed', $o);
	}

	public function testTheEighthRefusalOfAPairGivesUpOnce(): void
	{
		$entry = shopOfferShopEntry(['shops' => ['k' => ['pending' => self::OFFER]]], 'k');
		$gaveUp = [];
		for ($i = 1; $i <= SHOP_OFFER_MAX_REFUSALS + 1; $i++) {
			[, $entry, $now] = shopOfferRecordShop($entry, self::OFFER, ['ok' => false, 'httpCode' => 200, 'transient' => false, 'error' => 'no'], self::T);
			$gaveUp[] = $now;
		}
		$this->assertSame([false, false, false, false, false, false, false, true, false], $gaveUp);
	}

	public function testUnfinishedSendsAndEarlierPairsSentByOtherPathsAreEvents(): void
	{
		$state = self::state(self::OFFER, self::T, self::T);
		$state['intent'] = ['at' => self::T, 'pair' => self::OFFER, 'shops' => ['k1']];
		shopOfferAbsorbEvents($state, self::T + 300, false);
		$this->assertSame([self::T + 300, 'a send was not finished'], [$state['anchor'], $state['event']]);
		$this->assertArrayNotHasKey('intent', $state);
		$state['foreign']['staleAt'] = self::T + 400;
		shopOfferAbsorbEvents($state, self::T + 500, false);
		$this->assertSame(self::T + 400, $state['anchor'], 'from when the earlier pair was sent');
		$events = $state['events'];
		shopOfferAbsorbEvents($state, self::T + 510, false);
		$this->assertSame($events, $state['events'], 'a mark is an event once');
		$state['foreign']['staleAt'] = self::T + 9999;
		shopOfferAbsorbEvents($state, self::T + 520, false);
		$this->assertSame(self::T + 520, $state['anchor'], 'never later than the tick\'s clock');
		shopOfferAbsorbEvents($state, self::T + 600, self::T + 550);
		$this->assertSame([self::T + 550, 'another path sent without a mark'], [$state['anchor'], $state['event']]);
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

	public function testOtherPathsGetThePairEachShopLastAccepted(): void
	{
		$params = ['update_stock' => 1, 'stock' => 4, 'salesPrice' => '639.200', 'retailPrice' => '899.000'];
		$handle = ['pairs' => [0 => self::OFFER, 1 => null, 2 => self::OFFER]];
		$groups = shopOfferForeignGroups([0 => 'a', 1 => 'b', 2 => 'c'], $handle, $params);
		$this->assertSame([
			['targets' => [0 => 'a', 2 => 'c'], 'params' => ['update_stock' => 1, 'stock' => 4, 'salesPrice' => '159.200', 'retailPrice' => '799.00']],
			['targets' => [1 => 'b'], 'params' => $params],
		], $groups, 'a shop that never accepted a pair gets today\'s prices');
		$this->assertSame([['targets' => [0 => 'a'], 'params' => $params]], shopOfferForeignGroups([0 => 'a'], null, $params), 'not tracked: unchanged');
	}

	public function testTheStateIsSelfDescribingJson(): void
	{
		$GLOBALS['db'] = 'unit_shopoffer_' . bin2hex(random_bytes(4));
		$dir = dirname(__DIR__, 2) . '/temp/' . $GLOBALS['db'];
		try {
			$this->assertSame('', shopOfferPushDir(), 'no directory until the reconciler makes one');
			$this->assertNotSame('', shopOfferPushDir(true));
			$state = self::state(['sales' => '', 'retail' => null], self::T, self::T);
			$this->assertTrue(shopOfferStateWrite(42, $state));
			$this->assertStringStartsWith('{', (string)file_get_contents($dir . '/shopoffer/item-42.json'), 'JSON, no positional fields');
			$this->assertSame(['sales' => '', 'retail' => null], shopOfferStateRead(42)['desired'], 'empty fields kept');
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
