<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tjek4opdat.php --- ver 5.1.0 --- 2026-11-05---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 2013.09.06 Indsat $b=3;$c=0;  Søg 20130906  
// 2014.05.02 Indsat $b==4{ osv. PHR Danosoft Søg 20140502
// 2015.02.09 Indsat $b==5{ osv. PHR Danosoft Søg 20150209
// 2016.01.16 Indsat $b==6{ osv. PHR Danosoft Søg 20160116
// 2017.11.14 Indsat $b==7{ osv. PHR Søg 20171114
// 2019.06.06 PHR added $b==8{ etc. 20190606
// 20260206 PHR opdat_4.1
// 20260223 PHR Fixed opdat_4_1
// 20261027 CL/LH After opdat_4_3 go on to 5.0 ($a=5), so a 4.3.0 tenant reaches 5.1.0 in the same login.
// 20261105 CL/LH Two first logins at once both ran the chain, and the one-time 4.3.0 backfills in opdat_4.2.php inserted every delivery address and 'hoved' email twice.
//                The update of a regnskab now runs in one request at a time, held to the end of that request so betweenUpdates.php is done too.
//                After the lock the version is read again: a login that waited for another one goes on from where that one got to, or stops when it got to the end.
//                Without the lock the chain does not run. On MySQL/MariaDB it is asked for every 30 seconds, as MariaDB's GET_LOCK() does not wait on -1.
//                The callers write regnskab.version through tjek4opdat_skriv_version(), which writes only under the lock and from a new read.
//                A version a login read before another one updated the regnskab no longer takes regnskab.version back once that one is done.

include_once(__DIR__ . '/opdat_func/opdat_func.php');

if (!function_exists('tjek4opdat_laas')) {
	/**
	 * Take the lock that lets one request at a time bring this regnskab ($db) up to date, waiting for as long as another
	 * request holds it. It is held to the end of the request: after the update chain a login still runs
	 * includes/betweenUpdates.php, whose first CREATE INDEX IF NOT EXISTS fails on PostgreSQL when another login creates
	 * the same index at the same moment.
	 *
	 * It is a session lock on the master connection: the callers of tjek4opdat() come in on either connection and the
	 * chain switches between them, while the master connection stays the same for the whole request (and PostgreSQL
	 * advisory locks only meet inside one database). The shutdown function releases it, also when a failing query ends
	 * the request half-way, and also on a persistent connection that would otherwise keep it.
	 *
	 * @throws RuntimeException when the database does not give the lock (an error, which db_select() has shown): the
	 *                          update chain must not run beside another request's.
	 */
	function tjek4opdat_laas() {
		global $db, $db_type;
		if (!empty($GLOBALS['tjek4opdatLaas'])) {
			return;
		}
		$mysql = ($db_type == 'mysql' || $db_type == 'mysqli');
		$regnskab = db_escape_string($db);
		// GET_LOCK names are server-wide and at most 64 characters, so there the regnskab goes in hashed
		$navn = $mysql ? "CONCAT('saldi:tjek4opdat:', MD5('$regnskab'))" : "hashtext('saldi:tjek4opdat:$regnskab')";
		if ($mysql) {
			// MariaDB answers GET_LOCK(name, -1) with NULL instead of waiting without end as MySQL does, so it waits 30
			// seconds at a time: 1 is the lock, 0 is another request still holding it, NULL (or no row) is an error.
			do {
				$r = db_fetch_array(db_select("SELECT GET_LOCK($navn, 30) AS laast", __FILE__ . " linje " . __LINE__, true));
				$svar = $r ? $r['laast'] : null;
			} while ($svar !== null && (int)$svar === 0);
			$laast = ($svar !== null && (int)$svar === 1);
		} else {
			// pg_advisory_lock() waits and returns nothing: the query getting through is the lock
			$laast = (bool)db_select("SELECT pg_advisory_lock($navn)", __FILE__ . " linje " . __LINE__, true);
		}
		if (!$laast) {
			throw new RuntimeException('Could not acquire the update lock, so the update did not run.');
		}
		$GLOBALS['tjek4opdatLaas'] = $navn;
		register_shutdown_function('tjek4opdat_frigiv');
	}
}

