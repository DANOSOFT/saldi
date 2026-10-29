<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/vibrantPayment.php --- ver 5.1.0 --- 2026-10-29 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2026-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261029 CL/MMR SST-841: New. Server-side Vibrant payment check for the login-free booking pages.
//                 updateBooking is a named handler with a pure decision function; approve and delete are atomic.
//                 A failed invoice keeps the booking approved and is retried once per call, under a lock.
//

include_once(__DIR__ . '/stdFunc/ensureTableAndColumns.php');

if (!function_exists('rental_payment_column')) {
/**
 * Makes sure rentalperiod has payment_id and payment_url: the Vibrant payment the server created for the booking.
 * The login-free booking pages never run includes/betweenUpdates.php, so they ensure the column themselves.
 * The full column list keeps a missing table complete (same list as includes/opdat_4.0.php).
 *
 * @param string $db  The account database, already validated by public_tenant_db().
 * @return void
 */
function rental_payment_column($db) {
	ensureTableAndColumns($db, 'rentalperiod', array(
		'id' => 'SERIAL PRIMARY KEY', 'order_id' => 'INTEGER', 'rt_from' => 'numeric(15,0)', 'rt_to' => 'numeric(15,0)',
		'item_id' => 'INTEGER', 'cust_id' => 'INTEGER', 'expiry_time' => 'TIMESTAMP', 'payment_id' => 'varchar(100)',
		'payment_url' => 'varchar(500)'
	));
}
}

if (!function_exists('vibrant_payment_state')) {
/**
 * Asks Vibrant (production API, the account's own key) for the state of a payment intent, so a booking is
 * approved or dropped on the server's own word and never on the browser's. Transport errors are retried twice.
 * SALDI_VIBRANT_API_BASE may point the call at a local mock in tests; it defaults to Vibrant's production API.
 *
 * @param string $apiKey     rentalpayment.apikey of the account.
 * @param string $paymentId  rentalperiod.payment_id, stored when the server created the payment link.
 * @return string|null       Vibrant's status, e.g. "succeeded" or "failed"; null when there is no key or id, or
 *                           Vibrant gave no usable answer.
 */
function vibrant_payment_state($apiKey, $paymentId) {
	if ($apiKey === '' || $paymentId === '') {
		return null;
	}
	$base = getenv('SALDI_VIBRANT_API_BASE') ?: 'https://pos.api.vibrant.app';
	for ($try = 1; $try <= 3; $try++) {
		$ch = curl_init(rtrim($base, '/') . '/pos/v1/payment_intents/' . rawurlencode($paymentId));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Apikey: ' . $apiKey, 'Content-Type: application/json'));
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($body !== false && $code >= 200 && $code < 300) {
			$result = json_decode($body);
			return (is_object($result) && isset($result->status) && is_string($result->status)) ? $result->status : null;
		}
		if ($body !== false && $code >= 400 && $code < 500) {
			return null;
		}
		if ($try < 3) {
			sleep(1);
		}
	}
	return null;
}
}

if (!function_exists('booking_payment_decision')) {
/**
 * What updateBooking does with a booking. The stored payment and Vibrant's answer decide, never the browser alone.
 *
 * @param array|false  $booking       rentalperiod row with expiry_time and payment_id, or false when it does not exist.
 * @param string       $status        What the browser asked for: "approved", "rejected", "timeout", ...
 * @param string|null  $paymentState  vibrant_payment_state() for the stored payment, or null.
 * @return string  "not_found", "already_approved" (expiry_time cleared), "approve", "delete" or "pending".
 */
function booking_payment_decision($booking, $status, $paymentState) {
	if (!$booking) {
		return 'not_found';
	}
	if (!$booking['expiry_time']) {
		return 'already_approved';
	}
	if ($paymentState === 'succeeded') {
		return 'approve';
	}
	$noPayment = (string)$booking['payment_id'] === '';
	if ($status !== 'approved' && ($noPayment || in_array($paymentState, array('failed', 'canceled', 'cancelled'), true))) {
		return 'delete';
	}
	return 'pending';
}
}

