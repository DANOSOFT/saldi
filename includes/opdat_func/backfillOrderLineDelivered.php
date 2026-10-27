<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/backfillOrderLineDelivered.php --- patch 5.0.0 --- 2026-10-27 ---
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
// Copyright (c) 2026 Danosoft.ApS
// ----------------------------------------------------------------------
// 20261027 CL/LH MB-73 (review): the one-time leveret backfill from betweenUpdates.php, in id windows under a
//                try-lock and a time budget per login, so a large tenant's first login no longer waits minutes.

if (!function_exists('backfillOrderLineDelivered')) {
	/**
	 * One-time backfill of NULL ordrelinjer.leveret (MB-73) on sales, credit note and POS lines that have
	 * delivery records: leveret becomes the batch_salg sum for the line, order and item, as debitor/ordre.php
	 * shows it. Lines without delivery records (not delivered, text lines) and purchase lines are left alone.
	 *
	 * Runs at login, so it is bounded: it walks ordrelinjer.id in windows of $windowSize ids, each window one
	 * UPDATE that reads batch_salg by the same linje_id range (batch_salg_linje_id_idx) and ordrelinjer by its
	 * primary key, and it stops starting new windows once $budgetSeconds have passed. The next window is kept in
	 * settings (var_grp 'system', var_name 'ordrelinjer_leveret_backfill_id'), so the next login carries on;
	 * when the last window is done the flag 'ordrelinjer_leveret_backfilled' is written and the cursor removed.
	 * A login that finds another login running the backfill skips it instead of waiting for it.
	 *
	 * The correlated form this replaces could probe batch_salg through batch_salg_vare_id_idx for every line;
	 * without planner statistics on a POS tenant with a few items sold on most receipts, that took minutes.
	 *
	 * Lines created after the column got DEFAULT 0 are never NULL, so the cursor only has to reach the
	 * highest id once. A line delivered while it still waits for its window is not NULL afterwards, so it is
	 * skipped here; linjeopdat() (includes/ordrefunc.php) starts such a line from these same batch_salg records,
	 * so its leveret already counts the earlier deliveries.
	 *
	 * @param string $dbType        $db_type from includes/connect.php ('postgresql', 'mysql' or 'mysqli').
	 * @param float  $budgetSeconds No new window is started after this many seconds.
	 * @param int    $windowSize    Number of ordrelinjer ids per UPDATE.
	 * @return string 'done' when the backfill is complete (now or earlier), 'continued' when the budget ran out
	 *                and a later login carries on, 'busy' when another login holds the backfill lock.
	 */
	function backfillOrderLineDelivered($dbType, $budgetSeconds = 1.0, $windowSize = 20000) {
		$started = microtime(true);
		$windowSize = max(1, (int)$windowSize);
		$flagQuery = "SELECT var_value FROM settings WHERE var_name = 'ordrelinjer_leveret_backfilled' AND var_grp = 'system'";
		if (db_fetch_array(db_select($flagQuery, __FILE__ . " linje " . __LINE__))) {
			return 'done';
		}
		$mysql = in_array($dbType, array('mysql', 'mysqli'), true);
		$lockName = $mysql ? "CONCAT('saldi:ordrelinjer_leveret:', MD5(DATABASE()))" : "hashtext('ordrelinjer_leveret_backfill')";
		if ($mysql) {
			$lock = db_fetch_array(db_select("SELECT GET_LOCK($lockName, 0) AS locked", __FILE__ . " linje " . __LINE__));
			$locked = (int)($lock['locked'] ?? 0) === 1;
		} else {
			$lock = db_fetch_array(db_select("SELECT pg_try_advisory_lock($lockName) AS locked", __FILE__ . " linje " . __LINE__));
			$locked = $lock && $lock['locked'] === 't';
		}
		if (!$locked) {
			return 'busy';
		}
		try {
			// Another login may have finished the backfill between the check above and the lock
			if (db_fetch_array(db_select($flagQuery, __FILE__ . " linje " . __LINE__))) {
				return 'done';
			}
			$cursorWhere = "var_name = 'ordrelinjer_leveret_backfill_id' AND var_grp = 'system'";
			$cursor = db_fetch_array(db_select("SELECT var_value FROM settings WHERE $cursorWhere", __FILE__ . " linje " . __LINE__));
			if ($cursor) {
				$from = (int)$cursor['var_value'];
			} else {
				$from = 0;
				$qtxt = "INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ";
				$qtxt.= "('ordrelinjer_leveret_backfill_id', 'system', '0', 'Next ordrelinjer.id window of the leveret backfill (MB-73)')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
			$max = db_fetch_array(db_select("SELECT MAX(id) AS id FROM ordrelinjer", __FILE__ . " linje " . __LINE__));
			$maxId = (int)($max['id'] ?? 0);
			while ($from < $maxId) {
				$to = $from + $windowSize;
				$delivered = "SELECT batch_salg.linje_id, batch_salg.ordre_id, batch_salg.vare_id, SUM(batch_salg.antal) AS antal FROM batch_salg";
				$delivered.= " WHERE batch_salg.linje_id > $from AND batch_salg.linje_id <= $to";
				$delivered.= " GROUP BY batch_salg.linje_id, batch_salg.ordre_id, batch_salg.vare_id";
				$match = "ordrelinjer.id = delivered.linje_id AND ordrelinjer.ordre_id = delivered.ordre_id AND ordrelinjer.vare_id = delivered.vare_id";
				$where = "ordrelinjer.id > $from AND ordrelinjer.id <= $to AND ordrelinjer.leveret IS NULL";
				$where.= " AND ordrelinjer.ordre_id IN (SELECT ordrer.id FROM ordrer WHERE ordrer.art IN ('DO', 'DK', 'PO'))";
				if ($mysql) {
					$qtxt = "UPDATE ordrelinjer JOIN ($delivered) AS delivered ON $match";
					$qtxt.= " SET ordrelinjer.leveret = COALESCE(delivered.antal, 0) WHERE $where";
				} else {
					$qtxt = "UPDATE ordrelinjer SET leveret = COALESCE(delivered.antal, 0) FROM ($delivered) AS delivered";
					$qtxt.= " WHERE $match AND $where";
				}
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				db_modify("UPDATE settings SET var_value = '$to' WHERE $cursorWhere", __FILE__ . " linje " . __LINE__);
				$from = $to;
				if ($from < $maxId && microtime(true) - $started >= $budgetSeconds) {
					return 'continued';
				}
			}
			$qtxt = "INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ";
			$qtxt.= "('ordrelinjer_leveret_backfilled', 'system', 'yes', 'One-time backfill of NULL ordrelinjer.leveret from batch_salg (MB-73)')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			db_modify("DELETE FROM settings WHERE $cursorWhere", __FILE__ . " linje " . __LINE__);
			return 'done';
		} finally {
			if ($mysql) {
				db_select("SELECT RELEASE_LOCK($lockName)", __FILE__ . " linje " . __LINE__);
			} else {
				db_select("SELECT pg_advisory_unlock($lockName)", __FILE__ . " linje " . __LINE__);
			}
		}
	}
}
