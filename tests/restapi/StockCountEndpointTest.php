<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/StockCountEndpointTest.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Verify the installed count capability through auth/me.
// 20261007 CDX/LH Cover undated configuration outside the current fiscal year.
require_once __DIR__ . '/support/T4StockCountTestCase.php';

final class StockCountEndpointTest extends T4StockCountTestCase
{
    private const PATH = '/restapi/endpoints/v1/stock/';

    private function request(string $method, string $path, ?array $body = null): array
    {
        return RestApiEnv::http($method, self::PATH . $path, $body, RestApiEnv::authHeaders());
    }

    public function testCountsAndReplaysWorkWithoutACurrentFiscalYear(): void
    {
        $id = $this->item('10');
        $this->batch($id, '10');
        $body = $this->body($id, '7');
        $before = $this->request('POST', 'counts/index.php', $body);
        self::assertSame(201, $before['status'], $before['body']);
        $years = self::sql("SELECT id,box1,box2,box3,box4 FROM grupper WHERE art='RA'");
        self::sql("UPDATE grupper SET box1='1',box2='2000',box3='12',box4='2000' WHERE art='RA'");
        try {
            foreach (['warehouses.php', 'counts/index.php', 'counts/snapshot.php?lager=2', 'counts/lookup.php?lager=2&code=unknown'] as $path) {
                $response = $this->request('GET', $path);
                self::assertSame(200, $response['status'], $path . ': ' . $response['body']);
            }
            $replay = $this->request('POST', 'counts/index.php', $body);
            self::assertSame(201, $replay['status'], $replay['body']);
            self::assertSame($before['json'], $replay['json']);
            $next = $this->request('POST', 'counts/index.php', $this->body($id, '6'));
            self::assertSame(201, $next['status'], $next['body']);
        } finally {
            foreach ($years as $row) {
                self::sql('UPDATE grupper SET box1=$1,box2=$2,box3=$3,box4=$4 WHERE id=$5',
                    [$row['box1'],$row['box2'],$row['box3'],$row['box4'],$row['id']]);
            }
        }
    }

    public function testWarehouseSnapshotLookupAndHistoryContracts(): void
    {
        $me = RestApiEnv::http('GET', '/restapi/endpoints/v1/auth/me.php', null, RestApiEnv::authHeaders());
        self::assertSame(200, $me['status'], $me['body']);
        self::assertSame('v2', $me['json']['data']['capabilities']['stock_count']);
        $id = $this->item('10');
        $warehouses = $this->request('GET', 'warehouses.php');
        self::assertSame(200, $warehouses['status'], $warehouses['body']);
        self::assertSame([['lager'=>1,'name'=>'One'],['lager'=>2,'name'=>'Two']], $warehouses['json']['data']['items']);
        $snapshot = $this->request('GET', 'counts/snapshot.php?lager=2');
        self::assertSame(200, $snapshot['status'], $snapshot['body']);
        self::assertSame(500, $snapshot['json']['data']['limit']);
        $last = max(0, $snapshot['json']['data']['total'] - 1);
        $snapshot = $this->request('GET', 'counts/snapshot.php?lager=2&offset=' . $last);
        $item = array_values(array_filter($snapshot['json']['data']['items'], static fn(array $row): bool => $row['vare_id'] === $id))[0];
        self::assertSame('10', $item['quantity']);
        self::assertSame('4.00', $item['kostpris']);
        self::assertTrue($item['countable']);
        self::assertNull($item['why']);
        $alias = self::sql('SELECT varenr_alias FROM varer WHERE id=$1', [$id])[0]['varenr_alias'];
        $lookup = $this->request('GET', 'counts/lookup.php?lager=2&code=' . rawurlencode($alias));
        self::assertSame(200, $lookup['status'], $lookup['body']);
        self::assertSame([$item], $lookup['json']['data']['items']);
        self::assertSame([], $this->request('GET', 'counts/lookup.php?lager=2&code=not-a-known-code')['json']['data']['items']);
        $this->batch($id, '10');
        $response = $this->request('POST', 'counts/index.php', $this->body($id, '7'));
        self::assertSame(201, $response['status'], $response['body']);
        $count = $response['json']['data'];
        self::assertSame('-12.00', $count['value_estimate']);
        self::assertSame('not_configured', $count['shop_sync']);
        $history = $this->request('GET', 'counts/index.php?lager=2');
        self::assertSame(200, $history['status'], $history['body']);
        self::assertSame(50, $history['json']['data']['limit']);
        self::assertSame('Count actor', $history['json']['data']['items'][0]['by_name']);
        $detail = $this->request('GET', 'counts/index.php?id=' . $count['count_id']);
        self::assertSame($count['lines'], $detail['json']['data']['lines']);
        self::assertSame("Count's note", $detail['json']['data']['note']);
        self::assertSame(404, $this->request('GET', 'counts/index.php?id=2147483647')['status']);
        self::sql("UPDATE mobile_stock_counts SET shop_sync='pending' WHERE id=$1", [$count['count_id']]);
        self::assertSame('partial', $this->request('GET', 'counts/index.php?id=' . $count['count_id'])['json']['data']['shop_sync']);
    }

