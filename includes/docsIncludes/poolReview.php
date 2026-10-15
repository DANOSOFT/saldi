<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolReview.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Project stored claim employee and payable defaults for receipt review.

require_once __DIR__ . '/poolReviewVersion.php';
require_once __DIR__ . '/poolAudit.php';
require_once __DIR__ . '/poolVendorMatcher.php';
require_once __DIR__ . '/poolVendorSuggestion.php';
require_once __DIR__ . '/../../restapi/core/Paging.php';
require_once __DIR__ . '/../../restapi/core/ETag.php';
require_once __DIR__ . '/../../restapi/core/MobileDisplayName.php';
require_once __DIR__ . '/../../restapi/models/attachment/DurableAttachmentModel.php';

/**
 * Select queue identities inside the caller's consistent read snapshot.
 * @return array{items:list<array<string,mixed>>,total:int,limit:int,offset:int}
 */
function pool_review_queue(MobileSchema $store, string $state, Paging $page): array
{
    if (!in_array($state, ['pending', 'decided'], true)) {
        throw new MobileT2Error('validation_failed', 422, ['reason'=>'metadata_invalid', 'field'=>'state']);
    }
    $where = $state === 'pending' ? "status='ubehandlet' AND review_state='pending'"
        : "((status='ubehandlet' AND review_state='rejected') OR
            (status='attached' AND review_state='approved' AND reviewed_at >= now()-interval '30 days'))";
    $total = (int)$store->rows("SELECT count(*) AS total FROM pool_files WHERE $where")[0]['total'];
    $rows = $store->rows("SELECT * FROM pool_files WHERE $where ORDER BY uploaded_at DESC, id DESC " . $page->sql());
    return $page->response($rows, $total);
}

/** @return string|null ISO 8601 UTC instant, preserving missing legacy dates. */
function pool_review_time(?string $value): ?string
{
    return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
}

/** @return string Human label only; unresolved canonical identities never become public names. */
function pool_review_name(MobileDisplayName $names, string $actor): string
{
    $name = $names->sender($actor);
    return $name === '' || $name === $actor ? 'Saldi' : $name;
}

/** @return string|null Valid normalized invoice date; malformed legacy metadata is editable in detail. */
function pool_review_date(?string $value): ?string
{
    $date = normalizeDateFormat((string)$value);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date ? $date : null;
}

/** @return int|null Fiscal-year id for current names; never require valid invoice metadata to review a receipt. */
function pool_review_year(MobileSchema $store, ?string $date): ?int
{
    if ($date === null) { return null; }
    $month = (int)substr($date, 0, 4)*12 + (int)substr($date, 5, 2);
    foreach ($store->rows("SELECT kodenr,box1,box2,box3,box4 FROM grupper WHERE art='RA' ORDER BY kodenr DESC") as $year) {
        if ($month >= (int)$year['box2']*12+(int)$year['box1'] && $month <= (int)$year['box4']*12+(int)$year['box3']) {
            return (int)$year['kodenr'];
        }
    }
    return null;
}

/** @return string|null Current account label for the invoice's fiscal year. */
function pool_review_account_name(MobileSchema $store, int $account, ?int $year): ?string
{
    if ($account < 1 || $year === null) { return null; }
    return ifset($store->rows("SELECT beskrivelse FROM kontoplan WHERE kontonr=$account AND regnskabsaar=$year ORDER BY id LIMIT 1"), [0,'beskrivelse']);
}

/**
 * Claim defaults from the recorded employee, independent of the reviewer's login.
 * @return array{kind:string,employee:array{ansat_id:int,name:string,initials:string}|null,
 *   defaults:array{contra:array{type:string,kontonr:int},vat_exempt:bool}}|null
 */
function pool_review_claim(MobileSchema $store, array $row): ?array
{
    $claim = poolMetadataClaim($GLOBALS['connection'], (int)$row['id']);
    if ($claim === null) { return null; }
    $employees = $store->rows('SELECT id,navn,initialer FROM ansatte WHERE id=' . (int)$claim['ansat_id']);
    $employee = $employees[0] ?? null;
    $settings = $store->rows("SELECT var_value FROM settings WHERE var_grp='mobile_marken' AND var_name='claims_payable_account' AND user_id=0 ORDER BY id DESC LIMIT 1");
    return ['kind'=>$claim['kind'], 'employee'=>$employee === null ? null :
        ['ansat_id'=>(int)$employee['id'], 'name'=>(string)$employee['navn'], 'initials'=>(string)$employee['initialer']],
        'defaults'=>['contra'=>['type'=>'F', 'kontonr'=>(int)($settings[0]['var_value'] ?? 0)], 'vat_exempt'=>$claim['kind'] === 'koersel']];
}

/**
 * Public review projection: metadata and names, with no subject or storage paths.
 * @return array{
 *   id:int,client_id:?string,status:string,review_state:string,display_status:string,
 *   uploaded_at:?string,uploaded_by:array{name:string,is_me:bool}|null,source:string,
 *   vendor:string,description:string,amount:?string,currency:string,date:?string,
 *   invoice_number:string,vat_code:?string,suggestion:?array,bank_line:?array,
 *   claim:array{kind:string,employee:array{ansat_id:int,name:string,initials:string}|null,
 *     defaults:array{contra:array{type:string,kontonr:int},vat_exempt:bool}}|null,has_thumbnail:bool,etag:string
 * }
 */
