<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/updateFollowingOpeningBalances.php --- ver 5.1.1 --- 2026.11.13 ---
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
		$inputs = followingOpeningBalanceInputs($sourceYear, $targetYear, $start, $end);
		for ($attempt = 1; ; $attempt++) {
			$blocked = followingOpeningBalanceStopShape($sourceYear, $targetYear);
			$opening = followingOpeningBalanceTransfer($inputs, $blocked);
			if ($opening === null) {
				// A balance has no account to land on in the target year, so leave it and later years untouched.
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
			// A posting or another rebuild may have changed the source year since its inputs were read, even back to
			// how it was. If the inputs read now are the very ones this result was calculated from, it is current:
			// the target rows are locked, so whoever changes the source year from now on carries it forward after
			// this commits (or the next check does). Otherwise calculate again from what was just read.
			$current = followingOpeningBalanceInputs($sourceYear, $targetYear, $start, $end);
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
		// Avoid switching database connections while the journal transaction is active.
		genberegn($targetYear, false);
		$updated[] = $targetYear;
	}
	if ($pendingYear) {
		// The settings row comes after every kontoplan row, the order the callers take it in too.
		followingOpeningBalanceMarkPending($pendingYear);
	}
	return $updated;
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
 * Leave $year waiting in the follow-up state, so the next check in a later year rebuilds the years after it.
 * Nothing happens before the first check has started following. Called after the rebuild's last kontoplan write.
 *
 * @param int $year Fiscal year (kodenr).
 * @return void
 */
function followingOpeningBalanceMarkPending($year) {
	$year = (int)$year;
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'openingBalanceSync' order by id limit 1", __FILE__ . ' line ' . __LINE__));
	if (!$r || $r['var_value'] === null || $r['var_value'] === '') {
		return;
	}
	$state = followingOpeningBalanceStateParts($r['var_value']);
	if (!$state['pendingYear'] || $year < $state['pendingYear']) {
		$state['pendingYear'] = $year;
		saveFollowingOpeningBalanceState($state);
	}
}

/**
 * Everything a transfer from $sourceYear to $targetYear is calculated from: the status accounts of the target year,
 * the source year's movement per account (each posting rounded to øre, as in fiscalYearInc/yearX.php) and every
 * status, result and Sideskift account of the source year with its type, opening balance and «Overfør til».
 * updateFollowingOpeningBalances() calculates a transfer from one read and compares it with a read made after the
 * transfer is written. Because it compares the very inputs the result came from, a change that is undone while the
 * transfer is calculated is noticed too, and so is an amount moved from one account to another without changing
 * any total. Two reads of the same data are identical (===).
 * On PostgreSQL (read committed) the second read sees what other transactions committed meanwhile; a MySQL
 * transaction in its default repeatable read keeps seeing its own snapshot, so there the check finds nothing new.
 *
 * @param int $sourceYear Fiscal year (kontoplan.regnskabsaar) carried from.
 * @param int $targetYear Fiscal year carried into.
 * @param string $start First date of the source year ('Y-m-d').
 * @param string $end Last date of the source year ('Y-m-d').
 * @return array{
 *   targetAccounts: int[],                 Status accounts of the target year, in account order.
 *   movement: array<int, float>,           Source year movement per account in account order, in øre.
 *   sourceAccounts: array<int, array{kontonr: int, kontotype: string, primo: string, overfor_til: string|null}>,
 * }
 */
function followingOpeningBalanceInputs($sourceYear, $targetYear, $start, $end) {
	$sourceYear = (int)$sourceYear;
	$targetYear = (int)$targetYear;
	$inputs = array('targetAccounts' => array(), 'movement' => array(), 'sourceAccounts' => array());
	$q = db_select("select kontonr from kontoplan where regnskabsaar = '$targetYear' and kontotype = 'S' order by kontonr", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$inputs['targetAccounts'][] = (int)$row['kontonr'];
	}
	$q = db_select("select kontonr, debet, kredit from transaktioner where transdate >= '$start' and transdate <= '$end'", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$account = (int)$row['kontonr'];
		if (!isset($inputs['movement'][$account])) $inputs['movement'][$account] = 0;
		$inputs['movement'][$account] += afrund($row['debet'] - $row['kredit'], 2);
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
 * is undone at once and tried again shortly. While the rebuild holds the whole year, genberegn() and the writes need
 * no further row of it, so the rebuild itself never waits for a kontoplan row.
 * Outside a transaction a lock would end with the statement, so nothing is locked and true is returned, as before.
 * MySQL keeps the plain lock, because SKIP LOCKED needs MySQL 8 / MariaDB 10.6.
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
	if ($db_type === 'mysql' || $db_type === 'mysqli') {
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
	if (($r && $r['box10'] === 'on') || !db_fetch_array(db_select("select id from kontoplan where regnskabsaar = '$year' limit 1", __FILE__ . ' line ' . __LINE__))) {
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
	$qtxt.= "(select var_value from settings where var_name = 'openingBalanceSync' order by id limit 1) as state";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . ' line ' . __LINE__));
	if (!$r) {
		return null;
	}
	$state = ($r['state'] === null || $r['state'] === '') ? null : followingOpeningBalanceStateParts($r['state']);
	if ($state === null || $state['fingerprint'] === null) {
		$initial = followingOpeningBalanceInitialize();
		if ($initial === null) {
			return null;
		}
		list($state, $maxId, $changed) = $initial;
	} else {
		$maxId = (int)$r['max_id'];
		$changed = false;
		if (time() - $state['fingerprintAt'] >= 600) {
			// Postings that commit after a check with lower ids than it saw, and deleted postings, show up here.
			$fingerprint = followingOpeningBalanceFingerprint();
			$year = followingOpeningBalanceFingerprintChange($state['fingerprint'], $fingerprint);
			if ($year && (!$state['pendingYear'] || $year < $state['pendingYear'])) $state['pendingYear'] = $year;
			$state['fingerprint'] = $fingerprint;
			$state['fingerprintAt'] = time();
			$changed = true;
		}
	}
	if ($maxId > $state['lastId']) {
		$year = followingOpeningBalancePostedYear($state['lastId'], $maxId);
		if ($year && (!$state['pendingYear'] || $year < $state['pendingYear'])) $state['pendingYear'] = $year;
		$state['lastId'] = $maxId;
		$changed = true;
	}
	if ($changed) {
		saveFollowingOpeningBalanceState($state);
	}
	if (!$state['pendingYear'] || $activeYear <= $state['pendingYear']) {
		return null;
	}
	return syncFollowingOpeningBalances($state['pendingYear']);
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
 *   followingOpeningBalanceStateParts()), max(id) read now and whether the state still has to be saved; or null when
 *   this check stored the very first state, or another check is starting right now (the next page continues).
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
		if ($state === null) {
			saveFollowingOpeningBalanceState(array('lastId' => $maxId, 'pendingYear' => 0, 'fingerprintAt' => time(), 'fingerprint' => $fingerprint));
			return null;
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
	include_once(__DIR__ . '/genberegn.php');
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'openingBalanceSync' order by id limit 1", __FILE__ . ' line ' . __LINE__));
	if ($r && $r['var_value'] !== '' && $r['var_value'] !== null) {
		$state = followingOpeningBalanceStateParts($r['var_value']);
		if ($state['pendingYear']) {
			$fromYear = $fromYear > 0 ? min($fromYear, $state['pendingYear']) : $state['pendingYear'];
			// Cleared before the rebuild, so a failing rebuild is not retried on every page.
			$state['pendingYear'] = 0;
			saveFollowingOpeningBalanceState($state);
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
	$updated = updateFollowingOpeningBalances($fromYear, $stop, $deferredYear);
	// A transfer left for the next check is not a missing year before $year, so $year is not carried from itself then.
	if (!$stop && !$deferredYear && !in_array($year + 1, $updated, true)) {
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
 * Read the settings value 'openingBalanceSync'. It is one JSON row, so the watermark and the fingerprint it
 * belongs to are always written together. The plain "lastId|pendingYear|time" of earlier test builds is read too,
 * without a fingerprint.
 *
 * @param string $value The stored value.
 * @return array{
 *   lastId: int,             Highest transaktioner.id already looked at.
 *   pendingYear: int,        Earliest fiscal year with postings not yet carried forward, or 0.
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
 * @param array $state lastId, pendingYear, fingerprintAt and fingerprint.
 * @return void
 */
function saveFollowingOpeningBalanceState($state) {
	$value = json_encode(array(
		'lastId' => (int)$state['lastId'],
		'pendingYear' => (int)$state['pendingYear'],
		'fingerprintAt' => (int)$state['fingerprintAt'],
		'fingerprint' => $state['fingerprint'],
	));
	$description = 'SST-1005: posting id watermark, earliest fiscal year waiting for new opening balances, and the postings fingerprint of the open years';
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
 * A fingerprint of the postings in every open fiscal year that has a later year: the number of postings and their
 * debit and credit sums. Compared every ten minutes, it finds what the id watermark cannot see: postings that
 * commit after a check with lower ids than it saw, and deleted postings. Postings can only reach open years
 * (box5 'on'), so closed years are left out.
 *
 * @return array Fiscal year (kodenr) => "count:debet:kredit".
 */
function followingOpeningBalanceFingerprint() {
	$years = array();
	$q = db_select("select kodenr, box1, box2, box3, box4, box5, box10 from grupper where art = 'RA' order by kodenr", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$years[(int)$row['kodenr']] = $row;
	}
	$fingerprint = array();
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
		$fingerprint[$year] = $r['postings'] . ':' . $r['debet'] . ':' . $r['kredit'];
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
