<?php
// tests/characterization/includes/support/run_debitor_bogfor_nested_tx.php
//
// Child-process runner for NestedTransaktionTest (D5 / MB-195).
// Exercises the exact debitor/bogfor.php:54-64 nested transaction sequence
// (outer transaktion('begin') -> bogfor($id, '') -> outer rollback/commit)
// and reports transaction state, depth, $db_modify_fejl, and committed rows.
//
// History:
// 20261127 CL/HUG Created for D5 / MB-195 nested transaktion() regression test.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_debitor_bogfor_nested_tx.php <refused|outer_rollback> <ordre_id> <tenant_db>\n");
    exit(2);
}

$scenario = $argv[1];
$ordreId = (int)$argv[2];
$tenantDb = $argv[3];

require __DIR__ . '/../../support/bootstrap_ordrefunc.php';
error_reporting(E_ALL);

$tempDir = dirname(__DIR__, 4) . '/temp/' . $db;
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

$GLOBALS['db_transaktion_depth'] = 0;
$GLOBALS['db_modify_fejl'] = false;

// Exact debitor/bogfor.php:54-64 outer transaction pattern, plus a pre-bogfor write
// (analogous to settlePOS.php writing settlement data before calling bogfor($id, '')).
transaktion('begin');
db_modify("UPDATE ordrer SET ref = 'outer-tx-marker' WHERE id = '$ordreId'", __FILE__ . ' linje ' . __LINE__);
$svar = bogfor($ordreId, '');

$afterBogforTxStatus = pg_transaction_status($connection);
$afterBogforDepth = (int)($GLOBALS['db_transaktion_depth'] ?? 0);
$afterBogforModifyFejl = !empty($GLOBALS['db_modify_fejl']);
$afterBogforTxRow = db_fetch_array(db_select("SELECT count(*) AS cnt FROM transaktioner WHERE ordre_id = '$ordreId'", __FILE__ . ' linje ' . __LINE__));
$afterBogforOpRow = db_fetch_array(db_select("SELECT count(*) AS cnt FROM openpost WHERE refnr = '$ordreId'", __FILE__ . ' linje ' . __LINE__));

if ($scenario === 'outer_rollback' || ($svar && $svar !== 'OK')) {
    transaktion('rollback');
} else {
    transaktion('commit');
}

$finalTxStatus = pg_transaction_status($connection);
$finalDepth = (int)($GLOBALS['db_transaktion_depth'] ?? 0);

$txRow = db_fetch_array(db_select("SELECT count(*) AS cnt FROM transaktioner WHERE ordre_id = '$ordreId'", __FILE__ . ' linje ' . __LINE__));
$opRow = db_fetch_array(db_select("SELECT count(*) AS cnt FROM openpost WHERE refnr = '$ordreId'", __FILE__ . ' linje ' . __LINE__));
$ordRow = db_fetch_array(db_select("SELECT status, ref FROM ordrer WHERE id = '$ordreId'", __FILE__ . ' linje ' . __LINE__));

$out = [
    'svar' => $svar,
    'after_bogfor_tx_status' => $afterBogforTxStatus,
    'after_bogfor_depth' => $afterBogforDepth,
    'after_bogfor_modify_fejl' => $afterBogforModifyFejl,
    'after_bogfor_tx_count' => $afterBogforTxRow ? (int)$afterBogforTxRow['cnt'] : 0,
    'after_bogfor_openpost_count' => $afterBogforOpRow ? (int)$afterBogforOpRow['cnt'] : 0,
    'final_tx_status' => $finalTxStatus,
    'final_depth' => $finalDepth,
    'tx_count' => $txRow ? (int)$txRow['cnt'] : 0,
    'openpost_count' => $opRow ? (int)$opRow['cnt'] : 0,
    'order_status' => $ordRow ? (int)$ordRow['status'] : null,
    'order_ref' => $ordRow ? (string)$ordRow['ref'] : null,
];

ob_end_clean();
fwrite(STDOUT, json_encode($out) . "\n");
