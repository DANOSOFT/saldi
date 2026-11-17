<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/shopOfferPrice.php --- ver 5.1.1 --- 2026-11-17 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------------
// 20261117 CL/LH Created (Havemøbelland, Black Week): the prices a webshop is sent for an item at a moment.
//                During an active offer (specialtype 'price', the window as find_pris() in includes/ordrefunc.php reads it) the shop gets the offer as salesPrice and the normal price as retailPrice, so it shows the normal price as the before-price; otherwise salgspris and retail_price.
//                salesPrice keeps salgspris' convention (excluding VAT).
//                retailPrice is sent the way varer.retail_price (Vejl.pris) is entered: as typed, beside a salgspris the item card shows including VAT when the setting vatOnItemCard is on (lager/varekort.php), so the normal price is sent including VAT then, as a user copying the card's price into Vejl.pris would have done.
//                The lookups go through shopOfferFetchRow(), so inside the offer reconciler's tick they are bounded in time like its other queries.

if (!function_exists('shopOfferActive')) {
	/**
	 * Whether an item's offer price applies at a moment, read exactly as find_pris() reads it.
	 *
	 * find_pris() widens the start time to 00:00:00 once the first day has passed, and the end time to 23:59:59 only on
	 * days before the last and only when the end time is 00:00:00; any other end time is checked every day.
	 *
	 * @param array<string, mixed> $item varer columns specialtype, special_price, special_from_date, special_to_date,
	 *                                   special_from_time, special_to_time.
	 * @param int $now Unix time.
	 * @return bool
	 */
	function shopOfferActive(array $item, $now) {
		if ((string)($item['specialtype'] ?? '') !== 'price' || !((float)($item['special_price'] ?? 0))) {
			return false;
		}
		$dd = date('Y-m-d', (int)$now);
		$tt = date('H:i:s', (int)$now);
		$fromDate = (string)($item['special_from_date'] ?? '');
		$toDate = (string)($item['special_to_date'] ?? '');
		$fromTime = (string)($item['special_from_time'] ?? '');
		$toTime = (string)($item['special_to_time'] ?? '');
		if ($fromDate < $dd && $fromTime != '00:00:00') $fromTime = '00:00:00';
		if ($toDate > $dd && $toTime == '00:00:00') $toTime = '23:59:59';
		return $fromDate <= $dd && $toDate >= $dd && $fromTime <= $tt && $toTime >= $tt;
	}
}

if (!function_exists('shopOfferNextCheck')) {
	/**
	 * The next moment after $now at which shopOfferActive() may give another answer for the item.
	 *
	 * Candidates are the start, today's end time and the next midnight (where find_pris()'s widening changes); the caller
	 * evaluates again then, so a candidate where nothing changes only costs a look.
	 *
	 * @param array<string, mixed> $item As shopOfferActive() takes it.
	 * @param int $now Unix time.
	 * @return int|null Unix time, or null when the offer cannot apply again (no price offer, or it has ended).
	 */
	function shopOfferNextCheck(array $item, $now) {
		if ((string)($item['specialtype'] ?? '') !== 'price' || !((float)($item['special_price'] ?? 0))) {
			return null;
		}
		$now = (int)$now;
		$dd = date('Y-m-d', $now);
		$fromDate = (string)($item['special_from_date'] ?? '');
		$toDate = (string)($item['special_to_date'] ?? '');
		if ($toDate === '' || $toDate < $dd) {
			return null;
		}
		$toTime = (string)($item['special_to_time'] ?? '');
		if ($toDate > $dd && $toTime == '00:00:00') $toTime = '23:59:59';
		$candidates = array(strtotime('tomorrow', $now));
		if ($fromDate !== '') {
			$candidates[] = strtotime($fromDate . ' ' . ((string)($item['special_from_time'] ?? '') ?: '00:00:00'));
		}
		if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $toTime)) {
			$candidates[] = strtotime($dd . ' ' . $toTime) + 1;
		}
		$next = null;
		foreach ($candidates as $candidate) {
			if ($candidate !== false && $candidate > $now && ($next === null || $candidate < $next)) $next = $candidate;
		}
		return $next;
	}
}

