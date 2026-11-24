<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/fixStdItemGroupAccounts.php --- ver 5.1.1 --- 2026-11-13 ---
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
// 20261113 CL/ASR One-time repair of the EU and non-EU accounts of the standard item groups (grupper art 'VG', box11..box14), called from betweenUpdates.php.
//                 Seeded accounts the standard chart lacks (1220, 1105, 1250) and the EU sales account 1050 seeded as the EU purchase account become the standard chart's accounts, per fiscal year.
//                 A cell is only changed when the replacement is the standard chart's own account in that year, and cells a guard keeps are logged.
//                 Freight takes the goods accounts 1050/1100: freight the seller charges with the goods is part of the goods supply (ML § 27, stk. 3, nr. 2).

if (!function_exists('stdItemGroupAccountIsStandard')) {
	/**
	 * Whether an account of a fiscal year is the standard chart's own account (importfiler/kontoplan.txt) with that
	 * number: an operating account ('D') that is not closed, under the standard name, and for a sales account without
	 * a VAT code. The number alone proves nothing: on other charts 1050, 1150 and 1200 are unrelated accounts, some
	 * with sales VAT, and an EU or export sale booked there would be charged VAT.
	 *
	 * The name is compared on its letters a-z, digits and commas only, after HTML entities are decoded, so the
	 * database encoding of "Varekøb" does not matter.
	 *
	 * @param array<int, array{0: string, 1: string, 2: string, 3: string}|null> $accounts kontotype, moms, beskrivelse
	 *        and lukket by kontonr for the year's accounts; null where a number occurs twice in the year.
	 * @param int $kontonr 1050, 1100, 1150, 1200 or 1630.
	 */
	function stdItemGroupAccountIsStandard($accounts, $kontonr) {
		$standard = array(
			1050 => array('salgafvarerudland,eu', true),
			1100 => array('salgafvarerudland,ikkeeu', true),
			1150 => array('salgafydelserudland,eu', true),
			1200 => array('salgafydelserudland,ikkeeu', true),
			1630 => array('varekbudland,eu', false),
		);
		if (!isset($standard[$kontonr]) || !isset($accounts[$kontonr])) {
			return false; // missing, or the number occurs twice in the year
		}
		list($kontotype, $moms, $beskrivelse, $lukket) = $accounts[$kontonr];
		if (strtoupper(trim($kontotype)) !== 'D' || trim($lukket) === 'on') {
			return false;
		}
		if ($standard[$kontonr][1] && trim($moms) !== '') {
			return false; // an EU or export sale would be charged this VAT code
		}
		$name = preg_replace('/[^a-z0-9,]+/', '', strtolower(html_entity_decode($beskrivelse, ENT_QUOTES, 'UTF-8')));
		return $name === $standard[$kontonr][0];
	}
}

