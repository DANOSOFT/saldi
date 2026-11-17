<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/campaignPriceImport.php';

/**
 * The campaign (offer price) columns of the item import, systemdata/importer_varer.php.
 */
final class campaignPriceImport extends TestCase
{
	/**
	 * @return array<string, array{string, float}>
	 */
	public static function validAmounts(): array
	{
		return [
			'danish decimal'            => ['149,95', 149.95],
			'danish thousands'          => ['1.299,00', 1299.0],
			'danish thousands no dec'   => ['1.299', 1299.0],
			'danish millions'           => ['1.234.567,8', 1234567.8],
			'integer'                   => ['149', 149.0],
			'zero'                      => ['0', 0.0],
			'dot decimal'               => ['149.95', 149.95],
			'dot decimal four places'   => ['1.2345', 1.2345],
			'leading zero dot'          => ['0.995', 0.995],
			'english thousands'         => ['1,299.00', 1299.0],
			'space thousands'           => ['1 299,00', 1299.0],
			'no-break space thousands'  => ["1\xC2\xA0299,00", 1299.0],
			'kr suffix'                 => ['149,95 kr.', 149.95],
			'kr prefix'                 => ['kr. 149,95', 149.95],
			'dkk prefix'                => ['DKK 149,95', 149.95],
			'comma dash'                => ['149,-', 149.0],
			'surrounding spaces'        => ['  149,95  ', 149.95],
		];
	}

