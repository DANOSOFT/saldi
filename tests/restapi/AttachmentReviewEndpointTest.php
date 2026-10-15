<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/AttachmentReviewEndpointTest.php --- ver 5.0.0 --- 2026.10.05
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

require_once __DIR__ . '/support/T3B2cTestCase.php';

final class AttachmentReviewEndpointTest extends T3B2cTestCase
{
    public function testQueuePagingTotalsWebRowsAndNamesOnly(): void
    {
        $before = $this->reviewService()->queue([])['total'];
        $app = $this->reviewFixture();
        $web = $this->reviewFixture(false);
        $this->reviewFixture(true, 'staged');
        $this->reviewFixture(true, 'attached', 'approved');
        $this->reviewFixture(true, 'deleted');
        $this->reviewFixture(true, 'ubehandlet', 'rejected');
        $response = RestApiEnv::http('GET', '/restapi/endpoints/v1/attachment/review.php?limit=1&offset=0', null, self::$reviewerHeaders);
        $this->assertSame(200, $response['status'], $response['body']);
        $first = $response['json']['data'];
        $this->assertSame($before+2, $first['total']);
        $this->assertSame(1, $first['limit']);
        $this->assertCount(1, $first['items']);
        $this->assertSame((int)$web['id'], $first['items'][0]['id']);
        $this->assertSame('web', $first['items'][0]['source']);
        $this->assertNull($first['items'][0]['uploaded_by']);
        $second = RestApiEnv::http('GET', '/restapi/endpoints/v1/attachment/review.php?limit=1&offset=1', null, self::$reviewerHeaders)['json']['data'];
        $this->assertSame($first['total'], $second['total']);
        $item = $second['items'][0];
        $this->assertSame((int)$app['id'], $item['id']);
        $this->assertSame(['name'=>RestApiEnv::user(), 'is_me'=>false], $item['uploaded_by']);
        $this->assertSame('app', $item['source']);
        $this->assertSame('1234.56', $item['amount']);
        $this->assertStringNotContainsString(self::$owner, json_encode($item));
        foreach (['storage_path','uploaded_by_subject','reviewed_by'] as $field) { $this->assertArrayNotHasKey($field, $item); }
        $this->assertSame([], $this->reviewService()->queue(['offset'=>100000])['items']);
        foreach (['state=bad','limit=0','offset=-1','state[]=pending'] as $query) {
            $this->assertSame(422, RestApiEnv::http('GET', '/restapi/endpoints/v1/attachment/review.php?' . $query, null, self::$reviewerHeaders)['status']);
        }
    }

