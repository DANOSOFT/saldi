<?php

// --- tests/includes/tools/SaldiDocumentToolsTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// Document metadata, per-source permissions and registration-only draft tools.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Add regression coverage for the confirmed Assist review findings.
// 20261005 CDX/LH Assert visible VAT overrides on document-derived journal cards.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiDocumentTestCase.php';

final class SaldiDocumentToolsTest extends SaldiDocumentTestCase
{
    public function testPoolMetadataIsSanitizedAndSuggestionUsesTheSharedSuggester(): void
    {
        $id = $this->pool(['filename' => 'supplier.pdf']);
        $ctx = $this->env->ctx();
        $db = $this->env->db;
        $db->insert('posting_rules', [
            'match_type' => 'supplier', 'match_value' => '991004', 'account' => '991001',
            'vat_code' => '', 'created_by' => 'user:910001', 'created_at' => '2025-06-01 00:00:00',
        ]);
        $list = SaldiDocumentTools::listDocuments($ctx, $db, []);
        self::assertSame(1, $list['result_count']);
        $row = $list['data']['rows'][0];
        self::assertSame('pool:' . $id, $row['ref']);
        self::assertSame('1250.00', $row['amount']);
        self::assertSame('2025-06-15', $row['date']);
        self::assertSame('2025-06-15T10:00:00Z', $row['created_at']);
        self::assertSame('991004', $row['vendor']['supplier_account']);
        self::assertTrue($row['extracted']);
        self::assertTrue($row['manually_edited']);
        self::assertSame('saldi:pool_file:' . $id, $list['citations'][0]['id']);
        $get = SaldiDocumentTools::getDocument($ctx, $db, ['ref' => $row['ref']]);
        $json = json_encode($get, JSON_THROW_ON_ERROR);
        foreach (['vendor_iban', 'synthetic-bank-marker', 'filepath', $this->appRoot, 'file_contents'] as $private) {
            self::assertStringNotContainsString($private, $json);
        }
        if (class_exists('SaldiPostingSuggester')) {
            $expected = (new SaldiPostingSuggester($ctx, $db))->suggest([
                'description' => $row['description'], 'amount' => $row['amount'], 'date' => $row['date'],
                'party' => ['ledger' => 'supplier', 'account' => '991004'],
            ]);
            self::assertSame($expected['suggestions'][0] ?? null, $get['data']['suggestion']);
            self::assertSame('rule', $get['data']['suggestion']['source']);
        } else {
            self::assertNull($get['data']['suggestion']);
        }
    }

    public function testSuggestionFallsBackToTheVendorNameWhenTheInvoiceTextHasNoHistory(): void
    {
        $id = $this->pool(['filename' => 'vendor-fallback.pdf', 'description' => 'Garn til kollektionen']);
        $this->env->db->insert('posting_rules', [
            'match_type' => 'text', 'match_value' => 'document party', 'account' => '991001',
            'vat_code' => '', 'created_by' => 'user:910001', 'created_at' => '2025-06-01 00:00:00',
        ]);
        $get = SaldiDocumentTools::getDocument($this->env->ctx(), $this->env->db, ['ref' => 'pool:' . $id]);
        self::assertNotNull($get['data']['suggestion']);
        self::assertSame('rule', $get['data']['suggestion']['source']);
    }

    public function testMissingExtractionAndInvalidDecimalOrDateAreHonestNulls(): void
    {
        $id = $this->pool([
            'norm_amount' => null, 'amount' => 'unknown', 'file_date' => '2025-02-31',
            'vendor_name' => '', 'vendor_cvr' => '', 'vendor_konto_id' => null,
            'invoice_number' => '', 'manually_edited' => false,
        ]);
        $row = SaldiDocumentTools::getDocument($this->env->ctx(), $this->env->db, ['ref' => 'pool:' . $id])['data'];
        self::assertNull($row['date']);
        self::assertNull($row['amount']);
        self::assertNull($row['vendor']);
        self::assertNull($row['suggestion']);
        $this->env->db->exec(
            'UPDATE pool_files SET amount = ?, file_date = ? WHERE id = ?',
            ['12.345', '15/06/2025', $id]
        );
        $row = SaldiDocumentTools::getDocument($this->env->ctx(), $this->env->db, ['ref' => 'pool:' . $id])['data'];
        self::assertSame('12.35', $row['amount']);
        self::assertSame('2025-06-15', $row['date']);
    }

