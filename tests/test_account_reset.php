<?php
// --- tests/test_account_reset.php --- 2026-11-16 ---
// Copyright (c) 2003-2026 Danosoft ApS
// 20260916 CDX/PHR Verify reset scope and preservation options without database writes.
// 20260916 CDX/PHR Verify item texts follow the keep-items option.
// 20260916 CDX/PHR Verify user year reset and tenant-scoped session updates.
// 20260917 CL/LH Verify the reset is refused on MySQL/MariaDB before any statement is issued.
// 20261005 SS Verify personal settings (USET) are not deleted by a reset.
// 20261116 CL/ASR Verify the postings are counted under the lock and returned, and that a failed session update after the commit comes back as a warning, not an exception (review #1131).
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
	if ($global) $GLOBALS['resetSessionWebservice'] = !empty($GLOBALS['webservice']);
	return ($global && !empty($GLOBALS['resetFailSession'])) ? "1\tfejl" : "0\tquery accepted";
}
function db_select($sql, $trace) {
	$GLOBALS['resetStatementsIssued'][] = $sql;
	return $sql; # the handle is the query; db_fetch_array() answers it from $GLOBALS['resetRows']
}
function db_fetch_array($handle) {
	foreach ($GLOBALS['resetRows'] as $prefix => $rows) {
		if (strpos((string)$handle, $prefix) !== false) {
			return array_shift($GLOBALS['resetRows'][$prefix]) ?: false;
		}
	}
	return false;
}
function transaktion($what) {
	$GLOBALS['resetTransactions'][] = $what;
}
resetAccountSessionYear('test_41');
resetCheck($GLOBALS['resetSessionTestCall'] === array("UPDATE online SET regnskabsaar='1' WHERE db='test_41'", true), 'Session update must target only this tenant in the master database');
resetAccountSessionYear("test'41");
resetCheck($GLOBALS['resetSessionTestCall'][0] === "UPDATE online SET regnskabsaar='1' WHERE db='test''41'", 'Database name must be escaped');
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

// The PostgreSQL path: the postings are counted after the lock and returned, and a failed session update after the
// commit is a warning, because the ledger is reset by then (review #1131).
foreach (array(false, true) as $failSession) {
	$GLOBALS['resetStatementsIssued'] = array();
	$GLOBALS['resetTransactions'] = array();
	$GLOBALS['resetFailSession'] = $failSession;
	$GLOBALS['resetRows'] = array(
		'information_schema.tables' => array(array('table_name' => 'transaktioner'), array('table_name' => 'grupper')),
		'count(*) AS n FROM transaktioner' => array(array('n' => '5')),
		"FROM grupper WHERE art='RA'" => array(array('kodenr' => '1', 'box1' => '1', 'box2' => '2026', 'box3' => '12', 'box4' => '2026', 'box10' => '')),
	);
	$warning = 'unset';
	$deleted = resetAccount(false, false, 'postgresql', 'test_41', $warning);
	$issued = $GLOBALS['resetStatementsIssued'];
	$lockAt = array_search('LOCK TABLE transaktioner IN ACCESS EXCLUSIVE MODE', $issued, true);
	$countAt = array_search('SELECT count(*) AS n FROM transaktioner', $issued, true);
	resetCheck($lockAt !== false && $countAt !== false && $lockAt < $countAt, 'Postings must be counted after the lock is taken');
	resetCheck($GLOBALS['resetTransactions'] === array('begin', 'commit'), 'The lock, the count and the reset share one transaction');
	resetCheck($deleted === 5, 'The counted postings are returned');
	resetCheck($GLOBALS['resetSessionWebservice'] === true, 'The session update runs in webservice mode, so a failure cannot end the request');
	resetCheck(empty($GLOBALS['webservice']), 'Webservice mode is switched off again afterwards');
	if ($failSession) {
		resetCheck(strpos($warning, 'nulstillet') !== false, 'A failed session update comes back as a warning');
	} else {
		resetCheck($warning === '', 'No warning when the session update succeeds');
	}
}
$GLOBALS['resetFailSession'] = false;
echo "OK: postings counted under the lock and returned; a failed session update after the commit is a warning.\n";
