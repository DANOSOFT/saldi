<?php
// --- includes/orderFuncIncludes/dateRangeSearch.php ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260925 LOE SST-806 The date field in the order list stopped accepting the shorthand it had always
//                  accepted - a bare six-digit date (210926) and a colon-separated interval
//                  (010926:300926) - because the search builder introduced for the datepicker only
//                  understood the dd-mm-yyyy the picker writes, and required the separator to be
//                  surrounded by spaces. Anything else fell through to "1=1", so the list came back
//                  unfiltered instead of filtered. It also reversed the parts of an ISO date, which
//                  made 2026-02-03 search for the third of March.
//
//                  Living here rather than inline in debitor/ordreliste.php so the parsing can be
//                  tested without a session and a database (same shape as the other small helpers in
//                  includes/orderFuncIncludes/).

if (!function_exists('ordrelisteParseDateTerm')) {
	/**
	 * Parse one endpoint of a date search into Y-m-d, or '' when it is not a date.
	 *
	 * Accepts the shapes the field has been given over the years, whichever separator and whichever
	 * order the year comes in:
	 *   ddmmyy (210926), ddmmyyyy, dd-mm-yyyy, dd.mm.yyyy, dd/mm/yyyy, d-m-yyyy and yyyy-mm-dd.
	 * A two-digit year follows usdate(): below 80 is 20xx, otherwise 19xx. The date is validated with
	 * checkdate(), so an impossible day is rejected rather than clamped (usdate() walks 31 February
	 * back to the 28th, which is not what a filter should do).
	 *
	 * @param string $value One date as typed, or one endpoint of an interval.
	 * @return string Y-m-d, or '' when the value is not a date.
	 */
	function ordrelisteParseDateTerm($value)
	{
		$value = trim((string) $value);
		if ($value === '') {
			return '';
		}

		// Every separator the field has seen, including the space some copies paste with a date.
		$normalised = str_replace(array('.', '/', ' '), '-', $value);
		$parts = array();

		if (strpos($normalised, '-') !== false) {
			$parts = explode('-', $normalised);
		} elseif (preg_match('/^[0-9]{6}$/', $normalised)) {
			$parts = array(substr($normalised, 0, 2), substr($normalised, 2, 2), substr($normalised, 4, 2));
		} elseif (preg_match('/^[0-9]{8}$/', $normalised)) {
			$parts = array(substr($normalised, 0, 2), substr($normalised, 2, 2), substr($normalised, 4, 4));
		} else {
			return '';
		}

		if (count($parts) !== 3) {
			return '';
		}
		foreach ($parts as $part) {
			if (!preg_match('/^[0-9]{1,4}$/', $part)) {
				return '';
			}
		}

		list($first, $second, $third) = $parts;
		if (strlen($first) === 4) {
			// yyyy-mm-dd
			$year = (int) $first;
			$month = (int) $second;
			$day = (int) $third;
		} else {
			// dd-mm-yyyy and dd-mm-yy
			$day = (int) $first;
			$month = (int) $second;
			$year = (int) $third;
			if (strlen($third) <= 2) {
				$year = ($year < 80) ? 2000 + $year : 1900 + $year;
			}
		}

		if (!checkdate($month, $day, $year)) {
			return '';
		}

		return sprintf('%04d-%02d-%02d', $year, $month, $day);
	}
}

if (!function_exists('generateDateRangeSearch')) {
	/**
	 * Build the SQL condition for a date or date-range search in the order list.
	 *
	 * One date means that whole day, an interval means both days inclusive; the bounds carry a time
	 * because the same helper is used for date columns and for timestamp columns. Only a value that
	 * came back from ordrelisteParseDateTerm() is ever put into the SQL, so nothing a user typed
	 * reaches the statement; a value that does not parse leaves the list unfiltered.
	 *
	 * @param array  $column Column definition: sqlOverride or field names the column to filter.
	 * @param string $term   The search term: '210926', '21-09-2026', '010926:300926',
	 *                       '01-09-2026 : 30-09-2026' or '01-09-2026 - 30-09-2026'.
	 * @return string SQL condition.
	 */
	function generateDateRangeSearch($column, $term)
	{
		$field = (isset($column['sqlOverride']) && $column['sqlOverride']) ? $column['sqlOverride'] : $column['field'];
		$term = trim((string) $term, " \t\n\r\0\x0B'");

		if ($term === '') {
			return "1=1";
		}

		// The grid normalises ';' to ':' before some searches and not before this one; the picker
		// writes ' : ', and the shorthand the reporter types is ':' with nothing around it. A hyphen
		// only separates an interval when it is spaced, otherwise it belongs to the date itself.
		$normalised = str_replace(array(';', ' - '), array(':', ':'), $term);
		$bounds = (strpos($normalised, ':') !== false) ? explode(':', $normalised, 2) : array($normalised);

		if (count($bounds) === 2) {
			$start = ordrelisteParseDateTerm($bounds[0]);
			$end = ordrelisteParseDateTerm($bounds[1]);
			if ($start === '' || $end === '') {
				return "1=1";
			}
			return "({$field} >= '$start 00:00:00' AND {$field} <= '$end 23:59:59')";
		}

		$single = ordrelisteParseDateTerm($bounds[0]);
		if ($single === '') {
			return "1=1";
		}
		return "({$field} >= '$single 00:00:00' AND {$field} <= '$single 23:59:59')";
	}
}