    public function testDetailIncludesStoredSuggestionBankKreditorAndSafeAudit(): void
    {
        $row = $this->reviewFixture();
        $columns = array_column(RestApiEnv::rows(self::$conn, "SELECT column_name FROM information_schema.columns WHERE table_name='pool_files'"), 'column_name');
        foreach (['vendor_name'=>'text','vendor_cvr'=>'text','vendor_konto_id'=>'integer','vendor_match'=>'varchar(20)','vendor_score'=>'numeric'] as $name=>$type) {
            if (!in_array($name, $columns, true)) { RestApiEnv::rows(self::$conn, "ALTER TABLE pool_files ADD COLUMN $name $type"); }
        }
        $vendor = RestApiEnv::rows(self::$conn, "INSERT INTO adresser(art,kontonr,firmanavn,lukket) VALUES ('K','88012','Matched vendor','') RETURNING id")[0];
        RestApiEnv::rows(self::$conn, "UPDATE pool_files SET vendor_konto_id=$1,vendor_match='cvr',vendor_score=1,vendor_name='Matched vendor' WHERE id=$2", [$vendor['id'],$row['id']]);
        RestApiEnv::rows(self::$conn, "INSERT INTO classification_suggestions(pool_file_id,inputs_sha256,kontonr,vat_code,basis,engine_version,requested_by)
            VALUES ($1,$2,987001,'K987','ocr','det-1',$3)", [$row['id'],hash('sha256','b2c-suggestion'),self::$owner]);
        $journal = (int)RestApiEnv::rows(self::$conn,"SELECT coalesce(max(id),0)+1 AS id FROM kladdeliste")[0]['id'];
        RestApiEnv::rows(self::$conn,"INSERT INTO kladdeliste(id,bogfort,kladdenote) VALUES ($1,'-','Bank fixture')",[$journal]);
        $line = RestApiEnv::rows(self::$conn,"INSERT INTO kassekladde(kladde_id,bilag,transdate,amount) VALUES ($1,71,'2026-09-14',1234.56) RETURNING id",[$journal])[0];
        $import = RestApiEnv::rows(self::$conn,"INSERT INTO bank_line_imports(source,account_kontonr,kladde_id,imported_by) VALUES ('csv',6471,$1,$2) RETURNING id",[$journal,self::$reviewer])[0];
        $bank = RestApiEnv::rows(self::$conn,"INSERT INTO bank_lines(account_kontonr,line_key,source,transdate,amount,currency,text,first_import_id)
            VALUES (6471,$1,'csv','2026-09-14',-1234.56,'DKK','Bank text',$2) RETURNING id",[hash('sha256','b2c-bank'),$import['id']])[0];
        RestApiEnv::rows(self::$conn,'UPDATE pool_files SET bank_line_id=$1 WHERE id=$2',[$bank['id'],$row['id']]);
        RestApiEnv::rows(self::$conn,"INSERT INTO mobile_journal_lines(kassekladde_id,kladde_id,pool_file_id,bank_line_id,mode,currency,created_by,client_id)
            VALUES ($1,$2,$3,$4,'bank_row','DKK',$5,$6)",[$line['id'],$journal,$row['id'],$bank['id'],self::$reviewer,RequestId::resolve(null)]);
        RestApiEnv::rows(self::$conn,"INSERT INTO documents(source,source_id,pool_file_id,filename) VALUES ('kassekladde',$1,$2,'fixture.pdf')",[$line['id'],$row['id']]);
        $response = RestApiEnv::http('GET', '/restapi/endpoints/v1/attachment/review.php?id=' . $row['id'], null, self::$reviewerHeaders);
        $this->assertSame(200, $response['status'], $response['body']);
        $item = $response['json']['data'];
        $this->assertSame('Matched vendor', $item['vendor']);
        $this->assertSame('1234.56', $item['metadata']['amount']);
        $this->assertSame(987001, $item['suggestion']['kontonr']);
        $this->assertSame(['kontonr'=>'88012','name'=>'Matched vendor','match'=>'cvr','mode'=>'auto','candidates'=>[]], $item['kreditor']);
        $this->assertNotEmpty($item['audit']);
        $this->assertSame(['id'=>(int)$bank['id'],'text'=>'Bank text','date'=>'2026-09-14','amount'=>'-1234.56'],$item['bank_line']);
        $this->assertSame(['kassekladde_id'=>(int)$line['id'],'kladde_id'=>$journal,'bilag'=>71,'posted'=>false,'journal_note'=>'Bank fixture'],$item['draft']);
        $this->assertNull($item['rejection']);
        $this->assertStringNotContainsString(self::$owner, json_encode($item));
        $this->assertSame(404, RestApiEnv::http('GET','/restapi/endpoints/v1/attachment/review.php?id=2147483647',null,self::$reviewerHeaders)['status']);
        $staged = $this->reviewFixture(true, 'staged');
        $this->assertSame(404, RestApiEnv::http('GET','/restapi/endpoints/v1/attachment/review.php?id='.$staged['id'],null,self::$reviewerHeaders)['status']);
        // Empty legacy text and a stale creditor stay consumable by the app's strict schemas.
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET description=NULL,invoice_number=NULL,account='0',vendor_match='none' WHERE id=$1",[$row['id']]);
        $empty = $this->reviewService()->detail((int)$row['id']);
        $this->assertSame('', $empty['description']);
        $this->assertSame('', $empty['invoice_number']);
        $this->assertSame('', $empty['metadata']['description']);
        $this->assertSame('', $empty['metadata']['invoice_number']);
        $this->assertNull($empty['metadata']['kontonr']);
        $this->assertNull($empty['kreditor']);
        RestApiEnv::rows(self::$conn,"UPDATE adresser SET cvrnr='12345678' WHERE id=$1",[$vendor['id']]);
        RestApiEnv::rows(self::$conn,"INSERT INTO adresser(art,kontonr,firmanavn,cvrnr,lukket) VALUES ('K','88013','Other candidate','12345678','')");
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET vendor_match='ambiguous',vendor_konto_id=NULL,vendor_cvr='12345678' WHERE id=$1",[$row['id']]);
        $choice = $this->reviewService()->detail((int)$row['id'])['kreditor'];
        $this->assertSame('choose', $choice['mode']);
        $this->assertSame('', $choice['kontonr']);
        $this->assertSame('', $choice['name']);
        $this->assertNull($choice['match']);
        $this->assertCount(2, $choice['candidates']);
        $this->assertSame(['88012','88013'], array_column($choice['candidates'], 'kontonr'));
        $this->assertSame([null,null], array_column($choice['candidates'], 'match'));
    }

    public function testRejectRequiresMessageAndKeepsFileWithOneAuditedEventAndReplay(): void
    {
        $row = $this->reviewFixture();
        foreach (['', '   ', [], "bad\0message", str_repeat('x',4001)] as $message) {
            $response = $this->rejectRequest($row, ['message'=>$message]);
            $this->assertSame(422, $response['status'], $response['body']);
            $this->assertSame('rejection_reason_required', $response['json']['data']['details']['reason']);
            $this->assertSame('pending', $this->row((int)$row['id'])['review_state']);
        }
        $missing = RestApiEnv::http('POST','/restapi/endpoints/v1/attachment/reject.php', $this->body(['id'=>(int)$row['id'],'reason_code'=>'other','message'=>'Fix']),self::$reviewerHeaders);
        $this->assertSame('if_match_required', $missing['json']['data']['details']['reason']);
        $this->assertSame(422,$this->rejectRequest($row,['reason_code'=>'bad'])['status']);
        $path = self::$root . '/' . $row['storage_path'];
        file_put_contents($path, 'b2c fixture file');
        $client = RequestId::resolve(null);
        $requestId = RequestId::resolve(null);
        $response = $this->rejectRequest($row, ['client_id'=>$client], array_merge(self::$reviewerHeaders,['X-Request-Id: '.$requestId]));
        $this->assertSame(200, $response['status'], $response['body']);
        $data = $response['json']['data'];
        $this->assertSame('rejected', $data['review_state']);
        $this->assertSame('afvist', $data['display_status']);
        $this->assertSame('t3_reviewer', $data['rejection']['by_name']);
        $this->assertSame('ubehandlet', $this->row((int)$row['id'])['status']);
        $this->assertSame($row['storage_path'], $this->row((int)$row['id'])['storage_path']);
        $this->assertSame('b2c fixture file', file_get_contents($path));
        $this->assertSame($response['json'], $this->rejectRequest($row, ['client_id'=>$client])['json']);
        $replay = $this->reviewService()->reject((int)$row['id'], ['client_id'=>$client,'id'=>(int)$row['id'],
            'reason_code'=>'other','message'=>"Please fix the supplier's amount."], '"stale"');
        $this->assertSame(['Idempotent-Replayed'=>'true'], $replay['headers']);
        $this->assertSame($response['json'], $replay['body']);
        $different = $this->rejectRequest($row, ['client_id'=>$client,'message'=>'Different']);
        $this->assertSame('client_id_reused', $different['json']['data']['details']['reason']);
        $twice = $this->rejectRequest($this->row((int)$row['id']));
        $this->assertSame(409, $twice['status']);
        $this->assertSame('already_decided', $twice['json']['data']['details']['reason']);
        $audits = RestApiEnv::rows(self::$conn,"SELECT * FROM pool_file_audit WHERE pool_file_id=$1 AND event='rejected'",[$row['id']]);
        $this->assertCount(1,$audits);
        $this->assertSame($requestId,$audits[0]['request_id']);
        $this->assertSame(self::$reviewer,$audits[0]['actor']);
        $this->assertTrue(pool_audit_verify((int)$row['id']));
        $events = RestApiEnv::rows(self::$conn,"SELECT * FROM mobile_events WHERE ref_id=$1 AND type='attachment_decided'",[$row['id']]);
        $this->assertCount(1,$events);
        $this->assertSame(self::$owner,$events[0]['target_subject']);
        require_once __DIR__ . '/../../restapi/services/MobileEventFanout.php';
        $fanout = new MobileEventFanout(self::$conn,self::$registry,RestApiEnv::regnskabId(RestApiEnv::accountOpen()));
        $this->assertSame(0,$fanout->run()['failed']);
        $this->assertSame(0,$fanout->run()['failed']);
        $inbox = RestApiEnv::rows(self::$conn,"SELECT n.title,n.message,r.subject FROM notifications n JOIN notification_recipients r ON r.notification_id=n.id WHERE n.ref_id=$1 AND n.type='attachment_decided'",[$row['id']]);
        $this->assertCount(1,$inbox);
        $this->assertSame('Bogholder spørger',$inbox[0]['title']);
        $this->assertSame(self::$owner,$inbox[0]['subject']);
        $this->assertStringContainsString("Please fix the supplier's amount.",$inbox[0]['message']);
    }

    public function testDecidedQueueWindowProcessedStateAndStaleValidator(): void
    {
        $recent = $this->reviewFixture(true,'attached','approved');
        $old = $this->reviewFixture(true,'attached','approved');
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET reviewed_at=now() WHERE id=$1",[$recent['id']]);
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET reviewed_at=now()-interval '31 days' WHERE id=$1",[$old['id']]);
        $ids = array_column($this->reviewService()->queue(['state'=>'decided'])['items'],'id');
        $this->assertContains((int)$recent['id'],$ids);
        $this->assertNotContains((int)$old['id'],$ids);
        $this->assertSame('attachment_processed',$this->rejectRequest($recent)['json']['data']['details']['reason']);
        $row = $this->reviewFixture();
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET description='Concurrent change' WHERE id=$1",[$row['id']]);
        $this->assertSame(412,$this->rejectRequest($row)['status']);
        $web = $this->reviewFixture(false);
        $this->assertSame(200,$this->rejectRequest($web,[],self::$registryHeaders)['status']);
        $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT * FROM mobile_events WHERE ref_id=$1',[$web['id']]));
    }

    public function testRightsRequireReviewerReadAndWriteIncludingRegistry(): void
    {
        $row = $this->reviewFixture();
        $path = '/restapi/endpoints/v1/attachment/review.php';
        $this->assertSame(401,RestApiEnv::http('GET',$path)['status']);
        $this->assertSame(403,RestApiEnv::http('GET',$path,null,self::$ownerHeaders)['status']);
        $this->assertSame(403,$this->rejectRequest($row,[],self::$ownerHeaders)['status']);
        RestApiEnv::rows(self::$conn,"UPDATE brugere SET rettigheder='0020000000000000' WHERE brugernavn='t3_reviewer'");
        try {
            $this->assertSame(200,RestApiEnv::http('GET',$path,null,self::$reviewerHeaders)['status']);
            $this->assertSame(403,$this->rejectRequest($row)['status']);
            $this->assertSame(200,RestApiEnv::http('GET',$path,null,self::$registryHeaders)['status']);
        } finally { RestApiEnv::rows(self::$conn,"UPDATE brugere SET rettigheder='0010000000000000' WHERE brugernavn='t3_reviewer'"); }
    }

    public function testUnknownUploaderAndReviewerNeverExposeCanonicalSubjects(): void
    {
        $row = $this->reviewFixture();
        $unknown = Subject::build('account',2147483647);
        RestApiEnv::rows(self::$conn,'UPDATE pool_files SET uploaded_by=$1 WHERE id=$2',[$unknown,$row['id']]);
        $item = $this->reviewService()->detail((int)$row['id']);
        $this->assertSame(['name'=>'Saldi','is_me'=>false],$item['uploaded_by']);
        $this->assertStringNotContainsString($unknown,json_encode($item));
    }

    public function testOutboxFailureRollsBackDecisionAuditAndIdempotency(): void
    {
        $row = $this->reviewFixture();
        $before = pool_audit_read((int)$row['id'])['total'];
        $client = RequestId::resolve(null);
        RestApiEnv::rows(self::$conn,"CREATE FUNCTION b2c_fail_event() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN
            RAISE EXCEPTION 'b2c injected outbox failure'; END \$\$");
        RestApiEnv::rows(self::$conn,'CREATE TRIGGER b2c_fail_event BEFORE INSERT ON mobile_events FOR EACH ROW EXECUTE FUNCTION b2c_fail_event()');
        try {
            $failure = $this->rejectRequest($row,['client_id'=>$client]);
            $this->assertSame(500,$failure['status'],$failure['body']);
            $this->assertSame('internal',$failure['json']['data']['error']);
            $this->assertSame('pending',$this->row((int)$row['id'])['review_state']);
            $this->assertSame($before,pool_audit_read((int)$row['id'])['total']);
            $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT * FROM mobile_idempotency WHERE client_id=$1',[$client]));
        } finally {
            RestApiEnv::rows(self::$conn,'DROP TRIGGER b2c_fail_event ON mobile_events');
            RestApiEnv::rows(self::$conn,'DROP FUNCTION b2c_fail_event()');
        }
        $this->assertSame(200,$this->rejectRequest($row,['client_id'=>$client])['status']);
    }
}
