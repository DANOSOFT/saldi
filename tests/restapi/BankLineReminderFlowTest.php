<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/BankLineReminderFlowTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Cover attached upload replay and legacy reminder workflow isolation.

require_once __DIR__ . '/support/T3BankLineTestCase.php';
require_once __DIR__ . '/../../restapi/services/legacy/LegacyEngine.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolFileTransitions.php';

final class BankLineReminderFlowTest extends T3BankLineTestCase
{
    public function test_legacy_create_characterisation_preserves_defaults_and_recipient_resolution(): void
    {
        $service = new ReminderService(self::$conn,self::$master,self::$tenantId);
        $created = $service->create(self::$sender,['target'=>self::$capture,'message'=>'  Original reminder  ','amount'=>'612.500','date'=>'2026-09-14']);
        $this->assertSame(['id','notification_id','recipient_count','dispatch_count'],array_keys($created));
        $this->assertSame(1,$created['recipient_count']);
        $this->assertSame(0,$created['dispatch_count']);
        $row = RestApiEnv::rows(self::$conn,'SELECT * FROM reminders WHERE id=$1',[$created['id']])[0];
        $this->assertSame('Original reminder',$row['message']);
        $this->assertSame('612.500',$row['amount']);
        $this->assertNull($row['bank_line_id']);
        $notification = RestApiEnv::rows(self::$conn,'SELECT * FROM notifications WHERE id=$1',[$created['notification_id']])[0];
        $this->assertSame('Bilag til bogholder',$notification['title']);
        $first = mobileResolveReminder(self::$conn,self::$tenantId,self::$capture,$created['id']);
        $this->assertSame($first,mobileResolveReminder(self::$conn,self::$tenantId,self::$capture,$created['id']));
        $this->assertSame(PGSQL_TRANSACTION_IDLE,pg_transaction_status(self::$conn));
    }

    public function test_transaction_joiner_never_commits_caller_work(): void
    {
        $service = new ReminderService(self::$conn,self::$master,self::$tenantId);
        $before = RestApiEnv::rows(self::$conn,'SELECT count(*) AS count FROM reminders')[0]['count'];
        try {
            $service->createInTransaction(self::$sender,['target'=>'all','message'=>'No caller transaction']);
            $this->fail('Joiner must require a transaction');
        } catch (LogicException $error) {
            $this->assertSame(PGSQL_TRANSACTION_IDLE,pg_transaction_status(self::$conn));
        }
        RestApiEnv::rows(self::$conn,'BEGIN');
        try {
            $created = $service->createInTransaction(self::$sender,['target'=>'all','message'=>'Caller rollback']);
            $this->assertSame(2,$created['recipient_count']);
            $this->assertSame(PGSQL_TRANSACTION_INTRANS,pg_transaction_status(self::$conn));
            try {
                $service->create(self::$sender,['target'=>'all','message'=>'Nested owner']);
                $this->fail('Owner must reject a nested transaction');
            } catch (LogicException $error) {
                $this->assertSame(PGSQL_TRANSACTION_INTRANS,pg_transaction_status(self::$conn));
            }
        } finally {
            RestApiEnv::rows(self::$conn,'ROLLBACK');
        }
        $this->assertSame($before,RestApiEnv::rows(self::$conn,'SELECT count(*) AS count FROM reminders')[0]['count']);
    }

