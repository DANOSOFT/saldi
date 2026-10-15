<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiCompanionFoundationTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// Clone-backed companion registries, schedule slots and proposal custody.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Expect in-place finding revisions to use a distinct audit event.
// 20261002 CDX/LH Cover companion foundation contracts.
// 20261002 CDX/LH Exercise implemented kinds, settings, finding revisions and document privacy.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261004 CDX/LH Cover lead-day bounds, both routine toggles and isolated open-bank-account preferences.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiDocumentTestCase.php';
require_once __DIR__ . '/../../../includes/automation/SaldiJobRunner.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';

final class SaldiCompanionFoundationTest extends SaldiDocumentTestCase
{
    private function system(string $principal = 'system:worker'): SaldiTenantContext
    {
        $ctx = $this->env->ctx();
        return SaldiTenantContext::forSystem(
            $ctx->installId,
            $ctx->regnskabId,
            $ctx->db,
            $ctx->dbType,
            $principal,
            $ctx->fiscalYear,
            $ctx->baseCurrency
        );
    }

    public function testNewSchemaIndexesRepairOnAnExistingPostgresTable(): void
    {
        $db = $this->env->db;
        foreach (
            ['posting_rules' => 'posting_rules_match_idx',
            'assist_events' => 'assist_events_event_idx'] as $table => $index
        ) {
            $db->transaction(function () use ($db, $table, $index): void {
                $db->exec('DROP INDEX ' . $index);
                $step = saldi_plan_update_steps()['s91_' . $table];
                $step('postgresql', fn($sql) => $db->exec($sql), fn($sql) => $db->one($sql));
                self::assertNotNull($db->value(
                    'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',
                    [$index]
                ));
                $step('postgresql', fn($sql) => self::fail('Repaired index recreated'), fn($sql) => $db->one($sql));
            });
        }
        $db->exec(
            'INSERT INTO automation_state (state_key, state_value, updated_at) VALUES (?, ?, ?)',
            ['fixture',
            '{}',
            gmdate('Y-m-d H:i:s')]
        );
        self::assertSame('{}', $db->value('SELECT state_value FROM automation_state WHERE state_key = ?', ['fixture']));
    }

    private function seedGroups(): void
    {
        foreach (['DG', 'KG'] as $art) {
            $this->env->db->exec('DELETE FROM grupper WHERE art = ?', [$art]);
            $this->env->db->insert('grupper', ['art' => $art, 'kodenr' => 1, 'box3' => '', 'fiscal_year' => 4]);
        }
        $this->env->db->exec("UPDATE adresser SET gruppe = 1 WHERE id IN (991003, 991004)");
    }

    public function testNewKindsValidateApplyAndRenderTranslatedMetadata(): void
    {
        $this->seedGroups();
        $kinds = SaldiKindRegistry::default();
        self::assertCount(15, $kinds->names());
        $rule = $this->approve($this->inbox()->create($this->env->ctx(), 'rule.posting', [
            'match' => ['type' => 'text', 'value' => 'software'], 'account' => '991001',
        ]));
        $post = $this->openItem('customer', ['forfaldsdate' => '2025-06-15']);
        $expected = [
            'rule.posting' => ['persistent', 'journal.draft', 5900, 5910,
                ['match' => ['type' => 'text', 'value' => 'subscription'], 'account' => '991001']],
            'rule.retire' => ['persistent', 'journal.draft', 5901, 5911, ['rule_id' => $rule['result']['rule_id']]],
            'party.create' => ['persistent', 'debtors.draft', 5902, 5912, ['ledger' => 'customer', 'name' => 'Fixture customer']],
            'purchase_order.draft' => ['reversible_draft', 'purchases.draft', 5904, 5913,
                ['supplier_account' => '991004', 'lines' => [['description' => 'Paper', 'quantity' => '2.500']]]],
            'reminder.draft' => ['reversible_draft', 'debtors.draft', 5905, 5914,
                ['customer_account' => '991003', 'open_item_ids' => [$post]]],
            'support.report' => ['read_only', 'support.draft', 5906, 5915,
                ['category' => 'question', 'summary' => 'Question about the journal', 'details' => 'Synthetic question']],
        ];
        foreach ($expected as $name => [$action, $permission, $label, $next, $input]) {
            $kind = $kinds->get($name);
            self::assertSame($action, $kind->actionClass());
            self::assertSame($permission, $kind->proposePermission());
            $this->error('VALIDATION_ERROR', fn() => $kind->validate($this->env->ctx(), $this->env->db, []));
            $item = $this->inbox()->create($this->env->ctx(), $name, $input);
            self::assertSame($name === 'support.report' ? 'inbox.read' : $permission, $kind->decidePermission($item));
            self::assertSame(SaldiText::get(null, $label, 1, ''), $item['card']['kind_label']);
            self::assertSame(SaldiText::get(null, $next, 1, ''), $item['card']['next_step']);
            self::assertSame('applied', $this->approve($item)['status']);
        }
        $supplier = $this->inbox()->create($this->env->ctx(), 'party.create', ['ledger' => 'supplier', 'name' => 'New fixture supplier']);
        self::assertSame('creditors.draft', $kinds->get('party.create')->decidePermission($supplier));
        self::assertSame('Ny leverandør', $supplier['card']['kind_label']);
        self::assertSame('Opret', $supplier['card']['approve_label']);
    }

