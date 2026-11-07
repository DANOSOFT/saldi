<?php
// includes/stdFunc/rentalBookingCustomer.php
//
// History:
// 20261105 CL/LH Created. The public booking's createCust (remoteBooking/api.php) answered anyone who posted an e-mail with the id of the customer who has it.
//                It now answers a handle that only this session can book with (createBooking), whether the e-mail was a customer's or not.
//                A later handle in the session leaves the earlier ones good.
//                The booking page (remoteBooking/index.php) makes the session when it opens (rental_booking_customer_session()), so two tabs that send the form at once keep their handles in the same session.

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