    public function testGoldenS1S2S3S4AbsoluteRowsAndEquivalentWebMovements(): void
    {
        $cases = json_decode(file_get_contents(__DIR__ . '/fixtures/t4/golden/S1-S6-counts.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($cases as $name=>$case) {
            if (!in_array($name, ['S1','S2','S3','S4'], true)) {
                continue;
            }
            $app = $this->item($case['before'], $name !== 'S1');
            $web = $this->item($case['before'], $name !== 'S1');
            foreach ([$app,$web] as $id) {
                foreach ($case['seed_batches'] as $batch) {
                    $this->batch($id, $batch['qty'], $batch['due'], $batch['date']);
                }
            }
            $ledger = $this->ledger();
            $data = $this->submit($this->body($app, $case['counted']));
            $this->webSet($web, $case['counted']);
            self::assertSame($this->effects($web), $this->effects($app), $name);
            self::assertSame($ledger, $this->ledger(), $name . ' ledger unchanged');
            $effects = $this->effects($app);
            self::assertSame($case['counted'], $effects['quantity'], $name);
            self::assertEquals($case['rests'], array_column($effects['batches'], 1), $name);
            self::assertEquals($case['sales'], $effects['sales'], $name);
            self::assertSame($case['delta'], $data['lines'][0]['delta']);
            self::assertSame($case['value'], $data['value_estimate']);
            self::assertSame($case['delta'] === '0' ? 0 : 1, $data['adjusted_count']);
            self::assertEquals(array_sum(array_column($effects['batches'], 1)) - array_sum(array_map(static fn(array $sale): float => $sale[0] === 0 ? (float)$sale[1] : 0.0, $effects['sales'])), (float)$effects['quantity']);
            self::assertEquals((float)$effects['quantity'], (float)$effects['item_quantity']);
            require_once __DIR__ . '/../../lager/lagerstatusValue.php';
            $values = LegacyEngine::run(self::$context, fn(): array => [lagerstatusPurchaseValue(self::sql(lagerstatusPurchaseValueSql($app, 2, 'levdate', null)), (float)$case['counted']),lagerstatusPurchaseValue(self::sql(lagerstatusPurchaseValueSql($web, 2, 'levdate', null)), (float)$case['counted'])]);
            self::assertSame($values[0], $values[1]);
        }
    }

    public function testGoldenS5StaleIsAtomicAndSetMatchesWebCard(): void
    {
        $id = $this->item('10');
        $this->batch($id, '10');
        $body = $this->body($id, '6');
        $other = $this->item('3');
        $this->batch($other, '3');
        $body['lines'][] = $this->body($other, '1')['lines'][0];
        $this->webSet($id, '8');
        $before = $this->effects($id);
        $error = $this->error(fn(): array => $this->submit($body), 'count_stale', 409);
        self::assertSame('8', $error->details['lines'][0]['current_qty']);
        self::assertSame('10', $error->details['lines'][0]['expected_qty']);
        self::assertSame($before, $this->effects($id));
        self::assertSame('3', $this->snapshot($other)['quantity']);
        $body['lines'][0]['resolution'] = 'set';
        $body['client_id'] = MobileAuth::uuid();
        $data = $this->submit($body);
        self::assertSame('6', $this->snapshot($id)['quantity']);
        self::assertSame('1', $this->snapshot($other)['quantity']);
        self::assertSame(2, $data['adjusted_count']);
        $web = $this->item('10');
        $this->batch($web, '10');
        $this->webSet($web, '8');
        $this->webSet($web, '6');
        self::assertSame($this->effects($web), $this->effects($id));
    }

    public function testGoldenS6WebQueueOpenAndS12AssembledWriteNothing(): void
    {
        $id = $this->item('10');
        $body = $this->body($id, '7');
        self::sql("INSERT INTO regulering(vare_id,variant_id,lager,optalt,beholdning,bogfort,tidspkt) VALUES($1,0,2,9,10,false,'20261001000000')", [$id]);
        $before = $this->effects($id);
        $this->error(fn(): array => $this->submit($body), 'web_count_open', 409);
        self::assertSame($before, $this->effects($id));
        $id = $this->item('10');
        self::sql("UPDATE varer SET samlevare='on' WHERE id=$1", [$id]);
        $before = $this->effects($id);
        $golden = json_decode(file_get_contents(__DIR__ . '/fixtures/t4/golden/S12-assembled.json'), true, 512, JSON_THROW_ON_ERROR);
        $error = $this->error(fn(): array => $this->submit($this->body($id, '7')), $golden['reason'], $golden['http']);
        self::assertSame($golden['error'], $error->error);
        self::assertSame($golden['why'], $error->details['lines'][0]['why']);
        self::assertSame($before, $this->effects($id));
    }

    public function testReplayIsExactAndSessionCannotPostTwice(): void
    {
        $id = $this->item('0', false);
        $body = $this->body($id, '3');
        $first = $this->service()->submit($body, self::$actor);
        $effects = $this->effects($id);
        $replay = $this->service()->submit($body, self::$actor);
        self::assertSame($first['body'], $replay['body']);
        self::assertSame(['Idempotent-Replayed'=>'true'], $replay['headers']);
        self::assertSame($effects, $this->effects($id));
        $body['note'] = 'Changed';
        $this->error(fn(): array => $this->submit($body), 'client_id_reused', 409);
        $body['client_id'] = MobileAuth::uuid();
        $error = $this->error(fn(): array => $this->submit($body), 'count_already_posted', 409);
        self::assertSame($first['body']['data']['count_id'], $error->details['count_id']);
        $curl = curl_init(RestApiEnv::baseUrl() . self::PATH . 'counts/index.php');
        $headers = '';
        // The HTTP replay uses a separate fresh immutable session.
        $fresh = $this->body($id, '4');
        self::assertSame(201, $this->request('POST', 'counts/index.php', $fresh)['status']);
        curl_setopt_array($curl, [CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_POSTFIELDS=>json_encode($fresh),
            CURLOPT_HTTPHEADER=>array_merge(RestApiEnv::authHeaders(), ['Content-Type: application/json']),
            CURLOPT_HEADERFUNCTION=>static function ($handle, string $line) use (&$headers): int { $headers .= $line; return strlen($line); }]);
        $raw = curl_exec($curl);
        self::assertSame(201, curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string)$raw);
        curl_close($curl);
        self::assertStringContainsString('Idempotent-Replayed: true', $headers);
    }

