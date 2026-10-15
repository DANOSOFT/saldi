<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/includes/docsIncludes/PoolMetadataSaveCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Regress web registry bootstrap and first-config tenant preservation for positive user ids.
// 20261007 CDX/LH Assert the restored tenant SQL trail on the web write path.
// 20261007 CDX/LH Exclude registry bootstrap settings from the tenant modification trail.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../../includes/db_query.php';
require_once __DIR__ . '/../../../../includes/std_func.php';
require_once __DIR__ . '/../../../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../../../includes/mobileRegistryUpdates.php';
require_once __DIR__ . '/../../../../includes/docsIncludes/poolMetadata.php';

require_once __DIR__ . '/../../support/ModificationTrail.php';

final class PoolMetadataSaveCharacterizationTest extends TestCase
{
    private static $conn;
    private static array $globals;
    private static array $server;
    public static function setUpBeforeClass(): void
    {
        if ($reason = CharacterizationEnv::unavailableReason()) { self::markTestSkipped($reason); }
        self::$server = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/tests/metadata';
        CharacterizationEnv::bootstrapTenant();
        self::$conn = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        mobileTenantUpdates(self::$conn);
        foreach (['connection','db_type','bruger_id','brugernavn','revisor','db_transaktion_depth'] as $key) {
            self::$globals[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
        }
        $GLOBALS['connection'] = self::$conn;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['bruger_id'] = 0;
        $GLOBALS['brugernavn'] = 'characterization';
        $GLOBALS['revisor'] = false;
        CharacterizationEnv::rows(self::$conn, "INSERT INTO kontoplan(kontonr,regnskabsaar,kontotype,lukket) VALUES (987001,987,'D',NULL),(987002,987,'S',''),(987003,987,'D','on'),(987004,987,'H','')");
    }
    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            pg_close(self::$conn);
            foreach (self::$globals as $key => [$exists,$value]) {
                if ($exists) { $GLOBALS[$key] = $value; } else { unset($GLOBALS[$key]); }
            }
            $_SERVER = self::$server;
            CharacterizationEnv::teardownTenant();
        }
    }
    protected function setUp(): void
    {
        CharacterizationEnv::rows(self::$conn, "INSERT INTO pool_files(filename,subject,account,amount,file_date,invoice_number,description,currency) VALUES ($1,'Original','987001','123,45','2026-09-14','INV-1','Original description','DKK')", [$this->fixtureName()]);
    }
    private function fixtureName(): string { return $this->name() . '.pdf'; }
    private function row(): array
    {
        return CharacterizationEnv::one(self::$conn,'SELECT * FROM pool_files WHERE filename=$1',[$this->fixtureName()]);
    }
    private function reject(array $input, int $code, ?string $version = null): void
    {
        $before = $this->row();
        try {
            poolMetadataSave($this->fixtureName(),$input,true,$version ?? poolMetadataVersion($before),987);
            $this->fail('Invalid correction accepted');
        } catch (InvalidArgumentException | RuntimeException $error) {
            $this->assertSame($code,$error->getCode());
            $this->assertSame($before,$this->row());
        }
    }
    public function testManualSaveNormalizesDanishAmountDateAndEscapesText(): void
    {
        $result = poolMetadataSave($this->fixtureName(),['account'=>'987002','amount'=>'1.234,56','file_date'=>'14-09-2026','description'=>"Supplier's correction",'invoice_number'=>''],true,poolMetadataVersion($this->row()),987);
        $row = $this->row();
        $this->assertSame(['success'=>true,'skipped'=>false,'version'=>poolMetadataVersion($row)],$result);
        $this->assertSame('1234.560',$row['norm_amount']);
        $this->assertSame('1.234,56',$row['amount']);
        $this->assertSame('2026-09-14',$row['file_date']);
        $this->assertSame("Supplier's correction",$row['description']);
        $this->assertSame('',$row['invoice_number']);
        $this->assertSame('Original',$row['subject']);
        $this->assertTrue(poolMetadataIsManual($row));
    }
    public function testWebSaveBootstrapsFreshRegistryAndPreservesTenant(): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $registryDb = 'mc_t3b2ar_' . bin2hex(random_bytes(4));
        $registry = null;
        CharacterizationEnv::rows($master, 'CREATE DATABASE ' . $registryDb);
        try {
            $registry = CharacterizationEnv::connect($registryDb);
            CharacterizationEnv::rows($registry, 'CREATE TABLE settings (id serial PRIMARY KEY, var_name text, var_grp text, var_value text, user_id integer)');
            $user = CharacterizationEnv::one(self::$conn, 'SELECT id,brugernavn FROM brugere WHERE id>0 ORDER BY id LIMIT 1');
            $this->assertNotNull($user);
            $this->assertSame([], CharacterizationEnv::rows($registry, 'SELECT * FROM settings'));
            $trail = ModificationTrail::read(CharacterizationEnv::testDb());
            $child = CharacterizationEnv::runChild(__DIR__ . '/../../support/run_pool_metadata_web.php',
                [CharacterizationEnv::testDb(), $registryDb, $this->fixtureName(), $user['id'], $user['brugernavn']]);
            $this->assertSame(0, $child['exit'], $child['stdout'] . $child['stderr']);
            $this->assertSame('', $child['stderr']);
            $result = json_decode($child['stdout'], true, 512, JSON_THROW_ON_ERROR);
            $added = ModificationTrail::assertWrite(CharacterizationEnv::testDb(), $trail, 'UPDATE pool_files\\b');
            $this->assertStringNotContainsString("'mobile_registry_step'", $added);
            $this->assertStringNotContainsString("'installation_id'", $added);
            $this->assertTrue($result['success']);
            $this->assertTrue($result['same_connection']);
            $this->assertSame(CharacterizationEnv::testDb(), $result['selected_db']);
            $this->assertSame(PGSQL_TRANSACTION_IDLE, $result['transaction_status']);
            $installation = CharacterizationEnv::one($registry, "SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name='installation_id' AND user_id=0")['var_value'];
            $marker = CharacterizationEnv::one($registry, "SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name='mobile_registry_step' AND user_id=0")['var_value'];
            $this->assertSame(array_key_last(mobileRegistryUpdateSteps()), $marker);
            $this->assertSame('Web correction before mobile login', $this->row()['description']);
            $this->assertSame('1234.560', $this->row()['norm_amount']);
            $events = pool_audit_read((int)$this->row()['id'])['items'];
            $this->assertSame($installation . '/account/' . $user['id'], end($events)['actor']);
            $this->assertTrue(pool_audit_verify((int)$this->row()['id']));
            $this->assertSame([], CharacterizationEnv::rows(self::$conn, "SELECT * FROM settings WHERE var_grp='mobile' AND var_name IN ('installation_id','mobile_registry_step')"));
        } finally {
            if ($registry !== null) { pg_close($registry); }
            CharacterizationEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()', [$registryDb]);
            CharacterizationEnv::rows($master, 'DROP DATABASE ' . $registryDb);
            pg_close($master);
        }
    }
    public function testFirstConfigIncludePreservesSelectedWebTenant(): void
    {
        $user = CharacterizationEnv::one(self::$conn, 'SELECT id,brugernavn FROM brugere WHERE id>0 ORDER BY id LIMIT 1');
        $this->assertNotNull($user);
        $child = CharacterizationEnv::runChild(__DIR__ . '/../../support/run_pool_metadata_web.php',
            [CharacterizationEnv::testDb(), '', $this->fixtureName(), $user['id'], $user['brugernavn']]);
        $this->assertSame(0, $child['exit'], $child['stdout'] . $child['stderr']);
        $this->assertSame('', $child['stderr']);
        $result = json_decode($child['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['same_connection']);
        $this->assertSame(CharacterizationEnv::testDb(), $result['selected_db']);
        $this->assertSame(PGSQL_TRANSACTION_IDLE, $result['transaction_status']);
        $this->assertSame('Web correction before mobile login', $this->row()['description']);
        $this->assertSame('1234.560', $this->row()['norm_amount']);
    }
    public function testStaleAndMissingVersionRefused(): void
    {
        $old = poolMetadataVersion($this->row());
        poolMetadataSave($this->fixtureName(),['amount'=>'200'],false,null,987);
        $this->reject(['description'=>'stale'],409,$old);
        $this->expectExceptionCode(409);
        poolMetadataSave($this->fixtureName(),[],true,null,987);
    }
    public function testAutomaticCannotOverwriteManual(): void
    {
        poolMetadataSave($this->fixtureName(),[],true,poolMetadataVersion($this->row()),987);
        $before=$this->row();
        $result=poolMetadataSave($this->fixtureName(),['description'=>'late','account'=>'invalid'],false,null,987);
        $this->assertTrue($result['skipped']);
        $this->assertSame($before,$this->row());
    }
    public function testInvalidAccountsAndValuesRollBack(): void
    {
        foreach (['987003','987004','987005','bad'] as $account) { $this->reject(['account'=>$account],422); }
        foreach ([['amount'=>'invalid'],['file_date'=>'31-02-2026'],['description'=>[]]] as $input) { $this->reject($input,422); }
    }
    public function testUnknownCurrencyRetainsLegacyUppercase(): void
    {
        poolMetadataSave($this->fixtureName(),['currency'=>' xyz '],true,poolMetadataVersion($this->row()),987);
        $this->assertSame('XYZ',$this->row()['currency']);
    }
    public function testExplicitBlanksClearAndAutomaticAliasesNormalize(): void
    {
        poolMetadataSave($this->fixtureName(),['currency'=>'kr.'],false,null,987);
        $this->assertSame('DKK',$this->row()['currency']);
        poolMetadataSave($this->fixtureName(),['amount'=>'','file_date'=>'','account'=>''],true,poolMetadataVersion($this->row()),987);
        $this->assertNull($this->row()['norm_amount']);
        $this->assertSame('',$this->row()['file_date']);
    }
    public function testMissingAttachedAndDeletedRowsRefused(): void
    {
        foreach (['attached','deleted'] as $status) {
            CharacterizationEnv::rows(self::$conn,'UPDATE pool_files SET status=$1 WHERE filename=$2',[$status,$this->fixtureName()]);
            $this->reject([],409);
        }
        $this->expectExceptionCode(409);
        poolMetadataSave('missing.pdf',[],false,null,987);
    }
}
