<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/fefo.php --- patch 4.2.0 --- 2026-04-16 ---
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
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// FEFO (First Expired, First Out) helper functions for expiry date handling.

if (!function_exists('fefo_order_clause')) {
	/**
	 * Returns the ORDER BY clause for batch selection.
	 * Uses FEFO (First Expired, First Out) ordering:
	 *   1. Batches with due_date sorted earliest first
	 *   2. NULL due_dates sorted last (fallback to FIFO)
	 *   3. kobsdate ASC as secondary sort (FIFO tiebreaker)
	 *   4. id ASC as final tiebreaker
	 *
	 * This ordering is safe for all items: items without due_dates
	 * will naturally sort by kobsdate (FIFO) since all due_dates are NULL.
	 *
	 * @return string  The ORDER BY clause (without the "ORDER BY" keyword)
	 */
	function fefo_order_clause() {
		return "CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC, kobsdate ASC, id ASC";
	}
}

if (!function_exists('fefo_batch_query')) {
	/**
	 * Returns the full SELECT query for fetching available batches
	 * for a given item, optionally filtered by warehouse and variant.
	 * Results are ordered by FEFO principle.
	 *
	 * @param int    $vare_id     Item ID
	 * @param int    $lager       Warehouse ID (0 = no warehouse filter)
	 * @param int    $variant_id  Variant ID (0 = no variant filter)
	 * @return string  Complete SQL SELECT query
	 */
	function fefo_batch_query($vare_id, $lager = 0, $variant_id = 0) {
		$qtxt = "SELECT * FROM batch_kob WHERE vare_id = '$vare_id' AND rest > 0";
		if ($lager) $qtxt .= " AND lager = '$lager'";
		if ($variant_id) $qtxt .= " AND variant_id = '$variant_id'";
		$qtxt .= " ORDER BY " . fefo_order_clause();
		return $qtxt;
	}
}

if (!function_exists('item_has_due_date')) {
	/**
	 * Checks whether an item has expiry date tracking enabled.
	 *
	 * @param int $vare_id  Item ID
	 * @return bool  True if the item has due_date tracking enabled
	 */
	function item_has_due_date($vare_id) {
		$qtxt = "SELECT has_due_date FROM varer WHERE id = '$vare_id'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		return ($r && ($r['has_due_date'] === 't' || $r['has_due_date'] === true || $r['has_due_date'] == 1));
	}
}

if (!function_exists('item_default_shelf_life')) {
	/**
	 * Returns the default shelf life in days for an item, or 0 if not set.
	 *
	 * @param int $vare_id  Item ID
	 * @return int  Default shelf life in days, or 0
	 */
	function item_default_shelf_life($vare_id) {
		$qtxt = "SELECT default_shelf_life_days FROM varer WHERE id = '$vare_id'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		return ($r && $r['default_shelf_life_days']) ? intval($r['default_shelf_life_days']) : 0;
	}
}

if (!function_exists('get_due_date_warning_days')) {
	/**
	 * Returns the number of days before expiry to warn the user.
	 * Uses the settings table with var_name='due_date_warning_days'.
	 * Falls back to 30 days if not configured.
	 *
	 * @param int $user_id  Optional user ID for per-user setting
	 * @return int  Number of warning days
	 */
	function get_due_date_warning_days($user_id = NULL) {
		return intval(get_settings_value('due_date_warning_days', 'lager', 30, $user_id));
	}
}

if (!function_exists('batch_expiry_status')) {
	/**
	 * Determines the expiry status of a batch.
	 *
	 * @param string|null $due_date  The due date (Y-m-d format) or NULL
	 * @param int $warning_days      Number of days for near-expiry warning
	 * @return string  'expired', 'warning', 'ok', or 'none' (no due_date)
	 */
	function batch_expiry_status($due_date, $warning_days = 30) {
		if (!$due_date) return 'none';
		$today = date('Y-m-d');
		$warning_date = date('Y-m-d', strtotime("+$warning_days days"));
		if ($due_date < $today) return 'expired';
		if ($due_date <= $warning_date) return 'warning';
		return 'ok';
	}
}

if (!function_exists('days_until_expiry')) {
	/**
	 * Calculates the number of days until a batch expires.
	 * Returns negative number if already expired.
	 *
	 * @param string|null $due_date  The due date (Y-m-d format) or NULL
	 * @return int|null  Days until expiry, or null if no due_date
	 */
	function days_until_expiry($due_date) {
		if (!$due_date) return null;
		$today = new DateTime(date('Y-m-d'));
		$expiry = new DateTime($due_date);
		$diff = $today->diff($expiry);
		return $diff->invert ? -$diff->days : $diff->days;
	}
}
?>
