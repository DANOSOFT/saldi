<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiPostingRuleKindTest.test.php --- ai/mcp-assistant --- 2026.10.02
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

final class SaldiPostingRuleKindTest extends SaldiPostingTestCase
{
    public function testApprovalCreatesRuleWithProvenanceAndNeverWritesJournalRows(): void
    {
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        $item = $this->inbox()->create($agent, 'rule.posting', [
            'match' => ['type' => 'text', 'value' => 'Adobe ABONNEMENT 2026'],
            'account' => '2800', 'vat_code' => 'K1', 'description' => 'Abonnement',
        ], ['evidence' => ['explanation' => 'Brug samme konto hver måned.']]);
        $stored = $this->env->db->one('SELECT * FROM inbox_items WHERE id = ?', [$item['id']]);
        self::assertSame('permission', $stored['visibility']);
        self::assertSame('journal.draft', $stored['assignee_permission']);
        self::assertSame('adobe abonnement', $item['payload']['match']['value']);
        self::assertSame('rule:text:' . sha1('adobe abonnement'), $stored['dedupe_key']);
        self::assertLessThanOrEqual(120, mb_strlen($item['title']));
        $applied = $this->approve($item, $this->env->ctx(910002));
        $rule = SaldiPostingRules::get($this->env->db, $applied['result']['rule_id']);
        self::assertSame('agent:saldi-assist', $rule['created_by']);
        self::assertSame(910002, (int)$rule['approved_by']);
        self::assertSame($item['id'], (int)$rule['inbox_item_id']);
        self::assertSame('Brug samme konto hver måned.', $rule['note']);
        self::assertTrue($applied['can_undo']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
        self::assertSame(3, (int)$this->env->db->value('SELECT COUNT(*) FROM kladdeliste'));
    }

    public function testPartyRuleAcceptsAnExactUniqueNameAndExplainsOtherwise(): void
    {
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        $this->party(7701, 'Hjertegarn Navnetest');
        $item = $this->inbox()->create($agent, 'rule.posting', [
            'match' => ['type' => 'supplier', 'value' => 'hjertegarn navnetest'], 'account' => '2800',
        ], ['evidence' => ['explanation' => 'Navn i stedet for nummer.']]);
        self::assertSame('7701', $item['payload']['match']['value']);
        try {
            $this->inbox()->create($agent, 'rule.posting', [
                'match' => ['type' => 'supplier', 'value' => 'Findes Ikke ApS'], 'account' => '2800',
            ], ['evidence' => ['explanation' => 'x']]);
            self::fail('Expected unknown_party');
        } catch (SaldiRuntimeError $e) {
            self::assertSame('unknown_party', $e->detail['reason'] ?? null);
            self::assertStringContainsString('search_parties', $e->getMessage());
        }
    }

    public function testReplaceUndoAndRepeatedReplacementRestoreOnlyTheOwnedRule(): void
    {
        $original = $this->rule();
        $originalId = $original['result']['rule_id'];
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $item = $this->inbox()->create($this->env->ctx(), 'rule.posting', [
                'match' => ['type' => 'supplier', 'value' => '1005'], 'account' => '2900',
                'replaces_rule_id' => 999999,
            ]);
            self::assertSame($originalId, $item['payload']['replaces_rule_id']);
            $card = $this->inbox()->preview($this->env->ctx(), $item['id'])['card'];
            self::assertCount(4, $card['columns']);
            self::assertSame('Erstatter regel ' . $originalId, $card['fields'][0]['value']);
            $new = $this->approve($item);
            self::assertSame($originalId, $new['result']['replaced_rule_id']);
            self::assertSame('replaced', SaldiPostingRules::get($this->env->db, $originalId)['retire_reason']);
            self::assertFalse($this->inbox()->get($this->env->ctx(), $original['id'])['can_undo']);
            $undone = $this->inbox()->undo($this->env->ctx(), $new['id'], $this->env->ctx()->tenantHash());
            self::assertSame($originalId, $undone['result']['reactivated_rule_id']);
            self::assertSame('active', SaldiPostingRules::get($this->env->db, $originalId)['status']);
            self::assertCount(1, SaldiPostingRules::active($this->env->db));
        }
    }