    public function test_legacy_endpoint_ignores_internal_bank_fields_for_open_incoming_and_ignored_lines(): void
    {
        foreach (['open','incoming','ignored'] as $state) {
            $line = $this->bank($state === 'incoming' ? '12.00' : '-612.50');
            if ($state === 'ignored') {
                RestApiEnv::rows(self::$conn,"UPDATE bank_lines SET state='ignored' WHERE id=$1",[$line['id']]);
            }
            $before = RestApiEnv::rows(self::$conn,'SELECT * FROM bank_lines WHERE id=$1',[$line['id']])[0];
            $replays = $this->counts()['mobile_idempotency'];
            $response = $this->http('POST','reminders/',[
                'target'=>self::$capture,'message'=>'  Legacy reminder  ','amount'=>'1.250','date'=>'2026-09-15',
                'bank_line_id'=>(int)$line['id'],'title'=>'Mangler bilag']);
            $this->assertSame(201,$response['status'],$response['body']);
            $created = $response['json']['data'];
            $this->assertSame(['id','notification_id','recipient_count','dispatch_count'],array_keys($created));
            $reminder = RestApiEnv::rows(self::$conn,'SELECT * FROM reminders WHERE id=$1',[$created['id']])[0];
            $this->assertNull($reminder['bank_line_id']);
            $this->assertSame('Legacy reminder',$reminder['message']);
            $this->assertSame('1.250',$reminder['amount']);
            $this->assertSame('2026-09-15',$reminder['date']);
            $notification = RestApiEnv::rows(self::$conn,'SELECT title FROM notifications WHERE id=$1',[$created['notification_id']])[0];
            $this->assertSame('Bilag til bogholder',$notification['title']);
            $uploaded = $this->http('POST','attachment/',$this->uploadBody($created['id']),self::$captureHeaders);
            $this->assertSame(201,$uploaded['status'],$uploaded['body']);
            $this->assertNull($uploaded['json']['data']['bank_line_id']);
            $this->assertNotNull(RestApiEnv::rows(self::$conn,'SELECT resolved_at FROM notification_recipients WHERE notification_id=$1',[$created['notification_id']])[0]['resolved_at']);
            $this->assertSame($before,RestApiEnv::rows(self::$conn,'SELECT * FROM bank_lines WHERE id=$1',[$line['id']])[0]);
            $this->assertSame($replays,$this->counts()['mobile_idempotency']);
        }
    }

    /** @return void Attach a real uploaded file through the shared web transition to the fixture journal row. */
    private function attachUpload(int $poolId, array $line): void
    {
        $pool = RestApiEnv::rows(self::$conn,'SELECT * FROM pool_files WHERE id=$1',[$poolId])[0];
        $userId = (int)Subject::parse(self::$capture)['user_id'];
        $context = new EngineContext(self::$conn,RestApiEnv::testDb(),self::$tenantId,'bank_capture',$userId,'2026-09-14');
        LegacyEngine::run($context,function () use ($pool,$line,$userId): void {
            $requestUri = ifset($_SERVER,'REQUEST_URI');
            $_SERVER['REQUEST_URI'] = '/saldi/includes/documents.php';
            try {
                $target = pool_attach_target(self::$root,(int)$line['kladde_id'],(int)$line['kassekladde_id'],$pool['filename']);
                if (!is_dir($target['dir'])) {
                    mkdir($target['dir'],0777,true);
                }
                $this->assertTrue(PoolFileTransitions::insertDocument(RestApiEnv::testDb(),
                    self::$root . '/' . $pool['storage_path'],$target['path'],0,'kassekladde',(int)$line['kassekladde_id'],$userId));
            } finally {
                if ($requestUri === null) {
                    unset($_SERVER['REQUEST_URI']);
                } else {
                    $_SERVER['REQUEST_URI'] = $requestUri;
                }
            }
        });
        $this->assertSame('attached',RestApiEnv::rows(self::$conn,'SELECT status FROM pool_files WHERE id=$1',[$poolId])[0]['status']);
    }

