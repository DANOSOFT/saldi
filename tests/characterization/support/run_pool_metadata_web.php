<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_pool_metadata_web.php --- ver 5.0.0 --- 2026.10.07
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
// 20261007 CDX/LH Assert the restored tenant SQL trail on the web write path.

// Run in a fresh process so Subject has no identity cached by a REST login.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/CharacterizationEnv.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/docsIncludes/poolMetadata.php';

[$script, $tenantDb, $registryDb, $filename, $userId, $username] = $argv;
if ($registryDb !== '') {
    require_once __DIR__ . '/../../../includes/connect.php';
}
$sqhost = CharacterizationEnv::pgHost();
$squser = CharacterizationEnv::pgUser();
$sqpass = CharacterizationEnv::pgPass();
$sqdb = $registryDb;
$db_type = 'postgresql';
$db_encode = 'UTF8';
$connection = CharacterizationEnv::connect($tenantDb);
$tenant = $connection;
$db = $tenantDb;
$db_skriv_id = 2;
$trailDirectory = CharacterizationEnv::repoRoot() . '/temp/' . $db;
if (!is_dir($trailDirectory)) {
    mkdir($trailDirectory, 0777, true);
}
$bruger_id = (int)$userId;
$brugernavn = $username;
$revisor = false;
$_SERVER['REQUEST_URI'] = '/tests/web-metadata';
$row = CharacterizationEnv::one($tenant, 'SELECT * FROM pool_files WHERE filename=$1', [$filename]);
$result = poolMetadataSave($filename, ['description'=>'Web correction before mobile login','amount'=>'1.234,56'],
    true, poolMetadataVersion($row), 987);
echo json_encode($result + ['same_connection'=>$connection === $tenant,'selected_db'=>pg_dbname($connection),
    'transaction_status'=>pg_transaction_status($tenant)], JSON_THROW_ON_ERROR);
