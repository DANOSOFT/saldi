<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiDraftToolsTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Idempotent proposals, custody receipts, evidence validation and input bounds.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cover F1 integration behavior and regression cases.
// 20261002 CL/LH Balanced journal fixture; receipts follow the returned item's status and deduplication.

declare(strict_types=1);

require_once __DIR__ . '/SaldiToolDbTestCase.php';

final class SaldiDraftToolsTest extends SaldiToolDbTestCase
{
    public function testEachDraftIsIdempotentAndDoesNotApplyChanges(): void
    {
        $db = $this->env->db;
        $before = [(int)$db->value('SELECT COUNT(*) FROM kassekladde'),
            (int)$db->value('SELECT COUNT(*) FROM assistant_memory')];
        $calls = [
            'propose_journal_entry' => ['date' => '2026-06-10',
                'kladde_id' => 11,
                'lines' => [['description' => 'New entry',
                        'debit' => ['ledger' => 'account',
                            'account' => '1000'],
                        'credit' => ['ledger' => 'account',
                            'account' => '4000'],
                        'amount' => '10.50']],
                'explanation' => 'Review the evidence',
                'citations' => ['saldi:journal:11',
                    'saldi:journal_line:111',
                    'saldi:account:1000@4',
                    'saldi:party:10',
                    'saldi:invoice:51',
                    'saldi:product:61',
                    'saldi:memory:2',
                    'saldi:memory:3',
                    'saldi:journal:999',
                    'made-up'],
                'tainted' => true,
                'request_id' => 'same-journal'],
            'propose_memory_fact' => ['scope' => 'user',
                'fact' => 'Prefer Danish summaries',
                'request_id' => 'same-memory'],
            'propose_memory_retraction' => ['memory_id' => 2,
                'reason' => 'No longer relevant',
                'request_id' => 'same-retract'],
            'create_followup_task' => ['title' => 'Review journal',
                'details' => 'Check the invoice',
                'due_date' => '2026-12-01',
                'screen' => SaldiScreens::link(
                    'journal',
                    11
                ),
                'request_id' => 'same-task'],
        ];
        foreach ($calls as $name => $input) {
            $first = $this->toolCall($name, $input + ['tainted' => true]);
            self::assertTrue($first['ok'], json_encode($first));
            $second = $this->toolCall($name, $input + ['tainted' => true]);
            self::assertTrue($second['ok']);
            self::assertSame($first['data']['id'], $second['data']['id']);
            self::assertTrue($second['data']['deduplicated']);
            self::assertSame('open', $first['data']['status']);
            self::assertSame('awaiting_approval', $first['receipt']['state']);
            self::assertSame($first['data']['id'], $first['receipt']['item_id']);
            self::assertFalse($first['receipt']['deduplicated']);
            self::assertSame('awaiting_approval', $second['receipt']['state']);
            self::assertTrue($second['receipt']['deduplicated']);
            self::assertFalse($first['committed']);
            self::assertSame(
                1,
                (int)$db->value(
                    "SELECT COUNT(*) FROM decision_log WHERE inbox_item_id = ? AND event = 'prop" .
                    "osed'",
                    [$first['data']['id']]
                )
            );
            self::assertTrue($first['data']['evidence']['tainted']);
            self::assertSame('tainted', $first['data']['card']['notices'][0]['kind']);
            $invalidTaint = $this->toolCall($name, array_replace($input, ['tainted' => 'yes']));
            self::assertSame('VALIDATION_ERROR', $invalidTaint['error']['code']);
            if ($name === 'propose_journal_entry') {
                self::assertCount(7, $first['data']['evidence']['citations']);
                self::assertTrue($first['data']['evidence']['tainted']);
                foreach ($first['data']['evidence']['citations'] as $citation) {
                    if (isset($citation['screen'])) {
                        self::assertTrue(SaldiScreens::isAllowed($citation['screen']));
                    }
                }
            }
        }
        self::assertSame(
            $before,
            [(int)$db->value('SELECT COUNT(*) FROM kassekladde'),
            (int)$db->value('SELECT COUNT(*) FROM assistant_memory')]
        );
        self::assertSame(4, (int)$db->value('SELECT COUNT(*) FROM inbox_items'));
    }

