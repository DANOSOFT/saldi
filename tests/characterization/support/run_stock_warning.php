<?php
// 20261113 CDX/NTR SD-599 Run the real stock-warning functions against an isolated test tenant.

$tenantDb = $argv[1];
$itemId = (int)$argv[2];
$action = $argv[3];
$quantity = (float)$argv[4];
$entry = $argv[5];

if ($entry === 'module') {
    ob_start();
    require dirname(__DIR__, 3) . '/includes/connect.php';
    require dirname(__DIR__, 3) . '/includes/std_func.php';
    $db = $tenantDb;
    $connection = db_connect($sqhost, $squser, $sqpass, $db);
    $brugernavn = 'chartest';
    require_once dirname(__DIR__, 3) . '/includes/orderFuncIncludes/stockWarning.php';
} else {
    require __DIR__ . '/bootstrap_ordrefunc.php';
}
if ($action === 'log') {
    $result = [
        'accepted' => log_stock_warning(599, $itemId, "  Kunden siger 'ja' til æøå  "),
        'blank' => log_stock_warning(599, $itemId, '   '),
    ];
} else {
    $result = check_stock_warning($itemId, $quantity);
}
if ($entry === 'module') {
    $result['core_loaded'] = function_exists('bogfor');
}
ob_end_clean();
echo json_encode($result);
