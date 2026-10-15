<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiReadToolsTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Seeded read catalogue, branch permissions, balances, currencies and envelopes.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cover F1 integration behavior and regression cases.
// 20261002 CL/LH Invoice paid from settled open items, due-date fallbacks, placeholder exchange rates, creditor open items sorted largest debt first.

declare(strict_types=1);

require_once __DIR__ . '/SaldiToolDbTestCase.php';

final class SaldiReadToolsTest extends SaldiToolDbTestCase
{
    public function testInboxVisibilityKindStatusAndPagination(): void
    {
        $items = [];
        for ($i = 0; $i < 3; $i++) {
            $items[] = $this->read('create_followup_task', [
                'title' => 'Review records ' . $i,
                'request_id' => 'inbox-' . $i,
            ])['data'];
        }
        $this->read('propose_memory_fact', [
            'scope' => 'user',
            'fact' => 'Use concise summaries',
            'request_id' => 'inbox-memory',
        ]);
        $other = $this->env->ctx(910002);
        $this->read('create_followup_task', ['title' => 'Private other task', 'request_id' => 'other'], $other);
        $page = $this->read('list_inbox_items', ['limit' => 2]);
        self::assertSame(4, $page['data']['total']);
        self::assertCount(2, $page['data']['rows']);
        self::assertTrue($page['possibly_incomplete']);
        $second = $this->read('list_inbox_items', ['limit' => 2, 'offset' => 2]);
        self::assertFalse($second['possibly_incomplete']);
        self::assertSame([$items[1]['id'], $items[0]['id']], array_column($second['data']['rows'], 'id'));
        self::assertSame(3, count($this->read('list_inbox_items', ['kind' => 'followup.task'])['data']['rows']));
        $this->approve($items[0]);
        $applied = $this->read('list_inbox_items', ['status' => 'applied'])['data']['rows'];
        self::assertSame([$items[0]['id']], array_column($applied, 'id'));
        self::assertArrayNotHasKey('payload', $applied[0]);
        self::assertSame('applied', $this->read('get_inbox_item', ['item_id' => $items[0]['id']])['data']['status']);
        self::assertSame(4, count($this->read('list_inbox_items', ['status' => 'all'])['data']['rows']));
        $citation = SaldiDraftTools::resolve($this->env->ctx(), $this->env->db, 'saldi:inbox:' . $items[0]['id']);
        self::assertSame(SaldiScreens::link('inbox_item', $items[0]['id']), $citation['screen']);
    }

    public function testEveryReadHandlerEnforcesItsPermissionDirectly(): void
    {
        $item = $this->read(
            'create_followup_task',
            ['title' => 'Review records',
            'request_id' => 'permission-fixture']
        )['data'];
        $arguments = [
            'get_company_context' => [], 'get_memory' => [], 'search_accounts' => [],
            'get_account_balance' => ['account' => '1000'], 'list_ledger_entries' => ['account' => '1000'],
            'get_trial_balance' => [], 'list_journals' => [], 'get_journal' => ['journal_id' => 11],
            'list_open_items' => ['party_type' => 'debtor'], 'search_parties' => ['type' => 'debtor',
                'query' => 'Alpha'],
            'get_party' => ['party_id' => 10], 'search_invoices' => ['direction' => 'sales'],
            'get_invoice' => ['order_id' => 51], 'search_products' => [],
            'list_inbox_items' => [], 'get_inbox_item' => ['item_id' => $item['id']],
        ];
        $denied = $this->env->ctx(910001, str_repeat('0', 16), 'agent:saldi-assist', 99, ['read']);
        foreach ($arguments as $name => $args) {
            $this->error('PERMISSION_DENIED', fn() => $this->read($name, $args, $denied));
        }
    }