function pool_review_item(MobileSchema $store, DurableAttachmentModel $model, MobileDisplayName $names, string $subject, array $row): array
{
    $id = (int)$row['id'];
    $date = pool_review_date(ifset($row, 'file_date'));
    $year = pool_review_year($store, $date);
    $saved = $model->toArray($row);
    $suggestions = $store->rows("SELECT * FROM classification_suggestions WHERE pool_file_id=$id ORDER BY id DESC LIMIT 1");
    $suggestion = null;
    if ($suggestions) {
        $s = $suggestions[0];
        $suggestion = ['id'=>(int)$s['id'], 'kontonr'=>$s['kontonr'] === null ? null : (int)$s['kontonr'],
            'account_name'=>pool_review_account_name($store, (int)$s['kontonr'], $year), 'vat_code'=>$s['vat_code'], 'basis'=>$s['basis']];
    }
    $bank = null;
    $bankId = (int)ifset($row, 'bank_line_id', 0);
    if ($bankId > 0) {
        $lines = $store->rows("SELECT id,text,transdate,amount FROM bank_lines WHERE id=$bankId");
        if ($lines) {
            $b = $lines[0];
            $bank = ['id'=>(int)$b['id'], 'text'=>(string)$b['text'], 'date'=>$b['transdate'], 'amount'=>number_format((float)$b['amount'], 2, '.', '')];
        }
    }
    $amount = ifset($row, 'norm_amount');
    if ($amount === null) { $amount = normalizePoolAmount(ifset($row, 'amount', '')); }
    $owner = trim((string)ifset($row, 'uploaded_by', ''));
    return ['id'=>$id, 'client_id'=>$row['client_id'], 'status'=>$row['status'], 'review_state'=>$row['review_state'],
        'display_status'=>$saved['display_status'], 'uploaded_at'=>pool_review_time($row['uploaded_at']),
        'uploaded_by'=>$owner === '' ? null : ['name'=>pool_review_name($names, $owner), 'is_me'=>$owner === $subject],
        'source'=>empty($row['client_id']) ? 'web' : 'app',
        'vendor'=>trim((string)ifset($row, 'vendor_name', '')) ?: (string)$row['subject'],
        'description'=>(string)$row['description'], 'amount'=>$amount === null ? null : number_format((float)$amount, 2, '.', ''),
        'currency'=>normalizePoolCurrency($row['currency']) ?: 'DKK', 'date'=>$date, 'invoice_number'=>(string)$row['invoice_number'],
        'vat_code'=>$row['vat_code'], 'suggestion'=>$suggestion, 'bank_line'=>$bank, 'claim'=>pool_review_claim($store, $row),
        'has_thumbnail'=>$saved['size'] !== null && DurableAttachmentModel::mimeType($row) === 'application/pdf', 'etag'=>$saved['etag']];
}

/**
 * Reject a locked pending receipt without changing its lifecycle or file.
 * The caller owns the transaction, idempotency, actor scope and event emission.
 * @return array<string,mixed> Updated internal pool row.
 */
function pool_review_reject(MobileSchema $store, array $row, string $actor, ?string $ifMatch, array $input): array
{
    if (pg_transaction_status($GLOBALS['connection']) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Rejection requires a caller transaction');
    }
    $id = (int)$row['id'];
    if ($row['status'] !== 'ubehandlet') {
        throw new MobileT2Error('conflict', 409, ['reason'=>'attachment_processed']);
    }
    if ($row['review_state'] !== 'pending') {
        $by = pool_review_name(new MobileDisplayName($GLOBALS['connection']), (string)$row['reviewed_by']);
        throw new MobileT2Error('conflict', 409, ['reason'=>'already_decided', 'decided_by'=>$by]);
    }
    ETag::requireMatch($ifMatch, '"' . pool_review_version($row) . '"');
    $code = ifset($input, 'reason_code');
    $message = ifset($input, 'message');
    if (!is_string($code) || !in_array($code, ['unreadable','not_invoice','amount_mismatch','wrong_regnskab','other'], true)
        || !is_string($message) || trim($message) === '' || !mb_check_encoding($message, 'UTF-8')
        || mb_strlen($message) > 4000 || str_contains($message, "\0")) {
        throw new MobileT2Error('validation_failed', 422, ['reason'=>'rejection_reason_required']);
    }
    foreach (array_keys($input) as $key) {
        if (!in_array($key, ['id','client_id','reason_code','message'], true)) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'metadata_invalid','field'=>$key]);
        }
    }
    $actorSql = $store->literal($actor);
    $codeSql = $store->literal($code);
    $noteSql = $store->literal(trim($message));
    $store->execute("UPDATE pool_files SET review_state='rejected',reviewed_by=$actorSql,reviewed_at=now(),
        rejection_code=$codeSql,rejection_note=$noteSql WHERE id=$id");
    pool_audit_append($id, 'rejected', $actor, [], $code, trim($message));
    return $store->rows("SELECT * FROM pool_files WHERE id=$id")[0];
}