if (!function_exists('shopEffectivePrices')) {
	/**
	 * The salesPrice and retailPrice a webshop is sent for an item at a moment.
	 *
	 * @param array<string, mixed> $item varer columns salgspris and retail_price, plus the offer columns shopOfferActive() reads.
	 * @param int $now Unix time.
	 * @param float $vatRate The item group's VAT percentage, used only when $vatOnItemCard is set.
	 * @param bool $vatOnItemCard The item card shows prices including VAT (settings items/vatOnItemCard).
	 * @return array{
	 *   salesPrice: string|null,   Excluding VAT, as salgspris: the offer price during an offer, otherwise salgspris as stored.
	 *   retailPrice: string|null,  During an offer the normal price in Vejl.pris' convention, otherwise retail_price as stored
	 *                              (null stays null, so the shops get what they always got).
	 *   offer: bool,               True during an active offer.
	 * }
	 */
	function shopEffectivePrices(array $item, $now, $vatRate, $vatOnItemCard) {
		if (!shopOfferActive($item, $now)) {
			return array('salesPrice' => $item['salgspris'] ?? null, 'retailPrice' => $item['retail_price'] ?? null, 'offer' => false);
		}
		$normal = (float)($item['salgspris'] ?? 0);
		if ($vatOnItemCard) $normal = $normal * (100 + (float)$vatRate) / 100;
		return array(
			'salesPrice'  => (string)$item['special_price'],
			'retailPrice' => number_format(round($normal, 2), 2, '.', ''),
			'offer'       => true,
		);
	}
}

if (!function_exists('shopPricesForItem')) {
	/**
	 * shopEffectivePrices() for a varer row, looking up the VAT convention only during an offer, so an ordinary sale costs
	 * no extra query.
	 *
	 * @param array<string, mixed> $item varer row with salgspris, retail_price, gruppe and the offer columns.
	 * @param int|null $now Unix time, default now.
	 * @return array{salesPrice: string, retailPrice: string, offer: bool}
	 */
	function shopPricesForItem(array $item, $now = null) {
		$now = ($now === null) ? time() : (int)$now;
		if (!shopOfferActive($item, $now)) {
			return shopEffectivePrices($item, $now, 0, false);
		}
		$vatOnItemCard = shopVatOnItemCard();
		return shopEffectivePrices($item, $now, $vatOnItemCard ? shopOfferVatRate($item['gruppe'] ?? 0) : 0, $vatOnItemCard);
	}
}

if (!function_exists('shopVatOnItemCard')) {
	/**
	 * Whether the item card shows and takes prices including VAT (settings var_grp items, var_name vatOnItemCard).
	 *
	 * @return bool
	 */
	function shopVatOnItemCard() {
		global $db;
		static $cache = array();
		if (!array_key_exists((string)$db, $cache)) {
			$r = shopOfferFetchRow("select var_value from settings where var_name = 'vatOnItemCard' and var_grp = 'items'");
			$cache[(string)$db] = ($r && $r['var_value']);
		}
		return $cache[(string)$db];
	}
}

if (!function_exists('shopOfferVatRate')) {
	/**
	 * An item group's VAT percentage by the chain find_pris() adds VAT with: grupper VG box4 (sales account) to
	 * kontoplan.moms to grupper <S|K..>M box2; a VAT-free group (box7) or a missing link gives 0.
	 *
	 * @param int|string|null $gruppe varer.gruppe
	 * @return float
	 */
	function shopOfferVatRate($gruppe) {
		global $db, $regnaar;
		static $cache = array();
		$gruppe = (int)$gruppe;
		$year = (int)$regnaar;
		$key = $db . ':' . $year . ':' . $gruppe;
		if (isset($cache[$key])) return $cache[$key];
		$cache[$key] = 0.0;
		$r = shopOfferFetchRow("select box4,box7 from grupper where art = 'VG' and kodenr = '$gruppe' and fiscal_year = '$year'");
		if ($r && !$r['box7']) {
			$kontonr = (int)$r['box4'];
			$r = shopOfferFetchRow("select moms from kontoplan where regnskabsaar = '$year' and kontonr = '$kontonr'");
			if ($r && $r['moms']) {
				$art = db_escape_string(substr($r['moms'], 0, 1) . 'M');
				$kodenr = (int)substr($r['moms'], 1);
				$r = shopOfferFetchRow("select box2 from grupper where art = '$art' and kodenr = '$kodenr' and fiscal_year = '$year'");
				if ($r) $cache[$key] = (float)str_replace(',', '.', (string)$r['box2']);
			}
		}
		return $cache[$key];
	}
}

if (!function_exists('shopOfferFetchRow')) {
	/**
	 * One row of a lookup: inside the offer reconciler's tick through its time-bounded query (shopOfferBoundedQuery()),
	 * elsewhere through db_select() as always.
	 *
	 * @param string $sql
	 * @return array|false
	 */
	function shopOfferFetchRow($sql) {
		if (!empty($GLOBALS['shopOfferBoundedUntil']) && function_exists('shopOfferBoundedQuery')) {
			return db_fetch_array(shopOfferBoundedQuery($sql));
		}
		return db_fetch_array(db_select($sql, __FILE__ . " linje " . __LINE__));
	}
}

if (!function_exists('shopOfferColumns')) {
	/**
	 * The varer columns shopPricesForItem() needs beside the ones a caller already selects.
	 *
	 * @return string
	 */
	function shopOfferColumns() {
		return 'specialtype, special_price, special_from_date, special_to_date, special_from_time, special_to_time, gruppe';
	}
}
?>
