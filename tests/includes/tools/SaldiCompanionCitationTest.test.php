<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiCompanionCitationTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// Typed citations enforce source permissions, parent visibility and revenue restrictions.
//
// 20261002 CDX/LH Created.
// 20261004 CDX/LH Add companion part 2 foundation contracts.

declare(strict_types=1);

require_once __DIR__ . '/SaldiToolDbTestCase.php';

final class SaldiCompanionCitationTest extends SaldiToolDbTestCase
{
    private function resolve(string $ref, ?string $rights = null): ?array
    {
        return SaldiDraftTools::resolve($this->env->ctx(910001, $rights), $this->env->db, $ref);
    }

    private function rights(array $positions): string
    {
        $rights = str_repeat('0', 16);
        foreach ($positions as $position) {
            $rights[$position] = '2';
        }
        return $rights;
    }

    public function testRulePoolAndOrderCitationsHaveRealVisibilityChecks(): void
    {
        $db = $this->env->db;
        $rule = $db->insert('posting_rules', [
            'match_type' => 'text', 'match_value' => 'software', 'account' => '1000',
            'created_by' => 'user:910001', 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        self::assertSame(
            'systemdata/assistent.php',
            $this->resolve(
                'saldi:rule:' . $rule,
                $this->rights([2])
            )['screen']
        );
        self::assertNull($this->resolve('saldi:rule:' . $rule, $this->rights([7])));
        self::assertNull($this->resolve('saldi:rule:2147483647'));
        if (!$db->hasTable('pool_files')) {
            $db->exec('CREATE TABLE pool_files (id serial PRIMARY KEY, filename varchar(255) NOT NULL)');
        }
        $pool = $db->insert('pool_files', ['filename' => 'companion-pool.pdf']);
        foreach ([2, 7, 8] as $position) {
            self::assertSame(
                'finans/pulje_review.php',
                $this->resolve(
                    'saldi:pool_file:' . $pool,
                    $this->rights([$position])
                )['screen']
            );
        }
        self::assertNull($this->resolve('saldi:pool_file:' . $pool, $this->rights([6])));
        self::assertNull($this->resolve('saldi:pool_file:2147483647'));
        foreach (
            [501 => 'KO',
            502 => 'KK',
            503 => 'R1',
            504 => 'R2',
            505 => 'R3',
            506 => 'DO',
            507 => 'DK'] as $id => $art
        ) {
            $db->insert('ordrer', ['id' => $id, 'art' => $art, 'ordrenr' => $id, 'konto_id' => 20]);
            $ref = 'saldi:order:' . $id;
            if (in_array($art, ['KO', 'KK'], true)) {
                self::assertSame('kreditor/ordre.php?id=' . $id, $this->resolve($ref, $this->rights([7]))['screen']);
                self::assertNull($this->resolve($ref, $this->rights([6])));
            } elseif (str_starts_with($art, 'R')) {
                self::assertSame(
                    'debitor/rykker.php?rykker_id=' . $id,
                    $this->resolve(
                        $ref,
                        $this->rights([6])
                    )['screen']
                );
                self::assertNull($this->resolve($ref, $this->rights([7])));
            } else {
                self::assertSame('debitor/ordre.php?id=' . $id, $this->resolve($ref, $this->rights([5]))['screen']);
                self::assertNull($this->resolve($ref, $this->rights([7])));
            }
        }
        foreach (['saldi:order:2147483647', 'saldi:skill:1', 'saldi:rule:1@4', 'saldi:pool_file:../1'] as $ref) {
            self::assertNull($this->resolve($ref));
        }
    }

    public function testDocumentsRequireGeneralAndPerSourcePermissions(): void
    {
        $db = $this->env->db;
        if (!$db->hasTable('documents')) {
            $db->exec(
                'CREATE TABLE documents (id serial PRIMARY KEY, filename varchar(255), '
                . 'source varchar(32), source_id integer)'
            );
        }
        $db->insert('ordrer', ['id' => 501, 'art' => 'KO', 'ordrenr' => 501, 'konto_id' => 20]);
        $fixtures = [
            ['kassekladde', 111, [2], [7], 'finans/kassekladde.php?kladde_id=11'],
            ['creditorOrder', 501, [7], [2], 'kreditor/ordre.php?id=501'],
            ['debitorOrdrer', 51, [5, 8], [5], 'debitor/ordre.php?id=51'],
        ];
        foreach ($fixtures as [$source, $parent, $allowed, $denied, $screen]) {
            $id = $db->insert(
                'documents',
                ['filename' => 'companion-' . $source . '.pdf',
                'source' => $source,
                'source_id' => $parent]
            );
            $ref = 'saldi:document:' . $id;
            self::assertSame($screen, $this->resolve($ref, $this->rights($allowed))['screen']);
            self::assertNull($this->resolve($ref, $this->rights($denied)));
            self::assertNull($this->resolve($ref, str_repeat('0', 16)));
        }
        $salesId = $id;
        $db->insert('settings', ['var_grp' => 'finans', 'var_name' => 'hideRevenue', 'var_value' => 'on']);
        self::assertNull($this->resolve('saldi:document:' . $salesId, $this->rights([5, 8])));
        self::assertSame('debitor/ordre.php?id=51', $this->resolve('saldi:document:' . $salesId)['screen']);
        foreach (
            [['unrecognized',
            111],
            ['kassekladde',
            2147483647],
            ['creditorOrder',
            51],
            ['debitorOrdrer',
            501]] as [$source,
            $parent]
        ) {
            $id = $db->insert(
                'documents',
                ['filename' => 'invalid-parent.pdf',
                'source' => $source,
                'source_id' => $parent]
            );
            self::assertNull($this->resolve('saldi:document:' . $id));
        }
        self::assertNull($this->resolve('saldi:document:2147483647'));
    }

    public function testNewScreensAreActionFreeAndUseVerifiedReminderOverview(): void
    {
        self::assertSame('debitor/rapport.php?rapportart=openpost', SaldiScreens::link('reminders'));
        self::assertSame('debitor/rykker.php?rykker_id=12', SaldiScreens::link('reminder', 12));
        foreach (
            ['document_pool',
            'reminders',
            'assist_settings',
            'assist_report',
            'vat_report',
            'vat_periods'] as $type
        ) {
            self::assertTrue(SaldiScreens::isAllowed(SaldiScreens::link($type)));
        }
        self::assertFalse(SaldiScreens::isAllowed('debitor/rykker.php?rykker_id=12&inkasso=1'));
        self::assertFalse(SaldiScreens::isAllowed('debitor/rapport.php?rapportart=openpost&slet=1'));
    }
}