    public function testZeroFilteringAndJournalForeignCurrency(): void
    {
        $db = $this->env->db;
        $db->exec('UPDATE kontoplan SET primo = 0 WHERE kontonr = 1000');
        $db->exec('DELETE FROM transaktioner WHERE kontonr = 1000');
        foreach ([['10', '0'], ['0', '10']] as [$debit, $credit]) {
            $db->insert(
                'transaktioner',
                ['kontonr' => 1000,
                    'transdate' => '2026-02-01',
                    'debet' => $debit,
                'kredit' => $credit]
            );
        }
        $trial = $this->read('get_trial_balance', ['from' => '2026-06-01', 'to' => '2026-07-01'])['data'];
        self::assertNotContains('1000', array_column($trial['rows'], 'account'));
        $db->exec("DELETE FROM grupper WHERE art='VK' AND kodenr=991");
        $db->insert('grupper', ['art' => 'VK', 'kodenr' => 991, 'box1' => 'EUR']);
        $db->exec('UPDATE kassekladde SET valuta=991 WHERE id=111');
        $journal = $this->read('get_journal', ['journal_id' => 11])['data'];
        self::assertSame('EUR', $journal['lines'][0]['currency']);
        self::assertSame('12.35', $journal['lines'][0]['amount']);
    }
    public function testCompanyAndPrivateMemory(): void
    {
        $data = $this->read('get_company_context')['data'];
        self::assertSame('Own Alpha Company', $data['company_name']);
        self::assertSame(['id' => 4, 'start' => '2026-01-01', 'end' => '2026-12-31'], $data['fiscal_year']);
        self::assertSame("O'Brien", $data['user']['name']);
        self::assertSame($this->env->ctx()->userHash(), $data['user_hash']);
        $memory = $this->read('get_memory')['data'];
        self::assertSame(['Company fact'], array_column($memory['company'], 'fact'));
        self::assertSame(['Own fact'], array_column($memory['user'], 'fact'));
        self::assertSame(
            ['company_used' => 12,
                'company_cap' => 2000,
                'user_used' => 8,
                'user_cap' => 800],
            $memory['caps']
        );
    }

    public function testAccountQueriesAndMidYearFormulas(): void
    {
        $accounts = $this->read('search_accounts')['data'];
        self::assertSame(['1000', '2000', '4000'], array_column($accounts['rows'], 'account'));
        self::assertSame('3000', $this->read('search_accounts', ['query' => 'Head'])['data']['rows'][0]['account']);
        self::assertSame('1000', $this->read('search_accounts', ['query' => '10'])['data']['rows'][0]['account']);
        $balance = $this->read(
            'get_account_balance',
            ['account' => '1000',
                'from' => '2026-06-01',
            'to' => '2026-07-31']
        )['data'];
        foreach (
            ['opening' => '115.31',
                'debit' => '13.01',
                'credit' => '3.01',
                'movement' => '10.00',
                'closing' => '125.31'] as $key => $value
        ) {
            self::assertSame($value, $balance[$key], $key);
        }
        $trial = $this->read('get_trial_balance', ['from' => '2026-06-01', 'to' => '2026-07-31'])['data'];
        self::assertSame(['1000', '2000'], array_column($trial['rows'], 'account'));
        self::assertSame('DKK', $trial['rows'][0]['currency']);
        self::assertSame($balance['opening'], $trial['rows'][0]['opening']);
        self::assertSame($balance['closing'], $trial['rows'][0]['closing']);
        self::assertSame(3, $this->read('get_trial_balance', ['include_zero' => true])['data']['total']);
        $entries = $this->read(
            'list_ledger_entries',
            ['account' => '1000',
                'from' => '2026-06-01',
                'to' => '2026-07-31',
            'limit' => 1]
        );
        self::assertTrue($entries['possibly_incomplete']);
        self::assertSame(2, $entries['data']['total']);
        self::assertSame('DKK', $entries['data']['rows'][0]['currency']);
        self::assertSame('10.01', $entries['data']['rows'][0]['debit']);
        self::assertSame('saldi:journal:11', $entries['citations'][1]['id']);
        self::assertSame('finans/kassekladde.php?kladde_id=11', $entries['citations'][1]['screen']);
        $default = $this->read('get_account_balance', ['account' => '1000'])['data'];
        self::assertSame('2026-01-01', $default['from']);
        self::assertSame(min(SaldiToolRuntime::today(), '2026-12-31'), $default['to']);
        foreach (
            [['from' => '2025-12-31'],
                ['to' => '2027-01-01'],
                ['from' => '2026-07-01',
                    'to' => '2026-06-01'],
                ['from' => '2026-02-30']] as $range
        ) {
            $this->error(
                'VALIDATION_ERROR',
                fn() => $this->read(
                    'get_account_balance',
                    ['account' => '1000'] + $range
                )
            );
        }
    }

