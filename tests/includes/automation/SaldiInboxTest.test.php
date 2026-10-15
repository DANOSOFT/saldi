<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiInboxTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// Inbox lifecycle, visibility, idempotency, rollback audit and rule-only findings.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cover F1 integration behavior and regression cases.
// 20261002 CL/LH request_id reuse for another proposal fails; retries of decided requests return the item.
//                list() over several statuses.
// 20261002 CDX/LH Cover companion foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';

final class SaldiInboxTest extends SaldiRuntimeDbTestCase
{
    public function testRequestIdempotencyOpenKeyVisibilityAndHumanDecisions(): void
    {
        $ctx = $this->env->ctx();
        $service = $this->inbox();
        $item = $service->create($ctx, 'followup.task', ['title' => 'Follow this up'], ['request_id' => 'req-1']);
        $duplicate = $service->create($ctx, 'followup.task', ['title' => 'Follow this up'], ['request_id' => 'req-1']);
        self::assertSame($item['id'], $duplicate['id']);
        self::assertTrue($duplicate['deduplicated']);
        self::assertSame(1, $service->countOpen($ctx));
        self::assertCount(1, $service->list($ctx));
        self::assertSame(0, $service->countOpen($this->env->ctx(910002)));
        $this->error('NOT_FOUND', fn() => $service->get($this->env->ctx(910002), $item['id']));
        $this->error('NOT_FOUND', fn() => $service->preview($this->env->ctx(910002), $item['id']));
        $this->error('NOT_FOUND', fn() => $service->decide($this->env->ctx(910002), $item['id'], 'reject', $item['digest'], $ctx->tenantHash()));
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        $this->error('PERMISSION_DENIED', fn() => $service->decide($agent, $item['id'], 'approve', $item['digest'], $ctx->tenantHash()));
        $this->error('PERMISSION_DENIED', fn() => $service->undo($agent, $item['id'], $ctx->tenantHash()));
        self::assertSame('digest_mismatch', $this->error('CONFLICT', fn() => $service->decide($ctx, $item['id'], 'approve', str_repeat('0', 64), $ctx->tenantHash()))->detail['reason']);
        self::assertSame('tenant_changed', $this->error('CONFLICT', fn() => $service->decide($ctx, $item['id'], 'approve', $item['digest'], str_repeat('0', 32)))->detail['reason']);
        $applied = $this->approve($item);
        self::assertSame('applied', $applied['status']);
        self::assertTrue($applied['result']['done']);
        self::assertSame($ctx->userId, $applied['decided_by']);
        self::assertFalse($applied['can_undo']);
        self::assertSame(0, $service->countOpen($ctx));
        self::assertSame('not_open', $this->error('CONFLICT', fn() => $this->approve($item))->detail['reason']);
        $retry = $service->create($ctx, 'followup.task', ['title' => 'Follow this up'], ['request_id' => 'req-1']);
        self::assertTrue($retry['deduplicated']);
        self::assertSame('applied', $retry['status']);
    }