if (!function_exists('stdItemGroupAccountsTarget')) {
	/**
	 * Decides the new EU and non-EU accounts of one item group (grupper art 'VG') from its fiscal year's chart.
	 *
	 * importfiler/grupper.txt seeded accounts the standard chart has never had: box12 (sales to EU customers) 1220 and
	 * 1105 and box14 (sales outside the EU) 1250. includes/ordrefunc.php batch_salg() puts them on the invoice line and
	 * bogfor_nu() posts it without a check, so the sale lands on an account no report shows. It also seeded box11
	 * (purchases from EU suppliers, kreditor/bogfor.php) as 1050 Salg af varer udland, EU, so goods bought in the EU are
	 * booked against EU sales. The replacements are the standard chart's accounts the seeded Momsrapport row (grupper 'MR') already
	 * names: 1220 -> 1050, 1105 -> 1050 (freight the seller charges with the goods is part of the goods supply, ML § 27,
	 * stk. 3, nr. 2, so the freight group sells on the goods accounts), 1250 -> 1100 on a freight or goods row (box12 1105,
	 * 1050 or 1220) and 1250 -> 1200 otherwise, and box11 1050 -> 1630.
	 *
	 * A cell changes only when it still holds the bad default, the replacement is the standard chart's own account in
	 * the year (stdItemGroupAccountIsStandard()), and for 1220, 1105 and 1250 when that account is not in the year's
	 * chart: a company that created it books there on purpose. box11 1050 changes only on a row with all four seeded
	 * accounts (1050, 1220, 1650, 1100) where 1050 is the standard EU sales account, so a hand-made group is left alone.
	 *
	 * A bad default is reported as kept when it still sends postings astray: its account is not in the chart and no
	 * standard replacement is there, or box11 is the standard EU sales account 1050 on a row the guard cannot vouch for.
	 * An account the company has in its chart under that number is its own choice and is not reported.
	 *
	 * @param array{box11: string, box12: string, box13: string, box14: string} $cells The row's box11..box14, trimmed.
	 * @param array<int, array{0: string, 1: string, 2: string, 3: string}|null> $accounts kontotype, moms, beskrivelse
	 *        and lukket by kontonr for the year's accounts 1050, 1100, 1105, 1150, 1200, 1220, 1250 and 1630; null where a
	 *        number occurs twice in the year.
	 * @return array{0: array<string, string>, 1: string[]} The new values by column, and why a bad default that still
	 *         needs a hand was kept.
	 */
	function stdItemGroupAccountsTarget($cells, $accounts) {
		$changes = array();
		$kept = array();
		// A freight or goods row (box12 the freight seed 1105 or a goods EU account) sells outside the EU on the goods account.
		$goodsRow = in_array($cells['box12'], array('1105', '1050', '1220'), true);
		$missing = array(
			'box12' => array('1220' => 1050, '1105' => 1050),
			'box14' => array('1250' => $goodsRow ? 1100 : 1200),
		);
		foreach ($missing as $col => $map) {
			if (!isset($map[$cells[$col]])) {
				continue;
			}
			$from = $cells[$col];
			$to = $map[$from];
			if (array_key_exists((int) $from, $accounts)) {
				continue; // the company has this account: its own choice
			}
			if (stdItemGroupAccountIsStandard($accounts, $to)) {
				$changes[$col] = (string) $to;
			} else {
				$kept[] = "$col $from kept ($from is not in the chart, $to is not the standard account)";
			}
		}
		if ($cells['box11'] === '1050') {
			// Another account 1050 in the chart is the company's own choice and is left alone.
			if (!array_key_exists(1050, $accounts)) {
				$kept[] = 'box11 1050 kept (1050 is not in the chart)';
			} elseif (stdItemGroupAccountIsStandard($accounts, 1050)) {
				if (array($cells['box12'], $cells['box13'], $cells['box14']) !== array('1220', '1650', '1100')) {
					$kept[] = 'box11 1050 kept (the EU sales account, on a row that is not the seeded one)';
				} elseif (!stdItemGroupAccountIsStandard($accounts, 1630)) {
					$kept[] = 'box11 1050 kept (the EU sales account, 1630 is not the standard account)';
				} else {
					$changes['box11'] = '1630';
				}
			}
		}
		return array($changes, $kept);
	}
}

