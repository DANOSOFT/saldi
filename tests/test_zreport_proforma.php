<?php
// --- tests/test_zreport_proforma.php ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261026 CL/MMR SST-897 Exercise the X/Z-report proforma counter and the saved report rows on an isolated PostgreSQL schema.
// 20261027 CL/MMR SST-897 A proforma receipt printed between reading and saving the Z-report stays on the counter (CodeRabbit, PR #894).
//                 The Z-report's row lock makes a second Z-report and the real proformaCount() writer wait (Codex verification round 2).
// Run with SALDI_CHAR_PGHOST / PGPORT / PGUSER / PGPASS / PGDATABASE (all prefixed SALDI_CHAR_).

error_reporting(E_ALL);
// A failing query raises a PHP warning; turning it into an exception stops the test the way
// the live page stops on its generic database error alert.
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
if (($argv[1] ?? '') === 'writer') {
	// Child process: print one proforma receipt of 45,00 on register 2 with the real proformaCount().
	pg_query($connection, 'SET search_path TO ' . preg_replace('/[^a-z0-9_]/', '', $argv[2]));
	set_error_handler(null);
	// db_select(), db_fetch_array() and db_modify() are the stubs declared further down in this file.
	/** @return float std_func.php's afrund(), which dkdecimal() calls. */
	function afrund($number, $decimals) { return round((float)$number, (int)$decimals); }
	require_once(__DIR__ . '/../includes/stdFunc/dkDecimal.php');
	require_once(__DIR__ . '/../debitor/pos_ordre_includes/helperMethods/helperFunc.php');
	proformaCount(0, array('45'), '2');
	echo "writer done\n";
	exit(0);
}
$schema = 'zreport_proforma_test_' . bin2hex(random_bytes(6));

