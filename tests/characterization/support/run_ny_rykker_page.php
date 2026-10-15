<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_ny_rykker_page.php --- ver 5.0.0 --- 2026.10.03
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

if ($argc < 4) {
    fwrite(STDERR, "usage: run_ny_rykker_page.php <konto_id|alle> <rykker_id|0> <session_id>\n");
    exit(2);
}
error_reporting(E_ALL);
date_default_timezone_set('Europe/Copenhagen');
$_SERVER['REQUEST_URI'] = '/saldi/debitor/ny_rykker.php';
$_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_GET = ['kontoliste'=>$argv[1] === 'alle' ? 'alle' : (string)(int)$argv[1], 'kontoantal'=>'0'];
if ((int)$argv[2] > 0) {
    $_GET['rykker_id'] = (string)(int)$argv[2];
}
if (isset($argv[4])) {
    $_GET = array_replace($_GET,json_decode($argv[4],true,512,JSON_THROW_ON_ERROR));
}
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[3]);
chdir(dirname(__DIR__, 3) . '/debitor');
ob_start();
$page = getenv('SALDI_DUNNING_CHAR_PAGE') ?: __DIR__ . '/../../../debitor/ny_rykker.php';
include $page;
$output = ob_get_clean();
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $output);
}
fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
