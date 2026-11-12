<?php
// tests/characterization/debitor/support/run_afslut_posting_error.php
//
// Child-process runner for PosCheckoutPostingErrorTest (MB-49).
// It runs the real afslut() from debitor/pos_ordre_includes/exitFunc/exit.php, as tests/test_pos_cash_checkout.php does,
// with the database, settlement (settlePOS.php), voucher and receipt boundaries stood in for.
// The stand-in settlePOS.php sets $svar to what the case asks for, so the posting result can be chosen.
//
// argv: 1 = JSON case, 2 = fixture directory (holds debitor/settlePOS.php and the voucher stand-in).
// Prints one JSON line: afslut()'s return value, the boundary events in order and the HTML printed.
//
// History:
// 20261112 CL/SS MB-49: created.

$case = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
chdir($argv[2] . '/debitor');
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
$events = [];
$returned = null;
$afd = 0;
$bruger_id = 7;
$brugernavn = 'mb49test';
$db = 'mb49test';
$indbetaling = 0;
$kasse = 1;
$momssats = 25;
$regnaar = 1;
$retur = 0;
$sprog_id = 1;
$tracelog = null;
$_SERVER = ['SERVER_NAME' => 'checkout.example', 'PHP_SELF' => '/saldi/debitor/pos_ordre.php', 'REMOTE_ADDR' => '127.0.0.1'];
$_COOKIE = [];

/** @return string */
function posvaluta($amount) { return "$amount\t$amount\tDKK\t100"; }
/** @return float */
function betaling(...$args) { return $GLOBALS['case']['sum'] ?? 0; }
/** @return float */
function pos_afrund($amount, ...$args) { return round((float)$amount, 2); }
/** @return float */
function afrund($amount, $decimals) { return round((float)$amount, $decimals); }
/** @return string */
function findtekst($id, $language) { return (string)(explode('|', (string)$id, 2)[1] ?? $id); }
/** @return ArrayIterator */
function db_select($sql, ...$args) {
	$case = $GLOBALS['case'];
	if (str_contains($sql, 'from ordrer')) {
		return new ArrayIterator([[
			'sum' => $case['sum'] ?? 0, 'moms' => $case['moms'] ?? 0, 'konto_id' => 0,
			'status' => 0, 'fakturanr' => 0, 'momssats' => 25, 'betalingsbet' => 'Kontant',
		]]);
	}
	if (str_contains($sql, 'sum(amount) as paid from pos_betalinger')) {
		return new ArrayIterator([['paid' => $case['paid'] ?? 0]]);
	}
	if (str_contains($sql, 'from pos_betalinger')) {
		return new ArrayIterator([]);
	}
	if (str_contains($sql, 'select box3,box4,box5,box6')) {
		return new ArrayIterator([['box3' => 'printer.example', 'box4' => '', 'box5' => '', 'box6' => '']]);
	}
	if (str_contains($sql, "art='POSBUT'")) {
		return new ArrayIterator([['box9' => '']]);
	}
	if (str_contains($sql, 'box10')) {
		return new ArrayIterator([]);
	}
	throw new RuntimeException('Unexpected query: ' . $sql);
}
/** @return array|false */
function db_fetch_array($rows) {
	if (!$rows->valid()) {
		return false;
	}
	$row = $rows->current();
	$rows->next();
	return $row;
}
/** @return void */
function db_modify($sql, ...$args) {
	if (!str_starts_with($sql, 'insert into pos_events')) {
		throw new RuntimeException('Unexpected write: ' . $sql);
	}
}
/** @return void */
function transaktion($action) { $GLOBALS['events'][] = $action; }
/** @return void */
function shopSyncDrainQueue() {}
/** @return void */
function pos_txt_print(...$args) {
	$GLOBALS['events'][] = 'receipt';
	exit;
}

ob_start();
register_shutdown_function(function () {
	echo json_encode(['returned' => $GLOBALS['returned'], 'events' => $GLOBALS['events'], 'html' => ob_get_clean()], JSON_THROW_ON_ERROR), "\n";
});
require __DIR__ . '/../../../../debitor/pos_ordre_includes/exitFunc/exit.php';
$returned = afslut(42, $case['payment'], '', $case['received'], 0, 0, '', '');
