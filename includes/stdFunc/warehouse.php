<?php
//--- includes/stdFunc/warehouse.php ---ver 5.1.0 ----2026-10-30 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261021 CL/MJ SST-883 warehouseIsDefined() - is a warehouse number still one this account
//                  has? Order lines carry a warehouse from the moment they are written, so a
//                  document posted later can name one that has since been taken out of use.
// 20261027 CDX/MJ SST-883 Prefer the department warehouse mapping and validate both fallback mappings.
// 20261030 CL/MJ SST-883 Header marker set to the release this behaviour ships in (5.1.0).
// 20261030 CL/MJ SST-883 An account without any warehouses keeps every warehouse number, so levering()'s default warehouse 1 is not moved to 0 (CodeRabbit).

if (!function_exists('warehouseIsDefined')) {
	/**
	 * Is $lager a warehouse this account still has?
	 *
	 * Warehouse 0 is always accepted. An account with no grupper art='LG' rows at all accepts
	 * every number: levering() posts an order line without a warehouse to 1, and such an account
	 * has no warehouse columns where stock could go missing - only the total - so its postings
	 * stay exactly as they were.
	 *
	 * The lookup is not filtered by fiscal_year, which matches every other art='LG' read in the
	 * code - the warehouse columns on the item list (lager/lister/vareliste.php) and the rows on
	 * the product card (lager/varekort.php) are both unfiltered. Inventory is shared between
	 * fiscal years, so a warehouse defined in any of them is a real warehouse.
	 *
	 * @param mixed $lager The warehouse number, as it arrives from an order line or a form.
	 * @return bool
	 */
	function warehouseIsDefined($lager) {
		$lager = (int) $lager;
		if ($lager === 0) {
			return true;
		}
		$r = db_fetch_array(db_select(
			"select id from grupper where art='LG' and kodenr=$lager limit 1",
			__FILE__ . " linje " . __LINE__));
		if (!empty($r)) {
			return true;
		}
		$r = db_fetch_array(db_select(
			"select id from grupper where art='LG' limit 1",
			__FILE__ . " linje " . __LINE__));
		return empty($r);
	}
}

if (!function_exists('resolvePostingWarehouse')) {
	/**
	 * The warehouse stock should actually be posted to.
	 *
	 * An empty warehouse falls back to the order reference employee's department, which is what the
	 * posting code has always done. A warehouse that is set but no longer exists takes the same
	 * fallback rather than being used - that case is new, and it is the one that strands stock
	 * where no warehouse column can show it.
	 *
	 * Warehouse 0 is the last resort, as before: it is where an account without warehouses
	 * posts everything, so it is always a valid place to put stock.
	 *
	 * @param mixed $lager    The warehouse as it arrives from the order line or the form.
	 * @param mixed $vare_id  Item id, for the log line only.
	 * @param mixed $linje_id Order line id, for the log line only.
	 * @return int The warehouse to post to.
	 */
	function resolvePostingWarehouse($lager, $vare_id = 0, $linje_id = 0) {
		global $ref;

		# Set and still defined: nothing to do. The $lager test is kept because an empty
		# warehouse has always taken the department fallback below, and warehouseIsDefined()
		# accepts 0 - without it, an empty warehouse would stop looking up the department.
		if ($lager && warehouseIsDefined($lager)) {
			return (int) $lager;
		}
		$asked = (int) $lager;

		$resolved = 0;
		$employee = db_escape_string((string) ($ref ?? ''));
		if ($r = db_fetch_array(db_select(
				"select afd from ansatte where navn = '$employee'",
				__FILE__ . " linje " . __LINE__))) {
			$afd = (int) ifset($r, 'afd', 0);
			$r = db_fetch_array(db_select(
				"select box1 from grupper where kodenr='$afd' and art='AFD'",
				__FILE__ . " linje " . __LINE__));
			$resolved = (int) ifset($r, 'box1', 0);
			if (!$resolved || !warehouseIsDefined($resolved)) {
				$r = db_fetch_array(db_select(
					"select kodenr from grupper where box1='$afd' and art='LG'",
					__FILE__ . " linje " . __LINE__));
				$resolved = (int) ifset($r, 'kodenr', 0);
			}
		}
		# The department can point at a warehouse that was removed too.
		if (!warehouseIsDefined($resolved)) {
			$resolved = 0;
		}

		# Only worth a log line when a real warehouse number was asked for and could not be
		# honoured. An empty one is the ordinary case and says nothing.
		if ($asked !== 0) {
			error_log("saldi: warehouse $asked is not in grupper art='LG'; posting to $resolved "
				. "instead (vare_id $vare_id, ordrelinje $linje_id)");
		}
		return $resolved;
	}
}
