<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_legacy_stock.php --- ver 5.0.0 --- 2026.10.06
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

// CLI-only runner; receives disposable database identities, never provider credentials.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/CharacterizationEnv.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../restapi/services/legacy/LegacyEngine.php';
require_once __DIR__ . '/../../../includes/lager/stockMovement.php';
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$_SERVER['REQUEST_URI'] = '/saldi/debitor/levering.php';
$conn = CharacterizationEnv::connect($input['db']);
$context = new EngineContext($conn,$input['db'],(int)$input['db_id'],'chartest',1,'2026-10-06');
$result = LegacyEngine::run($context, static function () use ($input,$conn) {
    LegacyEngine::load('orders');
    $GLOBALS['art'] = 'DO';
    $GLOBALS['fakturadate'] = '2026-10-06';
    $GLOBALS['levdate'] = '2026-10-06';
    $GLOBALS['lev_nr'] = 1;
    if (!empty($input['caller_transaction'])) {
        transaktion('begin');
    }
    $line = CharacterizationEnv::one($conn,'SELECT l.*,v.gruppe,v.beholdning,v.samlevare FROM ordrelinjer l JOIN varer v ON v.id=l.vare_id WHERE l.id=$1',[$input['line']]);
    if (!empty($input['barrier'])) {
        file_put_contents($input['barrier'].'.ready', (string)pg_get_pid($conn));
        $deadline = microtime(true)+15;
        while (!is_file($input['barrier'].'.release')) {
            if (microtime(true)>$deadline) {
                throw new RuntimeException('Barrier timeout');
            }
            usleep(10000);
        }
    }
    $qty = (float)($input['quantity'] ?? $line['leveres']);
    $mode = $input['mode'] ?? 'sale';
    if ($mode === 'line') {
        $result = linjeopdat((int)$line['ordre_id'],(int)$line['gruppe'],(int)$line['id'],(float)$line['beholdning']-$qty,
            (int)$line['vare_id'],$qty,(float)$line['pris'],(float)$line['pris'],0,$line['samlevare'],1,1,'',0,0,(int)$line['variant_id'],(int)$line['lager'],true,true,static function (): void {});
    } elseif ($mode === 'warehouse') {
        $result = lagerstatus((int)$line['vare_id'],(int)$line['variant_id'],(int)$line['lager'],$qty);
    } elseif ($mode === 'reserved') {
        $result = batch_salg_lev((int)$line['ordre_id'],'2026-10-06','2026-10-06',0,(int)$line['vare_id'],$qty,8,8,(int)$line['id'],1,1,'',(int)$line['lager'],(int)$line['variant_id'],true);
    } else {
        // Default web signature: no opt-in error return or shop deferral.
        $result = levering((int)$line['ordre_id'],'',null,true);
    }
    $insideTransaction = pg_transaction_status($conn);
    if (!empty($input['after_barrier'])) {
        file_put_contents($input['after_barrier'].'.ready', (string)pg_get_pid($conn));
        $deadline = microtime(true)+15;
        while (!is_file($input['after_barrier'].'.release')) {
            if (microtime(true)>$deadline) {
                throw new RuntimeException('After-write barrier timeout');
            }
            usleep(10000);
        }
    }
    if (!empty($input['caller_transaction'])) {
        transaktion('commit');
    }
    return ['result'=>$result,'transaction'=>pg_transaction_status($conn),'inside_transaction'=>$insideTransaction];
});
echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
