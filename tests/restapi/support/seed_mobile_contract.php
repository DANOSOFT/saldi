<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/seed_mobile_contract.php --- ver 5.0.0 --- 2026.10.02
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
// 20261002 CDX/LH Seed isolated real-client contract accounts and clean up their exact resources.

require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/docsIncludes/poolPaths.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Remove only resources recorded in the private per-run manifest.
 * @param array{databases:list<string>,tenant_ids:list<int>,registry_id:?int,subjects:list<string>,buckets:list<string>} $state
 * @return void
 */
function teardownMobileContract(array $state): void
{
    $master = RestApiEnv::connect(RestApiEnv::masterDb());
    foreach (['refresh_sessions', 'auth_challenges', 'auth_versions'] as $table) {
        foreach ($state['subjects'] as $subject) {
            RestApiEnv::rows($master, "DELETE FROM $table WHERE subject=$1", [$subject]);
        }
        foreach ($state['tenant_ids'] as $id) {
            RestApiEnv::rows($master, "DELETE FROM $table WHERE tenant_id=$1", [$id]);
        }
    }
    foreach ($state['buckets'] as $bucket) {
        RestApiEnv::rows($master, "DELETE FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1", [$bucket]);
    }
    if ($state['registry_id'] !== null) {
        RestApiEnv::rows($master, 'DELETE FROM brugere WHERE id=$1', [$state['registry_id']]);
    }
    foreach ($state['tenant_ids'] as $id) {
        RestApiEnv::rows($master, 'DELETE FROM regnskab WHERE id=$1', [$id]);
    }
    foreach ($state['databases'] as $database) {
        if (!preg_match('/^saldi_c1_[a-f0-9]{8}(?:b)?$/D', $database)) {
            throw new RuntimeException('Unsafe contract database');
        }
        RestApiEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()', [$database]);
        RestApiEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $database));
        foreach ([dirname(poolPuljePath($database)), __DIR__ . '/../../../temp/' . $database] as $directory) {
            removeMobileContractDirectory($directory);
        }
    }
    pg_close($master);
}

/** Remove a recorded throwaway tenant directory, never following symlinks.
 * @return void
 */
function removeMobileContractDirectory(string $directory): void
{
    if (is_link($directory) || is_file($directory)) {
        if (!unlink($directory)) {
            throw new RuntimeException('Could not remove contract file');
        }
    } elseif (is_dir($directory)) {
        foreach (new DirectoryIterator($directory) as $entry) {
            if (!$entry->isDot()) {
                removeMobileContractDirectory($entry->getPathname());
            }
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('Could not remove contract directory');
        }
    }
}

