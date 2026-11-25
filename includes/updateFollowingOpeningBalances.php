<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/updateFollowingOpeningBalances.php --- ver 5.1.1 --- 2026.11.25 ---
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
//                  The first fingerprint is taken before the first watermark and both are stored together in one JSON row, so nothing committed in between is lost (Codex round 5).
//                  The first state is created under a lock and never replaces a state another check stored meanwhile (Codex round 6).
//                  Each transfer is checked again after its target rows are written and calculated again when its source year changed meanwhile, so an older rebuild cannot overwrite a newer one (Codex round 7).
//                  The check compares every account's inputs, not totals, and each transfer runs inside its own savepoint, so a retry that is blocked leaves the target as it was (Codex round 8).
//                  The year card locks its year's rows when it reads the opening balances it may put back.
// 20261113 CL/MMR  SST-1005: The check after writing compares the very inputs the transfer was calculated from, so a change that is undone while the transfer is calculated is noticed too (Codex round 9).
//                  A rebuild locks a target year's rows in one statement before writing any of them, and marks a source year that keeps changing as waiting only after its last write.
//                  The year card writes the «Overfør til» choices that change on the year before first, so it never holds its own year while it waits for the year before (Codex round 9).
//                  A rebuild never waits for a target year another transaction is writing: it tries to lock the whole year without waiting for a few seconds and otherwise leaves the source year waiting for the next check, so it cannot deadlock with a journal (Codex round 10).
//                  The year card tries the same before it waits for its own year.
//                  The waiting year has a settings row of its own that is only ever lowered or cleared in one statement, so a check that saves the watermark it read earlier cannot erase a year another request left waiting (Codex round 11).
//                  A request that leaves a year waiting before the first check has created that row creates it itself, under a lock the first check takes too (Codex round 12).
//                  A year still waiting in a state of an earlier test build is always lowered into that row before the state is saved without it (Codex round 13).
//                  A request that does not see the row goes straight to the locked create-or-lower, so a row created by someone else in between cannot skip its year (Codex round 14).
//                  A request that finds the row created meanwhile always writes it, also with the same year, so it holds the row until it commits (Codex round 15).
//                  The fingerprint is taken per account, so a late posting and a deleted one that cancel out in the year's totals are still found (Codex round 16).
//                  A deleted year is recognised by any marker in box10, the timestamp deleteFiscalYear.php writes as well as the older 'on' (Codex round 17).
// 20261120 CL/MMR  SST-1005: A waiting year that was deleted before its rebuild no longer swallows the later work lowered into it: the rebuild starts at the first year after it (CodeRabbit).
//                  A rebuild that a failed statement, a fatal error or a time-out ends before its catch runs is rolled back on shutdown and leaves the 'failed' message (CodeRabbit).
// 20261120 CL/MMR  SST-1005: The year card arms that guard for its whole save, so a failure in its own primo reset leaves the message too, and a nested caller never disarms it (Codex round 22).
// 20261120 CL/MMR  SST-1005: A COMMIT the database refuses is reported like any failed statement, and the guard is only disarmed after a confirmed commit (Codex round 23).
// 20261124 CL/MMR  SST-1005: The rebuild recalculates a year with recalculateLedgerYear() (includes/ledgerYearRecalculation.php), not genberegn(): debitor/ordreliste.php declares its own genberegn($id), so the page check there recalculated an order instead of the year (Saul on PR #1109).
//                  Only that side-effect-free file is included, not includes/genberegn.php, whose ?regnskabsaar= block would run a full recalculation and include online.php again on a page with that parameter.
// 20261125 CL/MMR  SST-1005: A transfer reads both years' dates again after the target year is locked and compares them with its other inputs, so a card that changed them meanwhile is calculated from (CodeRabbit).
//                  On MySQL the row for a waiting year is only created when GET_LOCK gives the lock; otherwise nothing is saved and the work is done again (CodeRabbit, Lui's pre-check).
//                  A year waiting in an old state is lowered into its row before the state is saved without it, also when the row exists (CodeRabbit).
//                  «Sæt aktivt» and the page check arm the failure guard before they take the waiting year (CodeRabbit).
//                  A rebuild that leaves a transfer for the next check keeps a stored block at or after it, and the block is read with its row locked and replaced in the same transaction (CodeRabbit).
//                  A setting of this feature is created with ON CONFLICT DO NOTHING on PostgreSQL, so two requests creating it at once no longer end one of them (Lui's pre-check).
//                  The alert text is converted to UTF-8 on a LATIN9 regnskab and written as ASCII, and the account list ends in plain dots (Lui's pre-check).
//                  MySQL 8 and MariaDB 10.6 lock a target year without waiting, like PostgreSQL (Lui's pre-check).
// 20261125 CL/MMR  SST-1005: A setting of this feature is created under a lock named after it and with a current read, and clearing the block never creates its row, so MySQL never gets a second row of one (Codex round 28). The block row is locked and written by its id, as a locking statement by var_name locks every settings row it scans on MySQL.
// 20261125 CL/MMR  SST-1005: On MySQL the lock for creating a setting is kept until the transaction that creates it ends, as under READ COMMITTED a second creator did not wait for the row (Codex round 29).
// 20261125 CL/MMR  SST-1005: The waiting-year and block rows are created in autocommit before a rebuild, the year card or a stored block begins its transaction, and by the first check (Codex round 31).
//                  Inside a transaction the MySQL creation lock is only tried, never awaited, so a request holding settings rows cannot wait in a circle with a creator.
// 20261125 CL/MMR  SST-1005: Inside a transaction the waiting-year and block rows are locked by the id read before it (or by the page check's first query) before anything of them is read, so two rebuilds under SERIALIZABLE no longer deadlock on a shared lock they both upgrade (Codex round 32).
// 20261125 CL/MMR  SST-1005: The waiting year is taken and a setting of this feature is written by its id, so no statement of the feature scans settings and waits in a circle with a year card that holds the block row (Codex round 33).

include_once(__DIR__ . '/ledgerYearRecalculation.php'); // recalculateLedgerYear(): a name no page declares, unlike genberegn()

/**
 * Whether a fiscal year's grupper art 'RA' box10 marks it as deleted. systemdata/fiscalYearInc/deleteFiscalYear.php
 * writes a Unix timestamp; years deleted before 2025-07-01 and the five-year mark in systemdata/regnskabsaar.php use
 * 'on'. Only an empty box10 is a live year, as in active_fiscal_years() (includes/std_func.php, MB-94).
 *
 * @param string|null $marker box10 of the year.
 * @return bool True for a deleted year.
 */
function followingOpeningBalanceYearDeleted($marker) {
	return trim((string)$marker) !== '';
}

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
 * @param int $deferredYear Receives 0, or the source year whose transfer was left for a later check because another
 *   transaction kept writing the target year (see followingOpeningBalanceTryLockYear()); nothing from there on is written.
 * @return int[] Fiscal year numbers whose opening balances were updated.
 */
function updateFollowingOpeningBalances($postedYear, &$stop = null, &$deferredYear = null) {
	$postedYear = (int)$postedYear;
	$stop = null;
	$deferredYear = 0;
	$years = array();
	$q = db_select("select kodenr, box1, box2, box3, box4, box10 from grupper where art = 'RA'", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$years[(int)$row['kodenr']] = $row;
	}
	$updated = array();
	$pendingYear = 0;
	for ($sourceYear = $postedYear; isset($years[$sourceYear], $years[$sourceYear + 1]); $sourceYear++) {
		$targetYear = $sourceYear + 1;
		$source = $years[$sourceYear];
		$target = $years[$targetYear];
		if (followingOpeningBalanceYearDeleted($source['box10']) || followingOpeningBalanceYearDeleted($target['box10'])) {
			break;
		}
		$blocked = followingOpeningBalanceStopShape($sourceYear, $targetYear);
		// The source year's dates select its postings and recalculateLedgerYear() reads the target year's dates,
		// so both are checked before anything is written; a target year is never left half rebuilt.
		if (followingOpeningBalanceInvalidDates($blocked, followingOpeningBalanceYearRange($source), followingOpeningBalanceYearRange($target))) {
			$stop = $blocked;
			break;
		}
		// Every attempt of this transfer is undone together when a later attempt turns out to be blocked.
		$savepoint = followingOpeningBalancesInTransaction();
		if ($savepoint) {
			db_modify('savepoint following_opening_balance_transfer', __FILE__ . ' line ' . __LINE__);
		}
		// The whole target year is locked before anything is read or written, without ever waiting for it: a journal
		// posting in the target year holds its accounts one by one and may then need one this rebuild holds.
		if (!followingOpeningBalanceTryLockYear($targetYear)) {
			if ($savepoint) {
				db_modify('rollback to savepoint following_opening_balance_transfer', __FILE__ . ' line ' . __LINE__);
				db_modify('release savepoint following_opening_balance_transfer', __FILE__ . ' line ' . __LINE__);
			}
			// The transaction writing the target year carries it on itself or leaves it to the next check, which also
			// picks up this source year; the later years depend on this one, so they wait too.
			$deferredYear = $sourceYear;
			if (!$pendingYear) $pendingYear = $sourceYear;
			break;
		}
		// The years' dates are read again here, after the target year is locked, and are part of the inputs: a card
		// that changed the source year's dates and carried it on before this lock is calculated from (CodeRabbit).
		$inputs = followingOpeningBalanceInputs($sourceYear, $targetYear);
		for ($attempt = 1; ; $attempt++) {
			$blocked = followingOpeningBalanceStopShape($sourceYear, $targetYear);
			$invalid = followingOpeningBalanceInvalidDates($blocked, $inputs['sourceRange'], $inputs['targetRange']);
			$opening = $invalid ? null : followingOpeningBalanceTransfer($inputs, $blocked);
			if ($opening === null) {
				// A balance has no account to land on in the target year, or a year's dates became invalid meanwhile,
				// so leave it and later years untouched.
				if ($savepoint) {
					db_modify('rollback to savepoint following_opening_balance_transfer', __FILE__ . ' line ' . __LINE__);
					db_modify('release savepoint following_opening_balance_transfer', __FILE__ . ' line ' . __LINE__);
				}
				$stop = $blocked;
				break 2;
			}
			foreach ($opening as $account => $amount) {
				$amount = (float)afrund($amount, 2);
				db_modify("update kontoplan set primo = '$amount' where regnskabsaar = '$targetYear' and kontonr = '$account' and kontotype = 'S'", __FILE__ . ' line ' . __LINE__);
			}
			// A posting, another rebuild or a year card may have changed the source year or its dates since the inputs
			// were read, even back to how they were. If the inputs read now are the very ones this result was
			// calculated from, it is current: the target rows are locked, so whoever changes the source year from now
			// on carries it forward after this commits (or the next check does). Otherwise calculate again from what
			// was just read, with the dates read now.
			$current = followingOpeningBalanceInputs($sourceYear, $targetYear);
			if ($current === $inputs) {
				break;
			}
			if ($attempt >= 5) {
				// Still changing: keep this result and leave the source year waiting, so a later check rebuilds it.
				// No exception here: the journal posting in finans/bogfor.php runs this inside its own transaction.
				if (!$pendingYear) $pendingYear = $sourceYear;
				break;
			}
			$inputs = $current;
		}
		if ($savepoint) {
			db_modify('release savepoint following_opening_balance_transfer', __FILE__ . ' line ' . __LINE__);
		}
		// Avoid switching database connections while the journal transaction is active. Not genberegn(): a page's own
		// function of that name (debitor/ordreliste.php) would be called instead.
		recalculateLedgerYear($targetYear, false);
		$updated[] = $targetYear;
	}
	if ($pendingYear && !followingOpeningBalanceMarkPending($pendingYear)) {
		// The settings row comes after every kontoplan row, the order the callers take it in too. A year that cannot be
		// left waiting (MySQL: the lock for creating the row was not given) must not be lost: the caller rolls back and
		// the same work is done again (CodeRabbit). Only possible before the tenant's very first check created the row.
		throw new RuntimeException('SST-1005: fiscal year ' . $pendingYear . ' could not be left waiting for its later years');
	}
	return $updated;
}

