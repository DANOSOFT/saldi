<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiPermissionTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// Every module digit, agent scope, revenue rule and trusted worker permission hook.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cover F1 integration behavior and regression cases.
// 20261002 CDX/LH Cover companion foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
require_once __DIR__ . '/../../../includes/assist/RecordAuth.php';

final class SaldiPermissionTest extends SaldiRuntimeDbTestCase
{
    public function testPermissionMatrixEveryPositionAndDigit(): void
    {
        $expected = [0 => ['accounts.read'], 1 => ['settings.admin'], 2 => ['journal.read', 'rules.read', 'documents.read', 'journal.draft'], 3 => ['accounts.read', 'ledger.read'], 4 => ['accounts.read', 'ledger.read'], 5 => ['sales.read', 'sales.draft'], 6 => ['debtors.read', 'debtors.draft'], 7 => ['purchases.read', 'documents.read', 'purchases.draft'], 8 => ['creditors.read', 'documents.read', 'creditors.draft'], 9 => ['products.read'], 10 => [], 11 => [], 12 => ['debtors.read'], 13 => ['creditors.read'], 14 => [], 15 => ['products.read']];
        $baseline = ['company.read', 'inbox.read', 'memory.read', 'memory.propose', 'followup.draft', 'support.draft'];
        foreach ($expected as $pos => $extra) {
            foreach (range(0, 9) as $digit) {
                $rights = str_repeat('0', 16);
                $rights[$pos] = (string)$digit;
                $want = [];
                if ($digit === 1 || $digit === 2) {
                    $want = array_merge($baseline, array_filter($extra, fn($p) => $digit === 1 || !in_array($p, ['settings.admin', 'journal.draft', 'debtors.draft', 'creditors.draft', 'purchases.draft', 'sales.draft'], true)));
                }
                sort($want);
                self::assertSame($want, SaldiPermissionService::userPermissions($this->env->db, $rights), 'position ' . $pos . ', digit ' . $digit);
            }
        }
        self::assertSame([], SaldiPermissionService::userPermissions($this->env->db, 'x9'));
    }

