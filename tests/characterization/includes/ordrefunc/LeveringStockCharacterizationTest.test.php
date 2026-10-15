<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/includes/ordrefunc/LeveringStockCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Cover zero handling for NULL item and warehouse stock, including DisItemIfNeg.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../support/StockFixture.php';
require_once __DIR__ . '/../../support/LegacyStockFixture.php';

final class LeveringStockCharacterizationTest extends TestCase
{
    private static int $dbId;

    public static function setUpBeforeClass(): void
    {
        if (($reason=CharacterizationEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        StockFixture::start();
        // Disable even the local shop: these tests pin database stock effects only.
        StockFixture::sql("DELETE FROM grupper WHERE art='API'");
        $master=CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        self::$dbId=(int)CharacterizationEnv::one($master,'SELECT id FROM regnskab WHERE db=$1',[CharacterizationEnv::testDb()])['id'];
        pg_close($master);
    }

    public static function tearDownAfterClass(): void
    {
        StockFixture::stop();
    }

    /** @return array{item:int,order:int,line:int,variant:int,batches:list<int>} */
    private function seed(int $quantity=10,bool $status=true,bool $variant=false,bool $batch=false,string $art='DO'): array
    {
        return LegacyStockFixture::seed([StockFixture::class,'sql'],$quantity,$status,$variant,$batch,2,$art);
    }

    private function deliver(array $fixture,bool $transaction=true): void
    {
        $r=CharacterizationEnv::runChild(__DIR__.'/../../support/run_legacy_stock.php',[
            json_encode(['db'=>CharacterizationEnv::testDb(),'db_id'=>self::$dbId,'line'=>$fixture['line'],'caller_transaction'=>$transaction])]);
        self::assertSame(0,$r['exit'],$r['stderr'].$r['stdout']);
        $result=json_decode(trim($r['stdout']),true);
        self::assertSame('OK',$result['result'],$r['stderr'].$r['stdout']);
        self::assertSame(PGSQL_TRANSACTION_IDLE,$result['transaction']);
    }

    private function assertEffects(array $f,int $itemQty,int $warehouseQty,array $rests,bool $batch=false): void
    {
        self::assertEquals($itemQty,StockFixture::one('SELECT beholdning FROM varer WHERE id=$1',[$f['item']])['beholdning']);
        self::assertEquals([[$warehouseQty,2,$f['variant']]],array_map('array_values',StockFixture::sql('SELECT beholdning,lager,variant_id FROM lagerstatus WHERE vare_id=$1',[$f['item']])));
        self::assertEquals($rests,array_column(StockFixture::sql('SELECT rest FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$f['item']]),'rest'));
        $sales=StockFixture::sql('SELECT batch_kob_id,antal,lager,variant_id FROM batch_salg WHERE linje_id=$1 ORDER BY id',[$f['line']]);
        self::assertEquals([$f['batches'][0],$f['batches'][1]],array_column($sales,'batch_kob_id'));
        self::assertEquals([3,2],array_column($sales,'antal'));
        // Reserved-batch sales historically omit their warehouse (NULL).
        self::assertEquals($batch ? [null,null] : [2,2],array_column($sales,'lager'));
        self::assertEquals([$f['variant'],$f['variant']],array_column($sales,'variant_id'));
        $line=StockFixture::one('SELECT leveres,leveret,bogf_konto FROM ordrelinjer WHERE id=$1',[$f['line']]);
        self::assertEquals([0,5,2000],array_values($line));
        self::assertSame([],StockFixture::sql('SELECT id FROM stocklog WHERE item_id=$1',[$f['item']]));
        self::assertSame([],StockFixture::sql('SELECT linje_id FROM reservation WHERE linje_id=$1',[$f['line']]));
        self::assertSame([],StockFixture::sql('SELECT id FROM transaktioner WHERE ordre_id=$1',[$f['order']]));
        self::assertSame([],StockFixture::sql('SELECT id FROM openpost WHERE refnr=$1',[$f['order']]));
    }

    public function testWarehouseTwoConsumesTwoBatches(): void
    {
        $f=$this->seed();
        $this->deliver($f);
        $this->assertEffects($f,5,5,[0,5]);
    }

    public function testAbsentWarehouseRowKeepsLegacyNegativeWarehouseEffect(): void
    {
        $f=$this->seed(10,false);
        $this->deliver($f,false);
        $this->assertEffects($f,5,-5,[0,5]);
    }

    public function testNullItemQuantityIsDeliveredFromZero(): void
    {
        $f = $this->seed();
        StockFixture::sql('UPDATE varer SET beholdning=NULL WHERE id=$1', [$f['item']]);
        $this->deliver($f);
        $this->assertEffects($f, -5, 5, [0, 5]);
    }

    public function testNullExistingWarehouseQuantityIsDeliveredFromZero(): void
    {
        $f = $this->seed();
        StockFixture::sql('UPDATE lagerstatus SET beholdning=NULL WHERE vare_id=$1', [$f['item']]);
        $this->deliver($f, false);
        $this->assertEffects($f, 5, -5, [0, 5]);
    }

    public function testNullWarehouseQuantityKeepsBatchControlledDeliveryEffects(): void
    {
        $f = $this->seed(10, true, false, true);
        StockFixture::sql('UPDATE lagerstatus SET beholdning=NULL WHERE vare_id=$1', [$f['item']]);
        $this->deliver($f);
        $this->assertEffects($f, 5, -10, [0, 5], true);
    }

    public function testWarehouseSaleAndReturnTreatNullQuantityAsZero(): void
    {
        $f = $this->seed();
        $warehouseId = StockFixture::one('SELECT id FROM lagerstatus WHERE vare_id=$1', [$f['item']])['id'];
        foreach ([5, -2] as $quantity) {
            StockFixture::sql('UPDATE lagerstatus SET beholdning=NULL WHERE id=$1', [$warehouseId]);
            $r = CharacterizationEnv::runChild(__DIR__ . '/../../support/run_legacy_stock.php', [
                json_encode(['db' => CharacterizationEnv::testDb(), 'db_id' => self::$dbId, 'line' => $f['line'],
                    'mode' => 'warehouse', 'quantity' => $quantity, 'caller_transaction' => false])
            ]);
            self::assertSame(0, $r['exit'], $r['stderr'] . $r['stdout']);
            $result = json_decode(trim($r['stdout']), true);
            self::assertSame(PGSQL_TRANSACTION_IDLE, $result['transaction']);
            $rows = StockFixture::sql('SELECT id,beholdning FROM lagerstatus WHERE vare_id=$1', [$f['item']]);
            self::assertCount(1, $rows);
            self::assertSame($warehouseId, $rows[0]['id']);
            self::assertEquals(-$quantity, $rows[0]['beholdning']);
            self::assertEquals(10, StockFixture::one('SELECT beholdning FROM varer WHERE id=$1', [$f['item']])['beholdning']);
        }
    }

    public function testVariantLineUpdatesVariantQuantity(): void
    {
        $f=$this->seed(10,true,true);
        $this->deliver($f);
        $this->assertEffects($f,5,5,[0,5]);
        self::assertEquals(5,StockFixture::one('SELECT variant_beholdning FROM variant_varer WHERE id=$1',[$f['variant']])['variant_beholdning']);
    }

    public function testBatchControlledLinePinsExistingDoubleWarehouseDecrement(): void
    {
        $f=$this->seed(10,true,false,true);
        $this->deliver($f);
        $this->assertEffects($f,5,0,[0,5],true);
    }

    public function testDisItemIfNegClosesTheItemAfterDelivery(): void
    {
        $f=$this->seed();
        StockFixture::sql('UPDATE varer SET beholdning=4 WHERE id=$1',[$f['item']]);
        StockFixture::sql("INSERT INTO settings(var_name,var_grp,var_value,user_id) VALUES('DisItemIfNeg','varer','on',0)");
        try {
            $this->deliver($f);
            $this->assertEffects($f,-1,5,[0,5]);
            self::assertSame('1',StockFixture::one('SELECT lukket FROM varer WHERE id=$1',[$f['item']])['lukket']);
        } finally {
            StockFixture::sql("DELETE FROM settings WHERE var_name='DisItemIfNeg' AND var_grp='varer'");
        }
    }

    public function testDisItemIfNegClosesAnItemDeliveredFromNullQuantity(): void
    {
        $f = $this->seed();
        StockFixture::sql('UPDATE varer SET beholdning=NULL WHERE id=$1', [$f['item']]);
        StockFixture::sql("INSERT INTO settings(var_name,var_grp,var_value,user_id) VALUES('DisItemIfNeg','varer','on',0)");
        try {
            $this->deliver($f);
            self::assertSame('1', StockFixture::one('SELECT lukket FROM varer WHERE id=$1', [$f['item']])['lukket']);
            $this->assertEffects($f, -5, 5, [0, 5]);
        } finally {
            StockFixture::sql("DELETE FROM settings WHERE var_name='DisItemIfNeg' AND var_grp='varer'");
        }
    }

    public function testNegativeDeliveryRestoresTheLastConsumedBatch(): void
    {
        $f=$this->seed();
        $this->deliver($f);
        $r=CharacterizationEnv::runChild(__DIR__.'/../../support/run_legacy_stock.php',[
            json_encode(['db'=>CharacterizationEnv::testDb(),'db_id'=>self::$dbId,'line'=>$f['line'],
                'mode'=>'line','quantity'=>-2,'caller_transaction'=>true])]);
        self::assertSame(0,$r['exit'],$r['stderr'].$r['stdout']);
        self::assertEquals(7,StockFixture::one('SELECT beholdning FROM varer WHERE id=$1',[$f['item']])['beholdning']);
        self::assertEquals(7,StockFixture::one('SELECT beholdning FROM lagerstatus WHERE vare_id=$1',[$f['item']])['beholdning']);
        self::assertEquals([0,7],array_column(StockFixture::sql('SELECT rest FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$f['item']]),'rest'));
        self::assertEquals([3],array_column(StockFixture::sql('SELECT antal FROM batch_salg WHERE linje_id=$1',[$f['line']]),'antal'));
        self::assertEquals([3,0],array_values(StockFixture::one('SELECT leveret,leveres FROM ordrelinjer WHERE id=$1',[$f['line']])));
    }

    public function testPosDeliveryHasTheSameStockEffects(): void
    {
        $f=$this->seed(10,true,false,false,'PO');
        $this->deliver($f);
        $this->assertEffects($f,5,5,[0,5]);
    }
}
