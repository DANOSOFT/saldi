<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/LegacyStockConcurrencyTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Verify opposing order-line sequences acquire the complete item set in ascending order.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../characterization/support/LegacyStockFixture.php';
require_once __DIR__ . '/../../restapi/services/StockCountService.php';

/** Real PostgreSQL sessions exercise the same item locks used by the app and classic web writers. */
final class LegacyStockConcurrencyTest extends TestCase
{
    private static $tenant;
    private static EngineContext $context;
    private static string $session;
    private array $processes = [];
    private array $paths = [];
    private array $serverState = [];

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        RestApiEnv::loginData();
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        RestApiEnv::rows(self::$tenant,"DELETE FROM grupper WHERE art='API'");
        $user = RestApiEnv::rows(self::$tenant,'SELECT id FROM brugere WHERE brugernavn=$1',[RestApiEnv::user()])[0];
        self::$context = new EngineContext(self::$tenant,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),
            RestApiEnv::user(),(int)$user['id'],'2026-10-06');
        $year = self::$context->regnaar;
        RestApiEnv::rows(self::$tenant,"INSERT INTO grupper(art,kodenr,fiscal_year,beskrivelse)
            SELECT 'LG',2,$1,'Two' WHERE NOT EXISTS(SELECT 1 FROM grupper WHERE art='LG' AND kodenr=2)",[$year]);
        $dir = dirname(__DIR__,2).'/temp/'.RestApiEnv::testDb();
        if (!is_dir($dir)) {
            mkdir($dir,0770,true);
        }
        self::$session = bin2hex(random_bytes(16));
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows($master,'INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES($1,$2,$3,$4,$5,$6,$7,false,0)',[self::$session,RestApiEnv::user(),RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('9',50),$year,(string)time()]);
        pg_close($master);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            $master = RestApiEnv::connect(RestApiEnv::masterDb());
            RestApiEnv::rows($master,'DELETE FROM online WHERE session_id=$1',[self::$session]);
            pg_close($master);
            pg_close(self::$tenant);
            RestApiEnv::teardownTenant();
        }
    }

    protected function setUp(): void
    {
        $this->serverState = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/saldi/lager/varekort.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $_SERVER = $this->serverState;
        if (pg_transaction_status(self::$tenant) !== PGSQL_TRANSACTION_IDLE) {
            $this->rows('ROLLBACK');
        }
    }

    private function rows(string $sql,array $params=[]): array
    {
        return RestApiEnv::rows(self::$tenant,$sql,$params);
    }

    private function seed(int $quantity=10,bool $status=true): array
    {
        return LegacyStockFixture::seed(fn(string $sql,array $params=[]): array => $this->rows($sql,$params),$quantity,$status);
    }

    private function path(): string
    {
        $path = tempnam(sys_get_temp_dir(),'legacy-stock-race-');
        unlink($path);
        foreach (['','.ready','.release','.out','.err'] as $suffix) {
            $this->paths[] = $path.$suffix;
        }
        return $path;
    }

    /** @return array{process:resource,path:string} */
    private function launch(array $input,bool $sale=false): array
    {
        $path = $this->path();
        $input += ['db'=>RestApiEnv::testDb(),'db_id'=>self::$context->dbId,'user_id'=>self::$context->userId,
            'username'=>RestApiEnv::user(),'started'=>$path];
        $runner = $sale ? __DIR__.'/../characterization/support/run_legacy_stock.php' : __DIR__.'/support/run_legacy_stock_race.php';
        $process = proc_open([PHP_BINARY,$runner,json_encode($input,JSON_THROW_ON_ERROR)],
            [0=>['file','/dev/null','r'],1=>['file',$path.'.out','w'],2=>['file',$path.'.err','w']],$pipes);
        self::assertIsResource($process);
        $this->processes[] = $process;
        return ['process'=>$process,'path'=>$path];
    }

    private function until(callable $condition,string $message): void
    {
        $deadline = microtime(true)+10;
        do {
            if ($condition()) {
                return;
            }
            usleep(10000);
        } while (microtime(true)<$deadline);
        $errors = '';
        foreach ($this->paths as $path) {
            if (str_ends_with($path,'.err') && is_file($path)) {
                $errors .= file_get_contents($path);
            }
        }
        self::fail($message."\n".$errors);
    }

    private function ready(string $barrier): int
    {
        $this->until(static fn(): bool => is_file($barrier.'.ready'),'Worker did not reach barrier');
        return (int)file_get_contents($barrier.'.ready');
    }

    private function blocked(int $blocker,?int $waiter=null): int
    {
        $waiting = [];
        $this->until(function () use ($blocker,$waiter,&$waiting): bool {
            $waiting = $this->rows("SELECT pid FROM pg_stat_activity WHERE datname=$1 AND wait_event='advisory'
                AND $2::int=ANY(pg_blocking_pids(pid)) AND ($3::int IS NULL OR pid=$3)",
                [RestApiEnv::testDb(),$blocker,$waiter]);
            return count($waiting)===1;
        },'Expected worker to wait on the item advisory lock');
        return (int)$waiting[0]['pid'];
    }

    private function release(string $barrier): void
    {
        file_put_contents($barrier.'.release','go');
    }

    private function finish(array $child,bool $page=false): array
    {
        $status = [];
        $this->until(static function () use ($child,&$status): bool {
            $status = proc_get_status($child['process']);
            return !$status['running'];
        },'Worker did not finish');
        $output = file_get_contents($child['path'].'.out');
        $errors = file_get_contents($child['path'].'.err');
        proc_close($child['process']);
        self::assertSame(0,$status['exitcode'],$output."\n".$errors);
        self::assertStringNotContainsString('Fatal error',$errors,$errors);
        if ($page) {
            self::assertStringContainsString('Vareproduktion gennemført',$output,$output."\n".$errors);
            return [];
        }
        $result = json_decode($output,true);
        self::assertIsArray($result,$output."\n".$errors);
        if (isset($result['transaction'])) {
            self::assertSame(PGSQL_TRANSACTION_IDLE,$result['transaction']);
        }
        return $result;
    }

    private function request(int $item,string $quantity): array
    {
        $snapshot = LegacyEngine::run(self::$context,static fn(): array => stock_snapshot($item,2));
        return ['client_id'=>self::uuid(),'session_id'=>self::uuid(),'lager'=>2,'reason'=>'optaelling',
            'lines'=>[['line_ref'=>self::uuid(),'vare_id'=>$item,'counted_qty'=>$quantity,
                'expected_qty'=>$snapshot['quantity'],'expected_token'=>$snapshot['token']]]];
    }

    private static function uuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-a'.substr($hex,17,3).'-'.substr($hex,20,12);
    }

    private function assertStock(int $item,int $quantity,int $rowCount=1): void
    {
        self::assertEquals($quantity,$this->rows('SELECT beholdning FROM varer WHERE id=$1',[$item])[0]['beholdning']);
        self::assertEquals($quantity,$this->rows('SELECT SUM(beholdning) AS n FROM lagerstatus WHERE vare_id=$1',[$item])[0]['n']);
        self::assertCount($rowCount,$this->rows('SELECT id FROM lagerstatus WHERE vare_id=$1',[$item]));
    }

    public function testCountFirstPreservesSaleThatReadBeforeCountCommitted(): void
    {
        $this->countFirst($this->seed(), '13',8);
    }

    public function testCountAndSaleCreateOnlyOneAbsentWarehouseRow(): void
    {
        $this->countFirst($this->seed(0,false),'3',-2);
    }

    private function countFirst(array $seed,string $counted,int $final): void
    {
        $countBarrier = $this->path();
        $count = $this->launch(['mode'=>'count','request'=>$this->request($seed['item'],$counted),'barrier'=>$countBarrier]);
        $countPid = $this->ready($countBarrier);
        $saleBarrier = $this->path();
        $sale = $this->launch(['mode'=>'sale','line'=>$seed['line'],'barrier'=>$saleBarrier],true);
        $salePid = $this->ready($saleBarrier); // Has already read the old item total.
        $this->release($saleBarrier);
        self::assertSame($salePid,$this->blocked($countPid,$salePid));
        $this->release($countBarrier);
        self::assertSame(201,$this->finish($count)['status']);
        self::assertSame('OK',$this->finish($sale)['result']);
        $this->assertStock($seed['item'],$final);
        self::assertEquals($final,$this->rows('SELECT SUM(rest) AS n FROM batch_kob WHERE vare_id=$1',[$seed['item']])[0]['n']);
        self::assertEquals([[5]],array_map('array_values',$this->rows('SELECT SUM(antal) FROM batch_salg WHERE linje_id=$1',[$seed['line']])));
    }

    public function testSaleFirstMakesCountStaleAndExplicitSetRecordsTheSaleInBeforeQuantity(): void
    {
        $this->saleFirst($this->seed(),'13',5);
    }

    public function testSaleFirstOnAbsentWarehouseThenConfirmedCountKeepsOneRow(): void
    {
        $this->saleFirst($this->seed(0,false),'3',-5);
    }

    private function saleFirst(array $seed,string $counted,int $before): void
    {
        $request = $this->request($seed['item'],$counted);
        $saleBarrier = $this->path();
        $sale = $this->launch(['mode'=>'sale','line'=>$seed['line'],'caller_transaction'=>true,'after_barrier'=>$saleBarrier],true);
        $salePid = $this->ready($saleBarrier);
        $count = $this->launch(['mode'=>'count','request'=>$request]);
        $this->blocked($salePid);
        $this->release($saleBarrier);
        $result = $this->finish($sale);
        self::assertSame('OK',$result['result']);
        self::assertSame(PGSQL_TRANSACTION_INTRANS,$result['inside_transaction']);
        $conflict = $this->finish($count);
        self::assertSame(409,$conflict['status']);
        self::assertSame('count_stale',$conflict['details']['reason']);
        $this->assertStock($seed['item'],$before);
        self::assertSame([], $this->rows('SELECT id FROM mobile_stock_counts WHERE session_id=$1',[$request['session_id']]));
        $request['client_id'] = self::uuid();
        $request['lines'][0]['resolution'] = 'set';
        $response = (new StockCountService(self::$context,Subject::build('account',self::$context->userId)))
            ->submit($request,new EmployeeContext(self::$tenant,'account',self::$context->userId));
        self::assertSame(201,$response['status']);
        self::assertSame((string)$before,$response['body']['data']['lines'][0]['before']);
        self::assertSame((string)((int)$counted-$before),$response['body']['data']['lines'][0]['delta']);
        $this->assertStock($seed['item'],(int)$counted);
    }

    public function testSalesInTwoWarehousesKeepTheItemTotalEqualToWarehouseStock(): void
    {
        $seed = $this->seed();
        $this->rows('UPDATE varer SET beholdning=20 WHERE id=$1',[$seed['item']]);
        $this->rows('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES($1,0,1,10)',[$seed['item']]);
        $this->rows("INSERT INTO batch_kob(vare_id,variant_id,lager,antal,rest,pris,kobsdate,fakturadate,linje_id,ordre_id)
            VALUES($1,0,1,10,10,4,'2026-01-01','2026-01-01',0,0)",[$seed['item']]);
        $second = (int)$this->rows("INSERT INTO ordrelinjer(ordre_id,vare_id,posnr,antal,leveres,leveret,pris,kostpris,rabat,variant_id,lager,serienr,folgevare,kred_linje_id,bogf_konto,fast_db)
            SELECT ordre_id,vare_id,2,5,5,0,8,4,0,0,1,'',0,0,0,0 FROM ordrelinjer WHERE id=$1 RETURNING id",[$seed['line']])[0]['id'];
        $firstBarrier = $this->path();
        $first = $this->launch(['mode'=>'line','line'=>$seed['line'],'caller_transaction'=>true,'after_barrier'=>$firstBarrier],true);
        $firstPid = $this->ready($firstBarrier);
        $secondBarrier = $this->path();
        $other = $this->launch(['mode'=>'line','line'=>$second,'barrier'=>$secondBarrier],true);
        $otherPid = $this->ready($secondBarrier);
        $this->release($secondBarrier);
        $this->blocked($firstPid,$otherPid);
        $this->release($firstBarrier);
        self::assertNull($this->finish($first)['result']);
        self::assertNull($this->finish($other)['result']);
        $this->assertStock($seed['item'],10,2);
        self::assertEquals([[1,5],[2,5]],array_map('array_values',$this->rows('SELECT lager,beholdning FROM lagerstatus WHERE vare_id=$1 ORDER BY lager',[$seed['item']])));
        self::assertEquals(10,$this->rows('SELECT SUM(rest) AS n FROM batch_kob WHERE vare_id=$1',[$seed['item']])[0]['n']);
    }

    public function testOrdersWithOppositeLineOrderLockAllItemsBeforeTheFirstMovement(): void
    {
        $lower = $this->seed();
        $higher = $this->seed();
        $this->rows('UPDATE ordrelinjer SET posnr=2 WHERE id=$1',[$lower['line']]);
        $firstLine = (int)$this->rows('INSERT INTO ordrelinjer(ordre_id,vare_id,varenr,posnr,antal,leveres,leveret,pris,kostpris,rabat,variant_id,lager,serienr,folgevare,kred_linje_id,bogf_konto,fast_db)
            SELECT $1,vare_id,varenr,1,antal,leveres,leveret,pris,kostpris,rabat,variant_id,lager,serienr,folgevare,kred_linje_id,bogf_konto,fast_db
            FROM ordrelinjer WHERE id=$2 RETURNING id',[$lower['order'],$higher['line']])[0]['id'];
        $this->rows('UPDATE ordrelinjer SET posnr=2 WHERE id=$1',[$higher['line']]);
        $secondLine = (int)$this->rows('INSERT INTO ordrelinjer(ordre_id,vare_id,varenr,posnr,antal,leveres,leveret,pris,kostpris,rabat,variant_id,lager,serienr,folgevare,kred_linje_id,bogf_konto,fast_db)
            SELECT $1,vare_id,varenr,1,antal,leveres,leveret,pris,kostpris,rabat,variant_id,lager,serienr,folgevare,kred_linje_id,bogf_konto,fast_db
            FROM ordrelinjer WHERE id=$2 RETURNING id',[$higher['order'],$lower['line']])[0]['id'];
        $this->rows('BEGIN');
        LegacyEngine::run(self::$context,static fn() => stock_lock_item($lower['item']));
        $startBarrier = $this->path();
        $barrier = $this->path();
        $first = $this->launch(['mode'=>'sale','line'=>$firstLine,'caller_transaction'=>true,
            'barrier'=>$startBarrier,'after_barrier'=>$barrier],true);
        $firstPid = $this->ready($startBarrier);
        $this->release($startBarrier);
        $this->blocked(pg_get_pid(self::$tenant),$firstPid);
        // The first line names the higher item, but its lock must wait for the lower item.
        self::assertSame([], $this->rows("SELECT pid FROM pg_locks WHERE pid=$1 AND locktype='advisory' AND granted
            AND classid=hashtext('saldi:stock')::oid AND objid=$2::oid",[$firstPid,$higher['item']]));
        $this->rows('COMMIT');
        $firstPid = $this->ready($barrier);
        $secondBarrier = $this->path();
        $second = $this->launch(['mode'=>'sale','line'=>$secondLine,'caller_transaction'=>true,'barrier'=>$secondBarrier],true);
        $secondPid = $this->ready($secondBarrier);
        $this->release($secondBarrier);
        $this->blocked($firstPid,$secondPid);
        $this->release($barrier);
        self::assertSame('OK',$this->finish($first)['result']);
        self::assertSame('OK',$this->finish($second)['result']);
        foreach ([$lower['item'],$higher['item']] as $item) {
            $this->assertStock($item,0);
            self::assertEquals(0,$this->rows('SELECT SUM(rest) AS n FROM batch_kob WHERE vare_id=$1',[$item])[0]['n']);
            self::assertEquals(10,$this->rows('SELECT SUM(antal) AS n FROM batch_salg WHERE vare_id=$1',[$item])[0]['n']);
        }
    }

    public function testProductionLocksTheLowerComponentBeforeTheAssembledItemAndReadsAfterCount(): void
    {
        $component = $this->seed();
        $assembled = $this->seed(0);
        $this->rows("UPDATE varer SET samlevare='on' WHERE id=$1",[$assembled['item']]);
        $this->rows('INSERT INTO styklister(indgaar_i,vare_id,antal,posnr) VALUES($1,$2,2,1)',[$assembled['item'],$component['item']]);
        self::assertLessThan($assembled['item'],$component['item']);
        $barrier = $this->path();
        $count = $this->launch(['mode'=>'count','request'=>$this->request($component['item'],'13'),'barrier'=>$barrier]);
        $countPid = $this->ready($barrier);
        $production = $this->launch(['mode'=>'production','item'=>$assembled['item'],'session'=>self::$session]);
        $productionPid = $this->blocked($countPid);
        self::assertSame([], $this->rows("SELECT pid FROM pg_locks WHERE pid=$1 AND locktype='advisory' AND granted
            AND classid=hashtext('saldi:stock')::oid AND objid=$2::oid",[$productionPid,$assembled['item']]));
        $this->release($barrier);
        self::assertSame(201,$this->finish($count)['status']);
        $this->finish($production,true);
        $this->assertStock($component['item'],9);
        $this->assertStock($assembled['item'],2);
        self::assertEquals(4,$this->rows('SELECT SUM(antal) AS n FROM batch_salg WHERE vare_id=$1',[$component['item']])[0]['n']);
    }
}
