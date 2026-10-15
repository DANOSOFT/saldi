<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolApprove.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Reserve attachment destinations and durably record collision recovery before renaming.
// 20261001 CDX/LH Apply stored claim defaults and refuse generated financial overrides in every approval mode.
// 20261001 CDX/LH Apply claim employee and mileage VAT exemption to unposted bank rows independently of debit classification.
require_once __DIR__ . '/poolFileTransitions.php';
require_once __DIR__ . '/poolReviewVersion.php';
require_once __DIR__ . '/poolClassification.php';
require_once __DIR__ . '/../finans/kladdeJournal.php';
require_once __DIR__ . '/../../finans/kassekladde_includes/journalLine.php';
require_once __DIR__ . '/../../restapi/core/ETag.php';
require_once __DIR__ . '/../../restapi/services/MobileEventOutbox.php';
require_once __DIR__ . '/../../restapi/core/MobileDisplayName.php';

/** Generated financial facts cannot be corrected, including in posted-row attachment mode. */
function pool_approve_claim_guard(array $row, array $decision): void
{
    $metadata = $decision;
    if (array_key_exists('date', $decision)) { $metadata['file_date'] = $decision['date']; }
    if (isset($metadata['amount']) && (is_int($metadata['amount']) || is_float($metadata['amount']))) {
        $metadata['amount'] = number_format($metadata['amount'], 2, '.', '');
    }
    if (poolMetadataGeneratedChange($row, $metadata)) {
        throw new MobileT2Error('conflict', 409, ['reason'=>'generated_document']);
    }
    if (array_key_exists('vat_code', $decision)
        && (poolMetadataClaim($GLOBALS['connection'], (int)$row['id'])['kind'] ?? null) === 'koersel') {
        throw new MobileT2Error('validation_failed', 422, ['reason'=>'vat_code_invalid']);
    }
}

/** @return array<string,mixed> Pool identity, optionally locked, never resolved by a guessed path. */
function pool_approve_row(int $id, bool $lock=false): array
{
    $rows=pool_attach_query("SELECT * FROM pool_files WHERE id=$id".($lock ? ' FOR UPDATE' : ''));
    if (!$rows || $rows[0]['status'] === 'staged') { throw new MobileT2Error('not_found',404); }
    return $rows[0];
}

