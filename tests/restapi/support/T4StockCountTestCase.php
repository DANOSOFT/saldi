<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T4StockCountTestCase.php --- ver 5.0.0 --- 2026.10.06
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
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../restapi/services/StockCountService.php';
require_once __DIR__ . '/../../../restapi/core/MobileAuth.php';

/** Disposable stock tenant; API configuration is removed before any movement can run. */
abstract class T4StockCountTestCase extends TestCase
{
    protected static $tenant;
    protected static EngineContext $context;
    protected static EmployeeContext $actor;
    protected static string $subject;
    protected static int $group;
    protected static int $employee;

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        RestApiEnv::loginData();
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $_SERVER['REQUEST_URI'] = '/saldi/lager/optalling.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $user = self::sql('SELECT * FROM brugere WHERE brugernavn=$1', [RestApiEnv::user()])[0];
        $id = (int)$user['id'];
        self::$context = new EngineContext(self::$tenant, RestApiEnv::testDb(), RestApiEnv::regnskabId(RestApiEnv::accountOpen()), $user['brugernavn'], $id, '2026-10-06');
        self::$group = 989100;
        self::sql("DELETE FROM grupper WHERE art IN ('API','LG')");
        self::sql("INSERT INTO grupper(art,kodenr,beskrivelse,box1,box2,box3,box4,box8,box9,fiscal_year) VALUES
            ('VG',$1,'Count test','1','2','1000','2000','on','',$2),
            ('LG','1','One','','','','','','',$2),('LG','2','Two','','','','','','',$2)", [self::$group,self::$context->regnaar]);
        self::$employee = (int)self::sql("INSERT INTO ansatte(navn,initialer,lukket) VALUES('Count actor','CA','') RETURNING id")[0]['id'];
        self::sql('UPDATE brugere SET ansat_id=$1 WHERE id=$2', [self::$employee,$id]);
        self::$actor = new EmployeeContext(self::$tenant, 'account', $id);
        self::$subject = Subject::build('account', $id);
        $directory = dirname(__DIR__, 3) . '/temp/' . RestApiEnv::testDb();
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        // CLI workers and Apache both use this disposable tenant's legacy log.
        chmod($directory, 0777);
        file_put_contents($directory . '/rest_api.log', '');
        chmod($directory . '/rest_api.log', 0666);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            pg_close(self::$tenant);
            self::$tenant = null;
            RestApiEnv::teardownTenant();
        }
    }

    protected static function sql(string $sql, array $params = []): array
    {
        return RestApiEnv::rows(self::$tenant, $sql, $params);
    }

    protected function service(?Closure $hook = null, $connection = null): StockCountService
    {
        $context = $connection === null ? self::$context : new EngineContext($connection, self::$context->db, self::$context->dbId,
            self::$context->username, self::$context->userId, '2026-10-06');
        return new StockCountService($context, self::$subject, $hook);
    }

    protected function item(string $quantity = '0', bool $status = true): int
    {
        $id = (int)self::sql("INSERT INTO varer(varenr,beskrivelse,gruppe,kostpris,salgspris,beholdning,serienr,samlevare,lukket,enhed,stregkode,varenr_alias)
            VALUES($1,'Count fixture',$2,4,8,$3,'','','','stk',$4,$5) RETURNING id",
            ['COUNT-'.bin2hex(random_bytes(5)),self::$group,$quantity,'EAN-'.bin2hex(random_bytes(5)),"Alias's ".bin2hex(random_bytes(5))])[0]['id'];
        if ($status) {
            self::sql('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES($1,0,2,$2)', [$id,$quantity]);
        }
        return $id;
    }

    protected function batch(int $id, string $quantity, string $due = '2026-12-01', string $date = '2026-01-01'): int
    {
        return (int)self::sql('INSERT INTO batch_kob(vare_id,variant_id,lager,antal,rest,pris,kobsdate,fakturadate,due_date,linje_id,ordre_id)
            VALUES($1,0,2,$2,$2,4,$3,$3,$4,0,0) RETURNING id', [$id,$quantity,$date,$due])[0]['id'];
    }

    protected function snapshot(int $id, int $lager = 2): array
    {
        return LegacyEngine::run(self::$context, static fn(): array => stock_snapshot($id, $lager));
    }

    protected function body(int $id, string $quantity, int $lager = 2): array
    {
        $snapshot = $this->snapshot($id, $lager);
        return ['client_id'=>MobileAuth::uuid(),'session_id'=>MobileAuth::uuid(),'lager'=>$lager,'reason'=>'optaelling',
            'note'=>"Count's note",'started_at'=>'2026-10-06T10:00:00+02:00','lines'=>[
                ['line_ref'=>MobileAuth::uuid(),'vare_id'=>$id,'counted_qty'=>$quantity,'expected_qty'=>$snapshot['quantity'],
                    'expected_token'=>$snapshot['token'],'counted_at'=>'2026-10-06T10:01:00+02:00']]];
    }

    protected function submit(array $body): array
    {
        return $this->service()->submit($body, self::$actor)['body']['data'];
    }

    protected function error(callable $operation, string $reason, int $status): MobileT2Error
    {
        try {
            $operation();
            self::fail('Expected ' . $reason);
        } catch (MobileT2Error $error) {
            self::assertSame($status, $error->status);
            self::assertSame($reason, $error->details['reason']);
            return $error;
        }
    }

    /** @return array Absolute rows without surrogate identities; batch references become seed-order indices. */
    protected function effects(int $id): array
    {
        $batches = self::sql('SELECT id,antal,rest,pris,lager,variant_id FROM batch_kob WHERE vare_id=$1 ORDER BY id', [$id]);
        $positions = [0=>0];
        foreach ($batches as $index=>$batch) {
            $positions[(int)$batch['id']] = $index + 1;
            unset($batches[$index]['id']);
        }
        $sales = self::sql('SELECT batch_kob_id,antal,pris,lager,variant_id FROM batch_salg WHERE vare_id=$1 ORDER BY id', [$id]);
        foreach ($sales as &$sale) {
            $sale['batch_kob_id'] = $positions[(int)$sale['batch_kob_id']];
        }
        unset($sale);
        return ['quantity'=>$this->snapshot($id)['quantity'],
            'item_quantity'=>self::sql('SELECT beholdning FROM varer WHERE id=$1', [$id])[0]['beholdning'],
            'batches'=>array_map('array_values', $batches),'sales'=>array_map('array_values', $sales),
            'log_deltas'=>array_column(self::sql('SELECT correction FROM stocklog WHERE item_id=$1 ORDER BY id', [$id]), 'correction'),
            'variants'=>self::sql('SELECT variant_beholdning FROM variant_varer WHERE vare_id=$1 ORDER BY id', [$id]),
            'reservations'=>self::sql('SELECT antal FROM reservation WHERE vare_id=$1 ORDER BY antal', [$id])];
    }

    protected function ledger(): array
    {
        return array_map(static fn(string $table): array => self::sql("SELECT * FROM $table ORDER BY id"), ['transaktioner','openpost','kontoplan']);
    }

    /** Replay the actual accepted-change controller up to its rendering/exit boundary. */
    protected function webSet(int $id, string $quantity): void
    {
        $before = $this->snapshot($id)['quantity'];
        if ($before === $quantity) {
            return;
        }
        $page = file_get_contents(__DIR__ . '/../../../lager/varekort.php');
        $start = strpos($page, 'if ($acceptStockChange) {');
        $start = strpos($page, 'require_once', $start);
        $end = strpos($page, '    print "<meta', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $code = str_replace('__DIR__', var_export(realpath(__DIR__ . '/../../../lager'), true), substr($page, $start, $end - $start));
        $saved = $_POST;
        $_POST = ['beholdning'=>$before,'ny_beholdning'=>$quantity,'initials'=>'CA','reason'=>'Web fixture',
            'lagerbeh'=>[2=>$before],'ny_lagerbeh'=>[2=>$quantity]];
        try {
            LegacyEngine::run(self::$context, static function () use ($id, $code): void {
                extract($GLOBALS, EXTR_SKIP);
                $warehouseNames = [1=>'One',2=>'Two'];
                eval($code);
            });
        } finally {
            $_POST = $saved;
        }
    }
}
