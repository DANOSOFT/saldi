<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileConfigFiscalYearTest.php --- ver 5.0.0 --- 2026.10.07
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
// 20261007 CDX/LH Created. Cover configuration and replay without a current fiscal year.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/T2Seed.php';

final class MobileConfigFiscalYearTest extends TestCase
{
    private static T2Seed $seed;
    private array $years;
    private static array $requestUri;

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        self::$requestUri = [array_key_exists('REQUEST_URI', $_SERVER), ifset($_SERVER, 'REQUEST_URI')];
        $_SERVER['REQUEST_URI'] = '/tests/config-fiscal-year';
        self::$seed = new T2Seed(true);
        self::$seed->sql("DELETE FROM grupper WHERE art='API'");
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$seed)) {
            self::$seed->close();
            if (self::$requestUri[0]) {
                $_SERVER['REQUEST_URI'] = self::$requestUri[1];
            } else {
                unset($_SERVER['REQUEST_URI']);
            }
        }
    }

    protected function setUp(): void
    {
        $this->years = self::$seed->sql("SELECT id,box1,box2,box3,box4 FROM grupper WHERE art='RA'");
    }

    protected function tearDown(): void
    {
        foreach ($this->years as $row) {
            self::$seed->sql('UPDATE grupper SET box1=$1,box2=$2,box3=$3,box4=$4 WHERE id=$5',
                [$row['box1'],$row['box2'],$row['box3'],$row['box4'],$row['id']]);
        }
    }

    private function withoutToday(): void
    {
        self::$seed->sql("UPDATE grupper SET box1='1',box2='2000',box3='12',box4='2000' WHERE art='RA'");
    }

    private function context(?string $date = null): EngineContext
    {
        $login = RestApiEnv::loginData();
        $identity = [self::$seed->tenant,RestApiEnv::testDb(),$login['tenant']['id'],RestApiEnv::user(),$login['user']['id']];
        return $date === null ? EngineContext::forConfig(...$identity) : new EngineContext(...[...$identity,$date]);
    }

    public function testConfigSelectsCoveringYearThenNewestAndStillRejectsMissingYears(): void
    {
        self::$seed->sql('BEGIN');
        try {
            self::$seed->sql("DELETE FROM grupper WHERE art='RA'");
            $today = new DateTimeImmutable('now',new DateTimeZone('Europe/Copenhagen'));
            self::$seed->sql("INSERT INTO grupper(art,kodenr,box1,box2,box3,box4) VALUES
                ('RA',42,'7',$1,'6',$2),('RA',43,'1','2090','12','2090')",
                [(string)((int)$today->format('Y') - ((int)$today->format('n') < 7 ? 1 : 0)),
                 (string)((int)$today->format('Y') + ((int)$today->format('n') < 7 ? 0 : 1))]);
            self::assertSame(42,$this->context()->regnaar,'Prefer the non-calendar year covering Copenhagen today');
            $this->withoutToday();
            self::assertSame(43,$this->context()->regnaar);
            self::assertSame('DKK',$this->context()->baseCurrency);
            foreach ([T2Seed::today()=>'outside_fiscal_year','2026-02-30'=>'invalid_date','malformed'=>'invalid_date',"2026-10-07\0"=>'invalid_date'] as $date=>$reason) {
                try {
                    $this->context($date);
                    self::fail('Strict date accepted: '.$date);
                } catch (MobileT2Error $error) {
                    self::assertSame(422,$error->status);
                    self::assertSame(['reason'=>$reason],$error->details);
                }
            }
            self::$seed->sql("DELETE FROM grupper WHERE art='RA'");
            try {
                $this->context();
                self::fail('Config without RA accepted');
            } catch (MobileT2Error $error) {
                self::assertSame(['reason'=>'outside_fiscal_year'],$error->details);
            }
        } finally {
            self::$seed->sql('ROLLBACK');
        }
    }

    public function testReadsWritesAndStoredReplaysDoNotRequireTodayInAFiscalYear(): void
    {
        $sale = self::$seed->sale(false);
        $saleBody = self::$seed->saleBody($sale);
        $customerBody = ['client_id'=>T2Seed::uuid(),'name'=>'Fiscal config customer'];
        $linkBody = PaymentLinkTestEnv::body();
        $saleBefore = self::$seed->request('POST','debitor/sales/index.php',$saleBody);
        $customerBefore = self::$seed->request('POST','debitor/customers/create.php',$customerBody);
        $linkBefore = self::$seed->request('POST','payments/links.php',$linkBody);
        foreach ([$saleBefore,$customerBefore,$linkBefore] as $response) {
            self::assertSame(201,$response['status'],$response['body']);
        }
        $this->withoutToday();
        foreach ([['debitor/sales/index.php',$saleBody,$saleBefore],
            ['debitor/customers/create.php',$customerBody,$customerBefore],['payments/links.php',$linkBody,$linkBefore]] as [$path,$body,$before]) {
            $replay = self::$seed->request('POST',$path,$body);
            self::assertSame($before['status'],$replay['status'],$replay['body']);
            self::assertSame($before['json'],$replay['json']);
            self::assertSame('true',$replay['headers']['idempotent-replayed']);
        }
        $customer = self::$seed->request('GET','debitor/customers/detail.php?id='.$customerBefore['json']['data']['id'])['json']['data'];
        $update = self::$seed->request('PATCH','debitor/customers/update.php?id='.$customer['id'],
            ['client_id'=>T2Seed::uuid(),'email'=>'config@example.invalid'], 'full',['If-Match: '.$customer['etag']]);
        self::assertSame(200,$update['status'],$update['body']);
        $newSale = self::$seed->request('POST','debitor/sales/index.php',self::$seed->saleBody($sale));
        self::assertSame(201,$newSale['status'],$newSale['body']);
        $quote = self::$seed->request('POST','debitor/sales/quote.php',self::$seed->saleBody($sale));
        self::assertSame(200,$quote['status'],$quote['body']);
        $newLink = self::$seed->request('POST','payments/links.php',PaymentLinkTestEnv::body());
        self::assertSame(201,$newLink['status'],$newLink['body']);
        $link = $linkBefore['json']['data'];
        foreach (['payments/accounts.php','payments/options.php?openpost_id='.$linkBody['openpost_id'],
            'payments/links.php?id='.$link['id'],'debitor/sales/index.php',
            'debitor/dunning/index.php?konto_id='.$sale['konto_id']] as $path) {
            $response = self::$seed->request('GET',$path);
            self::assertSame(200,$response['status'],$path.': '.$response['body']);
        }
        $cancel = self::$seed->request('DELETE','payments/links.php?id='.$link['id'],['client_id'=>T2Seed::uuid()]);
        self::assertSame(200,$cancel['status'],$cancel['body']);
        $public = RestApiEnv::http('GET','/debitor/payments/link/betal.php?' .parse_url($newLink['json']['data']['url'],PHP_URL_QUERY));
        self::assertSame(200,$public['status'],$public['body']);
        self::assertStringNotContainsString('Betalingen kunne ikke startes',$public['body']);
        foreach ([['GET','debitor/dunning/preview.php?konto_id=2147483647',null],
            ['POST','debitor/dunning/index.php',['client_id'=>T2Seed::uuid(),'konto_id'=>2147483647,'expected_level'=>1,'deliver'=>'none']],
            ['POST','debitor/dunning/send.php',['client_id'=>T2Seed::uuid(),'rykker_id'=>2147483647]]] as [$method,$path,$body]) {
            $response = self::$seed->request($method,$path,$body);
            self::assertSame(404,$response['status'],$path.': '.$response['body']);
            self::assertSame('not_found',$response['json']['data']['error']);
        }
        $posting = self::$seed->request('POST','payments/register.php',['client_id'=>T2Seed::uuid(),
            'openpost_id'=>$linkBody['openpost_id'],'amount'=>'1.00','date'=>T2Seed::today(),'method'=>'bank','currency'=>'DKK']);
        self::assertSame(422,$posting['status'],$posting['body']);
        self::assertSame('outside_fiscal_year',$posting['json']['data']['details']['reason']);
        foreach (['2026-02-30',"2026-10-07\0",42] as $date) {
            $invalid = self::$seed->request('POST','payments/register.php',['client_id'=>T2Seed::uuid(),'date'=>$date]);
            self::assertSame(422,$invalid['status'],$invalid['body']);
            self::assertSame('invalid_date',$invalid['json']['data']['details']['reason']);
        }
    }
}