    public function testLegacyRecordRightsAcceptOnlyOneOrTwo(): void
    {
        $cfg = saldi_runtime_config();
        $connect = static fn(string $name): PDO => new PDO(
            'pgsql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $name,
            $cfg['user'],
            $cfg['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $master = $connect($cfg['master_db']);
        $tenant = $connect($this->env->name);
        $online = ['db' => $this->env->name, 'brugernavn' => $this->env->ctx()->username];
        foreach (['journal' => 2, 'invoice' => 5] as $kind => $position) {
            foreach (array_merge(range(0, 9), ['x', '']) as $digit) {
                $rights = str_repeat('0', $position) . $digit;
                $this->env->db->exec('UPDATE brugere SET rettigheder = ? WHERE id = ?', [$rights, 910001]);
                try {
                    saldi_assist_authorize($master, $tenant, $online, $kind);
                    self::assertContains($digit, [1, 2], $kind . ': ' . $digit);
                } catch (SaldiAssistFailure $e) {
                    self::assertNotContains($digit, [1, 2]);
                    self::assertSame('record_forbidden', $e->getMessage());
                }
            }
        }
        $tenant = null;
        $master = null;
    }

    public function testAgentScopesAndRevenueRestriction(): void
    {
        $db = $this->env->db;
        $read = $this->env->ctx(910001, null, 'agent:saldi-assist', 1, ['read']);
        $draft = $this->env->ctx(910001, null, 'agent:saldi-assist', 1, ['read', 'draft']);
        self::assertFalse(SaldiPermissionService::allows($read, $db, 'journal.draft'));
        self::assertTrue(SaldiPermissionService::allows($draft, $db, 'journal.draft'));
        self::assertFalse(SaldiPermissionService::allows($draft, $db, 'settings.admin'));
        foreach (['debtors.draft', 'creditors.draft', 'purchases.draft', 'sales.draft', 'support.draft'] as $permission) {
            self::assertTrue(SaldiPermissionService::allows($draft, $db, $permission));
            self::assertFalse(SaldiPermissionService::allows($read, $db, $permission));
        }
        foreach (['rules.read', 'documents.read'] as $permission) {
            self::assertTrue(SaldiPermissionService::allows($read, $db, $permission));
        }
        self::assertSame([], SaldiPermissionService::effective($this->env->ctx(910001, null, 'agent:saldi-assist', 1, ['draft']), $db));
        $db->insert('settings', ['var_name' => 'hideRevenue', 'var_grp' => 'finans', 'var_value' => 'on']);
        self::assertFalse(SaldiPermissionService::revenueHidden($this->env->ctx(), $db));
        foreach (['0', '2', '9', 'x'] as $digit) {
            $rights = str_repeat('1', 16);
            $rights[1] = $digit;
            self::assertTrue(SaldiPermissionService::revenueHidden($this->env->ctx(910001, $rights), $db));
        }
        $this->error('PERMISSION_DENIED', fn() => SaldiPermissionService::require($read, $db, 'settings.admin'));
    }

    public function testTrustedRulesAndSystemHaveOnlyTheirPermissions(): void
    {
        $ctx = SaldiTenantContext::forSystem('local', 5, $this->env->name, 'postgresql', 'rule:low_stock', 4, 'DKK');
        self::assertSame(['company.read', 'products.read'], SaldiPermissionService::effective($ctx, $this->env->db));
        self::assertFalse(SaldiPermissionService::revenueHidden($ctx, $this->env->db));
        $sys = SaldiTenantContext::forSystem('local', 5, $this->env->name, 'postgresql', 'system:worker', 4, 'DKK');
        self::assertSame(['company.read'], SaldiPermissionService::effective($sys, $this->env->db));
        SaldiPermissionService::setRoutineLookup(fn($name) => $name === 'low_stock' ? 'journal.read' : null);
        self::assertSame(['company.read', 'journal.read'], SaldiPermissionService::effective($ctx, $this->env->db));
        $this->error('PERMISSION_DENIED', fn() => SaldiTenantContext::forSystem('local', 5, $this->env->name, 'postgresql', 'user:1', 4, 'DKK'));
    }

    public function testSettingsDefaultLatestGlobalRowAndAdminWrites(): void
    {
        $db = $this->env->db;
        self::assertSame('off', SaldiSettings::get($db, 'assistant', 'tools_enabled'));
        $db->insert('settings', ['var_grp' => 'assistant', 'var_name' => 'tools_enabled', 'var_value' => 'on', 'user_id' => 910001]);
        self::assertSame('off', SaldiSettings::get($db, 'assistant', 'tools_enabled'));
        SaldiSettings::set($db, $this->env->ctx(), 'assistant', 'tools_enabled', 'on');
        SaldiSettings::set($db, $this->env->ctx(), 'assistant', 'tools_enabled', 'off');
        self::assertSame('off', SaldiSettings::get($db, 'assistant', 'tools_enabled'));
        self::assertSame(1, (int)$db->value("SELECT COUNT(*) FROM settings WHERE var_grp = 'assistant' AND var_name = 'tools_enabled' AND user_id IS NULL"));
        $this->error('PERMISSION_DENIED', fn() => SaldiSettings::set($db, $this->env->ctx(910001, '2'), 'assistant', 'tools_enabled', 'on'));
        $this->error('PERMISSION_DENIED', fn() => SaldiSettings::set($db, $this->env->ctx(910001, null, 'agent:saldi-assist', 1, ['read', 'draft']), 'assistant', 'tools_enabled', 'on'));
        $this->error('VALIDATION_ERROR', fn() => SaldiSettings::set($db, $this->env->ctx(), 'assistant', 'unknown', 'on'));
    }
}
