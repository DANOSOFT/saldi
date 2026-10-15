<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/bank_integration/AiiaImportCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Created.

require_once __DIR__ . '/../support/BankImportFixture.php';

final class AiiaImportCharacterizationTest extends BankImportFixture
{
    public function test_aiia_journal_and_display_mirror_match_the_legacy_post(): void
    {
        $journal = $this->journal();
        $this->runImport('run_aiia_import.php', $journal);
        $rows = $this->journalRows($journal);
        $this->assertSnapshot('aiia', $rows);
        $this->assertSame([
            [1,'2026-09-14','CIRCLE K ÅRHUS','tx-one','612.500',0,6471],
            [2,'2026-09-15','Invoice payment','tx-two','1000.000',6471,0],
            [3,'2026-09-16','EUR purchase','tx-eur','99.990',0,6471],
        ], array_map(static fn(array $r): array => [(int)$r['bilag'],$r['transdate'],$r['beskrivelse'],$r['faktura'],
            $r['amount'],(int)$r['debet'],(int)$r['kredit']], $rows));
        $mirrors = $this->journalRows($journal, 'tmpkassekl');
        $this->assertSnapshot('aiia-mirror', $mirrors);
        foreach ($mirrors as $i => $mirror) {
            $this->assertSame((string)($i+1), $mirror['lobenr']);
            foreach (['bilag','transdate','beskrivelse','faktura','amount','debet','kredit'] as $field) {
                if ($field === 'amount') {
                    $this->assertSame((float)$rows[$i][$field], (float)$mirror[$field]);
                } else {
                    $this->assertSame($rows[$i][$field], $mirror[$field]);
                }
            }
        }
        $second = $this->journal();
        $this->runImport('run_aiia_import.php', $second);
        $this->assertSame($rows, $this->journalRows($second));
        $this->assertSame($mirrors, $this->journalRows($second, 'tmpkassekl'));
    }
    public function test_same_aiia_ids_reuse_identity_but_account_namespace_distinguishes_them(): void
    {
        $before = (int)CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_lines')['n'];
        $journal = $this->journal();
        $this->runImport('run_aiia_import.php', $journal);
        $this->assertSame($before, (int)CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_lines')['n']);
        $this->assertSame('3', CharacterizationEnv::one(self::$tenant,
            'SELECT count(*) AS n FROM bank_line_journal_links l JOIN kassekladde k ON k.id=l.kassekladde_id WHERE k.kladde_id=$1', [$journal])['n']);
        $rows = CharacterizationEnv::rows(self::$tenant, "SELECT provider_ref,amount,currency,balance FROM bank_lines WHERE source='aiia' ORDER BY id");
        $this->assertSame([
            ['provider_ref'=>'tx-one','amount'=>'-612.50','currency'=>'DKK','balance'=>null],
            ['provider_ref'=>'tx-two','amount'=>'1000.00','currency'=>'DKK','balance'=>null],
            ['provider_ref'=>'tx-eur','amount'=>'-99.99','currency'=>'EUR','balance'=>'500.00'],
        ], $rows);
        $this->runImport('run_aiia_import.php', $this->journal(), ['account_id'=>'bank-account-two']);
        $this->assertSame($before+3, (int)CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_lines')['n']);
        $this->assertSame('0', CharacterizationEnv::one(self::$tenant, "SELECT count(*) AS n FROM bank_lines WHERE state='matched'")['n']);
    }

    public function test_legacy_post_without_currency_or_balance_preserves_the_journal_rows(): void
    {
        $journal = $this->journal();
        $this->runImport('run_aiia_import.php', $journal, ['legacy_payload'=>true]);
        $this->assertSnapshot('aiia', $this->journalRows($journal));
        $this->assertSnapshot('aiia-mirror', $this->journalRows($journal, 'tmpkassekl'));
    }

    public function test_failed_identity_write_rolls_back_its_journal_and_mirror_but_keeps_prior_committed_line(): void
    {
        $journal = $this->journal();
        $this->rejectIdentityWrite(true);
        try {
            $result = CharacterizationEnv::runChild(__DIR__ . '/../support/run_aiia_import.php',
                [json_encode(['reject_second'=>true]),$journal,self::$session]);
            $this->assertNotSame(0, $result['exit']);
            $this->assertStringContainsString('Bank line database command failed', $result['stderr']);
        } finally {
            $this->rejectIdentityWrite(false);
        }
        $this->assertCount(1, $this->journalRows($journal));
        $this->assertCount(1, $this->journalRows($journal, 'tmpkassekl'));
        $this->assertSame('tx-one', $this->journalRows($journal)[0]['faktura']);
        $this->assertSame('1', CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_line_journal_links l
            JOIN kassekladde k ON k.id=l.kassekladde_id WHERE k.kladde_id=$1', [$journal])['n']);
        $this->assertSame('1', CharacterizationEnv::one(self::$tenant, 'SELECT line_count FROM bank_line_imports WHERE kladde_id=$1', [$journal])['line_count']);
    }

}
