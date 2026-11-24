<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/updateFollowingOpeningBalances.php --- ver 5.1.1 --- 2026.11.20 ---
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
// 20261119 CL/ASR  A source year without a Sideskift (X) account no longer stops propagation: its result goes onto the first status account after the target year's Resultat (R) account (6940 on the standard chart).
//                  The result is placed so the opening balances when a year is created, and moved only by the change otherwise (owner's decision 19 Nov 2026).
//                  The caller can read why a walk stopped.
// 20261119 CL/ASR  A carry with a following year to write first takes an advisory lock that only the carry takes (openingCarryLock()), so two sessions' carries no longer overwrite each other's opening balances (p1-pos-close-R1).
//                  The POS day close, the year card and Indstillinger take it before their transaction (openingCarryLockBeforeTransaction()), so their snapshot never misses another session's carry.
//                  A carry that had to wait for the lock while it reads from a snapshot is refused with OpeningCarryConflict instead of writing stale balances.
//                  A caller that does not catch OpeningCarryConflict (finans/bogfor.php, the year card, Opret) gets its transaction rolled back and a Danish message to try again, not an uncaught exception.
//                  For the POS day close the carry first locks the rows it and genberegn() write in each following year (openingCarryLockYearRows()), so a row another session changed after the close's snapshot, or a deadlock, is OpeningCarryConflict instead of the database error box and its mail.
//                  A primo that already holds the carried value is not written again.
// 20261119 CL/ASR  On MySQL and MariaDB the lock is asked for with GET_LOCK(name, 30), again while another session holds it, for at most 2 minutes (saldiNamedLockWait()), because MariaDB answers GET_LOCK(name, -1) with NULL and no lock, so every Gem on a year card, every Opret after the first year and every POS close with an earlier-year sale failed there (p1-yearend-F3).
//                  A wait that runs out is OpeningCarryLockTimeout, and the callers' alerts say how long they waited.
//                  finans/bogfor.php takes the lock before its transaction as well when its year has a following year (openingCarryHasFollowingYear()), so a journal's snapshot no longer predates another session's carry on MySQL and MariaDB, and a journal no longer deadlocks with a close or a card holding the lock (p1-yearend-F4).
// 20261120 CL/ASR  The lock is released on the connection that took it, which is kept until then, so on MySQL and MariaDB it is still held after genberegn() has connected to the master database and back, and finans/bogfor.php and the year card hold it through their recalculations of the year after the commit (p1-pos-close-N1).

/** Carry mode of updateFollowingOpeningBalances() for every caller but the creation of a year: only the change moves. */
if (!defined('OPENING_CARRY_DELTA')) define('OPENING_CARRY_DELTA', 'delta');
/** Carry mode of updateFollowingOpeningBalances() when a year is created: the result destination balances the opening. */
if (!defined('OPENING_CARRY_PLACE')) define('OPENING_CARRY_PLACE', 'place');

include_once(__DIR__ . '/stdFunc/namedLockWait.php');

if (!class_exists('OpeningCarryConflict', false)) {
	/**
	 * The opening-balance carry's lock could not be taken, or this transaction reads from a snapshot that is older than
	 * another session's carry, which it had to wait for. Nothing may be carried then: the caller rolls its transaction
	 * back and the user tries again. Only a caller that asks for it ($throwConflict) gets the exception; every other caller
	 * gets openingCarryConflictExit().
	 */
	class OpeningCarryConflict extends RuntimeException {
	}
}

if (!class_exists('OpeningCarryLockTimeout', false)) {
	/**
	 * OpeningCarryConflict on MySQL and MariaDB when another session held the carry's lock for longer than
	 * saldiNamedLockWait() waits (2 minutes). openingCarryConflictText() says so in the alert.
	 */
	class OpeningCarryLockTimeout extends OpeningCarryConflict {
	}
}

