<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiPostingSuggesterTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Rules, history, duplicates, permissions, VAT and 50k-row latency regression tests.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiPostingTestCase.php';

final class SaldiPostingSuggesterTest extends SaldiPostingTestCase
{
    public function testNormaliseAndTokensKeepDanishAndRemoveDatesMoneyAndStopwords(): void
    {
        self::assertSame('æble øl blå adobe abonnement kr', SaldiPostingSuggester::normalise(
            'ÆBLE / ØL, BLÅ! Adobe 02-10-2026 abonnement 1.250,00 kr. 123'
        ));
        self::assertSame(['æble', 'blå', 'adobe', 'abonnement'], SaldiPostingSuggester::tokens(
            'Faktura NR 12 af ÆBLE og BLÅ til Adobe abonnement Adobe betaling and the inv'
        ));
        self::assertSame('', SaldiPostingSuggester::normalise('123 12.50 / --'));
        self::assertSame([], SaldiPostingSuggester::tokens('betaling nr og af til'));
    }

    public function testPartyRulePrecedesTextRuleThenHistoryWithDistinctPatterns(): void
    {
        $this->repeat(4);
        $partyRule = $this->rule(['account' => '3200', 'description' => 'Adobe fra godkendt regel']);
        $textRule = $this->rule([
            'match' => ['type' => 'text', 'value' => 'ADOBE abonnement 2026'], 'account' => '2900',
        ]);
        $this->env->db->insert('assist_events', [
            'created_at' => gmdate('Y-m-d H:i:s'), 'event' => 'suggestion_used',
            'subject' => 'rule:' . $partyRule['result']['rule_id'],
        ]);
        $result = $this->suggest(['party' => ['ledger' => 'supplier', 'account' => '1005']]);
        self::assertCount(3, $result['suggestions']);
        self::assertSame(['rule', 'rule', 'history'], array_column($result['suggestions'], 'source'));
        self::assertSame($partyRule['result']['rule_id'], $result['suggestions'][0]['rule_id']);
        self::assertSame($textRule['result']['rule_id'], $result['suggestions'][1]['rule_id']);
        self::assertSame(
            ['3200', '2900', '2800'],
            array_column(array_column($result['suggestions'], 'debit'), 'account')
        );
        self::assertSame(1, $result['suggestions'][0]['basis']['count']);
        self::assertSame('Adobe fra godkendt regel', $result['suggestions'][0]['description']);
        self::assertSame('supplier', $result['suggestions'][0]['credit']['ledger']);
        self::assertSame('K1', $result['suggestions'][2]['debit']['vat_code']);
    }

    public function testTextRuleUsesWholeWordsLongestFirstAndUnknownSideStaysNull(): void
    {
        $short = $this->rule(['match' => ['type' => 'text', 'value' => 'adobe'], 'account' => '2800']);
        $long = $this->rule(['match' => ['type' => 'text', 'value' => 'adobe abonnement'], 'account' => '2900']);
        $result = $this->suggest();
        self::assertSame(
            [$long['result']['rule_id'], $short['result']['rule_id']],
            array_column($result['suggestions'], 'rule_id')
        );
        self::assertNull($result['suggestions'][0]['credit']);
        self::assertSame([], $this->suggest(['description' => 'superadobe abonnement'])['suggestions']);
    }

    public function testTextRuleBorrowsDominantCounterSideAndDeduplicatesHistory(): void
    {
        $this->repeat(3);
        $this->repeat(2, ['k_type' => 'F', 'kredit' => '5500']);
        $rule = $this->rule(['match' => ['type' => 'text', 'value' => 'adobe'], 'account' => '2800']);
        $result = $this->suggest();
        self::assertCount(2, $result['suggestions']);
        self::assertSame($rule['result']['rule_id'], $result['suggestions'][0]['rule_id']);
        self::assertSame('1005', $result['suggestions'][0]['credit']['account']);
        self::assertSame('5500', $result['suggestions'][1]['credit']['account']);
    }

    public function testHistoryConfidenceThresholdsAndRecencyAreDeterministic(): void
    {
        $this->repeat(3);
        self::assertSame('high', $this->suggest()['suggestions'][0]['confidence']);
        $this->repeat(2, ['debet' => '2900']);
        self::assertSame(['medium', 'medium'], array_column($this->suggest()['suggestions'], 'confidence'));
        $this->line(['debet' => '3200']);
        self::assertSame('low', $this->suggest()['suggestions'][2]['confidence']);
        $this->env->db->exec('DELETE FROM kassekladde');
        $this->repeat(3, ['debet' => '2900', 'transdate' => $this->date(-300)]);
        $this->repeat(2);
        self::assertSame('2800', $this->suggest()['suggestions'][0]['debit']['account']);
    }

