<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiInsightDbTestCase.php --- ai/mcp-assistant --- 2026.10.04
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
// Realistic isolated fixtures for B10 insights and master-data findings.
//
// 20261004 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';
require_once __DIR__ . '/../../../includes/tools/SaldiInsightTools.php';

abstract class SaldiInsightDbTestCase extends SaldiRuntimeDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->env->db;
        foreach (
            ['transaktioner', 'kassekladde', 'kladdeliste', 'openpost', 'ordrelinjer', 'ordrer',
            'adresser', 'kontoplan', 'varer', 'regulering', 'batch_kob', 'report', 'pos_betalinger'] as $table
        ) {
            $db->exec('DELETE FROM ' . $table);
        }
        $db->exec("UPDATE grupper SET box1='01',box2='2026',box3='12',box4='2026' WHERE art='RA' AND kodenr=4");
        $db->exec("DELETE FROM grupper WHERE art IN ('DG','KG','SM','KM','EM','YM','MR')");
        foreach (['DG', 'KG'] as $art) {
            $db->insert('grupper', ['art' => $art, 'kodenr' => 1, 'fiscal_year' => 4, 'box8' => 'on']);
            $db->insert('grupper', ['art' => $art, 'kodenr' => 2, 'fiscal_year' => 4, 'box8' => '']);
        }
        foreach (
            [1000 => ['S', 'Kasse', '100.305', 0], 1100 => ['S', 'Bank', '0', 0],
            1999 => ['Z', 'Likvider', '999', 1000], 2000 => ['D', 'Omsætning', '0', 0],
            2100 => ['D', 'Varekøb', '0', 0], 2999 => ['Z', 'Drift', '999', 2000],
            3000 => ['H', 'Overskrift', '999', 0], 4000 => ['S', 'Uændret', '0', 0],
            6500 => ['S', 'Salgsmoms', '0', 0], 6600 => ['S', 'Købsmoms', '0', 0]] as $number => $row
        ) {
            $db->insert('kontoplan', ['kontonr' => $number, 'beskrivelse' => $row[1],
                'kontotype' => $row[0], 'primo' => $row[2], 'fra_kto' => $row[3], 'regnskabsaar' => 4]);
        }
        foreach (
            [[1000, '2026-01-10', '10.005', '0'], [1000, '2026-04-10', '30.005', '0'],
            [2000, '2026-01-10', '0', '100'], [2000, '2026-04-10', '0', '175'],
            [1000, '2025-12-31', '900', '0'], [6500, '2026-04-10', '0', '25'],
            [6600, '2026-04-10', '10', '0']] as $row
        ) {
            $db->insert('transaktioner', ['kontonr' => $row[0], 'transdate' => $row[1],
                'debet' => $row[2], 'kredit' => $row[3], 'bilag' => 1]);
        }
        foreach ([['SM', 'S', 6500], ['KM', 'K', 6600]] as $group) {
            $db->insert('grupper', ['art' => $group[0], 'kode' => $group[1], 'kodenr' => 1,
                'box1' => (string)$group[2], 'box2' => '25', 'fiscal_year' => 4]);
        }
        $db->insert('grupper', ['art' => 'MR', 'box1' => '6500', 'box2' => '6600']);
        foreach (
            [10 => ['D', 'Nord Handel ApS', 'DK 1234-5678', 'kunde@example.test', 1, ''],
            11 => ['D', ' nord  handel aps ', '87654321', 'kontakt@example.test', 1, ''],
            20 => ['K', 'Leverandør ApS', '12345678', '', 1, ''],
            30 => ['D', 'Erhvervskunde', '', '', 1, ''],
            31 => ['D', 'Privatkunde', '', 'privat@example.test', 2, ''],
            40 => ['K', 'Lukket leverandør', '24682468', 'lukket@example.test', 99, 'on']] as $id => $row
        ) {
            $db->insert('adresser', ['id' => $id, 'kontonr' => $id * 100, 'art' => $row[0],
                'firmanavn' => $row[1], 'cvrnr' => $row[2], 'email' => $row[3], 'gruppe' => $row[4],
                'lukket' => $row[5], 'bank_konto' => 'private-field']);
        }
        foreach (
            [1 => ['Kaffe', '8.000', '12.000', ''], 2 => ['Kop', '0', '10', ''],
            3 => ['Restvare', '15', '10', 'on']] as $id => $row
        ) {
            $db->insert('varer', ['id' => $id, 'varenr' => 'V' . $id, 'beskrivelse' => $row[0],
                'kostpris' => $row[1], 'salgspris' => $row[2], 'lukket' => $row[3], 'beholdning' => '10']);
        }
        $db->insert('kladdeliste', ['id' => 11, 'kladdedate' => '2026-06-10', 'kladdenote' => 'Kassekladde',
            'oprettet_af' => 'Test', 'bogfort' => '-']);
        $db->insert('kassekladde', ['kladde_id' => 11, 'transdate' => '2026-06-10', 'd_type' => 'K',
            'debet' => '4000', 'k_type' => 'F', 'kredit' => '1000', 'amount' => '20', 'pos' => 1]);
        $db->insert('ordrer', ['id' => 90, 'konto_id' => 40, 'art' => 'KO', 'status' => 1]);
        $db->insert('ordrelinjer', ['ordre_id' => 90, 'vare_id' => 3, 'antal' => '1']);
        foreach (
            [1 => ['2026-01-10', '', null], 2 => ['2026-06-10', '1', '2027-01-02'],
            3 => ['2026-06-10', '1', '2026-12-31'], 4 => ['2027-01-01', '', null]] as $id => $row
        ) {
            $db->insert('openpost', ['id' => $id, 'konto_id' => 10, 'konto_nr' => 1000,
                'faktnr' => 'F' . $id, 'transdate' => $row[0], 'forfaldsdate' => '2026-12-31',
                'udlignet' => $row[1], 'udlign_date' => $row[2], 'amount' => '100.005', 'valuta' => 'DKK']);
        }
        $db->insert('openpost', ['id' => 5, 'konto_id' => 20, 'konto_nr' => 2000,
            'faktnr' => 'K5', 'transdate' => '2026-06-10', 'amount' => '-20', 'valuta' => 'EUR',
            'udlignet' => '', 'valutakurs' => '750']);
        foreach (
            [[51, 1, '100', '25', 0, 'Kontant'], [52, 1, '-20', '-5', 51, 'Kontant'],
            [53, 2, '40', '10', 0, 'Kontant']] as $row
        ) {
            $db->insert('ordrer', ['id' => $row[0], 'konto_id' => 10, 'art' => 'PO', 'status' => 4,
                'felt_5' => (string)$row[1], 'fakturadate' => '2026-06-10', 'sum' => $row[2],
                'moms' => $row[3], 'kred_ord_id' => $row[4], 'felt_1' => $row[5], 'valuta' => 'DKK']);
        }
        foreach (
            [[51, 'Kontant', '100'], [51, 'Betalingskort|Visa', '50'],
            [52, 'Kontant', '-25'], [53, 'Kontant', '100']] as $payment
        ) {
            $db->insert('pos_betalinger', ['ordre_id' => $payment[0], 'betalingstype' => $payment[1],
                'amount' => $payment[2], 'valuta' => 'DKK']);
        }
        foreach ([1 => ['150', '145', '-5'], 2 => ['60', '62', '2']] as $till => $values) {
            $summary = $till === 1 ? ['14', '100', '100', '50'] : ['6', '50', '10', '50'];
            $db->insert('report', ['report_number' => 100 + $till, 'date' => '2026-06-10',
                'type' => 'Head line', 'description' => 'Cash count, box ' . $till, 'total' => '0']);
            foreach (
                ['10 kr', 'Dagens omsætning:', 'Morgenbeholdning', 'Dagens tilgang:',
                'Forventet beholdning DKK:', 'Optalt beholdning DKK:', 'Difference DKK:'] as $i => $label
            ) {
                $total = $i < 4 ? $summary[$i] : $values[$i - 4];
                $db->insert('report', ['report_number' => 100 + $till, 'date' => '2026-06-10',
                    'type' => 'cashCount', 'description' => $label, 'total' => $total]);
            }
        }
        $db->insert('transaktioner', ['kontonr' => 1000, 'transdate' => '2026-06-10',
            'beskrivelse' => 'Kassedifference, kasse 1', 'kasse_nr' => 1, 'debet' => '0',
            'kredit' => '5', 'bilag' => 99, 'report_number' => 101]);
        foreach (['2026-06-09' => '1', '2026-06-10' => '0'] as $date => $posted) {
            $epoch = str_replace('-', '', $date) . '235959';
            $db->insert('regulering', ['vare_id' => 1, 'lager' => 1, 'variant_id' => 0,
                'tidspkt' => $epoch, 'beholdning' => '10.500', 'optalt' => '9.250', 'bogfort' => $posted]);
        }
        foreach (
            [['2026-06-09', '2'], ['2026-07-10', '3'], ['2026-07-11', '1'],
            ['2026-06-11', '0'], [null, '5']] as $batch
        ) {
            $db->insert('batch_kob', ['vare_id' => 1, 'rest' => $batch[1],
                'due_date' => $batch[0], 'kobsdate' => '2026-01-01']);
        }
        $this->setting('items', 'batchExpiryEnabled', 'on');
        $this->setting('finans', 'hideRevenue', 'off');
    }

    protected function setting(string $group, string $name, string $value): void
    {
        $this->env->db->insert('settings', ['var_grp' => $group, 'var_name' => $name,
            'var_value' => $value, 'user_id' => null, 'pos_id' => null]);
    }

    protected function validateData(string $tool, array $result): void
    {
        $schema = SaldiToolRegistry::default()->get($tool)->outputSchema()['properties']['data'];
        SaldiSchemaValidator::validate($result['data'], $schema);
        self::assertGreaterThanOrEqual(0, $result['result_count']);
    }

    protected function hiddenContext(): SaldiTenantContext
    {
        $this->setting('finans', 'hideRevenue', 'on');
        return $this->env->ctx(910001, '1011111111111111');
    }
}
