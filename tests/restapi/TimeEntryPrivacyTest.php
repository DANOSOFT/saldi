<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/TimeEntryPrivacyTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Pin privacy of validators when private payroll and another employee's hours change.
// 20261001 CDX/LH Regress an empty conditional lock followed by web membership restoration.

use PHPUnit\Framework\Attributes\DataProvider;
require_once __DIR__ . '/support/T4TimeTestCase.php';
require_once __DIR__ . '/support/TimeEntryLockRaceHarness.php';

final class TimeEntryPrivacyTest extends T4TimeTestCase
{
    public function testFiscalFreeServiceRestoresConnectionOnSuccessAndFailure(): void
    {
        $user = self::$users['self'];
        $service = new TimeEntryService(new TimeEntryContext(T3EventTestEnv::$tenant, RestApiEnv::testDb(),
            T3EventTestEnv::$tenantId, $user['brugernavn'], (int)$user['id']), 'account',
            Subject::build('account', (int)$user['id']));
        $hadConnection = array_key_exists('connection', $GLOBALS);
        $previous = ifset($GLOBALS, 'connection');
        try {
            $GLOBALS['connection'] = T3EventTestEnv::$registry;
            self::assertSame(['items'=>[['code'=>'hourTypes0','label'=>'Special hours']]], $service->hourTypes());
            self::assertSame(T3EventTestEnv::$registry, $GLOBALS['connection']);
            self::assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status(T3EventTestEnv::$tenant));
            try {
                $service->create($this->body(['hours'=>'0.00']));
                self::fail('Invalid hours must roll back the service transaction');
            } catch (MobileT2Error $error) {
                self::assertSame(422, $error->status);
            }
            self::assertSame(T3EventTestEnv::$registry, $GLOBALS['connection']);
            self::assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status(T3EventTestEnv::$tenant));
            unset($GLOBALS['connection']);
            $service->hourTypes();
            self::assertArrayNotHasKey('connection', $GLOBALS);
        } finally {
            if ($hadConnection) {
                $GLOBALS['connection'] = $previous;
            } else {
                unset($GLOBALS['connection']);
            }
        }
    }

    /** @return iterable<string,array{string}> */
    public static function restoredMembershipMutations(): iterable
    {
        foreach (['update','delete','submit'] as $operation) {
            yield $operation=>[$operation];
        }
    }

    #[DataProvider('restoredMembershipMutations')]
    public function testMembershipRestoredAfterEmptyLockStillReturns404(string $operation): void
    {
        $entry = $this->create();
        $this->rows("UPDATE loen SET ansatte='2' WHERE id=$1", [$entry['id']]);
        $web = RestApiEnv::connect(RestApiEnv::testDb());
        $restored = false;
        TimeEntryLockRaceHarness::load();
        TimeEntryLockRaceHarness::$afterLock = static function (array $rows) use ($web, $entry, &$restored): void {
            self::assertSame([], $rows, 'The conditional SELECT must lock no row');
            RestApiEnv::rows($web, "UPDATE loen SET ansatte='1' WHERE id=$1", [$entry['id']]);
            $restored = true;
        };
        $user = self::$users['self'];
        $context = new EngineContext(T3EventTestEnv::$tenant, RestApiEnv::testDb(), T3EventTestEnv::$tenantId,
            $user['brugernavn'], (int)$user['id'], '2026-07-01');
        $service = new TimeEntryLockRaceService($context, 'account', Subject::build('account', (int)$user['id']));
        $before = $this->rows('SELECT * FROM loen WHERE id=$1', [$entry['id']])[0];
        $before['ansatte'] = '1';
        $marker = $this->rows('SELECT * FROM mobile_time_entries WHERE loen_id=$1', [$entry['id']]);
        $idempotency = $this->rows('SELECT * FROM mobile_idempotency ORDER BY id');
        try {
            $body = ['client_id'=>MobileAuth::uuid()];
            try {
                if ($operation === 'submit') {
                    $service->submit($body + ['ids'=>[$entry['id']]], $entry['etag']);
                } elseif ($operation === 'delete') {
                    $service->delete($entry['id'], $body, $entry['etag']);
                } else {
                    $service->update($entry['id'], $body + ['text'=>'Unlocked edit'], $entry['etag']);
                }
                self::fail('An empty lock result must refuse the mutation');
            } catch (MobileT2Error $error) {
                self::assertSame(404, $error->status);
                self::assertSame('not_found', $error->error);
            }
            self::assertTrue($restored, 'Membership was restored before the service examined the lock result');
            self::assertSame($before, $this->rows('SELECT * FROM loen WHERE id=$1', [$entry['id']])[0]);
            self::assertSame($marker, $this->rows('SELECT * FROM mobile_time_entries WHERE loen_id=$1', [$entry['id']]));
            self::assertSame($idempotency, $this->rows('SELECT * FROM mobile_idempotency ORDER BY id'));
        } finally {
            TimeEntryLockRaceHarness::$afterLock = null;
            pg_close($web);
        }
    }

    public function testMixedWebSeddelProjectsOnlyCallerPositionAndEveryMutationIsReadOnly(): void
    {
        // Caller is the middle token, with nonmatching id 11 before it: substring membership is unsafe.
        $this->rows("INSERT INTO loen(art,nummer,loendate,sag_id,opg_id,ansatte,timer,t50pct,t100pct,hourtype,loen,sum,tekst)
            VALUES('timer',42,'2026-07-01',1,1,$1,$2,$3,$4,$5,$6,9999,'Shared work')",
            ["11\t1\t2","24\t7.5\t4","9\t1\t3","8\t0.5\t2","secretA\thourTypes0\tsecretB","999\t180\t888"]);
        $before = $this->rows('SELECT * FROM loen')[0];
        $id = (int)$before['id'];
        $list = $this->request('GET','time-entries/index.php');
        self::assertSame(200,$list['status'],$list['body']);
        self::assertSame(['hours'=>'7.50'],$list['json']['data']['totals']);
        $entry = $list['json']['data']['items'][0];
        self::assertSame('7.50',$entry['hours']);
        self::assertSame('1.00',$entry['overtime_50']);
        self::assertSame('0.50',$entry['overtime_100']);
        self::assertSame('hourTypes0',$entry['hour_type']);
        self::assertFalse($entry['created_in_app']);
        self::assertFalse($entry['editable']);
        self::assertSame('web_seddel',$entry['read_only_reason']);
        $this->assertPrivate($list['json']);
        foreach (['secretA','secretB','9999','888','Private Other Employee'] as $secret) {
            self::assertStringNotContainsString($secret,$list['body']);
        }
        $this->assertMutationsReadOnly($id,$entry['etag'],'web_seddel');
        self::assertSame($before,$this->rows('SELECT * FROM loen WHERE id=$1',[$id])[0]);
    }

    public function testWebSingleEmployeeSeddelStillCannotBeEditedInApp(): void
    {
        $entry = $this->create();
        $this->rows('DELETE FROM mobile_time_entries WHERE loen_id=$1',[$entry['id']]);
        $detail = $this->request('GET','time-entries/index.php?id=' . $entry['id']);
        self::assertFalse($detail['json']['data']['created_in_app']);
        $this->assertMutationsReadOnly($entry['id'],$detail['json']['data']['etag'],'web_seddel');
    }

    public function testPrivatePayrollAndOtherEmployeeChangesLeaveCallerResponseAndEtagUnchanged(): void
    {
        $entry = $this->create();
        $id = $entry['id'];
        $this->rows('UPDATE loen SET ansatte=$1,timer=$2,t50pct=$3,t100pct=$4,hourtype=$5 WHERE id=$6',
            ["2\t1","24\t7.5","6\t0","8\t0","privateType\t",$id]);
        $path = 'time-entries/index.php?id=' . $id;
        $before = $this->request('GET',$path);
        self::assertSame(200,$before['status'],$before['body']);

        $this->rows('UPDATE loen SET timer=$1,t50pct=$2,t100pct=$3,hourtype=$4,loen=$5,sum=12345,fordeling=$6 WHERE id=$7',
            ["12\t7.5","3\t0","4\t0","anotherPrivateType\t","999\t210","50\t100",$id]);
        $after = $this->request('GET',$path);
        self::assertSame(200,$after['status'],$after['body']);
        self::assertSame($before['json'],$after['json'],'Private changes must not become observable through the body or validator');
        self::assertStringContainsString('ETag: ' . $before['json']['data']['etag'],$after['headers']);
        $this->assertPrivate($after['json']);

        $this->rows('UPDATE loen SET timer=$1 WHERE id=$2',["12\t6.25",$id]);
        $ownChange = $this->request('GET',$path);
        self::assertSame(200,$ownChange['status'],$ownChange['body']);
        self::assertSame('6.25',$ownChange['json']['data']['hours']);
        self::assertNotSame($after['json']['data']['etag'],$ownChange['json']['data']['etag']);
    }

    public function testWebAddingSecondEmployeeToAppSeddelMakesItReadOnly(): void
    {
        $entry = $this->create();
        $this->rows('UPDATE loen SET ansatte=$1,timer=$2,t50pct=$3,t100pct=$4,hourtype=$5,loen=$6 WHERE id=$7',
            ["1\t2","7.5\t24","0\t6","0\t8","\tsecret","180\t888",$entry['id']]);
        $before = $this->rows('SELECT * FROM loen WHERE id=$1',[$entry['id']])[0];
        $detail = $this->request('GET','time-entries/index.php?id=' . $entry['id']);
        self::assertTrue($detail['json']['data']['created_in_app']);
        self::assertSame('7.50',$detail['json']['data']['hours']);
        $this->assertPrivate($detail['json']);
        // Read-only takes precedence over a stale validator, so no unsafe whole-row write can happen.
        $this->assertMutationsReadOnly($entry['id'],$entry['etag'],'web_seddel');
        self::assertSame($before,$this->rows('SELECT * FROM loen WHERE id=$1',[$entry['id']])[0]);
    }

    /** @return iterable<string,array{string,string}> */
    public static function states(): iterable
    {
        foreach (['afsluttet'=>'overfoert','godkendt'=>'godkendt','afvist'=>'afvist','afregnet'=>'afregnet','udbetalt'=>'afregnet'] as $stamp=>$state) {
            yield $stamp=>[$stamp,$state];
        }
    }

    #[DataProvider('states')]
    public function testAllTerminalStatesRefuseEditDeleteAndSubmit(string $stamp, string $state): void
    {
        $entry = $this->create();
        $this->rows("UPDATE loen SET $stamp=$1 WHERE id=$2", [(string)time(),$entry['id']]);
        if ($stamp === 'afvist') {
            $this->rows("UPDATE loen SET afvist_af='loenchar',afvist_pga='Correct hours' WHERE id=$1",[$entry['id']]);
        }
        $detail = $this->request('GET','time-entries/index.php?id=' . $entry['id']);
        self::assertSame($state,$detail['json']['data']['state']);
        self::assertFalse($detail['json']['data']['editable']);
        if ($stamp === 'afvist') {
            self::assertSame('Test Employee',$detail['json']['data']['rejection']['by_name']);
            self::assertSame('Correct hours',$detail['json']['data']['rejection']['reason']);
        }
        $this->assertPrivate($detail['json']);
        $this->assertMutationsReadOnly($entry['id'],$detail['json']['data']['etag'],'submitted');
    }

    public function testSeddelNotListingCallerIs404IncludingExactTokenMatching(): void
    {
        $entry = $this->create([], 'other');
        $this->rows("INSERT INTO loen(art,nummer,loendate,ansatte,timer) VALUES('timer',99,'2026-07-01','11','24')");
        self::assertSame([],$this->request('GET','time-entries/index.php')['json']['data']['items']);
        foreach ([['GET',null],['PATCH',['client_id'=>MobileAuth::uuid(),'hours'=>'1']],['DELETE',['client_id'=>MobileAuth::uuid()]]] as [$method,$body]) {
            $this->assertError($this->request($method,'time-entries/index.php?id=' . $entry['id'],$body,'self',$entry['etag']),404,'not_found');
        }
        $this->assertError($this->request('POST','time-entries/submit.php',['client_id'=>MobileAuth::uuid(),'ids'=>[$entry['id']]],'self',$entry['etag']),404,'not_found');
    }

    public function testSubmittedProvenanceStaysReadOnlyEvenIfWebClearsPayrollStamp(): void
    {
        $entry = $this->create();
        $submit = $this->request('POST','time-entries/submit.php',['client_id'=>MobileAuth::uuid(),'ids'=>[$entry['id']]],'self',$entry['etag']);
        self::assertSame(200,$submit['status'],$submit['body']);
        $this->rows("UPDATE loen SET afsluttet='' WHERE id=$1",[$entry['id']]);
        $this->assertMutationsReadOnly($entry['id'],$submit['json']['data']['items'][0]['etag'],'submitted');
    }

    public function testBulkSubmitRollsBackWhenAnyEntryIsShared(): void
    {
        $first = $this->create();
        $shared = $this->create();
        $this->rows('UPDATE loen SET ansatte=$1,timer=$2 WHERE id=$3',["1\t2","7.5\t24",$shared['id']]);
        $response = $this->request('POST','time-entries/submit.php',['client_id'=>MobileAuth::uuid(),'ids'=>[$first['id'],$shared['id']]],
            'self',$first['etag'] . ', ' . $shared['etag']);
        $this->assertError($response,409,'conflict','entry_read_only');
        self::assertSame(['',''],array_column($this->rows('SELECT afsluttet FROM loen ORDER BY id'),'afsluttet'));
        self::assertSame([null,null],array_column($this->rows('SELECT submitted_at FROM mobile_time_entries ORDER BY loen_id'),'submitted_at'));
    }

    public function testRightsAtEveryEndpointAndMethod(): void
    {
        $routes = ['cases/index.php'=>['GET'],'cases/tasks.php?case_id=1'=>['GET'],
            'time-entries/hour-types.php'=>['GET'],'time-entries/index.php'=>['GET','POST','PATCH','DELETE'],
            'time-entries/submit.php'=>['POST']];
        foreach ($routes as $path=>$methods) {
            foreach ($methods as $method) {
                foreach (['anonymous','unlinked','denied','registry'] as $role) {
                    $response = $this->request($method,$path,$method === 'GET' ? null : $this->body(),$role);
                    if ($role === 'anonymous') {
                        self::assertSame(401,$response['status'],$response['body']);
                    } else {
                        $this->assertError($response,403,'permission_denied');
                    }
                    if (in_array($role,['unlinked','registry'],true)) {
                        self::assertSame('no_employee_link',$response['json']['data']['details']['reason']);
                    }
                }
            }
        }
        $this->rows("UPDATE ansatte SET lukket='on' WHERE id=1");
        $this->assertError($this->request('GET','time-entries/index.php'),403,'permission_denied','no_employee_link');
    }

    public function testSameEmployeeAliasSeesOwnEntriesWhileCurrentGroupRightsControlAccess(): void
    {
        $entry = $this->create();
        $detail = $this->request('GET','time-entries/index.php?id=' . $entry['id'],null,'alias');
        self::assertSame(200,$detail['status'],$detail['body']);
        self::assertSame('7.50',$detail['json']['data']['hours']);
        $this->rows("UPDATE grupper SET box2='00000000' WHERE id=94001");
        $this->assertError($this->request('GET','time-entries/index.php'),403,'permission_denied');
    }

    public function testAllSuccessAndErrorBodiesHaveNoPayrollKeys(): void
    {
        $entry = $this->create();
        foreach (['cases/index.php','cases/tasks.php?case_id=1','time-entries/hour-types.php','time-entries/index.php','time-entries/index.php?id=' . $entry['id']] as $path) {
            $response = $this->request('GET',$path);
            self::assertSame(200,$response['status'],$response['body']);
            $this->assertPrivate($response['json']);
        }
        $error = $this->request('POST','time-entries/index.php',$this->body(['loen'=>'999']));
        $this->assertError($error,422,'validation_failed','unknown_field');
        $this->assertPrivate($error['json']);
        $submit = $this->request('POST','time-entries/submit.php',['client_id'=>MobileAuth::uuid(),'ids'=>[$entry['id']]],'self',$entry['etag']);
        self::assertSame(200,$submit['status'],$submit['body']);
        $this->assertPrivate($submit['json']);
        $me = $this->request('GET','auth/me.php');
        self::assertTrue($me['json']['data']['capabilities']['time_entries']);
    }

    public function testReplayAfterRelinkingLoginCannotRevealFormerEmployeeHours(): void
    {
        $body = $this->body();
        $response = $this->request('POST','time-entries/index.php',$body);
        self::assertSame(201,$response['status'],$response['body']);
        $this->rows('UPDATE brugere SET ansat_id=2 WHERE id=$1',[self::$users['self']['id']]);
        $this->assertError($this->request('POST','time-entries/index.php',$body),404,'not_found');
        self::assertSame([],$this->request('GET','time-entries/index.php')['json']['data']['items']);
    }

    public function testCreateClientIdReusedByAnotherSubjectConflictsWithoutNewRows(): void
    {
        $body = $this->body();
        $first = $this->request('POST','time-entries/index.php',$body);
        self::assertSame(201,$first['status'],$first['body']);
        $this->assertError($this->request('POST','time-entries/index.php',$body,'other'),409,'conflict','client_id_reused');
        self::assertCount(1,$this->rows('SELECT * FROM loen'));
        self::assertCount(1,$this->rows('SELECT * FROM mobile_time_entries'));
    }

    public function testWebMembershipChangeWhileAppWaitsForRowLockRefusesUpdate(): void
    {
        $entry = $this->create();
        $workerName = 'time-lock-' . bin2hex(random_bytes(8));
        $barrier = tempnam(sys_get_temp_dir(),'timelockbarrier');
        $base = tempnam(sys_get_temp_dir(),'timelockworker');
        $connection = T3EventTestEnv::$tenant;
        $process = null;
        $this->rows('BEGIN');
        try {
            $this->rows('UPDATE loen SET ansatte=$1,timer=$2 WHERE id=$3',["1\t2","7.5\t24",$entry['id']]);
            $args = ['db'=>RestApiEnv::testDb(),'tenant_id'=>T3EventTestEnv::$tenantId,'user'=>[
                'id'=>self::$users['self']['id'],'brugernavn'=>self::$users['self']['brugernavn']],
                'body'=>['client_id'=>MobileAuth::uuid(),'hours'=>'1'],'barrier'=>$barrier,'worker_name'=>$workerName,
                'operation'=>'update','id'=>$entry['id'],'etag'=>$entry['etag']];
            $process = proc_open([PHP_BINARY,__DIR__ . '/support/run_time_create.php',json_encode($args,JSON_THROW_ON_ERROR)],
                [0=>['file','/dev/null','r'],1=>['file',$base,'w'],2=>['file',$base . '.err','w']],$pipes);
            $deadline = microtime(true)+10;
            do {
                $this->rows('SELECT pg_stat_clear_snapshot()');
                $waiting = $this->rows("SELECT pid FROM pg_stat_activity WHERE application_name=$1 AND wait_event_type='Lock'",[$workerName]);
                if ($waiting !== []) {
                    break;
                }
                usleep(10000);
            } while (microtime(true)<$deadline);
            self::assertNotEmpty($waiting,'App worker must wait for the web row lock');
            $this->rows('COMMIT');
            self::assertSame(0,proc_close($process),file_get_contents($base . '.err'));
            $process = null;
            $result = json_decode(file_get_contents($base),true,512,JSON_THROW_ON_ERROR);
            self::assertSame(409,$result['status']);
            self::assertSame('entry_read_only',$result['body']['data']['details']['reason']);
            self::assertSame('web_seddel',$result['body']['data']['details']['read_only_reason']);
            self::assertSame("7.5\t24",$this->rows('SELECT timer FROM loen WHERE id=$1',[$entry['id']])[0]['timer']);
        } finally {
            if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) {
                $this->rows('ROLLBACK');
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            unlink($barrier);
            unlink($base);
            unlink($base . '.err');
        }
    }

    private function assertMutationsReadOnly(int $id, string $etag, string $reason): void
    {
        foreach ([['PATCH','time-entries/index.php?id=' . $id,['client_id'=>MobileAuth::uuid(),'hours'=>'1']],
            ['DELETE','time-entries/index.php?id=' . $id,['client_id'=>MobileAuth::uuid()]],
            ['POST','time-entries/submit.php',['client_id'=>MobileAuth::uuid(),'ids'=>[$id]]]] as [$method,$path,$body]) {
            $response = $this->request($method,$path,$body,'self',$etag);
            $this->assertError($response,409,'conflict','entry_read_only');
            self::assertSame($reason,$response['json']['data']['details']['read_only_reason']);
        }
    }

    private function assertPrivate($value): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key=>$child) {
            if (is_string($key)) {
                self::assertDoesNotMatchRegularExpression('/loen|sum|rate|wage/i',$key);
            }
            $this->assertPrivate($child);
        }
    }
}
