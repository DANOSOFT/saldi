<?php
// 20261119 CL/ASR Created: a MySQL or MariaDB named lock (GET_LOCK) waited for in rounds of 30 seconds for at most 2 minutes, for the opening-balance carry's lock (includes/updateFollowingOpeningBalances.php) and the journal save lock (finans/kassekladde_includes/journalSaveLock.php) (p1-yearend-F3, MB-81).
//                 MariaDB answers GET_LOCK(name, -1) with NULL and takes no lock, where MySQL waits without end, so both locks failed on MariaDB on every try.

/** Seconds one GET_LOCK() call of saldiNamedLockWait() waits for another session's lock. */
if (!defined('SALDI_NAMED_LOCK_WAIT_SECONDS')) define('SALDI_NAMED_LOCK_WAIT_SECONDS', 30);
/** Calls saldiNamedLockWait() makes while another session holds the lock: 4 of 30 seconds, so at most 2 minutes. */
if (!defined('SALDI_NAMED_LOCK_WAIT_ROUNDS')) define('SALDI_NAMED_LOCK_WAIT_ROUNDS', 4);

if (!function_exists('saldiNamedLockWait')) {
	/**
	 * Takes a MySQL or MariaDB named lock for this session, waiting while another session holds it, for at most
	 * $rounds times $seconds seconds.
	 *
	 * GET_LOCK(name, -1) waits without end on MySQL, but MariaDB answers a negative timeout at once with NULL and warning
	 * 1411 and takes no lock (MDEV-4017). So the lock is asked for with a timeout that both accept, and asked again while
	 * the answer says another session still holds it, as includes/tjek4opdat.php does; unlike there, the wait ends.
	 * GET_LOCK() answers 1 when this session has the lock, 0 when the timeout ran out while another session held it, and
	 * NULL on an error (on MySQL also when the wait was killed).
	 *
	 * @param callable(string): mixed $query Runs one select of one value on the connection the lock is for and returns
	 *   that value: '1' or '0' (or 1 or 0), null for SQL NULL or no row. An exception it throws reaches the caller.
	 * @param string $name SQL expression of the lock's name, such as "concat('saldi:x:', md5(database()))". GET_LOCK()
	 *   names are server-wide and at most 64 characters, so the database belongs in it, hashed.
	 * @param int $rounds How many times to ask while another session holds the lock (at least 1).
	 * @param int $seconds How long each GET_LOCK() waits (at least 0).
	 * @return string 'locked' when this session holds the lock, 'timeout' when another session held it through every
	 *   round, 'error' when the database answered NULL, no row or anything but 1 or 0.
	 */
	function saldiNamedLockWait(callable $query, $name, $rounds = SALDI_NAMED_LOCK_WAIT_ROUNDS, $seconds = SALDI_NAMED_LOCK_WAIT_SECONDS) {
		$rounds = max(1, (int) $rounds);
		$seconds = max(0, (int) $seconds);
		for ($round = 0; $round < $rounds; $round++) {
			$answer = $query("select get_lock($name, $seconds) as locked");
			if ($answer === null) {
				return 'error';
			}
			$answer = trim((string) $answer);
			if ($answer === '1') {
				return 'locked';
			}
			if ($answer !== '0') {
				return 'error';
			}
		}
		return 'timeout';
	}
}

if (!function_exists('saldiNamedLockWaitText')) {
	/**
	 * How long saldiNamedLockWait() waits at most, in Danish for an alert: "2 minutter".
	 *
	 * @return string
	 */
	function saldiNamedLockWaitText() {
		$seconds = SALDI_NAMED_LOCK_WAIT_ROUNDS * SALDI_NAMED_LOCK_WAIT_SECONDS;
		if ($seconds % 60) {
			return $seconds . ' sekunder';
		}
		return ($seconds / 60) . ($seconds === 60 ? ' minut' : ' minutter');
	}
}
