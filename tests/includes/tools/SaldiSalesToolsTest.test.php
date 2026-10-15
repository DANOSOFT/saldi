<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiSalesToolsTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// Historical customer statements, currency totals and permission regressions.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Add regression coverage for companion review corrections.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiSalesTestCase.php';
require_once __DIR__ . '/../../../includes/mcp/SaldiMcpServer.php';

final class SaldiSalesToolsTest extends SaldiSalesTestCase
{
    private function post(string $amount, string $date, array $extra = []): int
    {
        return $this->env->db->insert('openpost', $extra + ['konto_id' => $this->customer,
            'konto_nr' => '1000', 'amount' => $amount, 'transdate' => $date, 'forfaldsdate' => '2026-06-01',
            'valuta' => 'DKK', 'valutakurs' => '100.000', 'udlignet' => '0', 'faktnr' => 'INV-1']);
    }

    public function testBalancesAndHistoricalOpenItemsIncludingLaterSettlements(): void
    {
        $old = $this->post('100.005', '2026-05-01', ['udlignet' => '1', 'udlign_date' => '2026-07-01']);
        $invoice = $this->post('200.005', '2026-06-10');
        $payment = $this->post('-50.005', '2026-06-15', ['kladde_id' => 4]);
        $this->post('300.000', '2026-07-01');
        $settled = $this->post('80.000', '2026-05-01', ['udlignet' => '1', 'udlign_date' => '2026-05-02']);
        $this->post('-80.000', '2026-05-02', ['udlignet' => '1', 'udlign_date' => '2026-05-02', 'kladde_id' => 4]);
        $result = SaldiSalesTools::statement(
            $this->env->ctx(),
            $this->env->db,
            ['account' => '1000', 'from' => '2026-06-01', 'to' => '2026-06-30']
        );
        $data = $result['data'];
        self::assertSame('100.01', $data['opening_balance']);
        self::assertSame('250.01', $data['closing_balance']);
        self::assertSame('250.01', $data['overdue_sum']);
        self::assertSame([$old, $invoice, $payment], array_column($data['open_items'], 'item_id'));
        self::assertNotContains($settled, array_column($data['open_items'], 'item_id'));
        self::assertSame([$invoice], array_column($data['invoices'], 'item_id'));
        self::assertSame([$payment], array_column($data['payments'], 'item_id'));
        self::assertSame('-50.01', $data['payments'][0]['amount']);
        self::assertFalse($result['possibly_incomplete']);
        self::assertSame('saldi:party:' . $this->customer, $result['citations'][0]['id']);
        SaldiSchemaValidator::validate($data, SaldiToolRegistry::default()->get('get_customer_statement')->dataSchema);
    }

    public function testCreditNoteIsAnInvoiceAndForeignTotalsUseStoredRate(): void
    {
        $source = $this->invoice();
        $credit = $this->env->db->insert('ordrer', ['art' => 'DK', 'status' => 3,
            'konto_id' => $this->customer, 'ordrenr' => 90]);
        $creditPost = $this->post('-10.000', '2026-06-15', ['refnr' => $credit]);
        $foreign = $this->post('20.000', '2026-06-20', ['valuta' => 'EUR', 'valutakurs' => '750.000']);
        $result = SaldiSalesTools::statement(
            $this->env->ctx(),
            $this->env->db,
            ['party_id' => $this->customer, 'from' => '2026-06-01', 'to' => '2026-06-30']
        );
        self::assertSame('140.00', $result['data']['closing_balance']);
        self::assertSame('DKK', $result['data']['currency']);
        self::assertContains($creditPost, array_column($result['data']['invoices'], 'item_id'));
        self::assertSame([], $result['data']['payments']);
        $rows = array_column($result['data']['invoices'], null, 'item_id');
        self::assertSame('EUR', $rows[$foreign]['currency']);
        self::assertSame('20.00', $rows[$foreign]['amount']);
    }

