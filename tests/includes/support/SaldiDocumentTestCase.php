<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiDocumentTestCase.php --- ai/mcp-assistant --- 2026.10.02
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
// Disposable DB clones and temporary installation roots for C3 document tests.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiDocumentTools.php';
require_once __DIR__ . '/../../../includes/automation/approvalCard.php';
require_once __DIR__ . '/../../../includes/mcp/SaldiMcpServer.php';

abstract class SaldiDocumentTestCase extends SaldiRuntimeDbTestCase
{
    protected string $appRoot;
    private string|false $previousRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousRoot = getenv('SALDI_DOCUMENT_APP_ROOT');
        $this->appRoot = sys_get_temp_dir() . '/saldi-document-' . bin2hex(random_bytes(8));
        mkdir($this->appRoot . '/bilag/' . $this->env->name . '/pulje', 0775, true);
        putenv('SALDI_DOCUMENT_APP_ROOT=' . $this->appRoot);
        $db = $this->env->db;
        foreach (['documents', 'pool_files', 'kassekladde', 'tmpkassekl', 'kladdeliste', 'openpost'] as $table) {
            $db->exec('DELETE FROM ' . $table);
        }
        foreach (
            [
                'norm_amount' => 'numeric(15,3)', 'vendor_name' => 'text', 'vendor_cvr' => 'varchar(20)',
                'vendor_iban' => 'varchar(40)', 'vendor_konto_id' => 'integer', 'vendor_match' => 'varchar(10)',
                'manually_edited' => 'boolean NOT NULL DEFAULT false',
            ] as $column => $type
        ) {
            $db->exec('ALTER TABLE pool_files ADD COLUMN IF NOT EXISTS ' . $column . ' ' . $type);
        }
        foreach (['debetvat', 'kreditvat'] as $column) {
            $db->exec('ALTER TABLE kassekladde ADD COLUMN IF NOT EXISTS ' . $column . ' varchar(8)');
        }
        foreach ([991001 => 'D', 991002 => 'S'] as $number => $type) {
            $db->insert('kontoplan', [
                'id' => $number, 'kontonr' => $number, 'regnskabsaar' => 4, 'kontotype' => $type,
                'beskrivelse' => 'Document account', 'lukket' => '', 'moms' => 'K1',
            ]);
        }
        foreach (['D' => 991003, 'K' => 991004] as $type => $number) {
            $db->insert('adresser', [
                'id' => $number, 'kontonr' => (string)$number, 'art' => $type,
                'firmanavn' => 'Document party', 'lukket' => '',
            ]);
        }
        $db->insert('grupper', ['art' => 'KM', 'kodenr' => '991', 'fiscal_year' => 4]);
    }

    protected function tearDown(): void
    {
        if (isset($this->appRoot)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->appRoot, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isDir() && !$file->isLink()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($this->appRoot);
            $rootEnv = $this->previousRoot === false
                ? 'SALDI_DOCUMENT_APP_ROOT' : 'SALDI_DOCUMENT_APP_ROOT=' . $this->previousRoot;
            putenv($rootEnv);
        }
        parent::tearDown();
    }

    protected function pool(array $values = [], bool $file = true): int
    {
        $values += [
            'filename' => 'invoice-' . bin2hex(random_bytes(4)) . '.pdf', 'file_date' => '2025-06-15',
            'amount' => '1250.00', 'norm_amount' => '1250.000', 'invoice_number' => 'INV-17',
            'description' => 'Software abonnement', 'currency' => 'DKK', 'vendor_name' => 'Document party',
            'vendor_cvr' => 'synthetic-cvr', 'vendor_iban' => 'synthetic-bank-marker',
            'vendor_konto_id' => 991004, 'vendor_match' => 'cvr', 'manually_edited' => true,
            'updated' => '2025-06-15 10:00:00',
        ];
        $values['manually_edited'] = $values['manually_edited'] ? 'true' : 'false';
        $id = $this->env->db->insert('pool_files', $values);
        if ($file) {
            file_put_contents($this->poolPath($values['filename']), 'Synthetic invoice fixture');
        }
        return $id;
    }

    protected function poolPath(string $filename): string
    {
        return $this->appRoot . '/bilag/' . $this->env->name . '/pulje/' . $filename;
    }

    protected function draft(array $extra = []): array
    {
        return $extra + [
            'date' => '2025-06-15',
            'lines' => [[
                'description' => 'Invoice draft', 'amount' => '1250.00',
                'debit' => ['type' => 'F', 'account' => '991001'],
                'credit' => ['type' => 'K', 'account' => '991004'],
            ]],
        ];
    }

    protected function payment(string $ledger = 'supplier', array $extra = []): array
    {
        return $extra + [
            'party' => ['ledger' => $ledger, 'account' => $ledger === 'supplier' ? '991004' : '991003'],
            'bank_account' => '991002', 'amount' => '1250.00', 'date' => '2025-06-15',
            'invoice_numbers' => ['INV-17'], 'explanation' => 'Already transferred; registration only.',
            'request_id' => 'payment-' . bin2hex(random_bytes(8)),
        ];
    }

    protected function openItem(string $ledger = 'supplier', array $extra = []): int
    {
        return $this->env->db->insert('openpost', $extra + [
            'konto_id' => $ledger === 'supplier' ? 991004 : 991003, 'faktnr' => 'INV-17',
            'amount' => $ledger === 'supplier' ? '-1250.000' : '1250.000',
            'udlignet' => '', 'valuta' => 'DKK', 'transdate' => '2025-06-15',
        ]);
    }

    protected function tool(string $name, array $args): array
    {
        $server = new SaldiMcpServer(null, fn(): array => ['ctx' => $this->env->ctx(), 'db' => $this->env->db]);
        $response = $server->handle([
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer smcp1.local.' . bin2hex(random_bytes(16)),
            ],
            'body' => json_encode([
                'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => (object)$args],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertSame(200, $response['status']);
        $result = $response['body']['result']['structuredContent'];
        SaldiSchemaValidator::validate($result, SaldiToolRegistry::default()->get($name)->outputSchema());
        return $result;
    }
}