    public function testReusedRequestIdForAnotherProposalIsRefused(): void
    {
        $ctx = $this->env->ctx();
        $s = $this->inbox();
        $item = $s->create($ctx, 'followup.task', ['title' => 'Follow this up'], ['request_id' => 'r1']);
        foreach ([['followup.task', ['title' => 'A different title']], ['memory.fact', ['scope' => 'user', 'fact' => 'I prefer monthly reports']]] as [$kind, $input]) {
            $e = $this->error('VALIDATION_ERROR', fn() => $s->create($ctx, $kind, $input, ['request_id' => 'r1']));
            self::assertSame('request_id_reused', $e->detail['reason']);
            self::assertSame(['$.request_id'], $e->detail['fields']);
        }
        // Still refused once the first item is decided; another user's or grant's 'r1' is a separate key.
        $s->decide($ctx, $item['id'], 'reject', $item['digest'], $ctx->tenantHash());
        $this->error('VALIDATION_ERROR', fn() => $s->create($ctx, 'followup.task', ['title' => 'A different title'], ['request_id' => 'r1']));
        self::assertFalse($s->create($this->env->ctx(910002), 'followup.task', ['title' => 'A different title'], ['request_id' => 'r1'])['deduplicated']);
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        self::assertFalse($s->create($agent, 'followup.task', ['title' => 'A different title'], ['request_id' => 'r1'])['deduplicated']);
        self::assertSame(3, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
    }

    public function testRetryOfADecidedRequestReturnsTheItemEvenWhenItNoLongerValidates(): void
    {
        $ctx = $this->env->ctx();
        $s = $this->inbox();
        $fact = $this->approve($s->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => 'I prefer monthly reports']));
        $input = ['memory_id' => $fact['result']['memory_id'], 'reason' => 'Outdated'];
        $retract = $s->create($ctx, 'memory.retract', $input, ['request_id' => 'retract-1']);
        self::assertSame('applied', $this->approve($retract)['status']);
        // The memory is gone, so validate() alone would say NOT_FOUND; the retry still gets its applied item.
        $this->error('NOT_FOUND', fn() => $s->create($ctx, 'memory.retract', $input));
        $retry = $s->create($ctx, 'memory.retract', $input, ['request_id' => 'retract-1']);
        self::assertSame($retract['id'], $retry['id']);
        self::assertTrue($retry['deduplicated']);
        self::assertSame('applied', $retry['status']);
        // Kind is checked before validation, so a different kind under the same id is still refused.
        self::assertSame('request_id_reused', $this->error('VALIDATION_ERROR', fn() => $s->create($ctx, 'followup.task', ['title' => 'Other task'], ['request_id' => 'retract-1']))->detail['reason']);
        // An invisible item behind the key is never revealed.
        $this->env->db->exec('UPDATE inbox_items SET owner_user_id = ? WHERE id = ?', [910002, $retract['id']]);
        self::assertSame('not_open', $this->error('CONFLICT', fn() => $s->create($ctx, 'memory.retract', $input, ['request_id' => 'retract-1']))->detail['reason']);
    }

    public function testOpenDedupeDoesNotRevealInvisiblePrivateFacts(): void
    {
        $ctx = $this->env->ctx();
        $item = $this->inbox()->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => 'I prefer monthly reports']);
        $same = $this->inbox()->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => 'I prefer monthly reports']);
        self::assertSame($item['id'], $same['id']);
        self::assertTrue($same['deduplicated']);
        $db = $this->env->db;
        $db->exec('UPDATE inbox_items SET owner_user_id = ? WHERE id = ?', [910002, $item['id']]);
        $this->error('CONFLICT', fn() => $this->inbox()->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => 'I prefer monthly reports']));
        self::assertSame(1, (int)$db->value('SELECT COUNT(*) FROM inbox_items'));
    }

    public function testRejectCancelExpireAreTerminalAndExpirySurvivesConflict(): void
    {
        $ctx = $this->env->ctx();
        $s = $this->inbox();
        $item = $s->create($ctx, 'followup.task', ['title' => 'Reject me']);
        self::assertSame('rejected', $s->decide($ctx, $item['id'], 'reject', $item['digest'], $ctx->tenantHash(), 'Reason')['status']);
        $cancel = $s->create($ctx, 'followup.task', ['title' => 'Cancel me']);
        self::assertSame('cancelled', $s->cancel($ctx, $cancel['id'])['status']);
        $this->error('CONFLICT', fn() => $s->cancel($ctx, $cancel['id']));
        $expired = $s->create($ctx, 'followup.task', ['title' => 'Expire me'], ['expires_at' => '2000-01-01T00:00:00Z']);
        self::assertSame('expired', $this->error('CONFLICT', fn() => $this->approve($expired))->detail['reason']);
        self::assertSame('expired', $s->get($ctx, $expired['id'])['status']);
        self::assertNull($this->env->db->value('SELECT open_key FROM inbox_items WHERE id = ?', [$expired['id']]));
        $s->create($ctx, 'followup.task', ['title' => 'Worker expiry'], ['expires_at' => '2000-01-01 00:00:00']);
        self::assertSame(1, $s->expireDue());
        self::assertSame(0, $s->expireDue());
        self::assertSame(2, (int)$this->env->db->value("SELECT COUNT(*) FROM decision_log WHERE event = 'expired'"));
    }

    public function testAgentCancellationIsBoundToItsGrant(): void
    {
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        $other = $this->env->ctx(910001, null, 'agent:saldi-assist', 24, ['read', 'draft']);
        $item = $this->inbox()->create($agent, 'followup.task', ['title' => 'Agent draft']);
        $this->error('PERMISSION_DENIED', fn() => $this->inbox()->cancel($other, $item['id']));
        self::assertSame('cancelled', $this->inbox()->cancel($agent, $item['id'])['status']);
    }

    public function testRuleCancellationRequiresItsOwnOpenFinding(): void
    {
        $ctx = $this->env->ctx();
        $rule = SaldiTenantContext::forSystem($ctx->installId, $ctx->regnskabId, $ctx->db, $ctx->dbType, 'rule:low_stock', 4, 'DKK');
        $item = $this->inbox()->create($rule, 'routine.finding', ['routine' => 'low_stock', 'scheduled_date' => '2026-10-02', 'count' => 1, 'rows' => [['label' => 'Product', 'screen' => SaldiScreens::link('product', 1)]]]);
        $db = $this->env->db;
        $db->exec('UPDATE inbox_items SET created_by = ? WHERE id = ?', ['rule:other', $item['id']]);
        $this->error('PERMISSION_DENIED', fn() => $this->inbox()->cancel($rule, $item['id']));
        $db->exec('UPDATE inbox_items SET created_by = ?, kind = ? WHERE id = ?', [$rule->principal, 'followup.task', $item['id']]);
        $this->error('PERMISSION_DENIED', fn() => $this->inbox()->cancel($rule, $item['id']));
        $db->exec('UPDATE inbox_items SET kind = ? WHERE id = ?', ['routine.finding', $item['id']]);
        foreach (['approve', 'reject'] as $decision) {
            $this->error('PERMISSION_DENIED', fn() => $this->inbox()->decide($rule, $item['id'], $decision, $item['digest'], $ctx->tenantHash()));
        }
        $this->error('PERMISSION_DENIED', fn() => $this->inbox()->undo($rule, $item['id'], $ctx->tenantHash()));
        $cancelled = $this->inbox()->cancel($rule, $item['id']);
        self::assertSame('cancelled', $cancelled['status']);
        self::assertNull($cancelled['decided_by']);
        self::assertNull($cancelled['decided_by_name']);
        self::assertNull($db->value('SELECT open_key FROM inbox_items WHERE id = ?', [$item['id']]));
        self::assertSame($rule->principal, $db->value("SELECT actor FROM decision_log WHERE inbox_item_id = ? AND event = 'cancelled'", [$item['id']]));
        self::assertSame('not_open', $this->error('CONFLICT', fn() => $this->inbox()->cancel($rule, $item['id']))->detail['reason']);
    }

    public function testRoutineTotalsAndRowEvidenceSurviveNormalization(): void
    {
        $ctx = $this->env->ctx();
        $rule = SaldiTenantContext::forSystem($ctx->installId, $ctx->regnskabId, $ctx->db, $ctx->dbType, 'rule:overdue_debtors', 4, 'DKK');
        $input = ['routine' => 'overdue_debtors', 'scheduled_date' => '2026-10-02', 'count' => 2, 'total' => ['amount' => '10.00', 'currency' => 'DKK', 'converted' => true], 'rows' => [['label' => 'Customer', 'amount' => '10.00', 'currency' => 'DKK', 'screen' => SaldiScreens::link('debtor', 1)], ['label' => 'No screen']]];
        $item = $this->inbox()->create($rule, 'routine.finding', $input);
        self::assertEquals($input['total'], $item['payload']['total']);
        self::assertSame([['label' => 'Customer', 'screen' => SaldiScreens::link('debtor', 1)]], $item['card']['evidence']);
        self::assertTrue($this->inbox()->get($rule, $item['id'])['payload']['total']['converted']);
        $input['total']['converted'] = 'true';
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($rule, 'routine.finding', $input));
    }

    public function testApplyFailureRollsBackThenLogsAndKeepsItemOpen(): void
    {
        $registry = SaldiKindRegistry::default();
        $registry->register(new class extends SaldiBaseInboxKind {
            public function name(): string
            {
                return 'followup.task';
            }
            public function actionClass(): string
            {
                return 'read_only';
            }
            public function proposePermission(): string
            {
                return 'followup.draft';
            }
            public function decidePermission(array $item): string
            {
                return 'inbox.read';
            }
            public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
            {
                return $this->normalized($ctx, ['title' => 'Failure'], 'Failure');
            }
            public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
            {
                $db->insert('settings', ['var_name' => 'rolled_back_marker']);
                throw new SaldiRuntimeError('CONFLICT', 'Simulated apply failure', ['reason' => 'journal_in_use']);
            }
        });
        $s = new SaldiInboxService($this->env->db, $registry);
        $ctx = $this->env->ctx();
        $item = $s->create($ctx, 'followup.task', []);
        for ($i = 0; $i < 2; $i++) {
            $this->error('CONFLICT', fn() => $s->decide($ctx, $item['id'], 'approve', $item['digest'], $ctx->tenantHash()));
        }
        self::assertNull($this->env->db->value("SELECT id FROM settings WHERE var_name = 'rolled_back_marker'"));
        self::assertSame('open', $s->get($ctx, $item['id'])['status']);
        $logs = $this->env->db->all("SELECT detail, idempotency_key FROM decision_log WHERE event = 'apply_failed' ORDER BY id");
        self::assertCount(2, $logs);
        self::assertSame('CONFLICT', json_decode($logs[0]['detail'], true)['error_code']);
        self::assertNotSame($logs[0]['idempotency_key'], $logs[1]['idempotency_key']);
    }

    public function testDecisionLogIdempotencyAndAppendOnlyApi(): void
    {
        $row = ['event' => 'proposed', 'actor' => 'user:910001', 'idempotency_key' => 'test:audit'];
        $first = SaldiDecisionLog::append($this->env->db, $row);
        $again = SaldiDecisionLog::append($this->env->db, $row);
        self::assertSame($first, $again);
        self::assertSame(1, (int)$this->env->db->value('SELECT COUNT(*) FROM decision_log'));
        self::assertFalse(method_exists(SaldiDecisionLog::class, 'update'));
        self::assertFalse(method_exists(SaldiDecisionLog::class, 'delete'));
    }

    public function testFollowupValidationAndCardContract(): void
    {
        $ctx = $this->env->ctx();
        foreach ([['title' => 'x'], ['title' => 'Title', 'details' => str_repeat('x', 1001)], ['title' => 'Title', 'due_date' => '2026-02-30'], ['title' => 'Title', 'screen' => 'debitor/ordre.php?id=1&send=1']] as $input) {
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($ctx, 'followup.task', $input));
        }
        $item = $this->inbox()->create($ctx, 'followup.task', ['title' => 'Monthly review', 'details' => 'Look at the journal', 'screen' => SaldiScreens::link('journal', 1)], ['evidence' => ['explanation' => 'Why this matters', 'tainted' => true, 'citations' => [['label' => 'Journal', 'screen' => SaldiScreens::link('journal', 1)], ['label' => 'Unsafe', 'screen' => 'https://example.org/']]]]);
        $card = $item['card'];
        foreach (['item_id', 'revision', 'digest', 'tenant_hash', 'kind', 'kind_label', 'provenance', 'provenance_label', 'title', 'summary', 'why', 'columns', 'rows', 'fields', 'evidence', 'notices', 'next_step', 'action_class', 'status', 'can_undo', 'approve_label', 'reject_label', 'expires_at', 'screen'] as $key) {
            self::assertArrayHasKey($key, $card);
        }
        self::assertSame('Why this matters', $card['why']);
        self::assertCount(1, $card['evidence']);
        self::assertSame('tainted', $card['notices'][0]['kind']);
        self::assertSame('Udført', $card['approve_label']);
        self::assertNotSame($card['why'], $card['next_step']);
    }

    public function testRoutineOnlyRulesTrustedPermissionsAndDedupe(): void
    {
        $ctx = $this->env->ctx();
        $input = ['routine' => 'low_stock', 'scheduled_date' => '2026-10-01', 'count' => 1, 'rows' => [['label' => 'A product', 'screen' => SaldiScreens::link('product', 1)]]];
        $this->error('PERMISSION_DENIED', fn() => $this->inbox()->create($ctx, 'routine.finding', $input));
        $rule = SaldiTenantContext::forSystem($ctx->installId, $ctx->regnskabId, $ctx->db, $ctx->dbType, 'rule:low_stock', 4, 'DKK');
        $this->error('PERMISSION_DENIED', fn() => $this->inbox()->create($rule, 'followup.task', ['title' => 'Rule task']));
        $this->error('PERMISSION_DENIED', fn() => $this->inbox()->create($rule, 'routine.finding', array_replace($input, ['routine' => 'overdue_debtors'])));
        $item = $this->inbox()->create($rule, 'routine.finding', $input + ['assignee_permission' => 'company.read']);
        self::assertSame('Varer under minimumsbeholdning', $item['title']);
        self::assertNull($item['owner_user_id']);
        self::assertTrue($this->inbox()->create($rule, 'routine.finding', $input)['deduplicated']);
        $this->error('NOT_FOUND', fn() => $this->inbox()->get($this->env->ctx(910002, '2'), $item['id']));
        self::assertSame(0, $this->inbox()->countOpen($this->env->ctx(910002, '2')));
        self::assertTrue($this->approve($item)['result']['acknowledged']);
    }

    public function testFiltersPaginationUnknownKindsAndInvalidOptions(): void
    {
        $ctx = $this->env->ctx();
        $s = $this->inbox();
        self::assertCount(15, SaldiKindRegistry::default()->names());
        $this->error('VALIDATION_ERROR', fn() => $s->create($ctx, 'unknown.kind', []));
        $this->error('PERMISSION_DENIED', fn() => $s->create($this->env->ctx(910001, '0'), 'followup.task', ['title' => 'Denied task']));
        foreach ([['request_id' => str_repeat('x', 65)], ['attention' => 'bad'], ['origin' => 'bad'], ['due_at' => '2026-02-30T00:00:00Z'], ['evidence' => ['tainted' => 'yes']]] as $opts) {
            $this->error('VALIDATION_ERROR', fn() => $s->create($ctx, 'followup.task', ['title' => 'Task title'], $opts));
        }
        $first = $s->create($ctx, 'followup.task', ['title' => 'First title']);
        $second = $s->create($ctx, 'followup.task', ['title' => 'Second title']);
        self::assertSame($second['id'], $s->list($ctx, ['limit' => 1])[0]['id']);
        self::assertSame($first['id'], $s->list($ctx, ['limit' => 1, 'offset' => 1])[0]['id']);
        self::assertSame([], $s->list($ctx, ['kind' => 'memory.fact']));
        $this->error('VALIDATION_ERROR', fn() => $s->list($ctx, ['status' => 'bad']));
        $this->error('VALIDATION_ERROR', fn() => $s->decide($ctx, $first['id'], 'bad', $first['digest'], $ctx->tenantHash()));
        $this->error('VALIDATION_ERROR', fn() => $s->decide($ctx, $first['id'], 'reject', $first['digest'], $ctx->tenantHash(), str_repeat('x', 256)));
        $s->cancel($ctx, $first['id']);
        self::assertCount(2, $s->list($ctx, ['status' => 'all']));
        self::assertCount(1, $s->list($ctx, ['status' => 'cancelled']));
        $third = $s->create($ctx, 'followup.task', ['title' => 'Third title']);
        $s->decide($ctx, $third['id'], 'reject', $third['digest'], $ctx->tenantHash());
        self::assertSame([$third['id'], $first['id']], array_column($s->list($ctx, ['status' => ['rejected', 'cancelled']]), 'id'));
        self::assertSame([$first['id']], array_column($s->list($ctx, ['status' => ['rejected', 'cancelled'], 'limit' => 1, 'offset' => 1]), 'id'));
        $this->error('VALIDATION_ERROR', fn() => $s->list($ctx, ['status' => []]));
        $this->error('VALIDATION_ERROR', fn() => $s->list($ctx, ['status' => ['open', 'bad']]));
    }
}
