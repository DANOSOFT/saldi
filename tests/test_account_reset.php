<?php
// --- tests/test_account_reset.php --- 2026-11-18 ---
// Copyright (c) 2003-2026 Danosoft ApS
// 20260916 CDX/PHR Verify reset scope and preservation options without database writes.
// 20260916 CDX/PHR Verify item texts follow the keep-items option.
// 20260916 CDX/PHR Verify user year reset and tenant-scoped session updates.
// 20260917 CL/LH Verify the reset is refused on MySQL/MariaDB before any statement is issued.
// 20261005 SS Verify personal settings (USET) are not deleted by a reset.
// 20261118 CL/MMR SST-1016 Verify the session update leaves the customer sessions (rettigheder '0', regnskabsaar 0) at year 0.
require_once(__DIR__ . '/../systemdata/resetAccount.php');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
function resetCheck($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}
$added = explode(' ', 'documents paperflow pool_files betalingslink voucher voucheruse stripe_catalog stripe_customers stripe_events stripe_import_failures stocklog stockmovement order_stock_warning_log materialer emballage emballage_cat rental rentalclosed rentalitems rentalmail rentalpayment rentalperiod rentalremote rentalremoteperiods rentalreserved rentalsettings kds_records pos_events labels mylabel timereg_breaks timereg_sessions moms_periode_luk notifications datatables tekster');
$existing = array_merge($added, array('ordrer','transaktioner','grupper','kontoplan','settings','adresser','ansatte','varer','vare_lev','styklister','varetilbud','variant_typer','variant_varer','varianter','brugere','formularer','pos_buttons','varetekster','unexpected_table'));
foreach (array(false, true) as $keepAccounts) {
	foreach (array(false, true) as $keepItems) {
		$sql = accountResetStatements($existing, $keepAccounts, $keepItems, 'postgresql');
		$truncated = explode(',', substr($sql[0], 9, -17));
		foreach ($added as $table) {
			resetCheck(in_array($table, $truncated, true), 'Missing requested table: ' . $table);
		}
		foreach (array('brugere','formularer','pos_buttons','unexpected_table','crm','tabeller') as $table) {
			resetCheck(!in_array($table, $truncated, true), 'Unexpected truncation: ' . $table);
		}
		resetCheck(in_array('ansatte', $truncated, true) === !$keepAccounts, 'Keep accounts option');
		resetCheck(in_array('varer', $truncated, true) === !$keepItems, 'Keep items option');
		resetCheck(in_array('varetekster', $truncated, true) === !$keepItems, 'Item texts must follow the keep items option');
		resetCheck(in_array("UPDATE brugere SET regnskabsaar='1'", $sql, true), 'All users must select financial year 1');
		resetCheck(in_array('vare_lev', $truncated, true) === (!$keepAccounts || !$keepItems), 'Supplier-item relation option');
		resetCheck((strpos(implode(';', $sql), 'DELETE FROM adresser') !== false) === !$keepAccounts, 'Address deletion option');
		resetCheck(strpos(implode(';', $sql), "'USET'") === false, 'Personal settings (USET) must survive a reset');
	}
}
resetCheck(accountResetStatements(array(), false, false, 'postgresql') === array(), 'Empty schema');
$legacy = accountResetStatements(array('crm','tabeller'), true, true, 'postgresql');
resetCheck($legacy === array('TRUNCATE crm,tabeller RESTART IDENTITY'), 'Legacy tables must still be reset when present');
resetCheck(accountResetStatements(array('documents'), true, true, 'mysqli') === array('TRUNCATE TABLE `documents`'), 'MySQL truncate syntax');
function db_escape_string($value) {
	return str_replace("'", "''", $value);
}
function db_modify($sql, $trace, $global = false) {
	$GLOBALS['resetStatementsIssued'][] = $sql;
	$GLOBALS['resetSessionTestCall'] = array($sql, $global);
	return "0\tquery accepted";
}
function db_select($sql, $trace) {
	$GLOBALS['resetStatementsIssued'][] = $sql;
	return null;
}
resetAccountSessionYear('test_41');
resetCheck($GLOBALS['resetSessionTestCall'] === array("UPDATE online SET regnskabsaar='1' WHERE db='test_41' AND NOT (COALESCE(rettigheder, '') = '0' AND COALESCE(regnskabsaar, -1) = 0)", true), 'Session update must target only this tenant in the master database and skip the customer sessions');
resetAccountSessionYear("test'41");
resetCheck($GLOBALS['resetSessionTestCall'][0] === "UPDATE online SET regnskabsaar='1' WHERE db='test''41' AND NOT (COALESCE(rettigheder, '') = '0' AND COALESCE(regnskabsaar, -1) = 0)", 'Database name must be escaped');
echo "OK: reset scope, preservation options, user year 1 and tenant-scoped active sessions.\n";