    public function testRevenueRestrictionForLedgerAndSalesAndRecentDocuments(): void
    {
        $this->hideRevenue();
        $ctx = $this->env->ctx(910001, '1022222222222222', 'agent:saldi-assist', 99, ['read', 'draft']);
        foreach (['search_accounts', 'get_trial_balance'] as $name) {
            $r = $this->read($name, [], $ctx)['data'];
            self::assertTrue($r['revenue_hidden']);
            self::assertNotContains('2000', array_column($r['rows'], 'account'));
        }
        foreach (['get_account_balance', 'list_ledger_entries'] as $name) {
            $this->error('NOT_FOUND', fn() => $this->read($name, ['account' => '2000'], $ctx));
        }
        $this->error('PERMISSION_DENIED', fn() => $this->read('search_invoices', ['direction' => 'sales'], $ctx));
        $this->error('PERMISSION_DENIED', fn() => $this->read('get_invoice', ['order_id' => 51], $ctx));
        $this->error('PERMISSION_DENIED', fn() => $this->read('get_invoice', ['order_id' => 53], $ctx));
        self::assertSame([], $this->read('get_party', ['party_id' => 10], $ctx)['data']['recent_documents']);
        self::assertSame(52, $this->read('get_invoice', ['order_id' => 52], $ctx)['data']['order_id']);
        self::assertFalse($this->read('get_trial_balance')['data']['revenue_hidden']);
    }

    public function testJournalStatusesRevisionAndTwoHundredLineCap(): void
    {
        self::assertSame([11], array_column($this->read('list_journals')['data']['rows'], 'journal_id'));
        self::assertSame(
            ['posted',
                'simulated',
                'open'],
            array_column(
                $this->read(
                    'list_journals',
                    ['status' => 'all']
                )['data']['rows'],
                'status'
            )
        );
        self::assertSame(
            [13],
            array_column(
                $this->read(
                    'list_journals',
                    ['status' => 'posted']
                )['data']['rows'],
                'journal_id'
            )
        );
        $r = $this->read('get_journal', ['journal_id' => 11]);
        self::assertSame('12.35', $r['data']['lines'][0]['amount']);
        self::assertSame(['ledger' => 'account', 'account' => '1000', 'name' => 'Cash'], $r['data']['lines'][0]['debit']);
        self::assertSame('saldi:journal_line:111', $r['citations'][1]['id']);
        for ($i = 0; $i < 200; $i++) {
            $this->env->db->insert(
                'kassekladde',
                ['kladde_id' => 11,
                    'bilag' => 8,
                    'transdate' => '2026-06-10',
                    'beskrivelse' => 'Added',
                    'amount' => '1',
                'pos' => $i + 2]
            );
        }
        $after = $this->read('get_journal', ['journal_id' => 11]);
        self::assertCount(200, $after['data']['lines']);
        self::assertTrue($after['possibly_incomplete']);
        self::assertNotSame($r['data']['revision'], $after['data']['revision']);
    }

