<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_varekort_stock.php --- ver 5.0.0 --- 2026.10.06
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
$_GET = ['id' => (int)$payload['id']];
if (($payload['operation'] ?? '') === 'sale') {
    require_once __DIR__ . '/CharacterizationEnv.php';
    require_once __DIR__ . '/../../../includes/db_query.php';
    require_once __DIR__ . '/../../../restapi/services/legacy/LegacyEngine.php';
    $_SERVER['REQUEST_URI'] = '/saldi/debitor/levering.php';
    $tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
    $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
    $tenantId = (int)CharacterizationEnv::one($master, 'SELECT id FROM regnskab WHERE db=$1', [CharacterizationEnv::testDb()])['id'];
    $context = new EngineContext($tenant, CharacterizationEnv::testDb(), $tenantId, 'chartest', 1, '2026-10-06');
    $result = LegacyEngine::run($context, static function () use ($payload): string {
        LegacyEngine::load('orders');
        transaktion('begin');
        $result = levering((int)$payload['id'], 'on', null, 'on', true, static function (int $item, int $variant, int $warehouse): void {});
        transaktion($result === 'OK' ? 'commit' : 'rollback');
        return $result;
    });
    echo json_encode(['result'=>$result], JSON_THROW_ON_ERROR);
    exit;
}
$_POST = $payload['post'];
$_SERVER['REQUEST_METHOD'] = $_POST ? 'POST' : 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = '/saldi/lager/varekort.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_SERVER['HTTP_HOST'] = 'localhost';
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[2]);
if (($payload['operation'] ?? '') === 'receipt') {
    chdir(__DIR__ . '/../../../kreditor');
    $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = '/saldi/kreditor/modtag.php';
    require __DIR__ . '/../../../kreditor/modtag.php';
} else {
    chdir(__DIR__ . '/../../../lager');
    require __DIR__ . '/../../../lager/varekort.php';
}
