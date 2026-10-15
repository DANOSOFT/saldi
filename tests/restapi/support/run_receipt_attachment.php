<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_receipt_attachment.php --- ver 5.0.0 --- 2026.10.06
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

if (PHP_SAPI !== 'cli' || $argc !== 7) { exit(2); }
error_reporting(E_ALL);
require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../restapi/core/Subject.php';
require_once __DIR__ . '/../../../includes/docsIncludes/poolAttachSource.php';
$installation = Subject::installation();
$connection = RestApiEnv::connect($argv[1]);
$userId = (int)$argv[2];
$_SERVER['REQUEST_URI'] = '/saldi/kreditor/modtag.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$hook = static function (string $point) use ($argv): void {
    if ($point === $argv[6]) {
        fwrite(STDOUT, "READY\n");
        fflush(STDOUT);
        while (true) { usleep(100000); }
    }
};
pool_attach_to_source(['connection'=>$connection, 'db'=>$argv[1], 'subject'=>Subject::build('account', $userId, $installation),
    'user_id'=>$userId, 'hook'=>$hook], (int)$argv[4], 'creditorOrder', (int)$argv[3], $argv[5]);
