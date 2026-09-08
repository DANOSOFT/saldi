<?php
// --- tests/test_cash_report_payments.php --- patch 5.0.1 --- 2026.09.07 ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260907 CDX/PHR Regression test for report payment amounts and order assignment.
// 20260908 CDX/LH Exercise report payments with all PHP warnings visible.
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once(__DIR__ . '/../includes/stdFunc/usDecimal.php');
require_once(__DIR__ . '/../debitor/pos_ordre_includes/boxCountMethods/printBoxCount.php');
require_once(__DIR__ . '/../debitor/pos_ordre_includes/boxCountMethods/assignCashReport.php');

function if_isset($value, $default = null) { return isset($value) ? $value : $default; }
function getCountry() { return 'Denmark'; }
function dkdecimal($value, $decimals = 2) { return number_format((float)$value, $decimals, ',', '.'); }
function afrund($value, $decimals) { return round($value, $decimals); }
function db_escape_string($value) { return str_replace("'", "''", $value); }
function db_modify($sql, $location) { $GLOBALS['queries'][] = $sql; }
function checkReport($condition, $message) {
	if (!$condition) throw new RuntimeException($message);
	echo "PASS: $message\n";
}

$baseCurrency = 'DKK';
$_POST = array(
	'byttepenge' => 0, 'tilgang' => 0, 'optalt' => 0, 'omsatning' => 39448.7,
	'udtages' => '0,00', 'kontkonto' => array(),
	'kortnavn' => array('Dankort', 'MasterCard', 'Refund', "Customer's card"),
	'kortsum' => array('26120.4', '1249.500', '-5599.50', '0'),
	'ny_kortsum' => array('31.719,90', '1.249,50', '-5.599,50', '0,00'),
	'kontosum' => '100.25', 'ValutaByttePenge' => array(), 'ValutaTilgang' => array(),
	'ValutaKasseDiff' => array(), 'ValutaUdtages' => array()
);
foreach (array(false, true) as $manual) {
	$queries = array();
	$fp = fopen('php://memory', 'w+');
	$log = fopen('php://memory', 'w+');
	setPrintTxt($fp, $log, 'UTF-8', 'UTF-8', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, array(), array(), $manual, 5515, 2);
	$sql = implode("\n", $queries);
	checkReport(strpos($sql, 'Udtaget fra kasse  2 DKK:') !== false, 'Include the register in report descriptions');
	checkReport(strpos($sql, "'Salg på konto','0','100.25','5515'") !== false, 'Save account sales with decimals');
	if ($manual) {
		checkReport(strpos($sql, "'Dankort(26.120,40)','0','31719.9','5515'") !== false, 'Convert manually entered Danish amount once');
	} else {
		checkReport(strpos($sql, "'Dankort','0','26120.4','5515'") !== false, 'Preserve decimal point in calculated Dankort total');
		checkReport(strpos($sql, "'MasterCard','0','1249.5','5515'") !== false, 'Preserve three-decimal database amounts');
		checkReport(strpos($sql, "'Refund','0','-5599.5','5515'") !== false, 'Preserve negative payment amount');
		checkReport(strpos($sql, "'Customer''s card','0','0','5515'") !== false, 'Escape payment names and preserve zero');
	}
	fclose($fp);
	fclose($log);
}
$queries = array();
assignCashReport(array(), 5515);
assignCashReport(array(322628), 0);
checkReport(!$queries, 'Skip empty selection and invalid report number');
assignCashReport(array(322628, '322628', 0, -1), 5515);
checkReport(count($queries) === 1 && strpos($queries[0], 'in (322628)') !== false, 'Assign the exact included orders without duplicates');
checkReport(strpos($queries[0], 'coalesce(report_number, 0) = 0') !== false, 'Preserve existing report assignments');

$baseCurrency = 'EUR';
$_POST['ValutaByttePenge'] = array('12.25');
$_POST['ValutaTilgang'] = array('1250.5');
$_POST['ValutaKasseDiff'] = array('-2.5');
$_POST['ValutaUdtages'] = array('1.000,25');
$queries = array();
$fp = fopen('php://memory', 'w+');
$log = fopen('php://memory', 'w+');
setPrintTxt($fp, $log, 'UTF-8', 'UTF-8', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, array('USD'), array('1.262,75'), false, 5515, 2, 3, 4);
$sql = implode("\n", $queries);
checkReport(strpos($sql, "'10 cent','0','3'") !== false && strpos($sql, "'20 cent','0','4'") !== false, 'Save supplied small EUR denominations without undefined fields');
checkReport(strpos($sql, "'Dagens tilgang USD:','0','1250.5'") !== false, 'Preserve calculated foreign currency approach');
checkReport(strpos($sql, "'Forventet beholdning USD:','0','1262.75'") !== false, 'Add foreign balances without Danish reparsing');
checkReport(strpos($sql, "'Difference USD:','0','-2.5'") !== false, 'Preserve calculated foreign currency difference');
checkReport(strpos($sql, "'Optalt beholdning USD:','0','1262.75'") !== false && strpos($sql, "'Udtaget fra kasse 2  USD:','0','1000.25'") !== false, 'Parse manually counted and withdrawn foreign currency as Danish input');
fclose($fp);
fclose($log);
