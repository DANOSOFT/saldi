<?php
// --- tests/test_stock_receipt_posting.php --- ver 5.0.0 --- 2026-10-21 ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261021 CL/LH MB-75: Runs the real modtag() from lager/modtagelse.php against PostgreSQL, including two
//                independent worker processes. Ported from the unmerged 36e02eac (CDX/LH 20260921).
// Run with SALDI_TEST_DSN pointing at a throwaway PostgreSQL database; everything is created in a random
// schema that is dropped again at the end.

if (PHP_SAPI !== 'cli') exit('CLI only');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new RuntimeException("$message ($file:$line)");
});
$dsn = getenv('SALDI_TEST_DSN');
if (!$dsn) {
	exit("SKIP: set SALDI_TEST_DSN to an isolated PostgreSQL fixture.\n");
}
$connection = pg_connect($dsn);
$db_type = 'postgresql';
$db_encode = 'UTF8';
$db_transaktion_depth = 0;
$db_modify_fejl = false;
$brugernavn = "fixture's receiver";
$sprog_id = 1;
$db = 'receipt-fixture';
function db_select($sql, $where = '') {
	return pg_query($GLOBALS['connection'], $sql);
}
function db_fetch_array($result) {
	return pg_fetch_assoc($result);
}
function db_modify($sql, $where = '') {
	db_select($sql);
	return '0' . chr(9) . 'query accepted';
}
function db_escape_string($value) {
	return pg_escape_string($GLOBALS['connection'], $value);
}
function get_relative() {
	return '';
}
function db_log_append($path, $text) {
}
function findtekst($textId, $languageId) {
	$GLOBALS['receiptMessages'][] = $textId;
	$parts = explode('|', $textId, 2);
	return $parts[1] ?? $parts[0];
}
require_once __DIR__ . '/../includes/std_func.php';
$source = file_get_contents(__DIR__ . '/../lager/modtagelse.php');
$start = strpos($source, 'function modtag($liste_id)');
$end = strpos($source, "\n?>", $start);
eval(substr($source, $start, $end - $start));
$source = file_get_contents(__DIR__ . '/../includes/db_query.php');
$start = strpos($source, "if (!function_exists('transaktion'))");
$end = strpos($source, "if (!function_exists('db_escape_string'))", $start);
eval(substr($source, $start, $end - $start));

function receiptValue($sql) {
	return pg_fetch_result(db_select($sql), 0, 0);
}
function postingCheck($condition, $message) {
	if (!$condition) {
		throw new RuntimeException("FAIL: $message");
	}
	echo "PASS: $message\n";
}
/** Runs modtag() without its alert output and returns [result, text id of the message or null]. */
function receiptRun($listId) {
	$GLOBALS['receiptMessages'] = array();
	ob_start();
	$result = modtag($listId);
	ob_end_clean();
	$messages = $GLOBALS['receiptMessages'];
	return array($result, $messages ? strtok($messages[0], '|') : null);
}

if (($argv[1] ?? '') === '--worker') {
	$schema = $argv[2];
	if (!preg_match('/^receipt_[a-f0-9]+$/D', $schema)) {
		throw new RuntimeException('Unsafe fixture schema');
	}
	db_select("SET search_path TO $schema");
	db_select("SET application_name TO '" . db_escape_string($argv[4]) . "'");
	echo json_encode(array('result' => (bool)receiptRun((int)$argv[3])[0]));
	exit;
}

