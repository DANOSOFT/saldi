<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/StockCountConcurrencyTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Exercise current and late web snapshots and concurrent writes on two warehouses; require warning-free workers.
require_once __DIR__ . '/support/T4StockCountTestCase.php';

final class StockCountConcurrencyTest extends T4StockCountTestCase
{
    /** @return array{process:resource,pipes:array,pid:int} Separate PHP process and tenant database session. */
    private function worker(array $payload): array
    {
        $payload += ['db'=>self::$context->db,'tenant_id'=>self::$context->dbId,'username'=>self::$context->username,
            'user_id'=>self::$context->userId,'subject'=>self::$subject];
        $process = proc_open([PHP_BINARY, __DIR__ . '/support/run_stock_count_worker.php', json_encode($payload, JSON_THROW_ON_ERROR)],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        self::assertIsResource($process);
        stream_set_timeout($pipes[1], 10);
        $ready = json_decode((string)fgets($pipes[1]), true);
        self::assertIsArray($ready);
        return ['process'=>$process,'pipes'=>$pipes,'pid'=>$ready['pid']];
    }

    private function blocked(array $worker): void
    {
        for ($i = 0; $i < 200; $i++) {
            self::sql('SELECT pg_stat_clear_snapshot()');
            $state = self::sql('SELECT wait_event_type FROM pg_stat_activity WHERE pid=$1', [$worker['pid']]);
            if (($state[0]['wait_event_type'] ?? '') === 'Lock') {
                self::assertTrue(true);
                return;
            }
            usleep(10000);
        }
        stream_set_blocking($worker['pipes'][1], false);
        stream_set_blocking($worker['pipes'][2], false);
        self::fail('Worker did not wait for the shared item/session lock: ' . stream_get_contents($worker['pipes'][1]) . stream_get_contents($worker['pipes'][2]));
    }

    private function finish(array $worker): array
    {
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][0]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);
        $status = proc_close($worker['process']);
        self::assertSame(0, $status, $stderr . $stdout);
        self::assertSame('', $stderr);
        $result = json_decode(trim($stdout), true);
        self::assertIsArray($result, $stdout);
        return $result;
    }

    public function testGoldenS6bWebQueueWaitsThenRefusesAndPostingQueueChangesNothing(): void
    {
        $id = $this->item('10');
        $this->batch($id, '10');
        $body = $this->body($id, '7');
        $worker = null;
        $hook = function () use ($id, &$worker): void {
            $snapshotTime = (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('YmdHis');
            $worker = $this->worker(['mode'=>'queue','queue'=>['vare_id'=>$id,'variant_id'=>0,'lager'=>2,
                'beholdning'=>10,'optalt'=>'8','tidspkt'=>substr($snapshotTime, 0, 8) . '235959','snapshot_time'=>$snapshotTime,'sprog_id'=>0]]);
            $this->blocked($worker);
            self::assertSame([], self::sql('SELECT id FROM regulering WHERE vare_id=$1', [$id]));
        };
        $response = $this->service($hook)->submit($body, self::$actor);
        self::assertSame(201, $response['status']);
        $result = $this->finish($worker);
        $golden = json_decode(file_get_contents(__DIR__ . '/fixtures/t4/golden/S1-S6-counts.json'), true)['S6b'];
        self::assertStringContainsString($golden['text'], $result['result']);
        self::assertSame([], self::sql('SELECT id FROM regulering WHERE vare_id=$1', [$id]));
        $before = $this->effects($id);
        // The legacy poster expects at least one queued row; supply an unrelated unchanged item.
        $unrelated = $this->item('3');
        $this->batch($unrelated, '3');
        $queue = $this->worker(['mode'=>'queue','queue'=>['vare_id'=>$unrelated,'variant_id'=>0,'lager'=>2,
            'beholdning'=>3,'optalt'=>'3','tidspkt'=>'20260101000000','sprog_id'=>0]]);
        self::assertSame('', $this->finish($queue)['result']);
        $poster = $this->worker(['mode'=>'post-queue','id'=>$id]);
        $result = $this->finish($poster);
        self::assertSame('7', $result['result']['quantity']);
        self::assertSame($before, $this->effects($id));
    }

    public function testWebQueueFirstMakesAppRefuse(): void
    {
        $id = $this->item('10');
        $body = $this->body($id, '7');
        $worker = $this->worker(['mode'=>'queue','queue'=>['vare_id'=>$id,'variant_id'=>0,'lager'=>2,
            'beholdning'=>10,'optalt'=>'8','tidspkt'=>'20260101000000','sprog_id'=>0]]);
        self::assertSame('', $this->finish($worker)['result']);
        $this->error(fn(): array => $this->submit($body), 'web_count_open', 409);
        self::assertSame('10', $this->snapshot($id)['quantity']);
        self::assertSame([], self::sql('SELECT id FROM stocklog WHERE item_id=$1', [$id]));
    }

    public function testWebFormTakenAfterTheCountHeaderStillWaitsAndRefuses(): void
    {
        $id = $this->item('0', false);
        $body = $this->body($id, '3');
        $pid = (int)self::sql('SELECT pg_backend_pid() AS pid')[0]['pid'];
        self::sql("CREATE FUNCTION count_test_pause() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN PERFORM pg_sleep(3); RETURN NEW; END;'");
        self::sql("CREATE TRIGGER count_test_pause BEFORE INSERT ON mobile_stock_count_lines FOR EACH ROW WHEN (NEW.vare_id=$id) EXECUTE FUNCTION count_test_pause()");
        $worker = null;
        try {
            $this->service(function () use ($id, $pid, &$worker): void {
                $worker = $this->worker(['mode'=>'queue','wait_for_sleep'=>$pid,'queue'=>[
                    'vare_id'=>$id,'variant_id'=>0,'lager'=>2,'beholdning'=>0,'optalt'=>'2',
                    'tidspkt'=>'20261006235959','sprog_id'=>0]]);
            })->submit($body, self::$actor);
            $result = $this->finish($worker);
            self::assertGreaterThan(1, $result['queue_seconds']);
            self::assertStringContainsString('Følgende varer har ændret antal under optællingen og skal optælles igen.', $result['result']);
            self::assertSame([], self::sql('SELECT id FROM regulering WHERE vare_id=$1', [$id]));
            self::assertSame('3', $this->snapshot($id)['quantity']);
        } finally {
            self::sql('DROP TRIGGER count_test_pause ON mobile_stock_count_lines');
            self::sql('DROP FUNCTION count_test_pause()');
        }
    }

    public function testTwoSessionsOnOneItemMakeTheSecondStale(): void
    {
        $id = $this->item('10');
        $this->batch($id, '10');
        $first = $this->body($id, '7');
        $second = $this->body($id, '8');
        $worker = null;
        $this->service(function () use ($second, &$worker): void {
            $worker = $this->worker(['mode'=>'submit','body'=>$second]);
            $this->blocked($worker);
        })->submit($first, self::$actor);
        $result = $this->finish($worker);
        self::assertSame(409, $result['status']);
        self::assertSame('count_stale', $result['details']['reason']);
        self::assertSame('7', $this->snapshot($id)['quantity']);
        self::assertCount(1, self::sql('SELECT id FROM mobile_stock_count_lines WHERE vare_id=$1', [$id]));
    }

    public function testConcurrentReplayHasOneEffectAndSameSessionNewClientIsRefused(): void
    {
        foreach (['replay','session'] as $mode) {
            $id = $this->item('0', false);
            $body = $this->body($id, '3');
            $second = $body;
            if ($mode === 'session') {
                $second['client_id'] = MobileAuth::uuid();
            }
            $worker = null;
            $first = $this->service(function () use ($second, &$worker): void {
                $worker = $this->worker(['mode'=>'submit','body'=>$second]);
                $this->blocked($worker);
            })->submit($body, self::$actor);
            $result = $this->finish($worker);
            if ($mode === 'replay') {
                // The provisional response may be replayed before post-commit shop completion.
                self::assertSame($first['body']['data']['count_id'], $result['result']['body']['data']['count_id']);
                self::assertSame(['Idempotent-Replayed'=>'true'], $result['result']['headers']);
            } else {
                self::assertSame('count_already_posted', $result['details']['reason']);
            }
            self::assertCount(1, self::sql('SELECT id FROM mobile_stock_count_lines WHERE vare_id=$1', [$id]));
            self::assertCount(1, self::sql('SELECT id FROM lagerstatus WHERE vare_id=$1', [$id]));
            self::assertCount(1, self::sql('SELECT id FROM batch_kob WHERE vare_id=$1', [$id]));
        }
    }

    public function testCountThenProductCardSavePreservesBothAdjustments(): void
    {
        $id = $this->item('10');
        $this->batch($id, '10');
        $worker = null;
        $body = $this->body($id, '5');
        $this->service(function () use ($id, &$worker): void {
            $worker = $this->worker(['mode'=>'web','id'=>$id,'before'=>'10','quantity'=>'7']);
            $this->blocked($worker);
        })->submit($body, self::$actor);
        $this->finish($worker);
        self::assertSame('7', $this->snapshot($id)['quantity']);
        self::assertEquals([-5,-3], array_column(self::sql('SELECT correction FROM stocklog WHERE item_id=$1 ORDER BY id', [$id]), 'correction'));
        self::assertEquals(7, self::sql('SELECT SUM(rest) AS n FROM batch_kob WHERE vare_id=$1', [$id])[0]['n']);
        self::assertEquals(7, self::sql('SELECT beholdning FROM varer WHERE id=$1', [$id])[0]['beholdning']);
    }

    public function testProductCardSaveThenCountIsStaleAfterWaiting(): void
    {
        $id = $this->item('10');
        $this->batch($id, '10');
        $body = $this->body($id, '5');
        $web = $this->worker(['mode'=>'web-held','id'=>$id,'before'=>'10','quantity'=>'7']);
        self::assertSame("held\n", fgets($web['pipes'][1]));
        $worker = $this->worker(['mode'=>'submit','body'=>$body]);
        $this->blocked($worker);
        fwrite($web['pipes'][0], "commit\n");
        $this->finish($web);
        $result = $this->finish($worker);
        self::assertSame('count_stale', $result['details']['reason']);
        self::assertSame('7', $this->snapshot($id)['quantity']);
        self::assertSame([], self::sql('SELECT id FROM mobile_stock_count_lines WHERE vare_id=$1', [$id]));
    }

    public function testCountAndProductCardOnDifferentWarehousesKeepTheItemTotal(): void
    {
        $id = $this->item('10');
        $this->batch($id, '10');
        $worker = null;
        $this->service(function () use ($id, &$worker): void {
            $worker = $this->worker(['mode'=>'web','id'=>$id,'lager'=>1,'before'=>'0','quantity'=>'3']);
            $this->blocked($worker);
        })->submit($this->body($id, '5'), self::$actor);
        $this->finish($worker);

        self::assertSame('3', $this->snapshot($id, 1)['quantity']);
        self::assertSame('5', $this->snapshot($id, 2)['quantity']);
        self::assertEquals([['lager'=>1,'quantity'=>3],['lager'=>2,'quantity'=>5]],
            self::sql('SELECT lager,SUM(rest) AS quantity FROM batch_kob WHERE vare_id=$1 GROUP BY lager ORDER BY lager', [$id]));
        $total = self::sql('SELECT v.beholdning,(SELECT SUM(s.beholdning) FROM lagerstatus s WHERE s.vare_id=v.id) AS warehouses FROM varer v WHERE v.id=$1', [$id])[0];
        self::assertEquals(8, $total['beholdning']);
        self::assertEquals($total['warehouses'], $total['beholdning']);
    }
}
