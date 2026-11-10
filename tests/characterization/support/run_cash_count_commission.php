<?php
// --- tests/characterization/support/run_cash_count_commission.php ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261109 CL/SS SST-919 Child-process runner for CashCountCommissionSettlementTest.
//                The cash count commission settlement must post both sides, settle each sale once and build its own payment list.
//                It runs the real include against an isolated schema in the database named by SALDI_COMMISSION_DSN (set by the test).
//                It prints one "PASS: <label>" per check, and "FAIL: <label>" plus exit 1 on the first failure.

error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$dsn = getenv('SALDI_COMMISSION_DSN');
if (!extension_loaded('pgsql') || !$dsn) {
	fwrite(STDERR, "SALDI_COMMISSION_DSN and the pgsql extension are required\n");
	exit(2);
}
$connection = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
$schema = 'commission_test_' . bin2hex(random_bytes(6));

/** @return PgSql\Result */
function db_select($sql, $location) {
	if (!empty($GLOBALS['beforeLock']) && stripos($sql, 'for update') !== false) {
		$hook = $GLOBALS['beforeLock'];
		$GLOBALS['beforeLock'] = null;
		$hook();
	}
	return pg_query($GLOBALS['connection'], $sql);
}
/** @return array|false */
function db_fetch_array($query) { return pg_fetch_assoc($query); }
/** @return PgSql\Result */
function db_modify($sql, $location) { return pg_query($GLOBALS['connection'], $sql); }
/** @return string */
function db_escape_string($value) { return pg_escape_string($GLOBALS['connection'], (string)$value); }
/** @return string */
function findtekst($id, $language) { return (string)(explode('|', (string)$id, 2)[1] ?? $id); }
/** @return void */
function alert($message) { $GLOBALS['alerts'][] = $message; }
require_once(__DIR__ . '/../../../includes/std_func.php');
require_once(__DIR__ . '/../../../debitor/pos_ordre_includes/settleCommission/commissionDefaultFromDate.php');
$source = file_get_contents(__DIR__ . '/../../../finans/loppeafregning.php');
$start = strpos($source, 'function loppeafregningSalesQuery(');
eval(substr($source, $start, strpos($source, "\n}\n", $start) + 3 - $start));

