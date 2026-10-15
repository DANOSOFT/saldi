<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/ClaimPublicationTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Verify rejected claim uploads preserve existing reservations and plain retries.


require_once __DIR__.'/support/T4ClaimsTestCase.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolReviewVersion.php';
final class ClaimPublicationTest extends T4ClaimsTestCase
{
    public function test_conflicting_claim_leaves_a_plain_staged_upload_retryable(): void
    {
        $payload = $this->payload();
        $row = $this->fixture('staged');
        $id = (int)$row['id'];
        $filename = $payload['id'] . '.pdf';
        $row = self::sql('UPDATE pool_files SET client_id=$1,sha256=$2,filename=$3,storage_path=$4 WHERE id=$5 RETURNING *',
            [$payload['id'], $payload['sha256'], $filename, 'pulje/.staging/' . $filename, $id])[0];
        $markers = self::sql("SELECT * FROM mobile_idempotency WHERE subject=$1 AND resource_type='publication_hook' ORDER BY id",
            [self::$owner]);
        $changed = $payload + ['claim' => ['kind' => 'udlaeg']];
        $bytes = base64_decode($payload['image_base64']) . "% different receipt bytes\n";
        $changed['image_base64'] = base64_encode($bytes);
        $changed['size'] = strlen($bytes);
        $changed['sha256'] = hash('sha256', $bytes);

        $error = $this->assertStatus(409, $this->request('POST', 'attachment/', $changed));
        $this->assertSame('conflict', $error['error']);
        $this->assertSame($row, self::sql('SELECT * FROM pool_files WHERE id=$1', [$id])[0]);
        $this->assertSame($markers, self::sql("SELECT * FROM mobile_idempotency WHERE subject=$1 AND resource_type='publication_hook' ORDER BY id",
            [self::$owner]), 'A rejected claim must not commit publication intent');
        $this->assertSame('0', self::sql('SELECT count(*) AS n FROM mobile_expense_claims WHERE pool_file_id=$1', [$id])[0]['n']);

        $published = $this->assertStatus(200, $this->request('POST', 'attachment/', $payload));
        $this->assertSame($id, $published['id']);
        $this->assertSame('ubehandlet', $published['status']);
        $this->assertSame($payload['sha256'], self::sql('SELECT sha256 FROM pool_files WHERE id=$1', [$id])[0]['sha256']);
        $this->assertFileExists(self::$root . '/pulje/' . $payload['id'] . '.pdf');
        $this->assertSame('0', self::sql('SELECT count(*) AS n FROM mobile_expense_claims WHERE pool_file_id=$1', [$id])[0]['n']);
    }

    public function test_claim_rejects_foreign_deleted_and_published_plain_uploads_without_publication_intent(): void
    {
        foreach (['foreign' => 404, 'deleted' => 410, 'ubehandlet' => 409] as $state => $status) {
            $payload = $this->payload() + ['claim' => ['kind' => 'udlaeg']];
            $row = $this->fixture($state === 'foreign' ? 'staged' : $state);
            $id = (int)$row['id'];
            $owner = $state === 'foreign' ? Subject::build('account', self::$otherId) : self::$owner;
            $row = self::sql('UPDATE pool_files SET client_id=$1,sha256=$2,uploaded_by=$3 WHERE id=$4 RETURNING *',
                [$payload['id'], $payload['sha256'], $owner, $id])[0];
            $markers = self::sql("SELECT * FROM mobile_idempotency WHERE subject=$1 AND resource_type='publication_hook' ORDER BY id",
                [self::$owner]);

            $this->assertStatus($status, $this->request('POST', 'attachment/', $payload));
            $this->assertSame($row, self::sql('SELECT * FROM pool_files WHERE id=$1', [$id])[0]);
            $this->assertSame($markers, self::sql("SELECT * FROM mobile_idempotency WHERE subject=$1 AND resource_type='publication_hook' ORDER BY id",
                [self::$owner]), 'Rejected ' . $state . ' upload must not commit publication intent');
        }
    }