if (!function_exists('booking_invoice_once')) {
/**
 * Invoices a booking's order unless it already has an invoice number. A PostgreSQL advisory lock per order makes
 * concurrent calls wait for each other, so an order is never invoiced twice; a failed attempt can be repeated later.
 *
 * @param int      $orderId  ordrer.id of the booking.
 * @param callable $invoice  Invoices and mails the order; returns the order id on success (fakturer_ordre()).
 * @return bool  true when the order is invoiced (now or before), false when invoicing failed.
 */
function booking_invoice_once($orderId, $invoice) {
	$orderId = (int)$orderId;
	if ($orderId <= 0) {
		return false;
	}
	db_select("SELECT pg_advisory_lock(841, $orderId)", __FILE__ . " linje " . __LINE__);
	$order = db_fetch_array(db_select("SELECT fakturanr FROM ordrer WHERE id = $orderId", __FILE__ . " linje " . __LINE__));
	$done = $order && !in_array(trim((string)$order['fakturanr']), array('', '0'), true);
	if (!$done && $order) {
		$done = (string)call_user_func($invoice, $orderId) === (string)$orderId;
	}
	db_select("SELECT pg_advisory_unlock(841, $orderId)", __FILE__ . " linje " . __LINE__);
	return $done;
}
}

if (!function_exists('update_booking_payment')) {
/**
 * The updateBooking action of the login-free booking pages (remoteBooking/api.php, mysale/api.php). A payment the
 * server confirms with Vibrant approves the booking at once (so it is never cleared as expired) and then invoices
 * it once; if invoicing fails, the booking stays approved and a later call tries the invoice again. Deleting removes
 * the booking and then its order, only while the booking is still pending.
 *
 * @param string   $db       The account database, validated by public_tenant_db().
 * @param array    $data     The request body: id (booking id) and status.
 * @param callable $invoice  Invoices and mails an order id and returns it on success (fakturer_ordre()).
 * @return array{
 *   status: string,  "not_found", "approved", "invoice_failed", "deleted" or "pending".
 * }
 */
function update_booking_payment($db, $data, $invoice) {
	$bookingId = (int)($data['id'] ?? 0);
	rental_payment_column($db);
	$booking = db_fetch_array(db_select("SELECT order_id, expiry_time, payment_id FROM rentalperiod WHERE id = $bookingId", __FILE__ . " linje " . __LINE__));
	$paymentState = null;
	if ($booking && (string)$booking['payment_id'] !== '') {
		$r = db_fetch_array(db_select("SELECT apikey FROM rentalpayment WHERE id = 1", __FILE__ . " linje " . __LINE__));
		$paymentState = vibrant_payment_state($r ? (string)$r['apikey'] : '', (string)$booking['payment_id']);
	}
	$decision = booking_payment_decision($booking, (string)($data['status'] ?? ''), $paymentState);
	$orderId = $booking ? (int)$booking['order_id'] : 0;
	if ($decision === 'approve') {
		db_modify("UPDATE rentalperiod SET expiry_time = NULL WHERE id = $bookingId", __FILE__ . " linje " . __LINE__);
		$decision = 'already_approved';
	}
	if ($decision === 'already_approved') {
		if ($paymentState === 'succeeded' && !booking_invoice_once($orderId, $invoice)) {
			return array('status' => 'invoice_failed');
		}
		return array('status' => 'approved');
	}
	if ($decision === 'delete') {
		$deleted = db_fetch_array(db_select("DELETE FROM rentalperiod WHERE id = $bookingId AND expiry_time IS NOT NULL RETURNING order_id", __FILE__ . " linje " . __LINE__));
		if (!$deleted) {
			$still = db_fetch_array(db_select("SELECT id FROM rentalperiod WHERE id = $bookingId", __FILE__ . " linje " . __LINE__));
			return array('status' => $still ? 'approved' : 'deleted');
		}
		db_modify("DELETE FROM ordrer WHERE id = " . (int)$deleted['order_id'], __FILE__ . " linje " . __LINE__);
		return array('status' => 'deleted');
	}
	return array('status' => $decision);
}
}
