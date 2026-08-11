<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeHttp.php --- 2026.08.07
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
// 20260807 CL/LH Created: thin cURL client for the Stripe API (no SDK - the
//                repo's composer.json is dev-only and prod vendor/ is server-
//                level). Form-encoded requests per Stripe's API contract.
//                Never throws, never prints; returns a structured array so
//                callers decide how to surface errors.
//                Contract: doc/stripe/INTERFACE_CONTRACT.md

include_once(__DIR__ . '/stripeSettings.php');

// Pinned API version: every request states it explicitly so a Stripe account
// upgrade can never change response shapes under us. The sandbox/live accounts
// are created against this version (deploy runbook step 5.2).
if (!defined('SALDI_STRIPE_API_VERSION')) define('SALDI_STRIPE_API_VERSION', '2026-07-29.dahlia'); // = sandbox account default, recorded 2026-08-07

if (!function_exists('stripeHttpRequest')) {
	/**
	 * @param string $method 'GET' | 'POST' | 'DELETE'
	 * @param string $path   e.g. '/v1/checkout/sessions'
	 * @param array  $params form parameters; nested arrays become Stripe's
	 *                       bracket notation via http_build_query
	 * @param array  $opts   'idempotency_key' => string
	 * @return array ok(bool), status(int), body(array|string|null),
	 *               error(string), error_code(string), request_id(string)
	 */
	function stripeHttpRequest($method, $path, $params = [], $opts = []) {
		$out = ['ok' => false, 'status' => 0, 'body' => null, 'error' => '', 'error_code' => '', 'request_id' => ''];

		$mode = stripe_setting('mode');
		if ($mode != 'test' && $mode != 'live') {
			$out['error'] = 'stripe mode is not configured (expected test or live)';
			$out['error_code'] = 'config_mode';
			return $out;
		}
		$secret = stripe_setting('secret_key');
		if (!$secret) {
			$out['error'] = 'stripe secret_key is not configured';
			$out['error_code'] = 'config_key';
			return $out;
		}
		// Crossed-wires guard: a live key with mode=test (or vice versa) sends NO
		// request at all - this is the primary protection during the live cutover.
		$keyIsTest = (strpos($secret, 'sk_test_') === 0 || strpos($secret, 'rk_test_') === 0);
		$keyIsLive = (strpos($secret, 'sk_live_') === 0 || strpos($secret, 'rk_live_') === 0);
		if (($mode == 'test' && !$keyIsTest) || ($mode == 'live' && !$keyIsLive)) {
			$out['error'] = 'secret_key prefix does not match mode=' . $mode . ' - no request sent';
			$out['error_code'] = 'config_mode_mismatch';
			return $out;
		}

		$method = strtoupper($method);
		$url = 'https://api.stripe.com' . $path;
		$headers = [
			'Authorization: Bearer ' . $secret,
			'Stripe-Version: ' . SALDI_STRIPE_API_VERSION,
		];
		if (!empty($opts['idempotency_key'])) $headers[] = 'Idempotency-Key: ' . $opts['idempotency_key'];

		$ch = curl_init();
		if ($method == 'GET') {
			if ($params) $url .= '?' . http_build_query($params);
		} elseif ($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
			$headers[] = 'Content-Type: application/x-www-form-urlencoded';
		} elseif ($method == 'DELETE') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
			if ($params) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
				$headers[] = 'Content-Type: application/x-www-form-urlencoded';
			}
		} else {
			curl_close($ch);
			$out['error'] = 'unsupported method ' . $method;
			$out['error_code'] = 'config_method';
			return $out;
		}
		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$out) {
				if (stripos($line, 'Request-Id:') === 0) $out['request_id'] = trim(substr($line, 11));
				return strlen($line);
			},
		]);

		$raw = curl_exec($ch);
		if ($raw === false) {
			$out['error'] = 'curl: ' . curl_error($ch);
			$out['error_code'] = 'network';
			curl_close($ch);
			return $out;
		}
		$out['status'] = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);

		$decoded = json_decode($raw, true);
		$out['body'] = ($decoded === null && $raw !== 'null') ? $raw : $decoded;

		if ($out['status'] >= 200 && $out['status'] < 300) {
			$out['ok'] = true;
		} else {
			if (is_array($decoded) && isset($decoded['error']['message'])) {
				$out['error'] = $decoded['error']['message'];
				$out['error_code'] = isset($decoded['error']['code']) ? $decoded['error']['code'] : (isset($decoded['error']['type']) ? $decoded['error']['type'] : 'http_' . $out['status']);
			} else {
				$out['error'] = 'HTTP ' . $out['status'];
				$out['error_code'] = 'http_' . $out['status'];
			}
			$out['error'] = stripe_redact($out['error']);
		}
		return $out;
	}
}
