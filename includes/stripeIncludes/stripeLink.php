<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeLink.php --- 2026.10.22
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
// 20260807 CL/LH Created: the ONE owner of the signed-link scheme
//                (doc/stripe/INTERFACE_CONTRACT.md par. 4). Payload is
//                "v1:<order_id>" and nothing else: line-derived signatures
//                would break every outstanding link on any order edit, and
//                subscribe.php's mandatory re-match already protects the
//                content. The mailer and CLI tools consume these functions
//                verbatim - nobody reimplements them.
// 20261022 CDX/LH Separate invoice, signup and removal signatures; reject unscoped links and foreign payment Origins.

include_once(__DIR__ . '/stripeSettings.php');

if (!function_exists('stripe_link_sign')) {
	/** @return string Purpose-bound HMAC, or empty on invalid input/configuration. */
	function stripe_link_sign($order_id, $purpose = 'subscribe') {
		if ((int)$order_id < 1 || !in_array($purpose, array('subscribe', 'invoice', 'remove-card'), true)) return '';
		$secret = stripe_setting('link_secret');
		if (!$secret || strlen($secret) < 64) return ''; // 32 random bytes hex-encoded
		return hash_hmac('sha256', 'v2:' . $purpose . ':' . (int)$order_id, $secret);
	}
}

if (!function_exists('stripe_link_verify')) {
	/** @return bool Constant-time verification for precisely the requested purpose. */
	function stripe_link_verify($order_id, $sig, $purpose = 'subscribe') {
		if (!is_string($sig) || !preg_match('/^[0-9a-f]{64}$/', $sig)) return false;
		$expected = stripe_link_sign($order_id, $purpose);
		if ($expected === '') return false;
		return hash_equals($expected, $sig);
	}
}

if (!function_exists('stripe_link_url')) {
	// Full signed URL, or '' when config is incomplete (callers render nothing).
	// Base URL comes from settings ONLY - never $_SERVER[HTTP_HOST], which is
	// attacker-influenced on a public endpoint.
	function stripe_link_url($order_id) {
		if (!stripe_signup_allowed()) return '';
		$base = stripe_setting('public_base_url');
		$sig  = stripe_link_sign($order_id);
		if (!$base || $sig === '') return '';
		return $base . '/api/stripe/subscribe.php?id=' . (int)$order_id . '&sig=' . $sig;
	}
}

if (!function_exists('stripe_signup_allowed')) {
	/** @return bool New subscription signup requires both independently enabled switches. */
	function stripe_signup_allowed() {
		return stripe_setting('enabled', 'off') === 'on' && stripe_feature_on('signup_enabled');
	}
}

if (!function_exists('stripe_invoice_origin_allowed')) {
	/** @return bool Public payment POSTs must originate at the configured public origin. */
	function stripe_invoice_origin_allowed($origin) {
		if (!is_string($origin) || $origin === '') return false;
		$actual = parse_url($origin);
		$expected = parse_url((string)stripe_setting('public_base_url'));
		if (!$actual || !$expected || isset($actual['user']) || isset($actual['pass'])
			|| isset($actual['query']) || isset($actual['fragment']) || !empty($actual['path'])) return false;
		foreach (array('scheme', 'host') as $key) {
			if (empty($actual[$key]) || empty($expected[$key]) || strtolower($actual[$key]) !== strtolower($expected[$key])) return false;
		}
		$defaultPort = strtolower($expected['scheme']) === 'https' ? 443 : 80;
		return (int)($actual['port'] ?? $defaultPort) === (int)($expected['port'] ?? $defaultPort);
	}
}
