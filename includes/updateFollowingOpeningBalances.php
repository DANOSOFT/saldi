<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/updateFollowingOpeningBalances.php --- patch 5.0.1 --- 2026.09.07 ---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260907 CDX/PHR Carry revised year-end balances through subsequent fiscal years after journal posting.
// 20260907 CL/NTR  Stop propagation instead of dropping balances when the target year lacks a
//                  destination account for a status or result balance.

/**
 * Rebuild subsequent opening balances using the transfer rules from fiscalYearInc/yearX.php.
 * The caller owns the tenant transaction. Closed years retain their status.
 * Deleted years and missing predecessors form a boundary because their source data is unavailable.
 * A target year that lacks the destination account for a status or result balance is also a boundary,
 * so a partially transferable year is never written.
 *
 * @return int[] Fiscal year numbers whose opening balances were updated.
 */
function updateFollowingOpeningBalances($postedYear) {
	$postedYear = (int)$postedYear;
	$years = array();
	$q = db_select("select kodenr, box1, box2, box3, box4, box10 from grupper where art = 'RA'", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$years[(int)$row['kodenr']] = $row;
	}
	$updated = array();
	for ($sourceYear = $postedYear; isset($years[$sourceYear], $years[$sourceYear + 1]); $sourceYear++) {
		$targetYear = $sourceYear + 1;
		$source = $years[$sourceYear];
		$target = $years[$targetYear];
		if ($source['box10'] === 'on' || $target['box10'] === 'on') {
			break;
		}
		$startYear = (int)$source['box2'];
		$startMonth = (int)$source['box1'];
		$endYear = (int)$source['box4'];
		$endMonth = (int)$source['box3'];
		if (!checkdate($startMonth, 1, $startYear) || !checkdate($endMonth, 1, $endYear)) {
			throw new RuntimeException('Invalid fiscal year dates while updating opening balances.');
		}
		$start = sprintf('%04d-%02d-01', $startYear, $startMonth);
		$end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
		$opening = array();
		$q = db_select("select kontonr from kontoplan where regnskabsaar = '$targetYear' and kontotype = 'S'", __FILE__ . ' line ' . __LINE__);
		while ($row = db_fetch_array($q)) {
			$opening[(int)$row['kontonr']] = 0;
		}
		if (!$opening) {
			break;
		}
		$movement = array();
		$q = db_select("select kontonr, debet, kredit from transaktioner where transdate >= '$start' and transdate <= '$end'", __FILE__ . ' line ' . __LINE__);
		while ($row = db_fetch_array($q)) {
			$account = (int)$row['kontonr'];
			if (!isset($movement[$account])) $movement[$account] = 0;
			$movement[$account] += afrund($row['debet'] - $row['kredit'], 2);
		}
		$profit = 0;
		$resultAccount = null;
		$missingDestination = false;
		$q = db_select("select kontonr, kontotype, primo, overfor_til from kontoplan where regnskabsaar = '$sourceYear' and kontotype in ('S', 'D', 'X') order by kontonr", __FILE__ . ' line ' . __LINE__);
		while ($row = db_fetch_array($q)) {
			$account = (int)$row['kontonr'];
			$amount = isset($movement[$account]) ? $movement[$account] : 0;
			if ($row['kontotype'] === 'D') {
				$profit += afrund($row['primo'], 2) + $amount;
			} elseif ($row['kontotype'] === 'X' && $resultAccount === null) {
				$resultAccount = $row;
			} elseif ($row['kontotype'] === 'S') {
				$destination = (int)$row['overfor_til'];
				if (!isset($opening[$destination])) $destination = $account;
				if (isset($opening[$destination])) {
					$opening[$destination] += (float)$row['primo'] + $amount;
				} else {
					$missingDestination = true;
				}
			}
		}
		if ($resultAccount !== null) {
			$destination = (int)$resultAccount['overfor_til'];
			$account = (int)$resultAccount['kontonr'];
			if (isset($opening[$destination])) {
				$opening[$destination] += afrund($profit, 2) + (isset($movement[$account]) ? $movement[$account] : 0);
			} else {
				$missingDestination = true;
			}
		} else {
			$missingDestination = true;
		}
		if ($missingDestination) {
			// A balance has no account to land on in the target year, so leave it and later years untouched.
			break;
		}
		foreach ($opening as $account => $amount) {
			$amount = (float)afrund($amount, 2);
			db_modify("update kontoplan set primo = '$amount' where regnskabsaar = '$targetYear' and kontonr = '$account' and kontotype = 'S'", __FILE__ . ' line ' . __LINE__);
		}
		// Avoid switching database connections while the journal transaction is active.
		genberegn($targetYear, false);
		$updated[] = $targetYear;
	}
	return $updated;
}
