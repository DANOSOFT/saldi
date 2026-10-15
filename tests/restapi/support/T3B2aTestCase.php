<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T3B2aTestCase.php --- ver 5.0.0 --- 2026.10.05
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

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/docsIncludes/poolFileTransitions.php';
require_once __DIR__ . '/../../../restapi/services/AttachmentMetadataService.php';

/** Disposable tenant and authenticated actors shared only by this slice's tests. */
abstract class T3B2aTestCase extends TestCase
{
    protected static $conn;
    protected static array $ownerHeaders;
    protected static array $otherHeaders;
    protected static array $reviewerHeaders;
    protected static string $owner;
    protected static string $reviewer;
    protected static int $userId;
    protected static string $root;
    private static array $globals;
    private static array $server;
    public static function setUpBeforeClass(): void
    {
        if ($reason = RestApiEnv::unavailableReason()) { self::markTestSkipped($reason); }
        self::$server = $_SERVER;
        $_SERVER['REQUEST_URI']='/tests/t3-b2a';
        $_SERVER['REMOTE_ADDR']='127.0.0.1';
        foreach (['connection','db_type','db','db_skriv_id','bruger_id','brugernavn','revisor','db_transaktion_depth','db_modify_fejl'] as $key) {
            self::$globals[$key]=[array_key_exists($key,$GLOBALS),ifset($GLOBALS,$key)];
        }
        RestApiEnv::bootstrapTenant();
        self::$conn=RestApiEnv::connect(RestApiEnv::testDb());
        mobileTenantUpdates(self::$conn);
        RestApiEnv::rows(self::$conn,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[str_repeat('0',16),RestApiEnv::user()]);
        self::$ownerHeaders=RestApiEnv::authHeaders();
        self::$userId=(int)RestApiEnv::loginData()['user']['id'];
        $tenant=self::$conn;
        self::$owner=Subject::build('account',self::$userId);
        foreach (['t3_other'=>str_repeat('0',16),'t3_reviewer'=>'0010000000000000'] as $name=>$rights) {
            $rows=RestApiEnv::rows($tenant,'INSERT INTO brugere(brugernavn,kode,email,rettigheder,status) VALUES ($1,$2,$3,$4,true) RETURNING id',
                [$name,md5(TestCredentials::password($name)),$name.'@example.invalid',$rights]);
            $login=RestApiEnv::login($name,TestCredentials::password($name),RestApiEnv::accountOpen());
            $headers=['Authorization: Bearer '.$login['json']['data']['access_token']];
            if ($name==='t3_other') { self::$otherHeaders=$headers; }
            else { self::$reviewerHeaders=$headers; self::$reviewer=Subject::build('account',(int)$rows[0]['id']); }
        }
        $GLOBALS['connection']=$tenant;
        $GLOBALS['db_type']='postgresql';
        $GLOBALS['db']=RestApiEnv::testDb();
        $GLOBALS['db_skriv_id']=0;
        $GLOBALS['bruger_id']=self::$userId;
        $GLOBALS['brugernavn']=RestApiEnv::user();
        $GLOBALS['revisor']=false;
        self::$root=dirname(poolPuljePath(RestApiEnv::testDb()));
        if (!is_dir(self::$root.'/pulje')) { mkdir(self::$root.'/pulje',0777,true); }
        chmod(self::$root,0777);
        chmod(self::$root.'/pulje',0777);
        RestApiEnv::rows($tenant,"INSERT INTO kontoplan(kontonr,regnskabsaar,kontotype,lukket) SELECT 987001,kodenr,'D',NULL FROM grupper WHERE art='RA' AND box2='2026'");
        RestApiEnv::rows($tenant,"INSERT INTO grupper(kode,kodenr,beskrivelse,art) VALUES ('K',987,'Slice VAT','KM')");
    }
    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            pg_close(self::$conn);
            foreach ([self::$root,dirname(__DIR__,3).'/temp/'.RestApiEnv::testDb()] as $path) {
                if (is_dir($path)) { self::remove($path); }
            }
            foreach (self::$globals as $key=>[$exists,$value]) {
                if ($exists) { $GLOBALS[$key]=$value; } else { unset($GLOBALS[$key]); }
            }
            $_SERVER=self::$server;
        }
        RestApiEnv::teardownTenant();
    }
    private static function remove(string $path): void
    {
        foreach (new FilesystemIterator($path) as $file) {
            if ($file->isDir() && !$file->isLink()) { self::remove($file->getPathname()); }
            else { unlink($file->getPathname()); }
        }
        rmdir($path);
    }
    protected function fixture(string $status='ubehandlet',string $review='pending'): array
    {
        $name=RequestId::resolve(null).'.pdf';
        return RestApiEnv::rows(self::$conn,'INSERT INTO pool_files(filename,uploaded_by,status,review_state,subject,description,amount,file_date,currency,storage_path)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) RETURNING *',
            [$name,self::$owner,$status,$review,'Supplier','Original','10.00','2026-09-14','DKK','pulje/'.$name])[0];
    }
    protected function row(int $id): array { return RestApiEnv::rows(self::$conn,'SELECT * FROM pool_files WHERE id=$1',[$id])[0]; }
    protected function patch(array $row,array $body,?array $headers=null,?string $etag=null): array
    {
        return RestApiEnv::http('PATCH','/restapi/endpoints/v1/attachment/metadata.php?id='.$row['id'],$body,
            array_merge($headers ?? self::$ownerHeaders,['If-Match: '.($etag ?? '"'.pool_review_version($row).'"')]));
    }
    protected function body(array $fields=[]): array { return ['client_id'=>RequestId::resolve(null)]+$fields; }
    protected function service(): AttachmentMetadataService
    {
        return new AttachmentMetadataService(self::$conn,RestApiEnv::testDb(),self::$owner,RestApiEnv::user(),self::$userId,
            RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RequestId::resolve(null));
    }
}
