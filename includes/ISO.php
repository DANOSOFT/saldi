<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/ISO.php --- ver 5.0.0 --- 2026.10.05 ---
//                           LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20261005 CL/NTR Created. Country name to ISO 3166-1 code and Danish postal code to ISO 3166-2 region,
//                 moved from finans/saft.php and debitor/saftCashRegister.php.

/**
 * ISO 3166 lookups shared by the SAF-T exports.
 */
class ISO {
	/**
	 * EU member states, keyed by English country name (as stored in adresser.land).
	 *
	 * @var array<string,string> country name => ISO 3166-1 alpha-2 code
	 */
	public const EU_COUNTRIES = [
		'Austria' => 'AT',
		'Belgium' => 'BE',
		'Bulgaria' => 'BG',
		'Croatia' => 'HR',
		'Cyprus' => 'CY',
		'Czechia' => 'CZ',
		'Denmark' => 'DK',
		'Estonia' => 'EE',
		'Finland' => 'FI',
		'France' => 'FR',
		'Germany' => 'DE',
		'Greece' => 'GR',
		'Hungary' => 'HU',
		'Ireland' => 'IE',
		'Italy' => 'IT',
		'Latvia' => 'LV',
		'Lithuania' => 'LT',
		'Luxembourg' => 'LU',
		'Malta' => 'MT',
		'Netherlands' => 'NL',
		'Poland' => 'PL',
		'Portugal' => 'PT',
		'Romania' => 'RO',
		'Slovakia' => 'SK',
		'Slovenia' => 'SI',
		'Spain' => 'ES',
		'Sweden' => 'SE',
	];

	/**
	 * Non-EU countries the system already handles.
	 *
	 * @var array<string,string> country name => ISO 3166-1 alpha-2 code
	 */
	public const OTHER_COUNTRIES = [
		'Norway' => 'NO',
		'Switzerland' => 'CH',
	];

	/**
	 * Alternative spellings of the names above.
	 *
	 * @var array<string,string> alternative name => name used in EU_COUNTRIES/OTHER_COUNTRIES
	 */
	public const COUNTRY_ALIASES = [
		'Czech Republic' => 'Czechia',
		'The Netherlands' => 'Netherlands',
	];

	/**
	 * Danish postal code ranges per ISO 3166-2 region. The ranges do not overlap.
	 *
	 * @var array<string,array<int,array{int,int}>> region code => list of [first, last] postal codes
	 */
	private const DK_REGION_RANGES = [
		'DK-85' => [[2640, 2644], [2670, 2690], [4000, 4040], [4060, 4990]], // Sjælland
		'DK-84' => [[1, 2635], [2650, 2665], [2700, 3670], [3700, 3790], [4050, 4050]], // Hovedstaden
		'DK-83' => [[5000, 6870], [7000, 7120], [7173, 7260], [7300, 7323]], // Syddanmark
		'DK-82' => [[6880, 6990], [7130, 7171], [7270, 7280], [7330, 7680], [7790, 7884], [8000, 8990]], // Midtjylland
		'DK-81' => [[7700, 7770], [7900, 7990], [9000, 9990]], // Nordjylland
	];

	/**
	 * @param string $nameOfCountry English country name, e.g. "Denmark"
	 * @return string|null ISO 3166-1 alpha-2 code, or null when the country is not known
	 */
	public static function countryCode(string $nameOfCountry): ?string {
		$name = self::COUNTRY_ALIASES[$nameOfCountry] ?? $nameOfCountry;
		return self::EU_COUNTRIES[$name] ?? self::OTHER_COUNTRIES[$name] ?? null;
	}

	/**
	 * @param string $nameOfCountry English country name, e.g. "Germany"
	 * @return bool true when the country is an EU member state
	 */
	public static function isEuCountry(string $nameOfCountry): bool {
		return isset(self::EU_COUNTRIES[self::COUNTRY_ALIASES[$nameOfCountry] ?? $nameOfCountry]);
	}

	/**
	 * Converts a Danish postal code to its ISO 3166-2 region.
	 *
	 * @param int $postalCode Danish postal code
	 * @param string $nameOfCountry English country name; only "Denmark" has regions
	 * @return string ISO 3166-2 region code (DK-81 to DK-85), or 'NA' for other countries or unknown postal codes
	 */
	public static function regionNumber(int $postalCode, string $nameOfCountry): string {
		if ($nameOfCountry !== 'Denmark') {
			return 'NA';
		}
		foreach (self::DK_REGION_RANGES as $region => $ranges) {
			foreach ($ranges as [$first, $last]) {
				if ($postalCode >= $first && $postalCode <= $last) {
					return $region;
				}
			}
		}
		return 'NA';
	}
}
