<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/updateFollowingOpeningBalances.php --- ver 5.1.1 --- 2026.11.12 ---
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
// 20261112 CL/MMR  SST-1005: A zero balance no longer needs a destination account, and a stop reports which year and accounts block it.
//                  Postings in an earlier year from any posting path reach the later years once a session in a later year opens a page (refreshFollowingOpeningBalances()).
//                  syncFollowingOpeningBalances() runs the same rebuild in a transaction of its own, for «Sæt aktivt».
//                  updateOpeningBalancesAfterYearSave() does it for Gem/opdatér on the year card and leaves the later years alone when the transfer into the saved year is blocked.

/**
 * Rebuild subsequent opening balances using the transfer rules from fiscalYearInc/yearX.php.
 * The caller owns the tenant transaction. Closed years retain their status.
 * Deleted years and missing predecessors form a boundary because their source data is unavailable.
 * A target year that lacks the destination account for a non-zero status or result balance is also a
 * boundary, so a partially transferable year is never written; $stop then says what blocked it.
 * A zero balance has nothing to carry, so it needs no destination account.
 *
 * @param int $postedYear Fiscal year (grupper art 'RA' kodenr) whose postings or opening balances changed.
 * @param array|null $stop Receives null, or the transition that could not be written:
 * array{
 *   sourceYear: int,          Fiscal year whose closing balances could not be carried forward.
 *   targetYear: int,          Fiscal year whose opening balances were left unchanged, like all later years.
 *   accounts: int[],          Status accounts in sourceYear with a balance and no destination in targetYear.
 *   resultAccount: int,       Sideskift account in sourceYear when the result's destination is missing, else 0.
 *   resultDestination: int,   The result's «Overfør til» account that targetYear lacks, 0 when none is chosen.
 *   noResultAccount: bool,    sourceYear has a result but no Sideskift account to carry it.
 *   noStatusAccounts: bool,   targetYear has no status accounts.
 * }
 * @return int[] Fiscal year numbers whose opening balances were updated.
 */
function updateFollowingOpeningBalances($postedYear, &$stop = null) {
	$postedYear = (int)$postedYear;
	$stop = null;
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
		$blocked = array(
			'sourceYear' => $sourceYear,
			'targetYear' => $targetYear,
			'accounts' => array(),
			'resultAccount' => 0,
			'resultDestination' => 0,
			'noResultAccount' => false,
			'noStatusAccounts' => false,
		);
		$opening = array();
		$q = db_select("select kontonr from kontoplan where regnskabsaar = '$targetYear' and kontotype = 'S'", __FILE__ . ' line ' . __LINE__);
		while ($row = db_fetch_array($q)) {
			$opening[(int)$row['kontonr']] = 0;
		}
		if (!$opening) {
			$blocked['noStatusAccounts'] = true;
			$stop = $blocked;
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
				$balance = (float)$row['primo'] + $amount;
				if (isset($opening[$destination])) {
					$opening[$destination] += $balance;
				} elseif (afrund($balance, 2) != 0) {
					$blocked['accounts'][] = $account;
				}
			}
		}
		$result = afrund($profit, 2);
		if ($resultAccount !== null) {
			$destination = (int)$resultAccount['overfor_til'];
			$account = (int)$resultAccount['kontonr'];
			$result += isset($movement[$account]) ? $movement[$account] : 0;
			if (isset($opening[$destination])) {
				$opening[$destination] += $result;
			} elseif (afrund($result, 2) != 0) {
				$blocked['resultAccount'] = $account;
				$blocked['resultDestination'] = $destination;
			}
		} elseif (afrund($result, 2) != 0) {
			$blocked['noResultAccount'] = true;
		}
		if ($blocked['accounts'] || $blocked['resultAccount'] || $blocked['noResultAccount']) {
			// A balance has no account to land on in the target year, so leave it and later years untouched.
			$stop = $blocked;
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

/**
 * Bring the later years' opening balances up to date with postings that reached an earlier year, on any
 * posting path (invoices, purchases, POS, payments, settlement, stock, currency, journals, API).
 * Runs once per request from includes/online.php, so it must stay cheap: normally one indexed query.
 * Postings in the newest year need nothing. Postings in an earlier year are remembered, and the rebuild
 * waits until a session in a later year opens a page, so a shop that keeps selling in the old year is not
 * slowed down by a rebuild after every sale. It is the automatic version of Gem/opdatér on the year card.
 *
 * @param int $activeYear The session's fiscal year (online.regnskabsaar).
 * @return array|null The transition that could not be written (see updateFollowingOpeningBalances()), or null.
 */
function refreshFollowingOpeningBalances($activeYear) {
	static $checked = false;
	if ($checked || (int)$activeYear < 1 || followingOpeningBalancesInTransaction()) {
		return null;
	}
	$checked = true;
	return checkFollowingOpeningBalances($activeYear);
}

/**
 * The check behind refreshFollowingOpeningBalances(), without its once-per-request guard.
 *
 * @param int $activeYear The session's fiscal year (online.regnskabsaar).
 * @return array|null The transition that could not be written (see updateFollowingOpeningBalances()), or null.
 */
function checkFollowingOpeningBalances($activeYear) {
	$activeYear = (int)$activeYear;
	if ($activeYear < 1 || followingOpeningBalancesInTransaction()) {
		return null;
	}
	$qtxt = "select (select max(id) from transaktioner) as max_id, ";
	$qtxt.= "(select var_value from settings where var_name = 'openingBalanceSync' order by id limit 1) as state";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . ' line ' . __LINE__));
	if (!$r) {
		return null;
	}
	$maxId = (int)$r['max_id'];
	if ($r['state'] === null || $r['state'] === '') {
		// First check: follow postings from now on. Earlier years are brought up to date by «Sæt aktivt».
		saveFollowingOpeningBalanceState($maxId, 0);
		return null;
	}
	list($lastId, $pendingYear) = followingOpeningBalanceStateParts($r['state']);
	if ($maxId > $lastId) {
		$year = followingOpeningBalancePostedYear($lastId, $maxId);
		if ($year && (!$pendingYear || $year < $pendingYear)) $pendingYear = $year;
		$lastId = $maxId;
		saveFollowingOpeningBalanceState($lastId, $pendingYear);
	}
	if (!$pendingYear || $activeYear <= $pendingYear) {
		return null;
	}
	return syncFollowingOpeningBalances($pendingYear);
}