$years = array(
 array('kodenr'=>1, 'box1'=>1, 'box2'=>2006, 'box3'=>12, 'box4'=>2006, 'box10'=>'on'),
 array('kodenr'=>21, 'box1'=>1, 'box2'=>2026, 'box3'=>12, 'box4'=>2026, 'box10'=>''),
 array('kodenr'=>22, 'box1'=>1, 'box2'=>2027, 'box3'=>12, 'box4'=>2027, 'box10'=>'1780000000'),
 array('kodenr'=>99, 'box1'=>7, 'box2'=>2024, 'box3'=>6, 'box4'=>2025, 'box10'=>null),
);
resetCheck(accountResetLatestYear($years) === 21, 'Newest undeleted year must be chosen by dates');
resetCheck(accountResetLatestYear(array($years[1])) === 21, 'Original year 1 need not exist');
$failed = false;
try {
 accountResetLatestYear(array($years[0], $years[2]));
} catch (RuntimeException $e) {
 $failed = true;
}
resetCheck($failed, 'All-deleted years must abort the reset');
$sql = accountResetStatements($existing, true, true, 'postgresql', 21);
resetCheck(in_array("DELETE FROM kontoplan WHERE regnskabsaar!='21'", $sql, true), 'Keep latest chart');
resetCheck(in_array("UPDATE kontoplan SET regnskabsaar='1' WHERE regnskabsaar='21'", $sql, true), 'Renumber latest chart');
resetCheck(in_array("UPDATE grupper SET fiscal_year=1 WHERE fiscal_year=21", $sql, true), 'Renumber latest year settings');
echo "OK: latest valid year, deleted/missing year 1, date ordering and year renumbering.\n";

// MySQL/MariaDB commits implicitly on TRUNCATE, so the reset must be refused up front rather than
// risk a half-reset account that transaktion('rollback') cannot undo.
foreach (array('mysql', 'mysqli', 'MySQLi') as $dbType) {
	$GLOBALS['resetStatementsIssued'] = array();
	$refused = '';
	try {
		resetAccount(false, false, $dbType, 'test_41');
	} catch (RuntimeException $e) {
		$refused = $e->getMessage();
	}
	resetCheck(strpos($refused, 'MySQL/MariaDB') !== false, 'Reset must be refused on ' . $dbType . ' with a Danish explanation');
	resetCheck($GLOBALS['resetStatementsIssued'] === array(), 'Reset on ' . $dbType . ' must refuse before issuing any statement');
}
echo "OK: MySQL/MariaDB reset refused before any statement is issued.\n";

// SST-1016: the reset's session update on a real PostgreSQL online table (a TEMP table, gone with the connection).
// Three staff and two customer sessions (Mit salg 3024, booking) on the reset account, plus another account's rows;
// with 4 users allowed, the next login must still fit after the reset.
require_once(__DIR__ . '/characterization/support/database/TestDatabase.php');
// The shared helper leaves the port to libpq; keep honouring SALDI_CHAR_PGPORT by handing it on as PGPORT (an explicit PGPORT wins).
$charPgPort = getenv('SALDI_CHAR_PGPORT');
if ($charPgPort !== false && $charPgPort !== '' && (string) getenv('PGPORT') === '') {
	putenv('PGPORT=' . $charPgPort);
}
$pgUnavailable = TestDatabase::unavailableReason();
if ($pgUnavailable !== null) {
	echo "SKIP: reset session update on PostgreSQL (" . $pgUnavailable . ").\n";
} else {
	// The configured test master database (SALDI_CHAR_MASTER_DB), as the characterization tests use it; only a TEMP table is written.
	$pg = TestDatabase::connect(TestDatabase::masterDb());
	resetCheck(pg_query($pg, "CREATE TEMP TABLE online (session_id varchar(32), brugernavn text, db text, rettigheder text, regnskabsaar integer, revisor boolean)") !== false, 'TEMP online table');
	// The unqualified name must resolve to the TEMP table, never to a real online table in that database.
	resetCheck(pg_fetch_result(pg_query($pg, "SELECT n.nspname LIKE 'pg_temp%' FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.oid = 'online'::regclass"), 0, 0) === 't', 'online must be the TEMP table');
	$seeded = pg_query($pg, "INSERT INTO online VALUES
		('s1', 'tom1', 'sst1016_1', '0000011001000000', 10, null),
		('s2', 'tom2', 'sst1016_1', '1111111111111111', 10, false),
		('s3', 'tom3', 'sst1016_1', '0', 10, false),
		('s4', '3024', 'sst1016_1', '0', 0, false),
		('s5', 'booking', 'sst1016_1', '0', 0, false),
		('s6', 'anna', 'other_7', '1111111111111111', 5, false),
		('s7', '4711', 'other_7', '0', 0, false)");
	resetCheck($seeded !== false, 'Seed rows in the TEMP online table');
	resetAccountSessionYear('sst1016_1');
	resetCheck(pg_query($pg, $GLOBALS['resetSessionTestCall'][0]) !== false, 'The reset session update must run on PostgreSQL');
	$rows = array();
	$res = pg_query($pg, "SELECT brugernavn, regnskabsaar FROM online ORDER BY session_id");
	while ($row = pg_fetch_assoc($res)) {
		$rows[$row['brugernavn']] = (int) $row['regnskabsaar'];
	}
	resetCheck($rows === array('tom1' => 1, 'tom2' => 1, 'tom3' => 1, '3024' => 0, 'booking' => 0, 'anna' => 5, '4711' => 0),
		'Staff of the reset account get year 1, its customer sessions keep year 0, another account is untouched');
	// The max users count from index/login.php (maxUsersUserSessionCondition()).
	$res = pg_query($pg, "SELECT COUNT(DISTINCT brugernavn) AS n FROM online WHERE db = 'sst1016_1' AND revisor IS NOT true"
		. " AND NOT (COALESCE(rettigheder, '') = '0' AND COALESCE(regnskabsaar, -1) = 0)");
	$counted = (int) pg_fetch_result($res, 0, 'n');
	resetCheck($counted === 3 && $counted < 4, 'After the reset only the 3 staff count, so a 4th user can log in');
	pg_close($pg);
	echo "OK: reset session update on PostgreSQL keeps the customer sessions out of the max users count.\n";
}