/**
 * The lock that serializes the opening-balance carry between sessions (finding p1-pos-close-R1). The carry reads balances
 * and writes absolute opening balances, so two carries that interleave overwrite each other: a POS day close carried a
 * till's 125 into the active year, and a journal posted into last year at the same time wrote the till's opening back to 0.
 *
 * It is an advisory lock that only the carry takes, per tenant database: PostgreSQL's pg_advisory_xact_lock() or
 * pg_advisory_lock() on (hashtext('saldi:opening_carry'), 0), MySQL's and MariaDB's GET_LOCK('saldi:opening_carry:' plus
 * md5 of the database name). It is not a row, because an invoice runs genberegn() inside its transaction: genberegn()
 * updates the year's kontoplan rows and then the year's grupper RA row, so a carry that held the RA rows while it waited
 * for those kontoplan rows deadlocked with the invoice. A carry that holds this lock and waits for an invoice's rows only
 * waits until the invoice commits.
 *
 * Two ways to take it:
 * - openingCarryLockBeforeTransaction(), before the caller's begin, held until openingCarryUnlock() after its commit or
 *   rollback, after the recalculations of the year that follow the commit where the caller has them (finans/bogfor.php,
 *   the year card), or the end of the request: the POS day close when it posts a sale from an earlier fiscal year, a journal
 *   (finans/bogfor.php) posted into a fiscal year that has a following year (openingCarryHasFollowingYear()), Gem on the
 *   year card and Indstillinger » Regnskabsår » Opret. The transaction then starts after any other carry has committed,
 *   and no other carry commits before it has. That matters for the POS day close, which reads one REPEATABLE READ
 *   snapshot (debitor/pos_ordre_includes/boxCountMethods/cashCountSnapshot.php), and on MySQL and MariaDB, where every
 *   transaction does: a snapshot taken before another session's carry committed would carry stale balances
 *   (p1-yearend-F4: a journal's carry wrote retained earnings -50 over a close's -100, where both made -150).
 * - openingCarryLock(), inside the transaction, which updateFollowingOpeningBalances() takes before it reads anything when
 *   it has a following year to write. It waits for nobody when the session already holds the lock, as every caller above
 *   does; it is what is left for a carry whose caller did not see a following year before its transaction (a year
 *   created in between). On READ COMMITTED (PostgreSQL's default) every statement after a wait reads what the other carry
 *   committed. A transaction that reads from a snapshot (REPEATABLE READ or SERIALIZABLE; on MySQL and MariaDB every
 *   transaction is taken to) keeps reading it after a wait, so a carry that had to wait there is refused with
 *   OpeningCarryConflict.
 *
 * On MySQL and MariaDB the lock is asked for with saldiNamedLockWait(): GET_LOCK(name, 30), asked again while another
 * session holds it, for at most 2 minutes. GET_LOCK(name, -1) waits without end on MySQL, but MariaDB answers it at once
 * with NULL and no lock, so every caller failed there on every try (p1-yearend-F3). A wait that runs out is
 * OpeningCarryLockTimeout; PostgreSQL waits for its advisory lock as long as it takes.
 *
 * A caller that does not catch OpeningCarryConflict itself (the year card and Indstillinger » Regnskabsår » Opret, and
 * finans/bogfor.php inside its carry) does not get it: openingCarryConflictExit() rolls its transaction back, says in
 * Danish that nothing was saved and to try again, and ends the request, as the database error box ends it (db_modify()).
 * The POS day close catches it ($throwConflict) and asks the clerk to approve again; finans/bogfor.php catches a refused
 * lock before its transaction and says that nothing was posted.
 *
 * The lock keeps other carries out, not a posting into the active year (a journal, an invoice's genberegn(), the close of a
 * till with sales of the active year only), which writes the active year's kontoplan rows without a carry. The POS day
 * close reads one REPEATABLE READ snapshot, so when such a posting changed a row that the close's carry or genberegn()
 * then writes, PostgreSQL refuses the close ('could not serialize access due to concurrent update'). For the close the
 * carry locks those rows first (openingCarryLockYearRows()), so that is OpeningCarryConflict and not the database error box.
 *
 * Known limits: a journal checks for a following year before its transaction. A year that Opret creates after that
 * check is carried into by the journal's own carry with openingCarryLock() inside the transaction, as above; on MySQL
 * and MariaDB that carry does not see such a year at all when it was committed after the journal's first read.
 *
 * @throws OpeningCarryConflict When the database refuses the lock (on PostgreSQL also a deadlock), or after a wait under a
 *   snapshot; OpeningCarryLockTimeout when another session held it for longer than saldiNamedLockWait() waits.
 */
