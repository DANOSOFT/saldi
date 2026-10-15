<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiBankMatcherTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// Disposable-tenant bank staging, matching, approval, per-voucher dates and undo coverage.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Add regression coverage for companion review corrections.
// 20261007 CL/LH Batch claim pass (claimBatch) coverage.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiBankTestCase.php';

final class SaldiBankMatcherTest extends SaldiBankTestCase
{
    public function testInvoiceMatchesBothDirectionsAggregatedOpenAmountAndBoundaries(): void
    {
        $this->open('501', '1000.00');
        $this->open('501', '234.56');
        $this->open('SUP-44', '-25.00', 1005);
        self::assertSame(['party' => ['ledger' => 'customer', 'account' => '2005', 'name' => 'Kunde Æble'], 'account' => null, 'invoice' => '501', 'confidence' => 'high'], $this->match($this->row()));
        self::assertSame('medium', $this->match($this->row('100.00'))['confidence']);
        self::assertSame('supplier', $this->match($this->row('-25.00', 'Faktura SUP-44'))['party']['ledger']);
        self::assertNull($this->match($this->row('1234.56', 'Faktura 5010')));
        self::assertNull($this->match($this->row('-1234.56')));
        self::assertNull($this->match($this->row('0.00')));
    }

    public function testOneOpenItemIsClaimedOncePerBatch(): void
    {
        $this->open('637', '240.00');
        $matcher = new SaldiBankMatcher($this->env->ctx(), $this->env->db);
        $first = $matcher->match($this->row('240.00', 'Faktura 637', '2025-06-05') + ['bank_account' => '5500']);
        $second = $matcher->match($this->row('240.00', 'Faktura 637', '2025-06-08') + ['bank_account' => '5500']);
        // match() is pure; the claim is a batch pass, so cached and fresh matches agree.
        self::assertSame('high', $second['confidence']);
        $claimed = SaldiBankMatcher::claimBatch([['status' => 'new', 'match' => $first], ['status' => 'new', 'match' => $second]]);
        self::assertSame(['high', 'medium'], array_column($claimed, 'confidence'));
        self::assertSame('637', $claimed[1]['invoice']);
        // A proposed line holds the invoice even when it comes later in the file.
        $held = SaldiBankMatcher::claimBatch([['status' => 'new', 'match' => $first], ['status' => 'proposed', 'match' => $second]]);
        self::assertSame(['medium', 'high'], array_column($held, 'confidence'));
        // Other invoices, unmatched rows and party-only matches are untouched.
        $other = ['invoice' => '638'] + $first;
        $partyOnly = ['invoice' => null] + $first;
        self::assertSame([$first, null, $other, $partyOnly, $partyOnly], SaldiBankMatcher::claimBatch([
            ['status' => 'new', 'match' => $first], ['status' => 'new', 'match' => null], ['status' => 'new', 'match' => $other],
            ['status' => 'new', 'match' => $partyOnly], ['status' => 'new', 'match' => $partyOnly],
        ]));
    }

    public function testAmountToleranceUsesMilliUnits(): void
    {
        $this->open('TOL', '10.005');
        self::assertSame('high', $this->match($this->row('10.00', 'TOL'))['confidence']);
        self::assertSame('medium', $this->match($this->row('9.999', 'TOL'))['confidence']);
    }

    public function testPaymentIdsAndLegacyFikIk71NordeaReferences(): void
    {
        $this->open();
        $this->order('501', 'ha_12345');
        $references = [
            'Betaling ha_12345', '+71<000002005005017+12345678<',
            'IK71 0000000002005005017', '000002005005017 IK71',
            'Indbetalingskort, nr. 0002005005017',
        ];
        foreach ($references as $text) {
            $match = $this->match($this->row('1234.56', $text));
            self::assertNotNull($match, $text);
            self::assertSame('501', $match['invoice'], $text);
            self::assertSame('high', $match['confidence'], $text);
        }
        $this->open('602', '51.00');
        $this->order('602', '123456789012345');
        self::assertSame('602', $this->match($this->row('51.00', '+71<123456789012345+12345678<'))['invoice']);
        self::assertSame('501', $this->match($this->row('1234.56', 'Overførsel', '2025-06-04', ['reference' => 'ha_12345']))['invoice']);
    }

