<?php
// 20261020 CL/SZ MB-81 One save at a time per journal.
//
// A save stages the posted lines in tmpkassekl, which is keyed by the journal only, then checks them and moves
// them into kassekladde (opdater()). Two saves of the same journal at the same moment deleted and overwrote each
// other's staged lines, so one user's line was lost and the other's saved twice under the same voucher number.
// The lock is held from the first tmpkassekl write to its cleanup, so the second save starts when the first is
// done and sees the number it took. It is a session-level advisory lock: when a failing query ends the request,
// the connection closes and the lock goes with it. Same shape as pbs_laas()/pbs_frigiv() in includes/pbsfunc.php.
//
// 20261020 CL/SZ MB-81 (CodeRabbit) The page render reads and clears tmpkassekl too, so it takes the lock for that
// part as well, and a save keeps the lock it holds until then. The lock is also released when the request ends,
// for a persistent connection (a "p:" host on MySQL) that would otherwise keep it.

/**
 * Wait until no other request is using this journal's staged lines, then hold the lock until kkJournalUnlock() or
 * the end of the request. Does nothing when the request already holds it.
 *
 * @param int $kladde_id Journal (kladdeliste.id).
 */
function kkJournalLock($kladde_id) {
	global $db_type;
	$kladde_id = (int)$kladde_id;
	if ($kladde_id <= 0 || !empty($GLOBALS['kkJournalLocked'])) {
		return;
	}
	if ($db_type == 'mysql' || $db_type == 'mysqli') {
		// GET_LOCK names are server-wide, so the database is part of the name; -1 waits like pg_advisory_lock() does
		db_select("SELECT GET_LOCK(CONCAT('saldi:kassekladde:', MD5(DATABASE()), ':', $kladde_id), -1)", __FILE__ . " linje " . __LINE__);
	} else {
		db_select("SELECT pg_advisory_lock(hashtext('kassekladde'), $kladde_id)", __FILE__ . " linje " . __LINE__);
	}
	$GLOBALS['kkJournalLocked'] = $kladde_id;
	if (empty($GLOBALS['kkJournalUnlockOnShutdown'])) {
		$GLOBALS['kkJournalUnlockOnShutdown'] = true;
		register_shutdown_function('kkJournalUnlock');
	}
}

/**
 * Release the lock taken by kkJournalLock(), if this request holds one.
 */
function kkJournalUnlock() {
	global $db_type;
	$kladde_id = (int)($GLOBALS['kkJournalLocked'] ?? 0);
	if ($kladde_id <= 0) {
		return;
	}
	$GLOBALS['kkJournalLocked'] = 0;
	if ($db_type == 'mysql' || $db_type == 'mysqli') {
		db_select("SELECT RELEASE_LOCK(CONCAT('saldi:kassekladde:', MD5(DATABASE()), ':', $kladde_id))", __FILE__ . " linje " . __LINE__);
	} else {
		db_select("SELECT pg_advisory_unlock(hashtext('kassekladde'), $kladde_id)", __FILE__ . " linje " . __LINE__);
	}
}