function openingCarryLock() {
	global $db_type;
	$state = &openingCarryLockState();
	if ($state['held']) {
		return;
	}
	if ($db_type === 'mysql' || $db_type === 'mysqli') {
		$waited = (string) openingCarryLockQuery('select get_lock(' . openingCarryLockName() . ', 0)') !== '1';
		if ($waited) {
			openingCarryNamedLock();
		}
		// A named lock is the session's: it is held until openingCarryUnlock() or the end of the request.
		openingCarryLockHeld();
		$snapshot = true;
	} else {
		if (openingCarryLockQuery('select pg_try_advisory_xact_lock(' . openingCarryLockKey() . ')') === 't') {
			return;
		}
		openingCarryLockQuery('select pg_advisory_xact_lock(' . openingCarryLockKey() . ')');
		$waited = true;
		$snapshot = in_array(openingCarryLockQuery("select current_setting('transaction_isolation')"), array('repeatable read', 'serializable'), true);
	}
	if ($waited && $snapshot) {
		openingCarryUnlock();
		throw new OpeningCarryConflict("Another session's opening balance carry committed after this transaction's snapshot; the opening balances were not carried.");
	}
}

/**
 * The carry's lock (see openingCarryLock()) at session level, taken before the caller's transaction begins, so the
 * transaction's first read is after any other carry has committed. The caller releases it with openingCarryUnlock()
 * after its commit or rollback, or after the recalculations that follow its commit; the end of the request releases it
 * too. Taking it again in the same request does nothing.
 *
 * @param bool $throwConflict True when the caller catches OpeningCarryConflict itself (the POS day close, a journal).
 *   Otherwise a lock the database refuses ends the request with openingCarryConflictExit() (the year card, Opret).
 * @throws OpeningCarryConflict With $throwConflict, when the database refuses the lock; OpeningCarryLockTimeout when on
 *   MySQL or MariaDB another session held it for longer than saldiNamedLockWait() waits.
 */
function openingCarryLockBeforeTransaction($throwConflict = false) {
	global $db_type;
	$state = &openingCarryLockState();
	if ($state['held']) {
		return;
	}
	try {
		if ($db_type === 'mysql' || $db_type === 'mysqli') {
			openingCarryNamedLock();
		} else {
			openingCarryLockQuery('select pg_advisory_lock(' . openingCarryLockKey() . ')');
		}
	} catch (OpeningCarryConflict $e) {
		if ($throwConflict) {
			throw $e;
		}
		openingCarryConflictExit($e, "Intet er gemt: primo i de følgende regnskabsår kunne ikke låses mod samtidige opdateringer fra andre brugere. Prøv igen.");
	}
	openingCarryLockHeld();
}

/**
 * The carry's lock on MySQL and MariaDB, waited for with saldiNamedLockWait(): GET_LOCK(name, 30), asked again while
 * another session holds it, for at most 2 minutes. It does not mark the lock as held; the caller does.
 *
 * @throws OpeningCarryLockTimeout When another session held the lock through the whole wait.
 * @throws OpeningCarryConflict When the database answered NULL (or refused the statement).
 */
function openingCarryNamedLock() {
	$outcome = saldiNamedLockWait('openingCarryLockQuery', openingCarryLockName());
	if ($outcome === 'timeout') {
		throw new OpeningCarryLockTimeout('The opening balance carry could not take its lock: another session held it through the whole wait.');
	}
	if ($outcome !== 'locked') {
		throw new OpeningCarryConflict('The opening balance carry could not take its lock: GET_LOCK() answered NULL.');
	}
}

/**
 * The alert of a caller whose carry's lock or carry conflicted ($txt), with a sentence more when the lock was not given
 * within the wait (OpeningCarryLockTimeout): how long it waited, and for what.
 *
 * @param OpeningCarryConflict $e The conflict.
 * @param string $txt The caller's alert: what was not saved, and to try again.
 * @return string
 */
function openingCarryConflictText(OpeningCarryConflict $e, $txt) {
	$txt = (string) $txt;
	if ($e instanceof OpeningCarryLockTimeout) {
		$txt .= ' Der blev ventet ' . saldiNamedLockWaitText() . ' på, at en anden bruger blev færdig med at bogføre i et tidligere regnskabsår, '
			. 'godkende en kasseoptælling, gemme en åbningsbalance eller oprette et regnskabsår.';
	}
	return $txt;
}