/** @return void Check state before ETag so a web attachment is an attachment_processed conflict. */
function pool_approve_check(array $row, ?string $ifMatch): void
{
    if ($row['status'] === 'deleted') { throw new MobileT2Error('gone',410); }
    if ($row['review_state'] === 'approved') {
        $reviewer=(string)ifset($row,'reviewed_by','');
        throw new MobileT2Error('conflict',409,['reason'=>'already_decided',
            'decided_by'=>$reviewer === '' ? null : (new MobileDisplayName($GLOBALS['connection']))->sender($reviewer)]);
    }
    if ($row['status'] !== 'ubehandlet') { throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']); }
    if (!in_array($row['review_state'],['pending','rejected'],true)) { throw new MobileT2Error('conflict',409,['reason'=>'already_decided']); }
    ETag::requireMatch($ifMatch,'"'.pool_review_version($row).'"');
}

/** @return array<string,mixed>|null Newest extant journal row linked to this bank identity. */
function pool_approve_bank_target(int $bankId): ?array
{
    $rows=pool_attach_query("SELECT k.*,j.bogfort,j.kladdenote FROM bank_line_journal_links l
        JOIN kassekladde k ON k.id=l.kassekladde_id JOIN kladdeliste j ON j.id=k.kladde_id
        WHERE l.bank_line_id=$bankId ORDER BY l.created_at DESC,k.id DESC LIMIT 1");
    return $rows ? $rows[0] : null;
}

/** @return string Contained absolute path from a durable tenant-relative move field. */
function pool_move_path(string $db, string $relative): string
{
    $path=dirname(poolPuljePath($db)).'/'.$relative;
    pool_attach_relative($db,$path);
    return $path;
}

/** @return void Create a contained directory for staging or a committed target. */
function pool_move_directory(string $db, string $path): void
{
    pool_attach_relative($db,$path.'/placeholder');
    if (!is_dir($path) && !mkdir($path,0777,true) && !is_dir($path)) {
        throw new RuntimeException('Attachment folder unavailable');
    }
    pool_attach_relative($db,$path.'/placeholder');
}

/** @return bool File at this exact location with the intent's content hash. */
function pool_move_matches(string $path, string $hash): bool
{
    clearstatcache(true);
    if (!is_file($path) || is_link($path) || !is_readable($path)) { return false; }
    $actual=hash_file('sha256',$path);
    return is_string($actual) && hash_equals($hash,$actual);
}

/**
 * Recover a dead owner's intent. The tenant connection must be idle; live owners are skipped.
 * A committed decision is finished, a reservation is undone; a missing/hash-mismatched file is retained as lost.
 * @param ?Closure $hook CLI-only injected fault hook for recovery tests.
 * @return string busy | reserved | committed | done | aborted | lost
 */
function pool_move_reconcile(int $intentId, ?Closure $hook=null): string
{
    $connection=$GLOBALS['connection'];
    if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) { throw new LogicException('Move recovery owns its transaction'); }
    $store=new MobileSchema($connection);
    $rows=$store->rows("SELECT * FROM pool_file_moves WHERE id=$intentId");
    if (!$rows) { throw new InvalidArgumentException('Missing move intent'); }
    $id=(int)$rows[0]['pool_file_id'];
    $locked=$store->rows("SELECT pg_try_advisory_lock(hashtext('saldi:pool_move'),$id) AS locked")[0]['locked'] === 't';
    if (!$locked) { return 'busy'; }
    try {
        $store->execute('BEGIN');
        // Same order as approval and web transitions: identity, then intent.
        pool_approve_row($id,true);
        $intent=$store->rows("SELECT * FROM pool_file_moves WHERE id=$intentId FOR UPDATE")[0];
        $state=$intent['state'];
        if (!in_array($state,['reserved','committed'],true)) { $store->execute('COMMIT'); return $state; }
        $db=pg_dbname($connection);
        $source=pool_move_path($db,$intent['source_path']);
        $staging=pool_move_path($db,$intent['staging_path']);
        $dest=$intent['dest_path'] === null ? null : pool_move_path($db,$intent['dest_path']);
        if ($state === 'reserved') {
            if ($dest === null && pool_move_matches($staging,$intent['sha256']) && file_exists($source)) {
                // In a reservation, dest_path records collision recovery rather than an approval target.
                // Reserve its pool alias too, so web sync cannot adopt the fallback after a killed rename.
                $recoveryRelative='pulje/'.$id.'-'.basename($source);
                $dest=pool_move_path($db,$recoveryRelative);
                if (file_exists($dest)) { $store->execute('COMMIT'); return 'reserved'; }
                PoolFileTransitions::releaseName($db,$dest);
                $store->execute('UPDATE pool_files SET filename='.$store->literal(basename($dest))." WHERE id=$id");
                $store->execute('UPDATE pool_file_moves SET dest_path='.$store->literal($recoveryRelative).",updated_at=now() WHERE id=$intentId");
                $store->execute('COMMIT');
                pool_approve_hook(['hook'=>$hook],'recovery_reserved');
                $store->execute('BEGIN');
                pool_approve_row($id,true);
                $intent=$store->rows("SELECT * FROM pool_file_moves WHERE id=$intentId FOR UPDATE")[0];
            }
            if ($dest !== null) {
                if (pool_move_matches($dest,$intent['sha256'])) {
                    // The rename survived a rolled-back identity update; finish from its durable path.
                    if (!PoolFileTransitions::move($db,$dest,$dest,$intentId)) { $store->execute('COMMIT'); return 'reserved'; }
                    $state='aborted';
                } elseif (pool_move_matches($staging,$intent['sha256'])) {
                    if (!PoolFileTransitions::move($db,$staging,$dest,$intentId)) { $store->execute('COMMIT'); return 'reserved'; }
                    pool_approve_hook(['hook'=>$hook],'recovery_renamed');
                    $state='aborted';
                } else { $state='lost'; }
            } elseif (pool_move_matches($staging,$intent['sha256'])) {
                if (!pool_attach_move($staging,$source)) { $store->execute('COMMIT'); return 'reserved'; }
                $state='aborted';
            } elseif (pool_move_matches($source,$intent['sha256'])) { $state='aborted'; }
            else { $state='lost'; }
        } else {
            if ($dest !== null && pool_move_matches($dest,$intent['sha256'])) { $state='done'; }
            elseif (pool_move_matches($staging,$intent['sha256']) && $dest !== null) {
                pool_move_directory($db,dirname($dest));
                if (pool_attach_move($staging,$dest)) { $state='done'; }
            } else { $state='lost'; }
        }
        $error=$state === 'lost' ? "'file_missing_or_hash_mismatch'" : 'NULL';
        $finished=in_array($state,['done','aborted','lost'],true) ? 'now()' : 'NULL';
        $store->execute("UPDATE pool_file_moves SET state=".$store->literal($state).",last_error=$error,updated_at=now(),completed_at=$finished WHERE id=$intentId");
        $store->execute('COMMIT');
        if ($state === 'lost') { error_log(json_encode(['event'=>'pool_move_lost','intent_id'=>$intentId,'pool_file_id'=>$id])); }
        return $state;
    } catch (Throwable $error) {
        if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) { $store->execute('ROLLBACK'); }
        throw $error;
    } finally { $store->execute("SELECT pg_advisory_unlock(hashtext('saldi:pool_move'),$id)"); }
}

