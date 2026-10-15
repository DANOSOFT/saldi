<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiInsightToolsTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// Database-backed insight contracts, historic cutoffs, till isolation and read-only checks.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Add regression coverage for companion review corrections.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiInsightDbTestCase.php';
require_once __DIR__ . '/../../../includes/mcp/SaldiMcpServer.php';

final class SaldiInsightToolsTest extends SaldiInsightDbTestCase
{
    private const PERIODS = ['a_from' => '2026-01-01', 'a_to' => '2026-03-31',
        'b_from' => '2026-04-01', 'b_to' => '2026-06-30'];

    public function testGroupsSignsPrimoExclusionSortingAndPagination(): void
    {
        $result = SaldiInsightTools::comparePeriods($this->env->ctx(), $this->env->db, self::PERIODS);
        $this->validateData('compare_periods', $result);
        self::assertSame(['2999', '1999'], array_column($result['data']['rows'], 'key'));
        self::assertSame('-100.00', $result['data']['rows'][0]['a']);
        self::assertSame('-175.00', $result['data']['rows'][0]['b']);
        self::assertSame('-75.00', $result['data']['rows'][0]['change']);
        self::assertSame('-75.000', $result['data']['rows'][0]['change_pct']);
        self::assertSame('10.01', $result['data']['rows'][1]['a']);
        self::assertSame('25.01', $result['data']['rows'][1]['b']);
        $limited = SaldiInsightTools::comparePeriods(
            $this->env->ctx(),
            $this->env->db,
            self::PERIODS + ['limit' => 1]
        );
        self::assertSame(2, $limited['data']['total']);
        self::assertTrue($limited['possibly_incomplete']);
    }

    public function testAccountLevelZeroBaseAndRevenueHiddenGroups(): void
    {
        $db = $this->env->db;
        $account = SaldiInsightTools::comparePeriods(
            $this->env->ctx(),
            $db,
            self::PERIODS + ['level' => 'account']
        );
        $this->validateData('compare_periods', $account);
        $rows = array_column($account['data']['rows'], null, 'key');
        self::assertNull($rows['6600']['change_pct']);
        self::assertArrayNotHasKey('3000', $rows);
        $changed = SaldiInsightTools::comparePeriods(
            $this->env->ctx(),
            $db,
            self::PERIODS + ['level' => 'account', 'only_changed' => true]
        );
        self::assertNotContains('4000', array_column($changed['data']['rows'], 'key'));
        $ctx = $this->hiddenContext();
        $hidden = SaldiInsightTools::comparePeriods($ctx, $db, self::PERIODS);
        self::assertTrue($hidden['data']['revenue_hidden']);
        self::assertSame(['1999'], array_column($hidden['data']['rows'], 'key'));
        $this->error('PERMISSION_DENIED', fn() => SaldiInsightTools::tillSummary(
            $ctx,
            $db,
            ['date' => '2026-06-10']
        ));
    }

    public function testComparisonValidationAndPermission(): void
    {
        $db = $this->env->db;
        $this->error('VALIDATION_ERROR', fn() => SaldiInsightTools::comparePeriods(
            $this->env->ctx(),
            $db,
            array_replace(self::PERIODS, ['a_from' => '2026-02-30'])
        ));
        $this->error('VALIDATION_ERROR', fn() => SaldiInsightTools::comparePeriods(
            $this->env->ctx(),
            $db,
            array_replace(self::PERIODS, ['a_to' => '2025-12-31'])
        ));
        $this->error('PERMISSION_DENIED', fn() => SaldiInsightTools::comparePeriods(
            $this->env->ctx(910001, '1000000000000000'),
            $db,
            self::PERIODS
        ));
    }

