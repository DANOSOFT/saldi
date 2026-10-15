<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/lager/StockEngineInvariantTest.test.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Cover legacy aliases and variant totals across occupied warehouses.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../support/StockFixture.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/lager/stockMovement.php';
require_once __DIR__ . '/../../../restapi/services/legacy/LegacyEngine.php';

final class StockEngineInvariantTest extends TestCase
{
    private static EngineContext $context;
    private static $uri;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        self::$uri = ifset($_SERVER,'REQUEST_URI');
        $_SERVER['REQUEST_URI'] = '/saldi/lager/varekort.php';
        StockFixture::start();
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $id = (int)CharacterizationEnv::one($master,'SELECT id FROM regnskab WHERE db=$1',[CharacterizationEnv::testDb()])['id'];
        pg_close($master);
        self::$context = new EngineContext(StockFixture::$tenant,CharacterizationEnv::testDb(),$id,'chartest',1,'2026-10-06');
        StockFixture::sql("UPDATE grupper SET box1='1',box2='2',box3='1000',box4='2000',box9='' WHERE art='VG' AND kodenr=98760");
    }

    public static function tearDownAfterClass(): void
    {
        StockFixture::stop();
        if (self::$uri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = self::$uri;
        }
    }

    private function engine(callable $fn): mixed
    {
        return LegacyEngine::run(self::$context,$fn);
    }

    /** @return array{quantity:float,rest:float,unbacked:float,batches:array<int,float>} */
    private function totals(int $id, int $lager, int $variant = 0): array
    {
        $params = [$id,$lager,$variant];
        $scope = $lager === 1 ? '(lager=$2 OR lager=0 OR lager IS NULL)' : 'lager=$2';
        $rows = StockFixture::sql("SELECT id,rest FROM batch_kob WHERE vare_id=$1 AND $scope AND COALESCE(variant_id,0)=$3 ORDER BY id",$params);
        return ['quantity'=>(float)StockFixture::one("SELECT COALESCE(SUM(beholdning),0) AS n FROM lagerstatus WHERE vare_id=$1 AND $scope AND COALESCE(variant_id,0)=$3",$params)['n'],
            'rest'=>array_sum(array_column($rows,'rest')),
            'unbacked'=>(float)StockFixture::one("SELECT COALESCE(SUM(antal),0) AS n FROM batch_salg WHERE vare_id=$1 AND $scope AND COALESCE(variant_id,0)=$3 AND batch_kob_id=0",$params)['n'],
            'batches'=>array_map('floatval',array_column($rows,'rest','id'))];
    }

    private function move(int $id, int $lager, string $quantity, int $variant = 0): array
    {
        $before = $this->totals($id,$lager,$variant);
        $result = $this->engine(static fn(): array => stock_set_quantity($id,$variant,$lager,$quantity,'4','2026-10-06',true));
        $after = $this->totals($id,$lager,$variant);
        self::assertEqualsWithDelta($after['quantity']-$before['quantity'],
            ($after['rest']-$before['rest'])-($after['unbacked']-$before['unbacked']),0.000001);
        self::assertEqualsWithDelta($after['quantity'],$after['rest']-$after['unbacked'],0.000001);
        self::assertEquals((float)$quantity,$after['quantity']);
        if ($after['quantity'] < $before['quantity']) {
            foreach ($before['batches'] as $batch=>$rest) {
                self::assertLessThanOrEqual($rest,$after['batches'][$batch]);
            }
        }
        $total = StockFixture::one('SELECT COALESCE(SUM(beholdning),0) AS n FROM lagerstatus WHERE vare_id=$1',[$id]);
        self::assertEquals($total['n'],StockFixture::one('SELECT beholdning FROM varer WHERE id=$1',[$id])['beholdning']);
        return $result;
    }

    public function testEveryMovementPreservesWarehouseBatchAndItemInvariants(): void
    {
        StockFixture::item(988001);
        foreach (['10','7','2','-2','4','2','2'] as $quantity) {
            $this->move(988001,2,$quantity);
        }
        $this->move(988001,1,'1.125');
        $this->move(988001,1,'0.125');
        self::assertSame([],StockFixture::sql('SELECT id FROM batch_kob WHERE vare_id=988001 AND rest<0'));
    }

    public function testThreeDecimalsSurviveLargeQuantityWritesAndTotals(): void
    {
        StockFixture::item(988009);
        $this->move(988009,2,'123456789012.345');
        $this->move(988009,2,'123456789012.344');
        $this->move(988009,1,'0.006');
        self::assertSame('123456789012.350',StockFixture::one('SELECT beholdning FROM varer WHERE id=988009')['beholdning']);
    }

    public function testFefoConsumesTheEarlierExpiryAndNeverRaisesSecondRest(): void
    {
        $id=988002;
        StockFixture::item($id,10);
        StockFixture::stock($id,2,'10');
        $late=StockFixture::batch($id,2,'7','2026-12-01',0,'2026-01-01');
        $early=StockFixture::batch($id,2,'3','2026-10-01',0,'2026-02-01');
        $this->move($id,2,'5');
        self::assertEquals([[$late,5],[$early,0]],array_map('array_values',StockFixture::sql('SELECT id,rest FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$id])));
        self::assertEquals([[$early,3],[$late,2]],array_map('array_values',StockFixture::sql('SELECT batch_kob_id,antal FROM batch_salg WHERE vare_id=$1 ORDER BY id',[$id])));
        $this->move($id,2,'-2');
        self::assertEquals(2,StockFixture::one('SELECT antal FROM batch_salg WHERE vare_id=$1 AND batch_kob_id=0',[$id])['antal']);
    }

    public function testVariantWithoutStatusRowUpdatesVariantAndItsOwnBatches(): void
    {
        $id=988003;
        StockFixture::item($id);
        StockFixture::sql("INSERT INTO variant_varer(id,vare_id,variant_id,variant_stregkode,variant_beholdning) VALUES(988003,$1,1,'EAN988003',0)",[$id]);
        $this->move($id,2,'5',988003);
        $this->move($id,2,'3',988003);
        $empty=$this->move($id,1,'0',988003);
        self::assertNull($empty['sync']);
        self::assertEquals(3,StockFixture::one('SELECT variant_beholdning FROM variant_varer WHERE id=988003')['variant_beholdning']);
        self::assertSame([],StockFixture::sql('SELECT id FROM batch_kob WHERE vare_id=$1 AND variant_id<>988003',[$id]));
    }

    public function testDeferredSyncWaitsForCommitAndDeduplicatesTargets(): void
    {
        StockFixture::item(988004);
        file_put_contents(StockFixture::$log,'');
        $this->engine(static function (): void {
            transaktion('begin');
            $first=stock_set_quantity(988004,0,2,'4','4','2026-10-06',true);
            $second=stock_set_quantity(988004,0,2,'6','4','2026-10-06',true);
            self::assertSame([],StockFixture::calls());
            transaktion('commit');
            $sync=stock_shop_sync([$first['sync'],$second['sync']]);
            self::assertSame([['vare_id'=>988004,'variant_id'=>0,'lager'=>2,'state'=>'done']],$sync);
        });
        $stocks=array_values(array_filter(StockFixture::calls(),static fn(array $call): bool => isset($call['update_stock'])));
        self::assertCount(1,$stocks);
        self::assertEquals(6,$stocks[0]['stock']);
        file_put_contents(StockFixture::$log,'');
        $this->engine(static function (): void {
            transaktion('begin');
            stock_set_quantity(988004,0,2,'7','4','2026-10-06',true);
            self::assertSame([],StockFixture::calls());
            transaktion('rollback');
        });
        self::assertEquals(6,$this->totals(988004,2)['quantity']);
        self::assertSame([],StockFixture::calls());
    }

    public function testVariantTotalsIncludeNonzeroStockInTwoWarehouses(): void
    {
        $id = 988010;
        StockFixture::item($id);
        StockFixture::sql("INSERT INTO variant_varer(id,vare_id,variant_stregkode,variant_beholdning) VALUES(988010,$1,'EAN988010',0)", [$id]);
        $this->move($id, 2, '5', $id);
        $this->move($id, 1, '3', $id);
        self::assertEquals(8, StockFixture::one('SELECT variant_beholdning FROM variant_varer WHERE id=$1', [$id])['variant_beholdning']);
        $this->move($id, 1, '2', $id);
        self::assertEquals(7, StockFixture::one('SELECT variant_beholdning FROM variant_varer WHERE id=$1', [$id])['variant_beholdning']);
        $this->move($id, 2, '4', $id);
        self::assertEquals(6, StockFixture::one('SELECT variant_beholdning FROM variant_varer WHERE id=$1', [$id])['variant_beholdning']);
    }

    public function testWarehouseOneSetterUsesTheSnapshotScopeAndConsumesLegacyBatches(): void
    {
        $id = 988011;
        StockFixture::item($id, 4);
        StockFixture::stock($id, 0, '2');
        StockFixture::stock($id, 1, '1');
        StockFixture::stock($id, 1, '1');
        StockFixture::sql('UPDATE lagerstatus SET lager=NULL,variant_id=NULL WHERE vare_id=$1 AND id=(SELECT MAX(id) FROM lagerstatus WHERE vare_id=$1)', [$id]);
        StockFixture::batch($id, 0, '2', '2026-12-01');
        $batch = StockFixture::batch($id, 1, '2', '2026-11-01');
        StockFixture::sql('UPDATE batch_kob SET lager=NULL,variant_id=NULL WHERE id=$1', [$batch]);
        self::assertSame('4', $this->engine(static fn(): array => stock_snapshot($id, 1))['quantity']);
        $movement = $this->engine(static fn(): array => stock_set_quantity($id, 0, 1, '6', '4', '2026-10-06', true));
        self::assertSame(['4','6','2'], [$movement['before'],$movement['after'],$movement['delta']]);
        self::assertSame('6', $this->engine(static fn(): array => stock_snapshot($id, 1))['quantity']);
        $this->move($id, 1, '1');
        self::assertSame('1', $this->engine(static fn(): array => stock_snapshot($id, 1))['quantity']);
        self::assertEquals([0,0,1], array_column(StockFixture::sql('SELECT rest FROM batch_kob WHERE vare_id=$1 ORDER BY id', [$id]), 'rest'));
        self::assertSame([], StockFixture::sql('SELECT id FROM batch_salg WHERE vare_id=$1 AND batch_kob_id=0', [$id]));
    }

    public function testLegacySixArgumentCallSynchronizesAfterItsOwnedCommit(): void
    {
        StockFixture::item(988005);
        file_put_contents(StockFixture::$log,'');
        $this->engine(static function (): void {
            lagerreguler(988005,'3','4',2,'2026-10-06',0);
            self::assertSame(PGSQL_TRANSACTION_IDLE,pg_transaction_status(StockFixture::$tenant));
        });
        self::assertNotEmpty(StockFixture::calls());
        self::assertEquals(3,$this->totals(988005,2)['quantity']);
    }

    public function testTokensChangeOnSaleReceiptAdjustmentAndStocklogOnlyButNeverReads(): void
    {
        $id=988006;
        StockFixture::item($id,10);
        StockFixture::stock($id,2,'10');
        StockFixture::batch($id,2,'10','2026-12-01');
        $token=$this->engine(static fn(): array => stock_snapshot($id,2));
        self::assertSame('10',$token['quantity']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D',$token['token']);
        self::assertSame($token,$this->engine(static fn(): array => stock_snapshot($id,2)));
        $order=(int)StockFixture::one("INSERT INTO ordrer(art,status,ordrenr,ordredate,levdate,fakturadate,afd,ref)
            VALUES('DO',1,988006,'2026-10-06','2026-10-06','2026-10-06',1,'') RETURNING id")['id'];
        StockFixture::sql("INSERT INTO ordrelinjer(ordre_id,posnr,vare_id,varenr,antal,leveres,leveret,pris,kostpris,rabat,serienr,variant_id,lager,kred_linje_id,bogf_konto,folgevare,samlevare,fast_db)
            VALUES($1,1,$2,$3,2,2,0,8,4,0,'',0,2,0,0,0,0,0)",[$order,$id,'ST-'.$id]);
        $child=CharacterizationEnv::runChild(__DIR__ . '/../support/run_varekort_stock.php',
            [json_encode(['id'=>$order,'post'=>[],'operation'=>'sale'],JSON_THROW_ON_ERROR),StockFixture::$sessionId]);
        self::assertSame(0,$child['exit'],$child['stdout'].$child['stderr']);
        self::assertSame('OK',json_decode($child['stdout'],true,512,JSON_THROW_ON_ERROR)['result']);
        $after=$this->engine(static fn(): array => stock_snapshot($id,2));
        self::assertNotSame($token['token'],$after['token']);
        self::assertSame('8',$after['quantity']);
        $receipt=(int)StockFixture::one("INSERT INTO ordrer(art,status,ordrenr,ordredate,levdate,ref)
            VALUES('KO',1,988007,'2026-10-06','2026-10-06','chartest') RETURNING id")['id'];
        StockFixture::sql("INSERT INTO ordrelinjer(ordre_id,posnr,varenr,vare_id,antal,leveres,leveret,serienr,variant_id,lager,kred_linje_id)
            VALUES($1,1,$2,$3,1,1,0,'',0,2,0)",[$receipt,'ST-'.$id,$id]);
        $child=CharacterizationEnv::runChild(__DIR__ . '/../support/run_varekort_stock.php',
            [json_encode(['id'=>$receipt,'post'=>[],'operation'=>'receipt'],JSON_THROW_ON_ERROR),StockFixture::$sessionId]);
        self::assertSame(0,$child['exit'],$child['stdout'].$child['stderr']);
        $received=$this->engine(static fn(): array => stock_snapshot($id,2));
        self::assertNotSame($after['token'],$received['token']);
        self::assertSame('9',$received['quantity']);
        $this->move($id,2,'7');
        $adjusted=$this->engine(static fn(): array => stock_snapshot($id,2));
        self::assertNotSame($received['token'],$adjusted['token']);
        $this->engine(static function () use ($id): void {
            stock_item_section([$id],static fn(): int => stock_log_correction($id,'chartest-long-name','INITIALS-LONG',"Only log's reason",'0'));
        });
        $logged=$this->engine(static fn(): array => stock_snapshot($id,2));
        self::assertSame($adjusted['quantity'],$logged['quantity']);
        self::assertNotSame($adjusted['token'],$logged['token']);
        self::assertSame($logged,$this->engine(static fn(): array => stock_snapshot($id,2)));
        self::assertSame(['chartest-l','INITIALS-L'],array_values(StockFixture::one('SELECT username,initials FROM stocklog WHERE item_id=$1 ORDER BY id DESC LIMIT 1',[$id])));
    }

    public function testWarehouseOneTokenIncludesLegacyZeroAndNullBatches(): void
    {
        $id=988007;
        StockFixture::item($id);
        $before=$this->engine(static fn(): array => stock_snapshot($id,1));
        $other=$this->engine(static fn(): array => stock_snapshot($id,2));
        StockFixture::batch($id,0,'2','2026-12-01');
        $after=$this->engine(static fn(): array => stock_snapshot($id,1));
        self::assertNotSame($before['token'],$after['token']);
        self::assertSame($other,$this->engine(static fn(): array => stock_snapshot($id,2)));
        StockFixture::sql('UPDATE batch_kob SET lager=NULL WHERE vare_id=$1',[$id]);
        self::assertSame($after,$this->engine(static fn(): array => stock_snapshot($id,1)));
        $this->engine(static function () use ($id): void {
            transaktion('begin');
            self::assertSame('0',stock_snapshot($id,1,true)['quantity']);
            transaktion('rollback');
        });
    }

    public function testCountabilityUsesAllFiveReasons(): void
    {
        $id=988008;
        StockFixture::item($id);
        self::assertNull($this->engine(static fn(): ?string => stock_item_countable($id)));
        foreach (['serienr'=>'serial','samlevare'=>'assembled','lukket'=>'closed'] as $column=>$reason) {
            StockFixture::sql("UPDATE varer SET $column='on' WHERE id=$1",[$id]);
            self::assertSame($reason,$this->engine(static fn(): ?string => stock_item_countable($id)));
            StockFixture::sql("UPDATE varer SET $column='' WHERE id=$1",[$id]);
        }
        StockFixture::sql('UPDATE varer SET gruppe=0 WHERE id=$1',[$id]);
        self::assertSame('not_stock_item',$this->engine(static fn(): ?string => stock_item_countable($id)));
        StockFixture::sql("INSERT INTO variant_varer(vare_id,variant_stregkode) VALUES($1,'EAN988008')",[$id]);
        self::assertSame('variant',$this->engine(static fn(): ?string => stock_item_countable($id)));
        self::assertSame('not_stock_item',$this->engine(static fn(): ?string => stock_item_countable(99999999)));
    }
}