    public function testListFiltersAreLiteralAndPaginationHasAccurateTotals(): void
    {
        $this->pool(['filename' => 'vat_100%.pdf']);
        $this->pool(['filename' => 'vat-anything.pdf']);
        $args = ['query' => '_100%', 'from' => '2025-06-01', 'to' => '2025-06-30'];
        $result = SaldiDocumentTools::listDocuments($this->env->ctx(), $this->env->db, $args);
        self::assertSame(1, $result['data']['total']);
        self::assertSame('vat_100%.pdf', $result['data']['rows'][0]['filename']);
        $result = SaldiDocumentTools::listDocuments($this->env->ctx(), $this->env->db, ['limit' => 1]);
        self::assertSame(2, $result['data']['total']);
        self::assertTrue($result['possibly_incomplete']);
        $next = SaldiDocumentTools::listDocuments($this->env->ctx(), $this->env->db, ['limit' => 1, 'offset' => 1]);
        self::assertFalse($next['possibly_incomplete']);
        self::assertNotSame($result['data']['rows'][0]['ref'], $next['data']['rows'][0]['ref']);
    }

    public function testAttachedSourcesAreFilteredBeforeCountingOrPagination(): void
    {
        $db = $this->env->db;
        $this->pool();
        $db->insert('kladdeliste', ['id' => 10, 'bogfort' => '-']);
        $line = $db->insert('kassekladde', ['kladde_id' => 10, 'bilag' => 17, 'transdate' => '2025-06-15']);
        $targets = ['kassekladde' => $line];
        foreach (['creditorOrder' => 'KO', 'debitorOrdrer' => 'DO'] as $source => $art) {
            $targets[$source] = $db->insert('ordrer', ['art' => $art, 'status' => 0, 'ordredate' => '2025-06-15']);
        }
        $documentIds = [];
        foreach ($targets as $source => $target) {
            $documentIds[$source] = $db->insert('documents', [
                'source' => $source, 'source_id' => $target, 'filename' => $source . '.pdf',
                'filepath' => '/must/not/leak', 'timestamp' => '1750000000',
            ]);
        }
        $result = SaldiDocumentTools::listDocuments($this->env->ctx(), $db, ['status' => 'all']);
        self::assertSame(4, $result['data']['total']);
        $row = SaldiDocumentTools::getDocument(
            $this->env->ctx(),
            $db,
            ['ref' => 'doc:' . $documentIds['kassekladde']]
        )['data'];
        self::assertSame('Kassekladde 10, bilag 17', $row['attached_to']['label']);
        self::assertStringNotContainsString('/must/not/leak', json_encode($result, JSON_THROW_ON_ERROR));
        foreach ([2 => 'journal', 7 => 'purchase_order', 8 => null] as $position => $source) {
            $rights = str_repeat('0', 16);
            $rights[$position] = '1';
            $ctx = $this->env->ctx(910001, $rights);
            $result = SaldiDocumentTools::listDocuments($ctx, $db, ['status' => 'all']);
            self::assertSame($source === null ? 1 : 2, $result['data']['total']);
            $visibleSources = array_column($result['data']['rows'], 'source');
            self::assertContains('pool', $visibleSources);
            if ($source !== null) {
                self::assertContains($source, $visibleSources);
            }
            $this->error('NOT_FOUND', fn() => SaldiDocumentTools::getDocument($ctx, $db, [
                'ref' => 'doc:' . $documentIds['debitorOrdrer'],
            ]));
        }
        $db->exec("DELETE FROM settings WHERE var_grp = 'finans' AND var_name = 'hideRevenue'");
        $db->insert('settings', ['var_grp' => 'finans', 'var_name' => 'hideRevenue', 'var_value' => 'on']);
        $rights = str_repeat('1', 16);
        $rights[1] = '0';
        $result = SaldiDocumentTools::listDocuments($this->env->ctx(910001, $rights), $db, ['status' => 'all']);
        self::assertSame(3, $result['data']['total']);
    }

    public function testPermissionAndMissingReferencesUseStructuredErrors(): void
    {
        $ctx = $this->env->ctx(910001, str_repeat('0', 16));
        $this->error('PERMISSION_DENIED', fn() => SaldiDocumentTools::listDocuments($ctx, $this->env->db, []));
        $this->error('NOT_FOUND', fn() => SaldiDocumentTools::getDocument(
            $this->env->ctx(),
            $this->env->db,
            ['ref' => 'pool:999999']
        ));
        $this->error('VALIDATION_ERROR', fn() => SaldiDocumentTools::getDocument(
            $this->env->ctx(),
            $this->env->db,
            ['ref' => '../private']
        ));
    }

