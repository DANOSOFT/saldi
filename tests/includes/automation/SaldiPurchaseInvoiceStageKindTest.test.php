<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiPurchaseInvoiceStageKindTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// Invoice staging and exact undo, with live order, receipt and permission checks.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Add regression coverage for companion review corrections.
// 20261005 CDX/LH Check Danish quantities on proposed and applied invoice staging cards.

declare(strict_types=1);

require_once __DIR__ . "/../support/SaldiPurchaseTestCase.php";

final class SaldiPurchaseInvoiceStageKindTest extends SaldiPurchaseTestCase
{
    public function testApplyAndUndoRestoreEveryRowExactlyAndNeverTouchStock(): void
    {
        $before = $this->snapshot();
        $ctx = $this->env->ctx();
        $item = $this->inbox()->create($ctx, 'purchase_order.invoice_stage', $this->stage());
        self::assertSame($before, $this->snapshot());
        self::assertSame(['Linje', 'Antal at modtage', 'Pris før', 'Pris nu'], $item['card']['columns']);
        self::assertSame('10.13', $item['card']['rows'][0][2]);
        self::assertSame('11.01', $item['card']['rows'][0][3]);
        self::assertSame('4,125', $item['card']['rows'][0][1]);
        $applied = $this->approve($item);
        self::assertTrue($applied['can_undo']);
        $after = $this->snapshot();
        foreach (['batch_kob', 'batch_salg', 'varer', 'vare_lev'] as $table) {
            self::assertSame($before[$table], $after[$table], $table);
        }
        self::assertSame('INV-51', $after['ordrer'][0]['fakturanr']);
        self::assertSame('2026-06-10', $after['ordrer'][0]['levdate']);
        self::assertSame(1, $after['ordrer'][0]['status']);
        self::assertSame('', $after['ordrer'][0]['hvem']);
        self::assertSame('0', $after['ordrer'][0]['tidspkt']);
        self::assertSame('4.125', $after['ordrelinjer'][0]['leveres']);
        self::assertSame('11.005', $after['ordrelinjer'][0]['pris']);
        self::assertSame($before['ordrelinjer'][1], $after['ordrelinjer'][1]);
        self::assertSame('10.13', $applied['card']['rows'][0][2]);
        self::assertSame('4,125', $applied['card']['rows'][0][1]);
        self::assertSame('undone', $this->inbox()->undo($ctx, $item['id'], $ctx->tenantHash())['status']);
        self::assertSame($before, $this->snapshot());
    }

    public function testPartialReceiptAllowsOnlyRemainingAndUnchangedPrice(): void
    {
        $this->receipt($this->line, '6.125');
        $input = $this->stage(['lines' => [['line_id' => $this->line,
            'receive_quantity' => '3.875', 'unit_price' => '10.125']]]);
        $applied = $this->approve($this->inbox()->create(
            $this->env->ctx(),
            'purchase_order.invoice_stage',
            $input
        ));
        self::assertTrue($applied['can_undo']);
        self::assertSame('3.875', $this->env->db->value('SELECT leveres FROM ordrelinjer WHERE id = ?', [
            $this->line,
        ]));
        self::assertSame(1, (int)$this->env->db->value('SELECT COUNT(*) FROM batch_kob'));
    }