    public function testYearEndHistoricOpenItemsVatAndTrialTotals(): void
    {
        $result = SaldiInsightTools::yearEndPack($this->env->ctx(), $this->env->db, []);
        $this->validateData('get_year_end_pack', $result);
        $data = $result['data'];
        self::assertSame([1, 2, 5], array_column($data['open_items'], 'item_id'));
        self::assertSame('EUR', $data['open_items'][2]['currency']);
        self::assertSame('100.31', $data['trial_balance']['totals']['opening']);
        self::assertSame('50.01', $data['trial_balance']['totals']['debit']);
        self::assertSame('305.00', $data['trial_balance']['totals']['credit']);
        self::assertSame('-154.69', $data['trial_balance']['totals']['closing']);
        self::assertCount(4, $data['vat']);
        self::assertSame('25.00', $data['vat'][1]['sales_vat']);
        self::assertSame('10.00', $data['vat'][1]['purchase_vat']);
        self::assertSame('15.00', $data['vat'][1]['payable']);
        self::assertSame([11], array_column($data['unposted_journals'], 'journal_id'));
        self::assertCount(12, $data['periods_closed']);
        self::assertNotEmpty($data['notes']);
        $this->error('NOT_FOUND', fn() => SaldiInsightTools::yearEndPack(
            $this->env->ctx(),
            $this->env->db,
            ['fiscal_year' => 9999]
        ));
    }

    public function testHiddenYearEndOmitsDriftDebtorsAndVat(): void
    {
        $result = SaldiInsightTools::yearEndPack($this->hiddenContext(), $this->env->db, []);
        $this->validateData('get_year_end_pack', $result);
        self::assertSame([], $result['data']['vat']);
        self::assertSame(['creditor'], array_column($result['data']['open_items'], 'party_type'));
        self::assertNotContains('pl', array_column($result['data']['trial_balance']['rows'], 'kind'));
        self::assertSame('', $result['data']['unposted_journals'][0]['note']);
    }

    public function testTillIsolationRefundsSplitTenderChangeAndStoredCount(): void
    {
        $result = SaldiInsightTools::tillSummary(
            $this->env->ctx(),
            $this->env->db,
            ['date' => '2026-06-10']
        );
        $this->validateData('get_till_summary', $result);
        $rows = array_column($result['data']['rows'], null, 'till');
        self::assertSame(2, $rows[1]['receipts']);
        self::assertSame('80.00', $rows[1]['turnover_net']);
        self::assertSame('100.00', $rows[1]['turnover_gross']);
        self::assertSame('-25.00', $rows[1]['refunds']);
        $payments = array_column($rows[1]['payments'], 'amount', 'method');
        self::assertSame('50.00', $payments['Kontant']);
        self::assertSame('50.00', $payments['Betalingskort']);
        self::assertSame('150.00', $rows[1]['expected']);
        self::assertSame('145.00', $rows[1]['counted']);
        self::assertSame('-5.00', $rows[1]['difference']);
        self::assertSame('-5.00', $rows[1]['cash_difference_postings'][0]['amount']);
        self::assertSame('2.00', $rows[2]['difference']);
        self::assertSame('50.00', $rows[2]['payments'][0]['amount']);
        $one = SaldiInsightTools::tillSummary(
            $this->env->ctx(),
            $this->env->db,
            ['date' => '2026-06-10', 'till' => 2]
        );
        self::assertSame([2], array_column($one['data']['rows'], 'till'));
        self::assertSame([], $one['data']['rows'][0]['cash_difference_postings']);
    }

    public function testMissingTenderFallbackCardOnlyChangeAndUncountedTill(): void
    {
        $db = $this->env->db;
        $db->exec('DELETE FROM pos_betalinger WHERE ordre_id=53');
        $db->insert('ordrer', ['id' => 54, 'art' => 'PO', 'status' => 3, 'felt_5' => '3',
            'fakturadate' => '2026-06-10', 'sum' => '80', 'moms' => '20', 'valuta' => 'DKK']);
        $db->insert('pos_betalinger', ['ordre_id' => 54, 'betalingstype' => 'Betalingskort',
            'amount' => '120', 'valuta' => 'DKK']);
        $result = SaldiInsightTools::tillSummary($this->env->ctx(), $db, ['date' => '2026-06-10']);
        $this->validateData('get_till_summary', $result);
        $rows = array_column($result['data']['rows'], null, 'till');
        self::assertSame('50.00', $rows[2]['payments'][0]['amount']);
        self::assertNull($rows[3]['counted']);
        self::assertSame('-20.00', array_column($rows[3]['payments'], 'amount', 'method')['Kontant']);
    }

