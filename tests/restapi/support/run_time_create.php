<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_time_create.php --- ver 5.0.0 --- 2026.10.06
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
require_once __DIR__ . '/../../../restapi/core/BaseEndpoint.php';
require_once __DIR__ . '/../../../restapi/services/TimeEntryService.php';

error_reporting(E_ALL);
$args = json_decode($argv[1],true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/^[a-z0-9_]+$/D',$args['db']) || $args['db'] === RestApiEnv::masterDb() || $args['db'] === RestApiEnv::templateDb()) {
    throw new RuntimeException('Worker requires a disposable tenant');
}
$connection = RestApiEnv::connect($args['db']);
RestApiEnv::rows($connection,"SELECT set_config('application_name',$1,false)",[ifset($args,'worker_name','time-worker')]);
$context = new EngineContext($connection,$args['db'],(int)$args['tenant_id'],$args['user']['brugernavn'],(int)$args['user']['id'],'2026-07-01');
$service = new TimeEntryService($context,'account',Subject::build('account',(int)$args['user']['id']));
$deadline = microtime(true)+20;
while (!file_exists($args['barrier'])) {
    if (microtime(true)>$deadline) {
        throw new RuntimeException('Worker barrier timed out');
    }
    usleep(10000);
}
try {
    $response = ifset($args,'operation','create') === 'update'
        ? $service->update($args['id'],$args['body'],$args['etag']) : $service->create($args['body']);
} catch (MobileT2Error $error) {
    $response = ['status'=>$error->status,'body'=>['data'=>['error'=>$error->error,'details'=>$error->details]]];
}
echo json_encode($response,JSON_THROW_ON_ERROR);
pg_close($connection);