    public function test_replay_cannot_link_an_unrelated_attached_upload_to_a_bank_reminder(): void
    {
        $line = $this->bank();
        $unrelated = $this->bank();
        $reminder = $this->remind($line,self::$capture);
        $body = $this->uploadBody($reminder['reminder_id']);
        unset($body['extracted_data']['reminder_id']);
        $uploaded = $this->http('POST','attachment/',$body,self::$captureHeaders);
        $this->assertSame(201,$uploaded['status'],$uploaded['body']);
        $poolId = $uploaded['json']['data']['id'];
        $this->attachUpload($poolId,$unrelated);
        $pool = RestApiEnv::rows(self::$conn,'SELECT * FROM pool_files WHERE id=$1',[$poolId])[0];
        $bank = RestApiEnv::rows(self::$conn,'SELECT * FROM bank_lines WHERE id=$1',[$line['id']])[0];
        $documents = RestApiEnv::rows(self::$conn,'SELECT * FROM documents WHERE pool_file_id=$1 ORDER BY id',[$poolId]);
        $counts = $this->counts();
        $body['extracted_data']['reminder_id'] = $reminder['reminder_id'];
        $replay = $this->http('POST','attachment/',$body,self::$captureHeaders);
        $this->assertSame(409,$replay['status'],$replay['body']);
        $this->assertSame('conflict',$replay['json']['data']['error']);
        $this->assertSame($pool,RestApiEnv::rows(self::$conn,'SELECT * FROM pool_files WHERE id=$1',[$poolId])[0]);
        $this->assertSame($bank,RestApiEnv::rows(self::$conn,'SELECT * FROM bank_lines WHERE id=$1',[$line['id']])[0]);
        $this->assertSame($documents,RestApiEnv::rows(self::$conn,'SELECT * FROM documents WHERE pool_file_id=$1 ORDER BY id',[$poolId]));
        $this->assertSame($counts,$this->counts());
        $this->assertNull(RestApiEnv::rows(self::$conn,'SELECT resolved_at FROM notification_recipients WHERE notification_id=$1',[$reminder['notification_id']])[0]['resolved_at']);
        $missing = $this->http('GET','bank-lines/index.php?account=' . $this->account)['json']['data'];
        $this->assertSame([(int)$line['id']],array_column($missing['items'],'id'));
        $this->assertFileExists(self::$root . '/' . $pool['storage_path']);
    }

    public function test_attached_upload_replays_only_its_existing_reminder_and_bidirectional_bank_link(): void
    {
        $line = $this->bank();
        $reminder = $this->remind($line,self::$capture);
        $body = $this->uploadBody($reminder['reminder_id']);
        $uploaded = $this->http('POST','attachment/',$body,self::$captureHeaders);
        $this->assertSame(201,$uploaded['status'],$uploaded['body']);
        $poolId = $uploaded['json']['data']['id'];
        $this->attachUpload($poolId,$line);
        $resolved = RestApiEnv::rows(self::$conn,'SELECT resolved_at FROM notification_recipients WHERE notification_id=$1',[$reminder['notification_id']])[0]['resolved_at'];
        $replay = $this->http('POST','attachment/',$body,self::$captureHeaders);
        $this->assertSame(200,$replay['status'],$replay['body']);
        $this->assertSame($poolId,$replay['json']['data']['id']);
        $this->assertSame('attached',$replay['json']['data']['status']);
        $this->assertSame((int)$line['id'],$replay['json']['data']['bank_line_id']);
        $this->assertSame($resolved,RestApiEnv::rows(self::$conn,'SELECT resolved_at FROM notification_recipients WHERE notification_id=$1',[$reminder['notification_id']])[0]['resolved_at']);
        foreach (['reminder_id','bank_line_id','pool_file_id'] as $field) {
            $table = $field === 'pool_file_id' ? 'bank_lines' : 'pool_files';
            $id = $table === 'bank_lines' ? (int)$line['id'] : $poolId;
            $before = RestApiEnv::rows(self::$conn,"SELECT * FROM $table WHERE id=$1",[$id])[0];
            RestApiEnv::rows(self::$conn,"UPDATE $table SET $field=NULL WHERE id=$1",[$id]);
            $broken = RestApiEnv::rows(self::$conn,"SELECT * FROM $table WHERE id=$1",[$id])[0];
            try {
                $refused = $this->http('POST','attachment/',$body,self::$captureHeaders);
                $this->assertSame(409,$refused['status'],$refused['body']);
                $this->assertSame('conflict',$refused['json']['data']['error']);
                $this->assertSame($broken,RestApiEnv::rows(self::$conn,"SELECT * FROM $table WHERE id=$1",[$id])[0]);
            } finally {
                RestApiEnv::rows(self::$conn,"UPDATE $table SET $field=$1 WHERE id=$2",[$before[$field],$id]);
            }
        }
    }

