<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolAttachSource.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Acknowledge only the newest move at the current delivery-note path.

require_once __DIR__ . '/poolAttach.php';

/** @return array{dir:string,path:string,filepath:string} The web's creditor-order delivery-note folder. */
function pool_attach_target_source(string $docRoot, string $source, int $sourceId, string $fileName): array
{
    if ($source !== 'creditorOrder' || $sourceId <= 0 || $fileName === '' || basename($fileName) !== $fileName
        || str_contains($fileName, '\\') || str_contains($fileName, "\0") || in_array($fileName, ['.','..'], true)) {
        throw new InvalidArgumentException('Invalid attachment target');
    }
    $filepath = '/creditor/orders/' . $sourceId;
    $dir = rtrim($docRoot, '/') . $filepath;
    return ['dir'=>$dir, 'path'=>$dir . '/' . $fileName, 'filepath'=>$filepath];
}

/** @return array<string,mixed> An account's own published, undecided bilag, locked by the caller's transaction. */
function pool_attach_source_row(MobileSchema $store, int $id, string $subject): array
{
    $rows = $store->rows('SELECT * FROM pool_files WHERE id=' . $id . ' AND uploaded_by=' . $store->literal($subject) . ' FOR UPDATE');
    if (!$rows || Subject::parse($subject)['principal'] !== 'account') {
        throw new MobileT2Error('not_found', 404);
    }
    if ($rows[0]['status'] !== 'ubehandlet' || $rows[0]['review_state'] !== 'pending') {
        throw new MobileT2Error('conflict', 409, ['reason'=>'attachment_processed']);
    }
    return $rows[0];
}

/** @return void Validate and lock the existing creditor order, including closed orders for attachment retries. */
function pool_attach_source_order(MobileSchema $store, int $id): void
{
    if (!$store->rows("SELECT id FROM ordrer WHERE id=$id AND art='KO' FOR UPDATE")) {
        throw new MobileT2Error('not_found', 404);
    }
}

/** @return array{status:int,body:array,headers:array<string,string>} Delivery-note response in the shared REST envelope. */
function pool_attach_source_response(int $id, string $state, ?string $reason = null): array
{
    return ['status'=>200, 'body'=>['success'=>true, 'message'=>'', 'data'=>[
        'attachment'=>['state'=>$state, 'attachment_id'=>$id, 'reason'=>$reason]]], 'headers'=>[]];
}

/**
 * Attach an own delivery note with T3's reservation, decision and final-rename protocol.
 * Reservation replay records retain the original body even when recovery aborts the first move.
 * The function owns its transactions and restores the caller's selected legacy globals.
 * @param array{connection:mixed,db:string,subject:string,user_id:int,request_id?:?string,hook?:?Closure} $ctx
 * @return array{status:int,body:array,headers:array<string,string>} No accounting or stock mutation.
 */
