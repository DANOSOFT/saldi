<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/debitor/OrderTotalsCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Pin the web header's unchanged exchange-rate initialization.
// 20261007 CDX/LH Assert the restored tenant SQL trail on the web write path.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../restapi/fixtures/t2/SalesFixture.php';
require_once __DIR__ . '/../support/ModificationTrail.php';

final class OrderTotalsCharacterizationTest extends TestCase
{
    private static $conn;
    private const SESSION = 't2b6acharacterization0000000001';
    public static function setUpBeforeClass(): void
    {
        if ($reason = CharacterizationEnv::unavailableReason()) { self::markTestSkipped($reason); }
        CharacterizationEnv::bootstrapTenant();
        self::$conn = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $registry = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        CharacterizationEnv::rows($registry, 'DELETE FROM online WHERE session_id=$1',[self::SESSION]);
        CharacterizationEnv::rows($registry, "INSERT INTO online (session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id) VALUES ($1,'chartest',$2,$3,$4,$6,$5,false,0)",[self::SESSION,CharacterizationEnv::testDb(),CharacterizationEnv::pgUser(),str_repeat('9',50),(string)time(),(int)CharacterizationEnv::one(self::$conn,"SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")['year']]);
        pg_close($registry);
    }
    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            $registry = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            CharacterizationEnv::rows($registry,'DELETE FROM online WHERE session_id=$1',[self::SESSION]);
            pg_close($registry); pg_close(self::$conn); CharacterizationEnv::teardownTenant();
        }
    }
    private function rows(string $sql, array $args=[]): array { return CharacterizationEnv::rows(self::$conn,$sql,$args); }
    private function page(array $get, array $post=[]): string
    {
        $stdout = tempnam(sys_get_temp_dir(),'sales-out-'); $stderr = tempnam(sys_get_temp_dir(),'sales-err-');
        $process = proc_open([PHP_BINARY,__DIR__.'/../support/run_ordre_page.php',json_encode(['get'=>$get,'post'=>$post]),self::SESSION],
            [0=>['file','/dev/null','r'],1=>['file',$stdout,'w'],2=>['file',$stderr,'w']],$pipes);
        $child = ['exit'=>proc_close($process),'stdout'=>file_get_contents($stdout),'stderr'=>file_get_contents($stderr)];
        unlink($stdout); unlink($stderr);
        self::assertSame(0,$child['exit'],$child['stderr'].substr($child['stdout'],-2000));
        self::assertStringNotContainsString('Fatal error',$child['stdout']);
        return $child['stdout'];
    }
    /** @return array Browser form fields, parsed from the real rendered page. */
    private function form(string $html): array
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom); $pairs=[];
        foreach ($xpath->query('//form[@name="ordre"]//*[@name]') as $el) {
            if ($el->hasAttribute('disabled')) { continue; }
            $name=$el->getAttribute('name'); $type=$el->getAttribute('type');
            if (in_array($type,['button','submit','reset'],true) || (in_array($type,['checkbox','radio'],true) && !$el->hasAttribute('checked'))) { continue; }
            $value=$el->getAttribute('value');
            if ($el->tagName==='textarea') { $value=$el->textContent; }
            if ($el->tagName==='select') {
                $selected=$xpath->query('./option[@selected]',$el)->item(0) ?: $xpath->query('./option',$el)->item(0);
                $value=$selected ? $selected->getAttribute('value') : '';
            }
            $pairs[]=urlencode($name).'='.urlencode($value);
        }
        parse_str(implode('&',$pairs),$fields); $fields['save']='Gem';
        return $fields;
    }
    public function testEightTotalsThroughPageSave(): void
    {
        $cases=[
            'percent'=>[[],180,45,60], 'amount'=>[['rabatart'=>'amount'],180,45,60],
            'exempt'=>[['momsfri'=>'on'],180,0,60], 'mixed'=>[[],280,57.5,90],
            'inclusive'=>[[],180,45,60], 'surcharge'=>[[],198,49.5,60],
            'quantity'=>[['rabat'=>0,'m_rabat'=>5],190,47.5,60], 'credit'=>[['antal'=>-2],-180,-45,-60]
        ];
        foreach ($cases as $name=>[$opts,$net,$vat,$cost]) {
            $f=SalesFixture::create(self::$conn,[CharacterizationEnv::class,'rows']);
            SalesFixture::line(self::$conn,[CharacterizationEnv::class,'rows'],$f,$opts);
            if ($name==='mixed') { SalesFixture::line(self::$conn,[CharacterizationEnv::class,'rows'],$f,['posnr'=>2,'antal'=>1,'rabat'=>0,'momssats'=>12.5]); }
            if ($name==='inclusive') { $this->rows("UPDATE settings SET var_value='on' WHERE var_name='vatPrivateCustomers'"); }
            if ($name==='surcharge') { $this->rows('UPDATE ordrer SET procenttillag=10 WHERE id=$1',[$f['order_id']]); }
            if ($name==='credit') { $this->rows("UPDATE ordrer SET art='DK' WHERE id=$1",[$f['order_id']]); }
            $html=$this->page(['id'=>$f['order_id']]);
            $form=$this->form($html);
            self::assertSame((string)$f['order_id'],(string)($form['id']??''),$name);
            if ($name==='surcharge') { $form['procenttillag']='10'; }
            $this->page(['id'=>$f['order_id']],$form);
            $r=$this->rows('SELECT sum,moms,kostpris FROM ordrer WHERE id=$1',[$f['order_id']])[0];
            self::assertSame([$net*1.0,$vat*1.0,$cost*1.0],array_map('floatval',array_values($r)),$name);
        }
    }
    public function testHeaderAndDeletionCleanup(): void
    {
        $f=SalesFixture::create(self::$conn,[CharacterizationEnv::class,'rows']);
        $trail = ModificationTrail::read(CharacterizationEnv::testDb());
        $this->page(['konto_id'=>$f['konto_id']]);
        ModificationTrail::assertWrite(CharacterizationEnv::testDb(), $trail, 'insert into ordrer\\b');
        $h=$this->rows('SELECT * FROM ordrer WHERE konto_id=$1 ORDER BY id DESC LIMIT 1',[$f['konto_id']])[0];
        self::assertSame('Sales O\'Neil',$h['firmanavn']); self::assertSame('Testvej 1',$h['addr1']);
        self::assertSame('0',$h['status']); self::assertSame('DO',$h['art']); self::assertSame('123456789012345',$h['phone']);
        self::assertNull($h['valutakurs']);
        $line=SalesFixture::line(self::$conn,[CharacterizationEnv::class,'rows'],$f,['folgevare'=>$f['product_id']]);
        SalesFixture::line(self::$conn,[CharacterizationEnv::class,'rows'],$f,['posnr'=>2,'folgevare'=>-$line]);
        SalesFixture::line(self::$conn,[CharacterizationEnv::class,'rows'],$f,['posnr'=>0,'samlevare'=>(string)$line]);
        $this->rows('INSERT INTO reservation (linje_id,batch_salg_id,vare_id,antal) VALUES ($1,0,$2,2),(0,$3,$2,2)',[$line,$f['product_id'],-$line]);
        $this->rows('INSERT INTO batch_kob (linje_id,vare_id,antal) VALUES ($1,$2,0)',[$line,$f['product_id']]);
        $this->rows('INSERT INTO batch_salg (linje_id,vare_id,antal) VALUES ($1,$2,0)',[$line,$f['product_id']]);
        $this->rows("INSERT INTO serienr (serienr,vare_id,salgslinje_id) VALUES ('fixture',$1,$2)",[$f['product_id'],$line]);
        $form=$this->form($this->page(['id'=>$f['order_id']])); $form['posn1']='-';
        $this->page(['id'=>$f['order_id']],$form);
        foreach (['batch_kob','batch_salg'] as $t) { self::assertSame([],$this->rows("SELECT id FROM $t WHERE linje_id=$1",[$line])); }
        self::assertSame([],$this->rows('SELECT * FROM reservation WHERE linje_id=$1 OR batch_salg_id=$2',[$line,-$line]));
        self::assertSame([],$this->rows('SELECT id FROM ordrelinjer WHERE ordre_id=$1',[$f['order_id']]));
        self::assertSame('0',$this->rows("SELECT salgslinje_id FROM serienr WHERE serienr='fixture'")[0]['salgslinje_id']);
    }
    public function testApprovedWebSaveReservations(): void
    {
        $f=SalesFixture::create(self::$conn,[CharacterizationEnv::class,'rows']);
        $this->rows('UPDATE ordrer SET status=1,levdate=CURRENT_DATE WHERE id=$1',[$f['order_id']]);
        $line=SalesFixture::line(self::$conn,[CharacterizationEnv::class,'rows'],$f,['leveres'=>2]);
        $batch=(int)$this->rows('INSERT INTO batch_kob (ordre_id,linje_id,vare_id,antal,rest,lager,kobsdate) VALUES (0,0,$1,20,20,1,CURRENT_DATE) RETURNING id',[$f['product_id']])[0]['id'];
        $this->rows('INSERT INTO reservation (linje_id,vare_id,batch_kob_id,antal,lager) VALUES ($1,$2,$3,2,0)',[$line,$f['product_id'],$batch]);
        $form=$this->form($this->page(['id'=>$f['order_id']])); $form['dkan1']='3'; $form['leve1']='3';
        $this->page(['id'=>$f['order_id']],$form);
        $reservations=$this->rows('SELECT batch_kob_id,antal FROM reservation WHERE linje_id=$1 ORDER BY batch_kob_id',[$line]);
        // Ordinary web save leaves an existing reservation unchanged when quantity increases.
        self::assertSame([['batch_kob_id'=>(string)$batch,'antal'=>'2.000']],$reservations);
        self::assertSame('3.000',$this->rows('SELECT antal FROM ordrelinjer WHERE id=$1',[$line])[0]['antal']);
    }
    public function testMixedRatesExemptionAndAmountDiscountAgreeWithSharedTotals(): void
    {
        $f=SalesFixture::create(self::$conn,[CharacterizationEnv::class,'rows']);
        SalesFixture::line(self::$conn,[CharacterizationEnv::class,'rows'],$f,['rabatart'=>'amount']);
        SalesFixture::line(self::$conn,[CharacterizationEnv::class,'rows'],$f,['posnr'=>2,'antal'=>1,'rabat'=>0,'momssats'=>12.5]);
        SalesFixture::line(self::$conn,[CharacterizationEnv::class,'rows'],$f,['posnr'=>3,'antal'=>1,'rabat'=>0,'momsfri'=>'on']);
        $this->page(['id'=>$f['order_id']],$this->form($this->page(['id'=>$f['order_id']])));
        $web=$this->rows('SELECT sum,moms,kostpris FROM ordrer WHERE id=$1',[$f['order_id']])[0];
        self::assertSame([380.0,57.5,120.0],array_map('floatval',array_values($web)));
        require_once __DIR__.'/../../../includes/db_query.php';
        require_once __DIR__.'/../../../includes/std_func.php';
        require_once __DIR__.'/../../../includes/orders/orderTotals.php';
        $saved=[];
        foreach (['connection'=>self::$conn,'db'=>CharacterizationEnv::testDb(),'sqdb'=>'pg','regnaar'=>$f['year']] as $key=>$value) {
            $saved[$key]=[array_key_exists($key,$GLOBALS),ifset($GLOBALS,$key)]; $GLOBALS[$key]=$value;
        }
        $requestUri=ifset($_SERVER,'REQUEST_URI'); $_SERVER['REQUEST_URI']='/debitor/ordre.php';
        try { self::assertSame(array_map('floatval',$web),order_totals_compute($f['order_id'])); }
        finally {
            if ($requestUri===null) { unset($_SERVER['REQUEST_URI']); } else { $_SERVER['REQUEST_URI']=$requestUri; }
            foreach ($saved as $key=>[$exists,$value]) { if ($exists) { $GLOBALS[$key]=$value; } else { unset($GLOBALS[$key]); } }
        }
    }
    public function testReceivedAndDeliveredLineRefused(): void
    {
        foreach (['batch_kob','batch_salg'] as $t) {
            $f=SalesFixture::create(self::$conn,[CharacterizationEnv::class,'rows']);
            $line=SalesFixture::line(self::$conn,[CharacterizationEnv::class,'rows'],$f);
            $this->rows("INSERT INTO $t (linje_id,vare_id,antal) VALUES ($1,$2,1)",[$line,$f['product_id']]);
            $form=$this->form($this->page(['id'=>$f['order_id']])); $form['posn1']='-';
            $this->page(['id'=>$f['order_id']],$form);
            self::assertCount(1,$this->rows('SELECT id FROM ordrelinjer WHERE id=$1',[$line]));
        }
    }
}