/**
 * Note in $blocked which of the two years of a transfer has no valid start and end month.
 *
 * @param array $blocked The stop shape for this transition; invalidDates is filled in.
 * @param array|null $sourceRange followingOpeningBalanceYearRange() of the source year.
 * @param array|null $targetRange followingOpeningBalanceYearRange() of the target year.
 * @return bool True when either year's dates are invalid, so nothing of the transfer may be written.
 */
function followingOpeningBalanceInvalidDates(&$blocked, $sourceRange, $targetRange) {
	if (!$sourceRange) $blocked['invalidDates'][] = $blocked['sourceYear'];
	if (!$targetRange) $blocked['invalidDates'][] = $blocked['targetYear'];
	return (bool)$blocked['invalidDates'];
}

/**
 * The opening balances of the target year carried from the source year, by the transfer rules of
 * fiscalYearInc/yearX.php. Calculated from $inputs only, so the result belongs to exactly those inputs.
 *
 * @param array $inputs The transfer's inputs, see followingOpeningBalanceInputs().
 * @param array $blocked The stop shape for this transition; the reasons are filled in when a balance cannot land.
 * @return array|null Status account => opening balance in the target year, or null when the transfer is blocked.
 */
function followingOpeningBalanceTransfer($inputs, &$blocked) {
	$opening = array();
	foreach ($inputs['targetAccounts'] as $account) {
		$opening[$account] = 0;
	}
	if (!$opening) {
		$blocked['noStatusAccounts'] = true;
		return null;
	}
	$movement = $inputs['movement'];
	$profit = 0;
	$resultAccount = null;
	foreach ($inputs['sourceAccounts'] as $row) {
		$account = $row['kontonr'];
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
		return null;
	}
	return $opening;
}

/**
 * Leave $year waiting, so the next check in a later year rebuilds the years after it. The waiting year is the settings
 * row 'openingBalancePending' (a year number, '0' when nothing waits), apart from the watermark and fingerprint in
 * 'openingBalanceSync', because a check saves those from what it read at its start and must never erase a year
 * another request left waiting meanwhile (Codex round 11). This one statement lowers it to $year or keeps a lower
 * year, so concurrent requests can only add work. It always writes the row: inside a transaction the row then stays
 * locked until that transaction commits, so syncFollowingOpeningBalances() cannot take the year away before the
 * changes it stands for are visible. When the row does not exist yet (no check has started following, or the first
 * one is still starting), it is created here with $year, see followingOpeningBalanceCreatePending(), so a year left
 * waiting is never dropped (Codex round 12).
 * Called after the rebuild's last kontoplan write; the settings rows always come last.
 *
 * @param int $year Fiscal year (kodenr).
 * @return bool True when the year is left waiting (or nothing was asked); false only when the row had to be created
 *   and the lock for that was not given (see followingOpeningBalanceCreatePending()), so the caller must not go on as
 *   if the year were registered.
 */
function followingOpeningBalanceMarkPending($year) {
	global $db_type;
	$year = (int)$year;
	if ($year < 1) {
		return true;
	}
	// Inside a transaction the row is locked by its remembered id before anything of it is read, and lowered there
	// (Codex round 32: under SERIALIZABLE a plain read first and the write after deadlocked between two rebuilds).
	$row = followingOpeningBalanceLockRow('openingBalancePending');
	if ($row) {
		followingOpeningBalanceLowerPending($year, $row['id']);
		return true;
	}
	if (followingOpeningBalancePending() === null) {
		// No row visible: it does not exist, or another request created it and has not committed. Under the lock in
		// followingOpeningBalanceCreatePending() the row is read again and this year inserted or lowered into it, so a
		// row that appears between a read and a write here can never skip this year (Codex round 14).
		return followingOpeningBalanceCreatePending($year);
	}
	// The row is committed and visible, so this statement finds it (no row is ever deleted).
	followingOpeningBalanceLowerPending($year);
	return true;
}

/**
 * Lower 'openingBalancePending' to $year, or keep the lower year it holds, in one statement that always writes the row
 * (see followingOpeningBalanceMarkPending()).
 *
 * @param int $year Fiscal year (kodenr), 1 or later.
 * @param int $id The row's id when it is known and locked (followingOpeningBalanceLockRow()), else 0 (by name).
 * @return void
 */
function followingOpeningBalanceLowerPending($year, $id = 0) {
	global $db_type;
	$year = (int)$year;
	$id = (int)$id;
	$type = ($db_type === 'mysql' || $db_type === 'mysqli') ? 'signed' : 'integer';
	$current = "cast(coalesce(nullif(var_value, ''), '0') as $type)";
	$qtxt = "update settings set var_value = case when $current = 0 or $current > $year then '$year' else var_value end ";
	$qtxt.= $id ? "where id = '$id'" : "where var_name = 'openingBalancePending'";
	db_modify($qtxt, __FILE__ . ' line ' . __LINE__);
}

/**
 * Create 'openingBalancePending' with $year when it does not exist, or lower it to $year when another request created
 * it meanwhile. Creating the row is serialised by an advisory lock, so there is only ever one row:
 * - inside a transaction (a rebuild that left a year waiting) the lock is pg_advisory_xact_lock and is held until that
 *   transaction commits, so a first check that starts creating the row waits until it can see this request's row;
 * - outside one (the first check, followingOpeningBalanceInitialize()) it is a session lock released right after.
 * Whoever waits for the lock holds nothing else of this feature: a rebuild only gets here after its last kontoplan
 * write, and the first check runs in autocommit. MySQL has no transaction-level advisory lock; there GET_LOCK is taken
 * and only used when MySQL gives it (1; 0 is a time-out, NULL an error). Inside a transaction it is kept until that
 * transaction ends (followingOpeningBalanceGetLock()), not released before the caller's COMMIT (Lui's pre-check), so the
 * next creator only reads once this row is committed (Codex rounds 28-29).
 *
 * @param int $year Fiscal year to leave waiting, or 0 to only create the row.
 * @return bool True when the row was created or lowered; false when MySQL did not give the lock (followingOpeningBalanceGetLock()),
 *   and then nothing was read or written (CodeRabbit).
 */
