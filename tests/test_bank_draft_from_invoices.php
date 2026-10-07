<?php
// tests/test_bank_draft_from_invoices.php --- 2026-10-06
// Copyright (c) 2026 Danosoft ApS
// 20261006 LOE A bank draft line carries the invoice's own text, and its name when it has none.

# Run: php tests/test_bank_draft_from_invoices.php
error_reporting(E_ALL);
set_error_handler(function($severity,$message){throw new ErrorException($message,0,$severity);});

require_once __DIR__ . '/../debitor/ordLstIncludes/bankkladdeFraFakturaer.php';

$fejl = 0;
$proever = 0;

# The text on the line: the invoice's own text first, and the customer together with the invoice
# number when the invoice carries no text of its own.
$beskrivelser = array(
	array('Rådgivning i marts', 'Nordisk Handel A/S', '112', 'Rådgivning i marts'),
	array('  Luft i begge ender  ', 'Kunde A/S', '7', 'Luft i begge ender'),
	array('', 'Nordisk Handel A/S', '112', 'Nordisk Handel A/S - Faktura 112'),
	array('   ', 'Nordisk Handel A/S', '112', 'Nordisk Handel A/S - Faktura 112'),
	array('', '  Nordisk Handel A/S  ', ' 112 ', 'Nordisk Handel A/S - Faktura 112'),
	array('', '', '112', 'Faktura 112'),
	array('', 'Nordisk Handel A/S', '', 'Nordisk Handel A/S'),
	array('', '', '', ''),
	array('0', 'Nordisk Handel A/S', '112', '0'),
);
foreach ($beskrivelser as $proeve) {
	$proever++;
	$faktisk = bankkladdeBeskrivelse($proeve[0], $proeve[1], $proeve[2]);
	if ($faktisk !== $proeve[3]) {
		$fejl++;
		echo "FEJL: '{$proeve[0]}' / '{$proeve[1]}' / '{$proeve[2]}' gav '$faktisk', ventede '{$proeve[3]}'\n";
	}
}

# Which chart rows name a bank account the money can arrive on. The false cases are real chart texts
# that carry the word bank without being such an account.
$banknavne = array(
	array('Bank & giro', true),
	array('Bankkonto', true),
	array('Bankindestående', true),
	array('Kasse -> Bank Helsinge', true),
	array('Jyske Bank 5050 1548742', true),
	array('Renter fra banker', false),
	array('Bankrenter', false),
	array('Kurstab på likvider, bankgæld og prioritetsgæld', false),
	array('Gæld til banker - langfristet gæld', false),
	array('Banklån ', false),
	array('Bankgebyr', false),
	array('Debitorer, ubetalte fakturaer', false),
	array('', false),
);
foreach ($banknavne as $proeve) {
	$proever++;
	$faktisk = bankkladdeBanknavn($proeve[0]);
	if ($faktisk !== $proeve[1]) {
		$fejl++;
		echo "FEJL: banknavn '{$proeve[0]}' gav " . ($faktisk ? 'true' : 'false') . ", ventede " . ($proeve[1] ? 'true' : 'false') . "\n";
	}
}

# A chart account number may arrive with a decimal tail from some drivers, and the draft line needs
# the bare number.
$konti = array(
	array('9800', '9800'),
	array('9800.0', '9800'),
	array(' 56100 ', '56100'),
	array('1000.0', '1000'),
	array('', ''),
);
foreach ($konti as $proeve) {
	$proever++;
	$faktisk = bankkladdeKontonr($proeve[0]);
	if ($faktisk !== $proeve[1]) {
		$fejl++;
		echo "FEJL: kontonr '{$proeve[0]}' gav '$faktisk', ventede '{$proeve[1]}'\n";
	}
}

# Every new text must be registered, or findtekst() falls back to the literal in the code and the
# text cannot be translated.
$csv = __DIR__ . '/../importfiler/tekster.csv';
$linjer = file($csv, FILE_IGNORE_NEW_LINES);
$fundet = array();
foreach ($linjer as $linje) {
	$dele = explode("\t", $linje);
	if (count($dele) < 2) continue;
	if (preg_match('/^[0-9]+$/', $dele[0])) $fundet[$dele[0]] = $dele;
}
$ventede = array('5410', '5411', '5412', '5413', '5414', '5415', '5416', '5417', '5418', '5419', '5420', '5421', '5422', '5423', '5424', '5425', '5426');
foreach ($ventede as $id) {
	$proever++;
	if (!isset($fundet[$id])) {
		$fejl++;
		echo "FEJL: tekst $id mangler i importfiler/tekster.csv\n";
		continue;
	}
	if (count($fundet[$id]) !== 4) {
		$fejl++;
		echo "FEJL: tekst $id har " . count($fundet[$id]) . " felter, ventede 4\n";
	}
}

if ($fejl) {
	echo "$fejl af $proever prøver fejlede.\n";
	exit(1);
}
echo "OK: $proever prøver.\n";