function pool_attach_to_source(array $ctx, int $poolFileId, string $source, int $sourceId, string $clientId): array
{
    require_once __DIR__ . '/poolApprove.php';
    $connection = $ctx['connection'];
    if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) {
        throw new LogicException('Source attachment requires an idle connection');
    }
    pool_attach_target_source(dirname(poolPuljePath($ctx['db'])), $source, $sourceId, 'validate.pdf');
    $saved = [];
    foreach (['connection','db_type','db','revisor'] as $key) {
        $saved[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
    }
    $identity = Subject::parse($ctx['subject']);
    try {
        $installation = Subject::installation();
    } finally {
        // The first CLI registry bootstrap may replace the selected legacy connection.
        foreach ($saved as $key=>[$exists,$value]) {
            if ($exists) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }
    if ($identity['principal'] !== 'account' || $identity['user_id'] !== $ctx['user_id']
        || $identity['installation'] !== $installation || pg_dbname($connection) !== $ctx['db']) {
        throw new MobileT2Error('not_found', 404);
    }
    $body = ['client_id'=>$clientId, 'order_id'=>$sourceId, 'attachment_id'=>$poolFileId];
    $clientId = Idempotency::clientId($body);
    $body['client_id'] = $clientId;
    $operation = 'goods_receipt.attach';
    $store = new MobileSchema($connection);
    $idem = new Idempotency($connection);
    $GLOBALS['connection'] = $connection;
    $GLOBALS['db_type'] = 'postgresql';
    $GLOBALS['db'] = $ctx['db'];
    $GLOBALS['revisor'] = false;
    $intentId = null;
    $locked = false;
    try {
        $store->execute("SELECT pg_advisory_lock(hashtext('saldi:pool_move'),$poolFileId)");
        $locked = true;
        foreach ($store->rows("SELECT id FROM pool_file_moves WHERE pool_file_id=$poolFileId AND state IN ('reserved','committed')") as $move) {
            pool_move_reconcile((int)$move['id']);
        }
        $store->execute('BEGIN');
        $replay = $idem->replay($ctx['subject'], $operation, $sourceId, $body);
        $existing = $store->rows('SELECT * FROM pool_file_moves WHERE client_id=' . $store->literal($clientId) . ' FOR UPDATE');
        if ($replay !== null && !$existing && $replay['body']['data']['attachment']['state'] !== 'pending') {
            $store->execute('COMMIT');
            return $replay;
        }
        if ($replay !== null && $existing && $existing[0]['state'] !== 'aborted') {
            $state = $existing[0]['state'];
            $response = pool_attach_source_response($poolFileId, $state === 'done' ? 'attached' : ($state === 'lost' ? 'failed' : 'pending'),
                $state === 'done' ? null : ($state === 'lost' ? 'attachment_processed' : 'file_move_pending'));
            $idem->complete($ctx['subject'], $operation, $sourceId, $body, 200, $response['body']);
            $store->execute('COMMIT');
            $response['headers'] = $replay['headers'];
            return $response;
        }
        pool_attach_source_order($store, $sourceId);
        // A web return and reattach can reuse the destination; inspect the newest intent before its state.
        // Other processed rows still fail the ordinary pending-row check below.
        $attached = $store->rows("SELECT p.id,m.state FROM pool_files p JOIN documents d ON d.pool_file_id=p.id
            JOIN pool_file_moves m ON m.pool_file_id=p.id AND m.dest_path=p.storage_path
            WHERE p.id=$poolFileId AND p.status='attached' AND p.uploaded_by=" . $store->literal($ctx['subject'])
            . " AND d.source='creditorOrder' AND d.source_id=$sourceId ORDER BY m.id DESC LIMIT 1");
        if ($attached && in_array($attached[0]['state'], ['done','committed','lost'], true)) {
            $moveState = $attached[0]['state'];
            $state = $moveState === 'done' ? 'attached' : ($moveState === 'lost' ? 'failed' : 'pending');
            $reason = $moveState === 'done' ? null : ($moveState === 'lost' ? 'attachment_processed' : 'file_move_pending');
            $response = pool_attach_source_response($poolFileId, $state, $reason);
            if ($replay === null) {
                $idem->store($ctx['subject'], $operation, $sourceId, $body, 200, $response['body'], 'attachment', $poolFileId);
            } else {
                $idem->complete($ctx['subject'], $operation, $sourceId, $body, 200, $response['body']);
                $response['headers'] = $replay['headers'];
            }
            $store->execute('UPDATE mobile_goods_receipts SET attachment_state=' . $store->literal($state)
                . ',attachment_error=' . ($reason === null ? 'NULL' : $store->literal($reason)) . " WHERE ordre_id=$sourceId
                AND attachment_pool_file_id=$poolFileId AND subject=" . $store->literal($ctx['subject']));
            $store->execute('COMMIT');
            return $response;
        }
        $row = pool_attach_source_row($store, $poolFileId, $ctx['subject']);
        if ($store->rows("SELECT id FROM pool_file_moves WHERE pool_file_id=$poolFileId AND state IN ('reserved','committed','lost')")) {
            throw new MobileT2Error('conflict', 409, ['reason'=>'attachment_processed']);
        }
        $relative = $row['storage_path'] ?: 'pulje/' . $row['filename'];
        $from = pool_move_path($ctx['db'], $relative);
        $hash = is_file($from) && !is_link($from) && is_readable($from) ? hash_file('sha256', $from) : false;
        if (!$hash || ($row['sha256'] && !hash_equals($row['sha256'], $hash))) {
            throw new MobileT2Error('conflict', 409, ['reason'=>'attachment_processed']);
        }
        $target = pool_attach_target_source(dirname(poolPuljePath($ctx['db'])), $source, $sourceId, basename($from));
        $dest = pool_attach_relative($ctx['db'], $target['path']);
        $stagingRelative = 'pulje/.approving/' . $poolFileId . '-' . $clientId . '.' . pathinfo($from, PATHINFO_EXTENSION);
        if ($existing) {
            if ((int)$existing[0]['pool_file_id'] !== $poolFileId || $existing[0]['state'] !== 'aborted' || $replay === null) {
                throw new MobileT2Error('conflict', 409, ['reason'=>'client_id_reused']);
            }
            $intentId = (int)$existing[0]['id'];
            $store->execute('UPDATE pool_file_moves SET source_path=' . $store->literal($relative)
                . ',staging_path=' . $store->literal($stagingRelative) . ',sha256=' . $store->literal($hash)
                . ",dest_path=NULL,state='reserved',last_error=NULL,completed_at=NULL,updated_at=now() WHERE id=$intentId");
        } else {
            $created = $store->rows('INSERT INTO pool_file_moves(client_id,pool_file_id,source_path,staging_path,sha256,state) VALUES ('
                . $store->literal($clientId) . ",$poolFileId," . $store->literal($relative) . ',' . $store->literal($stagingRelative)
                . ',' . $store->literal($hash) . ",'reserved') ON CONFLICT (client_id) DO NOTHING RETURNING id");
            if (!$created) {
                throw new MobileT2Error('conflict', 409, ['reason'=>'client_id_reused']);
            }
            $intentId = (int)$created[0]['id'];
        }
        if ($replay === null) {
            $idem->store($ctx['subject'], $operation, $sourceId, $body, 200,
                pool_attach_source_response($poolFileId, 'pending', 'in_progress')['body'], 'attachment', $poolFileId);
        }
        $store->execute('COMMIT');
        pool_approve_hook($ctx, 'reserved');
        $staging = pool_move_path($ctx['db'], $stagingRelative);
        pool_move_directory($ctx['db'], dirname($staging));
        if (!pool_attach_move($from, $staging)) {
            throw new MobileT2Error('conflict', 409, ['reason'=>'attachment_processed']);
        }
        pool_approve_hook($ctx, 'K1');
        $store->execute('BEGIN');
        $idem->replay($ctx['subject'], $operation, $sourceId, $body);
        pool_attach_source_order($store, $sourceId);
        pool_attach_source_row($store, $poolFileId, $ctx['subject']);
        $store->rows("SELECT id FROM pool_file_moves WHERE id=$intentId AND state='reserved' FOR UPDATE");
        if (!pool_move_matches($staging, $hash) || file_exists($target['path'])
            || $store->rows('SELECT id FROM documents WHERE filename=' . $store->literal(basename($from))
                . ' AND (filepath=' . $store->literal($target['filepath']) . ' OR filepath='
                . $store->literal(ltrim($target['filepath'], '/')) . ')')
            || $store->rows("SELECT id FROM pool_file_moves WHERE state='committed' AND dest_path=" . $store->literal($dest))) {
            throw new MobileT2Error('conflict', 409, ['reason'=>'attachment_processed']);
        }
        pool_audit_actor_scope($ctx['subject'], ifset($ctx, 'request_id'), function () use ($store, $ctx, $target, $from, $sourceId, $source, $poolFileId, $hash): void {
            $global = $store->rows("SELECT var_value FROM settings WHERE var_name='globalId' ORDER BY id LIMIT 1");
            pool_attach_document_rows($target, basename($from), $sourceId, [], $global ? (int)$global[0]['var_value'] : 1,
                $ctx['user_id'], $poolFileId, $source);
            PoolFileTransitions::attachIdentity($ctx['db'], $poolFileId, $target['path'], $ctx['user_id']);
            $store->execute('UPDATE pool_files SET sha256=' . $store->literal($hash) . " WHERE id=$poolFileId");
        });
        $store->execute('UPDATE pool_file_moves SET dest_path=' . $store->literal($dest) . ",state='committed',updated_at=now() WHERE id=$intentId");
        $response = pool_attach_source_response($poolFileId, 'attached');
        $idem->complete($ctx['subject'], $operation, $sourceId, $body, 200, $response['body']);
        $store->execute("UPDATE mobile_goods_receipts SET attachment_state='attached',attachment_error=NULL WHERE ordre_id=$sourceId
            AND attachment_pool_file_id=$poolFileId AND subject=" . $store->literal($ctx['subject']));
        $store->execute('COMMIT');
        pool_approve_hook($ctx, 'K2');
        try {
            pool_move_directory($ctx['db'], $target['dir']);
            if (pool_attach_move($staging, $target['path'])) {
                pool_approve_hook($ctx, 'K3');
                $store->execute("UPDATE pool_file_moves SET state='done',updated_at=now(),completed_at=now() WHERE id=$intentId");
            } else {
                $response = pool_attach_source_response($poolFileId, 'pending', 'file_move_pending');
            }
        } catch (Throwable $error) {
            $response = pool_attach_source_response($poolFileId, 'pending', 'file_move_pending');
            error_log(json_encode(['event'=>'receipt_attachment_pending', 'intent_id'=>$intentId, 'exception'=>get_class($error)]));
        }
        $store->execute('BEGIN');
        $idem->complete($ctx['subject'], $operation, $sourceId, $body, 200, $response['body']);
        $store->execute('COMMIT');
        if ($replay !== null) {
            $response['headers'] = $replay['headers'];
        }
        return $response;
    } catch (Throwable $error) {
        if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) {
            $store->execute('ROLLBACK');
        }
        if ($intentId !== null) {
            try {
                pool_move_reconcile($intentId);
            } catch (Throwable $recoveryError) {
                error_log(json_encode(['event'=>'receipt_attachment_recovery_failed', 'intent_id'=>$intentId, 'exception'=>get_class($recoveryError)]));
            }
        }
        throw $error;
    } finally {
        if ($locked) {
            $store->execute("SELECT pg_advisory_unlock(hashtext('saldi:pool_move'),$poolFileId)");
        }
        foreach ($saved as $key=>[$exists,$value]) {
            if ($exists) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }
}
