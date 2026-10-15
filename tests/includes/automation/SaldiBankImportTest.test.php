<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiBankImportTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// 20261005 CDX/LH Add regression coverage for companion review corrections.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiBankTestCase.php';

final class SaldiBankImportTest extends SaldiBankTestCase
{
    public function testStagingRetainsFiscalYearWarningsHashDuplicatesAndPurgesOldBatches(): void
    {
        $db = $this->env->db;
        $first = $this->stage([$this->row(), $this->row('5.00', 'Udenfor', '2024-12-31')]);
        self::assertSame(['new', 'ignored'], $this->statuses($first['batch_id']));
        self::assertCount(1, $first['warnings']);
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM kassekladde'));
        $db->exec("UPDATE bank_import_lines SET status = 'proposed' WHERE batch_id = ? AND status = 'new'", [$first['batch_id']]);
        $second = $this->stage([$this->row('1234.560', '  FAKTURA   501  ')]);
        self::assertSame(1, $second['duplicates']);
        self::assertSame(['duplicate'], $this->statuses($second['batch_id']));
        $db->exec('UPDATE bank_import_lines SET created_at = ? WHERE batch_id = ?', [gmdate('Y-m-d H:i:s', time() - 31 * 86400), $first['batch_id']]);
        $this->stage([$this->row('7.00', 'Nyt beløb')]);
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM bank_import_lines WHERE batch_id = ?', [$first['batch_id']]));
        self::assertSame(1, (int)$db->value('SELECT COUNT(*) FROM bank_import_lines WHERE batch_id = ?', [$second['batch_id']]));
    }

    public function testExistingJournalDuplicatesRespectBankSideSignAndTwoDayWindow(): void
    {
        $db = $this->env->db;
        $incoming = $this->line(['debet' => '5500', 'd_type' => 'F', 'amount' => '100.000', 'transdate' => '2025-06-02']);
        $outgoing = $this->line(['debet' => '2800', 'k_type' => 'F', 'kredit' => '5500', 'amount' => '25.000', 'transdate' => '2025-06-06']);
        $this->line(['debet' => '5500', 'd_type' => 'F', 'amount' => '17.000', 'transdate' => '2025-06-01']);
        $batch = $this->stage([$this->row('100.00'), $this->row('-25.00'), $this->row('17.00'), $this->row('-100.00')]);
        self::assertSame(['duplicate', 'duplicate', 'new', 'new'], $this->statuses($batch['batch_id']));
        $proposals = $db->all('SELECT proposal FROM bank_import_lines WHERE batch_id = ? ORDER BY line_no', [$batch['batch_id']]);
        self::assertSame($incoming, json_decode($proposals[0]['proposal'], true)['line_id']);
        self::assertSame($outgoing, json_decode($proposals[1]['proposal'], true)['line_id']);
    }

    public function testInvalidBankAccountsFailAndInvalidLaterRowsRollBackEntireBatch(): void
    {
        foreach (['2800', '5900', '5999', '6000', '999999', "5500' OR 1=1"] as $account) {
            $this->error('VALIDATION_ERROR', fn() => SaldiBankImport::stage($this->env->ctx(), $this->env->db, $account, ['rows' => [$this->row()]]));
        }
        $this->error('VALIDATION_ERROR', fn() => $this->stage([$this->row(), $this->row('not-an-amount')]));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM bank_import_lines'));
    }

    public function testBankAccountLeadingZerosUseTheSameStagingIdentity(): void
    {
        $first = SaldiBankImport::stage($this->env->ctx(), $this->env->db, '05500', [
            'rows' => [$this->row()], 'warnings' => [],
        ]);
        self::assertSame('5500', $this->env->db->value(
            'SELECT bank_account FROM bank_import_lines WHERE batch_id = ?',
            [$first['batch_id']]
        ));
        self::assertSame(0, $this->stage([$this->row()])['duplicates']);
        self::assertSame(['ignored'], $this->statuses($first['batch_id']));
        self::assertSame(1, (int)$this->env->db->value('SELECT COUNT(DISTINCT line_hash) FROM bank_import_lines'));
    }
    public function testAbandonedPreviewIsSupersededAndCanBeReuploaded(): void
    {
        $this->open();
        $first = $this->stage([$this->row()]);
        $second = $this->stage([$this->row()]);
        self::assertSame(0, $second['duplicates']);
        self::assertSame(['ignored'], $this->statuses($first['batch_id']));
        self::assertSame(['new'], $this->statuses($second['batch_id']));
        $item = $this->propose($second['batch_id']);
        self::assertSame(['proposed'], $this->statuses($second['batch_id']));
        self::assertSame(1, $this->stage([$this->row()])['duplicates']);
        $this->approve($item);
    }

