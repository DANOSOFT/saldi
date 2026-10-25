<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeEnrol.php --- 2026.10.22
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261019 CL/ASR Created (S2 enrolment, Requirements par. 7): when a payment with "gem kortet"
//                 succeeds, the card becomes the debtor's card on file. adresser gets
//                 stripe='on', stripe_customer_id, stripe_pm_id, stripe_date (mirrors pbs*),
//                 the Stripe customer gets the card as default, and "Udskriv til" becomes
//                 'Stripe' on the debtor and on the recurring order the invoice came from, so
//                 recurring invoicing keeps running and the charge job collects on the due date.
//                 Un-enrolment reverses it. The nextfakt date is read for series eligibility, never written.
// 20261022 CDX/LH Refuse new enrolment when invoice payments or enrolment are disabled.
// 20261022 CDX/LH Apply central invoice eligibility before changing a customer or saved card.
// 20261022 CDX/LH Keep historical invoices untouched; use the PI customer; deliver issued invoices on Afmeld with a retry outbox.
// 20261022 CDX/LH Document that recurrence dates are read for eligibility but never written.
// 20261022 CL/LH S5 Greet by first name in the outstanding-invoice mail sent after Afmeld.

include_once(__DIR__ . '/stripeHttp.php');
include_once(__DIR__ . '/stripeSettings.php');
include_once(__DIR__ . '/stripeInvoice.php');