    public function testPartyPermissionUsesLedgerAtBothProposalEntrypoints(): void
    {
        $rights = str_repeat('0', 16);
        $rights[8] = '1';
        $supplierWriter = $this->env->ctx(910001, $rights);
        $args = ['ledger' => 'supplier',
            'name' => 'Fixture supplier',
            'request_id' => 'supplier',
            'explanation' => 'Requested'];
        $this->seedGroups();
        $input = ['ledger' => 'supplier', 'name' => 'Fixture supplier'];
        $item = $this->inbox()->create($supplierWriter, 'party.create', $input, ['request_id' => 'supplier', 'evidence' => ['explanation' => 'Requested']]);
        self::assertSame('open', $item['status']);
        self::assertSame($item['id'], SaldiDraftTools::propose('party.create', $supplierWriter, $this->env->db, $args)['data']['id']);
        $args['ledger'] = 'customer';
        $this->error('PERMISSION_DENIED', fn() => $this->inbox()->create($supplierWriter, 'party.create', ['ledger' => 'customer', 'name' => 'Fixture customer']));
        $this->error(
            'PERMISSION_DENIED',
            fn() => SaldiDraftTools::propose(
                'party.create',
                $supplierWriter,
                $this->env->db,
                $args
            )
        );
    }

    public function testNewRoutinesAndDeliveriesAreTrustedAndHonest(): void
    {
        $registry = SaldiJobRegistry::default();
        self::assertCount(10, $registry->routines());
        $expected = [
            'vat_missing_documents' => ['daily', 'journal.read', 5940],
            'supplier_bank_change' => ['hourly', 'creditors.read', 5941],
            'expiring_batches' => ['daily', 'products.read', 5942],
            'till_difference' => ['daily', 'ledger.read', 5943],
            'posting_rule_candidates' => ['weekly', 'journal.draft', 5944],
        ];
        foreach ($expected as $name => [$schedule, $permission, $title]) {
            $routine = $registry->routines()[$name];
            self::assertSame($name, $routine->name());
            self::assertSame($schedule, $routine->schedule());
            self::assertSame($permission, $routine->permission());
            self::assertSame($title, $routine->titleTextId());
            self::assertSame(
                ['permission' => $permission,
                'title_text_id' => $title],
                SaldiPermissionService::routine($name)
            );
            self::assertSame(
                ['company.read',
                $permission],
                SaldiPermissionService::effective(
                    $this->system('rule:' . $name),
                    $this->env->db
                )
            );
            self::assertNull($routine->run($this->system('rule:' . $name), $this->env->db, '2026-10-05'));
        }
        $registry->bindPermissions();
        self::assertTrue(SaldiPermissionService::allows(
            $this->system('rule:posting_rule_candidates'),
            $this->env->db,
            'journal.draft'
        ));
        self::assertFalse(SaldiPermissionService::allows(
            $this->system('rule:low_stock'),
            $this->env->db,
            'journal.draft'
        ));
        $this->error('PERMISSION_DENIED', fn() => SaldiPermissionService::routine('untrusted'));
        self::assertSame(
            [],
            $registry->routines()['posting_rule_candidates']->proposals(
                $this->system('rule:posting_rule_candidates'),
                $this->env->db,
                '2026-10-05'
            )
        );
        $digest = ($registry->get('digest.daily'))($this->system(), $this->env->db, ['payload' => '{"scheduled_date":"2026-10-05"}']);
        self::assertSame('skipped', $digest['status']);
        self::assertContains($digest['result']['value'], ['no subscribers', 'disabled']);
        self::assertSame(
            ['status' => 'failed', 'error' => 'invalid support payload'],
            ($registry->get('support.deliver'))($this->system(), $this->env->db, ['payload' => '{}'])
        );
        self::assertFalse(SaldiDigestJob::shouldEnqueue($this->env->db, new DateTimeImmutable()));
        self::assertFalse(SaldiSupportDeliverJob::shouldEnqueue($this->env->db, new DateTimeImmutable()));
    }

