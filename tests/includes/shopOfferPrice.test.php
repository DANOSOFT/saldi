<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stdFunc/shopOfferPrice.php';
require_once __DIR__ . '/../../includes/stdFunc/shopOfferPush.php';

/**
 * The prices the webshops are sent during and outside an offer (includes/stdFunc/shopOfferPrice.php) and the push decision
 * (includes/stdFunc/shopOfferPush.php).
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

	/**
	 * @return array<string, array{array{salesPrice: string, retailPrice: string, offer: bool}, array{sales: string, retail: string, attempts: int, retryAt: int}|null, string}>
	 */
	public static function decisions(): array
	{
		$offer = ['salesPrice' => '159.200', 'retailPrice' => '799.00', 'offer' => true];
		$normal = ['salesPrice' => '639.200', 'retailPrice' => '899.000', 'offer' => false];
		$shown = ['sales' => '159.200', 'retail' => '799.00', 'attempts' => 0, 'retryAt' => 0];
		return [
			'offer starts'                      => [$offer, null, 'push'],
			'nothing on record, no offer'       => [$normal, null, 'none'],
			'offer already shown'               => [$offer, $shown, 'none'],
			'offer ended'                       => [$normal, $shown, 'push'],
			'offer price changed'               => [['salesPrice' => '149.000'] + $offer, $shown, 'push'],
			'failed start, retry not yet due'   => [$offer, ['sales' => '', 'retail' => '', 'attempts' => 1, 'retryAt' => 2000], 'wait'],
			'failed start, retry due'           => [$offer, ['sales' => '', 'retail' => '', 'attempts' => 1, 'retryAt' => 500], 'push'],
			'failed start, offer over meanwhile' => [$normal, ['sales' => '', 'retail' => '', 'attempts' => 3, 'retryAt' => 2000], 'forget'],
			'failed end, retry due'             => [$normal, ['attempts' => 2, 'retryAt' => 900] + $shown, 'push'],
			'failed end, retry not yet due'     => [$normal, ['attempts' => 2, 'retryAt' => 1100] + $shown, 'wait'],
		];
	}

	/**
	 * @param array{salesPrice: string, retailPrice: string, offer: bool} $prices
	 * @param array{sales: string, retail: string, attempts: int, retryAt: int}|null $record
	 */
	#[DataProvider('decisions')]
	public function testOnlyAChangeIsSent(array $prices, ?array $record, string $expected): void
	{
		$this->assertSame($expected, shopOfferPushDecide($prices, $record, 1000));
	}
}