/**
 * Reconcile a bounded tenant batch with explicit connection selection, suitable for the cron's one call.
 * @return int Number of dead-owner intents examined (busy owners excluded).
 */
function pool_move_reconcile_pending($connection, int $ageSeconds=0): int
{
    if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) { throw new LogicException('Move recovery requires idle connection'); }
    $previous=ifset($GLOBALS,'connection');
    $GLOBALS['connection']=$connection;
    try {
        $store=new MobileSchema($connection);
        $rows=$store->rows("SELECT id FROM pool_file_moves WHERE state IN ('reserved','committed') AND created_at<=now()-interval '".(int)max(0,$ageSeconds)." seconds' ORDER BY id LIMIT 100");
        $count=0;
        foreach ($rows as $row) {
            try { if (pool_move_reconcile((int)$row['id']) !== 'busy') { $count++; } }
            catch (Throwable $error) { error_log(json_encode(['event'=>'pool_move_recovery_failed','intent_id'=>(int)$row['id'],'exception'=>get_class($error)])); }
        }
        return $count;
    } finally { $GLOBALS['connection']=$previous; }
}

/** @return void Test-only injected closure; HTTP never accepts hooks or pauses. */
function pool_approve_hook(array $ctx, string $point): void
{
    if (PHP_SAPI === 'cli' && ifset($ctx,'hook') instanceof Closure) { $ctx['hook']($point); }
}

/**
 * Validate correction types and apply the shared metadata engine in the decision transaction.
 * @return array<string,mixed> Corrected pool row, using the fiscal year of its corrected date.
 */
function pool_approve_metadata(array $ctx, array $row, array $decision, bool $posted): array
{
    $metadata=[];
    foreach (['date'=>'file_date','amount'=>'amount','currency'=>'currency','description'=>'description','invoice_number'=>'invoice_number'] as $key=>$field) {
        if (!array_key_exists($key,$decision)) { continue; }
        $value=$decision[$key];
        if ((!is_string($value) && !is_int($value) && !is_float($value))
            || (is_string($value) && (!mb_check_encoding($value,'UTF-8') || str_contains($value,"\0")))) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>$key]);
        }
        if ($key === 'amount' && !is_string($value)) {
            if (!is_finite((float)$value) || abs($value)>=1000000000000 || $value !== round($value,2)) {
                // Integer comparison must not reject integer amounts.
                if (!is_int($value) || abs($value)>=1000000000000) { throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>$key]); }
            }
            $value=number_format($value,2,'.','');
        }
        $metadata[$field]=(string)$value;
    }
    pool_approve_claim_guard($row,$decision);
    $date=normalizeDateFormat((string)ifset($metadata,'file_date',$row['file_date']));
    if (!$posted) {
        $context=new EngineContext($ctx['connection'],$ctx['db'],$ctx['tenant_id'],$ctx['username'],$ctx['user_id'],$date);
        $GLOBALS['regnaar']=$context->regnaar;
        $dateErrors=kladde_line_check(['transdate'=>$date],$context->regnaar);
        if ($dateErrors) { throw new MobileT2Error('validation_failed',422,$dateErrors[0]); }
        // Accounting validation provides its precise account_closed/invalid reason before metadata validation.
        $number=ifset($decision,'kontonr',ctype_digit((string)$row['account']) ? (int)$row['account'] : null);
        if (!is_int($number) || $number <= 0) { throw new MobileT2Error('validation_failed',422,['reason'=>'account_invalid']); }
        $errors=kladde_line_check(['debet'=>$number,'d_type'=>'F','transdate'=>$date,'valuta'=>normalizePoolCurrency(ifset($metadata,'currency',$row['currency'])) ?: $GLOBALS['baseCurrency']],$context->regnaar);
        if ($errors) { throw new MobileT2Error('validation_failed',422,$errors[0]); }
        $metadata['account']=(string)$number;
    } else {
        if ((poolMetadataClaim($ctx['connection'], (int)$row['id'])['kind'] ?? null) === 'koersel') { return $row; }
        // Posted attachment corrections must not validate or erase a historical OCR account.
        // Apply the descriptive subset using the same normalizers as poolMetadataApply.
        $values=[];
        foreach (['file_date','amount','currency','description','invoice_number'] as $field) {
            $value=trim((string)ifset($metadata,$field,$row[$field]));
            if ($field === 'file_date') {
                $value=normalizeDateFormat($value);
                if ($value !== '' && (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/',$value,$parts)
                    || !checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1]))) {
                    throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>'date']);
                }
            }
            if ($field === 'currency') { $value=normalizePoolCurrency($value) ?? ''; }
            $values[$field]=$value;
        }
        $amount=normalizePoolAmount($values['amount']);
        if ($values['amount'] !== '' && $amount === null) { throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>'amount']); }
        $store=new MobileSchema($ctx['connection']);
        $assignments=[];
        foreach ($values as $field=>$value) { $assignments[]=$field.'='.$store->literal($value); }
        $store->execute('UPDATE pool_files SET '.implode(',',$assignments).',norm_amount='.($amount === null ? 'NULL' : (float)$amount)
            .',manually_edited=true,updated=now() WHERE id='.(int)$row['id']);
        return pool_approve_row((int)$row['id']);
    }
    try { return poolMetadataApply($row,$metadata,true,poolMetadataVersion($row),(int)$GLOBALS['regnaar'])['row']; }
    catch (InvalidArgumentException $error) { throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid']); }
}