function followingOpeningBalanceCreatePending($year) {
	global $db_type;
	$year = (int)$year;
	$mysql = ($db_type === 'mysql' || $db_type === 'mysqli');
	$transaction = followingOpeningBalancesInTransaction();
	if ($mysql) {
		if (!followingOpeningBalanceGetLock('openingBalancePending')) {
			return false;
		}
	} elseif ($transaction) {
		db_select("SELECT pg_advisory_xact_lock(hashtext('openingBalancePending'), 1005)", __FILE__ . ' line ' . __LINE__);
	} else {
		db_select("SELECT pg_advisory_lock(hashtext('openingBalancePending'), 1005)", __FILE__ . ' line ' . __LINE__);
	}
	try {
		// Created only when a current read under this lock still finds no row (followingOpeningBalanceEnsureSetting()),
		// so a MySQL transaction whose snapshot predates another request's row does not add a second one (Codex round 28).
		$description = 'SST-1005: earliest fiscal year waiting for its later years to get new opening balances (0 = none)';
		followingOpeningBalanceEnsureSetting('openingBalancePending', (string)max(0, $year), $description, true);
		if ($year > 0) {
			// Written even when the row already holds this year or an earlier one: like every MarkPending, the write keeps
			// the row locked until this transaction commits, so syncFollowingOpeningBalances() cannot take the year and
			// rebuild before the changes it stands for are visible (Codex round 15).
			followingOpeningBalanceLowerPending($year);
		}
	} finally {
		if ($mysql && !$transaction) {
			followingOpeningBalanceReleaseLock('openingBalancePending');
		} elseif (!$mysql && !$transaction) {
			db_select("SELECT pg_advisory_unlock(hashtext('openingBalancePending'), 1005)", __FILE__ . ' line ' . __LINE__);
		}
	}
	return true;
}

/**
 * The year left waiting in 'openingBalancePending', see followingOpeningBalanceMarkPending().
 *
 * @return int|null The fiscal year, 0 when nothing waits, or null when the row does not exist yet.
 */
function followingOpeningBalancePending() {
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'openingBalancePending' order by id limit 1", __FILE__ . ' line ' . __LINE__));
	return $r ? (int)$r['var_value'] : null;
}

/**
 * Take the waiting year for a rebuild that starts now: it is cleared only while it still holds the value read, so a
 * lower year registered meanwhile stays (and is taken on the next pass). A request that registered a year inside its
 * transaction holds the row until it commits, so the clear waits for it and the rebuild then reads what it committed.
 *
 * @return int The earliest waiting fiscal year that this rebuild takes on, or 0.
 */
function followingOpeningBalanceTakePending() {
	$taken = 0;
	for ($pass = 0; $pass < 3; $pass++) {
		$year = (int)followingOpeningBalancePending();
		if ($year < 1 || ($taken && $year >= $taken)) {
			break;
		}
		// By its remembered id when there is one: MySQL has no index on settings.var_name, and an UPDATE by name locks
		// every settings row it scans, the block row too, which a rebuild or card may hold while it waits for this one
		// (Codex round 33).
		$id = isset($GLOBALS['followingOpeningBalanceRowIds']['openingBalancePending']) ? (int)$GLOBALS['followingOpeningBalanceRowIds']['openingBalancePending'] : 0;
		$where = $id ? "id = '$id'" : "var_name = 'openingBalancePending'";
		db_modify("update settings set var_value = '0' where $where and var_value = '$year'", __FILE__ . ' line ' . __LINE__);
		$taken = $year;
	}
	return $taken;
}

/**
 * Everything a transfer from $sourceYear to $targetYear is calculated from: the first and last date of both years,
 * the status accounts of the target year, the source year's movement per account (each posting rounded to øre, as in
 * fiscalYearInc/yearX.php) and every status, result and Sideskift account of the source year with its type, opening
 * balance and «Overfør til».
 * updateFollowingOpeningBalances() calculates a transfer from one read and compares it with a read made after the
 * transfer is written. Because it compares the very inputs the result came from, a change that is undone while the
 * transfer is calculated is noticed too, and so is an amount moved from one account to another without changing
 * any total, and a year card that changed the source year's dates (CodeRabbit). Two reads of the same data are
 * identical (===).
 * On PostgreSQL (read committed) the second read sees what other transactions committed meanwhile; a MySQL
 * transaction in its default repeatable read keeps seeing its own snapshot, so there the check finds nothing new.
 *
 * @param int $sourceYear Fiscal year (kontoplan.regnskabsaar) carried from.
 * @param int $targetYear Fiscal year carried into.
 * @return array{
 *   sourceRange: array{0: string, 1: string}|null, First and last date of the source year, null when invalid
 *                                          (see followingOpeningBalanceYearRange()); then no movement is read.
 *   targetRange: array{0: string, 1: string}|null, The same for the target year, whose dates recalculateLedgerYear() reads.
 *   targetAccounts: int[],                 Status accounts of the target year, in account order.
 *   movement: array<int, float>,           Source year movement per account in account order, in øre.
 *   sourceAccounts: array<int, array{kontonr: int, kontotype: string, primo: string, overfor_til: string|null}>,
 * }
 */
function followingOpeningBalanceInputs($sourceYear, $targetYear) {
	$sourceYear = (int)$sourceYear;
	$targetYear = (int)$targetYear;
	$inputs = array('sourceRange' => null, 'targetRange' => null, 'targetAccounts' => array(), 'movement' => array(), 'sourceAccounts' => array());
	$q = db_select("select kodenr, box1, box2, box3, box4 from grupper where art = 'RA' and kodenr in ('$sourceYear', '$targetYear')", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$inputs[(int)$row['kodenr'] === $sourceYear ? 'sourceRange' : 'targetRange'] = followingOpeningBalanceYearRange($row);
	}
	$q = db_select("select kontonr from kontoplan where regnskabsaar = '$targetYear' and kontotype = 'S' order by kontonr", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$inputs['targetAccounts'][] = (int)$row['kontonr'];
	}
	if ($inputs['sourceRange']) {
		list($start, $end) = $inputs['sourceRange'];
		$q = db_select("select kontonr, debet, kredit from transaktioner where transdate >= '$start' and transdate <= '$end'", __FILE__ . ' line ' . __LINE__);
		while ($row = db_fetch_array($q)) {
			$account = (int)$row['kontonr'];
			if (!isset($inputs['movement'][$account])) $inputs['movement'][$account] = 0;
			$inputs['movement'][$account] += afrund($row['debet'] - $row['kredit'], 2);
		}
	}
	// Every posting is rounded to øre, so the sum is a whole number of øre; rounding it once more only drops the
	// floating point noise of the order the rows came in, so two reads of the same postings compare equal.
	foreach ($inputs['movement'] as $account => $amount) {
		$inputs['movement'][$account] = afrund($amount, 2);
	}
	ksort($inputs['movement']);
	$qtxt = "select kontonr, kontotype, primo, overfor_til from kontoplan ";
	$qtxt.= "where regnskabsaar = '$sourceYear' and kontotype in ('S', 'D', 'X') order by kontonr, id";
	$q = db_select($qtxt, __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$inputs['sourceAccounts'][] = array(
			'kontonr' => (int)$row['kontonr'],
			'kontotype' => $row['kontotype'],
			'primo' => $row['primo'],
			'overfor_til' => $row['overfor_til'],
		);
	}
	return $inputs;
}

/**
 * Lock every kontoplan row of $year before a rebuild reads or writes it, without ever waiting for a row another
 * transaction holds. A journal posting (finans/bogfor.php) or an order (saldo per account) locks the accounts of its
 * year one at a time, in the order of its lines, so a rebuild that waited for one of them while holding another could
 * deadlock with it (Codex round 10: the journal holds 2000, the rebuild holds 1000 and waits for 2000, the journal
 * then needs 1000). Here the rows that are free are locked with SKIP LOCKED; when any row was busy, the partial lock
 * is undone at once and tried again shortly. While the rebuild holds the whole year, recalculateLedgerYear() and the writes need
 * no further row of it, so the rebuild itself never waits for a kontoplan row.
 * Outside a transaction a lock would end with the statement, so nothing is locked and true is returned, as before.
 * MySQL 8 and MariaDB 10.6 have SKIP LOCKED too and lock the same way (Lui's pre-check); an older MySQL server keeps
 * the plain lock that waits, and its deadlock detection then rolls one transaction back.
 *
 * @param int $year Fiscal year (kontoplan.regnskabsaar).
 * @param int $attempts How many times to try; between tries the rebuild sleeps 0.1 second.
 * @return bool True when every row of the year is locked by this transaction; false when another transaction kept
 *   at least one of them for all the attempts (then nothing of the year is locked).
 */
function followingOpeningBalanceTryLockYear($year, $attempts = 30) {
	global $db_type;
	$year = (int)$year;
	if (!followingOpeningBalancesInTransaction()) {
		return true;
	}
	if (($db_type === 'mysql' || $db_type === 'mysqli') && !followingOpeningBalanceSkipLocked()) {
		db_select("select id from kontoplan where regnskabsaar = '$year' for update", __FILE__ . ' line ' . __LINE__);
		return true;
	}
	for ($attempt = 1; $attempt <= $attempts; $attempt++) {
		db_modify('savepoint following_opening_balance_lock', __FILE__ . ' line ' . __LINE__);
		$locked = 0;
		$q = db_select("select id from kontoplan where regnskabsaar = '$year' for update skip locked", __FILE__ . ' line ' . __LINE__);
		while (db_fetch_array($q)) {
			$locked++;
		}
		// Counted after the lock: a row this transaction could not lock is still counted here.
		$r = db_fetch_array(db_select("select count(*) as antal from kontoplan where regnskabsaar = '$year'", __FILE__ . ' line ' . __LINE__));
		if ($locked >= (int)$r['antal']) {
			db_modify('release savepoint following_opening_balance_lock', __FILE__ . ' line ' . __LINE__);
			return true;
		}
		// Rolling back to the savepoint releases the rows locked in this attempt, so nothing is held while waiting.
		db_modify('rollback to savepoint following_opening_balance_lock', __FILE__ . ' line ' . __LINE__);
		db_modify('release savepoint following_opening_balance_lock', __FILE__ . ' line ' . __LINE__);
		if ($attempt < $attempts) {
			usleep(100000);
		}
	}
	return false;
}

