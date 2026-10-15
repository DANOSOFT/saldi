<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiExpiringBatchesRoutineTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Isolated Watch v2 regression coverage using synthetic fixtures only.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiWatchDbTestCase.php';
require_once __DIR__ . '/../../../includes/automation/routines/SaldiExpiringBatchesRoutine.php';

final class SaldiExpiringBatchesRoutineTest extends SaldiWatchDbTestCase
{
    public function testOptInRemainingStockWarningBoundaryAndPastDue(): void
    {
        $db = $this->env->db;
        $routine = new SaldiExpiringBatchesRoutine();
        $ctx = $this->rule($routine->name());
        $db->insert('varer', ['id' => 70001, 'varenr' => 'FIXTURE', 'beskrivelse' => 'Fixture item']);
        $batches = ['2026-09-01' => '1', '2026-11-01' => '2', '2026-11-02' => '3', '2026-10-10' => '0'];
        foreach ($batches as $date => $rest) {
            $db->insert('batch_kob', ['vare_id' => 70001, 'due_date' => $date, 'rest' => $rest, 'batch_no' => $date]);
        }
        self::assertNull($routine->run($ctx, $db, '2026-10-02'));
        $this->setting('items', 'batchExpiryEnabled', 'on');
        $payload = $routine->run($ctx, $db, '2026-10-02');
        self::assertSame(2, $payload['count']);
        self::assertSame(['2026-09-01', '2026-11-01'], array_column($payload['rows'], 'date'));
        self::assertNull($payload['rows'][0]['amount']);
        $this->setting('lager', 'due_date_warning_days', '2');
        self::assertSame(1, $routine->run($ctx, $db, '2026-10-02')['count']);
        $db->exec('UPDATE batch_kob SET rest = ?', ['0']);
        self::assertNull($routine->run($ctx, $db, '2026-10-02'));
    }
}
