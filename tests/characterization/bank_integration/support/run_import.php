<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/bank_integration/support/run_import.php --- ver 5.1.1 --- 2026-11-16 ---
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
// 20261116 CL/LH Created: an Enable Banking import in a process of its own, for the race with posting.

require_once __DIR__ . '/../../../../bank_integration/includes/ebClient.php';
require_once __DIR__ . '/EbTestEnv.php';
ebTestConnect();
require_once __DIR__ . '/../../../../bank_integration/includes/ebConnections.php';
// argv: 1 = kladde_id, 2 = account id, 3 = regnaar, 4.. = transaction ids
$GLOBALS['regnaar'] = (int)$argv[3];
try {
    $result = ebImportIntoJournal((int)$argv[1], (int)$argv[2], array_slice($argv, 4), 'chartest');
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . "\n");
} catch (EbUserError $e) {
    fwrite(STDOUT, json_encode(['refused' => $e->getMessage()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n");
}