/**
 * Rebuild the opening balances of the years after $fromYear in a transaction of its own, like pressing
 * Gem/opdatér on each of them, and remember a blocked transition so systemdata/regnskabsaar.php can show it.
 * A rebuild that is already waiting for an earlier year (refreshFollowingOpeningBalances()) starts there instead.
 *
 * @param int $fromYear Fiscal year whose closing balances are carried into the later years.
 * @return array|null The transition that could not be written (see updateFollowingOpeningBalances()), or null.
 */
function syncFollowingOpeningBalances($fromYear) {
	$fromYear = (int)$fromYear;
	if ($fromYear < 1 || followingOpeningBalancesInTransaction()) {
		return null;
	}
	include_once(__DIR__ . '/std_func.php');
	include_once(__DIR__ . '/genberegn.php');
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'openingBalanceSync' order by id limit 1", __FILE__ . ' line ' . __LINE__));
	if ($r && $r['var_value'] !== '' && $r['var_value'] !== null) {
		list($lastId, $pendingYear) = followingOpeningBalanceStateParts($r['var_value']);
		if ($pendingYear) {
			$fromYear = min($fromYear, $pendingYear);
			// Cleared before the rebuild, so a failing rebuild is not retried on every page.
			saveFollowingOpeningBalanceState($lastId, 0);
		}
	}
	$stop = null;
	transaktion('begin');
	try {
		updateFollowingOpeningBalances($fromYear, $stop);
		saveFollowingOpeningBalanceStop($stop, $fromYear);
	} catch (Throwable $e) {
		// A fiscal year with invalid dates must not break the page that triggered the rebuild.
		transaktion('rollback');
		error_log('SST-1005: opening balances after fiscal year ' . $fromYear . ' not updated: ' . $e->getMessage());
		return null;
	}
	transaktion('commit');
	return $stop;
}

/**
 * Gem/opdatér on the year card (systemdata/regnskabskort.php): carry the year before into $year again, so the
 * «Overfør til» choices just saved are checked, and on into every later year. Year 1 and a year after a deleted
 * year have their own opening balances, so those are carried forward from $year itself. When the transfer into
 * $year is blocked, the later years are left alone as well. The caller owns the transaction.
 *
 * @param int $year The fiscal year whose card was saved.
 * @return array|null The transition that could not be written (see updateFollowingOpeningBalances()), or null.
 */
