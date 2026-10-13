<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/formFuncIncludes/invoicePaymentLink.php --- 2026.10.12
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
// 20261007 CL/LH Created: $fakturalink helper for the invoice mailers - the
//                signed "Se faktura og betal" link to api/stripe/faktura.php.
//                Derived from THIS order at send time, never stored. Every
//                guard failure returns '' (the token renders as nothing and
//                the mail sends normally); never throws, never alerts. The
//                page itself re-checks everything (paid, opt-out, currency).
// 20261012 CL/LH Use shared PDO eligibility and refuse unsupported drivers when minting invoice links.

include_once(__DIR__ . '/../stripeIncludes/stripeSettings.php');
include_once(__DIR__ . '/../stripeIncludes/stripeLink.php');
require_once __DIR__ . '/../stripeIncludes/InvoicePaymentStore.php';
require_once __DIR__ . '/../stripeIncludes/InvoicePaymentMath.php';
require_once __DIR__ . '/../stripeIncludes/InvoicePaymentEligibility.php';

if (!function_exists('invoicePaymentLinkUrl')) {
	/**
	 * Mint a signed mail link only for an eligible invoice in the pinned PostgreSQL tenant.
	 * @param int $ordre_id Posted invoice id.
	 * @param \Saldi\Stripe\InvoicePaymentStore|null $store Optional tenant store for tests.
	 * @return string Signed URL, or an empty string when unavailable or blocked.
	 */
	function invoicePaymentLinkUrl($ordre_id, ?\Saldi\Stripe\InvoicePaymentStore $store = null) {
		global $db, $db_type, $sqhost, $squser, $sqpass;
		$ordre_id = (int)$ordre_id;
		if ($ordre_id < 1 || in_array($db_type, ['mysql', 'mysqli'], true)) return '';
		$target = stripeConfigDb();
		if (!$target || !isset($db) || $db !== $target) return '';
		if (stripe_setting('invoice_pay_enabled', 'off') !== 'on') return '';
		$mode = stripe_setting('mode');
		if ($mode !== 'test' && $mode !== 'live') return '';
		if (!stripe_setting('secret_key') || !stripe_setting('publishable_key')) return '';
		if (strlen(stripe_setting('link_secret')) < 64) return '';
		if (!stripe_setting('public_base_url')) return '';
		try {
			if ($store === null) {
				$store = new \Saldi\Stripe\InvoicePaymentStore(new PDO('pgsql:host=' . $sqhost . ';dbname=' . $target, $squser, $sqpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));
			}
			$o = $store->row('SELECT art,konto_id,fakturanr,valuta,sum,moms FROM ordrer WHERE id=?', [$ordre_id]);
			if (!$o || $o['art'] !== 'DO' || trim((string)$o['fakturanr']) === '') return '';
			if (!in_array(strtoupper(trim((string)$o['valuta'])), ['', 'DKK'], true)) return '';
			if (\Saldi\Stripe\InvoicePaymentMath::fromDb($o['sum']) + \Saldi\Stripe\InvoicePaymentMath::fromDb($o['moms']) <= 0) return '';
			// 20261012 CL/LH The same policy protects the link, payment start and worker.
			if ((new \Saldi\Stripe\InvoicePaymentEligibility($store))->collectionReasons((int)$o['konto_id']) !== []) return '';
			return stripe_pay_url($ordre_id);
		} catch (Throwable $e) {
			return '';
		}
	}
}

if (!function_exists('invoicePaymentLinkHtml')) {
	// Email-safe CTA replacing $fakturalink in the HTML mailers. Same shape as
	// subscriptionLinkHtml(): one table, inline styles, exactly ONE <a> so the
	// AltBody flatten yields "Se faktura og betal: url", built on one line so
	// the template's "\n\r" -> <br> rewrite cannot fire inside it.
	/** @return string Email-safe CTA, or an empty string when no URL is available. */
	function invoicePaymentLinkHtml($url) {
		if (!$url) return '';
		$u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
		return "<table role='presentation' cellpadding='0' cellspacing='0' border='0' style='margin:12px 0;border-collapse:separate'>"
			. "<tr><td style='background:#2563eb;border-radius:8px'>"
			. "<a href=\"$u\" style='display:inline-block;padding:12px 28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none'>Se faktura og betal</a>"
			. "</td></tr>\n<tr><td style='padding-top:8px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7590'>"
			. "Betal med kort på under et minut. Bankoverførsel virker stadig som normalt."
			. "</td></tr></table>";
	}
}
