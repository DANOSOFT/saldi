<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/PaymentSettlementConcurrencyTest.php --- ver 5.0.0 --- 2026-10-05 ---
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
// 20261005 CDX/LH Assert money received from both captured references.
// 20261005 CDX/LH Name period-fixture columns to support the full legacy schema at REST login.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/PaymentSettlementTestEnv.php';

final class PaymentSettlementConcurrencyTest extends TestCase
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

    public function testAuthorizationCapturesImmediatelyAndReplayUsesOneCapture(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $a = $pair['attempt'];
        PaymentSettlementTestEnv::remote($a, 'AUTHORIZED');
        self::assertSame(200, PaymentSettlementTestEnv::webhook(PaymentSettlementTestEnv::event($a, 'authorized'))['status']);
        self::assertSame('booked', PaymentSettlementTestEnv::row((int)$a['id'])['booking']);
        PaymentSettlementTestEnv::settlement()->synchronize($a['provider_reference']);
        $calls = array_filter(PaymentLinkTestEnv::control()['calls'], static fn(array $call): bool => $call['path'] === '/epayment/v1/payments/' . $a['provider_reference'] . '/capture');
        self::assertCount(1, $calls);
        self::assertSame($a['provider_reference'] . '-capture', array_values($calls)[0]['key']);
    }

    public function testCancelledLinkOrManualPaymentRejectsLateAuthorization(): void
    {
        foreach (['cancel','manual'] as $action) {
            $pair = PaymentSettlementTestEnv::attempt();
            $a = $pair['attempt'];
            // Provider is unavailable while local cancellation/supersession commits.
            PaymentLinkTestEnv::control(['mode'=>'unavailable']);
            try {
                if ($action === 'cancel') {
                    PaymentLinkTestEnv::service()->cancel($pair['link']['id'], ['client_id'=>PaymentLinkTestEnv::uuid()]);
                } else {
                    (new PaymentRegistrationService(PaymentSettlementTestEnv::context(), PaymentLinkTestEnv::subject(), PaymentLinkTestEnv::$registry))->register([
                        'client_id'=>PaymentLinkTestEnv::uuid(),'openpost_id'=>$pair['link']['openpost_id'],
                        'amount'=>'1000.00','method'=>'mobilepay','date'=>'2026-10-04']);
                }
            } finally {
                PaymentLinkTestEnv::control(['mode'=>'']);
            }
            PaymentSettlementTestEnv::remote($a, 'AUTHORIZED');
            PaymentSettlementTestEnv::settlement()->synchronize($a['provider_reference']);
            self::assertSame('cancelled', PaymentSettlementTestEnv::row((int)$a['id'])['state']);
            $calls = array_filter(PaymentLinkTestEnv::control()['calls'], static fn(array $call): bool => $call['path'] === '/epayment/v1/payments/' . $a['provider_reference'] . '/capture');
            self::assertCount(0, $calls);
            $registrations = RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT id FROM payment_registrations WHERE openpost_id=$1', [$pair['link']['openpost_id']]);
            self::assertCount($action === 'manual' ? 1 : 0, $registrations);
        }
    }

    public function testManualAndCancelRefusedWhileCaptureIsPending(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "UPDATE payment_attempts SET state='capturing' WHERE id=$1", [$pair['attempt']['id']]);
        $manual = PaymentLinkTestEnv::request('POST', '/restapi/endpoints/v1/payments/register.php', [
            'client_id'=>PaymentLinkTestEnv::uuid(),'openpost_id'=>$pair['link']['openpost_id'],'amount'=>'1000.00','method'=>'mobilepay','date'=>'2026-10-04']);
        $cancel = PaymentLinkTestEnv::request('DELETE', '/restapi/endpoints/v1/payments/links.php?id=' . $pair['link']['id'], ['client_id'=>PaymentLinkTestEnv::uuid()]);
        foreach ([$manual,$cancel] as $result) {
            self::assertSame(409, $result['status'], $result['body']);
            self::assertSame('payment_in_progress', $result['json']['data']['details']['reason']);
        }
    }

    public function testTwoCapturedReferencesGiveOneBookingAndOneReview(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $a = $pair['attempt'];
        PaymentSettlementTestEnv::remote($a, 'AUTHORIZED', 100000);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "UPDATE payment_attempts SET state='captured' WHERE id=$1", [$a['id']]);
        $ref = preg_replace('/-1$/', '-2', $a['provider_reference']);
        $second = RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "INSERT INTO payment_attempts(link_id,provider_reference,state,amount_minor,currency)
            VALUES($1,$2,'captured',100000,'DKK') RETURNING *", [$pair['link']['id'],$ref])[0];
        PaymentSettlementTestEnv::remote($second, 'AUTHORIZED', 100000);
        $multi = curl_multi_init();
        $handles = [];
        foreach ([$a,$second] as $attempt) {
            $handle = PaymentSettlementTestEnv::handle(PaymentSettlementTestEnv::signed(PaymentSettlementTestEnv::event($attempt)));
            $handles[] = $handle;
            curl_multi_add_handle($multi, $handle);
        }
        do {
            curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 0.1);
            }
        } while ($running);
        foreach ($handles as $handle) {
            self::assertSame(200, curl_getinfo($handle, CURLINFO_RESPONSE_CODE), curl_multi_getcontent($handle));
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);
        $attempts = RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT booking,registration_id FROM payment_attempts WHERE link_id=$1 ORDER BY booking', [$pair['link']['id']]);
        self::assertSame(['booked','review'], array_column($attempts, 'booking'));
        self::assertNull($attempts[1]['registration_id']);
        self::assertCount(1, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT id FROM payment_registrations WHERE openpost_id=$1', [$pair['link']['openpost_id']]));
        self::assertCount(1, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "SELECT id FROM notifications WHERE type='payment_review' AND ref_id=$1", [$pair['link']['openpost_id']]));
        $status = PaymentLinkTestEnv::service()->status($pair['link']['id'], false);
        self::assertSame('review', $status['state']);
        self::assertSame('2000.00', $status['paid_amount']);
        self::assertSame('DKK', $status['paid_currency']);
        self::assertSame([['amount'=>'2000.00','currency'=>'DKK']], $status['received_amounts']);
    }

    public function testWebhookStatusAndRealCronInParallelBookOnce(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $a = $pair['attempt'];
        PaymentSettlementTestEnv::remote($a, 'AUTHORIZED', 100000);
        // Hold the invoice lock until both HTTP requests and the actual CLI job are started.
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'BEGIN');
        PaymentLinkTestEnv::service()->lockGroup($pair['link']['openpost_id']);
        $files = '/tmp/t2b8b-race-' . bin2hex(random_bytes(8));
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/cron/payment_links.php',
            '--tenant-id=' . RestApiEnv::regnskabId(RestApiEnv::accountOpen())],
            [0=>['pipe','r'],1=>['file',$files . '.out','w'],2=>['file',$files . '.err','w']], $pipes);
        fclose($pipes[0]);
        $multi = curl_multi_init();
        $webhook = PaymentSettlementTestEnv::handle(PaymentSettlementTestEnv::signed(PaymentSettlementTestEnv::event($a)));
        $poll = curl_init(RestApiEnv::baseUrl() . '/restapi/endpoints/v1/payments/links.php?id=' . $pair['link']['id']);
        curl_setopt_array($poll, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>RestApiEnv::authHeaders(),CURLOPT_TIMEOUT=>40]);
        $handles = [$webhook,$poll];
        foreach ($handles as $handle) {
            curl_multi_add_handle($multi, $handle);
        }
        curl_multi_exec($multi, $running);
        usleep(150000);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'COMMIT');
        try {
            do {
                curl_multi_exec($multi, $running);
                if ($running) {
                    curl_multi_select($multi, 0.1);
                }
            } while ($running);
            foreach ($handles as $handle) {
                self::assertSame(200, curl_getinfo($handle, CURLINFO_RESPONSE_CODE), curl_multi_getcontent($handle));
            }
            self::assertSame(0, proc_close($process), file_get_contents($files . '.err') . file_get_contents($files . '.out'));
            $process = null;
            self::assertSame('booked', PaymentSettlementTestEnv::row((int)$a['id'])['booking']);
            $registrations = RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT kladde_id FROM payment_registrations WHERE openpost_id=$1', [$pair['link']['openpost_id']]);
            self::assertCount(1, $registrations);
            self::assertCount(2, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT id FROM transaktioner WHERE kladde_id=$1', [$registrations[0]['kladde_id']]));
            self::assertCount(1, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "SELECT id FROM notifications WHERE type='invoice_paid' AND ref_id=$1", [$pair['link']['openpost_id']]));
        } finally {
            foreach ($handles as $handle) {
                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
            }
            curl_multi_close($multi);
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            unlink($files . '.err');
            unlink($files . '.out');
        }
    }

    public function testAmbiguousCaptureRecoversWithoutCreatingAnotherPayment(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $a = $pair['attempt'];
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "UPDATE payment_attempts SET state='capturing' WHERE id=$1", [$a['id']]);
        PaymentSettlementTestEnv::remote($a, 'AUTHORIZED');
        $result = PaymentSettlementTestEnv::settlement()->synchronize($a['provider_reference']);
        self::assertSame('booked', $result['booking']);
        $calls = array_filter(PaymentLinkTestEnv::control()['calls'], static fn(array $call): bool => $call['path'] === '/epayment/v1/payments/' . $a['provider_reference'] . '/capture');
        self::assertCount(1, $calls);
        self::assertSame($a['provider_reference'] . '-capture', array_values($calls)[0]['key']);
        self::assertCount(1, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT id FROM payment_attempts WHERE link_id=$1', [$pair['link']['id']]));
    }

    public function testClosedPeriodRetainsCapturedUnsettledMoneyWithoutVoucher(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "CREATE TABLE IF NOT EXISTS moms_periode_luk(kalender_aar integer,kalender_maaned integer,status varchar(10))");
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "INSERT INTO moms_periode_luk(kalender_aar,kalender_maaned,status) VALUES(2026,10,'closed')");
        try {
            PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 100000);
            PaymentSettlementTestEnv::settlement()->synchronize($pair['attempt']['provider_reference']);
            self::assertSame('unsettled', PaymentSettlementTestEnv::row((int)$pair['attempt']['id'])['booking']);
            self::assertSame('paid_unsettled', PaymentLinkTestEnv::service()->status($pair['link']['id'], false)['state']);
            self::assertCount(0, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT id FROM payment_registrations WHERE openpost_id=$1', [$pair['link']['openpost_id']]));
        } finally {
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'DELETE FROM moms_periode_luk WHERE kalender_aar=2026 AND kalender_maaned=10');
        }
    }

    public function testCaptureRefusedAfterReservationBlocksManualPaymentAndRecovers(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED');
        $provider = new PaymentCaptureProbe(new VippsEpaymentProvider(PaymentLinkTestEnv::$tenant), static function (string $reference) use ($pair): void {
            self::assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status(PaymentLinkTestEnv::$tenant));
            self::assertSame('capturing', PaymentSettlementTestEnv::row((int)$pair['attempt']['id'])['state']);
            $response = PaymentLinkTestEnv::request('POST', '/restapi/endpoints/v1/payments/register.php', [
                'client_id'=>PaymentLinkTestEnv::uuid(),'openpost_id'=>$pair['link']['openpost_id'],
                'amount'=>'1000.00','method'=>'mobilepay','date'=>'2026-10-04']);
            self::assertSame(409, $response['status'], $response['body']);
            self::assertSame('payment_in_progress', $response['json']['data']['details']['reason']);
            throw new MobileT2Error('upstream_unavailable', 503);
        });
        try {
            (new PaymentSettlementService(PaymentSettlementTestEnv::context(), PaymentLinkTestEnv::subject(), $provider, PaymentLinkTestEnv::$registry))->synchronize($pair['attempt']['provider_reference']);
            self::fail('Capture refusal should be retryable');
        } catch (MobileT2Error $error) {
            self::assertSame('upstream_unavailable', $error->error);
        }
        self::assertSame('capturing', PaymentSettlementTestEnv::row((int)$pair['attempt']['id'])['state']);
        self::assertCount(0, RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT id FROM payment_registrations WHERE openpost_id=$1', [$pair['link']['openpost_id']]));
        PaymentSettlementTestEnv::settlement()->synchronize($pair['attempt']['provider_reference']);
        self::assertSame('booked', PaymentSettlementTestEnv::row((int)$pair['attempt']['id'])['booking']);
    }

    public function testGoldenG7CapturedWebhookMatchesManualMobilepayLedgerAndBalances(): void
    {
        $pair = PaymentSettlementTestEnv::attempt();
        $year = PaymentLinkTestEnv::$fixture['year'];
        $next = $year + 1;
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "INSERT INTO kontoplan(kontonr,beskrivelse,kontotype,regnskabsaar,fra_kto,saldo,primo)
            VALUES(980000,'G7 Z','Z',$1,$2,0,0),(980001,'G7 R','R',$1,980000,0,0)", [$year,PaymentLinkTestEnv::$fixture['bank']]);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "INSERT INTO grupper(art,kodenr,box1,box2,box3,box4,box10) VALUES('RA',$1,'1','2027','12','2027','')", [$next]);
        $columns = RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name='kontoplan' AND column_name<>'id' ORDER BY ordinal_position");
        $names = array_map(static fn(array $r): string => pg_escape_identifier(PaymentLinkTestEnv::$tenant, $r['column_name']), $columns);
        $exprs = array_map(static fn(array $r): string => $r['column_name'] === 'regnskabsaar' ? (string)$next : pg_escape_identifier(PaymentLinkTestEnv::$tenant, $r['column_name']), $columns);
        RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'INSERT INTO kontoplan(' . implode(',', $names) . ') SELECT ' . implode(',', $exprs) . ' FROM kontoplan WHERE regnskabsaar=$1', [$year]);
        try {
            $hadUri = array_key_exists('REQUEST_URI', $_SERVER);
            $previousUri = ifset($_SERVER, 'REQUEST_URI');
            $_SERVER['REQUEST_URI'] = '/saldi/finans/bogfor.php';
            try {
                LegacyEngine::run(PaymentSettlementTestEnv::context(), static function () use ($year): void {
                    LegacyEngine::load('kladde');
                    require_once __DIR__ . '/../../includes/genberegn.php';
                    genberegn($year, false);
                    updateFollowingOpeningBalances($year);
                });
            } finally {
                if ($hadUri) {
                    $_SERVER['REQUEST_URI'] = $previousUri;
                } else {
                    unset($_SERVER['REQUEST_URI']);
                }
            }
            $snapshot = static fn(): array => RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT kontonr,regnskabsaar,saldo,primo FROM kontoplan
                WHERE regnskabsaar IN ($1,$2) AND kontonr IN ($3,$4,980000,980001) ORDER BY regnskabsaar,kontonr',
                [$year,$next,PaymentLinkTestEnv::$fixture['bank'],PaymentLinkTestEnv::$fixture['collective']]);
            $before = $snapshot();
            PaymentSettlementTestEnv::remote($pair['attempt'], 'AUTHORIZED', 100000);
            self::assertSame(200, PaymentSettlementTestEnv::webhook(PaymentSettlementTestEnv::event($pair['attempt']))['status']);
            $providerBalances = $snapshot();
            $providerRow = PaymentSettlementTestEnv::row((int)$pair['attempt']['id']);
            $providerReg = RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT * FROM payment_registrations WHERE id=$1', [$providerRow['registration_id']])[0];
            $body = PaymentLinkTestEnv::body();
            $manual = (new PaymentRegistrationService(PaymentSettlementTestEnv::context(), PaymentLinkTestEnv::subject(), PaymentLinkTestEnv::$registry))->register([
                'client_id'=>PaymentLinkTestEnv::uuid(),'openpost_id'=>$body['openpost_id'],'amount'=>'1000.00','method'=>'mobilepay','date'=>$providerReg['transdate']]);
            $manualBalances = $snapshot();
            $golden = json_decode(file_get_contents(__DIR__ . '/fixtures/t2/golden/G7-payment-link.json'), true, 512, JSON_THROW_ON_ERROR);
            $ledger = static fn(int $journal): array => RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT kontonr,debet,kredit FROM transaktioner WHERE kladde_id=$1 ORDER BY kontonr', [$journal]);
            self::assertSame($ledger((int)$providerReg['kladde_id']), $ledger($manual['body']['data']['kladde_id']));
            self::assertSame($golden['ledger_amounts'], array_map(static fn(array $r): array => [number_format((float)$r['debet'],2,'.',''),number_format((float)$r['kredit'],2,'.','')], $ledger((int)$providerReg['kladde_id'])));
            foreach ($before as $i=>$row) {
                foreach (['saldo','primo'] as $field) {
                    $providerDelta = (float)$providerBalances[$i][$field] - (float)$row[$field];
                    $manualDelta = (float)$manualBalances[$i][$field] - (float)$providerBalances[$i][$field];
                    self::assertEqualsWithDelta($providerDelta, $manualDelta, 0.001);
                    if ((int)$row['regnskabsaar'] === $year && in_array((int)$row['kontonr'], [980000,980001], true) && $field === 'saldo') {
                        self::assertEquals((float)$golden['summary_saldo_delta'][(int)$row['kontonr'] === 980000 ? 'Z' : 'R'], $providerDelta);
                    }
                    if ((int)$row['regnskabsaar'] === $next && $field === 'primo') {
                        if ((int)$row['kontonr'] === PaymentLinkTestEnv::$fixture['bank']) {
                            self::assertEquals((float)$golden['following_year_primo_delta']['bank'], $providerDelta);
                        }
                        if ((int)$row['kontonr'] === PaymentLinkTestEnv::$fixture['collective']) {
                            self::assertEquals((float)$golden['following_year_primo_delta']['debtor'], $providerDelta);
                        }
                    }
                }
            }
            $post = RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT * FROM openpost WHERE id=$1', [$pair['link']['openpost_id']])[0];
            $group = RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'SELECT amount,udlignet,udlign_id,udlign_date FROM openpost WHERE konto_id=$1 AND faktnr=$2 ORDER BY id', [$post['konto_id'],$post['faktnr']]);
            self::assertSame($golden['openpost_amounts'], array_map(static fn(array $r): string => number_format((float)$r['amount'],2,'.',''), $group));
            self::assertSame(['1','1'], array_column($group, 'udlignet'));
            self::assertCount(1, array_unique(array_column($group, 'udlign_id')));
            self::assertSame([$providerReg['transdate'],$providerReg['transdate']], array_column($group, 'udlign_date'));
        } finally {
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant, 'DELETE FROM kontoplan WHERE regnskabsaar=$1 OR kontonr IN (980000,980001)', [$next]);
            RestApiEnv::rows(PaymentLinkTestEnv::$tenant, "DELETE FROM grupper WHERE art='RA' AND kodenr=$1", [$next]);
        }
    }
}