/** @return array{fields:array<string,mixed>,currency:string,rate:?array,warnings:list<array{code:string}>} Draft values resolved by Saldi's own rules. */
function pool_approve_fields(array $row, array $decision, int $journalId): array
{
    $currency=trim((string)$row['currency']) ?: $GLOBALS['baseCurrency'];
    $foreign=$currency !== $GLOBALS['baseCurrency'];
    $rate=$foreign ? kladde_line_rate($currency,$row['file_date']) : null;
    if ($foreign && ($rate === null || (float)$rate['rate'] <= 0)) { throw new MobileT2Error('validation_failed',422,['reason'=>'missing_exchange_rate']); }
    $code=ifset($decision,'vat_code',ifset($row,'vat_code'));
    $claim=poolMetadataClaim($GLOBALS['connection'], (int)$row['id']);
    $statement=($claim['kind'] ?? null) === 'koersel';
    $exempt=ifset($decision,'vat_exempt',false);
    if ($statement && is_bool($exempt)) { $exempt=true; $code=null; }
    if (!is_bool($exempt) || ($exempt && array_key_exists('vat_code',$decision))
        || ($code !== null && (!is_string($code) || !isset(kassekladde_vat_codes()[strtoupper(trim($code))])))) {
        throw new MobileT2Error('validation_failed',422,['reason'=>'vat_code_invalid']);
    }
    $vat=$exempt ? '' : ($code === null ? lookup_account_vat_code((string)$row['account'],'F',(int)$GLOBALS['regnaar'],kassekladde_vat_codes()) : strtoupper(trim($code)));
    $contra=ifset($decision,'contra');
    if ($contra instanceof stdClass) { $contra=(array)$contra; }
    if ($contra === null && $claim !== null) {
        $settings=pool_attach_query("SELECT var_value FROM settings WHERE var_grp='mobile_marken' AND var_name='claims_payable_account' AND user_id=0 ORDER BY id DESC LIMIT 1");
        $contra=['type'=>'F','kontonr'=>(string)($settings[0]['var_value'] ?? '')];
    }
    if ($contra === null) {
        $vendor=poolVendorSuggestion(poolVendorFromRow($row,poolVendorLoadIndex()));
        $contra=$vendor['mode'] === 'auto' ? ['type'=>'K','kontonr'=>(string)$vendor['kontonr']] : ['type'=>'none'];
    }
    if (!is_array($contra) || !in_array(ifset($contra,'type'),['none','F','K'],true)) {
        throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>'contra']);
    }
    $type=$contra['type'] === 'none' ? 'F' : $contra['type'];
    $credit=$contra['type'] === 'none' ? 0 : ifset($contra,'kontonr');
    if ((!is_int($credit) && (!is_string($credit) || !preg_match('/\A[0-9]{1,9}\z/',$credit))) || (int)$credit<($contra['type'] === 'none' ? 0 : 1) || (int)$credit>2147483647) {
        throw new MobileT2Error('validation_failed',422,['reason'=>$type === 'K' ? 'kreditor_invalid' : 'account_invalid']);
    }
    $group=$foreign ? pool_attach_query("SELECT kodenr FROM grupper WHERE art='VK' AND box1='".db_escape_string($currency)."' LIMIT 1") : [];
    $amount=normalizePoolAmount($row['amount']);
    if ($amount === null || abs($amount)>=1000000000000) { throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>'amount']); }
    $fields=['kladde_id'=>$journalId,'transdate'=>$row['file_date'],'beskrivelse'=>trim((string)$row['description']) ?: $row['subject'],
        'faktura'=>$row['invoice_number'],'d_type'=>'F','debet'=>(int)$row['account'],'k_type'=>$type,'kredit'=>(int)$credit,
        'debetvat'=>$vat,'kreditvat'=>$exempt ? '' : lookup_account_vat_code((string)$credit,$type,(int)$GLOBALS['regnaar'],kassekladde_vat_codes()),'momsfri'=>$exempt ? 'on' : '',
        'amount'=>number_format($amount,2,'.',''),'valuta'=>$foreign ? (int)$group[0]['kodenr'] : 0,'valutakurs'=>$rate === null ? 100 : $rate['rate']];
    $warnings=[];
    if ($claim !== null && $claim['ansat_id'] !== null) {
        $employee=pool_attach_query("SELECT id FROM ansatte WHERE id=".(int)$claim['ansat_id']." AND COALESCE(trim(lukket),'') <> 'on'
            AND konto_id=(SELECT id FROM adresser WHERE art='S' ORDER BY id LIMIT 1)");
        if ($employee) { $fields['ansat']=(int)$claim['ansat_id']; }
        else { $warnings[]=['code'=>'not_own_company']; }
    }
    $validation=$fields;
    $validation['valuta']=$currency;
    $errors=kladde_line_check($validation,(int)$GLOBALS['regnaar']);
    if ($errors) { throw new MobileT2Error('validation_failed',422,$errors[0]); }
    return ['fields'=>$fields,'currency'=>$currency,'rate'=>$rate,'warnings'=>$warnings];
}

