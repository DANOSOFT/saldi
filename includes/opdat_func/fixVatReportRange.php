<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/fixVatReportRange.php --- ver 5.1.1 --- 2026-11-13 ---
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
// 20261113 CL/ASR One-time repair of the VAT report range in grupper art 'MR' (box1..box2), called from betweenUpdates.php.
//                 The seeded 6780..7740, a digit swap of 7680, becomes 7680..7830 per fiscal year on the standard chart of accounts.
//                 A year without accounts of its own is judged by the nearest earlier year that has them, and rows a guard keeps are logged.
if (!function_exists('vatReportRangeTarget')) {
	/**
	 * Decides the new VAT report range for one seeded grupper 'MR' row (6780..7740 or 7680..7740) from its fiscal year.
	 *
	 * The row is only changed on the standard chart, recognised by 7680 Salgsmoms being a balance account ('S').
	 * The start 6780 becomes 7680 unless a VAT code of the year books to an account in 6780..7679: that account
	 * would drop out of the report, so the row is then left as it is. The end 7740 becomes 7830 only when every
	 * account of the year in 7741..7830 is one of the duty accounts 7760, 7780, 7800, 7810, 7820 and 7830; any
	 * other account there (any type) would be added to the VAT return. 7840 Skyldig moms, the settlement of the
	 * period, stays outside the range: the last duty account, 7830, is the end.
	 *
	 * @param string $box1 The row's box1, trimmed: '6780' or '7680'.
	 * @param array<int, string> $accounts kontotype ('S', 'D', 'H', 'Z', ...) by kontonr for the year's accounts 7680..7830.
	 * @param int[] $vatAccounts The accounts the year's VAT codes book to (SM/KM/EM/YM box1, EM/YM box3).
	 * @return array{0: string, 1: string}|null The new box1 and box2, or null to leave the row as it is.
	 */
	function vatReportRangeTarget($box1, $accounts, $vatAccounts) {
		if (!isset($accounts[7680]) || $accounts[7680] !== 'S') {
			return null; // not the standard chart
		}
		if ($box1 === '6780') {
			foreach ($vatAccounts as $account) {
				if ($account >= 6780 && $account <= 7679) {
					return null; // a VAT code of this year books below 7680
				}
			}
		}
		$duties = array(7760, 7780, 7800, 7810, 7820, 7830);
		$extend = true;
		foreach ($accounts as $kontonr => $kontotype) {
			if ($kontonr >= 7741 && $kontonr <= 7830 && !in_array($kontonr, $duties, true)) {
				$extend = false;
			}
		}
		$target = array('7680', $extend ? '7830' : '7740');
		return $target === array($box1, '7740') ? null : $target;
	}
}