    public function testLatestCountDateQuantityValueAndFefoHorizon(): void
    {
        $result = SaldiInsightTools::stockCount(
            $this->env->ctx(),
            $this->env->db,
            ['date' => '2026-06-10']
        );
        $this->validateData('get_stock_count', $result);
        self::assertSame('2026-06-10', $result['data']['date']);
        self::assertSame('10.500', $result['data']['rows'][0]['expected']);
        self::assertSame('9.250', $result['data']['rows'][0]['counted']);
        self::assertSame('-1.250', $result['data']['rows'][0]['difference']);
        self::assertSame('-10.00', $result['data']['rows'][0]['value_difference']);
        self::assertFalse($result['data']['rows'][0]['posted']);
        self::assertSame([-1, 30], array_column($result['data']['fefo'], 'days_left'));
        $old = SaldiInsightTools::stockCount(
            $this->env->ctx(),
            $this->env->db,
            ['date' => '2026-06-09']
        );
        self::assertTrue($old['data']['rows'][0]['posted']);
        $empty = SaldiInsightTools::stockCount(
            $this->env->ctx(),
            $this->env->db,
            ['date' => '2026-01-01']
        );
        self::assertNull($empty['data']['date']);
    }

    public function testMasterDataB2bDuplicatesAndAllHygieneChecks(): void
    {
        $result = SaldiInsightTools::masterData($this->env->ctx(), $this->env->db, []);
        $this->validateData('check_master_data', $result);
        $rows = $result['data']['rows'];
        self::assertSame(
            ['below_cost_price', 'closed_party_referenced', 'closed_product_on_order',
            'duplicate_party', 'invalid_group', 'missing_cost_price', 'missing_cvr', 'missing_email'],
            array_values(array_unique(array_column($rows, 'kind')))
        );
        $duplicates = array_filter($rows, fn($row) => $row['kind'] === 'duplicate_party');
        self::assertCount(3, $duplicates);
        $missing = array_values(array_filter($rows, fn($row) => $row['kind'] === 'missing_cvr'));
        self::assertCount(1, $missing);
        self::assertStringContainsString('Erhvervskunde', $missing[0]['label']);
        self::assertStringNotContainsString('private-field', json_encode($result));
        foreach ($rows as $row) {
            self::assertTrue(SaldiScreens::isAllowed($row['screen']));
        }
        $this->env->db->insert('adresser', ['id' => 12, 'kontonr' => 1200, 'art' => 'D',
            'firmanavn' => 'Nord Handel', 'cvrnr' => '90809080', 'email' => 'ny@example.test', 'gruppe' => 1]);
        $normalized = SaldiInsightTools::masterData($this->env->ctx(), $this->env->db, []);
        self::assertCount(4, array_filter(
            $normalized['data']['rows'],
            fn($row) => $row['kind'] === 'duplicate_party'
        ));
    }

    public function testAllToolsLeaveDomainAndReportRowsUnchanged(): void
    {
        $db = $this->env->db;
        $tables = ['report', 'ordrer', 'pos_betalinger', 'transaktioner', 'openpost',
            'regulering', 'adresser', 'varer', 'kladdeliste', 'kassekladde'];
        $snapshot = fn() => array_map(fn($table) => $db->all('SELECT * FROM ' . $table . ' ORDER BY id'), $tables);
        $before = $snapshot();
        $ctx = $this->env->ctx();
        SaldiInsightTools::comparePeriods($ctx, $db, self::PERIODS);
        SaldiInsightTools::yearEndPack($ctx, $db, []);
        SaldiInsightTools::tillSummary($ctx, $db, ['date' => '2026-06-10']);
        SaldiInsightTools::stockCount($ctx, $db, []);
        SaldiInsightTools::masterData($ctx, $db, []);
        self::assertSame($before, $snapshot());
    }

