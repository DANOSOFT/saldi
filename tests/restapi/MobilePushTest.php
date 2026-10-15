<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobilePushTest.php --- ver 5.0.0 --- 2026.10.01
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
// 20261005 CDX/LH Keep fixture cleanup compatible with retained T3 provenance and current migration markers.
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/core/JWT.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../restapi/services/PushService.php';
require_once __DIR__ . '/../../restapi/services/PushDispatchWorker.php';

final class MobilePushTest extends TestCase
{
    private static string $username;
    private static array $login;
    private static string $subject;
    private static int $tenantId;
    private $tenant;
    private $server;
    private string $stubUrl;
    private string $logFile;

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
        self::$username = 'push_' . bin2hex(random_bytes(4));
        $tenant = RestApiEnv::connect(RestApiEnv::testDb());
        RestApiEnv::rows($tenant, 'UPDATE brugere SET brugernavn=$1,rettigheder=$2 WHERE brugernavn=$3', [self::$username,str_repeat('0',16),RestApiEnv::user()]);
        $res = RestApiEnv::login(self::$username, RestApiEnv::password(), RestApiEnv::accountOpen());
        if ($res['status'] !== 200) {
            throw new RuntimeException('Push fixture login failed');
        }
        self::$login = $res['json']['data'];
        self::$tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        $claims = JWT::decode(self::$login['access_token']);
        self::$subject = $claims['sub'];
        pg_close($tenant);
    }

    public static function tearDownAfterClass(): void
    {
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows($master, 'DELETE FROM refresh_sessions WHERE tenant_id=$1', [self::$tenantId]);
        RestApiEnv::rows($master, 'DELETE FROM auth_versions WHERE tenant_id=$1', [self::$tenantId]);
        pg_close($master);
        RestApiEnv::teardownTenant();
    }

    protected function setUp(): void
    {
        $this->tenant = RestApiEnv::connect(RestApiEnv::testDb());
        // Preserve append-only T3 provenance while clearing mutable notification fixtures.
        foreach (['push_dispatch','notification_recipients','notifications'] as $table) {
            RestApiEnv::rows($this->tenant, "DELETE FROM $table");
        }
        RestApiEnv::rows($this->tenant, 'UPDATE pool_files SET reminder_id=NULL WHERE reminder_id IS NOT NULL');
        foreach (['reminders','push_tokens'] as $table) {
            RestApiEnv::rows($this->tenant, "DELETE FROM $table");
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->stubUrl = 'http://' . $address;
        $this->logFile = tempnam(sys_get_temp_dir(), 'expo-b7-');
        $env = getenv();
        $env['SALDI_EXPO_STUB_LOG'] = $this->logFile;
        $this->server = proc_open([PHP_BINARY,'-S',$address,__DIR__ . '/support/ExpoPushStub.php'], [0=>['pipe','r'],1=>['file',$this->logFile . '.server','a'],2=>['file',$this->logFile . '.server','a']], $pipes, null, $env);
        $ready = false;
        for ($i=0; $i<100; $i++) {
            $ch = curl_init($this->stubUrl);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT_MS=>50,CURLOPT_TIMEOUT_MS=>100]);
            curl_exec($ch);
            $ready = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) > 0;
            curl_close($ch);
            if ($ready) {
                break;
            }
            usleep(20000);
        }
        $this->assertTrue($ready, 'Local Expo stub must be ready');
    }

    protected function tearDown(): void
    {
        proc_terminate($this->server);
        proc_close($this->server);
        unlink($this->logFile);
        unlink($this->logFile . '.server');
        pg_close($this->tenant);
    }

    /** @return array{status:int,json:array|null,body:string} */
    private function http(string $method, string $path, ?array $body = null): array
    {
        return RestApiEnv::http($method, '/restapi/endpoints/v1/' . $path, $body, ['Authorization: Bearer ' . self::$login['access_token']]);
    }

    /** @return string */
    private function register(string $device = 'phone'): string
    {
        $token = 'ExpoPushToken[' . bin2hex(random_bytes(16)) . ']';
        $res = $this->http('POST','notifications/register.php',['device_id'=>$device,'token'=>$token,'platform'=>'ios']);
        $this->assertSame(200,$res['status'],$res['body']);
        return $token;
    }

    /** @return PushDispatchWorker */
    private function worker(string $send = '/send', string $receipts = '/receipts'): PushDispatchWorker
    {
        return new PushDispatchWorker($this->tenant,self::$tenantId,Subject::installation(),$this->stubUrl . $send,$this->stubUrl . $receipts);
    }

    /** @return array{notification_id:int,recipient_count:int,dispatch_count:int} */
    private function notify(): array
    {
        return (new PushService($this->tenant))->create([self::$subject,self::$subject],self::$subject,'reminder','Private title','Circle K 612,50 kr.');
    }

    public function test_register_upsert_and_scoped_idempotent_logout_delete(): void
    {
        $this->register();
        $token = $this->register();
        $rows = RestApiEnv::rows($this->tenant,'SELECT * FROM push_tokens');
        $this->assertCount(1,$rows);
        $this->assertSame($token,$rows[0]['token']);
        $this->assertSame(self::$subject,$rows[0]['subject']);
        $this->assertSame('account',$rows[0]['principal']);
        $this->assertSame('phone',$rows[0]['device_id']);
        $this->assertSame(RestApiEnv::testDb(),$rows[0]['db_name']);
        $this->assertNotNull($rows[0]['last_seen_at']);
        $this->assertNull($rows[0]['native_token']);
        $foreign = Subject::build('registry',(int)$rows[0]['user_id']);
        RestApiEnv::rows($this->tenant,"INSERT INTO push_tokens(user_id,token,subject,principal,device_id,db_name) VALUES($1,$2,$3,'registry','phone',$4)",[$rows[0]['user_id'],$token,$foreign,RestApiEnv::testDb()]);
        foreach ([1,2] as $ignored) {
            $res = $this->http('DELETE','notifications/register.php',['device_id'=>'phone']);
            $this->assertSame(200,$res['status']);
        }
        $rows = RestApiEnv::rows($this->tenant,'SELECT subject FROM push_tokens');
        $this->assertSame([['subject'=>$foreign]],$rows);
        $res = $this->http('POST','notifications/register.php',['token'=>$token]);
        $this->assertSame(200,$res['status'],$res['body']);
        $this->assertSame(200,$this->http('DELETE','notifications/register.php',['token'=>$token])['status']);
        $this->assertSame(422,$this->http('POST','notifications/register.php',['token'=>[]])['status']);
    }

    public function test_dispatch_inbox_privacy_paging_and_read_are_own_recipient_only(): void
    {
        $this->register();
        $this->register('tablet');
        $notification = $this->notify();
        $this->assertSame(1,$notification['recipient_count']);
        $this->assertSame(2,$notification['dispatch_count']);
        $stats = $this->worker()->run();
        $this->assertSame(2,$stats['ticketed']);
        $requests = array_map(static fn(string $line): array => json_decode($line,true),file($this->logFile,FILE_IGNORE_NEW_LINES));
        $this->assertCount(1,$requests);
        foreach ($requests[0]['body'] as $message) {
            $this->assertSame('Ny besked fra Saldi',$message['title']);
            $this->assertSame('Åbn appen for at se påmindelsen',$message['body']);
            $this->assertSame(['notification_id'=>$notification['notification_id'],'tenant_id'=>self::$tenantId,'installation'=>Subject::installation(),'type'=>'reminder'],$message['data']);
        }
        $this->assertStringNotContainsString('Circle K',file_get_contents($this->logFile));
        $inbox = $this->http('GET','notifications/');
        $this->assertSame(200,$inbox['status'],$inbox['body']);
        $this->assertSame('Circle K 612,50 kr.',$inbox['json']['data']['items'][0]['message']);
        $foreign = Subject::build('registry',9999);
        $other = (new PushService($this->tenant))->create([$foreign],$foreign,'reminder','Other','Other secret');
        $this->assertSame(403,$this->http('POST','notifications/read.php',['notification_id'=>$other['notification_id']])['status']);
        $res = $this->http('POST','notifications/read.php',['notification_id'=>$notification['notification_id']]);
        $this->assertSame(200,$res['status'],$res['body']);
        $this->assertNotNull($this->http('GET','notifications/')['json']['data']['items'][0]['read_at']);
        $next = $this->notify();
        $first = $this->http('GET','notifications/?limit=1')['json']['data'];
        $this->assertSame($next['notification_id'],$first['next_before']);
        $second = $this->http('GET','notifications/?limit=1&before=' . $first['next_before'])['json']['data'];
        $this->assertSame($notification['notification_id'],$second['items'][0]['notification_id']);
        $this->assertNull($second['next_before']);
        $this->assertSame(422,$this->http('GET','notifications/?limit[]=1')['status']);
        $this->assertSame(403,$this->http('GET','app-users.php')['status']);
        $this->assertSame(403,$this->http('POST','reminders/',['target'=>'all','message'=>'Denied'])['status']);
    }

    public function test_tickets_wait_fifteen_minutes_then_receipts_and_prune(): void
    {
        $this->register();
        $this->notify();
        $this->assertSame(1,$this->worker()->run()['ticketed']);
        $this->assertSame(0,$this->worker()->run()['delivered']);
        RestApiEnv::rows($this->tenant,"UPDATE push_dispatch SET next_attempt_at=now()-interval '1 second'");
        $this->assertSame(1,$this->worker()->run()['delivered']);
        $this->assertSame('delivered',RestApiEnv::rows($this->tenant,'SELECT state FROM push_dispatch')[0]['state']);
        $this->notify();
        $this->worker()->run();
        RestApiEnv::rows($this->tenant,"UPDATE push_dispatch SET next_attempt_at=now()-interval '1 second' WHERE state='ticketed'");
        $stats = $this->worker('/send','/device-receipts')->run();
        $this->assertSame(1,$stats['rejected']);
        $this->assertSame([],RestApiEnv::rows($this->tenant,'SELECT id FROM push_tokens'));
        $this->assertCount(2,RestApiEnv::rows($this->tenant,'SELECT id FROM notification_recipients'));
    }

    public function test_ticket_device_not_registered_and_rotated_token_receipt_protection(): void
    {
        $this->register();
        $this->notify();
        $this->assertSame(1,$this->worker('/device-tickets')->run()['rejected']);
        $this->assertSame([],RestApiEnv::rows($this->tenant,'SELECT id FROM push_tokens'));
        $this->register();
        $this->notify();
        $this->worker()->run();
        $newToken = $this->register();
        RestApiEnv::rows($this->tenant,"UPDATE push_dispatch SET next_attempt_at=now()-interval '1 second' WHERE state='ticketed'");
        $this->worker('/send','/device-receipts')->run();
        $this->assertSame($newToken,RestApiEnv::rows($this->tenant,'SELECT token FROM push_tokens')[0]['token']);
    }

    public function test_killed_worker_resends_once_on_the_same_dispatch_and_no_new_inbox_row(): void
    {
        $this->register();
        $this->notify();
        $code = 'require ' . var_export(dirname(__DIR__,2) . '/restapi/core/BaseEndpoint.php',true) . ';require ' . var_export(__DIR__ . '/support/RestApiEnv.php',true) . ';require ' . var_export(dirname(__DIR__,2) . '/restapi/services/PushDispatchWorker.php',true) . ';$c=RestApiEnv::connect(' . var_export(RestApiEnv::testDb(),true) . ');(new PushDispatchWorker($c,' . self::$tenantId . ',' . var_export(Subject::installation(),true) . ',' . var_export($this->stubUrl . '/slow-send',true) . ',' . var_export($this->stubUrl . '/receipts',true) . '))->run();';
        $child = proc_open([PHP_BINARY,'-r',$code],[0=>['pipe','r'],1=>['file',$this->logFile . '.server','a'],2=>['file',$this->logFile . '.server','a']],$pipes);
        $seen = false;
        for ($i=0; $i<200; $i++) {
            if (str_contains(file_get_contents($this->logFile),'/slow-send')) {
                $seen = true;
                break;
            }
            usleep(10000);
        }
        proc_terminate($child,9);
        proc_close($child);
        $this->assertTrue($seen,'Worker must have committed lease before SIGKILL');
        $row = RestApiEnv::rows($this->tenant,'SELECT id,state,attempts FROM push_dispatch')[0];
        $this->assertSame('sent',$row['state']);
        $this->assertSame('1',$row['attempts']);
        $this->assertSame(0,$this->worker()->run()['claimed']);
        RestApiEnv::rows($this->tenant,"UPDATE push_dispatch SET next_attempt_at=now()-interval '1 second'");
        $this->assertSame(1,$this->worker()->run()['ticketed']);
        $this->assertSame(0,$this->worker()->run()['claimed']);
        $rows = RestApiEnv::rows($this->tenant,'SELECT id,attempts FROM push_dispatch');
        $this->assertSame([['id'=>$row['id'],'attempts'=>'2']],$rows);
        $this->assertCount(1,RestApiEnv::rows($this->tenant,'SELECT id FROM notifications'));
        $this->assertCount(1,RestApiEnv::rows($this->tenant,'SELECT id FROM notification_recipients'));
        $this->assertCount(2,file($this->logFile));
    }

    public function test_transport_retry_and_retention_do_not_duplicate_inbox(): void
    {
        $this->register();
        $this->notify();
        $this->worker('/transport-error')->run();
        $row = RestApiEnv::rows($this->tenant,'SELECT state,last_error,attempts FROM push_dispatch')[0];
        $this->assertSame('stored',$row['state']);
        $this->assertSame('TransportError',$row['last_error']);
        RestApiEnv::rows($this->tenant,"UPDATE push_dispatch SET next_attempt_at=now()-interval '1 second'");
        $this->assertSame(1,$this->worker()->run()['ticketed']);
        $this->assertCount(1,RestApiEnv::rows($this->tenant,'SELECT id FROM notification_recipients'));
        RestApiEnv::rows($this->tenant,"UPDATE notifications SET created_at=now()-interval '13 months'");
        $this->worker()->run();
        foreach (['push_dispatch','notification_recipients','notifications'] as $table) {
            $this->assertSame([],RestApiEnv::rows($this->tenant,'SELECT id FROM ' . $table));
        }
    }

    public function test_cron_is_cli_only_and_uses_the_configured_local_relay(): void
    {
        $this->register();
        $this->notify();
        $env = getenv();
        $env['SALDI_EXPO_PUSH_URL'] = $this->stubUrl . '/send';
        $env['SALDI_EXPO_RECEIPTS_URL'] = $this->stubUrl . '/receipts';
        $child = proc_open([PHP_BINARY,dirname(__DIR__,2) . '/cron/push_dispatch.php','--tenant-id=' . self::$tenantId],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($child);
        $this->assertSame(0,$exit,$errors);
        $stats = json_decode(trim($output),true);
        $this->assertSame(self::$tenantId,$stats['tenant_id']);
        $this->assertSame(1,$stats['ticketed']);
        $this->assertSame(403,RestApiEnv::http('GET','/cron/push_dispatch.php')['status']);
    }

    public function test_legacy_notifications_are_preserved_and_retry_limit_stops_send(): void
    {
        RestApiEnv::rows($this->tenant,"INSERT INTO notifications(msg,read_status,created_at) VALUES('Legacy message',0,now()-interval '13 months')");
        $this->register();
        $this->notify();
        RestApiEnv::rows($this->tenant,'UPDATE push_dispatch SET attempts=8');
        $stats = $this->worker()->run();
        $this->assertSame(0,$stats['claimed']);
        $this->assertSame(1,$stats['rejected']);
        $this->assertSame('',file_get_contents($this->logFile));
        $this->assertSame([['msg'=>'Legacy message']],RestApiEnv::rows($this->tenant,'SELECT msg FROM notifications WHERE type IS NULL'));
    }


    public function test_missing_receipts_back_off_without_resending_and_expire(): void
    {
        $this->register();
        $this->notify();
        $this->worker()->run();
        RestApiEnv::rows($this->tenant,"UPDATE push_dispatch SET next_attempt_at=now()-interval '1 second'");
        $stats = $this->worker('/send','/empty-receipts')->run();
        $this->assertSame(0,$stats['claimed']);
        $row = RestApiEnv::rows($this->tenant,'SELECT state,attempts,last_error,next_attempt_at>now() AS delayed FROM push_dispatch')[0];
        $this->assertSame('ticketed',$row['state']);
        $this->assertSame('1',$row['attempts']);
        $this->assertSame('ReceiptPending',$row['last_error']);
        $this->assertSame('t',$row['delayed']);
        RestApiEnv::rows($this->tenant,"UPDATE push_dispatch SET ticketed_at=now()-interval '25 hours',next_attempt_at=now()-interval '1 second'");
        $this->assertSame(1,$this->worker('/send','/empty-receipts')->run()['rejected']);
        $this->assertSame('ReceiptMissing',RestApiEnv::rows($this->tenant,'SELECT last_error FROM push_dispatch')[0]['last_error']);
        $this->assertCount(3,file($this->logFile));
    }

}
