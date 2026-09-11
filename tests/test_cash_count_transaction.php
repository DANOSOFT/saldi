<?php
// --- tests/test_cash_count_transaction.php ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260908 CDX/LH Exercise report account scope and concurrent sales on an isolated PostgreSQL schema.
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
$dsn = implode(' ', $parts);
$connection = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
$writer = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
$schema = 'cash_count_test_' . bin2hex(random_bytes(6));

/** @return PgSql\Result */
function db_select($sql, $location) { return pg_query($GLOBALS['connection'], $sql); }
/** @return array|false */
function db_fetch_array($query) { return pg_fetch_assoc($query); }
/** @return PgSql\Result */
function db_modify($sql, $location) { return pg_query($GLOBALS['connection'], $sql); }
/** @return string */
function db_escape_string($value) { return pg_escape_string($GLOBALS['connection'], (string)$value); }
/** @return void */
function transaktion($sql) { pg_query($GLOBALS['connection'], $sql); }
/** @return string */
function findtekst($id, $language) { return ''; }
/** @return void */
function alert($message) { throw new RuntimeException($message); }
/** @return void */
function checkTransaction($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}
/** @return array */
function currentCashSale(&$ids = null, $currency = 'DKK') {
	ob_start();
	try {
		return findBoxSale(2, 0, $currency, $ids);
	} finally {
		ob_end_clean();
	}
}
require_once(__DIR__ . '/../debitor/pos_ordre_includes/boxCountMethods/cashCountSnapshot.php');
require_once(__DIR__ . '/../debitor/pos_ordre_includes/boxCountMethods/findBoxSale.php');
require_once(__DIR__ . '/../debitor/pos_ordre_includes/boxCountMethods/assignCashReport.php');
// Load only the posting function, avoiding the page's session and rendering bootstrap.
$source = file_get_contents(__DIR__ . '/../debitor/pos_ordre.php');
$start = strpos($source, 'function posbogfor(');
$end = strpos($source, 'function kasseoptalling(', $start);
eval(str_replace('__DIR__', var_export(realpath(__DIR__ . '/../debitor'), true), substr($source, $start, $end - $start)));
/** @return string */
function nav_popup_query($get, $post) { return ''; }
$_GET = $_POST = array();
$db_type = 'postgresql';
$reportNumber = 0;
$baseCurrency = 'DKK';
$regnaar = 9;
$sprog_id = 1;
$db = $schema;
try {
	pg_query($connection, "CREATE SCHEMA $schema");
	foreach (array($connection, $writer) as $conn) {
		pg_query($conn, "SET search_path TO $schema");
	}
	pg_query($connection, "CREATE TABLE grupper (art text, kodenr text, fiscal_year int, box1 text, box2 text, box4 text, box5 text, box6 text, box9 text)");
	pg_query($connection, "INSERT INTO grupper VALUES
		('RA', '9', 9, '01', '" . date('Y') . "', '', '', '', ''),
		('POS', '1', 9, '', E'5800\\t5810', '1', 'Dankort', '5830', 'on'),
		('POS', '2', 9, '100', '', '', '', '5840', ''),
		('VK', '1', 9, 'EUR', '', E'5850\\t5860', '', '', '')");
	pg_query($connection, "CREATE TABLE kontoplan (id int, regnskabsaar int, kontonr int, kontotype text, primo numeric)");
	pg_query($connection, "INSERT INTO kontoplan VALUES (1,9,5830,'S',0)");
	pg_query($connection, "CREATE TABLE ordrer (id int PRIMARY KEY, felt_5 text, status int, valuta text, report_number int DEFAULT 0)");
	pg_query($connection, "CREATE TABLE transaktioner (ordre_id int, transdate date, kontonr int, debet numeric, kredit numeric)");
	pg_query($connection, "INSERT INTO ordrer (id,felt_5,status,valuta) VALUES (1,'2',4,'DKK'),(2,'2',4,'DKK'),(3,'2',4,'DKK'),(4,'1',4,'DKK'),(5,'2',4,'EUR'),(6,'2',4,'EUR'),(7,'2',4,'DKK')");
	pg_query($connection, "INSERT INTO transaktioner VALUES
		(1,CURRENT_DATE,5810,100,0), (2,CURRENT_DATE,5830,200,0),
		(2,CURRENT_DATE,5830,50,0), (3,CURRENT_DATE,5820,300,0),
		(4,CURRENT_DATE,5800,400,0), (5,CURRENT_DATE,5860,50,0),
		(6,CURRENT_DATE,5830,60,0), (7,CURRENT_DATE - 1,5810,70,0)");
	$before = currentCashSale($ids);
	sort($ids);
	checkTransaction($ids === array(1, 2), 'Select cash/card orders once; exclude bank-only, other registers/currencies and prior dates');
	currentCashSale($foreignIds, 'EUR');
	checkTransaction($foreignIds === array(5), 'Foreign currency selects its cash account without base-currency card accounts');
	transaktion('begin');
	assignCashReport($ids, 5515);
	assignCashReport(array(1), 5516);
	$assigned = pg_fetch_all(pg_query($connection, 'SELECT id FROM ordrer WHERE report_number = 5515 ORDER BY id'));
	checkTransaction(array_column($assigned, 'id') === array('1', '2'), 'Assign only included orders and preserve earlier assignments');
	transaktion('rollback');
	$signature = cashCountSignature(array('DKK' => $before));
	// A sale commits after the form was calculated, before the posting transaction starts.
	pg_query($writer, "INSERT INTO ordrer (id,felt_5,status,valuta) VALUES (8,'2',4,'DKK')");
	pg_query($writer, 'INSERT INTO transaktioner VALUES (8,CURRENT_DATE,5830,5599.50,0)');
	ob_start();
	$accepted = posbogfor(2, date('Y') . '-01-01', 0, $signature, true);
	ob_end_clean();
	checkTransaction(!$accepted && pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE, 'Reject the newly committed sale and roll back before posting');
	$signature = cashCountSignature(array('DKK' => currentCashSale($ids)));
	ob_start();
	$accepted = beginCashCountPosting(2, 'DKK', $signature, 'postgresql', true);
	ob_end_clean();
	checkTransaction($accepted, 'Accept recalculated totals in the posting transaction');
	// Another connection now commits a sale between validation and the eligible-order read.
	pg_query($writer, "INSERT INTO ordrer (id,felt_5,status,valuta) VALUES (9,'2',4,'DKK')");
	pg_query($writer, 'INSERT INTO transaktioner VALUES (9,CURRENT_DATE,5830,42,0)');
	$within = currentCashSale($ids);
	checkTransaction(!in_array(9, $ids, true) && cashCountSignature(array('DKK' => $within)) === $signature,
		'Eligible orders and balances retain the validated snapshot despite a concurrent commit');
	assignCashReport($ids, 5517);
	checkTransaction(pg_num_rows(pg_query($connection, 'SELECT report_number FROM ordrer WHERE id = 9')) === 0,
		'The concurrent sale stays outside the posting snapshot');
	transaktion('commit');
	checkTransaction(pg_fetch_result(pg_query($connection, 'SELECT report_number FROM ordrer WHERE id = 9'), 0, 0) === '0',
		'The concurrent sale remains unassigned for the next report');
	$next = currentCashSale($ids);
	checkTransaction(in_array(9, $ids, true) && cashCountSignature(array('DKK' => $next)) !== $signature,
		'The next calculation sees the later sale');
	// Deferred posting includes both unposted POS sales and previously posted debtor orders.
	pg_query($connection, "UPDATE grupper SET box9 = '' WHERE art = 'POS' AND kodenr = '1'");
	pg_query($connection, 'TRUNCATE ordrer, transaktioner');
	pg_query($connection, "ALTER TABLE ordrer ADD fakturadate date DEFAULT CURRENT_DATE, ADD sum numeric DEFAULT 0, ADD moms numeric DEFAULT 0, ADD felt_1 text, ADD felt_3 text");
	pg_query($connection, "ALTER TABLE transaktioner ADD id serial, ADD logdate date DEFAULT CURRENT_DATE, ADD logtime text DEFAULT '12:00', ADD kasse_nr int DEFAULT 0, ADD kladde_id int DEFAULT 0, ADD beskrivelse text DEFAULT ''");
	pg_query($connection, 'CREATE TABLE pos_betalinger (ordre_id int, betalingstype text, amount numeric, valuta text)');
	pg_query($connection, 'CREATE TABLE ordrelinjer (ordre_id int, momssats numeric)');
	pg_query($connection, "INSERT INTO ordrer (id,felt_5,status,valuta,sum,felt_1) VALUES (11,'2',3,'DKK',100,'Kontant'),(12,'2',4,'DKK',200,'Dankort')");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (11,'Kontant',100,'DKK')");
	pg_query($connection, "INSERT INTO transaktioner (ordre_id,transdate,kontonr,debet,kredit,kasse_nr,logtime,beskrivelse) VALUES (0,CURRENT_DATE,0,0,0,2,'00:00','Kasseoptaelling'),(12,CURRENT_DATE,5830,200,0,0,'12:00','Sale')");
	$deferred = currentCashSale($ids);
	sort($ids);
	checkTransaction($ids === array(11, 12) && (float)$deferred[1] === 100.0 && (float)$deferred[6] === 200.0,
		'Deferred counts include unposted cash and already-posted card sales without payment rows');
	checkTransaction(pg_fetch_result(pg_query($connection, 'SELECT felt_3 IS NULL FROM ordrer WHERE id = 11'), 0, 0) === 't',
		'Calculating a count does not mutate unposted orders');
	$signature = cashCountSignature(array('DKK' => $deferred));
	ob_start();
	$accepted = beginCashCountPosting(2, 'DKK', $signature, 'postgresql', true);
	ob_end_clean();
	pg_query($writer, "INSERT INTO ordrer (id,felt_5,status,valuta,sum,felt_1) VALUES (13,'2',3,'DKK',42,'Kontant')");
	pg_query($writer, "INSERT INTO pos_betalinger VALUES (13,'Kontant',42,'DKK')");
	$within = currentCashSale($ids);
	checkTransaction($accepted && !in_array(13, $ids, true) && cashCountSignature(array('DKK' => $within)) === $signature,
		'Deferred posting also retains the validated snapshot after a concurrent sale');
	transaktion('rollback');
} finally {
	if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) {
		pg_query($connection, 'ROLLBACK');
	}
	pg_query($connection, "DROP SCHEMA IF EXISTS $schema CASCADE");
	pg_close($writer);
	pg_close($connection);
}