    public function testNamedExactAmountNeedsExactlyOnePartyAndFourCharacterName(): void
    {
        $this->open('501', '1234.56');
        self::assertSame('high', $this->match($this->row('1234.56', 'Betaling fra KUNDE ÆBLE'))['confidence']);
        $this->open('502', '1234.56', 2006);
        self::assertNull($this->match($this->row('1234.56', 'Kunde Æble og Kunde Øst')));
        $this->party(2007, 'ABC', 'D');
        $this->open('503', '1234.56', 2007);
        self::assertNull($this->match($this->row('1234.56', 'Betaling ABC')));
    }

    public function testAmbiguousInvoiceAndClosedSettledOrForeignItemsAreExcluded(): void
    {
        $this->open();
        $this->open('501', '1234.56', 2006);
        self::assertNull($this->match($this->row()));
        $db = $this->env->db;
        $db->exec('DELETE FROM openpost');
        $this->open('CLOSED', '10.00');
        $db->exec("UPDATE adresser SET lukket = 'on' WHERE id = 2005");
        self::assertNull($this->match($this->row('10.00', 'CLOSED')));
        $this->open('SETTLED', '10.00', 2006, ['udlignet' => '1']);
        $this->open('FOREIGN', '10.00', 2006, ['valuta' => 'EUR']);
        self::assertNull($this->match($this->row('10.00', 'SETTLED')));
        self::assertNull($this->match($this->row('10.00', 'FOREIGN')));
        self::assertNull($this->match($this->row('10.00', 'FOREIGN', '2025-06-04', ['currency' => 'EUR'])));
        self::assertNull($this->match($this->row('10.00', 'ANY', '2025-06-04', ['status' => 'duplicate'])));
    }

    public function testPostingRulePrecedesHistoryAndHistoryConfidenceIsPreserved(): void
    {
        $this->repeat(3, ['beskrivelse' => 'Bankgebyr', 'transdate' => '2025-05-01', 'debet' => '2900', 'k_type' => 'F', 'kredit' => '5500']);
        $this->rule(['match' => ['type' => 'text', 'value' => 'bankgebyr'], 'account' => '2800']);
        $match = $this->match($this->row('-25.00', 'Bankgebyr'));
        self::assertSame(['account' => '2800', 'name' => 'Software'], $match['account']);
        self::assertSame('high', $match['confidence']);
        $this->env->db->exec('DELETE FROM posting_rules');
        self::assertSame('2900', $this->match($this->row('-25.00', 'Bankgebyr'))['account']['account']);
        $this->env->db->exec('DELETE FROM kassekladde');
        $this->repeat(2, ['beskrivelse' => 'Bankgebyr', 'transdate' => '2025-05-01', 'debet' => '2900', 'k_type' => 'F', 'kredit' => '5500']);
        self::assertSame('medium', $this->match($this->row('-25.00', 'Bankgebyr'))['confidence']);
        $this->env->db->exec('DELETE FROM kassekladde');
        $this->repeat(1, ['beskrivelse' => 'Bankgebyr', 'transdate' => '2025-05-01', 'debet' => '2900', 'k_type' => 'F', 'kredit' => '5500']);
        self::assertNull($this->match($this->row('-25.00', 'Bankgebyr')));
    }

