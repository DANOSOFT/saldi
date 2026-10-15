<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/AttachmentMetadataEndpointTest.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Regress numeric amount exponent, precision and exact normalized storage.
// 20261007 CDX/LH Cover undated configuration outside the current fiscal year.

require_once __DIR__ . '/support/T3B2aTestCase.php';

final class AttachmentMetadataEndpointTest extends T3B2aTestCase
{
    public function testOnlyUndatedMetadataFallsBackToConfiguration(): void
    {
        $row = $this->fixture();
        RestApiEnv::rows(self::$conn, "UPDATE pool_files SET file_date='' WHERE id=$1", [$row['id']]);
        $row = $this->row((int)$row['id']);
        $years = RestApiEnv::rows(self::$conn, "SELECT id,box1,box2,box3,box4 FROM grupper WHERE art='RA'");
        RestApiEnv::rows(self::$conn, "UPDATE grupper SET box1='1',box2='2000',box3='12',box4='2000' WHERE art='RA'");
        try {
            $body = $this->body(['description'=>'Undated config']);
            $response = $this->patch($row,$body);
            self::assertSame(200,$response['status'],$response['body']);
            self::assertSame($response['json'],$this->patch($row,$body)['json']);
            $dated = $this->patch($this->row((int)$row['id']),$this->body(['date'=>'2026-10-07']));
            self::assertSame(422,$dated['status'],$dated['body']);
            self::assertSame('outside_fiscal_year',$dated['json']['data']['details']['reason']);
        } finally {
            foreach ($years as $year) {
                RestApiEnv::rows(self::$conn, 'UPDATE grupper SET box1=$1,box2=$2,box3=$3,box4=$4 WHERE id=$5',
                    [$year['box1'],$year['box2'],$year['box3'],$year['box4'],$year['id']]);
            }
        }
    }