    public function testOpenItemsGroupCurrencyAndPartyAllowlist(): void
    {
        $plain = $this->read('list_open_items', ['party_type' => 'debtor']);
        self::assertCount(3, $plain['data']['rows']);
        self::assertTrue($plain['data']['rows'][0]['converted']);
        self::assertSame('EUR', $plain['data']['rows'][0]['currency']);
        self::assertSame('20.01', $plain['data']['rows'][0]['amount']);
        self::assertSame('150.04', $plain['data']['rows'][0]['amount_base']);
        self::assertGreaterThan(0, $plain['data']['rows'][0]['item_id']);
        self::assertSame(
            'debtor',
            $this->env->db->value('SELECT a.art FROM openpost o JOIN adresser a ON a.id = o.konto_id WHERE o.id = ?', [$plain['data']['rows'][0]['item_id']]) === 'D' ? 'debtor' : 'other'
        );
        self::assertTrue($plain['data']['converted']);
        $group = $this->read(
            'list_open_items',
            ['party_type' => 'debtor',
            'group_by_party' => true]
        )['data']['rows'][0];
        self::assertSame('275.04', $group['open_amount']);
        self::assertSame('250.04', $group['overdue_amount']);
        self::assertTrue($group['converted']);
        self::assertSame('DKK', $group['currency']);
        self::assertSame(3, $group['items']);
        self::assertCount(
            2,
            $this->read(
                'list_open_items',
                ['party_type' => 'debtor',
                'overdue_only' => true]
            )['data']['rows']
        );
        self::assertSame(
            20,
            $this->read(
                'list_open_items',
                ['party_type' => 'creditor']
            )['data']['rows'][0]['party_id']
        );
        self::assertSame(
            [10],
            array_column(
                $this->read(
                    'search_parties',
                    ['type' => 'debtor',
                    'query' => 'Alpha']
                )['data']['rows'],
                'party_id'
            )
        );
        self::assertSame(
            [40,
                10],
            array_column(
                $this->read(
                    'search_parties',
                    ['type' => 'debtor',
                        'query' => 'Alpha',
                    'include_closed' => true]
                )['data']['rows'],
                'party_id'
            )
        );
        $party = $this->read('get_party', ['party_id' => 10])['data'];
        self::assertSame('1000.00', $party['credit_limit']);
        self::assertSame('275.04', $party['open_amount']);
        self::assertCount(2, $party['recent_documents']);
        self::assertSame([], $this->read('get_party', ['party_id' => 10], $this->only(6))['data']['recent_documents']);
        self::assertStringNotContainsString('never-export', json_encode($party));
        $this->error('NOT_FOUND', fn() => $this->read('get_party', ['party_id' => 30]));
    }

    public function testDirectionAndStoredRecordPermissions(): void
    {
        $debtor = $this->only(6);
        $creditor = $this->only(8);
        $sales = $this->only(5);
        $purchases = $this->only(7);
        $this->error(
            'PERMISSION_DENIED',
            fn() => $this->read(
                'list_open_items',
                ['party_type' => 'creditor'],
                $debtor
            )
        );
        $this->error(
            'PERMISSION_DENIED',
            fn() => $this->read(
                'list_open_items',
                ['party_type' => 'debtor'],
                $creditor
            )
        );
        $this->error(
            'PERMISSION_DENIED',
            fn() => $this->read(
                'search_parties',
                ['type' => 'creditor',
                    'query' => 'Beta'],
                $debtor
            )
        );
        $this->error(
            'PERMISSION_DENIED',
            fn() => $this->read(
                'search_parties',
                ['type' => 'debtor',
                    'query' => 'Alpha'],
                $creditor
            )
        );
        $this->error('PERMISSION_DENIED', fn() => $this->read('get_party', ['party_id' => 20], $debtor));
        $this->error('PERMISSION_DENIED', fn() => $this->read('get_party', ['party_id' => 10], $creditor));
        $this->error('PERMISSION_DENIED', fn() => $this->read('search_invoices', ['direction' => 'purchases'], $sales));
        $this->error('PERMISSION_DENIED', fn() => $this->read('search_invoices', ['direction' => 'sales'], $purchases));
        $this->error('PERMISSION_DENIED', fn() => $this->read('get_invoice', ['order_id' => 52], $sales));
        $this->error('PERMISSION_DENIED', fn() => $this->read('get_invoice', ['order_id' => 51], $purchases));
        self::assertSame(52, $this->read('get_invoice', ['order_id' => 52], $purchases)['data']['order_id']);
        self::assertSame(51, $this->read('get_invoice', ['order_id' => 51], $sales)['data']['order_id']);
    }

