<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiDocumentAttach.php --- ai/mcp-assistant --- 2026.10.03
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
// Attach pool documents to approved journal lines and reverse intact attachments.
// Receipts are private storage data, not tool output: they contain paths and raw metadata.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/../docsIncludes/poolPaths.php';

final class SaldiDocumentAttach
{
	private string $appRoot;

	/** The optional installation root isolates tests from real document folders. */
	public function __construct(private SaldiTenantContext $ctx, private SaldiDb $db, ?string $appRoot = null)
	{
		$this->appRoot = $appRoot ?? (getenv('SALDI_DOCUMENT_APP_ROOT') ?: dirname(__DIR__, 2));
		if ($ctx->db !== $db->name() || !preg_match('/^[A-Za-z0-9_]+$/D', $ctx->db)) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Invalid document tenant');
		}
	}

	/** @return array<string,mixed> Original pool metadata, including id and filename. */
	public function pool(string $ref): array
	{
		if (!preg_match('/^pool:([1-9][0-9]{0,14})$/D', $ref, $match)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid document reference');
		}
		$row = $this->db->one('SELECT * FROM pool_files WHERE id = ?' . ($this->db->inTransaction() ? ' FOR UPDATE' : ''), [(int)$match[1]]);
		if (!$row) {
			throw new SaldiRuntimeError('CONFLICT', 'Pool document no longer exists', ['reason' => 'document_missing']);
		}
		$this->filename($row['filename']);
		return $row;
	}

	private function filename(string $filename): void
	{
		if ($filename === '' || $filename === '.' || $filename === '..' || preg_match('#[/\\\\\x00-\x1f]#', $filename)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid document filename');
		}
	}

	private function contained(string $path): bool
	{
		$root = realpath(dirname(poolPuljePath($this->ctx->db, $this->appRoot)));
		$real = realpath($path);
		return $root !== false && $real !== false && ($real === $root || str_starts_with($real, $root . '/'));
	}

	/**
	 * Called last in the journal apply transaction. A move is not transactional: a later
	 * failed commit can leave an orphan file. The shutdown check logs that residual risk.
	 *
	 * @return array{document_id:int,pool_row:array,from:string,to:string,document_sha256:string,file_sha256:string}
	 */
	public function attach(string $ref, int $journalId, int $lineId): array
	{
		if (!$this->db->inTransaction() || !$this->ctx->isHuman()) {
			throw new LogicException('Document attachment requires a human approval transaction');
		}
		$this->lockDocuments();
		$row = $this->pool($ref);
		if (!$this->db->value('SELECT id FROM kassekladde WHERE id = ? AND kladde_id = ?', [$lineId, $journalId])) {
			throw new SaldiRuntimeError('CONFLICT', 'Journal line missing', ['reason' => 'document_missing']);
		}
		$pool = poolPuljePath($this->ctx->db, $this->appRoot);
		$from = $pool . '/' . $row['filename'];
		if (!is_file($from) || !$this->contained($from)) {
			throw new SaldiRuntimeError('CONFLICT', 'Document file missing', ['reason' => 'document_missing']);
		}
		$relative = '/finance/' . $journalId . '/' . $lineId;
		$directory = dirname($pool) . $relative;
		$ancestor = $directory;
		while (!file_exists($ancestor)) {
			$ancestor = dirname($ancestor);
		}
		if (!$this->contained($ancestor) || (!is_dir($directory) && !mkdir($directory, 0775, true)) || !$this->contained($directory)) {
			throw new SaldiRuntimeError('CONFLICT', 'Document directory unavailable', ['reason' => 'document_missing']);
		}
		$to = $directory . '/' . $row['filename'];
		$hash = hash_file('sha256', $from);
		$documentId = $this->db->insert('documents', [
			'global_id' => (string)($this->db->value("SELECT var_value FROM settings WHERE var_name = ? ORDER BY id LIMIT 1", ['globalId']) ?? '1'),
			'filename' => $row['filename'], 'filepath' => $relative, 'source' => 'kassekladde',
			'source_id' => $lineId, 'timestamp' => (string)time(), 'user_id' => $this->ctx->userId,
		]);
		$document = $this->db->one('SELECT * FROM documents WHERE id = ?', [$documentId]);
		$this->db->exec('DELETE FROM pool_files WHERE id = ?', [$row['id']]);
		if (file_exists($to) || is_link($to) || !rename($from, $to)) {
			throw new SaldiRuntimeError('CONFLICT', 'Document move failed', ['reason' => 'document_missing']);
		}
		$config = saldi_runtime_config();
		$database = $this->ctx->db;
		register_shutdown_function(static function () use ($config, $database, $documentId, $to): void {
			if (!is_file($to)) {
				return;
			}
			try {
				$check = SaldiDb::connect($config, $database);
				if ($check->value('SELECT id FROM documents WHERE id = ?', [$documentId]) === null) {
					error_log('Saldi document move survived rollback; document_id=' . $documentId);
				}
				$check->close();
			} catch (Throwable $error) {
				error_log('Saldi document move commit check unavailable; document_id=' . $documentId);
			}
		});
		return ['document_id' => $documentId, 'pool_row' => $row, 'from' => $from, 'to' => $to,
			'document_sha256' => hash('sha256', saldi_runtime_json($document)), 'file_sha256' => $hash];
	}

	/** Freeze file references before row locks; legacy document linking also takes this table lock implicitly. */
	private function lockDocuments(): void
	{
		$this->db->lock('saldi_documents_detach');
		if ($this->db->isPostgres()) {
			$this->db->exec('LOCK TABLE documents IN SHARE ROW EXCLUSIVE MODE');
		}
	}
	/** Inspect the original row and file without modifying either. */
	public function canDetach(array $receipt): bool
	{
		if (!$this->db->inTransaction()) {
			return $this->db->transaction(fn(): bool => $this->canDetach($receipt));
		}
		$this->lockDocuments();
		$row = $this->db->one('SELECT * FROM documents WHERE id = ?' . ($this->db->inTransaction() ? ' FOR UPDATE' : ''), [$receipt['document_id']]);
		return $row !== null
			&& $this->db->value('SELECT id FROM documents WHERE filepath = ? AND filename = ? AND id <> ? LIMIT 1 FOR UPDATE', [$row['filepath'], $row['filename'], $row['id']]) === null
			&& hash_equals($receipt['document_sha256'], hash('sha256', saldi_runtime_json($row)))
			&& is_file($receipt['to']) && $this->contained($receipt['to']) && $this->contained(dirname($receipt['from']))
			&& !file_exists($receipt['from']) && !is_link($receipt['from'])
			&& is_writable(dirname($receipt['from'])) && is_writable(dirname($receipt['to']))
			&& hash_equals($receipt['file_sha256'], hash_file('sha256', $receipt['to']))
			&& $this->db->value('SELECT id FROM pool_files WHERE filename = ?', [$receipt['pool_row']['filename']]) === null;
	}

	/** @return array{pool_id:int} */
	public function detach(array $receipt): array
	{
		if (!$this->db->inTransaction() || !$this->ctx->isHuman()) {
			throw new LogicException('Document detach requires a human undo transaction');
		}
		if (!$this->canDetach($receipt)) {
			throw new SaldiRuntimeError('CONFLICT', 'Attachment changed', ['reason' => 'undo_changed']);
		}
		$pool = $receipt['pool_row'];
		unset($pool['id']);
		foreach ($pool as $column => $value) {
			if (is_bool($value)) {
				$pool[$column] = $this->db->isPostgres() ? ($value ? 'true' : 'false') : (int)$value;
			}
		}
		$poolId = $this->db->insert('pool_files', $pool);
		$this->db->exec('DELETE FROM documents WHERE id = ?', [$receipt['document_id']]);
		if (!rename($receipt['to'], $receipt['from'])) {
			throw new SaldiRuntimeError('CONFLICT', 'Document detach failed', ['reason' => 'undo_changed']);
		}
		$config = saldi_runtime_config();
		$database = $this->ctx->db;
		register_shutdown_function(static function () use ($config, $database, $receipt): void {
			if (!is_file($receipt['from'])) {
				return;
			}
			try {
				$check = SaldiDb::connect($config, $database);
				if ($check->value('SELECT id FROM documents WHERE id = ?', [$receipt['document_id']]) !== null) {
					$restored = !file_exists($receipt['to']) && !is_link($receipt['to']) && rename($receipt['from'], $receipt['to']);
					error_log('Saldi document undo survived rollback; document_id=' . $receipt['document_id'] . '; restored=' . (int)$restored);
				}
				$check->close();
			} catch (Throwable $error) {
				error_log('Saldi document undo commit check unavailable; document_id=' . $receipt['document_id']);
			}
		});
		return ['pool_id' => $poolId];
	}
}
