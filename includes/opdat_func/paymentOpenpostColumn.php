<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/paymentOpenpostColumn.php --- ver 5.1.0 --- 2026-11-05 ---
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
// 20261105 CL/LH betalinger.openpost_id, the open post a payment on a payment list pays, added once, called from betweenUpdates.php.

if (!function_exists('addPaymentOpenpostColumn')) {
	/**
	 * Adds betalinger.openpost_id: the id of the open post a payment on a payment list pays. Afregn kommission
	 * (debitor/pos_ordre_includes/settleCommission/moveToCustomerAccount.php) and Fra liste (debitor/betalinger.php)
	 * write it for an Afr open post without a kassekladde, and Fra liste leaves out an open post a payment carries.
	 * Both used to link the two through the open post's refnr in ordre_id: the refnr is the cash count's report number,
	 * which every seller of the report shares, and the payment list and its XML export read ordre_id as an order.
	 *
	 * Runs at every login until the column is there; two first logins at once both add it without an error.
	 *
	 * @param string $dbType $db_type from includes/connect.php ('postgresql', 'mysql' or 'mysqli').
	 * @return string 'added', 'present', 'no table' (a database without betalinger) or 'busy' (the MySQL lock was
	 *                not granted; the next login tries again).
	 */
	function addPaymentOpenpostColumn($dbType) {
		$mysql = in_array($dbType, array('mysql', 'mysqli'), true);
		$schema = $mysql ? "table_schema = DATABASE()" : "table_schema = current_schema()";
		$columns = function () use ($schema) {
			$qtxt = "SELECT column_name AS col FROM information_schema.columns WHERE table_name = 'betalinger' AND $schema";
			$found = array();
			$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$found[] = strtolower((string) $r['col']);
			}
			return $found;
		};
		$found = $columns();
		if (!$found) {
			return 'no table';
		}
		if (in_array('openpost_id', $found, true)) {
			return 'present';
		}
		if (!$mysql) {
			// A second login waits for the first one's ALTER and then finds the column.
			db_modify("ALTER TABLE betalinger ADD COLUMN IF NOT EXISTS openpost_id INTEGER", __FILE__ . " linje " . __LINE__);
			return 'added';
		}
		// MySQL has no ADD COLUMN IF NOT EXISTS. GET_LOCK is server-wide, so the name carries the database.
		$lockName = "CONCAT('saldi:payopenpost:', MD5(DATABASE()))";
		$locked = db_fetch_array(db_select("SELECT GET_LOCK($lockName, 30) AS acquired", __FILE__ . " linje " . __LINE__));
		if ((int) ($locked['acquired'] ?? 0) !== 1) {
			return 'busy';
		}
		try {
			// Another login may have added it while this one waited for the lock.
			if (in_array('openpost_id', $columns(), true)) {
				return 'present';
			}
			db_modify("ALTER TABLE betalinger ADD COLUMN openpost_id INTEGER", __FILE__ . " linje " . __LINE__);
			return 'added';
		} finally {
			db_select("SELECT RELEASE_LOCK($lockName)", __FILE__ . " linje " . __LINE__);
		}
	}
}