    public function testHistoryJaccardCutoffPartyFallbackAndTwoYearWindow(): void
    {
        $this->repeat(3, ['beskrivelse' => 'Adobe abonnement faktura 123']);
        $this->line(['beskrivelse' => 'Adobe abonnement papir', 'debet' => '2900']);
        $this->line(['beskrivelse' => 'Adobe irrelevant wording long unrelated', 'debet' => '3100']);
        $this->line(['transdate' => $this->date(-731), 'debet' => '3200']);
        self::assertCount(2, $this->suggest()['suggestions']);
        $this->repeat(4, ['kredit' => '1006', 'beskrivelse' => 'Kontor Nord papirleverance', 'debet' => '2900']);
        $fallback = $this->suggest([
            'description' => 'Ukendt tekst', 'party' => ['ledger' => 'supplier', 'account' => '1006'],
        ]);
        self::assertSame('2900', $fallback['suggestions'][0]['debit']['account']);
        self::assertSame(4, $fallback['suggestions'][0]['basis']['count']);
        self::assertSame('high', $fallback['suggestions'][0]['confidence']);
        self::assertSame([], $this->suggest(['description' => 'Kun betaling nr'])['suggestions']);
    }

    public function testCustomerRuleAndExplicitSupplierDebitReverseTheSides(): void
    {
        $this->rule(['match' => ['type' => 'customer', 'value' => '2005'], 'account' => '5500']);
        $customer = $this->suggest(['party' => ['ledger' => 'customer', 'account' => '2005']])['suggestions'][0];
        self::assertSame('customer', $customer['debit']['ledger']);
        self::assertSame('5500', $customer['credit']['account']);
        $this->rule(['account' => '5500', 'vat_code' => 'none']);
        $payment = $this->suggest([
            'party' => ['ledger' => 'supplier', 'account' => '1005', 'side' => 'debit'],
        ])['suggestions'][0];
        self::assertSame('supplier', $payment['debit']['ledger']);
        self::assertSame('5500', $payment['credit']['account']);
        self::assertTrue($payment['vat_free']);
    }

    public function testClosedWrongYearAndRevenueHiddenAccountsNeverLeakSuggestions(): void
    {
        $this->rule();
        $this->repeat(3);
        $this->repeat(2, ['debet' => '5600']);
        $this->line(['debet' => '5999']);
        $this->line(['debet' => '5900']);
        $this->line(['debet' => '6000']);
        $this->line(['k_type' => 'K', 'kredit' => '9999']);
        self::assertCount(2, $this->suggest(['party' => ['ledger' => 'supplier', 'account' => '1005']])['suggestions']);
        $this->setting('finans', 'hideRevenue', 'on');
        $hidden = $this->suggest(
            ['party' => ['ledger' => 'supplier', 'account' => '1005']],
            $this->restrictedContext()
        );
        self::assertCount(1, $hidden['suggestions']);
        self::assertSame('5600', $hidden['suggestions'][0]['debit']['account']);
    }

    public function testStoredSideVatOverridesAndVatFreeAreSeparatePatterns(): void
    {
        foreach (['debetvat', 'kreditvat'] as $column) {
            if (!$this->env->db->hasColumn('kassekladde', $column)) {
                $this->env->db->exec('ALTER TABLE kassekladde ADD COLUMN ' . $column . ' varchar(8)');
            }
        }
        $database = SaldiDb::connect(saldi_runtime_config(), $this->env->name);
        $previous = $this->env->db;
        $this->env->db = $database;
        try {
            $this->repeat(3, ['debetvat' => 'Y1']);
            $this->repeat(2, ['debetvat' => 'E1', 'k_type' => 'F', 'kredit' => '5500', 'kreditvat' => 'S1']);
            $this->line(['momsfri' => 'on', 'debetvat' => '']);
            $result = $this->suggest()['suggestions'];
            self::assertSame(['Y1', 'E1', 'K1'], array_column(array_column($result, 'debit'), 'vat_code'));
            self::assertSame('S1', $result[1]['credit']['vat_code']);
            self::assertTrue($result[2]['vat_free']);
        } finally {
            $this->env->db = $previous;
            $database->close();
        }
    }

