<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/einvoiceLineSign.php --- ver 5.1.0 --- 2026-11-04 ---
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
// 20261102 CL/MMR SST-746 Created. The quantity and unit price an order line is sent with on an e-invoice or credit note.
//                 A discount line (a discount item at a negative price) has a negative unit price, which Peppol does not allow (BR-27).
//                 It is sent as a negative quantity at a positive price, so the line amount and the VAT stay as in Saldi.
// 20261104 CL/MMR SST-746 Review (PR #998): the comments no longer name a customer or real invoice numbers.

if (!function_exists('einvoice_line_quantity_price')) {
	/**
	 * The quantity and unit price of an order line as sent to EasyUBL.
	 *
	 * The unit price is never negative: Peppol BIS Billing 3.0 rejects a negative item net price (BR-27).
	 * Since 20260825 such lines went out flagged isAllowanceCharge with the negative price, which EasyUBL
	 * also rejects (BR-S-08, "Coded allowance reasons MUST belong to the UNCL 5189 code list"). A line with
	 * a negative quantity at a positive price is accepted (31 of 31 digital invoices with such lines in a
	 * customer's account since March 2026), so a negative price is moved to the quantity.
	 * The line amount (quantity x price) keeps its sign, so the totals and the VAT breakdown are unchanged.
	 *
	 * A credit note is sent with the amounts it credits as positive figures. Saldi stores a credit note's
	 * lines with the sign they have in the books (a returned item is -2 x 100), so the quantity is
	 * negated. Until 20261102 both quantity and price were made positive with abs(), which turned the
	 * discount on a credit note (-1 x -600) into a charge of +600, so the credit note credited too much.
	 *
	 * @param float|int|string $antal        ordrelinjer.antal.
	 * @param float|int|string $pris         ordrelinjer.pris.
	 * @param bool             $isCreditNote True when the document is sent as a credit note.
	 * @return array{
	 *   quantity: float,  Quantity to send; negative for a discount or a returned item.
	 *   price: float,     Unit price to send; never negative.
	 * }
	 */
	function einvoice_line_quantity_price($antal, $pris, $isCreditNote) {
		$quantity = (float) $antal;
		$price = (float) $pris;
		if ($isCreditNote) {
			$quantity = -$quantity;
		}
		if ($price < 0) {
			$quantity = -$quantity;
			$price = -$price;
		}
		// -0.0 would be sent as "-0" in the JSON
		if ($quantity == 0) $quantity = 0.0;
		return array('quantity' => $quantity, 'price' => $price);
	}
}