if (!function_exists('fixVatReportRange')) {
	/**
	 * Repairs the VAT report range (grupper art 'MR', box1..box2) that importfiler/grupper.txt seeded as 6780..7740,
	 * a digit swap of 7680. Finans > Rapporter > Momsangivelse and the dashboard's VAT figure add up every account in
	 * the range, and on the standard chart 6780..7740 also takes in the reserves, the debt accounts, 7440 Leverandører
	 * and their sum accounts. The owner's range is 7680..7830: the VAT accounts 7680 to 7740 and the duty accounts 7760
	 * to 7830, the fields of the VAT return. 7840 Skyldig moms, the settlement, stays out.
	 *
	 * Only the seeded ranges 6780..7740 and 7680..7740 are candidates; any other range was set by hand. The MR row of
	 * every fiscal year is judged against that year's chart of accounts (kontoplan.regnskabsaar = grupper.fiscal_year)
	 * and VAT codes by vatReportRangeTarget(). A year without accounts of its own (its grupper rows exist before the year
	 * is created) is judged by the nearest earlier year that has accounts, or else the latest one, and by that year's VAT
	 * codes when it has none itself: otherwise the flag would leave its typo for good. The rows are read first and updated
	 * by id, because MySQL refuses an UPDATE that selects from its own table. Runs once: the flag settings
	 * system/vat_report_range_7680 is written in the same transaction as the rows, under a lock, and read again once the
	 * lock is held. Rows a guard keeps are logged as well, so a range that still needs a hand can be found.
	 *
	 * @param string $dbType $db_type from includes/connect.php ('postgresql', 'mysql' or 'mysqli').
	 * @return string 'done' when the repair is complete (now or earlier), 'busy' when the MySQL lock was not granted,
	 *                'failed' when the transaction did not keep the flag (the next login tries again).
	 */
	function fixVatReportRange($dbType) {
		$flagQuery = "SELECT id FROM settings WHERE var_grp = 'system' AND var_name = 'vat_report_range_7680'";
		if (db_fetch_array(db_select($flagQuery, __FILE__ . " linje " . __LINE__))) {
			return 'done';
		}
		$db = isset($GLOBALS['db']) ? (string) $GLOBALS['db'] : '';
		if (in_array($dbType, array('mysql', 'mysqli'), true)) {
			// GET_LOCK is server-wide, so the name carries the database.
			$lockName = 'saldi_vatrange_' . substr(md5($db), 0, 16);
			$locked = db_fetch_array(db_select("SELECT GET_LOCK('$lockName', 30) AS acquired", __FILE__ . " linje " . __LINE__));
			if (!$locked || (int) $locked['acquired'] !== 1) {
				return 'busy'; // the next login tries again
			}
			$unlock = "SELECT RELEASE_LOCK('$lockName')";
		} else {
			db_select("SELECT pg_advisory_lock(hashtext('saldi_vat_report_range'))", __FILE__ . " linje " . __LINE__);
			$unlock = "SELECT pg_advisory_unlock(hashtext('saldi_vat_report_range'))";
		}
		try {
			// Another login may have finished it while this one waited for the lock.
			if (db_fetch_array(db_select($flagQuery, __FILE__ . " linje " . __LINE__))) {
				return 'done';
			}
			transaktion('begin');
			$rows = array();
			$q = db_select("SELECT id, box1, box2, fiscal_year FROM grupper WHERE art = 'MR' ORDER BY id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$rows[] = $r;
			}
			$years = array();
			$chartYears = null; // the fiscal years that have accounts, read once a row needs them
			$changed = $kept = 0;
			$log = array();
			foreach ($rows as $r) {
				$box1 = trim((string) $r['box1']);
				$box2 = trim((string) $r['box2']);
				if ($box2 !== '7740' || !in_array($box1, array('6780', '7680'), true)) {
					continue; // set by hand, or already right
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
					if ($chartYear === null) {
						$years[$year] = array(array(), array()); // no accounts at all: the standard-chart guard keeps the row
					}
				}
				if (!isset($years[$year])) {
					$accounts = array();
					$qtxt = "SELECT kontonr, kontotype FROM kontoplan WHERE regnskabsaar = $chartYear AND kontonr >= 7680 AND kontonr <= 7830";
					$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
					while ($k = db_fetch_array($q)) {
						$kontonr = (int) $k['kontonr'];
						$kontotype = strtoupper(trim((string) $k['kontotype']));
						if (!isset($accounts[$kontonr]) || $kontotype === 'S') {
							$accounts[$kontonr] = $kontotype;
						}
					}
					$vatAccounts = array();
					$vatYears = $chartYear === $year ? array($year) : array($year, $chartYear); // the year's own codes first
					foreach ($vatYears as $vatYear) {
						$qtxt = "SELECT art, box1, box3 FROM grupper WHERE fiscal_year = $vatYear AND art IN ('SM', 'KM', 'EM', 'YM')";
						$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
						$codes = 0;
						while ($g = db_fetch_array($q)) {
							$codes++;
							$boxes = in_array($g['art'], array('EM', 'YM'), true) ? array($g['box1'], $g['box3']) : array($g['box1']);
							foreach ($boxes as $box) {
								$box = trim((string) $box);
								if (ctype_digit($box)) {
									$vatAccounts[] = (int) $box;
								}
							}
						}
						if ($codes) {
							break;
						}
					}
					$years[$year] = array($accounts, $vatAccounts);
				}
				$target = vatReportRangeTarget($box1, $years[$year][0], $years[$year][1]);
				if ($target === null) {
					$kept++;
					$log[] = "year $year $box1..$box2 kept";
					continue;
				}
				// The stored values in the WHERE leave a row alone that was saved by hand since it was read.
				$qtxt = "UPDATE grupper SET box1 = '" . db_escape_string($target[0]) . "', box2 = '" . db_escape_string($target[1]) . "' ";
				$qtxt.= "WHERE id = " . (int) $r['id'] . " AND art = 'MR' AND box1 = '" . db_escape_string((string) $r['box1']) . "' ";
				$qtxt.= "AND box2 = '" . db_escape_string((string) $r['box2']) . "'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$changed++;
				$log[] = "year $year $box1..$box2 -> $target[0]..$target[1]";
			}
			$summary = "VAT report range (grupper MR) from 6780..7740 or 7680..7740 to 7680..7830: $changed changed, $kept kept by a guard";
			$qtxt = "INSERT INTO settings (var_name, var_grp, var_value, var_description, user_id) ";
			$qtxt.= "VALUES ('vat_report_range_7680', 'system', '" . date('Ymd') . "', '" . db_escape_string($summary) . "', 0)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			transaktion('commit');
			// A failed statement rolls the whole transaction back on Postgres, the flag with it.
			if (!db_fetch_array(db_select($flagQuery, __FILE__ . " linje " . __LINE__))) {
				error_log("Saldi $db: VAT report range repair (grupper MR) was rolled back; the next login tries again");
				return 'failed';
			}
			if ($log) {
				error_log("Saldi $db: $summary (" . implode(', ', $log) . ')');
			}
			return 'done';
		} finally {
			db_select($unlock, __FILE__ . " linje " . __LINE__);
		}
	}
}
