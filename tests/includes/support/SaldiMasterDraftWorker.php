<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiMasterDraftWorker.php --- ai/mcp-assistant --- 2026.10.02
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
//
// Independent PDO process used to verify concurrent allocations in disposable test databases.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/automation/SaldiKindRegistry.php';

if (!preg_match('/^plan_mcp_t_[0-9]+_[a-f0-9]{8}$/D', $argv[1] ?? '')) {
    throw new RuntimeException('Worker requires a disposable runtime test database');
}
$db = SaldiDb::connect(saldi_runtime_config(), $argv[1]);
$ctx = saldi_runtime_context_for_user($db, ['id' => (int)$argv[2], 'db' => $argv[1]], 910001, 'user:910001');
$kind = SaldiKindRegistry::default()->get($argv[3]);
$input = json_decode($argv[4], true, 512, JSON_THROW_ON_ERROR);
fwrite(STDOUT, json_encode(['pid' => (int)$db->value('SELECT pg_backend_pid()')]) . "\n");
fflush(STDOUT);
try {
    $result = $db->transaction(function () use ($db, $ctx, $kind, $input): array {
        $normalized = $kind->validate($ctx, $db, $input);
        return $kind->apply($ctx, $db, $normalized);
    });
    fwrite(STDOUT, json_encode(['result' => $result]) . "\n");
} catch (SaldiRuntimeError $e) {
    fwrite(STDOUT, json_encode(['error' => $e->errorCode, 'detail' => $e->detail]) . "\n");
} finally {
    $db->close();
}