$mode = ifset($argv, 1, 'seed');
if ($mode === 'teardown') {
    $handle = ifset($argv, 2, '');
    if (!preg_match('/^[a-f0-9]{32}$/D', $handle)) {
        throw new RuntimeException('Invalid cleanup handle');
    }
    $path = sys_get_temp_dir() . '/saldi-c1-' . $handle . '.json';
    if (!is_file($path)) {
        echo json_encode(['removed' => true]) . PHP_EOL;
        exit;
    }
    $state = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    teardownMobileContract($state);
    unlink($path);
    echo json_encode(['removed' => true]) . PHP_EOL;
    exit;
}
if ($mode !== 'seed') {
    throw new RuntimeException('Use seed or teardown <handle>');
}
$reason = RestApiEnv::unavailableReason();
if ($reason !== null) {
    throw new RuntimeException($reason);
}
putenv('SALDI_REST_TEST_DB=saldi_c1');
putenv('SALDI_REST_KEEP_TENANT=');
$handle = bin2hex(random_bytes(16));
$path = sys_get_temp_dir() . '/saldi-c1-' . $handle . '.json';
$state = ['databases' => [], 'tenant_ids' => [], 'registry_id' => null, 'subjects' => [], 'buckets' => []];
try {
    RestApiEnv::bootstrapTenant();
    $state['databases'][] = RestApiEnv::testDb();
    $state['tenant_ids'] = [RestApiEnv::regnskabId(RestApiEnv::accountOpen()), RestApiEnv::regnskabId(RestApiEnv::accountClosed())];
    $tenant = RestApiEnv::connect(RestApiEnv::testDb());
    $suffix = bin2hex(random_bytes(4));
    $owner = ['username' => 'c1_owner_' . $suffix, 'password' => TestCredentials::password('c1owner')];
    $receipt = ['username' => 'c1_receipt_' . $suffix, 'password' => TestCredentials::password('c1receipt')];
    RestApiEnv::rows($tenant, 'DELETE FROM brugere');
    foreach ([[$owner, str_repeat('1', 16)], [$receipt, str_repeat('0', 16)]] as [$user, $rights]) {
        RestApiEnv::rows($tenant, "INSERT INTO brugere(brugernavn,kode,rettigheder,status,twofactor,ip_address) VALUES ($1,$2,$3,true,false,'')", [$user['username'], md5($user['password']), $rights]);
    }
    // Deterministic reference data makes an empty catalog a failure rather than a vacuous pass.
    $year = (int)RestApiEnv::rows($tenant, "SELECT kodenr FROM grupper WHERE art='RA' AND CURRENT_DATE BETWEEN make_date(box2::int,box1::int,1) AND (make_date(box4::int,box3::int,1)+INTERVAL '1 month - 1 day')::date ORDER BY kodenr LIMIT 1")[0]['kodenr'];
    RestApiEnv::rows($tenant, "INSERT INTO grupper(art,kode,kodenr,beskrivelse,box1,box2,fiscal_year) VALUES ('KM','C',991,'Contract purchase','66200','25',$1)", [$year]);
    RestApiEnv::rows($tenant, "INSERT INTO kontoplan(kontonr,beskrivelse,moms,kontotype,lukket,regnskabsaar,saldo,primo) VALUES (900991,'Contract expense','C991','D','',$1,0,0)", [$year]);
    pg_close($tenant);
    $master = RestApiEnv::connect(RestApiEnv::masterDb());
    $secondDb = RestApiEnv::testDb() . 'b';
    RestApiEnv::rows($master, 'CREATE DATABASE ' . pg_escape_identifier($master, $secondDb) . ' TEMPLATE ' . pg_escape_identifier($master, RestApiEnv::testDb()));
    $state['databases'][] = $secondDb;
    $secondName = 'c1_second_' . $suffix;
    $secondId = (int)RestApiEnv::rows($master, "INSERT INTO regnskab(regnskab,dbhost,dbuser,db,version,sidst,brugerantal,posteringer,lukket,administrator) SELECT $1,dbhost,dbuser,$2,version,sidst,brugerantal,posteringer,'',administrator FROM regnskab WHERE id=$3 RETURNING id", [$secondName, $secondDb, $state['tenant_ids'][0]])[0]['id'];
    $state['tenant_ids'][] = $secondId;
    $registry = ['username' => 'c1_registry_' . $suffix, 'password' => TestCredentials::password('c1registry')];
    $registryId = (int)RestApiEnv::rows($master, "INSERT INTO brugere(brugernavn,kode,rettigheder,status,twofactor,ip_address) VALUES ($1,$2,$3,true,false,'') RETURNING id", [$registry['username'], md5($registry['password']), ',,,' . $state['tenant_ids'][0] . ',' . $secondId])[0]['id'];
    $state['registry_id'] = $registryId;
    $installation = RestApiEnv::rows($master, "SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name='installation_id' AND user_id=0")[0]['var_value'];
    $state['subjects'][] = $installation . '/registry/' . $registryId;
    require_once __DIR__ . '/../../../restapi/core/MobileAuth.php';
    $state['buckets'][] = MobileAuth::bucketKey('registry', '', $registry['username']);
    foreach ([$owner, $receipt] as $user) {
        $state['buckets'][] = MobileAuth::bucketKey('account', RestApiEnv::accountOpen(), $user['username']);
    }
    pg_close($master);
    $webUser = posix_getpwnam('www-data');
    if ($webUser === false) {
        throw new RuntimeException('Web service user unavailable');
    }
    foreach ($state['databases'] as $database) {
        foreach ([dirname(poolPuljePath($database)), __DIR__ . '/../../../temp/' . $database] as $directory) {
            if (!mkdir($directory, 0750, true) || !chown($directory, $webUser['uid']) || !chgrp($directory, $webUser['gid'])) {
                throw new RuntimeException('Could not provision writable contract storage');
            }
        }
    }
    $oldMask = umask(0077);
    $manifest = json_encode($state, JSON_THROW_ON_ERROR);
    $written = file_put_contents($path, $manifest);
    umask($oldMask);
    if ($written !== strlen($manifest)) {
        throw new RuntimeException('Could not persist contract cleanup manifest');
    }
    echo json_encode(['cleanup_handle' => $handle, 'accounts' => [['id' => $state['tenant_ids'][0], 'name' => RestApiEnv::accountOpen()], ['id' => $secondId, 'name' => $secondName]], 'owner' => $owner, 'receipt' => $receipt, 'registry' => $registry, 'year' => $year], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if ($state['databases'] !== []) {
        teardownMobileContract($state);
    } else {
        RestApiEnv::teardownTenant();
    }
    throw $error;
}
