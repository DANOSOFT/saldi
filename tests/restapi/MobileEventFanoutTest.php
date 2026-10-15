<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileEventFanoutTest.php --- ver 5.0.0 --- 2026.10.05
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
require_once __DIR__ . '/support/T3EventTestEnv.php';

final class MobileEventFanoutTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        T3EventTestEnv::setup();
    }
    public static function tearDownAfterClass(): void
    {
        T3EventTestEnv::teardown();
    }
    protected function setUp(): void
    {
        T3EventTestEnv::reset();
    }
    private function rows(string $sql, array $params = []): array
    {
        return RestApiEnv::rows(T3EventTestEnv::$tenant,$sql,$params);
    }

    public function testFanoutFiltersCurrentRightsPreferencesAndLiveSessions(): void
    {
        $id = T3EventTestEnv::emit();
        RestApiEnv::rows(T3EventTestEnv::$registry,"UPDATE refresh_sessions SET revoked_at=now() WHERE subject=$1 AND tenant_id=$2",
            [T3EventTestEnv::$registrySubject,T3EventTestEnv::$tenantId]);
        $stats = T3EventTestEnv::fanout()->run();
        self::assertSame(1,$stats['recipients']);
        $inbox = (new PushService(T3EventTestEnv::$tenant))->inbox(T3EventTestEnv::$subject);
        self::assertSame('Betaling modtaget',$inbox['items'][0]['title']);
        self::assertSame('Faktura 101 på 612,50 er betalt af Private Customer.',$inbox['items'][0]['message']);
        self::assertSame('31',$this->rows('SELECT ref_id FROM notifications')[0]['ref_id']);
        self::assertSame(0,T3EventTestEnv::fanout()->run()['fanned_out']);
        self::assertCount(1,$this->rows('SELECT * FROM notification_recipients'));
        $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2',[str_repeat('0',16),T3EventTestEnv::$account['id']]);
        T3EventTestEnv::emit();
        self::assertSame(0,T3EventTestEnv::fanout()->run()['recipients']);
        $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2',[str_repeat('1',16),T3EventTestEnv::$account['id']]);
        $this->rows('UPDATE brugere SET status=false WHERE id=$1',[T3EventTestEnv::$account['id']]);
        T3EventTestEnv::emit();
        self::assertSame(0,T3EventTestEnv::fanout()->run()['recipients']);
        $this->rows('UPDATE brugere SET status=true WHERE id=$1',[T3EventTestEnv::$account['id']]);
        $this->rows("INSERT INTO notification_preferences(subject,type,enabled) VALUES($1,'invoice_paid',false)",[T3EventTestEnv::$subject]);
        T3EventTestEnv::emit();
        self::assertSame(0,T3EventTestEnv::fanout()->run()['recipients']);
        self::assertCount(1,$this->rows('SELECT * FROM notifications'));
        self::assertNotNull($this->rows('SELECT fanned_out_at FROM mobile_events WHERE id=$1',[$id])[0]['fanned_out_at']);
    }

    public function testRegistryUsesCurrentMembershipAndAttachmentEventsOnlyReachTarget(): void
    {
        $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2',[str_repeat('0',16),T3EventTestEnv::$account['id']]);
        T3EventTestEnv::emit('low_stock',null,['varenr'=>'A1','name'=>'Private item','stock'=>'2','unit'=>'stk','min'=>'5']);
        self::assertSame(1,T3EventTestEnv::fanout()->run()['recipients']);
        self::assertSame(T3EventTestEnv::$registrySubject,$this->rows('SELECT subject FROM notification_recipients')[0]['subject']);
        RestApiEnv::rows(T3EventTestEnv::$registry,"UPDATE brugere SET rettigheder=',,,' WHERE id=$1",[T3EventTestEnv::$registryUser['id']]);
        T3EventTestEnv::emit('low_stock');
        self::assertSame(0,T3EventTestEnv::fanout()->run()['recipients']);
        T3EventTestEnv::emit('attachment_decided',T3EventTestEnv::$subject,['decision'=>'rejected','name'=>'Mette','description'=>'Bilag','reason'=>'Uklart']);
        self::assertSame(1,T3EventTestEnv::fanout()->run()['recipients']);
        T3EventTestEnv::emit('attachment_resubmitted',T3EventTestEnv::$subject,['name'=>'Lars','description'=>'Bilag']);
        self::assertSame(0,T3EventTestEnv::fanout()->run()['recipients']);
        $digits = str_repeat('0',16);
        $digits[2] = '2';
        $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2',[$digits,T3EventTestEnv::$account['id']]);
        T3EventTestEnv::emit('attachment_resubmitted',T3EventTestEnv::$subject);
        self::assertSame(0,T3EventTestEnv::fanout()->run()['recipients']);
        $digits[2] = '1';
        $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2',[$digits,T3EventTestEnv::$account['id']]);
        T3EventTestEnv::emit('attachment_resubmitted',T3EventTestEnv::$subject);
        self::assertSame(1,T3EventTestEnv::fanout()->run()['recipients']);
    }

    public function testOverlappingCronsAndKilledTransactionCreateOneInboxPerRecipient(): void
    {
        $id = T3EventTestEnv::emit();
        $this->pauseTrigger(1);
        $log = tempnam('/tmp','event-overlap-');
        $secondLog = $log . '.second';
        $first = T3EventTestEnv::cron($log);
        try {
            self::assertTrue($this->waiting(),'First cron is inside the event transaction');
            $second = T3EventTestEnv::cron($secondLog);
            self::assertSame(0,proc_close($second),file_get_contents($secondLog . '.err'));
            self::assertTrue(json_decode(trim(file_get_contents($secondLog)),true)['busy']);
            self::assertSame(0,proc_close($first),file_get_contents($log . '.err'));
            self::assertCount(1,$this->rows('SELECT * FROM notifications'));
            self::assertCount(2,$this->rows('SELECT * FROM notification_recipients'));
        } finally {
            $this->dropPauseTrigger();
            foreach ([$log,$log . '.err',$secondLog,$secondLog . '.err'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        $crashId = T3EventTestEnv::emit();
        $this->pauseTrigger(30);
        $log = tempnam('/tmp','event-crash-');
        $child = T3EventTestEnv::cron($log);
        try {
            self::assertTrue($this->waiting(),'Crash occurs after inbox insertion, before fanned_out_at');
            proc_terminate($child,9);
            proc_close($child);
            // Terminating the client disconnects its backend, releasing the transaction and locks.
            for ($i=0; $i<200 && $this->waiting(1); $i++) {
                usleep(10000);
            }
            self::assertCount(1,$this->rows('SELECT * FROM notifications'));
            self::assertNull($this->rows('SELECT fanned_out_at FROM mobile_events WHERE id=$1',[$crashId])[0]['fanned_out_at']);
        } finally {
            $this->dropPauseTrigger();
            unlink($log);
            unlink($log . '.err');
        }
        self::assertSame(1,T3EventTestEnv::fanout()->run()['fanned_out']);
        self::assertSame(0,T3EventTestEnv::fanout()->run()['fanned_out']);
        self::assertCount(2,$this->rows('SELECT * FROM notifications'));
        self::assertCount(4,$this->rows('SELECT * FROM notification_recipients'));
    }

    public function testSkipLockedClaimsAndEventRetentionAreBounded(): void
    {
        $lockedId = T3EventTestEnv::emit();
        $otherId = T3EventTestEnv::emit();
        $other = RestApiEnv::connect(RestApiEnv::testDb());
        RestApiEnv::rows($other,'BEGIN');
        RestApiEnv::rows($other,'SELECT id FROM mobile_events WHERE id=$1 FOR UPDATE',[$lockedId]);
        try {
            self::assertSame(1,T3EventTestEnv::fanout()->run()['fanned_out']);
            self::assertNull($this->rows('SELECT fanned_out_at FROM mobile_events WHERE id=$1',[$lockedId])[0]['fanned_out_at']);
            self::assertNotNull($this->rows('SELECT fanned_out_at FROM mobile_events WHERE id=$1',[$otherId])[0]['fanned_out_at']);
        } finally {
            RestApiEnv::rows($other,'ROLLBACK');
            pg_close($other);
        }
        self::assertSame(1,T3EventTestEnv::fanout()->run()['fanned_out']);
        for ($i=0; $i<102; $i++) {
            T3EventTestEnv::emit();
        }
        $this->rows("UPDATE mobile_events SET created_at=now()-interval '13 months'");
        self::assertSame(100,T3EventTestEnv::fanout()->run()['pruned']);
        self::assertCount(4,$this->rows('SELECT * FROM mobile_events'));
        self::assertSame(4,T3EventTestEnv::fanout()->run()['pruned']);
    }

    public function testAgedReferencesAndCleanupFailureStillDeliverOnlyGenericPush(): void
    {
        $reminder = $this->rows("INSERT INTO reminders(target_subject,message,created_by,created_at)
            VALUES($1,'Private reminder',$1,now()-interval '13 months') RETURNING id",[T3EventTestEnv::$subject])[0]['id'];
        $this->rows('INSERT INTO pool_files(filename,reminder_id) VALUES($1,$2)',[bin2hex(random_bytes(8)) . '.pdf',$reminder]);
        $old = (new PushService(T3EventTestEnv::$tenant))->create([T3EventTestEnv::$subject],T3EventTestEnv::$subject,'reminder','Old','Private',(int)$reminder);
        $this->rows("UPDATE notifications SET created_at=now()-interval '13 months' WHERE id=$1",[$old['notification_id']]);
        $eventId = T3EventTestEnv::emit();
        $this->rows('UPDATE mobile_events SET notification_id=$1,fanned_out_at=now() WHERE id=$2',[$old['notification_id'],$eventId]);
        $identity = Subject::parse(T3EventTestEnv::$subject);
        $this->rows("INSERT INTO push_tokens(user_id,subject,principal,device_id,db_name,token,platform)
            VALUES($1,$2,'account','event-phone',$3,$4,'ios')",[$identity['user_id'],T3EventTestEnv::$subject,RestApiEnv::testDb(),
                'ExpoPushToken[' . bin2hex(random_bytes(16)) . ']']);
        (new PushService(T3EventTestEnv::$tenant))->create([T3EventTestEnv::$subject],T3EventTestEnv::$subject,'invoice_paid','Private title','Private money');
        [$server,$url,$log] = $this->stub();
        try {
            $worker = new PushDispatchWorker(T3EventTestEnv::$tenant,T3EventTestEnv::$tenantId,Subject::installation(),$url . '/send',$url . '/receipts');
            self::assertSame(1,$worker->run()['ticketed']);
            self::assertCount(1,$this->rows('SELECT * FROM reminders WHERE id=$1',[$reminder]));
            self::assertNull($this->rows('SELECT notification_id FROM mobile_events WHERE id=$1',[$eventId])[0]['notification_id']);
            self::assertCount(1,$this->rows('SELECT * FROM notifications'));
            $this->rows("UPDATE push_dispatch SET next_attempt_at=now()-interval '1 second'");
            self::assertSame(1,$worker->run()['delivered']);
            // An intentional DB trigger failure tests the real cleanup rollback and continuation.
            $this->rows("CREATE FUNCTION event_test_cleanup_failure() RETURNS trigger LANGUAGE plpgsql AS
                $$ BEGIN RAISE EXCEPTION 'Injected cleanup refusal'; END $$");
            $this->rows('CREATE TRIGGER event_test_cleanup_failure BEFORE DELETE ON notifications FOR EACH ROW EXECUTE FUNCTION event_test_cleanup_failure()');
            (new PushService(T3EventTestEnv::$tenant))->create([T3EventTestEnv::$subject],T3EventTestEnv::$subject,'reminder','Old','Private');
            $this->rows("UPDATE notifications SET created_at=now()-interval '13 months' WHERE title='Old'");
            $fresh = (new PushService(T3EventTestEnv::$tenant))->create([T3EventTestEnv::$subject],T3EventTestEnv::$subject,'reminder','Private','Private');
            $code = 'require ' . var_export(dirname(__DIR__,2) . '/restapi/core/BaseEndpoint.php',true)
                . ';require ' . var_export(__DIR__ . '/support/RestApiEnv.php',true)
                . ';require ' . var_export(dirname(__DIR__,2) . '/restapi/services/PushDispatchWorker.php',true)
                . ';$c=RestApiEnv::connect(' . var_export(RestApiEnv::testDb(),true) . ');'
                . 'echo json_encode((new PushDispatchWorker($c,' . T3EventTestEnv::$tenantId . ','
                . var_export(Subject::installation(),true) . ',' . var_export($url . '/send',true) . ','
                . var_export($url . '/receipts',true) . '))->run());';
            $child = proc_open([PHP_BINARY,'-r',$code],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            fclose($pipes[0]);
            $result = stream_get_contents($pipes[1]);
            $diagnostics = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0,proc_close($child),$diagnostics);
            self::assertSame(2,json_decode($result,true)['ticketed']);
            self::assertStringContainsString('Injected cleanup refusal',$diagnostics);
            self::assertStringContainsString('push_cleanup_failed',$diagnostics);
            self::assertSame('ticketed',$this->rows('SELECT d.state FROM push_dispatch d JOIN notification_recipients r ON r.id=d.recipient_id WHERE r.notification_id=$1',[$fresh['notification_id']])[0]['state']);
            $requests = array_map(static fn(string $line): array=>json_decode($line,true),file($log,FILE_IGNORE_NEW_LINES));
            foreach ($requests as $request) {
                if ($request['path'] !== '/send') {
                    continue;
                }
                foreach ($request['body'] as $push) {
                    self::assertSame('Ny besked fra Saldi',$push['title']);
                    self::assertSame($push['data']['type'] === 'reminder' ? 'Åbn appen for at se påmindelsen' : 'Åbn appen for at se beskeden',$push['body']);
                    self::assertSame(['notification_id','tenant_id','installation','type'],array_keys($push['data']));
                }
            }
            self::assertStringNotContainsString('Private',file_get_contents($log));
        } finally {
            $this->rows('DROP TRIGGER IF EXISTS event_test_cleanup_failure ON notifications');
            $this->rows('DROP FUNCTION IF EXISTS event_test_cleanup_failure()');
            proc_terminate($server);
            proc_close($server);
            unlink($log);
            unlink($log . '.server');
        }
    }

    public function testMalformedEventRetriesWithoutBlockingOtherEventsAndCronIsCliOnly(): void
    {
        $bad = T3EventTestEnv::emit('unknown');
        $good = T3EventTestEnv::emit();
        $stats = T3EventTestEnv::fanout()->run();
        self::assertSame(1,$stats['failed']);
        self::assertSame(1,$stats['fanned_out']);
        self::assertSame('fanout_failed',$this->rows('SELECT last_error FROM mobile_events WHERE id=$1',[$bad])[0]['last_error']);
        self::assertSame(403,RestApiEnv::http('GET','/cron/mobile_events.php')['status']);
    }

    public function testAllDanishEventBodies(): void
    {
        foreach ([
            ['attachment_decided',['decision'=>'approved','name'=>'Mette','description'=>'Frokost','amount'=>'1234.50','kontonr'=>2750,'account_name'=>'Repræsentation'],
                ['Bilag godkendt','Mette godkendte "Frokost" på 1.234,50 — konto 2750 Repræsentation.']],
            ['attachment_decided',['decision'=>'rejected','name'=>'Mette','description'=>'Frokost','reason'=>'Uklart'],['Bogholder spørger','Mette afviste "Frokost": Uklart']],
            ['attachment_resubmitted',['name'=>'Lars','description'=>'Frokost'],['Bilag sendt igen','Lars har rettet "Frokost" og sendt det igen.']],
            ['invoice_paid',['faktnr'=>'101','amount'=>'100.00','customer_name'=>'Hansen','via'=>'mobilepay'],['Betaling modtaget','Faktura 101 på 100,00 er betalt af Hansen via MobilePay.']],
            ['invoice_overdue',['faktnr'=>'101','amount'=>'100.00','customer_name'=>'Hansen'],['Faktura forfalden','Faktura 101 til Hansen på 100,00 er forfalden.']],
            ['invoice_overdue_digest',['count'=>11],['Forfaldne fakturaer','11 fakturaer er forfaldne i dag. Se dem under Penge.']],
            ['webshop_order',['ordrenr'=>'102','amount'=>'100.00','customer_name'=>'Hansen'],['Ny webshopordre','Ordre 102 fra Hansen på 100,00.']],
            ['low_stock',['varenr'=>'A1','name'=>'Skruer','stock'=>'2','unit'=>'stk','min'=>'5'],['Lager under minimum','A1 Skruer: 2 stk på lager, minimum 5.']],
        ] as [$type,$payload,$expected]) {
            self::assertSame($expected,MobileEventFanout::render($type,$payload));
        }
    }

    /** Pause the mark after notification insertion: no production hooks or altered services. */
    private function pauseTrigger(int $seconds): void
    {
        $this->rows('CREATE FUNCTION event_test_pause() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
            IF NEW.fanned_out_at IS NOT NULL AND OLD.fanned_out_at IS NULL THEN PERFORM pg_sleep(' . (int)$seconds . '); END IF;
            RETURN NEW; END $$');
        $this->rows('CREATE TRIGGER event_test_pause BEFORE UPDATE ON mobile_events FOR EACH ROW EXECUTE FUNCTION event_test_pause()');
    }
    private function dropPauseTrigger(): void
    {
        $this->rows('DROP TRIGGER IF EXISTS event_test_pause ON mobile_events');
        $this->rows('DROP FUNCTION IF EXISTS event_test_pause()');
    }
    private function waiting(int $attempts = 200): bool
    {
        for ($i=0; $i<$attempts; $i++) {
            $rows = RestApiEnv::rows(T3EventTestEnv::$registry,"SELECT pid FROM pg_stat_activity WHERE datname=$1 AND wait_event='PgSleep' AND query LIKE 'UPDATE mobile_events%'",[RestApiEnv::testDb()]);
            if ($rows !== []) {
                return true;
            }
            usleep(10000);
        }
        return false;
    }
    /** @return array{0:resource,1:string,2:string} */
    private function stub(): array
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket,false);
        fclose($socket);
        $log = tempnam('/tmp','event-expo-');
        $server = proc_open([PHP_BINARY,'-S',$address,__DIR__ . '/support/ExpoPushStub.php'],
            [0=>['pipe','r'],1=>['file',$log . '.server','w'],2=>['file',$log . '.server','w']],$pipes,null,
            array_merge(getenv(),['SALDI_EXPO_STUB_LOG'=>$log]));
        fclose($pipes[0]);
        for ($i=0; $i<100; $i++) {
            $ch = curl_init('http://' . $address);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT_MS=>100]);
            curl_exec($ch);
            $ready = curl_getinfo($ch,CURLINFO_RESPONSE_CODE) > 0;
            curl_close($ch);
            if ($ready) {
                break;
            }
            usleep(10000);
        }
        self::assertTrue($ready);
        return [$server,'http://' . $address,$log];
    }
}
