<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/rentalClosedDays.php --- ver 5.1.1 --- 2026-11-11 ---
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
// 20261111 CL/MMR SST-999 Created. The end of a booking from the public booking page or mysale, moved one day on for every closed day in the period.
//                 The bookings used strtotime() on rentalclosed.day, which is a unix time, so every closed day read as false and was never counted.

if (!function_exists('rental_extend_for_closed_days')) {
	/**
	 * The end of a booking moved one day on for every closed day from the start to the end, both included.
	 *
	 * $closedDays are rentalclosed.day values: the unix time of the day at local midnight, as rental.php
	 * insertClosedDay stores them. Each is compared as its calendar day, so a value stored at another hour
	 * still counts, and two values on the same day count once. The days are taken in date order and
	 * compared with the end as moved so far, so a closed day the move reaches is counted too.
	 *
	 * @param int|false $from First day of the booking, unix time at local midnight (strtotime of start_date).
	 * @param int|false $to Last day of the booking, unix time at local midnight (strtotime of end_date).
	 * @param array<int, int|string|null> $closedDays rentalclosed.day values; values that are not numbers are skipped.
	 * @return int|false The last day moved one day on per closed day, as a unix time at local midnight.
	 *                   $to unchanged when $from or $to is not an int (a date that could not be read).
	 */
	function rental_extend_for_closed_days($from, $to, array $closedDays) {
		if (!is_int($from) || !is_int($to)) return $to;
		$days = array();
		foreach ($closedDays as $day) {
			if (!is_numeric($day)) continue;
			$days[date('Y-m-d', (int)$day)] = true;
		}
		ksort($days);
		foreach (array_keys($days) as $ymd) {
			$closedDate = strtotime($ymd);
			if ($closedDate >= $from && $closedDate <= $to) {
				$to = strtotime('+1 day', $to);
			}
		}
		return $to;
	}
}

if (!function_exists('rental_closed_days')) {
	/**
	 * Every closed day of the connected account, in date order.
	 *
	 * @return list<string> rentalclosed.day values as the database returns them: the unix time of the day
	 *                      at local midnight, as a string. Empty when no day is closed.
	 */
	function rental_closed_days() {
		$days = array();
		$query = db_select("SELECT day FROM rentalclosed ORDER BY day", __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$days[] = $row["day"];
		}
		return $days;
	}
}
