<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_modtag_page.php --- ver 5.0.0 --- 2026.10.06
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Pin the default PHP precision for decimal quantity regressions.

/**
 * Injected by includes/online.php in the engine branch below:
 * @var \PgSql\Connection $connection
 */
error_reporting(E_ALL);
ini_set('precision', '14');
date_default_timezone_set('Europe/Copenhagen');
$payload = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$_GET = ['id' => (string)(int)$payload['order_id']];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = '/saldi/kreditor/modtag.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[2]);
chdir(dirname(__DIR__, 3) . '/kreditor');
$GLOBALS['receiptShopCalls'] = [];
/** Transport stub: exercise the real sync_shop_vare payload without contacting a provider. */
function shopApiRequest($endpoint, array $params = [], $log = null, array $options = []): array
{
    $GLOBALS['receiptShopCalls'][] = ['params' => $params,
        'transaction' => pg_transaction_status($GLOBALS['connection'])];
    return ['ok' => true];
}
ob_start();
register_shutdown_function(static function (): void {
    $html = ob_get_clean();
    echo json_encode(['html' => $html, 'calls' => $GLOBALS['receiptShopCalls'],
        'result' => $GLOBALS['receiptEngineResult'] ?? null,
        'engine_html' => $GLOBALS['receiptEngineHtml'] ?? null,
        'transaction' => isset($GLOBALS['connection']) ? pg_transaction_status($GLOBALS['connection']) : null],
        JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
});
if (($payload['mode'] ?? 'page') === 'page') {
    require __DIR__ . '/../../../kreditor/modtag.php';
} else {
    session_start();
    $s_id = session_id();
    require __DIR__ . '/../../../includes/connect.php';
    require __DIR__ . '/../../../includes/online.php';
    require_once __DIR__ . '/../../../includes/kreditor/creditorReceive.php';
    $mode = ifset($payload,'mode','engine');
    $id = (int)$payload['order_id'];
    ob_start();
    try {
        if ($mode !== 'no_transaction') {
            transaktion('begin');
            creditor_receive_query('SELECT id FROM ordrer WHERE id=$1 FOR UPDATE', [$id]);
        }
        $result = creditor_receive($id, ['defer_shop_sync' => true]);
        $beforeCommit = count($GLOBALS['receiptShopCalls']);
        $open = pg_transaction_status($connection);
        transaktion($result['reason'] === null && !ifset($payload,'rollback',false) ? 'commit' : 'rollback');
        if ($result['reason'] === null && !ifset($payload,'rollback',false)) {
            stock_shop_sync($result['sync']);
        }
        $GLOBALS['receiptEngineResult'] = $result + ['calls_before_commit' => $beforeCommit, 'open_transaction' => $open];
    } catch (Throwable $error) {
        $open = pg_transaction_status($connection);
        if ($open !== PGSQL_TRANSACTION_IDLE) {
            transaktion('rollback');
        }
        $GLOBALS['receiptEngineResult'] = ['exception' => get_class($error), 'message' => $error->getMessage(),
            'open_transaction' => $open, 'calls_before_commit' => count($GLOBALS['receiptShopCalls'])];
    } finally {
        $GLOBALS['receiptEngineHtml'] = ob_get_clean();
        echo $GLOBALS['receiptEngineHtml'];
    }
}
