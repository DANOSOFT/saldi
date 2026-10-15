<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/lager/StockAdjustCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Cover ordinary-save rollback across two warehouses.
// 20261007 CDX/LH Assert the restored tenant SQL trail on the web write path.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../support/StockFixture.php';

require_once __DIR__ . '/../support/ModificationTrail.php';

final class StockAdjustCharacterizationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        StockFixture::start();
    }

    public static function tearDownAfterClass(): void
    {
        StockFixture::stop();
    }

    /** Replay the product form with its own GET defaults for ordinary saves. */
    private function page(int $id, array $post, bool $plain = false, bool $expectFailure = false): array
    {
        if ($plain) {
            $get = CharacterizationEnv::runChild(__DIR__ . '/../support/run_varekort_stock.php',
                [json_encode(['id'=>$id,'post'=>[]], JSON_THROW_ON_ERROR), StockFixture::$sessionId]);
            self::assertSame(0, $get['exit'], $get['stderr']);
            $dom = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML($get['stdout']);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $parts = [];
            foreach ($dom->getElementsByTagName('input') as $input) {
                $type = $input->getAttribute('type');
                if (in_array($type, ['submit','button','file'], true)
                    || (in_array($type, ['checkbox','radio'], true) && !$input->hasAttribute('checked'))) {
                    continue;
                }
                $parts[] = rawurlencode($input->getAttribute('name')) . '=' . rawurlencode($input->getAttribute('value'));
            }
            foreach ($dom->getElementsByTagName('select') as $select) {
                foreach ($select->getElementsByTagName('option') as $option) {
                    if ($option->hasAttribute('selected')) {
                        $parts[] = rawurlencode($select->getAttribute('name')) . '=' . rawurlencode($option->getAttribute('value'));
                        break;
                    }
                }
            }
            parse_str(implode('&', $parts), $defaults);
            $post = array_replace($defaults, $post);
        }
        file_put_contents(StockFixture::$log, '');
        $child = CharacterizationEnv::runChild(__DIR__ . '/../support/run_varekort_stock.php',
            [json_encode(['id'=>$id,'post'=>$post], JSON_THROW_ON_ERROR), StockFixture::$sessionId]);
        if ($expectFailure) {
            self::assertStringContainsString('stock_test_second_warehouse', $child['stdout'] . $child['stderr']);
            return $child;
        }
        self::assertSame(0, $child['exit'], $child['stdout'] . $child['stderr']);
        self::assertStringNotContainsString('Fatal error', $child['stdout']);
        self::assertStringNotContainsString('Stock callback', $child['stdout']);
        return $child;
    }

    private function accept(int $id, array $before, array $after): void
    {
        StockFixture::sql("DELETE FROM settings WHERE var_name='confirmStockChange'");
        StockFixture::sql("INSERT INTO settings(var_name,var_grp,var_value) VALUES('confirmStockChange','items','on')");
        $trail = ModificationTrail::read(CharacterizationEnv::testDb());
        $this->page($id, ['acceptStockChange'=>'1','initials'=>'LH','reason'=>"Count's reason",
            'beholdning'=>array_sum($before),'ny_beholdning'=>array_sum($after),'lagerbeh'=>$before,'ny_lagerbeh'=>$after]);
        ModificationTrail::assertWrite(CharacterizationEnv::testDb(), $trail, 'insert into stocklog\\b');
        $log = StockFixture::sql('SELECT item_id,username,initials,reason,correction FROM stocklog WHERE item_id=$1', [$id]);
        self::assertCount(1, $log);
        self::assertSame([(string)$id,'chartest','LH',"Count's reason"], array_values(array_slice($log[0],0,4)));
        self::assertEquals(array_sum($after)-array_sum($before), $log[0]['correction']);
    }

    private function quantities(int $id, array $expected, int $variant = 0, bool $plain = false): void
    {
        $rows = StockFixture::sql('SELECT lager,beholdning,variant_id FROM lagerstatus WHERE vare_id=$1 ORDER BY lager', [$id]);
        self::assertCount(count($expected), $rows);
        foreach ($rows as $row) {
            self::assertEquals($expected[(int)$row['lager']], $row['beholdning']);
            self::assertSame((string)$variant, $row['variant_id']);
        }
        self::assertEquals(array_sum($expected), StockFixture::one('SELECT beholdning FROM varer WHERE id=$1',[$id])['beholdning']);
        $calls = array_values(array_filter(StockFixture::calls(), static fn(array $call): bool => isset($call['update_stock'])));
        self::assertNotEmpty($calls);
        foreach ($calls as $call) {
            $warehouse = (int)$call['stockno'];
            if ($variant) {
                self::assertEquals(['update_stock'=>(string)$variant,'stock'=>number_format($expected[$warehouse],3,'.',''),
                    'stockno'=>(string)$warehouse,'stockvalue'=>''], $call);
            } else {
                // Sync runs after the complete accepted change: totalStock includes every warehouse.
                self::assertEquals(['update_stock'=>(string)$id,'stock'=>number_format($expected[$warehouse],3,'.',''),
                    'totalStock'=>number_format(array_sum($expected),3,'.',''),'stockno'=>(string)$warehouse,'costPrice'=>'4.000',
                    'salesPrice'=>'8.000','discountType'=>$plain ? '' : 'percent','discount'=>$plain ? '' : '0','itemNo'=>'ST-'.$id,
                    'itemNoAlias'=>'','retailPrice'=>'8.000','webFragt'=>'0','barcode'=>''], $call);
            }
        }
    }

    public function testIncreaseOnAbsentWarehouseWithConfirmationOff(): void
    {
        $id = 987001;
        StockFixture::item($id);
        StockFixture::sql("DELETE FROM settings WHERE var_name='confirmStockChange'");
        $this->page($id, ['id'=>$id,'saveItem'=>'Gem','ny_lagerbeh'=>[1=>0,2=>3],'lagerbeh'=>[1=>0,2=>0],
            'kostpris'=>[0=>'4'],'gl_kostpris'=>[0=>'4'],'beskrivelse0'=>'Stock fixture','gruppe'=>98760], true);
        $this->quantities($id, [2=>3], 0, true);
        self::assertSame([], StockFixture::sql('SELECT * FROM stocklog WHERE item_id=$1',[$id]));
        $batch = StockFixture::one('SELECT antal,rest,pris,lager,variant_id FROM batch_kob WHERE vare_id=$1',[$id]);
        self::assertEquals([3,3,4,2,0],array_values($batch));
        self::assertSame([],StockFixture::sql('SELECT * FROM batch_salg WHERE vare_id=$1',[$id]));
    }

    public function testDecreaseConsumesFefoRatherThanPurchaseDate(): void
    {
        $id=987002;
        StockFixture::item($id,5);
        StockFixture::stock($id,2,'5');
        $late=StockFixture::batch($id,2,'3','2026-12-31');
        $early=StockFixture::batch($id,2,'2','2026-10-01',0,'2026-02-01');
        $this->accept($id,[2=>5],[2=>0]);
        $this->quantities($id,[2=>0]);
        $rows=StockFixture::sql('SELECT batch_kob_id,antal,pris,lager,variant_id FROM batch_salg WHERE vare_id=$1 ORDER BY id',[$id]);
        self::assertEquals([[$early,2,4,2,0],[$late,3,4,2,0]],array_map('array_values',$rows));
        self::assertEquals([0,0],array_column(StockFixture::sql('SELECT rest FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$id]),'rest'));
    }

    public function testDecreaseBeyondBackedRest(): void
    {
        $id=987003;
        StockFixture::item($id,5);
        StockFixture::stock($id,2,'5');
        $a=StockFixture::batch($id,2,'2','2026-10-01');
        $b=StockFixture::batch($id,2,'3','2026-12-01');
        $this->accept($id,[2=>5],[2=>-2]);
        $this->quantities($id,[2=>-2]);
        self::assertEquals([[$a,2],[$b,3],[0,2]],array_map('array_values',StockFixture::sql('SELECT batch_kob_id,antal FROM batch_salg WHERE vare_id=$1 ORDER BY id',[$id])));
        self::assertEquals([0,0],array_column(StockFixture::sql('SELECT rest FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$id]),'rest'));
    }

    public function testPartialDecreaseConsumesRest(): void
    {
        $id=987004;
        StockFixture::item($id,10);
        StockFixture::stock($id,2,'10');
        $batch=StockFixture::batch($id,2,'10','2026-12-01');
        $this->accept($id,[2=>10],[2=>7]);
        $this->quantities($id,[2=>7]);
        self::assertEquals(7,StockFixture::one('SELECT rest FROM batch_kob WHERE id=$1',[$batch])['rest']);
        self::assertEquals([[$batch,3,4,2,0]],array_map('array_values',StockFixture::sql('SELECT batch_kob_id,antal,pris,lager,variant_id FROM batch_salg WHERE vare_id=$1',[$id])));
    }

    public function testTwoWarehousesInOneAcceptedSave(): void
    {
        $id=987005;
        StockFixture::item($id,2);
        StockFixture::stock($id,1,'2');
        StockFixture::batch($id,1,'2','2026-12-01');
        $this->accept($id,[1=>2,2=>0],[1=>5,2=>3]);
        $this->quantities($id,[1=>5,2=>3]);
        self::assertEquals([[1,2,2],[1,3,3],[2,3,3]],array_map('array_values',StockFixture::sql('SELECT lager,antal,rest FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$id])));
    }

    public function testVariantSaveUsesVariantRowsAndBatches(): void
    {
        $id=987006;
        StockFixture::item($id,10);
        StockFixture::sql("DELETE FROM settings WHERE var_name='confirmStockChange'");
        StockFixture::sql("INSERT INTO variant_varer(id,vare_id,variant_id,variant_stregkode,variant_beholdning) VALUES(987006,$1,1,'EAN987006',10)",[$id]);
        StockFixture::stock($id,2,'10',987006);
        StockFixture::batch($id,2,'10','2026-12-01',987006);
        $this->page($id,['id'=>$id,'saveItem'=>'Gem','variant_vare_id'=>[987006],'variant_vare_stregkode'=>['EAN987006'],
            'variant_varer_beholdning'=>[[2=>'12']],'kostpris'=>[0=>'4'],'gl_kostpris'=>[0=>'4'],'gruppe'=>98760,'beskrivelse0'=>'Stock fixture'],true);
        $this->quantities($id,[2=>12],987006);
        self::assertEquals(12,StockFixture::one('SELECT variant_beholdning FROM variant_varer WHERE id=987006')['variant_beholdning']);
        self::assertEquals([[987006,10,10],[987006,2,2]],array_map('array_values',StockFixture::sql('SELECT variant_id,antal,rest FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$id])));
    }

    public function testSecondWarehouseFailureRollsBackTheWholeOrdinarySave(): void
    {
        $id = 987008;
        StockFixture::item($id, 2);
        StockFixture::stock($id, 1, '2');
        StockFixture::batch($id, 1, '2', '2026-12-01');
        StockFixture::sql("ALTER TABLE batch_kob ADD CONSTRAINT stock_test_second_warehouse CHECK (vare_id<>987008 OR lager<>2)");
        $before = StockFixture::one('SELECT * FROM varer WHERE id=$1', [$id]);
        $stock = StockFixture::sql('SELECT * FROM lagerstatus WHERE vare_id=$1 ORDER BY id', [$id]);
        $batches = StockFixture::sql('SELECT * FROM batch_kob WHERE vare_id=$1 ORDER BY id', [$id]);
        try {
            $this->page($id, ['id'=>$id,'saveItem'=>'Gem','ny_lagerbeh'=>[1=>5,2=>3],'lagerbeh'=>[1=>2,2=>0],
                'salgspris'=>'9','kostpris'=>[0=>'4'],'gl_kostpris'=>[0=>'4'],'beskrivelse0'=>'Stock fixture','gruppe'=>98760], true, true);
            self::assertSame($before, StockFixture::one('SELECT * FROM varer WHERE id=$1', [$id]));
            self::assertSame($stock, StockFixture::sql('SELECT * FROM lagerstatus WHERE vare_id=$1 ORDER BY id', [$id]));
            self::assertSame($batches, StockFixture::sql('SELECT * FROM batch_kob WHERE vare_id=$1 ORDER BY id', [$id]));
            self::assertSame([], StockFixture::sql('SELECT * FROM batch_salg WHERE vare_id=$1', [$id]));
            self::assertSame([], StockFixture::calls());
        } finally {
            StockFixture::sql('ALTER TABLE batch_kob DROP CONSTRAINT stock_test_second_warehouse');
        }
    }

    public function testVariantSaveTotalsBothOccupiedWarehouses(): void
    {
        $id = 987009;
        StockFixture::item($id, 8);
        StockFixture::sql("INSERT INTO variant_varer(id,vare_id,variant_id,variant_stregkode,variant_beholdning) VALUES(987009,$1,1,'EAN987009',8)", [$id]);
        StockFixture::stock($id, 1, '3', $id);
        StockFixture::stock($id, 2, '5', $id);
        StockFixture::batch($id, 1, '3', '2026-12-01', $id);
        StockFixture::batch($id, 2, '5', '2026-12-01', $id);
        $this->page($id, ['id'=>$id,'saveItem'=>'Gem','variant_vare_id'=>[$id],'variant_vare_stregkode'=>['EAN987009'],
            'variant_varer_beholdning'=>[[1=>'2',2=>'4']],'kostpris'=>[0=>'4'],'gl_kostpris'=>[0=>'4'],'gruppe'=>98760,'beskrivelse0'=>'Stock fixture'], true);
        self::assertEquals([[1,2,$id],[2,4,$id]], array_map('array_values', StockFixture::sql('SELECT lager,beholdning,variant_id FROM lagerstatus WHERE vare_id=$1 ORDER BY lager', [$id])));
        self::assertEquals(6, StockFixture::one('SELECT beholdning FROM varer WHERE id=$1', [$id])['beholdning']);
        self::assertEquals(6, StockFixture::one('SELECT variant_beholdning FROM variant_varer WHERE id=$1', [$id])['variant_beholdning']);
        self::assertEquals([[1,2],[2,4]], array_map('array_values', StockFixture::sql('SELECT lager,rest FROM batch_kob WHERE vare_id=$1 ORDER BY lager', [$id])));
        $calls = array_values(array_filter(StockFixture::calls(), static fn(array $call): bool => isset($call['update_stock'])));
        self::assertEquals(['1','2'], array_column($calls, 'stockno'));
        self::assertEquals([(string)$id,(string)$id], array_column($calls, 'update_stock'));
        // Legacy sync_shop_vare reads the first variant row regardless of stockno; its payload is outside B1a ownership.
    }

    public function testAssembledItemAcceptedPathStillAdjustsParent(): void
    {
        $id=987007;
        StockFixture::item($id,0,true);
        $this->accept($id,[2=>0],[2=>2]);
        $this->quantities($id,[2=>2]);
        self::assertEquals([[2,2]],array_map('array_values',StockFixture::sql('SELECT antal,rest FROM batch_kob WHERE vare_id=$1',[$id])));
    }
}
