<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/commissionItemSeries.php --- ver 5.1.1 --- 2026-11-16 ---
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
// 20261116 CL/MMR SD-831 Created: when a kommission customer gets the account number of the last item in the shop's kommission series (e.g. kbsil4999) or a higher one, the next block of 5000 items (kbsil5000-kbsil9999, then 10000-14999 and so on) is created as copies of that item.

if (!defined('COMMISSION_ITEM_SERIES_BLOCK')) {
	// Morten 2026-11-16: at 4999 the shop gets 5000-9999, at 9999 10000-14999, at 14999 15000-19999, and so on.
	define('COMMISSION_ITEM_SERIES_BLOCK', 5000);
}

if (!function_exists('commissionItemSeriesEnsure')) {
	/**
	 * Create the next block of the kommission item series when a customer reaches its end.
	 *
	 * Kommission shops have one item per kommission customer: «kb» (used goods) or «kn» (new goods), a 3-character shop
	 * code and the customer's account number, e.g. kbsil4999. The items are made in advance as a series. When a customer
	 * is created with the account number of the series' last item or a higher one, the items up to the end of the next
	 * block of COMMISSION_ITEM_SERIES_BLOCK numbers are created (4999 gives 5000-9999, 9999 gives 10000-14999) as copies
	 * of the last item, without stand number («Varemærke»), offer dates, barcode, alias, notes and picture, and with
	 * stock 0. Numbers that already exist are skipped and not changed. Does nothing when commission is not enabled
	 * (settings useCommission) or the shop has no series for the prefix.
	 *
	 * @param int|string $accountNo The new customer's account number (adresser.kontonr).
	 * @return int Number of items created.
	 */
	function commissionItemSeriesEnsure($accountNo) {
		$accountNo = trim((string)$accountNo);
		if (!ctype_digit($accountNo) || strlen($accountNo) > 9 || (int)$accountNo < 1) {
			return 0;
		}
		$qtxt = "select var_value from settings where var_name = 'useCommission' and var_grp = 'items'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if (!$r || !$r['var_value']) {
			return 0;
		}
		if (!commissionItemSeriesLock()) {
			return 0;
		}
		$created = 0;
		foreach (array('kb', 'kn') as $prefix) {
			$created += commissionItemSeriesFill($prefix, (int)$accountNo);
		}
		commissionItemSeriesUnlock();
		return $created;
	}
}

if (!function_exists('commissionItemSeriesLastNo')) {
	/**
	 * The last number the series is filled up to: the end of the block of COMMISSION_ITEM_SERIES_BLOCK after the
	 * series' last item, or the end of the block that holds $accountNo when the customer is already beyond that.
	 *
	 * @param int $topNo     Number of the series' last item (e.g. 4999).
	 * @param int $accountNo The new customer's account number.
	 * @return int E.g. 9999 for 4999, 14999 for 9999, 9999 for 4964, 14999 for 4999 with account 12000.
	 */
	function commissionItemSeriesLastNo($topNo, $accountNo) {
		$block = COMMISSION_ITEM_SERIES_BLOCK;
		$lastNo = (int)ceil(($topNo + 1) / $block) * $block + $block - 1;
		if ($accountNo > $lastNo) {
			$lastNo = (int)ceil(($accountNo + 1) / $block) * $block - 1;
		}
		return $lastNo;
	}
}

