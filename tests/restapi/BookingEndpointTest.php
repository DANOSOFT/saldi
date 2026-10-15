<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/BookingEndpointTest.php --- ver 5.0.0 --- 2026.10.06
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

final class BookingEndpointTest extends T4BookingTestCase
{
    public function testTeamDaySelfCreateSettingsAndChooser(): void
    {
        $own = $this->create();
        $other = $this->create(['employee_ansat_id'=>self::$employees['other'],'start'=>'10:00'],'leader');
        $this->create(['start'=>'12:00'],'other');
        foreach (['booking_day_start'=>'06:30','booking_day_end'=>'18:30','booking_slot_minutes'=>'30'] as $name=>$value) {
            $this->rows("INSERT INTO settings(var_grp,var_name,var_value,user_id) VALUES('mobile_marken',$1,$2,0)",[$name,$value]);
        }
        try {
            $response = $this->request('GET','index.php?date=' . $this->date);
            self::assertSame(200,$response['status'],$response['body']);
            $day = $response['json']['data'];
            self::assertSame(['06:30','18:30',30],[$day['day_start'],$day['day_end'],$day['slot_minutes']]);
            self::assertSame(['planned'=>3,'done'=>0,'cancelled'=>0],$day['summary']);
            self::assertCount(3,$day['items']);
            self::assertTrue($day['items'][0]['can_edit']);
            self::assertFalse($day['items'][1]['can_edit']);
            self::assertFalse($day['items'][1]['employee']['is_me']);
            self::assertSame(self::$employees['self'],$own['employee']['ansat_id']);
            self::assertSame("Hansen's ApS",$own['customer']['name']);
            self::assertSame('09:00',$own['end']);
            self::assertSame(['id','date','start','end','duration_minutes','employee','customer','service','case','note','status','can_edit','etag'],array_keys($day['items'][0]));
            $chooser = $this->request('GET','employees.php');
            self::assertSame(200,$chooser['status'],$chooser['body']);
            self::assertContains(self::$employees['other'],array_column($chooser['json']['data']['items'],'ansat_id'));
            self::assertCount(1,array_filter($chooser['json']['data']['items'],static fn(array $item): bool=>$item['is_me']));
        } finally {
            $this->rows("DELETE FROM settings WHERE var_grp='mobile_marken' AND var_name LIKE 'booking_%'");
        }
        self::assertSame('created',$this->history($own['id'])[0]['event']);
    }