    public function test_process_killed_after_rename_rolls_back_bilag_and_claim_and_retry_publishes_together(): void
    {
        $payload=$this->payload()+['claim'=>['kind'=>'udlaeg']];
        $process=proc_open([PHP_BINARY,__DIR__.'/support/run_claim_publication.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $this->assertIsResource($process);
        try {
            fwrite($pipes[0],json_encode(['db'=>RestApiEnv::testDb(),'user_id'=>self::$userId,'subject'=>self::$owner,'payload'=>$payload],JSON_THROW_ON_ERROR)."\n");
            fflush($pipes[0]);
            $read=[$pipes[1]];
            $write=$except=[];
            $ready=stream_select($read,$write,$except,15);
            $line=$ready ? trim(fgets($pipes[1])) : '';
        } finally {
            proc_terminate($process,9);
            fclose($pipes[0]);
            fclose($pipes[1]);
            $stderr=stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            proc_close($process);
        }
        $this->assertSame('renamed-and-claim-inserted',$line,'Upload subprocess did not reach publication hook: '.$stderr);
        $this->assertSame('',$stderr);
        // FOR UPDATE synchronizes with PostgreSQL's rollback after the killed client disconnects.
        $row=self::sql('SELECT * FROM pool_files WHERE client_id=$1 FOR UPDATE',[$payload['id']])[0];
        $id=(int)$row['id'];
        $this->assertSame('staged',$row['status']);
        $this->assertFileExists(self::$root.'/pulje/'.$row['filename']);
        $this->assertSame('0',self::sql('SELECT count(*) AS n FROM mobile_expense_claims WHERE pool_file_id=$1',[$id])[0]['n']);
        $queue=$this->assertStatus(200,$this->request('GET','attachment/review.php',null,self::$reviewerHeaders));
        $this->assertNotContains($id,array_column($queue['items'],'id'));
        $this->assertStatus(404,$this->request('GET','attachment/review.php?id='.$id,null,self::$reviewerHeaders));
        $etag='"'.pool_review_version($row).'"';
        $headers=array_merge(self::$reviewerHeaders,['If-Match: '.$etag]);
        $this->assertStatus(404,$this->request('POST','attachment/approve.php',$this->body(['id'=>$id,'kontonr'=>987611,'contra'=>['type'=>'F','kontonr'=>987610]]),$headers));
        $this->assertStatus(404,$this->request('PATCH','attachment/metadata.php?id='.$id,$this->body(['amount'=>'99.00']),array_merge(self::$headers,['If-Match: '.$etag])));
        $this->assertStatus(409,$this->request('DELETE','attachment/?id='.$id));
        $plain=$payload;
        unset($plain['claim']);
        $this->assertStatus(409,$this->request('POST','attachment/',$plain));
        $changed=$payload;
        $changed['extracted_data']['total_amount']='99.00';
        $this->assertStatus(409,$this->request('POST','attachment/',$changed));
        $published=$this->assertStatus(200,$this->request('POST','attachment/',$payload));
        $this->assertSame('ubehandlet',$published['status']);
        $this->assertSame($id,$published['id']);
        $this->assertSame('1',self::sql('SELECT count(*) AS n FROM mobile_expense_claims WHERE pool_file_id=$1',[$id])[0]['n']);
        $this->assertSame($published,$this->assertStatus(200,$this->request('POST','attachment/',$payload)));
        $claim=self::sql('SELECT * FROM mobile_expense_claims WHERE pool_file_id=$1',[$id])[0];
        $this->assertSame($payload['id'],$claim['client_id']);
        $this->assertSame(self::$owner,$claim['subject']);
        $this->assertSame(self::$employeeId,(int)$claim['ansat_id']);
        $queue=$this->assertStatus(200,$this->request('GET','attachment/review.php',null,self::$reviewerHeaders));
        $this->assertContains($id,array_column($queue['items'],'id'));
    }
    public function test_publication_marker_does_not_take_the_statement_operations_replay_identity(): void
    {
        $payload=$this->payload();
        self::sql('BEGIN');
        try {
            (new Idempotency(self::$conn))->execute(self::$owner,'mileage.statement.issue',null,
                ['client_id'=>$payload['id'],'period_from'=>'2026-10-01','period_to'=>'2026-10-31'],
                static fn(): array => ['status'=>201,'body'=>['success'=>true,'message'=>'','data'=>['state'=>'generating']]]);
            self::sql('COMMIT');
        } catch (Throwable $error) { self::sql('ROLLBACK'); throw $error; }
        $claims=$this->service();
        $upload=AttachmentUpload::decode(json_decode(json_encode($payload)),[]);
        $upload['publication_claim']=['kind'=>'udlaeg','ansat_id'=>self::$employeeId];
        $published=(new DurableAttachmentModel(self::$conn,RestApiEnv::testDb(),self::$owner))->upload($upload,null,[$claims,'publishExpense']);
        $this->assertSame('ubehandlet',$published['row']['status']);
        $this->assertSame('2',self::sql('SELECT count(*) AS n FROM mobile_idempotency WHERE subject=$1 AND (client_id=$2 OR (resource_type=$3 AND request_sha256=$4))',
            [self::$owner,$payload['id'],'publication_hook',Idempotency::requestHash('attachment.publish',$payload['id'],['sha256'=>$payload['sha256'],'metadata'=>$upload['extracted_data'],'claim'=>$upload['publication_claim']])])[0]['n']);
    }

    public function test_json_and_multipart_claim_upload_are_published_and_idempotent(): void
    {
        $payload=$this->payload()+['claim'=>['kind'=>'udlaeg']];
        $created=$this->assertStatus(201,$this->request('POST','attachment/',$payload));
        $this->assertSame('1',self::sql('SELECT count(*) AS n FROM mobile_expense_claims WHERE pool_file_id=$1',[$created['id']])[0]['n']);
        $this->assertStatus(200,$this->request('POST','attachment/',$payload));
        // A processed receipt still replays its existing claim without creating a second row.
        self::sql("UPDATE pool_files SET status='attached',review_state='approved' WHERE id=$1",[$created['id']]);
        $this->assertStatus(200,$this->request('POST','attachment/',$payload));
        $multipart=$this->payload();
        $path=tempnam(sys_get_temp_dir(),'claims-pdf-');
        file_put_contents($path,base64_decode($multipart['image_base64']));
        $curl=curl_init(RestApiEnv::baseUrl().'/restapi/endpoints/v1/attachment/');
        try {
            curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>self::$headers,
                CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['id'=>$multipart['id'],'sha256'=>$multipart['sha256'],'size'=>(string)$multipart['size'],
                    'claim'=>json_encode(['kind'=>'udlaeg']),'extracted_data'=>json_encode($multipart['extracted_data']),
                    'file'=>new CURLFile($path,'application/pdf','receipt.pdf')]]);
            $raw=curl_exec($curl);
            $this->assertSame(201,(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),(string)$raw);
            $data=json_decode($raw,true)['data'];
            $this->assertSame('1',self::sql('SELECT count(*) AS n FROM mobile_expense_claims WHERE pool_file_id=$1',[$data['id']])[0]['n']);
        } finally { curl_close($curl); unlink($path); }
    }
}
