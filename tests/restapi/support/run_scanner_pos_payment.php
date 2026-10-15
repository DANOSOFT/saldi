<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_scanner_pos_payment.php --- ver 5.0.0 --- 2026.10.05
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

require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../restapi/services/legacy/LegacyEngine.php';
require_once __DIR__ . '/../../../includes/db_query.php';

[$script,$database,$tenantId,$orderId] = $argv;
if (!preg_match('/^saldi_apitest_[a-f0-9]{8}$/D',$database)) {
    throw new InvalidArgumentException('Disposable tenant required');
}
$_SERVER['REQUEST_URI'] = '/saldi/debitor/pos_ordre.php';
$connection = RestApiEnv::connect($database);
$context = new EngineContext($connection,$database,(int)$tenantId,RestApiEnv::USER,1,date('Y-m-d'));
$GLOBALS['valutakurs'] = 100;
$result = LegacyEngine::run($context,static function () use ($orderId) {
    LegacyEngine::load('orders');
    return bogfor_indbetaling((int)$orderId,'webservice');
});
echo json_encode(['result'=>$result],JSON_THROW_ON_ERROR) . "\n";
pg_close($connection);
