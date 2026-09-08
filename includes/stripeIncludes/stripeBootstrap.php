<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeBootstrap.php --- 2026.08.07
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
// 20260807 CL/LH Created: session-less tenant bootstrap for the Stripe entry
//                scripts (api/stripe/*.php, tools/). Include AFTER connect.php
//                at TOP LEVEL (plain statements, deliberately not a function:
//                connect.php and db_connect() work through globals). Sets
//                $stripe_boot_ok; on false the caller must respond 503 and
//                stop - never continue against the global db.
//                Same idiom as api/bet_func.php logon(): locator lookup is in
//                stripeConfigDb(), then db_connect() to the tenant.
// 20260818 CL/LH Added false-return handling for Stripe database writes.

include_once(__DIR__ . '/stripeSettings.php');

$webservice = true; // db_modify() returns control on failure instead of exiting

if (!function_exists('stripe_db_modify')) {
	function stripe_db_modify($qtext, $spor) {
		$result = db_modify($qtext, $spor);
		if (!is_string($result) || strncmp($result, '0' . chr(9), 2) !== 0) return false;
		return $result;
	}
}

$stripe_boot_ok    = false;
$stripe_target_db  = stripeConfigDb(); // '' = unresolved -> fail closed

if ($stripe_target_db) {
	$connection = db_connect($sqhost, $squser, $sqpass, $stripe_target_db);
	if ($connection) {
		$db = $stripe_target_db;
		$stripe_boot_ok = true;
	}
}
