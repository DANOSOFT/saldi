<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiBankToolsTest.test.php --- ai/mcp-assistant --- 2026.10.10
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
// 20261007 CL/LH A repeated invoice is high at most once, whatever the cache, filter or selection.
// 20261010 CDX/LH Keep the model bank import character limit.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiBankTestCase.php';

final class SaldiBankToolsTest extends SaldiBankTestCase
{
	public function testModelImportKeepsItsCharacterLimit(): void
	{
		$prefix = "date;description;amount\n2025-06-04;";
		$suffix = ";12.50\n";
		$content = $prefix . str_repeat('x', 200000 - strlen($prefix) - strlen($suffix)) . $suffix;
		$args = ['bank_account' => '5500', 'content' => $content];
		$schema = SaldiToolRegistry::default()->get('import_bank_statement')->inputSchema;
		SaldiSchemaValidator::validate($args, $schema);
		self::assertSame(200000, mb_strlen($content));
		self::assertCount(1, SaldiBankTools::import($this->env->ctx(), $this->env->db, $args)['data']['lines']);
		$args['content'] .= "\n";
		self::assertSame(200001, mb_strlen($args['content']));
		$this->error('VALIDATION_ERROR', fn() => SaldiSchemaValidator::validate($args, $schema));
		$this->error('VALIDATION_ERROR', fn() => SaldiBankTools::import($this->env->ctx(), $this->env->db, $args));
	}

    public function testMediumConfidenceIsExcludedUnlessExplicitlySelected(): void
    {
        $this->open('501', '1234.56');
        $batch = $this->stage([$this->row(), $this->row('100.00'), $this->row('20.00', 'Ukendt')]);
        $default = $this->propose($batch['batch_id']);
        self::assertCount(1, $default['payload']['lines']);
        self::assertSame(['proposed', 'new', 'new'], $this->statuses($batch['batch_id']));
        $medium = $this->propose($batch['batch_id'], 'bank-medium', ['line_ids' => [$batch['lines'][1]['line_id']]]);
        self::assertSame('100.00', $medium['payload']['lines'][0]['amount']);
        self::assertSame(['proposed', 'proposed', 'new'], $this->statuses($batch['batch_id']));
        $this->error('VALIDATION_ERROR', fn() => $this->propose($batch['batch_id'], 'bank-unknown', ['line_ids' => [$batch['lines'][2]['line_id']]]));
        $this->error('CONFLICT', fn() => $this->propose($batch['batch_id'], 'bank-other', ['line_ids' => [$batch['lines'][0]['line_id']]]));
    }

    public function testRepeatedInvoiceIsHighOnceAcrossCachedListsFiltersAndProposals(): void
    {
        $db = $this->env->db;
        $this->open('637', '240.00');
        $batch = $this->stage([$this->row('240.00', 'Faktura 637', '2025-06-05'), $this->row('240.00', 'Faktura 637', '2025-06-08')]);
        $id = $batch['batch_id'];
        $confidence = fn(array $args = []): array => array_column(array_column(SaldiBankTools::listLines($this->env->ctx(), $db, $args + ['batch_id' => $id])['data']['rows'], 'match'), 'confidence');
        self::assertSame(['high', 'medium'], $confidence());
        // Reviewer scenario: propose and reject the second line; its cache is cleared, the first keeps its cached high.
        $second = $this->propose($id, 'bank-second', ['line_ids' => [$batch['lines'][1]['line_id']]]);
        self::assertSame(['new', 'proposed'], $this->statuses($id));
        // While the second line holds the invoice, the first is opt-in.
        self::assertSame(['medium', 'medium'], $confidence());
        $this->inbox()->decide($this->env->ctx(), $second['id'], 'reject', $second['digest'], $this->env->ctx()->tenantHash());
        self::assertSame(['new', 'new'], $this->statuses($id));
        self::assertNotNull(json_decode((string)$db->value('SELECT proposal FROM bank_import_lines WHERE id = ?', [$batch['lines'][0]['line_id']]), true)['cache_key'] ?? null);
        self::assertSame(['high', 'medium'], $confidence());
        // A stale cache key on every row recomputes the same answer.
        $db->exec('UPDATE bank_import_lines SET proposal = NULL WHERE batch_id = ?', [$id]);
        self::assertSame(['high', 'medium'], $confidence());
        // The default proposal takes only the first line; a filter does not move the claim.
        self::assertCount(1, $this->propose($id, 'bank-default')['payload']['lines']);
        self::assertSame(['proposed', 'new'], $this->statuses($id));
        self::assertSame(['medium'], $confidence(['status' => 'new']));
    }

