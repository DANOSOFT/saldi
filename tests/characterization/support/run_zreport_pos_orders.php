<?php
// --- tests/characterization/support/run_zreport_pos_orders.php ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261029 CL/MMR SST-896 Child-process runner for ZReportPosOrdersOnlyTest: the X/Z-report counts only register
//                 sales; an ordinary invoice that got the register number in felt_5 stays out of turnover, VAT,
//                 payments and item groups, unless sets are enabled.
//                 Runs the real report functions against an isolated schema in the database named by the
//                 SALDI_ZREPORT_DSN environment variable (set by the test). Prints one "PASS: <label>" per check,
//                 "NOTE: ..." for the known legacy warning, and "FAIL: <label>" plus exit 1 on the first failure.

error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$dsn = getenv('SALDI_ZREPORT_DSN');
if (!extension_loaded('pgsql') || !$dsn) {
	fwrite(STDERR, "SALDI_ZREPORT_DSN and the pgsql extension are required\n");
	exit(2);
}
$connection = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
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
		echo "FAIL: $message\n";
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}
/** @return string[] Item group descriptions on the report for one register. */
function itemGroups($kasse, $date) {
	$groups = array();
	foreach (productGroupDescription($kasse, $date) as $group) {
		if (isset($group['description'])) $groups[] = $group['description'];
	}
	sort($groups);
	return $groups;
}
$root = dirname(__DIR__, 3);
require_once($root . '/debitor/pos_ordre_includes/helperMethods/helperFunc.php');
require_once($root . '/debitor/pos_ordre_includes/helperMethods/helperFuncII.php');
require_once($root . '/debitor/pos_ordre_includes/report/reportSubtract.php');
require_once($root . '/debitor/pos_ordre_includes/report/rapportData.php');
$regnaar = 10;
$today = date('Y-m-d');
$exit = 0;
try {
	pg_query($connection, "CREATE SCHEMA $schema");
	pg_query($connection, "SET search_path TO $schema");
	pg_query($connection, "CREATE TABLE grupper (art text, kodenr text, fiscal_year int, box12 text, beskrivelse text)");
	pg_query($connection, "INSERT INTO grupper VALUES ('POS', '2', 10, '', NULL), ('VG', '4', 10, NULL, 'Tilbehør'), ('VG', '12', 10, NULL, 'Udlejning af lokaler')");
	pg_query($connection, "CREATE TABLE report (id serial, date date, type text, description text, count int, total float, report_number int)");
	pg_query($connection, "CREATE TABLE ordrer (id int PRIMARY KEY, felt_5 text, fakturadate date, art text, felt_1 text, betalt numeric)");
	pg_query($connection, "CREATE TABLE ordrelinjer (ordre_id int, vare_id int, varenr text, pris numeric, antal numeric, rabat numeric, momssats numeric, vat_price numeric)");
	pg_query($connection, "CREATE TABLE varer (id int, varenr text, gruppe text)");
	pg_query($connection, "CREATE TABLE batch_salg (vare_id int, ordre_id int, fakturadate date, antal numeric, pris numeric)");
	pg_query($connection, "INSERT INTO varer VALUES (10, 'div10', '4'), (20, 'lok', '12')");
	// Register 2 today: a POS card sale of 249 incl. 25 % VAT (item group Tilbehør) and an ordinary rent invoice of
	// 12.000 without VAT (item group Udlejning af lokaler) that got felt_5 = 2 from its department, as invoice 36 at
	// Phone 2 Fix (pos_82) did. Plus a POS sale on register 1.
	pg_query($connection, "INSERT INTO ordrer VALUES
		(1, '2', '$today', 'PO', 'Visa', 249),
		(2, '2', '$today', 'DO', '', 0),
		(3, '1', '$today', 'PO', 'Visa', 100)");
	pg_query($connection, "INSERT INTO ordrelinjer VALUES
		(1, 10, 'div10', 199.2, 1, 0, 25, 249),
		(2, 20, 'lok', 12000, 1, 0, 0, 12000),
		(3, 10, 'div10', 80, 1, 0, 25, 100)");
	pg_query($connection, "INSERT INTO batch_salg VALUES (10, 1, '$today', 1, 199.2), (20, 2, '$today', 1, 12000), (10, 3, '$today', 1, 80)");

	checkZReport(makeOrderIdArray(2, $today) === array('1'), 'Register 2 report covers only its POS sale, not the invoice');
	$turnover = calculateTurnover(2, $today);
	checkZReport(abs($turnover['turnoverWithVat'] - 249) < 0.001, 'Turnover with VAT is the POS sale only (249, not 12.249)');
	checkZReport(abs($turnover['turnoverWithoutVat'] - 199.2) < 0.001, 'Turnover without VAT is the POS sale only');

	// vatPayments() adds to a 'price' key that does not exist yet (legacy, not changed by SST-896). Only that exact
	// warning from that line is allowed, and it is reported; any other warning still fails.
	$source = file_get_contents($root . '/debitor/pos_ordre_includes/report/rapportData.php');
	$priceLine = substr_count(substr($source, 0, strpos($source, "\$vatArray[\$vat]['price'] += \$price;")), "\n") + 1;
	$knownWarnings = 0;
	set_error_handler(function($severity, $message, $file, $line) use ($priceLine, &$knownWarnings) {
		if ($message === 'Undefined array key "price"' && basename($file) === 'rapportData.php' && $line === $priceLine) {
			$knownWarnings++;
			return true;
		}
		throw new ErrorException($message, 0, $severity, $file, $line);
	});
	$vat = vatPayments(2, $today);
	restore_error_handler();
	echo "NOTE: vatPayments() raised the known legacy warning 'Undefined array key \"price\"' at rapportData.php:$priceLine $knownWarnings time(s)\n";
	checkZReport(!isset($vat['0']) && !isset($vat[0]), 'No 0 % VAT line from the invoice');

	$payments = paymentMethods(2, $today);
	checkZReport(abs($payments['sum']['price'] - 249) < 0.001 && isset($payments['Visa']), 'Payment total is the POS sale only');

	// setCountAndTotal() reads count/sellPrice keys before setting them (legacy); allow only those two warnings here.
	set_error_handler(function($severity, $message, $file, $line) {
		if (in_array($message, array('Undefined array key "count"', 'Undefined array key "sellPrice"'), true) && basename($file) === 'rapportData.php') return true;
		throw new ErrorException($message, 0, $severity, $file, $line);
	});
	$groups2 = itemGroups(2, $today);
	$groups1 = itemGroups(1, $today);
	restore_error_handler();
	checkZReport($groups2 === array('Tilbehør'), 'Item groups on register 2 have no line for the invoice-only group');
	checkZReport($groups1 === array('Tilbehør'), 'Register 1 item groups are unaffected');
	checkZReport(makeOrderIdArray(1, $today) === array('3'), 'Register 1 is unaffected');

	// Every value posbogfor() and findBoxSale() read as "sets off" must leave the invoice out too.
	foreach (array("''", "'0'", "' 0 '", "'  '", 'NULL') as $off) {
		pg_query($connection, "UPDATE grupper SET box12 = $off WHERE art = 'POS'");
		$turnover = calculateTurnover(2, $today);
		checkZReport(makeOrderIdArray(2, $today) === array('1') && abs($turnover['turnoverWithVat'] - 249) < 0.001,
			"Sets value $off counts as off: invoice left out, turnover 249");
	}
	pg_query($connection, "DELETE FROM grupper WHERE art = 'POS'");
	checkZReport(makeOrderIdArray(2, $today) === array('1'), 'Missing POS settings row counts as sets off');
	pg_query($connection, "INSERT INTO grupper VALUES ('POS', '2', 10, '', NULL)");
	// The setting is read for the given fiscal year only.
	pg_query($connection, "INSERT INTO grupper VALUES ('POS', '2', 9, 'on', NULL)");
	checkZReport(makeOrderIdArray(2, $today) === array('1'), 'Sets enabled in another fiscal year do not count');
	pg_query($connection, "DELETE FROM grupper WHERE art = 'POS' AND fiscal_year = 9");

	// With sets enabled debtor orders are handed in at the register, so they count, as posbogfor() posts them.
	pg_query($connection, "UPDATE grupper SET box12 = 'on' WHERE art = 'POS'");
	$ids = makeOrderIdArray(2, $today);
	sort($ids);
	checkZReport($ids === array('1', '2'), 'With sets enabled the debtor order is included');
	$turnover = calculateTurnover(2, $today);
	checkZReport(abs($turnover['turnoverWithVat'] - 12249) < 0.001, 'With sets enabled turnover includes it');
	set_error_handler(function($severity, $message, $file, $line) {
		if (in_array($message, array('Undefined array key "count"', 'Undefined array key "sellPrice"'), true) && basename($file) === 'rapportData.php') return true;
		throw new ErrorException($message, 0, $severity, $file, $line);
	});
	$groupsOn = itemGroups(2, $today);
	restore_error_handler();
	checkZReport($groupsOn === array('Tilbehør', 'Udlejning af lokaler'), 'With sets enabled the invoice item group is on the report');
	pg_query($connection, "UPDATE grupper SET box12 = '' WHERE art = 'POS'");

	// A credit note to a debtor (art DK) is not a register sale either.
	pg_query($connection, "INSERT INTO ordrer VALUES (4, '2', '$today', 'DK', '', 0)");
	pg_query($connection, "INSERT INTO ordrelinjer VALUES (4, 10, 'div10', -500, 1, 0, 25, -625)");
	checkZReport(makeOrderIdArray(2, $today) === array('1'), 'A debtor credit note is left out without sets');

	// The register number reaches SQL int-cast.
	checkZReport(makeOrderIdArray("2' or '1'='1", $today) === array('1'), 'The register number is int-cast');
} catch (Throwable $e) {
	if (strpos($e->getMessage(), 'FAIL') === false) echo "FAIL: " . get_class($e) . ": " . $e->getMessage() . "\n";
	$exit = 1;
} finally {
	pg_query($connection, "DROP SCHEMA IF EXISTS $schema CASCADE");
	pg_close($connection);
}
exit($exit);