    public function testIdenticalMovementsConsumeOnlyOneExistingJournalLine(): void
    {
        $this->open('501', '100.00');
        $this->line(['debet' => '5500', 'd_type' => 'F', 'k_type' => 'D', 'kredit' => '2005',
            'amount' => '100.000', 'transdate' => '2025-06-04']);
        $batch = $this->stage([$this->row('100.00'), $this->row('100.00')]);
        self::assertSame(['duplicate', 'new'], $this->statuses($batch['batch_id']));
        $applied = $this->approve($this->propose($batch['batch_id']));
        self::assertCount(1, $applied['result']['line_ids']);
        self::assertSame(2, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
    }

    public function testLongDescriptionsAndReferencesAreTruncatedWithWarnings(): void
    {
        $batch = $this->stage([$this->row('-10.00', str_repeat('Æ', 300), '2025-06-04', ['reference' => str_repeat('R', 120)])]);
        self::assertCount(2, $batch['warnings']);
        $row = $this->env->db->one('SELECT description, reference FROM bank_import_lines WHERE batch_id = ?', [$batch['batch_id']]);
        self::assertSame(255, mb_strlen($row['description']));
        self::assertSame(100, mb_strlen($row['reference']));
    }

    public function testCamtAccountsRequireMappingAndOnlySelectedStatementIsStaged(): void
    {
        $entry = '<Ntry><Amt Ccy="DKK">10</Amt><CdtDbtInd>CRDT</CdtDbtInd><BookgDt><Dt>2025-06-04</Dt></BookgDt></Ntry>';
        $xml = '<Document><BkToCstmrStmt><Stmt><Acct><Id><IBAN>DK001111</IBAN></Id></Acct>' . $entry
            . '</Stmt><Stmt><Acct><Id><Othr><Id>2222</Id></Othr></Id></Acct>' . $entry . '</Stmt></BkToCstmrStmt></Document>';
        $parsed = SaldiBankStatementParser::parse($xml);
        $e = $this->error('VALIDATION_ERROR', fn() => SaldiBankImport::stage($this->env->ctx(), $this->env->db, '5500', $parsed));
        self::assertStringContainsString('DK001111, 2222', $e->detail['warning_da']);
        $this->setting('assistant', 'bank_iban.5500', 'DK00 1111');
        $batch = SaldiBankImport::stage($this->env->ctx(), $this->env->db, '5500', $parsed);
        self::assertCount(1, $batch['lines']);
        self::assertSame(1, (int)$this->env->db->value('SELECT COUNT(*) FROM bank_import_lines'));
    }

    public function testStagedMatchesAreCachedForRepeatedPreview(): void
    {
        $this->open();
        $batch = $this->stage([$this->row()]);
        $cached = $this->env->db->value('SELECT proposal FROM bank_import_lines WHERE batch_id = ?', [$batch['batch_id']]);
        self::assertSame('high', json_decode($cached, true)['match']['confidence']);
        $this->env->db->exec('DELETE FROM openpost');
        $preview = SaldiBankTools::listLines($this->env->ctx(), $this->env->db, ['batch_id' => $batch['batch_id']]);
        self::assertSame('501', $preview['data']['rows'][0]['match']['invoice']);
        $this->error('VALIDATION_ERROR', fn() => $this->propose($batch['batch_id']));
    }

    public function testAppliedHashAndJournalRowConsumeOneMovementTogether(): void
    {
        $this->open('501', '100.00');
        $first = $this->stage([$this->row('100.00')]);
        $this->approve($this->propose($first['batch_id']));
        $second = $this->stage([$this->row('100.00'), $this->row('100.00')]);
        self::assertSame(['duplicate', 'new'], $this->statuses($second['batch_id']));
        $this->approve($this->propose($second['batch_id'], 'bank-second'));
        self::assertSame(2, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
    }

    public function testCamtUnknownMultipleAccountsCannotBeMerged(): void
    {
        $entry = '<Ntry><Amt Ccy="DKK">10</Amt><CdtDbtInd>CRDT</CdtDbtInd><BookgDt><Dt>2025-06-04</Dt></BookgDt></Ntry>';
        $xml = '<Document><BkToCstmrStmt><Stmt>' . $entry . '</Stmt><Stmt>' . $entry . '</Stmt></BkToCstmrStmt></Document>';
        $parsed = SaldiBankStatementParser::parse($xml);
        self::assertSame(2, $parsed['statement_count']);
        $error = $this->error('VALIDATION_ERROR', fn() => SaldiBankImport::stage($this->env->ctx(), $this->env->db, '5500', $parsed));
        self::assertStringContainsString('ukendt konto', $error->detail['warning_da']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM bank_import_lines'));
    }

    public function testIdenticalMovementsCanBeApprovedSeparatelyWithinOneBatch(): void
    {
        $this->open('501', '100.00');
        $batch = $this->stage([$this->row('100.00'), $this->row('100.00')]);
        $first = $this->propose($batch['batch_id'], 'separate-first', ['line_ids' => [$batch['lines'][0]['line_id']]]);
        $this->approve($first);
        self::assertSame(1, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
        $second = $this->propose($batch['batch_id'], 'separate-second', ['line_ids' => [$batch['lines'][1]['line_id']]]);
        $this->approve($second);
        self::assertSame(2, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
    }

}
