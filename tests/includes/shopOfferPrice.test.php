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
		$this->assertFalse(shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, self::T + $day + 659)['untrack'],
			'the last re-send could still be on its way');
		$this->assertTrue(shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, self::T + $day + 660)['untrack']);
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
		$state = self::state(self::OFFER, self::T, self::T + 3600); // every shop was sent the pair at the 60-minute re-send
		$state['shops']['k2'] = ['pending' => self::OFFER, 'attempts' => 0, 'refusals' => 9, 'retryAt' => self::T + 3600 + 10800, 'error' => 'x']
			+ $state['shops']['k2'];
		$plan = shopOfferPlanItem($state, self::SHOPS, self::OFFER, true, self::T + 7200);
		$this->assertSame(['k2'], $plan['refusing'], 'nine refusals: shown as stuck');
		$this->assertArrayNotHasKey('k2', $plan['send'], 'not before its back-off');
		$this->assertArrayHasKey('k2', shopOfferPlanItem($state, self::SHOPS, self::OFFER, true, self::T + 3600 + 10800)['send'], 'but never given up');
		$new = ['sales' => '129.200', 'retail' => '799.00'];
		$plan = shopOfferPlanItem($state, self::SHOPS, $new, true, self::T + 7201);
		$this->assertArrayHasKey('k2', $plan['send'], 'a new pair is sent at once: no inherited refusals, no inherited back-off');
		$e = $plan['state']['shops']['k2'];
		$this->assertSame([0, 0, 0], [$e['refusals'], $e['attempts'], $e['retryAt']]);
	}

	public function testARefusingShopIsTriedAtMostThreeHoursAfterItsLastRefusalAlsoInTheTail(): void
	{
		$refusedAt = self::T + 21600; // the tail's 6-hour re-send of the normal prices; the next one is at 12 hours
		$state = self::state(self::NORMAL, self::T, $refusedAt, self::OFFER);
		$state['shops']['k2'] = ['pending' => self::NORMAL, 'refusals' => 5, 'retryAt' => $refusedAt + 960] + $state['shops']['k2'];
		$this->assertArrayNotHasKey('k2', shopOfferPlanItem($state, self::SHOPS, self::NORMAL, false, $refusedAt + 959)['send']);
		$this->assertSame(['k2' => true], shopOfferPlanItem($state, self::SHOPS, self::NORMAL, false, $refusedAt + 960)['send'],
			'the fifth refusal waits 16 minutes, not for the 12-hour re-send');
		$state['shops']['k2'] = ['refusals' => 12, 'retryAt' => $refusedAt + 10800] + $state['shops']['k2'];
		$this->assertSame(['k2' => true], shopOfferPlanItem($state, self::SHOPS, self::NORMAL, false, $refusedAt + 10800)['send'],
			'at most 3 hours, however often it refused');
	}

	public function testARefusingShopIsTriedAgainAfterTheScheduleHasRunOut(): void
	{
		$day = 86400;
		$state = self::state(self::NORMAL, self::T, self::T + $day, self::OFFER);
		$state['shops']['k2'] = ['pending' => self::NORMAL, 'refusals' => 12, 'retryAt' => self::T + 3 * $day] + $state['shops']['k2'];
		$plan = shopOfferPlanItem($state, self::SHOPS, self::NORMAL, false, self::T + 3 * $day);
		$this->assertSame(['k2' => true], $plan['send'], 'the tail is over, but shop 2 has not accepted the normal prices');
		$this->assertFalse($plan['untrack']);
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

	public function testARefusingShopIsRetriedWithBackOffUpToThreeHoursAndNeverGivenUp(): void
	{
		$entry = shopOfferShopEntry(['shops' => ['k' => ['pending' => self::OFFER]]], 'k');
		$waits = $alerts = [];
		for ($i = 1; $i <= 11; $i++) {
			[$o, $entry, $alert] = shopOfferRecordShop($entry, self::OFFER, ['ok' => false, 'httpCode' => 200, 'transient' => false, 'error' => 'no'], self::T);
			$waits[] = intdiv($entry['retryAt'] - self::T, 60);
			$alerts[] = $alert;
		}
		$this->assertSame([1, 2, 4, 8, 16, 32, 64, 128, 180, 180, 180], $waits, 'minutes to the next try');
		$this->assertSame([false, false, false, false, false, false, false, true, false, false, false], $alerts, 'flagged once, at the eighth');
		$this->assertArrayNotHasKey('gaveUp', $entry);
	}

	public function testAnInFlightMarkerIsOpenForTLatePlusTwoMinutesThenExpires(): void
	{
		$markers = ['a' => ['path' => 'card', 'startedAt' => self::T], 'b' => ['path' => 'bulk', 'startedAt' => self::T + 100]];
		$this->assertSame(['openUntil' => self::T + 820, 'expired' => [], 'keep' => $markers], shopOfferInflightCheck($markers, self::T + 719));
		$check = shopOfferInflightCheck($markers, self::T + 720);
		$this->assertSame([self::T + 820, ['card'], ['b' => $markers['b']]], [$check['openUntil'], $check['expired'], $check['keep']],
			'the card\'s marker expired 720 s after its claim, the bulk sync\'s is still open');
		$this->assertSame(['openUntil' => 0, 'expired' => ['unreadable markers'], 'keep' => []], shopOfferInflightCheck(false, self::T),
			'markers that cannot be read count as expired: an event');
		$this->assertSame(0, shopOfferInflightCheck([], self::T)['openUntil']);
	}

	public function testAnOpenMarkerKeepsTheItemTracked(): void
	{
		$day = 86400;
		$done = self::state(self::NORMAL, self::T, self::T + $day);
		$at = self::T + $day + 700;
		$this->assertTrue(shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, $at)['untrack'], 'without a marker: untracked');
		$this->assertFalse(shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, $at, $at + 1)['untrack'], 'a marker open a second longer: not');
		$this->assertTrue(shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, $at, $at)['untrack'], 'a marker that expires now: untracked');
	}

	public function testOfferFieldsAreAPriceOfferRunningOrToCome(): void
	{
		$now = strtotime('2026-11-20 12:00:00');
		$offer = ['specialtype' => 'price', 'special_price' => '79.2', 'special_to_date' => '2026-11-30'];
		$this->assertTrue(shopOfferHasOfferFields($offer, $now));
		$this->assertTrue(shopOfferHasOfferFields(['special_to_date' => '2026-11-19'] + $offer, $now), 'ended yesterday: still selected, as the tick selects');
		$this->assertFalse(shopOfferHasOfferFields(['special_to_date' => '2026-11-18'] + $offer, $now));
		$this->assertFalse(shopOfferHasOfferFields(['special_price' => '0'] + $offer, $now));
		$this->assertFalse(shopOfferHasOfferFields(['specialtype' => 'percent'] + $offer, $now));
		$this->assertFalse(shopOfferHasOfferFields(['salgspris' => '639.2'], $now));
	}

	public function testAClaimIsSentAsItIsWithinTwoMinutes(): void
	{
		$claim = ['path' => 'card', 'vare_id' => 7, 'item' => [], 'endpoints' => [0 => 'a'], 'pairs' => [0 => self::OFFER], 'token' => 't',
			'offer' => false, 'claimedAt' => 1000.0];
		$this->assertSame($claim, shopOfferForeignReady($claim, 1119.9));
		$this->assertNull(shopOfferForeignReady(null, 5000.0));
		// older: recorded and taken again; with no offer state on this account (the unit tests have none) that is today's prices
		$this->assertNull(shopOfferForeignReady($claim, 1120.0));
	}

	public function testUntrackingWaitsForTheLastDispatchPlusTLatePlusAMargin(): void
	{
		$day = 86400;
		$dispatched = self::T + $day + 23; // the tick at T + 1 day sent; its request left 23 seconds later
		$done = self::state(self::NORMAL, self::T, self::T + $day);
		foreach ($done['shops'] as $key => $entry) $done['shops'][$key]['leftAt'] = $dispatched;
		$this->assertFalse(shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, $dispatched + 600)['untrack'], 'T_late after the dispatch: not yet');
		$this->assertFalse(shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, $dispatched + 659)['untrack']);
		$this->assertTrue(shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, $dispatched + 660)['untrack'], 'T_late plus a minute');
	}

	public function testUnfinishedSendsAndEarlierPairsAreEventsAndUnmarkedSendsGetACatchUp(): void
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
		$this->assertSame([self::T + 520, self::T + 550 + 720], [$state['anchor'], $state['catchup']],
			'a send another path could not mark or record: its catch-up, not an event');
	}

	public function testASendByAnotherPathGetsOneCatchUpWithoutRestartingTheSchedule(): void
	{
		// an active offer whose early re-sends are done: the next scheduled send is the 3-hourly one, at T + 4 hours
		$state = self::state(self::OFFER, self::T, self::T + 3600);
		shopOfferCatchUp($state, self::T + 4000);
		shopOfferCatchUp($state, self::T + 4100);
		shopOfferCatchUp($state, self::T + 3900);
		$this->assertSame(self::T + 4100 + 720, $state['catchup'], 'several sends: one catch-up, after the latest');
		$plan = shopOfferPlanItem($state, self::SHOPS, self::OFFER, true, self::T + 4819);
		$this->assertSame([[], self::T + 4820, 0, false], [$plan['send'], $plan['next'], $plan['catchupDue'], $plan['untrack']],
			'not before the latest send\'s late window has passed');
		$plan = shopOfferPlanItem($state, self::SHOPS, self::OFFER, true, self::T + 4820);
		$this->assertSame(['k1' => true, 'k2' => true, 'k3' => true], $plan['send'], 'then every shop once');
		$this->assertSame([self::T + 4820, true, self::T, 0], [$plan['catchupDue'], $plan['urgent'], $plan['state']['anchor'], (int)($plan['state']['events'] ?? 0)],
			'no event: the schedule goes on from the same anchor');
	}

	/**
	 * The reconciler's calls (three shops) in one Black Week day for an offer item that other paths send N times, spread evenly
	 * over 08:00-20:00, with the shipped planner, recorder and catch-up. Before the catch-up (6d521cc22) every such send was an
	 * event: 1,101 calls for 200 sends (shop-push/resend-6/harness/volume-sales-day.php).
	 *
	 * @return array{0: int, 1: int} Calls, catch-ups.
	 */
	private static function dayOfSends(int $sends): array
	{
		$shops = self::SHOPS;
		$day = strtotime('2026-11-28 00:00:00');
		$state = ['desired' => self::OFFER, 'anchor' => $day - 86400, 'events' => 1, 'shops' => []];
		foreach (array_keys($shops) as $key) {
			$state['shops'][$key] = ['confirmed' => self::OFFER, 'okAt' => $day - 3600, 'sentAt' => $day - 3600, 'leftAt' => $day - 3600, 'pending' => self::OFFER];
		}
		$times = [];
		for ($i = 0; $i < $sends; $i++) $times[] = $day + 8 * 3600 + (int)round(($i + 0.5) * 12 * 3600 / $sends);
		$calls = $catchUps = 0;
		for ($t = $day; $t < $day + 86400; $t += 120) {
			foreach ($times as $at) {
				if ($at > $t - 120 && $at <= $t) shopOfferCatchUp($state, $at);
			}
			$plan = shopOfferPlanItem($state, $shops, self::OFFER, true, $t);
			$state = $plan['state'];
			if ($plan['catchupDue'] && count($plan['send']) === count($shops)) {
				$catchUps++;
				if ((int)($state['catchup'] ?? 0) <= $plan['catchupDue']) unset($state['catchup']);
			}
			foreach (array_keys($plan['send']) as $key) {
				$entry = shopOfferShopEntry($state, $key);
				$entry['sentAt'] = $entry['leftAt'] = $t;
				[, $entry] = shopOfferRecordShop($entry, self::OFFER, ['ok' => true, 'httpCode' => 200], $t);
				$state['shops'][$key] = $entry;
				$calls++;
			}
		}
		return [$calls, $catchUps];
	}

	public function testTwoHundredSalesInADayCostOneCatchUp(): void
	{
		$this->assertSame([24, 0], self::dayOfSends(0), 'the schedule alone: every 3 hours');
		$this->assertSame([27, 1], self::dayOfSends(200), 'a send every 3.6 minutes: one catch-up, after the last of them');
		$this->assertSame([168, 50], self::dayOfSends(50), 'a send every 14.4 minutes, more than its 12-minute window apart: one catch-up each');
	}

	public function testAPendingCatchUpKeepsTheItemTracked(): void
	{
		$day = 86400;
		$done = self::state(self::NORMAL, self::T, self::T + $day);
		$at = self::T + $day + 700;
		$this->assertTrue(shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, $at)['untrack']);
		shopOfferCatchUp($done, $at - 100);
		$plan = shopOfferPlanItem($done, self::SHOPS, self::NORMAL, false, $at);
		$this->assertSame([false, [], $at + 620], [$plan['untrack'], $plan['send'], $plan['next']], 'tracked until its catch-up is sent');
	}

	public function testTheBackOffDoublesUpToHalfAnHour(): void
	{
		$this->assertSame([60, 120, 240, 480, 960, 1800, 1800, 1800], array_map('shopOfferBackoff', [1, 2, 3, 4, 5, 6, 7, 20]));
		$this->assertSame([7680, 10800, 10800], [shopOfferBackoff(8, 10800), shopOfferBackoff(9, 10800), shopOfferBackoff(500, 10800)]);
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
