<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiWatchToolsTest.test.php --- ai/mcp-assistant --- 2026.10.03
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
// Isolated Watch v2 regression coverage using synthetic fixtures only.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Add regression coverage for the confirmed Assist review findings.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiWatchDbTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';
require_once __DIR__ . '/../../../includes/mcp/SaldiMcpServer.php';

final class SaldiWatchToolsTest extends SaldiWatchDbTestCase
{
    private function tool(string $name, array $args = [], ?SaldiTenantContext $ctx = null): array
    {
        $ctx ??= $this->env->ctx(910001, null, 'agent:saldi-assist', 99, ['read']);
        $server = new SaldiMcpServer(
            null,
            fn(string $token): array => ['ctx' => $ctx, 'db' => $this->env->db],
            [],
            'test'
        );
        $token = 'smcp1.local.10.99.' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $response = $server->handle(['method' => 'POST', 'headers' => ['Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token], 'body' => json_encode([
                'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => (object)$args]], JSON_THROW_ON_ERROR)]);
        self::assertSame(200, $response['status']);
        $result = $response['body']['result']['structuredContent'];
        SaldiSchemaValidator::validate($result, SaldiToolRegistry::default()->get($name)->outputSchema());
        return $result;
    }

    public function testVatPortsPerTransactionThenWholeAccountRoundingAndEverySign(): void
    {
        $db = $this->env->db;
        foreach (['SM' => 1100, 'KM' => 1200, 'EM' => 1300, 'YM' => 1400] as $art => $account) {
            $db->insert('kontoplan', ['kontonr' => $account, 'regnskabsaar' => 4, 'kontotype' => 'S']);
            $db->insert('grupper', ['art' => $art, 'kodenr' => 1, 'box1' => (string)$account,
                'box2' => '25', 'fiscal_year' => 4]);
        }
        $db->exec("DELETE FROM grupper WHERE art = 'MR'");
        $db->insert('grupper', ['art' => 'MR', 'box1' => '1100', 'box2' => '1499']);
        foreach (
            [[1100, '0', '100.495'], [1100, '0', '0.005'], [1200, '20.495', '0'],
            [1300, '0', '30.505'], [1400, '0', '40.495']] as $row
        ) {
            $db->insert('transaktioner', ['kontonr' => $row[0], 'debet' => $row[1],
                'kredit' => $row[2], 'transdate' => '2026-09-01']);
        }
        $db->insert('grupper', ['art' => 'SM', 'kodenr' => 2, 'box1' => '1100', 'box2' => '25', 'fiscal_year' => 4]);
        $db->insert(
            'transaktioner',
            ['kontonr' => 1100, 'debet' => '0', 'kredit' => '999', 'transdate' => '2026-08-31']
        );
        $result = $this->tool('get_vat_summary', ['from' => '2026-09-01', 'to' => '2026-09-30']);
        self::assertTrue($result['ok']);
        $data = (array)$result['data'];
        self::assertSame('101.00', $data['sales_vat']);
        self::assertSame('21.00', $data['purchase_vat']);
        self::assertSame('152.00', $data['payable']);
        self::assertCount(5, $data['codes']);
        self::assertFalse($data['period_closed']);
        self::assertSame('Computed from posted transactions only; unposted journals are not included.', $data['note']);
        self::assertSame(
            ['eu_purchase', 'purchase', 'sales', 'sales', 'foreign_service'],
            array_column($data['codes'], 'kind')
        );
        self::assertSame(['E1', 'K1', 'S1', 'S2', 'Y1'], array_column($data['codes'], 'code'));
    }

    public function testVatClosuresRequireEveryTouchedMonthAndEmptyVatIsHonest(): void
    {
        $this->closureTable();
        $db = $this->env->db;
        $args = ['from' => '2026-01-31', 'to' => '2026-03-01'];
        foreach ([1, 2] as $month) {
            $db->insert(
                'moms_periode_luk',
                ['kalender_aar' => 2026, 'kalender_maaned' => $month, 'status' => 'closed']
            );
        }
        self::assertFalse($this->tool('get_vat_summary', $args)['data']['period_closed']);
        $db->insert('moms_periode_luk', ['kalender_aar' => 2026, 'kalender_maaned' => 3, 'status' => 'closed']);
        $data = $this->tool('get_vat_summary', $args)['data'];
        self::assertTrue($data['period_closed']);
        self::assertSame('0.00', $data['payable']);
        self::assertSame([], $data['codes']);
    }

    public function testEveryToolErrorFixtureValidatesIncludingRevenueAndRanges(): void
    {
        $args = ['from' => '2026-09-01', 'to' => '2026-09-30'];
        $this->setting('finans', 'hideRevenue', 'on');
        $ctx = $this->onlyPermission(3);
        self::assertSame('PERMISSION_DENIED', $this->tool('get_vat_summary', $args, $ctx)['error']['code']);
        foreach (['get_vat_summary', 'get_period_status'] as $name) {
            $result = $this->tool($name, ['from' => '2026-10-02', 'to' => '2026-10-01']);
            self::assertSame('VALIDATION_ERROR', $result['error']['code']);
        }
        self::assertSame('VALIDATION_ERROR', $this->tool(
            'get_vat_summary',
            ['from' => '2026-12-01', 'to' => '2027-01-01']
        )['error']['code']);
        self::assertSame('VALIDATION_ERROR', $this->tool(
            'get_vat_summary',
            ['from' => '2026-02-30', 'to' => '2026-03-01']
        )['error']['code']);
        $denied = $this->tool('get_period_status', $args, $this->onlyPermission(0));
        self::assertSame('PERMISSION_DENIED', $denied['error']['code']);
        self::assertSame('PERMISSION_DENIED', $this->tool(
            'get_daily_overview',
            [],
            $this->env->ctx(910001, str_repeat('0', 16))
        )['error']['code']);
    }

    public function testOverviewAndPeriodOkFixturesValidateAndEmptyPeriodIsHonest(): void
    {
        self::assertTrue($this->tool('get_daily_overview')['ok']);
        $args = ['from' => '2026-09-01', 'to' => '2026-09-30'];
        $empty = $this->tool('get_period_status', $args)['data'];
        self::assertSame([], $empty['open_journals']);
        self::assertSame(0, $empty['unposted_lines']);
        self::assertSame(0, $empty['lines_without_document']);
        self::assertNull($empty['last_posted_date']);
        self::assertSame([['month' => '2026-09', 'closed' => false]], $empty['vat_months']);
        $this->journal();
        $this->line(70001, ['bilag' => 1]);
        $this->line(70002, ['bilag' => 1]);
        $this->journal(70002, '2026-09-01', 'on');
        $this->line(70003, ['kladde_id' => 70002, 'dokument' => 'fixture.pdf']);
        $this->env->db->insert('transaktioner', ['kontonr' => 1000, 'transdate' => '2026-09-20']);
        $data = $this->tool('get_period_status', $args)['data'];
        self::assertSame(2, $data['unposted_lines']);
        self::assertSame(1, $data['lines_without_document']);
        self::assertSame('2026-09-20', $data['last_posted_date']);
        self::assertSame(2, $data['open_journals'][0]['lines']);
    }

    public function testPeriodTruncationIsHonestWhileCountsStayComplete(): void
    {
        foreach (range(70001, 70025) as $id) {
            $this->journal($id);
            $this->line($id, ['kladde_id' => $id]);
        }
        $result = $this->tool('get_period_status', ['from' => '2026-09-01', 'to' => '2026-09-30']);
        self::assertTrue($result['possibly_incomplete']);
        self::assertCount(20, $result['data']['open_journals']);
        self::assertSame(25, $result['data']['unposted_lines']);
        self::assertSame(25, $result['data']['lines_without_document']);
    }
    public function testVatPayableIncludesUncodedExciseAccountsAndCanonicalCitations(): void
    {
        $db = $this->env->db;
        $db->exec("DELETE FROM grupper WHERE art = 'MR'");
        $db->insert('grupper', ['art' => 'MR', 'box1' => '1000', 'box2' => '1999']);
        $db->insert('grupper', ['art' => 'SM', 'kodenr' => 1, 'box1' => '1000', 'box2' => '25', 'fiscal_year' => 4]);
        foreach ([1500, 1600] as $account) {
            $db->insert('kontoplan', ['kontonr' => $account, 'regnskabsaar' => 4, 'kontotype' => 'S']);
        }
        foreach ([[1000, '0', '100.495'], [1500, '0', '4.505'], [1600, '2.495', '0'], [2000, '0', '999']] as [$account, $debit, $credit]) {
            $db->insert('transaktioner', ['kontonr' => $account, 'debet' => $debit, 'kredit' => $credit, 'transdate' => '2026-09-01']);
        }
        $result = $this->tool('get_vat_summary', ['from' => '2026-09-01', 'to' => '2026-09-30']);
        self::assertSame('103.00', $result['data']['payable']);
        self::assertCount(1, $result['data']['codes']);
        self::assertSame(['saldi:account:1000@4', 'saldi:account:1500@4', 'saldi:account:1600@4'], array_column($result['citations'], 'id'));
    }

}
