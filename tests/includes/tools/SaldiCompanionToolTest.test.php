<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiCompanionToolTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// All 54 tool envelopes plus implemented companion dispatch and input boundaries.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Validate real success and error responses for every companion tool.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261004 CDX/LH Exercise real successful and invalid-input MCP responses for all 54 tools.

declare(strict_types=1);

require_once __DIR__ . '/SaldiToolDbTestCase.php';
require_once __DIR__ . '/../support/SaldiCompanion2Fixtures.php';

final class SaldiCompanionToolTest extends SaldiToolDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->env->db;
        foreach (['DG', 'KG'] as $art) {
            $db->exec('DELETE FROM grupper WHERE art = ?', [$art]);
            $db->insert('grupper', ['art' => $art, 'kodenr' => 1, 'box3' => '', 'fiscal_year' => 4]);
        }
        $db->exec("UPDATE adresser SET gruppe = 1 WHERE art IN ('D', 'K')");
        $db->exec('DELETE FROM pool_files');
        $db->insert('pool_files', ['id' => 1, 'filename' => 'fixture.pdf', 'description' => 'Synthetic invoice', 'file_date' => '2026-06-10']);
        $db->insert('posting_rules', ['id' => 1, 'match_type' => 'text', 'match_value' => 'paper', 'account' => '1000', 'created_by' => 'user:910001', 'created_at' => gmdate('Y-m-d H:i:s')]);
        SaldiCompanion2Fixtures::seed($db);
    }

    /** @return array<string,array> Representative valid inputs for each companion tool. */
    private function inputs(): array
    {
        $common = ['request_id' => 'companion-fixture', 'explanation' => 'Requested by the user'];
        $inputs = [
            'list_posting_rules' => ['status' => 'all'],
            'suggest_posting' => ['description' => 'Software subscription', 'amount' => '1250.00', 'invoice' => 'INV-17', 'exclude_line_id' => 111,
                'party' => ['ledger' => 'supplier', 'account' => '2000', 'side' => 'debit']],
            'propose_posting_rule' => $common + ['match' => ['type' => 'text',
                'value' => 'software'],
                'account' => '1000'],
            'propose_rule_retirement' => $common + ['rule_id' => 1],
            'list_documents' => ['status' => 'pool'],
            'get_document' => ['ref' => 'pool:1'],
            'propose_supplier_invoice' => $common + [
                'supplier_account' => '2000', 'invoice_number' => 'INV-1', 'date' => '2026-06-10',
                'lines' => [['description' => 'Software', 'account' => '1000', 'amount' => '1250.00']],
            ],
            'propose_payment_registration' => $common + [
                'party' => ['ledger' => 'supplier', 'account' => '2000'],
                'amount' => '1250.00', 'date' => '2026-06-10', 'bank_account' => '1000',
            ],
            'propose_party' => $common + ['ledger' => 'customer', 'name' => 'Fixture customer'],
            'propose_purchase_order' => $common + ['supplier_account' => '2000',
                'lines' => [['description' => 'Paper',
                'quantity' => '2.500']]],
            'propose_reminder' => $common + ['customer_account' => '1000', 'open_item_ids' => [1]],
            'get_daily_overview' => [],
            'get_vat_summary' => ['from' => '2026-01-01', 'to' => '2026-03-31'],
            'get_period_status' => ['from' => '2026-01-01', 'to' => '2026-03-31'],
            'list_procedures' => [],
            'get_procedure' => ['slug' => 'momsafregning'],
            'report_problem' => $common + ['summary' => 'Question about the journal',
                'details' => 'How can I check it?',
                'category' => 'question'],
        ];
        foreach ($inputs as $name => &$args) {
            if (isset($args['request_id'])) {
                $args['request_id'] = 'fixture-' . $name;
            }
        }
        unset($args);
        return $inputs;
    }

    public function testEveryToolHasValidSuccessAndErrorEnvelopeFixtures(): void
    {
        $task = $this->read(
            'create_followup_task',
            ['title' => 'Review fixture records',
            'request_id' => 'all-tool-fixture']
        )['data'];
        $existing = [
            'get_company_context' => [], 'get_memory' => [], 'search_accounts' => [],
            'get_account_balance' => ['account' => '1000'], 'list_ledger_entries' => ['account' => '1000'],
            'get_trial_balance' => [], 'list_journals' => [], 'get_journal' => ['journal_id' => 11],
            'list_open_items' => ['party_type' => 'debtor'],
                'search_parties' => ['type' => 'debtor',
                'query' => 'Alpha'],
            'get_party' => ['party_id' => 10], 'search_invoices' => ['direction' => 'sales'],
            'get_invoice' => ['order_id' => 51], 'search_products' => [],
            'list_inbox_items' => [], 'get_inbox_item' => ['item_id' => $task['id']],
            'propose_journal_entry' => [
                'request_id' => 'all-journal-fixture', 'explanation' => 'Requested', 'date' => '2026-06-10',
                'lines' => [['description' => 'Fixture draft',
                    'amount' => '10.00',
                    'debit' => ['ledger' => 'account',
                    'account' => '1000'],
                    'credit' => ['ledger' => 'account',
                    'account' => '2000']]],
            ],
            'propose_memory_fact' => ['request_id' => 'all-memory-fixture',
                'scope' => 'user',
                'fact' => 'Use short summaries'],
            'propose_memory_retraction' => ['request_id' => 'all-retract-fixture', 'memory_id' => 2],
            'create_followup_task' => ['request_id' => 'all-task-fixture', 'title' => 'Review another fixture'],
        ];
        $registry = SaldiToolRegistry::default();
        $inputs = $existing + $this->inputs() + SaldiCompanion2Fixtures::inputs($this->env->db);
        self::assertCount(54, $registry->all());
        self::assertCount(54, $inputs);
        $outputs = [];
        foreach (array_values($registry->all()) as $index => $tool) {
            $ctx = $this->env->ctx(910001, null, 'agent:saldi-assist', 100 + $index, ['read', 'draft']);
            $success = $this->toolCall($tool->name, $inputs[$tool->name], $ctx);
            self::assertTrue($success['ok'], $tool->name . ': ' . json_encode($success));
            SaldiSchemaValidator::validate($success, $tool->outputSchema());
            $outputs[$tool->name] = $success['data'];
            $error = $this->toolCall($tool->name, ['unexpected' => true], $ctx);
            self::assertFalse($error['ok'], $tool->name);
            self::assertSame('VALIDATION_ERROR', $error['error']['code']);
            SaldiSchemaValidator::validate($error, $tool->outputSchema());
            self::assertFalse($tool->dataSchema['additionalProperties']);
        }
        self::assertTrue($outputs['get_customer_statement']['converted']);
        self::assertSame('DKK', $outputs['get_customer_statement']['currency']);
        self::assertSame('0.00', $outputs['suggest_settlements']['groups'][0]['total']);
        self::assertSame('settlement.draft', $outputs['propose_settlement']['kind']);
        self::assertSame('open', $outputs['propose_credit_note']['status']);
        $outputs['get_document']['vendor'] = ['name' => 'Fixture', 'cvr' => '', 'match' => null,
            'supplier_account' => null, 'supplier_name' => null, 'vendor_iban' => 'hidden'];
        $this->error('VALIDATION_ERROR', fn() => SaldiSchemaValidator::validate(
            $outputs['get_document'],
            $registry->get('get_document')->dataSchema
        ));
        $outputs['get_daily_overview']['unknown'] = true;
        $this->error('VALIDATION_ERROR', fn() => SaldiSchemaValidator::validate(
            $outputs['get_daily_overview'],
            $registry->get('get_daily_overview')->dataSchema
        ));
    }

    public function testCompanionHandlerDispatchAndInputBoundaries(): void
    {
        $registry = SaldiToolRegistry::default();
        foreach ($this->inputs() as $name => $args) {
            $tool = $registry->get($name);
            SaldiSchemaValidator::validate((object)$args, $tool->inputSchema);
            self::assertArrayHasKey('data', ($tool->handler)($this->env->ctx(), $this->env->db, $args), $name);
        }
        foreach (
            ['invoice' => str_repeat('x', 31), 'exclude_line_id' => 0,
            'party' => ['ledger' => 'supplier', 'account' => '2000', 'side' => 'other']] as $key => $value
        ) {
            $output = $this->toolCall('suggest_posting', array_replace($this->inputs()['suggest_posting'], [$key => $value]));
            self::assertSame('VALIDATION_ERROR', $output['error']['code']);
        }
        $missing = $this->toolCall('get_document', ['ref' => 'pool:999999']);
        self::assertSame('NOT_FOUND', $missing['error']['code']);
        $missing = $this->toolCall('get_procedure', ['slug' => 'missing-procedure']);
        self::assertSame('NOT_FOUND', $missing['error']['code']);
        $invalid = $this->toolCall('propose_payment_registration', array_replace($this->inputs()['propose_payment_registration'], ['invoice_numbers' => ['MISSING'], 'request_id' => 'missing-invoice']));
        self::assertSame('VALIDATION_ERROR', $invalid['error']['code']);
        $args = $this->inputs()['propose_party'] + ['bank_account' => 'hidden'];
        $this->error(
            'VALIDATION_ERROR',
            fn() => SaldiSchemaValidator::validate(
                $args,
                $registry->get('propose_party')->inputSchema
            )
        );
        $line = ['description' => 'Invoice',
            'amount' => '1250.00',
            'invoice' => 'INV-1',
            'due_date' => '2026-07-10',
            'vat_free' => false,
            'debit' => ['ledger' => 'account',
            'account' => '1000',
            'vat_code' => 'K1'],
            'credit' => ['ledger' => 'supplier',
            'account' => '2000']];
        $args = ['date' => '2026-06-10',
            'lines' => [$line],
            'document_ref' => 'pool:1',
            'explanation' => 'Requested',
            'request_id' => 'invoice-fixture'];
        SaldiSchemaValidator::validate($args, $registry->get('propose_journal_entry')->inputSchema);
        $args['lines'][0]['invoice'] = 'INV:<bad>';
        $this->error(
            'VALIDATION_ERROR',
            fn() => SaldiSchemaValidator::validate(
                $args,
                $registry->get('propose_journal_entry')->inputSchema
            )
        );
        $args = $this->inputs()['report_problem'];
        $args['details'] = str_repeat('a', 2001);
        $this->error(
            'VALIDATION_ERROR',
            fn() => SaldiSchemaValidator::validate(
                $args,
                $registry->get('report_problem')->inputSchema
            )
        );
        $args = $this->inputs()['get_procedure'];
        $args['slug'] = '../secret';
        $this->error(
            'VALIDATION_ERROR',
            fn() => SaldiSchemaValidator::validate(
                $args,
                $registry->get('get_procedure')->inputSchema
            )
        );
    }
}