    public function testMissingRateAndBadInputsFailClearly(): void
    {
        $this->post('20.000', '2026-06-10', ['valuta' => 'NO_RATE', 'valutakurs' => '100.000']);
        $this->error('UNAVAILABLE', fn() => SaldiSalesTools::statement(
            $this->env->ctx(),
            $this->env->db,
            ['party_id' => $this->customer, 'from' => '2026-06-01', 'to' => '2026-06-30']
        ));
        $this->error('VALIDATION_ERROR', fn() => SaldiSalesTools::statement($this->env->ctx(), $this->env->db, []));
        $this->error('VALIDATION_ERROR', fn() => SaldiSalesTools::statement(
            $this->env->ctx(),
            $this->env->db,
            ['account' => '1000', 'from' => '2026-07-01', 'to' => '2026-06-30']
        ));
        $this->error('NOT_FOUND', fn() => SaldiSalesTools::statement(
            $this->env->ctx(),
            $this->env->db,
            ['party_id' => $this->customer, 'account' => '9999']
        ));
        $this->error('PERMISSION_DENIED', fn() => SaldiSalesTools::statement(
            $this->env->ctx(910001, str_repeat('0', 16)),
            $this->env->db,
            ['account' => '1000']
        ));
    }

    public function testBoundedListsKeepCompleteTotals(): void
    {
        $this->env->db->exec(
            "INSERT INTO openpost (konto_id,amount,transdate,udlignet,valuta) " .
            "SELECT ?, 1.000, CAST('2026-06-10' AS date), '0', 'DKK' FROM generate_series(1,501)",
            [$this->customer]
        );
        $result = SaldiSalesTools::statement(
            $this->env->ctx(),
            $this->env->db,
            ['account' => '1000', 'from' => '2026-06-01', 'to' => '2026-06-30']
        );
        self::assertTrue($result['possibly_incomplete']);
        self::assertCount(500, $result['data']['open_items']);
        self::assertSame('501.00', $result['data']['closing_balance']);
    }
    private function callTool(string $name, array $args): array
    {
        $ctx = $this->env->ctx(910001, null, 'agent:saldi-assist', 99, ['read', 'draft']);
        $server = new SaldiMcpServer(
            null,
            fn(string $token): array => ['ctx' => $ctx, 'db' => $this->env->db],
            [],
            'test'
        );
        $token = 'smcp1.local.10.99.' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $response = $server->handle(['method' => 'POST',
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token],
            'body' => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => (object)$args]], JSON_THROW_ON_ERROR)]);
        self::assertSame(200, $response['status']);
        self::assertArrayHasKey('result', $response['body']);
        $envelope = $response['body']['result']['structuredContent'];
        SaldiSchemaValidator::validate($envelope, SaldiToolRegistry::default()->get($name)->outputSchema());
        return $envelope;
    }

    public function testActualToolSuccessErrorEnvelopesAndProposalReplay(): void
    {
        $this->env->enable();
        $this->post('100.000', '2026-06-10');
        $statement = $this->callTool('get_customer_statement', ['account' => '1000',
            'from' => '2026-06-01', 'to' => '2026-06-30']);
        self::assertTrue($statement['ok'], json_encode($statement));
        $sales = ['customer_account' => '1000', 'type' => 'offer',
            'lines' => [['product_id' => $this->product, 'quantity' => '1', 'unit_price' => '10.00']],
            'request_id' => 'sales-replay', 'explanation' => 'Test'];
        $proposed = $this->callTool('propose_sales_order', $sales);
        self::assertTrue($proposed['ok'], json_encode($proposed));
        self::assertSame('awaiting_approval', $proposed['receipt']['state']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM ordrer'));
        $again = $this->callTool('propose_sales_order', $sales);
        self::assertSame($proposed['data']['id'], $again['data']['id']);
        self::assertTrue($again['data']['deduplicated']);
        $source = $this->invoice();
        $credit = $this->callTool('propose_credit_note', ['invoice_order_id' => $source['id'],
            'reason' => 'Returneret', 'request_id' => 'credit-envelope', 'explanation' => 'Test']);
        self::assertTrue($credit['ok'], json_encode($credit));
        self::assertSame('awaiting_approval', $credit['receipt']['state']);
        foreach (['get_customer_statement', 'propose_sales_order', 'propose_credit_note'] as $tool) {
            $error = $this->callTool($tool, ['unexpected' => true]);
            self::assertFalse($error['ok']);
            self::assertSame('VALIDATION_ERROR', $error['error']['code']);
        }
    }
    public function testManualJournalInvoiceAndItsPaymentUseDocumentOrigin(): void
    {
        $invoice = $this->post('100.000', '2026-06-10', ['kladde_id' => 7, 'faktnr' => 'MANUAL-7']);
        $payment = $this->post('-100.000', '2026-06-11', ['kladde_id' => 7, 'faktnr' => 'MANUAL-7']);
        $result = SaldiSalesTools::statement($this->env->ctx(), $this->env->db, ['account' => '1000', 'from' => '2026-06-01', 'to' => '2026-06-30']);
        self::assertSame([$invoice], array_column($result['data']['invoices'], 'item_id'));
        self::assertSame([$payment], array_column($result['data']['payments'], 'item_id'));
        self::assertSame('0.00', $result['data']['closing_balance']);
    }

    public function testInvoicePaymentSharingDocumentReferenceKeepsOppositeOrigins(): void
    {
        $source = $this->invoice();
        $db = $this->env->db;
        $db->exec("UPDATE ordrer SET fakturanr='INV-1' WHERE id=?", [$source['id']]);
        $invoice = $this->post('100', '2026-06-10', ['refnr' => $source['id']]);
        $payment = $this->post('-100', '2026-06-10', ['refnr' => $source['id']]);
        $result = SaldiSalesTools::statement($this->env->ctx(), $db, ['account' => '1000', 'from' => '2026-06-01', 'to' => '2026-06-30']);
        self::assertSame([$invoice], array_column($result['data']['invoices'], 'item_id'));
        self::assertSame([$payment], array_column($result['data']['payments'], 'item_id'));
    }

    public function testImportedRowsDiscloseUncertainDocumentClassification(): void
    {
        $unknown = $this->post('100', '2026-06-10', ['refnr' => null, 'bilag_id' => null, 'faktnr' => '', 'forfaldsdate' => null]);
        $result = SaldiSalesTools::statement($this->env->ctx(), $this->env->db, ['account' => '1000', 'from' => '2026-06-01', 'to' => '2026-06-30']);
        self::assertSame($unknown, $result['data']['open_items'][0]['item_id']);
        self::assertTrue($result['data']['open_items'][0]['classification_uncertain']);
        SaldiSchemaValidator::validate($result['data'], SaldiToolRegistry::default()->get('get_customer_statement')->dataSchema);
    }

    public function testManualCreditInvoiceAndCustomerRefundUseCounterAccountOrigin(): void
    {
        $db = $this->env->db;
        $db->insert('kontoplan', ['kontonr' => 5500, 'regnskabsaar' => 4, 'kontotype' => 'S', 'beskrivelse' => 'Bank']);
        $creditLine = $db->insert('kassekladde', ['kladde_id' => 11, 'd_type' => 'F', 'debet' => 2000,
            'k_type' => 'D', 'kredit' => 1000, 'amount' => '100', 'transdate' => '2026-06-10']);
        $refundLine = $db->insert('kassekladde', ['kladde_id' => 11, 'd_type' => 'D', 'debet' => 1000,
            'k_type' => 'F', 'kredit' => 5500, 'amount' => '100', 'transdate' => '2026-06-10']);
        $credit = $this->post('-100', '2026-06-10', ['bilag_id' => $creditLine, 'kladde_id' => 11]);
        $refund = $this->post('100', '2026-06-10', ['bilag_id' => $refundLine, 'kladde_id' => 11]);
        $result = SaldiSalesTools::statement($this->env->ctx(), $db, ['account' => '1000', 'from' => '2026-06-01', 'to' => '2026-06-30']);
        self::assertSame([$credit], array_column($result['data']['invoices'], 'item_id'));
        self::assertSame([$refund], array_column($result['data']['payments'], 'item_id'));
        self::assertFalse($result['data']['invoices'][0]['classification_uncertain']);
        self::assertFalse($result['data']['payments'][0]['classification_uncertain']);
    }

    public function testCreditNoteAndPosRefundSharingReferenceKeepOppositeOrigins(): void
    {
        $db = $this->env->db;
        $credit = $db->insert('ordrer', ['art' => 'DK', 'status' => 3, 'konto_id' => $this->customer,
            'ordrenr' => 90, 'fakturanr' => 'CREDIT-90']);
        $invoice = $this->post('-100', '2026-06-10', ['refnr' => $credit, 'faktnr' => 'CREDIT-90']);
        $refund = $this->post('100', '2026-06-10', ['refnr' => $credit, 'faktnr' => 'CREDIT-90']);
        $result = SaldiSalesTools::statement($this->env->ctx(), $db, ['account' => '1000', 'from' => '2026-06-01', 'to' => '2026-06-30']);
        self::assertSame([$invoice], array_column($result['data']['invoices'], 'item_id'));
        self::assertSame([$refund], array_column($result['data']['payments'], 'item_id'));
        self::assertFalse($result['data']['payments'][0]['classification_uncertain']);
    }

}
