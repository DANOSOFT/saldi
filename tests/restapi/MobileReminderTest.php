<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileReminderTest.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Verify sender display names and username fallbacks across principal namespaces.
// 20261005 CDX/LH Keep fixture cleanup compatible with retained T3 provenance and current migration markers.
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/services/ReminderService.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../restapi/core/JWT.php';

final class MobileReminderTest extends TestCase
{
    private static $master;
    private static $tenant;
    private static int $tenantId;
    private static array $bookkeeper;
    private static array $capture;
    private static array $registryUser;
    private static string $bookkeeperSubject;
    private static string $captureSubject;
    private static string $registrySubject;
    private static string $bookkeeperName;
    private static string $captureName;
    private static string $webSession;
    private ReminderService $service;

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        foreach (['sqhost'=>RestApiEnv::pgHost(),'squser'=>RestApiEnv::pgUser(),'sqpass'=>RestApiEnv::pgPass(),'sqdb'=>RestApiEnv::masterDb(),'db_type'=>'postgresql','db_encode'=>'UTF8'] as $key=>$value) {
            $GLOBALS[$key] = $value;
        }
        RestApiEnv::signToken(['type'=>'access','user_id'=>1]);
        JWT::setClaimEnricher(null);
        self::$master = RestApiEnv::connect(RestApiEnv::masterDb());
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        self::$tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        self::$bookkeeperName = 'reminder_fin_' . bin2hex(random_bytes(4));
        self::$captureName = 'reminder_own_' . bin2hex(random_bytes(4));
        RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET brugernavn=$1,rettigheder=$2 WHERE brugernavn=$3', [self::$bookkeeperName,'0010000000000000',RestApiEnv::user()]);
        RestApiEnv::rows(self::$tenant, 'INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,true)', [self::$captureName,md5(RestApiEnv::password()),str_repeat('0',16)]);
        foreach (['bookkeeper'=>self::$bookkeeperName,'capture'=>self::$captureName] as $kind=>$username) {
            $res = RestApiEnv::login($username,RestApiEnv::password(),RestApiEnv::accountOpen());
            if ($res['status'] !== 200) {
                throw new RuntimeException('Reminder fixture login failed');
            }
            self::${$kind} = $res['json']['data'];
        }
        self::$bookkeeperSubject = JWT::decode(self::$bookkeeper['access_token'])['sub'];
        self::$captureSubject = JWT::decode(self::$capture['access_token'])['sub'];
        self::$registryUser = RestApiEnv::rows(self::$master,'INSERT INTO brugere(brugernavn,kode,rettigheder) VALUES($1,$2,$3) RETURNING *', ['b7_registry_' . bin2hex(random_bytes(4)),md5(TestCredentials::password('b7registry')),',,,' . self::$tenantId])[0];
        self::$registrySubject = Subject::build('registry',(int)self::$registryUser['id']);
        (new MobileAuth())->create(self::$registryUser,'registry',self::$tenantId,'b7-registry');
        self::$webSession = bin2hex(random_bytes(16));
        // Existing online.php authenticates these exact rows; no web protection is bypassed.
        $year = RestApiEnv::rows(self::$tenant,"SELECT kodenr FROM grupper WHERE art='RA' ORDER BY kodenr DESC LIMIT 1")[0]['kodenr'];
        RestApiEnv::rows(self::$master,'INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,revisor,logtime,regnskabsaar,language_id) VALUES($1,$2,$3,$4,$5,false,$6,$7,1)', [self::$webSession,self::$bookkeeperName,RestApiEnv::testDb(),RestApiEnv::pgUser(),'0010000000000000',(string)time(),$year]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$master) {
            RestApiEnv::rows(self::$master,'DELETE FROM online WHERE session_id=$1',[self::$webSession]);
            RestApiEnv::rows(self::$master,'DELETE FROM refresh_sessions WHERE tenant_id=$1 OR subject=$2',[self::$tenantId,self::$registrySubject]);
            RestApiEnv::rows(self::$master,'DELETE FROM auth_versions WHERE tenant_id=$1 OR subject=$2',[self::$tenantId,self::$registrySubject]);
            RestApiEnv::rows(self::$master,'DELETE FROM brugere WHERE id=$1',[self::$registryUser['id']]);
            pg_close(self::$tenant);
            pg_close(self::$master);
        }
        RestApiEnv::teardownTenant();
    }

    protected function setUp(): void
    {
        // Preserve append-only T3 provenance while clearing mutable notification fixtures.
        foreach (['push_dispatch','notification_recipients','notifications'] as $table) {
            RestApiEnv::rows(self::$tenant, "DELETE FROM $table");
        }
        RestApiEnv::rows(self::$tenant, 'UPDATE pool_files SET reminder_id=NULL WHERE reminder_id IS NOT NULL');
        foreach (['reminders','push_tokens'] as $table) {
            RestApiEnv::rows(self::$tenant, "DELETE FROM $table");
        }
        RestApiEnv::rows(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',['0010000000000000',self::$bookkeeperName]);
        $this->service = new ReminderService(self::$tenant,self::$master,self::$tenantId);
    }

    /** @return array{status:int,json:array|null,body:string} */
    private function http(string $method,string $path,?array $body = null,bool $asCapture = false): array
    {
        $login = $asCapture ? self::$capture : self::$bookkeeper;
        return RestApiEnv::http($method,'/restapi/endpoints/v1/' . $path,$body,['Authorization: Bearer ' . $login['access_token']]);
    }

    public function test_app_users_are_session_based_without_push_tokens_and_skip_revoked_expired_other_tenant(): void
    {
        $res = $this->http('GET','app-users.php');
        $this->assertSame(200,$res['status'],$res['body']);
        $this->assertEqualsCanonicalizing([self::$bookkeeperSubject,self::$captureSubject,self::$registrySubject],array_column($res['json']['data'],'subject'));
        $this->assertSame([],RestApiEnv::rows(self::$tenant,'SELECT id FROM push_tokens'));
        RestApiEnv::rows(self::$master,'UPDATE refresh_sessions SET expires_at=now()-interval \'1 second\' WHERE subject=$1 AND tenant_id=$2',[self::$captureSubject,self::$tenantId]);
        try {
            $this->assertNotContains(self::$captureSubject,array_column($this->service->appUsers(),'subject'));
        } finally {
            RestApiEnv::rows(self::$master,'UPDATE refresh_sessions SET expires_at=now()+interval \'1 day\' WHERE subject=$1 AND tenant_id=$2',[self::$captureSubject,self::$tenantId]);
        }
        RestApiEnv::rows(self::$master,'UPDATE refresh_sessions SET revoked_at=now() WHERE subject=$1',[self::$registrySubject]);
        try {
            $this->assertNotContains(self::$registrySubject,array_column($this->service->appUsers(),'subject'));
        } finally {
            RestApiEnv::rows(self::$master,'UPDATE refresh_sessions SET revoked_at=NULL WHERE subject=$1',[self::$registrySubject]);
        }
        RestApiEnv::rows(self::$master,'UPDATE refresh_sessions SET tenant_id=$1 WHERE subject=$2',[RestApiEnv::regnskabId(RestApiEnv::accountClosed()),self::$registrySubject]);
        try {
            $this->assertNotContains(self::$registrySubject,array_column($this->service->appUsers(),'subject'));
        } finally {
            RestApiEnv::rows(self::$master,'UPDATE refresh_sessions SET tenant_id=$1 WHERE subject=$2',[self::$tenantId,self::$registrySubject]);
        }
        $this->assertSame(403,$this->http('GET','app-users.php',null,true)['status']);
    }

    public function test_targeted_reminder_inbox_and_authorized_idempotent_resolution(): void
    {
        $res = $this->http('POST','reminders/',['target'=>self::$captureSubject,'message'=>'Circle K 14/9','amount'=>'612.500','date'=>'2026-09-14']);
        $this->assertSame(201,$res['status'],$res['body']);
        $data = $res['json']['data'];
        $this->assertSame(1,$data['recipient_count']);
        $this->assertSame(0,$data['dispatch_count']);
        $inbox = $this->http('GET','notifications/',null,true)['json']['data']['items'];
        $this->assertCount(1,$inbox);
        $this->assertSame($data['id'],$inbox[0]['reminder_id']);
        $this->assertSame('612.500',$inbox[0]['amount']);
        $this->assertSame('2026-09-14',$inbox[0]['date']);
        $this->assertSame(self::$bookkeeperName, $inbox[0]['sender_name']);
        try {
            mobileResolveReminder(self::$tenant,self::$tenantId,self::$bookkeeperSubject,$data['id']);
            $this->fail('Wrong targeted uploader must fail');
        } catch (MobileReminderError $e) {
            $this->assertSame('permission_denied',$e->error);
        }
        $first = mobileResolveReminder(self::$tenant,self::$tenantId,self::$captureSubject,$data['id']);
        $second = mobileResolveReminder(self::$tenant,self::$tenantId,self::$captureSubject,$data['id']);
        $this->assertSame($first,$second);
        $history = $this->http('GET','reminders/')['json']['data']['items'];
        $this->assertSame($first['resolved_at'],$history[0]['recipients'][0]['resolved_at']);
        $this->assertSame(self::$bookkeeperName, $history[0]['sender_name']);
        $this->assertSame(403,$this->http('GET','reminders/',null,true)['status']);
    }

    public function test_sender_display_names_and_principal_namespaces_on_both_lists(): void
    {
        $employee = (int)RestApiEnv::rows(self::$tenant, "INSERT INTO ansatte(navn,konto_id) VALUES ('Mette',0) RETURNING id")[0]['id'];
        $accountId = (int)Subject::parse(self::$bookkeeperSubject)['user_id'];
        $previousEmployee = RestApiEnv::rows(self::$tenant, 'SELECT ansat_id FROM brugere WHERE id=$1', [$accountId])[0]['ansat_id'];
        $registryId = (int)self::$registryUser['id'];
        $equalAccount = RestApiEnv::rows(self::$tenant, 'SELECT id FROM brugere WHERE id=$1', [$registryId]);
        if ($equalAccount === []) {
            RestApiEnv::rows(self::$tenant, "INSERT INTO brugere(id,brugernavn,kode,rettigheder,status) VALUES ($1,'Different account user',$2,$3,true)",
                [$registryId, md5(TestCredentials::password('fxpequaluser')), str_repeat('0',16)]);
        }
        try {
            RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET ansat_id=$1 WHERE id=$2', [$employee, $accountId]);
            $account = $this->service->create(self::$bookkeeperSubject, ['target'=>self::$captureSubject, 'message'=>'Account sender']);
            $registry = $this->service->create(self::$registrySubject, ['target'=>self::$captureSubject, 'message'=>'Registry sender']);
            foreach (['notifications/', 'reminders/'] as $route) {
                $response = $this->http('GET', $route, null, $route === 'notifications/');
                $this->assertSame(200, $response['status'], $response['body']);
                $items = array_column($response['json']['data']['items'], null, 'created_by');
                $this->assertSame('Mette', $items[self::$bookkeeperSubject]['sender_name']);
                $this->assertSame(self::$registryUser['brugernavn'], $items[self::$registrySubject]['sender_name']);
                $this->assertCount(2, $items);
            }
            // Empty or removed employee names fall back to the creating user's username.
            RestApiEnv::rows(self::$tenant, "UPDATE ansatte SET navn='   ' WHERE id=$1", [$employee]);
            foreach ([false, true] as $removeEmployee) {
                if ($removeEmployee) {
                    RestApiEnv::rows(self::$tenant, 'DELETE FROM ansatte WHERE id=$1', [$employee]);
                }
                foreach (['notifications/', 'reminders/'] as $route) {
                    $items = array_column($this->http('GET', $route, null, $route === 'notifications/')['json']['data']['items'], null, 'created_by');
                    $this->assertSame(self::$bookkeeperName, $items[self::$bookkeeperSubject]['sender_name']);
                }
            }
        } finally {
            RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET ansat_id=$1 WHERE id=$2', [$previousEmployee, $accountId]);
            RestApiEnv::rows(self::$tenant, 'DELETE FROM ansatte WHERE id=$1', [$employee]);
            if ($equalAccount === []) {
                RestApiEnv::rows(self::$tenant, 'DELETE FROM brugere WHERE id=$1', [$registryId]);
            }
        }
    }

    public function test_broadcast_resolution_is_per_recipient_and_rejects_nonrecipient_foreign_tenant(): void
    {
        $res = $this->http('POST','reminders/',['target'=>'all','message'=>'Send jeres bilag']);
        $this->assertSame(201,$res['status'],$res['body']);
        $data = $res['json']['data'];
        $this->assertSame(3,$data['recipient_count']);
        mobileResolveReminder(self::$tenant,self::$tenantId,self::$captureSubject,$data['id']);
        $rows = RestApiEnv::rows(self::$tenant,'SELECT subject,resolved_at FROM notification_recipients ORDER BY subject');
        foreach ($rows as $row) {
            if ($row['subject'] === self::$captureSubject) {
                $this->assertNotNull($row['resolved_at']);
            } else {
                $this->assertNull($row['resolved_at']);
            }
        }
        mobileResolveReminder(self::$tenant,self::$tenantId,self::$registrySubject,$data['id']);
        foreach ([[self::$tenantId,Subject::build('account',99999),$data['id']], [self::$tenantId,self::$captureSubject,99999]] as [$tenant,$subject,$id]) {
            try {
                mobileResolveReminder(self::$tenant,$tenant,$subject,$id);
                $this->fail('Foreign/missing reminder must fail');
            } catch (MobileReminderError $e) {
                $this->assertSame(403,$e->status);
            }
        }
        $other = RestApiEnv::connect(RestApiEnv::templateDb());
        try {
            mobileResolveReminder($other,self::$tenantId,self::$captureSubject,$data['id']);
            $this->fail('Foreign database must fail before resolution');
        } catch (MobileReminderError $e) {
            $this->assertSame('permission_denied',$e->error);
        } finally {
            pg_close($other);
        }
    }

    public function test_validation_rights_history_paging_and_transaction_rollback(): void
    {
        foreach ([['target'=>'all','message'=>''],['target'=>'all','message'=>"invalid\0"],['target'=>'all','message'=>'x','date'=>"2026-10-02\0"],['target'=>self::$captureSubject,'message'=>'x','date'=>'2026-02-30'],['target'=>'all','message'=>'x','date'=>'0000-01-01'],['target'=>'all','message'=>'x','amount'=>['1']],['target'=>'all','message'=>'x','amount'=>'1e3'],['target'=>'unknown','message'=>'x']] as $body) {
            $res = $this->http('POST','reminders/',$body);
            $this->assertSame(422,$res['status'],$res['body']);
            $this->assertSame('validation_failed',$res['json']['data']['error']);
        }
        RestApiEnv::rows(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',['0020000000000000',self::$bookkeeperName]);
        $this->assertSame(200,$this->http('GET','reminders/')['status']);
        $this->assertSame(403,$this->http('POST','reminders/',['target'=>'all','message'=>'Denied'])['status']);
        RestApiEnv::rows(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',['0010000000000000',self::$bookkeeperName]);
        $a = $this->service->create(self::$bookkeeperSubject,['target'=>'all','message'=>'First']);
        $b = $this->service->create(self::$bookkeeperSubject,['target'=>'all','message'=>'Second']);
        $first = $this->http('GET','reminders/?limit=1')['json']['data'];
        $this->assertSame($b['id'],$first['next_before']);
        $second = $this->http('GET','reminders/?limit=1&before=' . $first['next_before'])['json']['data'];
        $this->assertSame($a['id'],$second['items'][0]['id']);
        $this->assertNull($second['next_before']);
        $this->assertSame(422,$this->http('GET','reminders/?before=1%20OR%201=1')['status']);
        $counts = RestApiEnv::rows(self::$tenant,'SELECT count(*) FROM reminders')[0]['count'];
        RestApiEnv::rows(self::$tenant,"ALTER TABLE notifications ADD CONSTRAINT b7_reject CHECK (message<>'Rollback fixture')");
        try {
            $res = $this->http('POST','reminders/',['target'=>'all','message'=>'Rollback fixture']);
            $this->assertSame(500,$res['status']);
            $this->assertSame('internal',$res['json']['data']['error']);
            $this->assertSame($counts,RestApiEnv::rows(self::$tenant,'SELECT count(*) FROM reminders')[0]['count']);
        } finally {
            RestApiEnv::rows(self::$tenant,'ALTER TABLE notifications DROP CONSTRAINT b7_reject');
        }
    }

    /** @return array{status:int,body:string} */
    private function web(string $method,?array $body = null,bool $withSession = true): array
    {
        $ch = curl_init(RestApiEnv::baseUrl() . '/finans/bilag_paamindelse.php');
        $opts = [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false];
        if ($withSession) {
            $opts[CURLOPT_COOKIE] = 'PHPSESSID=' . self::$webSession;
        }
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($body);
        }
        curl_setopt_array($ch,$opts);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status'=>$status,'body'=>(string)$raw];
    }

    public function test_logged_in_finans_screen_renders_escapes_and_posts_with_csrf_prg(): void
    {
        $res = $this->web('GET');
        $this->assertSame(200,$res['status'],$res['body']);
        $this->assertStringContainsString('Bilagspåmindelser',$res['body']);
        $this->assertStringContainsString(self::$captureName,$res['body']);
        $this->assertStringContainsString('Send påmindelse',$res['body']);
        $this->assertStringNotContainsString('Fatal error',$res['body']);
        $this->assertStringNotContainsString('Warning:',$res['body']);
        $matched = preg_match("/name='csrf' value='([a-f0-9]+)'/",$res['body'],$m);
        $this->assertSame(1,$matched);
        $invalid = $this->web('POST',['target'=>'all','message'=>'forged']);
        $this->assertSame(403,$invalid['status']);
        $wrongDb = $this->web('POST',['csrf'=>$m[1],'tenant_db'=>'other','target'=>'all','message'=>'forged']);
        $this->assertSame(403,$wrongDb['status']);
        $post = $this->web('POST',['csrf'=>$m[1],'tenant_db'=>RestApiEnv::testDb(),'target'=>self::$captureSubject,'message'=>'<script>private</script>']);
        $this->assertSame(303,$post['status'],$post['body']);
        $get = $this->web('GET');
        $this->assertStringContainsString('Påmindelsen er sendt.',$get['body']);
        $this->assertStringContainsString('&lt;script&gt;private&lt;/script&gt;',$get['body']);
        $this->assertStringNotContainsString('<script>private</script>',$get['body']);
        $row = RestApiEnv::rows(self::$tenant,'SELECT id FROM reminders')[0];
        mobileResolveReminder(self::$tenant,self::$tenantId,self::$captureSubject,(int)$row['id']);
        $this->assertStringContainsString('Besvaret',$this->web('GET')['body']);
        $anonymous = $this->web('GET',null,false);
        $this->assertStringNotContainsString(self::$captureName,$anonymous['body']);
    }
}
