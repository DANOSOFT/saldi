<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolAudit.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Bootstrap registry identity before resolving ordinary web audit actors.

require_once __DIR__ . '/../std_func.php';
require_once __DIR__ . '/../../restapi/core/MobileSchema.php';
require_once __DIR__ . '/../../restapi/core/Subject.php';

/** @return string Web actor from online.php; revisors have no tenant user id. */
function pool_audit_web_actor(?int $userId = null): string
{
    global $sqhost, $squser, $sqpass, $sqdb, $db_type, $db_encode;
    $tenantConnection = $GLOBALS['connection'];
    try {
        $revisor = in_array(ifset($GLOBALS, 'revisor'), [true, 1, '1', 't', 'on'], true);
        $id = $userId ?? (int)ifset($GLOBALS, 'bruger_id', 0);
        if ($revisor || $id < 1) {
            return 'web:' . ((string)ifset($GLOBALS, 'brugernavn', '') ?: 'Saldi');
        }
        require_once __DIR__ . '/../db_query.php';
        require_once __DIR__ . '/../connect.php';
        require_once __DIR__ . '/../mobileRegistryUpdates.php';
        $registry = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . ' line ' . __LINE__);
        mobileRegistryUpdates($registry);
        return Subject::build('account', $id);
    } finally {
        // Registry bootstrap and Subject can select another link; retain the tenant.
        $GLOBALS['connection'] = $tenantConnection;
    }
}

/**
 * Attribute trigger writes, including autocommit web paths, restoring nested scopes.
 * Caller-owned failed transactions restore settings when the caller rolls back.
 * @return mixed Callback result.
 */
function pool_audit_actor_scope(string $actor, ?string $requestId, callable $fn)
{
    $connection = $GLOBALS['connection'];
    if ($connection instanceof mysqli) { return $fn(); }
    $store = new MobileSchema($connection);
    $before = $store->rows("SELECT current_setting('saldi.actor',true) AS actor, current_setting('saldi.request_id',true) AS request_id")[0];
    $store->execute('SELECT set_config(\'saldi.actor\',' . $store->literal($actor) . ',false),set_config(\'saldi.request_id\',' . $store->literal($requestId ?? '') . ',false)');
    try {
        return $fn();
    } finally {
        if (pg_connection_status($connection) === PGSQL_CONNECTION_OK
            && pg_transaction_status($connection) !== PGSQL_TRANSACTION_INERROR) {
            $store->execute('SELECT set_config(\'saldi.actor\',' . $store->literal($before['actor'] ?? '') . ',false),set_config(\'saldi.request_id\',' . $store->literal($before['request_id'] ?? '') . ',false)');
        }
    }
}

/** @return void Append a decision; PostgreSQL supplies the canonical hash and serializes the chain. */
function pool_audit_append(int $poolFileId, string $event, string $actor, array $changes = [], ?string $reason = null, ?string $note = null): void
{
    $store = new MobileSchema($GLOBALS['connection']);
    $values = array_map(static fn($value) => $value === null ? 'NULL' : $store->literal($value), [$event, $actor, json_encode((object)$changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $reason, $note]);
    $request = $GLOBALS['connection'] instanceof mysqli ? 'NULL' : "NULLIF(current_setting('saldi.request_id',true),'')";
    $store->execute("INSERT INTO pool_file_audit(pool_file_id,event,actor,changes,reason_code,note,request_id)
        VALUES ($poolFileId,$values[0],$values[1],$values[2],$values[3],$values[4],$request)");
}

/** @return array{items:list<array<string,mixed>>,total:int,limit:int,offset:int,chain_ok:bool} Raw internal audit rows; endpoint projects identities safely. */
function pool_audit_read(int $poolFileId, int $limit = 50, int $offset = 0): array
{
    if ($poolFileId < 1 || $limit < 1 || $limit > 100 || $offset < 0) {
        throw new InvalidArgumentException('Invalid audit page');
    }
    $store = new MobileSchema($GLOBALS['connection']);
    $items = $store->rows("SELECT * FROM pool_file_audit WHERE pool_file_id=$poolFileId ORDER BY id LIMIT $limit OFFSET $offset");
    $total = (int)$store->rows("SELECT count(*) AS total FROM pool_file_audit WHERE pool_file_id=$poolFileId")[0]['total'];
    return ['items'=>$items,'total'=>$total,'limit'=>$limit,'offset'=>$offset,'chain_ok'=>pool_audit_verify($poolFileId)];
}

/** @return bool Verify the entire chain with the exact PostgreSQL canonical representation used by m3_02. */
function pool_audit_verify(int $poolFileId): bool
{
    $store = new MobileSchema($GLOBALS['connection']);
    if ($GLOBALS['connection'] instanceof mysqli) { return false; }
    $rows = $store->rows("SELECT prev_sha256,row_sha256,
        lag(row_sha256) OVER (ORDER BY id) AS expected_prev,
        encode(sha256(convert_to(COALESCE(prev_sha256,'') ||
            jsonb_build_array(id,pool_file_id,event,actor,changes::jsonb,reason_code,note,request_id,
                to_char(created_at AT TIME ZONE 'UTC','YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"'))::text,'UTF8')),'hex') AS expected_hash
        FROM pool_file_audit WHERE pool_file_id=$poolFileId ORDER BY id");
    foreach ($rows as $row) {
        if ($row['prev_sha256'] !== $row['expected_prev'] || !hash_equals($row['row_sha256'], $row['expected_hash'])) {
            return false;
        }
    }
    return true;
}
