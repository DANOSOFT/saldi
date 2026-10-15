<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/finans/JournalLineTest.test.php --- ver 5.0.0 --- 2026.10.05
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../finans/kassekladde_includes/journalLine.php';

final class JournalLineTest extends TestCase
{
    private static $tenant;
    private static array $savedGlobals = [];
    private static array $savedServer;

    public static function setUpBeforeClass(): void
    {
        if (($reason = CharacterizationEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        self::$savedServer = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/tests/journal-line';
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        foreach (['connection','db_type','regnaar','baseCurrency'] as $key) {
            self::$savedGlobals[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
        }
        $GLOBALS['connection'] = self::$tenant;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['regnaar'] = 987;
        $GLOBALS['baseCurrency'] = 'DKK';
        CharacterizationEnv::rows(self::$tenant, 'ALTER TABLE kassekladde ADD COLUMN IF NOT EXISTS debetvat varchar');
        CharacterizationEnv::rows(self::$tenant, 'ALTER TABLE kassekladde ADD COLUMN IF NOT EXISTS kreditvat varchar');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            pg_close(self::$tenant);
            foreach (self::$savedGlobals as $key => [$exists,$value]) {
                if ($exists) {
                    $GLOBALS[$key] = $value;
                } else {
                    unset($GLOBALS[$key]);
                }
            }
            CharacterizationEnv::teardownTenant();
            $_SERVER = self::$savedServer;
        }
    }

    protected function setUp(): void
    {
        CharacterizationEnv::rows(self::$tenant, 'BEGIN');
        CharacterizationEnv::rows(self::$tenant, 'DELETE FROM kassekladde');
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO kladdeliste(id,bogfort,hvem,tidspkt) VALUES
            (987001,'-','',''),(987002,'V','',''),(987003,'S','','')");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO kontoplan(kontonr,beskrivelse,kontotype,lukket,regnskabsaar) VALUES
            (987001,'Open','D','',987),(987002,'Closed','D','on',987),
            (987003,'Heading','H','',987),(987004,'Summary','Z','',987),
            (987005,'Other year','D','',986)");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO adresser(kontonr,art) VALUES ('987006','D'),('987007','K')");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO grupper(art,kodenr,box1,box2,box3,box4,box5) VALUES
            ('RA','987','07','2025','06','2026','on'),('AFD','987','','','','',''),
            ('PRJ','987','','','','',''),('VK','987','TST','','','',''),('VK','988','NON','','','','')");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO valuta(gruppe,valdate,kurs) VALUES
            (987,'2025-07-01',745.123),(987,'2026-03-01',746.456),(987,'2026-07-01',999)");
    }

    protected function tearDown(): void
    {
        CharacterizationEnv::rows(self::$tenant, 'ROLLBACK');
    }

    /** @return array<string,mixed> Valid resolved line in a non-calendar fiscal year. */
    private function line(array $changes = []): array
    {
        return array_replace(['kladde_id'=>987001,'transdate'=>'2026-05-15','debet'=>987001,
            'd_type'=>'F','kredit'=>'987007','k_type'=>'K','afd'=>987,'projekt'=>987,'valuta'=>'DKK'], $changes);
    }

    /** @return array<string,array{0:array<string,mixed>,1:string,2:string}> */
    public static function refusalCases(): array
    {
        return [
            'posted journal' => [['kladde_id'=>987002], 'kladde_id','journal_posted'],
            'simulated journal' => [['kladde_id'=>987003], 'kladde_id','journal_posted'],
            'missing journal' => [['kladde_id'=>999999], 'kladde_id','journal_posted'],
            'missing debit account' => [['debet'=>999999], 'debet','account_invalid'],
            'missing credit account' => [['kredit'=>999999,'k_type'=>'F'], 'kredit','account_invalid'],
            'closed debit account' => [['debet'=>987002], 'debet','account_closed'],
            'closed credit account' => [['kredit'=>987002,'k_type'=>'F'], 'kredit','account_closed'],
            'heading excluded' => [['debet'=>987003], 'debet','account_invalid'],
            'summary excluded' => [['debet'=>987004], 'debet','account_invalid'],
            'account in other year' => [['debet'=>987005], 'debet','account_invalid'],
            'unresolved shortcut' => [['debet'=>'P'], 'debet','account_invalid'],
            'unresolved numeric text' => [['debet'=>'987001 OR 1=1'], 'debet','account_invalid'],
            'unknown debitor debit' => [['debet'=>'987007','d_type'=>'D'], 'debet','debitor_invalid'],
            'unknown debitor credit' => [['kredit'=>'987007','k_type'=>'D'], 'kredit','debitor_invalid'],
            'unknown kreditor debit' => [['debet'=>'987006','d_type'=>'K'], 'debet','kreditor_invalid'],
            'unknown kreditor credit' => [['kredit'=>'987006'], 'kredit','kreditor_invalid'],
            'before open year' => [['transdate'=>'2025-06-30'], 'transdate','outside_fiscal_year'],
            'after open year' => [['transdate'=>'2026-07-01'], 'transdate','outside_fiscal_year'],
            'unknown department' => [['afd'=>999999], 'afd','department_invalid'],
            'unknown project' => [['projekt'=>999999], 'projekt','project_invalid'],
            'unknown currency' => [['valuta'=>'ZZZ'], 'valuta','currency_unknown'],
        ];
    }

    #[DataProvider('refusalCases')]
    public function testEachValidationReason(array $changes, string $field, string $reason): void
    {
        ob_start();
        try {
            $this->assertSame([['field'=>$field,'reason'=>$reason]], kladde_line_check($this->line($changes), 987));
        } finally {
            $this->assertSame('', ob_get_clean(), 'Validation has no page output');
        }
        $this->assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status(self::$tenant));
        $this->assertSame(987, $GLOBALS['regnaar']);
    }

