<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolClassification.php --- ver 5.0.0 --- 2026-10-05 ---
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
// 20261001 CDX/LH Created. Deterministic classification and append-only feedback.
// 20261001 CDX/LH Resolve historical VAT and identify feedback proposals by their evidence hash.

require_once __DIR__ . '/../std_func.php';
require_once __DIR__ . '/poolVendorMatcher.php';
require_once __DIR__ . '/poolVendorSuggestion.php';
require_once __DIR__ . '/poolDateNormalizer.php';
require_once __DIR__ . '/../../finans/kassekladde_includes/journalHistory.php';
require_once __DIR__ . '/../../restapi/core/Idempotency.php';

/** @return ?string Stable creditor identity for certain matches, otherwise normalized seller name. */
function classification_vendor_key(array $poolRow, array $vendorIndex): ?string
{
    $vendor = poolVendorFromRow($poolRow, $vendorIndex);
    $suggestion = poolVendorSuggestion($vendor);
    if ($suggestion['mode'] === 'auto') {
        return 'K:' . $suggestion['kontonr'];
    }
    $name = trim((string)ifset($poolRow, 'vendor_name', '')) ?: (string)ifset($poolRow, 'subject', '');
    $name = normalizePoolVendorName($name);
    if (mb_strlen($name) > 118) {
        $name = mb_substr($name, 0, 53) . ':' . hash('sha256', $name);
    }
    return $name === '' ? null : 'N:' . $name;
}

/**
 * @param array{count:int,kontonr:int,last_used:?string,vat_code:?string} $a
 * @param array{count:int,kontonr:int,last_used:?string,vat_code:?string} $b
 * @return int Count, latest date, lowest account, then canonical VAT tie breaker.
 */
function classification_evidence_order(array $a, array $b): int
{
    return ($b['count'] <=> $a['count']) ?: strcmp((string)$b['last_used'], (string)$a['last_used'])
        ?: ($a['kontonr'] <=> $b['kontonr']) ?: strcmp((string)$a['vat_code'], (string)$b['vat_code']);
}

/**
 * Aggregate only valid finance counter-accounts. Never expose voucher texts or amounts.
 * @return list<array{kontonr:int,account_name:string,vat_code:?string,count:int,last_used:?string}>
 */
function classification_rank(array $rows, array $accounts, array $vatCodes): array
{
    $groups = [];
    foreach ($rows as $row) {
        $type = strtoupper(trim((string)ifset($row, 'kontoart', 'F'))) ?: 'F';
        $number = (int)ifset($row, 'kontonr', 0);
        if ($type !== 'F' || !isset($accounts[$number])) {
            continue;
        }
        $vat = trim((string)ifset($row, 'vat', ''));
        if (str_replace(' ', '', (string)ifset($row, 'momsfri', ''))) {
            $vat = null;
        } elseif ($vat === '' || !isset($vatCodes[$vat])) {
            $vat = $accounts[$number]['vat_code'];
        }
        $key = $number . '|' . ($vat ?? '');
        if (!isset($groups[$key])) {
            $groups[$key] = ['kontonr' => $number, 'account_name' => $accounts[$number]['account_name'],
                'vat_code' => $vat, 'count' => 0, 'last_used' => null];
        }
        $groups[$key]['count'] += (int)ifset($row, 'count', 1);
        $date = (string)ifset($row, 'transdate', '');
        if ($date > (string)$groups[$key]['last_used']) {
            $groups[$key]['last_used'] = $date;
        }
    }
    $groups = array_values($groups);
    usort($groups, 'classification_evidence_order');
    $ranked = [];
    foreach ($groups as $group) {
        $number = $group['kontonr'];
        if (!isset($ranked[$number])) {
            // First VAT variant is that account's most used, latest, then lexical code.
            $ranked[$number] = $group;
        } else {
            $ranked[$number]['count'] += $group['count'];
            $ranked[$number]['last_used'] = max($ranked[$number]['last_used'], $group['last_used']);
        }
    }
    $ranked = array_values($ranked);
    usort($ranked, 'classification_evidence_order');
    return $ranked;
}

/**
 * Pure det-1 precedence over validated, aggregated tenant evidence.
 * @param array{accounts:array,vat_codes:array,correction:?array,history:array,common:array,ocr:?int,kreditor:?array} $inputs
 * @return array{suggestion_id:?int,kontonr:?int,account_name:?string,vat_code:?string,vat_name:?string,kreditor:?array,basis:string,basis_detail:array{count:int,total:int,last_used:?string,corrected_on:?string},alternatives:array,engine_version:string}
 */