function updateOpeningBalancesAfterYearSave($year) {
	$year = (int)$year;
	$stop = null;
	$fromYear = $year - 1;
	try {
		if (!updateFollowingOpeningBalances($fromYear, $stop) && !$stop) {
			$fromYear = $year;
			updateFollowingOpeningBalances($fromYear, $stop);
		}
	} catch (Throwable $e) {
		// The year card itself is saved; only the carry-forward is skipped for a fiscal year with invalid dates.
		error_log('SST-1005: opening balances after saving fiscal year ' . $year . ' not updated: ' . $e->getMessage());
		return null;
	}
	saveFollowingOpeningBalanceStop($stop, $fromYear);
	return $stop;
}

/**
 * Whether a database transaction is open, in which case the caller owns it and nothing may be committed here.
 *
 * @return bool True when transaktion('begin') is active or the PostgreSQL connection is not idle.
 */
function followingOpeningBalancesInTransaction() {
	global $connection, $db_transaktion_depth, $db_type;
	if (!empty($db_transaktion_depth)) {
		return true;
	}
	if ($db_type !== 'mysql' && $db_type !== 'mysqli' && $connection && function_exists('pg_transaction_status')) {
		return pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE;
	}
	return false;
}

/**
 * Split the settings value 'openingBalanceSync'.
 *
 * @param string $state The stored value, "<last transaktioner.id seen>|<earliest year waiting for a rebuild>".
 * @return int[] array{0: int, 1: int} The last posting id seen, and the waiting fiscal year or 0.
 */
function followingOpeningBalanceStateParts($state) {
	$parts = explode('|', (string)$state);
	return array((int)$parts[0], isset($parts[1]) ? (int)$parts[1] : 0);
}

/**
 * Store the last posting id seen and the earliest fiscal year whose later years wait for a rebuild.
 *
 * @param int $lastId Highest transaktioner.id already looked at.
 * @param int $pendingYear Earliest fiscal year with postings not yet carried forward, or 0.
 * @return void
 */
function saveFollowingOpeningBalanceState($lastId, $pendingYear) {
	$value = (int)$lastId . '|' . (int)$pendingYear;
	$description = 'SST-1005: last posting id seen | earliest fiscal year whose later years wait for new opening balances';
	saveFollowingOpeningBalanceSetting('openingBalanceSync', $value, $description);
}

/**
 * Store or clear the transition that blocked a rebuild. A blocked transition before $fromYear was not
 * part of this rebuild and stays; one at or after it is replaced by this rebuild's outcome.
 *
 * @param array|null $stop The rebuild's outcome (see updateFollowingOpeningBalances()).
 * @param int $fromYear The fiscal year the rebuild started from.
 * @return void
 */
function saveFollowingOpeningBalanceStop($stop, $fromYear) {
	$stored = followingOpeningBalanceStop();
	if ($stored && (int)$stored['sourceYear'] < (int)$fromYear) {
		return;
	}
	if (!$stop && !$stored) {
		return;
	}
	$description = 'SST-1005: fiscal year transition whose opening balances could not be updated automatically';
	saveFollowingOpeningBalanceSetting('openingBalanceStop', $stop ? json_encode($stop) : '', $description);
}

/**
 * The stored transition that blocked the last rebuild.
 *
 * @return array|null See updateFollowingOpeningBalances() for the shape, or null when nothing is blocked.
 */
function followingOpeningBalanceStop() {
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'openingBalanceStop' order by id limit 1", __FILE__ . ' line ' . __LINE__));
	if (!$r || !$r['var_value']) {
		return null;
	}
	$stop = json_decode($r['var_value'], true);
	return (is_array($stop) && isset($stop['sourceYear'], $stop['targetYear'])) ? $stop : null;
}

/**
 * Write a tenant setting of this feature (var_grp 'system').
 *
 * @param string $name var_name.
 * @param string $value var_value.
 * @param string $description var_description, used only when the row is created.
 * @return void
 */
function saveFollowingOpeningBalanceSetting($name, $value, $description) {
	$name = db_escape_string($name);
	$value = db_escape_string($value);
	if (db_fetch_array(db_select("select id from settings where var_name = '$name'", __FILE__ . ' line ' . __LINE__))) {
		db_modify("update settings set var_value = '$value' where var_name = '$name'", __FILE__ . ' line ' . __LINE__);
	} else {
		$qtxt = "insert into settings (var_name, var_grp, var_value, var_description, user_id) values ";
		$qtxt.= "('$name', 'system', '$value', '" . db_escape_string($description) . "', '0')";
		db_modify($qtxt, __FILE__ . ' line ' . __LINE__);
	}
}

