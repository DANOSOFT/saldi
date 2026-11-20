<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/rentalOverlap.php --- ver 5.1.1 --- 2026-11-20 ---
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
// 20261120 CL/MMR SST-1000 (review) rental_approve_booking(): a payment that comes back after the booking expired is only approved when the unit is still free (Morten 2026-11-20, option A).
//                 Before, the approval cleared expiry_time and invoiced, also when another booking had taken the unit or the expired booking had been deleted (order 0).
//                 An approved booking whose order was not invoiced (the invoice failed) is invoiced again on the next approval, and approvals of one booking run one at a time (rental_approval_lock()).
//                 rental_approval_lock() reports whether it got the lock, and the MySQL lock name includes the tenant database.

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
	 * @param int $exceptBookingId rentalperiod.id that is not counted (the booking being approved); 0 counts all.
	 * @return bool True when at least one booking or reservation blocks the period (see rental_period_blocks()).
	 */
	function rental_item_busy($itemId, $from, $to, $exceptBookingId = 0) {
		$itemId = (int)$itemId;
		$from = (int)$from;
		$to = (int)$to;
		$exceptBookingId = (int)$exceptBookingId;
		list($wideFrom, $wideTo) = rental_search_bounds($from, $to);
		$query = db_select("SELECT rt_from, rt_to, expiry_time FROM rentalperiod WHERE item_id = $itemId AND rt_from <= $wideTo AND rt_to >= $wideFrom AND id <> $exceptBookingId", __FILE__ . " linje " . __LINE__);
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

if (!function_exists('rental_approve_booking')) {
	/**
	 * Approve a booking from the public booking page or mysale when its payment has gone through: the booking stops
	 * waiting for payment (expiry_time is cleared), and the caller invoices the order.
	 *
	 * A booking whose payment came back after its expiry_time no longer held the unit, so another booking may have
	 * taken it. It is approved only when no other booking or reservation blocks its period now (Morten 2026-11-20).
	 * The unit's rentalitems row is locked as in rental_store_booking(), so a new booking of the unit cannot pass its
	 * check while the approval decides, and the booking row is locked so the clean-up of expired bookings waits.
	 *
	 * @param int $bookingId rentalperiod.id.
	 * @param int $orderId Set to the booking's order id when the result is 'approved', otherwise 0.
	 * @param int|null $now Unix time to compare the expiry with; null is time().
	 * A booking approved before (expiry_time cleared) whose order is not invoiced yet, because the invoice failed, is
	 * 'approved' again so the caller retries the invoice; the caller holds rental_approval_lock() so two approvals of one
	 * booking cannot invoice at the same time.
	 *
	 * @return string 'approved' (invoice the order), 'already' (approved and invoiced before; do not invoice again),
	 *                'missing' (no such booking, or it has no order) or 'unavailable' (expired and the unit is taken).
	 */
	function rental_approve_booking($bookingId, &$orderId, $now = null) {
		$bookingId = (int)$bookingId;
		$orderId = 0;
		$row = db_fetch_array(db_select("SELECT item_id FROM rentalperiod WHERE id = $bookingId", __FILE__ . " linje " . __LINE__));
		if (!$row) return 'missing';
		$itemId = (int)$row["item_id"];
		transaktion('begin');
		db_select("SELECT id FROM rentalitems WHERE id = $itemId FOR UPDATE", __FILE__ . " linje " . __LINE__);
		$row = db_fetch_array(db_select("SELECT item_id, rt_from, rt_to, order_id, expiry_time FROM rentalperiod WHERE id = $bookingId FOR UPDATE", __FILE__ . " linje " . __LINE__));
		if (!$row || (int)$row["item_id"] !== $itemId) {
			transaktion('rollback');
			return 'missing';
		}
		$bookingOrder = (int)$row["order_id"];
		if ($bookingOrder <= 0 || !db_fetch_array(db_select("SELECT id FROM ordrer WHERE id = $bookingOrder", __FILE__ . " linje " . __LINE__))) {
			transaktion('rollback');
			return 'missing';
		}
		if ($row["expiry_time"] === null || $row["expiry_time"] === '') {
			transaktion('rollback');
			if (rental_order_invoiced($bookingOrder)) return 'already';
			$orderId = $bookingOrder;
			return 'approved';
		}
		$expires = strtotime((string)$row["expiry_time"]);
		if ($expires !== false && $expires <= ($now === null ? time() : (int)$now)
			&& rental_item_busy($itemId, $row["rt_from"], $row["rt_to"], $bookingId)) {
			transaktion('rollback');
			return 'unavailable';
		}
		db_modify("UPDATE rentalperiod SET expiry_time = NULL WHERE id = $bookingId", __FILE__ . " linje " . __LINE__);
		transaktion('commit');
		$orderId = $bookingOrder;
		return 'approved';
	}
}

if (!function_exists('rental_order_invoiced')) {
	/**
	 * Whether a booking's order has been invoiced (ordrer.status 3 or higher, as fakturer_ordre() leaves it).
	 *
	 * @param int $orderId ordrer.id.
	 * @return bool True when the order exists and is invoiced.
	 */
	function rental_order_invoiced($orderId) {
		$orderId = (int)$orderId;
		if ($orderId <= 0) return false;
		$row = db_fetch_array(db_select("SELECT status FROM ordrer WHERE id = $orderId", __FILE__ . " linje " . __LINE__));
		return $row && (int)$row["status"] >= 3;
	}
}

if (!function_exists('rental_approval_lock')) {
	/**
	 * Take or release a lock on one booking's approval for the rest of the request, so a second approval of the same
	 * booking waits until the first has invoiced (or failed) and then sees the result. The lock belongs to the database
	 * session, so it is also released when the request ends with an error. On PostgreSQL it is an advisory lock (scoped
	 * to the tenant database); on MySQL/MariaDB a named lock, whose name includes the tenant database because those
	 * names are server-wide.
	 *
	 * @param int $bookingId rentalperiod.id.
	 * @param bool $hold True to take the lock (waits up to 60 seconds on MySQL, until free on PostgreSQL), false to release it.
	 * @return bool True when the lock was taken (or released); false on a timeout or a database error.
	 */
	function rental_approval_lock($bookingId, $hold = true) {
		global $db_type, $db;
		$bookingId = (int)$bookingId;
		if ($db_type == "mysql" || $db_type == "mysqli") {
			$name = 'rental_approval_' . substr(md5((string)$db), 0, 12) . '_' . $bookingId;
			$qtxt = $hold ? "SELECT GET_LOCK('$name', 60) AS got" : "SELECT RELEASE_LOCK('$name') AS got";
		} else {
			// 1000 = SST-1000's lock class, so the key cannot meet another feature's advisory locks
			$qtxt = $hold ? "SELECT 1 AS got FROM (SELECT pg_advisory_lock(1000, $bookingId)) AS l" : "SELECT pg_advisory_unlock(1000, $bookingId) AS got";
		}
		$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		$row = $query ? db_fetch_array($query) : false;
		return $row && in_array((string)$row["got"], array('1', 't'), true);
	}
}
