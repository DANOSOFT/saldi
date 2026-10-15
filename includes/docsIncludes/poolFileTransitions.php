<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolFileTransitions.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Keep pool identity and document lists through reference unlink and journal copies.
// 20261001 CDX/LH Fix path-scoped lookup, release historical aliases and serialize legacy attachment completion.
// 20261001 CDX/LH Attribute lifecycle audit actors and reset review when returning files to the pool.
// 20261001 CDX/LH Extract attachment references, attach locked identities and protect approval move intents.
// 20261001 CDX/LH Preserve document ownership and finish reserved recovery from its durable destination.
// 20261007 CDX/LH Restore the shared tenant SQL modification trail after writes.

require_once __DIR__ . '/poolPaths.php';
require_once __DIR__ . '/poolAttach.php';
require_once __DIR__ . '/poolAudit.php';
require_once __DIR__ . '/../mobileUpdates.php';
require_once __DIR__ . '/../../restapi/core/Subject.php';

/** Stable identities shared by legacy file moves, attachment, tombstones and restoration. */
final class PoolFileTransitions
{
    private static int $depth = 0;
    private static array $moves = [];

    /** Run writes against the active tenant and fail before reporting a successful transition. */
    private static function write(string $sql): void
    {
        $connection = $GLOBALS['connection'];
        if ($connection instanceof mysqli) {
            $succeeded = mysqli_query($connection, $sql) !== false;
        } else {
            // Read the result status explicitly so a rejected INSERT becomes a rollback exception.
            $result = pg_send_query($connection, $sql) ? pg_get_result($connection) : false;
            $succeeded = $result !== false && in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true);
            while (pg_get_result($connection) !== false) {
                // Drain the connection before the rollback or next statement.
            }
        }
        db_modify_query_trail($connection, $sql, [], __FILE__, $succeeded,
            $succeeded ? '' : ($connection instanceof mysqli ? mysqli_error($connection)
                : ($result ? pg_result_error($result) : pg_last_error($connection))));
        if (!$succeeded) {
            throw new RuntimeException('Pool identity write failed');
        }
    }

    /** @return mixed Serialize against REST row locks and reverse completed moves on a failed DB write. */
    private static function atomic(Closure $operation)
    {
        if (self::$depth > 0) {
            return $operation();
        }
        self::ready();
        $connection = $GLOBALS['connection'];
        $inside = $connection instanceof mysqli ? (bool)($connection->server_status & 1)
            : pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE;
        self::write($inside ? 'SAVEPOINT b5_transition' : 'BEGIN');
        self::$depth++;
        self::$moves = [];
        try {
            $result = $operation();
            self::write($inside ? 'RELEASE SAVEPOINT b5_transition' : 'COMMIT');
            return $result;
        } catch (Throwable $error) {
            if ($connection instanceof mysqli || pg_connection_status($connection) === PGSQL_CONNECTION_OK) {
                self::write($inside ? 'ROLLBACK TO SAVEPOINT b5_transition' : 'ROLLBACK');
            }
            foreach (array_reverse(self::$moves) as [$from, $to]) {
                if (is_file($to) && !file_exists($from) && !rename($to, $from)) {
                    throw new RuntimeException('Pool move recovery failed', 0, $error);
                }
            }
            throw $error;
        } finally {
            self::$depth--;
            self::$moves = [];
        }
    }

    /** @return mixed Preserve an app scope, or attribute this legacy lifecycle transaction. */
    private static function audited(?int $userId, Closure $operation)
    {
        $connection = $GLOBALS['connection'];
        $current = $connection instanceof mysqli ? '' : (new MobileSchema($connection))
            ->rows("SELECT current_setting('saldi.actor',true) AS actor")[0]['actor'];
        if ($current !== null && $current !== '') {
            return self::atomic($operation);
        }
        return pool_audit_actor_scope(pool_audit_web_actor($userId), null,
            static fn() => self::atomic($operation));
    }

    /** Run schema before the legacy paths touch mobile columns. */
    public static function ready(): void
    {
        mobileTenantUpdates($GLOBALS['connection']);
    }

    /** @return ?array<string,mixed> A historical or mobile row, resolved by its DB location. */
    public static function row(string $db, string $path, bool $lock = false): ?array
    {
        self::ready();
        $relative = self::relative($db, $path);
        $escaped = db_escape_string($relative);
        $filename = db_escape_string(basename($path));
        $fallback = $relative === 'pulje/' . basename($path)
            ? " OR (storage_path IS NULL AND filename='$filename')" : '';
        $row = db_fetch_array(db_select("SELECT * FROM pool_files WHERE storage_path='$escaped'$fallback ORDER BY id LIMIT 1" . ($lock ? ' FOR UPDATE' : ''), __FILE__ . ' line ' . __LINE__));
        return $row ?: null;
    }

    /** @return string Relative path within the selected tenant, allowing a not-yet-created file. */
    public static function relative(string $db, string $path): string
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $db)) {
            throw new InvalidArgumentException('Invalid tenant');
        }
        clearstatcache(true);
        $root = realpath(dirname(poolPuljePath($db)));
        $parent = realpath(dirname($path));
        if ($root === false || $parent === false || is_link(dirname(poolPuljePath($db)))
            || ($parent !== $root && !str_starts_with($parent, $root . '/')) || is_link($path)
            || basename($path) === '.' || basename($path) === '..') {
            throw new RuntimeException('Pool file outside tenant document store');
        }
        return ltrim(substr($parent . '/' . basename($path), strlen($root)), '/');
    }

    /** @return string Bounded internal alias, leaving the pool filename free for reuse. */
    private static function historyName(array $row): string
    {
        $prefix = '.history-' . (int)$row['id'] . '-';
        return str_starts_with($row['filename'], $prefix) ? $row['filename'] : $prefix . mb_substr($row['filename'], 0, 220);
    }

    /**
     * Move, insert target/sibling references and attach under one pool row lock.
     * @param list<int> $targetIds Explicit sibling selection; empty selects the voucher's siblings.
     * @return bool Whether the file and every reference were attached successfully.
     */
    public static function insertDocument(string $db, string $from, string $to, int $globalId,
        string $source, int $sourceId, int $userId, array $targetIds = []): bool
    {
        return self::atomic(function () use ($db, $from, $to, $globalId, $source, $sourceId, $userId, $targetIds) {
            if (!self::move($db, $from, $to) || !is_file($to)) {
                return false;
            }
            $siblings = $source === 'kassekladde' ? ($targetIds ?: pool_attach_siblings($sourceId)) : [];
            pool_attach_document_rows(['filepath'=>'/' . dirname(self::relative($db, $to))],
                basename($to), $sourceId, $siblings, $globalId, $userId, null, $source);
            self::attach($db, $to, $userId);
            return true;
        });
    }

    /** @return bool Move the file or finish a recorded recovery rename, preserving the row id. */
    public static function move(string $db, string $from, string $to, ?int $recoverIntentId = null): bool
    {
        return self::atomic(function () use ($db, $from, $to, $recoverIntentId) {
            $row = self::row($db, $from, true);
            $relative = self::relative($db, $to);
            if ($recoverIntentId !== null) {
                $fromRelative = db_escape_string(self::relative($db, $from));
                $recoveryRelative = db_escape_string($relative);
                $recovery = pool_attach_query("SELECT p.* FROM pool_files p JOIN pool_file_moves m ON m.pool_file_id=p.id
                    WHERE m.id=$recoverIntentId AND m.state='reserved' AND m.dest_path='$recoveryRelative'
                    AND (m.staging_path='$fromRelative' OR m.dest_path='$fromRelative') FOR UPDATE OF p");
                if (!$recovery) { throw new RuntimeException('Invalid reserved move recovery'); }
                $row = $recovery[0];
            }
            if ($row && pool_attach_query("SELECT id FROM pool_file_moves WHERE pool_file_id=" . (int)$row['id'] . " AND state IN ('reserved','committed')" . ($recoverIntentId === null ? '' : ' AND id<>' . $recoverIntentId))) {
                return false;
            }
            $alreadyThere = $from === $to || realpath($from) === $to;
            if ($alreadyThere && $recoverIntentId === null) {
                return true;
            }
            if (!$alreadyThere) {
                if (file_exists($to) || !pool_attach_move($from, $to)) {
                    return false;
                }
                self::$moves[] = [$from, $to];
            }
            self::releaseName($db, $to);
            if ($row) {
                $id = (int)$row['id'];
                $filename = db_escape_string(basename($to));
                $relative = db_escape_string($relative);
                self::write("UPDATE pool_files SET storage_path='$relative',filename='$filename',updated=CURRENT_TIMESTAMP WHERE id=$id");
                $oldName = db_escape_string(basename($from));
                $oldFolder = db_escape_string('/' . trim(dirname(self::relative($db, $from)), './'));
                $folder = db_escape_string('/' . trim(dirname(self::relative($db, $to)), './'));
                self::write("UPDATE documents SET filename='$filename',filepath='$folder' WHERE pool_file_id=$id "
                    . "OR (filename='$oldName' AND filepath='$oldFolder')");
            }
            return true;
        });
    }

    /**
     * Link every target/sibling document row after legacy insertion, retaining upload ownership.
     * @return void
     */
    public static function attach(string $db, string $path, int $userId, ?int $bilag = null): void
    {
        self::audited($userId, function () use ($db, $path, $userId, $bilag) {
            $row = self::row($db, $path, true);
            if (!$row) {
                return;
            }
            self::attachIdentity($db, (int)$row['id'], $path, $userId, $bilag);
        });
    }

    /** @return void Attach an explicitly locked identity, including a future destination after decision commit. */
    public static function attachIdentity(string $db, int $poolFileId, string $destination, int $userId, ?int $bilag = null): void
    {
        self::audited($userId, function () use ($db, $poolFileId, $destination, $userId, $bilag) {
            $rows = pool_attach_query("SELECT * FROM pool_files WHERE id=$poolFileId FOR UPDATE");
            if (!$rows) { throw new RuntimeException('Pool identity missing'); }
            $row = $rows[0];
            $path = $destination;
            if (!in_array($row['status'], ['ubehandlet', 'attached'], true)) {
                throw new RuntimeException('Pool identity cannot be attached');
            }
            $id = (int)$row['id'];
            $relative = pool_attach_relative($db, $path);
            $folder = db_escape_string('/' . trim(dirname($relative), './'));
            $filename = db_escape_string(basename($relative));
            $matching = "filename='$filename' AND (filepath='$folder' OR filepath='" . db_escape_string(ltrim($folder, '/')) . "')";
            if (pool_attach_query("SELECT id FROM documents WHERE $matching AND pool_file_id IS NOT NULL AND pool_file_id<>$id")) {
                throw new RuntimeException('Attachment destination belongs to another pool identity');
            }
            self::write("UPDATE documents SET pool_file_id=$id WHERE $matching AND (pool_file_id IS NULL OR pool_file_id=$id)");
            self::refreshDocuments($id);
            if ($bilag === null) {
                $voucher = db_fetch_array(db_select("SELECT k.bilag FROM documents d JOIN kassekladde k ON k.id=d.source_id "
                    . "WHERE d.pool_file_id=$id AND d.source='kassekladde' ORDER BY d.id LIMIT 1", __FILE__ . ' line ' . __LINE__));
                $bilag = $voucher ? (int)$voucher['bilag'] : null;
            }
            $tenantConnection = $GLOBALS['connection'];
            try {
                $actor = $userId > 0 ? Subject::build(!empty($GLOBALS['revisor']) ? 'registry' : 'account', $userId) : null;
            } finally {
                // Subject's first CLI use loads connect.php, which can select the registry link.
                $GLOBALS['connection'] = $tenantConnection;
            }
            $actorSql = $actor === null ? 'NULL' : "'" . db_escape_string($actor) . "'";
            $bilagSql = $bilag === null ? 'NULL' : (string)$bilag;
            $relativeSql = db_escape_string($relative);
            $alias = db_escape_string(self::historyName($row));
            self::write("UPDATE pool_files SET filename='$alias',status='attached',storage_path='$relativeSql',attached_at=CURRENT_TIMESTAMP,updated=CURRENT_TIMESTAMP,"
                . "attached_by=$actorSql,bilag_nr=$bilagSql,deleted_at=NULL WHERE id=$id");
        });
    }

    /** @return void Remove a matching reference while retaining the file and attached pool identity. */
    public static function unlinkDocument(int $documentId, string $source, int $sourceId): void
    {
        self::atomic(function () use ($documentId, $source, $sourceId) {
            $sourceSql = db_escape_string($source);
            $document = db_fetch_array(db_select("SELECT pool_file_id FROM documents WHERE id=$documentId
                AND source='$sourceSql' AND source_id='$sourceId'", __FILE__ . ' line ' . __LINE__));
            $poolId = (int)ifset($document, 'pool_file_id', 0);
            if ($poolId) {
                db_select("SELECT id FROM pool_files WHERE id=$poolId FOR UPDATE", __FILE__ . ' line ' . __LINE__);
            }
            self::write("DELETE FROM documents WHERE id=$documentId AND source='$sourceSql' AND source_id='$sourceId'");
            if ($poolId) {
                self::refreshDocuments($poolId);
            }
        });
    }

    /** @return void Copy a journal reference and attach its sibling to the same pool identity. */
    public static function copyDocument(string $db, int $documentId, int $sourceId, int $userId): void
    {
        self::atomic(function () use ($db, $documentId, $sourceId, $userId) {
            $original = db_fetch_array(db_select("SELECT * FROM documents WHERE id=$documentId", __FILE__ . ' line ' . __LINE__));
            if (!$original) {
                return;
            }
            $path = dirname(poolPuljePath($db)) . '/' . ltrim($original['filepath'], '/') . '/' . $original['filename'];
            $row = self::row($db, $path, true);
            $filename = db_escape_string($original['filename']);
            $folder = db_escape_string($original['filepath']);
            $global = $original['global_id'] === null ? 'NULL' : (string)(int)$original['global_id'];
            $poolId = $row ? (string)(int)$row['id'] : 'NULL';
            $timestamp = time();
            self::write("INSERT INTO documents(global_id,filename,filepath,source,source_id,timestamp,user_id,pool_file_id)
                SELECT $global,'$filename','$folder','kassekladde','$sourceId','$timestamp','$userId',$poolId
                WHERE NOT EXISTS (SELECT 1 FROM documents WHERE source='kassekladde' AND source_id='$sourceId'
                    AND filename='$filename' AND filepath='$folder')");
            self::attach($db, $path, $userId);
        });
    }

    /** Rebuild the JSON document-id list after linking/unlinking a sibling. */
    public static function refreshDocuments(int $id): void
    {
        $result = db_select("SELECT id FROM documents WHERE pool_file_id=$id ORDER BY id", __FILE__ . ' line ' . __LINE__);
        $ids = [];
        while ($row = db_fetch_array($result)) {
            $ids[] = (int)$row['id'];
        }
        $json = db_escape_string(json_encode($ids));
        self::write("UPDATE pool_files SET document_ids='$json' WHERE id=$id");
    }

    /** @return bool Move tracked files to the recovery folder; auxiliary/untracked files keep legacy deletion. */
    public static function delete(string $db, string $path): bool
    {
        return self::audited(null, function () use ($db, $path) {
            $row = self::row($db, $path, true);
            if (!$row) {
                return !is_file($path) || unlink($path);
            }
            if ($row['status'] === 'deleted') {
                return true;
            }
            $id = (int)$row['id'];
            $relative = self::relative($db, $path);
            if (is_file($path)) {
                $directory = dirname(poolPuljePath($db)) . '/pulje/.deleted';
                if (is_link($directory) || (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory))) {
                    throw new RuntimeException('Unable to create recovery folder');
                }
                $target = $directory . '/' . $id . '.' . pathinfo($path, PATHINFO_EXTENSION);
                $relative = self::relative($db, $target);
                if (!self::move($db, $path, $target)) {
                    return false;
                }
            }
            $relative = db_escape_string($relative);
            $alias = db_escape_string(self::historyName($row));
            self::write("UPDATE pool_files SET filename='$alias',status='deleted',storage_path='$relative',deleted_at=CURRENT_TIMESTAMP,updated=CURRENT_TIMESTAMP WHERE id=$id");
            return true;
        });
    }

    /** @return bool Restore a tombstone or attached file to the pool, retaining its identity and metadata. */
    public static function restore(string $db, string $from, string $to): bool
    {
        return self::audited(null, function () use ($db, $from, $to) {
            $row = self::row($db, $from, true);
            if (!self::move($db, $from, $to)) {
                return false;
            }
            if ($row) {
                $id = (int)$row['id'];
                self::write("UPDATE pool_files SET status='ubehandlet',review_state='pending',reviewed_by=NULL,reviewed_at=NULL,
                    rejection_code=NULL,rejection_note=NULL,resubmitted_at=NULL,deleted_at=NULL,attached_at=NULL,attached_by=NULL,bilag_nr=NULL WHERE id=$id");
                self::refreshDocuments($id);
            }
            return true;
        });
    }

    /**
     * Free a reused pool alias while retaining the earlier attached/deleted row and its storage path.
     * @return void
     */
    public static function releaseName(string $db, string $path): void
    {
        self::atomic(function () use ($db, $path) {
            $relative = self::relative($db, $path);
            $filename = db_escape_string(basename($path));
            $row = db_fetch_array(db_select("SELECT * FROM pool_files WHERE filename='$filename' FOR UPDATE", __FILE__ . ' line ' . __LINE__));
            if (!$row || !in_array($row['status'], ['attached', 'deleted'], true)
                || ($row['status'] === 'attached' && $row['storage_path'] === $relative)) {
                return;
            }
            $id = (int)$row['id'];
            $alias = db_escape_string(self::historyName($row));
            self::write("UPDATE pool_files SET filename='$alias' WHERE id=$id");
        });
    }

    /** @return bool Adopt a successfully converted file while retaining the pool identity. */
    public static function converted(string $db, string $from, string $to): bool
    {
        if (!is_file($to) || filesize($to) <= 10) {
            return false;
        }
        self::atomic(function () use ($db, $from, $to) {
            $row = self::row($db, $from, true);
            $relative = db_escape_string(self::relative($db, $to));
            self::releaseName($db, $to);
            if ($row) {
                $id = (int)$row['id'];
                $filename = db_escape_string(basename($to));
                self::write("UPDATE pool_files SET storage_path='$relative',filename='$filename',thumbnail_path=NULL,updated=CURRENT_TIMESTAMP WHERE id=$id");
            }
        });
        return $from === $to || unlink($from);
    }

    /** Tombstone missing unprocessed rows; never discard attached, staged or deleted identities. */
    public static function missing(string $predicate): void
    {
        self::ready();
        self::write("UPDATE pool_files SET filename=CONCAT('.history-',id,'-',LEFT(filename,220)),
            status='deleted',deleted_at=CURRENT_TIMESTAMP,updated=CURRENT_TIMESTAMP WHERE status='ubehandlet' AND ($predicate)
            AND NOT EXISTS (SELECT 1 FROM pool_file_moves m WHERE m.pool_file_id=pool_files.id AND m.state IN ('reserved','committed'))");
    }
}
