<?php
// --- tests/test_zreport_pos_orders_only.php ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261029 CL/MMR SST-896 The X/Z-report counts only register sales: an ordinary invoice that got the
//                 register number in felt_5 stays out of turnover, VAT and payments, unless sets are enabled.
// Run with SALDI_CHAR_PGHOST / PGPORT / PGUSER / PGPASS / PGDATABASE (all prefixed SALDI_CHAR_).

error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
if (!extension_loaded('pgsql') || !getenv('SALDI_CHAR_PGHOST') || !getenv('SALDI_CHAR_PGUSER')) {
	echo "SKIP: PostgreSQL extension and SALDI_CHAR_PGHOST/PGUSER are required.\n";
	exit(0);
}
$parts = array();
foreach (array('host', 'port', 'user', 'password', 'dbname') as $key) {
	$env = array('password' => 'PASS', 'dbname' => 'DATABASE');
	$value = getenv('SALDI_CHAR_PG' . ($env[$key] ?? strtoupper($key)));
	if ($value !== false && $value !== '') {
		$parts[] = $key . "='" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $value) . "'";
	}
}
$connection = pg_connect(implode(' ', $parts), PGSQL_CONNECT_FORCE_NEW);
$schema = 'zreport_pos_only_test_' . bin2hex(random_bytes(6));

/** @return PgSql\Result */
function db_select($sql, $location) { return pg_query($GLOBALS['connection'], $sql); }
/** @return array|false */
function db_fetch_array($query) { return pg_fetch_assoc($query); }
/** @return string */
function db_escape_string($value) { return pg_escape_string($GLOBALS['connection'], (string)$value); }
/** @return void */
function checkZReport($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}
require_once(__DIR__ . '/../debitor/pos_ordre_includes/helperMethods/helperFuncII.php');
require_once(__DIR__ . '/../debitor/pos_ordre_includes/report/reportSubtract.php');
require_once(__DIR__ . '/../debitor/pos_ordre_includes/report/rapportData.php');
$regnaar = 10;
$today = date('Y-m-d');
try {
	pg_query($connection, "CREATE SCHEMA $schema");
	pg_query($connection, "SET search_path TO $schema");
	pg_query($connection, "CREATE TABLE grupper (art text, kodenr text, fiscal_year int, box12 text)");
	pg_query($connection, "INSERT INTO grupper VALUES ('POS', '2', 10, '')");
	pg_query($connection, "CREATE TABLE report (id serial, date date, type text, description text, count int, total float, report_number int)");
	pg_query($connection, "CREATE TABLE ordrer (id int PRIMARY KEY, felt_5 text, fakturadate date, art text, felt_1 text, betalt numeric)");
	pg_query($connection, "CREATE TABLE ordrelinjer (ordre_id int, pris numeric, antal numeric, rabat numeric, momssats numeric, vat_price numeric)");
	// Register 2 today: a POS card sale of 249 incl. 25 % VAT and an ordinary rent invoice of 12.000 without VAT
	// that got felt_5 = 2 from its department, as invoice 36 at Phone 2 Fix (pos_82) did. Plus a POS sale on register 1.
	pg_query($connection, "INSERT INTO ordrer VALUES
		(1, '2', '$today', 'PO', 'Visa', 249),
		(2, '2', '$today', 'DO', '', 0),
		(3, '1', '$today', 'PO', 'Visa', 100)");
	pg_query($connection, "INSERT INTO ordrelinjer VALUES
		(1, 199.2, 1, 0, 25, 249),
		(2, 12000, 1, 0, 0, 12000),
		(3, 80, 1, 0, 25, 100)");

	$ids = makeOrderIdArray(2, $today);
	checkZReport($ids === array('1'), 'Register 2 report covers only its POS sale, not the invoice');
	$turnover = calculateTurnover(2, $today);
	checkZReport(abs($turnover['turnoverWithVat'] - 249) < 0.001, 'Turnover with VAT is the POS sale only (249, not 12.249)');
	checkZReport(abs($turnover['turnoverWithoutVat'] - 199.2) < 0.001, 'Turnover without VAT is the POS sale only');
	// vatPayments() adds to price keys that do not exist yet (legacy, unchanged here); tolerate only that notice.
	set_error_handler(function($severity, $message, $file, $line) {
		if (strpos($message, 'Undefined array key') === 0 && strpos($file, 'rapportData.php') !== false) return true;
		throw new ErrorException($message, 0, $severity, $file, $line);
	});
	$vat = vatPayments(2, $today);
	restore_error_handler();
	checkZReport(!isset($vat['0']) && !isset($vat[0]), 'No 0 % VAT line from the invoice');
	$payments = paymentMethods(2, $today);
	checkZReport(abs($payments['sum']['price'] - 249) < 0.001 && isset($payments['Visa']), 'Payment total is the POS sale only');
	checkZReport(makeOrderIdArray(1, $today) === array('3'), 'Register 1 is unaffected');

	// With sets enabled debtor orders are handed in at the register, so they count, as posbogfor() posts them.
	pg_query($connection, "UPDATE grupper SET box12 = 'on'");
	$ids = makeOrderIdArray(2, $today);
	sort($ids);
	checkZReport($ids === array('1', '2'), 'With sets enabled the debtor order is included');
	$turnover = calculateTurnover(2, $today);
	checkZReport(abs($turnover['turnoverWithVat'] - 12249) < 0.001, 'With sets enabled turnover includes it');
	pg_query($connection, "UPDATE grupper SET box12 = ''");

	// A credit note to a debtor (art DK) is not a register sale either.
	pg_query($connection, "INSERT INTO ordrer VALUES (4, '2', '$today', 'DK', '', 0)");
	pg_query($connection, "INSERT INTO ordrelinjer VALUES (4, -500, 1, 0, 25, -625)");
	checkZReport(makeOrderIdArray(2, $today) === array('1'), 'A debtor credit note is left out without sets');

	// The register number and date reach SQL cast and escaped.
	checkZReport(makeOrderIdArray("2' or '1'='1", $today) === array('1'), 'The register number is int-cast');
} finally {
	pg_query($connection, "DROP SCHEMA IF EXISTS $schema CASCADE");
	pg_close($connection);
}
