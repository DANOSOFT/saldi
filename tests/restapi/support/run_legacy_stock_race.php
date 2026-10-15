<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_legacy_stock_race.php --- ver 5.0.0 --- 2026.10.06
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

// Runs the real app count service or production page against the disposable tenant.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
if ($input['mode'] === 'production') {
    $_GET = ['id'=>$input['item'],'antal'=>2,'samlevare'=>'on','lager'=>2];
    $_POST = ['OK'=>'on','id'=>[$input['item']],'ny_beholdning'=>[2],'bilag'=>''];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = '/saldi/lager/vareproduktion.php';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'chartest';
    $_SERVER['HTTP_HOST'] = 'localhost';
    ini_set('session.save_path', sys_get_temp_dir());
    session_id($input['session']);
    chdir(__DIR__ . '/../../../lager');
    file_put_contents($input['started'], 'page');
    require __DIR__ . '/../../../lager/vareproduktion.php';
    exit;
}
$_SERVER['REQUEST_URI'] = '/saldi/lager/varekort.php';
require_once __DIR__ . '/../../characterization/support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../restapi/services/StockCountService.php';
$conn = CharacterizationEnv::connect($input['db']);
$context = new EngineContext($conn,$input['db'],(int)$input['db_id'],$input['username'],(int)$input['user_id'],'2026-10-06');
$barrier = empty($input['barrier']) ? null : static function () use ($input,$conn): void {
    file_put_contents($input['barrier'].'.ready', (string)pg_get_pid($conn));
    $deadline = microtime(true)+15;
    while (!is_file($input['barrier'].'.release')) {
        if (microtime(true)>$deadline) {
            throw new RuntimeException('Count barrier timeout');
        }
        usleep(10000);
    }
};
file_put_contents($input['started'], (string)pg_get_pid($conn));
try {
    $result = (new StockCountService($context,Subject::build('account',$context->userId),$barrier))
        ->submit($input['request'],new EmployeeContext($conn,'account',$context->userId));
} catch (MobileT2Error $error) {
    $result = ['status'=>$error->status,'error'=>$error->error,'details'=>$error->details];
}
echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
