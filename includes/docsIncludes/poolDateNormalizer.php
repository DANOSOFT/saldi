<?php
// --- includes/docsIncludes/poolDateNormalizer.php ---
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
// 20260909 CDX/MJ SST-775 Moved normalizeDateFormat() here out of extractInvoiceHandler.php,
//                  which cannot be included without connecting to a database and exiting, so the
//                  date handling had no test coverage. Same shape as poolAmountNormalizer.php.
//                  Three extraction defects fixed at the same time - see normalizeDateFormat().
// 20261124 CL/LH A receipt printing "21 11 26" (dd mm yy with spaces) became 2021-11-26 in the pool.
//                A space-separated triple and a time after the date are read like the dotted forms.
//                poolExtractedDate() takes the day-first reading of an AI-read date that is more than a year before the upload or in the future, when that reading falls in between.

if (!function_exists('poolDateFromParts')) {
	/**
	 * Assemble a Y-m-d date from its parts, but only when the parts describe a real day.
	 *
	 * Returns '' rather than the raw string when the parts are not a real date. Keeping
	 * the raw string looks harmless because pool_files.file_date is a varchar, but
	 * docPool.php then formats it for the cash journal with
	 * date("d-m-Y", strtotime($newDate)) - and strtotime() rolls an impossible date over
	 * instead of rejecting it, so "31.02.2025" posts a journal line dated 03-03-2025 just
	 * as the old "2025-02-31" did. An empty value sets no date from the pool at all, which
	 * leaves the field to be filled in rather than silently posting the wrong day.
	 *
	 * @param string $year  Four-digit year.
	 * @param string $month Month, zero-padded.
	 * @param string $day   Day, zero-padded.
	 * @return string Y-m-d, or '' when the parts are not a real date.
	 */
	function poolDateFromParts($year, $month, $day)
	{
		if (!checkdate((int) $month, (int) $day, (int) $year)) {
			return '';
		}
		return $year . '-' . $month . '-' . $day;
	}
}

if (!function_exists('poolDateExpandYear')) {
	/**
	 * Expand a two-digit year to four digits, leaving four-digit years untouched.
	 *
	 * @param string $year
	 * @return string
	 */
	function poolDateExpandYear($year)
	{
		if (strlen($year) != 2) {
			return $year;
		}
		return ($year > 50 ? '19' : '20') . $year;
	}
}