    public function testReplayPrecedesCurrentWarehouseValidation(): void
    {
        $id = $this->item('0', false);
        $body = $this->body($id, '3');
        $response = $this->service()->submit($body, self::$actor);
        $warehouses = self::sql("SELECT kodenr,beskrivelse,fiscal_year FROM grupper WHERE art='LG' AND kodenr=2");
        self::sql("DELETE FROM grupper WHERE art='LG' AND kodenr=2");
        try {
            $replay = $this->service()->submit($body, self::$actor);
            self::assertSame($response['body'], $replay['body']);
            self::assertSame(['Idempotent-Replayed'=>'true'], $replay['headers']);
            $invalidReuse = $body;
            $invalidReuse['reason'] = 'invalid';
            $this->error(fn(): array => $this->submit($invalidReuse), 'client_id_reused', 409);
            $body['client_id'] = MobileAuth::uuid();
            $this->error(fn(): array => $this->submit($body), 'count_already_posted', 409);
        } finally {
            foreach ($warehouses as $warehouse) {
                self::sql("INSERT INTO grupper(art,kodenr,beskrivelse,fiscal_year) VALUES('LG',$1,$2,$3)", array_values($warehouse));
            }
        }
        self::assertSame('3', $this->snapshot($id)['quantity']);
    }