    public function test_failure_after_reminder_insertion_rolls_back_every_write_and_retry_creates_one(): void
    {
        $line = $this->bank();
        $id = (int)$line['id'];
        $body = $this->body(['bank_line_id'=>$id,'target'=>'all']);
        $captureId = (int)Subject::parse(self::$capture)['user_id'];
        RestApiEnv::rows(self::$conn,"INSERT INTO push_tokens(user_id,token,platform,db_name,subject,principal,device_id)
            VALUES ($1,$2,'ios',$3,$4,'account','bank-rollback-device')",
            [$captureId,'ExponentPushToken[' . bin2hex(random_bytes(12)) . ']',RestApiEnv::testDb(),self::$capture]);
        $before = $this->counts();
        RestApiEnv::rows(self::$conn,"CREATE FUNCTION b3b_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Injected bank state failure'; END $$");
        RestApiEnv::rows(self::$conn,"CREATE TRIGGER b3b_fail BEFORE UPDATE ON bank_lines FOR EACH ROW WHEN (NEW.id=$id) EXECUTE FUNCTION b3b_fail()");
        try {
            $failed = $this->http('POST','bank-lines/remind.php',$body);
            $this->assertSame(500,$failed['status'],$failed['body']);
            $this->assertSame('internal',$failed['json']['data']['error']);
            $this->assertSame($before,$this->counts());
            $this->assertSame('open',RestApiEnv::rows(self::$conn,'SELECT state FROM bank_lines WHERE id=$1',[$id])[0]['state']);
        } finally {
            RestApiEnv::rows(self::$conn,'DROP TRIGGER b3b_fail ON bank_lines');
            RestApiEnv::rows(self::$conn,'DROP FUNCTION b3b_fail()');
        }
        $retry = $this->http('POST','bank-lines/remind.php',$body);
        $this->assertSame(201,$retry['status'],$retry['body']);
        $after = $this->counts();
        foreach (['reminders'=>1,'notifications'=>1,'notification_recipients'=>2,'push_dispatch'=>1,'mobile_idempotency'=>1] as $table=>$delta) {
            $this->assertSame($before[$table]+$delta,$after[$table],$table);
        }
        $this->assertSame($retry['json'],$this->http('POST','bank-lines/remind.php',$body)['json']);
        $this->assertSame($after,$this->counts());
    }

    /** @return array<string,int> Counts include all notification and replay writes in the failure window. */
    private function counts(): array
    {
        $counts = [];
        foreach (['reminders','notifications','notification_recipients','push_dispatch','mobile_idempotency'] as $table) {
            $counts[$table] = (int)RestApiEnv::rows(self::$conn,"SELECT count(*) AS count FROM $table")[0]['count'];
        }
        return $counts;
    }

    /** @return array<string,mixed> Immutable upload body shared across HTTP retries and the child runner. */
    private function uploadBody(int $reminder): array
    {
        return ['id'=>RequestId::resolve(null),'image_base64'=>base64_encode(self::pdf()),'sha256'=>hash('sha256',self::pdf()),
            'extracted_data'=>['reminder_id'=>$reminder,'total_amount'=>'612.50','invoice_date'=>'2026-09-14','currency'=>'DKK']];
    }

    /** @return array<string,mixed> Successful reminder data. */
    private function remind(array $line,string $target): array
    {
        $response = $this->http('POST','bank-lines/remind.php',$this->body(['bank_line_id'=>(int)$line['id'],'target'=>$target]));
        $this->assertSame(201,$response['status'],$response['body']);
        return $response['json']['data'];
    }