/** @return PgSql\Result */
function db_select($sql, $location) { return pg_query($GLOBALS['connection'], $sql); }
/** @return array|false */
function db_fetch_array($query) { return pg_fetch_assoc($query); }
/** @return PgSql\Result */
function db_modify($sql, $location) { return pg_query($GLOBALS['connection'], $sql); }
/** @return string */
function db_escape_string($value) { return pg_escape_string($GLOBALS['connection'], (string)$value); }
/** @return PgSql\Result transaktion() as db_query.php runs it, on the test connection. */
function transaktion($qtext) { return pg_query($GLOBALS['connection'], $qtext); }
/** @return int The report number saveLastReport() stores; reportSetup.php is not loaded here. */
function getReportNumber($header) { return 4711; }
/** @return void */
function checkZReport($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}
/** @return string */
function firstValue($sql) {
	return pg_fetch_result(pg_query($GLOBALS['connection'], $sql), 0, 0);
}
require_once(__DIR__ . '/../debitor/pos_ordre_includes/report/reportSubtract.php');
require_once(__DIR__ . '/../debitor/pos_ordre_includes/report/rapportReceiptData.php');
require_once(__DIR__ . '/../debitor/pos_ordre_includes/report/saveReport.php');
$kasse = '2';
try {
	pg_query($connection, "CREATE SCHEMA $schema");
	pg_query($connection, "SET search_path TO $schema");
	pg_query($connection, 'CREATE TABLE report (id serial, date date, type text, description text, count integer, total float, report_number integer)');
	foreach (array('corrections', 'price_correction', 'returnings') as $table) {
		pg_query($connection, "CREATE TABLE $table (id serial, price numeric(15,3), kasse integer, report_number integer DEFAULT 0)");
	}
	pg_query($connection, 'CREATE TABLE deleted_order (id serial, price numeric(15,3), kasse integer, ordre_id integer, report_number integer DEFAULT 0)');
	// proforma as includes/opdat_3.7.php and opdat_3.8.php create it: one running row per register, no report_number or kasse column.
	pg_query($connection, 'CREATE TABLE proforma (id serial NOT NULL, price numeric(15,3), count integer, PRIMARY KEY (id))');
	pg_query($connection, 'INSERT INTO proforma (id, price, count) VALUES (1, 40, 1), (2, 300, 3)');
	pg_query($connection, 'INSERT INTO corrections (price, kasse) VALUES (10, 1), (20, 2)');

	$interim = proformaReceipts($kasse);
	checkZReport((int)$interim['count'] === 3 && (float)$interim['totalPrice'] === 300.0,
		'Read the register proforma counter on a table without report_number (X-report)');
	checkZReport(firstValue('SELECT count FROM proforma WHERE id = 2') === '3', 'Reading the counter leaves it unchanged');

	saveLastReport(array('proformaReceiptsArray' => $interim));
	$saved = pg_fetch_assoc(pg_query($connection, "SELECT count, total, report_number FROM report WHERE type = 'Proforma receipt'"));
	checkZReport($saved['count'] === '3' && (float)$saved['total'] === 300.0 && $saved['report_number'] === '4711',
		'The Z-report saves the proforma count and total');
	checkZReport((float)firstValue('SELECT price FROM proforma WHERE id = 2') === 0.0 && firstValue('SELECT count FROM proforma WHERE id = 2') === '0',
		'Saving the Z-report resets this register counter');
	checkZReport((float)firstValue('SELECT price FROM proforma WHERE id = 1') === 40.0 && firstValue('SELECT count FROM proforma WHERE id = 1') === '1',
		'Other registers keep their counter');
	checkZReport(firstValue('SELECT report_number FROM corrections WHERE kasse = 2') === '4711' && firstValue('SELECT report_number FROM corrections WHERE kasse = 1') === '0',
		'Corrections are still marked for this register only');

	// The next proforma print adds to the same row, as proformaCount() does.
	pg_query($connection, 'UPDATE proforma SET price = price + 125, count = count + 1 WHERE id = 2');
	$next = proformaReceipts($kasse);
	checkZReport((int)$next['count'] === 1 && (float)$next['totalPrice'] === 125.0,
		'The next report counts only proforma receipts printed after the Z-report');

	// Item group names and payment names end up in the description and may contain an apostrophe.
	writeToDatabase('2026/10/26', 'Item group description', "Kid's", 2, 4712, 99.5);
	checkZReport(firstValue('SELECT description FROM report WHERE report_number = 4712') === "Kid's",
		'A description with an apostrophe is stored as written');

	// On ssl3/no the proforma table got report_number and kasse by hand, with NULL in every row.
	pg_query($connection, 'ALTER TABLE proforma ADD COLUMN report_number integer, ADD COLUMN kasse integer');
	pg_query($connection, 'UPDATE proforma SET price = 62, count = 5 WHERE id = 2');
	$withColumns = proformaReceipts($kasse);
	checkZReport((int)$withColumns['count'] === 5 && (float)$withColumns['totalPrice'] === 62.0,
		'Read the counter when the hand-added columns hold NULL');
	saveLastReport(array('proformaReceiptsArray' => $withColumns));
	checkZReport(firstValue('SELECT count FROM proforma WHERE id = 2') === '0', 'Reset the counter on that table too');

	// A proforma receipt printed after the Z-report read the counter but before it was saved belongs to the next report.
	pg_query($connection, 'UPDATE proforma SET price = 80, count = 2 WHERE id = 2');
	$read = proformaReceipts($kasse);
	pg_query($connection, 'UPDATE proforma SET price = price + 45.5, count = count + 1 WHERE id = 2');
	saveLastReport(array('proformaReceiptsArray' => $read));
	checkZReport(firstValue("SELECT count FROM report WHERE type = 'Proforma receipt' ORDER BY id DESC LIMIT 1") === '2',
		'The Z-report saves the proforma count it read');
	checkZReport((float)firstValue('SELECT price FROM proforma WHERE id = 2') === 45.5 && firstValue('SELECT count FROM proforma WHERE id = 2') === '1',
		'A proforma receipt printed while the Z-report was saved stays on the counter');

	// Without a read in this request (no proforma numbers in the report) the counter is left alone.
	saveLastReport(array('receiptArray' => array('count' => 0, 'totalPrice' => 0)));
	checkZReport((float)firstValue('SELECT price FROM proforma WHERE id = 2') === 45.5 && firstValue('SELECT count FROM proforma WHERE id = 2') === '1',
		'Saving a report without reading the counter leaves it unchanged');

	// setupReport() for a Z-report: lock the register's row, read, save, commit (the same statements as reportSetup.php).
	$lockSql = "select id from proforma where id = '" . (int)$kasse . "' for update";
	pg_query($connection, 'UPDATE proforma SET price = 80, count = 2 WHERE id = 2');
	$other = pg_connect(implode(' ', $parts), PGSQL_CONNECT_FORCE_NEW);
	pg_query($other, "SET search_path TO $schema");
	transaktion('begin');
	pg_query($connection, $lockSql);
	// A second Z-report for the same register cannot take the row while the first one holds it.
	try {
		$second = pg_query($other, "$lockSql nowait");
	} catch (ErrorException $e) {
		$second = false;
	}
	checkZReport($second === false && strpos(pg_last_error($other), 'lock') !== false,
		'A second Z-report for the register waits for the first one');
	// The real proformaCount() writer (helperMethods/helperFunc.php) in its own PHP process prints a receipt now.
	$writer = proc_open(array(PHP_BINARY, __FILE__, 'writer', $schema), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
	usleep(700000);
	$status = proc_get_status($writer);
	checkZReport($status['running'], 'A proforma print waits while the Z-report holds the register row');
	$read = proformaReceipts($kasse);
	saveLastReport(array('proformaReceiptsArray' => $read));
	transaktion('commit');
	$writerOut = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	$writerExit = proc_close($writer);
	checkZReport($writerExit === 0, 'The proforma print finishes after the Z-report: ' . trim($writerOut));
	checkZReport(firstValue("SELECT count FROM report WHERE type = 'Proforma receipt' ORDER BY id DESC LIMIT 1") === '2',
		'The locked Z-report saves the numbers it read');
	checkZReport(firstValue('SELECT count FROM proforma WHERE id = 2') === '1' && (float)firstValue('SELECT price FROM proforma WHERE id = 2') === 45.0,
		'Only the receipt printed during the Z-report is left, the reported numbers do not come back');
	pg_query($other, 'BEGIN');
	pg_query($other, $lockSql);
	checkZReport(pg_fetch_result(pg_query($other, 'SELECT count FROM proforma WHERE id = 2'), 0, 0) === '1',
		'The second Z-report then reads only what was printed after the first one');
	pg_query($other, 'COMMIT');
	pg_close($other);
} finally {
	pg_query($connection, "DROP SCHEMA IF EXISTS $schema CASCADE");
	pg_close($connection);
}
