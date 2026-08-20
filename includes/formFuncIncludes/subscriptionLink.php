<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/formFuncIncludes/subscriptionLink.php --- 2026.08.17
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
// 20260817 CL/LH Created: $abonnementslink helper for the invoice mailers.
//                Derives the signed Stripe subscription URL from THIS order
//                at send time - the link is NEVER stored on the order, because
//                debitor/genfakturer.php's recurring clone drops stored fields.
//                Every guard failure returns '' (the token renders as nothing
//                and the mail sends normally) - never throws, never alerts:
//                a customer without subscription lines is the NORMAL case.
//                Contract: doc/stripe/INTERFACE_CONTRACT.md
// 20260818 CL/LH Guarded subscription links when stripe_catalog is unavailable.
// 20260820 CL/LH Added subscriptionLinkHtml(): shared email-safe CTA block for
//                the HTML invoice mailers (sendMail.php/oldSendMail.php).

include_once(__DIR__ . '/../stripeIncludes/stripeSettings.php');
include_once(__DIR__ . '/../stripeIncludes/stripeLink.php');

if (!function_exists('subscriptionLinkUrl')) {
	function subscriptionLinkUrl($ordre_id) {
		global $db;
		$ordre_id = (int)$ordre_id;
		if ($ordre_id < 1) return '';
		// Tenant guard: subscribe.php loads the order from the PINNED regnskab
		// (stripeConfigDb()), so a link minted from any other regnskab on the
		// same install would resolve to the wrong order there. Only the pinned
		// tenant may mint.
		$target = stripeConfigDb();
		if (!$target || !isset($db) || $db !== $target) return '';
		if (stripe_setting('enabled', 'off') !== 'on') return '';
		$mode = stripe_setting('mode');
		if ($mode !== 'test' && $mode !== 'live') return '';
		if (!stripe_setting('secret_key')) return '';
		if (strlen(stripe_setting('link_secret')) < 64) return '';
		if (!stripe_setting('public_base_url')) return '';
		$o = db_fetch_array(db_select("select id, art, konto_id from ordrer where id = " . $ordre_id, __FILE__ . " linje " . __LINE__));
		if (!$o || $o['art'] !== 'DO') return '';
		// Per-debtor opt-out ("Ingen kortbetaling" on the debitorkort) beats
		// templates and catalog. Column-existence is cached per tenant so a
		// mid-deploy install without the migration simply has no opt-outs yet.
		static $fravalg_col = [];
		if (!isset($fravalg_col[$db])) {
			$fc = db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'adresser' and column_name = 'stripe_fravalg'", __FILE__ . " linje " . __LINE__));
			$fravalg_col[$db] = !empty($fc['column_name']);
		}
		if ($fravalg_col[$db] && (int)$o['konto_id'] > 0) {
			$a = db_fetch_array(db_select("select stripe_fravalg from adresser where id = " . (int)$o['konto_id'], __FILE__ . " linje " . __LINE__));
			if ($a && trim((string)$a['stripe_fravalg']) !== '') return '';
		}
		static $catalog_exists = [];
		if (!isset($catalog_exists[$db])) {
			$r = db_fetch_array(db_select("select to_regclass('stripe_catalog') as table_name", __FILE__ . " linje " . __LINE__));
			$catalog_exists[$db] = !empty($r['table_name']);
		}
		if (!$catalog_exists[$db]) return '';
		// Already subscribed (mirrors subscribe.php's guard): renewal invoices
		// keep going out, but a paying subscriber gets no sign-up button.
		static $customers_exists = [];
		if (!isset($customers_exists[$db])) {
			$r = db_fetch_array(db_select("select to_regclass('stripe_customers') as table_name", __FILE__ . " linje " . __LINE__));
			$customers_exists[$db] = !empty($r['table_name']);
		}
		if ($customers_exists[$db] && (int)$o['konto_id'] > 0) {
			$s = db_fetch_array(db_select("select stripe_subscription_id from stripe_customers where konto_id = " . (int)$o['konto_id'] . " and status in ('active','trialing','past_due')", __FILE__ . " linje " . __LINE__));
			if ($s) return '';
		}
		// At least one line must map to an active catalog row - otherwise this
		// simply is not a subscription customer.
		$q = db_select("select l.id from ordrelinjer l join stripe_catalog c on c.active = true and c.varenr = l.varenr where l.ordre_id = " . $ordre_id . " limit 1", __FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($q)) return '';
		return stripe_link_url($ordre_id);
	}
}

if (!function_exists('subscriptionLinkHtml')) {
	// Email-safe CTA block replacing the $abonnementslink token in the HTML
	// mailers: table + inline styles (Gmail strips <style>; Outlook needs the
	// table for the button shape) with exactly ONE <a>, so the AltBody anchor
	// flatten still yields "Tilmeld automatisk betaling: url". Built as a
	// single line - the template pipeline rewrites "\n\r" to <br>, which must
	// not fire inside the block. '' in, '' out (not a subscription customer).
	function subscriptionLinkHtml($url) {
		if (!$url) return '';
		$u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
		return "<table role='presentation' cellpadding='0' cellspacing='0' border='0' style='margin:12px 0;border-collapse:separate'>"
			. "<tr><td style='background:#2563eb;border-radius:8px'>"
			. "<a href=\"$u\" style='display:inline-block;padding:12px 28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none'>Tilmeld automatisk betaling</a>"
			// The lone \n keeps the plain-text (AltBody) rendering readable after
			// strip_tags and never matches the template's "\n\r" -> <br> rewrite.
			. "</td></tr>\n<tr><td style='padding-top:8px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7590'>"
			. "Slip for at taste kortoplysninger hver gang - fremtidige fakturaer betales automatisk. "
			. "Du modtager stadig faktura på mail, og du kan opsige når som helst."
			. "</td></tr></table>";
	}
}