/**
 * Whether the database server knows SELECT ... FOR UPDATE SKIP LOCKED: PostgreSQL always (9.5 and later), MySQL from
 * 8.0.1 and MariaDB from 10.6. Asked once per request.
 *
 * @return bool True when followingOpeningBalanceTryLockYear() can lock without waiting.
 */
function followingOpeningBalanceSkipLocked() {
	global $db_type;
	static $supported = null;
	if ($db_type !== 'mysql' && $db_type !== 'mysqli') {
		return true;
	}
	if ($supported === null) {
		$r = db_fetch_array(db_select("select version() as version", __FILE__ . ' line ' . __LINE__));
		$supported = followingOpeningBalanceVersionSkipsLocked($r ? (string)$r['version'] : '');
	}
	return $supported;
}

/**
 * Whether a MySQL or MariaDB server of this version() knows SKIP LOCKED.
 *
 * @param string $version version(), e.g. '8.0.36' or '10.11.14-MariaDB-0ubuntu0.24.04.1'.
 * @return bool True for MySQL 8.0.1 or later and MariaDB 10.6 or later.
 */
function followingOpeningBalanceVersionSkipsLocked($version) {
	if (!preg_match('/^(\d+\.\d+\.\d+)/', (string)$version, $m)) {
		return false;
	}
	return stripos((string)$version, 'mariadb') !== false ? version_compare($m[1], '10.6.0', '>=') : version_compare($m[1], '8.0.1', '>=');
}

/**
 * The year card's «Overfør til» choices on the accounts of the year before $year, written before the card locks
 * $year itself (systemdata/regnskabskort.php). A journal posting in the year before holds its accounts there and
 * then carries that year into $year (finans/bogfor.php), so a card that held $year while it waited for those
 * accounts would deadlock with it. Only choices that change are written, so a card saved with its choices as they
 * were never waits for the year before. The card does the same as before for $year, and for the year before when
 * $year is new (no other transaction can see a new year's rows yet).
 *
 * @param int $year The fiscal year whose card is saved.
 * @param array $accounts The card's kontonr per row.
 * @param array $destinations The card's «Overfør til» per row; a row without one is left alone, like the card does.
 * @param int $rows The card's highest row number (kontoantal).
 * @return void
 */
function followingOpeningBalanceSaveLastYearChoices($year, $accounts, $destinations, $rows) {
	$year = (int)$year;
	$lastYear = $year - 1;
	if ($lastYear < 1) {
		return;
	}
	// The card writes the choices only for an existing year after a year that is not deleted.
	$r = db_fetch_array(db_select("select box10 from grupper where art = 'RA' and kodenr = '$lastYear'", __FILE__ . ' line ' . __LINE__));
	if (($r && followingOpeningBalanceYearDeleted($r['box10'])) || !db_fetch_array(db_select("select id from kontoplan where regnskabsaar = '$year' limit 1", __FILE__ . ' line ' . __LINE__))) {
		return;
	}
	for ($x = 0; $x <= (int)$rows; $x++) {
		$destination = isset($destinations[$x]) ? (int)$destinations[$x] : 0;
		$account = isset($accounts[$x]) ? (int)$accounts[$x] : 0;
		if ($destination) {
			$qtxt = "update kontoplan set overfor_til = '$destination' where kontonr = '$account' and regnskabsaar = '$lastYear' ";
			$qtxt.= "and coalesce(overfor_til, 0) <> '$destination'";
			db_modify($qtxt, __FILE__ . ' line ' . __LINE__);
		}
	}
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
	$qtxt.= "(select var_value from settings where var_name = 'openingBalanceSync' order by id limit 1) as state, ";
	$qtxt.= "(select var_value from settings where var_name = 'openingBalancePending' order by id limit 1) as pending, ";
	// The rows' ids for a transaction later in this request, such as a kassekladde posting (followingOpeningBalanceLockRow()).
	$qtxt.= "(select id from settings where var_name = 'openingBalancePending' order by id limit 1) as pending_id, ";
	$qtxt.= "(select id from settings where var_name = 'openingBalanceStop' order by id limit 1) as stop_id";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . ' line ' . __LINE__));
	if (!$r) {
		return null;
	}
	followingOpeningBalanceRememberRows(array('openingBalancePending' => (int)$r['pending_id'], 'openingBalanceStop' => (int)$r['stop_id']));
	$state = ($r['state'] === null || $r['state'] === '') ? null : followingOpeningBalanceStateParts($r['state']);
	$pending = $r['pending'] === null ? null : (int)$r['pending'];
	$found = 0;
	if ($state === null || $state['fingerprint'] === null || $pending === null) {
		$initial = followingOpeningBalanceInitialize();
		if ($initial === null) {
			return null;
		}
		list($state, $maxId, $changed) = $initial;
		$pending = (int)followingOpeningBalancePending();
	} else {
		$maxId = (int)$r['max_id'];
		$changed = false;
		if (time() - $state['fingerprintAt'] >= 600) {
			// Postings that commit after a check with lower ids than it saw, and deleted postings, show up here.
			$fingerprint = followingOpeningBalanceFingerprint();
			$year = followingOpeningBalanceFingerprintChange($state['fingerprint'], $fingerprint);
			if ($year) $found = $year;
			$state['fingerprint'] = $fingerprint;
			$state['fingerprintAt'] = time();
			$changed = true;
		}
	}
	if ($state['pendingYear'] > 0) {
		// A state of an earlier test build still holds a waiting year. It is lowered into the row of its own like any
		// other finding, also when another request created that row first, and only then is the state saved without it.
		if (!$found || $state['pendingYear'] < $found) $found = $state['pendingYear'];
		$changed = true;
	}
	if ($maxId > $state['lastId']) {
		$year = followingOpeningBalancePostedYear($state['lastId'], $maxId);
		if ($year && (!$found || $year < $found)) $found = $year;
		$state['lastId'] = $maxId;
		$changed = true;
	}
	if ($found) {
		// Registered before the watermark and fingerprint that cover it are saved, and only ever lowering the row. When
		// it cannot be registered (MySQL lock not given), neither is saved, so the next page finds it again.
		if (!followingOpeningBalanceMarkPending($found)) {
			return null;
		}
		$pending = (int)followingOpeningBalancePending();
	}
	if ($changed) {
		// Another check may save its own watermark and fingerprint over these; the waiting year is not in this row.
		saveFollowingOpeningBalanceState($state);
	}
	if (!$pending || $activeYear <= $pending) {
		return null;
	}
	// A waiting year that was deleted meanwhile has nothing left to carry; the rebuild starts at the first year after it,
	// and only once a session is in a year later than that one.
	if ($activeYear <= followingOpeningBalanceFirstLiveYear($pending)) {
		return null;
	}
	return syncFollowingOpeningBalances($pending);
}

/**
 * The first fiscal year from $year on that exists and is not deleted. Years are deleted oldest first
 * (systemdata/fiscalYearInc/deleteFiscalYear.php), and the first year after a deleted year keeps opening balances of its
 * own, so a rebuild asked to start at a deleted or missing year starts there instead. Without it the rebuild stopped at
 * the deleted year at once, while the waiting year it took also stood for later work the check had lowered into it, so
 * the years after it stayed behind and nothing was left waiting (CodeRabbit on PR #1109).
 *
 * @param int $year Fiscal year (grupper art 'RA' kodenr).
 * @return int That year, or the first live year after it, or $year when there is none.
 */
function followingOpeningBalanceFirstLiveYear($year) {
	$year = (int)$year;
	$q = db_select("select kodenr, box10 from grupper where art = 'RA' and kodenr >= '$year' order by kodenr", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		if (!followingOpeningBalanceYearDeleted($row['box10'])) {
			return (int)$row['kodenr'];
		}
	}
	return $year;
}

/**
 * Start following postings, one check at a time. Under a lock the state is read again: a state that another check
 * stored meanwhile is used as it is and never replaced, so postings that committed after that check started
 * following are compared against its baseline. Otherwise the fingerprint is taken first and the watermark (max id)
 * read after it: a posting that commits in between is missing from the baseline, so the next fingerprint finds it
 * even though its id is below the watermark. Both are stored together. Postings before that are history, which
 * «Sæt aktivt» brings up to date.
 *
 * @return array|null array{0: array, 1: int, 2: bool}: the state to continue from (see
 *   followingOpeningBalanceStateParts()), max(id) read now and whether the state still has to be saved (also after
 *   storing the very first state, so a year already left waiting is rebuilt); or null when another check is starting
 *   right now or MySQL did not give the lock for creating the 'openingBalancePending' row (the next page continues).
 *   That row is created here when it is missing.
 */
