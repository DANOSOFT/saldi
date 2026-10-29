<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeSettings.php --- 2026.10.26
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
// 20260807 CL/LH Created: canonical settings access + tenant resolver for the
//                Stripe subscription integration. All stripe settings reads go
//                through stripe_setting() - nobody queries the settings table
//                for var_grp='stripe' directly. Contract: doc/stripe/INTERFACE_CONTRACT.md
// 20261022 CDX/LH Add independent invoice, enrolment and signup rollout switch defaults.
// 20261022 CDX/LH Default all new rollout switches off, including subscription signup.
// 20261026 CL/NTR Multi-ledger: stripe_ledger_db() validates a ledger name against the master locator and
//                 stripe_request_db() takes the ledger from a public request (?r=), falling back to stripeConfigDb()
//                 for links and webhook endpoints from before the ledger was carried in the URL.
//                 stripe_ledger_armed() keeps a ledger restored from a copy (same keys) from collecting until
//                 its Stripe settings are saved in that ledger.

if (!function_exists('stripe_setting')) {
	// Returns the value of a settings row (var_grp='stripe') or $default when the
	// row is absent or empty. The whole group is loaded once per request.
	function stripe_setting($name, $default = '') {
		global $stripe_settings_cache;
		if (!is_array($stripe_settings_cache)) {
			$stripe_settings_cache = [];
			$q = db_select("select var_name, var_value from settings where var_grp = 'stripe'", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$stripe_settings_cache[$r['var_name']] = $r['var_value'];
			}
		}
		if (isset($stripe_settings_cache[$name]) && $stripe_settings_cache[$name] !== '') {
			return $stripe_settings_cache[$name];
		}
		return $default;
	}
}

if (!function_exists('stripe_feature_on')) {
	/**
	 * Read a Stripe rollout switch. Unknown features fail closed.
	 *
	 * The shared enabled switch remains independent so pilot webhook processing
	 * can continue while invoice payments and enrolment are disabled.
	 *
	 * @param string $name invoice_payments, enrolment or signup_enabled.
	 * @return bool Whether the feature is explicitly on; all rollout switches default off.
	 */
	function stripe_feature_on($name) {
		$defaults = array('invoice_payments' => 'off', 'enrolment' => 'off', 'signup_enabled' => 'off');
		if (!array_key_exists($name, $defaults)) {
			return false;
		}
		return stripe_setting($name, $defaults[$name]) === 'on';
	}
}

if (!function_exists('stripe_settings_flush')) {
	// The stripe_valg save handler calls this after writing so reads in the same
	// request see the new values.
	function stripe_settings_flush() {
		global $stripe_settings_cache;
		$stripe_settings_cache = null;
	}
}

if (!function_exists('stripeConfigDb')) {
	// The pinned (legacy default) ledger: links without r= and the original
	// webhook URL resolve to it, see stripe_request_db(). The regnskab db
	// comes from the environment or a gitignored dotfile - NEVER from a
	// committed default (this file deploys to every install, including customer
	// installs on the same host). Returns '' when unresolvable: callers must
	// fail closed on ''. Optional since every ledger can run Stripe itself.
	function stripeConfigDb() {
		static $resolved = null;
		if ($resolved !== null) return $resolved;
		$resolved = '';
		$candidate = getenv('SALDI_STRIPE_DB');
		if (!$candidate) {
			$file = __DIR__ . '/.ht_stripe_db';
			if (is_readable($file)) $candidate = trim((string)file_get_contents($file));
		}
		$resolved = stripe_ledger_db($candidate);
		return $resolved;
	}
}

if (!function_exists('stripe_ledger_db')) {
	/**
	 * Validate a ledger name against the master locator, so a typo or a forged URL cannot select a
	 * random db. Closed ledgers (regnskab.lukket) are refused like unknown ones.
	 *
	 * @param mixed $candidate Ledger db name from config, a CLI option or a public request.
	 * @return string The ledger db, or '' (callers must fail closed on '').
	 */
	function stripe_ledger_db($candidate) {
		if (!is_string($candidate) || !preg_match('/^[a-z0-9_]{1,40}$/', $candidate)) return '';
		$q = db_select("select db, lukket from regnskab where db = '" . db_escape_string($candidate) . "'", __FILE__ . " linje " . __LINE__, true);
		$r = db_fetch_array($q);
		if (!$r || ($r['lukket'] ?? '') === 'on') return '';
		return (string)$r['db'];
	}
}

if (!function_exists('stripe_ledger_armed')) {
	/**
	 * Whether the current ledger may mint links and collect. Settings are copied along with a ledger
	 * (a test ledger restored from production keeps the live keys), so a ledger only counts as set up
	 * when its Stripe settings were saved in it: target_db holds the ledger they were saved in. The
	 * pinned ledger is armed regardless (it predates target_db being the ledger's own name).
	 *
	 * @return bool Stripe in this ledger is its own, not a copy's.
	 */
	function stripe_ledger_armed() {
		global $db;
		if (!is_string($db) || $db === '') return false;
		return stripe_setting('target_db') === $db || $db === stripeConfigDb();
	}
}

if (!function_exists('stripe_request_db')) {
	/**
	 * Ledger for a session-less public request. Signed links and webhook URLs carry it as r=<db>; the
	 * signature (or the ledger's own webhook secret) is what authenticates it, never r itself. Without r
	 * the request is an old link or the original webhook endpoint, both of the pinned stripeConfigDb().
	 *
	 * @return string The ledger db, or '' when r is given but not a valid open ledger (no fallback then).
	 */
	function stripe_request_db() {
		$r = $_GET['r'] ?? ($_POST['r'] ?? null);
		if ($r === null) return stripeConfigDb();
		return stripe_ledger_db($r);
	}
}

if (!function_exists('stripe_redact')) {
	// Masks Stripe secrets in any string destined for a log line or error mail.
	function stripe_redact($text) {
		return preg_replace('/\b((?:sk|rk|pk)_(?:live|test)_|whsec_)[A-Za-z0-9]+/', '$1***', (string)$text);
	}
}
