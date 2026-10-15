<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/Idempotency.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Document delivery completion lock order before the delivery row update.

require_once __DIR__ . '/MobileSchema.php';

/** A structured failure for T2 helpers; map through BaseEndpoint::sendError(). */
final class MobileT2Error extends RuntimeException
{
    public string $error;
    public int $status;
    public array $details;

    public function __construct(string $error, int $status, array $details = [])
    {
        parent::__construct($error);
        $this->error = $error;
        $this->status = $status;
        $this->details = $details;
    }
}

/** Tenant-local replay records. All operations require the caller's business transaction. */
final class Idempotency
{
    private $connection;
    private MobileSchema $store;

    public function __construct($connection)
    {
        $this->connection = $connection;
        $this->store = new MobileSchema($connection);
    }

    /** Sort object keys recursively; array order and JSON scalar types remain significant. */
    public static function canonicalJson($value): string
    {
        $normalize = function ($value) use (&$normalize) {
            if (is_object($value)) {
                $properties = get_object_vars($value);
                ksort($properties, SORT_STRING);
                return (object)array_map($normalize, $properties);
            }
            if (is_array($value)) {
                if (!array_is_list($value)) {
                    ksort($value, SORT_STRING);
                }
                return array_map($normalize, $value);
            }
            return $value;
        };
        return json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /** Hash operation, target and the body excluding only its top-level client_id. */
    public static function requestHash(string $operation, $targetId, array $body): string
    {
        unset($body['client_id']);
        return hash('sha256', self::canonicalJson([$operation, $targetId, (object)$body]));
    }

    /** Validate app UUIDs, preserving equivalent case as one database identity. */
    public static function clientId(array $body): string
    {
        $id = ifset($body, 'client_id');
        if (!is_string($id) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $id)) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'client_id_required']);
        }
        return strtolower($id);
    }

    /** @return array{status:int,body:array,headers:array<string,string>}|null */
    public function replay(string $subject, string $operation, $targetId, array $body): ?array
    {
        $this->requireTransaction();
        if ($subject === '' || strlen($subject) > 255 || $operation === '' || strlen($operation) > 40) {
            throw new InvalidArgumentException('Invalid replay scope');
        }
        $id = self::clientId($body);
        // Acquired even for absent rows, so simultaneous first submissions serialize.
        $lock = $this->store->literal(self::canonicalJson([$subject, $id]));
        $this->store->execute("SELECT pg_advisory_xact_lock(hashtextextended($lock,0))");
        $rows = $this->store->rows('SELECT * FROM mobile_idempotency WHERE subject=' . $this->store->literal($subject)
            . ' AND client_id=' . $this->store->literal($id) . ' FOR UPDATE');
        if (!$rows) {
            return null;
        }
        $row = $rows[0];
        if (!hash_equals($row['request_sha256'], self::requestHash($operation, $targetId, $body))) {
            throw new MobileT2Error('conflict', 409, ['reason' => 'client_id_reused']);
        }
        $response = json_decode($row['response_json'], true, 512, JSON_THROW_ON_ERROR);
        if ($row['resource_type'] === 'document_delivery') {
            $deliveryId = (int)$row['resource_id'];
            $deliveries = $this->store->rows("SELECT state,created_at > now()-interval '10 minutes' AS fresh
                FROM document_deliveries WHERE id=$deliveryId FOR UPDATE");
            if (!$deliveries) {
                throw new RuntimeException('Replay delivery is missing');
            }
            if ($deliveries[0]['state'] === 'sending') {
                if ($deliveries[0]['fresh'] === 't') {
                    throw new MobileT2Error('conflict', 409, ['reason' => 'in_progress']);
                }
                $this->store->execute("UPDATE document_deliveries SET state='unknown',finished_at=now() WHERE id=$deliveryId");
                $response['data']['delivery']['state'] = 'unknown';
                $json = $this->store->literal(json_encode($response, JSON_THROW_ON_ERROR));
                $recordId = (int)$row['id'];
                $this->store->execute("UPDATE mobile_idempotency SET response_json=$json WHERE id=$recordId");
            }
        }
        return ['status' => (int)$row['http_status'], 'body' => $response,
            'headers' => ['Idempotent-Replayed' => 'true']];
    }

    /** Store the complete envelope in the same transaction as its resource. Call replay first. */
    public function store(string $subject, string $operation, $targetId, array $body, int $status, array $response,
        ?string $resourceType = null, ?int $resourceId = null): void
    {
        $this->requireTransaction();
        if ($status < 100 || $status > 599 || ($resourceType !== null && strlen($resourceType) > 30)) {
            throw new InvalidArgumentException('Invalid stored response');
        }
        $values = array_map([$this->store, 'literal'], [$subject, self::clientId($body), $operation,
            self::requestHash($operation, $targetId, $body), json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $type = $resourceType === null ? 'NULL' : $this->store->literal($resourceType);
        $resource = $resourceId === null ? 'NULL' : (string)(int)$resourceId;
        $this->store->execute("INSERT INTO mobile_idempotency(subject,client_id,operation,request_sha256,response_json,http_status,resource_type,resource_id)
            VALUES ($values[0],$values[1],$values[2],$values[3],$values[4],$status,$type,$resource)");
        $this->store->execute("DELETE FROM mobile_idempotency WHERE id IN (
            SELECT id FROM mobile_idempotency WHERE created_at < now() - interval '30 days' ORDER BY created_at,id LIMIT 100 FOR UPDATE SKIP LOCKED)");
    }

    /**
     * Save the state-accurate envelope after an outside effect, in a new caller-owned transaction.
     * The original hash/scope are checked without executing the effect again.
     * Call before locking/updating document_deliveries, then update that row in the same transaction.
     * Both completion and replay must lock advisory scope, idempotency row, then delivery row.
     */
    public function complete(string $subject, string $operation, $targetId, array $body, int $status, array $response): void
    {
        $this->requireTransaction();
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('Invalid stored response');
        }
        $id = self::clientId($body);
        $lock = $this->store->literal(self::canonicalJson([$subject, $id]));
        $this->store->execute("SELECT pg_advisory_xact_lock(hashtextextended($lock,0))");
        $rows = $this->store->rows('SELECT id,request_sha256 FROM mobile_idempotency WHERE subject='
            . $this->store->literal($subject) . ' AND client_id=' . $this->store->literal($id) . ' FOR UPDATE');
        if (!$rows) {
            throw new LogicException('No replay record to complete');
        }
        if (!hash_equals($rows[0]['request_sha256'], self::requestHash($operation, $targetId, $body))) {
            throw new MobileT2Error('conflict', 409, ['reason' => 'client_id_reused']);
        }
        $json = $this->store->literal(json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $recordId = (int)$rows[0]['id'];
        $this->store->execute("UPDATE mobile_idempotency SET http_status=$status,response_json=$json WHERE id=$recordId");
    }

    /**
     * Atomic database-only operation; callback returns the complete HTTP response.
     * @param callable():array{status:int,body:array,resource_type?:string,resource_id?:int} $change
     * @return array{status:int,body:array,headers:array<string,string>}
     */
    public function execute(string $subject, string $operation, $targetId, array $body, callable $change): array
    {
        $replay = $this->replay($subject, $operation, $targetId, $body);
        if ($replay !== null) {
            return $replay;
        }
        $response = $change();
        $this->store($subject, $operation, $targetId, $body, $response['status'], $response['body'],
            ifset($response, 'resource_type'), ifset($response, 'resource_id'));
        return ['status' => $response['status'], 'body' => $response['body'], 'headers' => []];
    }

    /** Ensure this helper never commits or owns business work. PostgreSQL is the production target. */
    private function requireTransaction(): void
    {
        if (!$this->connection instanceof \PgSql\Connection || pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_INTRANS) {
            throw new LogicException('Idempotency requires a caller-owned PostgreSQL transaction');
        }
    }
}
