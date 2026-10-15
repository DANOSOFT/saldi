<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/InvoiceFinalizeConcurrencyTest.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Cover downstream errors, rollback and webshop commit timing.
// 20261007 CDX/LH Expect invalid_date for malformed invoice dates while retaining fiscal period refusals.

use PHPUnit\Framework\TestCase;
require_once __DIR__.'/support/RestApiEnv.php';
require_once __DIR__.'/fixtures/t2/InvoiceFixture.php';
require_once __DIR__.'/support/InvoiceShopStub.php';

final class InvoiceFinalizeConcurrencyTest extends TestCase
{
    private $conn;
    private const ROOT='/restapi/endpoints/v1/debitor/sales/';
    public static function setUpBeforeClass(): void
    {
        if ($reason=RestApiEnv::unavailableReason()) { self::markTestSkipped($reason); }
        RestApiEnv::bootstrapTenant(); RestApiEnv::loginData();
    }
    public static function tearDownAfterClass(): void { RestApiEnv::teardownTenant(); }
    protected function setUp(): void { $this->conn=RestApiEnv::connect(RestApiEnv::testDb()); }
    protected function tearDown(): void { pg_close($this->conn); }
    private function rows(string $sql,array $params=[]): array { return RestApiEnv::rows($this->conn,$sql,$params); }
    private function fixture(bool $posted=true): array { return InvoiceFixture::create($this->conn,[RestApiEnv::class,'rows'],$posted); }
    private static function uuid(): string
    {
        $h=bin2hex(random_bytes(16)); return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20);
    }
    private function order(int $id): array
    {
        $r=RestApiEnv::http('GET',self::ROOT.'index.php?id='.$id,null,RestApiEnv::authHeaders());
        self::assertSame(200,$r['status'],$r['body']); return $r['json']['data'];
    }
    private function body(int $id,string $to='faktura'): array { return ['client_id'=>self::uuid(),'order_id'=>$id,'to'=>$to]; }
    private function transition(array $body,?string $etag): array
    {
        $headers=RestApiEnv::authHeaders(); if ($etag!==null) { $headers[]='If-Match: '.$etag; }
        $r=RestApiEnv::http('POST',self::ROOT.'transition.php',$body,$headers);
        self::assertIsArray($r['json'],$r['body']); self::assertStringNotContainsString('<BODY',$r['body']);
        return $r;
    }
    private function error(array $r,int $status,string $reason): void
    {
        self::assertSame($status,$r['status'],$r['body']);
        self::assertSame($reason,$r['json']['data']['details']['reason'],$r['body']);
        self::assertSame($status===409 ? 'conflict' : 'validation_failed',$r['json']['data']['error']);
    }
    public function testPostingModesReplayAndTilbudInOneCall(): void
    {
        foreach ([false,true] as $posted) {
            $f=$this->fixture($posted);
            $this->rows('UPDATE ordrer SET status=0,levdate=NULL WHERE id=$1',[$f['order_id']]);
            $o=$this->order($f['order_id']); $body=$this->body($o['id']);
            $r=$this->transition($body,$o['etag']); self::assertSame(200,$r['status'],$r['body']);
            $invoice=$r['json']['data']; self::assertSame($posted ? 4 : 3,$invoice['status']);
            self::assertSame($posted,$invoice['posted']); self::assertGreaterThan(0,(int)$invoice['fakturanr']);
            self::assertSame('faktura',$invoice['kind']); self::assertSame($posted,$invoice['openpost_id']!==null);
            self::assertSame(['net'=>'180.00','vat'=>'45.00','gross'=>'225.00','currency'=>'DKK'],$invoice['totals']);
            $retry=$this->transition($body,$o['etag']); self::assertSame($r['json'],$retry['json']);
            $body['to']='ordre'; $this->error($this->transition($body,$o['etag']),409,'client_id_reused');
            $this->error($this->transition($this->body($o['id']),$o['etag']),409,'already_invoiced');
        }
    }
    public function testApprovalEtagWebLockAndReplayedHeader(): void
    {
        $f=$this->fixture(false); $id=$f['order_id'];
        $this->rows('UPDATE ordrer SET status=0,levdate=NULL WHERE id=$1',[$id]);
        $o=$this->order($id); $body=$this->body($id,'ordre');
        $this->error($this->transition($body,null),422,'if_match_required');
        self::assertSame(412,$this->transition($body,'"stale"')['status']);
        $this->rows("UPDATE ordrer SET hvem='Other user' WHERE id=$1",[$id]);
        $this->error($this->transition($body,$o['etag']),409,'order_locked');
        $this->rows("UPDATE ordrer SET hvem='' WHERE id=$1",[$id]);
        $r=$this->transition($body,$this->order($id)['etag']); self::assertSame(200,$r['status'],$r['body']);
        self::assertSame(1,$r['json']['data']['status']);
        self::assertSame('2.000',$this->rows('SELECT antal FROM reservation WHERE linje_id=$1',[$f['line_id']])[0]['antal']);
        self::assertSame($r['json'],$this->transition($body,null)['json']);
    }
    public function testClosedPeriodOutsideYearMissingExchangeAndNonCalendarYear(): void
    {
        $f=$this->fixture(false); $id=$f['order_id']; $o=$this->order($id);
        $before=InvoiceFixture::stock($this->conn,[RestApiEnv::class,'rows'],$f);
        $this->rows("INSERT INTO moms_periode_luk (kalender_aar,kalender_maaned,status) VALUES (EXTRACT(YEAR FROM CURRENT_DATE),EXTRACT(MONTH FROM CURRENT_DATE),'closed')");
        try { $this->error($this->transition($this->body($id),$o['etag']),422,'period_locked'); }
        finally { $this->rows('DELETE FROM moms_periode_luk'); }
        $b=$this->body($id); $b['invoice_date']='2099-01-01';
        $this->error($this->transition($b,$o['etag']),422,'outside_fiscal_year');
        self::assertSame($before,InvoiceFixture::stock($this->conn,[RestApiEnv::class,'rows'],$f));
        foreach (['2026-02-30',true,'2026-13-01'] as $date) {
            $b=$this->body($id); $b['invoice_date']=$date;
            $this->error($this->transition($b,$o['etag']),422,'invalid_date');
        }
        $this->rows("UPDATE ordrer SET valuta='ZZZ' WHERE id=$1",[$id]);
        $this->error($this->transition($this->body($id),$this->order($id)['etag']),422,'missing_exchange_rate');
        self::assertSame($before,InvoiceFixture::stock($this->conn,[RestApiEnv::class,'rows'],$f));
        $this->rows("UPDATE ordrer SET valuta='DKK' WHERE id=$1",[$id]);
        $year=$f['year']; $original=$this->rows("SELECT box1,box2,box3,box4 FROM grupper WHERE art='RA' AND kodenr=$1",[$year])[0];
        $this->rows("UPDATE grupper SET box1='7',box2='2026',box3='6',box4='2027' WHERE art='RA' AND kodenr=$1",[$year]);
        try {
            $b=$this->body($id); $b['invoice_date']='2027-06-30';
            $r=$this->transition($b,$this->order($id)['etag']); self::assertSame(200,$r['status'],$r['body']);
        } finally { $this->rows("UPDATE grupper SET box1=$1,box2=$2,box3=$3,box4=$4 WHERE art='RA' AND kodenr=$5",array_merge(array_values($original),[$year])); }
    }
    public function testEveryDeliveryValidationReturnsJsonAndRollsBack(): void
    {
        foreach (['variant','over','serial_sale','serial_return','group_after_stock_write','batch_shortage','batch_missing','credit_batch_missing','missing_vat_account','invalid_vat_account'] as $case) {
            $f=$this->fixture(); $id=$f['order_id']; $line=$f['line_id']; $product=$f['product_id'];
            $reason='serial_numbers_missing'; $pos=1;
            if ($case==='variant') { $reason='variant_missing'; $this->rows('INSERT INTO variant_varer (vare_id) VALUES ($1)',[$product]); }
            if ($case==='over') {
                $reason='over_delivery';
                $this->rows('INSERT INTO batch_salg (ordre_id,linje_id,vare_id,antal,batch_kob_id) VALUES ($1,$2,$3,3,$4)',[$id,$line,$product,$f['batch_id']]);
            }
            if ($case==='serial_sale' || $case==='serial_return') {
                $quantity=$case==='serial_sale' ? 2 : -2;
                $this->rows("UPDATE ordrelinjer SET serienr='on',antal=$1,leveres=$1 WHERE id=$2",[$quantity,$line]);
                if ($case==='serial_return') { $this->rows("INSERT INTO serienr (vare_id,salgslinje_id,serienr) VALUES ($1,-1,'Return fixture')",[$product]); }
            }
            if ($case==='group_after_stock_write') {
                $reason='product_group_missing'; $pos=2;
                $bad=$this->rows("INSERT INTO varer (varenr,beskrivelse,gruppe,beholdning) VALUES ('BAD-GROUP','Fixture',0,10) RETURNING id")[0]['id'];
                SalesFixture::line($this->conn,[RestApiEnv::class,'rows'],$f,['posnr'=>2,'vare_id'=>(int)$bad,'varenr'=>'BAD-GROUP']);
            }
            if (in_array($case,['batch_shortage','batch_missing','credit_batch_missing'],true)) {
                $this->rows("UPDATE grupper SET box9='on',box3='7900' WHERE art='VG' AND kodenr=7861");
                $reason=$case==='batch_shortage' ? 'batch_shortage' : 'batch_missing';
                if ($case==='batch_shortage') {
                    $this->rows('UPDATE batch_kob SET rest=1 WHERE id=$1',[$f['batch_id']]);
                } elseif ($case==='batch_missing') {
                    $this->rows('INSERT INTO reservation (linje_id,vare_id,batch_kob_id,antal,lager) VALUES ($1,$2,$3,1,1),($1,$2,2147483647,1,1)',[$line,$product,$f['batch_id']]);
                } else {
                    $this->rows("UPDATE ordrer SET art='DK' WHERE id=$1",[$id]);
                    $this->rows('UPDATE ordrelinjer SET antal=-2,leveres=-2,kred_linje_id=2147483647 WHERE id=$1',[$line]);
                    $this->rows('INSERT INTO batch_salg (ordre_id,linje_id,vare_id,antal,batch_kob_id) VALUES (0,2147483647,$1,2,0)',[$product]);
                }
            }
            if ($case==='missing_vat_account') {
                $reason='missing_vat_account';
                $this->rows("UPDATE grupper SET box1='' WHERE art='SM' AND kodenr=7861");
            }
            if ($case==='invalid_vat_account') {
                $reason='invalid_vat_account';
                $this->rows("UPDATE grupper SET box1='1' WHERE art='SM' AND kodenr=7861");
                $this->rows("INSERT INTO kontoplan (kontonr,beskrivelse,kontotype,regnskabsaar) SELECT 1,'VAT termination fixture','D',$1 WHERE NOT EXISTS (SELECT 1 FROM kontoplan WHERE kontonr=1 AND regnskabsaar=$1)",[$f['year']]);
            }
            $before=InvoiceFixture::stock($this->conn,[RestApiEnv::class,'rows'],$f);
            $header=$this->rows('SELECT * FROM ordrer WHERE id=$1',[$id]);
            $lines=$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1 ORDER BY id',[$id]);
            $serials=$this->rows('SELECT * FROM serienr WHERE vare_id=$1 ORDER BY id',[$product]);
            $batches=$this->rows('SELECT * FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$product]);
            $number=$this->rows("SELECT max(CAST(fakturanr AS integer)) AS n FROM ordrer WHERE fakturanr~'^[0-9]+$'")[0];
            $r=$this->transition($this->body($id),$this->order($id)['etag']);
            $this->error($r,422,$reason);
            if ($case==='invalid_vat_account') { self::assertSame(1,$r['json']['data']['details']['account']); }
            else { self::assertSame($pos,$r['json']['data']['details']['line'],$case); }
            self::assertSame($before,InvoiceFixture::stock($this->conn,[RestApiEnv::class,'rows'],$f),$case);
            self::assertSame($header,$this->rows('SELECT * FROM ordrer WHERE id=$1',[$id]),$case);
            self::assertSame($lines,$this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1 ORDER BY id',[$id]),$case);
            self::assertSame($serials,$this->rows('SELECT * FROM serienr WHERE vare_id=$1 ORDER BY id',[$product]),$case);
            self::assertSame($batches,$this->rows('SELECT * FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$product]),$case);
            self::assertSame($number,$this->rows("SELECT max(CAST(fakturanr AS integer)) AS n FROM ordrer WHERE fakturanr~'^[0-9]+$'")[0]);
            self::assertSame([],$this->rows('SELECT id FROM openpost WHERE refnr=$1',[$id]));
            self::assertSame([],$this->rows('SELECT id FROM transaktioner WHERE ordre_id=$1',[$id]));
            self::assertSame([],$this->rows("SELECT id FROM mobile_idempotency WHERE resource_type='order' AND resource_id=$1",[$id]));
        }
    }
    public function testShopSyncOnlySeesCommittedInvoicesAndNeverRunsOnRefusalOrReplay(): void
    {
        $stub=new InvoiceShopStub(RestApiEnv::testDb());
        $apis=$this->rows("SELECT id,box4,box5,box6 FROM grupper WHERE art='API'");
        $created=[];
        if (!$apis) { $created=$this->rows("INSERT INTO grupper (art,kodenr) VALUES ('API',1) RETURNING id"); }
        try {
            foreach (['posting_refusal','later_line_refusal','success'] as $case) {
                $f=$this->fixture(); $id=$f['order_id'];
                $this->rows('UPDATE lagerstatus SET variant_id=0 WHERE vare_id=$1',[$f['product_id']]);
                $this->rows("UPDATE grupper SET box4=$1,box5='',box6='' WHERE art='API'",[$stub->endpoint($id,$f['product_id'])]);
                if ($case==='posting_refusal') {
                    $this->rows("UPDATE ordrer SET valuta='ZZZ' WHERE id=$1",[$id]);
                } elseif ($case==='later_line_refusal') {
                    $bad=$this->rows("INSERT INTO varer (varenr,beskrivelse,gruppe,beholdning) VALUES ('SHOP-BAD','Fixture',0,10) RETURNING id")[0]['id'];
                    SalesFixture::line($this->conn,[RestApiEnv::class,'rows'],$f,['posnr'=>2,'vare_id'=>(int)$bad,'varenr'=>'SHOP-BAD']);
                }
                $before=InvoiceFixture::stock($this->conn,[RestApiEnv::class,'rows'],$f);
                $body=$this->body($id); $etag=$this->order($id)['etag'];
                $r=$this->transition($body,$etag);
                if ($case!=='success') {
                    $this->error($r,422,$case==='posting_refusal' ? 'missing_exchange_rate' : 'product_group_missing');
                    self::assertSame([],$stub->calls(),$case);
                    self::assertSame($before,InvoiceFixture::stock($this->conn,[RestApiEnv::class,'rows'],$f));
                } else {
                    self::assertSame(200,$r['status'],$r['body']);
                    $calls=$stub->calls();
                    $stockCalls=array_values(array_filter($calls,static fn($c)=>isset($c['query']['update_stock'])));
                    self::assertCount(1,$stockCalls,$stub->diagnostics());
                    self::assertSame('4',$stockCalls[0]['order']['status']);
                    self::assertSame($r['json']['data']['fakturanr'],$stockCalls[0]['order']['fakturanr']);
                    self::assertSame($stockCalls[0]['committed_stock'],$stockCalls[0]['query']['stock']);
                    self::assertNotSame($before['stock'][0]['beholdning'],$stockCalls[0]['committed_stock']);
                    self::assertSame($r['json'],$this->transition($body,$etag)['json']);
                    self::assertSame($calls,$stub->calls(),'Replay must not send another webshop update');
                }
            }
        } finally {
            foreach ($apis as $api) { $this->rows('UPDATE grupper SET box4=$1,box5=$2,box6=$3 WHERE id=$4',[$api['box4'],$api['box5'],$api['box6'],$api['id']]); }
            foreach ($created as $api) { $this->rows('DELETE FROM grupper WHERE id=$1',[$api['id']]); }
            $stub->stop();
        }
    }
    /** @return array<array{status:int,json:?array,body:string}> Concurrent real HTTP requests. */
    private function parallel(array $requests): array
    {
        $multi=curl_multi_init(); $handles=[];
        foreach ($requests as [$body,$etag]) {
            $h=curl_init(RestApiEnv::baseUrl().self::ROOT.'transition.php');
            curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),
                CURLOPT_HTTPHEADER=>array_merge(RestApiEnv::authHeaders(),['Content-Type: application/json','If-Match: '.$etag]),CURLOPT_TIMEOUT=>60]);
            curl_multi_add_handle($multi,$h); $handles[]=$h;
        }
        do { $code=curl_multi_exec($multi,$active); if ($active) { curl_multi_select($multi,0.05); } } while ($active && $code===CURLM_OK);
        $out=[];
        foreach ($handles as $h) {
            $raw=curl_multi_getcontent($h); $out[]=['status'=>(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE),'json'=>json_decode($raw,true),'body'=>$raw];
            curl_multi_remove_handle($multi,$h); curl_close($h);
        }
        curl_multi_close($multi); return $out;
    }
    public function testTwentyParallelOrdersHaveConsecutiveNumbers(): void
    {
        $requests=[];
        for ($i=0;$i<20;$i++) {
            $f=$this->fixture(); $o=$this->order($f['order_id']); $requests[]=[$this->body($o['id']),$o['etag']];
        }
        $numbers=[];
        foreach ($this->parallel($requests) as $r) {
            self::assertSame(200,$r['status'],$r['body']); self::assertTrue($r['json']['data']['posted']);
            $numbers[]=(int)$r['json']['data']['fakturanr'];
        }
        sort($numbers); self::assertSame(range($numbers[0],$numbers[0]+19),$numbers);
    }
    public function testSameOrderTwiceGetsOneInvoiceAndOneConflict(): void
    {
        $f=$this->fixture(); $o=$this->order($f['order_id']);
        $results=$this->parallel([[$this->body($o['id']),$o['etag']],[$this->body($o['id']),$o['etag']]]);
        usort($results,static fn($a,$b)=>$a['status']<=>$b['status']);
        self::assertSame(200,$results[0]['status'],$results[0]['body']); $this->error($results[1],409,'already_invoiced');
        self::assertCount(1,$this->rows('SELECT id FROM openpost WHERE refnr=$1',[$o['id']]));
    }
    public function testSameClientIdParallelReplaysOneInvoice(): void
    {
        $f=$this->fixture(); $o=$this->order($f['order_id']); $request=[$this->body($o['id']),$o['etag']];
        $results=$this->parallel([$request,$request]);
        self::assertSame([200,200],array_column($results,'status')); self::assertSame($results[0]['json'],$results[1]['json']);
        self::assertCount(1,$this->rows('SELECT id FROM openpost WHERE refnr=$1',[$o['id']]));
    }
    public function testConcurrentCreditSettlementsAndAutocommitAllocationsNeverShareAnId(): void
    {
        $credits=[];
        for ($i=0;$i<2;$i++) {
            $f=$this->fixture(); $original=$f['order_id'];
            $r=$this->transition($this->body($original),$this->order($original)['etag']);
            self::assertSame(200,$r['status'],$r['body']);
            $credit=$this->rows("INSERT INTO ordrer (konto_id,kontonr,firmanavn,art,status,momssats,valuta,valutakurs,ordredate,levdate,fakturadate,ordrenr,sum,moms,betalingsbet,betalingsdage,kred_ord_id)
                VALUES ($1,'7861','Credit concurrency','DK',2,25,'DKK',100,CURRENT_DATE,CURRENT_DATE,CURRENT_DATE,7862,-180,-45,'Netto',14,$2) RETURNING id",[$f['konto_id'],$original])[0]['id'];
            $f['order_id']=(int)$credit;
            SalesFixture::line($this->conn,[RestApiEnv::class,'rows'],$f,['antal'=>-2,'leveres'=>0,'kred_linje_id'=>$f['line_id']]);
            $credits[]=(int)$credit;
        }
        $processes=[];
        $barrier=sys_get_temp_dir().'/invoice-credit-'.bin2hex(random_bytes(8));
        foreach (array_merge($credits,[0]) as $id) {
            $input=['barrier'=>$barrier,'mode'=>$id ? 'credit' : 'allocate','db'=>RestApiEnv::testDb(),'db_id'=>RestApiEnv::regnskabId(RestApiEnv::accountOpen()),
                'user_id'=>RestApiEnv::loginData()['user']['id'],'order_id'=>$id];
            $stdout=tempnam(sys_get_temp_dir(),'credit-out-'); $stderr=tempnam(sys_get_temp_dir(),'credit-err-');
            $process=proc_open([PHP_BINARY,__DIR__.'/support/run_invoice_credit.php',json_encode($input)],
                [0=>['file','/dev/null','r'],1=>['file',$stdout,'w'],2=>['file',$stderr,'w']],$pipes);
            $processes[]=[$process,$stdout,$stderr,$id];
        }
        $deadline=microtime(true)+10;
        while (count(glob($barrier.'.ready.*'))<3 && microtime(true)<$deadline) { usleep(10000); }
        $ready=count(glob($barrier.'.ready.*'));
        touch($barrier.'.release');
        $allocated=[];
        foreach ($processes as [$process,$stdout,$stderr,$id]) {
            $exit=proc_close($process); $raw=file_get_contents($stdout); $errors=file_get_contents($stderr);
            unlink($stdout); unlink($stderr);
            self::assertSame(0,$exit,$errors.$raw); $out=json_decode($raw,true); self::assertIsArray($out,$errors.$raw);
            if ($id) { self::assertSame('OK',$out['result']); }
            else { $allocated=$out['ids']; }
        }
        foreach (glob($barrier.'.*') as $file) { unlink($file); }
        self::assertSame(3,$ready,'All writers bootstrap before barrier release');
        $groups=$this->rows('SELECT refnr,udlign_id,udlignet FROM openpost WHERE refnr=ANY($1::int[]) ORDER BY refnr',['{'.implode(',',$credits).'}']);
        self::assertCount(2,$groups); self::assertSame(['1','1'],array_column($groups,'udlignet'));
        $all=array_merge($allocated,array_map('intval',array_column($groups,'udlign_id')));
        self::assertCount(22,array_unique($all));
        foreach ($groups as $group) {
            self::assertCount(2,$this->rows('SELECT id FROM openpost WHERE udlign_id=$1',[$group['udlign_id']]));
        }
    }

}
