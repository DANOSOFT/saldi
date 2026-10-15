<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/PaymentWebhookTest.php --- ver 5.0.0 --- 2026-10-05 ---
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
// 20261005 CDX/LH Cover provider event names and both POS authentication headers.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/PaymentSettlementTestEnv.php';

final class PaymentWebhookTest extends TestCase
{
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

    public function testInvalidSignatureHashAndDateStoreNothing(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $event = PaymentSettlementTestEnv::event($pair['attempt']);
        foreach ([['signature'=>base64_encode(random_bytes(32))], ['hash'=>base64_encode(random_bytes(32))],
            ['date'=>gmdate('D, d M Y H:i:s', time()-301) . ' GMT'], ['date'=>gmdate('D, d M Y H:i:s', time()+601) . ' GMT'],
            ['date'=>'invalid']] as $overrides) {
            $response = PaymentSettlementTestEnv::webhook($event, $overrides);
            self::assertSame(401, $response['status'], $response['body']);
        }
        self::assertSame([], RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT id FROM payment_provider_events WHERE provider_reference=$1', [$event['reference']]));
        self::assertSame('created', PaymentSettlementTestEnv::row((int)$pair['attempt']['id'])['state']);
    }

    public function testCapturedBeforeAuthorizedAndDuplicateSettleOnce(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $a = $pair['attempt'];
        PaymentSettlementTestEnv::remote($a, 'AUTHORIZED', (int)$a['amount_minor']);
        $event = PaymentSettlementTestEnv::event($a);
        $first = PaymentSettlementTestEnv::webhook($event);
        self::assertSame(200, $first['status'], $first['body']);
        $row = PaymentSettlementTestEnv::row((int)$a['id']);
        self::assertSame('booked', $row['booking']);
        $duplicate = PaymentSettlementTestEnv::webhook($event);
        self::assertSame(200, $duplicate['status']);
        self::assertTrue(json_decode($duplicate['body'], true)['duplicate']);
        $late = PaymentSettlementTestEnv::webhook(PaymentSettlementTestEnv::event($a, 'authorized'), ['fallback'=>true]);
        self::assertSame(200, $late['status']);
        self::assertSame($row['registration_id'], PaymentSettlementTestEnv::row((int)$a['id'])['registration_id']);
        self::assertCount(1, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT id FROM payment_registrations WHERE client_id=$1', ['vipps:' . $a['provider_reference']]));
        self::assertCount(1, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "SELECT id FROM notifications WHERE ref_id=$1 AND type='invoice_paid'", [$pair['link']['openpost_id']]));
        self::assertSame('0.00', PaymentLinkTestEnv::service()->status($pair['link']['id'], false)['rest_after']);
        $inbox = PaymentLinkTestEnv::request('GET', '/restapi/endpoints/v1/notifications/?limit=100');
        self::assertSame(200, $inbox['status'], $inbox['body']);
        $items = array_filter($inbox['json']['data']['items'], static fn(array $item): bool => $item['ref_id'] === $pair['link']['openpost_id']);
        self::assertCount(1, $items);
        self::assertSame('openpost', array_values($items)[0]['ref_type']);
    }

    public function testForeignAndUnknownReferencesAreIgnored(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        foreach (['saldi-999999999-1-1','pos-payment-1', 'saldi-' . RestApiEnv::regnskabId(RestApiEnv::accountOpen()) . '-999999-1'] as $ref) {
            $event = PaymentSettlementTestEnv::event($pair['attempt']);
            $event['reference'] = $ref;
            $response = PaymentSettlementTestEnv::webhook($event);
            self::assertSame(200, $response['status'], $response['body']);
            self::assertSame('ignored', RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT state FROM payment_provider_events WHERE provider_reference=$1', [$ref])[0]['state']);
        }
    }

    public function testPayloadAndSubscriptionNamesNormalizeToOneReplayKey(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $event = PaymentSettlementTestEnv::event($pair['attempt'], 'authorized');
        $first = PaymentSettlementService::receive(PaymentLinkTestEnv::$tenant, json_encode($event));
        foreach (['authorized','AuThOrIzEd','epayments.payment.authorized.v1','EPAYMENTS.PAYMENT.AUTHORIZED.V1'] as $name) {
            $event['name'] = $name;
            $duplicate = PaymentSettlementService::receive(PaymentLinkTestEnv::$tenant, json_encode($event));
            self::assertTrue($duplicate['duplicate']);
            self::assertSame($first['id'], $duplicate['id']);
        }
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED');
        PaymentSettlementTestEnv::settlement()->processEvent($first['id']);
        self::assertSame('booked', PaymentSettlementTestEnv::row((int)$pair['attempt']['id'])['booking']);
    }

    public function testCreatedAndTerminalPayloadsAreProcessedWithoutCapture(): void
    {
        foreach (['CREATED','ABORTED','EXPIRED','CANCELLED','TERMINATED'] as $state) {
            $pair = PaymentSettlementTestEnv::attempt();
            PaymentSettlementTestEnv::remote($pair['attempt'], $state);
            $event = PaymentSettlementTestEnv::event($pair['attempt'], $state);
            $response = PaymentSettlementTestEnv::webhook($event);
            self::assertSame(200, $response['status'], $response['body']);
            self::assertSame(strtolower($state), PaymentSettlementTestEnv::row((int)$pair['attempt']['id'])['state']);
            self::assertSame('processed', RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
                'SELECT state FROM payment_provider_events WHERE provider_reference=$1', [$event['reference']])[0]['state']);
            $calls = array_filter(PaymentLinkTestEnv::control()['calls'], static fn(array $call): bool =>
                $call['path'] === '/epayment/v1/payments/' . $event['reference'] . '/capture');
            self::assertCount(0, $calls);
            self::assertTrue(json_decode(PaymentSettlementTestEnv::webhook($event)['body'], true)['duplicate']);
        }
    }

    public function testAmountAndCurrencyMismatchAreReviewWithoutPosting(): void
    {
        foreach ([[100001,'DKK'],[100000,'EUR']] as [$amount,$currency]) {
            $pair = PaymentSettlementTestEnv::attempt();
            PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', $amount, $amount, $currency);
            $response = PaymentSettlementTestEnv::webhook(PaymentSettlementTestEnv::event($pair['attempt']));
            self::assertSame(200, $response['status'], $response['body']);
            $row = PaymentSettlementTestEnv::row((int)$pair['attempt']['id']);
            self::assertSame('review', $row['booking']);
            self::assertNull($row['registration_id']);
            $status = PaymentLinkTestEnv::service()->status($pair['link']['id'], false);
            self::assertSame('review', $status['state']);
            self::assertSame($amount === 100001 ? '1000.01' : '1000.00', $status['paid_amount']);
            self::assertSame($currency, $status['paid_currency']);
            self::assertSame([['amount'=>$status['paid_amount'],'currency'=>$currency]], $status['received_amounts']);
            self::assertSame((string)$amount, $row['captured_minor']);
            self::assertSame($currency, $row['captured_currency']);
            self::assertCount(1, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "SELECT id FROM notifications WHERE ref_id=$1 AND type='payment_review'", [$pair['link']['openpost_id']]));
        }
    }

