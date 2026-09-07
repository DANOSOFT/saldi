<?php
// --- tests/test_cash_count_snapshot.php --- patch 5.0.1 --- 2026.09.07 ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
require_once(__DIR__ . '/../debitor/pos_ordre_includes/boxCountMethods/cashCountSnapshot.php');

function checkCashCount($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}

// Report 5515: the last Dankort sale was absent from the calculated form.
$before = array('DKK' => array(4482, 3100.05, 0, 1, '16650', 'Dankort', '26120.4', 0));
$after = $before;
$after['DKK'][6] = '31719.9';
checkCashCount(cashCountSignature($before) !== cashCountSignature($after), 'Detect the additional 5599.50 Dankort sale');
checkCashCount(cashCountSignature($after) === cashCountSignature($after), 'Accept unchanged balances');
$counted = $after;
$counted['DKK'][2] = 100;
$counted['DKK'][6] = '31719.900';
checkCashCount(cashCountSignature($after) === cashCountSignature($counted), 'Ignore counted difference and numeric formatting');
foreach (array(0, 1, 7) as $field) {
	$changed = $after;
	$changed['DKK'][$field] += 100;
	checkCashCount(cashCountSignature($after) !== cashCountSignature($changed), "Detect changed balance field $field");
}
$foreign = $after + array('EUR' => array(0, 100, 0, 0, '', '', '', 0));
$changed = $foreign;
$changed['EUR'][1] = 150;
checkCashCount(cashCountSignature($foreign) !== cashCountSignature($changed), 'Detect foreign currency sales');
checkCashCount(cashCountSignature($foreign) === cashCountSignature(array_reverse($foreign, true)), 'Ignore currency iteration order');
checkCashCount(!cashCountIsCurrent(2, 'DKK', null), 'Require recalculation for forms opened before the update');

// Exercise the actual approval path: stale forms must return to the count before posting.
require_once(__DIR__ . '/../debitor/pos_ordre_includes/boxCountMethods/cashBalance.php');
function if_isset($value, $default = null) { return isset($value) ? $value : $default; }
function db_select($sql, $location) { return $sql; }
function db_fetch_array($query) {
	return array('box1' => 0, 'box2' => 'on', 'box3' => "localhost\tlocalhost", 'box6' => 0, 'box12' => '', 'var_value' => '');
}
function db_modify($sql, $location) { throw new RuntimeException('Unexpected database write'); }
function posbogfor($register, $start, $report) { throw new RuntimeException('Unexpected posting'); }
function tekstboks($text) { return $text; }
class CashCountRecalculated extends RuntimeException {}
function kasseoptalling(...$args) { throw new CashCountRecalculated(); }
$baseCurrency = 'DKK';
$regnaar = 9;
$_POST = array();
$returnedToCount = false;
ob_start();
try {
	cashBalance(2, 4482, 1, implode(chr(9), array_fill(0, 18, 0)));
} catch (CashCountRecalculated $e) {
	$returnedToCount = true;
}
$output = ob_get_clean();
checkCashCount($returnedToCount && isset($_POST['calculate']), 'Return stale approval to recalculation before posting');
checkCashCount(strpos($output, 'godkend igen') !== false, 'Explain that updated amounts need approval');