    public function testInvoicesProductsAndBounds(): void
    {
        $invoice = $this->read('get_invoice', ['order_id' => 51])['data'];
        self::assertSame('10.01', $invoice['net']);
        self::assertSame('2.01', $invoice['vat']);
        self::assertSame('12.01', $invoice['total']);
        self::assertSame('2026-06-29', $invoice['due_date']);
        self::assertSame($invoice['currency'], $invoice['lines'][0]['currency']);
        self::assertSame('5.01', $invoice['lines'][0]['unit_price']);
        $this->env->db->exec("UPDATE ordrer SET betalt='0.00' WHERE id=51");
        self::assertFalse($this->read('get_invoice', ['order_id' => 51])['data']['paid']);
        $this->env->db->exec("UPDATE ordrer SET betalt='1' WHERE id=51");
        self::assertTrue($this->read('get_invoice', ['order_id' => 51])['data']['paid']);
        self::assertStringNotContainsString('never-export', json_encode($invoice));
        self::assertCount(
            1,
            $this->read(
                'search_invoices',
                ['direction' => 'sales',
                'status' => 'invoiced']
            )['data']['rows']
        );
        self::assertCount(
            1,
            $this->read(
                'search_invoices',
                ['direction' => 'sales',
                'status' => 'offer']
            )['data']['rows']
        );
        self::assertCount(
            1,
            $this->read(
                'search_invoices',
                ['direction' => 'purchases',
                'status' => 'order']
            )['data']['rows']
        );
        self::assertCount(
            0,
            $this->read(
                'search_invoices',
                ['direction' => 'sales',
                'from' => '2026-07-01']
            )['data']['rows']
        );
        self::assertCount(
            1,
            $this->read(
                'search_invoices',
                ['direction' => 'sales',
                'query' => '151']
            )['data']['rows']
        );
        $low = $this->read('search_products', ['low_stock_only' => true])['data']['rows'];
        self::assertSame([61], array_column($low, 'product_id'));
        self::assertSame('12.35', $low[0]['price']);
        self::assertSame('4.01', $low[0]['cost']);
        self::assertCount(1, $this->read('search_products', ['query' => 'V62'])['data']['rows']);
        $this->env->db->exec('UPDATE varer SET beskrivelse = ? WHERE id = 61', [str_repeat('æ', 900)]);
        self::assertSame(500, mb_strlen($this->read('search_products', ['query' => 'V61'])['data']['rows'][0]['text']));
        $this->error('VALIDATION_ERROR', fn() => $this->read('search_accounts', ['limit' => 101]));
        $this->error('VALIDATION_ERROR', fn() => $this->read('search_accounts', ['offset' => -1]));
        foreach (
            ['get_invoice' => ['order_id' => 999],
                'get_journal' => ['journal_id' => 999],
                'get_party' => ['party_id' => 999],
                'get_account_balance' => ['account' => '999']] as $name => $args
        ) {
            $this->error('NOT_FOUND', fn() => $this->read($name, $args));
        }
    }

