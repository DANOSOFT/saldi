<?php

// --- tests/includes/automation/SaldiJournalDocumentExtensionTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Journal invoice, due-date and VAT validation at proposal and approval.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiDocumentTestCase.php';

final class SaldiJournalDocumentExtensionTest extends SaldiDocumentTestCase
{
    public function testInvoiceDueDateAndVatOverrideArePersisted(): void
    {
        $input = $this->draft();
        $input['lines'][0] += ['invoice' => 'INV-17', 'due_date' => '2025-07-15'];
        $input['lines'][0]['debit']['vat_code'] = 'K991';
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $input);
        $applied = $this->approve($item);
        $row = $this->env->db->one('SELECT * FROM kassekladde WHERE id = ?', [$applied['result']['line_ids'][0]]);
        self::assertSame('INV-17', $row['faktura']);
        self::assertSame('2025-07-15', $row['forfaldsdate']);
        self::assertSame('K991', $row['debetvat']);
        self::assertSame('', $row['kreditvat']);
        self::assertSame('', $row['momsfri']);
    }

    public function testVatFreeAndDefaultDueDateArePersisted(): void
    {
        $input = $this->draft();
        $input['lines'][0]['vat_free'] = true;
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $input);
        $applied = $this->approve($item);
        $row = $this->env->db->one('SELECT * FROM kassekladde WHERE id = ?', [$applied['result']['line_ids'][0]]);
        self::assertSame('on', $row['momsfri']);
        self::assertSame('2025-06-15', $row['forfaldsdate']);
    }

    public function testFinanceOnlyLinesCannotHaveInvoiceOrDueDate(): void
    {
        foreach (['invoice' => 'INV-17', 'due_date' => '2025-07-15'] as $field => $value) {
            $input = $this->draft();
            $input['lines'][0]['credit'] = ['type' => 'F', 'account' => '991002'];
            $input['lines'][0][$field] = $value;
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
                $this->env->ctx(),
                'journal.draft_entry',
                $input
            ));
        }
    }

    public function testInvalidInvoiceDateAndVatAreRejected(): void
    {
        foreach (['invoice' => 'bad@invoice', 'due_date' => '2025-02-31', 'vat_free' => 'true'] as $field => $value) {
            $input = $this->draft();
            $input['lines'][0][$field] = $value;
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
                $this->env->ctx(),
                'journal.draft_entry',
                $input
            ));
        }
        foreach (['none', 'K999999', 'k991'] as $code) {
            $input = $this->draft();
            $input['lines'][0]['debit']['vat_code'] = $code;
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
                $this->env->ctx(),
                'journal.draft_entry',
                $input
            ));
        }
        $input = $this->draft();
        $input['lines'][0]['debit']['vat_code'] = '';
        $input['lines'][0]['vat_free'] = true;
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'journal.draft_entry',
            $input
        ));
    }

    public function testMissingVatColumnReportsUnsupportedAndApprovalRechecksCode(): void
    {
        $input = $this->draft();
        $input['lines'][0]['debit']['vat_code'] = 'K991';
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $input);
        $this->env->db->exec("DELETE FROM grupper WHERE art = 'KM' AND kodenr = ? AND fiscal_year = ?", ['991', 4]);
        $this->error('VALIDATION_ERROR', fn() => $this->approve($item));
        $this->env->db->exec('ALTER TABLE kassekladde DROP COLUMN debetvat');
        $error = $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'journal.draft_entry',
            $input
        ));
        self::assertSame('vat_override_unsupported', $error->detail['reason']);
    }

    public function testMissingPoolRowIsRecheckedAtApproval(): void
    {
        $poolId = $this->pool();
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->draft([
            'document_ref' => 'pool:' . $poolId,
        ]));
        $this->env->db->exec('DELETE FROM pool_files WHERE id = ?', [$poolId]);
        $error = $this->error('CONFLICT', fn() => $this->approve($item));
        self::assertSame('document_missing', $error->detail['reason']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
    }
}