    public function testMismatchedAuthorizationCancelledWithoutCapture(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 0, 99999);
        PaymentSettlementTestEnv::webhook(PaymentSettlementTestEnv::event($pair['attempt'], 'authorized'));
        self::assertSame('cancelled', PaymentSettlementTestEnv::row((int)$pair['attempt']['id'])['state']);
        $state = PaymentLinkTestEnv::control();
        self::assertSame(0, $state['payments'][$pair['attempt']['provider_reference']]['aggregate']['capturedAmount']['value']);
    }

    public function testMissingAccountIsUnsettledAndRecoveryNotifiesOnce(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $a = $pair['attempt'];
        PaymentSettlementTestEnv::remote($a, 'AUTHORIZED', (int)$a['amount_minor']);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "DELETE FROM settings WHERE var_grp='mobile_betaling' AND var_name='account_mobilepay'");
        self::assertSame(200, PaymentSettlementTestEnv::webhook(PaymentSettlementTestEnv::event($a))['status']);
        self::assertSame('unsettled', PaymentSettlementTestEnv::row((int)$a['id'])['booking']);
        PaymentSettlementTestEnv::settlement()->synchronize($a['provider_reference']);
        self::assertCount(1, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "SELECT id FROM notifications WHERE ref_id=$1 AND type='payment_unsettled'", [$pair['link']['openpost_id']]));
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "INSERT INTO settings(var_grp,var_name,var_value,user_id) VALUES('mobile_betaling','account_mobilepay',$1,0)", [(string)PaymentLinkTestEnv::$fixture['bank']]);
        PaymentSettlementTestEnv::settlement()->synchronize($a['provider_reference']);
        self::assertSame('booked', PaymentSettlementTestEnv::row((int)$a['id'])['booking']);
    }

    public function testProviderOutageAcknowledgesDurableEventAndCronRetries(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 100000);
        PaymentLinkTestEnv::control(['mode'=>'unavailable']);
        try {
            self::assertSame(200, PaymentSettlementTestEnv::webhook(PaymentSettlementTestEnv::event($pair['attempt']))['status']);
            self::assertSame('failed', RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT state FROM payment_provider_events WHERE provider_reference=$1', [$pair['attempt']['provider_reference']])[0]['state']);
        } finally {
            PaymentLinkTestEnv::control(['mode'=>'']);
        }
        PaymentSettlementTestEnv::settlement()->run();
        self::assertSame('booked', PaymentSettlementTestEnv::row((int)$pair['attempt']['id'])['booking']);
    }

    public function testPosReceiverIgnoresInvoiceReferenceButStillWritesPos(): void
    {
        $secret = bin2hex(random_bytes(32));
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "INSERT INTO settings(var_grp,var_name,var_value,user_id) VALUES('mobilepay','webhook_secret',$1,0)", [$secret]);
        $folder = dirname(__DIR__, 2) . '/temp/' . RestApiEnv::testDb();
        if (!is_dir($folder)) {
            mkdir($folder, 0700, true);
        }
        $ref = 'saldi-' . RestApiEnv::regnskabId(RestApiEnv::accountOpen()) . '-123-1';
        $posReferences = [];
        try {
            foreach ([false,true] as $fallback) {
                $pos = 'pos-' . bin2hex(random_bytes(4));
                $posReferences[] = $pos;
                self::assertSame(200, PaymentSettlementTestEnv::webhook(['reference'=>$ref], ['fallback'=>$fallback], true)['status']);
                self::assertFileDoesNotExist($folder . '/data_' . $ref . '.json');
                $payload = ['reference'=>$pos,'name'=>'AUTHORIZED','success'=>true];
                self::assertSame(401, PaymentSettlementTestEnv::webhook($payload,
                    ['fallback'=>$fallback,'signature'=>base64_encode(random_bytes(32))], true)['status']);
                self::assertFileDoesNotExist($folder . '/data_' . $pos . '.json');
                self::assertSame(200, PaymentSettlementTestEnv::webhook($payload, ['fallback'=>$fallback], true)['status']);
                self::assertSame($payload, json_decode(file_get_contents($folder . '/data_' . $pos . '.json'), true));
            }
        } finally {
            foreach ($posReferences as $pos) {
                if (is_file($folder . '/data_' . $pos . '.json')) {
                    unlink($folder . '/data_' . $pos . '.json');
                }
            }
        }
    }
    public function testEnsureWebhookPersistsSecretAndMarkerAndRecoversLostSecret(): void
    {
        $files = '/tmp/t2b8b-hook-' . bin2hex(random_bytes(8));
        $scenario = $files . '.json';
        $log = $files . '.log';
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $secret = bin2hex(random_bytes(32));
        file_put_contents($scenario, json_encode([
            'token'=>['code'=>200,'body'=>json_encode(['access_token'=>bin2hex(random_bytes(32))])],
            'list'=>['code'=>200,'body'=>json_encode(['webhooks'=>[]])],
            'register'=>['code'=>201,'body'=>json_encode(['secret'=>$secret])],
            'delete'=>['code'=>204,'body'=>''],
        ]));
        $process = proc_open([PHP_BINARY,'-S','127.0.0.1:' . $port,__DIR__ . '/../fixtures/mobilepay/vipps_stub.php'],
            [0=>['pipe','r'],1=>['file',$files . '.out','w'],2=>['file',$files . '.out','w']], $pipes, null,
            array_merge(getenv(), ['MP_STUB_SCENARIO'=>$scenario,'MP_STUB_LOG'=>$log]));
        fclose($pipes[0]);
        usleep(200000);
        $id = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        try {
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "UPDATE settings SET var_value=$1 WHERE var_grp='mobilepay' AND var_name='epayment_api_base'", ['http://127.0.0.1:' . $port]);
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "UPDATE settings SET var_value='' WHERE var_grp='mobilepay' AND var_name IN ('invoice_webhook_url','invoice_webhook_secret')");
            self::assertTrue(PaymentSettlementService::ensureWebhook(PaymentLinkTestEnv::$tenant, $id));
            $values = array_column(RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "SELECT var_name,var_value FROM settings WHERE var_grp='mobilepay'"), 'var_value', 'var_name');
            self::assertSame($secret, $values['invoice_webhook_secret']);
            self::assertSame('https://pay.example.invalid/saldi/debitor/payments/link/webhook.php?t=' . $id, $values['invoice_webhook_url']);
            $calls = file_get_contents($log);
            self::assertTrue(PaymentSettlementService::ensureWebhook(PaymentLinkTestEnv::$tenant, $id));
            self::assertSame($calls, file_get_contents($log));
            $scenarioData = json_decode(file_get_contents($scenario), true);
            $scenarioData['list']['body'] = json_encode(['webhooks'=>[['id'=>'registered','url'=>$values['invoice_webhook_url']]]]);
            file_put_contents($scenario, json_encode($scenarioData));
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "UPDATE settings SET var_value='' WHERE var_grp='mobilepay' AND var_name='invoice_webhook_secret'");
            self::assertTrue(PaymentSettlementService::ensureWebhook(PaymentLinkTestEnv::$tenant, $id));
            self::assertStringContainsString('DELETE /webhooks/v1/webhooks/registered', file_get_contents($log));
            $scenarioData['delete'] = ['code'=>503,'body'=>''];
            file_put_contents($scenario, json_encode($scenarioData));
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "UPDATE settings SET var_value='' WHERE var_grp='mobilepay' AND var_name='invoice_webhook_secret'");
            self::assertFalse(PaymentSettlementService::ensureWebhook(PaymentLinkTestEnv::$tenant, $id));
            self::assertSame('', RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "SELECT var_value FROM settings WHERE var_grp='mobilepay' AND var_name='invoice_webhook_url'")[0]['var_value']);
        } finally {
            proc_terminate($process);
            proc_close($process);
            foreach ([$scenario,$log,$files . '.out'] as $file) {
                unlink($file);
            }
            PaymentLinkTestEnv::configure();
        }
    }

    public function testPaidNotificationIncludesAppCreatorAndDispatchAndLegacyInboxNullRefs(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $subject = PaymentLinkTestEnv::subject();
        $other = explode('/', $subject)[0] . '/account/987654';
        $link = PaymentLinkTestEnv::service()->link($pair['link']['id']);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "INSERT INTO mobile_order_origins(ordre_id,client_id,created_by,kind) VALUES($1,$2,$3,'ordre')",
            [$link['ordre_id'],PaymentLinkTestEnv::uuid(),$other]);
        foreach ([$subject,$other] as $owner) {
            $identity = Subject::parse($owner);
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "INSERT INTO push_tokens(user_id,token,platform,db_name,subject,principal,device_id)
                VALUES($1,$2,'ios',$3,$4,'account',$5)", [$identity['user_id'],'ExpoPushToken[' . bin2hex(random_bytes(16)) . ']',RestApiEnv::testDb(),$owner,'device-' . bin2hex(random_bytes(8))]);
        }
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 100000);
        PaymentSettlementTestEnv::webhook(PaymentSettlementTestEnv::event($pair['attempt']));
        $notifications = RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "SELECT id FROM notifications WHERE type='invoice_paid' AND ref_id=$1", [$pair['link']['openpost_id']]);
        self::assertCount(1, $notifications);
        self::assertCount(2, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT id FROM notification_recipients WHERE notification_id=$1', [$notifications[0]['id']]));
        self::assertCount(2, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT d.id FROM push_dispatch d JOIN notification_recipients r ON r.id=d.recipient_id WHERE r.notification_id=$1', [$notifications[0]['id']]));
        $legacy = (new PushService(PaymentLinkTestEnv::$tenant))->create([$subject],$subject,'reminder','Legacy','Message');
        $inbox = PaymentLinkTestEnv::request('GET', '/restapi/endpoints/v1/notifications/?limit=100');
        $items = array_values(array_filter($inbox['json']['data']['items'], static fn(array $item): bool => $item['notification_id'] === $legacy['notification_id']));
        self::assertCount(1, $items);
        self::assertNull($items[0]['ref_type']);
        self::assertNull($items[0]['ref_id']);
    }

}
