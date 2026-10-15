<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/TimeEntryEndpointTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Verify atomic bulk transfer when the web closes an entry's case.
// 20261001 CDX/LH Regress web allowances and historical time access without a current fiscal year.

use PHPUnit\Framework\Attributes\DataProvider;
require_once __DIR__ . '/support/T4TimeTestCase.php';

final class TimeEntryEndpointTest extends T4TimeTestCase
{
    public function testWebAllowancesSurviveTextOnlyPatchAndHoursRecalculation(): void
    {
        $entry = $this->create();
        // Model a browser save with shed, mentor and an already calculated mileage contribution.
        $this->rows("UPDATE loen SET skur='150|120',mentor='2',mentor_rate=110,korsel='40|3.50|20',sum=1910,datoer='web dates' WHERE id=$1", [$entry['id']]);
        $before = $this->rows('SELECT * FROM loen WHERE id=$1', [$entry['id']])[0];
        $response = $this->request('PATCH', 'time-entries/index.php?id=' . $entry['id'],
            ['client_id'=>MobileAuth::uuid(),'text'=>'Updated description'], 'self', $entry['etag']);
        self::assertSame(200, $response['status'], $response['body']);
        $after = $this->rows('SELECT * FROM loen WHERE id=$1', [$entry['id']])[0];
        $before['tekst'] = 'Updated description';
        self::assertSame($before, $after, 'Text-only edits preserve every payroll component and the saved total');
        $this->assertPrivate($response['json']);

        $hours = $this->request('PATCH', 'time-entries/index.php?id=' . $entry['id'],
            ['client_id'=>MobileAuth::uuid(),'hours'=>'8.00'], 'self', $response['json']['data']['etag']);
        self::assertSame(200, $hours['status'], $hours['body']);
        $after = $this->rows('SELECT * FROM loen WHERE id=$1', [$entry['id']])[0];
        self::assertSame('2000.000', $after['sum']);
        foreach (['skur','mentor','mentor_rate','korsel','datoer'] as $column) {
            self::assertSame($before[$column], $after[$column], $column);
        }
        $this->assertPrivate($hours['json']);
        $overtime = $this->request('PATCH', 'time-entries/index.php?id=' . $entry['id'],
            ['client_id'=>MobileAuth::uuid(),'overtime_50'=>'1.00','overtime_100'=>'0.50'], 'self', $hours['json']['data']['etag']);
        self::assertSame(200, $overtime['status'], $overtime['body']);
        self::assertSame('2120.500', $this->rows('SELECT sum FROM loen WHERE id=$1', [$entry['id']])[0]['sum']);
        $type = $this->request('PATCH', 'time-entries/index.php?id=' . $entry['id'],
            ['client_id'=>MobileAuth::uuid(),'hour_type'=>'hourTypes0'], 'self', $overtime['json']['data']['etag']);
        self::assertSame(200, $type['status'], $type['body']);
        $after = $this->rows('SELECT * FROM loen WHERE id=$1', [$entry['id']])[0];
        self::assertSame('2360.500', $after['sum']);
        foreach (['skur','mentor','mentor_rate','korsel','datoer'] as $column) {
            self::assertSame($before[$column], $after[$column], $column);
        }
        $this->assertPrivate($type['json']);
    }

    public function testHourTypeOnlyPatchPreservesStoredWebHoursPrecision(): void
    {
        $entry = $this->create();
        $this->rows("UPDATE loen SET timer='7.555',skur='150|0',sum=1509.9 WHERE id=$1", [$entry['id']]);
        $detail = $this->request('GET', 'time-entries/index.php?id=' . $entry['id']);
        self::assertSame(200, $detail['status'], $detail['body']);
        self::assertSame('7.56', $detail['json']['data']['hours']);
        $response = $this->request('PATCH', 'time-entries/index.php?id=' . $entry['id'],
            ['client_id'=>MobileAuth::uuid(),'hour_type'=>'hourTypes0'], 'self', $detail['json']['data']['etag']);
        self::assertSame(200, $response['status'], $response['body']);
        $row = $this->rows('SELECT timer,skur,sum FROM loen WHERE id=$1', [$entry['id']])[0];
        self::assertSame(['timer'=>'7.555','skur'=>'150|0','sum'=>'1736.550'], $row);
    }

