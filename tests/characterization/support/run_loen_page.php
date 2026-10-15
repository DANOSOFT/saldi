<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_loen_page.php --- ver 5.0.0 --- 2026.10.06
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

error_reporting(E_ALL);
$payload = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
if (isset($payload['mode']) && $payload['mode'] === 'number') {
    require_once __DIR__ . '/CharacterizationEnv.php';
    require_once __DIR__ . '/../../../includes/sager/loenTimer.php';
    CharacterizationEnv::assertSafeDatabases();
    $GLOBALS['connection'] = CharacterizationEnv::connect(CharacterizationEnv::testDb());
    $deadline = microtime(true) + 10;
    while (!file_exists($payload['barrier'])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Number worker barrier timed out');
        }
        usleep(10000);
    }
    loen_timer_query('BEGIN');
    try {
        $number = loen_next_number();
        loen_timer_query('SELECT pg_sleep(0.025)');
        $id = loen_seddel_insert(['art'=>'timer','nummer'=>$number]);
        loen_timer_query('COMMIT');
        echo json_encode(['number'=>$number,'id'=>$id], JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        loen_timer_query('ROLLBACK');
        throw $error;
    }
    exit;
}
$_GET = $payload['get'];
$_POST = $payload['post'];
$_SERVER['REQUEST_METHOD'] = $_POST ? 'POST' : 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = '/saldi/sager/loen.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTP_USER_AGENT'] = 'characterization';
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[2]);
chdir(__DIR__ . '/../../../sager');
require __DIR__ . '/../../../sager/loen.php';
