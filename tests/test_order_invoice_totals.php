<?php
// 20260920 CDX/LH Compare API preflight totals with the actual momsupdat calculation and reject mismatches.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
require_once(__DIR__ . '/../includes/orderInvoiceTotals.php');
function db_select($sql, $location = '') {
    return new ArrayIterator(stripos($sql, 'from ordrer ') !== false ? array($GLOBALS['testHeader']) : $GLOBALS['testLines']);
}
function db_fetch_array($result) {
    if (!$result->valid()) return false;
    $row = $result->current(); $result->next(); return $row;
}
function db_modify($sql, $location = '') { $GLOBALS['referenceWrites'][] = $sql; }
function checkInvoiceTotals($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
$source = file_get_contents(__DIR__ . '/../includes/ordrefunc.php');
$start = strpos($source, 'function momsupdat(');
$end = strpos($source, '\nfunction batch_salg(', $start);
if ($end === false) $end = strpos($source, "\nfunction batch_salg(", $start);
$reference = substr($source, $start, $end - $start);
$reference = str_replace('function momsupdat(', 'function referenceInvoiceTotals(', $reference);
$reference = str_replace('return ("OK");', "return array('net' => \$sum, 'vat' => \$moms);", $reference);
eval($reference);
$api = file_get_contents(__DIR__ . '/../api/rest_api.php');
$start = strpos($api, '\t$qtxt="select betalingsbet', strpos($api, 'function fakturer_ordre('));
if ($start === false) $start = strpos($api, "\t\$qtxt=\"select betalingsbet", strpos($api, 'function fakturer_ordre('));
$end = strpos($api, "\n\ttransaktion('begin');", $start);
$preflight = substr($api, $start, $end - $start);
// __DIR__ in the extracted function normally names api/, one level below the repo root just like tests/.
$defaults = array('id'=>1,'pris'=>100,'rabat'=>0,'antal'=>1,'rabatart'=>'','procent'=>'100','momssats'=>25,'momsfri'=>'','omvbet'=>'','samlevare'=>'','saet'=>0,'vare_id'=>1,'bogf_konto'=>1200);
$cases = array(
    'normal'=>array(array(array('pris'=>333.33,'antal'=>3)),999.99,250,'DO'),
    'amount discount and participation'=>array(array(array('antal'=>2,'rabat'=>10,'rabatart'=>'amount','procent'=>50)),90,22.5,'DO'),
    'credit reversal'=>array(array(array('antal'=>-2,'rabat'=>10,'rabatart'=>'amount','procent'=>50)),-90,-22.5,'DK'),
    'reverse charge'=>array(array(array('omvbet'=>'on')),100,0,'DO'),
    'tax exemption'=>array(array(array('momsfri'=>'on')),100,0,'DK'),
    'zero participation'=>array(array(array('procent'=>'0')),0,0,'DO'),
    'mixed rates'=>array(array(array(),array('momssats'=>10)),200,35,'DO'),
    'bundle correction'=>array(array(array('pris'=>-0.01,'samlevare'=>'on','saet'=>1),array()),99.99,25,'DO'),
    'two revenue groups'=>array(array(array('pris'=>1000.02),array('pris'=>1000.02,'bogf_konto'=>1210)),2000.04,500.01,'DO'),
    'POS per-line cents'=>array(array(array('pris'=>0.02),array('pris'=>0.02),array('pris'=>0.02)),0.06,0.03,'PO'),
    'invoice total cents'=>array(array(array('pris'=>0.02),array('pris'=>0.02),array('pris'=>0.02)),0.06,0.02,'DO'),
);
$GLOBALS['db_modify_fejl'] = false;
foreach ($cases as $name => $case) {
    list($lines,$net,$vat,$art) = $case;
    $GLOBALS['testLines'] = array_map(function ($line) use ($defaults) { return array_replace($defaults,$line); },$lines);
    $GLOBALS['testHeader'] = array('art'=>$art,'momssats'=>25,'sum'=>$net,'moms'=>$vat,'betalingsbet'=>'Netto','tidspkt'=>'','felt_1'=>'','felt_2'=>0);
    $GLOBALS['referenceWrites'] = array();
    $actual = orderInvoiceTotals($GLOBALS['testLines'],25,$art);
    $reference = referenceInvoiceTotals(1);
    checkInvoiceTotals(abs($actual['net']-$net)<0.00001 && abs($actual['vat']-$vat)<0.00001, "$name computes expected amounts");
    checkInvoiceTotals(abs($actual['net']-$reference['net'])<0.00001 && abs($actual['vat']-$reference['vat'])<0.00001, "$name agrees with the production momsupdat formula");
    $saldi_id = 1;
    $pos_betaling = null; // This fixture exercises invoice pricing before any payment handling.
    $log = fopen('php://memory','w');
    $result = eval($preflight);
    checkInvoiceTotals($result === null, "$name passes the actual API preflight");
    fclose($log);
    $GLOBALS['testHeader']['sum'] += 1;
    $log = fopen('php://memory','w');
    $result = eval($preflight);
    checkInvoiceTotals(is_string($result) && strpos($result,'Error in amount') === 0, "$name still rejects a mismatched declared invoice total");
}
