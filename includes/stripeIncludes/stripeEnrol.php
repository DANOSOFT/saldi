<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeEnrol.php --- 2026.10.19
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20261019 CL/ASR Created (S2 enrolment, Requirements par. 7): when a payment with "gem kortet"
//                 succeeds, the card becomes the debtor's card on file. adresser gets
//                 stripe='on', stripe_customer_id, stripe_pm_id, stripe_date (mirrors pbs*),
//                 the Stripe customer gets the card as default, and "Udskriv til" becomes
//                 'Stripe' on the debtor and on the recurring order the invoice came from, so
//                 recurring invoicing keeps running and the charge job collects on the due date.
//                 Un-enrolment reverses it. Nothing here touches ordrer.nextfakt (contract).

include_once(__DIR__ . '/stripeHttp.php');

if (!function_exists('stripe_enrol_debtor')) {
	function stripe_enrol_debtor($inv, $pmId, $brand = '', $last4 = '') {
		$d = $inv['debtor'];
		if (!$d || $pmId === '') return false;
		$cus = !empty($d['stripe_customer_id']) ? (string)$d['stripe_customer_id'] : stripe_customer_for_debtor($inv);
		if ($cus === '') return false;
		$r = stripeHttpRequest('POST', '/v1/customers/' . rawurlencode($cus), array('invoice_settings' => array('default_payment_method' => $pmId)));
		if (!$r['ok']) stripe_log('enrol: default pm failed: ' . $r['error']);
		stripeHttpRequest('POST', '/v1/payment_methods/' . rawurlencode($pmId), array('metadata' => array('saldi_role' => 'primary')));
		// previous primary (if another card) -> plain card, not deleted
		$pm = db_escape_string($pmId);
		db_modify("update adresser set stripe = 'on', stripe_customer_id = '" . db_escape_string($cus) . "', stripe_pm_id = '$pm', stripe_date = '" . date('Y-m-d') . "', udskriv_til = 'Stripe' where id = " . (int)$d['id'], __FILE__ . " linje " . __LINE__);
		// the recurring order this invoice was generated from: same ordrenr, art DO, nextfakt set
		$ordrenr = (int)$inv['o']['ordrenr'];
		db_modify("update ordrer set udskriv_til = 'Stripe' where art = 'DO' and ordrenr = $ordrenr and nextfakt is not null and konto_id = " . (int)$d['id'], __FILE__ . " linje " . __LINE__);
		db_modify("update stripe_customers set status = 'active', updated_at = CURRENT_TIMESTAMP where stripe_customer_id = '" . db_escape_string($cus) . "'", __FILE__ . " linje " . __LINE__);
		if (function_exists('audit_log_write') && function_exists('tbl_exists')) audit_log_write('stripe.enrolled', 'adresser', (string)$d['id'], json_encode(array('kontonr' => (string)$d['kontonr'], 'card' => stripe_card_label($brand, $last4), 'ordre' => (int)$inv['id'])), 'stripe');
		return true;
	}
	function stripe_unenrol_debtor($kontoId, $reason = 'manual') {
		$d = db_fetch_array(db_select("select id, kontonr, stripe_pm_id from adresser where id = " . (int)$kontoId, __FILE__ . " linje " . __LINE__));
		if (!$d) return false;
		db_modify("update adresser set stripe = '', stripe_pm_id = null, udskriv_til = case when udskriv_til = 'Stripe' then 'email' else udskriv_til end where id = " . (int)$kontoId, __FILE__ . " linje " . __LINE__);
		db_modify("update ordrer set udskriv_til = 'email' where art = 'DO' and nextfakt is not null and udskriv_til = 'Stripe' and konto_id = " . (int)$kontoId, __FILE__ . " linje " . __LINE__);
		if (function_exists('audit_log_write') && function_exists('tbl_exists')) audit_log_write('stripe.unenrolled', 'adresser', (string)$kontoId, json_encode(array('kontonr' => (string)$d['kontonr'], 'reason' => $reason)), 'stripe');
		return true;
	}
}
