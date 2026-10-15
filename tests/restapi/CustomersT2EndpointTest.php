<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/CustomersT2EndpointTest.php --- ver 5.0.0 --- 2026.10.03
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
// 20261001 CDX/LH Cover primary web races, fiscal defaults, primary email and tenant CVR isolation.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/services/CustomerDirectoryService.php';
require_once __DIR__ . '/../../restapi/core/MobileAuth.php';
require_once __DIR__ . '/../fixtures/cvr/cvrapi_stub.php';
require_once __DIR__ . '/fixtures/t2/SalesFixture.php';

final class CustomersT2EndpointTest extends TestCase
{
    private static $conn;
    private static $master;
    private static array $headers;
    private static string $subject;
    private static string $bucket;
    private static string $webSession;
    private static string $salesLog;
    private static int $salesLogMode;
    private static array $servers = [];
    private static string $cvrBase;
    private const BASE = '/restapi/endpoints/v1/';

    public static function setUpBeforeClass(): void {
        if (RestApiEnv::unavailableReason() !== null) { return; }
        RestApiEnv::bootstrapTenant();
        self::$conn = RestApiEnv::connect(RestApiEnv::testDb());
        self::$master = RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows(self::$conn,'ALTER TABLE adresser ADD COLUMN IF NOT EXISTS stripe_fravalg text');
        self::$headers = RestApiEnv::authHeaders();
        // Earlier CLI engine tests can leave the per-process tenant log owned by root.
        // This disposable fixture must be writable by both CLI and the HTTP service user.
        self::$salesLog=dirname(__DIR__,2).'/temp/'.RestApiEnv::testDb().'/momslog.log';
        if (!file_exists(self::$salesLog) && !touch(self::$salesLog)) {
            throw new RuntimeException('Cannot prepare the disposable sales log');
        }
        self::$salesLogMode=fileperms(self::$salesLog) & 0777;
        if (!chmod(self::$salesLog,0666)) { throw new RuntimeException('Cannot share the disposable sales log'); }
        $token = RestApiEnv::loginData()['access_token'];
        JWT::setSecret(_jwtLoadSecret(JWT::secretPath()));
        self::$subject = JWT::decode($token)['sub'];
        self::$bucket = CvrDirectoryService::bucketKey(self::$subject,RestApiEnv::regnskabId(RestApiEnv::accountOpen()));
        self::$webSession = 't2b3web'.bin2hex(random_bytes(8));
        $context = new EngineContext(self::$conn,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RestApiEnv::user(),(int)JWT::decode($token)['user_id'],OpenPostsReader::today());
        RestApiEnv::rows(self::$master,"INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES ($1,$2,$3,$4,$5,$6,$7,false,0)",[self::$webSession,RestApiEnv::user(),RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('1',32),$context->regnaar,(string)time()]);
        // CVR HTTP tests run the actual endpoint on a private PHP server with a local provider.
        $stubPort = self::serverPort();
        $apiPort = self::serverPort();
        $root = dirname(__DIR__,2);
        $environment = getenv();
        self::$servers[] = self::server([PHP_BINARY,'-S','127.0.0.1:'.$stubPort,$root.'/tests/fixtures/cvr/cvrapi_stub.php'],$environment);
        $environment['SALDI_CVR_API_URL'] = 'http://127.0.0.1:'.$stubPort.'/api';
        self::$servers[] = self::server([PHP_BINARY,'-S','127.0.0.1:'.$apiPort,'-t',$root],$environment);
        self::$cvrBase = 'http://127.0.0.1:'.$apiPort;
        for ($attempt=0;$attempt<100;$attempt++) {
            $ch=curl_init(self::$cvrBase.'/restapi/endpoints/v1/auth/login.php');
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>1]);
            curl_exec($ch);
            $ready=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE)>0;
            curl_close($ch);
            if ($ready) { break; }
            usleep(20000);
        }
        if (!$ready) { throw new RuntimeException('Local CVR test server unavailable'); }
    }
    private static function serverPort(): int {
        $socket=stream_socket_server('tcp://127.0.0.1:0',$code,$message);
        if (!$socket) { throw new RuntimeException('Local test port unavailable'); }
        $address=stream_socket_get_name($socket,false);
        fclose($socket);
        return (int)substr($address,strrpos($address,':')+1);
    }
    private static function server(array $command, array $environment) {
        $log=tempnam(sys_get_temp_dir(),'t2b3-server-');
        $process=proc_open($command,[0=>['file','/dev/null','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,null,$environment);
        if (!is_resource($process)) { throw new RuntimeException('Local test server could not start'); }
        return [$process,$log];
    }
    protected function setUp(): void {
        if (!self::$conn) { $this->markTestSkipped(RestApiEnv::unavailableReason()); }
        self::sql("DELETE FROM openpost");
        self::sql("DELETE FROM ordrer");
        self::sql("DELETE FROM ansatte WHERE konto_id IN (SELECT id FROM adresser WHERE art='D')");
        self::sql("DELETE FROM kontakt_emails WHERE konto_id IN (SELECT id FROM adresser WHERE art IN ('D','K'))");
        self::sql("DELETE FROM adresser WHERE art IN ('D','K')");
        self::sql("DELETE FROM mobile_idempotency");
        self::sql("UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2",[str_repeat('1',32),RestApiEnv::user()]);
        RestApiEnv::rows(self::$master,"DELETE FROM mobile_rate_buckets WHERE scope='cvr' AND bucket_key=$1",[self::$bucket]);
    }
    public static function tearDownAfterClass(): void {
        if (isset(self::$salesLog) && file_exists(self::$salesLog)) { chmod(self::$salesLog,self::$salesLogMode); }
        foreach (self::$servers as [$process,$log]) { proc_terminate($process); proc_close($process); unlink($log); }
        if (self::$master) {
            RestApiEnv::rows(self::$master,'DELETE FROM online WHERE session_id=$1',[self::$webSession]);
            RestApiEnv::rows(self::$master,"DELETE FROM mobile_rate_buckets WHERE scope='cvr' AND bucket_key=$1",[self::$bucket]);
            RestApiEnv::rows(self::$master,"DELETE FROM cvr_cache WHERE cvr IN ('12345678','23456789','00000000','99999999','88888888')");
            pg_close(self::$master);
        }
        if (self::$conn) { pg_close(self::$conn); }
        RestApiEnv::teardownTenant();
    }
    private static function sql(string $sql, array $parameters=[]): array { return RestApiEnv::rows(self::$conn,$sql,$parameters); }
    private static function uuid(): string { return MobileAuth::uuid(); }
    private function request(string $method,string $path,?array $body=null,array $extra=[]): array {
        $ch=curl_init(RestApiEnv::baseUrl().self::BASE.$path);
        $responseHeaders=[];
        $headers=array_merge(self::$headers,$extra,['Accept: application/json']);
        $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>30,
            CURLOPT_HEADERFUNCTION=>static function ($unused,$line) use (&$responseHeaders) {
                $responseHeaders[]=trim($line);return strlen($line);
            }];
        if ($body!==null) { $options[CURLOPT_POSTFIELDS]=json_encode($body);$headers[]='Content-Type: application/json'; }
        $options[CURLOPT_HTTPHEADER]=$headers;
        curl_setopt_array($ch,$options);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        return ['status'=>$status,'json'=>json_decode($raw,true),'body'=>$raw,'headers'=>$responseHeaders];
    }
    private function create(array $fields=[]): array {
        $response=$this->request('POST','debitor/customers/create.php',array_replace(['client_id'=>self::uuid(),'name'=>'Hansen ApS'],$fields));
        self::assertSame(201,$response['status'],$response['body']);
        return $response['json']['data'];
    }
    private function cvr(string $number,array $headers=null): array {
        $ch=curl_init(self::$cvrBase.self::BASE.'cvr/lookup.php?cvr='.urlencode($number));
        $responseHeaders=[];
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers ?? self::$headers,CURLOPT_TIMEOUT=>15,
            CURLOPT_HEADERFUNCTION=>static function ($unused,$line) use (&$responseHeaders) {
                $responseHeaders[]=trim($line); return strlen($line);
            }]);
        $body=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status'=>$status,'json'=>json_decode($body,true),'body'=>$body,'headers'=>$responseHeaders];
    }

    public function testCreatesFirstFreeNumberAndSharesFullCardDefaultsWithWeb(): void {
        self::sql("INSERT INTO adresser(art,kontonr) VALUES ('D','1000'),('D','1002'),('K','1001')");
        $created=$this->create(['phone'=>'12345678','email'=>'rest@example.invalid','cvr'=>'12345678']);
        self::assertSame('1001',$created['kontonr']);
        $row=self::sql('SELECT * FROM adresser WHERE id=$1',[$created['id']])[0];
        self::assertSame('1',$row['gruppe']);
        self::assertSame('8',$row['betalingsdage']);
        self::assertSame('Netto',$row['betalingsbet']);
        foreach (['kreditmax','kontoansvarlig','rabatgruppe','status'] as $key) { self::assertEquals(0,$row[$key],$key); }
        // Invoke the same web include through its isolated card runner against this tenant.
        $fields=['firmanavn'=>"O'Connor web",'submit'=>'Gem','gruppe'=>'1: Debitor','betalingsbet'=>'Netto','betalingsdage'=>'8'];
        $process=proc_open([PHP_BINARY,__DIR__.'/../characterization/support/run_debkort_save.php',RestApiEnv::testDb(),json_encode($fields)],
            [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        self::assertSame(0,proc_close($process),$stderr);
        self::assertStringContainsString('CHARTEST_CARD_ID=',$stdout);
        $web=self::sql("SELECT * FROM adresser WHERE firmanavn=$1",["O'Connor web"])[0];
        self::assertSame('1003',$web['kontonr']);
        foreach (['gruppe','betalingsdage','kreditmax','kontoansvarlig','rabatgruppe','status','betalingsbet'] as $key) { self::assertSame($row[$key],$web[$key]); }
    }

    /** @return array{process:resource,stdout:string,stderr:string} Actual primary card, with an authenticated private web session. */
    private function startWeb(array $post, array $get=[]): array {
        $stdout=tempnam(sys_get_temp_dir(),'t2b3-web-out-');
        $stderr=tempnam(sys_get_temp_dir(),'t2b3-web-err-');
        $process=proc_open([PHP_BINARY,__DIR__.'/../characterization/support/run_debitorkort_page.php',
            json_encode(['get'=>$get,'post'=>$post]),self::$webSession],
            [0=>['file','/dev/null','r'],1=>['file',$stdout,'w'],2=>['file',$stderr,'w']],$pipes);
        if (!is_resource($process)) { throw new RuntimeException('Primary card runner unavailable'); }
        return ['process'=>$process,'stdout'=>$stdout,'stderr'=>$stderr];
    }
    /** @return string Primary web output after a successful page process. */
    private function finishWeb(array $run): string {
        $exit=proc_close($run['process']);
        $stdout=file_get_contents($run['stdout']);$stderr=file_get_contents($run['stderr']);
        unlink($run['stdout']);unlink($run['stderr']);
        self::assertSame(0,$exit,$stderr.' '.$stdout);
        return $stdout;
    }
    /** @return void Observe real database contention, rather than relying on timing alone. */
    private function waitForAllocationLock(int $key, $multi=null): void {
        $deadline=microtime(true)+10;
        do {
            if ($multi !== null) { curl_multi_exec($multi,$running); }
            $waiters=self::sql("SELECT pid FROM pg_locks WHERE locktype='advisory' AND objid=$1 AND NOT granted
                AND database=(SELECT oid FROM pg_database WHERE datname=current_database())",[$key]);
            if ($waiters) { return; }
            usleep(20000);
        } while (microtime(true)<$deadline);
        self::fail('No waiter on customer test lock '.$key);
    }

    public function testPrimaryWebCreateWaitsForUncommittedRestCreate(): void {
        self::sql("CREATE FUNCTION t2b3_hold_create() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
            IF NEW.firmanavn='REST held customer' THEN PERFORM pg_advisory_xact_lock(5130514); END IF;
            RETURN NEW; END $$");
        self::sql('CREATE TRIGGER t2b3_hold_create AFTER INSERT ON adresser FOR EACH ROW EXECUTE FUNCTION t2b3_hold_create()');
        self::sql('SELECT pg_advisory_lock(5130514)');
        $multi=curl_multi_init();
        $ch=curl_init(RestApiEnv::baseUrl().self::BASE.'debitor/customers/create.php');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['client_id'=>self::uuid(),'name'=>'REST held customer']),
            CURLOPT_HTTPHEADER=>array_merge(self::$headers,['Content-Type: application/json']),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
        curl_multi_add_handle($multi,$ch);
        $web=null;
        try {
            $this->waitForAllocationLock(5130514,$multi);
            self::assertSame([],self::sql("SELECT id FROM adresser WHERE art='D'"),'REST row is still uncommitted');
            $web=$this->startWeb(['id'=>0,'firmanavn'=>"Concurrent O'Connor web",'submit'=>'Gem','gruppe'=>'1: Debitor',
                'betalingsbet'=>'Netto','betalingsdage'=>'8','swift'=>'TESTDKKK','productlimit'=>'13',
                'kontakt_email_val'=>[1=>'web@example.invalid']]);
            $this->waitForAllocationLock(5130513);
            self::sql('SELECT pg_advisory_unlock(5130514)');
            do { curl_multi_exec($multi,$running); if ($running) { curl_multi_select($multi,1); } } while ($running);
            $raw=curl_multi_getcontent($ch);
            self::assertSame(201,(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),$raw);
            $created=json_decode($raw,true)['data'];
            $output=$this->finishWeb($web);$web=null;
            $rows=self::sql("SELECT id,kontonr,firmanavn,swift,productlimit FROM adresser WHERE art='D' ORDER BY kontonr");
            self::assertSame(['1000','1001'],array_column($rows,'kontonr'));
            self::assertSame((int)$rows[0]['id'],$created['id']);
            self::assertSame('REST held customer',$rows[0]['firmanavn']);
            self::assertSame("Concurrent O'Connor web",$rows[1]['firmanavn']);
            self::assertSame('TESTDKKK',$rows[1]['swift']);
            self::assertSame('13',$rows[1]['productlimit']);
            self::assertStringContainsString('id='.$rows[1]['id'],$output);
        } finally {
            self::sql('SELECT pg_advisory_unlock(5130514)');
            if ($web !== null) { proc_terminate($web['process']);$this->finishWeb($web); }
            curl_multi_remove_handle($multi,$ch);curl_close($ch);curl_multi_close($multi);
            self::sql('DROP TRIGGER t2b3_hold_create ON adresser');
            self::sql('DROP FUNCTION t2b3_hold_create()');
        }
    }

    public function testDefaultCustomerCanCreateSalesAndGroupsAreFiscalYearScoped(): void {
        $fixture=SalesFixture::create(self::$conn,[RestApiEnv::class,'rows']);
        self::sql("DELETE FROM grupper WHERE art='DG' AND kodenr=0");
        $customer=$this->create();
        $detail=$this->request('GET','debitor/customers/detail.php?id='.$customer['id'])['json']['data'];
        self::assertSame(1,$detail['group']);
        self::assertSame(['type'=>'Netto','days'=>8],$detail['payment_terms']);
        $body=['client_id'=>self::uuid(),'konto_id'=>$customer['id'],'kind'=>'tilbud',
            'lines'=>[['vare_id'=>$fixture['product_id'],'quantity'=>'1','unit_price'=>'100']]];
        $sale=$this->request('POST','debitor/sales/index.php',$body);
        self::assertSame(201,$sale['status'],$sale['body']);
        $header=self::sql('SELECT gruppe,betalingsbet,betalingsdage FROM adresser WHERE id=$1',[$customer['id']])[0];
        $order=self::sql('SELECT betalingsbet,betalingsdage FROM ordrer WHERE id=$1',[$sale['json']['data']['id']])[0];
        self::assertSame($header['betalingsbet'],$order['betalingsbet']);
        self::assertSame($header['betalingsdage'],$order['betalingsdage']);
        self::sql("INSERT INTO grupper(art,kodenr,fiscal_year) VALUES ('DG',7862,$1)",[$fixture['year']+1]);
        foreach ([0,7862] as $group) {
            $bad=$this->request('POST','debitor/customers/create.php',['client_id'=>self::uuid(),'name'=>'Invalid group','group'=>$group]);
            self::assertSame(422,$bad['status'],$bad['body']);
            self::assertSame('invalid_group',$bad['json']['data']['details']['reason']);
        }
        $explicit=$this->create(['group'=>7861]);
        self::assertSame('7861',self::sql('SELECT gruppe FROM adresser WHERE id=$1',[$explicit['id']])[0]['gruppe']);
    }

    public function testEmailPatchSynchronizesWebPrimaryAndSaleRecipientPreservingExtras(): void {
        $output=$this->finishWeb($this->startWeb(['id'=>0,'firmanavn'=>'Web email customer','submit'=>'Gem',
            'gruppe'=>'1: Debitor','betalingsbet'=>'Netto','betalingsdage'=>'8',
            'kontakt_email_val'=>[1=>'old@example.invalid'],'kontakt_email_type'=>[1=>'hoved'],
            'kontakt_emails_json'=>json_encode([['email'=>'extra@example.invalid','type'=>'ordre']])]));
        $id=(int)self::sql("SELECT id FROM adresser WHERE art='D'")[0]['id'];
        self::assertStringContainsString('id='.$id,$output);
        $before=self::sql('SELECT * FROM kontakt_emails WHERE konto_id=$1 ORDER BY id',[$id]);
        self::assertCount(2,$before);
        $detail=$this->request('GET','debitor/customers/detail.php?id='.$id)['json']['data'];
        $patch=['client_id'=>self::uuid(),'email'=>'new@example.invalid'];
        $updated=$this->request('PATCH','debitor/customers/update.php?id='.$id,$patch,['If-Match: '.$detail['etag']]);
        self::assertSame(200,$updated['status'],$updated['body']);
        self::assertSame('new@example.invalid',$updated['json']['data']['email']);
        $after=self::sql('SELECT * FROM kontakt_emails WHERE konto_id=$1 ORDER BY id',[$id]);
        self::assertSame('new@example.invalid',$after[0]['email']);
        self::assertSame($before[0]['id'],$after[0]['id']);
        self::assertSame($before[0]['email_type'],$after[0]['email_type']);
        self::assertSame($before[1],$after[1]);
        require_once __DIR__.'/../../includes/orders/orderHeader.php';
        $token=JWT::decode(RestApiEnv::loginData()['access_token']);
        $context=new EngineContext(self::$conn,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RestApiEnv::user(),(int)$token['user_id'],OpenPostsReader::today());
        $requestUri=ifset($_SERVER,'REQUEST_URI');
        $_SERVER['REQUEST_URI']='/saldi/debitor/debitorkort.php';
        try {
            LegacyEngine::run($context,function () use ($id): void {
                require_once __DIR__.'/../../includes/stdFunc/getKontaktEmail.php';
                self::assertSame('new@example.invalid',getKontaktEmail($id,'faktura'));
                self::assertSame('extra@example.invalid',getKontaktEmail($id,'ordre'));
                $saleId=order_create_header($id,0,[],false);
                self::assertSame('new@example.invalid',self::sql('SELECT email FROM ordrer WHERE id=$1',[$saleId])[0]['email']);
            });
        } finally {
            if ($requestUri === null) { unset($_SERVER['REQUEST_URI']); } else { $_SERVER['REQUEST_URI']=$requestUri; }
        }
        $card=$this->finishWeb($this->startWeb([],['id'=>$id]));
        self::assertStringContainsString('new@example.invalid',$card);
        self::assertStringNotContainsString('old@example.invalid',$card);
        $replay=$this->request('PATCH','debitor/customers/update.php?id='.$id,$patch,['If-Match: '.$detail['etag']]);
        self::assertSame($updated['json'],$replay['json']);
        self::assertSame($after,self::sql('SELECT * FROM kontakt_emails WHERE konto_id=$1 ORDER BY id',[$id]));
        $stale=$this->request('PATCH','debitor/customers/update.php?id='.$id,['client_id'=>self::uuid(),'email'=>'stale@example.invalid'],['If-Match: '.$detail['etag']]);
        self::assertSame(412,$stale['status']);
        self::assertSame($after,self::sql('SELECT * FROM kontakt_emails WHERE konto_id=$1 ORDER BY id',[$id]));
        $current=$this->request('GET','debitor/customers/detail.php?id='.$id)['json']['data'];
        $cleared=$this->request('PATCH','debitor/customers/update.php?id='.$id,['client_id'=>self::uuid(),'email'=>''],['If-Match: '.$current['etag']]);
        self::assertSame(200,$cleared['status'],$cleared['body']);
        self::assertSame('extra@example.invalid',$cleared['json']['data']['email']);
        self::assertSame([$before[1]],self::sql('SELECT * FROM kontakt_emails WHERE konto_id=$1 ORDER BY id',[$id]));
    }

    public function testEmailPatchInsertsPrimaryWhenLegacyCustomerHasNone(): void {
        $customer=$this->create();$id=$customer['id'];
        $detail=$this->request('GET','debitor/customers/detail.php?id='.$id)['json']['data'];
        $updated=$this->request('PATCH','debitor/customers/update.php?id='.$id,['client_id'=>self::uuid(),'email'=>'inserted@example.invalid'],['If-Match: '.$detail['etag']]);
        self::assertSame(200,$updated['status'],$updated['body']);
        self::assertSame([['email'=>'inserted@example.invalid','email_type'=>'hoved']],self::sql('SELECT email,email_type FROM kontakt_emails WHERE konto_id=$1',[$id]));
    }

    public function testTenParallelCreatesHaveDistinctNumbersAndReplaysDoNotInsert(): void {
        $multi=curl_multi_init();$requests=[];$bodies=[];
        for ($index=0;$index<10;$index++) {
            $body=['client_id'=>self::uuid(),'name'=>'Parallel '.$index];$bodies[]=$body;
            $ch=curl_init(RestApiEnv::baseUrl().self::BASE.'debitor/customers/create.php');
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>array_merge(self::$headers,['Content-Type: application/json']),
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
            curl_multi_add_handle($multi,$ch);$requests[]=$ch;
        }
        do { $code=curl_multi_exec($multi,$running); if ($running) { curl_multi_select($multi,1); } } while ($running && $code===CURLM_OK);
        $numbers=[];
        foreach ($requests as $ch) {
            $raw=curl_multi_getcontent($ch);
            self::assertSame(201,(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),$raw);
            $numbers[]=json_decode($raw,true)['data']['kontonr'];
            curl_multi_remove_handle($multi,$ch);curl_close($ch);
        }
        curl_multi_close($multi);
        sort($numbers,SORT_NUMERIC);
        self::assertSame(array_map('strval',range(1000,1009)),$numbers);
        $replay=$this->request('POST','debitor/customers/create.php',$bodies[0]);
        self::assertSame(201,$replay['status'],$replay['body']);
        self::assertContains('Idempotent-Replayed: true',$replay['headers']);
        self::assertCount(10,self::sql("SELECT id FROM adresser WHERE art='D'"));
        $reused=$this->request('POST','debitor/customers/create.php',array_replace($bodies[0],['name'=>'Changed']));
        self::assertSame(409,$reused['status']);
        self::assertSame('client_id_reused',$reused['json']['data']['details']['reason']);
    }

    public function testSearchFieldsSafeWildcardsAndSaldoReconcileToB1(): void {
        $customer=$this->create(['name'=>"Århus O'Connor_100%",'phone'=>'12345678','email'=>'lookup@example.invalid','cvr'=>'12345678','postnr'=>'8000','city'=>'Aarhus']);
        $id=$customer['id'];
        self::sql("INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet) VALUES ($1,$2,'100',200,CURRENT_DATE-30,CURRENT_DATE-15,'DKK',100,'0'),($1,$2,'100',-50,CURRENT_DATE-5,CURRENT_DATE-5,'DKK',100,'0')",[$id,$customer['kontonr']]);
        foreach (["århus","O'Connor",'12345678','lookup@',$customer['kontonr'],'100%','_100','År*100%'] as $q) {
            $response=$this->request('GET','debitor/customers/search.php?q='.urlencode($q));
            self::assertSame(200,$response['status'],$response['body']);
            self::assertSame(1,$response['json']['data']['total']);
            self::assertSame(openposts_reader(self::$conn)->saldo([$id])[$id],$response['json']['data']['items'][0]['saldo']);
        }
        $injection=$this->request('GET','debitor/customers/search.php?q='.urlencode("' OR 1=1 --"));
        self::assertSame(0,$injection['json']['data']['total']);
        $detail=$this->request('GET','debitor/customers/detail.php?id='.$id);
        self::assertSame('150.00',$detail['json']['data']['saldo']);
        self::assertSame('150.00',$detail['json']['data']['overdue_total']);
        self::assertSame(1,$detail['json']['data']['open_posts_count']);
        self::assertCount(1,$detail['json']['data']['open_posts']);
    }

    public function testEtagUpdateReplayStaleAndRestrictedFieldValidation(): void {
        $customer=$this->create();$id=$customer['id'];
        $detail=$this->request('GET','debitor/customers/detail.php?id='.$id)['json']['data'];
        $body=['client_id'=>self::uuid(),'phone'=>'87654321','addr1'=>"O'Connor vej 1"];
        $missing=$this->request('PATCH','debitor/customers/update.php?id='.$id,$body);
        self::assertSame(422,$missing['status']);
        self::assertSame('if_match_required',$missing['json']['data']['details']['reason']);
        $updated=$this->request('PATCH','debitor/customers/update.php?id='.$id,$body,['If-Match: '.$detail['etag']]);
        self::assertSame(200,$updated['status'],$updated['body']);
        self::assertNotSame($detail['etag'],$updated['json']['data']['etag']);
        self::assertSame("O'Connor vej 1",$updated['json']['data']['addr1']);
        $replay=$this->request('PATCH','debitor/customers/update.php?id='.$id,$body,['If-Match: '.$detail['etag']]);
        self::assertSame($updated['json'],$replay['json']);
        self::assertContains('Idempotent-Replayed: true',$replay['headers']);
        self::assertContains('ETag: '.$updated['json']['data']['etag'],$updated['headers']);
        $stale=$this->request('PATCH','debitor/customers/update.php?id='.$id,array_replace($body,['client_id'=>self::uuid()]),['If-Match: '.$detail['etag']]);
        self::assertSame(412,$stale['status']);
        self::assertSame('precondition_failed',$stale['json']['data']['error']);
        $forbidden=$this->request('PATCH','debitor/customers/update.php?id='.$id,['client_id'=>self::uuid(),'name'=>'Changed'],['If-Match: '.$updated['json']['data']['etag']]);
        self::assertSame(422,$forbidden['status']);
        self::assertSame('Hansen ApS',self::sql('SELECT firmanavn FROM adresser WHERE id=$1',[$id])[0]['firmanavn']);
    }

    public function testSellerChooserReturnsOnlyItsAllowedFields(): void {
        $customer=$this->create();
        $rights=str_repeat('0',32);$rights[5]='1';
        self::sql('UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[$rights,RestApiEnv::user()]);
        $expected=['id','kontonr','name','postnr','city','email','ean','payment_terms'];
        $list=$this->request('GET','debitor/sales/customers.php?q=Hansen');
        self::assertSame(200,$list['status'],$list['body']);
        self::assertSame($expected,array_keys($list['json']['data']['items'][0]));
        $detail=$this->request('GET','debitor/sales/customers.php?id='.$customer['id']);
        self::assertSame(200,$detail['status'],$detail['body']);
        self::assertSame($expected,array_keys($detail['json']['data']));
        foreach (['debitor/customers/search.php','debitor/customers/detail.php?id='.$customer['id'],'cvr/lookup.php?cvr=12345678'] as $path) {
            self::assertSame(403,$this->request('GET',$path)['status']);
        }
    }

    public function testCvrHttpCacheExpiryNotFoundMalformedUpstreamAndRateLimit(): void {
        RestApiEnv::rows(self::$master,"DELETE FROM cvr_cache WHERE cvr='12345678'");
        $first=$this->cvr('12345678');
        self::assertSame(200,$first['status'],$first['body']);
        self::assertSame('Stub Hansen ApS',$first['json']['data']['name']);
        $cached=$first['json']['data'];$cached['name']='Cached company';
        RestApiEnv::rows(self::$master,"UPDATE cvr_cache SET payload=$1 WHERE cvr='12345678'",[json_encode($cached)]);
        self::assertSame('Cached company',$this->cvr('12345678')['json']['data']['name']);
        RestApiEnv::rows(self::$master,"UPDATE cvr_cache SET fetched_at=now()-interval '25 hours' WHERE cvr='12345678'");
        self::assertSame('Stub Hansen ApS',$this->cvr('12345678')['json']['data']['name']);
        self::assertSame(404,$this->cvr('00000000')['status']);
        foreach (['99999999','88888888'] as $number) {
            $down=$this->cvr($number);
            self::assertSame(503,$down['status'],$down['body']);
            self::assertSame('upstream_unavailable',$down['json']['data']['error']);
            self::assertStringNotContainsString('<',$down['body']);
        }
        self::assertSame(422,$this->cvr('123')['status']);
        RestApiEnv::rows(self::$master,"UPDATE mobile_rate_buckets SET tokens=0,updated_at=now() WHERE scope='cvr' AND bucket_key=$1",[self::$bucket]);
        $limited=$this->cvr('12345678');
        self::assertSame(429,$limited['status'],$limited['body']);
        self::assertSame('rate_limited',$limited['json']['data']['error']);
        self::assertContains('Retry-After: 60',$limited['headers']);
        self::assertSame(401,$this->cvr('12345678',[])['status']);
    }

    public function testEqualIdAccountUsersInTwoTenantsHaveIndependentCvrBuckets(): void {
        $database='t2b3cvr_'.bin2hex(random_bytes(4));
        $account='t2b3cvr_'.bin2hex(random_bytes(4));
        $tenantId=null;$other=null;$otherBucket=null;
        RestApiEnv::rows(self::$master,'CREATE DATABASE '.pg_escape_identifier(self::$master,$database).' TEMPLATE '.pg_escape_identifier(self::$master,RestApiEnv::templateDb()));
        try {
            $tenantId=(int)RestApiEnv::rows(self::$master,"INSERT INTO regnskab(regnskab,dbhost,dbuser,db,version,sidst,brugerantal,posteringer,lukket,administrator)
                SELECT $1,dbhost,dbuser,$2,version,sidst,brugerantal,1000000,'',administrator FROM regnskab WHERE db=$3 RETURNING id",
                [$account,$database,RestApiEnv::templateDb()])[0]['id'];
            $other=RestApiEnv::connect($database);
            $firstClaims=JWT::decode(RestApiEnv::loginData()['access_token']);
            RestApiEnv::rows($other,'DELETE FROM brugere WHERE id=$1 OR brugernavn=$2',[$firstClaims['user_id'],RestApiEnv::user()]);
            RestApiEnv::rows($other,"INSERT INTO brugere(id,brugernavn,kode,email,rettigheder,status) VALUES ($1,$2,$3,'apitest@example.invalid',$4,true)",
                [$firstClaims['user_id'],RestApiEnv::user(),md5(RestApiEnv::password()),str_repeat('1',32)]);
            $login=RestApiEnv::http('POST',self::BASE.'auth/login.php',['account_name'=>$account,'username'=>RestApiEnv::user(),
                'password'=>RestApiEnv::password(),'device_id'=>'t2b3-other-tenant']);
            self::assertSame(200,$login['status'],$login['body']);
            $otherToken=$login['json']['data']['access_token'];
            $otherClaims=JWT::decode($otherToken);
            self::assertSame($firstClaims['user_id'],$otherClaims['user_id']);
            self::assertSame(self::$subject,$otherClaims['sub']);
            self::assertNotSame($firstClaims['tenant_id'],$otherClaims['tenant_id']);
            $otherBucket=CvrDirectoryService::bucketKey(self::$subject,$tenantId);
            RestApiEnv::rows(self::$master,"DELETE FROM cvr_cache WHERE cvr='12345678'");
            for ($i=0;$i<10;$i++) { self::assertSame(200,$this->cvr('12345678')['status']); }
            self::assertSame(429,$this->cvr('12345678')['status']);
            $cached=RestApiEnv::rows(self::$master,"SELECT payload FROM cvr_cache WHERE cvr='12345678'")[0]['payload'];
            $result=$this->cvr('12345678',['Authorization: Bearer '.$otherToken]);
            self::assertSame(200,$result['status'],$result['body']);
            self::assertSame(json_decode($cached,true),$result['json']['data'],'Both tenants share the company cache');
            $buckets=RestApiEnv::rows(self::$master,"SELECT bucket_key,tokens FROM mobile_rate_buckets WHERE scope='cvr' AND bucket_key IN ($1,$2)",[self::$bucket,$otherBucket]);
            self::assertCount(2,$buckets);
            $tokens=array_column($buckets,'tokens','bucket_key');
            self::assertLessThan(1,(float)$tokens[self::$bucket]);
            self::assertEquals(9,(float)$tokens[$otherBucket]);
            $registry=Subject::build('registry',1,Subject::parse(self::$subject)['installation']);
            self::assertSame(CvrDirectoryService::bucketKey($registry,(int)$firstClaims['tenant_id']),CvrDirectoryService::bucketKey($registry,$tenantId));
        } finally {
            if ($other) { pg_close($other); }
            if ($tenantId !== null) {
                RestApiEnv::rows(self::$master,'DELETE FROM refresh_sessions WHERE tenant_id=$1',[$tenantId]);
                RestApiEnv::rows(self::$master,'DELETE FROM auth_versions WHERE tenant_id=$1',[$tenantId]);
                RestApiEnv::rows(self::$master,'DELETE FROM regnskab WHERE id=$1',[$tenantId]);
            }
            if ($otherBucket !== null) { RestApiEnv::rows(self::$master,"DELETE FROM mobile_rate_buckets WHERE scope='cvr' AND bucket_key=$1",[$otherBucket]); }
            RestApiEnv::rows(self::$master,'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()',[$database]);
            RestApiEnv::rows(self::$master,'DROP DATABASE '.pg_escape_identifier(self::$master,$database));
        }
    }

    public function testRightsMethodsNotFoundAndValidation(): void {
        $customer=$this->create();
        $rights=str_repeat('0',32);$rights[6]='2';
        self::sql('UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[$rights,RestApiEnv::user()]);
        self::assertSame(200,$this->request('GET','debitor/customers/search.php')['status']);
        self::assertSame(403,$this->request('POST','debitor/customers/create.php',['client_id'=>self::uuid(),'name'=>'Denied'])['status']);
        self::sql('UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[str_repeat('1',32),RestApiEnv::user()]);
        self::assertSame(404,$this->request('GET','debitor/customers/detail.php?id=2147483647')['status']);
        self::assertSame(422,$this->request('GET','debitor/customers/detail.php?id=0')['status']);
        self::assertSame(422,$this->request('PATCH','debitor/customers/update.php',['client_id'=>self::uuid(),'phone'=>'123'])['status']);
        self::assertSame(405,$this->request('GET','debitor/customers/create.php')['status']);
        self::assertSame(422,$this->request('POST','debitor/customers/create.php',['name'=>'Missing client'])['status']);
        self::assertSame(422,$this->request('POST','debitor/customers/create.php',['client_id'=>self::uuid(),'name'=>'Bad email','email'=>'bad'])['status']);
        $all=$this->request('GET','debitor/customers/search.php?limit=999');
        self::assertSame(100,$all['json']['data']['limit']);
    }
    public function testPublishedResponseFixturesMatchHttp(): void {
        $created=$this->request('POST','debitor/customers/create.php',['client_id'=>self::uuid(),'name'=>'Hansen ApS']);
        self::assertSame(201,$created['status'],$created['body']);
        $id=$created['json']['data']['id'];
        $detail=$this->request('GET','debitor/customers/detail.php?id='.$id);
        $responses=['customers-created'=>$created,'customers-search'=>$this->request('GET','debitor/customers/search.php'),
            'customers-detail'=>$detail,'sales-customer'=>$this->request('GET','debitor/sales/customers.php?id='.$id),
            'sales-customers'=>$this->request('GET','debitor/sales/customers.php')];
        $responses['customers-update']=$this->request('PATCH','debitor/customers/update.php?id='.$id,
            ['client_id'=>self::uuid(),'phone'=>'87654321'],['If-Match: '.$detail['json']['data']['etag']]);
        RestApiEnv::rows(self::$master,"DELETE FROM cvr_cache WHERE cvr='12345678'");
        $responses['cvr']=$this->cvr('12345678');
        $normalize=function (array $value) use (&$normalize): array {
            foreach ($value as $key=>&$field) {
                if (is_array($field)) { $field=$normalize($field); }
                elseif ($key==='id') { $field=12; }
                elseif ($key==='etag') { $field='"'.str_repeat('a',64).'"'; }
                elseif ($key==='fetched_at') { $field='2026-10-02T12:00:00Z'; }
            }
            unset($field);
            return $value;
        };
        foreach ($responses as $name=>$response) {
            self::assertContains($response['status'],[200,201],$response['body']);
            $fixture=json_decode(file_get_contents(__DIR__.'/fixtures/t2/'.$name.'.json'),true,512,JSON_THROW_ON_ERROR);
            self::assertSame($fixture,$normalize($response['json']),$name);
        }
    }

    public function testRecentDocumentsAreTheLatestTenAndContactsCannotModifyCreditors(): void {
        $customer=$this->create();$id=$customer['id'];
        foreach (range(1,12) as $index) {
            $art=$index===12 ? 'DK' : 'DO';$status=$index%5;
            self::sql("INSERT INTO ordrer(konto_id,art,status,ordrenr,fakturanr,ordredate,fakturadate,sum,moms,valuta)
                VALUES ($1,$2,$3,$4,$5,$6,$6,100,25,'DKK')",[$id,$art,$status,$index,'F'.$index,'2026-09-'.str_pad((string)$index,2,'0',STR_PAD_LEFT)]);
        }
        $detail=$this->request('GET','debitor/customers/detail.php?id='.$id);
        $documents=$detail['json']['data']['recent_documents'];
        self::assertCount(10,$documents);
        self::assertSame('kreditnota',$documents[0]['kind']);
        self::assertSame('2026-09-12',$documents[0]['date']);
        self::assertSame('125.00',$documents[0]['total']);
        $creditor=(int)self::sql("INSERT INTO adresser(art,kontonr,firmanavn) VALUES ('K','9999','Creditor') RETURNING id")[0]['id'];
        self::assertSame(404,$this->request('GET','debitor/customers/detail.php?id='.$creditor)['status']);
        self::assertSame(404,$this->request('PATCH','debitor/customers/update.php?id='.$creditor,
            ['client_id'=>self::uuid(),'phone'=>'12345678'],['If-Match: "stale"'])['status']);
        self::assertSame(0,$this->request('GET','debitor/customers/search.php?q=Creditor')['json']['data']['total']);
    }

}
