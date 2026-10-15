<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/BookingEventTest.php --- ver 5.0.0 --- 2026.10.06
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

require_once __DIR__ . '/support/T4BookingTestCase.php';

final class BookingEventTest extends T4BookingTestCase
{
    public function testOwnChangesHaveNoOwnInboxAndOtherUsersChangesFanOutOncePerVersion(): void
    {
        // A separate login linked to the same employee is an independent recipient.
        $item = $this->create();
        $selfSubject = Subject::build('account',(int)self::$users['self']['id']);
        $aliasSubject = Subject::build('account',(int)self::$users['alias']['id']);
        self::assertSame(1,T3EventTestEnv::fanout()->run()['recipients']);
        self::assertSame(0,T3EventTestEnv::fanout()->run()['fanned_out']);
        self::assertSame([], (new PushService(T3EventTestEnv::$tenant))->inbox($selfSubject)['items']);
        self::assertCount(1, (new PushService(T3EventTestEnv::$tenant))->inbox($aliasSubject)['items']);
        $this->rows('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$users['alias']['id']]);
        foreach ([['PATCH',['start'=>'10:00'],'Booking flyttet'],['PATCH',['note'=>'Revised'],'Booking ændret'],
            ['POST',['status'=>'udfoert'],'Booking ændret'],['POST',['status'=>'planlagt'],'Booking ændret'],
            ['POST',['status'=>'aflyst'],'Booking aflyst']] as [$method,$fields,$title]) {
            $body = ['client_id'=>MobileAuth::uuid()] + $fields;
            $path = 'index.php?id=' . $item['id'];
            if ($method === 'POST') {
                $path = 'status.php'; $body['id'] = $item['id'];
            }
            $result = $this->request($method,$path,$body,'leader',$item['etag']);
            self::assertSame(200,$result['status'],$result['body']);
            $retry = $this->request($method,$path,$body,'leader',$item['etag']);
            self::assertSame($result['json'],$retry['json']);
            $item = $result['json']['data'];
            $stats = T3EventTestEnv::fanout()->run();
            self::assertSame(1,$stats['recipients']);
            self::assertSame(0,$stats['failed']);
            self::assertSame(0,T3EventTestEnv::fanout()->run()['fanned_out']);
            $inbox = (new PushService(T3EventTestEnv::$tenant))->inbox($selfSubject)['items'];
            self::assertSame($title,$inbox[0]['title']);
            self::assertSame('booking_assigned',$inbox[0]['type']);
            $reference = $this->rows('SELECT ref_type,ref_id FROM notifications WHERE id=$1',[$inbox[0]['notification_id']])[0];
            self::assertSame('booking',$reference['ref_type']);
            self::assertSame($item['id'],(int)$reference['ref_id']);
        }
        self::assertCount(5,(new PushService(T3EventTestEnv::$tenant))->inbox($selfSubject)['items']);
        self::assertCount(6,$this->history($item['id']));
        $events = $this->rows("SELECT dedupe_key FROM mobile_events WHERE ref_type='booking' AND ref_id=$1 ORDER BY id",[$item['id']]);
        self::assertSame(array_map(static fn(int $version): string=>'booking_assigned:' . $item['id'] . ':' . $version,range(1,6)),array_column($events,'dedupe_key'));
    }

    public function testLinkedEmployeePreferenceFiltersBookingAndRejectsRegistryAndUnlinked(): void
    {
        $subject = Subject::build('account',(int)self::$users['self']['id']);
        $url = '/restapi/endpoints/v1/notifications/preferences.php';
        $headers = ['Authorization: Bearer ' . self::$tokens['self']];
        $get = RestApiEnv::http('GET',$url,null,$headers);
        self::assertSame(200,$get['status'],$get['body']);
        self::assertContains(['type'=>'booking_assigned','enabled'=>true],$get['json']['data']['items']);
        $body = ['client_id'=>MobileAuth::uuid(),'items'=>[['type'=>'booking_assigned','enabled'=>false]]];
        $put = RestApiEnv::http('PUT',$url,$body,$headers);
        self::assertSame(200,$put['status'],$put['body']);
        $this->rows('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$users['alias']['id']]);
        $item = $this->create(['employee_ansat_id'=>self::$employees['self']],'leader');
        self::assertSame(0,T3EventTestEnv::fanout()->run()['recipients']);
        self::assertSame([], (new PushService(T3EventTestEnv::$tenant))->inbox($subject)['items']);
        $body['client_id'] = MobileAuth::uuid(); $body['items'][0]['enabled'] = true;
        self::assertSame(200,RestApiEnv::http('PUT',$url,$body,$headers)['status']);
        $result = $this->request('PATCH','index.php?id=' . $item['id'],['client_id'=>MobileAuth::uuid(),'start'=>'09:00'],'admin',$item['etag']);
        self::assertSame(200,$result['status'],$result['body']);
        self::assertSame(1,T3EventTestEnv::fanout()->run()['recipients']);
        foreach ([['Authorization: Bearer ' . T3EventTestEnv::token(true)],$headers] as $index=>$identity) {
            if ($index === 1) {
                $this->rows('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$users['self']['id']]);
            }
            $result = RestApiEnv::http('GET',$url,null,$identity);
            self::assertSame(200,$result['status'],$result['body']);
            self::assertNotContains('booking_assigned',array_column($result['json']['data']['items'],'type'));
            $body['client_id'] = MobileAuth::uuid();
            self::assertSame(403,RestApiEnv::http('PUT',$url,$body,$identity)['status']);
        }
    }

    public function testRecipientSnapshotDoesNotBroadcastToNewLinksAndClosureDisableAndRevocationAreChecked(): void
    {
        $this->rows('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$users['alias']['id']]);
        $item = $this->create(['employee_ansat_id'=>self::$employees['self']],'leader');
        $this->rows('UPDATE brugere SET ansat_id=$1 WHERE id=$2',[self::$employees['self'],self::$users['alias']['id']]);
        self::assertSame(1,T3EventTestEnv::fanout()->run()['recipients']);
        foreach (['closed','disabled','revoked','relinked'] as $mode) {
            $result = $this->request('PATCH','index.php?id=' . $item['id'],['client_id'=>MobileAuth::uuid(),'note'=>$mode],'leader',$item['etag']);
            self::assertSame(200,$result['status'],$result['body']);
            $item = $result['json']['data'];
            if ($mode === 'closed') {
                $this->rows("UPDATE ansatte SET lukket='on' WHERE id=$1",[self::$employees['self']]);
            } elseif ($mode === 'disabled') {
                $this->rows('UPDATE brugere SET status=false WHERE id=ANY($1::int[])',['{' . self::$users['self']['id'] . ',' . self::$users['alias']['id'] . '}']);
            } elseif ($mode === 'revoked') {
                RestApiEnv::rows(T3EventTestEnv::$registry,'UPDATE refresh_sessions SET revoked_at=now() WHERE subject=ANY($1::text[]) AND tenant_id=$2',
                    ['{' . Subject::build('account',(int)self::$users['self']['id']) . ',' . Subject::build('account',(int)self::$users['alias']['id']) . '}',T3EventTestEnv::$tenantId]);
            } else {
                $this->rows('UPDATE brugere SET ansat_id=$1 WHERE id=ANY($2::int[])',[self::$employees['other'],'{' . self::$users['self']['id'] . ',' . self::$users['alias']['id'] . '}']);
            }
            self::assertSame(0,T3EventTestEnv::fanout()->run()['recipients'],$mode);
            $this->rows("UPDATE ansatte SET lukket='' WHERE id=$1",[self::$employees['self']]);
            $this->rows('UPDATE brugere SET status=true WHERE id=ANY($1::int[])',['{' . self::$users['self']['id'] . ',' . self::$users['alias']['id'] . '}']);
            T3EventTestEnv::session(Subject::build('account',(int)self::$users['self']['id']));
            T3EventTestEnv::session(Subject::build('account',(int)self::$users['alias']['id']));
        }
    }

    public function testOwnWriteWithNoOtherLoginEmitsNothingAndReassignmentTargetsOnlyNewEmployee(): void
    {
        $this->rows('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$users['alias']['id']]);
        $item = $this->create();
        self::assertSame([],$this->rows("SELECT * FROM mobile_events WHERE ref_type='booking' AND ref_id=$1",[$item['id']]));
        self::assertSame(0,T3EventTestEnv::fanout()->run()['recipients']);
        $moved = $this->request('PATCH','index.php?id=' . $item['id'],
            ['client_id'=>MobileAuth::uuid(),'employee_ansat_id'=>self::$employees['other']],'admin',$item['etag']);
        self::assertSame(200,$moved['status'],$moved['body']);
        self::assertSame(1,T3EventTestEnv::fanout()->run()['recipients']);
        $push = new PushService(T3EventTestEnv::$tenant);
        self::assertSame([],$push->inbox(Subject::build('account',(int)self::$users['self']['id']))['items']);
        self::assertCount(1,$push->inbox(Subject::build('account',(int)self::$users['other']['id']))['items']);
    }

    public function testBookingPushContainsGenericTextAndOnlyRoutingIds(): void
    {
        require_once __DIR__ . '/../../restapi/services/PushDispatchWorker.php';
        $subject = Subject::build('account',(int)self::$users['self']['id']);
        $this->rows('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$users['alias']['id']]);
        $this->rows("INSERT INTO push_tokens(user_id,subject,principal,device_id,db_name,token,platform)
            VALUES($1,$2,'account','booking-phone',$3,$4,'ios')",[self::$users['self']['id'],$subject,RestApiEnv::testDb(),
                'ExpoPushToken[' . bin2hex(random_bytes(16)) . ']']);
        $this->create(['employee_ansat_id'=>self::$employees['self']],'leader');
        self::assertSame(1,T3EventTestEnv::fanout()->run()['recipients']);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket,false);
        fclose($socket);
        $log = tempnam('/tmp','booking-expo-');
        $server = proc_open([PHP_BINARY,'-S',$address,__DIR__ . '/support/ExpoPushStub.php'],
            [0=>['pipe','r'],1=>['file',$log . '.server','w'],2=>['file',$log . '.server','w']],$pipes,null,
            array_merge(getenv(),['SALDI_EXPO_STUB_LOG'=>$log]));
        fclose($pipes[0]);
        try {
            $ready = false;
            for ($i=0;$i<100;$i++) {
                $handle = curl_init('http://' . $address);
                curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT_MS=>100]);
                curl_exec($handle);
                $ready = curl_getinfo($handle,CURLINFO_RESPONSE_CODE)>0;
                curl_close($handle);
                if ($ready) {
                    break;
                }
                usleep(10000);
            }
            self::assertTrue($ready);
            $worker = new PushDispatchWorker(T3EventTestEnv::$tenant,T3EventTestEnv::$tenantId,Subject::installation(),
                'http://' . $address . '/send','http://' . $address . '/receipts');
            self::assertSame(1,$worker->run()['ticketed']);
            $requests = array_map(static fn(string $line): array=>json_decode($line,true),file($log,FILE_IGNORE_NEW_LINES));
            $push = $requests[0]['body'][0];
            self::assertSame('Ny besked fra Saldi',$push['title']);
            self::assertSame('Åbn appen for at se beskeden',$push['body']);
            self::assertSame(['notification_id','tenant_id','installation','type'],array_keys($push['data']));
            self::assertSame('booking_assigned',$push['data']['type']);
            self::assertStringNotContainsString('Hansen',file_get_contents($log));
            self::assertStringNotContainsString('Montage',file_get_contents($log));
            self::assertStringNotContainsString($this->date,file_get_contents($log));
        } finally {
            proc_terminate($server);
            proc_close($server);
            unlink($log);
            unlink($log . '.server');
        }
    }

    public function testDanishCopyForCreateMoveCancelAndOtherChanges(): void
    {
        foreach ([['created','planlagt','Ny booking',"Montage hos Hansen's ApS 2026-10-25 kl. 02:30."],
            ['moved','planlagt','Booking flyttet',"Montage hos Hansen's ApS er flyttet til 2026-10-25 kl. 02:30."],
            ['status','aflyst','Booking aflyst',"Montage hos Hansen's ApS 2026-10-25 kl. 02:30 er aflyst."],
            ['status','udfoert','Booking ændret',"Montage hos Hansen's ApS 2026-10-25 kl. 02:30 er ændret."]] as [$event,$status,$title,$message]) {
            self::assertSame([$title,$message],MobileEventFanout::render('booking_assigned',
                ['event'=>$event,'status'=>$status,'service'=>'Montage','customer_name'=>"Hansen's ApS",'date'=>'2026-10-25','start'=>'02:30']));
        }
    }
}