    public function testDailyHourlyAndMondayWeeklyDeduplication(): void
    {
        $db = $this->env->db;
        $db->exec('DELETE FROM automation_jobs');
        foreach (['vat_missing_documents', 'supplier_bank_change', 'posting_rule_candidates'] as $name) {
            SaldiSettings::set($db, $this->env->ctx(), 'automation', 'routine.' . $name, 'on');
        }
        $runner = new SaldiJobRunner($db, SaldiJobRegistry::default());
        self::assertTrue($runner->hasWork());
        $now = new DateTimeImmutable('2026-10-05T10:00:00+02:00');
        self::assertSame(3, $runner->enqueue(null, $now));
        self::assertSame(0, $runner->enqueue(null, $now));
        self::assertSame(1, $runner->enqueue(null, $now->modify('+1 hour')));
        self::assertSame([
            'routine:posting_rule_candidates:2026-W41',
            'routine:supplier_bank_change:2026-10-05T10',
            'routine:supplier_bank_change:2026-10-05T11',
            'routine:vat_missing_documents:2026-10-05',
        ], array_column($db->all('SELECT dedupe_key FROM automation_jobs ORDER BY dedupe_key'), 'dedupe_key'));
        self::assertSame(2, $runner->enqueue(null, $now->modify('+1 day')));
        self::assertSame(3, $runner->enqueue(null, $now->modify('+7 days')));
        self::assertSame(0, (int)$db->value("SELECT COUNT(*) FROM automation_jobs WHERE job_type = 'digest.daily'"));
        $this->expectException(InvalidArgumentException::class);
        $runner->enqueue('2026-02-30');
    }

