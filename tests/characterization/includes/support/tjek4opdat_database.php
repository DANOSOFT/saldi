<?php
// 20261105 CL/LH One login for includes/tjek4opdat.php on an isolated PostgreSQL fixture: one PHP process, one connection.
//                Master and tenant are the same schema here, and opdat_4_3() and opdat_5_0() below stand in for the last two steps of the update chain.
//                tjek4opdat() still includes the real includes/opdat_4.3.php and opdat_5.0.php, which define nothing when these functions exist.

/**
 * Connect this process as one login to the fixture schema and set the globals tjek4opdat() reads.
 *
 * @param string $schema   Schema with grupper, regnskab and events.
 * @param string $regnskab The regnskab ($db) the login opens.
 * @param string $login    Name this login writes to events.
 * @param string $step     How the chain behaves: 'quick', 'slow' (a second inside the 5.1.0 step) or 'stops' (ends the
 *                         request half-way through the 5.1.0 step, after the 5.0.0 step was stamped).
 */
function tjekFixtureLogin($schema, $regnskab, $login, $step) {
	$connection = pg_connect(getenv('SALDI_CHAR_PG_DSN'), PGSQL_CONNECT_FORCE_NEW);
	if (!$connection) {
		throw new RuntimeException('Configured PostgreSQL connection is unavailable.');
	}
	pg_query($connection, 'SET search_path TO ' . $schema);
	pg_query($connection, "SET statement_timeout = '10s'");
	$GLOBALS['connection'] = $connection;
	$GLOBALS['db'] = $regnskab;
	$GLOBALS['sqdb'] = 'saldi_master';
	$GLOBALS['db_type'] = 'postgresql';
	$GLOBALS['sqhost'] = $GLOBALS['squser'] = $GLOBALS['sqpass'] = '';
	$GLOBALS['tjekLogin'] = $login;
	$GLOBALS['tjekStep'] = $step;
}

function db_select($sql, $spor, $global = false) {
	$result = pg_query($GLOBALS['connection'], $sql);
	if ($result === false) {
		throw new RuntimeException('Fixture statement failed: ' . pg_last_error($GLOBALS['connection']));
	}
	return $result;
}

function db_modify($sql, $spor, $global = false) {
	db_select($sql, $spor, $global);
	return "0\tquery accepted";
}

function db_fetch_array($result) {
	return $result ? pg_fetch_array($result) : false;
}

function db_escape_string($text) {
	return pg_escape_string($GLOBALS['connection'], (string) $text);
}

function db_connect($host, $user, $password, $database = '', $spor = '') {
	return $GLOBALS['connection'];
}

function db_select_database($connection, $database) {
	return true;
}

/**
 * Record in events what this login does now.
 */
function tjekFixtureEvent($what) {
	db_modify("INSERT INTO events (what) VALUES ('" . db_escape_string($GLOBALS['tjekLogin'] . ': ' . $what) . "')", 'event');
}

/**
 * Stamp grupper VE box1 and regnskab.version, as every step of the chain does at its end.
 */
function tjekFixtureStamp($version) {
	db_modify("UPDATE grupper SET box1 = '$version' WHERE art = 'VE'", 'stamp');
	db_modify("UPDATE regnskab SET version = '$version' WHERE db = '" . db_escape_string($GLOBALS['db']) . "'", 'stamp');
}

/**
 * The 4.3.0 -> 5.0.0 step as one login runs it: it records that it ran and stamps 5.0.0.
 */
function opdat_4_3() {
	tjekFixtureEvent('update to 5.0.0');
	tjekFixtureStamp('5.0.0');
}

/**
 * The 5.0.0 -> 5.1.0 step as one login runs it: it records that it ran, takes its time, and stamps 5.1.0.
 */
function opdat_5_0() {
	tjekFixtureEvent('update to 5.1.0');
	if ($GLOBALS['tjekStep'] !== 'quick') {
		db_select('SELECT pg_sleep(1)', 'opdat_5_0');
	}
	if ($GLOBALS['tjekStep'] === 'stops') {
		// A failed query ends the request like this (db_modify() and its error box), before the version is stamped.
		exit(3);
	}
	tjekFixtureStamp('5.1.0');
}
