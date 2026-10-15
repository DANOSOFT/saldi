<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T4TimeTestCase.php --- ver 5.0.0 --- 2026.10.06
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
require_once __DIR__ . '/T3EventTestEnv.php';
require_once __DIR__ . '/../../../restapi/services/TimeEntryService.php';

/** Real HTTP tests use disposable tenants and server-selected employee identities. */
abstract class T4TimeTestCase extends TestCase
{
    protected static array $users;
    protected static array $tokens;
    protected static string $session;

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        T3EventTestEnv::setup();
        self::$session = 'timeweb' . bin2hex(random_bytes(12));
        self::$users = ['self'=>T3EventTestEnv::$account];
        foreach (['other','unlinked','denied','alias'] as $role) {
            self::$users[$role] = RestApiEnv::rows(T3EventTestEnv::$tenant,
                'INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,true) RETURNING *',
                ['time_' . $role,md5(TestCredentials::password('time')) ,str_repeat('0',16)])[0];
        }
        RestApiEnv::rows(T3EventTestEnv::$tenant,"UPDATE brugere SET brugernavn='loenchar' WHERE id=$1", [self::$users['self']['id']]);
        self::$users['self']['brugernavn'] = 'loenchar';
        RestApiEnv::rows(T3EventTestEnv::$registry,"INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES($1,'loenchar',$2,$3,$4,1,$5,false,0)", [self::$session,RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('1',50),(string)time()]);
    }

    public static function tearDownAfterClass(): void
    {
        RestApiEnv::rows(T3EventTestEnv::$registry,'DELETE FROM online WHERE session_id=$1 AND db=$2', [self::$session,RestApiEnv::testDb()]);
        T3EventTestEnv::teardown();
    }

    protected function setUp(): void
    {
        T3EventTestEnv::reset();
        $this->rows('TRUNCATE loen_enheder,loen,mobile_time_entries,opgaver,sager,ansatte RESTART IDENTITY CASCADE');
        $this->rows("DELETE FROM grupper WHERE art IN ('loen','ANSAT')");
        $this->rows("INSERT INTO grupper(id,art,kodenr,box2) VALUES(94001,'ANSAT',1,'11111111'),(94002,'ANSAT',2,'00000000')");
        $this->rows("INSERT INTO grupper(art,kodenr,box1,box2,box3,box4,box5,box6,box7,box8)
            VALUES('loen',1,$1,'150','120','2026-01-01',$2,$3,'110',$4)", ["0\t0","6\t60","3.50\t20","60.25\t120.50"]);
        $this->rows("DELETE FROM settings WHERE var_grp='casePayment'");
        $this->rows("INSERT INTO settings(var_grp,var_name,var_description,var_value,user_id) VALUES('casePayment','hourTypes0','Special hours','210',0)");
        $this->rows("INSERT INTO ansatte(id,nummer,navn,loen,extraloen,trainee,startdate,gruppe,lukket)
            VALUES(1,101,'Test Employee',180,10,'','2026-04-01',94001,''),
                (2,102,'Private Other Employee',888,0,'','2026-04-01',94001,''),
                (3,103,'Denied Employee',100,0,'','2026-04-01',94002,'')");
        $this->rows("INSERT INTO sager(id,sagsnr,beskrivelse,status,ref,udf_addr1,firmanavn)
            VALUES(1,'701','Test case','Montage','Case ref','Test address','Test customer'),
                (2,'702','Inactive','Afsluttet','','','')");
        $this->rows("INSERT INTO opgaver(id,nr,assign_id,assign_to,beskrivelse,status)
            VALUES(1,1,1,'sager','Test task','Montage'),(2,2,2,'sager','Foreign task','Montage'),
                (3,3,1,'sager','Inactive task','Afsluttet'),(4,4,1,'other','Foreign domain','Montage')");
        self::$tokens = [];
        foreach (self::$users as $role=>$user) {
            $employee = ['self'=>1,'other'=>2,'unlinked'=>null,'denied'=>3,'alias'=>1][$role];
            $this->rows('UPDATE brugere SET ansat_id=$1,rettigheder=$2,status=true WHERE id=$3', [$employee,str_repeat('0',16),$user['id']]);
            self::$tokens[$role] = (new MobileAuth())->create($user, 'account', T3EventTestEnv::$tenantId, 'time-test')['access_token'];
        }
        self::$tokens['registry'] = T3EventTestEnv::token(true);
        $this->rows("UPDATE online SET logtime=$1 WHERE session_id=$2", [(string)time(),self::$session],true);
    }

    protected function rows(string $sql, array $params = [], bool $registry = false): array
    {
        return RestApiEnv::rows($registry ? T3EventTestEnv::$registry : T3EventTestEnv::$tenant, $sql, $params);
    }

    /** @return array{status:int,json:?array,body:string,headers:string} */
    protected function request(string $method, string $path, $body = null, string $role = 'self', ?string $etag = null): array
    {
        $headers = ['Accept: application/json','Content-Type: application/json'];
        if ($role !== 'anonymous') {
            $headers[] = 'Authorization: Bearer ' . self::$tokens[$role];
        }
        if ($etag !== null) {
            $headers[] = 'If-Match: ' . $etag;
        }
        $ch = curl_init(RestApiEnv::baseUrl() . '/restapi/endpoints/v1/' . $path);
        $responseHeaders = '';
        curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,
            CURLOPT_HTTPHEADER=>$headers,CURLOPT_HEADERFUNCTION=>static function ($curl, string $line) use (&$responseHeaders): int {
                $responseHeaders .= $line;
                return strlen($line);
            }]);
        if ($body !== null) {
            curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body, JSON_THROW_ON_ERROR));
        }
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status'=>$status,'body'=>(string)$raw,'json'=>json_decode((string)$raw,true),'headers'=>$responseHeaders];
    }

    protected function body(array $changes = []): array
    {
        return array_replace(['client_id'=>MobileAuth::uuid(),'case_id'=>1,'task_id'=>1,'date'=>'2026-07-01',
            'hours'=>'7.50','text'=>"Employee's work"], $changes);
    }

    protected function create(array $changes = [], string $role = 'self'): array
    {
        $response = $this->request('POST','time-entries/index.php',$this->body($changes),$role);
        self::assertSame(201,$response['status'],$response['body']);
        return $response['json']['data'];
    }

    protected function service($conn = null, string $role = 'self'): TimeEntryService
    {
        $user = self::$users[$role];
        return new TimeEntryService(new EngineContext($conn ?? T3EventTestEnv::$tenant,RestApiEnv::testDb(),T3EventTestEnv::$tenantId,
            $user['brugernavn'],(int)$user['id'],'2026-07-01'),'account',Subject::build('account',(int)$user['id']));
    }

    protected function assertError(array $response, int $status, string $error, ?string $reason = null): void
    {
        self::assertSame($status,$response['status'],$response['body']);
        self::assertSame($error,$response['json']['data']['error'],$response['body']);
        if ($reason !== null) {
            self::assertSame($reason,$response['json']['data']['details']['reason'],$response['body']);
        }
    }

    /** @return array{html:string,errors:string} Runs the existing browser harness against this class's disposable tenant. */
    protected function page(array $post, array $get = []): array
    {
        $base = tempnam(sys_get_temp_dir(),'timepage');
        $process = proc_open([PHP_BINARY,__DIR__ . '/../../characterization/support/run_loen_page.php',
            json_encode(['get'=>$get + ['funktion'=>'ret_loen'],'post'=>$post],JSON_THROW_ON_ERROR),self::$session],
            [0=>['file','/dev/null','r'],1=>['file',$base,'w'],2=>['file',$base . '.err','w']],$pipes);
        try {
            $exit = proc_close($process);
            $errors = file_get_contents($base . '.err');
            self::assertSame(0,$exit,$errors);
            file_put_contents('/tmp/t4-b4b-web-warnings.log',$errors,FILE_APPEND);
            $html = file_get_contents($base);
            self::assertStringNotContainsString('query failed',$html);
            return ['html'=>$html,'errors'=>$errors];
        } finally {
            unlink($base);
            unlink($base . '.err');
        }
    }
}
