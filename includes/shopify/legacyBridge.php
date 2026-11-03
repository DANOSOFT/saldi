<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/legacyBridge.php --- ver 5.1.0 --- 2026.11.03
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// The intake's seam to the legacy order functions (WP09-1): they read their state from globals
// and, on a bad order, print an alert and exit. shopify_with_legacy_context() lends them the
// globals for one call and takes them back. shopify_preflight_delivery() checks, before
// levering() is called, everything that would make it exit for a headless caller, so the
// intake never relies on catching an exit or reading HTML.
//
// 20261103 CL/SS SD-742: created (WP09-1).
// 20261103 CL/SS SD-742 review: the preflight refuses a line whose variant is not one of its item.

/** Globals levering() and bogfor() set and read again later: always given back, so one order's never reach the next. */
const SHOPIFY_LEGACY_STATE_GLOBALS = array('levdate', 'lev_nr', 'afd_lager', 'fakturadate', 'momssats', 'kasse');

/** The delivery would not go through; thrown before levering() runs, nothing is written. */
final class ShopifyPreflightError extends DomainException
{
}

/**
 * Runs $op with the given globals set, and restores every one of them afterwards, with those in
 * SHOPIFY_LEGACY_STATE_GLOBALS: the value it had, or none when it was not set. The legacy
 * functions read $momssats, $afd, $levdate and the like from the global scope, and levering()
 * leaves $lev_nr, $levdate and $afd_lager set for the next order.
 *
 * @param array<string, mixed> $ctx global name => value for the call
 * @return mixed what $op returns
 */
function shopify_with_legacy_context(array $ctx, callable $op) {
	$saved = array();
	foreach (array_unique(array_merge(SHOPIFY_LEGACY_STATE_GLOBALS, array_keys($ctx))) as $name) {
		$saved[$name] = array_key_exists($name, $GLOBALS) ? array(true, $GLOBALS[$name]) : array(false, null);
	}
	foreach ($ctx as $name => $value) {
		$GLOBALS[$name] = $value;
	}
	try {
		return $op();
	} finally {
		foreach ($saved as $name => [$had, $value]) {
			if ($had) {
				$GLOBALS[$name] = $value;
			} else {
				unset($GLOBALS[$name]);
			}
		}
	}
}

/**
 * Everything levering() refuses for a headless caller, found before it runs: the order must be
 * open with a delivery date, not before its order date; every item line's item must exist with
 * an item group of the fiscal year that has a purchase and a sales account (linjeopdat()); a
 * variant-controlled item needs its variant, and a line's variant must be one of its item; a line with serial numbers cannot be delivered
 * headless; a web order has no returns, so no line may be negative, and what is delivered and
 * left to deliver may not exceed the line's quantity. S09b calls levering($id, 'on', NULL, true),
 * under which levering() sets the date and the quantities itself; the checks hold either way.
 *
 * @throws ShopifyPreflightError
 */
function shopify_preflight_delivery(int $ordreId): void {
	global $regnaar;
	$order = shopify_db_rows('SELECT id, status, levdate, ordredate, art FROM ordrer WHERE id = $1', array($ordreId));
	if ($order === array()) {
		throw new ShopifyPreflightError("order $ordreId does not exist");
	}
	if ((int)$order[0]['status'] >= 3) {
		throw new ShopifyPreflightError("order $ordreId is already posted (status {$order[0]['status']})");
	}
	if (trim((string)$order[0]['levdate']) === '') {
		throw new ShopifyPreflightError("order $ordreId has no delivery date");
	}
	if (trim((string)$order[0]['ordredate']) !== '' && $order[0]['levdate'] < $order[0]['ordredate']) {
		throw new ShopifyPreflightError("order $ordreId has delivery date {$order[0]['levdate']} before its order date {$order[0]['ordredate']}");
	}
	$lines = shopify_db_rows(
		'SELECT l.id, l.posnr, l.varenr, l.vare_id, l.antal, COALESCE(l.leveres, 0) AS leveres, l.serienr, l.variant_id, v.gruppe,
			g.id AS group_id, g.box3, g.box4,
			EXISTS (SELECT 1 FROM variant_varer vv WHERE vv.vare_id = l.vare_id) AS variant_item,
			EXISTS (SELECT 1 FROM variant_varer vo WHERE vo.id::text = btrim(l.variant_id::text) AND vo.vare_id = l.vare_id) AS variant_owned,
			(SELECT COALESCE(sum(antal), 0) FROM batch_salg b WHERE b.linje_id = l.id) AS delivered
		 FROM ordrelinjer l LEFT JOIN varer v ON v.id = l.vare_id
			LEFT JOIN grupper g ON g.art = \'VG\' AND g.kodenr::text = v.gruppe::text AND g.fiscal_year::text = $2
		 WHERE l.ordre_id = $1 AND l.posnr > 0 AND btrim(COALESCE(l.varenr, \'\')) <> \'\'
		 ORDER BY l.posnr, l.id',
		array($ordreId, (string)(int)$regnaar)
	);
	foreach ($lines as $l) {
		$pos = "line {$l['posnr']} ({$l['varenr']})";
		if ((int)$l['vare_id'] < 1 || $l['gruppe'] === null || (int)$l['gruppe'] < 1) {
			throw new ShopifyPreflightError("order $ordreId: $pos has no item with an item group");
		}
		if ($l['group_id'] === null || trim((string)$l['box3']) === '' || trim((string)$l['box4']) === '') {
			throw new ShopifyPreflightError("order $ordreId: $pos has item group {$l['gruppe']} without a purchase and sales account in the fiscal year");
		}
		if (in_array($l['variant_item'], array(true, 't', '1', 1), true) && (int)$l['variant_id'] < 1) {
			throw new ShopifyPreflightError("order $ordreId: $pos is a variant item without a variant");
		}
		if ((int)$l['variant_id'] > 0 && !in_array($l['variant_owned'], array(true, 't', '1', 1), true)) {
			throw new ShopifyPreflightError("order $ordreId: $pos has variant {$l['variant_id']}, which is not a variant of its item");
		}
		if (trim((string)$l['serienr']) !== '') {
			throw new ShopifyPreflightError("order $ordreId: $pos carries serial numbers, which cannot be delivered headless");
		}
		if ((float)$l['antal'] < 0) {
			throw new ShopifyPreflightError("order $ordreId: $pos is a return; a web order has none");
		}
		if ((float)$l['delivered'] + (float)$l['leveres'] > (float)$l['antal']) {
			throw new ShopifyPreflightError("order $ordreId: $pos has {$l['delivered']} delivered and {$l['leveres']} to deliver of {$l['antal']}");
		}
	}
}
