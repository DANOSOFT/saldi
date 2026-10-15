<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_invoice_credit.php --- ver 5.0.0 --- 2026.10.03
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

require_once __DIR__.'/RestApiEnv.php';
require_once __DIR__.'/../../../includes/db_query.php';
require_once __DIR__.'/../../../includes/std_func.php';
require_once __DIR__.'/../../../includes/openpost/udlignId.php';
require_once __DIR__.'/../../../restapi/services/legacy/LegacyEngine.php';
error_reporting(E_ALL);
$_SERVER['REQUEST_URI']='/saldi/debitor/bogfor.php';
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$input=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR);
$connection=RestApiEnv::connect($input['db']);
$barrier=$input['barrier'];
touch($barrier.'.ready.'.getmypid());
$deadline=microtime(true)+15;
while (!is_file($barrier.'.release')) {
    if (microtime(true)>$deadline) { throw new RuntimeException('Writer barrier timed out'); }
    usleep(10000);
}
if ($input['mode']==='allocate') {
    $ids=[];
    for ($i=0;$i<20;$i++) { $ids[]=udlign_next_id($connection); usleep(10000); }
    echo json_encode(['ids'=>$ids]);
} else {
    $context=new EngineContext($connection,$input['db'],(int)$input['db_id'],'apitest',(int)$input['user_id'],date('Y-m-d'));
    pg_query($connection,'BEGIN');
    try {
        $result=LegacyEngine::run($context,static function () use ($connection,$input): string {
            LegacyEngine::load('orders');
            pg_query($connection,'LOCK TABLE ordrer IN EXCLUSIVE MODE');
            $GLOBALS['reportNumber']=0; $GLOBALS['valuta']='DKK'; $GLOBALS['valutakurs']=100;
            return bogfor((int)$input['order_id'],'on',false,true);
        });
        pg_query($connection,$result==='OK' ? 'COMMIT' : 'ROLLBACK');
        echo json_encode(['result'=>$result]);
    } catch (Throwable $error) { pg_query($connection,'ROLLBACK'); throw $error; }
}
pg_close($connection);
