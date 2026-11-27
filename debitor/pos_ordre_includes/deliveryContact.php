<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/deliveryContact.php --- ver 5.1.1 --- 2026.11.24 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261120 CL/MJ SD-844 K61: a pickup or delivery sale cannot be paid at the till without the customer's email or phone.
//                The sale's delivery choice (ordrer.leveringsvalg_id -> pos_leveringsvalg.type) decides; no choice is "taget med" and is never checked.
// 20261123 CL/MJ SD-844 K61: the rule itself is pos_delivery_contact_ok(), with no text and no database.
// 20261124 CL/MJ SD-844 K61: quotes in the refusal become \x27 and \x22, not curly quotes, which a LATIN9 tenant cannot show.
//                A refusal is remembered for the request, so voucherPay() does not show it a second time.

include_once(__DIR__ . "/../../includes/shopify/schema.php");

/**
 * Whether a sale of this delivery type may be paid with this contact data (K61, email OR phone):
 * "afhentning" and "levering" need at least one of the two, "taget med" and anything else neither.
 *
 * @param string $type  pos_leveringsvalg.type, or '' for a sale without a delivery choice.
 * @param string $email The sale's email.
 * @param string $phone The sale's phone number.
 * @return bool
 */
function pos_delivery_contact_ok($type, $email, $phone) {
	return !in_array((string) $type, array('afhentning', 'levering'), true)
		|| trim((string) $email) !== '' || trim((string) $phone) !== '';
}

/**
 * The contact rule for one delivery type (pos_delivery_contact_ok()), with the text for the cashier.
 *
 * @param string $type  pos_leveringsvalg.type, or '' for a sale without a delivery choice.
 * @param string $email The sale's email.
 * @param string $phone The sale's phone number.
 * @return array{ok: bool, message: string} message is '' when ok.
 */
function pos_validate_delivery_contact($type, $email, $phone) {
	global $sprog_id;

	if (pos_delivery_contact_ok($type, $email, $phone)) {
		return array('ok' => true, 'message' => '');
	}
	$message = findtekst('pos.delivery.contact_missing|Afhentning og levering kræver kundens e-mail eller telefonnummer.', $sprog_id);
	// The caller shows the refusal; a later check in the same request (voucherPay()) does not show it again.
	$GLOBALS['pos_delivery_contact_refused'] = true;
	return array('ok' => false, 'message' => pos_delivery_contact_alert_text($message));
}

/**
 * The contact rule for a sale, read from the database: its delivery choice's type and its own
 * email and phone. A sale passes untouched where the Shopify schema is not installed (MySQL, or
 * an install that never ran the manifest), because ordrer.leveringsvalg_id only exists there.
 *
 * @param int $orderId ordrer.id of the POS sale.
 * @return array{ok: bool, message: string}
 */
function pos_validate_checkout_sale($orderId) {
	$orderId = (int) $orderId;
	if ($orderId <= 0 || !shopify_schema_ready()) {
		return array('ok' => true, 'message' => '');
	}
	$qtxt = "select o.email, o.phone, v.type from ordrer o left join pos_leveringsvalg v on v.id = o.leveringsvalg_id ";
	$qtxt .= "where o.id = '$orderId'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$r) {
		return array('ok' => true, 'message' => '');
	}
	return pos_validate_delivery_contact((string) $r['type'], (string) $r['email'], (string) $r['phone']);
}

/**
 * The text as the till shows it: callers print it inside alert('...'), in a double-quoted HTML
 * attribute or a script tag. Quotes become the JavaScript escapes \x27 and \x22, which are plain
 * ASCII, so the alert shows them on a LATIN9 tenant too. Backslashes, line breaks and angle
 * brackets are taken out.
 *
 * @param string $message
 * @return string
 */
function pos_delivery_contact_alert_text($message) {
	return str_replace(
		array('\\', "'", '"', "\r", "\n", '<', '>'),
		array('', '\\x27', '\\x22', ' ', ' ', '', ''),
		(string) $message
	);
}
