<?php
// --- tests/test_cash_count_transaction.php ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260908 CDX/LH Exercise report account scope and concurrent sales on an isolated PostgreSQL schema.
// 20261019 CL/LH MB-70: Ordinary invoices stay out of the count unless sets are enabled; no nameless payment bucket.
// 20261105 CL/LH Stub get_settings_value(), which findBoxSale() calls for a foreign currency's till account (decision 22).
//                Without it the EUR count died and 13 of the 14 checks never ran.
//                A till account stored in settings for the till is checked to win over the box4 list.
// 20261117 CL/ASR POS-F2: euro cash on a kroner POS order is counted as euro takings, not as a card in the kroner count.
//                 transaktioner has its id, log and till columns and pos_betalinger exists from the start, as in a real database.
//                 With immediate posting findBoxSale() now reads them for a foreign currency.
// 20261117 CL/ASR The orders table has its payment columns and pos_betalinger from the start, as findBoxSale() reads status 3 sales also with box9.
//                 With box9 an unposted account sale is checked to be counted: its cash in the till, its Konto part on account.
// 20261118 CL/ASR Both schemas merged: the orders table has its payment columns, transaktioner its id, log and till columns, and pos_betalinger exists from the start.
//                 Three checks: euro cash on a box9 account sale, euro cash on a sale from before the fiscal year, and no count for a sale dated in a deleted fiscal year or in none.
// 20261119 CL/ASR Header only: the 20261117 POS-F2 entry's continuation is split into whole sentences.
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
function findtekst($id, $language) { return (string)(explode('|', (string)$id, 2)[1] ?? ''); }
/** @return void */
function alert($message) { throw new RuntimeException($message); }
/** @return mixed The value stored in $storedSettings for the group, name and till, else $default. */
function get_settings_value($name, $group, $default, $user = null, $kasse = null) {
	return $GLOBALS['storedSettings']["$group/$name/$kasse"] ?? $default;
}
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
$storedSettings = array();
try {
	pg_query($connection, "CREATE SCHEMA $schema");
	foreach (array($connection, $writer) as $conn) {
		pg_query($conn, "SET search_path TO $schema");
	}
	pg_query($connection, "CREATE TABLE grupper (art text, kodenr text, fiscal_year int, box1 text, box2 text, box4 text, box5 text, box6 text, box9 text, box12 text DEFAULT '', box3 text DEFAULT '', box10 text DEFAULT '', beskrivelse text DEFAULT '')");
	pg_query($connection, "INSERT INTO grupper VALUES
		('RA', '9', 9, '01', '" . date('Y') . "', '', '', '', ''),
		('POS', '1', 9, '', E'5800\\t5810', '1', 'Dankort', '5830', 'on'),
		('POS', '2', 9, '100', '', '', '', '5840', ''),
		('VK', '1', 9, 'EUR', '', E'5850\\t5860', '', '', '')");
	pg_query($connection, "CREATE TABLE kontoplan (id int, regnskabsaar int, kontonr int, kontotype text, primo numeric)");
	pg_query($connection, "INSERT INTO kontoplan VALUES (1,9,5830,'S',0)");
	pg_query($connection, "CREATE TABLE ordrer (id int PRIMARY KEY, felt_5 text, status int, valuta text, report_number int DEFAULT 0, art text DEFAULT 'PO',
		fakturadate date DEFAULT CURRENT_DATE, sum numeric DEFAULT 0, moms numeric DEFAULT 0, felt_1 text, felt_3 text)");
	pg_query($connection, "CREATE TABLE transaktioner (ordre_id int, transdate date, kontonr int, debet numeric, kredit numeric, id serial, logdate date DEFAULT CURRENT_DATE, logtime text DEFAULT '12:00', kasse_nr int DEFAULT 0, kladde_id int DEFAULT 0, beskrivelse text DEFAULT '')");
	pg_query($connection, 'CREATE TABLE pos_betalinger (ordre_id int, betalingstype text, amount numeric, valuta text)');
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
	// Decision 22: the till account stored in settings VK/till_account_EUR for till 2 is read before the box4 list.
	$storedSettings['VK/till_account_EUR/2'] = '5830';
	currentCashSale($storedIds, 'EUR');
	$storedSettings = array();
	checkTransaction($storedIds === array(6), 'A till account stored in settings for the till wins over the box4 list');
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
	// box9 posts only sales without a customer at once; an account sale waits at status 3 for the day close.
	pg_query($connection, "INSERT INTO ordrer (id,felt_5,status,valuta,sum,moms,felt_1) VALUES (10,'2',3,'DKK',200,50,'Kontant')");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (10,'Konto',100,'DKK'),(10,'Kontant',150,'DKK')");
	$withAccountSale = currentCashSale($ids);
	checkTransaction(in_array(10, $ids, true) && (float)$withAccountSale[1] === (float)$next[1] + 150.0 && (float)$withAccountSale[7] === 100.0,
		'With box9 an unposted account sale is counted: its cash in the till, its Konto part on account');
	// Deferred posting includes both unposted POS sales and previously posted debtor orders.
	pg_query($connection, "UPDATE grupper SET box9 = '' WHERE art = 'POS' AND kodenr = '1'");
	pg_query($connection, 'TRUNCATE ordrer, transaktioner, pos_betalinger');
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
	// MB-70: an ordinary invoice booked with a transaction on the register is not a register sale.
	$posOnly = currentCashSale($posOnlyIds);
	pg_query($connection, "INSERT INTO ordrer (id,felt_5,status,valuta,sum,art) VALUES (14,'2',4,'DKK',500,'DO')");
	pg_query($connection, "INSERT INTO transaktioner (ordre_id,transdate,kontonr,debet,kredit,kasse_nr,logtime,beskrivelse) VALUES (14,CURRENT_DATE,1000,500,0,2,'13:00','Invoice')");
	$withInvoice = currentCashSale($ids);
	checkTransaction($ids === $posOnlyIds && $withInvoice === $posOnly,
		'An ordinary invoice with a transaction on the register is left out of the count');
	// With sets enabled debtor orders count, as posbogfor() posts them; a missing payment type gets a name.
	pg_query($connection, "UPDATE grupper SET box12 = 'on' WHERE art = 'POS' AND kodenr = '2'");
	$withSets = currentCashSale($ids);
	$bucket = array_search('Ukendt betalingstype', explode(chr(9), $withSets[5]), true);
	checkTransaction(in_array(14, $ids, true) && $bucket !== false && (float)explode(chr(9), $withSets[6])[$bucket] === 500.0,
		'With sets enabled a debtor order without payment type is counted in a named bucket');
	// POS-F2: a POS order is in kroner. Cash paid in euro is a 'Kontant' payment row in EUR worth 149.12 kr.;
	// sale 21 is 100.12 kr. paid with 20 EUR (49 kr. back), sale 22 is 50 kr. in kroner.
	pg_query($connection, "UPDATE grupper SET box12 = '' WHERE art = 'POS' AND kodenr = '2'");
	pg_query($connection, 'TRUNCATE ordrer, transaktioner, pos_betalinger');
	pg_query($connection, "INSERT INTO ordrer (id,felt_5,status,valuta,sum,moms,felt_1) VALUES (21,'2',3,'DKK',80.09,20.03,'Kontant'),(22,'2',3,'DKK',50,0,'Kontant')");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (21,'Kontant',149.12,'EUR'),(22,'Kontant',50,'DKK')");
	pg_query($connection, "INSERT INTO transaktioner (ordre_id,transdate,kontonr,debet,kredit,kasse_nr,logtime,beskrivelse) VALUES (0,CURRENT_DATE,0,0,0,2,'00:00','Kasseoptaelling')");
	$noAssistant = currentCashSale($ids);
	checkTransaction(abs($noAssistant[1] - 1) < 0.001 && $noAssistant[5] === "Dankort\tKontant" && $noAssistant[6] === "0\t149.12",
		'Without the count assistant euro cash stays a card in the kroner count');
	// The count assistant (POS 2 box2) counts euro in the till (VK box4 '1'), on till 2's euro account 5860.
	pg_query($connection, "UPDATE grupper SET box2 = 'on' WHERE art = 'POS' AND kodenr = '2'");
	pg_query($connection, "UPDATE grupper SET box4 = '1' WHERE art = 'VK'");
	$storedSettings['VK/till_account_EUR/2'] = '5860';
	$kroner = currentCashSale($ids);
	$euro = currentCashSale($foreignIds, 'EUR');
	checkTransaction(abs($kroner[1] - 1) < 0.001 && $kroner[5] === 'Dankort' && abs($euro[1] - 149.12) < 0.001 && $foreignIds === array(21),
		'Deferred posting counts euro cash on a kroner order as euro takings, not as a card');
	// Immediate posting: each sale posts its whole cash amount on the kroner till account 5810. Sale 24 was sold
	// before the last count of till 2, which moved its euro to 5860 (float now, not takings); sale 23 came after it.
	pg_query($connection, "UPDATE grupper SET box9 = 'on' WHERE art = 'POS' AND kodenr = '1'");
	pg_query($connection, 'TRUNCATE ordrer, transaktioner, pos_betalinger');
	pg_query($connection, "INSERT INTO ordrer (id,felt_5,status,valuta,sum,moms) VALUES (23,'2',4,'DKK',80.09,20.03),(24,'2',4,'DKK',80.09,20.03)");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (23,'Kontant',149.12,'EUR'),(24,'Kontant',149.12,'EUR')");
	pg_query($connection, "INSERT INTO transaktioner (ordre_id,transdate,kontonr,debet,kredit,kasse_nr,beskrivelse) VALUES
		(24,CURRENT_DATE,5810,100.12,0,2,'Bon'),
		(0,CURRENT_DATE,5860,149.12,0,2,'Kassesalg EUR, kasse 2'), (0,CURRENT_DATE,5810,0,149.12,2,'Kassesalg EUR, kasse 2'),
		(0,CURRENT_DATE,0,0,0,2,'Kasseoptaelling,kasse 2'),
		(23,CURRENT_DATE,5810,100.12,0,2,'Bon')");
	$kroner = currentCashSale($ids);
	$euro = currentCashSale($foreignIds, 'EUR');
	checkTransaction(abs($kroner[1] - (-98)) < 0.001 && abs($euro[1] - 149.12) < 0.001,
		'Immediate posting moves euro cash taken since the last count from the kroner to the euro takings');
	// With box9 an account sale waits at status 3 for the day close. Sale 31 is 200.12 kr. paid Konto 100 and 20 EUR
	// (149.12 kr.), 49 kr. back in kroner: the euro is euro takings, not a card, and the kroner till gives the change.
	pg_query($connection, 'TRUNCATE ordrer, transaktioner, pos_betalinger');
	pg_query($connection, "INSERT INTO ordrer (id,felt_5,status,valuta,sum,moms,felt_1) VALUES (31,'2',3,'DKK',160.10,40.02,'Kontant')");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (31,'Konto',100,'DKK'),(31,'Kontant',149.12,'EUR')");
	$kroner = currentCashSale($ids);
	$euro = currentCashSale($foreignIds, 'EUR');
	checkTransaction(abs($kroner[1] - (-49)) < 0.001 && $kroner[5] === 'Dankort' && (float)$kroner[7] === 100.0 && $ids === array(31)
		&& abs($euro[1] - 149.12) < 0.001 && $foreignIds === array(31),
		'With box9 euro cash on an account sale the close posts is euro takings, not a card, and its Konto part is on account');
	// Last year (8) is a live fiscal year, the year before (7) is deleted (box10 set by deleteFiscalYear.php), and no year
	// covers five years ago. Sale 32 from last year was paid with 20 EUR (49 kr. back); sales 33 and 34 were paid in kroner.
	$year = (int)date('Y');
	pg_query($connection, "INSERT INTO grupper (art,kodenr,fiscal_year,box1,box2,box3,box4,box5,box10) VALUES
		('RA','8',8,'01','" . ($year - 1) . "','12','" . ($year - 1) . "','on',''), ('RA','7',7,'01','" . ($year - 2) . "','12','" . ($year - 2) . "','','1700000000')");
	pg_query($connection, 'TRUNCATE ordrer, transaktioner, pos_betalinger');
	pg_query($connection, "INSERT INTO ordrer (id,felt_5,status,valuta,sum,moms,felt_1,fakturadate) VALUES
		(32,'2',3,'DKK',80.09,20.03,'Kontant','" . ($year - 1) . "-12-31'), (33,'2',3,'DKK',40,10,'Kontant','" . ($year - 2) . "-06-30'),
		(34,'2',3,'DKK',24,6,'Kontant','" . ($year - 5) . "-12-31')");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (32,'Kontant',149.12,'EUR'),(33,'Kontant',50,'DKK'),(34,'Kontant',30,'DKK')");
	pg_query($connection, "INSERT INTO transaktioner (ordre_id,transdate,kontonr,debet,kredit,kasse_nr,logtime,beskrivelse) VALUES (0,CURRENT_DATE,0,0,0,2,'00:00','Kasseoptaelling')");
	$straksKroner = currentCashSale($straksIds);
	pg_query($connection, "UPDATE grupper SET box9 = '' WHERE art = 'POS' AND kodenr = '1'");
	$kroner = currentCashSale($ids);
	$euro = currentCashSale($foreignIds, 'EUR');
	checkTransaction($kroner[5] === 'Dankort' && abs($euro[1] - 149.12) < 0.001 && $foreignIds === array(32),
		'A sale from before the fiscal year paid in euro is counted as euro takings, not as a card');
	checkTransaction($ids === array(32) && abs($kroner[1] - (-49)) < 0.001 && $straksIds === array(32) && abs($straksKroner[1] - (-49)) < 0.001,
		'A sale dated in a deleted fiscal year or in no fiscal year is not counted, with or without box9');
} finally {
	if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) {
		pg_query($connection, 'ROLLBACK');
	}
	pg_query($connection, "DROP SCHEMA IF EXISTS $schema CASCADE");
	pg_close($writer);
	pg_close($connection);
}