    public function testComparisonUsesEachPeriodsOwnFiscalChart(): void
    {
        $db = $this->env->db;
        $db->insert('grupper', ['art' => 'RA', 'kodenr' => 99, 'box1' => '01',
            'box2' => '2025', 'box3' => '12', 'box4' => '2025']);
        foreach ([[1000, 'D', 0], [1100, 'S', 0], [1999, 'Z', 1100]] as $account) {
            $db->insert('kontoplan', ['kontonr' => $account[0], 'kontotype' => $account[1],
                'fra_kto' => $account[2], 'regnskabsaar' => 99, 'beskrivelse' => 'Historisk konto']);
        }
        $db->insert('transaktioner', ['kontonr' => 1100, 'transdate' => '2025-06-10',
            'debet' => '7', 'kredit' => '0']);
        $periods = ['a_from' => '2025-01-01', 'a_to' => '2025-12-31',
            'b_from' => '2026-01-01', 'b_to' => '2026-12-31'];
        $result = SaldiInsightTools::comparePeriods($this->env->ctx(), $db, $periods);
        $rows = array_column($result['data']['rows'], null, 'key');
        self::assertSame('7.00', $rows['1999']['a']);
        self::assertSame('35.01', $rows['1999']['b']);
        $hidden = SaldiInsightTools::comparePeriods(
            $this->hiddenContext(),
            $db,
            $periods + ['level' => 'account']
        );
        self::assertNotContains('1000', array_column($hidden['data']['rows'], 'key'));
        $this->validateData('compare_periods', $hidden);
    }

    public function testLatestCashReportAndUnknownLayoutNeverReuseOlderCount(): void
    {
        $db = $this->env->db;
        $db->insert('report', ['report_number' => 999, 'date' => '2026-06-10',
            'type' => 'Head line', 'description' => 'Cash count, box 1', 'total' => '0']);
        foreach (
            [['Forventet beholdning DKK:', '300'], ['Unknown count layout', '400'],
            ['Difference DKK:', '100']] as $row
        ) {
            $db->insert('report', ['report_number' => 999, 'date' => '2026-06-10',
                'type' => 'cashCount', 'description' => $row[0], 'total' => $row[1]]);
        }
        $result = SaldiInsightTools::tillSummary(
            $this->env->ctx(),
            $db,
            ['date' => '2026-06-10']
        );
        self::assertTrue($result['possibly_incomplete']);
        $rows = array_column($result['data']['rows'], null, 'till');
        self::assertNull($rows[1]['counted']);
        self::assertSame('62.00', $rows[2]['counted']);
    }

    public function testTillCurrenciesRemainSeparateAndCountOnlyTillIsVisible(): void
    {
        $db = $this->env->db;
        $db->insert('ordrer', ['id' => 60, 'art' => 'PO', 'status' => 3, 'felt_5' => '1',
            'fakturadate' => '2026-06-10', 'sum' => '8', 'moms' => '2', 'valuta' => 'EUR',
            'felt_1' => 'Kontant']);
        $db->insert('pos_betalinger', ['ordre_id' => 60, 'betalingstype' => 'Kontant',
            'amount' => '10', 'valuta' => 'EUR']);
        $db->insert('report', ['report_number' => 103, 'date' => '2026-06-10',
            'type' => 'Head line', 'description' => 'Cash count, box 3', 'total' => '0']);
        foreach (
            [['Forventet beholdning DKK:', '0'], ['Optalt beholdning DKK:', '0'],
            ['Difference DKK:', '0']] as $row
        ) {
            $db->insert('report', ['report_number' => 103, 'date' => '2026-06-10',
                'type' => 'cashCount', 'description' => $row[0], 'total' => $row[1]]);
        }
        $result = SaldiInsightTools::tillSummary($this->env->ctx(), $db, ['date' => '2026-06-10']);
        $this->validateData('get_till_summary', $result);
        $rows = $result['data']['rows'];
        self::assertCount(4, $rows);
        self::assertSame('100.00', $rows[0]['turnover_gross']);
        self::assertSame('EUR', $rows[1]['currency']);
        self::assertSame('10.00', $rows[1]['turnover_gross']);
        self::assertSame('10.00', $rows[1]['payments'][0]['amount']);
        self::assertSame(0, $rows[3]['receipts']);
        self::assertSame('0.00', $rows[3]['counted']);
    }