/**
 * Recoverable approval with separate reservation/decision transactions. Requires idle EngineContext globals.
 * @param array{connection:mixed,db:string,tenant_id:int,subject:string,username:string,user_id:int,request_id:?string,if_match:?string,hook?:?Closure} $ctx
 * @return array{status:int,body:array,headers:array<string,string>}
 */
function pool_approve(array $ctx, int $poolFileId, array $decision): array
{
    $connection=$ctx['connection'];
    if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) { throw new LogicException('Approval owns its transactions'); }
    $store=new MobileSchema($connection);
    $client=Idempotency::clientId($decision);
    $decision['client_id']=$client;
    $idempotency=new Idempotency($connection);
    pool_move_reconcile_pending($connection,120);
    $store->execute("SELECT pg_advisory_lock(hashtext('saldi:pool_move'),$poolFileId)");
    $intentId=null;
    try {
        $open=$store->rows("SELECT id FROM pool_file_moves WHERE pool_file_id=$poolFileId AND state IN ('reserved','committed')");
        foreach ($open as $move) {
            try { pool_move_reconcile((int)$move['id']); }
            catch (Throwable $error) { error_log(json_encode(['event'=>'pool_move_recovery_failed','intent_id'=>(int)$move['id'],'exception'=>get_class($error)])); }
        }
        $store->execute('BEGIN');
        try {
            $replay=$idempotency->replay($ctx['subject'],'attachment.approve',$poolFileId,$decision);
            $store->execute('COMMIT');
        } catch (Throwable $error) { $store->execute('ROLLBACK'); throw $error; }
        if ($replay !== null) { return $replay; }
        $row=pool_approve_row($poolFileId);
        pool_approve_check($row,$ctx['if_match']);
        pool_approve_claim_guard($row,$decision);
        $bankId=(int)ifset($row,'bank_line_id',0);
        $bank=$bankId ? pool_approve_bank_target($bankId) : null;
        if ($bankId && $bank === null) { throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']); }
        $journalId=$bank === null ? mobile_approval_journal($ctx['username']) : (int)$bank['kladde_id'];
        pool_approve_hook($ctx,'target_resolved');
        $store->execute('BEGIN');
        try {
            $row=pool_approve_row($poolFileId,true);
            pool_approve_check($row,$ctx['if_match']);
            pool_approve_claim_guard($row,$decision);
            if ((int)ifset($row,'bank_line_id',0) !== $bankId) { throw new MobileT2Error('precondition_failed',412); }
            $relative=ifset($row,'storage_path') ?: 'pulje/'.$row['filename'];
            $source=pool_move_path($ctx['db'],$relative);
            $hash=is_file($source) && !is_link($source) ? hash_file('sha256',$source) : false;
            if ($hash === false || (ifset($row,'sha256') && !hash_equals($row['sha256'],$hash))) {
                throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']);
            }
            $stagingRelative='pulje/.approving/'.$poolFileId.'-'.$client.'.'.pathinfo($source,PATHINFO_EXTENSION);
            $old=$store->rows('SELECT * FROM pool_file_moves WHERE client_id='.$store->literal($client).' FOR UPDATE');
            if ($old && ((int)$old[0]['pool_file_id'] !== $poolFileId || $old[0]['state'] !== 'aborted')) {
                throw new MobileT2Error('conflict',409,['reason'=>'client_id_reused']);
            }
            if ($old) {
                $intentId=(int)$old[0]['id'];
                $store->execute("UPDATE pool_file_moves SET source_path=".$store->literal($relative).',staging_path='.$store->literal($stagingRelative).',sha256='.$store->literal($hash).",dest_path=NULL,state='reserved',last_error=NULL,completed_at=NULL,updated_at=now() WHERE id=$intentId");
            } else {
                $created=$store->rows('INSERT INTO pool_file_moves(client_id,pool_file_id,source_path,staging_path,sha256,state) VALUES ('
                    .$store->literal($client).",$poolFileId,".$store->literal($relative).','.$store->literal($stagingRelative).','.$store->literal($hash).",'reserved') ON CONFLICT (client_id) DO NOTHING RETURNING id");
                if (!$created) { throw new MobileT2Error('conflict',409,['reason'=>'client_id_reused']); }
                $intentId=(int)$created[0]['id'];
            }
            $store->execute('COMMIT');
        } catch (Throwable $error) { $store->execute('ROLLBACK'); throw $error; }
        $staging=pool_move_path($ctx['db'],$stagingRelative);
        pool_move_directory($ctx['db'],dirname($staging));
        if (!pool_attach_move($source,$staging)) { throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']); }
        pool_approve_hook($ctx,'K1');
        for ($attempt=0;$attempt<2;$attempt++) {
            $store->execute('BEGIN');
            try {
                // Subject/client lock precedes resource locks across the Idempotency API.
                $replay=$idempotency->replay($ctx['subject'],'attachment.approve',$poolFileId,$decision);
                if ($replay !== null) { throw new MobileT2Error('conflict',409,['reason'=>'client_id_reused']); }
                $row=pool_approve_row($poolFileId,true);
                pool_approve_check($row,$ctx['if_match']);
                pool_approve_claim_guard($row,$decision);
                $store->rows("SELECT id FROM pool_file_moves WHERE id=$intentId AND state='reserved' FOR UPDATE");
                $journals=$store->rows("SELECT * FROM kladdeliste WHERE id=$journalId FOR UPDATE");
                $journal=$journals ? $journals[0] : null;
                $posted=$bankId && $journal !== null && $journal['bogfort'] !== '-';
                $bankRow=null;
                if ($bankId) {
                    $bankIdentity=$store->rows("SELECT * FROM bank_lines WHERE id=$bankId FOR UPDATE");
                    if (!$bankIdentity) { throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']); }
                    $target=pool_approve_bank_target($bankId);
                    if ($target === null || (int)$target['id'] !== (int)$bank['id'] || (int)$target['kladde_id'] !== $journalId) {
                        throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']);
                    }
                    $bankRow=$store->rows('SELECT * FROM kassekladde WHERE id='.(int)$target['id'].' FOR UPDATE')[0];
                }
                $lease=$posted || $journal === null ? null : kladde_lease_holder($journalId);
                if ($journal === null || (!$bankId && ($journal['bogfort'] !== '-' || $journal['mobile_approval_journal'] !== 't')) || $lease !== null) {
                    if (!$bankId && $attempt === 0) {
                        $store->execute('ROLLBACK');
                        $journalId=mobile_approval_journal($ctx['username']);
                        continue;
                    }
                    throw new MobileT2Error('conflict',409,['reason'=>'journal_locked','locked_by'=>$lease['holder'] ?? null,'self'=>($lease['holder'] ?? null) === $ctx['username']]);
                }
                if (!pool_move_matches($staging,$hash)) { throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']); }
                $mode=$posted ? 'attach_only' : ($bankId ? 'bank_row' : 'new_line');
                $warnings=[];
                $feedback=null;
                // Resolve proposal against the pre-correction evidence, before writing journal history.
                $suggestionId=null;
                if (!$posted) {
                    $date=normalizeDateFormat((string)ifset($decision,'date',$row['file_date']));
                    $yearContext=new EngineContext($connection,$ctx['db'],$ctx['tenant_id'],$ctx['username'],$ctx['user_id'],$date);
                    $GLOBALS['regnaar']=$yearContext->regnaar;
                    $evidence=classification_inputs($row,$yearContext->regnaar);
                    $suggestionId=array_key_exists('suggestion_id',$decision) ? $decision['suggestion_id'] : classification_applicable_suggestion_id($poolFileId,$evidence);
                }
                $row=pool_approve_metadata($ctx,$row,$decision,$posted);
                if ($posted) {
                    $warnings[]=['code'=>'posted_row_unchanged'];
                    $line=$bankRow;
                    $currency=trim((string)$bankIdentity[0]['currency']);
                    $rate=null;
                    if ($store->rows("SELECT d.id FROM documents d JOIN pool_files p ON p.id=d.pool_file_id WHERE d.source='kassekladde' AND d.source_id=".(int)$line['id']." AND p.sha256=".$store->literal($hash))) {
                        throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']);
                    }
                } else {
                    $resolved=pool_approve_fields($row,$decision,$journalId);
                    $fields=$resolved['fields'];
                    $warnings=array_merge($warnings,$resolved['warnings']);
                    $currency=$resolved['currency'];
                    $rate=$resolved['rate'];
                    if ($bankRow !== null) {
                        $line=$bankRow;
                        $bankFields=[];
                        if (empty($line['debet'])) {
                            $bankFields=['debet'=>(int)$fields['debet'],'d_type'=>'F',
                                'debetvat'=>$fields['debetvat'],'momsfri'=>$fields['momsfri']];
                        } else { $warnings[]=['code'=>'line_account_kept','kontonr'=>(int)$line['debet']]; }
                        if (array_key_exists('ansat',$fields)) {
                            $bankFields['ansat']=(int)$fields['ansat'];
                        }
                        if ((poolMetadataClaim($connection,$poolFileId)['kind'] ?? null) === 'koersel') {
                            $bankFields['debetvat']='';
                            $bankFields['kreditvat']='';
                            $bankFields['momsfri']='on';
                        }
                        if ($bankFields) {
                            $assignments=[];
                            foreach ($bankFields as $field=>$value) {
                                $assignments[]=$field.'='.$store->literal($value);
                            }
                            $store->execute('UPDATE kassekladde SET '.implode(',',$assignments).' WHERE id='.(int)$line['id']);
                        }
                    } else {
                        $lineId=kassekladde_insert_line($journalId,$fields);
                        if (!$lineId) { throw new RuntimeException('Journal insert failed'); }
                        $line=['id'=>$lineId];
                    }
                    $line=$store->rows('SELECT * FROM kassekladde WHERE id='.(int)$line['id'])[0];
                    $start=$store->rows("SELECT box1,box2,box3,box4 FROM grupper WHERE art='RA' AND kodenr=".(int)$GLOBALS['regnaar'])[0];
                    $from=$start['box2'].'-'.str_pad(trim($start['box1']),2,'0',STR_PAD_LEFT).'-01';
                    $to=(new DateTimeImmutable($start['box4'].'-'.str_pad(trim($start['box3']),2,'0',STR_PAD_LEFT).'-01'))->format('Y-m-t');
                    $duplicate=find_dublet((int)$line['id'],$line['transdate'],$line['d_type'],$line['debet'],$line['k_type'],$line['kredit'],$line['amount'],$line['faktura'],(float)$line['amount']*($rate === null ? 1 : (float)$rate['rate']/100),$from,$to);
                    [$voucher,$dupJournal,$dupSource]=explode(',',$duplicate);
                    if ((int)$voucher) { $warnings[]=['code'=>'possible_duplicate','bilag'=>(int)$voucher,'kladde_id'=>(int)$dupJournal,'source'=>$dupSource]; }
                    $feedback=classification_record_feedback($poolFileId,['client_id'=>$client,'kontonr'=>(int)$row['account'],
                        'vat_code'=>$fields['debetvat'] ?: null,'kreditor_kontonr'=>$fields['k_type'] === 'K' ? (string)$fields['kredit'] : null,
                        'suggestion_id'=>$suggestionId],(int)$GLOBALS['regnaar'],$ctx['subject']);
                    pool_audit_append($poolFileId,'draft_created',$ctx['subject'],['kassekladde_id'=>(int)$line['id'],'mode'=>$mode],null,null);
                }
                $target=pool_attach_target(dirname(poolPuljePath($ctx['db'])),$journalId,(int)$line['id'],basename($source));
                $dest=pool_attach_relative($ctx['db'],$target['path']);
                // The journal lock serializes this check with other approvals. A committed intent
                // reserves the destination before its file exists, and document references retain it.
                $references=$store->rows('SELECT id FROM documents WHERE filename='.$store->literal(basename($source))
                    .' AND (filepath='.$store->literal($target['filepath']).' OR filepath='.$store->literal(ltrim($target['filepath'],'/')).') LIMIT 1');
                $reserved=$store->rows("SELECT id FROM pool_file_moves WHERE state='committed' AND dest_path=".$store->literal($dest).' LIMIT 1');
                if (file_exists($target['path']) || $references || $reserved) {
                    throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']);
                }
                $global=$store->rows("SELECT var_value FROM settings WHERE var_name='globalId' ORDER BY id LIMIT 1");
                $documentUser=Subject::parse($ctx['subject'])['principal'] === 'registry' ? -1 : $ctx['user_id'];
                pool_attach_document_rows($target,basename($source),(int)$line['id'],pool_attach_siblings((int)$line['id']),
                    $global ? (int)$global[0]['var_value'] : 1,$documentUser,$poolFileId);
                PoolFileTransitions::attachIdentity($ctx['db'],$poolFileId,$target['path'],$ctx['user_id'],(int)$line['bilag']);
                $store->execute("UPDATE pool_files SET review_state='approved',reviewed_by=".$store->literal($ctx['subject']).",reviewed_at=now(),rejection_code=NULL,rejection_note=NULL,sha256=".$store->literal($hash)
                    .(!$posted ? ',vat_code='.($fields['debetvat'] === '' ? 'NULL' : $store->literal($fields['debetvat'])) : '')." WHERE id=$poolFileId");
                pool_audit_append($poolFileId,'approved',$ctx['subject'],['mode'=>$mode],null,null);
                $store->execute('INSERT INTO mobile_journal_lines(kassekladde_id,kladde_id,pool_file_id,bank_line_id,mode,currency,rate,rate_date,rate_source,created_by,client_id) VALUES ('
                    .(int)$line['id'].",$journalId,$poolFileId,".($bankId ?: 'NULL').','.$store->literal($mode).','.$store->literal($currency).','
                    .($rate === null ? 'NULL,NULL,NULL' : (float)$rate['rate'].','.$store->literal($rate['rate_date']).','.$store->literal($rate['source']))
                    .','.$store->literal($ctx['subject']).','.$store->literal($client).')');
                $name=(new MobileDisplayName($connection))->sender($ctx['subject']);
                $account=$store->rows('SELECT beskrivelse FROM kontoplan WHERE kontonr='.(int)$line['debet'].' AND regnskabsaar='.(int)$GLOBALS['regnaar'].' LIMIT 1');
                MobileEventOutbox::emit($connection,'attachment_decided','attachment_decided:'.$poolFileId.':'.$client,'attachment',$poolFileId,
                    ['decision'=>'approved','name'=>$name,'description'=>$row['description'],'amount'=>number_format((float)normalizePoolAmount($row['amount']),2,'.',''),
                    'kontonr'=>(int)$line['debet'],'account_name'=>$account ? $account[0]['beskrivelse'] : ''],ifset($row,'uploaded_by'));
                $store->execute("UPDATE pool_file_moves SET dest_path=".$store->literal($dest).",state='committed',updated_at=now() WHERE id=$intentId");
                $response=['status'=>200,'body'=>['success'=>true,'message'=>'','data'=>['id'=>$poolFileId,'status'=>'attached','review_state'=>'approved',
                    'display_status'=>$posted ? 'bogfoert' : 'godkendt','draft'=>['kassekladde_id'=>(int)$line['id'],'kladde_id'=>$journalId,'bilag'=>(int)$line['bilag'],
                    'mode'=>$mode,'balanced'=>$posted ? null : (!empty($line['debet']) && !empty($line['kredit'])),
                    'journal_note'=>$journal['kladdenote'],'currency'=>$currency,'rate'=>$rate === null ? null : number_format((float)$rate['rate'],6,'.',''),
                    'rate_date'=>$rate['rate_date'] ?? null],'warnings'=>$warnings,'feedback'=>$feedback,'etag'=>'"'.pool_review_version(pool_approve_row($poolFileId)).'"']], 'headers'=>[]];
                $idempotency->store($ctx['subject'],'attachment.approve',$poolFileId,$decision,200,$response['body'],'attachment',$poolFileId);
                pool_approve_hook($ctx,'before_commit');
                $store->execute('COMMIT');
                break;
            } catch (Throwable $error) {
                if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) { $store->execute('ROLLBACK'); }
                throw $error;
            }
        }
        pool_approve_hook($ctx,'K2');
        $pending=true;
        try {
            pool_move_directory($ctx['db'],dirname($target['path']));
            if (pool_attach_move($staging,$target['path'])) {
                pool_approve_hook($ctx,'K3');
                $store->execute("UPDATE pool_file_moves SET state='done',updated_at=now(),completed_at=now() WHERE id=$intentId");
                $pending=false;
            }
        } catch (Throwable $error) { error_log(json_encode(['event'=>'pool_move_pending','intent_id'=>$intentId,'exception'=>get_class($error)])); }
        if ($pending) {
            $response['body']['data']['warnings'][]=['code'=>'file_move_pending'];
            $store->execute('BEGIN');
            try { $idempotency->complete($ctx['subject'],'attachment.approve',$poolFileId,$decision,200,$response['body']); $store->execute('COMMIT'); }
            catch (Throwable $error) {
                if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) { $store->execute('ROLLBACK'); }
                error_log(json_encode(['event'=>'pool_move_response_update_failed','intent_id'=>$intentId,'exception'=>get_class($error)]));
            }
        }
        return $response;
    } catch (Throwable $error) {
        if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) { $store->execute('ROLLBACK'); }
        if ($intentId !== null) {
            try { pool_move_reconcile($intentId); }
            catch (Throwable $recoveryError) { error_log(json_encode(['event'=>'pool_move_recovery_failed','intent_id'=>$intentId,'exception'=>get_class($recoveryError)])); }
        }
        throw $error;
    } finally { $store->execute("SELECT pg_advisory_unlock(hashtext('saldi:pool_move'),$poolFileId)"); }
}
