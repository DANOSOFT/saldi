<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_bankimport_page.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Exercise legacy CSV bytes with a LATIN9 database client.

require_once __DIR__ . '/CharacterizationEnv.php';
if ($argc !== 4) {
    exit(2);
}
$options = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$_SERVER['REQUEST_URI'] = '/saldi/finans/bankimport.php';
$_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'bank-characterization';
$_GET = [];
$_FILES = ['uploadedfile' => ['name' => '']];
$file = tempnam(sys_get_temp_dir(), 'bank-char-');
copy(__DIR__ . '/../../fixtures/bank/' . basename($options['fixture']), $file . '2');
$latin9 = !empty($options['latin9']);
if ($latin9) {
    // vis_data() leaves its processed tab file in ISO-8859-1 on legacy tenants.
    file_put_contents($file . '2', mb_convert_encoding(file_get_contents($file . '2'), 'ISO-8859-1', 'UTF-8'));
}
$columns = $options['fixture'] === 'duplicates.tsv'
    ? ['dato','beskrivelse','belob','currency','']
    : ['dato','beskrivelse','belob','saldo','currency',''];
$_POST = [
    'import' => 'on', 'kladde_id' => $argv[2], 'filnavn' => $file,
    'splitter' => "\t", 'feltnavn' => $columns, 'feltantal' => count($columns)-1,
    'kontonr' => '6471', 'gebyrkonto' => '7770', 'currencyCode' => '0',
    'bilag' => '10', 'afd' => ['0'],
];
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[3]);
error_reporting(E_ALL);
register_shutdown_function(static function () use ($file): void {
    foreach ([$file, $file . '2'] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});
chdir(dirname(__DIR__, 3) . '/finans');
if ($latin9) {
    // Load the real page and its session bootstrap before selecting the legacy client encoding.
    $_POST = [];
}
include __DIR__ . '/../../../finans/bankimport.php';
if ($latin9) {
    if (pg_set_client_encoding($connection, 'LATIN9') !== 0) {
        throw new RuntimeException('Cannot select the legacy bank import encoding');
    }
    $charset = 'ISO-8859-1';
    flyt_data((int)$argv[2], $file, "\t", $columns, count($columns)-1, 6471, 7770, 10, 0, 0);
    if (pg_client_encoding($connection) !== 'LATIN9') {
        throw new RuntimeException('Bank import changed the database client encoding');
    }
}
