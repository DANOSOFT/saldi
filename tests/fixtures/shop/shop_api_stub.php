<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/fixtures/shop/shop_api_stub.php --- ver 5.0.0 --- 2026.10.06
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

if (PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit;
}
$path = getenv('SALDI_STOCK_STUB_LOG');
if (!$path || !str_starts_with($path, sys_get_temp_dir() . '/')) {
    http_response_code(500);
    exit;
}
$params = $_GET;
unset($params['rand']);
file_put_contents($path, json_encode($params, JSON_THROW_ON_ERROR) . "\n", FILE_APPEND | LOCK_EX);
header('Content-Type: text/plain');
echo 'OK';