function followingOpeningBalanceInitialize() {
	if (!followingOpeningBalanceInitLock(true)) {
		return null;
	}
	try {
		$r = db_fetch_array(db_select("select var_value from settings where var_name = 'openingBalanceSync' order by id limit 1", __FILE__ . ' line ' . __LINE__));
		$state = ($r && $r['var_value'] !== null && $r['var_value'] !== '') ? followingOpeningBalanceStateParts($r['var_value']) : null;
		if ($state === null || $state['fingerprint'] === null) {
			$fingerprint = followingOpeningBalanceFingerprint();
		}
		$row = db_fetch_array(db_select("select max(id) as max_id from transaktioner", __FILE__ . ' line ' . __LINE__));
		$maxId = (int)$row['max_id'];
		// A state from an earlier test build kept the waiting year in its JSON; it moves to the row of its own before
		// any state without it is saved, also when the row exists already, so a request that ends in between cannot
		// lose it (CodeRabbit). Lowering is safe to repeat. A request that left a year waiting meanwhile may create the
		// row first; its year is kept (lowered at most).
		$legacyYear = $state === null ? 0 : (int)$state['pendingYear'];
		if ($legacyYear > 0) {
			$registered = followingOpeningBalanceMarkPending($legacyYear);
		} else {
			$registered = followingOpeningBalancePending() !== null || followingOpeningBalanceCreatePending(0);
		}
		if (!$registered) {
			// MySQL did not give the lock for creating the row: nothing is saved, and the next page tries again.
			return null;
		}
		// The block row too, here in autocommit, so a later transaction never has to create it (Codex round 31).
		followingOpeningBalanceEnsureSetting('openingBalanceStop', '', 'SST-1005: fiscal year transition whose opening balances could not be updated automatically');
		followingOpeningBalanceRememberRows();
		if ($state === null) {
			$state = array('lastId' => $maxId, 'pendingYear' => 0, 'fingerprintAt' => time(), 'fingerprint' => $fingerprint);
			saveFollowingOpeningBalanceState($state);
			// A year a card or journal left waiting before this first check is rebuilt by it (Codex round 12).
			return array($state, $maxId, false);
		}
		if ($state['fingerprint'] === null) {
			// A state from before the fingerprint keeps its watermark and waiting year, and gets its first fingerprint now.
			$state['fingerprint'] = $fingerprint;
			$state['fingerprintAt'] = time();
			saveFollowingOpeningBalanceState($state);
		}
		return array($state, $maxId, false);
	} finally {
		followingOpeningBalanceInitLock(false);
	}
}

/**
 * Take, without waiting, or release the lock under which a tenant's first state is created.
 *
 * @param bool $take True to take it, false to release it.
 * @return bool True when the lock was taken or released.
 */
function followingOpeningBalanceInitLock($take) {
	global $db_type;
	if ($db_type == 'mysql' || $db_type == 'mysqli') {
		// GET_LOCK names are server-wide, so the database is part of the name.
		$name = "CONCAT('saldi:openingBalanceSync:', MD5(DATABASE()))";
		$qtxt = $take ? "SELECT GET_LOCK($name, 0) AS locked" : "SELECT RELEASE_LOCK($name) AS locked";
	} else {
		$qtxt = $take ? "SELECT pg_try_advisory_lock(hashtext('openingBalanceSync'), 1005) AS locked" : "SELECT pg_advisory_unlock(hashtext('openingBalanceSync'), 1005) AS locked";
	}
	$r = db_fetch_array(db_select($qtxt, __FILE__ . ' line ' . __LINE__));
	return $r && in_array((string)$r['locked'], array('1', 't', 'true'), true);
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
	// The rows it writes exist before its transaction begins (Codex round 31).
	followingOpeningBalancePrepareRows();
	// The rebuild runs in a page request: a failed statement there ends the request (db_modify() shows the error box and
	// exits), so the catch below never runs. The guard then rolls back and stores the same message on shutdown. It is
	// armed before the waiting year is taken, so a request that ends between taking it and the rebuild (a time-out in
	// the lookups below) still leaves the message instead of losing the work without a word (CodeRabbit).
	$guarded = followingOpeningBalanceFailureGuard($fromYear > 0 ? $fromYear : $year, $fromYear > 0 ? $fromYear : $year);
	// Taken before the rebuild, so a failing rebuild is not retried on every page. A year registered after this is
	// left for the next check.
	$pending = followingOpeningBalanceTakePending();
	if ($pending) {
		$fromYear = $fromYear > 0 ? min($fromYear, $pending) : $pending;
	}
	$checkedFrom = $fromYear;
	// A deleted year cannot be carried from; the first year after it carries its own opening balances on.
	$fromYear = followingOpeningBalanceFirstLiveYear($fromYear);
	if ($guarded) {
		// Now the years are known, the message the guard would store names them.
		followingOpeningBalanceFailureGuard(null);
		followingOpeningBalanceFailureGuard($fromYear, $checkedFrom);
	}
	$stop = null;
	$deferredYear = 0;
	transaktion('begin');
	try {
		if ($year > 0) {
			carryFollowingOpeningBalances($year, $fromYear, $stop, $deferredYear);
		} else {
			updateFollowingOpeningBalances($fromYear, $stop, $deferredYear);
		}
	} catch (Throwable $e) {
		// Nothing of the rebuild is kept, and the stored message tells the user to retry with Gem/opdatér.
		transaktion('rollback');
		followingOpeningBalanceReleaseLocks();
		error_log('SST-1005: opening balances after fiscal year ' . $fromYear . ' not updated: ' . $e->getMessage());
		$stop = followingOpeningBalanceStopShape($fromYear, $fromYear + 1);
		$stop['failed'] = true;
		saveFollowingOpeningBalanceStop($stop, $checkedFrom);
		if ($guarded) followingOpeningBalanceFailureGuard(null);
		return $stop;
	}
	// A transfer left for the next check did not reach a blocked transition stored at or after it, so that stays.
	saveFollowingOpeningBalanceStop($stop, $checkedFrom, $deferredYear);
	if (followingOpeningBalanceCommit() && $guarded) followingOpeningBalanceFailureGuard(null);
	return $stop;
}

/**
 * Commit the transaction a rebuild or the year card began, and say whether it was committed. transaktion()
 * (includes/db_query.php) does not report a COMMIT that the database refuses (a deferred constraint or trigger, a
 * serialization failure), and the transaction is then rolled back. Sent through db_modify() instead, a refused COMMIT
 * is reported like any failed statement: in a page request the error box and exit, after which the guard that is
 * still armed stores the 'failed' message on shutdown (Codex round 23). As a webservice db_modify() returns the error
 * instead; then this returns false and the caller leaves the guard armed, so the message is stored when the request
 * ends. The nesting count transaktion() keeps is lowered as transaktion('commit') does.
 *
 * @return bool True when the transaction was committed.
 */
function followingOpeningBalanceCommit() {
	global $db_transaktion_depth;
	$result = db_modify('commit', __FILE__ . ' line ' . __LINE__);
	if ($db_transaktion_depth) {
		$db_transaktion_depth--;
	}
	followingOpeningBalanceReleaseLocks();
	return !(is_string($result) && substr($result, 0, 1) === '1');
}

/**
 * Arm or disarm the guard for a rebuild that a page request may end before it finishes. In a browser request
 * db_modify() (includes/db_query.php) does not throw on a failed statement: it shows the error box and calls exit, so
 * a catch around the rebuild never runs, the connection is closed with the transaction rolled back, and the waiting
 * year taken before it was lost without a message (CodeRabbit on PR #1109). A fatal error or a time-out ends the
 * request the same way. While armed, followingOpeningBalanceFailureOnShutdown() rolls back what is still open and
 * stores the 'failed' message the catch would have stored, so the user is told to retry with Gem/opdatér.
 * The year card arms it right after its transaction begins, because its own writes (the primo reset) can fail
 * before updateOpeningBalancesAfterYearSave() is reached, and disarms it after its commit (Codex round 22). A guard
 * that is already armed is kept as it is: a nested caller gets false and must not disarm it.
 *
 * @param int|null $sourceYear The year the rebuild carries from, or null to disarm.
 * @param int $checkedFrom The year the caller checks from (see saveFollowingOpeningBalanceStop()).
 * @return bool True when this call armed the guard (the caller disarms it), false when it was armed already or on
 *   disarming.
 */
function followingOpeningBalanceFailureGuard($sourceYear, $checkedFrom = 0) {
	static $registered = false;
	if ($sourceYear === null) {
		$GLOBALS['followingOpeningBalanceFailure'] = null;
		return false;
	}
	if (!empty($GLOBALS['followingOpeningBalanceFailure'])) {
		return false;
	}
	$GLOBALS['followingOpeningBalanceFailure'] = array('sourceYear' => (int)$sourceYear, 'checkedFrom' => (int)$checkedFrom);
	if (!$registered) {
		$registered = true;
		register_shutdown_function('followingOpeningBalanceFailureOnShutdown');
	}
	return true;
}

/**
 * Shutdown function of followingOpeningBalanceFailureGuard(): when the request ended while a rebuild was armed, roll
 * back whatever is still open (the rebuild, and for the year card the card's own save, which db_modify() abandoned
 * too) and store the 'failed' message. Statements here run as for a webservice, so a second failure returns instead of
 * drawing another error box; it is only logged.
 *
 * @return void
 */
function followingOpeningBalanceFailureOnShutdown() {
	$guard = isset($GLOBALS['followingOpeningBalanceFailure']) ? $GLOBALS['followingOpeningBalanceFailure'] : null;
	$GLOBALS['followingOpeningBalanceFailure'] = null;
	if (!$guard) {
		return;
	}
	$webservice = isset($GLOBALS['webservice']) ? $GLOBALS['webservice'] : null;
	$GLOBALS['webservice'] = true;
	try {
		for ($i = 0; $i < 10 && followingOpeningBalancesInTransaction(); $i++) {
			transaktion('rollback');
		}
		followingOpeningBalanceReleaseLocks();
		error_log('SST-1005: opening balances after fiscal year ' . $guard['sourceYear'] . ' not updated: the request ended during the rebuild and it was rolled back');
		$stop = followingOpeningBalanceStopShape($guard['sourceYear'], $guard['sourceYear'] + 1);
		$stop['failed'] = true;
		saveFollowingOpeningBalanceStop($stop, $guard['checkedFrom']);
	} catch (Throwable $e) {
		error_log('SST-1005: the message for an interrupted rebuild could not be stored: ' . $e->getMessage());
	} finally {
		$GLOBALS['webservice'] = $webservice;
	}
}