if (!function_exists('normalizeDateFormat')) {
	/**
	 * Normalize date format from various formats to Y-m-d.
	 *
	 * Handles Danish month names like "januar", "februar", etc., and formats like
	 * "17.oktober.2025", "17-10-2025", "2025-10-17".
	 *
	 * Day-first is assumed throughout, which is the Danish convention. Two-digit years
	 * are therefore read as dd-mm-yy; "25-10-17" is genuinely ambiguous and resolves to
	 * 2017-10-25 rather than 2025-10-17.
	 *
	 * SST-775 fixed three defects here, all of which produced a wrong or impossible
	 * bilagsdato that was then stored silently:
	 *   - "17-10-25" returned 2017-10-25. The dd.mm.yyyy branch demanded a four-digit
	 *     year, so two-digit years fell through to strtotime(), which reads a
	 *     dash-separated triple as yy-mm-dd and swapped day with year.
	 *   - "10/17/2025" returned "2025-17-10", i.e. month 17. A month-first document
	 *     (US-style, as issued by some foreign suppliers) was read day-first.
	 *   - "31.02.2025" returned "2025-02-31". Nothing validated the result, and returning the
	 *     raw string instead was not enough either: docPool.php feeds file_date through
	 *     strtotime(), which rolls "31.02.2025" over to 3 March exactly as "2025-02-31" did.
	 *     An impossible date therefore yields '' - no date rather than the wrong one.
	 *
	 * @param string|null $dateStr Raw date as extracted from the document, or typed by a user.
	 * @return string Y-m-d when the input describes a real date; '' when the input is empty
	 *   or describes an impossible date; otherwise the trimmed input unchanged, for input
	 *   that was never date-shaped to begin with (strtotime() rejects it downstream too).
	 */
	function normalizeDateFormat($dateStr)
	{
		if (empty($dateStr)) {
			return '';
		}

		// Danish month names to numbers
		$danishMonths = [
			'januar' => '01', 'jan' => '01',
			'februar' => '02', 'feb' => '02',
			'marts' => '03', 'mar' => '03',
			'april' => '04', 'apr' => '04',
			'maj' => '05',
			'juni' => '06', 'jun' => '06',
			'juli' => '07', 'jul' => '07',
			'august' => '08', 'aug' => '08',
			'september' => '09', 'sep' => '09', 'sept' => '09',
			'oktober' => '10', 'okt' => '10', 'oct' => '10',
			'november' => '11', 'nov' => '11',
			'december' => '12', 'dec' => '12'
		];

		// Clean up the date string
		$dateStr = trim($dateStr);
		$originalDate = $dateStr;

		// Convert to lowercase for matching
		$lowerDate = strtolower($dateStr);

		// Replace Danish month names with numbers
		foreach ($danishMonths as $monthName => $monthNum) {
			if (stripos($lowerDate, $monthName) !== false) {
				// Found a Danish month name, try to parse
				// Pattern: day.monthname.year or day monthname year
				if (preg_match('/(\d{1,2})[.\s\-]+' . preg_quote($monthName, '/') . '[.\s\-]+(\d{4}|\d{2})/i', $dateStr, $matches)) {
					$day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
					$year = poolDateExpandYear($matches[2]);
					return poolDateFromParts($year, $monthNum, $day);
				}
			}
		}

		// A time after the date ("21 11 26 13:02" on a till receipt) says nothing about the day.
		$dateStr = preg_replace('/\s+\d{1,2}[:.]\d{2}(?:[:.]\d{2})?$/', '', $dateStr);
		// Between the parts: . - or /, or only spaces, as till receipts print "21 11 26".
		$separator = '(?:\s*[.\-\/]\s*|\s+)';

		// Format: dd.mm.yyyy or dd-mm-yyyy or dd/mm/yyyy or dd mm yyyy, and the same with a
		// two-digit year. A leading four-digit group cannot match here, so yyyy-mm-dd still
		// falls to the branch below.
		if (preg_match('/^(\d{1,2})' . $separator . '(\d{1,2})' . $separator . '(\d{4}|\d{2})$/', $dateStr, $matches)) {
			$day = (int) $matches[1];
			$month = (int) $matches[2];
			$year = poolDateExpandYear($matches[3]);
			// When the second group cannot be a month but the first can, the document is
			// month-first. Swapping is the only reading that yields a real date, so this is a
			// correction rather than a guess; anything else stays as it was and gets validated.
			if ($month > 12 && $day <= 12) {
				$tmp = $day;
				$day = $month;
				$month = $tmp;
			}
			return poolDateFromParts(
				$year,
				str_pad((string) $month, 2, '0', STR_PAD_LEFT),
				str_pad((string) $day, 2, '0', STR_PAD_LEFT)
			);
		}

		// Format: yyyy-mm-dd (already correct)
		if (preg_match('/^(\d{4})' . $separator . '(\d{1,2})' . $separator . '(\d{1,2})$/', $dateStr, $matches)) {
			$year = $matches[1];
			$month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
			$day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
			return poolDateFromParts($year, $month, $day);
		}

		// Try PHP's strtotime as fallback
		$timestamp = strtotime($originalDate);
		if ($timestamp !== false && $timestamp > 0) {
			return date('Y-m-d', $timestamp);
		}

		// Return original if nothing worked
		return $originalDate;
	}
}

if (!function_exists('poolExtractedDate')) {
	/**
	 * Normalize a date read from a document by the AI scan, and correct a year-first misreading.
	 *
	 * A till receipt printing "21 11 26" (21 November 2026) comes back from the scan as
	 * 2021-11-26 as often as not: the model read the triple year-first. Nothing in that ISO
	 * date says it was misread, but a bilag is seldom more than a year old when it is
	 * uploaded, and never from the future. When the date read is either, and reading its
	 * day as the two-digit year (and its year as the day) gives a date in that window,
	 * the scan misread the triple, and that reading is the one returned.
	 *
	 * Only for dates the AI read: a date the user typed is never second-guessed.
	 *
	 * @param string|null $dateStr Raw or normalized date from the extraction.
	 * @param string|null $referenceDate Y-m-d the document arrived (upload or file date); today when null.
	 * @return string As normalizeDateFormat(), with a misread year corrected.
	 */
	function poolExtractedDate($dateStr, $referenceDate = null)
	{
		$date = normalizeDateFormat($dateStr);
		if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $parts)) {
			return $date;
		}
		$reference = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($referenceDate ?: date('Y-m-d')));
		if ($reference === false) {
			return $date;
		}
		$earliest = $reference->modify('-1 year')->format('Y-m-d');
		// Some invoices are dated ahead (a month's rent, a subscription); a month is not "the future".
		$latest = $reference->modify('+1 month')->format('Y-m-d');
		if ($date >= $earliest && $date <= $latest) {
			return $date;
		}
		if (substr($parts[1], 0, 2) !== '20') {
			return $date;
		}
		$dayFirst = poolDateFromParts(poolDateExpandYear($parts[3]), $parts[2], substr($parts[1], 2));
		if ($dayFirst !== '' && $dayFirst >= $earliest && $dayFirst <= $latest) {
			return $dayFirst;
		}
		return $date;
	}
}
