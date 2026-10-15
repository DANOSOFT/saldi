<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/ExpoPushStub.php --- ver 5.0.0 --- 2026.10.01
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
/** Local PHP built-in-server router only. State and captured test payloads live in /tmp. */
if (PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit;
}
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$log = getenv('SALDI_EXPO_STUB_LOG');
if (!$log || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['errors'=>[['code'=>'INVALID_REQUEST']]]);
    exit;
}
file_put_contents($log, json_encode(['path'=>$path,'body'=>$input], JSON_THROW_ON_ERROR) . "\n", FILE_APPEND | LOCK_EX);
if ($path === '/transport-error') {
    http_response_code(503);
    echo '{}';
} elseif ($path === '/receipts' || $path === '/device-receipts' || $path === '/empty-receipts') {
    $data = [];
    foreach ($input['ids'] as $id) {
        if ($path === '/empty-receipts') {
            continue;
        }
        $data[$id] = $path === '/device-receipts' ? ['status'=>'error','details'=>['error'=>'DeviceNotRegistered']] : ['status'=>'ok'];
    }
    echo json_encode(['data'=>(object)$data]);
} else {
    $data = [];
    foreach ($input as $message) {
        $data[] = $path === '/device-tickets' ? ['status'=>'error','details'=>['error'=>'DeviceNotRegistered']] : ['status'=>'ok','id'=>'test-ticket-' . bin2hex(random_bytes(8))];
    }
    if ($path === '/slow-send') {
        // Signal the parent after the lease committed; it SIGKILLs the worker during this request.
        usleep(1500000);
    }
    echo json_encode(['data'=>$data]);
}