    public function testUndoDoesNotReactivateAnUnrelatedRetirement(): void
    {
        $original = $this->rule();
        $replacement = $this->rule(['account' => '2900']);
        $this->env->db->exec('UPDATE posting_rules SET retire_reason = ? WHERE id = ?', [
            'manually stopped', $original['result']['rule_id'],
        ]);
        $undone = $this->inbox()->undo($this->env->ctx(), $replacement['id'], $this->env->ctx()->tenantHash());
        self::assertNull($undone['result']['reactivated_rule_id']);
        self::assertSame([], SaldiPostingRules::active($this->env->db));
    }

    public function testApprovalRejectsStaleMatchAndRechecksClosedAccount(): void
    {
        $original = $this->rule();
        $item = $this->inbox()->create($this->env->ctx(), 'rule.posting', [
            'match' => ['type' => 'supplier', 'value' => '1005'], 'account' => '2900',
        ]);
        SaldiPostingRules::retire($this->env->db, $this->env->ctx(), $original['result']['rule_id'], 'stop');
        $this->error('STALE_CONTEXT', fn() => $this->approve($item));
        self::assertSame('open', $this->inbox()->get($this->env->ctx(), $item['id'])['status']);
        $this->env->db->exec("UPDATE kontoplan SET lukket = 'on' WHERE kontonr = 2900");
        $this->error('VALIDATION_ERROR', fn() => $this->approve($item));
        self::assertSame(1, (int)$this->env->db->value('SELECT COUNT(*) FROM posting_rules'));
    }

    public function testValidationCoversPartyTextFinanceAndVat(): void
    {
        $kind = new SaldiPostingRuleKind();
        $base = ['match' => ['type' => 'supplier', 'value' => '1005'], 'account' => '2800'];
        foreach (['', 'none', 'K1', 'S1', 'E1', 'Y1'] as $vat) {
            $normal = $kind->validate($this->env->ctx(), $this->env->db, $base + ['vat_code' => $vat]);
            self::assertSame($vat, $normal['payload']['vat_code']);
        }
        foreach (
            [['match' => ['type' => 'text', 'value' => '12345']],
            ['match' => ['type' => 'text', 'value' => 'ab']],
            ['match' => ['type' => 'text', 'value' => str_repeat('x', 61)]],
            ['match' => ['type' => 'supplier', 'value' => '2005']],
            ['match' => ['type' => 'customer', 'value' => '1005']],
            ['match' => ['type' => 'supplier', 'value' => '1 OR 1=1']],
            ['account' => '5900'], ['account' => '5999'], ['account' => '6000'],
            ['vat_code' => 'K9'], ['vat_code' => 'k1'], ['description' => str_repeat('x', 101)]] as $overrides
        ) {
            $this->error('VALIDATION_ERROR', fn() => $kind->validate(
                $this->env->ctx(),
                $this->env->db,
                array_merge($base, $overrides)
            ));
        }
        $this->env->db->exec("UPDATE adresser SET lukket = 'on' WHERE kontonr = ?", ['1005']);
        $this->error('VALIDATION_ERROR', fn() => $kind->validate($this->env->ctx(), $this->env->db, $base));
    }

    public function testRulesMayProposeButNeverApplyOrUndo(): void
    {
        $context = $this->env->ctx();
        $routine = SaldiTenantContext::forSystem(
            $context->installId,
            $context->regnskabId,
            $context->db,
            $context->dbType,
            'rule:posting_rule_candidates',
            $context->fiscalYear,
            $context->baseCurrency
        );
        $item = $this->inbox()->create($routine, 'rule.posting', [
            'match' => ['type' => 'supplier', 'value' => '1005'], 'account' => '2800',
        ]);
        self::assertNull($item['owner_user_id']);
        self::assertSame('rule', $item['origin']);
        $kind = new SaldiPostingRuleKind();
        $this->error('PERMISSION_DENIED', fn() => $kind->apply($routine, $this->env->db, $item));
        $this->error('PERMISSION_DENIED', fn() => $kind->undo($routine, $this->env->db, $item));
        self::assertSame('applied', $this->approve($item)['status']);
    }
}