    public function testEveryCatalogueSuccessAndErrorEnvelopeMatchesOutputSchema(): void
    {
        $followup = $this->toolCall(
            'create_followup_task',
            ['title' => 'Review the accounts',
            'request_id' => 'followup']
        );
        self::assertTrue($followup['ok'], json_encode($followup));
        $id = $followup['data']['id'];
        $args = [
            'get_company_context' => [], 'get_memory' => [], 'search_accounts' => [],
            'get_account_balance' => ['account' => '1000'],
            'list_ledger_entries' => ['account' => '1000'],
            'get_trial_balance' => [],
            'list_journals' => [], 'get_journal' => ['journal_id' => 11],
            'list_open_items' => ['party_type' => 'debtor'], 'search_parties' => ['type' => 'debtor',
                'query' => 'Alpha'], 'get_party' => ['party_id' => 10],
            'search_invoices' => ['direction' => 'sales'], 'get_invoice' => ['order_id' => 51], 'search_products' => [],
            'list_inbox_items' => [], 'get_inbox_item' => ['item_id' => $id],
            'propose_journal_entry' => ['date' => '2026-06-01',
                'lines' => [['description' => 'Draft',
                        'debit' => ['ledger' => 'account',
                            'account' => '1000'],
                        'credit' => ['ledger' => 'account',
                            'account' => '4000'],
                        'amount' => '10']],
                'explanation' => 'Review this entry',
                'request_id' => 'journal'],
            'propose_memory_fact' => ['scope' => 'user',
                'fact' => 'Prefer concise explanations',
                'request_id' => 'memory'],
            'propose_memory_retraction' => ['memory_id' => 2, 'request_id' => 'retract'],
            'create_followup_task' => ['title' => 'Review the accounts', 'request_id' => 'followup'],
        ];
        $denied = $this->env->ctx(910001, str_repeat('0', 16), 'agent:saldi-assist', 99, ['read', 'draft']);
        foreach ($args as $name => $input) {
            $ok = $this->toolCall($name, $input);
            self::assertTrue($ok['ok'], $name . ': ' . json_encode($ok));
            self::assertFalse($ok['committed']);
            self::assertSame(
                str_starts_with(
                    $name,
                    'propose_'
                ) || $name === 'create_followup_task' ? 'awaiting_approval' : 'done',
                $ok['receipt']['state']
            );
            $error = $this->toolCall($name, $input, $denied);
            self::assertFalse($error['ok']);
            self::assertSame('PERMISSION_DENIED', $error['error']['code']);
            self::assertArrayNotHasKey('data', $error);
        }
        $listed = $this->read('list_inbox_items')['data']['rows'];
        self::assertNotEmpty($listed);
        self::assertArrayNotHasKey('payload', $listed[0]);
        $other = $this->env->ctx(910002);
        $this->error('NOT_FOUND', fn() => $this->read('get_inbox_item', ['item_id' => $id], $other));
    }

    private function openpost(int $id, int $party, string $amount, array $extra = []): void
    {
        $this->env->db->insert('openpost', $extra + ['id' => $id, 'konto_id' => $party, 'konto_nr' => (string)($party * 100), 'faktnr' => 'F-' . $id, 'amount' => $amount, 'valuta' => 'DKK', 'valutakurs' => '100', 'udlignet' => '', 'transdate' => '2026-01-01', 'forfaldsdate' => '2026-01-15']);
    }

    public function testInvoicePaidComesFromTheSettledOpenItem(): void
    {
        $db = $this->env->db;
        $paid = fn(int $id): bool => $this->read('get_invoice', ['order_id' => $id])['data']['paid'];
        $db->exec("UPDATE ordrer SET status = 3 WHERE id = 52");
        self::assertFalse($paid(51));
        // Invoice on account (betalt empty), settled in openpost: debtor positive, creditor negative.
        $this->openpost(70, 10, '12.01', ['faktnr' => '151', 'udlignet' => '1']);
        $this->openpost(71, 20, '-12.01', ['faktnr' => '154', 'udlignet' => '1']);
        self::assertTrue($paid(51));
        self::assertTrue($paid(54));
        self::assertTrue($this->read('search_invoices', ['direction' => 'sales', 'status' => 'invoiced'])['data']['rows'][0]['paid']);
        self::assertTrue($this->read('search_invoices', ['direction' => 'purchases', 'status' => 'invoiced'])['data']['rows'][0]['paid']);
        // Still open, another amount, another party or not yet invoiced: unpaid.
        $db->exec("UPDATE openpost SET udlignet = '0' WHERE id = 70");
        self::assertFalse($paid(51));
        $db->exec("UPDATE openpost SET udlignet = '1', amount = '5.00' WHERE id = 70");
        self::assertFalse($paid(51));
        $db->exec("UPDATE openpost SET amount = '12.01', konto_id = 40 WHERE id = 70");
        self::assertFalse($paid(51));
        $this->openpost(72, 20, '12.01', ['faktnr' => '152', 'udlignet' => '1']);
        $db->exec("UPDATE ordrer SET status = 1 WHERE id = 52");
        self::assertFalse($paid(52));
        // A betalt mark still counts (cash and POS sales); zero-like marks do not.
        foreach (['1' => true, 'on' => true, '125.50' => true, '-' => false, '0' => false, '-0' => false, '0.00' => false] as $mark => $expected) {
            $db->exec('UPDATE ordrer SET betalt = ? WHERE id = 51', [(string)$mark]);
            self::assertSame($expected, $paid(51), 'betalt ' . $mark);
        }
    }

