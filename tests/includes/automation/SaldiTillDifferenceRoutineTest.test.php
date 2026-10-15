<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiTillDifferenceRoutineTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
require_once __DIR__ . '/../../../includes/automation/routines/SaldiTillDifferenceRoutine.php';

final class SaldiTillDifferenceRoutineTest extends SaldiWatchDbTestCase
{
    public function testExactStoredTextsDateThresholdSignsAndCurrency(): void
    {
        $db = $this->env->db;
        $routine = new SaldiTillDifferenceRoutine();
        $ctx = $this->rule($routine->name());
        self::assertNull($routine->run($ctx, $db, '2026-10-02'));
        $db->insert('report', ['date' => '2026-10-01', 'type' => 'Head line',
            'description' => 'Cash count, box 7', 'report_number' => 70001, 'total' => '0']);
        foreach (
            [['2026-10-01', 'Difference DKK: ', '-50.005'],
            ['2026-10-01', 'Difference EUR:', '51.001'], ['2026-10-01', 'Difference DKK: ', '49.999'],
            ['2026-10-02', 'Difference DKK: ', '100'], ['2026-10-01', 'Optalt beholdning DKK: ', '100']] as $row
        ) {
            $db->insert('report', ['date' => $row[0], 'type' => 'cashCount', 'description' => $row[1],
                'report_number' => 70001, 'total' => $row[2]]);
        }
        $payload = $routine->run($ctx, $db, '2026-10-02');
        self::assertSame(2, $payload['count']);
        self::assertSame(['Kasse 7', 'Kasse 7'], array_column($payload['rows'], 'label'));
        self::assertSame(['-50.01', '51.00'], array_column($payload['rows'], 'amount'));
        self::assertSame(['DKK', 'EUR'], array_column($payload['rows'], 'currency'));
        $this->setting('assistant', 'till_difference_threshold', '51.002');
        self::assertNull($routine->run($ctx, $db, '2026-10-02'));
        self::assertSame('daily', $routine->schedule());
    }
}
