<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T3B6TestCase.php --- ver 5.0.0 --- 2026.10.05
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
require_once __DIR__ . '/../../../restapi/services/BookkeeperSummaryService.php';

/** Disposable tenants and principals shared only by the B6 summary tests. */
abstract class T3B6TestCase extends TestCase
{
    protected static $tenant;
    protected static $registry;
    protected static $second;
    protected static int $tenantId;
    protected static int $closedId;
    protected static int $secondId;
    protected static string $secondDb;
    protected static array $user;
    protected static string $subject;
    protected static array $headers;
    private static array $savedGlobals = [];
    private static array $savedServer;

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        self::$savedServer = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/tests/t3-b6';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        foreach (['sqhost'=>RestApiEnv::pgHost(),'squser'=>RestApiEnv::pgUser(),'sqpass'=>RestApiEnv::pgPass(),
            'sqdb'=>RestApiEnv::masterDb(),'db_type'=>'postgresql','db_encode'=>'UTF8'] as $key=>$value) {
            self::$savedGlobals[$key] = [array_key_exists($key,$GLOBALS),ifset($GLOBALS,$key)];
            $GLOBALS[$key] = $value;
        }
        RestApiEnv::bootstrapTenant();
        JWT::setClaimEnricher(null);
        RestApiEnv::signToken(['type'=>'access','user_id'=>1]);
        self::$headers = RestApiEnv::authHeaders();
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        self::$registry = RestApiEnv::connect(RestApiEnv::masterDb());
        self::$tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        self::$closedId = RestApiEnv::regnskabId(RestApiEnv::accountClosed());
        mobileRegistryUpdates(self::$registry);
        mobileTenantUpdates(self::$tenant);
        self::$secondDb = 'b6_other_' . bin2hex(random_bytes(4));
        RestApiEnv::rows(self::$registry,'CREATE DATABASE ' . self::$secondDb . ' TEMPLATE ' . RestApiEnv::templateDb());
        self::$second = RestApiEnv::connect(self::$secondDb);
        mobileTenantUpdates(self::$second);
        self::$secondId = (int)RestApiEnv::rows(self::$registry,
            "INSERT INTO regnskab(regnskab,db,lukket) VALUES($1,$2,'') RETURNING id",[self::$secondDb,self::$secondDb])[0]['id'];
        self::$user = [];
        for ($attempt=0;$attempt<50;$attempt++) {
            $users = RestApiEnv::rows(self::$registry,
                'INSERT INTO brugere(id,brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,$4,true) ON CONFLICT(id) DO NOTHING RETURNING *',
                [random_int(100,999),'b6_' . bin2hex(random_bytes(4)),md5(TestCredentials::password('t3_b6')),',,,' . self::$tenantId]);
            if ($users) {
                self::$user = $users[0];
                break;
            }
        }
        if (!self::$user) {
            throw new RuntimeException('B6 user allocation failed');
        }
        self::$subject = Subject::build('registry',(int)self::$user['id']);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$registry) {
            foreach (['refresh_sessions','auth_versions','auth_challenges'] as $table) {
                RestApiEnv::rows(self::$registry,'DELETE FROM ' . $table . ' WHERE subject=$1',[self::$subject]);
            }
            RestApiEnv::rows(self::$registry,'DELETE FROM brugere WHERE id=$1',[self::$user['id']]);
            RestApiEnv::rows(self::$registry,'DELETE FROM mobile_summary_cache WHERE tenant_id=ANY($1::int[])',
                ['{' . self::$tenantId . ',' . self::$closedId . ',' . self::$secondId . '}']);
            RestApiEnv::rows(self::$registry,'DELETE FROM regnskab WHERE id=$1',[self::$secondId]);
            pg_close(self::$second);
            RestApiEnv::rows(self::$registry,'DROP DATABASE ' . self::$secondDb . ' WITH (FORCE)');
            pg_close(self::$tenant);
            pg_close(self::$registry);
            self::$registry = null;
        }
        RestApiEnv::teardownTenant();
        foreach (self::$savedGlobals as $key=>[$exists,$value]) {
            if ($exists) {
                $GLOBALS[$key]=$value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
        $_SERVER = self::$savedServer;
    }

    protected function setUp(): void
    {
        RestApiEnv::rows(self::$registry,'DELETE FROM mobile_summary_cache WHERE tenant_id=ANY($1::int[])',
            ['{' . self::$tenantId . ',' . self::$secondId . '}']);
        RestApiEnv::rows(self::$registry,'UPDATE brugere SET rettigheder=$1,status=true WHERE id=$2',[',,,' . self::$tenantId,self::$user['id']]);
        RestApiEnv::rows(self::$registry,"UPDATE regnskab SET lukket='',lukkes=NULL WHERE id=ANY($1::int[])",
            ['{' . self::$tenantId . ',' . self::$secondId . '}']);
        RestApiEnv::rows(self::$registry,'DELETE FROM refresh_sessions WHERE subject=$1',[self::$subject]);
        RestApiEnv::rows(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[str_repeat('1',16),RestApiEnv::USER]);
    }

    protected function service(?callable $clock=null): BookkeeperSummaryService
    {
        return new BookkeeperSummaryService(self::$registry,$clock);
    }

    /** @return array Modern registry token pair without a password HTTP/rate-limit dependency. */
    protected function pair(?int $tenantId=null): array
    {
        return (new MobileAuth())->create(self::$user,'registry',$tenantId,'b6-test');
    }

    /** @return array Summary HTTP response using a tenantless registry token. */
    protected function registryRequest(string $query='',?string $token=null): array
    {
        return RestApiEnv::http('GET','/restapi/endpoints/v1/bookkeeper/registry-summary.php' . $query,null,
            ['Authorization: Bearer ' . ($token ?? $this->pair()['access_token'])]);
    }

    /** @return array<string,mixed> One retained bilag; no files are needed for summary reads. */
    protected static function pool($link,string $status='ubehandlet',string $review='pending',int $age=0): array
    {
        return RestApiEnv::rows($link,"INSERT INTO pool_files(filename,status,review_state,uploaded_at)
            VALUES($1,$2,$3,now()-($4::int * interval '1 day')) RETURNING *",
            [MobileAuth::uuid() . '.pdf',$status,$review,$age])[0];
    }
}
