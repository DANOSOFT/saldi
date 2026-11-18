<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/ebSchemaInstall.php --- ver 5.1.1 --- 2026-11-16 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20261116 CL/LH Created from bank_integration/includes/ebSchema.php: installs the Enable Banking tables and indexes of a tenant, called at the end of includes/betweenUpdates.php (and by the tests), never by a page or the worker.

require_once __DIR__ . '/../../bank_integration/includes/ebSchema.php';

/** @return void Idempotent tenant schema installation outside import transactions. */
function ebEnsureSchema(): void
{
    if (($GLOBALS['db_transaktion_depth'] ?? 0) > 0) {
        throw new LogicException('Cannot install banking schema inside a transaction');
    }
    // Serialize concurrent login/worker installers, including MySQL check/create indexes.
    ebNamedLock('schema', function (): void {
        foreach (ebSchemaTables() as $table => $definition) {
            if (!ebTableExists($table)) {
                ebWrite('CREATE TABLE IF NOT EXISTS ' . $table . ' (' . $definition . ')' . (ebMysql() ? ' ENGINE=InnoDB' : ''));
            }
        }
        foreach (ebSchemaIndexes() as $name => $index) {
            if (!ebIndexExists($name, $index['table'])) {
                ebWrite('CREATE ' . ($index['unique'] ? 'UNIQUE ' : '') . 'INDEX ' . $name . ' ON ' . $index['table'] . ' (' . $index['columns'] . ')');
            }
        }
    });
}