function classification_decide(array $inputs): array
{
    $result = ['suggestion_id' => null, 'kontonr' => null, 'account_name' => null, 'vat_code' => null,
        'vat_name' => null, 'kreditor' => $inputs['kreditor'], 'basis' => 'none',
        'basis_detail' => ['count' => 0, 'total' => 0, 'last_used' => null, 'corrected_on' => null],
        'alternatives' => [], 'engine_version' => 'det-1'];
    $history = $inputs['history'];
    usort($history, 'classification_evidence_order');
    $common = $inputs['common'];
    usort($common, 'classification_evidence_order');
    $total = array_sum(array_column($history, 'count'));
    $correction = $inputs['correction'];
    $chosen = null;
    if ($correction !== null && isset($inputs['accounts'][(int)$correction['kontonr']])
        && ($correction['vat_code'] === null || isset($inputs['vat_codes'][$correction['vat_code']]))) {
        $chosen = $correction;
        $result['basis'] = 'last_correction';
        $result['basis_detail']['corrected_on'] = substr($correction['created_at'], 0, 10);
        $result['basis_detail']['count'] = 1;
        $result['basis_detail']['total'] = 1;
        $result['kreditor'] = ifset($correction, 'kreditor', $inputs['kreditor']);
    } elseif ($total >= 2) {
        $chosen = $history[0];
        $result['basis'] = 'supplier_history';
        $result['basis_detail'] = ['count' => $chosen['count'], 'total' => $total,
            'last_used' => $chosen['last_used'], 'corrected_on' => null];
        $result['alternatives'] = array_slice($history, 1, 5);
    } elseif ($inputs['ocr'] !== null && isset($inputs['accounts'][$inputs['ocr']])) {
        $chosen = $inputs['accounts'][$inputs['ocr']] + ['kontonr' => $inputs['ocr']];
        $result['basis'] = 'ocr';
    } elseif ($common) {
        $result['basis'] = 'common_accounts';
        $result['alternatives'] = array_slice($common, 0, 5);
        $result['basis_detail']['total'] = array_sum(array_column($common, 'count'));
    }
    if ($chosen !== null) {
        $number = (int)$chosen['kontonr'];
        $result['kontonr'] = $number;
        $result['account_name'] = $inputs['accounts'][$number]['account_name'];
        $result['vat_code'] = $chosen['vat_code'];
        $result['vat_name'] = $chosen['vat_code'] === null ? null : $inputs['vat_codes'][$chosen['vat_code']];
    }
    $result['alternatives'] = array_map(static function (array $row): array {
        return ['kontonr' => $row['kontonr'], 'account_name' => $row['account_name'],
            'vat_code' => $row['vat_code'], 'count' => $row['count']];
    }, $result['alternatives']);
    return $result;
}

/** @return MobileSchema Query store using online.php or LegacyEngine's selected tenant connection. */
function classification_store(): MobileSchema
{
    return new MobileSchema(ifset($GLOBALS, 'connection'));
}

/**
 * Load live chart, certain vendor, latest reviewer correction and posted evidence.
 * @return array{vendor_key:?string,inputs:array,inputs_sha256:string}
 */
