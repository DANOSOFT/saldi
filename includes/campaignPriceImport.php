<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/campaignPriceImport.php --- ver 5.1.1 --- 2026-11-17 ---
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
// 20261117 CL/LH Created for the item import (systemdata/importer_varer.php): campaign (offer) prices and periods from an Excel/CSV sheet.
//                Parses Danish and dot decimals, Danish and ISO dates with an optional time and times like 8.00, without touching the database.

/**
 * The import columns that only set an item's offer price and offer period.
 *
 * @return string[]
 */
function campaignImportFields() {
	return array('special_price', 'special_price_incl_moms', 'special_from_date', 'special_to_date', 'special_from_time', 'special_to_time');
}

/**
 * True when the mapped columns are an item number plus campaign columns only.
 * Such an import updates offer prices on existing items and nothing else.
 *
 * @param array<int, string|null> $fieldNames The column mapping, '' or '-' for an unused column.
 * @return bool
 */
function campaignImportOnly($fieldNames) {
	$hasItemNo = $hasCampaign = false;
	foreach ((array)$fieldNames as $name) {
		$name = strtolower(trim((string)$name));
		if ($name === '' || $name === '-') continue;
		if ($name === 'varenr') {
			$hasItemNo = true;
		} elseif (in_array($name, campaignImportFields(), true)) {
			$hasCampaign = true;
		} else {
			return false;
		}
	}
	return $hasItemNo && $hasCampaign;
}

/**
 * Reads a price from a spreadsheet cell.
 *
 * Accepted: "149,95", "1.299,00", "1.299" (Danish thousands), "149.95", "1,299.00", "1 299,00", "149,95 kr.", "DKK 149,95".
 * A dot followed by exactly three digits is a thousands separator, as in a Danish sheet; "0.995" stays a decimal.
 *
 * @param string|null $text The cell text.
 * @return float|null The amount, or null when the cell is empty, negative or not a number.
 */
function campaignImportAmount($text) {
	$text = str_replace(array("\xC2\xA0", ' '), '', trim((string)$text));
	$text = preg_replace('/^(dkk|kr\.?)/i', '', $text);
	$text = preg_replace('/(dkk|kr\.?|,-)$/i', '', $text);
	if ($text === '') return null;
	if (preg_match('/^(\d+|[1-9]\d{0,2}(\.\d{3})+)(,\d+)?$/', $text)) { // Danish: 149,95 1.299 1.299,00
		$number = str_replace(array('.', ','), array('', '.'), $text);
	} elseif (preg_match('/^\d+\.\d+$/', $text)) { // dot decimal: 149.95
		$number = $text;
	} elseif (preg_match('/^[1-9]\d{0,2}(,\d{3})+(\.\d+)?$/', $text)) { // English thousands: 1,299.00
		$number = str_replace(',', '', $text);
	} else {
		return null;
	}
	$amount = (float)$number;
	if ($amount >= 1000000000) return null; // beyond what varer.special_price (15,3) holds
	return $amount;
}

/**
 * Converts a price including VAT to the price excluding VAT that varer.special_price holds.
 *
 * @param float $amountInclVat
 * @param float $vatRate The item's VAT percentage, e.g. 25.
 * @return float Rounded to the three decimals of varer.special_price.
 */
function campaignImportExVat($amountInclVat, $vatRate) {
	return round($amountInclVat * 100 / (100 + $vatRate), 3);
}

/**
 * Reads a time of day from a spreadsheet cell: 8:00, 08:00, 8.00, 08:00:00, "kl. 8.00".
 *
 * An end time on minute 59 gets second 59, as on the item card (lager/varekort.php), and an end time 24:00 means 23:59:59.
 *
 * @param string|null $text The cell text.
 * @param bool $isEnd True for the end of the offer period.
 * @return string|null The time as HH:MM:SS, or null when the cell is empty or not a time.
 */
