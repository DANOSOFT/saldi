<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiJournalCheckTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// Posting-check parity, exact rates, warning rules, read-only and endpoint guards.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Add regression coverage for companion review corrections.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiPostingTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';
require_once __DIR__ . '/../../../includes/tools/SaldiJournalCheckTools.php';
if (!defined('SALDI_JOURNAL_SUGGEST_LIBRARY')) {
    define('SALDI_JOURNAL_SUGGEST_LIBRARY', true);
}
require_once __DIR__ . '/../../../finans/kassekladde_includes/assistSuggest.php';

final class SaldiJournalCheckTest extends SaldiPostingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->env->db->exec(
            "UPDATE grupper SET box1 = '01', box2 = '2026', box3 = '12', box4 = '2026', box5 = 'on'"
            . " WHERE art = 'RA' AND kodenr = 4"
        );
        $this->env->db->exec(
            "UPDATE grupper SET kode = SUBSTRING(art, 1, 1)"
            . " WHERE art IN ('KM','SM','EM','YM') AND fiscal_year = 4"
        );
        foreach (['debetvat', 'kreditvat'] as $column) {
            if (!$this->env->db->hasColumn('kassekladde', $column)) {
                $this->env->db->exec('ALTER TABLE kassekladde ADD COLUMN ' . $column . ' varchar(8)');
            }
        }
        $this->env->db->exec('DELETE FROM openpost');
        $this->env->db->exec('DELETE FROM transaktioner');
    }

    private function row(array $values = []): int
    {
        return $this->line($values + ['kladde_id' => 42, 'transdate' => '2026-06-10']);
    }

    private function check(): array
    {
        return SaldiJournalCheck::run($this->env->ctx(), $this->env->db, 42);
    }

    private function codes(array $result, string $severity): array
    {
        return array_column($result[$severity], 'code');
    }

    public function testBalancedVoucherReadyAndDatabaseUnchanged(): void
    {
        $first = $this->row(['bilag' => 8, 'kredit' => 0, 'k_type' => 'F', 'amount' => '100.000']);
        $this->row(['bilag' => 8, 'debet' => 0, 'kredit' => 5500, 'k_type' => 'F', 'amount' => '100.000']);
        $db = $this->env->db;
        $db->insert('documents', ['source' => 'kassekladde', 'source_id' => $first]);
        $tables = [
            'kassekladde', 'kladdeliste', 'kontoplan', 'grupper', 'openpost',
            'transaktioner', 'documents', 'decision_log',
        ];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = $db->all('SELECT * FROM ' . $table . ' ORDER BY id');
        }
        $result = $this->check();
        self::assertTrue($result['ready']);
        self::assertSame('open', $result['status']);
        self::assertSame([], $result['errors']);
        self::assertSame(['one_sided', 'one_sided'], $this->codes($result, 'warnings'));
        foreach ($tables as $table) {
            self::assertSame($before[$table], $db->all('SELECT * FROM ' . $table . ' ORDER BY id'), $table);
        }
    }

    public function testVoucherBalanceIsPerVoucherWithOneCentThreshold(): void
    {
        $first = $this->row(['bilag' => 1, 'kredit' => 0, 'amount' => '0.010']);
        $second = $this->row(['bilag' => 2, 'debet' => 0, 'amount' => '0.010']);
        $result = $this->check();
        self::assertTrue($result['ready']);
        self::assertSame([], $result['errors']);
        $result['errors'] = array_values(array_filter($result['warnings'], fn(array $w): bool => $w['code'] === 'unbalanced'));
        self::assertSame(['unbalanced', 'unbalanced'], array_column($result['errors'], 'code'));
        self::assertSame([$first], $result['errors'][0]['line_ids']);
        self::assertSame([$second], $result['errors'][1]['line_ids']);
        self::assertSame('0.01', $result['errors'][0]['detail']['difference']);
        self::assertSame('-0.01', $result['errors'][1]['detail']['difference']);
        $this->env->db->exec("UPDATE kassekladde SET amount = '0.009'");
        self::assertTrue($this->check()['ready']);
    }

    public function testPostedJournalAndFiscalYearClosed(): void
    {
        $this->row();
        $ctx = $this->env->ctx();
        $this->env->db->exec("UPDATE kladdeliste SET bogfort = 'V' WHERE id = 42");
        $this->env->db->exec("UPDATE grupper SET box5 = '' WHERE art = 'RA' AND kodenr = 4");
        $result = SaldiJournalCheck::run($ctx, $this->env->db, 42);
        self::assertSame('closed', $result['status']);
        self::assertSame(['not_open', 'date_outside_year'], $this->codes($result, 'errors'));
    }

    public function testFiscalYearEndpointsAndClosedVatMonth(): void
    {
        foreach (['2025-12-31', '2026-01-01', '2026-12-31', '2027-01-01'] as $date) {
            $this->row(['transdate' => $date]);
        }
        $db = $this->env->db;
        if (!$db->hasTable('moms_periode_luk')) {
            $db->exec('CREATE TABLE moms_periode_luk (id serial PRIMARY KEY,'
                . ' kalender_aar integer, kalender_maaned integer, status text)');
        }
        $db->insert('moms_periode_luk', ['kalender_aar' => 2026, 'kalender_maaned' => 12, 'status' => 'closed']);
        $errors = $this->check()['errors'];
        self::assertSame(
            ['date_outside_year', 'vat_period_closed', 'date_outside_year'],
            array_column($errors, 'code')
        );
    }

    public function testInvalidFinanceAndPartyAccountsAndAmountWithoutAccount(): void
    {
        foreach ([9998, 5999, 5900, 6000] as $account) {
            $this->row(['debet' => $account]);
        }
        $this->env->db->insert('kontoplan', ['id' => 5998, 'kontonr' => 5998, 'regnskabsaar' => 4, 'kontotype' => 'Z']);
        $this->row(['debet' => 5998]);
        $this->row(['kredit' => 8888]);
        $this->env->db->exec("UPDATE adresser SET lukket = 'on' WHERE kontonr IN ('1005','2005')");
        $this->row(['debet' => 2005, 'd_type' => 'D', 'kredit' => 5500, 'k_type' => 'F']);
        $this->row(['debet' => 0, 'kredit' => 0]);
        $this->row(['d_type' => 'X', 'k_type' => 'F', 'debet' => 2800, 'kredit' => 5500]);
        $codes = $this->codes($this->check(), 'errors');
        self::assertContains('no_account', $codes);
        SaldiSchemaValidator::validate(
            $this->check(), SaldiToolRegistry::default()->get('check_journal')->dataSchema
        );
        self::assertGreaterThanOrEqual(8, count(array_filter($codes, fn($code) => $code === 'account_unavailable')));
    }

    public function testStoredCurrencyRateAndMissingHistoricRate(): void
    {
        $db = $this->env->db;
        $db->exec("DELETE FROM grupper WHERE art = 'VK' AND kodenr = 79");
        $db->insert('grupper', ['art' => 'VK', 'kodenr' => 79, 'box1' => 'EUR']);
        $db->exec('DELETE FROM valuta WHERE gruppe = 79');
        $db->insert('valuta', ['gruppe' => 79, 'valdate' => '2026-01-01', 'kurs' => '746.000']);
        $this->row([
            'bilag' => 9, 'debet' => 2800, 'kredit' => 0, 'amount' => '1.000',
            'valuta' => 79, 'valutakurs' => '750.000',
        ]);
        $this->row(['bilag' => 9, 'debet' => 0, 'kredit' => 5500, 'k_type' => 'F', 'amount' => '7.500', 'valuta' => 0]);
        self::assertFalse($this->check()['ready']);
        self::assertSame('-0.04', $this->check()['errors'][0]['detail']['difference']);
        $db->exec('UPDATE kassekladde SET valutakurs = NULL WHERE valuta = 79');
        self::assertSame('-0.04', $this->check()['errors'][0]['detail']['difference']);
        $db->exec('DELETE FROM valuta WHERE gruppe = 79');
        self::assertContains('rate_unavailable', $this->codes($this->check(), 'warnings'));
    }

    public function testVatUnknownAccountAndActiveRuleMismatch(): void
    {
        $this->rule(['vat_code' => 'S1']);
        $this->row(['debetvat' => 'Q88']);
        $codes = $this->codes($this->check(), 'warnings');
        self::assertContains('vat_unknown', $codes);
        self::assertContains('vat_account_mismatch', $codes);
        self::assertContains('vat_rule_mismatch', $codes);
        $this->env->db->exec("UPDATE kassekladde SET debetvat = 'S1'");
        self::assertNotContains('vat_rule_mismatch', $this->codes($this->check(), 'warnings'));
        $this->env->db->exec("UPDATE posting_rules SET account = '2900'");
        $this->env->db->exec("UPDATE kassekladde SET debetvat = 'K1'");
        self::assertContains('vat_rule_mismatch', $this->codes($this->check(), 'warnings'));
        $this->env->db->exec("UPDATE posting_rules SET status = 'retired'");
        $this->env->db->exec("UPDATE kassekladde SET momsfri = 'on', debetvat = 'Q88'");
        self::assertNotContains('vat_unknown', $this->codes($this->check(), 'warnings'));
        self::assertNotContains('vat_rule_mismatch', $this->codes($this->check(), 'warnings'));
    }

    public function testOutlierAndDuplicateByInvoiceOrAmountWithinSixtyDays(): void
    {
        $this->line(['transdate' => '2026-06-01', 'amount' => '200.000', 'faktura' => 'SAME']);
        $this->line(['transdate' => '2026-05-31', 'amount' => '100.000']);
        $this->line(['transdate' => '2026-05-30', 'amount' => '300.000']);
        $this->row(['amount' => '1000.010', 'faktura' => 'SAME']);
        $result = $this->check();
        self::assertContains('amount_outlier', $this->codes($result, 'warnings'));
        self::assertContains('possible_duplicate', $this->codes($result, 'warnings'));
        $outlier = array_values(array_filter($result['warnings'], fn($w) => $w['code'] === 'amount_outlier'))[0];
        self::assertSame('200.00', $outlier['detail']['median']);
        $this->env->db->exec("UPDATE kassekladde SET amount = '1000.000' WHERE kladde_id = 42");
        self::assertNotContains('amount_outlier', $this->codes($this->check(), 'warnings'));
        $this->env->db->exec("UPDATE kassekladde SET faktura = 'OTHER', amount = '200.000' WHERE kladde_id = 42");
        self::assertContains('possible_duplicate', $this->codes($this->check(), 'warnings'));
        $this->env->db->exec("UPDATE kassekladde SET transdate = '2026-01-01' WHERE kladde_id = 41");
        self::assertNotContains('possible_duplicate', $this->codes($this->check(), 'warnings'));
    }

    public function testDocumentWarningIsOncePerVoucherAndLegacyLinkCounts(): void
    {
        $this->row(['bilag' => 1]);
        $this->row(['bilag' => 1]);
        $this->row(['bilag' => 2, 'dokument' => 'legacy.pdf']);
        $warnings = array_values(array_filter($this->check()['warnings'], fn($w) => $w['code'] === 'missing_document'));
        self::assertCount(1, $warnings);
        self::assertCount(2, $warnings[0]['line_ids']);
        self::assertSame(1, $warnings[0]['bilag']);
    }

    public function testToolResultMatchesContractAndRequiresOnlyJournalRead(): void
    {
        $this->row();
        $ctx = $this->env->ctx(910001, '001' . str_repeat('0', 13));
        $result = SaldiJournalCheckTools::check($ctx, $this->env->db, ['journal_id' => 42]);
        self::assertSame('saldi:journal:42', $result['citations'][0]['id']);
        SaldiSchemaValidator::validate($result['data'], SaldiToolRegistry::default()->get('check_journal')->dataSchema);
        $denied = $this->env->ctx(910001, str_repeat('0', 16));
        $this->error('PERMISSION_DENIED', fn() => SaldiJournalCheck::run($denied, $this->env->db, 42));
        $this->error('NOT_FOUND', fn() => SaldiJournalCheck::run($ctx, $this->env->db, 999999));
    }

    public function testHiddenRevenueDoesNotExposeDifferenceOrMedian(): void
    {
        $this->line(['amount' => '100.000', 'transdate' => '2026-06-01']);
        $this->row(['kredit' => 0, 'amount' => '10000.000']);
        $this->setting('finans', 'hideRevenue', 'on');
        $result = SaldiJournalCheckTools::check(
            $this->restrictedContext(),
            $this->env->db,
            ['journal_id' => 42]
        );
        self::assertNotContains('amount_outlier', $this->codes($result['data'], 'warnings'));
        self::assertEquals(new stdClass(), $result['data']['errors'][0]['detail']);
        self::assertStringNotContainsString('10000', $result['data']['errors'][0]['message_da']);
        SaldiSchemaValidator::validate(
            $result['data'],
            SaldiToolRegistry::default()->get('check_journal')->dataSchema
        );
    }

    public function testEndpointCheckUsesExistingRequestGuardsAndWorksWithSuggestionsOff(): void
    {
        $this->row();
        $csrf = bin2hex(random_bytes(32));
        $_SESSION = ['inbox_csrf' => $csrf];
        $server = [
            'REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'saldi.test', 'HTTP_ORIGIN' => 'http://saldi.test',
            'HTTP_X_SALDI_CSRF' => $csrf, 'CONTENT_TYPE' => 'application/json',
        ];
        $open = fn() => ['ctx' => $this->env->ctx(), 'db' => $this->env->db];
        $input = json_encode(['op' => 'check', 'kladde_id' => 42], JSON_THROW_ON_ERROR);
        $result = saldi_journal_suggest_handle($server, $input, 910001, $open);
        self::assertSame(200, $result['status']);
        self::assertEquals($this->check(), array_diff_key($result['body'], ['ok' => true]));
        foreach (['HTTP_ORIGIN', 'HTTP_X_SALDI_CSRF'] as $field) {
            $bad = $server;
            $bad[$field] = '';
            $response = saldi_journal_suggest_handle(
                $bad,
                $input,
                910001,
                fn() => self::fail('Session must not open')
            );
            self::assertSame(403, $response['status']);
        }
        self::assertSame(401, saldi_journal_suggest_handle($server, $input, 910002, $open)['status']);
        $invalid = json_encode(['op' => 'check', 'kladde_id' => '42']);
        self::assertSame(400, saldi_journal_suggest_handle($server, $invalid, 910001, $open)['status']);
        $_SESSION = [];
    }
    public function testJournalTotalBlocksPostingAndForeignRoundingAllowanceMatchesLegacy(): void
    {
        $this->row(['bilag' => 1, 'debet' => 2800, 'kredit' => 0, 'amount' => '100.00']);
        self::assertSame(['journal_unbalanced'], $this->codes($this->check(), 'errors'));
        $db = $this->env->db;
        $db->insert('grupper', ['art' => 'VK', 'kodenr' => 99, 'box1' => 'EUR']);
        $db->insert('valuta', ['gruppe' => 99, 'valdate' => '2026-01-01', 'kurs' => '100']);
        $db->exec('DELETE FROM kassekladde');
        $this->row(['bilag' => 1, 'debet' => 2800, 'kredit' => 0, 'amount' => '1.00', 'valuta' => 99]);
        $this->row(['bilag' => 2, 'debet' => 0, 'kredit' => 5500, 'k_type' => 'F', 'amount' => '0.99', 'valuta' => 99]);
        self::assertTrue($this->check()['ready']); // 0.01 is below 2 foreign rows / 100.
        $db->exec("UPDATE kassekladde SET amount='0.98' WHERE kredit=5500");
        self::assertFalse($this->check()['ready']); // Equality does not qualify for the allowance.
    }

    public function testBlankVatInheritsAccountUnlessOppositeSideIsExplicit(): void
    {
        $db = $this->env->db;
        $this->row(['debetvat' => '', 'kreditvat' => '', 'd_type' => 'F', 'debet' => 2800,
            'k_type' => 'F', 'kredit' => 5500]);
        self::assertNotContains('vat_account_mismatch', $this->codes($this->check(), 'warnings'));
        $db->exec("UPDATE kontoplan SET moms='Q88' WHERE kontonr=2800");
        self::assertContains('vat_unknown', $this->codes($this->check(), 'warnings'));
        $db->exec("UPDATE kontoplan SET moms='K1' WHERE kontonr=2800");
        $db->exec("UPDATE kassekladde SET kreditvat='S1'");
        $mismatches = array_values(array_filter($this->check()['warnings'], fn(array $w): bool => $w['code'] === 'vat_account_mismatch'));
        self::assertSame('', $mismatches[0]['detail']['vat_code']);
    }

    public function testInvoiceAndPaymentOppositeSidesAreNotDuplicatesAndPairsAppearOnce(): void
    {
        $this->row(['d_type' => 'F', 'debet' => 2800, 'k_type' => 'K', 'kredit' => 1005, 'amount' => '100', 'faktura' => 'INV-7']);
        $this->row(['d_type' => 'K', 'debet' => 1005, 'k_type' => 'F', 'kredit' => 5500, 'amount' => '100', 'faktura' => 'INV-7']);
        self::assertNotContains('possible_duplicate', $this->codes($this->check(), 'warnings'));
        $this->row(['d_type' => 'K', 'debet' => 1005, 'k_type' => 'F', 'kredit' => 5500, 'amount' => '100', 'faktura' => 'INV-7']);
        self::assertCount(1, array_filter($this->check()['warnings'], fn(array $w): bool => $w['code'] === 'possible_duplicate'));
    }

}