if (!function_exists('fixStdItemGroupAccounts')) {
	/**
	 * Repairs the EU and non-EU accounts (box11..box14) of the item groups seeded from importfiler/grupper.txt, once.
	 *
	 * Only rows holding one of the bad defaults are candidates (box11 1050, box12 1220 or 1105, box14 1250). Every row
	 * is judged against its own fiscal year's chart (kontoplan.regnskabsaar = grupper.fiscal_year) by
	 * stdItemGroupAccountsTarget(); a year without accounts of its own is judged by the nearest earlier year that has
	 * accounts, or else the latest one. Postings already made are not touched. The rows are read first and updated by id
	 * with their stored values in the WHERE, so a row saved by hand since it was read is left alone, and MySQL is not
	 * asked for an UPDATE that selects from its own table. Runs once: the flag settings system/std_item_group_accounts
	 * is written in the same transaction as the rows, under a lock, and read again once the lock is held. Changed and
	 * kept cells are logged, so a group that still needs a hand can be found.
	 *
	 * @param string $dbType $db_type from includes/connect.php ('postgresql', 'mysql' or 'mysqli').
	 * @return string 'done' when the repair is complete (now or earlier), 'busy' when the MySQL lock was not granted,
	 *                'failed' when the transaction did not keep the flag (the next login tries again).
	 */
	function fixStdItemGroupAccounts($dbType) {
		$flagQuery = "SELECT id FROM settings WHERE var_grp = 'system' AND var_name = 'std_item_group_accounts'";
		if (db_fetch_array(db_select($flagQuery, __FILE__ . " linje " . __LINE__))) {
			return 'done';
		}
		$db = isset($GLOBALS['db']) ? (string) $GLOBALS['db'] : '';
		if (in_array($dbType, array('mysql', 'mysqli'), true)) {
			// GET_LOCK is server-wide, so the name carries the database.
			$lockName = 'saldi_vgaccounts_' . substr(md5($db), 0, 16);
			$locked = db_fetch_array(db_select("SELECT GET_LOCK('$lockName', 30) AS acquired", __FILE__ . " linje " . __LINE__));
			if (!$locked || (int) $locked['acquired'] !== 1) {
				return 'busy'; // the next login tries again
			}
			$unlock = "SELECT RELEASE_LOCK('$lockName')";
		} else {
			db_select("SELECT pg_advisory_lock(hashtext('saldi_std_item_group_accounts'))", __FILE__ . " linje " . __LINE__);
			$unlock = "SELECT pg_advisory_unlock(hashtext('saldi_std_item_group_accounts'))";
		}
		try {
			// Another login may have finished it while this one waited for the lock.
			if (db_fetch_array(db_select($flagQuery, __FILE__ . " linje " . __LINE__))) {
				return 'done';
			}
			transaktion('begin');
			$rows = array();
			$qtxt = "SELECT id, kodenr, box11, box12, box13, box14, fiscal_year FROM grupper WHERE art = 'VG' ORDER BY id";
			$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$rows[] = $r;
			}
			$years = array(); // the accounts a fiscal year is judged by
			$chartYears = null; // the fiscal years that have accounts, read once a row needs them
			$changed = $kept = 0;
			$log = array();
			foreach ($rows as $r) {
				$cells = array();
				foreach (array('box11', 'box12', 'box13', 'box14') as $col) {
					$cells[$col] = trim((string) $r[$col]);
				}
				if ($cells['box11'] !== '1050' && !in_array($cells['box12'], array('1220', '1105'), true) && $cells['box14'] !== '1250') {
					continue; // no bad default
				}
				$year = (int) $r['fiscal_year'];
				if (!isset($years[$year])) {
					if ($chartYears === null) {
						$chartYears = array();
						$q = db_select("SELECT regnskabsaar FROM kontoplan GROUP BY regnskabsaar", __FILE__ . " linje " . __LINE__);
						while ($k = db_fetch_array($q)) {
							$chartYears[] = (int) $k['regnskabsaar'];
						}
						sort($chartYears);
					}
					$chartYear = null; // the year itself, else the nearest earlier year with accounts, else the latest
					foreach ($chartYears as $candidate) {
						if ($candidate <= $year) {
							$chartYear = $candidate;
						}
					}
					if ($chartYear === null && $chartYears) {
						$chartYear = end($chartYears);
					}
					$accounts = array();
					if ($chartYear !== null) {
						$qtxt = "SELECT kontonr, kontotype, moms, beskrivelse, lukket FROM kontoplan WHERE regnskabsaar = " . (int) $chartYear . " ";
						$qtxt.= "AND kontonr IN (1050, 1100, 1105, 1150, 1200, 1220, 1250, 1630)";
						$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
						while ($k = db_fetch_array($q)) {
							$kontonr = (int) $k['kontonr'];
							// A number that occurs twice in a year exists, but is nobody's standard account.
							$accounts[$kontonr] = array_key_exists($kontonr, $accounts) ? null : array((string) $k['kontotype'],
								(string) $k['moms'], (string) $k['beskrivelse'], (string) $k['lukket']);
						}
					}
					$years[$year] = $accounts;
				}
				list($changes, $reasons) = stdItemGroupAccountsTarget($cells, $years[$year]);
				$label = "year $year group " . (int) $r['kodenr'];
				foreach ($reasons as $reason) {
					$kept++;
					$log[] = "$label $reason";
				}
				if (!$changes) {
					continue;
				}
				$set = $where = $done = array();
				foreach ($changes as $col => $value) {
					$set[] = "$col = '" . db_escape_string($value) . "'";
					$done[] = "$col $cells[$col] -> $value";
				}
				// The stored values in the WHERE leave a row alone that was saved by hand since it was read.
				foreach (array('box11', 'box12', 'box13', 'box14') as $col) {
					$where[] = $r[$col] === null ? "$col IS NULL" : "$col = '" . db_escape_string((string) $r[$col]) . "'";
				}
				$qtxt = "UPDATE grupper SET " . implode(', ', $set) . " WHERE id = " . (int) $r['id'] . " AND art = 'VG' AND " . implode(' AND ', $where);
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$changed += count($changes);
				$log[] = "$label " . implode(', ', $done);
			}
			$summary = "Item group EU/non-EU accounts (grupper VG box11..box14) seeded as 1050/1220/1105/1250: $changed changed, $kept kept by a guard";
			$qtxt = "INSERT INTO settings (var_name, var_grp, var_value, var_description, user_id) ";
			$qtxt.= "VALUES ('std_item_group_accounts', 'system', '" . date('Ymd') . "', '" . db_escape_string($summary) . "', 0)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			transaktion('commit');
			// A failed statement rolls the whole transaction back on Postgres, the flag with it.
			if (!db_fetch_array(db_select($flagQuery, __FILE__ . " linje " . __LINE__))) {
				error_log("Saldi $db: item group account repair (grupper VG) was rolled back; the next login tries again");
				return 'failed';
			}
			if ($log) {
				error_log("Saldi $db: $summary (" . implode('; ', $log) . ')');
			}
			return 'done';
		} finally {
			db_select($unlock, __FILE__ . " linje " . __LINE__);
		}
	}
}