    public function testZeroQuantityReceiptStillPreventsPriceChanges(): void
    {
        $this->receipt($this->line, '0.000');
        $before = $this->snapshot();
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'purchase_order.invoice_stage',
            $this->stage()
        ));
        self::assertSame($before, $this->snapshot());
    }

    public function testRejectsExcessNegativeForeignDuplicateAndUnknownLines(): void
    {
        $foreign = $this->line(['ordre_id' => $this->order(['ordrenr' => 99])]);
        $inputs = [
            [['line_id' => $this->line, 'receive_quantity' => '10.001']],
            [['line_id' => $this->line, 'receive_quantity' => '-1']],
            [['line_id' => $foreign]],
            [['line_id' => $this->line], ['line_id' => $this->line]],
            [['line_id' => $this->line, 'kostpris' => '99']],
            [['line_id' => $this->line, 'unit_price' => 10.0]],
        ];
        $before = $this->snapshot();
        foreach ($inputs as $lines) {
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
                $this->env->ctx(),
                'purchase_order.invoice_stage',
                $this->stage(['lines' => $lines])
            ));
        }
        self::assertSame($before, $this->snapshot());
    }

    public function testInvoiceDateAndNumberValidation(): void
    {
        foreach (['', str_repeat('A', 31)] as $number) {
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
                $this->env->ctx(),
                'purchase_order.invoice_stage',
                $this->stage(['invoice_number' => $number])
            ));
        }
        foreach (['2026-02-30', '10-06-2026'] as $date) {
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
                $this->env->ctx(),
                'purchase_order.invoice_stage',
                $this->stage(['invoice_date' => $date])
            ));
        }
        $this->error('CLOSED_PERIOD', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'purchase_order.invoice_stage',
            $this->stage(['invoice_date' => '2027-01-01'])
        ));
        $this->env->db->exec('UPDATE ordrer SET ordredate = ? WHERE id = ?', ['2026-07-01', $this->order]);
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'purchase_order.invoice_stage',
            $this->stage()
        ));
    }

    public function testOnlyOpenKoOrdersAndPurchaseWritersCanStage(): void
    {
        $db = $this->env->db;
        foreach ([['DO', 1], ['KO', 0], ['KO', 3]] as [$art, $status]) {
            $db->exec('UPDATE ordrer SET art = ?, status = ? WHERE id = ?', [$art, $status, $this->order]);
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
                $this->env->ctx(),
                'purchase_order.invoice_stage',
                $this->stage()
            ));
        }
        $db->exec("UPDATE ordrer SET art = 'KO', status = 2 WHERE id = ?", [$this->order]);
        $reader = $this->env->ctx(910001, '0000000200000000');
        $this->error('PERMISSION_DENIED', fn() => $this->inbox()->create(
            $reader,
            'purchase_order.invoice_stage',
            $this->stage()
        ));
        $item = $this->inbox()->create($this->env->ctx(), 'purchase_order.invoice_stage', $this->stage());
        $this->error('PERMISSION_DENIED', fn() => $this->approve($item, $reader));
        self::assertSame('applied', $this->approve($item)['status']);
    }

    public function testLiveLocksAtProposalAndApprovalAndExpiredLocks(): void
    {
        $db = $this->env->db;
        foreach ([(string)time(), '0.123456 ' . time()] as $timestamp) {
            $db->exec('UPDATE ordrer SET hvem = ?, tidspkt = ? WHERE id = ?', ['Other', $timestamp, $this->order]);
            $error = $this->error('CONFLICT', fn() => $this->inbox()->create(
                $this->env->ctx(),
                'purchase_order.invoice_stage',
                $this->stage()
            ));
            self::assertSame('journal_in_use', $error->detail['reason']);
        }
        $db->exec('UPDATE ordrer SET tidspkt = ? WHERE id = ?', [(string)(time() - 3601), $this->order]);
        $item = $this->inbox()->create($this->env->ctx(), 'purchase_order.invoice_stage', $this->stage());
        $db->exec('UPDATE ordrer SET tidspkt = ? WHERE id = ?', [(string)time(), $this->order]);
        $this->error('CONFLICT', fn() => $this->approve($item));
        self::assertNull($db->value('SELECT fakturanr FROM ordrer WHERE id = ?', [$this->order]));
    }

    public function testChangesAfterProposalAreStaleAndReceiptChangesAreRevalidated(): void
    {
        $db = $this->env->db;
        $item = $this->inbox()->create($this->env->ctx(), 'purchase_order.invoice_stage', $this->stage());
        $db->exec('UPDATE ordrelinjer SET pris = ? WHERE id = ?', ['9.000', $this->secondLine]);
        $this->error('STALE_CONTEXT', fn() => $this->approve($item));
        self::assertNull($db->value('SELECT fakturanr FROM ordrer WHERE id = ?', [$this->order]));
        $item = $this->inbox()->create($this->env->ctx(), 'purchase_order.invoice_stage', $this->stage());
        $this->receipt($this->line, '9.000');
        $this->error('VALIDATION_ERROR', fn() => $this->approve($item));
    }

    public function testUndoRefusesEditedHeaderLinesStatusAndNewReceipts(): void
    {
        $db = $this->env->db;
        $ctx = $this->env->ctx();
        $item = $this->approve($this->inbox()->create($ctx, 'purchase_order.invoice_stage', $this->stage()));
        foreach (
            [['ordrer', 'fakturanr', $this->order, 'INV-51', 'Edited'],
            ['ordrer', 'status', $this->order, 1, 2],
            ['ordrelinjer', 'pris', $this->line, '11.005', '11.006'],
            ['ordrelinjer', 'leveres', $this->secondLine, '1.500', '1.000']] as $change
        ) {
            [$table, $field, $id, $original, $edited] = $change;
            $db->exec('UPDATE ' . $table . ' SET ' . $field . ' = ? WHERE id = ?', [$edited, $id]);
            self::assertFalse($this->inbox()->get($ctx, $item['id'])['can_undo']);
            $before = $this->snapshot();
            $error = $this->error('CONFLICT', fn() => $this->inbox()->undo($ctx, $item['id'], $ctx->tenantHash()));
            self::assertSame('undo_changed', $error->detail['reason']);
            self::assertSame($before, $this->snapshot());
            $db->exec('UPDATE ' . $table . ' SET ' . $field . ' = ? WHERE id = ?', [$original, $id]);
        }
        self::assertTrue($this->inbox()->get($ctx, $item['id'])['can_undo']);
        $this->receipt($this->line, '0.000');
        self::assertFalse($this->inbox()->get($ctx, $item['id'])['can_undo']);
        $this->error('CONFLICT', fn() => $this->inbox()->undo($ctx, $item['id'], $ctx->tenantHash()));
    }

    public function testZeroPriceMultipleLinesAndOmittedFieldsRestoreNulls(): void
    {
        $before = $this->snapshot();
        $ctx = $this->env->ctx();
        $input = $this->stage(['lines' => [
            ['line_id' => $this->line, 'unit_price' => '0.000'],
            ['line_id' => $this->secondLine, 'receive_quantity' => '0.000'],
        ]]);
        $item = $this->approve($this->inbox()->create($ctx, 'purchase_order.invoice_stage', $input));
        self::assertSame('0.000', $this->env->db->value('SELECT pris FROM ordrelinjer WHERE id = ?', [$this->line]));
        self::assertNull($this->env->db->value('SELECT leveres FROM ordrelinjer WHERE id = ?', [$this->line]));
        $this->inbox()->undo($ctx, $item['id'], $ctx->tenantHash());
        self::assertSame($before, $this->snapshot());
    }

    public function testMissingOrderAndUnknownHeaderFieldsFail(): void
    {
        $this->error('NOT_FOUND', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'purchase_order.invoice_stage',
            $this->stage(['order_id' => 999999])
        ));
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'purchase_order.invoice_stage',
            $this->stage() + ['hvem' => 'Override']
        ));
    }

    public function testNullPriceOnReceivedLineCannotBecomeZero(): void
    {
        $this->env->db->exec('UPDATE ordrelinjer SET pris = NULL WHERE id = ?', [$this->line]);
        $this->receipt($this->line, '0.000');
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'purchase_order.invoice_stage',
            $this->stage(['lines' => [['line_id' => $this->line, 'unit_price' => '0']]])
        ));
        self::assertNull($this->env->db->value('SELECT pris FROM ordrelinjer WHERE id = ?', [$this->line]));
    }
    public function testStockBatchRestAndOwnOrderLocksDoNotInvalidateStageOrUndo(): void
    {
        $this->receipt($this->line, '1.000');
        $db = $this->env->db;
        $input = $this->stage(['lines' => [['line_id' => $this->line, 'receive_quantity' => '2', 'unit_price' => '10.125']]]);
        $item = $this->inbox()->create($this->env->ctx(), 'purchase_order.invoice_stage', $input);
        $db->exec('UPDATE batch_kob SET rest=0');
        $db->exec('UPDATE varer SET beholdning=987');
        $db->exec('UPDATE ordrer SET hvem=?,tidspkt=? WHERE id=?', [$this->env->ctx()->username, (string)time(), $this->order]);
        $applied = $this->approve($item);
        $db->exec('UPDATE batch_kob SET rest=0.5');
        $db->exec('UPDATE ordrer SET tidspkt=? WHERE id=?', [(string)(time() + 1), $this->order]);
        self::assertTrue($this->inbox()->get($this->env->ctx(), $applied['id'])['can_undo']);
        self::assertSame('undone', $this->inbox()->undo($this->env->ctx(), $applied['id'], $this->env->ctx()->tenantHash())['status']);
        self::assertSame('0.500', $db->value('SELECT rest FROM batch_kob LIMIT 1'));
    }

}
