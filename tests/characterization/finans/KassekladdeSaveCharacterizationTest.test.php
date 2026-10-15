<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/finans/KassekladdeSaveCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.05
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

final class KassekladdeSaveCharacterizationTest extends TestCase
{
    private static $tenant;
    private static int $year;

    public static function setUpBeforeClass(): void
    {
        if (($reason = CharacterizationEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        self::$year = (int)CharacterizationEnv::one(self::$tenant, "SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")['year'];
        CharacterizationEnv::rows(self::$tenant, 'DELETE FROM kassekladde');
        CharacterizationEnv::rows(self::$tenant, "UPDATE grupper SET box1='01',box2='2026',box3='12',box4='2026',box5='on' WHERE art='RA' AND kodenr=$1", [self::$year]);
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO kontoplan(kontonr,beskrivelse,kontotype,lukket,moms,regnskabsaar) VALUES
            (987001,'T3 purchase','D','','K1',$1),(987002,'T3 closed','D','on','',$1)", [self::$year]);
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO adresser(kontonr,firmanavn,art,lukket) VALUES ('987003','T3 supplier','K','')");
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM grupper WHERE art IN ('KM','EM','VK')");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO grupper(kode,kodenr,beskrivelse,art,box1,box2,fiscal_year) VALUES
            ('K',1,'Purchase','KM','987001','25',$1),('E',2,'EU purchase','EM','987001','25',$1),
            ('',987,'EUR','VK','EUR','',$1)", [self::$year]);
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO valuta(gruppe,valdate,kurs) VALUES (987,'2026-01-01',745.123),(987,'2026-06-01',746.456),(987,'2026-12-01',999)");
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            pg_close(self::$tenant);
            CharacterizationEnv::teardownTenant();
        }
    }

    /** @return array<string,array{0:array<string,mixed>,1:?string,2:bool}> */
    public static function saveCases(): array
    {
        return [
            'balanced F K' => [[], null, false],
            'debit only' => [['kred1'=>'','k_ty1'=>'F'], null, false],
            'negative amount' => [['belo1'=>'-125,00'], null, false],
            'EUR existing row' => [['valu1'=>'EUR'], null, true],
            'posted journal' => [['posted'=>true], 'Kladden er allerede bogført - kladden lukkes', false],
            'closed account' => [['debe1'=>'987002'], ' 987002  42', false],
            'unknown account' => [['debe1'=>'999991'], "Der er ingen finanskonti hvor '999991' indgår (Bilag nr 42) Kladden en IKKE gemt!", false],
            'unknown kreditor' => [['kred1'=>'999992'], 'Kreditor 999992 eksisterer ikke  ( Bilag nr 42) Kladden en IKKE gemt!', false],
            'outside fiscal year' => [['dato1'=>'15-05-2025'], 'Dato (15-05-2025) udenfor regnskabsår ( Bilag nr 42)', false],
            'unknown department' => [['afd_1'=>'999993'], 'Afdeling 999993 eksisterer ikke  ( Bilag nr 42) Kladden en IKKE gemt!', false],
            'unknown project' => [['proj1'=>'999994'], 'Projekt 999994 eksisterer ikke  ( Bilag nr 42) Kladden en IKKE gemt!', false],
            'unknown currency' => [['valu1'=>'ZZZ'], 'Valuta ZZZ eksisterer ikke  ( Bilag nr 42) Kladden en IKKE gemt!', true],
            'VAT exempt' => [['moms1'=>'on','dvat1'=>'E2'], null, true],
            'explicit VAT override' => [['dvat1'=>'E2'], null, true],
            'new exemption keeps legacy account default' => [['moms1'=>'on','dvat1'=>'E2'], null, false],
            'new override keeps legacy account default' => [['dvat1'=>'E2'], null, false],
            'negative explicit VAT' => [['dvat1'=>'E2','belo1'=>'-125,00'], null, true],
            'new due date insert branch' => [['forf1'=>'15-06-2026','b_id1'=>'PAY42'], null, false],
        ];
    }

    #[DataProvider('saveCases')]
    public function testWebSavePinsRowsAndAlerts(array $changes, ?string $alert, bool $existing): void
    {
        $journal = CharacterizationEnv::one(self::$tenant, "INSERT INTO kladdeliste(kladdedate,kladdenote,bogfort,oprettet_af)
            VALUES ('2026-05-15','T3 save',$1,'chartest') RETURNING id", [isset($changes['posted']) ? 'V' : '-']);
        $journalId = (int)$journal['id'];
        $posted = isset($changes['posted']);
        unset($changes['posted']);
        $lineId = 0;
        if ($existing) {
            $lineId = (int)CharacterizationEnv::one(self::$tenant, "INSERT INTO kassekladde(kladde_id,bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,valutakurs,pos)
                VALUES ($1,42,'2026-05-15','existing','F',987001,'K',987003,10,100,1) RETURNING id", [$journalId])['id'];
        }
        $before = CharacterizationEnv::rows(self::$tenant, 'SELECT * FROM kassekladde WHERE kladde_id=$1 ORDER BY id', [$journalId]);
        $form = array_replace([
            'save'=>'on','antal_ny'=>1,'antal_ex'=>$existing ? 1 : 0,'fokus'=>'besk1','tidspkt'=>microtime(),
            'id'=>[1=>$lineId],'bila1'=>'42','dato1'=>'15-05-2026','besk1'=>'T3 save',
            'd_ty1'=>'F','debe1'=>'987001','k_ty1'=>'K','kred1'=>'987003','belo1'=>'125,00',
            'fakt1'=>'INV-42','valu1'=>'DKK','afd_1'=>'','proj1'=>'','meda1'=>'','forf1'=>'','b_id1'=>'',
            'dvat1'=>'','kvat1'=>'','dkka1'=>'125','kontrolkonto'=>'','kladdenote'=>'T3 save',
        ], $changes);
        if ($posted) {
            $form['_validator'] = true;
        }
        $result = CharacterizationEnv::runChild(__DIR__ . '/../support/run_kassekladde_page.php',
            [$journalId, CharacterizationEnv::SESSION_ID, json_encode($form, JSON_THROW_ON_ERROR)]);
        $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        $this->assertStringNotContainsString('FATAL:', $result['stdout']);
        preg_match_all("~<script type='text/javascript'>alert\('((?:\\\\.|[^'])*)'\);</script>|<BODY onLoad=\"javascript:alert\('([^']*)'\)\">~", $result['stdout'], $matches, PREG_SET_ORDER);
        $alerts = array_map(static fn(array $match): string => stripslashes($match[1] !== '' ? $match[1] : ($match[2] ?? '')), $matches);
        $this->assertSame($alert === null ? [] : [$alert], $alerts, 'Exact web validation alerts');
        $rows = CharacterizationEnv::rows(self::$tenant, 'SELECT * FROM kassekladde WHERE kladde_id=$1 ORDER BY id', [$journalId]);
        if ($alert !== null) {
            $this->assertSame($before, $rows, 'Rejected save must retain every stored line field');
        } else {
            $this->assertCount(1, $rows, $result['stderr']);
            $row = $rows[0];
            $negative = $form['belo1'][0] === '-';
            $this->assertSame('42', $row['bilag']);
            $this->assertSame('2026-05-15', $row['transdate']);
            $this->assertSame('T3 save', $row['beskrivelse']);
            $this->assertSame($negative ? 'K' : 'F', $row['d_type']);
            $this->assertSame($negative ? '987003' : '987001', $row['debet']);
            $this->assertSame($negative ? 'F' : $form['k_ty1'], $row['k_type']);
            $this->assertSame($negative ? '987001' : ($form['kred1'] ?: '0'), $row['kredit']);
            $this->assertEquals(125, $row['amount']);
            $this->assertSame('INV-42', $row['faktura']);
            $this->assertSame($form['forf1'] === '' ? null : '2026-06-15', $row['forfaldsdate']);
            $this->assertSame($form['forf1'] === '' ? ($existing ? '' : null) : 'PAY42', $row['betal_id']);
            $this->assertSame('0', $row['ansat']);
            $this->assertSame('0', $row['afd']);
            $this->assertSame('', $row['projekt']);
            $this->assertSame('1', $row['pos']);
            foreach (['medarb','ordre_id','dokument','saldo'] as $field) {
                $this->assertNull($row[$field], $field);
            }
            $vat = !$existing ? 'K1' : (isset($form['moms1']) ? '' : ($form['dvat1'] ?: 'K1'));
            $this->assertSame($negative ? '' : $vat, $row['debetvat']);
            $this->assertSame($negative ? $vat : '', $row['kreditvat']);
            $this->assertSame($form['moms1'] ?? '', $row['momsfri']);
            $this->assertSame($form['valu1'] === 'EUR' ? '987' : '0', $row['valuta']);
            if (!$existing) {
                $this->assertNull($row['valutakurs'], 'New web rows do not copy exchange rates');
            }
            if ($existing) {
                $this->assertEquals($form['valu1'] === 'EUR' ? 745.123 : 100, $row['valutakurs']);
                $this->assertSame((string)$lineId, $row['id']);
            }
        }
        $this->assertSame([], CharacterizationEnv::rows(self::$tenant, 'SELECT id FROM transaktioner WHERE kladde_id=$1', [$journalId]));
        $this->assertSame([], CharacterizationEnv::rows(self::$tenant, 'SELECT id FROM openpost WHERE kladde_id=$1', [$journalId]));
    }
}