/**
 * The earliest fiscal year with a later year that got one of the postings with id in ($afterId, $upToId].
 * Postings in the newest year, in a deleted year or outside every fiscal year need no rebuild.
 *
 * @param int $afterId Highest transaktioner.id already looked at.
 * @param int $upToId Highest transaktioner.id now.
 * @return int Fiscal year (kodenr), or 0 when none of the postings has a later year to carry into.
 */
function followingOpeningBalancePostedYear($afterId, $upToId) {
	$afterId = (int)$afterId;
	$upToId = (int)$upToId;
	$years = array();
	$q = db_select("select kodenr, box1, box2, box3, box4, box10 from grupper where art = 'RA' order by kodenr", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$years[(int)$row['kodenr']] = $row;
	}
	foreach ($years as $year => $row) {
		if ($row['box10'] === 'on' || !isset($years[$year + 1]) || $years[$year + 1]['box10'] === 'on') {
			continue;
		}
		$startMonth = (int)$row['box1'];
		$startYear = (int)$row['box2'];
		$endMonth = (int)$row['box3'];
		$endYear = (int)$row['box4'];
		if (!checkdate($startMonth, 1, $startYear) || !checkdate($endMonth, 1, $endYear)) {
			continue;
		}
		$start = sprintf('%04d-%02d-01', $startYear, $startMonth);
		$end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
		$qtxt = "select id from transaktioner where id > $afterId and id <= $upToId ";
		$qtxt.= "and transdate >= '$start' and transdate <= '$end' limit 1";
		if (db_fetch_array(db_select($qtxt, __FILE__ . ' line ' . __LINE__))) {
			return $year;
		}
	}
	return 0;
}

/**
 * The message for a blocked rebuild, naming the years and accounts and how to fix it.
 *
 * @param array $stop See updateFollowingOpeningBalances().
 * @param int $languageId sprog_id for findtekst().
 * @return string Plain text, not HTML-escaped.
 */
function followingOpeningBalanceStopText($stop, $languageId) {
	$names = array();
	$q = db_select("select kodenr, beskrivelse from grupper where art = 'RA'", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$names[(int)$row['kodenr']] = trim((string)$row['beskrivelse']) !== '' ? trim($row['beskrivelse']) : $row['kodenr'];
	}
	$source = isset($names[(int)$stop['sourceYear']]) ? $names[(int)$stop['sourceYear']] : $stop['sourceYear'];
	$target = isset($names[(int)$stop['targetYear']]) ? $names[(int)$stop['targetYear']] : $stop['targetYear'];
	$txt = sprintf(findtekst('8491|Primo i regnskabsåret %s er ikke opdateret automatisk.', $languageId), $target);
	if (!empty($stop['noStatusAccounts'])) {
		$txt.= ' ' . sprintf(findtekst('8492|Regnskabsåret %s har ingen statuskonti.', $languageId), $target);
	}
	if (!empty($stop['accounts'])) {
		$accounts = array_map('intval', $stop['accounts']);
		$accountList = implode(', ', array_slice($accounts, 0, 10)) . (count($accounts) > 10 ? ' …' : '');
		$txt.= ' ' . sprintf(findtekst('8493|Saldoen på konto %s i %s har ingen konto at blive overført til i %s. Vælg en konto i «Overfør til», eller opret kontoen i %s.', $languageId), $accountList, $source, $target, $target);
	}
	if (!empty($stop['resultAccount'])) {
		if (!empty($stop['resultDestination'])) {
			$txt.= ' ' . sprintf(findtekst('8494|Årets resultat i %s skal overføres til konto %s, som ikke findes i %s. Vælg en anden konto i «Overfør til» ud for Resultat, eller opret kontoen.', $languageId), $source, (int)$stop['resultDestination'], $target);
		} else {
			$txt.= ' ' . sprintf(findtekst('8495|Der er ikke valgt en konto i «Overfør til» ud for Resultat, så årets resultat i %s kan ikke overføres.', $languageId), $source);
		}
	}
	if (!empty($stop['noResultAccount'])) {
		$txt.= ' ' . sprintf(findtekst('8496|Årets resultat i %s kan ikke overføres, fordi kontoplanen ikke har en konto af typen Sideskift. Opret en Sideskift-konto i %s.', $languageId), $source, $source);
	}
	$txt.= ' ' . sprintf(findtekst('8497|Ret det på regnskabsåret %s under Indstillinger → Regnskabsår, og tryk Gem/opdatér.', $languageId), $target);
	return $txt;
}
