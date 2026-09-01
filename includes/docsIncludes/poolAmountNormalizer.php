<?php
// --- includes/docsIncludes/poolAmountNormalizer.php ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260721 CL/SZ Added normalizePoolAmount() for the Bilagsmatch scoring engine, shared
//                  between extractInvoiceHandler.php's save path and the pool_files.norm_amount
//                  backfill in opdat_4.3.php.

if (!function_exists('normalizePoolAmount')) {
	/**
	 * Parses a free-form amount string (as extracted from an invoice PDF/image) into a
	 * plain numeric value, handling the Danish/EU and US thousands/decimal conventions:
	 *   "1.234,56" -> 1234.56   (dot = thousands, comma = decimal)
	 *   "1,234.56" -> 1234.56   (comma = thousands, dot = decimal)
	 *   "1234.56"  -> 1234.56
	 *   "1.234"    -> 1234      (single separator + exactly 3 trailing digits = thousands
	 *                            grouping, not a fractional amount - Danish amounts are
	 *                            never written with 3 decimals, so this case is unambiguous
	 *                            enough in practice even though it's not logically provable)
	 *   "1 234,56" -> 1234.56   (space is always a thousands separator)
	 *
	 * @param string|null $raw
	 * @return float|null Null when $raw is empty or not parseable as a number.
	 */
	function normalizePoolAmount($raw) {
		if ($raw === null) return null;
		$raw = trim((string) $raw);
		if ($raw === '') return null;

		$negative = (strpos($raw, '-') !== false);

		// Drop currency symbols/letters/parentheses etc, keep only digits and separators.
		$clean = preg_replace('/[^0-9,.\s]/', '', $raw);
		$clean = str_replace(' ', '', $clean); // spaces are always thousands separators
		if ($clean === '') return null;

		$lastDot   = strrpos($clean, '.');
		$lastComma = strrpos($clean, ',');
		$decimalPos = false;

		if ($lastDot !== false && $lastComma !== false) {
			// Both kinds present: whichever occurs later in the string is the decimal point.
			$decimalPos = max($lastDot, $lastComma);
		} elseif ($lastComma !== false && substr_count($clean, ',') === 1) {
			// Single comma: decimal separator, unless followed by exactly 3 digits.
			if ((strlen($clean) - $lastComma - 1) !== 3) $decimalPos = $lastComma;
		} elseif ($lastDot !== false && substr_count($clean, '.') === 1) {
			if ((strlen($clean) - $lastDot - 1) !== 3) $decimalPos = $lastDot;
		}
		// Repeated occurrences of a single separator (e.g. "1.234.567") are always
		// thousands grouping, so $decimalPos stays false and everything gets stripped below.

		if ($decimalPos !== false) {
			$intPart  = preg_replace('/[.,]/', '', substr($clean, 0, $decimalPos));
			$fracPart = preg_replace('/[.,]/', '', substr($clean, $decimalPos + 1));
			$clean = $intPart . '.' . $fracPart;
		} else {
			$clean = preg_replace('/[.,]/', '', $clean);
		}

		if ($clean === '' || !is_numeric($clean)) return null;
		$value = (float) $clean;
		if ($negative) $value = -abs($value);
		return round($value, 3);
	}
}

if (!function_exists('normalizePoolCurrency')) {
	/**
	 * Maps a free-form currency string (as extracted from an invoice PDF/image, or typed
	 * by a user) to an ISO 4217 code. fetchbilagsmatch.php's currency hard gate compares
	 * pf.currency to the journal line's currency with a plain UPPER(TRIM(...)) - that
	 * catches whitespace/case but not aliases like "kr" or "kr." for DKK, so an invoice
	 * extracted with a non-ISO currency string would silently never match anything,
	 * regardless of how well amount/date/text otherwise line up.
	 *
	 * @param string|null $raw
	 * @return string|null Null when $raw is empty. Falls back to the trimmed/uppercased
	 *   input unchanged when it isn't a recognized alias (better than discarding it).
	 */
	function normalizePoolCurrency($raw) {
		if ($raw === null) return null;
		$raw = trim((string) $raw);
		if ($raw === '') return null;

		$symbolAliases = ['€' => 'EUR', '$' => 'USD', '£' => 'GBP'];
		if (isset($symbolAliases[$raw])) return $symbolAliases[$raw];

		$upper = rtrim(strtoupper($raw), '.');
		$wordAliases = [
			'KR' => 'DKK', 'DKR' => 'DKK', 'KRONER' => 'DKK', 'DANSKE KRONER' => 'DKK',
			'EURO' => 'EUR',
			'DOLLAR' => 'USD', 'DOLLARS' => 'USD',
			'PUND' => 'GBP',
			'NKR' => 'NOK', 'NORSKE KRONER' => 'NOK',
			'SKR' => 'SEK', 'SVENSKE KRONER' => 'SEK',
		];
		return $wordAliases[$upper] ?? $upper;
	}
}
