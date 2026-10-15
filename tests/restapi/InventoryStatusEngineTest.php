<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/InventoryStatusEngineTest.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Cover legacy NULL variants, existing row identity and location writes outside fiscal years.
// 20261007 CDX/LH Cover undated configuration outside the current fiscal year.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/RestApiEnv.php';

final class InventoryStatusEngineTest extends TestCase
{
    private const PATH='/restapi/endpoints/v1/inventory/status/index.php';
    private static $tenant;
    private int $item;

    public static function setUpBeforeClass(): void
    {
        if (($reason=RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        RestApiEnv::loginData();
        self::$tenant=RestApiEnv::connect(RestApiEnv::testDb());
        // A clone must never retain a real webshop URL from its template.
        RestApiEnv::rows(self::$tenant,"DELETE FROM grupper WHERE art='API'");
        $dir=dirname(__DIR__,2) . '/temp/' . RestApiEnv::testDb();
        if (!is_dir($dir)) {
            mkdir($dir,0770,true);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            pg_close(self::$tenant);
            RestApiEnv::teardownTenant();
        }
    }

    protected function setUp(): void
    {
        $this->item=(int)$this->rows("INSERT INTO varer(varenr,beskrivelse,kostpris,beholdning,gruppe) VALUES($1,'REST stock fixture',4,0,1) RETURNING id",['REST-'.bin2hex(random_bytes(4))])[0]['id'];
    }

    private function rows(string $sql,array $params=[]): array
    {
        return RestApiEnv::rows(self::$tenant,$sql,$params);
    }

    /** @return array{status:int,json:array,headers:array<string,string>,body:string} */
    private function request(string $method,array $body,?int $id=null,array $extraHeaders=[]): array
    {
        $headers=[];
        $curl=curl_init(RestApiEnv::baseUrl() . self::PATH . ($id ? '?id='.$id : ''));
        curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,
            CURLOPT_POSTFIELDS=>json_encode($body,JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER=>array_merge(RestApiEnv::authHeaders(),['Content-Type: application/json'],$extraHeaders),
            CURLOPT_HEADERFUNCTION=>static function ($curl,string $line) use (&$headers): int {
                if (str_contains($line,':')) {
                    [$name,$value]=explode(':',$line,2);
                    $headers[strtolower(trim($name))]=trim($value);
                }
                return strlen($line);
            }]);
        $raw=curl_exec($curl);
        $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $json=json_decode($raw,true);
        self::assertIsArray($json,$raw);
        self::assertStringNotContainsString('<BODY',$raw);
        return ['status'=>$status,'json'=>$json,'headers'=>$headers,'body'=>$raw];
    }

    private function create(string $qty='10',int $variant=0): array
    {
        $r=$this->request('POST',['inventory'=>2,'productId'=>$this->item,'quantity'=>$qty,'variantId'=>$variant,'location'=>"Shelf's A"]);
        self::assertSame(201,$r['status'],$r['body']);
        return $r;
    }

    private function assertStock(int $quantity,int $logs,int $variant=0): void
    {
        self::assertEquals($quantity,$this->rows('SELECT beholdning FROM lagerstatus WHERE vare_id=$1 AND lager=2 AND variant_id=$2',[$this->item,$variant])[0]['beholdning']);
        self::assertEquals($quantity,$this->rows('SELECT beholdning FROM varer WHERE id=$1',[$this->item])[0]['beholdning']);
        self::assertCount($logs,$this->rows('SELECT id FROM stocklog WHERE item_id=$1',[$this->item]));
    }

    public function testQuantityWritesUseConfigurationWithoutACurrentFiscalYear(): void
    {
        $years = $this->rows("SELECT id,box1,box2,box3,box4 FROM grupper WHERE art='RA'");
        $this->rows("UPDATE grupper SET box1='1',box2='2000',box3='12',box4='2000' WHERE art='RA'");
        try {
            $created = $this->create();
            $id = (int)$created['json']['data']['id'];
            foreach ([['PUT',['quantity'=>'8']],['PATCH',['action'=>'adjust_quantity','amount'=>'-1']]] as [$method,$body]) {
                $response = $this->request($method,$body,$id);
                self::assertSame(200,$response['status'],$response['body']);
            }
            $this->assertStock(7,3);
        } finally {
            foreach ($years as $row) {
                $this->rows('UPDATE grupper SET box1=$1,box2=$2,box3=$3,box4=$4 WHERE id=$5',
                    [$row['box1'],$row['box2'],$row['box3'],$row['box4'],$row['id']]);
            }
        }
    }

    public function testPostAndPatchKeepLegacyShapeAndWriteBatchesLogAndItemTotal(): void
    {
        $ledger=$this->rows('SELECT count(*) AS n FROM transaktioner');
        $open=$this->rows('SELECT count(*) AS n FROM openpost');
        $post=$this->create();
        $data=$post['json']['data'];
        self::assertSame(['id','inventory','productId','quantity','location','location2','location3','location4','location5','variantId'],array_keys($data));
        self::assertSame("Shelf's A",$data['location']);
        self::assertEquals(10,$data['quantity']);
        self::assertSame('true',$post['headers']['deprecation']);
        self::assertStringContainsString('stock/counts/index.php',$post['headers']['link']);
        $this->assertStock(10,1);
        $r=$this->request('PATCH',['action'=>'adjust_quantity','amount'=>'-3'],(int)$data['id']);
        self::assertSame(200,$r['status'],$r['body']);
        self::assertSame('Inventory quantity adjusted successfully',$r['json']['message']);
        self::assertSame(array_keys($data),array_keys($r['json']['data']));
        $this->assertStock(7,2);
        self::assertEquals([[10,7,4,2,0]],array_map('array_values',$this->rows('SELECT antal,rest,pris,lager,variant_id FROM batch_kob WHERE vare_id=$1',[$this->item])));
        self::assertEquals([[3,4,2,0]],array_map('array_values',$this->rows('SELECT antal,pris,lager,variant_id FROM batch_salg WHERE vare_id=$1',[$this->item])));
        self::assertSame(['REST API','REST API'],array_column($this->rows('SELECT reason FROM stocklog WHERE item_id=$1 ORDER BY id',[$this->item]),'reason'));
        self::assertEquals([10,-3],array_column($this->rows('SELECT correction FROM stocklog WHERE item_id=$1 ORDER BY id',[$this->item]),'correction'));
        self::assertSame($ledger,$this->rows('SELECT count(*) AS n FROM transaktioner'));
        self::assertSame($open,$this->rows('SELECT count(*) AS n FROM openpost'));
    }

    public function testPutAndSetQuantityAndLocationOnlyKeepTheirResponseShape(): void
    {
        $id=(int)$this->create()['json']['data']['id'];
        $put=$this->request('PUT',['quantity'=>'12','location2'=>"B's shelf"],$id);
        self::assertSame(200,$put['status'],$put['body']);
        $this->assertStock(12,2);
        $set=$this->request('PATCH',['action'=>'set_quantity','quantity'=>'7'],$id);
        self::assertSame(200,$set['status'],$set['body']);
        $this->assertStock(7,3);
        $batches=$this->rows('SELECT * FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$this->item]);
        $sales=$this->rows('SELECT * FROM batch_salg WHERE vare_id=$1 ORDER BY id',[$this->item]);
        $location=$this->request('PUT',['location'=>"C's shelf",'location5'=>'Top'],$id);
        self::assertSame(200,$location['status'],$location['body']);
        self::assertSame("C's shelf",$location['json']['data']['location']);
        self::assertSame("B's shelf",$location['json']['data']['location2']);
        self::assertSame('Top',$location['json']['data']['location5']);
        self::assertArrayNotHasKey('deprecation',$location['headers']);
        $this->assertStock(7,3);
        self::assertSame($batches,$this->rows('SELECT * FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$this->item]));
        self::assertSame($sales,$this->rows('SELECT * FROM batch_salg WHERE vare_id=$1 ORDER BY id',[$this->item]));
    }

    public function testVariantIdIsForwardedForCreationPatchAndPut(): void
    {
        $variant=(int)$this->rows("INSERT INTO variant_varer(vare_id,variant_stregkode,variant_beholdning) VALUES($1,$2,0) RETURNING id",[$this->item,'EAN'.$this->item])[0]['id'];
        $post=$this->create('5',$variant);
        $id=(int)$post['json']['data']['id'];
        self::assertSame($variant,$post['json']['data']['variantId']);
        $r=$this->request('PATCH',['action'=>'adjust_quantity','amount'=>'-2','variantId'=>$variant],$id);
        self::assertSame(200,$r['status'],$r['body']);
        $this->assertStock(3,2,$variant);
        $put=$this->request('PUT',['quantity'=>'4','variantId'=>$variant],$id);
        self::assertSame(200,$put['status'],$put['body']);
        $this->assertStock(4,3,$variant);
        self::assertEquals(4,$this->rows('SELECT variant_beholdning FROM variant_varer WHERE id=$1',[$variant])[0]['variant_beholdning']);
        self::assertEquals([$variant],array_unique(array_column($this->rows('SELECT variant_id FROM batch_kob WHERE vare_id=$1',[$this->item]),'variant_id')));
        self::assertEquals([$variant],array_column($this->rows('SELECT variant_id FROM batch_salg WHERE vare_id=$1',[$this->item]),'variant_id'));
    }

    public function testDuplicateAndInvalidWritesHaveNoPartialEffect(): void
    {
        $post=$this->create();
        $id=(int)$post['json']['data']['id'];
        $duplicate=$this->request('POST',['inventory'=>2,'productId'=>$this->item,'quantity'=>50]);
        self::assertSame(400,$duplicate['status']);
        $invalid=$this->request('PATCH',['action'=>'adjust_quantity','amount'=>"1 OR 1=1"],$id);
        self::assertSame(422,$invalid['status']);
        self::assertSame('validation_failed',$invalid['json']['data']['error']);
        $wrongVariant=$this->request('PATCH',['action'=>'set_quantity','quantity'=>'4','variantId'=>2147483647],$id);
        self::assertSame(422,$wrongVariant['status']);
        self::assertSame('variant_invalid',$wrongVariant['json']['data']['details']['reason']);
        $relabel=$this->request('PUT',['inventory'=>1],$id);
        self::assertSame(422,$relabel['status']);
        self::assertSame('inventory_target_changed',$relabel['json']['data']['details']['reason']);
        $this->assertStock(10,1);
        self::assertCount(1,$this->rows('SELECT * FROM batch_kob WHERE vare_id=$1',[$this->item]));
        self::assertSame([],$this->rows('SELECT * FROM batch_salg WHERE vare_id=$1',[$this->item]));
        $missing=$this->request('PATCH',['action'=>'set_quantity','quantity'=>'4'],2147483647);
        self::assertSame(404,$missing['status']);
    }

    private function legacyStatus(int $warehouse = 2): int
    {
        $this->rows('UPDATE varer SET beholdning=10 WHERE id=$1', [$this->item]);
        $id = (int)$this->rows("INSERT INTO lagerstatus(vare_id,lager,beholdning,lok1) VALUES($1,$2,10,'Original') RETURNING id", [$this->item,$warehouse])[0]['id'];
        $this->rows("INSERT INTO batch_kob(vare_id,lager,antal,rest,pris,kobsdate,fakturadate,linje_id,ordre_id)
            VALUES($1,$2,10,10,4,CURRENT_DATE,CURRENT_DATE,0,0)", [$this->item,$warehouse]);
        return $id;
    }

    public function testLegacyNullVariantQuantityKeepsItsIdAndConsumesItsBatch(): void
    {
        $id = $this->legacyStatus();
        $r = $this->request('PUT', ['quantity'=>'7','location2'=>"Legacy's shelf"], $id);
        self::assertSame(200, $r['status'], $r['body']);
        self::assertSame($id, $r['json']['data']['id']);
        self::assertSame(0, $r['json']['data']['variantId']);
        self::assertEquals(7, $r['json']['data']['quantity']);
        self::assertCount(1, $this->rows('SELECT id FROM lagerstatus WHERE vare_id=$1', [$this->item]));
        self::assertEquals(7, $this->rows('SELECT rest FROM batch_kob WHERE vare_id=$1', [$this->item])[0]['rest']);
        self::assertEquals([-3], array_column($this->rows('SELECT correction FROM stocklog WHERE item_id=$1', [$this->item]), 'correction'));
        $sales = $this->rows('SELECT batch_kob_id,antal FROM batch_salg WHERE vare_id=$1', [$this->item]);
        self::assertCount(1, $sales);
        self::assertGreaterThan(0, (int)$sales[0]['batch_kob_id']);
        self::assertEquals(3, $sales[0]['antal']);
        $patch = $this->request('PATCH', ['action'=>'adjust_quantity','amount'=>'-1'], $id);
        self::assertSame(200, $patch['status'], $patch['body']);
        self::assertSame($id, $patch['json']['data']['id']);
        $this->assertStock(6, 2);
    }

    public function testVariantStockInAnotherWarehouseIsIncludedInTheAggregate(): void
    {
        $variant = (int)$this->rows("INSERT INTO variant_varer(vare_id,variant_stregkode,variant_beholdning) VALUES($1,$2,0) RETURNING id", [$this->item,'EAN'.$this->item])[0]['id'];
        $post = $this->create('5', $variant);
        $id = (int)$post['json']['data']['id'];
        $second = $this->request('POST', ['inventory'=>1,'productId'=>$this->item,'quantity'=>'3','variantId'=>$variant]);
        self::assertSame(201, $second['status'], $second['body']);
        self::assertEquals(8, $this->rows('SELECT variant_beholdning FROM variant_varer WHERE id=$1', [$variant])[0]['variant_beholdning']);
        $changed = $this->request('PUT', ['quantity'=>'4'], $id);
        self::assertSame(200, $changed['status'], $changed['body']);
        self::assertEquals(7, $this->rows('SELECT variant_beholdning FROM variant_varer WHERE id=$1', [$variant])[0]['variant_beholdning']);
        self::assertEquals(7, $this->rows('SELECT beholdning FROM varer WHERE id=$1', [$this->item])[0]['beholdning']);
        self::assertEquals([[1,3],[2,4]], array_map('array_values', $this->rows('SELECT lager,beholdning FROM lagerstatus WHERE vare_id=$1 ORDER BY lager', [$this->item])));
        self::assertEquals([[1,3],[2,4]], array_map('array_values', $this->rows('SELECT lager,SUM(rest) FROM batch_kob WHERE vare_id=$1 GROUP BY lager ORDER BY lager', [$this->item])));
    }

    public function testLegacyNullVariantLocationOnlyPreservesTheExistingRow(): void
    {
        $id = $this->legacyStatus();
        $before = $this->rows('SELECT * FROM batch_kob WHERE vare_id=$1', [$this->item]);
        $r = $this->request('PUT', ['location'=>"Legacy's shelf",'location5'=>'Top'], $id);
        self::assertSame(200, $r['status'], $r['body']);
        self::assertSame($id, $r['json']['data']['id']);
        self::assertSame("Legacy's shelf", $r['json']['data']['location']);
        $row = $this->rows('SELECT * FROM lagerstatus WHERE id=$1', [$id])[0];
        self::assertSame("Legacy's shelf", $row['lok1']);
        self::assertSame('Top', $row['lok5']);
        self::assertNull($row['variant_id']);
        self::assertEquals(10, $row['beholdning']);
        self::assertSame($before, $this->rows('SELECT * FROM batch_kob WHERE vare_id=$1', [$this->item]));
        self::assertSame([], $this->rows('SELECT * FROM stocklog WHERE item_id=$1', [$this->item]));
    }

    public function testQuantityConsolidationKeepsTheRequestedAliasRowId(): void
    {
        $first = $this->legacyStatus(0);
        $id = (int)$this->rows("INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning,lok1) VALUES($1,0,1,2,'Requested') RETURNING id", [$this->item])[0]['id'];
        $this->rows('UPDATE varer SET beholdning=12 WHERE id=$1', [$this->item]);
        $r = $this->request('PUT', ['quantity'=>'9'], $id);
        self::assertSame(200, $r['status'], $r['body']);
        self::assertSame($id, $r['json']['data']['id']);
        self::assertSame('Requested', $r['json']['data']['location']);
        self::assertEquals(9, $r['json']['data']['quantity']);
        self::assertSame([], $this->rows('SELECT id FROM lagerstatus WHERE id=$1', [$first]));
        self::assertEquals([-3], array_column($this->rows('SELECT correction FROM stocklog WHERE item_id=$1', [$this->item]), 'correction'));
    }

    public function testLocationOnlyPutWorksWhenNoFiscalYearCoversToday(): void
    {
        $id = (int)$this->create()['json']['data']['id'];
        $years = $this->rows("SELECT id,box1,box2,box3,box4 FROM grupper WHERE art='RA'");
        $batches = $this->rows('SELECT * FROM batch_kob WHERE vare_id=$1', [$this->item]);
        try {
            $this->rows("UPDATE grupper SET box1='1',box2='2000',box3='12',box4='2000' WHERE art='RA'");
            $r = $this->request('PUT', ['location'=>"Last year's shelf",'location4'=>'Fourth'], $id);
            self::assertSame(200, $r['status'], $r['body']);
            self::assertSame("Last year's shelf", $r['json']['data']['location']);
            self::assertSame('Fourth', $r['json']['data']['location4']);
            self::assertArrayNotHasKey('deprecation', $r['headers']);
            $this->assertStock(10, 1);
            self::assertSame($batches, $this->rows('SELECT * FROM batch_kob WHERE vare_id=$1', [$this->item]));
        } finally {
            foreach ($years as $year) {
                $this->rows('UPDATE grupper SET box1=$1,box2=$2,box3=$3,box4=$4 WHERE id=$5',
                    [$year['box1'],$year['box2'],$year['box3'],$year['box4'],$year['id']]);
            }
        }
    }
}