if (!function_exists('commissionItemSeriesFill')) {
	/**
	 * Create the missing items of one series (kb or kn) after its last item, when $accountNo has reached that item.
	 *
	 * The series' last item is the item with the highest number among the items named <prefix><3 characters><digits>;
	 * its shop code is used for the new items.
	 *
	 * @param string $prefix    'kb' or 'kn'.
	 * @param int    $accountNo The new customer's account number.
	 * @return int Number of items created.
	 */
	function commissionItemSeriesFill($prefix, $accountNo) {
		if ($prefix !== 'kb' && $prefix !== 'kn') {
			return 0;
		}
		$existing = array();
		$topNo = 0;
		$topId = 0;
		$shopCode = '';
		$q = db_select("select id, varenr from varer where lower(varenr) like '$prefix%'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$itemNo = (string)$r['varenr'];
			$existing[strtolower($itemNo)] = true;
			if (preg_match('/^' . $prefix . '([^0-9]{3})([1-9][0-9]{0,8})$/i', $itemNo, $m) && (int)$m[2] > $topNo) {
				$topNo = (int)$m[2];
				$topId = (int)$r['id'];
				$shopCode = $m[1];
			}
		}
		if (!$topId || $accountNo < $topNo) {
			return 0;
		}
		$template = db_fetch_array(db_select("select * from varer where id = '$topId'", __FILE__ . " linje " . __LINE__));
		if (!$template) {
			return 0;
		}
		// Per-item data that must not be copied to the other customers' items; varenr and beholdning are set below.
		$skip = array('id', 'varenr', 'beholdning', 'trademark', 'stregkode', 'varenr_alias', 'beskrivelse_alias', 'notes',
			'notesinternal', 'fotonavn', 'modtime', 'special_from_date', 'special_to_date', 'special_from_time', 'special_to_time');
		$columns = array();
		$values = array();
		foreach ($template as $column => $value) {
			if (is_int($column) || in_array($column, $skip, true)) {
				continue;
			}
			$columns[] = $column;
			$values[] = ($value === null) ? 'NULL' : "'" . db_escape_string((string)$value) . "'";
		}
		$columnList = 'varenr, beholdning' . ($columns ? ', ' . implode(', ', $columns) : '');
		$copiedValues = $values ? ', ' . implode(', ', $values) : '';
		$lastNo = commissionItemSeriesLastNo($topNo, $accountNo);
		$created = 0;
		$rows = array();
		for ($n = $topNo + 1; $n <= $lastNo; $n++) {
			$itemNo = $prefix . $shopCode . $n;
			if (isset($existing[strtolower($itemNo)])) {
				continue;
			}
			$rows[] = "('" . db_escape_string($itemNo) . "', 0" . $copiedValues . ")";
			if (count($rows) == 250) {
				db_modify("insert into varer ($columnList) values " . implode(', ', $rows), __FILE__ . " linje " . __LINE__);
				$created += count($rows);
				$rows = array();
			}
		}
		if ($rows) {
			db_modify("insert into varer ($columnList) values " . implode(', ', $rows), __FILE__ . " linje " . __LINE__);
			$created += count($rows);
		}
		return $created;
	}
}

if (!function_exists('commissionItemSeriesLock')) {
	/**
	 * Take the lock that keeps two requests from creating the same series at the same time.
	 *
	 * @return bool True when the lock is held.
	 */
	function commissionItemSeriesLock() {
		global $db_type;
		if ($db_type == 'mysql' || $db_type == 'mysqli') {
			// GET_LOCK names are server-wide, so the database is part of the name
			$q = db_select("SELECT GET_LOCK(CONCAT('saldi:commissionItemSeries:', MD5(DATABASE())), 30) AS locked", __FILE__ . " linje " . __LINE__);
			$r = $q ? db_fetch_array($q) : null;
			return ($r && (int)$r['locked'] === 1);
		}
		return (bool)db_select("SELECT pg_advisory_lock(hashtext('saldi_commission_item_series'))", __FILE__ . " linje " . __LINE__);
	}
}

if (!function_exists('commissionItemSeriesUnlock')) {
	/**
	 * Release the lock taken by commissionItemSeriesLock().
	 */
	function commissionItemSeriesUnlock() {
		global $db_type;
		if ($db_type == 'mysql' || $db_type == 'mysqli') {
			db_select("SELECT RELEASE_LOCK(CONCAT('saldi:commissionItemSeries:', MD5(DATABASE())))", __FILE__ . " linje " . __LINE__);
		} else {
			db_select("SELECT pg_advisory_unlock(hashtext('saldi_commission_item_series'))", __FILE__ . " linje " . __LINE__);
		}
	}
}
