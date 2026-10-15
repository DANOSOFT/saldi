<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/debitor/InvoiceFinalizeCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.04
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

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../restapi/fixtures/t2/InvoiceFixture.php';
require_once __DIR__ . '/../../restapi/support/InvoiceShopStub.php';
final class InvoiceFinalizeCharacterizationTest extends TestCase
{
    private static $conn;
    private static string $session;
    public static function setUpBeforeClass(): void
    {
        if ($reason = CharacterizationEnv::unavailableReason()) { self::markTestSkipped($reason); }
        self::$session='t2b6b'.bin2hex(random_bytes(12));
        CharacterizationEnv::bootstrapTenant();
        self::$conn = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $registry = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        CharacterizationEnv::rows($registry, 'DELETE FROM online WHERE session_id=$1',[self::$session]);
        CharacterizationEnv::rows($registry, "INSERT INTO online (session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id) VALUES ($1,'chartest',$2,$3,$4,$6,$5,false,0)",[self::$session,CharacterizationEnv::testDb(),CharacterizationEnv::pgUser(),str_repeat('9',50),(string)time(),(int)CharacterizationEnv::one(self::$conn,"SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")['year']]);
        pg_close($registry);
    }
    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            $registry = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            CharacterizationEnv::rows($registry,'DELETE FROM online WHERE session_id=$1',[self::$session]);
            pg_close($registry); pg_close(self::$conn); CharacterizationEnv::teardownTenant();
        }
    }
    private function rows(string $sql, array $args=[]): array { return CharacterizationEnv::rows(self::$conn,$sql,$args); }
    private function page(array $get, array $post=[]): string
    {
        $stdout = tempnam(sys_get_temp_dir(),'sales-out-'); $stderr = tempnam(sys_get_temp_dir(),'sales-err-');
        $process = proc_open([PHP_BINARY,__DIR__.'/../support/run_ordre_page.php',json_encode(['get'=>$get,'post'=>$post]),self::$session],
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

    private function fixture(bool $posted=true): array { return InvoiceFixture::create(self::$conn,[CharacterizationEnv::class,'rows'],$posted); }
    private function invoicePage(string $page, int $id, array $get=[]): string
    {
        $r=CharacterizationEnv::runChild(__DIR__.'/../support/run_debitor_bogfor_page.php',[
            json_encode(['page'=>$page,'get'=>array_merge(['id'=>$id,'genfakt'=>'on'],$get)]),self::$session]);
        self::assertSame(0,$r['exit'],$r['stderr'].$r['stdout']);
        self::assertStringNotContainsString('Fatal error',$r['stdout']);
        return $r['stdout'];
    }
    public function testStraksbogforOnAndOffAndSecondInvoice(): void
    {
        foreach ([false,true] as $posted) {
            $f=$this->fixture($posted);
            $this->invoicePage('levering',$f['order_id'],['hurtigfakt'=>'on']);
            $this->invoicePage('bogfor',$f['order_id']);
            $h=$this->rows('SELECT status,fakturanr,sum,moms FROM ordrer WHERE id=$1',[$f['order_id']])[0];
            self::assertSame($posted ? '4' : '3',$h['status']);
            self::assertGreaterThan(0,(int)$h['fakturanr']);
            self::assertSame($posted ? 1 : 0,count($this->rows('SELECT id FROM openpost WHERE refnr=$1',[$f['order_id']])));
            self::assertStringContainsString('invoice allready created',$this->invoicePage('bogfor',$f['order_id']));
            self::assertSame($h,$this->rows('SELECT status,fakturanr,sum,moms FROM ordrer WHERE id=$1',[$f['order_id']])[0]);
        }
    }
    public function testClosedPeriodOutsideYearAndMissingDeliveryDate(): void
    {
        foreach (['closed','outside','missing'] as $case) {
            $f=$this->fixture(false);
            if ($case==='closed') {
                $this->rows("INSERT INTO moms_periode_luk (kalender_aar,kalender_maaned,status) VALUES (EXTRACT(YEAR FROM CURRENT_DATE),EXTRACT(MONTH FROM CURRENT_DATE),'closed')");
            } elseif ($case==='outside') {
                $this->rows("UPDATE ordrer SET fakturadate='2099-01-01' WHERE id=$1",[$f['order_id']]);
            } else { $this->rows('UPDATE ordrer SET levdate=NULL WHERE id=$1',[$f['order_id']]); }
            $html=$this->invoicePage('bogfor',$f['order_id']);
            self::assertStringContainsString(['closed'=>'lukket','outside'=>'udenfor regnskabs','missing'=>'Leveringsdato SKAL'][$case],$html);
            self::assertSame('1',$this->rows('SELECT status FROM ordrer WHERE id=$1',[$f['order_id']])[0]['status']);
            self::assertSame([],$this->rows('SELECT id FROM openpost WHERE refnr=$1',[$f['order_id']]));
            $this->rows('DELETE FROM moms_periode_luk');
        }
    }
    public function testPartiallyDeliveredWebPaths(): void
    {
        foreach ([false,true] as $quick) {
            $f=$this->fixture(false);
            $this->rows('UPDATE ordrelinjer SET leveres=1 WHERE id=$1',[$f['line_id']]);
            $this->invoicePage('levering',$f['order_id']);
            $before=InvoiceFixture::stock(self::$conn,[CharacterizationEnv::class,'rows'],$f);
            self::assertSame('19.000',$before['quantity']);
            self::assertSame(1.0,array_sum(array_column($before['sales'],'antal')));
            if ($quick) { $this->invoicePage('levering',$f['order_id'],['hurtigfakt'=>'on']); }
            $this->invoicePage('bogfor',$f['order_id']);
            $after=InvoiceFixture::stock(self::$conn,[CharacterizationEnv::class,'rows'],$f);
            self::assertSame($quick ? '18.000' : '19.000',$after['quantity']);
            self::assertSame($quick ? 2.0 : 1.0,array_sum(array_column($after['sales'],'antal')));
            if (!$quick) {
                self::assertSame($before['reservations'],$after['reservations']);
                self::assertSame($before['stock'],$after['stock']);
                self::assertSame($before['quantity'],$after['quantity']);
                foreach ($after['sales'] as $sale) {
                    self::assertSame('90.000',$sale['pris']);
                    self::assertSame(date('Y-m-d'),$sale['fakturadate']);
                    self::assertSame($before['sales'][0]['id'],$sale['id']);
                }
            }
            else { self::assertSame([],$after['reservations']); self::assertSame('20.000',$after['stock'][0]['beholdning']); }
        }
    }
    public function testGodkendUsesPersistedReservationQuantityAndDefaultsDate(): void
    {
        $f=$this->fixture(false);
        $this->rows('UPDATE ordrer SET status=0,levdate=NULL WHERE id=$1',[$f['order_id']]);
        $form=$this->form($this->page(['id'=>$f['order_id']]));
        $form['godkend']='on'; $form['levdato']='';
        $this->page(['id'=>$f['order_id']],$form);
        $h=$this->rows('SELECT status,levdate FROM ordrer WHERE id=$1',[$f['order_id']])[0];
        self::assertSame('1',$h['status']); self::assertSame((new DateTimeImmutable('now',new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d'),$h['levdate']);
        self::assertSame('2.000',$this->rows('SELECT antal,leveres FROM ordrelinjer WHERE id=$1',[$f['line_id']])[0]['leveres']);
        self::assertSame('2.000',$this->rows('SELECT antal FROM reservation WHERE linje_id=$1',[$f['line_id']])[0]['antal']);
    }
    public function testDefaultDeliveryValidationExits(): void
    {
        foreach (['variant','over','serial_sale','serial_return','group'] as $case) {
            $f=$this->fixture(false);
            if ($case==='variant') { $this->rows('INSERT INTO variant_varer (vare_id) VALUES ($1)',[$f['product_id']]); }
            if ($case==='over') { $this->rows('UPDATE ordrelinjer SET leveres=3 WHERE id=$1',[$f['line_id']]); }
            if ($case==='serial_sale' || $case==='serial_return') {
                $this->rows("UPDATE ordrelinjer SET serienr='on',antal=$1,leveres=$1 WHERE id=$2",[$case==='serial_sale' ? 2 : -2,$f['line_id']]);
            }
            if ($case==='group') { $this->rows('UPDATE varer SET gruppe=0 WHERE id=$1',[$f['product_id']]); }
            $html=$this->invoicePage('delivery_engine',$f['order_id']);
            self::assertStringNotContainsString('RETURNED:',$html,$case);
            self::assertStringContainsString(['variant'=>'variantinformation','over'=>'for meget','serial_sale'=>'serienumre','serial_return'=>'serienumre','group'=>'varegruppe'][$case],$html);
            self::assertSame('20.000',InvoiceFixture::stock(self::$conn,[CharacterizationEnv::class,'rows'],$f)['quantity']);
        }
    }
    public function testDefaultWebDeliverySynchronizesShopImmediately(): void
    {
        $f=$this->fixture(false);
        $stub=new InvoiceShopStub(CharacterizationEnv::testDb());
        $apis=$this->rows("SELECT id,box4,box5,box6 FROM grupper WHERE art='API'");
        $created=[];
        if (!$apis) { $created=$this->rows("INSERT INTO grupper (art,kodenr) VALUES ('API',1) RETURNING id"); }
        try {
            $this->rows("UPDATE grupper SET box4=$1,box5='',box6='' WHERE art='API'",[$stub->endpoint($f['order_id'],$f['product_id'])]);
            $this->invoicePage('levering',$f['order_id']);
            $stockCalls=array_values(array_filter($stub->calls(),static fn($c)=>isset($c['query']['update_stock'])));
            self::assertCount(1,$stockCalls);
            self::assertSame('1',$stockCalls[0]['order']['status']);
            self::assertNull($stockCalls[0]['order']['fakturanr']);
            self::assertSame('18.000',InvoiceFixture::stock(self::$conn,[CharacterizationEnv::class,'rows'],$f)['quantity']);
        } finally {
            foreach ($apis as $api) { $this->rows('UPDATE grupper SET box4=$1,box5=$2,box6=$3 WHERE id=$4',[$api['box4'],$api['box5'],$api['box6'],$api['id']]); }
            foreach ($created as $api) { $this->rows('DELETE FROM grupper WHERE id=$1',[$api['id']]); }
            $stub->stop();
        }
    }
    public function testDownstreamDefaultsStillExitAndRollback(): void
    {
        foreach (['batch_shortage','batch_missing','credit_batch_missing','missing_vat_account'] as $case) {
            $f=$this->fixture(false);
            $id=$f['order_id'];
            if ($case==='missing_vat_account') {
                $this->invoicePage('levering',$id);
                $this->rows("UPDATE grupper SET box1='0' WHERE art='SM' AND kodenr=7861");
            } else {
                $this->rows("UPDATE grupper SET box9='on',box3='7900' WHERE art='VG' AND kodenr=7861");
                if ($case==='batch_shortage') {
                    $this->rows('UPDATE batch_kob SET rest=1 WHERE id=$1',[$f['batch_id']]);
                } elseif ($case==='batch_missing') {
                    $this->rows('INSERT INTO reservation (linje_id,vare_id,batch_kob_id,antal,lager) VALUES ($1,$2,$3,1,1),($1,$2,2147483647,1,1)',[$f['line_id'],$f['product_id'],$f['batch_id']]);
                } else {
                    $this->rows("UPDATE ordrer SET art='DK' WHERE id=$1",[$id]);
                    $this->rows('UPDATE ordrelinjer SET antal=-2,leveres=-2,kred_linje_id=2147483647 WHERE id=$1',[$f['line_id']]);
                    $this->rows('INSERT INTO batch_salg (ordre_id,linje_id,vare_id,antal,batch_kob_id) VALUES (0,2147483647,$1,2,0)',[$f['product_id']]);
                }
            }
            $before=InvoiceFixture::stock(self::$conn,[CharacterizationEnv::class,'rows'],$f);
            $html=$this->invoicePage($case==='missing_vat_account' ? 'bogfor' : 'delivery_engine',$id);
            self::assertStringNotContainsString('RETURNED:',$html,$case);
            self::assertStringContainsString([
                'batch_shortage'=>'Reserveret antal', 'batch_missing'=>'Indkbsordre kan ikke findes',
                'credit_batch_missing'=>'uoverensstemmelse', 'missing_vat_account'=>'Manglende kontonummer for momstype'
            ][$case],$html,$case);
            self::assertSame($before,InvoiceFixture::stock(self::$conn,[CharacterizationEnv::class,'rows'],$f),$case);
        }
    }
    public function testDefaultPostingVatAccountOneStillExits(): void
    {
        $f=$this->fixture(); $id=$f['order_id'];
        $this->invoicePage('levering',$id);
        $this->rows("UPDATE grupper SET box1='1' WHERE art='SM' AND kodenr=7861");
        $this->rows("INSERT INTO kontoplan (kontonr,beskrivelse,kontotype,regnskabsaar) SELECT 1,'VAT termination fixture','D',$1 WHERE NOT EXISTS (SELECT 1 FROM kontoplan WHERE kontonr=1 AND regnskabsaar=$1)",[$f['year']]);
        $before=InvoiceFixture::stock(self::$conn,[CharacterizationEnv::class,'rows'],$f);
        $header=$this->rows('SELECT * FROM ordrer WHERE id=$1',[$id]);
        $html=$this->invoicePage('posting_engine',$id);
        self::assertStringNotContainsString('RETURNED:',$html);
        self::assertSame($before,InvoiceFixture::stock(self::$conn,[CharacterizationEnv::class,'rows'],$f));
        self::assertSame($header,$this->rows('SELECT * FROM ordrer WHERE id=$1',[$id]));
        self::assertSame([],$this->rows('SELECT id FROM transaktioner WHERE ordre_id=$1',[$id]));
    }
    public function testCreditNoteSettlementUsesSharedSequenceAndLatestDate(): void
    {
        $f=$this->fixture();
        $this->invoicePage('levering',$f['order_id'],['hurtigfakt'=>'on']);
        $this->invoicePage('bogfor',$f['order_id']);
        $original=$f['order_id'];
        $credit=$this->rows("INSERT INTO ordrer (konto_id,kontonr,firmanavn,art,status,momssats,valuta,valutakurs,ordredate,levdate,fakturadate,ordrenr,sum,moms,betalingsbet,betalingsdage,kred_ord_id) VALUES ($1,'7861','Credit fixture','DK',1,25,'DKK',100,CURRENT_DATE,CURRENT_DATE,CURRENT_DATE,7862,-180,-45,'Netto',14,$2) RETURNING id",[$f['konto_id'],$original])[0];
        $f['order_id']=(int)$credit['id'];
        SalesFixture::line(self::$conn,[CharacterizationEnv::class,'rows'],$f,['antal'=>-2,'leveres'=>-2,'kred_linje_id'=>$f['line_id']]);
        $this->invoicePage('levering',$f['order_id'],['hurtigfakt'=>'on']);
        $this->invoicePage('bogfor',$f['order_id']);
        $posts=$this->rows('SELECT amount,udlignet,udlign_id,udlign_date FROM openpost WHERE refnr IN ($1,$2) ORDER BY amount',[$original,$f['order_id']]);
        self::assertCount(2,$posts); self::assertSame(['1','1'],array_column($posts,'udlignet'));
        self::assertGreaterThan(0,(int)$posts[0]['udlign_id']); self::assertSame($posts[0]['udlign_id'],$posts[1]['udlign_id']);
        self::assertSame(date('Y-m-d'),$posts[0]['udlign_date']);
        $next=$this->rows("SELECT nextval('openpost_udlign_seq') AS n")[0]['n'];
        self::assertGreaterThan((int)$posts[0]['udlign_id'],(int)$next);
    }
}