    public function testImportListDraftApproveAndUndoPreserveDatesInvoicesSidesAndVouchers(): void
    {
        $db = $this->env->db;
        $this->open('501', '1234.56');
        $this->open('SUP-44', '-25.00', 1005);
        $import = SaldiBankTools::import($this->env->ctx(), $db, ['bank_account' => '5500', 'content' => "Dato;Tekst;Beløb\n04-06-2025;Faktura 501;1.234,56\n05-06-2025;Faktura SUP-44;-25,00\n"]);
        $batch = $import['data']['batch_id'];
        $list = SaldiBankTools::listLines($this->env->ctx(), $db, ['batch_id' => $batch]);
        self::assertSame(['high', 'high'], array_column(array_column($list['data']['rows'], 'match'), 'confidence'));
        self::assertSame(2, $list['data']['total']);
        $item = $this->propose($batch);
        self::assertSame(['2025-06-04', '2025-06-05'], array_column($item['payload']['lines'], 'date'));
        self::assertSame('F', $item['payload']['lines'][0]['debit']['type']);
        self::assertSame('D', $item['payload']['lines'][0]['credit']['type']);
        self::assertSame('K', $item['payload']['lines'][1]['debit']['type']);
        self::assertSame('F', $item['payload']['lines'][1]['credit']['type']);
        self::assertSame(['501', 'SUP-44'], array_column($item['payload']['lines'], 'invoice'));
        self::assertSame(['2025-06-04', '2025-06-05'], array_column($item['card']['rows'], 0));
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM kassekladde'));
        $applied = $this->approve($item);
        $rows = $db->all('SELECT * FROM kassekladde WHERE kladde_id = ? ORDER BY pos', [$applied['result']['kladde_id']]);
        self::assertSame([1, 2], array_column($rows, 'bilag'));
        self::assertSame(['2025-06-04', '2025-06-05'], array_column($rows, 'transdate'));
        self::assertSame(['2025-06-04', '2025-06-05'], array_column($rows, 'forfaldsdate'));
        self::assertSame(['501', 'SUP-44'], array_column($rows, 'faktura'));
        self::assertSame(['1234.560', '25.000'], array_column($rows, 'amount'));
        self::assertSame(['1', '2'], array_column($applied['card']['rows'], 1));
        self::assertSame('-', $db->value('SELECT bogfort FROM kladdeliste WHERE id = ?', [$applied['result']['kladde_id']]));
        self::assertTrue($applied['can_undo']);
        $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash());
        self::assertSame(['new', 'new'], $this->statuses($batch));
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM kassekladde'));
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM bank_import_lines WHERE inbox_item_id IS NOT NULL'));
        self::assertTrue($this->propose($batch)['deduplicated']);
        self::assertSame('undone', $this->propose($batch)['status']);
        self::assertSame(['new', 'new'], $this->statuses($batch));
        self::assertSame('open', $this->propose($batch, 'bank-after-undo')['status']);
        $registry = SaldiToolRegistry::default();
        foreach (['import_bank_statement' => $import, 'list_bank_lines' => $list] as $tool => $result) {
            SaldiSchemaValidator::validate($result['data'], $registry->get($tool)->dataSchema);
        }
        SaldiSchemaValidator::validate($item, $registry->get('propose_bank_entries')->dataSchema);
    }

    public function testRejectCancelAndBothExpiryPathsReleaseOnlyTheirOwnLines(): void
    {
        $this->open();
        foreach (['reject', 'cancel', 'worker', 'approve-expired'] as $action) {
            $batch = $this->stage([$this->row('10.00', '501 ' . $action)]);
            $item = $this->propose($batch['batch_id'], 'bank-' . $action, ['line_ids' => [$batch['lines'][0]['line_id']]]);
            if ($action === 'reject') {
                $this->inbox()->decide($this->env->ctx(), $item['id'], 'reject', $item['digest'], $this->env->ctx()->tenantHash());
            } elseif ($action === 'cancel') {
                $this->inbox()->cancel($this->env->ctx(), $item['id']);
            } else {
                $this->env->db->exec('UPDATE inbox_items SET expires_at = ? WHERE id = ?', ['2020-01-01 00:00:00', $item['id']]);
                if ($action === 'worker') {
                    self::assertSame(1, $this->inbox()->expireDue());
                } else {
                    self::assertSame('expired', $this->error('CONFLICT', fn() => $this->approve($item))->detail['reason']);
                }
            }
            self::assertSame(['new'], $this->statuses($batch['batch_id']), $action);
        }
    }

    public function testRequestReplayAndChangedSelectionConflict(): void
    {
        $this->open();
        $batch = $this->stage([$this->row(), $this->row('100.00', 'Anden 501')]);
        $item = $this->propose($batch['batch_id']);
        $again = $this->propose($batch['batch_id']);
        self::assertSame($item['id'], $again['id']);
        self::assertTrue($again['deduplicated']);
        $this->error('VALIDATION_ERROR', fn() => $this->propose($batch['batch_id'], 'bank-test', ['line_ids' => [$batch['lines'][0]['line_id']]]));
        $this->error('VALIDATION_ERROR', fn() => $this->propose($batch['batch_id'], 'bank-repeated', ['line_ids' => [$batch['lines'][1]['line_id'], $batch['lines'][1]['line_id']]]));
        $this->error('VALIDATION_ERROR', fn() => $this->propose($batch['batch_id'], 'bank-missing', ['line_ids' => [99999999]]));
    }

    public function testBankDraftMaxTwoHundredAndApprovalUsesExistingVoucherSeries(): void
    {
        $rows = [];
        for ($i = 0; $i < 201; $i++) {
            // One open item per line: a batch claims each open item once.
            $this->open((string)(7000 + $i), '1.00');
            $rows[] = $this->row('1.00', 'Faktura ' . (7000 + $i));
        }
        $this->line(['kladde_id' => 42, 'bilag' => 41, 'amount' => '17.00', 'transdate' => '2025-05-01']);
        $batch = $this->stage($rows);
        $this->error('VALIDATION_ERROR', fn() => $this->propose($batch['batch_id']));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
        $item = $this->propose($batch['batch_id'], 'bank-200', ['line_ids' => array_column(array_slice($batch['lines'], 0, 200), 'line_id'), 'kladde_id' => 42]);
        self::assertCount(200, $item['payload']['lines']);
        // The existing journal's snapshot must remain unchanged between proposal and approval.
        $applied = $this->approve($item);
        self::assertSame(range(42, 241), $applied['result']['bilags']);
        self::assertCount(200, $applied['result']['line_ids']);
        self::assertSame('new', $this->statuses($batch['batch_id'])[200]);
    }

    public function testPerLineVatDateStaleReservationAndNewJournalDuplicateBlockApproval(): void
    {
        $this->open();
        $batch = $this->stage([$this->row(), $this->row('25.00', '501 Second', '2025-07-04')]);
        $item = $this->propose($batch['batch_id'], 'bank-vat', ['line_ids' => array_column($batch['lines'], 'line_id')]);
        $db = $this->env->db;
        if (!$db->hasTable('moms_periode_luk')) {
            $db->exec('CREATE TABLE moms_periode_luk (id serial PRIMARY KEY, kalender_aar int, kalender_maaned int, status varchar(16))');
        }
        $db->insert('moms_periode_luk', ['kalender_aar' => 2025, 'kalender_maaned' => 7, 'status' => 'closed']);
        $this->error('CLOSED_PERIOD', fn() => $this->approve($item));
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM kassekladde'));
        $db->exec('DELETE FROM moms_periode_luk');
        $db->exec('UPDATE bank_import_lines SET inbox_item_id = NULL WHERE id = ?', [$batch['lines'][0]['line_id']]);
        $this->error('CONFLICT', fn() => $this->approve($item));
        $db->exec('UPDATE bank_import_lines SET inbox_item_id = ? WHERE id = ?', [$item['id'], $batch['lines'][0]['line_id']]);
        $this->line(['d_type' => 'F', 'debet' => '5500', 'amount' => '1234.560', 'transdate' => '2025-06-04']);
        $this->error('CONFLICT', fn() => $this->approve($item));
        self::assertSame(1, (int)$db->value('SELECT COUNT(*) FROM kassekladde'));
    }

    public function testOneSidedBankLineAndMismatchedDateSignOrAccountCannotBeForged(): void
    {
        $this->open();
        $batch = $this->stage([$this->row()]);
        $item = $this->propose($batch['batch_id']);
        $kind = new SaldiJournalDraftEntryKind();
        foreach (['one-sided', 'date', 'sign', 'bank-account'] as $change) {
            $payload = $item['payload'];
            if ($change === 'one-sided') {
                unset($payload['lines'][0]['credit']);
            } elseif ($change === 'date') {
                $payload['lines'][0]['date'] = '2025-06-05';
            } elseif ($change === 'sign') {
                [$payload['lines'][0]['debit'], $payload['lines'][0]['credit']] = [$payload['lines'][0]['credit'], $payload['lines'][0]['debit']];
            } else {
                $payload['lines'][0]['debit']['account'] = '5600';
            }
            try {
                $kind->validate($this->env->ctx(), $this->env->db, $payload);
                self::fail('Forged ' . $change . ' accepted');
            } catch (SaldiRuntimeError $error) {
                self::assertContains($error->errorCode, ['CONFLICT', 'VALIDATION_ERROR']);
            }
        }
    }

    public function testChangedAppliedLinePreventsUndoAndKeepsReservation(): void
    {
        $this->open();
        $batch = $this->stage([$this->row()]);
        $item = $this->propose($batch['batch_id']);
        $applied = $this->approve($item);
        $this->env->db->exec('UPDATE kassekladde SET amount = ? WHERE id = ?', ['1.00', $applied['result']['line_ids'][0]]);
        $this->error('CONFLICT', fn() => $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash()));
        self::assertSame(['proposed'], $this->statuses($batch['batch_id']));
    }

    public function testPermissionsAndUnknownBatchFailClosed(): void
    {
        $readOnly = $this->env->ctx(910001, str_repeat('0', 16));
        $this->error('PERMISSION_DENIED', fn() => SaldiBankTools::import($readOnly, $this->env->db, ['bank_account' => '5500', 'content' => 'x']));
        $this->error('PERMISSION_DENIED', fn() => SaldiBankTools::propose($readOnly, $this->env->db, ['batch_id' => str_repeat('a', 32)]));
        $this->error('NOT_FOUND', fn() => SaldiBankTools::listLines($this->env->ctx(), $this->env->db, ['batch_id' => str_repeat('a', 32)]));
        $this->error('VALIDATION_ERROR', fn() => SaldiBankTools::import($this->env->ctx(), $this->env->db, ['bank_account' => '5500', 'content' => str_repeat('x', 200001)]));
    }

    public function testEverySyntheticBankFixtureCanStageAndMatch(): void
    {
        $this->open();
        $this->order('501', 'ha_12345');
        $this->rule(['match' => ['type' => 'text', 'value' => 'bankgebyr'], 'account' => '2800']);
        foreach (glob(__DIR__ . '/../fixtures/bank/*') as $file) {
            if (!in_array(pathinfo($file, PATHINFO_EXTENSION), ['csv', 'xml'], true)) {
                continue;
            }
            $this->env->db->exec('DELETE FROM bank_import_lines');
            $data = SaldiBankTools::import($this->env->ctx(), $this->env->db, [
                'bank_account' => '5500', 'content' => file_get_contents($file), 'filename' => basename($file),
            ])['data'];
            $item = $this->propose($data['batch_id'], 'fixture-' . basename($file));
            self::assertCount(2, $item['payload']['lines'], basename($file));
            self::assertSame('501', $item['payload']['lines'][0]['invoice'], basename($file));
            self::assertSame('2800', $item['payload']['lines'][1]['debit']['account'], basename($file));
        }
    }
}
