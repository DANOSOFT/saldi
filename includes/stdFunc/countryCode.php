<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/countryCode.php --- ver 5.0.0 --- 2026-10-19 ---
// LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261019 CL/MJ SST-870 Created. ordrer.land and adresser.land are free text - one tenant has
//                  "Sweden", "Sverige" and "SE" all meaning the same country - so the code the
//                  e-invoice carries is derived here instead of being guessed from the CVR prefix.

if (!function_exists('countryCodeIso2')) {
	/**
	 * Reads a free-text country field as an ISO 3166-1 alpha-2 code.
	 *
	 * ordrer.land and adresser.land are typed by hand and hold whatever the bookkeeper wrote:
	 * the Danish name, the English name, or the code itself. All three are recognised; anything
	 * else returns $fallback, so a value nobody anticipated never becomes a wrong country.
	 *
	 * Lookup is on an ASCII skeleton - lower-cased, with every character that is not a-z or 0-9
	 * removed. That is deliberate and not only tidiness: a tenant database may be UTF-8 or
	 * ISO-8859-1 (see $db_encode in includes/stdFunc/findTxt.php), so "Grønland" arrives here as
	 * different bytes depending on the install. Dropping the non-ASCII bytes makes both spellings
	 * fold to the same key, which is why the keys below look clipped - "grnland", not "groenland".
	 * It also absorbs the spacing and punctuation differences in names like "Faroe Islands".
	 *
	 * The set is the EU and EEA plus the United Kingdom, Switzerland and the two Danish
	 * territories - the area e-invoicing is actually routed in. A country outside it falls back
	 * exactly as before this function existed, rather than being half-supported. Greenland and the
	 * Faroe Islands return their own codes, GL and FO; a caller that has to send them inside the
	 * Danish VAT area is the right place to decide that, not this function.
	 *
	 * Alpha-3 codes ("SWE") are not recognised, and fall back.
	 *
	 * @param string $country   Whatever is stored in the country field.
	 * @param string $fallback  Returned when $country is empty or not recognised.
	 * @return string  Upper-case alpha-2 code, or $fallback unchanged.
	 */
	function countryCodeIso2($country, $fallback = '') {
		$key = preg_replace('/[^a-z0-9]/', '', strtolower((string)$country));
		if ($key === '' || $key === null) {
			return $fallback;
		}

		static $map = null;
		if ($map === null) {
			$map = array();
			// code => every spelling that means it: alpha-2, Danish, English.
			$countries = array(
				'AT' => array('at', 'ostrig', 'strig', 'austria'),
				'BE' => array('be', 'belgien', 'belgium'),
				'BG' => array('bg', 'bulgarien', 'bulgaria'),
				'CH' => array('ch', 'schweiz', 'switzerland'),
				'CY' => array('cy', 'cypern', 'cyprus'),
				'CZ' => array('cz', 'tjekkiet', 'czechia', 'czechrepublic'),
				'DE' => array('de', 'tyskland', 'germany', 'deutschland'),
				'DK' => array('dk', 'danmark', 'denmark'),
				'EE' => array('ee', 'estland', 'estonia'),
				'ES' => array('es', 'spanien', 'spain'),
				'FI' => array('fi', 'finland'),
				// "frerne" is what "Færøerne" folds to once æ and ø are dropped; "faeroerne"
				// catches the same name typed without them.
				'FO' => array('fo', 'frerne', 'faeroerne', 'faroeislands', 'faroes'),
				'FR' => array('fr', 'frankrig', 'france'),
				'GB' => array('gb', 'uk', 'storbritannien', 'unitedkingdom', 'greatbritain', 'england'),
				'GL' => array('gl', 'grnland', 'groenland', 'greenland'),
				'GR' => array('gr', 'grkenland', 'graekenland', 'greece'),
				'HR' => array('hr', 'kroatien', 'croatia'),
				'HU' => array('hu', 'ungarn', 'hungary'),
				'IE' => array('ie', 'irland', 'ireland'),
				'IS' => array('is', 'island', 'iceland'),
				'IT' => array('it', 'italien', 'italy'),
				'LI' => array('li', 'liechtenstein'),
				'LT' => array('lt', 'litauen', 'lithuania'),
				'LU' => array('lu', 'luxembourg', 'luxemburg'),
				'LV' => array('lv', 'letland', 'latvia'),
				'MT' => array('mt', 'malta'),
				'NL' => array('nl', 'holland', 'nederlandene', 'netherlands', 'thenetherlands'),
				'NO' => array('no', 'norge', 'norway'),
				'PL' => array('pl', 'polen', 'poland'),
				'PT' => array('pt', 'portugal'),
				'RO' => array('ro', 'rumnien', 'rumaenien', 'romania'),
				'SE' => array('se', 'sverige', 'sweden'),
				'SI' => array('si', 'slovenien', 'slovenia'),
				'SK' => array('sk', 'slovakiet', 'slovakia'),
			);
			foreach ($countries as $code => $spellings) {
				foreach ($spellings as $spelling) {
					$map[$spelling] = $code;
				}
			}
		}

		return isset($map[$key]) ? $map[$key] : $fallback;
	}
}