    public function test_upload_links_and_resolves_atomically_replay_and_delete_release(): void
    {
        $line = $this->bank();
        $reminder = $this->remind($line,self::$capture);
        $body = $this->uploadBody($reminder['reminder_id']);
        $uploaded = $this->http('POST','attachment/',$body,self::$captureHeaders);
        $this->assertSame(201,$uploaded['status'],$uploaded['body']);
        $poolId = $uploaded['json']['data']['id'];
        $this->assertSame((int)$line['id'],$uploaded['json']['data']['bank_line_id']);
        $pool = RestApiEnv::rows(self::$conn,'SELECT * FROM pool_files WHERE id=$1',[$poolId])[0];
        $this->assertSame($reminder['reminder_id'],(int)$pool['reminder_id']);
        $bank = RestApiEnv::rows(self::$conn,'SELECT * FROM bank_lines WHERE id=$1',[$line['id']])[0];
        $this->assertSame('received',$bank['state']);
        $this->assertSame($poolId,(int)$bank['pool_file_id']);
        $recipient = RestApiEnv::rows(self::$conn,'SELECT resolved_at FROM notification_recipients WHERE notification_id=$1',[$reminder['notification_id']])[0]['resolved_at'];
        $this->assertNotNull($recipient);
        $again = $this->http('POST','attachment/',$body,self::$captureHeaders);
        $this->assertSame(200,$again['status'],$again['body']);
        $this->assertSame($poolId,$again['json']['data']['id']);
        $this->assertSame((int)$line['id'],$again['json']['data']['bank_line_id']);
        $this->assertSame($recipient,RestApiEnv::rows(self::$conn,'SELECT resolved_at FROM notification_recipients WHERE notification_id=$1',[$reminder['notification_id']])[0]['resolved_at']);
        $route = 'bank-lines/index.php?account=' . $this->account;
        $this->assertSame(0,$this->http('GET',$route)['json']['data']['total']);
        $received = $this->http('GET',$route . '&state=received')['json']['data']['items'][0];
        $this->assertSame($poolId,$received['attachment']['id']);
        $this->assertSame('bank_capture',$received['attachment']['uploaded_by_name']);
        $this->assertSame('sendt',$received['attachment']['display_status']);
        $followUp = $this->remind($line,self::$capture);
        $this->assertSame('received',$followUp['bank_line']['state']);
        $this->assertSame($poolId,$followUp['bank_line']['attachment']['id']);
        $ignored = $this->http('POST','bank-lines/state.php',$this->body(['bank_line_id'=>(int)$line['id'],'action'=>'ignore']));
        $this->assertSame('ignored',$ignored['json']['data']['state']);
        $reopened = $this->http('POST','bank-lines/state.php',$this->body(['bank_line_id'=>(int)$line['id'],'action'=>'reopen']));
        $this->assertSame('received',$reopened['json']['data']['state']);
        $deleted = $this->http('DELETE','attachment/?id=' . $poolId,null,self::$captureHeaders);
        $this->assertSame(200,$deleted['status'],$deleted['body']);
        $bank = RestApiEnv::rows(self::$conn,'SELECT * FROM bank_lines WHERE id=$1',[$line['id']])[0];
        $this->assertSame('requested',$bank['state']);
        $this->assertNull($bank['pool_file_id']);
        $this->assertSame(1,$this->http('GET',$route)['json']['data']['total']);
        $this->assertSame(200,$this->http('DELETE','attachment/?id=' . $poolId,null,self::$captureHeaders)['status']);
    }

    public function test_broadcast_answer_closes_only_its_recipient_and_refuses_a_second_active_answer(): void
    {
        $line = $this->bank();
        $reminder = $this->remind($line,'all');
        $first = $this->http('POST','attachment/',$this->uploadBody($reminder['reminder_id']),self::$captureHeaders);
        $this->assertSame(201,$first['status'],$first['body']);
        $recipients = RestApiEnv::rows(self::$conn,'SELECT subject,resolved_at FROM notification_recipients WHERE notification_id=$1 ORDER BY subject',[$reminder['notification_id']]);
        foreach ($recipients as $recipient) {
            if ($recipient['subject'] === self::$capture) {
                $this->assertNotNull($recipient['resolved_at']);
            } else {
                $this->assertNull($recipient['resolved_at']);
            }
        }
        $second = $this->http('POST','attachment/',$this->uploadBody($reminder['reminder_id']));
        $this->assertSame(409,$second['status'],$second['body']);
        $bank = RestApiEnv::rows(self::$conn,'SELECT * FROM bank_lines WHERE id=$1',[$line['id']])[0];
        $this->assertSame($first['json']['data']['id'],(int)$bank['pool_file_id']);
        $this->assertCount(1,RestApiEnv::rows(self::$conn,"SELECT id FROM pool_files WHERE bank_line_id=$1 AND status='ubehandlet'",[$line['id']]));
        $this->assertNull(RestApiEnv::rows(self::$conn,'SELECT resolved_at FROM notification_recipients WHERE notification_id=$1 AND subject=$2',[$reminder['notification_id'],self::$sender])[0]['resolved_at']);
        $projection = $this->http('GET','bank-lines/index.php?state=received&account=' . $this->account)['json']['data']['items'][0];
        $this->assertNull($projection['reminder']['resolved_at']);
    }