	#[DataProvider('validAmounts')]
	public function testReadsPrices(string $cell, float $expected): void
	{
		$this->assertSame($expected, campaignImportAmount($cell));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidAmounts(): array
	{
		return [
			'empty'          => [''],
			'text'           => ['Tilbudspris'],
			'negative'       => ['-149,95'],
			'two commas'     => ['1,2,3'],
			'broken groups'  => ['1.23.4'],
			'date'           => ['27-11-2026'],
			'trailing text'  => ['149,95 ex moms'],
			'too large'      => ['1.000.000.000'],
		];
	}

	#[DataProvider('invalidAmounts')]
	public function testRejectsWhatIsNotAPrice(string $cell): void
	{
		$this->assertNull(campaignImportAmount($cell));
	}

	public function testConvertsInclVatToTheStoredExVatPrice(): void
	{
		$this->assertSame(119.96, campaignImportExVat(149.95, 25.0));
		$this->assertSame(79.992, campaignImportExVat(99.99, 25.0));
		$this->assertSame(149.95, campaignImportExVat(149.95, 0.0));
		// find_pris() adds the rate back: the shop shows the sheet's price.
		$this->assertSame(99.99, round(campaignImportExVat(99.99, 25.0) * 1.25, 2));
	}

	/**
	 * @return array<string, array{string, bool, string}>
	 */
	public static function validTimes(): array
	{
		return [
			'hh:mm start'         => ['08:00', false, '08:00:00'],
			'h:mm start'          => ['8:00', false, '08:00:00'],
			'dot start'           => ['8.00', false, '08:00:00'],
			'seconds start'       => ['08:30:15', false, '08:30:15'],
			'kl prefix'           => ['kl. 9.30', false, '09:30:00'],
			'end minute 59'       => ['23:59', true, '23:59:59'],
			'end other minute 59' => ['20:59', true, '20:59:59'],
			'end other minute'    => ['20:00', true, '20:00:00'],
			'end with seconds'    => ['23:59:00', true, '23:59:00'],
			'end 24:00'           => ['24:00', true, '23:59:59'],
			'start minute 59'     => ['07:59', false, '07:59:00'],
		];
	}

	#[DataProvider('validTimes')]
	public function testReadsTimes(string $cell, bool $isEnd, string $expected): void
	{
		$this->assertSame($expected, campaignImportTime($cell, $isEnd));
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function invalidTimes(): array
	{
		return [
			'empty'          => ['', true],
			'hour only'      => ['8', false],
			'hour 25'        => ['25:00', true],
			'minute 60'      => ['08:60', false],
			'second 60'      => ['08:00:60', false],
			'start 24:00'    => ['24:00', false],
			'text'           => ['morgen', false],
		];
	}

	#[DataProvider('invalidTimes')]
	public function testRejectsWhatIsNotATime(string $cell, bool $isEnd): void
	{
		$this->assertNull(campaignImportTime($cell, $isEnd));
	}

	/**
	 * @return array<string, array{string, bool, string, string|null}>
	 */
	public static function validDates(): array
	{
		return [
			'dash'              => ['27-11-2026', false, '2026-11-27', null],
			'dot'               => ['27.11.2026', false, '2026-11-27', null],
			'slash'             => ['27/11/2026', false, '2026-11-27', null],
			'single digits'     => ['1-2-2027', false, '2027-02-01', null],
			'iso'               => ['2026-11-27', false, '2026-11-27', null],
			'with time'         => ['27-11-2026 08:00', false, '2026-11-27', '08:00:00'],
			'with kl'           => ['27.11.2026 kl. 8.00', false, '2026-11-27', '08:00:00'],
			'with seconds'      => ['27-11-2026 08:00:00', false, '2026-11-27', '08:00:00'],
			'iso T'             => ['2026-11-30T23:59', true, '2026-11-30', '23:59:59'],
			'end time'          => ['30-11-2026 20:00', true, '2026-11-30', '20:00:00'],
			'leap day'          => ['29-02-2028', false, '2028-02-29', null],
		];
	}

	#[DataProvider('validDates')]
	public function testReadsDates(string $cell, bool $isEnd, string $date, ?string $time): void
	{
		$this->assertSame(['date' => $date, 'time' => $time], campaignImportDateTime($cell, $isEnd));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidDates(): array
	{
		return [
			'empty'           => [''],
			'31 february'     => ['31-02-2026'],
			'month 13'        => ['01-13-2026'],
			'two digit year'  => ['27-11-26'],
			'mixed separator' => ['27-11.2026'],
			'iso with dots'   => ['2026.11.27'],
			'bad time'        => ['27-11-2026 25:00'],
			'text after'      => ['27-11-2026 morgen'],
			'header'          => ['Startdato'],
			'year 1900'       => ['01-01-1900'],
			'american'        => ['11/27/2026'],
		];
	}

	#[DataProvider('invalidDates')]
	public function testRejectsWhatIsNotADate(string $cell): void
	{
		$this->assertNull(campaignImportDateTime($cell, false));
	}

	public function testDatesWithoutTimesCoverWholeDays(): void
	{
		$window = campaignImportWindow(['special_from_date' => '27-11-2026', 'special_to_date' => '30-11-2026']);
		$this->assertNull($window['error']);
		$this->assertSame([
			'special_from_date' => '2026-11-27',
			'special_from_time' => '00:00:00',
			'special_to_date'   => '2026-11-30',
			'special_to_time'   => '23:59:59',
		], $window['values']);
	}

	public function testTimeInTheDateCellIsUsed(): void
	{
		$window = campaignImportWindow(['special_from_date' => '27-11-2026 08:00', 'special_to_date' => '30-11-2026 20:00']);
		$this->assertSame('08:00:00', $window['values']['special_from_time']);
		$this->assertSame('20:00:00', $window['values']['special_to_time']);
	}

	public function testTimeColumnWinsOverTheDateCell(): void
	{
		$window = campaignImportWindow([
			'special_from_date' => '27-11-2026 08:00',
			'special_from_time' => '10.00',
			'special_to_date'   => '30-11-2026',
			'special_to_time'   => '',
		]);
		$this->assertSame('10:00:00', $window['values']['special_from_time']);
		$this->assertSame('23:59:59', $window['values']['special_to_time']);
	}

	public function testEmptyCellsLeaveTheColumnsAlone(): void
	{
		$window = campaignImportWindow(['special_from_date' => '', 'special_to_date' => '  ']);
		$this->assertSame(['values' => [], 'error' => null], $window);
		$window = campaignImportWindow(['special_to_date' => '30-11-2026']);
		$this->assertSame(['special_to_date' => '2026-11-30', 'special_to_time' => '23:59:59'], $window['values']);
	}

	public function testATimeAloneIsWritten(): void
	{
		$window = campaignImportWindow(['special_to_time' => '18:00']);
		$this->assertSame(['special_to_time' => '18:00:00'], $window['values']);
	}

	public function testInvalidCellsNameTheirColumn(): void
	{
		$this->assertSame('special_to_date', campaignImportWindow(['special_from_date' => '27-11-2026', 'special_to_date' => '31-11-2026'])['error']);
		$this->assertSame('special_from_date', campaignImportWindow(['special_from_date' => '2026/27/11'])['error']);
		$this->assertSame('special_from_time', campaignImportWindow(['special_from_date' => '27-11-2026', 'special_from_time' => '8'])['error']);
		$this->assertSame([], campaignImportWindow(['special_from_date' => 'Startdato'])['values']);
	}

	public function testAPeriodThatEndsBeforeItStartsIsRejected(): void
	{
		$this->assertSame('special_to_date', campaignImportWindow(['special_from_date' => '30-11-2026', 'special_to_date' => '27-11-2026'])['error']);
		$this->assertSame('special_to_date', campaignImportWindow(['special_from_date' => '27-11-2026 10:00', 'special_to_date' => '27-11-2026 09:00'])['error']);
		$this->assertNull(campaignImportWindow(['special_from_date' => '27-11-2026', 'special_to_date' => '27-11-2026'])['error']);
	}

	public function testReadsACampaignLine(): void
	{
		$line = campaignImportLine([
			'special_price_incl_moms' => '149,95',
			'special_from_date'       => '27-11-2026 08:00',
			'special_to_date'         => '30-11-2026',
		]);
		$this->assertSame([
			'values'       => [
				'special_from_date' => '2026-11-27',
				'special_from_time' => '08:00:00',
				'special_to_date'   => '2026-11-30',
				'special_to_time'   => '23:59:59',
			],
			'price'        => 149.95,
			'priceInclVat' => true,
			'error'        => null,
		], $line);
	}

	public function testAnExVatPriceIsStoredAsItIs(): void
	{
		$line = campaignImportLine(['special_price' => '119,96']);
		$this->assertSame(119.96, $line['price']);
		$this->assertFalse($line['priceInclVat']);
		$this->assertSame([], $line['values']);
	}

	public function testTheInclVatPriceWinsWhenBothAreFilled(): void
	{
		$line = campaignImportLine(['special_price' => '100', 'special_price_incl_moms' => '149,95']);
		$this->assertSame(149.95, $line['price']);
		$this->assertTrue($line['priceInclVat']);
	}

	public function testAnEmptyPriceLeavesThePriceAlone(): void
	{
		$line = campaignImportLine(['special_price_incl_moms' => '', 'special_to_date' => '30-11-2026']);
		$this->assertNull($line['price']);
		$this->assertNull($line['error']);
		$this->assertSame('2026-11-30', $line['values']['special_to_date']);
	}

	public function testABadCellNamesTheColumnAndWritesNothing(): void
	{
		$this->assertSame('special_price_incl_moms', campaignImportLine(['special_price_incl_moms' => 'Tilbudspris'])['error']);
		$this->assertSame('special_price', campaignImportLine(['special_price_incl_moms' => '149,95', 'special_price' => 'x'])['error']);
		$line = campaignImportLine(['special_price_incl_moms' => '149,95', 'special_to_date' => '31-11-2026']);
		$this->assertSame('special_to_date', $line['error']);
		$this->assertSame([], $line['values']);
	}

	public function testRecognisesACampaignOnlyMapping(): void
	{
		$this->assertTrue(campaignImportOnly(['varenr', 'special_price_incl_moms', 'special_from_date', 'special_to_date', '-', '']));
		$this->assertTrue(campaignImportOnly(['Varenr', 'special_price']));
		$this->assertFalse(campaignImportOnly(['varenr', 'special_price', 'salgspris_incl_moms']));
		$this->assertFalse(campaignImportOnly(['varenr', 'beskrivelse']));
		$this->assertFalse(campaignImportOnly(['special_price', 'special_from_date']));
		$this->assertFalse(campaignImportOnly(['varenr']));
		$this->assertFalse(campaignImportOnly(['varenr', 'special_price', 'gruppe']));
	}
}