if (!function_exists('stripe_enrol_debtor')) {
	/** @return bool Enrol only after the PaymentIntent customer accepts its saved card as default. */
	function stripe_enrol_debtor($inv, $pmId, $brand = '', $last4 = '', $piCustomer = '') {
		if (!stripe_invoice_collectable($inv)) return false;
		if (!stripe_feature_on('enrolment') || !stripe_feature_on('invoice_payments') || stripe_setting('enabled', 'off') !== 'on') return false;
		$d = $inv['debtor'];
		if (!$d || $pmId === '') return false;
		// Completion passes the verified PaymentIntent customer. The fallback supports legacy direct callers.
		$cus = $piCustomer !== '' ? (string)$piCustomer : (string)($inv['paid']['stripe_customer_id'] ?? ($d['stripe_customer_id'] ?? ''));
		if ($cus === '') return false;
		$r = stripeHttpRequest('POST', '/v1/customers/' . rawurlencode($cus), array('invoice_settings' => array('default_payment_method' => $pmId)));
		if (!$r['ok']) { stripe_log('enrol: default pm failed: ' . $r['error']); return false; }
		$role = stripeHttpRequest('POST', '/v1/payment_methods/' . rawurlencode($pmId), array('metadata' => array('saldi_role' => 'primary')));
		if (!$role['ok']) return false;
		// Keep the original enrolment date when replacing a card.
		$pm = db_escape_string($pmId);
		stripe_payment_modify("update adresser set stripe = 'on', stripe_customer_id = '" . db_escape_string($cus) . "', stripe_pm_id = '$pm', stripe_date = case when stripe = 'on' and stripe_date is not null then stripe_date else '" . db_escape_string(stripe_today()) . "' end, udskriv_til = 'Stripe' where id = " . (int)$d['id'], __FILE__ . " linje " . __LINE__);
		// Only the current source row for this series is a recurring template. Old
		// invoices can retain nextfakt too; neither those nor another series are rewritten.
		$ordrenr = (int)$inv['o']['ordrenr'];
		$today = db_escape_string(stripe_today());
		$template = db_fetch_array(stripe_payment_select("select max(id) as id from ordrer where art = 'DO'
			and ordrenr = $ordrenr and nextfakt >= '$today' and konto_id = " . (int)$d['id']));
		if (!empty($template['id'])) stripe_payment_modify("update ordrer set udskriv_til = 'Stripe' where id = " . (int)$template['id']);
		stripe_payment_modify("update stripe_customers set status = 'active', updated_at = CURRENT_TIMESTAMP where stripe_customer_id = '" . db_escape_string($cus) . "'", __FILE__ . " linje " . __LINE__);
		if (function_exists('audit_log_write') && function_exists('tbl_exists')) audit_log_write('stripe.enrolled', 'adresser', (string)$d['id'], json_encode(array('kontonr' => (string)$d['kontonr'], 'card' => stripe_card_label($brand, $last4), 'ordre' => (int)$inv['id'])), 'stripe');
		return true;
	}
	/**
	 * Stop future automatic collection and durably queue delivery of every issued unpaid invoice.
	 * Failed delivery remains retryable even after the debtor has already been un-enrolled.
	 *
	 * @return bool Whether enrolment was cleared and all pending delivery succeeded.
	 */
	function stripe_unenrol_debtor($kontoId, $reason = 'manual') {
		$kontoId = (int)$kontoId;
		$d = db_fetch_array(stripe_payment_select("select id, kontonr from adresser where id = $kontoId"));
		if (!$d) return false;
		// 20261025 CL/ASR back to "E-mail med link" when the link mail is available, else the classic e-mail
		$back = stripe_invoice_link_available() ? 'Link' : 'email';
		stripe_payment_transaction('begin');
		try {
			stripe_payment_modify("update adresser set stripe = '', stripe_pm_id = null, udskriv_til = case when udskriv_til = 'Stripe' then '$back' else udskriv_til end where id = $kontoId");
			$q = stripe_payment_select("select id from ordrer where konto_id = $kontoId and art = 'DO'
				and udskriv_til = 'Stripe' and coalesce(betalt, '') <> 'on' and coalesce(fakturanr, '') not in ('', '0')
				and not exists (select 1 from stripe_payments p where p.ordre_id = ordrer.id and (p.journal_line_id is not null or p.status in ('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'unmapped')))");
			$ids = array();
			while ($row = db_fetch_array($q)) $ids[] = (int)$row['id'];
			foreach ($ids as $id) {
				$exists = db_fetch_array(stripe_payment_select("select ordre_id from stripe_unenrol_mail where ordre_id = $id"));
				if (!$exists) stripe_payment_modify("insert into stripe_unenrol_mail (ordre_id) values ($id)");
				stripe_payment_modify("update ordrer set udskriv_til = '$back' where id = $id");
			}
			stripe_payment_modify("update ordrer set udskriv_til = '$back' where art = 'DO' and nextfakt >= '" . db_escape_string(stripe_today()) . "' and udskriv_til = 'Stripe' and konto_id = $kontoId");
			stripe_payment_transaction('commit');
		} catch (Throwable $e) {
			stripe_payment_transaction('rollback');
			throw $e;
		}
		if (function_exists('audit_log_write') && function_exists('tbl_exists')) audit_log_write('stripe.unenrolled', 'adresser', (string)$kontoId, json_encode(array('kontonr' => (string)$d['kontonr'], 'reason' => $reason)), 'stripe');
		return stripe_unenrol_mail_retry($kontoId) === 0;
	}
}

/** @return int Delivery failures; pending rows survive for the next CLI run. */
function stripe_unenrol_mail_retry($kontoId = 0, $dryRun = false) {
	$q = stripe_payment_select("select m.ordre_id from stripe_unenrol_mail m join ordrer o on o.id = m.ordre_id
		where m.sent_at is null" . ($kontoId ? ' and o.konto_id = ' . (int)$kontoId : '') . ' order by m.ordre_id');
	$ids = array();
	while ($row = db_fetch_array($q)) $ids[] = (int)$row['ordre_id'];
	$errors = 0;
	foreach ($ids as $id) {
		if ($dryRun) continue;
		stripe_invoice_collection_lock($id);
		try {
			$row = db_fetch_array(stripe_payment_select("select sent_at from stripe_unenrol_mail where ordre_id = $id"));
			if ($row['sent_at']) continue;
			$inv = stripe_invoice_load($id);
			if (!$inv) throw new RuntimeException('Un-enrolled invoice missing');
			if (!$inv['paid'] && !$inv['paid_bank']) {
				$ok = stripe_invoice_payable($inv) ? stripe_mail_invoice_link($inv) : stripe_mail_invoice_classic($inv);
				if (!$ok) throw new RuntimeException('Un-enrolled invoice delivery failed');
			}
			stripe_payment_modify("update stripe_unenrol_mail set sent_at = CURRENT_TIMESTAMP where ordre_id = $id");
		} catch (Throwable $e) {
			$errors++;
			stripe_charge_error($id, $e->getMessage());
		} finally {
			stripe_invoice_collection_lock($id, true);
		}
	}
	return $errors;
}

/** @return bool Send a conventional invoice attachment when card/link delivery cannot be used. */
function stripe_mail_invoice_classic($inv) {
	$pdf = stripe_invoice_pdf_path($inv['id']);
	if (!$pdf) return false;
	$greeting = stripe_mail_greeting($inv);
	$body = stripe_txt('mail_unenrol_body', array('nr' => $inv['nr']));
	return stripe_mail_send($inv['email'], stripe_txt('mail_subj_new', array('nr' => $inv['nr'], 'brand' => $inv['brand'])),
		'<p>' . stripe_h($greeting) . '</p><p>' . stripe_h($body) . '</p>', $greeting . "\n\n" . $body, array($pdf));
}
