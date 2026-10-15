<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_goods_receipt.php --- ver 5.0.0 --- 2026.10.06
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


/** Child worker for actual independent receipt/order-save transactions and local transport evidence. */
error_reporting(E_ALL);
$_SERVER['REQUEST_URI'] = '/saldi/kreditor/modtag.php';
chdir(__DIR__ . '/../../../kreditor');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$payload = json_decode($argv[1],true,512,JSON_THROW_ON_ERROR);
$GLOBALS['receiptSyncCalls'] = [];
function shopApiRequest($endpoint, array $params = [], $log = null, array $options = []): array
{
    $GLOBALS['receiptSyncCalls'][] = ['transaction'=>pg_transaction_status($GLOBALS['connection']),'params'=>$params];
    return ['ok'=>!($GLOBALS['receiptSyncFail'] ?? false)];
}
require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../restapi/services/GoodsReceiptService.php';
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../kreditor/orderIncludes/lockOrderForSave.php';
$GLOBALS['receiptSyncFail'] = $payload['sync_fail'] ?? false;
$conn = RestApiEnv::connect($payload['db']);
$context = new EngineContext($conn,$payload['db'],(int)$payload['tenant_id'],RestApiEnv::user(),(int)$payload['user_id'],'2026-10-06');
$service = new GoodsReceiptService($context,Subject::build('account',(int)$payload['user_id']));
$store = new MobileSchema($conn);
try {
    if (($payload['mode'] ?? 'receive') === 'save') {
        $store->execute('BEGIN');
        $result = LegacyEngine::run($context, static function () use ($payload,$store): array {
            $id = (int)$payload['body']['order_id'];
            $line = (int)$payload['body']['lines'][0]['line_id'];
            if (!lockCreditorOrderForSave($id,1,null,false,[$line])) {
                throw new RuntimeException('Web order save refused');
            }
            $received = $store->rows('SELECT COALESCE(SUM(antal),0)::text AS n FROM batch_kob WHERE linje_id=' . $line)[0]['n'];
            $quantity = $store->literal($payload['ordered']);
            $store->execute('UPDATE ordrelinjer SET antal=' . $quantity . ',leveret=' . $store->literal($received) . ' WHERE id=' . $line);
            return ['received_at_save'=>$received];
        });
        $store->execute('COMMIT');
    } else {
        $result = $service->receive($payload['body'],$payload['etag']);
    }
    echo json_encode(['result'=>$result,'calls'=>$GLOBALS['receiptSyncCalls'],'transaction'=>pg_transaction_status($conn)],JSON_THROW_ON_ERROR);
} catch (MobileT2Error $error) {
    if (pg_transaction_status($conn) !== PGSQL_TRANSACTION_IDLE) {
        $store->execute('ROLLBACK');
    }
    echo json_encode(['error'=>$error->error,'status'=>$error->status,'details'=>$error->details],JSON_THROW_ON_ERROR);
}
pg_close($conn);
