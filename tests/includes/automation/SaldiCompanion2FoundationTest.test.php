<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiCompanion2FoundationTest.test.php --- ai/mcp-assistant --- 2026.10.08
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
// Companion part 2 schema, permissions, proposal custody, citations and contract boundaries.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Replace unavailable stub checks with valid permission, apply, undo and routine behavior.
// 20261008 CL/LH Cover the mobile grant migration and load inbox actions as a library in standalone tests.

declare(strict_types=1);

require_once __DIR__ . '/../tools/SaldiToolDbTestCase.php';
require_once __DIR__ . '/../support/SaldiCompanion2Fixtures.php';
require_once __DIR__ . '/../../../includes/automation/SaldiJobRegistry.php';

final class SaldiCompanion2FoundationTest extends SaldiToolDbTestCase
{
    public function testBankDdlAndSignatureOnBothEngines(): void
    {
        $step = saldi_plan_update_steps()['s92_bank_import_lines'];
        foreach (['postgresql', 'mysql', 'mysqli'] as $type) {
            $writes = [];
            $step($type, function ($sql) use (&$writes): void {
                $writes[] = $sql;
            }, fn($sql) => null);
            self::assertCount(3, $writes);
            self::assertStringContainsString('line_hash char(64) NOT NULL', $writes[0]);
            self::assertStringContainsString($type === 'postgresql' ? 'numeric(15,3)' : 'decimal(15,3)', $writes[0]);
            self::assertStringContainsString(
                $type === 'postgresql' ? 'serial PRIMARY KEY' : 'AUTO_INCREMENT PRIMARY KEY',
                $writes[0]
            );
            self::assertStringContainsString(
                $type === 'postgresql' ? 'created_at timestamp' : 'created_at datetime',
                $writes[0]
            );
            self::assertSame(
                'CREATE INDEX bank_import_lines_batch_idx ON bank_import_lines (batch_id, line_no)',
                $writes[1]
            );
            self::assertSame('CREATE INDEX bank_import_lines_hash_idx ON bank_import_lines (line_hash)', $writes[2]);
            $writes = [];
            $queries = [];
            $step($type, function ($sql) use (&$writes): void {
                $writes[] = $sql;
            }, function ($sql) use (&$queries) {
                $queries[] = $sql;
                return str_contains($sql, 'information_schema.tables') || str_contains(
                    $sql,
                    'information_schema.columns'
                )
                    || str_contains($sql, 'bank_import_lines_batch_idx') ? ['present' => 1] : null;
            });
            self::assertSame(['CREATE INDEX bank_import_lines_hash_idx ON bank_import_lines (line_hash)'], $writes);
            self::assertStringContainsString(
                $type === 'postgresql' ? 'pg_indexes' : 'information_schema.statistics',
                $queries[2]
            );
            try {
                $step(
                    $type,
                    fn($sql) => self::fail('Incompatible table was written'),
                    fn($sql) => str_contains($sql, 'information_schema.tables') ? ['present' => 1] : null
                );
                self::fail('Missing line_hash accepted');
            } catch (RuntimeException $e) {
                self::assertSame('incompatible existing table bank_import_lines', $e->getMessage());
            }
        }
    }

    public function testPostgresBankIndexRepairIsIdempotentAndKeepsRows(): void
    {
        $db = $this->env->db;
        $id = $this->bankLine();
        $db->transaction(function () use ($db): void {
            $db->exec('DROP INDEX bank_import_lines_hash_idx');
            $step = saldi_plan_update_steps()['s92_bank_import_lines'];
            $step('postgresql', fn($sql) => $db->exec($sql), fn($sql) => $db->one($sql));
            $step('postgresql', fn($sql) => self::fail('An intact step wrote data'), fn($sql) => $db->one($sql));
            self::assertSame(['bank_import_lines_batch_idx', 'bank_import_lines_hash_idx',
                'bank_import_lines_pkey'], array_column($db->all(
                    'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() '
                    . 'AND tablename = ? ORDER BY indexname',
                    ['bank_import_lines']
                ), 'indexname'));
        });
        self::assertSame('10.005', $db->value('SELECT amount FROM bank_import_lines WHERE id = ?', [$id]));
        self::assertSame(
            's93_agent_grants_family_id',
            $db->value("SELECT var_value FROM settings WHERE var_name = 'plan_updates_last_step'")
        );
    }

