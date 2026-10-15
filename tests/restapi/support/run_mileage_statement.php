<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_mileage_statement.php --- ver 5.0.0 --- 2026.10.06
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
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../restapi/models/attachment/AttachmentModel.php';
require_once __DIR__ . '/../../../restapi/services/ClaimsService.php';

$args = json_decode(fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
$connection = RestApiEnv::connect($args['db']);
$GLOBALS['connection'] = $connection;
$GLOBALS['db_type'] = 'postgresql';
$GLOBALS['db'] = $args['db'];
$_SERVER['REQUEST_URI'] = '/tests/t4-b6b';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$service = new ClaimsService($connection, $args['db'], $args['subject'], 'account', (int)$args['user_id']);
fwrite(STDOUT, "ready\n");
fflush(STDOUT);
fgets(STDIN);
try {
    $result = $service->issueStatement($args['body'], static function (string $phase, array $row) use ($args): void {
        $root = dirname(poolPuljePath($args['db']));
        if (is_dir($root . '/pulje/.staging')) {
            chmod($root . '/pulje/.staging', 0777);
        }
        foreach (glob($root . '/pulje/*.pdf') as $path) {
            chmod($path, 0666);
        }
        if ($phase === $args['stop']) {
            fwrite(STDOUT, $phase . "\n");
            fflush(STDOUT);
            fgets(STDIN); // Parent sends SIGKILL with real committed/uncommitted database and filesystem work.
        }
    });
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . "\n");
} catch (MobileT2Error $error) {
    fwrite(STDOUT, json_encode(['status'=>$error->status, 'error'=>$error->error, 'details'=>$error->details], JSON_THROW_ON_ERROR) . "\n");
}
