<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiWatchDbTestCase.php --- ai/mcp-assistant --- 2026.10.02
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

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiWatchTools.php';

abstract class SaldiWatchDbTestCase extends SaldiRuntimeDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (
            ['inbox_items', 'automation_state', 'documents', 'pool_files', 'report', 'batch_kob',
            'kassekladde', 'kladdeliste', 'transaktioner', 'kontoplan', 'openpost', 'adresser', 'varer'] as $table
        ) {
            $this->env->db->exec('DELETE FROM ' . $table);
        }
        $this->env->db->exec("DELETE FROM settings WHERE var_grp IN ('assistant','finans','items','lager')");
        $this->env->db->exec("DELETE FROM grupper WHERE art IN ('SM','KM','EM','YM')");
        $this->env->db->exec(
            "UPDATE grupper SET box1 = '01', box2 = '2026', box3 = '12', box4 = '2026', " .
            "box5 = 'on', box10 = '' WHERE art = 'RA' AND kodenr = ?",
            [4]
        );
        foreach ([1000 => 'SM1', 2000 => ''] as $account => $vat) {
            $this->env->db->insert('kontoplan', ['kontonr' => $account, 'regnskabsaar' => 4,
                'kontotype' => 'S', 'moms' => $vat, 'beskrivelse' => 'Fixture ' . $account]);
        }
    }

    protected function setting(string $group, string $name, string $value): void
    {
        $this->env->db->insert('settings', ['var_grp' => $group, 'var_name' => $name,
            'var_value' => $value, 'user_id' => null, 'pos_id' => null]);
    }

    protected function journal(int $id = 70001, string $date = '2026-09-01', string $posted = '-'): void
    {
        $this->env->db->insert('kladdeliste', ['id' => $id, 'kladdedate' => $date,
            'kladdenote' => 'Fixture journal', 'bogfort' => $posted]);
    }

    protected function line(int $id = 70001, array $overrides = []): void
    {
        $this->env->db->insert('kassekladde', array_replace(['id' => $id, 'kladde_id' => 70001,
            'bilag' => $id, 'transdate' => '2026-09-01', 'beskrivelse' => 'Fixture expense',
            'd_type' => 'F', 'debet' => '1000', 'k_type' => 'F', 'kredit' => '2000',
            'amount' => '125.005', 'dokument' => ''], $overrides));
    }

    protected function supplier(int $id = 70001, array $overrides = []): void
    {
        $this->env->db->insert('adresser', array_replace(['id' => $id, 'art' => 'K',
            'kontonr' => (string)$id, 'firmanavn' => 'Fixture supplier ' . $id, 'lukket' => '',
            'bank_navn' => 'Fixture bank', 'bank_reg' => '', 'bank_konto' => 'fixture-old',
            'bank_fi' => '', 'swift' => '', 'iban' => ''], $overrides));
    }

    protected function onlyPermission(int $position, string $level = '2'): SaldiTenantContext
    {
        $rights = str_repeat('0', 16);
        $rights[$position] = $level;
        return $this->env->ctx(910001, $rights);
    }

    protected function rule(string $name): SaldiTenantContext
    {
        $ctx = $this->env->ctx();
        return SaldiTenantContext::forSystem(
            $ctx->installId,
            $ctx->regnskabId,
            $ctx->db,
            $ctx->dbType,
            'rule:' . $name,
            $ctx->fiscalYear,
            $ctx->baseCurrency
        );
    }

    protected function closureTable(): void
    {
        $this->env->db->exec('CREATE TABLE moms_periode_luk (id serial PRIMARY KEY, kalender_aar integer, ' .
            'kalender_maaned integer, status varchar(10))');
    }
}