    public function testInstalledBookingCapability(): void
    {
        $me = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,['Authorization: Bearer ' . self::$tokens['self']]);
        self::assertSame(200,$me['status'],$me['body']);
        self::assertSame('v2',$me['json']['data']['capabilities']['bookings']);
    }

    public function testAssigneeAdminLeaderAndForbiddenCrossEmployeeActions(): void
    {
        $own = $this->create();
        $response = $this->request('POST','index.php',$this->body(['employee_ansat_id'=>self::$employees['other']]));
        self::assertSame(403,$response['status'],$response['body']);
        foreach (['PATCH','POST'] as $method) {
            $path = $method === 'PATCH' ? 'index.php?id=' . $own['id'] : 'status.php';
            $body = $method === 'PATCH' ? ['client_id'=>MobileAuth::uuid(),'start'=>'09:00'] : ['client_id'=>MobileAuth::uuid(),'id'=>$own['id'],'status'=>'udfoert'];
            $result = $this->request($method,$path,$body,'other',$own['etag']);
            self::assertSame(403,$result['status'],$result['body']);
        }
        $reassign = $this->request('PATCH','index.php?id=' . $own['id'],
            ['client_id'=>MobileAuth::uuid(),'employee_ansat_id'=>self::$employees['other']],'self',$own['etag']);
        self::assertSame(403,$reassign['status'],$reassign['body']);
        foreach (['leader','admin','self'] as $role) {
            $result = $this->request('PATCH','index.php?id=' . $own['id'],['client_id'=>MobileAuth::uuid(),'note'=>$role],$role,$own['etag']);
            self::assertSame(200,$result['status'],$result['body']);
            $own = $result['json']['data'];
        }
        self::assertCount(4,$this->history($own['id']));
        $adminCreated = $this->create(['employee_ansat_id'=>self::$employees['other']],'admin');
        self::assertSame(self::$employees['other'],$adminCreated['employee']['ansat_id']);
        self::assertSame('planlagt',$own['status']);
        $digits = str_repeat('0',16); $digits[1] = '2';
        $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2',[$digits,self::$users['admin']['id']]);
        $result = $this->request('PATCH','index.php?id=' . $own['id'],['client_id'=>MobileAuth::uuid(),'note'=>'denied'],'admin',$own['etag']);
        self::assertSame(403,$result['status'],$result['body']);
        $this->rows("UPDATE grupper SET box2='10000000' WHERE id=$1",[self::$groups['leader']]);
        try {
            self::assertSame(403,$this->request('POST','index.php',$this->body(['employee_ansat_id'=>self::$employees['self']]),'leader')['status']);
        } finally {
            $this->rows("UPDATE grupper SET box2='10000010' WHERE id=$1",[self::$groups['leader']]);
        }
    }

    public function testStatusHistoryStaleEtagsMissingMatchAndIdempotentRetries(): void
    {
        $body = $this->body();
        $first = $this->request('POST','index.php',$body);
        self::assertSame(201,$first['status'],$first['body']);
        $own = $first['json']['data'];
        $retry = $this->request('POST','index.php',$body);
        self::assertSame(201,$retry['status'],$retry['body']);
        self::assertSame($first['json'],$retry['json']);
        self::assertStringContainsString('Idempotent-Replayed: true',$retry['headers']);
        $body['service'] = 'Changed';
        $reuse = $this->request('POST','index.php',$body);
        self::assertSame(409,$reuse['status'],$reuse['body']);
        self::assertSame('client_id_reused',$reuse['json']['data']['details']['reason']);
        $move = ['client_id'=>MobileAuth::uuid(),'start'=>'09:00'];
        $missing = $this->request('PATCH','index.php?id=' . $own['id'],$move);
        self::assertSame(422,$missing['status'],$missing['body']);
        self::assertSame('if_match_required',$missing['json']['data']['details']['reason']);
        $moved = $this->request('PATCH','index.php?id=' . $own['id'],$move,'self',$own['etag']);
        self::assertSame(200,$moved['status'],$moved['body']);
        self::assertSame($moved['json'],$this->request('PATCH','index.php?id=' . $own['id'],$move,'self',$own['etag'])['json']);
        $stale = $this->request('POST','status.php',['client_id'=>MobileAuth::uuid(),'id'=>$own['id'],'status'=>'udfoert'],'self',$own['etag']);
        self::assertSame(412,$stale['status'],$stale['body']);
        self::assertSame('precondition_failed',$stale['json']['data']['error']);
        $own = $moved['json']['data'];
        foreach (['udfoert','planlagt','aflyst'] as $status) {
            $body = ['client_id'=>MobileAuth::uuid(),'id'=>$own['id'],'status'=>$status];
            $result = $this->request('POST','status.php',$body,'self',$own['etag']);
            self::assertSame(200,$result['status'],$result['body']);
            $retry = $this->request('POST','status.php',$body,'self',$own['etag']);
            self::assertSame($result['json'],$retry['json']);
            self::assertStringContainsString('Idempotent-Replayed: true',$retry['headers']);
            $own = $result['json']['data'];
            $done = $this->rows('SELECT done_at FROM mobile_bookings WHERE id=$1',[$own['id']])[0]['done_at'];
            self::assertSame($status==='udfoert',$done!==null);
        }
        $history = $this->history($own['id']);
        self::assertSame(['created','moved','status','status','status'],array_column($history,'event'));
        self::assertNull($history[0]['before_json']);
        for ($i=1;$i<count($history);$i++) {
            self::assertSame(json_decode($history[$i-1]['after_json'],true),json_decode($history[$i]['before_json'],true));
        }
    }

    public function testConcurrentMovesWithTheSameEtagCommitOnlyOneHistoryRow(): void
    {
        $item = $this->create();
        $this->rows('BEGIN');
        $this->rows('SELECT id FROM mobile_bookings WHERE id=$1 FOR UPDATE',[$item['id']]);
        $multi = curl_multi_init();
        $handles = [];
        foreach (['09:00','10:00'] as $start) {
            $handle = curl_init(RestApiEnv::baseUrl() . '/restapi/endpoints/v1/bookings/index.php?id=' . $item['id']);
            curl_setopt_array($handle,[CURLOPT_CUSTOMREQUEST=>'PATCH',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer ' . self::$tokens['leader'],'If-Match: ' . $item['etag']],
                CURLOPT_POSTFIELDS=>json_encode(['client_id'=>MobileAuth::uuid(),'start'=>$start],JSON_THROW_ON_ERROR)]);
            curl_multi_add_handle($multi,$handle);
            $handles[] = $handle;
        }
        try {
            $waiting = false;
            for ($i=0;$i<500;$i++) {
                curl_multi_exec($multi,$running);
                $this->rows('SELECT pg_stat_clear_snapshot()');
                $locks = $this->rows("SELECT count(*) AS n FROM pg_stat_activity WHERE datname=current_database()
                    AND pid<>pg_backend_pid() AND wait_event_type='Lock' AND query LIKE '%mobile_bookings%FOR UPDATE%'");
                if ((int)$locks[0]['n'] === 2) {
                    $waiting = true;
                    break;
                }
                usleep(10000);
            }
            self::assertTrue($waiting,'Both HTTP requests must reach the held booking row lock');
        } finally {
            $this->rows('COMMIT');
            do {
                curl_multi_exec($multi,$running);
                if ($running) {
                    curl_multi_select($multi,0.1);
                }
            } while ($running);
            $statuses = array_map(static fn($handle): int=>(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE),$handles);
            foreach ($handles as $handle) {
                curl_multi_remove_handle($multi,$handle);
                curl_close($handle);
            }
            curl_multi_close($multi);
        }
        sort($statuses);
        self::assertSame([200,412],$statuses);
        self::assertCount(2,$this->history($item['id']));
        self::assertSame('2',$this->rows('SELECT version FROM mobile_bookings WHERE id=$1',[$item['id']])[0]['version']);
    }

    public function testOverlapIsWarningAndCancelledAndAdjacentBookingsAreIgnored(): void
    {
        $first = $this->create();
        $overlap = $this->create(['start'=>'08:30']);
        self::assertSame([['code'=>'overlap','booking_ids'=>[$first['id']]]],$overlap['warnings']);
        $adjacent = $this->create(['start'=>'09:30']);
        self::assertSame([],$adjacent['warnings']);
        $cancel = $this->request('POST','status.php',['client_id'=>MobileAuth::uuid(),'id'=>$first['id'],'status'=>'aflyst'],'self',$first['etag']);
        self::assertSame(200,$cancel['status'],$cancel['body']);
        self::assertSame([],$this->create(['start'=>'08:00','duration_minutes'=>30])['warnings']);
    }

    public function testDstDayKeepsWallClockTimesAcrossDatabaseTimezones(): void
    {
        $item = $this->create(['date'=>'2026-10-25','start'=>'02:30','duration_minutes'=>120]);
        self::assertSame('04:30',$item['end']);
        foreach (['UTC','Europe/Copenhagen','America/New_York'] as $zone) {
            $this->rows('SET TIME ZONE ' . "'" . $zone . "'");
            $day = $this->service()->day('2026-10-25');
            self::assertSame('02:30',$day['items'][0]['start']);
            self::assertSame('04:30',$day['items'][0]['end']);
        }
        $this->rows("SET TIME ZONE 'UTC'");
        $moved = $this->request('PATCH','index.php?id=' . $item['id'],['client_id'=>MobileAuth::uuid(),'start'=>'03:00'],'self',$item['etag']);
        self::assertSame(200,$moved['status'],$moved['body']);
        self::assertSame('05:00',$moved['json']['data']['end']);
    }

    public function testInvalidInputNeverWritesAndMissingRowsReturn404(): void
    {
        foreach ([['date'=>'2026-02-30'],['start'=>'24:00'],['start'=>'23:00','duration_minutes'=>120],['duration_minutes'=>4],
            ['duration_minutes'=>'60'],['duration_minutes'=>721],['service'=>''],['customer'=>[]],['customer'=>['name'=>'']],
            ['customer'=>['konto_id'=>99999999]],['case_id'=>99999999],['employee_ansat_id'=>99999999],
            ['status'=>'udfoert'],['note'=>false],['note'=>"bad\0value"]] as $override) {
            $result = $this->request('POST','index.php',$this->body($override),'admin');
            self::assertSame(422,$result['status'],$result['body']);
            self::assertSame('booking_invalid',$result['json']['data']['details']['reason']);
        }
        $result = $this->request('POST','index.php',array_replace($this->body(),['client_id'=>'bad']));
        self::assertSame(422,$result['status'],$result['body']);
        self::assertSame([], $this->rows('SELECT * FROM mobile_bookings WHERE booking_date=$1',[$this->date]));
        self::assertSame(404,$this->request('PATCH','index.php?id=99999999',['client_id'=>MobileAuth::uuid(),'start'=>'09:00'],'self','"missing"')['status']);
        self::assertSame(422,$this->request('GET','index.php?date=2026-02-30')['status']);
    }

    public function testCustomerAndCaseReferencesHydrateAndPartialEditsKeepFields(): void
    {
        $customer = (int)$this->rows("INSERT INTO adresser(art,firmanavn,addr1,postnr,bynavn,tlf) VALUES('D','Customer','Road 1','8000','Aarhus','1234') RETURNING id")[0]['id'];
        $case = (int)$this->rows("INSERT INTO sager(sagsnr,beskrivelse) VALUES('B-42','Booking case') RETURNING id")[0]['id'];
        $item = $this->create(['customer'=>['konto_id'=>$customer],'case_id'=>$case]);
        self::assertSame('Customer',$item['customer']['name']);
        self::assertSame('Road 1 8000 Aarhus',$item['customer']['address']);
        self::assertSame(['case_id'=>$case,'sagsnr'=>'B-42'],$item['case']);
        $changed = $this->request('PATCH','index.php?id=' . $item['id'],['client_id'=>MobileAuth::uuid(),'customer'=>['phone'=>'5678'],'case_id'=>null],'self',$item['etag']);
        self::assertSame(200,$changed['status'],$changed['body']);
        self::assertSame('Customer',$changed['json']['data']['customer']['name']);
        self::assertSame('5678',$changed['json']['data']['customer']['phone']);
        self::assertNull($changed['json']['data']['case']);
    }

    public function testAnonymousUnlinkedClosedAndRegistryPrincipalsAreDenied(): void
    {
        foreach (['index.php','employees.php','status.php'] as $path) {
            $method = $path === 'status.php' ? 'POST' : 'GET';
            self::assertSame(401,$this->request($method,$path,null,'anonymous')['status']);
        }
        $this->rows('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$users['other']['id']]);
        foreach (['index.php','employees.php','status.php'] as $path) {
            $response = $this->request($path==='status.php'?'POST':'GET',$path,null,'other');
            self::assertSame(403,$response['status'],$response['body']);
            self::assertSame('no_employee_link',$response['json']['data']['details']['reason']);
        }
        $this->rows("UPDATE ansatte SET lukket='on' WHERE id=$1",[self::$employees['self']]);
        $closed = $this->request('GET','employees.php');
        self::assertSame(403,$closed['status'],$closed['body']);
        $registryToken = T3EventTestEnv::token(true);
        foreach (['index.php','employees.php','status.php'] as $path) {
            $response = RestApiEnv::http($path==='status.php'?'POST':'GET','/restapi/endpoints/v1/bookings/' . $path,null,['Authorization: Bearer ' . $registryToken]);
            self::assertSame(403,$response['status'],$response['body']);
            self::assertSame('no_employee_link',$response['json']['data']['details']['reason']);
        }
        self::assertSame(405,$this->request('DELETE','index.php',['client_id'=>MobileAuth::uuid()],'admin')['status']);
    }

    public function testHistoryFailureRollsBackBookingEventAndReplayRecord(): void
    {
        $item = $this->create();
        $body = ['client_id'=>MobileAuth::uuid(),'start'=>'09:00'];
        $this->rows("CREATE FUNCTION booking_test_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'injected history failure'; END $$");
        $this->rows('CREATE TRIGGER booking_test_fail BEFORE INSERT ON mobile_booking_history FOR EACH ROW EXECUTE FUNCTION booking_test_fail()');
        try {
            $failed = $this->request('PATCH','index.php?id=' . $item['id'],$body,'leader',$item['etag']);
            self::assertSame(500,$failed['status'],$failed['body']);
            self::assertSame('internal',$failed['json']['data']['error']);
            self::assertSame('08:00:00',$this->rows('SELECT start_time FROM mobile_bookings WHERE id=$1',[$item['id']])[0]['start_time']);
            self::assertCount(1,$this->history($item['id']));
            self::assertSame([],$this->rows('SELECT * FROM mobile_idempotency WHERE client_id=$1',[$body['client_id']]));
            self::assertSame([],$this->rows("SELECT * FROM mobile_events WHERE dedupe_key=$1",['booking_assigned:' . $item['id'] . ':2']));
        } finally {
            $this->rows('DROP TRIGGER booking_test_fail ON mobile_booking_history');
            $this->rows('DROP FUNCTION booking_test_fail()');
        }
        self::assertSame(200,$this->request('PATCH','index.php?id=' . $item['id'],$body,'leader',$item['etag'])['status']);
    }
}
