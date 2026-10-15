<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiPurchaseTestCase.php --- ai/mcp-assistant --- 2026.10.04
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
// Disposable purchase fixtures; every write stays in a plan_tpl test clone.
//
// 20261004 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiPurchaseTools.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';
require_once __DIR__ . '/../../../includes/mcp/SaldiMcpServer.php';
require_once __DIR__ . '/../../../includes/automation/routines/SaldiReorderSuggestionsRoutine.php';

abstract class SaldiPurchaseTestCase extends SaldiRuntimeDbTestCase
{
    protected int $supplier;
    protected int $product;
    protected int $order;
    protected int $line;
    protected int $secondLine;

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->env->db;
        foreach (
            ['ordrelinjer', 'ordrer', 'batch_kob', 'batch_salg', 'vare_lev', 'varer',
            'adresser', 'pool_files', 'inbox_items', 'decision_log'] as $table
        ) {
            $db->exec('DELETE FROM ' . $table);
        }
        $db->exec("UPDATE grupper SET box1 = '1', box2 = '2026', box3 = '12', box4 = '2026', "
            . "box5 = 'on', box10 = '' WHERE art = 'RA' AND kodenr = 4");
        $db->exec("DELETE FROM grupper WHERE art = 'VG'");
        $db->insert('grupper', ['art' => 'VG', 'kodenr' => 1, 'fiscal_year' => 4, 'box8' => 'on']);
        $this->supplier = $db->insert('adresser', ['art' => 'K', 'kontonr' => '2000',
            'firmanavn' => 'Testleverandør', 'lukket' => '']);
        $this->product = $this->product();
        $db->insert('vare_lev', ['vare_id' => $this->product, 'lev_id' => $this->supplier,
            'posnr' => 1, 'lev_varenr' => 'SUP-P1', 'kostpris' => '17.125']);
        $this->order = $this->order();
        $this->line = $this->line();
        $this->secondLine = $this->line(['varenr' => 'P2', 'beskrivelse' => 'Anden vare',
            'antal' => '8.000', 'leveres' => '1.500', 'pris' => '2.005', 'posnr' => 2]);
    }

    protected function product(array $values = []): int
    {
        return $this->env->db->insert('varer', $values + ['varenr' => 'P1',
            'beskrivelse' => 'Testvare', 'gruppe' => 1, 'lukket' => '', 'beholdning' => '2.000',
            'min_lager' => '3.000', 'colli' => '6.000', 'kostpris' => '17.125']);
    }

    protected function order(array $values = []): int
    {
        return $this->env->db->insert('ordrer', $values + ['art' => 'KO', 'status' => 1,
            'konto_id' => $this->supplier, 'kontonr' => '2000', 'firmanavn' => 'Testleverandør',
            'ordrenr' => 51, 'kundeordnr' => 'REF-51', 'ordredate' => '2026-01-01',
            'valuta' => 'DKK', 'sum' => '100.000', 'moms' => '25.000', 'fakturanr' => null,
            'levdate' => null, 'hvem' => '', 'tidspkt' => '0']);
    }

    protected function line(array $values = []): int
    {
        return $this->env->db->insert('ordrelinjer', $values + ['ordre_id' => $this->order,
            'vare_id' => $this->product, 'varenr' => 'P1', 'lev_varenr' => 'SUP-P1',
            'beskrivelse' => 'Testvare', 'antal' => '10.000', 'pris' => '10.125',
            'leveres' => null, 'leveret' => '0.000', 'posnr' => 1]);
    }

    protected function stage(array $values = []): array
    {
        return $values + ['order_id' => $this->order, 'invoice_number' => 'INV-51',
            'invoice_date' => '2026-06-10', 'lines' => [
                ['line_id' => $this->line, 'receive_quantity' => '4.125', 'unit_price' => '11.005'],
            ]];
    }

    protected function receipt(int $line, string $quantity = '2.000'): int
    {
        return $this->env->db->insert('batch_kob', ['ordre_id' => $this->order,
            'linje_id' => $line, 'vare_id' => $this->product, 'antal' => $quantity,
            'rest' => $quantity, 'pris' => '10.125', 'kobsdate' => '2026-06-01']);
    }

    protected function sale(string $quantity, array $orderValues = [], ?string $date = null): int
    {
        $id = $this->order($orderValues + ['art' => 'DO', 'status' => 3]);
        return $this->env->db->insert('batch_salg', ['ordre_id' => $id,
            'vare_id' => $this->product, 'antal' => $quantity,
            'salgsdate' => $date ?? SaldiToolRuntime::today()]);
    }

    /** @return array<string,list<array>> Every relevant row, including protected stock and supplier costs. */
    protected function snapshot(): array
    {
        $rows = [];
        foreach (['ordrer', 'ordrelinjer', 'batch_kob', 'batch_salg', 'varer', 'vare_lev'] as $table) {
            $rows[$table] = $this->env->db->all('SELECT * FROM ' . $table . ' ORDER BY id');
        }
        return $rows;
    }

    /** @return array MCP structured content, verified against the whole public output contract. */
    protected function toolCall(string $name, array $args): array
    {
        $this->env->enable();
        $server = new SaldiMcpServer(null, fn(): array => ['ctx' => $this->env->ctx(), 'db' => $this->env->db]);
        $response = $server->handle([
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json',
                'Authorization' => 'Bearer smcp1.local.' . bin2hex(random_bytes(32))],
            'body' => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => (object)$args]], JSON_THROW_ON_ERROR),
        ]);
        self::assertSame(200, $response['status']);
        $result = $response['body']['result']['structuredContent'];
        SaldiSchemaValidator::validate($result, SaldiToolRegistry::default()->get($name)->outputSchema());
        return $result;
    }

    protected function validateOutput(string $tool, array $result): void
    {
        $schema = SaldiToolRegistry::default()->get($tool)->outputSchema()['properties']['data'];
        SaldiSchemaValidator::validate($result['data'], $schema);
    }
}