/** @return void */
function check($condition, $message) {
	if (!$condition) {
		echo "FAIL: $message\n";
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}
/** @return string|null */
function value($sql) {
	$row = pg_fetch_row(pg_query($GLOBALS['connection'], $sql));
	return $row ? $row[0] : null;
}
/**
 * Runs the include in a function scope, as posbogfor() does.
 * @return void
 */
function settle($from, $to, $settleCommission, $createPayList, $pairs = array('kn', 'kb')) {
	$_POST = array('commissionFromDate' => $from, 'commissionToDate' => $to);
	$kasse = 1;
	$reportNumber = 77;
	$regnaar = 9;
	$dd = '2026-11-09';
	$logtime = '12:00:00';
	$ansat_id = 0;
	$afd = 0;
	$brugernavn = 'tester';
	$commissionAccountNew = '1110';
	$commissionAccountUsed = '1120';
	$customerCommissionAccountNew = '65020';
	$customerCommissionAccountUsed = '65030';
	if (!in_array('kn', $pairs, true)) {
		$commissionAccountNew = $customerCommissionAccountNew = '';
	}
	if (!in_array('kb', $pairs, true)) {
		$commissionAccountUsed = $customerCommissionAccountUsed = '';
	}
	include(__DIR__ . '/../../../debitor/pos_ordre_includes/settleCommission/moveToCustomerAccount.php');
}
/** @return string */
function sideEffects() {
	return value("SELECT (SELECT count(*) FROM transaktioner) || '/' || (SELECT count(*) FROM openpost) || '/' || (SELECT count(*) FROM betalinger) || '/' || (SELECT count(*) FROM betalingsliste)");
}
/** @return string */
function balance() { return value('SELECT coalesce(sum(coalesce(debet,0)) - sum(coalesce(kredit,0)),0)::numeric(15,2) FROM transaktioner'); }

$alerts = array();
$sprog_id = 1;
try {
	pg_query($connection, "CREATE SCHEMA $schema");
	pg_query($connection, "SET search_path TO $schema");
	pg_query($connection, "CREATE TABLE ordrer (id int PRIMARY KEY, art text, status int, felt_5 text, fakturadate date, settletime numeric(15,0) DEFAULT 0)");
	pg_query($connection, "CREATE TABLE ordrelinjer (id serial, ordre_id int, vare_id int, varenr text, antal numeric, pris numeric, kostpris numeric, rabat numeric DEFAULT 0, momssats numeric DEFAULT 25, momsfri text DEFAULT '')");
	pg_query($connection, "CREATE TABLE batch_salg (id serial, linje_id int, vare_id int, pris numeric, antal numeric, fakturadate date)");
	pg_query($connection, "CREATE TABLE varer (id int, varenr text, provision numeric, gruppe int)");
	pg_query($connection, "CREATE TABLE pos_betalinger (ordre_id int, betalingstype text)");
	pg_query($connection, "CREATE TABLE adresser (id int, kontonr text, art text, gruppe int, firmanavn text, bank_reg text, bank_konto text, land text, ean text)");
	pg_query($connection, "CREATE TABLE grupper (art text, kodenr text, fiscal_year int, box1 text, box2 text)");
	pg_query($connection, "CREATE TABLE kontoplan (kontonr int, regnskabsaar int, moms text)");
	pg_query($connection, "CREATE TABLE openpost (id serial, konto_id int, konto_nr text, faktnr text, amount numeric, beskrivelse text, udlignet int,
		transdate date, uxtid text, kladde_id int, refnr int, valuta text, valutakurs numeric, projekt text)");
	pg_query($connection, "CREATE TABLE transaktioner (id serial, bilag int, transdate date, beskrivelse text, kontonr int, faktura text, debet numeric,
		kredit numeric, kladde_id int, afd int, logdate date, logtime text, projekt text, ansat int, ordre_id int, kasse_nr int, report_number int, moms numeric)");
	pg_query($connection, "CREATE TABLE betalingsliste (id serial, listedate date, listenote text, oprettet_af text, hvem text, tidspkt text, bogfort text)");
	pg_query($connection, "CREATE TABLE betalinger (id serial, bet_type text, fra_kto text, egen_ref text, til_kto text, modt_navn text, kort_ref text,
		belob text, betalingsdato text, valuta text, bilag_id int, ordre_id int, liste_id int)");

	pg_query($connection, "INSERT INTO kontoplan VALUES (1110,9,''),(1120,9,'')");
	pg_query($connection, "INSERT INTO grupper (art,kodenr,fiscal_year,box2) VALUES ('DG','1',8,'9999'),('DG','1',9,'5600')");
	pg_query($connection, "INSERT INTO adresser VALUES (1,'1','S',0,'Loppen','1234','567','Denmark',''),
		(11,'1001','D',1,'Anna','1111','22','Denmark',''),(12,'1002','D',1,'Bo','3333','44','Denmark','')");
	pg_query($connection, "INSERT INTO varer VALUES (1,'kb1001001',50,1),(2,'kb1001002',50,1),(3,'kn1001001',50,2),(4,'kb1009999',50,1)");
	// An empty list that the old code reused for its payments and renamed.
	pg_query($connection, "INSERT INTO betalingsliste (listedate,listenote,oprettet_af,hvem,tidspkt,bogfort) VALUES ('2026-10-01','Egen liste','x','x','1','-')");
	pg_query($connection, "INSERT INTO ordrer VALUES
		(1,'PO',3,'1','2026-10-01',0), (2,'PO',3,'1','2026-10-02',0), (3,'PO',3,'2','2026-10-02',0),
		(4,'PO',3,'1','2026-10-03',0), (5,'PO',3,'1','2026-09-01',1700000000)");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (1,'Kontant'),(1,'Dankort'),(2,'Kontant'),(3,'Kontant'),(5,'Kontant')");
	pg_query($connection, "INSERT INTO ordrelinjer (ordre_id,vare_id,varenr,antal,pris,kostpris) VALUES
		(1,1,'kb1001001',1,100,60), (1,2,'kb1001002',2,50,25),
		(2,3,'kn1001001',1,40,30), (2,1,'kb1001001',1,20,10),
		(3,1,'kb1001001',1,100,60), (4,1,'kb1001001',1,100,60), (5,2,'kb1001002',1,100,70)");

	settle('01-01-2026', '31-10-2026', 'on', 'on');
	check(!$alerts, 'The settlement runs without a message');
	check(balance() === '0.00', 'Debit and credit balance after settling');
	check(value("SELECT debet FROM transaktioner WHERE kontonr = 65030") == 120 && value("SELECT debet FROM transaktioner WHERE kontonr = 65020") == 30,
		'Each commission account is debited with its own total (used 120, new 30)');
	check(value("SELECT sum(kredit) FROM transaktioner WHERE kontonr = 5600") == 150, "Customers are credited 150 on this year's collective account");
	$posts = pg_fetch_all(pg_query($connection, 'SELECT konto_nr, amount, beskrivelse FROM openpost ORDER BY konto_nr'));
	check(count($posts) === 2 && $posts[0]['amount'] == -100 && $posts[1]['amount'] == -50
		&& $posts[0]['beskrivelse'] === 'Afr: 1001 01-01-2026 - 31-10-2026',
		'One open post per customer with its share (Anna -100 over both prefixes, Bo -50)');
	check(value('SELECT count(*) FROM betalingsliste') === '2' && value("SELECT listenote FROM betalingsliste WHERE id = 1") === 'Egen liste'
		&& value('SELECT count(*) FROM betalinger WHERE liste_id = 1') === '0', 'The existing empty list is left alone');
	check(value('SELECT count(*) FROM betalinger WHERE liste_id = 2') === '2' && value("SELECT belob FROM betalinger WHERE egen_ref LIKE 'Afr: 1001 %'") === '100,00',
		'The new list holds one payment per customer for the amount just settled');
	check(value('SELECT string_agg(id::text, \',\' ORDER BY id) FROM ordrer WHERE settletime > 0 AND settletime <> 1700000000') === '1,2',
		'Only the settled orders on this register with a POS payment are stamped');

	settle('01-01-2026', '31-10-2026', 'on', 'on');
	check(value('SELECT count(*) FROM transaktioner') === '4' && value('SELECT count(*) FROM openpost') === '2'
		&& value('SELECT count(*) FROM betalingsliste') === '2', 'A second run settles nothing again');

	pg_query($connection, "INSERT INTO ordrer VALUES (6,'PO',3,'1','2026-11-02',0)");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (6,'Kontant')");
	pg_query($connection, "INSERT INTO ordrelinjer (ordre_id,vare_id,varenr,antal,pris,kostpris) VALUES (6,2,'kb1001002',1,30,12)");
	settle('01-01-2026', '09-11-2026', '', 'on');
	check(balance() === '0.00' && value("SELECT amount FROM openpost WHERE konto_nr = '1002' ORDER BY id DESC LIMIT 1") == -12
		&& value('SELECT count(*) FROM betalinger WHERE liste_id = 3') === '1', 'Dan betalingsliste alone also settles, balanced, with only the new sale');

	pg_query($connection, "INSERT INTO openpost (konto_id,konto_nr,amount,beskrivelse,transdate,kladde_id) VALUES (11,'1001',-40,'Afr: 1001 01-11-26 - 05-11-26 (60%)','2026-11-05',7)");
	pg_query($connection, "INSERT INTO ordrer VALUES (7,'PO',3,'1','2026-11-06',0)");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (7,'Kontant')");
	pg_query($connection, "INSERT INTO ordrelinjer (ordre_id,vare_id,varenr,antal,pris,kostpris) VALUES (7,1,'kb1001001',1,30,15)");
	$before = value('SELECT count(*) FROM transaktioner');
	settle('01-11-2026', '09-11-2026', 'on', '');
	check(count($alerts) === 1 && strpos($alerts[0], '05-11-2026') !== false && value('SELECT count(*) FROM transaktioner') === $before
		&& value('SELECT settletime FROM ordrer WHERE id = 7') === '0', 'A period already settled in the journal is refused with a message');
	// A journal settlement with a negative net commission (a return) is a debit open post.
	pg_query($connection, "INSERT INTO openpost (konto_id,konto_nr,amount,beskrivelse,transdate,kladde_id) VALUES (12,'1002',20,'Afr: 1002 20-10-26 - 25-10-26 (40%)','2026-11-07',8)");
	pg_query($connection, "INSERT INTO ordrer VALUES (9,'PO',3,'1','2026-10-28',0)");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (9,'Kontant')");
	pg_query($connection, "INSERT INTO ordrelinjer (ordre_id,vare_id,varenr,antal,pris,kostpris) VALUES (9,2,'kb1001002',1,20,8)");
	settle('21-10-2026', '22-10-2026', 'on', '');
	check(count($alerts) === 2 && strpos($alerts[1], '25-10-2026') !== false && value('SELECT count(*) FROM transaktioner') === $before,
		'A journal settlement with a negative net commission also blocks its period');
	settle('26-10-2026', '31-10-2026', 'on', '');
	check(count($alerts) === 2 && balance() === '0.00' && value("SELECT amount FROM openpost WHERE konto_nr = '1002' AND kladde_id = 0 ORDER BY id DESC LIMIT 1") == -8
		&& value('SELECT settletime FROM ordrer WHERE id = 7') === '0', 'A period between journal settlements is not blocked and settles only its own sale');
	settle('06-11-2026', '09-11-2026', 'on', '');
	check(balance() === '0.00' && value("SELECT amount FROM openpost WHERE konto_nr = '1001' AND kladde_id = 0 ORDER BY id DESC LIMIT 1") == -15
		&& value('SELECT count(*) FROM betalingsliste') === '3', 'After the journal period it settles, without a payment list');

	$alerts = array();
	pg_query($connection, "INSERT INTO ordrer VALUES (8,'PO',3,'1','2026-11-08',0)");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (8,'Kontant')");
	pg_query($connection, "INSERT INTO ordrelinjer (ordre_id,vare_id,varenr,antal,pris,kostpris) VALUES (8,1,'kb1001001',1,30,15),(8,4,'kb1009999',1,30,15)");
	$before = sideEffects();
	settle('06-11-2026', '09-11-2026', 'on', 'on');
	check(count($alerts) === 1 && strpos($alerts[0], 'kb1009999') !== false && sideEffects() === $before
		&& value('SELECT settletime FROM ordrer WHERE id = 8') === '0', 'A missing customer account stops the whole settlement before any posting');

	$alerts = array();
	pg_query($connection, "INSERT INTO adresser VALUES (13,'1003','D',2,'Cai','5555','66','Denmark','')");
	pg_query($connection, "INSERT INTO grupper (art,kodenr,fiscal_year,box2) VALUES ('DG','2',8,'5700')");
	pg_query($connection, "INSERT INTO varer VALUES (5,'kb1001003',50,1)");
	pg_query($connection, "INSERT INTO ordrer VALUES (10,'PO',3,'1','2026-11-20',0)");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (10,'Kontant')");
	pg_query($connection, "INSERT INTO ordrelinjer (ordre_id,vare_id,varenr,antal,pris,kostpris) VALUES (10,5,'kb1001003',1,30,15)");
	$before = sideEffects();
	settle('20-11-2026', '21-11-2026', 'on', 'on');
	check(count($alerts) === 1 && strpos($alerts[0], '1003') !== false && sideEffects() === $before
		&& value('SELECT settletime FROM ordrer WHERE id = 10') === '0', "A customer group without this year's collective account stops the settlement before any posting");

	$alerts = array();
	pg_query($connection, "INSERT INTO ordrer VALUES (11,'PO',3,'1','2026-11-22',0)");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (11,'Kontant')");
	pg_query($connection, "INSERT INTO ordrelinjer (ordre_id,vare_id,varenr,antal,pris,kostpris) VALUES (11,3,'kn1001001',1,10,5),(11,1,'kb1001001',1,14,7)");
	$before = sideEffects();
	settle('22-11-2026', '22-11-2026', 'on', '', array('kb'));
	check(count($alerts) === 1 && strpos($alerts[0], '1 ordrer') === 0 && strpos($alerts[0], 'med kn,') !== false
		&& sideEffects() === $before && value('SELECT settletime FROM ordrer WHERE id = 11') === '0',
		'An order with commission lines for an unconfigured account pair is left out with a message, not half settled');
	$alerts = array();
	settle('22-11-2026', '22-11-2026', 'on', '');
	check(!$alerts && balance() === '0.00' && value("SELECT amount FROM openpost WHERE konto_nr = '1001' AND kladde_id = 0 ORDER BY id DESC LIMIT 1") == -12
		&& value('SELECT settletime FROM ordrer WHERE id = 11') !== '0', 'With both account pairs the mixed order settles both lines');

	check(commissionDefaultFromDate(1, '2021-01-01') === '2026-11-22' && commissionDefaultFromDate(1, '2026-12-01') === '2026-12-01'
		&& commissionDefaultFromDate(2, '2021-01-01') === '2021-01-01' && commissionDefaultFromDate(2, '') === '',
		"The default from-date is the register's last settled sale, or the configured date when later");

	pg_query($connection, "INSERT INTO ordrer VALUES (12,'PO',3,'1','2026-11-23',0)");
	pg_query($connection, "INSERT INTO pos_betalinger VALUES (12,'Kontant')");
	pg_query($connection, "INSERT INTO ordrelinjer (ordre_id,vare_id,varenr,antal,pris,kostpris) VALUES (12,2,'kb1001002',1,18,9)");
	// Another cash count settles order 12 after this one read it, before this one locks it.
	$beforeLock = function() { pg_query($GLOBALS['connection'], 'UPDATE ordrer SET settletime = 1 WHERE id = 12'); };
	$before = sideEffects();
	settle('23-11-2026', '23-11-2026', 'on', 'on');
	check(count($alerts) === 1 && strpos($alerts[0], 'anden kasseoptælling') !== false && sideEffects() === $before,
		'Orders settled by another cash count after they were read are not settled again');

	// A zero-cost commission line and a line of an item without provision on order 1, which the cash count settled without them.
	pg_query($connection, "INSERT INTO varer VALUES (6,'kb1001004',NULL,1)");
	pg_query($connection, "INSERT INTO ordrelinjer (ordre_id,vare_id,varenr,antal,pris,kostpris) VALUES (1,2,'kb1001002',1,40,0),(1,6,'kb1001004',1,30,15)");
	pg_query($connection, "INSERT INTO batch_salg (linje_id,vare_id,pris,antal,fakturadate) SELECT id, vare_id, pris, antal, '2026-10-01' FROM ordrelinjer WHERE ordre_id IN (1,4)");
	$journal = pg_fetch_all(pg_query($connection, loppeafregningSalesQuery('kb', '', '2026-10-01', '2026-10-31'))) ?: array();
	check(count($journal) === 3 && $journal[0]['varenr'] === 'kb1001001' && $journal[0]['kostpris'] == 60
		&& $journal[1]['varenr'] === 'kb1001002' && $journal[1]['pris'] == 40 && $journal[1]['kostpris'] == 0
		&& $journal[2]['varenr'] === 'kb1001004' && $journal[2]['kostpris'] == 15,
		'The journal settlement leaves out only the lines settled at the cash count');
} finally {
	pg_query($connection, "DROP SCHEMA IF EXISTS $schema CASCADE");
	pg_close($connection);
}
