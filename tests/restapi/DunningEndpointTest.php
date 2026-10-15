<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/DunningEndpointTest.php --- ver 5.0.0 --- 2026.10.03
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
// 20261001 CDX/LH Cover PDF currency totals, settled series and replay after web deletion.
// 20261003 CDX/LH Provide a CLI remote address while rendering the PDF in the currency regression test.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../fixtures/dunning/DunningFixture.php';
require_once __DIR__ . '/../fixtures/easyubl/DocumentFixture.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../includes/db_query.php';
require_once __DIR__ . '/../../restapi/services/DunningService.php';

final class DunningEndpointTest extends TestCase
{
    private const ROOT = '/restapi/endpoints/v1/debitor/dunning/';
    private static $tenant;
    private array $fixture;
    private array $sessions = [];

    public static function setUpBeforeClass(): void
    {
        if (($reason=RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        mobileTenantUpdates(self::$tenant);
        RestApiEnv::accessToken();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            pg_close(self::$tenant);
            RestApiEnv::teardownTenant();
        }
    }

    protected function setUp(): void
    {
        $this->fixture = DunningFixture::seed(self::$tenant);
        DocumentFixture::configureMail(self::$tenant);
    }

    protected function tearDown(): void
    {
        if (pg_transaction_status(self::$tenant) !== PGSQL_TRANSACTION_IDLE) {
            RestApiEnv::rows(self::$tenant,'ROLLBACK');
        }
        if ($this->sessions) {
            $master = RestApiEnv::connect(RestApiEnv::masterDb());
            foreach ($this->sessions as $session) {
                RestApiEnv::rows($master,'DELETE FROM online WHERE session_id=$1',[$session]);
            }
            pg_close($master);
        }
        $this->sessions = [];
    }

    private static function uuid(): string
    {
        $random = bin2hex(random_bytes(16));
        return substr($random,0,8).'-'.substr($random,8,4).'-'.substr($random,12,4).'-'.substr($random,16,4).'-'.substr($random,20);
    }

    private function body(int $level=1,string $channel='none'): array
    {
        return ['client_id'=>self::uuid(),'konto_id'=>$this->fixture['konto_id'],'expected_level'=>$level,'deliver'=>$channel];
    }

    /** @return array{status:int,json:array,headers:array,raw:string} */
    private function request(string $method,string $path,?array $body=null,?array $auth=null): array
    {
        $headers=[];
        $curl=$this->handle($method,$path,$body,$auth);
        curl_setopt($curl,CURLOPT_HEADERFUNCTION,static function($curl,string $line) use (&$headers): int {
            if (str_contains($line,':')) {
                [$key,$value]=explode(':',$line,2);
                $headers[strtolower(trim($key))]=trim($value);
            }
            return strlen($line);
        });
        $raw=curl_exec($curl);
        self::assertIsString($raw,curl_error($curl));
        $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $json=json_decode($raw,true);
        self::assertIsArray($json,$raw);
        return ['status'=>$status,'json'=>$json,'headers'=>$headers,'raw'=>$raw];
    }

    private function handle(string $method,string $path,?array $body=null,?array $auth=null): CurlHandle
    {
        $curl=curl_init(RestApiEnv::baseUrl().self::ROOT.$path);
        $headers=$auth ?? RestApiEnv::authHeaders();
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>40,CURLOPT_CONNECTTIMEOUT=>5]);
        if ($body!==null) {
            $headers[]='Content-Type: application/json';
            curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
        }
        curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
        return $curl;
    }

    private function assertError(array $result,int $status,string $code,?string $reason=null): void
    {
        self::assertSame($status,$result['status'],$result['raw']);
        self::assertSame($code,$result['json']['data']['error']);
        if ($reason!==null) {
            self::assertSame($reason,$result['json']['data']['details']['reason']);
        }
    }

    public function testPreviewEqualsPersistedCreateAndLeavesNoRows(): void
    {
        $orders=RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrer ORDER BY id');
        $lines=RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrelinjer ORDER BY id');
        $posts=RestApiEnv::rows(self::$tenant,'SELECT * FROM openpost ORDER BY id');
        $preview=$this->request('GET','preview.php?konto_id='.$this->fixture['konto_id']);
        self::assertSame(200,$preview['status'],$preview['raw']);
        self::assertSame($orders,RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrer ORDER BY id'));
        self::assertSame($lines,RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrelinjer ORDER BY id'));
        self::assertSame($posts,RestApiEnv::rows(self::$tenant,'SELECT * FROM openpost ORDER BY id'));
        self::assertSame(0,(int)RestApiEnv::rows(self::$tenant,'SELECT count(*) AS count FROM document_deliveries')[0]['count']);
        $p=$preview['json']['data'];
        $fixture=json_decode(file_get_contents(__DIR__.'/fixtures/t2/dunning-preview.json'),true,512,JSON_THROW_ON_ERROR)['data'];
        self::assertSame(array_keys($fixture),array_keys($p));
        self::assertSame(array_keys($fixture['items'][0]),array_keys($p['items'][0]));
        foreach (['fee','interest','total'] as $money) {
            self::assertMatchesRegularExpression('/^-?[0-9]+\.[0-9]{2}$/D',$p[$money]);
        }
        self::assertSame('100.00',$p['fee']);
        self::assertCount(2,$p['items']);
        self::assertSame($this->fixture['posts'],array_column($p['items'],'openpost_id'));
        $request=$this->body();
        $created=$this->request('POST','index.php',$request);
        self::assertSame(201,$created['status'],$created['raw']);
        $c=$created['json']['data'];
        $fixture=json_decode(file_get_contents(__DIR__.'/fixtures/t2/dunning-created.json'),true,512,JSON_THROW_ON_ERROR)['data'];
        self::assertSame(array_keys($fixture),array_keys($c));
        self::assertSame(array_keys($fixture['delivery']),array_keys($c['delivery']));
        self::assertSame($p['total'],$c['total']);
        self::assertSame($p['currency'],$c['currency']);
        self::assertSame($p['level'],$c['level']);
        self::assertSame('none',$c['delivery']['state']);
        $stored=RestApiEnv::rows(self::$tenant,'SELECT sum FROM ordrer WHERE id=$1',[$c['rykker_id']])[0];
        self::assertSame((float)$p['fee']+(float)$p['interest'],(float)$stored['sum']);
        $replay=$this->request('POST','index.php',$request);
        self::assertSame($created['json'],$replay['json']);
        self::assertSame('true',$replay['headers']['idempotent-replayed']);
        $request['deliver']='email';
        $this->assertError($this->request('POST','index.php',$request),409,'conflict','client_id_reused');
        $history=$this->request('GET','index.php?konto_id='.$this->fixture['konto_id'].'&limit=1');
        self::assertSame(200,$history['status'],$history['raw']);
        self::assertSame(1,$history['json']['data']['total']);
        self::assertSame($c['rykker_id'],$history['json']['data']['items'][0]['rykker_id']);
    }

    public function testR2PreviewRollsBackBaseHeaderAndMatchesCreatedLines(): void
    {
        $base=DunningFixture::base(self::$tenant,$this->fixture);
        $orders=RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrer ORDER BY id');
        $lines=RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrelinjer ORDER BY id');
        $preview=$this->request('GET','preview.php?konto_id='.$this->fixture['konto_id']);
        self::assertSame(200,$preview['status'],$preview['raw']);
        self::assertSame($orders,RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrer ORDER BY id'));
        self::assertSame($lines,RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrelinjer ORDER BY id'));
        $p=$preview['json']['data'];
        self::assertSame(2,$p['level']);
        self::assertSame($base,$p['previous_rykker_id']);
        self::assertCount(2,$p['items']);
        $created=$this->request('POST','index.php',$this->body(2));
        self::assertSame(201,$created['status'],$created['raw']);
        self::assertSame($p['total'],$created['json']['data']['total']);
        self::assertSame('91881-2',$created['json']['data']['rykker_fakturanr']);
        self::assertEquals(0,RestApiEnv::rows(self::$tenant,'SELECT sum FROM ordrer WHERE id=$1',[$created['json']['data']['rykker_id']])[0]['sum']);
    }

    public function testMixedCurrencyPreviewCreateAndHistoryMatchRenderedPdf(): void
    {
        $this->fixture=DunningFixture::mixedCurrency(self::$tenant);
        $orders=RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrer ORDER BY id');
        $lines=RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrelinjer ORDER BY id');
        $preview=$this->request('GET','preview.php?konto_id='.$this->fixture['konto_id']);
        self::assertSame(200,$preview['status'],$preview['raw']);
        self::assertSame($orders,RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrer ORDER BY id'));
        self::assertSame($lines,RestApiEnv::rows(self::$tenant,'SELECT * FROM ordrelinjer ORDER BY id'));
        $created=$this->request('POST','index.php',$this->body());
        self::assertSame(201,$created['status'],$created['raw']);
        $p=$preview['json']['data'];
        $c=$created['json']['data'];
        $user=RestApiEnv::rows(self::$tenant,'SELECT id FROM brugere WHERE brugernavn=$1',[RestApiEnv::user()])[0];
        $context=new EngineContext(self::$tenant,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),
            RestApiEnv::user(),(int)$user['id'],date('Y-m-d'));
        $uri=$_SERVER['REQUEST_URI'] ?? null;
        $remoteAddress=$_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REQUEST_URI']='/restapi/endpoints/v1/debitor/documents/pdf.php';
        $_SERVER['REMOTE_ADDR']='127.0.0.1';
        try {
            $pdf=(new DocumentService($context,'test/account/'.$user['id']))->renderPdf($c['rykker_id']);
        } finally {
            if ($uri===null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI']=$uri;
            }
            if ($remoteAddress===null) {
                unset($_SERVER['REMOTE_ADDR']);
            } else {
                $_SERVER['REMOTE_ADDR']=$remoteAddress;
            }
        }
        $text=shell_exec('/usr/bin/gs -q -dBATCH -dNOPAUSE -sDEVICE=txtwrite -o - '.escapeshellarg($pdf['pdf']));
        self::assertIsString($text);
        self::assertStringContainsString('641,64',$text);
        self::assertSame('EUR',$p['currency']);
        self::assertSame(['133.33','500.00','-5.98'],array_column($p['items'],'amount'));
        // R1's stored header/fee still use the legacy oldest rate, while PDF invoice conversions use the latest.
        self::assertSame(700.0,(float)RestApiEnv::rows(self::$tenant,'SELECT valutakurs FROM ordrer WHERE id=$1',[$c['rykker_id']])[0]['valutakurs']);
        self::assertSame('14.29',$p['fee']);
        self::assertSame('641.64',$p['total']);
        self::assertSame($p['total'],$c['total']);
        $history=$this->request('GET','index.php?konto_id='.$this->fixture['konto_id']);
        self::assertSame(200,$history['status'],$history['raw']);
        self::assertSame($p['total'],$history['json']['data']['items'][0]['total']);
    }

    public function testSettledReminderHistoriesAllowR1ForNewOverdueInvoice(): void
    {
        foreach ([1,2,3] as $level) {
            $this->fixture=DunningFixture::seed(self::$tenant);
            $old=DunningFixture::base(self::$tenant,$this->fixture,$level);
            RestApiEnv::rows(self::$tenant,"UPDATE openpost SET udlignet='1' WHERE konto_id=$1",[$this->fixture['konto_id']]);
            if ($level===1) {
                RestApiEnv::rows(self::$tenant,'UPDATE ordrer SET ordredate=CURRENT_DATE WHERE id=$1',[$old]);
            }
            $post=RestApiEnv::rows(self::$tenant,"INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,
                udlignet,transdate,forfaldsdate,valuta,valutakurs)
                VALUES($1,91881,'B2-new',1250,0,'New invoice','0',CURRENT_DATE-45,CURRENT_DATE-35,'DKK',100) RETURNING id",
                [$this->fixture['konto_id']])[0];
            $preview=$this->request('GET','preview.php?konto_id='.$this->fixture['konto_id']);
            self::assertSame(200,$preview['status'],$preview['raw']);
            $p=$preview['json']['data'];
            self::assertSame(1,$p['level']);
            self::assertNull($p['previous_rykker_id']);
            self::assertSame([(int)$post['id']],array_column($p['items'],'openpost_id'));
            $created=$this->request('POST','index.php',$this->body());
            self::assertSame(201,$created['status'],$created['raw']);
            self::assertSame(1,$created['json']['data']['level']);
            self::assertSame($p['total'],$created['json']['data']['total']);
            self::assertSame('-',RestApiEnv::rows(self::$tenant,'SELECT betalt FROM ordrer WHERE id=$1',[$old])[0]['betalt']);
        }
    }

    public function testSendReplaysWithoutLanguageMutationAndAfterWebDeletion(): void
    {
        $created=$this->request('POST','index.php',$this->body());
        self::assertSame(201,$created['status'],$created['raw']);
        $id=$created['json']['data']['rykker_id'];
        $send=['client_id'=>self::uuid(),'rykker_id'=>$id,'email'=>$this->fixture['email']];
        RestApiEnv::rows(self::$tenant,"UPDATE ordrer SET sprog='' WHERE id=$1",[$id]);
        $sent=$this->request('POST','send.php',$send);
        self::assertSame(200,$sent['status'],$sent['raw']);
        self::assertSame('sent',$sent['json']['data']['delivery']['state']);
        self::assertSame('dansk',RestApiEnv::rows(self::$tenant,'SELECT sprog FROM ordrer WHERE id=$1',[$id])[0]['sprog']);
        RestApiEnv::rows(self::$tenant,"UPDATE ordrer SET sprog='' WHERE id=$1",[$id]);
        $replay=$this->request('POST','send.php',$send);
        self::assertSame($sent['json'],$replay['json']);
        self::assertSame('',RestApiEnv::rows(self::$tenant,'SELECT sprog FROM ordrer WHERE id=$1',[$id])[0]['sprog']);
        // The web removes order/line rows but keeps durable deliveries and idempotency records.
        RestApiEnv::rows(self::$tenant,'DELETE FROM ordrelinjer WHERE ordre_id=$1',[$id]);
        RestApiEnv::rows(self::$tenant,'DELETE FROM ordrer WHERE id=$1',[$id]);
        $replay=$this->request('POST','send.php',$send);
        self::assertSame(200,$replay['status'],$replay['raw']);
        self::assertSame($sent['json'],$replay['json']);
        self::assertSame('true',$replay['headers']['idempotent-replayed']);
        self::assertSame(1,DocumentFixture::messages($this->fixture['email'])['total']);
        $send['email']='changed@example.invalid';
        $this->assertError($this->request('POST','send.php',$send),409,'conflict','client_id_reused');
        $send['client_id']=self::uuid();
        $this->assertError($this->request('POST','send.php',$send),404,'not_found');
    }

    public function testNoConfigTopLevelNothingToDunAndChangedLevel(): void
    {
        RestApiEnv::rows(self::$tenant,"DELETE FROM formularer WHERE beskrivelse='GEBYR' AND formular=6");
        $result=$this->request('GET','preview.php?konto_id='.$this->fixture['konto_id']);
        $this->assertError($result,422,'not_configured');
        self::assertSame('dunning_fee',$result['json']['data']['details']['what']);
        $this->fixture=DunningFixture::seed(self::$tenant);
        DunningFixture::base(self::$tenant,$this->fixture,3);
        $this->assertError($this->request('GET','preview.php?konto_id='.$this->fixture['konto_id']),422,'validation_failed','top_level_reached');
        $this->fixture=DunningFixture::seed(self::$tenant);
        RestApiEnv::rows(self::$tenant,"UPDATE openpost SET udlignet='1' WHERE konto_id=$1",[$this->fixture['konto_id']]);
        $this->assertError($this->request('GET','preview.php?konto_id='.$this->fixture['konto_id']),422,'validation_failed','nothing_to_dun');
        $this->fixture=DunningFixture::seed(self::$tenant);
        $this->assertError($this->request('POST','index.php',$this->body(2)),409,'conflict','level_changed');
        $this->assertError($this->request('GET','preview.php?konto_id=2147483647'),404,'not_found');
    }

    public function testInvalidMailRollsBackCreationAndDeliveryAndRetryRemainsAvailable(): void
    {
        $request=$this->body(1,'email');
        $request['email']='invalid';
        $this->assertError($this->request('POST','index.php',$request),422,'validation_failed','invalid_email');
        self::assertSame(0,(int)RestApiEnv::rows(self::$tenant,"SELECT count(*) AS count FROM ordrer WHERE konto_id=$1 AND art LIKE 'R%'",[$this->fixture['konto_id']])[0]['count']);
        self::assertSame(0,(int)RestApiEnv::rows(self::$tenant,'SELECT count(*) AS count FROM mobile_idempotency WHERE client_id=$1',[$request['client_id']])[0]['count']);
        $request['email']=$this->fixture['email'];
        self::assertSame(201,$this->request('POST','index.php',$request)['status']);
    }

    public function testCreateMailAndReplayThenResendAndReplayEachMailOnce(): void
    {
        $body=$this->body(1,'email');
        $first=$this->request('POST','index.php',$body);
        self::assertSame(201,$first['status'],$first['raw']);
        self::assertSame('sent',$first['json']['data']['delivery']['state'],$first['raw']);
        $replay=$this->request('POST','index.php',$body);
        self::assertSame($first['json'],$replay['json']);
        self::assertSame('true',$replay['headers']['idempotent-replayed']);
        $messages=DocumentFixture::messages($this->fixture['email']);
        self::assertSame(1,$messages['total']);
        self::assertSame(1,$messages['messages'][0]['Attachments']);
        $send=['client_id'=>self::uuid(),'rykker_id'=>$first['json']['data']['rykker_id']];
        $sent=$this->request('POST','send.php',$send);
        self::assertSame(200,$sent['status'],$sent['raw']);
        self::assertSame('sent',$sent['json']['data']['delivery']['state']);
        $replay=$this->request('POST','send.php',$send);
        self::assertSame($sent['json'],$replay['json']);
        self::assertSame('true',$replay['headers']['idempotent-replayed']);
        self::assertSame(2,DocumentFixture::messages($this->fixture['email'])['total']);
    }

    public function testSmtpFailureReturnsCreatedRykkerWithFailedDelivery(): void
    {
        RestApiEnv::rows(self::$tenant,"UPDATE adresser SET felt_1='127.0.0.1:1' WHERE art='S'");
        $result=$this->request('POST','index.php',$this->body(1,'email'));
        self::assertSame(201,$result['status'],$result['raw']);
        self::assertSame('failed',$result['json']['data']['delivery']['state']);
        self::assertSame('smtp_failed',$result['json']['data']['delivery']['error_reason']);
        self::assertCount(1,RestApiEnv::rows(self::$tenant,'SELECT id FROM ordrer WHERE id=$1',[$result['json']['data']['rykker_id']]));
    }

    public function testReadOnlyUserCanReadHistoryButCannotPreviewOrWrite(): void
    {
        $paths=[['GET','preview.php?konto_id='.$this->fixture['konto_id'],null],['POST','index.php',$this->body()],
            ['POST','send.php',['client_id'=>self::uuid(),'rykker_id'=>1]]];
        foreach ($paths as [$method,$path,$body]) {
            $this->assertError($this->request($method,$path,$body,[]),401,'invalid_token');
        }
        $digits=str_repeat('0',16);
        $digits[5]='2';
        RestApiEnv::rows(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[$digits,RestApiEnv::user()]);
        try {
            self::assertSame(200,$this->request('GET','index.php?konto_id='.$this->fixture['konto_id'])['status']);
            foreach ($paths as [$method,$path,$body]) {
                $this->assertError($this->request($method,$path,$body),403,'permission_denied');
            }
            RestApiEnv::rows(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[str_repeat('0',16),RestApiEnv::user()]);
            $this->assertError($this->request('GET','index.php?konto_id='.$this->fixture['konto_id']),403,'permission_denied');
        } finally {
            RestApiEnv::rows(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[str_repeat('1',16),RestApiEnv::user()]);
        }
    }

    public function testBothEngineFunctionsRefuseAutocommitAndFalseDepth(): void
    {
        $saved=$GLOBALS;
        $GLOBALS['connection']=self::$tenant;
        $GLOBALS['db_type']='postgresql';
        try {
            foreach (['dunning_create_for_account','dunning_advance'] as $function) {
                foreach ([0,1] as $depth) {
                    $GLOBALS['db_transaktion_depth']=$depth;
                    try {
                        $function($this->fixture['konto_id'],date('Y-m-d'));
                        self::fail('Accepted a transaction without an open PostgreSQL transaction');
                    } catch (LogicException $error) {
                        self::assertStringContainsString('transaction',$error->getMessage());
                    }
                }
            }
            self::assertSame(PGSQL_TRANSACTION_IDLE,pg_transaction_status(self::$tenant));
        } finally {
            foreach (array_diff_key($GLOBALS,$saved) as $key=>$unused) {
                unset($GLOBALS[$key]);
            }
            foreach ($saved as $key=>$value) {
                $GLOBALS[$key]=$value;
            }
        }
    }

    /** A separate PHP session for each concurrent web writer; never share other slices' session locks. @return string */
    private function webSession(): string
    {
        $session='b2'.bin2hex(random_bytes(15));
        $master=RestApiEnv::connect(RestApiEnv::masterDb());
        $year=RestApiEnv::rows(self::$tenant,"SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")[0]['year'];
        RestApiEnv::rows($master,"INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES($1,$2,$3,$4,$5,$6,$7,false,0)",
            [$session,RestApiEnv::user(),RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('9',50),$year,(string)time()]);
        pg_close($master);
        $this->sessions[]=$session;
        return $session;
    }

    /** Force both contenders to wait for the SAME database lock before releasing them. @return array<int,array> */
    private function race(array $writers): array
    {
        $webs=[];
        $curls=[];
        $multi=curl_multi_init();
        // Session rows are seeded before taking the lock, since login may perform schema checks.
        foreach ($writers as $index=>$writer) {
            if ($writer['kind']==='web') {
                $writers[$index]['session']=$this->webSession();
            }
        }
        RestApiEnv::rows(self::$tenant,'BEGIN');
        RestApiEnv::rows(self::$tenant,"SELECT pg_advisory_xact_lock(hashtext('saldi:dunning'))");
        try {
            foreach ($writers as $index=>$writer) {
                if ($writer['kind']==='web') {
                    $log=tempnam(sys_get_temp_dir(),'b2web');
                    $proc=proc_open([PHP_BINARY,__DIR__.'/../characterization/support/run_ny_rykker_page.php',
                        (string)$writer['konto_id'],(string)($writer['base'] ?? 0),$writer['session']],
                        [0=>['pipe','r'],1=>['file',$log,'w'],2=>['file',$log.'.err','w']],$pipes);
                    fclose($pipes[0]);
                    $webs[$index]=[$proc,$log];
                } else {
                    $curl=$this->handle('POST','index.php',$writer['body']);
                    curl_multi_add_handle($multi,$curl);
                    $curls[$index]=$curl;
                }
            }
            $deadline=microtime(true)+8;
            $waiting=0;
            do {
                curl_multi_exec($multi,$running);
                $waiting=(int)RestApiEnv::rows(self::$tenant,"SELECT count(*) AS count FROM pg_locks
                    WHERE locktype='advisory' AND NOT granted AND database=(SELECT oid FROM pg_database WHERE datname=current_database())")[0]['count'];
                if ($waiting>=count($writers)) {
                    break;
                }
                usleep(20000);
            } while (microtime(true)<$deadline);
            self::assertSame(count($writers),$waiting,'Both writers must contend for the shared dunning lock');
        } finally {
            RestApiEnv::rows(self::$tenant,'ROLLBACK');
        }
        $results=[];
        do {
            curl_multi_exec($multi,$running);
            if ($running) {
                curl_multi_select($multi,0.1);
            }
        } while ($running);
        foreach ($curls as $index=>$curl) {
            $raw=curl_multi_getcontent($curl);
            $results[$index]=['status'=>(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'json'=>json_decode($raw,true),'raw'=>$raw];
            curl_multi_remove_handle($multi,$curl);
            curl_close($curl);
        }
        curl_multi_close($multi);
        foreach ($webs as $index=>[$proc,$log]) {
            $exit=proc_close($proc);
            $raw=file_get_contents($log);
            $diagnostics=file_get_contents($log.'.err');
            unlink($log);
            unlink($log.'.err');
            self::assertSame(0,$exit,$diagnostics);
            self::assertStringContainsString('CHARTEST_PAGE_DONE',$raw,$diagnostics);
            $results[$index]=['status'=>0,'raw'=>$raw];
        }
        ksort($results);
        return $results;
    }

    public function testWebWebWebMobileAndMobileMobileGiveOneRykker(): void
    {
        foreach ([['web','web'],['web','mobile'],['mobile','mobile']] as $kinds) {
            $this->fixture=DunningFixture::seed(self::$tenant);
            $writers=[];
            foreach ($kinds as $kind) {
                $writers[]=['kind'=>$kind,'konto_id'=>$this->fixture['konto_id'],'body'=>$this->body()];
            }
            $results=$this->race($writers);
            $rows=RestApiEnv::rows(self::$tenant,"SELECT id FROM ordrer WHERE konto_id=$1 AND art LIKE 'R%'",[$this->fixture['konto_id']]);
            self::assertCount(1,$rows,implode('/', $kinds));
            foreach ($results as $index=>$result) {
                if ($kinds[$index]==='mobile') {
                    self::assertContains($result['status'],[201,409],$result['raw']);
                }
            }
        }
    }

    public function testDifferentCustomersReceiveDistinctRykkerNumbers(): void
    {
        $first=$this->fixture;
        $second=DunningFixture::seed(self::$tenant);
        $body1=$this->body();
        $this->fixture=$second;
        $body2=$this->body();
        $results=$this->race([['kind'=>'mobile','body'=>$body1],['kind'=>'mobile','body'=>$body2]]);
        foreach ($results as $result) {
            self::assertSame(201,$result['status'],$result['raw']);
        }
        $numbers=RestApiEnv::rows(self::$tenant,"SELECT DISTINCT ordrenr FROM ordrer WHERE konto_id IN ($1,$2) AND art='R1'",[$first['konto_id'],$second['konto_id']]);
        self::assertCount(2,$numbers);
    }

    public function testConcurrentAdvancesOfOneWebRykkerHaveOneSuccessor(): void
    {
        $base=DunningFixture::base(self::$tenant,$this->fixture);
        $this->race([['kind'=>'web','konto_id'=>$this->fixture['konto_id'],'base'=>$base],
            ['kind'=>'mobile','body'=>$this->body(2)]]);
        self::assertCount(1,RestApiEnv::rows(self::$tenant,"SELECT id FROM ordrer WHERE konto_id=$1 AND art='R2'",[$this->fixture['konto_id']]));
    }

    public function testConcurrentReplayCreatesAndMailsOnce(): void
    {
        $body=$this->body(1,'email');
        $results=$this->race([['kind'=>'mobile','body'=>$body],['kind'=>'mobile','body'=>$body]]);
        $successes=0;
        foreach ($results as $result) {
            self::assertContains($result['status'],[201,409],$result['raw']);
            if ($result['status']===201) {
                $successes++;
            } else {
                self::assertSame('in_progress',$result['json']['data']['details']['reason']);
            }
        }
        self::assertGreaterThanOrEqual(1,$successes);
        self::assertCount(1,RestApiEnv::rows(self::$tenant,"SELECT id FROM ordrer WHERE konto_id=$1 AND art='R1'",[$this->fixture['konto_id']]));
        self::assertSame(1,DocumentFixture::messages($this->fixture['email'])['total']);
        self::assertSame(201,$this->request('POST','index.php',$body)['status']);
        self::assertSame(1,DocumentFixture::messages($this->fixture['email'])['total']);
    }

    public function testSendingReplayIsInProgressThenUnknownWithoutAnotherMailOrRykker(): void
    {
        $body=$this->body(1,'email');
        $created=$this->request('POST','index.php',$body);
        self::assertSame(201,$created['status'],$created['raw']);
        $delivery=$created['json']['data']['delivery']['id'];
        $envelope=$created['json'];
        $envelope['data']['delivery']['state']='sending';
        RestApiEnv::rows(self::$tenant,"UPDATE document_deliveries SET state='sending',created_at=now(),finished_at=NULL WHERE id=$1",[$delivery]);
        RestApiEnv::rows(self::$tenant,'UPDATE mobile_idempotency SET response_json=$1 WHERE client_id=$2',
            [json_encode($envelope,JSON_THROW_ON_ERROR),$body['client_id']]);
        $this->assertError($this->request('POST','index.php',$body),409,'conflict','in_progress');
        RestApiEnv::rows(self::$tenant,"UPDATE document_deliveries SET created_at=now()-interval '11 minutes' WHERE id=$1",[$delivery]);
        $unknown=$this->request('POST','index.php',$body);
        self::assertSame(201,$unknown['status'],$unknown['raw']);
        self::assertSame('unknown',$unknown['json']['data']['delivery']['state']);
        self::assertSame('true',$unknown['headers']['idempotent-replayed']);
        self::assertSame(1,DocumentFixture::messages($this->fixture['email'])['total']);
        self::assertCount(1,RestApiEnv::rows(self::$tenant,"SELECT id FROM ordrer WHERE konto_id=$1 AND art='R1'",[$this->fixture['konto_id']]));
    }

    public function testAlleUsesTheSharedSettlementAllocator(): void
    {
        $source=file_get_contents(__DIR__.'/../../debitor/ny_rykker.php');
        self::assertStringContainsString('udlign_next_id()',$source);
        self::assertDoesNotMatchRegularExpression('/max\s*\(\s*udlign_id\s*\)/i',$source);
    }
}