    public function testDuplicatesSamePartyAmountOrInvoiceExcludeCurrentLineAndBoundDates(): void
    {
        $first = $this->line(['faktura' => 'Same-Invoice', 'amount' => '50.000']);
        $second = $this->line(['kladde_id' => 42, 'amount' => '1250.004']);
        $excluded = $this->line(['amount' => '1250.000']);
        $this->line(['kredit' => '1006', 'amount' => '1250.000', 'faktura' => 'Same-Invoice']);
        $this->line(['amount' => '1250.000', 'transdate' => $this->date(-61)]);
        $result = $this->suggest([
            'party' => ['ledger' => 'supplier', 'account' => '1005'], 'amount' => '1250.00',
            'invoice' => 'same-invoice', 'exclude_line_id' => $excluded,
        ]);
        self::assertSame([$second, $first], array_column($result['duplicates'], 'line_id'));
        self::assertFalse($result['duplicates'][0]['posted']);
        self::assertTrue($result['duplicates'][1]['posted']);
        self::assertSame('1250.00', $result['duplicates'][0]['amount']);
        self::assertSame('finans/kassekladde.php?kladde_id=42', $result['duplicates'][0]['screen']);
        $this->repeat(8);
        self::assertCount(5, $this->suggest([
            'party' => ['ledger' => 'supplier', 'account' => '1005'], 'amount' => '1250.00',
        ])['duplicates']);
    }

    public function testPermissionsAndMalformedInputsFailClosed(): void
    {
        $rights = str_repeat('0', 16);
        $rights[2] = '2';
        $this->error('PERMISSION_DENIED', fn() => $this->suggest([], $this->env->ctx(910001, $rights)));
        $rights[2] = '0';
        $rights[3] = '2';
        $this->error('PERMISSION_DENIED', fn() => $this->suggest([], $this->env->ctx(910001, $rights)));
        foreach (
            [['description' => ''], ['amount' => 12.5], ['amount' => '12,50'],
            ['date' => '2026-02-30'], ['exclude_line_id' => -1],
            ['party' => ['ledger' => 'account', 'account' => '2800']],
            ['party' => ['ledger' => 'supplier', 'account' => '9999']]] as $input
        ) {
            $this->error('VALIDATION_ERROR', fn() => $this->suggest($input));
        }
    }

    public function testRevenueHiddenDuplicatesAndBlankInvoiceDoNotDiscloseHistory(): void
    {
        $this->line(['faktura' => '']);
        $balance = $this->line(['debet' => '5600', 'faktura' => '']);
        $this->setting('finans', 'hideRevenue', 'on');
        $result = $this->suggest([
            'party' => ['ledger' => 'supplier', 'account' => '1005'], 'amount' => '1250.00',
        ], $this->restrictedContext());
        self::assertSame([$balance], array_column($result['duplicates'], 'line_id'));
        self::assertSame([], $this->suggest([
            'party' => ['ledger' => 'supplier', 'account' => '1005'], 'invoice' => '   ',
        ])['duplicates']);
    }

    public function testLatencyOnFiftyThousandSyntheticJournalLines(): void
    {
        $this->env->db->exec(
            "INSERT INTO kassekladde (id, kladde_id, bilag, transdate, beskrivelse, d_type, debet, " .
            "k_type, kredit, amount, faktura, momsfri) " .
            "SELECT 200000 + sequence, 41, sequence, CAST(? AS date) - (sequence % 700), " .
            "CASE sequence % 5 WHEN 0 THEN 'Adobe abonnement' WHEN 1 THEN 'Kontor Nord papir' " .
            "WHEN 2 THEN 'Strøm Øst elregning' WHEN 3 THEN 'Dansk Tele abonnement' ELSE 'Bank gebyr' END, " .
            "'F', CASE sequence % 5 WHEN 0 THEN 2800 WHEN 1 THEN 2900 WHEN 2 THEN 3100 ELSE 3200 END, " .
            "CASE WHEN sequence % 5 = 4 THEN 'F' ELSE 'K' END, " .
            "CASE sequence % 5 WHEN 0 THEN 1005 WHEN 1 THEN 1006 WHEN 2 THEN 1007 WHEN 3 THEN 1008 ELSE 5500 END, " .
            "1250.000, 'SYN-' || sequence, '' FROM generate_series(1, 50000) sequence",
            [$this->date()]
        );
        self::assertSame(50000, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
        $samples = [];
        for ($index = 0; $index < 5; $index++) {
            $start = hrtime(true);
            $result = $this->suggest([
                'party' => ['ledger' => 'supplier', 'account' => '1005'], 'amount' => '1250.00',
            ]);
            $samples[] = (hrtime(true) - $start) / 1000000;
            self::assertSame('2800', $result['suggestions'][0]['debit']['account']);
        }
        fwrite(STDERR, "\nPosting suggest() 50k synthetic rows ms: " . implode(', ', array_map(
            static fn(float $sample): string => number_format($sample, 2, '.', ''),
            $samples
        )) . "\n");
        self::assertLessThan(150.0, max($samples));
    }
}
