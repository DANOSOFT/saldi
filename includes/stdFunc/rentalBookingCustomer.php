<?php
// includes/stdFunc/rentalBookingCustomer.php
//
// History:
// 20261105 CL/LH Created. The public booking's createCust (remoteBooking/api.php) answered anyone who posted an e-mail with the id of the customer who has it.
//                It now answers a handle that only this session can book with (createBooking), whether the e-mail was a customer's or not.
//                A later handle in the session leaves the earlier ones good.
//                The booking page (remoteBooking/index.php) makes the session when it opens (rental_booking_customer_session()), so two tabs that send the form at once keep their handles in the same session.
// 20261116 CL/ASR The session also keeps the bookings its createBooking made, per regnskab (rental_booking_note()), and updateBooking acts only on those (rental_booking_noted_in_session()).
//                 Mit salg keeps its bookings apart from the booking page's (rental_booking_mysale_notes()), so each page's updateBooking acts only on bookings made through it.
//                 rental_booking_note_in_session() answers false also when the session could not be saved (session_write_close() answers true then and only warns), so createBooking makes no hold.

if (!function_exists('rental_booking_customer_issue')) {
/**
 * Records in the session which customer the public booking (remoteBooking/api.php?createCust) found or made for the
 * e-mail it was given at this regnskab, and answers the handle createBooking takes for that customer instead of its id.
 * The handle is random and means something only in this session and for this regnskab: it says nothing about who the
 * customer is, nor whether the e-mail was already a customer's. A later createCust in the session adds its own handle
 * and leaves the earlier ones good, so two tabs that each send the form before they book both book. Only the 20 latest
 * handles of a session at a regnskab are kept; the booking page books with its handle in the request after createCust.
 *
 * @param array<string, mixed> $session $_SESSION
 * @param string               $db      the regnskab's database
 * @param int|string           $custId  adresser.id of the customer
 * @return string the handle
 */
function rental_booking_customer_issue(array &$session, $db, $custId) {
	$handle = bin2hex(random_bytes(16));
	$issued = $session['rentalBookingCustomers'][(string)$db] ?? array();
	if (!is_array($issued)) {
		$issued = array();
	}
	$issued[] = array('handle' => $handle, 'id' => (int)$custId);
	$session['rentalBookingCustomers'][(string)$db] = array_slice($issued, -20);
	return $handle;
}
}

if (!function_exists('rental_booking_customer')) {
/**
 * The adresser.id of the customer rental_booking_customer_issue() gave this handle for, in this session and at this
 * regnskab. 0 for anything else: a customer id, a handle the session was not given, or one given at another regnskab.
 *
 * @param array<string, mixed> $session $_SESSION
 * @param mixed                $handle  what the request names the customer by (createBooking's cust_id)
 * @param string               $db      the regnskab's database
 * @return int
 */
function rental_booking_customer(array $session, $handle, $db) {
	$issued = $session['rentalBookingCustomers'][(string)$db] ?? null;
	if (!is_string($handle) || !is_array($issued)) {
		return 0;
	}
	foreach ($issued as $customer) {
		if (is_array($customer) && is_string($customer['handle'] ?? null) && hash_equals($customer['handle'], $handle)) {
			return (int)($customer['id'] ?? 0);
		}
	}
	return 0;
}
}

if (!function_exists('rental_booking_customer_handle')) {
/**
 * rental_booking_customer_issue() on this request's session, which it starts and closes again: what createCust answers.
 *
 * @param string     $db     the regnskab's database
 * @param int|string $custId adresser.id of the customer
 * @return string the handle, '' when no session could be started (createBooking refuses that)
 */
function rental_booking_customer_handle($db, $custId) {
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	if (session_status() !== PHP_SESSION_ACTIVE) {
		return '';
	}
	$handle = rental_booking_customer_issue($_SESSION, $db, $custId);
	session_write_close();
	return $handle;
}
}

if (!function_exists('rental_booking_customer_session')) {
/**
 * Makes the visitor's session when the booking page (remoteBooking/index.php) opens, before it sends anything, so the
 * browser has the session cookie before the form can be sent. createCust keeps its handles in that session and
 * createBooking looks them up there. Without it, two tabs of a browser that has no session yet would each send
 * createCust without a cookie and each get a session of their own; the browser keeps one of the two cookies, so the
 * other tab's handle would book nothing. A session the visitor already has is kept. The session gets a place for this
 * regnskab's handles, so it is stored even before createCust has put one in it.
 *
 * @param string $db the regnskab's database
 * @return bool whether the visitor has a session now
 */
function rental_booking_customer_session($db) {
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	if (session_status() !== PHP_SESSION_ACTIVE) {
		return false;
	}
	if (!is_array($_SESSION['rentalBookingCustomers'][(string)$db] ?? null)) {
		$_SESSION['rentalBookingCustomers'][(string)$db] = array();
	}
	session_write_close();
	return true;
}
}