if (!function_exists('tjek4opdat_frigiv')) {
	/**
	 * Release the lock taken by tjek4opdat_laas(), if this request holds it. Runs at the end of the request.
	 */
	function tjek4opdat_frigiv() {
		global $db_type;
		$navn = $GLOBALS['tjek4opdatLaas'] ?? '';
		if ($navn === '') {
			return;
		}
		$GLOBALS['tjek4opdatLaas'] = '';
		$qtxt = ($db_type == 'mysql' || $db_type == 'mysqli') ? "SELECT RELEASE_LOCK($navn)" : "SELECT pg_advisory_unlock($navn)";
		db_select($qtxt, __FILE__ . " linje " . __LINE__, true);
	}
}

if (!function_exists('tjek4opdat_version')) {
	/**
	 * Read the version of this regnskab ($db) again and write it to master as the pages did with the version they read
	 * before the lock: regnskab.version, brought down to the tenant's grupper VE row where that is lower or
	 * regnskab.version is empty. Called under tjek4opdat_laas(): no other request is updating the regnskab then, so what
	 * it reads is where the updates got to, and what it writes cannot take that back.
	 *
	 * Reads grupper on the tenant connection and leaves $connection as the caller had it.
	 *
	 * @return string The version the update goes on from, e.g. "4.3.0".
	 */
	function tjek4opdat_version() {
		global $db, $sqhost, $squser, $sqpass, $connection;
		$regnskab = db_escape_string($db);
		$r = db_fetch_array(db_select("SELECT version FROM regnskab WHERE db = '$regnskab'", __FILE__ . " linje " . __LINE__, true));
		$nu = $r ? trim((string)$r['version']) : '';
		$kaldersForbindelse = $connection;
		$connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " linje " . __LINE__);
		db_select_database($connection, $db);
		$g = db_fetch_array(db_select("SELECT box1 FROM grupper WHERE art = 'VE'", __FILE__ . " linje " . __LINE__));
		$connection = $kaldersForbindelse;
		if ($g && (!$nu || opdat_version_compare($nu, (string)$g['box1']) > 0)) {
			$nu = trim((string)$g['box1']);
			$qtxt = "UPDATE regnskab SET version = '" . db_escape_string($nu) . "' WHERE db = '$regnskab'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
		}
		return $nu;
	}
}

if (!function_exists('tjek4opdat_skriv_version')) {
	/**
	 * Bring regnskab.version on master in line with $dbver, the version a caller of tjek4opdat() read from it and from
	 * the tenant's grupper VE row (index/login.php, includes/login.php, includes/online.php). It is written only under
	 * tjek4opdat_laas(), from a new read (tjek4opdat_version()): a version read before another request updated the
	 * regnskab, written after that request let go of the lock, took regnskab.version back, and the next tjek4opdat() ran
	 * the update again from there (opdat_3.4.php's ALTER TABLE ordrelinjer ADD saet then fails on the column that is
	 * there). When regnskab.version already says $dbver there is nothing to write, and the lock is not taken: the usual
	 * login of a regnskab that is up to date does not wait for another one.
	 *
	 * @param string $dbver The version the caller read, e.g. "4.3.0".
	 */
	function tjek4opdat_skriv_version($dbver) {
		global $db;
		$qtxt = "SELECT version FROM regnskab WHERE db = '" . db_escape_string($db) . "'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true));
		if ($r && trim((string)$r['version']) === trim((string)$dbver)) {
			return;
		}
		tjek4opdat_laas();
		tjek4opdat_version();
	}
}

