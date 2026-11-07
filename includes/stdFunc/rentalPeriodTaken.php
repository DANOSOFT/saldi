<?php
// includes/stdFunc/rentalPeriodTaken.php
//
// History:
// 20261105 CL/LH Created. A stand was booked, through the public booking (remoteBooking/api.php, mysale/api.php), by staff (rental/rental.php) or by the importer (rental/upload.php), for days it was already booked on, also when two requests for it came at once.

if (!function_exists('rental_begin_stand_lock')) {
/**
 * Opens a transaction whose first statement locks a stand's row (rentalitems), held until the caller commits or rolls back.
 * Every save of a booking opens its transaction here, checks the stand (rental_period_booked(), rental_period_taken()) and
 * commits once the booking is written: a second save for the stand, public or by staff, waits here and then finds the first
 * one's booking instead of finding the stand free before either has written.
 * Nothing may be read in the transaction before the lock. MySQL and MariaDB (InnoDB, REPEATABLE READ) read a whole
 * transaction from the snapshot of its first plain read, so a read before the lock would hide a booking committed while this
 * save waited for it. A locking read takes no snapshot, so the check after it sees every booking committed before the lock
 * was given. PostgreSQL (READ COMMITTED) takes a snapshot per statement.
 *
 * @param int $itemId rentalitems.id, the stand
 * @return bool false when there is no such stand, with the transaction rolled back
 */
function rental_begin_stand_lock($itemId) {
	$itemId = (int)$itemId;
	transaktion('begin');
	if (db_fetch_array(db_select("select id from rentalitems where id = $itemId for update", __FILE__ . " linje " . __LINE__))) {
		return true;
	}
	transaktion('rollback');
	return false;
}
}

if (!function_exists('rental_rows_on_days')) {
/**
 * Whether a stand has a booking (rentalperiod) or a block (rentalreserved) on any calendar day from $from's day to $to's.
 * Both ends count, as in the booking calendars (remoteBooking/index.js, mysale/remote.js, rental/booking.js, rental/edit.js):
 * a booking cannot start on the day another booking of the stand ends.
 * Days are compared, not times: the public booking stores local midnight, the staff booking (rental/booking.js)
 * UTC midnight, and a request may carry a time of day. A day is the date in PHP's timezone (includes/settings.php).
 *
 * @param string $table    'rentalperiod' or 'rentalreserved'
 * @param int    $itemId   rentalitems.id, the stand
 * @param int    $from     unix time on the first day
 * @param int    $to       unix time on the last day
 * @param int    $exceptId the table's id of a row that does not count (0: none)
 * @return bool
 */
function rental_rows_on_days($table, $itemId, $from, $to, $exceptId = 0) {
	$columns = array('rentalperiod' => array('rt_from', 'rt_to'), 'rentalreserved' => array('rr_from', 'rr_to'));
	list($fromColumn, $toColumn) = $columns[$table];
	$itemId = (int)$itemId;
	$from = (int)$from;
	$to = (int)$to;
	$exceptId = (int)$exceptId;
	$firstDay = date('Y-m-d', $from);
	$lastDay = date('Y-m-d', $to);
	// A row on one of the days starts less than a day and a DST hour after $to and ends less than that before $from.
	$before = $to + 2 * 86400;
	$after = $from - 2 * 86400;
	$qtxt = "select $fromColumn as day_from, $toColumn as day_to from $table where item_id = $itemId and $fromColumn <= $before and $toColumn >= $after";
	if ($exceptId) {
		$qtxt .= " and id <> $exceptId";
	}
	$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if (date('Y-m-d', (int)$row['day_from']) <= $lastDay && date('Y-m-d', (int)$row['day_to']) >= $firstDay) {
			return true;
		}
	}
	return false;
}
}

if (!function_exists('rental_period_booked')) {
/**
 * Whether a stand has a booking (rentalperiod), the public's or staff's, on any calendar day of the period.
 * The staff saves (rental/rental.php) ask this: staff may book a blocked stand, the page asks them first.
 * Days as in rental_rows_on_days().
 *
 * @param int $itemId        rentalitems.id, the stand
 * @param int $from          unix time on the first day
 * @param int $to            unix time on the last day
 * @param int $exceptBooking rentalperiod.id of the booking being moved, which does not count (0: none)
 * @return bool
 */
function rental_period_booked($itemId, $from, $to, $exceptBooking = 0) {
	return rental_rows_on_days('rentalperiod', $itemId, $from, $to, $exceptBooking);
}
}

if (!function_exists('rental_period_taken')) {
/**
 * Whether a stand is booked (rentalperiod) or blocked (rentalreserved) on any calendar day of the period.
 * The public booking (remoteBooking/api.php, mysale/api.php) asks this: its calendar offers neither.
 * Days as in rental_rows_on_days().
 *
 * @param int $itemId rentalitems.id, the stand
 * @param int $from   unix time on the first day
 * @param int $to     unix time on the last day
 * @return bool
 */
function rental_period_taken($itemId, $from, $to) {
	return rental_period_booked($itemId, $from, $to) || rental_rows_on_days('rentalreserved', $itemId, $from, $to);
}
}
