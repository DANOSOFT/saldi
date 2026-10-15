<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_stock_warning.php --- ver 5.0.0 --- 2026.10.05
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

require_once __DIR__ . '/CharacterizationEnv.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../restapi/services/legacy/LegacyEngine.php';

[$script,$itemId] = $argv;
$_SERVER['REQUEST_URI'] = '/saldi/debitor/ordre.php';
$connection = CharacterizationEnv::connect(CharacterizationEnv::testDb());
$registry = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
$id = (int)CharacterizationEnv::one($registry,'SELECT id FROM regnskab WHERE db=$1',[CharacterizationEnv::testDb()])['id'];
$context = new EngineContext($connection,CharacterizationEnv::testDb(),$id,'chartest',1,'2026-03-15');
$result = LegacyEngine::run($context,static function () use ($itemId): array {
    LegacyEngine::load('orders');
    return check_stock_warning((int)$itemId);
});
echo json_encode($result,JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) . "\n";
pg_close($registry);
pg_close($connection);