function classification_inputs(array $poolRow, int $regnaar): array
{
    $store = classification_store();
    $vatCodes = [];
    foreach ($store->rows("SELECT kode,kodenr,beskrivelse FROM grupper WHERE art IN ('KM','SM','EM','YM') AND fiscal_year=" . (int)$regnaar . ' ORDER BY art,kodenr,id') as $row) {
        $vatCodes[trim($row['kode']) . (int)$row['kodenr']] = (string)$row['beskrivelse'];
    }
    $accounts = [];
    foreach ($store->rows("SELECT kontonr,beskrivelse,moms FROM kontoplan WHERE regnskabsaar=" . (int)$regnaar
        . " AND kontotype IN ('D','S') AND (lukket IS NULL OR lukket!='on') ORDER BY kontonr,id") as $row) {
        $vat = trim((string)$row['moms']);
        $accounts[(int)$row['kontonr']] = ['account_name' => (string)$row['beskrivelse'],
            'vat_code' => isset($vatCodes[$vat]) ? $vat : null];
    }
    $index = poolVendorLoadIndex();
    $vendor = poolVendorFromRow($poolRow, $index);
    $vendorKey = classification_vendor_key($poolRow, $index);
    $kreditor = null;
    if (poolVendorSuggestion($vendor)['mode'] === 'auto') {
        $kreditor = ['kontonr' => $vendor['kontonr'], 'name' => $vendor['firmanavn'], 'match' => $vendor['match']];
    }
    $correction = null;
    if ($vendorKey !== null) {
        $rows = $store->rows("SELECT id,kontonr,vat_code,kreditor_kontonr,
            to_char(created_at AT TIME ZONE 'UTC','YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS created_at FROM classification_feedback WHERE vendor_key="
            . $store->literal($vendorKey) . " AND role='reviewer' AND outcome='corrected' ORDER BY created_at DESC,id DESC LIMIT 1");
        $correction = $rows ? $rows[0] : null;
        if ($correction !== null) {
            $correction['kreditor'] = null;
            if ($correction['kreditor_kontonr'] !== null) {
                $creditors = $store->rows("SELECT kontonr,firmanavn FROM adresser WHERE art='K' AND kontonr="
                    . $store->literal($correction['kreditor_kontonr']) . " AND (lukket IS NULL OR lukket!='on') ORDER BY id LIMIT 1");
                if ($creditors) {
                    $correction['kreditor'] = ['kontonr' => $creditors[0]['kontonr'], 'name' => $creditors[0]['firmanavn'],
                        'match' => $kreditor !== null && $kreditor['kontonr'] === $creditors[0]['kontonr'] ? $kreditor['match'] : 'name'];
                }
            }
        }
    }
    $window = $store->rows("SELECT (CURRENT_DATE - interval '24 months')::date AS since,CURRENT_DATE AS today")[0];
    $history = [];
    if ($kreditor !== null) {
        $history = array_merge(journal_counter_rows($kreditor['kontonr'], 'K', 'D', 0, true, 0, $window['since']),
            journal_counter_rows($kreditor['kontonr'], 'K', 'K', 0, true, 0, $window['since']));
        $history = array_values(array_filter($history, static fn(array $row): bool => $row['transdate'] <= $window['today']));
    }
    $common = $store->rows("SELECT k.debet AS kontonr,k.d_type AS kontoart,k.debetvat AS vat,k.momsfri,max(k.transdate) AS transdate,count(*) AS count FROM kassekladde k
        JOIN kladdeliste j ON j.id=k.kladde_id AND j.bogfort='V' WHERE k.k_type='K' AND k.transdate BETWEEN "
        . $store->literal($window['since']) . ' AND ' . $store->literal($window['today'])
        . " GROUP BY k.debet,k.d_type,k.debetvat,k.momsfri UNION ALL SELECT k.kredit,k.k_type,k.kreditvat,k.momsfri,max(k.transdate),count(*) FROM kassekladde k
        JOIN kladdeliste j ON j.id=k.kladde_id AND j.bogfort='V' WHERE k.d_type='K' AND k.transdate BETWEEN "
        . $store->literal($window['since']) . ' AND ' . $store->literal($window['today']) . ' GROUP BY k.kredit,k.k_type,k.kreditvat,k.momsfri');
    $ocr = trim((string)ifset($poolRow, 'account', ''));
    $inputs = ['accounts' => $accounts, 'vat_codes' => $vatCodes, 'correction' => $correction,
        'history' => classification_rank($history, $accounts, $vatCodes),
        'common' => classification_rank($common, $accounts, $vatCodes),
        'ocr' => ctype_digit($ocr) ? (int)$ocr : null, 'kreditor' => $kreditor];
    return ['vendor_key' => $vendorKey, 'inputs' => $inputs,
        'inputs_sha256' => hash('sha256', Idempotency::canonicalJson([$regnaar, $vendorKey, $inputs]))];
}

/**
 * Compute without persisting; ClassificationService stores attachment suggestions under its lock.
 * @param int|array $poolFileId Attachment id or normalized vendor pool-shaped fields.
 * @return array Suggestion shape documented at classification_decide().
 */
function classification_suggest($poolFileId, int $regnaar): array
{
    if (is_int($poolFileId)) {
        $rows = classification_store()->rows('SELECT * FROM pool_files WHERE id=' . (int)$poolFileId);
        if (!$rows) {
            throw new MobileT2Error('not_found', 404);
        }
        $poolFileId = $rows[0];
    }
    return classification_decide(classification_inputs($poolFileId, $regnaar)['inputs']);
}

/**
 * Find the stored det-1 proposal for the evidence loaded by classification_inputs().
 * Approval callers resolve under the pool row lock before changing metadata or journal evidence,
 * then pass suggestion_id (including null) to classification_record_feedback().
 *
 * @param array{inputs_sha256:string} $evidence Evidence from the pre-decision pool row and fiscal year.
 * @return ?int Applicable stored proposal, or null when that evidence has never been served.
 */
function classification_applicable_suggestion_id(int $poolFileId, array $evidence): ?int
{
    $store = classification_store();
    $rows = $store->rows('SELECT id FROM classification_suggestions WHERE pool_file_id=' . (int)$poolFileId
        . " AND engine_version='det-1' AND inputs_sha256=" . $store->literal($evidence['inputs_sha256']));
    return $rows ? (int)$rows[0]['id'] : null;
}

/**
 * Append final feedback in the caller's transaction; reviewer writes are committed with approval.
 * An omitted suggestion_id resolves from current evidence; explicit null preserves no proposal.
 * @param array{kontonr:int,vat_code?:?string,kreditor_kontonr?:?string,suggestion_id?:?int,client_id:string} $decision
 * @return array{feedback_id:int,outcome:string,kontonr:int,vendor_key:?string}
 */
function classification_record_feedback(int $poolFileId, array $decision, int $regnaar, string $actor, string $role = 'reviewer'): array
{
    if (pg_transaction_status($GLOBALS['connection']) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Classification feedback requires a caller-owned transaction');
    }
    if (!in_array($role, ['uploader', 'reviewer'], true) || $actor === '') {
        throw new InvalidArgumentException('Invalid feedback actor');
    }
    $clientId = Idempotency::clientId($decision);
    $store = classification_store();
    $rows = $store->rows('SELECT * FROM pool_files WHERE id=' . (int)$poolFileId . ' FOR UPDATE');
    if (!$rows) {
        throw new MobileT2Error('not_found', 404);
    }
    $loaded = classification_inputs($rows[0], $regnaar);
    $number = ifset($decision, 'kontonr');
    if (!is_int($number) || $number <= 0 || !isset($loaded['inputs']['accounts'][$number])) {
        throw new MobileT2Error('validation_failed', 422, ['reason' => 'account_invalid']);
    }
    $vat = ifset($decision, 'vat_code');
    if ($vat !== null && (!is_string($vat) || !isset($loaded['inputs']['vat_codes'][$vat]))) {
        throw new MobileT2Error('validation_failed', 422, ['reason' => 'vat_code_invalid']);
    }
    $creditor = ifset($decision, 'kreditor_kontonr');
    if ($creditor !== null && (!is_string($creditor) || !preg_match('/^[0-9]{1,20}$/D', $creditor)
        || !$store->rows("SELECT id FROM adresser WHERE art='K' AND kontonr=" . $store->literal($creditor) . " AND (lukket IS NULL OR lukket!='on')"))) {
        throw new MobileT2Error('validation_failed', 422, ['reason' => 'kreditor_invalid']);
    }
    $suggestionId = ifset($decision, 'suggestion_id');
    if ($suggestionId !== null && (!is_int($suggestionId) || $suggestionId <= 0)) {
        throw new MobileT2Error('validation_failed', 422, ['reason' => 'metadata_invalid', 'field' => 'suggestion_id']);
    }
    if (!array_key_exists('suggestion_id', $decision)) {
        $suggestionId = classification_applicable_suggestion_id($poolFileId, $loaded);
    }
    $suggestions = $suggestionId === null ? [] : $store->rows('SELECT * FROM classification_suggestions WHERE pool_file_id=' . (int)$poolFileId
        . ' AND id=' . (int)$suggestionId);
    if ($suggestionId !== null && !$suggestions) {
        throw new MobileT2Error('validation_failed', 422, ['reason' => 'metadata_invalid', 'field' => 'suggestion_id']);
    }
    $suggestion = $suggestions ? $suggestions[0] : null;
    $outcome = $suggestion === null ? 'none'
        : ($suggestion['kontonr'] !== null && (int)$suggestion['kontonr'] === $number && $suggestion['vat_code'] === $vat
            && $suggestion['kreditor_kontonr'] === $creditor ? 'accepted' : 'corrected');
    $literalOrNull = static fn($value): string => $value === null ? 'NULL' : $store->literal((string)$value);
    $saved = $store->rows('INSERT INTO classification_feedback (pool_file_id,suggestion_id,vendor_key,role,outcome,kontonr,vat_code,kreditor_kontonr,actor,client_id) VALUES ('
        . (int)$poolFileId . ',' . ($suggestion === null ? 'NULL' : (int)$suggestion['id']) . ','
        . $literalOrNull($loaded['vendor_key']) . ',' . $store->literal($role) . ',' . $store->literal($outcome) . ',' . (int)$number . ','
        . $literalOrNull($vat) . ',' . $literalOrNull($creditor) . ',' . $store->literal($actor) . ',' . $store->literal($clientId) . ') RETURNING id');
    return ['feedback_id' => (int)$saved[0]['id'], 'outcome' => $outcome, 'kontonr' => $number, 'vendor_key' => $loaded['vendor_key']];
}
