<?php
// --- tests/test_following_opening_balances.php --- patch 5.0.1 --- 2026.09.07 ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260907 CDX/PHR Verify chained opening balances against isolated PostgreSQL temporary tables.
// 20260907 CL/NTR  Cover missing status and result destinations in the target year.
// Run with SALDI_TEST_DSN and libpq credentials (for example PGPASSFILE).

if (PHP_SAPI !== 'cli') exit('CLI only');
require_once(__DIR__ . '/../includes/genberegn.php');
require_once(__DIR__ . '/../includes/updateFollowingOpeningBalances.php');
$dsn = getenv('SALDI_TEST_DSN');
if (!$dsn) exit("Set SALDI_TEST_DSN to a PostgreSQL test connection.\n");
$connection = pg_connect($dsn);
if (!$connection) throw new RuntimeException('Could not connect to the test database.');

function db_select($sql, $location = '') {
	$result = pg_query($GLOBALS['connection'], $sql);
	if (!$result) throw new RuntimeException(pg_last_error($GLOBALS['connection']));
	return $result;
}
function db_modify($sql, $location = '') { return db_select($sql, $location); }
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function afrund($value, $decimals) { return round((float)$value, $decimals); }
function checkOpening($condition, $message) {
	if (!$condition) throw new RuntimeException($message);
	echo "PASS: $message\n";
}
function accountValue($year, $account, $field) {
	$row = db_fetch_array(db_select("select $field from kontoplan where regnskabsaar=$year and kontonr=$account"));
	return (float)$row[$field];
}

