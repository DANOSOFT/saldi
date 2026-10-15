<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiSettlementWorker.php --- ai/mcp-assistant --- 2026.10.04
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
// Independent PDO connection for settlement allocation and source-conflict tests.
//
// 20261004 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/automation/kinds/SaldiSettlementKind.php';

if (!preg_match('/^plan_mcp_t_[0-9]+_[a-f0-9]+$/D', $argv[1] ?? '')) {
    throw new RuntimeException('Disposable tenant required');
}
$db = SaldiDb::connect(saldi_runtime_config(), $argv[1], 15000);
try {
    $ctx = saldi_runtime_context_for_user($db, ['id' => (int)$argv[2], 'db' => $argv[1]], 910001, 'user:910001');
    $item = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    fwrite(STDOUT, "ready\n");
    $result = $db->transaction(fn() => (new SaldiSettlementKind())->apply($ctx, $db, $item));
    echo json_encode(['result' => $result], JSON_THROW_ON_ERROR);
} catch (SaldiRuntimeError $error) {
    echo json_encode(['error' => $error->errorCode, 'detail' => $error->detail], JSON_THROW_ON_ERROR);
} finally {
    $db->close();
}
