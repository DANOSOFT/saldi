<?php

// --- tests/includes/automation/SaldiDocumentAttachTest.test.php --- ai/mcp-assistant --- 2026.10.03
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
//
// Attachment round trips, unchanged receipts, missing files and root resolution.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Add regression coverage for the confirmed Assist review findings.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiDocumentTestCase.php';
if (!defined('SALDI_INBOX_ACTION_LIBRARY')) {
    define('SALDI_INBOX_ACTION_LIBRARY', true);
}
require_once __DIR__ . '/../../../inbox/action.php';

final class SaldiDocumentAttachTest extends SaldiDocumentTestCase
{
    public function testApprovalAndUndoRestorePoolMetadataAndFile(): void
    {
        $db = $this->env->db;
        $poolId = $this->pool(['filename' => 'round-trip.pdf']);
        $poolRow = $db->one('SELECT * FROM pool_files WHERE id = ?', [$poolId]);
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->draft([
            'document_ref' => 'pool:' . $poolId,
        ]));
        self::assertSame('round-trip.pdf', $item['card']['fields'][1]['value']);
        self::assertFileExists($this->poolPath('round-trip.pdf'));
        $applied = $this->approve($item);
        $receipt = $applied['result']['document'];
        self::assertTrue($applied['can_undo']);
        self::assertFileDoesNotExist($receipt['from']);
        self::assertFileExists($receipt['to']);
        self::assertEquals($poolRow, $receipt['pool_row']);
        $document = $db->one('SELECT * FROM documents WHERE id = ?', [$receipt['document_id']]);
        self::assertSame('kassekladde', $document['source']);
        self::assertSame($applied['result']['line_ids'][0], (int)$document['source_id']);
        self::assertSame(910001, (int)$document['user_id']);
        self::assertSame(
            '/finance/' . $applied['result']['kladde_id'] . '/' . $document['source_id'],
            $document['filepath']
        );
        self::assertMatchesRegularExpression('/^[0-9]+$/', (string)$document['timestamp']);
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM pool_files'));
        $undone = $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash());
        self::assertSame('undone', $undone['status']);
        self::assertSame('round-trip.pdf', $undone['card']['fields'][1]['value']);
        self::assertFileExists($receipt['from']);
        self::assertFileDoesNotExist($receipt['to']);
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM documents'));
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM kassekladde'));
        $restored = $db->one('SELECT * FROM pool_files');
        self::assertNotSame($poolId, (int)$restored['id']);
        unset($restored['id'], $poolRow['id']);
        self::assertSame($poolRow, $restored);
    }

    public function testMissingFileConflictsWithoutApplyingAnyRows(): void
    {
        $poolId = $this->pool([], false);
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->draft([
            'document_ref' => 'pool:' . $poolId,
        ]));
        $error = $this->error('CONFLICT', fn() => $this->approve($item));
        self::assertSame('document_missing', $error->detail['reason']);
        foreach (['kassekladde', 'kladdeliste', 'documents'] as $table) {
            self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM ' . $table));
        }
        self::assertSame('open', $this->inbox()->get($this->env->ctx(), $item['id'])['status']);
        self::assertNotNull($this->env->db->value('SELECT id FROM pool_files WHERE id = ?', [$poolId]));
    }

    public function testChangedAttachmentAndReplacedFileBlockUndo(): void
    {
        $poolId = $this->pool();
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->draft([
            'document_ref' => 'pool:' . $poolId,
        ]));
        $applied = $this->approve($item);
        $receipt = $applied['result']['document'];
        $this->env->db->exec('UPDATE documents SET user_id = ? WHERE id = ?', [910002, $receipt['document_id']]);
        self::assertFalse($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
        $this->error('CONFLICT', fn() => $this->inbox()->undo(
            $this->env->ctx(),
            $item['id'],
            $this->env->ctx()->tenantHash()
        ));
        $this->env->db->exec('UPDATE documents SET user_id = ? WHERE id = ?', [910001, $receipt['document_id']]);
        file_put_contents($receipt['to'], 'Changed file contents');
        self::assertFalse($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
        self::assertCount(1, $this->env->db->all('SELECT id FROM kassekladde'));
    }

    public function testExistingDestinationNeverGetsOverwritten(): void
    {
        $poolId = $this->pool(['filename' => 'existing.pdf']);
        $db = $this->env->db;
        $db->insert('kladdeliste', ['id' => 10, 'bogfort' => '-']);
        $lineId = $db->insert('kassekladde', ['kladde_id' => 10]);
        $directory = $this->appRoot . '/bilag/' . $this->env->name . '/finance/10/' . $lineId;
        mkdir($directory, 0775, true);
        file_put_contents($directory . '/existing.pdf', 'Do not replace');
        $this->error('CONFLICT', fn() => $db->transaction(
            fn() => (new SaldiDocumentAttach($this->env->ctx(), $db))->attach('pool:' . $poolId, 10, $lineId)
        ));
        self::assertSame('Do not replace', file_get_contents($directory . '/existing.pdf'));
        self::assertFileExists($this->poolPath('existing.pdf'));
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM documents'));
    }

    public function testRootResolutionUsesLegacyPriorityAndTenantSubdirectory(): void
    {
        mkdir($this->appRoot . '/documents');
        mkdir($this->appRoot . '/owncloud/' . $this->env->name . '/pulje', 0775, true);
        self::assertSame('owncloud', poolDocFolderName($this->appRoot));
        $poolId = $this->pool(['filename' => 'cloud.pdf'], false);
        $from = $this->appRoot . '/owncloud/' . $this->env->name . '/pulje/cloud.pdf';
        file_put_contents($from, 'Cloud fixture');
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->draft([
            'document_ref' => 'pool:' . $poolId,
        ]));
        $applied = $this->approve($item);
        self::assertSame($from, $applied['result']['document']['from']);
        self::assertStringContainsString(
            '/owncloud/' . $this->env->name . '/finance/',
            $applied['result']['document']['to']
        );
    }

    public function testTraversalAndExternalSymlinksAreRejected(): void
    {
        $poolId = $this->pool(['filename' => '../outside.pdf'], false);
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'journal.draft_entry',
            $this->draft(['document_ref' => 'pool:' . $poolId])
        ));
        $poolId = $this->pool(['filename' => 'symlink.pdf'], false);
        file_put_contents($this->appRoot . '/outside.pdf', 'Outside tenant');
        symlink($this->appRoot . '/outside.pdf', $this->poolPath('symlink.pdf'));
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->draft([
            'document_ref' => 'pool:' . $poolId,
        ]));
        $this->error('CONFLICT', fn() => $this->approve($item));
        self::assertSame('Outside tenant', file_get_contents($this->appRoot . '/outside.pdf'));
    }

    public function testNewAttachmentsToInsertedLinesPreventOrphaningDuringUndo(): void
    {
        $item = $this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->draft());
        $applied = $this->approve($item);
        $this->env->db->insert('documents', [
            'source' => 'kassekladde', 'source_id' => $applied['result']['line_ids'][0], 'filename' => 'human.pdf',
        ]);
        self::assertFalse($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
        $this->error('CONFLICT', fn() => $this->inbox()->undo(
            $this->env->ctx(),
            $item['id'],
            $this->env->ctx()->tenantHash()
        ));
    }
    public function testLinkedDocumentFileCannotBeUndone(): void
    {
        $id = $this->pool();
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->draft(['document_ref' => 'pool:' . $id])));
        $receipt = $item['result']['document'];
        $row = $this->env->db->one('SELECT * FROM documents WHERE id = ?', [$receipt['document_id']]);
        unset($row['id']);
        $row['source_id'] = $item['result']['line_ids'][0] + 100;
        $this->env->db->insert('documents', $row);
        self::assertFalse($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
        $this->error('CONFLICT', fn() => $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash()));
        self::assertFileExists($receipt['to']);
        self::assertFileDoesNotExist($receipt['from']);
        self::assertSame(2, (int)$this->env->db->value('SELECT COUNT(*) FROM documents'));
    }

    public function testRolledBackUndoRestoresAttachedFileAtShutdown(): void
    {
        $id = $this->pool();
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'journal.draft_entry', $this->draft(['document_ref' => 'pool:' . $id])));
        $receipt = $item['result']['document'];
        $code = <<<'PHP'