    public function testFikCardTypeIsNotAnInvoiceNumber(): void
    {
        $this->open('71', '1234.56');
        self::assertNull($this->match($this->row('1234.56', '+71<999999999999999+12345678<')));
    }
    public function testKrakContainmentWithEightHistoryRowsSuggests5900(): void
    {
        $db = $this->env->db;
        $db->exec("UPDATE kontoplan SET kontotype='D', beskrivelse='Abonnement', moms='' WHERE kontonr=5900");
        $db->insert('kontoplan', ['kontonr' => 58000, 'regnskabsaar' => 4, 'kontotype' => 'S', 'beskrivelse' => 'Bank']);
        $this->repeat(8, ['beskrivelse' => 'Krak', 'transdate' => SaldiToolRuntime::today(), 'debet' => '5900', 'k_type' => 'F', 'kredit' => '58000']);
        foreach (['Krak abonnement', 'Krak abonnement maj dk reference'] as $text) {
            $match = (new SaldiBankMatcher($this->env->ctx(), $db))->match($this->row('-25.00', $text) + ['bank_account' => '58000']);
            self::assertSame('5900', $match['account']['account']);
            self::assertSame('high', $match['confidence']);
        }
    }

    public function testInvoiceRegistrationHistoryCannotBecomeBankCounterAccount(): void
    {
        $this->repeat(8, ['beskrivelse' => 'Adobe', 'transdate' => SaldiToolRuntime::today(), 'debet' => '2800', 'k_type' => 'K', 'kredit' => '1005']);
        self::assertNull($this->match($this->row('-25.00', 'Adobe abonnement')));
        $this->env->db->exec('DELETE FROM kassekladde');
        $this->repeat(8, ['beskrivelse' => 'Kunde Æble', 'transdate' => SaldiToolRuntime::today(), 'd_type' => 'D', 'debet' => '2005', 'k_type' => 'F', 'kredit' => '2800']);
        self::assertNull($this->match($this->row('25.00', 'Kunde Æble')));
    }

    public function testWrongSignedCustomerAndSupplierItemsNeverMatch(): void
    {
        $this->open('CREDIT', '-100.00');
        $this->open('PREPAY', '100.00', 1005);
        foreach (['CREDIT', 'Kunde Æble'] as $text) {
            self::assertNull($this->match($this->row('100.00', $text)));
        }
        foreach (['PREPAY', 'Adobe'] as $text) {
            self::assertNull($this->match($this->row('-100.00', $text)));
        }
    }

    public function testHistoryVatAndRuleExemptionReachDraftAndCard(): void
    {
        $db = $this->env->db;
        foreach (['debetvat', 'kreditvat'] as $column) {
            if (!$db->hasColumn('kassekladde', $column)) {
                $db->exec('ALTER TABLE kassekladde ADD COLUMN ' . $column . ' varchar(8)');
            }
        }
        $this->repeat(3, ['beskrivelse' => 'Forsikring', 'transdate' => SaldiToolRuntime::today(), 'debet' => '2800', 'k_type' => 'F', 'kredit' => '5500', 'debetvat' => 'S1']);
        $batch = $this->stage([$this->row('-25.00', 'Forsikring')]);
        $item = $this->propose($batch['batch_id']);
        self::assertSame('S1', $item['payload']['lines'][0]['debit']['vat_code']);
        self::assertStringContainsString('Moms: S1', json_encode($item['card'], JSON_UNESCAPED_UNICODE));
        $applied = $this->approve($item);
        self::assertSame('S1', $db->value('SELECT debetvat FROM kassekladde WHERE id=?', [$applied['result']['line_ids'][0]]));
        $this->rule(['match' => ['type' => 'text', 'value' => 'gebyrfri'], 'account' => '2800', 'vat_code' => 'none']);
        $batch = $this->stage([$this->row('-35.00', 'Gebyrfri')]);
        $item = $this->propose($batch['batch_id'], 'vat-free');
        self::assertTrue($item['payload']['lines'][0]['vat_free']);
        self::assertStringContainsString('Momsfri', json_encode($item['card'], JSON_UNESCAPED_UNICODE));
        $applied = $this->approve($item);
        self::assertSame('on', $db->value('SELECT momsfri FROM kassekladde WHERE id=?', [$applied['result']['line_ids'][0]]));
    }

}
