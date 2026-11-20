<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/deliveryContact.php --- ver 5.1.0 --- 2026.11.20 ---
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

include_once(__DIR__ . "/../../includes/shopify/schema.php");

/**
 * The contact rule for one delivery type (K61, email OR phone): "afhentning" and "levering"
 * need at least one of the two, "taget med" and anything else need neither.
 *
 * @param string $type  pos_leveringsvalg.type, or '' for a sale without a delivery choice.
 * @param string $email The sale's email.
 * @param string $phone The sale's phone number.
 * @return array{ok: bool, message: string} message is '' when ok.
 */
function pos_validate_delivery_contact($type, $email, $phone) {
	global $sprog_id;

	if (!in_array((string) $type, array('afhentning', 'levering'), true)) {
		return array('ok' => true, 'message' => '');
	}
	if (trim((string) $email) !== '' || trim((string) $phone) !== '') {
		return array('ok' => true, 'message' => '');
	}
	$message = findtekst('pos.delivery.contact_missing|Afhentning og levering kræver kundens e-mail eller telefonnummer.', $sprog_id);
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
 * The text as the till shows it: callers print it inside alert('...') in a double-quoted HTML
 * attribute, so quotes, backslashes, line breaks and angle brackets are taken out.
 *
 * @param string $message
 * @return string
 */
function pos_delivery_contact_alert_text($message) {
	return str_replace(
		array('\\', "'", '"', "\r", "\n", '<', '>'),
		array('', '’', '”', ' ', ' ', '', ''),
		(string) $message
	);
}