    public function testOwnerEditReturnsT1RowAndNewValidatorWithAppAudit(): void
    {
        $row=$this->fixture();
        $request=RequestId::resolve(null);
        $response=$this->patch($row,$this->body(['vendor'=>"Supplier's shop",'description'=>'Corrected','amount'=>'1.234,56',
            'date'=>'14-09-2026','invoice_number'=>'INV-2','currency'=>'kr.','kontonr'=>987001,'vat_code'=>'K987']),
            array_merge(self::$ownerHeaders,['X-Request-Id: '.$request]));
        $this->assertSame(200,$response['status'],$response['body']);
        $data=$response['json']['data'];
        $this->assertSame('1.234,56',$data['metadata']['amount']);
        $this->assertSame('2026-09-14',$data['metadata']['date']);
        $this->assertSame('DKK',$data['metadata']['currency']);
        $this->assertSame('K987',$data['vat_code']);
        $this->assertSame('pending',$data['review_state']);
        $this->assertNotSame('"'.pool_review_version($row).'"',$data['etag']);
        $this->assertArrayNotHasKey('uploaded_by',$data);
        $events=RestApiEnv::rows(self::$conn,"SELECT * FROM pool_file_audit WHERE pool_file_id=$1 AND event='metadata_changed'",[$row['id']]);
        $this->assertNotEmpty($events);
        foreach ($events as $event) { $this->assertSame(self::$owner,$event['actor']); $this->assertSame($request,$event['request_id']); }
        $this->assertTrue(pool_audit_verify((int)$row['id']));
    }
    public function testStateOwnershipEtagsAndRequiredInputs(): void
    {
        $row=$this->fixture();
        $this->assertSame(404,$this->patch($row,$this->body(),self::$otherHeaders)['status']);
        $this->assertSame(404,$this->patch($row,$this->body(),self::$reviewerHeaders)['status']);
        $this->assertSame(412,$this->patch($row,$this->body(),null,'"stale"')['status']);
        $missing=RestApiEnv::http('PATCH','/restapi/endpoints/v1/attachment/metadata.php?id='.$row['id'],$this->body(),self::$ownerHeaders);
        $this->assertSame(422,$missing['status']);
        $this->assertSame('if_match_required',$missing['json']['data']['details']['reason']);
        $this->assertSame(422,$this->patch($row,[])['status']);
        foreach (['attached'=>409,'deleted'=>410] as $status=>$code) {
            $closed=$this->fixture($status);
            $response=$this->patch($closed,$this->body());
            $this->assertSame($code,$response['status'],$response['body']);
            $this->assertSame($code===410?'gone':'conflict',$response['json']['data']['error']);
        }
        $approved=$this->fixture('ubehandlet','approved');
        $this->assertSame(409,$this->patch($approved,$this->body())['status']);
        $staged=$this->fixture('staged');
        $this->assertSame(404,$this->patch($staged,$this->body())['status']);
    }
    public function testInvalidValuesRollBackMetadataAndAudit(): void
    {
        $row=$this->fixture();
        $count=count(pool_audit_read((int)$row['id'])['items']);
        foreach ([['amount'=>'bad'],['date'=>'31-02-2026'],['kontonr'=>'bad'],['vat_code'=>'unknown'],['description'=>[]],['resubmit'=>'true'],['status'=>'attached']] as $fields) {
            $response=$this->patch($row,$this->body(array_replace(['description'=>'Should roll back'],$fields)));
            $this->assertSame(422,$response['status'],$response['body']);
            $this->assertSame($row,$this->row((int)$row['id']));
            $this->assertSame($count,pool_audit_read((int)$row['id'])['total']);
        }
    }
    public function testNumericAmountsRejectExponentAndUnsupportedPrecisionWithoutWrites(): void
    {
        foreach ([1e-7, -1e-7, 1e20, 1.234, -1.234, 0.001, 12.3456, 0.1 + 0.2, 1000000000000] as $amount) {
            $row = $this->fixture();
            RestApiEnv::rows(self::$conn, 'UPDATE pool_files SET norm_amount=10 WHERE id=$1', [$row['id']]);
            $row = $this->row((int)$row['id']);
            $count = pool_audit_read((int)$row['id'])['total'];
            $response = $this->patch($row, $this->body(['amount'=>$amount]));
            $this->assertSame(422, $response['status'], $response['body']);
            $this->assertSame('validation_failed', $response['json']['data']['error']);
            $this->assertSame(['reason'=>'metadata_invalid','field'=>'amount'], $response['json']['data']['details']);
            $this->assertSame('10.000', $this->row((int)$row['id'])['norm_amount']);
            $this->assertSame($row, $this->row((int)$row['id']));
            $this->assertSame($count, pool_audit_read((int)$row['id'])['total']);
        }
    }
    public function testSupportedNumericAndInvoiceTextAmountsStoreExactNormalizedValues(): void
    {
        foreach ([[0, '0.000'], [1234, '1234.000'], [1234.56, '1234.560'], [-12.34, '-12.340'],
            [1e-2, '0.010'], [-1e-2, '-0.010'], [1.2e3, '1200.000'], [0.1, '0.100'],
            [999999999999, '999999999999.000'], [999999999999.99, '999999999999.990'],
            [-999999999999.99, '-999999999999.990'], ['1.234', '1234.000'], ['1.234,56', '1234.560']] as [$amount,$expected]) {
            $row = $this->fixture();
            $response = $this->patch($row, $this->body(['amount'=>$amount]));
            $this->assertSame(200, $response['status'], $response['body']);
            $this->assertSame($expected, $this->row((int)$row['id'])['norm_amount']);
            $this->assertTrue(pool_audit_verify((int)$row['id']));
        }
    }
    public function testReplayReturnsStoredResponseAndChangedBodyConflicts(): void
    {
        $row=$this->fixture();
        $body=$this->body(['description'=>'Once']);
        $response=$this->patch($row,$body);
        $this->assertSame(200,$response['status'],$response['body']);
        $before=pool_audit_read((int)$row['id'])['total'];
        $replay=$this->patch($row,$body);
        $this->assertSame($response['json'],$replay['json']);
        $this->assertSame($before,pool_audit_read((int)$row['id'])['total']);
        $serviceReplay=$this->service()->update((int)$row['id'],$body,'"stale"',$body['client_id']);
        $this->assertSame(['Idempotent-Replayed'=>'true'],$serviceReplay['headers']);
        $body['description']='Different';
        $conflict=$this->patch($row,$body);
        $this->assertSame(409,$conflict['status']);
        $this->assertSame('client_id_reused',$conflict['json']['data']['details']['reason']);
    }
    public function testResubmitCreatesOneTargetedReviewerNotificationOnReplay(): void
    {
        $row=$this->fixture('ubehandlet','rejected');
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET reviewed_by=$1,reviewed_at=now(),rejection_code='other',rejection_note='Please fix' WHERE id=$2",[self::$reviewer,$row['id']]);
        $row=$this->row((int)$row['id']);
        $body=$this->body(['description'=>'Fixed','resubmit'=>true]);
        $response=$this->patch($row,$body);
        $this->assertSame(200,$response['status'],$response['body']);
        $this->assertSame('pending',$response['json']['data']['review_state']);
        $this->assertNull($response['json']['data']['rejection']);
        $this->assertNotNull($this->row((int)$row['id'])['resubmitted_at']);
        $this->assertSame($response['json'],$this->patch($row,$body)['json']);
        $events=RestApiEnv::rows(self::$conn,"SELECT * FROM mobile_events WHERE ref_id=$1 AND type='attachment_resubmitted'",[$row['id']]);
        $this->assertCount(1,$events);
        $this->assertSame(self::$reviewer,$events[0]['target_subject']);
        $this->assertSame(['name'=>RestApiEnv::user(),'description'=>'Fixed'],json_decode($events[0]['payload'],true));
        $this->assertCount(1,array_filter(pool_audit_read((int)$row['id'])['items'],static fn($event)=>$event['event']==='resubmitted'));
        // Verify the concurrent fan-out implementation when installed, with no provider calls.
        if (is_file(__DIR__.'/../../restapi/services/MobileEventFanout.php')) {
            require_once __DIR__.'/../../restapi/services/MobileEventFanout.php';
            $registry=RestApiEnv::connect(RestApiEnv::masterDb());
            try {
                $fanout=new MobileEventFanout(self::$conn,$registry,RestApiEnv::regnskabId(RestApiEnv::accountOpen()));
                $this->assertSame(0,$fanout->run()['failed']);
                $this->assertSame(0,$fanout->run()['failed']);
            } finally { pg_close($registry); }
            $notifications=RestApiEnv::rows(self::$conn,"SELECT r.subject FROM notifications n JOIN notification_recipients r ON r.notification_id=n.id WHERE n.type='attachment_resubmitted' AND n.ref_id=$1",[$row['id']]);
            $this->assertSame([['subject'=>self::$reviewer]],$notifications);
        }
    }
    public function testVendorOnlyRematchInvalidatesOwnerEdit(): void
    {
        $row=$this->fixture();
        if (!in_array('vendor_name',array_column(RestApiEnv::rows(self::$conn,"SELECT column_name FROM information_schema.columns WHERE table_name='pool_files'"),'column_name'),true)) {
            RestApiEnv::rows(self::$conn,'ALTER TABLE pool_files ADD COLUMN vendor_name text');
        }
        RestApiEnv::rows(self::$conn,'UPDATE pool_files SET vendor_name=$1 WHERE id=$2',['Rematched',$row['id']]);
        $this->assertSame(412,$this->patch($row,$this->body())['status']);
    }
}
