<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiPostingRuleCandidatesRoutineTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Deterministic candidates, tool contracts and permission regression tests.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiPostingTestCase.php';

final class SaldiPostingRuleCandidatesRoutineTest extends SaldiPostingTestCase
{
    private function context(): SaldiTenantContext
    {
        $context = $this->env->ctx();
        return SaldiTenantContext::forSystem(
            $context->installId,
            $context->regnskabId,
            $context->db,
            $context->dbType,
            'rule:posting_rule_candidates',
            $context->fiscalYear,
            $context->baseCurrency
        );
    }

    public function testRoutineFindsEightyPercentDominanceAndCapsAtFive(): void
    {
        $routine = new SaldiPostingRuleCandidatesRoutine();
        self::assertSame('posting_rule_candidates', $routine->name());
        self::assertSame('weekly', $routine->schedule());
        self::assertSame('journal.draft', $routine->permission());
        self::assertSame(5944, $routine->titleTextId());
        foreach (range(1005, 1011) as $number) {
            $this->repeat(4, ['kredit' => (string)$number]);
            $this->line(['kredit' => (string)$number, 'debet' => '2900']);
        }
        $proposals = $routine->proposals($this->context(), $this->env->db, $this->date());
        self::assertCount(5, $proposals);
        self::assertSame('2800', $proposals[0]['account']);
        self::assertSame('rule:supplier:' . sha1('1005'), $proposals[0]['dedupe_key']);
        self::assertSame(
            '4 af 5 linjer med Leverandør 1005 Adobe er bogført på 2800 Software siden ' . $this->date(-120) . '.',
            $proposals[0]['explanation']
        );
        self::assertSame(5, $routine->run($this->context(), $this->env->db, $this->date())['count']);
    }

    public function testCandidateDenominatorIncludesOtherAccountsAndIgnoresUnpostedOldAndClosedParties(): void
    {
        $this->repeat(4);
        $this->repeat(2, ['debet' => '2900']);
        $this->repeat(4, ['kredit' => '1006', 'kladde_id' => 42]);
        $this->repeat(4, ['kredit' => '1007', 'transdate' => $this->date(-121)]);
        $this->repeat(4, ['kredit' => '1008']);
        $this->env->db->exec("UPDATE adresser SET lukket = 'on' WHERE kontonr = ?", ['1008']);
        $this->repeat(4, ['kredit' => '1009', 'debet' => '5999']);
        $this->repeat(4, ['kredit' => '1010', 'debet' => '6000']);
        $routine = new SaldiPostingRuleCandidatesRoutine();
        self::assertSame([], $routine->proposals($this->context(), $this->env->db, $this->date()));
        $this->repeat(4, ['d_type' => 'D', 'debet' => '2005', 'k_type' => 'F', 'kredit' => '2800']);
        $proposals = $routine->proposals($this->context(), $this->env->db, $this->date());
        self::assertCount(1, $proposals);
        self::assertSame(['type' => 'customer', 'value' => '2005'], $proposals[0]['match']);
    }

    public function testWorkerCreatesProposalsOnceAndRecentRejectionsSuppressThem(): void
    {
        $this->repeat(4);
        $registry = SaldiJobRegistry::default();
        $this->setting('automation', 'routine.posting_rule_candidates', 'on');
        $context = $this->context();
        $job = ['payload' => json_encode(['scheduled_date' => $this->date()]), 'dedupe_key' => 'fixture-week'];
        $handler = $registry->get('routine.posting_rule_candidates');
        $first = $this->env->db->transaction(fn() => $handler($context, $this->env->db, $job));
        self::assertSame('proposals', $first['result']['value']);
        $item = $this->inbox()->get($this->env->ctx(), $first['result']['item_ids'][0]);
        self::assertSame('rule.posting', $item['kind']);
        self::assertSame('rule', $item['origin']);
        self::assertSame('ambient', $item['attention']);
        self::assertNull($item['owner_user_id']);
        self::assertSame(14, (int)round((strtotime($item['expires_at']) - time()) / 86400));
        $repeat = $this->env->db->transaction(fn() => $handler($context, $this->env->db, $job));
        self::assertSame('none', $repeat['result']['value']);
        self::assertSame(1, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
        $this->inbox()->decide(
            $this->env->ctx(),
            $item['id'],
            'reject',
            $item['digest'],
            $this->env->ctx()->tenantHash()
        );
        $routine = new SaldiPostingRuleCandidatesRoutine();
        self::assertSame([], $routine->proposals($context, $this->env->db, $this->date()));
        $this->env->db->exec('UPDATE inbox_items SET decided_at = ? WHERE id = ?', [
            $this->date(-91) . ' 00:00:00', $item['id'],
        ]);
        self::assertCount(1, $routine->proposals($context, $this->env->db, $this->date()));
        $this->rule();
        self::assertSame([], $routine->proposals($context, $this->env->db, $this->date()));
    }

    public function testEmptyRunIsHonestAndInvalidDateAndPermissionAreRefused(): void
    {
        $routine = new SaldiPostingRuleCandidatesRoutine();
        self::assertNull($routine->run($this->context(), $this->env->db, $this->date()));
        self::assertSame([], $routine->proposals($this->context(), $this->env->db, $this->date()));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
        $this->error('VALIDATION_ERROR', fn() => $routine->proposals($this->context(), $this->env->db, '2026-02-30'));
        $this->error('PERMISSION_DENIED', fn() => $routine->proposals(
            $this->env->ctx(910001, str_repeat('2', 16)),
            $this->env->db,
            $this->date()
        ));
    }
}
