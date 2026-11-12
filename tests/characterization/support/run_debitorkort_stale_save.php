<?php
// --- tests/characterization/support/run_debitorkort_stale_save.php ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261112 CL/SS MB-82 Child-process runner for DebitorkortStaleSaveTest.
//                A customer card opened before another user's save must not overwrite that save.
//                It runs the real debitorkortIncludes/staleSave.php against an isolated schema in the database named by SALDI_STALE_SAVE_DSN.
//                It prints one "PASS: <label>" per check, and "FAIL: <label>" plus exit 1 on the first failure.

error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$dsn = getenv('SALDI_STALE_SAVE_DSN');
if (!extension_loaded('pgsql') || !$dsn) {
	fwrite(STDERR, "SALDI_STALE_SAVE_DSN and the pgsql extension are required\n");
	exit(2);
}
$connection = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
$schema = 'stale_save_test_' . bin2hex(random_bytes(6));

/** @return PgSql\Result */
function db_select($sql, $location) { return pg_query($GLOBALS['connection'], $sql); }
/** @return array|false */
function db_fetch_array($query) { return pg_fetch_array($query); }
require_once(__DIR__ . '/../../../debitor/debitorkortIncludes/staleSave.php');

/** @return void */
function check($condition, $message) {
	if (!$condition) {
		echo "FAIL: $message\n";
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}
/** @return void */
function run($sql) { pg_query($GLOBALS['connection'], $sql); }

try {
	run("CREATE SCHEMA $schema");
	run("SET search_path TO $schema");
	run("CREATE TABLE adresser (id int PRIMARY KEY, kontonr text, firmanavn text, notes text, email text, kontakt text, lukket text,
		saldo numeric, invoiced date, modtime timestamp DEFAULT now())");
	run("CREATE TABLE kontakt_emails (id serial, konto_id int, email text, email_type text)");
	run("CREATE TABLE delivery_addresses (id serial, account_id int, is_primary boolean, sort_order int, address_line1 text, city text)");
	run("CREATE TABLE ansatte (id serial, konto_id int, navn text, posnr int)");
	run("INSERT INTO adresser (id,kontonr,firmanavn,notes,email,kontakt,lukket,saldo) VALUES (7,'1007','Anna ApS','Old notes','a@x.dk','Anna','',0)");
	run("INSERT INTO kontakt_emails (konto_id,email,email_type) VALUES (7,'a@x.dk','faktura')");
	run("INSERT INTO delivery_addresses (account_id,is_primary,sort_order,address_line1,city) VALUES (7,true,0,'Vej 1','Kbh')");
	run("INSERT INTO ansatte (konto_id,navn,posnr) VALUES (7,'Anna',1),(7,'Bent',2)");

	// User A and user B open the same card.
	$cardA = array('id' => '7', 'recordHash' => debitorkortRecordHash(7));
	$cardB = array('id' => '7', 'recordHash' => debitorkortRecordHash(7));
	check($cardA['recordHash'] === $cardB['recordHash'] && !debitorkortSaveIsStale(7, $cardB),
		'A card saved with nothing changed since it was opened is not stale');

	// B saves new notes; A, still on the old form, saves next.
	run("UPDATE adresser SET notes = 'New notes from B' WHERE id = 7");
	check(debitorkortSaveIsStale(7, $cardA), "A card opened before another user's save is refused");
	$cardA = array('id' => '7', 'recordHash' => debitorkortRecordHash(7));
	check(!debitorkortSaveIsStale(7, $cardA), 'After reopening, the same user can save');

	$opened = array('id' => '7', 'recordHash' => debitorkortRecordHash(7));
	run("UPDATE adresser SET saldo = 1250, invoiced = '2026-11-12', modtime = now() + interval '1 hour' WHERE id = 7");
	check(!debitorkortSaveIsStale(7, $opened), "Invoicing and modtime (columns the card doesn't save) don't make a card stale");

	run("UPDATE kontakt_emails SET email = 'ny@x.dk' WHERE konto_id = 7");
	check(debitorkortSaveIsStale(7, $opened), "A changed e-mail row makes the card stale");
	$opened = array('id' => '7', 'recordHash' => debitorkortRecordHash(7));
	run("UPDATE delivery_addresses SET city = 'Aarhus' WHERE account_id = 7");
	check(debitorkortSaveIsStale(7, $opened), "A changed delivery address makes the card stale");
	$opened = array('id' => '7', 'recordHash' => debitorkortRecordHash(7));
	run("INSERT INTO delivery_addresses (account_id,is_primary,sort_order,address_line1,city) VALUES (7,false,1,'Vej 2','Odense')");
	check(debitorkortSaveIsStale(7, $opened), "An added delivery address makes the card stale");
	$opened = array('id' => '7', 'recordHash' => debitorkortRecordHash(7));
	run("UPDATE ansatte SET posnr = 3 - posnr WHERE konto_id = 7");
	check(debitorkortSaveIsStale(7, $opened), "Reordered contacts make the card stale");

	// A legacy install whose connection reads and writes LATIN9.
	pg_set_client_encoding($connection, 'LATIN9');
	run("INSERT INTO adresser (id,kontonr,firmanavn,notes) VALUES (8,'1008','" . mb_convert_encoding('Søren Ærø', 'ISO-8859-15', 'UTF-8') . "','Gamle noter')");
	$latin = array('id' => '8', 'recordHash' => debitorkortRecordHash(8));
	run("UPDATE adresser SET notes = 'Nye noter' WHERE id = 8");
	check(debitorkortSaveIsStale(8, $latin) && $latin['recordHash'] !== debitorkortRecordHash(9999),
		'On a LATIN9 connection a customer with æøå is still detected as changed');
	pg_set_client_encoding($connection, 'UTF8');

	run("UPDATE adresser SET notes = 'Changed again' WHERE id = 7");
	check(!debitorkortSaveIsStale(7, array('id' => '7')) && !debitorkortSaveIsStale(0, array('id' => '0', 'recordHash' => 'x'))
		&& !debitorkortSaveIsStale(7, array('id' => '7', 'recordHash' => array('x'))),
		'A post without recordHash, a new customer and a malformed hash are not checked');
} finally {
	pg_query($connection, "DROP SCHEMA IF EXISTS $schema CASCADE");
	pg_close($connection);
}
