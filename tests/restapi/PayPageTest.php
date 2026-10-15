<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/PayPageTest.php --- ver 5.0.0 --- 2026-10-04 ---
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
// 20261001 CDX/LH Created. Invoice payment links.
// 20261001 CDX/LH Cover cancellation aggregates and stale failed-create recovery.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/PaymentLinkTestEnv.php';

final class PayPageTest extends TestCase
{
    private array $link;
    public static function setUpBeforeClass(): void
    {
        if ($reason = RestApiEnv::unavailableReason()) {
            self::markTestSkipped($reason);
        }
        PaymentLinkTestEnv::setup();
    }
    public static function tearDownAfterClass(): void
    {
        PaymentLinkTestEnv::teardown();
    }
    protected function setUp(): void
    {
        PaymentLinkTestEnv::configure();
        $this->link = PaymentLinkTestEnv::service()->create(PaymentLinkTestEnv::body())['body']['data'];
        // Reset only the disposable public test address bucket; restore it after the class.
        $registry = PaymentLinkTestEnv::$registry;
        mobileT2RegistryStore($registry);
        RestApiEnv::rows($registry,"DELETE FROM mobile_rate_buckets WHERE scope='paylink_public' AND bucket_key=$1",[hash('sha256','127.0.0.1')]);
    }
    protected function tearDown(): void
    {
        RestApiEnv::rows(PaymentLinkTestEnv::$registry,"DELETE FROM mobile_rate_buckets WHERE scope='paylink_public' AND bucket_key=$1",[hash('sha256','127.0.0.1')]);
    }
    private function url(): string
    {
        return RestApiEnv::baseUrl().'/debitor/payments/link/betal.php?'.parse_url($this->link['url'],PHP_URL_QUERY);
    }
    /** @return array{status:int,body:string,headers:array<string,string>} */
    private function page(string $method = 'GET', array $fields = [], ?string $cookie = null): array
    {
        $headers = [];
        $ch = curl_init($this->url());
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>30,
            CURLOPT_HEADERFUNCTION=>static function ($ch,string $line) use (&$headers): int {
                if (str_contains($line,':')) {
                    [$name,$value] = explode(':',$line,2);$headers[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            }]);
        if ($cookie !== null) { curl_setopt($ch,CURLOPT_COOKIE,$cookie); }
        if ($method === 'POST') { curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($fields)); }
        $raw = curl_exec($ch);$code = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        return ['status'=>$code,'body'=>(string)$raw,'headers'=>$headers];
    }
    /** @return array{string,string} */
    private function csrf(): array
    {
        $get = $this->page();
        self::assertSame(200,$get['status'],$get['body']);
        self::assertMatchesRegularExpression('/name="csrf" value="([a-f0-9]+)"/',$get['body']);
        preg_match('/name="csrf" value="([a-f0-9]+)"/',$get['body'],$match);
        return [$match[1],explode(';',$get['headers']['set-cookie'])[0]];
    }
    private function attempts(): array
    {
        return RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT * FROM payment_attempts WHERE link_id=$1 ORDER BY id',[$this->link['id']]);
    }
    public function testGetPreviewAndInvalidCsrfNeverCreatePayment(): void
    {
        [$csrf,$cookie] = $this->csrf();
        self::assertStringContainsString('1.000,00',$this->page()['body']);
        self::assertSame(403,$this->page('POST',['csrf'=>bin2hex(random_bytes(32))],$cookie)['status']);
        self::assertSame([],$this->attempts());
        $response = $this->page('POST',['csrf'=>$csrf],$cookie);
        self::assertSame(303,$response['status'],$response['body']);
        self::assertStringStartsWith('https://api.vipps.no/pay/',$response['headers']['location']);
        self::assertCount(1,$this->attempts());
    }
    public function testYoungAttemptIsReusedWithoutAnotherCreateCall(): void
    {
        [$csrf,$cookie] = $this->csrf();
        $first = $this->page('POST',['csrf'=>$csrf],$cookie);
        $before = count(PaymentLinkTestEnv::control()['calls']);
        $second = $this->page('POST',['csrf'=>$csrf],$cookie);
        self::assertSame($first['headers']['location'],$second['headers']['location']);
        self::assertSame($before,count(PaymentLinkTestEnv::control()['calls']));
        self::assertCount(1,$this->attempts());
    }
    public function testOldCreatedIsCancelledAndConfirmedBeforeNewPayment(): void
    {
        $service = PaymentLinkTestEnv::service();
        $first = $service->startAttempt($this->link['id'],$this->link['url']);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"UPDATE payment_attempts SET created_at=now()-interval '9 minutes 1 second' WHERE id=$1",[$first['attempt_id']]);
        $second = $service->startAttempt($this->link['id'],$this->link['url']);
        self::assertNotSame($first['attempt_id'],$second['attempt_id']);
        self::assertSame(['terminated','created'],array_column($this->attempts(),'state'));
        $calls = PaymentLinkTestEnv::control()['calls'];
        $ref = $this->attempts()[0]['provider_reference'];
        $cancel = array_search('/epayment/v1/payments/'.$ref.'/cancel',array_column($calls,'path'),true);
        $creates = array_keys(array_column($calls,'path'),'/epayment/v1/payments',true);
        self::assertLessThan(end($creates),$cancel);
    }
    public function testOldAuthorizedAndCapturingNeverGetSibling(): void
    {
        $service = PaymentLinkTestEnv::service();
        $first = $service->startAttempt($this->link['id'],$this->link['url']);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"UPDATE payment_attempts SET created_at=now()-interval '10 minutes' WHERE id=$1",[$first['attempt_id']]);
        PaymentLinkTestEnv::control(['reference'=>$this->attempts()[0]['provider_reference'],'payment'=>['state'=>'AUTHORIZED']]);
        self::assertSame('processing',$service->startAttempt($this->link['id'],$this->link['url'])['state']);
        self::assertSame('authorized',$this->attempts()[0]['state']);
        $get = $this->page();
        self::assertSame(200,$get['status'],$get['body']);
        self::assertStringContainsString('Betalingen er modtaget og behandles.',$get['body']);
        self::assertStringNotContainsString('<button>',$get['body']);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"UPDATE payment_attempts SET state='capturing' WHERE id=$1",[$first['attempt_id']]);
        self::assertSame('processing',$service->startAttempt($this->link['id'],$this->link['url'])['state']);
        self::assertCount(1,$this->attempts());
    }
    public function testAuthorizationBetweenGetAndCancelReleasesReservationAndAllowsRetry(): void
    {
        $service = PaymentLinkTestEnv::service();
        $first = $service->startAttempt($this->link['id'],$this->link['url']);
        $ref = $this->attempts()[0]['provider_reference'];
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"UPDATE payment_attempts SET created_at=now()-interval '10 minutes' WHERE id=$1",[$first['attempt_id']]);
        PaymentLinkTestEnv::control(['mode'=>'authorize_before_cancel']);
        $second = $service->startAttempt($this->link['id'],$this->link['url']);
        self::assertNotSame($first['attempt_id'],$second['attempt_id']);
        self::assertSame(['cancelled','created'],array_column($this->attempts(),'state'));
        $remote = PaymentLinkTestEnv::control()['payments'][$ref];
        self::assertSame('AUTHORIZED',$remote['state']);
        self::assertSame($remote['amount'],$remote['aggregate']['cancelledAmount']);
        self::assertSame(0,$remote['aggregate']['capturedAmount']['value']);
        self::assertSame('cancelled',(new VippsEpaymentProvider(PaymentLinkTestEnv::$tenant))->get($ref)['state']);
    }
    public function testCancellationAggregateValidationAndCapturePrecedence(): void
    {
        $first = PaymentLinkTestEnv::service()->startAttempt($this->link['id'],$this->link['url']);
        $ref = $this->attempts()[0]['provider_reference'];
        $provider = new VippsEpaymentProvider(PaymentLinkTestEnv::$tenant);
        foreach ([['value'=>-1,'currency'=>'DKK'],['value'=>'100000','currency'=>'DKK'],
            ['value'=>100000,'currency'=>'EUR'],['value'=>100001,'currency'=>'DKK'],['currency'=>'DKK']] as $cancelled) {
            PaymentLinkTestEnv::control(['reference'=>$ref,'payment'=>['state'=>'AUTHORIZED','aggregate'=>[
                'capturedAmount'=>['value'=>0,'currency'=>'DKK'],'cancelledAmount'=>$cancelled]]]);
            try {
                $provider->get($ref);
                self::fail('Malformed cancellation aggregate must fail closed');
            } catch (MobileT2Error $error) {
                self::assertSame(503,$error->status);
            }
        }
        foreach ([[0,50000,'authorized'],[0,100000,'cancelled'],[40000,60000,'captured']] as [$captured,$cancelled,$state]) {
            PaymentLinkTestEnv::control(['reference'=>$ref,'payment'=>['aggregate'=>[
                'capturedAmount'=>['value'=>$captured,'currency'=>'DKK'],
                'cancelledAmount'=>['value'=>$cancelled,'currency'=>'DKK']]]]);
            self::assertSame($state,$provider->get($ref)['state']);
        }
    }
    public function testReducedRestSupersedesLinkAndCreatesNoAttempt(): void
    {
        $post = $this->link['id'];
        $row = PaymentLinkTestEnv::service()->link($post);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'UPDATE openpost SET amount=500 WHERE id=$1',[$row['openpost_id']]);
        $result = PaymentLinkTestEnv::service()->startAttempt($post,$this->link['url']);
        self::assertSame('superseded',$result['state']);
        self::assertSame('superseded',PaymentLinkTestEnv::service()->link($post)['state']);
        self::assertSame([],$this->attempts());
    }
    public function testTwoConcurrentPostsShareOneAttemptAndRedirect(): void
    {
        [$csrf,$cookie] = $this->csrf();
        $multi = curl_multi_init();$handles = [];
        for ($i=0;$i<2;$i++) {
            $ch = curl_init($this->url());
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['csrf'=>$csrf]),
                CURLOPT_COOKIE=>$cookie,CURLOPT_TIMEOUT=>30]);
            curl_multi_add_handle($multi,$ch);$handles[] = $ch;
        }
        do {
            curl_multi_exec($multi,$running);
            if ($running) { curl_multi_select($multi,0.1); }
        } while ($running);
        foreach ($handles as $ch) {
            self::assertSame(303,curl_getinfo($ch,CURLINFO_RESPONSE_CODE),curl_multi_getcontent($ch));
            curl_multi_remove_handle($multi,$ch);curl_close($ch);
        }
        curl_multi_close($multi);
        self::assertCount(1,$this->attempts());
    }
    public function testCancelRefusalAndProviderOutageDoNotCreateSibling(): void
    {
        $service = PaymentLinkTestEnv::service();
        $first = $service->startAttempt($this->link['id'],$this->link['url']);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"UPDATE payment_attempts SET created_at=now()-interval '10 minutes' WHERE id=$1",[$first['attempt_id']]);
        foreach (['cancel_refused','unavailable'] as $mode) {
            PaymentLinkTestEnv::control(['mode'=>$mode]);
            try {
                $service->startAttempt($this->link['id'],$this->link['url']);
                self::fail('Expected provider failure');
            } catch (MobileT2Error $error) {
                self::assertSame(503,$error->status);
            }
            self::assertCount(1,$this->attempts());
            self::assertSame('created',$this->attempts()[0]['state']);
        }
    }
    public function testFailedCreateRetainsReferenceAndRetriesIdempotently(): void
    {
        $service = PaymentLinkTestEnv::service();
        PaymentLinkTestEnv::control(['mode'=>'unavailable']);
        try {
            $service->startAttempt($this->link['id'],$this->link['url']);
            self::fail('Expected provider failure');
        } catch (MobileT2Error $error) {
            self::assertSame(503,$error->status);
        }
        self::assertCount(1,$this->attempts());
        $ref = $this->attempts()[0]['provider_reference'];
        PaymentLinkTestEnv::control(['mode'=>'']);
        self::assertNotNull($service->startAttempt($this->link['id'],$this->link['url'])['redirect_url']);
        self::assertCount(1,$this->attempts());
        self::assertSame($ref,$this->attempts()[0]['provider_reference']);
    }
    public function testStaleFailedCreateRetriesOriginalPayloadOnlyAfterAuthoritativeNotFound(): void
    {
        [$csrf,$cookie] = $this->csrf();
        PaymentLinkTestEnv::control(['mode'=>'unavailable']);
        self::assertSame(503,$this->page('POST',['csrf'=>$csrf],$cookie)['status']);
        $attempt = $this->attempts()[0];
        self::assertNull($attempt['redirect_url']);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"UPDATE payment_attempts SET created_at=now()-interval '10 minutes' WHERE id=$1",[$attempt['id']]);
        $before = count(PaymentLinkTestEnv::control()['calls']);
        self::assertSame(503,$this->page('POST',['csrf'=>$csrf],$cookie)['status']);
        $newCalls = array_slice(PaymentLinkTestEnv::control()['calls'],$before);
        self::assertSame(['GET'],array_column($newCalls,'method'));
        self::assertCount(1,$this->attempts());
        PaymentLinkTestEnv::control(['mode'=>'']);
        self::assertSame('active',PaymentLinkTestEnv::service()->status($this->link['id'])['state']);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"UPDATE settings SET var_value=$1 WHERE var_grp='mobile_betaling' AND var_name='public_base_url'",['https://changed.example.invalid/saldi']);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"UPDATE settings SET var_value=$1 WHERE var_grp='mobilepay' AND var_name='invoice_webhook_url'",[
            'https://changed.example.invalid/saldi/debitor/payments/link/webhook.php?t='.RestApiEnv::regnskabId(RestApiEnv::accountOpen())]);
        $recovered = $this->page('POST',['csrf'=>$csrf],$cookie);
        self::assertSame(303,$recovered['status'],$recovered['body']);
        $reused = $this->page('POST',['csrf'=>$csrf],$cookie);
        self::assertSame(303,$reused['status'],$reused['body']);
        self::assertSame($recovered['headers']['location'],$reused['headers']['location']);
        self::assertCount(1,$this->attempts());
        self::assertSame($attempt['provider_reference'],$this->attempts()[0]['provider_reference']);
        $creates = array_values(array_filter(PaymentLinkTestEnv::control()['calls'],
            static fn(array $call): bool => $call['path'] === '/epayment/v1/payments'
                && $call['body']['reference'] === $attempt['provider_reference']));
        self::assertCount(2,$creates);
        self::assertSame($creates[0],$creates[1]);
        self::assertSame($attempt['provider_reference'].'-create',$creates[1]['key']);
    }
    public function testPublicRateLimitAndExpiry(): void
    {
        RestApiEnv::rows(PaymentLinkTestEnv::$registry,"INSERT INTO mobile_rate_buckets(scope,bucket_key,tokens,updated_at) VALUES('paylink_public',$1,0,now())",[hash('sha256','127.0.0.1')]);
        $response = $this->page();
        self::assertSame(429,$response['status'],$response['body']);
        self::assertArrayHasKey('retry-after',$response['headers']);
        self::assertSame([],$this->attempts());
        RestApiEnv::rows(PaymentLinkTestEnv::$registry,"DELETE FROM mobile_rate_buckets WHERE scope='paylink_public' AND bucket_key=$1",[hash('sha256','127.0.0.1')]);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"UPDATE payment_links SET expires_at=now()-interval '1 second' WHERE id=$1",[$this->link['id']]);
        $response = $this->page();
        self::assertSame(200,$response['status'],$response['body']);
        self::assertStringContainsString('Linket er udløbet',$response['body']);
        self::assertStringNotContainsString('<button>',$response['body']);
    }
    public function testWrongTokenAndTenantDoNotRevealInvoiceOrCreateAttempt(): void
    {
        $query = parse_url($this->link['url'],PHP_URL_QUERY);
        foreach (['l=invalid','l='.RestApiEnv::regnskabId(RestApiEnv::accountClosed()).'-'.substr($query,strrpos($query,'-')+1),
            'l='.RestApiEnv::regnskabId(RestApiEnv::accountOpen()).'-'.bin2hex(random_bytes(16))] as $bad) {
            $ch = curl_init(RestApiEnv::baseUrl().'/debitor/payments/link/betal.php?'.$bad);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
            $raw = curl_exec($ch);$status = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
            self::assertSame(404,$status,$raw);
            self::assertStringNotContainsString('1.000,00',$raw);
            self::assertStringNotContainsString('<button>',$raw);
        }
        self::assertSame([],$this->attempts());
    }

}
