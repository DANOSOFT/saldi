<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiPostingTestCase.php --- ai/mcp-assistant --- 2026.10.02
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
// Synthetic suppliers, finance accounts and journal history in disposable clones.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
require_once __DIR__ . '/../../../includes/automation/SaldiPostingSuggester.php';
require_once __DIR__ . '/../../../includes/tools/SaldiRuleTools.php';
require_once __DIR__ . '/../../../includes/automation/SaldiJobRunner.php';

abstract class SaldiPostingTestCase extends SaldiRuntimeDbTestCase
{
    private int $lineId = 100000;

    protected function setUp(): void
    {
        parent::setUp();
        $database = $this->env->db;
        foreach (
            ['kassekladde', 'tmpkassekl', 'kladdeliste', 'posting_rules', 'assist_events',
            'inbox_items', 'decision_log', 'adresser', 'kontoplan'] as $table
        ) {
            $database->exec('DELETE FROM ' . $table);
        }
        $this->lineId = 100000;
        foreach (
            [2800 => ['D', 'Software', 'K1'], 2900 => ['D', 'Kontorartikler', 'K1'],
            3100 => ['D', 'El og varme', 'K1'], 3200 => ['D', 'Øvrige omkostninger', ''],
            5500 => ['S', 'Bank', ''], 5600 => ['S', 'Inventar', 'K1'],
            5900 => ['H', 'Overskrift', ''], 5999 => ['S', 'Lukket', '']] as $number => $values
        ) {
            $database->insert('kontoplan', [
                'id' => $number, 'kontonr' => $number, 'regnskabsaar' => 4,
                'kontotype' => $values[0], 'beskrivelse' => $values[1], 'moms' => $values[2],
                'lukket' => $number === 5999 ? 'on' : '',
            ]);
        }
        $database->insert('kontoplan', [
            'id' => 6000, 'kontonr' => 6000, 'regnskabsaar' => 3, 'kontotype' => 'S',
            'beskrivelse' => 'Andet regnskabsår', 'lukket' => '',
        ]);
        foreach (
            [1005 => 'Adobe', 1006 => 'Kontor Nord', 1007 => 'Strøm Øst',
            1008 => 'Dansk Tele', 1009 => 'Leje Danmark', 1010 => 'Reklamebureau',
            1011 => 'Service Syd'] as $number => $name
        ) {
            $this->party($number, $name);
        }
        $this->party(2005, 'Kunde Æble', 'D');
        $this->party(2006, 'Kunde Øst', 'D');
        $database->exec("DELETE FROM grupper WHERE art IN ('KM', 'SM', 'EM', 'YM') AND kodenr = 1 AND fiscal_year = 4");
        foreach (['KM', 'SM', 'EM', 'YM'] as $art) {
            $database->insert('grupper', ['art' => $art, 'kodenr' => 1, 'fiscal_year' => 4]);
        }
        foreach ([41 => 'V', 42 => '-', 43 => 'V'] as $id => $posted) {
            $database->insert('kladdeliste', [
                'id' => $id, 'kladdedate' => $this->date(), 'bogfort' => $posted,
                'kladdenote' => 'Syntetisk kladde',
            ]);
        }
        $database->exec("DELETE FROM settings WHERE var_grp = 'assistant' AND var_name = 'journal_suggestions'");
        $database->exec("DELETE FROM settings WHERE var_grp = 'finans' AND var_name = 'hideRevenue'");
    }

    protected function party(int $number, string $name, string $type = 'K'): void
    {
        $this->env->db->insert('adresser', [
            'id' => $number, 'kontonr' => $number, 'art' => $type,
            'firmanavn' => $name, 'lukket' => '',
        ]);
    }

    protected function date(int $days = 0): string
    {
        return (new DateTimeImmutable(SaldiToolRuntime::today()))->modify($days . ' days')->format('Y-m-d');
    }

    protected function line(array $overrides = []): int
    {
        $row = array_merge([
            'id' => ++$this->lineId, 'kladde_id' => 41, 'bilag' => $this->lineId - 100000,
            'transdate' => $this->date(-5), 'beskrivelse' => 'Adobe abonnement',
            'd_type' => 'F', 'debet' => '2800', 'k_type' => 'K', 'kredit' => '1005',
            'amount' => '1250.000', 'faktura' => 'INV-' . $this->lineId, 'momsfri' => '',
            'debetvat' => 'K1', 'kreditvat' => '',
        ], $overrides);
        foreach (['debetvat', 'kreditvat'] as $column) {
            if (!$this->env->db->hasColumn('kassekladde', $column)) {
                unset($row[$column]);
            }
        }
        return $this->env->db->insert('kassekladde', $row);
    }

    protected function repeat(int $count, array $overrides = []): array
    {
        $ids = [];
        for ($index = 0; $index < $count; $index++) {
            $ids[] = $this->line($overrides + ['transdate' => $this->date(-5 - $index)]);
        }
        return $ids;
    }

    protected function rule(array $input = []): array
    {
        return $this->approve($this->inbox()->create($this->env->ctx(), 'rule.posting', array_merge([
            'match' => ['type' => 'supplier', 'value' => '1005'], 'account' => '2800',
        ], $input), ['evidence' => ['explanation' => 'Godkendt syntetisk regel.']]));
    }

    protected function suggest(array $line = [], ?SaldiTenantContext $context = null): array
    {
        return (new SaldiPostingSuggester($context ?? $this->env->ctx(), $this->env->db))->suggest(array_merge([
            'description' => 'Adobe abonnement',
        ], $line));
    }

    protected function setting(string $group, string $name, string $value): void
    {
        $this->env->db->exec('DELETE FROM settings WHERE var_grp = ? AND var_name = ?', [$group, $name]);
        $this->env->db->insert('settings', [
            'var_grp' => $group, 'var_name' => $name, 'var_value' => $value, 'user_id' => null,
        ]);
    }

    protected function restrictedContext(): SaldiTenantContext
    {
        $rights = str_repeat('1', 16);
        $rights[1] = '2';
        return $this->env->ctx(910001, $rights);
    }
}
