<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/PaymentCaptureFactsTest.php --- ver 5.0.0 --- 2026-10-05 ---
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
// 20261005 CDX/LH Created. Capture-time and received-money recovery regressions.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/PaymentSettlementTestEnv.php';

final class PaymentCaptureFactsTest extends TestCase
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

    /** @return array<string,mixed> */
    private static function capture(array $attempt, string $timestamp, int $minor = 100000, string $currency = 'DKK'): array
    {
        return ['reference'=>$attempt['provider_reference'],'pspReference'=>'capture-' . bin2hex(random_bytes(8)),
            'name'=>'CAPTURED','success'=>true,'amount'=>['value'=>$minor,'currency'=>$currency],
            'timestamp'=>$timestamp,'idempotencyKey'=>$attempt['provider_reference'] . '-capture'];
    }

    private static function assertPostedDate(array $pair, string $timestamp, string $date): void
    {
        $attempt = PaymentSettlementTestEnv::row((int)$pair['attempt']['id']);
        self::assertSame('booked', $attempt['booking']);
        self::assertEquals(new DateTimeImmutable($timestamp), new DateTimeImmutable($attempt['captured_at']));
        $registration = RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            'SELECT transdate,kladde_id FROM payment_registrations WHERE id=$1', [$attempt['registration_id']])[0];
        self::assertSame($date, $registration['transdate']);
        $ledger = RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            'SELECT transdate FROM transaktioner WHERE kladde_id=$1', [$registration['kladde_id']]);
        self::assertSame([$date,$date], array_column($ledger, 'transdate'));
        $link = PaymentLinkTestEnv::service()->link($pair['link']['id']);
        $group = RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            'SELECT udlign_date FROM openpost WHERE konto_id=$1 AND faktnr=$2 ORDER BY id', [$link['konto_id'],$link['faktnr']]);
        self::assertSame([$date,$date], array_column($group, 'udlign_date'));
        $status = PaymentLinkTestEnv::service()->status($pair['link']['id'], false);
        self::assertSame('1000.00', $status['paid_amount']);
        self::assertSame('DKK', $status['paid_currency']);
        self::assertSame((new DateTimeImmutable($timestamp))->getTimestamp(), (new DateTimeImmutable($status['paid_at']))->getTimestamp());
    }

    public function testDelayedWebhookRecoveryUsesCaptureDayAcrossMidnight(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        // 23:59:59 in Copenhagen, recovered days later; the HTTP signature still uses today's date.
        $timestamp = '2026-10-03T21:59:59.1234567Z';
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 100000);
        PaymentLinkTestEnv::control(['reference'=>$pair['attempt']['provider_reference'],
            'events'=>[self::capture($pair['attempt'], $timestamp)]]);
        PaymentLinkTestEnv::control(['mode'=>'unavailable']);
        try {
            self::assertSame(200, PaymentSettlementTestEnv::webhook(PaymentSettlementTestEnv::event($pair['attempt']))['status']);
        } finally {
            PaymentLinkTestEnv::control(['mode'=>'']);
        }
        PaymentSettlementTestEnv::settlement()->run();
        self::assertPostedDate($pair, $timestamp, '2026-10-03');
    }

    public function testDelayedCronRecoveryPostsInCaptureFiscalYear(): void
    {
        $current = PaymentLinkTestEnv::$fixture['year'];
        $prior = $current + 1;
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            "INSERT INTO grupper(art,kodenr,box1,box2,box3,box4,box5,box10) VALUES('RA',$1,'1','2025','12','2025','on','')", [$prior]);
        // The fiscal-year id is internal. Clone the needed accounts and debtor groups into 2025.
        $columns = RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            "SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name='kontoplan' AND column_name<>'id' ORDER BY ordinal_position");
        $names = array_map(static fn(array $r): string => pg_escape_identifier(PaymentLinkTestEnv::$tenant, $r['column_name']), $columns);
        $expressions = array_map(static fn(array $r): string => $r['column_name'] === 'regnskabsaar'
            ? (string)$prior : pg_escape_identifier(PaymentLinkTestEnv::$tenant, $r['column_name']), $columns);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'INSERT INTO kontoplan(' . implode(',', $names) . ') SELECT '
            . implode(',', $expressions) . ' FROM kontoplan WHERE regnskabsaar=$1', [$current]);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'UPDATE kontoplan SET saldo=0,primo=0 WHERE regnskabsaar=$1', [$prior]);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            "INSERT INTO grupper(art,kodenr,box2,fiscal_year) SELECT art,kodenr,box2,$1 FROM grupper WHERE art='DG' AND fiscal_year=$2", [$prior,$current]);
        $balance = static fn(int $year): float => (float)RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            'SELECT saldo FROM kontoplan WHERE regnskabsaar=$1 AND kontonr=$2', [$year,PaymentLinkTestEnv::$fixture['bank']])[0]['saldo'];
        $beforeCurrent = $balance($current);
        $beforePrior = $balance($prior);
        try {
            $pair = PaymentSettlementTestEnv::attempt();
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
                "UPDATE openpost SET transdate='2025-12-01',forfaldsdate='2025-12-01' WHERE id=$1", [$pair['link']['openpost_id']]);
            $timestamp = '2025-12-31T22:59:59Z';
            PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 100000);
            PaymentLinkTestEnv::control(['reference'=>$pair['attempt']['provider_reference'],
                'events'=>[self::capture($pair['attempt'], $timestamp)]]);
            PaymentSettlementTestEnv::settlement()->run();
            self::assertPostedDate($pair, $timestamp, '2025-12-31');
            self::assertEqualsWithDelta(1000.00, $balance($prior) - $beforePrior, 0.001);
            self::assertSame($beforeCurrent, $balance($current));
        } finally {
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'DELETE FROM kontoplan WHERE regnskabsaar=$1', [$prior]);
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "DELETE FROM grupper WHERE (art='RA' AND kodenr=$1) OR fiscal_year=$1", [$prior]);
        }
    }

    public function testCaptureTimeUsesCopenhagenDateAtUtcYearBoundary(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $timestamp = '2025-12-31T23:30:00Z';
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            "UPDATE openpost SET transdate='2025-12-01',forfaldsdate='2025-12-01' WHERE id=$1", [$pair['link']['openpost_id']]);
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 100000);
        PaymentLinkTestEnv::control(['reference'=>$pair['attempt']['provider_reference'],
            'events'=>[self::capture($pair['attempt'], $timestamp)]]);
        PaymentSettlementTestEnv::settlement()->synchronize($pair['attempt']['provider_reference']);
        self::assertPostedDate($pair, $timestamp, '2026-01-01');
    }

    public function testUnorderedPartialCaptureLogIgnoresFailedOperations(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $latest = '2026-10-03T21:59:59Z';
        $failed = self::capture($pair['attempt'], '2026-10-04T23:00:00Z');
        $failed['success'] = false;
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 100000);
        PaymentLinkTestEnv::control(['reference'=>$pair['attempt']['provider_reference'], 'events'=>[
            self::capture($pair['attempt'], $latest, 60000), $failed,
            self::capture($pair['attempt'], '2026-10-02T12:00:00Z', 40000),
        ]]);
        PaymentSettlementTestEnv::settlement()->synchronize($pair['attempt']['provider_reference']);
        self::assertPostedDate($pair, $latest, '2026-10-03');
    }

    public function testMissingOrInvalidCaptureFactsNeverInventTodaysPostingDate(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $event = self::capture($pair['attempt'], '2026-10-03T12:00:00Z');
        $invalid = [$event];
        $invalid[0]['timestamp'] = '2026-02-30T12:00:00Z';
        $incomplete = [$event];
        $incomplete[0]['amount']['value'] = 99999;
        $unsuccessful = [$event];
        $unsuccessful[0]['success'] = false;
        $foreign = [$event];
        $foreign[0]['reference'] = 'another-payment';
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 100000);
        foreach ([[], $invalid, $incomplete, $unsuccessful, $foreign] as $events) {
            PaymentLinkTestEnv::control(['reference'=>$pair['attempt']['provider_reference'],'events'=>$events]);
            try {
                PaymentSettlementTestEnv::settlement()->synchronize($pair['attempt']['provider_reference']);
                self::fail('Unconfirmed capture time must remain recoverable');
            } catch (MobileT2Error $error) {
                self::assertSame('upstream_unavailable', $error->error);
            }
            self::assertNull(PaymentSettlementTestEnv::row((int)$pair['attempt']['id'])['captured_at']);
            self::assertCount(0, RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
                'SELECT id FROM payment_registrations WHERE openpost_id=$1', [$pair['link']['openpost_id']]));
        }
        PaymentLinkTestEnv::control(['reference'=>$pair['attempt']['provider_reference'],'events'=>[$event]]);
        PaymentSettlementTestEnv::settlement()->run();
        self::assertPostedDate($pair, $event['timestamp'], '2026-10-03');
    }

    public function testDifferentCapturedCurrenciesRemainSeparateInStatus(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $a = $pair['attempt'];
        PaymentSettlementTestEnv::remote($a, 'AUTHORIZED', 100000);
        PaymentSettlementTestEnv::settlement()->synchronize($a['provider_reference']);
        $second = RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            "INSERT INTO payment_attempts(link_id,provider_reference,state,amount_minor,currency)
            VALUES($1,$2,'captured',100000,'DKK') RETURNING *", [$pair['link']['id'],preg_replace('/-1$/', '-2', $a['provider_reference'])])[0];
        PaymentSettlementTestEnv::remote($second, 'AUTHORIZED', 100000, 100000, 'EUR');
        PaymentSettlementTestEnv::settlement()->synchronize($second['provider_reference']);
        $response = PaymentLinkTestEnv::request('GET', '/restapi/endpoints/v1/payments/links.php?id=' . $pair['link']['id']);
        self::assertSame(200, $response['status'], $response['body']);
        $status = $response['json']['data'];
        self::assertSame('review', $status['state']);
        self::assertSame([['amount'=>'1000.00','currency'=>'DKK'],['amount'=>'1000.00','currency'=>'EUR']], $status['received_amounts']);
        self::assertArrayNotHasKey('paid_amount', $status);
        self::assertArrayNotHasKey('paid_currency', $status);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            'UPDATE payment_attempts SET captured_minor=NULL,captured_currency=NULL WHERE id=$1', [$second['id']]);
        $unknown = PaymentLinkTestEnv::service()->status($pair['link']['id'], false);
        self::assertSame([], $unknown['received_amounts']);
        self::assertArrayNotHasKey('paid_amount', $unknown);
        PaymentSettlementTestEnv::settlement()->run();
        self::assertSame($status['received_amounts'], PaymentLinkTestEnv::service()->status($pair['link']['id'], false)['received_amounts']);
    }

    public function testRecoveryFillsExistingBookedMoneyWithoutReposting(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $timestamp = '2026-10-03T12:00:00Z';
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 100000);
        PaymentLinkTestEnv::control(['reference'=>$pair['attempt']['provider_reference'],
            'events'=>[self::capture($pair['attempt'], $timestamp)]]);
        PaymentSettlementTestEnv::settlement()->synchronize($pair['attempt']['provider_reference']);
        $before = PaymentSettlementTestEnv::row((int)$pair['attempt']['id']);
        // Model a tenant upgraded from the old money-less attempts without inventing provider values.
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            'UPDATE payment_attempts SET captured_minor=NULL,captured_currency=NULL,captured_at=now() WHERE id=$1', [$before['id']]);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'UPDATE payment_links SET paid_at=now() WHERE id=$1', [$pair['link']['id']]);
        $unknown = PaymentLinkTestEnv::service()->status($pair['link']['id'], false);
        self::assertArrayNotHasKey('paid_amount', $unknown);
        self::assertSame([], $unknown['received_amounts']);
        PaymentSettlementTestEnv::settlement()->run();
        self::assertPostedDate($pair, $timestamp, '2026-10-03');
        self::assertSame($before['registration_id'], PaymentSettlementTestEnv::row((int)$before['id'])['registration_id']);
        self::assertCount(1, RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            'SELECT id FROM payment_registrations WHERE openpost_id=$1', [$pair['link']['openpost_id']]));
        self::assertCount(1, RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            "SELECT id FROM notifications WHERE type='invoice_paid' AND ref_id=$1", [$pair['link']['openpost_id']]));
    }

    public function testLaterCaptureEventRefreshesReceivedTotalForTheSameReference(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $first = self::capture($pair['attempt'], '2026-10-02T12:00:00Z', 40000);
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 40000);
        PaymentLinkTestEnv::control(['reference'=>$pair['attempt']['provider_reference'],'events'=>[$first]]);
        self::assertSame(200, PaymentSettlementTestEnv::webhook($first)['status']);
        self::assertSame('400.00', PaymentLinkTestEnv::service()->status($pair['link']['id'], false)['paid_amount']);
        $second = self::capture($pair['attempt'], '2026-10-03T12:00:00Z', 60000);
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 100000);
        PaymentLinkTestEnv::control(['reference'=>$pair['attempt']['provider_reference'],'events'=>[$first,$second]]);
        self::assertSame(200, PaymentSettlementTestEnv::webhook($second)['status']);
        $status = PaymentLinkTestEnv::service()->status($pair['link']['id'], false);
        self::assertSame('review', $status['state']);
        self::assertSame('1000.00', $status['paid_amount']);
        self::assertSame('DKK', $status['paid_currency']);
        self::assertCount(0, RestApiEnv::rows(PaymentLinkTestEnv::$tenant,
            'SELECT id FROM payment_registrations WHERE openpost_id=$1', [$pair['link']['openpost_id']]));
        self::assertTrue(json_decode(PaymentSettlementTestEnv::webhook($second)['body'], true)['duplicate']);
        self::assertSame('1000.00', PaymentLinkTestEnv::service()->status($pair['link']['id'], false)['paid_amount']);
    }
}
