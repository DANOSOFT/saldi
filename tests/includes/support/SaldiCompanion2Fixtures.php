<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiCompanion2Fixtures.php --- ai/mcp-assistant --- 2026.10.05
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
// Synthetic companion part 2 inputs and success data for the foundation contract.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Seed valid shared business fixtures and render implemented proposal kinds.
// 20261005 CDX/LH Add regression coverage for companion review corrections.

declare(strict_types=1);

final class SaldiCompanion2Fixtures
{
    /** @return array<string,array> Synthetic valid inputs for all seventeen new tools. */
    public static function inputs(?SaldiDb $db = null): array
    {
        $common = ['request_id' => 'companion2-fixture', 'explanation' => 'Requested', 'tainted' => true];
        $batch = str_repeat('a', 32);
        $inputs = [
            'import_bank_statement' => ['bank_account' => '1000', 'content' => "date;amount\n2026-06-10;10,00"],
            'list_bank_lines' => ['batch_id' => $batch],
            'propose_bank_entries' => $common + ['batch_id' => $batch],
            'check_journal' => ['journal_id' => 11],
            'suggest_settlements' => ['party_type' => 'debtor'],
            'propose_settlement' => $common + ['party_type' => 'debtor', 'party_account' => '1000',
                'item_ids' => [701, 702]],
            'match_purchase_order' => ['supplier_account' => '2000'],
            'propose_purchase_invoice_stage' => $common + ['order_id' => 52, 'invoice_number' => 'INV-1',
                'invoice_date' => '2026-06-10', 'lines' => [['line_id' => $db === null ? 1 : (int)$db->value('SELECT id FROM ordrelinjer WHERE ordre_id = 52 ORDER BY id LIMIT 1'), 'receive_quantity' => '1.000']]],
            'suggest_reorders' => [],
            'propose_sales_order' => $common + ['customer_account' => '1000', 'type' => 'offer',
                'lines' => [['description' => 'Paper', 'quantity' => '2.500']]],
            'propose_credit_note' => $common + ['invoice_order_id' => 51, 'reason' => 'Returned goods'],
            'get_customer_statement' => ['party_id' => 10],
            'compare_periods' => ['a_from' => '2026-01-01', 'a_to' => '2026-03-31',
                'b_from' => '2026-04-01', 'b_to' => '2026-06-30'],
            'get_year_end_pack' => [],
            'get_till_summary' => ['date' => '2026-06-10'],
            'get_stock_count' => [],
            'check_master_data' => [],
        ];
        foreach ($inputs as $name => &$args) {
            if (isset($args['request_id'])) {
                $args['request_id'] = 'fixture-' . $name;
            }
        }
        unset($args);
        return $inputs;
    }

    /** Seed only disposable tool-test clones with valid companion business records. */
    public static function seed(SaldiDb $db): void
    {
        if (!$db->hasColumn('ordrer', 'performed_by')) {
            $db->exec('ALTER TABLE ordrer ADD COLUMN performed_by text');
        }
        foreach (['DG', 'KG'] as $art) {
            $db->exec('DELETE FROM grupper WHERE art = ?', [$art]);
            $db->insert('grupper', ['art' => $art, 'kodenr' => 1, 'box1' => '', 'box3' => 'DKK', 'fiscal_year' => 4]);
        }
        $db->exec("UPDATE adresser SET gruppe = 1 WHERE art IN ('D', 'K')");
        $db->exec('UPDATE ordrer SET momssats = 25, kred_ord_id = 0 WHERE id = 51');
        $db->exec('UPDATE ordrelinjer SET vare_id = 61 WHERE ordre_id = 51');
        foreach ([701 => [10, '10.000'], 702 => [10, '-10.000'], 703 => [20, '10.000'], 704 => [20, '-10.000']] as $id => [$party, $amount]) {
            $db->insert('openpost', ['id' => $id, 'konto_id' => $party, 'konto_nr' => (string)($party * 100),
                'faktnr' => 'SET-' . $id, 'amount' => $amount, 'valuta' => 'DKK', 'valutakurs' => '100',
                'udlignet' => '', 'transdate' => '2026-06-10', 'forfaldsdate' => '2026-06-24']);
        }
        $db->insert('bank_import_lines', ['batch_id' => str_repeat('a', 32), 'line_no' => 1,
            'bank_account' => '1000', 'transdate' => '2026-06-10', 'description' => 'Reconcile bank fee',
            'amount' => '-10.000', 'currency' => 'DKK', 'line_hash' => hash('sha256', 'companion2-bank'),
            'status' => 'new', 'created_by' => 910001, 'created_at' => gmdate('Y-m-d H:i:s')]);
        $db->insert('posting_rules', ['id' => 700, 'match_type' => 'text', 'match_value' => 'reconcile bank fee',
            'account' => '4000', 'created_by' => 'user:910001', 'created_at' => gmdate('Y-m-d H:i:s')]);
        if ($db->isPostgres()) {
            $db->exec("SELECT setval(pg_get_serial_sequence('posting_rules', 'id'), (SELECT MAX(id) FROM posting_rules))");
        }
        $db->exec("DELETE FROM grupper WHERE art = 'VG' AND kodenr = 1 AND fiscal_year = 4");
        $db->insert('grupper', ['art' => 'VG', 'kodenr' => 1, 'fiscal_year' => 4, 'box8' => 'on']);
        $db->exec('UPDATE varer SET gruppe = 1');
    }