db_modify('begin');
try {
	// Temporary names shadow tenant tables; no persistent tables or rows are changed.
	db_modify("create temp table grupper (kodenr integer, art text, box1 text, box2 text, box3 text, box4 text, box5 text, box6 text, box7 text, box8 text, box10 text)");
	db_modify("create temp table kontoplan (id serial, regnskabsaar integer, kontonr integer, kontotype text, primo numeric default 0, saldo numeric default 0, overfor_til integer default 0, fra_kto integer default 0, lukket text default '')");
	db_modify("create temp table transaktioner (id serial, kontonr integer, transdate date, debet numeric default 0, kredit numeric default 0)");
	foreach (array(8, 9, 10, 11) as $year) {
		$calendar = 2014 + $year;
		$end = $calendar + 1;
		$open = $year === 10 ? '' : 'on';
		db_modify("insert into grupper (kodenr,art,box1,box2,box3,box4,box5,box10) values ($year,'RA','7','$calendar','6','$end','$open','')");
		foreach (array(1000 => 'D', 1999 => 'X', 2000 => 'S', 2100 => 'S', 2500 => 'S', 2900 => 'Z', 2950 => 'R', 3000 => 'S') as $account => $type) {
			$destination = $account === 1999 ? 3000 : ($account === 2100 ? 2000 : 0);
			$from = $type === 'Z' ? 2000 : ($type === 'R' ? 2900 : 0);
			db_modify("insert into kontoplan (regnskabsaar,kontonr,kontotype,primo,saldo,overfor_til,fra_kto) values ($year,$account,'$type',777,888,$destination,$from)");
		}
	}
	db_modify("update kontoplan set primo=0,saldo=0 where regnskabsaar=9");
	db_modify("update kontoplan set primo=1000 where regnskabsaar=9 and kontonr=2000");
	db_modify("update kontoplan set primo=-1000 where regnskabsaar=9 and kontonr=3000");
	db_modify("insert into transaktioner (kontonr,transdate,debet,kredit) values
		(2000,'2024-02-29',100,0), (1000,'2024-02-29',0,100),
		(2100,'2024-06-30',20,0), (1000,'2024-06-30',0,20),
		(2000,'2024-07-01',50,0), (1000,'2024-07-01',0,50),
		(2000,'2025-07-01',25,0), (1000,'2025-07-01',0,25)");
	checkOpening(updateFollowingOpeningBalances(9) === array(10, 11), 'Update every subsequent year in numeric order');
	checkOpening(accountValue(10, 2000, 'primo') === 1120.0, 'Merge transfer accounts and use the same-account fallback');
	checkOpening(accountValue(10, 3000, 'primo') === -1120.0, 'Transfer annual profit through the X account');
	checkOpening(accountValue(11, 2000, 'primo') === 1170.0, 'Propagate the corrected opening and intermediate-year movement');
	checkOpening(accountValue(11, 3000, 'primo') === -1170.0, 'Accumulate retained profit over multiple years');
	checkOpening(accountValue(11, 2000, 'saldo') === 1195.0, 'Recalculate the latest-year closing balance');
	checkOpening(accountValue(10, 2900, 'saldo') === 1170.0 && accountValue(10, 2950, 'saldo') === 1170.0, 'Recalculate subtotal and reference accounts');
	checkOpening(accountValue(10, 2500, 'primo') === 0.0 && accountValue(10, 1000, 'primo') === 0.0, 'Clear obsolete opening values without carrying income accounts forward');
	checkOpening(accountValue(8, 1000, 'primo') === 777.0 && accountValue(9, 2000, 'primo') === 1000.0, 'Preserve earlier years and the posted year opening');
	$row = db_fetch_array(db_select("select box5 from grupper where kodenr=10"));
	checkOpening($row['box5'] === '', 'Update a closed year without reopening it');
	updateFollowingOpeningBalances(9);
	checkOpening(accountValue(11, 2000, 'primo') === 1170.0, 'Repeated recalculation is idempotent');
	checkOpening(updateFollowingOpeningBalances(11) === array(), 'Posting in the newest year does not change openings');
	db_modify('savepoint missing_status_destination');
	db_modify("update kontoplan set primo=4444 where regnskabsaar in (10, 11) and kontonr=2000");
	db_modify("delete from kontoplan where regnskabsaar=10 and kontonr=2500");
	checkOpening(updateFollowingOpeningBalances(9) === array(), 'Stop when a status account has neither a transfer nor a same-number account in the next year');
	checkOpening(accountValue(10, 2000, 'primo') === 4444.0 && accountValue(11, 2000, 'primo') === 4444.0, 'Leave the target year and later years unwritten when a status destination is missing');
	db_modify('rollback to savepoint missing_status_destination');
	db_modify('savepoint missing_result_destination');
	db_modify("update kontoplan set primo=4444 where regnskabsaar in (10, 11) and kontonr=2000");
	db_modify("update kontoplan set overfor_til=3999 where regnskabsaar=9 and kontonr=1999");
	checkOpening(updateFollowingOpeningBalances(9) === array(), 'Stop when the result account transfers to an account missing from the next year');
	checkOpening(accountValue(10, 2000, 'primo') === 4444.0 && accountValue(11, 2000, 'primo') === 4444.0, 'Leave the target year and later years unwritten when the result destination is missing');
	db_modify('rollback to savepoint missing_result_destination');
	checkOpening(updateFollowingOpeningBalances(9) === array(10, 11) && accountValue(11, 2000, 'primo') === 1170.0, 'Resume propagation once every destination exists again');
	db_modify('savepoint additional_posting');
	db_modify("insert into transaktioner (kontonr,transdate,debet,kredit) values (2000,'2024-03-01',5.25,0),(1999,'2024-03-01',0,5.25)");
	updateFollowingOpeningBalances(9);
	checkOpening(accountValue(11, 2000, 'primo') === 1175.25 && accountValue(11, 3000, 'primo') === -1175.25, 'Include a backdated posting directly to the result account');
	db_modify('rollback to savepoint additional_posting');
	checkOpening(accountValue(11, 2000, 'primo') === 1170.0, 'Opening updates roll back with the posting transaction');
	db_modify("update grupper set box10='on' where kodenr=10");
	checkOpening(updateFollowingOpeningBalances(9) === array(), 'Preserve opening balances across deleted-year boundaries');
	db_modify("delete from grupper where kodenr=10");
	checkOpening(updateFollowingOpeningBalances(9) === array(), 'Do not jump over a missing fiscal year');
} finally {
	db_modify('rollback');
	pg_close($connection);
}
