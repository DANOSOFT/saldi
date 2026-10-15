<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_payment_settlement.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Race real web settlement writers against app registration.
// 20261001 CDX/LH Exercise the active credit-note settlement writer.

require_once __DIR__ . '/../../restapi/support/RestApiEnv.php';
require_once __DIR__ . '/../finans/support/PaymentFixture.php';
require_once __DIR__ . '/../../../restapi/services/legacy/LegacyEngine.php';
require_once __DIR__ . '/../../../includes/db_query.php';
[$script,$database,$mode,$encoded,$barrier]=$argv;
if (!preg_match('/^saldi_apitest_[a-f0-9]{8}$/D',$database) || !str_starts_with($barrier,'/tmp/t2b7-')) {
    throw new InvalidArgumentException('Disposable tenant required');
}
$data=json_decode($encoded,true,512,JSON_THROW_ON_ERROR);
$deadline=microtime(true)+20;
while (!file_exists($barrier)) {
    if (microtime(true)>$deadline) { throw new RuntimeException('Race barrier timed out'); }
    usleep(10000);
}
if ($mode==='dunning') {
    error_reporting(E_ALL);
    date_default_timezone_set('Europe/Copenhagen');
    $_SERVER['REQUEST_URI']='/saldi/debitor/ny_rykker.php';
    $_SERVER['PHP_SELF']=$_SERVER['REQUEST_URI'];
    $_SERVER['REMOTE_ADDR']='127.0.0.1';
    $_SERVER['HTTP_USER_AGENT']='t2b7';
    $_GET=['kontoliste'=>'alle','kontoantal'=>'0'];
    ini_set('session.save_path',sys_get_temp_dir());
    session_id($data['session']);
    chdir(dirname(__DIR__,3).'/debitor');
    require __DIR__.'/../../../debitor/ny_rykker.php';
    exit(0);
}
$connection=RestApiEnv::connect($database);
$_SERVER['REQUEST_URI']='/saldi/includes/udlign_openpost.php';
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$context=new EngineContext($connection,$database,(int)$data['tenant_id'],RestApiEnv::USER,1,'2026-03-15');
LegacyEngine::run($context,static function () use ($mode,$data): void {
    if ($mode==='credit-note') {
        LegacyEngine::load('orders');
        $result=bogfor_nu((int)$data['order'],'webservice');
        if ($result !== 'OK') {
            throw new RuntimeException('Credit-note posting refused: '.$result);
        }
    } elseif ($mode==='equalize' || $mode==='equalize-copy') {
        if ($mode==='equalize') { require_once __DIR__.'/../../../includes/genberegn.php'; }
        else { require_once __DIR__.'/../../../includes/orderFuncIncludes/genberegn.php'; }
        equalizeMatchingRecords();
    } elseif ($mode==='auto') {
        require_once __DIR__.'/../../../includes/autoudlign.php';
        autoudlign((int)$data['customer']);
    } elseif ($mode==='difference') {
        $post_id=array_map('intval',$data['ids']);
        $postantal=count($post_id)-1;
        $udlign=array_fill(0,count($post_id),'on');
        $konto_id=array_fill(0,count($post_id),(int)$data['customer']);
        $faktnr=array_fill(0,count($post_id),'fixture');
        $valuta=array_fill(0,count($post_id),'DKK');
        $x=1;$diff=$dkkdiff=0.01;$maxdiff=1;$basisvaluta='DKK';$basiskurs=100;
        $diffkto=(int)$data['bank'];$diffdato='15-03-2026';$insertInvoiceNumbers=false;
        $regnaar=(int)$data['year'];$sprog_id=0;
        $dato_fra='01-01-2026';$dato_til='31-12-2026';$konto_fra=$konto_til='fixture';
        $retur=$returside='../debitor/rapport.php';$layout='grid';
        require __DIR__.'/../../../includes/alignOpenpostIncludes/doAlign.php';
    } else { throw new InvalidArgumentException('Unknown writer'); }
});
$ids=implode(',',array_map('intval',$data['ids']));
print json_encode(PaymentFixture::query($connection,"SELECT udlign_id,udlignet FROM openpost WHERE id IN ($ids) ORDER BY id"),JSON_THROW_ON_ERROR);
pg_close($connection);
