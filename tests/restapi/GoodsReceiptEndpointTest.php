<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/GoodsReceiptEndpointTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Verify the installed receipt capability through auth/me.
// 20261001 CDX/LH Cover legacy tenant selection and receipts across an unconfigured year boundary.
// 20261006 CL/LH B3 attaches the delivery note after the stock commit; a missing file now reports failed, not pending.


use PHPUnit\Framework\Attributes\DataProvider;
require_once __DIR__ . '/support/T4ReceiptTestCase.php';

final class GoodsReceiptEndpointTest extends T4ReceiptTestCase
{
    public function testReadsKeepCurrentFiscalYearWhenFollowingYearIsConfigured(): void
    {
        $nextYear = (int)(new DateTimeImmutable('now',new DateTimeZone('Europe/Copenhagen')))->format('Y') + 1;
        $yearId = (int)$this->rows("INSERT INTO grupper(art,kodenr,box1,box2,box3,box4)
            VALUES('RA',$1,'01',$2,'12',$2) RETURNING id",[self::$year + 1,(string)$nextYear])[0]['id'];
        try {
            // The future year has no product group; reads must retain today's tracking configuration.
            self::assertTrue($this->detail()['lines'][0]['receivable']);
        } finally {
            $this->rows('DELETE FROM grupper WHERE id=$1',[$yearId]);
        }
    }

    public function testLegacyTokenUsesTenantHeaderForOrderHistoryAndReceive(): void
    {
        if (($reason = RestApiEnv::installSecretUnavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        $token = RestApiEnv::signToken(['user_id'=>(int)self::$user['id'],
            'username'=>RestApiEnv::user(),'type'=>'access']);
        $headers = ['Authorization: Bearer ' . $token,
            'X-Tenant-ID: ' . RestApiEnv::regnskabId(RestApiEnv::accountOpen())];
        $list = $this->request('GET','orders.php',null,null,$headers);
        self::assertSame(200,$list['status'],$list['body']);
        $detail = $this->request('GET','orders.php?id=' . $this->f['order'],null,null,$headers);
        self::assertSame(200,$detail['status'],$detail['body']);
        $response = $this->request('POST','index.php',$this->body('2'),$detail['json']['data']['etag'],$headers);
        self::assertSame(201,$response['status'],$response['body']);
        $history = $this->request('GET','index.php?order_id=' . $this->f['order'],null,null,$headers);
        self::assertSame(200,$history['status'],$history['body']);
        self::assertSame($response['json']['data']['receipt_id'],$history['json']['data']['items'][0]['receipt_id']);
        self::assertSame('2',$this->detail()['lines'][0]['received']);
    }

    public function testBackdatedReceiptReadsAndReplayWithoutNewYearConfiguration(): void
    {
        $years = $this->rows("SELECT id,box1,box2,box3,box4 FROM grupper WHERE art='RA'");
        $previousYear = (int)(new DateTimeImmutable('now',new DateTimeZone('Europe/Copenhagen')))->format('Y') - 1;
        $date = $previousYear . '-12-31';
        try {
            $this->rows("UPDATE grupper SET box1='01',box2=$1,box3='12',box4=$1 WHERE art='RA'",[(string)$previousYear]);
            $this->rows('UPDATE ordrer SET ordredate=$1,levdate=$2 WHERE id=$3',
                [$previousYear . '-12-01',$date,$this->f['order']]);
            $list = $this->request('GET','orders.php');
            self::assertSame(200,$list['status'],$list['body']);
            $detail = $this->detail();
            self::assertTrue($detail['lines'][0]['receivable']);
            $body = $this->body('2');
            $body['receipt_date'] = $date;
            $response = $this->send($body,$detail['etag']);
            self::assertSame(201,$response['status'],$response['body']);
            self::assertSame($date,$this->rows('SELECT kobsdate FROM batch_kob WHERE linje_id=$1',[$this->f['line']])[0]['kobsdate']);
            $history = $this->request('GET','index.php?order_id=' . $this->f['order']);
            self::assertSame(200,$history['status'],$history['body']);
            self::assertSame($date,$history['json']['data']['items'][0]['date']);
            self::assertSame('2',$this->detail()['lines'][0]['received']);

            $invalid = $body;
            $invalid['client_id'] = MobileAuth::uuid();
            $invalid['receipt_date'] = ($previousYear + 1) . '-01-01';
            $this->refused($this->send($invalid),422,'outside_fiscal_year');
            $invalid['receipt_date'] = 'invalid';
            $this->refused($this->send($invalid),422,'date_missing');

            // Replay must not need a fiscal year, a current order state or an If-Match header.
            $this->rows("UPDATE grupper SET art='RF' WHERE art='RA'");
            $this->rows('UPDATE ordrer SET status=3 WHERE id=$1',[$this->f['order']]);
            $replay = $this->request('POST','index.php',$body);
            self::assertSame(201,$replay['status'],$replay['body']);
            self::assertSame($response['json'],$replay['json']);
            self::assertStringContainsString('Idempotent-Replayed: true',$replay['headers']);
            self::assertCount(1,$this->rows('SELECT id FROM mobile_goods_receipts WHERE ordre_id=$1',[$this->f['order']]));
            self::assertCount(1,$this->rows('SELECT id FROM batch_kob WHERE linje_id=$1',[$this->f['line']]));
        } finally {
            foreach ($years as $year) {
                $this->rows("UPDATE grupper SET art='RA',box1=$1,box2=$2,box3=$3,box4=$4 WHERE id=$5",
                    [$year['box1'],$year['box2'],$year['box3'],$year['box4'],$year['id']]);
            }
        }
    }

    public function testInstalledGoodsReceiptCapability(): void
    {
        $me = RestApiEnv::http('GET', '/restapi/endpoints/v1/auth/me.php', null, self::$headers);
        self::assertSame(200, $me['status'], $me['body']);
        self::assertTrue($me['json']['data']['capabilities']['goods_receipt']);
    }

    public function testOpenOrdersAndDetailIncludeWarehouseBarcodesAndReceivedBatchSum(): void
    {
        $this->rows('UPDATE ordrelinjer SET leveret=99 WHERE id=$1',[$this->f['line']]);
        $list = $this->request('GET','orders.php');
        self::assertSame(200,$list['status'],$list['body']);
        self::assertSame(['items','total','limit','offset'],array_keys($list['json']['data']));
        $detail = $this->detail();
        self::assertStringContainsString('ETag: ' . $detail['etag'],$this->request('GET','orders.php?id=' . $this->f['order'])['headers']);
        self::assertSame(['kontonr'=>'1234','name'=>'Receipt supplier'],$detail['kreditor']);
        $line = $detail['lines'][0];
        self::assertSame(['10','0','10'],[$line['ordered'],$line['received'],$line['remaining']]);
        self::assertSame(2,$line['lager']);
        self::assertSame('Receipt warehouse',$line['lager_name']);
        self::assertContains('ALT',$line['barcodes']);
        self::assertContains('5701234567890',$line['barcodes']);
        self::assertTrue($line['receivable']);
        self::assertSame(['serial'=>false,'batch_no'=>false,'due_date'=>false],$line['tracking']);
        $this->rows('INSERT INTO batch_kob(vare_id,linje_id,ordre_id,antal,rest,lager) VALUES($1,$2,$3,10,10,2)',
            [$this->f['item'],$this->f['line'],$this->f['order']]);
        $items = $this->request('GET','orders.php')['json']['data']['items'];
        self::assertNotContains($this->f['order'],array_column($items,'order_id'));
        self::assertSame('0',$this->detail()['lines'][0]['remaining']);
    }

    public static function goldens(): array
    {
        return array_map(static fn(string $id): array=>[$id],['S7','S8','S9','S10','S11']);
    }

    #[DataProvider('goldens')]
    public function testGoldenReceiptMatchesEquivalentWebActionAndAbsoluteExpectations(string $id): void
    {
        $gold = json_decode(file_get_contents(__DIR__ . '/fixtures/t4/golden/' . $id . '.json'),true,512,JSON_THROW_ON_ERROR);
        $web = $this->fixture();
        $app = $this->f;
        $this->rows("INSERT INTO settings(var_name,var_grp,var_value) VALUES('confirmStockChange','items','on')");
        foreach ([$web,$app] as $f) {
            if ($id === 'S9') {
                $this->rows("UPDATE grupper SET box9='on' WHERE art='VG' AND kodenr=98282");
                $f = $this->salesReservation($f);
                if ($f['order'] === $app['order']) {
                    $app = $f;
                } else {
                    $web = $f;
                }
            } elseif ($id === 'S10') {
                $this->rows('UPDATE varer SET has_due_date=true WHERE id=$1',[$f['item']]);
            } elseif ($id === 'S11') {
                $variant = (int)$this->rows("INSERT INTO variant_varer(vare_id,variant_beholdning,variant_text,variant_stregkode) VALUES($1,2,'Blue','5700000000011') RETURNING id",[$f['item']])[0]['id'];
                $this->rows('UPDATE ordrelinjer SET variant_id=$1 WHERE id=$2',[$variant,$f['line']]);
                if ($f['order'] === $app['order']) {
                    $app['variant'] = $variant;
                } else {
                    $web['variant'] = $variant;
                }
            }
        }
        $ledger = $this->ledger();
        $this->f = $app;
        foreach ($gold['quantities'] as $quantity) {
            $appBody = $this->body($quantity);
            $webBody = $appBody;
            $webBody['order_id'] = $web['order'];
            $webBody['lines'][0]['line_id'] = $web['line'];
            if ($id === 'S9' || $id === 'S10') {
                $appBody['lines'][0]['batch_no'] = $webBody['lines'][0]['batch_no'] = '0';
            }
            if ($id === 'S10') {
                $appBody['lines'][0]['due_date'] = $webBody['lines'][0]['due_date'] = '2026-12-01';
            }
            $out = $this->web($web,$webBody);
            self::assertSame(PGSQL_TRANSACTION_IDLE,$out['transaction']);
            $response = $this->send($appBody);
            self::assertSame(201,$response['status'],$response['body']);
            self::assertSame($quantity,$response['json']['data']['lines'][0]['received_now']);
            self::assertSame('not_configured',$response['json']['data']['shop_sync']);
            self::assertSame($ledger,$this->ledger(),'Receipt must have no ledger effect');
            self::assertSame($this->stock($web),$this->stock($app),'Golden web/app business rows must match');
        }
        $actual = $this->stock($app);
        self::assertSame($gold['item_stock'],$actual['varer'][0]['beholdning']);
        self::assertSame($gold['warehouse_stock'],$actual['lagerstatus'][1]['beholdning']);
        self::assertSame($gold['batch_quantities'],array_column(array_slice($actual['batch_kob'],1),'antal'));
        self::assertSame($gold['batch_rests'],array_column(array_slice($actual['batch_kob'],1),'rest'));
        self::assertSame($gold['remaining'],$this->detail()['lines'][0]['remaining']);
        self::assertSame($gold['complete'],$response['json']['data']['order_complete']);
        self::assertSame('0.000',$actual['ordrelinjer'][0]['leveres']);
        self::assertCount(count($gold['quantities']),$actual['stocklog']);
        self::assertSame($gold['due_date'],$actual['batch_kob'][1]['due_date']);
        self::assertSame($gold['batch_no'],$actual['batch_kob'][1]['batch_no']);
        self::assertSame($gold['variant_stock'],$actual['variant_varer'][0]['variant_beholdning'] ?? null);
        $history = $this->request('GET','index.php?order_id=' . $app['order']);
        self::assertSame(200,$history['status'],$history['body']);
        self::assertSame(count($gold['quantities']),$history['json']['data']['total']);
        self::assertSame(RestApiEnv::user(),$history['json']['data']['items'][0]['by_name']);
        self::assertSame(count($gold['quantities']),count($this->detail()['receipts']));
    }

    /** @return array A delivered sale reserves its placeholder purchase batch before the goods arrive. */
    private function salesReservation(array $f): array
    {
        $f['sale_order'] = (int)$this->rows("INSERT INTO ordrer(art,status,ordrenr,ordredate) VALUES('DO',2,98283,'2026-10-01') RETURNING id")[0]['id'];
        $f['sale_line'] = (int)$this->rows("INSERT INTO ordrelinjer(ordre_id,vare_id,varenr,posnr,antal,lager,variant_id)
            SELECT $1,id,varenr,1,2,2,0 FROM varer WHERE id=$2 RETURNING id",[$f['sale_order'],$f['item']])[0]['id'];
        $batch = (int)$this->rows("INSERT INTO batch_kob(vare_id,variant_id,linje_id,ordre_id,antal,rest,lager)
            VALUES($1,0,$2,$3,0,-2,2) RETURNING id",[$f['item'],$f['line'],$f['order']])[0]['id'];
        $sale = (int)$this->rows('INSERT INTO batch_salg(vare_id,variant_id,linje_id,ordre_id,antal,batch_kob_id,lager)
            VALUES($1,0,$2,$3,2,$4,2) RETURNING id',[$f['item'],$f['sale_line'],$f['sale_order'],$batch])[0]['id'];
        $this->rows('INSERT INTO reservation(linje_id,batch_kob_id,batch_salg_id,vare_id,antal,lager)
            VALUES($1,$2,$3,$4,2,2)',[$f['line'],$batch,$sale,$f['item']]);
        $this->rows('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES($1,0,2,-2)',[$f['item']]);
        $this->rows('UPDATE varer SET beholdning=2 WHERE id=$1',[$f['item']]);
        return $f;
    }

    public function testReplayBeforeStaleValidatorNeverReceivesOrSyncsTwice(): void
    {
        $body = $this->body();
        $etag = $this->detail()['etag'];
        $first = $this->send($body,$etag);
        self::assertSame(201,$first['status'],$first['body']);
        $stock = $this->stock($this->f);
        $replay = $this->send($body,$etag);
        self::assertSame(201,$replay['status'],$replay['body']);
        self::assertSame($first['json'],$replay['json']);
        self::assertStringContainsString('Idempotent-Replayed: true',$replay['headers']);
        self::assertSame($stock,$this->stock($this->f));
        $body['lines'][0]['quantity'] = '5';
        $this->refused($this->send($body,$etag),409,'client_id_reused','conflict');
        $new = $this->body('1');
        $this->refused($this->send($new,$etag),412,'','precondition_failed');
        $this->refused($this->request('POST','index.php',$new),422,'if_match_required');
        self::assertSame($stock,$this->stock($this->f));
    }

    public function testOverReceiptSerialAndMissingBatchRefusalsWriteNothing(): void
    {
        $before = $this->stock($this->f);
        $this->refused($this->send($this->body('10.001')),422,'over_receipt');
        $this->rows("UPDATE ordrelinjer SET serienr='on' WHERE id=$1",[$this->f['line']]);
        self::assertSame('serial',$this->detail()['lines'][0]['why']);
        $this->refused($this->send($this->body()),422,'serial_numbers_required');
        $this->rows("UPDATE ordrelinjer SET serienr='' WHERE id=$1",[$this->f['line']]);
        $this->rows('UPDATE varer SET has_due_date=true WHERE id=$1',[$this->f['item']]);
        $body = $this->body();
        $this->refused($this->send($body),422,'batch_info_missing');
        $body['lines'][0]['batch_no'] = '0';
        $this->refused($this->send($body),422,'batch_info_missing');
        $body['lines'][0]['due_date'] = '2026-02-30';
        $this->refused($this->send($body),422,'batch_info_missing');
        self::assertSame($before,$this->stock($this->f));
        self::assertSame([], $this->rows('SELECT * FROM mobile_goods_receipts WHERE ordre_id=$1',[$this->f['order']]));
    }

    public function testWebLeaseAndPreparedOtherLinesRefusedWithoutTouchingEitherLine(): void
    {
        $this->rows("UPDATE ordrer SET hvem='other',tidspkt=extract(epoch FROM now())::bigint WHERE id=$1",[$this->f['order']]);
        self::assertSame('other',$this->detail()['locked_by']);
        $this->refused($this->send($this->body()),409,'order_locked','conflict');
        $this->rows('UPDATE ordrer SET hvem=$1 WHERE id=$2',[RestApiEnv::user(),$this->f['order']]);
        $other = $this->fixture();
        $this->rows('UPDATE ordrelinjer SET ordre_id=$1,posnr=2,leveres=2 WHERE id=$2',[$this->f['order'],$other['line']]);
        $this->refused($this->send($this->body()),409,'receipt_prepared_in_web','conflict');
        self::assertSame('2.000',$this->rows('SELECT leveres FROM ordrelinjer WHERE id=$1',[$other['line']])[0]['leveres']);
        self::assertSame('0.000',$this->rows('SELECT leveres FROM ordrelinjer WHERE id=$1',[$this->f['line']])[0]['leveres']);
        $this->rows('UPDATE ordrelinjer SET leveres=0 WHERE id=$1',[$other['line']]);
        $response = $this->send($this->body());
        self::assertSame(201,$response['status'],$response['body']);
    }

    public function testClosedWrongKindAndInvalidDates(): void
    {
        $before = $this->stock($this->f);
        $this->rows('UPDATE ordrer SET status=3 WHERE id=$1',[$this->f['order']]);
        $this->refused($this->send($this->body()),422,'order_closed');
        $this->rows("UPDATE ordrer SET status=1,art='DO' WHERE id=$1",[$this->f['order']]);
        $this->refused($this->send($this->body(),'"anything"'),404,'','not_found');
        $this->rows("UPDATE ordrer SET art='KO' WHERE id=$1",[$this->f['order']]);
        foreach (['2026-02-30'=>'date_missing','2026-09-30'=>'delivery_date_before_order','2027-01-01'=>'outside_fiscal_year'] as $date=>$reason) {
            $body = $this->body();
            $body['receipt_date'] = $date;
            $this->refused($this->send($body),422,$reason);
        }
        self::assertSame($before,$this->stock($this->f));
    }

    public function testLineAndQuantityValidationCannotReceiveForeignDuplicateOrMalformedLines(): void
    {
        $foreign = $this->fixture();
        $before = $this->stock($this->f);
        foreach (['0','-1','1.0001','1e1','01',6,[],true,'9999999999999'] as $quantity) {
            $body = $this->body();
            $body['lines'][0]['quantity'] = $quantity;
            $this->refused($this->send($body),422,'quantity_invalid');
        }
        $body = $this->body();
        $body['lines'][0]['line_id'] = $foreign['line'];
        $this->refused($this->send($body),422,'invalid_line');
        $body = $this->body();
        $body['lines'][] = $body['lines'][0];
        $this->refused($this->send($body),422,'invalid_line');
        self::assertSame($before,$this->stock($this->f));
    }

    public function testReceiptRollbackOnEngineReservationFailureLeavesStockAndAuditUntouched(): void
    {
        $this->rows("UPDATE grupper SET box9='on' WHERE art='VG' AND kodenr=98282");
        $this->rows('INSERT INTO reservation(linje_id,batch_kob_id,batch_salg_id,vare_id,antal,lager) VALUES($1,0,-99,$2,7,2)',[$this->f['line'],$this->f['item']]);
        $before = $this->stock($this->f);
        $body = $this->body();
        $body['lines'][0]['batch_no'] = 'BATCH';
        $this->refused($this->send($body),422,'reservations_exceed_receipt');
        self::assertSame($before,$this->stock($this->f));
        self::assertSame([], $this->rows('SELECT * FROM mobile_goods_receipts WHERE ordre_id=$1',[$this->f['order']]));
        self::assertSame([], $this->rows('SELECT * FROM mobile_idempotency WHERE client_id=$1',[$body['client_id']]));
    }

    public function testNonStockReceiptAndLargeDecimalQuantitiesAreExact(): void
    {
        $this->rows("UPDATE grupper SET box8='' WHERE art='VG' AND kodenr=98282");
        $response = $this->send($this->body('2.125'));
        self::assertSame(201,$response['status'],$response['body']);
        self::assertSame('2.125',$response['json']['data']['lines'][0]['received_total']);
        self::assertSame('4.000',$this->rows('SELECT beholdning FROM varer WHERE id=$1',[$this->f['item']])[0]['beholdning']);
        self::assertSame(null,$this->rows('SELECT rest FROM batch_kob WHERE linje_id=$1',[$this->f['line']])[0]['rest']);
        $this->rows("UPDATE grupper SET box8='on' WHERE art='VG' AND kodenr=98282");
        $this->f = $this->fixture();
        $this->rows('UPDATE varer SET beholdning=0 WHERE id=$1',[$this->f['item']]);
        $this->rows('UPDATE lagerstatus SET beholdning=0 WHERE vare_id=$1',[$this->f['item']]);
        $this->rows('UPDATE batch_kob SET antal=0,rest=0 WHERE vare_id=$1',[$this->f['item']]);
        $this->rows('UPDATE ordrelinjer SET antal=999999999999.999 WHERE id=$1',[$this->f['line']]);
        $response = $this->send($this->body('123456789012.345'));
        self::assertSame(201,$response['status'],$response['body']);
        self::assertSame('876543210987.654',$response['json']['data']['lines'][0]['remaining']);
        self::assertSame('123456789012.345',$response['json']['data']['lines'][0]['received_total']);
        self::assertSame('123456789012.345',$this->rows('SELECT quantity FROM mobile_goods_receipt_lines WHERE ordrelinje_id=$1',[$this->f['line']])[0]['quantity']);
    }

    public function testPreparedRequestedLinesAreOverwrittenAndMultiLineReceiptIsAtomic(): void
    {
        $other = $this->fixture();
        $this->rows('UPDATE ordrelinjer SET ordre_id=$1,posnr=2,leveres=9 WHERE id=$2',[$this->f['order'],$other['line']]);
        $this->rows('UPDATE ordrelinjer SET leveres=9 WHERE id=$1',[$this->f['line']]);
        $body = $this->body('4');
        $body['lines'][] = ['line_id'=>$other['line'],'quantity'=>'3'];
        $response = $this->send($body);
        self::assertSame(201,$response['status'],$response['body']);
        self::assertSame(['4','3'],array_column($response['json']['data']['lines'],'received_now'));
        self::assertSame(['6','7'],array_column($this->detail()['lines'],'remaining'));
        self::assertSame(['0.000','0.000'],array_column($this->rows('SELECT leveres FROM ordrelinjer WHERE ordre_id=$1 ORDER BY posnr',[$this->f['order']]),'leveres'));
        self::assertCount(2,$this->rows('SELECT * FROM mobile_goods_receipt_lines WHERE receipt_id=$1',[$response['json']['data']['receipt_id']]));
    }

    public function testClientIdIsAlsoProtectedAgainstCrossSubjectAuditCollisions(): void
    {
        $body = $this->body();
        self::assertSame(201,$this->send($body)['status']);
        $user = $this->rows('INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,true) RETURNING id',
            ['receipt_alias_' . bin2hex(random_bytes(4)),md5(TestCredentials::password('receipt_alias')),str_repeat('1',16)])[0];
        $id = (int)$user['id'];
        $service = new GoodsReceiptService(new EngineContext(self::$conn,RestApiEnv::testDb(),
            RestApiEnv::regnskabId(RestApiEnv::accountOpen()),'receipt_alias',$id,'2026-10-06'),Subject::build('account',$id));
        try {
            $service->receive($body,$this->detail()['etag']);
            self::fail('Audit UUID collision must be a conflict');
        } catch (MobileT2Error $error) {
            self::assertSame(409,$error->status);
            self::assertSame('client_id_reused',$error->details['reason']);
        }
        self::assertSame('6',$this->detail()['lines'][0]['received']);
    }

    public function testDeliveryNoteMustBeOwnPendingAndMissingFilePreservesReceipt(): void
    {
        $attachment = (int)$this->rows("INSERT INTO pool_files(filename,uploaded_by,status,review_state)
            VALUES($1,$2,'ubehandlet','pending') RETURNING id",[MobileAuth::uuid() . '.pdf',self::$subject])[0]['id'];
        $body = $this->body();
        $body['attachment_id'] = $attachment;
        $this->rows('UPDATE pool_files SET uploaded_by=$1 WHERE id=$2',[Subject::build('account',2147483647),$attachment]);
        $this->refused($this->send($body),404,'','not_found');
        $this->rows("UPDATE pool_files SET uploaded_by=$1,review_state='approved' WHERE id=$2",[self::$subject,$attachment]);
        $this->refused($this->send($body),409,'attachment_processed','conflict');
        $this->rows("UPDATE pool_files SET review_state='pending' WHERE id=$1",[$attachment]);
        $response = $this->send($body);
        self::assertSame(201,$response['status'],$response['body']);
        self::assertSame('failed',$response['json']['data']['attachment']['state']);
        self::assertSame($attachment,$response['json']['data']['attachment']['attachment_id']);
        // The pool file has no bytes on disk, so the post-commit attach fails without a domain reason.
        self::assertSame('internal',$response['json']['data']['attachment']['reason']);
        self::assertSame(['attachment_pool_file_id'=>(string)$attachment,'attachment_state'=>'failed'],
            $this->rows('SELECT attachment_pool_file_id,attachment_state FROM mobile_goods_receipts WHERE ordre_id=$1',[$this->f['order']])[0]);
        self::assertSame('ubehandlet',$this->rows('SELECT status FROM pool_files WHERE id=$1',[$attachment])[0]['status']);
        self::assertSame('6',$this->detail()['lines'][0]['received']);
    }

    public function testDefaultDateAndExpiredWebLeaseUseTheWebRules(): void
    {
        $this->rows("UPDATE ordrer SET hvem='other',tidspkt=extract(epoch FROM now())::bigint-3601 WHERE id=$1",[$this->f['order']]);
        self::assertNull($this->detail()['locked_by']);
        $body = $this->body('1');
        unset($body['receipt_date']);
        $response = $this->send($body);
        self::assertSame(201,$response['status'],$response['body']);
        $today = (new DateTimeImmutable('now',new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
        self::assertSame($today,$this->rows('SELECT levdate FROM ordrer WHERE id=$1',[$this->f['order']])[0]['levdate']);
        self::assertSame($today,$this->rows('SELECT receipt_date FROM mobile_goods_receipts WHERE ordre_id=$1',[$this->f['order']])[0]['receipt_date']);
    }

    public function testRightsAnonymousOwnReceiptUserAndReadOnlyCreditorOrderUser(): void
    {
        $this->refused($this->request('GET','orders.php',null,null,[]),401,'','invalid_token');
        $this->refused($this->request('POST','index.php',$this->body(),null,[]),401,'','invalid_token');
        $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2',[str_repeat('0',16),self::$user['id']]);
        $this->refused($this->request('GET','orders.php'),403,'','permission_denied');
        $this->refused($this->request('GET','index.php?order_id=' . $this->f['order']),403,'','permission_denied');
        $digits = str_repeat('0',16);
        $digits[7] = '2';
        $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2',[$digits,self::$user['id']]);
        $detail = $this->detail();
        self::assertSame(200,$this->request('GET','index.php?order_id=' . $this->f['order'])['status']);
        $this->refused($this->send($this->body(),$detail['etag']),403,'','permission_denied');
    }
}
