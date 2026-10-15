<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/models/attachment/DurableAttachmentModel.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Resolve upload reminders atomically with the durable identity.
// 20261001 CDX/LH Refresh filesystem containment checks after external symlink replacement.
// 20261001 CDX/LH Commit reminder reservations before files, refresh transition timestamps and preserve legacy XML MIME.
// 20261001 CDX/LH Add review fields and derive display status from current document and journal links.
// 20261001 CDX/LH Link fresh and replayed reminder uploads and release bank lines on deletion.

// 20261001 CDX/LH Publish claim hooks atomically and retain publication intent through staged retries.
// 20261001 CDX/LH Validate locked upload reservations before committing publication intent.
// 20261001 CDX/LH Withdraw mileage statements alongside bank-line release during deletion.

require_once __DIR__ . '/../../core/Idempotency.php';
require_once __DIR__ . '/AttachmentException.php';
require_once __DIR__ . '/AttachmentUpload.php';
require_once __DIR__ . '/../../services/ReminderService.php';
require_once __DIR__ . '/../../core/RequestId.php';
require_once __DIR__ . '/../../../includes/docsIncludes/poolPaths.php';
require_once __DIR__ . '/../../../includes/docsIncludes/poolMetadata.php';

/** Tenant-scoped durable identities, row locks, and shared authorized reads for B5. */
final class DurableAttachmentModel
{
    private $connection;
    private bool $mysql;
    private string $root;
    private string $owner;
    private bool $readAll;
    private array $columns;