$schema = 'receipt_' . bin2hex(random_bytes(8));
db_select("CREATE SCHEMA $schema; SET search_path TO $schema");
try {
	db_select("CREATE TABLE modtageliste(id integer PRIMARY KEY,modtaget text,modtaget_af text,modtagdate date,tidspkt text);
CREATE TABLE modtagelser(id integer PRIMARY KEY,liste_id integer,vare_id integer,varenr text,antal numeric(15,3),lager numeric(15,3));
CREATE TABLE varer(id integer PRIMARY KEY,beholdning numeric(15,3));
CREATE TABLE ordrer(id integer PRIMARY KEY,art text,status text,ordredate date);
CREATE TABLE ordrelinjer(id integer PRIMARY KEY,ordre_id integer,vare_id integer,antal numeric(15,3),leveres numeric(15,3),lager integer,variant_id text,batch_due_date date,batch_batch_no varchar(100));
CREATE TABLE batch_kob(id serial PRIMARY KEY,kobsdate date,vare_id integer,variant_id integer,linje_id integer,ordre_id integer,antal numeric(15,3),rest numeric(15,3),lager integer,due_date date,batch_no varchar(100));
CREATE TABLE lagerstatus(id serial PRIMARY KEY,vare_id integer,variant_id integer,lager integer,beholdning numeric(15,3));
CREATE TABLE variant_varer(id integer PRIMARY KEY,vare_id integer,variant_beholdning numeric(15,3));
CREATE TABLE grupper(id serial PRIMARY KEY,art text,kodenr text,beskrivelse text,fiscal_year integer);");
	function postingReset() {
		db_select("TRUNCATE modtageliste,modtagelser,varer,ordrer,ordrelinjer,batch_kob,lagerstatus,variant_varer,grupper RESTART IDENTITY;
INSERT INTO modtageliste VALUES(1,'-',NULL,NULL,NULL),(2,'-',NULL,NULL,NULL);
INSERT INTO modtagelser VALUES(1,1,1,'V-1',1,99),(2,2,1,'V-1',1,88);
INSERT INTO varer VALUES(1,7);
INSERT INTO ordrer VALUES(1,'KO','1',CURRENT_DATE-3);
INSERT INTO ordrelinjer VALUES(1,1,1,1,1,1,'0',CURRENT_DATE+20,'LOT-1');
INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,9,7);
INSERT INTO grupper(art,kodenr,beskrivelse,fiscal_year) VALUES('LG','1','Hovedlager',1),('LG','3','Lager 3',1),('LG','4','Lager 4',1),('LG','9','Lager 9',1),('LG','9','Lager 9',2);");
		$GLOBALS['db_modify_fejl'] = false;
	}
	function receiptSnapshot() {
		$result = array();
		foreach (array('modtageliste', 'modtagelser', 'varer', 'ordrelinjer', 'batch_kob', 'lagerstatus', 'variant_varer') as $table) {
			$result[$table] = receiptValue("SELECT COALESCE(json_agg(t ORDER BY id)::text,'[]') FROM $table t");
		}
		return $result;
	}

	postingReset();
	postingCheck(receiptRun(1) === array(true, null), 'actual receipt posts successfully without a message');
	postingCheck(receiptValue('SELECT beholdning FROM varer') === '8.000', 'global item stock increments once');
	postingCheck(receiptValue("SELECT vare_id||'|'||lager||'|'||beholdning||'|'||variant_id FROM lagerstatus WHERE lager=1") === '1|1|1.000|0' && receiptValue('SELECT beholdning FROM lagerstatus WHERE lager=9') === '7.000', 'new purchase warehouse row created, unrelated warehouse unchanged');
	postingCheck(receiptValue("SELECT linje_id||'|'||ordre_id||'|'||vare_id||'|'||antal||'|'||rest||'|'||(kobsdate=CURRENT_DATE)||'|'||(due_date=CURRENT_DATE+20)||'|'||batch_no||'|'||lager||'|'||variant_id FROM batch_kob") === '1|1|1|1.000|1.000|true|true|LOT-1|1|0' && receiptValue('SELECT COUNT(*) FROM batch_kob') === '1', 'purchase batch carries warehouse, variant and expiry metadata');
	postingCheck(receiptValue('SELECT modtaget FROM modtageliste WHERE id=1') === 'V' && receiptValue('SELECT modtaget_af FROM modtageliste WHERE id=1') === "fixture's receiver" && receiptValue('SELECT leveres FROM ordrelinjer') === '0.000', 'list is closed, receiver is escaped and outstanding delivery is consumed');
	postingCheck(preg_match('/^\d{2}:[0-5]\d:[0-5]\d$/D', receiptValue('SELECT tidspkt FROM modtageliste WHERE id=1')) === 1, 'receipt time is stored as hours:minutes:seconds');
	$before = receiptSnapshot();
	postingCheck(receiptRun(1) === array(true, null) && receiptSnapshot() === $before, 'completed list replay is an exact no-op');

	postingReset();
	db_select("UPDATE ordrelinjer SET lager=NULL,variant_id=NULL;INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,NULL,0,2);UPDATE varer SET beholdning=9");
	postingCheck(receiptRun(1)[0] && receiptValue('SELECT beholdning FROM lagerstatus WHERE lager=0') === '3.000' && receiptValue('SELECT COUNT(*) FROM lagerstatus') === '2' && receiptValue('SELECT lager FROM batch_kob') === '1', 'no warehouse means warehouse 1 and updates the existing warehouse 0 row without a duplicate');

	postingReset();
	db_select("UPDATE modtagelser SET antal=10 WHERE id=1;UPDATE ordrelinjer SET antal=4,leveres=4;INSERT INTO ordrelinjer VALUES(2,1,1,4,4,3,'0',NULL,NULL),(3,1,1,4,4,4,'',NULL,NULL)");
	postingCheck(receiptRun(1)[0] && receiptValue("SELECT string_agg(linje_id||':'||antal||':'||lager,',' ORDER BY linje_id) FROM batch_kob") === '1:4.000:1,2:4.000:3,3:2.000:4', 'oldest-first allocation uses each line outstanding quantity and its warehouse');
	postingCheck(receiptValue("SELECT string_agg(lager||':'||beholdning,',' ORDER BY lager) FROM lagerstatus") === '1:4.000,3:4.000,4:2.000,9:7.000' && receiptValue('SELECT beholdning FROM varer') === '17.000', 'split receipt books exact warehouse quantities');

	postingReset();
	db_select("UPDATE modtagelser SET antal=1.5 WHERE id=1;UPDATE ordrelinjer SET antal=2,leveres=2");
	postingCheck(receiptRun(1)[0] && receiptValue('SELECT antal FROM batch_kob') === '1.500' && receiptValue('SELECT beholdning FROM lagerstatus WHERE lager=1') === '1.500' && receiptValue('SELECT leveres FROM ordrelinjer') === '0.500', 'decimal quantity 1.5 is booked exactly');

	postingReset();
	db_select("UPDATE ordrelinjer SET variant_id='2';INSERT INTO variant_varer VALUES(2,1,3)");
	postingCheck(receiptRun(1)[0] && receiptValue('SELECT variant_beholdning FROM variant_varer') === '4.000' && receiptValue('SELECT variant_id FROM lagerstatus WHERE lager=1') === '2' && receiptValue('SELECT variant_id FROM batch_kob') === '2', 'receipt keeps the purchase variant and increments its stock');

	$failures = array(
		'over-receipt' => array('UPDATE modtagelser SET antal=2 WHERE id=1', '5464'),
		'duplicate-warehouse' => array('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,1,2),(1,0,1,3)', '5463'),
		'duplicate-warehouse-0-and-1' => array('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,0,2),(1,0,1,3)', '5463'),
		'missing-variant' => array("UPDATE ordrelinjer SET variant_id='999'", '5462'),
		'variant-of-other-item' => array("UPDATE ordrelinjer SET variant_id='2';INSERT INTO variant_varer VALUES(2,5,3)", '5462'),
		'non-numeric-variant' => array("UPDATE ordrelinjer SET variant_id='x'", '5462'),
		'unknown-warehouse' => array('UPDATE ordrelinjer SET lager=5', '5461'),
		'negative-warehouse' => array('UPDATE ordrelinjer SET lager=-1', '5461'),
		'missing-item' => array('UPDATE modtagelser SET vare_id=2 WHERE id=1', '5466'),
		'zero-quantity' => array('UPDATE modtagelser SET antal=0 WHERE id=1', '5466'),
		'empty-list' => array('DELETE FROM modtagelser WHERE id=1', '5467'),
		'no-open-order' => array("UPDATE ordrer SET status='3'", '5464'),
	);
	foreach ($failures as $failure => list($setup, $textId)) {
		postingReset();
		db_select($setup);
		$before = receiptSnapshot();
		$run = receiptRun(1);
		postingCheck($run === array(false, $textId) && receiptSnapshot() === $before, "$failure rejects with text $textId and full rollback");
	}
	postingReset();
	postingCheck(receiptRun(3) === array(false, '5467'), 'missing list rejects with text 5467');

	postingReset();
	db_select("CREATE FUNCTION discard_warehouse() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RETURN NULL; END';CREATE TRIGGER discard BEFORE INSERT ON lagerstatus FOR EACH ROW EXECUTE FUNCTION discard_warehouse()");
	$before = receiptSnapshot();
	postingCheck(receiptRun(1) === array(false, '5465') && receiptSnapshot() === $before, 'silently discarded warehouse insert rolls back batch, item and list writes');
	db_select('DROP TRIGGER discard ON lagerstatus');

	// The parent holds the item lock until both fresh processes are waiting.
	// Neither process shares a PHP transaction counter or database connection.
	foreach (array(array(false, 1), array(true, 1), array(false, 2)) as list($sameList, $capacity)) {
		postingReset();
		db_select("UPDATE ordrelinjer SET antal=$capacity,leveres=$capacity");
		db_select('BEGIN;SELECT id FROM varer WHERE id=1 FOR UPDATE');
		$workers = array();
		foreach (array(1, 2) as $number) {
			$name = $schema . '_' . $number;
			$process = proc_open(array(PHP_BINARY, __FILE__, '--worker', $schema, (string)($sameList ? 1 : $number), $name), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
			$workers[] = array($process, $pipes);
		}
		$waiting = false;
		for ($attempt = 0; $attempt < 250; $attempt++) {
			db_select('SELECT pg_stat_clear_snapshot()');
			if ((int)receiptValue("SELECT COUNT(*) FROM pg_stat_activity WHERE application_name IN ('{$schema}_1','{$schema}_2') AND wait_event_type='Lock'") === 2) {
				$waiting = true;
				break;
			}
			usleep(20000);
		}
		db_select('COMMIT');
		$results = array();
		foreach ($workers as list($process, $pipes)) {
			$stdout = stream_get_contents($pipes[1]);
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$status = proc_close($process);
			postingCheck($status === 0 && $stderr === '', 'independent receipt worker exits without warnings');
			$results[] = json_decode($stdout, true)['result'];
		}
		postingCheck($waiting, 'both receipt workers demonstrably overlapped on database locks');
		$expectedReceived = $sameList ? 1 : $capacity;
		postingCheck(array_sum($results) === ($sameList ? 2 : $capacity) && (int)receiptValue('SELECT COUNT(*) FROM batch_kob') === $expectedReceived && (float)receiptValue('SELECT beholdning FROM varer') === 7.0 + $expectedReceived && (float)receiptValue('SELECT beholdning FROM lagerstatus WHERE lager=1') === (float)$expectedReceived && receiptValue('SELECT COUNT(*) FROM lagerstatus WHERE lager=1') === '1', $sameList ? 'concurrent replay produces exactly one receipt' : "competing lists with capacity $capacity conserve the open quantity without lost increments or duplicate warehouse rows");
	}
} finally {
	if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) {
		db_select('ROLLBACK');
	}
	db_select("DROP SCHEMA $schema CASCADE");
}