    public function testEachCountabilityReasonAndBatchIncreaseIsRefused(): void
    {
        foreach (['variant','serial','assembled','not_stock_item','closed'] as $why) {
            $id = $this->item('10');
            if ($why === 'variant') {
                self::sql("INSERT INTO variant_varer(vare_id,variant_stregkode) VALUES($1,'Test variant')", [$id]);
            } elseif ($why === 'not_stock_item') {
                self::sql('UPDATE varer SET gruppe=2147483647 WHERE id=$1', [$id]);
            } else {
                $column = ['serial'=>'serienr','assembled'=>'samlevare','closed'=>'lukket'][$why];
                self::sql("UPDATE varer SET $column='on' WHERE id=$1", [$id]);
            }
            $error = $this->error(fn(): array => $this->submit($this->body($id, '7')), 'not_countable', 422);
            self::assertSame($why, $error->details['lines'][0]['why']);
            $code = self::sql('SELECT varenr FROM varer WHERE id=$1', [$id])[0]['varenr'];
            $lookup = $this->request('GET', 'counts/lookup.php?lager=2&code=' . rawurlencode($code));
            self::assertSame(200, $lookup['status'], $lookup['body']);
            self::assertFalse($lookup['json']['data']['items'][0]['countable']);
            self::assertSame($why, $lookup['json']['data']['items'][0]['why']);
            self::assertSame([], self::sql('SELECT id FROM mobile_stock_count_lines WHERE vare_id=$1', [$id]));
        }
        foreach (['batch','due'] as $tracking) {
            $id = $this->item('10');
            $this->batch($id, '10');
            if ($tracking === 'due') {
                self::sql('UPDATE varer SET has_due_date=true WHERE id=$1', [$id]);
            } else {
                self::sql("UPDATE grupper SET box9='on' WHERE art='VG' AND kodenr=$1", [self::$group]);
            }
            try {
                $this->error(fn(): array => $this->submit($this->body($id, '11')), 'batch_tracked_increase', 422);
                self::assertSame('10', $this->snapshot($id)['quantity']);
                self::assertSame('-1', $this->submit($this->body($id, '9'))['lines'][0]['delta']);
            } finally {
                self::sql("UPDATE grupper SET box9='' WHERE art='VG' AND kodenr=$1", [self::$group]);
            }
        }
    }

    public function testInvalidBodiesPagingWarehousesAndDuplicateLinesWriteNothing(): void
    {
        $id = $this->item('10');
        $body = $this->body($id, '7');
        foreach ([['reason'=>'x'],['lager'=>0],['lines'=>[]],['lines'=>array_fill(0, 1001, $body['lines'][0])],
            ['lines'=>[$body['lines'][0],$body['lines'][0]]],['started_at'=>'2026-10-06T00:00:00']] as $change) {
            $r = $this->request('POST', 'counts/index.php', array_replace($body, $change));
            self::assertSame(422, $r['status'], $r['body']);
        }
        foreach ([7,'NaN',"0' OR 1=1",'1.1234','9999999999999'] as $quantity) {
            $body['lines'][0]['counted_qty'] = $quantity;
            self::assertSame(422, $this->request('POST', 'counts/index.php', $body)['status']);
        }
        self::assertSame('10', $this->snapshot($id)['quantity']);
        self::assertSame([], self::sql('SELECT id FROM stocklog WHERE item_id=$1', [$id]));
        foreach (['lager=2&limit=-1','lager=999','lager=2&offset=oops'] as $query) {
            self::assertSame(422, $this->request('GET', 'counts/snapshot.php?' . $query)['status']);
        }
        self::assertSame(405, $this->request('PATCH', 'counts/index.php', [])['status']);
        self::assertSame(401, RestApiEnv::http('GET', self::PATH . 'warehouses.php')['status']);
    }