    public function __construct($connection, string $db, string $owner, bool $readAll = false)
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $db) || $owner === '') {
            throw new RuntimeException('Invalid server attachment context');
        }
        $this->connection = $connection;
        $this->mysql = $connection instanceof mysqli;
        $this->root = dirname(poolPuljePath($db));
        $this->owner = $owner;
        $this->readAll = $readAll;
        $schema = $this->mysql ? 'table_schema=DATABASE()' : "table_schema='public'";
        $this->columns = array_column($this->query("SELECT column_name FROM information_schema.columns WHERE $schema AND table_name='pool_files'"), 'column_name');
    }

    /** @return string Canonical UUID v4. */
    public static function uuid($value): string
    {
        if (!is_string($value) || !preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value)) {
            throw new AttachmentException('validation_failed', 'Expected a UUID v4', 422);
        }
        return strtolower($value);
    }

    /** Execute parameterized business SQL without legacy helpers' ignored write failures. @return list<array<string,mixed>> */
    private function query(string $sql, array $params = []): array
    {
        if ($this->mysql) {
            $sql = preg_replace('/\$[0-9]+/', '?', $sql);
            $statement = mysqli_prepare($this->connection, $sql);
            if (!$statement || !$statement->execute($params)) {
                throw new RuntimeException('Attachment database statement failed');
            }
            $result = $statement->get_result();
            $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $statement->close();
            return $rows;
        }
        $result = $params === [] ? pg_query($this->connection, $sql) : pg_query_params($this->connection, $sql, $params);
        if ($result === false) {
            throw new RuntimeException('Attachment database statement failed');
        }
        return pg_num_fields($result) > 0 ? pg_fetch_all($result) : [];
    }

    /** Roll back failed work when the connection still exists; a severed PostgreSQL link has already rolled back. */
    private function rollback(): void
    {
        if ($this->mysql || pg_connection_status($this->connection) === PGSQL_CONNECTION_OK) {
            $this->query('ROLLBACK');
        }
    }

    /** @return array<string,mixed> Row from a selector, with owner/read-right scope applied in SQL. */
    public function find(array $selector, bool $lock = false, bool $ownOnly = false): array
    {
        $keys = array_intersect(['id', 'client_id', 'file'], array_keys($selector));
        if (count($keys) !== 1) {
            throw new AttachmentException('validation_failed', 'Use exactly one of id, client_id or file', 422);
        }
        $key = reset($keys);
        $value = $selector[$key];
        if ($key === 'id' && (is_int($value) || (is_string($value) && preg_match('/\A[1-9][0-9]{0,9}\z/', $value)))) {
            $field = 'id';
            $value = (int)$value;
            if ($value < 1 || $value > 2147483647) {
                throw new AttachmentException('validation_failed', 'Invalid attachment id', 422);
            }
        } elseif ($key !== 'file') {
            $field = 'client_id';
            $value = self::uuid($value);
        } else {
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 255 || $value === ''
                || str_contains($value, '/') || str_contains($value, '\\') || str_contains($value, '%')
                || preg_match('/[\x00-\x1f\x7f]/', $value) || str_contains($value, '..')) {
                throw new AttachmentException('validation_failed', 'Invalid legacy file alias', 422);
            }
            $field = 'filename';
        }
        $params = [$value];
        $where = "$field=\$1";
        if (!$this->readAll || $ownOnly) {
            $where .= ' AND uploaded_by=$2';
            $params[] = $this->owner;
        }
        $rows = $this->query("SELECT * FROM pool_files WHERE $where" . ($lock ? ' FOR UPDATE' : ''), $params);
        if ($rows === []) {
            throw new AttachmentException('validation_failed', 'Attachment not found', 404);
        }
        return $rows[0];
    }

    /** @return array{items:list<array>, limit:int, offset:int, total:int} */
    public function listing(int $limit, int $offset, ?string $status, bool $mine): array
    {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new AttachmentException('validation_failed', 'limit must be 1..100 and offset must be nonnegative', 422);
        }
        $where = ["status <> 'staged'"];
        $params = [];
        if (!$this->readAll || $mine) {
            $params[] = $this->owner;
            $where[] = 'uploaded_by=$' . count($params);
        }
        if ($status !== null) {
            if (!in_array($status, ['ubehandlet', 'attached', 'deleted'], true)) {
                throw new AttachmentException('validation_failed', 'status must be ubehandlet, attached or deleted', 422);
            }
            $params[] = $status;
            $where[] = 'status=$' . count($params);
        }
        $where = implode(' AND ', $where);
        $this->query($this->mysql ? 'START TRANSACTION WITH CONSISTENT SNAPSHOT' : 'BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        try {
            $count = $this->query("SELECT count(*) AS total FROM pool_files WHERE $where", $params);
            $rows = $this->query("SELECT pool_files.*, EXISTS (SELECT 1 FROM documents d
                JOIN kassekladde k ON k.id=d.source_id JOIN kladdeliste j ON j.id=k.kladde_id
                WHERE d.pool_file_id=pool_files.id AND d.source='kassekladde' AND j.bogfort='V') AS mobile_journal_posted
                FROM pool_files WHERE $where ORDER BY uploaded_at DESC, id DESC LIMIT $limit OFFSET $offset", $params);
            $this->query('COMMIT');
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
        return ['items' => array_map(fn($row) => $this->toArray($row), $rows), 'limit' => $limit,
            'offset' => $offset, 'total' => (int)$count[0]['total']];
    }

    /** Create safe directories within this tenant's document store, rejecting symlink escapes. */
    private function directory(string $relative): string
    {
        if (!is_dir($this->root) && !mkdir($this->root, 0750, true) && !is_dir($this->root)) {
            throw new RuntimeException('Unable to create tenant document store');
        }
        $store = realpath(dirname($this->root));
        $root = realpath($this->root);
        if ($root === false || $store === false || dirname($root) !== $store || is_link($this->root)) {
            throw new RuntimeException('Invalid tenant document store');
        }
        $current = $root;
        foreach (explode('/', $relative) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new RuntimeException('Invalid internal directory');
            }
            $current .= '/' . $part;
            if (is_link($current)) {
                throw new RuntimeException('Symlink in upload directory');
            }
            if (!is_dir($current) && !mkdir($current, 0750) && !is_dir($current)) {
                throw new RuntimeException('Unable to create upload directory');
            }
            if (realpath($current) !== $current) {
                throw new RuntimeException('Upload directory escaped document store');
            }
        }
        return $current;
    }

    /**
     * Resolve only a DB path inside the current tenant, including moved attached documents.
     * Historical rows use their DB filename in pulje; staged/deleted identities cannot download.
     * @return string Existing contained absolute file path.
     */
    public function path(array $row): string
    {
        clearstatcache(true);
        if ($row['status'] === 'deleted') {
            throw new AttachmentException('gone', 'Attachment was deleted', 410);
        }
        if ($row['status'] === 'staged') {
            throw new AttachmentException('validation_failed', 'Upload not completed; retry POST', 404);
        }
        $relative = ifset($row, 'storage_path') ?: 'pulje/' . $row['filename'];
        if (!is_string($relative) || str_contains($relative, "\0") || str_contains($relative, '\\')
            || str_starts_with($relative, '/') || in_array('..', explode('/', $relative), true)) {
            throw new AttachmentException('validation_failed', 'Attachment file not found', 404);
        }
        $root = realpath($this->root);
        $path = realpath($this->root . '/' . $relative);
        if ($root === false || is_link($this->root) || $path === false || !str_starts_with($path, $root . '/') || !is_file($path)) {
            throw new AttachmentException('validation_failed', 'Attachment file not found', 404);
        }
        return $path;
    }

    /** Internal replay identity keeps statement issuance and attachment publication independent. */
    private function publicationClientId(string $id): string
    {
        $hex = substr(hash('sha256','saldi:attachment.publish:'.$id),0,32);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);
    }

    /** @param array{content:string,sha256:string,size:int,client_id:string,extracted_data:array,accountnr:string,subject:?string} $upload
     * @param callable($connection,array $row):void|null $onPublish Runs before publication COMMIT and validates published retries.
     * @return array{row:array, created:bool}
     */
    public function upload(array $upload, ?int $tenantId = null, ?callable $onPublish = null): array
    {
        $reminderId = ifset($upload['extracted_data'], 'reminder_id');
        if (array_key_exists('reminder_id', $upload['extracted_data'])
            && (!is_int($reminderId) || $reminderId < 1 || $reminderId > 2147483647)) {
            throw new AttachmentException('validation_failed', 'reminder_id must be a positive JSON integer', 422);
        }
        if ($reminderId !== null && ($tenantId === null || $tenantId < 1)) {
            throw new RuntimeException('Reminder upload requires a server-selected tenant');
        }
        $id = $upload['client_id'];
        $filename = $id . '.pdf';
        $metadata = $upload['extracted_data'];
        $fields = [
            'subject' => (string)ifset($metadata, 'vendor', $upload['subject'] ?: $id),
            'account' => (string)ifset($metadata, 'suggested_kontonr', $upload['accountnr']),
            'amount' => (string)ifset($metadata, 'total_amount', ''),
            'file_date' => (string)ifset($metadata, 'invoice_date', ''),
            'invoice_number' => (string)ifset($metadata, 'invoice_number', ''),
            'description' => (string)ifset($metadata, 'invoice_description', ''),
            'currency' => normalizePoolCurrency(ifset($metadata, 'currency')) ?: '',
        ];
        foreach (['account' => 50, 'amount' => 50, 'file_date' => 50, 'invoice_number' => 100] as $field => $length) {
            if (mb_strlen($fields[$field]) > $length) {
                throw new AttachmentException('validation_failed', 'Metadata field too long: ' . $field, 422);
            }
        }
        $columns = ['client_id', 'uploaded_by', 'sha256', 'filename', 'status', 'storage_path'];
        $params = [$id, $this->owner, $upload['sha256'], $filename, 'staged', 'pulje/.staging/' . $filename];
        foreach ($fields as $key => $value) {
            $columns[] = $key;
            $params[] = $value;
        }
        if (in_array('norm_amount', $this->columns, true)) {
            $columns[] = 'norm_amount';
            $params[] = normalizePoolAmount($fields['amount']);
        }
        $placeholders = implode(',', array_map(fn($i) => '$' . $i, range(1, count($params))));
        $this->query('BEGIN');
        try {
            if ($reminderId !== null) {
                mobileValidateUploadReminder($this->connection, $tenantId, $this->owner, $reminderId);
            }
            $sql = 'INSERT INTO pool_files (' . implode(',', $columns) . ") VALUES ($placeholders)";
            $sql .= $this->mysql ? ' ON DUPLICATE KEY UPDATE client_id=client_id' : ' ON CONFLICT DO NOTHING RETURNING id';
            $inserted = $this->query($sql, $params);
            $created = $this->mysql ? mysqli_affected_rows($this->connection) === 1 : $inserted !== [];
            // Validate the locked reservation before a rejected retry can leave durable publication intent.
            $row = $this->find(['client_id' => $id], true, true);
            if ($row['status'] === 'deleted') {
                throw new AttachmentException('gone', 'Attachment was deleted', 410);
            }
            if (!hash_equals($row['sha256'], $upload['sha256'])) {
                throw new AttachmentException('conflict', 'client_id already has different content', 409);
            }
            if ($onPublish !== null && $row['status'] !== 'staged'
                && $this->query('SELECT id FROM mobile_expense_claims WHERE pool_file_id=$1', [(int)$row['id']]) === []) {
                throw new AttachmentException('conflict', 'Published attachment has no matching claim', 409);
            }
            if ($onPublish !== null) {
                // Use the existing replay store as a durable publication-intent marker, never a claim row.
                // The marker also prevents a staged retry without its hook from publishing a bare bilag.
                $intent = ['client_id'=>$this->publicationClientId($id),'sha256'=>$upload['sha256'],'metadata'=>$metadata,
                    'claim'=>ifset($upload,'publication_claim')];
                $replay = new Idempotency($this->connection);
                if ($replay->replay($this->owner,'attachment.publish',$id,$intent) === null) {
                    $replay->store($this->owner,'attachment.publish',$id,$intent,200,
                        ['success'=>true,'message'=>'','data'=>['publication'=>'required']], 'publication_hook');
                }
            }
            // Every reservation survives a crash after the final rename, including reminder uploads.
            $this->query('COMMIT');
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
        $this->query('BEGIN');
        try {
            if ($reminderId !== null) {
                mobileValidateUploadReminder($this->connection, $tenantId, $this->owner, $reminderId);
            }
            $row = $this->find(['client_id' => $id], true, true);
            if ($row['status'] === 'deleted') {
                throw new AttachmentException('gone', 'Attachment was deleted', 410);
            }
            if (!hash_equals($row['sha256'], $upload['sha256'])) {
                throw new AttachmentException('conflict', 'client_id already has different content', 409);
            }
            if ($row['status'] === 'staged' && $onPublish === null && $this->query(
                "SELECT id FROM mobile_idempotency WHERE subject=$1 AND client_id=$2 AND resource_type='publication_hook'",
                [$this->owner,$this->publicationClientId($id)]) !== []) {
                throw new AttachmentException('conflict','This upload requires its publication claim',409);
            }
            if ($row['status'] !== 'staged') {
                if ($onPublish !== null) {
                    if ($this->query('SELECT id FROM mobile_expense_claims WHERE pool_file_id=$1',[(int)$row['id']]) === []) {
                        throw new AttachmentException('conflict','Published attachment has no matching claim',409);
                    }
                    $onPublish($this->connection,$row);
                }
                if ($reminderId !== null) {
                    $row = mobileResolveReminder($this->connection, $tenantId, $this->owner, $reminderId, (int)$row['id'])['pool_file'];
                }
                $this->query('COMMIT');
                return ['row' => $row, 'created' => $created];
            }
            $pdf = AttachmentUpload::pdf($upload['content']);
            $staging = $this->directory('pulje/.staging') . '/' . $filename;
            $destination = $this->directory('pulje') . '/' . $filename;
            if (is_link($staging) || is_link($destination)) {
                throw new RuntimeException('Symlink in upload file path');
            }
            if (file_put_contents($staging, $pdf) !== strlen($pdf)) {
                throw new RuntimeException('Unable to stage PDF');
            }
            clearstatcache(true, $staging);
            $storedHash = hash('sha256', $pdf);
            if (filesize($staging) !== strlen($pdf) || !hash_equals($storedHash, hash_file('sha256', $staging))) {
                throw new RuntimeException('Staged PDF failed verification');
            }
            if (!rename($staging, $destination)) {
                throw new RuntimeException('Unable to finalize PDF');
            }
            $relative = 'pulje/' . $filename;
            $this->query("UPDATE pool_files SET status='ubehandlet',storage_path=$1,uploaded_at=CURRENT_TIMESTAMP,updated=CURRENT_TIMESTAMP WHERE id=$2",
                [$relative, (int)$row['id']]);
            if (in_array('content_sha256', $this->columns, true)) {
                $this->query('UPDATE pool_files SET content_sha256=$1 WHERE id=$2', [$storedHash, (int)$row['id']]);
            }
            $identity = [];
            foreach (['vendor_vat_number' => 'cvr', 'vendor_iban' => 'iban', 'vendor_bank_reg' => 'bank_reg',
                'vendor_bank_account' => 'bank_konto', 'customer_vat_number' => 'customerCvr'] as $field => $key) {
                $identity[$key] = ifset($metadata, $field);
            }
            if (in_array('vendor_score', $this->columns, true) && (ifset($metadata, 'vendor') || array_filter($identity))) {
                $match = (new AttachmentModel())->matchVendor($row['subject'], $identity);
                $values = poolVendorColumnValues($match);
                $assignments = [];
                foreach ($values as $column => $literal) {
                    $assignments[] = "$column=$literal";
                }
                $this->query('UPDATE pool_files SET ' . implode(',', $assignments) . ' WHERE id=' . (int)$row['id']);
            }
            $row = $this->find(['client_id' => $id], true, true);
            if ($reminderId !== null) {
                $row = mobileResolveReminder($this->connection, $tenantId, $this->owner, $reminderId, (int)$row['id'])['pool_file'];
            }
            if ($onPublish !== null) {
                $onPublish($this->connection,$row);
            }
            $this->query('COMMIT');
            return ['row' => $row, 'created' => $created];
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /** @return array<string,mixed> Own unprocessed row, or the existing tombstone on repeated DELETE. */
    public function delete(array $selector): array
    {
        if (!array_key_exists('id', $selector)) {
            throw new AttachmentException('validation_failed', 'DELETE requires id', 422);
        }
        $this->query('BEGIN');
        try {
            $row = $this->find($selector, true, true);
            if ($row['status'] === 'deleted') {
                $this->query('COMMIT');
                return $row;
            }
            if ($row['status'] !== 'ubehandlet') {
                throw new AttachmentException('conflict', 'Only unprocessed attachments can be deleted', 409);
            }
            $name = (int)$row['id'] . (self::mimeType($row) === 'application/xml' ? '.xml' : '.pdf');
            $relative = 'pulje/.deleted/' . $name;
            $deleted = $this->directory('pulje/.deleted') . '/' . $name;
            if (is_link($deleted)) {
                throw new RuntimeException('Symlink in deleted path');
            }
            try {
                $source = $this->path($row);
            } catch (AttachmentException $e) {
                if ($e->getStatusCode() !== 404 || !is_file($deleted)) {
                    throw $e;
                }
                $source = null;
            }
            if ($source !== null && !rename($source, $deleted)) {
                throw new RuntimeException('Unable to move deleted PDF');
            }
            $this->query("UPDATE pool_files SET filename=CONCAT('.history-',id,'-',LEFT(filename,220)),status='deleted',storage_path=$1,deleted_at=CURRENT_TIMESTAMP,updated=CURRENT_TIMESTAMP WHERE id=$2", [$relative, (int)$row['id']]);
            mobileReleaseBankLine($this->connection, $row);
            poolMetadataWithdrawStatement($this->connection, $row);
            $row = $this->find($selector, true, true);
            $this->query('COMMIT');
            return $row;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Nightly maintenance expires abandoned reservations, preserving tombstones and any surviving PDF.
     * SKIP LOCKED avoids interrupting an active uploader; retry and expiry serialize on the same row lock.
     * @return int Number of staged rows expired. Default grace is seven days; unprocessed/attached rows are untouched.
     */
    public function expireStaged(int $days = 7, int $limit = 100): int
    {
        if ($days < 1 || $days > 365 || $limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Invalid staged expiration bounds');
        }
        $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-' . $days . ' days')->format('Y-m-d H:i:sP');
        $params = [$cutoff];
        $owner = '';
        if (!$this->readAll) {
            $owner = ' AND uploaded_by=$2';
            $params[] = $this->owner;
        }
        $this->query('BEGIN');
        try {
            $rows = $this->query("SELECT * FROM pool_files WHERE status='staged' AND uploaded_at < \$1$owner ORDER BY id LIMIT $limit FOR UPDATE SKIP LOCKED", $params);
            foreach ($rows as $row) {
                $filename = self::uuid($row['client_id']) . '.pdf';
                $destination = $this->directory('pulje/.deleted') . '/' . (int)$row['id'] . '.pdf';
                if (is_link($destination)) {
                    throw new RuntimeException('Symlink in expired destination');
                }
                foreach (['pulje/' . $filename, 'pulje/.staging/' . $filename] as $relative) {
                    $candidate = array_replace($row, ['status' => 'ubehandlet', 'storage_path' => $relative]);
                    try {
                        $source = $this->path($candidate);
                    } catch (AttachmentException $e) {
                        if ($e->getStatusCode() !== 404) {
                            throw $e;
                        }
                        continue;
                    }
                    if (!is_file($destination)) {
                        if (!rename($source, $destination)) {
                            throw new RuntimeException('Unable to preserve expired PDF');
                        }
                    } elseif (!unlink($source)) {
                        throw new RuntimeException('Unable to remove expired partial PDF');
                    }
                }
                $this->query("UPDATE pool_files SET filename=CONCAT('.history-',id,'-',LEFT(filename,220)),status='deleted',storage_path=$1,deleted_at=CURRENT_TIMESTAMP,updated=CURRENT_TIMESTAMP WHERE id=$2",
                    ['pulje/.deleted/' . (int)$row['id'] . '.pdf', (int)$row['id']]);
            }
            $this->query('COMMIT');
            return count($rows);
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /** @return string MIME of the stored PDF or legacy UBL XML, independent of historical aliases. */
    public static function mimeType(array $row): string
    {
        $name = $row['storage_path'] ?? $row['filename'];
        return strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'xml' ? 'application/xml' : 'application/pdf';
    }

    /**
     * Filesystem paths and owner subjects stay private. Metadata comes from the pool row.
     * @return array{
     *   id:int, client_id:?string, status:string, uploaded_at:?string, sha256:?string,
     *   filename:string, size:?int, mimeType:string, uploadDate:?string,
     *   extracted_data:array, metadata:array, attached_at:?string, bilag_nr:?int, deleted_at:?string,
     *   review_state:string, display_status:string,
     *   rejection:array{code:?string,message:?string,at:?string,by_name:string}|null,
     *   vat_code:?string, bank_line_id:?int, etag:string
     * }
     */
    public function toArray(array $row): array
    {
        require_once __DIR__ . '/../../../includes/docsIncludes/poolReviewVersion.php';
        require_once __DIR__ . '/../../core/MobileDisplayName.php';
        $size = null;
        if (in_array($row['status'], ['ubehandlet', 'attached'], true)) {
            try {
                $size = filesize($this->path($row));
            } catch (AttachmentException $e) {
                if ($e->getStatusCode() !== 404) {
                    throw $e;
                }
            }
        }
        $extracted = ['total_amount' => $row['amount'], 'invoice_date' => $row['file_date'], 'invoice_number' => $row['invoice_number'],
            'invoice_description' => $row['description'], 'currency' => $row['currency'], 'vendor' => $row['subject'], 'suggested_kontonr' => $row['account']];
        $metadata = ['date' => $row['file_date'], 'amount' => $row['amount'], 'accountnr' => $row['account'],
            'invoiceNumber' => $row['invoice_number'], 'invoiceDescription' => $row['description'], 'currency' => $row['currency'], 'subject' => $row['subject']];
        $timestamp = fn($value) => $value === null ? null : (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
        $review = (string)ifset($row, 'review_state', 'pending');
        $posted = false;
        if ($row['status'] === 'attached') {
            if (!array_key_exists('mobile_journal_posted', $row)) {
                $row['mobile_journal_posted'] = $this->query("SELECT EXISTS (SELECT 1 FROM documents d
                    JOIN kassekladde k ON k.id=d.source_id JOIN kladdeliste j ON j.id=k.kladde_id
                    WHERE d.pool_file_id=$1 AND d.source='kassekladde' AND j.bogfort='V') AS posted", [(int)$row['id']])[0]['posted'];
            }
            $posted = in_array($row['mobile_journal_posted'], ['t', true, 1, '1'], true);
        }
        $display = $row['status'] === 'deleted' ? 'slettet'
            : ($posted ? 'bogfoert'
            : ($row['status'] === 'attached' ? ($review === 'approved' ? 'godkendt' : 'vedhaeftet')
            : ($review === 'rejected' ? 'afvist' : 'sendt')));
        $rejection = null;
        if ($review === 'rejected') {
            $actor = (string)ifset($row, 'reviewed_by', '');
            $name = str_starts_with($actor, 'web:') ? substr($actor, 4) : (new MobileDisplayName($this->connection))->sender($actor);
            if ($name === '' || $name === $actor) {
                $name = 'Saldi';
            }
            $reviewedAt = ifset($row, 'reviewed_at');
            $rejection = ['code' => ifset($row, 'rejection_code'), 'message' => ifset($row, 'rejection_note'),
                'at' => $reviewedAt === null ? null : (new DateTimeImmutable($reviewedAt))
                    ->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM), 'by_name' => $name];
        }
        return ['id' => (int)$row['id'], 'client_id' => $row['client_id'], 'status' => $row['status'],
            'review_state' => $review, 'display_status' => $display, 'rejection' => $rejection,
            'vat_code' => ifset($row, 'vat_code'),
            'bank_line_id' => ifset($row, 'bank_line_id') === null ? null : (int)$row['bank_line_id'],
            'etag' => '"' . pool_review_version($row) . '"',
            'uploaded_at' => $timestamp($row['uploaded_at']), 'sha256' => $row['sha256'], 'filename' => $row['filename'],
            'size' => $size, 'mimeType' => self::mimeType($row), 'uploadDate' => $timestamp($row['uploaded_at']),
            'extracted_data' => $extracted, 'metadata' => $metadata, 'attached_at' => $timestamp($row['attached_at']),
            'bilag_nr' => $row['bilag_nr'] === null ? null : (int)$row['bilag_nr'], 'deleted_at' => $timestamp($row['deleted_at'])];
    }
}