/**
 * Carry $fromYear (normally the year before $year) into the later years. When that does not get past $year
 * without a block, because there is no usable year before it (year 1, a missing or deleted year), $year's own
 * opening balances are carried forward from $year. A real block is never bypassed. The caller owns the transaction.
 *
 * @param int $year The year being saved or activated.
 * @param int $fromYear In: where to start. Out: $year when the rebuild had to start there.
 * @param array|null $stop Receives the block, see updateFollowingOpeningBalances().
 * @param int $deferredYear Receives 0, or the source year whose transfer was left for a later check (see
 *   updateFollowingOpeningBalances()); a stored block at or after it was not reached and must stay.
 * @return int[] Fiscal year numbers whose opening balances were updated.
 */
function carryFollowingOpeningBalances($year, &$fromYear, &$stop, &$deferredYear = null) {
	$year = (int)$year;
	$updated = updateFollowingOpeningBalances($fromYear, $stop, $deferredYear);
	// A transfer left for the next check is not a missing year before $year, so $year is not carried from itself then.
	if (!$stop && !$deferredYear && !in_array($year + 1, $updated, true)) {
		$fromYear = $year;
		$updated = array_merge($updated, updateFollowingOpeningBalances($year, $stop, $deferredYear));
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
		// The year card calls followingOpeningBalancePrepareRows() itself before its transaction (Codex round 31).
		followingOpeningBalancePrepareRows();
		transaktion('begin');
	}
	db_modify('savepoint following_opening_balances', __FILE__ . ' line ' . __LINE__);
	$stop = null;
	$deferredYear = 0;
	$fromYear = $year - 1;
	$checkedFrom = $fromYear;
	// A failed statement ends the request before the catch below (see followingOpeningBalanceFailureGuard()). The year
	// card has armed it already for its whole save; then it stays the card's.
	$guarded = followingOpeningBalanceFailureGuard($fromYear, $checkedFrom);
	try {
		carryFollowingOpeningBalances($year, $fromYear, $stop, $deferredYear);
	} catch (Throwable $e) {
		db_modify('rollback to savepoint following_opening_balances', __FILE__ . ' line ' . __LINE__);
		error_log('SST-1005: opening balances after saving fiscal year ' . $year . ' not updated: ' . $e->getMessage());
		$stop = followingOpeningBalanceStopShape($fromYear, $fromYear + 1);
		$stop['failed'] = true;
		$deferredYear = 0;
	}
	if ($stop && (int)$stop['sourceYear'] === $year - 1 && is_array($primoBeforeSave)) {
		// The card has just reset and rewritten this year's opening balances; without a complete transfer they go back.
		foreach ($primoBeforeSave as $id => $primo) {
			$primo = (float)$primo;
			db_modify("update kontoplan set primo = '$primo' where id = '" . (int)$id . "' and regnskabsaar = '$year'", __FILE__ . ' line ' . __LINE__);
		}
	}
	db_modify('release savepoint following_opening_balances', __FILE__ . ' line ' . __LINE__);
	// A transfer left for the next check did not reach a blocked transition stored at or after it, so that stays (CodeRabbit).
	saveFollowingOpeningBalanceStop($stop, $checkedFrom, $deferredYear);
	$committed = $ownTransaction ? followingOpeningBalanceCommit() : true;
	if ($guarded && $committed) followingOpeningBalanceFailureGuard(null);
	return $stop;
}

/**
 * The opening balances of a fiscal year as they are now, for updateOpeningBalancesAfterYearSave(). The year card
 * reads them with $lock right after its transaction begins and it has written the changed choices of the year before
 * (followingOpeningBalanceSaveLastYearChoices()): the rows stay locked until it commits, so no rebuild can write the
 * year between this read and a restore. The card goes on to write this year and then the later years, the same
 * ascending order every rebuild locks years in, and it takes the whole year in one statement like a rebuild does
 * (followingOpeningBalanceTryLockYear()).
 *
 * @param int $year Fiscal year (kontoplan.regnskabsaar).
 * @param bool $lock Lock the rows (select ... for update); only inside a transaction.
 * @return array kontoplan.id => primo.
 */
function followingOpeningBalancePrimo($year, $lock = false) {
	$year = (int)$year;
	$primo = array();
	// A journal posting in the year holds its accounts one at a time, so the card first tries to get the whole year
	// without waiting (Codex round 10). When the year stays busy, it waits like the card's own «primo = 0» statement
	// always has.
	if ($lock && followingOpeningBalanceTryLockYear($year)) {
		$lock = false;
	}
	$q = db_select("select id, primo from kontoplan where regnskabsaar = '$year'" . ($lock ? ' for update' : ''), __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$primo[(int)$row['id']] = $row['primo'];
	}
	return $primo;
}

/**
 * A script tag that shows the message for a blocked rebuild in an alert. The text holds saved fiscal year
 * descriptions, so it is JSON-encoded with HTML-safe flags and cannot end the script element.
 * The text is in the database encoding (findtekst(), grupper.beskrivelse); on a LATIN9 regnskab it is converted to
 * UTF-8 first, as jsString() (includes/stdFunc/jsString.php) does, or json_encode() would replace every æ, ø and å
 * (Lui's pre-check). Every letter outside ASCII is written as \uXXXX, so the script reads the same on a page in
 * either charset.
 *
 * @param array $stop See updateFollowingOpeningBalances().
 * @param int $languageId sprog_id for findtekst().
 * @return string HTML, ASCII only.
 */