    public function testShopSyncRunsAfterCommitOncePerChangedItemAndReplayDoesNotSync(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $log = tempnam(sys_get_temp_dir(), 'count-shop-');
        $env = getenv();
        $env['SALDI_STOCK_STUB_LOG'] = $log;
        $server = proc_open([PHP_BINARY,'-S',$address,'-t',__DIR__ . '/../fixtures/shop'],
            [0=>['file','/dev/null','r'],1=>['file',$log . '.server','a'],2=>['file',$log . '.server','a']], $pipes, null, $env);
        try {
            $ready = false;
            for ($i = 0; $i < 100; $i++) {
                $curl = curl_init('http://' . $address . '/ready');
                curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT_MS=>100]);
                $ready = curl_exec($curl) !== false;
                curl_close($curl);
                if ($ready) {
                    break;
                }
                usleep(10000);
            }
            self::assertTrue($ready);
            self::sql("INSERT INTO grupper(art,kodenr,box4,box5,box6) VALUES('API','1',$1,'','')", ['http://' . $address . '/shop_api_stub.php']);
            $id = $this->item('0', false);
            self::sql('INSERT INTO shop_varer(saldi_id,shop_id) VALUES($1,$1)', [$id]);
            $unchanged = $this->item('10');
            $body = $this->body($id, '3');
            $body['lines'][] = $this->body($unchanged, '10')['lines'][0];
            file_put_contents($log, '');
            $service = $this->service(static function () use ($log): void {
                self::assertSame('', file_get_contents($log));
            });
            $response = $service->submit($body, self::$actor);
            self::assertSame('done', $response['body']['data']['shop_sync']);
            $calls = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $stocks = array_filter(array_map(static fn(string $line): array => json_decode($line, true), $calls), static fn(array $row): bool => isset($row['update_stock']));
            self::assertCount(1, $stocks);
            self::assertEquals(3, array_values($stocks)[0]['stock']);
            self::assertSame('done', self::sql('SELECT shop_sync FROM mobile_stock_counts WHERE id=$1', [$response['body']['data']['count_id']])[0]['shop_sync']);
            self::assertSame($response['body'], $service->submit($body, self::$actor)['body']);
            self::assertSame($calls, file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        } finally {
            self::sql("DELETE FROM grupper WHERE art='API'");
            proc_terminate($server);
            proc_close($server);
            unlink($log);
            unlink($log . '.server');
        }
    }

    public function testReadOnlyStockUserCanReadButCannotSubmit(): void
    {
        $digits = str_repeat('0', 16);
        $digits[9] = '2';
        $user = self::sql('INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,true) RETURNING *',
            ['count_ro_' . bin2hex(random_bytes(4)),md5(TestCredentials::password('stock_count_readonly')),$digits])[0];
        $login = RestApiEnv::login($user['brugernavn'], TestCredentials::password('stock_count_readonly'), RestApiEnv::accountOpen());
        self::assertSame(200, $login['status'], $login['body']);
        $token = $login['json']['data']['access_token'];
        $headers = ['Authorization: Bearer ' . $token];
        $id = $this->item('10');
        $code = self::sql('SELECT varenr FROM varer WHERE id=$1', [$id])[0]['varenr'];
        foreach (['warehouses.php','counts/snapshot.php?lager=2','counts/lookup.php?lager=2&code=' . rawurlencode($code),'counts/index.php'] as $path) {
            $response = RestApiEnv::http('GET', self::PATH . $path, null, $headers);
            self::assertSame(200, $response['status'], $response['body']);
        }
        $response = RestApiEnv::http('POST', self::PATH . 'counts/index.php', $this->body($id, '7'), $headers);
        self::assertSame(403, $response['status'], $response['body']);
        self::assertSame('permission_denied', $response['json']['data']['error']);
        self::assertSame('10', $this->snapshot($id)['quantity']);
    }

    public function testNoWarehouseDefinitionsFallsBackToOne(): void
    {
        $rows = self::sql("SELECT * FROM grupper WHERE art='LG'");
        try {
            self::sql("DELETE FROM grupper WHERE art='LG'");
            self::assertSame(['items'=>[['lager'=>1,'name'=>'']]], $this->service()->warehouses());
        } finally {
            foreach ($rows as $row) {
                self::sql("INSERT INTO grupper(art,kodenr,beskrivelse,fiscal_year) VALUES('LG',$1,$2,$3)", [$row['kodenr'],$row['beskrivelse'],$row['fiscal_year']]);
            }
        }
    }

    public function testOneThousandLinesCompleteWithinThirtySeconds(): void
    {
        $body = ['client_id'=>MobileAuth::uuid(),'session_id'=>MobileAuth::uuid(),'lager'=>2,'reason'=>'optaelling','lines'=>[]];
        for ($i = 0; $i < 1000; $i++) {
            $id = $this->item('0', false);
            $body['lines'][] = $this->body($id, '1')['lines'][0];
        }
        $started = microtime(true);
        $data = $this->submit($body);
        $seconds = microtime(true) - $started;
        self::assertSame(1000, $data['adjusted_count']);
        self::assertCount(1000, $data['lines']);
        self::assertLessThan(30, $seconds, '1000 lines took ' . $seconds . 's');
        fwrite(STDERR, sprintf("\nStock count: 1000 lines in %.3f s\n", $seconds));
    }
}
