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