    public function test_resolution_failure_rolls_back_pool_link_state_and_recipient(): void
    {
        $line = $this->bank();
        $reminder = $this->remind($line,self::$capture);
        $body = $this->uploadBody($reminder['reminder_id']);
        $id = (int)$line['id'];
        RestApiEnv::rows(self::$conn,"CREATE FUNCTION b3b_resolve_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Injected upload state failure'; END $$");
        RestApiEnv::rows(self::$conn,"CREATE TRIGGER b3b_resolve_fail BEFORE UPDATE ON bank_lines FOR EACH ROW WHEN (NEW.id=$id AND NEW.state='received') EXECUTE FUNCTION b3b_resolve_fail()");
        try {
            $failed = $this->http('POST','attachment/',$body,self::$captureHeaders);
            $this->assertSame(500,$failed['status'],$failed['body']);
            $pool = RestApiEnv::rows(self::$conn,'SELECT * FROM pool_files WHERE client_id=$1',[$body['id']])[0];
            $this->assertSame('staged',$pool['status']);
            $this->assertNull($pool['bank_line_id']);
            $this->assertNull($pool['reminder_id']);
            $this->assertNull(RestApiEnv::rows(self::$conn,'SELECT resolved_at FROM notification_recipients WHERE notification_id=$1',[$reminder['notification_id']])[0]['resolved_at']);
            $this->assertSame('requested',RestApiEnv::rows(self::$conn,'SELECT state FROM bank_lines WHERE id=$1',[$id])[0]['state']);
        } finally {
            RestApiEnv::rows(self::$conn,'DROP TRIGGER b3b_resolve_fail ON bank_lines');
            RestApiEnv::rows(self::$conn,'DROP FUNCTION b3b_resolve_fail()');
        }
        $retry = $this->http('POST','attachment/',$body,self::$captureHeaders);
        $this->assertContains($retry['status'],[200,201],$retry['body']);
        $this->assertSame($id,$retry['json']['data']['bank_line_id']);
    }

    public function test_killed_upload_after_commit_retains_consistent_links_and_retries_once(): void
    {
        $line = $this->bank();
        $reminder = $this->remind($line,self::$capture);
        $body = $this->uploadBody($reminder['reminder_id']);
        $process = proc_open([PHP_BINARY,__DIR__ . '/support/run_bank_line_upload.php',RestApiEnv::testDb(),
            (string)self::$tenantId,self::$capture],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $this->assertIsResource($process);
        try {
            fwrite($pipes[0],json_encode($body,JSON_THROW_ON_ERROR) . "\n");
            stream_set_timeout($pipes[1],20);
            $ready = fgets($pipes[1]);
            $this->assertSame("COMMITTED\n",$ready);
            $this->assertTrue(proc_terminate($process,9));
        } finally {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
        $pool = RestApiEnv::rows(self::$conn,'SELECT * FROM pool_files WHERE client_id=$1',[$body['id']])[0];
        $bank = RestApiEnv::rows(self::$conn,'SELECT * FROM bank_lines WHERE id=$1',[$line['id']])[0];
        $this->assertSame('ubehandlet',$pool['status']);
        $this->assertSame($reminder['reminder_id'],(int)$pool['reminder_id']);
        $this->assertSame((int)$line['id'],(int)$pool['bank_line_id']);
        $this->assertSame((int)$pool['id'],(int)$bank['pool_file_id']);
        $this->assertSame('received',$bank['state']);
        $this->assertNotNull(RestApiEnv::rows(self::$conn,'SELECT resolved_at FROM notification_recipients WHERE notification_id=$1',[$reminder['notification_id']])[0]['resolved_at']);
        $this->assertFileExists(self::$root . '/' . $pool['storage_path']);
        $retry = $this->http('POST','attachment/',$body,self::$captureHeaders);
        $this->assertSame(200,$retry['status'],$retry['body']);
        $this->assertSame((int)$pool['id'],$retry['json']['data']['id']);
        $this->assertSame((int)$line['id'],$retry['json']['data']['bank_line_id']);
        $this->assertCount(1,RestApiEnv::rows(self::$conn,'SELECT id FROM pool_files WHERE client_id=$1',[$body['id']]));
    }
}
