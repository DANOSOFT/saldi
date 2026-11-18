<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/bank_integration/support/run_sync.php --- ver 5.1.1 --- 2026-11-16 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
//
// 20261006 CDX/LH Created isolated Enable Banking fixture support.
// 20261007 CDX/LH Used two-decimal DKK fixture amounts for concurrent delivery.
// 20261116 CL/LH Header version follows the release; the race now only stores lines (pull model).

require_once __DIR__ . '/../../../../bank_integration/includes/ebClient.php';
require_once __DIR__ . '/EbTestEnv.php';
ebTestConnect();
require_once __DIR__ . '/../../../../bank_integration/includes/ebConnections.php';
$config = ['app_id' => 'fixture-application', 'key_path' => $argv[2], 'redirect_url' => 'https://example.invalid/callback'];
$page = json_decode(file_get_contents(__DIR__ . '/../../../fixtures/enablebanking/booked.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($page['transactions'] as &$tx) {
    $tx['booking_date'] = ebToday();
    $tx['value_date'] = ebToday();
    $tx['transaction_amount']['amount'] = substr($tx['transaction_amount']['amount'], 0, -1);
}
unset($tx);
$transport = new EbFixtureTransport([['method' => 'GET', 'path' => '/transactions?', 'status' => 200, 'body' => $page, 'delay' => 700000]]);
// Both real processes wait for the parent's barrier, then race the same tenant/account.
fwrite(STDOUT, "ready\n");
fflush(STDOUT);
fgets(STDIN);
$result = ebSyncAccount((int)$argv[1], 'schedule', [], new EnableBankingClient($config, $transport));
fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . "\n");