    private function bankLine(): int
    {
        return $this->env->db->insert('bank_import_lines', ['batch_id' => str_repeat('b', 32), 'line_no' => 1,
            'bank_account' => '1000', 'transdate' => '2026-06-10', 'description' => 'Syntetisk betaling',
            'amount' => '10.005', 'currency' => 'DKK', 'line_hash' => hash('sha256', 'synthetic'),
            'created_by' => 910001, 'created_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function testSalesScopeAndPartySpecificSettlementPermissionsAtBothEntrypoints(): void
    {
        foreach (['0', '1', '2', '9', 'x'] as $digit) {
            $rights = str_repeat('0', 16);
            $rights[5] = $digit;
            foreach ([['read'], ['read', 'draft'], ['draft']] as $scopes) {
                $ctx = $this->env->ctx(910001, $rights, 'agent:saldi-assist', 99, $scopes);
                self::assertSame(
                    $digit === '1' && $scopes === ['read', 'draft'],
                    SaldiPermissionService::allows($ctx, $this->env->db, 'sales.draft')
                );
                $visible = array_column(SaldiToolRegistry::default()->visible($ctx, $this->env->db), 'name');
                self::assertSame(
                    $digit === '1' && $scopes === ['read', 'draft'],
                    in_array('propose_sales_order', $visible, true)
                );
                self::assertSame(
                    $digit === '1' && $scopes === ['read', 'draft'],
                    in_array('propose_credit_note', $visible, true)
                );
            }
        }
        SaldiCompanion2Fixtures::seed($this->env->db);
        $kind = SaldiKindRegistry::default()->get('settlement.draft');
        foreach (
            ['debtor' => [6, 'debtors.draft'], 'creditor' => [8,
            'creditors.draft']] as $type => [$position, $permission]
        ) {
            $args = SaldiCompanion2Fixtures::inputs()['propose_settlement'];
            $args['party_type'] = $type;
            $args['party_account'] = $type === 'debtor' ? '1000' : '2000';
            $args['item_ids'] = $type === 'debtor' ? [701, 702] : [703, 704];
            $args['request_id'] = 'settlement-' . $type;
            self::assertSame($permission, $kind->decidePermission(['payload' => $args]));
            $allowed = $this->only($position, '1');
            $denied = $this->only($position === 6 ? 8 : 6, '1');
            $payload = array_diff_key($args, array_flip(['request_id', 'explanation', 'tainted']));
            $item = $this->inbox()->create($allowed, 'settlement.draft', $payload);
            self::assertSame('open', $item['status']);
            self::assertSame(910001, $item['owner_user_id']);
            self::assertSame($permission, $kind->decidePermission($item));
            $proposal = SaldiDraftTools::propose('settlement.draft', $allowed, $this->env->db, $args)['data'];
            self::assertSame('open', $proposal['status']);
            self::assertSame(910001, $proposal['owner_user_id']);
            self::assertSame($permission, $kind->decidePermission($proposal));
            $this->error('PERMISSION_DENIED', fn() => $this->inbox()->create($denied, 'settlement.draft', $payload));
            $this->error('PERMISSION_DENIED', fn() => SaldiDraftTools::propose('settlement.draft', $denied, $this->env->db, $args));
        }
    }

    public function testFourKindsApplyUndoAndRenderFiveDanishLabelsAndNextSteps(): void
    {
        $ctx = $this->env->ctx();
        $db = $this->env->db;
        SaldiCompanion2Fixtures::seed($db);
        $inputs = SaldiCompanion2Fixtures::inputs($db);
        $expected = [
            ['settlement.draft', 'persistent', 'debtors.draft', 6100, 6110, 'propose_settlement', []],
            ['purchase_order.invoice_stage', 'reversible_draft', 'purchases.draft', 6101, 6111, 'propose_purchase_invoice_stage', []],
            ['sales_order.draft', 'reversible_draft', 'sales.draft', 6102, 6112, 'propose_sales_order', ['type' => 'offer']],
            ['sales_order.draft', 'reversible_draft', 'sales.draft', 6103, 6113, 'propose_sales_order', ['type' => 'order']],
            ['credit_note.draft', 'reversible_draft', 'sales.draft', 6104, 6114, 'propose_credit_note', []],
        ];
        if (!defined('SALDI_INBOX_PAGE_LIBRARY')) {
            define('SALDI_INBOX_PAGE_LIBRARY', true);
        }
        if (!defined('SALDI_INBOX_ACTION_LIBRARY')) {
            define('SALDI_INBOX_ACTION_LIBRARY', true);
        }
        if (!defined('SALDI_ASSIST_REPORT_LIBRARY')) {
            define('SALDI_ASSIST_REPORT_LIBRARY', true);
        }
        require_once __DIR__ . '/../../../inbox/index.php';
        require_once __DIR__ . '/../../../systemdata/assistent_rapport.php';
        foreach ($expected as [$name, $action, $permission, $label, $next, $tool, $changes]) {
            $kind = SaldiKindRegistry::default()->get($name);
            self::assertSame($action, $kind->actionClass());
            self::assertSame($permission, $kind->proposePermission());
            $payload = array_replace(array_diff_key($inputs[$tool], array_flip(['request_id', 'explanation', 'tainted'])), $changes);
            self::assertSame($permission, $kind->decidePermission(['payload' => $payload]));
            $this->error('VALIDATION_ERROR', fn() => $kind->validate($ctx, $db, []));
            $item = $this->inbox()->create($ctx, $name, $payload);
            self::assertSame('open', $item['status']);
            self::assertFalse($kind->canUndo($ctx, $db, $item));
            self::assertSame(SaldiText::get(null, $label, 1, ''), $item['card']['kind_label']);
            self::assertSame(SaldiText::get(null, $next, 1, ''), $item['card']['next_step']);
            self::assertNotEmpty($item['card']['rows']);
            $applied = $this->approve($item);
            self::assertSame('applied', $applied['status']);
            self::assertTrue($kind->canUndo($ctx, $db, $applied));
            $receipt = saldi_approval_receipt($applied, $db, 1);
            self::assertNotNull($receipt);
            $appliedText = match ($name) {
                'settlement.draft' => 6280,
                'purchase_order.invoice_stage' => 6282,
                'sales_order.draft' => $payload['type'] === 'offer' ? 6284 : 6285,
                'credit_note.draft' => 6288,
            };
            self::assertSame(SaldiText::get(null, $appliedText, 1, ''), $receipt['text']);
            self::assertStringNotContainsString('hukommelse', $receipt['text']);
            if ($name === 'settlement.draft') {
                self::assertSame('1', $db->value('SELECT udlignet FROM openpost WHERE id = 701'));
            } elseif ($name === 'purchase_order.invoice_stage') {
                self::assertSame('INV-1', $db->value('SELECT fakturanr FROM ordrer WHERE id = 52'));
            } else {
                self::assertSame($name === 'credit_note.draft' ? 'DK' : 'DO', $db->value('SELECT art FROM ordrer WHERE id = ?', [$applied['result']['order_id']]));
            }
            $undone = $this->inbox()->undo($ctx, $applied['id'], $ctx->tenantHash());
            self::assertSame('undone', $undone['status']);
            $undoText = match ($name) {
                'settlement.draft' => 6281,
                'purchase_order.invoice_stage' => 6283,
                'sales_order.draft' => $payload['type'] === 'offer' ? 6286 : 6287,
                'credit_note.draft' => 6289,
            };
            foreach ([1, 2, 3] as $lang) {
                self::assertSame(SaldiText::get(null, $appliedText, $lang, ''), saldi_approval_receipt($applied, $db, $lang)['text']);
                self::assertSame(SaldiText::get(null, $undoText, $lang, ''), saldi_approval_receipt($undone, $db, $lang)['text']);
            }
            self::assertStringNotContainsString('hukommelse', saldi_approval_receipt($undone, $db, 1)['text']);
            if ($name === 'settlement.draft') {
                self::assertSame('', $db->value('SELECT udlignet FROM openpost WHERE id = 701'));
            } elseif ($name === 'purchase_order.invoice_stage') {
                self::assertSame('152', $db->value('SELECT fakturanr FROM ordrer WHERE id = 52'));
            } else {
                self::assertNull($db->value('SELECT id FROM ordrer WHERE id = ?', [$applied['result']['order_id']]));
            }
            $mapLabel = $name === 'sales_order.draft' ? 6102 : $label;
            self::assertSame($mapLabel, SALDI_INBOX_KIND_LABELS[$name][0]);
            self::assertSame($mapLabel, SALDI_ASSIST_REPORT_KINDS[$name][0]);
        }
    }

    public function testWeeklyRoutinesReturnRealFindingsWithTrustedPermissionsAndSettingsToggles(): void
    {
        SaldiCompanion2Fixtures::seed($this->env->db);
        if (!defined('SALDI_ASSISTENT_LIBRARY')) {
            define('SALDI_ASSISTENT_LIBRARY', true);
        }
        require_once __DIR__ . '/../../../systemdata/assistent.php';
        if (!defined('SALDI_ASSIST_REPORT_LIBRARY')) {
            define('SALDI_ASSIST_REPORT_LIBRARY', true);
        }
        require_once __DIR__ . '/../../../systemdata/assistent_rapport.php';
        $registry = SaldiJobRegistry::default();
        foreach (
            ['reorder_suggestions' => ['products.read', 6170], 'master_data_hygiene' => ['company.read',
            6171]] as $name => [$permission, $title]
        ) {
            $routine = $registry->routines()[$name];
            self::assertSame($name, $routine->name());
            self::assertSame('weekly', $routine->schedule());
            self::assertSame($permission, $routine->permission());
            self::assertSame($title, $routine->titleTextId());
            self::assertSame(
                ['permission' => $permission, 'title_text_id' => $title],
                SaldiPermissionService::routine($name)
            );
            $registry->bindPermissions();
            self::assertSame(
                ['permission' => $permission, 'title_text_id' => $title],
                SaldiPermissionService::routine($name)
            );
            SaldiPermissionService::setRoutineLookup(null);
            $finding = $routine->run($this->env->ctx(), $this->env->db, '2026-10-05');
            self::assertNotNull($finding);
            self::assertSame($name, $finding['routine']);
            self::assertSame('2026-10-05', $finding['scheduled_date']);
            self::assertGreaterThan(0, $finding['count']);
            self::assertNotEmpty($finding['rows']);
            foreach ($finding['rows'] as $row) {
                self::assertTrue(SaldiScreens::isAllowed($row['screen']));
            }
            SaldiSettings::set($this->env->db, $this->env->ctx(), 'automation', 'routine.' . $name, 'on');
            self::assertSame('on', SaldiSettings::get($this->env->db, 'automation', 'routine.' . $name));
            self::assertNotNull($registry->get('routine.' . $name));
            $toggle = array_values(array_filter(SALDI_ASSIST_SWITCHES, fn($row) => $row[1] === 'routine.' . $name));
            self::assertCount(1, $toggle);
            self::assertSame($title, $toggle[0][2]);
            self::assertSame($title, SALDI_ASSIST_REPORT_ROUTINES[$name][0]);
        }
        $this->error('PERMISSION_DENIED', fn() => SaldiPermissionService::routine('not_trusted'));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
    }

    public function testBankAndSalesCitationsAndVerifiedScreens(): void
    {
        $db = $this->env->db;
        $id = $this->bankLine();
        $citation = SaldiDraftTools::resolve($this->only(2), $db, 'saldi:bank_line:' . $id);
        self::assertSame('Syntetisk betaling', $citation['label']);
        self::assertSame('finans/assist_bankfil.php', $citation['screen']);
        self::assertNull(SaldiDraftTools::resolve($this->only(5), $db, 'saldi:bank_line:' . $id));
        self::assertNull(SaldiDraftTools::resolve($this->env->ctx(), $db, 'saldi:bank_line:2147483647'));
        self::assertNull(SaldiDraftTools::resolve($this->env->ctx(), $db, 'saldi:bank_line:1@4'));
        foreach (['DO', 'DK', 'PO'] as $art) {
            $order = $db->insert('ordrer', ['art' => $art, 'ordrenr' => 700, 'konto_id' => 10]);
            $ref = 'saldi:order:' . $order;
            self::assertSame('debitor/ordre.php?id=' . $order, SaldiDraftTools::resolve(
                $this->only(5),
                $db,
                $ref
            )['screen']);
            self::assertNull(SaldiDraftTools::resolve($this->only(7), $db, $ref));
            $this->hideRevenue();
            self::assertNull(SaldiDraftTools::resolve($this->only(5), $db, $ref));
            self::assertNotNull(SaldiDraftTools::resolve($this->env->ctx(), $db, $ref));
            $db->exec("DELETE FROM settings WHERE var_grp = 'finans' AND var_name = 'hideRevenue'");
        }
        foreach (
            ['sales_offer' => 'debitor/ordre.php?id=12', 'stock_count' => 'lager/optalling.php',
            'bank_import' => 'finans/assist_bankfil.php',
                'year_end_pack' => 'systemdata/revisorpakke.php'] as $type => $screen
        ) {
            self::assertSame($screen, SaldiScreens::link($type, 12));
            self::assertTrue(SaldiScreens::isAllowed($screen));
            self::assertFalse(SaldiScreens::isAllowed($screen . '&slet=1'));
            if (in_array($type, ['sales_offer', 'stock_count'], true)) {
                self::assertFileExists(__DIR__ . '/../../../' . strtok($screen, '?'));
            }
        }
    }

    public function testNewInputBoundsAndNestedOutputAllowlists(): void
    {
        $registry = SaldiToolRegistry::default();
        SaldiCompanion2Fixtures::seed($this->env->db);
        $inputs = SaldiCompanion2Fixtures::inputs($this->env->db);
        $data = SaldiCompanion2Fixtures::data($this->env->ctx(), $this->env->db, $this->toolCall(
            'create_followup_task',
            ['title' => 'Fixture', 'request_id' => 'schema-fixture']
        )['data']);
        self::assertCount(17, $data);
        foreach ($inputs as $name => $args) {
            $tool = $registry->get($name);
            SaldiSchemaValidator::validate((object)$args, $tool->inputSchema);
            SaldiSchemaValidator::validate($data[$name], $tool->dataSchema);
            $this->error(
                'VALIDATION_ERROR',
                fn() => SaldiSchemaValidator::validate($data[$name] + ['secret' => 'hidden'], $tool->dataSchema)
            );
            if ($tool->effect === 'draft') {
                foreach (['request_id', 'explanation'] as $required) {
                    $this->error(
                        'VALIDATION_ERROR',
                        fn() => SaldiSchemaValidator::validate(
                            (object)array_diff_key(
                                $args,
                                [$required => true]
                            ),
                            $tool->inputSchema
                        )
                    );
                }
                self::assertArrayHasKey('tainted', $tool->inputSchema['properties']);
                self::assertArrayHasKey('citations', $tool->inputSchema['properties']);
            }
        }
        $bad = [
            ['import_bank_statement', ['content' => str_repeat('x', 200001)]],
            ['list_bank_lines', ['batch_id' => 'INVALID']],
            ['list_bank_lines', ['status' => 'applied']],
            ['propose_bank_entries', ['line_ids' => range(1, 201)]],
            ['propose_settlement', ['item_ids' => [1]]],
            ['propose_settlement', ['item_ids' => range(1, 11)]],
            ['suggest_settlements', ['party_type' => 'sales']],
            ['suggest_reorders', ['days' => 29]], ['suggest_reorders', ['days' => 366]],
            ['propose_sales_order', ['lines' => []]],
            ['propose_sales_order', ['type' => 'invoice']],
            ['propose_sales_order', ['lines' => array_fill(0, 51, ['quantity' => '1'])]],
            ['propose_sales_order', ['lines' => [['quantity' => '1', 'discount_pct' => '100.001']]]],
            ['propose_purchase_invoice_stage', ['invoice_number' => str_repeat('x', 31)]],
            ['propose_credit_note', ['reason' => str_repeat('x', 201)]],
        ];
        foreach ($bad as [$name, $changes]) {
            $this->error(
                'VALIDATION_ERROR',
                fn() => SaldiSchemaValidator::validate(
                    (object)array_replace($inputs[$name], $changes),
                    $registry->get($name)->inputSchema
                )
            );
        }
        $data['list_bank_lines']['rows'][0]['match']['party']['bank_details'] = 'hidden';
        $data['get_year_end_pack']['trial_balance']['rows'][0]['private'] = true;
        $data['get_till_summary']['rows'][0]['payments'][0]['private'] = true;
        foreach (['list_bank_lines', 'get_year_end_pack', 'get_till_summary'] as $name) {
            $this->error('VALIDATION_ERROR', fn() => SaldiSchemaValidator::validate(
                $data[$name],
                $registry->get($name)->dataSchema
            ));
        }
        $import = $registry->get('import_bank_statement');
        self::assertSame(['journal.draft'], $import->discoveryPermissions);
        self::assertSame('read', $import->effect);
        self::assertSame('auto', $import->tier);
        self::assertStringContainsString(
            'stages lines for matching; changes nothing in the books',
            $import->description
        );
    }

    public function testTextRowsMatchSpecAndHaveAllThreeLanguages(): void
    {
        $spec = file_get_contents(__DIR__ . '/../../../doc/ai-plan/mcp-assistant/SPEC-companion-2.md');
        preg_match_all('/^\| (61[0-9]{2}) \| (.*?) \|$/m', $spec, $matches, PREG_SET_ORDER);
        self::assertCount(45, $matches);
        $rows = [];
        foreach (file(__DIR__ . '/../../../importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $line) {
            $fields = explode("\t", $line);
            if ((int)$fields[0] >= 6100 && (int)$fields[0] <= 6171) {
                self::assertArrayNotHasKey($fields[0], $rows);
                $rows[$fields[0]] = $fields;
            }
        }
        self::assertCount(45, $rows);
        foreach ($matches as [, $id, $danish]) {
            self::assertCount(4, $rows[$id]);
            self::assertSame($danish, $rows[$id][1]);
            foreach ([1, 2, 3] as $language) {
                self::assertNotEmpty($rows[$id][$language]);
                self::assertSame($rows[$id][$language], SaldiText::get(null, (int)$id, $language, ''));
                self::assertSame(substr_count($danish, '%s'), substr_count($rows[$id][$language], '%s'));
            }
        }
    }
}
