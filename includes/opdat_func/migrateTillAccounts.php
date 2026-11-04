<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/migrateTillAccounts.php --- ver 5.1.0 --- 2026-11-04 ---
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
// 20261104 CL/ASR One-time move of the till account per currency from the tab list in grupper VK box4 to settings VK/till_account_<currency> per till, called from betweenUpdates.php.

if (!function_exists('migrateTillAccounts')) {
	/**
	 * Copies the till account per currency from the old tab list in grupper art 'VK' box4 (one entry per till,
	 * index kasse-1, written by the old POS settings page) into the settings rows the redesign reads
	 * (var_grp 'VK', var_name 'till_account_<currency code>', pos_id = till number, user_id 0), the way
	 * SettingsService stores Indstillinger > Kasse > Kasser (pos.tills.currency_account_<kodenr>).
	 *
	 * Decision 22 (2026-10-09) made box4 the "used in the till" flag ('1'/'0', valuta.php and the currency table
	 * in Indstillinger), and saving that flag overwrites a list. Without this copy the accounts would be lost the
	 * first time the currency is switched on or the currency row is saved, and the cash count would then report
	 * "Kontonr mangler". box4 itself is left as it is: findBoxSale() and posbogfor() read the settings key first
	 * and the list only while no key is stored, and changing box4 to '1' would make the till offer a currency the
	 * customer has not chosen (the till and Indstillinger only show currencies whose box4 is '1').
	 *
	 * Only entries that are an account number (digits, not 0) are copied; an empty position or the '0' of a
	 * switched-off flag is not an account. A till that already has a row for the currency (any value, also an
	 * empty one a user saved) keeps it. Runs once: the flag settings system/vk_till_accounts_migrated is written
	 * in the same transaction as the rows, under a lock, and read again once the lock is held.
	 *
	 * @param string $dbType $db_type from includes/connect.php ('postgresql', 'mysql' or 'mysqli').
	 * @return string 'done' when the copy is complete (now or earlier), 'busy' when the MySQL lock was not granted.
	 */
	function migrateTillAccounts($dbType) {
		$flagQuery = "SELECT id FROM settings WHERE var_grp = 'system' AND var_name = 'vk_till_accounts_migrated'";
		if (db_fetch_array(db_select($flagQuery, __FILE__ . " linje " . __LINE__))) {
			return 'done';
		}
		$mysql = in_array($dbType, array('mysql', 'mysqli'), true);
		if ($mysql) {
			// GET_LOCK is server-wide, so the name carries the database.
			$lockName = 'saldi_vktill_' . substr(md5((string) ($GLOBALS['db'] ?? '')), 0, 16);
			$locked = db_fetch_array(db_select("SELECT GET_LOCK('$lockName', 30) AS acquired", __FILE__ . " linje " . __LINE__));
			if ((int) ($locked['acquired'] ?? 0) !== 1) {
				return 'busy'; // the next login tries again
			}
			$unlock = "SELECT RELEASE_LOCK('$lockName')";
		} else {
			db_select("SELECT pg_advisory_lock(hashtext('saldi_vk_till_accounts'))", __FILE__ . " linje " . __LINE__);
			$unlock = "SELECT pg_advisory_unlock(hashtext('saldi_vk_till_accounts'))";
		}
		try {
			// Another login may have finished it while this one waited for the lock.
			if (db_fetch_array(db_select($flagQuery, __FILE__ . " linje " . __LINE__))) {
				return 'done';
			}
			transaktion('begin');
			$written = $kept = $groups = 0;
			$log = array();
			$vk = array();
			$q = db_select("SELECT kodenr, box1, box4 FROM grupper WHERE art = 'VK' ORDER BY id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$vk[] = $r;
			}
			foreach ($vk as $r) {
				$code = trim((string) $r['box1']);
				$list = (string) $r['box4'];
				if ($code === '' || in_array(trim($list), array('', '0', '1'), true)) {
					continue; // no list: nothing to copy, or already the flag
				}
				$groups++;
				foreach (explode(chr(9), $list) as $i => $entry) {
					$entry = trim($entry);
					if (!preg_match('/^[0-9]+$/', $entry) || (int) $entry === 0) {
						continue;
					}
					$till = $i + 1;
					$name = db_escape_string('till_account_' . $code);
					$qtxt = "SELECT id FROM settings WHERE var_grp = 'VK' AND var_name = '$name' AND pos_id = $till";
					if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
						$kept++;
						$log[] = "$code till $till kept";
						continue;
					}
					$description = db_escape_string('pos.tills.currency_account_' . (int) $r['kodenr'] . '.' . $till);
					$qtxt = "INSERT INTO settings (var_name, var_grp, var_value, var_description, user_id, pos_id) ";
					$qtxt.= "VALUES ('$name', 'VK', '" . db_escape_string($entry) . "', '$description', 0, $till)";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					$written++;
					$log[] = "$code till $till = $entry";
				}
			}
			$summary = "Till accounts per currency copied from VK box4 to VK/till_account_<code>: $written written, $kept kept, $groups lists";
			$qtxt = "INSERT INTO settings (var_name, var_grp, var_value, var_description, user_id) ";
			$qtxt.= "VALUES ('vk_till_accounts_migrated', 'system', '" . date('Ymd') . "', '" . db_escape_string($summary) . "', 0)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			transaktion('commit');
			if ($groups) {
				error_log('Saldi ' . ($GLOBALS['db'] ?? '') . ": $summary (" . implode(', ', $log) . ')');
			}
			return 'done';
		} finally {
			db_select($unlock, __FILE__ . " linje " . __LINE__);
		}
	}
}
