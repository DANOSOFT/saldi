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
		$o = db_fetch_array(db_select("select id, art from ordrer where id = " . $ordre_id, __FILE__ . " linje " . __LINE__));
		if (!$o || $o['art'] !== 'DO') return '';
		static $catalog_exists = [];
		if (!isset($catalog_exists[$db])) {
			$r = db_fetch_array(db_select("select to_regclass('stripe_catalog') as table_name", __FILE__ . " linje " . __LINE__));
			$catalog_exists[$db] = !empty($r['table_name']);
		}
		if (!$catalog_exists[$db]) return '';
		// At least one line must map to an active catalog row - otherwise this
		// simply is not a subscription customer.
		$q = db_select("select l.id from ordrelinjer l join stripe_catalog c on c.active = true and c.varenr = l.varenr where l.ordre_id = " . $ordre_id . " limit 1", __FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($q)) return '';
		return stripe_link_url($ordre_id);
	}
}
