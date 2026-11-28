<?php
// 20261105 CL/LH Scripted database answers for Tjek4opdatLockTest: what GET_LOCK() and pg_advisory_lock() return, and the version.
//                Every query is recorded in $GLOBALS['tjekQueries'], and a run of the stand-in opdat_5_0() in $GLOBALS['tjekChain'].
// 20261115 CL/ASR Integration: the chain goes on from opdat_5_0() to master's opdat_5_1() (5.1.0 -> 5.1.1), whose real migrations need tables these scripted answers do not have; a stand-in that does nothing keeps the lock tests at 5.1.0. Release511UpgradeTest covers the real step.

/**
 * Set the globals tjek4opdat() reads for a tenant on $dbType, and the answers below.
 *
 * @param string            $dbType      'mysqli' or 'postgresql'.
 * @param array<int, mixed> $lockAnswers What GET_LOCK() answers, one per call ('1', '0' or null), or for pg_advisory_lock()
 *                                       whether the query gets through (true or false).
 * @param string            $version     The version grupper and regnskab hold.
 */
function tjekStubLogin($dbType, array $lockAnswers, $version) {
	$GLOBALS['db_type'] = $dbType;
	$GLOBALS['db'] = 'tenant_1';
	$GLOBALS['sqdb'] = 'saldi_master';
	$GLOBALS['sqhost'] = $GLOBALS['squser'] = $GLOBALS['sqpass'] = '';
	$GLOBALS['connection'] = 'connection';
	$GLOBALS['tjekLockAnswers'] = $lockAnswers;
	$GLOBALS['tjekVersion'] = $version;
	$GLOBALS['tjekQueries'] = [];
	$GLOBALS['tjekChain'] = [];
}

function db_select($sql, $spor, $global = false) {
	$GLOBALS['tjekQueries'][] = $sql;
	if (strpos($sql, 'GET_LOCK(') !== false) {
		return ['laast' => array_shift($GLOBALS['tjekLockAnswers'])];
	}
	if (strpos($sql, 'pg_advisory_lock(') !== false) {
		return (bool) array_shift($GLOBALS['tjekLockAnswers']);
	}
	if (strpos($sql, 'FROM grupper') !== false) {
		return ['box1' => $GLOBALS['tjekVersion']];
	}
	if (strpos($sql, 'FROM regnskab') !== false) {
		return ['version' => $GLOBALS['tjekVersion']];
	}
	return [];
}

function db_modify($sql, $spor, $global = false) {
	$GLOBALS['tjekQueries'][] = $sql;
	return "0\tquery accepted";
}

function db_fetch_array($result) {
	return is_array($result) && $result !== [] ? $result : false;
}

function db_escape_string($text) {
	return addslashes((string) $text);
}

function db_connect($host, $user, $password, $database = '', $spor = '') {
	return 'connection';
}

function db_select_database($connection, $database) {
	return true;
}

/**
 * The 5.0.0 -> 5.1.0 step: it records that it ran.
 */
function opdat_5_0() {
	$GLOBALS['tjekChain'][] = 'update to 5.1.0';
}

/**
 * The 5.1.0 -> 5.1.1 step (includes/opdat_5.1.php, which defines nothing when this exists): it does nothing here.
 */
function opdat_5_1() {
}
