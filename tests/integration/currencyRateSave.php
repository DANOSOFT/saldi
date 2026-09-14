<?php
// 20260914 CDX/LH Exercise the real currency POST controller with observable database boundaries.
// Run: php tests/integration/currencyRateSave.php

require_once __DIR__ . '/../../includes/std_func.php';

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

/** @return object{rows: array<int, array<string, mixed>>} */
function db_select($query, $source) {
	global $fixture;
	$rows = match (true) {
		str_contains($query, 'max(transdate)') => [['transdate' => $fixture['lastPosting']]],
		str_contains($query, "kontotype = 'D'") => $fixture['validAccount'] ? [['id' => 1]] : [],
		str_contains($query, 'select id,kurs from valuta') => [],
		str_contains($query, 'select kurs from valuta') => [['kurs' => $fixture['oldRate']]],
		str_contains($query, 'select id,kontonr,saldo from kontoplan') => [['id' => 2, 'kontonr' => 58103, 'saldo' => 700]],
		str_contains($query, "art = 'DG' or art = 'KG'") => [],
		default => throw new RuntimeException('Unexpected query: ' . $query)
	};
	return (object)['rows' => $rows];
}

/** @return array<string, mixed>|false */
function db_fetch_array($result) {
	return array_shift($result->rows) ?? false;
}

/** @return void */
function db_modify($query, $source) {
	$GLOBALS['writes'][] = $query;
}

/** @return void */
function transaktion($action) {
	$GLOBALS['transactions'][] = $action;
}

/** @return void */
function genberegn($year) {
	$GLOBALS['recalculations']++;
}

/** @return string */
function findtekst($text, $language) {
	return explode('|', $text, 2)[1] ?? $text;
}

// Execute the page's entire POST controller, including its tail after the rate-write branches.
// SQL calls are observed rather than executed; no account or tenant database is accessed.
$source = file_get_contents(__DIR__ . '/../../systemdata/valutakort.php');
$start = strpos($source, "if (isset(\$_POST['submit'])");
$end = strpos($source, 'if ($kodenr < 0) $bredde', $start);
if ($start === false || $end === false) {
	throw new RuntimeException('Currency POST controller boundaries not found');
}
$controller = substr($source, $start, $end - $start);

$cases = [
	'insert rate without posting' => ['button' => 'submit_uden_bogf', 'id' => 0, 'oldRate' => 700, 'expectedRateWrite' => 'insert', 'expectedPostings' => 0],
	'update rate without posting' => ['button' => 'submit_uden_bogf', 'id' => 7, 'oldRate' => 700, 'expectedRateWrite' => 'update', 'expectedPostings' => 0],
	'first rate via ordinary save' => ['button' => 'submit', 'id' => 0, 'oldRate' => 0, 'expectedRateWrite' => 'insert', 'expectedPostings' => 0],
	'ordinary adjustment still posts' => ['button' => 'submit', 'id' => 7, 'oldRate' => 700, 'expectedRateWrite' => 'update', 'expectedPostings' => 2],
	'rejected date cannot recalculate' => ['button' => 'submit', 'id' => 0, 'oldRate' => 700, 'lastPosting' => '2027-01-01', 'expectedRateWrite' => null, 'expectedPostings' => 0],
	'invalid account cannot recalculate' => ['button' => 'submit', 'id' => 0, 'oldRate' => 700, 'validAccount' => false, 'expectedRateWrite' => null, 'expectedPostings' => 0]
];
foreach ($cases as $name => $case) {
	$fixture = $case + ['lastPosting' => '2026-01-01', 'validAccount' => true];
	$writes = $transactions = [];
	$recalculations = 0;
	$_POST = [$case['button'] => 'save', 'dato' => '14-09-2026', 'kurs' => '750,00', 'valuta' => 'USD', 'beskrivelse' => 'US dollar', 'difkto' => '8040'];
	$id = $case['id'];
	$kodenr = 2;
	$regnaar = 1;
	$sprog_id = 1;
	ob_start();
	try {
		eval($controller);
	} finally {
		ob_end_clean();
	}
	$postings = array_filter($writes, fn($q) => str_starts_with($q, 'insert into transaktioner'));
	$accounts = array_filter($writes, fn($q) => str_starts_with($q, 'update kontoplan'));
	$rateWrites = array_values(array_filter($writes, fn($q) => preg_match('/^(insert into|update) valuta\b/', $q)));
	$shouldPost = $case['expectedPostings'] > 0;
	if (count($postings) !== $case['expectedPostings'] || $recalculations !== (int)$shouldPost
		|| (!$shouldPost && ($accounts || $transactions))
		|| count($rateWrites) !== (int)($case['expectedRateWrite'] !== null)
		|| ($rateWrites && !str_starts_with($rateWrites[0], $case['expectedRateWrite']))
		|| ($shouldPost && $transactions !== ['begin', 'commit'])) {
		throw new RuntimeException('FAIL: ' . $name . ': ' . json_encode([$writes, $transactions, $recalculations]));
	}
	echo "PASS: $name\n";
}
echo "6 currency POST scenarios passed with warnings enabled.\n";
