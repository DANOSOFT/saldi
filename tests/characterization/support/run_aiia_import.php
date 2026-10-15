<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_aiia_import.php --- ver 5.0.0 --- 2026.10.05
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
if ($argc !== 4) {
    exit(2);
}
$options = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$_SERVER['REQUEST_URI'] = '/saldi/bank_integration/aiia_import.php';
$_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'bank-characterization';
$_GET = ['kladde_id' => $argv[2]];
$_POST = ['debet' => '6471', 'import' => []];
$fp = fopen(__DIR__ . '/../../fixtures/bank/aiia.tsv', 'r');
while (($fields = fgetcsv($fp, 0, "\t", '"', '')) !== false) {
    $payload = [
        'id' => $fields[0], 'date' => $fields[1], 'text' => $fields[2], 'amount' => $fields[3],
        'currency' => $fields[4] ?? 'DKK', 'balance' => $fields[5] ?? null,
    ];
    if (!empty($options['legacy_payload'])) {
        unset($payload['currency'], $payload['balance']);
    }
    if (!empty($options['reject_second']) && $fields[0] === 'tx-two') {
        $payload['text'] = 'Rejected bank line';
    }
    $_POST['import'][] = json_encode($payload, JSON_THROW_ON_ERROR);
}
fclose($fp);
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[3]);
session_start();
// Only expiry metadata is needed by auth_check; no access/login token is seeded.
$_SESSION['OAuth'] = [
    'login' => ['expires' => date(DATE_ATOM, time()+3600)],
    'session' => ['expires' => date(DATE_ATOM, time()+3600)],
];
$_SESSION['aiia_account'] = ['id' => $options['account_id'] ?? 'bank-account-one', 'bookedBalance' => ['currency' => $options['account_currency'] ?? 'DKK']];
session_write_close();
error_reporting(E_ALL);
chdir(dirname(__DIR__, 3) . '/bank_integration');
include __DIR__ . '/../../../bank_integration/aiia_import.php';