if (!function_exists('tjek4opdat')) {
	function tjek4opdat($dbver,$version) {
		global $db, $sqdb;
		if ($dbver<$version) {
			if ($db && $db != $sqdb) {
				// Two logins to a regnskab that is behind both get here. Side by side, both passed the checks of the one-time
				// backfills in opdat_4.2.php and inserted every row twice. So one request at a time goes on from here, to its
				// end (see tjek4opdat_laas()). $dbver was read before the lock, and another request may have moved the
				// regnskab on since: to the end (nothing left to do) or part of the way before it stopped (go on from there,
				// as not every step is safe to run twice). A version lower than the caller's is regnskab.version behind the
				// tenant's grupper VE row, which admin/aaben_regnskab.php reads alone, and is not followed.
				tjek4opdat_laas();
				$nu = tjek4opdat_version();
				if (opdat_version_compare($nu, $version) >= 0) {
					return;
				}
				if (opdat_version_compare($nu, $dbver) > 0) {
					$dbver = $nu;
				}
			}
			$tmp = str_replace(".",";",$dbver);		
			list($a, $b, $c)=explode(";", trim($tmp)); 
			if ($a==0) {
				include("../includes/opdat_0.php");
				opdat_0('1.0', $dbver);
				$a=1;$b=0;
			}
			if ($a==1) {
				if ($b==0) {
					include("../includes/opdat_1.0.php");
					opdat_1_0($b, $c);
					$b=1;
				}
				if ($b==1) {
					include("../includes/opdat_1.1.php");
					opdat_1_1($b, $c);
					$b=9; $c=0;
				}
				if ($b==9) {
					include("../includes/opdat_1.9.php");
					opdat_1_9($b, $c);
					$a=2;$b=0;$c=0;
				}
			} 
			if ($a==2) {
				if ($b==0) {	
				include("../includes/opdat_2.0.php");
					opdat_2_0($b, $c);
					$b=1;$c=0;
				}
				if ($b==1) {
				include("../includes/opdat_2.1.php");
					opdat_2_1($b,$c);
					$a=3;$b=0;$c=0;
				}	
			} 
			if ($a==3) {
				if ($b==0) {	
					include("../includes/opdat_3.0.php");
					opdat_3_0($b,$c);
					$b=1;$c=0;
				}
				if ($b==1) {
					include("../includes/opdat_3.1.php");
					opdat_3_1($b,$c);
					$b=2;$c=0;
				}
				if ($b==2) {
					include("../includes/opdat_3.2.php");
					opdat_3_2($b,$c);
					$b=3;$c=0; #20130906
				}
				if ($b==3) {
					include("../includes/opdat_3.3.php");
					opdat_3_3($b,$c);
					$b=4;$c=0;
				}
				if ($b==4) { #20140502
					include("../includes/opdat_3.4.php");
					opdat_3_4($b,$c);
					$b=5;$c=0;
				}
				if ($b==5) { #20150209
					include("../includes/opdat_3.5.php");
					opdat_3_5($b,$c);
					$b=6;$c=0;
				}
				if ($b==6) { #20161016
					include("../includes/opdat_3.6.php");
					opdat_3_6($b,$c);
					$b=7;$c=0;
				}
				if ($b==7) { #20171114
					include("../includes/opdat_3.7.php");
					opdat_3_7($b,$c);
					$b=8;$c=0;
				}
				if ($b==8) { #20190606
					include("../includes/opdat_3.8.php");
					opdat_3_8($b,$c);
					$b=9;$c=0;
				}
				if ($b==9) { #20190606
					if (!function_exists('opdat_3_9')) include("../includes/opdat_3.9.php");
					opdat_3_9($a,$b,$c);
					$a=4;$b=0;$c=0;
				}
			}
			if ($a==4) {
				if ($b==0) {	
					include("../includes/opdat_4.0.php");
					opdat_4_0($a,$b,$c);
					$b=1;$c=0;
				}
				if ($b==1) {
					include("../includes/opdat_4.1.php");
					opdat_4_1($a,$b,$c);
					$b=2;$c=0;
				}
				if ($b==2) {
					include("../includes/opdat_4.2.php");
					opdat_4_2($a,$b,$c);
					$b=3;$c=0;
				}
				if ($b==3) {
					include("../includes/opdat_4.3.php");
					opdat_4_3();
					$a=5;$b=0;$c=0;
				}
			}
			if ($a==5) {
				if ($b==0) {	
					include("../includes/opdat_5.0.php");
					opdat_5_0();
					$b=1;$c=0;
				}
			}
		}
	}
}
?>