if (!function_exists('rental_booking_note')) {
/**
 * Records in the session that the public booking's createBooking (remoteBooking/api.php, mysale/api.php) made this
 * booking (rentalperiod.id) at this regnskab. updateBooking approves or rejects only a booking its session made
 * (rental_booking_noted()). Only the 20 latest bookings of a session at a regnskab are kept.
 *
 * The booking page (remoteBooking/api.php) keeps them under the regnskab's database name, Mit salg (mysale/api.php)
 * under rental_booking_mysale_notes() of it: the two pages share the visitor's PHP session, and each page's
 * updateBooking acts only on bookings made through it. Every function here that takes $db takes either.
 *
 * @param array<string, mixed> $session   $_SESSION
 * @param string               $db        the regnskab's database (Mit salg: rental_booking_mysale_notes() of it)
 * @param int|string           $bookingId rentalperiod.id
 * @return void
 */
function rental_booking_note(array &$session, $db, $bookingId) {
	$noted = $session['rentalBookings'][(string)$db] ?? array();
	if (!is_array($noted)) {
		$noted = array();
	}
	$noted[] = (int)$bookingId;
	$session['rentalBookings'][(string)$db] = array_slice($noted, -20);
}
}

if (!function_exists('rental_booking_noted')) {
/**
 * Whether rental_booking_note() recorded this booking in this session at this regnskab. False for a booking another
 * session made, one made at another regnskab, one rental_booking_unnote() took out again, and anything that is not a
 * booking id.
 *
 * @param array<string, mixed> $session   $_SESSION
 * @param mixed                $bookingId what the request names the booking by (updateBooking's id)
 * @param string               $db        the regnskab's database
 * @return bool
 */
function rental_booking_noted(array $session, $bookingId, $db) {
	$noted = $session['rentalBookings'][(string)$db] ?? null;
	if (is_string($bookingId) && ctype_digit($bookingId)) {
		$bookingId = (int)$bookingId;
	}
	if (!is_int($bookingId) || $bookingId <= 0 || !is_array($noted)) {
		return false;
	}
	foreach ($noted as $id) {
		if ((int)$id === $bookingId) {
			return true;
		}
	}
	return false;
}
}

if (!function_exists('rental_booking_unnote')) {
/**
 * Takes a booking out of what rental_booking_note() recorded, once updateBooking has deleted it.
 *
 * @param array<string, mixed> $session   $_SESSION
 * @param string               $db        the regnskab's database
 * @param int|string           $bookingId rentalperiod.id
 * @return void
 */
function rental_booking_unnote(array &$session, $db, $bookingId) {
	$noted = $session['rentalBookings'][(string)$db] ?? null;
	if (!is_array($noted)) {
		return;
	}
	$session['rentalBookings'][(string)$db] = array_values(array_filter($noted, function ($id) use ($bookingId) {
		return (int)$id !== (int)$bookingId;
	}));
}
}

if (!function_exists('rental_booking_note_in_session')) {
/**
 * rental_booking_note() on this request's session, which it starts and closes again: what createBooking does once it
 * has the new booking's id. The visitor has a session by then: createBooking found its customer there.
 * session_write_close() answers true also when the session could not be written, and only warns then, so a warning
 * while the session is closed counts as not saved.
 *
 * @param string     $db        the regnskab's database
 * @param int|string $bookingId rentalperiod.id
 * @return bool false when no session could be started or the session could not be saved (createBooking then makes no booking)
 */
function rental_booking_note_in_session($db, $bookingId) {
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	if (session_status() !== PHP_SESSION_ACTIVE) {
		return false;
	}
	rental_booking_note($_SESSION, $db, $bookingId);
	$saved = true;
	set_error_handler(function () use (&$saved) {
		$saved = false;
		return false; // the warning is still reported as usual
	}, E_WARNING);
	$closed = session_write_close();
	restore_error_handler();
	return $closed && $saved;
}
}

if (!function_exists('rental_booking_noted_in_session')) {
/**
 * Whether this request's session made the booking (rental_booking_noted()): what updateBooking asks before it reads or
 * writes anything. The session is opened only when the request carries its cookie, and it stays open until the request
 * ends: a second request of the same session waits for the session until this one is done, and then finds what this
 * one did to the booking. The caller may change $_SESSION (rental_booking_unnote()); it is saved when the request ends.
 *
 * @param string $db        the regnskab's database
 * @param mixed  $bookingId what the request names the booking by
 * @return bool
 */
function rental_booking_noted_in_session($db, $bookingId) {
	if (session_status() === PHP_SESSION_NONE && isset($_COOKIE[session_name()])) {
		session_start();
	}
	return session_status() === PHP_SESSION_ACTIVE && rental_booking_noted($_SESSION, $bookingId, $db);
}
}

if (!function_exists('rental_booking_mysale_notes')) {
/**
 * What Mit salg (mysale/api.php) keeps its bookings of a regnskab under in the session, in place of the database name
 * the booking page (remoteBooking/api.php) uses (rental_booking_note()). A booking made through one page is then not
 * approved or rejected through the other, and in Mit salg only while the booking's customer is signed in
 * (mySaleSignedInAs()).
 *
 * @param string $db the regnskab's database
 * @return string what the functions here take as $db for Mit salg
 */
function rental_booking_mysale_notes($db) {
	return 'mysale:' . $db;
}
}
