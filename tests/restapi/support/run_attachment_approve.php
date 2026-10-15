<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_attachment_approve.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Allow CLI-only reconciler rendezvous hooks for process-kill regressions.

// CLI-only fault harness. Hooks cannot be selected by an HTTP request or environment flag.
if (PHP_SAPI !== 'cli' || $argc !== 2) { exit(2); }
require_once __DIR__.'/RestApiEnv.php';
require_once __DIR__.'/../../../includes/std_func.php';
require_once __DIR__.'/../../../includes/db_query.php';
require_once __DIR__.'/../../../restapi/services/AttachmentApproveService.php';
require_once __DIR__.'/../../../restapi/core/RequestId.php';
umask(0);
error_reporting(E_ALL);
$args=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR);
$_SERVER['REQUEST_URI']='/saldi/debitor/t3-approve-test.php';
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$connection=RestApiEnv::connect($args['db']);
$db=$args['db']; $db_type='postgresql'; $db_skriv_id=0; $bruger_id=$args['user_id'];
$brugernavn=$args['username']; $revisor=false; $sprog_id=0; $s_id='t3b2b-test';
$mode=$args['mode'] ?? 'approve';
if ($mode === 'return') {
    $moveDoc=$args['path']; $source='kassekladde'; $sourceId=$args['line']; $kladde_id=$args['journal'];
    $fokus='beskrivelse'; $params=''; $docFolder='../bilag'; $bilag=42;
    chdir(dirname(__DIR__,3).'/includes');
    require __DIR__.'/../../../includes/docsIncludes/moveDoc.php';
    exit;
}
if ($mode === 'sync') {
    require_once __DIR__.'/../../../includes/docsIncludes/poolContentHash.php';
    function docPoolLog($message) { error_log((string)$message); }
    // Load the unchanged page function, without executing its rendering/extraction dispatch.
    $source=file_get_contents(__DIR__.'/../../../includes/docsIncludes/docPool.php');
    $start=strpos($source,'function syncPuljeFilesToDatabase(');
    $end=strpos($source,'function checkIfAllPoolFilesAreInDatabase(',$start);
    eval(substr($source,$start,$end-$start));
    chdir(dirname(__DIR__,3).'/debitor');
    syncPuljeFilesToDatabase(dirname(dirname(poolPuljePath($db))),$db);
    echo json_encode(['status'=>200]); exit;
}
$hook=null;
if (isset($args['hook'])) {
    $hook=static function(string $point) use ($args): void {
        if ($point !== $args['hook']) { return; }
        file_put_contents($args['marker'],$point);
        $deadline=microtime(true)+30;
        while (!is_file($args['marker'].'.release')) {
            clearstatcache(true,$args['marker'].'.release');
            if (microtime(true)>$deadline) { throw new RuntimeException('Test rendezvous timed out'); }
            usleep(20000);
        }
    };
}
if ($mode === 'reconcile') {
    echo json_encode(['status'=>200,'state'=>pool_move_reconcile((int)$args['intent'],$hook)]); exit;
}
try {
    if ($mode === 'metadata') {
        require_once __DIR__.'/../../../restapi/services/AttachmentMetadataService.php';
        $service=new AttachmentMetadataService($connection,$db,$args['subject'],$args['username'],$args['user_id'],$args['tenant_id']);
        $result=$service->update($args['id'],$args['body'],$args['etag'],$args['body']['client_id']);
    } else {
        $service=new AttachmentApproveService($connection,$db,$args['subject'],$args['username'],$args['user_id'],$args['tenant_id'],RequestId::resolve(null),$hook);
        $result=$service->approve($args['id'],$args['body'],$args['etag'],$args['body']['client_id']);
    }
    echo json_encode($result,JSON_THROW_ON_ERROR);
} catch (MobileT2Error $error) { echo json_encode(['status'=>$error->status,'error'=>$error->error,'details'=>$error->details]); }
