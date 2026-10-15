<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/ClassificationService.php --- ver 5.0.0 --- 2026-10-07 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Stored suggestions, owner feedback and measured reviewer outcomes.
// 20261001 CDX/LH Resolve feedback from served evidence without creating an unseen proposal.
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.

require_once __DIR__ . '/legacy/LegacyEngine.php';
require_once __DIR__ . '/../core/Rights.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolClassification.php';

final class ClassificationService
{
    private MobileSchema $store;
    private $connection;
    private string $db;
    private int $tenantId;
    private string $username;
    private int $userId;
    private string $subject;
    private bool $canRead;

    public function __construct($connection, string $db, int $tenantId, string $username, int $userId, string $subject, bool $registry = false)
    {
        $this->connection = $connection;
        $this->store = new MobileSchema($connection);
        $this->db = $db;
        $this->tenantId = $tenantId;
        $this->username = $username;
        $this->userId = $userId;
        $this->subject = $subject;
        $users = $registry ? [] : $this->store->rows('SELECT rettigheder FROM brugere WHERE id=' . (int)$userId);
        $this->canRead = $registry || ($users && Rights::allows($users[0]['rettigheder'], 'bilag', 'read'));
    }

    /** @return int Canonical positive id, without coercing arrays, booleans or overflowing integers. */
    public static function id($value): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string)$value)
            || (int)$value > 2147483647) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'metadata_invalid', 'field' => 'attachment_id']);
        }
        return (int)$value;
    }

    /** @return array Authorized pool identity, with a lock inside mutations. */
    private function attachment(int $id, bool $ownerOnly = false, bool $lock = false): array
    {
        $rows = $this->store->rows('SELECT * FROM pool_files WHERE id=' . (int)$id . ($lock ? ' FOR UPDATE' : ''));
        if (!$rows || (($ownerOnly || !$this->canRead) && $rows[0]['uploaded_by'] !== $this->subject)
            || $rows[0]['status'] === 'staged') {
            throw new MobileT2Error('not_found', 404);
        }
        if ($rows[0]['status'] === 'deleted') {
            throw new MobileT2Error('gone', 410);
        }
        return $rows[0];
    }

    /** @return EngineContext Document fiscal year, or configuration context for an undated document. */
    private function context(array $row): EngineContext
    {
        $date = trim((string)ifset($row, 'file_date', ''));
        if ($date === '') {
            return EngineContext::forConfig($this->connection, $this->db, $this->tenantId, $this->username, $this->userId);
        }
        $date = normalizeDateFormat($date);
        return new EngineContext($this->connection, $this->db, $this->tenantId, $this->username, $this->userId, $date);
    }

    /** @return array Vendor form represented as a pool row; no match is written to an attachment. */
    private function vendor(array $query): array
    {
        $row = [];
        foreach (['vendor_name', 'vendor_cvr', 'vendor_iban', 'date'] as $field) {
            $value = ifset($query, $field, '');
            if (!is_string($value) || mb_strlen($value) > ($field === 'vendor_name' ? 200 : 80) || str_contains($value, "\0")) {
                throw new MobileT2Error('validation_failed', 422, ['reason' => 'metadata_invalid', 'field' => $field]);
            }
            $row[$field === 'date' ? 'file_date' : $field] = trim($value);
        }
        if ($row['file_date'] !== '' && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $row['file_date'], $parts)
            || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]))) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_date']);
        }
        if ($row['vendor_name'] === '' && $row['vendor_cvr'] === '' && $row['vendor_iban'] === '') {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'metadata_invalid', 'field' => 'vendor_name']);
        }
        return $row;
    }

    /**
     * Persist once per complete evidence hash. The caller holds the attachment lock.
     * @return array Suggestion shape at classification_decide(), with its stored integer id.
     */
    private function storedSuggestion(array $row, int $regnaar): array
    {
        $loaded = classification_inputs($row, $regnaar);
        $result = classification_decide($loaded['inputs']);
        $nullable = fn($value): string => $value === null ? 'NULL' : $this->store->literal((string)$value);
        $hash = $this->store->literal($loaded['inputs_sha256']);
        $id = (int)$row['id'];
        $this->store->execute('INSERT INTO classification_suggestions (pool_file_id,vendor_key,inputs_sha256,kontonr,vat_code,kreditor_kontonr,basis,evidence_count,evidence_total,engine_version,requested_by) VALUES ('
            . $id . ',' . $nullable($loaded['vendor_key']) . ',' . $hash . ',' . $nullable($result['kontonr']) . ','
            . $nullable($result['vat_code']) . ',' . $nullable(ifset($result, ['kreditor', 'kontonr'])) . ','
            . $this->store->literal($result['basis']) . ',' . (int)$result['basis_detail']['count'] . ',' . (int)$result['basis_detail']['total']
            . ",'det-1'," . $this->store->literal($this->subject) . ') ON CONFLICT (pool_file_id,engine_version,inputs_sha256) DO NOTHING');
        $stored = $this->store->rows("SELECT * FROM classification_suggestions WHERE pool_file_id=$id AND engine_version='det-1' AND inputs_sha256=$hash")[0];
        $result['suggestion_id'] = (int)$stored['id'];
        return $result;
    }

    /** @return array Suggestion shape at classification_decide(); vendor-only requests have a null id. */
    public function suggest(array $query): array
    {
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Classification service requires an idle connection');
        }
        if (array_key_exists('attachment_id', $query)) {
            $id = self::id($query['attachment_id']);
            $this->store->execute('BEGIN');
            try {
                $row = $this->attachment($id, false, true);
                $context = $this->context($row);
                $result = LegacyEngine::run($context, fn(): array => $this->storedSuggestion($row, $context->regnaar));
                $this->store->execute('COMMIT');
                return $result;
            } catch (Throwable $error) {
                $this->store->execute('ROLLBACK');
                throw $error;
            }
        }
        $row = $this->vendor($query);
        $context = $this->context($row);
        return LegacyEngine::run($context, function () use ($row, $context): array {
            $index = poolVendorLoadIndex();
            $match = poolVendorMatch(['name' => $row['vendor_name'], 'cvr' => $row['vendor_cvr'], 'iban' => $row['vendor_iban']],
                $index, ['ownCvr' => poolVendorLoadOwnCvr()]);
            $matchedRow = $row + ['vendor_match' => $match['match'], 'vendor_konto_id' => $match['kontoId'], 'vendor_score' => $match['score']];
            return classification_suggest($matchedRow, $context->regnaar);
        });
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Idempotent uploader-only confirmation. */
    public function feedback(array $body): array
    {
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Classification service requires an idle connection');
        }
        Idempotency::clientId($body);
        $id = self::id(ifset($body, 'attachment_id'));
        if (!is_int(ifset($body, 'attachment_id'))) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'metadata_invalid', 'field' => 'attachment_id']);
        }
        foreach (array_keys($body) as $field) {
            if (!in_array($field, ['client_id', 'attachment_id', 'suggestion_id', 'kontonr', 'vat_code', 'kreditor_kontonr'], true)) {
                throw new MobileT2Error('validation_failed', 422, ['reason' => 'metadata_invalid', 'field' => $field]);
            }
        }
        $this->store->execute('BEGIN');
        try {
            $result = (new Idempotency($this->connection))->execute($this->subject, 'classification.feedback', $id, $body, function () use ($id, $body): array {
                $row = $this->attachment($id, true, true);
                if ($row['status'] !== 'ubehandlet' || $row['review_state'] === 'approved') {
                    throw new MobileT2Error('conflict', 409, ['reason' => 'attachment_processed']);
                }
                $context = $this->context($row);
                $saved = LegacyEngine::run($context, function () use ($row, $context, $id, $body): array {
                    $decision = $body;
                    if (!array_key_exists('suggestion_id', $decision)) {
                        $decision['suggestion_id'] = classification_applicable_suggestion_id($id, classification_inputs($row, $context->regnaar));
                    }
                    return classification_record_feedback($id, $decision, $context->regnaar, $this->subject, 'uploader');
                });
                return ['status' => 201, 'body' => ['success' => true, 'message' => '',
                    'data' => ['feedback_id' => $saved['feedback_id'], 'outcome' => $saved['outcome']]],
                    'resource_type' => 'classification_feedback', 'resource_id' => $saved['feedback_id']];
            });
            $this->store->execute('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }

    /** @return array{window_days:int,decisions:int,accepted:int,corrected:int,vendors_learned:int} Final reviewer decisions only. */
    public function stats(): array
    {
        if (!$this->canRead) {
            throw new MobileT2Error('permission_denied', 403);
        }
        $row = $this->store->rows("SELECT count(*) AS decisions,
            count(*) FILTER (WHERE outcome='accepted') AS accepted,
            count(*) FILTER (WHERE outcome='corrected') AS corrected,
            count(DISTINCT vendor_key) FILTER (WHERE outcome='corrected') AS vendors_learned
            FROM classification_feedback WHERE role='reviewer' AND outcome IN ('accepted','corrected')
            AND created_at >= now() - interval '90 days'")[0];
        return ['window_days' => 90, 'decisions' => (int)$row['decisions'], 'accepted' => (int)$row['accepted'],
            'corrected' => (int)$row['corrected'], 'vendors_learned' => (int)$row['vendors_learned']];
    }
}
