<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiSalesTestCase.php --- ai/mcp-assistant --- 2026.10.04
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
// Synthetic sales fixtures in disposable plan_tpl clones only.
//
// 20261004 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';

abstract class SaldiSalesTestCase extends SaldiRuntimeDbTestCase
{
    protected int $customer;
    protected int $product;

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->env->db;
        foreach (
            ['inbox_items', 'ordrelinjer', 'ordrer', 'batch_salg', 'batch_kob', 'openpost',
            'kassekladde', 'transaktioner', 'documents', 'kontakt_emails', 'adresser', 'varer',
            'kontoplan', 'rabat', 'varetilbud', 'variant_varer'] as $table
        ) {
            $db->exec('DELETE FROM ' . $table);
        }
        // plan_tpl predates the legacy performed_by migration; repair this clone only.
        if (!$db->hasColumn('ordrer', 'performed_by')) {
            $db->exec('ALTER TABLE ordrer ADD COLUMN performed_by text');
        }
        $db->exec("DELETE FROM grupper WHERE art IN ('DG','VG','SM','DRG','DVRG','VK','AFD','LG')");
        $db->exec("UPDATE grupper SET box1='01', box2='2026', box3='12', box4='2026' " .
            "WHERE art='RA' AND kodenr=4");
        foreach (
            [['DG', 1, ['box1' => 'S1', 'box3' => 'DKK']],
            ['VG', 1, ['box4' => '2000']], ['SM', 1, ['box1' => '6900', 'box2' => '25']]] as [$art, $n, $boxes]
        ) {
            $this->groupRow($art, $n, $boxes);
        }
        $db->insert('kontoplan', ['kontonr' => 2000, 'regnskabsaar' => 4, 'moms' => 'S1',
            'kontotype' => 'D', 'beskrivelse' => 'Test salg']);
        $this->customer = $db->insert('adresser', ['art' => 'D', 'kontonr' => '1000',
            'firmanavn' => 'Test kunde ÆØÅ', 'gruppe' => 1, 'rabatgruppe' => 0, 'lukket' => '',
            'email' => 'kunde@example.invalid', 'tlf' => '123456789012345',
            'betalingsbet' => 'Netto', 'betalingsdage' => 8, 'notes' => 'Kortnote',
            'lev_firmanavn' => 'Levering', 'lev_addr1' => 'Testvej 2', 'pbs_nr' => 12]);
        $this->product = $db->insert('varer', ['varenr' => 'P1', 'beskrivelse' => 'Testvare ÆØÅ',
            'gruppe' => 1, 'enhed' => 'stk', 'salgspris' => '100.125', 'tier_price' => '80.000',
            'kostpris' => '20.000', 'lukket' => '', 'rabatgruppe' => 0, 'dvrg' => 0,
            'special_price' => '60.000', 'specialtype' => 'price', 'special_from_date' => '2026-01-01',
            'special_to_date' => '2026-12-31', 'special_from_time' => '00:00:00',
            'special_to_time' => '23:59:59', 'campaign_cost' => '10.000']);
    }

    protected function groupRow(string $art, int $number, array $boxes = []): int
    {
        return $this->env->db->insert('grupper', $boxes + ['art' => $art, 'kodenr' => $number,
            'fiscal_year' => 4, 'box1' => '', 'box2' => '', 'box3' => '', 'box4' => '',
            'box6' => '', 'box7' => '', 'box8' => '', 'box9' => '']);
    }

    protected function sales(array $line = [], string $type = 'offer'): array
    {
        return ['customer_account' => '1000', 'type' => $type,
            'lines' => [$line + ['product_id' => $this->product, 'quantity' => '2.500']],
            'order_date' => '2026-06-10'];
    }

    protected function inserted(array $item): array
    {
        return $this->env->db->all(
            'SELECT * FROM ordrelinjer WHERE ordre_id = ? ORDER BY posnr,id',
            [$item['result']['order_id']]
        );
    }

    protected function invoice(): array
    {
        $item = $this->approve($this->inbox()->create(
            $this->env->ctx(),
            'sales_order.draft',
            $this->sales(['unit_price' => '125.005', 'discount_pct' => '12.500'])
        ));
        $id = $item['result']['order_id'];
        $this->env->db->exec("UPDATE ordrer SET status=3, fakturanr='INV-1', " .
            "fakturadate='2026-06-10', betalt='1' WHERE id=?", [$id]);
        $this->env->db->insert('ordrelinjer', ['ordre_id' => $id, 'posnr' => 2,
            'beskrivelse' => 'Tekstlinje']);
        return ['id' => $id, 'line_id' => (int)$this->inserted($item)[0]['id']];
    }

    protected function workers(string $kind, array $input): array
    {
        $workers = [];
        for ($i = 0; $i < 2; $i++) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, __DIR__ . '/SaldiMasterDraftWorker.php',
                $this->env->name, (string)$this->env->regnskab['id'], $kind,
                json_encode($input, JSON_THROW_ON_ERROR)], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $workers[] = [$process, $pipes];
        }
        $results = [];
        foreach ($workers as [$process, $pipes]) {
            $output = trim(stream_get_contents($pipes[1]));
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $errors);
            $rows = explode("\n", $output);
            $results[] = json_decode(end($rows), true, 512, JSON_THROW_ON_ERROR);
        }
        return $results;
    }
}
