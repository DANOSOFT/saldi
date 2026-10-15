<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiVatMissingDocumentsRoutineTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
require_once __DIR__ . '/../../../includes/automation/routines/SaldiVatMissingDocumentsRoutine.php';

final class SaldiVatMissingDocumentsRoutineTest extends SaldiWatchDbTestCase
{
    public function testEmptyRunAndDeadlineAttention(): void
    {
        $routine = new SaldiVatMissingDocumentsRoutine();
        $ctx = $this->rule($routine->name());
        self::assertNull($routine->run($ctx, $this->env->db, '2026-11-25'));
        $this->journal();
        $this->line();
        $payload = $routine->run($ctx, $this->env->db, '2026-11-25');
        self::assertSame('interrupt', $payload['attention']);
        self::assertSame(1, $payload['count']);
        self::assertSame('125.01', $payload['rows'][0]['amount']);
        self::assertSame('ambient', $routine->run($ctx, $this->env->db, '2026-10-02')['attention']);
        $this->env->db->exec('UPDATE kassekladde SET dokument = ?', ['fixture.pdf']);
        self::assertNull($routine->run($ctx, $this->env->db, '2026-11-25'));
    }

    public function testRowsAreBoundedButCountIsComplete(): void
    {
        $this->journal();
        foreach (range(70001, 70025) as $id) {
            $this->line($id);
        }
        $routine = new SaldiVatMissingDocumentsRoutine();
        $payload = $routine->run($this->rule($routine->name()), $this->env->db, '2026-10-02');
        self::assertSame(25, $payload['count']);
        self::assertCount(20, $payload['rows']);
        $item = $this->inbox()->create($this->rule($routine->name()), 'routine.finding', $payload);
        self::assertCount(20, $item['payload']['rows']);
    }
}
