<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/GoodsReceiptConcurrencyTest.php --- ver 5.0.0 --- 2026.10.06
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


require_once __DIR__ . '/support/T4ReceiptTestCase.php';
require_once __DIR__ . '/../../kreditor/orderIncludes/lockOrderForSave.php';

final class GoodsReceiptConcurrencyTest extends T4ReceiptTestCase
{
    /** @return array{process:resource,pipes:array} Independent PostgreSQL session. */
    private function worker(array $body, string $etag, array $extra = []): array
    {
        $payload = $extra + ['body'=>$body,'etag'=>$etag,'db'=>RestApiEnv::testDb(),
            'tenant_id'=>RestApiEnv::regnskabId(RestApiEnv::accountOpen()),'user_id'=>(int)self::$user['id']];
        $process = proc_open([PHP_BINARY,__DIR__ . '/support/run_goods_receipt.php',json_encode($payload,JSON_THROW_ON_ERROR)],
            [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        self::assertIsResource($process);
        return ['process'=>$process,'pipes'=>$pipes];
    }

    private function finish(array $worker): array
    {
        $out = stream_get_contents($worker['pipes'][1]);
        $err = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);
        self::assertSame(0,proc_close($worker['process']),$err . $out);
        self::assertSame('',$err,'Worker must not add PHP warnings');
        return json_decode($out,true,512,JSON_THROW_ON_ERROR);
    }

    /** Wait for database-observed lock contention, rather than assuming a timed interleaving. */
    private function waitLocks(string $kind, int $count): void
    {
        $deadline = microtime(true) + 8;
        do {
            if ($kind === 'order') {
                $sql = "SELECT pid FROM pg_stat_activity WHERE datname=current_database() AND wait_event_type='Lock'
                    AND query ILIKE '%from ordrer%for update%'";
                $waiting = $this->rows($sql);
            } else {
                $waiting = $this->rows("SELECT pid FROM pg_locks WHERE locktype='advisory' AND NOT granted
                    AND objid=$1::oid AND database=(SELECT oid FROM pg_database WHERE datname=current_database())",[$this->f['item']]);
            }
            if (count($waiting) >= $count) {
                self::assertCount($count,$waiting);
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        self::fail('Expected ' . $count . ' waiting ' . $kind . ' workers, observed ' . count($waiting));
    }

    public function testTwoParallelReceiptsNeverExceedRemaining(): void
    {
        $body = $this->body('6');
        $other = $body;
        $other['client_id'] = MobileAuth::uuid();
        $etag = $this->detail()['etag'];
        $owner = RestApiEnv::connect(RestApiEnv::testDb());
        $workers = [];
        try {
            RestApiEnv::rows($owner,'BEGIN');
            RestApiEnv::rows($owner,'SELECT id FROM ordrer WHERE id=$1 FOR UPDATE',[$this->f['order']]);
            $workers = [$this->worker($body,$etag),$this->worker($other,$etag)];
            $this->waitLocks('order',2);
            RestApiEnv::rows($owner,'COMMIT');
            $responses = array_map(fn(array $w): array=>$this->finish($w),$workers);
            $workers = [];
            $statuses = array_map(static fn(array $r): int=>$r['status'] ?? $r['result']['status'],$responses);
            sort($statuses);
            self::assertSame([201,412],$statuses);
            self::assertSame('6.000',$this->rows('SELECT SUM(antal) AS n FROM batch_kob WHERE linje_id=$1',[$this->f['line']])[0]['n']);
            self::assertSame('4',$this->detail()['lines'][0]['remaining']);
            self::assertSame('10.000',$this->rows('SELECT beholdning FROM varer WHERE id=$1',[$this->f['item']])[0]['beholdning']);
            self::assertCount(1,$this->rows('SELECT * FROM mobile_goods_receipts WHERE ordre_id=$1',[$this->f['order']]));
        } finally {
            $this->cleanup($owner,$workers);
        }
    }

    public function testConcurrentIdenticalClientIdReplaysOneEffect(): void
    {
        $body = $this->body('6');
        $etag = $this->detail()['etag'];
        $owner = RestApiEnv::connect(RestApiEnv::testDb());
        $workers = [];
        try {
            RestApiEnv::rows($owner,'BEGIN');
            RestApiEnv::rows($owner,'SELECT id FROM ordrer WHERE id=$1 FOR UPDATE',[$this->f['order']]);
            $workers[] = $this->worker($body,$etag);
            $this->waitLocks('order',1);
            $workers[] = $this->worker($body,$etag);
            $deadline = microtime(true) + 5;
            do {
                $waiting = $this->rows("SELECT pid FROM pg_stat_activity WHERE datname=current_database() AND wait_event_type='Lock'
                    AND query LIKE 'SELECT pg_advisory_xact_lock(hashtextextended(%'");
                if ($waiting) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertCount(1,$waiting,'Retry must wait for the client identity before locking the order');
            RestApiEnv::rows($owner,'COMMIT');
            $responses = array_map(fn(array $w): array=>$this->finish($w),$workers);
            $workers = [];
            self::assertSame([201,201],array_column(array_column($responses,'result'),'status'));
            self::assertSame('true',$responses[1]['result']['headers']['Idempotent-Replayed']);
            self::assertSame('6.000',$this->rows('SELECT SUM(antal) AS n FROM batch_kob WHERE linje_id=$1',[$this->f['line']])[0]['n']);
        } finally {
            $this->cleanup($owner,$workers);
        }
    }

    public function testReceiptWaitingForActualWebSaveSeesItsNewQuantityAndStaleValidator(): void
    {
        $etag = $this->detail()['etag'];
        $body = $this->body('6');
        $owner = RestApiEnv::connect(RestApiEnv::testDb());
        $workers = [];
        try {
            RestApiEnv::rows($owner,'BEGIN');
            $context = new EngineContext($owner,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),
                RestApiEnv::user(),(int)self::$user['id'],'2026-10-06');
            self::assertTrue(LegacyEngine::run($context,fn(): bool=>lockCreditorOrderForSave($this->f['order'],1,null,false,[$this->f['line']])));
            RestApiEnv::rows($owner,'UPDATE ordrelinjer SET antal=5 WHERE id=$1',[$this->f['line']]);
            $workers[] = $this->worker($body,$etag);
            $this->waitLocks('order',1);
            RestApiEnv::rows($owner,'COMMIT');
            $response = $this->finish($workers[0]);
            $workers = [];
            self::assertSame('precondition_failed',$response['error']);
            self::assertSame(412,$response['status']);
            self::assertSame('5',$this->detail()['lines'][0]['remaining']);
            self::assertSame(201,$this->send($this->body('5'))['status']);
            self::assertSame('0',$this->detail()['lines'][0]['remaining']);
        } finally {
            $this->cleanup($owner,$workers);
        }
    }

    public function testWebSaveWaitsForReceiptOrderLockAndPreservesReceivedRows(): void
    {
        $body = $this->body('6');
        $etag = $this->detail()['etag'];
        $owner = RestApiEnv::connect(RestApiEnv::testDb());
        $workers = [];
        try {
            RestApiEnv::rows($owner,'BEGIN');
            RestApiEnv::rows($owner,"SELECT pg_advisory_xact_lock(hashtext('saldi:stock'),$1::integer)",[$this->f['item']]);
            $workers[] = $this->worker($body,$etag);
            $this->waitLocks('item',1);
            $workers[] = $this->worker($body,$etag,['mode'=>'save','ordered'=>'8']);
            $this->waitLocks('order',1);
            RestApiEnv::rows($owner,'COMMIT');
            $receipt = $this->finish($workers[0]);
            $save = $this->finish($workers[1]);
            $workers = [];
            self::assertSame(201,$receipt['result']['status']);
            self::assertSame('6.000',$save['result']['received_at_save']);
            $line = $this->detail()['lines'][0];
            self::assertSame(['8','6','2'],[$line['ordered'],$line['received'],$line['remaining']]);
            self::assertSame('6.000',$this->rows('SELECT leveret FROM ordrelinjer WHERE id=$1',[$this->f['line']])[0]['leveret']);
        } finally {
            $this->cleanup($owner,$workers);
        }
    }

    public function testDeferredShopSyncUsesIdleConnectionAndFailureDoesNotUndoReceipt(): void
    {
        $this->rows("INSERT INTO grupper(art,kodenr,box4,box5,box6) VALUES('API',1,'http://127.0.0.1/receipt-stub','','')");
        $this->rows('UPDATE varer SET m_type=$1,m_rabat=0,retail_price=20,colli_webfragt=0 WHERE id=$2',['percent',$this->f['item']]);
        $body = $this->body('6');
        $etag = $this->detail()['etag'];
        try {
            $response = $this->finish($this->worker($body,$etag,['sync_fail'=>true]));
            self::assertSame(201,$response['result']['status']);
            self::assertNotEmpty($response['calls']);
            self::assertSame([PGSQL_TRANSACTION_IDLE],array_values(array_unique(array_column($response['calls'],'transaction'))));
            self::assertSame('partial',$response['result']['body']['data']['shop_sync']);
            self::assertSame(['shop_sync_failed'],$response['result']['body']['data']['warnings']);
            $retry = $this->finish($this->worker($body,$etag));
            self::assertSame([],$retry['calls'],'Replay must not repeat synchronization');
            self::assertSame($response['result']['body'],$retry['result']['body']);
            self::assertSame('6',$this->detail()['lines'][0]['received']);
        } finally {
            $this->rows("DELETE FROM grupper WHERE art='API'");
        }
    }

    /** Release blockers first so failed tests cannot leave waiting child processes or tenants. */
    private function cleanup($connection, array $workers): void
    {
        if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) {
            RestApiEnv::rows($connection,'ROLLBACK');
        }
        foreach ($workers as $worker) {
            if (is_resource($worker['process'])) {
                proc_terminate($worker['process']);
                foreach ($worker['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($worker['process']);
            }
        }
        pg_close($connection);
    }
}