    /** @return array<string,array> Explicit data fixtures; draft items reuse a real inbox shape. */
    public static function data(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
    {
        $bank = ['line_id' => 1, 'date' => '2026-06-10', 'description' => 'Payment',
            'amount' => '10.01', 'currency' => 'DKK', 'status' => 'new'];
        $statement = ['item_id' => 1, 'invoice' => 'INV-1', 'date' => '2026-06-10',
            'due_date' => null, 'amount' => '10.00', 'currency' => 'DKK'];
        $data = [
            'import_bank_statement' => ['batch_id' => str_repeat('a', 32), 'lines' => [$bank],
                'duplicates' => 0, 'warnings' => []],
            'list_bank_lines' => ['rows' => [$bank + ['match' => ['party' => ['ledger' => 'customer',
                'account' => '1000', 'name' => 'Alpha'], 'account' => null, 'invoice' => 'INV-1',
                'confidence' => 'high']]], 'total' => 1],
            'check_journal' => ['journal_id' => 11, 'status' => 'open', 'ready' => false,
                'errors' => [['code' => 'unbalanced', 'severity' => 'error', 'line_ids' => [111],
                    'bilag' => 1, 'message_da' => 'Bilaget stemmer ikke',
                    'detail' => ['difference' => '10.00', 'currency' => 'DKK']]], 'warnings' => []],
            'suggest_settlements' => ['groups' => [['party' => ['party_type' => 'debtor',
                'account' => '1000', 'name' => 'Alpha', 'party_id' => 10], 'item_ids' => [1, 2],
                'amounts' => ['10.00', '-10.00'], 'total' => '0.00', 'currency' => 'DKK',
                'reason' => 'Exact pair']]],
            'match_purchase_order' => ['orders' => [['order_id' => 51, 'order_number' => '51', 'score' => 1,
                'lines' => [['line_id' => 1, 'item_no' => 'P1', 'description' => 'Paper', 'currency' => 'DKK',
                    'ordered' => '5.000', 'received' => '1.000', 'remaining' => '4.000',
                    'order_price' => '10.00', 'invoice_price' => '12.00', 'quantity_difference' => null]],
                'differences' => [['line_id' => 1, 'field' => 'unit_price', 'order_value' => '10.00',
                    'invoice_value' => '12.00', 'difference' => '2.00', 'currency' => 'DKK']]]]],
            'suggest_reorders' => ['rows' => [['product_id' => 1, 'item_no' => 'P1', 'text' => 'Paper',
                'per_day' => '1.500', 'on_hand' => '2.000', 'on_order' => '0.000', 'cover_days' => '1.333',
                'suggest_qty' => '60.000', 'supplier' => ['account' => '2000', 'name' => 'Beta']]], 'total' => 1],
            'get_customer_statement' => ['party' => ['party_id' => 10, 'account' => '1000', 'name' => 'Alpha'],
                'from' => '2026-01-01', 'to' => '2026-06-30', 'currency' => 'DKK', 'open_items' => [$statement],
                'payments' => [], 'invoices' => [$statement], 'opening_balance' => '0.00',
                'closing_balance' => '10.00', 'overdue_sum' => '0.00', 'converted' => true],
            'compare_periods' => ['rows' => [['key' => '1000', 'name' => 'Cash', 'a' => '10.00', 'b' => '20.00',
                'change' => '10.00', 'change_pct' => '100.000', 'currency' => 'DKK']], 'total' => 1,
                'currency' => 'DKK', 'revenue_hidden' => false],
            'get_year_end_pack' => ['fiscal_year' => ['id' => 4, 'start' => '2026-01-01', 'end' => '2026-12-31'],
                'currency' => 'DKK', 'revenue_hidden' => false,
                'trial_balance' => ['rows' => [['account' => '1000', 'name' => 'Cash', 'kind' => 'balance',
                    'currency' => 'DKK', 'opening' => '0.00', 'debit' => '10.00', 'credit' => '0.00',
                        'closing' => '10.00']],
                    'totals' => ['currency' => 'DKK', 'opening' => '0.00', 'debit' => '10.00',
                        'credit' => '0.00', 'closing' => '10.00'],
                    'revenue_hidden' => false],
                'open_items' => [$statement + ['party_type' => 'debtor', 'party_account' => '1000',
                    'party_name' => 'Alpha']],
                'vat' => [['quarter' => 1, 'from' => '2026-01-01', 'to' => '2026-03-31', 'currency' => 'DKK',
                    'note' => '', 'period_closed' => false, 'revenue_hidden' => false,
                    'sales_vat' => '0.00', 'purchase_vat' => '0.00', 'payable' => '0.00', 'codes' => []]],
                'unposted_journals' => [['date' => '2026-06-10', 'note' => 'Review', 'status' => 'open',
                    'created_by' => '', 'journal_id' => 11, 'lines' => 1,
                        'screen' => SaldiScreens::link('journal', 11)]],
                'periods_closed' => [['month' => '2026-01', 'closed' => false]],
                    'notes' => ['Review missing documents'],
                'links' => [['label' => 'Revisorpakke', 'screen' => SaldiScreens::link('year_end_pack')]]],
            'get_till_summary' => ['date' => '2026-06-10', 'currency' => 'DKK', 'revenue_hidden' => false,
                'rows' => [['till' => 1, 'receipts' => 1, 'currency' => 'DKK', 'turnover_net' => '8.00',
                    'turnover_gross' => '10.00', 'refunds' => '0.00',
                    'payments' => [['method' => 'cash', 'currency' => 'DKK', 'amount' => '10.00']],
                    'expected' => '10.00', 'counted' => null, 'difference' => null,
                    'cash_difference_postings' => [['date' => '2026-06-10', 'account' => '1000',
                        'bilag' => 1, 'currency' => 'DKK', 'amount' => '0.00']]]]],
            'get_stock_count' => ['date' => '2026-06-10', 'rows' => [['product_id' => 1, 'item_no' => 'P1',
                'text' => 'Paper', 'currency' => 'DKK', 'warehouse' => 1, 'variant_id' => 0, 'expected' => '10.000', 'counted' => '9.000',
                'difference' => '-1.000', 'value_difference' => '-10.00', 'posted' => false]],
                'fefo' => [['product_id' => 1, 'batch_id' => 1, 'item_no' => 'P1', 'text' => 'Paper',
                    'expiry_date' => '2026-06-11', 'days_left' => 1, 'quantity' => '2.000',
                    'screen' => SaldiScreens::link('product', 1)]]],
            'check_master_data' => ['rows' => [['kind' => 'missing_email', 'label' => 'Alpha',
                'screen' => SaldiScreens::link('debtor', 10)]], 'total' => 1],
        ];
        foreach (
            ['propose_bank_entries' => 'journal.draft_entry', 'propose_settlement' => 'settlement.draft',
            'propose_purchase_invoice_stage' => 'purchase_order.invoice_stage',
                'propose_sales_order' => 'sales_order.draft',
            'propose_credit_note' => 'credit_note.draft'] as $tool => $kind
        ) {
            $draft = $item;
            $draft['kind'] = $kind;
            $input = self::inputs($db)[$tool];
            $draft['payload'] = array_diff_key($input, array_flip(['request_id', 'explanation', 'tainted']));
            if ($tool === 'propose_bank_entries') {
                $draft['payload'] = ['date' => '2026-06-10', 'fiscal_year' => 4, 'kladde_id' => null, 'lines' => [
                    ['description' => 'Payment', 'amount' => '10.00', 'currency' => 'DKK',
                        'debit' => ['type' => 'F', 'account' => '1000'],
                        'credit' => ['type' => 'D', 'account' => '1000']],
                ]];
            }
            $handler = SaldiKindRegistry::default()->get($kind);
            if ($tool !== 'propose_bank_entries') {
                $draft['payload'] = $handler->validate($ctx, $db, $draft['payload'])['payload'];
            }
            $draft['card'] = $handler->render($ctx, $db, $draft);
            $data[$tool] = $draft;
        }
        return $data;
    }
}