    public function testInvoiceDueDateFallsBackToThePostedItemThenTheLegacyTerms(): void
    {
        $db = $this->env->db;
        $due = fn(): ?string => $this->read('get_invoice', ['order_id' => 51])['data']['due_date'];
        $db->exec("UPDATE ordrer SET due_date = NULL, fakturadate = '2026-09-10', betalingsbet = 'Lb. md.', betalingsdage = 30 WHERE id = 51");
        self::assertSame('2026-10-30', $due());
        $db->exec("UPDATE ordrer SET betalingsbet = 'Kontant' WHERE id = 51");
        self::assertSame('2026-09-10', $due());
        $db->exec("UPDATE ordrer SET betalingsbet = 'Netto', betalingsdage = 14 WHERE id = 51");
        self::assertSame('2026-09-24', $due());
        // The posted open item is the truth once the invoice is booked; a non-matching amount is ignored.
        $this->openpost(73, 10, '99.00', ['faktnr' => '151', 'forfaldsdate' => '2026-12-24']);
        self::assertSame('2026-09-24', $due());
        $this->openpost(74, 10, '12.01', ['faktnr' => '151', 'forfaldsdate' => '2026-11-11']);
        self::assertSame('2026-11-11', $due());
        $db->exec("UPDATE ordrer SET due_date = '2026-06-29' WHERE id = 51");
        self::assertSame('2026-06-29', $due());
        $db->exec('UPDATE ordrer SET due_date = NULL, fakturadate = NULL, fakturanr = NULL WHERE id = 51');
        self::assertNull($due());
        self::assertSame('2027-02-14', SaldiReadTools::termsDueDate('2026-12-20', 'Lb. md.', 45));
        self::assertSame('2026-03-02', SaldiReadTools::termsDueDate('2026-01-31', 'netto', 30));
        self::assertSame('2026-02-28', SaldiReadTools::termsDueDate('2026-02-03', 'Forud', 0));
        self::assertSame('2026-02-03', SaldiReadTools::termsDueDate('2026-02-03', 'efterkrav', 8));
    }