    public function testEmptyCounterPostingAndValidTypesAreAllowedAndClosedYearIsRefused(): void
    {
        foreach ([['kredit'=>0], ['debet'=>'987006','d_type'=>'D'],
            ['debet'=>'0987006','d_type'=>'D','kredit'=>'0987007'], ['valuta'=>'TST'],
            ['transdate'=>'2025-07-01'], ['transdate'=>'2026-06-30'], ['afd'=>0,'projekt'=>0]] as $changes) {
            $this->assertSame([], kladde_line_check($this->line($changes),987));
        }
        CharacterizationEnv::rows(self::$tenant, "UPDATE grupper SET box5='' WHERE art='RA' AND kodenr='987'");
        $this->assertSame([['field'=>'transdate','reason'=>'outside_fiscal_year']], kladde_line_check($this->line(),987));
    }

    public function testRateRetainsDateSourceAndNeverUsesAFutureRate(): void
    {
        $this->assertSame(['rate'=>'746.456','rate_date'=>'2026-03-01','source'=>'saldi_valuta'], kladde_line_rate('TST','2026-05-15'));
        $this->assertSame(['rate'=>'745.123','rate_date'=>'2025-07-01','source'=>'saldi_valuta'], kladde_line_rate('TST','2025-07-01'));
        $this->assertNull(kladde_line_rate('TST','2025-06-30'));
        $this->assertNull(kladde_line_rate('NON','2026-05-15'));
        $this->assertNull(kladde_line_rate("ZZZ' OR '1'='1",'2026-05-15'));
        $this->assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status(self::$tenant));
    }

    public function testInsertionAllocatesNumbersOrdersPositionsSwapsVATAndLeavesRollbackToCaller(): void
    {
        $base = ['transdate'=>'2026-05-15','amount'=>'125.00','debet'=>987001,'d_type'=>'F',
            'kredit'=>'987007','k_type'=>'K','debetvat'=>'E2','kreditvat'=>'K1','momsfri'=>'',
            'beskrivelse'=>"Supplier's credit note",'faktura'=>"INV'42"];
        $first = kassekladde_insert_line(987001,$base + ['bilag'=>42]);
        $second = kassekladde_insert_line(987001,array_replace($base, ['amount'=>'-125.00','forfaldsdate'=>'2026-06-15','betal_id'=>"PAY'42",'valuta'=>987,'valutakurs'=>'746.456']));
        $third = kassekladde_insert_line(987001,array_replace($base, ['bilag'=>41,'momsfri'=>'on','debetvat'=>'','kreditvat'=>'','kredit'=>0]));
        $this->assertGreaterThan(0,$first);
        $this->assertGreaterThan($first,$second);
        $this->assertGreaterThan($second,$third);
        $rows = CharacterizationEnv::rows(self::$tenant,'SELECT * FROM kassekladde WHERE kladde_id=987001 ORDER BY pos');
        $this->assertSame(['41','42','43'],array_column($rows,'bilag'));
        $this->assertSame(['1','2','3'],array_column($rows,'pos'));
        $this->assertSame("Supplier's credit note",$rows[1]['beskrivelse']);
        $this->assertSame("INV'42",$rows[1]['faktura']);
        $this->assertNull($rows[1]['forfaldsdate']);
        $this->assertSame('987007',$rows[2]['debet']);
        $this->assertSame('987001',$rows[2]['kredit']);
        $this->assertSame('K',$rows[2]['d_type']);
        $this->assertSame('F',$rows[2]['k_type']);
        $this->assertSame('K1',$rows[2]['debetvat']);
        $this->assertSame('E2',$rows[2]['kreditvat']);
        $this->assertEquals(125,$rows[2]['amount']);
        $this->assertSame('2026-06-15',$rows[2]['forfaldsdate']);
        $this->assertSame("PAY'42",$rows[2]['betal_id']);
        $this->assertSame('987',$rows[2]['valuta']);
        $this->assertEquals(746.456,$rows[2]['valutakurs']);
        $this->assertSame('on',$rows[0]['momsfri']);
        $this->assertSame('',$rows[0]['debetvat']);
        $this->assertSame('',$rows[0]['kreditvat']);
        $this->assertSame(PGSQL_TRANSACTION_INTRANS,pg_transaction_status(self::$tenant));
        CharacterizationEnv::rows(self::$tenant,'ROLLBACK');
        $this->assertSame([],CharacterizationEnv::rows(self::$tenant,'SELECT id FROM kassekladde WHERE id IN ($1,$2,$3)',[$first,$second,$third]));
        CharacterizationEnv::rows(self::$tenant,'BEGIN');
    }

    public function testFirstVoucherContinuesTheFiscalYearAndValidationUsesExplicitYear(): void
    {
        CharacterizationEnv::rows(self::$tenant,"INSERT INTO kassekladde(kladde_id,bilag,transdate) VALUES (987003,250,'2025-09-15')");
        $id = kassekladde_insert_line(987001,['transdate'=>'2026-05-15','amount'=>125,'debet'=>987001]);
        $this->assertSame('251',CharacterizationEnv::one(self::$tenant,'SELECT bilag FROM kassekladde WHERE id=$1',[$id])['bilag']);
        $GLOBALS['regnaar'] = 986;
        $this->assertSame([],kladde_line_check($this->line(),987));
        $this->assertSame(986,$GLOBALS['regnaar']);
        $GLOBALS['regnaar'] = 987;
    }

    public function testWebLeaseAllowsSelfButMobileLeaseIncludesSelfAndExpires(): void
    {
        foreach ([microtime(), (string)time()] as $timestamp) {
            CharacterizationEnv::rows(self::$tenant,"UPDATE kladdeliste SET hvem='other',tidspkt=$1 WHERE id=987001",[$timestamp]);
            $this->assertSame('other',kladde_lock_holder(987001,'chartest'));
            $this->assertNull(kladde_lock_holder(987001,'other'));
            $this->assertSame('other',kladde_lease_holder(987001)['holder']);
        }
        CharacterizationEnv::rows(self::$tenant,"UPDATE kladdeliste SET tidspkt=$1 WHERE id=987001",['0.00000000 ' . (time()-3601)]);
        $this->assertNull(kladde_lock_holder(987001,'chartest'));
        CharacterizationEnv::rows(self::$tenant,"UPDATE kladdeliste SET bogfort='V',tidspkt=$1 WHERE id=987001",[microtime()]);
        $this->assertNull(kladde_lock_holder(987001,'chartest'));
        $this->assertNull(kladde_lock_holder(999999,'chartest'));
    }
}