    public function testProposingRoutineCreatesOnlyRuleProposalsAndReplaysIds(): void
    {
        $registry = new SaldiJobRegistry();
        $routine = new class extends SaldiBaseRoutine implements SaldiProposingRoutine {
            public function name(): string
            {
                return 'posting_rule_candidates';
            }
            public function permission(): string
            {
                return 'journal.draft';
            }
            public function titleTextId(): int
            {
                return 5944;
            }
            public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
            {
                return null;
            }
            public function proposals(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): array
            {
                return [
                    ['match' => ['type' => 'text',
                        'value' => 'software'],
                        'account' => '2800',
                        'explanation' => 'Repeated lines'],
                    ['match' => ['type' => 'text',
                        'value' => 'rent'],
                        'account' => '2900',
                        'dedupe_key' => 'rule:text:rent'],
                ];
            }
        };
        $registry->registerRoutine($routine);
        $kinds = SaldiKindRegistry::default();
        foreach ([2800, 2900] as $account) {
            $this->env->db->insert('kontoplan', ['kontonr' => $account, 'regnskabsaar' => 4, 'kontotype' => 'S', 'beskrivelse' => 'Fixture account', 'lukket' => '']);
        }
        $registry = new SaldiJobRegistry($kinds);
        $registry->registerRoutine($routine);
        $rule = $this->system('rule:posting_rule_candidates');
        $registry->bindPermissions();
        $inbox = new SaldiInboxService($this->env->db, $kinds);
        $item = $inbox->create(
            $rule,
            'rule.posting',
            ['match' => ['type' => 'text',
            'value' => 'initial'], 'account' => '2800'],
            ['request_id' => 'rule-proposal']
        );
        self::assertSame('rule', $item['origin']);
        self::assertNull($item['owner_user_id']);
        self::assertSame(
            $item['id'],
            $inbox->create(
                $rule,
                'rule.posting',
                $item['payload'],
                ['request_id' => 'rule-proposal']
            )['id']
        );
        foreach (['memory.fact', 'followup.task', 'rule.retire', 'party.create'] as $name) {
            $this->error('PERMISSION_DENIED', fn() => $inbox->create($rule, $name, []));
        }
        $this->error('PERMISSION_DENIED', fn() => $inbox->create($this->env->ctx(), 'routine.finding', []));
        $this->error('PERMISSION_DENIED', fn() => $inbox->create($this->system('rule:low_stock'), 'rule.posting', []));
        SaldiSettings::set($this->env->db, $this->env->ctx(), 'automation', 'routine.posting_rule_candidates', 'on');
        $handler = $registry->get('routine.posting_rule_candidates');
        $job = ['payload' => '{"scheduled_date":"2026-10-05"}',
            'dedupe_key' => 'routine:posting_rule_candidates:2026-W41'];
        $outcome = $this->env->db->transaction(fn() => $handler($this->system(), $this->env->db, $job));
        self::assertSame('done', $outcome['status']);
        self::assertSame('proposals', $outcome['result']['value']);
        self::assertCount(2, $outcome['result']['item_ids']);
        self::assertSame(
            $outcome,
            $this->env->db->transaction(fn() => $handler(
                $this->system(),
                $this->env->db,
                $job
            ))
        );
        foreach ($outcome['result']['item_ids'] as $id) {
            $row = $this->env->db->one('SELECT * FROM inbox_items WHERE id = ?', [$id]);
            self::assertSame('rule', $row['origin']);
            self::assertSame('ambient', $row['attention']);
            self::assertGreaterThanOrEqual(14 * 86400 - 2, strtotime($row['expires_at'] . ' UTC') - time());
            self::assertSame('rule:posting_rule_candidates', $row['created_by']);
        }
        self::assertSame(
            3,
            (int)$this->env->db->value(
                "SELECT COUNT(*) FROM decision_log WHERE event = 'proposed' AND kind = 'rule.posting'"
            )
        );
        $empty = SaldiJobRegistry::default();
        self::assertSame(
            ['status' => 'done',
            'result' => ['value' => 'none',
            'item_ids' => []]],
            $this->env->db->transaction(fn() => ($empty->get('routine.posting_rule_candidates'))(
                $this->system(),
                $this->env->db,
                [
                    'payload' => '{"scheduled_date":"2026-10-05"}',
                    'dedupe_key' => 'routine:posting_rule_candidates:2026-W41',
                ]
            ))
        );
    }
    public function testTenantSettingRangesAndAuthorization(): void
    {
        $db = $this->env->db;
        $valid = [
            'lead_days' => ['1', '14', '120'],
            'journal_suggestions' => ['on', 'off'], 'vat_period' => ['month', 'quarter', 'half'],
            'interrupt_budget' => ['0', '3', '10'], 'till_difference_threshold' => ['0', '50', '100000'],
            'minutes_per_suggestion' => ['0', '0.5', '60.00'], 'minutes_per_draft' => ['0', '2.25', '60'],
        ];
        foreach ($valid as $name => $values) {
            foreach ($values as $value) {
                SaldiSettings::set($db, $this->env->ctx(), 'assistant', $name, $value);
                self::assertSame($value, SaldiSettings::get($db, 'assistant', $name));
            }
            self::assertSame(1, (int)$db->value('SELECT COUNT(*) FROM settings WHERE var_grp = ? AND var_name = ? AND COALESCE(user_id, 0) = 0', ['assistant', $name]));
        }
        foreach (['vat_missing_documents', 'supplier_bank_change', 'expiring_batches', 'till_difference', 'posting_rule_candidates', 'reorder_suggestions', 'master_data_hygiene'] as $name) {
            foreach (['on', 'off'] as $value) {
                SaldiSettings::set($db, $this->env->ctx(), 'automation', 'routine.' . $name, $value);
                self::assertSame($value, SaldiSettings::get($db, 'automation', 'routine.' . $name));
            }
        }
        foreach (
            [
            'lead_days' => ['0', '121', '-1', '1.5', '1e1', ' 14', ''],
            'journal_suggestions' => ['daily', 'ON'], 'vat_period' => ['year', ''],
            'interrupt_budget' => ['-1', '11', '1.5', '1e0', ' 1'],
            'till_difference_threshold' => ['-1', '100001', '50.5'],
            'minutes_per_suggestion' => ['-0.1', '60.01', '60.00000000000000001', 'INF', 'NaN', '1e1'],
            'minutes_per_draft' => ['-1', '61', ''], 'unknown' => ['on'], 'digest' => ['daily'],
            ] as $name => $values
        ) {
            foreach ($values as $value) {
                $this->error('VALIDATION_ERROR', fn() => SaldiSettings::set($db, $this->env->ctx(), 'assistant', $name, $value));
            }
        }
        $this->error('VALIDATION_ERROR', fn() => SaldiSettings::set($db, $this->env->ctx(), 'automation', 'routine.unknown', 'on'));
        foreach ([$this->env->ctx(910002, str_repeat('2', 16)), $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']), $this->system()] as $ctx) {
            $this->error('PERMISSION_DENIED', fn() => SaldiSettings::set($db, $ctx, 'assistant', 'vat_period', 'month'));
        }
    }

    public function testDigestPreferencesArePrivateToTheHumanSelf(): void
    {
        $db = $this->env->db;
        $ctx = $this->env->ctx(910002);
        self::assertSame('off', SaldiSettings::getUser($db, $ctx, 'assistant', 'digest'));
        foreach (['daily', 'off', 'daily'] as $value) {
            SaldiSettings::setUser($db, $ctx, 'assistant', 'digest', $value);
            self::assertSame($value, SaldiSettings::getUser($db, $ctx, 'assistant', 'digest'));
        }
        foreach (['fixture@example.test', ''] as $value) {
            SaldiSettings::setUser($db, $ctx, 'assistant', 'digest_email', $value);
            self::assertSame($value, SaldiSettings::getUser($db, $ctx, 'assistant', 'digest_email', 'missing'));
        }
        self::assertSame('off', SaldiSettings::getUser($db, $this->env->ctx(), 'assistant', 'digest'));
        self::assertSame('off', SaldiSettings::get($db, 'assistant', 'digest'));
        self::assertSame(2, (int)$db->value("SELECT COUNT(*) FROM settings WHERE var_grp = 'assistant' AND user_id = ?", [$ctx->userId]));
        foreach ([['digest', 'on'], ['digest_email', 'invalid'], ['digest_email', "fixture@example.test\n"], ['vat_period', 'month']] as [$name, $value]) {
            $this->error('VALIDATION_ERROR', fn() => SaldiSettings::setUser($db, $ctx, 'assistant', $name, $value));
        }
        foreach ([$this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']), $this->system()] as $other) {
            $this->error('PERMISSION_DENIED', fn() => SaldiSettings::setUser($db, $other, 'assistant', 'digest', 'daily'));
            $this->error('PERMISSION_DENIED', fn() => SaldiSettings::getUser($db, $other, 'assistant', 'digest'));
        }
    }

    public function testBankAccountPreferencesValidateOpenBalanceAccountsAndStayPrivate(): void
    {
        $db = $this->env->db;
        $first = $this->env->ctx();
        $other = $this->env->ctx(910002, str_repeat('0', 16));
        foreach ([991101 => ['S', '', 4], 991102 => ['S', 'on', 4], 991103 => ['D', '', 4], 991104 => ['S', '', 3]] as $account => [$type, $closed, $year]) {
            $db->insert('kontoplan', ['kontonr' => $account, 'kontotype' => $type, 'lukket' => $closed, 'regnskabsaar' => $year]);
        }
        self::assertSame('missing', SaldiSettings::getUser($db, $other, 'assistant', 'bank_account', 'missing'));
        foreach ([$first, $other] as $ctx) {
            SaldiSettings::setUser($db, $ctx, 'assistant', 'bank_account', '991101');
            self::assertSame('991101', SaldiSettings::getUser($db, $ctx, 'assistant', 'bank_account'));
        }
        SaldiSettings::setUser($db, $first, 'assistant', 'bank_account', '991002');
        self::assertSame('991101', SaldiSettings::getUser($db, $other, 'assistant', 'bank_account'));
        self::assertSame('missing', SaldiSettings::get($db, 'assistant', 'bank_account', 'missing'));
        foreach (['991102', '991103', '991104', '999999', '', 'abc', '1 OR 1=1', '999999999999999', '1234567890123456'] as $invalid) {
            $this->error('VALIDATION_ERROR', fn() => SaldiSettings::setUser($db, $first, 'assistant', 'bank_account', $invalid));
        }
        $this->error('VALIDATION_ERROR', fn() => SaldiSettings::set($db, $first, 'assistant', 'bank_account', '991101'));
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        foreach ([$agent, $this->system()] as $ctx) {
            $this->error('PERMISSION_DENIED', fn() => SaldiSettings::setUser($db, $ctx, 'assistant', 'bank_account', '991101'));
            $this->error('PERMISSION_DENIED', fn() => SaldiSettings::getUser($db, $ctx, 'assistant', 'bank_account'));
        }
    }

    public function testAppliedDocumentReceiptsStayPrivateInBothInboxReadTools(): void
    {
        $pool = $this->pool();
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->draft(['document_ref' => 'pool:' . $pool])));
        $private = $item['result']['document'];
        self::assertArrayHasKey('from', $private);
        self::assertArrayHasKey('pool_row', $private);
        $single = $this->tool('get_inbox_item', ['item_id' => $item['id']]);
        $list = $this->tool('list_inbox_items', ['status' => 'applied']);
        foreach ([$single['data'], $list['data']['rows'][0]] as $public) {
            self::assertSame(['document_id' => $private['document_id']], $public['result']['document']);
            $json = json_encode($public, JSON_THROW_ON_ERROR);
            foreach ([$private['from'], $private['to'], 'vendor_iban', 'synthetic-bank-marker', 'pool_row'] as $hidden) {
                self::assertStringNotContainsString($hidden, $json);
            }
        }
        // Redaction never changes the private receipt needed for undo.
        self::assertSame($private, $this->inbox()->get($this->env->ctx(), $item['id'])['result']['document']);
        $result = $item['result'];
        $result['nested'] = ['from' => $private['from'], 'to' => $private['to'], 'pool_row' => $private['pool_row'], 'document' => $private];
        $this->env->db->exec('UPDATE inbox_items SET result = ? WHERE id = ?', [saldi_runtime_json($result), $item['id']]);
        $single = $this->tool('get_inbox_item', ['item_id' => $item['id']]);
        self::assertSame(['document' => ['document_id' => $private['document_id']]], $single['data']['result']['nested']);
        $undone = $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash());
        self::assertSame('undone', $undone['status']);
    }

    private function runFinding(SaldiJobRegistry $registry, string $name, string $date = '2026-10-02'): array
    {
        return $this->env->db->transaction(fn() => ($registry->get('routine.' . $name))(
            $this->system(),
            $this->env->db,
            ['payload' => saldi_runtime_json(['scheduled_date' => $date])]
        ));
    }

    public function testSupplierFindingRevisesInPlaceAndRejectsAnOldCard(): void
    {
        $db = $this->env->db;
        $registry = SaldiJobRegistry::default();
        SaldiSettings::set($db, $this->env->ctx(), 'automation', 'routine.supplier_bank_change', 'on');
        SaldiSettings::set($db, $this->env->ctx(), 'assistant', 'interrupt_budget', '1');
        self::assertSame('none', $this->runFinding($registry, 'supplier_bank_change')['result']['value']);
        $db->exec('UPDATE adresser SET bank_konto = ? WHERE id = ?', ['bank-change', 991004]);
        $id = $this->runFinding($registry, 'supplier_bank_change')['result']['item_id'];
        $first = $this->inbox()->get($this->env->ctx(), $id);
        self::assertSame('interrupt', $first['attention']);
        $db->exec('UPDATE inbox_items SET updated_at = ? WHERE id = ?', ['2026-01-01 00:00:00', $id]);
        $db->insert('adresser', ['id' => 991005, 'kontonr' => '991005', 'art' => 'K', 'firmanavn' => 'Another fixture supplier', 'lukket' => '']);
        $this->runFinding($registry, 'supplier_bank_change');
        $db->exec('UPDATE adresser SET iban = ? WHERE id = ?', ['iban-change', 991005]);
        self::assertSame($id, $this->runFinding($registry, 'supplier_bank_change')['result']['item_id']);
        $second = $this->inbox()->get($this->env->ctx(), $id);
        self::assertSame(2, $second['revision']);
        self::assertSame(2, $second['payload']['count']);
        self::assertSame('interrupt', $second['attention']);
        self::assertNotSame($first['digest'], $second['digest']);
        self::assertSame($first['created_at'], $second['created_at']);
        self::assertGreaterThan('2026-01-01 00:00:00', $db->value('SELECT updated_at FROM inbox_items WHERE id = ?', [$id]));
        $normalized = SaldiKindRegistry::default()->get('routine.finding')->validate($this->system('rule:supplier_bank_change'), $db, $second['payload']);
        self::assertSame(hash('sha256', saldi_runtime_json(['kind' => 'routine.finding', 'subject_type' => $normalized['subject_type'], 'subject_id' => $normalized['subject_id'], 'payload' => $normalized['payload']])), $second['digest']);
        self::assertSame(1, (int)$db->value('SELECT COUNT(*) FROM inbox_items'));
        self::assertSame(['proposed', 'revised'], array_column($db->all('SELECT event FROM decision_log ORDER BY id'), 'event'));
        self::assertSame('inbox:' . $id . ':revised:2', $db->value('SELECT idempotency_key FROM decision_log ORDER BY id DESC LIMIT 1'));
        self::assertSame('unchanged', $this->runFinding($registry, 'supplier_bank_change')['result']['value']);
        self::assertSame(2, (int)$db->value('SELECT COUNT(*) FROM decision_log'));
        $error = $this->error('CONFLICT', fn() => $this->approve($first));
        self::assertSame('digest_mismatch', $error->detail['reason']);
        self::assertSame('applied', $this->approve($second)['status']);
        self::assertSame('none', $this->runFinding($registry, 'supplier_bank_change')['result']['value']);
    }

    public function testRoutineAttentionValidationBudgetAndReplacement(): void
    {
        $db = $this->env->db;
        $registry = new SaldiJobRegistry();
        $routine = new class extends SaldiBaseRoutine {
            public mixed $attention = 'interrupt';
            public string $label = 'First fixture finding';
            public function name(): string
            {
                return 'vat_missing_documents';
            }
            public function permission(): string
            {
                return 'journal.read';
            }
            public function titleTextId(): int
            {
                return 5940;
            }
            public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
            {
                $input = ['routine' => $this->name(), 'scheduled_date' => $scheduledDate, 'count' => 1, 'rows' => [['label' => $this->label]]];
                if ($this->attention !== null) {
                    $input['attention'] = $this->attention;
                }
                return $input;
            }
        };
        $registry->registerRoutine($routine);
        SaldiSettings::set($db, $this->env->ctx(), 'automation', 'routine.vat_missing_documents', 'on');
        SaldiSettings::set($db, $this->env->ctx(), 'assistant', 'interrupt_budget', '1');
        $id = $this->runFinding($registry, $routine->name())['result']['item_id'];
        self::assertSame('interrupt', $this->inbox()->get($this->env->ctx(), $id)['attention']);
        $routine->label = 'Changed fixture finding';
        $next = $this->runFinding($registry, $routine->name())['result']['item_id'];
        self::assertNotSame($id, $next);
        self::assertSame('cancelled', $this->inbox()->get($this->env->ctx(), $id)['status']);
        $item = $this->inbox()->get($this->env->ctx(), $next);
        self::assertSame('ambient', $item['attention']);
        self::assertTrue($item['evidence']['attention_downgraded']);
        foreach (['silent', 'receipt', 'ambient', null] as $attention) {
            $routine->attention = $attention;
            $routine->label .= ' next';
            $item = $this->inbox()->get($this->env->ctx(), $this->runFinding($registry, $routine->name())['result']['item_id']);
            self::assertSame($attention ?? 'ambient', $item['attention']);
        }
        foreach (['invalid', 1, []] as $attention) {
            $routine->attention = $attention;
            $this->error('VALIDATION_ERROR', fn() => $this->runFinding($registry, $routine->name()));
        }
        self::assertSame('open', $this->inbox()->get($this->env->ctx(), $item['id'])['status']);
    }
}