/**
 * What a caller that does not catch OpeningCarryConflict gets, instead of an uncaught exception (a PHP fatal error and an
 * empty page): its transaction is rolled back, the lock released, the reason logged, and a Danish alert says that nothing
 * was saved and to try again (openingCarryConflictText()). Then the request ends, as the database error box ends it
 * (db_modify()). Nothing of the transaction is written.
 *
 * @param OpeningCarryConflict $e The conflict, for the log.
 * @param string $txt The alert.
 * @return never
 */
function openingCarryConflictExit(OpeningCarryConflict $e, $txt) {
	error_log('Saldi opening balance carry: ' . $e->getMessage());
	if (function_exists('transaktion')) {
		transaktion('rollback');
	}
	openingCarryUnlock();
	print "<script>alert(" . json_encode(openingCarryConflictText($e, $txt), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ");</script>\n";
	exit;
}

/**
 * Whether a carry from $year has a following year to write, so a caller that posts into $year takes the carry's lock
 * before its transaction (openingCarryLockBeforeTransaction()): $year and $year + 1 both have a year row (grupper art
 * RA), as updateFollowingOpeningBalances() decides it. Posting into the newest year takes no lock.
 *
 * @param int|string $year The fiscal year (grupper art RA kodenr) posted into.
 * @return bool
 */
function openingCarryHasFollowingYear($year) {
	$year = (int) $year;
	$found = array();
	$q = db_select("select kodenr from grupper where art = 'RA'", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$found[(int) $row['kodenr']] = true;
	}
	return isset($found[$year], $found[$year + 1]);
}

/**
 * Releases the carry's lock taken by openingCarryLockBeforeTransaction() (and on MySQL and MariaDB by openingCarryLock()),
 * after the caller's commit or rollback, or after the recalculations that follow its commit (genberegn()). It is released
 * on the connection that took it, also when the page has connected again since. A transaction-level lock on PostgreSQL
 * ends with its transaction. Does nothing when the request holds no lock; when the database cannot be reached, the end
 * of the connection releases it.
 */
function openingCarryUnlock() {
	global $db_type;
	$state = &openingCarryLockState();
	if (!$state['held']) {
		return;
	}
	$link = $state['connection'];
	$state['held'] = false;
	$state['connection'] = null;
	try {
		if ($db_type === 'mysql' || $db_type === 'mysqli') {
			openingCarryLockQuery('select release_lock(' . openingCarryLockName() . ')', $link);
		} else {
			openingCarryLockQuery('select pg_advisory_unlock(' . openingCarryLockKey() . ')', $link);
		}
	} catch (OpeningCarryConflict $e) {
		// The end of the connection releases the lock.
	}
}

/**
 * Whether this request holds the carry's session-level (or MySQL named) lock, and the connection that holds it.
 *
 * @return array{held: bool, shutdown: bool, connection: mixed}
 */
function &openingCarryLockState() {
	static $state = array('held' => false, 'shutdown' => false, 'connection' => null);
	return $state;
}

/**
 * Marks the lock as held and releases it at the end of the request, should the caller not get to it. The connection that
 * took it is kept until openingCarryUnlock(): genberegn() with its posting count connects to the master database and back
 * (includes/connect.php, includes/online.php), which on MySQL and MariaDB opens a new connection to the company database,
 * and the old one, with the lock, would otherwise be closed then. A caller that holds the lock through its recalculations
 * (finans/bogfor.php, the year card) keeps it that way until it releases it.
 */
function openingCarryLockHeld() {
	global $connection;
	$state = &openingCarryLockState();
	$state['held'] = true;
	$state['connection'] = $connection;
	if (!$state['shutdown']) {
		$state['shutdown'] = true;
		register_shutdown_function('openingCarryUnlock');
	}
}

/** The PostgreSQL advisory lock's keys, per database. */
function openingCarryLockKey() {
	return "hashtext('saldi:opening_carry'), 0";
}

/** The MySQL and MariaDB lock's name: GET_LOCK() names are server-wide, so the database is part of it. */
function openingCarryLockName() {
	return "concat('saldi:opening_carry:', md5(database()))";
}

/**
 * One statement of the carry's lock on the tenant connection, without db_select()'s error box, mail and exit: the
 * callers show their own message.
 *
 * @param string $qtxt A select of one value.
 * @param mixed $link The connection; null for the page's connection now ($connection).
 * @return string|null The value.
 * @throws OpeningCarryConflict When the database refuses the statement (on PostgreSQL also a deadlock).
 */
function openingCarryLockQuery($qtxt, $link = null) {
	global $connection, $db_type;
	if ($link === null) {
		$link = $connection;
	}
	$error = '';
	$value = null;
	$result = false;
	try {
		if ($db_type === 'mysql' || $db_type === 'mysqli') {
			$result = mysqli_query($link, $qtxt);
			if ($result === false) {
				$error = mysqli_error($link);
			} elseif ($result instanceof mysqli_result) {
				$row = mysqli_fetch_row($result);
				$value = $row ? $row[0] : null;
			}
		} else {
			$result = @pg_query($link, $qtxt);
			if ($result === false) {
				$error = (string) pg_last_error($link);
			} else {
				$row = pg_fetch_row($result);
				$value = $row ? $row[0] : null;
			}
		}
	} catch (Throwable $e) { // mysqli_sql_exception where mysqli_report() makes errors throw, or a closed connection
		$result = false;
		$error = $e->getMessage();
	}
	if ($result === false) {
		throw new OpeningCarryConflict('The opening balance carry could not take its lock: ' . trim($error));
	}
	return $value === null ? null : (string) $value;
}

/**
 * For a caller that catches OpeningCarryConflict ($throwConflict, the POS day close): locks the rows that the carry and
 * genberegn() are about to write in $year, before the first write. Those are all of the year's kontoplan rows (genberegn()
 * writes every one) and then the year's grupper RA row, the tables in the order genberegn() writes them (as an invoice's
 * genberegn() does). The transaction locks no row it would not lock a moment later anyway, only a few statements earlier.
 *
 * The close reads one REPEATABLE READ snapshot. When another session changed one of these rows after it and committed (a
 * posting into the active year), PostgreSQL refuses the lock with a serialization failure, as it refused the carry's
 * write before, where db_modify() ended the close with the database error box and a mail to fejl@saldi.dk. Here it is
 * OpeningCarryConflict: the caller rolls back and the clerk approves the count again. The same for a deadlock. Only these
 * two are a conflict (SQLSTATE 40001 and 40P01; MySQL and MariaDB error 1213). Any other error is left to the statements
 * after it, as before: on PostgreSQL the lock's savepoint is rolled back, the carry goes on, and the error reaches
 * db_modify()'s error box and mail there.
 *
 * @param int|string $year The fiscal year (grupper art RA kodenr) the carry writes next.
 * @throws OpeningCarryConflict On a serialization failure or a deadlock; the caller must roll its transaction back.
 */
function openingCarryLockYearRows($year) {
	global $connection, $db_type;
	$year = (int)$year;
	$statements = array(
		"select id from kontoplan where regnskabsaar = '$year' for update",
		"select id from grupper where art = 'RA' and kodenr = '$year' for update",
	);
	if ($db_type === 'mysql' || $db_type === 'mysqli') {
		foreach ($statements as $qtxt) {
			$errno = 0;
			$error = '';
			try {
				$result = mysqli_query($connection, $qtxt);
				if ($result === false) {
					$errno = (int) mysqli_errno($connection);
					$error = mysqli_error($connection);
				} elseif ($result instanceof mysqli_result) {
					mysqli_free_result($result);
				}
			} catch (mysqli_sql_exception $e) { // where mysqli_report() makes errors throw
				$errno = (int) $e->getCode();
				$error = $e->getMessage();
			}
			if ($errno === 1213) { // ER_LOCK_DEADLOCK: InnoDB has rolled the transaction back
				throw new OpeningCarryConflict('The opening balance carry could not lock the rows of fiscal year ' . $year . ': ' . trim($error));
			}
		}
		return;
	}
	// One statement on the tenant connection: the error's SQLSTATE and text, without db_select()'s error box, mail and exit.
	$run = function ($qtxt) use ($connection) {
		if (!@pg_send_query($connection, $qtxt)) {
			return array('', (string) pg_last_error($connection));
		}
		$state = $error = '';
		while (($result = pg_get_result($connection)) !== false) {
			if (pg_result_status($result) === PGSQL_FATAL_ERROR) {
				$state = (string) pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
				$error = (string) pg_result_error($result);
			}
		}
		return array($state, $error);
	};
	if ($run('savepoint opening_carry_rows')[1] !== '') {
		return; // no transaction to lock in: the carry goes on as before
	}
	foreach ($statements as $qtxt) {
		[$state, $error] = $run($qtxt);
		if ($state === '40001' || $state === '40P01') { // serialization_failure, deadlock_detected
			throw new OpeningCarryConflict('The opening balance carry could not lock the rows of fiscal year ' . $year . ': ' . trim($error));
		}
		if ($error !== '') {
			$run('rollback to savepoint opening_carry_rows');
			return;
		}
	}
	$run('release savepoint opening_carry_rows');
}

/**
 * Rebuild subsequent opening balances using the transfer rules from fiscalYearInc/yearX.php.
 * The caller owns the tenant transaction. Closed years retain their status.
 * When there is a following year to write, the carry first takes openingCarryLock() (held already when the caller took
 * openingCarryLockBeforeTransaction()) and reads everything after it, so a carry in another session is waited for (or,
 * by a transaction that reads from a snapshot, refused with OpeningCarryConflict) instead of overwritten. Posting into
 * the newest year has nothing to carry and takes no lock. A caller that does not pass $throwConflict (finans/bogfor.php)
 * does not get the conflict: openingCarryConflictExit() rolls its transaction back, tells the bookkeeper that nothing was
 * posted and to post again, and ends the request. A caller that passes it (the POS day close) also gets the rows of each
 * following year locked before the carry writes there (openingCarryLockYearRows()), so a row another session changed
 * after its snapshot is OpeningCarryConflict too. A primo that already holds the value it would get is not written.
 * Deleted years and missing predecessors form a boundary because their source data is unavailable.
 * A target year that lacks the destination account for a status or result balance is also a boundary,
 * so a partially transferable year is never written.
 *
 * A source year with a Sideskift (X) account is carried as it always was: each status (S) account onto its Overfør til
 * (or itself) and the year's result (the drift (D) accounts and the postings on the X account) onto the X account's
 * Overfør til. A source year without one, like the standard chart (importfiler/kontoplan.txt), is carried by the owner's
 * rule of 19 Nov 2026, "only the change goes forward": every status account but one is carried the same way, and the
 * result lands on the target year's result destination (resultDestinationWithoutSideskift(), 6935 Årets resultat ->
 * 6940 Overført resultat on the standard chart), which is not carried but set:
 * - OPENING_CARRY_PLACE (only when a year is created): minus the sum of the target year's other status openings, so the
 *   new year opens balanced. That is the destination's own carried balance plus the year's result plus postings on the R
 *   account or other accounts that are neither S nor D, so a result the bookkeeper moved within the source year
 *   (D 6935 / K 6940 dated in it) is not counted twice.
 * - OPENING_CARRY_DELTA (every other caller, and every year after the first target year): its stored opening less the
 *   change the call makes to the other status openings, so the sum of the target year's opening stays what it was. A
 *   result moved in by hand with a posting dated in the target year is not counted twice, and a year whose result was
 *   never placed stays off by it.
 * A target year without a result destination is a boundary, as a status balance without an account is.
 *
 * @param int|string $postedYear The fiscal year (grupper art RA kodenr) whose balances changed.
 * @param string $mode OPENING_CARRY_DELTA (the default) or OPENING_CARRY_PLACE; it applies to the first target year only.
 * @param array{reason: string, sourceYear: int, targetYear: int, account: int}|null $stop Set to null when the walk
 *   reached the newest year, else to why it stopped before targetYear, which with every later year is left as it was:
 *   'year' (targetYear or sourceYear is deleted, missing while a later year exists, or has no status accounts),
 *   'status' (the status account `account` of sourceYear has no account to land on in targetYear) or 'result' (the
 *   result has no account to land on in targetYear: the X account's Overfør til is not in its chart, or without an X
 *   account resultDestinationWithoutSideskift() names none). account is 0 unless the reason is 'status'.
 * @param bool $throwConflict True when the caller catches OpeningCarryConflict itself (the POS day close).
 * @return int[] Fiscal year numbers whose opening balances were updated.
 * @throws OpeningCarryConflict With $throwConflict, see openingCarryLock() and openingCarryLockYearRows(); the caller rolls
 *   its transaction back then, which may hold what the carry wrote in an earlier following year.
 */
function updateFollowingOpeningBalances($postedYear, $mode = OPENING_CARRY_DELTA, &$stop = null, $throwConflict = false) {
	if ($mode !== OPENING_CARRY_DELTA && $mode !== OPENING_CARRY_PLACE) {
		throw new InvalidArgumentException('Unknown opening balance carry mode.');
	}
	$postedYear = (int)$postedYear;
	$stop = null;
	$readYears = function () {
		$years = array();
		$q = db_select("select kodenr, box1, box2, box3, box4, box10 from grupper where art = 'RA'", __FILE__ . ' line ' . __LINE__);
		while ($row = db_fetch_array($q)) {
			$years[(int)$row['kodenr']] = $row;
		}
		return $years;
	};
	$years = $readYears();
	if (isset($years[$postedYear], $years[$postedYear + 1])) {
		// A following year to write: lock first (p1-pos-close-R1), then read the years and everything else the carry
		// uses, so nothing it writes comes from before another session's carry. The read above only decides this.
		try {
			openingCarryLock();
		} catch (OpeningCarryConflict $e) {
			if ($throwConflict) {
				throw $e;
			}
			// A journal (finans/bogfor.php) has no handler: a deadlock or a refused lock must not end in a PHP fatal error.
			openingCarryConflictExit($e, "Bogføringen er ikke gennemført, og intet er gemt: primo i de følgende regnskabsår kunne ikke opdateres sikkert, "
				. "fordi en anden bruger samtidig bogførte i et tidligere regnskabsår, godkendte en kasseoptælling eller gemte en åbningsbalance. Bogfør igen.");
		}
		$years = $readYears();
	}
	$stopAt = function ($reason, $sourceYear, $account = 0) use (&$stop) {
		$stop = array('reason' => $reason, 'sourceYear' => (int)$sourceYear, 'targetYear' => (int)$sourceYear + 1, 'account' => (int)$account);
	};
	$updated = array();
	for ($sourceYear = $postedYear; isset($years[$sourceYear], $years[$sourceYear + 1]); $sourceYear++) {
		$targetYear = $sourceYear + 1;
		$source = $years[$sourceYear];
		$target = $years[$targetYear];
		// Deleted: 'on' from older code, a timestamp from systemdata/fiscalYearInc/deleteFiscalYear.php (which also deletes the chart).
		if (trim((string)$source['box10']) !== '' || trim((string)$target['box10']) !== '') {
			$stopAt('year', $sourceYear);
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
		$storedOpening = array();
		$storedPrimo = array(); // every row's primo as stored, NULL included, per account number
		$q = db_select("select kontonr, primo from kontoplan where regnskabsaar = '$targetYear' and kontotype = 'S'", __FILE__ . ' line ' . __LINE__);
		while ($row = db_fetch_array($q)) {
			$opening[(int)$row['kontonr']] = 0;
			$storedOpening[(int)$row['kontonr']] = (float)afrund($row['primo'], 2);
			$storedPrimo[(int)$row['kontonr']][] = $row['primo'];
		}
		if (!$opening) {
			$stopAt('year', $sourceYear);
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
		$sourceHasStatus = false;
		$missingDestination = 0;
		$q = db_select("select kontonr, kontotype, primo, overfor_til from kontoplan where regnskabsaar = '$sourceYear' and kontotype in ('S', 'D', 'X') order by kontonr", __FILE__ . ' line ' . __LINE__);
		while ($row = db_fetch_array($q)) {
			$account = (int)$row['kontonr'];
			$amount = isset($movement[$account]) ? $movement[$account] : 0;
			if ($row['kontotype'] === 'D') {
				$profit += afrund($row['primo'], 2) + $amount;
			} elseif ($row['kontotype'] === 'X' && $resultAccount === null) {
				$resultAccount = $row;
			} elseif ($row['kontotype'] === 'S') {
				$sourceHasStatus = true;
				$destination = (int)$row['overfor_til'];
				if (!isset($opening[$destination])) $destination = $account;
				if (isset($opening[$destination])) {
					$opening[$destination] += (float)$row['primo'] + $amount;
				} elseif (!$missingDestination) {
					$missingDestination = $account;
				}
			}
		}
		if ($missingDestination) {
			// A balance has no account to land on in the target year, so leave it and later years untouched.
			$stopAt('status', $sourceYear, $missingDestination);
			break;
		}
		if ($resultAccount !== null) {
			$destination = (int)$resultAccount['overfor_til'];
			$account = (int)$resultAccount['kontonr'];
			if (!isset($opening[$destination])) {
				$stopAt('result', $sourceYear);
				break;
			}
			$opening[$destination] += afrund($profit, 2) + (isset($movement[$account]) ? $movement[$account] : 0);
		} else {
			if (!$sourceHasStatus) {
				// A source year without a chart has no balances to carry; setting the destination from the other openings
				// would empty the target year.
				$stopAt('year', $sourceYear);
				break;
			}
			$destination = resultDestinationWithoutSideskift($targetYear);
			if (!$destination) {
				$stopAt('result', $sourceYear);
				break;
			}
			// Only the first target year can be placed; every later one in the chain moves by the change only.
			$place = $mode === OPENING_CARRY_PLACE && $sourceYear === $postedYear;
			$others = 0;
			$change = 0;
			foreach ($opening as $account => $amount) {
				if ($account !== $destination) {
					$amount = (float)afrund($amount, 2);
					$others += $amount;
					$change += $amount - $storedOpening[$account];
				}
			}
			$opening[$destination] = $place ? -$others : $storedOpening[$destination] - $change;
		}
		if ($throwConflict) {
			// The POS day close reads one snapshot: a row a posting into this year changed after it is a conflict to approve
			// again, not the database error box (p1-pos-close-R1, review round 3).
			openingCarryLockYearRows($targetYear);
		}
		foreach ($opening as $account => $amount) {
			$amount = (float)afrund($amount, 2);
			$unchanged = true;
			foreach ($storedPrimo[$account] as $primo) {
				if ($primo === null || trim((string)$primo) === '' || abs((float)$primo - $amount) >= 0.0005) {
					$unchanged = false;
				}
			}
			if ($unchanged) {
				continue; // every row of the account already holds it: no write, so no row lock for it either
			}
			db_modify("update kontoplan set primo = '$amount' where regnskabsaar = '$targetYear' and kontonr = '$account' and kontotype = 'S'", __FILE__ . ' line ' . __LINE__);
		}
		// Avoid switching database connections while the journal transaction is active.
		genberegn($targetYear, false);
		$updated[] = $targetYear;
	}
	if ($stop === null && $years && max(array_keys($years)) > $sourceYear) {
		// The walk ended at a gap (the posted year or the one after it is missing) with later years after it.
		$stopAt('year', $sourceYear);
	}
	return $updated;
}

/**
 * Where updateFollowingOpeningBalances() puts the previous year's result in the opening of $year when the previous
 * year's chart has no Sideskift (X) account (owner's decision, 19 Nov 2026): the first status (S) account of $year's
 * chart, by account number, after its Resultat (R) account, the first R account by account number. On the standard
 * chart (importfiler/kontoplan.txt) that is 6940 Overført resultat after 6935 Årets resultat. An R account only shows
 * the result in the balance sheet (finans/rapport_includes/regnskab.php shows it with the value of its Fra konto), and
 * the result is the drift (D) accounts themselves, so no Sideskift account is needed to carry it.
 *
 * @param int|string $year The target fiscal year (grupper art RA kodenr).
 * @return int The account number, or 0 when $year's chart has no R account or no status account after it.
 */
function resultDestinationWithoutSideskift($year) {
	$year = (int)$year;
	$q = db_select("select kontonr from kontoplan where regnskabsaar = '$year' and kontotype = 'R' order by kontonr limit 1", __FILE__ . ' line ' . __LINE__);
	if (!($row = db_fetch_array($q))) {
		return 0;
	}
	$resultAccount = (int)$row['kontonr'];
	$q = db_select("select kontonr from kontoplan where regnskabsaar = '$year' and kontotype = 'S' and kontonr > '$resultAccount' order by kontonr limit 1", __FILE__ . ' line ' . __LINE__);
	return ($row = db_fetch_array($q)) ? (int)$row['kontonr'] : 0;
}
