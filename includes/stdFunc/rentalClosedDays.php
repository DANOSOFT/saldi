<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/rentalClosedDays.php --- ver 5.1.1 --- 2026-11-20 ---
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
// 20261119 CL/MMR SST-999 Review (PR #1097): rental_item_is_free() and rental_free_item_for_period() check a moved period against the item's bookings.
//                 The calendar only checked the period up to the chosen end, and reservations count too.
// 20261120 CL/MMR SST-999 Codex round 7: rental_insert_booking() locks the product's rentalitems rows, then checks and inserts in one transaction.
//                 Two requests at the same time can no longer both find the item free, and the booking id comes from the INSERT.
// 20261120 CL/MMR SST-999 Codex round 8: rental_insert_booking() always checks the requested item under the lock, also when the dates did not move.
//                 Only a booking whose end moved may use another free item of the product; otherwise a busy item refuses the booking.
// 20261120 CL/MMR SST-999 Codex round 9: rental_insert_booking() runs in the caller's transaction, which also makes the order and then commits.
//                 A booking whose order could not be made is no longer left behind, blocking the item.

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

if (!function_exists('rental_item_is_free')) {
	/**
	 * Whether an item has no booking (rentalperiod) and no reservation (rentalreserved) on any day from $from to $to.
	 *
	 * Both ends are included, the same rule the booking calendars use (onDayCreate in remoteBooking/index.js and
	 * mysale/remote.js): a booking that ends on the first day or starts on the last day overlaps. Every booking of
	 * the item counts, as in the calendar, also one waiting for payment.
	 *
	 * @param int|string $itemId rentalitems.id.
	 * @param int $from First day of the period, unix time.
	 * @param int $to Last day of the period, unix time.
	 * @return bool True when nothing on the item overlaps the period.
	 */
	function rental_item_is_free($itemId, $from, $to) {
		$itemId = (int)$itemId;
		$from = (int)$from;
		$to = (int)$to;
		$qtxt = "SELECT id FROM rentalperiod WHERE item_id = $itemId AND rt_from <= $to AND rt_to >= $from LIMIT 1";
		if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) return false;
		$qtxt = "SELECT id FROM rentalreserved WHERE item_id = $itemId AND rr_from <= $to AND rr_to >= $from LIMIT 1";
		if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) return false;
		return true;
	}
}

if (!function_exists('rental_free_item_for_period')) {
	/**
	 * The item to book for a period: the requested item when it is free, otherwise the free item of the same
	 * product with the lowest id. The calendar also books any item of the product that is free from the start
	 * date (availableDatesById), so the customer gets the same product.
	 *
	 * @param int|string $itemId The item the calendar chose, rentalitems.id.
	 * @param int $from First day of the period, unix time.
	 * @param int $to Last day of the period, unix time.
	 * @return int The rentalitems.id to book, or 0 when no item of the product is free for the whole period.
	 */
	function rental_free_item_for_period($itemId, $from, $to) {
		$itemId = (int)$itemId;
		if (rental_item_is_free($itemId, $from, $to)) return $itemId;
		$others = array();
		$qtxt = "SELECT id FROM rentalitems WHERE product_id = (SELECT product_id FROM rentalitems WHERE id = $itemId) ";
		$qtxt.= "AND id <> $itemId ORDER BY id";
		$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$others[] = (int)$row["id"];
		}
		foreach ($others as $otherId) {
			if (rental_item_is_free($otherId, $from, $to)) return $otherId;
		}
		return 0;
	}
}

if (!function_exists('rental_insert_booking')) {
	/**
	 * Store a booking from the public booking page or mysale (rentalperiod, order_id 0, waiting for payment), inside
	 * the caller's open transaction. The caller makes the order in the same transaction and commits, so a booking is
	 * only ever stored together with its order; a failure or an exit before the commit stores neither.
	 *
	 * It first locks the rentalitems rows of the item's product (SELECT ... FOR UPDATE, in id order), the same row lock
	 * SST-1000 (PR #1099) takes on the booked item, and holds it until the caller commits. A second request for an item
	 * of the product waits until then and sees the first booking, so two requests cannot both find the same item free.
	 * Reads are not blocked. The requested item is always checked (rental_item_is_free()): a booking stored by another
	 * request after the page loaded refuses it, also when this request's dates did not move.
	 *
	 * @param int|string $itemId The item the calendar chose, rentalitems.id.
	 * @param int|string $custId adresser.id of the customer.
	 * @param int $from First day of the booking, unix time.
	 * @param int $to Last day of the booking, unix time.
	 * @param string $expiryTime When an unpaid booking expires, 'Y-m-d H:i:s'.
	 * @param bool $mayUseOtherItem Whether another free item of the product may be booked when the requested one is
	 *                              not free (true when closed days moved the end past the period the calendar checked,
	 *                              see rental_free_item_for_period()). False: the booking is refused.
	 * @return array{item_id: int, booking_id: int}|null The booked item and the id of the rentalperiod row this call
	 *                                                   inserted, or null when no item may be booked or the row was
	 *                                                   not stored (the caller rolls back).
	 */
	function rental_insert_booking($itemId, $custId, $from, $to, $expiryTime, $mayUseOtherItem) {
		$itemId = (int)$itemId;
		$custId = (int)$custId;
		$from = (int)$from;
		$to = (int)$to;
		$expiryTime = db_escape_string((string)$expiryTime);
		$qtxt = "SELECT id FROM rentalitems WHERE id = $itemId ";
		$qtxt.= "OR product_id = (SELECT product_id FROM rentalitems WHERE id = $itemId) ORDER BY id FOR UPDATE";
		db_select($qtxt, __FILE__ . " linje " . __LINE__);
		if (!rental_item_is_free($itemId, $from, $to)) {
			$itemId = $mayUseOtherItem ? rental_free_item_for_period($itemId, $from, $to) : 0;
			if ($itemId <= 0) return null;
		}
		$qtxt = "INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id, expiry_time) VALUES ";
		$qtxt.= "($itemId, $custId, $from, $to, 0, TO_TIMESTAMP('$expiryTime', 'YYYY-MM-DD HH24:MI:SS')) RETURNING id";
		$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		$row = $query ? db_fetch_array($query) : false;
		if (!$row || !(int)$row['id']) return null;
		return array('item_id' => $itemId, 'booking_id' => (int)$row['id']);
	}
}
