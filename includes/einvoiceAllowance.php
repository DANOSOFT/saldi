<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoiceAllowance.php --- patch 5.0.0 --- 2026-08-28 ---
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
//
// Copyright (c) 2008-2026 Danosoft.ApS
// ----------------------------------------------------------------------
//
// 20260828 CL SST-746: Peppol BIS 3.0 (BR-27) rejects invoice lines with a negative
//             item net price, but Saldi stores order-level discounts as ordinary
//             order lines with a negative unit price (typically varenr '999rabat').
//             This helper splits a built invoiceLines array by SIGN of lineAmount:
//             negative lines become document-level allowanceCharges entries with a
//             POSITIVE amount and isCharge=false, everything else passes through
//             untouched. Detection is by sign, not varenr - the DB holds negative
//             lines under several item numbers and 224 with none at all.
//
//             NOT yet wired into debitor/api.php sendInvoice(). Wiring notes:
//             - Must run on lines that still carry their ORIGINAL sign: the
//               credit-note branch at the top of the ordrelinjer loop abs()'es
//               pris, which flips a rebate to a positive charge and makes the
//               credit note credit too much. Split first, abs() after.
//             - The allowance must carry the same VAT category/rate as the goods
//               (the discount line itself has momssats=0, but the order's VAT is
//               computed AFTER discount). Field name for VAT on an allowance is
//               pending confirmation from EasyUBL - see the vatPercent TODO below.

/**
 * Split built e-invoice lines into (lines to send, document-level allowances).
 *
 * @param array $lines           Line arrays as built in sendInvoice(); each must
 *                               carry at least 'lineAmount' and 'description'.
 * @param float $goodsVatPercent VAT rate the allowance must be deducted under -
 *                               the goods' rate, NOT the discount line's own
 *                               momssats (which is 0 in the DB).
 * @return array [0] => lines with non-negative lineAmount, reindexed;
 *               [1] => allowanceCharges entries (EasyUBL stub field names).
 */
function einvoice_split_allowances(array $lines, $goodsVatPercent)
{
	$invoiceLines = array();
	$allowances = array();
	foreach ($lines as $l) {
		$lineAmount = isset($l['lineAmount']) ? (float)$l['lineAmount'] : 0.0;
		if ($lineAmount < 0) {
			$allowances[] = array(
				'isCharge'   => false,               // false = allowance (discount)
				'reasonCode' => '95',                // UNCL5189: 95 = Discount
				'reason'     => isset($l['description']) && trim((string)$l['description']) !== ''
									? $l['description'] : 'Rabat',
				'percentage' => 0,
				'amount'     => round(abs($lineAmount), 2),
				'baseAmount' => 0,
				// TODO SST-746: confirm with EasyUBL which field carries the VAT
				// category/rate for an allowance; without it the allowance lands
				// outside the goods' VAT base and totals fail BR-CO-10/BR-CO-13.
				'vatPercent' => (float)$goodsVatPercent,
			);
		} else {
			$invoiceLines[] = $l;
		}
	}
	return array($invoiceLines, $allowances);
}
