<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiMasterDataHygieneRoutineTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// Weekly routine identity, bounded findings and shared tool parity.
//
// 20261004 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiInsightDbTestCase.php';
require_once __DIR__ . '/../../../includes/automation/routines/SaldiMasterDataHygieneRoutine.php';

final class SaldiMasterDataHygieneRoutineTest extends SaldiInsightDbTestCase
{
    public function testRoutineUsesToolFindingsAndDoesNotWrite(): void
    {
        $routine = new SaldiMasterDataHygieneRoutine();
        $ctx = SaldiTenantContext::forSystem(
            'test',
            $this->env->ctx()->regnskabId,
            $this->env->name,
            'postgresql',
            'rule:master_data_hygiene',
            4,
            'DKK'
        );
        self::assertSame('weekly', $routine->schedule());
        self::assertSame('company.read', $routine->permission());
        self::assertSame(6171, $routine->titleTextId());
        $payload = $routine->run($ctx, $this->env->db, '2026-06-15');
        $tool = SaldiInsightTools::masterData($this->env->ctx(), $this->env->db, []);
        self::assertSame($tool['data']['rows'], $payload['rows']);
        self::assertSame($tool['data']['total'], $payload['count']);
        self::assertSame('2026-06-15', $payload['scheduled_date']);
        self::assertSame('master_data_hygiene', $payload['routine']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
        $this->error('VALIDATION_ERROR', fn() => $routine->run($ctx, $this->env->db, '2026-02-30'));
    }

    public function testRoutineCapsRowsButPreservesTotalAndEmptyRunIsNull(): void
    {
        $db = $this->env->db;
        $db->exec('DELETE FROM adresser');
        $db->exec('DELETE FROM varer');
        $routine = new SaldiMasterDataHygieneRoutine();
        self::assertNull($routine->run($this->env->ctx(), $db, '2026-06-15'));
        for ($i = 1; $i <= 25; $i++) {
            $db->insert('varer', ['varenr' => 'M' . $i, 'beskrivelse' => 'Manglende pris',
                'kostpris' => '0', 'salgspris' => '10']);
        }
        $payload = $routine->run($this->env->ctx(), $db, '2026-06-15');
        self::assertSame(25, $payload['count']);
        self::assertCount(20, $payload['rows']);
    }
}