    public function testPlaceholderExchangeRatesResolveOrStayOutOfBaseTotals(): void
    {
        $db = $this->env->db;
        $db->exec("DELETE FROM grupper WHERE art = 'VK'");
        $db->exec('DELETE FROM valuta');
        $db->insert('grupper', ['art' => 'VK', 'kodenr' => 7, 'box1' => 'SEK', 'beskrivelse' => 'Svenske kroner']);
        foreach ([['2025-12-01', '65.00'], ['2026-02-01', '70.00']] as [$date, $rate]) {
            $db->insert('valuta', ['gruppe' => 7, 'valdate' => $date, 'kurs' => $rate]);
        }
        $this->openpost(80, 10, '100', ['valuta' => 'SEK', 'valutakurs' => '100']);
        $this->openpost(81, 10, '10', ['valuta' => 'SEK', 'valutakurs' => null]);
        $this->openpost(82, 10, '30', ['valuta' => '-', 'valutakurs' => '0']);
        $this->openpost(83, 10, '40', ['valuta' => 'NOK', 'valutakurs' => '0']);
        $rows = [];
        foreach ($this->read('list_open_items', ['party_type' => 'debtor'])['data']['rows'] as $row) {
            $rows[$row['invoice']] = $row;
        }
        self::assertSame('65.00', $rows['F-80']['amount_base']);
        self::assertSame('6.50', $rows['F-81']['amount_base']);
        self::assertSame('30.00', $rows['F-82']['amount_base']);
        self::assertSame('DKK', $rows['F-82']['currency']);
        self::assertTrue($rows['F-82']['converted']);
        self::assertNull($rows['F-83']['amount_base']);
        self::assertTrue($rows['F-83']['rate_unavailable']);
        self::assertFalse($rows['F-83']['converted']);
        self::assertSame('F-83', array_key_last($rows));
        $list = $this->read('list_open_items', ['party_type' => 'debtor'])['data'];
        self::assertFalse($list['converted']);
        self::assertSame('376.54', $list['totals']['open_amount']);
        self::assertSame(1, $list['totals']['rate_unavailable_items']);
        $group = $this->read('list_open_items', ['party_type' => 'debtor', 'group_by_party' => true])['data']['rows'][0];
        self::assertSame('376.54', $group['open_amount']);
        self::assertSame(1, $group['rate_unavailable_items']);
        self::assertFalse($group['converted']);
        $party = $this->read('get_party', ['party_id' => 10])['data'];
        self::assertSame('376.54', $party['open_amount']);
        self::assertSame(1, $party['rate_unavailable_items']);
        self::assertFalse($party['converted']);
    }

    public function testCreditorOpenItemsPutTheLargestDebtFirst(): void
    {
        $db = $this->env->db;
        $db->insert('adresser', ['id' => 21, 'kontonr' => '2100', 'art' => 'K', 'firmanavn' => 'Creditor Gamma', 'lukket' => '']);
        $this->openpost(90, 20, '-500');
        $this->openpost(91, 20, '-50');
        $this->openpost(92, 20, '30');
        $this->openpost(93, 21, '-900');
        $rows = $this->read('list_open_items', ['party_type' => 'creditor', 'party_account' => '2000'])['data']['rows'];
        self::assertSame(['-500.00', '-50.00', '30.00', '80.00'], array_column($rows, 'amount_base'));
        $grouped = $this->read('list_open_items', ['party_type' => 'creditor', 'group_by_party' => true])['data']['rows'];
        self::assertSame([21, 20], array_column($grouped, 'party_id'));
        self::assertSame(['-900.00', '-440.00'], array_column($grouped, 'open_amount'));
        // Debtors keep the largest receivable first.
        self::assertSame(['150.04', '100.01', '25.00'], array_column($this->read('list_open_items', ['party_type' => 'debtor'])['data']['rows'], 'amount_base'));
    }

    public function testPartySearchPutsTheExactAccountNumberFirst(): void
    {
        foreach ([601 => ['7', 'Syv ApS', ''], 602 => ['1700', 'Hansen 7 ApS', ''], 603 => ['7000', 'Andersen', ''], 604 => ['5555', 'Carlsen', '77777777']] as $id => [$account, $name, $cvr]) {
            $this->env->db->insert('adresser', ['id' => $id, 'kontonr' => $account, 'art' => 'D', 'firmanavn' => $name, 'cvrnr' => $cvr, 'lukket' => '']);
        }
        $rows = $this->read('search_parties', ['type' => 'debtor', 'query' => '7'])['data']['rows'];
        self::assertSame('7', $rows[0]['account']);
        self::assertSame('7000', $rows[1]['account']);
        $rows = $this->read('search_parties', ['type' => 'debtor', 'query' => '77777777'])['data']['rows'];
        self::assertSame(604, $rows[0]['party_id']);
    }
}
