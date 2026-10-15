<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_bank_line_upload.php --- ver 5.0.0 --- 2026.10.05
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

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../restapi/models/attachment/DurableAttachmentModel.php';
foreach (['sqhost'=>RestApiEnv::pgHost(),'squser'=>RestApiEnv::pgUser(),'sqpass'=>RestApiEnv::pgPass(),
    'sqdb'=>RestApiEnv::masterDb(),'db_type'=>'postgresql','db_encode'=>'UTF8'] as $key=>$value) {
    $GLOBALS[$key] = $value;
}
$connection = RestApiEnv::connect($argv[1]);
$GLOBALS['connection'] = $connection;
$GLOBALS['db'] = $argv[1];
$body = json_decode(fgets(STDIN),false,512,JSON_THROW_ON_ERROR);
$model = new DurableAttachmentModel($connection,$argv[1],$argv[3],false);
$model->upload(AttachmentUpload::decode($body,[]),(int)$argv[2]);
fwrite(STDOUT,"COMMITTED\n");
fflush(STDOUT);
// The parent kills this real uploader after its committed completion and before a response is sent.
fgets(STDIN);