function followingOpeningBalanceAlertScript($stop, $languageId) {
	global $db_encode;
	$text = followingOpeningBalanceStopText($stop, $languageId);
	if (!empty($db_encode) && $db_encode !== 'UTF8') {
		$text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-15');
	}
	// JSON_HEX_TAG turns < and > into \u003C/\u003E, so the text can never contain </script>; slashes may stay plain.
	$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
	return "<script type='text/javascript'>alert(" . json_encode($text, $flags) . ");</script>";
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
 * Read the settings value 'openingBalanceSync'. It is one JSON row, so the watermark and the fingerprint it
 * belongs to are always written together. The plain "lastId|pendingYear|time" of earlier test builds is read too,
 * without a fingerprint.
 *
 * @param string $value The stored value.
 * @return array{
 *   lastId: int,             Highest transaktioner.id already looked at.
 *   pendingYear: int,        Only from a state of an earlier test build: the waiting year it kept, or 0. The waiting
 *                            year now lives in 'openingBalancePending' (followingOpeningBalanceMarkPending()).
 *   fingerprintAt: int,      Unix time of the last fingerprint, or 0.
 *   fingerprint: array|null, followingOpeningBalanceFingerprint() at that time, or null when there is none yet.
 * }
 */
function followingOpeningBalanceStateParts($value) {
	$state = json_decode((string)$value, true);
	if (is_array($state)) {
		// Plain isset(): this runs from includes/online.php, possibly before std_func.php is loaded.
		return array(
			'lastId' => isset($state['lastId']) ? (int)$state['lastId'] : 0,
			'pendingYear' => isset($state['pendingYear']) ? (int)$state['pendingYear'] : 0,
			'fingerprintAt' => isset($state['fingerprintAt']) ? (int)$state['fingerprintAt'] : 0,
			'fingerprint' => (isset($state['fingerprint']) && is_array($state['fingerprint'])) ? $state['fingerprint'] : null,
		);
	}
	$parts = explode('|', (string)$value);
	return array(
		'lastId' => (int)$parts[0],
		'pendingYear' => isset($parts[1]) ? (int)$parts[1] : 0,
		'fingerprintAt' => 0,
		'fingerprint' => null,
	);
}

/**
 * Store the follow-up state (see followingOpeningBalanceStateParts()) as one JSON row.
 *
 * @param array $state lastId, fingerprintAt and fingerprint; the waiting year has a row of its own.
 * @return void
 */
function saveFollowingOpeningBalanceState($state) {
	$value = json_encode(array(
		'lastId' => (int)$state['lastId'],
		'fingerprintAt' => (int)$state['fingerprintAt'],
		'fingerprint' => $state['fingerprint'],
	));
	$description = 'SST-1005: posting id watermark and the postings fingerprint of the open years';
	saveFollowingOpeningBalanceSetting('openingBalanceSync', $value, $description);
}

/**
 * Store or clear the transition that blocked a rebuild. A blocked transition before $fromYear was not
 * part of this rebuild and stays; one at or after it is replaced by this rebuild's outcome, except that a rebuild
 * that left a transfer for the next check did not reach a stored transition at or after that one, so it stays too.
 * The stored transition is read with its row locked and replaced in the same transaction, so a block another rebuild
 * commits for an earlier year between the read and the write is waited for and kept, never overwritten (CodeRabbit).
 * Outside a transaction (after a rollback, on shutdown) the two run in a transaction of their own. The row is locked
 * and written by its id: MySQL has no index on settings.var_name, and a locking statement by name would lock every row
 * of settings it scans until the commit (Codex round 28).
 *
 * @param array|null $stop The rebuild's outcome (see updateFollowingOpeningBalances()).
 * @param int $fromYear The fiscal year the rebuild started from.
 * @param int $deferredYear The source year whose transfer was left for a later check, or 0.
 * @return void
 */
function saveFollowingOpeningBalanceStop($stop, $fromYear, $deferredYear = 0) {
	$description = 'SST-1005: fiscal year transition whose opening balances could not be updated automatically';
	$ownTransaction = !followingOpeningBalancesInTransaction();
	if ($ownTransaction) {
		if ($stop) {
			// Created in autocommit before the transaction, see followingOpeningBalancePrepareRows() (Codex round 31).
			followingOpeningBalanceEnsureSetting('openingBalanceStop', '', $description);
		}
		followingOpeningBalanceRememberRows();
		transaktion('begin');
	}
	// The row is locked by the id found before the transaction, with nothing of it read first in the transaction: under
	// SERIALIZABLE a plain read takes a shared lock, and two savers that both upgrade it deadlock (Codex round 32).
	$row = followingOpeningBalanceLockRow('openingBalanceStop');
	if (!$row) {
		// Not found before (a caller's transaction that did not prepare the rows): as before.
		if ($stop) {
			// A block to store: the row exists before it is locked, as there is no row to lock while it does not. An empty
			// value is no block. Clearing never creates it: with no row there is nothing to clear, and a row another
			// request creates meanwhile is its block, which stays (Codex round 28).
			followingOpeningBalanceEnsureSetting('openingBalanceStop', '', $description);
		}
		$foundId = followingOpeningBalanceStopRowId($stop !== null);
		if ($foundId) {
			$GLOBALS['followingOpeningBalanceRowIds']['openingBalanceStop'] = $foundId;
			$row = followingOpeningBalanceLockRow('openingBalanceStop');
		}
	}
	$id = $row ? $row['id'] : 0;
	$stored = $row ? followingOpeningBalanceParseStop($row['var_value']) : null;
	$keep = !$stop && !$stored;
	if ($stored && (int)$stored['sourceYear'] < (int)$fromYear) {
		$keep = true;
	}
	if (!$stop && $stored && (int)$deferredYear > 0 && (int)$stored['sourceYear'] >= (int)$deferredYear) {
		$keep = true;
	}
	if (!$keep && $id) {
		$value = db_escape_string($stop ? json_encode($stop) : '');
		db_modify("update settings set var_value = '$value' where id = '$id'", __FILE__ . ' line ' . __LINE__);
	}
	if ($ownTransaction) {
		transaktion('commit');
		followingOpeningBalanceReleaseLocks();
	}
}

/**
 * The id of the 'openingBalanceStop' row. A row that already existed is found by a plain read. A row another request
 * created after a MySQL transaction's repeatable-read snapshot is not; when it must be found ($current, a block is to
 * be stored and the row was just made sure of), a locking read by name finds it. That read locks the settings rows it
 * scans until the commit on MySQL, so it is only used then, which is once per tenant at most.
 *
 * @param bool $current Find a row the snapshot does not show as well.
 * @return int The row's id, or 0 when there is none to be seen.
 */
function followingOpeningBalanceStopRowId($current) {
	$r = db_fetch_array(db_select("select id from settings where var_name = 'openingBalanceStop' order by id limit 1", __FILE__ . ' line ' . __LINE__));
	if (!$r && $current && followingOpeningBalancesInTransaction()) {
		$r = db_fetch_array(db_select("select id from settings where var_name = 'openingBalanceStop' order by id limit 1 for update", __FILE__ . ' line ' . __LINE__));
	}
	return $r ? (int)$r['id'] : 0;
}

/**
 * The stored transition that blocked the last rebuild.
 *
 * @return array|null See updateFollowingOpeningBalances() for the shape, or null when nothing is blocked.
 */
function followingOpeningBalanceStop() {
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'openingBalanceStop' order by id limit 1", __FILE__ . ' line ' . __LINE__));
	return $r ? followingOpeningBalanceParseStop($r['var_value']) : null;
}

/**
 * A stored transition from its settings value.
 *
 * @param string|null $value var_value of 'openingBalanceStop'.
 * @return array|null See updateFollowingOpeningBalances() for the shape, or null when it holds no block.
 */
function followingOpeningBalanceParseStop($value) {
	if (!$value) {
		return null;
	}
	$stop = json_decode($value, true);
	return (is_array($stop) && isset($stop['sourceYear'], $stop['targetYear'])) ? $stop : null;
}

/**
 * Remember the ids of this feature's 'openingBalancePending' and 'openingBalanceStop' rows, read outside a transaction
 * (autocommit, so the read keeps no lock). Rows are never deleted, so an id stays valid; a transaction then locks a row
 * by its id first (followingOpeningBalanceLockRow()).
 *
 * @param array|null $ids var_name => id already read (by the page check's first query), or null to read them here.
 * @return void
 */
function followingOpeningBalanceRememberRows($ids = null) {
	if ($ids === null) {
		if (followingOpeningBalancesInTransaction()) {
			return;
		}
		$ids = array();
		$q = db_select("select id, var_name from settings where var_name in ('openingBalancePending', 'openingBalanceStop') order by id", __FILE__ . ' line ' . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!isset($ids[$r['var_name']])) $ids[$r['var_name']] = (int)$r['id'];
		}
	}
	foreach ($ids as $name => $id) {
		if ((int)$id > 0) $GLOBALS['followingOpeningBalanceRowIds'][$name] = (int)$id;
	}
}

/**
 * Lock this feature's row $name by its remembered id (select ... for update by primary key) and read it, as the first
 * access to it in the transaction. Under SERIALIZABLE a plain read of the row first would take a shared lock, and two
 * transactions that both then lock it for the write deadlock (Codex round 32); by id, and not by name, because MySQL
 * has no index on settings.var_name and would lock every row it scans (Codex round 28).
 * Only that row is locked, so a kassekladde posting that leaves a year waiting does not hold the block row too. This
 * feature's other statements on these rows also go by id (followingOpeningBalanceTakePending(),
 * saveFollowingOpeningBalanceSetting()), so none of them scans settings and waits in a circle with a transaction that
 * holds one of the rows (Codex round 33).
 *
 * @param string $name 'openingBalancePending' or 'openingBalanceStop'.
 * @return array|null array{id: int, var_value: string|null} of the locked row, or null when no id is remembered or the
 *   row is gone (the caller then finds the row as before).
 */
function followingOpeningBalanceLockRow($name) {
	$id = isset($GLOBALS['followingOpeningBalanceRowIds'][$name]) ? (int)$GLOBALS['followingOpeningBalanceRowIds'][$name] : 0;
	if (!$id || !followingOpeningBalancesInTransaction()) {
		return null;
	}
	$escaped = db_escape_string($name);
	$r = db_fetch_array(db_select("select id, var_value from settings where id = '$id' and var_name = '$escaped' for update", __FILE__ . ' line ' . __LINE__));
	if (!$r) {
		unset($GLOBALS['followingOpeningBalanceRowIds'][$name]);
		return null;
	}
	return array('id' => (int)$r['id'], 'var_value' => $r['var_value']);
}

/**
 * Write a tenant setting of this feature (var_grp 'system'), creating it first when it does not exist yet.
 *
 * @param string $name var_name.
 * @param string $value var_value.
 * @param string $description var_description, used only when the row is created.
 * @return void
 */
function saveFollowingOpeningBalanceSetting($name, $value, $description) {
	followingOpeningBalanceEnsureSetting($name, $value, $description);
	$name = db_escape_string($name);
	$value = db_escape_string($value);
	// By id, read first: MySQL has no index on settings.var_name, and an UPDATE by name locks every settings row it
	// scans, also a waiting-year or block row a rebuild holds while it waits for another (Codex round 33).
	$r = db_fetch_array(db_select("select id from settings where var_name = '$name' order by id limit 1", __FILE__ . ' line ' . __LINE__));
	$where = $r ? "id = '" . (int)$r['id'] . "'" : "var_name = '$name'";
	db_modify("update settings set var_value = '$value' where $where", __FILE__ . ' line ' . __LINE__);
}

/**
 * Create a tenant setting of this feature with $value when it does not exist, so there is only ever one row of it.
 * A row that is seen is used as it is. Otherwise the creation is serialised by a lock named after the setting, the
 * same lock followingOpeningBalanceCreatePending() and followingOpeningBalanceInitLock() take for theirs, and the
 * INSERT ... SELECT ... WHERE NOT EXISTS reads the table again under it (Codex round 28: MySQL has no unique index on
 * settings, so a second row was possible).
 * - PostgreSQL: inside a transaction pg_advisory_xact_lock, held until this row is committed, because a read there
 *   does not wait for a row that is not committed; outside one a session lock released right after. ON CONFLICT DO
 *   NOTHING is kept for settings_key_unique (includes/settings/uniqueIndex.php): a second INSERT that failed there
 *   ended its request and rolled back a card save with it (Lui's pre-check).
 * - MySQL: GET_LOCK, inside a transaction kept until it ends (followingOpeningBalanceGetLock()), so the next creator
 *   reads only once this row is committed. Under REPEATABLE READ the read of INSERT ... SELECT is a current, locking
 *   read, so an older snapshot does not hide the row; under READ COMMITTED a plain read sees it. On a table without
 *   an index on var_name that read locks the settings rows it scans until the commit; that happens only when the row
 *   is missing, once per tenant.
 *
 * @param string $name var_name.
 * @param string $value var_value of a new row.
 * @param string $description var_description of a new row.
 * @param bool $lockHeld True when the caller holds the setting's lock already (followingOpeningBalanceCreatePending()).
 * @return void
 * @throws RuntimeException When MySQL does not give the lock (see followingOpeningBalanceGetLock(); only while another
 *   request creates the same row); nothing is written then.
 */
