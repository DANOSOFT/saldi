<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/PaymentRegisterEndpointTest.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Payment registration HTTP, accounting and concurrency tests.
// 20261001 CDX/LH Cover future payments, closed bookkeeping years and credit-note allocation races.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../characterization/finans/support/PaymentFixture.php';
require_once __DIR__ . '/../../restapi/services/PaymentRegistrationService.php';

final class PaymentRegisterEndpointTest extends TestCase
{
    private const PATH = '/restapi/endpoints/v1/payments/';
    private static $tenant;
    private static array $fixture;
    private static int $serial=700;
    private array $server;

    public static function setUpBeforeClass(): void
    {
        if ($reason=RestApiEnv::unavailableReason()) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$tenant=RestApiEnv::connect(RestApiEnv::testDb());
        mobileTenantUpdates(self::$tenant);
        self::$fixture=PaymentFixture::seed(self::$tenant);
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
        $this->server = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/saldi/finans/bogfor.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        PaymentFixture::query(self::$tenant,"DELETE FROM settings WHERE var_grp='mobile_betaling'");
        foreach (['bank','kasse','mobilepay'] as $method) {
            PaymentFixture::query(self::$tenant,"INSERT INTO settings (var_grp,var_name,var_value,user_id) VALUES ('mobile_betaling',$1,$2,0)",['account_'.$method,(string)self::$fixture['bank']]);
        }
    }
    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }
    private static function uuid(): string
    {
        $value=bin2hex(random_bytes(16));
        return substr($value,0,8).'-'.substr($value,8,4).'-'.substr($value,12,4).'-'.substr($value,16,4).'-'.substr($value,20);
    }
    private function body(string $amount='1000.00',string $currency='DKK'): array
    {
        $id=PaymentFixture::invoice(self::$tenant,self::$fixture,(string)++self::$serial,'1000.00',$currency);
        return ['client_id'=>self::uuid(),'openpost_id'=>$id,'amount'=>$amount,'method'=>'bank','date'=>'2026-03-15'];
    }
    /** @return array{status:int,json:array|null,body:string,headers:array} */
    private function request(string $method,string $file,?array $body=null,bool $auth=true): array
    {
        $headers=[];
        $handle=curl_init(RestApiEnv::baseUrl().self::PATH.$file);
        $requestHeaders=$auth ? RestApiEnv::authHeaders():[];
        curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>60,
            CURLOPT_HEADERFUNCTION=>static function ($handle,string $line) use (&$headers): int {
                if (str_contains($line,':')) {
                    [$name,$value]=explode(':',$line,2);
                    $headers[strtolower(trim($name))]=trim($value);
                }
                return strlen($line);
            }]);
        if ($body !== null) {
            $requestHeaders[]='Content-Type: application/json';
            curl_setopt($handle,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
        }
        curl_setopt($handle,CURLOPT_HTTPHEADER,$requestHeaders);
        $raw=curl_exec($handle);
        $status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
        $error=curl_error($handle);
        curl_close($handle);
        self::assertIsString($raw,$error);
        return ['status'=>$status,'json'=>json_decode($raw,true),'body'=>$raw,'headers'=>$headers];
    }
    private function register(array $body): array
    {
        $response=$this->request('POST','register.php',$body);
        self::assertSame(201,$response['status'],$response['body']);
        self::assertIsArray($response['json']);
        self::assertStringNotContainsString('<',$response['body']);
        return $response;
    }
    private function assertRefused(array $body,string $reason,int $status=422): void
    {
        $before=PaymentFixture::query(self::$tenant,'SELECT count(*) AS count FROM payment_registrations')[0]['count'];
        $response=$this->request('POST','register.php',$body);
        self::assertSame($status,$response['status'],$response['body']);
        self::assertSame($reason,$response['json']['data']['details']['reason']);
        self::assertSame($before,PaymentFixture::query(self::$tenant,'SELECT count(*) AS count FROM payment_registrations')[0]['count']);
    }
    public function testAccountsAndAuthentication(): void
    {
        self::assertSame(401,$this->request('GET','accounts.php',null,false)['status']);
        self::assertSame(401,$this->request('POST','register.php',$this->body(),false)['status']);
        $response=$this->request('GET','accounts.php');
        self::assertSame(200,$response['status'],$response['body']);
        self::assertSame(self::$fixture['bank'],$response['json']['data']['bank']['kontonr']);
        PaymentFixture::query(self::$tenant,"DELETE FROM settings WHERE var_grp='mobile_betaling'");
        self::assertSame(['bank'=>null,'kasse'=>null,'mobilepay'=>null],$this->request('GET','accounts.php')['json']['data']);
        $this->assertRefused($this->body(),'no_account');
    }
    public function testWebSettingsRequiresCurrentWriteRightsCsrfAndOpenBalanceAccounts(): void
    {
        require_once __DIR__.'/../../systemdata/diverseIncludes/mobilBetalingValg.php';
        $context=new EngineContext(self::$tenant,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RestApiEnv::USER,1,'2026-03-15');
        $rights=PaymentFixture::query(self::$tenant,'SELECT rettigheder FROM brugere WHERE brugernavn=$1',[RestApiEnv::USER])[0]['rettigheder'];
        $session=ifset($GLOBALS,'_SESSION',[]);
        $revisor=ifset($GLOBALS,'revisor');
        $GLOBALS['revisor']=false;
        $_SESSION=['csrf_token'=>bin2hex(random_bytes(32)),'mobile_payment_settings_db'=>RestApiEnv::testDb()];
        $input=['csrf_token'=>$_SESSION['csrf_token'],'account_bank'=>(string)self::$fixture['bank'],'account_kasse'=>'','account_mobilepay'=>'','link_lifetime_days'=>'14','public_base_url'=>'https://payments.example.test/saldi/'];
        $save=static fn(array $fields): int=>LegacyEngine::run($context,static function () use ($fields): int {
            require_once __DIR__.'/../../includes/db_query.php';
            return mobilBetalingSave($fields);
        });
        try {
            PaymentFixture::query(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',['01',RestApiEnv::USER]);
            self::assertSame(5478,$save($input));
            self::assertSame('https://payments.example.test/saldi',PaymentFixture::query(self::$tenant,"SELECT var_value FROM settings WHERE var_grp='mobile_betaling' AND var_name='public_base_url'")[0]['var_value']);
            $before=PaymentFixture::query(self::$tenant,"SELECT var_name,var_value FROM settings WHERE var_grp='mobile_betaling' ORDER BY id");
            $invalid=$input;$invalid['account_bank']='999999999';
            self::assertSame(5476,$save($invalid));
            $invalid=$input;$invalid['public_base_url']='http://payments.example.test';
            self::assertSame(5476,$save($invalid));
            $invalid=$input;$invalid['csrf_token']='wrong';
            self::assertSame(5477,$save($invalid));
            PaymentFixture::query(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',['02',RestApiEnv::USER]);
            self::assertSame(5477,$save($input));
            self::assertSame($before,PaymentFixture::query(self::$tenant,"SELECT var_name,var_value FROM settings WHERE var_grp='mobile_betaling' ORDER BY id"));
        } finally {
            PaymentFixture::query(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[$rights,RestApiEnv::USER]);
            $_SESSION=$session;
            $GLOBALS['revisor']=$revisor;
        }
    }

    public function testFullPaymentReplayAndChangedBody(): void
    {
        $body=$this->body();
        $first=$this->register($body);
        self::assertTrue($first['json']['data']['aligned']);
        self::assertSame('0.00',$first['json']['data']['rest']);
        $again=$this->register($body);
        self::assertSame($first['json'],$again['json']);
        self::assertSame('true',$again['headers']['idempotent-replayed']);
        $body['amount']='10.00';
        $this->assertRefused($body,'client_id_reused',409);
        $rows=PaymentFixture::query(self::$tenant,'SELECT * FROM payment_registrations WHERE openpost_id=$1',[$body['openpost_id']]);
        self::assertCount(1,$rows);
        self::assertCount(2,PaymentFixture::query(self::$tenant,'SELECT * FROM transaktioner WHERE kladde_id=$1',[$rows[0]['kladde_id']]));
    }
    public function testPartialPartialFinalMatchesGoldenG4(): void
    {
        $body=$this->body('400.00');
        foreach (['400.00'=>'600.00','300.00'=>'300.00','300.00 '=>'0.00'] as $amount=>$rest) {
            $body['amount']=trim($amount);
            $body['client_id']=self::uuid();
            $response=$this->register($body);
            self::assertSame($rest,$response['json']['data']['rest']);
            self::assertSame($rest==='0.00',$response['json']['data']['aligned']);
        }
        $invoice=PaymentFixture::query(self::$tenant,'SELECT * FROM openpost WHERE id=$1',[$body['openpost_id']])[0];
        $rows=PaymentFixture::query(self::$tenant,'SELECT amount,udlignet,udlign_id,udlign_date FROM openpost WHERE konto_id=$1 AND faktnr=$2 ORDER BY id',[$invoice['konto_id'],$invoice['faktnr']]);
        self::assertSame([1000.0,-400.0,-300.0,-300.0],array_map('floatval',array_column($rows,'amount')));
        self::assertSame(['1','1','1','1'],array_column($rows,'udlignet'));
        self::assertCount(1,array_unique(array_column($rows,'udlign_id')));
        self::assertSame(['2026-03-15','2026-03-15','2026-03-15','2026-03-15'],array_column($rows,'udlign_date'));
        $golden=json_decode(file_get_contents(__DIR__.'/fixtures/t2/golden/G4-payments.json'),true,512,JSON_THROW_ON_ERROR);
        self::assertSame($golden['openpost_amounts'],array_map(static fn(array $r): string=>number_format((float)$r['amount'],2,'.',''),$rows));
    }
    public function testOverpaymentForeignCurrencyYearAndPeriodAreAtomic(): void
    {
        $this->assertRefused($this->body('1000.01'),'amount_exceeds_rest');
        $this->assertRefused($this->body('50.00','EUR'),'currency_not_supported');
        $body=$this->body();$body['date']='2099-01-01';
        $this->assertRefused($body,'outside_fiscal_year');
        PaymentFixture::query(self::$tenant,"CREATE TABLE IF NOT EXISTS moms_periode_luk (kalender_aar integer,kalender_maaned integer,status varchar(10))");
        PaymentFixture::query(self::$tenant,"INSERT INTO moms_periode_luk (kalender_aar,kalender_maaned,status) VALUES (2026,3,'closed')");
        try {
            $this->assertRefused($this->body(),'period_locked');
        } finally {
            PaymentFixture::query(self::$tenant,"DELETE FROM moms_periode_luk WHERE kalender_aar=2026 AND kalender_maaned=3");
        }
    }
    /** @return array<string,list<array<string,string|null>>> */
    private function postingState(): array
    {
        $state=[];
        foreach (['kladdeliste','kassekladde','transaktioner','openpost','payment_registrations','mobile_idempotency','kontoplan'] as $table) {
            $state[$table]=PaymentFixture::query(self::$tenant,"SELECT * FROM $table ORDER BY id");
        }
        return $state;
    }

    public function testFuturePartialPaymentCountsAgainstPayableRemainder(): void
    {
        $body=$this->body('400.00');
        $body['date']='2026-12-01';
        $this->register($body);
        $invoice=PaymentFixture::query(self::$tenant,'SELECT * FROM openpost WHERE id=$1',[$body['openpost_id']])[0];
        $rows=PaymentFixture::query(self::$tenant,'SELECT amount,transdate FROM openpost WHERE konto_id=$1 AND faktnr=$2 ORDER BY id',[$invoice['konto_id'],$invoice['faktnr']]);
        self::assertSame([1000.0,-400.0],array_map('floatval',array_column($rows,'amount')));
        self::assertSame('2026-12-01',$rows[1]['transdate']);
        $before=$this->postingState();
        $body['client_id']=self::uuid();
        $body['date']='2026-03-15';
        $body['amount']='700.00';
        $this->assertRefused($body,'amount_exceeds_rest');
        self::assertSame($before,$this->postingState());
        $body['amount']='600.00';
        $response=$this->register($body);
        self::assertTrue($response['json']['data']['aligned']);
        self::assertSame('0.00',$response['json']['data']['rest']);
        self::assertSame(['2026-12-01'],array_unique(array_column(PaymentFixture::query(self::$tenant,'SELECT udlign_date FROM openpost WHERE konto_id=$1 AND faktnr=$2 ORDER BY id',[$invoice['konto_id'],$invoice['faktnr']]),'udlign_date')));
    }

    public function testClosedFiscalYearRefusesRegistrationWithoutWrites(): void
    {
        $body=$this->body();
        $year=self::$fixture['year'];
        $original=PaymentFixture::query(self::$tenant,"SELECT box5 FROM grupper WHERE art='RA' AND kodenr=$1",[$year])[0]['box5'];
        PaymentFixture::query(self::$tenant,"UPDATE grupper SET box5='' WHERE art='RA' AND kodenr=$1",[$year]);
        try {
            $before=$this->postingState();
            $this->assertRefused($body,'outside_fiscal_year');
            self::assertSame($before,$this->postingState());
        } finally {
            PaymentFixture::query(self::$tenant,"UPDATE grupper SET box5=$2 WHERE art='RA' AND kodenr=$1",[$year,$original]);
        }
    }

    public function testFiscalYearCannotCloseBeforeProviderTransactionCommits(): void
    {
        $body=$this->body();
        $context=new EngineContext(self::$tenant,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RestApiEnv::USER,1,$body['date']);
        $other=RestApiEnv::connect(RestApiEnv::testDb());
        try {
            PaymentFixture::query(self::$tenant,'BEGIN');
            (new PaymentRegistrationService($context,'test/provider/year-lock'))->registerFromProvider($body,'saldi-year-lock-'.bin2hex(random_bytes(5)));
            $year=self::$fixture['year'];
            self::assertTrue(pg_send_query($other,"SELECT id FROM grupper WHERE art='RA' AND kodenr=$year FOR UPDATE NOWAIT"));
            $result=pg_get_result($other);
            self::assertSame(PGSQL_FATAL_ERROR,pg_result_status($result));
            self::assertSame('55P03',pg_result_error_field($result,PGSQL_DIAG_SQLSTATE));
            self::assertFalse(pg_get_result($other));
            PaymentFixture::query(self::$tenant,'ROLLBACK');
            self::assertNotEmpty(PaymentFixture::query($other,"SELECT id FROM grupper WHERE art='RA' AND kodenr=$year FOR UPDATE NOWAIT"));
        } finally {
            if (pg_transaction_status(self::$tenant)!==PGSQL_TRANSACTION_IDLE) {
                PaymentFixture::query(self::$tenant,'ROLLBACK');
            }
            pg_close($other);
        }
    }

    public function testPendingCaptureBlocksManualRegistrationAndCreatedLinkIsSuperseded(): void
    {
        $body=$this->body();
        $link=PaymentFixture::query(self::$tenant,"INSERT INTO payment_links (token_sha256,provider,openpost_id,konto_id,faktnr,amount,currency,expires_at,created_by,client_id) VALUES ($1,'vipps_epayment',$2,$3,'fixture',1000,'DKK',now()+interval '1 day','fixture',$4) RETURNING id",[hash('sha256',random_bytes(16)),$body['openpost_id'],self::$fixture['debtor'],self::uuid()])[0]['id'];
        $attempt=PaymentFixture::query(self::$tenant,"INSERT INTO payment_attempts (link_id,provider_reference,state,amount_minor,currency) VALUES ($1,$2,'capturing',100000,'DKK') RETURNING id",[$link,'saldi-test-'.bin2hex(random_bytes(5))])[0]['id'];
        $this->assertRefused($body,'payment_in_progress',409);
        PaymentFixture::query(self::$tenant,"UPDATE payment_attempts SET state='created' WHERE id=$1",[$attempt]);
        $this->register($body);
        self::assertSame('superseded',PaymentFixture::query(self::$tenant,'SELECT state FROM payment_links WHERE id=$1',[$link])[0]['state']);
    }
    public function testConcurrentRegistrationsNeverExceedInvoiceRest(): void
    {
        $body=$this->body('700.00');
        $requests=[$body,$body];$requests[1]['client_id']=self::uuid();
        $multi=curl_multi_init();$handles=[];
        foreach ($requests as $request) {
            $handle=curl_init(RestApiEnv::baseUrl().self::PATH.'register.php');
            curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($request),
                CURLOPT_HTTPHEADER=>array_merge(RestApiEnv::authHeaders(),['Content-Type: application/json']),CURLOPT_TIMEOUT=>60]);
            curl_multi_add_handle($multi,$handle);$handles[]=$handle;
        }
        do {
            curl_multi_exec($multi,$running);
            if ($running) { curl_multi_select($multi,1); }
        } while ($running);
        $statuses=[];
        foreach ($handles as $handle) {
            $statuses[]=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
            $raw=curl_multi_getcontent($handle);
            self::assertIsArray(json_decode($raw,true),$raw);
            curl_multi_remove_handle($multi,$handle);curl_close($handle);
        }
        curl_multi_close($multi);sort($statuses);
        self::assertSame([201,422],$statuses);
        $rows=PaymentFixture::query(self::$tenant,'SELECT amount FROM payment_registrations WHERE openpost_id=$1',[$body['openpost_id']]);
        self::assertCount(1,$rows);
        self::assertSame(700.0,(float)$rows[0]['amount']);
    }
    public function testHistoricalInvoiceRemainsOpenUntilPaymentDate(): void
    {
        $body=$this->body();$this->register($body);
        $rows=PaymentFixture::query(self::$tenant,'SELECT udlign_date FROM openpost WHERE id=$1',[$body['openpost_id']]);
        self::assertSame('2026-03-15',$rows[0]['udlign_date']);
        $invoice=PaymentFixture::query(self::$tenant,'SELECT * FROM openpost WHERE id=$1',[$body['openpost_id']])[0];
        $cache=[];
        $GLOBALS['baseCurrency']='DKK';
        $aging=openpost_account_aging([$invoice],'2026-02-15','2026-10-03','D',$cache);
        self::assertEquals(1000.0,(float)$aging['openY']);
    }
    public function testProviderRegistrationReplaysOneVoucher(): void
    {
        $body=$this->body();
        $context=new EngineContext(self::$tenant,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RestApiEnv::USER,1,$body['date']);
        $registry=RestApiEnv::connect(RestApiEnv::masterDb());
        $service=new PaymentRegistrationService($context,'test/provider/1',$registry);
        $reference='saldi-'.RestApiEnv::regnskabId(RestApiEnv::accountOpen()).'-123-1';
        $first=$service->registerFromProvider($body,$reference);
        $again=$service->registerFromProvider($body,$reference);
        self::assertSame($first['body']['data']['registration_id'],$again['body']['data']['registration_id']);
        self::assertSame('true',$again['headers']['Idempotent-Replayed']);
        self::assertSame('mobilepay',PaymentFixture::query(self::$tenant,'SELECT method FROM payment_registrations WHERE id=$1',[$first['body']['data']['registration_id']])[0]['method']);
        pg_close($registry);
    }
    public function testProviderCallerOwnsRollbackAndCancellationRunsAfterCommit(): void
    {
        $body=$this->body();
        $context=new EngineContext(self::$tenant,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RestApiEnv::USER,1,$body['date']);
        $registry=RestApiEnv::connect(RestApiEnv::masterDb());
        $reference='saldi-rollback-'.bin2hex(random_bytes(5));
        $service=new PaymentRegistrationService($context,'test/provider/rollback',$registry);
        try {
            PaymentFixture::query(self::$tenant,'BEGIN');
            $result=$service->registerFromProvider($body,$reference);
            self::assertSame(PGSQL_TRANSACTION_INTRANS,pg_transaction_status(self::$tenant));
            PaymentFixture::query(self::$tenant,'ROLLBACK');
            self::assertSame([],PaymentFixture::query(self::$tenant,'SELECT id FROM payment_registrations WHERE id=$1',[$result['body']['data']['registration_id']]));
            self::assertSame('0',PaymentFixture::query(self::$tenant,'SELECT udlignet FROM openpost WHERE id=$1',[$body['openpost_id']])[0]['udlignet']);
            $link=PaymentFixture::query(self::$tenant,"INSERT INTO payment_links (token_sha256,provider,openpost_id,konto_id,faktnr,amount,currency,expires_at,created_by,client_id) VALUES ($1,'vipps_epayment',$2,$3,'fixture',1000,'DKK',now()+interval '1 day','fixture',$4) RETURNING id",[hash('sha256',random_bytes(16)),$body['openpost_id'],self::$fixture['debtor'],self::uuid()])[0]['id'];
            PaymentFixture::query(self::$tenant,"INSERT INTO payment_attempts (link_id,provider_reference,state,amount_minor,currency) VALUES ($1,$2,'created',100000,'DKK')",[$link,$reference]);
            $seen=[];
            $cancel=static function (string $ref) use (&$seen,$body,$link): bool {
                self::assertSame(PGSQL_TRANSACTION_IDLE,pg_transaction_status(self::$tenant));
                self::assertSame('superseded',PaymentFixture::query(self::$tenant,'SELECT state FROM payment_links WHERE id=$1',[$link])[0]['state']);
                self::assertCount(1,PaymentFixture::query(self::$tenant,'SELECT id FROM payment_registrations WHERE openpost_id=$1',[$body['openpost_id']]));
                $seen[]=$ref;
                return true;
            };
            (new PaymentRegistrationService($context,'test/provider/rollback',$registry,$cancel))->register($body);
            self::assertContains($reference,$seen);
            self::assertSame('cancelled',PaymentFixture::query(self::$tenant,'SELECT state FROM payment_attempts WHERE provider_reference=$1',[$reference])[0]['state']);
        } finally {
            if (pg_transaction_status(self::$tenant)!==PGSQL_TRANSACTION_IDLE) { PaymentFixture::query(self::$tenant,'ROLLBACK'); }
            pg_close($registry);
        }
    }

    public function testMovedIncludesExistAndEnginesDoNotExitOrOwnTransactions(): void
    {
        $source=file_get_contents(__DIR__.'/../../includes/finans/kladdePosting.php');
        self::assertStringNotContainsString("../includes/",$source);
        self::assertDoesNotMatchRegularExpression('/^\s*(exit|die|transaktion)\b/m',$source);
        preg_match_all("~(?:require|include)(?:_once)?\\s*\\(?__DIR__\\s*\\.\\s*'([^']+)'~",$source,$matches);
        self::assertNotEmpty($matches[1]);
        foreach ($matches[1] as $path) {
            self::assertFileExists(__DIR__.'/../../includes/finans/'.$path);
        }
    }

    public function testAppAndWebHaveEqualSummaryBalancesAndFollowingYearPrimo(): void
    {
        $golden=json_decode(file_get_contents(__DIR__.'/fixtures/t2/golden/G4-payments.json'),true,512,JSON_THROW_ON_ERROR);
        $year=self::$fixture['year'];$next=$year+1;
        $summaries=[980000,980001];
        PaymentFixture::query(self::$tenant,"INSERT INTO kontoplan (kontonr,beskrivelse,kontotype,regnskabsaar,fra_kto,saldo,primo) VALUES (980000,'Payment Z','Z',$1,$2,0,0),(980001,'Payment R','R',$1,980000,0,0)",[$year,self::$fixture['bank']]);
        PaymentFixture::query(self::$tenant,"INSERT INTO grupper (art,kodenr,box1,box2,box3,box4,box10) VALUES ('RA',$1,'1','2027','12','2027','')",[$next]);
        $columns=PaymentFixture::query(self::$tenant,"SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name='kontoplan' AND column_name<>'id' ORDER BY ordinal_position");
        $names=array_map(static fn(array $row): string=>pg_escape_identifier(self::$tenant,$row['column_name']),$columns);
        $expressions=array_map(static fn(array $row): string=>$row['column_name']==='regnskabsaar' ? (string)$next:pg_escape_identifier(self::$tenant,$row['column_name']),$columns);
        PaymentFixture::query(self::$tenant,'INSERT INTO kontoplan ('.implode(',',$names).') SELECT '.implode(',',$expressions).' FROM kontoplan WHERE regnskabsaar=$1',[$year]);
        $registry=RestApiEnv::connect(RestApiEnv::masterDb());
        $context=new EngineContext(self::$tenant,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RestApiEnv::USER,1,'2026-03-15');
        try {
            PaymentFixture::query(self::$tenant,'BEGIN');
            LegacyEngine::run($context,static function () use ($year): void {
                LegacyEngine::load('kladde');
                require_once __DIR__.'/../../includes/genberegn.php';
                require_once __DIR__.'/../../includes/updateFollowingOpeningBalances.php';
                genberegn($year,false);
                updateFollowingOpeningBalances($year);
            });
            PaymentFixture::query(self::$tenant,'COMMIT');
            $snapshot=static function () use ($year,$next): array {
                return PaymentFixture::query(self::$tenant,'SELECT kontonr,regnskabsaar,saldo,primo FROM kontoplan WHERE regnskabsaar IN ($1,$2) AND kontonr IN ($3,$4,980000,980001) ORDER BY regnskabsaar,kontonr',[$year,$next,self::$fixture['bank'],self::$fixture['collective']]);
            };
            $before=$snapshot();
            $body=$this->body();$this->register($body);$app=$snapshot();
            $number=(string)++self::$serial;
            PaymentFixture::invoice(self::$tenant,self::$fixture,$number);
            $journal=PaymentFixture::journal(self::$tenant,self::$fixture,$number,'1000.00');
            $session='t2b7web'.bin2hex(random_bytes(10));
            PaymentFixture::query($registry,'INSERT INTO online (session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id) VALUES ($1,$2,$3,$4,$5,$6,$7,false,0)',[$session,RestApiEnv::USER,RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('9',50),$year,(string)time()]);
            $proc=proc_open([PHP_BINARY,__DIR__.'/../characterization/support/run_bogfor_page.php','bogfor',(string)$journal,$session],[0=>['pipe','r'],1=>['file','/tmp/t2b7-web-golden.out','w'],2=>['file','/tmp/t2b7-web-golden.err','w']],$pipes);
            fclose($pipes[0]);$exit=proc_close($proc);
            PaymentFixture::query($registry,'DELETE FROM online WHERE session_id=$1',[$session]);
            self::assertSame(0,$exit,file_get_contents('/tmp/t2b7-web-golden.err'));
            self::assertSame('V',PaymentFixture::query(self::$tenant,'SELECT bogfort FROM kladdeliste WHERE id=$1',[$journal])[0]['bogfort']);
            $web=$snapshot();
            self::assertCount(count($before),$web);
            foreach ($before as $index=>$row) {
                foreach (['saldo','primo'] as $field) {
                    $appDelta=(float)$app[$index][$field]-(float)$row[$field];
                    $webDelta=(float)$web[$index][$field]-(float)$app[$index][$field];
                    self::assertEqualsWithDelta($appDelta,$webDelta,0.001,'account '.$row['kontonr'].' year '.$row['regnskabsaar'].' '.$field);
                    if ((int)$row['regnskabsaar']===$year && in_array((int)$row['kontonr'],$summaries,true) && $field==='saldo') {
                        self::assertEquals((float)$golden['summary_saldo_delta'][$row['kontotype'] ?? ((int)$row['kontonr']===980000 ? 'Z':'R')],$appDelta,'R/Z totals must include the payment');
                    }
                    if ((int)$row['regnskabsaar']===$next && $field==='primo') {
                        if ((int)$row['kontonr']===self::$fixture['bank']) { self::assertEquals((float)$golden['following_year_primo_delta']['bank'],$appDelta); }
                        if ((int)$row['kontonr']===self::$fixture['collective']) { self::assertEquals((float)$golden['following_year_primo_delta']['debtor'],$appDelta); }
                    }
                }
            }
        } finally {
            if (pg_transaction_status(self::$tenant)!==PGSQL_TRANSACTION_IDLE) { PaymentFixture::query(self::$tenant,'ROLLBACK'); }
            PaymentFixture::query(self::$tenant,'DELETE FROM kontoplan WHERE regnskabsaar=$1 OR kontonr IN (980000,980001)',[$next]);
            PaymentFixture::query(self::$tenant,"DELETE FROM grupper WHERE art='RA' AND kodenr=$1",[$next]);
            pg_close($registry);
        }
    }

    public function testNonCalendarFiscalYearAndClosedPeriodMatchGoldenG6(): void
    {
        $golden=json_decode(file_get_contents(__DIR__.'/fixtures/t2/golden/G6-payment-period.json'),true,512,JSON_THROW_ON_ERROR);
        $year=self::$fixture['year'];
        $original=PaymentFixture::query(self::$tenant,"SELECT box1,box2,box3,box4 FROM grupper WHERE art='RA' AND kodenr=$1",[$year])[0];
        PaymentFixture::query(self::$tenant,"UPDATE grupper SET box1='7',box2='2025',box3='6',box4='2026' WHERE art='RA' AND kodenr=$1",[$year]);
        $before=PaymentFixture::query(self::$tenant,'SELECT count(*) AS count FROM transaktioner')[0]['count'];
        $balances=PaymentFixture::query(self::$tenant,'SELECT id,saldo,primo FROM kontoplan ORDER BY id');
        try {
            $body=$this->body();$body['date']=$golden['refused_date'];
            $this->assertRefused($body,'outside_fiscal_year');
            $body['date']='2026-06-15';
            PaymentFixture::query(self::$tenant,"CREATE TABLE IF NOT EXISTS moms_periode_luk (kalender_aar integer,kalender_maaned integer,status varchar(10))");
            PaymentFixture::query(self::$tenant,"INSERT INTO moms_periode_luk (kalender_aar,kalender_maaned,status) VALUES (2026,6,'closed')");
            $this->assertRefused($body,'period_locked');
            self::assertSame($before,PaymentFixture::query(self::$tenant,'SELECT count(*) AS count FROM transaktioner')[0]['count']);
            self::assertSame($balances,PaymentFixture::query(self::$tenant,'SELECT id,saldo,primo FROM kontoplan ORDER BY id'));
        } finally {
            PaymentFixture::query(self::$tenant,"UPDATE grupper SET box1=$2,box2=$3,box3=$4,box4=$5 WHERE art='RA' AND kodenr=$1",[$year,$original['box1'],$original['box2'],$original['box3'],$original['box4']]);
            PaymentFixture::query(self::$tenant,"DELETE FROM moms_periode_luk WHERE kalender_aar=2026 AND kalender_maaned=6");
        }
    }

    public function testLedgerWriteFailureReturnsJsonAndRollsBackEverything(): void
    {
        $body=$this->body();
        $before=PaymentFixture::query(self::$tenant,'SELECT count(*) AS count FROM kladdeliste')[0]['count'];
        PaymentFixture::query(self::$tenant,"CREATE FUNCTION t2b7_reject_ledger() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''test ledger refusal''; END'");
        PaymentFixture::query(self::$tenant,'CREATE TRIGGER t2b7_reject BEFORE INSERT ON transaktioner FOR EACH ROW EXECUTE FUNCTION t2b7_reject_ledger()');
        try {
            $response=$this->request('POST','register.php',$body);
            self::assertContains($response['status'],[422,500],$response['body']);
            self::assertIsArray($response['json'],$response['body']);
            self::assertStringNotContainsString('<',$response['body']);
            self::assertSame($before,PaymentFixture::query(self::$tenant,'SELECT count(*) AS count FROM kladdeliste')[0]['count']);
            self::assertSame('0',PaymentFixture::query(self::$tenant,'SELECT udlignet FROM openpost WHERE id=$1',[$body['openpost_id']])[0]['udlignet']);
        } finally {
            PaymentFixture::query(self::$tenant,'DROP TRIGGER t2b7_reject ON transaktioner');
            PaymentFixture::query(self::$tenant,'DROP FUNCTION t2b7_reject_ledger()');
        }
    }
    public function testMixedAppAndWebWritersReserveDistinctSettlementIds(): void
    {
        $body=$this->body();
        $jobs=[];
        foreach (['equalize'=>[-75.0,75.0],'equalize-copy'=>[-76.0,76.0],'difference'=>[11.0,-10.99],'auto'=>[100.0,-40.0,-60.0],'dunning'=>[90.0,-35.0,-55.0]] as $mode=>$amounts) {
            $number=987640+count($jobs);
            $customer=(int)PaymentFixture::query(self::$tenant,"INSERT INTO adresser (kontonr,firmanavn,art,gruppe) SELECT $1,'Settlement race','D',gruppe FROM adresser WHERE id=$2 RETURNING id",[(string)$number,self::$fixture['debtor']])[0]['id'];
            $ids=[];
            foreach ($amounts as $amount) {
                $ids[]=(int)PaymentFixture::query(self::$tenant,"INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,udlignet,transdate,valuta,valutakurs,udlign_id,projekt) VALUES ($1,$2,$3,$4,'0','2026-03-15','DKK',100,0,0) RETURNING id",[$customer,(string)$number,'race-'.self::$serial.'-'.$mode,$amount])[0]['id'];
            }
            $jobs[$mode]=['tenant_id'=>RestApiEnv::regnskabId(RestApiEnv::accountOpen()),'customer'=>$customer,'ids'=>$ids,'bank'=>self::$fixture['bank'],'year'=>self::$fixture['year']];
        }
        $jobs['credit-note']=$this->creditNoteJob();
        // The legacy alle branch recognizes zero totals when it advances to the next customer.
        $sentinel=(int)PaymentFixture::query(self::$tenant,"INSERT INTO adresser (kontonr,firmanavn,art,gruppe) SELECT '987649','Race sentinel','D',gruppe FROM adresser WHERE id=$1 RETURNING id",[self::$fixture['debtor']])[0]['id'];
        PaymentFixture::query(self::$tenant,"INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,udlignet,transdate,valuta,valutakurs,udlign_id) VALUES ($1,'987649','sentinel',1,'0','2026-03-15','DKK',100,0)",[$sentinel]);
        $registry=RestApiEnv::connect(RestApiEnv::masterDb());
        $session='t2b7dunning'.bin2hex(random_bytes(10));
        PaymentFixture::query($registry,'INSERT INTO online (session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id) VALUES ($1,$2,$3,$4,$5,$6,$7,false,0)',[$session,RestApiEnv::USER,RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('9',50),self::$fixture['year'],(string)time()]);
        $jobs['dunning']['session']=$session;
        // Both recalculation copies run in separate processes against the same disposable tenant.
        $barrier='/tmp/t2b7-'.bin2hex(random_bytes(6));
        $children=[];
        foreach ($jobs as $mode=>$data) {
            $output=$barrier.'-'.$mode.'.out';$error=$barrier.'-'.$mode.'.err';
            $proc=proc_open([PHP_BINARY,__DIR__.'/../characterization/support/run_payment_settlement.php',RestApiEnv::testDb(),$mode,json_encode($data),$barrier],
                [0=>['pipe','r'],1=>['file',$output,'w'],2=>['file',$error,'w']],$pipes);
            fclose($pipes[0]);
            $children[]=[$proc,$output,$error,$data];
        }
        touch($barrier);
        try {
            $payment=$this->register($body);
            foreach ($children as [$proc,$output,$error,$data]) {
                self::assertSame(0,proc_close($proc),file_get_contents($error).file_get_contents($output));
                $rows=PaymentFixture::query(self::$tenant,'SELECT udlign_id,udlignet FROM openpost WHERE id=ANY($1::integer[])',['{'.implode(',',$data['ids']).'}']);
                self::assertSame(array_fill(0,count($rows),'1'),array_column($rows,'udlignet'));
                self::assertCount(1,array_unique(array_column($rows,'udlign_id')));
            }
            $groupIds=[];
            foreach ($jobs as $data) {
                $groupIds[]=(int)PaymentFixture::query(self::$tenant,'SELECT udlign_id FROM openpost WHERE id=$1',[$data['ids'][0]])[0]['udlign_id'];
            }
            $groupIds[]=(int)PaymentFixture::query(self::$tenant,'SELECT udlign_id FROM openpost WHERE id=$1',[$body['openpost_id']])[0]['udlign_id'];
            $creditRows=$this->creditSettlement($jobs['credit-note']);
            $settled=PaymentFixture::query(self::$tenant,'SELECT id,udlign_id,udlign_date FROM openpost WHERE udlign_id=ANY($1::integer[]) ORDER BY id',['{'.implode(',',$groupIds).'}']);
            $next=$this->body();$next['date']='2026-04-15';
            $this->register($next);
            self::assertSame($creditRows,$this->creditSettlement($jobs['credit-note']));
            self::assertSame($settled,PaymentFixture::query(self::$tenant,'SELECT id,udlign_id,udlign_date FROM openpost WHERE udlign_id=ANY($1::integer[]) ORDER BY id',['{'.implode(',',$groupIds).'}']));
            $groupIds[]=(int)PaymentFixture::query(self::$tenant,'SELECT udlign_id FROM openpost WHERE id=$1',[$next['openpost_id']])[0]['udlign_id'];
            self::assertCount(8,array_unique($groupIds));
            self::assertGreaterThan(0,min($groupIds));
        } finally {
            PaymentFixture::query($registry,'DELETE FROM online WHERE session_id=$1',[$session]);
            pg_close($registry);
            unlink($barrier);
            foreach ($children as [$proc,$output,$error,$data]) {
                if (is_resource($proc)) { proc_terminate($proc);proc_close($proc); }
                unlink($output);unlink($error);
            }
        }
    }

    /** @return array{tenant_id:int,customer:int,ids:list<int>,order:int} */
    private function creditNoteJob(): array
    {
        $number=(string)++self::$serial;
        $invoiceId=PaymentFixture::invoice(self::$tenant,self::$fixture,$number,'123.00');
        $invoice=PaymentFixture::query(self::$tenant,'SELECT refnr FROM openpost WHERE id=$1',[$invoiceId])[0];
        $credit=PaymentFixture::query(self::$tenant,"INSERT INTO ordrer (konto_id,kontonr,firmanavn,art,status,fakturanr,fakturadate,ordredate,levdate,valuta,sum,moms,momssats,kred_ord_id,betalingsbet,betalingsdage,projekt,afd,ref,betalt) VALUES ($1,$2,'Payment fixture','DK',3,$3,'2026-02-20','2026-02-20','2026-02-20','DKK',-123,0,0,$4,'Netto',8,0,0,'',0) RETURNING id",[self::$fixture['debtor'],self::$fixture['number'],$number.'-K',$invoice['refnr']])[0]['id'];
        PaymentFixture::query(self::$tenant,"INSERT INTO ordrelinjer (ordre_id,posnr,antal,pris,rabat,momssats,momsfri,bogf_konto,projekt,procent,vat_account) VALUES ($1,1,-1,123,0,0,'on',$2,'',100,0)",[$credit,self::$fixture['bank']]);
        return ['tenant_id'=>RestApiEnv::regnskabId(RestApiEnv::accountOpen()),'customer'=>self::$fixture['debtor'],'ids'=>[$invoiceId],'order'=>(int)$credit];
    }

    /** @return list<array<string,string|null>> */
    private function creditSettlement(array $job): array
    {
        $rows=PaymentFixture::query(self::$tenant,'SELECT id,udlign_id,udlign_date,udlignet,amount FROM openpost WHERE id=$1 OR refnr=$2 ORDER BY id',[$job['ids'][0],$job['order']]);
        self::assertCount(2,$rows);
        self::assertSame(['1','1'],array_column($rows,'udlignet'));
        self::assertSame(['2026-02-20','2026-02-20'],array_column($rows,'udlign_date'));
        self::assertCount(1,array_unique(array_column($rows,'udlign_id')));
        self::assertGreaterThan(0,(int)$rows[0]['udlign_id']);
        self::assertSame([123.0,-123.0],array_map('floatval',array_column($rows,'amount')));
        return $rows;
    }

    public function testSequentialPaymentCreditNotePaymentKeepsDistinctIdsAndDates(): void
    {
        $first=$this->body();
        $this->register($first);
        $firstRows=PaymentFixture::query(self::$tenant,'SELECT id,udlign_id,udlign_date FROM openpost WHERE udlign_id=(SELECT udlign_id FROM openpost WHERE id=$1) ORDER BY id',[$first['openpost_id']]);
        $job=$this->creditNoteJob();
        $barrier='/tmp/t2b7-'.bin2hex(random_bytes(6));
        touch($barrier);
        try {
            $proc=proc_open([PHP_BINARY,__DIR__.'/../characterization/support/run_payment_settlement.php',RestApiEnv::testDb(),'credit-note',json_encode($job),$barrier],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            fclose($pipes[0]);
            $output=stream_get_contents($pipes[1]);fclose($pipes[1]);
            $error=stream_get_contents($pipes[2]);fclose($pipes[2]);
            self::assertSame(0,proc_close($proc),$error.$output);
            $creditRows=$this->creditSettlement($job);
            $last=$this->body();
            $last['date']='2026-04-15';
            $this->register($last);
            $lastId=(int)PaymentFixture::query(self::$tenant,'SELECT udlign_id FROM openpost WHERE id=$1',[$last['openpost_id']])[0]['udlign_id'];
            self::assertSame($creditRows,$this->creditSettlement($job));
            self::assertSame($firstRows,PaymentFixture::query(self::$tenant,'SELECT id,udlign_id,udlign_date FROM openpost WHERE udlign_id=$1 ORDER BY id',[$firstRows[0]['udlign_id']]));
            self::assertCount(3,array_unique([(int)$firstRows[0]['udlign_id'],(int)$creditRows[0]['udlign_id'],$lastId]));
        } finally {
            unlink($barrier);
        }
    }

}
