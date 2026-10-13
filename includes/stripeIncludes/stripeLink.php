<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeLink.php --- 2026.08.07
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
// 20260807 CL/LH Created: the ONE owner of the signed-link scheme
//                (doc/stripe/INTERFACE_CONTRACT.md par. 4). Payload is
//                "v1:<order_id>" and nothing else: line-derived signatures
//                would break every outstanding link on any order edit, and
//                subscribe.php's mandatory re-match already protects the
//                content. The mailer and CLI tools consume these functions
//                verbatim - nobody reimplements them.
// 20261007 CL/LH Added the invoice-payment links: "pay1:<order_id>" opens
//                api/stripe/faktura.php, "card1:<konto_id>" opens
//                api/stripe/kort.php. Own payload prefixes, so a subscribe
//                signature never opens a payment page or the reverse; v1 is
//                unchanged and outstanding subscribe links keep working.
//                stripe_same_origin() guards their POSTs.

include_once(__DIR__ . '/stripeSettings.php');

if (!function_exists('stripe_link_sign')) {
	function stripe_link_sign($order_id) {
		$secret = stripe_setting('link_secret');
		if (!$secret || strlen($secret) < 64) return ''; // 32 random bytes hex-encoded
		return hash_hmac('sha256', 'v1:' . (int)$order_id, $secret);
	}
}

if (!function_exists('stripe_link_verify')) {
	// Constant-time; false on any config gap (fail closed).
	function stripe_link_verify($order_id, $sig) {
		if (!is_string($sig) || !preg_match('/^[0-9a-f]{64}$/', $sig)) return false;
		$expected = stripe_link_sign($order_id);
		if ($expected === '') return false;
		return hash_equals($expected, $sig);
	}
}

if (!function_exists('stripe_link_url')) {
	// Full signed URL, or '' when config is incomplete (callers render nothing).
	// Base URL comes from settings ONLY - never $_SERVER[HTTP_HOST], which is
	// attacker-influenced on a public endpoint.
	function stripe_link_url($order_id) {
		$base = stripe_setting('public_base_url');
		$sig  = stripe_link_sign($order_id);
		if (!$base || $sig === '') return '';
		return $base . '/api/stripe/subscribe.php?id=' . (int)$order_id . '&sig=' . $sig;
	}
}

if (!function_exists('stripe_payload_sign')) {
	// HMAC over "<prefix>:<id>"; '' on a config gap. Prefixes: pay1, card1.
	function stripe_payload_sign($prefix, $id) {
		$secret = stripe_setting('link_secret');
		if (!$secret || strlen($secret) < 64 || !preg_match('/^[a-z0-9]+$/', $prefix)) return '';
		return hash_hmac('sha256', $prefix . ':' . (int)$id, $secret);
	}

	function stripe_payload_verify($prefix, $id, $sig) {
		if ((int)$id < 1 || !is_string($sig) || !preg_match('/^[0-9a-f]{64}$/', $sig)) return false;
		$expected = stripe_payload_sign($prefix, $id);
		if ($expected === '') return false;
		return hash_equals($expected, $sig);
	}

	// Signed "Se faktura og betal" URL for a posted invoice, or '' when config is incomplete.
	function stripe_pay_url($order_id) {
		$base = stripe_setting('public_base_url');
		$sig  = stripe_payload_sign('pay1', $order_id);
		if (!$base || $sig === '') return '';
		return $base . '/api/stripe/faktura.php?id=' . (int)$order_id . '&sig=' . $sig;
	}

	// Signed "Fjern kort" URL for a debitor's saved card, or ''.
	function stripe_card_url($konto_id) {
		$base = stripe_setting('public_base_url');
		$sig  = stripe_payload_sign('card1', $konto_id);
		if (!$base || $sig === '') return '';
		return $base . '/api/stripe/kort.php?k=' . (int)$konto_id . '&sig=' . $sig;
	}

	// POST guard for the payment pages: Origin must be the configured public
	// base URL (Sec-Fetch-Site as fallback for browsers that omit Origin).
	function stripe_same_origin() {
		$base = parse_url((string)stripe_setting('public_base_url'));
		if (!$base || empty($base['scheme']) || empty($base['host'])) return false;
		$want = strtolower($base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : ''));
		$origin = isset($_SERVER['HTTP_ORIGIN']) ? strtolower(rtrim((string)$_SERVER['HTTP_ORIGIN'], '/')) : '';
		if ($origin !== '') return $origin === $want;
		return isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] === 'same-origin';
	}
}