    public function testRetryReceiptSaysWhenTheItemIsAlreadyDecided(): void
    {
        $ctx = $this->env->ctx();
        $task = ['title' => 'Review journal', 'request_id' => 'decided-task'];
        $first = $this->toolCall('create_followup_task', $task);
        $item = $this->inbox()->get($ctx, $first['data']['id']);
        $this->approve($item);
        $applied = $this->toolCall('create_followup_task', $task);
        self::assertTrue($applied['ok']);
        self::assertSame('applied', $applied['receipt']['state']);
        self::assertSame('applied', $applied['receipt']['status']);
        self::assertTrue($applied['receipt']['deduplicated']);
        self::assertStringNotContainsString('til godkendelse', $applied['receipt']['label_da']);
        $other = $this->toolCall('create_followup_task', ['title' => 'Reject me', 'request_id' => 'rejected-task']);
        $item = $this->inbox()->get($ctx, $other['data']['id']);
        $this->inbox()->decide($ctx, $item['id'], 'reject', $item['digest'], $ctx->tenantHash());
        $closed = $this->toolCall('create_followup_task', ['title' => 'Reject me', 'request_id' => 'rejected-task']);
        self::assertSame('closed', $closed['receipt']['state']);
        self::assertSame('rejected', $closed['receipt']['status']);
        self::assertStringContainsString('afvist', $closed['receipt']['label_da']);
        // The same request_id for a different proposal is an error, not a receipt.
        $reused = $this->toolCall('create_followup_task', ['title' => 'Something else', 'request_id' => 'rejected-task']);
        self::assertFalse($reused['ok']);
        self::assertSame('VALIDATION_ERROR', $reused['error']['code']);
        self::assertSame('request_id_reused', $reused['error']['detail']['reason']);
        foreach (['open' => 'awaiting_approval', 'applied' => 'applied', 'rejected' => 'closed', 'cancelled' => 'closed', 'expired' => 'closed', 'undone' => 'closed'] as $status => $state) {
            $receipt = SaldiMcpServer::draftReceipt(['id' => 5, 'status' => $status, 'deduplicated' => true]);
            self::assertSame($state, $receipt['state']);
            self::assertSame(5, $receipt['item_id']);
        }
    }

    public function testCitationVisibilityAndInputLimits(): void
    {
        $ctx = $this->only(2, '1');
        self::assertNull(SaldiDraftTools::resolve($ctx, $this->env->db, 'saldi:invoice:51'));
        self::assertNull(SaldiDraftTools::resolve($ctx, $this->env->db, 'saldi:party:10'));
        self::assertNull(SaldiDraftTools::resolve($ctx, $this->env->db, 'saldi:account:1000@3'));
        self::assertNull(SaldiDraftTools::resolve($ctx, $this->env->db, 'saldi:memory:3'));
        self::assertNull(SaldiDraftTools::resolve($ctx, $this->env->db, 'saldi:journal:11@4'));
        self::assertNotNull(SaldiDraftTools::resolve($ctx, $this->env->db, 'saldi:journal:11'));
        $this->hideRevenue();
        $noAdmin = $this->env->ctx(910001, '1022222222222222');
        self::assertNull(SaldiDraftTools::resolve($noAdmin, $this->env->db, 'saldi:account:2000@4'));
        self::assertNull(SaldiDraftTools::resolve($noAdmin, $this->env->db, 'saldi:invoice:51'));
        $invalid = [
            ['create_followup_task', ['title' => 'Good title']],
            ['create_followup_task', ['title' => 'Good title', 'request_id' => str_repeat('x', 65)]],
            ['create_followup_task',
                ['title' => 'Good title',
                    'request_id' => 'id',
                    'screen' => 'finans/kassekladde.php?kladde_id=11&delete=1']],
            ['propose_memory_fact', ['scope' => 'user', 'fact' => str_repeat('æ', 301), 'request_id' => 'long']],
            ['propose_memory_retraction', ['memory_id' => 3, 'request_id' => 'private']],
            ['propose_journal_entry',
                ['date' => '2026-06-01',
                    'lines' => [],
                    'explanation' => 'x',
                    'request_id' => 'none']],
            ['propose_journal_entry',
                ['date' => '2026-06-01',
                    'lines' => [['description' => 'No side',
                            'amount' => '1']],
                    'explanation' => 'x',
                    'request_id' => 'side']],
        ];
        foreach ($invalid as [$name, $args]) {
            $e = $this->toolCall($name, $args);
            self::assertFalse($e['ok'], $name);
            self::assertContains($e['error']['code'], ['VALIDATION_ERROR', 'NOT_FOUND']);
        }
        $readOnly = $this->env->ctx(910001, null, 'agent:saldi-assist', 99, ['read']);
        $e = $this->toolCall(
            'create_followup_task',
            ['title' => 'Review records',
                'request_id' => 'read-only'],
            $readOnly
        );
        self::assertSame('PERMISSION_DENIED', $e['error']['code']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
    }
}
