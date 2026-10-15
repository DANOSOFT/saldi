<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiJournalTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Draft journal numbering, locks, periods, stale revision and exact-row undo on clones.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cover F1 integration behavior and regression cases.
// 20261002 CL/LH Balanced fixture; unbalanced drafts refused; betal_id survives a kassekladde save for Undo.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';

final class SaldiJournalTest extends SaldiRuntimeDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->env->db;
        $db->exec('DELETE FROM kassekladde');
        $db->exec('DELETE FROM tmpkassekl');
        $db->exec('DELETE FROM kladdeliste');
        foreach ([991001 => 'D', 991002 => 'S'] as $number => $type) {
            $db->insert('kontoplan', ['id' => $number, 'kontonr' => $number, 'regnskabsaar' => 4, 'kontotype' => $type, 'beskrivelse' => 'Runtime account', 'lukket' => '']);
        }
        foreach (['D' => 991003, 'K' => 991004] as $type => $number) {
            $db->insert('adresser', ['id' => $number, 'kontonr' => (string)$number, 'art' => $type, 'firmanavn' => 'Runtime party', 'lukket' => '']);
        }
    }

    private function input(?int $id = null): array
    {
        return ['kladde_id' => $id, 'date' => '2025-06-15', 'lines' => [['description' => 'First draft line', 'debit' => ['type' => 'F', 'account' => '991001'], 'credit' => ['type' => 'F', 'account' => '991002'], 'amount' => '1250.1'], ['description' => 'Second draft line', 'debit' => ['type' => 'D', 'account' => '991003'], 'amount' => '100'], ['description' => 'Third draft line', 'credit' => ['type' => 'K', 'account' => '991004'], 'amount' => '100']]];
    }

    private function journal(int $id = 500): void
    {
        $this->env->db->insert('kladdeliste', ['id' => $id, 'bogfort' => '-', 'kladdenote' => 'Existing', 'kladdedate' => '2025-01-01', 'hvem' => '', 'tidspkt' => '']);
    }

    public function testNewJournalIgnoresLaggingSequenceAndContinuesFiscalSeries(): void
    {
        $db = $this->env->db;
        $this->journal();
        $db->insert('kassekladde', ['kladde_id' => 500, 'bilag' => 42, 'transdate' => '2025-02-01', 'pos' => 1]);
        $db->value("SELECT setval('kladdeliste_id_seq', 1)");
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->input());
        self::assertSame('3 kladdelinjer: First draft line', $item['title']);
        self::assertSame('1250.10', $item['payload']['lines'][0]['amount']);
        self::assertSame(['Dato', 'Bilag', 'Tekst', 'Debet', 'Kredit', 'Beløb'], $item['card']['columns']);
        self::assertStringContainsString('991001 Runtime account', $item['card']['rows'][0][3]);
        $applied = $this->approve($item);
        $receipt = $applied['result'];
        self::assertSame(501, $receipt['kladde_id']);
        self::assertSame(43, $receipt['bilag']);
        self::assertTrue($receipt['created_kladde']);
        self::assertCount(3, $receipt['line_ids']);
        $rows = $db->all('SELECT * FROM kassekladde WHERE kladde_id = 501 ORDER BY pos');
        self::assertSame([43, 43, 43], array_column($rows, 'bilag'));
        self::assertSame([1, 2, 3], array_column($rows, 'pos'));
        self::assertSame(['', '', ''], array_column($rows, 'betal_id'));
        self::assertSame(0, $rows[0]['valuta']);
        self::assertSame('100.000', $rows[0]['valutakurs']);
        self::assertNull($rows[0]['forfaldsdate']);
        self::assertSame('2025-06-15', $rows[1]['forfaldsdate']);
        self::assertSame('', $rows[1]['k_type']);
        self::assertSame('0', $rows[1]['kredit']);
        self::assertSame('Forslag fra Saldi Assist', $db->value('SELECT kladdenote FROM kladdeliste WHERE id = 501'));
        self::assertSame("O'Brien", $db->value('SELECT oprettet_af FROM kladdeliste WHERE id = 501'));
        self::assertSame('-', $db->value('SELECT bogfort FROM kladdeliste WHERE id = 501'));
        self::assertNull($db->value('SELECT hvem FROM kladdeliste WHERE id = 501'));
        self::assertNull($db->value('SELECT tidspkt FROM kladdeliste WHERE id = 501'));
        $undo = $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash());
        self::assertSame('undone', $undo['status']);
        self::assertSame(3, $undo['result']['removed_lines']);
        self::assertTrue($undo['result']['removed_kladde']);
        self::assertNull($db->value('SELECT id FROM kladdeliste WHERE id = 501'));
        self::assertSame(1, (int)$db->value('SELECT COUNT(*) FROM kassekladde WHERE kladde_id = 500'));
    }

    public function testJournalTitlesUseTranslatedSingularAndPlural(): void
    {
        $ctx = $this->env->ctx();
        foreach ([1 => ['kladdelinje', 'kladdelinjer'], 2 => ['journal line', 'journal lines'], 3 => ['kladdelinje', 'kladdelinjer']] as $lang => $words) {
            $translated = new SaldiTenantContext($ctx->installId, $ctx->regnskabId, $ctx->db, $ctx->dbType, $ctx->userId, $ctx->username, $ctx->rights, $ctx->fiscalYear, $lang, $ctx->principal);
            foreach ([1, 2] as $count) {
                $input = $this->input();
                // The first line has both sides, so one or two copies of it always balance.
                $input['lines'] = array_fill(0, $count, $input['lines'][0]);
                $item = $this->inbox()->create($translated, 'journal.draft_entry', $input);
                self::assertSame($count . ' ' . $words[$count - 1] . ': First draft line', $item['title']);
            }
        }
    }

    public function testExistingJournalUsesOwnBilagAndAppendsPosUndoOnlyOwnRows(): void
    {
        $db = $this->env->db;
        $this->journal();
        $existing = $db->insert('kassekladde', ['kladde_id' => 500, 'bilag' => 9, 'pos' => 6, 'transdate' => '2025-03-01']);
        $db->insert('kassekladde', ['kladde_id' => 600, 'bilag' => 99, 'pos' => 1, 'transdate' => '2025-03-01']);
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->input(500));
        self::assertSame('500', $item['subject']['id']);
        $applied = $this->approve($item);
        self::assertSame(10, $applied['result']['bilag']);
        self::assertFalse($applied['result']['created_kladde']);
        self::assertSame([6, 7, 8, 9], array_column($db->all('SELECT pos FROM kassekladde WHERE kladde_id = 500 ORDER BY pos'), 'pos'));
        self::assertSame('', $db->value('SELECT hvem FROM kladdeliste WHERE id = 500'));
        self::assertSame('', $db->value('SELECT tidspkt FROM kladdeliste WHERE id = 500'));
        $undo = $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash());
        self::assertFalse($undo['result']['removed_kladde']);
        self::assertSame($existing, (int)$db->value('SELECT id FROM kassekladde WHERE kladde_id = 500'));
    }

    public function testOtherUserLockAndTemporaryRowsConflictThenRetry(): void
    {
        $db = $this->env->db;
        $this->journal();
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->input(500));
        $db->exec('UPDATE kladdeliste SET hvem = ?, tidspkt = ? WHERE id = 500', ['Other user', microtime()]);
        self::assertSame('journal_in_use', $this->error('CONFLICT', fn() => $this->approve($item))->detail['reason']);
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM kassekladde'));
        $db->exec("UPDATE kladdeliste SET tidspkt = '0 1' WHERE id = 500");
        $db->exec('INSERT INTO tmpkassekl (kladde_id) VALUES (?)', [500]);
        self::assertSame('journal_unsaved', $this->error('CONFLICT', fn() => $this->approve($item))->detail['reason']);
        $db->exec('DELETE FROM tmpkassekl WHERE kladde_id = 500');
        self::assertSame('applied', $this->approve($item)['status']);
        self::assertSame(2, (int)$db->value("SELECT COUNT(*) FROM decision_log WHERE event = 'apply_failed'"));
    }

    public function testChangedRowsStaleButPositionOnlyChangesAreAllowed(): void
    {
        $db = $this->env->db;
        $this->journal();
        $id = $db->insert('kassekladde', ['kladde_id' => 500, 'bilag' => 9, 'pos' => 1, 'beskrivelse' => 'Original', 'transdate' => '2025-01-01']);
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->input(500));
        $db->exec('UPDATE kassekladde SET beskrivelse = ? WHERE id = ?', ['Edited', $id]);
        $this->error('STALE_CONTEXT', fn() => $this->approve($item));
        $db->exec('UPDATE kassekladde SET beskrivelse = ?, pos = 50 WHERE id = ?', ['Original', $id]);
        self::assertSame('applied', $this->approve($item)['status']);
    }

    public function testClosedFiscalYearAndVatPeriodAtProposalAndApproval(): void
    {
        $db = $this->env->db;
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->input());
        $db->exec("UPDATE grupper SET box5 = 'off' WHERE art = 'RA' AND kodenr = 4");
        $this->error('CLOSED_PERIOD', fn() => $this->approve($item));
        $db->exec("UPDATE grupper SET box5 = 'on' WHERE art = 'RA' AND kodenr = 4");
        $db->exec('CREATE TABLE moms_periode_luk (id serial PRIMARY KEY, kalender_aar int, kalender_maaned int, status varchar(16))');
        $db->insert('moms_periode_luk', ['kalender_aar' => 2025, 'kalender_maaned' => 6, 'status' => 'closed']);
        $this->error('CLOSED_PERIOD', fn() => $this->approve($item));
        $this->error('CLOSED_PERIOD', fn() => $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->input()));
        $this->error('CLOSED_PERIOD', fn() => $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', array_replace($this->input(), ['date' => '2026-01-01'])));
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM kladdeliste'));
    }

    public function testUndoRefusesEditedDeletedOrPostedRows(): void
    {
        $ctx = $this->env->ctx();
        $item = $this->inbox()->create($ctx, 'journal.draft_entry', $this->input());
        $receipt = $this->approve($item)['result'];
        $id = $receipt['line_ids'][0];
        $db = $this->env->db;
        $db->exec('UPDATE kassekladde SET amount = ? WHERE id = ?', ['1.00', $id]);
        self::assertFalse($this->inbox()->get($ctx, $item['id'])['can_undo']);
        $this->error('CONFLICT', fn() => $this->inbox()->undo($ctx, $item['id'], $ctx->tenantHash()));
        $db->exec('UPDATE kassekladde SET amount = ? WHERE id = ?', ['1250.10', $id]);
        $db->exec("UPDATE kladdeliste SET bogfort = 'V' WHERE id = ?", [$receipt['kladde_id']]);
        $this->error('CONFLICT', fn() => $this->inbox()->undo($ctx, $item['id'], $ctx->tenantHash()));
        $db->exec("UPDATE kladdeliste SET bogfort = '-' WHERE id = ?", [$receipt['kladde_id']]);
        $db->exec('DELETE FROM kassekladde WHERE id = ?', [$id]);
        $this->error('CONFLICT', fn() => $this->inbox()->undo($ctx, $item['id'], $ctx->tenantHash()));
    }

    public function testAmountAccountDateAndLineValidation(): void
    {
        $ctx = $this->env->ctx();
        $good = $this->input();
        foreach (['0', '0.00', '-1', '1.001', '1000000000000', '1e3'] as $amount) {
            $bad = $good;
            $bad['lines'][0]['amount'] = $amount;
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($ctx, 'journal.draft_entry', $bad));
        }
        foreach ([[], array_fill(0, 21, $good['lines'][0]), [['description' => 'No side', 'amount' => '1']]] as $lines) {
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($ctx, 'journal.draft_entry', array_replace($good, ['lines' => $lines])));
        }
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($ctx, 'journal.draft_entry', array_replace($good, ['date' => '2025-02-30'])));
        foreach (['X', 'F', 'D', 'K'] as $type) {
            $bad = $good;
            $bad['lines'][0]['debit'] = ['type' => $type, 'account' => '999999'];
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($ctx, 'journal.draft_entry', $bad));
        }
        $this->env->db->exec("UPDATE kontoplan SET lukket = 'on' WHERE id = 991001");
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($ctx, 'journal.draft_entry', $good));
        $otherCurrency = new SaldiTenantContext($ctx->installId, $ctx->regnskabId, $ctx->db, $ctx->dbType, $ctx->userId, $ctx->username, $ctx->rights, $ctx->fiscalYear, 1, $ctx->principal, null, [], 'EUR');
        self::assertSame('currency_unsupported', $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($otherCurrency, 'journal.draft_entry', $good))->detail['reason']);
    }

    public function testApprovalRechecksAccountAndPinsFiscalYearAndVatColumns(): void
    {
        $db = $this->env->db;
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->input());
        $db->exec("UPDATE adresser SET lukket = 'on' WHERE id = 991003");
        $this->error('VALIDATION_ERROR', fn() => $this->approve($item));
        $db->exec("UPDATE adresser SET lukket = '' WHERE id = 991003");
        $db->exec('ALTER TABLE kassekladde ADD COLUMN debetvat text');
        $db->exec('ALTER TABLE kassekladde ADD COLUMN kreditvat text');
        $db->exec('UPDATE brugere SET regnskabsaar = 3 WHERE id = 910001');
        $applied = $this->approve($item);
        self::assertSame(4, $applied['payload']['fiscal_year']);
        self::assertSame('', $db->value('SELECT debetvat FROM kassekladde WHERE id = ?', [$applied['result']['line_ids'][0]]));
        self::assertSame('', $db->value('SELECT kreditvat FROM kassekladde WHERE id = ?', [$applied['result']['line_ids'][0]]));
    }

    public function testOneSidedLinesMustBalanceWithinTheDraft(): void
    {
        $ctx = $this->env->ctx();
        $good = $this->input();
        $debitOnly = $good;
        array_pop($debitOnly['lines']);
        $e = $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($ctx, 'journal.draft_entry', $debitOnly));
        self::assertSame('unbalanced', $e->detail['reason']);
        self::assertSame('100.00', $e->detail['difference']);
        self::assertSame(['$.lines'], $e->detail['fields']);
        $creditHeavy = $good;
        $creditHeavy['lines'][2]['amount'] = '100.05';
        self::assertSame('-0.05', $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($ctx, 'journal.draft_entry', $creditHeavy))->detail['difference']);
        // A two-sided line balances itself whatever its amount; split one-sided lines may net across several lines.
        $split = $good;
        $split['lines'][1]['amount'] = '60.5';
        $split['lines'][] = ['description' => 'Fourth draft line', 'debit' => ['type' => 'F', 'account' => '991001'], 'amount' => '39.50'];
        self::assertSame('open', $this->inbox()->create($ctx, 'journal.draft_entry', $split)['status']);
        self::assertSame(0, (int)$this->env->db->value("SELECT COUNT(*) FROM inbox_items WHERE kind = 'journal.draft_entry' AND title LIKE '3 %'"));
        // decide() validates again, so a stored unbalanced payload cannot be approved either.
        $item = $this->inbox()->create($ctx, 'journal.draft_entry', $good);
        $payload = $item['payload'];
        array_pop($payload['lines']);
        $this->env->db->exec('UPDATE inbox_items SET payload = ? WHERE id = ?', [saldi_runtime_json($payload), $item['id']]);
        $stored = $this->inbox()->get($ctx, $item['id']);
        self::assertSame('unbalanced', $this->error('VALIDATION_ERROR', fn() => $this->approve($stored))->detail['reason']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
    }

    public function testUndoSurvivesAnUnchangedKassekladdeSave(): void
    {
        $ctx = $this->env->ctx();
        $this->journal();
        $item = $this->inbox()->create($ctx, 'journal.draft_entry', $this->input(500));
        $receipt = $this->approve($item)['result'];
        $db = $this->env->db;
        // finans/kassekladde.php rewrites every saved row; without a betal_id it writes '' (and NULL forfaldsdate for F-only rows).
        foreach ($db->all('SELECT id, d_type, k_type, forfaldsdate FROM kassekladde WHERE kladde_id = 500') as $row) {
            if ($row['forfaldsdate'] === null) {
                $db->exec("UPDATE kassekladde SET forfaldsdate = NULL, betal_id = '' WHERE id = ?", [$row['id']]);
            } else {
                $db->exec("UPDATE kassekladde SET betal_id = '' WHERE id = ?", [$row['id']]);
            }
        }
        self::assertCount(3, $receipt['line_ids']);
        self::assertTrue($this->inbox()->get($ctx, $item['id'])['can_undo']);
        self::assertSame('undone', $this->inbox()->undo($ctx, $item['id'], $ctx->tenantHash())['status']);
    }
}
