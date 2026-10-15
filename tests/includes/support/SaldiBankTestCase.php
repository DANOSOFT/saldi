<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiBankTestCase.php --- ai/mcp-assistant --- 2026.10.04
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
// Disposable-tenant bank staging, matching, approval, per-voucher dates and undo coverage.
//
// 20261004 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/SaldiPostingTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiBankTools.php';
require_once __DIR__ . '/../../../includes/tools/SaldiSchemaValidator.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';

abstract class SaldiBankTestCase extends SaldiPostingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['bank_import_lines', 'openpost', 'ordrer', 'documents'] as $table) {
            $this->env->db->exec('DELETE FROM ' . $table);
        }
        $this->env->db->exec("UPDATE grupper SET box1 = '1', box2 = '2025', box3 = '12', box4 = '2025', box5 = 'on', box10 = '' WHERE art = 'RA' AND kodenr = 4");
    }

    protected function row(string $amount = '1234.56', string $text = 'Faktura 501', string $date = '2025-06-04', array $extra = []): array
    {
        return $extra + ['date' => $date, 'description' => $text, 'amount' => $amount, 'currency' => 'DKK'];
    }

    protected function stage(array $rows): array
    {
        return SaldiBankImport::stage($this->env->ctx(), $this->env->db, '5500', ['rows' => $rows, 'warnings' => []]);
    }

    protected function open(string $invoice = '501', string $amount = '1234.56', int $party = 2005, array $extra = []): void
    {
        $this->env->db->insert('openpost', $extra + [
            'konto_id' => $party, 'faktnr' => $invoice, 'amount' => $amount,
            'valuta' => 'DKK', 'valutakurs' => '100', 'udlignet' => '', 'transdate' => '2025-06-01',
        ]);
    }

    protected function order(string $invoice, string $payment, int $party = 2005, string $art = 'DO'): void
    {
        $this->env->db->insert('ordrer', [
            'art' => $art, 'konto_id' => $party, 'kontonr' => (string)$party,
            'fakturanr' => $invoice, 'betalings_id' => $payment, 'status' => 3,
        ]);
    }

    protected function match(array $row): ?array
    {
        return (new SaldiBankMatcher($this->env->ctx(), $this->env->db))->match($row + ['bank_account' => '5500']);
    }

    protected function propose(string $batch, string $request = 'bank-test', array $extra = []): array
    {
        return SaldiBankTools::propose($this->env->ctx(), $this->env->db, $extra + [
            'batch_id' => $batch, 'request_id' => $request, 'explanation' => 'Syntetisk bankfil til gennemgang.',
        ])['data'];
    }

    protected function statuses(string $batch): array
    {
        return array_column($this->env->db->all('SELECT status FROM bank_import_lines WHERE batch_id = ? ORDER BY line_no', [$batch]), 'status');
    }
}
