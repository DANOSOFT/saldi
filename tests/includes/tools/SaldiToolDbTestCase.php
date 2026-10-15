<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiToolDbTestCase.php --- ai/mcp-assistant --- 2026.10.01
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
// Seeded disposable tenant base for tools and MCP integration tests.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';
require_once __DIR__ . '/../../../includes/mcp/SaldiMcpServer.php';

abstract class SaldiToolDbTestCase extends SaldiRuntimeDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->env->db;
        foreach (
            ['transaktioner',
                'kassekladde',
                'tmpkassekl',
                'kladdeliste',
                'openpost',
                'ordrelinjer',
                'ordrer',
                'adresser',
                'kontoplan',
                'varer'] as $table
        ) {
            $db->exec('DELETE FROM ' . $table);
        }
        $db->exec("UPDATE grupper SET box1='01', box2='2026', box3='12', box4='2026', box5='on" .
            "', box10='' WHERE art='RA' AND kodenr=4");
        foreach (
            [1000 => ['S',
                    'Cash',
                    '100.305'],
                2000 => ['D',
                    'Revenue',
                    '0'],
                3000 => ['H',
                    'Header',
                    '0'],
                4000 => ['S',
                    'Zero',
                    '0']] as $account => [$type,
                $name,
                $primo]
        ) {
            $db->insert(
                'kontoplan',
                ['id' => $account,
                    'kontonr' => $account,
                    'beskrivelse' => $name,
                    'kontotype' => $type,
                    'regnskabsaar' => 4,
                    'primo' => $primo,
                    'lukket' => '',
                'moms' => 'U25']
            );
        }
        foreach (
            [10 => ['D',
                    'Debtor Alpha'],
                20 => ['K',
                    'Creditor Beta'],
                30 => ['S',
                    'Own Alpha Company'],
                40 => ['D',
                    'Closed Alpha']] as $id => [$art,
                $name]
        ) {
            $db->insert(
                'adresser',
                ['id' => $id,
                    'kontonr' => (string)($id * 100),
                    'art' => $art,
                    'firmanavn' => $name,
                    'cvrnr' => '12345678',
                    'addr1' => 'Road 1',
                    'addr2' => '',
                    'postnr' => '1000',
                    'bynavn' => 'Copenhagen',
                    'land' => 'DK',
                    'ean' => '1234567890123',
                    'email' => 'fixture@example.test',
                    'tlf' => '12345678',
                    'betalingsdage' => 14,
                    'kreditmax' => '999.995',
                    'lukket' => $id === 40 ? 'on' : '',
                    'bank_konto' => 'never-export',
                'notes' => 'never-export-notes']
            );
        }
        foreach ([11 => '-', 12 => 'S', 13 => 'V'] as $id => $status) {
            $db->insert(
                'kladdeliste',
                ['id' => $id,
                    'kladdedate' => '2026-01-01',
                    'kladdenote' => 'Fixture journal ' . $id,
                    'bogfort' => $status,
                    'oprettet_af' => 'Fixture',
                    'hvem' => '',
                'tidspkt' => '']
            );
        }
        $db->insert(
            'kassekladde',
            ['id' => 111,
                'kladde_id' => 11,
                'bilag' => 7,
                'transdate' => '2026-06-10',
                'beskrivelse' => 'Fixture line',
                'd_type' => 'F',
                'debet' => '1000',
                'k_type' => 'F',
                'kredit' => '2000',
                'amount' => '12.345',
                'valuta' => 0,
                'valutakurs' => '100',
            'pos' => 1]
        );
        foreach (
            [['2026-02-01',
                    '20.005',
                    '5.000'],
                ['2026-06-10',
                    '10.005',
                    '2.000'],
                ['2026-07-01',
                    '3.000',
                    '1.005'],
                ['2025-06-01',
                    '999',
                    '0'],
                ['2026-09-01',
                    '888',
                    '0']] as $i => [$date,
                $debit,
                $credit]
        ) {
            $db->insert(
                'transaktioner',
                ['id' => 201 + $i,
                    'kontonr' => 1000,
                    'bilag' => 7,
                    'transdate' => $date,
                    'beskrivelse' => 'Fixture transaction',
                    'debet' => $debit,
                    'kredit' => $credit,
                'kladde_id' => 11]
            );
        }
        $db->insert(
            'transaktioner',
            ['id' => 210,
                'kontonr' => 2000,
                'transdate' => '2026-06-10',
                'debet' => '0',
            'kredit' => '50']
        );
        foreach (
            [1 => [10,
                    '100.005',
                    'DKK',
                    '100',
                    '',
                    '2026-01-15'],
                2 => [10,
                    '20.005',
                    'EUR',
                    '750',
                    '',
                    '2026-01-15'],
                3 => [10,
                    '999',
                    'DKK',
                    '100',
                    '1',
                    '2026-01-15'],
                4 => [20,
                    '80',
                    'DKK',
                    '100',
                    '',
                    '2026-01-15'],
                5 => [30,
                    '777',
                    'DKK',
                    '100',
                    '',
                    '2026-01-15'],
                6 => [10,
                    '25',
                    'DKK',
                    '100',
                    '',
                    '2099-01-01']] as $id => [$party,
                $amount,
                $currency,
                $rate,
                $settled,
                $due]
        ) {
            $db->insert(
                'openpost',
                ['id' => $id,
                    'konto_id' => $party,
                    'konto_nr' => (string)($party * 100),
                    'faktnr' => 'F-' . $id,
                    'amount' => $amount,
                    'valuta' => $currency,
                    'valutakurs' => $rate,
                    'udlignet' => $settled,
                    'transdate' => '2026-01-01',
                'forfaldsdate' => $due]
            );
        }
        foreach (
            [51 => ['DO',
                    10,
                    3],
                52 => ['KO',
                    20,
                    1],
                53 => ['DK',
                    10,
                    0],
                54 => ['KK',
                    20,
                    3]] as $id => [$art,
                $party,
                $status]
        ) {
            $db->insert(
                'ordrer',
                ['id' => $id,
                    'art' => $art,
                    'konto_id' => $party,
                    'kontonr' => (string)($party * 100),
                    'firmanavn' => 'Fixture party',
                    'ordrenr' => $id,
                    'fakturanr' => (string)($id + 100),
                    'ordredate' => '2026-06-01',
                    'fakturadate' => '2026-06-15',
                    'due_date' => '2026-06-29',
                    'betalingsdage' => 14,
                    'sum' => '10.005',
                    'moms' => '2.005',
                    'valuta' => 'DKK',
                    'valutakurs' => '100',
                    'status' => $status,
                    'betalt' => '',
                    'ref' => 'Fixture ref',
                'notes' => 'never-export-order-notes']
            );
            $db->insert(
                'ordrelinjer',
                ['ordre_id' => $id,
                    'posnr' => 1,
                    'varenr' => 'V1',
                    'beskrivelse' => 'Fixture product',
                    'antal' => '2.000',
                    'pris' => '5.005',
                    'rabat' => '10.000',
                    'momssats' => '25.000',
                'bogf_konto' => '2000']
            );
        }
        foreach ([61 => [2, 5], 62 => [10, 5], 63 => [0, 0]] as $id => [$stock, $minimum]) {
            $db->insert(
                'varer',
                ['id' => $id,
                    'varenr' => 'V' . $id,
                    'beskrivelse' => 'Fixture product',
                    'enhed' => 'pcs',
                    'salgspris' => '12.345',
                    'kostpris' => '4.005',
                    'beholdning' => $stock,
                    'min_lager' => $minimum,
                'lukket' => '']
            );
        }
        foreach (
            [1 => ['company',
                    null,
                    'Company fact'],
                2 => ['user',
                    910001,
                    'Own fact'],
                3 => ['user',
                    910002,
                    'Other private fact']] as $id => [$scope,
                $user,
                $fact]
        ) {
            $db->insert(
                'assistant_memory',
                ['id' => $id,
                    'scope' => $scope,
                    'user_id' => $user,
                    'fact' => $fact,
                    'source_principal' => 'user:910001',
                'created_at' => gmdate('Y-m-d H:i:s')]
            );
        }
    }

    protected function read(string $name, array $args = [], ?SaldiTenantContext $ctx = null): array
    {
        $tool = SaldiToolRegistry::default()->get($name);
        SaldiSchemaValidator::validate(json_decode(json_encode((object)$args), false), $tool->inputSchema);
        $result = ($tool->handler)($ctx ?? $this->env->ctx(), $this->env->db, $args);
        SaldiSchemaValidator::validate($result['data'], $tool->dataSchema);
        return $result;
    }

    protected function rpc(
        string $method,
        array $params = [],
        ?SaldiTenantContext $ctx = null,
        array $headers = []
    ): array {
        $ctx ??= $this->env->ctx(910001, null, 'agent:saldi-assist', 99, ['read', 'draft']);
        $server = new SaldiMcpServer(
            null,
            fn(string $token): array => ['ctx' => $ctx,
                'db' => $this->env->db],
            [],
            'test'
        );
        return $server->handle(['method' => 'POST',
                'headers' => array_replace(
                    ['Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->opaqueToken()],
                    $headers
                ),
                'body' => json_encode(
                    ['jsonrpc' => '2.0',
                        'id' => 1,
                        'method' => $method,
                        'params' => (object)$params],
                    JSON_THROW_ON_ERROR
                )]);
    }

    protected function opaqueToken(): string
    {
        return 'smcp1.local.10.99.' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    protected function toolCall(string $name, array $args = [], ?SaldiTenantContext $ctx = null): array
    {
        $response = $this->rpc('tools/call', ['name' => $name, 'arguments' => (object)$args], $ctx);
        self::assertSame(200, $response['status']);
        self::assertArrayHasKey('result', $response['body']);
        $result = $response['body']['result'];
        self::assertSame(
            json_decode(
                $result['content'][0]['text'],
                true
            ),
            json_decode(
                json_encode($result['structuredContent']),
                true
            )
        );
        $tool = SaldiToolRegistry::default()->get($name);
        if ($tool !== null) {
            SaldiSchemaValidator::validate($result['structuredContent'], $tool->outputSchema());
        }
        return $result['structuredContent'];
    }

    protected function only(int $position, string $level = '2'): SaldiTenantContext
    {
        $rights = str_repeat('0', 16);
        $rights[$position] = $level;
        return $this->env->ctx(910001, $rights, 'agent:saldi-assist', 99, ['read', 'draft']);
    }

    protected function hideRevenue(): void
    {
        $this->env->db->insert(
            'settings',
            ['var_grp' => 'finans',
                'var_name' => 'hideRevenue',
                'var_value' => 'on',
                'var_description' => '',
                'user_id' => null,
            'pos_id' => null]
        );
    }
}
