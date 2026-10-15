<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/ReceiptAttachmentTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Regress restore-and-reattach retries with newer pending or lost moves.

use PHPUnit\Framework\Attributes\DataProvider;
require_once __DIR__ . '/support/T4ReceiptTestCase.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolApprove.php';

final class ReceiptAttachmentTest extends T4ReceiptTestCase
{
    private static string $root;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$root = dirname(poolPuljePath(RestApiEnv::testDb()));
        if (!is_dir(self::$root . '/pulje')) {
            mkdir(self::$root . '/pulje', 0777, true);
        }
        chmod(self::$root, 0777);
        chmod(self::$root . '/pulje', 0777);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$root) && is_dir(self::$root)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::$root,
                FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir(self::$root);
        }
        parent::tearDownAfterClass();
    }

    /** @return array<string,mixed> Real own upload identity, with distinct bytes and a contained file. */
    private function upload(): array
    {
        $name = MobileAuth::uuid() . '.pdf';
        $bytes = 'delivery-note-' . bin2hex(random_bytes(12));
        file_put_contents(self::$root . '/pulje/' . $name, $bytes);
        chmod(self::$root . '/pulje/' . $name, 0666);
        return $this->rows("INSERT INTO pool_files(filename,uploaded_by,status,review_state,storage_path,sha256,file_date)
            VALUES($1,$2,'ubehandlet','pending',$3,$4,'2026-10-06') RETURNING *",
            [$name,self::$subject,'pulje/' . $name,hash('sha256', $bytes)])[0];
    }

    /** @return array{client_id:string,order_id:int,attachment_id:int} */
    private function attachBody(array $row): array
    {
        return ['client_id'=>MobileAuth::uuid(), 'order_id'=>$this->f['order'], 'attachment_id'=>(int)$row['id']];
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    private function direct(array $body, ?Closure $hook = null): array
    {
        return pool_attach_to_source(['connection'=>self::$conn, 'db'=>RestApiEnv::testDb(), 'subject'=>self::$subject,
            'user_id'=>(int)self::$user['id'], 'hook'=>$hook], $body['attachment_id'], 'creditorOrder', $body['order_id'], $body['client_id']);
    }

    /** @return array<string,mixed> Assert one linked identity/document/file and the canonical legacy target. */
    private function attached(array $original): array
    {
        $row = $this->rows('SELECT * FROM pool_files WHERE id=$1', [$original['id']])[0];
        $docs = $this->rows('SELECT * FROM documents WHERE pool_file_id=$1', [$row['id']]);
        self::assertCount(1, $docs);
        self::assertSame('creditorOrder', $docs[0]['source']);
        self::assertSame((string)$this->f['order'], $docs[0]['source_id']);
        self::assertSame((string)self::$user['id'], $docs[0]['user_id']);
        self::assertSame('/creditor/orders/' . $this->f['order'], $docs[0]['filepath']);
        self::assertSame('attached', $row['status']);
        self::assertSame('pending', $row['review_state']);
        self::assertSame(self::$subject, $row['uploaded_by']);
        self::assertSame(self::$subject, $row['attached_by']);
        self::assertSame([(int)$docs[0]['id']], json_decode($row['document_ids'], true));
        self::assertSame('creditor/orders/' . $this->f['order'] . '/' . $original['filename'], $row['storage_path']);
        self::assertSame($original['sha256'], hash_file('sha256', self::$root . '/' . $row['storage_path']));
        self::assertFileDoesNotExist(self::$root . '/' . $original['storage_path']);
        self::assertCount(1, $this->rows('SELECT id FROM pool_files WHERE id=$1', [$original['id']]));
        self::assertSame(1, $this->fileCopies($original['sha256']));
        return $row;
    }

    private function fileCopies(string $hash): int
    {
        $count = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::$root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && hash_file('sha256', $file->getPathname()) === $hash) { $count++; }
        }
        return $count;
    }

    public function testEndpointAndReplayWriteOneDocumentWithoutStockOrAccountingChanges(): void
    {
        $row = $this->upload();
        $body = $this->attachBody($row);
        $ledger = $this->ledger();
        $stock = $this->rows('SELECT * FROM batch_kob ORDER BY id');
        $response = $this->request('POST', 'attach.php', $body);
        self::assertSame(200, $response['status'], $response['body']);
        self::assertSame(['state'=>'attached','attachment_id'=>(int)$row['id'],'reason'=>null], $response['json']['data']['attachment']);
        $this->attached($row);
        $retry = $this->request('POST', 'attach.php', $body);
        self::assertSame($response['json'], $retry['json']);
        self::assertStringContainsString('Idempotent-Replayed: true', $retry['headers']);
        $this->attached($row);
        self::assertSame($ledger, $this->ledger());
        self::assertSame($stock, $this->rows('SELECT * FROM batch_kob ORDER BY id'));
        $other = $this->upload();
        $this->refused($this->request('POST', 'attach.php', array_replace($body, ['attachment_id'=>(int)$other['id']])),
            409, 'client_id_reused', 'conflict');
        $newClient = array_replace($body, ['client_id'=>MobileAuth::uuid()]);
        self::assertSame('attached', $this->request('POST', 'attach.php', $newClient)['json']['data']['attachment']['state']);
        self::assertSame('attached', $this->request('POST', 'attach.php', $newClient)['json']['data']['attachment']['state']);
        $this->attached($row);
    }

    public function testReceiptAttachesAfterStockCommitAndReplayCannotReceiveTwice(): void
    {
        $row = $this->upload();
        $body = $this->body() + ['attachment_id'=>(int)$row['id']];
        $ledger = $this->ledger();
        $response = $this->send($body);
        self::assertSame(201, $response['status'], $response['body']);
        self::assertSame('attached', $response['json']['data']['attachment']['state']);
        $this->attached($row);
        $retry = $this->request('POST', 'index.php', $body, '"stale"');
        self::assertSame($response['json'], $retry['json']);
        self::assertStringContainsString('Idempotent-Replayed: true', $retry['headers']);
        self::assertSame('6.000', $this->rows('SELECT sum(antal) AS n FROM batch_kob WHERE linje_id=$1', [$this->f['line']])[0]['n']);
        self::assertSame($ledger, $this->ledger());
        $receipt = $this->rows('SELECT * FROM mobile_goods_receipts WHERE id=$1', [$response['json']['data']['receipt_id']])[0];
        self::assertSame('attached', $receipt['attachment_state']);
    }

    public function testFailedAttachmentPreservesReceiptAndRetryRepairsIt(): void
    {
        $row = $this->upload();
        $target = pool_attach_target_source(self::$root, 'creditorOrder', $this->f['order'], $row['filename']);
        mkdir($target['dir'], 0777, true);
        chmod($target['dir'], 0777);
        file_put_contents($target['path'], 'unrelated-existing-file');
        $body = $this->body() + ['attachment_id'=>(int)$row['id']];
        $response = $this->send($body);
        self::assertSame(201, $response['status'], $response['body']);
        self::assertSame('failed', $response['json']['data']['attachment']['state']);
        self::assertContains('attachment_failed', $response['json']['data']['warnings']);
        self::assertSame('6.000', $this->rows('SELECT sum(antal) AS n FROM batch_kob WHERE linje_id=$1', [$this->f['line']])[0]['n']);
        self::assertSame('unrelated-existing-file', file_get_contents($target['path']));
        self::assertCount(0, $this->rows('SELECT * FROM documents WHERE pool_file_id=$1', [$row['id']]));
        unlink($target['path']);
        $retry = $this->request('POST', 'attach.php', $this->attachBody($row));
        self::assertSame(200, $retry['status'], $retry['body']);
        $this->attached($row);
        self::assertSame('attached', $this->service()->receipts($this->f['order'])['items'][0]['attachment']['state']);
        $replay = $this->request('POST', 'index.php', $body, '"stale"');
        self::assertSame(201, $replay['status'], $replay['body']);
        self::assertSame('attached', $replay['json']['data']['attachment']['state']);
        self::assertNotContains('attachment_failed', $replay['json']['data']['warnings']);
        self::assertCount(1, $this->rows('SELECT * FROM mobile_goods_receipts WHERE ordre_id=$1', [$this->f['order']]));
    }

    public function testCommittedMoveRemainsPendingUntilReconciledWithoutRepeatingReceipt(): void
    {
        $row = $this->upload();
        $body = $this->attachBody($row);
        $receipt = $this->send($this->body());
        self::assertSame(201, $receipt['status'], $receipt['body']);
        $receiptId = $receipt['json']['data']['receipt_id'];
        $this->rows("UPDATE mobile_goods_receipts SET attachment_pool_file_id=$1,attachment_state='pending' WHERE id=$2",
            [$row['id'],$receiptId]);
        $target = pool_attach_target_source(self::$root, 'creditorOrder', $this->f['order'], $row['filename']);
        $response = $this->direct($body, static function (string $point) use ($target): void {
            if ($point === 'K2') {
                mkdir($target['dir'], 0777, true);
                chmod($target['dir'], 0777);
                file_put_contents($target['path'], 'unrelated-final-collision');
            }
        });
        self::assertSame('pending', $response['body']['data']['attachment']['state']);
        self::assertSame('file_move_pending', $response['body']['data']['attachment']['reason']);
        self::assertSame('pending', $this->service()->receipts($this->f['order'])['items'][0]['attachment']['state']);
        self::assertSame('unrelated-final-collision', file_get_contents($target['path']));
        unlink($target['path']);
        $intent = $this->rows('SELECT id FROM pool_file_moves WHERE client_id=$1', [$body['client_id']])[0];
        $previous = $GLOBALS['connection'] ?? null;
        $GLOBALS['connection'] = self::$conn;
        try { self::assertSame('done', pool_move_reconcile((int)$intent['id'])); }
        finally { $GLOBALS['connection'] = $previous; }
        $this->attached($row);
        self::assertSame('attached', $this->service()->receipts($this->f['order'])['items'][0]['attachment']['state']);
        $retry = $this->request('POST', 'attach.php', $body);
        self::assertSame(200, $retry['status'], $retry['body']);
        self::assertSame('attached', $retry['json']['data']['attachment']['state']);
        self::assertStringContainsString('Idempotent-Replayed: true', $retry['headers']);
        self::assertSame('6.000', $this->rows('SELECT sum(antal) AS n FROM batch_kob WHERE linje_id=$1', [$this->f['line']])[0]['n']);
    }

    /** @return array<string,array{string,string,string}> */
    public static function reattachCases(): array
    {
        return ['committed'=>['committed','pending','file_move_pending'],
            'lost'=>['lost','failed','attachment_processed']];
    }

    #[DataProvider('reattachCases')]
    public function testRestoredNoteRetryUsesNewestMove(string $moveState, string $state, string $reason): void
    {
        $row = $this->upload();
        $receipt = $this->send($this->body() + ['attachment_id'=>(int)$row['id']]);
        self::assertSame(201, $receipt['status'], $receipt['body']);
        $this->attached($row);
        $old = $this->rows('SELECT * FROM pool_file_moves WHERE pool_file_id=$1', [$row['id']])[0];
        self::assertSame('done', $old['state']);

        // Exercise the browser's return-to-pool action, including its document unlink.
        $query = http_build_query(['source'=>'creditorOrder', 'sourceId'=>$this->f['order'],
            'moveDoc'=>'../' . poolDocFolderName() . '/' . RestApiEnv::testDb() . '/' . $old['dest_path']]);
        $curl = curl_init(RestApiEnv::baseUrl() . '/includes/documents.php?' . $query);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20,
            CURLOPT_COOKIE=>'PHPSESSID=' . self::$session]);
        $html = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        self::assertSame(200, $status, substr(strip_tags((string)$html), -500));
        self::assertTrue(str_contains((string)$html, 'successfully moved to pool!'), substr(strip_tags((string)$html), -500));
        self::assertSame('ubehandlet', $this->rows('SELECT status FROM pool_files WHERE id=$1', [$row['id']])[0]['status']);
        self::assertCount(0, $this->rows('SELECT id FROM documents WHERE pool_file_id=$1', [$row['id']]));
        self::assertFileExists(self::$root . '/' . $row['storage_path']);

        $body = $this->attachBody($row);
        $target = self::$root . '/' . $old['dest_path'];
        $second = $this->direct($body, static function (string $point) use ($target): void {
            if ($point === 'K2') {
                file_put_contents($target, 'unrelated-final-collision');
            }
        });
        self::assertSame('pending', $second['body']['data']['attachment']['state']);
        $new = $this->rows('SELECT * FROM pool_file_moves WHERE client_id=$1', [$body['client_id']])[0];
        self::assertGreaterThan((int)$old['id'], (int)$new['id']);
        self::assertSame($old['dest_path'], $new['dest_path']);
        self::assertSame('committed', $new['state']);
        if ($moveState === 'lost') {
            unlink(self::$root . '/' . $new['staging_path']);
            unlink($target);
            $previous = $GLOBALS['connection'] ?? null;
            $GLOBALS['connection'] = self::$conn;
            try {
                self::assertSame('lost', pool_move_reconcile((int)$new['id']));
            } finally {
                $GLOBALS['connection'] = $previous;
            }
        }
        $this->rows("UPDATE mobile_goods_receipts SET attachment_state='failed',attachment_error='attachment_processed' WHERE id=$1",
            [$receipt['json']['data']['receipt_id']]);
        $retryBody = $this->attachBody($row);
        $retry = $this->request('POST', 'attach.php', $retryBody);
        self::assertSame(200, $retry['status'], $retry['body']);
        $expected = ['state'=>$state, 'attachment_id'=>(int)$row['id'], 'reason'=>$reason];
        self::assertSame($expected, $retry['json']['data']['attachment']);
        $stored = $this->rows('SELECT attachment_state,attachment_error FROM mobile_goods_receipts WHERE id=$1',
            [$receipt['json']['data']['receipt_id']])[0];
        self::assertSame($state, $stored['attachment_state']);
        self::assertSame($reason, $stored['attachment_error']);
        self::assertSame($expected, $this->service()->receipts($this->f['order'])['items'][0]['attachment']);
        $replay = $this->request('POST', 'attach.php', $retryBody);
        self::assertSame($retry['json'], $replay['json']);
        self::assertStringContainsString('Idempotent-Replayed: true', $replay['headers']);
        self::assertCount(2, $this->rows('SELECT id FROM pool_file_moves WHERE pool_file_id=$1', [$row['id']]));
        self::assertCount(1, $this->rows('SELECT id FROM documents WHERE pool_file_id=$1', [$row['id']]));
        self::assertSame('done', $this->rows('SELECT state FROM pool_file_moves WHERE id=$1', [$old['id']])[0]['state']);
        self::assertSame($moveState, $this->rows('SELECT state FROM pool_file_moves WHERE id=$1', [$new['id']])[0]['state']);
        self::assertSame('6.000', $this->rows('SELECT sum(antal) AS n FROM batch_kob WHERE linje_id=$1', [$this->f['line']])[0]['n']);

        if ($moveState === 'committed') {
            unlink($target);
            $previous = $GLOBALS['connection'] ?? null;
            $GLOBALS['connection'] = self::$conn;
            try {
                self::assertSame('done', pool_move_reconcile((int)$new['id']));
            } finally {
                $GLOBALS['connection'] = $previous;
            }
            $this->attached($row);
            self::assertSame(['state'=>'attached', 'attachment_id'=>(int)$row['id'], 'reason'=>null],
                $this->service()->receipts($this->f['order'])['items'][0]['attachment']);
        } else {
            self::assertSame(0, $this->fileCopies($row['sha256']));
        }
    }

    public function testForeignAndProcessedNotesAreRefused(): void
    {
        $row = $this->upload();
        $this->rows('UPDATE pool_files SET uploaded_by=$1 WHERE id=$2', [Subject::build('account', 987654),$row['id']]);
        $response = $this->request('POST', 'attach.php', $this->attachBody($row));
        self::assertSame(404, $response['status'], $response['body']);
        foreach (['attached','deleted','staged'] as $state) {
            $note = $this->upload();
            $this->rows('UPDATE pool_files SET status=$1 WHERE id=$2', [$state,$note['id']]);
            $this->refused($this->request('POST', 'attach.php', $this->attachBody($note)), 409, 'attachment_processed', 'conflict');
        }
        $note = $this->upload();
        $this->rows("UPDATE pool_files SET review_state='rejected' WHERE id=$1", [$note['id']]);
        $this->refused($this->request('POST', 'attach.php', $this->attachBody($note)), 409, 'attachment_processed', 'conflict');
    }

    public function testEndpointRightsMethodsAndValidation(): void
    {
        $body = $this->attachBody($this->upload());
        self::assertSame(401, $this->request('POST', 'attach.php', $body, null, [])['status']);
        foreach (['0','2'] as $right) {
            $digits = str_repeat('0', 16);
            $digits[7] = $right;
            $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2', [$digits,self::$user['id']]);
            self::assertSame(403, $this->request('POST', 'attach.php', $body)['status']);
        }
        $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2', [str_repeat('1',16),self::$user['id']]);
        self::assertSame(405, $this->request('GET', 'attach.php')['status']);
        $this->refused($this->request('POST', 'attach.php', array_replace($body, ['attachment_id'=>(string)$body['attachment_id']])), 422, 'invalid_id');
        $this->refused($this->request('POST', 'attach.php', array_diff_key($body, ['client_id'=>true])), 422, 'client_id_required');
        self::assertSame(404, $this->request('POST', 'attach.php', array_replace($body, ['order_id'=>2147483647]))['status']);
    }

    public static function crashCases(): array
    {
        $cases = [];
        foreach (['reserved','K1','K2','K3'] as $point) {
            foreach ([false,true] as $retry) { $cases[$point . ($retry ? ' retry' : ' reconcile')] = [$point,$retry]; }
        }
        return $cases;
    }

    #[DataProvider('crashCases')]
    public function testKilledProcessRecovery(string $point, bool $retry): void
    {
        $row = $this->upload();
        $body = $this->attachBody($row);
        $ledger = $this->ledger();
        $process = proc_open([PHP_BINARY, __DIR__ . '/support/run_receipt_attachment.php', RestApiEnv::testDb(),
            (string)self::$user['id'], (string)$this->f['order'], (string)$row['id'], $body['client_id'], $point],
            [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        self::assertIsResource($process);
        stream_set_blocking($pipes[1], false);
        $out = '';
        $deadline = microtime(true) + 10;
        while (!str_contains($out, 'READY') && microtime(true) < $deadline && proc_get_status($process)['running']) {
            $out .= stream_get_contents($pipes[1]);
            usleep(10000);
        }
        proc_terminate($process, 9);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        self::assertStringContainsString('READY', $out, $err);
        self::assertSame('', $err);
        $intent = $this->rows('SELECT * FROM pool_file_moves WHERE client_id=$1', [$body['client_id']])[0];
        if ($retry) {
            self::assertSame('attached', $this->direct($body)['body']['data']['attachment']['state']);
        } else {
            $previous = $GLOBALS['connection'] ?? null;
            $GLOBALS['connection'] = self::$conn;
            try {
                $state = pool_move_reconcile((int)$intent['id']);
                self::assertSame(in_array($point, ['reserved','K1'], true) ? 'aborted' : 'done', $state);
            } finally { $GLOBALS['connection'] = $previous; }
            if (in_array($point, ['reserved','K1'], true)) {
                self::assertCount(0, $this->rows('SELECT * FROM documents WHERE pool_file_id=$1', [$row['id']]));
                self::assertFileExists(self::$root . '/' . $row['storage_path']);
                self::assertSame(1, $this->fileCopies($row['sha256']));
                self::assertSame('ubehandlet', $this->rows('SELECT status FROM pool_files WHERE id=$1', [$row['id']])[0]['status']);
                self::assertSame('attached', $this->direct($body)['body']['data']['attachment']['state']);
            }
        }
        $this->attached($row);
        self::assertSame('attached', $this->direct($body)['body']['data']['attachment']['state']);
        $this->attached($row);
        self::assertSame($ledger, $this->ledger());
    }

    public function testAbortedReservationRetainsTheOriginalRequestHash(): void
    {
        $row = $this->upload();
        $body = $this->attachBody($row);
        try {
            $this->direct($body, static function (string $point): void {
                if ($point === 'K1') { throw new RuntimeException('Injected reservation failure'); }
            });
            self::fail('Expected injected failure');
        } catch (RuntimeException $error) {
            self::assertSame('Injected reservation failure', $error->getMessage());
        }
        $otherOrder = $this->fixture()['order'];
        try {
            $this->direct(array_replace($body, ['order_id'=>$otherOrder]));
            self::fail('Expected UUID reuse refusal');
        } catch (MobileT2Error $error) {
            self::assertSame('client_id_reused', $error->details['reason']);
        }
        self::assertSame('attached', $this->direct($body)['body']['data']['attachment']['state']);
        $this->attached($row);
    }
}
