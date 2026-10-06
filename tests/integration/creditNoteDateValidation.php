<?php
// 20260914 CDX/LH Exercise credit-note classification and the actual approval/delivery/posting guards.
// Run: php tests/integration/creditNoteDateValidation.php
require_once __DIR__ . '/../../includes/ordrefunc.php';
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

/** @return object{rows: array<int, array{vare_id: int, antal: int}>} */
function db_select($query, $source) {
	if ($query !== "select vare_id, antal from ordrelinjer where ordre_id = '42' and antal != '0' order by saet") {
		throw new RuntimeException('Unexpected classification query: ' . $query);
	}
	return (object)['rows' => array_values(array_filter($GLOBALS['orderLines'], fn($r) => $r['antal'] !== 0))];
}

/** @return array{vare_id: int, antal: int}|false */
function db_fetch_array($result) {
	return array_shift($result->rows) ?? false;
}

$root = dirname(__DIR__, 2);
$guards = [];
foreach (['debitor/ordre.php', 'finans/ordre.php'] as $file) {
	$source = file_get_contents($root . '/' . $file);
	if (!preg_match('/elseif\s*\((delivery_date_before_order_date\([^\n]+)\)\s*\{/', $source, $match)) {
		throw new RuntimeException('Approval guard missing in ' . $file);
	}
	$guards[$file] = $match[1];
}
$source = file_get_contents($root . '/includes/ordrefunc.php');
if (!preg_match('/if \((!\$hurtigfakt && delivery_date_before_order_date\([^\n]+)\)\s*\{/', $source, $delivery)
	|| !preg_match('/if \((delivery_date_before_order_date\(\$dan_kn[^\n]+)\)\s*\{/', $source, $posting)) {
	throw new RuntimeException('Delivery/posting guard missing');
}
$guards['levering'] = $delivery[1];
$guards['bogfor'] = $posting[1];

$cases = [
	['DK', [-2], '2026-05-01', false],
	['DO', [-2], '2026-05-01', false],
	['DO', [-2, -1], '2026-05-01', false],
	['DO', [2], '2026-05-01', true],
	['DO', [-2, 1], '2026-05-01', true],
	['DO', [0], '2026-05-01', true],
	['DO', [], '2026-05-01', true],
	['DO', [2], '2026-09-14', false],
	['DK', [-2], '', true],
	['DO', [-2], '', true],
	['KO', [-2], '2026-05-01', true]
];
$count = 0;
foreach ($cases as [$art, $quantities, $levdate, $invalid]) {
	$orderLines = array_map(fn($q) => ['vare_id' => 1, 'antal' => $q], $quantities);
	$id = 42;
	$ordredate = '2026-09-14';
	$hurtigfakt = false;
	$r = ['levdate' => $levdate, 'ordredate' => $ordredate];
	$dan_kn = order_becomes_credit_note($id, $art);
	$effektiv_art = $dan_kn ? 'DK' : $art;
	foreach ($guards as $name => $expression) {
		if (eval('return ' . $expression . ';') !== $invalid) {
			throw new RuntimeException("Unexpected $name result for $art, " . json_encode([$quantities, $levdate]));
		}
		$count++;
	}
}
echo "$count approval/delivery/posting date checks passed with warnings enabled.\n";
