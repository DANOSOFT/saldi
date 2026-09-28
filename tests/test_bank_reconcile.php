<?php
// 20260916 CDX/LH Reproduce decimal parsing, skipped-line alignment and unmatched ledger rendering.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$source = file_get_contents(__DIR__ . '/../finans/bankReconcile.php');
// Load real functions while bypassing the authenticated page controller.
$functions = substr($source, strpos($source, 'function reconcile('));
eval(substr($functions, 0, strpos($functions, 'print "<script')));
function usdecimal($value) { return (float)str_replace(',', '.', str_replace('.', '', $value)); }
function if_isset($array, $default = null, $key = null) { return $array[$key] ?? $default; }
function dkdecimal($value) { return number_format((float)$value, 2, ',', '.'); }
function afrund($value, $precision) { return round($value, $precision); }
function db_select($query, $source) {
    if (strpos($query, "art = 'RA'") !== false) $rows = [['box1'=>1,'box2'=>2026,'box3'=>12,'box4'=>2026,'kodenr'=>1]];
    elseif (strpos($query, 'select * FROM transaktioner') !== false) $rows = [
        ['id'=>1,'pos'=>1,'transdate'=>'2026-09-16','beskrivelse'=>'Matched ledger','debet'=>123.45,'kredit'=>0],
        ['id'=>2,'pos'=>2,'transdate'=>'2026-09-16','beskrivelse'=>'Extra ledger row','debet'=>10,'kredit'=>0]
    ];
    elseif (strpos($query, 'SELECT primo') !== false) $rows = [['primo'=>1000]];
    else $rows = [];
    return (object)['rows'=>$rows];
}
function db_fetch_array($result) { return array_shift($result->rows); }
function db_modify($query, $source) { $GLOBALS['writes'][] = $query; }
foreach (['comma', 'dot', 'integer'] as $format) {
    $lines = file(__DIR__ . '/fixtures/bank-reconcile/' . $format . '.csv', FILE_IGNORE_NEW_LINES);
    $rows = bankReconcileRows($lines, ';', ['dato', 'beskrivelse', 'belob', 'saldo']);
    if (array_keys($rows) !== [1, 3]) throw new RuntimeException('File-line indices drifted');
    $amount = $format === 'integer' ? 123.0 : 123.45;
    if ($rows[1]['amount'] !== $amount || $rows[1]['saldo'] !== 1000.0 + $amount) throw new RuntimeException("Incorrect $format amount or balance");
    echo "PASS: $format amounts, balances and skipped lines\n";
}
// eval makes __DIR__ point at tests; use a disposable sibling of that directory.
$db = 'bank_regression_' . bin2hex(random_bytes(5));
$directory = __DIR__ . '/../temp/' . $db;
mkdir($directory, 0700, true);
$charset = 'UTF-8';
$bgcolor = 'white'; $bgcolor5 = 'grey'; $writes = [];
try {
    copy(__DIR__ . '/fixtures/bank-reconcile/comma.csv', "$directory/bank.csv");
    if (bankReconcilePath('../../bank.csv') !== realpath($directory) . '/bank.csv') throw new RuntimeException('Unconfined path');
    symlink(__FILE__, "$directory/link.csv");
    try { bankReconcilePath('link.csv'); throw new RuntimeException('Symlink accepted'); }
    catch (InvalidArgumentException $expected) {}
    $_GET = ['up'=>'1 OR 1=1', 'byt'=>'2 OR 1=1'];
    ob_start();
    reconcile('bank.csv', ';', ['dato','beskrivelse','belob','saldo'], 3, '5820 OR 1=1', false);
    $html = ob_get_clean();
    if (strpos($html, 'Extra ledger row') === false) throw new RuntimeException('Unmatched ledger row disappeared');
    foreach ($writes as $query) if (strpos($query, 'OR 1=1') !== false) throw new RuntimeException('Request SQL was not cast');
    echo "PASS: unmatched ledger row, confined files and numeric SQL inputs\n";
} finally {
    foreach (glob("$directory/*") as $file) unlink($file);
    rmdir($directory);
}
