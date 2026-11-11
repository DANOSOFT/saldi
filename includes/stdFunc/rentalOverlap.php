<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/rentalOverlap.php --- ver 5.1.1 --- 2026-11-11 ---
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
// 20261111 CL/MMR SST-1000 Created. A booking from the public booking page or mysale is only stored when the unit is free.
//                 createBooking stored every booking it got, so a unit could be booked twice when two customers booked at once, when the page had been open while someone else booked, or when the page picked a busy unit.

if (!function_exists('rental_booking_day')) {
	/**
	 * A booking day as the booking page sends it ('Y-m-d', a real calendar date) as a unix time at local midnight.
	 *
	 * Only that form is accepted: a time of day, a time zone or a relative date ("+1 week") would give a period
	 * that does not start and end on whole days, and the check against other bookings works on whole days.
	 *
	 * @param mixed $value start_date or end_date from the request.
	 * @return int|false Unix time of the day at local midnight; false when $value is not a 'Y-m-d' calendar date.
	 */
	function rental_booking_day($value) {
		if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})\z/', $value, $m)) return false;
		if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return false;
		return strtotime($value);
	}
}

if (!function_exists('rental_period_blocks')) {
	/**
	 * Whether an existing booking or reservation from $rowFrom to $rowTo blocks a new booking from $from to $to
	 * of the same unit. All four are unix times, compared as the local calendar days they fall on, so an
	 * existing period stored at another hour than midnight still counts on its first and last day. It is the
	 * rule the booking page uses (remoteBooking/index.js onDayCreate): the periods overlap, both first and
	 * last day included.
	 * A booking that is still waiting for payment has an expiry_time; once that time has passed, the booking
	 * is abandoned (rental.php cleanUpExpiredBookings deletes it) and does not block.
	 *
	 * @param int|string|null $rowFrom First day of the existing booking or reservation (rt_from / rr_from).
	 * @param int|string|null $rowTo Last day of the existing booking or reservation (rt_to / rr_to).
	 * @param int $from First day of the new booking.
	 * @param int $to Last day of the new booking.
	 * @param string|null $expiresAt expiry_time of an unpaid booking ('Y-m-d H:i:s'), null when paid or a reservation.
	 * @param int|null $now Unix time to compare the expiry with; null is time().
	 * @return bool True when the periods overlap and the existing one has not expired.
	 *              False for an existing period without a first or last day.
	 */
	function rental_period_blocks($rowFrom, $rowTo, $from, $to, $expiresAt = null, $now = null) {
		if (!is_numeric($rowFrom) || !is_numeric($rowTo)) return false;
		if ($expiresAt !== null && $expiresAt !== '') {
			$expires = strtotime((string)$expiresAt);
			if ($expires !== false && $expires <= ($now === null ? time() : $now)) return false;
		}
		$day = static function ($t) { return date('Y-m-d', (int)$t); };
		return $day($rowFrom) <= $day($to) && $day($rowTo) >= $day($from);
	}
}

if (!function_exists('rental_search_bounds')) {
	/**
	 * The unix times the database search for other periods is limited to: from local midnight the day before the
	 * first day to local midnight two days after the last day. That covers every hour of the first and last day,
	 * also on the 23- and 25-hour days when summer time starts or ends; rental_period_blocks() then decides by day.
	 *
	 * @param int $from First day of the new booking, unix time.
	 * @param int $to Last day of the new booking, unix time.
	 * @return array{0: int, 1: int} [search from, search to], unix times.
	 */
	function rental_search_bounds($from, $to) {
		$fromDay = strtotime(date('Y-m-d', (int)$from));
		$toDay = strtotime(date('Y-m-d', (int)$to));
		return array((int)strtotime('-1 day', $fromDay), (int)strtotime('+2 days', $toDay));
	}
}

if (!function_exists('rental_item_busy')) {
	/**
	 * Whether a unit has a booking (rentalperiod) or a reservation (rentalreserved) that blocks a new booking.
	 *
	 * @param int $itemId rentalitems.id of the unit.
	 * @param int $from First day of the new booking, unix time.
	 * @param int $to Last day of the new booking, unix time.
	 * @return bool True when at least one booking or reservation blocks the period (see rental_period_blocks()).
	 */
	function rental_item_busy($itemId, $from, $to) {
		$itemId = (int)$itemId;
		$from = (int)$from;
		$to = (int)$to;
		list($wideFrom, $wideTo) = rental_search_bounds($from, $to);
		$query = db_select("SELECT rt_from, rt_to, expiry_time FROM rentalperiod WHERE item_id = $itemId AND rt_from <= $wideTo AND rt_to >= $wideFrom", __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (rental_period_blocks($row["rt_from"], $row["rt_to"], $from, $to, $row["expiry_time"])) return true;
		}
		$query = db_select("SELECT rr_from, rr_to FROM rentalreserved WHERE item_id = $itemId AND rr_from <= $wideTo AND rr_to >= $wideFrom", __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (rental_period_blocks($row["rr_from"], $row["rr_to"], $from, $to)) return true;
		}
		return false;
	}
}

if (!function_exists('rental_store_booking')) {
	/**
	 * Store a booking from the public booking page or mysale, waiting for payment until $expiryTime
	 * ('Y-m-d H:i:s'). Returns the new rentalperiod id, or 0 when the unit is not free in the period.
	 * The unit's rentalitems row is locked while it is checked and stored, so two bookings of the same
	 * unit at the same moment cannot both pass the check.
	 *
	 * @param int $itemId rentalitems.id of the unit.
	 * @param int $custId adresser.id of the customer.
	 * @param int $from First day, unix time at local midnight.
	 * @param int $to Last day, unix time at local midnight.
	 * @param string $expiryTime When the unpaid booking expires, 'Y-m-d H:i:s'.
	 * @return int The new rentalperiod.id; 0 when the unit is not free in the period or the row was not stored.
	 */
	function rental_store_booking($itemId, $custId, $from, $to, $expiryTime) {
		$itemId = (int)$itemId;
		$custId = (int)$custId;
		$from = (int)$from;
		$to = (int)$to;
		$expiry = db_escape_string((string)$expiryTime);
		transaktion('begin');
		db_select("SELECT id FROM rentalitems WHERE id = $itemId FOR UPDATE", __FILE__ . " linje " . __LINE__);
		if (rental_item_busy($itemId, $from, $to)) {
			transaktion('rollback');
			return 0;
		}
		db_modify("INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id, expiry_time) VALUES ($itemId, $custId, $from, $to, 0, TO_TIMESTAMP('$expiry', 'YYYY-MM-DD HH24:MI:SS'))", __FILE__ . " linje " . __LINE__);
		$query = db_select("SELECT id FROM rentalperiod WHERE item_id = $itemId AND cust_id = $custId AND rt_from = $from AND rt_to = $to ORDER BY id DESC LIMIT 1", __FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		transaktion('commit');
		return $row ? (int)$row["id"] : 0;
	}
}
