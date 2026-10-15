<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiPostingRulesTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Approval custody, validation and audited rule lifecycle regression tests.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiPostingTestCase.php';

final class SaldiPostingRulesTest extends SaldiPostingTestCase
{
    public function testDirectRetirementIsIdempotentHumanOnlyAndAppendOnly(): void
    {
        $item = $this->rule();
        $id = $item['result']['rule_id'];
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        $this->error('PERMISSION_DENIED', fn() => SaldiPostingRules::retire($this->env->db, $agent, $id, 'stop'));
        $rights = str_repeat('1', 16);
        $rights[2] = '2';
        $this->error('PERMISSION_DENIED', fn() => SaldiPostingRules::retire(
            $this->env->db,
            $this->env->ctx(910001, $rights),
            $id,
            'stop'
        ));
        SaldiPostingRules::retire($this->env->db, $this->env->ctx(), $id, 'Ikke længere relevant.');
        SaldiPostingRules::retire($this->env->db, $this->env->ctx(), $id, 'retry');
        $events = $this->env->db->all("SELECT * FROM decision_log WHERE event = 'rule_retired'");
        self::assertCount(1, $events);
        self::assertSame('rule:' . $id . ':retire', $events[0]['idempotency_key']);
        self::assertSame('Ikke længere relevant.', SaldiPostingRules::get($this->env->db, $id)['retire_reason']);
        self::assertSame([], SaldiPostingRules::active($this->env->db));
        self::assertSame(1, (int)$this->env->db->value('SELECT COUNT(*) FROM posting_rules'));
    }

    public function testLabelsAndMissingRule(): void
    {
        self::assertSame('Leverandør 1005 Adobe', SaldiPostingRules::label([
            'match_type' => 'supplier', 'match_value' => '1005',
        ], $this->env->db));
        self::assertSame('Kunde 2005 Kunde Æble', SaldiPostingRules::label([
            'match_type' => 'customer', 'match_value' => '2005',
        ], $this->env->db));
        self::assertSame('Teksten indeholder »abonnement«', SaldiPostingRules::label([
            'match_type' => 'text', 'match_value' => 'abonnement',
        ], $this->env->db));
        self::assertNull(SaldiPostingRules::get($this->env->db, 999999));
        $this->error('NOT_FOUND', fn() => SaldiPostingRules::retire($this->env->db, $this->env->ctx(), 999999, 'stop'));
    }
}
