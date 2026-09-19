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
		// The $id-to-$kodenr binding lookup.
		//
		// 20260919 CDX/MJ This used to answer from a fixture flag without looking at the query, so
		// a regression that changed either predicate still got the bound row back and the test
		// passed. Raised by CodeRabbit on #598. It now models the data instead: the tenant holds
		// one rate row, $fixture['id'], belonging to currency $fixture['rateRowCurrency'], and the
		// lookup matches only if the request asks for exactly that pair. Rejection is therefore a
		// consequence of the data, not something the fixture asserts directly.
		str_contains($query, 'and gruppe =') => (static function () use ($query, $fixture): array {
			// The binding is only meaningful if it constrains BOTH the row and the currency, so a
			// lookup that has lost either predicate fails the run rather than being answered.
			if (!preg_match("/\bid = '([^']*)'/", $query, $mId)
				|| !preg_match("/\bgruppe = '([^']*)'/", $query, $mGruppe)) {
				throw new RuntimeException('Binding lookup lost a predicate: ' . $query);
			}
			$matches = $mId[1] === (string)$fixture['id']
				&& $mGruppe[1] === (string)$fixture['rateRowCurrency'];
			return $matches ? [['id' => $fixture['id']]] : [];
		})(),
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
//
// 20260919 CDX/MJ The span used to start at the POST dispatch, which left the $id/$kodenr
// binding above it unexecuted - so the IDOR fix this PR exists for was the one thing the test
// could not see, and a regression that updated another currency's row by $id alone would still
// have passed. The span now starts at the parameter read, and $_GET drives it instead of $id
// and $kodenr being assigned directly. Raised by CodeRabbit on #598.
$source = file_get_contents(__DIR__ . '/../../systemdata/valutakort.php');
$start = strpos($source, "\$kodenr = ifset(\$_GET, 'kodenr');");
$end = strpos($source, 'if ($kodenr < 0) $bredde', $start === false ? 0 : $start);
if ($start === false || $end === false) {
	throw new RuntimeException('Currency POST controller boundaries not found');
}
$controller = substr($source, $start, $end - $start);
// Guard the span: if the binding ever moves out of it, these tests would silently stop covering
// the thing they were added for.
foreach (["\$id     = (int) ifset(\$_GET, 'id');", '$bundet', "if (isset(\$_POST['submit'])"] as $needle) {
	if (!str_contains($controller, $needle)) {
		throw new RuntimeException("Extracted controller no longer contains: $needle");
	}
}

$cases = [
	'insert rate without posting' => ['button' => 'submit_uden_bogf', 'id' => 0, 'oldRate' => 700, 'expectedRateWrite' => 'insert', 'expectedPostings' => 0],
	'update rate without posting' => ['button' => 'submit_uden_bogf', 'id' => 7, 'oldRate' => 700, 'expectedRateWrite' => 'update', 'expectedPostings' => 0],
	'first rate via ordinary save' => ['button' => 'submit', 'id' => 0, 'oldRate' => 0, 'expectedRateWrite' => 'insert', 'expectedPostings' => 0],
	'ordinary adjustment still posts' => ['button' => 'submit', 'id' => 7, 'oldRate' => 700, 'expectedRateWrite' => 'update', 'expectedPostings' => 2],
	'rejected date cannot recalculate' => ['button' => 'submit', 'id' => 0, 'oldRate' => 700, 'lastPosting' => '2027-01-01', 'expectedRateWrite' => null, 'expectedPostings' => 0],
	'invalid account cannot recalculate' => ['button' => 'submit', 'id' => 0, 'oldRate' => 700, 'validAccount' => false, 'expectedRateWrite' => null, 'expectedPostings' => 0],

	// The IDOR the binding exists to stop: an $id from one currency paired with another
	// currency's $kodenr. $id must be discarded, so the write degrades to an insert against the
	// currency actually being edited rather than updating the other currency's rate row.
	'id from another currency is discarded' => ['button' => 'submit_uden_bogf', 'id' => 7, 'kodenr' => 1,
		'oldRate' => 700, 'expectedRateWrite' => 'insert', 'expectedPostings' => 0, 'forbidTouchingId' => 7],
	// No $kodenr at all skips the lookup, so "no currency given" must not mean "no check".
	'id without a currency is discarded' => ['button' => 'submit_uden_bogf', 'id' => 7, 'kodenr' => null,
		'oldRate' => 700, 'expectedRateWrite' => 'insert', 'expectedPostings' => 0, 'forbidTouchingId' => 7],
	// And the posting path must be protected the same way, not just the rate-only one.
	'id from another currency cannot be posted against' => ['button' => 'submit', 'id' => 7, 'kodenr' => 1,
		'oldRate' => 700, 'expectedRateWrite' => 'insert', 'expectedPostings' => 2, 'forbidTouchingId' => 7]
];
foreach ($cases as $name => $case) {
	// rateRowCurrency is the currency the stored rate row actually belongs to. A case that pairs a
	// different kodenr with it is the cross-currency request the binding has to reject.
	$fixture = $case + ['lastPosting' => '2026-01-01', 'validAccount' => true, 'rateRowCurrency' => 2, 'kodenr' => 2];
	$writes = $transactions = [];
	$recalculations = 0;
	$_POST = [$case['button'] => 'save', 'dato' => '14-09-2026', 'kurs' => '750,00', 'valuta' => 'USD', 'beskrivelse' => 'US dollar', 'difkto' => '8040'];
	// $id and $kodenr are no longer set here: the controller reads them from $_GET and binds them
	// itself, which is the behaviour under test.
	$_GET = ['id' => (string)$case['id']];
	if ($fixture['kodenr'] !== null) {
		$_GET['kodenr'] = (string)$fixture['kodenr'];
	}
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
	// An update must name the row it was bound to, not merely start with "update valuta" - a
	// regression that updated a different currency's row would otherwise pass on the prefix alone.
	$updateTargetsBoundRow = !$rateWrites || $case['expectedRateWrite'] !== 'update'
		|| str_contains($rateWrites[0], "where id = '" . $case['id'] . "'");
	// For a rejected $id, no statement anywhere may mention it.
	$leaksForbiddenId = isset($case['forbidTouchingId'])
		&& array_filter($writes, fn($q) => str_contains($q, "'" . $case['forbidTouchingId'] . "'"));

	if (count($postings) !== $case['expectedPostings'] || $recalculations !== (int)$shouldPost
		|| (!$shouldPost && ($accounts || $transactions))
		|| count($rateWrites) !== (int)($case['expectedRateWrite'] !== null)
		|| ($rateWrites && !str_starts_with($rateWrites[0], $case['expectedRateWrite']))
		|| !$updateTargetsBoundRow
		|| $leaksForbiddenId
		|| ($shouldPost && $transactions !== ['begin', 'commit'])) {
		throw new RuntimeException('FAIL: ' . $name . ': ' . json_encode([$writes, $transactions, $recalculations]));
	}
	echo "PASS: $name\n";
}
echo count($cases) . " currency POST scenarios passed with warnings enabled.\n";
