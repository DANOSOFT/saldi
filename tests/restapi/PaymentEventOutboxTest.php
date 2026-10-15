<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/PaymentEventOutboxTest.php --- ver 5.0.0 --- 2026.10.05
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
require_once __DIR__ . '/support/PaymentSettlementTestEnv.php';

/** T2 settlement remains atomic with its deduplicated T3 event and creator inbox. */
final class PaymentEventOutboxTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
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
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'DELETE FROM notification_preferences');
    }

    public function testSettlementAndScannerShareOneDedupeKeyAndImmediateInbox(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $attempt = $pair['attempt'];
        PaymentSettlementTestEnv::remote($attempt, 'AUTHORIZED', (int)$attempt['amount_minor']);
        PaymentSettlementTestEnv::settlement()->synchronize($attempt['provider_reference']);
        $key = 'invoice_paid:' . $pair['link']['openpost_id'];
        $rows = RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT * FROM mobile_events WHERE dedupe_key=$1',[$key]);
        self::assertCount(1,$rows);
        self::assertNotNull($rows[0]['fanned_out_at']);
        self::assertNotNull($rows[0]['notification_id']);
        self::assertSame('openpost',$rows[0]['ref_type']);
        self::assertFalse(MobileEventOutbox::emit(PaymentLinkTestEnv::$tenant,'invoice_paid',$key,'openpost',
            (int)$pair['link']['openpost_id'],['faktnr'=>'scanner copy'],null));
        $worker = new MobileEventFanout(PaymentLinkTestEnv::$tenant,PaymentLinkTestEnv::$registry,
            RestApiEnv::regnskabId(RestApiEnv::accountOpen()));
        self::assertSame(0,$worker->run()['fanned_out']);
        PaymentSettlementTestEnv::settlement()->synchronize($attempt['provider_reference']);
        self::assertCount(1,RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            "SELECT id FROM notifications WHERE type='invoice_paid' AND ref_id=$1",[$pair['link']['openpost_id']]));
        self::assertCount(1,RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            'SELECT id FROM payment_registrations WHERE client_id=$1',['vipps:' . $attempt['provider_reference']]));
    }

    public function testCreatorPreferenceDoesNotSuppressMandatoryPaymentReview(): void
    {
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,"INSERT INTO notification_preferences(subject,type,enabled)
            VALUES($1,'invoice_paid',false)",[PaymentLinkTestEnv::subject()]);
        $pair = PaymentSettlementTestEnv::attempt();
        $attempt = $pair['attempt'];
        PaymentSettlementTestEnv::remote($attempt,'AUTHORIZED',(int)$attempt['amount_minor']);
        PaymentSettlementTestEnv::settlement()->synchronize($attempt['provider_reference']);
        $rows = RestApiEnv::rows(PaymentLinkTestEnv::$tenant,'SELECT * FROM mobile_events WHERE dedupe_key=$1',
            ['invoice_paid:' . $pair['link']['openpost_id']]);
        self::assertCount(1,$rows);
        self::assertNotNull($rows[0]['fanned_out_at']);
        self::assertNull($rows[0]['notification_id']);
        self::assertSame('booked',PaymentSettlementTestEnv::row((int)$attempt['id'])['booking']);
        $review = PaymentSettlementTestEnv::attempt();
        PaymentSettlementTestEnv::remote($review['attempt'],'CAPTURED',
            (int)$review['attempt']['amount_minor']+100,(int)$review['attempt']['amount_minor']+100);
        PaymentSettlementTestEnv::settlement()->synchronize($review['attempt']['provider_reference']);
        self::assertSame('review',PaymentSettlementTestEnv::row((int)$review['attempt']['id'])['booking']);
        self::assertCount(1,RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            "SELECT id FROM notifications WHERE type='payment_review' AND ref_id=$1",[$review['link']['openpost_id']]));
    }
}
