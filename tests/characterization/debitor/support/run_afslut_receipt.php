<?php
// Child process for MobilepayPaymentFlowTest: settles a POS order paid with MobilePay through the
// shipped afslut() (debitor/pos_ordre_includes/exitFunc/exit.php) and settlePOS.php, the way
// pos_ordre.php calls it when the till comes back with godkendt=OK, and prints the pos_betalinger
// rows as JSON once the sale commits.
//
// afslut() calls exit() and include()s relative paths, so it cannot run inside PHPUnit. The tables
// it touches live in an in-memory SQLite database. levering(), bogfor() and the invoice number are
// stubbed - they are not what this pins - and so is voucherPay(), from a scratch tree.
//
// Usage: php run_afslut_receipt.php <receipt_id> <placeholder: 1 = the '!' row exit.php inserts before
//        sending the till to payments/mobilepay.php, 0 = no such row>
//
// 20261027 CL/LH Created (PR #502).

$receiptId   = (string) $argv[1];
$placeholder = ($argv[2] ?? '1') === '1';

# Warnings go to stderr and the page output is dropped, so stdout carries only the JSON
ini_set('display_errors', 'stderr');
ob_start();

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ordrer (id INTEGER PRIMARY KEY, sum NUMERIC, moms NUMERIC, konto_id INTEGER, status INTEGER,
	fakturanr TEXT, momssats NUMERIC, betalingsbet TEXT, levdate TEXT, fakturadate TEXT, betalt NUMERIC, felt_1 TEXT,
	felt_2 TEXT, felt_3 TEXT, felt_4 TEXT, felt_5 TEXT, tidspkt TEXT, projekt TEXT, ref TEXT)");
$pdo->exec("CREATE TABLE ordrelinjer (id INTEGER PRIMARY KEY, ordre_id INTEGER, vare_id INTEGER, varenr TEXT,
	pris NUMERIC, kostpris NUMERIC, antal NUMERIC, momsfri TEXT, momssats NUMERIC, fast_db NUMERIC, folgevare INTEGER,
	tilfravalg TEXT, rabat NUMERIC, rabatart TEXT, rabatgruppe INTEGER, variant_id INTEGER, saet INTEGER,
	m_rabat NUMERIC, posnr INTEGER, projekt TEXT)");
$pdo->exec("CREATE TABLE pos_betalinger (id INTEGER PRIMARY KEY AUTOINCREMENT, ordre_id INTEGER, betalingstype TEXT,
	amount NUMERIC, valuta TEXT, valutakurs NUMERIC, receipt_id VARCHAR(75), payment_id INTEGER)");
$pdo->exec("CREATE TABLE grupper (id INTEGER PRIMARY KEY AUTOINCREMENT, beskrivelse TEXT, art TEXT, kodenr TEXT,
	fiscal_year TEXT, box1 TEXT, box2 TEXT, box3 TEXT, box4 TEXT, box5 TEXT, box6 TEXT, box7 TEXT, box8 TEXT,
	box9 TEXT, box10 TEXT, box11 TEXT, box12 TEXT)");
$pdo->exec("CREATE TABLE pos_events (id INTEGER PRIMARY KEY AUTOINCREMENT, ev_type TEXT, ev_time TEXT,
	cash_register_id TEXT, employee_id TEXT, order_id INTEGER, file TEXT, line TEXT)");

# Order 42: one item at 100 + 25 % VAT, paid in full with MobilePay
$pdo->exec("INSERT INTO ordrer (id, sum, moms, konto_id, status, momssats, betalingsbet) VALUES (42, 100, 25, 0, 0, 25, 'Kontant')");
$pdo->exec("INSERT INTO ordrelinjer (id, ordre_id, vare_id, varenr, pris, kostpris, antal, momsfri, momssats, fast_db,
	folgevare, tilfravalg, rabat, rabatart, rabatgruppe, variant_id, saet, m_rabat)
	VALUES (1, 42, 7, 'KAFFE', 100, 40, 1, '', 25, 0, 0, '', 0, '', 0, 0, 0, 0)");
$pdo->exec("INSERT INTO grupper (art, kodenr, fiscal_year, box8, box9) VALUES ('POS', '1', '1', '', '')");
$pdo->exec("INSERT INTO grupper (art, kodenr, fiscal_year, box9) VALUES ('POS', '2', '1', '')");
if ($placeholder) {
	$pdo->exec("INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES (42, '!', 125, 'DKK', 100)");
}

function db_select($qtxt, $source = '') {
	return $GLOBALS['pdo']->query($qtxt);
}
function db_fetch_array($statement) {
	return $statement ? $statement->fetch(PDO::FETCH_BOTH) : false;
}
function db_modify($qtxt, $source = '') {
	$GLOBALS['pdo']->exec($qtxt);
}
function db_escape_string($text) {
	return str_replace("'", "''", (string) $text);
}
function transaktion($action) {
	if ($action === 'commit') {
		$rows = $GLOBALS['pdo']->query("SELECT ordre_id, betalingstype, amount, receipt_id FROM pos_betalinger ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		print json_encode(array('pos_betalinger' => $rows));
		exit(0);
	}
}
function posvaluta($amount) {
	return "$amount\t$amount\tDKK\t100";
}
function betaling(...$args) {
	return 125;
}
function pos_afrund($amount, ...$args) {
	return round((float) $amount, 2);
}
function afrund($amount, $decimals) {
	return round((float) $amount, (int) $decimals);
}
function get_next_invoice_number($art = 'DO', $id = null) {
	return 1001;
}
function levering(...$args) {
	return 'OK';
}
function bogfor(...$args) {
	return 'OK';
}

$scratch = sys_get_temp_dir() . '/mp-afslut-' . bin2hex(random_bytes(6));
mkdir($scratch . '/debitor/pos_ordre_includes/voucherFunc', 0700, true);
file_put_contents($scratch . '/debitor/pos_ordre_includes/voucherFunc/voucherPay.php', '<?php function voucherPay(...$args) {}');
register_shutdown_function(function () use ($scratch) {
	unlink($scratch . '/debitor/pos_ordre_includes/voucherFunc/voucherPay.php');
	rmdir($scratch . '/debitor/pos_ordre_includes/voucherFunc');
	rmdir($scratch . '/debitor/pos_ordre_includes');
	rmdir($scratch . '/debitor');
	rmdir($scratch);
});
chdir($scratch . '/debitor');

# The globals pos_ordre.php has set up when it calls afslut()
$afd = 0;
$betalingsbet = 'Kontant';
$bruger_id = 7;
$brugernavn = 'mobilepaytest';
$charset = 'UTF-8';
$db = 'mobilepaytest';
$indbetaling = 0;
$kasse = 1;
$momssats = 25;
$regnaar = 1;
$retur = 0;
$tracelog = null;

require dirname(__DIR__, 4) . '/debitor/pos_ordre_includes/exitFunc/exit.php';
# pos_ordre.php, $godkendt == 'OK' branch: the values come from the redirect of payments/mobilepay/paymentui.php
afslut(42, 'mobilepay', null, '125', null, '0', 'OK', null, $receiptId, __LINE__);
fwrite(STDERR, "afslut() returned without committing the sale\n");
exit(1);