function followingOpeningBalanceEnsureSetting($name, $value, $description, $lockHeld = false) {
	global $db_type;
	$name = db_escape_string($name);
	if (db_fetch_array(db_select("select id from settings where var_name = '$name' order by id limit 1", __FILE__ . ' line ' . __LINE__))) {
		return;
	}
	$mysql = ($db_type === 'mysql' || $db_type === 'mysqli');
	$transaction = followingOpeningBalancesInTransaction();
	if (!$lockHeld) {
		if ($mysql) {
			if (!followingOpeningBalanceGetLock($name)) {
				throw new RuntimeException("SST-1005: the setting $name could not be created: another request is creating it");
			}
		} elseif ($transaction) {
			db_select("SELECT pg_advisory_xact_lock(hashtext('$name'), 1005)", __FILE__ . ' line ' . __LINE__);
		} else {
			db_select("SELECT pg_advisory_lock(hashtext('$name'), 1005)", __FILE__ . ' line ' . __LINE__);
		}
	}
	try {
		$qtxt = "insert into settings (var_name, var_grp, var_value, var_description, user_id) ";
		$qtxt.= "select '$name', 'system', '" . db_escape_string($value) . "', '" . db_escape_string($description) . "', '0' ";
		$qtxt.= ($mysql ? "from dual " : "") . "where not exists (select id from settings where var_name = '$name')";
		if (!$mysql) {
			$qtxt.= " on conflict do nothing";
		}
		db_modify($qtxt, __FILE__ . ' line ' . __LINE__);
	} finally {
		if (!$lockHeld && $mysql && !$transaction) {
			followingOpeningBalanceReleaseLock($name);
		} elseif (!$lockHeld && !$transaction) {
			db_select("SELECT pg_advisory_unlock(hashtext('$name'), 1005)", __FILE__ . ' line ' . __LINE__);
		}
	}
}

/**
 * Create the rows 'openingBalancePending' ('0') and 'openingBalanceStop' (empty) when they are missing, before a
 * rebuild or a year card begins its transaction. Created in autocommit, the creation holds no row lock while it waits
 * for the creation lock or for another creator, so it cannot wait in a circle with a transaction that holds settings
 * rows and waits for that lock (Codex round 31: a first block saved inside a transaction under SERIALIZABLE, or after
 * the waiting year was written, timed out). Inside a transaction this does nothing; a row still missing there is
 * created by followingOpeningBalanceEnsureSetting() as before, which only happens when no page check has run for the
 * tenant yet.
 *
 * @return void
 */
function followingOpeningBalancePrepareRows() {
	if (followingOpeningBalancesInTransaction()) {
		return;
	}
	followingOpeningBalanceEnsureSetting('openingBalancePending', '0', 'SST-1005: earliest fiscal year waiting for its later years to get new opening balances (0 = none)');
	followingOpeningBalanceEnsureSetting('openingBalanceStop', '', 'SST-1005: fiscal year transition whose opening balances could not be updated automatically');
	followingOpeningBalanceRememberRows();
}

/**
 * Take the MySQL lock under which the setting $name of this feature is created (GET_LOCK names are server-wide, so the
 * database is part of the name; MD5 only shortens it, it secures nothing). Inside a transaction the lock is kept until
 * that transaction ends: followingOpeningBalanceReleaseLocks() releases it after the COMMIT or ROLLBACK of the callers in
 * this file, and otherwise (a kassekladde posting commits in finans/bogfor.php) the connection releases it when the
 * request ends. A lock released earlier let the next creator read before this row was committed: under READ COMMITTED
 * its INSERT ... SELECT does not wait for a row that is not committed, and it added a second one (Codex round 29).
 * A lock this transaction holds already is not taken again.
 * Only a request outside a transaction waits for the lock (up to 10 seconds); it holds no row lock while it waits. A
 * transaction may hold settings rows another creator's INSERT waits for, so it only tries (timeout 0) and gives up at
 * once when the lock is taken; waiting there was a circle InnoDB cannot see and ended in the 10-second time-out (Codex
 * round 31). followingOpeningBalancePrepareRows() makes that case rare: the rows exist before the transactions of this
 * feature begin.
 *
 * @param string $name var_name, already escaped.
 * @return bool True when the lock is held; false when MySQL did not give it (0, or NULL on an error).
 */
function followingOpeningBalanceGetLock($name) {
	if (!isset($GLOBALS['followingOpeningBalanceLocks']) || !is_array($GLOBALS['followingOpeningBalanceLocks'])) {
		$GLOBALS['followingOpeningBalanceLocks'] = array();
	}
	if (!empty($GLOBALS['followingOpeningBalanceLocks'][$name])) {
		return true;
	}
	$transaction = followingOpeningBalancesInTransaction();
	$timeout = $transaction ? 0 : 10;
	$r = db_fetch_array(db_select("SELECT GET_LOCK(CONCAT('saldi:$name:', MD5(DATABASE())), $timeout) AS locked", __FILE__ . ' line ' . __LINE__));
	if (!$r || (string)$r['locked'] !== '1') {
		return false;
	}
	if ($transaction) {
		$GLOBALS['followingOpeningBalanceLocks'][$name] = true;
	}
	return true;
}

/**
 * Release one MySQL creation lock now (outside a transaction, see followingOpeningBalanceGetLock()).
 *
 * @param string $name var_name, already escaped.
 * @return void
 */
function followingOpeningBalanceReleaseLock($name) {
	unset($GLOBALS['followingOpeningBalanceLocks'][$name]);
	db_select("SELECT RELEASE_LOCK(CONCAT('saldi:$name:', MD5(DATABASE()))) AS locked", __FILE__ . ' line ' . __LINE__);
}

/**
 * Release the MySQL creation locks kept for the transaction that has just been committed or rolled back. Nothing to
 * do on PostgreSQL, whose transaction-level advisory locks end with the transaction.
 *
 * @return void
 */
function followingOpeningBalanceReleaseLocks() {
	$locks = isset($GLOBALS['followingOpeningBalanceLocks']) && is_array($GLOBALS['followingOpeningBalanceLocks']) ? array_keys($GLOBALS['followingOpeningBalanceLocks']) : array();
	foreach ($locks as $name) {
		followingOpeningBalanceReleaseLock($name);
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
		if (followingOpeningBalanceYearDeleted($row['box10']) || !isset($years[$year + 1]) || followingOpeningBalanceYearDeleted($years[$year + 1]['box10'])) {
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
 * A fingerprint of the postings in every open fiscal year that has a later year, per account: the number of postings,
 * their debit and credit sums and the sum of each posting's movement rounded to øre, the very amount a transfer adds
 * up (followingOpeningBalanceInputs()). Compared every ten minutes, it finds what the id watermark cannot see: postings
 * that commit after a check with lower ids than it saw, and deleted postings. Because it is per account, a late posting
 * and a deleted one that cancel out in the year's totals but move an amount to another account are found too (Codex
 * round 16); a change that leaves every account's movement as it was does not change any opening balance. Postings can
 * only reach open years (box5 'on'), so closed years are left out. A fingerprint of an earlier test build (the year's
 * totals) differs from this one, so the first comparison after the update rebuilds once.
 *
 * @return array Fiscal year (kodenr) => md5 of "kontonr:count:debet:kredit:movement" per account in account order.
 */
function followingOpeningBalanceFingerprint() {
	$years = array();
	$q = db_select("select kodenr, box1, box2, box3, box4, box5, box10 from grupper where art = 'RA' order by kodenr", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$years[(int)$row['kodenr']] = $row;
	}
	$fingerprint = array();
	foreach ($years as $year => $row) {
		if (followingOpeningBalanceYearDeleted($row['box10']) || $row['box5'] !== 'on' || !isset($years[$year + 1]) || followingOpeningBalanceYearDeleted($years[$year + 1]['box10'])) {
			continue;
		}
		$range = followingOpeningBalanceYearRange($row);
		if (!$range) {
			continue;
		}
		$qtxt = "select kontonr, count(*) as postings, coalesce(sum(debet), 0) as debet, coalesce(sum(kredit), 0) as kredit, ";
		$qtxt.= "coalesce(sum(round(coalesce(debet, 0) - coalesce(kredit, 0), 2)), 0) as movement ";
		$qtxt.= "from transaktioner where transdate >= '$range[0]' and transdate <= '$range[1]' group by kontonr order by kontonr";
		$q = db_select($qtxt, __FILE__ . ' line ' . __LINE__);
		$accounts = array();
		while ($r = db_fetch_array($q)) {
			$accounts[] = $r['kontonr'] . ':' . $r['postings'] . ':' . $r['debet'] . ':' . $r['kredit'] . ':' . $r['movement'];
		}
		$fingerprint[$year] = md5(implode('|', $accounts));
	}
	return $fingerprint;
}

/**
 * The earliest fiscal year whose fingerprint changed. A year that was not in the old fingerprint (newly opened, or
 * newly given a later year) has nothing to compare with yet.
 *
 * @param array $old The stored fingerprint.
 * @param array $new The fingerprint now.
 * @return int Fiscal year (kodenr), or 0.
 */
function followingOpeningBalanceFingerprintChange($old, $new) {
	$changed = 0;
	foreach ($new as $year => $fingerprint) {
		if (isset($old[$year]) && (string)$old[$year] !== (string)$fingerprint && (!$changed || (int)$year < $changed)) {
			$changed = (int)$year;
		}
	}
	return $changed;
}

/**
 * The message for a blocked rebuild, naming the years and accounts and how to fix it.
 *
 * @param array $stop See updateFollowingOpeningBalances().
 * @param int $languageId sprog_id for findtekst().
 * @return string Plain text in the database encoding, not HTML-escaped.
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
		// Plain dots: the text is in the database encoding, and LATIN9 has no ellipsis character (Lui's pre-check).
		$accountList = implode(', ', array_slice($accounts, 0, 10)) . (count($accounts) > 10 ? ' ...' : '');
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
