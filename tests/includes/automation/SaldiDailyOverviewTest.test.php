<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiDailyOverviewTest.test.php --- ai/mcp-assistant --- 2026.10.02
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

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiWatchDbTestCase.php';

final class SaldiDailyOverviewTest extends SaldiWatchDbTestCase
{
    public function testEverySectionIsGatedIndependentlyIncludingAgentScopes(): void
    {
        foreach (
            [0 => [], 2 => ['open_journals', 'vat', 'pool_documents'], 6 => ['overdue_debtors'],
            7 => ['pool_documents'], 9 => ['low_stock']] as $position => $sections
        ) {
            $data = SaldiDailyOverview::build($this->onlyPermission($position), $this->env->db);
            $keys = array_keys($data);
            sort($keys);
            $expected = array_merge(['date', 'nothing_to_do', 'open_items'], $sections);
            sort($expected);
            self::assertSame($expected, $keys);
            self::assertTrue($data['nothing_to_do']);
        }
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 99, ['draft']);
        self::assertSame(['date', 'nothing_to_do'], array_keys(SaldiDailyOverview::build($agent, $this->env->db)));
        $zero = $this->env->ctx(910001, str_repeat('0', 16));
        self::assertSame(['date', 'nothing_to_do'], array_keys(SaldiDailyOverview::build($zero, $this->env->db)));
    }

    public function testVisibleInterruptsKindsAndOwnerIsolation(): void
    {
        $ctx = $this->env->ctx();
        $own = $this->inbox()->create($ctx, 'followup.task', ['title' => 'Own fixture'], ['attention' => 'interrupt']);
        $this->inbox()->create(
            $this->env->ctx(910002),
            'followup.task',
            ['title' => 'Other fixture'],
            ['attention' => 'interrupt']
        );
        $rule = $this->rule('supplier_bank_change');
        $this->inbox()->create(
            $rule,
            'routine.finding',
            ['routine' => 'supplier_bank_change', 'scheduled_date' => '2026-10-02',
                'count' => 1, 'rows' => [['label' => 'Bank changed']]],
            ['attention' => 'interrupt']
        );
        $data = SaldiDailyOverview::build($this->onlyPermission(0), $this->env->db);
        self::assertSame(1, $data['open_items']['count']);
        self::assertSame([$own['id']], array_column($data['open_items']['interrupts'], 'item_id'));
        self::assertSame('followup.task', $data['open_items']['by_kind'][0]['kind']);
        self::assertSame(SaldiText::get($this->env->db, 5704, 1, ''), $data['open_items']['by_kind'][0]['label']);
        self::assertFalse($data['nothing_to_do']);
        self::assertSame(2, SaldiDailyOverview::build($ctx, $this->env->db)['open_items']['count']);
    }

    public function testOverdueHasNoGraceUsesSharedCurrencyRulesAndHonestMissingRate(): void
    {
        $db = $this->env->db;
        $this->supplier(70001, ['art' => 'D']);
        $yesterday = (new DateTimeImmutable(SaldiToolRuntime::today()))->modify('-1 day')->format('Y-m-d');
        foreach ([['100.005', 'DKK', '100'], ['10.000', 'EUR', '750'], ['22.000', 'UNKNOWN', '100']] as $row) {
            $db->insert('openpost', ['konto_id' => 70001, 'amount' => $row[0], 'valuta' => $row[1],
                'valutakurs' => $row[2], 'forfaldsdate' => $yesterday, 'transdate' => $yesterday, 'udlignet' => '']);
        }
        $overview = SaldiDailyOverview::build($this->onlyPermission(6), $db);
        self::assertSame(
            ['count' => 1, 'amount' => '175.01', 'currency' => 'DKK', 'converted' => false],
            $overview['overdue_debtors']
        );
        self::assertFalse($overview['nothing_to_do']);
    }

    public function testJournalAgeAndLowStockMatterButPoolDocumentsAloneDoNot(): void
    {
        $db = $this->env->db;
        $db->insert('pool_files', ['filename' => 'fixture.pdf']);
        self::assertTrue(SaldiDailyOverview::build($this->env->ctx(), $db)['nothing_to_do']);
        $today = SaldiToolRuntime::today();
        $this->journal(70001, $today);
        $this->line(70001, ['transdate' => $today, 'd_type' => 'D']);
        self::assertTrue(SaldiDailyOverview::build($this->env->ctx(), $db)['nothing_to_do']);
        $old = (new DateTimeImmutable($today))->modify('-15 days')->format('Y-m-d');
        $db->exec('UPDATE kladdeliste SET kladdedate = ?', [$old]);
        self::assertFalse(SaldiDailyOverview::build($this->onlyPermission(2), $db)['nothing_to_do']);
        self::assertTrue(SaldiDailyOverview::build($this->onlyPermission(9), $db)['nothing_to_do']);
        $db->insert('varer', ['varenr' => 'FIXTURE', 'min_lager' => '3', 'beholdning' => '2', 'lukket' => '']);
        self::assertFalse(SaldiDailyOverview::build($this->onlyPermission(9), $db)['nothing_to_do']);
    }

    public function testMissingDocumentIsPerBilagAndAnySiblingAttachmentCoversIt(): void
    {
        $db = $this->env->db;
        $ctx = $this->env->ctx();
        $this->journal();
        $this->line(70001, ['bilag' => 1]);
        $this->line(70002, ['bilag' => 1]);
        $this->line(70003, ['bilag' => 2, 'd_type' => 'D']);
        $this->line(70004, ['bilag' => 3, 'debet' => '2000', 'kredit' => '1000']);
        self::assertSame(2, SaldiDailyOverview::missingDocuments($ctx, $db, '2026-09-01', '2026-09-30', true)['count']);
        $all = SaldiDailyOverview::missingDocuments($ctx, $db, '2026-09-01', '2026-09-30', false);
        self::assertSame(3, $all['count']);
        $this->line(70005, ['bilag' => 1, 'transdate' => '2026-08-01', 'dokument' => 'legacy-fixture.pdf']);
        $db->insert('documents', ['source' => 'kassekladde', 'source_id' => 70004, 'filename' => 'fixture.pdf']);
        self::assertSame(0, SaldiDailyOverview::missingDocuments($ctx, $db, '2026-09-01', '2026-09-30', true)['count']);
        $all = SaldiDailyOverview::missingDocuments($ctx, $db, '2026-09-01', '2026-09-30', false);
        self::assertSame(1, $all['count']);
    }

    public function testMissingBilagAmountsUseStoredForeignRateInBaseCurrency(): void
    {
        $db = $this->env->db;
        $db->insert('grupper', ['art' => 'VK', 'kodenr' => 70001, 'box1' => 'EUR']);
        $this->journal();
        $this->line(70001, ['valuta' => 70001, 'valutakurs' => '750', 'amount' => '10.000']);
        $rows = SaldiDailyOverview::missingDocuments(
            $this->env->ctx(),
            $db,
            '2026-09-01',
            '2026-09-30',
            true
        )['rows'];
        self::assertSame('75.00', $rows[0]['amount']);
        self::assertSame('DKK', $rows[0]['currency']);
    }
}
