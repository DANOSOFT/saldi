<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiPostingRuleRetireKindTest.test.php --- ai/mcp-assistant --- 2026.10.02
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

final class SaldiPostingRuleRetireKindTest extends SaldiPostingTestCase
{
    public function testRetireApprovalIsAuditedAndCannotBeUndone(): void
    {
        $rule = $this->rule();
        $item = $this->inbox()->create($this->env->ctx(), 'rule.retire', [
            'rule_id' => $rule['result']['rule_id'], 'reason' => 'Brug en anden konto fremover.',
        ]);
        self::assertSame(
            'permission',
            $this->env->db->value('SELECT visibility FROM inbox_items WHERE id = ?', [$item['id']])
        );
        $applied = $this->approve($item);
        self::assertFalse($applied['can_undo']);
        self::assertSame('retired', SaldiPostingRules::get($this->env->db, $rule['result']['rule_id'])['status']);
        $event = $this->env->db->one("SELECT * FROM decision_log WHERE event = 'rule_retired'");
        self::assertSame(910001, (int)$event['approved_by']);
        self::assertSame('user:910001', $event['actor']);
        self::assertSame($item['id'], (int)$event['inbox_item_id']);
        self::assertSame('Brug en anden konto fremover.', json_decode($event['detail'], true)['reason']);
        $this->error('CONFLICT', fn() => $this->inbox()->undo(
            $this->env->ctx(),
            $item['id'],
            $this->env->ctx()->tenantHash()
        ));
        self::assertSame([], $this->suggest(['party' => ['ledger' => 'supplier', 'account' => '1005']])['suggestions']);
    }

    public function testValidationAndApprovalRecheckActiveRuleAndReason(): void
    {
        $kind = new SaldiPostingRuleRetireKind();
        foreach ([['rule_id' => 0], ['rule_id' => '1']] as $input) {
            $this->error('VALIDATION_ERROR', fn() => $kind->validate($this->env->ctx(), $this->env->db, $input));
        }
        $this->error('NOT_FOUND', fn() => $kind->validate($this->env->ctx(), $this->env->db, ['rule_id' => 999999]));
        $rule = $this->rule();
        $input = ['rule_id' => $rule['result']['rule_id']];
        $this->error('VALIDATION_ERROR', fn() => $kind->validate(
            $this->env->ctx(),
            $this->env->db,
            $input + ['reason' => str_repeat('x', 256)]
        ));
        $item = $this->inbox()->create($this->env->ctx(), 'rule.retire', $input);
        $card = $this->inbox()->preview($this->env->ctx(), $item['id'])['card'];
        self::assertStringContainsString('Tidligere posteringer ændres ikke.', $card['next_step']);
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        $this->error('PERMISSION_DENIED', fn() => $kind->apply($agent, $this->env->db, $item));
        SaldiPostingRules::retire($this->env->db, $this->env->ctx(), $input['rule_id'], 'direct');
        $this->error('NOT_FOUND', fn() => $this->approve($item));
    }
}
