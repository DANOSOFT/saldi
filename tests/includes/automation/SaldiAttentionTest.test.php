<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiAttentionTest.test.php --- ai/mcp-assistant --- 2026.10.02
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

final class SaldiAttentionTest extends SaldiWatchDbTestCase
{
    public function testBudgetDowngradesFourthItemAndKeepsOtherOwnersIndependent(): void
    {
        $ctx = $this->env->ctx();
        foreach (range(1, 4) as $index) {
            $item = $this->inbox()->create(
                $ctx,
                'followup.task',
                ['title' => 'Fixture task ' . $index],
                ['attention' => 'interrupt']
            );
            self::assertSame($index <= 3 ? 'interrupt' : 'ambient', $item['attention']);
            self::assertSame($index === 4, $item['evidence']['attention_downgraded'] ?? false);
            if ($index === 1) {
                $this->inbox()->cancel($ctx, $item['id']);
            }
        }
        self::assertSame('interrupt', SaldiAttention::decide($this->env->db, 910002, null, 'interrupt'));
        self::assertSame('silent', SaldiAttention::decide($this->env->db, 910001, null, 'silent'));
    }

    public function testPermissionBudgetIsIndependentFromOwnerBudgetAndOtherPermissions(): void
    {
        $db = $this->env->db;
        $ctx = $this->rule('supplier_bank_change');
        $this->setting('assistant', 'interrupt_budget', '1');
        $payload = ['routine' => 'supplier_bank_change', 'scheduled_date' => '2026-10-02', 'count' => 1,
            'rows' => [['label' => 'Fixture change']]];
        $item = $this->inbox()->create($ctx, 'routine.finding', $payload, ['attention' => 'interrupt']);
        self::assertSame('interrupt', $item['attention']);
        self::assertSame('ambient', SaldiAttention::decide($db, null, 'creditors.read', 'interrupt'));
        self::assertSame('interrupt', SaldiAttention::decide($db, null, 'journal.read', 'interrupt'));
        self::assertSame('interrupt', SaldiAttention::decide($db, 910001, null, 'interrupt'));
        $again = $this->inbox()->create($ctx, 'routine.finding', $payload, ['attention' => 'interrupt']);
        self::assertTrue($again['deduplicated']);
        self::assertSame($item['id'], $again['id']);
        self::assertSame('interrupt', $again['attention']);
    }

    public function testLocalDayBoundsAcrossBothDstTransitionsAndPriorDayDoesNotCount(): void
    {
        self::assertSame(['2026-03-28 23:00:00', '2026-03-29 22:00:00'], SaldiAttention::dayBounds('2026-03-29'));
        self::assertSame(['2026-10-24 22:00:00', '2026-10-25 23:00:00'], SaldiAttention::dayBounds('2026-10-25'));
        $this->setting('assistant', 'interrupt_budget', '1');
        $item = $this->inbox()->create(
            $this->env->ctx(),
            'followup.task',
            ['title' => 'Prior day'],
            ['attention' => 'interrupt']
        );
        [$start] = SaldiAttention::dayBounds(SaldiToolRuntime::today());
        $before = (new DateTimeImmutable($start, new DateTimeZone('UTC')))->modify('-1 second')->format('Y-m-d H:i:s');
        $this->env->db->exec('UPDATE inbox_items SET created_at = ? WHERE id = ?', [$before, $item['id']]);
        self::assertSame('interrupt', SaldiAttention::decide($this->env->db, 910001, null, 'interrupt'));
        $this->env->db->exec('UPDATE inbox_items SET created_at = ? WHERE id = ?', [$start, $item['id']]);
        self::assertSame('ambient', SaldiAttention::decide($this->env->db, 910001, null, 'interrupt'));
    }

    public function testZeroAndClampedBudget(): void
    {
        $this->setting('assistant', 'interrupt_budget', '0');
        self::assertSame('ambient', SaldiAttention::decide($this->env->db, 910001, null, 'interrupt'));
        $this->setting('assistant', 'interrupt_budget', '-2');
        self::assertSame('ambient', SaldiAttention::decide($this->env->db, 910001, null, 'interrupt'));
        $this->setting('assistant', 'interrupt_budget', '99');
        foreach (range(1, 11) as $index) {
            $item = $this->inbox()->create(
                $this->env->ctx(),
                'followup.task',
                ['title' => 'Clamped task ' . $index],
                ['attention' => 'interrupt']
            );
            self::assertSame($index <= 10 ? 'interrupt' : 'ambient', $item['attention']);
        }
    }

    public function testAllocationLockSurvivesNestedDecideUntilCreationTransactionCompletes(): void
    {
        $db = $this->env->db;
        $other = SaldiDb::connect(saldi_runtime_config(), $this->env->name);
        try {
            $db->transaction(function () use ($db, $other): void {
                self::assertSame('interrupt', SaldiAttention::decide($db, 910001, null, 'interrupt'));
                $locked = $other->transaction(fn(): mixed => $other->value(
                    'SELECT CASE WHEN pg_try_advisory_xact_lock(hashtext(?)) THEN 1 ELSE 0 END',
                    ['saldi_attention:owner:910001']
                ));
                self::assertSame(0, $locked);
                $item = $this->inbox()->create(
                    $this->env->ctx(),
                    'followup.task',
                    ['title' => 'Concurrent fixture'],
                    ['attention' => 'interrupt']
                );
                self::assertSame('interrupt', $item['attention']);
            });
            self::assertSame(1, $other->transaction(fn(): mixed => $other->value(
                'SELECT CASE WHEN pg_try_advisory_xact_lock(hashtext(?)) THEN 1 ELSE 0 END',
                ['saldi_attention:owner:910001']
            )));
        } finally {
            $other->close();
        }
    }
}
