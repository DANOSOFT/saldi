<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/finans/BankImportCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Cover accented legacy CSV descriptions and identity reuse across reimports.
// 20261007 CDX/LH Assert the restored tenant SQL trail on the web write path.

require_once __DIR__ . '/../support/BankImportFixture.php';

require_once __DIR__ . '/../support/ModificationTrail.php';

final class BankImportCharacterizationTest extends BankImportFixture
{
    public function test_latin9_descriptions_keep_distinct_identities_and_reuse_them_on_reimport(): void
    {
        $before = (int)CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_lines')['n'];
        $identities = [];
        foreach (['latin9-cafe.tsv' => 'Café', 'latin9-the.tsv' => 'Thé'] as $fixture => $description) {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $journal = $this->journal();
                $this->runImport('run_bankimport_page.php', $journal, ['fixture' => $fixture, 'latin9' => true]);
                $row = CharacterizationEnv::one(self::$tenant, 'SELECT b.id,b.line_key,b.text,b.transdate,b.amount,b.balance,
                    k.beskrivelse,k.amount AS journal_amount,k.saldo,k.kredit FROM bank_lines b
                    JOIN bank_line_journal_links l ON l.bank_line_id=b.id JOIN kassekladde k ON k.id=l.kassekladde_id
                    WHERE k.kladde_id=$1', [$journal]);
                $this->assertSame($description, $row['text']);
                // Pin the existing db_escape_string() UTF-8 conversion before the LATIN9 journal write.
                $this->assertSame(mb_convert_encoding($description, 'UTF-8', 'ISO-8859-15'), $row['beskrivelse']);
                $this->assertSame('2026-09-14', $row['transdate']);
                $this->assertSame('-75.00', $row['amount']);
                $this->assertSame('9000.00', $row['balance']);
                $this->assertSame('75.000', $row['journal_amount']);
                $this->assertSame('9000.000', $row['saldo']);
                $this->assertSame('6471', $row['kredit']);
                if ($attempt === 0) {
                    $identities[$fixture] = $row;
                } else {
                    $this->assertSame($identities[$fixture], $row);
                }
            }
        }
        $this->assertNotSame($identities['latin9-cafe.tsv']['id'], $identities['latin9-the.tsv']['id']);
        $this->assertNotSame($identities['latin9-cafe.tsv']['line_key'], $identities['latin9-the.tsv']['line_key']);
        $this->assertSame($before+2, (int)CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_lines')['n']);
        foreach ($identities as $identity) {
            $this->assertSame('2', CharacterizationEnv::one(self::$tenant,
                'SELECT count(*) AS n FROM bank_line_journal_links WHERE bank_line_id=$1', [$identity['id']])['n']);
        }
    }

    public function test_csv_journal_rows_match_the_legacy_fixtures(): void
    {
        $cases = [
            'fik.tsv' => [[10,'IK 0000000001001000421','F',6471,'',0,'','1000.000',0,'11000.000']],
            'card-fee.tsv' => [[10,'ABCDEFGHIJKLha_00042','F',6471,'F',0,'42','1002.000',0,'12002.000'],
                [10,'ABCDEFGHIJKLha_00042','F',0,'D',1001,'42','1000.000',0,'12002.000'],
                [10,'ABCDEFGHIJKLha_00042','F',0,'F',7770,'42','2.000',0,'12002.000']],
            'outgoing.tsv' => [[10,'CIRCLE K ÅRHUS','',0,'F',6471,null,'612.500',0,'10000.000']],
            'refund.tsv' => [[10,'Afr: 1001','D',1001,'F',6471,null,'200.000',0,'9800.000']],
            'eur.tsv' => [[10,'EUR purchase','',0,'F',6471,null,'99.990',1,'500.000']],
            'duplicates.tsv' => [[10,'Coffee','',0,'F',6471,null,'50.000',0,null],
                [11,'Coffee','',0,'F',6471,null,'50.000',0,null]],
        ];
        foreach ($cases as $fixture => $expected) {
            $journal = $this->journal();
            $trail = ModificationTrail::read(CharacterizationEnv::testDb());
            $this->runImport('run_bankimport_page.php', $journal, ['fixture' => $fixture]);
            ModificationTrail::assertWrite(CharacterizationEnv::testDb(), $trail, 'insert into kassekladde\\b');
            ModificationTrail::assertWrite(CharacterizationEnv::testDb(), $trail, 'INSERT INTO bank_lines\\b');
            $rows = $this->journalRows($journal);
            $this->assertSnapshot(basename($fixture, '.tsv'), $rows);
            $actual = array_map(static fn(array $r): array => [(int)$r['bilag'],$r['beskrivelse'],$r['d_type'],(int)$r['debet'],
                $r['k_type'],(int)$r['kredit'],$r['faktura'],$r['amount'],(int)$r['valuta'],$r['saldo']], $rows);
            $this->assertSame($expected, $actual, $fixture);
            foreach ($rows as $row) {
                $this->assertSame('2026-09-14', $row['transdate']);
                $this->assertSame('0', $row['afd']);
            }
            // Pin every persisted column, including defaults, in a second identical import.
            $second = $this->journal();
            $this->runImport('run_bankimport_page.php', $second, ['fixture' => $fixture]);
            $this->assertSame($rows, $this->journalRows($second), $fixture);
        }
    }
    public function test_reimports_overlap_and_identical_purchases_keep_identity_and_link_every_row(): void
    {
        $journal = $this->journal();
        $this->runImport('run_bankimport_page.php', $journal, ['fixture' => 'outgoing.tsv']);
        $before = (int)CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_lines')['n'];
        $identity = CharacterizationEnv::one(self::$tenant, 'SELECT b.* FROM bank_lines b JOIN bank_line_journal_links l ON l.bank_line_id=b.id
            JOIN kassekladde k ON k.id=l.kassekladde_id WHERE k.kladde_id=$1', [$journal]);
        $this->assertSame('-612.50', $identity['amount']);
        $this->assertSame('10000.00', $identity['balance']);
        $this->runImport('run_bankimport_page.php', $this->journal(), ['fixture' => 'outgoing.tsv']);
        $this->assertSame($before, (int)CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_lines')['n']);
        $this->runImport('run_bankimport_page.php', $this->journal(), ['fixture' => 'overlap.tsv']);
        $this->assertSame($before+1, (int)CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_lines')['n']);
        $this->assertGreaterThanOrEqual(3, (int)CharacterizationEnv::one(self::$tenant,
            'SELECT count(*) AS n FROM bank_line_journal_links WHERE bank_line_id=$1', [$identity['id']])['n']);
        $journal = $this->journal();
        $this->runImport('run_bankimport_page.php', $journal, ['fixture' => 'duplicates.tsv']);
        $this->assertSame('2', CharacterizationEnv::one(self::$tenant, 'SELECT count(DISTINCT l.bank_line_id) AS n
            FROM bank_line_journal_links l JOIN kassekladde k ON k.id=l.kassekladde_id WHERE k.kladde_id=$1', [$journal])['n']);
        $this->assertSame('0', CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM kassekladde k
            LEFT JOIN bank_line_journal_links l ON l.kassekladde_id=k.id JOIN kladdeliste kl ON kl.id=k.kladde_id
            WHERE kl.kladdenote=\'bank-char\' AND l.bank_line_id IS NULL')['n']);
        foreach (CharacterizationEnv::rows(self::$tenant, 'SELECT i.id,i.line_count,count(DISTINCT l.bank_line_id) AS registered
            FROM bank_line_imports i LEFT JOIN bank_line_journal_links l ON l.import_id=i.id GROUP BY i.id') as $import) {
            $this->assertSame($import['registered'], $import['line_count']);
        }
    }

    public function test_current_document_links_derive_matched_and_detach_restores_each_stored_state(): void
    {
        $this->selectLibrary();
        $journal = $this->journal();
        $this->runImport('run_bankimport_page.php', $journal, ['fixture' => 'outgoing.tsv']);
        $row = CharacterizationEnv::one(self::$tenant, 'SELECT b.*,l.kassekladde_id FROM bank_lines b
            JOIN bank_line_journal_links l ON l.bank_line_id=b.id JOIN kassekladde k ON k.id=l.kassekladde_id
            WHERE k.kladde_id=$1', [$journal]);
        $pool = CharacterizationEnv::one(self::$tenant,
            "INSERT INTO pool_files(filename,status,bank_line_id) VALUES ('bank-state.pdf','ubehandlet',$1) RETURNING id", [$row['id']]);
        foreach (['open','requested','received'] as $state) {
            CharacterizationEnv::rows(self::$tenant, 'UPDATE bank_lines SET state=$1,pool_file_id=$2 WHERE id=$3', [$state,$pool['id'],$row['id']]);
            $this->assertSame($state, bank_line_effective_state($row));
            // This is the source/source_id relation written by insertDoc.php, independent of pool identity.
            $doc = CharacterizationEnv::one(self::$tenant, "INSERT INTO documents(source,source_id,filename)
                VALUES ('kassekladde',$1,'web-attach.pdf') RETURNING id", [$row['kassekladde_id']]);
            $this->assertSame('matched', bank_line_effective_state($row));
            $this->assertSame($state, CharacterizationEnv::one(self::$tenant, 'SELECT state FROM bank_lines WHERE id=$1', [$row['id']])['state']);
            CharacterizationEnv::rows(self::$tenant, 'DELETE FROM documents WHERE id=$1', [$doc['id']]);
            $this->assertSame($state, bank_line_effective_state($row));
        }
        CharacterizationEnv::rows(self::$tenant, "UPDATE pool_files SET status='deleted' WHERE id=$1", [$pool['id']]);
        $this->assertSame('open', bank_line_effective_state($row));
        $reminder = CharacterizationEnv::one(self::$tenant, "INSERT INTO reminders(target_subject,message,created_by,bank_line_id)
            VALUES ('all','bank reminder','web',$1) RETURNING id", [$row['id']]);
        $notification = CharacterizationEnv::one(self::$tenant, "INSERT INTO notifications(type,title,message,created_by,reminder_id)
            VALUES ('reminder','Bank','Reminder','web',$1) RETURNING id", [$reminder['id']]);
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO notification_recipients(notification_id,subject) VALUES ($1,'bank-test/account/1')", [$notification['id']]);
        $this->assertSame('requested', bank_line_effective_state($row));
        CharacterizationEnv::rows(self::$tenant, 'UPDATE notification_recipients SET resolved_at=now() WHERE notification_id=$1', [$notification['id']]);
        $this->assertSame('open', bank_line_effective_state($row));
        // A stale document for a deleted journal row cannot match this identity.
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO documents(source,source_id,filename) VALUES ('kassekladde',$1,'stale.pdf')", [$row['kassekladde_id']]);
        CharacterizationEnv::rows(self::$tenant, 'DELETE FROM kassekladde WHERE id=$1', [$row['kassekladde_id']]);
        $this->assertSame('open', bank_line_effective_state($row));
    }

    public function test_missing_projection_paging_and_current_row_follow_live_relationships(): void
    {
        $this->selectLibrary();
        $one = bank_lines_missing(['account'=>6471], 1, 0);
        $this->assertGreaterThan(0, $one['total']);
        $this->assertCount(1, $one['items']);
        $empty = bank_lines_missing(['account'=>6471], 1, 10000);
        $this->assertSame($one['total'], $empty['total']);
        $this->assertSame([], $empty['items']);
        $row = $one['items'][0];
        $this->assertContains($row['state'], ['open','requested']);
        $this->assertIsString($row['amount']);
        $this->assertMatchesRegularExpression('/^-?[0-9]+\.[0-9]{2}$/', $row['amount']);
        $this->assertSame($row['kassekladde_id'], (int)CharacterizationEnv::one(self::$tenant,
            'SELECT max(k.id) AS id FROM bank_line_journal_links l JOIN kassekladde k ON k.id=l.kassekladde_id WHERE l.bank_line_id=$1', [$row['id']])['id']);
        $pool = CharacterizationEnv::one(self::$tenant, "INSERT INTO pool_files(filename,status,bank_line_id)
            VALUES ('missing-answer.pdf','ubehandlet',$1) RETURNING id", [$row['id']]);
        $this->assertSame($one['total']-1, bank_lines_missing(['account'=>6471], 100, 0)['total']);
        CharacterizationEnv::rows(self::$tenant, "UPDATE pool_files SET status='deleted' WHERE id=$1", [$pool['id']]);
        $this->assertSame($one['total'], bank_lines_missing(['account'=>6471], 100, 0)['total']);
        CharacterizationEnv::rows(self::$tenant, "UPDATE bank_lines SET state='ignored' WHERE id=$1", [$row['id']]);
        $this->assertSame($one['total']-1, bank_lines_missing(['account'=>6471], 100, 0)['total']);
        $this->assertSame('ignored', bank_lines_missing(['state'=>'ignored'], 100, 0)['items'][0]['state']);
        $this->assertSame(0, bank_lines_missing(['account'=>98765,'state'=>'all'], 100, 0)['total']);
    }

    public function test_two_parallel_file_imports_share_unique_keys_and_keep_all_journal_links(): void
    {
        $journalIds = [$this->journal(), $this->journal()];
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $sessions = ['bank' . bin2hex(random_bytes(14)), 'bank' . bin2hex(random_bytes(14))];
        foreach ($sessions as $session) {
            CharacterizationEnv::rows($master, 'INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
                SELECT $1,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id FROM online WHERE session_id=$2', [$session,self::$session]);
        }
        CharacterizationEnv::rows(self::$tenant, 'BEGIN');
        CharacterizationEnv::rows(self::$tenant, 'LOCK TABLE bank_lines IN SHARE MODE');
        $children = [];
        try {
            foreach ($journalIds as $i => $journal) {
                $process = proc_open([PHP_BINARY,__DIR__ . '/../support/run_bankimport_page.php',
                    json_encode(['fixture'=>'duplicates.tsv']), (string)$journal,$sessions[$i]],
                    [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $children[] = [$process,$pipes];
            }
            $observer = CharacterizationEnv::connect(CharacterizationEnv::testDb());
            $deadline = microtime(true)+15;
            do {
                $waiting = (int)CharacterizationEnv::one($observer,
                    "SELECT count(*) AS n FROM pg_stat_activity WHERE datname=current_database()
                    AND wait_event_type='Lock' AND query LIKE 'INSERT INTO bank_lines(%'")['n'];
                if ($waiting === 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true)<$deadline);
            pg_close($observer);
            $this->assertSame(2, $waiting, 'Both import processes must be in flight at the identity insert');
        } finally {
            CharacterizationEnv::rows(self::$tenant, 'COMMIT');
            foreach ($children as [$process,$pipes]) {
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $errors . $output);
            }
            foreach ($sessions as $session) {
                CharacterizationEnv::rows($master, 'DELETE FROM online WHERE session_id=$1', [$session]);
            }
            pg_close($master);
        }
        $this->assertSame('2', CharacterizationEnv::one(self::$tenant, "SELECT count(*) AS n FROM bank_lines WHERE text='Coffee'")['n']);
        foreach ($journalIds as $journal) {
            $this->assertSame('2', CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_line_journal_links l
                JOIN kassekladde k ON k.id=l.kassekladde_id WHERE k.kladde_id=$1', [$journal])['n']);
        }
    }

    public function test_key_normalization_occurrences_and_transaction_ownership(): void
    {
        $this->selectLibrary();
        $line = ['account_kontonr'=>6471,'transdate'=>'2026-09-14','amount'=>'-50.00','text'=>" café\t ÅRHUS ",'balance'=>null];
        $same = $line;
        $same['text'] = 'CAFÉ ÅRHUS';
        $same['amount'] = -50;
        $this->assertSame(bank_line_key($line, 'csv', 1), bank_line_key($same, 'csv', 1));
        $legacy = $line;
        $legacy['text'] = mb_convert_encoding(" café\u{00A0} ÅRHUS ", 'ISO-8859-1', 'UTF-8');
        $legacy['text_encoding'] = 'ISO-8859-1';
        $this->assertSame(bank_line_key($line, 'csv', 1), bank_line_key($legacy, 'csv', 1));
        $this->assertNotSame(bank_line_key($line, 'csv', 1), bank_line_key($same, 'csv', 2));
        $same['balance'] = '0.00';
        $this->assertNotSame(bank_line_key($line, 'csv', 1), bank_line_key($same, 'csv', 1));
        $this->expectException(LogicException::class);
        bank_line_register(1, $line, [1]);
    }


    public function test_identity_failure_rolls_back_the_entire_csv_file_and_quoted_text_is_escaped(): void
    {
        $journal = $this->journal();
        $imports = (int)CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_line_imports')['n'];
        $this->rejectIdentityWrite(true);
        try {
            $result = CharacterizationEnv::runChild(__DIR__ . '/../support/run_bankimport_page.php',
                [json_encode(['fixture'=>'rollback.tsv']),$journal,self::$session]);
            $this->assertNotSame(0, $result['exit']);
            $this->assertStringContainsString('Bank line database command failed', $result['stderr']);
        } finally {
            $this->rejectIdentityWrite(false);
        }
        $this->assertSame([], $this->journalRows($journal));
        $this->assertSame($imports, (int)CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM bank_line_imports')['n']);
        $this->assertSame('0', CharacterizationEnv::one(self::$tenant, "SELECT count(*) AS n FROM bank_lines WHERE text='Rollback first'")['n']);
        $this->runImport('run_bankimport_page.php', $journal, ['fixture'=>'quoted.tsv']);
        $this->assertSame("Supplier's purchase", $this->journalRows($journal)[0]['beskrivelse']);
    }

    public function test_schema_forbids_storing_matched_and_registration_rollback_keeps_identity_state(): void
    {
        $this->selectLibrary();
        $row = CharacterizationEnv::one(self::$tenant, 'SELECT * FROM bank_lines ORDER BY id LIMIT 1');
        bank_line_query('BEGIN');
        try {
            bank_line_query("UPDATE bank_lines SET state='matched' WHERE id=$1", [$row['id']]);
            $this->fail('Stored matched must be rejected by the schema');
        } catch (RuntimeException $error) {
            $this->assertSame('Bank line database command failed', $error->getMessage());
        } finally {
            bank_line_query('ROLLBACK');
        }
        $this->assertSame($row['state'], CharacterizationEnv::one(self::$tenant, 'SELECT state FROM bank_lines WHERE id=$1', [$row['id']])['state']);
    }

    /** @return void Give the definition-only helper the selected disposable tenant link. */
    private function selectLibrary(): void
    {
        $GLOBALS['connection'] = self::$tenant;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db'] = CharacterizationEnv::testDb();
        $GLOBALS['sqdb'] = CharacterizationEnv::masterDb();
        $GLOBALS['db_skriv_id'] = 2;
        $GLOBALS['brugernavn'] = 'chartest';
        $_SERVER['REQUEST_URI'] = '/saldi/index.php';
        require_once __DIR__ . '/../../../includes/finans/bankLines.php';
    }

}
