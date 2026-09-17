<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeSettings.php --- 2026.08.07
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
// 20260807 CL/LH Created: canonical settings access + tenant resolver for the
//                Stripe subscription integration. All stripe settings reads go
//                through stripe_setting() - nobody queries the settings table
//                for var_grp='stripe' directly. Contract: doc/stripe/INTERFACE_CONTRACT.md

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

if (!function_exists('stripe_settings_flush')) {
	// The stripe_valg save handler calls this after writing so reads in the same
	// request see the new values.
	function stripe_settings_flush() {
		global $stripe_settings_cache;
		$stripe_settings_cache = null;
	}
}

if (!function_exists('stripeConfigDb')) {
	// The one and only tenant resolver for the Stripe endpoints. The regnskab db
	// comes from the environment or a gitignored dotfile - NEVER from the request
	// and NEVER from a committed default (this file deploys to every install,
	// including customer installs on the same host). Returns '' when unresolvable:
	// callers must fail closed on ''.
	function stripeConfigDb() {
		static $resolved = null;
		if ($resolved !== null) return $resolved;
		$resolved = '';
		$candidate = getenv('SALDI_STRIPE_DB');
		if (!$candidate) {
			$file = __DIR__ . '/.ht_stripe_db';
			if (is_readable($file)) $candidate = trim((string)file_get_contents($file));
		}
		if (!$candidate || !preg_match('/^[a-z0-9_]{1,40}$/', $candidate)) return $resolved;
		// Validate against the global locator so a typo cannot select a random db
		// (precedent: includes/online.php:176).
		$q = db_select("select db from regnskab where db = '" . db_escape_string($candidate) . "'", __FILE__ . " linje " . __LINE__, true);
		if ($r = db_fetch_array($q)) $resolved = $r['db'];
		return $resolved;
	}
}

if (!function_exists('stripe_redact')) {
	// Masks Stripe secrets in any string destined for a log line or error mail.
	function stripe_redact($text) {
		return preg_replace('/\b((?:sk|rk|pk)_(?:live|test)_|whsec_)[A-Za-z0-9]+/', '$1***', (string)$text);
	}
}