    public function testFefoUserHorizonAndDisabledExpiry(): void
    {
        $db = $this->env->db;
        $db->insert('settings', ['var_grp' => 'lager', 'var_name' => 'due_date_warning_days',
            'var_value' => '0', 'user_id' => 910001]);
        $result = SaldiInsightTools::stockCount($this->env->ctx(), $db, ['date' => '2026-06-10']);
        self::assertSame([-1], array_column($result['data']['fefo'], 'days_left'));
        $this->setting('items', 'batchExpiryEnabled', 'off');
        $db->exec("UPDATE grupper SET box9='' WHERE art='VG'");
        $disabled = SaldiInsightTools::stockCount($this->env->ctx(), $db, ['date' => '2026-06-10']);
        self::assertSame([], $disabled['data']['fefo']);
    }

    public function testAllFiveMcpSuccessAndPermissionErrorEnvelopesValidate(): void
    {
        $db = $this->env->db;
        $inputs = ['compare_periods' => self::PERIODS, 'get_year_end_pack' => [],
            'get_till_summary' => ['date' => '2026-06-10'],
            'get_stock_count' => ['date' => '2026-06-10'], 'check_master_data' => []];
        foreach ([true, false] as $allowed) {
            $ctx = $this->env->ctx(910001, $allowed ? str_repeat('1', 16) : str_repeat('0', 16));
            $server = new SaldiMcpServer(null, fn() => ['ctx' => $ctx, 'db' => $db]);
            foreach ($inputs as $name => $args) {
                $response = $server->handle(['method' => 'POST', 'headers' => [
                    'Authorization' => 'Bearer smcp1.' . bin2hex(random_bytes(16)),
                    'Content-Type' => 'application/json'], 'body' => json_encode([
                        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                        'params' => ['name' => $name, 'arguments' => (object)$args]])]);
                self::assertSame(200, $response['status']);
                $envelope = $response['body']['result']['structuredContent'];
                SaldiSchemaValidator::validate($envelope, SaldiToolRegistry::default()->get($name)->outputSchema());
                self::assertSame($allowed, $envelope['ok'], $name . ': ' . json_encode($envelope['error'] ?? []));
                self::assertFalse($envelope['committed']);
                if (!$allowed) {
                    self::assertSame('PERMISSION_DENIED', $envelope['error']['code']);
                }
            }
        }
    }
    public function testForeignTenderUsesStoredRateBeforeComputingCashChange(): void
    {
        $db = $this->env->db;
        $db->insert('ordrer', ['id' => 80, 'art' => 'PO', 'status' => 3, 'felt_5' => '4', 'fakturadate' => '2026-06-10',
            'sum' => '600', 'moms' => '150', 'valuta' => 'DKK']);
        $db->insert('pos_betalinger', ['ordre_id' => 80, 'betalingstype' => 'Kontant', 'amount' => '100', 'valuta' => 'EUR', 'valutakurs' => '750']);
        $result = SaldiInsightTools::tillSummary($this->env->ctx(), $db, ['date' => '2026-06-10', 'till' => 4]);
        $rows = array_column($result['data']['rows'], null, 'currency');
        self::assertSame('0.00', array_column($rows['DKK']['payments'], 'amount', 'method')['Kontant']);
        self::assertSame('100.00', $rows['EUR']['payments'][0]['amount']);
        $db->exec('UPDATE pos_betalinger SET amount=110 WHERE ordre_id=80');
        $result = SaldiInsightTools::tillSummary($this->env->ctx(), $db, ['date' => '2026-06-10', 'till' => 4]);
        $rows = array_column($result['data']['rows'], null, 'currency');
        self::assertSame('-75.00', array_column($rows['DKK']['payments'], 'amount', 'method')['Kontant']);
    }