    public function testSupplierInvoiceCreatesOnlyTwoSidedDraftsWithDocumentAndVat(): void
    {
        $poolId = $this->pool();
        $args = [
            'supplier_account' => '991004', 'invoice_number' => 'INV-17', 'date' => '2025-06-15',
            'due_date' => '2025-07-15', 'document_ref' => 'pool:' . $poolId,
            'explanation' => 'From invoice fields', 'request_id' => 'supplier-invoice',
            'lines' => [
                ['description' => 'Software', 'account' => '991001', 'amount' => '1000', 'vat_code' => 'K991'],
                ['description' => 'Service', 'account' => '991001', 'amount' => '250'],
            ],
        ];
        $item = SaldiDocumentTools::proposeSupplierInvoice($this->env->ctx(), $this->env->db, $args)['data'];
        self::assertSame('journal.draft_entry', $item['kind']);
        self::assertCount(2, $item['payload']['lines']);
        self::assertEquals(
            ['type' => 'F', 'account' => '991001', 'vat_code' => 'K991'],
            $item['payload']['lines'][0]['debit']
        );
        self::assertEquals(['type' => 'K', 'account' => '991004'], $item['payload']['lines'][0]['credit']);
        self::assertSame('2025-07-15', $item['payload']['lines'][0]['due_date']);
        self::assertSame('Software (fakt. INV-17) (Moms: K991)', $item['card']['rows'][0][2]);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
        $applied = $this->approve($item);
        self::assertCount(2, $applied['result']['line_ids']);
        $replayed = SaldiDocumentTools::proposeSupplierInvoice($this->env->ctx(), $this->env->db, $args);
        $json = json_encode($replayed, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('synthetic-bank-marker', $json);
        self::assertStringNotContainsString($this->appRoot, $json);
        self::assertSame(
            ['document_id' => $applied['result']['document']['document_id']],
            $replayed['data']['result']['document']
        );
    }

    public function testCardWarnsWhenTheSameSupplierInvoiceIsAlreadyInAJournal(): void
    {
        $args = [
            'supplier_account' => '991004', 'invoice_number' => 'INV-DUP', 'date' => '2025-06-15',
            'explanation' => 'From invoice fields', 'request_id' => 'dup-first',
            'lines' => [['description' => 'Software', 'account' => '991001', 'amount' => '1000']],
        ];
        $first = SaldiDocumentTools::proposeSupplierInvoice($this->env->ctx(), $this->env->db, $args)['data'];
        self::assertSame([], array_values(array_filter($first['card']['notices'], static fn(array $n): bool => $n['kind'] === 'warning')));
        $applied = $this->approve($first);
        $applied = (new SaldiInboxService($this->env->db, SaldiKindRegistry::default()))->get($this->env->ctx(), (int)$applied['id']);
        self::assertSame([], array_values(array_filter($applied['card']['notices'] ?? [], static fn(array $n): bool => $n['kind'] === 'warning')));
        $second = SaldiDocumentTools::proposeSupplierInvoice($this->env->ctx(), $this->env->db, ['request_id' => 'dup-second'] + $args)['data'];
        $warnings = array_values(array_filter($second['card']['notices'], static fn(array $n): bool => $n['kind'] === 'warning'));
        self::assertCount(1, $warnings);
        self::assertStringContainsString('INV-DUP', $warnings[0]['text']);
    }

    public function testCustomerAndSupplierPaymentsNeverSettleOpenItems(): void
    {
        foreach (['supplier', 'customer'] as $ledger) {
            $openId = $this->openItem($ledger);
            $item = SaldiDocumentTools::proposePaymentRegistration(
                $this->env->ctx(),
                $this->env->db,
                $this->payment($ledger)
            )['data'];
            self::assertSame('Already transferred; registration only.', $item['evidence']['explanation']);
            self::assertSame([], $item['card']['notices']);
            $line = $item['payload']['lines'][0];
            self::assertSame($ledger === 'supplier' ? 'K' : 'F', $line['debit']['type']);
            self::assertSame($ledger === 'supplier' ? 'F' : 'D', $line['credit']['type']);
            self::assertSame('INV-17', $line['invoice']);
            $this->approve($item);
            self::assertSame('', $this->env->db->value('SELECT udlignet FROM openpost WHERE id = ?', [$openId]));
        }
    }

    public function testPaymentMismatchPreservesExplanationAndAddsNeutralInfo(): void
    {
        $this->openItem();
        $args = $this->payment('supplier', [
            'amount' => '1200.00', 'tainted' => true,
        ]);
        $item = SaldiDocumentTools::proposePaymentRegistration($this->env->ctx(), $this->env->db, $args)['data'];
        self::assertSame('Already transferred; registration only.', $item['evidence']['explanation']);
        self::assertSame(['tainted', 'info'], array_column($item['card']['notices'], 'kind'));
        self::assertSame('Beløbet svarer ikke til de valgte fakturaer (1250.00).', $item['card']['notices'][1]['text']);
        $html = saldi_approval_card_html($item, ['db' => $this->env->db]);
        self::assertStringContainsString('<p class="sac-info">Beløbet', $html);
        self::assertSame('applied', $this->approve($item)['status']);
    }

    public function testInvalidOrSettledInvoicesAreRejectedAndApprovalRevalidates(): void
    {
        $this->openItem('customer');
        $error = $this->error('VALIDATION_ERROR', fn() => SaldiDocumentTools::proposePaymentRegistration(
            $this->env->ctx(),
            $this->env->db,
            $this->payment()
        ));
        self::assertStringContainsString('INV-17', $error->getMessage());
        $openId = $this->openItem();
        $item = SaldiDocumentTools::proposePaymentRegistration(
            $this->env->ctx(),
            $this->env->db,
            $this->payment()
        )['data'];
        $this->env->db->exec('UPDATE openpost SET udlignet = ? WHERE id = ?', ['1', $openId]);
        $this->error('VALIDATION_ERROR', fn() => $this->approve($item));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
    }

    public function testPaymentCanHaveNoInvoiceAndMultipleNumbersAreJoined(): void
    {
        $args = $this->payment('supplier', ['invoice_numbers' => []]);
        $item = SaldiDocumentTools::proposePaymentRegistration($this->env->ctx(), $this->env->db, $args)['data'];
        self::assertArrayNotHasKey('invoice', $item['payload']['lines'][0]);
        $this->openItem('supplier', ['faktnr' => 'A', 'amount' => '-1000']);
        $this->openItem('supplier', ['faktnr' => 'B', 'amount' => '-250']);
        $args = $this->payment('supplier', ['invoice_numbers' => ['A', 'B']]);
        $item = SaldiDocumentTools::proposePaymentRegistration($this->env->ctx(), $this->env->db, $args)['data'];
        self::assertSame('A;B', $item['payload']['lines'][0]['invoice']);
        self::assertSame([], $item['card']['notices']);
    }

    public function testRealToolSuccessAndErrorEnvelopesValidateAgainstTheContract(): void
    {
        $poolId = $this->pool();
        $this->openItem();
        $calls = [
            'list_documents' => [],
            'get_document' => ['ref' => 'pool:' . $poolId],
            'propose_supplier_invoice' => [
                'supplier_account' => '991004', 'invoice_number' => 'INV-17', 'date' => '2025-06-15',
                'lines' => [['account' => '991001', 'description' => 'Software', 'amount' => '1250.00']],
                'request_id' => 'contract-invoice', 'explanation' => 'Invoice fixture',
            ],
            'propose_payment_registration' => $this->payment(),
        ];
        foreach ($calls as $name => $args) {
            $ok = $this->tool($name, $args);
            self::assertTrue($ok['ok'], json_encode($ok));
            self::assertFalse($ok['committed']);
            $error = $this->tool($name, ['unrecognised_field' => true]);
            self::assertFalse($error['ok']);
            self::assertSame('VALIDATION_ERROR', $error['error']['code']);
        }
    }
    public function testAttachedJournalAmountUsesItsForeignCurrency(): void
    {
        $db = $this->env->db;
        $db->insert('grupper', ['art' => 'VK', 'kodenr' => 977, 'box1' => 'EUR']);
        $db->insert('kladdeliste', ['id' => 10, 'bogfort' => '-']);
        $line = $db->insert('kassekladde', ['kladde_id' => 10, 'amount' => '125.500', 'valuta' => 977]);
        $document = $db->insert('documents', ['source' => 'kassekladde', 'source_id' => $line, 'filename' => 'eur.pdf']);
        $row = SaldiDocumentTools::getDocument($this->env->ctx(), $db, ['ref' => 'doc:' . $document])['data'];
        self::assertSame('125.50', $row['amount']);
        self::assertSame('EUR', $row['currency']);
        self::assertSame('EUR', SaldiDocumentTools::listDocuments($this->env->ctx(), $db, ['status' => 'attached'])['data']['rows'][0]['currency']);
    }

    public function testPaymentAllowsExactlyTwoHundredJoinedCharactersAndPreservesSingleInvoiceLimit(): void
    {
        $numbers = [];
        foreach (range(0, 9) as $index) {
            $number = str_repeat('A', $index === 0 ? 19 : 18) . $index;
            $numbers[] = $number;
            $this->openItem('supplier', ['faktnr' => $number, 'amount' => '-125']);
        }
        self::assertSame(200, strlen(implode(';', $numbers)));
        $result = $this->tool('propose_payment_registration', $this->payment('supplier', ['invoice_numbers' => $numbers]));
        self::assertTrue($result['ok']);
        $item = $this->approve((array)$result['data']);
        self::assertSame(implode(';', $numbers), $this->env->db->value('SELECT faktura FROM kassekladde WHERE id = ?', [$item['result']['line_ids'][0]]));
        $numbers[0] .= 'B';
        $error = $this->tool('propose_payment_registration', $this->payment('supplier', ['invoice_numbers' => $numbers]));
        self::assertSame('VALIDATION_ERROR', $error['error']['code']);
        $draft = $this->draft();
        $draft['lines'][0]['invoice'] = str_repeat('A', 31);
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $draft));
    }

}
