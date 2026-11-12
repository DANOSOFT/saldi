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
//                  Invalid fiscal year dates are checked before anything is written and reported like a block; an unexpected error is rolled back and reported.
//                  A fingerprint of the open years' postings every ten minutes also finds late commits below the id watermark and deleted postings (CodeRabbit).
//                  «Sæt aktivt» carries year 1 and a year after a deleted year forward, a reversed date range is invalid, and the alert text is JSON-encoded.
//                  When the transfer into a saved year is blocked, the year card keeps that year's opening balances as they were.

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
 *   invalidDates: int[],      Fiscal years (sourceYear and/or targetYear) whose start or end month is not valid.
 *   failed: bool,             The rebuild stopped on an unexpected error and was rolled back (set by the callers).
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
		$blocked = followingOpeningBalanceStopShape($sourceYear, $targetYear);
		// The source year's dates select its postings and genberegn() reads the target year's dates,
		// so both are checked before anything is written; a target year is never left half rebuilt.
		$sourceRange = followingOpeningBalanceYearRange($source);
		if (!$sourceRange) $blocked['invalidDates'][] = $sourceYear;
		if (!followingOpeningBalanceYearRange($target)) $blocked['invalidDates'][] = $targetYear;
		if ($blocked['invalidDates']) {
			$stop = $blocked;
			break;
		}
		list($start, $end) = $sourceRange;
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
		saveFollowingOpeningBalanceState($maxId, 0, 0);
		return null;
	}
	list($lastId, $pendingYear, $fingerprintAt) = followingOpeningBalanceStateParts($r['state']);
	$changed = false;
	if ($maxId > $lastId) {
		$year = followingOpeningBalancePostedYear($lastId, $maxId);
		if ($year && (!$pendingYear || $year < $pendingYear)) $pendingYear = $year;
		$lastId = $maxId;
		$changed = true;
	}
	if (time() - $fingerprintAt >= 600) {
		// Postings that commit after a check with lower ids than it saw, and deleted postings, show up here.
		$year = followingOpeningBalanceChangedYear();
		if ($year && (!$pendingYear || $year < $pendingYear)) $pendingYear = $year;
		$fingerprintAt = time();
		$changed = true;
	}
	if ($changed) {
		saveFollowingOpeningBalanceState($lastId, $pendingYear, $fingerprintAt);
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
 * With $year («Sæt aktivt» on that year), $year is carried forward from itself when it has no usable year before it.
 *
 * @param int $fromYear Fiscal year whose closing balances are carried into the later years (0 = none).
 * @param int $year The fiscal year being activated, or 0.
 * @return array|null The transition that could not be written (see updateFollowingOpeningBalances()), or null.
 */
function syncFollowingOpeningBalances($fromYear, $year = 0) {
	$fromYear = (int)$fromYear;
	$year = (int)$year;
	if (($fromYear < 1 && $year < 1) || followingOpeningBalancesInTransaction()) {
		return null;
	}
	include_once(__DIR__ . '/std_func.php');
	include_once(__DIR__ . '/genberegn.php');
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'openingBalanceSync' order by id limit 1", __FILE__ . ' line ' . __LINE__));
	if ($r && $r['var_value'] !== '' && $r['var_value'] !== null) {
		list($lastId, $pendingYear, $fingerprintAt) = followingOpeningBalanceStateParts($r['var_value']);
		if ($pendingYear) {
			$fromYear = $fromYear > 0 ? min($fromYear, $pendingYear) : $pendingYear;
			// Cleared before the rebuild, so a failing rebuild is not retried on every page.
			saveFollowingOpeningBalanceState($lastId, 0, $fingerprintAt);
		}
	}
	$checkedFrom = $fromYear;
	$stop = null;
	transaktion('begin');
	try {
		if ($year > 0) {
			carryFollowingOpeningBalances($year, $fromYear, $stop);
		} else {
			updateFollowingOpeningBalances($fromYear, $stop);
		}
	} catch (Throwable $e) {
		// Nothing of the rebuild is kept, and the stored message tells the user to retry with Gem/opdatér.
		transaktion('rollback');
		error_log('SST-1005: opening balances after fiscal year ' . $fromYear . ' not updated: ' . $e->getMessage());
		$stop = followingOpeningBalanceStopShape($fromYear, $fromYear + 1);
		$stop['failed'] = true;
		saveFollowingOpeningBalanceStop($stop, $checkedFrom);
		return $stop;
	}
	saveFollowingOpeningBalanceStop($stop, $checkedFrom);
	transaktion('commit');
	return $stop;
}

/**
 * Carry $fromYear (normally the year before $year) into the later years. When that does not get past $year
 * without a block, because there is no usable year before it (year 1, a missing or deleted year), $year's own
 * opening balances are carried forward from $year. A real block is never bypassed. The caller owns the transaction.
 *
 * @param int $year The year being saved or activated.
 * @param int $fromYear In: where to start. Out: $year when the rebuild had to start there.
 * @param array|null $stop Receives the block, see updateFollowingOpeningBalances().
 * @return int[] Fiscal year numbers whose opening balances were updated.
 */
function carryFollowingOpeningBalances($year, &$fromYear, &$stop) {
	$year = (int)$year;
	$updated = updateFollowingOpeningBalances($fromYear, $stop);
	if (!$stop && !in_array($year + 1, $updated, true)) {
		$fromYear = $year;
		$updated = array_merge($updated, updateFollowingOpeningBalances($year, $stop));
	}
	return $updated;
}

/**
 * Gem/opdatér on the year card (systemdata/regnskabskort.php): carry the year before into $year again, so the
 * «Overfør til» choices just saved are checked, and on into every later year. Year 1 and a year after a deleted
 * year have their own opening balances, so those are carried forward from $year itself. When the transfer into
 * $year is blocked or fails, $year keeps the opening balances it had before the card was saved
 * ($primoBeforeSave) and the later years are left alone; the card's other fields stay saved. The caller owns the
 * transaction; the carry-forward runs inside a savepoint, so an unexpected error undoes all of it.
 *
 * @param int $year The fiscal year whose card was saved.
 * @param array|null $primoBeforeSave kontoplan.id => primo of $year before the save (followingOpeningBalancePrimo()).
 * @return array|null The transition that could not be written (see updateFollowingOpeningBalances()), or null.
 */
function updateOpeningBalancesAfterYearSave($year, $primoBeforeSave = null) {
	$year = (int)$year;
	$ownTransaction = !followingOpeningBalancesInTransaction();
	if ($ownTransaction) {
		transaktion('begin');
	}
	db_modify('savepoint following_opening_balances', __FILE__ . ' line ' . __LINE__);
	$stop = null;
	$fromYear = $year - 1;
	$checkedFrom = $fromYear;
	try {
		carryFollowingOpeningBalances($year, $fromYear, $stop);
	} catch (Throwable $e) {
		db_modify('rollback to savepoint following_opening_balances', __FILE__ . ' line ' . __LINE__);
		error_log('SST-1005: opening balances after saving fiscal year ' . $year . ' not updated: ' . $e->getMessage());
		$stop = followingOpeningBalanceStopShape($fromYear, $fromYear + 1);
		$stop['failed'] = true;
	}
	if ($stop && (int)$stop['sourceYear'] === $year - 1 && is_array($primoBeforeSave)) {
		// The card has just reset and rewritten this year's opening balances; without a complete transfer they go back.
		foreach ($primoBeforeSave as $id => $primo) {
			$primo = (float)$primo;
			db_modify("update kontoplan set primo = '$primo' where id = '" . (int)$id . "' and regnskabsaar = '$year'", __FILE__ . ' line ' . __LINE__);
		}
	}
	db_modify('release savepoint following_opening_balances', __FILE__ . ' line ' . __LINE__);
	saveFollowingOpeningBalanceStop($stop, $checkedFrom);
	if ($ownTransaction) {
		transaktion('commit');
	}
	return $stop;
}

/**
 * The opening balances of a fiscal year as they are now, for updateOpeningBalancesAfterYearSave().
 *
 * @param int $year Fiscal year (kontoplan.regnskabsaar).
 * @return array kontoplan.id => primo.
 */
function followingOpeningBalancePrimo($year) {
	$year = (int)$year;
	$primo = array();
	$q = db_select("select id, primo from kontoplan where regnskabsaar = '$year'", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$primo[(int)$row['id']] = $row['primo'];
	}
	return $primo;
}

/**
 * A script tag that shows the message for a blocked rebuild in an alert. The text holds saved fiscal year
 * descriptions, so it is JSON-encoded with HTML-safe flags and cannot end the script element.
 *
 * @param array $stop See updateFollowingOpeningBalances().
 * @param int $languageId sprog_id for findtekst().
 * @return string HTML.
 */
function followingOpeningBalanceAlertScript($stop, $languageId) {
	// JSON_HEX_TAG turns < and > into \u003C/\u003E, so the text can never contain </script>; slashes may stay plain.
	$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
	return "<script type='text/javascript'>alert(" . json_encode(followingOpeningBalanceStopText($stop, $languageId), $flags) . ");</script>";
}

/**
 * An empty stop for the transition from $sourceYear to $targetYear (see updateFollowingOpeningBalances()).
 *
 * @param int $sourceYear Fiscal year whose closing balances are carried forward.
 * @param int $targetYear Fiscal year whose opening balances are written.
 * @return array The stop with every reason cleared.
 */
function followingOpeningBalanceStopShape($sourceYear, $targetYear) {
	return array(
		'sourceYear' => (int)$sourceYear,
		'targetYear' => (int)$targetYear,
		'accounts' => array(),
		'resultAccount' => 0,
		'resultDestination' => 0,
		'noResultAccount' => false,
		'noStatusAccounts' => false,
		'invalidDates' => array(),
		'failed' => false,
	);
}

/**
 * First and last date of a fiscal year from its grupper art 'RA' row, read the way genberegn() reads them.
 *
 * @param array $row Row with box1 (start month), box2 (start year), box3 (end month) and box4 (end year).
 * @return array{0: string, 1: string}|null Start and end as 'Y-m-d', or null when a box is not a valid month or year
 *   or the year ends before it starts.
 */
function followingOpeningBalanceYearRange($row) {
	foreach (array('box1', 'box2', 'box3', 'box4') as $box) {
		if (!isset($row[$box]) || !ctype_digit(trim((string)$row[$box]))) {
			return null;
		}
	}
	$startMonth = (int)$row['box1'];
	$startYear = (int)$row['box2'];
	$endMonth = (int)$row['box3'];
	$endYear = (int)$row['box4'];
	if (!checkdate($startMonth, 1, $startYear) || !checkdate($endMonth, 1, $endYear)) {
		return null;
	}
	$start = sprintf('%04d-%02d-01', $startYear, $startMonth);
	$end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
	// A year that ends before it starts selects no postings at all, so it is as invalid as a bad month.
	return $end < $start ? null : array($start, $end);
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
 * @param string $state "<last transaktioner.id seen>|<earliest year waiting for a rebuild>|<unix time of the last fingerprint>".
 * @return array{0: int, 1: int, 2: int} The last posting id seen, the waiting fiscal year or 0, and the fingerprint time or 0.
 */
function followingOpeningBalanceStateParts($state) {
	$parts = explode('|', (string)$state);
	return array((int)$parts[0], isset($parts[1]) ? (int)$parts[1] : 0, isset($parts[2]) ? (int)$parts[2] : 0);
}

/**
 * Store the last posting id seen, the earliest fiscal year whose later years wait for a rebuild, and when the
 * postings' fingerprint was last compared.
 *
 * @param int $lastId Highest transaktioner.id already looked at.
 * @param int $pendingYear Earliest fiscal year with postings not yet carried forward, or 0.
 * @param int $fingerprintAt Unix time of the last followingOpeningBalanceChangedYear(), or 0.
 * @return void
 */
function saveFollowingOpeningBalanceState($lastId, $pendingYear, $fingerprintAt) {
	$value = (int)$lastId . '|' . (int)$pendingYear . '|' . (int)$fingerprintAt;
	$description = 'SST-1005: last posting id seen | earliest fiscal year whose later years wait for new opening balances | time of the last posting fingerprint';
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
		$range = followingOpeningBalanceYearRange($row);
		if (!$range) {
			// Reported by the rebuild of the year before it; this year's postings cannot be dated.
			continue;
		}
		list($start, $end) = $range;
		$qtxt = "select id from transaktioner where id > $afterId and id <= $upToId ";
		$qtxt.= "and transdate >= '$start' and transdate <= '$end' limit 1";
		if (db_fetch_array(db_select($qtxt, __FILE__ . ' line ' . __LINE__))) {
			return $year;
		}
	}
	return 0;
}

/**
 * Compare a fingerprint (number of postings and their debit and credit sums) of every open fiscal year that has a
 * later year with the one stored last time, and store the new one. The id check in checkFollowingOpeningBalances()
 * cannot see postings that commit after it with lower ids than it saw, nor deleted postings; this does.
 * Postings can only reach open years (box5 'on'), so closed years are left out.
 *
 * @return int The earliest fiscal year whose postings changed, or 0 (also the first time, which only stores them).
 */
function followingOpeningBalanceChangedYear() {
	$years = array();
	$q = db_select("select kodenr, box1, box2, box3, box4, box5, box10 from grupper where art = 'RA' order by kodenr", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$years[(int)$row['kodenr']] = $row;
	}
	$current = array();
	foreach ($years as $year => $row) {
		if ($row['box10'] === 'on' || $row['box5'] !== 'on' || !isset($years[$year + 1]) || $years[$year + 1]['box10'] === 'on') {
			continue;
		}
		$range = followingOpeningBalanceYearRange($row);
		if (!$range) {
			continue;
		}
		$qtxt = "select count(*) as postings, coalesce(sum(debet), 0) as debet, coalesce(sum(kredit), 0) as kredit ";
		$qtxt.= "from transaktioner where transdate >= '$range[0]' and transdate <= '$range[1]'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . ' line ' . __LINE__));
		$current[$year] = $r['postings'] . ':' . $r['debet'] . ':' . $r['kredit'];
	}
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'openingBalanceFingerprint' order by id limit 1", __FILE__ . ' line ' . __LINE__));
	$stored = ($r && $r['var_value']) ? json_decode($r['var_value'], true) : null;
	$description = 'SST-1005: number and sums of the postings per open fiscal year with a later year, compared every ten minutes';
	saveFollowingOpeningBalanceSetting('openingBalanceFingerprint', json_encode($current), $description);
	if (!is_array($stored)) {
		return 0;
	}
	foreach ($current as $year => $fingerprint) {
		if (isset($stored[$year]) && $stored[$year] !== $fingerprint) {
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
	if (!empty($stop['invalidDates'])) {
		$invalidNames = array();
		foreach ($stop['invalidDates'] as $year) {
			$invalidNames[] = isset($names[(int)$year]) ? $names[(int)$year] : (int)$year;
		}
		$txt.= ' ' . sprintf(findtekst('8498|Regnskabsåret %s har ikke en gyldig start- og slutmåned.', $languageId), implode(', ', $invalidNames));
		$txt.= ' ' . sprintf(findtekst('8499|Ret datoerne på regnskabsåret %s under Indstillinger → Regnskabsår, og tryk Gem/opdatér.', $languageId), $invalidNames[0]);
		return $txt;
	}
	if (!empty($stop['failed'])) {
		$txt.= ' ' . sprintf(findtekst('8500|Der opstod en uventet fejl under opdateringen. Tryk Gem/opdatér på regnskabsåret %s for at prøve igen, og kontakt Saldi, hvis fejlen bliver ved.', $languageId), $target);
		return $txt;
	}
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