    public function testHistoricalAccessWithoutFiscalYearCoveringToday(): void
    {
        $year = (int)(new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y') - 1;
        $date = $year . '-07-01';
        $entry = $this->create(['date'=>$date]);
        $years = $this->rows("SELECT * FROM grupper WHERE art='RA'");
        try {
            $this->rows("UPDATE grupper SET box1='1',box2=$1,box3='12',box4=$1 WHERE art='RA'", [(string)$year]);
            foreach (['cases/index.php','cases/tasks.php?case_id=1','time-entries/hour-types.php',
                'time-entries/index.php?date_from=' . $date . '&date_to=' . $date,
                'time-entries/index.php?id=' . $entry['id']] as $path) {
                $response = $this->request('GET', $path);
                self::assertSame(200, $response['status'], $path . ': ' . $response['body']);
                $this->assertPrivate($response['json']);
            }
            $patch = $this->request('PATCH', 'time-entries/index.php?id=' . $entry['id'],
                ['client_id'=>MobileAuth::uuid(),'hours'=>'8.00'], 'self', $entry['etag']);
            self::assertSame(200, $patch['status'], $patch['body']);
            $submit = $this->request('POST', 'time-entries/submit.php',
                ['client_id'=>MobileAuth::uuid(),'ids'=>[$entry['id']]], 'self', $patch['json']['data']['etag']);
            self::assertSame(200, $submit['status'], $submit['body']);
            $another = $this->create(['date'=>$date]);
            $delete = $this->request('DELETE', 'time-entries/index.php?id=' . $another['id'],
                ['client_id'=>MobileAuth::uuid()], 'self', $another['etag']);
            self::assertSame(200, $delete['status'], $delete['body']);
            $this->rows("DELETE FROM grupper WHERE art='RA'");
            foreach (['cases/index.php','cases/tasks.php?case_id=1','time-entries/hour-types.php',
                'time-entries/index.php','time-entries/index.php?id=' . $entry['id']] as $path) {
                $response = $this->request('GET', $path);
                self::assertSame(200, $response['status'], $response['body']);
            }
        } finally {
            $this->rows("DELETE FROM grupper WHERE art='RA'");
            foreach ($years as $row) {
                $this->rows('INSERT INTO grupper SELECT * FROM json_populate_record(NULL::grupper,$1::json)',
                    [json_encode($row, JSON_THROW_ON_ERROR)]);
            }
        }
    }

    /** Recursively assert that neither data nor validators disclose payroll field names. */
    private function assertPrivate($value): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key=>$child) {
            if (is_string($key)) {
                self::assertDoesNotMatchRegularExpression('/loen|sum|rate|wage/i', $key);
            }
            $this->assertPrivate($child);
        }
    }

    /** @return iterable<string,array{string,array,string}> */
    public static function goldenCases(): iterable
    {
        yield 'L1 normal'=>['L1-normal',[], ''];
        yield 'L2 overtime'=>['L2-overtime',['hours'=>'8.00','overtime_50'=>'1.00','overtime_100'=>'0.50'], ''];
        yield 'L3 hour type'=>['L3-hour-type',['hour_type'=>'hourTypes0'], ''];
        yield 'L3 trainee'=>['L3-trainee',[], 'trainee'];
        yield 'L5 empty rates'=>['L2-L5-fallback-empty',['hours'=>'8.00','overtime_50'=>'1.00','overtime_100'=>'0.50'], 'empty'];
        yield 'L5 missing rates'=>['L2-L5-fallback-missing',['hours'=>'8.00','overtime_50'=>'1.00','overtime_100'=>'0.50'], 'missing'];
    }

    #[DataProvider('goldenCases')]
    public function testGoldenPayrollRowsMatchCharacterizedBrowser(string $fixture, array $input, string $mode): void
    {
        if ($mode === 'trainee') {
            $this->rows("UPDATE ansatte SET trainee='on' WHERE id=1");
        } elseif ($mode === 'empty') {
            $this->rows("UPDATE grupper SET box8='' WHERE art='loen'");
        } elseif ($mode === 'missing') {
            $this->rows("DELETE FROM grupper WHERE art='loen'");
        }
        $groups = $this->rows('SELECT * FROM grupper ORDER BY id');
        $settings = $this->rows('SELECT * FROM settings ORDER BY id');
        $entry = $this->create($input);
        $this->assertGolden($fixture,'saved',$entry['id']);
        self::assertSame($groups,$this->rows('SELECT * FROM grupper ORDER BY id'));
        self::assertSame($settings,$this->rows('SELECT * FROM settings ORDER BY id'));
        self::assertSame('kladde',$entry['state']);
        self::assertTrue($entry['editable']);
    }

    private function assertGolden(string $fixture, string $stage, int $id): void
    {
        $expected = json_decode(file_get_contents(__DIR__ . '/fixtures/t4/golden/' . $fixture . '.json'),true,512,JSON_THROW_ON_ERROR)[$stage];
        $actual = $this->rows('SELECT * FROM loen WHERE id=$1',[$id])[0];
        foreach (['oprettet','afsluttet','godkendt','afvist','afregnet','udbetalt'] as $stamp) {
            if ((int)$actual[$stamp] > 0) {
                self::assertGreaterThan(time()-120,(int)$actual[$stamp]);
                self::assertLessThanOrEqual(time()+1,(int)$actual[$stamp]);
                $actual[$stamp] = '<stamp>';
            }
        }
        self::assertSame($expected,$actual);
    }

    public function testL4WebApprovalAndSettlementAcceptAppSeddel(): void
    {
        $entry = $this->create();
        $response = $this->request('POST','time-entries/submit.php',['client_id'=>MobileAuth::uuid(),'ids'=>[$entry['id']]],'self',$entry['etag']);
        self::assertSame(200,$response['status'],$response['body']);
        $this->assertGolden('L4-approved','submitted',$entry['id']);
        $this->page(['godkend'=>'Godkend','id'=>$entry['id'],'sag_id'=>1,'opg_id'=>1]);
        $this->assertGolden('L4-approved','approved',$entry['id']);
        $approved = $this->rows('SELECT * FROM loen WHERE id=$1',[$entry['id']])[0];
        // Exercise the actual settlement page, whose period is fourteen days from the supplied local start.
        $this->page(['afregn'=>'Afregn','afregnet'=>'14-07-2026','periode'=>(string)strtotime('2026-07-01'),'vis'=>'timer'], ['funktion'=>'loenafregning']);
        $row = $this->rows('SELECT * FROM loen WHERE id=$1',[$entry['id']])[0];
        self::assertSame('loenchar',$row['afregnet_af']);
        self::assertSame((new DateTimeImmutable('2026-07-14',new DateTimeZone('Europe/Copenhagen')))->getTimestamp(),(int)$row['afregnet']);
        self::assertSame('1350.000',$row['sum']);
        $approved['afregnet'] = (string)(new DateTimeImmutable('2026-07-14',new DateTimeZone('Europe/Copenhagen')))->getTimestamp();
        $approved['afregnet_af'] = 'loenchar';
        self::assertSame($approved,$row,'Settlement changes only the same two fields as the web seddel rule');
        $detail = $this->request('GET','time-entries/index.php?id=' . $entry['id']);
        self::assertSame('afregnet',$detail['json']['data']['state']);
        self::assertFalse($detail['json']['data']['editable']);
    }

    public function testReferenceDataFollowsLegacyActiveRulesAndContainsLabelsOnly(): void
    {
        foreach (['Ordrebekræftelse','Tilbud','Beregning','Afsluttet','Godkendt','Afmeldt','Drivgods'] as $i=>$status) {
            $this->rows('INSERT INTO sager(id,sagsnr,beskrivelse,status) VALUES($1,$2,$3,$4)',[10+$i,(string)(710+$i),$status,$status]);
        }
        $cases = $this->request('GET','cases/index.php');
        self::assertSame(200,$cases['status'],$cases['body']);
        self::assertSame(4,$cases['json']['data']['total']);
        self::assertSame(['Montage','Godkendt','Afmeldt','Drivgods'],array_column($cases['json']['data']['items'],'status'));
        $search = $this->request('GET','cases/index.php?q=Test&limit=1');
        self::assertSame('Test customer',$search['json']['data']['items'][0]['customer_name']);
        self::assertSame('Test address',$search['json']['data']['items'][0]['address']);
        $quoted = $this->request('GET',"cases/index.php?q=" . rawurlencode("%' OR true --"));
        self::assertSame(0,$quoted['json']['data']['total']);
        $tasks = $this->request('GET','cases/tasks.php?case_id=1');
        self::assertSame(1,$tasks['json']['data']['total']);
        self::assertSame(1,$tasks['json']['data']['items'][0]['task_id']);
        $types = $this->request('GET','time-entries/hour-types.php');
        self::assertSame(['items'=>[['code'=>'hourTypes0','label'=>'Special hours']]],$types['json']['data']);
    }

    public function testValidationRefusesInactiveCaseForeignTaskAndMalformedInputWithoutWrites(): void
    {
        foreach ([[['case_id'=>2],'case_inactive'],[['case_id'=>999],'case_inactive'],[['task_id'=>2],'task_required'],
            [['task_id'=>3],'task_required'],[['task_id'=>4],'task_required'],[['text'=>'   '],'text_required'],
            [['hours'=>'0'],'hours_invalid'],[['hours'=>'24.01'],'hours_invalid'],[['hours'=>7.5],'hours_invalid'],
            [['hours'=>[]],'hours_invalid'],[['overtime_50'=>'-1'],'hours_invalid'],[['date'=>'2026-02-30'],'date_missing'],
            [['hour_type'=>'unknown'],'hour_type_invalid'],[['employee_ansat_id'=>2],'unknown_field'],
            [['timer'=>['started_at'=>'2026-07-01T09:00:00','stopped_at'=>'2026-07-01T10:00:00Z']],'timer_invalid'],
            [['timer'=>['started_at'=>'2026-07-01T11:00:00Z','stopped_at'=>'2026-07-01T10:00:00Z']],'timer_invalid'],
            [['timer'=>['started_at'=>'0000-07-01T09:00:00Z','stopped_at'=>'2026-07-01T10:00:00Z']],'timer_invalid'],
            [['timer'=>['started_at'=>'2026-07-01T09:00:00+15:00','stopped_at'=>'2026-07-01T10:00:00+15:00']],'timer_invalid']] as [$changes,$reason]) {
            $response = $this->request('POST','time-entries/index.php',$this->body($changes));
            $this->assertError($response,422,'validation_failed',$reason);
            self::assertSame([],$this->rows('SELECT * FROM loen'));
            self::assertSame([],$this->rows('SELECT * FROM mobile_time_entries'));
            self::assertSame([],$this->rows('SELECT * FROM mobile_idempotency'));
        }
    }

    public function testEditDeleteReplayAndEtagProtection(): void
    {
        $body = $this->body(['timer'=>['started_at'=>'2026-07-01T08:00:00+02:00','stopped_at'=>'2026-07-01T15:30:00+02:00']]);
        $first = $this->request('POST','time-entries/index.php',$body);
        self::assertSame(201,$first['status'],$first['body']);
        $entry = $first['json']['data'];
        $replay = $this->request('POST','time-entries/index.php',$body);
        self::assertSame($first['json'],$replay['json']);
        self::assertStringContainsString('Idempotent-Replayed: true',$replay['headers']);
        $this->assertError($this->request('POST','time-entries/index.php',array_replace($body,['hours'=>'8'])),409,'conflict','client_id_reused');
        $path = 'time-entries/index.php?id=' . $entry['id'];
        $update = ['client_id'=>MobileAuth::uuid(),'hours'=>'6.25','text'=>"Edited ' work"];
        $this->assertError($this->request('PATCH',$path,$update),422,'validation_failed','if_match_required');
        $changed = $this->request('PATCH',$path,$update,'self',$entry['etag']);
        self::assertSame(200,$changed['status'],$changed['body']);
        self::assertSame('6.25',$changed['json']['data']['hours']);
        self::assertSame('1125.000',$this->rows('SELECT sum FROM loen WHERE id=$1',[$entry['id']])[0]['sum']);
        $replay = $this->request('PATCH',$path,$update,'self',$entry['etag']);
        self::assertSame($changed['json'],$replay['json']);
        self::assertStringContainsString('Idempotent-Replayed: true',$replay['headers']);
        $this->assertError($this->request('PATCH',$path,['client_id'=>MobileAuth::uuid(),'text'=>'stale'],'self',$entry['etag']),412,'precondition_failed');
        $delete = ['client_id'=>MobileAuth::uuid()];
        $deleted = $this->request('DELETE',$path,$delete,'self',$changed['json']['data']['etag']);
        self::assertSame(200,$deleted['status'],$deleted['body']);
        self::assertTrue($deleted['json']['data']['deleted']);
        self::assertNotNull($this->rows('SELECT deleted_at FROM mobile_time_entries WHERE loen_id=$1',[$entry['id']])[0]['deleted_at']);
        $replay = $this->request('DELETE',$path,$delete,'self',$changed['json']['data']['etag']);
        self::assertSame($deleted['json'],$replay['json']);
        self::assertStringContainsString('Idempotent-Replayed: true',$replay['headers']);
        $this->assertError($this->request('GET',$path),404,'not_found');
        $marker = $this->rows('SELECT timer_started_at,timer_stopped_at FROM mobile_time_entries WHERE loen_id=$1',[$entry['id']])[0];
        self::assertSame(27000,strtotime($marker['timer_stopped_at'])-strtotime($marker['timer_started_at']));
    }

    public function testBulkSubmitIsAtomicLocksInIdOrderAndReplays(): void
    {
        $a = $this->create();
        $b = $this->create(['hours'=>'2']);
        $submit = ['client_id'=>MobileAuth::uuid(),'ids'=>[$b['id'],$a['id']]];
        $this->assertError($this->request('POST','time-entries/submit.php',$submit),422,'validation_failed','if_match_required');
        $this->assertError($this->request('POST','time-entries/submit.php',$submit,'self',$a['etag']),412,'precondition_failed');
        self::assertSame(['',''],array_column($this->rows('SELECT afsluttet FROM loen ORDER BY id'),'afsluttet'));
        $etag = $a['etag'] . ', ' . $b['etag'];
        $response = $this->request('POST','time-entries/submit.php',$submit,'self',$etag);
        self::assertSame(200,$response['status'],$response['body']);
        self::assertSame([$b['id'],$a['id']],array_column($response['json']['data']['items'],'id'));
        self::assertSame(['overfoert','overfoert'],array_column($response['json']['data']['items'],'state'));
        $replay = $this->request('POST','time-entries/submit.php',$submit,'self',$etag);
        self::assertSame($response['json'],$replay['json']);
        self::assertStringContainsString('Idempotent-Replayed: true',$replay['headers']);
        $this->assertError($this->request('POST','time-entries/submit.php',array_replace($submit,['ids'=>[$a['id']]]),'self',$etag),409,'conflict','client_id_reused');
    }

    public function testWeekPagingAndTotalsUseAllCallerRows(): void
    {
        $this->create();
        $this->create(['hours'=>'2.25','date'=>'2026-07-02']);
        $this->create(['date'=>'2026-07-10']);
        $this->create(['hours'=>'24'],'other');
        $response = $this->request('GET','time-entries/index.php?week=2026-W27&limit=1&offset=1');
        self::assertSame(200,$response['status'],$response['body']);
        self::assertSame(2,$response['json']['data']['total']);
        self::assertSame(['hours'=>'9.75'],$response['json']['data']['totals']);
        self::assertCount(1,$response['json']['data']['items']);
        self::assertSame('2026-07-01',$response['json']['data']['items'][0]['date']);
        $period = $this->request('GET','time-entries/index.php?from=2026-07-01&to=2026-07-02');
        self::assertSame(2,$period['json']['data']['total']);
        self::assertSame(['hours'=>'9.75'],$period['json']['data']['totals']);
        $this->assertError($this->request('GET','time-entries/index.php?from=bad'),422,'validation_failed','date_missing');
        $this->assertError($this->request('GET','time-entries/index.php?week=2026-W99'),422,'validation_failed','query_invalid');
        $this->assertError($this->request('GET','time-entries/index.php?week=0000-W01'),422,'validation_failed','query_invalid');
    }

    public function testBulkSubmitRevalidatesCaseStateAndLeavesEveryEntryUnsentOnFailure(): void
    {
        $first = $this->create();
        $this->rows("UPDATE sager SET status='Montage' WHERE id=2");
        $closed = $this->create(['case_id'=>2,'task_id'=>2]);
        $this->rows("UPDATE sager SET status='Afsluttet' WHERE id=2");
        $body = ['client_id'=>MobileAuth::uuid(),'ids'=>[$first['id'],$closed['id']]];
        $etag = $first['etag'] . ', ' . $closed['etag'];
        $this->assertError($this->request('POST','time-entries/submit.php',$body,'self',$etag),422,'validation_failed','case_inactive');
        self::assertSame(['',''],array_column($this->rows('SELECT afsluttet FROM loen ORDER BY id'),'afsluttet'));
        self::assertSame([null,null],array_column($this->rows('SELECT submitted_at FROM mobile_time_entries ORDER BY loen_id'),'submitted_at'));
        self::assertSame([],$this->rows('SELECT client_id FROM mobile_idempotency WHERE client_id=$1',[$body['client_id']]));

        $this->rows("UPDATE sager SET status='Montage' WHERE id=2");
        $retry = $this->request('POST','time-entries/submit.php',$body,'self',$etag);
        self::assertSame(200,$retry['status'],$retry['body']);
        self::assertSame(['overfoert','overfoert'],array_column($retry['json']['data']['items'],'state'));
    }

    public function testTenAppInsertsRacingBrowserHaveDistinctNumbers(): void
    {
        $barrier = tempnam(sys_get_temp_dir(),'timebarrier');
        unlink($barrier);
        $workers = [];
        try {
            for ($i=0; $i<10; $i++) {
                $base = tempnam(sys_get_temp_dir(),'timeworker');
                $args = ['db'=>RestApiEnv::testDb(),'tenant_id'=>T3EventTestEnv::$tenantId,'user'=>self::$users['self'],
                    'body'=>$this->body(),'barrier'=>$barrier];
                // Do not pass password-bearing user rows to workers.
                $args['user'] = array_intersect_key($args['user'],array_flip(['id','brugernavn']));
                $process = proc_open([PHP_BINARY,__DIR__ . '/support/run_time_create.php',json_encode($args,JSON_THROW_ON_ERROR)],
                    [0=>['file','/dev/null','r'],1=>['file',$base,'w'],2=>['file',$base . '.err','w']],$pipes);
                $workers[] = [$process,$base];
            }
            touch($barrier);
            $this->page(['gem'=>'Gem','id'=>0,'loen_art'=>'timer','loendato'=>'01-07-2026','sag_nr'=>701,'opg_id'=>1,'opg_nr'=>1,
                'loen_tekst'=>'Concurrent browser','medarb_nr'=>[101],'medarb_navn'=>['Test Employee'],'ansat_id'=>[1],
                'loen_timer'=>['7,5'],'loen_50pct'=>['0'],'loen_100pct'=>['0'],'loen_loen'=>['0'],'loen_date'=>['01-07-2026'],
                'loen_km'=>['0'],'loen_mentor'=>['0'],'a_stk'=>[]]);
            foreach ($workers as [$process,$base]) {
                self::assertSame(0,proc_close($process),file_get_contents($base . '.err'));
                self::assertSame(201,json_decode(file_get_contents($base),true,512,JSON_THROW_ON_ERROR)['status']);
            }
            self::assertSame(range(1,11),array_map('intval',array_column($this->rows('SELECT nummer FROM loen ORDER BY nummer'),'nummer')));
            self::assertCount(10,$this->rows('SELECT * FROM mobile_time_entries'));
            self::assertCount(10,$this->rows('SELECT * FROM mobile_idempotency'));
        } finally {
            foreach ($workers as [$process,$base]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
                unlink($base);
                unlink($base . '.err');
            }
            unlink($barrier);
        }
    }
}
