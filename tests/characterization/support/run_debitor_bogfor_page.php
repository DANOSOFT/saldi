<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_debitor_bogfor_page.php --- ver 5.0.0 --- 2026.10.04
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
// 20261001 CDX/LH Expose the default posting engine termination to characterization tests.

error_reporting(E_ALL);
$payload = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$page = $payload['page'];
if (!in_array($page, ['bogfor','levering','delivery_engine','posting_engine'], true)) {
    throw new InvalidArgumentException('Unknown invoice page');
}
$_GET = $payload['get'];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = '/saldi/debitor/'.$page.'.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[2]);
chdir(dirname(__DIR__,3).'/debitor');
if (in_array($page,['delivery_engine','posting_engine'],true)) {
    session_start();
    $s_id = session_id();
    $modulnr = 5;
    include __DIR__.'/../../../includes/connect.php';
    include __DIR__.'/../../../includes/online.php';
    include __DIR__.'/../../../includes/std_func.php';
    include __DIR__.'/../../../includes/ordrefunc.php';
    transaktion('begin');
    $result = $page==='posting_engine' ? bogfor((int)$_GET['id'],'on',false,true) : levering((int)$_GET['id'], '', null, false);
    transaktion($result === 'OK' ? 'commit' : 'rollback');
    echo 'RETURNED:'.$result;
} else {
    require __DIR__.'/../../../debitor/'.$page.'.php';
}