function campaignImportTime($text, $isEnd) {
	$text = trim(preg_replace('/^kl\.?/i', '', trim((string)$text)));
	if (!preg_match('/^(\d{1,2})[:.](\d{2})(?:[:.](\d{2}))?$/', $text, $m)) return null;
	$hour = (int)$m[1];
	$minute = (int)$m[2];
	$second = isset($m[3]) ? (int)$m[3] : null;
	if ($isEnd && $hour == 24 && $minute == 0 && !$second) return '23:59:59';
	if ($hour > 23 || $minute > 59 || $second > 59) return null;
	if ($second === null) $second = ($isEnd && $minute == 59) ? 59 : 0;
	return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

/**
 * Reads a date, with an optional time, from a spreadsheet cell.
 *
 * Dates: 27-11-2026, 27.11.2026, 27/11/2026, 1-12-2026, 2026-11-27. A time may follow after a space, "kl." or ISO's T:
 * "27-11-2026 08:00", "27.11.2026 kl. 8.00", "2026-11-27T08:00:00". Two-digit years are not accepted.
 *
 * @param string|null $text The cell text.
 * @param bool $isEnd True for the end of the offer period (see campaignImportTime()).
 * @return array{date: string, time: string|null}|null The date as YYYY-MM-DD and the time as HH:MM:SS or null when the cell has none;
 *                                                     null when the cell is empty or not a valid date and time.
 */
function campaignImportDateTime($text, $isEnd) {
	$text = trim((string)$text);
	if (!preg_match('/^(\d{1,4}[-.\/]\d{1,2}[-.\/]\d{1,4})(?:(?:\s+|T)(.+))?$/i', $text, $m)) return null;
	if (preg_match('/^(\d{1,2})([-.\/])(\d{1,2})\2(\d{4})$/', $m[1], $d)) {
		list(, $day, , $month, $year) = $d;
	} elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $m[1], $d)) {
		list(, $year, $month, $day) = $d;
	} else {
		return null;
	}
	if ($year < 2000 || $year > 2099 || !checkdate((int)$month, (int)$day, (int)$year)) return null;
	$time = null;
	if (isset($m[2]) && trim($m[2]) !== '') {
		$time = campaignImportTime($m[2], $isEnd);
		if ($time === null) return null;
	}
	return array('date' => sprintf('%04d-%02d-%02d', $year, $month, $day), 'time' => $time);
}

/**
 * Turns one line's offer period cells into the varer columns to write.
 *
 * An empty cell leaves its column as it is. A date without a time starts at 00:00:00 or ends at 23:59:59,
 * so the offer lasts the whole last day: find_pris() (includes/ordrefunc.php) ends it at the stored
 * special_to_time on that day, and 00:00:00 would end it as the day begins.
 * A time column wins over a time written in the date cell.
 *
 * @param array<string, string|null> $cells Raw cells keyed by import column (special_from_date, special_to_date,
 *                                          special_from_time, special_to_time); a column that is not mapped is absent.
 * @return array{
 *   values: array<string, string>,  The columns to set: special_from_date/special_to_date as YYYY-MM-DD,
 *                                   special_from_time/special_to_time as HH:MM:SS.
 *   error: string|null,             The import column whose cell is invalid, or special_to_date when the period
 *                                   ends before it starts; null when the line can be imported.
 * }
 */
function campaignImportWindow($cells) {
	$values = array();
	foreach (array('from' => false, 'to' => true) as $side => $isEnd) {
		$dateField = "special_{$side}_date";
		$timeField = "special_{$side}_time";
		$dateCell = trim((string)($cells[$dateField] ?? ''));
		$timeCell = trim((string)($cells[$timeField] ?? ''));
		$time = null;
		if ($timeCell !== '') {
			$time = campaignImportTime($timeCell, $isEnd);
			if ($time === null) return array('values' => array(), 'error' => $timeField);
		}
		if ($dateCell !== '') {
			$dateTime = campaignImportDateTime($dateCell, $isEnd);
			if ($dateTime === null) return array('values' => array(), 'error' => $dateField);
			$values[$dateField] = $dateTime['date'];
			if ($time === null) $time = $dateTime['time'] ?? ($isEnd ? '23:59:59' : '00:00:00');
		}
		if ($time !== null) $values[$timeField] = $time;
	}
	if (isset($values['special_from_date'], $values['special_to_date'])) {
		$from = $values['special_from_date'] . ' ' . $values['special_from_time'];
		$to = $values['special_to_date'] . ' ' . $values['special_to_time'];
		if ($from > $to) return array('values' => array(), 'error' => 'special_to_date');
	}
	return array('values' => $values, 'error' => null);
}

/**
 * Reads one import line's campaign cells: the offer price and the offer period.
 *
 * An empty price cell leaves special_price as it is; 0 ends the offer. When both price columns are filled,
 * special_price_incl_moms is used.
 *
 * @param array<string, string|null> $cells Raw cells keyed by import column (see campaignImportFields()); a column that is not mapped is absent.
 * @return array{
 *   values: array<string, string>,  The period columns to set, as campaignImportWindow() returns them.
 *   price: float|null,              The offer price as written in the sheet, or null when no price cell is filled.
 *   priceInclVat: bool,             True when price includes VAT and must go through campaignImportExVat() before it is stored.
 *   error: string|null,             The import column whose cell is invalid; null when the line can be imported.
 * }
 */
function campaignImportLine($cells) {
	$line = array('values' => array(), 'price' => null, 'priceInclVat' => false, 'error' => null);
	foreach (array('special_price_incl_moms' => true, 'special_price' => false) as $field => $inclVat) {
		$cell = trim((string)($cells[$field] ?? ''));
		if ($cell === '') continue;
		$amount = campaignImportAmount($cell);
		if ($amount === null) {
			$line['error'] = $field;
			return $line;
		}
		if ($line['price'] === null) {
			$line['price'] = $amount;
			$line['priceInclVat'] = $inclVat;
		}
	}
	$window = campaignImportWindow($cells);
	$line['values'] = $window['values'];
	$line['error'] = $window['error'];
	return $line;
}
?>
