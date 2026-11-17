<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/orderIncludes/orderPayment.php --- ver 5.1.1 --- 2026-11-17 ---
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
// 20261117 CL/MMR SST-1018 Created. With Brug samlet pris (POS/2 box12) felt_2 and felt_4 on an order are the two payment amounts.
//                 credit_note_payment_amount() turns a paid amount of the credited order into the credit note's amount.
//                 order_invoice_payment_check() is the payment check the order page (debitor/ordre.php) runs before Fakturér/Kreditér, moved out of the page.
//                 It also stops a credit note while a payment has the sale's sign (credit_note_payment_sign_wrong()), even when the amounts add up to the total.

/**
 * The payment amount a new credit note gets from the order it credits.
 *
 * With Brug samlet pris the order page keeps the payments in felt_2 (first payment, e.g. card) and felt_4
 * (second payment, e.g. cash). A credit note pays back what the order took in, so each amount changes sign.
 * Zero and a value that is not a number are returned unchanged, so an unused field does not become "-0".
 *
 * @param mixed $amount felt_2 or felt_4 of the credited order, as a number (usdecimal()) or a numeric string.
 * @return mixed The amount with the opposite sign, or the value unchanged when it is 0 or not a number.
 */
function credit_note_payment_amount($amount) {
	if (!is_numeric($amount) || (float)$amount == 0) {
		return $amount;
	}
	return -(float)$amount;
}

/**
 * Whether a payment amount on a credit note has the sign of a sale.
 *
 * A credit note whose total is negative pays money back, so neither payment may be positive. A positive amount
 * there is booked as money taken in on the payment's account, while the other payment is computed as the total
 * minus this one and is booked too large on its own account. Zero, a value that is not a number and a credit
 * note whose total is not negative are not checked here; the sum check still applies to them.
 *
 * @param mixed $amount felt_2 or felt_4 of the credit note.
 * @param mixed $total  The credit note's total incl. VAT (sum + moms).
 * @return bool True when the total is negative and the amount is positive.
 */
function credit_note_payment_sign_wrong($amount, $total): bool {
	if (!is_numeric($amount) || !is_numeric($total)) {
		return false;
	}
	return round((float)$total, 2) < 0 && round((float)$amount, 2) > 0;
}

/**
 * The payment check the order page runs when Fakturér or Kreditér is pressed with Brug samlet pris.
 *
 * Konto as first payment on an order whose terms are Kreditkort or Kontant is not invoiced. A credit note
 * with a payment that has a sale's sign is not invoiced, also when the amounts add up to the total. Otherwise
 * the payments must add up to the total.
 *
 * @param string $art          ordrer.art ('DO' order, 'DK' credit note).
 * @param mixed  $felt_1       First payment type.
 * @param mixed  $betalingsbet Payment terms.
 * @param mixed  $felt_2       First payment amount (usdecimal()).
 * @param mixed  $felt_4       Second payment amount (usdecimal()).
 * @param mixed  $sum          Order total excl. VAT.
 * @param mixed  $moms         Order VAT.
 * @return array{stop: bool, alert: string|null, paid: float, total: float}
 *         stop: invoicing must not go on (the page saves instead).
 *         alert: 'sign' for a credit note payment with a sale's sign, 'sum' when the payments do not add up, else null.
 *         paid and total: felt_2 + felt_4 and sum + moms, for the 'sum' message.
 */
function order_invoice_payment_check($art, $felt_1, $betalingsbet, $felt_2, $felt_4, $sum, $moms): array {
	$stop = ($felt_1 == 'Konto' && ($betalingsbet == 'Kreditkort' || $betalingsbet == 'Kontant'));
	$alert = null;
	$total = $sum + $moms;
	$paid = $felt_2 + $felt_4;
	if ($art == 'DK' && (credit_note_payment_sign_wrong($felt_2, $total) || credit_note_payment_sign_wrong($felt_4, $total))) {
		$stop = true;
		$alert = 'sign';
	} elseif (afrund($total - $paid, 2)) {
		$stop = true;
		$alert = 'sum';
	}
	return array('stop' => $stop, 'alert' => $alert, 'paid' => $paid, 'total' => $total);
}
