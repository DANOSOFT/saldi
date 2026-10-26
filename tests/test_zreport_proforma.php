<?php
// --- tests/test_zreport_proforma.php ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261026 CL/MMR SST-897 Exercise the X/Z-report proforma counter and the saved report rows on an isolated PostgreSQL schema.
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
$schema = 'zreport_proforma_test_' . bin2hex(random_bytes(6));

/** @return PgSql\Result */
function db_select($sql, $location) { return pg_query($GLOBALS['connection'], $sql); }
/** @return array|false */
function db_fetch_array($query) { return pg_fetch_assoc($query); }
/** @return PgSql\Result */
function db_modify($sql, $location) { return pg_query($GLOBALS['connection'], $sql); }
/** @return string */
function db_escape_string($value) { return pg_escape_string($GLOBALS['connection'], (string)$value); }
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
} finally {
	pg_query($connection, "DROP SCHEMA IF EXISTS $schema CASCADE");
	pg_close($connection);
}
