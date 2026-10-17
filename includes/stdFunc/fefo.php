<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/fefo.php --- ver 5.0.0 --- 2026-10-01 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// FEFO (First Expired, First Out) helper functions for expiry date handling.
// 20260923 CL/SZ Validate $vare_id as an integer in item_has_due_date() before interpolating it
//                 into the query (CodeRabbit, PR #608).
// 20260930 LOE SST-836 Added batch_expiry_in_use() for the expiry report's menu entry.
// 20261011 CL/ASR Added batch_missing_info_count() and batch_txt() for lager/batch_udfyld.php and its entry points.

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
	 * Checks whether an item has expiry date tracking enabled
	 * (varer.has_due_date, set via the "Varen har udl&oslash;bsdato" checkbox on the item card).
	 *
	 * @param int $vare_id  Item ID
	 * @return bool  True if the item has due_date tracking enabled
	 */
	function item_has_due_date($vare_id) {
		if (!is_scalar($vare_id) || filter_var($vare_id, FILTER_VALIDATE_INT) === false) {
			return false;
		}
		$vare_id = (int) $vare_id;
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

if (!function_exists('batch_expiry_in_use')) {
	/**
	 * Whether this account uses batch and expiry date handling at all, so the expiry report is only
	 * offered where it can show anything: the company setting is on, or at least one product group of
	 * the current fiscal year has batch control. Cached for the request - every menu surface calls it.
	 *
	 * @return bool True when batch and expiry date handling is in use in this account.
	 */
	function batch_expiry_in_use() {
		static $in_use = NULL;
		if ($in_use !== NULL) {
			return $in_use;
		}
		if (get_settings_value('batchExpiryEnabled', 'items', 'off') === 'on') {
			return $in_use = true;
		}
		/**
		 * Fiscal year in use, set by includes/online.php through the entry page before this runs.
		 * @var string $regnaar
		 */
		$regnaar = 0;
		if (isset($GLOBALS['regnaar'])) {
			$regnaar = (int)$GLOBALS['regnaar'];
		}
		if ($regnaar <= 0) {
			return $in_use = false;
		}
		$qtxt = "SELECT 1 FROM grupper WHERE art = 'VG' AND box9 = 'on' AND fiscal_year = '$regnaar' LIMIT 1";
		return $in_use = (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	}
}

if (!function_exists('batch_missing_info_count')) {
	/**
	 * Number of batches in stock that lack the details their item needs: no expiry date on an
	 * item with expiry tracking, or no batch number on an item with expiry tracking or a
	 * batch-controlled product group. Typically stock received before the feature was turned on.
	 *
	 * @param int $vare_id Item ID, or 0 for all items.
	 * @return int Number of batches with rest > 0 missing details.
	 */
	function batch_missing_info_count($vare_id = 0) {
		$regnaar = isset($GLOBALS['regnaar']) ? (int)$GLOBALS['regnaar'] : 0;
		$qtxt = "SELECT count(*) AS antal FROM batch_kob bk JOIN varer v ON v.id = bk.vare_id ";
		$qtxt .= "LEFT JOIN grupper g ON g.art = 'VG' AND g.kodenr = v.gruppe AND g.fiscal_year = '$regnaar' ";
		$qtxt .= "WHERE bk.rest > 0 AND (v.has_due_date = true OR g.box9 = 'on') ";
		$qtxt .= "AND ((v.has_due_date = true AND bk.due_date IS NULL) OR coalesce(bk.batch_no,'') = '')";
		if ((int)$vare_id) $qtxt .= " AND bk.vare_id = '" . (int)$vare_id . "'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		return $r ? (int)$r['antal'] : 0;
	}
}

if (!function_exists('batch_txt')) {
	/**
	 * Texts for the batch details page and its entry points, inline per language because the
	 * text ids in tekster.csv differ between installations (see productOptions.php).
	 *
	 * @param string $key Text key.
	 * @return string Text in the user's language (sprog_id 1 = da, 2 = en, 3 = no).
	 */
	function batch_txt($key) {
		static $t = array(
			'title'       => array('Batchoplysninger', 'Batch details', 'Batchopplysninger'),
			'expired'     => array('Formularen er udløbet. Genindlæs siden og prøv igen.', 'The form has expired. Reload the page and try again.', 'Skjemaet er utløpt. Last inn siden på nytt og prøv igjen.'),
			'gone'        => array('Et batch er solgt ud eller fjernet, mens siden var åben. Intet er gemt - indlæs siden igen.', 'A batch was sold out or removed while the page was open. Nothing was saved - reload the page.', 'En batch er utsolgt eller fjernet mens siden var åpen. Ingenting er lagret - last inn siden på nytt.'),
			'search'      => array('Søg på varenr. eller beskrivelse', 'Search item no. or description', 'Søk på varenr. eller beskrivelse'),
			'no_match'    => array('Ingen batches matcher søgningen.', 'No batches match the search.', 'Ingen batcher passer til søket.'),
			'showing'     => array('Viser $a-$b af $n', 'Showing $a-$b of $n', 'Viser $a-$b av $n'),
			'prev'        => array('Forrige', 'Previous', 'Forrige'),
			'next'        => array('Næste', 'Next', 'Neste'),
			'unsaved'     => array('Du har ændringer, der ikke er gemt. Vil du forlade siden?', 'You have unsaved changes. Leave the page?', 'Du har endringer som ikke er lagret. Vil du forlate siden?'),
			'bad_date'    => array('Ugyldig udløbsdato', 'Invalid expiry date', 'Ugyldig utløpsdato'),
			'bad_split'   => array('En opdeling skal have et antal over 0 og en gyldig dato', 'A split needs a quantity above 0 and a valid date', 'En oppdeling må ha et antall over 0 og en gyldig dato'),
			'max_split'   => array('Der kan højst opdeles', 'The most you can split off is', 'Det kan maksimalt deles opp'),
			'saved'       => array('Batchoplysningerne er gemt.', 'The batch details have been saved.', 'Batchopplysningene er lagret.'),
			'intro'       => array('Her kan du give varer, der allerede ligger på lager, batchnummer og udløbsdato. Partier uden udløbsdato sælges sidst og kommer ikke med på udløbsrapporten. Del et parti op, hvis det består af flere batches.', 'Here you can give stock already in the warehouse a batch number and expiry date. Lots without an expiry date are sold last and are left out of the expiry report. Split a lot if it consists of several batches.', 'Her kan du gi varer som allerede ligger på lager batchnummer og utløpsdato. Partier uten utløpsdato selges sist og kommer ikke med i utløpsrapporten. Del opp et parti hvis det består av flere batcher.'),
			'only_missing'=> array('Vis kun batches, der mangler oplysninger', 'Show only batches with missing details', 'Vis bare batcher som mangler opplysninger'),
			'show_all'    => array('Vis alle batches på lager', 'Show all batches in stock', 'Vis alle batcher på lager'),
			'none'        => array('Der er ingen batches, der mangler batchnummer eller udløbsdato.', 'No batches are missing a batch number or expiry date.', 'Ingen batcher mangler batchnummer eller utløpsdato.'),
			'suggest'     => array('Foreslå udløbsdato ud fra købsdato og standardholdbarhed', 'Suggest expiry date from purchase date and default shelf life', 'Foreslå utløpsdato ut fra kjøpsdato og standard holdbarhet'),
			'suggest_note'=> array('Forslagene gemmes først, når du trykker Gem.', 'The suggestions are only saved when you press Save.', 'Forslagene lagres først når du trykker Lagre.'),
			'reserved'    => array('Reserveret på åbne ordrer', 'Reserved on open orders', 'Reservert på åpne ordrer'),
			'split_title' => array('Del partiet op i flere batches', 'Split the lot into several batches', 'Del partiet opp i flere batcher'),
			'split'       => array('Opdel', 'Split', 'Del opp'),
			'quantity'    => array('Antal', 'Quantity', 'Antall'),
			'remove'      => array('Fjern', 'Remove', 'Fjern'),
			'new_batch'   => array('Ny batch', 'New batch', 'Ny batch'),
			'missing'     => array('batches på lager mangler udløbsdato eller batchnummer', 'batches in stock are missing an expiry date or batch number', 'batcher på lager mangler utløpsdato eller batchnummer'),
			'fill_now'    => array('Udfyld nu', 'Fill in now', 'Fyll ut nå'),
			'edit'        => array('Ret batchoplysninger', 'Edit batch details', 'Rediger batchopplysninger'),
			'fill_stock'  => array('Udfyld batchoplysninger for varer, der allerede er på lager', 'Fill in batch details for stock already in the warehouse', 'Fyll ut batchopplysninger for varer som allerede er på lager'),
		);
		$sprog = isset($GLOBALS['sprog_id']) ? (int)$GLOBALS['sprog_id'] : 1;
		$i = ($sprog == 2) ? 1 : (($sprog == 3) ? 2 : 0);
		return isset($t[$key]) ? $t[$key][$i] : $key;
	}
}
?>
