<?php
// 20260916 CDX/LH Exercise real bogfor() early-failure transaction ownership.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$source = file_get_contents(__DIR__ . '/../includes/ordrefunc.php');
$start = strpos($source, 'function bogfor(');
$end = strpos($source, "\nfunction ", $start + 10);
eval(substr($source, $start, $end - $start));
function db_select($sql, $location) { return true; }
function db_fetch_array($result) {
    return array_merge(array_fill_keys(['konto_id','ordredate','levdate','fakturadate','nextfakt','kred_ord_id','valuta','afd','fakturanr','procenttillag','momssats','projekt','felt_2','felt_4','felt_5'], 0), ['art'=>'DO','status'=>3,'felt_2'=>'','felt_4'=>'','felt_5'=>'']);
}
function transaktion($action) { $GLOBALS['transactions'][] = $action; }
$baseCurrency = 'DKK';
foreach ([false, true] as $owned) {
    $transactions = [];
    $result = bogfor(17, true, false, $owned);
    if ($result !== 'invoice allready created for order id 17') throw new RuntimeException('Expected posting refusal');
    if ($transactions !== ($owned ? [] : ['begin','rollback'])) throw new RuntimeException('Posting changed caller-owned transaction');
}
echo "PASS: default posting rolls back; imported posting leaves transaction control to caller\n";