require $argv[1];
$data = json_decode(stream_get_contents(STDIN), true, 64, JSON_THROW_ON_ERROR);
$db = SaldiDb::connect(saldi_runtime_config(), $data['database']);
$ctx = saldi_runtime_context_for_user($db, $data['regnskab'], 910001, 'user:910001');
try {
    $db->transaction(function () use ($ctx, $db, $data): void {
        (new SaldiDocumentAttach($ctx, $db, $data['root']))->detach($data['receipt']);
        throw new RuntimeException('Simulated undo rollback');
    });
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'Simulated undo rollback') {
        throw $error;
    }
}
$db->close();
PHP;
        $process = proc_open([PHP_BINARY, '-r', $code, dirname(__DIR__, 3) . '/includes/automation/SaldiDocumentAttach.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode(['database' => $this->env->name, 'regnskab' => $this->env->regnskab, 'root' => $this->appRoot, 'receipt' => $receipt], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame('', $output);
        self::assertStringContainsString('restored=1', $errors);
        self::assertFileExists($receipt['to']);
        self::assertFileDoesNotExist($receipt['from']);
        self::assertNotNull($this->env->db->value('SELECT id FROM documents WHERE id = ?', [$receipt['document_id']]));
    }

    public function testInboxEndpointRedactsPrivateReceiptsForEveryOperation(): void
    {
        $_SESSION['inbox_csrf'] = bin2hex(random_bytes(32));
        $ctx = $this->env->ctx();
        $db = $this->env->db;
        $call = function (string $op, array $item) use ($ctx, $db): array {
            $response = saldi_inbox_action_handle([
                'REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'saldi.test', 'HTTP_ORIGIN' => 'http://saldi.test',
                'HTTP_X_SALDI_CSRF' => $_SESSION['inbox_csrf'],
            ], json_encode(['op' => $op, 'item_id' => $item['id'], 'digest' => $item['digest'], 'tenant_hash' => $ctx->tenantHash()], JSON_THROW_ON_ERROR), $ctx->userId, static fn(): array => ['ctx' => $ctx, 'db' => $db]);
            self::assertSame(200, $response['status']);
            $json = json_encode($response['body'], JSON_THROW_ON_ERROR);
            foreach ([$this->appRoot, 'pool_row', 'vendor_iban', 'synthetic-bank-marker', '"from":', '"to":'] as $private) {
                self::assertStringNotContainsString($private, $json);
            }
            return $response['body']['item'];
        };
        $id = $this->pool();
        $proposal = $this->inbox()->create($ctx, 'journal.draft_entry', $this->draft(['document_ref' => 'pool:' . $id]));
        $item = $call('approve', $proposal);
        self::assertCount(1, $item['result']['document']);
        $call('preview', $item);
        $call('undo', $item);
        foreach (['reject', 'cancel'] as $op) {
            $id = $this->pool();
            $proposal = $this->inbox()->create($ctx, 'journal.draft_entry', $this->draft(['document_ref' => 'pool:' . $id]));
            $db->exec('UPDATE inbox_items SET result = ? WHERE id = ?', [saldi_runtime_json(['document' => ['document_id' => 123, 'pool_row' => ['vendor_iban' => 'synthetic-bank-marker'], 'from' => $this->appRoot]]), $proposal['id']]);
            $call('preview', $proposal);
            $call($op, $proposal);
        }
    }

}
