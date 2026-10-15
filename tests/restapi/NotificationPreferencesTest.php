<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/NotificationPreferencesTest.php --- ver 5.0.0 --- 2026.10.08
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
// 20261001 CDX/LH Cover settlement creator opt-out without debtor report rights.
// 20261008 CL/LH Include the default-on Assist interrupt preference.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/T3EventTestEnv.php';

final class NotificationPreferencesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        T3EventTestEnv::setup();
    }
    public static function tearDownAfterClass(): void
    {
        T3EventTestEnv::teardown();
    }
    protected function setUp(): void
    {
        T3EventTestEnv::reset();
    }

    private function request(string $method = 'GET', ?array $body = null, bool $registry = false): array
    {
        $headers = [];
        $ch = curl_init(RestApiEnv::baseUrl() . '/restapi/endpoints/v1/notifications/preferences.php');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>20,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . T3EventTestEnv::token($registry),'Content-Type: application/json'],
            CURLOPT_HEADERFUNCTION=>static function ($ch, string $line) use (&$headers): int {
                if (str_contains($line,':')) {
                    [$key,$value] = explode(':',$line,2);
                    $headers[strtolower(trim($key))] = trim($value);
                }
                return strlen($line);
            }]);
        if ($body !== null) {
            curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
        }
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status'=>$status,'json'=>json_decode($raw,true),'body'=>$raw,'headers'=>$headers];
    }

    public function testOwnDefaultSwitchesFollowCurrentRightsAndRegistryMembership(): void
    {
        $reply = $this->request();
        self::assertSame(200,$reply['status'],$reply['body']);
        self::assertCount(8,$reply['json']['data']['items']);
        self::assertSame([true],array_values(array_unique(array_column($reply['json']['data']['items'],'enabled'))));
        RestApiEnv::rows(T3EventTestEnv::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',
            [str_repeat('0',16),T3EventTestEnv::$account['id']]);
        $reply = $this->request();
        self::assertSame(200,$reply['status'],$reply['body']);
        self::assertSame([['type'=>'attachment_decided','enabled'=>true]],$reply['json']['data']['items']);
        $reply = $this->request('GET',null,true);
        self::assertSame(200,$reply['status'],$reply['body']);
        self::assertCount(8,$reply['json']['data']['items']);
    }

    public function testUpdateIsSubjectScopedAtomicAndReplaysOriginalResponse(): void
    {
        $body = ['client_id'=>MobileAuth::uuid(),'items'=>[['type'=>'invoice_paid','enabled'=>false]]];
        $reply = $this->request('PUT',$body);
        self::assertSame(200,$reply['status'],$reply['body']);
        $expected = $reply['json'];
        self::assertFalse(array_column($reply['json']['data']['items'],'enabled','type')['invoice_paid']);
        self::assertTrue(array_column($this->request('GET',null,true)['json']['data']['items'],'enabled','type')['invoice_paid']);
        $httpReplay = $this->request('PUT',$body);
        self::assertSame($expected,$httpReplay['json']);
        self::assertSame('true',$httpReplay['headers']['idempotent-replayed']);
        $service = T3EventTestEnv::fanout();
        $replay = $service->updatePreferences(T3EventTestEnv::$subject,$body);
        self::assertSame(['Idempotent-Replayed'=>'true'],$replay['headers']);
        self::assertSame($expected,$replay['body']);
        $body['items'][0]['enabled'] = true;
        $conflict = $this->request('PUT',$body);
        self::assertSame(409,$conflict['status'],$conflict['body']);
        self::assertSame('client_id_reused',$conflict['json']['data']['details']['reason']);
        self::assertCount(1,RestApiEnv::rows(T3EventTestEnv::$tenant,'SELECT * FROM notification_preferences'));
    }

    /** @return array<string,array{0:string}> */
    public static function sellerRights(): array
    {
        return ['writable'=>['1'],'read_only'=>['2']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sellerRights')]
    public function testSellerCanDisableSettlementCreatorNotificationsWithoutReceivingBroadcasts(string $sellerRight): void
    {
        $rights = str_repeat('0',16);
        $rights[5] = $sellerRight;
        RestApiEnv::rows(T3EventTestEnv::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',
            [$rights,T3EventTestEnv::$account['id']]);
        $service = T3EventTestEnv::fanout();
        $push = new PushService(T3EventTestEnv::$tenant);

        T3EventTestEnv::emit();
        self::assertSame(1,$service->run()['recipients']);
        self::assertSame([],$push->inbox(T3EventTestEnv::$subject)['items']);

        $first = $this->settlementNotification();
        $inbox = $push->inbox(T3EventTestEnv::$subject)['items'];
        self::assertCount(1,$inbox);
        self::assertSame('invoice_paid',$inbox[0]['type']);
        self::assertSame($first,$inbox[0]['notification_id']);

        $reply = $this->request();
        self::assertSame(200,$reply['status'],$reply['body']);
        $switches = array_column($reply['json']['data']['items'],'enabled','type');
        self::assertArrayHasKey('invoice_paid',$switches);
        self::assertTrue($switches['invoice_paid']);
        self::assertArrayNotHasKey('invoice_overdue',$switches);
        self::assertArrayNotHasKey('invoice_overdue_digest',$switches);

        $reply = $this->request('PUT',['client_id'=>MobileAuth::uuid(),
            'items'=>[['type'=>'invoice_paid','enabled'=>false]]]);
        self::assertSame(200,$reply['status'],$reply['body']);
        self::assertFalse(array_column($reply['json']['data']['items'],'enabled','type')['invoice_paid']);
        self::assertFalse(array_column($this->request()['json']['data']['items'],'enabled','type')['invoice_paid']);

        $second = $this->settlementNotification();
        self::assertSame([$first],array_column($push->inbox(T3EventTestEnv::$subject)['items'],'notification_id'));
        self::assertContains($second,array_column($push->inbox(T3EventTestEnv::$registrySubject)['items'],'notification_id'));
        self::assertSame(0,$service->run()['fanned_out']);
        self::assertSame(403,$this->request('PUT',['client_id'=>MobileAuth::uuid(),
            'items'=>[['type'=>'invoice_overdue','enabled'=>false]]])['status']);
    }

    /** @return int Notification id from the same transactional hook used by T2 settlement. */
    private function settlementNotification(): int
    {
        RestApiEnv::rows(T3EventTestEnv::$tenant,'BEGIN');
        try {
            $id = T3EventTestEnv::emit();
            $event = RestApiEnv::rows(T3EventTestEnv::$tenant,'SELECT dedupe_key FROM mobile_events WHERE id=$1',[$id])[0];
            T3EventTestEnv::fanout()->settlementInTransaction($event['dedupe_key'],[T3EventTestEnv::$subject]);
            $notification = RestApiEnv::rows(T3EventTestEnv::$tenant,'SELECT notification_id FROM mobile_events WHERE id=$1',[$id])[0];
            RestApiEnv::rows(T3EventTestEnv::$tenant,'COMMIT');
            return (int)$notification['notification_id'];
        } catch (Throwable $error) {
            RestApiEnv::rows(T3EventTestEnv::$tenant,'ROLLBACK');
            throw $error;
        }
    }

    public function testMandatoryUnknownInvalidAndUnauthorizedTypesWriteNothing(): void
    {
        foreach (['reminder','payment_review','payment_unsettled','unknown'] as $type) {
            $reply = $this->request('PUT',['client_id'=>MobileAuth::uuid(),'items'=>[
                ['type'=>'invoice_paid','enabled'=>false],['type'=>$type,'enabled'=>false]]]);
            self::assertSame(422,$reply['status'],$reply['body']);
        }
        foreach ([null,'no',1] as $enabled) {
            self::assertSame(422,$this->request('PUT',['client_id'=>MobileAuth::uuid(),'items'=>[['type'=>'invoice_paid','enabled'=>$enabled]]])['status']);
        }
        self::assertSame(422,$this->request('PUT',['items'=>[]])['status']);
        self::assertSame(422,$this->request('PUT',['client_id'=>MobileAuth::uuid(),'items'=>[['type'=>'low_stock','enabled'=>false],['type'=>'low_stock','enabled'=>true]]])['status']);
        RestApiEnv::rows(T3EventTestEnv::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',[str_repeat('0',16),T3EventTestEnv::$account['id']]);
        self::assertSame(403,$this->request('PUT',['client_id'=>MobileAuth::uuid(),'items'=>[['type'=>'invoice_paid','enabled'=>false]]])['status']);
        self::assertSame([],RestApiEnv::rows(T3EventTestEnv::$tenant,'SELECT * FROM notification_preferences'));
        self::assertSame([],RestApiEnv::rows(T3EventTestEnv::$tenant,'SELECT * FROM mobile_idempotency'));
    }

    public function testMissingTokenAndUnsupportedMethodHaveCodedErrors(): void
    {
        $reply = RestApiEnv::http('GET','/restapi/endpoints/v1/notifications/preferences.php');
        self::assertSame(401,$reply['status'],$reply['body']);
        self::assertSame('invalid_token',$reply['json']['data']['error']);
        $reply = $this->request('POST',[]);
        self::assertSame(405,$reply['status'],$reply['body']);
        self::assertSame('validation_failed',$reply['json']['data']['error']);
    }
}
