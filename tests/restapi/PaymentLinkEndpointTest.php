<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/PaymentLinkEndpointTest.php --- ver 5.0.0 --- 2026-10-05 ---
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
// 20261005 CDX/LH Expect immediate capture when status polling observes authorization.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/PaymentLinkTestEnv.php';

final class PaymentLinkEndpointTest extends TestCase
{
    private const PATH = '/restapi/endpoints/v1/payments/links.php';
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
    }
    private function create(?array $body = null): array
    {
        $response = PaymentLinkTestEnv::request('POST',self::PATH,$body ?? PaymentLinkTestEnv::body());
        self::assertSame(201,$response['status'],$response['body']);
        return $response['json']['data'];
    }
    public function testOptionsRequireAllConfigurationAndDkkInvoice(): void
    {
        $body = PaymentLinkTestEnv::body();
        $path = '/restapi/endpoints/v1/payments/options.php?openpost_id='.$body['openpost_id'];
        $options = PaymentLinkTestEnv::request('GET',$path);
        self::assertSame(200,$options['status'],$options['body']);
        self::assertTrue($options['json']['data']['payable']);
        foreach (['client_id','client_secret','subscriptionKey','MSN','invoice_webhook_secret','invoice_webhook_url'] as $key) {
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"UPDATE settings SET var_value='' WHERE var_grp='mobilepay' AND var_name=$1",[$key]);
            self::assertFalse(PaymentLinkTestEnv::request('GET',$path)['json']['data']['providers'][0]['configured']);
            PaymentLinkTestEnv::configure();
        }
        $body = PaymentLinkTestEnv::body('EUR');
        $refused = PaymentLinkTestEnv::request('POST',self::PATH,$body);
        self::assertSame(422,$refused['status']);
        self::assertSame('currency_not_supported',$refused['json']['data']['details']['reason']);
    }
    public function testCreateReplayChangedBodyAndOneActiveLink(): void
    {
        $body = PaymentLinkTestEnv::body();
        $first = $this->create($body);
        self::assertSame('1000.00',$first['amount']);
        self::assertMatchesRegularExpression('~^https://pay\.example\.invalid/saldi/debitor/payments/link/betal\.php\?l=[1-9][0-9]*-[a-f0-9]{32}$~',$first['url']);
        self::assertStringEndsWith('Z',$first['expires_at']);
        $replay = PaymentLinkTestEnv::request('POST',self::PATH,$body);
        self::assertSame($first,$replay['json']['data']);
        self::assertSame('true',$replay['headers']['idempotent-replayed']);
        $body['client_id'] = PaymentLinkTestEnv::uuid();
        self::assertSame($first,$this->create($body));
        $body['provider'] = 'other';
        $conflict = PaymentLinkTestEnv::request('POST',self::PATH,$body);
        self::assertSame(409,$conflict['status']);
        self::assertSame('client_id_reused',$conflict['json']['data']['details']['reason']);
        self::assertCount(1,RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"SELECT id FROM payment_links WHERE openpost_id=$1 AND state='active'",[$body['openpost_id']]));
        self::assertSame([],PaymentLinkTestEnv::control()['payments']);
    }
    public function testGetNeverCreatesProviderPaymentAndStatusUrlIsRecoverable(): void
    {
        $link = $this->create();
        $result = PaymentLinkTestEnv::request('GET',self::PATH.'?id='.$link['id']);
        self::assertSame(200,$result['status'],$result['body']);
        self::assertSame($link['url'],$result['json']['data']['url']);
        self::assertCount(0,RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT id FROM payment_attempts WHERE link_id=$1',[$link['id']]));
    }
    public function testCancelReplayAndAuthorizationBlocksCancellation(): void
    {
        $link = $this->create();
        $service = PaymentLinkTestEnv::service();
        $attempt = $service->startAttempt($link['id'],$link['url']);
        $body = ['client_id'=>PaymentLinkTestEnv::uuid()];
        $cancel = PaymentLinkTestEnv::request('DELETE',self::PATH.'?id='.$link['id'],$body);
        self::assertSame(200,$cancel['status'],$cancel['body']);
        self::assertSame('cancelled',$cancel['json']['data']['state']);
        self::assertSame('cancelled',RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT state FROM payment_attempts WHERE id=$1',[$attempt['attempt_id']])[0]['state']);
        self::assertSame('true',PaymentLinkTestEnv::request('DELETE',self::PATH.'?id='.$link['id'],$body)['headers']['idempotent-replayed']);
        $link = $this->create();
        $attempt = $service->startAttempt($link['id'],$link['url']);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"UPDATE payment_attempts SET state='authorized' WHERE id=$1",[$attempt['attempt_id']]);
        $refused = PaymentLinkTestEnv::request('DELETE',self::PATH.'?id='.$link['id'],['client_id'=>PaymentLinkTestEnv::uuid()]);
        self::assertSame(409,$refused['status']);
        self::assertSame('payment_in_progress',$refused['json']['data']['details']['reason']);
    }
    public function testMailAttachmentAndReplaySendExactlyOneLinkAndPdf(): void
    {
        DocumentFixture::configureMail(PaymentLinkTestEnv::$tenant);
        $fixture = DocumentFixture::seed(PaymentLinkTestEnv::$tenant,4);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,refnr,udlignet,transdate,forfaldsdate,valuta,valutakurs,projekt,kladde_id)
            VALUES($1,91901,'91901',312.50,$2,'0',CURRENT_DATE,CURRENT_DATE,'DKK',100,0,0)",[$fixture['customer_id'],$fixture['order_id']]);
        $body = ['client_id'=>PaymentLinkTestEnv::uuid(),'order_id'=>$fixture['order_id'],'channel'=>'email','attach_payment_link'=>true];
        $path = '/restapi/endpoints/v1/debitor/sales/deliver.php';
        $response = PaymentLinkTestEnv::request('POST',$path,$body);
        self::assertSame(200,$response['status'],$response['body']);
        self::assertSame('sent',$response['json']['data']['delivery']['state'],$response['body']);
        $url = $response['json']['data']['payment_link']['url'];
        $messages = DocumentFixture::messages($fixture['recipient']);
        self::assertSame(1,$messages['total']);
        self::assertSame(1,$messages['messages'][0]['Attachments']);
        $mail = json_decode(file_get_contents('http://'.getenv('SMTP_HOST').':8025/api/v1/message/'.$messages['messages'][0]['ID']),true);
        self::assertStringContainsString(htmlspecialchars($url,ENT_QUOTES),$mail['HTML']);
        self::assertStringContainsString($url,$mail['Text']);
        self::assertStringNotContainsString('$betalingslink',$mail['HTML']);
        $replay = PaymentLinkTestEnv::request('POST',$path,$body);
        self::assertSame('true',$replay['headers']['idempotent-replayed']);
        self::assertSame($response['json'],$replay['json']);
        self::assertSame(1,DocumentFixture::messages($fixture['recipient'])['total']);
        self::assertSame($url,PaymentLinkTestEnv::service()->url($response['json']['data']['payment_link']['id']));
        require_once __DIR__ . '/../../includes/payments/paymentLinkToken.php';
        $previous = $GLOBALS['connection'] ?? null;
        $GLOBALS['connection'] = PaymentLinkTestEnv::$tenant;
        try {
            self::assertSame($url,paymentLinkUrl($fixture['order_id']));
        } finally {
            $GLOBALS['connection'] = $previous;
        }
    }
    public function testAttachOptionRejectsQuoteAndNonEmailWithoutReservingLink(): void
    {
        $fixture = DocumentFixture::seed(PaymentLinkTestEnv::$tenant,0);
        foreach (['email','none','ean'] as $channel) {
            $response = PaymentLinkTestEnv::request('POST','/restapi/endpoints/v1/debitor/sales/deliver.php',
                ['client_id'=>PaymentLinkTestEnv::uuid(),'order_id'=>$fixture['order_id'],'channel'=>$channel,'attach_payment_link'=>true]);
            self::assertSame(422,$response['status'],$response['body']);
            self::assertCount(0,RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT id FROM payment_links WHERE ordre_id=$1',[$fixture['order_id']]));
        }
    }
    public function testCreateRateLimitHasRetryAfterAndNoReservation(): void
    {
        $key = hash('sha256',RestApiEnv::regnskabId(RestApiEnv::accountOpen()).':'.PaymentLinkTestEnv::subject());
        mobileT2RegistryStore(PaymentLinkTestEnv::$registry);
        RestApiEnv::rows(PaymentLinkTestEnv::$registry,"INSERT INTO mobile_rate_buckets(scope,bucket_key,tokens,updated_at) VALUES('paylink_create',$1,0,now())
            ON CONFLICT(scope,bucket_key) DO UPDATE SET tokens=0,updated_at=now()",[$key]);
        try {
            $body = PaymentLinkTestEnv::body();
            $response = PaymentLinkTestEnv::request('POST',self::PATH,$body);
            self::assertSame(429,$response['status'],$response['body']);
            self::assertSame('rate_limited',$response['json']['data']['error']);
            self::assertArrayHasKey('retry-after',$response['headers']);
            self::assertCount(0,RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT id FROM payment_links WHERE openpost_id=$1',[$body['openpost_id']]));
        } finally {
            RestApiEnv::rows(PaymentLinkTestEnv::$registry,"DELETE FROM mobile_rate_buckets WHERE scope='paylink_create' AND bucket_key=$1",[$key]);
        }
    }
    public function testReadAndWriteUseTheirExactSeparateRights(): void
    {
        $user = (int)RestApiEnv::loginData()['user']['id'];
        $link = $this->create();
        try {
            foreach ([['debitorrapport',12],['debitorordre',5]] as [$module,$position]) {
                $digits = str_repeat('0',16);$digits[$position] = '1';
                RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',[$digits,$user]);
                $read = PaymentLinkTestEnv::request('GET',self::PATH.'?id='.$link['id']);
                $write = PaymentLinkTestEnv::request('POST',self::PATH,PaymentLinkTestEnv::body());
                self::assertSame($module === 'debitorrapport' ? 200:403,$read['status'],$read['body']);
                self::assertSame($module === 'debitorordre' ? 201:403,$write['status'],$write['body']);
            }
        } finally {
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',[str_repeat('1',16),$user]);
        }
    }
    public function testConcurrentCreatesForSamePostReserveOneLink(): void
    {
        $body = PaymentLinkTestEnv::body();
        $multi = curl_multi_init();$handles = [];
        for ($i=0;$i<2;$i++) {
            $body['client_id'] = PaymentLinkTestEnv::uuid();
            $ch = curl_init(RestApiEnv::baseUrl().self::PATH);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),
                CURLOPT_HTTPHEADER=>array_merge(RestApiEnv::authHeaders(),['Content-Type: application/json']),CURLOPT_TIMEOUT=>30]);
            curl_multi_add_handle($multi,$ch);$handles[] = $ch;
        }
        do {
            curl_multi_exec($multi,$running);
            if ($running) { curl_multi_select($multi,0.1); }
        } while ($running);
        $results = [];
        foreach ($handles as $ch) {
            $raw = curl_multi_getcontent($ch);
            self::assertSame(201,curl_getinfo($ch,CURLINFO_RESPONSE_CODE),$raw);
            $results[] = json_decode($raw,true)['data'];
            curl_multi_remove_handle($multi,$ch);curl_close($ch);
        }
        curl_multi_close($multi);
        self::assertSame($results[0],$results[1]);
        self::assertCount(1,RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"SELECT id FROM payment_links WHERE openpost_id=$1 AND state='active'",[$body['openpost_id']]));
    }
    public function testStatusPollIsThrottledAndOnlyObservesExistingAttempt(): void
    {
        $link = $this->create();
        $service = PaymentLinkTestEnv::service();
        $attempt = $service->startAttempt($link['id'],$link['url']);
        $row = RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT * FROM payment_attempts WHERE id=$1',[$attempt['attempt_id']])[0];
        PaymentLinkTestEnv::control(['reference'=>$row['provider_reference'],'payment'=>['state'=>'AUTHORIZED']]);
        $first = PaymentLinkTestEnv::request('GET',self::PATH.'?id='.$link['id']);
        self::assertSame(200,$first['status'],$first['body']);
        self::assertSame('captured',RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT state FROM payment_attempts WHERE id=$1',[$attempt['attempt_id']])[0]['state']);
        $calls = count(PaymentLinkTestEnv::control()['calls']);
        PaymentLinkTestEnv::request('GET',self::PATH.'?id='.$link['id']);
        self::assertSame($calls,count(PaymentLinkTestEnv::control()['calls']));
        self::assertCount(1,RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT id FROM payment_attempts WHERE link_id=$1',[$link['id']]));
    }

    public function testProviderFailureFromStatusIsSafe503Json(): void
    {
        $link = $this->create();
        PaymentLinkTestEnv::service()->startAttempt($link['id'],$link['url']);
        PaymentLinkTestEnv::control(['mode'=>'unavailable']);
        try {
            $response = PaymentLinkTestEnv::request('GET',self::PATH.'?id='.$link['id']);
            self::assertSame(503,$response['status'],$response['body']);
            self::assertSame('upstream_unavailable',$response['json']['data']['error']);
            self::assertStringNotContainsString('<',$response['body']);
            self::assertArrayHasKey('x-request-id',$response['headers']);
        } finally {
            PaymentLinkTestEnv::control(['mode'=>'']);
        }
    }

}
