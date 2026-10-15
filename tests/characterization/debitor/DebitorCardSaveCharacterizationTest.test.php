<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/debitor/DebitorCardSaveCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Characterize the primary customer card and its form defaults before extraction.
// 20261007 CDX/LH Assert the restored tenant SQL trail on the web write path.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../support/ModificationTrail.php';

final class DebitorCardSaveCharacterizationTest extends TestCase
{
    private static $conn;
    private static bool $ownsTenant = false;
    public static function setUpBeforeClass(): void {
        if (CharacterizationEnv::unavailableReason() !== null) { return; }
        // CharacterizationEnv's shared session id collides with other concurrent slices.
        // Provision only this database and our private session instead.
        CharacterizationEnv::assertSafeDatabases();
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $database = CharacterizationEnv::testDb();
        CharacterizationEnv::rows($master,'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()',[$database]);
        CharacterizationEnv::rows($master,'DROP DATABASE IF EXISTS '.pg_escape_identifier($master,$database));
        CharacterizationEnv::rows($master,'CREATE DATABASE '.pg_escape_identifier($master,$database).' TEMPLATE '.pg_escape_identifier($master,CharacterizationEnv::templateDb()));
        self::$ownsTenant = true;
        CharacterizationEnv::rows($master,'DELETE FROM regnskab WHERE db=$1',[$database]);
        CharacterizationEnv::rows($master,"INSERT INTO regnskab(regnskab,dbhost,dbuser,db,version,sidst,brugerantal,posteringer,lukket,administrator)
            SELECT 'chartest',dbhost,dbuser,$1,version,sidst,brugerantal,1000000,'',administrator FROM regnskab WHERE db=$2",[$database,CharacterizationEnv::templateDb()]);
        pg_close($master);
        self::$conn = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        CharacterizationEnv::rows(self::$conn, 'ALTER TABLE adresser ADD COLUMN IF NOT EXISTS stripe_fravalg text');
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        CharacterizationEnv::rows($master, "INSERT INTO online (session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id) VALUES ($1,'chartest',$2,$3,$4,1,$5,false,0)", ['t2b3session'.getmypid(),CharacterizationEnv::testDb(),CharacterizationEnv::pgUser(),str_repeat('1',32),(string)time()]);
        pg_close($master);
    }
    protected function setUp(): void {
        if (!self::$conn) { $this->markTestSkipped(CharacterizationEnv::unavailableReason()); }
        CharacterizationEnv::rows(self::$conn, "DELETE FROM adresser WHERE art='D'");
    }
    public static function tearDownAfterClass(): void {
        if (self::$conn) { pg_close(self::$conn); }
        if (self::$conn) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            CharacterizationEnv::rows($master,'DELETE FROM online WHERE session_id=$1',['t2b3session'.getmypid()]);
            pg_close($master);
        }
        if (self::$ownsTenant) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            $database = CharacterizationEnv::testDb();
            CharacterizationEnv::rows($master,'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()',[$database]);
            CharacterizationEnv::rows($master,'DROP DATABASE IF EXISTS '.pg_escape_identifier($master,$database));
            CharacterizationEnv::rows($master,"DELETE FROM regnskab WHERE db=$1 AND regnskab='chartest'",[$database]);
            pg_close($master);
            self::$ownsTenant = false;
        }
    }
    private function save(array $fields): array {
        $result = CharacterizationEnv::runChild(__DIR__ . '/../support/run_debkort_save.php',
            [CharacterizationEnv::testDb(),json_encode(array_replace(['firmanavn'=>'Characterized customer','submit'=>'Gem'],$fields))]);
        self::assertSame(0,$result['exit'],$result['stderr']);
        return $result;
    }
    public function testAutomaticNumberAndCardDefaults(): void {
        $output=$this->save([]);
        $row=CharacterizationEnv::one(self::$conn,"SELECT * FROM adresser WHERE art='D'");
        self::assertSame('1000',$row['kontonr']);
        foreach (['gruppe','betalingsdage','kreditmax','kontoansvarlig','rabatgruppe','status'] as $key) {
            self::assertEquals(0,$row[$key],$key);
        }
        self::assertSame('', $row['betalingsbet']);
        self::assertStringContainsString('tekst 349',$output['stdout']);
    }
    public function testFirstFreeNumberAndTypedDuplicate(): void {
        $this->save(['ny_kontonr'=>'1000','gruppe'=>'2: Debitor']);
        $this->save(['ny_kontonr'=>'1002']);
        $this->save([]);
        $duplicate=$this->save(['ny_kontonr'=>'1000']);
        self::assertStringContainsString('alert',$duplicate['stdout']);
        self::assertSame(['1000','1001','1002'],array_column(CharacterizationEnv::rows(self::$conn,
            "SELECT kontonr FROM adresser WHERE art='D' ORDER BY kontonr"),'kontonr'));
        self::assertSame('2',CharacterizationEnv::one(self::$conn,"SELECT gruppe FROM adresser WHERE kontonr='1000' AND art='D'")['gruppe']);
    }
    public function testPreassignedNumberMovesWhenAnotherSaveTookIt(): void {
        $this->save(['ny_kontonr'=>'1000']);
        $this->save(['ny_kontonr'=>'1000','auto_kontonr'=>1000]);
        self::assertSame(['1000','1001'],array_column(CharacterizationEnv::rows(self::$conn,
            "SELECT kontonr FROM adresser WHERE art='D' ORDER BY kontonr"),'kontonr'));
    }
    public function testFullCardFieldsAndPrivateNamesKeepTheirStoredValues(): void {
        $fields=['firmanavn'=>"O'Connor ApS",'ny_kontonr'=>'1100','gruppe'=>'2: Debitor',
            'addr1'=>"O'Connor vej",'addr2'=>'Floor 2','postnr'=>'8000','bynavn'=>'Aarhus',
            'land'=>'DK','kontakt'=>"O'Connor contact",'email'=>'contact@example.invalid',
            'betalingsbet'=>'Netto','betalingsdage'=>'14','kreditmax'=>'1.234,50','bank_reg'=>'1234',
            'bank_konto'=>'1234567890','lev_firmanavn'=>'Delivery','lev_addr1'=>"O'Connor delivery",
            'lev_postnr'=>'8000','lev_bynavn'=>'Aarhus','felt_1'=>"Field's value",'notes'=>"Note's value"];
        $this->save($fields);
        $row=CharacterizationEnv::one(self::$conn,"SELECT * FROM adresser WHERE art='D'");
        foreach (['firmanavn','addr1','addr2','land','kontakt','email','betalingsbet','bank_reg','bank_konto','lev_addr1','felt_1','notes'] as $key) {
            self::assertSame($fields[$key],$row[$key],$key);
        }
        self::assertSame('14',$row['betalingsdage']);
        self::assertEquals(1234.50,$row['kreditmax']);
        self::assertSame("O'Connor contact",CharacterizationEnv::one(self::$conn,'SELECT navn FROM ansatte WHERE konto_id=$1',[$row['id']])['navn']);
        $this->save(['firmanavn'=>'Ignored','gl_kontotype'=>'privat','fornavn'=>'Anne','efternavn'=>"O'Connor",'ny_kontonr'=>'1101']);
        self::assertSame("Anne O'Connor",CharacterizationEnv::one(self::$conn,"SELECT firmanavn FROM adresser WHERE art='D' AND kontonr='1101'")['firmanavn']);
    }
    public function testPrimaryCardPreservesAdditionalFieldsAndEmailRecipients(): void {
        $fields=['id'=>0,'firmanavn'=>"Primary O'Connor",'submit'=>'Gem','gruppe'=>'1: Debitor',
            'betalingsbet'=>'Netto','betalingsdage'=>'14','swift'=>'TESTDKKK','productlimit'=>'12,50',
            'kontakt'=>"Primary O'Connor contact",'bank_reg'=>'1234','bank_konto'=>'12345678',
            'kontakt_email_val'=>[1=>'primary@example.invalid'],'kontakt_email_type'=>[1=>'hoved'],
            'kontakt_emails_json'=>json_encode([['email'=>'extra@example.invalid','type'=>'ordre']])];
        $trail = ModificationTrail::read(CharacterizationEnv::testDb());
        $result=CharacterizationEnv::runChild(__DIR__.'/../support/run_debitorkort_page.php',
            [json_encode(['get'=>[],'post'=>$fields]),'t2b3session'.getmypid()]);
        self::assertSame(0,$result['exit'],$result['stderr']);
        ModificationTrail::assertWrite(CharacterizationEnv::testDb(), $trail, 'INSERT INTO adresser\\b');
        $row=CharacterizationEnv::one(self::$conn,"SELECT * FROM adresser WHERE art='D'");
        self::assertNotNull($row,$result['stdout'].' '.$result['stderr']);
        self::assertSame('1000',$row['kontonr']);
        self::assertSame($fields['firmanavn'],$row['firmanavn']);
        self::assertSame('TESTDKKK',$row['swift']);
        self::assertEquals(13,$row['productlimit']);
        self::assertSame('14',$row['betalingsdage']);
        self::assertSame('primary@example.invalid',$row['email']);
        self::assertSame(['primary@example.invalid','extra@example.invalid'],array_column(
            CharacterizationEnv::rows(self::$conn,'SELECT email FROM kontakt_emails WHERE konto_id=$1 ORDER BY id',[$row['id']]),'email'));
        self::assertSame($fields['kontakt'],CharacterizationEnv::one(self::$conn,'SELECT navn FROM ansatte WHERE konto_id=$1',[$row['id']])['navn']);
        self::assertStringContainsString('id='.$row['id'],$result['stdout']);
    }

    public function testPrimaryCardPaymentAndGroupFormDefaults(): void {
        CharacterizationEnv::rows(self::$conn,"INSERT INTO adresser(art,kontonr,betalingsbet,betalingsdage) VALUES ('D','1200','Netto',14),('D','1201','Netto',14)");
        $result=CharacterizationEnv::runChild(__DIR__.'/../support/run_debitorkort_page.php',
            [json_encode(['get'=>[],'post'=>[]]),'t2b3session'.getmypid()]);
        self::assertSame(0,$result['exit'],$result['stderr']);
        self::assertMatchesRegularExpression('/NAME=betalingsbet[^>]*>\s*<option>Netto<\/option>/',$result['stdout']);
        self::assertMatchesRegularExpression('/name=[\"\']betalingsdage[\"\'][^>]*value=\"8\"/',$result['stdout']);
        self::assertMatchesRegularExpression('/NAME=gruppe[^>]*>\s*<option>1:/',$result['stdout']);
    }

    public function testCvrProxyAgainstLocalTransportFixture(): void {
        foreach ([['vat','12345678',200],['phone','12345678',200],['vat','123',400],['vat','00000000',404],['vat','99999999',503]] as [$type,$param,$status]) {
            $result=CharacterizationEnv::runChild(__DIR__ . '/../support/run_cvr_proxy.php',
                [json_encode(['type'=>$type,'param'=>$param,'country'=>'dk']),'t2b3session'.getmypid()]);
            self::assertSame(0,$result['exit'],$result['stderr']);
            self::assertStringContainsString('CHARTEST_STATUS='.$status,$result['stdout']);
            if ($status===200) { self::assertStringContainsString('Stub Hansen ApS',$result['stdout']); }
        }
    }
}