    public function testEighteenMonthYearIncludesEveryCalendarQuarter(): void
    {
        $db = $this->env->db;
        $db->exec("UPDATE grupper SET box1='05',box2='2025',box3='10',box4='2026' WHERE art='RA' AND kodenr=4");
        $result = SaldiInsightTools::yearEndPack($this->env->ctx(), $db, []);
        $this->validateData('get_year_end_pack', $result);
        self::assertCount(7, $result['data']['vat']);
        self::assertSame('2025-05-01', $result['data']['vat'][0]['from']);
        self::assertSame('2025-06-30', $result['data']['vat'][0]['to']);
        self::assertSame('2026-10-01', $result['data']['vat'][6]['from']);
        self::assertSame('2026-10-31', $result['data']['vat'][6]['to']);
    }

    public function testCompleteYearEndPageAndCsvContainMoreThanTwoHundredRows(): void
    {
        $db = $this->env->db;
        $db->exec("INSERT INTO kontoplan (kontonr,regnskabsaar,kontotype,beskrivelse) SELECT 10000+n,4,'S','Testkonto' FROM generate_series(1,250) n");
        $db->exec("INSERT INTO openpost (konto_id,konto_nr,transdate,amount,udlignet,valuta) SELECT 10,1000,CAST('2026-06-10' AS date),1,'','DKK' FROM generate_series(1,250)");
        $limited = SaldiInsightTools::yearEndPack($this->env->ctx(), $db, []);
        // The tool returns at most 60 non-zero accounts; the page and CSV carry everything.
        self::assertLessThanOrEqual(60, count($limited['data']['trial_balance']['rows']));
        self::assertLessThanOrEqual(30, count($limited['data']['open_items']));
        self::assertTrue($limited['possibly_incomplete']);
        $complete = SaldiYearEndPack::build($this->env->ctx(), $db, 4, true);
        self::assertCount(257, $complete['data']['trial_balance']['rows']);
        self::assertCount(253, $complete['data']['open_items']);
        if (!defined('SALDI_REVISORPAKKE_LIBRARY')) {
            define('SALDI_REVISORPAKKE_LIBRARY', true);
        }
        require_once __DIR__ . '/../../../systemdata/revisorpakke.php';
        self::assertStringContainsString('10250', saldi_revisorpakke_csv($complete['data'], 'trial_balance', $db, 1));
        self::assertCount(255, explode("\r\n", saldi_revisorpakke_csv($complete['data'], 'open_items', $db, 1)));
    }

    public function testSupplierCvrDoesNotUseDebtorB2bGroup(): void
    {
        $db = $this->env->db;
        $db->exec("UPDATE adresser SET cvrnr='' WHERE art='K'");
        $result = SaldiInsightTools::masterData($this->env->ctx(), $db, []);
        $missing = array_values(array_filter($result['data']['rows'], fn(array $row): bool => $row['kind'] === 'missing_cvr'));
        self::assertCount(1, $missing);
        self::assertStringContainsString('Erhvervskunde', $missing[0]['label']);
    }

    public function testDuplicatePartyGroupingScalesAndPreservesCvrAndNameMatches(): void
    {
        $db = $this->env->db;
        $db->exec("INSERT INTO adresser (kontonr,art,firmanavn,cvrnr,gruppe,email) SELECT 20000+n,'D','Unik '||n,CAST(90000000+n AS text),2,'test@example.invalid' FROM generate_series(1,2500) n");
        $result = SaldiInsightTools::masterData($this->env->ctx(), $db, []);
        self::assertCount(3, array_filter($result['data']['rows'], fn(array $row): bool => $row['kind'] === 'duplicate_party'));
        self::assertStringNotContainsString('b.id<>a.id', file_get_contents(__DIR__ . '/../../../includes/automation/SaldiMasterDataHygiene.php'));
    }

    public function testStockCountKeepsWarehouseAndVariantIdentities(): void
    {
        $db = $this->env->db;
        $db->insert('regulering', ['vare_id' => 1, 'lager' => 2, 'variant_id' => 7, 'tidspkt' => '20260610235959', 'beholdning' => '10', 'optalt' => '8', 'bogfort' => '0']);
        $result = SaldiInsightTools::stockCount($this->env->ctx(), $db, ['date' => '2026-06-10']);
        $this->validateData('get_stock_count', $result);
        self::assertSame([1, 2], array_column($result['data']['rows'], 'warehouse'));
        self::assertSame([0, 7], array_column($result['data']['rows'], 'variant_id'));
    }

}
