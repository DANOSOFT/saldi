<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/SalesEndpointTest.php --- ver 5.0.0 --- 2026.10.03
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
// 20261001 CDX/LH Cover dated exchange rates, dependent zero-price discounts and accounting override refusals.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/fixtures/t2/SalesFixture.php';
require_once __DIR__ . '/fixtures/t2/ProductVatFixture.php';

final class SalesEndpointTest extends TestCase
{
    private $conn;
    private array $fixture;
    private const ROOT='/restapi/endpoints/v1/debitor/sales/';
    public static function setUpBeforeClass(): void
    {
        if ($reason=RestApiEnv::unavailableReason()) { self::markTestSkipped($reason); }
        RestApiEnv::bootstrapTenant(); RestApiEnv::loginData();
    }
    public static function tearDownAfterClass(): void { RestApiEnv::teardownTenant(); }
    protected function setUp(): void
    {
        $this->conn=RestApiEnv::connect(RestApiEnv::testDb());
        $this->fixture=SalesFixture::create($this->conn,[RestApiEnv::class,'rows']);
    }
    protected function tearDown(): void { pg_close($this->conn); }
    private function rows(string $sql,array $params=[]): array { return RestApiEnv::rows($this->conn,$sql,$params); }
    private static function uuid(): string
    {
        $hex=bin2hex(random_bytes(16)); return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-a'.substr($hex,17,3).'-'.substr($hex,20);
    }
    private function body(string $kind='tilbud'): array
    {
        return ['client_id'=>self::uuid(),'konto_id'=>$this->fixture['konto_id'],'kind'=>$kind,
            'lines'=>[['vare_id'=>$this->fixture['product_id'],'quantity'=>'2','unit_price'=>'100','discount_pct'=>'10']]];
    }
    private function request(string $method,string $file,?array $body=null,array $headers=[]): array
    {
        $response=RestApiEnv::http($method,self::ROOT.$file,$body,array_merge(RestApiEnv::authHeaders(),$headers));
        self::assertIsArray($response['json'],$response['body']);
        self::assertStringNotContainsString('<html',$response['body']);
        return $response;
    }
    private function create(string $kind='tilbud'): array
    {
        $r=$this->request('POST','index.php',$this->body($kind));
        self::assertSame(201,$r['status'],$r['body']);
        return $r['json']['data'];
    }
    public function testQuoteCreateReadMineReplayAndReplace(): void
    {
        $body=$this->body();
        $before=$this->rows('SELECT count(*) AS n FROM ordrer')[0]['n'];
        $q=$this->request('POST','quote.php',$body); self::assertSame(200,$q['status'],$q['body']);
        self::assertSame($before,$this->rows('SELECT count(*) AS n FROM ordrer')[0]['n']);
        $r=$this->request('POST','index.php',$body); self::assertSame(201,$r['status'],$r['body']); $order=$r['json']['data'];
        self::assertSame($q['json']['data']['totals'],$order['totals']);
        self::assertSame(['net'=>'180.00','vat'=>'45.00','gross'=>'225.00','currency'=>'DKK'],$order['totals']);
        self::assertTrue($order['editable']); self::assertTrue($order['created_in_app']);
        $replay=$this->request('POST','index.php',$body); self::assertSame($order,$replay['json']['data']);
        $other=$body; $other['lines'][0]['quantity']='3';
        $reused=$this->request('POST','index.php',$other); self::assertSame(409,$reused['status']);
        self::assertSame('client_id_reused',$reused['json']['data']['details']['reason']);
        $read=$this->request('GET','index.php?id='.$order['id']); self::assertSame($order,$read['json']['data']);
        $mine=$this->request('GET','index.php?mine=1&limit=1'); self::assertSame($order['id'],$mine['json']['data']['items'][0]['id']);
        $lines=['client_id'=>self::uuid(),'lines'=>$other['lines']];
        $replace=$this->request('PUT','lines.php?order_id='.$order['id'],$lines,['If-Match: '.$order['etag']]); self::assertSame(200,$replace['status'],$replace['body']);
        self::assertSame('270.00',$replace['json']['data']['totals']['net']);
        $retry=$this->request('PUT','lines.php?order_id='.$order['id'],$lines,['If-Match: '.$order['etag']]); self::assertSame($replace['json'],$retry['json']);
        $lines['client_id']=self::uuid();
        $stale=$this->request('PUT','lines.php?order_id='.$order['id'],$lines,['If-Match: '.$order['etag']]); self::assertSame(412,$stale['status']);
        $missing=$this->request('PUT','lines.php?order_id='.$order['id'],$lines); self::assertSame(422,$missing['status']);
        self::assertSame('if_match_required',$missing['json']['data']['details']['reason']);
    }
    public function testWebLockAndUnsupportedOrdersStayUntouched(): void
    {
        $order=$this->create();
        $this->rows("UPDATE ordrer SET hvem='Other user' WHERE id=$1",[$order['id']]);
        $read=$this->request('GET','index.php?id='.$order['id'])['json']['data'];
        self::assertSame('Other user',$read['locked_by_web']);
        $body=['client_id'=>self::uuid(),'lines'=>$this->body()['lines']];
        $r=$this->request('PUT','lines.php?order_id='.$order['id'],$body,['If-Match: '.$read['etag']]);
        self::assertSame(409,$r['status']); self::assertSame('order_locked',$r['json']['data']['details']['reason']);
        $this->rows("UPDATE ordrer SET hvem='' WHERE id=$1",[$order['id']]);
        foreach (['variant_id'=>1,'lager'=>2,'rabatart'=>'amount','samlevare'=>'on','folgevare'=>1,'serienr'=>'on','vare_id'=>0,'leveret'=>1,'pris'=>100.001] as $field=>$value) {
            $original=$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1',[$order['id']])[0];
            $this->rows("UPDATE ordrelinjer SET $field=$1 WHERE ordre_id=$2",[$value,$order['id']]);
            $before=$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1',[$order['id']]);
            $read=$this->request('GET','index.php?id='.$order['id'])['json']['data']; self::assertFalse($read['editable'],$field);
            $body['client_id']=self::uuid();
            $r=$this->request('PUT','lines.php?order_id='.$order['id'],$body,['If-Match: '.$read['etag']]);
            self::assertSame(409,$r['status'],$r['body']); self::assertSame('order_not_editable',$r['json']['data']['details']['reason']);
            self::assertSame($before,$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1',[$order['id']]),$field);
            $this->rows("UPDATE ordrelinjer SET $field=$1 WHERE ordre_id=$2",[$original[$field],$order['id']]);
        }
    }
    public function testQuoteAvoidsNumberingLock(): void
    {
        // ROW SHARE is compatible with quote's ordinary insert lock, incompatible with number allocation's EXCLUSIVE lock.
        $this->rows('BEGIN'); $this->rows('LOCK TABLE ordrer IN ROW SHARE MODE');
        try { $q=$this->request('POST','quote.php',$this->body()); self::assertSame(200,$q['status'],$q['body']); }
        finally { $this->rows('ROLLBACK'); }
    }
    public function testApprovedWebOrderReplacementRebuildsReservations(): void
    {
        $f=$this->fixture;
        $this->rows('UPDATE ordrer SET status=1,levdate=CURRENT_DATE WHERE id=$1',[$f['order_id']]);
        $line=SalesFixture::line($this->conn,[RestApiEnv::class,'rows'],$f,['leveres'=>2]);
        $batch=(int)$this->rows('INSERT INTO batch_kob (ordre_id,linje_id,vare_id,antal,rest,lager,kobsdate) VALUES (0,0,$1,20,20,1,CURRENT_DATE) RETURNING id',[$f['product_id']])[0]['id'];
        $this->rows('INSERT INTO reservation (linje_id,vare_id,batch_kob_id,antal,lager) VALUES ($1,$2,$3,2,0)',[$line,$f['product_id'],$batch]);
        $read=$this->request('GET','index.php?id='.$f['order_id'])['json']['data'];
        self::assertFalse($read['created_in_app']); self::assertTrue($read['editable']);
        foreach (['2','3'] as $quantity) {
            $body=['client_id'=>self::uuid(),'lines'=>[['vare_id'=>$f['product_id'],'quantity'=>$quantity,'unit_price'=>'100','discount_pct'=>'10']]];
            $r=$this->request('PUT','lines.php?order_id='.$f['order_id'],$body,['If-Match: '.$read['etag']]);
            self::assertSame(200,$r['status'],$r['body']); $read=$r['json']['data'];
            self::assertSame(1,$read['status']);
            self::assertSame([],$this->rows('SELECT * FROM reservation WHERE linje_id=$1',[$line]));
            $reservations=$this->rows('SELECT r.batch_kob_id,r.antal FROM reservation r JOIN ordrelinjer l ON l.id=r.linje_id WHERE l.ordre_id=$1 ORDER BY r.batch_kob_id',[$f['order_id']]);
            self::assertSame([['batch_kob_id'=>(string)$batch,'antal'=>$quantity.'.000']],$reservations);
        }
        $created=$this->create('ordre'); self::assertSame(1,$created['status']);
    }
    public function testQuoteMatchesPersistedTaxableAndExemptLines(): void
    {
        $f=ProductVatFixture::create($this->conn,[RestApiEnv::class,'rows'],$this->fixture['year'],'group_exempt');
        $body=$this->body();
        $body['lines'][]=['vare_id'=>$f['product_id'],'quantity'=>'1','unit_price'=>'100'];
        $q=$this->request('POST','quote.php',$body); self::assertSame(200,$q['status'],$q['body']);
        $r=$this->request('POST','index.php',$body); self::assertSame(201,$r['status'],$r['body']);
        self::assertSame(['net'=>'280.00','vat'=>'45.00','gross'=>'325.00','currency'=>'DKK'],$q['json']['data']['totals']);
        self::assertSame($q['json']['data']['totals'],$r['json']['data']['totals']);
    }
    public function testVatEngineAgreement(): void
    {
        foreach (['taxable','group_exempt','department','caller_exempt','reverse_customer','reverse_normal'] as $scenario) {
            if ($scenario==='caller_exempt') { continue; } // the public line model has no exemption override
            $f=ProductVatFixture::create($this->conn,[RestApiEnv::class,'rows'],$this->fixture['year'],$scenario);
            $this->rows("UPDATE grupper SET box1='S1',box3='DKK',box4='Dansk' WHERE art='DG' AND kodenr IN (SELECT gruppe FROM adresser WHERE id IN (SELECT konto_id FROM ordrer WHERE id=$1))",[$f['order_id']]);
            $customer=(int)$this->rows('SELECT konto_id FROM ordrer WHERE id=$1',[$f['order_id']])[0]['konto_id'];
            // The app has no department context. Department VAT is characterised by B4's engine suite.
            if ($scenario==='department') { continue; }
            $body=['client_id'=>self::uuid(),'konto_id'=>$customer,'kind'=>'tilbud','lines'=>[['vare_id'=>$f['product_id'],'quantity'=>'1','unit_price'=>'100']]];
            $q=$this->request('POST','quote.php',$body); self::assertSame(200,$q['status'],$q['body']);
            $r=$this->request('POST','index.php',$body); self::assertSame(201,$r['status'],$r['body']);
            self::assertSame($q['json']['data']['totals'],$r['json']['data']['totals']);
            $persisted=$this->rows('SELECT momssats,momsfri,omvbet FROM ordrelinjer WHERE ordre_id=$1',[$r['json']['data']['id']])[0];
            self::assertSame($f['expected_rate'],(float)$persisted['momssats']);
            if ($scenario!=='reverse_customer') { self::assertSame((float)$persisted['momssats'],(float)$q['json']['data']['lines'][0]['vat_rate']); }
        }
    }
    public function testExplicitZeroOverridesDefaultsAndInvalidReplacementRollsBack(): void
    {
        $this->rows("INSERT INTO rabat (vare,debitor,rabat,rabatart) VALUES (7861,7861,20,'')");
        $this->rows('UPDATE adresser SET rabatgruppe=7861 WHERE id=$1',[$this->fixture['konto_id']]);
        $this->rows('UPDATE varer SET dvrg=7861 WHERE id=$1',[$this->fixture['product_id']]);
        $body=$this->body(); unset($body['lines'][0]['discount_pct']);
        $q=$this->request('POST','quote.php',$body); self::assertSame(200,$q['status'],$q['body']);
        self::assertSame('20.00',$q['json']['data']['lines'][0]['discount_pct']);
        $body['lines'][0]['discount_pct']='0'; $body['lines'][0]['unit_price']='0';
        $q=$this->request('POST','quote.php',$body); self::assertSame(200,$q['status'],$q['body']);
        self::assertSame('0.00',$q['json']['data']['lines'][0]['discount_pct']);
        self::assertSame('0.00',$q['json']['data']['totals']['gross']);
        $precise=$body; $precise['lines'][0]['discount_pct']='12.34567';
        $qPrecise=$this->request('POST','quote.php',$precise); self::assertSame(200,$qPrecise['status'],$qPrecise['body']);
        self::assertSame('12.34567',$qPrecise['json']['data']['lines'][0]['discount_pct']);
        $precise['lines'][0]['unit_price']='1.001';
        self::assertSame(422,$this->request('POST','quote.php',$precise)['status']);
        $r=$this->request('POST','index.php',$body); self::assertSame(201,$r['status'],$r['body']);
        $order=$r['json']['data']; self::assertSame($q['json']['data']['totals'],$order['totals']);
        $before=$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1',[$order['id']]);
        $bad=['client_id'=>self::uuid(),'lines'=>[['vare_id'=>$this->fixture['product_id'],'quantity'=>'1;SELECT 1']]];
        $r=$this->request('PUT','lines.php?order_id='.$order['id'],$bad,['If-Match: '.$order['etag']]);
        self::assertSame(422,$r['status']); self::assertSame($before,$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1',[$order['id']]));
        $this->rows('DELETE FROM rabat WHERE vare=7861 AND debitor=7861');
    }
    public function testDatedForeignExchangeConvertsCataloguePriceAndCost(): void
    {
        $this->rows("UPDATE grupper SET box3='EUR' WHERE art='DG' AND kodenr=7861");
        $this->rows("DELETE FROM grupper WHERE art='VK' AND (box1='EUR' OR kodenr=7861)");
        $this->rows("INSERT INTO grupper (art,kodenr,box1) VALUES ('VK',7861,'EUR')");
        $this->rows('DELETE FROM valuta WHERE gruppe=7861');
        $this->rows("INSERT INTO valuta (gruppe,valdate,kurs) VALUES (7861,CURRENT_DATE-2,700),(7861,CURRENT_DATE,750),(7861,CURRENT_DATE+1,900)");
        $this->rows('UPDATE varer SET salgspris=750,kostpris=225 WHERE id=$1',[$this->fixture['product_id']]);
        $body=$this->body(); $body['lines']=[['vare_id'=>$this->fixture['product_id'],'quantity'=>'2']];
        $expected=['net'=>'200.00','vat'=>'50.00','gross'=>'250.00','currency'=>'EUR'];
        $q=$this->request('POST','quote.php',$body); self::assertSame(200,$q['status'],$q['body']);
        self::assertSame('100.00',$q['json']['data']['lines'][0]['unit_price']);
        self::assertSame($expected,$q['json']['data']['totals']);
        foreach (['tilbud','ordre'] as $kind) {
            $body['kind']=$kind; $body['client_id']=self::uuid();
            $r=$this->request('POST','index.php',$body); self::assertSame(201,$r['status'],$r['body']);
            self::assertSame($expected,$r['json']['data']['totals']);
            $id=$r['json']['data']['id'];
            $header=$this->rows('SELECT valutakurs,kostpris FROM ordrer WHERE id=$1',[$id])[0];
            self::assertSame(750.0,(float)$header['valutakurs']); self::assertSame(60.0,(float)$header['kostpris']);
            $line=$this->rows('SELECT pris,kostpris FROM ordrelinjer WHERE ordre_id=$1',[$id])[0];
            self::assertSame([100.0,30.0],array_map('floatval',array_values($line)));
        }
    }
    public function testMissingForeignRateRejectsQuoteAndCreateWithoutWrites(): void
    {
        $this->rows("UPDATE grupper SET box3='EUR' WHERE art='DG' AND kodenr=7861");
        $this->rows("DELETE FROM grupper WHERE art='VK' AND box1='EUR'");
        $this->rows("INSERT INTO grupper (art,kodenr,box1) VALUES ('VK',7861,'EUR')");
        $this->rows('DELETE FROM valuta WHERE gruppe=7861');
        foreach (['future','zero','negative','absent'] as $scenario) {
            $this->rows('DELETE FROM valuta WHERE gruppe=7861');
            if ($scenario!=='absent') {
                $this->rows('INSERT INTO valuta (gruppe,valdate,kurs) VALUES (7861,$1,$2)',[
                    date('Y-m-d',strtotime($scenario==='future' ? '+1 day' : 'today')),$scenario==='negative' ? -750 : ($scenario==='zero' ? 0 : 750)]);
            }
            $before=$this->rows('SELECT (SELECT count(*) FROM ordrer) AS orders,(SELECT count(*) FROM ordrelinjer) AS lines,(SELECT count(*) FROM mobile_idempotency) AS replays');
            foreach (['quote.php','index.php'] as $file) {
                $r=$this->request('POST',$file,$this->body());
                self::assertSame(422,$r['status'],$scenario.': '.$r['body']);
                self::assertSame('validation_failed',$r['json']['data']['error']);
                self::assertSame('missing_exchange_rate',$r['json']['data']['details']['reason']);
                self::assertSame($before,$this->rows('SELECT (SELECT count(*) FROM ordrer) AS orders,(SELECT count(*) FROM ordrelinjer) AS lines,(SELECT count(*) FROM mobile_idempotency) AS replays'));
            }
        }
    }
    public function testBaseCurrencyAlwaysHasRateOneHundred(): void
    {
        foreach (['DKK',''] as $currency) {
            $this->rows('UPDATE grupper SET box3=$1 WHERE art=\'DG\' AND kodenr=7861',[$currency]);
            $order=$this->create();
            self::assertSame('DKK',$order['totals']['currency']);
            self::assertSame(100.0,(float)$this->rows('SELECT valutakurs FROM ordrer WHERE id=$1',[$order['id']])[0]['valutakurs']);
        }
    }
    public function testExplicitZeroWithCampaignOrB2bQuantityDiscount(): void
    {
        foreach (['campaign','b2b','margin'] as $scenario) {
            $this->rows("UPDATE varer SET salgspris=$1,kostpris=$2,m_antal='2',m_rabat='10',m_type='percent',tier_price=100,
                special_price=$3,campaign_cost=20,specialtype='price',special_from_date=CURRENT_DATE-1,
                special_to_date=CURRENT_DATE+1,special_from_time='00:00:00',special_to_time='23:59:59' WHERE id=$4",
                [$scenario==='margin' ? 0 : 100,$scenario==='margin' ? 0.3 : 30,$scenario==='b2b' ? 0 : 100,$this->fixture['product_id']]);
            $this->rows('UPDATE grupper SET box8=$1 WHERE art=\'DG\' AND kodenr=7861',[$scenario==='b2b' ? 'on' : '']);
            $body=$this->body(); $body['lines'][0]['unit_price']='0'; $body['lines'][0]['discount_pct']='0';
            $q=$this->request('POST','quote.php',$body); self::assertSame(200,$q['status'],$q['body']);
            $expected=['net'=>'0.00','vat'=>'0.00','gross'=>'0.00','currency'=>'DKK'];
            self::assertSame($expected,$q['json']['data']['totals'],$scenario);
            $r=$this->request('POST','index.php',$body); self::assertSame(201,$r['status'],$r['body']);
            self::assertSame($expected,$r['json']['data']['totals'],$scenario);
            $line=$this->rows('SELECT pris,m_rabat,vat_price,kostpris FROM ordrelinjer WHERE ordre_id=$1',[$r['json']['data']['id']])[0];
            self::assertSame([0.0,0.0,0.0,$scenario==='margin' ? 0.0 : 30.0],array_map('floatval',array_values($line)),$scenario);
            $replacement=['client_id'=>self::uuid(),'lines'=>$body['lines']];
            $replace=$this->request('PUT','lines.php?order_id='.$r['json']['data']['id'],$replacement,['If-Match: '.$r['json']['data']['etag']]);
            self::assertSame(200,$replace['status'],$replace['body']); self::assertSame($expected,$replace['json']['data']['totals']);
        }
    }
    public function testCampaignQuantityDiscountStillAppliesWithoutPriceOverride(): void
    {
        $this->rows("UPDATE varer SET m_antal='2',m_rabat='10',m_type='percent',special_price=100,campaign_cost=20,
            specialtype='price',special_from_date=CURRENT_DATE-1,special_to_date=CURRENT_DATE+1,
            special_from_time='00:00:00',special_to_time='23:59:59' WHERE id=$1",[$this->fixture['product_id']]);
        $body=$this->body(); unset($body['lines'][0]['unit_price']); $body['lines'][0]['discount_pct']='0';
        $q=$this->request('POST','quote.php',$body); self::assertSame(200,$q['status'],$q['body']);
        $expected=['net'=>'180.00','vat'=>'45.00','gross'=>'225.00','currency'=>'DKK'];
        self::assertSame($expected,$q['json']['data']['totals']);
        $r=$this->request('POST','index.php',$body); self::assertSame(201,$r['status'],$r['body']);
        self::assertSame($expected,$r['json']['data']['totals']);
        $line=$this->rows('SELECT m_rabat,kostpris FROM ordrelinjer WHERE ordre_id=$1',[$r['json']['data']['id']])[0];
        self::assertSame([10.0,30.0],array_map('floatval',array_values($line)));
    }
    public function testAccountingOverridesRejectUnchangedReplacementAndPreserveRows(): void
    {
        $order=$this->create();
        foreach (['momsfri'=>'on','momssats'=>12.5,'omvbet'=>'on','vat_account'=>7902,'bogf_konto'=>7862] as $field=>$value) {
            $original=$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1',[$order['id']])[0];
            $this->rows("UPDATE ordrelinjer SET $field=$1 WHERE ordre_id=$2",[$value,$order['id']]);
            $before=$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1',[$order['id']]);
            $header=$this->rows('SELECT * FROM ordrer WHERE id=$1',[$order['id']]);
            $read=$this->request('GET','index.php?id='.$order['id'])['json']['data'];
            self::assertFalse($read['editable'],$field); self::assertSame('line_accounting',$read['not_editable_why']);
            $body=['client_id'=>self::uuid(),'lines'=>$this->body()['lines']];
            $r=$this->request('PUT','lines.php?order_id='.$order['id'],$body,['If-Match: '.$read['etag']]);
            self::assertSame(409,$r['status'],$r['body']); self::assertSame('order_not_editable',$r['json']['data']['details']['reason']);
            self::assertSame($before,$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1',[$order['id']]),$field);
            self::assertSame($header,$this->rows('SELECT * FROM ordrer WHERE id=$1',[$order['id']]),$field);
            $this->rows("UPDATE ordrelinjer SET $field=$1 WHERE ordre_id=$2",[$original[$field],$order['id']]);
        }
        // Catalogue drift: the persisted exemption and its totals must survive an unchanged mobile save.
        $this->rows("UPDATE grupper SET box7='on' WHERE art='VG' AND kodenr=7861");
        $order=$this->create(); self::assertSame('0.00',$order['totals']['vat']);
        $this->rows("UPDATE grupper SET box7='' WHERE art='VG' AND kodenr=7861");
        $before=$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1',[$order['id']]);
        $header=$this->rows('SELECT * FROM ordrer WHERE id=$1',[$order['id']]);
        $read=$this->request('GET','index.php?id='.$order['id'])['json']['data']; self::assertFalse($read['editable']);
        $r=$this->request('PUT','lines.php?order_id='.$order['id'],['client_id'=>self::uuid(),'lines'=>$this->body()['lines']],['If-Match: '.$read['etag']]);
        self::assertSame(409,$r['status'],$r['body']); self::assertSame('order_not_editable',$r['json']['data']['details']['reason']);
        self::assertSame($before,$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1',[$order['id']]));
        self::assertSame($header,$this->rows('SELECT * FROM ordrer WHERE id=$1',[$order['id']]));
    }

}
